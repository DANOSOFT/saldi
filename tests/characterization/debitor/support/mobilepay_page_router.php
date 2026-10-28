<?php
// Router for `php -S`, used by MobilepayPaymentFlowTest. Runs one shipped MobilePay page
// (debitor/payments/mobilepay/mobilepay.php or mobilepayListen.php) the way the till reaches it
// through debitor/payments/<page>, except that the settings come from the scenario file and the
// Vipps API is the stub in tests/fixtures/mobilepay/vipps_stub.php. It runs under a web SAPI, so
// the page's header() redirects and exit() behave as in production.
//
// The scenario file (env MP_PAGE_SCENARIO) holds:
//   apiBase   base url the page calls instead of https://api.vipps.no
//   root      a scratch tree with debitor/payments/ and temp/<db>/ (the page reads ../../temp/<db>/)
//   db        account name, used in the temp path
//   settings  var_name => var_value of the settings rows with var_grp 'mobilepay'
//
// 20261027 CL/LH Created (PR #502).

$repo     = dirname(__DIR__, 4);
$scenario = json_decode((string) file_get_contents(getenv('MP_PAGE_SCENARIO')), true);
$page     = basename((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (!in_array($page, array('mobilepay.php', 'mobilepayListen.php'), true)) {
	http_response_code(404);
	return true;
}

/**
 * Points the page at the stub (see paymentui.php).
 *
 * @return string
 */
function mobilepay_api_base() {
	return $GLOBALS['scenario']['apiBase'];
}

/**
 * Answers the only reads the pages make: the 'mobilepay' settings and the till's terminal row.
 *
 * @param string $qtxt
 * @param string $source
 * @return ArrayIterator
 */
function db_select($qtxt, $source = '') {
	$rows = array();
	if (preg_match("/var_name = '([A-Za-z_]+)' AND var_grp = 'mobilepay'/", $qtxt, $m)) {
		$value = $GLOBALS['scenario']['settings'][$m[1]] ?? null;
		if ($value !== null) {
			$rows[] = array(0 => $value, 'var_value' => $value);
		}
	} elseif (strpos($qtxt, "beskrivelse = 'Pos valg'") !== false) {
		$rows[] = array(0 => '', 'box4' => '');
	} else {
		throw new RuntimeException('Unexpected query: ' . $qtxt);
	}
	return new ArrayIterator($rows);
}

/**
 * @param ArrayIterator $rows
 * @return array|false
 */
function db_fetch_array($rows) {
	if (!$rows->valid()) {
		return false;
	}
	$row = $rows->current();
	$rows->next();
	return $row;
}

/**
 * The payment pages write nothing to the database.
 *
 * @param string $qtxt
 * @param string $source
 * @return void
 */
function db_modify($qtxt, $source = '') {
	throw new RuntimeException('Unexpected write: ' . $qtxt);
}

/**
 * findtekst() as on an account whose tekster table does not have the id yet: the Danish text of
 * that id in importfiler/tekster.csv, so a text id taken by another string shows up here.
 *
 * @param string $textId   "<id>|<fallback>".
 * @param int    $languageID
 * @return string
 */
function findtekst($textId, $languageID) {
	$parts = explode('|', $textId, 2);
	foreach (file($GLOBALS['repo'] . '/importfiler/tekster.csv') as $line) {
		$cols = explode("\t", rtrim($line, "\r\n"));
		if ($cols[0] === $parts[0]) {
			return $cols[1];
		}
	}
	return $parts[1] ?? $textId;
}

include $repo . '/includes/std_func.php';

# What debitor/payments/<page> and includes/online.php provide in production
$db       = $scenario['db'];
$sprog_id = 1;
$regnaar  = 1;
$version  = '5.0.0';
$_COOKIE['saldi_pos'] = '1';

chdir($scenario['root'] . '/debitor/payments');
include $repo . '/debitor/payments/mobilepay/' . $page;
return true;
