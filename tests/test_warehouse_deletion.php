<?php
// Copyright (c) 2026 Danosoft ApS
// 20261001 CDX/PHR Verify warehouse deletion against real SQL while preserving stock history.
if (!getenv('SALDI_CHAR_DSN')) {
	echo "SKIP: set SALDI_CHAR_DSN, SALDI_CHAR_PGUSER and SALDI_CHAR_PGPASS.\n";
	exit;
}
require_once(__DIR__ . '/../includes/std_func.php');
require_once(__DIR__ . '/../systemdata/syssetupIncludes/warehouseDeletion.php');
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$pdo = new PDO(getenv('SALDI_CHAR_DSN'), getenv('SALDI_CHAR_PGUSER'), getenv('SALDI_CHAR_PGPASS'), array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
function db_select($sql, $context) { return $GLOBALS['pdo']->query($sql); }
function db_fetch_array($result) { return $result->fetch(PDO::FETCH_ASSOC); }
function db_modify($sql, $context) { return $GLOBALS['pdo']->exec($sql); }
function checkWarehouseDeletion($condition, $message)
{
	if (!$condition) {
		throw new RuntimeException($message);
	}
	echo "PASS: $message\n";
}
function warehouseSnapshot()
{
	$snapshot = array();
	foreach (array('grupper', 'batch_kob', 'batch_salg', 'regulering', 'ordrelinjer', 'ordrer', 'lagerstatus') as $table) {
		$snapshot[$table] = $GLOBALS['pdo']->query("SELECT * FROM $table ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
	}
	return $snapshot;
}
$pdo->beginTransaction();
try {
	$pdo->exec('CREATE TEMP TABLE grupper (id integer,art text,kodenr integer,box1 text,fiscal_year integer)');
	foreach (array('batch_kob', 'batch_salg', 'regulering', 'ordrelinjer', 'ordrer') as $table) {
		$pdo->exec("CREATE TEMP TABLE $table (id integer,lager integer,antal numeric)");
	}
	$pdo->exec('CREATE TEMP TABLE lagerstatus (id integer,lager integer,beholdning numeric,variant_id integer)');
	$pdo->exec("INSERT INTO grupper VALUES (1,'LG',10,'',9),(2,'LG',10,'',10),(3,'LG',11,'',10),(4,'VG',10,'',10)");
	$pdo->exec('INSERT INTO batch_kob VALUES (1,10,303),(2,11,5)');
	$pdo->exec('INSERT INTO batch_salg VALUES (1,10,303)');
	$pdo->exec('INSERT INTO lagerstatus VALUES (1,10,0,0),(2,11,5,0)');
	$before = warehouseSnapshot();
	checkWarehouseDeletion(deleteWarehouseDefinition(2) === '', 'A duplicate fiscal-year definition can be removed');
	$after = warehouseSnapshot();
	unset($before['grupper'], $after['grupper']);
	checkWarehouseDeletion($before === $after, 'Removing a duplicate leaves every stock row and higher warehouse number unchanged');
	$before = warehouseSnapshot();
	checkWarehouseDeletion(deleteWarehouseDefinition(1) !== '', 'A zero net balance does not permit deletion of historical batches');
	checkWarehouseDeletion(warehouseSnapshot() === $before, 'Refused deletion writes nothing');
	checkWarehouseDeletion(deleteWarehouseDefinition(4) === '' && warehouseSnapshot() === $before, 'A non-warehouse group ID cannot be deleted');
	checkWarehouseDeletion(deleteWarehouseDefinition(999) === '' && warehouseSnapshot() === $before, 'A missing definition is harmless');
	$pdo->exec("INSERT INTO grupper VALUES (10,'LG',7,'',10)");
	foreach (array('batch_kob', 'batch_salg', 'regulering', 'ordrelinjer', 'ordrer') as $table) {
		$pdo->exec("INSERT INTO $table VALUES (100,7,0)");
		$before = warehouseSnapshot();
		checkWarehouseDeletion(deleteWarehouseDefinition(10) !== '' && warehouseSnapshot() === $before, "$table references block deleting the last definition, even at zero quantity");
		$pdo->exec("DELETE FROM $table WHERE id=100");
	}
	$pdo->exec('INSERT INTO lagerstatus VALUES (100,7,-1,42)');
	checkWarehouseDeletion(deleteWarehouseDefinition(10) !== '', 'Negative variant stock also blocks deletion');
	$pdo->exec('DELETE FROM lagerstatus WHERE id=100');
	$pdo->exec("INSERT INTO grupper VALUES (100,'AFD',3,'7',0)");
	checkWarehouseDeletion(deleteWarehouseDefinition(10) !== '', 'Department routing blocks deletion');
	$pdo->exec('DELETE FROM grupper WHERE id=100');
	checkWarehouseDeletion(deleteWarehouseDefinition(10) === '', 'A truly unused warehouse can be deleted');
	checkWarehouseDeletion((int)$pdo->query("SELECT kodenr FROM grupper WHERE id=3")->fetchColumn() === 11, 'Deleting an unused warehouse preserves higher warehouse numbers');
} finally {
	$pdo->rollBack();
}
