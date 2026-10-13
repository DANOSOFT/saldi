<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/stripe/support/WebhookFixture.php --- 2026.10.12
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
// 20261012 CL/LH Child-process adapters for the actual webhook dispatch; no network, credentials or mail.
// 20261012 CL/LH Supply the request method during preloading for POST and method-guard regressions.
// 20261012 CL/LH Use real delivery-aware mail throttles with a failing transport and recover unbound Stripe snapshots.

$stripe_test_raw_body = base64_decode((string)getenv('SALDI_WEBHOOK_TEST_BODY'));
$stripe_test_pdo = new PDO((string)getenv('SALDI_WEBHOOK_TEST_DSN'), getenv('SALDI_STRIPE_TEST_USER') ?: null, getenv('SALDI_STRIPE_TEST_PASSWORD') ?: null,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
if ($stripe_test_pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
    $stripe_test_pdo->exec('SET search_path TO ' . getenv('SALDI_WEBHOOK_TEST_SCHEMA'));
} else {
    $stripe_test_pdo->sqliteCreateFunction('hashtext', static fn($key) => crc32($key), 1);
    $stripe_test_pdo->sqliteCreateFunction('pg_advisory_lock', static fn($key) => 1, 1);
    $stripe_test_pdo->sqliteCreateFunction('to_regclass', static function ($table) use ($stripe_test_pdo) {
        $q = $stripe_test_pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
        $q->execute([$table]);
        return $q->fetchColumn() ?: null;
    }, 1);
}
$db_type = 'pgsql';
$db = 'saldi_test';
$stripe_boot_ok = true;
$_SERVER['REQUEST_METHOD'] = getenv('SALDI_WEBHOOK_TEST_METHOD') ?: 'POST';
$_SERVER['REMOTE_ADDR'] = 'fixture';
$signatureTime = time();
$_SERVER['HTTP_STRIPE_SIGNATURE'] = 't=' . $signatureTime . ',v1=' . hash_hmac('sha256', $signatureTime . '.' . $stripe_test_raw_body, (string)getenv('SALDI_WEBHOOK_TEST_SECRET'));
register_shutdown_function(static function () { print "\n" . json_encode(['http' => http_response_code()]); });

function db_select($sql, $trace = '') {
    global $stripe_test_pdo;
    if (strpos($sql, 'FROM pg_indexes') !== false) return $stripe_test_pdo->query('SELECT NULL AS indexname WHERE 1=0');
    return $stripe_test_pdo->query($sql);
}
function db_fetch_array($query) { return $query->fetch(); }
function db_escape_string($value) { return str_replace("'", "''", (string)$value); }
function stripe_db_modify($sql, $trace) {
    global $stripe_test_pdo;
    if (stripe_setting('fixture_fail_write', 'off') === 'on') return false;
    try { $stripe_test_pdo->exec($sql); return "0\tfixture"; } catch (Throwable $e) { return false; }
}
function stripe_setting($key, $default = '') {
    global $stripe_test_pdo;
    if ($key === 'bookkeeper_email') return 'bookkeeper@example.test';
    if ($key === 'public_base_url') return 'https://example.test';
    if ($key === 'webhook_secret') return (string)getenv('SALDI_WEBHOOK_TEST_SECRET');
    $q = $stripe_test_pdo->prepare('SELECT var_value FROM settings WHERE var_name=?');
    $q->execute([$key]);
    $value = $q->fetchColumn();
    return $value === false ? $default : $value;
}
function stripeConfigDb() { return 'saldi_test'; }
function stripe_redact($message) { return $message; }
function stripe_log($message) { }
// 20261012 CL/LH The parent writes a transport-adapted copy of stripeAlertMail, preserving its locking and stamps.
require_once getenv('SALDI_WEBHOOK_TEST_DIR') . '/alert.php';
function stripe_test_mail($key, $to, $subject, $body, $headers): bool {
    global $stripe_test_pdo;
    $stripe_test_pdo->prepare('INSERT INTO fixture_mail_attempts (subject) VALUES (?)')->execute([$subject]);
    $failure = stripe_setting('fixture_mail_fail', 'off');
    if ($failure === 'throw') throw new RuntimeException('Fixture mail transport failure');
    if ($failure === 'on') return false;
    $stripe_test_pdo->prepare('INSERT INTO fixture_alerts (subject,body,dedupe_key) VALUES (?,?,?)')->execute([$subject, $body, $key]);
    return true;
}
function stripeHttpRequest($method, $path, $params = [], $options = []) {
    global $stripe_test_pdo;
    if (strpos($path, '/v1/payment_intents/') === 0) {
        $q = $stripe_test_pdo->prepare('SELECT body FROM fixture_intents WHERE intent_id=?');
        $q->execute([basename($path)]);
        $body = $q->fetchColumn();
        return ['ok' => true, 'body' => $body ? json_decode($body, true) : ['id' => basename($path), 'metadata' => []]];
    }
    if (strpos($path, '/v1/charges/') === 0) return ['ok' => true, 'body' => ['id' => 'ch_invoice', 'payment_intent' => 'pi_fixture', 'amount_refunded' => 50000, 'currency' => 'dkk']];
    return ['ok' => false, 'body' => [], 'status' => 404];
}
function stripe_test_runtime_load() {
    if (stripe_setting('fixture_runtime_error', 'off') === 'on') throw new RuntimeException('Fixture runtime failure');
    require_once dirname(__DIR__, 3) . '/includes/stripeIncludes/InvoicePaymentMath.php';
    require_once dirname(__DIR__, 3) . '/includes/stripeIncludes/InvoicePaymentLedger.php';
    require_once dirname(__DIR__, 3) . '/includes/stripeIncludes/InvoicePaymentService.php';
}
function stripe_invoice_payment_service() {
    global $stripe_test_pdo;
    $store = new \Saldi\Stripe\InvoicePaymentStore($stripe_test_pdo);
    return new \Saldi\Stripe\InvoicePaymentService($store, 'stripeHttpRequest', new \Saldi\Stripe\InvoicePaymentLedger($store), 'stripeAlertMail', 'saldi_test');
}
