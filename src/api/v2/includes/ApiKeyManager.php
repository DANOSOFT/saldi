<?php
class ApiKeyManager {
    private $connection;
    private $db_type;
    private $sqhost;
    private $squser;
    private $sqpass;
    private $sqdb;
    private $masterConnection;

    public function __construct() {
        global $connection, $db_type, $sqhost, $squser, $sqpass, $sqdb;
        $this->connection = $connection;
        $this->db_type = $db_type;
        $this->sqhost = $sqhost;
        $this->squser = $squser;
        $this->sqpass = $sqpass;
        $this->sqdb = $sqdb;
        
        // Connect to master database for API key validation
        $this->masterConnection = db_connect($this->sqhost, $this->squser, $this->sqpass, 'develop');
        
        // Debug connection info
        error_log("Database Type: " . $this->db_type);
        error_log("Master Database: develop");
        error_log("User Database: " . $this->sqdb);
    }

    public function validateApiKey($apiKey) {
        if (empty($apiKey)) {
            error_log("Empty API key provided");
            return false;
        }

        // Debug the API key being checked
        error_log("Validating API key: " . substr($apiKey, 0, 8) . '...' . substr($apiKey, -8));

        // First check if the table exists in master database
        $tableCheck = "SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_name = 'api_keys'
        )";
        error_log("Checking table existence with query: " . $tableCheck);
        
        // Use master connection for API key validation
        $tableResult = pg_query($this->masterConnection, $tableCheck);
        $tableExists = pg_fetch_result($tableResult, 0, 0) === 't';
        
        error_log("API Keys table exists: " . ($tableExists ? 'Yes' : 'No'));

        if (!$tableExists) {
            error_log("API Keys table does not exist in master database");
            return false;
        }

        $query = "SELECT * FROM api_keys WHERE api_key = '" . pg_escape_string($apiKey) . "' AND active = true";
        error_log("Executing query: " . $query);
        
        // Use master connection for API key validation
        $result = pg_query($this->masterConnection, $query);
        
        if ($row = pg_fetch_assoc($result)) {
            error_log("API key found in master database");
            error_log("API key data: " . print_r($row, true));
            return $row;
        }
        
        error_log("API key not found in master database or not active");
        return false;
    }

    public function connectToUserDatabase($database) {
        error_log("Attempting to connect to database: " . $database);
        
        if ($this->db_type == 'mysql' || $this->db_type == 'mysqli') {
            if (!mysqli_select_db($this->connection, $database)) {
                error_log("Failed to select MySQL database: " . mysqli_error($this->connection));
                return false;
            }
        } else {
            $this->connection = db_connect($this->sqhost, $this->squser, $this->sqpass, $database);
            if (!$this->connection) {
                error_log("Failed to connect to PostgreSQL database");
                return false;
            }
        }
        error_log("Successfully connected to database: " . $database);
        return true;
    }

    public function getDatabaseConnection() {
        return $this->connection;
    }
} 