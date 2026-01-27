<?php
/**
 * Projects Management Page
 * List, create, and manage projects
 */
@session_start();
$s_id=session_id();
require_once '../includes/connect.php';
require_once '../includes/online.php';
require_once '../includes/ProjectManager.php';

session_start();
$user_id = isset($_SESSION['bruger_id']) ? $_SESSION['bruger_id'] : (isset($bruger_id) ? $bruger_id : 1);

$pm = new ProjectManager();

// Handle project creation
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'create') {
    $project_data = [
        'name' => $_POST['project_name'],
        'key' => strtoupper($_POST['project_key']),
        'description' => $_POST['description'],
        'manager_id' => $user_id,
        'status' => 'active',
        'start_date' => $_POST['start_date'],
        'end_date' => $_POST['end_date'],
        'budget' => floatval($_POST['budget']),
        'created_by' => $user_id
    ];
    
    try {
        $project_id = $pm->createProject($project_data);
        
        // Add creator as admin member
        $sql = "INSERT INTO project_members (project_id, user_id, role) VALUES ('$project_id', '$user_id', 'admin')";
        db_modify($sql, __FILE__ . " line " . __LINE__);
        
        // Create default statuses
        $default_statuses = [
            ['To Do', 'todo', 1, '#6c757d'],
            ['In Progress', 'in_progress', 2, '#ffc107'],
            ['Review', 'review', 3, '#17a2b8'],
            ['Done', 'done', 4, '#28a745']
        ];
        
        foreach ($default_statuses as $status) {
            $sql = "INSERT INTO issue_statuses (name, status_type, project_id, position, color) VALUES ('" . db_escape_string($status[0]) . "', '" . db_escape_string($status[1]) . "', '$project_id', '" . $status[2] . "', '" . db_escape_string($status[3]) . "')";
            db_modify($sql, __FILE__ . " line " . __LINE__);
        }
        
        header('Location: project_view.php?id=' . $project_id);
        exit;
    } catch (Exception $e) {
        $error = "Error creating project: " . $e->getMessage();
    }
}

// Get all projects user has access to
$sql = "SELECT DISTINCT p.*, pm.role, 
        COUNT(DISTINCT i.id) as issue_count,
        COUNT(DISTINCT CASE WHEN s.status_type = 'done' THEN i.id END) as completed_issues
        FROM projects p 
        LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.user_id = '$user_id'
        LEFT JOIN issues i ON p.id = i.project_id
        LEFT JOIN issue_statuses s ON i.status_id = s.id
        WHERE pm.user_id = '$user_id' OR p.project_manager_id = '$user_id'
        GROUP BY p.id, pm.role
        ORDER BY p.updated_at DESC";
$result = db_select($sql, __FILE__ . " line " . __LINE__);

$projects = [];
while ($row = db_fetch_array($result)) {
    $projects[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects - Project Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .project-card {
            transition: transform 0.2s;
            cursor: pointer;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .project-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .progress-thin {
            height: 8px;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-project-diagram"></i> Project Management
            </a>
            <div class="navbar-nav">
                <a class="nav-link active" href="projects.php">
                    <i class="fas fa-folder"></i> Projects
                </a>
                <a class="nav-link" href="issues.php">
                    <i class="fas fa-tasks"></i> Issues
                </a>
                <a class="nav-link" href="../index.php">
                    <i class="fas fa-home"></i> Back to ERP
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-folder"></i> Projects</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProjectModal">
                        <i class="fas fa-plus"></i> New Project
                    </button>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="row">
                    <?php if (empty($projects)): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted">No Projects Yet</h4>
                                <p class="text-muted">Create your first project to get started with project management.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProjectModal">
                                    <i class="fas fa-plus"></i> Create First Project
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($projects as $project): ?>
                            <div class="col-lg-6 col-xl-4 mb-4">
                                <div class="card project-card h-100" onclick="window.location.href='project_view.php?id=<?= $project['id'] ?>'">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?= htmlspecialchars($project['project_key']) ?></h6>
                                        <span class="badge bg-<?= $project['status'] == 'active' ? 'success' : ($project['status'] == 'completed' ? 'primary' : 'warning') ?>">
                                            <?= ucfirst($project['status']) ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($project['project_name']) ?></h5>
                                        <p class="card-text text-muted"><?= htmlspecialchars(substr($project['description'], 0, 100)) ?>...</p>
                                        
                                        <div class="mb-3">
                                            <?php 
                                            $progress = $project['issue_count'] > 0 ? ($project['completed_issues'] / $project['issue_count']) * 100 : 0;
                                            ?>
                                            <div class="d-flex justify-content-between mb-1">
                                                <small class="text-muted">Progress</small>
                                                <small class="text-muted"><?= $project['completed_issues'] ?>/<?= $project['issue_count'] ?> issues</small>
                                            </div>
                                            <div class="progress progress-thin">
                                                <div class="progress-bar" role="progressbar" style="width: <?= $progress ?>%"></div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                Role: <?= ucfirst($project['role'] ?: 'Manager') ?>
                                            </small>
                                            <?php if ($project['budget']): ?>
                                                <small class="text-muted">
                                                    Budget: $<?= number_format($project['budget']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> 
                                            Updated <?= date('M j, Y', strtotime($project['updated_at'])) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Project Statistics</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $total_projects = count($projects);
                        $active_projects = count(array_filter($projects, function($p) { return $p['status'] == 'active'; }));
                        $completed_projects = count(array_filter($projects, function($p) { return $p['status'] == 'completed'; }));
                        $total_issues = array_sum(array_column($projects, 'issue_count'));
                        $completed_issues = array_sum(array_column($projects, 'completed_issues'));
                        ?>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <h3 class="text-primary"><?= $total_projects ?></h3>
                                <small class="text-muted">Total Projects</small>
                            </div>
                            <div class="col-6">
                                <h3 class="text-success"><?= $active_projects ?></h3>
                                <small class="text-muted">Active</small>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <h3 class="text-info"><?= $total_issues ?></h3>
                                <small class="text-muted">Total Issues</small>
                            </div>
                            <div class="col-6">
                                <h3 class="text-warning"><?= $completed_issues ?></h3>
                                <small class="text-muted">Completed</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> Tips</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-lightbulb text-warning"></i>
                                Use project keys to quickly reference projects
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-users text-info"></i>
                                Add team members to collaborate effectively
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-tags text-success"></i>
                                Use labels to categorize and filter issues
                            </li>
                            <li>
                                <i class="fas fa-sprint text-primary"></i>
                                Create sprints for agile development
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Project Modal -->
    <div class="modal fade" id="createProjectModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="project_name" class="form-label">Project Name *</label>
                                    <input type="text" class="form-control" id="project_name" name="project_name" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="project_key" class="form-label">Project Key *</label>
                                    <input type="text" class="form-control" id="project_key" name="project_key" 
                                           pattern="[A-Z0-9]{2,10}" title="2-10 uppercase letters/numbers" required>
                                    <small class="form-text text-muted">2-10 characters, uppercase</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="budget" class="form-label">Budget</label>
                                    <input type="number" class="form-control" id="budget" name="budget" step="0.01">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Project</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-generate project key from name
        document.getElementById('project_name').addEventListener('input', function() {
            const name = this.value;
            const key = name.replace(/[^a-zA-Z0-9]/g, '').substring(0, 10).toUpperCase();
            document.getElementById('project_key').value = key;
        });
    </script>
</body>
</html>
