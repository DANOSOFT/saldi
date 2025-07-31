<?php

require_once '../../../models/finans/VatModel.php';
require_once '../../../core/BaseEndpoint.php';

class VatEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }

    // Field mapping from English to Danish
    private function mapEnglishToDanish($field) {
        $fieldMap = [
            'description' => 'beskrivelse',
            'vatCode' => 'kode',
            'number' => 'kodenr',
            'fiscalYear' => 'fiscal_year',
            'account' => 'account',
            'rate' => 'sats',
            'contraAccount' => 'modkonto',
            'mapping' => 'map'
        ];
        
        return $fieldMap[$field] ?? $field;
    }

    protected function handleGet($id = null)
    {
        try {
            $vatcode = $_GET['vatcode'] ?? null;
            
            if ($id) {
                // Get single VAT item by ID
                $vatItem = new VatModel($id, null);
                if ($vatItem->getId()) {
                    $this->sendResponse(true, $vatItem->toArray());
                } else {
                    $this->sendResponse(false, null, 'VAT item not found', 404);
                }
            } elseif ($vatcode) {
                // Get VAT items by vatcode
                $vatItems = VatModel::loadFromVatcode($vatcode);
                
                $items = [];
                foreach ($vatItems as $vatItem) {
                    $items[] = $vatItem->toArray();
                }
                
                if(count($items) > 0) {
                    $this->sendResponse(true, $items);
                } else {
                    $this->sendResponse(false, null, 'VAT item not found', 404);
                }
            } else {
                // Get all VAT items with optional filtering
                $orderBy = $_GET['orderBy'] ?? 'number';
                $orderDirection = $_GET['orderDirection'] ?? 'ASC';
                $field = $_GET['field'] ?? null;
                $value = $_GET['value'] ?? null;
                $limit = $_GET['limit'] ?? 50;
                
                if($limit > 200 || $limit < 1) {
                    $limit = 50; // Enforce a maximum limit
                }
                
                // Map English field names to Danish for database queries
                $orderBy = $this->mapEnglishToDanish($orderBy);
                $field = $field ? $this->mapEnglishToDanish($field) : null;
                
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
            // Validate required fields - accept both English and Danish names
            $hasVatCode = isset($data->vatCode) || isset($data->momskode);
            $hasNumber = isset($data->number) || isset($data->nr);
            $hasDescription = isset($data->description) || isset($data->beskrivelse);
            
            if (!$hasVatCode || !$hasNumber || !$hasDescription) {
                $this->sendResponse(false, null, 'VAT code, number, and description are required', 400);
                return;
            }
            
            $vatItem = new VatModel();
            
            // Set basic properties - support both English and Danish field names
            if (isset($data->vatCode)) $vatItem->setMomskode($data->vatCode);
            elseif (isset($data->momskode)) $vatItem->setMomskode($data->momskode);
            
            if (isset($data->number)) $vatItem->setNr($data->number);
            elseif (isset($data->nr)) $vatItem->setNr($data->nr);
            
            if (isset($data->description)) $vatItem->setBeskrivelse($data->description);
            elseif (isset($data->beskrivelse)) $vatItem->setBeskrivelse($data->beskrivelse);
            
            // Set VAT specific properties with defaults
            $vatItem->setAccount(
                isset($data->account) ? $data->account : 
                (isset($data->account) ? $data->account : 0)
            );
            
            $vatItem->setSats(
                isset($data->rate) ? $data->rate : 
                (isset($data->sats) ? $data->sats : 0.0)
            );
            
            $vatItem->setModkonto(
                isset($data->contraAccount) ? $data->contraAccount : 
                (isset($data->modkonto) ? $data->modkonto : 0)
            );
            
            if (isset($data->mapping)) $vatItem->setMap($data->mapping);
            elseif (isset($data->map)) $vatItem->setMap($data->map);
            
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
            // If ID is in GET parameters, add it to data instead of overwriting
            if(isset($_GET["id"])){
                $data->id = $_GET["id"];
            }

            // Validate required fields
            $this->validateData($data, ['id']);
            
            $vatItem = new VatModel($data->id);
            if (!$vatItem->getId()) {
                $this->sendResponse(false, null, 'VAT item not found', 404);
                return;
            }
            
            // Update properties - support both English and Danish field names
            if (isset($data->vatCode)) $vatItem->setMomskode($data->vatCode);
            elseif (isset($data->momskode)) $vatItem->setMomskode($data->momskode);
            
            if (isset($data->number)) $vatItem->setNr($data->number);
            elseif (isset($data->nr)) $vatItem->setNr($data->nr);
            
            if (isset($data->description)) $vatItem->setBeskrivelse($data->description);
            elseif (isset($data->beskrivelse)) $vatItem->setBeskrivelse($data->beskrivelse);
            
            if (isset($data->account)) $vatItem->setAccount($data->account);
            elseif (isset($data->account)) $vatItem->setAccount($data->account);
            
            if (isset($data->rate)) $vatItem->setSats($data->rate);
            elseif (isset($data->sats)) $vatItem->setSats($data->sats);
            
            if (isset($data->contraAccount)) $vatItem->setModkonto($data->contraAccount);
            elseif (isset($data->modkonto)) $vatItem->setModkonto($data->modkonto);
            
            if (isset($data->mapping)) $vatItem->setMap($data->mapping);
            elseif (isset($data->map)) $vatItem->setMap($data->map);
            
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
            // If ID is in GET parameters, add it to data instead of overwriting
            if(isset($_GET["id"])){
                $data->id = $_GET["id"];
            }

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