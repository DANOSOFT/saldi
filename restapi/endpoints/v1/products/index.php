<?php

require_once '../../../models/lager/VareModel.php';
require_once '../../../core/BaseEndpoint.php';

class ProductsEndpoint extends BaseEndpoint
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function handleGet($id = null)
    {
        try {
            if ($id) {
                // Get single product
                $product = new VareModel($id);
                if ($product->getId()) {
                    $this->sendResponse(true, $product->toArray());
                } else {
                    $this->sendResponse(false, null, 'Product not found', 404);
                }
            } else {
                // Get all products with optional filtering
                $orderBy = $_GET['orderBy'] ?? 'id';
                $orderDirection = $_GET['orderDirection'] ?? 'ASC';
                $field = $_GET['field'] ?? null;
                $value = $_GET['value'] ?? null;
                $limit = $_GET['limit'] ?? 20;
                if($limit > 100 || $limit < 1) {
                    $limit = 20; // Enforce a maximum limit
                }
                
                if ($field && $value) {
                    // Search by specific field
                    $products = VareModel::findBy($field, $value);
                } else {
                    // Get all products
                    $products = VareModel::getAllItems($orderBy, $orderDirection, $limit);
                }
                
                $items = [];
                foreach ($products as $product) {
                    $items[] = $product->toArray();
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
            $this->validateData($data, ['sku', 'description']);
            
            $product = new VareModel();
            
            // Set basic properties
            if (isset($data->sku)) $product->setVarenr($data->sku);
            if (isset($data->barcode)) $product->setStregkode($data->barcode);
            if (isset($data->description)) $product->setBeskrivelse($data->description);
            if (isset($data->salesPrice)) $product->setSalgspris($data->salesPrice);
            if (isset($data->costPrice)) $product->setKostpris($data->costPrice);
            
            // Set additional properties
            if (isset($data->notes)) $product->setNotes($data->notes);
            if (isset($data->serialNumber)) $product->setSerienr($data->serialNumber);
            if (isset($data->collectionOfItems)) $product->setSamlevare($data->collectionOfItems);
            if (isset($data->partialItem)) $product->setDelvare($data->partialItem);
            if (isset($data->minInventory)) $product->setMinLager($data->minInventory);
            if (isset($data->maxInventory)) $product->setMaxLager($data->maxInventory);
            if (isset($data->location)) $product->setLocation($data->location);
            if (isset($data->group)) $product->setGruppe($data->group);
            
            // Set size/weight properties
            if (isset($data->netweight)) $product->setNetweight($data->netweight);
            if (isset($data->netweightunit)) $product->setNetweightunit($data->netweightunit);
            if (isset($data->grossweight)) $product->setGrossweight($data->grossweight);
            if (isset($data->grossweightunit)) $product->setGrossweightunit($data->grossweightunit);
            if (isset($data->length)) $product->setLength($data->length);
            if (isset($data->width)) $product->setWidth($data->width);
            if (isset($data->height)) $product->setHeight($data->height);
            if (isset($data->colli_webfreight)) $product->setColliWebfragt($data->colli_webfreight);
            
            $result = $product->save();
            
            if ($result === true) {
                $this->sendResponse(true, $product->toArray(), 'Product created successfully', 201);
            } else {
                $this->sendResponse(false, null, is_string($result) ? $result : 'Failed to create product', 400);
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
            
            $product = new VareModel($data->id);
            if (!$product->getId()) {
                $this->sendResponse(false, null, 'Product not found', 404);
                return;
            }
            
            // Set basic properties
            if (isset($data->sku)) $product->setVarenr($data->sku);
            if (isset($data->barcode)) $product->setStregkode($data->barcode);
            if (isset($data->description)) $product->setBeskrivelse($data->description);
            if (isset($data->salesPrice)) $product->setSalgspris($data->salesPrice);
            if (isset($data->costPrice)) $product->setKostpris($data->costPrice);
            
            // Set additional properties
            if (isset($data->notes)) $product->setNotes($data->notes);
            if (isset($data->serialNumber)) $product->setSerienr($data->serialNumber);
            if (isset($data->collectionOfItems)) $product->setSamlevare($data->collectionOfItems);
            if (isset($data->partialItem)) $product->setDelvare($data->partialItem);
            if (isset($data->minInventory)) $product->setMinLager($data->minInventory);
            if (isset($data->maxInventory)) $product->setMaxLager($data->maxInventory);
            if (isset($data->location)) $product->setLocation($data->location);
            if (isset($data->group)) $product->setGruppe($data->group);
            
            // Set size/weight properties
            if (isset($data->netweight)) $product->setNetweight($data->netweight);
            if (isset($data->netweightunit)) $product->setNetweightunit($data->netweightunit);
            if (isset($data->grossweight)) $product->setGrossweight($data->grossweight);
            if (isset($data->grossweightunit)) $product->setGrossweightunit($data->grossweightunit);
            if (isset($data->length)) $product->setLength($data->length);
            if (isset($data->width)) $product->setWidth($data->width);
            if (isset($data->height)) $product->setHeight($data->height);
            if (isset($data->colli_webfreight)) $product->setColliWebfragt($data->colli_webfreight);
            
            $result = $product->save();
            
            if ($result === true) {
                $this->sendResponse(true, $product->toArray(), 'Product updated successfully');
            } else {
                $this->sendResponse(false, null, is_string($result) ? $result : 'Failed to update product', 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    protected function handleDelete($data)
    {
        try {
            
            $id = isset($_GET['id'])
            ? (int)$_GET['id']
            : (isset($data->id) ? (int)$data->id : null);

            if (!$id) {
                $this->sendResponse(false, null, 'Product ID is required for deletion', 400);
                return;
            }

            $product = new VareModel($id);
            if (!$product->getId()) {
                $this->sendResponse(false, null, 'Product not found', 404);
                return;
            }
            
            $result = $product->delete();
            
            if ($result) {
                $this->sendResponse(true, null, 'Product deleted successfully');
            } else {
                $this->sendResponse(false, null, 'Failed to delete product', 400);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
}

// Initialize and handle the request
$endpoint = new ProductsEndpoint();
$endpoint->handleRequestMethod();
