<?php

function write_log($text, $db, $log_level = 'INFO')
{
    // Ensure log directories exist
    $base_log_path = __DIR__ . "/../../temp";
    $db_log_path = "$base_log_path/$db";

    // Create directories if they don't exist
    if (!file_exists($base_log_path)) {
        mkdir($base_log_path, 0777, true);
    }
    if (!file_exists($db_log_path)) {
        mkdir($db_log_path, 0777, true);
    }

    // Get caller information
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = $trace[1] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'];

    // Prepare log details
    $timestamp = date("Y-m-d H:i:s");
    $file = $caller ? ($caller['file'] ?? 'Unknown') : 'Unknown';
    $line = $caller ? ($caller['line'] ?? 'Unknown') : 'Unknown';
    $function = $caller ? ($caller['function'] ?? 'Unknown') : 'Unknown';

    // Detailed log format
    $detailed_log_path = "$db_log_path/.ht_rest_api.log";
    $detailed_entry = implode(' | ', [
        $timestamp,
        $ip,
        $log_level,
        $text,
        "File: $file",
        "Line: $line",
        "Function: $function"
    ]) . "\n";
    file_put_contents($detailed_log_path, $detailed_entry, FILE_APPEND);
}