<?php

ob_start();

@session_start();
$s_id = session_id();
$title = "invoiceSearch"; 
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
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 50; 
$offset = ($page - 1) * $limit;

if (!isset($regnaar) || empty($regnaar)) {
    echo json_encode(array('error' => 'Session expired'));
    exit;
}

$results = array();
$totalCount = 0;

$search_escaped = db_escape_string($search);
$accountNr_escaped = db_escape_string($accountNr);
// Sanitize accountType - only allow 'D' or 'K'
$accountType = strtoupper(substr($accountType, 0, 1));
if ($accountType !== 'D' && $accountType !== 'K') {
    $accountType = '';
}

$baseWhere = "(openpost.udlignet != '1' OR openpost.udlignet IS NULL)";

if ($accountNr !== '' && $accountType !== '') {
    $baseWhere .= " AND openpost.konto_nr = '$accountNr_escaped'";
    $ktoQuery = db_select("SELECT id FROM adresser WHERE kontonr = '$accountNr_escaped' AND art = '$accountType'", __FILE__ . " line " . __LINE__);
    if ($ktoRow = db_fetch_array($ktoQuery)) {
        $konto_id = $ktoRow['id'];
        $baseWhere .= " AND openpost.konto_id = '$konto_id'";
    }
}

// Add search filter
if ($search !== '') {
    $baseWhere .= " AND (
        CAST(openpost.faktnr AS TEXT) ILIKE '%$search_escaped%' 
        OR adresser.firmanavn ILIKE '%$search_escaped%'
        OR CAST(openpost.konto_nr AS TEXT) ILIKE '%$search_escaped%'
        OR openpost.beskrivelse ILIKE '%$search_escaped%'
    )";
}

$countQuery = db_select("
    SELECT COUNT(*) as cnt 
    FROM openpost 
    LEFT JOIN adresser ON openpost.konto_id = adresser.id
    WHERE $baseWhere
", __FILE__ . " line " . __LINE__);

if ($countQuery) {
    $countRow = db_fetch_array($countQuery);
    $totalCount = intval($countRow['cnt']);
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
        adresser.firmanavn,
        adresser.art
    FROM openpost 
    LEFT JOIN adresser ON openpost.konto_id = adresser.id
    WHERE $baseWhere
    ORDER BY openpost.transdate DESC, openpost.faktnr
    LIMIT $limit OFFSET $offset
";

$query = db_select($qtxt, __FILE__ . " line " . __LINE__);

if ($query) {
    while ($row = db_fetch_array($query)) {
        $results[] = array(
            'id' => $row['id'],
            'kontonr' => trim($row['konto_nr']),
            'konto_id' => $row['konto_id'],
            'faktnr' => trim($row['faktnr']),
            'amount' => floatval($row['amount']),
            'transdate' => $row['transdate'],
            'firmanavn' => trim(stripslashes($row['firmanavn'])),
            'beskrivelse' => isset($row['beskrivelse']) ? trim(stripslashes($row['beskrivelse'])) : '',
            'art' => isset($row['art']) ? trim($row['art']) : ''
        );
    }
}

$response = array(
    'results' => $results,
    'pagination' => array(
        'page' => $page,
        'limit' => $limit,
        'total' => $totalCount,
        'hasMore' => ($offset + count($results)) < $totalCount
    )
);

echo json_encode($response);
exit;
?>
