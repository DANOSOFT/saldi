<?php
// --- finans/kassekladde_includes/redesign.php --- ver 5.0.0 --- 2026-10-21 ---
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
// 20261021 ASR Kassekladde redesign: the toolbar's right-hand cluster (Luk, bank, Hjælp, Ny, gear) and the gear panel,
//                  and the small da/en text helper (findtekst with free text is uncached and expensive).

if (!function_exists('kk_t')):

/** Danish or English UI text in the page's charset (the source is UTF-8; latin1 tenants get ISO-8859-15). */
function kk_t($da, $en)
{
	global $sprog_id, $charset;
	$s = (isset($sprog_id) && $sprog_id == 2) ? $en : $da;
	if (isset($charset) && strtoupper((string) $charset) !== 'UTF-8') {
		$r = @iconv('UTF-8', 'ISO-8859-15//TRANSLIT', $s);
		if ($r !== false) $s = $r;
	}
	return $s;
}

/** Escape for HTML attributes and text in the page's charset. */
function kk_h($s)
{
	global $charset;
	return htmlspecialchars((string) $s, ENT_QUOTES, isset($charset) ? $charset : 'UTF-8');
}

/** The few line icons the page uses (stroke follows currentColor). */
function kk_icon($name)
{
	$p = array(
		'back'  => '<path d="M15 6l-6 6 6 6"/>',
		'plus'  => '<path d="M12 5v14M5 12h14"/>',
		'help'  => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.7M12 17h.01"/>',
		'print' => '<path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="7"/>',
		'gear'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
		'sort'  => '<path d="M7 4v16M3 8l4-4 4 4M17 20V4M13 16l4 4 4-4"/>',
	);
	return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
		. (isset($p[$name]) ? $p[$name] : '') . '</svg>';
}

/**
 * The toolbar's right-hand cluster: print, Ny kassekladde, bank status, Hjælp (tour) and the gear.
 * Luk is a hidden link: the shell's breadcrumb closes the journal and pageChrome.js leaves through its luk.php
 * address (the lock is released, SST-755); outside the shell it is shown. Alt+L and N keep their accesskeys.
 *
 * @param string $closeHref luk.php address back to the journal list
 * @param string $newHref   luk.php address that opens a new journal
 * @param bool   $editable  the journal can be edited (gear)
 * @param string $bankHtml  output of bank_integration/includes/auth_check_icon.php (included by the caller, it needs globals)
 */
function kk_toolbar_cluster($closeHref, $newHref, $editable, $unsavedTxt, $bankHtml = '')
{
	global $sprog_id, $charset;
	$js = function ($u) use ($unsavedTxt) {
		return "javascript:confirmClose('" . kk_h($u) . "','" . kk_h($unsavedTxt) . "')";
	};
	$out = "<span class='kk-tb-right'>";
	$out .= "<a class='kk-ghost kk-close' href=\"" . $js($closeHref) . "\" accesskey='L' title='" . kk_h(kk_t('Luk (Alt+L)', 'Close (Alt+L)')) . "'>"
		. kk_icon('back') . "<span>" . kk_h(findtekst('30|Tilbage', $sprog_id)) . "</span></a>";
	$out .= "<button type='button' class='kk-ghost kk-icon kk-print' onclick='window.print()' title='" . kk_h(kk_t('Udskriv', 'Print')) . "' aria-label='" . kk_h(kk_t('Udskriv', 'Print')) . "'>" . kk_icon('print') . "</button>";
	if (trim($bankHtml) !== '') $out .= "<span class='kk-bank'>" . trim($bankHtml) . "</span>";
	$out .= "<button type='button' class='kk-ghost kk-icon' id='tutorial-help' title='" . kk_h(findtekst('2564|Hjælp', $sprog_id)) . "' aria-label='" . kk_h(findtekst('2564|Hjælp', $sprog_id)) . "'>" . kk_icon('help') . "</button>";
	$out .= "<a class='kk-newbtn' id='create-new' href=\"" . $js($newHref) . "\" accesskey='N' title='" . kk_h(kk_t('Opret ny kassekladde (Alt+N)', 'Create a new cash journal (Alt+N)')) . "'>"
		. kk_icon('plus') . kk_h(findtekst('39|Ny', $sprog_id)) . "<span class='kk-long'>&nbsp;" . kk_h(kk_t('kassekladde', 'cash journal')) . "</span></a>";
	if ($editable) {
		$out .= "<button type='button' id='kkVisToggle' class='kk-gear' title='" . kk_h(findtekst('3380|Tilpas visning', $sprog_id)) . "' aria-label='" . kk_h(findtekst('3380|Tilpas visning', $sprog_id)) . "' aria-haspopup='true' aria-expanded='false'>" . kk_icon('gear') . "</button>";
	}
	return $out . "</span>";
}

