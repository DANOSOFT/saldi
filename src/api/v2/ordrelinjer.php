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
    default:
        http_response_code(405);
        echo json_encode(['error' => true, 'message' => 'Method not allowed']);
        break;
}

function handleGet() {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $ordre_id = isset($_GET['ordre_id']) ? (int)$_GET['ordre_id'] : null;
    
    if ($id) {
        // Get single order line
        $query = "SELECT * FROM ordrelinjer WHERE id = " . $id;
        $result = db_select($query, __FILE__ . " linje " . __LINE__);
        $orderLine = db_fetch_array($result);
        
        if ($orderLine) {
            echo json_encode($orderLine);
        } else {
            http_response_code(404);
            echo json_encode(['error' => true, 'message' => 'Order line not found']);
        }
    } elseif ($ordre_id) {
        // Get all order lines for a specific order
        $query = "SELECT * FROM ordrelinjer WHERE ordre_id = " . $ordre_id . " ORDER BY id";
        $result = db_select($query, __FILE__ . " linje " . __LINE__);
        
        $orderLines = [];
        while ($row = db_fetch_array($result)) {
            $orderLines[] = $row;
        }
        
        echo json_encode($orderLines);
    } else {
        // Get all order lines
        $query = "SELECT * FROM ordrelinjer ORDER BY id";
        $result = db_select($query, __FILE__ . " linje " . __LINE__);
        
        $orderLines = [];
        while ($row = db_fetch_array($result)) {
            $orderLines[] = $row;
        }
        
        echo json_encode($orderLines);
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
    
    $query = "INSERT INTO ordrelinjer (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
    
    if (db_modify($query, __FILE__ . " linje " . __LINE__)) {
        $id = db_fetch_array(db_select("SELECT currval('ordrelinjer_id_seq') as id", __FILE__ . " linje " . __LINE__))['id'];
        http_response_code(201);
        echo json_encode(['id' => $id, 'message' => 'Order line created successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => true, 'message' => 'Failed to create order line']);
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
    
    $query = "UPDATE ordrelinjer SET " . implode(', ', $updates) . " WHERE id = " . $id;
    
    if (db_modify($query, __FILE__ . " linje " . __LINE__)) {
        echo json_encode(['message' => 'Order line updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => true, 'message' => 'Failed to update order line']);
    }
}
