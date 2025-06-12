<?php

/**
 * Test script for Order Line REST API endpoint
 * This script tests the creation of order lines via POST requests
 */

// Configuration
$base_url = 'http://localhost/pblm/restapi/endpoints/v1/debitor/orderlines/';
$test_db = 'test_db';
$test_user = 'test_user';
$test_auth = 'test_auth_token';

// Test order ID (make sure this order exists in your database)
$test_order_id = 1;

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
 * Print test results
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
    'Authorization: Bearer ' . $test_auth,
    'x-saldiuser: ' . $test_user,
    'x-db: ' . $test_db
];

echo "🚀 Starting Order Line API Tests\n";
echo "Base URL: $base_url\n";
echo "Test Database: $test_db\n";
echo "Test User: $test_user\n";

// Test 1: Create order line with product number
echo "\n" . str_repeat("🧪", 30) . " TEST SUITE START " . str_repeat("🧪", 30) . "\n";

$test_data_1 = [
    'ordre_id' => $test_order_id,
    'varenr' => 'TEST001',
    'antal' => 2,
    'pris' => 100.50,
    'beskrivelse' => 'Test product line 1'
];

$result_1 = makeRequest($base_url, 'POST', $test_data_1, $headers);
$test_1_passed = printTestResult('Create Order Line with Product Number', $result_1, 201);

// Test 2: Create order line with only description (no product)
$test_data_2 = [
    'ordre_id' => $test_order_id,
    'antal' => 1,
    'pris' => 50.00,
    'beskrivelse' => 'Manual line item - no product reference'
];

$result_2 = makeRequest($base_url, 'POST', $test_data_2, $headers);
$test_2_passed = printTestResult('Create Order Line with Description Only', $result_2, 201);

// Test 3: Create order line with vare_id
$test_data_3 = [
    'ordre_id' => $test_order_id,
    'vare_id' => 1,
    'antal' => 3,
    'rabat' => 10.0,
    'beskrivelse' => 'Product by ID with discount'
];

$result_3 = makeRequest($base_url, 'POST', $test_data_3, $headers);
$test_3_passed = printTestResult('Create Order Line with Product ID', $result_3, 201);

// Test 4: Create order line with minimum required data
$test_data_4 = [
    'ordre_id' => $test_order_id,
    'beskrivelse' => 'Minimum data test line'
];

$result_4 = makeRequest($base_url, 'POST', $test_data_4, $headers);
$test_4_passed = printTestResult('Create Order Line with Minimum Data', $result_4, 201);

// Test 5: Error test - missing ordre_id
$test_data_5 = [
    'varenr' => 'TEST005',
    'antal' => 1,
    'beskrivelse' => 'This should fail - no ordre_id'
];

$result_5 = makeRequest($base_url, 'POST', $test_data_5, $headers);
$test_5_passed = printTestResult('Error Test - Missing ordre_id', $result_5, 400);

// Test 6: Error test - invalid order ID
$test_data_6 = [
    'ordre_id' => 99999,
    'varenr' => 'TEST006',
    'antal' => 1,
    'beskrivelse' => 'This should fail - invalid order'
];

$result_6 = makeRequest($base_url, 'POST', $test_data_6, $headers);
$test_6_passed = printTestResult('Error Test - Invalid Order ID', $result_6, 400);

// Test 7: Error test - missing authorization header
$headers_no_auth = [
    'Content-Type: application/json',
    'x-saldiuser: ' . $test_user,
    'x-db: ' . $test_db
];

$result_7 = makeRequest($base_url, 'POST', $test_data_1, $headers_no_auth);
$test_7_passed = printTestResult('Error Test - Missing Authorization', $result_7, 401);

// Test 8: Create order line with VAT-free setting
$test_data_8 = [
    'ordre_id' => $test_order_id,
    'varenr' => 'VATFREE001',
    'antal' => 1,
    'pris' => 200.00,
    'momsfri' => 1,
    'beskrivelse' => 'VAT-free product line'
];

$result_8 = makeRequest($base_url, 'POST', $test_data_8, $headers);
$test_8_passed = printTestResult('Create VAT-Free Order Line', $result_8, 201);

// Test 9: Create order line with percentage discount
$test_data_9 = [
    'ordre_id' => $test_order_id,
    'varenr' => 'DISCOUNT001',
    'antal' => 2,
    'pris' => 150.00,
    'rabat' => 15.0,
    'procent' => 85,
    'beskrivelse' => 'Product with 15% discount'
];

