<?php

/**
 * Order Endpoint Test Script
 * 
 * This script tests all operations for the Order API endpoint
 * including validation, business logic, and error handling.
 */

class OrderEndpointTest
{
    private $baseUrl;
    private $headers;
    private $createdOrderIds = [];

    public function __construct()
    {
        // Configure your API base URL and headers
        $this->baseUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/debitor/orders/';
        
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
        echo "=== Order API Endpoint Tests ===\n\n";

        try {
            $this->testCreateOrder();
            $this->testCreateOrderWithExistingDebtor();
            $this->testGetSingleOrder();
            $this->testGetAllOrders();
            $this->testGetOrdersWithDateFilter();
            $this->testCreateOrderMissingFields();
            $this->testCreateOrderWithCurrency();
            $this->testCreateOrderWithFullAddress();
            $this->testCreateOrderWithDeliveryAddress();
            $this->testGetNonExistentOrder();
            $this->testGetOrdersWithInvalidDateRange();
            $this->testUnsupportedMethods();
            
            echo "\n=== Test Summary ===\n";
            echo "All tests completed successfully!\n";
            
        } catch (Exception $e) {
            echo "Test failed with error: " . $e->getMessage() . "\n";
        } finally {
            $this->cleanup();
        }
    }

    /**
     * Test creating a new order with new debtor
     */
    public function testCreateOrder()
    {
        echo "Testing: Create Order (New Debtor)\n";
        
        $orderData = [
            'companyName' => 'Test Order Company A',
            'phone' => '12345678',
            'email' => 'test-order@company-a.com',
            'vatRate' => 25.0,
            'addr1' => 'Order Street 123',
            'zipcode' => '2000',
            'city' => 'Order City',
            'notes' => 'This is a test order'
        ];

        $response = $this->makeRequest('POST', $orderData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdOrderIds[] = $response['data']['id'];
            echo "✓ Order created successfully with ID: " . $response['data']['id'] . "\n";
            echo "  Order Number: " . $response['data']['ordrenr'] . "\n";
        } else {
            throw new Exception("Failed to create order: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating order with existing debtor (same phone number)
     */
    public function testCreateOrderWithExistingDebtor()
    {
        echo "Testing: Create Order (Existing Debtor)\n";
        
        $orderData = [
            'firmanavn' => 'Test Order Company B',
            'telefon' => '12345678', // Same phone as previous order
            'email' => 'test-order-b@company-a.com',
            'momssats' => 25.0,
            'sum' => 1000.00,
            'kostpris' => 600.00
        ];

        $response = $this->makeRequest('POST', $orderData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdOrderIds[] = $response['data']['id'];
            echo "✓ Order created with existing debtor, ID: " . $response['data']['id'] . "\n";
        } else {
            throw new Exception("Failed to create order with existing debtor: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test getting a single order
     */
    public function testGetSingleOrder()
    {
        if (empty($this->createdOrderIds)) {
            echo "Skipping: Get Single Order (no order created)\n\n";
            return;
        }

        echo "Testing: Get Single Order\n";
        
        $orderId = $this->createdOrderIds[0];
        $response = $this->makeRequest('GET', null, "?id=$orderId");

        if ($response['success'] && $response['data']['id'] == $orderId) {
            echo "✓ Retrieved order with ID: $orderId\n";
            echo "  Company: " . $response['data']['firmanavn'] . "\n";
        } else {
            throw new Exception("Failed to get single order");
        }
        
        echo "\n";
    }

    /**
     * Test getting all orders
     */
    public function testGetAllOrders()
    {
        echo "Testing: Get All Orders\n";
        
        $response = $this->makeRequest('GET');
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Retrieved " . count($response['data']) . " orders\n";
        } else {
            throw new Exception("Failed to get all orders");
        }
        
        echo "\n";
    }

    /**
     * Test getting orders with date filter
     */
    public function testGetOrdersWithDateFilter()
    {
        echo "Testing: Get Orders with Date Filter\n";
        
        $fromDate = date('Y-m-d', strtotime('-30 days'));
        $toDate = date('Y-m-d');
        
        $response = $this->makeRequest('GET', null, "?fromDate=$fromDate&toDate=$toDate&limit=10");
        
        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Retrieved " . count($response['data']) . " orders within date range\n";
        } else {
            throw new Exception("Failed to get orders with date filter");
        }
        
        echo "\n";
    }

    /**
     * Test creating order with missing required fields
     */
    public function testCreateOrderMissingFields()
    {
        echo "Testing: Create Order with Missing Required Fields\n";
        
        $orderData = [
            'firmanavn' => 'Test Company Missing Fields',
            'telefon' => '87654321'
            // Missing email and momssats
        ];

        $response = $this->makeRequest('POST', $orderData);
        
        if (!$response['success'] && strpos($response['message'], 'Required field missing') !== false) {
            echo "✓ Correctly rejected order with missing fields\n";
        } else {
            throw new Exception("Should have rejected order with missing fields");
        }
        
        echo "\n";
    }

    /**
     * Test creating order with specific currency
     */
    public function testCreateOrderWithCurrency()
    {
        echo "Testing: Create Order with Currency\n";
        
        $orderData = [
            'firmanavn' => 'Test Currency Company',
            'telefon' => '11111111',
            'email' => 'test-currency@company.com',
            'momssats' => 25.0,
            'valuta' => 'EUR',
            'sum' => 500.00
        ];

        $response = $this->makeRequest('POST', $orderData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdOrderIds[] = $response['data']['id'];
            echo "✓ Order created with EUR currency, ID: " . $response['data']['id'] . "\n";
            
            if (isset($response['data']['valuta']) && $response['data']['valuta'] === 'EUR') {
                echo "✓ Currency correctly set to EUR\n";
            }
        } else {
            throw new Exception("Failed to create order with currency: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating order with full address information
     */
    public function testCreateOrderWithFullAddress()
    {
        echo "Testing: Create Order with Full Address\n";
        
        $orderData = [
            'firmanavn' => 'Test Full Address Company',
            'telefon' => '22222222',
            'email' => 'test-address@company.com',
            'momssats' => 25.0,
            'addr1' => 'Main Street 456',
            'addr2' => 'Building B, Floor 3',
            'postnr' => '1000',
            'bynavn' => 'Copenhagen',
            'land' => 'Denmark',
            'cvrnr' => '12345678',
            'ean' => '1234567890123'
        ];

        $response = $this->makeRequest('POST', $orderData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdOrderIds[] = $response['data']['id'];
            echo "✓ Order created with full address, ID: " . $response['data']['id'] . "\n";
        } else {
            throw new Exception("Failed to create order with full address: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating order with delivery address
     */
    public function testCreateOrderWithDeliveryAddress()
    {
        echo "Testing: Create Order with Delivery Address\n";
        
        $orderData = [
            'firmanavn' => 'Test Delivery Company',
            'telefon' => '33333333',
            'email' => 'test-delivery@company.com',
            'momssats' => 25.0,
            'addr1' => 'Billing Street 123',
            'postnr' => '2000',
            'bynavn' => 'Billing City',
            'lev_navn' => 'Delivery Department',
            'lev_addr1' => 'Delivery Street 789',
            'lev_addr2' => 'Warehouse A',
            'lev_postnr' => '3000',
            'lev_bynavn' => 'Delivery City',
            'lev_land' => 'Denmark'
        ];

        $response = $this->makeRequest('POST', $orderData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdOrderIds[] = $response['data']['id'];
            echo "✓ Order created with delivery address, ID: " . $response['data']['id'] . "\n";
        } else {
            throw new Exception("Failed to create order with delivery address: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test getting non-existent order
     */
    public function testGetNonExistentOrder()
    {
        echo "Testing: Get Non-Existent Order\n";
        
        $response = $this->makeRequest('GET', null, '?id=999999');
        
        // Debug the raw response
        echo "Raw response debug:\n";
        var_dump($response);
        echo "Response type: " . gettype($response) . "\n";
        
        if (isset($response['success'])) {
            echo "Success field exists, value: ";
            var_dump($response['success']);
            echo "Success field type: " . gettype($response['success']) . "\n";
        } else {
            echo "Success field does NOT exist in response\n";
        }
        
        // Check for the correct condition
        if (isset($response['success']) && $response['success'] === false) {
            echo "✓ Correctly returned error for non-existent order\n";
        } else {
            throw new Exception("Should have returned success=false for non-existent order");
        }
        
        echo "\n";
    }

    /**
     * Test getting orders with invalid date range
     */
    public function testGetOrdersWithInvalidDateRange()
    {
        echo "Testing: Get Orders with Invalid Date Range\n";
        
        $response = $this->makeRequest('GET', null, '?fromDate=invalid-date&toDate=also-invalid');
        
        if (!$response['success'] && strpos($response['message'], 'Invalid date format') !== false) {
            echo "✓ Correctly rejected invalid date format\n";
        } else {
            throw new Exception("Should have rejected invalid date format");
        }
        
        echo "\n";
    }

    /**
     * Test unsupported HTTP methods
     */
    public function testUnsupportedMethods()
    {
        echo "Testing: Unsupported HTTP Methods\n";
        
        // Test PUT method
        $putData = ['id' => 1, 'firmanavn' => 'Updated Company'];
        $response = $this->makeRequest('PUT', $putData);
        
        if (!$response['success'] && strpos($response['message'], 'PUT method is not supported') !== false) {
            echo "✓ Correctly rejected PUT method\n";
        } else {
            throw new Exception("Should have rejected PUT method");
        }

        // Test DELETE method
        $deleteData = ['id' => 1];
        $response = $this->makeRequest('DELETE', $deleteData);
        
        if (!$response['success'] && strpos($response['message'], 'DELETE method is not supported') !== false) {
            echo "✓ Correctly rejected DELETE method\n";
        } else {
            throw new Exception("Should have rejected DELETE method");
        }
        
        echo "\n";
    }

    /**
     * Make HTTP request to API endpoint
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
        
        // Debug: Show raw response
        echo "HTTP Code: $httpCode\n";
        echo "Raw Response: " . substr($response, 0, 500) . "\n"; // First 500 chars
        
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

    /**
     * Clean up created test data
     * Note: Since DELETE is not supported, we'll just log the IDs that were created
     */
    private function cleanup()
    {
        echo "\n=== Cleanup ===\n";
        
        if (empty($this->createdOrderIds)) {
            echo "No orders to clean up.\n";
            return;
        }

        echo "Created order IDs during testing:\n";
        foreach ($this->createdOrderIds as $orderId) {
            echo "- Order ID: $orderId\n";
        }
        
        echo "\nNote: DELETE method is not supported for orders.\n";
        echo "Please manually clean up test orders if needed.\n";
    }
}

// Run the tests
$tester = new OrderEndpointTest();
$tester->runAllTests();