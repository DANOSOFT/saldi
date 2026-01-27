<?php

ob_start();

@session_start();
$s_id = session_id();
$title = "currencySearch"; 
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

// Get search term
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Sanitize search term for SQL
$search = db_escape_string($search);

$results = array();

// Search currencies - by code (box1) or description
$qtxt = "SELECT kodenr, box1, beskrivelse 
         FROM grupper 
         WHERE art = 'VK'";

if ($search !== '') {
    $qtxt .= " AND (box1 ILIKE '%$search%' OR beskrivelse ILIKE '%$search%')";
}

$qtxt .= " ORDER BY box1 LIMIT 50";

$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

while ($row = db_fetch_array($q)) {
    $results[] = array(
        'id' => $row['kodenr'],
        'code' => $row['box1'],
        'description' => $row['beskrivelse']
    );
}

echo json_encode(array(
    'success' => true,
    'results' => $results,
    'count' => count($results)
));
