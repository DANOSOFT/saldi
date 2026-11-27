<?php
// 20261105 CL/LH List the SM/KM/EM/YM VAT codes of the current fiscal year (api_current_fiscal_year()), rate from box2 and description from beskrivelse.
// 20261127 ASR With $allowedLedgers in includes/connect.php the token's account database must be listed, the master database too (403).
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
    // 20260904 CL/NTR: dropped a `private $db` redeclaration - narrowing BaseEndpoint's protected $db
    // was a PHP fatal error, so every request to this endpoint died as an HTML error page.

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
        
        $tenantDb = JWTAuth::getTenantDatabase();
        if (!$tenantDb) {
            $this->sendResponse(false, null, 'Tenant database not found. Set X-Tenant-ID header.', 400);
            return false;
        }
        // An installation with $allowedLedgers (includes/connect.php) serves only the listed ledgers, the master database too.
        if (!saldi_ledger_allowed($tenantDb)) {
            $this->sendResponse(false, null, saldi_ledger_refusal_text(), 403);
            return false;
        }
        $this->db = $tenantDb;
        
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
        
        // The VAT codes are the grupper rows art SM/KM/EM/YM of a fiscal year, as vat/ and the Moms page read them:
        // box1 is the account, box2 the rate. grupper has no art 'S', so the old query always came back empty.
        $fiscalYear = (int)api_current_fiscal_year();
        $qtxt = "SELECT kodenr, box2 AS rate, beskrivelse AS description FROM grupper "
            . "WHERE art IN ('SM', 'KM', 'EM', 'YM') AND fiscal_year = $fiscalYear ORDER BY kode, kodenr";
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

