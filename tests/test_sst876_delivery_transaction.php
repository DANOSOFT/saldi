<?php
namespace Sst876DeliveryFixture;
// Copyright (c) 2026 Danosoft ApS
// 20261027 CDX/MJ SST-876 Execute the delivery page with transaction doubles to verify post-commit retries and rollback isolation.
require_once __DIR__ . '/../includes/std_func.php';
$events = array();
$depth = 0;
$result = 'OK';
function session_start() { return true; }
function session_id() { return 'fixture'; }
function if_isset($value) { return $value; }
function transaktion($command) {
	$GLOBALS['events'][] = $command;
	$GLOBALS['depth'] += $command === 'begin' ? 1 : -1;
}
function levering(...$arguments) {
	$GLOBALS['events'][] = 'document';
	return $GLOBALS['result'];
}
function shopSyncDrainQueue() {
	if ($GLOBALS['depth'] !== 0) {
		throw new \RuntimeException('Backlog attempted before commit');
	}
	$GLOBALS['events'][] = 'backlog';
}
function shopSyncAlertFailedItems() { $GLOBALS['events'][] = 'warning'; }
function shopSyncClearFailedItems() { $GLOBALS['events'][] = 'clear'; }
$src = file_get_contents(__DIR__ . '/../debitor/levering.php');
$src = preg_replace('/^[\t ]*include\("\.\.\/includes\/[^"\n]+"\);[\t ]*$/m', '', $src);
$src = substr($src, strlen('<?php'));
foreach (array('OK', 'Failed delivery') as $result) {
	$events = array();
	$depth = 0;
	$_GET = array('id' => 41, 'hurtigfakt' => '', 'genfakt' => '', 'pbs' => '', 'mail_fakt' => '', 'oioubl' => '');
	ob_start();
	try {
		eval('namespace Sst876DeliveryFixture;' . $src);
	} finally {
		$html = ob_get_clean();
	}
	$expected = $result === 'OK' ? array('begin', 'document', 'commit', 'backlog', 'warning')
		: array('begin', 'document', 'rollback', 'clear');
	if ($events !== $expected || $depth !== 0) {
		throw new \RuntimeException('Delivery transaction ordering differs: ' . json_encode($events));
	}
	if ($result === 'OK' && strpos($html, 'ordre.php?id=41') === false) {
		throw new \RuntimeException('The delivery redirect was lost');
	}
	echo 'PASS: ', $result === 'OK' ? 'Delivery commits before retries and warning' : 'Rolled-back delivery never drains retries or shows sync warnings', "\n";
}
