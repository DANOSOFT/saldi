<?php
// --- includes/saldiBillingApi.php --- ver 5.1.0 --- 2026.10.10 ---
// LICENSE: GNU GPL v2 or later; see admin/vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261010 CL/ASR Moved unchanged from admin/admin_panel.php so the nightly job and the operator overview can
//                  use the same REST client against the billing ledger on ssl3 (settings var_grp='internal_api').
if (!defined('SALDI_API_BASE')) {
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

}
?>
