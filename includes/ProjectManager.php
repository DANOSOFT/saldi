<?php
/**
 * ProjectManager Class
 * Core functionality for the Project Management System
 * Compatible with existing ERP multi-tenant architecture
 */

// This file is included after connect.php and online.php are already loaded
// So database connection and user session are available

class ProjectManager {
    
    private $user_id;
    private $username;
    
    public function __construct() {
        // Get user from ERP global variables (set by online.php)
        global $bruger_id, $brugernavn;
        
        $this->user_id = $bruger_id ?? $_SESSION['bruger_id'] ?? null;
        $this->username = $brugernavn ?? $_SESSION['brugernavn'] ?? null;
        
        if (!$this->user_id) {
            throw new Exception('User not authenticated');
        }
    }
    
    /**
     * Get current user ID
     */
    public function getCurrentUserId() {
        return $this->user_id;
    }
    
    /**
     * Get current username
     */
    public function getCurrentUsername() {
        return $this->username;
    }
    
    /**
     * Create a new project
     */
    public function createProject($data) {
        $name = db_escape_string($data['name']);
        $key = strtoupper(trim($data['key']));
        $description = db_escape_string($data['description']);
        
        // Validate project key
        if (!preg_match('/^[A-Z0-9]{2,10}$/', $key)) {
            throw new Exception('Project key must be 2-10 uppercase letters/numbers');
        }
        
        // Check if key already exists
        $existing = db_fetch_array(db_select("SELECT id FROM projects WHERE project_key = '$key'", __FILE__ . " linje " . __LINE__));
        if ($existing) {
            throw new Exception('Project key already exists');
        }
        
        $sql = "INSERT INTO projects (project_name, project_key, description, project_manager_id, start_date, end_date, budget, created_by) 
                VALUES ('$name', '$key', '$description', " . (int)$data['manager_id'] . ", " . 
                ($data['start_date'] ? "'" . $data['start_date'] . "'" : "NULL") . ", " .
                ($data['end_date'] ? "'" . $data['end_date'] . "'" : "NULL") . ", " .
                ($data['budget'] ? $data['budget'] : "NULL") . ", " . (int)$data['created_by'] . ")";
        
        $result = db_modify($sql, __FILE__ . " linje " . __LINE__);
        if ($result) {
            $project_id = db_insert_id();
            
            // Add creator as project member with admin role
            $this->addProjectMember($project_id, $data['created_by'], 'admin');
            
            // Create default issue statuses for the project
            $this->createDefaultIssueStatuses($project_id);
            
            return $project_id;
        }
        
        return false;
    }
    
    /**
     * Create default issue statuses for a new project
     */
    private function createDefaultIssueStatuses($project_id) {
        $statuses = [
            ['name' => 'To Do', 'type' => 'todo', 'color' => '#6c757d', 'position' => 1],
            ['name' => 'In Progress', 'type' => 'in_progress', 'color' => '#007bff', 'position' => 2],
            ['name' => 'Review', 'type' => 'review', 'color' => '#ffc107', 'position' => 3],
            ['name' => 'Done', 'type' => 'done', 'color' => '#28a745', 'position' => 4]
        ];
        
        foreach ($statuses as $status) {
            db_modify("INSERT INTO issue_statuses (name, status_type, project_id, position, color) 
                      VALUES ('{$status['name']}', '{$status['type']}', $project_id, {$status['position']}, '{$status['color']}')", 
                      __FILE__ . " linje " . __LINE__);
        }
    }
    
    /**
     * Add a member to a project
     */
    public function addProjectMember($project_id, $user_id, $role = 'member') {
        // Check if already exists
        $existing = db_fetch_array(db_select("SELECT id FROM project_members WHERE project_id = $project_id AND user_id = $user_id", __FILE__ . " linje " . __LINE__));
        if ($existing) {
            // Update role
            return db_modify("UPDATE project_members SET role = '$role' WHERE project_id = $project_id AND user_id = $user_id", __FILE__ . " linje " . __LINE__);
        } else {
            // Insert new
            return db_modify("INSERT INTO project_members (project_id, user_id, role) VALUES ($project_id, $user_id, '$role')", __FILE__ . " linje " . __LINE__);
        }
    }
    
    /**
     * Create a new issue
     */
    public function createIssue($data) {
        $title = db_escape_string($data['title']);
        $description = db_escape_string($data['description']);
        
        // Get project key
        $project = db_fetch_array(db_select("SELECT project_key FROM projects WHERE id = " . (int)$data['project_id'], __FILE__ . " linje " . __LINE__));
        if (!$project) {
            throw new Exception('Project not found');
        }
        
        // Generate issue key
        $issue_key = $this->generateIssueKey($project['project_key']);
        
        // Get first status for the project
        $status = db_fetch_array(db_select("SELECT id FROM issue_statuses WHERE project_id = " . (int)$data['project_id'] . " ORDER BY position ASC LIMIT 1", __FILE__ . " linje " . __LINE__));
        $status_id = $status ? $status['id'] : (int)$data['status_id'];
        
        $sql = "INSERT INTO issues (issue_key, project_id, parent_id, title, description, issue_type_id, priority_id, status_id, assignee_id, reporter_id, story_points, original_estimate, due_date, created_by) 
                VALUES ('$issue_key', " . (int)$data['project_id'] . ", " . 
                ($data['parent_id'] ? (int)$data['parent_id'] : "NULL") . ", '$title', '$description', " . 
                (int)$data['type_id'] . ", " . (int)$data['priority_id'] . ", $status_id, " . 
                ($data['assignee_id'] ? (int)$data['assignee_id'] : "NULL") . ", " . (int)$data['reporter_id'] . ", " .
                ($data['story_points'] ? (int)$data['story_points'] : "NULL") . ", " .
                ($data['estimate'] ? (int)$data['estimate'] : "NULL") . ", " .
                ($data['due_date'] ? "'" . $data['due_date'] . "'" : "NULL") . ", " . (int)$data['created_by'] . ")";
        
        $result = db_modify($sql, __FILE__ . " linje " . __LINE__);
        if ($result) {
            $issue_id = db_insert_id();
            
            // Log activity
            $this->logIssueActivity($issue_id, 'created', null, null, $title);
            
            // Notify assignee if different from creator
            if ($data['assignee_id'] && $data['assignee_id'] != $data['created_by']) {
                $this->createNotification($data['assignee_id'], 'issue_assigned', "Issue assigned: $issue_key", $issue_id, $data['project_id']);
            }
            
            return $issue_id;
        }
        
        return false;
    }
    
    /**
     * Generate unique issue key
     */
    private function generateIssueKey($project_key) {
        // Get the highest existing number for this project
        $result = db_fetch_array(db_select("
            SELECT COALESCE(MAX(CAST(SUBSTRING(issue_key FROM " . (strlen($project_key) + 2) . ") AS INTEGER)), 0) + 1 as next_number
            FROM issues 
            WHERE issue_key LIKE '$project_key-%'", __FILE__ . " linje " . __LINE__));
        
        $next_number = $result['next_number'];
        return $project_key . '-' . $next_number;
    }
    
    /**
     * Get user's projects
     */
    public function getUserProjects($user_id = null) {
        if (!$user_id) $user_id = $this->user_id;
        
        $sql = "SELECT p.*, u.navn as manager_name 
                FROM projects p 
                LEFT JOIN brugere u ON p.project_manager_id = u.id 
                WHERE p.id IN (SELECT project_id FROM project_members WHERE user_id = $user_id) 
                ORDER BY p.updated_at DESC";
        
        $projects = [];
        $query = db_select($sql, __FILE__ . " linje " . __LINE__);
        while ($row = db_fetch_array($query)) {
            $projects[] = $row;
        }
        
        return $projects;
    }
    
    /**
     * Get user's assigned issues
     */
    public function getUserIssues($user_id = null, $limit = 10) {
        if (!$user_id) $user_id = $this->user_id;
        
        $sql = "SELECT i.*, p.project_name, p.project_key, t.name as type_name, pr.name as priority_name, s.name as status_name 
                FROM issues i 
                JOIN projects p ON i.project_id = p.id 
                LEFT JOIN issue_types t ON i.issue_type_id = t.id 
                LEFT JOIN issue_priorities pr ON i.priority_id = pr.id 
                LEFT JOIN issue_statuses s ON i.status_id = s.id 
                WHERE i.assignee_id = $user_id AND s.status_type != 'done' 
                ORDER BY i.updated_at DESC 
                LIMIT $limit";
        
        $issues = [];
        $query = db_select($sql, __FILE__ . " linje " . __LINE__);
        while ($row = db_fetch_array($query)) {
            $issues[] = $row;
        }
        
        return $issues;
    }
    
    /**
     * Get user notifications
     */
    public function getUserNotifications($user_id = null, $limit = 10) {
        if (!$user_id) $user_id = $this->user_id;
        
        $sql = "SELECT * FROM project_notifications 
                WHERE user_id = $user_id 
                ORDER BY created_at DESC 
                LIMIT $limit";
        
        $notifications = [];
        try {
            $query = db_select($sql, __FILE__ . " linje " . __LINE__);
            if ($query) {
                while ($row = db_fetch_array($query)) {
                    $notifications[] = $row;
                }
            }
        } catch (Exception $e) {
            // If table doesn't exist, return empty array
            return [];
        }
        
        return $notifications;
    }
    
    /**
     * Mark notification as read
     */
    public function markNotificationRead($notification_id) {
        return db_modify("UPDATE project_notifications SET is_read = TRUE WHERE id = $notification_id AND user_id = {$this->user_id}", __FILE__ . " linje " . __LINE__);
    }
    
    /**
     * Create a notification
     */
    public function createNotification($user_id, $type, $title, $issue_id = null, $project_id = null, $message = '') {
        $title = db_escape_string($title);
        $message = db_escape_string($message);
        
        $sql = "INSERT INTO project_notifications (user_id, type, title, message, related_issue_id, related_project_id) 
                VALUES ($user_id, '$type', '$title', '$message', " . 
                ($issue_id ? $issue_id : "NULL") . ", " . 
                ($project_id ? $project_id : "NULL") . ")";
        
        return db_modify($sql, __FILE__ . " linje " . __LINE__);
    }
    
    /**
     * Log issue activity
     */
    public function logIssueActivity($issue_id, $action, $field_name = null, $old_value = null, $new_value = null) {
        $action = db_escape_string($action);
        $field_name = $field_name ? "'" . db_escape_string($field_name) . "'" : "NULL";
        $old_value = $old_value ? "'" . db_escape_string($old_value) . "'" : "NULL";
        $new_value = $new_value ? "'" . db_escape_string($new_value) . "'" : "NULL";
        
        $sql = "INSERT INTO issue_activity (issue_id, user_id, action, field_name, old_value, new_value) 
                VALUES ($issue_id, {$this->user_id}, '$action', $field_name, $old_value, $new_value)";
        
        return db_modify($sql, __FILE__ . " linje " . __LINE__);
    }
    
    /**
     * Get project details
     */
    public function getProject($project_id) {
        $sql = "SELECT p.*, u.navn as manager_name 
                FROM projects p 
                LEFT JOIN brugere u ON p.project_manager_id = u.id 
                WHERE p.id = $project_id";
        
        return db_fetch_array(db_select($sql, __FILE__ . " linje " . __LINE__));
    }
    
    /**
     * Get project issues
     */
    public function getProjectIssues($project_id, $filters = []) {
        $where_conditions = ["i.project_id = $project_id"];
        
        if (isset($filters['assignee_id'])) {
            $where_conditions[] = "i.assignee_id = " . $filters['assignee_id'];
        }
        
        if (isset($filters['status_type'])) {
            $where_conditions[] = "s.status_type = '" . $filters['status_type'] . "'";
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT i.*, t.name as type_name, pr.name as priority_name, s.name as status_name, s.status_type, 
                       u1.navn as assignee_name, u2.navn as reporter_name 
                FROM issues i 
                LEFT JOIN issue_types t ON i.issue_type_id = t.id 
                LEFT JOIN issue_priorities pr ON i.priority_id = pr.id 
                LEFT JOIN issue_statuses s ON i.status_id = s.id 
                LEFT JOIN brugere u1 ON i.assignee_id = u1.id 
                LEFT JOIN brugere u2 ON i.reporter_id = u2.id 
                WHERE $where_clause
                ORDER BY i.created_at DESC";
        
        $issues = [];
        try {
            $query = db_select($sql, __FILE__ . " linje " . __LINE__);
            if ($query) {
                while ($row = db_fetch_array($query)) {
                    $issues[] = $row;
                }
            }
        } catch (Exception $e) {
            // If tables don't exist, return empty array
            return [];
        }
        
        return $issues;
    }
    
    /**
     * Get project members
     */
    public function getProjectMembers($project_id) {
        $sql = "SELECT pm.*, u.navn as user_name, u.brugernavn as username 
                FROM project_members pm 
                JOIN brugere u ON pm.user_id = u.id 
                WHERE pm.project_id = $project_id 
                ORDER BY pm.role, u.navn";
        
        $members = [];
        $query = db_select($sql, __FILE__ . " linje " . __LINE__);
        while ($row = db_fetch_array($query)) {
            $members[] = $row;
        }
        
        return $members;
    }
    
    /**
     * Get issue statuses for project
     */
    public function getProjectStatuses($project_id) {
        $sql = "SELECT * FROM issue_statuses 
                WHERE project_id = $project_id AND is_active = TRUE 
                ORDER BY position";
        
        $statuses = [];
        $query = db_select($sql, __FILE__ . " linje " . __LINE__);
        while ($row = db_fetch_array($query)) {
            $statuses[] = $row;
        }
        
        return $statuses;
    }
    
    /**
     * Get all users for assignment
     */
    public function getAllUsers() {
        $sql = "SELECT id, navn as name, brugernavn as username FROM brugere WHERE lukket != 1 ORDER BY navn";
        
        $users = [];
        $query = db_select($sql, __FILE__ . " linje " . __LINE__);
        while ($row = db_fetch_array($query)) {
            $users[] = $row;
        }
        
        return $users;
    }
    
    /**
     * Get issue types
     */
    public function getIssueTypes() {
        $sql = "SELECT * FROM issue_types WHERE is_active = TRUE ORDER BY name";
        
        $types = [];
        $query = db_select($sql, __FILE__ . " linje " . __LINE__);
        while ($row = db_fetch_array($query)) {
            $types[] = $row;
        }
        
        return $types;
    }
    
    /**
     * Get issue priorities
     */
    public function getIssuePriorities() {
        $sql = "SELECT * FROM issue_priorities WHERE is_active = TRUE ORDER BY level";
        
        $priorities = [];
        $query = db_select($sql, __FILE__ . " linje " . __LINE__);
        while ($row = db_fetch_array($query)) {
            $priorities[] = $row;
        }
        
        return $priorities;
    }
    
    /**
     * Update issue status
     */
    public function updateIssueStatus($issue_id, $new_status_id) {
        // Get current status
        $current = db_fetch_array(db_select("SELECT status_id, issue_key FROM issues WHERE id = $issue_id", __FILE__ . " linje " . __LINE__));
        if (!$current) return false;
        
        if ($current['status_id'] != $new_status_id) {
            $result = db_modify("UPDATE issues SET status_id = $new_status_id, updated_at = CURRENT_TIMESTAMP WHERE id = $issue_id", __FILE__ . " linje " . __LINE__);
            
            if ($result) {
                // Get status names for activity log
                $old_status = db_fetch_array(db_select("SELECT name FROM issue_statuses WHERE id = {$current['status_id']}", __FILE__ . " linje " . __LINE__));
                $new_status = db_fetch_array(db_select("SELECT name FROM issue_statuses WHERE id = $new_status_id", __FILE__ . " linje " . __LINE__));
                
                $this->logIssueActivity($issue_id, 'status_changed', 'status', $old_status['name'], $new_status['name']);
            }
            
            return $result;
        }
        
        return true;
    }
    
    /**
     * Check if user has access to project
     */
    public function hasProjectAccess($project_id, $user_id = null) {
        if (!$user_id) $user_id = $this->user_id;
        
        $result = db_fetch_array(db_select("SELECT id FROM project_members WHERE project_id = $project_id AND user_id = $user_id", __FILE__ . " linje " . __LINE__));
        return (bool)$result;
    }
    
    /**
     * Update an issue
     */
    public function updateIssue($issue_id, $data, $user_id) {
        $title = db_escape_string($data['title']);
        $description = db_escape_string($data['description']);
        
        $sql = "UPDATE issues SET title = '$title', description = '$description', issue_type_id = " . (int)$data['type_id'] . ", 
                status_id = " . (int)$data['status_id'] . ", priority_id = " . (int)$data['priority_id'] . ", 
                assignee_id = " . ($data['assignee_id'] ? (int)$data['assignee_id'] : "NULL") . ", 
                story_points = " . ($data['story_points'] ? (int)$data['story_points'] : "NULL") . ", 
                original_estimate = " . ($data['estimate'] ? (int)$data['estimate'] : "NULL") . ", 
                remaining_estimate = " . ($data['remaining_estimate'] ? (int)$data['remaining_estimate'] : "NULL") . ", 
                due_date = " . ($data['due_date'] ? "'" . $data['due_date'] . "'" : "NULL") . ", 
                updated_by = $user_id, updated_at = CURRENT_TIMESTAMP 
                WHERE id = $issue_id";
        
        $result = db_modify($sql, __FILE__ . " linje " . __LINE__);
        
        return $result;
    }
    
    /**
     * Get issue details with related data
     */
    public function getIssue($issue_id) {
        $sql = "SELECT i.*, p.project_name, p.project_key,
                it.name as issue_type, it.color as type_color,
                s.name as status_name, s.color as status_color,
                pr.name as priority_name, pr.color as priority_color,
                assignee.brugernavn as assignee_name,
                reporter.brugernavn as reporter_name
                FROM issues i
                JOIN projects p ON i.project_id = p.id
                LEFT JOIN issue_types it ON i.issue_type_id = it.id
                LEFT JOIN issue_statuses s ON i.status_id = s.id
                LEFT JOIN issue_priorities pr ON i.priority_id = pr.id
                LEFT JOIN brugere assignee ON i.assignee_id = assignee.id
                LEFT JOIN brugere reporter ON i.reporter_id = reporter.id
                WHERE i.id = $issue_id";
        
        $result = db_select($sql, __FILE__ . " linje " . __LINE__);
        return db_fetch_array($result);
    }
    
    /**
     * Add comment to issue
     */
    public function addComment($issue_id, $author_id, $content, $is_internal = false) {
        $content = db_escape_string($content);
        $internal_flag = $is_internal ? 'TRUE' : 'FALSE';
        
        $sql = "INSERT INTO issue_comments (issue_id, author_id, content, is_internal) 
                VALUES ($issue_id, $author_id, '$content', $internal_flag)";
        
        $comment_id = db_modify($sql, __FILE__ . " linje " . __LINE__);
        
        // Log activity
        $this->logIssueActivity($issue_id, 'commented', null, null, 'Added comment');
        
        return $comment_id;
    }
    
    /**
     * Log work time - overloaded method for API compatibility
     */
    public function logWork($issue_id, $user_id, $time_spent, $description, $work_date = null) {
        if (!$work_date) {
            $work_date = date('Y-m-d');
        }
        
        $description = db_escape_string($description);
        
        $sql = "INSERT INTO work_logs (issue_id, user_id, time_spent, work_date, description) 
                VALUES ($issue_id, $user_id, $time_spent, '$work_date', '$description')";
        
        $log_id = db_modify($sql, __FILE__ . " linje " . __LINE__);
        
        // Update issue time spent
        if ($log_id) {
            db_modify("UPDATE issues SET time_spent = (SELECT SUM(time_spent) FROM work_logs WHERE issue_id = $issue_id) WHERE id = $issue_id", __FILE__ . " linje " . __LINE__);
            
            // Log activity
            $hours = round($time_spent / 60, 2);
            $this->logIssueActivity($issue_id, 'work_logged', null, null, "Logged {$hours}h of work");
        }
        
        return $log_id;
    }
    
    /**
     * Get project statistics
     */
    public function getProjectStats($project_id) {
        $stats = [];
        
        // Total issues
        $sql = "SELECT COUNT(*) as total FROM issues WHERE project_id = $project_id";
        $result = db_select($sql, __FILE__ . " linje " . __LINE__);
        $stats['total_issues'] = db_fetch_array($result)['total'];
        
        // Issues by status
        $sql = "SELECT s.name, s.status_type, COUNT(*) as count 
                FROM issues i 
                JOIN issue_statuses s ON i.status_id = s.id 
                WHERE i.project_id = $project_id 
                GROUP BY s.id, s.name, s.status_type";
        $result = db_select($sql, __FILE__ . " linje " . __LINE__);
        
        $stats['by_status'] = [];
        while ($row = db_fetch_array($result)) {
            $stats['by_status'][] = $row;
        }
        
        // Total time logged
        $sql = "SELECT SUM(wl.time_spent) as total_time 
                FROM work_logs wl 
                JOIN issues i ON wl.issue_id = i.id 
                WHERE i.project_id = $project_id";
        $result = db_select($sql, __FILE__ . " linje " . __LINE__);
        $stats['total_time'] = db_fetch_array($result)['total_time'] ?: 0;
        
        return $stats;
    }
}

// Helper functions for the project management system

/**
 * Format time duration in minutes to human readable format
 */
function formatTimeDuration($minutes) {
    if ($minutes < 60) {
        return $minutes . 'm';
    } else {
        $hours = floor($minutes / 60);
        $remaining_minutes = $minutes % 60;
        if ($remaining_minutes > 0) {
            return $hours . 'h ' . $remaining_minutes . 'm';
        } else {
            return $hours . 'h';
        }
    }
}

/**
 * Get time ago string
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}

/**
 * Escape HTML for safe output
 */
function escapeHtml($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

?>
