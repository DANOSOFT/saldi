<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/formeditor.php --- patch 5.0.0 --- 2026-07-10 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// New visual (drag & drop) form editor - phase 1, increment 1.
//
// This page is PURELY ADDITIVE. It is a new "head" on the existing "body":
//   * It reads the same `formularer` rows the old coordinate editor uses.
//   * It shows them as draggable elements on an A4 sheet (WYSIWYG).
//   * On save it only issues UPDATE ... WHERE id=? AND formular=? AND sprog=?
//     for geometry/typography - it never inserts, deletes or restructures
//     rows and never touches the `beskrivelse`/variable syntax, so the PDF
//     engine and every existing customer keep working exactly as before.
//
// The coordinate system is documented from the PDF engine (includes/formfunk.php):
//   x = mm from the LEFT edge, y = mm from the BOTTOM edge (origin bottom-left),
//   page 210 x 297 mm. Screen top = (297 - y). Right-align anchors on x.
// ----------------------------------------------------------------------

@session_start();
$s_id = session_id();

// ---------------------------------------------------------------------------
// Detect the JSON save endpoint BEFORE any HTML chrome so we can return clean
// JSON. online.php establishes $db_id / $sprog_id / DB selection / login, but
// on some (legacy) menu types it also prints a page <head>; we buffer + discard
// that output on the API path so the fetch() always receives pure JSON.
// ---------------------------------------------------------------------------
$fe_action = isset($_GET['fe_action']) ? $_GET['fe_action'] : '';
$FE_API    = in_array($fe_action, array('save','reset','logo_upload','logo_remove','savedraft','discarddraft','save_mail','set_printlang'), true);

if ($FE_API) ob_start();

$title = "Formulareditor";
$css   = "../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

