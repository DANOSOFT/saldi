<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- cron/mobile_scanners.php --- ver 5.0.0 --- 2026.10.05
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

/**
 * Injected by includes/connect.php, included below:
 * @var string $sqhost
 * @var string $squser
 * @var string $sqpass
 */

/**
 * Per-installation ledger scanners, scheduled every 15 minutes as the web service user.
 * --tenant-id=<registry id> restricts diagnostics to an open local tenant.
 * Each tenant has its own advisory lock; HTTP calls are refused.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/../includes/db_query.php';
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/mobileUpdates.php';
require_once __DIR__ . '/../restapi/core/MobileAuth.php';
require_once __DIR__ . '/../restapi/services/MobileEventScanners.php';

$options = getopt('', ['tenant-id:']);
$tenantId = ifset($options, 'tenant-id');
if ($tenantId !== null && (!is_string($tenantId) || !preg_match('/^[1-9][0-9]*$/D', $tenantId) || filter_var($tenantId, FILTER_VALIDATE_INT) === false)) {
    fwrite(STDERR, "Invalid --tenant-id\n");
    exit(2);
}
$registryLink = MobileAuth::connectRegistry();
mobileRegistryUpdates($registryLink);
$registry = new MobileSchema($registryLink);
$filter = $tenantId === null ? '' : ' AND id=' . (int)$tenantId;
$tenants = $registry->rows("SELECT id,db FROM regnskab WHERE COALESCE(lukket,'')<>'on'
    AND (lukkes IS NULL OR lukkes>current_date) $filter AND EXISTS (SELECT 1 FROM refresh_sessions s WHERE s.tenant_id=regnskab.id
        AND s.revoked_at IS NULL AND s.expires_at>now()) ORDER BY id");
$failed = false;
$seen = [];
foreach ($tenants as $tenant) {
    if (isset($seen[$tenant['db']])) {
        continue;
    }
    $seen[$tenant['db']] = true;
    $link = null;
    try {
        $link = db_connect($sqhost, $squser, $sqpass, $tenant['db'], __FILE__ . ' linje ' . __LINE__);
        mobileTenantUpdates($link);
        $store = new MobileSchema($link);
        $locked = $store->rows("SELECT pg_try_advisory_lock(hashtext(current_database()),hashtext('mobile_scanners_cron')) AS locked")[0]['locked'] === 't';
        $stats = ['busy'=>!$locked];
        if ($locked) {
            try {
                $stats = (new MobileEventScanners($link, $registryLink, (int)$tenant['id']))->run();
            } finally {
                $store->execute("SELECT pg_advisory_unlock(hashtext(current_database()),hashtext('mobile_scanners_cron'))");
            }
        }
        echo json_encode(['tenant_id'=>(int)$tenant['id']] + $stats, JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable $e) {
        $failed = true;
        fwrite(STDERR, json_encode(['tenant_id'=>(int)$tenant['id'], 'error'=>'event_scanners_failed', 'exception'=>get_class($e)]) . "\n");
    } finally {
        if ($link instanceof \PgSql\Connection) {
            pg_close($link);
        }
    }
}
exit($failed ? 1 : 0);
