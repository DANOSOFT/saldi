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
        
        if ($project_id) {
            $filters = [];
            if (isset($_GET['status_id'])) $filters['status_id'] = (int)$_GET['status_id'];
            if (isset($_GET['assignee_id'])) $filters['assignee_id'] = (int)$_GET['assignee_id'];
            if (isset($_GET['priority_id'])) $filters['priority_id'] = (int)$_GET['priority_id'];
            
            $issues = $pm->getProjectIssues($project_id, $filters);
            echo json_encode(['issues' => $issues]);
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
    echo json_encode(['error' => $e->getMessage()]);
}
?>
