<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/ordreliste.php -----patch 5.0.0 ----2026-02-20--------------
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. 
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------

// 20240528 PHR Added $_SESSION['debitorId']
// 20240815 PHR- $title 
// 20250828 PHR error in translation of 'tilbud'
// 20240906 phr Moved $debitorId to settings as 20240528 didnt work with open orders ??
// 20240106 PBLM Added box5 on line 1187 for the extra api client
// 20250415 LOE Updated some variables using if_isset
// 20250605	PHR Removed konto_id from href
// 26062025 PBLM Added link to the whole line almost
// 14082025 Sawaneh Fix invoicelist for english language
// 20251016 MS Changed "$confirm1" and "confirm('$confirm1 $valg?')" to allow complete translation
// 20251104 LOE General 0verhaul of this file to fit the new grid framework.
// 20260127 LOE Selected calender type now saved for the user.
// 20260207 LOE Fixed a bug created by git merge
// 20260212 PHR Disabled popup checker
// 20260216 LOE Updated delivery note navigation behaviour. 20260220 + locat
@session_start();
$s_id = session_id();

$css = "../css/std.css?v=24"; 

print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/overlib.js\"></script>";

global $sprog_id;
$modulnr = 5;
$api_encode = NULL;
$check_all = $checked = $cols = NULL;
$dk_dg = NULL;
$fakturadatoer = $fakturanumre = $firma = $firmanavn = $firmanavn_ant = NULL;
$genfakt = $genfaktdatoer = $genfakturer = NULL;
$hreftext = $hurtigfakt = NULL;
$ialt_m_moms = NULL;
$ialt_kostpris = NULL;
$konto_id = $kontonumre = NULL;
$lev_datoer = $linjebg = NULL;
$ny_sort = NULL;
$ordreantal = $ordredatoer = $ordrenumre = NULL;
$readonly = $ref[0] = NULL;
$shop_ordre_id = $summer = NULL;
$totalkost = $tr_title = NULL;
$uncheck_all = $understreg = NULL;
$vis_projekt = $vis_ret_next = $who = NULL;
$tidspkt = 0;
$timestamp = date('U');
$find = array(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
$padding2 = $padding = $padding1_5 = null; #20211018
include("../includes/connect.php");
include("../includes/std_func.php");
$title = findtekst('1201|Ordreliste • Kunder', $sprog_id);
include("../includes/online.php");
include("../includes/udvaelg.php");
include("../includes/row-hover-style-with-links.js.php");
$sprog_id = if_isset($sprog_id, 1);
/* 
* check for popup blocker 
*/
?>
<script>
  /*   function checkPopupBlocked() {
        var popup = window.open('', 'test', 'width=1,height=1');

        if (!popup || popup.closed || typeof popup.closed == 'undefined') {
            // Popup blocked
            return true;
        } else {
            // Popup allowed - close test popup
            popup.close();
            return false;
        }
    }

    const res = checkPopupBlocked();
    if (res) {
        // Alert the user about the popup blocker (Dansk translation)
        alert("<?php echo findtekst('2719|Din browser blokerer pop-up vinduer. For at kunne bruge rapportfunktionen skal du tillade pop-up vinduer for denne side.', $sprog_id) ?>");
    } else {
        // Proceed with the report functionality
        console.log("Pop-up allowed, proceeding with report functionality.");
    } */
</script>
<?php

# >> Date picker scripts <<
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/jquery-3.6.4.min.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/moment.min.js\"></script>";
print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/daterangepicker.min.js\" defer></script>";
print '<link rel="stylesheet" type="text/css" href="../css/daterangepicker.css" />';
include("../includes/row-hover-style-with-links.js.php");
include("../includes/datepkr.php");


global $color;
//	
#print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Ordreliste - Kunder</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";

$aa           = findtekst('360|Firmanavn', $sprog_id);
$firmanavn1   = ucfirst(str_replace(' ', '_', $aa));
$bb           = findtekst('107|Ordrer', $sprog_id);
$ordrer1      = strtolower(str_replace(' ', '_', $bb));
$cc           = findtekst('893|faktura', $sprog_id);
$faktura1     = strtolower(str_replace(' ', '_', $cc));
$dd           = findtekst('812|Tilbud', $sprog_id);
$tilbud1      = strtolower(str_replace(' ', '_', $dd));
$ee           = findtekst('892|Ordrelistevisning', $sprog_id);
$beskrivelse  = strtolower(str_replace(' ', '_', $ee)); //20210527

$ff           = findtekst('500|Ordrenr.', $sprog_id);
$ordrenr1     = strtolower(str_replace(' ', '_', $ff));
$gg           = findtekst('881|Ordredato', $sprog_id);
$ordredate1   = strtolower(str_replace(' ', '_', $gg));
$hh           = findtekst('804|Kontonr.', $sprog_id);
$kontonr1     = strtolower(str_replace(' ', '_', $hh));
$ii           = findtekst('882|Fakt. nr.', $sprog_id);
$fakturanr1   = strtolower(str_replace(' ', '_', $ii));
$jj           = findtekst('883|Fakt. dato', $sprog_id);
$fakturadate1 = strtolower(str_replace(' ', '_', $jj));
$kk           = findtekst('891|nextfakt', $sprog_id);
$nextfakt1    = strtolower(str_replace(' ', '_', $kk));






#if($h1= db_fetch_array(db_select("select*from grupper where art='OLV' and kode='$valg' and kodenr = '$bruger_id' ",__FILE__ . " linje " . __LINE__))) $q =$h1['box3']; #2021/05/31

$id = if_isset($_GET, NULL, 'id');
$konto_id = if_isset($_GET, NULL, 'konto_id');
if ($konto_id) {
    $qtxt = "update settings set var_value = '$konto_id' where ";
    $qtxt .= "var_name = 'debitorId' and var_grp = 'debitor' and user_id = '$bruger_id'";
    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
} else {
    $qtxt = "select var_value from settings where var_name = 'debitorId' and var_grp = 'debitor' and user_id = '$bruger_id'";
    if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $konto_id  = $r['var_value'];
    #	(isset($_SESSION['debitorId']) && $_SESSION['debitorId']) $konto_id  = $_SESSION['debitorId'];
}
if ($konto_id) $returside = "../debitor/debitorkort.php?id=$konto_id";
else $returside = if_isset($_GET, NULL, 'returside');
if (!$returside) $returside = '../index/menu.php';
$valg = strtolower(if_isset($_GET, NULL, 'valg'));
$sort = if_isset($_GET, NULL, 'sort');
$nysort = if_isset($_GET, NULL, 'nysort');
$kontoid = if_isset($_GET, NULL, 'kontoid');
$genberegn = if_isset($_GET, NULL, 'genberegn');
$start = if_isset($_GET, NULL, 'start');
if (empty($start)) {
    $start = 0;
} #20210817
$vis_lagerstatus = if_isset($_GET, NULL, 'vis_lagerstatus');
$gem = if_isset($_GET, NULL, 'gem');
$gem_id = if_isset($_GET, NULL, 'gem_id');
$download = if_isset($_GET, NULL, 'download');
$hent_nu = if_isset($_GET, NULL, 'hent_nu');
$shop_ordre_id = if_isset($_GET, NULL, 'shop_ordre_id');
$shop_faktura = if_isset($_GET, NULL, 'shop_faktura');
# if ($hent_nu && file_exists("../temp/$db/shoptidspkt.txt")) unlink ("../temp/$db/shoptidspkt.txt");

if (!$returside && $konto_id && !$popup) {
    $returside = "debitorkort.php?id=$konto_id";
}

// View selection - stored in database settings for persistence
// Priority: 1) URL parameter (user clicked tab), 2) Database setting, 3) Default 'ordrer'
if (isset($_GET['valg']) && $_GET['valg']) {
    // User explicitly clicked a tab - use this value
    $valg = strtolower($_GET['valg']);
} else {
    // No valg in URL - try to restore from database setting
    $valg = null;
    $qtxt = "SELECT var_value FROM settings WHERE var_name = 'ordreliste_valg' AND var_grp = 'debitor' AND user_id = '$bruger_id'";
    if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
        $valg = $r['var_value'];
    }
    
    // If no saved setting, check special cases or default to 'ordrer'
    if (!$valg) {
        if (isset($_GET['konto_id']) && $_GET['konto_id']) {
            $valg = "faktura";
        } else {
            $valg = "ordrer";
        }
    }
}

$r2 = db_fetch_array(db_select("select max(id) as id from grupper", __FILE__ . " linje " . __LINE__));

if (db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '3' and box4='on'", __FILE__ . " linje " . __LINE__))) $hurtigfakt = 'on';
if ($valg == "tilbud" && $hurtigfakt) $valg = "ordrer"; //20210323
if ($valg == 'invoice') $valg = 'faktura';
if (!$valg) $valg = "ordrer";
$tjek = array("tilbud", "ordrer", "faktura", "pbs");
if (!in_array($valg, $tjek)) $valg = "ordrer";

// Save the validated valg to database setting for persistence
$qtxt = "SELECT id FROM settings WHERE var_name = 'ordreliste_valg' AND var_grp = 'debitor' AND user_id = '$bruger_id'";
if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $qtxt = "UPDATE settings SET var_value = '$valg' WHERE var_name = 'ordreliste_valg' AND var_grp = 'debitor' AND user_id = '$bruger_id'";
} else {
    $qtxt = "INSERT INTO settings (var_name, var_value, var_grp, user_id) VALUES ('ordreliste_valg', '$valg', 'debitor', '$bruger_id')";
}
db_modify($qtxt, __FILE__ . " linje " . __LINE__);
#if ($valg=="ordrer" && $sort=="fakturanr") $sort="ordrenr";
if ($nysort == 'sum_m_moms') $nysort = 'sum';
$sort = str_replace("ordrer.", "", $sort);
if ($sort && $nysort == $sort) $sort = $sort . " desc";
elseif ($nysort) $sort = $nysort;
db_modify("update ordrer set betalt = '0' where betalt is NULL", __FILE__ . " linje " . __LINE__);

$r2 = db_fetch_array(db_select("select max(id) as id from grupper", __FILE__ . " linje " . __LINE__));

if ($r = db_fetch_array(db_select("select id from adresser where art = 'S' and pbs_nr > '0'", __FILE__ . " linje " . __LINE__))) {
    $pbs = 1;
} else $pbs = 0;


$box5 = select_valg("$valg", "box5");

$box3 = select_valg("$valg", "box3");
$box4 = select_valg("$valg", "box4");
$box6 = select_valg("$valg", "box6");

$qtxt = "select id from grupper where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'";
if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
    $qtxt = "insert into grupper (beskrivelse,kode,kodenr,art,box2,box3,box4,box5,box6,box7) values ";
    $qtxt .= "('$beskrivelse','$valg','$bruger_id','OLV','$returside','$box3','$box4','$box5','$box6','100')";
    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
} else {
    $qtxt = "select * from grupper where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'"; #20210623
    if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
        $box6 = $r['box6'];
        $c = explode(",", $box6);
        $c = array_map('trim', $c);
        if (!in_array(trim("$firmanavn1"), $c)) {
            $qtxt = "update grupper set beskrivelse='$beskrivelse',kode='$valg',kodenr='$bruger_id',box2='$returside',";
            $qtxt .= "box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='100' where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'";
        }
    } else {
        $qtxt = "update grupper set box3='$box3',box6='$box6' where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'";
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
    }
    $qtxt = "select box2,box7,box8,box9 from grupper where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'";
    $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
    if (!$returside) {
        $returside = $r['box2'];
        if (strstr($returside, "debitorkort.php?id=") && !$konto_id) {
            list($tmp, $konto_id) = explode("=", $returside);
        }
    }
    $linjeantal = $r['box7'];
    if (!$sort) $sort = $r['box8'];
    $find = explode("\n", $r['box9']);
}
if (!$returside) {
    #	$r=db_fetch_array(db_select("select box2,box7 from grupper where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__)); 
    #	$returside=$r['box2'];
    #	$linjeantal=$r['box7'];
    if ($popup) $returside = "../includes/luk.php";
    else $returside = "../index/menu.php";
} elseif (!$popup && $returside == "../includes/luk.php") $returside = "../index/menu.php";
$qtxt = "update grupper set box2 = '$returside', box8 = '$sort' where art = 'OLV' and kode = '$valg' and kodenr = '$bruger_id'";
db_modify($qtxt, __FILE__ . " linje " . __LINE__);
if (!$popup) {
    $qtxt = "update ordrer set hvem='', tidspkt='' where hvem='$brugernavn' and art like 'D%' and status < '3'";
    db_modify($qtxt, __FILE__ . " linje " . __LINE__); #20150308
}


