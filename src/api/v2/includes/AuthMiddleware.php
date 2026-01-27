<?php
require_once('ApiKeyManager.php');

class AuthMiddleware {
    private $apiKeyManager;

    public function __construct() {
        $this->apiKeyManager = new ApiKeyManager();
    }

    public function authenticate() {
        // Try different ways to get the API key
        $apiKey = null;
        
        // Try getallheaders() first
        $headers = getallheaders();
        if (isset($headers['X-API-Key'])) {
            $apiKey = $headers['X-API-Key'];
        } elseif (isset($headers['x-api-key'])) {
            $apiKey = $headers['x-api-key'];
        }
        
        // If not found, try $_SERVER
        if (!$apiKey) {
            $headerKey = 'HTTP_X_API_KEY';
            if (isset($_SERVER[$headerKey])) {
                $apiKey = $_SERVER[$headerKey];
            }
        }
        
        // Debug output
        error_log("Headers received: " . print_r($headers, true));
        error_log("API Key found: " . ($apiKey ? 'Yes' : 'No'));

        if (!$apiKey) {
            $this->sendErrorResponse('API key is required', 401);
            return false;
        }

        $apiKeyData = $this->apiKeyManager->validateApiKey($apiKey);
        if (!$apiKeyData) {
            $this->sendErrorResponse('Invalid API key', 401);
            return false;
        }

        // Connect to the user's database
        if (!$this->apiKeyManager->connectToUserDatabase($apiKeyData['database'])) {
            $this->sendErrorResponse('Failed to connect to database', 500);
            return false;
        }

        return true;
    }

    private function sendErrorResponse($message, $statusCode) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => true,
            'message' => $message
        ]);
        exit;
    }
} 