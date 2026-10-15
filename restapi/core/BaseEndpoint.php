<?php
// 20261001 CDX/LH Mobile schema bootstrap, subject, declared rights and allowlisted request correlation.
// 20261001 CDX/LH Lazy legacy identity, token-bound tenants and private server diagnostics.
// 20261001 CDX/LH Check the registry session once before every authenticated request.
// 20261001 CDX/LH Deny undeclared resource endpoints by default.
// 20261001 CDX/LH Add tenantless registry/read declarations for registry principals only.
// 20261001 CDX/LH Enforce current employee links and case Login/approval declarations.

include_once __DIR__ . "/../../includes/db_query.php";
include_once __DIR__ . "/../../includes/connect.php";
include_once __DIR__ . "/../../includes/std_func.php";
include_once __DIR__ . "/auth.php";
include_once __DIR__ . "/logging.php";
include_once __DIR__ . "/cors.php";
require_once __DIR__ . '/ApiException.php';
require_once __DIR__ . '/JWT.php';
require_once __DIR__ . '/JWTAuth.php';
require_once __DIR__ . '/Subject.php';
require_once __DIR__ . '/Rights.php';
require_once __DIR__ . '/RequestId.php';
require_once __DIR__ . '/../../includes/mobileRegistryUpdates.php';

JWT::setClaimEnricher(function (array $payload): array {
    if (isset($payload['user_id']) && in_array(ifset($payload, 'type', ''), ['access', 'refresh'], true)) {
        $principal = ifset($payload, 'principal', 'account');
        $payload['principal'] = $principal;
        $payload['sub'] = Subject::build($principal, (int)$payload['user_id']);
        if (!empty($payload['tenant_id'])) {
            global $sqhost, $squser, $sqpass, $sqdb, $connection;
            $previous = $connection;
            try {
                db_connect($sqhost, $squser, $sqpass, $sqdb, __FILE__ . ' linje ' . __LINE__);
                $tenantId = (int)$payload['tenant_id'];
                $tenant = db_fetch_array(db_select("SELECT db FROM regnskab WHERE id=$tenantId", __FILE__ . ' linje ' . __LINE__));
                if (!$tenant) {
                    throw new RuntimeException('Token tenant does not exist');
                }
                db_connect($sqhost, $squser, $sqpass, $tenant['db'], __FILE__ . ' linje ' . __LINE__);
                require_once __DIR__ . '/../../includes/mobileUpdates.php';
                mobileTenantUpdates();
            } finally {
                $connection = $previous;
            }
        }
    }
    return $payload;
});

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
    error_log(json_encode(['request_id' => RequestId::current(), 'exception' => get_class($e), 'location' => $e->getFile() . ':' . $e->getLine()]));
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Internal Server Error', 'data' => ['error' => 'internal']]);
    exit;
}

abstract class BaseEndpoint
{
    protected $conn;
    protected $model;
    protected $db;
    protected $userId;
    protected $username;
    protected $subject;
    protected $principal = 'account';
    protected $requestId;
    private $requestStarted;

    /**
     * Every resource endpoint must declare a right; an unknown default module denies access.
     * Override this method for declarations that differ by HTTP method.
     * @return array{module:string, action:string}|null
     */
    protected function requiredRight(): ?array
    {
        return ['module' => 'undeclared', 'action' => 'read'];
    }

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
        
