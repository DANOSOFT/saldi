<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/inventory/status/index.php --- ver 5.0.0 --- 2026.10.07
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Declare precise module rights for reads and writes.
// 20261001 CDX/LH Route legacy quantity writes through the stock engine; preserve response fields.
// 20261001 CDX/LH Persist locations in tenant context without date-dependent fiscal-year validation.
// 20261007 CDX/LH Separate configuration fiscal years from posting date validation.

require_once __DIR__ . '/../../../../models/lager/LagerStatusModel.php';
require_once __DIR__ . '/../../../../core/BaseEndpoint.php';

class InventoryStatusEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module' => 'varer', 'action' => ifset($_SERVER, 'REQUEST_METHOD') === 'GET' ? 'read' : 'write'];
    }

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
        $this->validateData($data, ['inventory','productId','quantity']);
        $status = new LagerStatusModel();
        $this->assignFields($status, $data);
        $this->persist($status, true, null, 'Inventory status created successfully', 201);
    }

    protected function handlePut($data)
    {
        $data->id = ifset($_GET,'id',ifset($data,'id'));
        $this->validateData($data, ['id']);
        $status = $this->existing($data->id);
        $this->assignFields($status, $data);
        $this->persist($status, isset($data->quantity), null, 'Inventory status updated successfully');
    }

    protected function handlePatch($data)
    {
        $data->id = ifset($_GET,'id',ifset($data,'id'));
        if (!isset($data->action)) {
            $this->sendResponse(false, null, 'Action parameter required for PATCH requests', 400);
        }
        if (!in_array($data->action, ['adjust_quantity','set_quantity'], true)) {
            $this->sendResponse(false, null, 'Invalid action specified', 400);
        }
        $adjust = $data->action === 'adjust_quantity';
        $this->validateData($data, ['id', $adjust ? 'amount' : 'quantity']);
        $status = $this->existing($data->id);
        if (isset($data->variantId)) {
            $this->validateInteger($data->variantId, 'variantId', true);
            $status->setVariantId($data->variantId);
        }
        if ($adjust) {
            $amount = $this->quantity($data->amount);
        } else {
            $status->setQuantity($this->quantity($data->quantity));
            $amount = null;
        }
        $this->persist($status, true, $amount, $adjust ? 'Inventory quantity adjusted successfully' : 'Inventory quantity updated successfully');
    }

    /** @return LagerStatusModel The legacy id target, or the existing 404 envelope. */
    private function existing($id): LagerStatusModel
    {
        $this->validateInteger($id, 'id');
        $status = new LagerStatusModel((int)$id);
        if (!$status->getId()) {
            $this->sendResponse(false, null, 'Inventory status not found', 404);
        }
        return $status;
    }

    /** Validate external ids before a setter can cast away an invalid value. */
    private function validateInteger($value, string $field, bool $zero = false): void
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^\d+$/D', (string)$value)
            || (float)$value > 2147483647 || (int)$value < ($zero ? 0 : 1)) {
            $this->sendError('validation_failed', 'Invalid inventory field', 422, ['details'=>['field'=>$field]]);
        }
    }

    /** @return string A validated finite legacy number/string quantity, with at most three decimals. */
    private function quantity($value): string
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            $this->sendError('validation_failed', 'Invalid inventory quantity', 422);
        }
        try {
            return stock_quantity((string)$value);
        } catch (InvalidArgumentException $error) {
            $this->sendError('validation_failed', 'Invalid inventory quantity', 422);
        }
    }

    private function assignFields(LagerStatusModel $status, $data): void
    {
        foreach (['inventory'=>'setInventory','productId'=>'setProductId','variantId'=>'setVariantId'] as $field=>$setter) {
            if (isset($data->$field)) {
                $this->validateInteger($data->$field, $field, $field !== 'productId');
                $status->$setter($data->$field);
            }
        }
        if (isset($data->quantity)) {
            $status->setQuantity($this->quantity($data->quantity));
        }
        foreach (['location','location2','location3','location4','location5'] as $field) {
            if (isset($data->$field)) {
                if (!is_string($data->$field)) {
                    $this->sendError('validation_failed', 'Invalid inventory location', 422, ['details'=>['field'=>$field]]);
                }
                $setter = 'set' . ucfirst($field);
                $status->$setter($data->$field);
            }
        }
    }

    /** Commit stock, batches, log and locations together, then synchronize outside the transaction. */
    private function persist(LagerStatusModel $status, bool $writeQuantity, ?string $adjustment, string $message, int $httpCode = 200): void
    {
        try {
            if ($writeQuantity) {
                $payload = JWTAuth::validateToken();
                $context = EngineContext::forConfig($this->conn,$this->db,(int)JWTAuth::tenantId($payload),$this->username,(int)$this->userId);
                $result = LegacyEngine::run($context, function () use ($status,$adjustment): array {
                    // The section owns the transaction and detects legacy write failures before commit.
                    $result = stock_item_section([(int)$status->getProductId()], static fn(): array => $status->save(true,$adjustment));
                    if ($result['sync']) {
                        stock_shop_sync([$result['sync']]);
                    }
                    return $result;
                });
            } else {
                $result = $this->persistLocations($status);
            }
            if ($writeQuantity) {
                header('Deprecation: true');
                header('Link: </restapi/endpoints/v1/stock/counts/index.php>; rel="successor-version"');
            }
            if (!$result['saved']) {
                $this->sendResponse(false, null, $result['message'], 400);
            }
            $this->sendResponse(true, $status->toArray(), $message, $httpCode);
        } catch (MobileT2Error $error) {
            $this->sendError($error->error, 'Inventory change refused', $error->status, ['details'=>$error->details]);
        } catch (InvalidArgumentException $error) {
            $this->sendError('validation_failed', 'Invalid inventory quantity', 422);
        } catch (Throwable $error) {
            $this->handleError($error);
        }
    }

    /** @return array{saved:bool,message:?string,sync:array|null} Locations saved in the authenticated tenant. */
    private function persistLocations(LagerStatusModel $status): array
    {
        $saved = [];
        foreach (['connection'=>$this->conn,'db'=>$this->db,'db_type'=>'postgresql'] as $key=>$value) {
            $saved[$key] = [array_key_exists($key, $GLOBALS), ifset($GLOBALS, $key)];
            $GLOBALS[$key] = $value;
        }
        try {
            return stock_item_section([(int)$status->getProductId()], static fn(): array => $status->save(false));
        } finally {
            foreach ($saved as $key=>[$exists,$value]) {
                if ($exists) {
                    $GLOBALS[$key] = $value;
                } else {
                    unset($GLOBALS[$key]);
                }
            }
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
