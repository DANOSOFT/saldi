<?php
// -------systemdata/ansatte_body.php--------lap 3.0.0-------2013-01-29---12:05----
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
// Diverse html rettelser
// 20140923 PK - Indsat $messages efter nummer, som viser fejlmeddelelse
// 20150610 PK - Har ændret fax til privattlf i tekst 656

print "<input type=\"hidden\" name=\"id\" value=\"$id\">\n";
print "<input type=\"hidden\" name=\"konto_id\" value=\"$konto_id\">\n";
print "<input type=\"hidden\" name=\"returside\" value=\"$returside\">\n";
print "<input type=\"hidden\" name=\"fokus\" value=\"$fokus\">\n";

print "<div style=\"float:left; margin-right:70px; width:379px;\">\n";
print "<h3>Medarbejderinformation</h3>\n";
print "<div class=\"contentA\">\n";
if (findtekst(645,$sprog_id)) print "<div class=\"row\"><div class=\"left\">".findtekst(645,$sprog_id)."<!--tekst 645--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"nummer\" value=\"$nummer\"><i style=\"color:red;\">$messages&nbsp;</i></div><div class=\"clear\"></div></div><!-- end of row -->\n";
if (findtekst(646,$sprog_id)) print "<div class=\"row\"><div class=\"left\">".findtekst(646,$sprog_id)."<!--tekst 646--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"navn\" value=\"$navn\"><i style=\"color:red;\">$messages1&nbsp;</i></div><div class=\"clear\"></div></div><!-- end of row -->\n";
if (findtekst(648,$sprog_id)) print "<div class=\"row\"><div class=\"left\">".findtekst(648,$sprog_id)."<!--tekst 648--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"addr1\" value=\"$addr1\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
if (findtekst(649,$sprog_id)) print "<div class=\"row\"><div class=\"left\">".findtekst(649,$sprog_id)."<!--tekst 649--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"addr2\" value=\"$addr2\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
if (findtekst(650,$sprog_id)) print "<div class=\"row\"><div class=\"left\">".findtekst(666,$sprog_id)."<!--tekst 666--></div><div class=\"right\"><input class=\"textSmall textIndent\" type=\"text\" name=\"postnr\" value=\"$postnr\"><input class=\"textMediumLarge textSpace textIndent\" type=\"text\" name=\"bynavn\" value=\"$bynavn\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
if (findtekst(652,$sprog_id)) print "<div class=\"row\"><div class=\"left\">".findtekst(652,$sprog_id)."<!--tekst 652--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"email\" value=\"$email\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
if (findtekst(654,$sprog_id)) print "<div class=\"row\"><div class=\"left\">".findtekst(654,$sprog_id)."<!--tekst 654--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"tlf\" value=\"$tlf\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
if (findtekst(656,$sprog_id)) print "<div class=\"row\"><div class=\"left\">".findtekst(656,$sprog_id)."<!--tekst 656--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"privattlf\" value=\"$privattlf\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
print "</div><!-- end of contentA -->\n";
print "</div><!-- end of left container -->\n";

print "<div style=\"float:left; width:379px;\">\n";
print "<h3>&nbsp;</h3>\n";
print "<div class=\"contentA\">\n";
if (findtekst(661,$sprog_id)) print "<div class=\"row\"><div class=\"left\">".findtekst(661,$sprog_id)."<!--tekst 661--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"cprnr\" value=\"$cprnr\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
if (findtekst(647,$sprog_id)) print "<div class=\"row\"><div class=\"left\">".findtekst(647,$sprog_id)."<!--tekst 647--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"initialer\" value=\"$initialer\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
if (findtekst(653,$sprog_id)) print "<div class=\"row\"><div class=\"left\">".findtekst(653,$sprog_id)."<!--tekst 653--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"mobil\" value=\"$mobil\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
if (findtekst(655,$sprog_id)) print "<div class=\"row\"><div class=\"left\">".findtekst(655,$sprog_id)."<!--tekst 655--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"fax\" value=\"$fax\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
if (findtekst(662,$sprog_id)) print "<div class=\"row\"><div class=\"left\">".findtekst(662,$sprog_id)."<!--tekst 662--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"bank\" value=\"$bank\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
if (findtekst(664,$sprog_id)) print "<div class=\"row\"><div class=\"left\">".findtekst(664,$sprog_id)."<!--tekst 664--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" style=\"text-align:right;\" name=\"loen\" value=\"$loen\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
if (findtekst(665,$sprog_id)) print "<div class=\"row\"><div class=\"left\">".findtekst(665,$sprog_id)."<!--tekst 665--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" style=\"text-align:right;\" name=\"extraloen\" value=\"$extraloen\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
if ($afd)	{
	print "<div class=\"row\"><div class=\"left\">".findtekst(658,$sprog_id)."<!--tekst 658--></div><div class=\"right\"><select name=\"afd\" style=\"width: 196px;\">\n";
	if ($afd) print "<option>$afd:$afdeling</option>";	
	for ($x=1; $x<=$afd_antal; $x++) { 
		print "<option value=\"$afd_nr[$x]\">$afd_nr[$x]:$afd_beskrivelse[$x]</option>";
	}
	print "</select></div><div class=\"clear\"></div></div><!-- end of row -->\n";
} 
print "</div><!-- end of contentA -->\n";
print "</div><!-- end of right container -->\n";