$tidspkt = date("U");

// ADD MISSING POST DATA HANDLING
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $submit = if_isset($_POST, NULL, 'submit');
    if ($submit) {
        if (strstr($submit, "Genfaktur")) $submit = "Genfakturer";
        $find = if_isset($_POST['find'], array());
        $valg = if_isset($_POST['valg'], $valg);
        $sort = if_isset($_POST['sort'], $sort);
        $nysort = if_isset($_POST['nysort'], $nysort);
        $firma = if_isset($_POST['firma'], NULL);
        $kontoid = if_isset($_POST['kontoid'], $kontoid);
        $firmanavn_ant = if_isset($_POST['firmanavn_antal'], NULL);
    } elseif (isset($_POST["clear"])) {
        // Clear all search criteria
        $find = array();
        $konto_id = NULL;
        $udvaelg = NULL;
        $kontoid = NULL;
        $firma = NULL;
        $firmanavn_ant = NULL;
        $datagrid_id = if_isset($_POST, NULL, 'datagrid_id');

        if ($datagrid_id) {
            // Clear datagrid filters
            db_modify("delete from datatables where tabel_id = '$datagrid_id' and user_id = '$bruger_id'", __FILE__ . " linje " . __LINE__);
        }

        // Clear the stored search criteria in the database
        $qtxt = "update grupper set box9='' where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'";
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);

        // Also clear the settings for debitorId
        $qtxt = "update settings set var_value = NULL where var_name = 'debitorId' and var_grp = 'debitor' and user_id = '$bruger_id'";
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);

        // Redirect to the same page with only the view type preserved
        print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?valg=$valg\">";
        exit;
    }

    // Process order selections
    $ordre_id = if_isset($_POST, NULL, 'ordre_id');
    $checked = if_isset($_POST, NULL, 'checked');
}
if (isset($_POST['check']) || isset($_POST['uncheck'])) { 
    if (isset($_POST['check'])) $check_all = 'on';
    else $uncheck_all = 'on';
}

/* 20141106
if (($firma)&&($firmanavn_ant>0)) {
	for ($x=1; $x<=$firmanavn_ant; $x++) {
		$tmp="firmanavn$x";
		if ($firma==$_POST[$tmp]) {
			$tmp="konto_id$x";
			$kontoid=$_POST[$tmp];
		}
	}
}
elseif ($firmanavn_ant>0) {$kontoid='';}
*/
if (!$valg) $valg = "ordrer"; //20210323
if (!$sort) $sort = 'ordrenr desc';

$sort = str_replace("ordrer.", "", $sort); #2008.02.05
$sortering = $sort;

if ($valg != "faktura") { //20210323
    #	$fakturanumre='';
    #	$fakturadatoer='';
    $genfakturer = '';
}


if ($valg == "tilbud") {
    $status = "ordrer.status = 0";
} elseif ($valg == "faktura") {
    $status = "ordrer.status >= 3";
} elseif ($valg == "ordrer" && $hurtigfakt) {
    $status = "ordrer.status < 3";
} else {
    $status = "(ordrer.status = 1 or ordrer.status = 2)";
}

if ($r = db_fetch_array(db_select("select distinct id from ordrer where projekt > '0' and $status", __FILE__ . " linje " . __LINE__))) $vis_projekt = 'on';



if ($menu == 'T') include_once 'ordLstIncludes/topMenu.php';
elseif ($menu == 'S') include_once 'ordLstIncludes/topLine.php';
else include_once 'ordLstIncludes/oldTopLine.php';
include(get_relative() . "includes/orderFuncIncludes/grid_order.php"); 



////// Tutorial //////

$steps = array();
$steps[] = array(
    "selector" => "#ordrer",
    "content" => findtekst('2610|Her ser du en liste af alle dine ordrer', $sprog_id) . "."
);
$steps[] = array(
    "selector" => "#ny",
    "content" => findtekst('2611|For at oprette en ny ordre, klik her', $sprog_id) . "."
);

$steps[] = array(
    "selector" => "#visning",
    "content" => findtekst('2612|For at ændre, hvad der vises i oversigten, klik her', $sprog_id) . "."
);

include(__DIR__ . "/../includes/tutorial.php");
create_tutorial("order-list", $steps);

////// Tutorial end //////

$qtxt = "select box3,box4,box5,box6,box10 from grupper where art = 'OLV' and kodenr = '$bruger_id' and kode='$valg'";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
$vis_felt = explode(",", $r['box3']);
$feltbredde = explode(",", $r['box4']);
$justering = explode(",", $r['box5']);
$feltnavn = explode(",", $r['box6']);
$vis_feltantal = count($vis_felt);
if ($r['box10']) $dropDown = explode(",", $r['box10']);
else {
    $selectfelter = array("firmanavn", "konto_id", "bynavn", "land", "lev_navn", "lev_addr1", "lev_addr2", "lev_postnr", "lev_bynavn", "lev_kontakt", "ean", "institution", "betalingsbet", "betalingsdage", "art", "momssats", "ref", "betalt", "valuta", "sprog", "mail_fakt", "pbs", "mail", "mail_cc", "mail_bcc", "mail_subj", "mail_text", "udskriv_til", "kundegruppe");
    for ($i = 0; $i < $vis_feltantal; $i++) {
        (in_array(strtolower($vis_felt[$i]), $selectfelter)) ? $dropDown[$i] = 'on' : $dropDown[$i] = '';
        ($i < 1) ? $box10 = $dropDown[$i] : $box10 .= ',' . $dropDown[$i];
    }
    $qtxt = "update grupper set box10='$box10' where art = 'OLV' and kodenr = '$bruger_id' and kode='$valg'";
    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
$tekstfelter = array("cvrnr", "email", "kontakt", "firmanavn", "addr1", "addr2", "ref"); #20160901
$gem_fra = $gem_til = NULL;
if (in_array('kundeordnr', $vis_felt)) {
    for ($i = 0; $i < count($vis_felt); $i++) {
        if ($vis_felt[$i] == 'kundeordnr') {
            if (strpos($find[$i], ":")) list($gem_fra, $gem_til) = explode(":", $find[$i]);
            elseif ($find) $gem_fra = $find[$i];
        }
    }
    if ($gem_fra && $gem_til && $gem_til - $gem_fra > 10) $gem_fra = $gem_til = NULL;
}




########################


// Grid-specific parameters
$grid_id = "ordrelst_$valg";


// If konto_id is in GET and search fields are not already set, pre-populate from adresser
if (isset($_GET['konto_id']) && $_GET['konto_id']) {
    $debug_log[] = "konto_id found in GET, processing...";

    // Initialize search array if it doesn't exist
    if (!isset($_GET['search'])) {
        $_GET['search'] = array();
        $debug_log[] = "Initialized \$_GET['search'] array";
    }
    if (!isset($_GET['search'][$grid_id])) {
        $_GET['search'][$grid_id] = array();
        $debug_log[] = "Initialized \$_GET['search'][$grid_id] array";
    }

    $debug_log[] = "Current search values: " . json_encode($_GET['search'][$grid_id]);

    // Only pre-populate if search fields are empty
    if (empty($_GET['search'][$grid_id]['firmanavn']) && empty($_GET['search'][$grid_id]['kontonr'])) {
        $konto_id_from_get = db_escape_string($_GET['konto_id']);
        // $qtxt = "SELECT firmanavn, kontonr FROM adresser WHERE id = '$konto_id_from_get'";
         $qtxt = "SELECT  kontonr FROM adresser WHERE id = '$konto_id_from_get'";
        $debug_log[] = "Query to fetch customer: $qtxt";

        if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            $debug_log[] = "Customer found: " . json_encode($r);

            // if (!empty($r['firmanavn'])) {
            //     $_GET['search'][$grid_id]['firmanavn'] = $r['firmanavn'];
            //     $debug_log[] = "Set firmanavn search: " . $r['firmanavn'];
            // }
            if (!empty($r['kontonr'])) {
                $_GET['search'][$grid_id]['kontonr'] = $r['kontonr'];
                $debug_log[] = "Set kontonr search: " . $r['kontonr'];
            }
        } else {
            $debug_log[] = "ERROR: No customer found with id = $konto_id_from_get";
        }
    } else {
        $debug_log[] = "Search fields already populated, skipping";
    }
} else {
    $debug_log[] = "konto_id NOT in GET or empty";
}

$grid_search = if_isset($_GET['search'][$grid_id], array());

$grid_offset = if_isset($_GET['offset'][$grid_id], 0);
$grid_rowcount = if_isset($_GET['rowcount'][$grid_id], 100);
$grid_sort = if_isset($_GET['sort'][$grid_id], '');

// Also check how many orders exist for this konto_id
if ($konto_id) {
    $test_query = "SELECT COUNT(*) as count FROM ordrer WHERE konto_id = '$konto_id' AND status >= 3";
    if ($test_r = db_fetch_array(db_select($test_query, __FILE__ . " linje " . __LINE__))) {
        $debug_log[] = "Total invoices for konto_id $konto_id: " . $test_r['count'];
    }

    // Check with firmanavn search
    if (!empty($grid_search['firmanavn'])) {
        $test_query2 = "SELECT COUNT(*) as count FROM ordrer WHERE firmanavn = '" . db_escape_string($grid_search['firmanavn']) . "' AND status >= 3";
        if ($test_r2 = db_fetch_array(db_select($test_query2, __FILE__ . " linje " . __LINE__))) {
            $debug_log[] = "Total invoices for firmanavn '{$grid_search['firmanavn']}': " . $test_r2['count'];
        }
    }
}


// Initialize totals
$ialt = 0;
$ialt_m_moms = 0;
$ialt_kostpris = 0;

//Fetch ALL columns from the ordrer table
$all_db_columns = array();
$qtxt = "SELECT column_name, data_type 
         FROM information_schema.columns 
         WHERE table_schema = 'public' 
           AND table_name = 'ordrer' 
         ORDER BY ordinal_position";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
    $all_db_columns[$r['column_name']] = $r['data_type'];
}

###########date range

/**
 * Generate SQL condition for date range search
 */
function generateDateRangeSearch($column, $term) {
    $field = $column['sqlOverride'] ?: $column['field'];
    $term = db_escape_string(trim($term, "'"));
    
    if (empty($term)) {
        return "1=1";
    }
    
    // Check if it's a date range (contains " : " or " - ")
    if (strpos($term, ' : ') !== false || strpos($term, ' - ') !== false) {
        // Normalize to colon separator for splitting
        $term = str_replace(' - ', ' : ', $term);
        $dates = explode(' : ', $term);
        
        if (count($dates) == 2) {
            $startDate = trim($dates[0]);
            $endDate = trim($dates[1]);
            
            // Convert DD-MM-YYYY to YYYY-MM-DD for SQL
            $startParts = explode('-', $startDate);
            $endParts = explode('-', $endDate);
            
            if (count($startParts) == 3 && count($endParts) == 3) {
                $sqlStartDate = $startParts[2] . '-' . $startParts[1] . '-' . $startParts[0];
                $sqlEndDate = $endParts[2] . '-' . $endParts[1] . '-' . $endParts[0];
                
                return "({$field} >= '$sqlStartDate' AND {$field} <= '$sqlEndDate')";
            }
        }
    }
    
    // Single date search
    $parts = explode('-', $term);
    if (count($parts) == 3) {
        $sqlDate = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        return "({$field} = '$sqlDate')";
    }
    
    return "1=1";
}
###########

