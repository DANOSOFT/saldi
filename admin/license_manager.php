<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- admin/license_manager.php --- 2024-12-17 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
//
// Copyright (c) 2024 Saldi.dk ApS
// ----------------------------------------------------------------------
// License Manager - Vendor admin page for managing customer feature licenses
// SECURITY NOTE: This page should only be accessible to system vendors

@session_start();
$s_id=session_id();

$modulnr = 104; // Admin module
$css = "../css/standard.css";
$title = "License Manager";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

// Available features
$available_features = array(
    'booking' => 'Booking / Udlejning',
    'lager' => 'Lager (Varer)',
    'kreditor' => 'Kreditor'
);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = if_isset($_POST['action'], '');
    
    if ($action === 'update_license') {
        $regnskab_id = (int)$_POST['regnskab_id'];
        $feature_key = db_escape_string($_POST['feature_key']);
        $enabled = isset($_POST['enabled']) ? 'true' : 'false';
        $expires_at = $_POST['expires_at'] ? "'" . db_escape_string($_POST['expires_at']) . "'" : 'NULL';
        
        // Check if record exists
        $qtxt = "SELECT id FROM license_features WHERE regnskab_id = $regnskab_id AND feature_key = '$feature_key'";
        $existing = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        
        if ($existing) {
            $qtxt = "UPDATE license_features SET enabled = $enabled, expires_at = $expires_at, updated_at = NOW() 
                     WHERE regnskab_id = $regnskab_id AND feature_key = '$feature_key'";
        } else {
            $qtxt = "INSERT INTO license_features (regnskab_id, feature_key, enabled, expires_at) 
                     VALUES ($regnskab_id, '$feature_key', $enabled, $expires_at)";
        }
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        
        $message = "Licens opdateret!";
    }
    
    if ($action === 'bulk_update') {
        $regnskab_id = (int)$_POST['regnskab_id'];
        
        foreach ($available_features as $feature_key => $feature_name) {
            $enabled = isset($_POST['feature_' . $feature_key]) ? 'true' : 'false';
            $expires_at = $_POST['expires_' . $feature_key] ? "'" . db_escape_string($_POST['expires_' . $feature_key]) . "'" : 'NULL';
            
            $qtxt = "SELECT id FROM license_features WHERE regnskab_id = $regnskab_id AND feature_key = '$feature_key'";
            $existing = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
            
            if ($existing) {
                $qtxt = "UPDATE license_features SET enabled = $enabled, expires_at = $expires_at, updated_at = NOW() 
                         WHERE regnskab_id = $regnskab_id AND feature_key = '$feature_key'";
            } else {
                $qtxt = "INSERT INTO license_features (regnskab_id, feature_key, enabled, expires_at) 
                         VALUES ($regnskab_id, '$feature_key', $enabled, $expires_at)";
            }
            db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        }
        
        $message = "Alle licenser opdateret for regnskab #$regnskab_id!";
    }
}

// Get filter
$filter_regnskab = if_isset($_GET['regnskab_id'], 0);

