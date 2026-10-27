<?php
// 20261027 CL/LH MB-78: Child-process runner for QtyDiscountDecimalsTest. Renders
//                  lager/productCardIncludes/showQtyDiscounts.php with the globals varekort.php
//                  sets and prints {"html": ..., "errors": [...]} as JSON, every PHP notice included.

/**
 * Input (JSON on stdin): the globals showQtyDiscounts.php reads -
 * m_antal_array, m_rabat_array, m_type, incl_moms, rabatgruppe, special_price, specialType.
 */
$input = json_decode((string) stream_get_contents(STDIN), true);
if (!is_array($input)) {
	fwrite(STDERR, "render_qty_discounts.php: expected a JSON object on stdin\n");
	exit(2);
}

// findtekst() reads the tekster table; the labels are not under test, so answer with the id.
function findtekst($textId, $languageID) {
	return "tekst-$textId";
}

$repoRoot = dirname(__DIR__, 4);
require_once $repoRoot . '/includes/std_func.php';

$errors = array();
error_reporting(E_ALL);
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use (&$errors): bool {
	$errors[] = basename($errfile) . ":$errline $errstr";
	return true;
});

$sprog_id      = 1;
$m_antal_array = $input['m_antal_array'];
$m_rabat_array = $input['m_rabat_array'];
$m_type        = $input['m_type'];
$incl_moms     = $input['incl_moms'];
$rabatgruppe   = $input['rabatgruppe'];
$special_price = $input['special_price'];
$specialType   = $input['specialType'];

ob_start();
include $repoRoot . '/lager/productCardIncludes/showQtyDiscounts.php';
$html = ob_get_clean();

restore_error_handler();
print json_encode(array('html' => $html, 'errors' => $errors));
