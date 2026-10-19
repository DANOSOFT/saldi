<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_func.php --- patch 5.0.0 --- 2026-07-02 ---
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
// 20260727 NTR Removed current version parameter, as it was incorrect and resulted in no update.
//              opdat_func now automatically fetches the version from the database.
// 20260925 CL/NTR opdat_to() now reconnects to the tenant db itself (moved from opdat_4.3.php's
//                caller-side guard), so every opdat_to() caller is covered without needing its
//                own reconnect before the version check and $update_step() closure run.
// 20260925 CL/NTR opdat_to()'s reconnect now also selects the tenant db on MySQLi, via the new
//                db_select_database() helper in includes/db_query.php; db_connect() alone
//                doesn't select a database on that engine (CodeRabbit PR #564).

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
     * The current version is fetched from grupper (box1 where art = 'VE') and from
     * regnskab (version where db = $db); the lowest of the two is used, since both
     * are expected to be updated together and neither should be trusted alone.
     *
     * Reconnects to the tenant database ($db) first when the active connection is still
     * on master ($sqdb), so both the version read above and $update_step() always run
     * against the tenant - callers don't need their own reconnect before calling opdat_to().
     *
     * Scope: db_select()/db_modify() calls inside $update_step() run against the tenant
     * database, same as elsewhere in the app - pass global=true on those calls to target
     * master ($sqdb) instead.
     *
     * Usage:
     * opdat_to('4.3.0', function () {
     *     // Database changes needed before version 4.3.0.
     * });
     *
     * @param string      $targeted_version Version that this update step upgrades to, e.g. "4.3.0".
     * @param callable    $update_step      Code to run if current version is lower than targeted version.
     * @return bool True if the update step was run, otherwise false.
     */
    function opdat_to($targeted_version, $update_step){
        global $db, $sqdb, $sqhost, $squser, $sqpass, $connection, $db_type;

        // 20260925 CL/NTR Reconnect to the tenant before reading/stamping its version. Moved
        // here from opdat_4.3.php's caller-side guard so every opdat_to() call is covered
        // uniformly, instead of each opdat_4_X() needing to remember to reconnect itself.
        // db_select_database() covers the engines (MySQLi) where db_connect() alone doesn't
        // select a database; it's a no-op on Postgres, which already connects straight to $db.
        if ($db && $db != $sqdb) {
            $connection = db_connect($sqhost, $squser, $sqpass, $db, __FILE__ . " linje " . __LINE__);
            db_select_database($connection, $db);
        }

        $qtxt = "SELECT box1 FROM grupper WHERE art = 'VE'";
        $grupper_row = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        $grupper_version = $grupper_row['box1'] ?? '0';

        $qtxt = "SELECT version FROM regnskab WHERE db = '$db'";
        $regnskab_row = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, true));
        $regnskab_version = $regnskab_row['version'] ?? '0';

        $current_version = opdat_version_compare($grupper_version, $regnskab_version) <= 0
            ? $grupper_version
            : $regnskab_version;

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