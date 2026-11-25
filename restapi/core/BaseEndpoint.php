<?php
// 20261105 CL/LH An endpoint that names its module's permission key ($permissionKey) refuses a user without that module's right with 403 (checkModuleRight()).
//                The level is the user's role's when one is assigned (perm_user_levels()), as on the web, and the rights string's position must be open.
//                A level below the request's is refused once the enforcement mode is 'deny' (or on a dangerous key), as on the web.
//                The token was the only check, so a user with Debitorordre alone read the chart of accounts and the purchase orders.
//                checkAuthorization() selects the account database and sets global $db to it, as includes/online.php does on the web.
//                On MySQL/MariaDB tbl_exists() looked in no schema, so the role and the enforcement mode were never read and read-only could write under 'deny'.
// 20261125 CDX/ASR Check the authenticated account package before module writes; preserve reads and exports.

include_once __DIR__ . "/../../includes/db_query.php";
include_once __DIR__ . "/../../includes/connect.php";
include_once __DIR__ . "/../../includes/std_func.php";
include_once __DIR__ . "/auth.php";
include_once __DIR__ . "/logging.php";
include_once __DIR__ . "/cors.php";
require_once __DIR__ . '/ApiException.php';
require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/JWTAuth.php';
require_once __DIR__ . '/../../includes/entitlements.php';

