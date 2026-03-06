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
function fetch_customer_invoices($regnskab_name) {
    $token = get_saldi_api_token();
    if (!$token) return ['error' => 'Kunne ikke logge ind på Saldi API'];
    
    // First, search for the customer by name
    $customers = fetch_saldi_api('/debitor/customers/index.php', $token, [
        'search' => $regnskab_name,
        'limit' => 1
    ]);
    
    if ($customers === null) {
        return ['error' => 'Kunne ikke hente kunde fra API'];
    }
    
    if (!is_array($customers) || count($customers) === 0 || !isset($customers[0]['kontonr'])) {
        return ['error' => 'Ingen kunde fundet for "' . htmlspecialchars($regnskab_name) . '"'];
    }
    
    $customer_id = $customers[0]['kontonr'];
    
    // Fetch recent invoices for this customer
    $invoices = fetch_saldi_api('/debitor/invoices/index.php', $token, [
        'customer' => $customer_id,
        'limit' => 10,
        'page' => 1
    ]);
    
    if ($invoices === null) return ['error' => 'Kunne ikke hente fakturaer fra API'];
    if (!is_array($invoices) || count($invoices) === 0) return ['error' => 'Ingen fakturaer fundet for "' . htmlspecialchars($regnskab_name) . '"'];
    
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
        * { box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Arial, Helvetica, sans-serif; 
            padding: 0; margin: 0;
            background: #f0f2f5; 
            color: #333;
        }
        
        /* Top header bar */
        .top-bar {
            background: linear-gradient(135deg, #114691, #1a5bb5);
            color: white;
            padding: 16px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .top-bar h1 { margin: 0; font-size: 22px; font-weight: 600; letter-spacing: 0.5px; }
        .top-bar a { color: rgba(255,255,255,0.85); text-decoration: none; font-size: 14px; transition: color 0.2s; }
        .top-bar a:hover { color: #fff; }
        
        .container { max-width: 1300px; margin: 0 auto; padding: 24px 30px; }
        
        /* Message banner */
        .message { 
            background: #d4edda; border: 1px solid #c3e6cb; color: #155724; 
            padding: 14px 20px; border-radius: 8px; margin-bottom: 20px;
            font-size: 14px; display: flex; align-items: center; gap: 8px;
        }
        .message.error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        
        /* Search / Filter bar */
        .filter-bar {
            background: white;
            padding: 16px 20px;
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .filter-bar input[type="text"] {
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            width: 300px;
            transition: border-color 0.2s;
        }
        .filter-bar input[type="text"]:focus { border-color: #114691; outline: none; }
        
        /* Buttons */
        .btn {
            padding: 10px 20px;
            background: #114691;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s, transform 0.1s;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover { background: #0d3a7a; transform: translateY(-1px); }
        .btn:active { transform: translateY(0); }
        .btn-small { padding: 6px 14px; font-size: 13px; }
        .btn-outline { 
            background: transparent; color: #114691; 
            border: 1px solid #114691; 
        }
        .btn-outline:hover { background: #114691; color: white; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        
        /* Overview table */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        .data-table th {
            background: #114691;
            color: white;
            padding: 14px 16px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        .data-table tbody tr { transition: background 0.15s; }
        .data-table tbody tr:hover { background: #f7f9fc; }
        .data-table tbody tr:last-child td { border-bottom: none; }
        
        /* Status badges */
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-active { background: #d4edda; color: #155724; }
        .badge-closed { background: #f8d7da; color: #721c24; }
        .badge-expired { background: #fff3cd; color: #856404; }
        
        .license-icons { display: flex; gap: 6px; }
        .license-icon {
            width: 24px; height: 24px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px;
            font-weight: bold;
        }
        .license-on { background: #d4edda; color: #155724; }
        .license-off { background: #f8d7da; color: #721c24; }
        
        /* Detail view cards */
        .breadcrumb {
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }
        .breadcrumb a { color: #114691; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        
        .detail-header {
            background: white;
            border-radius: 10px;
            padding: 24px 28px;
            margin-bottom: 20px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .detail-header h2 { margin: 0 0 4px 0; font-size: 20px; color: #333; }
        .detail-header .subtitle { color: #888; font-size: 14px; }
        
        .cards-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .card-header {
            padding: 16px 22px;
            border-bottom: 1px solid #f0f0f0;
            font-weight: 600;
            font-size: 15px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-body { padding: 20px 22px; }
        .card-full { grid-column: 1 / -1; }
        
        /* Info rows in cards */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f7f7f7;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #888; font-size: 13px; font-weight: 500; }
        .info-value { font-size: 14px; color: #333; font-weight: 500; }
        
        /* Usage bars */
        .usage-bar-wrap {
            margin-top: 6px;
            background: #eee;
            border-radius: 10px;
            height: 8px;
            overflow: hidden;
            width: 200px;
        }
        .usage-bar {
            height: 100%;
            border-radius: 10px;
            transition: width 0.4s ease;
        }
        .usage-bar.green { background: linear-gradient(90deg, #28a745, #34d058); }
        .usage-bar.yellow { background: linear-gradient(90deg, #ffc107, #ffca2c); }
        .usage-bar.red { background: linear-gradient(90deg, #dc3545, #e4606d); }
        
        /* Feature toggles */
        .feature-row {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 12px 0;
            border-bottom: 1px solid #f7f7f7;
        }
        .feature-row:last-child { border-bottom: none; }
        .feature-name { width: 180px; font-weight: 500; font-size: 14px; }
        
        /* Toggle switch */
        .toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute; cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #ccc; border-radius: 24px;
            transition: 0.3s;
        }
        .toggle-slider:before {
            position: absolute; content: "";
            height: 18px; width: 18px;
            left: 3px; bottom: 3px;
            background: white; border-radius: 50%;
            transition: 0.3s;
        }
        .toggle-switch input:checked + .toggle-slider { background: #28a745; }
        .toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }
        
        /* Form inputs in cards */
        .form-group { margin-bottom: 16px; }
        .form-group label { 
            display: block; margin-bottom: 6px; 
            font-size: 13px; font-weight: 500; color: #666; 
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="date"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-group input:focus { border-color: #114691; outline: none; }
        
        .checkbox-group {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 0;
        }
        .checkbox-group input[type="checkbox"] { width: 18px; height: 18px; accent-color: #114691; }
        .checkbox-group label { font-size: 14px; color: #333; cursor: pointer; }
        
        .stats-number {
            font-size: 28px;
            font-weight: 700;
            color: #114691;
        }
        .stats-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            text-align: center;
        }
        .stats-item {
            padding: 16px;
            background: #f7f9fc;
            border-radius: 8px;
        }
        /* Payment card styles */
        .payment-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .payment-table th {
            text-align: left;
            padding: 8px 10px;
            background: #f7f9fc;
            color: #666;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 2px solid #e9ecef;
        }
        .payment-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
        }
        .payment-table tr:last-child td { border-bottom: none; }
        .payment-table tr:hover { background: #fafbfd; }
        .badge-paid { background: #d4edda; color: #155724; }
        .badge-unpaid { background: #fff3cd; color: #856404; }
        .api-error {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 14px 18px;
            border-radius: 8px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .payment-highlight {
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
        }
        .payment-highlight .amount {
            font-size: 24px;
            font-weight: 700;
            color: #16a34a;
        }
        .payment-highlight .label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Invoice Preview Modal */
        .invoice-preview-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.4);
            z-index: 1000;
            backdrop-filter: blur(2px);
            align-items: center; justify-content: center;
        }
        .invoice-preview-modal {
            background: white;
            border-radius: 12px;
            width: 700px;
            max-width: 90vw;
            max-height: 85vh;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transform: translateY(20px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .invoice-preview-backdrop.visible { display: flex; }
        .invoice-preview-backdrop.visible .invoice-preview-modal {
            transform: translateY(0); opacity: 1;
        }
        
        .inv-modal-header {
            padding: 20px 24px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            display: flex; justify-content: space-between; align-items: flex-start;
        }
        .inv-modal-title { font-size: 20px; font-weight: 600; color: #333; margin: 0 0 4px 0; }
        .inv-modal-info { color: #666; font-size: 14px; }
        .inv-modal-close {
            background: none; border: none; font-size: 24px; color: #999;
            cursor: pointer; line-height: 1; min-width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%; transition: 0.2s;
        }
        .inv-modal-close:hover { background: #eee; color: #333; }
        
        .inv-modal-body {
            padding: 24px;
            overflow-y: auto;
            flex-grow: 1;
        }
        .inv-lines-table {
            width: 100%; border-collapse: collapse; margin-bottom: 24px;
        }
        .inv-lines-table th {
            text-align: left; padding: 10px;
            border-bottom: 2px solid #ddd;
            color: #555; font-size: 13px; text-transform: uppercase; font-weight: 600;
        }
        .inv-lines-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #efefef;
            font-size: 14px; color: #333;
        }
        .inv-lines-table .num { text-align: right; }
        .inv-totals {
            width: 300px; margin-left: auto;
            background: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 16px;
        }
        .inv-totals-row {
            display: flex; justify-content: space-between;
            padding: 6px 0; font-size: 14px; color: #555;
        }
        .inv-totals-row.bold {
            font-weight: 700; color: #333; font-size: 16px;
            border-top: 2px solid #ddd; margin-top: 6px; padding-top: 10px;
        }
        
        .inv-loading {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 40px; color: #777;
        }
        .spinner {
            width: 40px; height: 40px; border: 4px solid #f3f3f3;
            border-top: 4px solid #114691; border-radius: 50%;
            animation: spin 1s linear infinite; margin-bottom: 16px;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .invoice-row-trigger { cursor: pointer; transition: background 0.15s; }
        .invoice-row-trigger:hover { background: #eef2f7 !important; }
        .payment-highlight.invoice-row-trigger:hover {
            border-color: #4ade80; box-shadow: 0 4px 12px rgba(22, 163, 74, 0.15);
        }
    </style>
</head>
<body>

<div class="top-bar">
    <h1>🛡️ Admin Panel</h1>
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
        
        // Try to get actual user count and transaction count from client database
        $actual_brugere = '?';
        $actual_transaktioner = '?';
        $client_db = $reg['db'];
        
        if ($client_db && $client_db != $sqdb) {
            // Connect to client DB
            $client_conn = @db_connect($sqhost, $squser, $sqpass, $client_db, __FILE__ . " linje " . __LINE__);
            if ($client_conn) {
                // Count brugere
                $qtxt = "SELECT count(*) as cnt FROM brugere";
                $qr = @db_select($qtxt, __FILE__ . " linje " . __LINE__);
                if ($qr && $rr = db_fetch_array($qr)) {
                    $actual_brugere = $rr['cnt'] * 1;
                }
                
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
                    <div class="stats-label">Brugere</div>
                    <div class="usage-bar-wrap" style="margin: 8px auto 0;">
                        <div class="usage-bar <?php echo bar_color($brugere_pct); ?>" style="width: <?php echo $brugere_pct; ?>%"></div>
                    </div>
                </div>
                <div class="stats-item">
                    <div class="stats-number"><?php echo number_format($posteret, 0, ',', '.'); ?><span style="font-size:16px;color:#999;"> / <?php echo number_format($max_posteringer, 0, ',', '.'); ?></span></div>
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
        
        <!-- Seneste Betaling (fra Saldi API) -->
        <div class="card">
            <div class="card-header">💳 Seneste Betaling</div>
            <div class="card-body">
                <?php
                $payment_data = fetch_customer_invoices($reg['regnskab']);
                
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
                    $latest_ordrenr = $latest['orderNo'] ?? '-';
                    
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
                                $show_count = min(5, count($invoices));
                                for ($i = 0; $i < $show_count; $i++) {
                                    $inv = $invoices[$i];
                                    $inv_date = $inv['invoiceDate'] ? date('d-m-Y', strtotime($inv['invoiceDate'])) : '-';
                                    $inv_total = number_format(($inv['economic']['sum'] ?? 0) + ($inv['economic']['vat'] ?? 0), 2, ',', '.');
                                    $inv_paid = $inv['paid'] == '1' || $inv['paid'] === true;
                                    ?>
                                    <tr class="invoice-row-trigger" onclick="openInvoiceModal(<?php echo $inv['id']; ?>)">
                                        <td><?php echo $inv['orderNo'] ?? '-'; ?></td>
                                        <td><?php echo $inv_date; ?></td>
                                        <td style="font-weight: 600;"><?php echo $inv_total; ?></td>
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
                    </div>
                    <?php } ?>
                    
                <?php } ?>
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
            <button type="submit" class="btn btn-small">Søg</button>
            <?php if (if_isset($_GET['search'], '')) { ?>
                <a href="admin_panel.php" class="btn btn-small btn-outline">Nulstil</a>
            <?php } ?>
        </form>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Regnskab</th>
                <th>Database</th>
                <th>Brugere</th>
                <th>Posteringer</th>
                <th>Posteret</th>
                <th>Sidst aktiv</th>
                <th>Status</th>
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
            
            $qtxt = "SELECT * FROM regnskab $where ORDER BY regnskab";
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
                echo "<td>" . number_format($reg['posteringer'] * 1, 0, ',', '.') . "</td>";
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
                    echo "<span class='license-icon $status_class' title='" . htmlspecialchars($fn) . "'>$short</span>";
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
            
            metaContainer.innerHTML = `Faktura #${inv.orderNo || '-'} &nbsp;&bull;&nbsp; Dato: ${dateStr}`;
            
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
                    if (!line.description && !line.itemNo) return;
                    
                    const qty = line.qty !== null && line.qty !== undefined ? line.qty : '';
                    const price = line.price ? numberFormat(line.price) : '';
                    const total = line.total ? numberFormat(line.total) : '';
                    
                    linesHtml += `
                        <tr>
                            <td style="width: 15%;">${line.itemNo || ''}</td>
                            <td style="width: 40%;">${line.description || ''}</td>
                            <td class="num" style="width: 15%;">${qty} ${line.unit || ''}</td>
                            <td class="num" style="width: 15%;">${price}</td>
                            <td class="num" style="width: 15%; font-weight: 500;">${total}</td>
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
</script>

</body>
</html>
