<?php

// setup the json
$data = [
	'firmanavn' => 'Test Company',
	'kontakt' => 'John Doe',
	'email' => 'john@test.com',
	'kontonr' => '12345',
	'cvrnr' => 'DK12345678',
	'ordredate' => '2025-06-10',
	'levdate' => '2025-06-15',
	'momssats' => 25,
	'sum' => 349.00,
	'valuta' => 'DKK',
	'status' => 1,
	'notes' => 'Test order via API',
	'adresse' => [
		'firmanavn' => "Moe Lester",
		'addr1' => 'Test Street 123',
		'postnr' => '1000',
		'bynavn' => 'Copenhagen',
		'land' => 'Denmark'
	],
	'orderLines' => [
		[
			'vare_id' => 10,
			'antal' => 2,
			'pris' => 62.00,
			'beskrivelse' => 'Radaflex Twin 2x10 mm rød/sort',
			'enhed' => 'stk'
		],
		[
			'vare_id' => 12,
			'antal' => 1,
			'pris' => 250.00,
			'rabat' => 10,
			'rabatart' => 'procent'
		]
	]
];

$dataJson = json_encode($data, JSON_PRETTY_PRINT);

$ch = curl_init("https://dev.saldi.dk/pblm/restapi/endpoints/v1/orders/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
/* 
Content-Type: application/json
Authorization: your-api-key
x-saldiuser: username
x-db: database_name
*/
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $dataJson);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
	'Content-Type: application/json',
	'Authorization: 4M1SlprEv82hhtl2KSfCFOs4*BzLYgAdUD',
	'x-saldiuser: api',
	'x-db: develop_8'
]);

$response = curl_exec($ch);
if ($response === false) {
	echo 'Curl error: ' . curl_error($ch);
} else {
	echo 'Response: ' . $response;
}
curl_close($ch);

print_r($dataJson); // Output the JSON data for debugging
