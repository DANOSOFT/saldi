<?php
@session_start();
include("../../includes/connect.php");   // brings in $db
// read incoming JSON
$input = json_decode(file_get_contents('php://input'), true);
$msg   = isset($input['message']) ? $input['message'] : 'no message';
$ts    = date('c');
$log   = __DIR__ . "/../../temp/$input[db]/vibrant_log.log";
file_put_contents($log, "[$ts] $msg\n", FILE_APPEND);
http_response_code(204);