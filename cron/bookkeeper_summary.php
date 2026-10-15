<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- cron/bookkeeper_summary.php --- ver 5.0.0 --- 2026.10.05
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.

/** CLI only: schedule every five minutes on each installation as the web service user. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/../includes/db_query.php';
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../restapi/services/BookkeeperSummaryService.php';

$options = getopt('', ['tenant-id:']);
$tenantId = ifset($options, 'tenant-id');
if ($tenantId !== null && (!is_string($tenantId) || !preg_match('/^[1-9][0-9]*$/D', $tenantId)
    || strlen($tenantId) > 10 || (int)$tenantId > 2147483647)) {
    fwrite(STDERR, "Invalid --tenant-id\n");
    exit(2);
}
try {
    $service = new BookkeeperSummaryService(MobileAuth::connectRegistry());
    $failed = false;
    foreach ($service->warm($tenantId === null ? null : (int)$tenantId) as $result) {
        $failed = $failed || $result['state'] === 'error';
        echo json_encode($result, JSON_THROW_ON_ERROR) . "\n";
    }
    exit($failed ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, json_encode(['error' => 'summary_warmer_failed', 'exception' => get_class($e)]) . "\n");
    exit(1);
}
