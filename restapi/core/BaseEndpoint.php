<?php

include_once __DIR__ . "/../../includes/db_query.php";
include_once __DIR__ . "/../../includes/connect.php";
include_once __DIR__ . "/../../includes/std_func.php";
include_once __DIR__ . "/auth.php";


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
        // Check authorization
        if (!$this->checkAuthorization()) {
            http_response_code(401);
            #echo json_encode(array("message" => "Unauthorized"));
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'];

        try {
            switch ($method) {
                case 'POST':
                    $data = json_decode(file_get_contents("php://input"));
                    $this->handlePost($data);
                    break;
                case 'GET':
                    $id = $_GET['id'] ?? null;
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

        return access_check($db, $user, $authorization) === 'OK';
    }

    protected function sendResponse($success, $data = null, $message = null, $code = 200)
    {
        http_response_code($code);

        $response = [
            'success' => $success
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        echo json_encode($response);
    }

    protected function handleError($exception = null)
    {
        $errorMessage = $exception ? $exception->getMessage() : "Internal Server Error";

        // Log the error (you'd typically use a proper logging mechanism)
        error_log($errorMessage);

        $this->sendResponse(false, null, $errorMessage, 500);
    }

    // Utility method for data validation
    protected function validateData($data, $requiredFields = [])
    {
        if (!$data) {
            throw new Exception("No data provided");
        }

        foreach ($requiredFields as $field) {
            if (!isset($data->$field)) {
                throw new Exception("Missing required field: $field");
            }
        }
    }
}
