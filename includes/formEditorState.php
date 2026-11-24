<?php
// ---- includes/formEditorState.php --- ver 5.1.1 --- 2026.11.14 ---
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
// Copyright (c) 2026-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261005 SS Settings redesign G6.1 (audit FE3): the visual form editor's print-language lock and drafts are kept in the settings table (var_grp 'formeditor'), so they are in backups and the ledger, not in files under logolib/.
//             A file left from before is moved into the table the first time it is read.
// 20261006 SS Onboarding part 1: fe_logo_store() is the logo upload shared by the form editor and the welcome guide; the background helpers and fe_composite_logo() moved here from formeditor.php so the guide can put the logo on the invoice (an optional file for a form without a background).
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
// 20261114 ASR fe_composite_logo(): a LOGO row without a size (xb/yb 0, never resized in the editor) gets the size the editor shows, 35 mm wide and the height from the image, instead of being skipped.
// 20261114 CL/ASR The logo has one place for all documents: Gem & aktivér moves the LOGO row of every form of the language to the form saved and stamps bg.pdf and each group background the user has (tilbud_bg, ordrer_bg, faktura_bg, kreditnota_bg).
//                 Each stamped file keeps the user's own ground as fe_base_<name>.pdf with its md5 in fe_logo_meta.json, so no file is stamped twice and a later upload becomes the new ground (fe_logo_background_changed()).
//                 Before, the last form saved put its place on the one shared background, and a second background got the first one's ground.
// 20261114 CL/ASR fe_logo_store() writes a line to the PHP error log when the logo folder is not writable, so the cause shows on the server.
// 20261114 CL/ASR fe_logo_store() keeps the upload as fe_logo_pending.png until Gem & aktivér; the stamp fits the logo inside its box without stretching, as the editor shows it; Fjern logo removes a pending logo too.
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
 * Store an uploaded logo as logolib/<db_id>/fe_logo_pending.png (live as fe_logo.png at Gem & aktivér): png or jpg up to 5 MB, re-encoded to drop embedded scripts and
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
	$png = "$dir/fe_logo_pending.png";   // live (fe_logo.png) at Gem & aktivér, formeditor.php save
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
		error_log("fe_logo_store: could not write $png (is $dir writable for the web server?)");
		return $fail('store');
	}

	// Never write logolib/logo_<db_id>.eps: the print engine stamps that file on every form without a background PDF.
	@unlink("../logolib/logo_" . (int) $dbId . ".eps");
	$ni = @getimagesize($png);
	if ($ni) { $w = (int) $ni[0]; $h = (int) $ni[1]; }
	return array('ok' => true, 'error' => '', 'w' => $w, 'h' => $h, 'url' => "../logolib/" . (int) $dbId . "/fe_logo_pending.png?t=" . time());
}

endif;