// JWT signing secret (SD-587, self-provisioning added SD-634): install-specific,
// >=256 bits, stored outside the repo at restapi/.ht_jwt_secret.bin (git-ignored,
// matches the .ht* pattern already used for bank_integration/.ht_oauth_key.bin).
// One file per codebase install, shared by every tenant it serves - see
// "JWT signing secret is per install, not per tenant" in restapi/IMPLEMENTATION_STATUS.md.
// See JwtSecretProvisioning.php for how it's loaded/created.
require_once __DIR__ . '/JwtSecretProvisioning.php';
try {
    JWT::setSecret(_jwtLoadSecret());
} catch (\RuntimeException $e) {
    error_log('JWT bootstrap failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'REST API is not configured', 'data' => null]);
    exit;
}

abstract class BaseEndpoint
{
    protected $conn;
    protected $model;
    protected $db;
    protected $userId;
    protected $username;

    /**
     * Permission key (includes/permissionRegistry.php) of the Saldi module whose data the endpoint serves, the key that
     * module's pages declare; null when every user with a valid token may call it. See checkModuleRight().
     *
     * @var string|null
     */
    protected $permissionKey = null;

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
        
        // Try the account database identified by the JWT tenant_id claim
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

        ent_load_database((string) $this->db);
        if ($this->permissionKey !== null && !ent_permission_allowed($this->permissionKey, in_array($method, array('GET', 'HEAD'), true) ? 'read' : 'write')) {
            $this->sendResponse(false, array('code' => 'upgrade_required'), 'Opgradér: Funktionen er ikke med i jeres pakke.', 403);
            return;
        }
        if (!$this->checkModuleRight()) {
            write_log("Module right {$this->permissionKey} missing for user {$this->username}", $logDb, 'WARNING');
            $this->sendResponse(false, null, "This user has no access to this function in Saldi ({$this->permissionKey}). Contact your administrator.", 403);
        }
        ob_end_clean(); // Clear any buffered output before processing request

        try {
            switch ($method) {
                case 'POST':
                    $rawInput = file_get_contents("php://input");
                    $data = $this->decodeJsonBody($rawInput);
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
                    $data = $this->decodeJsonBody($rawInput);
                    write_log("PUT data received: " . $this->sanitizeLogData($rawInput), $logDb, 'DEBUG');
                    write_log("Calling handlePut()", $logDb, 'INFO');
                    $this->handlePut($data);
                    break;
                case 'DELETE':
                    $rawInput = file_get_contents("php://input");
                    $data = $this->decodeJsonBody($rawInput);
                    write_log("DELETE data received: " . $this->sanitizeLogData($rawInput), $logDb, 'DEBUG');
                    write_log("Calling handleDelete()", $logDb, 'INFO');
                    $this->handleDelete($data);
                    break;
                case 'PATCH':
                    $rawInput = file_get_contents("php://input");
                    $data = $this->decodeJsonBody($rawInput);
                    write_log("PATCH data received: " . $this->sanitizeLogData($rawInput), $logDb, 'DEBUG');
                    write_log("Calling handlePatch()", $logDb, 'INFO');
                    $this->handlePatch($data);
                    break;
                default:
                    write_log("Method not allowed: $method", $logDb, 'WARNING');
                    $this->handleError(new Exception("Method Not Allowed"));
                    break;
            }
        } catch (Throwable $e) { // 20260812 CL/LH: was Exception - an Error (e.g. undefined method) escaped the JSON error handling and died as an empty HTTP 500
            write_log("Exception caught: " . $e->getMessage(), $logDb, 'ERROR');
            write_log("Stack trace: " . $e->getTraceAsString(), $logDb, 'ERROR');
            $this->handleError($e);
        }
    }

    // 20260812 CL/LH: reject malformed JSON bodies with a clear 400 instead of letting a null
    // $data surface further down as misleading 'missing required field' errors (a truncated
    // body previously read as a business-validation problem).
    protected function decodeJsonBody($rawInput)
    {
        $data = json_decode($rawInput);
        if ($data === null && trim((string)$rawInput) !== '' && json_last_error() !== JSON_ERROR_NONE) {
            $this->sendResponse(false, null, 'Invalid JSON body: ' . json_last_error_msg(), 400);
            exit;
        }
        return $data;
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
            
            // Get the account database from the JWT or legacy X-Tenant-ID header
            $this->db = JWTAuth::getTenantDatabase();
            if (!$this->db) {
                $this->sendResponse(false, null, 'Account database not found. Login again or set the legacy X-Tenant-ID header to the numeric account ID.', 400);
                return false;
            }
            
            // Connect to the selected account database. db_connect() selects no database on MySQL/MariaDB;
            // db_select_database() does (on Postgres the connection is to that database already).
            global $sqhost, $squser, $sqpass, $db;
            $conn = db_connect($sqhost, $squser, $sqpass, $this->db, __FILE__ . " linje " . __LINE__);
            if (!$conn || !db_select_database($conn, $this->db)) {
                $this->sendResponse(false, null, 'Database connection failed', 500);
                return false;
            }
            // The includes read the account database from global $db, as includes/online.php sets it on the web:
            // tbl_exists() looks in that schema on MySQL/MariaDB, and checkModuleRight() asks it for the role tables
            // and the settings table.
            $db = $this->db;

            // 20261008 CL/LH: the models read the fiscal year from global $regnaar, which nothing
            // set on the JWT path - see api_current_fiscal_year() in auth.php.
            global $regnaar;
            $regnaar = api_current_fiscal_year();
            if (!$regnaar) {
                write_log("No fiscal year found (grupper art RA) in {$this->db}", $this->db, 'WARNING');
            }
            
            write_log("JWT auth successful for user: {$this->username}, db: {$this->db}", $this->db, 'INFO');
            return true;
        }

        // No valid authentication provided
        $this->sendResponse(false, null, 'Valid Bearer token required. Login via POST /auth/login.php with username, password and account_name.', 401);
        return false;
    }

    /**
     * True when the token's user may make this request: the endpoint names no module, or the user passes both gates
     * includes/online.php puts in front of that module's pages, for the user in brugere of the account database
     * checkAuthorization() connected to.
     * 1. The module gate: the key's position in the rights string (brugere.rettigheder) must be open, read as
     *    perm_levels_from_legacy() reads it (a character from '1' up). A closed position is refused every method.
     * 2. The role gate (perm_enforce_request()): the user's effective level for the key, from perm_user_levels(), the
     *    assigned role's levels when the user has a role and the rights string's otherwise. A role is rendered into the
     *    rights string, but that string is not the role: a role with Kassesystem and no Debitorordre opens position 5 for
     *    the till, so the level has to come from the role. A GET needs read; any other method writes and needs write.
     *    A level below that is refused where perm_enforce_request() refuses it: once the company's enforcement mode
     *    (settings permissions/enforce) is 'deny', or always for a dangerous key. Until then the request is let through
     *    and logged, as the web lets that user in and logs it.
     * A user without a rights string (NULL, '' or '0'), or no longer in brugere, holds no right and is refused; online.php
     * refuses such a user only on the chart-of-accounts pages.
     *
     * @return bool
     */
    protected function checkModuleRight()
    {
        if ($this->permissionKey === null) {
            return true;
        }
        $key = $this->permissionKey;
        $userId = (int) $this->userId;
        $r = ($userId > 0) ? db_fetch_array(db_select("select rettigheder from brugere where id = $userId", __FILE__ . " linje " . __LINE__)) : false;
        if (!$r) {
            return false;
        }
        $rettigheder = trim((string) $r['rettigheder']);
        $position = perm_levels_from_legacy($rettigheder);
        if (perm_level_valid(isset($position[$key]) ? $position[$key] : 'none') === 'none') {
            return false;
        }
        $levels = perm_user_levels($userId, $rettigheder);
        $level = perm_level_valid(isset($levels[$key]) ? $levels[$key] : 'none');
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
        $need = in_array($method, array('GET', 'HEAD'), true) ? 'read' : 'write';
        $rank = perm_level_rank();
        if ($rank[$level] >= $rank[$need]) {
            return true;
        }
        $registry = permission_registry();
        if (perm_enforcement_mode() === 'deny' || !empty($registry[$key]['dangerous'])) {
            return false;
        }
        write_log("User {$this->username} holds $key at $level and $method needs $need; enforcement mode 'deny' would refuse it", $this->getLogDb(), 'WARNING');
        return true;
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

    // 20261019 CL/LH Added validateNumericFields() (MB-76).
    //                The models cast numbers with (float)/(int), so "1,50" was saved as 1 without an error.
    /**
     * Reject numeric request fields that are not a JSON number or a plain numeric string, with a
     * 400 ApiException in the same format as validateData(). A string must match ^-?\d+(\.\d+)?$
     * (dot as decimal separator, no thousands separator, no spaces); a whole-number field only
     * accepts ^-?\d+$ or a JSON number without a fractional part. Absent and null fields are
     * skipped, matching the isset() checks endpoints use before calling a setter.
     *
     * @param object|null $data          Decoded JSON request body.
     * @param string[]    $decimalFields Fields that take a decimal number (prices, quantities, sizes).
     * @param string[]    $integerFields Fields that take a whole number (ids, codes).
     * @return void
     * @throws ApiException 400 naming every field with an invalid value.
     */
    protected function validateNumericFields($data, $decimalFields, $integerFields = [])
    {
        $invalidFields = [];

        foreach (array_merge($decimalFields, $integerFields) as $field) {
            if (!isset($data->$field)) {
                continue;
            }
            $value = $data->$field;
            $wholeNumber = in_array($field, $integerFields, true);

            if (is_int($value)) {
                $valid = true;
            } elseif (is_float($value)) {
                $valid = is_finite($value) && (!$wholeNumber || floor($value) == $value);
            } elseif (is_string($value)) {
                $valid = preg_match($wholeNumber ? '/^-?\d+$/D' : '/^-?\d+(\.\d+)?$/D', $value) === 1;
            } else {
                $valid = false;
            }

            if (!$valid) {
                $invalidFields[] = $field . ($wholeNumber ? ' (whole number)' : '');
            }
        }

        if (!empty($invalidFields)) {
            throw new ApiException(
                "Invalid number format for fields: " . implode(', ', $invalidFields)
                . ". Send a JSON number or a string such as \"1250.50\" - dot as decimal separator, no thousands separator.",
                400
            );
        }
    }
}
