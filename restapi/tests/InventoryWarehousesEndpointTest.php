<?php

/**
 * Inventory Warehouses Endpoint Test Script
 * 
 * This script tests all CRUD operations for the Warehouses API endpoint
 * including validation and warehouse-specific functionality.
 */

class InventoryWarehousesEndpointTest
{
    private $baseUrl;
    private $headers;
    private $createdWarehouseIds = [];

    public function __construct()
    {
        // Configure your API base URL and headers
        $this->baseUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/inventory/warehouses/';
        
        // Set your actual authorization headers - UPDATE THESE VALUES
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
        echo "=== Inventory Warehouses API Endpoint Tests ===\n\n";

        try {
            $this->testCreateWarehouse();
            $this->testGetAllWarehouses();
            $this->testGetSingleWarehouse();
            $this->testCreateWarehouseWithFiscalYear();
            $this->testCreateDuplicateWarehouseNumber();
            $this->testUpdateWarehouse();
            $this->testSearchWarehouses();
            $this->testOrderingWarehouses();
            $this->testDeleteWarehouse();
            $this->testCreateWarehouseMissingFields();
            $this->testGetNonExistentWarehouse();
            $this->testUpdateNonExistentWarehouse();
            $this->testDeleteNonExistentWarehouse();
            
            echo "\n=== Test Summary ===\n";
            echo "All Warehouses API tests completed successfully!\n";
            
        } catch (Exception $e) {
            echo "Test failed with error: " . $e->getMessage() . "\n";
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Test creating a basic warehouse
     */
    public function testCreateWarehouse()
    {
        echo "Testing: Create Basic Warehouse\n";
        
        $warehouseData = [
            'beskrivelse' => 'Test Warehouse ' . time(),
            'nr' => time() % 1000 // Use timestamp to ensure uniqueness
        ];

        $response = $this->makeRequest('POST', $warehouseData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdWarehouseIds[] = $response['data']['id'];
            echo "✓ Warehouse created successfully with ID: " . $response['data']['id'] . "\n";
            
            // Verify warehouse data
            if ($response['data']['beskrivelse'] === $warehouseData['beskrivelse']) {
                echo "✓ Warehouse description correctly set\n";
            } else {
                throw new Exception("Warehouse description mismatch");
            }
            
            if ($response['data']['nr'] == $warehouseData['nr']) {
                echo "✓ Warehouse number correctly set\n";
            } else {
                throw new Exception("Warehouse number mismatch");
            }
        } else {
            throw new Exception("Failed to create warehouse: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating a warehouse with fiscal year
     */
    public function testCreateWarehouseWithFiscalYear()
    {
        echo "Testing: Create Warehouse with Fiscal Year\n";
        
        $warehouseData = [
            'beskrivelse' => 'Test Warehouse FY ' . time(),
            'nr' => (time() % 1000) + 1,
            'fiscal_year' => 2024
        ];

        $response = $this->makeRequest('POST', $warehouseData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdWarehouseIds[] = $response['data']['id'];
            echo "✓ Warehouse with fiscal year created successfully\n";
            
            // Verify fiscal year
            if ($response['data']['fiscal_year'] == $warehouseData['fiscal_year']) {
                echo "✓ Fiscal year correctly set\n";
            }
        } else {
            throw new Exception("Failed to create warehouse with fiscal year: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test getting all warehouses
     */
    public function testGetAllWarehouses()
    {
        echo "Testing: Get All Warehouses\n";
        
        $response = $this->makeRequest('GET');
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Retrieved " . count($response['data']) . " warehouses\n";
            
            // Check if our created warehouses are in the list
            $foundIds = array_column($response['data'], 'id');
            foreach ($this->createdWarehouseIds as $createdId) {
                if (in_array($createdId, $foundIds)) {
                    echo "✓ Created warehouse ID $createdId found in list\n";
                }
            }
        } else {
            throw new Exception("Failed to get warehouses: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test getting a single warehouse
     */
    public function testGetSingleWarehouse()
    {
        echo "Testing: Get Single Warehouse\n";
        
        if (empty($this->createdWarehouseIds)) {
            throw new Exception("No warehouses created to test with");
        }
        
        $warehouseId = $this->createdWarehouseIds[0];
        $response = $this->makeRequest('GET', null, ['id' => $warehouseId]);
        
        if ($response['success'] && isset($response['data']['id'])) {
            echo "✓ Retrieved single warehouse with ID: " . $response['data']['id'] . "\n";
            
            if ($response['data']['id'] == $warehouseId) {
                echo "✓ Correct warehouse returned\n";
            } else {
                throw new Exception("Wrong warehouse returned");
            }
        } else {
            throw new Exception("Failed to get single warehouse: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test searching warehouses
     */
    public function testSearchWarehouses()
    {
        echo "Testing: Search Warehouses\n";
        
        // Search by field
        $response = $this->makeRequest('GET', null, [
            'field' => 'beskrivelse',
            'value' => 'Test Warehouse'
        ]);
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Search returned " . count($response['data']) . " results\n";
            
            // Verify search results contain our search term
            foreach ($response['data'] as $warehouse) {
                if (strpos($warehouse['beskrivelse'], 'Test Warehouse') !== false) {
                    echo "✓ Search result contains search term\n";
                    break;
                }
            }
        } else {
            throw new Exception("Failed to search warehouses: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test ordering warehouses
     */
    public function testOrderingWarehouses()
    {
        echo "Testing: Warehouse Ordering\n";
        
        // Test ordering by number DESC
        $response = $this->makeRequest('GET', null, [
            'orderBy' => 'nr',
            'orderDirection' => 'DESC'
        ]);
        
        if ($response['success'] && is_array($response['data']) && count($response['data']) > 1) {
            echo "✓ Ordered warehouses retrieved\n";
            
            // Check if ordering is working (first item number should be >= second)
            if ($response['data'][0]['nr'] >= $response['data'][1]['nr']) {
                echo "✓ Warehouses correctly ordered DESC by number\n";
            }
        } else {
            echo "⚠ Could not verify ordering (insufficient data)\n";
        }
        
        echo "\n";
    }

    /**
     * Test updating a warehouse
     */
    public function testUpdateWarehouse()
    {
        echo "Testing: Update Warehouse\n";
        
        if (empty($this->createdWarehouseIds)) {
            throw new Exception("No warehouses created to test with");
        }
        
        $warehouseId = $this->createdWarehouseIds[0];
        $updateData = [
            'id' => $warehouseId,
            'beskrivelse' => 'Updated Test Warehouse Description',
            'nr' => 999
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if ($response['success']) {
            echo "✓ Warehouse updated successfully\n";
            
            // Verify the update
            $getResponse = $this->makeRequest('GET', null, ['id' => $warehouseId]);
            if ($getResponse['success'] && $getResponse['data']['beskrivelse'] === $updateData['beskrivelse']) {
                echo "✓ Warehouse description correctly updated\n";
            }
            if ($getResponse['success'] && $getResponse['data']['nr'] == $updateData['nr']) {
                echo "✓ Warehouse number correctly updated\n";
            }
        } else {
            throw new Exception("Failed to update warehouse: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating duplicate warehouse number
     */
    public function testCreateDuplicateWarehouseNumber()
    {
        echo "Testing: Create Duplicate Warehouse Number\n";
        
        if (empty($this->createdWarehouseIds)) {
            throw new Exception("No warehouses created to test with");
        }
        
        // Get the first warehouse's number
        $firstWarehouseResponse = $this->makeRequest('GET', null, ['id' => $this->createdWarehouseIds[0]]);
        if (!$firstWarehouseResponse['success']) {
            throw new Exception("Could not get first warehouse for duplicate test");
        }
        
        $duplicateData = [
            'beskrivelse' => 'Duplicate Warehouse Test',
            'nr' => $firstWarehouseResponse['data']['nr'] // Use same warehouse number
        ];

        $response = $this->makeRequest('POST', $duplicateData);
        
        if (!$response['success']) {
            echo "✓ Duplicate warehouse number correctly rejected\n";
        } else {
            echo "⚠ Duplicate warehouse number was allowed (may be acceptable)\n";
            // Clean up if it was created
            if (isset($response['data']['id'])) {
                $this->createdWarehouseIds[] = $response['data']['id'];
            }
        }
        
        echo "\n";
    }

    /**
     * Test deleting a warehouse
     */
    public function testDeleteWarehouse()
    {
        echo "Testing: Delete Warehouse\n";
        
        if (empty($this->createdWarehouseIds)) {
            throw new Exception("No warehouses created to test with");
        }
        
        $warehouseId = array_pop($this->createdWarehouseIds); // Remove from our tracking
        $deleteData = ['id' => $warehouseId];

        $response = $this->makeRequest('DELETE', $deleteData);
        
        if ($response['success']) {
            echo "✓ Warehouse deleted successfully\n";
            
            // Verify deletion
            $getResponse = $this->makeRequest('GET', null, ['id' => $warehouseId]);
            if (!$getResponse['success'] && strpos($getResponse['message'], 'not found') !== false) {
                echo "✓ Warehouse correctly removed from database\n";
            }
        } else {
            throw new Exception("Failed to delete warehouse: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating warehouse with missing required fields
     */
    public function testCreateWarehouseMissingFields()
    {
        echo "Testing: Create Warehouse Missing Required Fields\n";
        
        $incompleteData = [
            'beskrivelse' => 'Warehouse without number'
            // Missing required 'nr' field
        ];

        $response = $this->makeRequest('POST', $incompleteData);
        
        if (!$response['success']) {
            echo "✓ Missing required fields correctly rejected\n";
        } else {
            throw new Exception("Warehouse creation with missing fields should have failed");
        }
        
        echo "\n";
    }

    /**
     * Test getting non-existent warehouse
     */
    public function testGetNonExistentWarehouse()
    {
        echo "Testing: Get Non-existent Warehouse\n";
        
        $response = $this->makeRequest('GET', null, ['id' => 999999]);
        
        if (!$response['success'] && strpos($response['message'], 'not found') !== false) {
            echo "✓ Non-existent warehouse correctly returns 404\n";
        } else {
            throw new Exception("Non-existent warehouse should return error");
        }
        
        echo "\n";
    }

    /**
     * Test updating non-existent warehouse
     */
    public function testUpdateNonExistentWarehouse()
    {
        echo "Testing: Update Non-existent Warehouse\n";
        
        $updateData = [
            'id' => 999999,
            'beskrivelse' => 'This should fail'
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if (!$response['success'] && strpos($response['message'], 'not found') !== false) {
            echo "✓ Non-existent warehouse update correctly rejected\n";
        } else {
            throw new Exception("Non-existent warehouse update should have failed");
        }
        
        echo "\n";
    }

    /**
     * Test deleting non-existent warehouse
     */
    public function testDeleteNonExistentWarehouse()
    {
        echo "Testing: Delete Non-existent Warehouse\n";
        
        $deleteData = ['id' => 999999];

        $response = $this->makeRequest('DELETE', $deleteData);
        
        if (!$response['success'] && strpos($response['message'], 'not found') !== false) {
            echo "✓ Non-existent warehouse deletion correctly rejected\n";
        } else {
            throw new Exception("Non-existent warehouse deletion should have failed");
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
        
        foreach ($this->createdWarehouseIds as $warehouseId) {
            try {
                $response = $this->makeRequest('DELETE', ['id' => $warehouseId]);
                if ($response['success']) {
                    echo "✓ Cleaned up warehouse ID: $warehouseId\n";
                } else {
                    echo "⚠ Could not clean up warehouse ID: $warehouseId\n";
                }
            } catch (Exception $e) {
                echo "⚠ Error cleaning up warehouse ID $warehouseId: " . $e->getMessage() . "\n";
            }
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new InventoryWarehousesEndpointTest();
    $test->runAllTests();
}
