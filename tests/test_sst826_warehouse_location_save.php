<?php
// tests/test_sst826_warehouse_location_save.php
//
// SST-826: a location typed on the item card was silently discarded for any item that
// had no lagerstatus row for that warehouse - which is every item with no stock.
//
// When an account defines warehouses (grupper art LG), lager/productCardIncludes/
// showLocations.php renders one location field per warehouse and the value is stored in
// lagerstatus.lok1 rather than varer.location. On save, lager/varekort.php looked for an
// existing lagerstatus row for the item and warehouse: if one existed it updated lok1,
// and if none existed it did nothing, because the insert that belonged there was
// commented out. showLocations.php only creates a row when the recorded stock disagrees
// with batch_kob/batch_salg, and for a new item both are 0 - so the row never appeared
// and the location could never be saved, nor reach the picking list (includes/formfunk.php
// reads lok1 for the 'lokation' variable on formular 3 and 9).
//
// The save block is lifted out of lager/varekort.php by brace-walking and run against an
// in-memory SQLite lagerstatus, so the assertions are against the real code rather than a
// copy of it. No database or credentials needed.
//
// Run:  php tests/test_sst826_warehouse_location_save.php

$checks = 0;
$failures = array();

function check($cond, $what)
{
    global $checks, $failures;
    $checks++;
    if (!$cond) {
        $failures[] = $what;
        echo "  FAIL  $what\n";
    }
}

function check_same($expected, $actual, $what)
{
    if ($expected !== $actual) {
        $what .= "\n          expected: " . var_export($expected, true)
               . "\n          actual:   " . var_export($actual, true);
        check(false, $what);
        return;
    }
    check(true, $what);
}

// ---------------------------------------------------------------------------
// db doubles over a real SQL engine
// ---------------------------------------------------------------------------

$pdo = new PDO('sqlite::memory:', null, null, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT));
$GLOBALS['sst826_pdo'] = $pdo;
$GLOBALS['sst826_writes'] = array();

function db_select($qtxt, $where = '')
{
    $st = $GLOBALS['sst826_pdo']->query($qtxt);
    return $st === false ? false : $st;
}

function db_fetch_array($st)
{
    if (!$st || $st === true) {
        return false;
    }
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        return false;
    }
    // Postgres hands every column back as a string; mirror that.
    $out = array();
    foreach ($row as $k => $v) {
        $out[$k] = $v === null ? null : (string) $v;
    }
    return $out;
}

function db_modify($qtxt, $where = '')
{
    $GLOBALS['sst826_writes'][] = $qtxt;
    $ok = $GLOBALS['sst826_pdo']->exec($qtxt);
    if ($ok === false) {
        throw new RuntimeException('SQL rejected: ' . $qtxt . ' -- '
            . implode(' ', $GLOBALS['sst826_pdo']->errorInfo()));
    }
    return $ok;
}

function db_escape_string($s)
{
    return str_replace("'", "''", (string) $s);
}

// ---------------------------------------------------------------------------
// lift the save block out of lager/varekort.php
// ---------------------------------------------------------------------------

$source = file_get_contents(dirname(__DIR__) . '/lager/varekort.php');
$anchor = 'if ($id && is_array($lagerlok)) {';
$start  = strpos($source, $anchor);
if ($start === false) {
    echo "FAIL: could not find the location-save block in lager/varekort.php\n";
    exit(1);
}
$depth = 0;
$end = null;
for ($i = strpos($source, '{', $start); $i < strlen($source); $i++) {
    if ($source[$i] === '{') {
        $depth++;
    } elseif ($source[$i] === '}') {
        $depth--;
        if ($depth === 0) {
            $end = $i + 1;
            break;
        }
    }
}
if ($end === null) {
    echo "FAIL: unbalanced braces around the location-save block\n";
    exit(1);
}
$block = substr($source, $start, $end - $start);

// Deliberately not asserted by searching the source for "insert into lagerstatus": the
// commented-out line that caused this bug contains that very text, so such a check passes
// on the broken code. What the writes actually were is asserted after the first save below.
check(strpos($block, '#else $qtxt="insert into lagerstatus') === false,
    'the commented-out insert is gone, not left as dead code');

/**
 * Runs the real block for one item against the fixture.
 *
 * @param array<int,string> $warehouseNames warehouse number => name
 * @param array<int,string> $lagerlok       warehouse number => posted location
 */
function run_save($id, array $warehouseNames, array $lagerlok)
{
    global $block;
    $GLOBALS['sst826_writes'] = array();
    eval($block);
}

function rows($vare_id)
{
    $st = $GLOBALS['sst826_pdo']->query(
        "select lager, variant_id, beholdning, lok1 from lagerstatus
          where vare_id = $vare_id order by lager, variant_id");
    $out = array();
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = array((int) $r['lager'], (int) $r['variant_id'],
                       (float) $r['beholdning'], $r['lok1']);
    }
    return $out;
}

