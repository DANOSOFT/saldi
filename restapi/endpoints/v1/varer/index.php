<?php

require_once '../../../models/VareModel.php';
require_once '../../../core/BaseEndpoint.php';

class VareEndpoint extends BaseEndpoint
{
    protected $model;

    public function __construct()
    {
        #parent::__construct($db);
    }

    protected function handleGet($id = null)
    {
        if ($id) {
            $vare = new VareModel($id);
            $item = $vare->toArray();
            $this->sendResponse(true, $item);
        } else {
            // Get all items and convert each to an array
            $varer = VareModel::getAllItems();
            $items = [];
            foreach ($varer as $vare) {
                $items[] = $vare->toArray();
            }
            $this->sendResponse(true, $items);
        }
    }
}

$endpoint = new VareEndpoint();
$endpoint->handleRequestMethod();