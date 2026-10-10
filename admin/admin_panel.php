<?php
ob_start();
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- admin/admin_panel.php --- 2026-03-05 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
//
// Copyright (c) 2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// Admin Panel - Comprehensive admin page for managing customer accounts,
// feature licenses, usage stats, and account settings.
// 20261010 CL/ASR Rebuilt as Saldi's own administration panel in the new shell (admin/vr_ui.php): customer list with
//                  tabs (Normale kunder / Bogholdere/revisorer / Koncernregnskaber / Alle), customer card with
//                  Oversigt, Abonnement (includes/entitlements.php, replaces the three licence flags), Brugere,
//                  Betalinger (Saldi API, unchanged), Indstillinger, Bogholdere and Log. Operators only.
//                  Backend (API helpers, POST actions, ajax endpoints) kept as before; license_features bulk
//                  update stays reachable under Indstillinger until the entitlement layer replaces it (E0).

@session_start();
$s_id = session_id();

$modulnr = 104; // Admin module
$css = "../css/standard.css";
$title = "Admin Panel";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
include("../includes/partnerScope.php");
include("../includes/entitlements.php");
include("vr_ui.php");
include("inc_abonnement.php");
include("inc_forbrug.php");
include("inc_betalinger.php");
partner_tables_ensure();
ent_tables_ensure();

// ---- Ensure lukkes_kommentar column exists on regnskab ----
$_col_check = db_fetch_array(db_select("SELECT column_name FROM information_schema.columns WHERE table_name='regnskab' AND column_name='lukkes_kommentar' AND table_schema='public'", __FILE__ . " linje " . __LINE__));
if (!$_col_check) {
    db_modify("ALTER TABLE regnskab ADD COLUMN lukkes_kommentar text", __FILE__ . " linje " . __LINE__);
}

// ---- REST API Configuration for ssl3.saldi.dk ----
$query = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name = 'saldi_api_user' AND var_grp = 'internal_api'", __FILE__ . " linje " . __LINE__));
$saldi_api_user = $query['var_value'];
$query = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name = 'saldi_api_pass' AND var_grp = 'internal_api'", __FILE__ . " linje " . __LINE__));
$saldi_api_pass = $query['var_value'];
$query = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name = 'saldi_api_account' AND var_grp = 'internal_api'", __FILE__ . " linje " . __LINE__));
$saldi_api_account = $query['var_value'];
define('SALDI_API_BASE', 'https://ssl3.saldi.dk/finans/restapi/endpoints/v1');
define('SALDI_API_USER', $saldi_api_user);
define('SALDI_API_PASS', $saldi_api_pass);
define('SALDI_API_ACCOUNT', $saldi_api_account);
define('SALDI_API_TOKEN_FILE', '/tmp/saldi_api_token_admin.json');

/**
 * Get a cached or fresh JWT token from the Saldi REST API
 */
function get_saldi_api_token() {
    // Check for cached token
    if (file_exists(SALDI_API_TOKEN_FILE)) {
        $cached = json_decode(file_get_contents(SALDI_API_TOKEN_FILE), true);
        if ($cached && isset($cached['token']) && isset($cached['expires']) && $cached['expires'] > time()) {
            return $cached['token'];
        }
    }
    
    $url = SALDI_API_BASE . '/auth/login.php';
    $postData = json_encode([
        'username' => SALDI_API_USER,
        'password' => SALDI_API_PASS,
        'account_name' => SALDI_API_ACCOUNT
    ]);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ]);
    
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if (!$response || $httpCode !== 200) return null;
    
    $data = json_decode($response, true);
    if (!$data || !$data['success'] || !isset($data['data']['access_token'])) return null;
    
    $token = $data['data']['access_token'];
    
    // Cache token (expires in 55 min to be safe)
    file_put_contents(SALDI_API_TOKEN_FILE, json_encode([
        'token' => $token,
        'expires' => time() + 3300
    ]));
    
    return $token;
}

/**
 * Fetch data from the Saldi REST API
 */
function fetch_saldi_api($endpoint, $token, $params = []) {
    $url = SALDI_API_BASE . $endpoint;
    if ($params) $url .= '?' . http_build_query($params);
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) return null;
    
    $data = json_decode($response, true);
    if (!$data || !$data['success']) return null;
    
    return $data['data'];
}

/**
 * Fetch customer invoices from the Saldi API by searching for matching customer name
 */
function fetch_customer_invoices($search_term) {
    $token = get_saldi_api_token();
    if (!$token) return ['error' => 'Kunne ikke logge ind på Saldi API'];
    
    // First, search for the customer
    $customers = fetch_saldi_api('/debitor/customers/index.php', $token, [
        'search' => $search_term,
        'limit' => 1
    ]);
    
    if ($customers === null) {
        return ['error' => 'Kunne ikke hente kunde fra API'];
    }
    
    if (!is_array($customers) || count($customers) === 0 || !isset($customers[0]['kontonr'])) {
        return ['error' => 'Ingen kunde fundet for "' . htmlspecialchars($search_term) . '"'];
    }
    
    $customer_id = $customers[0]['kontonr'];
    
    // Fetch recent invoices for this customer
    $invoices = fetch_saldi_api('/debitor/invoices/index.php', $token, [
        'customer' => $customer_id,
        'limit' => 50,
        'page' => 1
    ]);
    
    if ($invoices === null) return ['error' => 'Kunne ikke hente fakturaer fra API'];
    if (!is_array($invoices) || count($invoices) === 0) return ['error' => 'Ingen fakturaer fundet for "' . htmlspecialchars($search_term) . '"'];
    
    // Sort by invoiceDate DESC
    usort($invoices, function($a, $b) {
        return strcmp($b['invoiceDate'] ?? '', $a['invoiceDate'] ?? '');
    });
    
    return ['invoices' => $invoices];
}

// Security check
if ($db != $sqdb) {
    print "<BODY onLoad=\"javascript:alert('Hmm du har vist ikke noget at gøre her!')\">"; 
    print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">";
    exit;
}

$vr_me = partner_current_user();
if (!$vr_me['is_operator']) { print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/admin_menu.php\">"; exit; }

// Available features for license management
$available_features = array(
    'booking' => 'Booking / Udlejning',
    'lager'   => 'Lager (Varer)',
    'kreditor' => 'Kreditor'
);

