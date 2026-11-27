<?php
require_once('../../includes/connect.php');
require_once('../../includes/db_query.php');

$apiKey = '3150a782893ca1b7c76e05259820922bd439d888e2383e6cdadafbcf5d7d119';

// Check if api_keys table exists
$query = "SELECT EXISTS (
    SELECT FROM information_schema.tables 
    WHERE table_schema = 'public' 
    AND table_name = 'api_keys'
)";
$result = db_select($query, __FILE__ . " linje " . __LINE__);
$tableExists = db_fetch_array($result)['exists'];

echo "API Keys table exists: " . ($tableExists ? 'Yes' : 'No') . "\n";

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
    } else {
        echo "API Key not found in database\n";
    }
} 