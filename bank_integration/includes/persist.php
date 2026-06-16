<?php
// Provides oauthSessionSave() and oauthSessionLoad() for persisting encrypted
// OAuth session data across PHP sessions via the existing settings table.
//
// Row identity: var_grp = 'OAuth', var_name = 'oauth_data'
//
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/persist.php --- patch 0.0.1 --- 2026-05-26 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20260526 NTR - Initial version.


if (!function_exists('oauthSessionSave')) {
    /**
     * Upserts the encrypted OAuth payload into the settings table.
     * Scoped to the current user via the global $bruger_id (set by online.php).
     *
     * @param string $encrypted  Output of oauthEncrypt(json_encode($data)).
     */
    function oauthSessionSave(string $encrypted): void {
        global $bruger_id;
        $uid    = intval($bruger_id);
        $sql    = "SELECT id FROM settings WHERE var_grp = 'OAuth' AND var_name = 'oauth_data' AND user_id = '$uid'";
        $result = db_select($sql, __FILE__ . " linje: " . __LINE__);
        $row    = db_fetch_array($result);

        if ($row) {
            $rowId = intval($row['id']);
            $sql = <<<SQL
                UPDATE settings SET var_value = '$encrypted' WHERE id = $rowId
                SQL;
        } else {
            $sql = <<<SQL
                INSERT INTO settings 
                (var_grp,   var_name,       var_value,      user_id) 
                VALUES 
                ('OAuth',   'oauth_data',   '$encrypted',   '$uid')
            SQL;
        }
        db_modify($sql, __FILE__ . " linje: " . __LINE__);
    }

    /**
     * Loads the encrypted OAuth payload from the settings table.
     * Scoped to the current user via the global $bruger_id (set by online.php).
     * Returns null if no row exists yet.
     *
     * @return string|null  The raw encrypted string, ready for oauthDecrypt().
     */
    function oauthSessionLoad(): ?string {
        global $bruger_id;
        $uid    = intval($bruger_id);
        $sql    = <<<SQL
            SELECT var_value
            FROM settings
            WHERE var_grp = 'OAuth'
                AND var_name = 'oauth_data'
                AND user_id = '$uid'
            SQL;
        $result = db_select($sql, __FILE__ . " linje: " . __LINE__);
        $row    = db_fetch_array($result);

        return $row ? $row['var_value'] : null;
    }

    /**
     * Removes the OAuth row from the settings table.
     * Scoped to the current user via the global $bruger_id (set by online.php).
     * Safe to call even if no row exists.
     */
    function oauthSessionDelete(): void {
        global $bruger_id;
        $uid = intval($bruger_id);
        $sql = <<<SQL
            DELETE FROM settings
            WHERE var_grp = 'OAuth'
                AND var_name = 'oauth_data'
                AND user_id = '$uid'
            SQL;
        db_modify($sql, __FILE__ . " linje: " . __LINE__);
    }
}
