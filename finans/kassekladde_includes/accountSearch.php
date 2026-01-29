<?php

ob_start();

@session_start();
$s_id = session_id();
$title = "accountSearch"; 
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
$type = isset($_GET['type']) ? trim($_GET['type']) : 'finance';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Sanitize type - only allow specific values
if (!in_array($type, array('finance', 'debitor', 'kreditor', ''))) {
    $type = 'finance';
}

if (!isset($regnaar) || empty($regnaar)) {
    echo json_encode(array('error' => 'Session expired'));
    exit;
}

$results = array();
$totalCount = 0;

if ($type === 'finance' || $type === '') {
    $search_escaped = db_escape_string($search);
    
    $baseWhere = "(kontotype = 'D' OR kontotype = 'S' OR kontotype = 'H') 
             AND regnskabsaar = '$regnaar' 
             AND (lukket IS NULL OR lukket != 'on')";
    
    if ($search !== '') {
        $baseWhere .= " AND (CAST(kontonr AS TEXT) ILIKE '%$search_escaped%' OR beskrivelse ILIKE '%$search_escaped%' OR genvej ILIKE '%$search_escaped%')";
    }
    
    $countQuery = db_select("SELECT COUNT(*) as cnt FROM kontoplan WHERE $baseWhere", __FILE__ . " line " . __LINE__);
    if ($countQuery) {
        $countRow = db_fetch_array($countQuery);
        $totalCount = intval($countRow['cnt']);
    }
    
    $qtxt = "SELECT kontotype, kontonr, beskrivelse, moms, genvej, saldo 
             FROM kontoplan 
             WHERE $baseWhere
             ORDER BY kontonr LIMIT $limit OFFSET $offset";
    
    $query = db_select($qtxt, __FILE__ . " line " . __LINE__);
    
    if ($query) {
        while ($row = db_fetch_array($query)) {
            $results[] = array(
                'kontonr' => trim($row['kontonr']),
                'beskrivelse' => trim(stripslashes($row['beskrivelse'])),
                'moms' => isset($row['moms']) ? trim($row['moms']) : '',
                'genvej' => isset($row['genvej']) ? trim($row['genvej']) : '',
                'saldo' => isset($row['saldo']) ? floatval($row['saldo']) : 0,
                'kontotype' => trim($row['kontotype']),
                'type' => 'finance'
            );
        }
    }
} elseif ($type === 'debitor') {
    $search_escaped = db_escape_string($search);
    
    $baseWhere = "art = 'D'";
    
    if ($search !== '') {
        $baseWhere .= " AND (CAST(kontonr AS TEXT) ILIKE '%$search_escaped%' OR firmanavn ILIKE '%$search_escaped%')";
    }
    
    $countQuery = db_select("SELECT COUNT(*) as cnt FROM adresser WHERE $baseWhere", __FILE__ . " line " . __LINE__);
    if ($countQuery) {
        $countRow = db_fetch_array($countQuery);
        $totalCount = intval($countRow['cnt']);
    }
    
    $qtxt = "SELECT id, kontonr, firmanavn, kontakt 
             FROM adresser 
             WHERE $baseWhere
             ORDER BY kontonr LIMIT $limit OFFSET $offset";
    
    $query = db_select($qtxt, __FILE__ . " line " . __LINE__);
    
    if ($query) {
        while ($row = db_fetch_array($query)) {
            $results[] = array(
                'id' => $row['id'],
                'kontonr' => trim($row['kontonr']),
                'beskrivelse' => trim(stripslashes($row['firmanavn'])),
                'kontakt' => isset($row['kontakt']) ? trim($row['kontakt']) : '',
                'type' => 'debitor'
            );
        }
    }
} elseif ($type === 'kreditor') {
    $search_escaped = db_escape_string($search);
    
    $baseWhere = "art = 'K'";
    
    if ($search !== '') {
        $baseWhere .= " AND (CAST(kontonr AS TEXT) ILIKE '%$search_escaped%' OR firmanavn ILIKE '%$search_escaped%')";
    }
    
    $countQuery = db_select("SELECT COUNT(*) as cnt FROM adresser WHERE $baseWhere", __FILE__ . " line " . __LINE__);
    if ($countQuery) {
        $countRow = db_fetch_array($countQuery);
        $totalCount = intval($countRow['cnt']);
    }
    
    $qtxt = "SELECT id, kontonr, firmanavn, kontakt 
             FROM adresser 
             WHERE $baseWhere
             ORDER BY kontonr LIMIT $limit OFFSET $offset";
    
    $query = db_select($qtxt, __FILE__ . " line " . __LINE__);
    
    if ($query) {
        while ($row = db_fetch_array($query)) {
            $results[] = array(
                'id' => $row['id'],
                'kontonr' => trim($row['kontonr']),
                'beskrivelse' => trim(stripslashes($row['firmanavn'])),
                'kontakt' => isset($row['kontakt']) ? trim($row['kontakt']) : '',
                'type' => 'kreditor'
            );
        }
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
