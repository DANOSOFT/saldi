<?php

ob_start();

@session_start();
$s_id = session_id();
$title = "itemSearch"; 
$modulnr = 0;  
$bg = "nix";   
$header = "nix";
$webservice = true; 

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
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

$baseWhere = "(lukket IS NULL OR lukket != '1')";

if ($search !== '') {
    $words = explode(' ', $search_escaped);
    foreach ($words as $word) {
        if (trim($word) === '') continue;
        $baseWhere .= " AND (varenr ILIKE '%$word%' OR varenr_alias ILIKE '%$word%' OR beskrivelse ILIKE '%$word%' OR stregkode ILIKE '%$word%' OR trademark ILIKE '%$word%')";
    }
}

$countQuery = db_select("SELECT COUNT(*) as cnt FROM varer WHERE $baseWhere", __FILE__ . " line " . __LINE__);
if ($countQuery) {
    $countRow = db_fetch_array($countQuery);
    $totalCount = intval($countRow['cnt']);
}

$qtxt = "SELECT id, varenr, beskrivelse, salgspris, enhed, beholdning 
         FROM varer 
         WHERE $baseWhere
         ORDER BY varenr LIMIT $limit OFFSET $offset";

$query = db_select($qtxt, __FILE__ . " line " . __LINE__);

if ($query) {
    while ($row = db_fetch_array($query)) {
        $results[] = array(
            'id' => $row['id'],
            'varenr' => trim($row['varenr']),
            'beskrivelse' => trim(stripslashes($row['beskrivelse'])),
            'salgspris' => floatval($row['salgspris']),
            'enhed' => trim($row['enhed']),
            'beholdning' => floatval($row['beholdning'])
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
