<?php
/**
 * Notifications API
 * Handle notification operations via AJAX
 */

header('Content-Type: application/json');
require_once '../../includes/connect.php';
require_once '../../includes/online.php';
require_once '../../includes/ProjectManager.php';

session_start();
$user_id = isset($_SESSION['bruger_id']) ? $_SESSION['bruger_id'] : (isset($bruger_id) ? $bruger_id : 1);

$pm = new ProjectManager();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['count_unread'])) {
            // Get unread notification count
            $sql = "SELECT COUNT(*) as count FROM project_notifications WHERE user_id = '$user_id' AND is_read = FALSE";
            $result = db_select($sql, __FILE__ . " line " . __LINE__);
            $row = db_fetch_array($result);
            
            echo json_encode(['unread_count' => (int)$row['count']]);
            exit;
        }
        
        // Get notifications
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $notifications = $pm->getUserNotifications($user_id, $limit);
        
        echo json_encode(['notifications' => $notifications]);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'mark_read':
                $notification_id = (int)$input['id'];
                $result = $pm->markNotificationRead($notification_id);
                echo json_encode(['success' => $result]);
                break;
                
            case 'mark_all_read':
                $sql = "UPDATE project_notifications SET is_read = TRUE WHERE user_id = ?";
                $result = db_modify($sql, __FILE__ . " line " . __LINE__, $user_id);
                echo json_encode(['success' => $result]);
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
