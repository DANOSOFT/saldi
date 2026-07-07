<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_func.php --- patch 4.3.0 --- 2026-07-02 ---
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
// Copyright (c) 2022-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 202060702 NTR Initial version of opdat_func.php with opdat_to() function for version comparison and update steps.

if (!function_exists('opdat_version_compare')) {
    /**
     * Compare two dot-separated version numbers as integers.
     *
     * @param string $left_version
     * @param string $right_version
     * @return int Returns -1 when left is lower, 0 when equal, and 1 when left is higher.
     */
    function opdat_version_compare($left_version, $right_version){
        $left_parts = array_map('intval', explode('.', $left_version));
        $right_parts = array_map('intval', explode('.', $right_version));
        $part_count = max(count($left_parts), count($right_parts));

        for ($i = 0; $i < $part_count; $i++) {
            $left_part = $left_parts[$i] ?? 0;
            $right_part = $right_parts[$i] ?? 0;

            if ($left_part < $right_part) {
                return -1;
            }

            if ($left_part > $right_part) {
                return 1;
            }
        }

        return 0;
    }
}

if (!function_exists('opdat_version_string')) {
    /**
     * Build a dot-separated version string from the update function arguments.
     *
     * @param int|string $majorNo
     * @param int|string $subNo
     * @param int|string $fixNo
     * @return string Version string, e.g. "4.3.0".
     */
    function opdat_version_string($majorNo, $subNo, $fixNo){
        return implode('.', [$majorNo, $subNo, $fixNo]);
    }
}

if (!function_exists('opdat_to')) {
    /**
     * Run an update step when the current version is lower than the targeted version.
     *
     * Versions are compared segment by segment as dot-separated integers. For example,
     * "4.2.6" is lower than "4.3.0" because the second segment changes from 2 to 3.
     * The update step is skipped when the targeted version is higher than the global
     * program version, so updates cannot move the database past the installed code.
     *
     * Usage:
     * opdat_to($current_version, '4.3.0', function () {
     *     // Database changes needed before version 4.3.0.
     * });
     *
     * @param string      $current_version  Current installed version, e.g. "4.2.6".
     * @param string      $targeted_version Version that this update step upgrades to, e.g. "4.3.0".
     * @param callable    $update_step      Code to run if current version is lower than targeted version.
     * @return bool True if the update step was run, otherwise false.
     */
    function opdat_to($current_version, $targeted_version, $update_step){
        global $version;
        global $db;

        // If the targeted version is higher than the global program version, skip the update step.
        if ($version && opdat_version_compare($targeted_version, $version) > 0) {
            return false;
        }

        // If the current version is already equal to or higher than the targeted version, skip the update step.
        if (opdat_version_compare($current_version, $targeted_version) >= 0) {
            return false;
        }

        $update_step();

        // Update the database version to the targeted version after running the update step.
        $qtxt = "UPDATE grupper set box1='$targeted_version' where art = 'VE'";
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);

        $qtxt="UPDATE regnskab set version = '$targeted_version' where db = '$db'";
        db_modify($qtxt,__FILE__ . " linje " . __LINE__, true);

        return true;
    }
}
