<?php

include_once __DIR__ . "/../../includes/db_query.php";
include_once __DIR__ . "/../../includes/connect.php";
include_once __DIR__ . "/../../includes/std_func.php";
include_once __DIR__ . "/auth.php";
include_once __DIR__ . "/logging.php";
include_once __DIR__ . "/cors.php";
require_once __DIR__ . '/ApiException.php';


abstract class BaseEndpoint
{
    protected $conn;
    protected $model;

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

    public function handleRequestMethod()
    {
        // Start output buffering to catch any errors/warnings
        ob_start();
        
        // Check authorization
        if (!$this->checkAuthorization()) {
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
        
        ob_end_clean(); // Clear any buffered output before processing request

        $method = $_SERVER['REQUEST_METHOD'];

        try {
            switch ($method) {
                case 'POST':
                    $data = json_decode(file_get_contents("php://input"));
                    $this->handlePost($data);
                    break;
                case 'GET':
                    $id = $_GET['id'] ?? $_GET["currencyCode"] ?? null;
                    $this->handleGet($id);
                    break;
                case 'PUT':
                    $data = json_decode(file_get_contents("php://input"));
                    $this->handlePut($data);
                    break;
                case 'DELETE':
                    $data = json_decode(file_get_contents("php://input"));
                    $this->handleDelete($data);
                    break;
                case 'PATCH':
                    $data = json_decode(file_get_contents("php://input"));
                    $this->handlePatch($data);
                    break;
                default:
                    $this->handleError(new Exception("Method Not Allowed"));
                    break;
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }
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

        // Try JWT authentication first (for new mobile app endpoints)
        if (isset($headers['authorization']) && preg_match('/Bearer\s+/i', $headers['authorization'])) {
            // JWT token authentication
            require_once __DIR__ . '/JWT.php';
            require_once __DIR__ . '/JWTAuth.php';
            
            $payload = JWTAuth::validateToken();
            if ($payload) {
                // JWT authentication successful
                // Store user info for later use
                $this->userId = $payload['user_id'];
                $this->username = $payload['username'];
                
                // Get tenant database if X-Tenant-ID is provided
                if (isset($headers['x-tenant-id'])) {
                    $this->tenantDb = JWTAuth::getTenantDatabase();
                }
                
                return true;
            }
        }

        // Fall back to legacy API key authentication (for backward compatibility)
        // Check for required headers
        $requiredHeaders = ['authorization', 'x-saldiuser', 'x-db'];
        foreach ($requiredHeaders as $header) {
            if (!isset($headers[$header])) {
                $this->sendResponse(false, array(), "Missing required header: '{$header}'", 401);
                return false;
            }
        }

        // Extract header values
        $authorization = $headers['authorization'];
        $user = $headers['x-saldiuser'];
        $db = $headers['x-db'];

        // Validate Authorization header
        if (empty($authorization)) {
            $this->sendResponse(false, array(), "Authorization header cannot be empty", 401);
            return false;
        }

        // Additional optional validations
        if (empty($user)) {
            $this->sendResponse(false, array(), "User identifier cannot be empty", 401);
            return false;
        }

        if (empty($db)) {
            $this->sendResponse(false, array(), "Database identifier cannot be empty", 401);
            return false;
        }

        // Log authorization attempt
        write_log("Authorization attempt for user: $user", $db, 'INFO');

        $result = access_check($db, $user, $authorization) === 'OK';
        
        if (!$result) {
            write_log("Authorization failed for user: $user", $db, 'WARNING');
        }
        
        return $result;
    }

    protected function sendResponse($success, $data = null, $message = '', $httpCode = 200)
    {
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
        $errorMessage = $exception ? $exception->getMessage() : "Internal Server Error";
        $statusCode = 500;
        
        if ($exception instanceof ApiException) {
            $statusCode = $exception->getStatusCode();
        }
        
        // Log the error
        error_log($errorMessage);
        
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
