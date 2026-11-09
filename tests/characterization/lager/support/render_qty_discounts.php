<?php
// 20261027 CL/LH MB-78: Child-process runner for QtyDiscountDecimalsTest.
//                Calls renderQtyDiscounts() with explicit inputs and reports its HTML, input isolation and PHP notices as JSON.
// 20261109 CDX/NTR Call the renderer twice with explicit inputs and report loading output, input mutations and PHP notices.

/**
 * Input (JSON on stdin): the item-card values to pass to renderQtyDiscounts() -
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

$errors = array();
error_reporting(E_ALL);
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use (&$errors): bool {
	$errors[] = basename($errfile) . ":$errline $errstr";
	return true;
});

ob_start();
require_once __DIR__ . '/../../../../lager/productCardIncludes/showQtyDiscounts.php';
$includeOutput = ob_get_clean();

$render = static function () use (&$input): string {
	ob_start();
	renderQtyDiscounts(
		discountAmounts: $input['m_rabat_array'],
		quantities: $input['m_antal_array'],
		discountType: $input['m_type'],
		vatPercent: (float) $input['incl_moms'],
		readOnly: (bool) $input['rabatgruppe'],
		hasPercentageOffer: (bool) $input['special_price'] && $input['specialType'] == 'percent',
		languageId: 1
	);
	return ob_get_clean();
};
$before = $input;
$html = $render();
$repeatHtml = $render();

restore_error_handler();
print json_encode(array(
	'html' => $html,
	'errors' => $errors,
	'includeOutput' => $includeOutput,
	'inputsUnchanged' => $input === $before,
	'repeatHtml' => $repeatHtml,
));
