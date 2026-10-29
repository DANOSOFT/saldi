<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kladdeliste.php --- patch 5.0.0 --- 2026-10-20 ---
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
// Copyright (c) 2003-2026 Danosoft.ApS
// -----------------------------------------------------------------------------------
// 20150722 PHR Vis alle/egne gemmes nu som cookie. 
// 20181220 MSC - Rettet ny kladde knap til Ny
// 20190130 MSC - Rettet topmenu design til
// 20210211 PHR - Some cleanup
// 20211112 MSC - Implementing new design
// 20220627 MSC - Implementing new design
// 20220930 MSC - Changed new button text to a plus icon, if the design is topmenu
// 20230708 LOE - A minor modification
// 12/02/2025 PBLM - Added a new button to open the digital approver
// 16/05/2025 make sure the back button redirect too the previous page rather than going back to the dashboard
// 20251021 LOE Added pagination and static header and footer
// 20260126 PHR fixed $exitDraft
// 20260706 MJ Optimized cash journal list entry counts for large databases.
// 20260904 Sawaneh WP-1.4: pass GET returside (sanitised) to topLineFinans; exitDraft cast to int
// 20260908 SZ SST-755: exitDraft's release now also requires hvem to still match the
//                  current user, so it can't clear a lock a different user has since taken.
// 20260910 SZ SST-755 (CodeRabbit): removed the exitDraft release entirely - it still let a
//                  stale tab clobber a lock a newer tab had since acquired (no tidspkt check),
//                  and every real caller now goes through includes/luk.php instead (confirmed
//                  no remaining ?exitDraft= link generator anywhere in the codebase).
// 20261002 NTR - Dropped the returside parameter from both the normal and the locked-kladde links so kassekladde falls back to its own default return target.
// 20261012 CL/SZ SD-730 Deleting an empty journal also deletes the journal's own documents (journalDocumentsDelete()); if a
//                 document's file can't be removed, the journal and that document's row stay (CodeRabbit).
// 20261020 ASR - New layout for the journal list (prototype_kladdeliste.html, 6/10-2026): tabs Åbne/Simulerede/
//                 Bogførte/Alle with counts, columns per tab, a permanent search row under the headings, Alle/Mine,
//                 the journal's gear panel (columns, export, reset). The list no longer uses includes/grid.php; it
//                 loads its rows from this file with POST kl=list (POST, so the navigation stack is not touched).
//                 The delete endpoints above are unchanged. Preferences live in datatables 'kladdelst2'; the
//                 Alle/Mine choice is also written to the old 'kladdelst' row so grid.php reads it if reverted.
// 20261021 ASR - On the redesign branch. Like the other pages, the head, <html data-theme>, the fonts and the shell's
//                 breadcrumb come from online.php; the list is a .kl-page wrapper styled by css/kladdeliste.css with the
//                 saldi-theme.css tokens (dark mode follows data-theme). The Assist menu finds the tour through
//                 #tutorial-help. The list/csv/pref POSTs count as reads in the permission gate.
// 20261022 CL/NTR - css/kladdeliste.css: headings, counts, row numbers, switch notes, footer and the trash icon use
//                 --text-2 instead of --text-3 (too low contrast); stylesheet bumped to v=20261022b.

@session_start();
$s_id=session_id();

// delete functionality for cash journals - 2025-10-18
if (isset($_GET['delete_kladde'])) {
    $kladde_id = (int)$_GET['delete_kladde'];
    
    include("../includes/connect.php");
    include("../includes/std_func.php");
    
    $qtxt = "SELECT db FROM online WHERE session_id = '$s_id' ORDER BY logtime DESC LIMIT 1";
    $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    $delete_error = null;
    
    if ($r = db_fetch_array($q)) {
        $db = trim($r['db']);
        
        if ($db && $db != $sqdb) {
            $connection = db_connect($sqhost, $squser, $sqpass, $db, __FILE__ . " linje " . __LINE__);
        }
        
        $check_query = db_select("SELECT bogfort FROM kladdeliste WHERE id = $kladde_id", __FILE__ . " linje " . __LINE__);
        if ($check_row = db_fetch_array($check_query)) {
            if ($check_row['bogfort'] == '-' || $check_row['bogfort'] == '!') {
                $count_query = db_select("SELECT COUNT(*)::integer as entry_count FROM kassekladde WHERE kladde_id = $kladde_id", __FILE__ . " linje " . __LINE__);
                $count_row = db_fetch_array($count_query);
                $entry_count = intval($count_row['entry_count']);
                
                if ($entry_count == 0) {
                    // SD-730: the journal's own documents go with it; if a file can't be removed, the journal stays
                    require_once __DIR__ . '/kassekladde_includes/journalDocumentsFunc.php';
                    if (journalDocumentsDelete($db, $kladde_id)) {
                        db_modify("DELETE FROM kladdeliste WHERE id = $kladde_id", __FILE__ . " linje " . __LINE__);
                        header("Location: kladdeliste.php?reset_grid=kladdelst");
                        exit;
                    }
                    $delete_error = "documents_not_deleted";
                } else {
                    $delete_error = "not_empty:$entry_count";
                }
            } else {
                $delete_error = "already_posted";
            }
        } else {
            $delete_error = "not_found";
        }
    } else {
        $delete_error = "session_expired";
    }
    
    header("Location: kladdeliste.php?delete_error=" . urlencode($delete_error));
    exit;
}

