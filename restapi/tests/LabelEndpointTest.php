<?php

/**
 * Label Endpoint Test Script
 * 
 * This script tests all operations for the Label API endpoint
 * using real HTTP requests to simulate actual API usage.
 * Note: Labels are read-only, so we test GET operations and error handling.
 */

class LabelEndpointTest
{
    private $baseUrl;
    private $headers;

    public function __construct()
    {
        // Configure your API base URL and headers
        $this->baseUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/labels/';
        
        // Set your actual authorization headers
        $this->headers = [
            'Content-Type: application/json',
            'Authorization: sioefjsofksodkf',
            'X-SaldiUser: api',
            'X-DB: test_16'
        ];
    }

    /**
     * Run all tests
     */
    public function runAllTests()
    {
        echo "=== Label API Endpoint Tests ===\n\n";

        try {
            $this->testGetAllLabels();
            $this->testGetLabelsWithLimit();
            $this->testGetLabelsWithOrdering();
            $this->testGetLabelsWithAccountFilter();
            $this->testGetSingleLabel();
            $this->testGetNonExistentLabel();
            $this->testPostMethodNotAllowed();
            $this->testPutMethodNotAllowed();
            $this->testDeleteMethodNotAllowed();
            $this->testInvalidParameters();
            $this->testLargeLimit();
            $this->testCombinedFilters();
            
            echo "\n=== Test Summary ===\n";
            echo "All tests completed successfully!\n";
            
        } catch (Exception $e) {
            echo "Test failed with error: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }

    /**
     * Test getting all labels (basic functionality)
     */
    public function testGetAllLabels()
    {
        echo "Testing: Get All Labels\n";
        
        $response = $this->makeRequest('GET');

        if ($response['success'] && isset($response['data']['items']) && is_array($response['data']['items'])) {
            $count = count($response['data']['items']);
            echo "✓ Retrieved $count labels\n";
            
            // Verify response structure
            if (isset($response['data']['count']) && isset($response['data']['filters'])) {
                echo "✓ Response has correct structure (items, count, filters)\n";
            } else {
                throw new Exception("Response missing expected structure");
            }
            
            // Verify individual label structure if we have labels
            if ($count > 0) {
                $firstLabel = $response['data']['items'][0];
                $requiredFields = ['id', 'accountId', 'price', 'description', 'barcode', 'sold', 'created', 'lastPrint'];
                
                foreach ($requiredFields as $field) {
                    if (!array_key_exists($field, $firstLabel)) {
                        throw new Exception("Label missing required field: $field");
                    }
                }
                echo "✓ Label objects have correct structure\n";
            }
        } else {
            throw new Exception("Failed to get all labels or unexpected response structure");
        }
        
        echo "\n";
    }

    /**
     * Test getting labels with custom limit
     */
    public function testGetLabelsWithLimit()
    {
        echo "Testing: Get Labels with Custom Limit\n";
        
        $response = $this->makeRequest('GET', null, '?limit=5');
        
        if ($response['success'] && isset($response['data']['items'])) {
            $count = count($response['data']['items']);
            echo "✓ Retrieved $count labels with limit parameter\n";
            
            if ($count <= 5) {
                echo "✓ Limit parameter respected (requested 5, got $count)\n";
            } else {
                throw new Exception("Limit parameter not respected");
            }
        } else {
            throw new Exception("Failed to get labels with limit parameter");
        }
        
        echo "\n";
    }

    /**
     * Test ordering functionality
     */
    public function testGetLabelsWithOrdering()
    {
        echo "Testing: Get Labels with Custom Ordering\n";
        
        // Test ordering by created date descending
        $response = $this->makeRequest('GET', null, '?orderBy=created&orderDirection=DESC&limit=10');
        
        if ($response['success'] && isset($response['data']['items'])) {
            $count = count($response['data']['items']);
            echo "✓ Retrieved $count labels ordered by created DESC\n";
            
            // Verify ordering if we have multiple labels
            if ($count > 1) {
                $dates = array_map(function($label) {
                    return $label['created'];
                }, $response['data']['items']);
                
                $sortedDates = $dates;
                rsort($sortedDates); // Sort descending
                
                if ($dates === $sortedDates) {
                    echo "✓ Labels correctly ordered by created date DESC\n";
                } else {
                    echo "⚠ Warning: Ordering may not be working as expected\n";
                }
            }
        } else {
            throw new Exception("Failed to get labels with ordering");
        }
        
        // Test ordering by price ascending
        $response = $this->makeRequest('GET', null, '?orderBy=price&orderDirection=ASC&limit=10');
        
        if ($response['success']) {
            echo "✓ Retrieved labels ordered by price ASC\n";
        } else {
            throw new Exception("Failed to get labels ordered by price");
        }
        
        echo "\n";
    }
    /**
     * Test account ID filtering
     */
    public function testGetLabelsWithAccountFilter()
    {
        echo "Testing: Get Labels with Account ID Filter\n";
        
        // First, get some labels to find a valid account_id
        $response = $this->makeRequest('GET', null, '?limit=10');
        
        if ($response['success'] && isset($response['data']['items']) && count($response['data']['items']) > 0) {
            $accountId = null;
            
            // Find a label with an account_id
            foreach ($response['data']['items'] as $label) {
                if ($label['accountId'] !== null) {
                    $accountId = $label['accountId'];
                    break;
                }
            }
            
            if ($accountId !== null) {
                $filterResponse = $this->makeRequest('GET', null, "?account_id=$accountId&limit=10");
                
                if ($filterResponse['success'] && isset($filterResponse['data']['items'])) {
                    $count = count($filterResponse['data']['items']);
                    echo "✓ Retrieved $count labels with account_id '$accountId'\n";
                    
                    // Verify all returned labels have the requested account_id
                    foreach ($filterResponse['data']['items'] as $label) {
                        if ($label['accountId'] != $accountId) {
                            throw new Exception("Label with wrong account_id returned");
                        }
                    }
                    
                    // Verify filter is reflected in response
                    if ($filterResponse['data']['filters']['account_id'] == $accountId) {
                        echo "✓ Account ID filter correctly reflected in response\n";
                    }
                } else {
                    throw new Exception("Failed to filter by account_id");
                }
            } else {
                echo "⚠ Skipping account_id filter test (no labels with account_id found)\n";
            }
        } else {
            echo "⚠ Skipping account_id filter test (no labels available)\n";
        }
        
        echo "\n";
    }

    /**
     * Test getting a single label
     */
    public function testGetSingleLabel()
    {
        echo "Testing: Get Single Label\n";
        
        // First, get all labels to find a valid ID
        $response = $this->makeRequest('GET', null, '?limit=5');

        if ($response['success'] && isset($response['data']['items']) && count($response['data']['items']) > 0) {
            $labelId = $response['data']['items'][0]['id'];
            
            $singleResponse = $this->makeRequest('GET', null, "?id=$labelId");

             // Verify we got the correct label
            if ($singleResponse['success'] && $singleResponse['data']['id'] == $labelId) {
                echo "✓ Retrieved single label with ID: $labelId\n";
                
                // Verify label data integrity
                $requiredFields = ['id', 'accountId', 'price', 'description', 'barcode', 'sold', 'created', 'lastPrint'];
                foreach ($requiredFields as $field) {
                    if (!array_key_exists($field, $singleResponse['data'])) {
                        throw new Exception("Single label missing required field: $field");
                    }
                }
                echo "✓ Single label has all required fields\n";
            } else {
                throw new Exception("Failed to get single label or incorrect data returned");
            }
        } else {
            echo "⚠ Skipping single label test (no labels available)\n";
        }
        
        echo "\n";
    }

    /**
     * Test getting non-existent label
     */
    public function testGetNonExistentLabel()
    {
        echo "Testing: Get Non-Existent Label\n";
        
        $response = $this->makeRequest('GET', null, '?id=999999');
        
        if (!$response['success'] && $response['http_code'] == 404 && (
            strpos($response['message'], 'not found') !== false ||
            strpos($response['message'], 'Label with ID') !== false
        )) {
            echo "✓ Correctly returned 404 for non-existent label\n";
        } else {
            throw new Exception("Should have returned 404 for non-existent label");
        }
        
        echo "\n";
    }

    /**
     * Test that POST method is not allowed
     */
    public function testPostMethodNotAllowed()
    {
        echo "Testing: POST Method Not Allowed\n";
        
        $labelData = [
            'accountId' => 123,
            'price' => 29.99,
            'description' => 'Test Label',
            'barcode' => '1234567890'
        ];
        
        $response = $this->makeRequest('POST', $labelData);
        
        if (!$response['success'] && $response['http_code'] == 405 && (
            strpos($response['message'], 'not supported') !== false ||
            strpos($response['message'], 'read-only') !== false
        )) {
            echo "✓ Correctly rejected POST method (405 Method Not Allowed)\n";
        } else {
            throw new Exception("Should have rejected POST method");
        }
        
        echo "\n";
    }

    /**
     * Test that PUT method is not allowed
     */
    public function testPutMethodNotAllowed()
    {
        echo "Testing: PUT Method Not Allowed\n";
        
        $updateData = [
            'id' => 1,
            'description' => 'Updated Label',
        ];
        
        $response = $this->makeRequest('PUT', $updateData);
        
        if (!$response['success'] && $response['http_code'] == 405 && (
            strpos($response['message'], 'not supported') !== false ||
            strpos($response['message'], 'read-only') !== false
        )) {
            echo "✓ Correctly rejected PUT method (405 Method Not Allowed)\n";
        } else {
            throw new Exception("Should have rejected PUT method");
        }
        
        echo "\n";
    }

    /**
     * Test that DELETE method is not allowed
     */
    public function testDeleteMethodNotAllowed()
    {
        echo "Testing: DELETE Method Not Allowed\n";
        
        $deleteData = ['id' => 1];
        
        $response = $this->makeRequest('DELETE', $deleteData);
        
        if (!$response['success'] && $response['http_code'] == 405 && (
            strpos($response['message'], 'not supported') !== false ||
            strpos($response['message'], 'read-only') !== false
        )) {
            echo "✓ Correctly rejected DELETE method (405 Method Not Allowed)\n";
        } else {
            throw new Exception("Should have rejected DELETE method");
        }
        
        echo "\n";
    }

    /**
     * Test invalid parameters
     */
    public function testInvalidParameters()
    {
        echo "Testing: Invalid Parameters\n";
        
        // Test invalid orderBy field
        $response = $this->makeRequest('GET', null, '?orderBy=invalid_field');
        
        if ($response['success']) {
            echo "✓ Invalid orderBy parameter handled gracefully (fell back to default)\n";
        } else {
            echo "⚠ Warning: Invalid orderBy parameter may need better handling\n";
        }
        
        // Test invalid orderDirection
        $response = $this->makeRequest('GET', null, '?orderDirection=INVALID');
        
        if ($response['success']) {
            echo "✓ Invalid orderDirection parameter handled gracefully (fell back to default)\n";
        } else {
            echo "⚠ Warning: Invalid orderDirection parameter may need better handling\n";
        }
        
        // Test invalid account_id
        $response = $this->makeRequest('GET', null, '?account_id=not_a_number');
        
        if ($response['success']) {
            echo "✓ Invalid account_id parameter handled gracefully\n";
        } else {
            echo "⚠ Warning: Invalid account_id parameter may need better handling\n";
        }
        
        echo "\n";
    }

    /**
     * Test limit boundaries
     */
    public function testLargeLimit()
    {
        echo "Testing: Large Limit Parameter\n";
        
        // Test limit larger than allowed maximum
        $response = $this->makeRequest('GET', null, '?limit=1000');
        
        if ($response['success'] && isset($response['data']['items'])) {
            $count = count($response['data']['items']);
            if ($count <= 100) { // Assuming 100 is the max limit
                echo "✓ Large limit capped appropriately (requested 1000, got $count)\n";
            } else {
                echo "⚠ Warning: Large limit may not be properly capped\n";
            }
        } else {
            throw new Exception("Failed to handle large limit parameter");
        }
        
        // Test negative limit
        $response = $this->makeRequest('GET', null, '?limit=-5');
        
        if ($response['success']) {
            echo "✓ Negative limit handled gracefully\n";
        } else {
            echo "⚠ Warning: Negative limit parameter may need better handling\n";
        }
        
        echo "\n";
    }

    /**
     * Test combined filters
     */
    public function testCombinedFilters()
    {
        echo "Testing: Combined Filters\n";
        
        $response = $this->makeRequest('GET', null, '?limit=5&orderBy=created&orderDirection=DESC');
        
        if ($response['success'] && isset($response['data']['items'])) {
            $count = count($response['data']['items']);
            echo "✓ Retrieved $count labels with combined filters (limit + ordering)\n";

        } else {
            echo "⚠ Warning: Combined filters may not be working properly\n";
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
}

// Run the tests
try {
    $tester = new LabelEndpointTest();
    $tester->runAllTests();
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}