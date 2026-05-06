<?php
/**
 * Project View Page
 * Display project details, issues, and management options
 */
@session_start();
$s_id=session_id();
require_once '../includes/connect.php';
require_once '../includes/online.php';
require_once '../includes/ProjectManager.php';

session_start();
$user_id = isset($_SESSION['bruger_id']) ? $_SESSION['bruger_id'] : (isset($bruger_id) ? $bruger_id : 1);

$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$project_id) {
    header('Location: projects.php');
    exit;
}

$pm = new ProjectManager();

// Get project details
$project = $pm->getProject($project_id);
if (!$project) {
    header('Location: projects.php');
    exit;
}

// Check user access
$sql = "SELECT role FROM project_members WHERE project_id = '$project_id' AND user_id = '$user_id'";
$result = db_select($sql, __FILE__ . " line " . __LINE__);
$user_role = db_fetch_array($result);

if (!$user_role && $project['project_manager_id'] != $user_id) {
    header('Location: projects.php');
    exit;
}

// Get project statistics
$stats = $pm->getProjectStats($project_id);

// Get project issues
$issues = $pm->getProjectIssues($project_id);

// Get project statuses
$statuses = $pm->getProjectStatuses($project_id);

// Get project members
$sql = "SELECT pm.*, b.brugernavn, b.email 
        FROM project_members pm
        JOIN brugere b ON pm.user_id = b.id
        WHERE pm.project_id = '$project_id'
        ORDER BY pm.role, b.brugernavn";
$result = db_select($sql, __FILE__ . " line " . __LINE__);

$members = [];
while ($row = db_fetch_array($result)) {
    $members[] = $row;
}

