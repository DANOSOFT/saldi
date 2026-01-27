<?php
// --- debitor/update_payment_dates.php --- AJAX Handler for Updating Payment Dates ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2023 saldi.dk aps
// -----------------------------------------------------------------------------------

@session_start();
$s_id = session_id();

// Start output buffering to capture any HTML output
ob_start();

// Include necessary files
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

// Clear any HTML output that was generated
ob_end_clean();

// Set JSON header
header('Content-Type: application/json');


// Get POST parameters
$liste_id = isset($_POST['liste_id']) ? intval($_POST['liste_id']) : 0;
$new_date = isset($_POST['new_date']) ? trim($_POST['new_date']) : '';
$payment_ids = isset($_POST['payment_ids']) ? $_POST['payment_ids'] : '';
$update_all = isset($_POST['update_all']) ? $_POST['update_all'] : '';

// Validate inputs
if (!$liste_id) {
    echo json_encode(['success' => false, 'message' => 'Ugyldigt liste ID']);
    exit;
}

if (!$new_date || !preg_match('/^\d{8}$/', $new_date)) {
    echo json_encode(['success' => false, 'message' => 'Ugyldig dato format']);
    exit;
}

// Validate date
$day = substr($new_date, 0, 2);
$month = substr($new_date, 2, 2);
$year = substr($new_date, 4, 4);

if (!checkdate($month, $day, $year)) {
    echo json_encode(['success' => false, 'message' => 'Ugyldig dato']);
    exit;
}

// Check if we should update all payments in the list
if ($update_all == '1') {
    // Update all payments in the specified list
    $qtxt = "UPDATE betalinger SET betalingsdato = '$new_date' WHERE liste_id = '$liste_id'";
    
    if (db_modify($qtxt, __FILE__ . " linje " . __LINE__)) {
        // Get count of updated records
        $qtxt = "SELECT COUNT(*) as count FROM betalinger WHERE liste_id = '$liste_id'";
        $result = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        $updated = $result['count'];
        
        echo json_encode(['success' => true, 'updated' => $updated, 'message' => 'Alle betalingsdatoer opdateret']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Fejl ved opdatering af database']);
    }
} else {
    // Original logic for specific payment IDs
    if (!$payment_ids) {
        echo json_encode(['success' => false, 'message' => 'Ingen betalings-IDer modtaget']);
        exit;
    }
    
    // Parse payment IDs
    $ids = explode(',', $payment_ids);
    $valid_ids = array();

    foreach ($ids as $id) {
        $id = intval($id);
        if ($id > 0) {
            $valid_ids[] = $id;
        }
    }

    if (empty($valid_ids)) {
        echo json_encode(['success' => false, 'message' => 'Ingen gyldige betalings-IDer']);
        exit;
    }

    // Verify that all payments belong to the specified list
    $qtxt = "SELECT COUNT(*) as count FROM betalinger WHERE liste_id = '$liste_id' AND id IN (" . implode(',', $valid_ids) . ")";
    $result = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));

    if ($result['count'] != count($valid_ids)) {
        echo json_encode(['success' => false, 'message' => 'Nogle betalinger tilhører ikke denne liste']);
        exit;
    }

    // Update specific payment dates
    $qtxt = "UPDATE betalinger SET betalingsdato = '$new_date' WHERE id IN (" . implode(',', $valid_ids) . ") AND liste_id = '$liste_id'";

    if (db_modify($qtxt, __FILE__ . " linje " . __LINE__)) {
        $updated = count($valid_ids);
        echo json_encode(['success' => true, 'updated' => $updated, 'message' => 'Betalingsdatoer opdateret']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Fejl ved opdatering af database']);
    }
}
?>