// ---------------------------------------------------------------------------
//  The logo on the documents
// ---------------------------------------------------------------------------
// The print engine reads one background per group of documents (includes/formfunk.php formularprint(): 5 kreditnota_bg, >=3
// faktura_bg, 2 ordrer_bg, <=1 tilbud_bg; reminders, statements, inkasso and the pick-list mail read bg.pdf), a lower-case language
// prefix first for a form language other than Dansk, then bg.pdf. Gem & aktivér stamps the logo onto bg.pdf and onto every one of
// those files the user has, at one place for all documents (the LOGO row of every form of the language follows the form saved).
// A user's own file under such a name is kept as fe_base_<name>.pdf and is the ground of the stamp; fe_logo_meta.json holds the
// md5 of each stamp, so a file uploaded later under the name is recognised as the user's and becomes the new ground.
if (!function_exists('fe_logo_prefix')):
function fe_logo_groups(): array {
	return array('tilbud_bg', 'ordrer_bg', 'faktura_bg', 'kreditnota_bg');
}
function fe_logo_group_of(int $form): string {
	if ($form == 5) return 'kreditnota_bg';
	if ($form >= 3) return 'faktura_bg';
	return ($form == 2) ? 'ordrer_bg' : 'tilbud_bg';
}
function fe_logo_prefix(string $sprog): string {
	$l = strtolower(trim($sprog));
	return ($l === '' || $l === 'dansk' || $l === 'danish') ? '' : $l . '_';
}
// A page without a mark (the white ground the first editor made when there was no background).
function fe_pdf_is_blank(string $pdf): bool {
	if (!function_exists('shell_exec') || !file_exists($pdf)) return false;
	$min = @shell_exec("pdftoppm -gray -r 20 -f 1 -l 1 " . escapeshellarg($pdf) . " 2>/dev/null | convert - -format '%[fx:minima]' info: 2>/dev/null");
	return $min !== null && trim((string) $min) !== '' && (float) $min > 0.98;
}
// The stamps as array('v' => 2, 'out' => array(<file> => array('md5', 'own', 'base')), 'langs' => array(<prefix> => array('sprog', 'place'))).
// The first editor's meta (one stamped file 'active' + fe_logobase.pdf) is read as one stamp whose ground is fe_logobase.pdf.
function fe_logo_meta_load(string $dir): array {
	$f = "$dir/fe_logo_meta.json";
	$m = file_exists($f) ? json_decode((string) @file_get_contents($f), true) : null;
	if (!is_array($m)) $m = array();
	if (isset($m['v']) && (int) $m['v'] >= 2) {
		if (!isset($m['out']) || !is_array($m['out'])) $m['out'] = array();
		if (!isset($m['langs']) || !is_array($m['langs'])) $m['langs'] = array();
		return $m;
	}
	$out = array();
	if (isset($m['active'], $m['out_md5']) && $m['out_md5'] !== '') {
		$name = basename((string) $m['active']);
		$own = file_exists("$dir/fe_logobase.pdf") && !fe_pdf_is_blank("$dir/fe_logobase.pdf");
		$out[$name] = array('md5' => (string) $m['out_md5'], 'own' => $own, 'base' => $own ? 'fe_logobase.pdf' : '');
	}
	return array('v' => 2, 'out' => $out, 'langs' => array(), 'v1' => true);
}
function fe_logo_meta_save(string $dir, array $m): void {
	unset($m['v1']);
	$m['v'] = 2;
	@file_put_contents("$dir/fe_logo_meta.json", json_encode($m));
}
// The user's own background under $name (without .pdf), never our stamp: the file itself when it is not a stamp (or was replaced
// since), the kept copy when it is; '' when the user has none.
function fe_logo_user_file(string $dir, string $name, array $m): string {
	$f = "$dir/$name.pdf";
	$rec = isset($m['out']["$name.pdf"]) ? $m['out']["$name.pdf"] : null;
	if (file_exists($f) && (!$rec || md5_file($f) !== $rec['md5'])) return $f;
	if ($rec && !empty($rec['own'])) {
		$b = "$dir/" . (!empty($rec['base']) ? $rec['base'] : "fe_base_$name.pdf");
		if (file_exists($b)) return $b;
	}
	return '';
}
// The user's background of a form as the engine picks it (language + group, group, language bg, bg), seen through the stamps.
function fe_logo_ground($db_id, $form_nr, $sprog): string {
	$dir = "../logolib/$db_id";
	$m = fe_logo_meta_load($dir);
	$p = fe_logo_prefix((string) $sprog);
	$g = fe_logo_group_of((int) $form_nr);
	foreach (array_unique(array($p . $g, $g, $p . 'bg', 'bg')) as $n) {
		$u = fe_logo_user_file($dir, $n, $m);
		if ($u !== '') return $u;
	}
	return '';
}
// Stamp the logo at $place (xa, ya = its top-left from the bottom-left corner, w, h in mm) onto $name.pdf, over $ground or white.
function fe_logo_stamp_file(string $dir, string $name, string $ground, array $place, array &$m): void {
	$out = "$dir/$name.pdf";
	$rec = isset($m['out']["$name.pdf"]) ? $m['out']["$name.pdf"] : null;
	$own = false;
	if (file_exists($out) && (!$rec || md5_file($out) !== $rec['md5'])) {
		// the user's own file (or one uploaded since): keep it as the ground before the stamp goes over it
		@copy($out, "$dir/fe_base_$name.pdf");
		$own = true;
	} elseif ($rec && !empty($rec['own'])) {
		$b = !empty($rec['base']) ? $rec['base'] : "fe_base_$name.pdf";
		if ($b !== "fe_base_$name.pdf" && file_exists("$dir/$b")) @copy("$dir/$b", "$dir/fe_base_$name.pdf");
		$own = file_exists("$dir/fe_base_$name.pdf");
	}
	if ($own) $ground = "$dir/fe_base_$name.pdf";

	$dpi = 200; $A4W = 1654; $A4H = 2339;   // A4 @200dpi (210x297mm)
	$basePng = "$dir/fe_basetmp-1.png";
	@unlink($basePng);
	if ($ground !== '' && file_exists($ground)) {
		@shell_exec("pdftoppm -png -r $dpi -f 1 -l 1 " . escapeshellarg($ground) . " " . escapeshellarg("$dir/fe_basetmp") . " 2>/dev/null");
		if (file_exists($basePng)) @shell_exec("convert " . escapeshellarg($basePng) . " -resize " . $A4W . "x" . $A4H . "! " . escapeshellarg($basePng) . " 2>/dev/null");
	}
	if (!file_exists($basePng)) @shell_exec("convert -size {$A4W}x{$A4H} xc:white " . escapeshellarg($basePng) . " 2>/dev/null");
	if (!file_exists($basePng)) return;

	$xpx = (int) round($place[0] / 25.4 * $dpi);
	$ypx = (int) round((297 - $place[1]) / 25.4 * $dpi);
	$wpx = max(1, (int) round($place[2] / 25.4 * $dpi));
	$hpx = max(1, (int) round($place[3] / 25.4 * $dpi));
	$logoRs  = "$dir/fe_logors.png";
	$compPng = "$dir/fe_comptmp.png";
	// fitted inside the box, centred, never stretched (the editor draws it with object-fit: contain)
	@shell_exec("convert " . escapeshellarg("$dir/fe_logo.png") . " -resize " . escapeshellarg($wpx . 'x' . $hpx) . " -background none -gravity center -extent " . escapeshellarg($wpx . 'x' . $hpx) . " " . escapeshellarg($logoRs) . " 2>/dev/null");
	@shell_exec("convert " . escapeshellarg($basePng) . " " . escapeshellarg($logoRs) . " -gravity NorthWest -geometry +$xpx+$ypx -composite " . escapeshellarg($compPng) . " 2>/dev/null");
	if (file_exists($compPng)) {
		$tmp = "$dir/$name.fe_new.pdf";
		@shell_exec("convert " . escapeshellarg($compPng) . " -units PixelsPerInch -density $dpi " . escapeshellarg($tmp) . " 2>/dev/null");
		if (file_exists($tmp) && filesize($tmp) > 0 && @rename($tmp, $out)) {
			$m['out']["$name.pdf"] = array('md5' => md5_file($out), 'own' => $own, 'base' => '');
		} else {
			@unlink($tmp);
		}
	}
	@unlink($basePng); @unlink($logoRs); @unlink($compPng);
}
// Stamp every file of a language: its bg.pdf, and each group background the user has (or that already carries a stamp).
function fe_logo_stamp_lang(string $dir, string $p, array $place, array &$m): void {
	$names = array($p . 'bg');
	foreach (fe_logo_groups() as $g) {
		if (file_exists("$dir/$p$g.pdf") || isset($m['out']["$p$g.pdf"])) $names[] = $p . $g;
	}
	foreach ($names as $n) {
		$ground = fe_logo_user_file($dir, $n, $m);
		if ($ground === '' && $p !== '' && $n === $p . 'bg') $ground = fe_logo_user_file($dir, 'bg', $m);
		fe_logo_stamp_file($dir, $n, $ground, $place, $m);
	}
}
// After the first editor's meta: its ground copy is now fe_base_<name>.pdf (or not needed), so fe_logobase.pdf can go.
function fe_logo_meta_finish(string $dir, array $m): void {
	$v1 = !empty($m['v1']);
	if ($v1) {
		foreach ($m['out'] as $file => $rec) {
			if (!empty($rec['base']) && $rec['base'] === 'fe_logobase.pdf' && file_exists("$dir/fe_logobase.pdf")) {
				@copy("$dir/fe_logobase.pdf", "$dir/fe_base_" . basename((string) $file));
				$m['out'][$file]['base'] = '';
			}
		}
	}
	fe_logo_meta_save($dir, $m);
	if ($v1) @unlink("$dir/fe_logobase.pdf");
	@array_map('unlink', glob("$dir/*_feprev.png") ?: array());   // the editor's background preview renders again
}