// Default
$custom_columns = array(
    "ordrenr" => array(
        "field" => "ordrenr",
        "headerName" => findtekst('500|Ordrenr.', $sprog_id),
        "width" => "0.8",
        "align" => "right",
        "type"  => "number",
        "sortable" => true,
        "defaultSort" => true,
        "defaultSortDirection" => "desc",
        "searchable" => true,
        "generateSearch" => function ($column, $term) {
            $term = db_escape_string(trim($term, "'"));
            if (empty($term)) {
                return "1=1";
            }
            // Handle range search (e.g., "100:200")
            if (strpos($term, ':') !== false) {
                list($from, $to) = explode(':', $term, 2);
                $from = intval($from);
                $to = intval($to);
                return "(o.ordrenr >= $from AND o.ordrenr <= $to)";
            }
            // Partial match - search for orders containing the number
            return "(CAST(o.ordrenr AS TEXT) ILIKE '%$term%')";
        },
        "valueGetter" => function ($value, $row, $column) {
            // Return raw value without decimal formatting
            return $value;
        },
        "render" => function ($value, $row, $column) {
            global $brugernavn;
            global $vis_lagerstatus, $ls_vgr, $sprog_id;
            $href = "ordre.php?tjek={$row['id']}&id={$row['id']}&returside=" . urlencode($_SERVER["REQUEST_URI"]);
            
            $timestamp = $row['tidspkt'];
            if (strpos($timestamp, ':')) {
                $timestamp = strtotime(date('Y/m/d') . " " . $timestamp);
            } else {
                $timestamp = strtotime($timestamp);
            }
            $current_time = time();
            $who = $row['hvem'];
            
            $is_editable = ($row['status'] >= 3 || ($current_time - $timestamp) > 3600 || $who == $brugernavn || $who == '');
            
            if ($is_editable) {
                $style = "cursor: pointer; text-decoration: underline;";
                $title = findtekst('1522|Fortsæt med at redigere ordren', $sprog_id);
                $onclick = "onClick=\"window.location.href='$href'\"";
            } else {
                $style = "color: #FF0000; cursor: not-allowed;";
                $title = findtekst('1421|Ordre er i brug af', $sprog_id) . " $who";
                $onclick = "";
            }
            
            if ($row['art'] == 'DK') {
                $display = "(KN)&nbsp;$value";
            } else if ($row['restordre'] == '1') {
                $display = "(R)&nbsp;$value";
            } else {
                $display = $value;
            }

            // vis_lagerstatus: wrap display in overlib span with stock details tooltip
            if ($vis_lagerstatus && $row['art'] != 'DK' && $row['restordre'] != '1') {
                $id = $row['id'];
                $spantxt = "<table><tbody>";
                $spantxt .= "<tr><td>Varenr</td><td>" . findtekst('948|Beholdning', $sprog_id) . "</td><td>" . findtekst('916|Antal', $sprog_id) . "</td><td>" . findtekst('1190|Leveret', $sprog_id) . "</td><td>" . findtekst('1428|Bestilt', $sprog_id) . "</td><td>" . findtekst('1429|Reserveret', $sprog_id) . "</td><td>" . findtekst('1430|I bestilling', $sprog_id) . "</td><td>" . findtekst('976|Disponibel', $sprog_id) . "</td></tr>";
                $q_ls = db_select("select * from ordrelinjer where ordre_id='$id' and antal != '0'", __FILE__ . " linje " . __LINE__);
                if ($q_ls) while ($r_ls = db_fetch_array($q_ls)) {
                    if (!$r_ls['vare_id']) continue;
                    $q2_ls = db_select("select beholdning, gruppe from varer where id='{$r_ls['vare_id']}'", __FILE__ . " linje " . __LINE__);
                    if (!$q2_ls) continue;
                    $r2_ls = db_fetch_array($q2_ls);
                    if (!$r2_ls) continue;
                    $tmp_ls = find_beholdning($r_ls['vare_id'], NULL);
                    $beholdning = $r2_ls['beholdning'];
                    $antal = $r_ls['antal'];
                    $leveret = $r_ls['leveret'];
                    $needed = $antal - $leveret;
                    $is_lagerfrt = in_array($r2_ls['gruppe'], $ls_vgr);

                    if ($beholdning - $needed < 0 && $beholdning + $tmp_ls[4] - $needed >= 0 && $is_lagerfrt) {
                        $spanbg = '#FFFF66';
                    } elseif ($beholdning - $needed < 0 && $is_lagerfrt) {
                        $spanbg = '#FF4D4D';
                    } elseif ($antal != $leveret) {
                        $spanbg = '#66FF66';
                    } else {
                        $spanbg = '#FF33FF';
                    }

                    if ($spanbg != '#FF33FF' && $spanbg != '#66FF66') {
                        $spantxt .= "<tr bgcolor=$spanbg><td>$r_ls[varenr]</td><td align=right>" . dkdecimal($beholdning * 1, 0) . "</td>";
                        $spantxt .= "<td align=right>" . dkdecimal($antal * 1, 0) . "</td><td align=right>" . dkdecimal($leveret * 1, 0) . "</td>";
                        $spantxt .= "<td align=right>$tmp_ls[1]</td><td align=right>$tmp_ls[2]</td><td align=right>$tmp_ls[3]</td><td align=right>$tmp_ls[4]</td></tr>";
                    }
                }
                $spantxt .= "<tr><td colspan=100><hr></td></tr>";
                $spantxt .= "<tr><td>Magenta</td><td colspan=7>" . findtekst('2403|Alt leveret', $sprog_id) . "</td></tr>";
                $spantxt .= "<tr><td>Grøn</td><td colspan=7>" . findtekst('1431|På lager', $sprog_id) . "</td></tr>";
                $spantxt .= "<tr><td>Gul</td><td colspan=7>" . findtekst('1432|Delvist på lager', $sprog_id) . "</td></tr>";
                $spantxt .= "<tr><td>Rød</td><td colspan=7>" . findtekst('1433|Ikke på lager', $sprog_id) . "</td></tr>";
                $spantxt .= "</tbody></table>";
                $display = "<span onmouseover=\"return overlib('" . $spantxt . "', WIDTH=800);\" onmouseout=\"return nd();\">" . $display . "</span>";
            }
            
            return "<td align='$column[align]' style='$style' $onclick title='$title'>$display</td>";
        }
    ),
    
    "ordredate" => array(
        "field" => "ordredate",
        "headerName" => findtekst('881|Ordredato', $sprog_id),
        "width" => "1",
        "type" => "date",
        "searchable" => true,
        "sqlOverride" => "o.ordredate", //override default query
        "generateSearch" => function ($column, $term) {
            return generateDateRangeSearch($column, $term);
        },
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>" . dkdato($value) . "</td>";
        }
    ),
    
    "levdate" => array(
        "field" => "levdate",
        "headerName" => findtekst('886|Dato for levering', $sprog_id),
        "width" => "1",
        "type" => "date",
        "searchable" => true,
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>" . dkdato($value) . "</td>";
        }
    ),
    
    "fakturanr" => array(
        "field" => "fakturanr",
        "headerName" => findtekst('882|Fakt. nr.', $sprog_id),
        "width" => "0.8",
        "align" => "right",
        "type" => "number",
        "sortable" => true,
        "searchable" => true,
        "hidden" => ($valg != "faktura"),
        "sqlOverride" => "CAST(NULLIF(o.fakturanr, '') AS INTEGER)",
        "generateSearch" => function ($column, $term) {
            $term = db_escape_string(trim($term, "'"));
            if (empty($term)) {
                return "1=1";
            }
            // Handle range search (e.g., "10000:20000")
            if (strpos($term, ':') !== false) {
                list($from, $to) = explode(':', $term, 2);
                $from = intval($from);
                $to = intval($to);
                return "(CAST(NULLIF(o.fakturanr, '') AS INTEGER) >= $from AND CAST(NULLIF(o.fakturanr, '') AS INTEGER) <= $to)";
            }
            // Partial match - search for invoices containing the number
            return "(o.fakturanr ILIKE '%$term%')";
        },
        "valueGetter" => function ($value, $row, $column) {
            // Return raw value without decimal formatting
            return $value;
        },
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>$value</td>";
        }
    ),
    
    "fakturadate" => array(
        "field" => "fakturadate",
        "headerName" => findtekst('883|Fakt. dato', $sprog_id),
        "width" => "1",
        "type" => "date",
        "searchable" => true,
        "hidden" => ($valg != "faktura"),
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>" . dkdato($value) . "</td>";
        }
    ),
    
    "firmanavn" => array(
        "field" => "firmanavn",
        "headerName" => findtekst('360|Firmanavn', $sprog_id),
        "width" => "2",
        "type" => "text",
        "searchable" => true,
        "sqlOverride" => "o.firmanavn",
        "generateSearch" => function ($column, $term) use ($konto_id) {
            global $konto_id;
            $field = $column['sqlOverride'] ?: $column['field'];
            $term = db_escape_string(trim($term, "'"));

            if (empty($term)) {
                return "1=1";
            }

            if ($konto_id && $term && strpos($term, ':') === false) {
                $konto_ids = array();
                $q = db_select("SELECT DISTINCT konto_id FROM ordrer WHERE firmanavn = '$term'", __FILE__ . " linje " . __LINE__);
                while ($r = db_fetch_array($q)) {
                    $konto_ids[] = $r['konto_id'];
                }
                if (count($konto_ids) > 0) {
                    $id_conditions = array();
                    foreach ($konto_ids as $kid) {
                        $id_conditions[] = "o.konto_id = '$kid'";
                    }
                    return "(" . implode(" OR ", $id_conditions) . ")";
                } else {
                    return "o.konto_id = '0'";
                }
            }

            $term = strtolower($term);
            if (strpos($term, '*') !== false) {
                $term = str_replace('*', '%', $term);
                $termLower = mb_strtolower($term);
                $termUpper = mb_strtoupper($term);
                return "({$field} LIKE '$term' OR lower({$field}) LIKE '$termLower' OR upper({$field}) LIKE '$termUpper')";
            } else {
                $termLower = mb_strtolower($term);
                $termUpper = mb_strtoupper($term);
                return "({$field} = '$term' OR lower({$field}) LIKE '$termLower' OR upper({$field}) LIKE '$termUpper' OR lower({$field}) LIKE '%$termLower%' OR upper({$field}) LIKE '%$termUpper%')";
            }
        },
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>$value</td>";
        }
    ),
    
    "nextfakt" => array(
        "field" => "nextfakt",
        "headerName" => "Genfakt.",
        "width" => "1",
        "type" => "date",
        "searchable" => true,
        "hidden" => ($valg != "faktura"),
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>" . dkdato($value) . "</td>";
        }
    ), 
    
    "kontonr" => array(
        "field" => "kontonr",
        "headerName" => findtekst('804|Kontonr.', $sprog_id),
        "width" => "1",
        "sqlOverride" => "o.kontonr",
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>$value</td>";
        }
    ),
    
    "ref" => array(
        "field" => "ref",
        "headerName" => findtekst('884|Sælger', $sprog_id),
        "width" => "1.5",
        "type" => "dropdown",
        "searchable" => true,
        "dropdownOptions" => function () use ($valg) {
            $options = array();
            if ($valg == "tilbud") {
                $status_condition = "status < 1";
            } elseif ($valg == "faktura") {
                $status_condition = "status >= 3";
            } else {
                $status_condition = "(status = 1 OR status = 2)";
            }
            $qtxt = "SELECT DISTINCT ref FROM ordrer WHERE (art = 'DO' OR art = 'DK' OR (art = 'PO' AND konto_id > '0')) AND $status_condition AND ref IS NOT NULL AND ref != '' ORDER BY ref";
            $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
            while ($r = db_fetch_array($q)) {
                $options[] = $r['ref'];
            }
            return $options;
        },
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>$value</td>";
        }
    ),
    
    "sum_m_moms" => array(
        "field" => "sum_m_moms",
        "headerName" => "Sum m. moms",
        "type" => "number",
        "decimalPrecision" => 2,
        "align" => "right",
        "searchable" => true,
        "sqlOverride" => "(o.sum + o.moms)",
        "generateSearch" => function ($column, $term) {
            $field = $column['sqlOverride'];
            $term = db_escape_string(trim($term, "'"));
            if (strpos($term, ':') !== false) {
                list($a, $b) = explode(':', $term, 2);
                $a = usdecimal($a);
                $b = usdecimal($b);
                return "({$field} >= $a AND {$field} <= $b)";
            } else {
                $val = usdecimal($term);
                $tmp1 = $val - 0.005;
                $tmp2 = $val + 0.004;
                return "({$field} >= $tmp1 AND {$field} <= $tmp2)";
            }
        },
        "render" => function ($value, $row, $column) {
            $formatted = is_numeric($value) ? dkdecimal($value, 2) : $value;
            return "<td align='$column[align]'>$formatted</td>";
        }
    ),
    
    "betalingsbet" => array(
        "field" => "betalingsbet",
        "headerName" => findtekst('56|Betalingsbet.', $sprog_id),
        "width" => "1",
        "type" => "dropdown",
        "align" => "left",
        "searchable" => true,
        "hidden" => ($valg != "faktura"),
        "dropdownOptions" => function () use ($valg) {
            $options = array();
            if ($valg == "tilbud") {
                $status_condition = "status < 1";
            } elseif ($valg == "faktura") {
                $status_condition = "status >= 3";
            } else {
                $status_condition = "(status = 1 OR status = 2)";
            }
            $qtxt = "SELECT DISTINCT betalingsbet FROM ordrer WHERE (art = 'DO' OR art = 'DK' OR (art = 'PO' AND konto_id > '0')) AND $status_condition AND betalingsbet IS NOT NULL AND trim(betalingsbet) != '' ORDER BY betalingsbet";
            $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
            while ($r = db_fetch_array($q)) {
                if (trim($r['betalingsbet']) != '') {
                    $options[] = $r['betalingsbet'];
                }
            }
            return $options;
        },
        "generateSearch" => function ($column, $term) {
            $term = db_escape_string(trim($term));
            if ($term === '') {
                return "1=1";
            }
            return "(o.betalingsbet = '$term')";
        },
        "render" => function ($value, $row, $column) {
            return "<td align='$column[align]'>$value</td>";
        }
    ),
    
    "sum" => array(
        "field" => "sum",
        "headerName" => ($valg == "faktura") ? findtekst('885|Fakturasum', $sprog_id) : (($valg == "tilbud") ? findtekst('890|Tilbudssum', $sprog_id) : findtekst('887|Ordresum', $sprog_id)),
        "width" => "1",
        "type" => "number",
        "align" => "right",
        "decimalPrecision" => 2,
        "searchable" => true,
        "render" => function ($value, $row, $column) use ($valg, &$ialt, &$ialt_m_moms, &$ialt_kostpris) {
            global $genberegn;

            if ($genberegn) {
                $kostpris = genberegn($row['id']);
                $row['kostpris'] = $kostpris;
            }

            $formatted = dkdecimal($value, 2);
            $ialt += floatval($value);
            $ialt_m_moms += $row['sum_m_moms'];
            $ialt_kostpris += $row['kostpris'];

            if ($valg == "faktura") {
                $udlignet = intval($row['udlignet']);
                $kostpris = $row['kostpris'];
                $dk_db = '0,00';
                $dk_dg = '0,00';
                $value_numeric = is_numeric($value) ? floatval($value) : floatval(str_replace(',', '.', $value));

                if (!empty($kostpris) && !empty($value) && $value_numeric != 0) {
                    $kostpris_numeric = is_numeric($kostpris) ? floatval($kostpris) : floatval(str_replace(',', '.', $kostpris));
                    $dk_db = dkdecimal($value_numeric - $kostpris_numeric, 2);
                    $dk_dg = dkdecimal(($value_numeric - $kostpris_numeric) * 100 / $value_numeric, 2);
                }

                $style = $udlignet ? "color: #000000;" : "color: #FF0000;";
                $title = $udlignet ? "db: $dk_db - dg: $dk_dg%" : findtekst('1442|Ikke udlignet', $sprog_id) . "\r\ndb: $dk_db - dg: $dk_dg%";
                return "<td align='$column[align]' style='$style' title='$title'>" . htmlspecialchars($formatted) . "</td>";
            }
            return "<td align='$column[align]'>" . htmlspecialchars($formatted) . "</td>";
        }
    ),
    
    "kundeordnr" => array(
        "field" => "kundeordnr",
        "headerName" => findtekst('500|Ordrenr.', $sprog_id),
        "width" => "1",
        "type" => "text",
        "align" => "right",
        "sortable" => true,
        "searchable" => true,
        "hidden" => true,
        "sqlOverride" => "o.kundeordnr",
        "valueGetter" => function ($value, $row, $column) {
            // Return raw value without decimal formatting
            return $value;
        },
        "render" => function ($value, $row, $column) {
            $display = (is_numeric($value) && $value !== '') ? intval($value) : $value;
            return "<td align='{$column['align']}'>$display</td>";
        }
    ),
);

