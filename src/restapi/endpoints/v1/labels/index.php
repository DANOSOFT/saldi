<?php
require_once '../../../models/labels/LabelModel.php';
require_once '../../../core/BaseEndpoint.php';

class LabelEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function handleGet($id = null)
    {
        if ($id) {
            $label = new LabelModel($id);
            if ($label->getId()) {
                $this->sendResponse(true, $label->toArray());
            } else {
                $this->sendResponse(
                    false,
                    null,
                    "Label with ID $id not found", 
                    404
                );
            }
        } else {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $orderBy = isset($_GET['orderBy']) ? $_GET['orderBy'] : 'id';
            $orderDirection = isset($_GET['orderDirection']) ? $_GET['orderDirection'] : 'DESC';
            
            // Filter parameters
            $state = isset($_GET['state']) ? $_GET['state'] : null;
            $account_id = isset($_GET['account_id']) ? (int)$_GET['account_id'] : null;

            // Validate state filter
            if ($state) {
                $allowedStates = ['active', 'inactive', 'printed', 'sold']; // Adjust based on your business logic
                if (!in_array($state, $allowedStates)) {
                    $this->sendResponse(false, null, 'Invalid state filter', 400);
                    return;
                }
            }

            // Get all labels
            $labels = LabelModel::getAllItems($limit, $orderBy, $orderDirection, $state, $account_id);
            $items = [];
            foreach ($labels as $label) {
                $items[] = $label->toArray();
            }
            
            $response = [
                'items' => $items,
                'count' => count($items),
                'filters' => [
                    'state' => $state,
                    'account_id' => $account_id
                ]
            ];
            
            $this->sendResponse(true, $response);
        }
    }

    protected function handlePost($data)
    {
        // Create new label - POST method not supported for labels (read-only)
        $this->sendResponse(false, null, 'POST method is not supported for labels (read-only endpoint)', 405);
        return;
    }

    protected function handlePut($data)
    {
        // Update label - PUT method not supported for labels (read-only)
        $this->sendResponse(false, null, 'PUT method is not supported for labels (read-only endpoint)', 405);
        return;
    }

    protected function handleDelete($data)
    {
        // Delete label - DELETE method not supported for labels (read-only)
        $this->sendResponse(false, null, 'DELETE method is not supported for labels (read-only endpoint)', 405);
        return;
    }
}

$endpoint = new LabelEndpoint();
$endpoint->handleRequestMethod();