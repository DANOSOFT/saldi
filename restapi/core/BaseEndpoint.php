<?php

include_once __DIR__ . "/../../includes/db_query.php";
include_once __DIR__ . "/../../includes/connect.php";
include_once __DIR__ . "/../../includes/std_func.php";
include_once __DIR__ . "/auth.php";
include_once __DIR__ . "/logging.php";
include_once __DIR__ . "/cors.php";
require_once __DIR__ . '/ApiException.php';
require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/JWTAuth.php';


abstract class BaseEndpoint
{
    protected $conn;
    protected $model;
    protected $db;
    protected $userId;
    protected $username;

    public function __construct()
    {
        #$this->conn = $db;
    }

    // Abstract methods that child classes must implement
    // Default implementation with a no-op or default response
    protected function handlePost($data)
    {
        throw new \BadMethodCallException('POST method not implemented for this endpoint');
    }

    // Default implementation with a no-op or default response
    protected function handleGet($id = null)
    {
        throw new \BadMethodCallException('GET method not implemented for this endpoint');
    }

    // Default implementation with a no-op or default response
    protected function handlePut($data)
    {
        throw new \BadMethodCallException('PUT method not implemented for this endpoint');
    }

    // Default implementation with a no-op or default response
    protected function handleDelete($data)
    {
        throw new \BadMethodCallException('DELETE method not implemented for this endpoint');
    }

    // Default implementation with a no-op or default response
    protected function handlePatch($data)
    {
        throw new \BadMethodCallException('PATCH method not implemented for this endpoint');
    }

    protected function getLogDb()
    {
        // Use the db property if already set from JWT auth
        if ($this->db) {
            return $this->db;
        }
        
        // Try JWT tenant database
        try {
            $tenantDb = JWTAuth::getTenantDatabase();
            if ($tenantDb) {
                return $tenantDb;
            }
        } catch (Exception $e) {
            // JWT validation failed, continue to fallback
        } catch (Error $e) {
            // PHP error, continue to fallback
        }
        
        // Fall back to X-Tenant-ID header
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        return $headers['x-tenant-id'] ?? 'api';
    }

