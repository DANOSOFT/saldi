<?php
/**
 * Installation Status and Setup Check
 * Verify that the project management system is properly installed
 */
@session_start();
$s_id=session_id();
require_once '../includes/connect.php';
require_once '../includes/online.php';
require_once 'config.php';

// Check database tables
$tables_to_check = [
    'projects', 'project_members', 'issue_types', 'issue_priorities', 
    'issue_statuses', 'issues', 'project_labels', 'issue_project_labels',
    'issue_comments', 'issue_attachments', 'sprints', 'sprint_issues',
    'work_logs', 'issue_watchers', 'issue_activity', 'project_notifications',
    'teams', 'team_members', 'project_teams', 'boards', 'custom_fields',
    'custom_field_values'
];

$installed_tables = [];
$missing_tables = [];

foreach ($tables_to_check as $table) {
    $sql = "SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = '$table')";
    try {
        $result = db_select($sql, __FILE__ . " line " . __LINE__);
        $row = db_fetch_array($result);
        if ($row['exists'] === 't' || $row['exists'] === true) {
            $installed_tables[] = $table;
        } else {
            $missing_tables[] = $table;
        }
    } catch (Exception $e) {
        $missing_tables[] = $table;
    }
}

// Get statistics
$stats = [];
if (empty($missing_tables)) {
    try {
        // Count projects
        $result = db_select("SELECT COUNT(*) as count FROM projects", __FILE__ . " line " . __LINE__);
        $row = db_fetch_array($result);
        $stats['projects'] = $row['count'];
        
        // Count issues
        $result = db_select("SELECT COUNT(*) as count FROM issues", __FILE__ . " line " . __LINE__);
        $row = db_fetch_array($result);
        $stats['issues'] = $row['count'];
        
        // Count users in brugere table
        $result = db_select("SELECT COUNT(*) as count FROM brugere", __FILE__ . " line " . __LINE__);
        $row = db_fetch_array($result);
        $stats['users'] = $row['count'];
        
        // Count teams
        $result = db_select("SELECT COUNT(*) as count FROM teams", __FILE__ . " line " . __LINE__);
        $row = db_fetch_array($result);
        $stats['teams'] = $row['count'];
        
    } catch (Exception $e) {
        // Ignore errors for statistics
    }
}

// Check file permissions
$files_to_check = [
    'index.php' => 'Main dashboard',
    'projects.php' => 'Projects listing',
    'project_view.php' => 'Project details',
    'api/notifications.php' => 'Notifications API',
    'api/issues.php' => 'Issues API',
    'config.php' => 'Configuration file'
];

$file_status = [];
foreach ($files_to_check as $file => $description) {
    $file_status[$file] = [
        'description' => $description,
        'exists' => file_exists($file),
        'readable' => file_exists($file) && is_readable($file)
    ];
}

