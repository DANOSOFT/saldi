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
// DTAILS' mails are live in the sandbox. Prints one PASS/FAIL/SKIP/BLOCKED line per part: SKIP when
// the arguments or the documents don't fit, FAIL when a print fails or shows the wrong numbers.
// Texts inside the layout's order-line rows are left out, across the whole width of the page, so the
// check assumes the layout puts its reference fields (numbers, order name) outside those rows.
//
// History:
// 20261103 CL/SZ SD-754: created.
// 20261103 CL/SZ SD-754: Numbers are matched outside the order-line rows and must have 3+ characters.
//                A line row is found by its item number or the start of its description.
//                Each argument must be the document its position names, and a failed print is FAIL.
//                temp/<db> is never created here.
// 20261104 CL/SZ SD-754: The order lines are the layout's order-line area (art 3, generelt), on every page.
//                The area comes from the layout the renderer prints the lines with, in its fallback order.

if (PHP_SAPI !== 'cli') {
    exit;
}
if ($argc < 5) {
    echo "K54 SKIP: usage php tests/shopify/support/sandbox/K54.php <tenant_db> <web_invoice_id> <web_credit_note_id> <store_invoice_id>\n";
    exit(0);
}
[, $tenant, $webInvoice, $webCredit, $storeInvoice] = $argv;
foreach ([$webInvoice, $webCredit, $storeInvoice] as $arg) {
    if (!ctype_digit($arg)) {
        echo "K54 SKIP: $arg is not an ordrer id\n";
        exit(0);
    }
}
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
 * A posted document's numbers, checked against what its argument position says it is.
 *
 * @return array{id: int, art: string, kundeordnr: string, fakturanr: string, ordrenr: string, sprog: string}
 */
function k54_document(int $ordreId, string $art, bool $web): array
{
    $rows = shopify_db_rows('SELECT art, status, kundeordnr, fakturanr, ordrenr, sprog FROM ordrer WHERE id = $1', [$ordreId]);
    if (!isset($rows[0]) || $rows[0]['art'] !== $art || (int)$rows[0]['status'] < 3) {
        throw new RuntimeException("order $ordreId is no posted " . ($art === 'DK' ? 'credit note' : 'invoice'));
    }
    $doc = ['id' => $ordreId, 'art' => $art, 'kundeordnr' => trim((string)$rows[0]['kundeordnr']),
        'fakturanr' => trim((string)$rows[0]['fakturanr']), 'ordrenr' => trim((string)$rows[0]['ordrenr']),
        'sprog' => trim((string)$rows[0]['sprog'])];
    if ($web !== ($doc['kundeordnr'] !== '')) {
        throw new RuntimeException("order $ordreId " . ($web ? 'has no Shopify order name (kundeordnr); give a web order\'s document'
            : "has the Shopify order name {$doc['kundeordnr']}; give a store order's invoice"));
    }
    // a short number also matches a page number, a quantity or a lager number in the print
    foreach (['fakturanr', 'ordrenr'] as $field) {
        if (strlen($doc[$field]) < 3) {
            throw new RuntimeException("order $ordreId: $field {$doc[$field]} is too short to tell apart in the print; use documents numbered 100 or higher");
        }
    }
    return $doc;
}

/**
 * The order-line area of a document's layout, in PostScript points, from the layout formularprint()
 * prints the lines with (includes/formfunk.php): the order's language, then Dansk, any language and
 * formular 3, until one has line fields. Its 'generelt' row of art 3 holds the top line's y (ya), the
 * number of lines (xa) and the line spacing (xb), in mm. A 'generelt' row of a layout without line
 * fields still counts when the layout used has none, as in the renderer.
 *
 * @param array{id: int, art: string, sprog: string} $doc
 * @return array{0: float, 1: float} bottom and top y
 */