        return 'api';
    }

    public function handleRequestMethod()
    {
        $this->requestStarted = microtime(true);
        $this->requestId = RequestId::current();
        RequestId::emit();
        ob_start();
        try {
            if (strpos(ifset($_SERVER, 'SCRIPT_NAME', ''), '/endpoints/v1/auth/') !== false) {
                global $sqhost, $squser, $sqpass, $sqdb;
                $registry = db_connect($sqhost, $squser, $sqpass, $sqdb, __FILE__ . ' linje ' . __LINE__);
                mobileRegistryUpdates($registry);
            }
            $payload = JWTAuth::validateToken();
            $right = $this->requiredRight();
            if ($payload && ($right === null || $right['module'] !== 'auth' || $right['action'] !== 'public')) {
                $this->requireTenantClaim($payload);
                try {
                    JWTAuth::checkSession($payload);
                } catch (MobileAuthError $e) {
                    $this->sendError($e->error, 'Session revoked. Login again', $e->status);
                }
            }
            if (!$this->checkAuthorization()) {
                $this->sendResponse(false, ['error' => 'invalid_token'], 'Unauthorized', 401);
            }
            // Custom authorization overrides also get canonical identity and declarations.
            $payload = JWTAuth::validateToken();
            if ($payload) {
                $this->setIdentity($payload);
            }
            if ($this->requiredRight() !== null && !$this->enforceDeclaredRight($payload)) {
                $this->sendResponse(false, ['error' => 'permission_denied'], 'Permission denied', 403);
            }
            ob_end_clean();
            $method = $_SERVER['REQUEST_METHOD'];
            switch ($method) {
                case 'POST':
                    $this->handlePost($this->decodeJsonBody(file_get_contents('php://input')));
                    break;
                case 'GET':
                    $this->handleGet(ifset($_GET, 'id', ifset($_GET, 'currencyCode')));
                    break;
                case 'PUT':
                    $this->handlePut($this->decodeJsonBody(file_get_contents('php://input')));
                    break;
                case 'DELETE':
                    $this->handleDelete($this->decodeJsonBody(file_get_contents('php://input')));
                    break;
                case 'PATCH':
                    $this->handlePatch($this->decodeJsonBody(file_get_contents('php://input')));
                    break;
                default:
                    $this->handleError(new Exception('Method Not Allowed'));
                    break;
            }
        } catch (Throwable $e) {
            $this->handleError($e);
        }
    }

    /** Derive old account tokens' subject; new claims must match server installation and numeric id. */
    protected function setIdentity(array $payload): void
    {
        $this->principal = ifset($payload, 'principal', 'account');
        if (!in_array($this->principal, ['account', 'registry'], true) || (int)(ifset($payload, 'user_id', 0)) < 1) {
            $this->sendError('invalid_token', 'Invalid token identity', 401);
        }
        if (!isset($payload['sub']) && $this->requiredRight() === null) {
            return;
        }
        $expected = Subject::build($this->principal, (int)$payload['user_id']);
        if (isset($payload['sub']) && $payload['sub'] !== $expected) {
            $this->sendResponse(false, ['error' => 'invalid_token'], 'Invalid token subject', 401);
        }
        $this->subject = $expected;
    }

    /** Principal-bearing tokens cannot open a tenant selected by a request header. */
    private function requireTenantClaim($payload): void
    {
        $right = $this->requiredRight();
        if ($payload && array_key_exists('principal', $payload)
            && !($right !== null && (($right['module'] === 'auth' && $right['action'] === 'self')
                || ($right['module'] === 'registry' && $right['action'] === 'read')))
            && filter_var(ifset($payload, 'tenant_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->sendError('invalid_token', 'Token requires a tenant identity', 401);
        }
    }

    /** Use current DB rights, never client-provided JWT rights or an is_admin substring. */
    protected function enforceDeclaredRight($payload): bool
    {
        $right = $this->requiredRight();
        if ($right['module'] === 'auth' && $right['action'] === 'public') {
            return true;
        }
        if (!$payload) {
            return false;
        }
        if ($right['module'] === 'auth' && $right['action'] === 'self') {
            return true;
        }
        if ($right['module'] === 'registry' && $right['action'] === 'read') {
            return $this->principal === 'registry';
        }
        if (!$this->db) {
            return false;
        }
        if ($this->principal === 'registry') {
            if (str_starts_with($right['module'], 'sager_') || $right['module'] === 'employee') {
                $this->sendError('permission_denied', 'Permission denied', 403, ['details' => ['reason' => 'no_employee_link']]);
            }
            return Rights::allows('', $right['module'], $right['action'], null, true);
        }
        global $sqhost, $squser, $sqpass;
        $this->conn = db_connect($sqhost, $squser, $sqpass, $this->db, __FILE__ . ' linje ' . __LINE__);
        $id = (int)$payload['user_id'];
        $row = db_fetch_array(db_select("SELECT rettigheder, ansat_id FROM brugere WHERE id=$id", __FILE__ . ' linje ' . __LINE__));
        if (!$row) {
            return false;
        }
        $caseRights = null;
        if (str_starts_with($right['module'], 'sager') || $right['module'] === 'employee') {
            require_once __DIR__ . '/EmployeeContext.php';
            $actor = new EmployeeContext($this->conn, $this->principal, $id);
            $caseRights = $actor->caseDigits;
            if (($right['module'] === 'employee' || str_starts_with($right['module'], 'sager_')) && !$actor->linked) {
                $this->sendError('permission_denied', 'Permission denied', 403, ['details' => ['reason' => 'no_employee_link']]);
            }
        }
        return Rights::allows((string)$row['rettigheder'], $right['module'], $right['action'], $caseRights);
    }

    // 20260812 CL/LH: reject malformed JSON bodies with a clear 400 instead of letting a null
    // $data surface further down as misleading 'missing required field' errors (a truncated
    // body previously read as a business-validation problem).
    protected function decodeJsonBody($rawInput)
    {
        $data = json_decode($rawInput);
        if ($data === null && trim((string)$rawInput) !== '' && json_last_error() !== JSON_ERROR_NONE) {
            $this->sendResponse(false, ['error' => 'validation_failed'], 'Invalid JSON body: ' . json_last_error_msg(), 400);
            exit;
        }
        return $data;
    }

    protected function sanitizeLogData($data)
    {
        $redacted = redactRestLogData($data);
        return substr(json_encode($redacted), 0, 2000);
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
            $this->username = ifset($payload, 'username', '');
            $right = $this->requiredRight();
            if ($right !== null && (($right['module'] === 'auth' && $right['action'] === 'self')
                || ($right['module'] === 'registry' && $right['action'] === 'read'))) {
                $this->setIdentity($payload);
                return true;
            }
            
            // Get the account database from the JWT or legacy X-Tenant-ID header
            $this->db = JWTAuth::getTenantDatabase();
            if (!$this->db) {
                $this->sendResponse(false, null, 'Account database not found. Login again or set the legacy X-Tenant-ID header to the numeric account ID.', 400);
                return false;
            }
            
            // Connect to the selected account database
            global $sqhost, $squser, $sqpass;
            $conn = db_connect($sqhost, $squser, $sqpass, $this->db, __FILE__ . " linje " . __LINE__);
            if (!$conn) {
                $this->sendResponse(false, null, 'Database connection failed', 500);
                return false;
            }
            
            $this->conn = $conn;
            $this->setIdentity($payload);
            return true;
        }

        // No valid authentication provided
        $this->sendResponse(false, null, 'Valid Bearer token required. Login via POST /auth/login.php with username, password and account_name.', 401);
        return false;
    }

    protected function sendResponse($success, $data = null, $message = '', $httpCode = 200)
    {
        if ($httpCode >= 500) {
            $success = false;
            $data = ['error' => 'internal'];
            $message = 'Internal Server Error';
        }
        $logDb = $this->getLogDb();
        if (is_array($data) && isset($data['access_token'])) {
            $payload = JWT::decode($data['access_token']);
            if ($payload) {
                $this->setIdentity($payload);
            }
            $logDb = ifset($data, ['tenant', 'db'], $logDb);
        }
        RequestId::emit();
        write_log([
            'method' => ifset($_SERVER, 'REQUEST_METHOD', ''),
            'path' => parse_url(ifset($_SERVER, 'REQUEST_URI', ''), PHP_URL_PATH) ?: '',
            'request_id' => RequestId::current(),
            'subject' => $this->subject,
            'status' => (int)$httpCode,
            'duration' => round((microtime(true) - (if_isset($this->requestStarted, microtime(true)))) * 1000, 3),
        ], $logDb);

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

    /** Canonical errors for new endpoints; legacy envelopes remain compatible until their owning slice updates them. */
    protected function sendError(string $code, string $message, int $httpCode, array $details = []): void
    {
        $details['error'] = $code;
        $this->sendResponse(false, $details, $message, $httpCode);
    }

    protected function handleError($exception = null)
    {
        $errorMessage = $exception ? $exception->getMessage() : "Internal Server Error";
        $statusCode = 500;
        
        if ($exception instanceof ApiException) {
            $statusCode = $exception->getStatusCode();
        }
        
        if ($statusCode >= 500) {
            error_log(json_encode([
                'request_id' => RequestId::current(),
                'exception' => $exception ? get_class($exception) : 'unknown',
                'location' => $exception ? $exception->getFile() . ':' . $exception->getLine() : 'unknown',
            ]));
            $this->sendError('internal', 'Internal Server Error', $statusCode);
        }
        $this->sendResponse(false, null, $exception instanceof ApiException && $statusCode >= 400 && $statusCode < 500 ? $errorMessage : 'Request failed', $statusCode);
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
