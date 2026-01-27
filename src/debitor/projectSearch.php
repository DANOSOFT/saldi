<?php

ob_start();

@session_start();
$s_id = session_id();
$title = "projectSearch"; 
$modulnr = 0;  
$bg = "nix";   
$header = "nix";
$webservice = true; 

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

if (!isset($regnaar) || empty($regnaar)) {
    echo json_encode(array('error' => 'Session expired'));
    exit;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$search_escaped = db_escape_string($search);

$results = array();

$qtxt = "SELECT kodenr, beskrivelse 
         FROM grupper 
         WHERE art = 'PRJ' AND kodenr != '0'";

if ($search !== '') {
    $qtxt .= " AND (kodenr ILIKE '%$search_escaped%' OR beskrivelse ILIKE '%$search_escaped%')";
}

$qtxt .= " ORDER BY kodenr LIMIT 50";

$query = db_select($qtxt, __FILE__ . " line " . __LINE__);

if ($query) {
    while ($row = db_fetch_array($query)) {
        $results[] = array(
            'id' => trim($row['kodenr']),
            'code' => trim($row['kodenr']),
            'description' => trim(stripslashes($row['beskrivelse']))
        );
    }
}

echo json_encode(array(
    'success' => true,
    'results' => $results,
    'count' => count($results)
));
exit;
?>