if (isset($_POST['delete_kladde'])) {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    ob_start();
    
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });
    
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error', 
                'message' => 'Fatal error: ' . $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);
        }
    });
    
    try {
        $kladde_id = (int)$_POST['delete_kladde'];
        
        if ($kladde_id <= 0) {
            throw new Exception("Invalid kladde_id: " . $_POST['delete_kladde']);
        }
        
        include("../includes/connect.php");
        include("../includes/std_func.php");
        
        $qtxt = "SELECT db FROM online WHERE session_id = '$s_id' ORDER BY logtime DESC LIMIT 1";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        if ($r = db_fetch_array($q)) {
            $db = trim($r['db']);
            
            if ($db && isset($sqdb) && $db != $sqdb) {
                $connection = db_connect($sqhost, $squser, $sqpass, $db, __FILE__ . " linje " . __LINE__);
            }
            
            $check_query = db_select("SELECT bogfort FROM kladdeliste WHERE id = $kladde_id", __FILE__ . " linje " . __LINE__);
            if ($check_row = db_fetch_array($check_query)) {
                if ($check_row['bogfort'] == '-' || $check_row['bogfort'] == '!') {
                    $count_query = db_select("SELECT COUNT(*)::integer as entry_count FROM kassekladde WHERE kladde_id = $kladde_id", __FILE__ . " linje " . __LINE__);
                    $count_row = db_fetch_array($count_query);
                    $entry_count = intval($count_row['entry_count']);
                    
                    if ($entry_count == 0) {
                        // SD-730: the journal's own documents go with it; if a file can't be removed, the journal stays
                        require_once __DIR__ . '/kassekladde_includes/journalDocumentsFunc.php';
                        if (!journalDocumentsDelete($db, $kladde_id)) {
                            ob_end_clean();
                            header('Content-Type: application/json');
                            echo json_encode(['status' => 'error', 'message' => findtekst('5437|Et dokument til kladden kunne ikke slettes, så kladden er ikke slettet', $sprog_id ?? 1)]);
                            exit;
                        }
                        db_modify("DELETE FROM kladdeliste WHERE id = $kladde_id", __FILE__ . " linje " . __LINE__);
                        ob_end_clean();
                        header('Content-Type: application/json');
                        echo json_encode(['status' => 'deleted', 'id' => $kladde_id]);
                        exit;
                    } else {
                        ob_end_clean();
                        header('Content-Type: application/json');
                        echo json_encode(['status' => 'not_empty', 'entry_count' => $entry_count]);
                        exit;
                    }
                } else {
                    ob_end_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'already_posted', 'bogfort' => $check_row['bogfort']]);
                    exit;
                }
            } else {
                ob_end_clean();
                header('Content-Type: application/json');
                echo json_encode(['status' => 'not_found', 'id' => $kladde_id, 'db' => $db]);
                exit;
            }
        } else {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Session expired or not found', 'session_id' => $s_id]);
            exit;
        }
    } catch (Exception $e) {
        $buffered = ob_get_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error', 
            'message' => $e->getMessage(), 
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'buffered' => substr($buffered, 0, 500)
        ]);
        exit;
    }
}

