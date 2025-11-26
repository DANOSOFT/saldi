<?php
/**
 * POST /auth/login
 * OAuth2/JWT Login endpoint
 * 
 * Request body:
 * {
 *   "username": "brugernavn",
 *   "password": "password"
 * }
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
        
        $username = db_escape_string($data->username);
        $password = $data->password;
        
        // Connect to master database to check user
        global $sqhost, $squser, $sqpass, $sqdb;
        $connection = db_connect($sqhost, $squser, $sqpass, $sqdb, __FILE__ . " linje " . __LINE__);
        
        if (!$connection) {
            $this->sendResponse(false, null, 'Database connection failed', 500);
            return;
        }
        
        // Find user (case-insensitive)
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
            write_log("Login failed: User not found: $username", '', 'WARNING');
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
                    write_log("Login failed: Invalid password for user: $username", '', 'WARNING');
                    $this->sendResponse(false, null, 'Invalid username or password', 401);
                    return;
                }
            } else {
                write_log("Login failed: Invalid password for user: $username", '', 'WARNING');
                $this->sendResponse(false, null, 'Invalid username or password', 401);
                return;
            }
        }
        
        // Get user's accessible regnskaber (tenants)
        $adgang_til = isset($user['adgang_til']) ? $user['adgang_til'] : '';
        $tenant_ids = $adgang_til ? explode(',', $adgang_til) : [];
        
        // If user is admin, they have access to all regnskaber
        $is_admin = (strpos($user['rettigheder'], 'admin') !== false || $user['rettigheder'] == '*');
        
        // Create JWT tokens
        $accessTokenPayload = [
            'user_id' => $user['id'],
            'username' => $user['brugernavn'],
            'type' => 'access'
        ];
        
        $refreshTokenPayload = [
            'user_id' => $user['id'],
            'username' => $user['brugernavn'],
            'type' => 'refresh'
        ];
        
        $accessToken = JWT::encode($accessTokenPayload, 3600); // 1 hour
        $refreshToken = JWT::encode($refreshTokenPayload, 86400 * 30); // 30 days
        
        write_log("Login successful for user: $username (ID: {$user['id']})", '', 'INFO');
        
        $response = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'user' => [
                'id' => (int)$user['id'],
                'username' => $user['brugernavn'],
                'is_admin' => $is_admin
            ]
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

