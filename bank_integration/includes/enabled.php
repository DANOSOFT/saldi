<?php
// Include this file wherever bank integration UI should only appear when the
// integration is configured (Client ID + Client Secret present in settings).
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/enabled.php --- patch 0.0.1 --- 2026-09-15 ---
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
// 20260915 CL/NTR Initial version. bankIntegrationEnabled() tells whether the
//                 bank integration API credentials are configured.

if (!function_exists('bankIntegrationEnabled')) {
    /**
     * Whether the bank integration is configured for this account.
     *
     * The integration needs both the 'Client ID' and 'Client Secret' rows in the
     * settings table (var_grp 'OAuth'). Without them no login or import can work,
     * so callers use this to hide bank integration menus, icons and settings.
     * Requires includes/connect.php to be loaded by the caller.
     *
     * @return bool True when both credentials are present and non-empty.
     */
    function bankIntegrationEnabled(): bool {
        static $enabled = null;
        if ($enabled !== null) {
            return $enabled;
        }
        $sql = "SELECT
                    MAX(CASE WHEN var_name = 'Client ID'     THEN var_value END) AS client_id,
                    MAX(CASE WHEN var_name = 'Client Secret' THEN var_value END) AS client_secret
                FROM settings
                WHERE var_grp = 'OAuth'
                  AND var_name IN ('Client ID', 'Client Secret')";
        $row = db_fetch_array(db_select($sql, __FILE__ . " linje " . __LINE__, true));
        $enabled = $row
            && trim((string) $row['client_id']) !== ''
            && trim((string) $row['client_secret']) !== '';
        return $enabled;
    }
}
