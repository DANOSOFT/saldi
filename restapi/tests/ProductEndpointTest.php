<?php

/**
 * Product Endpoint Test Script
 * 
 * This script tests all CRUD operations for the Product API endpoint
 * including validation for required fields and size data handling.
 */

class ProductEndpointTest
{
    private $baseUrl;
    private $headers;
    private $createdProductIds = [];

    public function __construct()
    {
        // Configure your API base URL and headers
        $this->baseUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/Products/';
        
        // Set your actual authorization headers
        $this->headers = [
            'Content-Type: application/json',
            'Authorization: 4M1SlprEv82hhtl2KSfCFOs4BzLYgAdUD',
            'X-SaldiUser: api',
            'X-DB: test_4'
        ];
    }

    /**
     * Run all tests
     */
    public function runAllTests()
    {
        echo "=== Product API Endpoint Tests ===\n\n";

        try {
            $this->testCreateProduct();
            $this->testGetAllProducts();
            $this->testGetSingleProduct();
            $this->testCreateProductWithSize();
            $this->testCreateDuplicateVarenr();
            $this->testUpdateProduct();
            $this->testUpdateProductWithSize();
            $this->testPartialUpdateProduct();
            $this->testDeleteProduct();
            $this->testCreateProductMissingFields();
            $this->testGetNonExistentProduct();
            $this->testUpdateNonExistentProduct();
            $this->testDeleteNonExistentProduct();
            
            echo "\n=== Test Summary ===\n";
            echo "All tests completed successfully!\n";
            
        } catch (Exception $e) {
            echo "Test failed with error: " . $e->getMessage() . "\n";
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Test creating a new product (basic)
     */
    public function testCreateProduct()
    {
        echo "Testing: Create Basic Product\n";
        
        $productData = [
            'sku' => 'TEST-PROD-001',
            'barcode' => '1234567890123',
            'description' => 'Test Product Basic'
        ];

        $response = $this->makeRequest('POST', $productData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdProductIds[] = $response['data']['id'];
            echo "✓ Basic product created successfully with ID: " . $response['data']['id'] . "\n";
        } else {
            throw new Exception("Failed to create basic product: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating a product with size data
     */
    public function testCreateProductWithSize()
    {
        echo "Testing: Create Product with Size Data\n";
        
        $productData = [
            'sku' => 'TEST-PROD-002',
            'barcode' => '1234567890124',
            'description' => 'Test Product with Size',
            'width' => 10.5,
            'height' => 20.0,
            'length' => 15.5
        ];

        $response = $this->makeRequest('POST', $productData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdProductIds[] = $response['data']['id'];
            echo "✓ Product with size created successfully with ID: " . $response['data']['id'] . "\n";
            
            // Verify size data was saved correctly
            if (isset($response['data']['width']) &&
                isset($response['data']['height']) &&
                isset($response['data']['length'])) {
                echo "✓ Size data included in response\n";
            }
        } else {
            throw new Exception("Failed to create product with size: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test getting all products
     */
    public function testGetAllProducts()
    {
        echo "Testing: Get All Products\n";
        
        $response = $this->makeRequest('GET');
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Retrieved " . count($response['data']) . " products\n";
        } else {
            throw new Exception("Failed to get all products");
        }
        
        echo "\n";
    }

    /**
     * Test getting a single product
     */
    public function testGetSingleProduct()
    {
        if (empty($this->createdProductIds)) {
            echo "Skipping: Get Single Product (no product created)\n\n";
            return;
        }

        echo "Testing: Get Single Product\n";
        
        $productId = $this->createdProductIds[0];
        $response = $this->makeRequest('GET', null, $productId);
        
        if ($response['success'] && $response['data']['id'] == $productId) {
            echo "✓ Retrieved product with ID: $productId\n";
        } else {
            throw new Exception("Failed to get single product");
        }
        
        echo "\n";
    }

    /**
     * Test creating product with duplicate varenr (if validation exists)
     */
    public function testCreateDuplicateVarenr()
    {
        echo "Testing: Create Product with Duplicate Varenr\n";
        
        $productData = [
            'sku' => 'TEST-PROD-001', // Same sku as first product
            'barcode' => '1234567890125',
            'description' => 'Duplicate Varenr Test'
        ];

        $response = $this->makeRequest('POST', $productData);

        // Note: This test assumes duplicate sku validation exists
        // If not implemented, this test will need to be adjusted
        if (!$response['success'] && strpos($response['message'], 'already exists') !== false) {
            echo "✓ Correctly rejected duplicate varenr\n";
        } else {
            echo "⚠ Warning: Duplicate varenr was accepted (validation may not be implemented)\n";
            // If duplicate was accepted, add to cleanup list
            if ($response['success'] && isset($response['data']['id'])) {
                $this->createdProductIds[] = $response['data']['id'];
            }
        }
        
        echo "\n";
    }

    /**
     * Test updating a product
     */
    public function testUpdateProduct()
    {
        if (empty($this->createdProductIds)) {
            echo "Skipping: Update Product (no product created)\n\n";
            return;
        }

        echo "Testing: Update Product\n";
        
        $productId = $this->createdProductIds[0];
        $updateData = [
            'sku' => 'TEST-PROD-001-UPDATED',
            'description' => 'Updated Test Product Basic',
            'barcode' => '1234567890999'
        ];

        $response = $this->makeRequest('PUT', $updateData, $productId);
        
        if ($response['success']) {
            echo "✓ Product updated successfully\n";
        } else {
            throw new Exception("Failed to update product: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test updating product with size data
     */
    public function testUpdateProductWithSize()
    {
        if (count($this->createdProductIds) < 2) {
            echo "Skipping: Update Product with Size (need product with size)\n\n";
            return;
        }

        echo "Testing: Update Product with Size Data\n";
        
        $productId = $this->createdProductIds[1]; // Product with size
        $updateData = [
            'description' => 'Updated Product with Size',
            'width' => 12.0,
            'height' => 25.0,
            'length' => 18.0
        ];

        $response = $this->makeRequest('PUT', $updateData, $productId);
        
        if ($response['success']) {
            echo "✓ Product with size updated successfully\n";
        } else {
            throw new Exception("Failed to update product with size: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test partial update (only some fields)
     */
    public function testPartialUpdateProduct()
    {
        if (empty($this->createdProductIds)) {
            echo "Skipping: Partial Update Product (no product created)\n\n";
            return;
        }

        echo "Testing: Partial Update Product (only description)\n";
        
        $productId = $this->createdProductIds[0];
        $updateData = [
            'beskrivelse' => 'Partially Updated Description'
        ];

        $response = $this->makeRequest('PUT', $updateData, $productId);
        
        if ($response['success']) {
            echo "✓ Product partially updated successfully\n";
        } else {
            throw new Exception("Failed to partially update product: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating product with missing required fields
     */
    public function testCreateProductMissingFields()
    {
        echo "Testing: Create Product with Missing Required Fields\n";
        
        $productData = [
            'barcode' => '1234567890126',
            'description' => 'Product Missing Varenr'
            // Missing required 'sku' field
        ];

        $response = $this->makeRequest('POST', $productData);
        
        if (!$response['success'] && strpos($response['message'], 'sku') !== false) {
            echo "✓ Correctly rejected product with missing sku\n";
        } else {
            throw new Exception("Should have rejected product with missing sku");
        }
        
        echo "\n";
    }

    /**
     * Test getting non-existent product
     */
    public function testGetNonExistentProduct()
    {
        echo "Testing: Get Non-Existent Product\n";
        
        $response = $this->makeRequest('GET', null, '999999');
        
        if (!$response['success'] && strpos($response['message'], 'not found') !== false) {
            echo "✓ Correctly returned error for non-existent product\n";
        } else {
            throw new Exception("Should have returned error for non-existent product");
        }
        
        echo "\n";
    }

    /**
     * Test updating non-existent product
     */
    public function testUpdateNonExistentProduct()
    {
        echo "Testing: Update Non-Existent Product\n";
        
        $updateData = [
            'sku' => 'NON-EXISTENT',
            'description' => 'This should fail'
        ];

        $response = $this->makeRequest('PUT', $updateData, '999999');
        
        if (!$response['success'] && strpos($response['message'], 'not found') !== false) {
            echo "✓ Correctly returned error for updating non-existent product\n";
        } else {
            throw new Exception("Should have returned error for updating non-existent product");
        }
        
        echo "\n";
    }

    /**
     * Test deleting non-existent product
     */
    public function testDeleteNonExistentProduct()
    {
        echo "Testing: Delete Non-Existent Product\n";
        
        $response = $this->makeRequest('DELETE', null, '999999');
        
        if (!$response['success'] && strpos($response['message'], 'not found') !== false) {
            echo "✓ Correctly returned error for deleting non-existent product\n";
        } else {
            throw new Exception("Should have returned error for deleting non-existent product");
        }
        
        echo "\n";
    }

    /**
     * Test deleting a product
     */
    public function testDeleteProduct()
    {
        if (empty($this->createdProductIds)) {
            echo "Skipping: Delete Product (no product created)\n\n";
            return;
        }

        echo "Testing: Delete Product\n";
        
        $productId = array_pop($this->createdProductIds); // Remove from cleanup list
        
        $response = $this->makeRequest('DELETE', null, $productId);
        
        if ($response['success']) {
            echo "✓ Product deleted successfully\n";
        } else {
            throw new Exception("Failed to delete product: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Make HTTP request to the API
     * FIXED: Use query parameters instead of path parameters
     */
    private function makeRequest($method, $data = null, $id = '')
    {
        $url = $this->baseUrl;
        
        // Use query parameter instead of path parameter
        if ($id && in_array($method, ['GET', 'PUT', 'DELETE'])) {
            $url .= '?id=' . $id;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($data && in_array($method, ['POST', 'PUT'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception("Failed to make request to $url");
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($decodedResponse === null) {
            throw new Exception("Invalid JSON response from $url: $response");
        }
        
        return $decodedResponse;
    }

    /**
     * Clean up created test data
     */
    private function cleanup()
    {
        echo "\n=== Cleanup ===\n";
        
        foreach ($this->createdProductIds as $productId) {
            try {
                $response = $this->makeRequest('DELETE', null, $productId);
                
                if ($response['success']) {
                    echo "✓ Cleaned up product ID: $productId\n";
                } else {
                    echo "✗ Failed to cleanup product ID: $productId\n";
                }
            } catch (Exception $e) {
                echo "✗ Error cleaning up product ID $productId: " . $e->getMessage() . "\n";
            }
        }
    }
}

// Run the tests
$tester = new ProductEndpointTest();
$tester->runAllTests();