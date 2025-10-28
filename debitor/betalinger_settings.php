<?php
// --- debitor/betalinger_settings.php --- Payment Date Settings ---
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
// Copyright (c) 2003-2023 saldi.dk aps
// -----------------------------------------------------------------------------------

@session_start();
$s_id = session_id();

$modulnr = 12;
#$title = "Betalingsdato indstillinger";
$css = "../css/standard.css";

global $menu;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

$title = findtekst('2732|Indstillinger for betalingsdato', $sprog_id);

$save = isset($_POST['save']) ? $_POST['save'] : null;
$paymentDays = isset($_POST['paymentDays']) ? intval($_POST['paymentDays']) : 1;

// Handle form submission
if ($save) {
    // Validate input
    if ($paymentDays < 0) {
        $paymentDays = 0;
    }
    
    // Update or insert the setting
    $qtxt = "SELECT id FROM settings WHERE var_name = 'paymentDays' AND var_grp = 'payment_list' LIMIT 1";
    $existing = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
    
    if ($existing) {
        // Update existing setting
        $qtxt = "UPDATE settings SET var_value = '$paymentDays' WHERE var_name = 'paymentDays' AND var_grp = 'payment_list'";
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
    } else {
        // Insert new setting
        $qtxt = "INSERT INTO settings (var_name, var_value, var_grp) VALUES ('paymentDays', '$paymentDays', 'payment_list')";
        db_modify($qtxt, __FILE__ . " linje " . __LINE__);
    }
    
    print "<meta http-equiv='refresh' content='1; url=betalinger_settings.php?saved=1'>";
    exit;
}

// Get current setting
$qtxt = "SELECT var_value FROM settings WHERE var_name = 'paymentDays' AND var_grp = 'payment_list' LIMIT 1";
$settings = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
$currentPaymentDays = (is_array($settings) && isset($settings['var_value']) && is_numeric($settings['var_value']))
    ? intval($settings['var_value'])
    : 1;

// Calculate example dates
$today = date('d-m-Y');
$exampleDate = date('d-m-Y', strtotime("+$currentPaymentDays days"));

if ($menu == 'T') {
    include_once '../includes/topmenu/header.php';
    print "<div class='$kund'>$title</div>
    <div class='content-noside'>";
} elseif ($menu == 'S') {
    print "<table width='100%' height='100%' border='0' cellspacing='0' cellpadding='0'><tbody>";
    print "<tr><td height = '25' align='center' valign='top'>";
    print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>";

    print "<td width='10%'><a href='betalingsliste.php' accesskey=L>";
    print "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">".findtekst('2172|Luk', $sprog_id)."</button></a></td>";

    print "<td width='80%' style='$topStyle' align='center'>$title</td>";

    print "<td width='10%' style='$topStyle'></td>";
    print "</tbody></table>";
    print "</td></tr>";
    print "<tr><td valign='top'>";
} else {
    print "<table width='100%' height='100%' border='0' cellspacing='0' cellpadding='0'><tbody>";
    print "<tr><td height = '25' align='center' valign='top'>";
    print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>";
    print "<td width='10%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'><a href='betalingsliste.php' accesskey=L>".findtekst('2172|Luk', $sprog_id)."</a></td>";
    print "<td width='80%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'>$title</td>";
    print "<td width='10%' $top_bund></td>";
    print "</tbody></table>";
    print "</td></tr>";
    print "<tr><td valign='top'>";
}

if (isset($_GET['saved'])) {
    print "<div style='background-color: #d4edda; color: #155724; padding: 10px; margin: 10px; border: 1px solid #c3e6cb; border-radius: 4px;'>";
    print findtekst('2731|Indstillingerne er gemt', $sprog_id)."!";
    print "</div>";
}

$txt1 = findtekst('2732|Indstillinger for betalingsdato', $sprog_id);
$txt2 = findtekst('2733|Standard betalingsfrist (dage)', $sprog_id);
$txt3 = findtekst('914|Beskrivelse', $sprog_id);
$txt4 = findtekst('2734|Angiv hvor mange dage der som standard skal gå fra dags dato til betalingsdatoen', $sprog_id);
$txt5 = findtekst('2737|Eksempel (ud fra aktuelle indstillinger)', $sprog_id);
$txt6 = findtekst('2735|Dags dato', $sprog_id);
$txt7 = findtekst('2736|Betalingsdato', $sprog_id);
$txt8 = findtekst('3|Gem', $sprog_id)." ".lcfirst(findtekst('122|Indstillinger', $sprog_id));
$txt9 = findtekst('5|Annullér', $sprog_id);

print "<form method='post' action='betalinger_settings.php'>";
print "<table cellpadding='5' cellspacing='5' border='0' width='100%'>";

print "<tr>";
print "<td colspan='2'><h3>$txt1</h3></td>";
print "</tr>";

print "<tr>";
print "<td width='30%'><b>$txt2:</b></td>";
print "<td><input type='number' name='paymentDays' value='$currentPaymentDays' min='0' max='365' style='width: 80px;'></td>";
print "</tr>";

print "<tr>";
print "<td><b>$txt3:</b></td>";
print "<td>$txt4.</td>";
print "</tr>";

print "<tr>";
print "<td><b>$txt5:</b></td>";
print "<td>$txt6: <strong>$today</strong> → $txt7: <strong>$exampleDate</strong></td>";
print "</tr>";

print "<tr>";
print "<td colspan='2'><hr></td>";
print "</tr>";

print "<tr>";
print "<td colspan='2'>";
print "<input type='submit' name='save' value='$txt8' style='background-color: #007bff; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;'>";
print "&nbsp;&nbsp;";
print "<input type='button' value='$txt9' onclick=\"location.href='betalingsliste.php'\" style='background-color: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;'>";
print "</td>";
print "</tr>";

print "</table>";
print "</form>";

if ($menu == 'T') {
    print "</div>";
} else {
    print "</td></tr>";
    print "</tbody></table>";
}
?>
