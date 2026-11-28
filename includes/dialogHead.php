<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- includes/dialogHead.php --- ver 5.1.1 --- 2026.11.19 ---
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
// 20261114 ASR Created: the head tags of Saldi's own message box (javascript/saldi-dialog.js) for includes/online.php and the shell (index/main.php).
//              The box's texts and the questions this user chose not to see again (settings var_grp 'dialogs', user_id) go in window.SALDI_DIALOG_CFG.
// 20261114 CL/ASR The tip on Vis ikke igen says where the question is turned on again (Personlige indstillinger, Skjulte spørgsmål).
// 20261116 CL/ASR The box's script is linked as saldi-dialog.js?v=3, so a browser loads the changed file (a question holds a click the browser makes under it) instead of its cached copy.
// 20261119 CL/ASR The box's script is linked as saldi-dialog.js?v=4, so a browser loads the changed file (a form OK sends by its button's click asks prompt() again) instead of its cached copy.

if (!function_exists('saldi_dialog_head')):

/**
 * The <script> tags that put Saldi's message box in place of alert() and confirm() on the page.
 *
 * @param string $javaPath the page's path to the javascript folder (includes/online.php $javaPath)
 * @param string $prefUrl  the page's path to index/dialogPref.php
 */
function saldi_dialog_head(string $javaPath, string $prefUrl): string
{
	global $bruger_id, $sprog_id;
	include_once(__DIR__ . '/std_func.php');   // findtekst(); every function there is guarded, so a later include on the page is harmless
	$lang = isset($sprog_id) ? (int) $sprog_id : 1;
	$u8 = function ($s): string {
		$s = (string) $s;
		return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
	};
	$hidden = array();
	if (isset($bruger_id) && (int) $bruger_id > 0 && function_exists('db_select')) {
		$q = db_select("select var_name from settings where var_grp = 'dialogs' and user_id = " . (int) $bruger_id, __FILE__ . " linje " . __LINE__);
		while ($q && ($r = db_fetch_array($q))) $hidden[] = $u8($r['var_name']);
	}
	if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	$cfg = array(
		't' => array(
			'ok'       => $u8(findtekst('dialog.ok|OK', $lang)),
			'cancel'   => $u8(findtekst('common.cancel|Annullér', $lang)),
			'dontShow' => $u8(findtekst('dialog.dont_show_again|Vis ikke igen', $lang)),
			'dontShowTip' => $u8(findtekst('dialog.dont_show_again_tip|Du kan få spørgsmålet igen under Personlige indstillinger', $lang)),
		),
		'hidden'  => $hidden,
		'csrf'    => isset($_SESSION['csrf_token']) ? (string) $_SESSION['csrf_token'] : '',
		'prefUrl' => $prefUrl,
	);
	// json_encode escapes every non-ASCII letter, so the line is right in an ISO-8859-1 page as well
	return "<script>window.SALDI_DIALOG_CFG = " . json_encode($cfg) . ";</script>\n"
		. "<script src=\"" . htmlspecialchars($javaPath, ENT_QUOTES) . "/saldi-dialog.js?v=4\"></script>\n";
}

endif;
