<?php

/**
 * Combined test script for Order and Order Line REST API endpoints
 * This script first creates an order, then creates multiple order lines for that order
 */

// Configuration
$base_url_orders = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/debitor/orders/';
$base_url_orderlines = 'https://ssl12.saldi.dk/pblm/restapi/endpoints/v1/debitor/orderlines/';
$test_db = 'test_4';
$test_user = 'api';
$test_auth = '4M1SlprEv82hhtl2KSfCFOs4BzLYgAdUD';

/**
 * Helper function to make HTTP requests
 */
function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    return [
        'http_code' => $http_code,
        'response' => $response,
        'error' => $error
    ];
}

/**
 * Print test results with detailed debugging
 */
function printTestResult($test_name, $result, $expected_code = 200) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "TEST: $test_name\n";
    echo str_repeat("-", 60) . "\n";
    
    if (!empty($result['error'])) {
        echo "❌ CURL ERROR: " . $result['error'] . "\n";
        return false;
    }
    
    $response_data = json_decode($result['response'], true);
    
    echo "HTTP Code: " . $result['http_code'] . "\n";
    echo "Expected: $expected_code\n";
    echo "Response: " . $result['response'] . "\n";
    
    // Additional debugging for JSON decode issues
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "⚠️ JSON DECODE ERROR: " . json_last_error_msg() . "\n";
    }
    
    if ($result['http_code'] == $expected_code) {
        echo "✅ STATUS: PASSED\n";
        return true;
    } else {
        echo "❌ STATUS: FAILED\n";
        return false;
    }
}

// Common headers for all requests
$headers = [
    'Content-Type: application/json',
    'Authorization: ' . $test_auth,
    'x-saldiuser: ' . $test_user,
    'x-db: ' . $test_db
];

echo "🚀 Starting Combined Order + Order Lines API Tests\n";
echo "Orders URL: $base_url_orders\n";
echo "Order Lines URL: $base_url_orderlines\n";
echo "Test Database: $test_db\n";
echo "Test User: $test_user\n";

echo "\n" . str_repeat("🏗️", 30) . " ORDER CREATION " . str_repeat("🏗️", 30) . "\n";

// Step 1: Create a new order
$order_data = [
    // Required fields
    'firmanavn' => 'Combined Test Company A/S',
    'telefon' => '+4512345678',  
    'email' => 'combined-test@company.dk',
    'momssats' => 25.0,
    
    // Optional fields
    'sum' => 0.00,  // Will be calculated from order lines
    'kostpris' => 0.00,
    'moms' => 0.00,
    'valuta' => 'DKK',
    
    // Address fields
    'addr1' => 'Testgade 123',
    'addr2' => '2. sal',
    'postnr' => '2100',
    'bynavn' => 'København Ø',
    'land' => 'Danmark',
    
    // Delivery address
    'lev_navn' => 'Test Modtager',
    'lev_addr1' => 'Leveringsgade 456',
    'lev_addr2' => '',
    'lev_postnr' => '2200',
    'lev_bynavn' => 'København N',
    'lev_land' => 'Danmark',
    
    // Other fields
    'ean' => '1234567890123',
    'cvrnr' => 'DK12345678',
    'ordredate' => date('Y-m-d'),
    'notes' => 'Combined test ordre oprettet via REST API - will have order lines added',
    'betalt' => false
];

echo "📤 Sending order data:\n";
echo json_encode($order_data, JSON_PRETTY_PRINT) . "\n";

$result_order = makeRequest($base_url_orders, 'POST', $order_data, $headers);
$order_test_passed = printTestResult('Create Order for Order Lines Test', $result_order, 201);

// More detailed debugging for order creation
echo "\n🔍 DEBUGGING ORDER CREATION:\n";
echo "Raw response: " . $result_order['response'] . "\n";

$order_response = json_decode($result_order['response'], true);
echo "Decoded response: " . print_r($order_response, true) . "\n";

if (!$order_test_passed) {
    echo "\n❌ FATAL ERROR: Could not create order. Response details:\n";
    echo "HTTP Code: " . $result_order['http_code'] . "\n";
    echo "Response: " . $result_order['response'] . "\n";
    
    // Try to determine what went wrong
    if ($result_order['http_code'] == 401) {
        echo "🔐 AUTHENTICATION ISSUE: Check your API key and database credentials\n";
    } elseif ($result_order['http_code'] == 500) {
        echo "🔥 SERVER ERROR: Check server logs for detailed error information\n";
    } elseif ($result_order['http_code'] == 400) {
        echo "📝 VALIDATION ERROR: Check required fields and data format\n";
    }
    
    echo "Stopping test.\n";
    exit(1);
}