// Gem & aktivér: the logo's place is the LOGO row of the form saved (a row never resized gets the size the editor shows, 35 mm
// wide and the height from the image); every form of the language takes that place, so each form shows in the editor where the
// logo prints, and the files of the language are stamped. Non-fatal: a tool failure leaves the files as they were.
function fe_composite_logo($db_id, $form_nr, $sprog, $defaultOut = 'bg.pdf') {
	if (!function_exists('shell_exec')) return;
	$dir  = "../logolib/$db_id";
	$logo = "$dir/fe_logo.png";
	if (!file_exists($logo)) return;
	$sp = db_escape_string($sprog);
	$r = db_fetch_array(db_select("select xa,ya,xb,yb from formularer where formular=" . (int) $form_nr . " and art=1 and upper(beskrivelse)='LOGO' and sprog='$sp'", __FILE__ . " linje " . __LINE__));
	if (!$r) return;
	$xa = (float) $r['xa']; $ya = (float) $r['ya']; $w = (float) $r['xb']; $h = (float) $r['yb'];
	if ($w <= 0) $w = 35;
	if ($h <= 0) {
		$li = @getimagesize($logo);
		$h = ($li && $li[0] > 0) ? round($w * $li[1] / $li[0], 1) : 20;
	}
	db_modify("update formularer set xa=$xa, ya=$ya, xb=$w, yb=$h where art=1 and upper(beskrivelse)='LOGO' and sprog='$sp'", __FILE__ . " linje " . __LINE__);

	$m = fe_logo_meta_load($dir);
	$p = fe_logo_prefix((string) $sprog);
	$m['langs'][$p] = array('sprog' => (string) $sprog, 'place' => array($xa, $ya, $w, $h));
	fe_logo_stamp_lang($dir, $p, array($xa, $ya, $w, $h), $m);
	fe_logo_meta_finish($dir, $m);
}

