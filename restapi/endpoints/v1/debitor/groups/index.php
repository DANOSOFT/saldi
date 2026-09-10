<?php
// 20260715 CL/PHR Added read-only current-year debtor group endpoint.

require_once __DIR__ . '/../../../../core/BaseEndpoint.php';

class DebtorGroupsEndpoint extends BaseEndpoint
{
    protected function handleGet($id = null)
    {
        $q = db_select(
            "SELECT kodenr FROM grupper WHERE art = 'RA' ORDER BY kodenr DESC LIMIT 1",
            __FILE__ . " linje " . __LINE__
        );
        $yearRow = db_fetch_array($q);
        if (!$yearRow) {
            $this->sendResponse(false, null, 'Current fiscal year not found', 404);
            return;
        }

        $fiscalYear = intval($yearRow['kodenr']);
        $qtxt = "SELECT id, kodenr, beskrivelse, box1, box3, box4, box5, box7, box8, box9 "
            . "FROM grupper WHERE art = 'DG' AND fiscal_year = '$fiscalYear' ORDER BY kodenr";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

        $groups = [];
        while ($row = db_fetch_array($q)) {
            $groups[] = [
                'id' => intval($row['id']),
                'number' => intval($row['kodenr']),
                'description' => $row['beskrivelse'],
                'vatGroup' => $row['box1'],
                'currency' => $row['box3'],
                'language' => $row['box4'],
                'contraAccount' => $row['box5'],
                'commissionPercentage' => $row['box7'],
                'b2b' => $row['box8'] === 'on',
                'reversePayment' => $row['box9'] === 'on',
                'fiscalYear' => $fiscalYear,
            ];
        }

        $this->sendResponse(true, $groups);
    }

    protected function handlePost($data)
    {
        $this->sendResponse(false, null, 'POST method not supported', 405);
    }

    protected function handlePut($data)
    {
        $this->sendResponse(false, null, 'PUT method not supported', 405);
    }

    protected function handleDelete($data)
    {
        $this->sendResponse(false, null, 'DELETE method not supported', 405);
    }
}

$endpoint = new DebtorGroupsEndpoint();
$endpoint->handleRequestMethod();