// Build the FINAL $columns array dynamically
$columns = array();

// Define explicit default columns for each view type
$explicit_default_columns = array();
if ($valg == "tilbud") {
    $explicit_default_columns = array("ordrenr", "ordredate", "kontonr", "firmanavn", "ref", "sum");
} elseif ($valg == "faktura") {
    $explicit_default_columns = array("ordrenr", "ordredate", "fakturanr", "fakturadate", "nextfakt", "kontonr", "firmanavn", "ref", "sum");
} else {
    $explicit_default_columns = array("ordrenr", "ordredate", "levdate", "kontonr", "firmanavn", "ref", "sum");
}

// Check if user has saved column preferences in the OLD system (box3)
$qtxt = "select box3 from grupper where art = 'OLV' and kodenr = '$bruger_id' and kode='$valg'";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
$user_column_names = array();

if ($r['box3']) {
    // User has custom column setup in old system
    $user_column_names = explode(",", $r['box3']);
    $user_column_names = array_map('trim', $user_column_names);
    $active_column_names = $user_column_names;
} else {
    // Use explicit defaults based on view type
    $active_column_names = $explicit_default_columns;
}

// First, add all custom columns
foreach ($custom_columns as $field_name => $column_def) {
    // Set hidden property based on whether column is in active list
    $column_def['hidden'] = !in_array($field_name, $active_column_names);
    $columns[] = $column_def;
}

// Add all other database columns
foreach ($all_db_columns as $field_name => $data_type) {
    // Skip technical/internal fields and fields already in custom_columns
    $skip_fields = ['id', 'hvem', 'tidspkt', 'copied', 'scan_id'];
    if (in_array($field_name, $skip_fields) || isset($custom_columns[$field_name])) {
        continue;
    }
    
    // Create automatic definition
    $column_def = array(
        "field" => $field_name,
        "headerName" => ucfirst(str_replace('_', ' ', $field_name)),
        "width" => "1.5",
        "type" => "text",
        "align" => "left",
        "sortable" => true,
        "searchable" => true,
        "hidden" => !in_array($field_name, $active_column_names), // Hidden if not in active list
        "sqlOverride" => "o.$field_name", 
    );
    
    // AUTO-DETECT TYPE AND ADD BOTH valueGetter AND render FUNCTIONS
    // This is the CRITICAL part - every column MUST have both functions
    
    // Date fields
  if (strpos($field_name, 'date') !== false || 
    in_array($field_name, ['ordredate', 'levdate', 'fakturadate', 'nextfakt', 'due_date', 'datotid', 'settletime'])) {
    
    $column_def['type'] = 'date';
    $column_def['align'] = 'left';
    
    // Add date range search capability
    $column_def['generateSearch'] = function ($column, $term) {
        return generateDateRangeSearch($column, $term);
    };
    
    // valueGetter: Just return the raw value
    $column_def['valueGetter'] = function ($value, $row, $column) {
        return $value;
    };
    
    // render: Format the date
    $column_def['render'] = function ($value, $row, $column) {
        $formatted = $value ? dkdato($value) : '';
        return "<td align='{$column['align']}'>" . ordreliste_safe_output($formatted) . "</td>";
    };
}elseif ($field_name == 'betalings_id') {
        // betalings_id (payment ID) should be displayed as plain text, not formatted as a number
        $column_def['type'] = 'text';
        $column_def['align'] = 'left';
        
        // valueGetter: Return the raw value as plain integer string, or empty if no value
        $column_def['valueGetter'] = function ($value, $row, $column) {
            if ($value === null || $value === '' || $value == 0 || $value == '0') return '';
            return intval($value);
        };
        
        // render: Display as plain text, empty if no value
        $column_def['render'] = function ($value, $row, $column) {
            if ($value === null || $value === '' || $value == 0 || $value === 0) return "<td align='{$column['align']}'></td>";
            return "<td align='{$column['align']}'>" . htmlspecialchars($value) . "</td>";
        };
    }elseif ($field_name == 'konto_id') {
        // konto_id should be text, not number
        $column_def['type'] = 'text';
        $column_def['align'] = 'left';
        
        // valueGetter: Return the raw value
        $column_def['valueGetter'] = function ($value, $row, $column) {
            return $value !== null ? $value : '';
        };
        
        // render: Escape HTML
        $column_def['render'] = function ($value, $row, $column) {
            return "<td align='{$column['align']}'>" . htmlspecialchars($value) . "</td>";
        };
    }elseif (in_array($field_name, ['ordrenr', 'fakturanr', 'kontonr', 'kundeordnr', 'cvrnr'])) {
        // These "nr" fields should be displayed as plain text/integers without decimal formatting
        $column_def['type'] = 'number';
        $column_def['align'] = 'right';
        $column_def['decimalPrecision'] = 0;
        
        // valueGetter: Return as integer (strip decimals) for numeric values
        $column_def['valueGetter'] = function ($value, $row, $column) {
            return $value !== null ? $value : '';
        };
        
        // render: Display as plain text (no dkdecimal formatting)
        $column_def['render'] = function ($value, $row, $column) {
            return "<td align='{$column['align']}'>" . $value . "</td>";
        };
    }elseif ($data_type == 'numeric' || $data_type == 'integer' || 
            strpos($field_name, 'sum') !== false || 
            strpos($field_name, 'nr') !== false ||
            strpos($field_name, 'id') !== false ||
            in_array($field_name, ['kostpris', 'moms', 'procenttillag', 'netweight', 'grossweight', 'valutakurs', 'betalingsdage', 'kontakt_tlf', 'phone', 'report_number'])) {
        
        $column_def['type'] = 'number';
        $column_def['align'] = 'right';
        $column_def['decimalPrecision'] = ($data_type == 'integer') ? 0 : 2;


        
        // valueGetter: Return numeric value
        $column_def['valueGetter'] = function ($value, $row, $column) {
            return is_numeric($value) ? $value : 0;
        };
        
        // render: Format the number
        $column_def['render'] = function ($value, $row, $column) {
            if (is_numeric($value)) {
                $precision = isset($column['decimalPrecision']) ? $column['decimalPrecision'] : 2;
                $formatted = dkdecimal($value, $precision);
            } else {
                $formatted = '';
            }
           
            if (!is_numeric($value) && strpos($value, '<span style') !== false) {
                 $formatted = $value;
            }
            return "<td align='{$column['align']}'>" . $formatted . "</td>";
        };
    } 
    // Boolean/status fields (0/1 values)
    elseif (in_array($field_name, ['betalt', 'restordre', 'vis_lev_addr', 'pbs', 'mail_fakt', 'omvbet'])) {
        
        $column_def['type'] = 'dropdown';
        $column_def['align'] = 'center';
        
        // valueGetter: Return the raw value
        $column_def['valueGetter'] = function ($value, $row, $column) {
            return $value;
        };
        
        // render: Show checkmark or X
        $column_def['render'] = function ($value, $row, $column) {
            if ($value == '1' || $value === true) {
                $display = '✓';
                $color = '#008000';
            } elseif ($value == '0' || $value === false) {
                $display = '✗';
                $color = '#FF0000';
            } else {
                $display = '';
                $color = '#000000';
            }
            return "<td align='{$column['align']}' style='color: $color;'>" . $display . "</td>";
        };
    }
    // Default text fields
    else {
        // valueGetter: Return the raw value
        $column_def['valueGetter'] = function ($value, $row, $column) {
            return $value !== null ? $value : '';
        };
        
        // render: Escape HTML
        $column_def['render'] = function ($value, $row, $column) {
            return "<td align='{$column['align']}'>" . htmlspecialchars($value) . "</td>";
        };
    }
    
    // Add dropdown options for specific fields (AFTER valueGetter and render are set)
    if (in_array($field_name, ['status', 'valuta', 'sprog', 'art', 'udskriv_til', 'betalt', 'restordre', 'shop_status', 'digital_status'])) {
        $column_def['type'] = 'dropdown';
        $column_def['dropdownOptions'] = function() use ($field_name, $valg) {
            $options = array();
            
            // Build the status condition based on valg
            if ($valg == "tilbud") {
                $status_condition = "status < 1";
            } elseif ($valg == "faktura") {
                $status_condition = "status >= 3";
            } else {
                $status_condition = "(status = 1 OR status = 2)";
            }
            
            $qtxt = "SELECT DISTINCT $field_name FROM ordrer 
                     WHERE $field_name IS NOT NULL 
                     AND trim($field_name::text) != '' 
                     AND $status_condition
                     ORDER BY $field_name";
            $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
            while ($r = db_fetch_array($q)) {
                if (trim($r[$field_name]) != '') {
                    $options[] = $r[$field_name];
                }
            }
            return $options;
        };
    }
    
    $columns[] = $column_def;
}

// Checkbox
$columns[] = array(
    "field" => "checkbox",
    "headerName" => "O",
    "width" => "0.3",
    "sortable" => false,
    "searchable" => false,
    "align" => "center",
    "hidden" => false, // Always visible
    "valueGetter" => function ($value, $row, $column) {
        return $row['selected'] ?? false;
    },
    "render" => function ($value, $row, $column) {
        $checked = $value ? 'checked' : '';
        return "<td align='center'><input type='checkbox' name='checked[{$row['id']}]' $checked class='deliveryNoteSelect'></td>";
    }
);

