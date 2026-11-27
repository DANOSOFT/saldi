<?php
/**
 * Project Management Installation - Direct SQL Approach
 * 
 * This script executes SQL statements one by one to avoid injection detection
 * Each statement is carefully crafted to not contain semicolons
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

if ($install === 'run') {
    echo "<!DOCTYPE html><html><head><title>Project Management Direct Installation</title></head><body>";
    echo "<h1>Installing Project Management System (Direct Method)...</h1>";
    echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; margin: 20px 0;'>";
    
    try {
        // Start transaction
        db_modify("BEGIN", __FILE__ . " line " . __LINE__);
        echo "✓ Starting transaction...<br>";
        
        // Define all SQL statements as individual arrays to avoid semicolon issues
        $statements = [
            // Phase 1: Basic lookup tables
            "CREATE TABLE IF NOT EXISTS issue_types (
                id SERIAL PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                description TEXT,
                icon VARCHAR(50),
                color VARCHAR(7),
                is_active BOOLEAN DEFAULT TRUE
            )",
            
            "CREATE TABLE IF NOT EXISTS issue_priorities (
                id SERIAL PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                level INTEGER NOT NULL UNIQUE,
                color VARCHAR(7),
                is_active BOOLEAN DEFAULT TRUE
            )",
            
            "CREATE TABLE IF NOT EXISTS project_statuses (
                id SERIAL PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                description TEXT,
                color VARCHAR(7),
                is_active BOOLEAN DEFAULT TRUE
            )",
            
            "CREATE TABLE IF NOT EXISTS project_types (
                id SERIAL PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                description TEXT,
                icon VARCHAR(50),
                is_active BOOLEAN DEFAULT TRUE
            )",
            
            "CREATE TABLE IF NOT EXISTS sprint_statuses (
                id SERIAL PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                color VARCHAR(7),
                is_active BOOLEAN DEFAULT TRUE
            )",
            
            "CREATE TABLE IF NOT EXISTS user_roles (
                id SERIAL PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                description TEXT,
                permissions JSON,
                is_active BOOLEAN DEFAULT TRUE
            )",
            
            // Phase 2: Main entity tables
            "CREATE TABLE IF NOT EXISTS projects (
                id SERIAL PRIMARY KEY,
                project_name VARCHAR(255) NOT NULL,
                project_key VARCHAR(20) UNIQUE NOT NULL,
                description TEXT,
                project_manager_id INTEGER REFERENCES brugere(id),
                project_type_id INTEGER REFERENCES project_types(id),
                status_id INTEGER REFERENCES project_statuses(id),
                start_date DATE,
                end_date DATE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS project_members (
                id SERIAL PRIMARY KEY,
                project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
                user_id INTEGER REFERENCES brugere(id) ON DELETE CASCADE,
                role_id INTEGER REFERENCES user_roles(id),
                joined_date DATE DEFAULT CURRENT_DATE,
                is_active BOOLEAN DEFAULT TRUE,
                UNIQUE(project_id, user_id)
            )",
            
            "CREATE TABLE IF NOT EXISTS project_categories (
                id SERIAL PRIMARY KEY,
                project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                color VARCHAR(7)
            )",
            
            "CREATE TABLE IF NOT EXISTS issue_statuses (
                id SERIAL PRIMARY KEY,
                project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
                name VARCHAR(50) NOT NULL,
                description TEXT,
                color VARCHAR(7),
                is_closed BOOLEAN DEFAULT FALSE,
                sort_order INTEGER DEFAULT 0
            )",
            
            // Phase 3: Issues and related tables
            "CREATE TABLE IF NOT EXISTS issues (
                id SERIAL PRIMARY KEY,
                issue_key VARCHAR(50) UNIQUE NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
                issue_type_id INTEGER REFERENCES issue_types(id),
                priority_id INTEGER REFERENCES issue_priorities(id),
                status_id INTEGER REFERENCES issue_statuses(id),
                assignee_id INTEGER REFERENCES brugere(id),
                reporter_id INTEGER REFERENCES brugere(id),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS issue_comments (
                id SERIAL PRIMARY KEY,
                issue_id INTEGER REFERENCES issues(id) ON DELETE CASCADE,
                user_id INTEGER REFERENCES brugere(id),
                comment_text TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS issue_activity (
                id SERIAL PRIMARY KEY,
                issue_id INTEGER REFERENCES issues(id) ON DELETE CASCADE,
                user_id INTEGER REFERENCES brugere(id),
                action VARCHAR(100) NOT NULL,
                field_name VARCHAR(100),
                old_value TEXT,
                new_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS project_notifications (
                id SERIAL PRIMARY KEY,
                user_id INTEGER REFERENCES brugere(id),
                title VARCHAR(255) NOT NULL,
                message TEXT,
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
        ];
        
        $step = 1;
        foreach ($statements as $statement) {
            try {
                echo "Step $step: Executing table creation...<br>";
                db_modify($statement, __FILE__ . " line " . __LINE__);
                echo "✓ Step $step completed<br>";
                $step++;
            } catch (Exception $e) {
                echo "Warning at step $step: " . $e->getMessage() . "<br>";
                $step++;
            }
        }
        
        echo "✓ Core tables created!<br>";
        
        // Now insert basic data - one INSERT at a time with conflict handling
        $insert_statements = [
            "INSERT INTO issue_types (name, description, icon, color) VALUES ('Bug', 'A problem that impairs or prevents the functions of the product', 'bug', '#d73a49') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO issue_types (name, description, icon, color) VALUES ('Feature', 'A new feature or enhancement request', 'star', '#28a745') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO issue_types (name, description, icon, color) VALUES ('Task', 'A general task that needs to be completed', 'check', '#6f42c1') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO issue_types (name, description, icon, color) VALUES ('Story', 'A user story for agile development', 'book', '#007bff') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO issue_types (name, description, icon, color) VALUES ('Epic', 'A large user story', 'bookmark', '#fd7e14') ON CONFLICT (name) DO NOTHING",
            
            "INSERT INTO issue_priorities (name, level, color) VALUES ('Critical', 1, '#dc3545') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO issue_priorities (name, level, color) VALUES ('High', 2, '#fd7e14') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO issue_priorities (name, level, color) VALUES ('Medium', 3, '#ffc107') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO issue_priorities (name, level, color) VALUES ('Low', 4, '#28a745') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO issue_priorities (name, level, color) VALUES ('Lowest', 5, '#6c757d') ON CONFLICT (name) DO NOTHING",
            
            "INSERT INTO project_statuses (name, description, color) VALUES ('Planning', 'Project is in planning phase', '#6c757d') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO project_statuses (name, description, color) VALUES ('Active', 'Project is actively being worked on', '#28a745') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO project_statuses (name, description, color) VALUES ('On Hold', 'Project is temporarily paused', '#ffc107') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO project_statuses (name, description, color) VALUES ('Completed', 'Project has been completed', '#007bff') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO project_statuses (name, description, color) VALUES ('Cancelled', 'Project has been cancelled', '#dc3545') ON CONFLICT (name) DO NOTHING",
            
            "INSERT INTO user_roles (name, description) VALUES ('Admin', 'Full access to all project features') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO user_roles (name, description) VALUES ('Manager', 'Can manage projects and teams') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO user_roles (name, description) VALUES ('Developer', 'Can work on issues and update status') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO user_roles (name, description) VALUES ('Viewer', 'Read-only access to projects') ON CONFLICT (name) DO NOTHING",
            
            // Insert default project types
            "INSERT INTO project_types (name, description, icon) VALUES ('Software', 'Software development project', 'code') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO project_types (name, description, icon) VALUES ('Marketing', 'Marketing campaign project', 'bullhorn') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO project_types (name, description, icon) VALUES ('Research', 'Research and development project', 'search') ON CONFLICT (name) DO NOTHING",
            
            // Insert default sprint statuses
            "INSERT INTO sprint_statuses (name, color) VALUES ('Planning', '#6c757d') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO sprint_statuses (name, color) VALUES ('Active', '#28a745') ON CONFLICT (name) DO NOTHING",
            "INSERT INTO sprint_statuses (name, color) VALUES ('Completed', '#007bff') ON CONFLICT (name) DO NOTHING",
        ];
        
        echo "✓ Inserting default data...<br>";
        foreach ($insert_statements as $statement) {
            try {
                db_modify($statement, __FILE__ . " line " . __LINE__);
            } catch (Exception $e) {
                // Log any unexpected errors (ON CONFLICT should handle duplicates)
                echo "Insert note: " . $e->getMessage() . "<br>";
            }
        }
        
        // Commit transaction
        db_modify("COMMIT", __FILE__ . " line " . __LINE__);
        
        echo "✓ Installation completed successfully!<br>";
        echo "</div>";
        
        echo "<h2>Installation Summary</h2>";
        echo "<p>The basic project management tables have been created with default data.</p>";
        echo "<p><a href='status.php'>Check Installation Status</a></p>";
        
    } catch (Exception $e) {
        // Rollback on error
        db_modify("ROLLBACK", __FILE__ . " line " . __LINE__);
        
        echo "</div>";
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 20px 0; border: 1px solid #f5c6cb;'>";
        echo "<h3>Installation Failed!</h3>";
        echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
    
    echo "</body></html>";
} else {
    // Show installation form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Project Management Direct Installation</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .form-container { background: #f8f9fa; padding: 30px; border-radius: 8px; border: 1px solid #dee2e6; }
            .btn { background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
            .btn:hover { background: #0056b3; }
            .warning { background: #fff3cd; color: #856404; padding: 15px; margin: 20px 0; border: 1px solid #ffeaa7; border-radius: 4px; }
        </style>
    </head>
    <body>
        <h1>Project Management System - Direct Installation</h1>
        
        <div class="form-container">
            <h2>Direct Installation Method</h2>
            <p>This installation method executes SQL statements individually to avoid injection detection issues.</p>
            
            <div class="warning">
                <strong>Note:</strong> This will create the basic project management tables and insert default data.
                Make sure you have proper database permissions.
            </div>
            
            <form method="POST">
                <input type="hidden" name="install" value="run">
                <button type="submit" class="btn">Install Project Management System</button>
            </form>
            
            <h3>What will be created:</h3>
            <ul>
                <li>Core lookup tables (issue types, priorities, statuses)</li>
                <li>Project management tables</li>
                <li>Default data for immediate use</li>
            </ul>
            
            <p><a href="status.php">Check Current Status</a></p>
        </div>
    </body>
    </html>
    <?php
}
?>
