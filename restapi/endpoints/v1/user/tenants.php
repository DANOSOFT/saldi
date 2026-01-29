<?php
/**
 * GET /user/tenants
 * Get list of accessible regnskaber (tenants) for the authenticated user
 * 
 * Headers:
 * - Authorization: Bearer {access_token}
 * 
 * Response:
 * {
 *   "success": true,
 *   "data": [
 *     {
 *       "id": 1,
 *       "name": "Regnskab Navn",
 *       "db": "database_name",
 *       "cvr": "12345678",
 *       "email": "email@example.com",
 *       "closed": false
 *     }
 *   ]
 * }
 */

require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../core/JWT.php';
require_once __DIR__ . '/../../../core/logging.php';

include_once __DIR__ . '/../../../../includes/db_query.php';
include_once __DIR__ . '/../../../../includes/connect.php';

class UserTenantsEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }
    
    protected function checkAuthorization()
    {
        // Get Authorization header
        $headers = getallheaders();
        $headers = array_change_key_case($headers, CASE_LOWER);
        
        if (!isset($headers['authorization'])) {
            $this->sendResponse(false, null, 'Authorization header required', 401);
            return false;
        }
        
        $authHeader = $headers['authorization'];
        
        // Extract token from "Bearer {token}"
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        } else {
            $this->sendResponse(false, null, 'Invalid authorization format. Use: Bearer {token}', 401);
            return false;
        }
        
        $payload = JWT::decode($token);
        
        if (!$payload) {
            $this->sendResponse(false, null, 'Invalid or expired token', 401);
            return false;
        }
        
        if (!isset($payload['type']) || $payload['type'] !== 'access') {
            $this->sendResponse(false, null, 'Invalid token type', 401);
            return false;
        }
        
        // Store user info for later use
        $this->userId = $payload['user_id'];
        $this->username = $payload['username'];
        
        return true;
    }
    
    protected function handleGet($id = null)
    {
        global $sqhost, $squser, $sqpass, $sqdb;
        $connection = db_connect($sqhost, $squser, $sqpass, $sqdb, __FILE__ . " linje " . __LINE__);
        
        if (!$connection) {
            $this->sendResponse(false, null, 'Database connection failed', 500);
            return;
        }
        
        // Get user info
        $user_id = $this->userId;
        $qtxt = "select * from brugere where id='$user_id' limit 1";
        $user = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        
        if (!$user) {
            $this->sendResponse(false, null, 'User not found', 404);
            return;
        }
        
        // Check if user is admin
        $is_admin = (strpos($user['rettigheder'], 'admin') !== false || $user['rettigheder'] == '*');
        
        // Get accessible regnskaber
        $adgang_til = isset($user['adgang_til']) ? trim($user['adgang_til']) : '';
        
        if ($is_admin) {
            // Admin has access to all non-closed regnskaber
            $qtxt = "select * from regnskab where db != '$sqdb' and (lukket != 'on' or lukket is null) order by regnskab";
        } else {
            if (empty($adgang_til)) {
                // User has no specific access
                $this->sendResponse(true, [], 'No accessible regnskaber found', 200);
                return;
            }
            
            $adgang_til = db_escape_string($adgang_til);
            $qtxt = "select * from regnskab where id in ($adgang_til) and (lukket != 'on' or lukket is null) order by regnskab";
        }
        
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        $tenants = [];
        
        while ($r = db_fetch_array($q)) {
            // Get CVR from adresser table if available
            $cvr = '';
            $email = isset($r['email']) ? $r['email'] : '';
            
            if ($r['db'] != $sqdb) {
                $db_conn = db_connect($sqhost, $squser, $sqpass, $r['db'], __FILE__ . " linje " . __LINE__);
                if ($db_conn) {
                    $adr_qtxt = "select cvrnr, email from adresser where art='S' limit 1";
                    $adr_r = db_fetch_array(db_select($adr_qtxt, __FILE__ . " linje " . __LINE__));
                    if ($adr_r) {
                        $cvr = isset($adr_r['cvrnr']) ? trim($adr_r['cvrnr']) : '';
                        if (empty($email) && isset($adr_r['email'])) {
                            $email = $adr_r['email'];
                        }
                    }
                }
            }
            
            $tenants[] = [
                'id' => (int)$r['id'],
                'name' => $r['regnskab'],
                'db' => $r['db'],
                'cvr' => $cvr,
                'email' => $email,
                'closed' => ($r['lukket'] == 'on')
            ];
        }
        
        $this->sendResponse(true, $tenants, 'Tenants retrieved successfully', 200);
    }
    
    protected function handlePost($data)
    {
        $this->sendResponse(false, null, 'POST method not supported', 405);
    }
}

$endpoint = new UserTenantsEndpoint();
$endpoint->handleRequestMethod();

