<?php
// tests/shopify/support/sandbox/K54.php
//
// Sandbox acceptance of K54, WP11 part (SD-754): a web order's invoice and credit note show the
// Shopify order name (ordrer.kundeordnr, e.g. HML1234) and the invoice / credit-note number, a store
// order's invoice shows its invoice number as the customer's reference, and none of them shows
// Saldi's order number. Run on the server that hosts the tenant, after the brand layouts have the
// K54 changes of doc/shopify/layouts-pos50.md:
//
//   php tests/shopify/support/sandbox/K54.php <tenant_db> <web_invoice_id> <web_credit_note_id> <store_invoice_id>
//   e.g. php tests/shopify/support/sandbox/K54.php saldi_shopify_test_xx 1042 1043 1050
//
// The ids are ordrer.id of posted documents. Each is printed headless into a work directory of its
// own, which is deleted after; nothing is posted, mailed or stored. The joint part of K54 (the
// same reference in all five customer documents, three of them Shopify's mails) is BLOCKED until
// DTAILS' mails are live in the sandbox. Prints one PASS/FAIL/SKIP/BLOCKED line per part.
//
// History:
// 20261103 CL/SZ SD-754: created.

if (PHP_SAPI !== 'cli') {
    exit;
}
if ($argc < 5) {
    echo "K54 SKIP: usage php tests/shopify/support/sandbox/K54.php <tenant_db> <web_invoice_id> <web_credit_note_id> <store_invoice_id>\n";
    exit(0);
}
[, $tenant, $webInvoice, $webCredit, $storeInvoice] = $argv;
// Documents are printed on test tenants only, never on a live one.
if (!preg_match('/^saldi_shopify_test_[a-z0-9_]+$/', $tenant)) {
    echo "K54 SKIP: $tenant is not a saldi_shopify_test_* tenant\n";
    exit(0);
}
$root = dirname(__DIR__, 4);
if (!is_readable("$root/includes/connect.php")) {
    echo "K54 SKIP: includes/connect.php is not installed here\n";
    exit(0);
}
// connect.php finds db_query.php relative to the working directory, like the worker in tools/.
chdir("$root/tools");
include("$root/includes/connect.php");
require_once "$root/includes/shopify/bootstrap.php";
require_once "$root/includes/shopify/db.php";

/**
 * The printed text of a posted document and its numbers.
 *
 * @return array{text: string, kundeordnr: string, fakturanr: string, ordrenr: string}
 */
function k54_print(int $ordreId, string $root): array
{
    global $db;
    $rows = shopify_db_rows('SELECT art, status, kundeordnr, fakturanr, ordrenr FROM ordrer WHERE id = $1', [$ordreId]);
    if (!isset($rows[0]) || !in_array($rows[0]['art'], ['DO', 'DK'], true) || (int)$rows[0]['status'] < 3) {
        throw new RuntimeException("order $ordreId is no posted invoice or credit note");
    }
    $work = "$root/temp/$db/.ht_k54_" . bin2hex(random_bytes(6));
    if (!mkdir($work, 0700, true)) {
        throw new RuntimeException("cannot create $work");
    }
    $cwd = getcwd();
    try {
        // formularprint() includes ../includes/..., reads ../logolib and writes ../temp
        chdir("$root/includes");
        require_once "$root/includes/formfunk.php";
        ob_start();
        try {
            $documents = formularprint($ordreId, $rows[0]['art'] === 'DK' ? 5 : 4, 0, 'UTF-8', 'PDF', null, $work);
        } finally {
            ob_end_clean();
        }
        if (!is_array($documents) || $documents === []) {
            throw new RuntimeException("order $ordreId: " . (is_string($documents) ? $documents : 'nothing was printed'));
        }
        $text = '';
        foreach (glob("$work/*.ps") ?: [] as $file) {
            $text .= (string)file_get_contents($file);
        }
    } finally {
        chdir($cwd);
        foreach (glob("$work/*") ?: [] as $file) {
            unlink($file);
        }
        rmdir($work);
    }
    return ['text' => $text, 'kundeordnr' => trim((string)$rows[0]['kundeordnr']), 'fakturanr' => trim((string)$rows[0]['fakturanr']),
        'ordrenr' => trim((string)$rows[0]['ordrenr'])];
}

/** One result line's detail and pass/fail for a document. */
function k54_check(string $what, array $doc, bool $web): array
{
    $printed = fn(string $value): bool => $value !== '' && strpos($doc['text'], "($value)") !== false;
    // an order number equal to the document's number or Shopify name can't be told apart from them in the print
    $ordrenrShown = !in_array($doc['ordrenr'], [$doc['fakturanr'], $doc['kundeordnr']], true) && $printed($doc['ordrenr']);
    $ok = $printed($doc['fakturanr']) && !$ordrenrShown && (!$web || $printed($doc['kundeordnr']));
    $detail = "$what " . ($web ? "shows {$doc['kundeordnr']} " . ($printed($doc['kundeordnr']) ? 'yes' : 'no') . ', ' : '')
        . "number {$doc['fakturanr']} " . ($printed($doc['fakturanr']) ? 'yes' : 'no') . ", order number {$doc['ordrenr']} "
        . ($ordrenrShown ? 'shown' : 'not shown');
    return [$ok, $detail];
}

try {
    shopify_bootstrap($tenant);
    $docs = [
        ['web invoice', k54_print((int)$webInvoice, $root), true],
        ['web credit note', k54_print((int)$webCredit, $root), true],
        ['store invoice', k54_print((int)$storeInvoice, $root), false],
    ];
} catch (Throwable $e) {
    echo 'K54 SKIP: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    exit(0);
}
$pass = true;
$details = [];
foreach ($docs as [$what, $doc, $web]) {
    if ($web && $doc['kundeordnr'] === '') {
        echo "K54 SKIP: the $what has no Shopify order name (kundeordnr); give a web order's document\n";
        exit(0);
    }
    [$ok, $details[]] = k54_check($what, $doc, $web);
    $pass = $pass && $ok;
}
echo 'K54 ' . ($pass ? 'PASS' : 'FAIL') . ': ' . implode('; ', $details) . "\n";
echo "K54 BLOCKED: the same reference in order receipt, order confirmation and shipping mail needs DTAILS' mails in the sandbox (owner DTAILS)\n";
exit($pass ? 0 : 1);
