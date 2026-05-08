<?php

require_once '../../../models/finans/AccountModel.php';
require_once '../../../core/BaseEndpoint.php';

class AccountsEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }

    // Field mapping from English to Danish
    private function mapEnglishToDanish($field) {
        $fieldMap = [
            'accountNumber' => 'kontonr',
            'description' => 'beskrivelse',
            'accountType' => 'kontotype',
            'vat' => 'moms',
            'fromAccount' => 'fra_kto',
            'toAccount' => 'til_kto',
            'closed' => 'lukket',
            'openingBalance' => 'primo',
            'balance' => 'saldo',
            'shortcut' => 'genvej',
            'transferTo' => 'overfor_til',
            'usage' => 'anvendelse',
            'contraAccount' => 'modkonto',
            'currency' => 'valuta',
            'exchangeRate' => 'valutakurs',
            'mapTo' => 'map_to'
        ];
        
        return $fieldMap[$field] ?? $field;
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
                $orderBy = $_GET['orderBy'] ?? 'accountNumber';
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
            // Validate required fields - accept both English and Danish names
            $hasAccountNumber = isset($data->accountNumber) || isset($data->kontonr);
            $hasDescription = isset($data->description) || isset($data->beskrivelse);
            
            if (!$hasAccountNumber || !$hasDescription) {
                $this->sendResponse(false, null, 'Account number and description are required', 400);
                return;
            }
            
            $account = new AccountModel();
            
            // Set basic properties - support both English and Danish field names
            if (isset($data->accountNumber)) $account->setKontonr($data->accountNumber);
            elseif (isset($data->kontonr)) $account->setKontonr($data->kontonr);
            
            if (isset($data->description)) $account->setBeskrivelse($data->description);
            elseif (isset($data->beskrivelse)) $account->setBeskrivelse($data->beskrivelse);
            
            if (isset($data->accountType)) $account->setKontotype($data->accountType);
            elseif (isset($data->kontotype)) $account->setKontotype($data->kontotype);
            
            if (isset($data->vat)) $account->setMoms($data->vat);
            elseif (isset($data->moms)) $account->setMoms($data->moms);
            
            // Handle numeric fields with English names first, then Danish, then defaults
            $account->setFraKto(
                isset($data->fromAccount) ? $data->fromAccount : 
                (isset($data->fra_kto) ? $data->fra_kto : 0)
            );
            
            $account->setTilKto(
                isset($data->toAccount) ? $data->toAccount : 
                (isset($data->til_kto) ? $data->til_kto : 0)
            );
            
            if (isset($data->closed)) $account->setLukket($data->closed);
            elseif (isset($data->lukket)) $account->setLukket($data->lukket);
            
            $account->setPrimo(
                isset($data->openingBalance) ? $data->openingBalance : 
                (isset($data->primo) ? $data->primo : 0)
            );
            
            $account->setSaldo(
                isset($data->balance) ? $data->balance : 
                (isset($data->saldo) ? $data->saldo : 0)
            );
            
            if (isset($data->shortcut)) $account->setGenvej($data->shortcut);
            elseif (isset($data->genvej)) $account->setGenvej($data->genvej);
            
            $account->setOverforTil(
                isset($data->transferTo) ? $data->transferTo : 
                (isset($data->overfor_til) ? $data->overfor_til : 0)
            );
            
            if (isset($data->usage)) $account->setAnvendelse($data->usage);
            elseif (isset($data->anvendelse)) $account->setAnvendelse($data->anvendelse);
            
            $account->setModkonto(
                isset($data->contraAccount) ? $data->contraAccount : 
                (isset($data->modkonto) ? $data->modkonto : 0)
            );
            
            $account->setValuta(
                isset($data->currency) ? $data->currency : 
                (isset($data->valuta) ? $data->valuta : 0)
            );
            
            $account->setValutakurs(
                isset($data->exchangeRate) ? $data->exchangeRate : 
                (isset($data->valutakurs) ? $data->valutakurs : 0)
            );
            
            $account->setMapTo(
                isset($data->mapTo) ? $data->mapTo : 
                (isset($data->map_to) ? $data->map_to : 0)
            );

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
            
            // Update properties - support both English and Danish field names
            if (isset($data->accountNumber)) $account->setKontonr($data->accountNumber);
            elseif (isset($data->kontonr)) $account->setKontonr($data->kontonr);
            
            if (isset($data->description)) $account->setBeskrivelse($data->description);
            elseif (isset($data->beskrivelse)) $account->setBeskrivelse($data->beskrivelse);
            
            if (isset($data->accountType)) $account->setKontotype($data->accountType);
            elseif (isset($data->kontotype)) $account->setKontotype($data->kontotype);
            
            if (isset($data->vat)) $account->setMoms($data->vat);
            elseif (isset($data->moms)) $account->setMoms($data->moms);
            
            if (isset($data->fromAccount)) $account->setFraKto($data->fromAccount);
            elseif (isset($data->fra_kto)) $account->setFraKto($data->fra_kto);
            
            if (isset($data->toAccount)) $account->setTilKto($data->toAccount);
            elseif (isset($data->til_kto)) $account->setTilKto($data->til_kto);
            
            if (isset($data->closed)) $account->setLukket($data->closed);
            elseif (isset($data->lukket)) $account->setLukket($data->lukket);
            
            if (isset($data->openingBalance)) $account->setPrimo($data->openingBalance);
            elseif (isset($data->primo)) $account->setPrimo($data->primo);
            
            if (isset($data->balance)) $account->setSaldo($data->balance);
            elseif (isset($data->saldo)) $account->setSaldo($data->saldo);
            
            if (isset($data->shortcut)) $account->setGenvej($data->shortcut);
            elseif (isset($data->genvej)) $account->setGenvej($data->genvej);
            
            if (isset($data->transferTo)) $account->setOverforTil($data->transferTo);
            elseif (isset($data->overfor_til)) $account->setOverforTil($data->overfor_til);
            
            if (isset($data->usage)) $account->setAnvendelse($data->usage);
            elseif (isset($data->anvendelse)) $account->setAnvendelse($data->anvendelse);
            
            if (isset($data->contraAccount)) $account->setModkonto($data->contraAccount);
            elseif (isset($data->modkonto)) $account->setModkonto($data->modkonto);
            
            if (isset($data->currency)) $account->setValuta($data->currency);
            elseif (isset($data->valuta)) $account->setValuta($data->valuta);
            
            if (isset($data->exchangeRate)) $account->setValutakurs($data->exchangeRate);
            elseif (isset($data->valutakurs)) $account->setValutakurs($data->valutakurs);
            
            if (isset($data->mapTo)) $account->setMapTo($data->mapTo);
            elseif (isset($data->map_to)) $account->setMapTo($data->map_to);
            
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