<?php
require_once('../../includes/connect.php');
require_once('../../includes/db_query.php');

// Set headers
header('Content-Type: application/json');

// Helper function to generate a secure API key
function generateApiKey() {
    return bin2hex(random_bytes(32)); // Generates a 64-character hex string
}

// Function to create a new API key
function createApiKey($database, $description = '', $createdBy = 'system') {
    // First check if the database exists
    if (!db_exists($database)) {
        return [
            'error' => true,
            'message' => "Database '$database' does not exist"
        ];
    }

    // Generate new API key
    $apiKey = generateApiKey();
    
    // Insert into api_keys table
    $query = "INSERT INTO api_keys (api_key, database, description, created_by) VALUES (
        '" . db_escape_string($apiKey) . "',
        '" . db_escape_string($database) . "',
        '" . db_escape_string($description) . "',
        '" . db_escape_string($createdBy) . "'
    )";
    
    if (db_modify($query, __FILE__ . " linje " . __LINE__)) {
        return [
            'error' => false,
            'message' => 'API key created successfully',
            'api_key' => $apiKey
        ];
    } else {
        return [
            'error' => true,
            'message' => 'Failed to create API key'
        ];
    }
}

// Function to list all API keys
function listApiKeys() {
    $query = "SELECT id, api_key, database, description, active, created_at, last_used_at, created_by 
              FROM api_keys 
              ORDER BY created_at DESC";
    
    $result = db_select($query, __FILE__ . " linje " . __LINE__);
    $keys = [];
    
    while ($row = db_fetch_array($result)) {
        // Mask the API key for security
        $row['api_key'] = substr($row['api_key'], 0, 8) . '...' . substr($row['api_key'], -8);
        $keys[] = $row;
    }
    
    return [
        'error' => false,
        'keys' => $keys
    ];
}

// Function to deactivate an API key
function deactivateApiKey($apiKey) {
    $query = "UPDATE api_keys SET active = false WHERE api_key = '" . db_escape_string($apiKey) . "'";
    
    if (db_modify($query, __FILE__ . " linje " . __LINE__)) {
        return [
            'error' => false,
            'message' => 'API key deactivated successfully'
        ];
    } else {
        return [
            'error' => true,
            'message' => 'Failed to deactivate API key'
        ];
    }
}

// Handle different actions based on request method and parameters
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Initialize response array
$response = [
    'error' => true,
    'message' => 'Invalid request'
];

switch ($method) {
    case 'POST':
        if ($action === 'create') {
            $database = $_POST['database'] ?? '';
            $description = $_POST['description'] ?? '';
            $createdBy = $_POST['created_by'] ?? 'system';
            
            if (empty($database)) {
                $response = [
                    'error' => true,
                    'message' => 'Database name is required'
                ];
            } else {
                $response = createApiKey($database, $description, $createdBy);
            }
        }
        break;
        
    case 'GET':
        if ($action === 'list') {
            $response = listApiKeys();
        }
        break;
        
    case 'PUT':
        if ($action === 'deactivate') {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if ($data === null) {
                $response = [
                    'error' => true,
                    'message' => 'Invalid JSON input'
                ];
            } else {
                $apiKey = $data['api_key'] ?? '';
                
                if (empty($apiKey)) {
                    $response = [
                        'error' => true,
                        'message' => 'API key is required'
                    ];
                } else {
                    $response = deactivateApiKey($apiKey);
                }
            }
        }
        break;
}

// Ensure we always output valid JSON
header('Content-Type: application/json');
echo json_encode($response);
exit; 