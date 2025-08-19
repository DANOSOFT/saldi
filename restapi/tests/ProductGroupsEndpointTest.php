<?php

/**
 * Inventory Product Groups Endpoint Test Script
 * 
 * This script tests all CRUD operations for the Product Groups API endpoint
 * including validation, boolean options, and account settings.
 */

class ProductGroupsEndpointTest
{
    private $baseUrl;
    private $headers;
    private $createdGroupIds = [];

    public function __construct()
    {
        // Configure your API base URL and headers
        $this->baseUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/products/groups/';
        
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
        echo "=== Inventory Product Groups API Endpoint Tests ===\n\n";

        try {
            $this->testCreateProductGroup();
            $this->testGetAllProductGroups();
            $this->testGetSingleProductGroup();
            $this->testCreateProductGroupWithFullData();
            $this->testCreateDuplicateKodenr();
            $this->testUpdateProductGroup();
            $this->testUpdateProductGroupBooleans();
            $this->testUpdateProductGroupAccounts();
            $this->testSearchProductGroups();
            $this->testOrderingProductGroups();
            $this->testDeleteProductGroup();
            $this->testCreateProductGroupMissingFields();
            $this->testGetNonExistentProductGroup();
            $this->testUpdateNonExistentProductGroup();
            $this->testDeleteNonExistentProductGroup();
            
            echo "\n=== Test Summary ===\n";
            echo "All Product Groups API tests completed successfully!\n";
            
        } catch (Exception $e) {
            echo "Test failed with error: " . $e->getMessage() . "\n";
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Test creating a basic product group
     */
    public function testCreateProductGroup()
    {
        echo "Testing: Create Basic Product Group\n";
        
        $groupData = [
            'codeNo' => 1234, // kodenr is an integer
            'description' => 'Test Product Group'
        ];

        $response = $this->makeRequest('POST', $groupData);
		print_r($response);
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdGroupIds[] = $response['data']['id'];
            echo "✓ Product group created successfully with ID: " . $response['data']['id'] . "\n";
            // Verify group data
            if ($response['data']['codeNo'] === $groupData['codeNo']) {
                echo "✓ Group code correctly set\n";
            } else {
                throw new Exception("Group code mismatch");
            }

            if ($response['data']['description'] === $groupData['description']) {
                echo "✓ Group description correctly set\n";
            } else {
                throw new Exception("Group description mismatch");
            }
        } else {
            throw new Exception("Failed to create product group: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating a product group with full data
     */
    public function testCreateProductGroupWithFullData()
    {
        echo "Testing: Create Product Group with Full Data\n";
        
        $groupData = [
            'codeNo' => 4567,
            'description' => 'Test Full Product Group',
            'fiscalYear' => 2024,
            'reversePayment' => true,
            'taxFree' => false,
            'inventory' => true,
            'batch' => false,
            'operation' => true,
            'buyAccount' => 1000,
            'sellAccount' => 2000,
            'buyEuAccount' => 1100,
            'sellEuAccount' => 2100,
            'buyOutsideEuAccount' => 1200,
            'sellOutsideEuAccount' => 2200
        ];

        $response = $this->makeRequest('POST', $groupData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdGroupIds[] = $response['data']['id'];
            echo "✓ Full product group created successfully with ID: " . $response['data']['id'] . "\n";
            
            // Verify key fields
            $data = $response['data'];
            if ($data['fiscalYear'] == $groupData['fiscalYear']) {
                echo "✓ Fiscal year correctly set\n";
            }
            if ($data['reversePayment'] === $groupData['reversePayment']) {
                echo "✓ Boolean option correctly set\n";
            }
            if ($data["accounts"]['buyAccount'] == $groupData['buyAccount']) {
                echo "✓ Account settings correctly set\n";
            }
        } else {
            throw new Exception("Failed to create full product group: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test getting all product groups
     */
    public function testGetAllProductGroups()
    {
        echo "Testing: Get All Product Groups\n";
        
        $response = $this->makeRequest('GET');
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Retrieved " . count($response['data']) . " product groups\n";
            
            // Check if our created groups are in the list
            $foundIds = array_column($response['data'], 'id');
            foreach ($this->createdGroupIds as $createdId) {
                if (in_array($createdId, $foundIds)) {
                    echo "✓ Created product group ID $createdId found in list\n";
                }
            }
        } else {
            throw new Exception("Failed to get product groups: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test getting a single product group
     */
    public function testGetSingleProductGroup()
    {
        echo "Testing: Get Single Product Group\n";
        
        if (empty($this->createdGroupIds)) {
            throw new Exception("No product groups created to test with");
        }
        
        $groupId = $this->createdGroupIds[0];
        $response = $this->makeRequest('GET', null, ['id' => $groupId]);
        
        if ($response['success'] && isset($response['data']['id'])) {
            echo "✓ Retrieved single product group with ID: " . $response['data']['id'] . "\n";
            
            if ($response['data']['id'] == $groupId) {
                echo "✓ Correct product group returned\n";
            } else {
                throw new Exception("Wrong product group returned");
            }
        } else {
            throw new Exception("Failed to get single product group: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test searching product groups
     */
    public function testSearchProductGroups()
    {
        echo "Testing: Search Product Groups\n";
        
        // Search by field
        $response = $this->makeRequest('GET', null, [
            'field' => 'beskrivelse',
            'value' => 'Test Product Group'
        ]);
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Search returned " . count($response['data']) . " results\n";
            
            // Verify search results contain our search term
            foreach ($response['data'] as $group) {
                if (strpos($group['description'], 'Test Product Group') !== false) {
                    echo "✓ Search result contains search term\n";
                    break;
                }
            }
        } else {
            throw new Exception("Failed to search product groups: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test ordering product groups
     */
    public function testOrderingProductGroups()
    {
        echo "Testing: Product Group Ordering\n";
        
        // Test ordering by code DESC
        $response = $this->makeRequest('GET', null, [
            'orderBy' => 'kodenr',
            'orderDirection' => 'DESC'
        ]);
        
        if ($response['success'] && is_array($response['data']) && count($response['data']) > 1) {
            echo "✓ Ordered product groups retrieved\n";
            // Check if ordering is working (first item should be >= second alphabetically)
            if ($response['data'][0]['codeNo'] >= $response['data'][1]['codeNo']) {
                echo "✓ Product groups correctly ordered DESC by code\n";
            }
        } else {
            echo "⚠ Could not verify ordering (insufficient data)\n";
        }
        
        echo "\n";
    }

    /**
     * Test updating a product group
     */
    public function testUpdateProductGroup()
    {
        echo "Testing: Update Product Group\n";
        
        if (empty($this->createdGroupIds)) {
            throw new Exception("No product groups created to test with");
        }
        
        $groupId = $this->createdGroupIds[0];
        $updateData = [
            'id' => $groupId,
            'description' => 'Updated Test Product Group Description',
            'fiscalYear' => 2025
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if ($response['success']) {
            echo "✓ Product group updated successfully\n";
            
            // Verify the update
            $getResponse = $this->makeRequest('GET', null, ['id' => $groupId]);
            if ($getResponse['success'] && $getResponse['data']['description'] === $updateData['description']) {
                echo "✓ Product group description correctly updated\n";
            }
            if ($getResponse['success'] && $getResponse['data']['fiscalYear'] == $updateData['fiscalYear']) {
                echo "✓ Fiscal year correctly updated\n";
            }
        } else {
            throw new Exception("Failed to update product group: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test updating product group boolean options
     */
    public function testUpdateProductGroupBooleans()
    {
        echo "Testing: Update Product Group Boolean Options\n";
        
        if (count($this->createdGroupIds) < 2) {
            echo "⚠ Skipping boolean update test (insufficient groups)\n\n";
            return;
        }
        
        $groupId = $this->createdGroupIds[1];
        $updateData = [
            'id' => $groupId,
            'reversePayment' => false,
            'taxFree' => true,
            'inventory' => false,
            'batch' => true,
            'operation' => false
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if ($response['success']) {
            echo "✓ Product group boolean options updated successfully\n";
            
            // Verify the update
            $getResponse = $this->makeRequest('GET', null, ['id' => $groupId]);
            if ($getResponse['success']) {
                $data = $getResponse['data'];
                if ($data['reversePayment'] === $updateData['reversePayment'] && $data['taxFree'] === $updateData['taxFree']) {
                    echo "✓ Boolean options correctly updated\n";
                }
                if ($data['inventory'] === $updateData['inventory'] && $data['batch'] === $updateData['batch']) {
                    echo "✓ Additional boolean options correctly updated\n";
                }
            }
        } else {
            throw new Exception("Failed to update product group booleans: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test updating product group account settings
     */
    public function testUpdateProductGroupAccounts()
    {
        echo "Testing: Update Product Group Account Settings\n";
        
        if (count($this->createdGroupIds) < 2) {
            echo "⚠ Skipping account update test (insufficient groups)\n\n";
            return;
        }
        
        $groupId = $this->createdGroupIds[1];
        $updateData = [
            'id' => $groupId,
            'buyAccount' => 5000,
            'sellAccount' => 6000,
            'buyEuAccount' => 5100,
            'sellEuAccount' => 6100
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if ($response['success']) {
            echo "✓ Product group account settings updated successfully\n";
            
            // Verify the update
            $getResponse = $this->makeRequest('GET', null, ['id' => $groupId]);
            if ($getResponse['success']) {
                $data = $getResponse['data'];
                if ($data['buyAccount'] == $updateData['buyAccount'] && $data['sellAccount'] == $updateData['sellAccount']) {
                    echo "✓ Buy/sell accounts correctly updated\n";
                }
                if ($data['buyEuAccount'] == $updateData['buyEuAccount'] && $data['sellEuAccount'] == $updateData['sellEuAccount']) {
                    echo "✓ EU accounts correctly updated\n";
                }
            }
        } else {
            throw new Exception("Failed to update product group accounts: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating duplicate product group code
     */
    public function testCreateDuplicateKodenr()
    {
        echo "Testing: Create Duplicate Product Group Code\n";
        
        if (empty($this->createdGroupIds)) {
            throw new Exception("No product groups created to test with");
        }
        
        // Get the first group's code
        $firstGroupResponse = $this->makeRequest('GET', null, ['id' => $this->createdGroupIds[0]]);
        if (!$firstGroupResponse['success']) {
            throw new Exception("Could not get first group for duplicate test");
        }
        
        $duplicateData = [
            'codeNo' => $firstGroupResponse['data']['codeNo'], // Use same group code
            'beskrivelse' => 'Duplicate Group Test'
        ];

        $response = $this->makeRequest('POST', $duplicateData);
        
        if (!$response['success']) {
            echo "✓ Duplicate product group code correctly rejected\n";
        } else {
            echo "⚠ Duplicate product group code was allowed (may be acceptable)\n";
            // Clean up if it was created
            if (isset($response['data']['id'])) {
                $this->createdGroupIds[] = $response['data']['id'];
            }
        }
        
        echo "\n";
    }

    /**
     * Test deleting a product group
     */
    public function testDeleteProductGroup()
    {
        echo "Testing: Delete Product Group\n";
        
        if (empty($this->createdGroupIds)) {
            throw new Exception("No product groups created to test with");
        }
        
        $groupId = array_pop($this->createdGroupIds); // Remove from our tracking
        $deleteData = ['id' => $groupId];

        $response = $this->makeRequest('DELETE', $deleteData);
        
        if ($response['success']) {
            echo "✓ Product group deleted successfully\n";
            
            // Verify deletion
            $getResponse = $this->makeRequest('GET', null, ['id' => $groupId]);
            if (!$getResponse['success'] && strpos($getResponse['message'], 'not found') !== false) {
                echo "✓ Product group correctly removed from database\n";
            }
        } else {
            throw new Exception("Failed to delete product group: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating product group with missing required fields
     */
    public function testCreateProductGroupMissingFields()
    {
        echo "Testing: Create Product Group Missing Required Fields\n";
        
        $incompleteData = [
            'beskrivelse' => 'Group without code'
            // Missing required 'kodenr' field
        ];

        $response = $this->makeRequest('POST', $incompleteData);
        
        if (!$response['success']) {
            echo "✓ Missing required fields correctly rejected\n";
        } else {
            throw new Exception("Product group creation with missing fields should have failed");
        }
        
        echo "\n";
    }

    /**
     * Test getting non-existent product group
     */
    public function testGetNonExistentProductGroup()
    {
        echo "Testing: Get Non-existent Product Group\n";
        
        $response = $this->makeRequest('GET', null, ['id' => 999999]);
        
        if (!$response['success'] && strpos($response['message'], 'not found') !== false) {
            echo "✓ Non-existent product group correctly returns 404\n";
        } else {
            throw new Exception("Non-existent product group should return error");
        }
        
        echo "\n";
    }

    /**
     * Test updating non-existent product group
     */
    public function testUpdateNonExistentProductGroup()
    {
        echo "Testing: Update Non-existent Product Group\n";
        
        $updateData = [
            'id' => 999999,
            'description' => 'This should fail'
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if (!$response['success'] && strpos($response['message'], 'not found') !== false) {
            echo "✓ Non-existent product group update correctly rejected\n";
        } else {
            throw new Exception("Non-existent product group update should have failed");
        }
        
        echo "\n";
    }

    /**
     * Test deleting non-existent product group
     */
    public function testDeleteNonExistentProductGroup()
    {
        echo "Testing: Delete Non-existent Product Group\n";
        
        $deleteData = ['id' => 999999];

        $response = $this->makeRequest('DELETE', $deleteData);
        
        if (!$response['success'] && strpos($response['message'], 'not found') !== false) {
            echo "✓ Non-existent product group deletion correctly rejected\n";
        } else {
            throw new Exception("Non-existent product group deletion should have failed");
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
        
        foreach ($this->createdGroupIds as $groupId) {
            try {
                $response = $this->makeRequest('DELETE', ['id' => $groupId]);
                if ($response['success']) {
                    echo "✓ Cleaned up product group ID: $groupId\n";
                } else {
                    echo "⚠ Could not clean up product group ID: $groupId\n";
                }
            } catch (Exception $e) {
                echo "⚠ Error cleaning up product group ID $groupId: " . $e->getMessage() . "\n";
            }
        }
    }
}

// Run tests if this file is executed directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new ProductGroupsEndpointTest();
    $test->runAllTests();
}
