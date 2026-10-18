<?php
// ---- includes/formEditorState.php --- lap 5.0.0 --- 2026.10.05 ---
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
// 20261005 Sawaneh Settings redesign G6.1 (audit FE3): the visual form editor's print-language lock and drafts are kept
//                  in the settings table (var_grp 'formeditor'), so they are in backups and the ledger, not in files
//                  under logolib/. A file left from before is moved into the table the first time it is read.
// 20261006 Sawaneh Onboarding part 1: fe_logo_store() is the logo upload shared by the form editor and the welcome guide;
//                  the background helpers and fe_composite_logo() moved here from formeditor.php so the guide can put the
//                  logo on the invoice (an optional file for a form without a background).

if (!function_exists('fe_state_get')):

function fe_state_get(string $name): string
{
	$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'formeditor' and var_name = '" . db_escape_string($name) . "' and coalesce(user_id, 0) = 0 order by id desc limit 1", __FILE__ . " linje " . __LINE__));
	return $r ? (string) $r['var_value'] : '';
}

function fe_state_set(string $name, string $value): void
{
	$n = db_escape_string($name);
	$v = db_escape_string($value);
	if (db_fetch_array(db_select("select id from settings where var_grp = 'formeditor' and var_name = '$n' and coalesce(user_id, 0) = 0", __FILE__ . " linje " . __LINE__))) {
		db_modify("update settings set var_value = '$v' where var_grp = 'formeditor' and var_name = '$n' and coalesce(user_id, 0) = 0", __FILE__ . " linje " . __LINE__);
	} else {
		db_modify("insert into settings (var_grp, var_name, var_value, var_description, user_id) values ('formeditor', '$n', '$v', 'Visual form editor', 0)", __FILE__ . " linje " . __LINE__);
	}
}

function fe_state_delete(string $name): void
{
	db_modify("delete from settings where var_grp = 'formeditor' and var_name = '" . db_escape_string($name) . "'", __FILE__ . " linje " . __LINE__);
}

/**
 * The value stored under $name, taking over the old file $legacyFile when the table has none yet.
 */
function fe_state_get_migrating(string $name, string $legacyFile): string
{
	$v = fe_state_get($name);
	if ($v === '' && $legacyFile !== '' && @is_file($legacyFile)) {
		$v = (string) @file_get_contents($legacyFile);
		if ($v !== '') {
			fe_state_set($name, $v);
		}
		@unlink($legacyFile);
	}
	return $v;
}

/**
 * The language a form is locked to print in ('' when not locked).
 */
function fe_printlang_get($dbId, int $form): string
{
	$json = fe_state_get_migrating('printlang_' . $form, "../logolib/$dbId/fe_printlang_$form.json");
	$pl = $json !== '' ? json_decode($json, true) : null;
	return (is_array($pl) && !empty($pl['sprog'])) ? (string) $pl['sprog'] : '';
}

function fe_draft_name(int $form, string $sprog): string
{
	return 'draft_' . $form . '_' . preg_replace('/[^A-Za-z0-9_]/', '_', $sprog);
}

/**
 * Store an uploaded logo as logolib/<db_id>/fe_logo.png: png or jpg up to 5 MB, re-encoded to drop embedded scripts and
 * metadata (NR-8) and capped at 1500 px. It is placed on printed forms when a form is saved in the form editor.
 *
 * @return array{ok: bool, error: string, w: int, h: int, url: string}
 */
function fe_logo_store($dbId, string $tmp, int $size): array
{
	$fail = function (string $e) {
		return array('ok' => false, 'error' => $e, 'w' => 0, 'h' => 0, 'url' => '');
	};
	if ($tmp === '' || !is_uploaded_file($tmp)) return $fail('nofile');
	if ($size > 5 * 1024 * 1024) return $fail('toobig');
	$info = @getimagesize($tmp);
	if (!$info || !in_array($info['mime'], array('image/png', 'image/jpeg', 'image/jpg'), true)) return $fail('badtype');
	$w = (int) $info[0]; $h = (int) $info[1];
	if ($w < 1 || $h < 1) return $fail('badimg');

	$dir = "../logolib/" . (int) $dbId;
	if (!is_dir($dir)) @mkdir($dir, 0775, true);
	$png = "$dir/fe_logo.png";
	$new = "$dir/fe_logo_new.png";
	@unlink($new);
	$ok = false;
	if (function_exists('shell_exec')) {
		@shell_exec("convert " . escapeshellarg($tmp) . "[0] -strip -background none -resize '1500x1500>' " . escapeshellarg($new) . " 2>/dev/null");
		$ok = file_exists($new);
	}
	if (!$ok) $ok = @move_uploaded_file($tmp, $new);
	if (!$ok || !@rename($new, $png)) {
		@unlink($new);
		return $fail('store');
	}

	// Never write logolib/logo_<db_id>.eps: the print engine stamps that file on every form without a background PDF.
	@unlink("../logolib/logo_" . (int) $dbId . ".eps");
	$ni = @getimagesize($png);
	if ($ni) { $w = (int) $ni[0]; $h = (int) $ni[1]; }
	return array('ok' => true, 'error' => '', 'w' => $w, 'h' => $h, 'url' => "../logolib/" . (int) $dbId . "/fe_logo.png?t=" . time());
}

