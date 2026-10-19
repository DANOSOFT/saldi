<?php
// ---- includes/stdFunc/pageBar.php --- lap 5.0.0 --- 2026.10.08 ---
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
// 20261008 Sawaneh The page head of a migrated page (topbar addendum 2026-10-05 §5, prototype_dashboard_tema v5): the
//                  title in the content, the old bar's tabs as a tab row, its actions on the right in the user's colour,
//                  Ny as the primary button, Hjælp kept as a hidden #tutorial-help for the Assist menu's tour, Tilbage as a
//                  plain link with accesskey L (the shell hides it, standalone pages keep it).
// 20261008 Sawaneh Migrated pages follow the theme: html.saldi-dark (css/saldi-theme.css) while the theme is dark.
// 20261009 Sawaneh Asset links via get_relative(): pages two levels down (lager/lister) got no CSS.
// 20261009 Sawaneh 'extra' html among the actions for the open-post mode select (rapportfunc).
// 20261009 Sawaneh The gear (settings section for the page) is the last element of the page toolbar (decision 22, addendum 2 §5).
// 20261018 Sawaneh Review 2 (PR #803): page_bar_back_ok() - a back link from the request may not run a script.

if (!function_exists('page_bar_back_ok')) {
	/**
	 * Whether a back link may be printed (review 2, PR #803). Several pages pass a returside taken from the request.
	 * Raw control characters are refused (browsers drop tabs/newlines and strip leading control characters, which
	 * would hide a scheme). A plain link may not use a script scheme. A javascript: link is percent-decoded by the
	 * browser before it runs, so it may hold no encoded quote, bracket, backslash, backtick or control character,
	 * and it must be one of the legacy forms: history back, or confirmClose(target[, text]) with single- or
	 * double-quoted (json_encode) arguments, optionally after the product card's opener reload.
	 */
	function page_bar_back_ok(string $back): bool
	{
		// Browsers strip leading/trailing spaces and control characters from a URL; inside it they are refused here.
		$b = preg_replace('/^[\x00-\x20]+|[\x00-\x20]+$/', '', $back);
		if (preg_match('/[\x00-\x1f\x7f]/', $b)) {
			return false;
		}
		$scheme = '/^[a-z][a-z0-9+.\-]*:/i';
		if (!preg_match('/^javascript:/i', $b)) {
			return !preg_match($scheme, $b) || (bool) preg_match('#^https?://#i', $b);
		}
		if (preg_match('/%(?:22|27|3c|3e|5c|60|[01][0-9a-f]|7f)/i', $b)) {
			return false;
		}
		// What runs is the percent-decoded body (an encoded colon would otherwise hide a script target).
		$js = rawurldecode(substr($b, 11));
		if (preg_match('/[\x00-\x1f\x7f]/', $js)) {
			return false;
		}
		if (preg_match('/^(?:window\.)?history\.(?:back\(\)|go\(-1\));?$/', $js)) {
			return true;
		}
		$prefix = 'if (window.opener) { window.opener.location.reload(); }';
		if (strpos($js, $prefix) === 0) {
			$js = substr($js, strlen($prefix));
		}
		$str = "'(?:[^'\\\\]|\\\\.)*'|\"(?:[^\"\\\\]|\\\\.)*\"";
		if (!preg_match('/^confirmClose\((' . $str . ')(?:\s*,\s*(' . $str . '))?\);?$/', $js, $m)) {
			return false;
		}
		if ($m[1][0] === '"') {
			$target = json_decode($m[1]);
			if (!is_string($target)) {
				return false;
			}
		} else {
			// A single-quoted target from the codebase never holds a backslash; one would be a JS escape (\x3a ...).
			$target = substr($m[1], 1, -1);
		}
		if (isset($m[2]) && $m[2] !== '' && $m[2][0] === '"' && !is_string(json_decode($m[2]))) {
			return false;
		}
		if (preg_match('/[\x00-\x1f\x7f"\'<>`\\\\]/', $target)) {
			return false;
		}
		$t = ltrim($target, ' ');
		return !preg_match($scheme, $t) || (bool) preg_match('#^https?://#i', $t);
	}
}

if (!function_exists('page_bar')):

/**
 * The gear for this page (settings spec §8.11, addendum 2 §5): the settings section whose context names the page, with a
 * way back. null when no section governs the page or the user may not read it.
 *
 * @return array{href: string, label: string}|null
 */
function page_bar_gear(string $title): ?array
{
	include_once(__DIR__ . '/pageChrome.php');
	include_once(__DIR__ . '/../../systemdata/settingsRegistry.php');
	if (!function_exists('settings_context_links') || !function_exists('page_route_key')) {
		return null;
	}
	$page = page_route_key();
	if ($page === '') {
		return null;
	}
	$map = settings_context_links((int) $GLOBALS['sprog_id']);
	if (empty($map[$page])) {
		return null;
	}
	$link = $map[$page][0];
	$back = '/' . $page . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
	$href = str_repeat('../', substr_count($page, '/')) . ltrim((string) $link['url'], '/') . '&back=' . rawurlencode($back)
		. ($title !== '' ? '&back_label=' . rawurlencode(mb_substr($title, 0, 60)) : '');
	return array('href' => $href, 'label' => (string) $link['label']);
}

