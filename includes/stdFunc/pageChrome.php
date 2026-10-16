<?php
// ---- includes/stdFunc/pageChrome.php --- lap 5.0.0 --- 2026.10.10 ---
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
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20261005 Sawaneh Topbar addendum 2026-10-05 §4: the one breadcrumb mechanism for every page in the shell.
//                  page_help() registers the page's tour, shortcuts and guide for the Assist menu (§7);
//                  page_breadcrumb() prints a script that posts saldi:breadcrumb to the shell (index/main.php),
//                  answers the shell's request after each load, and handles saldi:navigate - through
//                  window.saldiNavigate(href) when the page defines its own close logic, otherwise respecting
//                  docChange. A "came from" chip (§3.1) is added from the navigation stack when the previous page
//                  is not one of the page's own levels.
// 20261006 Sawaneh Breadcrumb on every page (Adam 2026-10-06): online.php prints page_auto_breadcrumb() for each page from
//                  the central map in pageRoutes.php (folder module + page title when a page is not in it); a page's own
//                  page_breadcrumb() replaces it. The shell is always answered with the latest message, and a click
//                  leaves through the page's old luk.php link when it has one, so record locks are still released.
//                  Step 2: in a module listed under 'migrated' in the map, the shell hides the page's old Luk/Tilbage
//                  (a link or plain button with accesskey L; never a submit button, and not a link marked
//                  data-keep-in-shell) - outside the shell the page is unchanged.
// 20261008 Sawaneh page_title(): the page's heading ("Kundeordre 1234", "Kassekladde 2877") becomes the last breadcrumb
//                  level and the label the next page's came-from chip shows (addendum §5).
// 20261008 Sawaneh The breadcrumb handlers moved to javascript/pageChrome.js; the head carries only window.saldiPageChrome
//                  (and the shell-nav style), so a page that redirects after online.php stays under the output buffer.
// 20261008 Sawaneh The came-from chip keeps the whole path of a page in a subfolder (lager/lister/vareliste.php gave a 404).
// 20261009 Sawaneh page_record(): the record as the last breadcrumb level on cards (decision 22 of 2026-10-09).
// 20261010 Sawaneh systemdata/labels.php prints the settings breadcrumb itself, like settingsSection.php.

if (!function_exists('page_breadcrumb')):

/**
 * Tour, shortcut list and guide of the current page for the Assist menu (§7), e.g.
 * page_help(['tour' => '#tutorial-help', 'shortcuts' => '../doc/ledgerGuide.pdf', 'guide' => 'guides/finans/kassekladde']).
 */
function page_help(array $items): void
{
	$GLOBALS['page_help_items'] = array_intersect_key($items, array('tour' => 1, 'shortcuts' => 1, 'guide' => 1));
}

/**
 * Print the page's breadcrumb for the shell. $levels: [['label' => 'Finans'], ['label' => 'Kassekladder',
 * 'href' => '../finans/kladdeliste.php'], ['label' => 'Kassekladde 2877']]; the company name is added by the shell.
 * An href is relative to the page or starts with '/' for a path from the Saldi root. $back overrides the
 * came-from chip: ['label' => 'Ordre 1234', 'href' => '...'], or false for none.
 *
 * @param array<int, array{label: string, href?: string}> $levels
 * @param array{label: string, href: string}|false|null    $back
 */
