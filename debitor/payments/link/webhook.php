<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/payments/link/webhook.php --- ver 5.0.0 --- 2026-10-04 ---
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
 * Injected by includes/connect.php, included below:
 * @var string $sqhost
 * @var string $squser
 * @var string $sqpass
 * @var string $sqdb
 */
require_once __DIR__ . '/../../../includes/db_query.php';
require_once __DIR__ . '/../../../includes/connect.php';
require_once __DIR__ . '/../../../restapi/core/MobileAuth.php';
require_once __DIR__ . '/../../../restapi/services/payments/PaymentSettlementService.php';

header('Cache-Control: no-store');
header('Content-Type: application/json; charset=UTF-8');
// Apache may expose Authorization only through getallheaders(), as the REST JWT gate does.
$requestHeaders = array_change_key_case(getallheaders() ?: [], CASE_LOWER);
if (isset($requestHeaders['authorization'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = $requestHeaders['authorization'];
}
$tenantId = ifset($_GET, 't');
$method = ifset($_SERVER, 'REQUEST_METHOD', '');
$body = file_get_contents('php://input', false, null, 0, 65537);
if ($method !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
if (!is_string($tenantId) || !preg_match('/^[1-9][0-9]{0,9}$/D', $tenantId)) {
    http_response_code(401);
    exit;
}
if (!is_string($body) || strlen($body) > 65536) {
    http_response_code(413);
    exit;
}
try {
    $registry = MobileAuth::connectRegistry();
    $tenants = (new MobileSchema($registry))->rows('SELECT db FROM regnskab WHERE id=' . (int)$tenantId . " AND COALESCE(lukket,'')<>'on'
        AND (lukkes IS NULL OR lukkes>CURRENT_DATE)");
    $db = (string)ifset($tenants, [0,'db'], '');
    if (!$tenants || !preg_match('/^[A-Za-z0-9_]+$/D', $db) || $db === $sqdb) {
        http_response_code(401);
        exit;
    }
    $connection = db_connect($sqhost, $squser, $sqpass, $db, __FILE__);
    $store = new MobileSchema($connection);
    $secrets = $store->rows("SELECT var_value FROM settings WHERE var_grp='mobilepay' AND var_name='invoice_webhook_secret' AND user_id=0 ORDER BY id DESC LIMIT 1");
    if (!PaymentSettlementService::verify($body, $_SERVER, (string)ifset($secrets, [0,'var_value'], ''))) {
        http_response_code(401);
        exit;
    }
    mobileTenantUpdates($connection);
    $event = PaymentSettlementService::receive($connection, $body);
} catch (MobileT2Error $error) {
    http_response_code($error->status);
    exit;
} catch (Throwable $error) {
    error_log(json_encode(['exception'=>get_class($error), 'location'=>$error->getFile() . ':' . $error->getLine()]));
    http_response_code(500);
    exit;
}
// Acknowledgement follows durable storage. Processing failures remain recoverable by the cron.
http_response_code(200);
echo json_encode(['received'=>true,'duplicate'=>$event['duplicate']]);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}
if (!$event['duplicate']) {
    try {
        $row = $store->rows('SELECT l.* FROM payment_links l JOIN payment_attempts a ON a.link_id=l.id JOIN payment_provider_events e
            ON e.provider_reference=a.provider_reference WHERE e.id=' . $event['id']);
        if (!$row || !str_starts_with(json_decode($body, true)['reference'], 'saldi-' . (int)$tenantId . '-')) {
            $store->execute("UPDATE payment_provider_events SET state='ignored',processed_at=now() WHERE id=" . $event['id']);
        } else {
            $link = $row[0];
            $creator = Subject::parse($link['created_by']);
            $date = (new DateTimeImmutable($link['created_at']))->setTimezone(new DateTimeZone('Europe/Copenhagen'))->format('Y-m-d');
            $context = new EngineContext($connection, $db, (int)$tenantId, $link['created_by'], $creator['user_id'], $date);
            (new PaymentSettlementService($context, $link['created_by'], new VippsEpaymentProvider($connection)))->processEvent($event['id']);
        }
    } catch (Throwable $error) {
        $store->execute("UPDATE payment_provider_events SET state='failed',error='processing_failed',processed_at=now()
            WHERE id=" . $event['id'] . " AND state='received'");
        error_log(json_encode(['exception'=>get_class($error), 'location'=>$error->getFile() . ':' . $error->getLine()]));
    }
}
