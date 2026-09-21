<?php
// 20260920 CDX/LH Real PostgreSQL coverage of product-group year and nested account context.
if (PHP_SAPI !== 'cli') exit('CLI only');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
$dsn = getenv('SALDI_TEST_DSN');
if (!$dsn) exit("SKIP: set SALDI_TEST_DSN to a PostgreSQL test connection.\n");
$connection = pg_connect($dsn);
require_once(__DIR__ . '/../restapi/models/lager/VareGruppeModel.php');
function db_select($sql, $location = '') { return pg_query($GLOBALS['connection'], $sql); }
function db_fetch_array($result) { return pg_fetch_assoc($result); }
function db_num_rows($result) { return pg_num_rows($result); }
function db_escape_string($value) { return pg_escape_string($GLOBALS['connection'], $value); }
function checkYear($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
$boxes = array_map(function ($i) { return "box$i text DEFAULT ''"; }, range(1,14));
db_select('CREATE TEMP TABLE grupper (id integer, art text, kodenr integer, fiscal_year integer, beskrivelse text,' . implode(',', $boxes) . ')');
$columns = array('kontonr', 'beskrivelse', 'kontotype', 'moms', 'fra_kto', 'til_kto', 'lukket', 'primo', 'saldo', 'regnskabsaar', 'genvej', 'overfor_til', 'anvendelse', 'modkonto', 'valuta', 'valutakurs', 'map_to');
db_select('CREATE TEMP TABLE kontoplan (id integer,' . implode(',', array_map(function ($column) { return in_array($column, array('kontonr', 'regnskabsaar')) ? "$column integer" : "$column text DEFAULT ''"; }, $columns)) . ')');
db_select("INSERT INTO grupper(id,art,kodenr,fiscal_year,beskrivelse,box4) VALUES (1,'RA',1,1,'old',''),(2,'RA',2,2,'latest',''),(101,'VG',1,1,'old group','1200'),(202,'VG',1,2,'current group','1200')");
db_select("INSERT INTO kontoplan(id,kontonr,regnskabsaar) VALUES (11,'1200','1'),(22,'1200','2')");
foreach (array(null, '') as $initial) {
    $regnaar = $initial;
    $items = VareGruppeModel::getAllItems('id');
    checkYear(count($items) === 1 && $items[0]->getId() === 202, 'missing REST context returns exactly current-year groups');
    checkYear($items[0]->getSellAccount()->getId() == 22, 'nested posting account uses the same resolved year');
}
$regnaar = '1';
$items = VareGruppeModel::getAllItems('id');
checkYear(count($items) === 1 && $items[0]->getId() === 101 && $regnaar === '1', 'explicit existing year is preserved');
checkYear($items[0]->getSellAccount()->getId() == 11, 'explicit year also selects its posting account');
unset($regnaar);
$items = VareGruppeModel::findBy('kodenr', '1');
checkYear(count($items) === 1 && $items[0]->getId() === 202, 'filtered list resolves the same tenant year');
unset($regnaar);
db_select("DELETE FROM grupper WHERE art='RA'");
checkYear(VareGruppeModel::getAllItems() === [], 'tenant without a configured year cannot leak groups from other years');
