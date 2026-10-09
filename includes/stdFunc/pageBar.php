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

if (!function_exists('page_bar')):

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
		print "<link rel='stylesheet' href='$rel/css/saldi-theme.css?v=4'><link rel='stylesheet' href='$rel/css/page.css?v=1'><link rel='stylesheet' href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css'>";
		// The page follows the user's theme: html.saldi-dark while the theme (or the device, for System) is dark.
		print "<script>(function(){var r=document.documentElement;function ap(){var t=r.dataset.theme;r.classList.toggle('saldi-dark',t==='dark'||(t==='system'&&matchMedia('(prefers-color-scheme: dark)').matches));}ap();"
			. "new MutationObserver(ap).observe(r,{attributes:true,attributeFilter:['data-theme']});try{matchMedia('(prefers-color-scheme: dark)').addEventListener('change',ap);}catch(e){}})();</script>";
	}
	$style = "--user-primary:" . $h(!empty($buttonColor) ? $buttonColor : '#114691') . ";--user-primary-text:" . $h(!empty($buttonTxtColor) ? $buttonTxtColor : '#ffffff');
	print "<div class='pg' style='$style'>";
	$title = html_entity_decode(strip_tags(isset($o['title']) ? (string) $o['title'] : ''), ENT_QUOTES, $enc);
	print "<div class='pg-head'><h1>" . $h(trim(preg_replace('/\s+/', ' ', $title))) . "</h1>";
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
	print "</span></div></div>";
}

endif;