/**
 * Print the page head. $o: 'title', 'tabs' => [['label', 'href', on(bool)], ...], 'actions' => [['label', 'href', icon class,
 * target, 'onclick' => ...], ...], 'primary' => ['label', 'href', 'onclick' => '...'], 'back' => href, 'back_onclick' => js,
 * 'help' => bool (a hidden tour trigger), 'extra' => ready-made html printed among the actions (a select).
 *
 * @param array<string, mixed> $o
 */
function page_bar(array $o): void
{
	global $buttonColor, $buttonTxtColor, $charset;
	static $assets = false;
	$enc = (isset($charset) && $charset === 'UTF-8') ? 'UTF-8' : 'ISO-8859-1';
	$h = function ($s) use ($enc) {
		return htmlspecialchars((string) $s, ENT_QUOTES, $enc);
	};
	if (!$assets) {
		$assets = true;
		// Pages sit one level (debitor/) or two levels (lager/lister/) below the install root.
		$rel = function_exists('get_relative') ? rtrim(get_relative(), '/') : '..';
		print "<link rel='stylesheet' href='$rel/css/saldi-theme.css?v=7'><link rel='stylesheet' href='$rel/css/page.css?v=2'><link rel='stylesheet' href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css'>";
		// The page follows the user's theme: html.saldi-dark while the theme (or the device, for System) is dark.
		print "<script>(function(){var r=document.documentElement;function ap(){var t=r.dataset.theme;r.classList.toggle('saldi-dark',t==='dark'||(t==='system'&&matchMedia('(prefers-color-scheme: dark)').matches));}ap();"
			. "new MutationObserver(ap).observe(r,{attributes:true,attributeFilter:['data-theme']});try{matchMedia('(prefers-color-scheme: dark)').addEventListener('change',ap);}catch(e){}})();</script>";
	}
	$style = "--user-primary:" . $h(!empty($buttonColor) ? $buttonColor : '#114691') . ";--user-primary-text:" . $h(!empty($buttonTxtColor) ? $buttonTxtColor : '#ffffff');
	print "<div class='pg' style='$style'>";
	$title = html_entity_decode(strip_tags(isset($o['title']) ? (string) $o['title'] : ''), ENT_QUOTES, $enc);
	print "<div class='pg-head'><h1>" . $h(trim(preg_replace('/\s+/', ' ', $title))) . "</h1>";
	if (!empty($o['back']) && !page_bar_back_ok((string) $o['back'])) {
		$o['back'] = '';
	}
	if (!empty($o['back'])) {
		print "<a class='pg-back' accesskey='L' href='" . $h($o['back']) . "'" . (!empty($o['back_onclick']) ? " onclick=\"" . $h($o['back_onclick']) . "\"" : '') . ">&larr; " . $h(findtekst('30|Tilbage', $GLOBALS['sprog_id'])) . "</a>";
	}
	print "</div>";
	print "<div class='pg-subbar" . (empty($o['tabs']) ? ' no-tabs' : '') . "'>";
	foreach (isset($o['tabs']) ? $o['tabs'] : array() as $t) {
		if (!empty($t[2])) {
			print "<span class='pg-tab on' aria-current='page'>" . $h($t[0]) . "</span>";
		} else {
			print "<a class='pg-tab' href='" . $h($t[1]) . "'>" . $h($t[0]) . "</a>";
		}
	}
	print "<span class='pg-grow'></span><span class='pg-acts'>";
	if (!empty($o['extra'])) {
		print $o['extra']; // ready-made control (e.g. a mode select), the caller escapes it
	}
	foreach (isset($o['actions']) ? $o['actions'] : array() as $a) {
		print "<a class='pg-act' href='" . $h($a[1]) . "'" . (!empty($a['accesskey']) ? " accesskey='" . $h($a['accesskey']) . "'" : '') . (!empty($a[3]) ? " target='" . $h($a[3]) . "' rel='noopener'" : '') . (!empty($a['onclick']) ? " onclick=\"" . $h($a['onclick']) . " return false;\"" : '') . ">" . (!empty($a[2]) ? "<i class='bx " . $h($a[2]) . "'></i>" : '') . $h($a[0]) . "</a>";
	}
	if (!empty($o['help'])) {
		print "<button type='button' id='tutorial-help' class='pg-hidden' aria-hidden='true' tabindex='-1'></button>";
	}
	if (!empty($o['primary'])) {
		$p = $o['primary'];
		$key = !empty($p['accesskey']) ? " accesskey='" . $h($p['accesskey']) . "'" : '';
		if (!empty($p['onclick'])) {
			print "<a class='pg-btn' href='#'$key onclick=\"" . $h($p['onclick']) . " return false;\">+ " . $h($p[0]) . "</a>";
		} else {
			print "<a class='pg-btn' href='" . $h($p[1]) . "'$key>+ " . $h($p[0]) . "</a>";
		}
	}
	$gear = page_bar_gear($title);
	if ($gear) {
		print "<a class='pg-act pg-gear' href='" . $h($gear['href']) . "' title='" . $h($gear['label']) . "' aria-label='" . $h($gear['label']) . "'><i class='bx bx-cog'></i></a>";
	}
	print "</span></div></div>";
}

endif;
