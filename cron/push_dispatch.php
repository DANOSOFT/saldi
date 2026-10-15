<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- cron/push_dispatch.php --- ver 5.0.0 --- 2026.10.01
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
// 20261001 CDX/LH Quarantine dispatches with ambiguous legacy identities before sending.
/**
 * Install on EACH Saldi installation, with its local includes/connect.php and registry identity:
 * * * * * * /usr/bin/php /var/www/html/saldi/cron/push_dispatch.php
 * Enumerates that registry's open tenants, connects using the installation's configured DB role,
 * and runs one <=100 message batch per tenant per minute. Separate installations use separate
 * crontabs/configurations; no host list and no HTTP discovery. Overlaps are locked per database.
 * SALDI_EXPO_PUSH_URL / SALDI_EXPO_RECEIPTS_URL override the relay URLs for a local stub.
 * SALDI_EXPO_ACCESS_TOKEN optionally enables Expo's enhanced push security.
 * --tenant-id=<registry id> restricts a diagnostic run to one locally registered tenant.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/../includes/db_query.php';
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../includes/mobileUpdates.php';
require_once __DIR__ . '/../restapi/core/MobileAuth.php';
require_once __DIR__ . '/../restapi/services/PushDispatchWorker.php';
require_once __DIR__ . '/../restapi/services/PushService.php';

/**
 * Injected by includes/connect.php, included above:
 * @var string $sqhost
 * @var string $squser
 * @var string $sqpass
 */
$options = getopt('', ['tenant-id:']);
$tenantId = ifset($options, 'tenant-id');
if ($tenantId !== null && (!is_string($tenantId) || !preg_match('/^[1-9][0-9]*$/D', $tenantId) || filter_var($tenantId, FILTER_VALIDATE_INT) === false)) {
    fwrite(STDERR, "Invalid --tenant-id\n");
    exit(2);
}
$registryLink = MobileAuth::connectRegistry();
mobileRegistryUpdates($registryLink);
$registry = new MobileSchema($registryLink);
$installation = Subject::installation();
$filter = $tenantId === null ? '' : ' AND id=' . (int)$tenantId;
$tenants = $registry->rows("SELECT id,db FROM regnskab WHERE COALESCE(lukket,'')<>'on'
    AND (lukkes IS NULL OR lukkes>current_date) $filter ORDER BY id");
$failed = false;
$seen = [];
foreach ($tenants as $tenant) {
    if (isset($seen[$tenant['db']])) {
        continue;
    }
    $seen[$tenant['db']] = true;
    try {
        $link = db_connect($sqhost, $squser, $sqpass, $tenant['db'], __FILE__ . ' linje ' . __LINE__);
        mobileTenantUpdates($link);
        (new PushService($link))->quarantineAmbiguousDispatches($installation);
        $stats = (new PushDispatchWorker($link, (int)$tenant['id'], $installation))->run();
        echo json_encode(['tenant_id'=>(int)$tenant['id']] + $stats, JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable $e) {
        $failed = true;
        fwrite(STDERR, json_encode(['tenant_id'=>(int)$tenant['id'], 'error'=>'dispatch_failed', 'exception'=>get_class($e)]) . "\n");
    }
}
exit($failed ? 1 : 0);
