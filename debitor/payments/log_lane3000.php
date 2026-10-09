<?php
@session_start();
include ("../../includes/connect.php");

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if ($input) {
    $logFile = '../../temp/'.$input['db'].'/lane3000.log';
    $timestamp = date('Y-m-d H:i:s');
    $level = $input['level'] ?? 'INFO';
    $message = $input['message'] ?? 'No message';
    $ordre_id = $input['ordre_id'] ?? 'Unknown';
    
    $logEntry = "[$timestamp] [CLIENT-$level] [Order: $ordre_id] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    http_response_code(200);
    echo json_encode(['status' => 'logged']);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
}
?>