function page_breadcrumb(array $levels, ?string $tag = null, $back = null, string $charset = 'UTF-8'): string
{
	$items = array();
	foreach ($levels as $level) {
		if (!isset($level['label']) || (string) $level['label'] === '') {
			continue;
		}
		$item = array('label' => page_chrome_text((string) $level['label'], $charset));
		if (!empty($level['href'])) {
			$item['href'] = (string) $level['href'];
		}
		$items[] = $item;
	}
	if ($back === null) {
		$back = page_came_from($items);
	}
	page_remember($items);
	$GLOBALS['page_chrome_items'] = $items;
	$GLOBALS['page_chrome_charset'] = $charset;
	$msg = array('type' => 'saldi:breadcrumb', 'items' => $items);
	if ($tag !== null && $tag !== '') {
		$msg['tag'] = page_chrome_text($tag, $charset);
	}
	if (is_array($back) && !empty($back['href'])) {
		$msg['back'] = array('label' => page_chrome_text((string) $back['label'], $charset), 'href' => (string) $back['href']);
	}
	if (!empty($GLOBALS['page_help_items'])) {
		$msg['help'] = $GLOBALS['page_help_items'];
	}
	$json = json_encode($msg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
	// Migrated module (step 2): the breadcrumb replaces the old Luk/Tilbage in the shell; it stays in the page for its luk.php link.
	$hide = !empty($GLOBALS['page_chrome_hide_close']) ? "if (window.parent && window.parent !== window && !document.getElementById('saldi-shell-nav')) {\n"
		. "\t\tdocument.documentElement.classList.add('saldi-shell-nav');\n"
		. "\t\tvar st = document.createElement('style'); st.id = 'saldi-shell-nav';\n"
		. "\t\tst.textContent = 'html.saldi-shell-nav a[accesskey=\"l\" i]:not([data-keep-in-shell]), html.saldi-shell-nav td:has(> a[accesskey=\"l\" i]:not([data-keep-in-shell])), html.saldi-shell-nav input[type=\"button\" i][accesskey=\"l\" i] { display: none !important; }';\n"
		. "\t\t(document.head || document.documentElement).appendChild(st);\n"
		. "\t}\n" : '';
	// The handlers live in javascript/pageChrome.js so the head only carries the message (a page that redirects
	// after online.php must stay under PHP's output buffer).
	$depth = substr_count(page_route_key(), '/');
	$src = str_repeat('../', $depth) . 'javascript/pageChrome.js?v=20261008';
	return ($hide !== '' ? '<script>' . "\n" . $hide . '</script>' . "\n" : '')
		. '<script>window.saldiPageChrome = ' . $json . ';</script>' . "\n"
		. '<script src="' . htmlspecialchars($src, ENT_QUOTES) . '"></script>';
}

/**
 * The page's title as the last breadcrumb level (addendum §5: "Kassekladde 2877"), printed where the page composes
 * its heading, after the breadcrumb itself. Updates the shell and the label remembered for came-from chips.
 */
function page_title(string $text, ?string $tag = null): string
{
	$items = isset($GLOBALS['page_chrome_items']) ? $GLOBALS['page_chrome_items'] : array();
	$charset = isset($GLOBALS['page_chrome_charset']) ? (string) $GLOBALS['page_chrome_charset'] : 'UTF-8';
	$label = trim(preg_replace('/\s+/', ' ', page_chrome_text(strip_tags($text), $charset)));
	if (!$items || $label === '') {
		return '';
	}
	$items[count($items) - 1]['label'] = $label;
	$GLOBALS['page_chrome_items'] = $items;
	page_remember($items);
	$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
	$tagJs = ($tag !== null && $tag !== '') ? ' c.tag = ' . json_encode(page_chrome_text($tag, $charset), $flags) . ';' : '';
	return '<script>(function () { var c = window.saldiPageChrome; if (!c || !c.items || !c.items.length) { return; }'
		. ' c.items[c.items.length - 1].label = ' . json_encode($label, $flags) . ';' . $tagJs
		. ' if (window.parent && window.parent !== window) { window.parent.postMessage(c, window.location.origin); } })();</script>';
}

/**
 * The record as one more breadcrumb level after the page ("Debitorer / Debitorkort / 1042 Jensen VVS", decision 22 of
 * 2026-10-09): printed where the card knows its record, after the breadcrumb itself. The page level stays as plain text.
 */
function page_record(string $text): string
{
	$items = isset($GLOBALS['page_chrome_items']) ? $GLOBALS['page_chrome_items'] : array();
	$charset = isset($GLOBALS['page_chrome_charset']) ? (string) $GLOBALS['page_chrome_charset'] : 'UTF-8';
	$label = trim(preg_replace('/\s+/', ' ', page_chrome_text(html_entity_decode(strip_tags($text), ENT_QUOTES, $charset), $charset)));
	if (!$items || $label === '') {
		return '';
	}
	$items[] = array('label' => $label);
	$GLOBALS['page_chrome_items'] = $items;
	page_remember($items);
	$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
	return '<script>(function () { var c = window.saldiPageChrome; if (!c || !c.items || !c.items.length) { return; }'
		. ' c.items.push({ label: ' . json_encode($label, $flags) . ' });'
		. ' if (window.parent && window.parent !== window) { window.parent.postMessage(c, window.location.origin); } })();</script>';
}

/**
 * A label as UTF-8 plain text (the shell inserts it with textContent).
 */
function page_chrome_text(string $label, string $charset): string
{
	if ($charset !== 'UTF-8') {
		$label = mb_convert_encoding($label, 'UTF-8', $charset);
	}
	return html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * The page a link points at, as "dir/file.php" from the Saldi root (for comparing with the navigation stack).
 */
function page_chrome_path(string $href): string
{
	$path = (string) parse_url($href, PHP_URL_PATH);
	if ($path === '') {
		return '';
	}
	if ($path[0] !== '/') {
		$here = dirname((string) parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH));
		$path = $here . '/' . $path;
	}
	$parts = array();
	foreach (explode('/', $path) as $p) {
		if ($p === '' || $p === '.') {
			continue;
		}
		if ($p === '..') {
			array_pop($parts);
			continue;
		}
		$parts[] = $p;
	}
	return implode('/', array_slice($parts, -2));
}

/**
 * Remember the current page's label and parent for later "came from" chips (session, last 30 pages).
 */
function page_remember(array $items): void
{
	if (!$items || session_status() !== PHP_SESSION_ACTIVE || !isset($_SERVER['REQUEST_URI'])) {
		return;
	}
	$parent = '';
	for ($i = count($items) - 2; $i >= 0; $i--) {
		if (!empty($items[$i]['href'])) {
			$parent = page_chrome_path($items[$i]['href']);
			break;
		}
	}
	$key = page_chrome_path((string) $_SERVER['REQUEST_URI']);
	$pages = isset($_SESSION['page_chrome']) && is_array($_SESSION['page_chrome']) ? $_SESSION['page_chrome'] : array();
	unset($pages[$key]);
	$pages[$key] = array('label' => $items[count($items) - 1]['label'], 'parent' => $parent);
	$_SESSION['page_chrome'] = array_slice($pages, -30, null, true);
}

/**
 * The label a page was last shown with (its last breadcrumb level), or "Tilbage".
 */
function page_label_for(string $href): string
{
	$path = page_chrome_path($href);
	if (isset($_SESSION['page_chrome'][$path]['label'])) {
		return (string) $_SESSION['page_chrome'][$path]['label'];
	}
	return function_exists('findtekst') ? findtekst('30|Tilbage', isset($GLOBALS['sprog_id']) ? (int) $GLOBALS['sprog_id'] : 1) : 'Tilbage';
}

/**
 * The came-from chip (§3.1): the previous page in the navigation stack, unless it is one of this page's own
 * levels, the page itself or a sibling with the same parent - breadcrumbs show where a page belongs, the chip
 * where the user came from.
 *
 * @return array{label: string, href: string}|false
 */
function page_came_from(array $items)
{
	if (!function_exists('nav_back_url') || !function_exists('_nav_read')) {
		return false;
	}
	$stack = _nav_read();
	if (count($stack) < 2) {
		return false;
	}
	$prev = (string) $stack[count($stack) - 2];
	$prevPath = page_chrome_path($prev);
	$here = page_chrome_path(isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '');
	if ($prevPath === '' || $prevPath === $here || $prevPath === 'index/dashboard.php' || $prevPath === 'index/menu.php') {
		return false;
	}
	$parent = '';
	foreach ($items as $i => $item) {
		if (!empty($item['href'])) {
			if (page_chrome_path($item['href']) === $prevPath) {
				return false;
			}
			if ($i < count($items) - 1) {
				$parent = page_chrome_path($item['href']);
			}
		}
	}
	$known = isset($_SESSION['page_chrome'][$prevPath]) ? $_SESSION['page_chrome'][$prevPath] : null;
	if ($known && $parent !== '' && $known['parent'] === $parent) {
		return false;
	}
	$label = page_label_for($prev);
	$href = $prev;
	if (strpos($href, '/') === 0) {
		// The stack holds request URIs (/<install>/dir/page.php?...); the chip needs the path from the Saldi root,
		// so only the install prefix (what precedes this page's own key in the request path) is taken off.
		$key = page_route_key();
		$reqPath = (string) parse_url(isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH);
		$prefix = ($key !== '' && substr($reqPath, -strlen('/' . $key)) === '/' . $key) ? substr($reqPath, 0, -strlen('/' . $key)) : '';
		if ($prefix !== '' && strpos($href, $prefix . '/') === 0) {
			$href = substr($href, strlen($prefix));
		}
	}
	return array('label' => $label, 'href' => $href);
}

/**
 * The central page map (pageRoutes.php): 'modules' key => [l, href], 'pages' 'dir/file.php' => [m, l, p].
 *
 * @return array{modules: array<string, array<string, string>>, pages: array<string, array<string, mixed>>}
 */
function page_routes(): array
{
	static $routes = null;
	if ($routes === null) {
		$f = __DIR__ . '/pageRoutes.php';
		$routes = is_file($f) ? (array) include $f : array();
		$routes += array('modules' => array(), 'pages' => array(), 'dirs' => array());
	}
	return $routes;
}

/**
 * The running page as 'dir/file.php' from the Saldi root.
 */
function page_route_key(): string
{
	$root = realpath(__DIR__ . '/../..');
	$file = isset($_SERVER['SCRIPT_FILENAME']) ? realpath((string) $_SERVER['SCRIPT_FILENAME']) : false;
	if ($root === false || $file === false || strpos($file, $root . DIRECTORY_SEPARATOR) !== 0) {
		return '';
	}
	return str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($root) + 1));
}

