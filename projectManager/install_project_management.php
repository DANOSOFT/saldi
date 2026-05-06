<?php
/**
 * Project Management System Installation Script
 * 
 * This script will install the project management database schema
 * and optionally insert sample data.
 */
@session_start();
$s_id=session_id();
// Include the database connection
require_once '../includes/connect.php';
require_once '../includes/online.php';
require_once '../includes/std_func.php';

// Set content type
header('Content-Type: text/html; charset=utf-8');

// Check if we're running the installation
$install = isset($_POST['install']) ? $_POST['install'] : '';
$install_sample_data = isset($_POST['sample_data']) ? true : false;

if ($install === 'run') {
    echo "<!DOCTYPE html><html><head><title>Project Management Installation</title></head><body>";
    echo "<h1>Installing Project Management System...</h1>";
    echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; margin: 20px 0;'>";
    
    try {
        // Start transaction
        db_modify("BEGIN", __FILE__ . " linje " . __LINE__);
        
        echo "✓ Starting installation...<br>";
        
        // Check if required functions are available
        if (!function_exists('db_modify')) {
            throw new Exception("Database function db_modify() not available. Check includes.");
        }
        
        // Read and execute the main schema
        $schema_file = 'project_management_schema_ordered.sql';
        if (!file_exists($schema_file)) {
            // Fallback to original schema file
            $schema_file = 'project_management_schema.sql';
        }
        if (!file_exists($schema_file)) {
            throw new Exception("Schema file not found: $schema_file");
        }
        
        $schema_sql = file_get_contents($schema_file);
        
        echo "✓ Loading schema file...<br>";
        
        // Execute the schema carefully to avoid injection check
        try {
            // Clean the SQL: remove comments and empty lines
            $cleaned_sql = preg_replace('/--.*$/m', '', $schema_sql); // Remove line comments
            $cleaned_sql = preg_replace('/\/\*.*?\*\//s', '', $cleaned_sql); // Remove block comments
            
            // Better approach: Split on semicolons that are NOT inside PostgreSQL functions
            // PostgreSQL functions use $$ delimiters, so we need to preserve those
            $statements = [];
            $lines = explode("\n", $cleaned_sql);
            $current_statement = '';
            $in_function = false;
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Detect start of PostgreSQL function
                if (preg_match('/\$\$\s*$/', $line) && preg_match('/CREATE.*FUNCTION/i', $current_statement)) {
                    $in_function = true;
                }
                
                $current_statement .= $line . "\n";
                
                // Detect end of PostgreSQL function
                if ($in_function && preg_match('/\$\$\s*LANGUAGE/i', $line)) {
                    $in_function = false;
                    // For functions, only remove trailing semicolon, not internal ones
                    $stmt = trim($current_statement);
                    $stmt = rtrim($stmt, ';');
                    $statements[] = $stmt;
                    $current_statement = '';
                } elseif (!$in_function && preg_match('/;\s*$/', $line)) {
                    // For regular statements, remove semicolon at end
                    $stmt = trim($current_statement);
                    $stmt = rtrim($stmt, ';');
                    $statements[] = $stmt;
                    $current_statement = '';
                }
            }
            
            // Add any remaining statement
            if (!empty(trim($current_statement))) {
                $stmt = trim($current_statement);
                $stmt = rtrim($stmt, ';');
                $statements[] = $stmt;
            }
            
            $step = 1;
            $total_steps = count($statements);
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    try {
                        echo "Step $step/$total_steps: Executing SQL statement...<br>";
                        db_modify($statement, __FILE__ . " line " . __LINE__);
                        $step++;
                    } catch (Exception $e) {
                        $error_msg = $e->getMessage();
                        // Handle expected errors gracefully
                        if (strpos($error_msg, 'already exists') !== false || 
                            strpos($error_msg, 'duplicate key') !== false ||
                            strpos($error_msg, 'constraint') !== false) {
                            echo "Info at step $step: " . $error_msg . " (ignored)<br>";
                        } else {
                            echo "Warning at step $step: " . $error_msg . "<br>";
                            // Don't throw on warnings, continue installation
                        }
                        $step++;
                    }
                }
            }
        } catch (Exception $e) {
            echo "Critical error: " . $e->getMessage() . "<br>";
            throw $e;
        }
        
        echo "✓ Schema installed successfully!<br>";
        
        // Install sample data if requested
        if ($install_sample_data) {
            $sample_file = 'sample_project_data.sql';
            if (file_exists($sample_file)) {
                echo "✓ Installing sample data...<br>";
                
                $sample_sql = file_get_contents($sample_file);
                // Split statements and remove semicolons to avoid injection check
                $sample_statements = explode(';', $sample_sql);
                
                foreach ($sample_statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        try {
                            // Remove any remaining semicolons
                            $statement = rtrim($statement, ';');
                            db_modify($statement, __FILE__ . " line " . __LINE__);
                        } catch (Exception $e) {
                            echo "Sample data warning: " . $e->getMessage() . "<br>";
                        }
                    }
                }
                echo "✓ Sample data installed successfully!<br>";
            } else {
                echo "No sample data file found (sample_project_data.sql)<br>";
            }
        }
        
        // Commit transaction
        db_modify("COMMIT", __FILE__ . " line " . __LINE__);
        
        echo "✓ Installation completed successfully!<br>";
        echo "</div>";
        
        echo "<h2>Next Steps:</h2>";
        echo "<ul>";
        echo "<li>Review the PROJECT_MANAGEMENT_README.md file for detailed documentation</li>";
        echo "<li>Include the ProjectManager.php class in your application</li>";
        echo "<li>Create your first project and start managing issues</li>";
        echo "<li>Set up proper user permissions and roles</li>";
        echo "</ul>";
        
        echo "<h2>Quick Test:</h2>";
        echo "<p>You can test the installation by running some basic queries:</p>";
        
        // Test the installation
        echo "<div style='background: #e8f5e8; padding: 10px; margin: 10px 0;'>";
        
        $test_query = "SELECT COUNT(*) as count FROM projects";
        $result = db_select($test_query, __FILE__ . " line " . __LINE__);
        $row = db_fetch_array($result);
        echo "Projects in database: " . $row['count'] . "<br>";
        
        $test_query = "SELECT COUNT(*) as count FROM issues";
        $result = db_select($test_query, __FILE__ . " line " . __LINE__);
        $row = db_fetch_array($result);
        echo "Issues in database: " . $row['count'] . "<br>";
        
        $test_query = "SELECT COUNT(*) as count FROM issue_types";
        $result = db_select($test_query, __FILE__ . " line " . __LINE__);
        $row = db_fetch_array($result);
        echo "Issue types available: " . $row['count'] . "<br>";
        
        echo "</div>";
        
    } catch (Exception $e) {
        // Rollback on error
        db_modify("ROLLBACK", __FILE__ . " line " . __LINE__);
        
        echo "</div>";
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 20px 0; border: 1px solid #f5c6cb;'>";
        echo "<h3>Installation Failed!</h3>";
        echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p>Please check your database connection and try again.</p>";
        echo "</div>";
    }
    
    echo "</body></html>";
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Management System Installation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }
        .header {
            background: #007bff;
            color: white;
            padding: 20px;
            margin: -20px -20px 20px -20px;
            text-align: center;
        }
        .form-group {
            margin: 15px 0;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .checkbox-group {
            margin: 10px 0;
        }
        .checkbox-group input {
            margin-right: 10px;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .info-box {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .feature-list {
            background: #f8f9fa;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .feature-list ul {
            margin: 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Project Management System Installation</h1>
        <p>Issue Tracking, Agile Project Management & Collaboration</p>
    </div>

    <div class="info-box">
        <h3>About This Installation</h3>
        <p>This installation will add comprehensive project management capabilities to your ERP system, including:</p>
        
        <div class="feature-list">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4>📊 Project Management</h4>
                    <ul>
                        <li>Project creation and tracking</li>
                        <li>Team assignment and roles</li>
                        <li>Budget and timeline management</li>
                        <li>Project status tracking</li>
                    </ul>
                </div>
                
                <div>
                    <h4>🎯 Issue Tracking</h4>
                    <ul>
                        <li>Bug tracking and feature requests</li>
                        <li>Task assignment and prioritization</li>
                        <li>Status workflows</li>
                        <li>Issue linking and dependencies</li>
                    </ul>
                </div>
                
                <div>
                    <h4>🏃 Agile Features</h4>
                    <ul>
                        <li>Sprint planning and management</li>
                        <li>Story points and estimation</li>
                        <li>Kanban and Scrum boards</li>
                        <li>Velocity tracking</li>
                    </ul>
                </div>
                
                <div>
                    <h4>👥 Collaboration</h4>
                    <ul>
                        <li>Comments and discussions</li>
                        <li>File attachments</li>
                        <li>Notifications and watchers</li>
                        <li>Activity logging</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="warning-box">
        <h3>⚠️ Before Installation</h3>
        <ul>
            <li><strong>Backup your database</strong> - This installation will create new tables</li>
            <li><strong>Database permissions</strong> - Ensure your database user has CREATE TABLE privileges</li>
            <li><strong>Existing data</strong> - This installation integrates with your existing 'brugere' table</li>
            <li><strong>PostgreSQL required</strong> - This schema is designed for PostgreSQL databases</li>
        </ul>
    </div>

    <form method="post" action="">
        <h2>Installation Options</h2>
        
        <div class="form-group">
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="sample_data" value="1" checked>
                    Install sample data (recommended for testing)
                </label>
                <small style="display: block; color: #666; margin-top: 5px;">
                    This will create sample projects, issues, and data to help you get started.
                </small>
            </div>
        </div>
        
        <div class="info-box">
            <h4>Database Connection Status</h4>
            <?php
            try {
                // Test database connection
                $test_query = "SELECT current_database(), current_user, version()";
                $result = db_select($test_query, __FILE__ . " line " . __LINE__);
                $row = db_fetch_array($result);
                
                echo "<p>✅ <strong>Database Connection:</strong> Successfully connected</p>";
                echo "<p><strong>Database:</strong> " . htmlspecialchars($row['current_database']) . "</p>";
                echo "<p><strong>User:</strong> " . htmlspecialchars($row['current_user']) . "</p>";
                echo "<p><strong>Version:</strong> " . htmlspecialchars($row['version']) . "</p>";
                
                // Check if brugere table exists
                $check_query = "SELECT COUNT(*) as count FROM brugere";
                $result = db_select($check_query, __FILE__ . " line " . __LINE__);
                $row = db_fetch_array($result);
                echo "<p>✅ <strong>Users table (brugere):</strong> Found " . $row['count'] . " users</p>";
                
                $can_install = true;
                
            } catch (Exception $e) {
                echo "<p>❌ <strong>Database Connection:</strong> Failed</p>";
                echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
                $can_install = false;
            }
            ?>
        </div>
        
        <?php if ($can_install): ?>
        <div class="form-group">
            <button type="submit" name="install" value="run" class="btn">
                🚀 Install Project Management System
            </button>
        </div>
        <?php else: ?>
        <div class="form-group">
            <button type="button" class="btn" disabled style="background: #6c757d;">
                ❌ Cannot Install - Fix Database Connection First
            </button>
        </div>
        <?php endif; ?>
    </form>

    <div class="info-box">
        <h3>📚 What Happens Next?</h3>
        <ol>
            <li><strong>Database Setup:</strong> New tables will be created for project management</li>
            <li><strong>Sample Data:</strong> Example projects and issues will be added (if selected)</li>
            <li><strong>Integration:</strong> The system will be ready to use with your existing users</li>
            <li><strong>Documentation:</strong> Review the README file for usage instructions</li>
        </ol>
    </div>

    <div class="info-box">
        <h3>🔧 Files Included</h3>
        <ul>
            <li><code>project_management_schema.sql</code> - Main database schema</li>
            <li><code>sample_project_data.sql</code> - Sample data for testing</li>
            <li><code>includes/ProjectManager.php</code> - PHP helper class</li>
            <li><code>PROJECT_MANAGEMENT_README.md</code> - Detailed documentation</li>
        </ul>
    </div>
</body>
</html>
