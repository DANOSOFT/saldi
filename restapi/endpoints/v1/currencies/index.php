<?php

require_once '../../../models/finans/CurrencyModel.php';
require_once '../../../core/BaseEndpoint.php';

class CurrenciesEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function handleGet($currencyCode = null)
    {
        try {
            if ($currencyCode) {
                // Get single currency
                $currency = new CurrencyModel($currencyCode);
                if ($currency->getId()) {
                    $this->sendResponse(true, $currency->toArray());
                } else {
                    $this->sendResponse(false, null, 'Currency not found', 404);
                }
            } else {
                // Get all currencies with optional filtering
                $orderBy = $_GET['orderBy'] ?? 'beskrivelse';
                $orderDirection = $_GET['orderDirection'] ?? 'ASC';
                $field = $_GET['field'] ?? null;
                $value = $_GET['value'] ?? null;
                $limit = $_GET['limit'] ?? 50;
                
                if ($limit > 200 || $limit < 1) {
                    $limit = 50; // Enforce a maximum limit
                }
                
                if ($field && $value) {
                    // Search by specific field
                    $currencies = CurrencyModel::findBy($field, $value);
                } else {
                    // Get all currencies
                    $currencies = CurrencyModel::getAllItems($orderBy, $orderDirection, $limit);
                }
                
                $items = [];
                foreach ($currencies as $currency) {
                    $items[] = $currency->toArray();
                }
                $this->sendResponse(true, $items);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    protected function handlePost($data)
    {
        // POST method not allowed for currencies (read-only endpoint)
        $this->sendResponse(false, null, 'POST method not allowed for currencies endpoint', 405);
    }

    protected function handlePut($data)
    {
        // PUT method not allowed for currencies (read-only endpoint)
        $this->sendResponse(false, null, 'PUT method not allowed for currencies endpoint', 405);
    }

    protected function handleDelete($data)
    {
        // DELETE method not allowed for currencies (read-only endpoint)
        $this->sendResponse(false, null, 'DELETE method not allowed for currencies endpoint', 405);
    }
}

// Initialize and handle the request
$endpoint = new CurrenciesEndpoint();
$endpoint->handleRequestMethod();
