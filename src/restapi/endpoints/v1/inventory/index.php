<?php

// Inventory Management API Endpoints
// This directory contains endpoints for inventory management

$endpoints = [
    'warehouses' => [
        'path' => '/inventory/warehouses/',
        'description' => 'Manage warehouses (storage locations)',
        'methods' => ['GET', 'POST', 'PUT', 'DELETE']
    ],
    'groups' => [
        'path' => '/inventory/groups/',
        'description' => 'Manage product groups and categories',
        'methods' => ['GET', 'POST', 'PUT', 'DELETE']
    ],
    'status' => [
        'path' => '/inventory/status/',
        'description' => 'Manage inventory levels and stock status',
        'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']
    ]
];

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Inventory Management API',
    'version' => '1.0',
    'endpoints' => $endpoints,
    'documentation' => 'See README.md for detailed API documentation'
]);
