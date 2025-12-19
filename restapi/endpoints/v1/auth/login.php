<?php
/**
 * POST /auth/login
 * OAuth2/JWT Login endpoint
 * 
 * Request body:
 * {
 *   "username": "brugernavn",
 *   "password": "password",
 *   "account_name": "account_name" (REQUIRED - account name matching regnskab column)
 * }
 * 
 * IMPORTANT: Users are stored in each tenant's database, not the master database.
 * The "account_name" parameter is REQUIRED to look up the tenant database from the regnskab table.
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "access_token": "jwt_token",
 *     "refresh_token": "refresh_jwt_token",
 *     "token_type": "Bearer",
 *     "expires_in": 3600,
 *     "user": {
 *       "id": 1,
 *       "username": "brugernavn"
 *     },
 *     "tenant": {
 *       "id": 1,
 *       "name": "Regnskab Navn",
 *       "db": "database_name"
 *     }
 *   }
 * }
 */

require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../core/JWT.php';
require_once __DIR__ . '/../../../core/logging.php';

include_once __DIR__ . '/../../../../includes/db_query.php';
include_once __DIR__ . '/../../../../includes/connect.php';
include_once __DIR__ . '/../../../../includes/std_func.php';

class AuthLoginEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }
    
    protected function checkAuthorization()
    {
        // Login endpoint doesn't require authentication
        return true;
    }
    
    protected function handlePost($data)
    {
        if (!isset($data->username) || !isset($data->password)) {
            $this->sendResponse(false, null, 'Username and password are required', 400);
            return;
        }
        
        if (!isset($data->account_name) || empty($data->account_name)) {
            $this->sendResponse(false, null, 'Account name parameter is required.', 400);
            return;
        }
        
        $username = db_escape_string($data->username);
        $password = $data->password;
        $account_name = trim($data->account_name);
        
        // First, connect to master database to look up tenant information
        global $sqhost, $squser, $sqpass, $sqdb;
        $master_connection = db_connect($sqhost, $squser, $sqpass, $sqdb, __FILE__ . " linje " . __LINE__);
        
        if (!$master_connection) {
            $this->sendResponse(false, null, 'Master database connection failed', 500);
            return;
        }
        
        // Find tenant by account_name (regnskab column)
        $account_name_escaped = db_escape_string($account_name);
        $tenant_query = "select * from regnskab where regnskab='$account_name_escaped' limit 1";
        $tenant_result = db_fetch_array(db_select($tenant_query, __FILE__ . " linje " . __LINE__));
        
        if (!$tenant_result) {
            $this->sendResponse(false, null, 'Account not found', 404);
            return;
        }
        
        // Check if tenant is closed
        if ($tenant_result['lukket'] == 'on') {
            $this->sendResponse(false, null, 'Tenant account is closed', 403);
            return;
        }
        
        $tenant = [
            'id' => (int)$tenant_result['id'],
            'name' => $tenant_result['regnskab'],
            'db' => $tenant_result['db']
        ];
        
        $tenant_db = $tenant_result['db'];
        
        // Now connect to the tenant database to check user
        $tenant_connection = db_connect($sqhost, $squser, $sqpass, $tenant_db, __FILE__ . " linje " . __LINE__);
        
        if (!$tenant_connection) {
            $this->sendResponse(false, null, 'Tenant database connection failed', 500);
            return;
        }
        
        // Find user in tenant database (case-insensitive)
        $asIs = db_escape_string($username);
        $low = strtolower($username);
        $low = str_replace(['Æ', 'Ø', 'Å', 'É'], ['æ', 'ø', 'å', 'é'], $low);
        $low = db_escape_string($low);
        $up = strtoupper($username);
        $up = str_replace(['æ', 'ø', 'å', 'é'], ['Æ', 'Ø', 'Å', 'É'], $up);
        $up = db_escape_string($up);
        
        $qtxt = "select * from brugere where brugernavn='$asIs' or lower(brugernavn)='$low' or upper(brugernavn)='$up' limit 1";
        $user = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        
        if (!$user) {
            write_log("Login failed: User not found: $username in database: $tenant_db", $tenant_db, 'WARNING');
            $this->sendResponse(false, null, 'Invalid username or password', 401);
            return;
        }
        
        // Verify password
        $pw1 = md5($password);
        $pw2 = saldikrypt($user['id'], $password);
        
        if ($user['kode'] != $pw1 && $user['kode'] != $pw2) {
            // Check for temporary password
            if (isset($user['tmp_kode'])) {
                list($tidspkt, $tmp_kode) = explode("|", $user['tmp_kode']);
                if (date("U") <= $tidspkt && $tmp_kode == $password) {
                    // Temporary password is valid
                } else {
                    write_log("Login failed: Invalid password for user: $username in database: $tenant_db", $tenant_db, 'WARNING');
                    $this->sendResponse(false, null, 'Invalid username or password', 401);
                    return;
                }
            } else {
                write_log("Login failed: Invalid password for user: $username in database: $tenant_db", $tenant_db, 'WARNING');
                $this->sendResponse(false, null, 'Invalid username or password', 401);
                return;
            }
        }
        
        // If user is admin, they have access to all regnskaber
        $is_admin = (strpos($user['rettigheder'], 'admin') !== false || $user['rettigheder'] == '*');
        
        // Create JWT tokens (always include tenant_id since database is required)
        $accessTokenPayload = [
            'user_id' => $user['id'],
            'username' => $user['brugernavn'],
            'type' => 'access',
            'tenant_id' => $tenant['id']
        ];
        
        $refreshTokenPayload = [
            'user_id' => $user['id'],
            'username' => $user['brugernavn'],
            'type' => 'refresh'
        ];
        
        $accessToken = JWT::encode($accessTokenPayload, 3600); // 1 hour
        $refreshToken = JWT::encode($refreshTokenPayload, 86400 * 30); // 30 days
        
        $logMsg = "Login successful for user: $username (ID: {$user['id']}) with tenant: {$tenant['name']} (ID: {$tenant['id']}, DB: {$tenant['db']})";
        write_log($logMsg, $tenant_db, 'INFO');
        
        $response = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['brugernavn'],
                'is_admin' => $is_admin
            ],
            'tenant' => $tenant
        ];
        
        $this->sendResponse(true, $response, 'Login successful', 200);
    }
    
    protected function handleGet($id = null)
    {
        $this->sendResponse(false, null, 'GET method not supported. Use POST to login.', 405);
    }
}

$endpoint = new AuthLoginEndpoint();
$endpoint->handleRequestMethod();