    public function handleRequestMethod()
    {
        // Start output buffering to catch any errors/warnings
        ob_start();
        
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $endpoint = get_class($this);
        $logDb = $this->getLogDb();
        
        // Log incoming request
        write_log("=== INCOMING REQUEST ===", $logDb, 'INFO');
        write_log("Endpoint: $endpoint", $logDb, 'INFO');
        write_log("Method: $method", $logDb, 'INFO');
        write_log("URI: $uri", $logDb, 'INFO');
        write_log("Client IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), $logDb, 'INFO');
        
        // Log headers (sanitized - exclude sensitive data)
        $headers = getallheaders();
        $safeHeaders = [];
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, ['authorization', 'x-api-key', 'cookie'])) {
                $safeHeaders[$key] = '[REDACTED]';
            } else {
                $safeHeaders[$key] = $value;
            }
        }
        write_log("Headers: " . json_encode($safeHeaders), $logDb, 'DEBUG');
        
        // Log query parameters for GET requests
        if ($method === 'GET' && !empty($_GET)) {
            write_log("Query params: " . json_encode($_GET), $logDb, 'DEBUG');
        }
        
        // Check authorization
        if (!$this->checkAuthorization()) {
            write_log("Authorization failed for request to $endpoint", $logDb, 'WARNING');
            // If checkAuthorization didn't already send a response (shouldn't happen if it calls sendResponse)
            // Send a default unauthorized response
            ob_end_clean(); // Clear any buffered output
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized',
                'data' => null
            ]);
            exit;
        }
        
        write_log("Authorization successful", $logDb, 'INFO');
        ob_end_clean(); // Clear any buffered output before processing request

        try {
            switch ($method) {
                case 'POST':
                    $rawInput = file_get_contents("php://input");
                    $data = json_decode($rawInput);
                    write_log("POST data received: " . $this->sanitizeLogData($rawInput), $logDb, 'DEBUG');
                    write_log("Calling handlePost()", $logDb, 'INFO');
                    $this->handlePost($data);
                    break;
                case 'GET':
                    $id = $_GET['id'] ?? $_GET["currencyCode"] ?? null;
                    write_log("GET request with id: " . ($id ?? 'null'), $logDb, 'INFO');
                    write_log("Calling handleGet()", $logDb, 'INFO');
                    $this->handleGet($id);
                    break;
                case 'PUT':
                    $rawInput = file_get_contents("php://input");
                    $data = json_decode($rawInput);
                    write_log("PUT data received: " . $this->sanitizeLogData($rawInput), $logDb, 'DEBUG');
                    write_log("Calling handlePut()", $logDb, 'INFO');
                    $this->handlePut($data);
                    break;
                case 'DELETE':
                    $rawInput = file_get_contents("php://input");
                    $data = json_decode($rawInput);
                    write_log("DELETE data received: " . $this->sanitizeLogData($rawInput), $logDb, 'DEBUG');
                    write_log("Calling handleDelete()", $logDb, 'INFO');
                    $this->handleDelete($data);
                    break;
                case 'PATCH':
                    $rawInput = file_get_contents("php://input");
                    $data = json_decode($rawInput);
                    write_log("PATCH data received: " . $this->sanitizeLogData($rawInput), $logDb, 'DEBUG');
                    write_log("Calling handlePatch()", $logDb, 'INFO');
                    $this->handlePatch($data);
                    break;
                default:
                    write_log("Method not allowed: $method", $logDb, 'WARNING');
                    $this->handleError(new Exception("Method Not Allowed"));
                    break;
            }
        } catch (Exception $e) {
            write_log("Exception caught: " . $e->getMessage(), $logDb, 'ERROR');
            write_log("Stack trace: " . $e->getTraceAsString(), $logDb, 'ERROR');
            $this->handleError($e);
        }
    }

    protected function sanitizeLogData($data)
    {
        // Limit data length for logging
        $maxLength = 2000;
        if (strlen($data) > $maxLength) {
            return substr($data, 0, $maxLength) . '... [TRUNCATED]';
        }
        // Redact sensitive fields from JSON
        $decoded = json_decode($data, true);
        if (is_array($decoded)) {
            $sensitiveFields = ['password', 'token', 'secret', 'api_key', 'apikey'];
            array_walk_recursive($decoded, function(&$value, $key) use ($sensitiveFields) {
                if (in_array(strtolower($key), $sensitiveFields)) {
                    $value = '[REDACTED]';
                }
            });
            return json_encode($decoded);
        }
        return $data;
    }

    protected function checkAuthorization()
    {
        // Retrieve all headers
        $headers = getallheaders();

        // headers to lowercase for case-insensitive comparison
        $headers = array_change_key_case($headers, CASE_LOWER);
        
        // Check if headers are present
        if (empty($headers)) {
            $this->sendResponse(false, array(), "No headers received", 400);
            return false;
        }

        // JWT authentication (primary method)
        $payload = JWTAuth::validateToken();
        if ($payload) {
            // JWT authentication successful
            $this->userId = $payload['user_id'];
            $this->username = $payload['username'];
            
            // Get tenant database from JWT token or X-Tenant-ID header
            $this->db = JWTAuth::getTenantDatabase();
            if (!$this->db) {
                $this->sendResponse(false, null, 'Tenant database not found. Set X-Tenant-ID header.', 400);
                return false;
            }
            
            // Connect to tenant database (same as legacy access_check did)
            global $sqhost, $squser, $sqpass;
            $conn = db_connect($sqhost, $squser, $sqpass, $this->db, __FILE__ . " linje " . __LINE__);
            if (!$conn) {
                $this->sendResponse(false, null, 'Database connection failed', 500);
                return false;
            }
            
            write_log("JWT auth successful for user: {$this->username}, db: {$this->db}", $this->db, 'INFO');
            return true;
        }

        // No valid authentication provided
        $this->sendResponse(false, null, 'Valid Bearer token required. Login via POST /auth/login with username, password and account_name.', 401);
        return false;
    }

    protected function sendResponse($success, $data = null, $message = '', $httpCode = 200)
    {
        $logDb = $this->getLogDb();
        $endpoint = get_class($this);
        
        // Log outgoing response
        write_log("=== OUTGOING RESPONSE ===", $logDb, 'INFO');
        write_log("Endpoint: $endpoint", $logDb, 'INFO');
        write_log("HTTP Code: $httpCode", $logDb, 'INFO');
        write_log("Success: " . ($success ? 'true' : 'false'), $logDb, 'INFO');
        write_log("Message: $message", $logDb, 'INFO');
        
        // Log response data (truncated for large payloads)
        $dataJson = json_encode($data);
        if (strlen($dataJson) > 2000) {
            write_log("Response data: " . substr($dataJson, 0, 2000) . "... [TRUNCATED - " . strlen($dataJson) . " bytes total]", $logDb, 'DEBUG');
        } else {
            write_log("Response data: $dataJson", $logDb, 'DEBUG');
        }
        write_log("=== END REQUEST ===", $logDb, 'INFO');
        
        // Clear any output buffers to ensure clean JSON response
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        http_response_code($httpCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => $success,
            'message' => $message,
            'data' => $data
        ];
        
        // Pretty print JSON for better readability
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function handleError($exception = null)
    {
        $logDb = $this->getLogDb();
        $endpoint = get_class($this);
        $errorMessage = $exception ? $exception->getMessage() : "Internal Server Error";
        $statusCode = 500;
        
        if ($exception instanceof ApiException) {
            $statusCode = $exception->getStatusCode();
        }
        
        // Enhanced error logging
        write_log("=== ERROR OCCURRED ===", $logDb, 'ERROR');
        write_log("Endpoint: $endpoint", $logDb, 'ERROR');
        write_log("Error message: $errorMessage", $logDb, 'ERROR');
        write_log("Status code: $statusCode", $logDb, 'ERROR');
        
        if ($exception) {
            write_log("Exception class: " . get_class($exception), $logDb, 'ERROR');
            write_log("File: " . $exception->getFile() . ":" . $exception->getLine(), $logDb, 'ERROR');
            write_log("Stack trace: " . $exception->getTraceAsString(), $logDb, 'ERROR');
        }
        
        // Also log to PHP error log
        error_log("[$endpoint] $errorMessage");
        
        $this->sendResponse(false, null, $errorMessage, $statusCode);
    }

    // Utility method for data validation
    protected function validateData($data, $requiredFields)
    {
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data->$field)) {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            throw new ApiException("Missing required fields: " . implode(', ', $missingFields), 400);
        }
    }
}
