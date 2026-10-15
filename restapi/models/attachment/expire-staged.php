<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/models/attachment/expire-staged.php --- ver 5.0.0 --- 2026.10.01
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
 * Installation configuration supplied by includes/connect.php, included below.
 * @var string $sqhost
 * @var string $squser
 * @var string $sqpass
 * @var string $sqdb
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../../includes/connect.php';
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../includes/mobileUpdates.php';
require_once __DIR__ . '/DurableAttachmentModel.php';

$registry = db_connect($sqhost, $squser, $sqpass, $sqdb, __FILE__ . ' line ' . __LINE__);
$schema = new MobileSchema($registry);
$tenants = $schema->rows("SELECT DISTINCT db FROM regnskab WHERE db IS NOT NULL AND db <> '' AND db <> " . $schema->literal($sqdb));
$expired = 0;
$failed = false;
foreach ($tenants as $tenant) {
    try {
        $database = $tenant['db'];
        if (!preg_match('/\A[a-zA-Z0-9_-]+\z/', $database)) {
            throw new RuntimeException('Invalid tenant database name');
        }
        $tenantConnection = db_connect($sqhost, $squser, $sqpass, $database, __FILE__ . ' line ' . __LINE__);
        mobileTenantUpdates($tenantConnection);
        $store = new DurableAttachmentModel($tenantConnection, $database, 'maintenance', true);
        do {
            $batch = $store->expireStaged();
            $expired += $batch;
        } while ($batch === 100);
    } catch (Throwable $e) {
        $failed = true;
        error_log(json_encode(['operation' => 'attachment_staged_expiry', 'exception' => get_class($e),
            'location' => $e->getFile() . ':' . $e->getLine()]));
    }
}
echo json_encode(['expired' => $expired, 'failed' => $failed]) . "\n";
exit($failed ? 1 : 0);
