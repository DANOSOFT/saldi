<?php
require_once('../../includes/connect.php');
require_once('../../includes/db_query.php');
require_once('includes/AuthMiddleware.php');

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

// Authenticate request
$auth = new AuthMiddleware();
if (!$auth->authenticate()) {
    exit;
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Handle different HTTP methods
switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost();
        break;
    case 'PUT':
        handlePut();
        break;
    case 'DELETE':
        handleDelete();
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => true, 'message' => 'Method not allowed']);
        break;
}

function handleGet() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if ($id) {
        // Get single address
        $query = "SELECT * FROM adresser WHERE id = " . $id;
        $result = db_select($query, __FILE__ . " linje " . __LINE__);
        $address = db_fetch_array($result);
        
        if ($address) {
            echo json_encode($address);
        } else {
            http_response_code(404);
            echo json_encode(['error' => true, 'message' => 'Address not found']);
        }
    } else {
        // Get all addresses
        $query = "SELECT * FROM adresser ORDER BY id";
        $result = db_select($query, __FILE__ . " linje " . __LINE__);
        
        $addresses = [];
        while ($row = db_fetch_array($result)) {
            $addresses[] = $row;
        }
        
        echo json_encode($addresses);
    }
}

function handlePost() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => 'Invalid request data']);
        return;
    }
    
    // Build insert query
    $fields = [];
    $values = [];
    
    foreach ($data as $field => $value) {
        if ($field !== 'id') { // Skip id as it's auto-generated
            $fields[] = $field;
            $values[] = "'" . db_escape_string($value) . "'";
        }
    }
    
    $query = "INSERT INTO adresser (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
    
    if (db_modify($query, __FILE__ . " linje " . __LINE__)) {
        $id = db_fetch_array(db_select("SELECT currval('adresser_id_seq') as id", __FILE__ . " linje " . __LINE__))['id'];
        http_response_code(201);
        echo json_encode(['id' => $id, 'message' => 'Address created successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => true, 'message' => 'Failed to create address']);
    }
}

function handlePut() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => 'ID is required']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => 'Invalid request data']);
        return;
    }
    
    // Build update query
    $updates = [];
    foreach ($data as $field => $value) {
        if ($field !== 'id') { // Skip id as it shouldn't be updated
            $updates[] = $field . " = '" . db_escape_string($value) . "'";
        }
    }
    
    $query = "UPDATE adresser SET " . implode(', ', $updates) . " WHERE id = " . $id;
    
    if (db_modify($query, __FILE__ . " linje " . __LINE__)) {
        echo json_encode(['message' => 'Address updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => true, 'message' => 'Failed to update address']);
    }
}

function handleDelete() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => 'ID is required']);
        return;
    }
    
    $query = "DELETE FROM adresser WHERE id = " . $id;
    
    if (db_modify($query, __FILE__ . " linje " . __LINE__)) {
        echo json_encode(['message' => 'Address deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => true, 'message' => 'Failed to delete address']);
    }
} 