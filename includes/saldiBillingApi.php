<?php
// --- includes/saldiBillingApi.php --- ver 5.1.0 --- 2026.10.23 ---
// LICENSE: GNU GPL v2 or later; see admin/vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261010 CL/ASR Moved unchanged from admin/admin_panel.php so the nightly job and the operator overview can
//                  use the same REST client against the billing ledger on ssl3 (settings var_grp='internal_api').
// 20261012 CL/ASR The billing ledger's URL comes from settings too (var_name 'saldi_api_base', same group) with the
//                  old constant as default, so nothing hardcodes the billing ledger (owner's decision 12/10-2026);
//                  the customer-side invoice overview (billing overview spec) reuses this client and these settings.
//                  TLS certificates are now verified (verification was off; the billing ledger has a valid certificate).
if (!defined('SALDI_API_BASE')) {
// ---- REST API Configuration for the billing ledger (default ssl3.saldi.dk) ----
$query = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name = 'saldi_api_base' AND var_grp = 'internal_api'", __FILE__ . " linje " . __LINE__));
$saldi_api_base = ($query && trim((string)$query['var_value']) !== '') ? rtrim(trim($query['var_value']), '/') : 'https://ssl3.saldi.dk/finans/restapi/endpoints/v1';
// 20261013 CL/ASR Credentials are only sent over HTTPS to a saldi.dk host; anything else falls back to the default (review #785).
if (!preg_match('#^https://([a-z0-9-]+\.)*saldi\.dk(:\d+)?/#i', $saldi_api_base.'/')) $saldi_api_base = 'https://ssl3.saldi.dk/finans/restapi/endpoints/v1';
$query = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name = 'saldi_api_user' AND var_grp = 'internal_api'", __FILE__ . " linje " . __LINE__));
$saldi_api_user = $query['var_value'];
$query = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name = 'saldi_api_pass' AND var_grp = 'internal_api'", __FILE__ . " linje " . __LINE__));
$saldi_api_pass = $query['var_value'];
$query = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name = 'saldi_api_account' AND var_grp = 'internal_api'", __FILE__ . " linje " . __LINE__));
$saldi_api_account = $query['var_value'];
define('SALDI_API_BASE', $saldi_api_base);
define('SALDI_API_USER', $saldi_api_user);
define('SALDI_API_PASS', $saldi_api_pass);
define('SALDI_API_ACCOUNT', $saldi_api_account);
// 20261023 CL/ASR The token cache is a 0700 directory of its own per system user (web server, cron) with a file that is
//                  created 0600 before the token is written and then renamed into place, so the token is never readable by
//                  other local users, not even for a moment (review #785). A symlink, another owner or a wider mode on the
//                  directory means: no cache, log in every time.
define('SALDI_API_TOKEN_DIR', rtrim(sys_get_temp_dir(), '/').'/saldi_api_token'.(function_exists('posix_geteuid') ? '_'.posix_geteuid() : ''));
define('SALDI_API_TOKEN_FILE', SALDI_API_TOKEN_DIR.'/token.json');

/**
 * Get a cached or fresh JWT token from the Saldi REST API
 */
// 20261021 CL/ASR The cached token is bound to the whole API identity (base URL, user, password, account), so changed
//                  credentials never reuse an old token; the cache file is per system user and readable by its owner only.
function saldi_api_identity() { return hash('sha256', SALDI_API_BASE."\n".SALDI_API_USER."\n".SALDI_API_PASS."\n".SALDI_API_ACCOUNT); }
/** The cache directory exists, is a real directory owned by this user and is 0700; otherwise the cache is not used. */
function saldi_api_token_dir_ok() {
    $dir = SALDI_API_TOKEN_DIR;
    clearstatcache(true, $dir); # judge the path as it is now, not as an earlier call in this request saw it
    if (!is_link($dir) && !is_dir($dir)) {
        $old = umask(0077);
        @mkdir($dir, 0700);
        umask($old);
    }
    clearstatcache(true, $dir);
    if (is_link($dir) || !is_dir($dir)) return false;
    if (function_exists('posix_geteuid') && fileowner($dir) !== posix_geteuid()) return false;
    if ((fileperms($dir) & 0777) !== 0700 && !@chmod($dir, 0700)) return false;
    return true;
}
/** The cached token, when the cache is sound, not expired and from the same API identity; otherwise null. */
function saldi_api_token_cache_read() {
    if (!saldi_api_token_dir_ok() || is_link(SALDI_API_TOKEN_FILE) || !is_file(SALDI_API_TOKEN_FILE)) return null;
    $cached = json_decode((string)@file_get_contents(SALDI_API_TOKEN_FILE), true);
    if ($cached && isset($cached['token']) && isset($cached['expires']) && $cached['expires'] > time() && ($cached['id'] ?? '') === saldi_api_identity()) { # token is bound to the base URL and credentials it came from
        return $cached['token'];
    }
    return null;
}
/** Writes the token to a file that is 0600 from the moment it exists, then renames it into place (atomic replace). */
function saldi_api_token_cache_write($token) {
    if (!saldi_api_token_dir_ok()) return false;
    $tmp = SALDI_API_TOKEN_FILE.'.'.getmypid().'.tmp';
    $old = umask(0077);          # born 0600: there is no moment where the file is wider than that
    $fh = @fopen($tmp, 'x');     # 'x' creates a new file only; an existing path or symlink is refused
    umask($old);
    if (!$fh) return false;
    $json = json_encode(['token' => $token, 'id' => saldi_api_identity(), 'expires' => time() + 3300]); # expires in 55 min to be safe
    $ok = @chmod($tmp, 0600) && fwrite($fh, $json) !== false;
    fclose($fh);
    if (!$ok || !@rename($tmp, SALDI_API_TOKEN_FILE)) {
        @unlink($tmp);
        return false;
    }
    return true;
}
function get_saldi_api_token() {
    // Check for cached token
    $cached = saldi_api_token_cache_read();
    if ($cached !== null) return $cached;
    
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
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);
    
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if (!$response || $httpCode !== 200) return null;
    
    $data = json_decode($response, true);
    if (!$data || !$data['success'] || !isset($data['data']['access_token'])) return null;
    
    $token = $data['data']['access_token'];
    
    saldi_api_token_cache_write($token); // a cache that cannot be written safely is simply not used
    
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
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
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
