<?php
// ---- includes/settings/layout.php --- ver 5.1.0 --- 2026.11.03 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261102 CL/ASR Created: the standard layout of a settings page (owner, 2 Nov 2026: "When I click Logo & baggrunde it keeps a sidebar with
//                 all related items of the same menu. It doesn't on all items - e.g. Formularer ... Can we make this a standard?").
//                 Every page reached from a hub row, and the pages under it (import/export, backup, the form editor, POS menus ...),
//                 shows the left menu of the section pages in the same place: the page's hub group with its sub-groups, the row marked,
//                 Tilbage above the title, the breadcrumb Indstillinger / group / row (/ page). Same markup and classes as
//                 systemdata/settingsSection.php (settings_menu_html(), css/unified-components.css), so it looks the same.
// 20261102 CL/ASR css/unified-components.css linked as ?v=20261102b (the direct Brugere & ansatte heading in the left menu).
// 20261103 CL/ASR QA: the title's whitespace is normalised without the /u modifier on latin-1 installations (it returned null there: a blank heading).

/*
 * HOW TO USE (for the pages that do not have the menu yet)
 *
 *  An old page (its own tables and forms) - one line before its content and one after:
 *
 *      include_once("../includes/settings/layout.php");
 *      settings_layout_open();                 // or settings_layout_open(array('at' => 'formularer', 'actions' => ...))
 *      ... the page's content, unchanged ...
 *      settings_layout_close();
 *
 *    The page's place on the hub is found by itself: the request (a registry url such as diverse.php?sektion=x), the hub page
 *    that is this file (formularkort.php with any query), else the breadcrumb map (includes/stdFunc/pageRoutes.php): a page
 *    whose parents lead to a section or a hub row's page is a sub-page of it (importer_kontoplan.php -> Import & eksport,
 *    exporter_posmenu.php -> POS-menuer, admin/backup.php -> Sikkerhedskopi, kontokort.php -> Kontoplan). A sub-page that is not
 *    in the map passes 'at' (the section id or registry key it belongs under) and its 'title'.
 *    Remove the page's own Tilbage (settings_back_button() in a .sk div), its page_bar() title and the old title bar: the layout
 *    prints Tilbage + title + lead, and the page's buttons go to 'actions'. Do not link css/unified-components.css yourself.
 *
 *  A module's page that is also a hub row (systemdata/kontoplan.php = Finans > Kontoplan, rental/settings.php = Booking): only when it
 *  was opened from Indstillinger, so it stays exactly as on master from its own module:
 *
 *      $fromSettings = settings_opened_from_settings();   // includes/settings/hub.php: settings=1 in the request
 *      if ($fromSettings) { settings_layout_open(); }
 *      ... the page ...
 *      if ($fromSettings) { settings_layout_close(); }
 *
 *    The hub rows, the settings menu and the moved map add settings=1 to such pages' links by themselves. The page keeps it on
 *    in its own links and forms: settings_marker_query() ('settings=1' or '') and settings_marker_input() (a hidden input or '');
 *    a form that posts back to the same address without it is still recognised (the referring address carried it).
 *
 *  A page built from the kit (class sk, its own head and panels - usersRoles.php): settings_layout_open(array('kit' => true,
 *    'title' => ..., 'lead' => ..., 'actions_html' => <its sk-btn buttons>)) puts its tabs and panels in the content column.
 *    A page that keeps its own grid takes only the menu: settings_layout_menu() inside an element with the class st-page
 *    (and css/unified-components.css linked: settings_layout_assets()).
 *
 *  Options of settings_layout_open():
 *    'at'           section id or registry key of the page's row page ('' = found as above)
 *    'title'        the heading (plain text); default the row's name on a row page, the map's name of a sub-page
 *    'lead'         the line under the heading (plain text); default as the section pages: the hub row's line (on a row page) and
 *                   "Gælder for alle i <firma>."; false = none
 *    'back'         Tilbage's target (relative to the page); default the hub on a row page, the page above a sub-page
 *    'actions'      buttons right of the heading: array of array('label' =>, 'href' =>, 'icon' => 'bx-..', 'title' =>, 'accesskey' =>,
 *                   'target' =>, 'onclick' =>, 'primary' => bool); 'actions_html' = ready-made html instead
 *    'notes'        html between the head and the columns (notices, a moved toast) - wrap each in .st-flash / .st-toast
 *    'menu'         false = no left menu (a popup window; also when the request has popup=1); array = options for settings_menu_html()
 *    'kit'          true for a page built from the kit: the root also carries sk and the kit is linked
 *    'legacy'       default !kit: the content column keeps an old page's font and lets a wide editor scroll inside it
 *    'class'        extra classes on the root (.st-page)
 *    'breadcrumb'   false = the page prints its own
 */

