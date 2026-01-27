<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/license_func.php --- 2024-12-17 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
//
// Copyright (c) 2024 Saldi.dk ApS
// ----------------------------------------------------------------------
// Feature licensing functions for controlling access to paid features
//
// IMPORTANT: This file must be included AFTER connect.php but BEFORE online.php
// so that license data is loaded while still connected to the master database.

// Global cache for license data - loaded once when this file is included
$GLOBALS['_license_cache'] = array();
$GLOBALS['_license_cache_loaded'] = false;

/**
 * Load all license data for all regnskaber into cache
 * Called automatically when this file is included (while connected to master)
 */
function _load_license_cache() {
    // Only load once
    if ($GLOBALS['_license_cache_loaded']) {
        return;
    }
    
    // Check if license_features table exists
    $qtxt = "SELECT 1 FROM information_schema.tables WHERE table_name = 'license_features'";
    $result = @db_select($qtxt, __FILE__ . " linje " . __LINE__);
    if (!$result || !db_fetch_array($result)) {
        // Table doesn't exist yet, skip loading
        $GLOBALS['_license_cache_loaded'] = true;
        return;
    }
    
    $qtxt = "SELECT regnskab_id, feature_key, enabled, expires_at FROM license_features";
    $result = @db_select($qtxt, __FILE__ . " linje " . __LINE__);
    
    if ($result) {
        while ($row = db_fetch_array($result)) {
            $regnskab_id = $row['regnskab_id'];
            $feature_key = $row['feature_key'];
            
            if (!isset($GLOBALS['_license_cache'][$regnskab_id])) {
                $GLOBALS['_license_cache'][$regnskab_id] = array();
            }
            
            $enabled = $row['enabled'] && $row['enabled'] != 'f' && $row['enabled'] != '0';
            
            // Check expiration
            if ($enabled && $row['expires_at']) {
                $expires = strtotime($row['expires_at']);
                if ($expires && $expires < time()) {
                    $enabled = false;
                }
            }
            
            $GLOBALS['_license_cache'][$regnskab_id][$feature_key] = $enabled;
        }
    }
    
    $GLOBALS['_license_cache_loaded'] = true;
}

// Load cache immediately when this file is included
_load_license_cache();

/**
 * Check if a feature is licensed for the current regnskab (customer database)
 * 
 * @param string $feature_key The feature to check ('booking', 'lager', 'kreditor', etc.)
 * @return bool True if the feature is enabled and not expired
 */
function is_feature_licensed($feature_key) {
    global $db_id;
    
    // If no database context or cache not loaded, allow access (safety fallback)
    if (!isset($db_id) || !$db_id || !$GLOBALS['_license_cache_loaded']) {
        return true;
    }
    
    // Check cache
    if (isset($GLOBALS['_license_cache'][$db_id][$feature_key])) {
        return $GLOBALS['_license_cache'][$db_id][$feature_key];
    }
    
    // If no record exists in cache, feature is allowed (backwards compatibility)
    return true;
}

/**
 * Get all licensed features for the current regnskab
 * 
 * @return array Associative array of feature_key => enabled status
 */
function get_licensed_features() {
    global $db_id;
    
    if (!isset($db_id) || !$db_id) {
        return array();
    }
    
    if (isset($GLOBALS['_license_cache'][$db_id])) {
        return $GLOBALS['_license_cache'][$db_id];
    }
    
    return array();
}

/**
 * Display a friendly upgrade message when a feature is not licensed
 * 
 * @param string $feature_name Display name of the feature
 */
function show_upgrade_message($feature_name) {
    global $bgcolor;
    
    $feature_name = htmlspecialchars($feature_name);
    
    // Include standard styles if not already included
    if (!isset($bgcolor)) $bgcolor = "#eeeef0";
    
    print "<!DOCTYPE html>
<html>
<head>
    <title>Feature ikke tilgængelig</title>
    <meta http-equiv='content-type' content='text/html; charset=UTF-8;'>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: $bgcolor;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .upgrade-box {
            background: white;
            border-radius: 10px;
            padding: 40px 60px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-width: 500px;
        }
        .upgrade-box h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .upgrade-box p {
            color: #666;
            line-height: 1.6;
        }
        .upgrade-box .icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        .upgrade-box a {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 30px;
            background: #114691;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .upgrade-box a:hover {
            background: #0d3a7a;
        }
    </style>
</head>
<body>
    <div class='upgrade-box'>
        <div class='icon'>🔒</div>
        <h2>$feature_name er ikke aktiveret</h2>
        <p>Denne funktion er ikke inkluderet i dit nuværende abonnement.</p>
        <p>Kontakt os for at opgradere dit abonnement og få adgang til $feature_name.</p>
        <a href='javascript:history.back()'>Tilbage</a>
        <br><br>
        <small>Kontakt: support@saldi.dk | Tlf: 46 90 22 08</small>
    </div>
</body>
</html>";
}
?>
