<?php

/**
 * SST-796 - cost price when a sale runs off negative stock.
 *
 * linjeopdat()'s deficit branch used to price the synthetic batch_kob from the static
 * varer.kostpris. At Den-Tec that field was three years old, so a machine bought at EUR 18.500
 * (kurs 7,50 = 138.750 kr) was invoiced out at a cost price of 88.100,93 kr while the real price
 * sat on an open purchase line for the same item the whole time.
 *
 * Exercises the real find_open_purchase_cost() against a SQL double, in the style of
 * tests/test_pos_cash_checkout.php - no database, credentials or tenant needed. The three sale
 * shapes the ticket asks for map onto it as: (a) a covering batch means the deficit branch is never
 * reached and the helper is never called, (b) no batch and an open purchase line must give the
 * purchase price, (c) no batch and no purchase line must give varer.kostpris.
 *
 * Run: php tests/test_sst796_deficit_cost_price.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

$GLOBALS['sst796_rows'] = [];      // rows the double returns for the purchase-line query
$GLOBALS['sst796_queries'] = [];
$GLOBALS['sst796_varekostpris'] = '88100.930'; // Den-Tec's three-year-old varekort figure

/** findtekst() is not under test here; return the fallback text carried by the id. */
function findtekst($key, $sprog_id)
{
    return explode('|', $key, 2)[1];
}

/** @return object{rows: array} */
function db_select($query, $source)
{
    $GLOBALS['sst796_queries'][] = $query;
    if (stripos($query, 'from varer') !== false) {
        return (object)['rows' => [['kostpris' => $GLOBALS['sst796_varekostpris']]]];
    }
    return (object)['rows' => $GLOBALS['sst796_rows']];
}

/** @return array|false */
function db_fetch_array($result)
{
    return array_shift($result->rows) ?? false;
}

require_once __DIR__ . '/../includes/stdFunc/findOpenPurchaseCost.php';

$failures = 0;

/** @return void */
function check(string $label, $expected, $actual): void
{
    global $failures;
    $ok = is_float($expected) || is_float($actual)
        ? (abs((float)$expected - (float)$actual) < 0.0005)
        : ($expected === $actual);
    if (!$ok) {
        $failures++;
        printf("FAIL: %s\n  expected %s\n  actual   %s\n", $label, var_export($expected, true), var_export($actual, true));
    } else {
        printf("PASS: %s\n", $label);
    }
}

/** One purchase line row as the query returns it. */
function poLine(array $over = []): array
{
    return $over + [
        'linje_id'   => 27083,
        'ordre_id'   => 8469,
        'pris'       => '18500.000',
        'rabat'      => '0.000',
        'antal'      => '1.000',
        'modtaget'   => '0.000',
        'valutakurs' => '750.000',
        'ordrenr'    => '355',
    ];
}

// (b) the Den-Tec case: open purchase line, EUR 18.500 at kurs 7,50
$GLOBALS['sst796_rows'] = [poLine()];
$hit = find_open_purchase_cost(1096);
check('open purchase line is used', true, $hit !== NULL);
check('Den-Tec price: EUR 18.500 x 7,50 = 138.750', 138750.0, $hit['pris']);
check('the source is reported for the log', 27083, $hit['linje_id']);
check('the order number is reported', '355', $hit['ordrenr']);

// (c) nothing open: the caller keeps varer.kostpris
$GLOBALS['sst796_rows'] = [];
check('no purchase line means no override', NULL, find_open_purchase_cost(1096));

// a line discount must be applied, as includes/ordrefunc.php:1925 does on a real receipt
$GLOBALS['sst796_rows'] = [poLine(['rabat' => '10.000'])];
check('line discount is applied', 124875.0, find_open_purchase_cost(1096)['pris']);

// a missing kurs must not zero the price
$GLOBALS['sst796_rows'] = [poLine(['valutakurs' => '0', 'pris' => '1234.000'])];
check('missing valutakurs is treated as 100', 1234.0, find_open_purchase_cost(1096)['pris']);

// A line is outstanding by the goods-receipt screen's own measure: ordrelinjer.antal less the
// batch_kob already booked against it (lager/modtagelse.php:258-261). Not ordrelinjer.leveret - the
// receipt path never writes it, and on the purchase side the statements that would are commented
// out in kreditor/ordre.php and ordreM.php, so leveret reads 0 on every KO line and a fully
// received order would look open forever.
$GLOBALS['sst796_rows'] = [poLine(['modtaget' => '1.000'])];
check('a fully received line is skipped', NULL, find_open_purchase_cost(1096));

$GLOBALS['sst796_rows'] = [poLine(['antal' => '3.000', 'modtaget' => '3.000'])];
check('received in several batches still counts as closed', NULL, find_open_purchase_cost(1096));

$GLOBALS['sst796_rows'] = [poLine(['antal' => '3.000', 'modtaget' => '1.000'])];
check('a partly received line is still open', 138750.0, find_open_purchase_cost(1096)['pris']);

