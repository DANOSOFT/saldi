<?php
require_once '../../../../models/orderlines/OrderLineModel.php';
require_once '../../../../services/OrderLineService.php';
require_once '../../../../core/BaseEndpoint.php';
require_once '../../../../core/logging.php';

class OrderLineEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function handleGet($id = null)
    {
        $orderId = $_GET['order_id'] ?? null;
        
        if ($id) {
            // Get specific orderline
            $orderLine = new OrderLineModel($id);
            if ($orderLine->getId()) {
                $this->sendResponse(true, $orderLine->toArray());
            } else {
                $this->sendResponse(false, null, 'Order line not found', 404);
            }
        } elseif ($orderId) {
            // Get all orderlines for a specific order
            $orderLines = OrderLineModel::getByOrderId($orderId);
            $items = [];
            foreach ($orderLines as $line) {
                $items[] = $line->toArray();
            }
            $this->sendResponse(true, $items);
        } else {
            $this->sendResponse(false, null, 'Order ID or line ID is required', 400);
        }
    }

    protected function handlePost($data)
    {
        try {
            $service = new OrderLineService();
            $result = $service->createOrderLine($data);
            
            if ($result['success']) {
                $this->sendResponse(true, $result['data'], $result['message'], 201);
            } else {
                // Use the status_code from the service response, default to 500 if not set
                $statusCode = isset($result['status_code']) ? $result['status_code'] : 500;
                $this->sendResponse(false, null, $result['message'], $statusCode);
            }
        } catch (Exception $e) {
            $this->sendResponse(false, null, 'Internal server error: ' . $e->getMessage(), 500);
        }
    }

    protected function handlePut($data)
    {
        // There is no PUT method for order lines
        $this->sendResponse(false, null, 'PUT method is not supported for order lines', 405);
        return;
    }

    protected function handleDelete($data)
    {
        // There is no DELETE method for order lines
        $this->sendResponse(false, null, 'DELETE method is not supported for order lines', 405);
        return;
    }
}

$endpoint = new OrderLineEndpoint();
$endpoint->handleRequestMethod();