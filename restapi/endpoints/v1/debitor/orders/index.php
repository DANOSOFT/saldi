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
            $order = new OrderModel($id);
            if ($order->getId()) {
                $this->sendResponse(true, $order->toArray());
            } else {
                $this->sendResponse(false, null, 'Order not found', 404);
            }
        } else {
            // Get all orders
            $orders = OrderModel::getAllItems();
            $items = [];
            foreach ($orders as $order) {
                $items[] = $order->toArray();
            }
            $this->sendResponse(true, $items);
        }
    }

    protected function handlePost($data)
    {
        $result = OrderService::createOrder($data);
        
        if ($result['success']) {
            $this->sendResponse(true, $result['data'], 'Order created successfully', 201);
        } else {
            $this->sendResponse(false, null, $result['message'], 400);
        }
    }

    protected function handlePut($data)
    {
        if (!isset($data->id)) {
            $this->sendResponse(false, null, 'Order ID is required for update', 400);
            return;
        }

        $order = new OrderModel($data->id);
        if (!$order->getId()) {
            $this->sendResponse(false, null, 'Order not found', 404);
            return;
        }

        // Update fields
        if (isset($data->firmanavn)) $order->setFirmanavn($data->firmanavn);
        if (isset($data->telefon)) $order->setTelefon($data->telefon);
        if (isset($data->email)) $order->setEmail($data->email);
        if (isset($data->notes)) $order->setNotes($data->notes);
        if (isset($data->status)) $order->setStatus($data->status);

        if ($order->save()) {
            $this->sendResponse(true, $order->toArray(), 'Order updated successfully');
        } else {
            $this->sendResponse(false, null, 'Failed to update order', 500);
        }
    }

    protected function handleDelete($data)
    {
        if (!isset($data->id)) {
            $this->sendResponse(false, null, 'Order ID is required for deletion', 400);
            return;
        }

        $order = new OrderModel($data->id);
        if (!$order->getId()) {
            $this->sendResponse(false, null, 'Order not found', 404);
            return;
        }

        if ($order->delete()) {
            $this->sendResponse(true, null, 'Order deleted successfully');
        } else {
            $this->sendResponse(false, null, 'Failed to delete order', 500);
        }
    }
}

$endpoint = new OrderEndpoint();
$endpoint->handleRequestMethod();