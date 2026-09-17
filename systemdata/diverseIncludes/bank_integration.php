<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/diverseIncludes/bank_integration.php --- patch 0.0.1 --- 2026-06-09 ---
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
// but WITHOUT ANY WARRANTY OF ANY KIND.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20260609 NTR - Initial version. Bank integration settings.

global $bgcolor, $bgcolor5, $bruger_id, $sprog_id;

// =====================================================================
// show_status
// =====================================================================

if (isset($_POST['show_status_submit'])) {
    $value       = isset($_POST['show_status']) ? '1' : '0';
    $description = db_escape_string('vis status: ' . ($value === '1' ? 'ja' : 'nej'));

    $r = db_fetch_array(db_select(
        "SELECT id FROM settings WHERE var_name = 'show_status' AND var_grp = 'bank_integration' AND user_id = '$bruger_id'",
        __FILE__ . " linje " . __LINE__
    ));

    if ($r) {
        db_modify(
            "UPDATE settings SET var_value='$value', var_description='$description' WHERE id='$r[id]'",
            __FILE__ . " linje " . __LINE__
        );
    } else {
        db_modify(
            "INSERT INTO settings (var_name, var_grp, var_value, var_description, user_id) VALUES ('show_status', 'bank_integration', '$value', '$description', '$bruger_id')",
            __FILE__ . " linje " . __LINE__
        );
    }
}

$show_status = false;
$r = db_fetch_array(db_select(
    "SELECT var_value FROM settings WHERE var_name = 'show_status' AND var_grp = 'bank_integration' AND user_id = '$bruger_id'",
    __FILE__ . " linje " . __LINE__
));
if (!$r) $r = db_fetch_array(db_select(
    "SELECT var_value FROM settings WHERE var_name = 'show_status' AND var_grp = 'bank_integration' LIMIT 1",
    __FILE__ . " linje " . __LINE__
));
if ($r) $show_status = $r['var_value'] == '1';

// =====================================================================
// date_method
// =====================================================================

$dateMethodOptions = [ // TODO: Translation.
    'last_year'      => 'Sidste År',
    'this_year'      => 'Dette År',
    'last_half_year' => 'Sidste Halvår',
    'this_half_year' => 'Dette Halvår',
    'last_quarter'   => 'Sidste kvartal',
    'this_quarter'   => 'Dette kvartal',
    'last_month'     => 'Sidste Måned',
    'this_month'     => 'Dette Måned',
    'last_week'      => 'Sidste Uge',
    'this_week'      => 'Denne Uge',
    'yesterday'      => 'i går',
    'today'          => 'i dag',
];

if (isset($_POST['date_method']) && array_key_exists($_POST['date_method'], $dateMethodOptions)) {
    $method      = db_escape_string($_POST['date_method']);
    $description = db_escape_string('dato metode:' . $dateMethodOptions[$_POST['date_method']]);

    $r = db_fetch_array(db_select(
        "SELECT id FROM settings WHERE var_name = 'date_method' AND var_grp = 'bank_integration' AND user_id = '$bruger_id'",
        __FILE__ . " linje " . __LINE__
    ));

    if ($r) {
        db_modify(
            "UPDATE settings SET var_value='$method', var_description='$description', user_id='$bruger_id' WHERE id='$r[id]'",
            __FILE__ . " linje " . __LINE__
        );
    } else {
        db_modify(
            "INSERT INTO settings (var_name, var_grp, var_value, var_description, user_id) VALUES ('date_method', 'bank_integration', '$method', '$description', '$bruger_id')",
            __FILE__ . " linje " . __LINE__
        );
    }
}

$dateMethod = 'last_quarter';
$r = db_fetch_array(db_select(
    "SELECT var_value FROM settings WHERE var_name = 'date_method' AND var_grp = 'bank_integration' AND user_id = '$bruger_id'",
    __FILE__ . " linje " . __LINE__
));
if (!$r) $r = db_fetch_array(db_select(
    "SELECT var_value FROM settings WHERE var_name = 'date_method' AND var_grp = 'bank_integration' LIMIT 1",
    __FILE__ . " linje " . __LINE__
));
if ($r) $dateMethod = $r['var_value'];

// =====================================================================
// Render
// =====================================================================
?>
<form name="bank_integration" action="diverse.php?sektion=bank_integration" method="post">
<!-- Table is already surrounding the form -->
    <tr><td colspan="2"><hr></td></tr>
    <tr bgcolor="<?= htmlspecialchars($bgcolor5 ?? '') ?>"><td colspan="2"><b>Bank Integration</b></td></tr>
    <tr><td colspan="2"><br></td></tr>
    <tr>
        <td>
            <label for="show_status" title="Skal status for bank integration vises i kassekladder?">
                <?= 'Show Status:' // TODO: Translation.?>
            </label>
        </td>
        <td>
            <input type="checkbox" name="show_status" id="show_status" value="1" <?= $show_status ? 'checked' : '' ?>>
        </td>
    </tr>
    <tr><td colspan="2"><br></td></tr>
    <tr>
        <td>
            <label for="date_method" title="Methode til, at finde start dato når man vil importere transaktioner via bank integrationen.">
                <?= 'Standard Dato Methode:' // TODO: Translation.?>
            </label>
        </td>
        <td>
            <select name="date_method" id="date_method" class="inputbox">
                <?php foreach ($dateMethodOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"<?= $value === $dateMethod ? ' selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr><td colspan="2"><br></td></tr>
    <tr><td><input type="submit" name="show_status_submit" class="inputbox" value="Gem"></td></tr>
</form>
<?php
