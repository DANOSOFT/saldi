<?php
// --- systemdata/top.php --- ver 4.1.1 --- 2025-04-14 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2025 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20181102 PHR Oprydning, udefinerede variabler.
// 20210223 Loe Replaced the text values on the table data with dynamic data with findtekst().
// 20220103 PHR Checks for error in text id 778 - can be removed in 2023
// 20250414 LOE Barcode button added for app
// 20260710 SZ Added Settings search box to sidebar (settingsSearch.php/.js/.css)
// 20260928 Sawaneh Phase 4: menu column generated from settingsRegistry.php (current group, access-filtered),
//                  Back goes to settings.php, unsaved-changes guard for the settings pages.
// 20261002 Sawaneh The page tells the shell its breadcrumb trail (settings redesign §8.0); the tab is kept after a save
//                  (the old forms post without ?valg).
// 20260928 Sawaneh Unsaved-changes guard uses the native beforeunload dialog (settings redesign spec §8.1).

$small=NULL;
if (!isset($css)) $css=NULL;
if (!isset($rightoptxt)) $rightoptxt=NULL;

if ($css) $font="";
else $small="<small>";

if ($popup) $returside="../includes/luk.php";
else $returside="settings.php";

if (findtekst(778,$sprog_id) == 'Regnskabså') {
	$qtxt = "update tekster set tekst = '' where tekst_id = '778'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}

include("../includes/topline_settings.php");

include_once("../includes/stdFunc/pageBar.php");
page_bar(array('title' => findtekst(613, $sprog_id), 'back' => $returside));
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #tabel 1 
print "<tr><td colspan=\"2\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>"; # tabel 1.1

print "</tr></tbody></table></td></tr>
  <tr><td id='sidebar-base'  width=\"125px\" align=\"left\" valign=\"top\">";
print "<table align=\"left\" border=\"0\" cellspacing=\"2\" cellpadding=\"2\"width=\"170px\"><tbody>"; #tabel 1.1.2

$searchPlaceholder = ($sprog_id == 2) ? 'Search settings...' : (($sprog_id == 3) ? 'Søk i innstillinger...' : 'Søg i indstillinger...');
$noResultsText = ($sprog_id == 2) ? 'No results' : (($sprog_id == 3) ? 'Ingen resultater' : 'Ingen resultater');
$matchHintText = ($sprog_id == 2) ? 'Found via' : (($sprog_id == 3) ? 'Funnet via' : 'Fundet via');
print "<script>
if (typeof window.saldiTranslations === 'undefined') {
	window.saldiLanguage = " . (int)$sprog_id . ";
	window.saldiTranslations = { settingsNoResults: " . json_encode($noResultsText) . ", settingsMatchHint: " . json_encode($matchHintText) . " };
}
</script>";
print "<link rel=\"stylesheet\" href=\"../css/settingsSearch.css\">";
print "<script src=\"../javascript/settingsSearch.js\" defer></script>";
print "<tr><td width=\"170px\"><div class=\"settings-search-wrapper\"><input type=\"text\" class=\"settings-search-input\" autocomplete=\"off\" placeholder=\"" . htmlspecialchars($searchPlaceholder) . "\"></div></td></tr>";

print "<tr><td width=\"170px\"><br></td></tr>";

// 20260928 Phase 4: the column lists the pages of the group the current page belongs to
// 20261009 Sawaneh The page on the new page head (page_bar) instead of the blue bar; the print layout untouched.
// (settingsRegistry.php), filtered by the user's access; without a match, the groups.
include_once(__DIR__ . "/settingsRegistry.php");
$settingsGroups = settings_accessible_groups();
// The old pages post without their ?valg (syssetup.php); the posted value says which tab is shown.
$currentRequest = $_GET;
if (!isset($currentRequest['valg']) && isset($_POST['valg']) && is_string($_POST['valg'])) {
	$currentRequest['valg'] = $_POST['valg'];
}
$currentEntries = settings_entries_for_request(basename($_SERVER['PHP_SELF']), $currentRequest);
$currentGroup = '';
foreach ($currentEntries as $currentEntry) {
	if (isset($settingsGroups[$currentEntry['group']])) {
		$currentGroup = $currentEntry['group'];
		break;
	}
}
$currentKeys = array_map(function ($e) { return $e['key']; }, $currentEntries);
if ($currentGroup !== '') {
	foreach ($currentEntries as $currentEntry) {
		if ($currentEntry['group'] === $currentGroup) {
			print settings_breadcrumb_script(settings_breadcrumb($currentGroup, settings_entry_label($currentEntry, (int) $sprog_id), (int) $sprog_id), (isset($db_encode) && $db_encode !== 'UTF8') ? 'ISO-8859-1' : 'UTF-8');
			break;
		}
	}
}
$activeStyle = "$buttonStyle; filter:brightness(1.25); font-weight:bold";
if ($currentGroup !== '') {
	print "<tr><td style='padding:6px 2px 2px;font-weight:bold;font-size:12px;text-transform:uppercase;letter-spacing:.4px'>".findtekst($settingsGroups[$currentGroup]['def']['label'], $sprog_id)."</td></tr>";
	foreach ($settingsGroups[$currentGroup]['entries'] as $menuEntry) {
		$style = in_array($menuEntry['key'], $currentKeys, true) ? $activeStyle : $buttonStyle;
		print "<tr><td><a href=\"".htmlspecialchars($menuEntry['url'], ENT_QUOTES)."\"><button style='$style; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".settings_entry_label($menuEntry, $sprog_id)."</button></a></td></tr>\n";
	}
} else {
	foreach ($settingsGroups as $menuGroup) {
		print "<tr><td><a href=\"".htmlspecialchars($menuGroup['entries'][0]['url'], ENT_QUOTES)."\"><button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst($menuGroup['def']['label'], $sprog_id)."</button></a></td></tr>\n";
	}
}
print "<tr><td><br></td></tr>";
print "<tr><td><a href=\"settings.php\"><button style='$buttonStyle; width:100%; opacity:.85' onMouseOver=\"this.style.cursor='pointer'\">".findtekst('5666|Alle indstillinger', $sprog_id)."</button></a></td></tr>";

// Unsaved changes (settings redesign spec §8.1): the browser's native beforeunload dialog when
// leaving a settings page with edited, unsubmitted forms. window.docChange is the shared flag the
// shell (index/main.php) also checks and clears after its own confirm.
print "<script>
(function () {
	function mark(e) { if (e.target.form) { window.docChange = true; } }
	document.addEventListener('input', mark, true);
	document.addEventListener('change', mark, true);
	document.addEventListener('submit', function () { window.docChange = false; }, true);
	var nativeSubmit = HTMLFormElement.prototype.submit;
	HTMLFormElement.prototype.submit = function () { window.docChange = false; return nativeSubmit.apply(this, arguments); };
	window.addEventListener('beforeunload', function (e) {
		if (!window.docChange) { return; }
		e.preventDefault();
		e.returnValue = '';
	});
})();
</script>";
print "</tbody></table>";# <-tabel 1.1.2
print "</td><td align=\"center\" valign=\"top\" height=\"99%\"><br>";

?>