include_once(__DIR__ . '/components.php');                     // st_h(), st_txt(), st_company(), settings_back_button()
include_once(__DIR__ . '/../../systemdata/settingsRegistry.php'); // the hub (includes/settings/hub.php), settings_breadcrumb()
include_once(__DIR__ . '/listView.php');                       // settings_menu_html()
if (!function_exists('page_routes')) {
	include_once(__DIR__ . '/../stdFunc/pageChrome.php');
}

if (!function_exists('settings_layout_open')):

/**
 * The page a hub url (relative to systemdata/: 'kontoplan.php', '../rental/settings.php') or a path from the Saldi root
 * ('/systemdata/posmenuer.php') points at, as 'dir/file.php'.
 */
function settings_layout_url_file(string $url): string
{
	$path = (string) parse_url($url, PHP_URL_PATH);
	if (strpos($path, '/') === 0) {
		return ltrim($path, '/');
	}
	return (strpos($path, '../') === 0) ? substr($path, 3) : 'systemdata/' . $path;
}

/**
 * The hub page (section id or registry key) a path from the Saldi root opens ('/systemdata/settingsSection.php?s=x',
 * '/systemdata/posmenuer.php', '/systemdata/formularkort.php?form_nr=4'), or ''. A file several hub pages share
 * (diverse.php?sektion=...) needs its query; a file only one hub page has is that page whatever its query.
 */
function settings_layout_key_for_path(string $path): string
{
	$file = settings_layout_url_file($path);
	$query = array();
	parse_str((string) parse_url($path, PHP_URL_QUERY), $query);
	if ($file === 'systemdata/settingsSection.php') {
		return (isset($query['s']) && is_string($query['s']) && settings_hub_locate($query['s']) !== null) ? $query['s'] : '';
	}
	$best = '';
	$bestSpec = -1;
	$all = array();
	foreach (settings_hub_index() as $key => $loc) {
		if ((string) $key !== $loc['page']) {
			continue;
		}
		$def = settings_hub_page_def((string) $key);
		if ($def === null || $def['kind'] !== 'entry' || settings_layout_url_file($def['url']) !== $file) {
			continue;
		}
		$all[] = (string) $key;
		$params = array();
		parse_str((string) parse_url($def['url'], PHP_URL_QUERY), $params);
		unset($params[settings_marker_param()]);
		$ok = true;
		foreach ($params as $name => $value) {
			if (!isset($query[$name]) || !is_string($query[$name]) || $query[$name] !== (string) $value) {
				$ok = false;
				break;
			}
		}
		if ($ok && count($params) > $bestSpec) {
			$best = (string) $key;
			$bestSpec = count($params);
		}
	}
	return ($best === '' && count($all) === 1) ? $all[0] : $best;
}

/**
 * A page's entry in the breadcrumb map: its variant whose query the request has ('dir/file.php?x=y'), else its own entry.
 *
 * @return array{0: string, 1: array<string, mixed>}|null
 */
function settings_layout_route(string $file, array $query): ?array
{
	$routes = page_routes();
	foreach ($routes['pages'] as $k => $entry) {
		if (strpos((string) $k, $file . '?') !== 0) {
			continue;
		}
		$want = array();
		parse_str(substr((string) $k, strlen($file) + 1), $want);
		if (page_route_query_matches($want, $query)) {
			return array((string) $k, $entry);
		}
	}
	return isset($routes['pages'][$file]) ? array($file, $routes['pages'][$file]) : null;
}

/**
 * A label of the breadcrumb map ('textId|Dansk', or '|Text' without an id) as plain text in the page's charset.
 */
function settings_layout_route_text(string $label, int $sprogId): string
{
	if (strpos($label, '|') === 0) {
		$text = substr($label, 1);
		return st_charset() === 'UTF-8' ? $text : mb_convert_encoding($text, st_charset(), 'UTF-8');
	}
	return settings_hub_text($label, $sprogId);
}

