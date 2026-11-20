<?php
// Child runner for PosDeliveryContactSyncTest: asks pos_validate_checkout_sale() about each sale
// on a test tenant, through the legacy database layer the till uses.
//
// usage: php run_pos_contact.php <test_db> <order id> [<order id> ...]
// prints JSON: {"<order id>": {"ok": bool, "message": string}, ...}

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_pos_contact.php <test_db> <order id> [<order id> ...]\n");
    exit(2);
}
$db = $argv[1];

require_once __DIR__ . '/ShopifyEnv.php';
ShopifyEnv::assertSafeTestDb($db);

$_SERVER['REQUEST_URI'] = '/saldi/debitor/pos_ordre.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
chdir(ShopifyEnv::repoRoot() . '/debitor');

$db_type = 'postgresql';
$db_encode = 'UTF8';
$brugernavn = 'shopifytest';
$sqdb = '';
$db_skriv_id = 0;
$sprog_id = 1;

/** The text after the pipe, as findtekst() shows a key no translator has filled in yet. */
function findtekst($token, $sprogId)
{
    $parts = explode('|', (string)$token, 2);
    return $parts[1] ?? $parts[0];
}

require_once ShopifyEnv::repoRoot() . '/includes/db_query.php';
$connection = db_connect(ShopifyEnv::pgHost(), ShopifyEnv::pgUser(), ShopifyEnv::pgPass(), $db);

require_once ShopifyEnv::repoRoot() . '/debitor/pos_ordre_includes/deliveryContact.php';

$out = [];
foreach (array_slice($argv, 2) as $orderId) {
    $out[$orderId] = pos_validate_checkout_sale((int)$orderId);
}
echo json_encode($out);
