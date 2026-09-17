<?php
// --- kreditor/generalLedgerTopLine.php --- patch 5.0.0 --- 2026-03-18 ---

$back_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';
$ledger_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 -960 960 960" width="20px" fill="#ffffff"><path d="M280-600v-80h560v80H280Zm0 160v-80h560v80H280Zm0 160v-80h560v80H280ZM160-600q-17 0-28.5-11.5T120-640q0-17 11.5-28.5T160-680q17 0 28.5 11.5T200-640q0 17-11.5 28.5T160-600Zm0 160q-17 0-28.5-11.5T120-480q0-17 11.5-28.5T160-520q17 0 28.5 11.5T200-480q0 17-11.5 28.5T160-440Zm0 160q-17 0-28.5-11.5T120-320q0-17 11.5-28.5T160-360q17 0 28.5 11.5T200-320q0 17-11.5 28.5T160-280Z"/></svg>';
$print_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" viewBox="0 -960 960 960" fill="#FFFFFF"><path d="M360-720H120v-120h720v120H600v120H360v-120Zm360 360q25 0 42.5-17.5T780-420q0-25-17.5-42.5T720-480q-25 0-42.5 17.5T660-420q0 25 17.5 42.5T720-360ZM240-120v-240H120v-240q0-50 35-85t85-35h480q50 0 85 35t35 85v240H720v240H240Zm120-80h240v-160H360v160Zm-160-240h560v-160H200v160Z"/></svg>';
$mail_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="20" width="20" viewBox="0 -960 960 960" fill="#FFFFFF"><path d="M120-160q-33 0-56.5-23.5T40-240v-480q0-33 23.5-56.5T120-800h720q33 0 56.5 23.5T920-720v480q0 33-23.5 56.5T840-160H120Zm360-280L120-680v440h720v-440L480-440Zm0-80 360-240H120l360 240Zm-360-160v440-440Z"/></svg>';

print "<tr><td class='creditor-ledger-topline-cell' height='25' align='center' valign='top'>";
print "<table class='topLine' width='100%' align='center' border='0' cellspacing='2' cellpadding='0' style='width:100%; box-sizing:border-box;'><tbody><tr class='header-row'>";

print "<td width='10%' style='$buttonStyle'>";
print "<a href='" . htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') . "' accesskey='L'>";
print "<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
print $back_icon . findtekst('2172|Luk', $sprog_id);
print "</button></a></td>";

print "<td width='75%' style='$topStyle; text-align:center; font-weight:700; color:#fff;'>";
print "<span class='center-btn' style='justify-content:center; width:100%;'>" . $ledger_icon . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</span>";
print "</td>";

print "<td width='8%' style='$buttonStyle; white-space:nowrap;'>";
print "<button type='button' class='center-btn' style='$buttonStyle; width:100%; justify-content:center;' onclick=\"showLangModalKontoprint()\">" . $print_icon . creditorGeneralLedgerEscape($printLabel) . "</button>";
print "</td>";

print "<td width='7%' style='$buttonStyle; white-space:nowrap;'>";
print "<button type='button' class='center-btn' style='$buttonStyle; width:100%; justify-content:center;' onclick=\"window.open('" . creditorGeneralLedgerEscape($emailUrl) . "','kontomail','" . creditorGeneralLedgerEscape($jsvars) . "')\">" . $mail_icon . findtekst('52|E-mail', $sprog_id) . "</button>";
print "</td>";
print "</tr></tbody></table></td></tr>\n";
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

.creditor-ledger-topline-cell {
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
	/* padding: 4px; */

	/* justify-content: center; */
}

.topLine a,
.topLine a:hover,
.topLine a:focus {
	text-decoration: none !important;
	background: none !important;
}
</style>
