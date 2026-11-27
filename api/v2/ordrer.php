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
    $include_lines = isset($_GET['include_lines']) ? $_GET['include_lines'] === 'true' : false;
    $konto_id = isset($_GET['konto_id']) ? (int)$_GET['konto_id'] : null;
    $status = isset($_GET['status']) ? (int)$_GET['status'] : null;
    
    if ($id) {
        // Get single order
        $query = "SELECT * FROM ordrer WHERE id = " . $id;
        $result = db_select($query, __FILE__ . " linje " . __LINE__);
        $order = db_fetch_array($result);
        
        if ($order) {
            // Convert numeric fields and handle nulls properly
            $order = formatOrderData($order);
            
            if ($include_lines) {
                // Get order lines with variant information
                $lines_query = "SELECT ol.*, v.varenr as vare_varenr, v.beskrivelse as vare_beskrivelse, 
                               vv.variant_stregkode, vv.variant_beskrivelse, vv.variant_pris 
                               FROM ordrelinjer ol 
                               LEFT JOIN varer v ON ol.vare_id = v.id 
                               LEFT JOIN variant_varer vv ON ol.variant_id = vv.id::text
                               WHERE ol.ordre_id = " . $id . " ORDER BY ol.posnr";
                $lines_result = db_select($lines_query, __FILE__ . " linje " . __LINE__);
                
                $order_lines = [];
                while ($line = db_fetch_array($lines_result)) {
                    $order_lines[] = formatOrderLineData($line);
                }
                $order['order_lines'] = $order_lines;
            }
            
            echo json_encode($order);
        } else {
            http_response_code(404);
            echo json_encode(['error' => true, 'message' => 'Order not found']);
        }
    } else {
        // Build query with filters
        $where_conditions = [];
        $params = [];
        
        if ($konto_id) {
            $where_conditions[] = "konto_id = " . $konto_id;
        }
        
        if ($status !== null) {
            $where_conditions[] = "status = " . $status;
        }
        
        $query = "SELECT * FROM ordrer";
        if (!empty($where_conditions)) {
            $query .= " WHERE " . implode(' AND ', $where_conditions);
        }
        $query .= " ORDER BY id DESC";
        
        // Add limit for performance
        if (!isset($_GET['limit'])) {
            $query .= " LIMIT 100";
        } else {
            $limit = (int)$_GET['limit'];
            if ($limit > 0 && $limit <= 1000) {
                $query .= " LIMIT " . $limit;
            }
        }
        
        $result = db_select($query, __FILE__ . " linje " . __LINE__);
        
        $orders = [];
        while ($row = db_fetch_array($result)) {
            $orders[] = formatOrderData($row);
        }
        
        echo json_encode($orders);
    }
}