$columns[] = array(
    "field" => "actions",
    "headerName" => "Handlinger",
    "width" => "0.5",
    "sortable" => false,
    "searchable" => false,
    "align" => "center",
    "hidden" => false, // Always visible
    "valueGetter" => function ($value, $row, $column) {
        return ''; // Actions don't need a value
    },
    "render" => function ($value, $row, $column) use ($valg, $sprog_id) {
        $actions = "<td align='center'><div style='display:flex;gap:5px;justify-content:center;'>";

        $returside = urlencode($_SERVER["REQUEST_URI"]);
        if ($valg == "ordrer") {
            $actions .= "<a href='formularprint.php?id={$row['id']}&formular=9&udskriv_til=PDF&returside={$returside}' title='" . findtekst('2723|Klik for at udskrive', $sprog_id) . " " . lcfirst(findtekst('574|Plukliste', $sprog_id)) . "'><svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'><path d='M216-96q-29.7 0-50.85-21.15Q144-138.3 144-168v-412q-21-8-34.5-26.5T96-648v-144q0-29.7 21.15-50.85Q138.3-864 168-864h624q29.7 0 50.85 21.15Q864-821.7 864-792v144q0 23-13.5 41.5T816-580v411.86Q816-138 794.85-117T744-96H216Zm0-480v408h528v-408H216Zm-48-72h624v-144H168v144Zm216 240h192v-72H384v72Zm96 36Z'/></svg></a>";
            $actions .= "<a href='formularprint.php?id={$row['id']}&formular=2&udskriv_til=PDF&returside={$returside}' title='" . findtekst('2723|Klik for at udskrive', $sprog_id) . " " . lcfirst(findtekst('575|Ordrebekræftelse', $sprog_id)) . "'><svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'><path d='M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.72-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.16 50.85Q725.68-96 695.96-96H263.72ZM528-624v-168H264v624h432v-456H528ZM264-792v189-189 624-624Z'/></svg></a>";
        } elseif ($valg == "faktura") {
            $actions .= "<a href='formularprint.php?id={$row['id']}&formular=3&udskriv_til=PDF&returside={$returside}' title='" . findtekst('2723|Klik for at udskrive', $sprog_id) . " " . lcfirst(findtekst('576|Følgeseddel', $sprog_id)) . "'><svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'><path d='M216-96q-29.7 0-50.85-21.15Q144-138.3 144-168v-412q-21-8-34.5-26.5T96-648v-144q0-29.7 21.15-50.85Q138.3-864 168-864h624q29.7 0 50.85 21.15Q864-821.7 864-792v144q0 23-13.5 41.5T816-580v411.86Q816-138 794.85-117T744-96H216Zm0-480v408h528v-408H216Zm-48-72h624v-144H168v144Zm216 240h192v-72H384v72Zm96 36Z'/></svg></a>";
            $actions .= "<a href='formularprint.php?id={$row['id']}&formular=4&udskriv_til=PDF&returside={$returside}' title='" . findtekst('2723|Klik for at udskrive', $sprog_id) . " " . lcfirst(findtekst('643|Faktura', $sprog_id)) . "'><svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'><path d='M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.72-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.16 50.85Q725.68-96 695.96-96H263.72ZM528-624v-168H264v624h432v-456H528ZM264-792v189-189 624-624Z'/></svg></a>";
        } elseif ($valg == "tilbud") {
            $actions .= "<a href='formularprint.php?id={$row['id']}&formular=1&udskriv_til=PDF&returside={$returside}' title='" . findtekst('2723|Klik for at udskrive', $sprog_id) . " " . lcfirst(findtekst('812|Tilbud', $sprog_id)) . "'><svg xmlns='http://www.w3.org/2000/svg' height='20px' viewBox='0 -960 960 960' width='20px' fill='#000000'><path d='M336-240h288v-72H336v72Zm0-144h288v-72H336v72ZM263.72-96Q234-96 213-117.15T192-168v-624q0-29.7 21.15-50.85Q234.3-864 264-864h312l192 192v504q0 29.7-21.16 50.85Q725.68-96 695.96-96H263.72ZM528-624v-168H264v624h432v-456H528ZM264-792v189-189 624-624Z'/></svg></a>";
        }

        $actions .= "</div></td>";
        return $actions;
    }
);
// === END DYNAMIC COLUMN DEFINITION ===


// Checkbox column for selection
$metaColumnHeaders = ['']; //

// Filters setup
$filters = array();

// Order type filter
$filters[] = array(
    "filterName" => findtekst('2769|Ordretype', $sprog_id),
    "joinOperator" => "or",
    "options" => array(
        array(
            "name" => findtekst('2770|Tilbud', $sprog_id),
            "checked" => ($valg == "tilbud") ? "checked" : "",
            "sqlOn" => "o.status < 1",
            "sqlOff" => "",
        ),
        array(
            "name" => findtekst('107|Ordrer', $sprog_id),
            "checked" => ($valg == "ordrer") ? "checked" : "",
            "sqlOn" => $hurtigfakt ? "o.status < 3" : "(o.status = 1 OR o.status = 2)",
            "sqlOff" => "",
        ),
        array(
            "name" => findtekst('1777|Fakturaer', $sprog_id),
            "checked" => ($valg == "faktura") ? "checked" : "",
            "sqlOn" => "o.status >= 3",
            "sqlOff" => "",
        ),
        array(
            "name" => "BS",
            "checked" => ($valg == "pbs") ? "checked" : "",
            "sqlOn" => "o.art = 'PO' AND o.konto_id > '0'", // PBS orders
            "sqlOff" => "",
        )
    )
);

###############################Data configuration##############*****************++++++++++++++++

##################
// Build the base WHERE conditions based on order type
$base_where_conditions = "";
if ($valg == "tilbud") {
    $base_where_conditions = "o.status < 1";
} elseif ($valg == "faktura") {
    $base_where_conditions = "o.status >= 3";
} elseif ($valg == "ordrer" && $hurtigfakt) {
    $base_where_conditions = "o.status < 3";
} else {
    $base_where_conditions = "(o.status = 1 OR o.status = 2)";
}

$debug_log[] = "base_where_conditions: $base_where_conditions";

// IMPORTANT: Update the SQL query to include ALL columns dynamically
$select_fields = "o.id as id";
foreach ($all_db_columns as $field_name => $data_type) {
    if ($field_name != 'id') {
        $select_fields .= ", o.$field_name as $field_name";
    }
}
// Add calculated fields
$select_fields .= ", (o.sum::numeric + o.moms::numeric) as sum_m_moms";
$select_fields .= ", CASE 
        WHEN o.status >= 3 THEN
            CASE 
                WHEN EXISTS (
                    SELECT 1 FROM openpost op 
                    WHERE op.faktnr = o.fakturanr::text 
                    AND op.konto_id = o.konto_id 
                    AND ABS(ROUND(op.amount::numeric, 2) - ROUND((o.sum + o.moms)::numeric, 2)) < 0.01
                    AND op.udlignet = '1'
                ) THEN 1
                WHEN o.betalt = '1' THEN 1
                ELSE 0
            END
        ELSE 0 
    END as udlignet";
$select_fields .= ", CASE 
        WHEN EXISTS (
            SELECT 1 FROM ordrelinjer ol 
            WHERE ol.ordre_id = o.id 
            AND ((ol.leveret > 0 AND ol.antal > ol.leveret) 
                 OR (ol.antal > (ol.leveres + ol.leveret)))
        ) THEN 'Mangler'
        WHEN EXISTS (
            SELECT 1 FROM ordrelinjer ol 
            WHERE ol.ordre_id = o.id 
            AND ol.leveret > 0 AND ol.antal = ol.leveret
        ) THEN 'Leveret'
        ELSE 'Intet'
    END as levstatus";

// vis_lagerstatus: Load lagerførte varegrupper (VG groups with box8='on')
$ls_vgr = array();
if ($vis_lagerstatus) {
    $qtxt = "select kodenr from grupper where art='VG' and box8='on'";
    $q_vgr = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    while ($r_vgr = db_fetch_array($q_vgr)) {
        $ls_vgr[] = $r_vgr['kodenr'];
    }
}

$data = array(
    "table_name" => "ordrer",
    "query" => "SELECT 
        $select_fields
    FROM ordrer o
    LEFT JOIN adresser a ON a.id = o.konto_id
    WHERE (o.art = 'DO' OR o.art = 'DK' OR (o.art = 'PO' AND o.konto_id > '0')) 
    AND $base_where_conditions
    AND {{WHERE}}
    ORDER BY {{SORT}}",

    "rowStyle" => function ($row) use ($valg, $vis_lagerstatus, $ls_vgr, $sprog_id) {
        if (!$vis_lagerstatus) return "";

        $id = $row['id'];
        $linjebg = null;

        // Fetch order lines with non-zero quantities
        $q = db_select("select * from ordrelinjer where ordre_id='$id' and antal != '0'", __FILE__ . " linje " . __LINE__);
        if (!$q) return "";
        while ($r = db_fetch_array($q)) {
            if (!$r['vare_id']) continue;
            $q2 = db_select("select beholdning, gruppe from varer where id='{$r['vare_id']}'", __FILE__ . " linje " . __LINE__);
            if (!$q2) continue;
            $r2 = db_fetch_array($q2);
            if (!$r2) continue;

            $tmp = find_beholdning($r['vare_id'], NULL);
            $beholdning = $r2['beholdning'];
            $antal = $r['antal'];
            $leveret = $r['leveret'];
            $needed = $antal - $leveret;
            $is_lagerfrt = in_array($r2['gruppe'], $ls_vgr);

            if ($beholdning - $needed < 0 && $beholdning + $tmp[4] - $needed >= 0 && $is_lagerfrt) {
                // Yellow: Low stock but sufficient with pending
                if ($linjebg === null || $linjebg === '#66FF66') {
                    $linjebg = '#FFFF66';
                }
            } elseif ($beholdning - $needed < 0 && $is_lagerfrt) {
                // Red: Insufficient stock - overrides yellow/green
                if ($linjebg === null || $linjebg === '#FFFF66' || $linjebg === '#FF33FF' || $linjebg === '#66FF66') {
                    $linjebg = '#FF4D4D';
                }
            } elseif ($antal != $leveret && $linjebg === null) {
                // Green: In stock, not yet delivered
                $linjebg = '#66FF66';
            }
        }

        if (!$linjebg) $linjebg = '#FF33FF'; // Magenta: All delivered / sufficient
        return "background-color: $linjebg;";
    },
    "columns" => $columns,
    "filters" => $filters,
    'metaColumnHeaders' => $metaColumnHeaders,
);


##############


// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $checked_orders = if_isset($_POST['checked'], array());

    $submit = if_isset($_POST['submit'], '');
    $slet_valgte = if_isset($_POST['slet_valgte'], '');

    // Handle Genfakturer and Ret actions
    if ($submit == "Genfakturer" || $submit == findtekst('1206|Ret', $sprog_id)) {
        $genfakt = "";
        $y = 0;

        foreach ($checked_orders as $order_id => $value) {
            if ($value == "on") {
                $y++;
                if (!$genfakt) $genfakt = $order_id;
                else $genfakt = $genfakt . "," . $order_id;
            }
        }

        $alert2 = findtekst('1419|Ingen fakturaer er markeret til genfakturering!', $sprog_id);

        if ($y > 0) {
            if ($submit == findtekst('1206|Ret', $sprog_id)) {
                print "<meta http-equiv=\"refresh\" content=\"0;URL=ret_genfakt.php?ordreliste=$genfakt\">";
            } else {
                print "<meta http-equiv=\"refresh\" content=\"0;URL=genfakturer.php?id=-1&ordre_antal=$y&genfakt=$genfakt\">";
            }
            exit;
        } else {
            print "<BODY onLoad=\"javascript:alert('$alert2')\">";
        }
    }

    ######
    $selected_ids = array();
    foreach ($checked_orders as $order_id => $value) {
        if ($value == 'on') {
            $selected_ids[] = $order_id;
        }
    }

    if (!empty($selected_ids)) {
        $id_list = implode(',', $selected_ids);

        $returside = urlencode($_SERVER["REQUEST_URI"]);
        if ($submit == findtekst('880|Udskriv', $sprog_id)) {
            print "<script>window.location.href='formularprint.php?id=-1&ordre_antal=" . count($selected_ids) . "&skriv=$id_list&formular=4&udskriv_til=PDF&returside=$returside'</script>";
        } elseif ($submit == "Send mails") {
            print "<script>window.location.href='formularprint.php?id=-1&ordre_antal=" . count($selected_ids) . "&skriv=$id_list&formular=4&udskriv_til=email&returside=$returside'</script>";
        } elseif ($submit == findtekst('576|Følgeseddel', $sprog_id)) {
            print "<script>window.location.href='formularprint.php?locat=1&id=-1&ordre_antal=" . count($selected_ids) . "&skriv=$id_list&formular=3&udskriv_til=PDF&returside=$returside'</script>";
        } elseif ($slet_valgte == findtekst('1099|Slet', $sprog_id)) {
            include("../includes/ordrefunc.php");
            foreach ($selected_ids as $order_id) {
                slet_ordre($order_id);
            }
        }
    } else {
        // No orders selected for other actions
        if ($submit == findtekst('880|Udskriv', $sprog_id) || $submit == "Send mails") {
            print "<BODY onLoad=\"javascript:alert('" . findtekst('1418|Ingen fakturaer er markeret til udskrivning!', $sprog_id) . "')\">";
        }
    }
}

