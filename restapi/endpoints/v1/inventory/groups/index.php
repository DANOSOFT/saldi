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
            $this->validateData($data, ['kodenr', 'beskrivelse']);
            
            $group = new VareGruppeModel();
            
            // Set basic properties
            if (isset($data->kodenr)) $group->setKodenr($data->kodenr);
            if (isset($data->beskrivelse)) $group->setBeskrivelse($data->beskrivelse);
            if (isset($data->fiscal_year)) $group->setFiscalYear($data->fiscal_year);
            
            // Set boolean options
            if (isset($data->omv_bet)) $group->setOmvBet($data->omv_bet);
            if (isset($data->moms_fri)) $group->setMomsFri($data->moms_fri);
            if (isset($data->lager)) $group->setLager($data->lager);
            if (isset($data->batch)) $group->setBatch($data->batch);
            if (isset($data->operation)) $group->setOperation($data->operation);
            
            // Set account properties
            if (isset($data->buy_account)) $group->setBuyAccount($data->buy_account);
            if (isset($data->sell_account)) $group->setSellAccount($data->sell_account);
            if (isset($data->buy_eu_account)) $group->setBuyEuAccount($data->buy_eu_account);
            if (isset($data->sell_eu_account)) $group->setSellEuAccount($data->sell_eu_account);
            if (isset($data->buy_outside_eu_account)) $group->setBuyOutsideEuAccount($data->buy_outside_eu_account);
            if (isset($data->sell_outside_eu_account)) $group->setSellOutsideEuAccount($data->sell_outside_eu_account);
            
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
            if (isset($data->kodenr)) $group->setKodenr($data->kodenr);
            if (isset($data->beskrivelse)) $group->setBeskrivelse($data->beskrivelse);
            if (isset($data->fiscal_year)) $group->setFiscalYear($data->fiscal_year);
            
            // Update boolean options
            if (isset($data->omv_bet)) $group->setOmvBet($data->omv_bet);
            if (isset($data->moms_fri)) $group->setMomsFri($data->moms_fri);
            if (isset($data->lager)) $group->setLager($data->lager);
            if (isset($data->batch)) $group->setBatch($data->batch);
            if (isset($data->operation)) $group->setOperation($data->operation);
            
            // Update account properties
            if (isset($data->buy_account)) $group->setBuyAccount($data->buy_account);
            if (isset($data->sell_account)) $group->setSellAccount($data->sell_account);
            if (isset($data->buy_eu_account)) $group->setBuyEuAccount($data->buy_eu_account);
            if (isset($data->sell_eu_account)) $group->setSellEuAccount($data->sell_eu_account);
            if (isset($data->buy_outside_eu_account)) $group->setBuyOutsideEuAccount($data->buy_outside_eu_account);
            if (isset($data->sell_outside_eu_account)) $group->setSellOutsideEuAccount($data->sell_outside_eu_account);
            
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
            
            $result = $group->delete();
            
            if ($result) {
                $this->sendResponse(true, null, 'Product group deleted successfully');
            } else {
                $this->sendResponse(false, null, 'Failed to delete product group', 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
}

// Initialize and handle the request
$endpoint = new ProductGroupsEndpoint();
$endpoint->handleRequestMethod();