$GLOBALS['sst796_rows'] = [poLine(['antal' => '1.000', 'modtaget' => '2.000'])];
check('over-receipt does not resurrect the line', NULL, find_open_purchase_cost(1096));

// A receipt queued but not yet booked has written no batch_kob, so the line is still outstanding on
// its own and needs no separate modtagelser lookup.
$GLOBALS['sst796_rows'] = [poLine(['modtaget' => '0.000'])];
$GLOBALS['sst796_queries'] = [];
check('a queued, unbooked receipt leaves the line open', 138750.0, find_open_purchase_cost(1096)['pris']);
$touchedModtagelser = false;
foreach ($GLOBALS['sst796_queries'] as $q) {
    if (stripos($q, 'from modtagelser') !== false) $touchedModtagelser = true;
}
check('and modtagelser is not consulted at all', false, $touchedModtagelser);

// a zero-priced line tells us nothing; keep looking rather than writing 0 onto the invoice
$GLOBALS['sst796_rows'] = [poLine(['pris' => '0.000']), poLine(['pris' => '2000.000', 'linje_id' => 999])];
$hit = find_open_purchase_cost(1096);
check('a zero-priced line is skipped', 15000.0, $hit['pris']);
check('and the next line is used instead', 999, $hit['linje_id']);

// the item id reaches SQL, so it must be cast
$GLOBALS['sst796_rows'] = [];
$GLOBALS['sst796_queries'] = [];
find_open_purchase_cost("1096' or '1'='1");
$injected = false;
foreach ($GLOBALS['sst796_queries'] as $q) {
    if (strpos($q, "or '1'='1") !== false) $injected = true;
}
check('the vare_id is int-cast before it reaches SQL', false, $injected);

// the open-line definition must match lager/modtagelse.php:254
$GLOBALS['sst796_rows'] = [poLine()];
$GLOBALS['sst796_queries'] = [];
find_open_purchase_cost(1096);
$q = $GLOBALS['sst796_queries'][0];
check("only kreditorordrer are considered", true, strpos($q, "o.art = 'KO'") !== false);
check("only status 1 or 2 count as open", true, strpos($q, "o.status = '1' or o.status = '2'") !== false);
check("newest order first", true, strpos($q, 'order by o.ordredate desc') !== false);
check("outstanding quantity comes from batch_kob, as the receipt path measures it", true, strpos($q, 'from batch_kob bk where bk.linje_id = ol.id') !== false);
check("ordrelinjer.leveret is not consulted", false, strpos($q, 'leveret') !== false);

// ---------------------------------------------------------------------------------------------
// deficit_cost_price(): the resolver both of linjeopdat()'s deficit branches now share. While only
// the normal-sale branch was fixed, the negative-quantity/kreditnota branch still read
// varer.kostpris, so the two returned different cost prices for the same item. These cases pin the
// shared behaviour, including the log label that tells the two branches apart.

/** Call the resolver, capturing what it prints and what it writes to the order log. */
function runResolver($vare_id, $linje_id, $kontekst)
{
    $fp = fopen('php://memory', 'w+');
    ob_start();
    $pris = deficit_cost_price($vare_id, $linje_id, $fp, 2, $kontekst);
    $printed = ob_get_clean();
    rewind($fp);
    $log = stream_get_contents($fp);
    fclose($fp);
    return ['pris' => $pris, 'printed' => $printed, 'log' => $log];
}

// An API delivery must not print markup: rest_api.php, mysale/api.php, remoteBooking/api.php and
// soapserver/invoice.php all reach levering() with webservice on and then emit JSON or XML, which
// printed HTML would precede and invalidate. The log line still has to be written, because that is
// the only place an API caller can see the cost price was estimated.
//
// This runs FIRST, while the once-per-request flag is still unset. Run after the interactive cases
// below it would pass even with the suppression removed, because the flag would already be spent.
$GLOBALS['sst796_rows'] = [poLine()];
$fp = fopen('php://memory', 'w+');
ob_start();
$ws = deficit_cost_price(1096, 44005, $fp, 2, 'salg fra negativ lagerbeholdning', 'on');
$wsPrinted = ob_get_clean();
rewind($fp);
$wsLog = stream_get_contents($fp);
fclose($fp);
check('a webservice delivery still gets the right price', 138750.0, $ws);
check('a webservice delivery prints nothing', '', $wsPrinted);
check('a webservice delivery is still logged', true, strpos($wsLog, 'source open purchase line') !== false);

// The sale branch, Den-Tec's shape: the open purchase line must beat the stale varekort figure.
// Reaching here with the flag unspent also proves the webservice call above did not consume it.
$GLOBALS['sst796_rows'] = [poLine()];
$salg = runResolver(1096, 44001, 'salg fra negativ lagerbeholdning');
check('sale branch prices from the open purchase line', 138750.0, $salg['pris']);
check('sale branch does not use the stale varekort figure', false, abs($salg['pris'] - 88100.93) < 0.0005);
check('sale branch logs the purchase line as the source', true, strpos($salg['log'], 'source open purchase line 27083 on order 355') !== false);
check('sale branch logs its own context label', true, strpos($salg['log'], '(salg fra negativ lagerbeholdning)') !== false);
check('sale branch warns the user', true, strpos($salg['printed'], 'indkøbsordre') !== false);

