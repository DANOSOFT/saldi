<?php
/**
 * GET /dashboard/stats
 * Get dashboard statistics
 * Returns: revenue_ytd, overdue_count, overdue_amount
 * @deprecated Use dashboard/overview.php and the overview v2 capability.
 */
// 20261001 CDX/LH Corrected fiscal-year turnover and overdue debtor remainders; require finans/read.
// 20261001 CDX/LH Return null turnover and available debtor totals when fiscal-year data is unavailable.

require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../core/JWT.php';
require_once __DIR__ . '/../../../core/JWTAuth.php';
require_once __DIR__ . '/../../../core/logging.php';
require_once __DIR__ . '/../../../services/OverviewService.php';

include_once __DIR__ . '/../../../../includes/db_query.php';
include_once __DIR__ . '/../../../../includes/connect.php';

class DashboardStatsEndpoint extends BaseEndpoint
{
    // 20260904 CL/NTR: dropped a `private $db` redeclaration - narrowing BaseEndpoint's protected $db
    // was a PHP fatal error, so every request to this endpoint died as an HTML error page.

    public function __construct()
    {
        parent::__construct();
    }
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module' => 'finans', 'action' => 'read'];
    }

    /** Legacy response keys, backed by the same accounting functions as Overblik v2. */
    protected function handleGet($id = null)
    {
        header('Cache-Control: private, no-store');
        $service = new OverviewService($this->conn);
        $today = OverviewService::today();
        $revenue = null;
        $unavailable = [];
        try {
            $year = $service->fiscalYear($today);
            $revenue = $service->revenue($year, $today);
        } catch (OverviewFiscalYearUnavailable $e) {
            $unavailable = ['money_cards_available' => false, 'money_cards_unavailable_reason' => $e->reason];
        } catch (InvalidArgumentException $e) {
            $this->sendError('validation_failed', $e->getMessage(), 422);
            return;
        }
        $overdue = $service->overdue($today);
        $this->sendResponse(true, [
            'revenue_ytd' => $revenue,
            'overdue_count' => $overdue['count'],
            'overdue_amount' => $overdue['amount'],
        ] + $unavailable);
    }

    protected function handlePost($data)
    {
        $this->sendResponse(false, null, 'POST method not supported', 405);
    }
}

$endpoint = new DashboardStatsEndpoint();
$endpoint->handleRequestMethod();
