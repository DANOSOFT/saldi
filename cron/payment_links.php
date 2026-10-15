<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- cron/payment_links.php --- ver 5.0.0 --- 2026-10-04 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created. Invoice payment links.

/**
 * Installation-local recovery, once per minute as the web service user:
 * * * * * * /usr/bin/php /var/www/html/saldi/cron/payment_links.php
 * --tenant-id=<registry id> restricts a diagnostic run. No provider create or refunds.
 * Injected by includes/connect.php below:
 * @var string $sqhost
 * @var string $squser
 * @var string $sqpass
 * @var string $sqdb
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once __DIR__ . '/../includes/db_query.php';
require_once __DIR__ . '/../includes/connect.php';
require_once __DIR__ . '/../restapi/core/MobileAuth.php';
require_once __DIR__ . '/../restapi/services/payments/PaymentSettlementService.php';
$options = getopt('', ['tenant-id:']);
$tenantId = ifset($options, 'tenant-id');
if ($tenantId !== null && (!is_string($tenantId) || !preg_match('/^[1-9][0-9]{0,9}$/D', $tenantId))) {
    fwrite(STDERR, "Invalid --tenant-id\n");
    exit(2);
}
$registry = MobileAuth::connectRegistry();
$filter = $tenantId === null ? '' : ' AND id=' . (int)$tenantId;
$tenants = (new MobileSchema($registry))->rows("SELECT id,db FROM regnskab WHERE COALESCE(lukket,'')<>'on'
    AND (lukkes IS NULL OR lukkes>CURRENT_DATE) $filter ORDER BY id");
$failed = false;
$seen = [];
foreach ($tenants as $tenant) {
    if (isset($seen[$tenant['db']]) || $tenant['db'] === $sqdb || !preg_match('/^[A-Za-z0-9_]+$/D', $tenant['db'])) {
        continue;
    }
    $seen[$tenant['db']] = true;
    $connection = null;
    $lock = false;
    try {
        $connection = db_connect($sqhost, $squser, $sqpass, $tenant['db'], __FILE__);
        mobileTenantUpdates($connection);
        $store = new MobileSchema($connection);
        $lock = $store->rows("SELECT pg_try_advisory_lock(hashtext('saldi:payment_links:cron')) AS locked")[0]['locked'] === 't';
        if (!$lock) {
            echo json_encode(['tenant_id'=>(int)$tenant['id'], 'busy'=>true]) . "\n";
            continue;
        }
        PaymentSettlementService::ensureWebhook($connection, (int)$tenant['id']);
        $links = $store->rows("SELECT * FROM payment_links ORDER BY id LIMIT 1");
        $stats = ['events'=>0,'attempts'=>0,'expired'=>0,'failed'=>0];
        if ($links) {
            $link = $links[0];
            $creator = Subject::parse($link['created_by']);
            $date = (new DateTimeImmutable($link['created_at']))->setTimezone(new DateTimeZone('Europe/Copenhagen'))->format('Y-m-d');
            $context = new EngineContext($connection, $tenant['db'], (int)$tenant['id'], $link['created_by'], $creator['user_id'], $date);
            $stats = (new PaymentSettlementService($context, $link['created_by'], new VippsEpaymentProvider($connection)))->run();
        } else {
            // Foreign events do not require a bookkeeping year or identity.
            $store->execute("UPDATE payment_provider_events SET state='ignored',processed_at=now() WHERE state IN ('received','failed')");
        }
        $failed = $failed || $stats['failed'] > 0;
        echo json_encode(['tenant_id'=>(int)$tenant['id'], 'busy'=>false] + $stats, JSON_THROW_ON_ERROR) . "\n";
    } catch (Throwable $error) {
        $failed = true;
        fwrite(STDERR, json_encode(['tenant_id'=>(int)$tenant['id'], 'error'=>'settlement_failed', 'exception'=>get_class($error)]) . "\n");
    } finally {
        if ($lock) {
            pg_query($connection, "SELECT pg_advisory_unlock(hashtext('saldi:payment_links:cron'))");
        }
        if ($connection) {
            pg_close($connection);
        }
    }
}
exit($failed ? 1 : 0);
