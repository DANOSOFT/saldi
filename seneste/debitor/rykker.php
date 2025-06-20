<?php
// -----------debitor/rykker.php---------lap 3.4.3-------2014-07-07--------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20140628 - Diverse rettelser da rykkergebyr altid vistes i DKK Søg 20140628
// 20140707 - Opdatering virkede kun når der blev ændret valuta 20140707
// 20140903 - Opdatering slettede beskrivelse ved bogført rykker 20140903  

@session_start();
$s_id=session_id();

$modulnr=5;
$title="Rykker";
$css="../css/standard.css";

	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
	
$rykker_id=if_isset($_GET['rykker_id']);
if ($_POST['submit']) {
	$linjeantal=if_isset($_POST['linjeantal']);
	if (!$rykker_id) $rykker_id=if_isset($_POST['rykker_id']);
	$r=db_fetch_array(db_select("select status from ordrer where id = '$rykker_id'",__FILE__ . " linje " . __LINE__)); #20140903
	$status=$r['status'];
	$rykkernr=if_isset($_POST['rykkernr']);
	$submit=trim(if_isset($_POST['submit']));
	if (strstr($submit, "Opdat")) $submit="Opdater";
	$linje_id=if_isset($_POST['linje_id']);
	$kontakt=db_escape_string(trim($_POST['kontakt']));
	$email=db_escape_string(trim($_POST['email']));
	$valuta=trim($_POST['valuta']);
	$ny_valuta=trim($_POST['ny_valuta']); #21040628
	if (strpos($email,"@") && strpos($email,".") && strlen($email)>5) $mail_fakt = $_POST['mail_fakt'];
	elseif($_POST['mail_fakt'])	{
		$mail_fakt='';
		print "<BODY onLoad=\"javascript:alert('e-mail ikke gyldig')\">";
	}
	if ($ny_valuta != $valuta) { #21040628 ->
	if ($valuta=='DKK') $valutakurs=100;
			$r=db_fetch_array(db_select("select valutakurs,fakturadate from ordrer where id = '$rykker_id'",__FILE__ . " linje " . __LINE__));
			$valutakurs=$r['valutakurs'];
			if ($valuta=='DKK') $valutakurs=100;
		if (!$valutakurs) {
			if ($r2=db_fetch_array(db_select("select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe = ".nr_cast("grupper.kodenr")." and valuta.valdate <= '$r[fakturadate]' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
				$valutakurs=$r2['kurs'];
			} else {
				print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs i $valuta d. $r[fakturadate]')\">";	
				break 3;
			}
		}
		if ($ny_valuta=='DKK') $ny_valutakurs=100; 
		else {
			if ($r2=db_fetch_array(db_select("select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$ny_valuta' and valuta.gruppe = ".nr_cast("grupper.kodenr")." and valuta.valdate <= '$r[fakturadate]' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
				$ny_valutakurs=$r2['kurs'];
			} else {
				print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs i $valuta d. $r[fakturadate]')\">";	
				break 3;
			}
		}
		db_modify("update ordrelinjer set pris =pris*$valutakurs/$ny_valutakurs where varenr != '' and varenr is not NULL and ordre_id='$rykker_id'",__FILE__ . " linje " . __LINE__);
		db_modify("update ordrer set valuta ='$ny_valuta',valutakurs ='$ny_valutakurs' where id='$rykker_id'",__FILE__ . " linje " . __LINE__);
	}
	#20140707
	db_modify("update ordrer set email ='$email',mail_fakt='$mail_fakt',kontakt='$kontakt' where id='$rykker_id'",__FILE__ . " linje " . __LINE__);
	if ($submit=="Send" && $mail_fakt) {
		#	print "<BODY onLoad=\"return confirm('Dokumentet sendes pr. mail til $email')\">";
	}
	
	if ($submit=="Slet valgte") {
		$rykkerbox=$_POST['rykkerbox'];
		$slettet=0;
		for ($x=1; $x<=$linjeantal; $x++) {
			if ($rykkerbox[$x]=='on') {
				db_modify("delete from ordrelinjer where id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
				$slettet++;
			}
		}
		if ($slettet==$linjeantal) {
#		echo "delete from ordrer where id=$rykker_id<br>";
			db_modify("delete from ordrer where id=$rykker_id",__FILE__ . " linje " . __LINE__);
			$rykker_id=0;
		} 
	} elseif ($submit=="Opdater" && $status < 3) { #20140903
		
		$beskrivelse=$_POST['beskrivelse'];
		$dkpris=$_POST['dkpris'];
		$ny_beskrivelse=db_escape_string(trim($_POST['ny_beskrivelse']));
		if ($ny_beskrivelse) db_modify("insert into ordrelinjer(ordre_id, beskrivelse) values ($rykker_id, '$ny_beskrivelse')",__FILE__ . " linje " . __LINE__);
		else {
			for ($x=1; $x<=$linjeantal; $x++) {
				$beskrivelse[$x]=db_escape_string($beskrivelse[$x]);
#			$pris[$x]=usdecimal($dkpris[$x]); 2009.02.05 - Pris fjernet fra update da den elles bogfoerer hele beloebet.
			db_modify("update ordrelinjer set beskrivelse = '$beskrivelse[$x]' where id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
			}	
		}
	} elseif (strstr($submit,"Udskriv") || $submit=="Send") {
		print "<BODY onLoad=\"window.open('rykkerprint.php?rykker_id=$rykker_id&rykkernr=$rykkernr&kontoantal=1','','width=800,height=600,scrollbars=1,resizeable=1')\">";
	} elseif (strstr($submit,"Tilbage")) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
		exit;
	}
}	
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><a href=../includes/luk.php accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund>Rykkerbrev</td>";
print "<td width=\"10%\" $top_bund><br></td>";
print "</tbody></table>";
print "</td></tr>";
print "<td align = center valign = top>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
	
	
if ($rykker_id) {
	$query = db_select("select * from ordrer where id = '$rykker_id'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$konto_id = $row['konto_id'];
	$kontonr = htmlentities($row['kontonr'],ENT_COMPAT,$charset);
	$firmanavn = htmlentities($row['firmanavn'],ENT_COMPAT,$charset);
	$addr1 = htmlentities($row['addr1'],ENT_COMPAT,$charset);
	$addr2 = htmlentities($row['addr2'],ENT_COMPAT,$charset);
	$postnr = htmlentities($row['postnr'],ENT_COMPAT,$charset);
	$bynavn = htmlentities($row['bynavn'],ENT_COMPAT,$charset);
	$land = htmlentities($row['land'],ENT_COMPAT,$charset);
	$kontakt = htmlentities($row['kontakt'],ENT_COMPAT,$charset);
	$email = htmlentities($row['email'],ENT_COMPAT,$charset);
	$valuta = htmlentities($row['valuta'],ENT_COMPAT,$charset);
	$mail_fakt = htmlentities($row['mail_fakt'],ENT_COMPAT,$charset);
	$kundeordnr = htmlentities($row['kundeordnr'],ENT_COMPAT,$charset);
	$cvrnr = $row['cvrnr'];
	$ean = htmlentities($row['ean'],ENT_COMPAT,$charset);
	$institution = htmlentities($row['institution'],ENT_COMPAT,$charset);
	$betalingsbet = trim($row['betalingsbet']);
		$betalingsdage = $row['betalingsdage'];
	$ref = trim(htmlentities($row['ref'],ENT_COMPAT,$charset));
	$ordrenr=$row['ordrenr'];
	$ordredato=dkdato($row['ordredate']);
	$momssats=$row['momssats'];
	$status=$row['status'];
	$rykkernr=substr($row['art'],-1);
	if ($row['valuta']) $valuta=$row['valuta'];
	else $valuta='DKK';
	if (!$status){$status=0;}
	$kontonr=$row['kontonr'];
} else {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
	exit;
}
$x=0;
$valutakode[$x]='DKK';
$valutabesk[$x]='Danske kroner';
$q = db_select("select * from grupper where art = 'VK' order by box1",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)){
	$x++;
	$valutakode[$x]=$r['box1'];
	$valutabesk[$x]=$r['beskrivelse'];
}

print "<form name=rykker action=rykker.php method=post>";
print "<input type=hidden name=rykker_id value=$rykker_id>";
print "<input type=hidden name=rykkernr value=$rykkernr>";
print "<input type=hidden name=valuta value=$valuta>"; #21040628

$ordre_id=if_isset($id);
print "<tr><td width=50%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
print "<tr><td width=100><b>Kontnr.</td><td width=100>$kontonr</td></tr>\n";
print "<tr><td><b>Firmanavn</td><td>$firmanavn</td></tr>\n";
print "<tr><td><b>Adresse</td><td>$addr1</td></tr>\n";
print "<tr><td></td><td>$addr2</td></tr>\n";
print "<tr><td><b>Postnr, by</td><td>$postnr $bynavn</td></tr>\n";
print "<tr><td><b>Land</td><td>$land</td></tr>\n";

print "<tr><td><b>Att.:</td><td><input type=text name=kontakt size = \"20\" class=\"inputbox\" value=\"$kontakt\"></td></tr>\n";
print "<tr><td><b>e-mail</td><td><input type=text name=email size = \"20\" class=\"inputbox\" value=\"$email\"></td></tr>\n";
print "</tbody></table></td>";
print "<td width=50%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
print "<tr><td><b>CVR.nr</td><td>$cvrnr</td></tr>\n";
print "<tr><td><b>EAN.nr</td><td>$ean</td></tr>\n";
print "<tr><td><b>Institution</td><td>$institution</td></tr>\n";
print "<tr><td width=100><b>Rykkerdato</td><td width=100>$ordredato</td></tr>\n";
print "<tr><td><b>Betaling</td><td>$betalingsbet&nbsp;+&nbsp;$betalingsdage</td>";
print "<tr><td><b>Vor ref.</td><td>$ref</td></tr>\n";
if ($mail_fakt=='on') $mail_fakt='checked';
print "<tr><td><b>Send pr. mail</td><td><input type=checkbox name=mail_fakt $mail_fakt></td></tr>\n";
print "<tr><td><b>Valuta</b></td><td><select class=\"inputbox\" name=ny_valuta>";
for ($x=0;$x<count($valutakode);$x++) {
	if ($valuta==$valutakode[$x]) print "<option title=\"$valutabesk[$x]\" onchange=\"javascript:docChange = true;\">$valutakode[$x]</option>";
}
for ($x=0;$x<count($valutakode);$x++) {
	if ($valuta!=$valutakode[$x]) print "<option title=\"$valutabesk[$x]\" onchange=\"javascript:docChange = true;\">$valutakode[$x]</option>";
}
print "</SELECT></td></tr>";
print "</tbody></table></td>";
print "</td></tr><tr><td align=center colspan=3><table cellpadding=0 cellspacing=0 border=1 width=100%><tbody>";
	print "<tr><td colspan=4></td></tr><tr>";
	print "<td align=center><b>dato</td><td align=center><b>faktura</td><td align=center><b>beskrivelse</td><td align=center><b>bel&oslash;b i $valuta</td>";
	print "</tr>\n";
	$x=0;
	$sum=0;
	$ialt=0;
// echo "select * from ordrelinjer where ordre_id = '$rykker_id' order by posnr<br>";
	$q = db_select("select * from ordrelinjer where ordre_id = '$rykker_id' order by serienr, posnr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$pris[$x]=$r['pris'];
		$linje_id[$x]=$r['id'];
		$vare_id[$x]=$r['vare_id'];
		$varenr[$x]=htmlentities($r['varenr'],ENT_COMPAT,$charset);
		$beskrivelse[$x]=htmlentities($r['beskrivelse'],ENT_COMPAT,$charset);
		$enhed[$x]=htmlentities($r['enhed'],ENT_COMPAT,$charset);
		if ($r['serienr']) $dato[$x]=dkdato($r['serienr']);
		if ($vare_id[$x]) $sum=$sum+$r['pris'];
		if (($r['enhed'])&&(is_numeric($r['enhed']))) {
			$r2 = db_fetch_array(db_select("select * from openpost where id = '$r[enhed]'",__FILE__ . " linje " . __LINE__));
			if ($r2['valuta']) $opp_valuta[$x]=$r2['valuta'];
			else $opp_valuta[$x]='DKK';
			if ($r2['valutakurs']) $opp_valkurs[$x]=$r2['valutakurs'];
			else $opp_valkurs[$x]=100;
			if ($valuta!="DKK") {
				if ($r3=db_fetch_array(db_select("select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe = ".nr_cast("grupper.kodenr")." and valuta.valdate <= '$r2[transdate]' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
					$valutakurs=$r3['kurs'];
				} else print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs i $valuta for faktura $r2[faktnr]')\">";	
			} else $valutakurs=100;
			$dkkpris[$x]=$r2['amount']*$opp_valkurs[$x]/100;
			if ($valuta!="DKK" && ($opp_valuta[$x]!=$valuta))  $pris[$x]=$dkkpris[$x]*100/$valutakurs;
			elseif ($opp_valuta[$x]==$valuta) $pris[$x]=$r2['amount'];
			else $pris[$x]=$dkkpris[$x];

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
#		print "<tr bgcolor=\"$linjebg\">";
		print "<input type=hidden name=linje_id[$x] value=$linje_id[$x]>";
		if ($dato[$x]) {
			print "<td align=center>$dato[$x]</td>";
			print "<td align=center>$faktnr[$x]</td>";
			if ($status<3) {
				print "<td><input class=\"inputbox\" type=\"text\" size=\"44\" name=\"beskrivelse[$x]\" value=\"$beskrivelse[$x]\"></td>";
				print "<td><input class=\"inputbox\" \"$inputtype[$x]\" style=\"text-align:right\" size=\"10\" name=\"dkpris[$x]\" value=\"$dkpris[$x]\"></td>";
			} else {
				print "<td>$beskrivelse[$x]</td>";
				print "<td align=right>$dkpris[$x]</td>";
			}
		}	else {
			print "<td colspan=3><input class=\"inputbox\" type=\"text\" size=\"60\" name=\"beskrivelse[$x]\" value=\"$beskrivelse[$x]\"></td><td></td>";
		}
		if ($status<3) print "<td align=center><input type=checkbox name=rykkerbox[$x]>";	
		print "</td></tr>";
	}
	if ($dato[$linjeantal]&&$status<3) {	
		$x++;
		print "<tr><td colspan=3><input class=\"inputbox\" type=\"text\" size=\"60\" name=\"ny_beskrivelse\"></td>";
	}
	print "<tr><td colspan=5><br></td></tr>\n";
	print "<td align=right colspan=4>I alt ".dkdecimal($ialt)."</td>";
	print "</tbody></table></td></tr>\n";
	print "<tr><td align=center colspan=8>";
	print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>";
#	print "<td align=center><input type=submit value=\"Tilbage\" name=\"submit\"></td>";
	print "<td align=center><input type=submit value=\"Opdat&eacute;r\" name=\"submit\"></td>";

	if ( strlen("which ps2pdf")) {
		if ($mail_fakt) print "<td align=center><input type=submit value=\"Send\" name=\"submit\"></td>"; 
		else print "<td align=center><input type=submit value=\"Udskriv\" name=\"submit\"></td>";
	} else {
		if ($mail_fakt) print "<td align=center><input type=submit value=\"Send\" name=\"submit\" disabled=\"disabled\"></td>"; 
		else print "<td align=center><input type=submit value=\"Udskriv\" name=\"submit\" disabled=\"disabled\"></td>";
	}

	if ($status<3) {
		db_modify("update ordrer set sum='$sum' where id='$rykker_id'",__FILE__ . " linje " . __LINE__);
		print "<td align=center><input type=submit value=\"Slet valgte\" name=\"submit\"></td>";
	}
	print "</form>";
?>
</tbody></table>
</td></tr>
</tbody></table>
</body></html>
