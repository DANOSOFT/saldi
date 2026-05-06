<?php
/**
 * Project Management Dashboard
 */
@session_start();
$s_id=session_id();
// Include system files
require_once '../includes/connect.php';
require_once '../includes/online.php';
require_once '../includes/ProjectManager.php';

// Get user ID (adapt to your session system)
global $bruger_id, $brugernavn;
$user_id = $bruger_id ?? $_SESSION['bruger_id'] ?? 1;

$pm = new ProjectManager();

// Get user's projects
$sql = "SELECT DISTINCT p.*, pm.role 
        FROM projects p 
        LEFT JOIN project_members pm ON p.id = pm.project_id AND pm.user_id = $user_id
        WHERE pm.user_id = $user_id OR p.project_manager_id = $user_id
        ORDER BY p.updated_at DESC";
$result = db_select($sql, __FILE__ . " linje " . __LINE__);

$user_projects = [];
while ($row = db_fetch_array($result)) {
    $user_projects[] = $row;
}

// Get recent activity
$sql = "SELECT ia.*, i.issue_key, i.title as issue_title, p.project_name, b.brugernavn
        FROM issue_activity ia
        JOIN issues i ON ia.issue_id = i.id
        JOIN projects p ON i.project_id = p.id
        LEFT JOIN brugere b ON ia.user_id = b.id
        JOIN project_members pm ON p.id = pm.project_id AND pm.user_id = $user_id
        ORDER BY ia.created_at DESC
        LIMIT 10";
$result = db_select($sql, __FILE__ . " line " . __LINE__);

$recent_activity = [];
while ($row = db_fetch_array($result)) {
    $recent_activity[] = $row;
}

// Get assigned issues - use getUserIssues instead of getProjectIssues
$assigned_issues = $pm->getUserIssues($user_id, 10);

