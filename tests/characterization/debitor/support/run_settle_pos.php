<?php

// tests/characterization/debitor/support/run_settle_pos.php
//
// Child-process runner for debitor/pos_ordre_includes/exitFunc/settlePOS.php (SD-772).
//
// settlePOS.php is not a function. It is a bare script include()d into afslut() in
// exitFunc/exit.php, and it reads and writes a dozen variables of that function's scope.
// To pin what it does today it is include()d here unchanged, with those variables set up
// exactly as afslut() leaves them, against an in-memory SQLite database holding the tables
// it touches. Only the boundaries it is not responsible for are stood in for: the ledger
// (levering/bogfor/bogfor_indbetaling), the invoice-number allocator and transaktion().
//
// Two things are deliberately NOT papered over, because they are what the sale does today:
//   - $diffkto (lines 317-324) is never assigned anywhere - settlePOS.php spells it
//     $difkto at lines 44-46 - so the final cash rounding runs with null.
//   - $receipt_id and $payment_id are not set by afslut() either.
// They are left undefined unless a spec supplies them, and the warnings they raise are
// recorded rather than silenced, so a test can assert on them. Recording is the point:
// nothing here hides a problem, it reports it.
//
// argv: 1 = path to a JSON spec:
//   vars      afslut()'s scope: id, kasse, regnaar, konto_id, betalingsbet, momssats,
//             betaling, betaling2, modtaget, modtaget2, indbetaling, betvaluta, betvalkurs,
//             bruger_id, brugernavn, projekt. Anything else named here is set too, so a
//             test can supply receipt_id/payment_id/diffkto to show the difference.
//   order     the ordrer row (status, konto_id, betalingsbet, momssats)
//   lines     ordrelinjer rows for the sale
//   varer, grupper, kontoplan, openpost, betalinger   seed rows for those tables
//   post      $_POST (settlePOS reads $_POST['sum'] for leftToPay)
//   levering, bogfor, bogfor_indbetaling, invoice_number   what the stand-ins return
//
// Prints one JSON line with the result, every table afterwards, the SQL in order, the
// boundary calls in order, the warnings raised and anything printed. A settle that exits
// (the oere-rounding alert) still reports, because the dump runs on shutdown.
//
// History:
// 20261026 CL/MJ SD-772: created (M-PREP for S12).

$spec = json_decode((string)file_get_contents($argv[1] ?? ''), true);
if (!is_array($spec)) {
    fwrite(STDERR, "usage: php run_settle_pos.php <spec.json>\n");
    exit(2);
}

const SETTLE_POS_TABLES = ['ordrer', 'ordrelinjer', 'varer', 'grupper', 'kontoplan',
    'openpost', 'pos_betalinger', 'pos_events'];

$GLOBALS['settleSpec'] = $spec;
$GLOBALS['settleSql'] = [];
$GLOBALS['settleCalls'] = [];
$GLOBALS['settleWarnings'] = [];
$GLOBALS['settleResult'] = null;
$GLOBALS['settleReached'] = 'start';

// ---------------------------------------------------------------- the database

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$GLOBALS['settleDb'] = $db;

$db->exec("CREATE TABLE ordrer (id INTEGER PRIMARY KEY, konto_id INTEGER, status INTEGER,
    fakturanr TEXT, momssats NUMERIC, betalingsbet TEXT, levdate TEXT, fakturadate TEXT,
    sum NUMERIC, moms NUMERIC, betalt NUMERIC, felt_1 TEXT, felt_2 TEXT, felt_3 TEXT,
    felt_4 TEXT, felt_5 TEXT, tidspkt TEXT, projekt TEXT, ref TEXT)");
$db->exec("CREATE TABLE ordrelinjer (id INTEGER PRIMARY KEY AUTOINCREMENT, ordre_id INTEGER,
    vare_id INTEGER, varenr TEXT, beskrivelse TEXT, antal NUMERIC, m_rabat NUMERIC,
    pris NUMERIC, kostpris NUMERIC, momssats NUMERIC, momsfri TEXT, posnr INTEGER,
    procent NUMERIC, vat_account TEXT, vat_price NUMERIC, projekt TEXT, kdo TEXT, saet TEXT,
    rabatgruppe TEXT, tilfravalg TEXT, rabat NUMERIC, rabatart TEXT, fast_db NUMERIC,
    folgevare INTEGER, variant_id INTEGER)");
