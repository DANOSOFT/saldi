<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- payments/flatpay.php --- lap 4.1.0 --- 2024.02.27 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2024-2024 saldi.dk aps
// ----------------------------------------------------------------------
// 20240209 PHR Added indbetaling
// 20240227 PHR Added $printfile and call to saldiprint.php

@session_start();
$s_id = session_id();

include ("../includes/connect.php");
include ("../includes/online.php");
include ("../includes/std_func.php");
include ("../includes/stdFunc/dkDecimal.php");
include ("../includes/stdFunc/usDecimal.php");

$returside = if_isset($_GET["returside"], "../index/dashboard.php");
$valg = "visning";

include ("crmIncludes/topLine.php");

function get_current_status_groups() {
    $q = db_select("SELECT * FROM grupper WHERE art='SKN' ORDER BY kodenr", __FILE__ . " linje " . __LINE__);
    $groups = [];
    while ($r = db_fetch_array($q)) {
        $groups[] = $r;
    }
    return $groups;
}

function get_available_statuses() {
    $r = db_fetch_array(db_select("SELECT * FROM grupper WHERE art='DebInfo' ORDER BY box4", __FILE__ . " linje " . __LINE__));
    return [
        'ids' => explode(chr(9), $r['box3']),
        'descriptions' => explode(chr(9), $r['box4'])
    ];
}

function process_status_configuration() {
    // Update number of status buttons
    $button_count = (int)($_POST['antalStatusKnapper'] ?? 5);
    update_settings_value("antalStatusKnapper", "crmvisning", $button_count, "Number of status buttons");

    // Manage database rows for status groups
    manage_status_group_rows($button_count);

    // Process each status group
    $current_groups = get_current_status_groups();
    $available_statuses = get_available_statuses();

    foreach ($current_groups as $index => $group) {
        // Update group name and position
        if (isset($_POST['status_groups'][$group['id']]['name'])) {
            $new_name = db_escape_string($_POST['status_groups'][$group['id']]['name']);
            $new_position = $index + 1;
            
            db_modify("UPDATE grupper SET 
                box1 = '$new_name', 
                kodenr = '$new_position' 
                WHERE id = '{$group['id']}'", 
                __FILE__ . " linje " . __LINE__
            );

            // Process selected statuses for this group
            $selected_statuses = [];
            foreach ($available_statuses['ids'] as $status_index => $status_id) {
                if (isset($_POST['status_groups'][$group['id']]['statuses'][$status_id])) {
                    $selected_statuses[] = $status_id;
                }
            }

            // Update selected statuses
            $status_string = implode("\t", $selected_statuses);
            db_modify("UPDATE grupper SET 
                box2 = '$status_string' 
                WHERE id = '{$group['id']}'", 
                __FILE__ . " linje " . __LINE__
            );
        }
    }
}

function manage_status_group_rows($desired_count) {
    $current_count = db_fetch_array(db_select(
        "SELECT COUNT(*) as antal FROM grupper WHERE art='SKN'", 
        __FILE__ . " linje " . __LINE__
    ))['antal'];

    if ($current_count < $desired_count) {
        // Insert new rows
        for ($i = 0; $i < $desired_count - $current_count; $i++) {
            db_modify("INSERT INTO grupper (
                art, beskrivelse, box1, box2, kodenr
            ) VALUES (
                'SKN',
                'Status knapper (CRM)',
                '',
                '',
                10000
            )", __FILE__ . " linje " . __LINE__);
        }
    } elseif ($current_count > $desired_count) {
        // Delete excess rows
        for ($i = 0; $i < $current_count - $desired_count; $i++) {
            db_modify("DELETE FROM grupper 
                WHERE id = (
                    SELECT id
                    FROM grupper
                    WHERE art = 'SKN'
                    ORDER BY kodenr DESC
                    LIMIT 1
                )", __FILE__ . " linje " . __LINE__);
        }
    }
}

function render_status_configuration_form() {
    $button_count = (int)get_settings_value("antalStatusKnapper", "crmvisning", "5", );
    $current_groups = get_current_status_groups();
    $available_statuses = get_available_statuses();

    ?>
    <form action="" method="POST">
        <table>
            <tr>
                <th>Antal statusknapper</th>
                <td>
                    <input type="number" name="antalStatusKnapper" value="<?php echo $button_count; ?>" min="1" max="10">
                </td>
            </tr>
        </table>

        <div class="status-groups">
            <?php foreach ($current_groups as $index => $group): ?>
                <div class="status-group">
                    <h3>Status gruppe <?php echo $index + 1; ?></h3>
                    <input 
                        type="text" 
                        name="status_groups[<?php echo $group['id']; ?>][name]" 
                        value="<?php echo htmlspecialchars($group['box1']); ?>" 
                        placeholder="Gruppenavn"
                    >

                    <div class="status-options">
                        <?php 
                        $group_statuses = explode("\t", $group['box2']);
                        foreach ($available_statuses['ids'] as $status_index => $status_id): 
                        ?>
                            <label>
                                <input 
                                    type="checkbox" 
                                    name="status_groups[<?php echo $group['id']; ?>][statuses][<?php echo $status_id; ?>]"
                                    <?php echo in_array($status_id, $group_statuses) ? 'checked' : ''; ?>
                                >
                                <?php echo htmlspecialchars($available_statuses['descriptions'][$status_index]); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="submit">Gem indstillinger</button>
    </form>
    <?php
}

// Main execution
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    process_status_configuration();
}

render_status_configuration_form();
?>

<style>
    * {
        font-family: Arial, Helvetica, sans-serif;
    }

    h3 {
        margin: 10px 0;
    }

    .status-groups {
        display: flex;
        gap: 20px;
    }

    .status-options {
        display: flex;
        flex-direction: column;
    }

    label:has(input[type="checkbox"]:not(:checked)) {
        text-decoration: line-through;
        color: #555;
    }

</style>