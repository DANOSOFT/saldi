<?php

require_once '../../../../models/lager/LagerModel.php';
require_once '../../../../core/BaseEndpoint.php';

class WarehousesEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function handleGet($id = null)
    {
        try {
            if ($id) {
                // Get single warehouse
                $warehouse = new LagerModel($id);
                if ($warehouse->getId() > 0) {
                    $this->sendResponse(true, $warehouse->toArray());
                } else {
                    $this->sendResponse(false, null, 'Warehouse not found', 404);
                }
            } else {
                // Get all warehouses with optional filtering
                $orderBy = $_GET['orderBy'] ?? 'kodenr';
                $orderDirection = $_GET['orderDirection'] ?? 'ASC';
                $field = $_GET['field'] ?? null;
                $value = $_GET['value'] ?? null;
                $vare_id = $_GET['productId'] ?? null;
                
                if ($field && $value) {
                    // Search by specific field
                    $warehouses = LagerModel::findBy($field, $value);
                } else {
                    // Get all warehouses
                    $warehouses = LagerModel::getAllItems($vare_id, $orderBy, $orderDirection);
                }
                
                $items = [];
                foreach ($warehouses as $warehouse) {
                    $items[] = $warehouse->toArray();
                }
                $this->sendResponse(true, $items);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    protected function handlePost($data)
    {
        try {
            // Validate required fields
            $this->validateData($data, ['beskrivelse', 'nr']);
            
            $warehouse = new LagerModel();
            
            // Set properties
            if (isset($data->beskrivelse)) $warehouse->setBeskrivelse($data->beskrivelse);
            if (isset($data->nr)) $warehouse->setNr($data->nr);
            if (isset($data->fiscal_year)) $warehouse->setFiscalYear($data->fiscal_year);
            
            $result = $warehouse->save();
            
            if ($result === true) {
                $this->sendResponse(true, $warehouse->toArray(), 'Warehouse created successfully', 201);
            } else {
                $this->sendResponse(false, null, is_string($result) ? $result : 'Failed to create warehouse', 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    protected function handlePut($data)
    {
        try {
            // Validate required fields
            $this->validateData($data, ['id']);
            
            $warehouse = new LagerModel($data->id);
            if ($warehouse->getId() <= 0) {
                $this->sendResponse(false, null, 'Warehouse not found', 404);
                return;
            }
            
            // Update properties
            if (isset($data->beskrivelse)) $warehouse->setBeskrivelse($data->beskrivelse);
            if (isset($data->nr)) $warehouse->setNr($data->nr);
            if (isset($data->fiscal_year)) $warehouse->setFiscalYear($data->fiscal_year);
            
            $result = $warehouse->save();
            
            if ($result === true) {
                $this->sendResponse(true, $warehouse->toArray(), 'Warehouse updated successfully');
            } else {
                $this->sendResponse(false, null, is_string($result) ? $result : 'Failed to update warehouse', 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    protected function handleDelete($data)
    {

        // no deletion for warehouses
        $this->sendResponse(false, null, 'DELETE method is not supported for Warehouses', 405);
        return;

        /* try {
            // Validate required fields
            $this->validateData($data, ['id']);
            
            $warehouse = new LagerModel($data->id);
            if ($warehouse->getId() <= 0) {
                $this->sendResponse(false, null, 'Warehouse not found', 404);
                return;
            }
            
            $result = $warehouse->delete();
            
            if ($result) {
                $this->sendResponse(true, null, 'Warehouse deleted successfully');
            } else {
                $this->sendResponse(false, null, 'Failed to delete warehouse', 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        } */
    }
}

// Initialize and handle the request
$endpoint = new WarehousesEndpoint();
$endpoint->handleRequestMethod();