$db->exec("CREATE TABLE varer (id INTEGER PRIMARY KEY, varenr TEXT, beskrivelse TEXT,
    salgspris NUMERIC, gruppe TEXT)");
$db->exec("CREATE TABLE grupper (id INTEGER PRIMARY KEY AUTOINCREMENT, art TEXT, kodenr TEXT,
    fiscal_year TEXT, box1 TEXT, box2 TEXT, box3 TEXT, box4 TEXT, box5 TEXT, box6 TEXT,
    box7 TEXT, box8 TEXT, box9 TEXT, box10 TEXT)");
$db->exec("CREATE TABLE kontoplan (kontonr TEXT, regnskabsaar TEXT, moms TEXT)");
$db->exec("CREATE TABLE openpost (id INTEGER PRIMARY KEY AUTOINCREMENT, konto_id INTEGER,
    amount NUMERIC)");
$db->exec("CREATE TABLE pos_betalinger (id INTEGER PRIMARY KEY AUTOINCREMENT, ordre_id INTEGER,
    betalingstype TEXT, amount NUMERIC, valuta TEXT, valutakurs NUMERIC, payment_id INTEGER,
    receipt_id INTEGER)");
$db->exec("CREATE TABLE pos_events (id INTEGER PRIMARY KEY AUTOINCREMENT, ev_type TEXT,
    ev_time TEXT, cash_register_id TEXT, employee_id TEXT, order_id TEXT, file TEXT, line TEXT)");

/** Seeds one table from the spec; every column named in a row has to exist. */
function settle_pos_seed(PDO $db, string $table, array $rows): void
{
    foreach ($rows as $row) {
        $columns = array_keys($row);
        $values = array_map(static function ($value) {
            return $value === null ? 'NULL' : "'" . str_replace("'", "''", (string)$value) . "'";
        }, array_values($row));
        $db->exec("INSERT INTO $table (" . implode(',', $columns) . ') VALUES ('
            . implode(',', $values) . ')');
    }
}

$order = ($spec['order'] ?? []) + ['id' => $spec['vars']['id'] ?? 1, 'status' => 0];
settle_pos_seed($db, 'ordrer', [$order]);
settle_pos_seed($db, 'ordrelinjer', $spec['lines'] ?? []);
settle_pos_seed($db, 'varer', $spec['varer'] ?? []);
settle_pos_seed($db, 'grupper', $spec['grupper'] ?? []);
settle_pos_seed($db, 'kontoplan', $spec['kontoplan'] ?? []);
settle_pos_seed($db, 'openpost', $spec['openpost'] ?? []);
settle_pos_seed($db, 'pos_betalinger', $spec['betalinger'] ?? []);

// ------------------------------------------------- the functions settlePOS calls

function db_select($qtxt, $where = '')
{
    $GLOBALS['settleSql'][] = $qtxt;
    return $GLOBALS['settleDb']->query($qtxt);
}

function db_fetch_array($query)
{
    if (!$query instanceof PDOStatement) {
        return false;
    }
    return $query->fetch(PDO::FETCH_ASSOC);
}

function db_modify($qtxt, $where = '')
{
    $GLOBALS['settleSql'][] = $qtxt;
    $GLOBALS['settleDb']->exec($qtxt);
    return "0\tquery accepted";
}

function db_escape_string($text)
{
    return str_replace("'", "''", (string)$text);
}

function transaktion($qtext)
{
    $GLOBALS['settleCalls'][] = ['transaktion', $qtext];
}

/**
 * The ledger boundary. settlePOS decides WHETHER and in WHICH ORDER these run; what they
 * post is finans/bogfor.php's business and is pinned by its own characterization tests.
 */
function levering($id, $hurtigfakt, $a, $b)
{
    $GLOBALS['settleCalls'][] = ['levering', (string)$id, (string)$hurtigfakt];
    return $GLOBALS['settleSpec']['levering'] ?? 'OK';
}

function bogfor($id, $a)
{
    $GLOBALS['settleCalls'][] = ['bogfor', (string)$id];
    return $GLOBALS['settleSpec']['bogfor'] ?? 'OK';
}