// Get notifications
$notifications = $pm->getUserNotifications($user_id, 5);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Management Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .dashboard-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .project-card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        .project-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .activity-item {
            border-left: 3px solid #007bff;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .notification-item {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .notification-item.unread {
            background: #e3f2fd;
            border-left: 3px solid #2196f3;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-project-diagram"></i> Project Management
            </a>
            <div class="navbar-nav ms-auto">
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
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Welcome Section -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card dashboard-card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-folder fa-2x mb-2"></i>
                                <h3><?= count($user_projects) ?></h3>
                                <p class="mb-0">My Projects</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                            <div class="card-body text-center">
                                <i class="fas fa-tasks fa-2x mb-2"></i>
                                <h3><?= count($assigned_issues) ?></h3>
                                <p class="mb-0">Assigned Issues</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                            <div class="card-body text-center">
                                <i class="fas fa-bell fa-2x mb-2"></i>
                                <h3><?= count(array_filter($notifications, function($n) { return !$n['is_read']; })) ?></h3>
                                <p class="mb-0">Notifications</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card dashboard-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-2x mb-2"></i>
                                <h3>100%</h3>
                                <p class="mb-0">Productivity</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Projects -->
                <div class="card dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-folder"></i> My Projects</h5>
                        <a href="project_create.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> New Project
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($user_projects)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No projects yet. <a href="project_create.php">Create your first project</a></p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($user_projects as $project): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card project-card h-100" onclick="window.location.href='project_view.php?id=<?= $project['id'] ?>'">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title"><?= htmlspecialchars($project['project_name']) ?></h6>
                                                    <span class="badge bg-<?= $project['status'] == 'active' ? 'success' : ($project['status'] == 'completed' ? 'primary' : 'warning') ?>">
                                                        <?= ucfirst($project['status']) ?>
                                                    </span>
                                                </div>
                                                <p class="card-text text-muted small"><?= htmlspecialchars(substr($project['description'], 0, 100)) ?>...</p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="fas fa-code"></i> <?= htmlspecialchars($project['project_key']) ?>
                                                    </small>
                                                    <small class="text-muted">
                                                        Role: <?= ucfirst($project['role'] ?: 'Manager') ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_activity)): ?>
                            <p class="text-muted">No recent activity</p>
                        <?php else: ?>
                            <?php foreach ($recent_activity as $activity): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= htmlspecialchars($activity['brugernavn']) ?></strong>
                                        <small class="text-muted"><?= date('M j, H:i', strtotime($activity['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-1">
                                        <?= htmlspecialchars($activity['action']) ?> 
                                        <a href="issue_view.php?key=<?= $activity['issue_key'] ?>"><?= htmlspecialchars($activity['issue_key']) ?></a>
                                        in <strong><?= htmlspecialchars($activity['project_name']) ?></strong>
                                    </p>
                                    <?php if ($activity['field_name']): ?>
                                        <small class="text-muted">
                                            Changed <?= htmlspecialchars($activity['field_name']) ?>: 
                                            "<?= htmlspecialchars($activity['old_value']) ?>" → "<?= htmlspecialchars($activity['new_value']) ?>"
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Assigned Issues -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-check"></i> Assigned to Me</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($assigned_issues)): ?>
                            <p class="text-muted">No issues assigned</p>
                        <?php else: ?>
                            <?php foreach (array_slice($assigned_issues, 0, 5) as $issue): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                                    <div>
                                        <a href="issue_view.php?key=<?= $issue['issue_key'] ?>" class="text-decoration-none">
                                            <strong><?= htmlspecialchars($issue['issue_key']) ?></strong>
                                        </a>
                                        <br>
                                        <small><?= htmlspecialchars(substr($issue['title'], 0, 40)) ?>...</small>
                                    </div>
                                    <span class="badge bg-<?= $issue['priority_name'] == 'High' ? 'danger' : ($issue['priority_name'] == 'Medium' ? 'warning' : 'success') ?>">
                                        <?= htmlspecialchars($issue['priority_name']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($assigned_issues) > 5): ?>
                                <div class="text-center">
                                    <a href="issues.php?assignee=me" class="btn btn-sm btn-outline-primary">
                                        View All (<?= count($assigned_issues) ?>)
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="card dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-bell"></i> Notifications</h5>
                        <button class="btn btn-sm btn-outline-secondary" onclick="markAllRead()">
                            Mark all read
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($notifications)): ?>
                            <p class="text-muted">No notifications</p>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?= !$notification['is_read'] ? 'unread' : '' ?>">
                                    <div class="d-flex justify-content-between">
                                        <strong><?= htmlspecialchars($notification['title']) ?></strong>
                                        <small class="text-muted"><?= date('M j', strtotime($notification['created_at'])) ?></small>
                                    </div>
                                    <p class="mb-1 small"><?= htmlspecialchars($notification['message']) ?></p>
                                    <?php if (!$notification['is_read']): ?>
                                        <button class="btn btn-xs btn-outline-primary" onclick="markRead(<?= $notification['id'] ?>)">
                                            Mark read
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-lightning-bolt"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="project_create.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create Project
                            </a>
                            <a href="issue_create.php" class="btn btn-success">
                                <i class="fas fa-bug"></i> Report Issue
                            </a>
                            <a href="time_log.php" class="btn btn-info">
                                <i class="fas fa-clock"></i> Log Time
                            </a>
                            <a href="reports.php" class="btn btn-warning">
                                <i class="fas fa-chart-bar"></i> View Reports
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function markRead(notificationId) {
            fetch('api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    id: notificationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }

        function markAllRead() {
            fetch('api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_all_read'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }

        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            fetch('api/notifications.php?count_unread=1')
                .then(response => response.json())
                .then(data => {
                    if (data.unread_count > 0) {
                        document.title = `(${data.unread_count}) Project Management Dashboard`;
                    }
                });
        }, 30000);
    </script>
</body>
</html>
