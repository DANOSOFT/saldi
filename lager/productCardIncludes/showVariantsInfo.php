<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----/lager/productCardIncludes/showVarianntsInfo.php----lap 4.0.8---2023-10-06-----
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
// 2023.08.30 PHR - Created this file from variant section of ../varekort.php
// 2023.10.06 PHR - Added $variantVarerText[$x]

print "<tr>";
for ($x=0;$x<count($variantVarerId);$x++) {
  print "<tr>";
  print "<input type=\"hidden\" name=\"variant_vare_id[$x]\" value=\"$variantVarerId[$x]\">";
  print "<td>$variantVarerText[$x]</td>";
  print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:left;width:400px;\"
  name=\"variant_vare_stregkode[$x]\" value=\"$variantVarerBarcode[$x]\"
  onchange=\"javascript:docChange = true;\"></td>";
  if ($stockItem) {
    for ($l=1;$l<=$numberOfStocks;$l++) {
      if ($variantVarerId[$x]) {
        print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;\" name=\"variant_varer_beholdning[$x][$l]\" value=\"".dkdecimal($variantVarerQty[$x][$l],2)."\" onchange=\"javascript:docChange = true;\"></td>";
      } else print "<td></td>";
    }
  }
  if ($var_beh[$x]) {
    print "<td></td>";
  } else {
    print "<td title=\"".findtekst(397,$sprog_id)."\">";
    print "<!--tekst 396--><a href=\"varekort.php?id=$id&delete_var_type=$variantVarerId[$x]\" onclick=\"return confirm('Vil du slette denne variant fra listen?')\">";
    print "<img src=../ikoner/delete.png border=0></a></td>\n";
  }
  $png=barcode($variantVarerBarcode[$x]);
  if ($png) {
    if ($labelprint && file_exists($png)) print "<td align=\"right\"><a href=\"../lager/labelprint.php?id=$id&beskrivelse=".urlencode($beskrivelse[0])."&stregkode=".urlencode($variantVarerBarcode[$x])."&src=$png&pris=$salgspris&enhed=$enhed&indhold=$indhold\" target=\"_blank\"><img src=\"../ikoner/print.png\" style=\"border: 0px solid;\"></a></td>";
  }
  print "</tr>";
}
 ?>