function bogfor_indbetaling($id, $a)
{
    $GLOBALS['settleCalls'][] = ['bogfor_indbetaling', (string)$id];
    return $GLOBALS['settleSpec']['bogfor_indbetaling'] ?? 'OK';
}

function get_next_invoice_number($art = 'DO', $id = null)
{
    $GLOBALS['settleCalls'][] = ['get_next_invoice_number', (string)$art, (string)$id];
    return $GLOBALS['settleSpec']['invoice_number'] ?? 1001;
}

// ------------------------------------- the real arithmetic, lifted from where it ships

// settlePOS's totals are the thing under test, so the rounding and the group discount have to
// be the real ones - a stand-in would pin the stand-in's arithmetic.
require_once __DIR__ . '/pos_lift.php';

$root = dirname(__DIR__, 4);
pos_lift_into([
    $root . '/includes/std_func.php' => ['afrund'],
    $root . '/includes/stdFunc/usDecimal.php' => ['usdecimal'],
    $root . '/includes/ordrefunc.php' => ['pos_afrund', 'grupperabat'],
]);

// --------------------------------------------------------------- reporting

// Recorded, not suppressed: the settle path runs with three variables its own author never
// assigned, and a test has to be able to say so. Returning true keeps the child's stdout
// clean enough to stay valid JSON.
set_error_handler(static function (int $no, string $message, string $file = '', int $line = 0) {
    $GLOBALS['settleWarnings'][] = $message . ' in ' . basename($file) . ':' . $line;
    return true;
});

ob_start();
register_shutdown_function(static function () {
    $printed = ob_get_clean();
    $db = $GLOBALS['settleDb'];
    $tables = [];
    foreach (SETTLE_POS_TABLES as $table) {
        $tables[$table] = $db->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode([
        'result' => $GLOBALS['settleResult'],
        'reached' => $GLOBALS['settleReached'],
        'tables' => $tables,
        'sql' => $GLOBALS['settleSql'],
        'calls' => $GLOBALS['settleCalls'],
        'warnings' => $GLOBALS['settleWarnings'],
        'printed' => $printed,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n";
});

// ------------------------------------------- settlePOS.php in afslut()'s scope

/**
 * Sets up afslut()'s scope and include()s settlePOS.php into it, unchanged.
 *
 * The variables below are the ones afslut() has assigned by the time it reaches its
 * include. Anything the spec adds is set as well, and anything neither sets stays
 * undefined on purpose - see the file header.
 */
function settle_pos_run(array $spec): array
{
    $_POST = $spec['post'] ?? [];
    // afslut() reads these four off the ordrer row right before its include, so they follow the
    // seeded sale rather than being set twice; a spec may still override any of them in vars.
    $order = $spec['order'] ?? [];
    $vars = ($spec['vars'] ?? []) + [
        'status' => $order['status'] ?? 0,
        'konto_id' => $order['konto_id'] ?? 0,
        'betalingsbet' => $order['betalingsbet'] ?? '',
        'momssats' => $order['momssats'] ?? 25,
    ] + [
        'id' => 1,
        'kasse' => 1,
        'regnaar' => 2026,
        'betaling' => 'Kontant',
        'betaling2' => '',
        'modtaget' => 0,
        'modtaget2' => 0,
        'indbetaling' => 0,
        'betvaluta' => 'DKK',
        'betvalkurs' => 100,
        'bruger_id' => 7,
        'brugernavn' => 'sd772',
        'projekt' => '',
    ];
    extract($vars, EXTR_OVERWRITE);

    $svar = null;
    $sum = 0;
    $moms = 0;
    $GLOBALS['settleReached'] = 'included';

    $returned = include dirname(__DIR__, 4) . '/debitor/pos_ordre_includes/exitFunc/settlePOS.php';

    $GLOBALS['settleReached'] = 'finished';
    return [
        'svar' => $svar,
        'returned' => $returned === 1 ? null : $returned,
        'sum' => $sum,
        'moms' => $moms,
        'fakturanr' => $fakturanr ?? null,
        'incl_moms' => $incl_moms ?? null,
        'retur' => $retur ?? null,
        'betalt' => $betalt ?? null,
        'modtaget' => $modtaget,
        'modtaget2' => $modtaget2,
        'betaling' => $betaling,
    ];
}

$GLOBALS['settleResult'] = settle_pos_run($spec);
