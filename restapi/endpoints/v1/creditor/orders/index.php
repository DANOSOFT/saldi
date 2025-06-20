<?php
require_once '../../../../models/orders/OrderModel.php';
require_once '../../../../services/OrderService.php';
require_once '../../../../core/BaseEndpoint.php';

class KreditorOrderEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function handleGet($id = null)
    {
        if ($id) {
            $order = new OrderModel($id, 'KO');
            if ($order->getId()) {
                $this->sendResponse(true, $order->toArray());
            } else {
                $this->sendResponse(false, null, 'Order not found', 404);
            }
        } else {
            // Get all orders with art = 'KO' for kreditor
            $orders = OrderModel::getAllItems('KO');
            $items = [];
            foreach ($orders as $order) {
                $items[] = $order->toArray();
            }
            $this->sendResponse(true, $items);
        }
    }

    protected function handlePost($data)
    {
        // Set art to KO for kreditor orders
        $data->art = 'KO';
        $result = OrderService::createOrder($data);
        
        if ($result['success']) {
            $this->sendResponse(true, $result['data'], 'Order created successfully', 201);
        } else {
            $this->sendResponse(false, null, $result['message'], 400);
        }
    }

    protected function handlePut($data)
    {
        // there is not put method for kreditor orders
        $this->sendResponse(false, null, 'PUT method is not supported for Kreditor orders', 405);
        return;
    }

    protected function handleDelete($data)
    {
        // there is no delete method for Kreditor orders
        $this->sendResponse(false, null, 'DELETE method is not supported for Kreditor orders', 405);
        return;
    }
}

$endpoint = new KreditorOrderEndpoint();
$endpoint->handleRequestMethod();