// ---------------------------------------------------------------------------
//  Background helpers (shared by the view and the logo compositor)
// ---------------------------------------------------------------------------
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
function fe_composite_logo($db_id, $form_nr, $sprog) {
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
	$out    = $active ? $active : "$dir/bg.pdf";
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


if ($fe_action === 'save') {
	@ob_end_clean();
	header('Content-Type: application/json; charset=utf-8');

	if (empty($db_id)) {
		http_response_code(401);
		print json_encode(array('ok' => false, 'error' => 'session'));
		exit;
	}

	$payload = json_decode(file_get_contents('php://input'), true);
	if (!is_array($payload) || !isset($payload['form_nr']) || !isset($payload['sprog']) || !isset($payload['elements'])) {
		http_response_code(400);
		print json_encode(array('ok' => false, 'error' => 'payload'));
		exit;
	}

	$form_nr = (int) $payload['form_nr'];
	$sprog   = db_escape_string((string) $payload['sprog']);
	$updated = 0;

	// Whitelist of forms this editor understands, to avoid accidental writes.
	$valid_forms = array(1,2,3,4,5,6,7,8,9,11,12,13,14);
	if (!in_array($form_nr, $valid_forms, true)) {
		http_response_code(400);
		print json_encode(array('ok' => false, 'error' => 'form'));
		exit;
	}

	$inserted = array();  // temp id (from editor) -> new real DB id
	transaktion('begin');
	foreach ($payload['elements'] as $el) {
		$id = isset($el['id']) ? (int) $el['id'] : 0;

		// Sanitise every value; geometry + typography only.
		$xa    = round((float) ($el['xa'] ?? 0), 2);
		$ya    = round((float) ($el['ya'] ?? 0), 2);
		$xb    = round((float) ($el['xb'] ?? 0), 2);
		$yb    = round((float) ($el['yb'] ?? 0), 2);
		$str   = round((float) ($el['str'] ?? 0), 2);
		$color = (int) round((float) ($el['color'] ?? 0));
		if ($color < 0) $color = 0;
		$art   = (int) ($el['art'] ?? 0);

		// --- NEW element (temp id <= 0): INSERT a fresh text (art 2) or line (art 1) ---
		if ($id <= 0) {
			if (!in_array($art, array(1,2), true)) continue;   // only text/line can be added
			$just = strtoupper((string) ($el['justering'] ?? 'V'));
			if (!in_array($just, array('V','C','H'), true)) $just = 'V';
			$fed    = (!empty($el['fed']))    ? 'on' : '';
			$kursiv = (!empty($el['kursiv'])) ? 'on' : '';
			$font   = db_escape_string((string) ($el['font'] ?? 'Helvetica'));
			if ($font === '') $font = 'Helvetica';
			$side   = db_escape_string((string) ($el['side'] ?? 'A'));
			$besk   = db_escape_string((string) ($el['besk'] ?? ''));
			if ($str <= 0 && $art === 2) $str = 10;
			$qtxt  = "insert into formularer (formular,art,beskrivelse,xa,ya,xb,yb,str,color,font,fed,kursiv,side,justering,sprog) values ";
			$qtxt .= "($form_nr,$art,'$besk',$xa,$ya,$xb,$yb,$str,$color,'$font','$fed','$kursiv','$side','$just','$sprog') returning id";
			$rq = db_select($qtxt, __FILE__ . " linje " . __LINE__);
			$rr = db_fetch_array($rq);
			if ($rr && isset($rr['id'])) { $inserted[(string) $id] = (int) $rr['id']; $updated++; }
			continue;
		}

		if ($art === 3) {
			// Order-line table: geometry the editor changes (row count / top Y /
			// spacing / column X). Column str/typography stay untouched; colour is
			// written ONLY for the column rows (which send 'color'), so the data
			// follows the theme's Text colour. The 'generelt' geometry row sends no
			// 'color' key and is never recoloured.
			$set = "xa=$xa, ya=$ya, xb=$xb";
			if (array_key_exists('color', $el)) $set .= ", color=$color";
			// per-column alignment + bold (only the column rows send these; the
			// 'generelt' geometry row does not, so it's never touched).
			if (array_key_exists('justering', $el)) {
				$cjust = strtoupper((string) $el['justering']);
				if (in_array($cjust, array('V','C','H'), true)) $set .= ", justering='$cjust'";
			}
			if (array_key_exists('fed', $el)) $set .= ", fed='" . (!empty($el['fed']) ? 'on' : '') . "'";
		} else {
			// Geometry + colour for every row; alignment/bold/italic ONLY for text
			// rows (art=2) so line/logo rows are never even cosmetically altered.
			$set = "xa=$xa, ya=$ya, xb=$xb, yb=$yb, str=$str, color=$color";
			if ($art === 2) {
				$just = strtoupper((string) ($el['justering'] ?? 'V'));
				if (!in_array($just, array('V','C','H'), true)) $just = 'V';
				$fed    = (!empty($el['fed']))    ? 'on' : '';
				$kursiv = (!empty($el['kursiv'])) ? 'on' : '';
				$set .= ", justering='$just', fed='$fed', kursiv='$kursiv'";
				// Font family (brand presets). Only ever WRITE a family the print
				// engine + PostScript prolog fully support across bold/italic (see
				// includes/faktinit.ps + formfunk.php). An unknown/exotic stored font
				// is left untouched so we can never coerce a working form's font away.
				if (isset($el['font']) && in_array((string) $el['font'], array('Helvetica','Times','Courier','Palatino','NewCenturySchlbk','Ocrbb12'), true)) {
					$set .= ", font='" . db_escape_string((string) $el['font']) . "'";
				}
				// text content is editable in the editor now (FR-2.1). Escape it;
				// this is the same field the classic Formularkort text box writes.
				if (array_key_exists('besk', $el)) {
					$besk = db_escape_string((string) $el['besk']);
					$set .= ", beskrivelse='$besk'";
				}
			}
		}

		// The id + formular + sprog guard (and art restriction below) means we can
		// only ever touch a row that truly belongs to the form/variant currently
		// open in this account's DB, and only the line/text arts the editor loads.
		// art 3 (order-line table generelt + columns) is geometry-only, like lines.
		$qtxt = "update formularer set $set where id=$id and formular=$form_nr and sprog='$sprog' and art in (1,2,3)";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		$updated++;
	}

	// Deletions requested from the editor. Protected: the LOGO and the whole
	// order-line table (art=3) can NEVER be deleted here, and we only ever touch
	// art 1/2 rows of THIS form + variant in this account's DB.
	$deleted = 0;
	if (isset($payload['deleted']) && is_array($payload['deleted'])) {
		$del_ids = array();
		foreach ($payload['deleted'] as $did) { $did = (int) $did; if ($did > 0) $del_ids[] = $did; }
		if ($del_ids) {
			$in = implode(',', array_unique($del_ids));
			db_modify("delete from formularer where id in ($in) and formular=$form_nr and sprog='$sprog' and art in (1,2) and upper(beskrivelse) <> 'LOGO'", __FILE__ . " linje " . __LINE__);
			$deleted = count($del_ids);
		}
	}

	transaktion('commit');

	// Activating supersedes any draft for this form + variant.
	$sprog_raw = (string) $payload['sprog'];
	$sprog_safe = preg_replace('/[^A-Za-z0-9_]/', '_', $sprog_raw);
	@unlink("../logolib/$db_id/fe_draft_{$form_nr}_{$sprog_safe}.json");

	// Stamp the placed logo onto the print background (if any logo uploaded).
	fe_composite_logo($db_id, $form_nr, $sprog_raw);

	print json_encode(array('ok' => true, 'updated' => $updated, 'deleted' => $deleted, 'inserted' => $inserted));
	exit;
}

// ===========================================================================
//  RESET ENDPOINT  (POST ?fe_action=reset, JSON body)
//  Restores ONE form + ONE variant to Saldi's standard layout. Scoped so it
//  never touches other forms or other variants (unlike the global "reload
//  standard forms"). Never deletes unless the standard rows were found first.
// ===========================================================================
if ($fe_action === 'reset') {
	global $db_encode;
	@ob_end_clean();
	header('Content-Type: application/json; charset=utf-8');

	if (empty($db_id)) {
		http_response_code(401);
		print json_encode(array('ok' => false, 'error' => 'session'));
		exit;
	}
	$payload = json_decode(file_get_contents('php://input'), true);
	$form_nr = isset($payload['form_nr']) ? (int) $payload['form_nr'] : 0;
	$sprog   = isset($payload['sprog']) ? (string) $payload['sprog'] : '';
	$valid_forms = array(1,2,3,4,5,6,7,8,9,11,12,13,14);
	if (!in_array($form_nr, $valid_forms, true) || $sprog === '') {
		http_response_code(400);
		print json_encode(array('ok' => false, 'error' => 'payload'));
		exit;
	}
	$sprog_db = db_escape_string($sprog);

	// Read the standard rows for THIS form from the factory file FIRST.
	$stdfile = "../importfiler/formular.txt";
	if (!is_readable($stdfile)) { print json_encode(array('ok' => false, 'error' => 'nostd')); exit; }
	$rows = array();
	foreach (file($stdfile) as $linje) {
		$linje = rtrim($linje, "\r\n");
		if ($linje === '') continue;
		if ($db_encode == 'UTF8') $linje = mb_convert_encoding($linje, 'UTF-8', 'ISO-8859-1');
		$f = explode(chr(9), $linje);
		if (count($f) < 15) continue;
		foreach ($f as $k => $v) {
			$v = trim($v);
			if (strlen($v) >= 2 && $v[0] === "'" && substr($v, -1) === "'") $v = substr($v, 1, -1);
			$f[$k] = $v;
		}
		if ((int) $f[0] !== $form_nr) continue;         // only this form
		$xa = is_numeric($f[4]) ? (float) $f[4] : 0;
		if (!($xa > 0)) continue;                        // matches formularimport() guard
		$rows[] = array(
			'art'  => (int) $f[1],
			'besk' => db_escape_string($f[2]),
			'just' => db_escape_string(trim($f[3])),
			'xa'   => $xa,
			'ya'   => is_numeric($f[5]) ? (float) $f[5] : 0,
			'xb'   => is_numeric($f[6]) ? (float) $f[6] : 0,
			'yb'   => is_numeric($f[7]) ? (float) $f[7] : 0,
			'str'  => is_numeric($f[8]) ? (float) $f[8] : 0,
			'color'=> is_numeric($f[9]) ? (float) $f[9] : 0,
			'font' => db_escape_string(trim($f[10])),
			'fed'  => db_escape_string(trim($f[11])),
			'kursiv'=> db_escape_string(trim($f[12])),
			'side' => db_escape_string(trim($f[13])),
		);
	}
	// Refuse to wipe the form if we have no standard to put back.
	if (!count($rows)) { print json_encode(array('ok' => false, 'error' => 'nostd')); exit; }

	transaktion('begin');
	db_modify("delete from formularer where formular=$form_nr and sprog='$sprog_db'", __FILE__ . " linje " . __LINE__);
	foreach ($rows as $r) {
		$qtxt  = "insert into formularer (formular,art,beskrivelse,xa,ya,xb,yb,justering,str,color,font,fed,kursiv,side,sprog) values (";
		$qtxt .= "$form_nr,$r[art],'$r[besk]',$r[xa],$r[ya],$r[xb],$r[yb],'$r[just]',$r[str],$r[color],'$r[font]','$r[fed]','$r[kursiv]','$r[side]','$sprog_db')";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	transaktion('commit');

	print json_encode(array('ok' => true, 'rows' => count($rows)));
	exit;
}


if ($fe_action === 'logo_upload') {
	@ob_end_clean();
	header('Content-Type: application/json; charset=utf-8');
	if (empty($db_id)) { http_response_code(401); print json_encode(array('ok'=>false,'error'=>'session')); exit; }

	if (empty($_FILES['logo']) || !is_uploaded_file($_FILES['logo']['tmp_name'])) {
		http_response_code(400); print json_encode(array('ok'=>false,'error'=>'nofile')); exit;
	}
	$tmp = $_FILES['logo']['tmp_name'];
	if ((int) $_FILES['logo']['size'] > 5 * 1024 * 1024) { print json_encode(array('ok'=>false,'error'=>'toobig')); exit; }
	$info = @getimagesize($tmp);
	if (!$info || !in_array($info['mime'], array('image/png','image/jpeg','image/jpg'), true)) {
		print json_encode(array('ok'=>false,'error'=>'badtype')); exit;
	}
	$w = (int) $info[0]; $h = (int) $info[1];
	if ($w < 1 || $h < 1) { print json_encode(array('ok'=>false,'error'=>'badimg')); exit; }

	$dir = "../logolib/$db_id";
	if (!is_dir($dir)) @mkdir($dir, 0775, true);
	$png = "$dir/fe_logo.png";

	// Re-encode via ImageMagick to strip any embedded scripts/metadata (NR-8),
	// and cap the size. Fall back to a plain move if convert is unavailable.
	$ok = false;
	if (function_exists('shell_exec')) {
		@shell_exec("convert " . escapeshellarg($tmp) . "[0] -strip -background none -resize '1500x1500>' " . escapeshellarg($png) . " 2>/dev/null");
		if (file_exists($png)) $ok = true;
	}
	if (!$ok) { $ok = @move_uploaded_file($tmp, $png); }
	if (!$ok || !file_exists($png)) { print json_encode(array('ok'=>false,'error'=>'store')); exit; }

	// IMPORTANT: do NOT write ../logolib/logo_<db_id>.eps here. The print engine
	// auto-stamps that file on every form without a background PDF, which would
	// silently change printed output (e.g. orders). The uploaded logo stays
	// editor-only until an explicit, opt-in compositing step is built. We also
	// remove any stale EPS a previous version of this endpoint may have created,
	// so existing printing is restored.
	@unlink("../logolib/logo_$db_id.eps");
	// refresh natural size from the normalised file
	$ni = @getimagesize($png); if ($ni) { $w = (int) $ni[0]; $h = (int) $ni[1]; }

	print json_encode(array('ok'=>true, 'w'=>$w, 'h'=>$h, 'url'=>"../logolib/$db_id/fe_logo.png?t=".time()));
	exit;
}


//  LOGO REMOVE ENDPOINT  (POST ?fe_action=logo_remove, JSON body)

if ($fe_action === 'logo_remove') {
	@ob_end_clean();
	header('Content-Type: application/json; charset=utf-8');
	if (empty($db_id)) { http_response_code(401); print json_encode(array('ok'=>false,'error'=>'session')); exit; }

	$payload = json_decode(file_get_contents('php://input'), true);
	$form_nr = (isset($payload['form_nr'])) ? (int) $payload['form_nr'] : 0;
	$sprog   = (isset($payload['sprog']))   ? (string) $payload['sprog'] : 'Dansk';

	$dir  = "../logolib/$db_id";
	$base = "$dir/fe_logobase.pdf";
	// Put the pristine letterhead back where the print engine reads it, then drop
	// only the logo overlay + meta. The pristine snapshot itself is KEPT — it is
	// the user's original letterhead and must never be thrown away.
	if (file_exists($base)) {
		$active = fe_active_bg($db_id, $form_nr, $sprog);
		if ($active) @copy($base, $active);
	}
	@unlink("$dir/fe_logo.png");
	@unlink("$dir/fe_logors.png");
	@unlink("$dir/fe_logo_meta.json");
	// bust the editor bg preview cache so the clean background re-renders next load
	@array_map('unlink', glob("$dir/*_feprev.png") ?: array());

	print json_encode(array('ok'=>true));
	exit;
}


if ($fe_action === 'savedraft' || $fe_action === 'discarddraft') {
	@ob_end_clean();
	header('Content-Type: application/json; charset=utf-8');
	if (empty($db_id)) { http_response_code(401); print json_encode(array('ok'=>false,'error'=>'session')); exit; }
	$payload = json_decode(file_get_contents('php://input'), true);
	$form_nr = isset($payload['form_nr']) ? (int) $payload['form_nr'] : 0;
	$sprog   = isset($payload['sprog']) ? (string) $payload['sprog'] : '';
	$valid_forms = array(1,2,3,4,5,6,7,8,9,11,12,13,14);
	if (!in_array($form_nr, $valid_forms, true) || $sprog === '') {
		http_response_code(400); print json_encode(array('ok'=>false,'error'=>'payload')); exit;
	}
	$dir = "../logolib/$db_id";
	if (!is_dir($dir)) @mkdir($dir, 0775, true);
	$sprog_safe = preg_replace('/[^A-Za-z0-9_]/', '_', $sprog);
	$file = "$dir/fe_draft_{$form_nr}_{$sprog_safe}.json";

	if ($fe_action === 'discarddraft') {
		@unlink($file);
		print json_encode(array('ok'=>true));
		exit;
	}
	// savedraft: store the working state (elements/table/deleted) verbatim.
	$state = isset($payload['state']) ? $payload['state'] : null;
	if ($state === null) { http_response_code(400); print json_encode(array('ok'=>false,'error'=>'payload')); exit; }
	$doc = array('ts' => time(), 'form_nr' => $form_nr, 'sprog' => $sprog, 'state' => $state);
	$ok = @file_put_contents($file, json_encode($doc)) !== false;
	print json_encode($ok ? array('ok'=>true,'ts'=>$doc['ts']) : array('ok'=>false,'error'=>'store'));
	exit;
}

// ---------------------------------------------------------------------------
//  SAVE MAIL TEXT ENDPOINT  (POST ?fe_action=save_mail, JSON body)
//  The e-mail that sends with the PDF: art=5, xa 1=subject, 2=body, 3=attach.
// ---------------------------------------------------------------------------
if ($fe_action === 'save_mail') {
	@ob_end_clean();
	header('Content-Type: application/json; charset=utf-8');
	if (empty($db_id)) { http_response_code(401); print json_encode(array('ok'=>false,'error'=>'session')); exit; }
	$payload = json_decode(file_get_contents('php://input'), true);
	$form_nr = isset($payload['form_nr']) ? (int) $payload['form_nr'] : 0;
	$sprog   = isset($payload['sprog']) ? db_escape_string((string) $payload['sprog']) : '';
	$valid_forms = array(1,2,3,4,5,6,7,8,9,11,12,13,14);
	if (!in_array($form_nr, $valid_forms, true) || $sprog === '') {
		http_response_code(400); print json_encode(array('ok'=>false,'error'=>'payload')); exit;
	}
	$has_attach = in_array($form_nr, array(1,2,4), true);
	$vals = array(
		1 => db_escape_string((string) (isset($payload['subject']) ? $payload['subject'] : '')),
		2 => db_escape_string((string) (isset($payload['body'])    ? $payload['body']    : '')),
	);
	if ($has_attach) $vals[3] = db_escape_string((string) (isset($payload['attach']) ? $payload['attach'] : ''));
	transaktion('begin');
	foreach ($vals as $xa => $v) {
		$r = db_fetch_array(db_select("select id from formularer where formular=$form_nr and art=5 and sprog='$sprog' and xa='$xa'", __FILE__ . " linje " . __LINE__));
		if ($r && $r['id']) {
			db_modify("update formularer set beskrivelse='$v' where id=" . (int) $r['id'] . " and formular=$form_nr and art=5", __FILE__ . " linje " . __LINE__);
		} else {
			db_modify("insert into formularer (xa, formular, art, sprog, beskrivelse) values ('$xa', $form_nr, 5, '$sprog', '$v')", __FILE__ . " linje " . __LINE__);
		}
	}
	transaktion('commit');
	print json_encode(array('ok'=>true));
	exit;
}

// ---------------------------------------------------------------------------
//  SET PRINT-LANGUAGE LOCK  (POST ?fe_action=set_printlang, JSON body)
//  Writes/removes ../logolib/<db_id>/fe_printlang_<form>.json. When present,
//  the print engine forces this form into that language for every customer.
// ---------------------------------------------------------------------------
if ($fe_action === 'set_printlang') {
	@ob_end_clean();
	header('Content-Type: application/json; charset=utf-8');
	if (empty($db_id)) { http_response_code(401); print json_encode(array('ok'=>false,'error'=>'session')); exit; }
	$payload = json_decode(file_get_contents('php://input'), true);
	$form_nr = isset($payload['form_nr']) ? (int) $payload['form_nr'] : 0;
	$valid_forms = array(1,2,3,4,5,6,7,8,9,11,12,13,14);
	if (!in_array($form_nr, $valid_forms, true)) { http_response_code(400); print json_encode(array('ok'=>false,'error'=>'form')); exit; }
	if (!is_dir("../logolib/$db_id")) @mkdir("../logolib/$db_id", 0775, true);
	$file = "../logolib/$db_id/fe_printlang_$form_nr.json";
	$lock = !empty($payload['lock']);
	if ($lock) {
		$sp = trim((string) (isset($payload['sprog']) ? $payload['sprog'] : ''));
		if ($sp === '') { http_response_code(400); print json_encode(array('ok'=>false,'error'=>'sprog')); exit; }
		@file_put_contents($file, json_encode(array('sprog'=>$sp)));
	} else {
		@unlink($file);
	}
	print json_encode(array('ok'=>true, 'locked'=>$lock));
	exit;
}

// ---- helpers ---------------------------------------------------------------
function fe_bg_display_name($sprog_value) {
	global $sprog_id;
	if ($sprog_value === 'Dansk') return ($sprog_id == 1) ? 'Standard' : 'Default';
	return $sprog_value;
}


function fe_var_map() {
	static $map = null;
	if ($map === null) {
		$map = array(
			// token => array(Danish, English)
			// --- own company (eget/egen) ---
			'eget_firmanavn'  => array('Eget firmanavn', 'Own company name'),
			'egen_addr1'      => array('Egen adresse 1', 'Own address 1'),
			'egen_addr2'      => array('Egen adresse 2', 'Own address 2'),
			'eget_postnr'     => array('Eget postnr.', 'Own zip code'),
			'eget_bynavn'     => array('Egen by', 'Own city'),
			'eget_land'       => array('Eget land', 'Own country'),
			'eget_cvrnr'      => array('Eget CVR-nr.', 'Own VAT no.'),
			'egen_tlf'        => array('Eget tlf.', 'Own phone'),
			'egen_mobile'     => array('Egen mobil', 'Own mobile'),
			'egen_bank_navn'  => array('Eget banknavn', 'Own bank name'),
			'egen_bank_reg'   => array('Eget reg.nr.', 'Own reg. no.'),
			'egen_bank_konto' => array('Egen bankkonto', 'Own bank account'),
			'egen_email'      => array('Egen e-mail', 'Own e-mail'),
			'egen_web'        => array('Egen hjemmeside', 'Own website'),
			// --- employee / seller (ansat) ---
			'ansat_initialer' => array('Sælger initialer', 'Seller initials'),
			'ansat_navn'      => array('Sælger navn', 'Seller name'),
			'ansat_addr1'     => array('Sælger adresse 1', 'Seller address 1'),
			'ansat_addr2'     => array('Sælger adresse 2', 'Seller address 2'),
			'ansat_postnr'    => array('Sælger postnr.', 'Seller zip code'),
			'ansat_by'        => array('Sælger by', 'Seller city'),
			'ansat_email'     => array('Sælger e-mail', 'Seller e-mail'),
			'ansat_mobil'     => array('Sælger mobil', 'Seller mobile'),
			'ansat_tlf'       => array('Sælger tlf.', 'Seller phone'),
			'ansat_privattlf' => array('Sælger privat tlf.', 'Seller private phone'),
			// --- account (kontokort) ---
			'konto_firmanavn' => array('Konto firmanavn', 'Account company name'),
			'konto_addr1'     => array('Konto adresse 1', 'Account address 1'),
			'konto_addr2'     => array('Konto adresse 2', 'Account address 2'),
			'konto_postnr'    => array('Konto postnr.', 'Account zip code'),
			'konto_bynavn'    => array('Konto by', 'Account city'),
			'konto_land'      => array('Konto land', 'Account country'),
			'konto_kontakt'   => array('Konto kontakt', 'Account contact'),
			'konto_cvrnr'     => array('Konto CVR-nr.', 'Account VAT no.'),
			'konto_valuta'    => array('Konto valuta', 'Account currency'),
			// --- customer / order (ordre) ---
			'ordre_firmanavn' => array('Kunde firmanavn', 'Customer company name'),
			'ordre_addr1'     => array('Kunde adresse 1', 'Customer address 1'),
			'ordre_addr2'     => array('Kunde adresse 2', 'Customer address 2'),
			'ordre_postnr'    => array('Kunde postnr.', 'Customer zip code'),
			'ordre_bynavn'    => array('Kunde by', 'Customer city'),
			'ordre_land'      => array('Kunde land', 'Customer country'),
			'ordre_kontakt'   => array('Kunde kontakt', 'Customer contact'),
			'ordre_cvrnr'     => array('Kunde CVR-nr.', 'Customer VAT no.'),
			'ordre_email'     => array('Kunde e-mail', 'Customer e-mail'),
			'ordre_tlf'       => array('Kunde tlf.', 'Customer phone'),
			'ordre_hvem'      => array('Udført af', 'Performed by'),
			'ordre_ean'       => array('Kunde EAN', 'Customer EAN'),
			'ordre_institution' => array('Kunde institution', 'Customer institution'),
			'ordre_kundenr'   => array('Kundenr.', 'Customer no.'),
			'ordre_kundeordnr'=> array('Kundeordrenr.', 'Customer order no.'),
			'ordre_afd'       => array('Afdeling', 'Department'),
			'ordre_momssats'  => array('Momssats', 'VAT rate'),
			'ordre_notes'     => array('Ordrenote', 'Order note'),
			'ordre_ordredate' => array('Ordredato', 'Order date'),
			'ordre_ordrenr'   => array('Ordrenr.', 'Order no.'),
			'ordre_projekt'   => array('Projekt', 'Project'),
			'ordre_valuta'    => array('Valuta', 'Currency'),
			'ordre_fakturanr' => array('Fakturanr.', 'Invoice no.'),
			'ordre_fakturadate' => array('Fakturadato', 'Invoice date'),
			'ordre_betalingsbet'  => array('Betalingsbetingelser', 'Payment terms'),
			'ordre_betalingsdage' => array('Betalingsdage', 'Payment days'),
			'ordre_felt_1'    => array('Felt 1', 'Field 1'),
			'ordre_felt_2'    => array('Felt 2', 'Field 2'),
			'ordre_felt_3'    => array('Felt 3', 'Field 3'),
			'ordre_felt_4'    => array('Felt 4', 'Field 4'),
			'ordre_felt_5'    => array('Felt 5', 'Field 5'),
			// --- delivery address (lev) ---
			'ordre_lev_navn'   => array('Leveringsnavn', 'Delivery name'),
			'ordre_lev_addr1'  => array('Leveringsadresse 1', 'Delivery address 1'),
			'ordre_lev_addr2'  => array('Leveringsadresse 2', 'Delivery address 2'),
			'ordre_lev_postnr' => array('Leverings postnr.', 'Delivery zip code'),
			'ordre_lev_bynavn' => array('Leverings by', 'Delivery city'),
			'ordre_lev_kontakt'=> array('Leverings kontakt', 'Delivery contact'),
			'ordre_levdate'    => array('Leveringsdato', 'Delivery date'),
			// --- form-level totals & pages (formular) ---
			'formular_forfaldsdato' => array('Forfaldsdato', 'Due date'),
			'formular_transportsum' => array('Transport', 'Carried forward'),
			'formular_ialt'    => array('I alt', 'Total'),
			'formular_moms'    => array('Moms', 'VAT'),
			'formular_momsgrundlag' => array('Momsgrundlag', 'VAT basis'),
			'formular_side'    => array('Side', 'Page'),
			'formular_nextside'=> array('Næste side', 'Next page'),
			'formular_preside' => array('Forrige side', 'Previous page'),
			'formular_betalingsid' => array('Betalings-ID', 'Payment ID'),
			'formular_grossWeight' => array('Bruttovægt', 'Gross weight'),
			'formular_netWeight'   => array('Nettovægt', 'Net weight'),
			// --- misc ---
			'levering_lev_nr'  => array('Leverings nr.', 'Delivery no.'),
			'levering_salgsdate' => array('Salgsdato', 'Sales date'),
			'forfalden_sum'    => array('Forfalden sum', 'Amount due'),
			'rykker_gebyr'     => array('Rykkergebyr', 'Reminder fee'),
			'afdeling_note'    => array('Afdelingsnote', 'Department note'),
			// --- order-line columns (art=3), for later increments ---
			'posnr'      => array('Pos nr.', 'Pos no.'),
			'varenr'     => array('Varenr.', 'Item no.'),
			'lev_varenr' => array('Lev. varenr.', 'Supplier item no.'),
			'antal'      => array('Antal', 'Qty'),
			'enhed'      => array('Enhed', 'Unit'),
			'beskrivelse'=> array('Beskrivelse', 'Description'),
			'pris'       => array('Pris', 'Price'),
			'rabat'      => array('Rabat', 'Discount'),
			'linjesum'   => array('Linjesum', 'Line total'),
			'lokation'   => array('Lokation', 'Location'),
		);
	}
	return $map;
}

function fe_var_label($token) {
	global $sprog_id;
	$map = fe_var_map();
	$dk = ($sprog_id == 1);
	// normalise: strip any "(args)" and a stray leading/trailing ")"
	$key = $token;
	if (($p = strpos($key, '(')) !== false) $key = substr($key, 0, $p);
	$key = trim($key, ") \t");
	if (isset($map[$key])) return $dk ? $map[$key][0] : $map[$key][1];
	// fallback: prettify "table_field" -> "Field" so no raw syntax leaks
	$pretty = $key;
	foreach (array('ordre_','eget_','egen_','ansat_','konto_','formular_','levering_','forfalden_','rykker_','afdeling_') as $pre) {
		if (strpos($pretty, $pre) === 0) { $pretty = substr($pretty, strlen($pre)); break; }
	}
	$pretty = trim(str_replace('_', ' ', $pretty));
	return ($pretty === '') ? $token : ucfirst($pretty);
}

$forms = array(
	1  => findtekst('812|Tilbud', $sprog_id),
	9  => findtekst('574|Plukliste', $sprog_id),
	2  => findtekst('575|Ordrebekræftelse', $sprog_id),
	3  => findtekst('576|Følgeseddel', $sprog_id),
	4  => findtekst('989|Faktura', $sprog_id),
	5  => findtekst('577|Kreditnota', $sprog_id),
	6  => findtekst('578|Rykker', $sprog_id) . ' 1',
	7  => findtekst('578|Rykker', $sprog_id) . ' 2',
	8  => findtekst('578|Rykker', $sprog_id) . ' 3',
	11 => findtekst('515|Kontokort', $sprog_id),
	12 => findtekst('954|Indkøbsforslag', $sprog_id),
	13 => findtekst('579|Rekvisition', $sprog_id),
	14 => findtekst('580|Købsfaktura', $sprog_id),
);

$form_nr = isset($_GET['form_nr']) ? (int) $_GET['form_nr'] : 4;
if (!isset($forms[$form_nr])) $form_nr = 4;
$sprog = isset($_GET['sprog']) && $_GET['sprog'] !== '' ? $_GET['sprog'] : 'Dansk';
$sprog_db = db_escape_string($sprog);

$returside = nav_back_url((isset($_GET['returside']) && $_GET['returside']) ? $_GET['returside'] : 'formularkort.php');

// UI language: Danish account (sprog_id==1) -> Danish, otherwise English.
$dk_ui = ($sprog_id == 1);
$T = function($da, $en) use ($dk_ui) { return $dk_ui ? $da : $en; };

// ---- load elements (art 1 = lines/logo, art 2 = texts) --------------------
$elements = array();
$qtxt = "select id, art, beskrivelse, xa, ya, xb, yb, str, color, font, fed, kursiv, side, justering "
      . "from formularer where formular = $form_nr and art in (1,2) and sprog = '$sprog_db' "
      . "and beskrivelse != 'GEBYR' order by art, ya desc, xa";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$art  = (int) $r['art'];
	$besk = $r['beskrivelse'];
	if ($art === 1 && strtoupper($besk) === 'LOGO') {
		$kind = 'logo';
	} elseif ($art === 1) {
		$kind = 'line';
	} else {
		$kind = 'text';
	}
	$elements[] = array(
		'id'        => (int) $r['id'],
		'art'       => $art,
		'kind'      => $kind,
		'besk'      => $besk,
		'xa'        => (float) $r['xa'],
		'ya'        => (float) $r['ya'],
		'xb'        => (float) $r['xb'],
		'yb'        => (float) $r['yb'],
		'str'       => (float) $r['str'],
		'color'     => (int) round((float) $r['color']),
		'font'      => $r['font'] ? $r['font'] : 'Helvetica',
		'justering' => $r['justering'] ? strtoupper($r['justering']) : 'V',
		'fed'       => ($r['fed'] === 'on') ? 1 : 0,
		'kursiv'    => ($r['kursiv'] === 'on') ? 1 : 0,
		'side'      => $r['side'],
	);
}

// ---- order-line table (art 3): the repeating item grid, as one element -----
// generelt row: xa=row count, ya=top Y (mm from bottom), xb=row spacing (mm).
// column rows: xa=X position (mm), str=font height, xb=text length (description).
$fe_table = null;
$tbl_gen = null;
$tbl_cols = array();
$qt = db_select("select id, beskrivelse, xa, ya, xb, str, color, justering, fed, kursiv, font "
              . "from formularer where formular = $form_nr and art = 3 and sprog = '$sprog_db' order by xa", __FILE__ . " linje " . __LINE__);
while ($rt = db_fetch_array($qt)) {
	if ($rt['beskrivelse'] === 'generelt') {
		$tbl_gen = array(
			'id'      => (int) $rt['id'],
			'count'   => (float) $rt['xa'],
			'ya'      => (float) $rt['ya'],
			'spacing' => (float) $rt['xb'],
		);
	} else {
		$tbl_cols[] = array(
			'id'    => (int) $rt['id'],
			'type'  => $rt['beskrivelse'],
			'label' => fe_var_label($rt['beskrivelse']),
			'xa'    => (float) $rt['xa'],
			'ya'    => (float) $rt['ya'],   // preserved unchanged on save
			'str'   => (float) $rt['str'],
			'xb'    => (float) $rt['xb'],   // description length - preserved
			'color' => (int) round((float) $rt['color']),
			'just'  => $rt['justering'] ? strtoupper($rt['justering']) : 'V',
			'fed'   => ($rt['fed'] === 'on') ? 1 : 0,
		);
	}
}
if ($tbl_gen && $tbl_cols) {
	$xs = array();
	foreach ($tbl_cols as $c) $xs[] = $c['xa'];
	$fe_table = array(
		'gen'   => $tbl_gen,
		'cols'  => $tbl_cols,
		'leftX' => min($xs),
		'rightX'=> max($xs),
	);
}

// list of distinct variants for the selector
$variants = array();
$qv = db_select("select distinct sprog from formularer order by sprog", __FILE__ . " linje " . __LINE__);
while ($rv = db_fetch_array($qv)) $variants[] = $rv['sprog'];

// print-language lock: the sprog this form is forced to print in (or '')
$fe_printlang = '';
$_plf = "../logolib/$db_id/fe_printlang_$form_nr.json";
if (@file_exists($_plf)) { $_pl = @json_decode(@file_get_contents($_plf), true); if (is_array($_pl) && !empty($_pl['sprog'])) $fe_printlang = (string) $_pl['sprog']; }

// ---- e-mail text (art=5): xa 1=subject, 2=body, 3=attachment ---------------
$fe_mail = array('subject'=>'', 'body'=>'', 'attach'=>'', 'has_attach'=>in_array($form_nr, array(1,2,4), true));
$qmail = db_select("select xa, beskrivelse from formularer where formular = $form_nr and art = 5 and sprog = '$sprog_db' order by xa", __FILE__ . " linje " . __LINE__);
while ($rm = db_fetch_array($qmail)) {
	$mxa = (int) $rm['xa'];
	if ($mxa === 1) $fe_mail['subject'] = $rm['beskrivelse'];
	elseif ($mxa === 2) $fe_mail['body'] = $rm['beskrivelse'];
	elseif ($mxa === 3) $fe_mail['attach'] = $rm['beskrivelse'];
}

// ---- background letterhead (rendered to PNG, best effort, cached) ----------
$bg_url = '';
$bg_src = '';
$sprog_prefix = ($sprog != 'Dansk') ? $sprog . "_" : "";
$form_slug = array(1=>'tilbud',4=>'faktura'); // known per-form filenames
$candidates = array();
if (isset($form_slug[$form_nr])) {
	$candidates[] = "../logolib/$db_id/{$sprog_prefix}{$form_slug[$form_nr]}_bg.pdf";
	$candidates[] = "../logolib/$db_id/{$form_slug[$form_nr]}_bg.pdf";
}
$candidates[] = "../logolib/$db_id/{$sprog_prefix}bg.pdf";
$candidates[] = "../logolib/$db_id/bg.pdf";

$logo_base = "../logolib/$db_id/fe_logobase.pdf";
$active_bg = '';
foreach ($candidates as $cand) { if (file_exists($cand)) { $active_bg = $cand; break; } }
$fe_meta_file = "../logolib/$db_id/fe_logo_meta.json";
$fe_meta = file_exists($fe_meta_file) ? json_decode(@file_get_contents($fe_meta_file), true) : array();
if (file_exists($logo_base) && $active_bg && isset($fe_meta['out_md5']) && md5_file($active_bg) === $fe_meta['out_md5']) {
	$bg_src = $logo_base;      // print bg is our composite -> show the clean base
} else {
	$bg_src = $active_bg;      // pristine / freshly uploaded letterhead -> show it
}
if ($bg_src) {
	$cache_png = "../logolib/$db_id/" . md5(basename($bg_src)) . "_feprev.png";
	$need = !file_exists($cache_png) || filemtime($cache_png) < filemtime($bg_src);
	if ($need && function_exists('shell_exec')) {
		// pdftoppm writes <prefix>-1.png for page 1; render at ~100 dpi.
		$prefix = escapeshellarg("../logolib/$db_id/" . md5(basename($bg_src)) . "_fetmp");
		@shell_exec("pdftoppm -png -r 100 -f 1 -l 1 " . escapeshellarg($bg_src) . " " . $prefix . " 2>/dev/null");
		$tmp1 = "../logolib/$db_id/" . md5(basename($bg_src)) . "_fetmp-1.png";
		if (file_exists($tmp1)) { @rename($tmp1, $cache_png); }
	}
	if (file_exists($cache_png)) $bg_url = $cache_png . '?t=' . filemtime($cache_png);
}

// uploaded logo image (shown in the editor at the LOGO element position)
$fe_logo_url = ''; $fe_logo_w = 0; $fe_logo_h = 0;
$logo_png = "../logolib/$db_id/fe_logo.png";
if (file_exists($logo_png)) {
	$li = @getimagesize($logo_png);
	if ($li) { $fe_logo_w = (int) $li[0]; $fe_logo_h = (int) $li[1]; }
	$fe_logo_url = "../logolib/$db_id/fe_logo.png?t=" . filemtime($logo_png);
}

// existing draft for this form + variant (working state not yet activated)
$fe_draft = null;
$sprog_safe = preg_replace('/[^A-Za-z0-9_]/', '_', $sprog);
$draft_file = "../logolib/$db_id/fe_draft_{$form_nr}_{$sprog_safe}.json";
if (file_exists($draft_file)) {
	$dj = json_decode(@file_get_contents($draft_file), true);
	if (is_array($dj) && isset($dj['state'])) $fe_draft = $dj;
}

// data for JS
$fe_data = array();
foreach ($elements as $el) {
	$e = $el;
	if ($el['kind'] === 'text') {
		$e['label'] = fe_render_besk_labels($el['besk']);
	}
	$fe_data[] = $e;
}


function fe_render_besk_labels($besk) {
	$out = array();
	$len = strlen($besk);
	$i = 0;
	$buf = '';
	while ($i < $len) {
		// if( ... ) or if(! ... ) conditional wrapper
		if (substr($besk, $i, 3) === 'if(') {
			$close = strpos($besk, ')', $i);
			if ($close !== false) {
				if ($buf !== '') { $out[] = array('t' => 'text', 'v' => $buf); $buf = ''; }
				$inner = trim(substr($besk, $i + 3, $close - ($i + 3)));
				$neg = (substr($inner, 0, 1) === '!') ? 1 : 0;
				$inner = ltrim($inner, '!$');
				$var = preg_replace('/[^A-Za-z0-9_].*$/', '', $inner);
				$out[] = array('t' => 'cond', 'v' => $var, 'label' => fe_var_label($var), 'neg' => $neg);
				$i = $close + 1;
				continue;
			}
		}
		$ch = $besk[$i];
		if ($ch === '$') {
			if ($buf !== '') { $out[] = array('t' => 'text', 'v' => $buf); $buf = ''; }
			$j = $i + 1;
			$tok = '';
			while ($j < $len && (ctype_alnum($besk[$j]) || $besk[$j] === '_')) { $tok .= $besk[$j]; $j++; }
			// optional "(args)" e.g. betalingsid(9,5)
			if ($j < $len && $besk[$j] === '(') {
				$k = strpos($besk, ')', $j);
				if ($k !== false) $j = $k + 1;
			}
			$out[] = array('t' => 'var', 'v' => $tok, 'label' => fe_var_label($tok));
			$i = $j;
			continue;
		}
		if ($ch === ';') { $i++; continue; } // variable terminator - never printed
		$buf .= $ch;
		$i++;
	}
	if ($buf !== '') $out[] = array('t' => 'text', 'v' => $buf);
	return $out;
}

// ---------------------------------------------------------------------------
//  PAGE CHROME (modern top menu, mirrors formularkort.php / logoupload.php)
// ---------------------------------------------------------------------------
$menu = isset($menu) ? $menu : '';
if ($menu == 'T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">\n";
	print "<div class=\"headerbtnLft\">";
	print "<a class='button gray small' href=\"" . htmlspecialchars($returside) . "\">" . findtekst('30|Tilbage', $sprog_id) . "</a> &nbsp;";
	print "<a class='button blue small' href=\"formularkort.php\">" . findtekst('573|Formularkort', $sprog_id) . "</a>";
	print "</div>";
	print "<span class=\"headerTxt\">Formulareditor</span>\n";
	print "<div class=\"headerbtnRght\"></div>";
	print "</div><!-- end of header -->";
	print "<div id=\"leftmenuholder\">";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontentLargeHolder\">\n";
} else {
	// Embedded (inside main.php): a self-contained header styled to match the
	// standard Saldi page header (#header in top_menu.css) without pulling in
	// external CSS, so nothing below it — canvas, rulers, alignment — shifts.
	print "<link href=\"https://fonts.googleapis.com/css2?family=Exo:wght@700;800&display=swap\" rel=\"stylesheet\">";
	print "<div id=\"fe-header\">";
	print "<div class=\"fe-header-side\">";
	print "<a class=\"fe-hbtn fe-hbtn-gray\" href=\"" . htmlspecialchars($returside) . "\">&laquo; " . findtekst('30|Tilbage', $sprog_id) . "</a>";
	print "<a class=\"fe-hbtn fe-hbtn-blue\" href=\"formularkort.php\">" . findtekst('573|Formularkort', $sprog_id) . "</a>";
	print "</div>";
	print "<span class=\"fe-header-title\">" . htmlspecialchars($title) . "</span>";
	print "<div class=\"fe-header-side\"></div>";
	print "</div>";
}
?>
<style>
  #fe-wrap { font-family: Arial, Helvetica, sans-serif; }
  /* Saldi-style page header — matches the modern navy chrome (sidebar_style.css:
     #114691 / #1b54a4). Self-contained so nothing below it shifts. */
  #fe-header { display:flex; align-items:center; background:#114691; color:#fff; padding:5px 12px; }
  #fe-header .fe-header-side { flex:0 0 auto; display:flex; align-items:center; gap:8px; }
  #fe-header .fe-header-title { flex:1 1 auto; text-align:center; margin:0 8px;
    font-family:'Exo',Tahoma,Arial,sans-serif; text-transform:uppercase; font-weight:800; font-size:16px; letter-spacing:.5px; }
  #fe-header .fe-hbtn { display:inline-block; text-decoration:none; color:#fff; font-size:12px; line-height:1.2;
    padding:5px 11px; border-radius:5px; font-family:'Montserrat',Arial,Helvetica,sans-serif;
    background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.25); }
  #fe-header .fe-hbtn:hover { background:rgba(255,255,255,.22); }
  #fe-header .fe-hbtn-blue { background:#1b54a4; border-color:#2a63b5; }
  #fe-header .fe-hbtn-blue:hover { background:#2560c0; }
  #fe-toolbar {
    display:flex; align-items:center; flex-wrap:wrap; gap:10px;
    padding:8px 12px; background:#f2f4f8; border-bottom:1px solid #d3d8e0;
    position:sticky; top:0; z-index:20;
  }
  #fe-toolbar label { font-size:12px; color:#333; }
  #fe-toolbar select, #fe-toolbar input[type=number] { font-size:13px; padding:2px 4px; }
  #fe-toolbar .sep { width:1px; height:22px; background:#c7cdd6; }
  #fe-main { display:flex; align-items:flex-start; gap:0; position:relative; }
  #fe-canvas-area { position:relative; flex:1 1 auto; height:calc(100vh - 190px); min-height:420px; }
  #fe-canvas-scroll {
    position:absolute; inset:0; overflow:auto; background:#8a90a0; padding:24px;
  }
  #fe-canvas-area.fe-rulers #fe-canvas-scroll { top:18px; left:18px; }
  #fe-page {
    position:relative; background:#fff; margin:0 auto;
    box-shadow:0 3px 14px rgba(0,0,0,.35);
  }
  #fe-page .fe-bg { position:absolute; inset:0; width:100%; height:100%; opacity:.9; pointer-events:none; }
  #fe-page.fe-bg-dim .fe-bg { opacity:.12 !important; }
  #fe-grid { position:absolute; inset:0; pointer-events:none !important; }
  #fe-ruler-h { position:absolute; top:0; left:18px; right:0; height:18px; z-index:8; pointer-events:none;
    background:#eef1f6; border-bottom:1px solid #d3d8e0; }
  #fe-ruler-v { position:absolute; top:18px; left:0; bottom:0; width:18px; z-index:8; pointer-events:none;
    background:#eef1f6; border-right:1px solid #d3d8e0; }
  #fe-ruler-corner { position:absolute; top:0; left:0; width:18px; height:18px; z-index:9;
    background:#e2e6ec; border-right:1px solid #d3d8e0; border-bottom:1px solid #d3d8e0; }
  .fe-rubber { position:absolute; z-index:35; border:1px dashed #1769ff;
    background:rgba(23,105,255,.08); pointer-events:none; }
  #fe-align-bar { position:absolute; z-index:40; top:8px; left:50%; transform:translateX(-50%);
    display:flex; align-items:center; gap:3px; background:#fff; border:1px solid #c7cdd6;
    border-radius:8px; box-shadow:0 4px 16px rgba(0,0,0,.18); padding:5px 8px; font-family:Arial,Helvetica,sans-serif; }
  #fe-align-bar .fe-ab-btn { min-width:27px; height:26px; border:1px solid #d3d8e0; background:#f6f8fb;
    border-radius:5px; cursor:pointer; font-size:13px; color:#334; line-height:1; }
  #fe-align-bar .fe-ab-btn:hover { background:#e9f0ff; border-color:#9db4e6; }
  #fe-align-bar .fe-ab-sep { width:1px; height:20px; background:#d3d8e0; margin:0 3px; }
  #fe-align-bar .fe-ab-count { font-size:11px; color:#667; margin-right:4px; white-space:nowrap; }
  #fe-fields-drawer { position:absolute; z-index:39; top:8px; left:8px; width:234px; max-height:calc(100vh - 240px);
    background:#fff; border:1px solid #c7cdd6; border-radius:8px; box-shadow:0 6px 22px rgba(0,0,0,.18);
    display:flex; flex-direction:column; font-family:Arial,Helvetica,sans-serif; }
  #fe-fields-drawer .fe-fd-head { display:flex; align-items:center; justify-content:space-between;
    padding:8px 10px; border-bottom:1px solid #e2e6ec; font-weight:bold; font-size:13px; color:#223; }
  #fe-fields-drawer .fe-fd-head .fe-x { font-size:20px; }
  #fe-fd-search { margin:8px 10px 6px; padding:5px 8px; border:1px solid #d3d8e0; border-radius:5px; font-size:12px; }
  #fe-fd-list { overflow:auto; padding:0 6px 8px; }
  .fe-fd-item { display:flex; align-items:center; gap:7px; padding:5px 6px; border-radius:5px; cursor:pointer; font-size:12px; color:#334; }
  .fe-fd-item:hover { background:#eef3ff; }
  .fe-fd-item.active { background:#dbe7ff; }
  .fe-fd-ic { width:16px; text-align:center; color:#8894ad; font-size:11px; flex:0 0 16px; }
  .fe-fd-lbl { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .fe-fd-empty { color:#8894ad; font-size:12px; padding:8px 8px; }
  #fe-help-modal { position:fixed; inset:0; z-index:9998; background:rgba(20,26,40,.45);
    display:flex; align-items:center; justify-content:center; }
  .fe-help-cols { display:flex; gap:22px; flex-wrap:wrap; }
  .fe-help-cols > div { flex:1 1 220px; }
  .fe-help-cols h4 { margin:0 0 8px; font-size:13px; color:#223; }
  .fe-help-tbl { width:100%; border-collapse:collapse; font-size:12px; color:#334; }
  .fe-help-tbl td { padding:4px 6px; border-bottom:1px solid #eef1f6; vertical-align:top; }
  .fe-help-tbl td.k { white-space:nowrap; color:#1746a0; font-weight:bold; width:46%; }
  #fe-tour-hi { position:fixed; z-index:10000; border-radius:6px; pointer-events:none;
    box-shadow:0 0 0 9999px rgba(15,20,30,.55); transition:left .2s, top .2s, width .2s, height .2s; }
  #fe-tour-tip { position:fixed; z-index:10001; background:#fff; border-radius:9px; width:290px;
    box-shadow:0 10px 34px rgba(0,0,0,.32); padding:14px 16px 12px; font-family:Arial,Helvetica,sans-serif;
    font-size:13px; color:#223; }
  #fe-tour-tip .fe-tour-body { color:#445; line-height:1.5; margin-bottom:12px; }
  #fe-tour-tip .fe-tour-foot { display:flex; align-items:center; justify-content:space-between; gap:8px; }
  #fe-tour-tip .fe-tour-dots { font-size:11px; color:#98a2b3; }
  .fe-tour-btn { font-size:12px; padding:5px 12px; border-radius:6px; cursor:pointer; border:1px solid #c7cdd6; background:#fff; color:#334; }
  .fe-tour-btn.primary { background:#1769ff; color:#fff; border-color:#1257cc; }
  .fe-tour-skip { position:absolute; top:9px; right:11px; font-size:11px; color:#98a2b3; cursor:pointer; background:none; border:none; }
  .fe-tour-skip:hover { color:#556; }
  #fe-ctx {
    position:fixed; z-index:9999; background:#fff; border:1px solid #c7cdd6; border-radius:6px;
    box-shadow:0 6px 22px rgba(0,0,0,.22); padding:4px 0; min-width:150px;
    font-family:Arial,Helvetica,sans-serif; font-size:13px;
  }
  #fe-ctx .fe-ctx-item { padding:6px 14px; cursor:pointer; color:#223; white-space:nowrap; }
  #fe-ctx .fe-ctx-item:hover { background:#eef3ff; }
  #fe-ctx .fe-ctx-item.danger { color:#b23b3b; }
  #fe-ctx .fe-ctx-item.danger:hover { background:#fdeaea; }
  .fe-guide { position:absolute; pointer-events:none; z-index:30; }
  .fe-guide.v { top:0; bottom:0; width:0; border-left:1px dashed #e0359b; }
  .fe-guide.h { left:0; right:0; height:0; border-top:1px dashed #e0359b; }
  .fe-guide.fe-join { width:10px; height:10px; margin:-5px 0 0 -5px; border-radius:50%;
    background:#e0359b; border:2px solid #fff; box-shadow:0 0 0 1px #e0359b; }
  .fe-lhandle {
    position:absolute; width:11px; height:11px; margin:-6px 0 0 -6px;
    background:#1769ff; border:2px solid #fff; border-radius:2px;
    box-shadow:0 1px 3px rgba(0,0,0,.3); z-index:31; cursor:pointer;
  }
  .fe-el {
    position:absolute; box-sizing:border-box; white-space:nowrap;
    cursor:move; padding:0; line-height:1;
    border:1px solid transparent;
  }
  .fe-el.fe-text:hover { outline:1px dashed #6a8; }
  .fe-el.fe-selected { outline:2px solid #1769ff !important; background:rgba(23,105,255,.06); }

  .fe-chip {
    background:#dde8ff; color:#1746a0; border-radius:2px;
    padding:0; margin:0; border:0; font-size:inherit; line-height:inherit;
    box-decoration-break:clone; -webkit-box-decoration-break:clone;
  }

  .fe-cond { position:relative; }
  .fe-cond-badge {
    position:absolute; left:0; top:-0.72em; font-size:9px; line-height:1;
    background:#fff4d6; color:#8a5b00; border:1px solid #d9a441; border-radius:2px;
    padding:0 2px; white-space:nowrap; cursor:help; z-index:5;
  }

  #fe-table-el {
    position:absolute; box-sizing:border-box; pointer-events:none;
    border:1px dashed rgba(23,105,255,.45); background:transparent;
  }
  #fe-table-el:hover { border-color:rgba(23,105,255,.8); background:transparent; }
 
  #fe-table-el.fe-selected { border:1.5px solid #1769ff; background:transparent; box-shadow:0 0 0 2px rgba(23,105,255,.30); }
  #fe-table-el .fe-tcol { position:absolute; top:0; bottom:0; border-left:1px dashed #a9c2ef; }
  #fe-table-el .fe-tdiv { position:absolute; top:0; bottom:0; width:9px; margin-left:-4px; cursor:col-resize; z-index:4; pointer-events:auto; }
  #fe-table-el .fe-tdiv-grip { position:absolute; top:-6px; left:50%; margin-left:-5px; width:10px; height:10px; background:#1769ff; border:2px solid #fff; border-radius:2px; box-shadow:0 1px 3px rgba(0,0,0,.3); }
  #fe-table-el .fe-tdiv:hover { background:rgba(23,105,255,.10); }
  #fe-table-el .fe-tlabel {
    position:absolute; top:1px; font-size:9px; color:#1746a0; white-space:nowrap;
    background:rgba(255,255,255,.6); padding:0 2px; pointer-events:none;
  }
  #fe-table-el .fe-trow { position:absolute; left:0; right:0; border-top:1px solid rgba(23,105,255,.08); pointer-events:none; }
  #fe-table-el .fe-thandle {
    position:absolute; width:13px; height:13px; background:#1769ff; border:2px solid #fff;
    border-radius:50%; box-shadow:0 1px 3px rgba(0,0,0,.3); display:none; pointer-events:auto;
  }
  #fe-table-el.fe-selected .fe-thandle { display:block; }   /* handles only when selected */
  #fe-table-el .fe-thandle.bottom { left:50%; bottom:-7px; margin-left:-6px; cursor:ns-resize; }
  #fe-table-el .fe-thandle.right  { top:50%; right:-7px; margin-top:-6px; cursor:ew-resize; }
  /* the label IS the drag handle for moving the whole table */
  #fe-table-el .fe-tcaption {
    position:absolute; top:-17px; left:0; font-size:10px; color:#fff; font-weight:bold;
    white-space:nowrap; pointer-events:auto; cursor:move; background:#1769ff;
    padding:1px 6px; border-radius:3px 3px 0 0;
  }
  #fe-table-el .fe-tcaption::before { content:"\2725  "; }  /* move grip */
  .fe-logo {
    background:repeating-linear-gradient(45deg,#eef1f6,#eef1f6 6px,#e3e8f0 6px,#e3e8f0 12px);
    border:1px dashed #8894ad; color:#556; display:flex; align-items:center; justify-content:center;
    font-size:11px; min-width:70px; min-height:34px;
  }
  .fe-logo-rz {
    position:absolute; right:-6px; bottom:-6px; width:12px; height:12px;
    background:#1769ff; border:2px solid #fff; border-radius:2px; cursor:nwse-resize;
    box-shadow:0 1px 3px rgba(0,0,0,.3);
  }
 
  .fe-logo-ph {
    background:transparent; border:1px dotted #cbd2de; color:#aeb6c4;
    display:flex; align-items:center; justify-content:center;
    font-size:10px; min-width:36px; min-height:18px; opacity:.55;
  }
  .fe-el.fe-logo-ph:hover { border-color:#8894ad; color:#667; opacity:1; }
  #fe-side { width:280px; flex:0 0 280px; background:#fafbfc; border-left:1px solid #d3d8e0;
    height:calc(100vh - 190px); overflow:auto; padding:12px; box-sizing:border-box; }
  #fe-side h3 { margin:0 0 8px; font-size:14px; color:#223; }
  #fe-side .row { display:flex; align-items:center; justify-content:space-between; margin:6px 0; font-size:12px; }
  #fe-side .row input[type=number] { width:80px; }
  #fe-side .muted { color:#888; font-size:12px; }
  #fe-style-presets, #fe-page-align { display:grid; grid-template-columns:1fr 1fr 1fr; gap:4px; }
  .fe-sp { font-size:11px; padding:4px 2px; border:1px solid #d3d8e0; border-radius:5px;
    background:#f6f8fb; color:#334; cursor:pointer; }
  .fe-sp:hover { background:#e9f0ff; border-color:#9db4e6; }
  .fe-sp[disabled] { opacity:.45; cursor:default; }
  .fe-el.fe-locked { cursor:not-allowed; }
  .fe-el.fe-locked::after { content:"\1F512"; position:absolute; top:-8px; right:-8px; font-size:10px; opacity:.8; }
  #fe-page.fe-reveal .fe-el { outline:1px solid rgba(23,105,255,.7) !important; background:rgba(23,105,255,.06) !important; }
  .fe-el.fe-placeholder { outline:1px dashed #c9a227; background:rgba(201,162,39,.06); }
  #fe-tcols { display:flex; flex-direction:column; gap:5px; }
  .fe-tc-row { display:flex; align-items:center; gap:4px; font-size:11px; }
  .fe-tc-name { flex:1; color:#334; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .fe-tc-btn { width:21px; height:21px; border:1px solid #d3d8e0; background:#f6f8fb; border-radius:4px;
    cursor:pointer; font-size:10px; color:#334; padding:0; line-height:1; }
  .fe-tc-btn.on { background:#1769ff; color:#fff; border-color:#1257cc; }
  .fe-tc-btn:hover { border-color:#9db4e6; }
  .fe-tc-color { width:24px; height:21px; padding:0; border:1px solid #d3d8e0; border-radius:4px; cursor:pointer; background:none; }
  #fe-mail-modal { position:fixed; inset:0; z-index:9998; background:rgba(20,26,40,.45); display:flex; align-items:center; justify-content:center; }
  #fe-lang-toggle { display:inline-flex; align-items:center; gap:5px; font-size:13px; color:#556; }
  #fe-lang-toggle .fe-lang-select { font-size:12px; padding:4px 8px; border:1px solid #c7cdd6; border-radius:4px; background:#fff; color:#334; cursor:pointer; }
  #fe-lang-toggle .fe-lang-select:hover { border-color:#9db4e6; }
  #fe-printlang-wrap { display:inline-flex; align-items:center; gap:4px; font-size:12px; color:#556; cursor:pointer; user-select:none; }
  #fe-printlang-wrap input { cursor:pointer; }
  .fe-mail-row { display:flex; align-items:center; gap:10px; margin:9px 0; font-size:13px; color:#334; }
  .fe-mail-row label { width:150px; flex:0 0 auto; color:#556; }
  .fe-mail-row input, .fe-mail-row textarea { flex:1; font-size:13px; padding:5px 8px; border:1px solid #d3d8e0;
    border-radius:5px; box-sizing:border-box; font-family:Arial,Helvetica,sans-serif; }
  .fe-mail-row textarea { resize:vertical; line-height:1.4; }
  .fe-mail-format { display:flex; gap:4px; margin-bottom:5px; flex-wrap:wrap; }
  .fe-mail-format button { min-width:28px; height:26px; border:1px solid #d3d8e0; background:#f6f8fb;
    border-radius:4px; cursor:pointer; font-size:13px; color:#334; padding:0 6px; }
  .fe-mail-format button:hover { background:#e9f0ff; border-color:#9db4e6; }
  .fe-mail-rte { min-height:130px; max-height:300px; overflow:auto; border:1px solid #d3d8e0; border-radius:5px;
    padding:8px 10px; font-size:13px; line-height:1.5; background:#fff; font-family:Arial,Helvetica,sans-serif; }
  .fe-mail-rte:focus { outline:2px solid #9db4e6; }
  #fe-status { font-size:12px; color:#444; margin-left:auto; }
  .fe-btn { font-size:13px; padding:4px 10px; cursor:pointer; }
  .fe-btn.fe-active { background:#1769ff !important; color:#fff !important; border:1px solid #1257cc !important; border-radius:3px; }
  /* carry-over elements (only print on continuation pages): dimmed in edit so
     they don't read as an overlap with the last-page totals */
  #fe-page:not(.fe-preview) .fe-el.fe-carryover { opacity:.4; }
  /* clean look while previewing (Show draft) */
  #fe-page.fe-preview .fe-el { outline:none !important; background:transparent !important; cursor:default; }
  #fe-page.fe-preview .fe-el.fe-selected { outline:none !important; box-shadow:none !important; }
  .fe-toggle { font-size:12px; }

  /* ---- Design / templates modal (Tier 1) --------------------------------- */
  #fe-design-modal { position:fixed; inset:0; z-index:9998; background:rgba(20,26,40,.45);
    display:flex; align-items:center; justify-content:center; }
  .fe-dlg { background:#fff; width:720px; max-width:94vw; max-height:90vh; overflow:auto;
    border-radius:8px; box-shadow:0 12px 48px rgba(0,0,0,.35); font-family:Arial,Helvetica,sans-serif; }
  .fe-dlg-head { display:flex; align-items:center; justify-content:space-between;
    padding:12px 16px; border-bottom:1px solid #e2e6ec; font-size:15px; font-weight:bold; color:#223; }
  .fe-x { border:0; background:transparent; font-size:22px; line-height:1; cursor:pointer; color:#889; padding:0 4px; }
  .fe-x:hover { color:#334; }
  .fe-dlg-body { padding:14px 16px; }
  .fe-dlg-sub { font-size:12px; color:#788; margin:2px 0 12px; line-height:1.45; }
  .fe-theme-cards { display:flex; gap:12px; flex-wrap:wrap; }
  .fe-theme-card { position:relative; flex:1 1 150px; border:1px solid #d5dae2; border-radius:6px; padding:10px;
    cursor:pointer; background:#fbfcfe; text-align:center; transition:border-color .12s, box-shadow .12s, transform .1s; }
  .fe-theme-card:hover { border-color:#9db4e6; transform:translateY(-2px); }
  .fe-theme-card.active { border-color:#1769ff; box-shadow:0 0 0 2px rgba(23,105,255,.25); background:#fff; }
  .fe-theme-card.active::after { content:"\2713"; position:absolute; top:6px; right:8px;
    width:19px; height:19px; line-height:19px; text-align:center; background:#1769ff; color:#fff;
    border-radius:50%; font-size:12px; font-weight:bold; box-shadow:0 1px 3px rgba(0,0,0,.25); }
  .fe-tc-thumb { display:flex; justify-content:center; margin-bottom:8px; }
  .fe-tc-name { font-size:13px; font-weight:bold; color:#223; }
  .fe-tc-desc { font-size:11px; color:#788; margin-top:2px; line-height:1.3; }
  .fe-brand { margin-top:16px; border-top:1px solid #e2e6ec; padding-top:12px; }
  .fe-brand-row { display:flex; align-items:center; gap:10px; margin:8px 0; font-size:13px; color:#334; }
  .fe-brand-row label { width:104px; color:#556; flex:0 0 auto; }
  .fe-swatches { display:flex; gap:6px; flex-wrap:wrap; }
  .fe-swatch { width:20px; height:20px; border-radius:50%; border:1px solid rgba(0,0,0,.18);
    cursor:pointer; transition:transform .1s; }
  .fe-swatch:hover { transform:scale(1.15); }
  .fe-dlg-foot { padding:12px 16px; border-top:1px solid #e2e6ec; text-align:right; }
  .fe-schemes { display:flex; gap:8px; flex-wrap:wrap; margin:2px 0 4px; }
  .fe-scheme { cursor:pointer; border-radius:7px; overflow:hidden; width:66px;
    border:1px solid #d5dae2; background:#fff; transition:border-color .12s, transform .1s; }
  .fe-scheme:hover { border-color:#9db4e6; transform:translateY(-2px); }
  .fe-scheme .sw { height:26px; display:flex; }
  .fe-scheme .sw i { flex:1; display:block; }
  .fe-scheme .sw i.t { flex:0 0 20px; }
  .fe-scheme .nm { font-size:9.5px; color:#556; padding:3px 2px; line-height:1.1;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-align:center; }
  .fe-swatch.active { box-shadow:0 0 0 2px #fff, 0 0 0 4px #1769ff; }
  .fe-brand-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
  .fe-mini-btn { font-size:12px; padding:4px 10px; border:1px solid #c7cdd6; border-radius:14px;
    background:#fff; cursor:pointer; color:#334; white-space:nowrap; transition:border-color .12s, background .12s; }
  .fe-mini-btn:hover { border-color:#9db4e6; background:#f4f8ff; }
  .fe-mini-btn[disabled] { opacity:.45; cursor:default; }
  #fe-font-sample { margin:6px 0 4px 114px; font-size:17px; color:#223; line-height:1.2;
    padding:4px 0; border-bottom:1px dashed #e2e6ec; }
</style>

<div id="fe-wrap">
  <div id="fe-toolbar">
    <form method="get" action="formeditor.php" style="display:flex;gap:8px;align-items:center;margin:0;">
      <label><?php echo findtekst('780|Formularer', $sprog_id); ?></label>
      <select name="form_nr" onchange="this.form.submit()">
        <?php foreach ($forms as $fn => $fl) {
          $selp = ($fn === $form_nr) ? ' selected' : '';
          echo "<option value=\"$fn\"$selp>" . htmlspecialchars($fl) . "</option>";
        } ?>
      </select>
      <label>Bg.<?php echo findtekst('646|Navn', $sprog_id); ?></label>
      <select name="sprog" onchange="this.form.submit()">
        <?php foreach ($variants as $v) {
          $selp = ($v === $sprog) ? ' selected' : '';
          echo "<option value=\"" . htmlspecialchars($v) . "\"$selp>" . htmlspecialchars(fe_bg_display_name($v)) . "</option>";
        } ?>
      </select>
    </form>
    <span class="sep"></span>
    <label>Zoom</label>
    <input type="range" id="fe-zoom" min="50" max="200" value="100" step="5" style="width:110px;">
    <span id="fe-zoom-val" style="font-size:12px;width:38px;">100%</span>
    <button type="button" class="fe-btn" id="fe-zoom-fit" style="border:1px solid #c7cdd6;border-radius:3px;background:#fff;padding:2px 7px;" title="<?php echo $T('Tilpas hele siden i vinduet','Fit the whole page in the window'); ?>"><?php echo $T('Tilpas','Fit'); ?></button>
    <button type="button" class="fe-btn" id="fe-zoom-width" style="border:1px solid #c7cdd6;border-radius:3px;background:#fff;padding:2px 7px;" title="<?php echo $T('Tilpas sidebredden','Fit the page width'); ?>"><?php echo $T('Bredde','Width'); ?></button>
    <button type="button" class="fe-btn" id="fe-zoom-100" style="border:1px solid #c7cdd6;border-radius:3px;background:#fff;padding:2px 7px;" title="100%">100%</button>
    <span class="sep"></span>
    <label class="fe-toggle"><input type="checkbox" id="fe-grid-toggle"> <?php echo $T('Gitter','Grid'); ?> (5mm)</label>
    <label class="fe-toggle"><input type="checkbox" id="fe-ruler-toggle"> <?php echo $T('Linealer','Rulers'); ?></label>
    <button type="button" class="fe-btn" id="fe-fields-btn" style="border:1px solid #c7cdd6;border-radius:3px;background:#fff;" title="<?php echo $T('Vis liste over alle felter – søg og vælg (også skjulte)','List all fields – search and select (even hidden ones)'); ?>">&#9776; <?php echo $T('Felter','Fields'); ?></button>
    <?php if ($bg_url) { ?><label class="fe-toggle"><input type="checkbox" id="fe-bgdim-toggle"> <?php echo $T('Dæmp bg','Dim bg'); ?></label><?php } ?>
    <span class="sep"></span>
    <button type="button" class="fe-btn" id="fe-add-text" title="<?php echo $T('Tilføj tekstfelt','Add text box'); ?>">&#43; <?php echo $T('Tekst','Text'); ?></button>
    <button type="button" class="fe-btn" id="fe-add-line" title="<?php echo $T('Tilføj streg','Add line'); ?>">&#43; <?php echo $T('Streg','Line'); ?></button>
    <span class="sep"></span>
    <button type="button" class="fe-btn" id="fe-design-open" style="border:1px solid #c7cdd6;border-radius:3px;background:#fff;" title="<?php echo $T('Vælg en færdig skabelon (Klassisk/Moderne/Minimal) og brand-farve/skrifttype','Pick a ready-made template (Classic/Modern/Minimal) and brand colour/font'); ?>">&#127912; <?php echo $T('Skabeloner','Templates'); ?></button>
    <?php $bg_label = $bg_url ? $T('Skift baggrund','Change background') : $T('Tilføj baggrund','Add background'); ?>
    <a id="fe-bg-btn" class="fe-btn" style="text-decoration:none;color:inherit;border:1px solid #c7cdd6;border-radius:3px;" href="logoupload.php?upload=yes" target="_blank" rel="noopener" title="<?php echo $T('Upload eller skift baggrund/logo (letterhead)','Upload or change background/logo (letterhead)'); ?>">&#128444; <?php echo htmlspecialchars($bg_label); ?></a>
    <button type="button" class="fe-btn" id="fe-mail-btn" style="border:1px solid #c7cdd6;border-radius:3px;background:#fff;" title="<?php echo $T('Rediger e-mailteksten der sendes med PDF&apos;en','Edit the e-mail text sent with the PDF'); ?>">&#9993; <?php echo $T('E-mailtekst','Email text'); ?></button>
    <label id="fe-lang-toggle" title="<?php echo $T('Skift sproget på alle tekster','Switch the language of all captions'); ?>">&#127760;
      <select id="fe-lang-select" class="fe-lang-select"><option value="da">Dansk</option><option value="en">English</option></select></label>
    <label id="fe-printlang-wrap" title="<?php echo $T('Tving denne formular til altid at udskrive i den valgte sprogversion – uanset kundens sprog','Force this form to always print in the selected language version – regardless of the customer&apos;s language'); ?>"><input type="checkbox" id="fe-printlang"> <?php echo $T('Lås udskrift','Lock print'); ?></label>
    <span class="sep"></span>
    <button type="button" class="fe-btn" id="fe-undo" title="Ctrl+Z">&#8630; <?php echo $T('Fortryd','Undo'); ?></button>
    <button type="button" class="fe-btn" id="fe-redo" title="Ctrl+Y">&#8631; <?php echo $T('Gentag','Redo'); ?></button>
    <span class="sep"></span>
    <button type="button" class="fe-btn" id="fe-preview" title="<?php echo $T('Vis hvordan udskriften kommer til at se ud med eksempeldata','Show how the print will look with sample data'); ?>">&#128065; <?php echo $T('Vis kladde','Show draft'); ?></button>
    <button type="button" class="fe-btn" id="fe-savedraft" title="<?php echo $T('Gem som kladde (går ikke live)','Save as draft (does not go live)'); ?>"><?php echo $T('Gem kladde','Save draft'); ?></button>
    <button type="button" class="fe-btn" id="fe-save" style="background:#1769ff;color:#fff;border:1px solid #1257cc;border-radius:3px;" title="<?php echo $T('Gem og gør aktiv (bruges ved udskrift)','Save and make live (used for printing)'); ?>"><?php echo $T('Gem & aktivér','Save & activate'); ?></button>
    <span class="sep"></span>
    <button type="button" class="fe-btn" id="fe-reset" style="color:#b23b3b;border:1px solid #d9a1a1;border-radius:3px;background:#fff;" title="<?php echo $T('Nulstil denne formular til Saldis standard','Reset this form to Saldi&apos;s standard'); ?>">&#8635; <?php echo $T('Nulstil','Reset'); ?></button>
    <button type="button" class="fe-btn" id="fe-help-btn" style="border:1px solid #c7cdd6;border-radius:50%;background:#fff;width:26px;height:26px;padding:0;font-weight:bold;color:#556;" title="<?php echo $T('Hjælp og tastaturgenveje','Help &amp; keyboard shortcuts'); ?>">?</button>
    <span id="fe-status"></span>
  </div>

  <div id="fe-draft-banner" style="display:none;align-items:center;gap:12px;padding:8px 14px;background:#fff8e1;border-bottom:1px solid #e6cf8b;color:#7a5a00;font-family:Arial,Helvetica,sans-serif;font-size:13px;">
    <span id="fe-draft-text"></span>
    <button type="button" id="fe-draft-continue" class="fe-btn" style="background:#1769ff;color:#fff;border:1px solid #1257cc;border-radius:3px;"><?php echo $T('Fortsæt kladde','Continue draft'); ?></button>
    <button type="button" id="fe-draft-discard" class="fe-btn" style="border:1px solid #d9a1a1;color:#b23b3b;background:#fff;border-radius:3px;"><?php echo $T('Kassér kladde','Discard draft'); ?></button>
  </div>

  <div id="fe-design-modal" style="display:none;">
    <div class="fe-dlg">
      <div class="fe-dlg-head">
        <span>&#127912; <?php echo $T('Skabeloner &amp; brand','Templates &amp; brand'); ?></span>
        <button type="button" id="fe-design-close" class="fe-x" title="<?php echo $T('Luk','Close'); ?>">&times;</button>
      </div>
      <div class="fe-dlg-body">
        <div class="fe-dlg-sub" style="margin-bottom:10px;"><?php echo $T('Vælg et udseende – det anvendes med det samme og kan fortrydes (Ctrl+Z). Intet går live før du trykker “Gem &amp; aktivér”.', 'Pick a look – it is applied instantly and can be undone (Ctrl+Z). Nothing goes live until you press “Save &amp; activate”.'); ?></div>
        <div id="fe-theme-cards" class="fe-theme-cards"></div>
        <div class="fe-brand">
          <div class="fe-dlg-sub" style="margin:2px 0 6px;font-weight:bold;color:#556;"><?php echo $T('Hurtige farvesæt (accent + tekst)','Quick colour schemes (accent + text)'); ?></div>
          <div id="fe-schemes" class="fe-schemes"></div>
          <div class="fe-brand-row">
            <label><?php echo $T('Accent (overskrifter)','Accent (headings)'); ?></label>
            <input type="color" id="fe-brand-accent" value="#000066">
            <span class="fe-swatches" id="fe-brand-swatches"></span>
            <button type="button" id="fe-brand-fromlogo" class="fe-mini-btn" title="<?php echo $T('Hent accentfarven fra dit uploadede logo','Pick the accent colour from your uploaded logo'); ?>">&#127919; <?php echo $T('Fra logo','From logo'); ?></button>
          </div>
          <div class="fe-brand-row">
            <label><?php echo $T('Tekst (brødtekst)','Text (body)'); ?></label>
            <input type="color" id="fe-brand-text" value="#2b2b2b">
            <span class="fe-swatches" id="fe-brand-text-swatches"></span>
            <button type="button" id="fe-text-match" class="fe-mini-btn" title="<?php echo $T('Sæt tekstfarven til en læsbar mørk nuance af accentfarven','Set the text colour to a readable dark shade of the accent'); ?>">&#127760; <?php echo $T('Match accent','Match accent'); ?></button>
          </div>
          <div class="fe-brand-row">
            <label><?php echo $T('Skrifttype','Font'); ?></label>
            <select id="fe-brand-font">
              <option value="Helvetica">Helvetica (sans-serif)</option>
              <option value="Times">Times (serif)</option>
              <option value="Palatino">Palatino (serif)</option>
              <option value="NewCenturySchlbk">Century Schoolbook (serif)</option>
              <option value="Courier">Courier (<?php echo $T('fast bredde','monospace'); ?>)</option>
              <option value="Ocrbb12">OCR-B (<?php echo $T('teknisk','technical'); ?>)</option>
            </select>
          </div>
          <div id="fe-font-sample">Aa Bb Cc &ndash; 1.250,00 kr</div>
          <div class="fe-dlg-sub" style="margin-bottom:0;"><?php echo $T('Accent farver overskrifterne; tekstfarven farver etiketter, værdier og ordrelinjer. Skrifttypen gælder al tekst.','Accent colours the headings; the text colour colours labels, values and order lines. The font applies to all text.'); ?></div>
        </div>
      </div>
      <div class="fe-dlg-foot">
        <button type="button" id="fe-design-done" class="fe-btn" style="background:#1769ff;color:#fff;border:1px solid #1257cc;border-radius:3px;"><?php echo $T('Luk','Close'); ?></button>
      </div>
    </div>
  </div>

  <div id="fe-main">
    <div id="fe-canvas-area">
      <!-- sticky rulers: pinned to the visible canvas edges (drawn from scroll pos) -->
      <div id="fe-ruler-corner" style="display:none;"></div>
      <canvas id="fe-ruler-h" style="display:none;"></canvas>
      <canvas id="fe-ruler-v" style="display:none;"></canvas>
      <div id="fe-canvas-scroll">
        <div id="fe-page">
          <?php if ($bg_url) { echo '<img class="fe-bg" src="' . htmlspecialchars($bg_url) . '" alt="">'; } ?>
          <svg id="fe-lines" style="position:absolute;inset:0;pointer-events:none;overflow:visible;"></svg>
          <canvas id="fe-grid"></canvas>
          <!-- elements injected by JS -->
        </div>
      </div>
    </div>

    <div id="fe-side">
      <h3><?php echo $T('Egenskaber','Properties'); ?></h3>
      <div id="fe-noselect" class="muted"><?php echo $T('Klik på et element for at redigere det. Træk for at flytte. Piletaster: 0,5&nbsp;mm (Shift: 5&nbsp;mm).', 'Click an element to edit it. Drag to move. Arrow keys: 0.5&nbsp;mm (Shift: 5&nbsp;mm).'); ?></div>
      <div id="fe-props" style="display:none;">
        <div class="row"><span id="fe-prop-kind" style="font-weight:bold;"></span></div>
        <div class="row"><label>&#128274; <?php echo $T('Lås','Lock'); ?></label><input type="checkbox" id="fe-lock" title="<?php echo $T('Lås så elementet ikke kan flyttes ved et uheld','Lock so it can\'t be moved by accident'); ?>"></div>
        <div class="row" data-text-only style="flex-direction:column;align-items:stretch;gap:4px;">
          <span class="muted" style="font-size:11px;"><?php echo $T('Hurtig stil','Quick style'); ?></span>
          <div id="fe-style-presets">
            <button type="button" class="fe-sp" data-sp="title"><?php echo $T('Titel','Title'); ?></button>
            <button type="button" class="fe-sp" data-sp="heading"><?php echo $T('Overskrift','Heading'); ?></button>
            <button type="button" class="fe-sp" data-sp="label"><?php echo $T('Etiket','Label'); ?></button>
            <button type="button" class="fe-sp" data-sp="body"><?php echo $T('Brødtekst','Body'); ?></button>
            <button type="button" class="fe-sp" data-sp="total"><?php echo $T('Total','Total'); ?></button>
            <button type="button" class="fe-sp" data-sp="fine"><?php echo $T('Fintryk','Fine print'); ?></button>
          </div>
        </div>
        <div class="row" data-text-only style="flex-direction:column;align-items:stretch;gap:4px;">
          <span class="muted" style="font-size:11px;"><?php echo $T('Placér på siden','Place on page'); ?></span>
          <div id="fe-page-align">
            <button type="button" class="fe-sp" data-pa="left"><?php echo $T('Venstre','Left'); ?></button>
            <button type="button" class="fe-sp" data-pa="center"><?php echo $T('Centrér','Centre'); ?></button>
            <button type="button" class="fe-sp" data-pa="right"><?php echo $T('Højre','Right'); ?></button>
          </div>
        </div>
        <div class="row" data-text-only style="gap:6px;">
          <button type="button" class="fe-sp" id="fe-copystyle" style="flex:1;">&#128203; <?php echo $T('Kopiér stil','Copy style'); ?></button>
          <button type="button" class="fe-sp" id="fe-pastestyle" style="flex:1;" disabled>&#127912; <?php echo $T('Indsæt stil','Paste style'); ?></button>
        </div>
        <div class="row"><label>X (mm)</label><input type="number" step="0.1" id="fe-x"></div>
        <div class="row"><label>Y (mm)</label><input type="number" step="0.1" id="fe-y"></div>
        <div class="row" data-line-only><label>X2 (mm)</label><input type="number" step="0.1" id="fe-x2"></div>
        <div class="row" data-line-only><label>Y2 (mm)</label><input type="number" step="0.1" id="fe-y2"></div>
        <div class="row" data-text-only><label><?php echo $T('Højde','Height'); ?> (pt)</label><input type="number" step="1" id="fe-str"></div>
        <div class="row" data-line-only><label><?php echo $T('Bredde','Width'); ?></label><input type="number" step="1" id="fe-strline"></div>
        <div class="row" data-text-only><label><?php echo $T('Justering','Align'); ?></label>
          <select id="fe-just"><option value="V"><?php echo $T('V (venstre)','L (left)'); ?></option><option value="C"><?php echo $T('C (center)','C (center)'); ?></option><option value="H"><?php echo $T('H (højre)','R (right)'); ?></option></select>
        </div>
        <div class="row" data-text-only><label><?php echo $T('Fed','Bold'); ?></label><input type="checkbox" id="fe-fed"></div>
        <div class="row" data-text-only><label><?php echo $T('Kursiv','Italic'); ?></label><input type="checkbox" id="fe-kursiv"></div>
        <div class="row" data-text-only><label><?php echo $T('Farve','Colour'); ?></label><input type="color" id="fe-color"></div>
        <div class="row" data-logo-only><label><?php echo $T('Bredde','Width'); ?> (mm)</label><input type="number" step="0.5" id="fe-lw"></div>
        <div class="row" data-logo-only><label><?php echo $T('Højde','Height'); ?> (mm)</label><input type="number" step="0.5" id="fe-lh"></div>
        <div class="row" data-logo-only style="flex-direction:column;align-items:stretch;">
          <button type="button" id="fe-logo-upload" style="border:1px solid #1257cc;background:#1769ff;color:#fff;border-radius:3px;padding:4px 10px;cursor:pointer;font-size:13px;">&#8593; <?php echo $T('Upload logo','Upload logo'); ?></button>
          <input type="file" id="fe-logo-file" accept="image/png,image/jpeg" style="display:none;">
          <button type="button" id="fe-logo-remove" style="border:1px solid #c0392b;background:#fff;color:#c0392b;border-radius:3px;padding:4px 10px;cursor:pointer;font-size:13px;margin-top:5px;">&#10005; <?php echo $T('Fjern logo','Remove logo'); ?></button>
          <span class="muted" style="font-size:10.5px;line-height:1.3;margin-top:4px;"><?php echo $T('PNG/JPG, maks 5 MB. Træk hjørnet for at skalere.','PNG/JPG, max 5 MB. Drag the corner to resize.'); ?></span>
        </div>
        <div class="row" data-text-only><span class="muted" style="font-size:11px;"><?php echo $T('Indhold','Content'); ?>:</span></div>
        <div class="row" data-text-only><div id="fe-content" style="text-align:left;width:100%;min-height:16px;padding:2px 0;"></div></div>
        <div class="row" data-text-only><textarea id="fe-content-edit" rows="2" spellcheck="false" style="width:100%;font-size:12px;box-sizing:border-box;"></textarea></div>
        <div class="row" data-text-only>
          <select id="fe-var-picker" style="width:100%;font-size:12px;">
            <option value="">&#43; <?php echo $T('Indsæt felt…','Insert field…'); ?></option>
            <?php
              // Build the human-readable variable picker, grouped by topic.
              $groups = array(
                'ordre_lev_' => $T('Levering','Delivery'),
                'ordre_'     => $T('Kunde','Customer'),
                'eget_'      => $T('Eget firma','Own company'),
                'egen_'      => $T('Eget firma','Own company'),
                'ansat_'     => $T('Sælger','Seller'),
                'konto_'     => $T('Konto','Account'),
                'formular_'  => $T('Beløb & dokument','Amounts & document'),
                'forfalden_' => $T('Beløb & dokument','Amounts & document'),
                'rykker_'    => $T('Beløb & dokument','Amounts & document'),
                'levering_'  => $T('Beløb & dokument','Amounts & document'),
                'afdeling_'  => $T('Beløb & dokument','Amounts & document'),
              );
              $buckets = array();
              foreach (fe_var_map() as $tok => $pair) {
                // skip bare order-line column keys (they belong to the table)
                if (strpos($tok, '_') === false) continue;
                $grp = null;
                foreach ($groups as $pre => $label) { if (strpos($tok, $pre) === 0) { $grp = $label; break; } }
                if ($grp === null) continue;
                $buckets[$grp][$tok] = $dk_ui ? $pair[0] : $pair[1];
              }
              // preserve a friendly group order
              $order = array($T('Kunde','Customer'), $T('Levering','Delivery'), $T('Eget firma','Own company'), $T('Sælger','Seller'), $T('Konto','Account'), $T('Beløb & dokument','Amounts & document'));
              $seen = array();
              foreach ($order as $glabel) {
                if (empty($buckets[$glabel]) || isset($seen[$glabel])) continue;
                $seen[$glabel] = 1;
                echo '<optgroup label="' . htmlspecialchars($glabel) . '">';
                asort($buckets[$glabel]);
                foreach ($buckets[$glabel] as $tok => $lab) {
                  echo '<option value="$' . htmlspecialchars($tok) . '">' . htmlspecialchars($lab) . '</option>';
                }
                echo '</optgroup>';
              }
            ?>
          </select>
        </div>
        <div class="row" data-text-only><span class="muted" style="font-size:10.5px;line-height:1.3;"><?php echo $T('Rediger teksten frit. Behold felter der starter med $ (fx $ordre_ordrenr) for at bevare data.','Edit the text freely. Keep fields starting with $ (e.g. $ordre_ordrenr) to keep the data.'); ?></span></div>
        <div class="row" style="margin-top:10px;border-top:1px solid #e2e6ec;padding-top:8px;">
          <button type="button" id="fe-delete" style="color:#b23b3b;border:1px solid #d9a1a1;background:#fff;border-radius:3px;padding:3px 10px;cursor:pointer;font-size:13px;">&#128465; <?php echo $T('Slet element','Delete element'); ?></button>
          <span id="fe-core-note" class="muted" style="display:none;font-size:11px;">&#128274; <?php echo $T('Kerneelement – kan ikke slettes','Core element – cannot be deleted'); ?></span>
        </div>
      </div>
      <div id="fe-tprops" style="display:none;">
        <div class="row"><span style="font-weight:bold;"><?php echo $T('Ordrelinje-tabel','Order-line table'); ?></span></div>
        <div class="row"><span class="muted" style="font-size:11px;line-height:1.3;"><?php echo $T('Træk tabellen for at flytte den, og træk det nederste håndtag for at gøre den højere/lavere. Træk kolonne-skillelinjerne for at flytte kolonner.','Drag the table to move it, and drag the bottom handle to make it taller/shorter. Drag the column dividers to move columns.'); ?></span></div>
        <div class="row" style="flex-direction:column;align-items:stretch;gap:5px;margin-top:8px;">
          <span class="muted" style="font-size:11px;"><?php echo $T('Kolonner (justering + fed)','Columns (alignment + bold)'); ?></span>
          <div id="fe-tcols"></div>
        </div>
        <details style="margin-top:6px;">
          <summary style="cursor:pointer;font-size:12px;color:#556;"><?php echo $T('Avanceret (præcise tal)','Advanced (exact numbers)'); ?></summary>
          <div class="row" style="margin-top:6px;"><label><?php echo $T('Antal linjer','Number of rows'); ?></label><input type="number" step="1" min="1" id="fe-trows"></div>
          <div class="row"><label><?php echo $T('Linjeafstand (mm)','Row spacing (mm)'); ?></label><input type="number" step="0.1" id="fe-tspace"></div>
          <div class="row"><label><?php echo $T('Top Y (mm)','Top Y (mm)'); ?></label><input type="number" step="0.1" id="fe-ttopy"></div>
        </details>
        <div class="row" style="margin-top:8px;"><span class="muted" style="font-size:11px;">&#128274; <?php echo $T('Kerneelement – kan ikke slettes','Core element – cannot be deleted'); ?></span></div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  "use strict";
  var PAGE_W = 210, PAGE_H = 297;      // mm
  var PT_TO_MM = 0.352777;             // 1 pt in mm
  var BASE_PXMM = 3.2;                 // px per mm at 100%
  var zoom = 1.0;
  var grid = false;
  var rulersOn = false;
  var previewMode = false;   // "Show draft": fill variables with sample data
  // Sample values used only for the on-screen preview (Spec FR-3.1 sample data).
  var SAMPLE = {
    ordre_firmanavn:'Acme Trading ApS', ordre_addr1:'Nørregade 12', ordre_addr2:'2. sal',
    ordre_postnr:'1165', ordre_bynavn: (DK_UI?'København K':'Copenhagen'), ordre_land:(DK_UI?'Danmark':'Denmark'),
    ordre_kontakt:'Jens Hansen', ordre_cvrnr:'12345678', ordre_email:'info@acme.dk', ordre_tlf:'+45 33 12 34 56',
    ordre_ordrenr:'10042', ordre_ordredate:'14-07-2026', ordre_fakturanr:'2026-0042', ordre_fakturadate:'14-07-2026',
    ordre_kundenr:'1001', ordre_kundeordnr:'PO-5567', ordre_projekt:'P-2026-07', ordre_valuta:'DKK',
    ordre_momssats:'25', ordre_betalingsbet:(DK_UI?'Netto 8 dage':'Net 8 days'), ordre_betalingsdage:'8',
    ordre_institution:'', ordre_ean:'5790000012345', ordre_hvem:'Jens Hansen', ordre_notes:'',
    ordre_lev_navn:'Acme Warehouse', ordre_lev_addr1:'Havnevej 5', ordre_lev_addr2:'',
    ordre_lev_postnr:'2100', ordre_lev_bynavn:(DK_UI?'København Ø':'Copenhagen'), ordre_lev_kontakt:'Lars Lager',
    ordre_levdate:'16-07-2026',
    eget_firmanavn:'Havemøbelland ApS', egen_addr1:'Fredgårdsvej 2', egen_addr2:'', eget_postnr:'3200',
    eget_bynavn:'Helsinge', eget_land:(DK_UI?'Danmark':'Denmark'), eget_cvrnr:'87654321',
    egen_tlf:'8877 8288', egen_mobile:'2222 3333', egen_email:'info@havemoebelland.dk', egen_web:'www.havemoebelland.dk',
    egen_bank_navn:'Danske Bank', egen_bank_reg:'1234', egen_bank_konto:'5678901234',
    ansat_navn:'Peter Sælger', ansat_initialer:'PS', ansat_email:'ps@havemoebelland.dk', ansat_tlf:'8877 8290', ansat_mobil:'2011 2233',
    konto_firmanavn:'Acme Trading ApS', konto_addr1:'Nørregade 12', konto_postnr:'1165', konto_bynavn:'København K', konto_cvrnr:'12345678',
    formular_ialt:'12.500,00', formular_moms:'2.500,00', formular_momsgrundlag:'10.000,00',
    formular_side:'1', formular_nextside:'2', formular_preside:'0', formular_transportsum:'0,00',
    formular_forfaldsdato:'22-07-2026', formular_betalingsid:'71000000123456', formular_grossWeight:'12,50', formular_netWeight:'10,00',
    forfalden_sum:'12.500,00', rykker_gebyr:'100,00'
  };
  // sample values for order-line columns, per sample row
  var COL_SAMPLE = {
    posnr:['1','2','3'], varenr:['VN-100','VN-205','VN-330'], lev_varenr:['LV-1','LV-2','LV-3'],
    beskrivelse:[(DK_UI?'Kontorstol, sort':'Office chair, black'),(DK_UI?'Skrivebord 160 cm':'Desk 160 cm'),(DK_UI?'Bordlampe':'Desk lamp')],
    antal:['2','1','4'], enhed:['stk','stk','stk'], pris:['1.250,00','3.400,00','450,00'],
    rabat:['0','10','0'], momssats:['25','25','25'], linjesum:['2.500,00','3.060,00','1.800,00'],
    varemomssats:['25','25','25'], linjemoms:['625,00','765,00','450,00'], projekt:['P-07','P-07','P-07'], lokation:['A-1','A-2','B-1']
  };
  var GRID_MM = 5;
  var MARGIN_MM = 10;   // standard page margin (guides + snap)

  var elements = <?php echo json_encode($fe_data); ?> || [];
  var table    = <?php echo json_encode($fe_table); ?>;
  var tableInit = table ? JSON.parse(JSON.stringify(table)) : null;   // pristine copy for "Reset table"
  var tableGroupInit = [];   // pristine geometry of the table's headers/border lines (filled at init)
  var VAR_MAP  = <?php echo json_encode(fe_var_map()); ?> || {};
  var DK_UI    = <?php echo $dk_ui ? 'true' : 'false'; ?>;
  var LOGO_URL = <?php echo json_encode($fe_logo_url); ?>;
  var LOGO_NAT = { w: <?php echo (int) $fe_logo_w; ?>, h: <?php echo (int) $fe_logo_h; ?> };
  var FE_DRAFT = <?php echo json_encode($fe_draft); ?>;   // saved working state, or null
  var FE_MAIL  = <?php echo json_encode($fe_mail); ?>;    // e-mail text (subject/body/attachment)
  var formNr = <?php echo (int) $form_nr; ?>;
  var sprog  = <?php echo json_encode($sprog); ?>;
  var FE_PRINTLANG = <?php echo json_encode($fe_printlang); ?>;   // sprog this form is forced to print in, or ''
  var COND_TIP = {
    yes: <?php echo json_encode($T('Vises kun hvis "%s" er udfyldt', 'Only shown if "%s" is filled')); ?>,
    no:  <?php echo json_encode($T('Vises kun hvis "%s" er tom', 'Only shown if "%s" is empty')); ?>
  };
  var EMPTY_LBL = <?php echo json_encode($T('(tom)', '(empty)')); ?>;
  var L = {
    text:     <?php echo json_encode($T('Tekst','Text')); ?>,
    line:     <?php echo json_encode($T('Streg','Line')); ?>,
    logo:     <?php echo json_encode($T('Logo','Logo')); ?>,
    unsaved:  <?php echo json_encode($T('Ikke gemte ændringer','Unsaved changes')); ?>,
    saving:   <?php echo json_encode($T('Gemmer…','Saving…')); ?>,
    saved:    <?php echo json_encode($T('Gemt (%s felter)','Saved (%s fields)')); ?>,
    session:  <?php echo json_encode($T('Session udløbet – genindlæs siden','Session expired – reload the page')); ?>,
    failed:   <?php echo json_encode($T('Kunne ikke gemme','Could not save')); ?>,
    neterr:   <?php echo json_encode($T('Netværksfejl','Network error')); ?>,
    noel:     <?php echo json_encode($T('Ingen elementer for denne formular/variant','No elements for this form/variant')); ?>,
    tkind:    <?php echo json_encode($T('Ordrelinje-tabel','Order-line table')); ?>,
    trows:    <?php echo json_encode($T('Antal linjer','Number of rows')); ?>,
    tspace:   <?php echo json_encode($T('Linjeafstand (mm)','Row spacing (mm)')); ?>,
    ttopy:    <?php echo json_encode($T('Top Y (mm)','Top Y (mm)')); ?>,
    resetConfirm: <?php echo json_encode($T(
        'Nulstil DENNE formular og variant til Saldis standardopsætning?\n\nDine ændringer til netop denne formular/variant erstattes permanent. Andre formularer og varianter påvirkes ikke.',
        'Reset THIS form and variant to Saldi\'s standard layout?\n\nYour changes to this form/variant will be permanently replaced. Other forms and variants are not affected.')); ?>,
    resetting: <?php echo json_encode($T('Nulstiller…','Resetting…')); ?>,
    resetDone: <?php echo json_encode($T('Nulstillet – genindlæser…','Reset – reloading…')); ?>,
    resetNoStd: <?php echo json_encode($T('Ingen standard fundet for denne formular','No standard found for this form')); ?>,
    coreCantDelete: <?php echo json_encode($T('Kerneelement – kan ikke slettes','Core element – cannot be deleted')); ?>,
    logoRemoveConfirm: <?php echo json_encode($T('Fjern logoet fra formularen og slet det uploadede billede?','Remove the logo from the form and delete the uploaded image?')); ?>,
    logoRemoving: <?php echo json_encode($T('Fjerner logo…','Removing logo…')); ?>,
    logoRemoved: <?php echo json_encode($T('Logo fjernet','Logo removed')); ?>,
    logoNone: <?php echo json_encode($T('Der er intet logo at fjerne','There is no logo to remove')); ?>,
    logoPlaceholder: <?php echo json_encode($T('+ logo','+ logo')); ?>,
    savingDraft: <?php echo json_encode($T('Gemmer kladde…','Saving draft…')); ?>,
    draftSaved:  <?php echo json_encode($T('Kladde gemt (ikke live)','Draft saved (not live)')); ?>,
    draftFound:  <?php echo json_encode($T('Du har en gemt kladde fra %s.','You have a saved draft from %s.')); ?>,
    draftLoaded: <?php echo json_encode($T('Kladde indlæst – Gem & aktivér for at gå live','Draft loaded – Save & activate to go live')); ?>,
    ctxDuplicate: <?php echo json_encode($T('Dupliker','Duplicate')); ?>,
    ctxDelete: <?php echo json_encode($T('Slet','Delete')); ?>,
    ctxRemoveLogo: <?php echo json_encode($T('Fjern logo','Remove logo')); ?>,
    ctxAddText: <?php echo json_encode($T('Tilføj tekst her','Add text here')); ?>,
    ctxAddLine: <?php echo json_encode($T('Tilføj streg (tegn)','Add line (draw)')); ?>,
    ctxWidthPlus:  <?php echo json_encode($T('Tykkere streg (+1)','Thicker line (+1)')); ?>,
    ctxWidthMinus: <?php echo json_encode($T('Tyndere streg (−1)','Thinner line (−1)')); ?>,
    ctxMeet:  <?php echo json_encode($T('Ret ind til nærmeste streger','Snap ends to nearest lines')); ?>,
    ctxAlignL: <?php echo json_encode($T('Justér venstre','Align left')); ?>,
    ctxAlignC: <?php echo json_encode($T('Justér centreret','Align centre')); ?>,
    ctxAlignR: <?php echo json_encode($T('Justér højre','Align right')); ?>
  };
  var TABLE_CAP       = <?php echo json_encode($T('Ordrelinje-tabel','Order-line table')); ?>;
  var ROWS_WORD       = <?php echo json_encode($T('linjer','rows')); ?>;
  var NEW_TEXT        = <?php echo json_encode($T('Ny tekst','New text')); ?>;
  var DRAW_LINE_HINT  = <?php echo json_encode($T('Tegn en streg: klik og træk på arket','Draw a line: click and drag on the sheet')); ?>;
  var AUTO_TR_MSG = <?php echo json_encode($T('Etiketter vist på dansk – klik Gem for at bevare','Captions shown in English – click Save to keep')); ?>;
  var PREVIEW_ON  = <?php echo json_encode($T('Forhåndsvisning med eksempeldata – klik igen for at redigere','Preview with sample data – click again to edit')); ?>;
  var COL_DRAG_TIP    = <?php echo json_encode($T('Træk for at flytte kolonnen','Drag to move this column')); ?>;
  var RESIZE_ROWS_TIP = <?php echo json_encode($T('Træk for at ændre antal linjer','Drag to change number of rows')); ?>;
  var RESIZE_WIDTH_TIP= <?php echo json_encode($T('Træk for at ændre bredden','Drag to change width')); ?>;

  var page   = document.getElementById('fe-page');
  var svg    = document.getElementById('fe-lines');
  var gridC  = document.getElementById('fe-grid');
  var rulerH = document.getElementById('fe-ruler-h');
  var rulerV = document.getElementById('fe-ruler-v');
  var rulerCorner = document.getElementById('fe-ruler-corner');
  var statusEl = document.getElementById('fe-status');
  var selId = null;
  var selSet = [];   // multi-selection (element ids)
  var undoStack = [];
  var redoStack = [];
  var deletedIds = [];   // ids removed in this session, applied on Save
  var nextTmpId = -1;    // temp ids for newly-added elements (until saved)

  function pxmm() { return BASE_PXMM * zoom; }

  // ---- colour helpers (match includes/formfunk.php encoding) --------------
  function colorNumToRgb(num) {
    num = Math.max(0, Math.round(num));
    var s = String(num);
    while (s.length < 9) s = '0' + s;
    s = s.slice(-9);
    var r = parseInt(s.slice(0,3),10), g = parseInt(s.slice(3,6),10), b = parseInt(s.slice(6,9),10);
    return [Math.round(r*2.55), Math.round(g*2.55), Math.round(b*2.55)];
  }
  function rgbToColorNum(r,g,b) {
    function p(v){ var x = Math.round(v/2.55); if (x<0) x=0; if (x>100) x=100; var s=String(x); while(s.length<3) s='0'+s; return s; }
    return parseInt(p(r)+p(g)+p(b), 10);
  }
  function rgbToHex(rgb){ function h(v){var s=v.toString(16);return s.length<2?'0'+s:s;} return '#'+h(rgb[0])+h(rgb[1])+h(rgb[2]); }
  function hexToRgb(hex){ hex=hex.replace('#',''); return [parseInt(hex.slice(0,2),16),parseInt(hex.slice(2,4),16),parseInt(hex.slice(4,6),16)]; }

  function snap(mm){ if (!grid) return mm; return Math.round(mm/GRID_MM)*GRID_MM; }

  // ---- render -------------------------------------------------------------
  function sizePage() {
    page.style.width  = (PAGE_W*pxmm()) + 'px';
    page.style.height = (PAGE_H*pxmm()) + 'px';
    gridC.width  = PAGE_W*pxmm();
    gridC.height = PAGE_H*pxmm();
    drawGrid();
    drawMargins();
    drawRulers();
  }

  // Rulers (Tier 2): top + left mm scales that track zoom. Vertical ruler counts
  // mm from the BOTTOM so it matches the Y (mm) field. Purely visual overlay.
  // Sticky rulers: pinned to the visible canvas edges. Tick positions come from
  // where the page currently sits in the viewport (getBoundingClientRect already
  // accounts for scroll), so they stay on-screen and show the mm of the visible
  // region as you scroll/zoom. Vertical ruler counts mm from the BOTTOM (matches Y).
  function drawRulers() {
    var area = document.getElementById('fe-canvas-area');
    var scroll = document.getElementById('fe-canvas-scroll');
    var show = rulersOn && !previewMode;
    if (area) area.classList.toggle('fe-rulers', show);
    rulerH.style.display = show ? '' : 'none';
    rulerV.style.display = show ? '' : 'none';
    rulerCorner.style.display = show ? '' : 'none';
    if (!show || !scroll) return;
    var s = pxmm();
    var pr = page.getBoundingClientRect(), sr = scroll.getBoundingClientRect();
    var offX = pr.left - sr.left, offY = pr.top - sr.top;   // page origin in the viewport
    var HW = rulerH.clientWidth || (sr.width-0), VH = rulerV.clientHeight || sr.height;
    if (HW < 1 || VH < 1) return;
    rulerH.width = HW; rulerH.height = 18;
    var ch = rulerH.getContext('2d');
    ch.clearRect(0,0,HW,18); ch.fillStyle='#eef1f6'; ch.fillRect(0,0,HW,18);
    ch.strokeStyle='#8b96a8'; ch.fillStyle='#556'; ch.font='8px Arial,Helvetica,sans-serif'; ch.textBaseline='top';
    for (var x=0; x<=PAGE_W; x+=5){ var px=offX+x*s; if (px<-6||px>HW+6) continue; var maj=(x%10===0);
      ch.beginPath(); ch.moveTo(px,18); ch.lineTo(px, maj?7:12); ch.stroke();
      if (x%20===0 && x>0 && px>8) ch.fillText(x, px+1.5, 1);
    }
    rulerV.width = 18; rulerV.height = VH;
    var cv = rulerV.getContext('2d');
    cv.clearRect(0,0,18,VH); cv.fillStyle='#eef1f6'; cv.fillRect(0,0,18,VH);
    cv.strokeStyle='#8b96a8'; cv.fillStyle='#556'; cv.font='8px Arial,Helvetica,sans-serif';
    for (var y=0; y<=PAGE_H; y+=5){ var py=offY+(PAGE_H-y)*s; if (py<-6||py>VH+6) continue; var maj=(y%10===0);
      cv.beginPath(); cv.moveTo(18,py); cv.lineTo(maj?7:12, py); cv.stroke();
      if (y%20===0 && y>0 && py>10){ cv.save(); cv.translate(9,py+1); cv.rotate(-Math.PI/2); cv.textAlign='right'; cv.textBaseline='top'; cv.fillText(y,0,-4); cv.restore(); }
    }
  }
  // margin guides drawn on the page canvas (they scroll with the page, on purpose)
  function drawMargins() {
    if (!rulersOn || previewMode) return;
    var s=pxmm(), W=PAGE_W*s, H=PAGE_H*s;
    var gx=gridC.getContext('2d'); gx.save();
    gx.strokeStyle='rgba(224,120,40,.45)'; gx.lineWidth=1; gx.setLineDash([4,3]);
    [MARGIN_MM, PAGE_W-MARGIN_MM].forEach(function(mx){ gx.beginPath(); gx.moveTo(mx*s,0); gx.lineTo(mx*s,H); gx.stroke(); });
    [MARGIN_MM, PAGE_H-MARGIN_MM].forEach(function(my){ gx.beginPath(); gx.moveTo(0,my*s); gx.lineTo(W,my*s); gx.stroke(); });
    gx.restore();
  }
  function drawGrid() {
    var ctx = gridC.getContext('2d');
    ctx.clearRect(0,0,gridC.width,gridC.height);
    if (!grid || previewMode) return;
    ctx.strokeStyle = 'rgba(23,105,255,.12)';
    ctx.lineWidth = 1;
    for (var x=0; x<=PAGE_W; x+=GRID_MM){ ctx.beginPath(); ctx.moveTo(x*pxmm(),0); ctx.lineTo(x*pxmm(),PAGE_H*pxmm()); ctx.stroke(); }
    for (var y=0; y<=PAGE_H; y+=GRID_MM){ ctx.beginPath(); ctx.moveTo(0,y*pxmm()); ctx.lineTo(PAGE_W*pxmm(),y*pxmm()); ctx.stroke(); }
  }

  function render() {
    // remove existing element nodes (keep bg, svg, grid)
    Array.prototype.slice.call(page.querySelectorAll('.fe-el, .fe-lhandle, .fe-guide')).forEach(function(n){ n.remove(); });
    var old = document.getElementById('fe-table-el'); if (old) old.remove();
    svg.innerHTML = '';
    sizePage();

    elements.forEach(function (el) {
      // carry-over elements (side "!S") only print on continuation pages, never
      // on a normal one-pager — hide them in preview so it matches the PDF.
      if (previewMode && el.side === '!S') return;
      if (el.kind === 'line') { renderLine(el); return; }
      renderBox(el);
    });
    if (table) { if (previewMode) renderTablePreview(); else renderTable(); }
    highlight();
    if (fieldsOpen && _fieldCount !== elements.length) refreshFieldList(fieldSearchVal());
  }

  // Preview: draw a few sample item rows at the column positions (no frame).
  function renderTablePreview() {
    var s = pxmm(), g = table.gen;
    var rows = Math.min(3, Math.max(1, g.count|0));
    for (var ri = 0; ri < rows; ri++) {
      var ymm = g.ya - ri * g.spacing;
      table.cols.forEach(function(c){
        var arr = COL_SAMPLE[c.type];
        var val = (arr && arr[ri] !== undefined) ? arr[ri] : '';
        if (val === '') return;
        var d = document.createElement('div');
        d.className = 'fe-el fe-text';
        d.style.left = (c.xa*s) + 'px'; d.style.top = ((PAGE_H - ymm)*s) + 'px';
        var tx = (c.just==='C') ? '-50%' : (c.just==='H' ? '-100%' : '0');
        d.style.transform = 'translate(' + tx + ', -0.8em)';   // baseline (WYSIWYG), matches print
        d.style.fontSize = Math.max(4, (c.str>0?c.str:10) * PT_TO_MM * s) + 'px';
        d.style.fontWeight = c.fed ? 'bold' : 'normal';
        d.style.pointerEvents = 'none';
        d.textContent = val;
        page.appendChild(d);
      });
    }
  }

 
  var TBL_HEADER_MM = 8;   // header band kept above the first item row
  var TBL_PAD_MM    = 8;   // horizontal margin when grabbing the group

  function tableRectMM() {
    // rectangle in engine mm (y measured from the bottom of the page)
    var g = table.gen;
    return {
      xL: table.leftX - TBL_PAD_MM,
      xR: table.rightX + TBL_PAD_MM,
      yTop: g.ya + TBL_HEADER_MM,
      yBot: g.ya - g.count * g.spacing
    };
  }
  function inTableRect(x, y, r) { return x >= r.xL && x <= r.xR && y <= r.yTop && y >= r.yBot; }

  function renderTable() {
    var s = pxmm(), g = table.gen, r = tableRectMM();
    var left = r.xL*s, top = (PAGE_H - r.yTop)*s, w = (r.xR - r.xL)*s, h = (r.yTop - r.yBot)*s;
    if (w < 20) w = 20; if (h < 12) h = 12;
    var box = document.createElement('div');
    box.id = 'fe-table-el';
    box.style.left = left + 'px'; box.style.top = top + 'px';
    box.style.width = w + 'px';  box.style.height = h + 'px';
    if (selId === 'table') box.className = 'fe-selected';

    var cap = document.createElement('div'); cap.className = 'fe-tcaption';
    cap.textContent = TABLE_CAP;
    cap.addEventListener('mousedown', function(ev){ startDragTable(ev); });  // label = move handle
    box.appendChild(cap);   // no raw row count for normal users

    if (selId === 'table') {
      table.cols.forEach(function(c){
        var cx = (c.xa - r.xL)*s;
        if (cx > 1 && cx < w-1) { var d=document.createElement('div'); d.className='fe-tcol'; d.style.left=cx+'px'; box.appendChild(d); }
        var dv = document.createElement('div'); dv.className='fe-tdiv'; dv.style.left=cx+'px';
        dv.title = COL_DRAG_TIP;
        var grip = document.createElement('div'); grip.className='fe-tdiv-grip'; dv.appendChild(grip);
        dv.addEventListener('mousedown', function(ev){ startDragCol(ev, c); });
        box.appendChild(dv);
      });
      var rowTop = TBL_HEADER_MM*s;
      var rows = Math.max(1, Math.min(g.count|0, 60));
      for (var i=1; i<rows; i++) {
        var rr=document.createElement('div'); rr.className='fe-trow';
        rr.style.top=(rowTop + i*g.spacing*s)+'px'; box.appendChild(rr);
      }
    }
    // bottom handle = number of item rows (expand / reduce)
    var hb=document.createElement('div'); hb.className='fe-thandle bottom'; hb.title=RESIZE_ROWS_TIP;
    hb.addEventListener('mousedown', function(ev){ startResizeRows(ev); }); box.appendChild(hb);
    // right handle = stretch the table wider/narrower (gives big amounts more room)
    var hr=document.createElement('div'); hr.className='fe-thandle right';
    hr.title=(DK_UI?'Træk for at gøre tabellen bredere (mere plads til store beløb)':'Drag to make the table wider (more room for large amounts)');
    hr.addEventListener('mousedown', function(ev){ startResizeTableWidth(ev); }); box.appendChild(hr);

    box.addEventListener('mousedown', function(ev){ if (ev.target===box || ev.target===cap) startDragTable(ev); });
    page.appendChild(box);
  }

  // the other elements (column headers, border lines) that sit inside the frame
  function tableGroup() {
    var r = tableRectMM();
    return elements.filter(function(el){
      if (el.kind === 'line') return inTableRect(el.xa,el.ya,r) || inTableRect(el.xb,el.yb,r);
      return inTableRect(el.xa, el.ya, r);
    });
  }

  function startDragTable(ev) {
    ev.preventDefault(); selectTable();
    var s=pxmm(), sx=ev.clientX, sy=ev.clientY;
    var t0ya=table.gen.ya, cols0=table.cols.map(function(c){return c.xa;});
    var lx0=table.leftX, rx0=table.rightX;
    var grp=tableGroup().map(function(el){ return {el:el,xa:el.xa,ya:el.ya,xb:el.xb,yb:el.yb}; });
    var moved=false;
    function mv(e){
      if(!moved){pushUndo();moved=true;}
      var dx=(e.clientX-sx)/s, dy=(e.clientY-sy)/s;
      table.gen.ya=clampY(t0ya-dy);
      table.leftX=clampX(lx0+dx); table.rightX=clampX(rx0+dx);
      table.cols.forEach(function(c,i){ c.xa=clampX(cols0[i]+dx); });
      grp.forEach(function(o){
        o.el.xa=clampX(o.xa+dx); o.el.ya=clampY(o.ya-dy);
        if(o.el.kind==='line'){ o.el.xb=clampX(o.xb+dx); o.el.yb=clampY(o.yb-dy); }
      });
      render();
    }
    function up(){ document.removeEventListener('mousemove',mv); document.removeEventListener('mouseup',up); if(moved) markDirty(); }
    document.addEventListener('mousemove',mv); document.addEventListener('mouseup',up);
  }
  function startResizeRows(ev) {
    ev.preventDefault(); ev.stopPropagation(); selectTable();
    var s=pxmm(), sy=ev.clientY, c0=table.gen.count, sp=table.gen.spacing; var moved=false;
    function mv(e){ if(!moved){pushUndo();moved=true;}
      var dRows=Math.round(((e.clientY-sy)/s)/Math.max(sp,0.5));
      table.gen.count=Math.max(1, Math.round(c0+dRows)); render();
    }
    function up(){ document.removeEventListener('mousemove',mv); document.removeEventListener('mouseup',up); if(moved) markDirty(); }
    document.addEventListener('mousemove',mv); document.addEventListener('mouseup',up);
  }
  // Stretch the whole table wider/narrower around its left edge — spreads the
  // columns (and their headers) so large amounts have room instead of squeezing.
  function startResizeTableWidth(ev) {
    ev.preventDefault(); ev.stopPropagation(); selectTable();
    var s=pxmm(), sx=ev.clientX;
    var leftX=table.leftX, span0=Math.max(1, table.rightX-leftX);
    var cols0=table.cols.map(function(c){ return c.xa; });
    var grp=tableGroup().map(function(el){ return {el:el, xa:el.xa, xb:el.xb}; });
    var moved=false;
    function mv(e){
      if(!moved){ pushUndo(); moved=true; }
      var scale=(span0 + (e.clientX-sx)/s)/span0;
      if(scale<0.3) scale=0.3;
      var maxX=leftX; cols0.forEach(function(x){ maxX=Math.max(maxX, leftX+(x-leftX)*scale); });
      if(maxX>PAGE_W-2) scale=(PAGE_W-2-leftX)/span0;   // keep widest column on the page
      table.cols.forEach(function(c,i){ c.xa=clampX(leftX+(cols0[i]-leftX)*scale); });
      grp.forEach(function(o){
        o.el.xa=clampX(leftX+(o.xa-leftX)*scale);
        if(o.el.kind==='line') o.el.xb=clampX(leftX+(o.xb-leftX)*scale);
      });
      var xs=table.cols.map(function(c){ return c.xa; });
      table.leftX=Math.min.apply(null,xs); table.rightX=Math.max.apply(null,xs);
      render();
    }
    function up(){ document.removeEventListener('mousemove',mv); document.removeEventListener('mouseup',up); if(moved) markDirty(); }
    document.addEventListener('mousemove',mv); document.addEventListener('mouseup',up);
  }
  // Scale the table columns AND their headers by `factor` around an anchor edge
  // ('left'=extend right, 'right'=extend left, 'center'=both). Headers move with
  // the columns so they stay aligned. Auto-limits so nothing runs off the page.
  function stretchTable(factor, anchor){
    if(!table || !table.cols.length) return;
    var ax = anchor==='left' ? table.leftX : (anchor==='right' ? table.rightX : (table.leftX+table.rightX)/2);
    var cols0 = table.cols.map(function(c){ return c.xa; });
    function span(f){ var mn=Infinity,mx=-Infinity; cols0.forEach(function(x){ var nx=ax+(x-ax)*f; mn=Math.min(mn,nx); mx=Math.max(mx,nx); }); return {mn:mn,mx:mx}; }
    var sp=span(factor);
    while(factor>0.4 && (sp.mx>PAGE_W-2 || sp.mn<2)){ factor -= 0.02; sp=span(factor); }   // keep on page
    if(Math.abs(factor-1)<0.001) return;
    pushUndo();
    var grp=tableGroup();
    table.cols.forEach(function(c,i){ c.xa=clampX(ax+(cols0[i]-ax)*factor); });
    grp.forEach(function(el){ el.xa=clampX(ax+(el.xa-ax)*factor); if(el.kind==='line') el.xb=clampX(ax+(el.xb-ax)*factor); });
    var xs=table.cols.map(function(c){ return c.xa; });
    table.leftX=Math.min.apply(null,xs); table.rightX=Math.max.apply(null,xs);
    render(); markDirty();
  }
  function changeRows(d){ if(!table) return; pushUndo(); table.gen.count=Math.max(1, Math.round((table.gen.count||1)+d)); render(); markDirty(); }
  // Reset the table (columns + headers/border lines) to how it looked on load.
  function resetTable(){
    if(!tableInit) return;
    pushUndo();
    table = JSON.parse(JSON.stringify(tableInit));
    tableGroupInit.forEach(function(g){ var el=elById(g.id); if(el){ el.xa=g.xa; el.ya=g.ya; el.xb=g.xb; el.yb=g.yb; } });
    selId='table'; render(); showProps(); markDirty();
    flashStatus((DK_UI?'Tabel nulstillet':'Table reset'), '#1769ff');
  }
  // find the header text (art=2) that belongs to a column: a text element just
  // above the item area whose x is closest to the column's x.
  function findColHeader(col) {
    var top = table.gen.ya, band = 14, best = null, bestd = 18;
    elements.forEach(function(e){
      if (e.kind !== 'text') return;
      if (e.ya < top - 1 || e.ya > top + band) return;
      var d = Math.abs(e.xa - col.xa);
      if (d < bestd) { bestd = d; best = e; }
    });
    return best;
  }
  // drag a column: moves the column X AND its connected header text together
  function startDragCol(ev, col) {
    ev.preventDefault(); ev.stopPropagation(); selectTable();
    var s=pxmm(), sx=ev.clientX, x0=col.xa;
    var hdr = findColHeader(col), hx0 = hdr ? hdr.xa : 0;
    var moved=false;
    function mv(e){ if(!moved){pushUndo();moved=true;}
      var dx=(e.clientX-sx)/s;
      col.xa = clampX(x0+dx);
      if (hdr) hdr.xa = clampX(hx0+dx);
      table.leftX  = Math.min.apply(null, table.cols.map(function(c){return c.xa;}));
      table.rightX = Math.max.apply(null, table.cols.map(function(c){return c.xa;}));
      render();
    }
    function up(){ document.removeEventListener('mousemove',mv); document.removeEventListener('mouseup',up); if(moved) markDirty(); }
    document.addEventListener('mousemove',mv); document.addEventListener('mouseup',up);
  }
  function selectTable(){ selId='table'; showProps(); highlight(); }

  // Match the PDF engine (formfunk.php): a line is only shown when its start-X
  // is set (xa != 0) AND it is horizontal (ya==yb) or vertical (xa==xb).
  // Diagonal rows are template artefacts the real preview never draws.
  function lineVisible(el) {
    if (!el.xa) return false;
    var horiz = Math.abs(el.ya - el.yb) < 0.05;
    var vert  = Math.abs(el.xa - el.xb) < 0.05;
    return horiz || vert;
  }

  function renderLine(el) {
    if (!lineVisible(el)) return;
    var s = pxmm();
    var x1 = el.xa*s, y1 = (PAGE_H-el.ya)*s, x2 = el.xb*s, y2 = (PAGE_H-el.yb)*s;
    var rgb = colorNumToRgb(el.color);
    var ln = document.createElementNS('http://www.w3.org/2000/svg','line');
    ln.setAttribute('x1',x1); ln.setAttribute('y1',y1);
    ln.setAttribute('x2',x2); ln.setAttribute('y2',y2);
    ln.setAttribute('stroke','rgb('+rgb.join(',')+')');
    ln.setAttribute('stroke-width', Math.max(1, (el.str||0.3) * PT_TO_MM * s));
    ln.setAttribute('data-id', el.id);
    ln.style.pointerEvents = 'none';
    if (el.side==='!S') ln.style.opacity = '0.4';   // carry-over line: dimmed in edit
    if (el.id === selId) { ln.setAttribute('stroke-dasharray','4 3'); }
    svg.appendChild(ln);
    // wide invisible hit-area so a thin line is easy to click and move
    var hit = document.createElementNS('http://www.w3.org/2000/svg','line');
    hit.setAttribute('x1',x1); hit.setAttribute('y1',y1);
    hit.setAttribute('x2',x2); hit.setAttribute('y2',y2);
    hit.setAttribute('stroke','transparent'); hit.setAttribute('stroke-width', 10);
    hit.setAttribute('data-id', el.id);
    hit.style.pointerEvents = 'stroke'; hit.style.cursor = 'move';
    hit.addEventListener('mousedown', function(ev){ startDragLine(ev, el); });
    svg.appendChild(hit);
    // endpoint handles (resize the line longer/shorter) when selected
    if (el.id === selId && !previewMode) {
      addLineHandle(el, 'a', el.xa, el.ya);
      addLineHandle(el, 'b', el.xb, el.yb);
    }
  }
  function addLineHandle(el, which, xmm, ymm) {
    var s = pxmm();
    var h = document.createElement('div'); h.className = 'fe-lhandle';
    h.style.left = (xmm*s) + 'px'; h.style.top = ((PAGE_H-ymm)*s) + 'px';
    h.addEventListener('mousedown', function(ev){ startResizeLineEnd(ev, el, which); });
    page.appendChild(h);
  }
  // Drag a line's end to make it longer/shorter. Horizontal lines resize left/
  // right (width); vertical lines resize up/down (length) - staying H or V.
  function startResizeLineEnd(ev, el, which) {
    ev.preventDefault(); ev.stopPropagation(); select(el.id);
    var s = pxmm(), sx = ev.clientX, sy = ev.clientY;
    var horiz = Math.abs(el.ya - el.yb) < 0.05;
    var o = { xa:el.xa, ya:el.ya, xb:el.xb, yb:el.yb }, moved=false;
    var tg = lineSnapTargets(el);
    var eps = endpointTargets(el);
    function setEndX(v){ if (which==='a') el.xa=clampX(v); else el.xb=clampX(v); }
    function setEndY(v){ if (which==='a') el.ya=clampY(v); else el.yb=clampY(v); }
    function mv(e){ if(!moved){ pushUndo(); moved=true; }
      var dxmm = (e.clientX-sx)/s, dymm = -(e.clientY-sy)/s;
      // the cursor's true position, in BOTH axes (the handle started on the end)
      var curX = (which==='a' ? o.xa : o.xb) + dxmm;
      var curY = (which==='a' ? o.ya : o.yb) + dymm;

      // Candidate A: exact join onto another line's ENDPOINT (2D). If the cursor
      // is genuinely ON a corner (within CORNER_MM) we take it, even though a 1D
      // axis-snap might read as "numerically closer" - reaching the corner needs
      // the perpendicular shift.
      var CORNER_MM = 3.5;
      var P = grid ? null : nearestPoint(curX, curY, eps, CORNER_MM);
      // Candidate B: straight extend + align the free axis onto a line (1D).
      var cVal = grid ? null : (horiz ? snapVal(curX, tg.xs) : snapVal(curY, tg.ys));

      if (P) {
        if (horiz) { setEndX(P.x); el.ya = el.yb = clampY(P.y); }   // shift up/down to meet corner
        else       { setEndY(P.y); el.xa = el.xb = clampX(P.x); }   // shift left/right to meet corner
        drawJoin(P.x, P.y);
      } else if (cVal !== null) {
        if (horiz) { setEndX(cVal); drawGuides(cVal, null); }       // extend, aligned to a line's x
        else       { setEndY(cVal); drawGuides(null, cVal); }       // extend, aligned to a line's y
      } else {
        if (horiz) setEndX(grid ? snap(curX) : curX); else setEndY(grid ? snap(curY) : curY);
        clearGuides();
      }
      render(); syncProps();
    }
    function up(){ document.removeEventListener('mousemove',mv); document.removeEventListener('mouseup',up); clearGuides(); if(moved) markDirty(); }
    document.addEventListener('mousemove',mv); document.addEventListener('mouseup',up);
  }

  function renderBox(el) {
    var s = pxmm();
    var d = document.createElement('div');
    d.className = 'fe-el ' + (el.kind === 'logo' ? 'fe-logo' : 'fe-text') + (el._locked ? ' fe-locked' : '')
      + ((el.kind==='text' && !previewMode && (el.besk===NEW_TEXT || !el.besk)) ? ' fe-placeholder' : '')
      + (el.side==='!S' ? ' fe-carryover' : '');
    d.setAttribute('data-id', el.id);
    if (el.side==='!S') d.title = (DK_UI?'Vises kun på fortsættelsessider (ikke på en enkeltsidet udskrift)':'Shows only on continuation pages (not on a single-page print)');
    d.style.left = (el.xa*s) + 'px';
    d.style.top  = ((PAGE_H-el.ya)*s) + 'px';
    var tx = (el.justering==='C') ? '-50%' : (el.justering==='H' ? '-100%' : '0');
    // WYSIWYG: print draws text from its baseline (ya). CSS positions by the top,
    // so shift the box up ~1 ascent (0.8em) to place the baseline on the ya line —
    // this matches the print, so a big title sits where it prints instead of
    // overhanging the fields below it. (Logo keeps top-left positioning below.)
    d.style.transform = 'translate(' + tx + ', -0.8em)';

    if (el.kind === 'logo') {
      d.style.transform = 'translate(0,0)';
      // display size (mm): stored in xb/yb; fall back to a sensible default
      var wmm = el.xb > 0 ? el.xb : 35;
      var hmm = el.yb > 0 ? el.yb : (LOGO_NAT.w ? +(wmm * LOGO_NAT.h / LOGO_NAT.w).toFixed(1) : 20);
      d.style.width = (wmm*s) + 'px'; d.style.height = (hmm*s) + 'px';
      if (LOGO_URL) {
        d.classList.remove('fe-logo');
        d.style.border = previewMode ? 'none' : ((el.id===selId) ? '1px solid #1769ff' : '1px dashed #b9c4d6');
        var img = document.createElement('img');
        img.src = LOGO_URL; img.style.width='100%'; img.style.height='100%';
        img.style.objectFit='contain'; img.draggable=false; d.appendChild(img);
        if (el.id===selId && !previewMode) {
          var rh = document.createElement('div'); rh.className='fe-logo-rz';
          rh.addEventListener('mousedown', function(ev){ startResizeLogo(ev, el); });
          d.appendChild(rh);
        }
      } else if (previewMode) {
        return;   // no logo image -> show nothing in the preview
      } else {
        // empty slot: faint optional placeholder, not a second logo
        d.classList.remove('fe-logo'); d.classList.add('fe-logo-ph');
        d.style.border = (el.id===selId) ? '1px solid #1769ff' : '';
        d.textContent = L.logoPlaceholder;
      }
    } else {
      var fs = Math.max(4, (el.str>0?el.str:10) * PT_TO_MM * s);
      d.style.fontSize = fs + 'px';
      d.style.fontFamily = feCssFont(el.font);
      d.style.fontWeight = el.fed ? 'bold' : 'normal';
      d.style.fontStyle  = el.kursiv ? 'italic' : 'normal';
      var rgb = colorNumToRgb(el.color);
      d.style.color = 'rgb('+rgb.join(',')+')';
      d.appendChild(contentNode(el, false));
    }
    d.addEventListener('mousedown', function(ev){ startDrag(ev, el, d); });
    page.appendChild(d);
  }

  function logoAspect() { return (LOGO_NAT.w && LOGO_NAT.h) ? (LOGO_NAT.w / LOGO_NAT.h) : 1.6; }
  function startResizeLogo(ev, el) {
    ev.preventDefault(); ev.stopPropagation();
    var s = pxmm(), sx = ev.clientX;
    var w0 = el.xb > 0 ? el.xb : 35, asp = logoAspect(), moved = false;
    function mv(e){ if(!moved){ pushUndo(); moved=true; }
      var neww = Math.max(5, w0 + (e.clientX - sx)/s);
      el.xb = Math.round(neww*10)/10;
      el.yb = Math.round((neww/asp)*10)/10;   // keep aspect ratio
      render();
      var lw=document.getElementById('fe-lw'), lh=document.getElementById('fe-lh');
      if(lw) lw.value=el.xb; if(lh) lh.value=el.yb;
    }
    function up(){ document.removeEventListener('mousemove',mv); document.removeEventListener('mouseup',up); if(moved) markDirty(); }
    document.addEventListener('mousemove',mv); document.addEventListener('mouseup',up);
  }

  // JS port of fe_var_label()/fe_render_besk_labels() so chips recompute live
  // when the user edits an element's content.
  function feVarLabel(token) {
    var key = token || '';
    var p = key.indexOf('('); if (p >= 0) key = key.slice(0, p);
    key = key.replace(/[)\s]+$/,'').replace(/^[)\s]+/,'');
    if (VAR_MAP[key]) return DK_UI ? VAR_MAP[key][0] : VAR_MAP[key][1];
    var pretty = key;
    ['ordre_','eget_','egen_','ansat_','konto_','formular_','levering_','forfalden_','rykker_','afdeling_'].some(function(pre){
      if (pretty.indexOf(pre)===0){ pretty=pretty.slice(pre.length); return true; } return false;
    });
    pretty = pretty.replace(/_/g,' ').trim();
    return pretty==='' ? token : pretty.charAt(0).toUpperCase()+pretty.slice(1);
  }
  function feTokenize(besk) {
    besk = besk || ''; var out=[], i=0, buf='';
    while (i < besk.length) {
      if (besk.substr(i,3) === 'if(') {
        var close = besk.indexOf(')', i);
        if (close >= 0) {
          if (buf!==''){ out.push({t:'text',v:buf}); buf=''; }
          var inner = besk.substring(i+3, close).trim();
          var neg = inner.charAt(0)==='!' ? 1 : 0;
          inner = inner.replace(/^[!$]+/,'');
          var m = inner.match(/^[A-Za-z0-9_]+/); var vv = m ? m[0] : '';
          out.push({t:'cond', v:vv, label:feVarLabel(vv), neg:neg});
          i = close+1; continue;
        }
      }
      var ch = besk.charAt(i);
      if (ch === '$') {
        if (buf!==''){ out.push({t:'text',v:buf}); buf=''; }
        var j=i+1, tok='';
        while (j<besk.length && /[A-Za-z0-9_]/.test(besk.charAt(j))) { tok+=besk.charAt(j); j++; }
        if (j<besk.length && besk.charAt(j)==='('){ var k=besk.indexOf(')',j); if(k>=0) j=k+1; }
        out.push({t:'var', v:tok, label:feVarLabel(tok)});
        i=j; continue;
      }
      if (ch === ';') { i++; continue; }
      buf += ch; i++;
    }
    if (buf!=='') out.push({t:'text', v:buf});
    return out;
  }

  // contentNode(el, useLabels):
  //  - on the CANVAS (useLabels=false) variables render as their SAMPLE DATA, so
  //    the edit view occupies the exact same width as the draft/print -> pixel
  //    perfect. In edit they get a highlight; in preview (Show draft) plain text.
  //  - in the PROPERTIES panel (useLabels=true) variables render as their human
  //    label so you can see which fields the element contains.
  function contentNode(el, useLabels) {
    var span = document.createElement('span');
    feTokenize(el.besk).forEach(function (tok) {
      if (tok.t === 'var') {
        var text = useLabels ? (tok.label || tok.v) : sampleFor(tok.v);
        if (previewMode && !useLabels) { span.appendChild(document.createTextNode(text)); return; }
        var c = document.createElement('span');
        c.className = 'fe-chip';
        c.textContent = text;
        c.title = tok.label || tok.v;   // hover shows which field it is
        span.appendChild(c);
      } else if (tok.t === 'cond') {
        // conditional (if-field): no on-canvas marker; the following text just
        // shows. The rule is still visible/editable in the Content box.
        return;
      } else {
        span.appendChild(document.createTextNode(tok.v));
      }
    });
    if (!el.besk && !previewMode) span.appendChild(document.createTextNode(EMPTY_LBL));
    return span;
  }

  // Sample value for a variable token (preview only). Falls back to the label.
  function sampleFor(token) {
    var key = token || '';
    var p = key.indexOf('('); if (p >= 0) key = key.slice(0, p);
    key = key.replace(/[)\s]+$/,'');
    if (SAMPLE[key] !== undefined) return SAMPLE[key];
    return feVarLabel(token);
  }

  // ---- selection & dragging ----------------------------------------------
  function elById(id){ for (var i=0;i<elements.length;i++) if (elements[i].id===id) return elements[i]; return null; }

  function select(id) {
    selId = id;
    selSet = (id===null || id==='table') ? [] : [id];   // single-select resets the group
    highlight();
    showProps();
    updateAlignBar();
  }
  // --- multi-select helpers (Tier 2) ---------------------------------------
  function selHas(id){ return selSet.indexOf(id) >= 0; }
  function toggleSel(id){
    if (id==null || id==='table') return;
    var i = selSet.indexOf(id);
    if (i>=0) selSet.splice(i,1); else selSet.push(id);
    selId = selSet.length ? selSet[selSet.length-1] : null;
    highlight(); showProps(); updateAlignBar();
  }
  function setSel(ids){
    selSet = ids.slice();
    selId = selSet.length ? selSet[selSet.length-1] : null;
    highlight(); showProps(); updateAlignBar();
  }
  // move an element's anchor to v; a line translates its far end by the same delta
  function setElemX(e,v){ var d=v-e.xa; e.xa=Math.round(v*100)/100; if(e.kind==='line') e.xb=Math.round((e.xb+d)*100)/100; }
  function setElemY(e,v){ var d=v-e.ya; e.ya=Math.round(v*100)/100; if(e.kind==='line') e.yb=Math.round((e.yb+d)*100)/100; }
  function alignSelected(mode){
    var els = selSet.map(elById).filter(function(e){return e && e.kind!=='logo';});
    if (els.length<2) return;
    pushUndo();
    var xs=els.map(function(e){return e.xa;}), ys=els.map(function(e){return e.ya;});
    var minX=Math.min.apply(null,xs), maxX=Math.max.apply(null,xs);
    var minY=Math.min.apply(null,ys), maxY=Math.max.apply(null,ys);
    els.forEach(function(e){
      if      (mode==='left')    setElemX(e,minX);
      else if (mode==='right')   setElemX(e,maxX);
      else if (mode==='centerX') setElemX(e,(minX+maxX)/2);
      else if (mode==='top')     setElemY(e,maxY);   // top = larger y (from bottom)
      else if (mode==='bottom')  setElemY(e,minY);
      else if (mode==='middleY') setElemY(e,(minY+maxY)/2);
    });
    render(); syncProps(); markDirty();
  }
  function distributeSel(mode){
    var els = selSet.map(elById).filter(function(e){return e && e.kind!=='logo';});
    if (els.length<3) return;
    pushUndo();
    var horiz=(mode==='distH');
    els.sort(function(a,b){ return horiz ? a.xa-b.xa : a.ya-b.ya; });
    var first = horiz ? els[0].xa : els[0].ya;
    var last  = horiz ? els[els.length-1].xa : els[els.length-1].ya;
    var step  = (last-first)/(els.length-1);
    els.forEach(function(e,i){ var v=first+step*i; if(horiz) setElemX(e,v); else setElemY(e,v); });
    render(); syncProps(); markDirty();
  }
  var alignBar = null;
  function buildAlignBar(){
    if (alignBar) return alignBar;
    alignBar = document.createElement('div'); alignBar.id='fe-align-bar'; alignBar.style.display='none';
    var cnt=document.createElement('span'); cnt.className='fe-ab-count'; cnt.id='fe-ab-count'; alignBar.appendChild(cnt);
    var defs=[
      ['left','L', DK_UI?'Justér venstre':'Align left'],
      ['centerX','C', DK_UI?'Centrér vandret':'Centre horizontally'],
      ['right','R', DK_UI?'Justér højre':'Align right'],
      ['sep'],
      ['top','T', DK_UI?'Justér top':'Align top'],
      ['middleY','M', DK_UI?'Centrér lodret':'Centre vertically'],
      ['bottom','B', DK_UI?'Justér bund':'Align bottom'],
      ['sep'],
      ['distH','↔', DK_UI?'Fordel vandret (3+)':'Distribute horizontally (3+)'],
      ['distV','↕', DK_UI?'Fordel lodret (3+)':'Distribute vertically (3+)']
    ];
    defs.forEach(function(d){
      if (d[0]==='sep'){ var sp=document.createElement('span'); sp.className='fe-ab-sep'; alignBar.appendChild(sp); return; }
      var b=document.createElement('button'); b.type='button'; b.className='fe-ab-btn'; b.textContent=d[1]; b.title=d[2];
      b.addEventListener('click', function(){ (d[0]==='distH'||d[0]==='distV') ? distributeSel(d[0]) : alignSelected(d[0]); });
      alignBar.appendChild(b);
    });
    document.getElementById('fe-main').appendChild(alignBar);
    return alignBar;
  }
  function updateAlignBar(){
    var bar=buildAlignBar();
    if (selSet.length>=2){ bar.style.display='flex';
      var c=document.getElementById('fe-ab-count'); if(c) c.textContent=selSet.length+(DK_UI?' valgt':' selected'); }
    else bar.style.display='none';
  }

  // ---- searchable Fields panel (Tier 2) -----------------------------------
  var fieldsDrawer=null, fieldsOpen=false, _fieldCount=-1;
  function fieldLabel(el){
    if (el.kind==='logo') return L.logo;
    if (el.kind==='line') return L.line;
    var parts = feTokenize(el.besk||'');
    var s = parts.map(function(p){ return (p.t==='text') ? p.v : (p.label||p.v); }).join('').trim();
    return s || EMPTY_LBL;
  }
  function scrollToElement(el){
    var sc=document.getElementById('fe-canvas-scroll'); if(!sc||!el) return;
    var s=pxmm();
    sc.scrollTop  = Math.max(0, (PAGE_H-el.ya)*s - sc.clientHeight/2);
    sc.scrollLeft = Math.max(0, el.xa*s - sc.clientWidth/2);
  }
  function buildFieldsDrawer(){
    if (fieldsDrawer) return fieldsDrawer;
    fieldsDrawer=document.createElement('div'); fieldsDrawer.id='fe-fields-drawer'; fieldsDrawer.style.display='none';
    fieldsDrawer.innerHTML='<div class="fe-fd-head"><span>&#9776; '+(DK_UI?'Felter':'Fields')+'</span>'
      +'<span><button type="button" id="fe-fd-flash" class="fe-mini-btn" style="padding:2px 8px;" title="'+(DK_UI?'Blink alle felter':'Flash every field')+'">&#10024; '+(DK_UI?'Vis alle':'Reveal')+'</button> '
      +'<button type="button" id="fe-fd-close" class="fe-x" title="'+(DK_UI?'Luk':'Close')+'">&times;</button></span></div>'
      +'<input type="text" id="fe-fd-search" placeholder="'+(DK_UI?'Søg felter…':'Search fields…')+'" spellcheck="false">'
      +'<div id="fe-fd-list"></div>';
    document.getElementById('fe-main').appendChild(fieldsDrawer);
    fieldsDrawer.querySelector('#fe-fd-close').addEventListener('click', function(){ toggleFields(false); });
    fieldsDrawer.querySelector('#fe-fd-flash').addEventListener('click', flashAllFields);
    fieldsDrawer.querySelector('#fe-fd-search').addEventListener('input', function(){ refreshFieldList(this.value); });
    return fieldsDrawer;
  }
  function fieldSearchVal(){ var s=document.getElementById('fe-fd-search'); return s?s.value:''; }
  function refreshFieldList(filter){
    buildFieldsDrawer();
    var list=document.getElementById('fe-fd-list'); if(!list) return;
    _fieldCount = elements.length;
    var f=(filter||'').toLowerCase();
    list.innerHTML='';
    var shown=0;
    function addItem(idVal, kind, label){
      if (f && label.toLowerCase().indexOf(f)<0) return;
      shown++;
      var it=document.createElement('div');
      var isSel = (idVal==='table') ? (selId==='table') : (idVal===selId || selHas(idVal));
      it.className='fe-fd-item'+(isSel?' active':''); it.setAttribute('data-id', idVal);
      var ic = kind==='logo'?'&#9635;':(kind==='line'?'&#9472;':(kind==='table'?'&#9638;':'T'));
      it.innerHTML='<span class="fe-fd-ic">'+ic+'</span><span class="fe-fd-lbl"></span>';
      it.querySelector('.fe-fd-lbl').textContent = label;
      it.addEventListener('click', function(ev){
        if (idVal==='table'){ selectTable(); }
        else if (ev.shiftKey){ toggleSel(idVal); }
        else { select(idVal); var el=elById(idVal); if(el) scrollToElement(el); }
        refreshFieldList(fieldSearchVal());
      });
      list.appendChild(it);
    }
    elements.forEach(function(el){ addItem(el.id, el.kind, fieldLabel(el)); });
    if (table) addItem('table','table', TABLE_CAP);
    if (!shown){ var e=document.createElement('div'); e.className='fe-fd-empty'; e.textContent=(DK_UI?'Ingen match':'No matches'); list.appendChild(e); }
  }
  function toggleFields(open){
    buildFieldsDrawer();
    fieldsOpen = (open!==undefined) ? open : !fieldsOpen;
    fieldsDrawer.style.display = fieldsOpen ? 'flex' : 'none';
    if (fieldsOpen) refreshFieldList(fieldSearchVal());
  }
  function syncFieldActive(){
    if (!fieldsDrawer || !fieldsOpen) return;
    Array.prototype.forEach.call(fieldsDrawer.querySelectorAll('.fe-fd-item'), function(it){
      var a=it.getAttribute('data-id');
      var on = (a==='table') ? (selId==='table') : (parseInt(a,10)===selId || selHas(parseInt(a,10)));
      it.classList.toggle('active', on);
    });
  }
  function highlight() {
    Array.prototype.slice.call(page.querySelectorAll('.fe-el')).forEach(function(n){
      var idn = parseInt(n.getAttribute('data-id'),10);
      n.classList.toggle('fe-selected', idn === selId || selHas(idn));
    });
    var tb = document.getElementById('fe-table-el');
    if (tb) tb.classList.toggle('fe-selected', selId === 'table');
    syncFieldActive();
  }

  function snapshot() { return JSON.stringify({ e: elements, t: table, d: deletedIds }); }
  function restore(str) { var s = JSON.parse(str); elements = s.e; if (s.t !== undefined) table = s.t; deletedIds = s.d || []; }
  function pushUndo() {
    undoStack.push(snapshot());
    if (undoStack.length > 60) undoStack.shift();
    redoStack = [];   // a new action invalidates the redo history
  }

  // Core elements are structural and can never be deleted (logo + the table).
  function isCore(el) { return !el || el.kind === 'logo'; }
  function deleteSelected() {
    // Multi-select: delete every deletable element in the group (skip core/logo).
    if (selSet.length > 1) {
      var toDel = selSet.map(elById).filter(function(el){ return el && !isCore(el) && el.kind!=='logo' && !el._locked; });
      if (!toDel.length) { flashStatus(L.coreCantDelete, '#b26a00'); return; }
      pushUndo();
      toDel.forEach(function(el){ if (el.id>0) deletedIds.push(el.id); });
      elements = elements.filter(function(x){ return toDel.indexOf(x)<0; });
      selSet=[]; selId=null; render(); showProps(); updateAlignBar(); markDirty();
      return;
    }
    if (selId === 'table') { flashStatus(L.coreCantDelete, '#b26a00'); return; }
    var el = elById(selId);
    if (!el) return;
    if (el._locked) { flashStatus(DK_UI?'Låst – lås op først':'Locked – unlock first', '#b26a00'); return; }
    // The logo can't be deleted as an element (it's a fixed slot), but pressing
    // Delete on it should offer to remove the uploaded logo image itself.
    if (el.kind === 'logo') { removeLogo(); return; }
    if (isCore(el)) { flashStatus(L.coreCantDelete, '#b26a00'); return; }
    pushUndo();
    if (el.id > 0) deletedIds.push(el.id);
    elements = elements.filter(function (x) { return x !== el; });
    selId = null; selSet=[]; render(); showProps(); updateAlignBar(); markDirty();
  }
  // Remove the uploaded logo image and restore the clean background. Prompts
  // first. Shared by the Delete key, the panel button and the context menu.
  function removeLogo() {
    if (!LOGO_URL) { flashStatus(L.logoNone, '#b26a00'); return; }
    if (!window.confirm(L.logoRemoveConfirm)) return;
    flashStatus(L.logoRemoving, '#444');
    fetch('formeditor.php?fe_action=logo_remove', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ form_nr: formNr, sprog: sprog }), credentials:'same-origin'
    }).then(function(r){ return r.json(); }).then(function(j){
      if(j && j.ok){
        LOGO_URL=''; LOGO_NAT={w:0,h:0};
        render(); showProps();
        flashStatus(L.logoRemoved, '#2a7d2a');
      } else if(j && j.error==='session'){ flashStatus(L.session,'#c00'); }
      else { flashStatus(L.neterr,'#c00'); }
    }).catch(function(){ flashStatus(L.neterr,'#c00'); });
  }
  function flashStatus(msg, col) { statusEl.textContent = msg; statusEl.style.color = col || '#444'; }

  // ---- add new elements ---------------------------------------------------
  // Place near the middle of the visible canvas area so the user sees it.
  function newSpot() {
    var s = pxmm(), sc = document.getElementById('fe-canvas-scroll');
    var xmm = (sc.scrollLeft + sc.clientWidth/2) / s;
    var ymmTop = (sc.scrollTop + sc.clientHeight/2) / s;
    return { x: clampX(Math.round(xmm)), y: clampY(Math.round(PAGE_H - ymmTop)) };
  }
  function addText() {
    pushUndo();
    var p = newSpot();
    var el = { id: nextTmpId--, art: 2, kind: 'text', besk: NEW_TEXT,
               xa: p.x, ya: p.y, xb: 0, yb: 0, str: 10, color: 0,
               justering: 'V', font: 'Helvetica', fed: 0, kursiv: 0, side: 'A' };
    elements.push(el); selId = el.id; render(); showProps(); markDirty();
    var ce = document.getElementById('fe-content-edit'); if (ce) { ce.focus(); ce.select(); }
  }
  function addLine() {
    pushUndo();
    var p = newSpot();
    var el = { id: nextTmpId--, art: 1, kind: 'line', besk: '',
               xa: clampX(p.x-30), ya: p.y, xb: clampX(p.x+30), yb: p.y,
               str: 1, color: 0, justering: 'V', font: 'Helvetica', fed: 0, kursiv: 0, side: 'A' };
    elements.push(el); selId = el.id; render(); showProps(); markDirty();
  }
  function addTextAt(xmm, ymm) {
    pushUndo();
    var el = { id: nextTmpId--, art: 2, kind: 'text', besk: NEW_TEXT,
               xa: clampX(xmm), ya: clampY(ymm), xb: 0, yb: 0, str: 10, color: 0,
               justering: 'V', font: 'Helvetica', fed: 0, kursiv: 0, side: 'A' };
    elements.push(el); selId = el.id; render(); showProps(); markDirty();
    var ce = document.getElementById('fe-content-edit'); if (ce) { ce.focus(); ce.select(); }
  }
  // duplicate the selected element (text/line) a few mm offset
  function duplicateSelected() {
    var el = elById(selId); if (!el || el.kind === 'logo') return;
    pushUndo();
    var c = JSON.parse(JSON.stringify(el));
    c.id = nextTmpId--; c.xa = clampX(c.xa + 3); c.ya = clampY(c.ya - 3);
    if (c.kind === 'line') { c.xb = clampX(c.xb + 3); c.yb = clampY(c.yb - 3); }
    elements.push(c); selId = c.id; render(); showProps(); markDirty();
  }
  // ---- copy / paste / duplicate / select-all (text + lines) ---------------
  var feClipboard = null;
  var FE_CLIP_FIELDS = ['art','kind','besk','xa','ya','xb','yb','str','color','font','fed','kursiv','side','justering'];
  function feSelectedMovable(){
    var ids = selSet.length ? selSet : (selId!=null && selId!=='table' ? [selId] : []);
    return ids.map(elById).filter(function(e){ return e && (e.kind==='text'||e.kind==='line'); });
  }
  function feCopy(){
    var els = feSelectedMovable(); if(!els.length) return;
    feClipboard = els.map(function(e){ var o={}; FE_CLIP_FIELDS.forEach(function(f){ o[f]=e[f]; }); return o; });
    try { localStorage.setItem('fe_clipboard', JSON.stringify(feClipboard)); } catch(err){}   // works across forms/tabs
    flashStatus((DK_UI?'Kopieret: ':'Copied: ')+els.length, '#1769ff');
  }
  function fePaste(){
    var clip = feClipboard;
    if(!clip){ try { clip = JSON.parse(localStorage.getItem('fe_clipboard')||'null'); } catch(err){} }
    if(!clip || !clip.length) return;
    pushUndo();
    var ids=[];
    clip.forEach(function(d){
      var el={}; FE_CLIP_FIELDS.forEach(function(f){ el[f]=d[f]; });
      el.id = nextTmpId--;
      el.xa = clampX((el.xa||0)+4); el.ya = clampY((el.ya||0)-4);
      if(el.kind==='line'){ el.xb = clampX((el.xb||0)+4); el.yb = clampY((el.yb||0)-4); }
      elements.push(el); ids.push(el.id);
    });
    render(); setSel(ids); markDirty();
    flashStatus((DK_UI?'Indsat: ':'Pasted: ')+ids.length, '#2a7d2a');
  }
  function feDuplicate(){
    var els = feSelectedMovable(); if(!els.length) return;
    pushUndo();
    var ids=[];
    els.forEach(function(e){
      var c={}; FE_CLIP_FIELDS.forEach(function(f){ c[f]=e[f]; });
      c.id = nextTmpId--; c.xa = clampX(c.xa+4); c.ya = clampY(c.ya-4);
      if(c.kind==='line'){ c.xb=clampX(c.xb+4); c.yb=clampY(c.yb-4); }
      elements.push(c); ids.push(c.id);
    });
    render(); setSel(ids); markDirty();
  }
  function feSelectAll(){
    var ids = elements.filter(function(e){ return e.kind==='text'||e.kind==='line'; }).map(function(e){ return e.id; });
    if(ids.length) setSel(ids);
  }
  // change a line's thickness by delta (min 0.5)
  function changeLineWidth(delta) {
    var el = elById(selId); if (!el || el.kind !== 'line') return;
    pushUndo();
    el.str = Math.max(0.5, Math.round(((el.str || 1) + delta) * 10) / 10);
    render(); syncProps(); markDirty();
  }
  // snap a line's ends onto the nearest other lines so they meet exactly:
  // each end first tries an exact end-to-end join, else aligns its coordinate.
  function meetNearestLines() {
    var el = elById(selId); if (!el || el.kind !== 'line') return;
    var tg = lineSnapTargets(el), eps = endpointTargets(el), THR = 12;
    function near(v, arr){ var b=null,bd=THR; for(var i=0;i<arr.length;i++){var d=Math.abs(v-arr[i]); if(d<bd){bd=d;b=arr[i];}} return b; }
    pushUndo();
    var horiz = Math.abs(el.ya - el.yb) < 0.05;
    // end A
    var pa = nearestPoint(el.xa, el.ya, eps, THR);
    if (pa) { el.xa=clampX(pa.x); if(horiz){el.ya=el.yb=clampY(pa.y);} else {el.xa=el.xb=clampX(pa.x); el.ya=clampY(pa.y);} }
    else if (horiz) { var a=near(el.xa,tg.xs); if(a!==null) el.xa=clampX(a); }
    else { var a2=near(el.ya,tg.ys); if(a2!==null) el.ya=clampY(a2); }
    // end B
    var pb = nearestPoint(el.xb, el.yb, eps, THR);
    if (pb) { el.xb=clampX(pb.x); if(horiz){el.ya=el.yb=clampY(pb.y);} else {el.xa=el.xb=clampX(pb.x); el.yb=clampY(pb.y);} }
    else if (horiz) { var b=near(el.xb,tg.xs); if(b!==null) el.xb=clampX(b); }
    else { var b2=near(el.yb,tg.ys); if(b2!==null) el.yb=clampY(b2); }
    render(); showProps(); markDirty();
  }
  // set text alignment (justering) from the menu
  function setAlign(j) {
    var el = elById(selId); if (!el || el.kind !== 'text') return;
    pushUndo(); el.justering = j; render(); syncProps(); markDirty();
  }

  function startDrag(ev, el, node) {
    ev.preventDefault();
    if (ev.shiftKey) { toggleSel(el.id); return; }   // shift-click toggles selection, no move
    if (!selHas(el.id)) select(el.id);               // dragging an unselected element selects just it
    if (el._locked) return;                          // locked: selectable but not draggable
    var s = pxmm();
    var startX = ev.clientX, startY = ev.clientY;
    var moved = false;
    var group = (selSet.length > 1) && selHas(el.id);
    // snapshot original positions of everything that will move (locked ones stay put)
    var movers = group ? selSet.map(elById).filter(function(m){ return m && !m._locked; }) : [el];
    var orig = movers.map(function(m){ return { m:m, xa:m.xa, ya:m.ya, xb:m.xb, yb:m.yb }; });
    function onMove(e) {
      if (!moved) { pushUndo(); moved = true; }
      var dx = (e.clientX-startX)/s, dy = -(e.clientY-startY)/s;   // mm; screen down = smaller y
      if (!group) {
        var nx = clampX(orig[0].xa + dx), ny = clampY(orig[0].ya + dy);
        if (grid) { el.xa = clampX(snap(nx)); el.ya = clampY(snap(ny)); clearGuides(); }
        else {
          var gx = snapVal(nx, snapTargetsX(el)), gy = snapVal(ny, snapTargetsY(el));
          el.xa = (gx !== null) ? gx : nx;
          el.ya = (gy !== null) ? gy : ny;
          drawGuides(gx, gy);
        }
        node.style.left = (el.xa*s) + 'px';
        node.style.top  = ((PAGE_H-el.ya)*s) + 'px';
      } else {
        // group move: translate every selected element by the same delta
        if (grid) { dx = snap(dx); dy = snap(dy); }
        orig.forEach(function(o){
          o.m.xa = clampX(o.xa + dx); o.m.ya = clampY(o.ya + dy);
          if (o.m.kind === 'line') { o.m.xb = clampX(o.xb + dx); o.m.yb = clampY(o.yb + dy); }
        });
        render();
      }
      syncProps();
    }
    function onUp() {
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('mouseup', onUp);
      clearGuides();
      if (moved) markDirty();
    }
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
  }

  // ---- smart alignment guides (snap to other elements' edges) --------------
  var SNAP_MM = 1.3;
  function snapTargetsX(self) {
    var xs = [105, MARGIN_MM, PAGE_W-MARGIN_MM];   // page centre + left/right margins
    elements.forEach(function(e){ if (e !== self && e.kind !== 'line') xs.push(e.xa); });
    if (table) table.cols.forEach(function(c){ xs.push(c.xa); });
    return xs;
  }
  function snapTargetsY(self) {
    var ys = [MARGIN_MM, PAGE_H-MARGIN_MM];         // top/bottom margins
    elements.forEach(function(e){ if (e !== self && e.kind !== 'line') ys.push(e.ya); });
    return ys;
  }
  function snapVal(v, targets) {
    var best = null, bestd = SNAP_MM;
    for (var i=0;i<targets.length;i++){ var d = Math.abs(v - targets[i]); if (d < bestd){ bestd = d; best = targets[i]; } }
    return best;
  }
  // x/y coordinates of every OTHER line's endpoints (+ element anchors), so a
  // line's end can snap onto another line to meet cleanly at a corner.
  function lineSnapTargets(self) {
    var xs = [105], ys = [];
    elements.forEach(function(e){
      if (e === self) return;
      if (e.kind === 'line') { xs.push(e.xa); xs.push(e.xb); ys.push(e.ya); ys.push(e.yb); }
      else { xs.push(e.xa); ys.push(e.ya); }
    });
    if (table) table.cols.forEach(function(c){ xs.push(c.xa); });
    return { xs: xs, ys: ys };
  }
  // all OTHER lines' endpoints as points, for exact end-to-end joining
  function endpointTargets(self) {
    var pts = [];
    elements.forEach(function(e){
      if (e === self || e.kind !== 'line') return;
      pts.push({ x:e.xa, y:e.ya }); pts.push({ x:e.xb, y:e.yb });
    });
    return pts;
  }
  function nearestPoint(x, y, pts, thr) {
    var best = null, bd = thr;
    for (var i=0;i<pts.length;i++){ var dx=x-pts[i].x, dy=y-pts[i].y, d=Math.sqrt(dx*dx+dy*dy); if (d<bd){ bd=d; best=pts[i]; } }
    return best;
  }
  function clearGuides() {
    Array.prototype.slice.call(page.querySelectorAll('.fe-guide')).forEach(function(n){ n.remove(); });
  }
  function drawGuides(xmm, ymm) {
    clearGuides();
    var s = pxmm();
    if (xmm !== null && xmm !== undefined) {
      var v = document.createElement('div'); v.className='fe-guide v'; v.style.left=(xmm*s)+'px'; page.appendChild(v);
    }
    if (ymm !== null && ymm !== undefined) {
      var h = document.createElement('div'); h.className='fe-guide h'; h.style.top=((PAGE_H-ymm)*s)+'px'; page.appendChild(h);
    }
  }
  // a distinct dot at an exact join point (both guide lines + a marker)
  function drawJoin(xmm, ymm) {
    drawGuides(xmm, ymm);
    var s = pxmm();
    var d = document.createElement('div'); d.className='fe-guide fe-join';
    d.style.left=(xmm*s)+'px'; d.style.top=((PAGE_H-ymm)*s)+'px';
    page.appendChild(d);
  }

  function startDragLine(ev, el) {
    ev.preventDefault();
    select(el.id);
    if (el._locked) return;   // locked line: selectable but not draggable
    var s = pxmm();
    var startX = ev.clientX, startY = ev.clientY;
    var oXa=el.xa,oYa=el.ya,oXb=el.xb,oYb=el.yb;
    var tg = lineSnapTargets(el);
    var moved=false;
    function onMove(e){
      if (!moved) { pushUndo(); moved=true; }
      var dxmm=(e.clientX-startX)/s, dymm=(e.clientY-startY)/s;
      var nxa=oXa+dxmm, nxb=oXb+dxmm, nya=oYa-dymm, nyb=oYb-dymm;
      if (!grid) {
        // snap the whole line so an endpoint lands on another line's x / y
        var gx=null, gxL=null;
        [nxa,nxb].forEach(function(xv){ var t=snapVal(xv,tg.xs); if(t!==null){ var a=t-xv; if(gx===null||Math.abs(a)<Math.abs(gx)){gx=a;gxL=t;} } });
        if (gx!==null){ nxa+=gx; nxb+=gx; }
        var gy=null, gyL=null;
        [nya,nyb].forEach(function(yv){ var t=snapVal(yv,tg.ys); if(t!==null){ var a=t-yv; if(gy===null||Math.abs(a)<Math.abs(gy)){gy=a;gyL=t;} } });
        if (gy!==null){ nya+=gy; nyb+=gy; }
        drawGuides(gxL, gyL);
      } else clearGuides();
      el.xa=clampX(nxa); el.xb=clampX(nxb); el.ya=clampY(nya); el.yb=clampY(nyb);
      render();
      syncProps();
    }
    function onUp(){
      document.removeEventListener('mousemove',onMove);
      document.removeEventListener('mouseup',onUp);
      clearGuides();
      if (moved) markDirty();
    }
    document.addEventListener('mousemove',onMove);
    document.addEventListener('mouseup',onUp);
  }

  function clampX(v){ return Math.min(PAGE_W, Math.max(0, Math.round(v*100)/100)); }
  function clampY(v){ return Math.min(PAGE_H, Math.max(0, Math.round(v*100)/100)); }

  // ---- properties panel ---------------------------------------------------
  var propsBox = document.getElementById('fe-props');
  var noSel = document.getElementById('fe-noselect');
  var tPropsBox = document.getElementById('fe-tprops');
  function showProps() {
    if (selId === 'table' && table) {
      propsBox.style.display='none'; noSel.style.display='none'; tPropsBox.style.display='block';
      setVal('fe-trows', Math.round(table.gen.count));
      setVal('fe-tspace', table.gen.spacing);
      setVal('fe-ttopy', table.gen.ya);
      buildColStyleList();
      return;
    }
    tPropsBox.style.display='none';
    var el = elById(selId);
    if (!el) { propsBox.style.display='none'; noSel.style.display='block'; return; }
    propsBox.style.display='block'; noSel.style.display='none';
    document.getElementById('fe-prop-kind').textContent =
      el.kind==='line' ? L.line : (el.kind==='logo' ? L.logo : L.text);
    Array.prototype.slice.call(propsBox.querySelectorAll('[data-text-only]')).forEach(function(n){ n.style.display = (el.kind==='text')?'flex':'none'; });
    Array.prototype.slice.call(propsBox.querySelectorAll('[data-line-only]')).forEach(function(n){ n.style.display = (el.kind==='line')?'flex':'none'; });
    Array.prototype.slice.call(propsBox.querySelectorAll('[data-logo-only]')).forEach(function(n){ n.style.display = (el.kind==='logo')?'flex':'none'; });
    var lk=document.getElementById('fe-lock'); if (lk) lk.checked = !!el._locked;
    syncProps();
    var cn = document.getElementById('fe-content');
    cn.innerHTML=''; if (el.kind==='text') cn.appendChild(contentNode(el, true));
    var ce = document.getElementById('fe-content-edit');
    if (ce && document.activeElement!==ce) ce.value = (el.kind==='text') ? (el.besk||'') : '';
    if (el.kind==='logo') {
      var lw=document.getElementById('fe-lw'), lh=document.getElementById('fe-lh');
      if (lw && document.activeElement!==lw) lw.value = el.xb>0 ? el.xb : 35;
      if (lh && document.activeElement!==lh) lh.value = el.yb>0 ? el.yb : (LOGO_NAT.w ? +(35*LOGO_NAT.h/LOGO_NAT.w).toFixed(1) : 20);
    }
    // delete button vs core-note
    var delBtn = document.getElementById('fe-delete'), coreNote = document.getElementById('fe-core-note');
    if (el.kind==='logo') { delBtn.style.display='none'; coreNote.style.display='none'; }
    else if (isCore(el)) { delBtn.style.display='none'; coreNote.style.display='inline'; }
    else { delBtn.style.display='inline-block'; coreNote.style.display='none'; }
  }
  function syncProps() {
    var el = elById(selId); if (!el) return;
    setVal('fe-x', el.xa); setVal('fe-y', el.ya);
    setVal('fe-x2', el.xb); setVal('fe-y2', el.yb);
    setVal('fe-str', el.str); setVal('fe-strline', el.str);
    var js=document.getElementById('fe-just'); if (js) js.value = el.justering;
    document.getElementById('fe-fed').checked = !!el.fed;
    document.getElementById('fe-kursiv').checked = !!el.kursiv;
    document.getElementById('fe-color').value = rgbToHex(colorNumToRgb(el.color));
  }
  function setVal(id,v){ var n=document.getElementById(id); if (n && document.activeElement!==n) n.value = v; }

  function bindProp(id, fn) {
    var n=document.getElementById(id);
    if (!n) return;
    n.addEventListener('change', function(){ var el=elById(selId); if(!el) return; pushUndo(); fn(el, n); render(); markDirty(); });
    n.addEventListener('input', function(){ var el=elById(selId); if(!el) return; fn(el, n); render(); });
  }
  bindProp('fe-x', function(el,n){ el.xa=clampX(parseFloat(n.value)||0); });
  bindProp('fe-y', function(el,n){ el.ya=clampY(parseFloat(n.value)||0); });
  bindProp('fe-x2', function(el,n){ el.xb=clampX(parseFloat(n.value)||0); });
  bindProp('fe-y2', function(el,n){ el.yb=clampY(parseFloat(n.value)||0); });
  bindProp('fe-str', function(el,n){ el.str=parseFloat(n.value)||0; });
  bindProp('fe-strline', function(el,n){ el.str=parseFloat(n.value)||0; });
  bindProp('fe-just', function(el,n){ el.justering=n.value; });
  bindProp('fe-fed', function(el,n){ el.fed=n.checked?1:0; });
  bindProp('fe-kursiv', function(el,n){ el.kursiv=n.checked?1:0; });
  bindProp('fe-color', function(el,n){ el.color=rgbToColorNum.apply(null, hexToRgb(n.value)); });

  // content editing (text elements): live preview on input, undo checkpoint on change
  (function(){
    var ce=document.getElementById('fe-content-edit'); if(!ce) return;
    ce.addEventListener('input', function(){
      var el=elById(selId); if(!el||el.kind!=='text') return;
      el.besk=ce.value;
      // update just this element's node (smooth typing) + the side preview
      var node=page.querySelector('.fe-el[data-id="'+el.id+'"]');
      if(node){ node.innerHTML=''; node.appendChild(contentNode(el, false)); } else { render(); }
      var cn=document.getElementById('fe-content'); cn.innerHTML=''; cn.appendChild(contentNode(el, true));
    });
    ce.addEventListener('focus', function(){ var el=elById(selId); if(el&&el.kind==='text'){ pushUndo(); } });
    ce.addEventListener('change', function(){ var el=elById(selId); if(el&&el.kind==='text'){ markDirty(); } });
    // variable picker: insert the chosen $token at the cursor
    var vp=document.getElementById('fe-var-picker');
    if (vp) vp.addEventListener('change', function(){
      var el=elById(selId); if(!el||el.kind!=='text'||!vp.value){ vp.value=''; return; }
      pushUndo();
      var start=ce.selectionStart, end=ce.selectionEnd, v=ce.value;
      var ins=vp.value; if(start>0 && v.charAt(start-1)!==' ') ins=' '+ins;
      ce.value=v.slice(0,start)+ins+v.slice(end);
      var pos=start+ins.length; ce.setSelectionRange(pos,pos); ce.focus();
      el.besk=ce.value; render();
      var cn=document.getElementById('fe-content'); cn.innerHTML=''; cn.appendChild(contentNode(el, true));
      markDirty(); vp.value='';
    });
  })();

  document.getElementById('fe-add-text').addEventListener('click', addText);
  // "+ Line" -> draw mode: click-drag on the sheet to draw a line where you want
  var drawLineMode = false;
  document.getElementById('fe-add-line').addEventListener('click', function(){
    drawLineMode = true; page.style.cursor = 'crosshair';
    flashStatus(DRAW_LINE_HINT, '#1769ff');
  });
  var SVGNS = 'http://www.w3.org/2000/svg';
  // capture phase so it runs before element/line handlers and can take over
  page.addEventListener('mousedown', function(e){
    if (!drawLineMode) return;
    e.preventDefault(); e.stopPropagation();
    var s = pxmm(), rect = page.getBoundingClientRect();
    var sxMM = (e.clientX-rect.left)/s, syMM = (e.clientY-rect.top)/s;   // mm from top-left
    var prev = document.createElementNS(SVGNS,'line');
    prev.setAttribute('stroke','#1769ff'); prev.setAttribute('stroke-width',2);
    prev.setAttribute('stroke-dasharray','4 3'); prev.setAttribute('x1',sxMM*s); prev.setAttribute('y1',syMM*s);
    svg.appendChild(prev);
    function endpt(ev){
      var cx=(ev.clientX-rect.left)/s, cy=(ev.clientY-rect.top)/s;
      var horiz = Math.abs(cx-sxMM) >= Math.abs(cy-syMM);
      return { horiz:horiz, ex: horiz?cx:sxMM, ey: horiz?syMM:cy };
    }
    function mv(ev){ var p=endpt(ev); prev.setAttribute('x2',p.ex*s); prev.setAttribute('y2',p.ey*s); }
    function up(ev){
      document.removeEventListener('mousemove',mv,true); document.removeEventListener('mouseup',up,true);
      prev.remove(); drawLineMode=false; page.style.cursor='';
      var p=endpt(ev);
      // engine coords: xa=x from left, ya=y from BOTTOM
      var xa=clampX(sxMM), ya=clampY(PAGE_H-syMM), xb=clampX(p.ex), yb=clampY(PAGE_H-p.ey);
      if (p.horiz) yb=ya; else xb=xa;
      var len = p.horiz ? Math.abs(xb-xa) : Math.abs(ya-yb);
      if (len < 2) { render(); return; }   // too short -> cancel
      pushUndo();
      var el={ id: nextTmpId--, art:1, kind:'line', besk:'', xa:xa, ya:ya, xb:xb, yb:yb,
               str:1, color:0, justering:'V', font:'Helvetica', fed:0, kursiv:0, side:'A' };
      elements.push(el); selId=el.id; render(); showProps(); markDirty();
    }
    document.addEventListener('mousemove',mv,true); document.addEventListener('mouseup',up,true);
  }, true);

  // Map the standard captions toward the account's UI language on load, using
  // the shared translator (feTranslateCaption / TR_PAIRS). Only text captions
  // are touched, never variables/chips. Reversible + undoable. Returns count.
  function applyTranslation() {
    var toEN = !DK_UI;               // English UI -> template captions to English
    var n = 0;
    elements.forEach(function(el){
      if (el.kind!=='text') return;
      var t = String(el.besk||''); if (!t.trim()) return;
      var out = feTranslateCaption(t, toEN);
      if (out !== t) { el.besk = out; n++; }
    });
    return n;
  }

  // logo size inputs + upload
  bindProp('fe-lw', function(el,n){ if(el.kind!=='logo')return; el.xb=Math.max(2, parseFloat(n.value)||35); });
  bindProp('fe-lh', function(el,n){ if(el.kind!=='logo')return; el.yb=Math.max(2, parseFloat(n.value)||20); });
  (function(){
    var btn=document.getElementById('fe-logo-upload'), fin=document.getElementById('fe-logo-file');
    if(!btn||!fin) return;
    btn.addEventListener('click', function(){ fin.click(); });
    fin.addEventListener('change', function(){
      if(!fin.files || !fin.files[0]) return;
      var el=elById(selId); if(!el||el.kind!=='logo') return;
      flashStatus(<?php echo json_encode($T('Uploader logo…','Uploading logo…')); ?>, '#444');
      var fd=new FormData(); fd.append('logo', fin.files[0]);
      fetch('formeditor.php?fe_action=logo_upload', { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); }).then(function(j){
          if(j && j.ok){
            LOGO_URL=j.url; LOGO_NAT={w:j.w,h:j.h};
            pushUndo();
            if(!(el.xb>0)){ el.xb=35; el.yb=Math.round((35*j.h/j.w)*10)/10; }
            render(); showProps(); markDirty();
            flashStatus(<?php echo json_encode($T('Logo uploadet – husk Gem','Logo uploaded – remember Save')); ?>, '#2a7d2a');
          } else if(j && j.error==='session'){ flashStatus(L.session,'#c00'); }
          else { flashStatus(<?php echo json_encode($T('Kunne ikke uploade (kun PNG/JPG, maks 5 MB)','Upload failed (PNG/JPG only, max 5 MB)')); ?>,'#c00'); }
          fin.value='';
        }).catch(function(){ flashStatus(L.neterr,'#c00'); fin.value=''; });
    });
    var rbtn=document.getElementById('fe-logo-remove');
    if(rbtn) rbtn.addEventListener('click', removeLogo);
  })();

  // table property inputs
  function bindTableProp(id, fn) {
    var n=document.getElementById(id); if (!n) return;
    n.addEventListener('change', function(){ if(!table) return; pushUndo(); fn(n); render(); markDirty(); });
    n.addEventListener('input', function(){ if(!table) return; fn(n); render(); });
  }
  bindTableProp('fe-trows',  function(n){ table.gen.count=Math.max(1, Math.round(parseFloat(n.value)||1)); });
  bindTableProp('fe-tspace', function(n){ table.gen.spacing=parseFloat(n.value)||table.gen.spacing; });
  bindTableProp('fe-ttopy',  function(n){ table.gen.ya=clampY(parseFloat(n.value)||0); });

  // ---- keyboard nudge -----------------------------------------------------
  document.addEventListener('keydown', function(e){
    if ((e.ctrlKey||e.metaKey) && (e.key==='z'||e.key==='Z') && e.shiftKey) { e.preventDefault(); redo(); return; }
    if ((e.ctrlKey||e.metaKey) && (e.key==='y'||e.key==='Y')) { e.preventDefault(); redo(); return; }
    if ((e.ctrlKey||e.metaKey) && (e.key==='z'||e.key==='Z')) { e.preventDefault(); undo(); return; }
    var inField = document.activeElement && (document.activeElement.tagName==='INPUT' || document.activeElement.tagName==='TEXTAREA' || document.activeElement.tagName==='SELECT');
    // ---- editor clipboard shortcuts (copy/paste like a real editor) --------
    if ((e.ctrlKey||e.metaKey) && !inField) {
      var k=(e.key||'').toLowerCase();
      if (k==='c') { feCopy(); return; }
      if (k==='v') { e.preventDefault(); fePaste(); return; }
      if (k==='d') { e.preventDefault(); feDuplicate(); return; }
      if (k==='a') { e.preventDefault(); feSelectAll(); return; }
    }
    if ((e.key==='Delete') && (selId!==null || selSet.length) && !inField) { e.preventDefault(); deleteSelected(); return; }
    if (['ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].indexOf(e.key)<0) return;
    if (inField) return;
    var movers = ((selSet.length>1) ? selSet.map(elById).filter(Boolean)
               : (elById(selId) ? [elById(selId)] : [])).filter(function(el){ return !el._locked; });
    if (!movers.length) return;
    e.preventDefault();
    var step = e.shiftKey ? 5 : 0.5;
    var dx=0, dy=0;
    if (e.key==='ArrowUp') dy=step; else if (e.key==='ArrowDown') dy=-step;
    else if (e.key==='ArrowLeft') dx=-step; else dx=step;   // ArrowRight
    pushUndo();
    movers.forEach(function(el){
      el.xa=clampX(el.xa+dx); el.ya=clampY(el.ya+dy);
      if(el.kind==='line'){ el.xb=clampX(el.xb+dx); el.yb=clampY(el.yb+dy); }
    });
    render(); syncProps(); markDirty();
  });

  function undo() {
    if (!undoStack.length) return;
    redoStack.push(snapshot());
    restore(undoStack.pop());
    selId = null; selSet = []; render(); showProps(); updateAlignBar(); markDirty();
  }
  function redo() {
    if (!redoStack.length) return;
    undoStack.push(snapshot());
    restore(redoStack.pop());
    selId = null; selSet = []; render(); showProps(); updateAlignBar(); markDirty();
  }
  document.getElementById('fe-undo').addEventListener('click', undo);
  var _redoBtn = document.getElementById('fe-redo'); if (_redoBtn) _redoBtn.addEventListener('click', redo);
  document.getElementById('fe-delete').addEventListener('click', deleteSelected);

  // ---- dirty / save -------------------------------------------------------
  var dirty = false;
  function markDirty(){ dirty=true; statusEl.textContent=L.unsaved; statusEl.style.color='#b26a00'; scheduleAutosave(); }
  window.addEventListener('beforeunload', function(e){ if (dirty){ e.preventDefault(); e.returnValue=''; } });

  document.getElementById('fe-save').addEventListener('click', function(){
    // quality guard: warn about unedited "New text" / empty placeholders going live
    var ph = elements.filter(function(el){ return el.kind==='text' && (el.besk===NEW_TEXT || !String(el.besk||'').trim()); }).length;
    if (ph > 0 && !window.confirm(ph + (DK_UI ? ' tomme/uredigerede tekstfelter (fx “Ny tekst”) kommer med på udskriften. Gem & aktivér alligevel?'
                                             : ' empty/unedited text fields (e.g. “New text”) will appear on the print. Save & activate anyway?'))) return;
    if (feClampText()) { render(); if (selId) syncProps(); }   // never save a title that would print off the top
    statusEl.textContent=L.saving; statusEl.style.color='#444';
    var payload = elements.map(function(el){
      return { id: el.id, art: el.art, xa: el.xa, ya: el.ya, xb: el.xb, yb: el.yb, str: el.str, color: el.color, justering: el.justering, fed: el.fed, kursiv: el.kursiv, besk: el.besk, font: el.font, side: el.side };
    });
    if (table) {
      // generelt row: xa=row count, ya=top Y, xb=row spacing
      payload.push({ id: table.gen.id, art: 3, xa: table.gen.count, ya: table.gen.ya, xb: table.gen.spacing });
      table.cols.forEach(function(c){ payload.push({ id: c.id, art: 3, xa: c.xa, ya: c.ya, xb: c.xb, color: c.color, justering: c.just, fed: c.fed }); });
    }
    var body = { form_nr: formNr, sprog: sprog, elements: payload, deleted: deletedIds };
    fetch('formeditor.php?fe_action=save', {
      method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'},
      body: JSON.stringify(body), credentials:'same-origin'
    }).then(function(r){ return r.json(); }).then(function(j){
      if (j && j.ok) {
        dirty=false; deletedIds=[]; clearAutosave();   // work is live now
        // patch newly-inserted elements with their real DB ids
        if (j.inserted) { elements.forEach(function(el){ if (el.id<=0 && j.inserted[el.id]!=null) el.id = j.inserted[el.id]; }); }
        hideDraftBanner();   // activating supersedes the draft (server deleted it)
        statusEl.textContent=L.saved.replace('%s', j.updated); statusEl.style.color='#2a7d2a';
      }
      else if (j && j.error==='session') { statusEl.textContent=L.session; statusEl.style.color='#c00'; }
      else { statusEl.textContent=L.failed; statusEl.style.color='#c00'; }
    }).catch(function(){ statusEl.textContent=L.neterr; statusEl.style.color='#c00'; });
  });

  // ---- draft: save working state without going live ------------------------
  function currentState() {
    return { elements: elements, table: table, deletedIds: deletedIds };
  }
  document.getElementById('fe-savedraft').addEventListener('click', function(){
    statusEl.textContent=L.savingDraft; statusEl.style.color='#444';
    fetch('formeditor.php?fe_action=savedraft', {
      method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'},
      body: JSON.stringify({ form_nr: formNr, sprog: sprog, state: currentState() }), credentials:'same-origin'
    }).then(function(r){ return r.json(); }).then(function(j){
      if (j && j.ok) { dirty=false; statusEl.textContent=L.draftSaved; statusEl.style.color='#2a7d2a'; }
      else if (j && j.error==='session') { statusEl.textContent=L.session; statusEl.style.color='#c00'; }
      else { statusEl.textContent=L.failed; statusEl.style.color='#c00'; }
    }).catch(function(){ statusEl.textContent=L.neterr; statusEl.style.color='#c00'; });
  });

  var draftBanner = document.getElementById('fe-draft-banner');
  function hideDraftBanner(){ if (draftBanner) draftBanner.style.display='none'; }
  function fmtTs(ts){ try { return new Date(ts*1000).toLocaleString(); } catch(e){ return ''; } }
  function showDraftBanner(ts){
    if (!draftBanner) return;
    document.getElementById('fe-draft-text').textContent = L.draftFound.replace('%s', fmtTs(ts));
    draftBanner.style.display='flex';
  }
  document.getElementById('fe-draft-continue').addEventListener('click', function(){
    if (!FE_DRAFT || !FE_DRAFT.state) { hideDraftBanner(); return; }
    var st = FE_DRAFT.state;
    if (st.elements) elements = st.elements;
    if (st.table !== undefined) table = st.table;
    deletedIds = st.deletedIds || [];
    // keep temp ids from colliding with any new additions this session
    var minId = 0; elements.forEach(function(el){ if (el.id < minId) minId = el.id; });
    if (minId < 0) nextTmpId = minId - 1;
    selId = null; undoStack = []; render(); showProps(); hideDraftBanner(); markDirty();
    flashStatus(L.draftLoaded, '#2a7d2a');
  });
  document.getElementById('fe-draft-discard').addEventListener('click', function(){
    hideDraftBanner();
    fetch('formeditor.php?fe_action=discarddraft', {
      method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'},
      body: JSON.stringify({ form_nr: formNr, sprog: sprog }), credentials:'same-origin'
    }).catch(function(){});
  });

  // ---- autosave (Tier 3): local safety net so work survives a tab close ---
  // Debounced snapshot to localStorage (client-only, never hits the server or
  // goes live). On reload we offer to restore it. Cleared on Save & activate.
  var AUTO_KEY = 'fe_autosave_' + formNr + '_' + String(sprog).replace(/[^A-Za-z0-9_]/g,'_');
  var autosaveArmed = false, autosaveTimer = null, autoBanner = null;
  function scheduleAutosave(){
    if (!autosaveArmed) return;
    if (autosaveTimer) clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(function(){
      try { localStorage.setItem(AUTO_KEY, JSON.stringify({ ts: Math.floor(Date.now()/1000), state: currentState() })); } catch(e){}
    }, 1500);
  }
  function clearAutosave(){ try { localStorage.removeItem(AUTO_KEY); } catch(e){} }
  function readAutosave(){ try { var s=JSON.parse(localStorage.getItem(AUTO_KEY)||'null'); return (s&&s.state&&s.state.elements)?s:null; } catch(e){ return null; } }
  function loadState(st){
    if (st.elements) elements = st.elements;
    if (st.table !== undefined) table = st.table;
    deletedIds = st.deletedIds || [];
    var minId=0; elements.forEach(function(el){ if(el.id<minId) minId=el.id; });
    if (minId<0) nextTmpId = minId-1;
    selId=null; selSet=[]; undoStack=[]; render(); showProps(); updateAlignBar(); markDirty();
  }
  function showAutosaveBanner(ts){
    if (!autoBanner){
      autoBanner=document.createElement('div'); autoBanner.id='fe-autosave-banner';
      autoBanner.style.cssText='display:flex;align-items:center;gap:12px;padding:8px 14px;background:#fff8e1;border-bottom:1px solid #e6cf8b;color:#7a5a00;font-family:Arial,Helvetica,sans-serif;font-size:13px;';
      var txt=document.createElement('span'); txt.id='fe-autosave-text';
      var b1=document.createElement('button'); b1.type='button'; b1.className='fe-btn'; b1.style.cssText='background:#1769ff;color:#fff;border:1px solid #1257cc;border-radius:3px;'; b1.textContent=DK_UI?'Gendan':'Restore';
      var b2=document.createElement('button'); b2.type='button'; b2.className='fe-btn'; b2.style.cssText='border:1px solid #d9a1a1;color:#b23b3b;background:#fff;border-radius:3px;'; b2.textContent=DK_UI?'Kassér':'Discard';
      autoBanner.appendChild(txt); autoBanner.appendChild(b1); autoBanner.appendChild(b2);
      document.getElementById('fe-wrap').insertBefore(autoBanner, document.getElementById('fe-main'));
      b1.addEventListener('click', function(){ var s=readAutosave(); if(s){ loadState(s.state); flashStatus(DK_UI?'Gendannet – Gem & aktivér for at gå live':'Restored – Save & activate to go live','#2a7d2a'); } autoBanner.style.display='none'; });
      b2.addEventListener('click', function(){ clearAutosave(); autoBanner.style.display='none'; });
    }
    document.getElementById('fe-autosave-text').textContent = (DK_UI?'Du har ugemte ændringer fra ':'You have unsaved changes from ')+fmtTs(ts)+'.';
    autoBanner.style.display='flex';
  }

  // ---- reset this form + variant to Saldi's standard ----------------------
  document.getElementById('fe-reset').addEventListener('click', function(){
    if (!window.confirm(L.resetConfirm)) return;
    statusEl.textContent=L.resetting; statusEl.style.color='#444';
    fetch('formeditor.php?fe_action=reset', {
      method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'},
      body: JSON.stringify({ form_nr: formNr, sprog: sprog }), credentials:'same-origin'
    }).then(function(r){ return r.json(); }).then(function(j){
      if (j && j.ok) { dirty=false; statusEl.textContent=L.resetDone; statusEl.style.color='#2a7d2a'; setTimeout(function(){ location.reload(); }, 400); }
      else if (j && j.error==='session') { statusEl.textContent=L.session; statusEl.style.color='#c00'; }
      else if (j && j.error==='nostd')   { statusEl.textContent=L.resetNoStd; statusEl.style.color='#c00'; }
      else { statusEl.textContent=L.failed; statusEl.style.color='#c00'; }
    }).catch(function(){ statusEl.textContent=L.neterr; statusEl.style.color='#c00'; });
  });

  // ---- toolbar ------------------------------------------------------------
  var zEl = document.getElementById('fe-zoom'), zVal=document.getElementById('fe-zoom-val');
  function setZoom(z){
    z = Math.max(0.5, Math.min(2.0, z));
    zoom = z;
    var pct = Math.round(z*100);
    zEl.value = pct; zVal.textContent = pct+'%';
    render();
  }
  function fitZoom(widthOnly){
    var sc=document.getElementById('fe-canvas-scroll'); if(!sc) return;
    var pad=24, avW=sc.clientWidth-2*pad, avH=sc.clientHeight-2*pad;
    var zw = avW/(PAGE_W*BASE_PXMM), zh = avH/(PAGE_H*BASE_PXMM);
    setZoom(widthOnly ? zw : Math.min(zw, zh));
  }
  zEl.addEventListener('input', function(){ zoom=parseInt(zEl.value,10)/100; zVal.textContent=zEl.value+'%'; render(); });
  document.getElementById('fe-zoom-fit').addEventListener('click', function(){ fitZoom(false); });
  document.getElementById('fe-zoom-width').addEventListener('click', function(){ fitZoom(true); });
  document.getElementById('fe-zoom-100').addEventListener('click', function(){ setZoom(1.0); });

  // ---- Help & keyboard-shortcuts card (Tier 3) ----------------------------
  var helpModal=null;
  function buildHelpModal(){
    if(helpModal) return helpModal;
    var DA=DK_UI;
    function row(k,v){ return '<tr><td class="k">'+k+'</td><td>'+v+'</td></tr>'; }
    var shortcuts='<table class="fe-help-tbl">'
      + row('Ctrl+Z / Ctrl+Y', DA?'Fortryd / Gentag':'Undo / Redo')
      + row('Ctrl+C / Ctrl+V', DA?'Kopiér / indsæt (også til andre formularer)':'Copy / paste (also into other forms)')
      + row('Ctrl+D', DA?'Dupliker det valgte':'Duplicate selection')
      + row('Ctrl+A', DA?'Vælg alt':'Select all')
      + row(DA?'Piletaster':'Arrow keys', DA?'Flyt 0,5&nbsp;mm (Shift = 5&nbsp;mm)':'Move 0.5&nbsp;mm (Shift = 5&nbsp;mm)')
      + row('Delete', DA?'Slet det valgte':'Delete selected')
      + row('Shift + '+(DA?'klik':'click'), DA?'Vælg flere felter':'Add to selection')
      + row(DA?'Træk på tomt ark':'Drag on empty sheet', DA?'Ramme-vælg flere':'Rubber-band select')
      + '</table>';
    var feats='<table class="fe-help-tbl">'
      + row(DA?'Skabeloner':'Templates', DA?'Færdige udseender + brand-farver/skrift':'Ready looks + brand colours/fonts')
      + row(DA?'Linealer':'Rulers', DA?'mm-skala + marginer at snappe til':'mm scale + margins to snap to')
      + row(DA?'Felter':'Fields', DA?'Søg &amp; vælg ethvert element (også skjulte)':'Search &amp; select any element (even hidden)')
      + row(DA?'Gitter':'Grid', DA?'5&nbsp;mm snap-gitter':'5&nbsp;mm snap grid')
      + row(DA?'Vis kladde':'Show draft', DA?'Forhåndsvis med eksempeldata':'Preview with sample data')
      + '</table>';
    helpModal=document.createElement('div'); helpModal.id='fe-help-modal'; helpModal.style.display='none';
    helpModal.innerHTML='<div class="fe-dlg" style="width:560px;">'
      +'<div class="fe-dlg-head"><span>&#63;&nbsp; '+(DA?'Hjælp':'Help')+'</span><button type="button" class="fe-x" id="fe-help-close" title="'+(DA?'Luk':'Close')+'">&times;</button></div>'
      +'<div class="fe-dlg-body"><div class="fe-help-cols">'
      +'<div><h4>'+(DA?'Tastaturgenveje':'Keyboard shortcuts')+'</h4>'+shortcuts+'</div>'
      +'<div><h4>'+(DA?'Funktioner':'Features')+'</h4>'+feats+'</div>'
      +'</div><div class="fe-dlg-sub" style="margin-top:12px;">'+(DA?'Tip: intet går live før du trykker “Gem &amp; aktivér”.':'Tip: nothing goes live until you press “Save &amp; activate”.')+'</div>'
      +'</div><div class="fe-dlg-foot"><button type="button" class="fe-btn" id="fe-help-tour" style="margin-right:8px;">&#127891; '+(DA?'Fuld guide':'Full tour')+'</button><button type="button" class="fe-btn" id="fe-help-done" style="background:#1769ff;color:#fff;border:1px solid #1257cc;border-radius:3px;">'+(DA?'Luk':'Close')+'</button></div></div>';
    document.getElementById('fe-wrap').appendChild(helpModal);
    function close(){ helpModal.style.display='none'; }
    helpModal.addEventListener('mousedown', function(e){ if(e.target===helpModal) close(); });
    helpModal.querySelector('#fe-help-close').addEventListener('click', close);
    helpModal.querySelector('#fe-help-done').addEventListener('click', close);
    helpModal.querySelector('#fe-help-tour').addEventListener('click', function(){ close(); startTour(fullTourSteps); });
    return helpModal;
  }
  document.getElementById('fe-help-btn').addEventListener('click', function(){ buildHelpModal().style.display='flex'; });

  // ---- e-mail text editor (the message sent with the PDF; art=5) ----------
  // Body is stored with <br> (Saldi sends the mail as HTML). We show real line
  // breaks in the textarea and convert back on save, so the client never sees HTML.
  function mailNl2br(s){ return String(s||'').replace(/\r?\n/g,'<br>'); }
  function mailSanitize(html){
    var d=document.createElement('div'); d.innerHTML=String(html||'');
    Array.prototype.forEach.call(d.querySelectorAll('script,style'), function(n){ n.remove(); });
    Array.prototype.forEach.call(d.querySelectorAll('*'), function(n){
      for(var i=n.attributes.length-1;i>=0;i--){ if(/^on/i.test(n.attributes[i].name)) n.removeAttribute(n.attributes[i].name); }
    });
    return d.innerHTML;
  }
  var EMAIL_TEMPLATES = [
    { name:(DK_UI?'Standard':'Standard'),
      subject:(DK_UI?'Faktura':'Invoice'),
      body:(DK_UI?'Hej\n\nHermed fremsendes fakturaen. Beløbet bedes betalt inden forfaldsdatoen.\n\nMed venlig hilsen':'Hello\n\nPlease find your invoice attached. Kindly pay by the due date.\n\nKind regards') },
    { name:(DK_UI?'Venlig':'Friendly'),
      subject:(DK_UI?'Tak for din ordre':'Thank you for your order'),
      body:(DK_UI?'Hej\n\nTusind tak for din ordre! Din faktura er vedhæftet. Sig endelig til, hvis du har spørgsmål.\n\nDe bedste hilsner':'Hi\n\nThank you so much for your order! Your invoice is attached. Just let us know if you have any questions.\n\nAll the best') },
    { name:(DK_UI?'Formel':'Formal'),
      subject:(DK_UI?'Faktura vedhæftet':'Invoice enclosed'),
      body:(DK_UI?'Til rette vedkommende\n\nVedhæftet finder De fakturaen til betaling i henhold til de aftalte betalingsbetingelser.\n\nMed venlig hilsen':'To whom it may concern\n\nPlease find enclosed the invoice for payment in accordance with the agreed terms.\n\nYours sincerely') },
    { name:(DK_UI?'Påmindelse':'Reminder'),
      subject:(DK_UI?'Venlig betalingspåmindelse':'Friendly payment reminder'),
      body:(DK_UI?'Hej\n\nVi kan se, at fakturaen endnu ikke er betalt. Måske er den blot overset? Vi vedhæfter den igen.\n\nMed venlig hilsen':'Hello\n\nWe notice the invoice hasn’t been paid yet — perhaps it was simply overlooked? We’re attaching it again.\n\nKind regards') }
  ];
  var mailModal=null;
  function buildMailModal(){
    if(mailModal) return mailModal;
    var attachRow = FE_MAIL.has_attach
      ? '<div class="fe-mail-row"><label>'+(DK_UI?'Vedhæftning (bilagsnavn)':'Attachment (file name)')+'</label><input type="text" id="fe-mail-attach"></div>' : '';
    mailModal=document.createElement('div'); mailModal.id='fe-mail-modal'; mailModal.style.display='none';
    mailModal.innerHTML='<div class="fe-dlg" style="width:600px;max-width:94vw;">'
      +'<div class="fe-dlg-head"><span>&#9993; '+(DK_UI?'E-mailtekst':'Email text')+'</span><button type="button" class="fe-x" id="fe-mail-close" title="'+(DK_UI?'Luk':'Close')+'">&times;</button></div>'
      +'<div class="fe-dlg-body">'
      +'<div class="fe-dlg-sub">'+(DK_UI?'Teksten der sendes sammen med PDF&apos;en. Felter som $ordre_fakturanr bevares.':'The message sent together with the PDF. Fields like $ordre_fakturanr are kept.')+'</div>'
      +'<div class="fe-mail-row"><label>'+(DK_UI?'Skabeloner':'Templates')+'</label><span id="fe-mail-tpls" style="display:flex;gap:6px;flex-wrap:wrap;"></span></div>'
      +'<div class="fe-mail-row"><label>'+(DK_UI?'Emne':'Subject')+'</label><input type="text" id="fe-mail-subject"></div>'
      +'<div class="fe-mail-row" style="align-items:flex-start;"><label>'+(DK_UI?'Besked':'Message')+'</label>'
      +'<div style="flex:1;"><div class="fe-mail-format" id="fe-mail-format">'
      +'<button type="button" data-cmd="bold" title="'+(DK_UI?'Fed':'Bold')+'" style="font-weight:bold;">B</button>'
      +'<button type="button" data-cmd="italic" title="'+(DK_UI?'Kursiv':'Italic')+'" style="font-style:italic;">I</button>'
      +'<button type="button" data-cmd="underline" title="'+(DK_UI?'Understreget':'Underline')+'" style="text-decoration:underline;">U</button>'
      +'<button type="button" data-cmd="insertUnorderedList" title="'+(DK_UI?'Punktliste':'Bullet list')+'">&#8226;</button>'
      +'<button type="button" data-cmd="hr" title="'+(DK_UI?'Linje':'Divider')+'">&#8213;</button>'
      +'<button type="button" data-cmd="clear" title="'+(DK_UI?'Ryd formatering':'Clear formatting')+'">&#10008;</button>'
      +'</div><div class="fe-mail-rte" id="fe-mail-body" contenteditable="true" spellcheck="false"></div></div></div>'
      + attachRow
      +'</div>'
      +'<div class="fe-dlg-foot"><span id="fe-mail-status" class="fe-dlg-sub" style="margin:0;margin-right:auto;"></span>'
      +'<button type="button" class="fe-btn" id="fe-mail-cancel" style="margin-right:8px;">'+(DK_UI?'Luk':'Close')+'</button>'
      +'<button type="button" class="fe-btn" id="fe-mail-save" style="background:#1769ff;color:#fff;border:1px solid #1257cc;border-radius:3px;">'+(DK_UI?'Gem e-mailtekst':'Save email text')+'</button></div>'
      +'</div>';
    document.getElementById('fe-wrap').appendChild(mailModal);
    var bodyEl=mailModal.querySelector('#fe-mail-body');
    mailModal.querySelector('#fe-mail-subject').value = FE_MAIL.subject||'';
    bodyEl.innerHTML = mailNl2br(FE_MAIL.body);
    if(FE_MAIL.has_attach) mailModal.querySelector('#fe-mail-attach').value = FE_MAIL.attach||'';
    var tplHost=mailModal.querySelector('#fe-mail-tpls');
    EMAIL_TEMPLATES.forEach(function(t){
      var b=document.createElement('button'); b.type='button'; b.className='fe-mini-btn'; b.textContent=t.name;
      b.addEventListener('click', function(){ mailModal.querySelector('#fe-mail-subject').value=t.subject; bodyEl.innerHTML=mailNl2br(t.body); });
      tplHost.appendChild(b);
    });
    Array.prototype.forEach.call(mailModal.querySelectorAll('#fe-mail-format button'), function(b){
      b.addEventListener('mousedown', function(e){ e.preventDefault(); });
      b.addEventListener('click', function(){
        var cmd=b.getAttribute('data-cmd'); bodyEl.focus();
        try { document.execCommand('styleWithCSS', false, false); } catch(err){}
        if(cmd==='hr') document.execCommand('insertHorizontalRule');
        else if(cmd==='clear') document.execCommand('removeFormat');
        else document.execCommand(cmd);
      });
    });
    function close(){ mailModal.style.display='none'; }
    mailModal.addEventListener('mousedown', function(e){ if(e.target===mailModal) close(); });
    mailModal.querySelector('#fe-mail-close').addEventListener('click', close);
    mailModal.querySelector('#fe-mail-cancel').addEventListener('click', close);
    mailModal.querySelector('#fe-mail-save').addEventListener('click', function(){
      var st=mailModal.querySelector('#fe-mail-status'); st.textContent=(DK_UI?'Gemmer…':'Saving…'); st.style.color='#444';
      var body={ form_nr:formNr, sprog:sprog,
        subject:mailModal.querySelector('#fe-mail-subject').value,
        body:mailSanitize(mailModal.querySelector('#fe-mail-body').innerHTML) };
      if(FE_MAIL.has_attach) body.attach=mailModal.querySelector('#fe-mail-attach').value;
      fetch('formeditor.php?fe_action=save_mail',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(body),credentials:'same-origin'})
        .then(function(r){return r.json();}).then(function(j){
          if(j&&j.ok){ FE_MAIL.subject=body.subject; FE_MAIL.body=body.body; if(FE_MAIL.has_attach) FE_MAIL.attach=body.attach;
            st.textContent=(DK_UI?'Gemt ✓':'Saved ✓'); st.style.color='#2a7d2a'; }
          else if(j&&j.error==='session'){ st.textContent=(DK_UI?'Session udløbet':'Session expired'); st.style.color='#c00'; }
          else { st.textContent=(DK_UI?'Kunne ikke gemme':'Could not save'); st.style.color='#c00'; }
        }).catch(function(){ st.textContent=(DK_UI?'Netværksfejl':'Network error'); st.style.color='#c00'; });
    });
    return mailModal;
  }
  document.getElementById('fe-mail-btn').addEventListener('click', function(){ buildMailModal().style.display='flex'; });

  // ---- language toggle: switch all captions between Dansk and English -------
  // Shared DA<->EN dictionary. Longest phrases win; $variables and table column
  // keys are never touched. Reversible (Ctrl+Z); Save & activate to keep.
  var TR_PAIRS = [
    ['Ordrebekræftelse','Order confirmation'], ['Købsfaktura','Purchase invoice'], ['Kreditnota','Credit note'],
    ['Følgeseddel','Delivery note'], ['Pakkeseddel','Packing slip'], ['Kontoudtog','Account statement'],
    ['Kontoudskrift','Account statement'], ['Indkøbsforslag','Purchase suggestion'], ['Rekvisition','Requisition'],
    ['Plukliste','Picking list'], ['Rykkerskrivelse','Reminder'], ['Rykkerbrev','Reminder'],
    ['Faktura','Invoice'], ['Tilbud','Quote'], ['Ordre','Order'],
    ['1. Rykker','1st reminder'], ['2. Rykker','2nd reminder'], ['3. Rykker','3rd reminder'], ['Rykker','Reminder'],
    ['Deres ordre nr','Your order no.'], ['Vores ordre nr','Our order no.'], ['Deres ordre','Your order'],
    ['Ordrenummer','Order number'], ['Ordrenr','Order no.'], ['Ordredato','Order date'],
    ['Fakturanummer','Invoice number'], ['Fakturanr','Invoice no.'], ['Fakturadato','Invoice date'],
    ['Kundenummer','Customer number'], ['Kundenr','Customer no.'], ['Kunde','Customer'],
    ['Leverandørnr','Supplier no.'], ['Leverandør','Supplier'],
    ['Rekvisitionsnr','Requisition no.'], ['Deres ref','Your ref'], ['Vores ref','Our ref'], ['Reference','Reference'],
    ['Kontaktperson','Contact'], ['Vores kontakt','Our contact'], ['Deres kontakt','Your contact'], ['Sælger','Salesperson'],
    ['Betalingsbetingelser','Payment terms'], ['Betalingsbet','Payment terms'], ['Betalingsdato','Payment date'],
    ['Leveringsbetingelser','Delivery terms'], ['Leveringsdato','Delivery date'], ['Leveringsadresse','Delivery address'],
    ['Forfaldsdato','Due date'], ['Forfald','Due'], ['Forventet levering','Expected delivery'],
    ['Forventet lev.','Expected delivery'], ['Forventet lev','Expected delivery'],
    ['Varenummer','Item number'], ['Varenr','Item no.'], ['Vare','Item'], ['Beskrivelse','Description'], ['Tekst','Text'],
    ['Enhedspris','Unit price'], ['Enhed','Unit'], ['Antal','Qty'], ['Mængde','Quantity'], ['Stk','Pcs'],
    ['Pris pr. enhed','Price per unit'], ['Pris','Price'], ['Rabat','Discount'], ['Linjesum','Line total'],
    ['Momssats','VAT rate'], ['Moms','VAT'], ['Projekt','Project'], ['Lokation','Location'], ['Position','Position'],
    ['Nettosum','Net amount'], ['Nettobeløb','Net amount'], ['Subtotal','Subtotal'],
    ['I alt inkl. moms','Total incl. VAT'], ['I alt','Total'], ['At betale','Amount due'], ['Beløb','Amount'], ['Sum','Amount'],
    ['Transport til side','Carried to page'], ['Transport','Carried fwd'], ['Overført','Brought fwd'],
    ['Restbeløb','Outstanding amount'], ['Rest','Balance'], ['Saldo','Balance'], ['Debet','Debit'], ['Kredit','Credit'],
    ['Valuta','Currency'],
    ['Bankoverførsel','Bank transfer'], ['Bankkonto','Bank account'], ['Kontonummer','Account number'],
    ['Kontonr','Account no.'], ['Konto','Account'], ['Reg. nr','Reg. no.'], ['Reg.nr','Reg. no.'],
    ['CVR-nr','VAT no.'], ['CVR nr','VAT no'], ['Cvr.nr:','VAT no:'], ['CVR nr:','VAT no:'], ['Cvr.:','VAT:'],
    ['Telefon:','Phone:'], ['Telefon','Phone'], ['Tlf:','Phone:'], ['Tlf','Phone'], ['Mobil','Mobile'],
    ['Fax:','Fax:'], ['E-mail','Email'], ['Hjemmeside','Website'], ['Adresse','Address'], ['Postnr','Postcode'],
    ['By','City'], ['Land','Country'], ['Danmark','Denmark'],
    ['Leveret','Delivered'], ['Leveres','To be delivered'], ['Levering','Delivery'], ['Restordre','Back order'],
    ['Nummer','Number'], ['Dato','Date'], ['Side','Page'], ['Initialer','Initials'], ['Underskrift','Signature'],
    ['Med venlig hilsen','Kind regards'], ['Venlig hilsen','Kind regards'], ['Att.:','Attn:'], ['Att:','Attn:'],
    ['Emne','Subject'], ['Bilag','Attachment'], ['dage','days']
  ];
  function feEsc(s){ return s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'); }
  var _trCache={};
  function feTr(toEN){
    var k=toEN?'en':'da'; if(_trCache[k]) return _trCache[k];
    var m={}; TR_PAIRS.forEach(function(p){ var key=(toEN?p[0]:p[1]), val=(toEN?p[1]:p[0]); if(!key) return; var lk=key.toLowerCase(); if(!(lk in m)) m[lk]=val; });
    var keys=Object.keys(m).sort(function(a,b){ return b.length-a.length; });
    var re; try { re=new RegExp('(^|[^\\p{L}])('+keys.map(function(x){return feEsc(x);}).join('|')+')(?![\\p{L}])','giu'); }
    catch(e){ re=new RegExp('(^|[^A-Za-zÀ-ÿ])('+keys.map(function(x){return feEsc(x);}).join('|')+')(?![A-Za-zÀ-ÿ])','gi'); }
    return (_trCache[k]={map:m, re:re});
  }
  function feTranslateCaption(besk, toEN){
    if(!besk) return besk;
    var tr=feTr(toEN);
    var parts=String(besk).split(/(\$[A-Za-z0-9_]+;?)/);
    for(var i=0;i<parts.length;i+=2){
      parts[i]=parts[i].replace(tr.re, function(_m, pre, w){ var rep=tr.map[w.toLowerCase()]; return rep ? (pre+rep) : (pre+w); });
    }
    return parts.join('');
  }
  function feDetectLang(){
    var da=0,en=0;
    elements.forEach(function(el){
      if(el.kind!=='text') return;
      var b=String(el.besk||''); if(!/[A-Za-zÀ-ÿ]/.test(b.replace(/\$[A-Za-z0-9_]+;?/g,''))) return;
      if(feTranslateCaption(b,true)!==b) da++;
      if(feTranslateCaption(b,false)!==b) en++;
    });
    return en>da ? 'en' : 'da';
  }
  var feCurLang = 'da';
  function updateLangToggle(){ var s=document.getElementById('fe-lang-select'); if(s) s.value=feCurLang; }
  function feSwitchLang(toEN){
    var want = toEN?'en':'da';
    if(feCurLang===want){ updateLangToggle(); return; }
    pushUndo();
    elements.forEach(function(el){ if(el.kind==='text') el.besk = feTranslateCaption(String(el.besk||''), toEN); });
    feCurLang = want;
    render(); if(selId) showProps(); markDirty(); updateLangToggle();
    flashStatus(toEN ? (DK_UI?'Alle tekster skiftet til engelsk':'All captions switched to English')
                     : (DK_UI?'Alle tekster skiftet til dansk':'All captions switched to Danish'), '#1769ff');
  }
  (function(){ var s=document.getElementById('fe-lang-select'); if(s) s.addEventListener('change', function(){ feSwitchLang(this.value==='en'); }); })();

  // print-language lock: force this form to always print in the current variant,
  // regardless of the customer's language (writes a per-form lock file server-side).
  (function(){
    var cb=document.getElementById('fe-printlang'); if(!cb) return;
    cb.checked = (FE_PRINTLANG !== '' && FE_PRINTLANG != null);
    cb.addEventListener('change', function(){
      var lock=cb.checked;
      if(lock && !window.confirm(DK_UI
        ? 'Alle “'+sprog+'” udskrifter af denne formular vil altid være på denne sprogversion – uanset kundens sprog.\n\nAktivér?'
        : 'Every printout of this form will always use this language version “'+sprog+'” – regardless of the customer’s language.\n\nEnable?')) { cb.checked=false; return; }
      fetch('formeditor.php?fe_action=set_printlang',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({ form_nr:formNr, sprog:sprog, lock:lock }), credentials:'same-origin'})
        .then(function(r){return r.json();}).then(function(j){
          if(j&&j.ok){ FE_PRINTLANG = lock ? sprog : ''; flashStatus(lock ? (DK_UI?'Udskriftssprog låst':'Print language locked') : (DK_UI?'Udskriftssprog frigivet':'Print language unlocked'), '#2a7d2a'); }
          else { cb.checked=!lock; alert(DK_UI?'Handlingen mislykkedes.':'The action failed.'); }
        }).catch(function(){ cb.checked=!lock; alert(DK_UI?'Netværksfejl.':'Network error.'); });
    });
  })();

  // ---- first-run guided tour (Tier 3): friendly coach-marks ---------------
  // Two tours: a short Quick-start (auto-runs first visit) and a Full tour
  // (from Help) that also dives inside the Templates modal.
  var TOUR_KEY='fe_tour_done_v1', tourIdx=0, tourHi=null, tourTip=null, tourList=[];
  var tourSteps=[
    { el:null, title:(DK_UI?'Velkommen! 👋':'Welcome! 👋'),
      body:(DK_UI?'Lav en flot, professionel faktura i få trin. Intet går live før du gemmer.':'Let’s make a polished, professional invoice in a few steps. Nothing goes live until you save.') },
    { el:'fe-design-open', title:(DK_UI?'1. Udseende & farver':'1. Look & colours'),
      body:(DK_UI?'Klik “Skabeloner” for et færdigt design + dine brand-farver (accent + tekst) og skrifttype.':'Click “Templates” for a ready-made design, plus your brand colours (accent + text) and font.') },
    { el:'fe-bg-btn', title:(DK_UI?'2. Tilføj brevpapir':'2. Add letterhead'),
      body:(DK_UI?'Upload dit brevpapir/baggrund her. (Et logo tilføjes via Felter → Logo.)':'Upload your letterhead / background here. (A logo is added via Fields → Logo.)') },
    { el:'fe-add-text', title:(DK_UI?'3. Tilføj & rediger':'3. Add & edit'),
      body:(DK_UI?'Klik på et felt for at redigere det, træk for at flytte. Tilføj tekst eller streger her.':'Click any field to edit it, drag to move. Add text or lines here.') },
    { el:'fe-fields-btn', title:(DK_UI?'4. Find alle felter':'4. Find every field'),
      body:(DK_UI?'Søg og vælg ethvert element – også skjulte, som dit logo.':'Search & select any element — even hidden ones, like your logo.') },
    { el:'fe-undo', title:(DK_UI?'5. Fortryd trygt':'5. Undo safely'),
      body:(DK_UI?'Fortryd/gentag alt (Ctrl+Z / Ctrl+Y). Eksperimentér frit.':'Undo/redo anything (Ctrl+Z / Ctrl+Y). Experiment freely.') },
    { el:'fe-preview', title:(DK_UI?'6. Se udskriften':'6. Preview'),
      body:(DK_UI?'“Vis kladde” viser hvordan udskriften ser ud med eksempeldata.':'“Show draft” previews how the print looks with sample data.') },
    { el:'fe-save', title:(DK_UI?'7. Gå live':'7. Go live'),
      body:(DK_UI?'Når du er tilfreds: klik “Gem & aktivér”. Så bruges designet ved udskrift.':'When you’re happy, click “Save & activate”. That’s when it’s used for printing.') },
    { el:'fe-reset', title:(DK_UI?'8. Start forfra':'8. Start over'),
      body:(DK_UI?'“Nulstil” sætter formularen tilbage til Saldis standard.':'“Reset” returns the form to Saldi’s standard.') }
  ];
  // Full tour — also opens the Templates modal and walks its colours/theme/font.
  var fullTourSteps=[
    { el:null, title:(DK_UI?'Fuld rundvisning 🎬':'Full tour 🎬'),
      body:(DK_UI?'Vi ser på alt – inkl. farver, temaer og skrifttyper inde i Skabeloner.':'We’ll walk through everything — including the colours, themes and fonts inside Templates.') },
    { el:'fe-design-open', title:(DK_UI?'Skabeloner':'Templates'),
      body:(DK_UI?'Her åbner du design & brand. Lad os kigge indenfor…':'This opens design & brand. Let’s look inside…') },
    { el:'fe-theme-cards', modal:true, run:openDesign, title:(DK_UI?'10 udseender':'10 looks'),
      body:(DK_UI?'Ét klik anvender et helt design (Klassisk, Moderne, Fed, Elegant, Hero …).':'One click applies a whole design (Classic, Modern, Bold, Elegant, Hero …).') },
    { el:'fe-schemes', modal:true, run:openDesign, title:(DK_UI?'Farvesæt':'Colour schemes'),
      body:(DK_UI?'Harmoniske accent+tekst-farvepar med ét klik.':'Harmonious accent+text colour pairs in one click.') },
    { el:'fe-brand-accent', modal:true, run:openDesign, title:(DK_UI?'Accentfarve':'Accent colour'),
      body:(DK_UI?'Farver overskrifterne. “Fra logo” henter farven fra dit logo.':'Colours the headings. “From logo” pulls the colour from your logo.') },
    { el:'fe-brand-text', modal:true, run:openDesign, title:(DK_UI?'Tekstfarve':'Text colour'),
      body:(DK_UI?'Farver brødtekst, værdier og tabellinjer. “Match accent” afstemmer dem.':'Colours body text, values and table rows. “Match accent” coordinates them.') },
    { el:'fe-brand-font', modal:true, run:openDesign, title:(DK_UI?'Skrifttype':'Font'),
      body:(DK_UI?'6 skrifttyper. Prøven nedenfor viser hvordan de ser ud.':'6 fonts. The sample below shows how they look.') },
    { el:'fe-bg-btn', title:(DK_UI?'Brevpapir':'Letterhead'),
      body:(DK_UI?'Upload dit brevpapir/baggrund. (Logo tilføjes via Felter → Logo.)':'Upload your letterhead / background. (A logo is added via Fields → Logo.)') },
    { el:'fe-add-text', title:(DK_UI?'Tilføj & rediger':'Add & edit'),
      body:(DK_UI?'Klik for at redigere, træk for at flytte. Tilføj tekst eller streger.':'Click to edit, drag to move. Add text or lines.') },
    { el:'fe-fields-btn', title:(DK_UI?'Felter':'Fields'),
      body:(DK_UI?'Søg og vælg ethvert element – også skjulte, som logoet.':'Search & select any element — even hidden ones, like the logo.') },
    { el:'fe-ruler-toggle', title:(DK_UI?'Linealer & marginer':'Rulers & margins'),
      body:(DK_UI?'Slå mm-linealer og margin-guides til; felter snapper til dem.':'Turn on mm rulers and margin guides; fields snap to them.') },
    { el:'fe-grid-toggle', title:(DK_UI?'Gitter':'Grid'),
      body:(DK_UI?'5 mm snap-gitter for helt præcis placering.':'A 5 mm snap grid for precise placement.') },
    { el:'fe-undo', title:(DK_UI?'Fortryd':'Undo'),
      body:(DK_UI?'Fortryd/gentag alt (Ctrl+Z / Ctrl+Y).':'Undo/redo anything (Ctrl+Z / Ctrl+Y).') },
    { el:'fe-preview', title:(DK_UI?'Se udskriften':'Preview'),
      body:(DK_UI?'“Vis kladde” viser udskriften med eksempeldata.':'“Show draft” previews the print with sample data.') },
    { el:'fe-save', title:(DK_UI?'Gem & aktivér':'Save & activate'),
      body:(DK_UI?'Gør designet aktivt ved udskrift. Intet går live før det.':'Makes the design live for printing. Nothing goes live until then.') },
    { el:'fe-reset', title:(DK_UI?'Nulstil':'Reset'),
      body:(DK_UI?'Sætter formularen tilbage til Saldis standard.':'Returns the form to Saldi’s standard.') }
  ];
  function buildTour(){
    if(tourTip) return;
    tourHi=document.createElement('div'); tourHi.id='fe-tour-hi'; document.body.appendChild(tourHi);
    tourTip=document.createElement('div'); tourTip.id='fe-tour-tip';
    tourTip.innerHTML='<button type="button" class="fe-tour-skip">'+(DK_UI?'Spring over':'Skip')+'</button>'
      +'<div class="fe-tour-title" style="font-weight:bold;font-size:14px;margin-bottom:6px;"></div>'
      +'<div class="fe-tour-body"></div>'
      +'<div class="fe-tour-foot"><span class="fe-tour-dots"></span><span>'
      +'<button type="button" class="fe-tour-btn fe-tour-back">'+(DK_UI?'Tilbage':'Back')+'</button> '
      +'<button type="button" class="fe-tour-btn primary fe-tour-next"></button></span></div>';
    document.body.appendChild(tourTip);
    tourTip.querySelector('.fe-tour-next').addEventListener('click', function(){ showTourStep(tourIdx+1); });
    tourTip.querySelector('.fe-tour-back').addEventListener('click', function(){ showTourStep(tourIdx-1); });
    tourTip.querySelector('.fe-tour-skip').addEventListener('click', endTour);
    window.addEventListener('resize', function(){ if(tourTip && tourTip.style.display!=='none' && tourList[tourIdx]) positionTour(tourList[tourIdx]); });
  }
  function positionTour(step){
    var target = step.el ? document.getElementById(step.el) : null;
    var W=window.innerWidth, H=window.innerHeight, tw=290, th=tourTip.offsetHeight||160, m=10;
    if(target && target.getClientRects().length){
      try { target.scrollIntoView({block:'nearest', inline:'nearest'}); } catch(e){}
      var r=target.getBoundingClientRect(), pad=6;
      // spotlight, clamped to the viewport so a tall/off-screen target still shows
      var hlL=Math.max(2, r.left-pad), hlT=Math.max(2, r.top-pad);
      var hlR=Math.min(W-2, r.right+pad), hlB=Math.min(H-2, r.bottom+pad);
      tourHi.style.display='block';
      tourHi.style.left=hlL+'px'; tourHi.style.top=hlT+'px';
      tourHi.style.width=Math.max(0,hlR-hlL)+'px'; tourHi.style.height=Math.max(0,hlB-hlT)+'px';
      // tip: prefer below the visible top of the target, else above, else beside;
      // then hard-clamp fully on-screen so Back/Next are ALWAYS reachable.
      var tipTop, tipLeft=r.left-20;
      if (hlB+12+th <= H-m)      tipTop=hlB+12;                 // below the (clamped) highlight
      else if (r.top-12-th >= m) tipTop=r.top-12-th;            // above the target
      else {                                                    // tall target: sit beside it, centred
        tipLeft=(r.left>=tw+2*m)?(r.left-tw-14):(r.right+14);
        tipTop=r.top + Math.min(r.height, H)/2 - th/2;
      }
      tipLeft=Math.min(Math.max(m, tipLeft), W-tw-m);
      tipTop =Math.min(Math.max(m, tipTop),  H-th-m);
      tourTip.style.left=tipLeft+'px'; tourTip.style.top=tipTop+'px';
    } else {
      tourHi.style.display='none';
      tourTip.style.left=Math.max(m, W/2-145)+'px';
      tourTip.style.top=Math.max(60, H/2-110)+'px';
    }
  }
  function showTourStep(i){
    if(i>=tourList.length){ endTour(); return; } if(i<0) i=0;
    tourIdx=i; var step=tourList[i];
    // modal steps open the Templates dialog; a non-modal step closes it again
    if (step.run) step.run();
    else if (!step.modal && designModal && designModal.style.display!=='none') closeDesign();
    tourTip.querySelector('.fe-tour-title').textContent=step.title;
    tourTip.querySelector('.fe-tour-body').textContent=step.body;
    tourTip.querySelector('.fe-tour-dots').textContent=(i+1)+' / '+tourList.length;
    tourTip.querySelector('.fe-tour-back').style.visibility=(i>0)?'visible':'hidden';
    tourTip.querySelector('.fe-tour-next').textContent=(i===tourList.length-1)?(DK_UI?'Færdig':'Done'):(DK_UI?'Næste':'Next');
    positionTour(step);
    requestAnimationFrame(function(){ if(tourList[tourIdx]===step) positionTour(step); });   // re-measure after any modal layout
  }
  function startTour(steps){ tourList = steps || tourSteps; buildTour(); tourHi.style.display='block'; tourTip.style.display='block'; showTourStep(0); }
  function endTour(){
    if (designModal && designModal.style.display!=='none') closeDesign();
    if(tourHi) tourHi.style.display='none'; if(tourTip) tourTip.style.display='none';
    try{ localStorage.setItem(TOUR_KEY,'1'); }catch(e){}
  }

  // ---- text style presets (Tier 3+): one-click typographic styles ---------
  var TEXT_PRESETS = {
    title:   { str:24, fed:1, color:'ACCENT' },
    heading: { str:14, fed:1, color:'ACCENT' },
    label:   { str:10, fed:1, color:'TEXT'   },
    body:    { str:10, fed:0, color:'TEXT'   },
    total:   { str:11, fed:1, color:'TEXT'   },
    fine:    { str:8,  fed:0, color:'#888888' }
  };
  function applyTextPreset(name){
    var p = TEXT_PRESETS[name]; if(!p) return;
    var targets = (selSet.length>1)
      ? selSet.map(elById).filter(function(e){ return e && e.kind==='text'; })
      : (function(){ var e=elById(selId); return (e && e.kind==='text') ? [e] : []; })();
    if(!targets.length) return;
    pushUndo();
    targets.forEach(function(el){
      el.str = p.str; el.fed = p.fed;
      var col = feResolveColor(p.color, null); if(col!=null) el.color = feHexNum(col);
    });
    render(); syncProps(); markDirty();
  }
  Array.prototype.forEach.call(document.querySelectorAll('#fe-style-presets .fe-sp'), function(b){
    b.addEventListener('click', function(){ applyTextPreset(b.getAttribute('data-sp')); });
  });

  function feTextTargets(){
    return (selSet.length>1) ? selSet.map(elById).filter(function(e){ return e && e.kind==='text'; })
      : (function(){ var e=elById(selId); return (e && e.kind==='text') ? [e] : []; })();
  }
  // Snap-to-page: align selected text to the left margin / page centre / right margin
  function snapToPage(mode){
    var t=feTextTargets(); if(!t.length) return;
    pushUndo();
    t.forEach(function(el){
      if(mode==='left'){ el.xa=MARGIN_MM; el.justering='V'; }
      else if(mode==='center'){ el.xa=PAGE_W/2; el.justering='C'; }
      else if(mode==='right'){ el.xa=PAGE_W-MARGIN_MM; el.justering='H'; }
    });
    render(); syncProps(); markDirty();
  }
  Array.prototype.forEach.call(document.querySelectorAll('#fe-page-align .fe-sp'), function(b){
    b.addEventListener('click', function(){ snapToPage(b.getAttribute('data-pa')); });
  });
  // Format painter: copy one text's look, paint it onto others
  var copiedStyle=null;
  function copyStyle(){
    var e=elById(selId); if(!e||e.kind!=='text') return;
    copiedStyle={ str:e.str, fed:e.fed, kursiv:e.kursiv, color:e.color, font:e.font, justering:e.justering };
    var pb=document.getElementById('fe-pastestyle'); if(pb) pb.disabled=false;
    flashStatus(DK_UI?'Stil kopieret – vælg felter og indsæt':'Style copied – select fields and paste','#1769ff');
  }
  function pasteStyle(){
    if(!copiedStyle) return; var t=feTextTargets(); if(!t.length) return;
    pushUndo();
    t.forEach(function(el){ el.str=copiedStyle.str; el.fed=copiedStyle.fed; el.kursiv=copiedStyle.kursiv;
      el.color=copiedStyle.color; el.font=copiedStyle.font; el.justering=copiedStyle.justering; });
    render(); syncProps(); markDirty();
  }
  var _cs=document.getElementById('fe-copystyle'); if(_cs) _cs.addEventListener('click', copyStyle);
  var _ps=document.getElementById('fe-pastestyle'); if(_ps) _ps.addEventListener('click', pasteStyle);
  // Lock: freeze an element so it can't be dragged/nudged/deleted (session only)
  var _lock=document.getElementById('fe-lock');
  if(_lock) _lock.addEventListener('change', function(){ var el=elById(selId); if(!el) return; el._locked=this.checked; render(); });
  // Reveal all: briefly flash every element's outline
  function flashAllFields(){ page.classList.add('fe-reveal'); setTimeout(function(){ page.classList.remove('fe-reveal'); }, 1400); }

  // Per-column table styling: alignment (L/C/R) + bold for each order-line column
  function buildColStyleList(){
    var host=document.getElementById('fe-tcols'); if(!host||!table||!table.cols) return;
    host.innerHTML='';
    table.cols.forEach(function(c){
      var row=document.createElement('div'); row.className='fe-tc-row';
      var nm=document.createElement('span'); nm.className='fe-tc-name'; nm.textContent=c.label||c.type; nm.title=c.label||c.type; row.appendChild(nm);
      [['V','L'],['C','C'],['H','R']].forEach(function(a){
        var b=document.createElement('button'); b.type='button'; b.className='fe-tc-btn'+(c.just===a[0]?' on':''); b.textContent=a[1];
        b.addEventListener('click', function(){ pushUndo(); c.just=a[0]; markDirty(); render(); buildColStyleList(); });
        row.appendChild(b);
      });
      var bb=document.createElement('button'); bb.type='button'; bb.className='fe-tc-btn'+(c.fed?' on':''); bb.textContent='B'; bb.style.fontWeight='bold';
      bb.addEventListener('click', function(){ pushUndo(); c.fed=c.fed?0:1; markDirty(); render(); buildColStyleList(); });
      row.appendChild(bb);
      // per-column colour (e.g. tint the Total column in the accent colour)
      var ci=document.createElement('input'); ci.type='color'; ci.className='fe-tc-color';
      ci.value=rgbToHex(colorNumToRgb(c.color||0)); ci.title=(DK_UI?'Kolonnens farve':'Column colour');
      ci.addEventListener('change', function(){ pushUndo(); c.color=feHexNum(ci.value); markDirty(); render(); });
      row.appendChild(ci);
      host.appendChild(row);
    });
  }
  document.getElementById('fe-grid-toggle').addEventListener('change', function(e){ grid=e.target.checked; render(); });
  document.getElementById('fe-ruler-toggle').addEventListener('change', function(e){ rulersOn=e.target.checked; render(); });
  (function(){ var sc=document.getElementById('fe-canvas-scroll');
    if (sc) sc.addEventListener('scroll', function(){ if (rulersOn) drawRulers(); }); })();
  window.addEventListener('resize', function(){ if (rulersOn) drawRulers(); });
  document.getElementById('fe-fields-btn').addEventListener('click', function(){ toggleFields(); });
  var _bgDim=document.getElementById('fe-bgdim-toggle');
  if(_bgDim) _bgDim.addEventListener('change', function(e){ page.classList.toggle('fe-bg-dim', e.target.checked); });

  // Show draft: toggle preview (sample data, clean look, no editing chrome)
  document.getElementById('fe-preview').addEventListener('click', function(){
    previewMode = !previewMode;
    this.classList.toggle('fe-active', previewMode);
    page.classList.toggle('fe-preview', previewMode);
    if (previewMode) {
      selId = null; selSet = []; showProps(); updateAlignBar();
      if (fieldsDrawer) fieldsDrawer.style.display = 'none';   // clean WYSIWYG preview
    } else if (fieldsOpen && fieldsDrawer) {
      fieldsDrawer.style.display = 'flex';                     // restore the panel
    }
    flashStatus(previewMode ? PREVIEW_ON : '', previewMode ? '#1769ff' : '#444');
    render();
  });

  // Background is uploaded in a separate tab; when the user returns, refresh so
  // the new background shows (auto-reload if nothing unsaved, else prompt).
  var bgPending = false;
  var bgBtn = document.getElementById('fe-bg-btn');
  if (bgBtn) bgBtn.addEventListener('click', function(){ bgPending = true; });
  document.addEventListener('visibilitychange', function(){
    if (document.visibilityState === 'visible' && bgPending) {
      bgPending = false;
      if (!dirty) { location.reload(); }
      else { flashStatus(<?php echo json_encode($T('Baggrund evt. ændret – gem og genindlæs (F5) for at se den','Background may have changed – Save, then reload (F5) to see it')); ?>, '#b26a00'); }
    }
  });

  // Empty-canvas mousedown: rubber-band select (drag) or clear (plain click).
  page.addEventListener('mousedown', function(e){
    if (drawLineMode) return;
    if (!(e.target===page || e.target===gridC || (e.target.classList && e.target.classList.contains('fe-bg')))) return;
    var s=pxmm(), rect=page.getBoundingClientRect();
    var x0=e.clientX, y0=e.clientY, dragged=false;
    var band=document.createElement('div'); band.className='fe-rubber'; page.appendChild(band);
    function mv(ev){
      if (Math.abs(ev.clientX-x0)+Math.abs(ev.clientY-y0) > 3) dragged=true;
      var Lx=Math.min(x0,ev.clientX)-rect.left, Ty=Math.min(y0,ev.clientY)-rect.top;
      band.style.left=Lx+'px'; band.style.top=Ty+'px';
      band.style.width=Math.abs(ev.clientX-x0)+'px'; band.style.height=Math.abs(ev.clientY-y0)+'px';
    }
    function up(ev){
      document.removeEventListener('mousemove',mv); document.removeEventListener('mouseup',up); band.remove();
      if (!dragged){ select(null); return; }
      var xL=(Math.min(x0,ev.clientX)-rect.left)/s, xR=(Math.max(x0,ev.clientX)-rect.left)/s;
      var yTop=PAGE_H-(Math.min(y0,ev.clientY)-rect.top)/s, yBot=PAGE_H-(Math.max(y0,ev.clientY)-rect.top)/s;
      var ids=[];
      elements.forEach(function(el){ if (el.kind==='logo') return;
        if (el.xa>=xL && el.xa<=xR && el.ya<=yTop && el.ya>=yBot) ids.push(el.id); });
      if (ev.shiftKey){ ids.forEach(function(id){ if(!selHas(id)) selSet.push(id); });
        selId=selSet.length?selSet[selSet.length-1]:null; highlight(); showProps(); updateAlignBar(); }
      else setSel(ids);
    }
    document.addEventListener('mousemove',mv); document.addEventListener('mouseup',up);
  });

  // ---- Templates & brand (Tier 1) -----------------------------------------
  // A "theme" is a client-side restyle over the elements already loaded: it sets
  // font / colour / weight / line width per element ROLE. Nothing is inserted or
  // deleted, so it is idempotent, works on every form + variant, is fully
  // undoable and only persists through the normal Save path.
  var designTheme  = null;          // null until the user picks one
  var designAccent = '#000066';     // brand accent — headings/emphasis
  var designText   = '#2b2b2b';     // body text — labels, values, order lines
  var designFont   = 'Helvetica';   // PostScript base family
  var FE_PALETTES = [
    { name: DK_UI?'Marineblå':'Navy',        hex:'#000066' },
    { name: DK_UI?'Klar blå':'Bright blue',  hex:'#1769ff' },
    { name: DK_UI?'Petrol':'Teal',           hex:'#0f766e' },
    { name: DK_UI?'Skovgrøn':'Forest',       hex:'#2e7d32' },
    { name: DK_UI?'Blomme':'Plum',           hex:'#6d28d9' },
    { name: DK_UI?'Rødvin':'Bordeaux',       hex:'#b23b3b' },
    { name: DK_UI?'Rust':'Rust',             hex:'#c2410c' },
    { name: DK_UI?'Grafit':'Graphite',       hex:'#333333' }
  ];
  // curated accent+text pairs — one click to a harmonious, professional look
  var FE_SCHEMES = [
    { name:(DK_UI?'Marine':'Navy'),        a:'#1b3a5b', t:'#22303f' },
    { name:(DK_UI?'Grafit':'Graphite'),    a:'#3a4657', t:'#1f2430' },
    { name:(DK_UI?'Skov':'Forest'),        a:'#245c3b', t:'#26332b' },
    { name:(DK_UI?'Petrol':'Teal'),        a:'#0f6e6a', t:'#1b3936' },
    { name:(DK_UI?'Bordeaux':'Bordeaux'),  a:'#7a2233', t:'#2f2327' },
    { name:(DK_UI?'Blomme':'Plum'),        a:'#5b2a6b', t:'#2c2233' },
    { name:(DK_UI?'Rust':'Rust'),          a:'#b4531f', t:'#3a2a1e' },
    { name:(DK_UI?'Guld & blæk':'Gold & Ink'), a:'#9a7b1f', t:'#1e2430' }
  ];
  feLoadPrefs();   // restore this customer's brand (accent + font) if saved

  var THEME_META = [
    { id:'classic',   name: DK_UI?'Klassisk':'Classic',     desc: DK_UI?'Traditionel, sort/hvid':'Traditional, black & white' },
    { id:'modern',    name: DK_UI?'Moderne':'Modern',       desc: DK_UI?'Accentfarve & fede overskrifter':'Accent colour & bold headings' },
    { id:'minimal',   name: DK_UI?'Minimal':'Minimal',      desc: DK_UI?'Tynde streger, luftigt':'Hairline rules, airy' },
    { id:'bold',      name: DK_UI?'Kraftig':'Bold',         desc: DK_UI?'Stor titel, tung, høj kontrast':'Big title, heavy, high contrast' },
    { id:'elegant',   name: DK_UI?'Elegant':'Elegant',      desc: DK_UI?'Serif, forfinet, luftig':'Serif, refined, airy' },
    { id:'editorial', name: DK_UI?'Redaktionel':'Editorial',desc: DK_UI?'Serif-overskrift + god linjeafstand':'Serif heading + roomy spacing' },
    { id:'centered',  name: DK_UI?'Centreret':'Centered',   desc: DK_UI?'Centreret brevhoved':'Centered letterhead' },
    { id:'condensed', name: DK_UI?'Kompakt':'Condensed',    desc: DK_UI?'Tæt – plads til mange linjer':'Tight – fits many lines' },
    { id:'colorful',  name: DK_UI?'Farverig':'Colourful',   desc: DK_UI?'Accentfarve overalt, livlig':'Accent everywhere, lively' },
    { id:'hero',      name: DK_UI?'Stor titel':'Hero',      desc: DK_UI?'Kæmpe centreret titel':'Huge centred title' }
  ];
  // Roles that layout transforms may reposition/realign (always reversibly).
  var FE_LAYOUT_ROLES = ['title','company'];
  // Per role: color ('ACCENT' = brand accent), bold, optional font override
  // ('Helvetica'/'Times'), optional scale (title size × baseline). Lines: color +
  // width (pt). tableSpace = row-spacing multiplier for the order-line table.
  // Colours: 'ACCENT' = user accent (headings/emphasis), 'TEXT' = user body colour
  // (labels, values, order lines), or a fixed hex (rules / intentional shades).
  var THEMES = {
    classic: {
      title:{color:'TEXT',bold:true,scale:1.0}, colheader:{color:'TEXT',bold:true},
      total:{color:'TEXT',bold:false}, company:{color:'TEXT'},
      body:{color:'TEXT'}, ruleHeader:{color:'#000000',width:1}, rule:{color:'#000000',width:1},
      tableSpace:1.0
    },
    modern: {
      title:{color:'ACCENT',bold:true,scale:1.15}, colheader:{color:'ACCENT',bold:true},
      total:{color:'TEXT',bold:true}, company:{color:'ACCENT'},
      body:{color:'TEXT'}, ruleHeader:{color:'ACCENT',width:2}, rule:{color:'#888888',width:1},
      tableSpace:1.0
    },
    minimal: {
      title:{color:'TEXT',bold:true,scale:1.0}, colheader:{color:'TEXT',bold:false},
      total:{color:'TEXT',bold:true}, company:{color:'TEXT'},
      body:{color:'TEXT'}, ruleHeader:{color:'#999999',width:0.3}, rule:{color:'#aaaaaa',width:0.3},
      tableSpace:1.12
    },
    bold: {
      title:{color:'ACCENT',bold:true,scale:1.45}, colheader:{color:'ACCENT',bold:true},
      total:{color:'ACCENT',bold:true}, company:{color:'TEXT',bold:true},
      body:{color:'TEXT'}, ruleHeader:{color:'ACCENT',width:3}, rule:{color:'#666666',width:1.5},
      tableSpace:1.0
    },
    elegant: {
      title:{color:'ACCENT',bold:false,scale:1.28,font:'Palatino'}, colheader:{color:'TEXT',bold:false,font:'Palatino'},
      total:{color:'TEXT',bold:false,font:'Palatino'}, company:{color:'TEXT',font:'Palatino'},
      body:{color:'TEXT',font:'Palatino'}, ruleHeader:{color:'#8a7a55',width:0.5}, rule:{color:'#c9bfa6',width:0.5},
      tableSpace:1.14
    },
    editorial: {
      title:{color:'ACCENT',bold:true,scale:1.32,font:'Times'}, colheader:{color:'ACCENT',bold:true},
      total:{color:'TEXT',bold:true}, company:{color:'TEXT'},
      body:{color:'TEXT'}, ruleHeader:{color:'ACCENT',width:1.5}, rule:{color:'#bbbbbb',width:0.4},
      tableSpace:1.2
    },
    // --- way-different formats (layout / density, not just typography) --------
    centered: {   // centered letterhead: title + sender block pulled to page centre
      title:{color:'ACCENT',bold:true,scale:1.2}, colheader:{color:'ACCENT',bold:true},
      total:{color:'TEXT',bold:true}, company:{color:'TEXT'},
      body:{color:'TEXT'}, ruleHeader:{color:'ACCENT',width:1.5}, rule:{color:'#bbbbbb',width:0.5},
      tableSpace:1.05,
      layout:{ title:{align:'C',x:105}, company:{align:'C',x:105} }
    },
    condensed: { // small + tight, to fit long invoices on fewer pages
      title:{color:'TEXT',bold:true,scale:1.0}, colheader:{color:'TEXT',bold:true,scale:0.9},
      total:{color:'TEXT',bold:true,scale:0.9}, company:{color:'TEXT',scale:0.88},
      body:{color:'TEXT',scale:0.88}, ruleHeader:{color:'#333333',width:0.5}, rule:{color:'#cccccc',width:0.3},
      tableSpace:0.82
    },
    colorful: {  // accent colour used generously; lively
      title:{color:'ACCENT',bold:true,scale:1.2}, colheader:{color:'ACCENT',bold:true},
      total:{color:'ACCENT',bold:true}, company:{color:'ACCENT'},
      body:{color:'TEXT'}, ruleHeader:{color:'ACCENT',width:2.5}, rule:{color:'ACCENT',width:1},
      tableSpace:1.05
    },
    hero: {      // one huge centred title, everything else quiet
      title:{color:'ACCENT',bold:true,scale:1.9}, colheader:{color:'TEXT',bold:false},
      total:{color:'TEXT',bold:true}, company:{color:'TEXT'},
      body:{color:'TEXT'}, ruleHeader:{color:'ACCENT',width:1}, rule:{color:'#dddddd',width:0.3},
      tableSpace:1.1,
      layout:{ title:{align:'C',x:105} }
    }
  };

  var FE_COLWORDS = /(^|[^a-zæøå])(antal|beskrivelse|pris|sum|varenr|nummer|enhed|rabat|pos|stk|description|qty|price|amount|number|item|unit|disc)([^a-zæøå]|$)/i;
  var FE_TOTWORDS = /(i alt|nettosum|moms|subtotal|netto|total|at betale|restg|grand total|balance)/i;
  var FE_TOTVARS  = /formular_(sum|moms|ialt|momsgrundlag|transportsum)|forfalden_sum|rykker_gebyr/i;

  function findTitleId(){
    var best=null, bs=12.5;   // only a genuinely large text counts as the title
    for (var i=0;i<elements.length;i++){ var el=elements[i];
      if (el.kind==='text' && el.str>bs){ bs=el.str; best=el.id; } }
    return best;
  }
  function feRole(el, titleId){
    if (el.kind==='logo') return 'logo';
    if (el.kind==='line'){
      var horiz = Math.abs(el.ya-el.yb) < 0.5;
      return (horiz && el.ya>=185) ? 'ruleHeader' : 'rule';
    }
    if (titleId!=null && el.id===titleId) return 'title';
    var low = (el.besk||'').toLowerCase();
    if (FE_TOTVARS.test(low)) return 'total';
    if (table && table.gen){ var top=table.gen.ya;
      if (el.ya>=top-2 && el.ya<=top+12 && FE_COLWORDS.test(low)) return 'colheader'; }
    if (/\$e(get|gen)_/.test(low)) return 'company';
    if (el.xa>=118 && FE_TOTWORDS.test(low)) return 'total';
    return 'body';
  }
  // map a stored PostScript family to a CSS stack so the canvas previews it
  function feCssFont(font){
    if (font==='Times' || font==='Times-Roman') return '"Times New Roman", Times, serif';
    if (font==='Palatino') return '"Palatino Linotype", Palatino, "Book Antiqua", Georgia, serif';
    if (font==='NewCenturySchlbk') return '"Century Schoolbook", "Century Schoolbook L", "TeX Gyre Schola", Georgia, serif';
    if (font==='Courier') return '"Courier New", Courier, monospace';
    if (font==='Ocrbb12') return '"OCR B", "OCRB", "Courier New", monospace';
    return 'Helvetica, Arial, sans-serif';
  }
  function feHexNum(hex){ var r=hexToRgb(hex); return rgbToColorNum(r[0],r[1],r[2]); }
  // 'ACCENT' = brand/heading colour, 'TEXT' = body colour, else a literal hex
  function feResolveColor(c, fb){ if(c==null) return (fb!=null?fb:null); if(c==='ACCENT') return designAccent; if(c==='TEXT') return designText; return c; }
  // blend two hex colours (t = weight of h1)
  function feMix(h1,h2,t){ var a=hexToRgb(h1),b=hexToRgb(h2);
    return rgbToHex([Math.round(a[0]*t+b[0]*(1-t)),Math.round(a[1]*t+b[1]*(1-t)),Math.round(a[2]*t+b[2]*(1-t))]); }
  // a body-text colour that coordinates with the accent but stays readable:
  // dark accents are used as-is (true match); bright ones are darkened, hue kept.
  function feReadableText(hex){
    var r=hexToRgb(hex), lum=0.2126*r[0]+0.7152*r[1]+0.0722*r[2];
    return (lum<=72) ? hex : feMix(hex, '#242424', 0.42);   // dark accents kept; brighter ones darkened for body readability
  }
  function feStyleColor(st){ if(!st||st.color==null) return null; return feResolveColor(st.color, null); }
  // Safety net: print draws text from its baseline upward, so a tall title whose
  // baseline sits too near the top edge runs off the page. Reposition ONLY a text
  // element that would genuinely overflow the top — anything already on the page
  // is left exactly where it is (so nothing that currently prints fine is touched).
  function feClampText(){
    var changed=false;
    for(var i=0;i<elements.length;i++){ var el=elements[i];
      if(el.kind!=='text' || !(el.str>0)) continue;
      var ascMm = el.str * PT_TO_MM * 0.82;         // ascent above the baseline
      if((PAGE_H - el.ya) - ascMm < 0){             // text top is above the page edge
        el.ya = Math.round((PAGE_H - 3 - ascMm)*100)/100;   // bring it back with a 3mm margin
        changed=true;
      }
    }
    return changed;
  }

  function applyDesign(){
    if (!elements.length || !designTheme) return;
    pushUndo();
    var th = THEMES[designTheme] || THEMES.classic;
    var titleId = findTitleId();
    elements.forEach(function(el){
      if (el.kind==='logo') return;              // logo is never restyled
      var role = feRole(el, titleId);
      var st = th[role];
      if (el.kind==='text'){
        if (el._str0==null)  el._str0  = el.str;         // baseline size (once)
        if (el._xa0==null)   el._xa0   = el.xa;          // baseline x     (once)
        if (el._just0==null) el._just0 = el.justering;   // baseline align (once)
        el.font = (st && st.font) ? st.font : designFont;
        // size is always derived from the baseline, so every theme is a clean,
        // reversible reset (no scale on a role => back to original size).
        var sc = (st && st.scale!=null) ? st.scale : 1;
        el.str = Math.round(el._str0 * sc * 10) / 10;
        // A large title is drawn from its baseline (ya) upward when printed, so a
        // big font near the top can run off the top edge. Clamp the title down just
        // enough to keep it on the page (reversible: small titles restore to ya0).
        if (role==='title'){
          if (el._ya0==null) el._ya0 = el.ya;
          var maxYa = PAGE_H - 5 - el.str * PT_TO_MM * 0.82;   // top margin + font ascent above baseline
          el.ya = Math.min(el._ya0, maxYa);                    // only moves down if it would overflow
        }
        if (!st) st = th.body;
        if (st){
          var col = feStyleColor(st); if (col!=null) el.color = feHexNum(col);
          if (st.bold===true) el.fed=1; else if (st.bold===false) el.fed=0;
        }
        // layout: only title + sender block ever move, and always reversibly.
        if (FE_LAYOUT_ROLES.indexOf(role) >= 0){
          var lay = th.layout && th.layout[role];
          if (lay){
            if (lay.x!=null)  el.xa = lay.x;
            if (lay.align)    el.justering = lay.align;
          } else {                                       // theme has no layout => restore
            el.xa = el._xa0; el.justering = el._just0;
          }
        }
      } else if (el.kind==='line'){
        if (!st) st = th.rule;
        if (st){
          var lcol = feStyleColor(st); if (lcol!=null) el.color = feHexNum(lcol);
          if (st.width!=null) el.str = st.width;
        }
      }
    });
    // order-line table row spacing (reversible; baseline captured once)
    if (table && table.gen && th.tableSpace!=null){
      if (table.gen._space0==null) table.gen._space0 = table.gen.spacing;
      table.gen.spacing = Math.round(table.gen._space0 * th.tableSpace * 10) / 10;
    }
    // order-line DATA colour follows the body Text colour (headers are art2 = accent)
    if (table && table.cols){
      var tcol = feHexNum(designText);
      table.cols.forEach(function(c){ c.color = tcol; });
    }
    markDirty(); render(); if (selId) showProps();
  }
  function setTheme(id){
    designTheme=id; applyDesign(); markActiveCard(); feSavePrefs();
    var m=null; for(var i=0;i<THEME_META.length;i++) if(THEME_META[i].id===id) m=THEME_META[i];
    if(m) flashStatus((DK_UI?'Skabelon: ':'Template: ')+m.name+' ✓','#1769ff');
  }

  // small live SVG preview of a theme applied to the CURRENT form
  function themeThumb(name){
    var th = THEMES[name] || THEMES.classic, s=0.5;
    var W=(PAGE_W*s).toFixed(1), H=(PAGE_H*s).toFixed(1);
    var titleId=findTitleId();
    var p=['<svg width="'+W+'" height="'+H+'" viewBox="0 0 '+W+' '+H+'" style="background:#fff;border:1px solid #d5dae2;border-radius:3px;box-shadow:0 1px 3px rgba(0,0,0,.12)">'];
    elements.forEach(function(el){
      var role=feRole(el,titleId), y=(PAGE_H-el.ya)*s;
      if (el.kind==='line'){
        var st=th[role]||th.rule, col=feResolveColor(st&&st.color, '#333');
        var w=(st&&st.width!=null)?st.width:1;
        p.push('<line x1="'+(el.xa*s).toFixed(1)+'" y1="'+y.toFixed(1)+'" x2="'+(el.xb*s).toFixed(1)+'" y2="'+((PAGE_H-el.yb)*s).toFixed(1)+'" stroke="'+col+'" stroke-width="'+Math.max(0.35,w*0.5).toFixed(2)+'"/>');
      } else if (el.kind==='logo'){
        p.push('<rect x="'+(el.xa*s).toFixed(1)+'" y="'+y.toFixed(1)+'" width="'+Math.max(6,el.xb*s).toFixed(1)+'" height="'+Math.max(4,el.yb*s).toFixed(1)+'" rx="1" fill="#dfe5ee"/>');
      } else {
        var st2=th[role]||th.body, c2=feResolveColor(st2&&st2.color, '#444');
        var txt=(el.besk||'').replace(/if\([^)]*\)/g,'').replace(/\$[a-z0-9_]+/gi,'xxxxxx').replace(/;/g,'');
        var len=Math.max(3,Math.min(48,txt.length));
        var base=(el._str0!=null?el._str0:el.str)||10;
        var eff=base*((st2&&st2.scale!=null)?st2.scale:1);
        // mirror the layout engine so centred/hero previews look right
        var exa=el.xa, ejust=el.justering;
        if (FE_LAYOUT_ROLES.indexOf(role) >= 0){
          var bxa=(el._xa0!=null?el._xa0:el.xa), bj=(el._just0!=null?el._just0:el.justering);
          var lay=th.layout && th.layout[role];
          if (lay){ exa=(lay.x!=null?lay.x:bxa); ejust=lay.align||bj; } else { exa=bxa; ejust=bj; }
        }
        var w2=len*s*1.3, h2=Math.max(1.3, eff*0.353*s*1.05);
        var x=exa*s; if (ejust==='H') x-=w2; else if (ejust==='C') x-=w2/2; if (x<0) x=0;
        p.push('<rect x="'+x.toFixed(1)+'" y="'+(y-h2).toFixed(1)+'" width="'+w2.toFixed(1)+'" height="'+h2.toFixed(1)+'" rx="0.4" fill="'+c2+'" opacity="'+((st2&&st2.bold)?0.95:0.72)+'"/>');
      }
    });
    p.push('</svg>'); return p.join('');
  }

  var designModal = document.getElementById('fe-design-modal');
  function buildDesignCards(){
    var host=document.getElementById('fe-theme-cards'); if(!host) return;
    host.innerHTML='';
    THEME_META.forEach(function(m){
      var card=document.createElement('div');
      card.className='fe-theme-card'+(m.id===designTheme?' active':'');
      card.setAttribute('data-theme', m.id);
      card.innerHTML='<div class="fe-tc-thumb">'+themeThumb(m.id)+'</div><div class="fe-tc-name">'+m.name+'</div><div class="fe-tc-desc">'+m.desc+'</div>';
      card.addEventListener('click', function(){ setTheme(m.id); });
      host.appendChild(card);
    });
  }
  function markActiveCard(){
    var host=document.getElementById('fe-theme-cards'); if(!host) return;
    Array.prototype.forEach.call(host.children, function(c){
      c.classList.toggle('active', c.getAttribute('data-theme')===designTheme);
    });
  }
  function openDesign(){
    if(!designModal) return;
    if(accentInput) accentInput.value=designAccent;
    if(textInput) textInput.value=designText;
    if(fontSel) fontSel.value=designFont;
    var fl=document.getElementById('fe-brand-fromlogo'); if(fl) fl.disabled=!LOGO_URL;
    buildDesignCards(); updateSwatchActive(); updateFontSample();
    designModal.style.display='flex';
  }
  function closeDesign(){ if(designModal) designModal.style.display='none'; }

  var _dOpen=document.getElementById('fe-design-open'); if(_dOpen) _dOpen.addEventListener('click', openDesign);
  var _dClose=document.getElementById('fe-design-close'); if(_dClose) _dClose.addEventListener('click', closeDesign);
  var _dDone=document.getElementById('fe-design-done'); if(_dDone) _dDone.addEventListener('click', closeDesign);
  if (designModal) designModal.addEventListener('mousedown', function(e){ if(e.target===designModal) closeDesign(); });

  var accentInput=document.getElementById('fe-brand-accent');
  var textInput=document.getElementById('fe-brand-text');
  var fontSel=document.getElementById('fe-brand-font');
  var swHost=document.getElementById('fe-brand-swatches');
  var swTextHost=document.getElementById('fe-brand-text-swatches');
  // readable dark body colours that echo the accent hues (navy/teal/green/plum/bordeaux/rust)
  var FE_TEXT_SWATCHES=['#2b2b2b','#000000','#20304a','#123b38','#1c3a20','#33214d','#4a2323','#4a2a15'];

  function feSavePrefs(){ try{ localStorage.setItem('fe_design', JSON.stringify({accent:designAccent, text:designText, font:designFont, theme:designTheme})); }catch(e){} }
  function feLoadPrefs(){ try{ var p=JSON.parse(localStorage.getItem('fe_design')||'null'); if(p){ if(p.accent) designAccent=p.accent; if(p.text) designText=p.text; if(p.font && ['Helvetica','Times','Palatino','NewCenturySchlbk','Courier','Ocrbb12'].indexOf(p.font)>=0) designFont=p.font; } }catch(e){} }
  function updateFontSample(){ var s=document.getElementById('fe-font-sample'); if(s){ s.style.fontFamily=feCssFont(designFont); s.style.color=designText; } }
  function swatchSync(host, current){ if(!host) return; Array.prototype.forEach.call(host.children,function(b){
    b.classList.toggle('active', (b.getAttribute('data-hex')||'').toLowerCase()===String(current).toLowerCase()); }); }
  function updateSwatchActive(){ swatchSync(swHost, designAccent); swatchSync(swTextHost, designText); }
  function setAccent(hex, apply){ designAccent=hex; if(accentInput) accentInput.value=hex; updateSwatchActive(); buildDesignCards(); if(apply){ applyDesign(); feSavePrefs(); } }
  function setTextColor(hex, apply){ designText=hex; if(textInput) textInput.value=hex; updateSwatchActive(); updateFontSample(); buildDesignCards(); if(apply){ applyDesign(); feSavePrefs(); } }

  if (accentInput){
    accentInput.value=designAccent;
    accentInput.addEventListener('input',  function(){ designAccent=accentInput.value; updateSwatchActive(); buildDesignCards(); });
    accentInput.addEventListener('change', function(){ setAccent(accentInput.value, true); });
  }
  if (textInput){
    textInput.value=designText;
    textInput.addEventListener('input',  function(){ designText=textInput.value; updateSwatchActive(); updateFontSample(); buildDesignCards(); });
    textInput.addEventListener('change', function(){ setTextColor(textInput.value, true); });
  }
  if (fontSel){
    fontSel.value=designFont;
    fontSel.addEventListener('change', function(){ designFont=fontSel.value; updateFontSample(); applyDesign(); feSavePrefs(); });
  }
  if (swHost){
    FE_PALETTES.forEach(function(p){
      var b=document.createElement('span'); b.className='fe-swatch'; b.style.background=p.hex;
      b.title=p.name; b.setAttribute('data-hex', p.hex);
      b.addEventListener('click', function(){ setAccent(p.hex, true); });
      swHost.appendChild(b);
    });
  }
  if (swTextHost){
    FE_TEXT_SWATCHES.forEach(function(hex){
      var b=document.createElement('span'); b.className='fe-swatch'; b.style.background=hex;
      b.title=hex; b.setAttribute('data-hex', hex);
      b.addEventListener('click', function(){ setTextColor(hex, true); });
      swTextHost.appendChild(b);
    });
  }
  // 🌐 Match accent — derive a readable dark body colour from the current accent
  var _textMatch=document.getElementById('fe-text-match');
  if(_textMatch) _textMatch.addEventListener('click', function(){
    setTextColor(feReadableText(designAccent), true);
    flashStatus(DK_UI?'Tekstfarve afstemt med accent':'Text colour matched to accent','#1769ff');
  });

  // one-click coordinated colour schemes (sets accent + text together)
  function setScheme(a, t){
    designAccent=a; designText=t;
    if(accentInput) accentInput.value=a;
    if(textInput)   textInput.value=t;
    if(!designTheme){ designTheme='modern'; markActiveCard(); }   // so the colours are visible
    updateSwatchActive(); updateFontSample(); buildDesignCards();
    applyDesign(); feSavePrefs();
  }
  var schemeHost=document.getElementById('fe-schemes');
  if(schemeHost){
    FE_SCHEMES.forEach(function(s){
      var c=document.createElement('div'); c.className='fe-scheme'; c.title=s.name;
      c.innerHTML='<div class="sw"><i style="background:'+s.a+'"></i><i class="t" style="background:'+s.t+'"></i></div><div class="nm">'+s.name+'</div>';
      c.addEventListener('click', function(){ setScheme(s.a, s.t); flashStatus((DK_UI?'Farvesæt: ':'Scheme: ')+s.name,'#1769ff'); });
      schemeHost.appendChild(c);
    });
  }

  // 🎯 From logo — sample the dominant vivid colour of the uploaded logo
  var _fromLogo=document.getElementById('fe-brand-fromlogo');
  if(_fromLogo) _fromLogo.addEventListener('click', function(){
    if(!LOGO_URL) return;
    var img=new Image();
    img.onload=function(){
      try{
        var W=Math.min(96, img.naturalWidth||96), H=Math.min(96, img.naturalHeight||96);
        var cv=document.createElement('canvas'); cv.width=W; cv.height=H;
        var ctx=cv.getContext('2d'); ctx.drawImage(img,0,0,W,H);
        var d=ctx.getImageData(0,0,W,H).data, buckets={};
        for(var i=0;i<d.length;i+=4){
          var r=d[i],g=d[i+1],b=d[i+2],a=d[i+3]; if(a<128) continue;
          var mx=Math.max(r,g,b), mn=Math.min(r,g,b), sat=mx-mn, lum=(r+g+b)/3;
          if(sat<28 || lum>238 || lum<18) continue;   // skip greys / near-white / near-black
          var key=(r>>5)+'-'+(g>>5)+'-'+(b>>5);
          var bk=buckets[key]||(buckets[key]={r:0,g:0,b:0,n:0,s:0});
          bk.r+=r; bk.g+=g; bk.b+=b; bk.n++; bk.s+=sat;
        }
        var best=null, bestScore=-1;
        for(var k in buckets){ var bk=buckets[k]; var score=bk.n*(bk.s/bk.n); if(score>bestScore){ bestScore=score; best=bk; } }
        if(best){
          var hex=rgbToHex([Math.round(best.r/best.n), Math.round(best.g/best.n), Math.round(best.b/best.n)]);
          if(!designTheme){ designTheme='modern'; markActiveCard(); }   // so the colour is actually visible
          setAccent(hex, true);
          flashStatus('🎯 '+(DK_UI?'Accentfarve hentet fra logo':'Accent colour taken from logo'),'#1769ff');
        } else {
          flashStatus(DK_UI?'Ingen tydelig farve i logoet':'No clear colour found in the logo','#b26a00');
        }
      }catch(e){ flashStatus(DK_UI?'Kunne ikke læse logoet':'Could not read the logo','#b26a00'); }
    };
    img.onerror=function(){ flashStatus(DK_UI?'Kunne ikke indlæse logoet':'Could not load the logo','#b26a00'); };
    img.src=LOGO_URL;
  });

  updateFontSample();

  // ---- right-click context menu -------------------------------------------
  var ctx = document.createElement('div'); ctx.id='fe-ctx'; ctx.style.display='none'; document.body.appendChild(ctx);
  function hideCtx(){ ctx.style.display='none'; ctx.innerHTML=''; }
  function showCtx(px, py, items){
    ctx.innerHTML='';
    items.forEach(function(it){
      var b=document.createElement('div'); b.className='fe-ctx-item'+(it.danger?' danger':'');
      b.textContent=it.label;
      b.addEventListener('mousedown', function(ev){ ev.preventDefault(); ev.stopPropagation(); hideCtx(); it.fn(); });
      ctx.appendChild(b);
    });
    ctx.style.display='block'; ctx.style.left=px+'px'; ctx.style.top=py+'px';
    var r=ctx.getBoundingClientRect();
    if (r.right>window.innerWidth)  ctx.style.left=Math.max(0,px-r.width)+'px';
    if (r.bottom>window.innerHeight) ctx.style.top=Math.max(0,py-r.height)+'px';
  }
  document.addEventListener('mousedown', function(e){ if (e.target && e.target.closest && e.target.closest('#fe-ctx')) return; hideCtx(); });
  document.addEventListener('keydown', function(e){ if (e.key==='Escape') hideCtx(); });
  window.addEventListener('blur', hideCtx);

  page.addEventListener('contextmenu', function(e){
    e.preventDefault();
    // walk up to find an element id (works for divs and svg line hit-areas)
    var node=e.target, id=null;
    while (node && node!==page){ if (node.getAttribute && node.getAttribute('data-id')){ id=node.getAttribute('data-id'); break; } node=node.parentNode; }
    var items=[];
    if (id!==null){
      var idn=parseInt(id,10); select(idn);
      var el=elById(idn);
      if (el && el.kind!=='logo') items.push({ label:L.ctxDuplicate, fn:duplicateSelected });
      if (el && el.kind==='line') {
        items.push({ label:L.ctxWidthPlus,  fn:function(){ changeLineWidth(1); } });
        items.push({ label:L.ctxWidthMinus, fn:function(){ changeLineWidth(-1); } });
        items.push({ label:L.ctxMeet,        fn:meetNearestLines });
      }
      if (el && el.kind==='text') {
        items.push({ label:L.ctxAlignL, fn:function(){ setAlign('V'); } });
        items.push({ label:L.ctxAlignC, fn:function(){ setAlign('C'); } });
        items.push({ label:L.ctxAlignR, fn:function(){ setAlign('H'); } });
      }
      if (el && !isCore(el)) items.push({ label:L.ctxDelete, danger:true, fn:deleteSelected });
      if (el && el.kind==='logo' && LOGO_URL) items.push({ label:L.ctxRemoveLogo, danger:true, fn:removeLogo });
    } else {
      var s=pxmm(), rect=page.getBoundingClientRect();
      var xmm=(e.clientX-rect.left)/s, ymm=PAGE_H-(e.clientY-rect.top)/s;
      if (table && inTableRect(xmm, ymm, tableRectMM())) {
        // right-click on the order-line table: resize options (headers follow)
        selectTable();
        items.push({ label:(DK_UI?'Tabel bredere → mod højre':'Table wider → to the right'), fn:function(){ stretchTable(1.15,'left'); } });
        items.push({ label:(DK_UI?'Tabel bredere ← mod venstre':'Table wider ← to the left'), fn:function(){ stretchTable(1.15,'right'); } });
        items.push({ label:(DK_UI?'Tabel smallere':'Table narrower'), fn:function(){ stretchTable(0.87,'center'); } });
        items.push({ label:(DK_UI?'Flere linjer':'More rows'), fn:function(){ changeRows(1); } });
        items.push({ label:(DK_UI?'Færre linjer':'Fewer rows'), fn:function(){ changeRows(-1); } });
        if (tableInit) items.push({ label:(DK_UI?'Nulstil tabel':'Reset table'), danger:true, fn:resetTable });
      } else {
        items.push({ label:L.ctxAddText, fn:function(){ addTextAt(xmm, ymm); } });
      }
    }
    if (items.length) showCtx(e.clientX, e.clientY, items);
  });

  // Automatically show the standard captions in the account's language (like
  // the rest of Saldi). Reversible with Ctrl+Z; Save to keep it for printing.
  var _preTr = JSON.stringify({ e: elements, t: table, d: deletedIds });
  if (table) tableGroupInit = tableGroup().map(function(el){ return {id:el.id, xa:el.xa, ya:el.ya, xb:el.xb, yb:el.yb}; });
  var _autoTr = applyTranslation();
  render();
  if (_autoTr > 0) { undoStack.push(_preTr); markDirty(); flashStatus(AUTO_TR_MSG, '#b26a00'); }
  feCurLang = feDetectLang(); updateLangToggle();
  if (!elements.length) { statusEl.textContent=L.noel; }
  // Offer to continue a saved draft (working state not yet activated).
  var _bannerShown = !!(FE_DRAFT && FE_DRAFT.state);
  if (_bannerShown) { showDraftBanner(FE_DRAFT.ts); }
  else { var _as = readAutosave(); if (_as) { showAutosaveBanner(_as.ts); _bannerShown = true; } }
  autosaveArmed = true;   // arm only after init (so the initial auto-translation isn't autosaved)
  // First-run guided tour: only on a clean first visit (no draft/autosave banner).
  try { if (!_bannerShown && !localStorage.getItem(TOUR_KEY) && elements.length) setTimeout(function(){ startTour(tourSteps); }, 700); } catch(e){}
})();
</script>

<?php
if ($menu == 'T') {
	print "</div>\n"; // maincontentLargeHolder
}
print "</body></html>\n";
?>
