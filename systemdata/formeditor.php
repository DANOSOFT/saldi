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
$FE_API    = in_array($fe_action, array('save','reset','logo_upload','logo_remove','savedraft','discarddraft'), true);

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
			// Order-line table: only the geometry the editor changes (row count /
			// top Y / spacing / column X). Never touch str/color/typography so
			// column styling is preserved exactly.
			$set = "xa=$xa, ya=$ya, xb=$xb";
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
			'just'  => $rt['justering'] ? strtoupper($rt['justering']) : 'V',
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
	print "<div style='padding:6px 10px;background:#000066;color:#fff;font-family:Arial,Helvetica,sans-serif;'>";
	print "<a style='color:#fff' href=\"" . htmlspecialchars($returside) . "\">&laquo; " . findtekst('30|Tilbage', $sprog_id) . "</a>";
	print " &nbsp; | &nbsp; <b>Formulareditor</b>";
	print "</div>";
}
?>
<style>
  #fe-wrap { font-family: Arial, Helvetica, sans-serif; }
  #fe-toolbar {
    display:flex; align-items:center; flex-wrap:wrap; gap:10px;
    padding:8px 12px; background:#f2f4f8; border-bottom:1px solid #d3d8e0;
    position:sticky; top:0; z-index:20;
  }
  #fe-toolbar label { font-size:12px; color:#333; }
  #fe-toolbar select, #fe-toolbar input[type=number] { font-size:13px; padding:2px 4px; }
  #fe-toolbar .sep { width:1px; height:22px; background:#c7cdd6; }
  #fe-main { display:flex; align-items:flex-start; gap:0; }
  #fe-canvas-scroll {
    flex:1 1 auto; overflow:auto; background:#8a90a0;
    height:calc(100vh - 190px); min-height:420px; padding:24px;
  }
  #fe-page {
    position:relative; background:#fff; margin:0 auto;
    box-shadow:0 3px 14px rgba(0,0,0,.35);
  }
  #fe-page .fe-bg { position:absolute; inset:0; width:100%; height:100%; opacity:.9; pointer-events:none; }
  #fe-grid { position:absolute; inset:0; pointer-events:none !important; }
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
  #fe-status { font-size:12px; color:#444; margin-left:auto; }
  .fe-btn { font-size:13px; padding:4px 10px; cursor:pointer; }
  .fe-btn.fe-active { background:#1769ff !important; color:#fff !important; border:1px solid #1257cc !important; border-radius:3px; }
  /* clean look while previewing (Show draft) */
  #fe-page.fe-preview .fe-el { outline:none !important; background:transparent !important; cursor:default; }
  #fe-page.fe-preview .fe-el.fe-selected { outline:none !important; box-shadow:none !important; }
  .fe-toggle { font-size:12px; }
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
    <span class="sep"></span>
    <label class="fe-toggle"><input type="checkbox" id="fe-grid-toggle"> <?php echo $T('Gitter','Grid'); ?> (5mm)</label>
    <span class="sep"></span>
    <button type="button" class="fe-btn" id="fe-add-text" title="<?php echo $T('Tilføj tekstfelt','Add text box'); ?>">&#43; <?php echo $T('Tekst','Text'); ?></button>
    <button type="button" class="fe-btn" id="fe-add-line" title="<?php echo $T('Tilføj streg','Add line'); ?>">&#43; <?php echo $T('Streg','Line'); ?></button>
    <?php $bg_label = $bg_url ? $T('Skift baggrund','Change background') : $T('Tilføj baggrund','Add background'); ?>
    <a id="fe-bg-btn" class="fe-btn" style="text-decoration:none;color:inherit;border:1px solid #c7cdd6;border-radius:3px;" href="logoupload.php?upload=yes" target="_blank" rel="noopener" title="<?php echo $T('Upload eller skift baggrund/logo (letterhead)','Upload or change background/logo (letterhead)'); ?>">&#128444; <?php echo htmlspecialchars($bg_label); ?></a>
    <span class="sep"></span>
    <button type="button" class="fe-btn" id="fe-undo" title="Ctrl+Z">&#8630; <?php echo $T('Fortryd','Undo'); ?></button>
    <button type="button" class="fe-btn" id="fe-redo" title="Ctrl+Y">&#8631; <?php echo $T('Gentag','Redo'); ?></button>
    <span class="sep"></span>
    <button type="button" class="fe-btn" id="fe-preview" title="<?php echo $T('Vis hvordan udskriften kommer til at se ud med eksempeldata','Show how the print will look with sample data'); ?>">&#128065; <?php echo $T('Vis kladde','Show draft'); ?></button>
    <button type="button" class="fe-btn" id="fe-savedraft" title="<?php echo $T('Gem som kladde (går ikke live)','Save as draft (does not go live)'); ?>"><?php echo $T('Gem kladde','Save draft'); ?></button>
    <button type="button" class="fe-btn" id="fe-save" style="background:#1769ff;color:#fff;border:1px solid #1257cc;border-radius:3px;" title="<?php echo $T('Gem og gør aktiv (bruges ved udskrift)','Save and make live (used for printing)'); ?>"><?php echo $T('Gem & aktivér','Save & activate'); ?></button>
    <span class="sep"></span>
    <button type="button" class="fe-btn" id="fe-reset" style="color:#b23b3b;border:1px solid #d9a1a1;border-radius:3px;background:#fff;" title="<?php echo $T('Nulstil denne formular til Saldis standard','Reset this form to Saldi&apos;s standard'); ?>">&#8635; <?php echo $T('Nulstil','Reset'); ?></button>
    <span id="fe-status"></span>
  </div>

  <div id="fe-draft-banner" style="display:none;align-items:center;gap:12px;padding:8px 14px;background:#fff8e1;border-bottom:1px solid #e6cf8b;color:#7a5a00;font-family:Arial,Helvetica,sans-serif;font-size:13px;">
    <span id="fe-draft-text"></span>
    <button type="button" id="fe-draft-continue" class="fe-btn" style="background:#1769ff;color:#fff;border:1px solid #1257cc;border-radius:3px;"><?php echo $T('Fortsæt kladde','Continue draft'); ?></button>
    <button type="button" id="fe-draft-discard" class="fe-btn" style="border:1px solid #d9a1a1;color:#b23b3b;background:#fff;border-radius:3px;"><?php echo $T('Kassér kladde','Discard draft'); ?></button>
  </div>

  <div id="fe-main">
    <div id="fe-canvas-scroll">
      <div id="fe-page">
        <?php if ($bg_url) { echo '<img class="fe-bg" src="' . htmlspecialchars($bg_url) . '" alt="">'; } ?>
        <svg id="fe-lines" style="position:absolute;inset:0;pointer-events:none;overflow:visible;"></svg>
        <canvas id="fe-grid"></canvas>
        <!-- elements injected by JS -->
      </div>
    </div>

    <div id="fe-side">
      <h3><?php echo $T('Egenskaber','Properties'); ?></h3>
      <div id="fe-noselect" class="muted"><?php echo $T('Klik på et element for at redigere det. Træk for at flytte. Piletaster: 0,5&nbsp;mm (Shift: 5&nbsp;mm).', 'Click an element to edit it. Drag to move. Arrow keys: 0.5&nbsp;mm (Shift: 5&nbsp;mm).'); ?></div>
      <div id="fe-props" style="display:none;">
        <div class="row"><span id="fe-prop-kind" style="font-weight:bold;"></span></div>
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

  var elements = <?php echo json_encode($fe_data); ?> || [];
  var table    = <?php echo json_encode($fe_table); ?>;
  var VAR_MAP  = <?php echo json_encode(fe_var_map()); ?> || {};
  var DK_UI    = <?php echo $dk_ui ? 'true' : 'false'; ?>;
  var LOGO_URL = <?php echo json_encode($fe_logo_url); ?>;
  var LOGO_NAT = { w: <?php echo (int) $fe_logo_w; ?>, h: <?php echo (int) $fe_logo_h; ?> };
  var FE_DRAFT = <?php echo json_encode($fe_draft); ?>;   // saved working state, or null
  var formNr = <?php echo (int) $form_nr; ?>;
  var sprog  = <?php echo json_encode($sprog); ?>;
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
  var statusEl = document.getElementById('fe-status');
  var selId = null;
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
      if (el.kind === 'line') { renderLine(el); return; }
      renderBox(el);
    });
    if (table) { if (previewMode) renderTablePreview(); else renderTable(); }
    highlight();
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
        d.style.transform = 'translate(' + tx + ', 0)';
        d.style.fontSize = Math.max(4, (c.str>0?c.str:10) * PT_TO_MM * s) + 'px';
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
    d.className = 'fe-el ' + (el.kind === 'logo' ? 'fe-logo' : 'fe-text');
    d.setAttribute('data-id', el.id);
    d.style.left = (el.xa*s) + 'px';
    d.style.top  = ((PAGE_H-el.ya)*s) + 'px';
    var tx = (el.justering==='C') ? '-50%' : (el.justering==='H' ? '-100%' : '0');
    d.style.transform = 'translate(' + tx + ', 0)';

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
    highlight();
    showProps();
  }
  function highlight() {
    Array.prototype.slice.call(page.querySelectorAll('.fe-el')).forEach(function(n){
      n.classList.toggle('fe-selected', parseInt(n.getAttribute('data-id'),10) === selId);
    });
    var tb = document.getElementById('fe-table-el');
    if (tb) tb.classList.toggle('fe-selected', selId === 'table');
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
    if (selId === 'table') { flashStatus(L.coreCantDelete, '#b26a00'); return; }
    var el = elById(selId);
    if (!el) return;
    // The logo can't be deleted as an element (it's a fixed slot), but pressing
    // Delete on it should offer to remove the uploaded logo image itself.
    if (el.kind === 'logo') { removeLogo(); return; }
    if (isCore(el)) { flashStatus(L.coreCantDelete, '#b26a00'); return; }
    pushUndo();
    if (el.id > 0) deletedIds.push(el.id);
    elements = elements.filter(function (x) { return x !== el; });
    selId = null; render(); showProps(); markDirty();
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
    select(el.id);
    var s = pxmm();
    var startX = ev.clientX, startY = ev.clientY;
    var origXa = el.xa, origYa = el.ya;
    var moved = false;
    function onMove(e) {
      if (!moved) { pushUndo(); moved = true; }
      var dx = e.clientX-startX, dy = e.clientY-startY;
      var nx = clampX(origXa + dx/s);
      var ny = clampY(origYa - dy/s);   // screen down = y decreases
      if (grid) { el.xa = clampX(snap(nx)); el.ya = clampY(snap(ny)); clearGuides(); }
      else {
        // smart guides: snap to other elements' x / y so fields line up
        var gx = snapVal(nx, snapTargetsX(el));
        var gy = snapVal(ny, snapTargetsY(el));
        el.xa = (gx !== null) ? gx : nx;
        el.ya = (gy !== null) ? gy : ny;
        drawGuides(gx, gy);
      }
      node.style.left = (el.xa*s) + 'px';
      node.style.top  = ((PAGE_H-el.ya)*s) + 'px';
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
    var xs = [105];   // page centre
    elements.forEach(function(e){ if (e !== self && e.kind !== 'line') xs.push(e.xa); });
    if (table) table.cols.forEach(function(c){ xs.push(c.xa); });
    return xs;
  }
  function snapTargetsY(self) {
    var ys = [];
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

  // ---- translate the standard literal captions (Danish <-> English) --------
  // Only whole-caption text elements are touched; variable chips are never
  // altered. Content editing is already safe + undoable.
  var CAPTION_PAIRS = [
    ['Nummer','Number'],['Dato','Date'],['Side','Page'],['Sælger','Seller'],['Afdeling','Department'],
    ['Betalingsbet','Payment terms'],['Betalingsbet.','Payment terms'],['Forfaldsdato','Due date'],
    ['Kontonr','Account no.'],['Deres ref','Your ref'],['Vores ordre nr','Our order no.'],
    ['Inden levering','Before delivery'],['Telefon','Phone'],['Tlf','Phone'],['Varenr','Item no.'],
    ['Beskrivelse','Description'],['Antal','Qty'],['Pris','Price'],['Sum','Total'],['Rabat','Discount'],
    ['Enhed','Unit'],['Netto','Net'],['moms','VAT'],['I alt','Total'],['Transport til side','Carried forward'],
    ['Kundenr','Customer no.'],['Faktura','Invoice'],['Tilbud','Quote'],['Kreditnota','Credit note'],
    ['Følgeseddel','Delivery note'],['Ordrebekræftelse','Order confirmation'],['Købsfaktura','Purchase invoice'],
    ['Rekvisition','Requisition'],['Att','Att'],['CVR-nr','VAT no.'],['CVR nr','VAT no.'],['EAN','EAN']
  ];
  // Silently map the standard captions toward the account's UI language.
  // Returns the number changed. Variables (chips) are never touched.
  function applyTranslation() {
    var toEN = !DK_UI;               // English UI -> Danish template captions to English
    var map = {};
    CAPTION_PAIRS.forEach(function(p){ map[(toEN?p[0]:p[1]).toLowerCase()] = toEN?p[1]:p[0]; });
    var n = 0;
    elements.forEach(function(el){
      if (el.kind!=='text') return;
      var t = (el.besk||'').trim(); if (!t) return;
      var punc='', core=t;
      if (/[:.]$/.test(t)) { punc=t.slice(-1); core=t.slice(0,-1).trim(); }
      var hit = map[core.toLowerCase()];
      if (hit && hit.toLowerCase()!==core.toLowerCase()) { el.besk = hit + punc; n++; }
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
    if ((e.key==='Delete') && selId!==null && !inField) { e.preventDefault(); deleteSelected(); return; }
    var el = elById(selId); if (!el) return;
    if (['ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].indexOf(e.key)<0) return;
    if (document.activeElement && document.activeElement.tagName==='INPUT') return;
    e.preventDefault();
    var step = e.shiftKey ? 5 : 0.5;
    pushUndo();
    if (e.key==='ArrowUp')    { el.ya=clampY(el.ya+step); if(el.kind==='line') el.yb=clampY(el.yb+step); }
    if (e.key==='ArrowDown')  { el.ya=clampY(el.ya-step); if(el.kind==='line') el.yb=clampY(el.yb-step); }
    if (e.key==='ArrowLeft')  { el.xa=clampX(el.xa-step); if(el.kind==='line') el.xb=clampX(el.xb-step); }
    if (e.key==='ArrowRight') { el.xa=clampX(el.xa+step); if(el.kind==='line') el.xb=clampX(el.xb+step); }
    render(); syncProps(); markDirty();
  });

  function undo() {
    if (!undoStack.length) return;
    redoStack.push(snapshot());
    restore(undoStack.pop());
    selId = null; render(); showProps(); markDirty();
  }
  function redo() {
    if (!redoStack.length) return;
    undoStack.push(snapshot());
    restore(redoStack.pop());
    selId = null; render(); showProps(); markDirty();
  }
  document.getElementById('fe-undo').addEventListener('click', undo);
  var _redoBtn = document.getElementById('fe-redo'); if (_redoBtn) _redoBtn.addEventListener('click', redo);
  document.getElementById('fe-delete').addEventListener('click', deleteSelected);

  // ---- dirty / save -------------------------------------------------------
  var dirty = false;
  function markDirty(){ dirty=true; statusEl.textContent=L.unsaved; statusEl.style.color='#b26a00'; }
  window.addEventListener('beforeunload', function(e){ if (dirty){ e.preventDefault(); e.returnValue=''; } });

  document.getElementById('fe-save').addEventListener('click', function(){
    statusEl.textContent=L.saving; statusEl.style.color='#444';
    var payload = elements.map(function(el){
      return { id: el.id, art: el.art, xa: el.xa, ya: el.ya, xb: el.xb, yb: el.yb, str: el.str, color: el.color, justering: el.justering, fed: el.fed, kursiv: el.kursiv, besk: el.besk, font: el.font, side: el.side };
    });
    if (table) {
      // generelt row: xa=row count, ya=top Y, xb=row spacing
      payload.push({ id: table.gen.id, art: 3, xa: table.gen.count, ya: table.gen.ya, xb: table.gen.spacing });
      table.cols.forEach(function(c){ payload.push({ id: c.id, art: 3, xa: c.xa, ya: c.ya, xb: c.xb }); });
    }
    var body = { form_nr: formNr, sprog: sprog, elements: payload, deleted: deletedIds };
    fetch('formeditor.php?fe_action=save', {
      method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json'},
      body: JSON.stringify(body), credentials:'same-origin'
    }).then(function(r){ return r.json(); }).then(function(j){
      if (j && j.ok) {
        dirty=false; deletedIds=[];
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
  zEl.addEventListener('input', function(){ zoom=parseInt(zEl.value,10)/100; zVal.textContent=zEl.value+'%'; render(); });
  document.getElementById('fe-grid-toggle').addEventListener('change', function(e){ grid=e.target.checked; render(); });

  // Show draft: toggle preview (sample data, clean look, no editing chrome)
  document.getElementById('fe-preview').addEventListener('click', function(){
    previewMode = !previewMode;
    this.classList.toggle('fe-active', previewMode);
    page.classList.toggle('fe-preview', previewMode);
    if (previewMode) { selId = null; showProps(); }
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

  page.addEventListener('mousedown', function(e){ if (drawLineMode) return; if (e.target===page || e.target===gridC) { selId=null; highlight(); showProps(); } });

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
      items.push({ label:L.ctxAddText, fn:function(){ addTextAt(xmm, ymm); } });
    }
    if (items.length) showCtx(e.clientX, e.clientY, items);
  });

  // Automatically show the standard captions in the account's language (like
  // the rest of Saldi). Reversible with Ctrl+Z; Save to keep it for printing.
  var _preTr = JSON.stringify({ e: elements, t: table, d: deletedIds });
  var _autoTr = applyTranslation();
  render();
  if (_autoTr > 0) { undoStack.push(_preTr); markDirty(); flashStatus(AUTO_TR_MSG, '#b26a00'); }
  if (!elements.length) { statusEl.textContent=L.noel; }
  // Offer to continue a saved draft (working state not yet activated).
  if (FE_DRAFT && FE_DRAFT.state) { showDraftBanner(FE_DRAFT.ts); }
})();
</script>

<?php
if ($menu == 'T') {
	print "</div>\n"; // maincontentLargeHolder
}
print "</body></html>\n";
?>
