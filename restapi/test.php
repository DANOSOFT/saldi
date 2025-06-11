<?php

// Setup the JSON according to ordrer.txt specifications
$data = [
    // Required fields
    'firmanavn' => 'Test Company A/S',
    'telefon' => '+4512345678',  // This is required for user lookup
    'email' => 'test@company.dk',
    'momssats' => 25.0,
    
    // Optional fields
    'sum' => 500.00,
    'kostpris' => 300.00,
    'moms' => 125.00,
    'valuta' => 'DKK',
    // betalingsbet and betalingsdage will be set automatically based on user lookup
    
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
    'ordredate' => date('Y-m-d'), // Today's date
    'notes' => 'Test ordre oprettet via REST API',
    'betalt' => false // Will be stored as empty string or "on"
];

echo "=== Testing Order Creation ===\n";
echo "JSON Data to send:\n";
echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

// Initialize cURL
$ch = curl_init("https://dev.saldi.dk/pblm/restapi/endpoints/v1/debitor/orders/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: 4M1SlprEv82hhtl2KSfCFOs4*BzLYgAdUD',
    'x-saldiuser: api',
    'x-db: develop_8'
]);

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    echo 'cURL Error: ' . curl_error($ch) . "\n";
} else {
    echo "HTTP Status Code: $httpCode\n";
    echo "Response:\n";
    
    // Pretty print JSON response
    $responseData = json_decode($response, true);
    if ($responseData) {
        echo json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
        
        // If order was created successfully, test GET request
        if (isset($responseData['success']) && $responseData['success'] && isset($responseData['data']['id'])) {
            $orderId = $responseData['data']['id'];
            echo "\n=== Testing Order Retrieval ===\n";
            testGetOrder($orderId);
        }
    } else {
        echo $response . "\n";
    }
}

curl_close($ch);

// Test GET request for specific order
function testGetOrder($orderId) {
    $ch = curl_init("https://dev.saldi.dk/pblm/restapi/endpoints/v1/debitor/orders/?id=$orderId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: 4M1SlprEv82hhtl2KSfCFOs4*BzLYgAdUD',
        'x-saldiuser: api',
        'x-db: develop_8'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($response === false) {
        echo 'cURL Error: ' . curl_error($ch) . "\n";
    } else {
        echo "GET Order HTTP Status Code: $httpCode\n";
        echo "GET Order Response:\n";
        
        $responseData = json_decode($response, true);
        if ($responseData) {
            echo json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
        } else {
            echo $response . "\n";
        }
    }
    
    curl_close($ch);
}

// Test GET all orders
echo "\n=== Testing Get All Orders ===\n";
$ch = curl_init("https://dev.saldi.dk/pblm/restapi/endpoints/v1/debitor/orders/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: 4M1SlprEv82hhtl2KSfCFOs4*BzLYgAdUD',
    'x-saldiuser: api',
    'x-db: develop_8'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    echo 'cURL Error: ' . curl_error($ch) . "\n";
} else {
    echo "GET All Orders HTTP Status Code: $httpCode\n";
    echo "GET All Orders Response (first 3 orders):\n";
    
    $responseData = json_decode($response, true);
    if ($responseData && isset($responseData['data'])) {
        // Show only first 3 orders to avoid too much output
        $orders = array_slice($responseData['data'], 0, 3);
        echo json_encode(['success' => $responseData['success'], 'data' => $orders, 'total_count' => count($responseData['data'])], JSON_PRETTY_PRINT) . "\n";
    } else {
        echo $response . "\n";
    }
}

curl_close($ch);