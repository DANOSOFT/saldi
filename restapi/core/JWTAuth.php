<?php
/**
 * JWT Authentication Middleware
 * Validates JWT tokens from Authorization header
 */

require_once __DIR__ . '/JWT.php';

class JWTAuth
{
    /**
     * Validate JWT token from Authorization header
     * 
     * @return array|false User payload or false if invalid
     */
    public static function validateToken()
    {
        $headers = getallheaders();
        if (!$headers) {
            return false;
        }
        
        $headers = array_change_key_case($headers, CASE_LOWER);
        
        if (!isset($headers['authorization'])) {
            return false;
        }
        
        $authHeader = $headers['authorization'];
        
        // Extract token from "Bearer {token}"
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        } else {
            return false;
        }
        
        $payload = JWT::decode($token);
        
        if (!$payload) {
            return false;
        }
        
        if (!isset($payload['type']) || $payload['type'] !== 'access') {
            return false;
        }
        
        return $payload;
    }
    
    /**
     * Get tenant database from JWT token or X-Tenant-ID header
     * Priority: 1) JWT token tenant_id, 2) X-Tenant-ID header
     * 
     * @return string|false Database name or false if not found
     */
    public static function getTenantDatabase()
    {
        $tenant_id = null;
        
        // First, try to get tenant_id from JWT token (if database was specified during login)
        $payload = self::validateToken();
        if ($payload && isset($payload['tenant_id'])) {
            $tenant_id = (int)$payload['tenant_id'];
        }
        
        // If not in token, try X-Tenant-ID header
        if (!$tenant_id) {
            $headers = getallheaders();
            if ($headers) {
                $headers = array_change_key_case($headers, CASE_LOWER);
                if (isset($headers['x-tenant-id'])) {
                    $tenant_id = (int)$headers['x-tenant-id'];
                }
            }
        }
        
        // If still no tenant_id, return false
        if (!$tenant_id) {
            return false;
        }
        
        global $sqhost, $squser, $sqpass, $sqdb;
        $conn = db_connect($sqhost, $squser, $sqpass, $sqdb, __FILE__ . " linje " . __LINE__);
        
        if (!$conn) {
            return false;
        }
        
        $qtxt = "select db from regnskab where id='$tenant_id' limit 1";
        $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        
        if ($r && isset($r['db'])) {
            return $r['db'];
        }
        
        return false;
    }
}