print "<div style='width: 100%; height: calc(100vh - 34px - 16px);'>";




// Shop order integration logic (ported from ordrelisteOld.php)
if ($r=db_fetch_array(db_select("select box4, box5, box6 from grupper where art='API' and box4 != ''",__FILE__ . " linje " . __LINE__))) {
    $api_fil=trim($r['box4']);
    $api_fil2=trim($r['box5']);
    $api_fil3=trim($r['box6']);
    
    if (file_exists("../temp/$db/shoptidspkt.txt")) {
        $fp=fopen("../temp/$db/shoptidspkt.txt","r");
        $tidspkt=fgets($fp);
        if ($hent_nu) $tidspkt-=1170; 
        fclose ($fp);
    } else $tidspkt = 0;
    
    if ($tidspkt < date("U")-1200 || $shop_ordre_id  || $shop_faktura) {
        $fp=fopen("../temp/$db/shoptidspkt.txt","w");
        fwrite($fp,date("U"));
        fclose ($fp);
        $header="User-Agent: Mozilla/5.0 Gecko/20100101 Firefox/23.0";
        $api_txt="$api_fil?put_new_orders=1";
//		$api_encode='utf-8';
        if ($api_encode) $api_txt.="&encode=$api_encode";
        if ($shop_ordre_id && is_numeric($shop_ordre_id)) $api_txt.="&order_id=$shop_ordre_id";
        elseif ($shop_faktura) $api_txt.="&invoice=$shop_faktura";
        exec ("nohup /usr/bin/wget  -O - -q  --no-check-certificate --header='$header' '$api_txt' > /dev/null 2>&1 &\n");
        if($api_fil2){
            $api_txt="$r[box5]?put_new_orders=1";
    //		$api_encode='utf-8';
            if ($api_encode) $api_txt.="&encode=$api_encode";
            if ($shop_ordre_id && is_numeric($shop_ordre_id)) $api_txt.="&order_id=$shop_ordre_id";
            elseif ($shop_faktura) $api_txt.="&invoice=$shop_faktura";
            exec ("nohup /usr/bin/wget  -O - -q  --no-check-certificate --header='$header' '$api_txt' > /dev/null 2>&1 &\n");
        }
        if($api_fil3){
            $api_txt="$r[box6]?put_new_orders=1";
    //		$api_encode='utf-8';
            if ($api_encode) $api_txt.="&encode=$api_encode";
            if ($shop_ordre_id && is_numeric($shop_ordre_id)) $api_txt.="&order_id=$shop_ordre_id";
            elseif ($shop_faktura) $api_txt.="&invoice=$shop_faktura";
            exec ("nohup /usr/bin/wget  -O - -q  --no-check-certificate --header='$header' '$api_txt' > /dev/null 2>&1 &\n");
        }
    } elseif ($hent_nu) {
         print "<script>alert('Vent 30 sekunder');</script>";
    }
}

// The grid will create its own form - no outer form needed
// Create the grid first (it creates its own form for pagination/search)
create_datagrid($grid_id, $data);

########
if (preg_match('/background-color:([a-fA-F0-9#]+)/', $topStyle, $matches)) {
    $backgroundColor = $matches[1]; // Store the extracted color value
} else {
    $backgroundColor = '#114691'; // Fallback to a default color if no background-color found
}

#####

//data picker
?>
<style>
.daterangepicker .ranges li.active {
    background-color: <?= htmlspecialchars($backgroundColor) ?> !important;
}
.daterangepicker td.active{
     background-color: <?= htmlspecialchars($backgroundColor) ?> !important;

}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var bruger_id = <?php echo json_encode($bruger_id); ?>;
    
    // Initialize date range pickers for date fields
    const dateInputs = document.querySelectorAll(
        "input[name^='search[ordrelst_'][name$='[ordredate]'], " +
        "input[name^='search[ordrelst_'][name$='[levdate]'], " +
        "input[name^='search[ordrelst_'][name$='[nextfakt]'], "+
        "input[name^='search[ordrelst_'][name$='[due_date]'], "+
        "input[name^='search[ordrelst_'][name$='[datotid]'], "+
        "input[name^='search[ordrelst_'][name$='[settletime'], "+
        "input[name^='search[ordrelst_'][name$='[fakturadate]']"
    );
    
    dateInputs.forEach(function(input) {
        // Add autocomplete="off" to prevent browser history dropdown
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('autocapitalize', 'off');
        input.setAttribute('autocorrect', 'off');
        input.setAttribute('spellcheck', 'false');
        
        // Get the field name from input name
        var fieldName = input.name;
        var match = fieldName.match(/search\[(.*?)\]\[(.*?)\]/);
        
        if (!match) return;
        
        var gridId = match[1];
        var field = match[2];
        
        // Initialize variables
        var savedPreference = null;
        var startDate = moment();
        var endDate = moment();
        var chosenLabel = null;
        
        // Function to load saved preference from database
        function loadSavedPreference(callback) {
            $.ajax({
                url: 'save_date_settings.php',
                type: 'POST',
                data: {
                    action: 'get_date_preference',
                    grid_id: gridId,
                    field: field,
                    bruger_id: bruger_id
                },
                success: function(response) {
                    try {
                        if (response && typeof response === 'string') {
                            response = JSON.parse(response);
                        }
                        
                        if (response && response.date_value !== undefined && response.date_value !== null && response.date_value !== '') {
                            if (callback) callback(response);
                        } else {
                            if (callback) callback(null);
                        }
                    } catch(e) {
                        console.log('Error parsing response:', e);
                        if (callback) callback(null);
                    }
                },
                error: function(xhr, status, error) {
                    console.log('Error loading date preference:', error);
                    if (callback) callback(null);
                }
            });
        }
        
        // Load saved preference via AJAX BEFORE initializing picker
        loadSavedPreference(function(preference) {
            if (preference) {
                savedPreference = preference;
                chosenLabel = preference.range_type;
                
                // Parse the saved date value
                var dateValue = preference.date_value;
                if (dateValue.includes(' : ') || dateValue.includes(' - ')) {
                    var separator = dateValue.includes(' : ') ? ' : ' : ' - ';
                    var dates = dateValue.split(separator);
                    
                    if (dates.length >= 2) {
                        var parsedStart = moment(dates[0].trim(), 'DD-MM-YYYY', true);
                        var parsedEnd = moment(dates[1].trim(), 'DD-MM-YYYY', true);
                        
                        if (parsedStart.isValid() && parsedEnd.isValid()) {
                            startDate = parsedStart;
                            endDate = parsedEnd;
                        }
                    }
                } else {
                    // Single date
                    var parsed = moment(dateValue, 'DD-MM-YYYY', true);
                    if (parsed.isValid()) {
                        startDate = parsed;
                        endDate = parsed;
                    }
                }
                
                // Set input value from saved preference ONLY if there's a search value in the URL
                var urlParams = new URLSearchParams(window.location.search);
                var searchKey = 'search[' + gridId + '][' + field + ']';
                var urlSearchValue = urlParams.get(searchKey);
                
                if (urlSearchValue && urlSearchValue.trim() !== '') {
                    input.value = urlSearchValue;
                } else {
                    // URL has no search value (empty or cleared), so leave input empty
                    input.value = '';
                }
            }
            
            initializePicker();
        });
        
        function initializePicker() {
            // Initialize daterangepicker
            $(input).daterangepicker({
                singleDatePicker: false,
                showDropdowns: true,
                autoUpdateInput: false,
                autoApply: false,
                linkedCalendars: false,
                startDate: startDate,
                endDate: endDate,
                minYear: 1900,
                maxYear: parseInt(moment().format('YYYY'), 10) + 10,
                alwaysShowCalendars: true,
                showCustomRangeLabel: true,
                ranges: {
                    'Clear': [],
                    'I dag': [moment(), moment()],
                    'I går': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Sidste 7 dage': [moment().subtract(6, 'days'), moment()],
                    'Sidste 30 dage': [moment().subtract(29, 'days'), moment()],
                    'Denne måned': [moment().startOf('month'), moment().endOf('month')],
                    'Sidste måned': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                    'Dette år': [moment().startOf('year'), moment().endOf('year')],
                    'Sidste år': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
                },
                locale: {
                    format: 'DD-MM-YYYY',
                    separator: ' : ',
                    applyLabel: 'Søg',
                    cancelLabel: 'Ryd',
                    fromLabel: 'Fra',
                    toLabel: 'Til',
                    customRangeLabel: 'Brugerdefineret',
                    daysOfWeek: ['Sø', 'Ma', 'Ti', 'On', 'To', 'Fr', 'Lø'],
                    monthNames: ['Januar', 'Februar', 'Marts', 'April', 'Maj', 'Juni',
                        'Juli', 'August', 'September', 'Oktober', 'November', 'December'
                    ],
                    firstDay: 1
                }
            });
            
            var picker = $(input).data('daterangepicker');
            
            // Set the chosenLabel AFTER initialization if we have a known range type
            if (savedPreference && chosenLabel !== null && chosenLabel !== undefined && chosenLabel !== 'Clear') {
                setTimeout(function() {
                    if (picker) {
                        picker.chosenLabel = chosenLabel;
                        
                        // If the saved range type exists in ranges, apply it
                        if (chosenLabel in picker.ranges) {
                            picker.setStartDate(picker.ranges[chosenLabel][0]);
                            picker.setEndDate(picker.ranges[chosenLabel][1]);
                        }
                        
                        // Update the calendar display
                        picker.updateCalendars();
                        picker.updateView();
                    }
                }, 100);
            } else if (savedPreference && (chosenLabel === null || chosenLabel === undefined)) {
                // We have a date value but unknown range type
                if (picker) {
                    picker.chosenLabel = null;
                }
            }
            
            // When user clicks "Søg" (Apply) button
            $(input).on('apply.daterangepicker', function(ev, picker) {
                // Handle Clear action - just clear input and submit
                if (picker.chosenLabel === 'Clear') {
                    $(this).val('');
                    
                    var form = $(this).closest('form');
                    if (form.length > 0) {
                        form.submit();
                    }
                    
                    picker.hide();
                    return;
                }
                
                var selectedStartDate = picker.startDate.format('DD-MM-YYYY');
                var selectedEndDate = picker.endDate.format('DD-MM-YYYY');
                
                var displayValue;
                if (selectedStartDate === selectedEndDate) {
                    displayValue = selectedStartDate;
                } else {
                    displayValue = selectedStartDate + ' : ' + selectedEndDate;
                }
                
                $(this).val(displayValue);
                
                // Determine the range type to save
                var rangeTypeToSave = picker.chosenLabel;
                if (!rangeTypeToSave || rangeTypeToSave === 'Custom Range' || rangeTypeToSave === 'Brugerdefineret') {
                    rangeTypeToSave = 'Custom';
                }
                
                // Save the range type preference via AJAX
                $.ajax({
                    url: 'save_date_settings.php',
                    type: 'POST',
                    data: {
                        action: 'save_date_preference',
                        grid_id: gridId,
                        field: field,
                        range_type: rangeTypeToSave,
                        date_value: displayValue,
                        bruger_id: bruger_id
                    },
                    success: function(response) {
                        var form = $(input).closest('form');
                        if (form.length > 0) {
                            form.submit();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('Error saving date preference:', error);
                    }
                });
            });
            
            // When user clicks "Ryd" (Cancel) button
            $(input).on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
                
                var form = $(this).closest('form');
                if (form.length > 0) {
                    form.submit();
                }
            });
            
            // Visual feedback when opening - reload saved preference
            $(input).on('show.daterangepicker', function(ev, picker) {
                // Reload saved preference from database when picker opens
                loadSavedPreference(function(freshPreference) {
                    if (freshPreference && freshPreference.date_value) {
                        // Update the picker's chosenLabel with the saved preference
                        if (freshPreference.range_type !== null && freshPreference.range_type !== undefined && freshPreference.range_type !== 'Clear') {
                            picker.chosenLabel = freshPreference.range_type;
                        } else {
                            picker.chosenLabel = null;
                        }
                        
                        // Parse and set the dates from the saved value
                        var dateValue = freshPreference.date_value;
                        if (dateValue.includes(' : ') || dateValue.includes(' - ')) {
                            var separator = dateValue.includes(' : ') ? ' : ' : ' - ';
                            var dates = dateValue.split(separator);
                            
                            if (dates.length >= 2) {
                                var parsedStart = moment(dates[0].trim(), 'DD-MM-YYYY', true);
                                var parsedEnd = moment(dates[1].trim(), 'DD-MM-YYYY', true);
                                
                                if (parsedStart.isValid() && parsedEnd.isValid()) {
                                    picker.setStartDate(parsedStart);
                                    picker.setEndDate(parsedEnd);
                                }
                            }
                        } else {
                            var parsed = moment(dateValue, 'DD-MM-YYYY', true);
                            if (parsed.isValid()) {
                                picker.setStartDate(parsed);
                                picker.setEndDate(parsed);
                            }
                        }
                        
                        // Update calendars
                        picker.updateCalendars();
                        picker.updateView();
                        
                        // Highlight the active range in the list
                        if (picker.chosenLabel && picker.chosenLabel !== 'Clear') {
                            // Remove active class from all ranges
                            picker.container.find('.ranges li').removeClass('active');
                            
                            // Add active class to the saved range
                            picker.container.find('.ranges li').each(function() {
                                if ($(this).text().trim() === picker.chosenLabel) {
                                    $(this).addClass('active');
                                }
                            });
                        }
                    }
                });
            });
        }
    });
});
</script>




