<?php
// --- systemdata/syssetup.php --- lap 4.1.0 -- 2024-06-04 --
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
// Copyright (c) 2003-2024 saldi.dk aps
// ----------------------------------------------------------------------
//

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $count = count($_POST['id']);
    
    for ($i = 0; $i < $count; $i++) {
        // Sanitize inputs
        $id = db_escape_string($_POST['id'][$i]);
        $kodenr = db_escape_string($_POST['kodenr'][$i]);
        $beskrivelse = db_escape_string($_POST['beskrivelse'][$i]);
        $box1 = db_escape_string($_POST['box1'][$i]);
        $box2 = db_escape_string($_POST['box2'][$i]);
        
        if ($kodenr != "-") {
            if ($id != "NULL") {
                // Update existing record
                $qtxt = "UPDATE grupper SET ";
                $qtxt .= "kodenr = '$kodenr', ";
                $qtxt .= "beskrivelse = '$beskrivelse', ";
                $qtxt .= "box1 = '$box1', ";
                $qtxt .= "box2 = '$box2' ";
                $qtxt .= "WHERE id = '$id' AND art='AFD'";
                db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            } else if (!empty($beskrivelse)) {
                // Insert new record if description is not empty
                $qtxt = "INSERT INTO grupper ";
                $qtxt .= "(kodenr, beskrivelse, box1, box2, art) ";
                $qtxt .= "VALUES ";
                $qtxt .= "('$kodenr', '$beskrivelse', '$box1', '$box2', 'AFD')";
                db_modify($qtxt, __FILE__ . " linje " . __LINE__);
            }
        } else if ($id != "NULL") {
            $qtxt = "DELETE FROM grupper WHERE id='$id'";
            db_modify($qtxt, __FILE__ . " linje " . __LINE__);
        }
    }
}

?>
    <tr>
        <th colspan="100"><?php print findtekst('772|Afdelinger', $sprog_id)?></th>
    </tr>
    <tr>
        <td><?php print findtekst('2248|Nr.', $sprog_id)?></td>
        <td><?php print findtekst('914|Beskrivelse', $sprog_id)?></td>
        <td><?php print findtekst('608|Lager', $sprog_id)?></td>
        <td><?php print findtekst('2552|Formularnote', $sprog_id)?></td>
    </tr>

<?php

$qtxt = "SELECT * FROM grupper WHERE art='AFD' ORDER BY kodenr";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
$i = 0;
while ($row = db_fetch_array($q)) {
?>
    <tr>
        <td>
            <input type="hidden" name="id[<?php print $i; ?>]" value="<?php print $row['id']; ?>">
            <input class="inputbox" name="kodenr[<?php print $i; ?>]" value="<?php print $row['kodenr']; ?>" size="3">
        </td>
        <td><input class="inputbox" name="beskrivelse[<?php print $i; ?>]" value="<?php print $row['beskrivelse']; ?>" size="35"></td>
        <td><input class="inputbox" name="box1[<?php print $i; ?>]" value="<?php print $row['box1']; ?>" size="10"></td>
        <td><input class="inputbox" name="box2[<?php print $i; ?>]" value="<?php print $row['box2']; ?>" size="10"></td>
    </tr>
<?php
    $i++;
}

?>

<tr>
    <td>
        <input type="hidden" name="id[<?php print $i; ?>]" value="NULL">
        <input class="inputbox" name="kodenr[<?php print $i; ?>]" value="<?php print $i+1; ?>" size="3">
    </td>
    <td><input class="inputbox" name="beskrivelse[<?php print $i; ?>]" value="" size="35"></td>
    <td><input class="inputbox" name="box1[<?php print $i; ?>]" value="" size="10"></td>
    <td><input class="inputbox" name="box2[<?php print $i; ?>]" value="" size="10"></td>
</tr>
