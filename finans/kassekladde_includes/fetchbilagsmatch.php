<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde.php --- ver 5.0.0 --- 2026-04-10 ---
// verifying fork target points to DANOSOFT/saldi
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------

// 20260507 NTR - Added batch invoice matching

    // Start buffering
    ob_start();

    @session_start();
    $s_id=session_id();
    include("../../includes/connect.php");
    include("../../includes/online.php");


    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    header('Content-Type: application/json');

    $kladde_id = $_GET['kladde_id'] ?? $_POST['kladde_id'];

    //kassekladde info
    $qtxt = "SELECT
                k.id as kasse_id,
                pf.id as pf_id,
                k.kladde_id as kladde_id,
                k.amount as amount,
                pf.norm_amt as pool_amount,
                COALESCE(
                    NULLIF(pf.currency, ''),
                    CAST(k.valuta as TEXT)
                ) as currency,
                pf.subject as subject,
                k.beskrivelse as description,
                CAST(pf.file_date as DATE) as pool_date,
                k.transdate as file_date,
                pf.filename as filename,
                pf.account as account,
                pf.invoice_number as invoice_number,
                (
                    CASE WHEN k.amount = pf.norm_amt
                    THEN 1 ELSE 0 END
                    +
                    CASE WHEN k.transdate = CAST(pf.file_date as DATE)
                    THEN 1 ELSE 0 END
                ) AS aligns,
                CASE WHEN k.amount = pf.norm_amt
                    THEN 1 ELSE 0 END
                    AS beloeb_match,
                CASE WHEN k.transdate = CAST(pf.file_date as DATE)
                    THEN 1 ELSE 0 END
                    AS date_match
            FROM kassekladde k
            JOIN (
                SELECT *,
                    CAST(NULLIF(REGEXP_REPLACE(REPLACE(amount, ',', '.'), '\.(?=.*\.[^.]*$)', '', 'gm'), '') as NUMERIC(15,3)) AS norm_amt
                FROM pool_files
            ) pf
            ON 
                k.amount = pf.norm_amt
                OR k.transdate = CAST(pf.file_date as DATE)
            WHERE k.kladde_id = $kladde_id";

    $kasseQuery = db_select($qtxt, __FILE__ . " linje " . __LINE__);

    $rowIndex = 0;
    $OD = []; //Output Data

    while($row = db_fetch_array($kasseQuery)){    
        $row = array_filter($row, 
            function ($col) {
                return !is_int($col);
            },
            ARRAY_FILTER_USE_KEY
        );      

        $OD[$rowIndex] = [];
        foreach($row as $key => $value){
            $OD[$rowIndex][$key] = $value;
        }
        ++$rowIndex;
    }
    
    // Get rid of anything they output
    ob_clean(); // or ob_end_clean();

    echo json_encode($OD);
?>