/**
 * Where the running page sits on the hub. $at: the section id or registry key of its row page (its own, or the one it is
 * a sub-page of); '' = found from the request, the hub page that is this file, then the breadcrumb map's parents.
 * Null when the page is not on the hub.
 *
 * key:   the hub page the menu marks (settings_hub_menu($sprogId, key)) and the breadcrumb runs through
 * sub:   true when the running page is a page under it (an import page, the form editor, Kontokort)
 * label: the running page's name in the breadcrumb map ('' when the map does not know it)
 * trail: the map's pages between the hub page and the running page, outermost first (label, url from the Saldi root)
 *
 * @return array{key: string, sub: bool, label: string, trail: array<int, array{label: string, url: string}>}|null
 */
function settings_layout_locate(string $at = ''): ?array
{
	global $sprog_id;
	$sprogId = (int) $sprog_id;
	$file = page_route_key();
	if ($file === '') {
		$script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
		$file = basename(dirname($script)) . '/' . basename($script);
	}
	$query = array_filter($_GET, 'is_string');
	if (!isset($query['valg']) && isset($_POST['valg']) && is_string($_POST['valg'])) {
		$query['valg'] = $_POST['valg'];
	}
	$here = '/' . $file . ($query ? '?' . http_build_query($query) : '');
	$route = settings_layout_route($file, $query);
	$label = $route ? settings_layout_route_text((string) $route[1]['l'], $sprogId) : '';

	if ($at !== '') {
		$loc = settings_hub_locate($at);
		$def = $loc !== null ? settings_hub_page_def($loc['page']) : null;
		if ($def === null) {
			return null;
		}
		$key = $at;
		$sub = ($def['kind'] === 'section') ? !($file === 'systemdata/settingsSection.php' && isset($query['s']) && $query['s'] === $loc['page'])
			: settings_layout_url_file($def['url']) !== $file;
	} else {
		$key = settings_layout_key_for_path($here);
		$sub = false;
	}
	$trail = array();
	if ($key === '' || $sub) {
		// Up the map's parents to the first page a hub row opens; the pages passed on the way are levels of the trail.
		$parent = ($route && isset($route[1]['p'])) ? (string) $route[1]['p'] : '';
		$seen = array($route ? $route[0] : $file => true);
		$found = '';
		while ($parent !== '' && count($seen) < 8) {
			$found = settings_layout_key_for_path($parent);
			if ($found !== '') {
				break;
			}
			$parentQuery = array();
			parse_str((string) parse_url($parent, PHP_URL_QUERY), $parentQuery);
			$p = settings_layout_route(settings_layout_url_file($parent), $parentQuery);
			if (!$p || isset($seen[$p[0]])) {
				break;
			}
			$seen[$p[0]] = true;
			array_unshift($trail, array('label' => settings_layout_route_text((string) $p[1]['l'], $sprogId), 'url' => page_route_visited($parent)));
			$parent = isset($p[1]['p']) ? (string) $p[1]['p'] : '';
		}
		if ($key === '') {
			if ($found === '') {
				return null;
			}
			$key = $found;
			$sub = true;
		} elseif ($found === '' || ($found !== $key && settings_hub_locate($found)['page'] !== settings_hub_locate($key)['page'])) {
			$trail = array(); // the map does not lead to the page given: no levels in between
		}
	}
	return array('key' => $key, 'sub' => $sub, 'label' => $sub ? $label : '', 'trail' => $trail);
}

/**
 * A link from the running page to a hub url (relative to systemdata/) or to a path from the Saldi root.
 */
function settings_layout_href(string $url): string
{
	if (strpos($url, '/') === 0) {
		return '..' . $url; // every settings page sits one folder below the root
	}
	if (strpos($url, '../') === 0) {
		return $url;
	}
	return settings_menu_base() . $url;
}

/**
 * The name of a hub page: its row's name when it is the row's own page, else its own (a row's second page, POS-menuer).
 */
function settings_layout_page_name(string $key, int $sprogId): string
{
	$loc = settings_hub_locate($key);
	$row = $loc !== null ? settings_hub_row($loc) : null;
	if ($row === null) {
		return '';
	}
	if ((string) $row['maps'][0] === $loc['page']) {
		return settings_hub_text($row['label'], $sprogId);
	}
	$def = settings_hub_page_def($loc['page']);
	return $def !== null ? settings_hub_page_label($def, $sprogId) : settings_hub_text($row['label'], $sprogId);
}

