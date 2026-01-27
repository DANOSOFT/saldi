<?php
// save_date_settings.php 

if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

@session_start();
$s_id = session_id();

require_once('../includes/connect.php');
require_once('../includes/online.php');


header('Content-Type: application/json');

// Validate session and user
if (!isset($bruger_id) || !$bruger_id) {
    ob_end_clean();
    echo json_encode(array('error' => 'Invalid session or user not authenticated'));
    exit;
}

$action = if_isset($_POST, '', 'action');
$grid_id = if_isset($_POST, '', 'grid_id');
$field = if_isset($_POST, '', 'field');

if (!$action || !$grid_id || !$field) {
    ob_end_clean();
    echo json_encode(array('error' => 'Missing required parameters', 'received' => array('action' => $action, 'grid_id' => $grid_id, 'field' => $field)));
    exit;
}

// Function to safely get datatable record, creating column if needed
function get_datatable_record_safe($user_id, $grid_id) {
    // First try with date_range_meta column
    $qtxt = "SELECT search_setup, date_range_meta FROM datatables WHERE user_id = '$user_id' AND tabel_id = '$grid_id'";
    $result = @db_select($qtxt, __FILE__ . " linje " . __LINE__);
    
    // Check if query failed due to missing column
    if (!$result) {
        // Try to create the column
        $alter_sql = "ALTER TABLE datatables ADD COLUMN IF NOT EXISTS date_range_meta TEXT DEFAULT '{}'";
        @db_modify($alter_sql, __FILE__ . " linje " . __LINE__);
        
        // Try the query again without date_range_meta
        $qtxt = "SELECT search_setup FROM datatables WHERE user_id = '$user_id' AND tabel_id = '$grid_id'";
        $result = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        if ($result) {
            $row = db_fetch_array($result);
            if ($row) {
                $row['date_range_meta'] = null;
            }
            return $row;
        }
        return false;
    }
    
    return db_fetch_array($result);
}

// Function to save/update record safely
function save_datatable_record($user_id, $grid_id, $search_setup_json, $date_range_meta_json) {
    // Check if record exists
    $check_query = "SELECT 1 FROM datatables WHERE user_id = '$user_id' AND tabel_id = '$grid_id'";
    $check_result = db_select($check_query, __FILE__ . " linje " . __LINE__);
    
    // Try to update with date_range_meta
    if (pg_num_rows($check_result) > 0) {
        $update_query = "UPDATE datatables SET search_setup = '$search_setup_json', date_range_meta = '$date_range_meta_json' WHERE user_id = '$user_id' AND tabel_id = '$grid_id'";
    } else {
        $update_query = "INSERT INTO datatables (user_id, tabel_id, search_setup, date_range_meta) VALUES ('$user_id', '$grid_id', '$search_setup_json', '$date_range_meta_json')";
    }
    
    $result = @db_modify($update_query, __FILE__ . " linje " . __LINE__);
    
    // If failed due to missing column, create it and try without
    if (!$result) {
        // Create column
        $alter_sql = "ALTER TABLE datatables ADD COLUMN IF NOT EXISTS date_range_meta TEXT DEFAULT '{}'";
        db_modify($alter_sql, __FILE__ . " linje " . __LINE__);
        
        // Try again without date_range_meta
        if (pg_num_rows($check_result) > 0) {
            $update_query = "UPDATE datatables SET search_setup = '$search_setup_json' WHERE user_id = '$user_id' AND tabel_id = '$grid_id'";
        } else {
            $update_query = "INSERT INTO datatables (user_id, tabel_id, search_setup) VALUES ('$user_id', '$grid_id', '$search_setup_json')";
        }
        
        return db_modify($update_query, __FILE__ . " linje " . __LINE__);
    }
    
    return $result;
}

