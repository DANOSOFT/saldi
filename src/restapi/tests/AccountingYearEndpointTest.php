<?php

/**
 * Accounting Year Endpoint Test Script
 * 
 * This script tests the Accounting Year API endpoint
 * including data retrieval and error handling.
 */

class AccountingYearEndpointTest
{
    private $baseUrl;
    private $headers;

    public function __construct()
    {
        // Configure your API base URL and headers
        $this->baseUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/accountingYear/';
        
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
        echo "=== Accounting Year API Endpoint Tests ===\n\n";

        try {
            $this->testGetCurrentFiscalYear();
            $this->testGetFiscalYearWithoutId();
            $this->testGetFiscalYearWithId();
            $this->testUnsupportedMethods();
            
            echo "\n=== Test Summary ===\n";
            echo "All tests completed successfully!\n";
            
        } catch (Exception $e) {
            echo "Test failed with error: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Test getting the current fiscal year
     */
    public function testGetCurrentFiscalYear()
    {
        echo "Testing: Get Current Fiscal Year\n";
        
        $response = $this->makeRequest('GET');
        
        if ($response['success']) {
            echo "✓ Current fiscal year retrieved successfully\n";
            echo "  Fiscal Year: " . $response['data']['fiscal_year'] . "\n";
            echo "  Current Date: " . $response['data']['current_date'] . "\n";
            echo "  Current Month: " . $response['data']['current_month'] . "\n";
            echo "  Current Year: " . $response['data']['current_year'] . "\n";
            
            // Validate data structure
            $this->validateFiscalYearResponse($response['data']);
            
        } else {
            if (strpos($response['message'], 'No active fiscal year found') !== false) {
                echo "✓ No active fiscal year configured (expected in some environments)\n";
            } else {
                throw new Exception("Failed to get fiscal year: " . $response['message']);
            }
        }
        
        echo "\n";
    }

    /**
     * Test getting fiscal year without ID parameter (should work the same)
     */
    public function testGetFiscalYearWithoutId()
    {
        echo "Testing: Get Fiscal Year Without ID Parameter\n";
        
        $response = $this->makeRequest('GET', null, '');
        
        if ($response['success']) {
            echo "✓ Fiscal year retrieved without ID parameter\n";
            echo "  Fiscal Year: " . $response['data']['fiscal_year'] . "\n";
        } else {
            if (strpos($response['message'], 'No active fiscal year found') !== false) {
                echo "✓ No active fiscal year found (expected in some environments)\n";
            } else {
                throw new Exception("Unexpected error: " . $response['message']);
            }
        }
        
        echo "\n";
    }

    /**
     * Test getting fiscal year with ID parameter (should ignore it)
     */
    public function testGetFiscalYearWithId()
    {
        echo "Testing: Get Fiscal Year With ID Parameter (should ignore)\n";
        
        $response = $this->makeRequest('GET', null, '?id=123');
        
        if ($response['success']) {
            echo "✓ Fiscal year retrieved (ID parameter ignored as expected)\n";
            echo "  Fiscal Year: " . $response['data']['fiscal_year'] . "\n";
        } else {
            if (strpos($response['message'], 'No active fiscal year found') !== false) {
                echo "✓ No active fiscal year found (expected in some environments)\n";
            } else {
                throw new Exception("Unexpected error: " . $response['message']);
            }
        }
        
        echo "\n";
    }

    /**
     * Test unsupported HTTP methods
     */
    public function testUnsupportedMethods()
    {
        echo "Testing: Unsupported HTTP Methods\n";
        
        // Test POST method
        $postData = ['test' => 'data'];
        $response = $this->makeRequest('POST', $postData);
        
        if (!$response['success'] && strpos($response['message'], 'POST method is not supported') !== false) {
            echo "✓ Correctly rejected POST method\n";
        } else {
            throw new Exception("Should have rejected POST method");
        }

        // Test PUT method
        $putData = ['test' => 'data'];
        $response = $this->makeRequest('PUT', $putData);
        
        if (!$response['success'] && strpos($response['message'], 'PUT method is not supported') !== false) {
            echo "✓ Correctly rejected PUT method\n";
        } else {
            throw new Exception("Should have rejected PUT method");
        }

        // Test DELETE method
        $response = $this->makeRequest('DELETE');
        
        if (!$response['success'] && strpos($response['message'], 'DELETE method is not supported') !== false) {
            echo "✓ Correctly rejected DELETE method\n";
        } else {
            throw new Exception("Should have rejected DELETE method");
        }
        
        echo "\n";
    }

    /**
     * Validate the structure of fiscal year response data
     */
    private function validateFiscalYearResponse($data)
    {
        // Check required fields
        $requiredFields = ['fiscal_year', 'current_date', 'current_month', 'current_year'];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
        
        // Validate fiscal year format (should be numeric or string)
        if (empty($data['fiscal_year'])) {
            throw new Exception("Fiscal year should not be empty");
        }
        
        // Validate date format (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['current_date'])) {
            throw new Exception("Invalid current_date format, expected YYYY-MM-DD");
        }
        
        // Validate month format (01-12)
        if (!preg_match('/^(0[1-9]|1[0-2])$/', $data['current_month'])) {
            throw new Exception("Invalid current_month format, expected 01-12");
        }
        
        // Validate year format (4 digits)
        if (!preg_match('/^\d{4}$/', $data['current_year'])) {
            throw new Exception("Invalid current_year format, expected 4 digits");
        }
        
        // Verify date consistency
        $expectedDate = $data['current_year'] . '-' . $data['current_month'] . '-' . date('d');
        $actualDate = $data['current_date'];
        
        if (substr($actualDate, 0, 7) !== substr($expectedDate, 0, 7)) {
            throw new Exception("Date consistency check failed");
        }
        
        echo "✓ Response data structure validated successfully\n";
    }

    /**
     * Make HTTP request to Accounting Year API endpoint
     */
    private function makeRequest($method, $data = null, $urlSuffix = '')
    {
        $url = $this->baseUrl . $urlSuffix;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development only
        
        // Debug: Show the actual URL being called
        echo "Making $method request to: $url\n";
        
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Debug: Show response info
        echo "HTTP Code: $httpCode\n";
        echo "Raw Response: " . substr($response, 0, 300) . "\n"; // First 300 chars
        
        if (curl_error($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        // Try to decode JSON
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON decode error: ' . json_last_error_msg() . ". Raw response: " . $response);
        }
        
        return $decodedResponse;
    }
}

// Run the tests
$tester = new AccountingYearEndpointTest();
$tester->runAllTests();