print "<div style=\"float:left; width:828px;\">\n";
print "<div class=\"contentA\" style=\"808px;\">\n";
print "<div class=\"row\"><div class=\"left\">".findtekst(659,$sprog_id)."<!--tekst 659--></div><div class=\"right\"><textarea name=\"notes\" rows=\"3\" cols=\"76\" style=\"min-width:681px;max-width:681px;\" class=\"textArea\">$notes</textarea></div><div class=\"clear\"></div></div><!-- end of row -->\n";
print "</div><!-- end of contentA -->\n";
print "</div><!-- end of full container -->\n";
if ($lukket && !$slutdate) { 
	$lukket="checked";
	print "<div style=\"float:left; margin-right:70px; width:379px;\">\n";
	print "<div class=\"contentA\">\n";
	print "<div class=\"row\"><div class=\"left\">".findtekst(660,$sprog_id)."<!--tekst 660--></div><div class=\"right\"><input class=\"textSpace\" type=\"checkbox\" name=\"lukket\" $lukket></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "</div><!-- end of contentA -->\n";
	print "</div><!-- end of left container -->\n";
} else {
	print "<div style=\"float:left; margin-right:70px; width:379px;\">\n";
	print "<div class=\"contentA\">\n";
	print "<div class=\"row\"><div class=\"left\">".findtekst(663,$sprog_id)."<!--tekst 663--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"startdato\" value=\"".dkdato($startdate)."\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "<div class=\"row\"><div class=\"left\">".findtekst(670,$sprog_id)."<!--tekst 670--></div><div class=\"right\"><input class=\"textSpace\" type=\"checkbox\" name=\"trainee\" $trainee></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "</div><!-- end of contentA -->\n";
	print "</div><!-- end of left container -->\n";
	
	print "<div style=\"float:left; width:379px;\">\n";
	print "<div class=\"contentA\">\n";
	print "<div class=\"row\"><div class=\"left\">".findtekst(660,$sprog_id)."<!--tekst 660--></div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"slutdato\" value=\"".dkdato($slutdate)."\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "</div><!-- end of contentA -->\n";
	print "</div><!-- end of left container -->\n";
}
if (isset($box)&&count($box)) {
	print "<div class=\"clear\"></div>\n";
	print "<hr>";
	print "<div style=\"float:left; width:828px;\">\n";
	print "<h3>Stamkort</h3>\n";
	print "<div class=\"contentB\">\n";
	print "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"table-layout:fixed; width:100%; border:none;\">";
	$kolonne=0;
	for($x=1;$x<=28;$x++) {
		if ($feltnavn[$x]) {
			if (!$id) $box[$x]=NULL;
			if (!$kolonne) {
				print "<tr class=\"row2\">";
			}
			$kolonne++;
			if ($felttype[$x]=='textarea' && $kolonne==2) print "</tr>\n<tr class=\"row2\">";
			if ($feltnavn[$x]) print "<td  class=\"leftTableCell hyphenate\" >$feltnavn[$x]</td>";
			if ($felttype[$x]=='text') print "<td class=\"rightTableCell\"><input class=\"text textIndent\" type=\"text\" name=\"box[$x]\" value=\"$box[$x]\"></td>";
			elseif ($felttype[$x]=='select') {
				print "<td class=\"rightTableCell\"><select name=\"box[$x]\" style=\"width: 196px;\">";
				for ($y=0;$y<=count($feltvalg[$x]);$y++){
					if ($box[$x]==$feltvalg[$x][$y]) print "<option value=\"$box[$x]\">$box[$x]&nbsp;</option>"; 
				}
				for ($y=0;$y<=count($feltvalg[$x]);$y++){
					if ($box[$x]!=$feltvalg[$x][$y]) print "<option value=\"".$feltvalg[$x][$y]."\">".$feltvalg[$x][$y]."&nbsp;</option>"; 
				}
				print "</select></td>";
			} elseif ($felttype[$x]=='checkbox') {
				if ($box[$x]) $box[$x]="checked";
				print "<td class=\"rightTableCell\"><input type=\"checkbox\" class=\"textSpace\" name=\"box[$x]\" $box[$x]></td>"; 
			}
			elseif ($felttype[$x]=='textarea') {
				print "<td colspan=\"4\" style=\"padding:5px;\"><textarea name=\"box[$x]\" rows=\"3\" cols=\"76\" style=\"min-width:681px;max-width:681px;\" class=\"textArea\">$box[$x]</textarea></td>";
				$kolonne=2;
			}
			if ($kolonne==1) print "<td style=\"width:81px;\">&nbsp;</td>";
			else {
				print "</tr>\n";
				$kolonne=0;
			}
		}
	}
	print "</table>\n";
	print "</div><!-- end of contentB -->\n";
	print "</div><!-- end of left container -->\n";
	print "<div class=\"clear\"></div>\n";
	print "<hr>";
}
?>