$css = "../css/standard.css";
$modulnr = 2;
$title = "kladdeliste";
$klAction = isset($_POST['kl']) ? (string)$_POST['kl'] : '';
// JSON/CSV requests and the reset redirect must not get the head, body tag and button style that online.php prints;
// what the includes still print goes into a buffer that is dropped before the JSON/CSV/redirect
if ($klAction !== '' || isset($_GET['reset_grid'])) { $header = 'nix'; $bg = 'nix'; ob_start(); }
// Loading the list, the CSV and the user's own view settings are reads in the permission gate (online.php)
if (in_array($klAction, array('list', 'csv', 'pref'), true)) $permission_post_read = true;
include("../includes/connect.php");
include("../includes/std_func.php");
include("../includes/online.php");

if (strpos(findtekst('639|Kladdeliste', $sprog_id), 'undtrykke')) {
	$qtxt = "update tekster set tekst = '' where tekst_id >= '600'";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

/* ---------- helpers ---------- */
function kl_t($da, $en) {
	global $sprog_id;
	return ($sprog_id == 2) ? $en : $da;
}
// db text -> UTF-8 (JSON); request text (UTF-8) -> db encoding
function kl_utf8($s) {
	global $db_encode;
	$s = (string)$s;
	if ($db_encode == 'UTF8') return $s;
	$r = @iconv('ISO-8859-15', 'UTF-8//IGNORE', $s);
	return $r === false ? $s : $r;
}
function kl_dbtxt($s) {
	global $db_encode;
	$s = (string)$s;
	if ($db_encode == 'UTF8') return $s;
	$r = @iconv('UTF-8', 'ISO-8859-15//TRANSLIT', $s);
	return $r === false ? $s : $r;
}
// UTF-8 literal -> HTML in the page's charset
function kl_h($s) {
	global $db_encode;
	$s = (string)$s;
	if ($db_encode != 'UTF8') {
		$r = @iconv('UTF-8', 'ISO-8859-15//TRANSLIT', $s);
		if ($r !== false) $s = $r;
	}
	return htmlspecialchars($s, ENT_QUOTES, $db_encode == 'UTF8' ? 'UTF-8' : 'ISO-8859-15');
}
function kl_json($data) {
	while (ob_get_level()) ob_end_clean();
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
	exit;
}
// "01-09-2026", "1.9.2026", "010926", "01092026" -> "2026-09-01"; anything else -> null
function kl_isodate($s) {
	$s = trim($s);
	if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2}|\d{4})$/', $s, $m) || preg_match('/^(\d{2})(\d{2})(\d{2}|\d{4})$/', $s, $m)) {
		$y = strlen($m[3]) == 2 ? 2000 + (int)$m[3] : (int)$m[3];
		if (checkdate((int)$m[2], (int)$m[1], $y)) return sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]);
	}
	return null;
}
function kl_datewhere($field, $term) {
	$term = trim($term);
	if ($term === '') return '';
	if (strpos($term, ':') !== false) {
		list($a, $b) = array_map('trim', explode(':', $term, 2));
		$w = array();
		if ($a !== '' && ($d = kl_isodate($a))) $w[] = "$field >= '$d'";
		if ($b !== '' && ($d = kl_isodate($b))) $w[] = "$field <= '$d'";
		return $w ? '(' . implode(' AND ', $w) . ')' : '';
	}
	if ($d = kl_isodate($term)) return "$field = '$d'";
	return "to_char($field, 'DD-MM-YYYY') ILIKE '%" . db_escape_string(kl_dbtxt($term)) . "%'";
}
// the user's name as stored (online.php escapes $brugernavn; redesign keeps the raw one in $brugernavn_raw)
function kl_me() {
	global $brugernavn, $brugernavn_raw;
	return isset($brugernavn_raw) ? (string)$brugernavn_raw : (string)$brugernavn;
}
function kl_locked($row) {
	$t = isset($row['tidspkt']) ? trim((string)$row['tidspkt']) : '';
	if ($t === '') return false;
	$b = strpos($t, ' ') ? explode(' ', $t)[1] : $t;
	return !((date('U') - (int)trim($b) > 3600) || $row['hvem'] == kl_me());
}

