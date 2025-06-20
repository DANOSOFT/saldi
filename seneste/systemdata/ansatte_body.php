<?php
// -------systemdata/ansatte_body.php--------lap 3.0.0-------2013-01-06---20:03----
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
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2003-2013 DANOSOFT ApS
// ----------------------------------------------------------------------
print "<input type=\"hidden\" name=\"id\" value=\"$id\">";
print "<input type=\"hidden\" name=\"konto_id\" value=\"$konto_id\">";
print "<input type=\"hidden\" name=\"returside\" value=\"$returside\">";
print "<input type=\"hidden\" name=\"fokus\" value=\"$fokus\">";

print "<tr><td colspan=\"2\" width=\"315px\"valign=\"top\"><table valign=\"top\"><tbody>";
if (findtekst(645,$sprog_id)) print "<tr><td width=\"150px\">".findtekst(645,$sprog_id)."<!--tekst 645--></td><td width=\"180px\"><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"nummer\" value=\"$nummer\"></td></tr>\n";
if (findtekst(646,$sprog_id)) print "<tr><td width=\"150px\">".findtekst(646,$sprog_id)."<!--tekst 646--></td><td><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"navn\" value=\"$navn\"></td></tr>\n";
if (findtekst(648,$sprog_id)) print "<tr><td width=\"150px\">".findtekst(648,$sprog_id)."<!--tekst 648--></td><td><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"addr1\" value=\"$addr1\"></td></tr>\n";
if (findtekst(650,$sprog_id)) print "<tr><td width=\"150px\">".findtekst(650,$sprog_id)."<!--tekst 650--></td><td><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"postnr\" value=\"$postnr\"></td></tr>\n";
if (findtekst(652,$sprog_id)) print "<tr><td width=\"150px\">".findtekst(652,$sprog_id)."<!--tekst 652--></td><td><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"email\" value=\"$email\"></td></tr>\n";
if (findtekst(654,$sprog_id)) print "<tr><td width=\"150px\">".findtekst(654,$sprog_id)."<!--tekst 654--></td><td><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"tlf\" value=\"$tlf\"></td></tr>\n";
if (findtekst(656,$sprog_id)) print "<tr><td width=\"150px\">".findtekst(656,$sprog_id)."<!--tekst 656--></td><td><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"fax\" value=\"$fax\"></td></tr>\n";
if (findtekst(664,$sprog_id)) print "<tr><td width=\"150px\">".findtekst(664,$sprog_id)."<!--tekst 664--></td><td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:180px\" name=\"loen\" value=\"$loen\"></td></tr>\n";
if (count($afd_nr)) {
	print "<tr><td>".findtekst(658,$sprog_id)."<!--tekst 658--></td><td><SELECT style=\"width:180px\" NAME=\"afd\">";
	for ($x=0;$x<count($afd_nr);$x++) { 
		if ($afd==$afd_nr[$x]) print "<option value=\"$afd_nr[$x]\">$afd_nr[$x]:$afd_navn[$x]</option>";
	}
	for ($x=0;$x<count($afd_nr);$x++) { 
		if ($afd!=$afd_nr[$x]) print "<option value=\"$afd_nr[$x]\">$afd_nr[$x]:$afd_navn[$x]</option>";
	}
	print "</SELECT></td>";
} 
print "</tbody></table></td>";
print "<td colspan=\"2\" width=\"315px\" valign=\"top\"><table valign=\"top\"><tbody>";
if (findtekst(661,$sprog_id)) print "<td width=\"150px\">".findtekst(661,$sprog_id)."<!--tekst 661--></td><td><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"cprnr\" value=\"$cprnr\"></td></tr>\n";
if (findtekst(647,$sprog_id)) print "<td width=\"150px\">".findtekst(647,$sprog_id)."<!--tekst 647--></td><td><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"initialer\" value=\"$initialer\"></td></tr>\n";
if (findtekst(649,$sprog_id)) print "<td width=\"150px\">".findtekst(649,$sprog_id)."<!--tekst 649--></td><td><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"addr2\" value=\"$addr2\"></td></tr>\n";
if (findtekst(651,$sprog_id)) print "<td width=\"150px\">".findtekst(651,$sprog_id)."<!--tekst 651--></td><td><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"bynavn\" value=\"$bynavn\"></td></tr>\n";
if (findtekst(653,$sprog_id)) print "<td width=\"150px\">".findtekst(653,$sprog_id)."<!--tekst 653--></td><td><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"mobil\" value=\"$mobil\"></td></tr>\n";
if (findtekst(655,$sprog_id)) print "<td width=\"150px\">".findtekst(655,$sprog_id)."<!--tekst 655--></td><td><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"fax\" value=\"$fax\"></td></tr>\n";
if (findtekst(662,$sprog_id)) print "<td width=\"150px\">".findtekst(662,$sprog_id)."<!--tekst 662--></td><td><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"bank\" value=\"$bank\"></td></tr>\n";
if (findtekst(665,$sprog_id)) print "<td width=\"150px\">".findtekst(665,$sprog_id)."<!--tekst 665--></td><td><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"extraloen\" value=\"$extraloen\"></td></tr>\n";
/*
print "<tr>";
if (findtekst(657,$sprog_id)) {
	print "<td>".findtekst(657,$sprog_id)."<!--tekst 657--></td><td></td>";
	print "</tr>\n<tr><td>";
	$x=0;
	$q1 = db_select("SELECT id, beskrivelse FROM grupper WHERE art='DG' order by beskrivelse",__FILE__ . " linje " . __LINE__);
	while ($r1 = db_fetch_array($q1)) {
		$x++;
		$r2 = db_fetch_array(db_select("SELECT * from provision WHERE ansat_id = '$id' and gruppe_id = '$r1[id]'",__FILE__ . " linje " . __LINE__));
		if ($x>1) print "<tr>";
		$provision=dkdecimal($r2['provision']);
		print "<td>$r1[beskrivelse]</td><td></td><td><input class=\"inputbox\" type=\"text\" style=text-align:right size=\"5\" name=\"provision[$x]\" value=\"$provision\">%</td></tr>\n";	
		print "<input type=\"hidden\" name=\"gruppe_id[$x]\" value=\"$r1[id]\">";
		print "<input type=\"hidden\" name=\"provision_id[$x]\" value=\"$r2[id]\">";
	}
	print "<input type=\"hidden\" name=\"pro_antal\" value=\"$x\">";
}
*/
print "</tbody></table></td></tr>\n";

