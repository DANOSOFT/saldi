<?php
ob_start();
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- admin/opret_email.php --- patch 5.0.0 --- 2026.08.04 ---
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
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// Opret email - administration edits the welcome email sent when a customer
// creates an account from the website: subject, sender, text/graphics and the
// package names and prices. The sending itself happens in
// admin/opret_email_send.php through includes/opretEmailFunc.php.
//
// 20260804 Sawaneh New file. Every text comes from findtekst() - see new rows
//                  5056-5113 in importfiler/tekster.csv.
// 20260804 Sawaneh Language selector in the editor, so each language version of the
//                  welcome email can be edited on its own (?sprog=1|2|3).
// 20260804 Sawaneh The key is no longer written into the page. The 'Connection from
//                  the website' card is collapsed, and the key is only handed out on
//                  request (?ajax=api_key) or replaced (?ajax=rotate_key).
// 20260806 Sawaneh Review: the editor was a fixed 380px box that stood mostly empty.
//                  It now starts at 180px, grows with the text and scrolls at 55vh.
// 20260812 Sawaneh Review: ajax calls require this session's CSRF token, a JSON content
//                  type and a matching Origin. The preview iframe is sandboxed, the
//                  package codes the website posts cannot be deleted, the source
//                  toggle labels come from findtekst(), and the Google Fonts import
//                  is gone.

@session_start();
$s_id = session_id();

$modulnr = 104; // Admin module - same as admin_panel.php
$css = "../css/standard.css";
$title = "Opret email";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include(__DIR__ . "/../includes/opretEmailFunc.php");

if (!isset($sprog_id)) {
	$sprog_id = 0;
}


if ($db != $sqdb) {

	$advarsel = htmlspecialchars(addslashes(findtekst(1905, $sprog_id)), ENT_QUOTES);
	print "<BODY onLoad=\"javascript:alert('$advarsel')\">";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">";
	exit;
}

opret_email_setup();

$rediger_sprog = (int) if_isset($_GET, 0, 'sprog');
if (!in_array($rediger_sprog, opret_email_languages(), true)) {
	$rediger_sprog = in_array((int) $sprog_id, opret_email_languages(), true) ? (int) $sprog_id : 1;
}


function opret_email_tekst($textId)
{
	global $sprog_id;
	return findtekst($textId, $sprog_id);
}


function opret_email_eksempeldata()
{
	return array(
		'navn'  => opret_email_tekst(5106),
		'cvrnr' => '12345678',
		'tlf'   => '12345678',
		'email' => opret_email_tekst(5107),
	);
}

