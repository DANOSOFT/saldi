<?php

/**
 * VAT Endpoint Test Script
 * 
 * This script tests all CRUD operations for the VAT API endpoint
 * using real HTTP requests to simulate actual API usage.
 */

class VatEndpointTest
{
    private $baseUrl;
    private $headers;
    private $createdVatIds = [];

    public function __construct()
    {
        // Configure your API base URL and headers
        $this->baseUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/vat/';
        
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
        echo "=== VAT API Endpoint Tests ===\n\n";

        try {
            $this->testCreateVatItem();
            $this->testGetAllVatItems();
            $this->testGetSingleVatItem();
            $this->testCreateDuplicateVatCode();
            $this->testUpdateVatItem();
            $this->testUpdateWithDuplicateVatCode();
            $this->testDeleteVatItem();
            $this->testCreateVatItemMissingFields();
            $this->testGetNonExistentVatItem();
            $this->testFilterVatItems();
            $this->testVatValidation();
            $this->testVatRateCalculation();
            
            echo "\n=== Test Summary ===\n";
            echo "All tests completed successfully!\n";
            
        } catch (Exception $e) {
            echo "Test failed with error: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Test creating a new VAT item
     */
    public function testCreateVatItem()
    {
        echo "Testing: Create VAT Item\n";
        
        $vatData = [
            'momskode' => 'S',
            'nr' => '25',
            'beskrivelse' => 'Standard VAT 25%',
            'fiscal_year' => 2024,
            'account' => 1001,
            'sats' => 25.0,
            'modkonto' => 2001,
            'map' => 'STD25'
        ];

        $response = $this->makeRequest('POST', $vatData);
        print_r($response);
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdVatIds[] = $response['data']['id'];
            echo "✓ VAT item created successfully with ID: " . $response['data']['id'] . "\n";
            
            // Verify the created VAT item has correct data
            if ($response['data']['momskode'] != $vatData['momskode']) {
                throw new Exception("Created VAT item has wrong momskode");
            }
            if ($response['data']['beskrivelse'] != $vatData['beskrivelse']) {
                throw new Exception("Created VAT item has wrong beskrivelse");
            }
            if ($response['data']['sats'] != $vatData['sats']) {
                throw new Exception("Created VAT item has wrong sats");
            }
        } else {
            throw new Exception("Failed to create VAT item: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test getting all VAT items
     */
    public function testGetAllVatItems()
    {
        echo "Testing: Get All VAT Items\n";
        
        $response = $this->makeRequest('GET');
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Retrieved " . count($response['data']) . " VAT items\n";
            
            // Verify our created VAT item is in the list
            $found = false;
            foreach ($response['data'] as $vatItem) {
                if (in_array($vatItem['id'], $this->createdVatIds)) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found && !empty($this->createdVatIds)) {
                throw new Exception("Created VAT item not found in VAT list");
            }
        } else {
            throw new Exception("Failed to get all VAT items");
        }
        
        echo "\n";
    }

    /**
     * Test getting a single VAT item
     */
    public function testGetSingleVatItem()
    {
        if (empty($this->createdVatIds)) {
            echo "Skipping: Get Single VAT Item (no VAT item created)\n\n";
            return;
        }

        echo "Testing: Get Single VAT Item\n";
        
        $vatId = $this->createdVatIds[0];
        $response = $this->makeRequest('GET', null, "?id=$vatId");
        
        if ($response['success'] && $response['data']['id'] == $vatId) {
            echo "✓ Retrieved VAT item with ID: $vatId\n";
            
            // Verify VAT item data integrity
            if (!isset($response['data']['momskode']) || !isset($response['data']['beskrivelse'])) {
                throw new Exception("VAT item data missing required fields");
            }
            if (!isset($response['data']['sats'])) {
                throw new Exception("VAT item missing sats field");
            }
        } else {
            throw new Exception("Failed to get single VAT item");
        }
        
        echo "\n";
    }

    /**
     * Test creating VAT item with duplicate code
     */
    public function testCreateDuplicateVatCode()
    {
        echo "Testing: Create VAT Item with Duplicate Code\n";
        
        $vatData = [
            'momskode' => 'S', // Same code as first VAT item
            'nr' => '25',      // Same nr as first VAT item
            'beskrivelse' => 'Duplicate Standard VAT',
            'fiscal_year' => 2024,
            'sats' => 25.0
        ];

        $response = $this->makeRequest('POST', $vatData);
        
        if (!$response['success'] && (
            strpos($response['message'], 'duplicate') !== false ||
            strpos($response['message'], 'already exists') !== false ||
            strpos($response['message'], 'momskode') !== false
        )) {
            echo "✓ Correctly rejected duplicate VAT code\n";
        } else {
            echo "⚠ Warning: Duplicate VAT code was allowed (this might be intended behavior)\n";
        }
        
        echo "\n";
    }

    /**
     * Test updating a VAT item
     */
    public function testUpdateVatItem()
    {
        if (empty($this->createdVatIds)) {
            echo "Skipping: Update VAT Item (no VAT item created)\n\n";
            return;
        }

        echo "Testing: Update VAT Item\n";
        
        $vatId = $this->createdVatIds[0];
        $updateData = [
            'id' => $vatId,
            'beskrivelse' => 'Updated Standard VAT 25%',
            'sats' => 25.5,
            'map' => 'UPD25'
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if ($response['success']) {
            echo "✓ VAT item updated successfully\n";
            
            // Verify the update by fetching the VAT item again
            $verifyResponse = $this->makeRequest('GET', null, "?id=$vatId");
            if ($verifyResponse['success']) {
                if ($verifyResponse['data']['beskrivelse'] == $updateData['beskrivelse']) {
                    echo "✓ Update verified - beskrivelse changed correctly\n";
                } else {
                    throw new Exception("Update verification failed - beskrivelse not updated");
                }
                if ($verifyResponse['data']['sats'] == $updateData['sats']) {
                    echo "✓ Update verified - sats changed correctly\n";
                } else {
                    throw new Exception("Update verification failed - sats not updated");
                }
            }
        } else {
            throw new Exception("Failed to update VAT item: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test updating VAT item with duplicate code
     */
    public function testUpdateWithDuplicateVatCode()
    {
        if (count($this->createdVatIds) < 2) {
            // Create a second VAT item for this test
            $vatData = [
                'momskode' => 'R',
                'nr' => '0',
                'beskrivelse' => 'Reduced VAT 0%',
                'fiscal_year' => 2024,
                'sats' => 0.0
            ];
            
            $response = $this->makeRequest('POST', $vatData);
            if ($response['success']) {
                $this->createdVatIds[] = $response['data']['id'];
            }
        }

        if (count($this->createdVatIds) < 2) {
            echo "Skipping: Update with Duplicate VAT Code (need 2 VAT items)\n\n";
            return;
        }

        echo "Testing: Update VAT Item with Duplicate Code\n";
        
        $vatId = $this->createdVatIds[1];
        $updateData = [
            'id' => $vatId,
            'momskode' => 'S', // Code from first VAT item
            'nr' => '25'       // Nr from first VAT item
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if (!$response['success'] && (
            strpos($response['message'], 'duplicate') !== false ||
            strpos($response['message'], 'already exists') !== false
        )) {
            echo "✓ Correctly rejected duplicate VAT code on update\n";
        } else {
            echo "⚠ Warning: Duplicate VAT code update was allowed (this might be intended behavior)\n";
        }
        
        echo "\n";
    }

    /**
     * Test creating VAT item with missing required fields
     */
    public function testCreateVatItemMissingFields()
    {
        echo "Testing: Create VAT Item with Missing Required Fields\n";
        
        $vatData = [
            'beskrivelse' => 'VAT Item Missing Fields',
            'sats' => 15.0
            // Missing momskode and nr
        ];

        $response = $this->makeRequest('POST', $vatData);
        
        if (!$response['success'] && (
            strpos($response['message'], 'required') !== false ||
            strpos($response['message'], 'missing') !== false ||
            strpos($response['message'], 'momskode') !== false
        )) {
            echo "✓ Correctly rejected VAT item with missing fields\n";
        } else {
            throw new Exception("Should have rejected VAT item with missing required fields");
        }
        
        echo "\n";
    }

    /**
     * Test getting non-existent VAT item
     */
    public function testGetNonExistentVatItem()
    {
        echo "Testing: Get Non-Existent VAT Item\n";
        
        $response = $this->makeRequest('GET', null, "?id=999999");
        
        if (!$response['success'] && (
            strpos($response['message'], 'not found') !== false ||
            strpos($response['message'], 'VAT item not found') !== false
        )) {
            echo "✓ Correctly returned error for non-existent VAT item\n";
        } else {
            throw new Exception("Should have returned error for non-existent VAT item");
        }
        
        echo "\n";
    }

    /**
     * Test filtering VAT items
     */
    public function testFilterVatItems()
    {
        echo "Testing: Filter VAT Items\n";
        
        // Test filtering by field
        $response = $this->makeRequest('GET', null, "?field=momskode&value=S");
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Filter by momskode=S returned " . count($response['data']) . " VAT items\n";
            
            // Verify all returned items have momskode 'S'
            foreach ($response['data'] as $vatItem) {
                if ($vatItem['momskode'] !== 'S') {
                    throw new Exception("Filter returned VAT item with wrong momskode");
                }
            }
        } else {
            echo "⚠ Warning: Filter functionality may not be implemented\n";
        }
        
        // Test ordering
        $response = $this->makeRequest('GET', null, "?orderBy=sats&orderDirection=DESC");
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Ordering by sats DESC returned " . count($response['data']) . " VAT items\n";
        } else {
            echo "⚠ Warning: Ordering functionality may not be implemented\n";
        }
        
        echo "\n";
    }

    /**
     * Test VAT validation
     */
    public function testVatValidation()
    {
        echo "Testing: VAT Validation\n";
        
        // Test invalid VAT rate
        $invalidData = [
            'momskode' => 'X',
            'nr' => 'invalid',
            'beskrivelse' => '',
            'sats' => 'not-numeric'
        ];

        $response = $this->makeRequest('POST', $invalidData);
        
        if (!$response['success']) {
            echo "✓ Correctly rejected invalid VAT data\n";
        } else {
            echo "⚠ Warning: VAT validation may need improvement\n";
        }
        
        // Test negative VAT rate
        $negativeData = [
            'momskode' => 'N',
            'nr' => '1',
            'beskrivelse' => 'Negative VAT',
            'sats' => -5.0
        ];

        $response = $this->makeRequest('POST', $negativeData);
        
        if (!$response['success']) {
            echo "✓ Correctly rejected negative VAT rate\n";
        } else {
            echo "⚠ Warning: Negative VAT rates are allowed (this might be intended)\n";
        }
        
        echo "\n";
    }

    /**
     * Test VAT rate calculation scenarios
     */
    public function testVatRateCalculation()
    {
        echo "Testing: VAT Rate Scenarios\n";
        
        // Test zero VAT
        $zeroVatData = [
            'momskode' => 'Z',
            'nr' => '0',
            'beskrivelse' => 'Zero VAT',
            'sats' => 0.0,
            'fiscal_year' => 2024
        ];

        $response = $this->makeRequest('POST', $zeroVatData);
        
        if ($response['success']) {
            $this->createdVatIds[] = $response['data']['id'];
            echo "✓ Zero VAT rate created successfully\n";
        } else {
            echo "⚠ Warning: Zero VAT rate creation failed\n";
        }
        
        // Test high VAT
        $highVatData = [
            'momskode' => 'H',
            'nr' => '50',
            'beskrivelse' => 'High VAT 50%',
            'sats' => 50.0,
            'fiscal_year' => 2024
        ];

        $response = $this->makeRequest('POST', $highVatData);
        
        if ($response['success']) {
            $this->createdVatIds[] = $response['data']['id'];
            echo "✓ High VAT rate created successfully\n";
        } else {
            echo "⚠ Warning: High VAT rate creation failed\n";
        }
        
        echo "\n";
    }

    /**
     * Test deleting a VAT item
     */
    public function testDeleteVatItem()
    {
        if (empty($this->createdVatIds)) {
            echo "Skipping: Delete VAT Item (no VAT item created)\n\n";
            return;
        }

        echo "Testing: Delete VAT Item\n";
        
        $vatId = array_pop($this->createdVatIds); // Remove from cleanup list
        $deleteData = ['id' => $vatId];

        $response = $this->makeRequest('DELETE', $deleteData);
        
        if ($response['success']) {
            echo "✓ VAT item deleted successfully\n";
            
            // Verify the VAT item is actually deleted
            $verifyResponse = $this->makeRequest('GET', null, "?id=$vatId");
            if (!$verifyResponse['success']) {
                echo "✓ Delete verified - VAT item no longer exists\n";
            } else {
                throw new Exception("VAT item still exists after delete");
            }
        } else {
            // Add back to cleanup list if delete failed
            $this->createdVatIds[] = $vatId;
            throw new Exception("Failed to delete VAT item: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Make HTTP request to the API
     */
    private function makeRequest($method, $data = null, $urlSuffix = '')
    {
        $url = $this->baseUrl . $urlSuffix;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        // Disable SSL verification for local testing (remove in production)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        if ($data && in_array($method, ['POST', 'PUT', 'DELETE'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            throw new Exception("cURL Error: $error for URL: $url");
        }
        
        if ($httpCode >= 500) {
            throw new Exception("Server Error (HTTP $httpCode) for URL: $url. Response: $response");
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($decodedResponse === null) {
            throw new Exception("Invalid JSON response from $url (HTTP $httpCode): $response");
        }
        
        // Add HTTP status code to response for better debugging
        $decodedResponse['http_code'] = $httpCode;
        
        return $decodedResponse;
    }

    /**
     * Clean up created test data
     */
    private function cleanup()
    {
        echo "\n=== Cleanup ===\n";
        
        if (empty($this->createdVatIds)) {
            echo "No test VAT items to clean up.\n";
            return;
        }
        
        foreach ($this->createdVatIds as $vatId) {
            try {
                $deleteData = ['id' => $vatId];
                $response = $this->makeRequest('DELETE', $deleteData);
                
                if ($response['success']) {
                    echo "✓ Cleaned up VAT item ID: $vatId\n";
                } else {
                    echo "✗ Failed to cleanup VAT item ID: $vatId - " . ($response['message'] ?? 'Unknown error') . "\n";
                }
            } catch (Exception $e) {
                echo "✗ Error cleaning up VAT item ID $vatId: " . $e->getMessage() . "\n";
            }
        }
    }
}

// Run the tests
try {
    $tester = new VatEndpointTest();
    $tester->runAllTests();
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}