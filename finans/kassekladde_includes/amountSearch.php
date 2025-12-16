<?php
/**
 * AJAX endpoint for amount search from open posts
 * Returns amounts from openpost table matching search criteria
 */

ob_start();

@session_start();
$s_id = session_id();
$title = "amountSearch"; 
$modulnr = 0;  
$bg = "nix";   
$header = "nix";
$webservice = true; 

chdir(dirname(__FILE__) . '/..');

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');


$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$accountNr = isset($_GET['account']) ? trim($_GET['account']) : '';
$accountType = isset($_GET['accountType']) ? trim($_GET['accountType']) : ''; // D or K
$invoiceNr = isset($_GET['invoice']) ? trim($_GET['invoice']) : '';


$search_escaped = db_escape_string($search);
$accountNr_escaped = db_escape_string($accountNr);
$invoiceNr_escaped = db_escape_string($invoiceNr);

$accountType = strtoupper(substr($accountType, 0, 1));
if ($accountType !== 'D' && $accountType !== 'K') {
    $accountType = '';
}

$results = array();

$baseWhere = "(openpost.udlignet != '1' OR openpost.udlignet IS NULL)";

if ($accountNr !== '' && $accountType !== '') {
    $baseWhere .= " AND openpost.konto_nr = '$accountNr_escaped'";
    $ktoQuery = db_select("SELECT id FROM adresser WHERE kontonr = '$accountNr_escaped' AND art = '$accountType'", __FILE__ . " line " . __LINE__);
    if ($ktoRow = db_fetch_array($ktoQuery)) {
        $konto_id = $ktoRow['id'];
        $baseWhere .= " AND openpost.konto_id = '$konto_id'";
    }
}


if ($invoiceNr !== '') {
    $baseWhere .= " AND CAST(openpost.faktnr AS TEXT) = '$invoiceNr_escaped'";
}

if ($search !== '') {
    $baseWhere .= " AND (
        CAST(openpost.amount AS TEXT) ILIKE '%$search_escaped%'
        OR CAST(openpost.faktnr AS TEXT) ILIKE '%$search_escaped%'
        OR adresser.firmanavn ILIKE '%$search_escaped%'
    )";
}

$qtxt = "
    SELECT 
        openpost.id,
        openpost.konto_nr,
        openpost.konto_id,
        openpost.faktnr,
        openpost.amount,
        openpost.transdate,
        openpost.beskrivelse,
        openpost.valuta,
        adresser.firmanavn,
        adresser.art
    FROM openpost 
    LEFT JOIN adresser ON openpost.konto_id = adresser.id
    WHERE $baseWhere
    ORDER BY openpost.transdate DESC, openpost.faktnr
    LIMIT 50
";

$query = db_select($qtxt, __FILE__ . " line " . __LINE__);

if ($query) {
    while ($row = db_fetch_array($query)) {
        $offsetAccount = '';
        $accountArt = isset($row['art']) ? trim($row['art']) : '';
        if ($accountArt && isset($row['konto_id'])) {
            $grpQuery = db_select("SELECT gruppe FROM adresser WHERE id = '" . db_escape_string($row['konto_id']) . "'", __FILE__ . " line " . __LINE__);
            if ($grpQuery && $grpRow = db_fetch_array($grpQuery)) {
                $grp = trim($grpRow['gruppe']);
                if ($grp) {
                    $grpArt = $accountArt . 'G'; 
                    $offsetQuery = db_select("SELECT box5 FROM grupper WHERE art = '$grpArt' AND kodenr = '$grp' AND fiscal_year = '$regnaar'", __FILE__ . " line " . __LINE__);
                    if ($offsetQuery && $offsetRow = db_fetch_array($offsetQuery)) {
                        $offsetAccount = trim($offsetRow['box5']);
                    }
                }
            }
        }
        
        $results[] = array(
            'id' => $row['id'],
            'accountNr' => $row['konto_nr'],
            'invoiceNr' => $row['faktnr'],
            'amount' => floatval($row['amount']),
            'date' => $row['transdate'],
            'description' => $row['beskrivelse'],
            'companyName' => $row['firmanavn'],
            'accountType' => $accountArt,
            'currency' => isset($row['valuta']) ? trim($row['valuta']) : '',
            'offsetAccount' => $offsetAccount
        );
    }
}

echo json_encode(array(
    'success' => true,
    'results' => $results,
    'count' => count($results)
));
