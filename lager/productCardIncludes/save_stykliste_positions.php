<?php

@session_start();
$s_id = session_id();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$bg = "nix";
$header = 'nix';
$modulnr = 0;

header('Content-Type: application/json');

require_once(__dir__ . "/../../includes/connect.php");
require_once(__dir__ . "/../../includes/online.php");
require_once(__dir__ . "/../../includes/std_func.php");

$response = ['success' => false];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action !== 'update_stykliste_positions') {
        throw new Exception('Invalid action');
    }

    $vare_id = intval(isset($_POST['vare_id']) ? $_POST['vare_id'] : 0);
    if (!$vare_id) {
        throw new Exception('Missing vare_id');
    }

    $positions_json = isset($_POST['positions']) ? $_POST['positions'] : '';
    $positions = json_decode($positions_json, true);

    if (!is_array($positions) || empty($positions)) {
        throw new Exception('Invalid positions data');
    }

    $updated = 0;
    foreach ($positions as $pos) {
        $stykliste_id = intval(isset($pos['stykliste_id']) ? $pos['stykliste_id'] : 0);
        $posnr        = intval(isset($pos['posnr'])        ? $pos['posnr']        : 0);

        if ($stykliste_id > 0 && $posnr > 0) {
            $qtxt = "UPDATE styklister SET posnr = '$posnr' WHERE id = '$stykliste_id' AND indgaar_i = '$vare_id'";
            db_modify($qtxt, __FILE__ . " line " . __LINE__);
            $updated++;
        }
    }

    $response['success'] = true;
    $response['updated'] = $updated;
    $response['message'] = "Updated $updated stykliste positions";

} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

echo "--- DATA BEYOND THIS LINE ---\n" . json_encode($response);
