<?php
/*
print "<tr><td valign=\"top\"><table cellpadding=\"0\" cellspacing=\"1\" border=\"0\" width=\"100%\"><tbody>"; # TABEL 1.2.4.1 ->
$bg=$bgcolor5;
print "<tr bgcolor=$bg><td colspan=\"4\" valign=\"top\">".findtekst(388,$sprog_id)."<!--tekst 388--></td></tr>\n";
$x=0;
if (!$rename_category) {
	for ($x=0;$x<$cat_antal;$x++) {
#	if ($cat_id[$x]!=$rename_category) {
		$checked="";
		for ($y=0;$y<$kategori_antal;$y++) {
			if ($cat_id[$x]==$kategori[$y]) $checked="checked";
		}	
		print "<tr><td>$cat_beskrivelse[$x]</td>\n";
		$tekst=findtekst(395,$sprog_id);
		$tekst=str_replace('$firmanavn',$firmanavn,$tekst);
		print "<td title=\"$tekst\" align=\"center\"><!--tekst 395--><input type=\"checkbox\" name=\"cat_valg[$x]\" $checked></td>\n";
		print "<td title=\"".findtekst(396,$sprog_id)."\"><!--tekst 396--><a href=\"debitorkort.php?id=$id&rename_category=$cat_id[$x]\" onclick=\"return confirm('Vil du omd&oslash;be denne kategori?')\"><img src=../ikoner/rename.png border=0></a></td>\n";
		print "<td title=\"".findtekst(397,$sprog_id)."\"><!--tekst 396--><a href=\"debitorkort.php?id=$id&delete_category=$cat_id[$x]\" onclick=\"return confirm('Vil du slette denne kategori?')\"><img src=../ikoner/delete.png border=0></a></td>\n";
		print "</tr>\n";
		print "<input type=\"hidden\" name=\"cat_id[$x]\" value=\"$cat_id[$x]\">\n";
		print "<input type=\"hidden\" name=\"cat_beskrivelse[$x]\" value=\"$cat_beskrivelse[$x]\">\n";
	}
}
if ($rename_category){
	for ($x=1;$x<=$cat_antal;$x++) {
		if ($rename_category==$cat_id[$x]) $ny_kategori=$cat_beskrivelse[$x];
		print "<input type=\"hidden\" name=\"cat_id[$x]\" value=\"$cat_id[$x]\">\n";
		print "<input type=\"hidden\" name=\"cat_beskrivelse[$x]\" value=\"$cat_beskrivelse[$x]\">\n";
	}
	$tekst=findtekst(388,$sprog_id);
	$tekst=str_replace('$ny_kategori',$ny_kategori,$tekst);
	print "<tr><td colspan=\"4\">$tekst<!--tekst 388--></td></tr>\n";
	print "<input type=\"hidden\" name=\"rename_category\" value=\"$rename_category\">\n";
	print "<tr><td colspan=\"4\" title=\"Skriv det nye navn p&aring; kategorien her\"><input type=\"text\" size=\"25\" name=\"ny_kategori\" value=\"$ny_kategori\"></td></tr>\n";
} else print "<tr><td colspan=\"4\" title=\"".findtekst(390,$sprog_id)."\"><!--tekst 390--><input class=\"inputbox\" type=\"text\" size=\"25\" name=\"ny_kategori\" value=\"".findtekst(343,$sprog_id)."\"></td></tr>\n";
print "<input type=\"hidden\" name=\"cat_antal\" value=\"$cat_antal\">\n";

print "</tbody></table></td>";# <- TABEL 1.2.4.1
print "<td><table border=0><tbody>"; # TABEL 1.2.4.2 ->
*/
//$bg=$bgcolor5;
print "<div style=\"float:left; width:828px;\">\n";
print "<div class=\"contentA\" style=\"808px;\">\n";
print "<div class=\"row\"><div class=\"left\">".findtekst(391,$sprog_id)."<!--tekst 391--></div><div class=\"right\"><textarea name=\"notes\" rows=\"3\" cols=\"76\" style=\"min-width:681px;max-width:681px;\" class=\"textArea\">$notes</textarea></div><div class=\"clear\"></div></div><!-- end of row -->\n";
print "</div><!-- end of contentA -->\n";
print "</div><!-- end of full container -->\n";
print "<div class=\"clear\"></div>\n";
//print "<hr>\n";
	if ($kontotype == 'erhverv') {
	if ($id) {
		print "<hr>\n";
		$r=db_fetch_array(db_select("SELECT * FROM ansatte WHERE konto_id = '$id'",__FILE__ . " linje " . __LINE__));
		$ansat_id=$r['id'];
		if ($ansat_id) {
			print "<h3>".findtekst(392,$sprog_id)."<!--tekst 392--></h3>\n";
			print "<div class=\"contentkontakt\">\n";
			print "<ul><li>\n";
			//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
			print "<span class=\"pos\" title=\"".findtekst(393,$sprog_id)."\"><!--tekst 393--><b>".findtekst(394,$sprog_id)."<!--tekst 394--></b></span>\n";
			print "<span class=\"kontakt\"><b>".findtekst(398,$sprog_id)."<!--tekst 398--></b></span>\n";
			print "<span class=\"lokal\" title=\"".findtekst(399,$sprog_id)."\"><!--tekst 399--><b>".findtekst(400,$sprog_id)."<!--tekst 400--></b></span>\n";
			print "<span class=\"mobil\"><b>".findtekst(401,$sprog_id)."<!--tekst 401--></b></span>\n";
			print "<span class=\"email\"><b>".findtekst(402,$sprog_id)."<!--tekst 402--></b></span>\n";
			//print "<!--<td>$href".findtekst(39,$sprog_id)."--tekst 39--</a></td></tr>-->\n";
			print "</li></ul>\n";
			print "<ul class=\"contentkontaktbody contentkontaktborder\">\n";
			$x=0;
			$q = db_select("SELECT * FROM ansatte WHERE konto_id = '$id' ORDER BY posnr",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)){
				$x++;
				if (strpos($_SERVER['PHP_SELF'],"kunder.php")) $href="<a href=\"kunder.php?konto_id=$id&amp;ansat_id=$r[id]&amp;sag_id=$sagid&amp;funktion=ret_kunde_ansat\">";
				else $href="<a href=\"ansatte.php?returside=$returside&amp;ordre_id=$ordre_id&amp;fokus=$fokus&amp;konto_id=$id&amp;ansat_id=$r[id]\">";
				//($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
				//print "<tr bgcolor=$bg>\n";
				print "<li>\n";
				print "<span class=\"pos\"><input class=\"textXSmall textIndent\" type=\"text\" name=\"posnr[$x]\" value=\"$x\"/></span>\n";
				print "$href<span class=\"kontakt\" title=\"".htmlentities($r['notes'],ENT_COMPAT,$charset)."\">".htmlspecialchars($r['navn'])."</span>\n";
				print "<span class=\"lokal\">$r[tlf]&nbsp;</span>\n";
				print "<span class=\"mobil\">$r[mobil]&nbsp;</span>\n";
				print "<span class=\"email\">".htmlspecialchars($r['email'])."&nbsp;</span>\n";
				print "<input type=\"hidden\" name=\"ans_id[$x]\" value=$r[id]>\n";
				if ($x==1) {print "<input type=\"hidden\" name=\"kontakt\" value=\"".htmlspecialchars($r['navn'])."\">\n";}
				print "</a>\n";
				print "</li>\n";
				//print "<td>$r[tlf]</td><td>$r[mobil]</td><td> $r[email]</td></tr>\n";
			}
			print "<li style=\"display:none;\">&nbsp;</li>";
			print "</ul>\n";
			print "<input type=\"hidden\" name=\"ans_ant\" value=$x>\n";
			print "</div><!-- end of contentkontakt -->\n";
			print "<div class=\"clear\"></div>\n";
			print "<div class=\"contentA\" style=\"float:right;\">\n";
			if (strpos($_SERVER['PHP_SELF'],"kunder.php")) $href="<a href=\"kunder.php?konto_id=$id&amp;ansat_id=0&amp;sag_id=$sagid&amp;funktion=ret_kunde_ansat\" class=\"button blue small\">";
			else $href="<a href=\"ansatte.php?returside=$returside&amp;ordre_id=$ordre_id&amp;fokus=$fokus&amp;konto_id=$id&amp;ansat_id=0\" class=\"button blue small\">";
			print "$href".findtekst(669,$sprog_id)."<!--tekst 669--></a>\n";
			print "</div>\n";
		} else {
			print "<h3>Opret kontaktperson her</h3>\n";
			print "<div class=\"contentA\" style=\"float:left;\">\n";
			if (strpos($_SERVER['PHP_SELF'],"kunder.php")) $href="<a href=\"kunder.php?konto_id=$id&amp;ansat_id=0&amp;funktion=ret_kunde_ansat\" class=\"button blue small\">";
			else $href="<a href=\"ansatte.php?returside=$returside&amp;ordre_id=$ordre_id&amp;fokus=$fokus&amp;konto_id=$id&amp;ansat_id=0\" class=\"button blue small\">";
			print "$href".findtekst(669,$sprog_id)."<!--tekst 669--></a>\n";
			print "</div>\n";
		}
	}
}

?>