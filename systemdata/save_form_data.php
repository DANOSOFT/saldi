<?php
// save_form_data.php - Save form element data to database

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Start session and include necessary files
@session_start();
$s_id = session_id();

// Disable error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering
ob_start();

// Include your database connection and standard functions
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

// Initialize response
$response = array(
    'success' => false,
    'updated' => 0,
    'error' => ''
);

try {
    // Get POST parameters
    $formular = isset($_POST['formular']) ? (int)$_POST['formular'] : 0;
    $sprog = isset($_POST['sprog']) ? $_POST['sprog'] : 'Dansk';
    $elements_json = isset($_POST['elements']) ? $_POST['elements'] : '';
    if($sprog == "English") {
        $sprog = "Engelsk";
    }
    // Validate required parameters
    if (!$formular) {
        throw new Exception('Form number is required');
    }
    
    if (!$elements_json) {
        throw new Exception('No elements data provided');
    }
    
    // Parse elements JSON
    $elements = json_decode($elements_json, true);
    if (!$elements || !is_array($elements)) {
        throw new Exception('Invalid elements data format');
    }
    
    $updated_count = 0;
    
    foreach ($elements as $element) {
        // Validate element data
        $id = (int)$element['id'];
        $art = (int)$element['art'];
        $xa = (float)$element['xa'];
        $ya = (float)$element['ya'];
        $str = (int)$element['str'];
        
        if (!$id) {
            continue; // Skip invalid elements
        }
        
        // Build update query based on element type
        if ($art == 2) { // Text element
            $beskrivelse = db_escape_string($element['beskrivelse']);
            $justering = db_escape_string($element['justering']);
            $font = db_escape_string($element['font']);
            $fed = db_escape_string($element['fed']);
            $kursiv = db_escape_string($element['kursiv']);
            
            $qtxt = "UPDATE formularer SET 
                        xa = '$xa',
                        ya = '$ya',
                        str = '$str',
                        beskrivelse = '$beskrivelse',
                        justering = '$justering',
                        font = '$font',
                        fed = '$fed',
                        kursiv = '$kursiv'
                     WHERE id = '$id' AND formular = '$formular' AND sprog = '$sprog'";
                     
        } else if ($art == 1) { // Line element
            $xb = (float)$element['xb'];
            $yb = (float)$element['yb'];
            
            $qtxt = "UPDATE formularer SET 
                        xa = '$xa',
                        ya = '$ya',
                        xb = '$xb',
                        yb = '$yb',
                        str = '$str'
                     WHERE id = '$id' AND formular = '$formular' AND sprog = '$sprog'";
        } else {
            continue; // Skip unknown element types
        }
        
        // Execute update
        if (db_modify($qtxt, __FILE__ . " linje " . __LINE__)) {
            $updated_count++;
        } else {
            throw new Exception("Failed to update element ID: $id");
        }
    }
    
    $response['success'] = true;
    $response['updated'] = $updated_count;
    $response['message'] = "Successfully updated $updated_count elements";
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    
    // Log error for debugging
    error_log("save_form_data.php Error: " . $e->getMessage());
}

// Output JSON response
ob_end_clean(); // Clear any output buffer
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>