/* ---------- preferences ---------- */
$klDefaults = array('scope' => 'egne', 'cols' => array('ejer' => true, 'linjer' => true, 'af' => true), 'pp' => 100);
function kl_prefs() {
	global $bruger_id, $klDefaults;
	$p = $klDefaults;
	$r = db_fetch_array(db_select("SELECT filter_setup FROM datatables WHERE user_id = '$bruger_id' AND tabel_id = 'kladdelst2'", __FILE__ . " linje " . __LINE__));
	if ($r && ($j = json_decode($r['filter_setup'], true)) && is_array($j)) {
		if (isset($j['scope']) && in_array($j['scope'], array('alle', 'egne'), true)) $p['scope'] = $j['scope'];
		if (isset($j['cols']) && is_array($j['cols'])) foreach ($p['cols'] as $k => $v) if (isset($j['cols'][$k])) $p['cols'][$k] = (bool)$j['cols'][$k];
		if (isset($j['pp']) && in_array((int)$j['pp'], array(50, 100, 250, 500), true)) $p['pp'] = (int)$j['pp'];
		return $p;
	}
	// first visit with the new list: take Alle/Mine from the old grid's saved filter
	$r = db_fetch_array(db_select("SELECT filter_setup FROM datatables WHERE user_id = '$bruger_id' AND tabel_id = 'kladdelst'", __FILE__ . " linje " . __LINE__));
	if ($r && ($j = json_decode($r['filter_setup'], true)) && isset($j[0]['options'][0]['name']) && in_array($j[0]['options'][0]['name'], array('alle', 'egne'), true)) {
		$p['scope'] = $j[0]['options'][0]['name'];
	}
	return $p;
}
function kl_save_prefs($p) {
	global $bruger_id;
	$json = db_escape_string(json_encode($p));
	if (db_fetch_array(db_select("SELECT id FROM datatables WHERE user_id = '$bruger_id' AND tabel_id = 'kladdelst2'", __FILE__ . " linje " . __LINE__))) {
		db_modify("UPDATE datatables SET filter_setup = '$json' WHERE user_id = '$bruger_id' AND tabel_id = 'kladdelst2'", __FILE__ . " linje " . __LINE__);
	} else {
		db_modify("INSERT INTO datatables (user_id, tabel_id, filter_setup) VALUES ('$bruger_id', 'kladdelst2', '$json')", __FILE__ . " linje " . __LINE__);
	}
	// keep the old grid's Alle/Mine in step (same format grid.php writes)
	$legacy = db_escape_string(json_encode(array(array('filterName' => 'filter', 'options' => array(array('name' => $p['scope'], 'checked' => true))))));
	db_modify("UPDATE datatables SET filter_setup = '$legacy' WHERE user_id = '$bruger_id' AND tabel_id = 'kladdelst'", __FILE__ . " linje " . __LINE__);
}

if (isset($_GET['reset_grid'])) {
	// the GET delete above lands here; the old grid's saved view was cleared too
	db_modify("DELETE FROM datatables WHERE user_id = '$bruger_id' AND tabel_id = 'kladdelst'", __FILE__ . " linje " . __LINE__);
	while (ob_get_level()) ob_end_clean();
	header("Location: kladdeliste.php");
	exit;
}

