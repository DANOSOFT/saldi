<?php
// 20261030 CL/MJ SST-882 Child process for EinvoiceRejectionTest: runs includes/stdFunc/einvoiceEvents.php against stub database functions.
//                It prints, as JSON, the SQL each scenario sent, so the test can check the escaping without a database.

$GLOBALS['db'] = 'saldi_test';
$GLOBALS['stubTableExists'] = true;
$GLOBALS['stubOrder'] = array('id' => 42, 'cvrnr' => '');
$GLOBALS['stubSql'] = array();

function db_escape_string($s) {
	return str_replace("'", "''", (string) $s);
}
function db_select($sql, $where = '') {
	$GLOBALS['stubSql'][] = $sql;
	if (strpos($sql, 'information_schema.tables') !== false) {
		return $GLOBALS['stubTableExists'] ? array('table_name' => 'einvoice_events') : false;
	}
	if (strpos($sql, 'FROM ordrer') !== false) {
		return $GLOBALS['stubOrder'];
	}
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

$out = array();

$GLOBALS['stubSql'] = array();
$out['webhook'] = array(
	'written' => einvoice_record_webhook("1042' OR '1'='1", 'Error', "[DK-R-017]-For Danish Customers it is mandatory to specify schemeID as \"0184\" (O'Brien)"),
	'sql' => $GLOBALS['stubSql'],
);

$GLOBALS['stubSql'] = array();
$GLOBALS['stubOrder'] = false;
$out['unknown_invoice'] = array('written' => einvoice_record_webhook('999', 'Error', 'x'), 'sql' => $GLOBALS['stubSql']);

$GLOBALS['stubSql'] = array();
$out['send'] = array(
	'written' => einvoice_log_event(42, '1042', 'send', 'SendFailed', 'E-APS24003 Error while transmitting request', '29190909'),
	'sql' => $GLOBALS['stubSql'],
);

echo json_encode($out);
