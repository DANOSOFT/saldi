<?php
// -----------debitor/rykker.php---------lap 1.9.4-------2008-04-16------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$modulnr=5;
$title="Rykker";
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");
	
if ($_GET['rykker_id'])	$rykker_id=$_GET['rykker_id'];
else if ($_POST) {
	$linjeantal=$_POST['linjeantal'];
	$rykker_id=$_POST['rykker_id'];
	$submit=trim($_POST['submit']);
	$linje_id=$_POST['linje_id'];
}
if ($submit=="Slet valgte") {
	$rykkerbox=$_POST['rykkerbox'];
	$slettet=0;
	for ($x=1; $x<=$linjeantal; $x++) {
		if ($rykkerbox[$x]=='on') {
			db_modify("delete from ordrelinjer where id=$linje_id[$x]");
			$slettet++;
		}
	}
	if ($slettet==$linjeantal) {
#		echo "delete from ordrer where id=$rykker_id<br>";
		db_modify("delete from ordrer where id=$rykker_id");
		$rykker_id=0;
	} 
} elseif ($submit=="Opdater") {
	$beskrivelse=$_POST['beskrivelse'];
	$dkpris=$_POST['dkpris'];
	$ny_beskrivelse=trim($_POST['ny_beskrivelse']);
	if ($ny_beskrivelse) db_modify("insert into ordrelinjer(ordre_id, beskrivelse) values ($rykker_id, '$ny_beskrivelse')");
	else {
		for ($x=1; $x<=$linjeantal; $x++) {
			$pris[$x]=usdecimal($dkpris[$x]);
			echo "update ordrelinjer set beskrivelse = '$beskrivelse[$x]', pris=$pris[$x] where id=$linje_id[$x]<br>";
			db_modify("update ordrelinjer set beskrivelse = '$beskrivelse[$x]', pris=$pris[$x] where id=$linje_id[$x]");
		}

	}
} elseif (strstr($submit,"Udskriv")) {
	print "<BODY onLoad=\"window.open('rykkerprint.php?rykker_id=$rykker_id&kontoantal=1','','width=800,height=600,scrollbars=1,resizeable=1')\">";
} elseif (strstr($submit,"Tilbage")) {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
	exit;
}
	
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sns-serif\" color=\"#000066\"><small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>";
print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>Rykkerbrev</small></td>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><br></small></td>";
print "</tbody></table>";
print "</td></tr>";
print "<td align = center valign = top>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
	
	
if ($rykker_id) {
	$query = db_select("select * from ordrer where id = '$rykker_id'");
	$row = db_fetch_array($query);
	$konto_id = $row['konto_id'];
	$kontonr = stripslashes($row['kontonr']);
	$firmanavn = stripslashes($row['firmanavn']);
	$addr1 = stripslashes($row['addr1']);
	$addr2 = stripslashes($row['addr2']);
	$postnr = stripslashes($row['postnr']);
	$bynavn = stripslashes($row['bynavn']);
	$land = stripslashes($row['land']);
	$kontakt = stripslashes($row['kontakt']);
	$kundeordnr = stripslashes($row['kundeordnr']);
	$cvrnr = $row['cvrnr'];
	$ean = stripslashes($row['ean']);
	$institution = stripslashes($row['institution']);
	$betalingsbet = trim($row['betalingsbet']);
	$betalingsdage = $row['betalingsdage'];
	$ref = trim(stripslashes($row['ref']));
	$ordrenr=$row['ordrenr'];
	$ordredato=dkdato($row['ordredate']);
	$momssats=$row['momssats'];
	$status=$row['status'];
	if ($row['valuta']) $valuta=$row['valuta'];
	else $valuta='DKK';
	
	if (!$status){$status=0;}
	$kontonr=$row['kontonr'];
} else {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
	exit;
}

print "<form name=rykker action=rykker.php method=post>";
print "<input type=hidden name=rykker_id value=$rykker_id>";

