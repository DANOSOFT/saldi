<?php
// --- debitor/payments/log_lane3000.php --- Client-side log endpoint for the Lane3000/Move3500 payment page ---
// 20261006 CL/LH SST-845: Require a Saldi login and log into the session's own tenant folder; the tenant
//                 (and thereby the file path) used to come straight from the request body.

@session_start();
$s_id = session_id();
$header = 'nix'; // JSON endpoint: online.php must not emit the HTML head.
include ("../../includes/connect.php");
include ("../../includes/online.php");

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$tenant = basename((string)$db);

if (is_array($input) && $tenant !== '' && $tenant !== '.' && $tenant !== '..') {
    $logFile = '../../temp/' . $tenant . '/lane3000.log';
    $timestamp = date('Y-m-d H:i:s');
    $level = in_array($input['level'] ?? '', ['INFO', 'WARN', 'ERROR'], true) ? $input['level'] : 'INFO';
    $message = str_replace(["\r", "\n"], ' ', (string)($input['message'] ?? 'No message'));
    $ordre_id = (int)($input['ordre_id'] ?? 0);

    $logEntry = "[$timestamp] [CLIENT-$level] [Order: $ordre_id] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    http_response_code(200);
    echo json_encode(['status' => 'logged']);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
}
