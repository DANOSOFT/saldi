<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/duplicate_line.php --- patch 5.0.0 --- 2026-07-016 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft.ApS
// ----------------------------------------------------------------------
//
// 20260716 PHR Missing ) at: (int$row['afd'];

ob_start(); // Start output buffering

header('Content-Type: application/json; charset=utf-8');

if (isset($_POST['kladde_id'], $_POST['source_id'])) {

    $kladde_id = (int)$_POST['kladde_id'];
    $source_id = (int)$_POST['source_id'];

    // Fetch the existing line data
    $qtxt = "
        SELECT *
        FROM kassekladde
        WHERE id = '$source_id'
          AND kladde_id = '$kladde_id'
    ";
    $result = db_select($qtxt, __FILE__ . " line " . __LINE__);

    if (!$row = db_fetch_array($result)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Linje ikke fundet']);
        exit;
    }

    $bilag        = db_escape_string($row['bilag']);
    $transdate    = $row['transdate'];
    $beskrivelse  = db_escape_string($row['beskrivelse']);
    $d_type       = db_escape_string($row['d_type']);
    $debet        = (int)$row['debet'];
    $k_type       = db_escape_string($row['k_type']);
    $kredit       = (int)$row['kredit'];
    $faktura      = db_escape_string($row['faktura']);
    $amount       = (float)$row['amount'];
    $momsfri      = db_escape_string($row['momsfri']);
    $afd          = (int)$row['afd'];
    $ansat_id     = db_escape_string($row['ansat']);
    $projekt      = db_escape_string($row['projekt']);
    $valutakode   = (int)$row['valuta'];
    $forfaldsdate = $row['forfaldsdate'];
    $betal_id     = db_escape_string($row['betal_id']);
    $dokument     = db_escape_string($row['dokument'] ?? ''); // Use null coalescing

    // Get next position
    $pos_q = "
        SELECT COALESCE(MAX(pos), 0) + 1 AS next_pos
        FROM kassekladde
        WHERE kladde_id = '$kladde_id'
          AND bilag = '$bilag'
          AND transdate = '$transdate'
    ";
    $pos_r = db_fetch_array(db_select($pos_q, __FILE__ . " line " . __LINE__));
    $next_pos = (int)$pos_r['next_pos'];

    // INSERT + RETURNING (PostgreSQL) - add dokument field to the insert
    $qtxt = "
        INSERT INTO kassekladde (
            bilag, kladde_id, transdate, beskrivelse,
            d_type, debet, k_type, kredit,
            faktura, amount, momsfri, afd,
            ansat, projekt, valuta, forfaldsdate,
            betal_id, pos, dokument
        ) VALUES (
            '$bilag', '$kladde_id', '$transdate', '$beskrivelse',
            '$d_type', '$debet', '$k_type', '$kredit',
            '$faktura', '$amount', '$momsfri', '$afd',
            '$ansat_id', '$projekt', '$valutakode',
            " . ($forfaldsdate ? "'$forfaldsdate'" : "NULL") . ",
            '$betal_id', '$next_pos', '$dokument'
        )
        RETURNING id
    ";

    $ins_res = db_select($qtxt, __FILE__ . " line " . __LINE__);
    $ins_row = db_fetch_array($ins_res);
    $new_id  = (int)$ins_row['id'];

    if (!$new_id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to get new ID from INSERT']);
        exit;
    }

    // First, check what columns exist in the documents table
    $check_columns_q = "
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'documents' 
        AND table_schema = 'public'
        ORDER BY ordinal_position
    ";
    $columns_result = db_select($check_columns_q, __FILE__ . " line " . __LINE__);
    
    $available_columns = [];
    while ($col = db_fetch_array($columns_result)) {
        $available_columns[] = $col['column_name'];
    }
    
 
    // Duplicate documents - Only include columns that actually exist
    $doc_query = db_select("
        SELECT *
        FROM documents
        WHERE source = 'kassekladde'
          AND source_id = '$source_id'
    ", __FILE__ . " line " . __LINE__);

    $doc_count = 0;
    while ($doc = db_fetch_array($doc_query)) {
        // Build dynamic INSERT based on available columns
        $insert_columns = [];
        $insert_values = [];
        
        foreach ($doc as $key => $value) {
            if ($key === 'id') continue; // Skip the auto-increment ID
            if ($key === 'source_id') {
                $value = $new_id; 
            }
            
            // Only include columns that exist in the table
            if (in_array($key, $available_columns)) {
                $insert_columns[] = $key;
                $insert_values[] = "'" . db_escape_string($value) . "'";
            }
        }
        
       
        if (in_array('uploaded_at', $available_columns) && !isset($doc['uploaded_at'])) {
            $insert_columns[] = 'uploaded_at';
            $insert_values[] = 'NOW()';
        }
        
        if (!empty($insert_columns)) {
            $insert_doc = "
                INSERT INTO documents (" . implode(', ', $insert_columns) . ")
                VALUES (" . implode(', ', $insert_values) . ")
            ";
            
            if (db_modify($insert_doc, __FILE__ . " line " . __LINE__)) {
                $doc_count++;
            } else {
                error_log("Failed to insert document for new_id: $new_id");
            }
        }
    }

    ob_end_clean();
    echo json_encode([
        'success' => true, 
        'new_id' => $new_id,
        'documents_copied' => $doc_count
    ]);
    exit;

} else {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}
?>