$ordre_id=$id;
print "<tr><td width=50%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
print "<tr><td width=100>$font<small><b>Kontnr.</td><td width=100>$font<small>$kontonr</td></tr>\n";
print "<tr><td>$font<small><b>Firmanavn</td><td>$font<small>$firmanavn</td></tr>\n";
print "<tr><td>$font<small><b>Adresse</td><td>$font<small>$addr1</td></tr>\n";
print "<tr><td>$font<small></td><td>$font<small>$addr2</td></tr>\n";
print "<tr><td>$font<small><b>Postnr, by</td><td>$font<small>$postnr $bynavn</td></tr>\n";
print "<tr><td>$font<small><b>Land</td><td>$font<small>$land</td></tr>\n";
print "<tr><td>$font<small><b>Att.:</td><td>$font<small>$kontakt</td></tr>\n";
print "</tbody></table></td>";
print "<td width=50%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
print "<tr><td>$font<small><b>CVR.nr</td><td>$font<small>$cvrnr</td></tr>\n";
print "<tr><td>$font<small><b>EAN.nr</td><td>$font<small>$ean</td></tr>\n";
print "<tr><td>$font<small><b>Institution</td><td>$font<small>$institution</td></tr>\n";
print "<tr><td width=100>$font<small><b>Rykkerdato</td><td width=100>$font<small>$ordredato</td></tr>\n";
print "<tr><td>$font<small><b>Betaling</td><td>$font<small>$betalingsbet&nbsp;+&nbsp;$betalingsdage</td>";
print "<tr><td>$font<small><b>Vor ref.</td><td>$font<small>$ref</td></tr>\n";
print "</tbody></table></td>";
print "</td></tr><tr><td align=center colspan=3><table cellpadding=0 cellspacing=0 border=1 width=100%><tbody>";
	print "<tr><td colspan=4></td></tr><tr>";
	print "<td align=center>$font<small><b>dato</td><td align=center>$font<small><b>faktura</td><td align=center>$font<small><b>beskrivelse</td><td align=center>$font<small><b>bel&oslash;b i $valuta</td>";
	print "</tr>\n";
	$x=0;
	$sum=0;
	$q = db_select("select * from ordrelinjer where ordre_id = '$rykker_id'");
	while ($r = db_fetch_array($q)) {
		$x++;
		$pris[$x]=$r['pris'];
		$linje_id[$x]=$r['id'];
		$vare_id[$x]=$r['vare_id'];
		$varenr[$x]=stripslashes(htmlentities($r['varenr']));
		$beskrivelse[$x]=stripslashes(htmlentities($r['beskrivelse']));
		$enhed[$x]=stripslashes(htmlentities($r['enhed']));
		if ($r[serienr]) $dato[$x]=dkdato($r['serienr']);
		$sum=$sum+$r['pris'];
		if (($r['enhed'])&&(is_numeric($r['enhed']))) {
			$r2 = db_fetch_array(db_select("select * from openpost where id = '$r[enhed]'"));
#			$dato[$x]=$r2[transdate];
			if ($r2['valuta']) $opp_valuta=$r2['valuta'];
			else $opp_valuta='DKK';
			if ($r2['valutakurs']) $opp_valkurs=$r2['valutakurs'];
			else $opp_valkurs=100;
			if ($valuta=='DKK'&& $opp_valuta!='DKK') $pris[$x]=$r2['amount']*$opp_valkurs/100;
			elseif ($valuta!='DKK' && $opp_valuta=='DKK') {
					if ($r3=db_fetch_array(db_select("select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe = grupper.kodenr and valuta.valdate <= '$r2[transdate]' order by valuta.valdate desc"))) {
						$pris[$x]=$r2['amount']*100/$r3['kurs'];
					} else print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs for faktura $r2[faktnr]')\">";	
			}
			elseif ($valuta!='DKK' && $opp_valuta!='DKK' && $opp_valuta!=$valuta) {
				$tmp==$r2['amount']*$opp_valkurs/100;
			 	$pris[$x]=$tmp*100/$opp_valkurs;
			} else $pris[$x]=$r2['amount'];
			$faktnr[$x]=$r2['faktnr'];
			$udlignet=$r2['udlignet']; 
			$inputtype[$x]="readonly";
		} else $inputtype[$x]="text";
		 $ialt=$ialt+$pris[$x];
	print "<input type=hidden name=linje_id value=$linje_id[$x]>";
	}
	$linjeantal=$x;
	print "<input type=hidden name=linjeantal value=$x>";
	for ($x=1; $x<=$linjeantal; $x++) {
		if ($pris[$x]) $dkpris[$x]=dkdecimal($pris[$x]);
		else $dkpris[$x]='';
		print "<tr bgcolor=\"$linjebg\">";
		print "<input type=hidden name=linje_id[$x] value=$linje_id[$x]>";
		if ($dato[$x]) {
			print "<td align=center>$font<small>$dato[$x]</small></td>";
			print "<td align=center>$font<small>$faktnr[$x]</small></td>";
			if ($status<3) {
				print "<td>$font<small><input type=\"text\" size=\"35\" name=\"beskrivelse[$x]\" value=\"$beskrivelse[$x]\"></small></td>";
				print "<td>$font<small><input \"$inputtype[$x]\" style=\"text-align:right\" size=\"10\" name=\"dkpris[$x]\" value=\"$dkpris[$x]\"></small></td>";
			} else {
				print "<td>$font<small>$beskrivelse[$x]</small></td>";
				print "<td align=right>$font<small>$dkpris[$x]</small></td>";
			}
		}	else {
			print "<td colspan=3>$font<small><input type=\"text\" size=\"60\" name=\"beskrivelse[$x]\" value=\"$beskrivelse[$x]\"></small></td><td></td>";
		}
		if ($status<3) print "<td align=center><input type=checkbox name=rykkerbox[$x]>";	
		print "</td></tr>";
	}
	if ($dato[$linjeantal]&&$status<3) {	
		$x++;
		print "<tr><td colspan=3>$font<small><input type=\"text\" size=\"60\" name=\"ny_beskrivelse\"></small></td>";
	}
	print "<tr><td colspan=5><br></td></tr>\n";
	print "<td align=right colspan=4>$font<small>I alt ".dkdecimal($ialt)."</td>";
	print "</tbody></table></td></tr>\n";
	print "<tr><td align=center colspan=8>";
	print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>";
#	print "<td align=center><input type=submit value=\"Tilbage\" name=\"submit\"></td>";
	if ($status<3) print "<td align=center><input type=submit value=\"Opdater\" name=\"submit\"></td>";

	if ( strlen("which ps2pdf")) {
		print "<td align=center><input type=submit value=\"&nbsp;Udskriv&nbsp;\" name=\"submit\"></td>";
	} else {
		print "<td align=center><input type=submit value=\"&nbsp;Udskriv&nbsp;\" name=\"submit\" disabled=\"disabled\"></td>";
	}

	if ($status<3) {
		db_modify("update ordrer set sum=$sum where id=$rykker_id");
		print "<td align=center><input type=submit value=\"Slet valgte\" name=\"submit\"></td>";
	}
	print "</form>";
?>
</tbody></table>
</td></tr>
</tbody></table>
</body></html>
