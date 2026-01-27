<?php
/**
 * Issues API
 * Handle issue operations via AJAX
 */

header('Content-Type: application/json');
@session_start();
$s_id=session_id();
require_once '../../includes/connect.php';
require_once '../../includes/online.php';
require_once '../../includes/ProjectManager.php';

session_start();
$user_id = isset($_SESSION['bruger_id']) ? $_SESSION['bruger_id'] : (isset($bruger_id) ? $bruger_id : 1);

$pm = new ProjectManager();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
        
        if (isset($_GET['search'])) {
            // Search issues
            $search = $_GET['search'];
            $search_escaped = db_escape_string($search);
            $sql = "SELECT i.*, p.project_name, p.project_key 
                    FROM issues i 
                    JOIN projects p ON i.project_id = p.id 
                    WHERE (i.title ILIKE '%$search_escaped%' OR i.issue_key ILIKE '%$search_escaped%')
                    ORDER BY i.created_at DESC
                    LIMIT 10";
            $result = db_select($sql, __FILE__ . " line " . __LINE__);
            
            $issues = [];
            while ($row = db_fetch_array($result)) {
                $issues[] = $row;
            }
            
            echo json_encode(['issues' => $issues]);
            exit;
        }
        
        // Check if project_id is valid and not zero - ADD DEBUGGING
        error_log("Issues API: project_id received: " . var_export($project_id, true));
        error_log("Issues API: GET parameters: " . var_export($_GET, true));
        
        if ($project_id && $project_id > 0) {
            // Build the WHERE clause properly
            $where_conditions = ["i.project_id = " . (int)$project_id];
            
            if (isset($_GET['status_id']) && $_GET['status_id'] !== '' && $_GET['status_id'] !== '0') {
                $status_id = (int)$_GET['status_id'];
                $where_conditions[] = "i.status_id = " . $status_id;
            }
            
            if (isset($_GET['assignee_id']) && $_GET['assignee_id'] !== '') {
                $assignee_id = (int)$_GET['assignee_id'];
                if ($assignee_id == -1) {
                    $where_conditions[] = "i.assignee_id IS NULL";
                } else if ($assignee_id > 0) {
                    $where_conditions[] = "i.assignee_id = " . $assignee_id;
                }
            }
            
            if (isset($_GET['priority_id']) && $_GET['priority_id'] !== '' && $_GET['priority_id'] !== '0') {
                $priority_id = (int)$_GET['priority_id'];
                $where_conditions[] = "i.priority_id = " . $priority_id;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            // Add debugging for the SQL query
            error_log("Issues API: WHERE clause: " . $where_clause);
            
            $sql = "SELECT i.*, 
                           it.name as issue_type,
                           ip.name as priority_name,
                           ist.name as status_name,
                           b.brugernavn as assignee_name
                    FROM issues i
                    LEFT JOIN issue_types it ON i.issue_type_id = it.id
                    LEFT JOIN issue_priorities ip ON i.priority_id = ip.id
                    LEFT JOIN issue_statuses ist ON i.status_id = ist.id
                    LEFT JOIN brugere b ON i.assignee_id = b.id
                    WHERE $where_clause
                    ORDER BY i.created_at DESC";
            
            error_log("Issues API: Final SQL: " . $sql);
            
            $result = db_select($sql, __FILE__ . " line " . __LINE__);
            
            $issues = [];
            while ($row = db_fetch_array($result)) {
                $issues[] = $row;
            }
            
            echo json_encode(['issues' => $issues]);
            exit;
        } else {
            // If no valid project_id specified, return empty array with debug info
            error_log("Issues API: No valid project_id, returning empty array. Received: " . var_export($project_id, true));
            echo json_encode(['issues' => [], 'debug' => 'No valid project_id']);
            exit;
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $issue_data = [
                    'project_id' => (int)$input['project_id'],
                    'parent_id' => $input['parent_id'] ? (int)$input['parent_id'] : null,
                    'title' => $input['title'],
                    'description' => $input['description'] ?? '',
                    'type_id' => (int)$input['type_id'],
                    'status_id' => (int)$input['status_id'],
                    'priority_id' => (int)$input['priority_id'],
                    'assignee_id' => $input['assignee_id'] ? (int)$input['assignee_id'] : null,
                    'reporter_id' => $user_id,
                    'story_points' => $input['story_points'] ? (int)$input['story_points'] : null,
                    'estimate' => $input['estimate'] ? (int)$input['estimate'] : null,
                    'due_date' => $input['due_date'] ?: null,
                    'created_by' => $user_id
                ];
                
                $issue_id = $pm->createIssue($issue_data);
                echo json_encode(['success' => true, 'issue_id' => $issue_id]);
                break;
                
            case 'update':
                $issue_id = (int)$input['issue_id'];
                $issue_data = [
                    'title' => $input['title'],
                    'description' => $input['description'] ?? '',
                    'type_id' => (int)$input['type_id'],
                    'status_id' => (int)$input['status_id'],
                    'priority_id' => (int)$input['priority_id'],
                    'assignee_id' => $input['assignee_id'] ? (int)$input['assignee_id'] : null,
                    'story_points' => $input['story_points'] ? (int)$input['story_points'] : null,
                    'estimate' => $input['estimate'] ? (int)$input['estimate'] : null,
                    'remaining_estimate' => $input['remaining_estimate'] ? (int)$input['remaining_estimate'] : null,
                    'due_date' => $input['due_date'] ?: null
                ];
                
                $result = $pm->updateIssue($issue_id, $issue_data, $user_id);
                echo json_encode(['success' => $result]);
                break;
                
            case 'add_comment':
                $issue_id = (int)$input['issue_id'];
                $content = $input['content'];
                $is_internal = $input['is_internal'] ?? false;
                
                $comment_id = $pm->addComment($issue_id, $user_id, $content, $is_internal);
                echo json_encode(['success' => true, 'comment_id' => $comment_id]);
                break;
                
            case 'log_work':
                $issue_id = (int)$input['issue_id'];
                $time_spent = (int)$input['time_spent'];
                $description = $input['description'] ?? '';
                $work_date = $input['work_date'] ?? date('Y-m-d');
                
                $log_id = $pm->logWork($issue_id, $user_id, $time_spent, $description, $work_date);
                echo json_encode(['success' => true, 'log_id' => $log_id]);
                break;
                
            default:
                echo json_encode(['error' => 'Invalid action']);
        }
        exit;
    }
    
} catch (Exception $e) {
    error_log("Issues API Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?>
