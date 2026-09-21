<?php
// 20260920 CDX/LUI Verify CSV mapping and optional isolated PostgreSQL import/rollback.
// 20260921 CDX/LH Honor the native gate DSN, including authentication and nondefault ports.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
require_once __DIR__ . '/../includes/legacyItemImport.php';
function checkImport($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
$path = tempnam(sys_get_temp_dir(), 'saldi-import-');
$labels = ['Begge varenr.','Beskrivelse','Salgspris','Kostpris','Enhed','Varegrp.'];
try {
    foreach (['Semikolon'=>';','Komma'=>',','Tabulator'=>"\t"] as $format => $separator) {
        $handle = fopen($path, 'w');
        fputcsv($handle, ['B-1', "Bolt; owner's", '12345', '75,50', 'stk', '1'], $separator, '"', '');
        fwrite($handle, "\n"); fclose($handle);
        $rows = legacyItemImportRead($path, $format);
        $items = legacyItemImportPrepare($rows, $labels, 0, 0, [1]);
        checkImport(count($items) === 1 && $items[0]['description'] === "Bolt; owner's" && $items[0]['sales_price'] === 123.45 && $items[0]['cost_price'] === 75.5,
            "$format quoted fields, blank lines and exact price units");
    }
    checkImport(legacyItemImportNumber('1.234,56') === 1234.56 && legacyItemImportNumber('1234.56') === 1234.56, 'Danish and plain decimal values');
    $discounted = legacyItemImportPrepare([['B-1','Bolt','10000','stk']], ['Eget varenr.','Beskrivelse','Salgspris','Enhed'], 1, 20, [1]);
    checkImport($discounted[0]['cost_price'] === 80.0, 'Discount determines cost only when no mapped cost column exists');
    foreach ([
        [['B-1','Bolt','oops','12','stk','1']],
        [['B-1','Bolt','10000','12','stk','9']],
        [['B-1','Bolt','10000','12','stk','1'],['B-2','Bolt','10000']],
    ] as $invalid) {
        try { legacyItemImportPrepare($invalid, $labels, 0, 0, [1]); throw new RuntimeException('Invalid file accepted'); }
        catch (InvalidArgumentException $expected) { echo "PASS: invalid amount/group/truncated row rejected before writes\n"; }
    }
    $fixed = str_repeat(' ', 68);
    foreach ([[1,'FIXED'],[12,'Fixed item'],[47,'001000'],[58,'stk'],[61,'1'],[64,'0'],[65,'010']] as [$position,$value]) {
        $fixed = substr_replace($fixed, $value, $position, strlen($value));
    }
    file_put_contents($path, $fixed . "\n");
    checkImport(legacyItemImportRead($path, 'Brdr. Dahl')[0][0] === 'FIXED', 'Fixed-width Brdr. Dahl format remains readable');
} finally { unlink($path); }

$dsn = getenv('SALDI_CHAR_PG_DSN') ?: getenv('SALDI_TEST_DSN');
if (!$dsn && getenv('SALDI_ITEM_IMPORT_PGHOST')) {
    $parts = [];
    $defaults = ['dbname' => 'postgres', 'user' => 'postgres'];
    foreach (['host' => 'PGHOST', 'port' => 'PGPORT', 'dbname' => 'PGDATABASE', 'user' => 'PGUSER', 'password' => 'PGPASSWORD'] as $key => $suffix) {
        $value = getenv('SALDI_ITEM_IMPORT_' . $suffix) ?: ($defaults[$key] ?? false);
        if ($value !== false && $value !== '') {
            $parts[] = $key . "='" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
        }
    }
    $dsn = implode(' ', $parts);
}
if (!$dsn || !extension_loaded('pgsql')) {
    echo "SKIP: PostgreSQL write/rollback checks need pgsql and SALDI_CHAR_PG_DSN (use an isolated test database)\n";
    exit;
}
$connection = pg_connect($dsn);
if (!$connection) { throw new RuntimeException('Isolated test database unavailable'); }
function db_escape_string($value) { return pg_escape_string($GLOBALS['connection'], $value); }
function db_select($sql, $source = '') { return pg_query($GLOBALS['connection'], $sql); }
function db_fetch_array($result) { return pg_fetch_assoc($result); }
function db_modify($sql, $source = '') {
    pg_send_query($GLOBALS['connection'], $sql);
    $result = pg_get_result($GLOBALS['connection']);
    return pg_result_status($result) === PGSQL_COMMAND_OK ? "0\tquery accepted" : "1\t" . pg_result_error($result);
}
function transaktion($command) { pg_query($GLOBALS['connection'], $command); }
pg_query($connection, "CREATE TEMP TABLE varer (id serial primary key,varenr text unique,beskrivelse text,salgspris numeric(15,3),kostpris numeric(15,3),enhed text,gruppe integer,min_lager numeric,beholdning numeric);
CREATE TEMP TABLE settings (id serial,var_name text,var_grp text,var_value text);
CREATE TEMP TABLE vare_lev (id serial primary key,vare_id integer,lev_id integer,lev_varenr text,kostpris numeric CHECK(kostpris>=0),UNIQUE(vare_id,lev_id));
INSERT INTO varer (varenr,beskrivelse,salgspris,kostpris,enhed,gruppe,beholdning) VALUES ('B-1','Existing',100,80,'stk',1,5);
INSERT INTO vare_lev (vare_id,lev_id,lev_varenr,kostpris) VALUES (1,7,'old',80);
INSERT INTO settings(var_name,var_grp,var_value) VALUES('min_beholdning','productOptions','2');");
$items = legacyItemImportPrepare([['B-1',"Owner's bolt",'12345','75,50','stk','1'],['B-2','New bolt','20000','100.00','stk','1']], $labels, 0, 0, [1]);
checkImport(legacyItemImportApply($items, 7) === 2, 'Existing and new item rows imported in one transaction');
$result = pg_fetch_all(pg_query($connection, 'SELECT v.varenr,v.beskrivelse,v.salgspris,v.kostpris,v.beholdning,v.min_lager,l.lev_id,l.lev_varenr FROM varer v JOIN vare_lev l ON l.vare_id=v.id ORDER BY v.id'));
checkImport(count($result) === 2 && $result[0]['beskrivelse'] === "Owner's bolt" && (float)$result[0]['salgspris'] === 123.45 && (float)$result[0]['kostpris'] === 75.5 && (float)$result[0]['beholdning'] === 5.0 && $result[1]['lev_id'] === '7' && $result[1]['lev_varenr'] === 'B-2' && (float)$result[1]['min_lager'] === 2.0, 'Correct supplier ids, escaped text, numeric prices, minimum stock and unchanged existing stock');
$items[0]['cost_price'] = 1;
$items[1]['cost_price'] = -1;
try { legacyItemImportApply($items, 7); throw new LogicException('Constraint failure accepted'); }
catch (RuntimeException $expected) { echo "PASS: rejected later write surfaces import failure\n"; }
$costs = pg_fetch_all(pg_query($connection, 'SELECT kostpris FROM varer ORDER BY id'));
checkImport((float)$costs[0]['kostpris'] === 75.5 && (float)$costs[1]['kostpris'] === 100.0, 'Later write failure rolls back earlier item updates');
pg_close($connection);