// Extract order ID from response with multiple fallback strategies
$order_id = null;

// Strategy 1: Check data.id
if (isset($order_response['data']['id'])) {
    $order_id = $order_response['data']['id'];
    echo "✅ Order ID found in data.id: $order_id\n";
}

// Strategy 2: Check if response is directly the order data
if (!$order_id && isset($order_response['id'])) {
    $order_id = $order_response['id'];
    echo "✅ Order ID found directly: $order_id\n";
}

// Strategy 3: Check for order_id field
if (!$order_id && isset($order_response['data']['order_id'])) {
    $order_id = $order_response['data']['order_id'];
    echo "✅ Order ID found in data.order_id: $order_id\n";
}

// Strategy 4: Check all fields for any ID-like value
if (!$order_id) {
    echo "🔍 Searching for order ID in response...\n";
    if (is_array($order_response)) {
        foreach ($order_response as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subkey => $subvalue) {
                    if (strpos($subkey, 'id') !== false && is_numeric($subvalue)) {
                        echo "   Found potential ID: $subkey = $subvalue\n";
                    }
                }
            } elseif (strpos($key, 'id') !== false && is_numeric($value)) {
                echo "   Found potential ID: $key = $value\n";
            }
        }
    }
}

if (!$order_id) {
    echo "\n❌ FATAL ERROR: Could not extract order ID from response.\n";
    echo "Response structure: " . print_r($order_response, true) . "\n";
    echo "Please check your OrderService.createOrder() method to ensure it returns the order ID properly.\n";
    exit(1);
}

echo "\n✅ SUCCESS: Order created with ID: $order_id\n";

// Continue with the rest of the test (order lines creation)
echo "\n" . str_repeat("📝", 30) . " ORDER LINES CREATION " . str_repeat("📝", 30) . "\n";

// Step 2: Create multiple order lines for the created order
$orderline_tests = [];

// Test 1: Create order line with product number
$test_data_1 = [
    'ordre_id' => $order_id,
    'varenr' => '190008',
    'antal' => 2,
    'pris' => 100.50,
    'beskrivelse' => 'Test product line 1'
];

$result_1 = makeRequest($base_url_orderlines, 'POST', $test_data_1, $headers);
$orderline_tests['Product Number Line'] = printTestResult('Create Order Line with Product Number', $result_1, 201);

// Test 2: Create order line with only description (no product)
$test_data_2 = [
    'ordre_id' => $order_id,
    'antal' => 1,
    'pris' => 50.00,
    'beskrivelse' => 'Manual line item - no product reference'
];

$result_2 = makeRequest($base_url_orderlines, 'POST', $test_data_2, $headers);
$orderline_tests['Description Only Line'] = printTestResult('Create Order Line with Description Only', $result_2, 201);

// Test 3: Create order line with vare_id
$test_data_3 = [
    'ordre_id' => $order_id,
    'vare_id' => 10,
    'antal' => 3,
    'rabat' => 10.0,
    'beskrivelse' => 'Product by ID with discount'
];

$result_3 = makeRequest($base_url_orderlines, 'POST', $test_data_3, $headers);
$orderline_tests['Product ID Line'] = printTestResult('Create Order Line with Product ID', $result_3, 201);

// Test 4: Create order line with VAT-free setting
$test_data_4 = [
    'ordre_id' => $order_id,
    'varenr' => '190008',
    'antal' => 1,
    'pris' => 200.00,
    'momsfri' => 1,
    'beskrivelse' => 'VAT-free product line'
];

$result_4 = makeRequest($base_url_orderlines, 'POST', $test_data_4, $headers);
$orderline_tests['VAT-Free Line'] = printTestResult('Create VAT-Free Order Line', $result_4, 201);

// Test 5: Create order line with percentage discount
$test_data_5 = [
    'ordre_id' => $order_id,
    'varenr' => '190008',
    'antal' => 2,
    'pris' => 150.00,
    'rabat' => 15.0,
    'procent' => 85,
    'beskrivelse' => 'Product with 15% discount'
];

$result_5 = makeRequest($base_url_orderlines, 'POST', $test_data_5, $headers);
$orderline_tests['Discount Line'] = printTestResult('Create Order Line with Discount', $result_5, 201);

