<?php
// 20261030 CL/MJ SST-882 Child process for EinvoiceRejectionTest: a tenant that has not run the update yet has no einvoice_events.
//                The webhook and a failed send must then write nothing and create nothing (tables only come from includes/betweenUpdates.php).

$GLOBALS['db'] = 'saldi_test';
$GLOBALS['stubSql'] = array();

function db_escape_string($s) {
	return str_replace("'", "''", (string) $s);
}
function db_select($sql, $where = '') {
	$GLOBALS['stubSql'][] = $sql;
	return false;
}
function db_fetch_array($r) {
	return $r;
}
function db_modify($sql, $where = '') {
	$GLOBALS['stubSql'][] = $sql;
	return true;
}

require dirname(__DIR__, 4) . '/includes/stdFunc/einvoiceEvents.php';

echo json_encode(array(
	'webhook' => einvoice_record_webhook('1042', 'Error', '[DK-R-017]'),
	'send' => einvoice_log_event(42, '1042', 'send', 'SendFailed', 'E-APS24003'),
	'latest' => einvoice_latest_rejection(42),
	'sql' => $GLOBALS['stubSql'],
));
