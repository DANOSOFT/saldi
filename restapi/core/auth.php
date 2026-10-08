<?php

include_once __DIR__."/logging.php";

$regnaar = NULL;

// 20261008 CL/LH: JWT requests never ran access_check() below, so $regnaar stayed NULL and every
// model that filters on fiscal_year built broken SQL (product groups came back empty, warehouses
// fell back to a made-up default, group accounts were missing). BaseEndpoint::checkAuthorization()
// now sets $regnaar from this after connecting to the account database. Same rule as
// access_check() and /accountingYear/: the fiscal year covering today, else the newest open one.
// Months are compared as numbers - box1-box4 are text, so a SQL text compare puts '9' after '10'.
function api_current_fiscal_year()
{
    $now = (int)date('Y') * 12 + (int)date('n');
    $qtxt = "select kodenr, box1, box2, box3, box4 from grupper where art = 'RA' and (box10 is null or box10 <> 'on') order by id";
    $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    while ($q && $r = db_fetch_array($q)) {
        $start = (int)$r['box2'] * 12 + (int)$r['box1'];
        $end = (int)$r['box4'] * 12 + (int)$r['box3'];
        if ($start <= $now && $now <= $end) {
            return (int)$r['kodenr'];
        }
    }
    $kodenr = newest_active_fiscal_year();
    return $kodenr ? $kodenr : null;
}


function access_check($db, $saldiuser, $user_apikey)
{
    global $sqhost;
    global $squser;
    global $sqpass;
    global $regnaar;

    # Check the db format
    if (isset($db)) {
        (strpos($db, '_')) ? list($master, $db_skriv_id) = explode('_', $db) : $master = $db;
        write_log("Getting db: $master,$db_skriv_id", $db, "INFO");
    } else {
        write_log("Missing database, try setting header 'x-db'", "", "ERROR");
        return 'missing db';
    }

    # Get the accounting system data
    $qtxt = "select id,lukket from regnskab where db='$db'"; #20201223
    $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));

    # Check if the account does not exsist
    if (!$r['id']) {
        write_log("Non existing account $db", $db, "ERROR");
        return "Non existing account $db";
    }

    # Check if the system is turned off by an administrator
    if ($r['lukket'] == 'on') {
        write_log("Account $db closed", $db, "ERROR");
        return "Account $db closed";
    }

    # Try and create a connection to the database
    $ip = $_SERVER['REMOTE_ADDR'];
    $connection = db_connect("$sqhost", "$squser", "$sqpass", "$db", __FILE__ . " linje " . __LINE__);
    if (!$connection) {
        write_log("Unable to connect to $db", $db, "ERROR");
        return "Unable to connect to $db";
    }

    # Check if saldiuser is set correctly
    if (isset($saldiuser)) {
        write_log("Saldibruger: $saldiuser", $db, "INFO");
    } else {
        write_log("Missing saldiuser", $db, "ERROR");
        return 'Missing saldiuser';
    }

    # Confirm the API key and username
    if ($db != $master) {
        # Check fiscal year
        $year = date("Y"); 
        $month = date("m");
        $del1 = "(box1<='$month' and box2<='$year' and box3>='$month' and box4>='$year')";
        $del2 = "(box1<='$month' and box2<='$year' and box3<'$month' and box4>'$year')";
        $del3 = "(box1>'$month' and box2<'$year' and box3>='$month' and box4>='$year')";
        $qtxt = "select kodenr from grupper where art='RA' and ($del1 or $del2 or $del3)"; #20190318
        if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            $regnaar = (int)$r['kodenr'];
        } elseif ($r = db_fetch_array(db_select("select max(kodenr) as kodenr from grupper where art='RA' and box5='on'", __FILE__ . " linje " . __LINE__))) {
            $regnaar = (int)$r['kodenr'];
        } else {
            write_log("Missing or invalid fiscal year", $db, "ERROR");
            return 'Missing or invalid fiscal year';
        } 

        # Get the APIKEY
        $qtxt = "select * from grupper where art = 'API' and kodenr = '1'";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        $r = db_fetch_array($q);
        $api_key = trim($r['box1']);

        # Check IP's, explode if comma seperated
        if (strpos($r['box2'], ',')) {
            $ip_list = explode(',', trim($r['box2']));
        } else {
            $ip_list[0] = trim($r['box2']);
        }

        # Check for apikey validity
        if ($api_key != $user_apikey) {
            $log = fopen("../temp/$db/rest_api.log", "a");
            write_log("Access denied (key) $api_key != $user_apikey", $db, "ERROR");
            return "Access denied (key)";
        } elseif (!in_array($ip, $ip_list) && !in_array('*', $ip_list)) {
            write_log("Access denied (ip) ($ip) != ($r[box2])", $db, "ERROR");
            return "Access denied (ip)";
        } else {
            write_log("Access granted ($ip)", $db, "INFO");
            return 'OK';
        }
    }
    return 'OK';
}