// Get issue types and priorities for forms
$issue_types = $pm->getIssueTypes();
$issue_priorities = $pm->getIssuePriorities();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project['project_name']) ?> - Project Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .kanban-column {
            background: #f8f9fa;
            border-radius: 8px;
            min-height: 400px;
        }
        .issue-card {
            cursor: pointer;
            transition: transform 0.2s;
        }
        .issue-card:hover {
            transform: translateY(-2px);
        }
        .priority-high { border-left: 4px solid #dc3545; }
        .priority-medium { border-left: 4px solid #ffc107; }
        .priority-low { border-left: 4px solid #28a745; }
        .priority-critical { border-left: 4px solid #6f42c1; }
        .priority-lowest { border-left: 4px solid #6c757d; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-project-diagram"></i> Project Management
            </a>
            <div class="navbar-nav">
                <a class="nav-link" href="projects.php">
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
        <!-- Project Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2>
                    <span class="badge bg-secondary me-2"><?= htmlspecialchars($project['project_key']) ?></span>
                    <?= htmlspecialchars($project['project_name']) ?>
                    <span class="badge bg-<?= $project['status'] == 'active' ? 'success' : ($project['status'] == 'completed' ? 'primary' : 'warning') ?> ms-2">
                        <?= ucfirst($project['status']) ?>
                    </span>
                </h2>
                <p class="text-muted"><?= htmlspecialchars($project['description']) ?></p>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createIssueModal">
                    <i class="fas fa-plus"></i> New Issue
                </button>
                <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#projectSettingsModal">
                    <i class="fas fa-cog"></i> Settings
                </button>
            </div>
        </div>

        <!-- Project Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-primary"><?= $stats['total_issues'] ?></h3>
                        <small class="text-muted">Total Issues</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-success">
                            <?= count(array_filter($stats['by_status'], function($s) { return $s['status_type'] == 'done'; })) ?>
                        </h3>
                        <small class="text-muted">Completed</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-info"><?= round($stats['total_time'] / 60, 1) ?>h</h3>
                        <small class="text-muted">Time Logged</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-warning"><?= count($members) ?></h3>
                        <small class="text-muted">Team Members</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs" id="projectTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="board-tab" data-bs-toggle="tab" data-bs-target="#board" type="button" role="tab">
                    <i class="fas fa-columns"></i> Board
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="issues-tab" data-bs-toggle="tab" data-bs-target="#issues" type="button" role="tab">
                    <i class="fas fa-list"></i> Issues
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="team-tab" data-bs-toggle="tab" data-bs-target="#team" type="button" role="tab">
                    <i class="fas fa-users"></i> Team
                </button>
            </li>
        </ul>

        <div class="tab-content" id="projectTabContent">
            <!-- Kanban Board -->
            <div class="tab-pane fade show active" id="board" role="tabpanel">
                <div class="row mt-3">
                    <?php foreach ($statuses as $status): ?>
                        <div class="col-md-3">
                            <div class="kanban-column p-3">
                                <h6><?= htmlspecialchars($status['name']) ?></h6>
                                <div class="status-issues" data-status="<?= $status['id'] ?>">
                                    <?php foreach ($issues as $issue): ?>
                                        <?php if ($issue['status_id'] == $status['id']): ?>
                                            <div class="card issue-card mb-2 priority-<?= strtolower($issue['priority_name']) ?>" 
                                                 onclick="viewIssue('<?= $issue['issue_key'] ?>')">
                                                <div class="card-body p-2">
                                                    <small class="text-muted"><?= htmlspecialchars($issue['issue_key']) ?></small>
                                                    <p class="mb-1 small"><?= htmlspecialchars($issue['title']) ?></p>
                                                    <?php if ($issue['assignee_name']): ?>
                                                        <small class="text-muted">
                                                            <i class="fas fa-user"></i> <?= htmlspecialchars($issue['assignee_name']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Issues List -->
            <div class="tab-pane fade" id="issues" role="tabpanel">
                <div class="mt-3">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Key</th>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Assignee</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($issues as $issue): ?>
                                    <tr onclick="viewIssue('<?= $issue['issue_key'] ?>')" style="cursor: pointer;">
                                        <td><?= htmlspecialchars($issue['issue_key']) ?></td>
                                        <td><?= htmlspecialchars($issue['title']) ?></td>
                                        <td><?= htmlspecialchars($issue['issue_type']) ?></td>
                                        <td>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($issue['status_name']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $issue['priority_name'] == 'High' ? 'danger' : ($issue['priority_name'] == 'Medium' ? 'warning' : 'success') ?>">
                                                <?= htmlspecialchars($issue['priority_name']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($issue['assignee_name'] ?: 'Unassigned') ?></td>
                                        <td><?= date('M j, Y', strtotime($issue['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Team -->
            <div class="tab-pane fade" id="team" role="tabpanel">
                <div class="mt-3">
                    <div class="row">
                        <?php foreach ($members as $member): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h6><?= htmlspecialchars($member['brugernavn']) ?></h6>
                                        <p class="mb-1 text-muted"><?= htmlspecialchars($member['email']) ?></p>
                                        <span class="badge bg-primary"><?= ucfirst($member['role']) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Issue Modal -->
    <div class="modal fade" id="createIssueModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="createIssueForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Issue</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="project_id" value="<?= $project_id ?>">
                        
                        <div class="mb-3">
                            <label for="issue_title" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="issue_title" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="issue_description" class="form-label">Description</label>
                            <textarea class="form-control" id="issue_description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="issue_type" class="form-label">Type *</label>
                                    <select class="form-select" id="issue_type" name="type_id" required>
                                        <?php foreach ($issue_types as $type): ?>
                                            <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="issue_priority" class="form-label">Priority *</label>
                                    <select class="form-select" id="issue_priority" name="priority_id" required>
                                        <?php foreach ($issue_priorities as $priority): ?>
                                            <option value="<?= $priority['id'] ?>"><?= htmlspecialchars($priority['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="issue_status" class="form-label">Status *</label>
                                    <select class="form-select" id="issue_status" name="status_id" required>
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?= $status['id'] ?>"><?= htmlspecialchars($status['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="issue_assignee" class="form-label">Assignee</label>
                                    <select class="form-select" id="issue_assignee" name="assignee_id">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($members as $member): ?>
                                            <option value="<?= $member['user_id'] ?>"><?= htmlspecialchars($member['brugernavn']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="issue_due_date" class="form-label">Due Date</label>
                                    <input type="date" class="form-control" id="issue_due_date" name="due_date">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Issue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewIssue(issueKey) {
            window.location.href = 'issue_view.php?key=' + issueKey;
        }

        document.getElementById('createIssueForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = {
                action: 'create',
                project_id: parseInt(formData.get('project_id')),
                title: formData.get('title'),
                description: formData.get('description'),
                type_id: parseInt(formData.get('type_id')),
                priority_id: parseInt(formData.get('priority_id')),
                status_id: parseInt(formData.get('status_id')),
                assignee_id: formData.get('assignee_id') ? parseInt(formData.get('assignee_id')) : null,
                due_date: formData.get('due_date')
            };
            
            fetch('api/issues.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error creating issue: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        });
    </script>
</body>
</html>
