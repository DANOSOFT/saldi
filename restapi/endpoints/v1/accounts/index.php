<?php

require_once '../../../models/finans/AccountModel.php';
require_once '../../../core/BaseEndpoint.php';

class AccountsEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function handleGet($id = null)
    {
        try {
            if ($id) {
                // Get single account
                $account = new AccountModel($id);
                if ($account->getId()) {
                    $this->sendResponse(true, $account->toArray());
                } else {
                    $this->sendResponse(false, null, 'Account not found', 404);
                }
            } else {
                // Get all accounts with optional filtering
                $orderBy = $_GET['orderBy'] ?? 'kontonr';
                $orderDirection = $_GET['orderDirection'] ?? 'ASC';
                $field = $_GET['field'] ?? null;
                $value = $_GET['value'] ?? null;
                $limit = $_GET['limit'] ?? 50;
                if($limit > 200 || $limit < 1) {
                    $limit = 50; // Enforce a maximum limit
                }
                
                if ($field && $value) {
                    // Search by specific field
                    $accounts = AccountModel::findBy($field, $value);
                } else {
                    // Get all accounts
                    $accounts = AccountModel::getAllItems($orderBy, $orderDirection, $limit);
                }
                
                $items = [];
                foreach ($accounts as $account) {
                    $items[] = $account->toArray();
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
            $this->validateData($data, ['kontonr', 'beskrivelse']);
            
            $account = new AccountModel();
            
            // Set basic properties
            if (isset($data->kontonr)) $account->setKontonr($data->kontonr);
            if (isset($data->beskrivelse)) $account->setBeskrivelse($data->beskrivelse);
            if (isset($data->kontotype)) $account->setKontotype($data->kontotype);
            if (isset($data->moms)) $account->setMoms($data->moms);
			$account->setFraKto(isset($data->fra_kto) ? $data->fra_kto : 0);
			$account->setTilKto(isset($data->til_kto) ? $data->til_kto : 0);
            if (isset($data->lukket)) $account->setLukket($data->lukket);
			$account->setPrimo(isset($data->primo) ? $data->primo : 0);
			$account->setSaldo(isset($data->saldo) ? $data->saldo : 0);
            if (isset($data->regnskabsaar)) $account->setRegnskabsaar($data->regnskabsaar);
            if (isset($data->genvej)) $account->setGenvej($data->genvej);
			$account->setOverforTil(isset($data->overfor_til) ? $data->overfor_til : 0);
            if (isset($data->anvendelse)) $account->setAnvendelse($data->anvendelse);
			$account->setModkonto(isset($data->modkonto) ? $data->modkonto : 0);
			$account->setValuta(isset($data->valuta) ? $data->valuta : 0);
			$account->setValutakurs(isset($data->valutakurs) ? $data->valutakurs : 0);
			$account->setMapTo(isset($data->map_to) ? $data->map_to : 0);

            $result = $account->save();
            
            if ($result === true) {
                $this->sendResponse(true, $account->toArray(), 'Account created successfully', 201);
            } else {
                $this->sendResponse(false, null, is_string($result) ? $result : 'Failed to create account', 400);
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
            
            $account = new AccountModel($data->id);
            if (!$account->getId()) {
                $this->sendResponse(false, null, 'Account not found', 404);
                return;
            }
            
            // Update properties
            if (isset($data->kontonr)) $account->setKontonr($data->kontonr);
            if (isset($data->beskrivelse)) $account->setBeskrivelse($data->beskrivelse);
            if (isset($data->kontotype)) $account->setKontotype($data->kontotype);
            if (isset($data->moms)) $account->setMoms($data->moms);
			$account->setFraKto(isset($data->fra_kto) ? $data->fra_kto : 0);
			$account->setTilKto(isset($data->til_kto) ? $data->til_kto : 0);
            if (isset($data->lukket)) $account->setLukket($data->lukket);
			$account->setPrimo(isset($data->primo) ? $data->primo : 0);
			$account->setSaldo(isset($data->saldo) ? $data->saldo : 0);
            if (isset($data->regnskabsaar)) $account->setRegnskabsaar($data->regnskabsaar);
            if (isset($data->genvej)) $account->setGenvej($data->genvej);
			$account->setOverforTil(isset($data->overfor_til) ? $data->overfor_til : 0);
            if (isset($data->anvendelse)) $account->setAnvendelse($data->anvendelse);
			$account->setModkonto(isset($data->modkonto) ? $data->modkonto : 0);
            $account->setValuta(isset($data->valuta) ? $data->valuta : 0);
			$account->setValutakurs(isset($data->valutakurs) ? $data->valutakurs : 0);
			$account->setMapTo(isset($data->map_to) ? $data->map_to : 0);
            
            $result = $account->save();
            
            if ($result === true) {
                $this->sendResponse(true, $account->toArray(), 'Account updated successfully');
            } else {
                $this->sendResponse(false, null, is_string($result) ? $result : 'Failed to update account', 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    protected function handleDelete($data)
    {
        // no delete operation for accounts
		$this->sendResponse(false, null, 'Delete operation is not allowed for accounts', 405);
    }
}

// Initialize and handle the request
$endpoint = new AccountsEndpoint();
$endpoint->handleRequestMethod();