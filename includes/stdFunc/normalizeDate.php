<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/normalizeDate.php --- patch 5.0.0 --- 2026.02.27 ---
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
// Copyright (c) 2003-2026 saldi.dk aps
// ----------------------------------------------------------------------


function normalizeDate($dateString) {
    if (empty($dateString) || !is_numeric(substr($dateString,0,2)) || !is_numeric(substr($dateString,-2))) {
         return NULL;
    }

    try {
        // Hvis det er et timestamp (kun tal)
        if (ctype_digit($dateString)) {
            $date = (new DateTime())->setTimestamp((int)$dateString);
        } else {
            $date = new DateTime($dateString);
        }
#cho __line__." ". $date. "<br>";

        return $date->format('Y-m-d');

    } catch (Exception $e) {
        return null; // ugyldigt datoformat
    }
}
?>