echo "\n" . str_repeat("📋", 30) . " VERIFICATION TESTS " . str_repeat("📋", 30) . "\n";

// Step 3: Verify order lines were created by retrieving them
$get_url = $base_url_orderlines . '?order_id=' . $order_id;
$result_get = makeRequest($get_url, 'GET', null, $headers);
$get_test_passed = printTestResult('Retrieve Order Lines for Created Order', $result_get, 200);

// Step 4: Verify the order still exists and can be retrieved
$get_order_url = $base_url_orders . '?id=' . $order_id;
$result_get_order = makeRequest($get_order_url, 'GET', null, $headers);
$get_order_test_passed = printTestResult('Retrieve Created Order', $result_get_order, 200);

echo "\n" . str_repeat("🔍", 30) . " ERROR TESTS " . str_repeat("🔍", 30) . "\n";

// Step 5: Test error scenarios
// Test error: missing ordre_id
$test_error_1 = [
    'varenr' => '190008',
    'antal' => 1,
    'beskrivelse' => 'This should fail - no ordre_id'
];

$result_error_1 = makeRequest($base_url_orderlines, 'POST', $test_error_1, $headers);
$error_test_1 = printTestResult('Error Test - Missing ordre_id', $result_error_1, 400);

// Test error: invalid order ID
$test_error_2 = [
    'ordre_id' => 99999,
    'varenr' => '190008',
    'antal' => 1,
    'beskrivelse' => 'This should fail - invalid order'
];

$result_error_2 = makeRequest($base_url_orderlines, 'POST', $test_error_2, $headers);
$error_test_2 = printTestResult('Error Test - Invalid Order ID', $result_error_2, 400);

echo "\n" . str_repeat("📊", 30) . " FINAL SUMMARY " . str_repeat("📊", 30) . "\n";

// Final summary
$all_tests = [
    'Order Creation' => $order_test_passed,
    'Product Number Line' => $orderline_tests['Product Number Line'],
    'Description Only Line' => $orderline_tests['Description Only Line'],
    'Product ID Line' => $orderline_tests['Product ID Line'],
    'VAT-Free Line' => $orderline_tests['VAT-Free Line'],
    'Discount Line' => $orderline_tests['Discount Line'],
    'Retrieve Order Lines' => $get_test_passed,
    'Retrieve Order' => $get_order_test_passed,
    'Error - Missing ordre_id' => $error_test_1,
    'Error - Invalid Order ID' => $error_test_2
];

$passed = 0;
$total = count($all_tests);

foreach ($all_tests as $test_name => $result) {
    $status = $result ? '✅ PASSED' : '❌ FAILED';
    echo "$status: $test_name\n";
    if ($result) $passed++;
}

echo "\n" . str_repeat("-", 80) . "\n";
echo "OVERALL RESULTS: $passed/$total tests passed\n";

if ($passed == $total) {
    echo "🎉 ALL TESTS PASSED! 🎉\n";
    echo "✨ Order ID $order_id was created successfully with multiple order lines\n";
} else {
    echo "⚠️  SOME TESTS FAILED - Check the output above for details\n";
}

// Show final order summary if we can retrieve the order lines
if ($get_test_passed) {
    echo "\n" . str_repeat("📄", 30) . " ORDER SUMMARY " . str_repeat("📄", 30) . "\n";
    
    $orderlines_response = json_decode($result_get['response'], true);
    if ($orderlines_response && isset($orderlines_response['data'])) {
        $lines = $orderlines_response['data'];
        echo "Order ID: $order_id\n";
        echo "Total Order Lines: " . count($lines) . "\n";
        echo "\nOrder Lines Summary:\n";
        
        $total_amount = 0;
        foreach ($lines as $index => $line) {
            $line_total = ($line['pris'] ?? 0) * ($line['antal'] ?? 1);
            $total_amount += $line_total;
            
            echo sprintf(
                "  %d. %s (Qty: %s, Price: %.2f, Total: %.2f)\n",
                $index + 1,
                $line['beskrivelse'] ?? 'No description',
                $line['antal'] ?? 1,
                $line['pris'] ?? 0,
                $line_total
            );
        }
        
        echo "\nEstimated Order Total: " . number_format($total_amount, 2) . " DKK\n";
    }
}

echo "\n🏁 Combined test script completed!\n";
echo "💡 You can now check order ID $order_id in your system to verify the results.\n";