if ($action == 'save_date_preference') {
    $range_type = db_escape_string(if_isset($_POST, '', 'range_type'));
    $date_value = db_escape_string(if_isset($_POST, '', 'date_value'));
    
    if ($range_type === 'Clear') {
        ob_end_clean();
        echo json_encode(array('error' => 'Clear is not a valid preference to save'));
        exit;
    }
    
    if (empty($date_value)) {
        ob_end_clean();
        echo json_encode(array('error' => 'Missing date value'));
        exit;
    }
    
    // Get or create record
    $r = get_datatable_record_safe($bruger_id, $grid_id);
    
    // Prepare search_setup with metadata
    if ($r && !empty($r['search_setup'])) {
        $search_setup = json_decode($r['search_setup'], true);
        if (!is_array($search_setup)) {
            $search_setup = array();
        }
    } else {
        $search_setup = array();
    }
    
    $search_setup[$field] = $date_value;
    
    // Store metadata in search_setup as well for backward compatibility
    if (!isset($search_setup['_date_range_meta'])) {
        $search_setup['_date_range_meta'] = array();
    }
    $search_setup['_date_range_meta'][$field] = array(
        'range_type' => $range_type,
        'date_value' => $date_value,
        'updated' => date('Y-m-d H:i:s')
    );
    
    $search_setup_json = db_escape_string(json_encode($search_setup));
    
    // Prepare date_range_meta
    $date_range_meta = array(
        $field => array(
            'range_type' => $range_type,
            'date_value' => $date_value,
            'updated' => date('Y-m-d H:i:s')
        )
    );
    $date_range_meta_json = db_escape_string(json_encode($date_range_meta));
    
    // Save the record
    $result = save_datatable_record($bruger_id, $grid_id, $search_setup_json, $date_range_meta_json);
    
    if ($result) {
        ob_end_clean();
        echo json_encode(array(
            'success' => true, 
            'message' => 'Date preference saved',
            'range_type' => $range_type,
            'date_value' => $date_value
        ));
        exit;
    } else {
        ob_end_clean();
        echo json_encode(array('error' => 'Failed to save preference'));
        exit;
    }
}

if ($action == 'get_date_preference') {
    // Get record safely
    $r = get_datatable_record_safe($bruger_id, $grid_id);
    
    ob_end_clean();
    
    if ($r) {
        // Try date_range_meta first
        if ($r['date_range_meta']) {
            $date_range_meta = json_decode($r['date_range_meta'], true);
            if (is_array($date_range_meta) && isset($date_range_meta[$field])) {
                $meta = $date_range_meta[$field];
                echo json_encode(array(
                    'range_type' => $meta['range_type'],
                    'date_value' => $meta['date_value']
                ));
                exit;
            }
        }
        
        // Try search_setup
        if ($r['search_setup']) {
            $search_setup = json_decode($r['search_setup'], true);
            if (is_array($search_setup)) {
                // Check _date_range_meta
                if (isset($search_setup['_date_range_meta'][$field])) {
                    $meta = $search_setup['_date_range_meta'][$field];
                    echo json_encode(array(
                        'range_type' => $meta['range_type'],
                        'date_value' => $meta['date_value'],
                        'debug' => 'From _date_range_meta'
                    ));
                    exit;
                }
                // Check plain value
                else if (isset($search_setup[$field]) && !empty($search_setup[$field])) {
                    echo json_encode(array(
                        'range_type' => null,
                        'date_value' => $search_setup[$field],
                        'debug' => 'Plain value, no range type'
                    ));
                    exit;
                }
            }
        }
        
        echo json_encode(array(
            'range_type' => null,
            'date_value' => '',
            'debug' => 'No preference found'
        ));
        exit;
    } else {
        echo json_encode(array(
            'range_type' => null,
            'date_value' => '',
            'debug' => 'No record found'
        ));
        exit;
    }
}

if ($action == 'clear_date_preference') {
    // Get record safely
    $r = get_datatable_record_safe($bruger_id, $grid_id);
    
    if ($r) {
        // Update search_setup
        $search_setup = $r['search_setup'] ? json_decode($r['search_setup'], true) : array();
        if (!is_array($search_setup)) {
            $search_setup = array();
        }
        
        // Remove the field
        if (isset($search_setup[$field])) {
            unset($search_setup[$field]);
        }
        if (isset($search_setup['_date_range_meta'][$field])) {
            unset($search_setup['_date_range_meta'][$field]);
        }
        if (isset($search_setup['_date_range_meta']) && empty($search_setup['_date_range_meta'])) {
            unset($search_setup['_date_range_meta']);
        }
        
        $search_setup_json = db_escape_string(json_encode($search_setup));
        
        // Prepare empty date_range_meta
        $date_range_meta_json = '{}';
        if ($r['date_range_meta']) {
            $date_range_meta = json_decode($r['date_range_meta'], true);
            if (is_array($date_range_meta) && isset($date_range_meta[$field])) {
                unset($date_range_meta[$field]);
                $date_range_meta_json = db_escape_string(json_encode($date_range_meta));
            }
        }
        
        // Save
        save_datatable_record($bruger_id, $grid_id, $search_setup_json, $date_range_meta_json);
    }
    
    ob_end_clean();
    echo json_encode(array('success' => true, 'message' => 'Date preference deleted'));
    exit;
}

ob_end_clean();
echo json_encode(array('error' => 'No valid action specified', 'action' => $action));
exit;
?>