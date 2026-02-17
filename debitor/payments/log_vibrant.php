<?php
@session_start();
include("../../includes/connect.php");   // brings in $db
// read incoming JSON
$input = json_decode(file_get_contents('php://input'), true);
$msg   = isset($input['message']) ? $input['message'] : 'no message';
$ts    = date('c');
if (isset($input['db']) && $input['db']) {
    $log = __DIR__ . "/../../temp/$input[db]/vibrant_debug.log";
} else {
    $log = __DIR__ . "/../../temp/vibrant_debug_fallback.log";
}
$ip    = $_SERVER['REMOTE_ADDR'];
$sid   = session_id();
// Format: Date Time [CLIENT] [IP] [SessionID] Message
$entry = "[$ts] [CLIENT] [$ip] [$sid] $msg" . PHP_EOL;
file_put_contents($log, $entry, FILE_APPEND);
http_response_code(204);