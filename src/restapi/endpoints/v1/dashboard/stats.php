<?php
/**
 * GET /dashboard/stats
 * Get dashboard statistics
 * Returns: revenue_ytd, overdue_count, overdue_amount
 */

require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../core/JWT.php';
require_once __DIR__ . '/../../../core/JWTAuth.php';
require_once __DIR__ . '/../../../core/logging.php';

include_once __DIR__ . '/../../../../includes/db_query.php';
include_once __DIR__ . '/../../../../includes/connect.php';

class DashboardStatsEndpoint extends BaseEndpoint
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
        
        $currentYear = date('Y');
        $today = date('Y-m-d');
        
        // Revenue year-to-date (from invoices with status >= 2)
        $qtxt = "SELECT COALESCE(SUM(sum + moms), 0) as revenue_ytd 
                 FROM ordrer 
                 WHERE art = 'DO' 
                 AND status >= 2 
                 AND EXTRACT(YEAR FROM fakturadate) = '$currentYear'";
        $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        $revenue_ytd = (float)$r['revenue_ytd'];
        
        // Overdue invoices (status >= 2, fakturadate < today, not paid)
        $qtxt = "SELECT COUNT(*) as count, COALESCE(SUM(sum + moms), 0) as amount 
                 FROM ordrer 
                 WHERE art = 'DO' 
                 AND status >= 2 
                 AND fakturadate < '$today' 
                 AND betalt != '1'";
        $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        $overdue_count = (int)$r['count'];
        $overdue_amount = (float)$r['amount'];
        
        $stats = [
            'revenue_ytd' => $revenue_ytd,
            'overdue_count' => $overdue_count,
            'overdue_amount' => $overdue_amount
        ];
        
        $this->sendResponse(true, $stats);
    }
    
    protected function handlePost($data)
    {
        $this->sendResponse(false, null, 'POST method not supported', 405);
    }
}

$endpoint = new DashboardStatsEndpoint();
$endpoint->handleRequestMethod();