/* ---------- list query ---------- */
$klGroupSql = "CASE WHEN k.bogfort IN ('-','!') THEN 'open' WHEN k.bogfort = 'S' THEN 'sim' ELSE 'post' END";
function kl_where($req) {
	$w = array();
	if (($req['scope'] ?? '') === 'egne') $w[] = "k.oprettet_af = '" . db_escape_string(kl_me()) . "'";
	$f = isset($req['f']) && is_array($req['f']) ? $req['f'] : array();
	$text = array('nr' => 'k.id::text', 'note' => 'k.kladdenote', 'ejer' => 'k.oprettet_af', 'af' => 'k.bogfort_af');
	foreach ($text as $k => $field) {
		if (isset($f[$k]) && trim((string)$f[$k]) !== '') $w[] = "COALESCE($field, '') ILIKE '%" . db_escape_string(kl_dbtxt(trim((string)$f[$k]))) . "%'";
	}
	if (isset($f['dato']) && ($d = kl_datewhere('k.kladdedate', (string)$f['dato'])) !== '') $w[] = $d;
	if (isset($f['bogf']) && ($d = kl_datewhere('k.bogforingsdate', (string)$f['bogf'])) !== '') $w[] = $d;
	return $w ? implode(' AND ', $w) : '1=1';
}
function kl_rows($req, $limit, $offset) {
	global $klGroupSql;
	$where = kl_where($req);
	$tab = in_array($req['tab'] ?? '', array('open', 'sim', 'post', 'all'), true) ? $req['tab'] : 'open';
	$tabWhere = $tab == 'all' ? '' : " AND $klGroupSql = '$tab'";
	$sorts = array('id' => 'k.id', 'note' => 'lower(k.kladdenote)', 'dato' => 'k.kladdedate', 'ejer' => 'lower(k.oprettet_af)',
		'bogf' => 'k.bogforingsdate', 'af' => 'lower(k.bogfort_af)', 'linjer' => '(SELECT count(*) FROM kassekladde x WHERE x.kladde_id = k.id)');
	$sort = isset($sorts[$req['sort'] ?? '']) ? $sorts[$req['sort']] : null;
	$dir = (($req['dir'] ?? '') === 'desc') ? 'DESC' : 'ASC';
	$order = ($tab == 'all' ? "CASE WHEN k.bogfort IN ('-','!') THEN 0 WHEN k.bogfort = 'S' THEN 1 ELSE 2 END, " : '')
		. ($sort ? "$sort $dir NULLS LAST, " : '') . 'k.id DESC';
	$qtxt = "SELECT k.id, k.kladdedate, k.oprettet_af, k.kladdenote, k.bogfort, k.bogforingsdate, k.bogfort_af, k.tidspkt, k.hvem
		FROM kladdeliste k WHERE $where$tabWhere ORDER BY $order" . ($limit ? " LIMIT " . (int)$limit . " OFFSET " . (int)$offset : '');
	$rows = array();
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $rows[] = $r;
	// line counts for these journals only (the whole kassekladde table is not grouped)
	$n = array();
	if ($rows) {
		$ids = implode(',', array_map(function ($r) { return (int)$r['id']; }, $rows));
		$q = db_select("SELECT kladde_id, count(*) AS n FROM kassekladde WHERE kladde_id IN ($ids) GROUP BY kladde_id", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) $n[(int)$r['kladde_id']] = (int)$r['n'];
	}
	$out = array();
	foreach ($rows as $r) {
		$g = in_array($r['bogfort'], array('-', '!'), true) ? 'open' : ($r['bogfort'] == 'S' ? 'sim' : 'post');
		$out[] = array(
			'id' => (int)$r['id'],
			'dato' => $r['kladdedate'] ? dkdato($r['kladdedate']) : '',
			'ejer' => kl_utf8($r['oprettet_af']),
			'note' => kl_utf8(strip_tags((string)$r['kladdenote'])),
			'g' => $g,
			'bogf' => $r['bogforingsdate'] ? dkdato($r['bogforingsdate']) : '',
			'af' => $g == 'open' ? '' : kl_utf8($r['bogfort_af']),
			'linjer' => $n[(int)$r['id']] ?? 0,
			'laast' => ($g != 'post' && kl_locked($r)) ? kl_utf8($r['hvem']) : '',
		);
	}
	return $out;
}

if ($klAction === 'list') {
	$req = $_POST;
	$where = kl_where($req);
	$counts = array('open' => 0, 'sim' => 0, 'post' => 0, 'all' => 0);
	$q = db_select("SELECT $klGroupSql AS g, count(*) AS n FROM kladdeliste k WHERE $where GROUP BY 1", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) { $counts[$r['g']] = (int)$r['n']; $counts['all'] += (int)$r['n']; }
	if (($req['tab'] ?? '') === 'auto') $req['tab'] = $counts['open'] ? 'open' : 'post';
	$tab = in_array($req['tab'] ?? '', array('open', 'sim', 'post', 'all'), true) ? $req['tab'] : 'open';
	$pp = in_array((int)($req['pp'] ?? 0), array(50, 100, 250, 500), true) ? (int)$req['pp'] : 100;
	$total = $counts[$tab];
	$pages = max(1, (int)ceil($total / $pp));
	$page = min(max(1, (int)($req['page'] ?? 1)), $pages);
	$req['tab'] = $tab;
	kl_json(array('tab' => $tab, 'counts' => $counts, 'total' => $total, 'page' => $page, 'pages' => $pages, 'rows' => kl_rows($req, $pp, ($page - 1) * $pp)));
}

