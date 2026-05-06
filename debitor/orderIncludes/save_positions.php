<?php

@session_start();


error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, but log them
ini_set('log_errors', 1);

$bg = "nix";
$header = 'nix';
$modulnr = 0;

header('Content-Type: application/json');

require_once("../../includes/connect.php");
require_once("../../includes/online.php");
require_once("../../includes/std_func.php");

$response = ['success' => false];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $action = $_POST['action'] ?? '';
    if ($action !== 'update_positions') {
        throw new Exception('Invalid action');
    }

    $ordre_id = intval($_POST['ordre_id'] ?? 0);
    if (!$ordre_id) {
        throw new Exception('Missing ordre_id');
    }

    $positions_json = $_POST['positions'] ?? '';
    $positions = json_decode($positions_json, true);
    
    if (!is_array($positions) || empty($positions)) {
        throw new Exception('Invalid positions data');
    }

    // Update each position in the database
    $updated = 0;
    foreach ($positions as $pos) {
        $linje_id = intval($pos['linje_id'] ?? 0);
        $posnr = intval($pos['posnr'] ?? 0);
        
        if ($linje_id > 0 && $posnr >= 0) {
            $qtxt = "UPDATE ordrelinjer SET posnr = '$posnr' WHERE id = '$linje_id' AND ordre_id = '$ordre_id'";
            db_modify($qtxt, __FILE__ . " line " . __LINE__);
            $updated++;
        }
    }

    $response['success'] = true;
    $response['updated'] = $updated;
    $response['message'] = "Updated $updated positions";

} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
