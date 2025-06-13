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
        try {
            if (!isset($data->id)) {
                $this->sendResponse(false, null, 'Order line ID is required for update', 400);
                return;
            }

            $orderLine = new OrderLineModel($data->id);
            if (!$orderLine->getId()) {
                $this->sendResponse(false, null, 'Order line not found', 404);
                return;
            }

            // Update fields
            if (isset($data->antal)) $orderLine->setAntal($data->antal);
            if (isset($data->pris)) $orderLine->setPris($data->pris);
            if (isset($data->rabat)) $orderLine->setRabat($data->rabat);
            if (isset($data->beskrivelse)) $orderLine->setBeskrivelse($data->beskrivelse);

            if ($orderLine->save()) {
                $headers = array_change_key_case(getallheaders(), CASE_LOWER);
                $db = $headers['x-db'] ?? 'unknown';
                write_log("Order line updated: " . $data->id, $db, 'INFO');
                $this->sendResponse(true, $orderLine->toArray(), 'Order line updated successfully');
            } else {
                $this->sendResponse(false, null, 'Failed to update order line', 500);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    protected function handleDelete($data)
    {
        try {
            if (!isset($data->id)) {
                $this->sendResponse(false, null, 'Order line ID is required for deletion', 400);
                return;
            }

            $orderLine = new OrderLineModel($data->id);
            if (!$orderLine->getId()) {
                $this->sendResponse(false, null, 'Order line not found', 404);
                return;
            }

            if ($orderLine->delete()) {
                $headers = array_change_key_case(getallheaders(), CASE_LOWER);
                $db = $headers['x-db'] ?? 'unknown';
                write_log("Order line deleted: " . $data->id, $db, 'INFO');
                $this->sendResponse(true, null, 'Order line deleted successfully');
            } else {
                $this->sendResponse(false, null, 'Failed to delete order line', 500);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
}

$endpoint = new OrderLineEndpoint();
$endpoint->handleRequestMethod();