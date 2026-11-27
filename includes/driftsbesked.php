<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/driftsbesked.php --- ver 5.0.0 --- 2026-11-27 ---
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
// 20261127 LH Created: an operations notice (driftsbesked) shown as a popup when the server has a
//             driftsbesked.txt next to the installations (e.g. /var/www/html/driftsbesked.txt).
//             The file's text is the message; no file or an empty file shows nothing. Support turns it
//             on, edits it and turns it off on the server without a deploy.

if (!function_exists('driftsbeskedText')) {
	/**
	 * The operations notice for this server, or '' when there is none.
	 *
	 * @param string $file Path of the notice file; default is driftsbesked.txt in the folder above the installation.
	 * @return string The notice text, trimmed and capped at 2000 characters.
	 */
	function driftsbeskedText($file = '') {
		if ($file === '') $file = dirname(__DIR__, 2) . '/driftsbesked.txt';
		if (!is_file($file) || !is_readable($file)) return '';
		$text = @file_get_contents($file, false, null, 0, 4000);
		if ($text === false) return '';
		$text = trim($text);
		if (function_exists('mb_substr')) $text = mb_substr($text, 0, 2000, 'UTF-8');
		else $text = substr($text, 0, 2000);
		return $text;
	}
}

if (!function_exists('driftsbeskedHtml')) {
	/**
	 * The popup for an operations notice, or '' when there is none.
	 *
	 * The first line is the heading, the rest is the body. "OK" closes it for the rest of the
	 * browser session; a changed text shows again. A temporary incident notice, removed when the
	 * incident is over, so it has no per-user "Vis ikke igen" (see doc/ai/convention_popup_off_switch.md).
	 *
	 * @param string $text The notice text from driftsbeskedText().
	 * @return string HTML, CSS and script of the popup.
	 */
	function driftsbeskedHtml($text) {
		if ($text === '') return '';
		$lines = preg_split('/\r\n|\r|\n/', $text, 2);
		$title = htmlspecialchars(trim($lines[0]), ENT_QUOTES, 'UTF-8');
		$body = isset($lines[1]) ? nl2br(htmlspecialchars(trim($lines[1]), ENT_QUOTES, 'UTF-8')) : '';
		$key = 'saldi_driftsbesked_' . substr(md5($text), 0, 12);

		$html = "<div id='driftsbesked' role='alertdialog' aria-modal='true' aria-labelledby='driftsbeskedTitle' style='display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,0.35);align-items:center;justify-content:center'>";
		$html.= "<div style='max-width:520px;margin:16px;padding:20px 24px;border:1px solid #f0c36d;border-left:6px solid #e0a800;border-radius:8px;background:#fff8e1;color:#5c4400;font-family:Helvetica,Arial,sans-serif;font-size:15px;line-height:1.5;box-shadow:0 8px 24px rgba(0,0,0,0.2)'>";
		$html.= "<div id='driftsbeskedTitle' style='font-weight:bold;font-size:17px;margin-bottom:8px'>$title</div>";
		if ($body !== '') $html.= "<div>$body</div>";
		$html.= "<div style='text-align:right;margin-top:16px'><button type='button' id='driftsbeskedOk' style='padding:8px 22px;font-size:15px;cursor:pointer;border:1px solid #8a6700;border-radius:6px;background:#ffd24d;color:#3d2e00;font-weight:bold'>OK</button></div>";
		$html.= "</div></div>";
		$html.= "<script>(function(){var k=" . json_encode($key) . ",d=document.getElementById('driftsbesked');";
		$html.= "try{if(sessionStorage.getItem(k)==='1')return;}catch(e){}";
		$html.= "d.style.display='flex';document.getElementById('driftsbeskedOk').onclick=function(){d.style.display='none';try{sessionStorage.setItem(k,'1');}catch(e){}};})();</script>";
		return $html;
	}
}
?>
