<?php
// ---- debitor/debLstIncludes/topLine.php --- lap 5.0.0 --- 2026.10.08 ---
// 20250911 LOE Sets value of jobkort directly.
// 20261008 Sawaneh The Debitor lists (debitor.php, debitor_historik.php, debitor_kommission.php, jobliste.php) are the first
//                  pages on the new page head (topbar addendum §5, prototype_dashboard_tema v5): title in the content,
//                  Debitorer / Historik / Kommission / Opgaveliste as a tab row, Mailtekst as a page action, Ny as the primary
//                  button, Hjælp kept as the hidden tour trigger for the Assist menu, Tilbage only outside the shell.

include("../includes/oldDesign/header.php");
include("../includes/topline_settings.php");
include_once("../includes/stdFunc/pageBar.php");

$border = 'border:1px';
$TableBG = "bgcolor=$bgcolor";

$backUrl = nav_back_url(isset($_GET['returside']) ? $_GET['returside'] : null);
if (!isset($jobkort)) { #LOE
	$jobkort = isset($_GET['jobkort']) ? $_GET['jobkort'] : null;
}
if (!isset($konto_id)) $konto_id = 0;
if (!isset($ordre_id)) $ordre_id = 0;

$tabs = array(
	array(findtekst('908|Debitorer', $sprog_id), 'debitor.php', $valg == 'debitor'),
	array(findtekst('907|Historik', $sprog_id), 'debitor_historik.php', $valg == 'historik'),
);
if ($valg == 'kommission' || !empty($showMySale)) {
	$tabs[] = array(findtekst('909|Kommission', $sprog_id), 'debitor_kommission.php', $valg == 'kommission');
}
if ($jobkort) {
	$tabs[] = array(findtekst('38|Opgaveliste', $sprog_id), 'jobliste.php?valg=jobkort&jobkort=' . urlencode((string) $jobkort), $valg == 'jobkort');
}

$actions = array();
if ($valg == 'kommission' || $valg == 'historik') {
	$mailTxtField = ($valg == 'historik') ? 'documents.email.customer_text' : 'documents.email.mysale_text'; // 20261003 Sawaneh G6.3: mail texts live in Settings
	$actions[] = array(findtekst('218|Mailtekst', $sprog_id), '../systemdata/settingsSection.php?s=documents.email&field=' . $mailTxtField, 'bx-envelope');
}

if ($jobkort && $valg == 'jobkort') {
	$jobUrl = 'jobkort.php?returside=jobliste.php&konto_id=' . (int) $konto_id . '&ordre_id=' . (int) $ordre_id; // WP-2.2: was returside=jobkort.php (a new row on every Tilbage)
	$primary = array(findtekst('7001|Nyt jobkort', $sprog_id), $jobUrl);
	if ($popup) {
		$primary['onclick'] = "job=window.open('" . $jobUrl . "','job','scrollbars=1,resizable=1');job.focus();";
	}
} else {
	$primary = array(findtekst('7000|Ny debitor', $sprog_id), 'debitorkort.php?returside=debitor.php');
}

page_bar(array(
	'title' => findtekst('908|Debitorer', $sprog_id),
	'tabs' => $tabs,
	'actions' => $actions,
	'primary' => $primary,
	'back' => $backUrl,
	'help' => ($valg != 'jobkort'),
));
?>