/**
 * True when a request's query has every parameter of a map variant ('' = present with any value).
 */
function page_route_query_matches(array $want, array $have): bool
{
	foreach ($want as $k => $v) {
		if (!isset($have[$k]) || !is_string($have[$k]) || ($v !== '' && $have[$k] !== $v)) {
			return false;
		}
	}
	return true;
}

/**
 * The link for a parent level: the user's own visit from the navigation stack when there is one (it keeps the ids
 * and list filters), else the map's path.
 */
function page_route_visited(string $parent): string
{
	$path = ltrim((string) parse_url($parent, PHP_URL_PATH), '/');
	$want = array();
	parse_str((string) parse_url($parent, PHP_URL_QUERY), $want);
	$stack = function_exists('_nav_read') ? _nav_read() : array();
	for ($i = count($stack) - 1; $i >= 0; $i--) {
		$url = (string) $stack[$i];
		if (page_chrome_path($url) !== implode('/', array_slice(explode('/', $path), -2))) {
			continue;
		}
		$have = array();
		parse_str((string) parse_url($url, PHP_URL_QUERY), $have);
		$ok = page_route_query_matches($want, $have);
		if ($ok && !$want) {
			// The plain page, not one of its variants.
			foreach (array_keys(page_routes()['pages']) as $key) {
				if (strpos($key, $path . '?') === 0) {
					$variant = array();
					parse_str(substr($key, strlen($path) + 1), $variant);
					if (page_route_query_matches($variant, $have)) {
						$ok = false;
						break;
					}
				}
			}
		}
		if ($ok) {
			$q = (string) parse_url($url, PHP_URL_QUERY);
			return '/' . $path . ($q !== '' ? '?' . $q : '');
		}
	}
	return $parent;
}

