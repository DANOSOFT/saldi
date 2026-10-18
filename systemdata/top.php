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
// 20261016 Sawaneh The frame draws the new settings layout (title, lead, the group's sections as the left menu, content beside)
//                  so the seven old pages inside it look like the generated sections; their own markup is untouched.

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

include_once("../includes/settings/components.php");
include_once(__DIR__ . "/settingsRegistry.php");
$settingsGroups = settings_accessible_groups();
$currentRequest = $_GET;
if (!isset($currentRequest['valg']) && isset($_POST['valg']) && is_string($_POST['valg'])) {
	$currentRequest['valg'] = $_POST['valg'];
}
$currentEntries = settings_entries_for_request(basename($_SERVER['PHP_SELF']), $currentRequest);
$currentGroup = '';
$currentLabel = findtekst(613, $sprog_id);
foreach ($currentEntries as $currentEntry) {
	if (isset($settingsGroups[$currentEntry['group']])) {
		$currentGroup = $currentEntry['group'];
		$currentLabel = settings_entry_label($currentEntry, (int) $sprog_id);
		break;
	}
}
$currentKeys = array_map(function ($e) { return $e['key']; }, $currentEntries);
if ($currentGroup !== '') {
	print settings_breadcrumb_script(settings_breadcrumb($currentGroup, $currentLabel, (int) $sprog_id), (isset($db_encode) && $db_encode !== 'UTF8') ? 'ISO-8859-1' : 'UTF-8');
}
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
print "<link rel=\"stylesheet\" href=\"../css/unified-components.css?v=20261016b\">";
print "<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>";
print "<style>.st-legacy { min-width: 0; } .st-legacy table { max-width: 100%; } .st-legacy input[type=submit], .st-legacy input[type=button], .st-legacy button { border-radius: 8px; }</style>";
print "<div class=\"st-page\" style=\"" . st_h(st_accent_style((string) $buttonColor, (string) $buttonTxtColor)) . "\">";
print "<section class=\"st-phead\"><div><h1>" . st_h(html_entity_decode($currentLabel, ENT_QUOTES, st_charset())) . "</h1><p class=\"st-lead\">" . st_t(5706) . " " . st_h(st_company()) . ".</p></div></section>";
print "<div class=\"st-layout st-layout-list\">";
print "<nav class=\"st-tabs\"><div class=\"settings-search-wrapper\" style=\"margin:0 0 10px\"><input type=\"text\" class=\"settings-search-input\" autocomplete=\"off\" placeholder=\"" . htmlspecialchars($searchPlaceholder) . "\"></div><ul>";
if ($currentGroup !== '') {
	foreach ($settingsGroups[$currentGroup]['entries'] as $menuEntry) {
		$on = in_array($menuEntry['key'], $currentKeys, true);
		print "<li><a href=\"" . htmlspecialchars($menuEntry['url'], ENT_QUOTES) . "\"" . ($on ? ' class="on" aria-current="page"' : '') . "><span>" . settings_entry_label($menuEntry, $sprog_id) . "</span>" . ($on ? '<i class="bx bx-chevron-right" aria-hidden="true"></i>' : '') . "</a></li>";
	}
} else {
	foreach ($settingsGroups as $menuGroup) {
		print "<li><a href=\"" . htmlspecialchars($menuGroup['entries'][0]['url'], ENT_QUOTES) . "\"><span>" . findtekst($menuGroup['def']['label'], $sprog_id) . "</span></a></li>";
	}
}
print "<li class=\"st-tabs-sep\" aria-hidden=\"true\"></li><li><a href=\"settings.php\"><span>" . findtekst('5666|Alle indstillinger', $sprog_id) . "</span></a></li></ul></nav>";
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
print "<div class=\"st-legacy\">";

?>
