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

@session_start();
$s_id = session_id();

$modulnr = 104; // Admin module
$css = "../css/standard.css";
$title = "Admin Panel";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

// ---- REST API Configuration for ssl3.saldi.dk ----
define('SALDI_API_BASE', 'https://ssl3.saldi.dk/finans/restapi/endpoints/v1');
define('SALDI_API_USER', 'api');
define('SALDI_API_PASS', 'Misko3023');
define('SALDI_API_ACCOUNT', 'DANOSOFT');
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
    }
    
    // --- Account settings update ---
    if ($action === 'update_settings') {
        $regnskab_id = (int)$_POST['regnskab_id'];
        $brugerantal = (int)$_POST['brugerantal'];
        $posteringer = (int)$_POST['posteringer'];
        $lukket = isset($_POST['lukket']) ? 'on' : '';
        $betalt_til = $_POST['betalt_til'] ? "'" . db_escape_string($_POST['betalt_til']) . "'" : "'2099-12-31'";
        $logintekst = db_escape_string(if_isset($_POST['logintekst'], ''));
        
        $qtxt = "UPDATE regnskab SET brugerantal='$brugerantal', posteringer='$posteringer', lukket='$lukket', 
                 betalt_til=$betalt_til, logintekst='$logintekst' WHERE id = $regnskab_id";
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        
        $message = "Indstillinger opdateret!";
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

// Get filter
$filter_regnskab = (int)if_isset($_GET['regnskab_id'], 0);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel</title>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8;">
    <link rel="stylesheet" type="text/css" href="../css/standard.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; 
            background: #f7fafc; 
            color: #1a202c;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        
        /* ─── Chakra-inspired Top Bar ─── */
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
            font-weight: 500; transition: color 0.2s; padding: 6px 12px;
            border-radius: 6px;
        }
        .top-bar a:hover { color: #fff; background: rgba(255,255,255,0.1); }
        
        .container { max-width: 1280px; margin: 0 auto; padding: 28px 32px; }
        
        /* ─── Message Banner (Chakra Alert) ─── */
        .message { 
            background: #c6f6d5; border-left: 4px solid #38a169; color: #22543d; 
            padding: 14px 20px; border-radius: 8px; margin-bottom: 24px;
            font-size: 14px; font-weight: 500; display: flex; align-items: center; gap: 10px;
        }
        .message.error { background: #fed7d7; border-left-color: #e53e3e; color: #742a2a; }
        
        /* ─── Filter / Search Bar ─── */
        .filter-bar {
            background: white;
            padding: 16px 24px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0,0,0,0.04);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .filter-bar input[type="text"] {
            padding: 10px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            width: 340px;
            transition: all 0.2s;
            background: #fff;
            color: #1a202c;
        }
        .filter-bar input[type="text"]::placeholder { color: #a0aec0; }
        .filter-bar input[type="text"]:focus { 
            border-color: #319795; outline: none; 
            box-shadow: 0 0 0 3px rgba(49, 151, 149, 0.2);
        }
        
        /* ─── Buttons (Chakra-style) ─── */
        .btn {
            padding: 9px 18px;
            background: #319795;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            font-family: inherit;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            letter-spacing: -0.01em;
        }
        .btn:hover { background: #2c7a7b; box-shadow: 0 2px 4px rgba(49,151,149,0.3); }
        .btn:active { background: #285e61; transform: scale(0.98); }
        .btn-small { padding: 6px 14px; font-size: 13px; border-radius: 6px; }
        .btn-outline { 
            background: transparent; color: #319795; 
            border: 2px solid #319795; 
        }
        .btn-outline:hover { background: #319795; color: white; }
        .btn-success { background: #38a169; }
        .btn-success:hover { background: #2f855a; box-shadow: 0 2px 4px rgba(56,161,105,0.3); }
        
        /* ─── Data Table (Chakra-style) ─── */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0,0,0,0.04);
        }
        .data-table th {
            background: #edf2f7;
            color: #4a5568;
            padding: 12px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 1px solid #e2e8f0;
        }
        .data-table th a:hover { color: #319795 !important; }
        .data-table th .sort-active { color: #319795 !important; }
        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
            color: #2d3748;
        }
        .data-table tbody tr { transition: background 0.15s; }
        .data-table tbody tr:hover { background: #f7fafc; }
        .data-table tbody tr:last-child td { border-bottom: none; }
        
        /* ─── Badges (Chakra pill) ─── */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 10px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.01em;
            line-height: 1.8;
        }
        .badge-active { background: #c6f6d5; color: #22543d; }
        .badge-closed { background: #fed7d7; color: #742a2a; }
        .badge-expired { background: #fefcbf; color: #744210; }
        
        .license-icons { display: flex; gap: 6px; }
        .license-icon {
            width: 26px; height: 26px;
            border-radius: 9999px;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px;
            font-weight: 700;
            transition: transform 0.15s;
        }
        .license-icon:hover { transform: scale(1.15); }
        .license-on { background: #c6f6d5; color: #22543d; }
        .license-off { background: #fed7d7; color: #742a2a; }
        
        /* ─── Breadcrumb ─── */
        .breadcrumb {
            margin-bottom: 20px;
            font-size: 14px;
            color: #718096;
            font-weight: 500;
        }
        .breadcrumb a { color: #319795; text-decoration: none; font-weight: 600; }
        .breadcrumb a:hover { color: #2c7a7b; text-decoration: underline; }
        
        /* ─── Detail Header ─── */
        .detail-header {
            background: white;
            border-radius: 12px;
            padding: 24px 28px;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0,0,0,0.04);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .detail-header h2 { margin: 0 0 4px 0; font-size: 22px; color: #1a202c; font-weight: 700; letter-spacing: -0.02em; }
        .detail-header .subtitle { color: #718096; font-size: 14px; font-weight: 500; }
        
        /* ─── Cards Grid ─── */
        .cards-grid {
            columns: 2;
            column-gap: 24px;
        }
        .card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0,0,0,0.04);
            overflow: hidden;
            transition: box-shadow 0.2s;
            break-inside: avoid;
            margin-bottom: 24px;
        }
        .card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        .card-header {
            padding: 16px 24px;
            border-bottom: 1px solid #edf2f7;
            font-weight: 600;
            font-size: 15px;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.01em;
        }
        .card-body { padding: 20px 24px; }
        .card-full { column-span: all; }
        
        /* ─── Info Rows ─── */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #edf2f7;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #718096; font-size: 13px; font-weight: 500; }
        .info-value { font-size: 14px; color: #2d3748; font-weight: 600; }
        
        /* ─── Usage Bars ─── */
        .usage-bar-wrap {
            margin-top: 6px;
            background: #edf2f7;
            border-radius: 9999px;
            height: 8px;
            overflow: hidden;
            width: 200px;
        }
        .usage-bar {
            height: 100%;
            border-radius: 9999px;
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .usage-bar.green { background: linear-gradient(90deg, #38a169, #48bb78); }
        .usage-bar.yellow { background: linear-gradient(90deg, #d69e2e, #ecc94b); }
        .usage-bar.red { background: linear-gradient(90deg, #e53e3e, #fc8181); }
        
        /* ─── Feature Toggles ─── */
        .feature-row {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 14px 0;
            border-bottom: 1px solid #edf2f7;
        }
        .feature-row:last-child { border-bottom: none; }
        .feature-name { width: 180px; font-weight: 500; font-size: 14px; color: #2d3748; }
        
        /* ─── Toggle Switch (Chakra-style) ─── */
        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute; cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #cbd5e0; border-radius: 9999px;
            transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .toggle-slider:before {
            position: absolute; content: "";
            height: 18px; width: 18px;
            left: 3px; bottom: 3px;
            background: white; border-radius: 50%;
            transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        }
        .toggle-switch input:checked + .toggle-slider { background: #319795; }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }
        
        /* ─── Form Inputs (Chakra-style) ─── */
        .form-group { margin-bottom: 18px; }
        .form-group label { 
            display: block; margin-bottom: 6px; 
            font-size: 14px; font-weight: 600; color: #4a5568; 
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="date"] {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            color: #1a202c;
            transition: all 0.2s;
            background: #fff;
        }
        .form-group input:focus { 
            border-color: #319795; outline: none; 
            box-shadow: 0 0 0 3px rgba(49, 151, 149, 0.2);
        }
        
        .checkbox-group {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 0;
        }
        .checkbox-group input[type="checkbox"] { 
            width: 18px; height: 18px; accent-color: #319795; 
            border-radius: 4px; cursor: pointer;
        }
        .checkbox-group label { font-size: 14px; color: #2d3748; cursor: pointer; font-weight: 500; }
        
        /* ─── Stats Section ─── */
        .stats-number {
            font-size: 30px;
            font-weight: 700;
            color: #319795;
            letter-spacing: -0.02em;
        }
        .stats-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 600;
            margin-top: 4px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            text-align: center;
        }
        .stats-item {
            padding: 20px 16px;
            background: #f7fafc;
            border-radius: 12px;
            border: 1px solid #edf2f7;
            transition: border-color 0.2s;
        }
        .stats-item:hover { border-color: #b2dfdb; }

        /* ─── Payment Card Styles ─── */
        .payment-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .payment-table th {
            text-align: left;
            padding: 10px 12px;
            background: #f7fafc;
            color: #4a5568;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: 2px solid #e2e8f0;
        }
        .payment-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #edf2f7;
            color: #2d3748;
        }
        .payment-table tr:last-child td { border-bottom: none; }
        .payment-table tr:hover { background: #f7fafc; }
        .badge-paid { background: #c6f6d5; color: #22543d; }
        .badge-unpaid { background: #fefcbf; color: #744210; }
        .api-error {
            background: #fefcbf;
            border: 1px solid #ecc94b;
            border-left: 4px solid #d69e2e;
            color: #744210;
            padding: 14px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .payment-highlight {
            background: linear-gradient(135deg, #f0fff4, #e6fffa);
            border: 1px solid #9ae6b4;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 18px;
            transition: all 0.2s;
        }
        .payment-highlight .amount {
            font-size: 26px;
            font-weight: 700;
            color: #276749;
            letter-spacing: -0.02em;
        }
        .payment-highlight .label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 600;
        }
        
        /* ─── Users Card ─── */
        .users-list { display: flex; flex-direction: column; gap: 0; }
        .user-item {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 10px;
            overflow: hidden;
            transition: border-color 0.2s;
        }
        .user-item:hover { border-color: #b2dfdb; }
        .user-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 18px;
            cursor: pointer;
            transition: background 0.15s;
            background: #fff;
        }
        .user-item-header:hover { background: #f7fafc; }
        .user-item-name {
            font-weight: 600; font-size: 14px; color: #2d3748;
            display: flex; align-items: center; gap: 10px;
        }
        .user-item-name .user-avatar {
            width: 32px; height: 32px;
            border-radius: 9999px;
            background: linear-gradient(135deg, #319795, #38b2ac);
            color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700;
            text-transform: uppercase;
            flex-shrink: 0;
        }
        .user-item-meta {
            display: flex; align-items: center; gap: 12px;
            font-size: 12px; color: #718096;
        }
        .user-item-chevron {
            color: #a0aec0; transition: transform 0.25s;
            font-size: 14px;
        }
        .user-item.expanded .user-item-chevron { transform: rotate(180deg); }
        .user-item-body {
            display: none;
            padding: 0 18px 18px;
            background: #f7fafc;
            border-top: 1px solid #edf2f7;
        }
        .user-item.expanded .user-item-body { display: block; }
        .user-edit-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            padding-top: 16px;
        }
        .user-edit-grid .form-group { margin-bottom: 0; }
        .user-edit-grid .form-group label { font-size: 12px; }
        .user-edit-grid .form-group input {
            padding: 8px 12px; font-size: 13px;
        }
        .user-edit-actions {
            display: flex; justify-content: space-between; align-items: center;
            padding-top: 16px;
            border-top: 1px solid #edf2f7;
            margin-top: 16px;
        }
        .btn-danger {
            background: transparent; color: #e53e3e;
            border: 2px solid #e53e3e;
            padding: 6px 14px; border-radius: 6px;
            font-size: 13px; font-weight: 600; cursor: pointer;
            font-family: inherit; transition: all 0.2s;
        }
        .btn-danger:hover { background: #e53e3e; color: white; }
        .user-2fa-toggle {
            display: flex; align-items: center; gap: 10px;
            padding-top: 16px;
        }
        .user-2fa-toggle label { font-size: 12px; font-weight: 600; color: #4a5568; }
        .user-success-msg {
            font-size: 13px; color: #38a169; font-weight: 600;
            opacity: 0; transition: opacity 0.3s;
        }
        .user-success-msg.visible { opacity: 1; }
        .users-empty {
            text-align: center; padding: 32px; color: #a0aec0;
            font-size: 14px;
        }

        /* ─── Collapsible Card ─── */
        .card-header-toggle {
            cursor: pointer;
            user-select: none;
            justify-content: space-between;
        }
        .card-header-toggle:hover { background: #f7fafc; }
        .card-header-chevron {
            font-size: 12px; color: #a0aec0;
            transition: transform 0.25s;
        }
        .card.collapsed .card-header-chevron { transform: rotate(-90deg); }
        .card.collapsed .card-body-collapsible { display: none; }
        .users-search {
            padding: 12px 24px;
            border-bottom: 1px solid #edf2f7;
            background: #f7fafc;
        }
        .users-search input {
            width: 100%; padding: 8px 14px;
            border: 1px solid #e2e8f0; border-radius: 8px;
            font-size: 13px; font-family: inherit;
            background: #fff;
            outline: none; transition: border-color 0.2s;
        }
        .users-search input:focus { border-color: #319795; }
        .card.collapsed .users-search { display: none; }

        /* ─── Invoice Preview Modal (Chakra Modal) ─── */
        .invoice-preview-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.48);
            z-index: 1000;
            backdrop-filter: blur(4px);
            align-items: center; justify-content: center;
        }
        .invoice-preview-modal {
            background: white;
            border-radius: 12px;
            width: 700px;
            max-width: 90vw;
            max-height: 85vh;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 0 0 1px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: translateY(20px) scale(0.97);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .invoice-preview-backdrop.visible { display: flex; }
        .invoice-preview-backdrop.visible .invoice-preview-modal {
            transform: translateY(0) scale(1); opacity: 1;
        }
        
        .inv-modal-header {
            padding: 20px 24px;
            background: #f7fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; align-items: flex-start;
        }
        .inv-modal-title { font-size: 20px; font-weight: 700; color: #1a202c; margin: 0 0 4px 0; letter-spacing: -0.02em; }
        .inv-modal-info { color: #718096; font-size: 14px; font-weight: 500; }
        .inv-modal-close {
            background: none; border: none; font-size: 22px; color: #a0aec0;
            cursor: pointer; line-height: 1; width: 36px; height: 36px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 8px; transition: 0.2s;
        }
        .inv-modal-close:hover { background: #edf2f7; color: #1a202c; }
        
        .inv-modal-body {
            padding: 24px;
            overflow-y: auto;
            flex-grow: 1;
        }
        .inv-lines-table {
            width: 100%; border-collapse: collapse; margin-bottom: 24px;
        }
        .inv-lines-table th {
            text-align: left; padding: 10px 12px;
            border-bottom: 2px solid #e2e8f0;
            color: #4a5568; font-size: 11px; text-transform: uppercase; font-weight: 700;
            letter-spacing: 0.06em;
        }
        .inv-lines-table td {
            padding: 12px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px; color: #2d3748;
        }
        .inv-lines-table .num { text-align: right; }
        .inv-totals {
            width: 300px; margin-left: auto;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px;
        }
        .inv-totals-row {
            display: flex; justify-content: space-between;
            padding: 6px 0; font-size: 14px; color: #4a5568; font-weight: 500;
        }
        .inv-totals-row.bold {
            font-weight: 700; color: #1a202c; font-size: 16px;
            border-top: 2px solid #e2e8f0; margin-top: 8px; padding-top: 12px;
        }
        
        .inv-loading {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 48px; color: #718096;
        }
        .spinner {
            width: 40px; height: 40px; border: 3px solid #e2e8f0;
            border-top: 3px solid #319795; border-radius: 50%;
            animation: spin 0.8s linear infinite; margin-bottom: 16px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .invoice-row-trigger { cursor: pointer; transition: all 0.15s; }
        .invoice-row-trigger:hover { background: #edf2f7 !important; }
        .payment-highlight.invoice-row-trigger:hover {
            border-color: #68d391; box-shadow: 0 4px 12px rgba(56, 161, 105, 0.12);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

<div class="top-bar">
    <h1>⚡ Admin Panel</h1>
    <div>
        <a href="vis_regnskaber.php" style="margin-right: 20px;">← Vis regnskaber</a>
        <a href="../index/admin_menu.php">← Admin menu</a>
    </div>
</div>

<div class="container">

<?php if ($message) { ?>
    <div class="message <?php echo $message_type === 'error' ? 'error' : ''; ?>">
        <?php echo $message_type === 'error' ? '⚠️' : '✅'; ?>
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php } ?>

<?php if ($filter_regnskab) { 
    // ============================================================
    // DETAIL VIEW - Single regnskab
    // ============================================================
    
    $qtxt = "SELECT * FROM regnskab WHERE id = $filter_regnskab";
    $reg = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
    
    if (!$reg) {
        echo "<div class='message error'>⚠️ Regnskab ikke fundet!</div>";
        echo "<a href='admin_panel.php' class='btn btn-outline'>← Tilbage</a>";
    } else {
        // Get license data
        $licenses = array();
        $qtxt = "SELECT feature_key, enabled, expires_at FROM license_features WHERE regnskab_id = $filter_regnskab";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        while ($r = db_fetch_array($q)) {
            $licenses[$r['feature_key']] = $r;
        }
        
        // Try to get actual user count, transaction count, and cvrnr from client database
        $actual_brugere = '?';
        $actual_transaktioner = '?';
        $client_cvrnr = '';
        $client_db = $reg['db'];
        
        if ($client_db && $client_db != $sqdb) {
            // Count users online in the last 20 minutes from the master database 'online' table
            $time_limit = time() - 1200; // 20 minutes ago
            $qtxt = "SELECT COUNT(DISTINCT session_id) as cnt FROM online WHERE db = '$client_db' AND logtime >= '$time_limit'";
            $qr = db_select($qtxt, __FILE__ . " linje " . __LINE__);
            if ($qr && $rr = db_fetch_array($qr)) {
                $actual_brugere = $rr['cnt'] * 1;
            }
            
            // Connect to client DB
            $client_conn = @db_connect($sqhost, $squser, $sqpass, $client_db, __FILE__ . " linje " . __LINE__);
            if ($client_conn) {
                // Count transaktioner (last year)
                $y = date("Y") - 1;
                $m = date("m");
                $d = date("d");
                $dd = "$y-$m-$d";
                $qtxt = "SELECT count(id) as cnt FROM transaktioner WHERE logdate >= '$dd'";
                $qr = @db_select($qtxt, __FILE__ . " linje " . __LINE__);
                if ($qr && $rr = db_fetch_array($qr)) {
                    $actual_transaktioner = $rr['cnt'] * 1;
                }
                
                // Get cvrnr for API search
                $qtxt = "SELECT cvrnr FROM adresser WHERE art='S' LIMIT 1";
                $qr = @db_select($qtxt, __FILE__ . " linje " . __LINE__);
                if ($qr && $rr = db_fetch_array($qr)) {
                    $cvr = trim($rr['cvrnr']);
                    $cvr = preg_replace('/^DK\s*/i', '', $cvr);
                    $client_cvrnr = str_replace(' ', '', $cvr);
                }
                
                // Re-connect to master
                include("../includes/connect.php");
            }
        }
        
        // Prepare display values
        $reg_name = htmlspecialchars($reg['regnskab']);
        $reg_db = htmlspecialchars($reg['db']);
        $reg_email = htmlspecialchars(if_isset($reg['email'], '-'));
        $max_brugere = $reg['brugerantal'] * 1;
        $max_posteringer = $reg['posteringer'] * 1;
        $posteret = $reg['posteret'] * 1;
        $sidst = $reg['sidst'] ? date("d-m-Y", $reg['sidst']) : '-';
        $is_closed = ($reg['lukket'] == 'on');
        $betalt_til_raw = if_isset($reg, '', 'betalt_til');
        $betalt_til_display = $betalt_til_raw ? date("d-m-Y", strtotime($betalt_til_raw)) : '-'; 
        $betalt_til_input = $betalt_til_raw ? date("Y-m-d", strtotime($betalt_til_raw)) : '';
        $logintekst_val = htmlspecialchars(if_isset($reg, '', 'logintekst'));
        
        // Usage percentages
        if ($max_brugere > 0 && is_numeric($actual_brugere)) {
            $brugere_pct = min(100, round(($actual_brugere / $max_brugere) * 100));
        } else {
            $brugere_pct = 0;
        }
        if ($max_posteringer > 0) {
            $poster_pct = min(100, round(($posteret / $max_posteringer) * 100));
        } else {
            $poster_pct = 0;
        }
        
        function bar_color($pct) {
            if ($pct < 60) return 'green';
            if ($pct < 85) return 'yellow';
            return 'red';
        }
?>

    <div class="breadcrumb">
        <a href="admin_panel.php">Admin Panel</a> &raquo; <?php echo $reg_name; ?>
    </div>
    
    <div class="detail-header">
        <div>
            <h2><?php echo $reg_name; ?></h2>
            <div class="subtitle">Database: <?php echo $reg_db; ?> &nbsp;|&nbsp; ID: <?php echo $filter_regnskab; ?></div>
        </div>
        <div>
            <?php if ($is_closed) { ?>
                <span class="badge badge-closed">🔒 Lukket</span>
            <?php } else { ?>
                <span class="badge badge-active">✓ Aktiv</span>
            <?php } ?>
        </div>
    </div>
    
    <!-- Stats overview -->
    <div class="card card-full" style="margin-bottom: 20px;">
        <div class="card-body">
            <div class="stats-grid">
                <div class="stats-item">
                    <div class="stats-number"><?php echo $actual_brugere; ?><span style="font-size:16px;color:#999;"> / <?php echo $max_brugere; ?></span></div>
                    <div class="stats-label">Brugere Online (20 min)</div>
                    <div class="usage-bar-wrap" style="margin: 8px auto 0;">
                        <div class="usage-bar <?php echo bar_color($brugere_pct); ?>" style="width: <?php echo $brugere_pct; ?>%"></div>
                    </div>
                </div>
                <div class="stats-item">
                    <div class="stats-number"><?php echo number_format($posteret, 0, ',', '.'); ?><span style="font-size:16px;color:#999;"> / <?php echo $max_posteringer > 0 ? number_format($max_posteringer, 0, ',', '.') : '<span style="font-size: 1.5em; vertical-align: middle; line-height: 1;">&infin;</span>'; ?></span></div>
                    <div class="stats-label">Posteringer (brugt / maks)</div>
                    <div class="usage-bar-wrap" style="margin: 8px auto 0;">
                        <div class="usage-bar <?php echo bar_color($poster_pct); ?>" style="width: <?php echo $poster_pct; ?>%"></div>
                    </div>
                </div>
                <div class="stats-item">
                    <div class="stats-number"><?php echo $sidst; ?></div>
                    <div class="stats-label">Sidst aktiv</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="cards-grid">
        
        <!-- Kontoinformation -->
        <div class="card">
            <div class="card-header">📋 Kontoinformation</div>
            <div class="card-body">
                <div class="info-row">
                    <span class="info-label">Regnskab</span>
                    <span class="info-value"><?php echo $reg_name; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Database</span>
                    <span class="info-value"><?php echo $reg_db; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo $reg_email; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Sidst aktiv</span>
                    <span class="info-value"><?php echo $sidst; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Betalt til</span>
                    <span class="info-value"><?php echo $betalt_til_display; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Logintekst</span>
                    <span class="info-value"><?php echo $logintekst_val ?: '-'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <?php if ($is_closed) { ?>
                            <span class="badge badge-closed">Lukket</span>
                        <?php } else { ?>
                            <span class="badge badge-active">Aktiv</span>
                        <?php } ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Licenser -->
        <div class="card">
            <div class="card-header">🔐 Licenser</div>
            <div class="card-body">
                <form method="post" action="admin_panel.php?regnskab_id=<?php echo $filter_regnskab; ?>">
                    <input type="hidden" name="action" value="bulk_update">
                    <input type="hidden" name="regnskab_id" value="<?php echo $filter_regnskab; ?>">
                    
                    <?php foreach ($available_features as $feature_key => $feature_name) { 
                        $license = isset($licenses[$feature_key]) ? $licenses[$feature_key] : array('enabled' => true, 'expires_at' => null);
                        $is_enabled = $license['enabled'] && $license['enabled'] != 'f' && $license['enabled'] != '0';
                    ?>
                    <div class="feature-row">
                        <div class="feature-name"><?php echo htmlspecialchars($feature_name); ?></div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="feature_<?php echo $feature_key; ?>" <?php echo $is_enabled ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <div>
                            <input type="date" name="expires_<?php echo $feature_key; ?>" 
                                   value="<?php echo $license['expires_at'] ? date('Y-m-d', strtotime($license['expires_at'])) : ''; ?>"
                                   style="padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;"
                                   title="Udløbsdato (tom = ingen udløb)">
                        </div>
                    </div>
                    <?php } ?>
                    
                    <div style="margin-top: 16px; text-align: right;">
                        <button type="submit" class="btn btn-small btn-success">💾 Gem licenser</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Seneste Betaling (fra Saldi API) -->
        <div class="card">
            <div class="card-header">💳 Seneste Betaling</div>
            <div class="card-body">
                <?php
                $api_search = $client_cvrnr ? $client_cvrnr : $reg['regnskab'];
                $payment_data = fetch_customer_invoices($api_search);
                
                if (isset($payment_data['error'])) {
                    echo "<div class='api-error'>⚠️ " . $payment_data['error'] . "</div>";
                } else {
                    $invoices = $payment_data['invoices'];
                    $latest = $invoices[0];
                    
                    $latest_date = $latest['invoiceDate'] ? date('d-m-Y', strtotime($latest['invoiceDate'])) : '-';
                    $latest_sum = number_format(($latest['economic']['sum'] ?? 0), 2, ',', '.');
                    $latest_moms = number_format(($latest['economic']['vat'] ?? 0), 2, ',', '.');
                    $latest_total = number_format(($latest['economic']['sum'] ?? 0) + ($latest['economic']['vat'] ?? 0), 2, ',', '.');
                    $latest_paid = $latest['paid'] == '1' || $latest['paid'] === true;
                    $latest_company = htmlspecialchars($latest['companyName'] ?? '-');
                    $latest_ordrenr = $latest['invoiceNo'] ?? $latest['orderNo'] ?? '-';
                    
                    $latest_terms = htmlspecialchars($latest['paymentInfo']['paymentTerms'] ?? '-');
                    ?>
                    
                    <div class="payment-highlight invoice-row-trigger" onclick="openInvoiceModal(<?php echo $latest['id']; ?>)">
                        <div class="label">Seneste faktura</div>
                        <div class="amount"><?php echo $latest_total; ?> DKK</div>
                        <div style="font-size: 13px; color: #666; margin-top: 4px;">
                            Faktura #<?php echo $latest_ordrenr; ?> — <?php echo $latest_date; ?>
                            &nbsp;
                            <?php if ($latest_paid) { ?>
                                <span class="badge badge-paid">✓ Betalt</span>
                            <?php } else { ?>
                                <span class="badge badge-unpaid">⏳ Ubetalt</span>
                            <?php } ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Firma</span>
                        <span class="info-value"><?php echo $latest_company; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Beløb ekskl. moms</span>
                        <span class="info-value"><?php echo $latest_sum; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Moms</span>
                        <span class="info-value"><?php echo $latest_moms; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Betalingsbetingelser</span>
                        <span class="info-value"><?php echo $latest_terms; ?></span>
                    </div>
                    
                    <?php if (count($invoices) > 1) { ?>
                    <div style="margin-top: 16px;">
                        <div style="font-weight: 600; font-size: 13px; color: #666; margin-bottom: 8px;">Seneste fakturaer</div>
                        <table class="payment-table">
                            <thead>
                                <tr>
                                    <th>Faktura #</th>
                                    <th>Dato</th>
                                    <th>Beløb</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_invoices = count($invoices);
                                for ($i = 0; $i < $total_invoices; $i++) {
                                    $inv = $invoices[$i];
                                    $inv_date = $inv['invoiceDate'] ? date('d-m-Y', strtotime($inv['invoiceDate'])) : '-';
                                    $inv_total = number_format(($inv['economic']['sum'] ?? 0) + ($inv['economic']['vat'] ?? 0), 2, ',', '.');
                                    $inv_paid = $inv['paid'] == '1' || $inv['paid'] === true;
                                    $hidden = $i >= 5 ? ' class="invoice-row-extra" style="display:none;"' : '';
                                    ?>
                                    <tr<?php echo $hidden; ?>>
                                        <td class="invoice-row-trigger" onclick="openInvoiceModal(<?php echo $inv['id']; ?>)"><?php echo $inv['invoiceNo'] ?? $inv['orderNo'] ?? '-'; ?></td>
                                        <td class="invoice-row-trigger" onclick="openInvoiceModal(<?php echo $inv['id']; ?>)"><?php echo $inv_date; ?></td>
                                        <td class="invoice-row-trigger" onclick="openInvoiceModal(<?php echo $inv['id']; ?>)" style="font-weight: 600;"><?php echo $inv_total; ?></td>
                                        <td>
                                            <?php if ($inv_paid) { ?>
                                                <span class="badge badge-paid">Betalt</span>
                                            <?php } else { ?>
                                                <span class="badge badge-unpaid">Ubetalt</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                        <?php if ($total_invoices > 5) { ?>
                        <div style="text-align:center; margin-top: 10px;">
                            <button type="button" class="btn btn-small btn-outline" id="invoiceShowMoreBtn" onclick="toggleInvoiceRows()">
                                Vis flere (<?php echo $total_invoices - 5; ?>)
                            </button>
                        </div>
                        <?php } ?>
                    </div>
                    <?php } ?>
                    
                <?php } ?>
            </div>
        </div>
        
        <!-- Indstillinger -->
        <div class="card card-full">
            <div class="card-header">⚙️ Indstillinger</div>
            <div class="card-body">
                <form method="post" action="admin_panel.php?regnskab_id=<?php echo $filter_regnskab; ?>">
                    <input type="hidden" name="action" value="update_settings">
                    <input type="hidden" name="regnskab_id" value="<?php echo $filter_regnskab; ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Max brugere</label>
                            <input type="number" name="brugerantal" value="<?php echo $max_brugere; ?>" min="0">
                        </div>
                        <div class="form-group">
                            <label>Max posteringer</label>
                            <input type="number" name="posteringer" value="<?php echo $max_posteringer; ?>" min="0">
                        </div>
                        <div class="form-group">
                            <label>Betalt til</label>
                            <input type="date" name="betalt_til" value="<?php echo $betalt_til_input; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Logintekst</label>
                        <input type="text" name="logintekst" value="<?php echo $logintekst_val; ?>" placeholder="Tekst der vises ved login...">
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" name="lukket" id="lukket_cb" <?php echo $is_closed ? 'checked' : ''; ?>>
                        <label for="lukket_cb">Lukket (deaktivér konto)</label>
                    </div>
                    
                    <div style="margin-top: 16px; text-align: right;">
                        <button type="submit" class="btn btn-success">💾 Gem indstillinger</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Brugere (Users) -->
        <div class="card card-full collapsed" id="usersCard">
            <div class="card-header card-header-toggle" onclick="toggleUsersCard()">
                👥 Brugere
                <span class="card-header-chevron">▼</span>
            </div>
            <div class="users-search">
                <input type="text" id="usersSearchInput" placeholder="Søg brugere..." oninput="filterUsers(this.value)">
            </div>
            <div class="card-body card-body-collapsible">
                <div id="usersContainer">
                    <div class="inv-loading" id="usersLoading">
                        <div class="spinner"></div>
                        <div>Henter brugere...</div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>

<?php 
    } // end if reg found
} else { 
    // ============================================================
    // OVERVIEW LIST - All regnskaber
    // ============================================================
?>
    
    <div class="filter-bar">
        <form method="get" action="admin_panel.php" style="display:flex; gap:12px; align-items:center; width:100%;">
            <span style="font-size:18px;">🔍</span>
            <input type="text" name="search" value="<?php echo htmlspecialchars(if_isset($_GET['search'], '')); ?>" 
                   placeholder="Søg efter regnskab, database...">
            <input type="hidden" name="sort" value="<?php echo htmlspecialchars(if_isset($_GET['sort'], 'regnskab')); ?>">
            <input type="hidden" name="dir" value="<?php echo htmlspecialchars(if_isset($_GET['dir'], 'asc')); ?>">
            <button type="submit" class="btn btn-small">Søg</button>
            <?php if (if_isset($_GET['search'], '')) { ?>
                <a href="admin_panel.php" class="btn btn-small btn-outline">Nulstil</a>
            <?php } ?>
        </form>
    </div>
    
    <?php
    // Sorting logic
    $sort_col = if_isset($_GET['sort'], 'regnskab');
    $sort_dir = strtolower(if_isset($_GET['dir'], 'asc')) === 'desc' ? 'desc' : 'asc';
    
    $allowed_sorts = [
        'id' => 'id',
        'regnskab' => 'regnskab',
        'db' => 'db',
        'brugerantal' => "COALESCE(NULLIF(brugerantal::text,''),'0')::integer",
        'posteringer' => "COALESCE(NULLIF(posteringer::text,''),'0')::integer",
        'posteret' => "COALESCE(NULLIF(posteret::text,''),'0')::integer",
        'sidst' => "COALESCE(NULLIF(sidst::text,''),'0')::integer",
        'lukket' => 'lukket'
    ];
    $order_column = isset($allowed_sorts[$sort_col]) ? $allowed_sorts[$sort_col] : 'regnskab';
    
    $search_param = htmlspecialchars(if_isset($_GET['search'], ''));
    
    function sort_link($col, $label, $current_sort, $current_dir, $search) {
        $new_dir = ($col === $current_sort && $current_dir === 'asc') ? 'desc' : 'asc';
        $arrow = '';
        $active = '';
        if ($col === $current_sort) {
            $arrow = $current_dir === 'asc' ? ' ▲' : ' ▼';
            $active = ' class="sort-active"';
        }
        $params = 'sort=' . $col . '&dir=' . $new_dir;
        if ($search) $params .= '&search=' . urlencode($search);
        return '<a href="admin_panel.php?' . $params . '"' . $active . ' style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:4px;white-space:nowrap;">' . $label . '<span style="font-size:10px;opacity:0.7;">' . $arrow . '</span></a>';
    }
    ?>
    <table class="data-table">
        <thead>
            <tr>
                <th><?php echo sort_link('id', 'ID', $sort_col, $sort_dir, $search_param); ?></th>
                <th><?php echo sort_link('regnskab', 'Regnskab', $sort_col, $sort_dir, $search_param); ?></th>
                <th><?php echo sort_link('db', 'Database', $sort_col, $sort_dir, $search_param); ?></th>
                <th><?php echo sort_link('brugerantal', 'Brugere', $sort_col, $sort_dir, $search_param); ?></th>
                <th><?php echo sort_link('posteringer', 'Posteringer', $sort_col, $sort_dir, $search_param); ?></th>
                <th><?php echo sort_link('posteret', 'Posteret', $sort_col, $sort_dir, $search_param); ?></th>
                <th><?php echo sort_link('sidst', 'Sidst aktiv', $sort_col, $sort_dir, $search_param); ?></th>
                <th><?php echo sort_link('lukket', 'Status', $sort_col, $sort_dir, $search_param); ?></th>
                <th>Licenser</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $search = db_escape_string(if_isset($_GET['search'], ''));
            $where = "WHERE db != '$sqdb'";
            if ($search) {
                $where .= " AND (regnskab ILIKE '%$search%' OR db ILIKE '%$search%')";
            }
            
            $qtxt = "SELECT * FROM regnskab $where ORDER BY $order_column $sort_dir";
            $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
            
            // Preload all licenses
            $all_licenses = array();
            $qtxt2 = "SELECT regnskab_id, feature_key, enabled, expires_at FROM license_features";
            $q2 = db_select($qtxt2, __FILE__ . " linje " . __LINE__);
            if ($q2) {
                while ($lic = db_fetch_array($q2)) {
                    if (!isset($all_licenses[$lic['regnskab_id']])) $all_licenses[$lic['regnskab_id']] = array();
                    $all_licenses[$lic['regnskab_id']][$lic['feature_key']] = $lic;
                }
            }
            
            $row_count = 0;
            while ($reg = db_fetch_array($q)) {
                $reg_id = $reg['id'];
                $row_count++;
                $is_closed = ($reg['lukket'] == 'on');
                $sidst_val = $reg['sidst'] ? date("d-m-Y", $reg['sidst']) : '-';
                
                // License status
                $reg_licenses = isset($all_licenses[$reg_id]) ? $all_licenses[$reg_id] : array();
                
                echo "<tr>";
                echo "<td style='color:#999;'>" . $reg_id . "</td>";
                echo "<td><strong>" . htmlspecialchars($reg['regnskab']) . "</strong></td>";
                echo "<td style='color:#666; font-size:13px;'>" . htmlspecialchars($reg['db']) . "</td>";
                echo "<td>" . ($reg['brugerantal'] * 1) . "</td>";
                echo "<td>" . ($reg['posteringer'] * 1 > 0 ? number_format($reg['posteringer'] * 1, 0, ',', '.') : '<span style="font-size: 1.5em; vertical-align: middle; line-height: 1;">&infin;</span>') . "</td>";
                echo "<td>" . number_format($reg['posteret'] * 1, 0, ',', '.') . "</td>";
                echo "<td style='font-size:13px;'>" . $sidst_val . "</td>";
                
                // Status
                if ($is_closed) {
                    echo "<td><span class='badge badge-closed'>Lukket</span></td>";
                } else {
                    echo "<td><span class='badge badge-active'>Aktiv</span></td>";
                }
                
                // Licenses
                echo "<td><div class='license-icons'>";
                foreach ($available_features as $fk => $fn) {
                    $lic = isset($reg_licenses[$fk]) ? $reg_licenses[$fk] : null;
                    if (!$lic) {
                        $status_class = 'license-on';
                        $icon = '✓';
                    } else {
                        $is_on = $lic['enabled'] && $lic['enabled'] != 'f' && $lic['enabled'] != '0';
                        $is_exp = $lic['expires_at'] && strtotime($lic['expires_at']) < time();
                        if ($is_exp) {
                            $status_class = 'license-off';
                            $icon = '!';
                        } elseif ($is_on) {
                            $status_class = 'license-on';
                            $icon = '✓';
                        } else {
                            $status_class = 'license-off';
                            $icon = '✗';
                        }
                    }
                    $short = strtoupper(substr($fk, 0, 1));
                    echo "<span class='license-icon $status_class' title='" . htmlspecialchars($fn) . "' onclick='toggleLicense(this, $reg_id, \"$fk\")' style='cursor:pointer;'>$short</span>";
                }
                echo "</div></td>";
                
                echo "<td><a href='admin_panel.php?regnskab_id=$reg_id' class='btn btn-small btn-outline'>Administrér</a></td>";
                echo "</tr>";
            }
            
            if ($row_count === 0) {
                echo "<tr><td colspan='10' style='text-align:center; padding:40px; color:#999;'>Ingen regnskaber fundet</td></tr>";
            }
            ?>
        </tbody>
    </table>
    
    <div style="margin-top: 16px; color: #999; font-size: 13px;">
        <?php echo $row_count; ?> regnskab<?php echo $row_count !== 1 ? 'er' : ''; ?> fundet
    </div>

<?php } // end overview ?>

</div>

<!-- Invoice Preview Modal -->
<div class="invoice-preview-backdrop" id="invoiceModalBackdrop">
    <div class="invoice-preview-modal" id="invoiceModal">
        <div class="inv-modal-header">
            <div>
                <h3 class="inv-modal-title" id="invModalCompany">Laster...</h3>
                <div class="inv-modal-info" id="invModalMeta"></div>
            </div>
            <button class="inv-modal-close" onclick="closeInvoiceModal()">&times;</button>
        </div>
        <div class="inv-modal-body" id="invModalBody">
            <div class="inv-loading">
                <div class="spinner"></div>
                <div>Henter fakturadetaljer...</div>
            </div>
        </div>
    </div>
</div>

<script>
function numberFormat(number) {
    if(!number) return '0,00';
    return parseFloat(number).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function closeInvoiceModal() {
    document.getElementById('invoiceModalBackdrop').classList.remove('visible');
}

// Close on backdrop click
document.getElementById('invoiceModalBackdrop').addEventListener('click', function(e) {
    if (e.target === this) {
        closeInvoiceModal();
    }
});

function toggleInvoiceRows() {
    const extras = document.querySelectorAll('.invoice-row-extra');
    const btn = document.getElementById('invoiceShowMoreBtn');
    const showing = extras.length > 0 && extras[0].style.display !== 'none';
    extras.forEach(r => r.style.display = showing ? 'none' : '');
    btn.textContent = showing ? 'Vis flere (' + extras.length + ')' : 'Vis færre';
}

function openInvoiceModal(invoiceId) {
    const backdrop = document.getElementById('invoiceModalBackdrop');
    const body = document.getElementById('invModalBody');
    const companyTitle = document.getElementById('invModalCompany');
    const metaContainer = document.getElementById('invModalMeta');
    
    // Reset modal state
    companyTitle.innerHTML = 'Henter...';
    metaContainer.innerHTML = '';
    body.innerHTML = `
        <div class="inv-loading">
            <div class="spinner"></div>
            <div>Henter fakturadetaljer...</div>
        </div>
    `;
    
    backdrop.classList.add('visible');
    
    // Fetch data
    fetch(`admin_panel.php?ajax_invoice_id=${invoiceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                body.innerHTML = `<div style="padding: 20px; color: #dc3545; text-align: center;">Værdien kunne ikke hentes: ${data.error}</div>`;
                companyTitle.innerHTML = 'Fejl';
                return;
            }
            
            const inv = data.invoice;
            
            // Header info
            companyTitle.textContent = inv.companyName || 'Ukendt firma';
            
            let dateStr = '-';
            if (inv.invoiceDate) {
                const parts = inv.invoiceDate.split('-');
                if(parts.length === 3) dateStr = `${parts[2]}-${parts[1]}-${parts[0]}`;
            }
            
            metaContainer.innerHTML = `Faktura #${inv.invoiceNo || inv.orderNo || '-'} &nbsp;&bull;&nbsp; Dato: ${dateStr}`;
            
            // Build lines table
            let linesHtml = `
                <table class="inv-lines-table">
                    <thead>
                        <tr>
                            <th>Varenr</th>
                            <th>Beskrivelse</th>
                            <th class="num">Antal</th>
                            <th class="num">Pris</th>
                            <th class="num">I alt</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            if (inv.lines && inv.lines.length > 0) {
                inv.lines.forEach(line => {
                    // Only show lines with actual items, or fallback for text lines
                    if (!line.description && !line.sku) return;
                    
                    const qty = line.quantity !== null && line.quantity !== undefined ? line.quantity : '';
                    const price = line.price ? numberFormat(line.price) : '';
                    const lineTotal = (line.quantity && line.price) ? numberFormat(line.quantity * line.price) : '';
                    
                    linesHtml += `
                        <tr>
                            <td style="width: 15%;">${line.sku || ''}</td>
                            <td style="width: 40%;">${line.description || ''}</td>
                            <td class="num" style="width: 15%;">${qty} ${line.unit || ''}</td>
                            <td class="num" style="width: 15%;">${price}</td>
                            <td class="num" style="width: 15%; font-weight: 500;">${lineTotal}</td>
                        </tr>
                    `;
                });
            } else {
                linesHtml += `<tr><td colspan="5" style="text-align: center; color: #999;">Ingen linjer fundet</td></tr>`;
            }
            linesHtml += `</tbody></table>`;
            
            // Add totals
            const sum = inv.economic && inv.economic.sum ? inv.economic.sum : 0;
            const vat = inv.economic && inv.economic.vat ? inv.economic.vat : 0;
            const total = parseFloat(sum) + parseFloat(vat);
            
            linesHtml += `
                <div class="inv-totals">
                    <div class="inv-totals-row">
                        <span>Subtotal ekskl. moms</span>
                        <span>${numberFormat(sum)} DKK</span>
                    </div>
                    <div class="inv-totals-row">
                        <span>Moms</span>
                        <span>${numberFormat(vat)} DKK</span>
                    </div>
                    <div class="inv-totals-row bold">
                        <span>Total inkl. moms</span>
                        <span>${numberFormat(total)} DKK</span>
                    </div>
                </div>
            `;
            
            body.innerHTML = linesHtml;
        })
        .catch(error => {
            console.error('Error fetching invoice:', error);
            body.innerHTML = `<div style="padding: 20px; color: #dc3545; text-align: center;">Der opstod en fejl under hentning af fakturaen.</div>`;
            companyTitle.innerHTML = 'Fejl';
        });
}

// ─── Users Management ───
const REGNSKAB_ID = <?php echo $filter_regnskab ? $filter_regnskab : 0; ?>;

function loadUsers() {
    if (!REGNSKAB_ID) return;
    const container = document.getElementById('usersContainer');
    if (!container) return;
    
    fetch(`admin_panel.php?ajax_users=1&regnskab_id=${REGNSKAB_ID}&user_action=list`)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                container.innerHTML = `<div class="api-error">⚠️ ${data.error}</div>`;
                return;
            }
            if (!data.users || data.users.length === 0) {
                container.innerHTML = `<div class="users-empty">Ingen brugere fundet i denne database</div>`;
                return;
            }
            renderUsers(data.users, container);
        })
        .catch(err => {
            container.innerHTML = `<div class="api-error">⚠️ Fejl ved hentning af brugere</div>`;
        });
}

function renderUsers(users, container) {
    let html = '<div class="users-list">';
    users.forEach(u => {
        const initials = (u.brugernavn || '?').substring(0, 2);
        const tfaLabel = u.twofactor ? '<span class="badge badge-active">2FA</span>' : '';
        const emailDisplay = u.email || '';
        const tlfDisplay = u.tlf || '';
        html += `
        <div class="user-item" id="user-item-${u.id}">
            <div class="user-item-header" onclick="toggleUserItem(${u.id})">
                <div class="user-item-name">
                    <div class="user-avatar">${initials}</div>
                    <div>
                        <div>${u.brugernavn || '?'}</div>
                        <div style="font-size:12px;color:#718096;font-weight:400;">${emailDisplay}</div>
                    </div>
                </div>
                <div class="user-item-meta">
                    ${tfaLabel}
                    <span class="user-item-chevron">▼</span>
                </div>
            </div>
            <div class="user-item-body">
                <div class="user-edit-grid">
                    <div class="form-group">
                        <label>Ny adgangskode</label>
                        <input type="password" id="pw-${u.id}" placeholder="Lad tom for ingen ændring">
                    </div>
                    <div class="form-group">
                        <label>Email (til 2FA)</label>
                        <input type="text" id="email-${u.id}" value="${u.email || ''}">
                    </div>
                    <div class="form-group">
                        <label>Telefon (til 2FA)</label>
                        <input type="text" id="tlf-${u.id}" value="${u.tlf || ''}">
                    </div>
                    <div class="form-group">
                        <label>Tilladte IP-adresser</label>
                        <input type="text" id="ip-${u.id}" value="${u.ip_address || ''}">
                    </div>
                </div>
                <div class="user-2fa-toggle">
                    <label class="toggle-switch">
                        <input type="checkbox" id="2fa-${u.id}" ${u.twofactor ? 'checked' : ''}>
                        <span class="toggle-slider"></span>
                    </label>
                    <label>Two-factor authentication</label>
                </div>
                <div class="user-edit-actions">
                    <div style="display:flex; gap:10px;">
                        <button class="btn-danger" onclick="deleteUser(${u.id}, '${(u.brugernavn || '').replace(/'/g, "\\'")}')">Slet bruger</button>
                        <button class="btn btn-small btn-outline" style="border-radius: 6px;" onclick="clearDatatables(${u.id}, '${(u.brugernavn || '').replace(/'/g, "\\'")}')">Nulstil tabeller</button>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span class="user-success-msg" id="msg-${u.id}">✓ Gemt</span>
                        <button class="btn btn-small btn-success" onclick="saveUser(${u.id})">💾 Gem ændringer</button>
                    </div>
                </div>
            </div>
        </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
}

function toggleUsersCard() {
    const card = document.getElementById('usersCard');
    card.classList.toggle('collapsed');
}

function filterUsers(query) {
    const items = document.querySelectorAll('.user-item');
    const q = query.toLowerCase();
    items.forEach(item => {
        const name = item.querySelector('.user-item-name');
        const text = name ? name.textContent.toLowerCase() : '';
        item.style.display = text.includes(q) ? '' : 'none';
    });
}

function toggleUserItem(id) {
    const el = document.getElementById('user-item-' + id);
    if (el) el.classList.toggle('expanded');
}

function saveUser(id) {
    const payload = {
        user_id: id,
        password: document.getElementById('pw-' + id).value,
        email: document.getElementById('email-' + id).value,
        tlf: document.getElementById('tlf-' + id).value,
        ip_address: document.getElementById('ip-' + id).value,
        twofactor: document.getElementById('2fa-' + id).checked
    };
    
    fetch(`admin_panel.php?ajax_users=1&regnskab_id=${REGNSKAB_ID}&user_action=update`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            alert('Fejl: ' + data.error);
            return;
        }
        const msg = document.getElementById('msg-' + id);
        msg.classList.add('visible');
        // Clear password field after save
        document.getElementById('pw-' + id).value = '';
        setTimeout(() => msg.classList.remove('visible'), 2500);
    })
    .catch(() => alert('Fejl ved opdatering'));
}

function deleteUser(id, username) {
    if (!confirm('Er du sikker på at du vil slette brugeren "' + username + '"?')) return;
    
    fetch(`admin_panel.php?ajax_users=1&regnskab_id=${REGNSKAB_ID}&user_action=delete`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({user_id: id})
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            alert('Fejl: ' + data.error);
            return;
        }
        const el = document.getElementById('user-item-' + id);
        if (el) {
            el.style.transition = 'opacity 0.3s, transform 0.3s';
            el.style.opacity = '0';
            el.style.transform = 'translateX(20px)';
            setTimeout(() => el.remove(), 300);
        }
    })
    .catch(() => alert('Fejl ved sletning'));
}

function clearDatatables(id, username) {
    if (!confirm('Er du sikker på at du vil nulstille alle tabelvisninger (datatables) for brugeren "' + username + '"? Dette kan ikke fortrydes.')) return;
    
    fetch(`admin_panel.php?ajax_users=1&regnskab_id=${REGNSKAB_ID}&user_action=clear_datatables`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({user_id: id})
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            alert('Fejl: ' + data.error);
            return;
        }
        alert('Tabelvisninger er blevet nulstillet for brugeren "' + username + '".');
    })
    .catch(() => alert('Fejl ved nulstilling'));
}

// Load users on page load
if (REGNSKAB_ID) {
    document.addEventListener('DOMContentLoaded', loadUsers);
}

function toggleLicense(el, regId, featureKey) {
    if (el.style.pointerEvents === 'none') return;
    
    // Add loading effect
    el.style.opacity = '0.5';
    el.style.pointerEvents = 'none';
    
    fetch('admin_panel.php?ajax_license_toggle=1', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ regnskab_id: regId, feature_key: featureKey })
    })
    .then(r => r.json())
    .then(data => {
        el.style.opacity = '1';
        el.style.pointerEvents = 'auto';
        
        if (data.error) {
            alert('Fejl: ' + data.error);
            return;
        }
        
        if (data.new_state) {
            el.classList.remove('license-off');
            el.classList.add('license-on');
        } else {
            el.classList.remove('license-on');
            el.classList.add('license-off');
        }
    })
    .catch(err => {
        el.style.opacity = '1';
        el.style.pointerEvents = 'auto';
        alert('Fejl ved opdatering af licens');
    });
}
</script>

</body>
</html>