endif;

// ---------------------------------------------------------------------------
//  Background helpers (shared by the view and the logo compositor)
// ---------------------------------------------------------------------------
if (!function_exists('fe_bg_candidates')):
function fe_bg_candidates($db_id, $form_nr, $sprog) {
	$sp   = ($sprog != 'Dansk') ? $sprog . '_' : '';
	$slug = array(1=>'tilbud', 4=>'faktura');
	$c = array();
	if (isset($slug[$form_nr])) {
		$c[] = "../logolib/$db_id/{$sp}{$slug[$form_nr]}_bg.pdf";
		$c[] = "../logolib/$db_id/{$slug[$form_nr]}_bg.pdf";
	}
	$c[] = "../logolib/$db_id/{$sp}bg.pdf";
	$c[] = "../logolib/$db_id/bg.pdf";
	return $c;
}
function fe_active_bg($db_id, $form_nr, $sprog) {
	foreach (fe_bg_candidates($db_id, $form_nr, $sprog) as $f) if (file_exists($f)) return $f;
	return null;
}
// Stamp the placed logo onto the print background at Save & activate. Keeps a
// clean "base" copy so re-saves never double-stamp; the editor shows the base.
// Non-fatal: any tool failure just leaves the background untouched.
function fe_composite_logo($db_id, $form_nr, $sprog, $defaultOut = 'bg.pdf') {
	if (!function_exists('shell_exec')) return;
	$dir  = "../logolib/$db_id";
	$logo = "$dir/fe_logo.png";
	if (!file_exists($logo)) return;                       // no logo -> nothing to do
	$sp = db_escape_string($sprog);
	$r = db_fetch_array(db_select("select xa,ya,xb,yb from formularer where formular=$form_nr and art=1 and beskrivelse='LOGO' and sprog='$sp'", __FILE__ . " linje " . __LINE__));
	if (!$r) return;
	$xa=(float)$r['xa']; $ya=(float)$r['ya']; $w=(float)$r['xb']; $h=(float)$r['yb'];
	if ($w <= 0 || $h <= 0) return;                        // logo not sized -> skip

	$base   = "$dir/fe_logobase.pdf";
	$active = fe_active_bg($db_id, $form_nr, $sprog);
	$out    = $active ? $active : "$dir/" . basename($defaultOut);
	$metaF  = "$dir/fe_logo_meta.json";
	$meta   = file_exists($metaF) ? json_decode(@file_get_contents($metaF), true) : array();
	$activeMd5 = ($active && file_exists($active)) ? md5_file($active) : '';

	if (!file_exists($base)) {
		if ($active && file_exists($active)) @copy($active, $base);
		else @shell_exec("convert -size 1654x2339 xc:white -units PixelsPerInch -density 200 " . escapeshellarg($base) . " 2>/dev/null");
	} elseif ($activeMd5 !== '' && isset($meta['out_md5']) && $activeMd5 !== $meta['out_md5']) {
		@copy($active, $base);   
	}
	if (!file_exists($base)) return;

	$dpi = 200;
	$A4W = 1654; $A4H = 2339;   // A4 @200dpi (210x297mm)
	$basePng = "$dir/fe_basetmp-1.png";
	@shell_exec("pdftoppm -png -r $dpi -f 1 -l 1 " . escapeshellarg($base) . " " . escapeshellarg("$dir/fe_basetmp") . " 2>/dev/null");
	if (!file_exists($basePng)) return;

	@shell_exec("convert " . escapeshellarg($basePng) . " -resize " . $A4W . "x" . $A4H . "! " . escapeshellarg($basePng) . " 2>/dev/null");

	$xpx = (int) round($xa/25.4*$dpi);
	$ypx = (int) round((297-$ya)/25.4*$dpi);
	$wpx = max(1, (int) round($w/25.4*$dpi));
	$hpx = max(1, (int) round($h/25.4*$dpi));
	$logoRs  = "$dir/fe_logors.png";
	$compPng = "$dir/fe_comptmp.png";
	@shell_exec("convert " . escapeshellarg($logo) . " -resize " . escapeshellarg($wpx . 'x' . $hpx . '!') . " " . escapeshellarg($logoRs) . " 2>/dev/null");
	@shell_exec("convert " . escapeshellarg($basePng) . " " . escapeshellarg($logoRs) . " -gravity NorthWest -geometry +$xpx+$ypx -composite " . escapeshellarg($compPng) . " 2>/dev/null");
	if (file_exists($compPng)) {
		@shell_exec("convert " . escapeshellarg($compPng) . " -units PixelsPerInch -density $dpi " . escapeshellarg($out) . " 2>/dev/null");
		@file_put_contents($metaF, json_encode(array('active'=>$out, 'out_md5'=> file_exists($out) ? md5_file($out) : '')));
		// bust the editor bg preview cache so the base re-renders next load
		@array_map('unlink', glob("$dir/*_feprev.png") ?: array());
	}
	@unlink($basePng); @unlink($logoRs); @unlink($compPng);
}
endif;