function k54_line_area(array $doc): array
{
    $formular = $doc['art'] === 'DK' ? 5 : 4;
    $sprog = strtolower($doc['sprog'] !== '' ? $doc['sprog'] : 'dansk');
    $layouts = [
        [$formular, $sprog], [$formular, 'dansk'], [$formular, null], [3, $sprog], [3, 'dansk'], [3, null],
    ];
    $generelt = null;
    foreach ($layouts as [$form, $lang]) {
        $rows = $lang === null
            ? shopify_db_rows('SELECT beskrivelse, xa, ya, xb FROM formularer WHERE formular = $1 AND art = 3', [$form])
            : shopify_db_rows('SELECT beskrivelse, xa, ya, xb FROM formularer WHERE formular = $1 AND art = 3 AND lower(sprog) = $2', [$form, $lang]);
        $fields = 0;
        foreach ($rows as $row) {
            if ($row['beskrivelse'] === 'generelt') {
                $generelt = $row;
            } else {
                $fields++;
            }
        }
        if ($fields > 0) {
            break;
        }
    }
    if ($generelt === null) {
        throw new RuntimeException("order {$doc['id']}: the layout has no order-line area (formular $formular, art 3, generelt)");
    }
    $top = (float)$generelt['ya'];
    return [($top - (float)$generelt['xa'] * (float)$generelt['xb']) * 2.86 - 1, $top * 2.86 + 1];
}

/**
 * The texts a document prints outside its layout's order-line area, on every page.
 *
 * @param array{id: int, art: string, sprog: string} $doc
 * @return list<string>
 */
function k54_print(array $doc, string $root): array
{
    global $db;
    [$lineBottom, $lineTop] = k54_line_area($doc);
    if (!is_dir("$root/temp/$db")) {
        throw new RuntimeException("temp/$db doesn't exist yet; print a document in the browser first");
    }
    $work = "$root/temp/$db/.ht_k54_" . bin2hex(random_bytes(6));
    if (!mkdir($work, 0700)) {
        throw new K54PrintFailed("cannot create $work");
    }
    $cwd = getcwd();
    try {
        // formularprint() includes ../includes/..., reads ../logolib and writes ../temp
        chdir("$root/includes");
        require_once "$root/includes/formfunk.php";
        ob_start();
        try {
            $documents = formularprint($doc['id'], $doc['art'] === 'DK' ? 5 : 4, 0, 'UTF-8', 'PDF', null, $work);
        } catch (Throwable $e) {
            throw new K54PrintFailed("order {$doc['id']}: " . get_class($e) . ': ' . $e->getMessage(), 0, $e);
        } finally {
            ob_end_clean();
        }
        if (!is_array($documents) || $documents === []) {
            throw new K54PrintFailed("order {$doc['id']}: " . (is_string($documents) ? $documents : 'nothing was printed'));
        }
        $texts = [];
        $printed = false;
        foreach (glob("$work/*.ps") ?: [] as $file) {
            preg_match_all('/([0-9.]+) ([0-9.]+) moveto \(((?:[^()\\\\]|\\\\.)*)\)/', (string)file_get_contents($file), $found, PREG_SET_ORDER);
            foreach ($found as [, , $y, $text]) {
                $printed = true;
                if ((float)$y < $lineBottom || (float)$y > $lineTop) {
                    $texts[] = trim(stripcslashes($text));
                }
            }
        }
    } finally {
        chdir($cwd);
        foreach (glob("$work/*") ?: [] as $file) {
            unlink($file);
        }
        rmdir($work);
    }
    if (!$printed) {
        throw new K54PrintFailed("order {$doc['id']}: the print has no text");
    }
    return $texts;
}

/** A print that failed: K54 FAIL, not SKIP. */
final class K54PrintFailed extends RuntimeException
{
}

/** One result line's detail and pass/fail for a document. */
function k54_check(string $what, array $doc, bool $web): array
{
    $printed = fn(string $value): bool => $value !== '' && in_array($value, $doc['texts'], true);
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
        ['web invoice', k54_document((int)$webInvoice, 'DO', true), true],
        ['web credit note', k54_document((int)$webCredit, 'DK', true), true],
        ['store invoice', k54_document((int)$storeInvoice, 'DO', false), false],
    ];
    foreach ($docs as $i => [, $doc]) {
        $docs[$i][1]['texts'] = k54_print($doc, $root);
    }
} catch (K54PrintFailed $e) {
    echo 'K54 FAIL: ' . $e->getMessage() . "\n";
    exit(1);
} catch (Throwable $e) {
    echo 'K54 SKIP: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    exit(0);
}
$pass = true;
$details = [];
foreach ($docs as [$what, $doc, $web]) {
    [$ok, $details[]] = k54_check($what, $doc, $web);
    $pass = $pass && $ok;
}
echo 'K54 ' . ($pass ? 'PASS' : 'FAIL') . ': ' . implode('; ', $details) . "\n";
echo "K54 BLOCKED: the same reference in order receipt, order confirmation and shipping mail needs DTAILS' mails in the sandbox (owner DTAILS)\n";
exit($pass ? 0 : 1);
