<?php
// ---- lager/lister/topLineVarer.php --- lap 5.0.0 --- 2026.10.08 ---
// 20261008 Sawaneh The item lists (vareliste, ordrestatus, indkøb, serialnumber, styklister) on the new page head (topbar
//                  addendum §5, prototype_dashboard_tema v5): title in the content, the lists as a tab row, Ny vare as the
//                  primary button on Vareliste, Hjælp kept as the hidden tour trigger for the Assist menu, Luk only outside
//                  the shell.

include(get_relative()."/includes/oldDesign/header.php");
include(get_relative()."/includes/topline_settings.php");
include_once(get_relative()."/includes/stdFunc/pageBar.php");
$returside = nav_back_url(if_isset($returside, null));

$border = 'border:1px';
$TableBG = "bgcolor=$bgcolor";
$rs = 'returside=' . urlencode((string) $returside);

$tabs = array(array(findtekst('957|Vareliste', $sprog_id), "vareliste.php?$rs", $valg == 'Vareliste'));
if (substr($rettigheder, 5, 1)) {
	$tabs[] = array(findtekst('546|Ordrevisning', $sprog_id), "ordrestatus.php?$rs", $valg == 'Ordrevisning');
}
if (substr($rettigheder, 7, 1)) {
	$tabs[] = array(findtekst('4979|Indkøb', $sprog_id), "indkøb.php?$rs", $valg == 'Indkøb');
}
$tabs[] = array(findtekst('4980|Serienumre', $sprog_id), "serialnumber.php?$rs", $valg == 'Serienumre');
$tabs[] = array('Styklister', "styklister.php?$rs", $valg == 'Styklister');

page_bar(array(
	'title' => findtekst('110|Varer', $sprog_id),
	'tabs' => $tabs,
	'primary' => ($valg == 'Vareliste') ? array(findtekst('7003|Ny vare', $sprog_id), '../varekort.php?returside=lister/vareliste.php') : null,
	'back' => $returside,
	'help' => true,
));
?>
