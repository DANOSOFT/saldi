<?php
// tests/shopify/support/sandbox/K45.php
//
// Sandbox acceptance of K45 (SD-752): the document link of a posted invoice opens that invoice as
// a PDF, and a changed link opens no document. Run on the server that hosts the tenant, against
// its deployed endpoint (the link's base URL is the one register-webhooks stored):
//
//   php tests/shopify/support/sandbox/K45.php <tenant_db> <ordre_id>
//   e.g. php tests/shopify/support/sandbox/K45.php saldi_shopify_test_xx 1042
//
// Makes the invoice's link if it has none (a dokument_links row; nothing is posted, mailed or
// printed) and prints the link's host and path only, never the token. The second part, the link
// in DTAILS' shipping mail and on the account page (K42), is BLOCKED until DTAILS' sandbox shows
// it. Prints one PASS/FAIL/SKIP/BLOCKED line per part.
//
// History:
// 20261030 CL/SZ SD-752: created (in support/sandbox/: the test-discovery guardrail keeps tests/shopify/ for PHPUnit tests).

if (PHP_SAPI !== 'cli') {
    exit;
}
if ($argc < 3) {
    echo "K45 SKIP: usage php tests/shopify/support/sandbox/K45.php <tenant_db> <ordre_id>\n";
    exit(0);
}
[, $tenant, $ordreId] = $argv;
// Links are made on test tenants only, never on a live one.
if (!preg_match('/^saldi_shopify_test_[a-z0-9_]+$/', $tenant)) {
    echo "K45 SKIP: $tenant is not a saldi_shopify_test_* tenant\n";
    exit(0);
}
$root = dirname(__DIR__, 4);
if (!is_readable("$root/includes/connect.php")) {
    echo "K45 SKIP: includes/connect.php is not installed here\n";
    exit(0);
}
// connect.php finds db_query.php relative to the working directory, like the worker in tools/.
chdir("$root/tools");
include("$root/includes/connect.php");
require_once "$root/includes/shopify/bootstrap.php";
require_once "$root/includes/documents/dokumentLinks.php";

/** @return array{0: int, 1: string, 2: string} status, content type and the body's first 5 bytes */
function k45_get(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
    $body = (string)curl_exec($ch);
    return [(int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE), substr($body, 0, 5)];
}

try {
    shopify_bootstrap($tenant);
    $link = dokument_link((int)$ordreId, 'invoice');
} catch (Throwable $e) {
    echo 'K45 SKIP: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    exit(0);
}
$token = (string)substr($link, strrpos($link, '=') + 1);
$changed = substr($link, 0, -1) . ($token[-1] === 'A' ? 'B' : 'A');
$good = k45_get($link);
$bad = k45_get($changed);
$pass = $good[0] === 200 && str_starts_with($good[1], 'application/pdf') && $good[2] === '%PDF-' && $bad[0] === 404 && $bad[2] !== '%PDF-';
echo 'K45 ' . ($pass ? 'PASS' : 'FAIL') . ': ' . parse_url($link, PHP_URL_HOST) . parse_url($link, PHP_URL_PATH)
    . " link -> HTTP {$good[0]} {$good[1]}, changed link -> HTTP {$bad[0]} (expected 200 application/pdf, 404)\n";
echo "K42 BLOCKED: the link in DTAILS' shipping mail and on the account page needs DTAILS' sandbox (owner DTAILS)\n";
exit($pass ? 0 : 1);
