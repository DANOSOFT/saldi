<?php
// ---- includes/kreditorOrderFuncIncludes/topLine.php --- lap 5.0.0 --- 2026.10.08 ---
// 20261008 Sawaneh The supplier order list on the new page head (topbar addendum §5, prototype_dashboard_tema v5): Forslag /
//                  Ordrer / Faktura as a tab row, Ny ordre as the primary button, Hjælp kept as the hidden tour trigger,
//                  Tilbage to the creditor card only outside the shell.

include_once(__DIR__ . "/../stdFunc/pageBar.php");

$konto_id = if_isset($_GET, NULL, 'konto_id');
$returside = nav_back_url(if_isset($_GET, NULL, 'returside'));
$valg = if_isset($_GET, 'ordrer', 'valg');
$sort = if_isset($_GET, NULL, 'sort');
$hreftext = if_isset($hreftext, NULL);
if (is_array($sort)) {
	$sort = implode(',', $sort);
}
$backUrl = $konto_id ? "kreditorkort.php?id=" . (int) $konto_id : $returside;
$konto_param = $konto_id ? "&konto_id=" . (int) $konto_id : "";

$tabs = array();
if (!$hurtigfakt || $hurtigfakt == 'off') {
	$tabs[] = array(findtekst('827|Forslag', $sprog_id), "ordreliste.php?sort=" . urlencode((string) $sort) . "&valg=forslag$konto_param$hreftext", $valg == 'forslag');
}
$tabs[] = array(findtekst('107|Ordrer', $sprog_id), "ordreliste.php?valg=ordrer$konto_param", $valg == 'ordrer');
$tabs[] = array(findtekst('643|Faktura', $sprog_id), "ordreliste.php?valg=faktura$konto_param", $valg == 'faktura');

page_bar(array(
	'title' => findtekst('107|Ordrer', $sprog_id),
	'tabs' => $tabs,
	'primary' => array(findtekst('7004|Ny ordre', $sprog_id), "ordre.php?returside=ordreliste.php$konto_param"),
	'back' => $backUrl,
	'help' => true,
));
?>
