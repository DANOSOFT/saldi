<?php

require_once '../../../../models/lager/LagerStatusModel.php';
require_once '../../../../core/BaseEndpoint.php';

class InventoryStatusEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function handleGet($id = null)
    {
        try {
            if ($id) {
                // Get single inventory status
                $status = new LagerStatusModel($id);
                if ($status->getId()) {
                    $this->sendResponse(true, $status->toArray());
                } else {
                    $this->sendResponse(false, null, 'Inventory status not found', 404);
                }
            } else {
                // Get all inventory status with optional filtering
                $orderBy = $_GET['orderBy'] ?? 'id';
                $orderDirection = $_GET['orderDirection'] ?? 'ASC';
                $field = $_GET['field'] ?? null;
                $value = $_GET['value'] ?? null;
                $lager_nr = $_GET['inventoryNr'] ?? null;
                
                if ($lager_nr || $lager_nr === '0') {
                    // Get inventory for specific warehouse
                    $items = LagerStatusModel::getWarehouseInventory($lager_nr);
                } elseif ($field && $value) {
                    // Search by specific field
                    $items = LagerStatusModel::findBy($field, $value);
                } else {
                    // Get all inventory status
                    $items = LagerStatusModel::getAllItems($orderBy, $orderDirection);
                }
                
                $result = [];
                foreach ($items as $item) {
                    $result[] = $item->toArray();
                }
                $this->sendResponse(true, $result);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    protected function handlePost($data)
    {
        try {
            // Validate required fields
            $this->validateData($data, ['inventory', 'productId', 'quantity']);
            
            $status = new LagerStatusModel();
            
            // Set properties
            if (isset($data->inventory)) $status->setInventory($data->inventory);
            if (isset($data->productId)) $status->setProductId($data->productId);
            if (isset($data->quantity)) $status->setQuantity($data->quantity);
            if (isset($data->location)) $status->setLocation($data->location);
            if (isset($data->location2)) $status->setLocation2($data->location2);
            if (isset($data->location3)) $status->setLocation3($data->location3);
            if (isset($data->location4)) $status->setLocation4($data->location4);
            if (isset($data->location5)) $status->setLocation5($data->location5);
            if (isset($data->variantId)) $status->setVariantId($data->variantId);

            $result = $status->save();
            
            if ($result === true) {
                $this->sendResponse(true, $status->toArray(), 'Inventory status created successfully', 201);
            } else {
                $this->sendResponse(false, null, is_string($result) ? $result : 'Failed to create inventory status', 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    protected function handlePut($data)
    {
        try {
            if(isset($_GET["id"])){
                $data->id = $_GET["id"];
            }

            // Validate required fields
            $this->validateData($data, ['id']);
            
            $status = new LagerStatusModel($data->id);
            if (!$status->getId()) {
                $this->sendResponse(false, null, 'Inventory status not found', 404);
                return;
            }

            // Update properties
            if (isset($data->inventory)) $status->setInventory($data->inventory);
            if (isset($data->productId)) $status->setProductId($data->productId);
            if (isset($data->quantity)) $status->setQuantity($data->quantity);
            if (isset($data->location)) $status->setLocation($data->location);
            if (isset($data->location2)) $status->setLocation2($data->location2);
            if (isset($data->location3)) $status->setLocation3($data->location3);
            if (isset($data->location4)) $status->setLocation4($data->location4);
            if (isset($data->location5)) $status->setLocation5($data->location5);
            if (isset($data->variantId)) $status->setVariantId($data->variantId);
            
            $result = $status->save();
            
            if ($result === true) {
                $this->sendResponse(true, $status->toArray(), 'Inventory status updated successfully');
            } else {
                $this->sendResponse(false, null, is_string($result) ? $result : 'Failed to update inventory status', 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    protected function handlePatch($data)
    {
        try {
            if(isset($_GET["id"])){
                $data->id = $_GET["id"];
            }
            
            // Handle inventory adjustments
            if (isset($data->action)) {
                switch ($data->action) {
                    case 'adjust_quantity':
                        $this->handleQuantityAdjustment($data);
                        break;
                    case 'set_quantity':
                        $this->handleQuantityUpdate($data);
                        break;
                    default:
                        $this->sendResponse(false, null, 'Invalid action specified', 400);
                        break;
                }
            } else {
                $this->sendResponse(false, null, 'Action parameter required for PATCH requests', 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    private function handleQuantityAdjustment($data)
    {
        // Validate required fields
        $this->validateData($data, ['id', 'amount']);
        
        $status = new LagerStatusModel($data->id);
        if (!$status->getId()) {
            $this->sendResponse(false, null, 'Inventory status not found', 404);
            return;
        }
        
        $result = $status->adjustQuantity($data->amount);
        
        if ($result) {
            $this->sendResponse(true, $status->toArray(), 'Inventory quantity adjusted successfully');
        } else {
            $this->sendResponse(false, null, 'Failed to adjust inventory quantity', 400);
        }
    }

    private function handleQuantityUpdate($data)
    {
        // Validate required fields
        $this->validateData($data, ['id', 'quantity']);
        
        $status = new LagerStatusModel($data->id);
        if (!$status->getId()) {
            $this->sendResponse(false, null, 'Inventory status not found', 404);
            return;
        }
        
        $result = $status->updateQuantity($data->quantity);
        
        if ($result) {
            $this->sendResponse(true, $status->toArray(), 'Inventory quantity updated successfully');
        } else {
            $this->sendResponse(false, null, 'Failed to update inventory quantity', 400);
        }
    }

    protected function handleDelete($data)
    {

        // no deletion for inventory status
        $this->sendResponse(false, null, 'DELETE method is not supported for Inventory Status', 405);
        /* try {
            // For inventory status, we typically don't delete records but set quantity to 0
            // However, if deletion is needed:
            $this->validateData($data, ['id']);
            
            $status = new LagerStatusModel($data->id);
            if (!$status->getId()) {
                $this->sendResponse(false, null, 'Inventory status not found', 404);
                return;
            }
            
            // Set quantity to 0 instead of deleting
            $result = $status->updateQuantity(0);
            
            if ($result) {
                $this->sendResponse(true, $status->toArray(), 'Inventory status cleared successfully');
            } else {
                $this->sendResponse(false, null, 'Failed to clear inventory status', 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        } */
    }
}

// Initialize and handle the request
$endpoint = new InventoryStatusEndpoint();
$endpoint->handleRequestMethod();