$result_9 = makeRequest($base_url, 'POST', $test_data_9, $headers);
$test_9_passed = printTestResult('Create Order Line with Discount', $result_9, 201);

// Test 10: GET request - retrieve order lines for the test order
$get_url = $base_url . '?order_id=' . $test_order_id;
$result_10 = makeRequest($get_url, 'GET', null, $headers);
$test_10_passed = printTestResult('Get Order Lines for Order', $result_10, 200);

// Test Summary
echo "\n" . str_repeat("📊", 30) . " TEST SUMMARY " . str_repeat("📊", 30) . "\n";

$tests = [
    'Create Order Line with Product Number' => $test_1_passed,
    'Create Order Line with Description Only' => $test_2_passed,
    'Create Order Line with Product ID' => $test_3_passed,
    'Create Order Line with Minimum Data' => $test_4_passed,
    'Error Test - Missing ordre_id' => $test_5_passed,
    'Error Test - Invalid Order ID' => $test_6_passed,
    'Error Test - Missing Authorization' => $test_7_passed,
    'Create VAT-Free Order Line' => $test_8_passed,
    'Create Order Line with Discount' => $test_9_passed,
    'Get Order Lines for Order' => $test_10_passed
];

$passed = 0;
$total = count($tests);

foreach ($tests as $test_name => $result) {
    $status = $result ? '✅ PASSED' : '❌ FAILED';
    echo "$status: $test_name\n";
    if ($result) $passed++;
}

echo "\n" . str_repeat("-", 80) . "\n";
echo "RESULTS: $passed/$total tests passed\n";

if ($passed == $total) {
    echo "🎉 ALL TESTS PASSED! 🎉\n";
} else {
    echo "⚠️  SOME TESTS FAILED - Check the output above for details\n";
}

// Additional helper function to create a test order if needed
function createTestOrder($base_url, $headers) {
    $order_url = str_replace('/orderlines/', '/orders/', $base_url);
    
    $order_data = [
        'firmanavn' => 'Test Company for Order Lines',
        'telefon' => '12345678',
        'email' => 'test@orderlines.com',
        'notes' => 'Test order created for order line testing'
    ];
    
    $result = makeRequest($order_url, 'POST', $order_data, $headers);
    
    if ($result['http_code'] == 201) {
        $response = json_decode($result['response'], true);
        return $response['data']['id'] ?? null;
    }
    
    return null;
}

// Uncomment the following lines if you need to create a test order first:
/*
echo "\n" . str_repeat("🔧", 20) . " CREATING TEST ORDER " . str_repeat("🔧", 20) . "\n";
$created_order_id = createTestOrder($base_url, $headers);
if ($created_order_id) {
    echo "✅ Test order created with ID: $created_order_id\n";
    echo "💡 Update \$test_order_id = $created_order_id in this script for future tests\n";
} else {
    echo "❌ Failed to create test order\n";
}
*/

echo "\n🏁 Test script completed!\n";

// Performance test function
function performanceTest($base_url, $headers, $order_id, $iterations = 10) {
    echo "\n" . str_repeat("⚡", 20) . " PERFORMANCE TEST " . str_repeat("⚡", 20) . "\n";
    echo "Creating $iterations order lines...\n";
    
    $start_time = microtime(true);
    $successful = 0;
    
    for ($i = 1; $i <= $iterations; $i++) {
        $test_data = [
            'ordre_id' => $order_id,
            'varenr' => "PERF$i",
            'antal' => rand(1, 5),
            'pris' => rand(10, 1000) / 10,
            'beskrivelse' => "Performance test line $i"
        ];
        
        $result = makeRequest($base_url, 'POST', $test_data, $headers);
        
        if ($result['http_code'] == 201) {
            $successful++;
        }
        
        if ($i % 5 == 0) {
            echo "Progress: $i/$iterations lines processed\n";
        }
    }
    
    $end_time = microtime(true);
    $duration = $end_time - $start_time;
    $avg_time = $duration / $iterations;
    
    echo "\n📈 Performance Results:\n";
    echo "- Total time: " . round($duration, 3) . " seconds\n";
    echo "- Average per request: " . round($avg_time * 1000, 2) . " ms\n";
    echo "- Successful requests: $successful/$iterations\n";
    echo "- Requests per second: " . round($iterations / $duration, 2) . "\n";
}

// Uncomment to run performance test:
// performanceTest($base_url, $headers, $test_order_id, 20);