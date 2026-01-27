<?php


ob_start();

@session_start();
$s_id = session_id();
$title = "departmentSearch"; 
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


$search = db_escape_string($search);

$results = array();

$qtxt = "SELECT kodenr, beskrivelse 
         FROM grupper 
         WHERE art = 'AFD'";

if ($search !== '') {
    $qtxt .= " AND (kodenr ILIKE '%$search%' OR beskrivelse ILIKE '%$search%')";
}

$qtxt .= " ORDER BY kodenr LIMIT 50";

$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

while ($row = db_fetch_array($q)) {
    $results[] = array(
        'code' => $row['kodenr'],
        'description' => $row['beskrivelse']
    );
}

echo json_encode(array(
    'success' => true,
    'results' => $results,
    'count' => count($results)
));
