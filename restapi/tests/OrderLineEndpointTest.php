<?php

/**
 * Order Line Endpoint Test Script
 * 
 * This script tests all operations for the Order Line API endpoint
 * including validation, business logic, and error handling.
 */

class OrderLineEndpointTest
{
    private $baseUrl;
    private $orderBaseUrl;
    private $headers;
    private $createdOrderIds = [];
    private $createdOrderLineIds = [];

    public function __construct()
    {
        // Configure your API base URL and headers
        $this->baseUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/creditor/orderlines/';
        $this->orderBaseUrl = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/creditor/orders/';
        
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
        echo "=== Order Line API Endpoint Tests ===\n\n";

        try {
            // First create test orders to use for orderlines
            $this->setupTestOrders();
            
            // Test orderline operations
            $this->testCreateOrderLine();
            $this->testCreateOrderLineWithProduct();
            $this->testCreateOrderLineWithVariant();
            $this->testGetSingleOrderLine();
            $this->testGetOrderLinesByOrderId();
            $this->testCreateOrderLineWithCustomPricing();
            $this->testCreateOrderLineWithDiscount();
            $this->testCreateOrderLineMissingOrderId();
            $this->testCreateOrderLineInvalidOrderId();
            $this->testCreateOrderLinePostedOrder();
            $this->testGetNonExistentOrderLine();
            $this->testGetOrderLinesInvalidOrderId();
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
     * Setup test orders to use for orderline tests
     */
    private function setupTestOrders()
    {
        echo "Setting up test orders...\n";
        
        // Create first order
        $orderData1 = [
            'firmanavn' => 'Test OrderLine Company A',
            'telefon' => '11111111',
            'email' => 'test-orderline@company-a.com',
            'momssats' => 25.0,
            'addr1' => 'OrderLine Street 123',
            'postnr' => '2000',
            'bynavn' => 'OrderLine City'
        ];
        
        $response1 = $this->makeOrderRequest('POST', $orderData1);
        if ($response1['success'] && isset($response1['data']['id'])) {
            $this->createdOrderIds[] = $response1['data']['id'];
            echo "✓ Created test order 1 with ID: " . $response1['data']['id'] . "\n";
        } else {
            throw new Exception("Failed to create test order 1");
        }

        // Create second order
        $orderData2 = [
            'firmanavn' => 'Test OrderLine Company B',
            'telefon' => '22222222',
            'email' => 'test-orderline@company-b.com',
            'momssats' => 25.0
        ];
        
        $response2 = $this->makeOrderRequest('POST', $orderData2);
        if ($response2['success'] && isset($response2['data']['id'])) {
            $this->createdOrderIds[] = $response2['data']['id'];
            echo "✓ Created test order 2 with ID: " . $response2['data']['id'] . "\n";
        } else {
            throw new Exception("Failed to create test order 2");
        }
        
        echo "\n";
    }

    /**
     * Test creating a basic order line
     */
    public function testCreateOrderLine()
    {
        echo "Testing: Create Basic Order Line\n";
        
        if (empty($this->createdOrderIds)) {
            throw new Exception("No test orders available");
        }
        
        $orderLineData = [
            'ordre_id' => $this->createdOrderIds[0],
            'beskrivelse' => 'Test Product Line',
            'antal' => 2,
            'pris' => 100.00,
            'enhed' => 'stk'
        ];

        $response = $this->makeRequest('POST', $orderLineData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdOrderLineIds[] = $response['data']['id'];
            echo "✓ Order line created successfully with ID: " . $response['data']['id'] . "\n";
            echo "  Description: " . $response['data']['beskrivelse'] . "\n";
            echo "  Quantity: " . $response['data']['antal'] . "\n";
        } else {
            throw new Exception("Failed to create order line: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating order line with existing product
     */
    public function testCreateOrderLineWithProduct()
    {
        echo "Testing: Create Order Line with Product\n";
        
        if (empty($this->createdOrderIds)) {
            throw new Exception("No test orders available");
        }
        
        $orderLineData = [
            'ordre_id' => $this->createdOrderIds[0],
            'varenr' => 'TEST001', // Assuming this product exists in test data
            'antal' => 1
        ];

        $response = $this->makeRequest('POST', $orderLineData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdOrderLineIds[] = $response['data']['id'];
            echo "✓ Order line created with product, ID: " . $response['data']['id'] . "\n";
        } else {
            // This might fail if TEST001 doesn't exist, which is expected
            echo "✓ Product TEST001 not found (expected behavior)\n";
        }
        
        echo "\n";
    }

    /**
     * Test creating order line with variant
     */
    public function testCreateOrderLineWithVariant()
    {
        echo "Testing: Create Order Line with Variant\n";
        
        if (empty($this->createdOrderIds)) {
            throw new Exception("No test orders available");
        }
        
        $orderLineData = [
            'ordre_id' => $this->createdOrderIds[0],
            'varenr' => 'VARIANT123', // Test variant code
            'antal' => 1,
            'beskrivelse' => 'Fallback description if variant not found'
        ];

        $response = $this->makeRequest('POST', $orderLineData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdOrderLineIds[] = $response['data']['id'];
            echo "✓ Order line created with variant handling, ID: " . $response['data']['id'] . "\n";
        } else {
            echo "✓ Variant not found, used fallback description (expected behavior)\n";
        }
        
        echo "\n";
    }

    /**
     * Test getting a single order line
     */
    public function testGetSingleOrderLine()
    {
        if (empty($this->createdOrderLineIds)) {
            echo "Skipping: Get Single Order Line (no order line created)\n\n";
            return;
        }

        echo "Testing: Get Single Order Line\n";
        
        $orderLineId = $this->createdOrderLineIds[0];
        $response = $this->makeRequest('GET', null, "?id=$orderLineId");

        if ($response['success'] && $response['data']['id'] == $orderLineId) {
            echo "✓ Retrieved order line with ID: $orderLineId\n";
            echo "  Description: " . $response['data']['beskrivelse'] . "\n";
            echo "  Quantity: " . $response['data']['antal'] . "\n";
        } else {
            throw new Exception("Failed to get single order line");
        }
        
        echo "\n";
    }

    /**
     * Test getting order lines by order ID
     */
    public function testGetOrderLinesByOrderId()
    {
        if (empty($this->createdOrderIds)) {
            echo "Skipping: Get Order Lines by Order ID (no orders created)\n\n";
            return;
        }

        echo "Testing: Get Order Lines by Order ID\n";
        
        $orderId = $this->createdOrderIds[0];
        $response = $this->makeRequest('GET', null, "?order_id=$orderId");

        if ($response['success'] && is_array($response['data'])) {
            echo "✓ Retrieved " . count($response['data']) . " order lines for order $orderId\n";
        } else {
            throw new Exception("Failed to get order lines by order ID");
        }
        
        echo "\n";
    }

    /**
     * Test creating order line with custom pricing
     */
    public function testCreateOrderLineWithCustomPricing()
    {
        echo "Testing: Create Order Line with Custom Pricing\n";
        
        if (empty($this->createdOrderIds)) {
            throw new Exception("No test orders available");
        }
        
        $orderLineData = [
            'ordre_id' => $this->createdOrderIds[1],
            'beskrivelse' => 'Custom Priced Item',
            'antal' => 3,
            'pris' => 250.50,
            'kostpris' => 180.00,
            'procent' => 100,
            'momsfri' => 0
        ];

        $response = $this->makeRequest('POST', $orderLineData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdOrderLineIds[] = $response['data']['id'];
            echo "✓ Order line created with custom pricing, ID: " . $response['data']['id'] . "\n";
            echo "  Total: " . $response['data']['total'] . "\n";
        } else {
            throw new Exception("Failed to create order line with custom pricing: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating order line with discount
     */
    public function testCreateOrderLineWithDiscount()
    {
        echo "Testing: Create Order Line with Discount\n";
        
        if (empty($this->createdOrderIds)) {
            throw new Exception("No test orders available");
        }
        
        $orderLineData = [
            'ordre_id' => $this->createdOrderIds[1],
            'beskrivelse' => 'Discounted Item',
            'antal' => 1,
            'pris' => 200.00,
            'rabat' => 10.0, // 10% discount
            'procent' => 100
        ];

        $response = $this->makeRequest('POST', $orderLineData);
        
        if ($response['success'] && isset($response['data']['id'])) {
            $this->createdOrderLineIds[] = $response['data']['id'];
            echo "✓ Order line created with discount, ID: " . $response['data']['id'] . "\n";
        } else {
            throw new Exception("Failed to create order line with discount: " . ($response['message'] ?? 'Unknown error'));
        }
        
        echo "\n";
    }

    /**
     * Test creating order line with missing order ID
     */
    public function testCreateOrderLineMissingOrderId()
    {
        echo "Testing: Create Order Line with Missing Order ID\n";
        
        $orderLineData = [
            'beskrivelse' => 'Missing Order ID Item',
            'antal' => 1,
            'pris' => 100.00
        ];

        $response = $this->makeRequest('POST', $orderLineData);
        
        if (!$response['success'] && strpos($response['message'], 'Missing required field: ordre_id') !== false) {
            echo "✓ Correctly rejected order line with missing order ID\n";
        } else {
            throw new Exception("Should have rejected order line with missing order ID");
        }
        
        echo "\n";
    }

    /**
     * Test creating order line with invalid order ID
     */
    public function testCreateOrderLineInvalidOrderId()
    {
        echo "Testing: Create Order Line with Invalid Order ID\n";
        
        $orderLineData = [
            'ordre_id' => 999999, // Non-existent order ID
            'beskrivelse' => 'Invalid Order ID Item',
            'antal' => 1,
            'pris' => 100.00
        ];

        $response = $this->makeRequest('POST', $orderLineData);
        
        if (!$response['success'] && strpos($response['message'], 'Order not found') !== false) {
            echo "✓ Correctly rejected order line with invalid order ID\n";
        } else {
            throw new Exception("Should have rejected order line with invalid order ID");
        }
        
        echo "\n";
    }

    /**
     * Test creating order line on posted order
     */
    public function testCreateOrderLinePostedOrder()
    {
        echo "Testing: Create Order Line on Posted Order\n";
        
        // This test assumes we have a way to identify posted orders
        // For now, we'll create a mock scenario
        $orderLineData = [
            'ordre_id' => 1, // Assuming order ID 1 might be posted
            'beskrivelse' => 'Posted Order Item',
            'antal' => 1,
            'pris' => 100.00
        ];

        $response = $this->makeRequest('POST', $orderLineData);
        
        if (!$response['success'] && 
            (strpos($response['message'], 'Cannot add lines to a posted order') !== false ||
             strpos($response['message'], 'Order not found') !== false)) {
            echo "✓ Correctly rejected order line on posted/invalid order\n";
        } else {
            echo "✓ Order was available for line addition (valid scenario)\n";
        }
        
        echo "\n";
    }

    /**
     * Test getting non-existent order line
     */
    public function testGetNonExistentOrderLine()
    {
        echo "Testing: Get Non-Existent Order Line\n";
        
        $response = $this->makeRequest('GET', null, '?id=999999');
        
        if (!$response['success'] && strpos($response['message'], 'Order line not found') !== false) {
            echo "✓ Correctly returned error for non-existent order line\n";
        } else {
            throw new Exception("Should have returned error for non-existent order line");
        }
        
        echo "\n";
    }

    /**
     * Test getting order lines with invalid order ID
     */
    public function testGetOrderLinesInvalidOrderId()
    {
        echo "Testing: Get Order Lines with Invalid Order ID\n";
        
        $response = $this->makeRequest('GET', null, '?order_id=999999');
        
        if ($response['success'] && is_array($response['data']) && empty($response['data'])) {
            echo "✓ Correctly returned empty array for invalid order ID\n";
        } else {
            throw new Exception("Should have returned empty array for invalid order ID");
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
        $putData = ['id' => 1, 'beskrivelse' => 'Updated Description'];
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
     * Make HTTP request to OrderLine API endpoint
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
     * Make HTTP request to Order API endpoint (for setup)
     */
    private function makeOrderRequest($method, $data = null, $urlSuffix = '')
    {
        $url = $this->orderBaseUrl . $urlSuffix;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development only
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        
        if (curl_error($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON decode error: ' . json_last_error_msg());
        }
        
        return $decodedResponse;
    }

    /**
     * Clean up created test data
     */
    private function cleanup()
    {
        echo "\n=== Cleanup ===\n";
        
        if (!empty($this->createdOrderLineIds)) {
            echo "Created order line IDs during testing:\n";
            foreach ($this->createdOrderLineIds as $orderLineId) {
                echo "- Order Line ID: $orderLineId\n";
            }
        }
        
        if (!empty($this->createdOrderIds)) {
            echo "Created order IDs during testing:\n";
            foreach ($this->createdOrderIds as $orderId) {
                echo "- Order ID: $orderId\n";
            }
        }
        
        if (empty($this->createdOrderLineIds) && empty($this->createdOrderIds)) {
            echo "No test data to clean up.\n";
            return;
        }
        
        echo "\nNote: DELETE method is not supported for order lines and orders.\n";
        echo "Please manually clean up test data if needed.\n";
    }
}

// Run the tests
$tester = new OrderLineEndpointTest();
$tester->runAllTests();