<?php

/**
 * Inventory Products Endpoint Test Script
 * 
 * This script tests all CRUD operations for the Products API endpoint
 * including validation, size data handling, and inventory integration.
 */

class InventoryProductsEndpointTest
{
    private $baseUrl;
    private $headers;
    private $createdProductIds = [];

    public function __construct()
    {
        // Configure your API base URL and headers
        $this->baseUrl = 'https://yourdomain.com/restapi/endpoints/v1/products/';
        
        // Set your actual authorization headers - UPDATE THESE VALUES
        $this->headers = [
            'Content-Type: application/json',
            'Authorization: YOUR_API_KEY_HERE',
            'X-SaldiUser: YOUR_USERNAME_HERE',
            'X-DB: YOUR_DATABASE_HERE'
        ];
    }

    /**
     * Run all tests
     */
    public function runAllTests()
    {
        echo "=== Inventory Products API Endpoint Tests ===\n\n";

        try {
            $this->testCreateProduct();
            $this->testGetAllProducts();
            $this->testGetSingleProduct();
            $this->testCreateProductWithFullData();
            $this->testCreateDuplicateVarenr();
            $this->testUpdateProduct();
            $this->testUpdateProductWithSizeData();
            $this->testPartialUpdateProduct();
            $this->testSearchProducts();
            $this->testOrderingProducts();
            $this->testDeleteProduct();
            $this->testCreateProductMissingFields();
            $this->testGetNonExistentProduct();
            $this->testUpdateNonExistentProduct();
            $this->testDeleteNonExistentProduct();
            
            echo "\n=== Test Summary ===\n";
            echo "All Products API tests completed successfully!\n";
            
        } catch (Exception $e) {
            echo "Test failed with error: " . $e->getMessage() . "\n";
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Test creating a basic product
     */
    public function testCreateProduct()
    {
        echo "Testing: Create Basic Product\n";
        
        $productData = [
            'varenr' => 'TEST-PROD-' . time(),
            'beskrivelse' => 'Test Product Basic',
            'salgspris' => 99.99,
            'kostpris' => 49.99
        ];

        $response = $this->makeRequest('POST', $productData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdProductIds[] = $response['data']['id'];
            echo "✓ Product created successfully with ID: " . $response['data']['id'] . "\n";
            
            // Verify product data
            if ($response['data']['varenr'] === $productData['varenr']) {
                echo "✓ Product number correctly set\n";
            } else {
                throw new Exception("Product number mismatch");
            }
        } else {
            throw new Exception("Failed to create product: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating a product with full data including size/weight
     */
    public function testCreateProductWithFullData()
    {
        echo "Testing: Create Product with Full Data\n";
        
        $productData = [
            'varenr' => 'TEST-FULL-' . time(),
            'stregkode' => '1234567890123',
            'beskrivelse' => 'Test Product with Full Data',
            'salgspris' => 199.99,
            'kostpris' => 99.99,
            'notes' => 'This is a test product with all fields',
            'serienr' => 'SN-' . time(),
            'min_lager' => 10,
            'max_lager' => 100,
            'location' => 'A1-01-01',
            'gruppe' => 1,
            'netweight' => 2.5,
            'netweightunit' => 'kg',
            'grossweight' => 3.0,
            'grossweightunit' => 'kg',
            'length' => 25.0,
            'width' => 15.0,
            'height' => 10.0,
            'colli_webfragt' => 1.0
        ];

        $response = $this->makeRequest('POST', $productData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdProductIds[] = $response['data']['id'];
            echo "✓ Full product created successfully with ID: " . $response['data']['id'] . "\n";
            
            // Verify key fields
            $data = $response['data'];
            if ($data['stregkode'] === $productData['stregkode']) {
                echo "✓ Barcode correctly set\n";
            }
            if ($data['min_lager'] == $productData['min_lager']) {
                echo "✓ Min stock level correctly set\n";
            }
            if ($data['netweight'] == $productData['netweight']) {
                echo "✓ Net weight correctly set\n";
            }
        } else {
            throw new Exception("Failed to create full product: " . ($response['message'] ?? 'Unknown error'));
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
            
            // Check if our created products are in the list
            $foundIds = array_column($response['data'], 'id');
            foreach ($this->createdProductIds as $createdId) {
                if (in_array($createdId, $foundIds)) {
                    echo "✓ Created product ID $createdId found in list\n";
                }
            }
        } else {
            throw new Exception("Failed to get products: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test getting a single product
     */
    public function testGetSingleProduct()
    {
        echo "Testing: Get Single Product\n";
        
        if (empty($this->createdProductIds)) {
            throw new Exception("No products created to test with");
        }
        
        $productId = $this->createdProductIds[0];
        $response = $this->makeRequest('GET', null, ['id' => $productId]);
        
        if ($response['success'] && isset($response['data']['id'])) {
            echo "✓ Retrieved single product with ID: " . $response['data']['id'] . "\n";
            
            if ($response['data']['id'] == $productId) {
                echo "✓ Correct product returned\n";
            } else {
                throw new Exception("Wrong product returned");
            }
        } else {
            throw new Exception("Failed to get single product: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test searching products
     */
    public function testSearchProducts()
    {
        echo "Testing: Search Products\n";
        
        // Search by field
        $response = $this->makeRequest('GET', null, [
            'field' => 'beskrivelse',
            'value' => 'Test Product'
        ]);
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Search returned " . count($response['data']) . " results\n";
            
            // Verify search results contain our search term
            foreach ($response['data'] as $product) {
                if (strpos($product['beskrivelse'], 'Test Product') !== false) {
                    echo "✓ Search result contains search term\n";
                    break;
                }
            }
        } else {
            throw new Exception("Failed to search products: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test ordering products
     */
    public function testOrderingProducts()
    {
        echo "Testing: Product Ordering\n";
        
        // Test ordering by description DESC
        $response = $this->makeRequest('GET', null, [
            'orderBy' => 'beskrivelse',
            'orderDirection' => 'DESC'
        ]);
        
        if ($response['success'] && is_array($response['data']) && count($response['data']) > 1) {
            echo "✓ Ordered products retrieved\n";
            
            // Check if ordering is working (first item should be >= second alphabetically)
            if ($response['data'][0]['beskrivelse'] >= $response['data'][1]['beskrivelse']) {
                echo "✓ Products correctly ordered DESC by description\n";
            }
        } else {
            echo "⚠ Could not verify ordering (insufficient data)\n";
        }
        
        echo "\n";
    }

    /**
     * Test updating a product
     */
    public function testUpdateProduct()
    {
        echo "Testing: Update Product\n";
        
        if (empty($this->createdProductIds)) {
            throw new Exception("No products created to test with");
        }
        
        $productId = $this->createdProductIds[0];
        $updateData = [
            'id' => $productId,
            'beskrivelse' => 'Updated Test Product Description',
            'salgspris' => 149.99,
            'notes' => 'This product has been updated'
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if ($response['success']) {
            echo "✓ Product updated successfully\n";
            
            // Verify the update
            $getResponse = $this->makeRequest('GET', null, ['id' => $productId]);
            if ($getResponse['success'] && $getResponse['data']['beskrivelse'] === $updateData['beskrivelse']) {
                echo "✓ Product description correctly updated\n";
            }
            if ($getResponse['success'] && $getResponse['data']['salgspris'] == $updateData['salgspris']) {
                echo "✓ Product price correctly updated\n";
            }
        } else {
            throw new Exception("Failed to update product: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test updating product with size data
     */
    public function testUpdateProductWithSizeData()
    {
        echo "Testing: Update Product with Size Data\n";
        
        if (count($this->createdProductIds) < 2) {
            echo "⚠ Skipping size data update test (insufficient products)\n\n";
            return;
        }
        
        $productId = $this->createdProductIds[1];
        $updateData = [
            'id' => $productId,
            'length' => 30.0,
            'width' => 20.0,
            'height' => 15.0,
            'netweight' => 5.0,
            'grossweight' => 6.0
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if ($response['success']) {
            echo "✓ Product size data updated successfully\n";
            
            // Verify the update
            $getResponse = $this->makeRequest('GET', null, ['id' => $productId]);
            if ($getResponse['success']) {
                $data = $getResponse['data'];
                if ($data['length'] == $updateData['length'] && $data['width'] == $updateData['width']) {
                    echo "✓ Dimensions correctly updated\n";
                }
                if ($data['netweight'] == $updateData['netweight']) {
                    echo "✓ Weight correctly updated\n";
                }
            }
        } else {
            throw new Exception("Failed to update product size data: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating duplicate product number
     */
    public function testCreateDuplicateVarenr()
    {
        echo "Testing: Create Duplicate Product Number\n";
        
        if (empty($this->createdProductIds)) {
            throw new Exception("No products created to test with");
        }
        
        // Get the first product's varenr
        $firstProductResponse = $this->makeRequest('GET', null, ['id' => $this->createdProductIds[0]]);
        if (!$firstProductResponse['success']) {
            throw new Exception("Could not get first product for duplicate test");
        }
        
        $duplicateData = [
            'varenr' => $firstProductResponse['data']['varenr'], // Use same product number
            'beskrivelse' => 'Duplicate Product Test'
        ];

        $response = $this->makeRequest('POST', $duplicateData);
        
        if (!$response['success']) {
            echo "✓ Duplicate product number correctly rejected\n";
        } else {
            echo "⚠ Duplicate product number was allowed (may be acceptable)\n";
            // Clean up if it was created
            if (isset($response['data']['id'])) {
                $this->createdProductIds[] = $response['data']['id'];
            }
        }
        
        echo "\n";
    }

    /**
     * Test deleting a product
     */
    public function testDeleteProduct()
    {
        echo "Testing: Delete Product\n";
        
        if (empty($this->createdProductIds)) {
            throw new Exception("No products created to test with");
        }
        
        $productId = array_pop($this->createdProductIds); // Remove from our tracking
        $deleteData = ['id' => $productId];

        $response = $this->makeRequest('DELETE', $deleteData);
        
        if ($response['success']) {
            echo "✓ Product deleted successfully\n";
            
            // Verify deletion
            $getResponse = $this->makeRequest('GET', null, ['id' => $productId]);
            if (!$getResponse['success'] && $getResponse['message'] === 'Product not found') {
                echo "✓ Product correctly removed from database\n";
            }
        } else {
            throw new Exception("Failed to delete product: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating product with missing required fields
     */
    public function testCreateProductMissingFields()
    {
        echo "Testing: Create Product Missing Required Fields\n";
        
        $incompleteData = [
            'beskrivelse' => 'Product without number'
            // Missing required 'varenr' field
        ];

        $response = $this->makeRequest('POST', $incompleteData);
        
        if (!$response['success']) {
            echo "✓ Missing required fields correctly rejected\n";
        } else {
            throw new Exception("Product creation with missing fields should have failed");
        }
        
        echo "\n";
    }

    /**
     * Test getting non-existent product
     */
    public function testGetNonExistentProduct()
    {
        echo "Testing: Get Non-existent Product\n";
        
        $response = $this->makeRequest('GET', null, ['id' => 999999]);
        
        if (!$response['success'] && strpos($response['message'], 'not found') !== false) {
            echo "✓ Non-existent product correctly returns 404\n";
        } else {
            throw new Exception("Non-existent product should return error");
        }
        
        echo "\n";
    }

    /**
     * Test updating non-existent product
     */
    public function testUpdateNonExistentProduct()
    {
        echo "Testing: Update Non-existent Product\n";
        
        $updateData = [
            'id' => 999999,
            'beskrivelse' => 'This should fail'
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if (!$response['success'] && strpos($response['message'], 'not found') !== false) {
            echo "✓ Non-existent product update correctly rejected\n";
        } else {
            throw new Exception("Non-existent product update should have failed");
        }
        
        echo "\n";
    }

    /**
     * Test deleting non-existent product
     */
    public function testDeleteNonExistentProduct()
    {
        echo "Testing: Delete Non-existent Product\n";
        
        $deleteData = ['id' => 999999];

        $response = $this->makeRequest('DELETE', $deleteData);
        
        if (!$response['success'] && strpos($response['message'], 'not found') !== false) {
            echo "✓ Non-existent product deletion correctly rejected\n";
        } else {
            throw new Exception("Non-existent product deletion should have failed");
        }
        
        echo "\n";
    }

    /**
     * Test partial update
     */
    public function testPartialUpdateProduct()
    {
        echo "Testing: Partial Product Update\n";
        
        if (empty($this->createdProductIds)) {
            echo "⚠ Skipping partial update test (no products available)\n\n";
            return;
        }
        
        $productId = $this->createdProductIds[0];
        
        // Get current data
        $currentResponse = $this->makeRequest('GET', null, ['id' => $productId]);
        if (!$currentResponse['success']) {
            throw new Exception("Could not get current product data");
        }
        
        $originalPrice = $currentResponse['data']['salgspris'];
        
        // Update only price
        $updateData = [
            'id' => $productId,
            'salgspris' => $originalPrice + 10.00
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if ($response['success']) {
            echo "✓ Partial update successful\n";
            
            // Verify other fields unchanged
            $updatedResponse = $this->makeRequest('GET', null, ['id' => $productId]);
            if ($updatedResponse['success']) {
                if ($updatedResponse['data']['beskrivelse'] === $currentResponse['data']['beskrivelse']) {
                    echo "✓ Non-updated fields preserved\n";
                }
                if ($updatedResponse['data']['salgspris'] == $updateData['salgspris']) {
                    echo "✓ Updated field correctly changed\n";
                }
            }
        } else {
            throw new Exception("Partial update failed: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Make HTTP request to API
     */
    private function makeRequest($method, $data = null, $params = [])
    {
        $url = $this->baseUrl;
        
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if ($method !== 'GET' && $data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("cURL error: " . curl_error($ch));
        }

        $decodedResponse = json_decode($response, true);
        if ($decodedResponse === null) {
            throw new Exception("Invalid JSON response: " . $response);
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
                $response = $this->makeRequest('DELETE', ['id' => $productId]);
                if ($response['success']) {
                    echo "✓ Cleaned up product ID: $productId\n";
                } else {
                    echo "⚠ Could not clean up product ID: $productId\n";
                }
            } catch (Exception $e) {
                echo "⚠ Error cleaning up product ID $productId: " . $e->getMessage() . "\n";
            }
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new InventoryProductsEndpointTest();
    $test->runAllTests();
}
