<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/showPosLines/sum.php --- lap 4.1.1 --- 2024.07.29 ---
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
// Copyright (c) 2019-2024 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190508 Move the html that shows the sum here
// 20210127 PHR Some minor design changes 
// 20240725 PHR - Replaced 'DKK' with $baseCurrency.
// 20240729 PHR Various translations

//
	print "<!-- pos_ordre_includes/showPosLines/sum.php start -->";
	if ($sum || $sum=='0' || $pris_ny) {
		$txt="Ex. moms: ".dkdecimal($nettosum,2)." Kost ".dkdecimal($kostsum,2)." \nDB:".dkdecimal($d_b,2).", DG:".dkdecimal($dg,2)."% ";
        $country = getCountry();
		if ($country == "Switzerland") {
            print "<tr><td><div onclick=\"javascript:alert('$txt')\">SFr</div></td><td align=\"right\"></td><td></  td><td align=\"right\"></td>";
		} elseif ($country == "Norway") {
            print "<tr><td><div onclick=\"javascript:alert('$txt')\">Ialt Nok</div></td><td align=\"right\"></td><td></  td><td align=\"right\"></td>";
		} else {
            print "<tr><td><div onclick=\"javascript:alert('$txt')\">";
						print "". findtekst('3072|I alt',$sprog_id) ." $baseCurrency</div></td>";
						print "<td align=\"right\"></td><td></  td><td align=\"right\"></td>";
		}
		print "<td align=\"right\"><div title=\"$txt\">";
		if ($vis_saet && $status < 3)  {
			print "<input type=\"hidden\" name=\"sum\" value=\"$sum\"></b>\n";
			print "<input type=\"hidden\" name=\"bruttosum\" value=\"$bruttosum\"></b>\n";
			print "<input type=\"text\" class=\"inputbox\" style=\"width:100px;text-align:right;font-size:$ifs;\" ";
			print "name=\"samlet_pris\" value=\"".dkdecimal($sum,2)."\"></b></div>\n";
		} else {
			$big = get_settings_value("show_big_sum", "POS", "off");
			if ($big == "on") print "<b><h1>".dkdecimal($sum,2)."</h1></b></div>";
			else print "<b>".dkdecimal($sum,2)."</b></div>";
		}
		$tmp=$sum+usdecimal($pris_ny,2);
		if ($pris_ny) print " (".dkdecimal($tmp,2).")";
		print "<input type=\"hidden\" name=\"betvaluta\" value=\"$betvaluta\">";
		print "</td></tr>\n";
		if ($status<3) {
			$q=db_select("select kodenr,box1 from grupper where art = 'VK' order by box1",__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)) {
				$qtxt="select kurs from valuta where valdate <='$dd' and gruppe='$r[kodenr]' order by valdate desc limit 1";
				$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				print "<tr><td>I alt $r[box1]</td><td colspan=\"4\" align=\"right\">".dkdecimal($sum*100/$r2['kurs'],2)."</td></tr>";
			}
		}
	}
	print "<!-- pos_ordre_includes/showPosLines/sum.php end -->";
?>