/**
 * The gear panel "Tilpas kassekladden" (spec §7): sections Kolonner and Opslagspanel. Further sections (SD-721/722
 * "Bilag fra puljen", SD-726 "Tastatur") use the same .kk-sec markup when they arrive.
 *
 * @param array $toggle  key => label, shown by default (box3 holds the hidden keys)
 * @param array $hidden  hidden keys of $toggle
 * @param array $opt     key => label, hidden by default (box3 holds '<key>_vis')
 * @param array $optOn   keys of $opt the user turned on
 * @param array $forced  keys of $opt shown because the journal holds values
 * @param array $listed  keys (of both) that exist on this journal and are listed
 * @param array $panelHidden hidden lookup-panel sections
 */
function kk_gear_panel($toggle, $hidden, $opt, $optOn, $forced, $listed, $panelHidden)
{
	global $sprog_id;
	$notes = array('forf' => kk_t('Udfyldes automatisk – kan rettes', 'Filled in automatically – can be changed'));
	$sw = function ($key, $label, $checked, $kind, $note = '', $disabled = false) {
		return "<label class='kk-sw kkVisRow'><span class='kk-sw-l'>" . kk_h($label)
			. ($note !== '' ? "<small>" . kk_h($note) . "</small>" : '') . "</span>"
			. "<input type='checkbox' class='kk-col-toggle' role='switch' data-col='" . kk_h($key) . "' data-kind='$kind'"
			. ($checked ? ' checked' : '') . ($disabled ? ' disabled' : '') . "><i aria-hidden='true'></i></label>";
	};
	$out = "<div id='kkVisPanel' class='kk-panel' role='dialog' aria-label='" . kk_h(kk_t('Tilpas kassekladden', 'Customise the cash journal')) . "'>";
	$out .= "<div class='kk-ph'>" . kk_h(kk_t('Tilpas kassekladden', 'Customise the cash journal')) . "</div>";
	$out .= "<div class='kk-pi'>" . kk_h(kk_t('Gælder kun for dig.', 'Applies to you only.')) . "</div>";
	$out .= "<div class='kk-sec'><h4>" . kk_h(kk_t('Kolonner', 'Columns')) . "</h4>";
	foreach ($toggle as $key => $label) {
		if (!in_array($key, $listed, true)) continue;
		$out .= $sw($key, $label, !in_array($key, $hidden, true), 'hide', isset($notes[$key]) ? $notes[$key] : '');
	}
	foreach ($opt as $key => $label) {
		if (!in_array($key, $listed, true)) continue;
		$isForced = in_array($key, $forced, true);
		$out .= $sw($key, $label, $isForced || in_array($key, $optOn, true), 'opt',
			$isForced ? kk_t('Vises, fordi kladden har værdier', 'Shown because the journal has values') : '', $isForced);
	}
	$out .= "</div><div class='kk-sec'><h4>" . kk_h(kk_t('Opslagspanel', 'Lookup panel')) . "</h4>";
	$out .= $sw('ac_forslag', findtekst('5142|Kontoforslag (sidste 5)', $sprog_id), !in_array('ac_forslag', $panelHidden, true), 'hide');
	$out .= $sw('ac_opslag', findtekst('5143|Konto-opslag', $sprog_id), !in_array('ac_opslag', $panelHidden, true), 'hide');
	$out .= "</div><div class='kk-pf'><button type='button' id='kkVisShowAll'>" . kk_h(kk_t('Vis alle kolonner', 'Show all columns')) . "</button></div>";
	return $out . "</div>";
}

endif;
