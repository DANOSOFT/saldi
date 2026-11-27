<?php
// 20261105 CL/LH Needs the Debitorordre right (debitor.ordre), as debitor/ordre.php and ordreliste.php do; without it the request is refused with 403.
//                A POST creates a sales order (DO) whatever 'type' or 'art' the body sends.
//                A number field that is not a number (status "0', '987654', ...") is a 400 before anything is stored.
require_once '../../../../models/orders/OrderModel.php';
require_once '../../../../services/OrderService.php';
require_once '../../../../core/BaseEndpoint.php';

class OrderEndpoint extends BaseEndpoint
{
    protected $permissionKey = 'debitor.ordre';

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
        // A debitor order (DO), whatever type the body names
        $this->validateNumericFields($data, OrderService::DECIMAL_FIELDS, OrderService::INTEGER_FIELDS);
        $result = OrderService::createOrder($data, 'DO');
        
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