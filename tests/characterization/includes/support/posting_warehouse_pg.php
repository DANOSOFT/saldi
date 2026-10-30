<?php
// Copyright (c) 2026 Danosoft ApS
// 20261027 CDX/MJ SST-883 Exercise the shipped posting writers against isolated PostgreSQL temporary tables.
// Run with SALDI_TEST_PG_DSN or the standard PG* environment variables; no persistent data is changed.
// 20261030 CL/MJ SST-883 Moved here from tests/integration/ so PostingWarehousePgTest runs it in CI's characterization suite.
require_once __DIR__ . '/../../../../includes/std_func.php';
if (!extension_loaded('pgsql') || (!getenv('SALDI_TEST_PG_DSN') && !getenv('SALDI_TEST_PG'))) {
    echo "SKIP: set SALDI_TEST_PG=1 or SALDI_TEST_PG_DSN for PostgreSQL integration tests.\n";
    exit(0);
}
$connection = pg_connect(getenv('SALDI_TEST_PG_DSN') ?: '');
if (!$connection) {
    throw new RuntimeException('Cannot connect to the integration database');
}
function db_select($sql, $location = '') {
    $result = pg_query($GLOBALS['connection'], $sql);
    if ($result === false) {
        throw new RuntimeException("$location: " . pg_last_error($GLOBALS['connection']));
    }
    return $result;
}
function db_modify($sql, $location = '') { return db_select($sql, $location); }
function db_fetch_array($result) { return pg_fetch_assoc($result); }
function db_escape_string($value) { return pg_escape_string($GLOBALS['connection'], (string) $value); }
function check($condition, $message) {
    $GLOBALS['assertions']++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function scalar($sql) { return pg_fetch_result(db_select($sql), 0, 0); }

// An optional source root lets the same assertions demonstrate the regression on the PR baseline.
$sourceRoot = getenv('SALDI_POSTING_WAREHOUSE_SOURCE_ROOT') ?: dirname(__DIR__, 4);
require_once $sourceRoot . '/includes/ordrefunc.php';
$assertions = 0;
db_modify('BEGIN');
try {
    // No fallback to public tables, even if a fixture omits a table. All sequences are temporary too.
    db_modify('SET LOCAL search_path TO pg_temp');
    db_modify("CREATE TEMP TABLE grupper (id serial, art text, kodenr integer, fiscal_year integer, box1 text, box2 text, box3 text, box4 text, box8 text, box9 text) ON COMMIT DROP");
    db_modify('CREATE TEMP TABLE ansatte (navn text, afd integer) ON COMMIT DROP');
    db_modify('CREATE TEMP TABLE varer (id integer, beholdning numeric, lukket text) ON COMMIT DROP');
    db_modify('CREATE TEMP TABLE ordrelinjer (id integer, fast_db numeric, bogf_konto integer, leveret numeric, leveres numeric) ON COMMIT DROP');
    db_modify('CREATE TEMP TABLE lagerstatus (id serial, vare_id integer, variant_id integer, lager integer, beholdning numeric) ON COMMIT DROP');
    db_modify('CREATE TEMP TABLE variant_varer (id integer, variant_beholdning numeric) ON COMMIT DROP');
    db_modify('CREATE TEMP TABLE batch_kob (id serial, vare_id integer, linje_id integer, ordre_id integer, rest numeric, antal numeric, pris numeric, lager integer, variant_id integer) ON COMMIT DROP');
    db_modify('CREATE TEMP TABLE batch_salg (id serial, batch_kob_id integer, vare_id integer, linje_id integer, salgsdate date, ordre_id integer, antal numeric, pris numeric, lev_nr integer, lager integer, variant_id integer) ON COMMIT DROP');
    $ref = "Fixture O'Brien";
    $regnaar = 2026;
    $levdate = '2026-10-27';
    $fakturadate = $levdate;
    $lev_nr = 1;
    $db = 'sst883_temporary_fixture';
    db_modify("INSERT INTO ansatte VALUES ('Fixture O''Brien',2)");
    db_modify("INSERT INTO grupper (art,kodenr,fiscal_year,box1,box2,box3,box4,box8,box9) VALUES ('VG',1,2026,'1000','1000','2000','3000','on','')");
    $scenarios = [
        // label, requested warehouse, primary department mapping, legacy mapping, expected, existing row, variant, quantity, LG definitions
        ['removed to zero sale', 10, null, null, 0, true, 0, 1, [4]],
        ['removed to zero credit', 10, null, null, 0, true, 0, -1, [4]],
        ['removed to zero variant', 10, null, null, 0, true, 7, 1, [4]],
        ['missing zero row', 10, null, null, 0, false, 0, 1, [4]],
        ['primary mapping', 10, 4, null, 4, true, 0, 1, [1,4]],
        ['primary preferred', 10, 1, 4, 1, true, 0, -1, [1,4]],
        ['legacy mapping', 10, null, 4, 4, true, 0, 1, [1,4]],
        ['removed primary', 10, 10, 4, 4, false, 0, 1, [1,4]],
        ['both mappings removed', 10, 10, 10, 0, true, 0, -1, [4]],
        ['valid line preserved', 4, 1, null, 4, true, 7, 1, [1,4]],
        ['empty line uses department', 0, 4, null, 4, true, 0, 1, [4]],
        ['no warehouses zero', 0, null, null, 0, true, 0, 1, []],
        ['no warehouses removed line', 10, null, null, 0, true, 0, -1, []],
    ];
    foreach ($scenarios as [$label, $requested, $primary, $legacy, $expected, $exists, $variant, $quantity, $defined]) {
        db_modify("DELETE FROM grupper WHERE art IN ('LG','AFD')");
        foreach ($defined as $warehouse) {
            $department = $warehouse === $legacy ? '2' : '';
            db_modify("INSERT INTO grupper (art,kodenr,box1) VALUES ('LG',$warehouse,'$department')");
        }
        if ($primary !== null) {
            db_modify("INSERT INTO grupper (art,kodenr,box1) VALUES ('AFD',2,'$primary')");
        }
        check(resolvePostingWarehouse($requested, 1, 1) === $expected, "$label: mapping");
        foreach (['linjeopdat', 'lagerstatus'] as $writer) {
            db_modify('TRUNCATE varer, ordrelinjer, lagerstatus, variant_varer, batch_kob, batch_salg RESTART IDENTITY');
            $targetBefore = $exists ? 2 : 0;
            $otherStock = $defined ? 10 : 0;
            $otherWarehouse = $expected === 4 ? 1 : 4;
            $totalBefore = $targetBefore + $otherStock;
            db_modify("INSERT INTO varer VALUES (1,$totalBefore,'')");
            db_modify("INSERT INTO ordrelinjer VALUES (1,0,0,0,1)");
            db_modify("INSERT INTO variant_varer VALUES (7,$totalBefore)");
            if ($exists) {
                db_modify("INSERT INTO lagerstatus (vare_id,variant_id,lager,beholdning) VALUES (1,$variant,$expected,$targetBefore)");
            }
            if ($otherStock) {
                db_modify("INSERT INTO lagerstatus (vare_id,variant_id,lager,beholdning) VALUES (1,$variant,$otherWarehouse,$otherStock)");
            }
            db_modify("INSERT INTO batch_kob (vare_id,linje_id,ordre_id,rest,antal,pris,lager,variant_id) VALUES (1,9,9,10,11,5,$expected,$variant)");
            db_modify("INSERT INTO batch_salg (batch_kob_id,vare_id,linje_id,ordre_id,antal,lager,variant_id) VALUES (1,1,99,9,1,$expected,$variant)");
            $art = $quantity < 0 ? 'DK' : 'DO';
            if ($writer === 'linjeopdat') {
                linjeopdat(1,1,1,$totalBefore-$quantity,1,$quantity,10,10,0,0,1,1,'',$quantity < 0 ? 99 : 0,0,$variant,$requested,true);
                check((float) scalar('SELECT beholdning FROM varer WHERE id=1') === (float) ($totalBefore-$quantity), "$label: product balance");
                check((int) scalar('SELECT lager FROM batch_salg WHERE linje_id=1') === $expected, "$label: batch warehouse");
                check((float) scalar('SELECT leveret FROM ordrelinjer WHERE id=1') === (float) $quantity, "$label: delivered quantity");
                if ($variant) {
                    check((float) scalar('SELECT variant_beholdning FROM variant_varer WHERE id=7') === (float) ($totalBefore-$quantity), "$label: variant total");
                }
            } else {
                lagerstatus(1,$variant,$requested,$quantity);
                check((float) scalar('SELECT beholdning FROM varer WHERE id=1') === (float) $totalBefore, "$label: low-level writer leaves product total to caller");
            }
            check((float) scalar("SELECT beholdning FROM lagerstatus WHERE vare_id=1 AND variant_id=$variant AND lager=$expected") === (float) ($targetBefore-$quantity), "$label/$writer: target balance must receive only the delta");
            check((float) scalar('SELECT SUM(beholdning) FROM lagerstatus') === (float) ($totalBefore-$quantity), "$label/$writer: warehouse total");
            check((int) scalar('SELECT COUNT(*) FROM lagerstatus WHERE lager=10') === 0, "$label/$writer: removed warehouse recreated");
            if ($otherStock) {
                check((float) scalar("SELECT beholdning FROM lagerstatus WHERE lager=$otherWarehouse") === 10.0, "$label/$writer: unrelated warehouse changed");
            }
        }
    }
    echo 'PASS: ' . count($scenarios) . " scenarios through both real stock writers, $assertions assertions.\n";
} finally {
    db_modify('ROLLBACK');
    pg_close($connection);
}
