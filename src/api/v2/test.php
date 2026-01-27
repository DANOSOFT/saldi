<?php
// Configuration
$apiKey = '13150a782893ca1b7c76e05259820922bd439d888e2383e6cdadafbcf5d7d119'; // Fixed API key
$baseUrl = 'https://dev.saldi.dk/pblm/api/v2/addresses.php'; // Added addresses.php endpoint

// Helper function to make API requests
function makeRequest($url, $method = 'GET', $data = null) {
    global $apiKey;
    
    echo "Making request to: " . $url . "\n";
    echo "Method: " . $method . "\n";
    echo "API Key: " . $apiKey . "\n";
    if ($data) {
        echo "Data: " . json_encode($data) . "\n";
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Set headers properly
    $headers = [
        'X-API-Key: ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // Enable error reporting
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    // Additional debugging options
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    // Print request headers for debugging
    echo "Request Headers:\n";
    foreach ($headers as $header) {
        echo $header . "\n";
    }
    
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
    
    // Get verbose information
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    
    if ($response === false) {
        echo "cURL Error: " . curl_error($ch) . "\n";
        echo "Verbose information:\n", htmlspecialchars($verboseLog), "\n";
        echo "cURL Info: " . print_r(curl_getinfo($ch), true) . "\n";
    }
    
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'response' => json_decode($response, true),
        'raw_response' => $response
    ];
}

// Test functions
function testGetAllAddresses() {
    global $baseUrl;
    echo "Testing GET all addresses...\n";
    $result = makeRequest($baseUrl);
    echo "Status Code: " . $result['code'] . "\n";
    echo "Raw Response: " . $result['raw_response'] . "\n";
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";
}

function testGetSingleAddress($id) {
    global $baseUrl;
    echo "Testing GET single address (ID: $id)...\n";
    $result = makeRequest($baseUrl . "?id=$id");
    echo "Status Code: " . $result['code'] . "\n";
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";
}

function testCreateAddress() {
    global $baseUrl;
    echo "Testing POST create address...\n";
    $data = [
        'firmanavn' => 'Test Company',
        'addr1' => 'Test Street 123',
        'postnr' => '1234',
        'bynavn' => 'Test City',
        'land' => 'Denmark',
        'tlf' => '12345678',
        'email' => 'test@example.com'
    ];
    $result = makeRequest($baseUrl, 'POST', $data);
    echo "Status Code: " . $result['code'] . "\n";
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";
    return $result['response']['id'] ?? null;
}

function testUpdateAddress($id) {
    global $baseUrl;
    echo "Testing PUT update address (ID: $id)...\n";
    $data = [
        'firmanavn' => 'Updated Company',
        'addr1' => 'Updated Street 456',
        'postnr' => '5678',
        'bynavn' => 'Updated City'
    ];
    $result = makeRequest($baseUrl . "?id=$id", 'PUT', $data);
    echo "Status Code: " . $result['code'] . "\n";
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";
}

function testDeleteAddress($id) {
    global $baseUrl;
    echo "Testing DELETE address (ID: $id)...\n";
    $result = makeRequest($baseUrl . "?id=$id", 'DELETE');
    echo "Status Code: " . $result['code'] . "\n";
    echo "Response: " . json_encode($result['response'], JSON_PRETTY_PRINT) . "\n\n";
}

// Run tests
echo "Starting API Tests...\n\n";

// Test 1: Get all addresses
testGetAllAddresses();

// Test 2: Create a new address
$newId = testCreateAddress();

if ($newId) {
    // Test 3: Get the newly created address
    testGetSingleAddress($newId);
    
    // Test 4: Update the address
    testUpdateAddress($newId);
    
    // Test 5: Get the updated address
    testGetSingleAddress($newId);
    
    // Test 6: Delete the address
    testDeleteAddress($newId);
    
    // Test 7: Try to get the deleted address (should fail)
    testGetSingleAddress($newId);
}

echo "API Tests Completed!\n"; 