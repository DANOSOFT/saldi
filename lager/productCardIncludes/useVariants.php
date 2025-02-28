<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----/lager/productCardIncludes/useVariants.php----lap 4.0.8---2023-10-06-----
// LICENS
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
// Copyright (c) 2003-2023 saldi.dk aps
// ----------------------------------------------------------------------
// 2023.10.06 PHR - Created this file from 2. variant section of ../varekort.php
// varianter_id & $varianter_beskrivelse is the is and name of the variant group, eg 'size', 'color' etc.

    // Display variant group header
    echo "<tr><td valign=\"top\"><b>" . findtekst(472, $sprog_id) . "</b></td></tr>\n";
    
    // Create table for variant selections
    echo "<tr><td colspan='2'>";
    echo "<table class='variant-table' width='100%'>";
    echo "<tr><th>Variant</th><th>Vælg type</th></tr>";
    
    // Process each variant type
    for ($x = 0; $x < count($varianter_id); $x++) {
        echo "<tr>";
        echo "<td>$varianter_beskrivelse[$x]</td>";
        echo "<td>";
        echo "<input type=\"hidden\" name=\"varianter_id[$x]\" value=\"$varianter_id[$x]\">";
        
        // Create dropdown for this variant type
        echo "<select name='var_type[$x]'>";
        
        // Add default "not selected" option
        echo "<option value=''>- Ikke valgt -</option>";
        
        // Get variant types from database
        $qtxt = "select * from variant_typer where variant_id = '$varianter_id[$x]' order by beskrivelse";
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        
        // Display variant type options
        while ($r = db_fetch_array($q)) {
            echo "<option value='$r[id]'>$r[beskrivelse]</option>";
        }
        
        echo "</select>";
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "</td></tr>";
    
    // Single barcode input for all selected variants
    echo "<tr>";
    echo "<td><b>Stregkode</b></td>";
    echo "<td><input type=\"text\" style=\"width:250px\" name=\"var_type_stregk\" placeholder=\"Stregkode for valgte varianter\"></td>";
    echo "</tr>";
    
    // Add help text to explain how variants work
    echo "<tr>";
    echo "<td colspan='2' class='help-text'>";
    echo "<small><i>Vælg variant typer for at kombinere dem med en enkelt stregkode. Hvis en variant er markeret som '- Ikke valgt -', vil den ikke blive inkluderet.</i></small>";
    echo "</td>";
    echo "</tr>";
    ?>