/**
 * The breadcrumb every page gets from online.php (Adam 2026-10-06: breadcrumbs on every page, replacing the old back
 * buttons): module, the page's parents and the page from the central map; a page not in the map shows its folder's
 * module and its title. Pages that call page_breadcrumb() themselves replace it.
 */
function page_auto_breadcrumb(string $title, int $sprogId, string $charset): string
{
	$key = page_route_key();
	$routes = page_routes();
	if ($key === '' || in_array($key, array('index/main.php', 'index/dashboard.php', 'index/index.php', 'index/login.php', 'index/menu.php', 'index/onboarding.php', 'systemdata/settingsSection.php', 'systemdata/settings.php', 'systemdata/labels.php'), true)) {
		return '';
	}
	$tx = function (string $l) use ($sprogId) {
		return trim((strpos($l, '|') === 0) ? substr($l, 1) : findtekst($l, $sprogId));
	};
	// A page's own entry, or the variant whose query ('?funktion=vis_sag', '?vare') matches the request's.
	$find = function (string $path) use ($routes) {
		$k = ltrim((string) parse_url($path, PHP_URL_PATH), '/');
		$q = array();
		parse_str((string) parse_url($path, PHP_URL_QUERY), $q);
		foreach ($routes['pages'] as $key => $entry) {
			if (strpos($key, $k . '?') !== 0) {
				continue;
			}
			$want = array();
			parse_str(substr($key, strlen($k) + 1), $want);
			if (page_route_query_matches($want, $q)) {
				return array($key, $entry);
			}
		}
		return isset($routes['pages'][$k]) ? array($k, $routes['pages'][$k]) : null;
	};
	$here = $find('/' . $key . '?' . http_build_query(array_filter($_GET, 'is_string')));
	$levels = array();
	if ($here) {
		$levels[] = array('label' => $tx((string) $here[1]['l']));
		$parent = isset($here[1]['p']) ? (string) $here[1]['p'] : '';
		$seen = array($here[0] => true);
		while ($parent !== '' && count($levels) < 6) {
			$p = $find($parent);
			if (!$p || isset($seen[$p[0]])) {
				break;
			}
			$seen[$p[0]] = true;
			$href = page_route_visited($parent);
			array_unshift($levels, array('label' => $tx((string) $p[1]['l'])) + ($href !== '' ? array('href' => $href) : array()));
			$parent = isset($p[1]['p']) ? (string) $p[1]['p'] : '';
		}
		$module = (string) $here[1]['m'];
	} else {
		$label = trim(preg_replace('/\s+/', ' ', strip_tags($title)));
		if ($label === '') {
			return '';
		}
		$levels[] = array('label' => function_exists('mb_strtoupper') ? mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1) : ucfirst($label));
		$dir = strpos($key, '/') !== false ? substr($key, 0, strpos($key, '/')) : '';
		$module = isset($routes['dirs'][$dir]) ? (string) $routes['dirs'][$dir] : '';
	}
	if ($module !== '' && isset($routes['modules'][$module])) {
		$m = $routes['modules'][$module];
		$landing = isset($m['href']) ? (string) $m['href'] : '';
		$first = isset($levels[0]['href']) ? ltrim((string) parse_url($levels[0]['href'], PHP_URL_PATH), '/') : $key;
		$isLanding = $landing !== '' && ltrim((string) parse_url($landing, PHP_URL_PATH), '/') === $first;
		array_unshift($levels, array('label' => $tx((string) $m['l'])) + (($landing !== '' && !$isLanding) ? array('href' => $landing) : array()));
	}
	$migrated = isset($routes['migrated']) ? $routes['migrated'] : array();
	$GLOBALS['page_chrome_hide_close'] = $module !== '' && in_array($module, isset($migrated['modules']) ? (array) $migrated['modules'] : array(), true)
		&& !in_array(strtok($here ? $here[0] : $key, '?'), isset($migrated['keep']) ? (array) $migrated['keep'] : array(), true);
	return page_breadcrumb($levels, null, null, $charset);
}

endif;
