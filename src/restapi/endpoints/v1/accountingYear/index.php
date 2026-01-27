<?php

require_once '../../../core/BaseEndpoint.php';
require_once '../../../core/logging.php';

class AccountingYearEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function handleGet($id = null)
    {
        try {
            $res = $this->getCurrentFiscalYear();
            
            if ($res) {
                $this->sendResponse(true, [
                    'fiscal_year' => $res["box2"],
					'fiscal_year_start' => $res["box1"],
					'fiscal_year_end' => $res["box3"],
                    'current_date' => date('Y-m-d'),
                    'current_month' => date('m'),
                    'current_year' => date('Y')
                ], 'Current fiscal year retrieved successfully');
            } else {
                $this->sendResponse(false, null, 'No active fiscal year found', 404);
            }
        } catch (Exception $e) {
            $this->sendResponse(false, null, 'Internal server error: ' . $e->getMessage(), 500);
        }
    }

    protected function handlePost($data)
    {
        $this->sendResponse(false, null, 'POST method is not supported for accounting year', 405);
    }

    protected function handlePut($data)
    {
        $this->sendResponse(false, null, 'PUT method is not supported for accounting year', 405);
    }

    protected function handleDelete($data)
    {
        $this->sendResponse(false, null, 'DELETE method is not supported for accounting year', 405);
    }

    private function getCurrentFiscalYear()
    {
        $query = db_select("SELECT box1, box2, box3, box4, kodenr FROM grupper WHERE art = 'RA'", __FILE__ . " linje " . __LINE__);
        
        if (!$query) {
            error_log("Failed to get fiscal year data");
            return null;
        }
        
        $currentYear = date('Y');
        $currentMonth = date('m');
        $regnaar = null;
        
        while($row = db_fetch_array($query)) {
            $box1 = $row['box1']; // Starting month
            $box2 = $row['box2']; // Starting year
            $box3 = $row['box3']; // Ending month
            $box4 = $row['box4']; // Ending year
            $kodenr = $row['kodenr'];

            if (($currentYear > $box2 || ($currentYear == $box2 && $currentMonth >= $box1)) &&
                ($currentYear < $box4 || ($currentYear == $box4 && $currentMonth <= $box3))) {
                $regnaar = $row;
                break;
            }
        }
        
        return $regnaar;
    }
}

$endpoint = new AccountingYearEndpoint();
$endpoint->handleRequestMethod();