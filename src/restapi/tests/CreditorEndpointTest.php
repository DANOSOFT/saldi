<?php

/**
 * Creditor Endpoint Test Script
 * 
 * This script tests all CRUD operations for the Customer API endpoint
 * including validation for duplicate email and phone numbers.
 */

class CustomerEndpointTest
{
    private $baseUrl;
    private $headers;
    private $createdCustomerIds = [];

    public function __construct()
    {
        // Configure your API base URL and headers
        $this->baseUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/creditor/creditors/';
        
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
        echo "=== Customer API Endpoint Tests ===\n\n";

        try {
            $this->testCreateCustomer();
            $this->testGetAllCustomers();
            $this->testGetSingleCustomer();
            $this->testCreateDuplicateEmail();
            $this->testCreateDuplicatePhone();
            $this->testUpdateCustomer();
            $this->testUpdateWithDuplicateEmail();
            $this->testUpdateWithDuplicatePhone();
            $this->testDeleteCustomer();
            $this->testCreateCustomerMissingFields();
            $this->testGetNonExistentCustomer();
            
            echo "\n=== Test Summary ===\n";
            echo "All tests completed successfully!\n";
            
        } catch (Exception $e) {
            echo "Test failed with error: " . $e->getMessage() . "\n";
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Test creating a new customer
     */
    public function testCreateCustomer()
    {
        echo "Testing: Create Customer\n";
        
        $customerData = [
            'firmanavn' => 'Test Company A',
            'tlf' => '59842682',
            'email' => 'test4@company-a.com',
            'addr1' => 'Test Street 123',
            'postnr' => '1234',
            'bynavn' => 'Test City',
            'notes' => 'This is a test customer'
        ];

        $response = $this->makeRequest('POST', $customerData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdCustomerIds[] = $response['data']['id'];
            echo "✓ Customer created successfully with ID: " . $response['data']['id'] . "\n";
        } else {
            throw new Exception("Failed to create customer: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test getting all customers
     */
    public function testGetAllCustomers()
    {
        echo "Testing: Get All Customers\n";
        
        $response = $this->makeRequest('GET');
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Retrieved " . count($response['data']) . " customers\n";
        } else {
            throw new Exception("Failed to get all customers");
        }
        
        echo "\n";
    }

    /**
     * Test getting a single customer
     */
    public function testGetSingleCustomer()
    {
        if (empty($this->createdCustomerIds)) {
            echo "Skipping: Get Single Customer (no customer created)\n\n";
            return;
        }

        echo "Testing: Get Single Customer\n";
        
        $customerId = $this->createdCustomerIds[0];
        $response = $this->makeRequest('GET', null, "?id=$customerId");
        
        if ($response['success'] && $response['data']['id'] == $customerId) {
            echo "✓ Retrieved customer with ID: $customerId\n";
        } else {
            throw new Exception("Failed to get single customer");
        }
        
        echo "\n";
    }

    /**
     * Test creating customer with duplicate email
     */
    public function testCreateDuplicateEmail()
    {
        echo "Testing: Create Customer with Duplicate Email\n";
        
        $customerData = [
            'firmanavn' => 'Test Company B',
            'tlf' => '87654321',
            'email' => 'test@company-a.com', // Same email as first customer
        ];

        $response = $this->makeRequest('POST', $customerData);
        
        if (!$response['success'] && strpos($response['message'], 'Email address is already in use') !== false) {
            echo "✓ Correctly rejected duplicate email\n";
        } else {
            throw new Exception("Should have rejected duplicate email");
        }
        
        echo "\n";
    }

    /**
     * Test creating customer with duplicate phone
     */
    public function testCreateDuplicatePhone()
    {
        echo "Testing: Create Customer with Duplicate Phone\n";
        
        $customerData = [
            'firmanavn' => 'Test Company C',
            'tlf' => '12345678', // Same phone as first customer
            'email' => 'test@company-c.com',
        ];

        $response = $this->makeRequest('POST', $customerData);
        
        if (!$response['success'] && strpos($response['message'], 'Phone number is already in use') !== false) {
            echo "✓ Correctly rejected duplicate phone\n";
        } else {
            throw new Exception("Should have rejected duplicate phone");
        }
        
        echo "\n";
    }

    /**
     * Test updating a customer
     */
    public function testUpdateCustomer()
    {
        if (empty($this->createdCustomerIds)) {
            echo "Skipping: Update Customer (no customer created)\n\n";
            return;
        }

        echo "Testing: Update Customer\n";
        
        $customerId = $this->createdCustomerIds[0];
        $updateData = [
            'id' => $customerId,
            'firmanavn' => 'Updated Test Company A',
            'notes' => 'This customer has been updated'
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if ($response['success']) {
            echo "✓ Customer updated successfully\n";
        } else {
            throw new Exception("Failed to update customer: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test updating customer with duplicate email (should fail)
     */
    public function testUpdateWithDuplicateEmail()
    {
        if (count($this->createdCustomerIds) < 2) {
            // Create a second customer for this test
            $customerData = [
                'firmanavn' => 'Test Company D',
                'tlf' => '11111111',
                'email' => 'test@company-d.com'
            ];
            
            $response = $this->makeRequest('POST', $customerData);
            if ($response['success']) {
                $this->createdCustomerIds[] = $response['data']['id'];
            }
        }

        if (count($this->createdCustomerIds) < 2) {
            echo "Skipping: Update with Duplicate Email (need 2 customers)\n\n";
            return;
        }

        echo "Testing: Update Customer with Duplicate Email\n";
        
        $customerId = $this->createdCustomerIds[1];
        $updateData = [
            'id' => $customerId,
            'email' => 'test@company-a.com' // Email from first customer
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if (!$response['success'] && strpos($response['message'], 'Email address is already in use') !== false) {
            echo "✓ Correctly rejected duplicate email on update\n";
        } else {
            throw new Exception("Should have rejected duplicate email on update");
        }
        
        echo "\n";
    }

    /**
     * Test updating customer with duplicate phone (should fail)
     */
    public function testUpdateWithDuplicatePhone()
    {
        if (count($this->createdCustomerIds) < 2) {
            echo "Skipping: Update with Duplicate Phone (need 2 customers)\n\n";
            return;
        }

        echo "Testing: Update Customer with Duplicate Phone\n";
        
        $customerId = $this->createdCustomerIds[1];
        $updateData = [
            'id' => $customerId,
            'tlf' => '12345678' // Phone from first customer
        ];

        $response = $this->makeRequest('PUT', $updateData);
        
        if (!$response['success'] && strpos($response['message'], 'Phone number is already in use') !== false) {
            echo "✓ Correctly rejected duplicate phone on update\n";
        } else {
            throw new Exception("Should have rejected duplicate phone on update");
        }
        
        echo "\n";
    }

    /**
     * Test creating customer with missing required fields
     */
    public function testCreateCustomerMissingFields()
    {
        echo "Testing: Create Customer with Missing Required Fields\n";
        
        $customerData = [
            'firmanavn' => 'Test Company Missing Fields',
            // Missing tlf and email
        ];

        $response = $this->makeRequest('POST', $customerData);
        
        if (!$response['success'] && strpos($response['message'], 'Missing required field') !== false) {
            echo "✓ Correctly rejected customer with missing fields\n";
        } else {
            throw new Exception("Should have rejected customer with missing fields");
        }
        
        echo "\n";
    }

    /**
     * Test getting non-existent customer
     */
    public function testGetNonExistentCustomer()
    {
        echo "Testing: Get Non-Existent Customer\n";
        
        $response = $this->makeRequest('GET', null, "?id=999999");
        
        if (!$response['success'] && strpos($response['message'], 'Customer not found') !== false) {
            echo "✓ Correctly returned error for non-existent customer\n";
        } else {
            throw new Exception("Should have returned error for non-existent customer");
        }
        
        echo "\n";
    }

    /**
     * Test deleting a customer
     */
    public function testDeleteCustomer()
    {
        if (empty($this->createdCustomerIds)) {
            echo "Skipping: Delete Customer (no customer created)\n\n";
            return;
        }

        echo "Testing: Delete Customer\n";
        
        $customerId = array_pop($this->createdCustomerIds); // Remove from cleanup list
        $deleteData = ['id' => $customerId];

        $response = $this->makeRequest('DELETE', $deleteData);
        
        if ($response['success']) {
            echo "✓ Customer deleted successfully\n";
        } else {
            throw new Exception("Failed to delete customer: " . ($response['message'] ?? 'Unknown error'));
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
        
        if ($data && in_array($method, ['POST', 'PUT', 'DELETE'])) {
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
        
        foreach ($this->createdCustomerIds as $customerId) {
            try {
                $deleteData = ['id' => $customerId];
                $response = $this->makeRequest('DELETE', $deleteData);
                
                if ($response['success']) {
                    echo "✓ Cleaned up customer ID: $customerId\n";
                } else {
                    echo "✗ Failed to cleanup customer ID: $customerId\n";
                }
            } catch (Exception $e) {
                echo "✗ Error cleaning up customer ID $customerId: " . $e->getMessage() . "\n";
            }
        }
    }
}

// Run the tests
$tester = new CustomerEndpointTest();
$tester->runAllTests();