/**
 * The stylesheets of the layout, once per page: the fonts, the kit (pages built from it) and the settings components.
 */
function settings_layout_assets(bool $kit = false): string
{
	static $done = array();
	$out = '';
	$rel = str_repeat('../', max(1, substr_count(page_route_key(), '/')));
	foreach (array_filter(array(
		'fonts' => $rel . 'css/saldi-fonts.css?v=2',
		'kit' => $kit ? $rel . 'css/saldi-kit.css?v=20261102' : '',
		'st' => $rel . 'css/unified-components.css?v=20261102b',
		'bx' => 'https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css',
	)) as $id => $href) {
		if (!isset($done[$id])) {
			$done[$id] = true;
			$out .= "<link rel='stylesheet' href='" . st_h($href) . "'>";
		}
	}
	return $out === '' ? '' : $out . "\n";
}

/**
 * Only the left menu of the running page (for a page that builds its own columns inside .st-page): the hub group with the
 * page's row marked, as on the section pages. $opts: 'at' as settings_layout_open(), and settings_menu_html()'s options.
 */
function settings_layout_menu(array $opts = array()): string
{
	global $sprog_id;
	$where = settings_layout_locate(isset($opts['at']) ? (string) $opts['at'] : '');
	unset($opts['at']);
	if (!isset($opts['label']) && $where !== null) {
		$opts['label'] = ($where['sub'] && $where['label'] !== '') ? $where['label'] : settings_layout_page_name($where['key'], (int) $sprog_id);
	}
	return settings_menu_html($where !== null ? settings_hub_menu((int) $sprog_id, $where['key']) : array(), (int) $sprog_id, $opts);
}

/**
 * Print the standard settings layout up to the content column: stylesheets, breadcrumb, Tilbage + title + lead (+ the
 * page's buttons), the hub group's menu with the page's row marked, and the opening of the content column. The page then
 * prints its own content and calls settings_layout_close(). Options: see the head of this file.
 *
 * @param array<string, mixed> $opts
 */
