<?php
/**
 * API Router for v1 endpoints
 * Routes requests to appropriate endpoint files
 */

// Set CORS headers
require_once __DIR__ . '/../../core/cors.php';

$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove base path
$base_path = '/pblm/restapi/endpoints/v1';
if (strpos($path, $base_path) === 0) {
    $path = substr($path, strlen($base_path));
}
$path = trim($path, '/');

$path_parts = explode('/', $path);

// Route to appropriate endpoint
if (empty($path) || $path === 'index.php') {
    // API info endpoint
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'SALDI REST API v1',
        'version' => '1.0.0',
        'endpoints' => [
            'auth' => '/auth/login, /auth/refresh',
            'user' => '/user/tenants',
            'vouchers' => '/vouchers',
            'invoices' => '/invoices',
            'customers' => '/customers',
            'dashboard' => '/dashboard/stats',
            'notifications' => '/notifications/register',
            'vat-codes' => '/vat-codes'
        ]
    ]);
    exit;
}

// Route auth endpoints
if ($path_parts[0] === 'auth') {
    if (isset($path_parts[1]) && $path_parts[1] === 'login') {
        require_once __DIR__ . '/auth/login.php';
        exit;
    } elseif (isset($path_parts[1]) && $path_parts[1] === 'refresh') {
        require_once __DIR__ . '/auth/refresh.php';
        exit;
    }
}

// Route user endpoints
if ($path_parts[0] === 'user' && isset($path_parts[1]) && $path_parts[1] === 'tenants') {
    require_once __DIR__ . '/user/tenants.php';
    exit;
}

// Route vouchers endpoints
if ($path_parts[0] === 'vouchers') {
    require_once __DIR__ . '/vouchers/index.php';
    exit;
}

// Route invoices endpoints
if ($path_parts[0] === 'invoices') {
    require_once __DIR__ . '/invoices/index.php';
    exit;
}

// Route vat-codes endpoints
if ($path_parts[0] === 'vat-codes') {
    require_once __DIR__ . '/vat-codes/index.php';
    exit;
}

// Route dashboard endpoints
if ($path_parts[0] === 'dashboard' && isset($path_parts[1]) && $path_parts[1] === 'stats') {
    require_once __DIR__ . '/dashboard/stats.php';
    exit;
}

// Route customers endpoints
if ($path_parts[0] === 'customers') {
    require_once __DIR__ . '/customers/index.php';
    exit;
}

// Route notifications endpoints
if ($path_parts[0] === 'notifications' && isset($path_parts[1]) && $path_parts[1] === 'register') {
    require_once __DIR__ . '/notifications/register.php';
    exit;
}

// 404 - Endpoint not found
http_response_code(404);
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'message' => 'Endpoint not found',
    'data' => null
]);

