<?php
// Copyright (c) 2026 Danosoft ApS
// 20261028 CDX/MJ SST-876 Preserve the active claim when rewriting a failed delivery marker fails.
function shopSyncQueueWrite($path, $mode, $attempts, $first) { return false; }
require_once dirname(__DIR__, 4) . '/includes/stdFunc/shopSyncQueue.php';
$dir = sys_get_temp_dir() . '/sst876-retry-write-' . bin2hex(random_bytes(8));
mkdir($dir, 0700);
$claim = $dir . '/v1-x0-l4.1.claim';
file_put_contents($claim, "stock 0 1\n");
$log = $dir . '/error.log';
$previousLog = ini_set('error_log', $log);
try {
    shopSyncQueueRelease(['live' => $dir . '/v1-x0-l4', 'claim' => $claim, 'attempts' => 0, 'first' => 1, 'mode' => 'stock']);
    if (!is_file($claim) || !str_contains(file_get_contents($log), 'cannot persist shop retry')) {
        throw new RuntimeException('A failed marker write lost its recoverable claim or produced no diagnostic');
    }
    echo "PASS: A failed retry-marker write retains its claim and logs the failure\n";
} finally {
    ini_set('error_log', $previousLog);
    foreach (glob($dir . '/*') as $file) {
        unlink($file);
    }
    rmdir($dir);
}