// A background uploaded or deleted on logoupload.php ($name without .pdf; $deleted = it was removed): the stamps are made again on
// the new ground. A deleted file the user had under a stamp loses its kept copy, so the stamp falls back as the engine would.
function fe_logo_background_changed($db_id, string $name, bool $deleted): void {
	if (!function_exists('shell_exec')) return;
	$dir = "../logolib/$db_id";
	if (!file_exists("$dir/fe_logo_meta.json")) return;
	$m = fe_logo_meta_load($dir);
	if ($deleted && isset($m['out']["$name.pdf"])) {
		unset($m['out']["$name.pdf"]);
		@unlink("$dir/fe_base_$name.pdf");
	}
	if (file_exists("$dir/fe_logo.png")) {
		foreach ($m['langs'] as $p => $lang) {
			if (isset($lang['place']) && is_array($lang['place']) && count($lang['place']) === 4) fe_logo_stamp_lang($dir, (string) $p, $lang['place'], $m);
		}
	}
	fe_logo_meta_finish($dir, $m);
}

// Fjern logo: every stamp still in place gets the user's own file back, or goes when there was none; the kept copies go too.
function fe_logo_remove_all($db_id): void {
	$dir = "../logolib/$db_id";
	$m = fe_logo_meta_load($dir);
	foreach ($m['out'] as $file => $rec) {
		$f = "$dir/" . basename((string) $file);
		if (!file_exists($f) || md5_file($f) !== $rec['md5']) continue;   // replaced by the user since: theirs now
		$b = "$dir/" . (!empty($rec['base']) ? basename((string) $rec['base']) : 'fe_base_' . basename((string) $file));
		if (!empty($rec['own']) && file_exists($b)) @copy($b, $f); else @unlink($f);
	}
	@array_map('unlink', glob("$dir/fe_base_*.pdf") ?: array());
	@unlink("$dir/fe_logobase.pdf");
	@unlink("$dir/fe_logo_meta.json");
	@unlink("$dir/fe_logo.png");
	@unlink("$dir/fe_logo_pending.png");
	@unlink("$dir/fe_logors.png");
	@array_map('unlink', glob("$dir/*_feprev.png") ?: array());
}
endif;