?>
<!DOCTYPE html>
<html>
<head>
    <title>License Manager</title>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8;">
    <link rel="stylesheet" type="text/css" href="../css/standard.css">
    <style>
        body { font-family: Arial, Helvetica, sans-serif; padding: 20px; background: #eeeef0; }
        h1 { color: #114691; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .back-link { color: #114691; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #114691; color: white; }
        tr:hover { background: #f5f5f5; }
        .enabled { color: green; font-weight: bold; }
        .disabled { color: red; font-weight: bold; }
        .expired { color: orange; font-weight: bold; }
        .btn { padding: 8px 16px; background: #114691; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0d3a7a; }
        .btn-small { padding: 4px 10px; font-size: 12px; }
        .message { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .filter-box { background: white; padding: 20px; margin-bottom: 20px; border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .detail-box { background: white; padding: 20px; margin-bottom: 20px; border-radius: 4px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        input[type="date"] { padding: 5px; border: 1px solid #ccc; border-radius: 4px; }
        input[type="checkbox"] { width: 18px; height: 18px; }
        .feature-row { display: flex; align-items: center; gap: 20px; padding: 10px 0; border-bottom: 1px solid #eee; }
        .feature-name { width: 200px; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>🔐 License Manager</h1>
        <a href="vis_regnskaber.php" class="back-link">← Tilbage til admin</a>
    </div>
    
    <?php if (isset($message)) { ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php } ?>
    
    <?php if ($filter_regnskab) { 
        // Show detail view for specific regnskab
        $qtxt = "SELECT id, regnskab, db FROM regnskab WHERE id = $filter_regnskab";
        $reg = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        
        if ($reg) {
            // Get current licenses
            $licenses = array();
            $qtxt = "SELECT feature_key, enabled, expires_at FROM license_features WHERE regnskab_id = $filter_regnskab";
            $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
            while ($r = db_fetch_array($q)) {
                $licenses[$r['feature_key']] = $r;
            }
    ?>
    <div class="detail-box">
        <h2>Rediger licenser for: <?php echo htmlspecialchars($reg['regnskab']); ?> (DB: <?php echo htmlspecialchars($reg['db']); ?>)</h2>
        <a href="license_manager.php" class="back-link">← Tilbage til oversigt</a>
        
        <form method="post" action="license_manager.php?regnskab_id=<?php echo $filter_regnskab; ?>">
            <input type="hidden" name="action" value="bulk_update">
            <input type="hidden" name="regnskab_id" value="<?php echo $filter_regnskab; ?>">
            
            <?php foreach ($available_features as $feature_key => $feature_name) { 
                $license = isset($licenses[$feature_key]) ? $licenses[$feature_key] : array('enabled' => true, 'expires_at' => null);
                $is_enabled = $license['enabled'] && $license['enabled'] != 'f' && $license['enabled'] != '0';
            ?>
            <div class="feature-row">
                <div class="feature-name"><?php echo htmlspecialchars($feature_name); ?></div>
                <label>
                    <input type="checkbox" name="feature_<?php echo $feature_key; ?>" <?php echo $is_enabled ? 'checked' : ''; ?>>
                    Aktiveret
                </label>
                <label>
                    Udløber:
                    <input type="date" name="expires_<?php echo $feature_key; ?>" value="<?php echo $license['expires_at'] ? date('Y-m-d', strtotime($license['expires_at'])) : ''; ?>">
                </label>
            </div>
            <?php } ?>
            
            <br>
            <button type="submit" class="btn">Gem ændringer</button>
        </form>
    </div>
    <?php } 
    } else { ?>
    
    <div class="filter-box">
        <form method="get" action="license_manager.php">
            <label>Søg regnskab: 
                <input type="text" name="search" value="<?php echo htmlspecialchars(if_isset($_GET['search'], '')); ?>" placeholder="Navn eller DB...">
            </label>
            <button type="submit" class="btn btn-small">Søg</button>
        </form>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Regnskab</th>
                <th>Database</th>
                <?php foreach ($available_features as $feature_key => $feature_name) { ?>
                    <th><?php echo htmlspecialchars($feature_name); ?></th>
                <?php } ?>
                <th>Handling</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $search = db_escape_string(if_isset($_GET['search'], ''));
            $where = "";
            if ($search) {
                $where = "WHERE regnskab ILIKE '%$search%' OR db ILIKE '%$search%'";
            }
            
            $qtxt = "SELECT id, regnskab, db FROM regnskab $where ORDER BY id";
            $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
            
            while ($reg = db_fetch_array($q)) {
                $reg_id = $reg['id'];
                
                // Get licenses for this regnskab
                $licenses = array();
                $qtxt2 = "SELECT feature_key, enabled, expires_at FROM license_features WHERE regnskab_id = $reg_id";
                $q2 = db_select($qtxt2, __FILE__ . " linje " . __LINE__);
                while ($lic = db_fetch_array($q2)) {
                    $licenses[$lic['feature_key']] = $lic;
                }
                
                echo "<tr>";
                echo "<td>" . $reg_id . "</td>";
                echo "<td>" . htmlspecialchars($reg['regnskab']) . "</td>";
                echo "<td>" . htmlspecialchars($reg['db']) . "</td>";
                
                foreach ($available_features as $feature_key => $feature_name) {
                    $license = isset($licenses[$feature_key]) ? $licenses[$feature_key] : null;
                    
                    if (!$license) {
                        // No record = enabled by default
                        echo "<td class='enabled'>✓ Aktiv</td>";
                    } else {
                        $is_enabled = $license['enabled'] && $license['enabled'] != 'f' && $license['enabled'] != '0';
                        $is_expired = $license['expires_at'] && strtotime($license['expires_at']) < time();
                        
                        if ($is_expired) {
                            echo "<td class='expired'>⚠ Udløbet</td>";
                        } elseif ($is_enabled) {
                            $exp = $license['expires_at'] ? ' (til ' . date('d/m/Y', strtotime($license['expires_at'])) . ')' : '';
                            echo "<td class='enabled'>✓ Aktiv$exp</td>";
                        } else {
                            echo "<td class='disabled'>✗ Deaktiveret</td>";
                        }
                    }
                }
                
                echo "<td><a href='license_manager.php?regnskab_id=$reg_id' class='btn btn-small'>Rediger</a></td>";
                echo "</tr>";
            }
            ?>
        </tbody>
    </table>
    <?php } ?>
</div>
</body>
</html>