function settings_layout_open(array $opts = array()): void
{
	global $sprog_id, $buttonColor, $buttonTxtColor;
	$sprogId = (int) $sprog_id;
	$charset = st_charset();
	$plain = function ($s) use ($charset): string {
		return trim((string) preg_replace($charset === 'UTF-8' ? '/\s+/u' : '/\s+/', ' ', html_entity_decode(strip_tags((string) $s), ENT_QUOTES | ENT_HTML5, $charset)));
	};
	$kit = !empty($opts['kit']);
	$where = settings_layout_locate(isset($opts['at']) ? (string) $opts['at'] : '');

	if (isset($opts['title']) && (string) $opts['title'] !== '') {
		$title = $plain($opts['title']);
	} elseif ($where !== null && $where['sub'] && $where['label'] !== '') {
		$title = $where['label'];
	} elseif ($where !== null) {
		$title = settings_layout_page_name($where['key'], $sprogId);
	} else {
		$title = $plain(isset($GLOBALS['title']) ? $GLOBALS['title'] : '');
	}
	if (array_key_exists('lead', $opts)) {
		$lead = $opts['lead'] === false ? '' : $plain($opts['lead']);
	} else {
		// As the section pages: what the page is for (a row page: its hub row's line), then who it applies to.
		$hint = ($where !== null && !$where['sub']) ? settings_hub_hint_for($where['key'], $sprogId) : '';
		$lead = ($hint !== '' ? rtrim($hint, '.') . '. ' : '') . st_txt(5706) . ' ' . st_company() . '.';
	}

	// Tilbage: a row page goes to the hub (settings_back_link()); a sub-page to the page above it, a fixed target as well.
	$back = isset($opts['back']) ? (string) $opts['back'] : '';
	if (!isset($opts['back']) && $where !== null && $where['sub']) {
		if ($where['trail']) {
			$back = settings_layout_href((string) $where['trail'][count($where['trail']) - 1]['url']);
		} else {
			$def = settings_hub_page_def(settings_hub_locate($where['key'])['page']);
			$back = $def !== null ? settings_layout_href($def['url']) : '';
		}
	}

	$menuOpts = (isset($opts['menu']) && is_array($opts['menu'])) ? $opts['menu'] : array();
	$showMenu = !(isset($opts['menu']) && $opts['menu'] === false) && empty($_GET['popup']);

	print settings_layout_assets($kit);
	if (!isset($opts['breadcrumb']) || $opts['breadcrumb'] !== false) {
		if ($where !== null) {
			$crumbs = settings_breadcrumb('', $where['sub'] ? '' : $title, $sprogId, $where['key']);
			if ($where['sub']) {
				// The hub page is a level of its own (a link), then the map's pages in between, then this page.
				$def = settings_hub_page_def(settings_hub_locate($where['key'])['page']);
				$crumbs[count($crumbs) - 1]['url'] = $def !== null ? settings_shell_path($def['url']) : '';
				foreach ($where['trail'] as $level) {
					$crumbs[] = array('label' => $level['label'], 'url' => $level['url']);
				}
				$crumbs[] = array('label' => $title, 'url' => '');
			}
		} else {
			$crumbs = array(array('label' => settings_hub_text('122|Indstillinger', $sprogId), 'url' => '/systemdata/settings.php'), array('label' => $title, 'url' => ''));
		}
		print settings_breadcrumb_script($crumbs, $charset) . "\n";
	}
	print '<script>document.title = ' . json_encode($charset === 'UTF-8' ? $title : mb_convert_encoding($title, 'UTF-8', $charset)) . ";</script>\n";

	$actions = isset($opts['actions_html']) ? (string) $opts['actions_html'] : '';
	foreach ((isset($opts['actions']) && is_array($opts['actions'])) ? $opts['actions'] : array() as $a) {
		$actions .= '<a class="st-btn' . (!empty($a['primary']) ? ' st-btn-primary' : '') . '" href="' . st_h(isset($a['href']) ? $a['href'] : '#') . '"'
			. (!empty($a['title']) ? ' title="' . st_h($plain($a['title'])) . '"' : '')
			. (!empty($a['accesskey']) ? ' accesskey="' . st_h($a['accesskey']) . '"' : '')
			. (!empty($a['target']) ? ' target="' . st_h($a['target']) . '" rel="noopener"' : '')
			. (!empty($a['onclick']) ? ' onclick="' . st_h($a['onclick']) . '"' : '') . '>'
			. (!empty($a['icon']) ? '<i class="bx ' . st_h($a['icon']) . '" aria-hidden="true"></i>' : '')
			. '<span>' . st_h($plain(isset($a['label']) ? $a['label'] : '')) . '</span></a>';
	}

	$root = 'st-page st-frame' . ($kit ? ' sk' : '') . (!empty($opts['class']) ? ' ' . (string) $opts['class'] : '');
	print '<div class="' . st_h($root) . '" style="' . st_h(st_accent_style((string) $buttonColor, (string) $buttonTxtColor)) . '">' . "\n";
	print '<section class="st-phead"><div>' . settings_back_button($back) . '<h1>' . st_h($title) . '</h1>'
		. ($lead !== '' ? '<p class="st-lead">' . st_h($lead) . '</p>' : '') . '</div>'
		. ($actions !== '' ? '<div class="st-phead-acts">' . $actions . '</div>' : '') . "</section>\n";
	if (!empty($opts['notes'])) {
		print '<div class="st-notes">' . (string) $opts['notes'] . "</div>\n";
	}
	$main = '<div class="st-frame-main' . ((isset($opts['legacy']) ? !empty($opts['legacy']) : !$kit) ? ' st-legacy' : '') . '">' . "\n";
	if ($showMenu) {
		$menu = $where !== null ? settings_hub_menu($sprogId, $where['key']) : array();
		print '<div class="st-layout st-layout-list">' . settings_menu_html($menu, $sprogId, $menuOpts + array('label' => $title)) . "\n" . $main;
	} else {
		print $main;
	}
	$GLOBALS['settings_layout_stack'][] = $showMenu ? 3 : 2;
}

/**
 * Close what settings_layout_open() opened (the content column, the columns and the page), after the page's content.
 */
function settings_layout_close(): void
{
	if (empty($GLOBALS['settings_layout_stack'])) {
		return;
	}
	print "\n" . str_repeat('</div>', (int) array_pop($GLOBALS['settings_layout_stack'])) . "\n";
}

endif;