// The negative/kreditnota branch, same item and same open line, must reach the same price. This is
// the gap ZaynSaul found: before this change it returned 88.100,93 for the item above.
$GLOBALS['sst796_rows'] = [poLine()];
$kredit = runResolver(1096, 44002, 'negativt salg/kreditnota');
check('kreditnota branch prices from the same open purchase line', 138750.0, $kredit['pris']);
check('both branches agree on the same item', true, abs($salg['pris'] - $kredit['pris']) < 0.0005);
check('kreditnota branch logs its own context label', true, strpos($kredit['log'], '(negativt salg/kreditnota)') !== false);
check('kreditnota branch logs the line it priced', true, strpos($kredit['log'], 'linje_id 44002') !== false);

// Warned once per delivery, not once per line: the first call warned, later ones must stay silent.
check('the warning is not repeated for later lines', '', $kredit['printed']);

// No open purchase line: varer.kostpris is still the last resort, for both branches.
$GLOBALS['sst796_rows'] = [];
$fallback = runResolver(1096, 44003, 'negativt salg/kreditnota');
check('falls back to varer.kostpris when nothing is open', 88100.93, $fallback['pris']);
check('and logs varer.kostpris as the source', true, strpos($fallback['log'], 'source varer.kostpris') !== false);

// A caller without an order log handle must not fatal.
$GLOBALS['sst796_rows'] = [poLine()];
ob_start();
$noLog = deficit_cost_price(1096, 44004, NULL, 2, 'negativt salg/kreditnota');
ob_end_clean();
check('a NULL log handle is tolerated', 138750.0, $noLog);

$guard = file_get_contents(__DIR__ . '/../includes/stdFunc/findOpenPurchaseCost.php');
check(
    'the warn-once flag is guarded by the webservice flag, not set before it',
    true,
    (bool)preg_match('/if\s*\(\s*!\$webservice\s*&&\s*!\$advaret\s*\)/', $guard)
);

// ---------------------------------------------------------------------------------------------
// The resolver being correct is not enough: linjeopdat() has to actually call it from both deficit
// branches. Only the normal-sale branch was converted at first, and the unit tests above passed the
// whole time - nothing tied them to the call sites. This scans linjeopdat()'s own body, using
// token_get_all() so that commented-out code and the surrounding functions cannot satisfy it.

$tokens = token_get_all(file_get_contents(__DIR__ . '/../includes/ordrefunc.php'));
$body = '';
$depth = null;
for ($i = 0; $i < count($tokens); $i++) {
    $t = $tokens[$i];
    if (is_array($t) && $t[0] === T_FUNCTION) {
        // Look ahead for the name, skipping whitespace, so only linjeopdat() is captured.
        for ($j = $i + 1; $j < count($tokens); $j++) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) continue;
            $isTarget = is_array($tokens[$j]) && $tokens[$j][0] === T_STRING && $tokens[$j][1] === 'linjeopdat';
            break;
        }
        if (!empty($isTarget)) $depth = 0;
        continue;
    }
    if ($depth === null) continue;
    $text = is_array($t) ? $t[1] : $t;
    // Comments and doc comments are dropped, so a commented-out call cannot pass this check.
    if (!is_array($t) || ($t[0] !== T_COMMENT && $t[0] !== T_DOC_COMMENT)) {
        $body .= $text;
    }
    if ($text === '{') $depth++;
    if ($text === '}') {
        $depth--;
        if ($depth === 0) break;   // end of linjeopdat()
    }
}

check('linjeopdat() body was located', true, strlen($body) > 2000);
check(
    'both deficit branches call the shared resolver',
    2,
    preg_match_all('/deficit_cost_price\s*\(/', $body)
);
check(
    'no deficit branch reads varer.kostpris directly any more',
    0,
    preg_match_all('/select\s+kostpris\s+from\s+varer/i', $body)
);
// Both call sites must forward the webservice flag, or the API paths print markup again. A global
// would not do here: only api/rest_api.php sets a request-scope $webservice, while mysale/api.php,
// remoteBooking/api.php and soapserver/invoice.php pass it to levering() as an argument only.
check(
    'both call sites forward the webservice flag',
    2,
    preg_match_all('/deficit_cost_price\s*\([^;]*\$webservice\s*\)/', $body)
);
check(
    'linjeopdat() accepts the flag from levering()',
    true,
    (bool)preg_match('/function\s+linjeopdat\s*\([^)]*\$webservice/', file_get_contents(__DIR__ . '/../includes/ordrefunc.php'))
);
check(
    'levering() passes its own flag down to linjeopdat()',
    true,
    (bool)preg_match('/linjeopdat\([^;]*\$webservice\s*\)\s*;/', file_get_contents(__DIR__ . '/../includes/ordrefunc.php'))
);

printf("\n%s\n", $failures ? "$failures FAILURE(S)" : 'All SST-796 cost-price cases passed.');
exit($failures ? 1 : 0);
