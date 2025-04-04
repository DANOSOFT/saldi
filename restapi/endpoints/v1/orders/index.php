<?php

require_once '../../../models/ordre/OrdreModel.php';
require_once '../../../core/BaseEndpoint.php';

class OrdreEndpoint extends BaseEndpoint
{
    protected $model;

    public function __construct()
    {
        #parent::__construct($db);
    }

    protected function handleGet($id = null)
    {
        if ($id) {
            $vare = new OrdreModel($id);
            $item = $vare->toArray();
            $this->sendResponse(true, $item);
        } else {
            // Get all items and convert each to an array
            $orders = OrdreModel::getAllOrders();
            $items = [];
            foreach ($orders as $order) {
                $items[] = $order->toArray();
            }
            $this->sendResponse(true, $items);
        }
    }
}

$endpoint = new OrdreEndpoint();
$endpoint->handleRequestMethod();