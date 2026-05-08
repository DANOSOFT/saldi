<?php
/**
 * Auth endpoints router
 * Routes to login.php or refresh.php based on request
 */

$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));

// Find the endpoint name (after /auth/)
$endpoint_index = array_search('auth', $path_parts);
if ($endpoint_index !== false && isset($path_parts[$endpoint_index + 1])) {
    $endpoint = $path_parts[$endpoint_index + 1];
    
    if ($endpoint === 'login') {
        require_once __DIR__ . '/login.php';
    } elseif ($endpoint === 'refresh') {
        require_once __DIR__ . '/refresh.php';
    } else {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Endpoint not found',
            'data' => null
        ]);
    }
} else {
    // Default to login if no specific endpoint
    require_once __DIR__ . '/login.php';
}