function handlePost() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => 'Invalid request data']);
        return;
    }
    
    // Validate required fields
    $required_fields = ['konto_id', 'art'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => "Missing required field: $field"]);
            return;
        }
    }
    
    // Set default values for important fields
    if (!isset($data['status'])) $data['status'] = 0;
    if (!isset($data['ordredate'])) $data['ordredate'] = date('Y-m-d');
    if (!isset($data['valuta'])) $data['valuta'] = 'DKK';
    if (!isset($data['valutakurs'])) $data['valutakurs'] = 100;
    if (!isset($data['momssats'])) $data['momssats'] = 25;
    
    // Generate next order number
    if (!isset($data['ordrenr']) || !$data['ordrenr']) {
        $nr_result = db_select("SELECT COALESCE(MAX(ordrenr), 0) + 1 as next_nr FROM ordrer WHERE art = '" . db_escape_string($data['art']) . "'", __FILE__ . " linje " . __LINE__);
        $nr_row = db_fetch_array($nr_result);
        $data['ordrenr'] = $nr_row['next_nr'];
    }
    
    // Build insert query with proper null handling
    $fields = [];
    $values = [];
    
    foreach ($data as $field => $value) {
        if ($field !== 'id') { // Skip id as it's auto-generated
            $fields[] = $field;
            if ($value === null || $value === '') {
                // Handle specific fields that should be NULL vs empty string
                if (in_array($field, ['konto_id', 'ordrenr', 'betalingsdage', 'momssats', 'status', 'valutakurs', 'sum', 'moms', 'afd', 'lager', 'shop_id'])) {
                    $values[] = 'NULL';
                } else {
                    $values[] = "''";
                }
            } else {
                $values[] = "'" . db_escape_string($value) . "'";
            }
        }
    }
    
    $query = "INSERT INTO ordrer (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
    
    if (db_modify($query, __FILE__ . " linje " . __LINE__)) {
        $id = db_fetch_array(db_select("SELECT currval('ordrer_id_seq') as id", __FILE__ . " linje " . __LINE__))['id'];
        http_response_code(201);
        echo json_encode(['id' => $id, 'ordrenr' => $data['ordrenr'], 'message' => 'Order created successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => true, 'message' => 'Failed to create order']);
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
    
    // Build update query with proper null handling
    $updates = [];
    foreach ($data as $field => $value) {
        if ($field !== 'id') { // Skip id as it shouldn't be updated
            if ($value === null || $value === '') {
                // Handle specific fields that should be NULL vs empty string
                if (in_array($field, ['konto_id', 'ordrenr', 'betalingsdage', 'momssats', 'status', 'valutakurs', 'sum', 'moms', 'afd', 'lager', 'shop_id'])) {
                    $updates[] = $field . " = NULL";
                } else {
                    $updates[] = $field . " = ''";
                }
            } else {
                $updates[] = $field . " = '" . db_escape_string($value) . "'";
            }
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => true, 'message' => 'No fields to update']);
        return;
    }
    
    $query = "UPDATE ordrer SET " . implode(', ', $updates) . " WHERE id = " . $id;
    
    if (db_modify($query, __FILE__ . " linje " . __LINE__)) {
        echo json_encode(['message' => 'Order updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => true, 'message' => 'Failed to update order']);
    }
}

function formatOrderData($order) {
    // Convert numeric fields to proper types
    $numeric_fields = ['id', 'konto_id', 'ordrenr', 'betalingsdage', 'momssats', 'status', 'valutakurs', 'sum', 'moms', 'afd', 'lager', 'shop_id', 'sag_id', 'tilbudnr', 'sagsnr', 'procenttillag', 'report_number', 'scan_id', 'settletime'];
    
    foreach ($numeric_fields as $field) {
        if (isset($order[$field]) && $order[$field] !== null && $order[$field] !== '') {
            $order[$field] = is_numeric($order[$field]) ? (float)$order[$field] : $order[$field];
        }
    }
    
    // Convert boolean fields
    $boolean_fields = ['copied', 'gls_label', 'fedex_label'];
    foreach ($boolean_fields as $field) {
        if (isset($order[$field])) {
            $order[$field] = $order[$field] === 't' || $order[$field] === true || $order[$field] === 'true';
        }
    }
    
    return $order;
}

function formatOrderLineData($line) {
    // Convert numeric fields to proper types
    $numeric_fields = ['id', 'posnr', 'pris', 'vat_price', 'rabat', 'ordre_id', 'vare_id', 'antal', 'leveres', 'leveret', 'bogf_konto', 'kred_linje_id', 'momssats', 'vat_account', 'kostpris', 'm_rabat', 'rabatgruppe', 'folgevare', 'procent', 'saet', 'fast_db', 'afd', 'lager', 'rental_id'];
    
    foreach ($numeric_fields as $field) {
        if (isset($line[$field]) && $line[$field] !== null && $line[$field] !== '') {
            $line[$field] = is_numeric($line[$field]) ? (float)$line[$field] : $line[$field];
        }
    }
    
    return $line;
}
