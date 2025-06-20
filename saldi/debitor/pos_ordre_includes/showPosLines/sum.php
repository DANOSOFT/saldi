<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/showPosLines/sum.php ---------- lap 3.7.7----2019.05.08-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190508 Move the html that shows the sum here

	if ($sum || $pris_ny) {
		$txt="Ex. moms: ".dkdecimal($nettosum,2)." Kost ".dkdecimal($kostsum,2)." \\nDB:".dkdecimal($d_b,2).", DG:".dkdecimal($dg,2)."% ";
        $country = getCountry();
		if ($country == "Switzerland") {
            print "<tr><td><div onclick=\"javascript:alert('$txt')\">SFr</div></td><td align=\"right\"></td><td></  td><td align=\"right\"></td>";
		} elseif ($country == "Norway") {
            print "<tr><td><div onclick=\"javascript:alert('$txt')\">Ialt Nok</div></td><td align=\"right\"></td><td></  td><td align=\"right\"></td>";
		} else {
            print "<tr><td><div onclick=\"javascript:alert('$txt')\">I alt DKK</div></td><td align=\"right\"></td><td></  td><td align=\"right\"></td>";
		}
		print "<td align=\"right\"><div title=\"$txt\">";
		if ($vis_saet && $status < 3)  {
			print "<input type=\"hidden\" name=\"sum\" value=\"$sum\"></b>\n";
			print "<input type=\"hidden\" name=\"bruttosum\" value=\"$bruttosum\"></b>\n";
			print "<input type=\"text\" class=\"inputbox\" style=\"width:100px;text-align:right;font-size:$ifs;\" name=\"samlet_pris\" value=\"".dkdecimal($sum,2)."\"></b></div>\n";
		} else print "<b>".dkdecimal($sum,2)."</b></div>";
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

?>

