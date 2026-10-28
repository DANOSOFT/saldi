<?php
// tests/shopify/sandbox/K03.php
//
// Sandbox acceptance of K03 (SD-748): the same webhook twice gives one inbox row, and a wrong
// signature gets 401 and creates no rows. Run on the server that hosts the tenant, against its
// deployed endpoint:
//
//   php tests/shopify/sandbox/K03.php <tenant_db> <shop_key> <endpoint_url>
//   e.g. php tests/shopify/sandbox/K03.php saldi_shopify_test_xx hml https://ssl12.saldi.dk/sz/api/shopify/webhook.php
//
// Part 1 posts synthetic deliveries signed with the shop's webhook secret, under the unrouted
// topic shop/update, so promotion never turns them into jobs, and deletes its own rows after.
// Part 2, a real delivery from the sandbox shop, is BLOCKED until the DTAILS apps have their
// scopes and SD-749 registers the subscriptions. Prints one PASS/FAIL/SKIP/BLOCKED line per part.
//
// History:
// 20261027 CL/SZ SD-748: created.

if (PHP_SAPI !== 'cli') {
    exit;
}
if ($argc < 4) {
    echo "K03 SKIP: usage php tests/shopify/sandbox/K03.php <tenant_db> <shop_key> <endpoint_url>\n";
    exit(0);
}
[, $tenant, $shopKey, $url] = $argv;
// Synthetic deliveries go to test tenants only, never to a live one.
if (!preg_match('/^saldi_shopify_test_[a-z0-9_]+$/', $tenant)) {
    echo "K03 SKIP: $tenant is not a saldi_shopify_test_* tenant\n";
    exit(0);
}
$root = dirname(__DIR__, 3);
if (!is_readable("$root/includes/connect.php")) {
    echo "K03 SKIP: includes/connect.php is not installed here\n";
    exit(0);
}
// connect.php finds db_query.php relative to the working directory, like the worker in tools/.
chdir("$root/tools");
include("$root/includes/connect.php");
require_once "$root/includes/shopify/bootstrap.php";
require_once "$root/includes/shopify/events.php";

function k03_post(string $url, string $shopKey, string $raw, array $headers): int
{
    $ch = curl_init($url . (strpos($url, '?') === false ? '?' : '&') . 'shop=' . rawurlencode($shopKey));
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $raw, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
    curl_exec($ch);
    return (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
}

try {
    shopify_bootstrap($tenant);
    $shop = shopify_shop($shopKey);
    $secret = $shop ? shopify_secret($shopKey, SHOPIFY_WEBHOOK_SECRET) : null;
} catch (Throwable $e) {
    echo 'K03 SKIP: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    exit(0);
}
if ($shop === null || $secret === null) {
    echo "K03 SKIP: shop $shopKey or its " . SHOPIFY_WEBHOOK_SECRET . " is not set in $tenant\n";
    exit(0);
}

$eventId = 'k03-' . bin2hex(random_bytes(8));
$raw = json_encode(['id' => 1, 'note' => 'K03 sandbox check ' . date('c')]);
$headers = [
    'X-Shopify-Topic: shop/update',
    'X-Shopify-Shop-Domain: ' . $shop['domain'],
    'X-Shopify-Event-Id: ' . $eventId,
    'X-Shopify-API-Version: 2026-07',
    'Content-Type: application/json',
];
$signed = array_merge($headers, ['X-Shopify-Hmac-Sha256: ' . base64_encode(hash_hmac('sha256', $raw, $secret, true))]);
$forged = array_merge(array_slice($headers, 0, 2), ['X-Shopify-Event-Id: ' . $eventId . '-forged'], array_slice($headers, 3), ['X-Shopify-Hmac-Sha256: ' . base64_encode(hash_hmac('sha256', $raw, bin2hex(random_bytes(16)), true))]);

try {
    $answers = [k03_post($url, $shopKey, $raw, $signed), k03_post($url, $shopKey, $raw, $signed), k03_post($url, $shopKey, $raw, $forged)];
    $rows = shopify_db_rows('SELECT event_id FROM shopify_events WHERE shop_id = $1 AND event_id IN ($2, $3)', [(int)$shop['id'], $eventId, "$eventId-forged"]);
} finally {
    shopify_db_rows('DELETE FROM shopify_events WHERE shop_id = $1 AND event_id IN ($2, $3)', [(int)$shop['id'], $eventId, "$eventId-forged"]);
}
$pass = $answers === [200, 200, 401] && array_column($rows, 'event_id') === [$eventId];
echo 'K03 ' . ($pass ? 'PASS' : 'FAIL') . ': signed twice, forged once -> HTTP ' . implode('/', $answers) . ', inbox rows ' . count($rows) . " (expected 200/200/401, 1 row)\n";
echo "K03 BLOCKED: a real delivery from the sandbox shop needs the DTAILS app scopes and SD-749's webhook registration\n";
exit($pass ? 0 : 1);