function reset_fixture()
{
    $pdo = $GLOBALS['sst826_pdo'];
    $pdo->exec('drop table if exists lagerstatus');
    $pdo->exec('create table lagerstatus (id integer primary key autoincrement,
                lager integer, vare_id integer, variant_id integer,
                beholdning numeric, lok1 text, lok2 text, lok3 text, lok4 text, lok5 text)');
}

// WHESCO's own setup: two warehouses, numbered 3 and 8 - not 1 and 2. The numbers are
// what goes into lagerstatus.lager, so an index would silently write the wrong warehouse.
$warehouses = array(3 => 'Lager nr 3', 8 => 'Lager nr 8');

// --- a new item with no rows at all: the reported bug ----------------------
reset_fixture();
run_save(4299, $warehouses, array(3 => 'A-01-03', 8 => 'B-02-08'));
check_same(
    array(array(3, 0, 0.0, 'A-01-03'), array(8, 0, 0.0, 'B-02-08')),
    rows(4299),
    'a new item gets one zero-stock row per warehouse, carrying the location');
// Two inserts actually executed - not a string found in a comment.
$inserts = 0;
foreach ($GLOBALS['sst826_writes'] as $w) {
    if (stripos(ltrim($w), 'insert') === 0) {
        $inserts++;
    }
}
check_same(2, $inserts, 'two inserts were executed, one per warehouse');

// --- the same save again must not duplicate -------------------------------
run_save(4299, $warehouses, array(3 => 'C-09-03', 8 => 'D-07-08'));
check_same(
    array(array(3, 0, 0.0, 'C-09-03'), array(8, 0, 0.0, 'D-07-08')),
    rows(4299),
    'saving again updates the rows instead of adding more');

// --- an existing row keeps its stock --------------------------------------
reset_fixture();
$GLOBALS['sst826_pdo']->exec("insert into lagerstatus (lager, vare_id, variant_id, beholdning, lok1)
                              values (3, 500, 0, 5, 'GAMMEL')");
run_save(500, $warehouses, array(3 => 'NY-03', 8 => 'NY-08'));
check_same(
    array(array(3, 0, 5.0, 'NY-03'), array(8, 0, 0.0, 'NY-08')),
    rows(500),
    'stock on an existing row is untouched, and the empty warehouse gets a zero-stock row');

// --- an empty field must not create a row ---------------------------------
reset_fixture();
run_save(600, $warehouses, array(3 => '', 8 => '   '));
check_same(array(), rows(600),
    'an empty or blank location creates no row');

// --- but an empty field still clears an existing location -----------------
reset_fixture();
$GLOBALS['sst826_pdo']->exec("insert into lagerstatus (lager, vare_id, variant_id, beholdning, lok1)
                              values (3, 700, 0, 2, 'SKAL-RYDDES')");
run_save(700, $warehouses, array(3 => '', 8 => ''));
check_same(array(array(3, 0, 2.0, '')), rows(700),
    'clearing the field on an existing row still empties lok1, and adds no row for the other warehouse');

// --- a warehouse the form did not post is left alone ----------------------
reset_fixture();
run_save(800, $warehouses, array(3 => 'KUN-03'));
check_same(array(array(3, 0, 0.0, 'KUN-03')), rows(800),
    'a warehouse with no posted field is skipped entirely');

// --- a variant item: the existing variant row is updated, not replaced ----
reset_fixture();
$GLOBALS['sst826_pdo']->exec("insert into lagerstatus (lager, vare_id, variant_id, beholdning, lok1)
                              values (3, 900, 777, 3, 'VAR-GAMMEL')");
run_save(900, $warehouses, array(3 => 'VAR-NY', 8 => 'VAR-8'));
check_same(
    array(array(3, 777, 3.0, 'VAR-NY'), array(8, 0, 0.0, 'VAR-8')),
    rows(900),
    'a variant row keeps its variant_id and stock; no second row is added for that warehouse');

// --- a quote in the location must not break or inject SQL -----------------
reset_fixture();
run_save(1000, $warehouses, array(3 => "O'Brien's hylde", 8 => "x'); delete from lagerstatus; --"));
check_same(
    array(array(3, 0, 0.0, "O'Brien's hylde"),
          array(8, 0, 0.0, "x'); delete from lagerstatus; --")),
    rows(1000),
    'quotes in a location are stored literally and cannot inject SQL');

// --- the stored value is what reaches the picking list --------------------
// includes/formfunk.php reads "select lok1 as location from lagerstatus where vare_id and
// lager" for the 'lokation' variable on formular 3 and 9, so the row above is what the
// picking list prints.
reset_fixture();
run_save(1100, $warehouses, array(3 => 'PLUK-03'));
$st = $GLOBALS['sst826_pdo']->query(
    "select lok1 as location from lagerstatus where vare_id = '1100' and lager = '3'");
$r = db_fetch_array($st);
check_same('PLUK-03', $r['location'],
    "the picking list's own query finds the location that was saved");

// ---------------------------------------------------------------------------

echo "\nchecks: $checks, failures: " . count($failures) . "\n";
if ($failures) {
    echo "\nfailed:\n";
    foreach ($failures as $f) {
        echo "  - $f\n";
    }
    exit(1);
}
echo "OK\n";
exit(0);