print "<tr><td valign=top width=\"150px\">".findtekst(659,$sprog_id)."<!--tekst 659--></td><td colspan=\"3\"><textarea name=\"notes\" rows=\"3\" style=\"width:600px\">$notes</textarea></td></tr>\n";
if ($lukket && !$slutdate) { 
	$lukket="checked";
	print "<tr><td valign=top width=\"150px\">".findtekst(660,$sprog_id)."<!--tekst 660--></td><td></td><td><input type=\"checkbox\" name=\"lukket\" $lukket></td></tr>\n";
} else {
	print "<tr><td width=\"150px\">".findtekst(663,$sprog_id)."<!--tekst 663--></td><td width=\"180px\"><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"startdato\" value=\"".dkdato($startdate)."\"></td>";
	print "<td width=\"150px\">".findtekst(660,$sprog_id)."<!--tekst 660--></td><td style width=\"180px\"><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"slutdato\" value=\"".dkdato($slutdate)."\"></td></tr>\n";
}
if (isset($box)&&count($box)) {
	print "<tr><td colspan=\"4\"><hr></td></tr>\n";
	$kolonne=0;
	for($x=1;$x<=28;$x++) {
		if ($feltnavn[$x]) {
			if (!$id) $box[$x]=NULL;
			if (!$kolonne) {
				print "<tr>";
			}
			$kolonne++;
			if ($felttype[$x]=='textarea' && $kolonne==2) print "</tr>\n<tr>";
			if ($feltnavn[$x]) print "<td colspan=\"1\" valign=\"top\" width=\"150px\">$feltnavn[$x]</td>";
			if ($felttype[$x]=='text') print "<td><input class=\"inputbox\" type=\"text\" style=\"width:180px\" name=\"box[$x]\" value=\"$box[$x]\"></td>";
			elseif ($felttype[$x]=='select') {
				print "<td><select name=\"box[$x]\">";
				for ($y=0;$y<=count($feltvalg[$x]);$y++){
					if ($box[$x]==$feltvalg[$x][$y]) print "<option value=\"$box[$x]\">$box[$x]</option>"; 
				}
				for ($y=0;$y<=count($feltvalg[$x]);$y++){
					if ($box[$x]!=$feltvalg[$x][$y]) print "<option value=\"".$feltvalg[$x][$y]."\">".$feltvalg[$x][$y]."</option>"; 
				}
				print "</select></td>";
			} elseif ($felttype[$x]=='checkbox') {
				if ($box[$x]) $box[$x]="checked";
				print "<td><input type=\"checkbox\" name=\"box[$x]\" $box[$x]></td>"; 
			}
			elseif ($felttype[$x]=='textarea') {
				print "<td colspan=\"3\"><textarea name=\"box[$x]\" rows=\"3\" style=\"width:600px\">$box[$x]</textarea></td>";
				$kolonne=2;
			}
			if ($kolonne==1) print "";
			else {
				print "</tr>\n";
				$kolonne=0;
			}
		}
	}
	print "<tr><td colspan=\"4\"><hr></td></tr>\n";
}
?>