if (isset($_GET['ajax'])) {
	while (ob_get_level()) {
		ob_end_clean();
	}
	header('Content-Type: application/json; charset=UTF-8');

	$handling = $_GET['ajax'];
	$input = json_decode(file_get_contents('php://input'), true);
	if (!is_array($input)) {
		$input = array();
	}

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		echo json_encode(array('error' => opret_email_tekst(5090)), JSON_UNESCAPED_UNICODE);
		exit;
	}

	// Every handler below changes state or hands out the api key, and the session
	// cookie travels with a cross-site request just as it does with our own. The
	// check therefore sits ahead of the dispatch, so a handler added later is
	// covered without anyone having to remember it.
	if (!opret_email_csrf_ok()) {
		http_response_code(403);
		echo json_encode(array('error' => opret_email_tekst(5098)), JSON_UNESCAPED_UNICODE);
		exit;
	}

	if ($handling === 'save_template') {
	
		$gem_sprog = (int) if_isset($input, $rediger_sprog, 'sprog');
		if (!in_array($gem_sprog, opret_email_languages(), true)) {
			$gem_sprog = 1;
		}
		opret_email_save_setting(opret_email_setting_name('emne', $gem_sprog), trim((string) if_isset($input, '', 'emne')));
		opret_email_save_setting(opret_email_setting_name('html', $gem_sprog), (string) if_isset($input, '', 'html'));
		opret_email_save_setting('afsender', trim((string) if_isset($input, '', 'afsender')));
		echo json_encode(array('success' => true));
		exit;
	}

	if ($handling === 'save_package') {
		$id = (int) if_isset($input, 0, 'id');
		$navn = trim((string) if_isset($input, '', 'navn'));
		$pris = opret_email_parse_price(if_isset($input, '', 'pris'));
		if (!$id || $navn === '') {
			echo json_encode(array('error' => opret_email_tekst(5083)), JSON_UNESCAPED_UNICODE);
			exit;
		}
		if ($pris === null) {
			echo json_encode(array('error' => opret_email_tekst(5084)), JSON_UNESCAPED_UNICODE);
			exit;
		}
		$qtxt = "UPDATE opret_pakker SET navn = '" . db_escape_string($navn) . "', pris = " . $pris;
		$qtxt .= " WHERE id = $id";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		echo json_encode(array('success' => true, 'pris' => dkdecimal($pris, 2)));
		exit;
	}

	if ($handling === 'add_package') {
		$kode = strtolower(trim((string) if_isset($input, '', 'kode')));
		$navn = trim((string) if_isset($input, '', 'navn'));
		$pris = opret_email_parse_price(if_isset($input, '', 'pris'));
	
		if (!preg_match('/^[a-z0-9_-]{2,30}$/', $kode)) {
			echo json_encode(array('error' => opret_email_tekst(5085)), JSON_UNESCAPED_UNICODE);
			exit;
		}
		if ($navn === '') {
			echo json_encode(array('error' => opret_email_tekst(5083)), JSON_UNESCAPED_UNICODE);
			exit;
		}
		if ($pris === null) {
			echo json_encode(array('error' => opret_email_tekst(5084)), JSON_UNESCAPED_UNICODE);
			exit;
		}
		if (opret_email_package($kode)) {
			echo json_encode(array('error' => opret_email_tekst(5086)), JSON_UNESCAPED_UNICODE);
			exit;
		}
		$r = db_fetch_array(db_select("SELECT max(sorteringnr) AS hoejest FROM opret_pakker", __FILE__ . " linje " . __LINE__));
		$sorteringnr = (int) if_isset($r, 0, 'hoejest') + 10;
		$qtxt = "INSERT INTO opret_pakker (kode, navn, pris, sorteringnr, aktiv) VALUES ";
		$qtxt .= "('" . db_escape_string($kode) . "', '" . db_escape_string($navn) . "', " . $pris . ", $sorteringnr, true)";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		echo json_encode(array('success' => true));
		exit;
	}

	if ($handling === 'delete_package') {
		$id = (int) if_isset($input, 0, 'id');
		if (!$id) {
			echo json_encode(array('error' => opret_email_tekst(5087)), JSON_UNESCAPED_UNICODE);
			exit;
		}
		$r = db_fetch_array(db_select("SELECT count(*) AS antal FROM opret_pakker", __FILE__ . " linje " . __LINE__));
		if ((int) $r['antal'] <= 1) {
			echo json_encode(array('error' => opret_email_tekst(5088)), JSON_UNESCAPED_UNICODE);
			exit;
		}
		// The website posts its package code to opret_email_send.php. Deleting one
		// of those rows would leave every new registration with 'unknown_package'
		// and no welcome email, so the code is kept even though name and price
		// remain editable.
		$r = db_fetch_array(db_select("SELECT kode FROM opret_pakker WHERE id = $id", __FILE__ . " linje " . __LINE__));
		if ($r && in_array(strtolower(trim($r['kode'])), opret_email_beskyttede_koder(), true)) {
			echo json_encode(array('error' => opret_email_tekst(5114)), JSON_UNESCAPED_UNICODE);
			exit;
		}
		db_modify("DELETE FROM opret_pakker WHERE id = $id", __FILE__ . " linje " . __LINE__);
		echo json_encode(array('success' => true));
		exit;
	}

	if ($handling === 'preview') {
		$pakke = opret_email_package(if_isset($input, '', 'kode'));
		$body = opret_email_inline_styles((string) if_isset($input, '', 'html'));
		$body = opret_email_render($body, $pakke, opret_email_eksempeldata());
		$emne = html_entity_decode(strip_tags(opret_email_render((string) if_isset($input, '', 'emne'), $pakke)), ENT_QUOTES, 'UTF-8');
		echo json_encode(array('success' => true, 'emne' => $emne, 'html' => opret_email_wrap($body, $emne)), JSON_UNESCAPED_UNICODE);
		exit;
	}

	if ($handling === 'send_test') {
		
		$eksempel = opret_email_eksempeldata();
		$eksempel['email'] = if_isset($input, '', 'email');
		$test_sprog = (int) if_isset($input, $rediger_sprog, 'sprog');
		if (!in_array($test_sprog, opret_email_languages(), true)) {
			$test_sprog = 1;
		}
		$resultat = opret_email_send(if_isset($input, '', 'email'), if_isset($input, '', 'kode'), $eksempel, $test_sprog);
		if ($resultat['success']) {
			echo json_encode(array('success' => true, 'emne' => $resultat['emne']), JSON_UNESCAPED_UNICODE);
		} else {
		
			$textId = opret_email_error_textid($resultat['error_code']);
			echo json_encode(array('error' => $textId ? opret_email_tekst($textId) : $resultat['error']), JSON_UNESCAPED_UNICODE);
		}
		exit;
	}

	if ($handling === 'api_key') {
		echo json_encode(array('success' => true, 'key' => opret_email_api_key()), JSON_UNESCAPED_UNICODE);
		exit;
	}

	if ($handling === 'rotate_key') {
		echo json_encode(array('success' => true, 'key' => opret_email_rotate_api_key()), JSON_UNESCAPED_UNICODE);
		exit;
	}

	echo json_encode(array('error' => opret_email_tekst(5089)), JSON_UNESCAPED_UNICODE);
	exit;
}