<?php

$valg = is_array($valg) ? implode(',', $valg) : $valg;
$sort = is_array($sort) ? implode(',', $sort) : $sort;
// Create a SEPARATE form for bulk actions


// Determine initial state based on POST data and current checkbox states
$anyChecked = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checked'])) {
    foreach ($_POST['checked'] as $value) {
        if ($value == 'on') {
            $anyChecked = true;
            break;
        }
    }
}

// Set initial button text based on whether any checkboxes are checked
$buttonText = $anyChecked ? findtekst('90|Fravælg alle', $sprog_id) : findtekst('89|Vælg alle', $sprog_id);


?>




<style>
    .button.blue.small {
        background-color: <?php echo $backgroundColor; ?> !important;
        color: white;
        border-radius: 4px;
    }
</style>
<?php



// JavaScript for checkbox management

?>

<script>
    // Function to toggle all checkboxes and update button text
    function toggleAllCheckboxes() {
        var checkboxes = document.querySelectorAll('input.deliveryNoteSelect');
        var button = document.getElementById('toggleButton');
        var allChecked = true;

        // Check if all are currently checked
        checkboxes.forEach(function(checkbox) {
            if (!checkbox.checked) {
                allChecked = false;
            }
        });

        // Toggle all checkboxes to the opposite state
        var newState = !allChecked;
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = newState;
        });

        // Update button text
        if (newState) {
            button.innerHTML = "<?php echo addslashes(findtekst('90|Fravælg alle', $sprog_id)); ?>";
        } else {
            button.innerHTML = "<?php echo addslashes(findtekst('89|Vælg alle', $sprog_id)); ?>";
        }

        // Also update the hidden checkboxes in the bulk form
        updateBulkFormCheckboxes();
    }

    // Function to sync checkboxes with bulk form
    function updateBulkFormCheckboxes() {
        var bulkForm = document.getElementById('bulkActionForm');
        var visibleCheckboxes = document.querySelectorAll('input.deliveryNoteSelect');
        var hiddenCheckboxes = bulkForm.querySelectorAll('input.deliveryNoteSelect');

        visibleCheckboxes.forEach(function(visibleCheckbox, index) {
            if (hiddenCheckboxes[index]) {
                hiddenCheckboxes[index].checked = visibleCheckbox.checked;
            }
        });
    }

    // Add event listeners to individual checkboxes to update button state
    document.addEventListener('DOMContentLoaded', function() {
        var checkboxes = document.querySelectorAll('input.deliveryNoteSelect');
        var button = document.getElementById('toggleButton');

        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                updateBulkFormCheckboxes();
                updateToggleButtonState();
            });
        });

        // Initial button state update
        updateToggleButtonState();
    });

    // Update toggle button state based on current checkbox states
    function updateToggleButtonState() {
        var checkboxes = document.querySelectorAll('input.deliveryNoteSelect');
        var button = document.getElementById('toggleButton');
        var allChecked = true;
        var anyChecked = false;

        checkboxes.forEach(function(checkbox) {
            if (checkbox.checked) {
                anyChecked = true;
            } else {
                allChecked = false;
            }
        });

        // Update button text
        if (allChecked && anyChecked) {
            button.innerHTML = "<?php echo addslashes(findtekst('90|Fravælg alle', $sprog_id)); ?>";
        } else {
            button.innerHTML = "<?php echo addslashes(findtekst('89|Vælg alle', $sprog_id)); ?>";
        }
    }
</script>
<?php


print "<script>

// Move checkboxes to bulk action form when clicked
document.addEventListener('DOMContentLoaded', function() {
    var bulkForm = document.getElementById('bulkActionForm');
    var checkboxes = document.querySelectorAll('input.deliveryNoteSelect');
    
    checkboxes.forEach(function(checkbox) {
        // Clone checkbox to bulk form
        var clone = checkbox.cloneNode(true);
        clone.style.display = 'none'; // Hide the duplicate
        bulkForm.appendChild(clone);
        
        // Sync changes between original and clone
        checkbox.addEventListener('change', function() {
            clone.checked = this.checked;
        });
    });
});
</script>";

########

// Calculate and display turnover summary
$dk_db = dkdecimal($ialt - $ialt_kostpris, 2);
if ($ialt != 0) {
    $dk_dg = dkdecimal(($ialt - $ialt_kostpris) * 100 / $ialt, 2);
} else {
    $dk_dg = '0,00';
}
$ialt_formatted = dkdecimal($ialt, 2);
$ialt_m_moms_formatted = dkdecimal($ialt_m_moms, 2);

// NEW unified top control bar
print "<div id='top-control-bar'>";


// ------------------------------------------------------------
// LEFT SIDE
// ------------------------------------------------------------
print "<div id='left-controls' >";

if ($valg == "ordrer" && !$vis_lagerstatus) {
    print "<a href='ordreliste.php?vis_lagerstatus=on&valg=$valg'>"
          . findtekst('810|Vis lagerstatus', $sprog_id) . "</a>  ";
}

if ($valg == "ordrer") {
    $r = db_fetch_array(db_select(
        "select box1 from grupper where art='MFAKT' and kodenr='1'",
        __FILE__ . " linje " . __LINE__
    ));

    if ($r) {
        // print "<a href='csv2ordre.php' target='_blank'>CSV import</a>";
         print "<a href='csv2ordre.php?valg=$valg'>CSV import</a>";

        if ($r['box1'] && $ialt != '0,00') {
            $tekst = "Fakturér alt som kan leveres?";
            print "  <a href='massefakt.php?valg=$valg' 
                        onClick=\"return MasseFakt('$tekst')\">Fakturér alt</a>";
        }
    }
}

// Add Shop Fetch Link if active 
if ($show_shop_link) {
    print "  <a href='ordreliste.php?sort=$sort&hent_nu=1&valg=$valg'>" . findtekst('879|Hent ordrer', $sprog_id) . "</a>";
}

print "</div>";  // END LEFT



// ------------------------------------------------------------
// CENTER — Turnover Summary
// ------------------------------------------------------------


if ($valg == "faktura") {
print "<div id='center-turnover-f' style='flex:1; text-align:left;'>";
print "<div>";
    print "<a href='ordreliste.php?genberegn=1&valg=$valg'>
                <b>" . findtekst('878|Samlet omsætning / db / dg (ekskl. moms.)', $sprog_id) . "</b>
           </a><br>";
    print "$ialt_formatted / $dk_db / $dk_dg%<br>";
    print "<b>" . findtekst('877|Samlet omsætning inkl. moms', $sprog_id)
          . ": $ialt_m_moms_formatted</b>";
} else {
print "<div id='center-turnover' style='flex:1; text-align:center;'>";
print "<div style='display:flex;'>";
    print findtekst('811|Samlet omsætning inkl./ekskl. Moms', $sprog_id) . "<br>";
    print findtekst('2772|db / dg (ekskl. moms)', $sprog_id) . "<br>";
    print "<b style='margin-left: 20px;'>$ialt_m_moms_formatted ($ialt_formatted)<br>
           $dk_db / $dk_dg%</b>";
}

print "</div>";
print "</div>"; // END CENTER



// ------------------------------------------------------------
// RIGHT — Bulk Actions
// ------------------------------------------------------------
print "<div id='right-bulk-actions' style='flex:1; text-align:right;'>";

print "<form method='post' action='ordreliste.php' id='bulkActionForm' style='display:inline;'>";
print "<input type='hidden' name='valg' value='$valg'>";
print "<input type='hidden' name='sort' value='$sort'>";
print "<input type='hidden' name='nysort' value='$nysort'>";
print "<input type='hidden' name='datagrid_id' value='$grid_id'>";

print "<div class='bulk-actions' style='display:inline-block;'>";

print "<strong>" . findtekst('2768|Massehandlinger',$sprog_id) . ":</strong> ";

// Toggle button
print "<button type='submit' id='toggleButton' name='toggle_checkboxes'
        onclick=\"event.preventDefault(); toggleAllCheckboxes();\"
        class='button blue small'>$buttonText</button> ";

// Other right-side buttons
if ($valg == "faktura") {
    print "<input type='submit' name='submit' value='" . findtekst('880|Udskriv',$sprog_id) . "' class='button blue small'> ";
    print "<input type='submit' name='submit' value='" . findtekst('576|Følgeseddel',$sprog_id) . "' class='button blue small'> ";
    print "<input type='submit' name='submit' value='Genfakturer' class='button blue small'> ";
    print "<input type='submit' name='submit' value='Send mails' class='button blue small'> ";
} else {
    print "<input 
        type='submit' 
        name='slet_valgte' 
        value='" . findtekst('1099|Slet',$sprog_id) . "' 
        class='button blue small'
        style='margin-right: 4px;'
        onclick=\"return confirm('Do you want to delete this $valg(s)?');\"
    >";

    if ($valg == "ordrer") {
        print "<input type='submit' name='submit' value='" . findtekst('1206|Ret',$sprog_id) . "' class='button blue small'> ";
    }
}

print "<input type='submit' name='clear' value='" . findtekst('2117|Ryd',$sprog_id) . "' class='button blue small'>";

print "</div>"; // end .bulk-actions
print "</form>";
print "</div>"; // END RIGHT



// CLOSE MAIN WRAPPER
print "</div>";


// Handle recalculate if needed
if ($genberegn == 1) {
    print "<meta http-equiv=\"refresh\" content=\"0;URL='ordreliste.php?genberegn=2&valg=$valg'\">";
}



// Additional API integration
$r = db_fetch_array(db_select("select box2 from grupper where art='DIV' and kodenr='5'", __FILE__ . " linje " . __LINE__));

