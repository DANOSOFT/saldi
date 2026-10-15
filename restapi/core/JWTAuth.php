<?php
/**
 * JWT Authentication Middleware
 * Validates JWT tokens from Authorization header
 *
 * History:
 * 20261001 CDX/LH Refuse tenant header fallback for principal-bearing tokens.
 * 20261001 CDX/LH Remove all tenant header fallback and expose session validation.
 */
// 20261001 CDX/LH Restore bounded legacy tenant selection while retaining modern session enforcement.

require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/MobileAuth.php';

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
        
        if (!isset($payload['type'], $payload['exp']) || $payload['type'] !== 'access') {
            return false;
        }
        
        return $payload;
    }
    
    /** Validate modern sessions and all auth routes; old resource tokens retain signature-only access. */
    public static function checkSession(array $payload): void
    {
        if (self::isLegacyToken($payload)
            && strpos((string)ifset($_SERVER, 'SCRIPT_NAME', ''), '/endpoints/v1/auth/') === false) {
            return;
        }
        MobileAuth::checkSession($payload);
    }

    /** True only for pre-identity tokens; partial modern claims never enable compatibility. */
    public static function isLegacyToken(array $payload): bool
    {
        foreach (['principal', 'sub', 'sid', 'auth_version', 'device_id', 'jti'] as $claim) {
            if (array_key_exists($claim, $payload)) {
                return false;
            }
        }
        return true;
    }

    /** @return int|null Signed tenant first; only old tokens missing that claim may use the header. */
    public static function tenantId(array $payload): ?int
    {
        $candidate = ifset($payload, 'tenant_id');
        if (!array_key_exists('tenant_id', $payload) && self::isLegacyToken($payload)) {
            $headers = array_change_key_case(getallheaders() ?: [], CASE_LOWER);
            $candidate = ifset($headers, 'x-tenant-id');
        }
        $id = filter_var($candidate, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : $id;
    }

    /** @return string|false Registry database selected by the signed tenant or the legacy fallback. */
    public static function getTenantDatabase()
    {
        $payload = self::validateToken();
        $tenant_id = $payload ? self::tenantId($payload) : null;
        if ($tenant_id === null) {
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
