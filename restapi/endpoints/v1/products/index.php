<?php

require_once '../../../models/lager/VareModel.php';
require_once '../../../core/BaseEndpoint.php';

class VareEndpoint extends BaseEndpoint
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
    }

    protected function handleGet($id = null)
    {
        // Check for ID in URL path or query parameter
        $productId = $this->getIdFromRequest($id);
        
        if ($productId) {
            $vare = new VareModel($productId);
            if ($vare->getId()) {
                $this->sendResponse(true, $vare->toArray());
            } else {
                $this->sendResponse(false, null, "Product not found", 404);
            }
        } else {
            // Get all items and convert each to an array
            $varer = VareModel::getAllItems();
            $items = [];
            foreach ($varer as $vare) {
                $items[] = $vare->toArray();
            }
            $this->sendResponse(true, $items);
        }
    }

    protected function handlePost($data)
    {
        // Convert stdClass to array if needed
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }
        
        // Validate required field
        if (!isset($data['varenr']) || empty($data['varenr'])) {
            $this->sendResponse(false, null, "Field 'varenr' is required", 400);
            return;
        }

        // TEMPORARILY DISABLE duplicate check to avoid database errors
        // TODO: Re-enable once VareModel::findBy is fixed
        /*
        try {
            $existingProducts = VareModel::findBy('varenr', $data['varenr']);
            if ($existingProducts !== false && !empty($existingProducts)) {
                $this->sendResponse(false, null, "Product with varenr '{$data['varenr']}' already exists", 409);
                return;
            }
        } catch (Exception $e) {
            error_log("Error checking for duplicate varenr: " . $e->getMessage());
        }
        */

        // Create new product
        $vare = new VareModel();
        $vare->setVarenr($data['varenr']);
        
        // Set optional fields if provided
        if (isset($data['stregkode']) && !empty($data['stregkode'])) {
            $vare->setStregkode($data['stregkode']);
        }
        if (isset($data['beskrivelse']) && !empty($data['beskrivelse'])) {
            $vare->setBeskrivelse($data['beskrivelse']);
        }
        if (isset($data['salgspris']) && is_numeric($data['salgspris'])) {
            $vare->setSalgspris($data['salgspris']);
        }
        if (isset($data['kostpris']) && is_numeric($data['kostpris'])) {
            $vare->setKostpris($data['kostpris']);
        }
        
        // Handle size data if provided and SizeModel exists
        if (isset($data['size']) && is_array($data['size']) && class_exists('SizeModel')) {
            $sizeData = [];
            if (isset($data['size']['dimensions'])) {
                $sizeData['width'] = $data['size']['dimensions']['width'] ?? 0;
                $sizeData['height'] = $data['size']['dimensions']['height'] ?? 0;
                $sizeData['length'] = $data['size']['dimensions']['length'] ?? 0;
            }
            if (isset($data['size']['weights'])) {
                $sizeData['netWeight'] = $data['size']['weights']['net']['value'] ?? 0;
                $sizeData['grossWeight'] = $data['size']['weights']['gross']['value'] ?? 0;
                $sizeData['netWeightUnit'] = $data['size']['weights']['net']['unit'] ?? 'kg';
                $sizeData['grossWeightUnit'] = $data['size']['weights']['gross']['unit'] ?? 'kg';
            }
            
            try {
                $size = new SizeModel($sizeData);
                $vare->setSize($size);
            } catch (Exception $e) {
                error_log("Error creating size model: " . $e->getMessage());
            }
        }

        // Save the product
        try {
            $result = $vare->save();
            if ($result === true) {
                // —— NEW: handle lagerstatus sub‐object ——
                if (
                    isset($data['lagerstatus']) &&
                    is_array($data['lagerstatus']) &&
                    class_exists('LagerStatusModel')
                ) {
                    $ls = new LagerStatusModel(
                        null,
                        $vare->getId(),
                        $data['lagerstatus']['lager'] ?? null
                    );
                    // set absolute stock
                    if (isset($data['lagerstatus']['beholdning'])) {
                        $ls->setBeholdning($data['lagerstatus']['beholdning']);
                    }
                    // optionally adjust relative stock
                    if (isset($data['lagerstatus']['adjust'])) {
                        $ls->adjustQuantity($data['lagerstatus']['adjust']);
                    }
                    if (isset($data['lagerstatus']['lok'])) {
                        $ls->setLok($data['lagerstatus']['lok']);
                    }
                    if (isset($data['lagerstatus']['variant_id'])) {
                        $ls->setVariantId($data['lagerstatus']['variant_id']);
                    }
                    $ls->save();
                }

                $this->sendResponse(true, $vare->toArray(), "Product saved", $this->getRequestMethod()==='POST'?201:200);
            } else {
                // Handle both string error messages and boolean false
                $errorMessage = is_string($result) ? $result : "Failed to create product";
                $this->sendResponse(false, null, $errorMessage, 500);
            }
        } catch (Exception $e) {
            $this->sendResponse(false, null, "Error saving product: " . $e->getMessage(), 500);
        }
    }

    protected function handlePut($data)
    {
        // Get ID from request
        $productId = $this->getIdFromRequest();
        
        if (!$productId) {
            $this->sendResponse(false, null, "Product ID is required for update", 400);
            return;
        }

        // Convert stdClass to array if needed
        if (is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }
        
        // Load existing product
        try {
            $vare = new VareModel($productId);
            if (!$vare->getId()) {
                $this->sendResponse(false, null, "Product not found", 404);
                return;
            }
        } catch (Exception $e) {
            $this->sendResponse(false, null, "Error loading product: " . $e->getMessage(), 500);
            return;
        }

        // Update fields if provided
        if (isset($data['varenr']) && !empty($data['varenr'])) {
            $vare->setVarenr($data['varenr']);
        }
        if (isset($data['stregkode'])) {
            $vare->setStregkode($data['stregkode']);
        }
        if (isset($data['beskrivelse'])) {
            $vare->setBeskrivelse($data['beskrivelse']);
        }
        if (isset($data['salgspris']) && is_numeric($data['salgspris'])) {
            $vare->setSalgspris($data['salgspris']);
        }
        if (isset($data['kostpris']) && is_numeric($data['kostpris'])) {
            $vare->setKostpris($data['kostpris']);
        }
        
        // Handle size data if provided
        if (isset($data['size']) && is_array($data['size']) && class_exists('SizeModel')) {
            try {
                $currentSize = $vare->getSize();
                $sizeData = [];
                
                if (isset($data['size']['dimensions'])) {
                    $sizeData['width'] = $data['size']['dimensions']['width'] ?? ($currentSize ? $currentSize->getWidth() : 0);
                    $sizeData['height'] = $data['size']['dimensions']['height'] ?? ($currentSize ? $currentSize->getHeight() : 0);
                    $sizeData['length'] = $data['size']['dimensions']['length'] ?? ($currentSize ? $currentSize->getLength() : 0);
                } else {
                    $sizeData['width'] = $currentSize ? $currentSize->getWidth() : 0;
                    $sizeData['height'] = $currentSize ? $currentSize->getHeight() : 0;
                    $sizeData['length'] = $currentSize ? $currentSize->getLength() : 0;
                }
                
                if (isset($data['size']['weights'])) {
                    $sizeData['netWeight'] = $data['size']['weights']['net']['value'] ?? ($currentSize ? $currentSize->getNetWeight() : 0);
                    $sizeData['grossWeight'] = $data['size']['weights']['gross']['value'] ?? ($currentSize ? $currentSize->getGrossWeight() : 0);
                    $sizeData['netWeightUnit'] = $data['size']['weights']['net']['unit'] ?? ($currentSize ? $currentSize->getNetWeightUnit() : 'kg');
                    $sizeData['grossWeightUnit'] = $data['size']['weights']['gross']['unit'] ?? ($currentSize ? $currentSize->getGrossWeightUnit() : 'kg');
                } else {
                    $sizeData['netWeight'] = $currentSize ? $currentSize->getNetWeight() : 0;
                    $sizeData['grossWeight'] = $currentSize ? $currentSize->getGrossWeight() : 0;
                    $sizeData['netWeightUnit'] = $currentSize ? $currentSize->getNetWeightUnit() : 'kg';
                    $sizeData['grossWeightUnit'] = $currentSize ? $currentSize->getGrossWeightUnit() : 'kg';
                }
                
                $size = new SizeModel($sizeData);
                $vare->setSize($size);
            } catch (Exception $e) {
                error_log("Error updating size model: " . $e->getMessage());
            }
        }

        // Save the updated product
        try {
            $result = $vare->save();
            if ($result === true) {
                // —— NEW: handle lagerstatus sub‐object ——
                if (
                    isset($data['lagerstatus']) &&
                    is_array($data['lagerstatus']) &&
                    class_exists('LagerStatusModel')
                ) {
                    $ls = new LagerStatusModel(
                        null,
                        $vare->getId(),
                        $data['lagerstatus']['lager'] ?? null
                    );
                    // set absolute stock
                    if (isset($data['lagerstatus']['beholdning'])) {
                        $ls->setBeholdning($data['lagerstatus']['beholdning']);
                    }
                    // optionally adjust relative stock
                    if (isset($data['lagerstatus']['adjust'])) {
                        $ls->adjustQuantity($data['lagerstatus']['adjust']);
                    }
                    if (isset($data['lagerstatus']['lok'])) {
                        $ls->setLok($data['lagerstatus']['lok']);
                    }
                    if (isset($data['lagerstatus']['variant_id'])) {
                        $ls->setVariantId($data['lagerstatus']['variant_id']);
                    }
                    $ls->save();
                }

                $this->sendResponse(true, $vare->toArray(), "Product updated successfully");
            } else {
                $errorMessage = is_string($result) ? $result : "Failed to update product";
                $this->sendResponse(false, null, $errorMessage, 500);
            }
        } catch (Exception $e) {
            $this->sendResponse(false, null, "Error updating product: " . $e->getMessage(), 500);
        }
    }

    protected function handleDelete($data)
    {
        // Get ID from request
        $productId = $this->getIdFromRequest();
        
        if (!$productId) {
            $this->sendResponse(false, null, "Product ID is required for deletion", 400);
            return;
        }

        // Load existing product
        try {
            $vare = new VareModel($productId);
            if (!$vare->getId()) {
                $this->sendResponse(false, null, "Product not found", 404);
                return;
            }
        } catch (Exception $e) {
            $this->sendResponse(false, null, "Error loading product: " . $e->getMessage(), 500);
            return;
        }

        // Delete the product
        try {
            $result = $vare->delete();
            if ($result) {
                $this->sendResponse(true, null, "Product deleted successfully");
            } else {
                $this->sendResponse(false, null, "Failed to delete product", 500);
            }
        } catch (Exception $e) {
            $this->sendResponse(false, null, "Error deleting product: " . $e->getMessage(), 500);
        }
    }

    /**
     * Helper method to get request data
     */
    protected function getRequestData()
    {
        return json_decode(file_get_contents("php://input"), true);
    }

    /**
     * Helper method to get ID from various sources
     */
    private function getIdFromRequest($paramId = null)
    {
        // Check parameter first
        if ($paramId) {
            return $paramId;
        }
        
        // Check query parameter
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            return (int)$_GET['id'];
        }
        
        // Check URL path for RESTful style
        $requestUri = $_SERVER['REQUEST_URI'];
        $path = parse_url($requestUri, PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));
        
        // Look for numeric ID at the end of the path
        $lastPart = end($pathParts);
        if (is_numeric($lastPart)) {
            return (int)$lastPart;
        }
        
        return null;
    }
}

$endpoint = new VareEndpoint();
$endpoint->handleRequestMethod();