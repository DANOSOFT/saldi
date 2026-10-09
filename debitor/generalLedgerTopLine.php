<?php
// 20261009 Sawaneh The ledger head on the new page head (page_bar): Luk, Udskriv and E-mail as actions; sticky cell kept.
// --- debitor/generalLedgerTopLine.php --- patch 5.0.0 --- 2026-03-19 ---

include_once("../includes/stdFunc/pageBar.php");
print "<tr><td class='debitor-ledger-topline-cell pg-sticky'>";
page_bar(array(
	'title' => $title,
	'actions' => array(
		array($printLabel, '#', 'bx-printer', '', 'onclick' => 'showLangModalKontoprint();'),
		array(findtekst('52|E-mail', $sprog_id), '#', 'bx-envelope', '', 'onclick' => "window.open('" . debitorGeneralLedgerEscape($emailUrl) . "','kontomail','" . debitorGeneralLedgerEscape($jsvars) . "');"),
	),
	'back' => $backUrl,
));
print "</td></tr>\n";
?>
<style>
.headerbtn,
.center-btn {
  font-size: 10pt;
	display: flex;
	align-items: center;
	text-decoration: none;
	gap: 5px;
}

.debitor-ledger-topline-cell {
	position: sticky;
	top: 0;
	z-index: 60;
	background: #f5f6fb;
}

.topLine {
	position: sticky;
	top: 0;
	z-index: 61;
	background: #f5f6fb;
}

a:link {
	text-decoration: none;
}

.center-btn {
	width: 100% !important;
	border-radius: 5px;
	padding: 4px; 

	/* justify-content: center; */
}

.topLine a,
.topLine a:hover,
.topLine a:focus {
	text-decoration: none !important;
	background: none !important;
}
</style>
