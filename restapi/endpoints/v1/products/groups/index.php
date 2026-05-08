<?php

require_once '../../../../models/lager/VareGruppeModel.php';
require_once '../../../../core/BaseEndpoint.php';

class ProductGroupsEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function handleGet($id = null)
    {
        try {
            if ($id) {
                // Get single product group
                $group = new VareGruppeModel($id);
                if ($group->getId()) {
                    $this->sendResponse(true, $group->toArray());
                } else {
                    $this->sendResponse(false, null, 'Product group not found', 404);
                }
            } else {
                // Get all product groups with optional filtering
                $orderBy = $_GET['orderBy'] ?? 'kodenr';
                $orderDirection = $_GET['orderDirection'] ?? 'ASC';
                $field = $_GET['field'] ?? null;
                $value = $_GET['value'] ?? null;
                
                if ($field && $value) {
                    // Search by specific field
                    $groups = VareGruppeModel::findBy($field, $value);
                } else {
                    // Get all product groups
                    $groups = VareGruppeModel::getAllItems($orderBy, $orderDirection);
                }
                
                $items = [];
                foreach ($groups as $group) {
                    $items[] = $group->toArray();
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
            $this->validateData($data, ['codeNo', 'description']);
            
            $group = new VareGruppeModel();
            
            // Update basic properties
            if (isset($data->codeNo)) $group->setCodeNo($data->codeNo);
            if (isset($data->description)) $group->setDescription($data->description);
            if (isset($data->fiscalYear)) $group->setFiscalYear($data->fiscalYear);

            // Update boolean options
            if (isset($data->reversePayment)) $group->setReversePayment($data->reversePayment);
            if (isset($data->taxFree)) $group->setTaxFree($data->taxFree);
            if (isset($data->inventory)) $group->setInventory($data->inventory);
            if (isset($data->batch)) $group->setBatch($data->batch);
            if (isset($data->operation)) $group->setOperation($data->operation);
            
            // Update account properties
            if (isset($data->buyAccount)) $group->setBuyAccount($data->buyAccount);
            if (isset($data->sellAccount)) $group->setSellAccount($data->sellAccount);
            if (isset($data->buyEuAccount)) $group->setBuyEuAccount($data->buyEuAccount);
            if (isset($data->sellEuAccount)) $group->setSellEuAccount($data->sellEuAccount);
            if (isset($data->buyOutsideEuAccount)) $group->setBuyOutsideEuAccount($data->buyOutsideEuAccount);
            if (isset($data->sellOutsideEuAccount)) $group->setSellOutsideEuAccount($data->sellOutsideEuAccount);
            
            $result = $group->save();
            
            if ($result === true) {
                $this->sendResponse(true, $group->toArray(), 'Product group created successfully', 201);
            } else {
                $this->sendResponse(false, null, is_string($result) ? $result : 'Failed to create product group', 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    protected function handlePut($data)
    {
        try {
			// Take in id from URL parameters
			if (isset($_GET['id'])) {
				$data->id = $_GET['id'];
			}

            // Validate required fields
            $this->validateData($data, ['id']);
            
            $group = new VareGruppeModel($data->id);
            if (!$group->getId()) {
                $this->sendResponse(false, null, 'Product group not found', 404);
                return;
            }
            
            // Update basic properties
            if (isset($data->codeNo)) $group->setCodeNo($data->codeNo);
            if (isset($data->description)) $group->setDescription($data->description);
            if (isset($data->fiscalYear)) $group->setFiscalYear($data->fiscalYear);

            // Update boolean options
            if (isset($data->reversePayment)) $group->setReversePayment($data->reversePayment);
            if (isset($data->taxFree)) $group->setTaxFree($data->taxFree);
            if (isset($data->inventory)) $group->setInventory($data->inventory);
            if (isset($data->batch)) $group->setBatch($data->batch);
            if (isset($data->operation)) $group->setOperation($data->operation);
            
            // Update account properties
            if (isset($data->buyAccount)) $group->setBuyAccount($data->buyAccount);
            if (isset($data->sellAccount)) $group->setSellAccount($data->sellAccount);
            if (isset($data->buyEuAccount)) $group->setBuyEuAccount($data->buyEuAccount);
            if (isset($data->sellEuAccount)) $group->setSellEuAccount($data->sellEuAccount);
            if (isset($data->buyOutsideEuAccount)) $group->setBuyOutsideEuAccount($data->buyOutsideEuAccount);
            if (isset($data->sellOutsideEuAccount)) $group->setSellOutsideEuAccount($data->sellOutsideEuAccount);
            
            $result = $group->save();
            
            if ($result === true) {
                $this->sendResponse(true, $group->toArray(), 'Product group updated successfully');
            } else {
                $this->sendResponse(false, null, is_string($result) ? $result : 'Failed to update product group', 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    protected function handleDelete($data)
    {

        // no delete method for product groups
        $this->sendResponse(false, null, 'DELETE method is not supported for Product groups', 405);
        return;
        /* try {
			// Take in id from URL parameters
			if (isset($_GET['id'])) {
				$data->id = $_GET['id'];
			}
			
            // Validate required fields
            $this->validateData($data, ['id']);
            
            $group = new VareGruppeModel($data->id);
            if (!$group->getId()) {
                $this->sendResponse(false, null, 'Product group not found', 404);
                return;
            }
            
            $result = $group->delete();
            
            if ($result) {
                $this->sendResponse(true, null, 'Product group deleted successfully');
            } else {
                $this->sendResponse(false, null, 'Failed to delete product group', 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        } */
    }
}

// Initialize and handle the request
$endpoint = new ProductGroupsEndpoint();
$endpoint->handleRequestMethod();