$message = '';
$message_type = 'success';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = if_isset($_POST['action'], '');
    
    // --- License bulk update ---
    if ($action === 'bulk_update') {
        $regnskab_id = (int)$_POST['regnskab_id'];
        
        foreach ($available_features as $feature_key => $feature_name) {
            $enabled = isset($_POST['feature_' . $feature_key]) ? 'true' : 'false';
            $expires_at = $_POST['expires_' . $feature_key] ? "'" . db_escape_string($_POST['expires_' . $feature_key]) . "'" : 'NULL';
            
            $qtxt = "SELECT id FROM license_features WHERE regnskab_id = $regnskab_id AND feature_key = '$feature_key'";
            $existing = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
            
            if ($existing) {
                $qtxt = "UPDATE license_features SET enabled = $enabled, expires_at = $expires_at, updated_at = NOW() 
                         WHERE regnskab_id = $regnskab_id AND feature_key = '$feature_key'";
            } else {
                $qtxt = "INSERT INTO license_features (regnskab_id, feature_key, enabled, expires_at) 
                         VALUES ($regnskab_id, '$feature_key', $enabled, $expires_at)";
            }
            db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        }
        
        $message = "Licenser opdateret!";
        partner_log('license.updated', $regnskab_id, null, 'license_features via adminpanel');
    }
    
    // --- Account settings update ---
    if ($action === 'update_settings') {
        $regnskab_id = (int)$_POST['regnskab_id'];
        $brugerantal = (int)$_POST['brugerantal'];
        $posteringer = (int)$_POST['posteringer'];
        $lukket = isset($_POST['lukket']) ? 'on' : '';
        $betalt_til = $_POST['betalt_til'] ? "'" . db_escape_string($_POST['betalt_til']) . "'" : "'2099-12-31'";
        $logintekst = db_escape_string(if_isset($_POST['logintekst'], ''));
        $lukkes_post = trim(if_isset($_POST['lukkes'], ''));
        $lukkes_sql = ($lukkes_post && $lukkes_post !== '') ? "'" . db_escape_string($lukkes_post) . "'" : "NULL";
        $lukkes_kommentar = db_escape_string(if_isset($_POST['lukkes_kommentar'], ''));

        $qtxt = "UPDATE regnskab SET brugerantal='$brugerantal', posteringer='$posteringer', lukket='$lukket',
                 betalt_til=$betalt_til, logintekst='$logintekst',
                 lukkes=$lukkes_sql, lukkes_kommentar='$lukkes_kommentar' WHERE id = $regnskab_id";
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);

        $message = "Indstillinger opdateret!";
        partner_log('ledger.settings_updated', $regnskab_id, null, "brugerantal=$brugerantal posteringer=$posteringer lukket='$lukket' betalt_til=$betalt_til lukkes=$lukkes_sql");
    }
}

