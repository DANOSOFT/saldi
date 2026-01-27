<?php
/**
 * GET /vat-codes
 * Get list of VAT codes (momskoder)
 */

require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../core/JWT.php';
require_once __DIR__ . '/../../../core/JWTAuth.php';
require_once __DIR__ . '/../../../core/logging.php';

include_once __DIR__ . '/../../../../includes/db_query.php';
include_once __DIR__ . '/../../../../includes/connect.php';

class VatCodesEndpoint extends BaseEndpoint
{
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
        
        $this->db = JWTAuth::getTenantDatabase();
        if (!$this->db) {
            $this->sendResponse(false, null, 'Tenant database not found. Set X-Tenant-ID header.', 400);
            return false;
        }
        
        return true;
    }
    
    protected function handleGet($id = null)
    {
        global $sqhost, $squser, $sqpass;
        $conn = db_connect($sqhost, $squser, $sqpass, $this->db, __FILE__ . " linje " . __LINE__);
        
        if (!$conn) {
            $this->sendResponse(false, null, 'Database connection failed', 500);
            return;
        }
        
        // Get VAT codes from grupper table where art = 'S' (momssatser)
        $qtxt = "SELECT kodenr, box1 as rate, box2 as description FROM grupper WHERE art = 'S' ORDER BY kodenr";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        $vatCodes = [];
        while ($r = db_fetch_array($q)) {
            $vatCodes[] = [
                'code' => (int)$r['kodenr'],
                'rate' => (float)$r['rate'],
                'description' => $r['description']
            ];
        }
        
        $this->sendResponse(true, $vatCodes);
    }
    
    protected function handlePost($data)
    {
        $this->sendResponse(false, null, 'POST method not supported', 405);
    }
}

$endpoint = new VatCodesEndpoint();
$endpoint->handleRequestMethod();

