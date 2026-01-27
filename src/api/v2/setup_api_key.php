<?php
require_once('../../includes/connect.php');
require_once('../../includes/db_query.php');

$apiKey = '3150a782893ca1b7c76e05259820922bd439d888e2383e6cdadafbcf5d7d119';
$database = 'develop'; // Using the same database as in connect.php

echo "Current database: " . $sqdb . "\n";
echo "Database type: " . $db_type . "\n";

// Check if api_keys table exists
$tableCheck = "SELECT EXISTS (
    SELECT FROM information_schema.tables 
    WHERE table_schema = 'public' 
    AND table_name = 'api_keys'
)";
$tableResult = db_select($tableCheck, __FILE__ . " linje " . __LINE__);
$tableExists = db_fetch_array($tableResult)['exists'];

echo "API Keys table exists: " . ($tableExists ? 'Yes' : 'No') . "\n";

if (!$tableExists) {
    // Create the table
    $createTable = "CREATE TABLE api_keys (
        id SERIAL PRIMARY KEY,
        api_key VARCHAR(64) UNIQUE NOT NULL,
        database VARCHAR(255) NOT NULL,
        description TEXT,
        active BOOLEAN DEFAULT true,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_used_at TIMESTAMP,
        created_by VARCHAR(255) DEFAULT 'system'
    )";
    
    if (db_modify($createTable, __FILE__ . " linje " . __LINE__)) {
        echo "Created api_keys table\n";
        $tableExists = true;
    } else {
        echo "Failed to create api_keys table\n";
        exit;
    }
}

if ($tableExists) {
    // Check if the API key exists
    $query = "SELECT * FROM api_keys WHERE api_key = '" . db_escape_string($apiKey) . "'";
    $result = db_select($query, __FILE__ . " linje " . __LINE__);
    $keyData = db_fetch_array($result);
    
    if ($keyData) {
        echo "API Key found:\n";
        echo "Database: " . $keyData['database'] . "\n";
        echo "Active: " . ($keyData['active'] ? 'Yes' : 'No') . "\n";
        echo "Created: " . $keyData['created_at'] . "\n";
        echo "Last Used: " . ($keyData['last_used_at'] ?? 'Never') . "\n";
        
        // Update if needed
        if (!$keyData['active']) {
            $update = "UPDATE api_keys SET active = true WHERE api_key = '" . db_escape_string($apiKey) . "'";
            if (db_modify($update, __FILE__ . " linje " . __LINE__)) {
                echo "Reactivated API key\n";
            }
        }
    } else {
        // Create the API key
        $insert = "INSERT INTO api_keys (api_key, database, description, created_by) VALUES (
            '" . db_escape_string($apiKey) . "',
            '" . db_escape_string($database) . "',
            'Test API Key',
            'setup_script'
        )";
        
        if (db_modify($insert, __FILE__ . " linje " . __LINE__)) {
            echo "Created new API key\n";
        } else {
            echo "Failed to create API key\n";
        }
    }
    
    // Verify the API key was created/updated
    $verifyQuery = "SELECT * FROM api_keys WHERE api_key = '" . db_escape_string($apiKey) . "'";
    $verifyResult = db_select($verifyQuery, __FILE__ . " linje " . __LINE__);
    $verifyData = db_fetch_array($verifyResult);
    
    if ($verifyData) {
        echo "\nVerification - API Key details:\n";
        echo "ID: " . $verifyData['id'] . "\n";
        echo "Database: " . $verifyData['database'] . "\n";
        echo "Active: " . ($verifyData['active'] ? 'Yes' : 'No') . "\n";
        echo "Created: " . $verifyData['created_at'] . "\n";
    }
} 