// Handle AJAX License toggle
if (isset($_GET['ajax_license_toggle']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $reg_id = (int)($input['regnskab_id'] ?? 0);
    $feature = if_isset($input['feature_key'], '');
    
    if (!$reg_id || !$feature) {
        echo json_encode(['error' => 'Mangler parametre']);
        exit;
    }
    
    $qtxt = "SELECT id, enabled FROM license_features WHERE regnskab_id = $reg_id AND feature_key = '" . db_escape_string($feature) . "'";
    $existing = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
    
    if ($existing) {
        $is_on = ($existing['enabled'] && $existing['enabled'] != 'f' && $existing['enabled'] != '0');
        $new_state = $is_on ? 'false' : 'true';
        $qtxt = "UPDATE license_features SET enabled = $new_state, updated_at = NOW() WHERE id = " . $existing['id'];
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        echo json_encode(['success' => true, 'new_state' => $new_state === 'true']);
    } else {
        $qtxt = "INSERT INTO license_features (regnskab_id, feature_key, enabled) VALUES ($reg_id, '" . db_escape_string($feature) . "', false)";
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        echo json_encode(['success' => true, 'new_state' => false]);
    }
    exit;
}

// Handle AJAX Invoice fetch
if (isset($_GET['ajax_invoice_id'])) {
    while (ob_get_level()) { ob_end_clean(); } // Clean ANY previous output (notices etc)
    header('Content-Type: application/json');
    $invoice_id = (int)$_GET['ajax_invoice_id'];
    $token = get_saldi_api_token();
    if (!$token) {
        echo json_encode(['error' => 'Kunne ikke logge ind på Saldi API']);
        exit;
    }
    
    // Fetch single invoice by passing 'id' as param
    $invoice_details = fetch_saldi_api('/debitor/invoices/index.php', $token, ['id' => $invoice_id]);
    
    if ($invoice_details === null) {
        echo json_encode(['error' => 'Invoice not fundet fra API']);
        exit;
    }
    
    echo json_encode(['invoice' => $invoice_details]);
    exit;
}

// Handle AJAX User operations
if (isset($_GET['ajax_users']) && isset($_GET['regnskab_id'])) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    $reg_id = (int)$_GET['regnskab_id'];
    
    // Get client DB name
    $qtxt = "SELECT db FROM regnskab WHERE id = $reg_id";
    $reg_row = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
    if (!$reg_row || !$reg_row['db']) {
        echo json_encode(['error' => 'Regnskab ikke fundet']);
        exit;
    }
    $client_db = $reg_row['db'];
    $client_conn = @db_connect($sqhost, $squser, $sqpass, $client_db, __FILE__ . " linje " . __LINE__);
    if (!$client_conn) {
        echo json_encode(['error' => 'Kunne ikke forbinde til klient-database: ' . $client_db]);
        exit;
    }
    
    $user_action = if_isset($_GET['user_action'], 'list');
    
    if ($user_action === 'list') {
        $users = [];
        $q = db_select("SELECT id, brugernavn, rettigheder, ansat_id, ip_address, tlf, email, twofactor FROM brugere ORDER BY brugernavn", __FILE__ . " linje " . __LINE__);
        while ($r = db_fetch_array($q)) {
            $r['twofactor'] = ($r['twofactor'] === 't' || $r['twofactor'] === true || $r['twofactor'] === '1') ? true : false;
            $users[] = $r;
        }
        // Reconnect master
        include("../includes/connect.php");
        echo json_encode(['users' => $users]);
        exit;
    }
    
    if ($user_action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $user_id = (int)($input['user_id'] ?? 0);
        if (!$user_id) { echo json_encode(['error' => 'Mangler bruger-ID']); exit; }
        
        $sets = [];
        if (isset($input['password']) && $input['password'] !== '' && strpos($input['password'], '****') === false) {
            $encrypted = saldikrypt($user_id, $input['password']);
            $sets[] = "kode='" . db_escape_string($encrypted) . "'";
        }
        if (isset($input['email'])) {
            $sets[] = "email='" . db_escape_string(trim($input['email'])) . "'";
        }
        if (isset($input['tlf'])) {
            $sets[] = "tlf='" . db_escape_string(trim($input['tlf'])) . "'";
        }
        if (isset($input['twofactor'])) {
            $tf = $input['twofactor'] ? 't' : 'f';
            $sets[] = "twofactor='$tf'";
        }
        if (isset($input['ip_address'])) {
            $sets[] = "ip_address='" . db_escape_string(trim($input['ip_address'])) . "'";
        }
        
        if (count($sets) > 0) {
            $qtxt = "UPDATE brugere SET " . implode(', ', $sets) . " WHERE id = $user_id";
            db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        }
        // Reconnect master
        include("../includes/connect.php");
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($user_action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $user_id = (int)($input['user_id'] ?? 0);
        if (!$user_id) { echo json_encode(['error' => 'Mangler bruger-ID']); exit; }
        
        // Check if user has ansat_id, close the employee
        $r = db_fetch_array(db_select("SELECT ansat_id FROM brugere WHERE id = $user_id", __FILE__ . " linje " . __LINE__));
        if ($r && $r['ansat_id']) {
            db_modify("UPDATE ansatte SET lukket='on', slutdate='" . date('Y-m-d') . "' WHERE id = " . (int)$r['ansat_id'], __FILE__ . " linje " . __LINE__);
        }
        db_modify("DELETE FROM brugere WHERE id = $user_id", __FILE__ . " linje " . __LINE__);
        
        // Reconnect master
        include("../includes/connect.php");
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($user_action === 'clear_datatables' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $user_id = (int)($input['user_id'] ?? 0);
        if (!$user_id) { echo json_encode(['error' => 'Mangler bruger-ID']); exit; }
        
        db_modify("DELETE FROM datatables WHERE user_id = $user_id", __FILE__ . " linje " . __LINE__);
        
        // Reconnect master
        include("../includes/connect.php");
        echo json_encode(['success' => true]);
        exit;
    }
    
    echo json_encode(['error' => 'Ukendt handling']);
    exit;
}


// ============================================================
// VIEW
// ============================================================
$filter_regnskab = (int)if_isset($_GET['regnskab_id'], 0);
$tab    = if_isset($_GET, 'oversigt', 'tab');
$ltab   = if_isset($_GET, 'normale', 'ltab');
$search = trim((string)if_isset($_GET, '', 'search'));
$showClosed = if_isset($_GET, '', 'showClosed');
$notes = array(); if ($message) $notes[] = $message;

if ($filter_regnskab) {
	// ---------------- customer card ----------------
	$rid = $filter_regnskab;
	$reg = db_fetch_array(db_select("SELECT * FROM regnskab WHERE id = $rid", __FILE__ . " linje " . __LINE__));
	if (!$reg) { vr_open(array(array('Administrationspanel', 'admin_panel.php'), 'Kunde'), vr_t('Regnskabet findes ikke','Account not found'), ''); vr_close(); print "</body></html>"; exit; }
	// Auto-close if the scheduled date has passed (as before)
	if ($reg['lukket'] != 'on' && !empty($reg['lukkes']) && $reg['lukkes'] !== '2099-12-31' && strtotime($reg['lukkes']) <= strtotime(date('Y-m-d'))) {
		db_modify("UPDATE regnskab SET lukket='on' WHERE id=" . (int)$reg['id'], __FILE__ . " linje " . __LINE__);
		db_modify("DELETE FROM online WHERE db='" . db_escape_string($reg['db']) . "'", __FILE__ . " linje " . __LINE__);
		$reg['lukket'] = 'on'; partner_log('ledger.auto_closed', $rid, null, 'lukkes '.$reg['lukkes']);
	}
	ent_tab_actions($rid, $reg, $notes);
	ent_forbrug_actions($rid, $notes);
	bill_actions($rid, $notes);
	// partner links (operator)
	if (isset($_POST['vr_action'])) {
		if ($_POST['vr_action'] == 'link') {
			$pid = (int)if_isset($_POST, 0, 'partner_id'); $access = in_array(if_isset($_POST, 'full', 'access'), array('full','readonly','owner'), true) ? $_POST['access'] : 'full';
			$p = db_fetch_array(db_select("select * from partners where id = '$pid' and status = 'active'", __FILE__ . " linje " . __LINE__));
			if (!$p) $notes[] = vr_t('Partneren findes ikke','Partner not found');
			elseif (db_fetch_array(db_select("select 1 from regnskab_partners where regnskab_id = '$rid' and partner_id = '$pid' and until is null", __FILE__ . " linje " . __LINE__))) $notes[] = vr_t('Partneren har allerede adgang','The partner already has access');
			elseif ($access == 'owner' && db_fetch_array(db_select("select 1 from regnskab_partners where regnskab_id = '$rid' and access = 'owner' and until is null", __FILE__ . " linje " . __LINE__))) $notes[] = vr_t('Regnskabet har allerede en ejer','The account already has an owner');
			else { db_modify("insert into regnskab_partners (regnskab_id, partner_id, access, created_by, note) values ('$rid', '$pid', '$access', '".(int)$vr_me['id']."', '".db_escape_string(trim(if_isset($_POST, '', 'note')))."')", __FILE__ . " linje " . __LINE__); partner_log('partner.link_added', $rid, $pid, $p['name'].' ('.$access.')'); $notes[] = vr_h($p['name']).' '.vr_t('har nu adgang','now has access'); }
		} elseif ($_POST['vr_action'] == 'unlink') {
			$lid = (int)if_isset($_POST, 0, 'link_id');
			$l = db_fetch_array(db_select("select rp.*, p.name from regnskab_partners rp join partners p on p.id = rp.partner_id where rp.id = '$lid' and rp.regnskab_id = '$rid' and rp.until is null", __FILE__ . " linje " . __LINE__));
			if ($l) { db_modify("update regnskab_partners set until = now() where id = '$lid'", __FILE__ . " linje " . __LINE__); partner_log('partner.link_removed', $rid, (int)$l['partner_id'], $l['name']); $notes[] = vr_h($l['name']).' '.vr_t('har ikke længere adgang','no longer has access'); }
		}
	}
	// live data from the customer database
	$live = array('ok' => false, 'cvr' => '', 'firma' => '', 'users' => 0, 'trans12' => null, 'last' => null, 'items' => null, 'online' => 0);
	$time_limit = time() - 1200; $qr = db_fetch_array(db_select("SELECT COUNT(DISTINCT session_id) as cnt FROM online WHERE db = '".db_escape_string($reg['db'])."' AND logtime >= '$time_limit'", __FILE__ . " linje " . __LINE__)); $live['online'] = (int)$qr['cnt'];
	if ($reg['db'] && $reg['db'] != $sqdb && db_exists($reg['db']) && @db_connect($sqhost, $squser, $sqpass, $reg['db'], __FILE__ . " linje " . __LINE__)) {
		$live['ok'] = true;
		if ($r = db_fetch_array(db_select("select firmanavn, cvrnr from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__))) { $live['firma'] = $r['firmanavn']; $live['cvr'] = str_replace(' ', '', preg_replace('/^DK\s*/i', '', trim((string)$r['cvrnr']))); }
		if (tbl_exists('brugere')) { $cols = array(); $q = db_select("select column_name from information_schema.columns where table_name = 'brugere'", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $cols[] = $r['column_name']; $r = db_fetch_array(db_select("select count(*) as n from brugere".(in_array('revisor', $cols) ? " where revisor is not true" : ""), __FILE__ . " linje " . __LINE__)); $live['users'] = (int)$r['n']; }
		if (tbl_exists('transaktioner')) { $r = db_fetch_array(db_select("select count(id) as n, max(logdate) as l from transaktioner where logdate >= '".date('Y-m-d', strtotime('-1 year'))."'", __FILE__ . " linje " . __LINE__)); $live['trans12'] = (int)$r['n']; $live['last'] = $r['l']; }
		if (tbl_exists('varer')) { $r = db_fetch_array(db_select("select count(id) as n from varer", __FILE__ . " linje " . __LINE__)); $live['items'] = (int)$r['n']; }
		include("../includes/connect.php");
	}
	$ent = ent_resolve($rid); $cat = ent_catalog();
	$links = array(); $q = db_select("select rp.*, p.name, p.kind from regnskab_partners rp join partners p on p.id = rp.partner_id where rp.regnskab_id = '$rid' and rp.until is null order by p.name", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $links[] = $r;
	$home = db_fetch_array(db_select("select * from partners where home_regnskab_id = '$rid' and status = 'active'", __FILE__ . " linje " . __LINE__));
	$isClosed = ($reg['lukket'] == 'on');
	if (!in_array($tab, array('oversigt','abonnement','forbrug','brugere','betalinger','indstillinger','log'), true)) $tab = 'oversigt';

	$lead = '<span class="vr-st"><span class="vr-dot '.($isClosed ? 'vr-off' : 'vr-ok').'"></span>'.($isClosed ? findtekst('387|Lukket', $sprog_id) : vr_t('Aktiv','Active')).'</span> · '.vr_h($reg['db']).' · ID '.$rid.' · '.($ent['plan'] ? vr_h($ent['plan']['name']) : vr_t('ingen pakke','no plan')).($links ? ' · '.vr_t('Bogholder','Accountant').': '.implode(', ', array_map(fn($l) => vr_h($l['name']), $links)) : ($home ? ' · '.vr_t('partnerens eget regnskab','the partner\'s own account') : ' · '.vr_t('direkte kunde','direct customer')));
	vr_open(array(array('Administrationspanel', 'admin_panel.php'), array(vr_t('Kunder','Customers'), 'admin_panel.php'), $reg['regnskab']), $reg['regnskab'], $lead, "<a class=\"vr-btn vr-primary\" href=\"aaben_regnskab.php?db_id=$rid\">".vr_t('Åbn regnskab','Open account')."</a>", $home ? strtolower(vr_kind($home['kind'])) : '');
	vr_note($notes);
	$tu = fn($t) => "admin_panel.php?regnskab_id=$rid&tab=$t";
	vr_tabs(array(
		'oversigt' => array(vr_t('Oversigt','Overview'), $tu('oversigt'), null),
		'abonnement' => array(vr_t('Abonnement','Subscription'), $tu('abonnement'), null),
		'forbrug' => array(vr_t('Forbrug','Usage'), $tu('forbrug'), null),
		'brugere' => array(vr_t('Brugere','Users'), $tu('brugere'), $live['ok'] ? $live['users'] : null),
		'betalinger' => array(vr_t('Betalinger','Payments'), $tu('betalinger'), null),
		'indstillinger' => array(vr_t('Indstillinger','Settings'), $tu('indstillinger'), null),
		'log' => array(vr_t('Log','Log'), $tu('log'), null),
	), $tab);

	if ($tab == 'oversigt') {
		$maxU = $ent['plan'] ? $ent['limits']['max_users'] : (int)$reg['brugerantal']; $incP = $ent['plan'] ? $ent['limits']['included_postings'] : (int)$reg['posteringer'];
		$pU = ($maxU && $live['ok']) ? min(100, (int)round($live['users'] / $maxU * 100)) : 0; $tr = $live['trans12'] !== null ? $live['trans12'] : (int)$reg['posteret']; $pP = $incP ? min(100, (int)round($tr / $incP * 100)) : 0;
		print "<div class=\"vr-grid2\">\n<section class=\"vr-sect\"><h2>".vr_t('Nøgletal','Key figures')."</h2><div class=\"vr-card vr-kv\">";
		print "<div><span>".vr_t('Brugere','Users')."</span><b>".($live['ok'] ? $live['users'] : '?')." ".vr_t('af','of')." ".($maxU === null ? vr_t('ubegrænset','unlimited') : $maxU)." <span class=\"vr-b\" style=\"margin-left:8px\"><i".($pU >= 90 ? " class=\"vr-hi\"" : "")." style=\"width:$pU%\"></i></span></b></div>";
		print "<div><span>".vr_t('Online nu','Online now')."</span><b>".$live['online']."</b></div>";
		print "<div><span>".vr_t('Posteringer 12 mdr.','Entries 12 m')."</span><b>".vr_num($tr)." ".vr_t('af','of')." ".($incP === null ? vr_t('ubegrænset','unlimited') : vr_num($incP))." <span class=\"vr-b\" style=\"margin-left:8px\"><i".($pP >= 90 ? " class=\"vr-hi\"" : "")." style=\"width:$pP%\"></i></span></b></div>";
		print "<div><span>".vr_t('Varer','Items')."</span><b>".($live['items'] !== null ? vr_num($live['items']) : '–')."</b></div>";
		print "<div><span>".vr_t('Sidst aktiv','Last active')."</span><b>".($live['last'] ? vr_rel($live['last']).' <span class="vr-mut2">'.vr_h(substr($live['last'],0,10)).'</span>' : vr_rel($reg['sidst']))."</b></div>";
		print "<div><span>".vr_t('Pakke','Plan')."</span><b>".($ent['plan'] ? vr_h($ent['plan']['name']).($ent['price_ore'] !== null ? ' · '.ent_kr($ent['price_ore']).'/md.' : '') : '<span class="vr-mut2">'.vr_t('ingen pakke','no plan').'</span>')." <a class=\"vr-card-link\" href=\"".$tu('abonnement')."\">".vr_t('Abonnement','Subscription')."</a></b></div>";
		print "</div></section>\n<section class=\"vr-sect\"><h2>".vr_t('Kontoinformation','Account information')."</h2><div class=\"vr-card vr-kv\">";
		foreach (array(array(vr_t('Firma i regnskabet','Company'), $live['firma'] ? vr_h($live['firma']) : '–'), array('CVR', $live['cvr'] ? vr_h($live['cvr']) : '–'), array(vr_t('Database','Database'), vr_h($reg['db'])), array('E-mail', $reg['email'] ? vr_h($reg['email']) : '–'), array(vr_t('Betalt til','Paid until'), $reg['betalt_til'] && $reg['betalt_til'] != '2099-12-31' ? date('d-m-Y', strtotime($reg['betalt_til'])) : '–'), array(vr_t('Lukkes','Closes'), $reg['lukkes'] && $reg['lukkes'] != '2099-12-31' ? date('d-m-Y', strtotime($reg['lukkes'])).' <span class="vr-mut2">('.(int)floor((strtotime($reg['lukkes']) - time())/86400).' '.vr_t('dage','days').')</span>' : '–'), array(vr_t('Logintekst','Login text'), $reg['logintekst'] ? vr_h($reg['logintekst']) : '–'), array(vr_t('Version','Version'), $reg['version'] ? vr_h($reg['version']) : '–')) as $row) print "<div><span>$row[0]</span><b>$row[1]</b></div>";
		print "</div>";
		print "<h2 style=\"margin-top:18px\">".vr_t('Bogholdere og koncern med adgang','Accountants and group with access')."</h2><div class=\"vr-card\">";
		if ($links) foreach ($links as $l) print "<div class=\"vr-frow\"><div><b><a href=\"partnere.php?id=".(int)$l['partner_id']."\">".vr_h($l['name'])."</a> <span class=\"vr-pill\">".vr_h(strtolower(vr_kind($l['kind'])))."</span></b><small>".($l['access']=='owner' ? vr_t('Ejer','Owner') : ($l['access']=='readonly' ? vr_t('Kun læsning','Read only') : vr_t('Fuld adgang','Full access')))." &middot; ".vr_t('siden','since')." ".vr_h(substr($l['since'],0,10))."</small></div><span></span><form method=\"post\" style=\"display:inline\"><input type=\"hidden\" name=\"vr_action\" value=\"unlink\"><input type=\"hidden\" name=\"link_id\" value=\"".(int)$l['id']."\"><button type=\"submit\" class=\"vr-link\" onclick=\"return confirm('".vr_t('Fjern adgangen?','Remove access?')."')\">".vr_t('Frigør','Unlink')."</button></form></div>";
		else print "<div class=\"vr-empty\"><b>".vr_t('Ingen bogholder','No accountant')."</b><span>".($home ? vr_t('Regnskabet er partnerens eget.','This is the partner\'s own account.') : vr_t('Regnskabet er en direkte kunde.','The account is a direct customer.'))."</span></div>";
		print "<div class=\"vr-foot\"><form method=\"post\" class=\"vr-inline\"><input type=\"hidden\" name=\"vr_action\" value=\"link\"><select name=\"partner_id\" class=\"vr-sel\" required><option value=\"\">".vr_t('Vælg bogholder eller koncern…','Choose accountant or group…')."</option>"; foreach (partner_all() as $pp) print "<option value=\"".(int)$pp['id']."\">".vr_h($pp['name'])." · ".vr_h(vr_kind($pp['kind']))."</option>"; print "</select><select name=\"access\" class=\"vr-sel\"><option value=\"full\">".vr_t('Fuld adgang','Full access')."</option><option value=\"readonly\">".vr_t('Kun læsning','Read only')."</option><option value=\"owner\">".vr_t('Ejer (koncern)','Owner (group)')."</option></select><button type=\"submit\" class=\"vr-btn\">+ ".vr_t('Tilknyt','Link')."</button></form></div></div></section>\n</div>\n";
	} elseif ($tab == 'abonnement') {
		ent_tab_render($rid, $ent, $cat);
	} elseif ($tab == 'forbrug') {
		$usage = ent_usage_collect($reg); if ($usage['ok']) ent_snapshot_store($rid, $usage);
		ent_forbrug_render($rid, $reg, $ent, $cat, $usage);
	} elseif ($tab == 'brugere') {
		print "<section class=\"vr-sect\"><h2>".vr_t('Kundens brugere','Customer users')." <small>".($live['ok'] ? $live['users'].' '.vr_t('aktive','active') : '')."</small></h2><div class=\"vr-card\"><div class=\"vr-bar\"><label class=\"vr-search\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linecap=\"round\"><circle cx=\"11\" cy=\"11\" r=\"7\"/><path d=\"M20 20l-3.5-3.5\"/></svg><input type=\"search\" id=\"uq\" placeholder=\"".vr_t('Søg bruger','Search user')."\" oninput=\"filterUsers(this.value)\"></label><span class=\"vr-hint\">".vr_t('Klik på en bruger for at rette adgangskode, e-mail, telefon, IP og 2FA','Click a user to edit password, e-mail, phone, IP and 2FA')."</span></div><div id=\"usersBox\"><div class=\"vr-empty\"><span>".vr_t('Henter brugere…','Loading users…')."</span></div></div></div></section>\n";
	} elseif ($tab == 'betalinger') {
		$cfg = bill_get($rid);
		$search = $cfg['billing_kontonr'] ?: ($live['cvr'] ? $live['cvr'] : $reg['regnskab']);
		$data = bill_fetch($search);
		bill_render($rid, $reg, $ent, $live, $data, $cfg);
	} elseif ($tab == 'indstillinger') {
		$bt = $reg['betalt_til'] && $reg['betalt_til'] != '2099-12-31' ? date('Y-m-d', strtotime($reg['betalt_til'])) : ''; $lk = $reg['lukkes'] && $reg['lukkes'] != '2099-12-31' ? date('Y-m-d', strtotime($reg['lukkes'])) : '';
		print "<div class=\"vr-grid2\"><section class=\"vr-sect\"><h2>".vr_t('Grænser og lukning','Limits and closing')."</h2><div class=\"vr-card\"><form method=\"post\" action=\"admin_panel.php?regnskab_id=$rid&tab=indstillinger\" class=\"vr-form\"><input type=\"hidden\" name=\"action\" value=\"update_settings\"><input type=\"hidden\" name=\"regnskab_id\" value=\"$rid\">";
		print "<p class=\"vr-sub\">".vr_t('Disse to grænser er de gamle felter på regnskabet. Når pakkelaget håndhæver, styres de fra Abonnement.','These two limits are the legacy fields. Once the entitlement layer enforces, they are managed from Subscription.')."</p>";
		print "<label>".vr_t('Maks. brugere (gammelt felt)','Max users (legacy)')."<input class=\"vr-inp\" type=\"number\" name=\"brugerantal\" value=\"".(int)$reg['brugerantal']."\" min=\"0\"></label><label>".vr_t('Maks. posteringer (gammelt felt)','Max entries (legacy)')."<input class=\"vr-inp\" type=\"number\" name=\"posteringer\" value=\"".(int)$reg['posteringer']."\" min=\"0\"></label>";
		print "<label>".vr_t('Betalt til','Paid until')."<input class=\"vr-inp\" type=\"date\" name=\"betalt_til\" value=\"$bt\"></label><label>".vr_t('Logintekst (vises ved login)','Login text')."<input class=\"vr-inp\" name=\"logintekst\" value=\"".vr_h($reg['logintekst'])."\"></label>";
		print "<label class=\"vr-chk\"><input type=\"checkbox\" name=\"lukket\"".($isClosed ? " checked" : "")."> ".vr_t('Regnskabet er lukket','The account is closed')."</label><label>".vr_t('Lukkes automatisk den','Close automatically on')."<input class=\"vr-inp\" type=\"date\" name=\"lukkes\" value=\"$lk\"></label><label>".vr_t('Kommentar til lukning','Closing note')."<textarea class=\"vr-inp\" name=\"lukkes_kommentar\" style=\"height:70px;padding:8px 12px\">".vr_h($reg['lukkes_kommentar'] ?? '')."</textarea></label>";
		print "<div><button type=\"submit\" class=\"vr-btn vr-primary\">".findtekst('3|Gem', $sprog_id)."</button></div></form></div></section>";
		$lic = array(); $q = db_select("SELECT feature_key, enabled, expires_at FROM license_features WHERE regnskab_id = $rid", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $lic[$r['feature_key']] = $r;
		print "<section class=\"vr-sect\"><h2>".vr_t('Licenser (gamle flag)','Licences (legacy flags)')."</h2><div class=\"vr-card\"><form method=\"post\" action=\"admin_panel.php?regnskab_id=$rid&tab=indstillinger\" class=\"vr-form\"><input type=\"hidden\" name=\"action\" value=\"bulk_update\"><input type=\"hidden\" name=\"regnskab_id\" value=\"$rid\"><p class=\"vr-sub\">".vr_t('De tre flag, koden stadig læser (license_features). Afløses af Abonnement, når pakkelaget håndhæver.','The three flags the code still reads (license_features). Replaced by Subscription once the entitlement layer enforces.')."</p>";
		foreach ($available_features as $k => $n) { $on = !isset($lic[$k]) || ($lic[$k]['enabled'] === 't' || $lic[$k]['enabled'] === true); print "<div class=\"vr-inline\"><label class=\"vr-chk\" style=\"min-width:220px\"><input type=\"checkbox\" name=\"feature_$k\"".($on ? " checked" : "")."> ".vr_h($n)."</label><input class=\"vr-inp\" type=\"date\" name=\"expires_$k\" value=\"".(isset($lic[$k]) && $lic[$k]['expires_at'] ? date('Y-m-d', strtotime($lic[$k]['expires_at'])) : '')."\" title=\"".vr_t('Udløber','Expires')."\"></div>"; }
		print "<div><button type=\"submit\" class=\"vr-btn\">".findtekst('3|Gem', $sprog_id)."</button></div></form></div></section></div>\n";
	} else {
		print "<section class=\"vr-sect\"><h2>".vr_t('Log','Log')."</h2><div class=\"vr-card\"><table class=\"vr-t\"><thead><tr><th>".vr_t('Tidspunkt','Time')."</th><th>".vr_t('Hvem','Who')."</th><th>".vr_t('Hvad','What')."</th><th>".vr_t('Detaljer','Details')."</th></tr></thead><tbody>";
		$q = db_select("select l.*, b.brugernavn, p.name as pname from partner_log l left join brugere b on b.id = l.actor_bruger_id left join partners p on p.id = l.actor_partner_id where l.regnskab_id = '$rid' order by l.id desc limit 300", __FILE__ . " linje " . __LINE__); $n = 0;
		while ($r = db_fetch_array($q)) { $n++; print "<tr><td class=\"vr-mut vr-num\">".vr_h(substr($r['ts'],0,16))."</td><td>".vr_h($r['brugernavn'])." <span class=\"vr-mut2\">(".($r['pname'] ? vr_h($r['pname']) : 'Saldi').")</span></td><td>".vr_h($r['action'])."</td><td class=\"vr-mut\">".vr_h($r['details'])."</td></tr>"; }
		if (!$n) print "<tr><td colspan=\"4\" class=\"vr-mut\">".vr_t('Ingen hændelser endnu','No events yet')."</td></tr>";
		print "</tbody></table></div></section>\n";
	}
	vr_close();
	// ---- JS: users (same ajax endpoints as before) and invoice dialog ----
	print <<<JS
<script>
const REGNSKAB_ID = $rid;
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
let USERS=[];
function loadUsers(){const box=document.getElementById('usersBox');if(!box)return;fetch('admin_panel.php?ajax_users=1&regnskab_id='+REGNSKAB_ID+'&user_action=list').then(r=>r.json()).then(d=>{if(d.error){box.innerHTML='<div class="vr-empty"><b>'+esc(d.error)+'</b></div>';return;}USERS=d.users||[];renderUsers(USERS);}).catch(()=>{box.innerHTML='<div class="vr-empty"><b>Kunne ikke hente brugere</b></div>';});}
function renderUsers(list){const box=document.getElementById('usersBox');if(!list.length){box.innerHTML='<div class="vr-empty"><b>Ingen brugere</b></div>';return;}let h='<table class="vr-t"><thead><tr><th>Brugernavn</th><th>E-mail</th><th>Telefon</th><th>2FA</th><th>IP</th><th></th></tr></thead><tbody>';list.forEach(u=>{h+='<tr id="ur'+u.id+'" onclick="toggleUser('+u.id+')" style="cursor:pointer"><td class="vr-nm"><a>'+esc(u.brugernavn)+'</a></td><td class="vr-mut">'+esc(u.email||'–')+'</td><td class="vr-mut">'+esc(u.tlf||'–')+'</td><td>'+(u.twofactor?'<span class="vr-st"><span class="vr-dot vr-ok"></span>Ja</span>':'<span class="vr-mut2">–</span>')+'</td><td class="vr-mut">'+esc(u.ip_address||'–')+'</td><td class="vr-r"><span class="vr-link">Ret</span></td></tr>';h+='<tr id="ue'+u.id+'" hidden><td colspan="6" style="background:var(--surface-2)"><div class="vr-inline" style="padding:6px 0 10px;gap:10px"><input class="vr-inp" type="password" id="pw'+u.id+'" placeholder="Ny adgangskode (tom = uændret)" style="width:220px"><input class="vr-inp" id="em'+u.id+'" value="'+esc(u.email||'')+'" placeholder="E-mail (2FA)" style="width:220px"><input class="vr-inp" id="tl'+u.id+'" value="'+esc(u.tlf||'')+'" placeholder="Telefon (2FA)" style="width:150px"><input class="vr-inp" id="ip'+u.id+'" value="'+esc(u.ip_address||'')+'" placeholder="Tilladte IP-adresser" style="width:200px"><label class="vr-chk" style="display:inline-flex;gap:6px;font-size:13px"><input type="checkbox" id="tf'+u.id+'" '+(u.twofactor?'checked':'')+'> 2FA</label><button type="button" class="vr-btn vr-primary" onclick="event.stopPropagation();saveUser('+u.id+')">Gem</button><button type="button" class="vr-btn vr-quiet" onclick="event.stopPropagation();clearDT('+u.id+',\\''+esc(u.brugernavn).replace(/'/g,"\\\\'")+'\\')">Nulstil tabeller</button><button type="button" class="vr-btn vr-danger" onclick="event.stopPropagation();delUser('+u.id+',\\''+esc(u.brugernavn).replace(/'/g,"\\\\'")+'\\')">Slet bruger</button></div></td></tr>';});box.innerHTML=h+'</tbody></table>';}
function toggleUser(id){const e=document.getElementById('ue'+id);if(e)e.hidden=!e.hidden;}
function filterUsers(q){q=(q||'').toLowerCase();renderUsers(USERS.filter(u=>((u.brugernavn||'')+' '+(u.email||'')).toLowerCase().includes(q)));}
function post(action,body){return fetch('admin_panel.php?ajax_users=1&regnskab_id='+REGNSKAB_ID+'&user_action='+action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}).then(r=>r.json());}
function saveUser(id){post('update',{user_id:id,password:document.getElementById('pw'+id).value,email:document.getElementById('em'+id).value,tlf:document.getElementById('tl'+id).value,ip_address:document.getElementById('ip'+id).value,twofactor:document.getElementById('tf'+id).checked}).then(d=>{if(d.error)alert(d.error);else loadUsers();});}
function delUser(id,n){if(!confirm('Slet brugeren '+n+'? Handlingen kan ikke fortrydes.'))return;post('delete',{user_id:id}).then(d=>{if(d.error)alert(d.error);else loadUsers();});}
function clearDT(id,n){if(!confirm('Nulstil gemte tabelopsætninger for '+n+'?'))return;post('clear_datatables',{user_id:id}).then(d=>{if(d.error)alert(d.error);else alert('Nulstillet');});}
function nf(v){return Number(v||0).toLocaleString('da-DK',{minimumFractionDigits:2,maximumFractionDigits:2});}
function openInvoice(id){const s=document.getElementById('invScrim'),d=document.getElementById('invDlg');if(!d)return;s.classList.add('vr-on');d.classList.add('vr-open');document.getElementById('invTitle').textContent='Henter…';document.getElementById('invMeta').textContent='';document.getElementById('invBody').innerHTML='';fetch('admin_panel.php?ajax_invoice_id='+id).then(r=>r.json()).then(x=>{if(x.error){document.getElementById('invTitle').textContent='Fejl';document.getElementById('invBody').textContent=x.error;return;}const inv=x.invoice;document.getElementById('invTitle').textContent=inv.companyName||'Faktura';let ds='-';if(inv.invoiceDate){const p=inv.invoiceDate.split('-');if(p.length===3)ds=p[2]+'-'+p[1]+'-'+p[0];}document.getElementById('invMeta').textContent='Faktura #'+(inv.invoiceNo||inv.orderNo||'-')+' · '+ds;let h='<table class="vr-t"><thead><tr><th>Varenr</th><th>Beskrivelse</th><th class="vr-r">Antal</th><th class="vr-r">Pris</th><th class="vr-r">I alt</th></tr></thead><tbody>';(inv.lines||[]).forEach(l=>{if(!l.description&&!l.sku)return;const q=(l.quantity!=null?l.quantity:'');h+='<tr><td class="vr-mut">'+esc(l.sku||'')+'</td><td>'+esc(l.description||'')+'</td><td class="vr-r">'+esc(q)+' '+esc(l.unit||'')+'</td><td class="vr-r">'+(l.price?nf(l.price):'')+'</td><td class="vr-r">'+((l.quantity&&l.price)?nf(l.quantity*l.price):'')+'</td></tr>';});const sum=parseFloat((inv.economic&&inv.economic.sum)||0),vat=parseFloat((inv.economic&&inv.economic.vat)||0);h+='</tbody></table><div class="vr-kv" style="margin-top:10px"><div><span>Subtotal ekskl. moms</span><b>'+nf(sum)+' DKK</b></div><div><span>Moms</span><b>'+nf(vat)+' DKK</b></div><div><span>I alt</span><b>'+nf(sum+vat)+' DKK</b></div></div>';document.getElementById('invBody').innerHTML=h;}).catch(()=>{document.getElementById('invTitle').textContent='Fejl';});}
function closeInvoice(){document.getElementById('invScrim').classList.remove('vr-on');document.getElementById('invDlg').classList.remove('vr-open');}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeInvoice();});
loadUsers();
</script>
JS;
	print "</body></html>"; exit;
}

// ---------------- customer list ----------------
$sort_col = if_isset($_GET['sort'], 'regnskab');
$sort_dir = strtolower(if_isset($_GET['dir'], 'asc')) === 'desc' ? 'desc' : 'asc';
$allowed_sorts = array('id' => 'id', 'regnskab' => 'regnskab', 'db' => 'db', 'brugerantal' => "COALESCE(NULLIF(brugerantal::text,''),'0')::integer", 'posteringer' => "COALESCE(NULLIF(posteringer::text,''),'0')::integer", 'posteret' => "COALESCE(NULLIF(posteret::text,''),'0')::integer", 'sidst' => "COALESCE(NULLIF(sidst::text,''),'0')::integer", 'lukket' => 'lukket');
$order_column = isset($allowed_sorts[$sort_col]) ? $allowed_sorts[$sort_col] : 'regnskab';
if (!in_array($ltab, array('normale','bogholdere','koncerner','alle'), true)) $ltab = 'normale';
if ($search !== '') $ltab = 'alle'; # a search looks across every customer
$lurl = fn($o) => vr_url('admin_panel.php', array_merge(array('ltab' => $ltab, 'search' => $search, 'sort' => $sort_col, 'dir' => $sort_dir, 'showClosed' => $showClosed), $o));
function ap_th($col, $label, $cls = '') { global $sort_col, $sort_dir, $lurl; $on = ($col === $sort_col); $dir = ($on && $sort_dir === 'asc') ? 'desc' : 'asc'; return "<th".($cls ? " class=\"$cls\"" : "")." aria-sort=\"".($on ? ($sort_dir == 'asc' ? 'ascending' : 'descending') : 'none')."\"><a href=\"".vr_h($lurl(array('sort' => $col, 'dir' => $dir)))."\">".vr_h($label).($on ? ' <span class="vr-ar">'.($sort_dir == 'asc' ? '&#9650;' : '&#9660;').'</span>' : '')."</a></th>"; }
$rows = array(); $where = "db != '$sqdb'"; if ($search !== '') { $s = db_escape_string($search); $where .= " and (regnskab ilike '%$s%' or db ilike '%$s%' or email ilike '%$s%')"; } if (!$showClosed) $where .= " and lukket != 'on'";
$q = db_select("select * from regnskab where $where order by $order_column $sort_dir, id", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $rows[] = $r;
$links = partner_links_by_ledger(); $partners = partner_all(); $plans = ent_plans_by_ledger();
$homes = array(); foreach ($partners as $pp) if ($pp['home_regnskab_id']) $homes[(int)$pp['home_regnskab_id']] = $pp;
$isDirect = fn($r) => empty($links[(int)$r['id']]) && empty($homes[(int)$r['id']]);
$nDirect = count(array_filter($rows, $isDirect)); $nB = count(array_filter($partners, fn($p) => $p['kind'] != 'koncern')); $nK = count(array_filter($partners, fn($p) => $p['kind'] == 'koncern'));
$cntOpen = 0; $cntClosed = 0; $q = db_select("select lukket from regnskab where db != '$sqdb'", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) { if ($r['lukket'] == 'on') $cntClosed++; else $cntOpen++; }

vr_open(array(array('Administrationspanel', 'admin_panel.php'), vr_t('Kunder','Customers')), vr_t('Kunder','Customers'), vr_t('Alle regnskaber på installationen med pakke, forbrug og status. Klik på en kunde for kundekortet med abonnement, brugere, betalinger og indstillinger.','All accounts on this installation with plan, usage and status. Click a customer for the card with subscription, users, payments and settings.'),
	"<a class=\"vr-tog\" role=\"switch\" aria-checked=\"".($showClosed ? 'true' : 'false')."\" href=\"".vr_h($lurl(array('showClosed' => $showClosed ? '' : 'on')))."\"><span>".vr_t('Vis lukkede','Show closed')."</span><span class=\"vr-sw\"></span></a><a class=\"vr-btn\" href=\"partnere.php\">".vr_t('Bogholdere og koncerner','Accountants and groups')."</a><a class=\"vr-btn vr-primary\" href=\"opret.php\">+ ".findtekst('339|Opret regnskab', $sprog_id)."</a>");
vr_note($notes);
vr_tabs(array('normale' => array(vr_t('Normale kunder','Direct customers'), $lurl(array('ltab' => 'normale')), $nDirect), 'bogholdere' => array(vr_t('Bogholdere/revisorer','Accountants'), $lurl(array('ltab' => 'bogholdere')), $nB), 'koncerner' => array(vr_t('Koncernregnskaber','Group accounts'), $lurl(array('ltab' => 'koncerner')), $nK), 'alle' => array(vr_t('Alle','All'), $lurl(array('ltab' => 'alle')), count($rows))), $ltab);
print "<section class=\"vr-sect\"><h2>".vr_t('Kunder','Customers')." <small>$cntOpen ".vr_t('åbne','open')." &middot; $cntClosed ".vr_t('lukkede','closed')."</small></h2><div class=\"vr-card\">";
print "<div class=\"vr-bar\"><form method=\"get\" action=\"admin_panel.php\" class=\"vr-search\" style=\"display:flex\"><input type=\"hidden\" name=\"ltab\" value=\"".vr_h($ltab)."\"><input type=\"hidden\" name=\"sort\" value=\"".vr_h($sort_col)."\"><input type=\"hidden\" name=\"dir\" value=\"".vr_h($sort_dir)."\"><input type=\"hidden\" name=\"showClosed\" value=\"".vr_h($showClosed)."\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linecap=\"round\" style=\"align-self:center\"><circle cx=\"11\" cy=\"11\" r=\"7\"/><path d=\"M20 20l-3.5-3.5\"/></svg><input type=\"search\" name=\"search\" value=\"".vr_h($search)."\" placeholder=\"".vr_t('Søg på navn, database eller e-mail','Search by name, database or e-mail')."\"></form>".($search !== '' ? "<a class=\"vr-btn vr-quiet\" href=\"".vr_h($lurl(array('search' => '')))."\">".vr_t('Nulstil','Reset')."</a>" : "")."<span class=\"vr-grow\"></span><span class=\"vr-hint\">".vr_t('Klik på en kunde for kundekortet','Click a customer for the card')."</span></div>";
print "<table class=\"vr-t\"><thead><tr>".ap_th('id', 'Id', 'vr-r vr-id').ap_th('regnskab', findtekst('2682|Regnskab', $sprog_id))."<th>".vr_t('Pakke','Plan')."</th><th>".vr_t('Tilhører','Belongs to')."</th>".ap_th('brugerantal', findtekst('777|Brugere', $sprog_id), 'vr-r').ap_th('posteret', findtekst('1910|Posteringer', $sprog_id), 'vr-r').ap_th('sidst', vr_t('Sidst aktiv','Last active'), 'vr-r').ap_th('lukket', vr_t('Status','Status'))."<th></th></tr></thead><tbody>";
$row = function($r, $indent = false) use ($links, $homes, $plans, $sprog_id) {
	$id = (int)$r['id']; $closed = ($r['lukket'] == 'on'); $pct = $r['posteringer'] > 0 ? min(100, (int)round($r['posteret'] / $r['posteringer'] * 100)) : 0; $home = isset($homes[$id]) ? $homes[$id] : null; $lk = isset($links[$id]) ? $links[$id] : array();
	print "<tr class=\"".($closed ? 'vr-closed' : '').($indent ? ' vr-in' : '')."\" onclick=\"location.href='admin_panel.php?regnskab_id=$id'\" style=\"cursor:pointer\">";
	print "<td class=\"vr-r vr-id\">$id</td><td class=\"vr-nm\"><a href=\"admin_panel.php?regnskab_id=$id\">".vr_h($r['regnskab'])."</a>".($home ? " <span class=\"vr-pill\">".vr_h(strtolower(vr_kind($home['kind'])))."</span>" : "")."<small>".vr_h($r['db'])."</small></td>";
	print "<td class=\"vr-mut\">".(isset($plans[$id]) && $plans[$id] ? vr_h(ent_plan_name($plans[$id])) : '<span class="vr-mut2">–</span>')."</td>";
	print "<td class=\"vr-mut\">".($lk ? vr_h($lk[0]['name']).(count($lk) > 1 ? "<span class=\"vr-plus\">+".(count($lk)-1)."</span>" : "") : ($home ? '<span class="vr-mut2">'.vr_t('eget regnskab','own account').'</span>' : '<span class="vr-mut2">'.vr_t('direkte kunde','direct customer').'</span>'))."</td>";
	print "<td class=\"vr-r vr-num\">".vr_num($r['brugerantal'])."</td><td class=\"vr-r\"><span class=\"vr-cap\"><span class=\"vr-num\">".vr_num($r['posteret'])."</span><span class=\"vr-mut2\">/ ".vr_num($r['posteringer'])."</span><span class=\"vr-b\"><i".($pct >= 90 ? " class=\"vr-hi\"" : "")." style=\"width:$pct%\"></i></span></span></td>";
	print "<td class=\"vr-r vr-mut\">".vr_rel($r['sidst'])."</td><td><span class=\"vr-st\"><span class=\"vr-dot ".($closed ? 'vr-off' : 'vr-ok')."\"></span>".($closed ? findtekst('387|Lukket', $sprog_id) : vr_t('Åbent','Open'))."</span></td>";
	print "<td class=\"vr-r\"><a class=\"vr-link\" href=\"admin_panel.php?regnskab_id=$id\" onclick=\"event.stopPropagation()\">".vr_t('Administrer','Manage')."</a> · <a class=\"vr-link\" href=\"aaben_regnskab.php?db_id=$id\" onclick=\"event.stopPropagation()\">".vr_t('Åbn','Open')."</a></td></tr>\n";
};
$byId = array(); foreach ($rows as $r) $byId[(int)$r['id']] = $r; $shown = 0;
if ($ltab == 'bogholdere' || $ltab == 'koncerner') {
	foreach ($partners as $pp) { if (($ltab == 'koncerner') != ($pp['kind'] == 'koncern')) continue;
		$members = array(); if ($pp['home_regnskab_id'] && isset($byId[(int)$pp['home_regnskab_id']])) $members[] = (int)$pp['home_regnskab_id']; $nC = 0;
		foreach ($byId as $rid2 => $r2) if (isset($links[$rid2])) foreach ($links[$rid2] as $l) if ((int)$l['id'] == (int)$pp['id']) { $members[] = $rid2; $nC++; }
		$members = array_unique($members); $ne = db_fetch_array(db_select("select count(*) as n from partner_users where partner_id = '".(int)$pp['id']."'", __FILE__ . " linje " . __LINE__));
		print "<tr class=\"vr-grp\"><td colspan=\"9\"><a href=\"partnere.php?id=".(int)$pp['id']."\">".vr_h($pp['name'])."</a><small>$nC ".($pp['kind']=='koncern' ? vr_t('selskaber','companies') : vr_t('kunder','customers'))." &middot; ".(int)$ne['n']." ".vr_t('medarbejdere','employees')."</small></td></tr>\n";
		foreach ($members as $m) { $row($byId[$m], true); $shown++; }
		if (!$members) print "<tr><td colspan=\"9\" class=\"vr-mut2 vr-in\">".vr_t('Ingen regnskaber tilknyttet endnu','No accounts linked yet')."</td></tr>";
	}
} else { foreach ($rows as $r) { if ($ltab == 'normale' && !$isDirect($r)) continue; $row($r); $shown++; } }
if (!$shown) print "<tr><td colspan=\"9\" class=\"vr-mut\" style=\"padding:24px 20px\">".vr_t('Ingen kunder matcher','No customers match')."</td></tr>";
print "</tbody></table><div class=\"vr-foot\"><span>$shown ".vr_t('kunder vist','customers shown')." &middot; ".vr_t('posteringer er fra sidste genberegning','entries are from the last recalculation')."</span></div></div></section>\n";
vr_close();
print "</body></html>";
?>
