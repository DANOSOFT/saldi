<?php
// 20260920 CDX/LH Reject SQL suffixes in model limits using real PostgreSQL result sets.
if (PHP_SAPI !== 'cli') exit('CLI only');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
$dsn = getenv('SALDI_TEST_DSN');
if (!$dsn) exit("SKIP: set SALDI_TEST_DSN to a PostgreSQL test connection.\n");
$connection = pg_connect($dsn);
require_once(__DIR__ . '/../restapi/models/lager/VareModel.php');
require_once(__DIR__ . '/../restapi/models/finans/AccountModel.php');
function db_select($sql, $location = '') { return pg_query($GLOBALS['connection'], $sql); }
function db_fetch_array($result) { return pg_fetch_assoc($result); }
function db_num_rows($result) { return pg_num_rows($result); }
function checkLimit($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
db_select('CREATE TEMP TABLE varer (id integer)');
$columns = array('kontonr', 'beskrivelse', 'kontotype', 'moms', 'fra_kto', 'til_kto', 'lukket', 'primo', 'saldo', 'regnskabsaar', 'genvej', 'overfor_til', 'anvendelse', 'modkonto', 'valuta', 'valutakurs', 'map_to');
db_select('CREATE TEMP TABLE kontoplan (id integer,' . implode(',', array_map(function ($column) { return "$column text"; }, $columns)) . ')');
db_select('INSERT INTO varer SELECT generate_series(1,80)');
db_select('INSERT INTO kontoplan(id,kontonr) SELECT generate_series(1,80),\'1000\'');
foreach (array('VareModel'=>20, 'AccountModel'=>50) as $class=>$default) {
    $ids = static function ($items) { return array_map(static function ($item) { return (int)$item->getId(); }, $items); };
    $baseline = $ids($class::getAllItems('id', 'ASC', $default));
    foreach (array('1 OFFSET 3', '1; SELECT 1', '1 UNION SELECT 999', '', 'abc', -1, 0, 999999, array(1)) as $limit) {
        checkLimit($ids($class::getAllItems('id', 'ASC', $limit)) === $baseline, "$class rejects malformed/out-of-range limit and retains the exact default page");
    }
    checkLimit($ids($class::getAllItems('id', 'ASC', '2')) === array(1,2), "$class honors a decimal query-string limit");
    checkLimit($ids($class::getAllItems('id', 'DESC', 1)) === array(80), "$class preserves integer limits and sort direction");
}
