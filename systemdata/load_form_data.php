<?php
// load_form_data.php - Load form element data from database

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
    'data' => array(),
    'error' => ''
);

try {
    // Get POST parameters
    $formular = isset($_POST['formular']) ? (int)$_POST['formular'] : 0;
    $sprog = isset($_POST['sprog']) ? $_POST['sprog'] : 'Dansk';
    if($sprog == "English") {
        $sprog = "Engelsk";
    }
    // Validate required parameters
    if (!$formular) {
        throw new Exception('Form number is required');
    }
    
    // Map form numbers to form names (based on your form selector options)
    $form_names = array(
        1 => 'Tilbud',
        2 => 'Ordrebekræftelse', 
        3 => 'Følgeseddel',
        4 => 'Faktura',
        5 => 'Kreditnota',
        6 => 'Rykker_1',
        7 => 'Rykker_2',
        8 => 'Rykker_3',
        9 => 'Plukliste'
    );
    
    $form_name = isset($form_names[$formular]) ? $form_names[$formular] : '';
    
    // Query to get form elements from database
    // Assuming table structure based on your code analysis
    $qtxt = "SELECT 
                id,
                art,
                formular,
                sprog,
                beskrivelse,
                xa,
                ya,
                xb,
                yb,
                str,
                color,
                justering,
                font,
                fed,
                kursiv,
                side
             FROM formularer 
             WHERE formular = '$formular' AND sprog = '$sprog'
             ORDER BY id";
    
    // Use database query
    if ($q = db_select($qtxt, __FILE__ . " linje " . __LINE__)) {
        
        $elements = array();
        while ($row = db_fetch_array($q)) {
            // Convert numeric values and handle nulls
            $row['id'] = (int)$row['id'];
            $row['art'] = (int)$row['art'];
            $row['formular'] = (int)$row['formular'];
            $row['xa'] = (float)$row['xa'];
            $row['ya'] = (float)$row['ya'];
            $row['xb'] = (float)$row['xb'];
            $row['yb'] = (float)$row['yb'];
            $row['str'] = (int)$row['str'];
            $row['color'] = (int)$row['color'];
            
            $elements[] = $row;
        }
        
        $response['success'] = true;
        $response['data'] = $elements;
        $response['count'] = count($elements);
        $response['form_name'] = $form_name;
        
    } else {
        throw new Exception('Database query failed');
    }
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    
    // Log error for debugging
    error_log("load_form_data.php Error: " . $e->getMessage());
}

// Output JSON response
ob_end_clean(); // Clear any output buffer
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>