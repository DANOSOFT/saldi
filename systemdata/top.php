<?php
// --- systemdata/top.php --- ver 5.1.0 --- 2026-11-02 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// 20181102 PHR Oprydning, udefinerede variabler.
// 20210223 Loe Replaced the text values on the table data with dynamic data with findtekst().
// 20220103 PHR Checks for error in text id 778 - can be removed in 2023
// 20250414 LOE Barcode button added for app
// 20260710 SZ Added Settings search box to sidebar (settingsSearch.php/.js/.css)
// 20260928 SS Phase 4: menu column generated from settingsRegistry.php (current group, access-filtered), Back goes to settings.php, unsaved-changes guard for the settings pages.
// 20261002 SS The page tells the shell its breadcrumb trail (settings redesign §8.0); the tab is kept after a save (the old forms post without ?valg).
// 20260928 SS Unsaved-changes guard uses the native beforeunload dialog (settings redesign spec §8.1).
// 20261016 SS The frame draws the new settings layout (title, lead, the group's sections as the left menu, content beside) so the seven old pages inside it look like the generated sections; their own markup is untouched.
// 20261101 CL/ASR New design (prototype D): the frame takes the new look of css/unified-components.css (white page, 4 px corners, the section menu as a panel).
//                 The old page inside keeps the font it inherited before, and its buttons get the 4 px corners of the frame.
//                 The search field's stylesheet (css/settingsSearch.css, restyled the same day) carries a version, so browsers fetch the new look.
// 20261101 CL/ASR Settings hub "C - Fold ud" (owner, 1 Nov 2026): the menu is the one of the section pages (settings_menu_html(), includes/settings/listView.php): the page's hub group as its sub-groups, the current row marked, no zebra.
//                 A Tilbage button above the title (settings_back_button()); the breadcrumb follows the hub as well.
// 20261101 CL/ASR Review: Tilbage carries Alt+L, as on the other settings pages.
// 20261102 CL/ASR Tilbage goes to the hub, a fixed target, so it never loops; settings_back_button() adds Alt+L itself.
// 20261102 CL/ASR Review: a page without a hub row of its own that the breadcrumb map hangs under a section (tekster.php under Sprog & lokalisering) gets that section's group as its left menu, with the row marked, and its own name as the title (it showed "Indstillinger" and the four groups).
// 20261102 CL/ASR Review: such a page (tekster.php, regnskabskort.php) is a sub-page of its section, so Tilbage goes to that section, as on every other settings sub-page (includes/settings/layout.php: Kontokort, the import pages, Sikkerhedskopi); it went to the hub.
// 20261102 CL/ASR No search field above the menu any more (search lives on the hub), so the menu starts where it does on every other settings page. unified-components.css v=20261102.
// 20261102 CL/ASR unified-components.css v=20261102b (the direct Brugere & ansatte heading in the left menu).

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
include_once(__DIR__ . "/../includes/settings/listView.php"); // settings_menu_html()
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
$hubMenu = settings_hub_menu((int) $sprog_id);
// A page the hub reaches through a section (tekster.php from Sprog & lokalisering) has no row of its own: the left menu marks that
// section's row and the title is the page's own name. Its breadcrumb stays the one of includes/stdFunc/pageRoutes.php (online.php).
$routeParent = false;
$routeBack = ''; // Tilbage of a sub-page: its section; '' = the hub
if (!$hubMenu && $currentGroup === '' && function_exists('page_routes') && function_exists('page_route_key')) {
	$routePages = page_routes()['pages'];
	$route = isset($routePages[page_route_key()]) ? $routePages[page_route_key()] : null;
	if ($route && preg_match('#^/systemdata/settingsSection\.php\?s=([A-Za-z0-9_.]+)$#', isset($route['p']) ? (string) $route['p'] : '', $routeMatch)) {
		$hubMenu = settings_hub_menu((int) $sprog_id, $routeMatch[1]);
		if ($hubMenu) {
			$routeParent = true;
			$routeBack = 'settingsSection.php?s=' . rawurlencode($routeMatch[1]);
			$currentLabel = (strpos((string) $route['l'], '|') === 0) ? substr((string) $route['l'], 1) : findtekst((string) $route['l'], $sprog_id);
		}
	}
}
if (($currentGroup !== '' || $hubMenu) && !$routeParent) {
	print settings_breadcrumb_script(settings_breadcrumb($currentGroup, $currentLabel, (int) $sprog_id), (isset($db_encode) && $db_encode !== 'UTF8') ? 'ISO-8859-1' : 'UTF-8');
}
$noResultsText = ($sprog_id == 2) ? 'No results' : (($sprog_id == 3) ? 'Ingen resultater' : 'Ingen resultater');
$matchHintText = ($sprog_id == 2) ? 'Found via' : (($sprog_id == 3) ? 'Funnet via' : 'Fundet via');
print "<script>
if (typeof window.saldiTranslations === 'undefined') {
	window.saldiLanguage = " . (int)$sprog_id . ";
	window.saldiTranslations = { settingsNoResults: " . json_encode($noResultsText) . ", settingsMatchHint: " . json_encode($matchHintText) . " };
}
</script>";
print "<link rel=\"stylesheet\" href=\"../css/unified-components.css?v=20261102b\">";
print "<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>";
print "<style>.st-legacy { min-width: 0; font: 14px/1.5 Inter, \"Segoe UI\", system-ui, sans-serif; } .st-legacy table { max-width: 100%; } .st-legacy input[type=submit], .st-legacy input[type=button], .st-legacy button { border-radius: 4px; }</style>";
print "<div class=\"st-page\" style=\"" . st_h(st_accent_style((string) $buttonColor, (string) $buttonTxtColor)) . "\">";
print "<section class=\"st-phead\"><div>" . settings_back_button($routeBack) . "<h1>" . st_h(html_entity_decode($currentLabel, ENT_QUOTES, st_charset())) . "</h1><p class=\"st-lead\">" . st_t(5706) . " " . st_h(st_company()) . ".</p></div></section>";
print "<div class=\"st-layout st-layout-list\">";
print settings_menu_html($hubMenu, (int) $sprog_id, array(
	'label' => html_entity_decode($currentLabel, ENT_QUOTES, st_charset()),
));
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
