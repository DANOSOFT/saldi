<?php

/**
 * Inventory Status Endpoint Test Script
 * 
 * This script tests all CRUD operations for the Inventory Status API endpoint
 * including validation, quantity adjustments, and warehouse-specific queries.
 */

class InventoryStatusEndpointTest
{
    private $baseUrl;
    private $headers;
    private $createdStatusIds = [];

    public function __construct()
    {
        // Configure your API base URL and headers
        $this->baseUrl = 'https://yourdomain.com/restapi/endpoints/v1/inventory/status/';
        
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
        echo "=== Inventory Status API Endpoint Tests ===\n\n";

        try {
            $this->testCreateInventoryStatus();
            $this->testGetAllInventoryStatus();
            $this->testGetSingleInventoryStatus();
            $this->testCreateInventoryStatusWithLocation();
            $this->testUpdateInventoryStatus();
            $this->testAdjustQuantityPositive();
            $this->testAdjustQuantityNegative();
            $this->testSetQuantity();
            $this->testGetWarehouseInventory();
            $this->testSearchInventoryStatus();
            $this->testOrderingInventoryStatus();
            $this->testClearInventoryStatus();
            $this->testCreateInventoryStatusMissingFields();
            $this->testGetNonExistentInventoryStatus();
            $this->testUpdateNonExistentInventoryStatus();
            $this->testAdjustNonExistentInventoryStatus();
            
            echo "\n=== Test Summary ===\n";
            echo "All Inventory Status API tests completed successfully!\n";
            
        } catch (Exception $e) {
            echo "Test failed with error: " . $e->getMessage() . "\n";
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Test creating a basic inventory status
     */
    public function testCreateInventoryStatus()
    {
        echo "Testing: Create Basic Inventory Status\n";
        
        $statusData = [
            'lager' => 1,
            'vare_id' => 123,
            'beholdning' => 50.0
        ];

        $response = $this->makeRequest('POST', $statusData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdStatusIds[] = $response['data']['id'];
            echo "✓ Inventory status created successfully with ID: " . $response['data']['id'] . "\n";
            
            // Verify status data
            if ($response['data']['lager'] == $statusData['lager']) {
                echo "✓ Warehouse ID correctly set\n";
            } else {
                throw new Exception("Warehouse ID mismatch");
            }
            
            if ($response['data']['vare_id'] == $statusData['vare_id']) {
                echo "✓ Product ID correctly set\n";
            } else {
                throw new Exception("Product ID mismatch");
            }
            
            if ($response['data']['beholdning'] == $statusData['beholdning']) {
                echo "✓ Quantity correctly set\n";
            } else {
                throw new Exception("Quantity mismatch");
            }
        } else {
            throw new Exception("Failed to create inventory status: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating inventory status with location
     */
    public function testCreateInventoryStatusWithLocation()
    {
        echo "Testing: Create Inventory Status with Location\n";
        
        $statusData = [
            'lager' => 1,
            'vare_id' => 124,
            'beholdning' => 25.5,
            'lok' => 'A1-01-05',
            'variant_id' => 1
        ];

        $response = $this->makeRequest('POST', $statusData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdStatusIds[] = $response['data']['id'];
            echo "✓ Inventory status with location created successfully\n";
            
            // Verify location and variant
            if ($response['data']['lok'] === $statusData['lok']) {
                echo "✓ Location correctly set\n";
            }
            if ($response['data']['variant_id'] == $statusData['variant_id']) {
                echo "✓ Variant ID correctly set\n";
            }
        } else {
            throw new Exception("Failed to create inventory status with location: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test getting all inventory status
     */
    public function testGetAllInventoryStatus()
    {
        echo "Testing: Get All Inventory Status\n";
        
        $response = $this->makeRequest('GET');
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Retrieved " . count($response['data']) . " inventory status records\n";
            
            // Check if our created status records are in the list
            $foundIds = array_column($response['data'], 'id');
            foreach ($this->createdStatusIds as $createdId) {
                if (in_array($createdId, $foundIds)) {
                    echo "✓ Created inventory status ID $createdId found in list\n";
                }
            }
        } else {
            throw new Exception("Failed to get inventory status: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test getting a single inventory status
     */
    public function testGetSingleInventoryStatus()
    {
        echo "Testing: Get Single Inventory Status\n";
        
        if (empty($this->createdStatusIds)) {
            throw new Exception("No inventory status created to test with");
        }
        
        $statusId = $this->createdStatusIds[0];
        $response = $this->makeRequest('GET', null, ['id' => $statusId]);
        
        if ($response['success'] && isset($response['data']['id'])) {
            echo "✓ Retrieved single inventory status with ID: " . $response['data']['id'] . "\n";
            
            if ($response['data']['id'] == $statusId) {
                echo "✓ Correct inventory status returned\n";
            } else {
                throw new Exception("Wrong inventory status returned");
            }
        } else {
            throw new Exception("Failed to get single inventory status: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test getting warehouse inventory
     */
    public function testGetWarehouseInventory()
    {
        echo "Testing: Get Warehouse Inventory\n";
        
        // Get inventory for warehouse 1
        $response = $this->makeRequest('GET', null, ['lager_nr' => 1]);
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Retrieved warehouse inventory with " . count($response['data']) . " items\n";
            
            // Verify all items are from warehouse 1
            foreach ($response['data'] as $item) {
                if ($item['lager'] != 1) {
                    throw new Exception("Non-warehouse 1 item found in warehouse 1 inventory");
                }
            }
            echo "✓ All items correctly filtered to warehouse 1\n";
        } else {
            throw new Exception("Failed to get warehouse inventory: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test searching inventory status
     */
    public function testSearchInventoryStatus()
    {
        echo "Testing: Search Inventory Status\n";
        
        // Search by product ID
        $response = $this->makeRequest('GET', null, [
            'field' => 'vare_id',
            'value' => '123'
        ]);
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Search returned " . count($response['data']) . " results\n";
            
            // Verify search results contain our search term
            foreach ($response['data'] as $status) {
                if ($status['vare_id'] == 123) {
                    echo "✓ Search result contains correct product ID\n";
                    break;
                }
            }
        } else {
            throw new Exception("Failed to search inventory status: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test ordering inventory status
     */
    public function testOrderingInventoryStatus()
    {
        echo "Testing: Inventory Status Ordering\n";
        
        // Test ordering by quantity DESC
        $response = $this->makeRequest('GET', null, [
            'orderBy' => 'beholdning',
            'orderDirection' => 'DESC'
        ]);
        
        if ($response['success'] && is_array($response['data']) && count($response['data']) > 1) {
            echo "✓ Ordered inventory status retrieved\n";
            
            // Check if ordering is working (first item quantity should be >= second)
            if ($response['data'][0]['beholdning'] >= $response['data'][1]['beholdning']) {
                echo "✓ Inventory status correctly ordered DESC by quantity\n";
            }
        } else {
            echo "⚠ Could not verify ordering (insufficient data)\n";
        }
        
        echo "\n";
    }

    /**
     * Test updating inventory status
     */
    public function testUpdateInventoryStatus()
    {
        echo "Testing: Update Inventory Status\n";
        
        if (empty($this->createdStatusIds)) {
            throw new Exception("No inventory status created to test with");
        }
        
        $statusId = $this->createdStatusIds[0];
        $updateData = [
            'id' => $statusId,
            'beholdning' => 75.0,
            'lok' => 'B2-03-01'
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if ($response['success']) {
            echo "✓ Inventory status updated successfully\n";
            
            // Verify the update
            $getResponse = $this->makeRequest('GET', null, ['id' => $statusId]);
            if ($getResponse['success'] && $getResponse['data']['beholdning'] == $updateData['beholdning']) {
                echo "✓ Quantity correctly updated\n";
            }
            if ($getResponse['success'] && $getResponse['data']['lok'] === $updateData['lok']) {
                echo "✓ Location correctly updated\n";
            }
        } else {
            throw new Exception("Failed to update inventory status: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test adjusting quantity positively
     */
    public function testAdjustQuantityPositive()
    {
        echo "Testing: Adjust Quantity (Positive)\n";
        
        if (empty($this->createdStatusIds)) {
            throw new Exception("No inventory status created to test with");
        }
        
        $statusId = $this->createdStatusIds[0];
        
        // Get current quantity
        $currentResponse = $this->makeRequest('GET', null, ['id' => $statusId]);
        if (!$currentResponse['success']) {
            throw new Exception("Could not get current quantity");
        }
        $currentQuantity = $currentResponse['data']['beholdning'];
        
        $adjustData = [
            'action' => 'adjust_quantity',
            'id' => $statusId,
            'amount' => 15.0
        ];

        $response = $this->makeRequest('PATCH', $adjustData);
        
        if ($response['success']) {
            echo "✓ Quantity adjusted positively\n";
            
            // Verify the adjustment
            $newResponse = $this->makeRequest('GET', null, ['id' => $statusId]);
            if ($newResponse['success']) {
                $expectedQuantity = $currentQuantity + $adjustData['amount'];
                if ($newResponse['data']['beholdning'] == $expectedQuantity) {
                    echo "✓ Quantity correctly increased from $currentQuantity to " . $newResponse['data']['beholdning'] . "\n";
                } else {
                    throw new Exception("Quantity adjustment calculation error");
                }
            }
        } else {
            throw new Exception("Failed to adjust quantity: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test adjusting quantity negatively
     */
    public function testAdjustQuantityNegative()
    {
        echo "Testing: Adjust Quantity (Negative)\n";
        
        if (count($this->createdStatusIds) < 2) {
            echo "⚠ Skipping negative adjustment test (insufficient status records)\n\n";
            return;
        }
        
        $statusId = $this->createdStatusIds[1];
        
        // Get current quantity
        $currentResponse = $this->makeRequest('GET', null, ['id' => $statusId]);
        if (!$currentResponse['success']) {
            throw new Exception("Could not get current quantity");
        }
        $currentQuantity = $currentResponse['data']['beholdning'];
        
        $adjustData = [
            'action' => 'adjust_quantity',
            'id' => $statusId,
            'amount' => -10.5
        ];

        $response = $this->makeRequest('PATCH', $adjustData);
        
        if ($response['success']) {
            echo "✓ Quantity adjusted negatively\n";
            
            // Verify the adjustment
            $newResponse = $this->makeRequest('GET', null, ['id' => $statusId]);
            if ($newResponse['success']) {
                $expectedQuantity = $currentQuantity + $adjustData['amount'];
                if ($newResponse['data']['beholdning'] == $expectedQuantity) {
                    echo "✓ Quantity correctly decreased from $currentQuantity to " . $newResponse['data']['beholdning'] . "\n";
                } else {
                    throw new Exception("Negative quantity adjustment calculation error");
                }
            }
        } else {
            throw new Exception("Failed to adjust quantity negatively: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test setting absolute quantity
     */
    public function testSetQuantity()
    {
        echo "Testing: Set Absolute Quantity\n";
        
        if (empty($this->createdStatusIds)) {
            throw new Exception("No inventory status created to test with");
        }
        
        $statusId = $this->createdStatusIds[0];
        $setData = [
            'action' => 'set_quantity',
            'id' => $statusId,
            'quantity' => 100.0
        ];

        $response = $this->makeRequest('PATCH', $setData);
        
        if ($response['success']) {
            echo "✓ Quantity set to absolute value\n";
            
            // Verify the set operation
            $newResponse = $this->makeRequest('GET', null, ['id' => $statusId]);
            if ($newResponse['success'] && $newResponse['data']['beholdning'] == $setData['quantity']) {
                echo "✓ Quantity correctly set to " . $setData['quantity'] . "\n";
            } else {
                throw new Exception("Set quantity operation failed");
            }
        } else {
            throw new Exception("Failed to set quantity: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test clearing inventory status (DELETE operation sets quantity to 0)
     */
    public function testClearInventoryStatus()
    {
        echo "Testing: Clear Inventory Status\n";
        
        if (empty($this->createdStatusIds)) {
            throw new Exception("No inventory status created to test with");
        }
        
        $statusId = array_pop($this->createdStatusIds); // Remove from our tracking
        $deleteData = ['id' => $statusId];

        $response = $this->makeRequest('DELETE', $deleteData);
        
        if ($response['success']) {
            echo "✓ Inventory status cleared successfully\n";
            
            // Verify quantity is set to 0
            $getResponse = $this->makeRequest('GET', null, ['id' => $statusId]);
            if ($getResponse['success'] && $getResponse['data']['beholdning'] == 0) {
                echo "✓ Quantity correctly set to 0 after clear operation\n";
            } else {
                echo "⚠ Clear operation may have deleted record instead of setting to 0\n";
            }
        } else {
            throw new Exception("Failed to clear inventory status: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating inventory status with missing required fields
     */
    public function testCreateInventoryStatusMissingFields()
    {
        echo "Testing: Create Inventory Status Missing Required Fields\n";
        
        $incompleteData = [
            'lager' => 1,
            'vare_id' => 125
            // Missing required 'beholdning' field
        ];

        $response = $this->makeRequest('POST', $incompleteData);
        
        if (!$response['success']) {
            echo "✓ Missing required fields correctly rejected\n";
        } else {
            throw new Exception("Inventory status creation with missing fields should have failed");
        }
        
        echo "\n";
    }

    /**
     * Test getting non-existent inventory status
     */
    public function testGetNonExistentInventoryStatus()
    {
        echo "Testing: Get Non-existent Inventory Status\n";
        
        $response = $this->makeRequest('GET', null, ['id' => 999999]);
        
        if (!$response['success'] && strpos($response['message'], 'not found') !== false) {
            echo "✓ Non-existent inventory status correctly returns 404\n";
        } else {
            throw new Exception("Non-existent inventory status should return error");
        }
        
        echo "\n";
    }

    /**
     * Test updating non-existent inventory status
     */
    public function testUpdateNonExistentInventoryStatus()
    {
        echo "Testing: Update Non-existent Inventory Status\n";
        
        $updateData = [
            'id' => 999999,
            'beholdning' => 50.0
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if (!$response['success'] && strpos($response['message'], 'not found') !== false) {
            echo "✓ Non-existent inventory status update correctly rejected\n";
        } else {
            throw new Exception("Non-existent inventory status update should have failed");
        }
        
        echo "\n";
    }

    /**
     * Test adjusting non-existent inventory status
     */
    public function testAdjustNonExistentInventoryStatus()
    {
        echo "Testing: Adjust Non-existent Inventory Status\n";
        
        $adjustData = [
            'action' => 'adjust_quantity',
            'id' => 999999,
            'amount' => 10.0
        ];

        $response = $this->makeRequest('PATCH', $adjustData);
        
        if (!$response['success'] && strpos($response['message'], 'not found') !== false) {
            echo "✓ Non-existent inventory status adjustment correctly rejected\n";
        } else {
            throw new Exception("Non-existent inventory status adjustment should have failed");
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
        
        foreach ($this->createdStatusIds as $statusId) {
            try {
                $response = $this->makeRequest('DELETE', ['id' => $statusId]);
                if ($response['success']) {
                    echo "✓ Cleaned up inventory status ID: $statusId\n";
                } else {
                    echo "⚠ Could not clean up inventory status ID: $statusId\n";
                }
            } catch (Exception $e) {
                echo "⚠ Error cleaning up inventory status ID $statusId: " . $e->getMessage() . "\n";
            }
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new InventoryStatusEndpointTest();
    $test->runAllTests();
}
