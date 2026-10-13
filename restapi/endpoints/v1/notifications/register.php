<?php
/**
 * POST /notifications/register - Register device token for push notifications
 * DELETE /notifications/register - Unregister device token
 */

require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../core/JWT.php';
require_once __DIR__ . '/../../../core/JWTAuth.php';
require_once __DIR__ . '/../../../core/logging.php';

include_once __DIR__ . '/../../../../includes/db_query.php';
include_once __DIR__ . '/../../../../includes/connect.php';

class NotificationsRegisterEndpoint extends BaseEndpoint
{
    private $userId;
    private $db;
    
    public function __construct()
    {
        parent::__construct();
    }
    
    protected function checkAuthorization()
    {
        $payload = JWTAuth::validateToken();
        
        if (!$payload) {
            $this->sendResponse(false, null, 'Invalid or expired token', 401);
            return false;
        }
        
        $this->userId = $payload['user_id'];
        $this->db = JWTAuth::getTenantDatabase();
        
        // Database is optional for notifications (can be global)
        return true;
    }
    
    protected function handlePost($data)
    {
        if (!isset($data->token) || empty($data->token)) {
            $this->sendResponse(false, null, 'Device token is required', 400);
            return;
        }
        
        $token = db_escape_string($data->token);
        $platform = isset($data->platform) ? db_escape_string($data->platform) : 'unknown';
        
        // Store token in database (create table if needed)
        global $sqhost, $squser, $sqpass, $sqdb;
        $conn = db_connect($sqhost, $squser, $sqpass, $sqdb, __FILE__ . " linje " . __LINE__);
        
        if (!$conn) {
            $this->sendResponse(false, null, 'Database connection failed', 500);
            return;
        }
        
        // Check if table exists, create if not
        $qtxt = "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'push_tokens')";
        $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        
        if (!$r || !$r['exists']) {
            // Create table
            $qtxt = "CREATE TABLE push_tokens (
                id serial PRIMARY KEY,
                user_id integer,
                token text NOT NULL,
                platform varchar(20),
                db_name varchar(100),
                created_at timestamp DEFAULT now(),
                UNIQUE(token, user_id, db_name)
            )";
            db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        }
        
        // Insert or update token
        $db_name = $this->db ? db_escape_string($this->db) : 'NULL';
        $qtxt = "INSERT INTO push_tokens (user_id, token, platform, db_name) 
                 VALUES ('$this->userId', '$token', '$platform', $db_name)
                 ON CONFLICT (token, user_id, db_name) 
                 DO UPDATE SET platform = '$platform', created_at = now()";
        
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        
        write_log("Push token registered for user {$this->userId}", $this->db ?: '', 'INFO');
        
        $this->sendResponse(true, ['registered' => true], 'Device token registered successfully');
    }
    
    protected function handleDelete($data)
    {
        if (!isset($data->token) && !isset($_GET['token'])) {
            $this->sendResponse(false, null, 'Device token is required', 400);
            return;
        }
        
        $token = db_escape_string($data->token ?? $_GET['token']);
        $db_name = $this->db ? db_escape_string($this->db) : 'NULL';
        
        global $sqhost, $squser, $sqpass, $sqdb;
        $conn = db_connect($sqhost, $squser, $sqpass, $sqdb, __FILE__ . " linje " . __LINE__);
        
        if (!$conn) {
            $this->sendResponse(false, null, 'Database connection failed', 500);
            return;
        }
        
        // Delete token
        if ($db_name !== 'NULL') {
            $qtxt = "DELETE FROM push_tokens WHERE token = '$token' AND user_id = '$this->userId' AND db_name = '$db_name'";
        } else {
            $qtxt = "DELETE FROM push_tokens WHERE token = '$token' AND user_id = '$this->userId' AND db_name IS NULL";
        }
        
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        
        write_log("Push token unregistered for user {$this->userId}", $this->db ?: '', 'INFO');
        
        $this->sendResponse(true, ['unregistered' => true], 'Device token unregistered successfully');
    }
}

$endpoint = new NotificationsRegisterEndpoint();
$endpoint->handleRequestMethod();