if ($klAction === 'pref') {
	$p = kl_prefs();
	if (isset($_POST['scope']) && in_array($_POST['scope'], array('alle', 'egne'), true)) $p['scope'] = $_POST['scope'];
	if (isset($_POST['pp']) && in_array((int)$_POST['pp'], array(50, 100, 250, 500), true)) $p['pp'] = (int)$_POST['pp'];
	if (isset($_POST['col'], $_POST['on']) && array_key_exists($_POST['col'], $p['cols'])) $p['cols'][$_POST['col']] = $_POST['on'] === '1';
	if (!empty($_POST['reset'])) $p = $klDefaults;
	kl_save_prefs($p);
	kl_json(array('ok' => true, 'prefs' => $p));
}

if ($klAction === 'csv') {
	$req = $_POST;
	$rows = kl_rows($req, 50000, 0);
	while (ob_get_level()) ob_end_clean();
	$status = array('open' => kl_t('Ikke bogført', 'Not posted'), 'sim' => kl_t('Simuleret', 'Simulated'), 'post' => kl_t('Bogført', 'Posted'));
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="kassekladder.csv"');
	$out = fopen('php://output', 'w');
	fwrite($out, "\xEF\xBB\xBF");
	fputcsv($out, array(kl_t('Nr.', 'No.'), kl_t('Dato', 'Date'), kl_t('Ejer', 'Owner'), kl_t('Bemærkning', 'Note'), kl_t('Linjer', 'Lines'), 'Status', kl_t('Bogført', 'Posted'), kl_t('Bogført af', 'Posted by')), ';');
	foreach ($rows as $r) fputcsv($out, array($r['id'], $r['dato'], $r['ejer'], $r['note'], $r['linjer'], $status[$r['g']], $r['bogf'], $r['af']), ';');
	fclose($out);
	exit;
}