$settings = opret_email_settings($rediger_sprog);
$packages = opret_email_packages();

opret_email_api_key();
$live_koder = opret_email_live_codes();
$placeholders = opret_email_placeholders();

$endpoint_url = 'https://' . if_isset($_SERVER, 'ssl3.saldi.dk', 'SERVER_NAME');
$endpoint_url .= dirname(if_isset($_SERVER, '/admin/opret_email.php', 'SCRIPT_NAME')) . '/opret_email_send.php';

$r = db_fetch_array(db_select("SELECT email FROM brugere WHERE brugernavn = '" . db_escape_string($brugernavn) . "'", __FILE__ . " linje " . __LINE__));
$test_email = (string) if_isset($r, '', 'email');

?>
<!DOCTYPE html>
<html lang="<?php echo $sprog_id == 2 ? 'en' : ($sprog_id == 3 ? 'no' : 'da'); ?>">
<head>
    <title><?php echo htmlspecialchars(opret_email_tekst(5056)); ?></title>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8;">
    <link rel="stylesheet" type="text/css" href="../css/standard.css">
    <link rel="stylesheet" type="text/css" href="../javascript/quill/quill.snow.css">
    <style>
        /* No webfont import: the admin pages load no external resources, and an
           @import from another host would also block the page render on it. */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background: #f7fafc;
            color: #1a202c;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* ─── Top Bar (same as admin_panel.php) ─── */
        .top-bar {
            background: #1a202c;
            color: white;
            padding: 14px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px 0 rgba(0,0,0,0.06);
        }
        .top-bar h1 {
            margin: 0; font-size: 18px; font-weight: 700; letter-spacing: -0.02em;
            display: flex; align-items: center; gap: 10px;
        }
        .top-bar a {
            color: rgba(255,255,255,0.7); text-decoration: none; font-size: 13px;
            font-weight: 500; transition: color 0.2s; padding: 6px 12px; border-radius: 6px;
        }
        .top-bar a:hover { color: #fff; background: rgba(255,255,255,0.1); }

        .container { max-width: 1280px; margin: 0 auto; padding: 28px 32px; }

        /* ─── Beskeder ─── */
        .message {
            background: #c6f6d5; border-left: 4px solid #38a169; color: #22543d;
            padding: 14px 20px; border-radius: 8px; margin-bottom: 24px;
            font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px;
        }
        .message.error { background: #fed7d7; border-left-color: #e53e3e; color: #742a2a; }
        .message.info { background: #e6fffa; border-left-color: #319795; color: #234e52; }

        /* ─── Knapper ─── */
        .btn {
            padding: 9px 18px; background: #319795; color: white; border: none;
            border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600;
            font-family: inherit; transition: all 0.2s; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px; letter-spacing: -0.01em;
        }
        .btn:hover { background: #2c7a7b; box-shadow: 0 2px 4px rgba(49,151,149,0.3); }
        .btn:active { background: #285e61; transform: scale(0.98); }
        .btn:disabled { opacity: 0.55; cursor: default; transform: none; box-shadow: none; }
        .btn-small { padding: 6px 14px; font-size: 13px; border-radius: 6px; }
        .btn-outline { background: transparent; color: #319795; border: 2px solid #319795; }
        .btn-outline:hover { background: #319795; color: white; }
        .btn-danger { background: transparent; color: #e53e3e; border: 2px solid #fed7d7; }
        .btn-danger:hover { background: #e53e3e; border-color: #e53e3e; color: white; box-shadow: none; }

        /* ─── Kort ─── */
        .card {
            background: white; border: 1px solid #e2e8f0; border-radius: 12px;
            box-shadow: 0 1px 3px 0 rgba(0,0,0,0.04); margin-bottom: 20px;
        }
        .card-head {
            padding: 14px 20px; border-bottom: 1px solid #edf2f7;
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
        }
        .card-head h2 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #4a5568; }
        .card-body { padding: 20px; }

        .layout { display: grid; grid-template-columns: 320px minmax(0, 1fr); gap: 24px; align-items: start; }
        @media (max-width: 980px) { .layout { grid-template-columns: minmax(0, 1fr); } }

        label.field { display: block; margin-bottom: 14px; font-size: 12px; font-weight: 700; color: #4a5568; text-transform: uppercase; letter-spacing: 0.05em; }
        label.field span { display: block; margin-bottom: 6px; }
        input[type="text"], input[type="email"], textarea {
            width: 100%; padding: 9px 12px; border: 2px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; font-family: inherit; font-weight: 400; text-transform: none;
            letter-spacing: normal; color: #1a202c; background: #fff; transition: all 0.2s;
        }
        input:focus, textarea:focus { border-color: #319795; outline: none; box-shadow: 0 0 0 3px rgba(49,151,149,0.2); }
        input[readonly] { background: #edf2f7; color: #4a5568; }
        select {
            padding: 9px 12px; border: 2px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; font-family: inherit; background: #fff; color: #1a202c;
        }

        .package { border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin-bottom: 12px; background: #fff; }
        .package:last-of-type { margin-bottom: 0; }
        .package-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
        .package-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; color: #718096; }
        .package-row { display: grid; grid-template-columns: minmax(0, 1fr) 110px; gap: 8px; margin-bottom: 10px; }
        .package-actions { display: flex; gap: 8px; }
        .badge {
            display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 9999px;
            font-size: 12px; font-weight: 600; letter-spacing: 0.01em; line-height: 1.8;
        }
        .badge-active { background: #c6f6d5; color: #22543d; }
        .badge-prepared { background: #fefcbf; color: #744210; }

        /* ─── Editor ─── */
        /* Starts compact and grows with the text, then scrolls instead of pushing
           the preview off screen. min-height keeps an empty template from
           collapsing to a single line. */
        #editor { font-size: 15px; }
        /* height:auto overrides Quill's own height:100%, which would otherwise
           only resolve to auto by accident of the ancestor chain. */
        #editor .ql-editor { height: auto; min-height: 180px; max-height: 55vh; overflow-y: auto; }
        .ql-toolbar.ql-snow, .ql-container.ql-snow { border-color: #e2e8f0; }
        .ql-toolbar.ql-snow { border-radius: 8px 8px 0 0; background: #f7fafc; }
        .ql-container.ql-snow { border-radius: 0 0 8px 8px; }
        /* Same proportions as the editor, so switching view does not jump. */
        #source {
            display: none; min-height: 220px; max-height: 55vh; resize: vertical;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; line-height: 1.5;
        }
        body.source-mode #editor-wrap { display: none; }
        body.source-mode #source { display: block; }

        .placeholders { display: flex; flex-wrap: wrap; gap: 8px; margin: 14px 0 0; }
        .chip {
            border: 1px dashed #319795; background: #e6fffa; color: #234e52; cursor: pointer;
            border-radius: 9999px; padding: 4px 12px; font-size: 12px; font-weight: 600;
            font-family: inherit; transition: all 0.2s;
        }
        .chip:hover { background: #319795; color: #fff; }
        .hint { font-size: 12px; color: #718096; margin-top: 8px; }
        details.card > summary.card-head { cursor: pointer; list-style: none; }
        details.card > summary.card-head::-webkit-details-marker { display: none; }
        details.card > summary.card-head::after {
            content: '\25be'; color: #718096; font-size: 14px; margin-left: auto; transition: transform 0.2s;
        }
        details.card[open] > summary.card-head::after { transform: rotate(180deg); }
        details.card > summary.card-head:hover h2 { color: #319795; }
        #api-key { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; max-width: 340px; }

        .sr-only {
            position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
            overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
        }
        .toolbar-row { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }

        #preview-frame { width: 100%; height: 420px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; }
        code.snippet {
            display: block; white-space: pre; overflow-x: auto; background: #1a202c; color: #e2e8f0;
            padding: 16px; border-radius: 8px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px; line-height: 1.6;
        }
    </style>
</head>
<body>

<div class="top-bar">
    <h1>✉️ <?php echo htmlspecialchars(opret_email_tekst(5056)); ?></h1>
    <div>
        <a href="admin_panel.php" style="margin-right: 20px;">← Admin Panel</a>
        <a href="../index/admin_menu.php">← <?php echo htmlspecialchars(opret_email_tekst(5104)); ?></a>
    </div>
</div>

<div class="container">

<div id="message" class="message" style="display:none;"></div>

<div class="layout">

    <div>
        <div class="card">
            <div class="card-head"><h2><?php echo htmlspecialchars(opret_email_tekst(5057)); ?></h2></div>
            <div class="card-body">
                <div id="packages">
<?php foreach ($packages as $package) { ?>
                    <div class="package" data-id="<?php echo $package['id']; ?>">
                        <div class="package-top">
                            <span class="package-code"><?php echo htmlspecialchars($package['kode']); ?></span>
<?php if (in_array($package['kode'], $live_koder, true)) { ?>
                            <span class="badge badge-active" title="<?php echo htmlspecialchars(opret_email_tekst(5060)); ?>"><?php echo htmlspecialchars(opret_email_tekst(5058)); ?></span>
<?php } else { ?>
                            <span class="badge badge-prepared" title="<?php echo htmlspecialchars(opret_email_tekst(5061)); ?>"><?php echo htmlspecialchars(opret_email_tekst(5059)); ?></span>
<?php } ?>
                        </div>
                        <div class="package-row">
                            <input type="text" class="pkg-navn" value="<?php echo htmlspecialchars($package['navn']); ?>" maxlength="100" aria-label="<?php echo htmlspecialchars(opret_email_tekst(5062)); ?>">
                            <input type="text" class="pkg-pris" value="<?php echo htmlspecialchars(dkdecimal($package['pris'], 2)); ?>" aria-label="<?php echo htmlspecialchars(opret_email_tekst(5063)); ?>">
                        </div>
                        <div class="package-actions">
                            <button type="button" class="btn btn-small" onclick="savePackage(this)"><?php echo htmlspecialchars(opret_email_tekst(3)); ?></button>
                            <button type="button" class="btn btn-small btn-danger" onclick="deletePackage(this, '<?php echo htmlspecialchars($package['navn'], ENT_QUOTES); ?>')"><?php echo htmlspecialchars(opret_email_tekst(1099)); ?></button>
                        </div>
                    </div>
<?php } ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2><?php echo htmlspecialchars(opret_email_tekst(5064)); ?></h2></div>
            <div class="card-body">
                <label class="field"><span><?php echo htmlspecialchars(opret_email_tekst(3338)); ?></span>
                    <input type="text" id="ny-kode" placeholder="premium" maxlength="30">
                </label>
                <label class="field"><span><?php echo htmlspecialchars(opret_email_tekst(138)); ?></span>
                    <input type="text" id="ny-navn" placeholder="Premium" maxlength="100">
                </label>
                <label class="field"><span><?php echo htmlspecialchars(opret_email_tekst(915)); ?></span>
                    <input type="text" id="ny-pris" value="0,00">
                </label>
                <button type="button" class="btn btn-outline" onclick="addPackage()">+ <?php echo htmlspecialchars(opret_email_tekst(5064)); ?></button>
                <div class="hint"><?php echo htmlspecialchars(opret_email_tekst(5066)); ?></div>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-head">
                <h2><?php echo htmlspecialchars(opret_email_tekst(5067)); ?></h2>
                <div class="toolbar-row">
                    <label for="sprog-valg" class="sr-only"><?php echo htmlspecialchars(opret_email_tekst(801)); ?></label>
                    <select id="sprog-valg" onchange="skiftSprog(this.value)" title="<?php echo htmlspecialchars(opret_email_tekst(801)); ?>">
<?php foreach (opret_email_languages() as $sprog_nr) { ?>
                        <option value="<?php echo $sprog_nr; ?>"<?php echo $sprog_nr === $rediger_sprog ? ' selected' : ''; ?>><?php echo htmlspecialchars(findtekst(1, $sprog_nr)); ?></option>
<?php } ?>
                    </select>
                    <button type="button" class="btn btn-small btn-outline" id="source-toggle" onclick="toggleSource()"><?php echo htmlspecialchars(opret_email_tekst(5068)); ?></button>
                    <button type="button" class="btn btn-small" onclick="saveTemplate()"><?php echo htmlspecialchars(opret_email_tekst(5070)); ?></button>
                </div>
            </div>
            <div class="card-body">
<?php if ($settings['fallback']) { ?>
                <div class="message info" style="margin-bottom:16px;">ℹ️ <?php echo htmlspecialchars(opret_email_tekst(5109)); ?></div>
<?php } ?>
                <label class="field"><span><?php echo htmlspecialchars(opret_email_tekst(5071)); ?></span>
                    <input type="text" id="emne" value="<?php echo htmlspecialchars($settings['emne']); ?>" maxlength="200">
                </label>
                <label class="field"><span><?php echo htmlspecialchars(opret_email_tekst(5072)); ?></span>
                    <input type="text" id="afsender" value="<?php echo htmlspecialchars($settings['afsender']); ?>" maxlength="200">
                </label>

                <div id="editor-wrap">
                    <div id="editor"></div>
                </div>
                <textarea id="source" spellcheck="false" aria-label="<?php echo htmlspecialchars(opret_email_tekst(5068)); ?>"></textarea>

                <div class="placeholders">
<?php foreach ($placeholders as $navn => $textId) { ?>
                    <button type="button" class="chip" onclick="insertPlaceholder('<?php echo htmlspecialchars($navn, ENT_QUOTES); ?>')" title="{{<?php echo htmlspecialchars($navn); ?>}}"><?php echo htmlspecialchars(opret_email_tekst($textId)); ?></button>
<?php } ?>
                </div>
                <div class="hint"><?php echo htmlspecialchars(opret_email_tekst(5076)); ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h2><?php echo htmlspecialchars(opret_email_tekst(5077)); ?></h2>
                <div class="toolbar-row">
                    <select id="pakke-valg" aria-label="<?php echo htmlspecialchars(opret_email_tekst(5105)); ?>">
<?php foreach ($packages as $package) { ?>
                        <option value="<?php echo htmlspecialchars($package['kode'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($package['navn']); ?></option>
<?php } ?>
                    </select>
                    <button type="button" class="btn btn-small btn-outline" onclick="showPreview()"><?php echo htmlspecialchars(opret_email_tekst(1133)); ?></button>
                </div>
            </div>
            <div class="card-body">
                <!-- sandbox="": the preview is srcdoc, which would otherwise inherit
                     this page's origin, so script in a stored template would run with
                     the administrator's session. Any administrator can save a
                     template, so this is not limited to whoever is looking at it.
                     The preview only needs to render markup and inline styles. -->
                <iframe id="preview-frame" sandbox="" title="<?php echo htmlspecialchars(opret_email_tekst(3276) . ' - ' . opret_email_tekst(5067)); ?>"></iframe>
                <div class="toolbar-row" style="margin-top:16px;">
                    <input type="email" id="test-email" style="max-width:280px;" value="<?php echo htmlspecialchars($test_email); ?>" placeholder="<?php echo htmlspecialchars(opret_email_tekst(5107)); ?>">
                    <button type="button" class="btn btn-small" onclick="sendTest()"><?php echo htmlspecialchars(opret_email_tekst(5078)); ?></button>
                </div>
                <div class="hint"><?php echo htmlspecialchars(opret_email_tekst(5079)); ?></div>
            </div>
        </div>

   
        <details class="card">
            <summary class="card-head">
                <h2><?php echo htmlspecialchars(opret_email_tekst(5080)); ?></h2>
                <span class="hint" style="margin:0;"><?php echo htmlspecialchars(opret_email_tekst(5110)); ?></span>
            </summary>
            <div class="card-body">
                <div class="hint" style="margin-top:0;margin-bottom:12px;"><?php echo htmlspecialchars(opret_email_tekst(5081)); ?></div>
                <label class="field"><span><?php echo htmlspecialchars(opret_email_tekst(5103)); ?></span>
                    <input type="text" readonly value="<?php echo htmlspecialchars($endpoint_url); ?>" onclick="this.select()">
                </label>
                <label class="field"><span><?php echo htmlspecialchars(opret_email_tekst(5082)); ?></span>
                    <span class="toolbar-row">
                        <input type="text" id="api-key" readonly value="••••••••••••••••••••••••••••••••••••••••" onclick="this.select()">
                        <button type="button" class="btn btn-small btn-outline" onclick="visNoegle()"><?php echo htmlspecialchars(opret_email_tekst(1133)); ?></button>
                        <button type="button" class="btn btn-small btn-danger" onclick="skiftNoegle()"><?php echo htmlspecialchars(opret_email_tekst(5111)); ?></button>
                    </span>
                </label>
                <?php 
                       ?>
                <code class="snippet">$ch = curl_init('<?php echo htmlspecialchars($endpoint_url); ?>');
curl_setopt_array($ch, [
    CURLOPT_POST           =&gt; true,
    CURLOPT_RETURNTRANSFER =&gt; true,
    CURLOPT_TIMEOUT        =&gt; 10,
    CURLOPT_POSTFIELDS     =&gt; http_build_query([
        'key'   =&gt; $opretEmailKey,   // <?php echo htmlspecialchars(opret_email_tekst(5082)); ?> - hentes fra .ht_keys.txt
        'pakke' =&gt; 'finans',         // pakkens kode - identificerer hvilken opret.php der koerte
        'email' =&gt; $email,
        'navn'  =&gt; $firmanavn,
        'cvrnr' =&gt; $cvrnr,
        'tlf'   =&gt; $tlf,
        'sprog' =&gt; 1,               // 1 dansk, 2 engelsk, 3 norsk
    ]),
]);
$svar = json_decode(curl_exec($ch), true);
curl_close($ch);</code>
            </div>
        </details>
    </div>

</div><!-- /layout -->
</div><!-- /container -->

<script src="../javascript/quill/quill.min.js"></script>
<script>

var TXT = <?php echo json_encode(array(
	'imageUrl'       => opret_email_tekst(5099),
	'sourceWarning'  => opret_email_tekst(5100),
	'sessionExpired' => opret_email_tekst(5098),
	'templateSaved'  => opret_email_tekst(5094),
	'packageSaved'   => opret_email_tekst(5095),
	'needEmail'      => opret_email_tekst(5096),
	'testSentTo'     => opret_email_tekst(5097),
	'deletePackage'  => opret_email_tekst(5101),
	'deleteWarning'  => opret_email_tekst(5102),
	'unsavedSwitch'  => opret_email_tekst(5108),
	'rotateConfirm'  => opret_email_tekst(5112),
	'rotateDone'     => opret_email_tekst(5113),
	'sourceView'     => opret_email_tekst(5068),
	'visualEditor'   => opret_email_tekst(5069),
), JSON_UNESCAPED_UNICODE); ?>;
// Sent as X-CSRF-Token on every ajax call. Only this page can read it, which is
// what separates our own request from one started by another site.
var CSRF = <?php echo json_encode(opret_email_csrf_token()); ?>;


var REDIGER_SPROG = <?php echo (int) $rediger_sprog; ?>;


var sourceMode = false;

var quill = new Quill('#editor', {
    theme: 'snow',
    modules: {
        toolbar: {
            container: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ color: [] }, { background: [] }],
                [{ list: 'ordered' }, { list: 'bullet' }],
                [{ align: [] }],
                ['link', 'image'],
                ['clean']
            ],
            handlers: {
            
                image: function () {
                    var url = prompt(TXT.imageUrl);
                    if (!url) return;
                    var range = quill.getSelection(true);
                    quill.insertEmbed(range.index, 'image', url, Quill.sources.USER);
                    quill.setSelection(range.index + 1, Quill.sources.SILENT);
                }
            }
        }
    }
});

quill.clipboard.dangerouslyPasteHTML(<?php echo json_encode($settings['html'], JSON_UNESCAPED_UNICODE); ?>);

function currentHtml() {
    return sourceMode ? document.getElementById('source').value : quill.root.innerHTML;
}


var gemtTilstand = null;

function tilstand() {
    return JSON.stringify([
        document.getElementById('emne').value,
        document.getElementById('afsender').value,
        currentHtml()
    ]);
}

function markerGemt() {
    gemtTilstand = tilstand();
}

function harUgemteAendringer() {
    return gemtTilstand !== null && gemtTilstand !== tilstand();
}

function skiftSprog(sprog) {
    if (harUgemteAendringer() && !confirm(TXT.unsavedSwitch)) {
        document.getElementById('sprog-valg').value = String(REDIGER_SPROG);
        return;
    }
    window.location.search = '?sprog=' + encodeURIComponent(sprog);
}

function showMessage(text, type) {
    var box = document.getElementById('message');
    box.className = 'message' + (type ? ' ' + type : '');
    box.textContent = (type === 'error' ? '⚠️ ' : '✅ ') + text;
    box.style.display = 'flex';
    if (type !== 'error') {
        window.clearTimeout(showMessage.timer);
        showMessage.timer = window.setTimeout(function () { box.style.display = 'none'; }, 4000);
    }
}

function post(handling, payload) {
    return fetch('opret_email.php?ajax=' + handling, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify(payload)
    }).then(function (response) {
     
        return response.text().then(function (text) {
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error(TXT.sessionExpired);
            }
        });
    }).then(function (data) {
        if (data.error) throw new Error(data.error);
        return data;
    });
}

function toggleSource() {
    var source = document.getElementById('source');
    if (!sourceMode) {
        source.value = quill.root.innerHTML;
        sourceMode = true;
    } else {
  
        if (source.value !== quill.root.innerHTML &&
            !confirm(TXT.sourceWarning)) {
            return;
        }
        quill.setContents([]);
        quill.clipboard.dangerouslyPasteHTML(source.value);
        sourceMode = false;
    }
    document.body.classList.toggle('source-mode', sourceMode);
    document.getElementById('source-toggle').textContent = sourceMode ? TXT.visualEditor : TXT.sourceView;
}

function insertPlaceholder(navn) {
    var token = '{{' + navn + '}}';
    if (sourceMode) {
        var source = document.getElementById('source');
        var at = source.selectionStart;
        source.value = source.value.slice(0, at) + token + source.value.slice(source.selectionEnd);
        source.selectionStart = source.selectionEnd = at + token.length;
        source.focus();
        return;
    }
    var range = quill.getSelection(true);
    quill.deleteText(range.index, range.length, Quill.sources.USER);
    quill.insertText(range.index, token, Quill.sources.USER);
    quill.setSelection(range.index + token.length, Quill.sources.SILENT);
}

function visNoegle() {
    post('api_key', {}).then(function (data) {
        var felt = document.getElementById('api-key');
        felt.value = data.key;
        felt.select();
    }).catch(function (error) {
        showMessage(error.message, 'error');
    });
}

function skiftNoegle() {
    if (!confirm(TXT.rotateConfirm)) return;
    post('rotate_key', {}).then(function (data) {
        document.getElementById('api-key').value = data.key;
        showMessage(TXT.rotateDone);
    }).catch(function (error) {
        showMessage(error.message, 'error');
    });
}

function saveTemplate() {
    post('save_template', {
        sprog: REDIGER_SPROG,
        emne: document.getElementById('emne').value,
        afsender: document.getElementById('afsender').value,
        html: currentHtml()
    }).then(function () {
        markerGemt();
        showMessage(TXT.templateSaved);
    }).catch(function (error) {
        showMessage(error.message, 'error');
    });
}

function savePackage(button) {
    var row = button.closest('.package');
    post('save_package', {
        id: Number(row.dataset.id),
        navn: row.querySelector('.pkg-navn').value,
        pris: row.querySelector('.pkg-pris').value
    }).then(function (data) {
        row.querySelector('.pkg-pris').value = data.pris;
        showMessage(TXT.packageSaved);
        refreshPackageNames();
    }).catch(function (error) {
        showMessage(error.message, 'error');
    });
}

function addPackage() {
    post('add_package', {
        kode: document.getElementById('ny-kode').value,
        navn: document.getElementById('ny-navn').value,
        pris: document.getElementById('ny-pris').value
    }).then(function () {
        window.location.reload();
    }).catch(function (error) {
        showMessage(error.message, 'error');
    });
}

function deletePackage(button, navn) {
    if (!confirm(TXT.deletePackage + ' "' + navn + '"?\n\n' + TXT.deleteWarning)) {
        return;
    }
    post('delete_package', { id: Number(button.closest('.package').dataset.id) }).then(function () {
        window.location.reload();
    }).catch(function (error) {
        showMessage(error.message, 'error');
    });
}

function refreshPackageNames() {
    var select = document.getElementById('pakke-valg');
    document.querySelectorAll('.package').forEach(function (row, index) {
        if (select.options[index]) {
            select.options[index].textContent = row.querySelector('.pkg-navn').value;
        }
    });
}

function showPreview() {
    post('preview', {
        emne: document.getElementById('emne').value,
        html: currentHtml(),
        kode: document.getElementById('pakke-valg').value
    }).then(function (data) {
        document.getElementById('preview-frame').srcdoc = data.html;
    }).catch(function (error) {
        showMessage(error.message, 'error');
    });
}

function sendTest() {
    var email = document.getElementById('test-email').value;
    if (!email) {
        showMessage(TXT.needEmail, 'error');
        return;
    }
    post('send_test', { email: email, kode: document.getElementById('pakke-valg').value, sprog: REDIGER_SPROG }).then(function (data) {
        showMessage(TXT.testSentTo + ' ' + email + ' - "' + data.emne + '"');
    }).catch(function (error) {
        showMessage(error.message, 'error');
    });
}

markerGemt();
showPreview();
</script>
</body>
</html>
