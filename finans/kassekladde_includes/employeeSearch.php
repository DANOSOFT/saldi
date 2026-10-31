<?php

// 20260216 PHR - *1 -> (int)


ob_start();

@session_start();
$s_id = session_id();
$title = "employeeSearch"; 
$modulnr = 0;  
$permission_key = 'finans.kassekladde|debitor.ordre|kreditor.ordre'; // 20261018 Sawaneh Review 2 (PR #803): modulnr 0 is no key
$permission_post_read = true; // a lookup: its POST only reads
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

$r = db_fetch_array(db_select("SELECT id FROM adresser WHERE art = 'S'", __FILE__ . " linje " . __LINE__));
$egen_kto_id = (int)$r['id'];

$results = array();

$qtxt = "SELECT id, initialer, navn 
         FROM ansatte 
         WHERE konto_id = '$egen_kto_id' 
         AND (lukket IS NULL OR lukket != 'on')";

if ($search !== '') {
    $qtxt .= " AND (initialer ILIKE '%$search%' OR navn ILIKE '%$search%')";
}

$qtxt .= " ORDER BY initialer, navn LIMIT 50";

$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

while ($row = db_fetch_array($q)) {
    $results[] = array(
        'id' => $row['id'],
        'initials' => $row['initialer'],
        'name' => $row['navn']
    );
}

echo json_encode(array(
    'success' => true,
    'results' => $results,
    'count' => count($results)
));