/* ---------- page ---------- */
$klPrefs = kl_prefs();
$returside = isset($_GET['returside']) ? nav_sanitize_returside($_GET['returside']) : null;
$klBack = (($menu ?? '') !== 'S') ? htmlspecialchars(nav_back_url($returside), ENT_QUOTES, $charset) : '';
$klNew = 'kassekladde.php?returside=kladdeliste.php&tjek=-1';
$btn = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)$buttonColor) ? $buttonColor : '#114691';
$btnTxt = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string)$buttonTxtColor) ? $buttonTxtColor : '#ffffff';
$klTexts = array(
	'tabs' => array('open' => kl_t('Åbne', 'Open'), 'sim' => kl_t('Simulerede', 'Simulated'), 'post' => kl_t('Bogførte', 'Posted'), 'all' => kl_t('Alle', 'All')),
	'cols' => array('nr' => kl_t('Nr.', 'No.'), 'note' => kl_t('Bemærkning', 'Note'), 'dato' => kl_t('Dato', 'Date'), 'ejer' => kl_t('Ejer', 'Owner'),
		'linjer' => kl_t('Linjer', 'Lines'), 'bogf' => kl_t('Bogført', 'Posted'), 'sim' => kl_t('Simuleret', 'Simulated'), 'status' => 'Status', 'af' => kl_t('Af', 'By'), 'afLong' => kl_t('Bogført af', 'Posted by')),
	'search' => kl_t('Søg', 'Search'),
	'dateHint' => kl_t('dd-mm-åååå, eller en periode som fra:til (f.eks. 01-09-2026:30-09-2026)', 'dd-mm-yyyy, or a period as from:to (e.g. 01-09-2026:30-09-2026)'),
	'inUse' => kl_t('I brug af', 'In use by'),
	'lockTip' => kl_t('Låsen udløber efter en times inaktivitet', 'The lock expires after an hour of inactivity'),
	'notPosted' => kl_t('Ikke bogført', 'Not posted'), 'simulated' => kl_t('Simuleret', 'Simulated'), 'posted' => kl_t('Bogført', 'Posted'),
	'deleteTip' => kl_t('Slet tom kladde', 'Delete empty journal'),
	'deleteQ' => kl_t('Slet kassekladde %s?', 'Delete cash journal %s?'),
	'deleteBody' => kl_t('Kladden er tom. Det kan ikke fortrydes.', 'The journal is empty. This cannot be undone.'),
	'cancel' => kl_t('Annullér', 'Cancel'), 'delete' => kl_t('Slet', 'Delete'),
	'deleted' => kl_t('Kassekladde %s er slettet', 'Cash journal %s has been deleted'),
	'delNotEmpty' => kl_t('Kladden har linjer og kan ikke slettes', 'The journal has lines and cannot be deleted'),
	'delPosted' => kl_t('Kladden er bogført og kan ikke slettes', 'The journal is posted and cannot be deleted'),
	'delNotFound' => kl_t('Kladden findes ikke længere', 'The journal no longer exists'),
	'of' => kl_t('af', 'of'), 'journal' => kl_t('kladde', 'journal'), 'journals' => kl_t('kladder', 'journals'),
	'onlyMine' => kl_t('kun dine kladder', 'only your journals'),
	'noMatch' => kl_t('Ingen %s kladder matcher', 'No %s journals match'),
	'seeIn' => kl_t('Se %s i %s →', 'See %s in %s →'),
	'clearAll' => kl_t('Ryd søgning', 'Clear search'),
	'noneMine' => kl_t('Du har ingen %s kladder', 'You have no %s journals'),
	'mineHint' => kl_t('Du ser kun de kladder, du selv har oprettet.', 'You only see the journals you created yourself.'),
	'showAll' => kl_t('Vis alle kladder', 'Show all journals'),
	'noneOpen' => kl_t('Ingen åbne kladder', 'No open journals'), 'allPosted' => kl_t('Alt er bogført.', 'Everything is posted.'),
	'createNew' => kl_t('Opret en ny kassekladde', 'Create a new cash journal'),
	'none' => kl_t('Ingen %s kladder', 'No %s journals'),
	'perPage' => kl_t('%s pr. side', '%s per page'),
	'prev' => kl_t('Forrige side', 'Previous page'), 'next' => kl_t('Næste side', 'Next page'),
	'loadError' => kl_t('Listen kunne ikke hentes. Prøv igen.', 'The list could not be loaded. Please try again.'),
	'listTitle' => kl_t('Kassekladder', 'Cash journals'),
	'hiddenNarrow' => kl_t('Skjult, fordi der ikke er plads', 'Hidden because there is no room'),
	'always' => kl_t('Vises altid', 'Always shown'),
	'resetDone' => kl_t('Visningen er nulstillet', 'The view has been reset'),
);
$klConfig = array(
	'texts' => $klTexts,
	'lang' => $sprog_id == 2 ? 'en' : 'da',
	'prefs' => $klPrefs,
	'open' => 'kassekladde.php?tjek=%d&kladde_id=%d',
);
?>
<link rel="stylesheet" href="../css/saldi-theme.css">
<link rel="stylesheet" href="../css/kladdeliste.css?v=20261022b">
<style>.kl-page{--btn:<?= $btn ?>;--btn-text:<?= $btnTxt ?>}</style>
<div class="kl-page">
<section class="kl" id="kl">
	<div class="kl-head">
		<?php if ($klBack !== '') { ?>
		<a class="kl-ghost kl-back" href="<?= $klBack ?>" accesskey="L" title="<?= kl_h(kl_t('Luk (Alt+L)', 'Close (Alt+L)')) ?>"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg><span><?= kl_h(kl_t('Luk', 'Close')) ?></span></a>
		<?php } ?>
		<div class="kl-tabs" role="tablist" aria-label="<?= kl_h(kl_t('Kassekladder', 'Cash journals')) ?>" id="klTabs"></div>
		<div class="kl-tools">
			<span class="kl-bank"><?php include(__DIR__ . '/../bank_integration/includes/auth_check_icon.php'); ?></span>
			<button type="button" class="kl-ghost" id="klClear" hidden title="<?= kl_h(kl_t('Ryd alle søgefelter', 'Clear all search fields')) ?>"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg><span class="kl-lbl"><?= kl_h(kl_t('Ryd søgning', 'Clear search')) ?></span></button>
			<div class="kl-scope" id="klScope" role="radiogroup" aria-label="<?= kl_h(kl_t('Hvis kladder', 'Whose journals')) ?>">
				<button type="button" role="radio" data-scope="alle" title="<?= kl_h(kl_t('Alle kladder i regnskabet', 'All journals in the accounts')) ?>"><?= kl_h(kl_t('Alle', 'All')) ?><span class="kl-lbl">&nbsp;<?= kl_h(kl_t('kladder', 'journals')) ?></span></button>
				<button type="button" role="radio" data-scope="egne" title="<?= kl_h(kl_t('Kun kladder, du selv har oprettet. Valget huskes til næste gang.', 'Only journals you created yourself. The choice is remembered.')) ?>"><?= kl_h(kl_t('Mine', 'Mine')) ?><span class="kl-lbl">&nbsp;<?= kl_h(kl_t('kladder', 'journals')) ?></span></button>
			</div>
			<button type="button" class="kl-ghost kl-icon" id="tutorial-help" title="<?= kl_h(kl_t('Hjælp', 'Help')) ?>" aria-label="<?= kl_h(kl_t('Hjælp', 'Help')) ?>"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.7M12 17h.01"/></svg></button>
			<span class="kl-vsep"></span>
			<a class="kl-newbtn" id="create-new" href="<?= $klNew ?>" accesskey="N" title="<?= kl_h(kl_t('Opret ny kassekladde (N)', 'Create new cash journal (N)')) ?>"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg><?= kl_h(kl_t('Ny', 'New')) ?><span class="kl-long">&nbsp;<?= kl_h(kl_t('kassekladde', 'cash journal')) ?></span></a>
			<div class="kl-cols">
				<button type="button" class="kl-cols-btn" id="klGear" title="<?= kl_h(kl_t('Tilpas listen', 'Customise the list')) ?>" aria-label="<?= kl_h(kl_t('Tilpas listen', 'Customise the list')) ?>" aria-haspopup="true" aria-expanded="false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg></button>
				<div class="kl-panel" id="klPanel" role="dialog" aria-label="<?= kl_h(kl_t('Tilpas listen', 'Customise the list')) ?>">
					<div class="kl-ph"><?= kl_h(kl_t('Tilpas listen', 'Customise the list')) ?></div>
					<div class="kl-pi"><?= kl_h(kl_t('Gælder kun for dig.', 'Only applies to you.')) ?></div>
					<h4><?= kl_h(kl_t('Kolonner', 'Columns')) ?></h4><div id="klPCols"></div>
					<h4><?= kl_h(kl_t('Eksportér', 'Export')) ?></h4>
					<button type="button" class="kl-sw" data-exp="csv"><span><?= kl_h(kl_t('Eksportér til CSV', 'Export to CSV')) ?><small class="kl-swn"><?= kl_h(kl_t('Alle kladder i fanen, med søgning', 'All journals in the tab, with the search')) ?></small></span></button>
					<button type="button" class="kl-sw" data-exp="pdf"><span><?= kl_h(kl_t('Udskriv / gem som PDF', 'Print / save as PDF')) ?><small class="kl-swn"><?= kl_h(kl_t('Den side, du ser', 'The page you are looking at')) ?></small></span></button>
					<div class="kl-pfoot"><button type="button" id="klReset"><?= kl_h(kl_t('Nulstil visning', 'Reset view')) ?></button></div>
				</div>
			</div>
		</div>
	</div>

	<div class="kl-tbl" id="klTbl">
		<div class="kl-row kl-thead" id="klThead"></div>
		<div class="kl-row kl-frow" id="klFrow" role="search" aria-label="<?= kl_h(kl_t('Søg i kolonner', 'Search in columns')) ?>"></div>
		<div id="klBody" role="grid" aria-label="<?= kl_h(kl_t('Kassekladder', 'Cash journals')) ?>"></div>
	</div>

	<div class="kl-foot">
		<span class="kl-num" id="klCount"></span>
		<span class="kl-grow"></span>
		<select id="klPP" aria-label="<?= kl_h(kl_t('Linjer pr. side', 'Lines per page')) ?>"></select>
		<div class="kl-pager" id="klPager"></div>
	</div>
</section>

<div class="kl-scrim" id="klScrim"><div class="kl-dlg" role="dialog" aria-modal="true" aria-labelledby="klDlgT" id="klDlg"></div></div>
<div class="kl-toast" id="klToast" role="status" aria-live="polite"></div>
</div>

<script>window.KL = <?= json_encode($klConfig, JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG) ?>;</script>
<script src="../javascript/kladdeliste.js?v=20261021"></script>
<?php
// Tutorial: same steps as before; the Ny button keeps the id the step points at
$steps = array();
$steps[] = array(
	"selector" => "#create-new",
	"content" => findtekst('2607|Opret ny kassekladde ved at klikke her', $sprog_id) . ".",
);
include(__DIR__ . "/../includes/tutorial.php");
create_tutorial("kladlist", $steps);
