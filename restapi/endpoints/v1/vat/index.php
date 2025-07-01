<?php

require_once '../../../models/finans/VatModel.php';
require_once '../../../core/BaseEndpoint.php';

class VatEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function handleGet($id = null)
    {
        try {
            if ($id) {
                // Get single VAT item
                $vatItem = new VatModel($id);
                if ($vatItem->getId()) {
                    $this->sendResponse(true, $vatItem->toArray());
                } else {
                    $this->sendResponse(false, null, 'VAT item not found', 404);
                }
            } else {
                // Get all VAT items with optional filtering
                $orderBy = $_GET['orderBy'] ?? 'kodenr';
                $orderDirection = $_GET['orderDirection'] ?? 'ASC';
                $field = $_GET['field'] ?? null;
                $value = $_GET['value'] ?? null;
                $limit = $_GET['limit'] ?? 50;
                if($limit > 200 || $limit < 1) {
                    $limit = 50; // Enforce a maximum limit
                }
                
                if ($field && $value) {
                    // Search by specific field
                    $vatItems = VatModel::findBy($field, $value);
                } else {
                    // Get all VAT items
                    $vatItems = VatModel::getAllItems($orderBy, $orderDirection);
                }
                
                $items = [];
                foreach ($vatItems as $vatItem) {
                    $items[] = $vatItem->toArray();
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
            $this->validateData($data, ['momskode', 'nr', 'beskrivelse']);
            
            $vatItem = new VatModel();
            
            // Set basic properties
            if (isset($data->momskode)) $vatItem->setMomskode($data->momskode);
            if (isset($data->nr)) $vatItem->setNr($data->nr);
            if (isset($data->beskrivelse)) $vatItem->setBeskrivelse($data->beskrivelse);
            if (isset($data->fiscal_year)) $vatItem->setFiscalYear($data->fiscal_year);
            
            // Set VAT specific properties
            if (isset($data->account)) $vatItem->setAccount($data->account);
            if (isset($data->sats)) $vatItem->setSats($data->sats);
            if (isset($data->modkonto)) $vatItem->setModkonto($data->modkonto);
            if (isset($data->map)) $vatItem->setMap($data->map);
            
            $result = $vatItem->save();
            
            if ($result === true) {
                $this->sendResponse(true, $vatItem->toArray(), 'VAT item created successfully', 201);
            } else {
                $this->sendResponse(false, null, is_string($result) ? $result : 'Failed to create VAT item', 400);
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
            
            $vatItem = new VatModel($data->id);
            if (!$vatItem->getId()) {
                $this->sendResponse(false, null, 'VAT item not found', 404);
                return;
            }
            
            // Update properties
            if (isset($data->momskode)) $vatItem->setMomskode($data->momskode);
            if (isset($data->nr)) $vatItem->setNr($data->nr);
            if (isset($data->beskrivelse)) $vatItem->setBeskrivelse($data->beskrivelse);
            if (isset($data->fiscal_year)) $vatItem->setFiscalYear($data->fiscal_year);
            if (isset($data->account)) $vatItem->setAccount($data->account);
            if (isset($data->sats)) $vatItem->setSats($data->sats);
            if (isset($data->modkonto)) $vatItem->setModkonto($data->modkonto);
            if (isset($data->map)) $vatItem->setMap($data->map);
            
            $result = $vatItem->save();
            
            if ($result === true) {
                $this->sendResponse(true, $vatItem->toArray(), 'VAT item updated successfully');
            } else {
                $this->sendResponse(false, null, is_string($result) ? $result : 'Failed to update VAT item', 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    protected function handleDelete($data)
    {
        try {
            // Validate required fields
            $this->validateData($data, ['id']);
            
            $vatItem = new VatModel($data->id);
            if (!$vatItem->getId()) {
                $this->sendResponse(false, null, 'VAT item not found', 404);
                return;
            }
            
            $result = $vatItem->delete();
            
            if ($result) {
                $this->sendResponse(true, null, 'VAT item deleted successfully');
            } else {
                $this->sendResponse(false, null, 'Failed to delete VAT item', 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
}

// Initialize and handle the request
$endpoint = new VatEndpoint();
$endpoint->handleRequestMethod();