// System requirements check
$requirements = [
    'PHP Version' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PostgreSQL Extension' => function_exists('pg_connect'),
    'Session Support' => function_exists('session_start'),
    'JSON Support' => function_exists('json_encode'),
    'File Upload Support' => ini_get('file_uploads')
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Status - Project Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-ok { color: #28a745; }
        .status-error { color: #dc3545; }
        .status-warning { color: #ffc107; }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header text-center">
                        <h2><i class="fas fa-project-diagram"></i> Project Management System</h2>
                        <p class="mb-0">Installation Status & System Check</p>
                    </div>
                    <div class="card-body">
                        
                        <!-- Overall Status -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <?php if (empty($missing_tables)): ?>
                                    <div class="alert alert-success">
                                        <h4><i class="fas fa-check-circle"></i> Installation Complete!</h4>
                                        <p class="mb-0">All database tables are installed and the system is ready to use.</p>
                                    </div>
                                    <div class="text-center mb-3">
                                        <a href="index.php" class="btn btn-primary btn-lg">
                                            <i class="fas fa-rocket"></i> Launch Project Management
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <h4><i class="fas fa-exclamation-triangle"></i> Installation Incomplete</h4>
                                        <p class="mb-0">Some database tables are missing. Please run the installation script.</p>
                                    </div>
                                    <div class="text-center mb-3">
                                        <a href="install_project_management.php" class="btn btn-warning btn-lg">
                                            <i class="fas fa-tools"></i> Run Installation
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Statistics -->
                        <?php if (!empty($stats)): ?>
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h5><i class="fas fa-chart-bar"></i> System Statistics</h5>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="card bg-primary text-white">
                                            <div class="card-body text-center">
                                                <h3><?= $stats['projects'] ?></h3>
                                                <small>Projects</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-success text-white">
                                            <div class="card-body text-center">
                                                <h3><?= $stats['issues'] ?></h3>
                                                <small>Issues</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-info text-white">
                                            <div class="card-body text-center">
                                                <h3><?= $stats['users'] ?></h3>
                                                <small>Users</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card bg-warning text-white">
                                            <div class="card-body text-center">
                                                <h3><?= $stats['teams'] ?></h3>
                                                <small>Teams</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="row">
                            <!-- Database Tables -->
                            <div class="col-md-6">
                                <h5><i class="fas fa-database"></i> Database Tables</h5>
                                <div class="list-group mb-3">
                                    <?php foreach ($tables_to_check as $table): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= $table ?>
                                            <?php if (in_array($table, $installed_tables)): ?>
                                                <i class="fas fa-check status-ok"></i>
                                            <?php else: ?>
                                                <i class="fas fa-times status-error"></i>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (!empty($missing_tables)): ?>
                                    <div class="alert alert-danger">
                                        <strong>Missing Tables:</strong>
                                        <ul class="mb-0">
                                            <?php foreach ($missing_tables as $table): ?>
                                                <li><?= $table ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- System Files -->
                            <div class="col-md-6">
                                <h5><i class="fas fa-file-code"></i> System Files</h5>
                                <div class="list-group mb-3">
                                    <?php foreach ($file_status as $file => $info): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?= $file ?></strong>
                                                    <br><small class="text-muted"><?= $info['description'] ?></small>
                                                </div>
                                                <?php if ($info['exists'] && $info['readable']): ?>
                                                    <i class="fas fa-check status-ok"></i>
                                                <?php elseif ($info['exists']): ?>
                                                    <i class="fas fa-exclamation-triangle status-warning" title="File exists but not readable"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-times status-error" title="File missing"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- System Requirements -->
                                <h5><i class="fas fa-server"></i> System Requirements</h5>
                                <div class="list-group">
                                    <?php foreach ($requirements as $requirement => $status): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= $requirement ?>
                                            <?php if ($status): ?>
                                                <i class="fas fa-check status-ok"></i>
                                            <?php else: ?>
                                                <i class="fas fa-times status-error"></i>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Configuration -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h5><i class="fas fa-cog"></i> Configuration</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <tbody>
                                            <tr>
                                                <td><strong>Version</strong></td>
                                                <td><?= PM_VERSION ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Database Type</strong></td>
                                                <td><?= $db_type ?? 'PostgreSQL' ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>PHP Version</strong></td>
                                                <td><?= PHP_VERSION ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>ERP Integration</strong></td>
                                                <td><?= PM_USE_ERP_AUTH ? 'Enabled' : 'Disabled' ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Time Tracking</strong></td>
                                                <td><?= PM_ENABLE_TIME_TRACKING ? 'Enabled' : 'Disabled' ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>File Uploads</strong></td>
                                                <td><?= PM_ENABLE_ATTACHMENTS ? 'Enabled' : 'Disabled' ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="row mt-4">
                            <div class="col-md-12 text-center">
                                <?php if (empty($missing_tables)): ?>
                                    <a href="index.php" class="btn btn-primary me-2">
                                        <i class="fas fa-home"></i> Dashboard
                                    </a>
                                    <a href="projects.php" class="btn btn-success me-2">
                                        <i class="fas fa-folder"></i> Projects
                                    </a>
                                <?php endif; ?>
                                <a href="../index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to ERP
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
