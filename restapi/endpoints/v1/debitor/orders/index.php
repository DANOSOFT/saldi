<?php
require_once '../../../../models/orders/OrderModel.php';
require_once '../../../../services/OrderService.php';
require_once '../../../../core/BaseEndpoint.php';

class OrderEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function handleGet($id = null)
    {
        if ($id) {
            $order = new OrderModel($id, 'DO');
            if ($order->getId()) {
                $this->sendResponse(true, $order->toArray());
            } else {
                $this->sendResponse(
                    false,
                    null,
                    "Order with ID $id not found or is not a debitor order", 
                    404
                );
            }
        } else {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $orderBy = isset($_GET['orderBy']) ? $_GET['orderBy'] : 'ordrenr';
            $orderDirection = isset($_GET['orderDirection']) ? $_GET['orderDirection'] : 'ASC';

            // get order based on dates 
            $fromDate = isset($_GET['fromDate']) ? $_GET['fromDate'] : null;
            $toDate = isset($_GET['toDate']) ? $_GET['toDate'] : null;
            
            if ($fromDate && $toDate) {
                // Validate date format
                if (!DateTime::createFromFormat('Y-m-d', $fromDate) || !DateTime::createFromFormat('Y-m-d', $toDate)) {
                    $this->sendResponse(false, null, 'Invalid date format. Use YYYY-MM-DD', 400);
                    return;
                }
            }else{
                $fromDate = null;
                $toDate = null;
            }
            // Get all orders with art = 'DO' for debitor
            $orders = OrderModel::getAllItems('DO', $limit, $orderBy, $orderDirection, $fromDate, $toDate);
            $items = [];
            foreach ($orders as $order) {
                $items[] = $order->toArray();
            }
            $this->sendResponse(true, $items);
        }
    }

    protected function handlePost($data)
    {
        // Set art to DO for debitor orders
        $data->art = 'DO';
        $result = OrderService::createOrder($data);
        
        if ($result['success']) {
            $this->sendResponse(true, $result['data'], 'Order created successfully', 201);
        } else {
            $this->sendResponse(false, null, $result['message'], 400);
        }
    }

    protected function handlePut($data)
    {
        // there is no put method for Debitor orders
        $this->sendResponse(false, null, 'PUT method is not supported for Debitor orders', 405);
        return;
    }

    protected function handleDelete($data)
    {
        // there is no delete method for Debitor orders
        $this->sendResponse(false, null, 'DELETE method is not supported for Debitor orders', 405);
        return;
    }
}

$endpoint = new OrderEndpoint();
$endpoint->handleRequestMethod();