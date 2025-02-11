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
    print "<tr><td valign=\"top\"><b>".findtekst(472,$sprog_id)."<!--tekst 472--></b></td></tr>\n";
    for ($x=0;$x<count($varianter_id);$x++) {
        (isset($variantVarerBarcode) && count($variantVarerBarcode))?$checked[$x]='checked':$checked[$x]='';
#       (in_array($variantVarerVariantId[$x],$vare_varianter))?$checked[$x]='checked':$checked[$x]='';
        if ($useVariants[$x]) $checked[$x] = 'checked';
        print "<input type=\"hidden\" name=\"varianter_id[$x]\" value=\"$varianter_id[$x]\">";
        $title=findtekst(487,$sprog_id); // Afmærk her hvis varen indeholder denne type varianter
        print "<tr title='$title'><!--tekst 487 --><td>$varianter_beskrivelse[$x]</td><td>";
#       if (count($varianter)) $checked[$x]='checked';
        print "<input type=\"hidden\" name=\"vare_varianter[$x]\" value=\"$checked[$x]\">";
        if ($beholdning) {# 20180118
            $readonly='disabled';
            $title='Kan ikke ændres når der er varer på lager';
        } else $readonly=NULL;
        print "<input title='$title' $readonly class='inputbox' type='checkbox' name=\"vare_varianter[$x]\" $checked[$x]>";
        print "</td></tr>\n";  
				if ($checked[$x]) {
					print "<tr><td colspan = '2'>Tilføj Variant type</td></tr>";
					print "<tr><td><select name='var_type[$x]'>";
				$qtxt = "select * from variant_typer where variant_id = '$varianter_id[$x]' order by beskrivelse";
				$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
        while ($r=db_fetch_array($q)) {
            print "<option value='$r[id]'>$r[beskrivelse]</option>";
					}
					print "</select></td>";
					print "<td>Stregkode</td><td><input type=\"text\" style=\"width:250px\" name=\"var_type_stregk\"></td></tr>";
				}
			
				
				
		}
/*
    print "<tr><td colspan=\"2\"><table border=0><tbody><tr>";
    for ($x=0;$x<count($vare_varianter);$x++) {
				$qtxt = "select beskrivelse from varianter where id = '$vare_varianter[$x]'";
echo __line__."$qtxt<br>";
        $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
        print "<td align=\"center\">$r[beskrivelse]</td>";
    }
    print "</tr><tr>";
    for ($x=0;$x<count($vare_varianter);$x++) {
        print "<td><select name=var_type[$x]>";
				$qtxt = "select * from variant_typer where variant_id = '$vare_varianter[$x]' order by beskrivelse";
echo __line__."$qtxt<br>";
				$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
        while ($r=db_fetch_array($q)) {
            print "<option value='$r[id]'>$r[beskrivelse]</option>";
        }
        print "</select></td>";
    }
    print "</tr></tbody></table></td></tr>";
*/
		#print "<tr><td>Antal</td><td><input type=\"text\" style=\"width:50px\" name=\"var_type_beh\"></td></tr>";
    if (count($vare_varianter)) print "<tr><td>Stregkode</td><td><input type=\"text\" style=\"width:250px\" name=\"var_type_stregk\"></td></tr>";
?>
