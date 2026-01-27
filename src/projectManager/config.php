<?php
/**
 * Project Management Configuration
 * Customize settings for the project management system
 */

// System Configuration
define('PM_VERSION', '1.0.0');
define('PM_TITLE', 'Project Management System');

// Database Settings (inherits from main ERP system)
// Uses the same database connection as the main system

// User Authentication
// Set to true if you want to use the ERP system's authentication
define('PM_USE_ERP_AUTH', true);

// Session variable names (adjust to match your ERP system)
define('PM_SESSION_USER_ID', 'bruger_id');
define('PM_SESSION_USERNAME', 'brugernavn');

// Default Settings
define('PM_DEFAULT_TIMEZONE', 'Europe/Copenhagen');
define('PM_DATE_FORMAT', 'Y-m-d');
define('PM_DATETIME_FORMAT', 'Y-m-d H:i:s');

// File Upload Settings
define('PM_UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('PM_UPLOAD_PATH', '../temp/uploads/');
define('PM_ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt']);

// Email Notifications (optional)
define('PM_EMAIL_ENABLED', false);
define('PM_EMAIL_FROM', 'noreply@yourcompany.com');
define('PM_EMAIL_FROM_NAME', 'Project Management System');

// Issue Key Settings
define('PM_ISSUE_KEY_FORMAT', '{PROJECT_KEY}-{NUMBER}');
define('PM_PROJECT_KEY_MIN_LENGTH', 2);
define('PM_PROJECT_KEY_MAX_LENGTH', 10);

// Pagination
define('PM_ITEMS_PER_PAGE', 25);

// Time Tracking
define('PM_TIME_UNIT', 'hours'); // 'hours' or 'minutes'
define('PM_DEFAULT_WORK_HOURS_PER_DAY', 8);

// Security Settings
define('PM_ENABLE_CSRF_PROTECTION', true);
define('PM_SESSION_TIMEOUT', 3600); // 1 hour

// Feature Flags
define('PM_ENABLE_SPRINTS', true);
define('PM_ENABLE_TIME_TRACKING', true);
define('PM_ENABLE_ATTACHMENTS', true);
define('PM_ENABLE_CUSTOM_FIELDS', true);
define('PM_ENABLE_TEAMS', true);
define('PM_ENABLE_BOARDS', true);

// UI Settings
define('PM_THEME_COLOR', '#007bff');
define('PM_ITEMS_PER_ROW', 3);
define('PM_ENABLE_DARK_MODE', false);

// Integration Settings
define('PM_ERP_BASE_URL', '../');
define('PM_ERP_USER_PROFILE_URL', '../admin/admin_brugere.php?bruger_id=');

// API Settings
define('PM_API_RATE_LIMIT', 100); // requests per hour
define('PM_API_ENABLE_CORS', true);

/**
 * Get current user ID from session
 */
function pm_get_current_user_id() {
    session_start();
    
    if (PM_USE_ERP_AUTH) {
        return isset($_SESSION[PM_SESSION_USER_ID]) ? (int)$_SESSION[PM_SESSION_USER_ID] : null;
    }
    
    // If not using ERP auth, implement your own logic here
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Get current username from session
 */
function pm_get_current_username() {
    session_start();
    
    if (PM_USE_ERP_AUTH) {
        return isset($_SESSION[PM_SESSION_USERNAME]) ? $_SESSION[PM_SESSION_USERNAME] : null;
    }
    
    return isset($_SESSION['username']) ? $_SESSION['username'] : null;
}

/**
 * Check if user is logged in
 */
function pm_is_user_logged_in() {
    return pm_get_current_user_id() !== null;
}

/**
 * Redirect to login if not authenticated
 */
function pm_require_login() {
    if (!pm_is_user_logged_in()) {
        header('Location: ' . PM_ERP_BASE_URL . 'index/login.php');
        exit;
    }
}

/**
 * Format time duration
 */
function pm_format_time($minutes) {
    if (PM_TIME_UNIT === 'hours') {
        $hours = $minutes / 60;
        return round($hours, 1) . 'h';
    }
    return $minutes . 'm';
}

/**
 * Parse time input to minutes
 */
function pm_parse_time($input) {
    if (PM_TIME_UNIT === 'hours') {
        return (float)$input * 60;
    }
    return (int)$input;
}

/**
 * Format date according to settings
 */
function pm_format_date($date, $include_time = false) {
    if (!$date) return '';
    
    $format = $include_time ? PM_DATETIME_FORMAT : PM_DATE_FORMAT;
    return date($format, strtotime($date));
}

/**
 * Generate CSRF token
 */
function pm_generate_csrf_token() {
    if (!PM_ENABLE_CSRF_PROTECTION) return '';
    
    if (!isset($_SESSION['pm_csrf_token'])) {
        $_SESSION['pm_csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['pm_csrf_token'];
}

/**
 * Verify CSRF token
 */
function pm_verify_csrf_token($token) {
    if (!PM_ENABLE_CSRF_PROTECTION) return true;
    
    return isset($_SESSION['pm_csrf_token']) && hash_equals($_SESSION['pm_csrf_token'], $token);
}

/**
 * Get file upload path
 */
function pm_get_upload_path() {
    $path = PM_UPLOAD_PATH;
    if (!file_exists($path)) {
        mkdir($path, 0755, true);
    }
    return $path;
}

/**
 * Check if file type is allowed
 */
function pm_is_file_type_allowed($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, PM_ALLOWED_FILE_TYPES);
}

/**
 * Send email notification (if enabled)
 */
function pm_send_notification_email($to, $subject, $message) {
    if (!PM_EMAIL_ENABLED) return false;
    
    $headers = [
        'From: ' . PM_EMAIL_FROM_NAME . ' <' . PM_EMAIL_FROM . '>',
        'Reply-To: ' . PM_EMAIL_FROM,
        'Content-Type: text/html; charset=UTF-8'
    ];
    
    return mail($to, $subject, $message, implode("\r\n", $headers));
}

/**
 * Log system activity
 */
function pm_log_activity($message, $level = 'INFO') {
    if (defined('PM_LOG_FILE') && PM_LOG_FILE) {
        $timestamp = date(PM_DATETIME_FORMAT);
        $user_id = pm_get_current_user_id() ?: 'anonymous';
        $log_entry = "[$timestamp] [$level] [User: $user_id] $message\n";
        file_put_contents(PM_LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

// Set timezone
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set(PM_DEFAULT_TIMEZONE);
}

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
