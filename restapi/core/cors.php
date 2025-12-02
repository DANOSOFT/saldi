<?php
/**
 * CORS Configuration
 * Handles Cross-Origin Resource Sharing headers
 */

function setCorsHeaders()
{
    // Get origin from request
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
    
    // List of allowed origins (add your development domains here)
    $allowedOrigins = [
        'https://ssl12.saldi.dk',
        'https://saldi.dk',
        'http://localhost:3000',
        'http://localhost:8080',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:8080'
    ];
    
    // Allow all origins in development, restrict in production
    if (in_array($origin, $allowedOrigins) || strpos($origin, 'localhost') !== false || strpos($origin, '127.0.0.1') !== false) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        // In production, you might want to restrict this
        header("Access-Control-Allow-Origin: *");
    }
    
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Tenant-ID, X-DB, X-SaldiUser, X-Requested-With");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 3600");
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

// Set CORS headers for all requests
setCorsHeaders();