if (isset($r['box2']) && $apifil = $r['box2']) { //checks if $r$r['box2'] exists before using it
    (strpos($r['box2'], 'opdat_status=1')) ? $opdat_status = 1 : $opdat_status = 0;
    (strpos($r['box2'], 'shop_fakt=1')) ? $shop_fakt = 1 : $shop_fakt = 0;
    (strpos($r['box2'], 'betaling=kort')) ? $kortbetaling = 1 : $kortbetaling = 0;
    ($kortbetaling) ? $betalingsbet = 'betalingskort' : $betalingsbet = 'netto+8';
    if (substr($apifil, 0, 4) == 'http') {
        $apifil = trim(str_replace("/?", "/hent_ordrer.php?", $apifil));
        $apifil = $apifil . "&saldi_db=$db";
        $saldiurl = "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        if ($_SERVER['HTTPS']) $saldiurl = "s" . $saldiurl;
        $saldiurl = "http" . $saldiurl;
        if ($shop_fakt) {
            $qtxt = "select max(shop_id) as shop_id from shop_ordrer";
            $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
            $next_id = $r['shop_id'] + 1;
            $apifil .= "&next_id=$next_id";
        }
        if ($shop_fakt) {
            $shop_ordre_id *= 1;
            $apifil .= "&shop_fakt=$shop_fakt&popup=1&shop_ordre_id=$shop_ordre_id";
        }
        $apifil .= "&saldiurl=$saldiurl";
        $apifil .= "&random=" . rand();
        if ($shop_fakt) {
            if (file_exists("../temp/$db/shoptidspkt.txt")) {
                $fp = fopen("../temp/$db/shoptidspkt.txt", "r");
                $tidspkt = fgets($fp);
            } else $tidspkt = 0;
            fclose($fp);
            if ($tidspkt < date("U") - 300 || $shop_ordre_id) {
                $fp = fopen("../temp/$db/shoptidspkt.txt", "w");
                fwrite($fp, date("U"));
                fclose($fp);
                if ($db == 'bizsys_52') {
                    print "<BODY onLoad=\"JavaScript:window.open('$apifil','hent:ordrer','width=10,height=10,top=1024,left=1280')\">";
                } else exec("nohup /usr/bin/wget --spider $api_fil  > /dev/null 2>&1 &\n");
            } else {
                $tjek = $next_id - 50;
                $qtxt = "select shop_id from shop_ordrer where shop_id >= '$tjek'";
                $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
                while ($r = db_fetch_array($q)) {
                    while ($r['shop_id'] != $tjek && $tjek < $next_id) {
                        $tmp = $apifil . "&shop_ordre_id=$tjek";
                        print "<BODY onLoad=\"JavaScript:window.open('$tmp'	,'hent:ordrer','width=10,height=10,top=1024,left=1280')\">";
                        $tjek++;
                    }
                    $tjek++;
                }
            }
        } else print "<tr><td colspan=\"3\"><span title='" . findtekst('1441|Klik her for at hente nye ordrer fra shop', $sprog_id) . "' onclick=\"JavaScript:window.open('$apifil','hent:ordrer','width=10,height=10,top=1024,left=1280')\">SHOP import</span></td></tr>";
    }
}
######
print "</div>";

#don't show turnover summary and bulk actions if 'kolonner' menu is selected
if (isset($_GET['menu']["$grid_id"])) {
    $menu_value = $_GET['menu']["$grid_id"];
    if ($menu_value == 'kolonner' || $menu_value == 'filtre') {
?>
        <style>
            #top-control-bar,
            .turnover-summary,
            .bulk-actions {
                display: none !important;
            }
        </style>

<?php
    }
}

#################
function genberegn($id)
{
    $kostpris = 0;
    $qtxt = "select id,vare_id,antal,pris,kostpris,saet,samlevare from ordrelinjer where ordre_id = '$id' ";
    $qtxt .= "and posnr > '0' and vare_id > '0' and antal IS NOT NULL and kostpris IS NOT NULL";
    $q0 = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    while ($r0 = db_fetch_array($q0)) {
        $qtxt = "select provisionsfri, gruppe from varer where id = '$r0[vare_id]'";
        if ($r1 = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            $qtxt = "select box9 from grupper where art = 'VG' and kodenr='$r1[gruppe]' and box9 = 'on'";
            if (!$r1['provisionsfri'] && db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
                $kostpris += $r0['kostpris'] * $r0['antal'];
            } elseif ($r1['provisionsfri']) {
                $kostpris += $r0['pris'] * $r0['antal'];
            } else {
                if ($r0['saet'] && $r0['samlevare'] && $r0['kostpris']) {
                    $r0['kostpris'] = 0;
                    db_modify("update ordrelinjer set kostpris='0' where id = '$r0[id]'");
                }
                $kostpris += $r0['kostpris'] * $r0['antal'];
                #					$r2=db_fetch_array(db_select("select kostpris from varer where id = $r0[vare_id]",__FILE__ . " linje " . __LINE__));	
                #					$kostpris=$kostpris+$r2['kostpris']*$r0['antal'];
            }
        }
    }
    db_modify("update ordrer set kostpris=$kostpris where id = $id", __FILE__ . " linje " . __LINE__); #xit;
    return $kostpris;
}

function bidrag($feltnavn, $sum, $moms, $sum_m_moms, $kostpris, $udlignet)
{
    global $genberegn, $ialt, $totalkost, $sprog_id;

    $ialt = $ialt + $sum;
    $totalkost = $totalkost + $kostpris;
    $dk_db = dkdecimal($sum - $kostpris, 2);
    $sum = round($sum, 2);
    $kostpris = round($kostpris, 2);
    if ($sum) $dk_dg = dkdecimal(($sum - $kostpris) * 100 / $sum, 2);
    else $dk_dg = '0,00';
    if ($feltnavn == 'sum') $tmp = $sum;
    elseif ($feltnavn == 'moms') $tmp = $moms;
    elseif ($feltnavn == 'sum_m_moms') $tmp = $sum_m_moms;
    $tmp = dkdecimal($tmp, 2);
    if ($genberegn) {
        print "<span title= 'db: $dk_db - dg: $dk_dg%'>$tmp/$dk_db/$dk_dg%<br></span>";
    } else {
        if ($udlignet) $span = "style='color: #000000;' title='db: $dk_db - dg: $dk_dg%'";
        else $span = "style='color: #FF0000;' title='" . findtekst('1442|Ikke udlignet', $sprog_id) . "\r\ndb: $dk_db - dg: $dk_dg%'";
        print "<span $span>$tmp<br></span>";
    }
}

################
function select_valg($valg, $box)
{  #20210623
    global $bruger_id, $sprog_id, $firmanavn1;
    global $beskrivelse, $ordrenr1, $kontonr1, $fakturanr1, $fakturadate1, $nextfakt1;

    if ($valg == "tilbud") {
        $qtxt = "select * from grupper where art = 'OLV' and kode = 'tilbud' and kodenr = '$bruger_id'";
        if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            return $r[$box];
        } else {
            switch ($box) {
                case "box3":
                    return "ordrenr,ordredate,kontonr,firmanavn,ref,sum";
                case "box5":
                    return "right,left,left,left,left,right";
                case "box4":
                    return "50,100,100,150,100,100";
                case "box6":
                    return "" . findtekst('888|Tilbudsnr.', $sprog_id) . ".," . findtekst('888|Tilbudsnr.', $sprog_id) . "," . findtekst('804|Kontonr.', $sprog_id) . ".," . findtekst('360|Firmanavn', $sprog_id) . "," . findtekst('884|Sælger', $sprog_id) . "," . findtekst('890|Tilbudssum', $sprog_id) . ""; #20210318
                default:
                    return "choose a box";
            }
        }
    } elseif ($valg == "ordrer") {
        $qtxt = "select * from grupper where art = 'OLV' and kode = 'ordrer' and kodenr = '$bruger_id'";
        if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            return $r[$box];
        } else {
            switch ($box) {
                case "box3":
                    return "ordrenr,ordredate,levdate,kontonr,firmanavn,ref,sum";
                case "box5":
                    return "right,left,left,left,left,left,right";
                case "box4":
                    return "50,100,100,100,150,100,100";
                case "box6":
                    return "" . findtekst('500|Ordrenr.', $sprog_id) . ".," . findtekst('881|Ordredato', $sprog_id) . "," . findtekst('886|Dato for levering', $sprog_id) . "," . findtekst('804|Kontonr.', $sprog_id) . ".," . findtekst('360|Firmanavn', $sprog_id) . "," . findtekst('884|Sælger', $sprog_id) . "," . findtekst('887|Ordresum', $sprog_id) . "";
                default:
                    return "choose a box";
            }
        }
    } elseif ($valg == "faktura") {
        $qtxt = "select * from grupper where art = 'OLV' and kode = 'faktura' and kodenr = '$bruger_id'";
        if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            return $r[$box];
        } else {
            switch ($box) {
                case "box3":
                    return "ordrenr,ordredate,fakturanr,fakturadate,nextfakt,kontonr,firmanavn,ref,sum";
                case "box5":
                    return "right,left,right,left,left,left,left,left,right";
                case "box4":
                    return "50,100,100,100,100,150,100,100,100";
                case "box6":
                    return "" . findtekst('500|Ordrenr.', $sprog_id) . "," . findtekst('881|Ordredato', $sprog_id) . "," . findtekst('882|Fakt. nr.', $sprog_id) . "," . findtekst('883|Fakt. dato', $sprog_id) . ",Genfakt.," . findtekst('804|Kontonr.', $sprog_id) . "," . findtekst('360|Firmanavn', $sprog_id) . "," . findtekst('884|Sælger', $sprog_id) . "," . findtekst('885|Fakturasum', $sprog_id) . "";
                default:
                    return "choose a box";
            }
        }
    }
}

################


?>

<script>
    // Get the 'valg' parameter from the URL (if it exists)
    let valgParam = new URLSearchParams(window.location.search).get('valg');

    // If 'valg' isn't in the URL, check if PHP variable '$valg' is set (passed from PHP)
    if (!valgParam) {
        <?php if (isset($valg)): ?>
            valgParam = "<?php echo addslashes($valg); ?>"; // Set from PHP
        <?php else: ?>
            valgParam = "ordrer"; // Default to 'ordrer' if neither is set
        <?php endif; ?>
    }



    // Add 'valg' parameter to all forms in the datagrid
    document.addEventListener('DOMContentLoaded', function() {
        if (valgParam) {
            const forms = document.querySelectorAll('.datatable-wrapper form');

            forms.forEach(form => {
                let valgInput = form.querySelector('input[name="valg"]');
                if (!valgInput) {
                    valgInput = document.createElement('input');
                    valgInput.type = 'hidden';
                    valgInput.name = 'valg';
                    form.appendChild(valgInput);
                }
                valgInput.value = valgParam;
            });
        }
    });
</script>



<script>
    document.addEventListener('DOMContentLoaded', () => {
        try {
            const table = document.querySelector('.datatable');
            if (!table) {
                console.error('[ERROR] No table with class "datatable" found');
                return;
            }

            const rows = table.querySelectorAll('tr'); // Target all <tr> elements in the table

            rows.forEach((row, rowIndex) => {
                try {
                    const cells = Array.from(row.querySelectorAll('td'));
                    if (cells.length < 2) {
                        return; // Skip rows with less than 2 cells
                    }

                    let linkInCell1 = cells[0].getAttribute('onclick');
                    let linkInCell2 = cells[1].getAttribute('onclick');
                    let linkFound = false;


                    if (linkInCell1) {
                        linkFound = true;
                        // Make the entire row clickable
                        row.style.cursor = 'pointer';
                        cells.slice(0, cells.length - 2).forEach(cell => {
                            cell.style.cursor = 'pointer';
                        });
                    }


                    if (linkInCell2) {
                        linkFound = true;

                        cells.slice(1).forEach(cell => {
                            cell.style.cursor = 'pointer';
                        });
                    }

                    if (!linkFound) {
                        return;
                    }

                    // Attach click event to the row to navigate
                    row.addEventListener('click', (event) => {

                        if (event.target.type === 'checkbox') {
                            return;
                        }


                        if (event.target === cells[0]) return;

                        // If the link is in the first cell, navigate to it
                        if (linkInCell1) {
                            eval(linkInCell1);
                        } else if (linkInCell2) {
                            eval(linkInCell2);
                        }
                    });
                } catch (rowErr) {
                    console.error('[ERROR] Failed processing row:', rowErr);
                }
            });
        } catch (err) {
            console.error('[ERROR] DOMContentLoaded handler failed:', err);
        }
    });
</script>

<style>
    #massefakt_div,
    #shop_integration {
        display: inline-block;
        vertical-align: top;
        margin-right: 10px;
    }

    /* Force underline on all links in .turnover-summary*/
    .turnover-summary a,
    .turnover-summary a:link,
    .turnover-summary a:visited,
    .turnover-summary a:hover,
    .turnover-summary a:active , #left-controls{
        text-decoration: underline !important;
    }

   /*  */
   /* Fixed control bar */

.datatable-wrapper {
    height: calc(100vh - 98px) !important;
}

#top-control-bar {
    position: fixed;
    bottom: 10px;
    left: 0;
    right: 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    z-index: 1000; 
}
html, body{
    overflow: hidden;
}
body {
    padding-bottom: 100px; 
}

#left-controls {
    display: flex;         
    flex-direction: column; 
    gap: 5px;              
    align-items: flex-start; 
    flex:1;
    text-align:left;'
}

#toggleButton{
    
  border: 1px solid #ccc;

}


</style>