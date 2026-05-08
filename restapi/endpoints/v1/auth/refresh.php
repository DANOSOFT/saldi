<?php
/**
 * POST /auth/refresh
 * Refresh access token using refresh token
 * 
 * Request body:
 * {
 *   "refresh_token": "refresh_jwt_token"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": {
 *     "access_token": "new_jwt_token",
 *     "token_type": "Bearer",
 *     "expires_in": 3600
 *   }
 * }
 */

require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../core/JWT.php';
require_once __DIR__ . '/../../../core/logging.php';

include_once __DIR__ . '/../../../../includes/db_query.php';
include_once __DIR__ . '/../../../../includes/connect.php';

class AuthRefreshEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }
    
    protected function checkAuthorization()
    {
        // Refresh endpoint doesn't require authentication (it uses refresh token)
        return true;
    }
    
    protected function handlePost($data)
    {
        if (!isset($data->refresh_token)) {
            $this->sendResponse(false, null, 'Refresh token is required', 400);
            return;
        }
        
        $refreshToken = $data->refresh_token;
        $payload = JWT::decode($refreshToken);
        
        if (!$payload) {
            $this->sendResponse(false, null, 'Invalid or expired refresh token', 401);
            return;
        }
        
        if (!isset($payload['type']) || $payload['type'] !== 'refresh') {
            $this->sendResponse(false, null, 'Invalid token type', 401);
            return;
        }
        
        // Verify user still exists
        global $sqhost, $squser, $sqpass, $sqdb;
        $connection = db_connect($sqhost, $squser, $sqpass, $sqdb, __FILE__ . " linje " . __LINE__);
        
        if (!$connection) {
            $this->sendResponse(false, null, 'Database connection failed', 500);
            return;
        }
        
        $user_id = (int)$payload['user_id'];
        $qtxt = "select * from brugere where id='$user_id' limit 1";
        $user = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        
        if (!$user) {
            $this->sendResponse(false, null, 'User not found', 401);
            return;
        }
        
        // Generate new access token
        $accessTokenPayload = [
            'user_id' => $user['id'],
            'username' => $user['brugernavn'],
            'type' => 'access'
        ];
        
        $accessToken = JWT::encode($accessTokenPayload, 3600); // 1 hour
        
        $response = [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600
        ];
        
        write_log("Token refreshed for user: {$user['brugernavn']} (ID: {$user['id']})", '', 'INFO');
        
        $this->sendResponse(true, $response, 'Token refreshed successfully', 200);
    }
    
    protected function handleGet($id = null)
    {
        $this->sendResponse(false, null, 'GET method not supported. Use POST to refresh token.', 405);
    }
}

$endpoint = new AuthRefreshEndpoint();
$endpoint->handleRequestMethod();

