<?php
// //---------------------includes/openpost.php ----- lap 2.0.0l ---- 30.05.2008 ---------------------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaetelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------
function openpost($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til, $rapportart, $art) 
{
	?>
	<script LANGUAGE="JavaScript">
	<!--
	function confirmSubmit()
	{
		var agree=confirm("Bekræft venligst");
		if (agree) return true ;
		else return false ;
	}
	// -->
	</script>
	<?php

	$forfaldsum=NULL;$forfaldsum_plus8=NULL;$forfaldsum_plus30=NULL;$forfaldsum_plus60=NULL;$forfaldsum_plus90=NULL;
	$linjebg=NULL;
	
	global $bgcolor;
	global $bgcolor5;
	global $font;
	global $top_bund;
	global $md;
	global $kontoudtog;
	global $ny_rykker;
	
	db_modify("update ordrer set art = 'R1' where art = 'RB'");
	
	if ($ny_rykker) {
		print "1;URL=rapport.php?ny_rykker=1&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til&rapportart=$rapportart"; 
		print "<meta http-equiv=\"refresh\" content=\"1;URL=rapport.php?ny_rykker=1&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til&rapportart=$rapportart\">"; 
	}
	$currentdate=date("Y-m-d");
 	
	list ($x, $tmp1) = split(" ", $maaned_fra);
	list ($x, $tmp2) = split(" ", $maaned_til);
	if ($tmp1 && $tmp1){
		$maaned_fra=$tmp1;
		$maaned_til=$tmp2;
	}
	
	$maaned_fra=trim($maaned_fra);
	$maaned_til=trim($maaned_til);

	print "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
	print "<tr><td colspan=\"10\" height=\"8\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a accesskey=l href=\"rapport.php?rapportart=openpost&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til\">Luk</a></small></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>Rapport - $rapportart</small></td>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
	print "</tbody></table>"; #B slut
	print "</td></tr>\n";

	print "<tr><td>$font<small>Kontonr</small></td><td>$font<small>Firmanavn</small></td><td align=right>$font<small>>90</small></td><td align=right>$font<small>60-90</small></td><td align=right>$font<small>30-60</small></td><td align=right>$font<small>8-30</small></td><td align=right>$font<small>0-8</small></td><td align=right>$font<small>I alt</small></td><tr>";


	## Finder start og slut paa regnskabsaar


	for ($x=1; $x<=12; $x++) {
		if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
		if ($maaned_til==$md[$x]){$maaned_til=$x;}
	}

	$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'");
	$row = db_fetch_array($query);
#	$regnaar=$row[kodenr];
	$startmaaned=$row['box1']*1;
	$startaar=$row['box2']*1;
	$slutmaaned=$row['box3']*1;
	$slutaar=$row['box4']*1;
	$slutdato=31;

	##

	if ($maaned_fra) $startmaaned=$maaned_fra;
	if ($maaned_til) $slutmaaned=$maaned_til;

	while (!checkdate($slutmaaned,$slutdato,$slutaar))	{
		$slutdato=$slutdato-1;
		if ($slutdato<28) break;
	}

#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}

	$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;
 #$regnslut = "2005-05-04"; 
 
#	echo "$maaned_fra -$maaned_til<br>";

	print "<form name=aabenpost action=rapport.php method=post>";
	print "<tr><td colspan=10><hr></td></tr>\n";
		
	$x=0;
	$q = db_select("select * from adresser where kontonr>='$konto_fra' and kontonr<='$konto_til' and art = '$art' order by firmanavn");
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		print "<input type=hidden name=konto_id[$x] value=$id[$x]>";
		$kontonr[$x]=trim($r['kontonr']);
		$firmanavn[$x]=stripslashes($r['firmanavn']);
		$addr1[$x]=stripslashes($r['addr1']);
		$addr2[$x]=stripslashes($r['addr2']);
		$postnr[$x]=trim($r['postnr']);
		$bynavn[$x]=stripslashes($r['bynavn']);
		$email[$x]=trim($r['email']);
		$betalingsbet[$x]=trim($r['betalingsbet']);
		$betalingsdage[$x]=trim($r['betalingsdage']);
	}
	$kontoantal=$x;

	$sum=0;
	for ($x=1; $x<=$kontoantal; $x++) {
		$amount=0;
		$udlignet=1;
		$rykkerbelob=0;
		$forfalden=0;
		$forfalden_plus8=0;
		$forfalden_plus30=0;
		$forfalden_plus60=0;
		$forfalden_plus90=0;
		$y=0;
		$q=db_select("select * from openpost where konto_id=$id[$x] and transdate <= '$regnslut' and udlignet!='1'");
		while ($r=db_fetch_array($q)) {
			if ($r['udlignet']!=1){
				$transdate=$r['transdate'];
				if ($r['valuta']) $valuta=$r['valutakurs'];
				else $valuta='DKK';
				if ($r['valutakurs']) $valutakurs=$r['valutakurs'];
				else $valutakurs=100;
				$udlignet="0";
				$amount=$r['amount'];
				if (!$r['kladde_id']&&$r['refnr']) { #så er aaben posten genereret direkte fra en ordre.
					$r2=db_fetch_array(db_select("select betalingsbet, betalingsdage from ordrer where id='$r[refnr]'"));
					$betalingsbet[$x]=trim($r2['betalingsbet']);
					$betalingsdage[$x]=trim($r2['betalingsdage']);
				} 
				$amount=$amount*$valutakurs/100;
				$forfaldsdag=usdate(forfaldsdag($transdate, $betalingsbet[$x], $betalingsdage[$x]));
				$forfaldsdag_plus8=usdate(forfaldsdag($transdate, $betalingsbet[$x], $betalingsdage[$x]+8));
				$forfaldsdag_plus30=usdate(forfaldsdag($transdate, $betalingsbet[$x], $betalingsdage[$x]+30));
				$forfaldsdag_plus60=usdate(forfaldsdag($transdate, $betalingsbet[$x], $betalingsdage[$x]+60));
				$forfaldsdag_plus90=usdate(forfaldsdag($transdate, $betalingsbet[$x], $betalingsdage[$x]+90));
				if ($forfaldsdag<$currentdate){$rykkerbelob=$rykkerbelob+$amount;}
				if (($forfaldsdag<$currentdate)&&($forfaldsdag_plus8>$currentdate)){$forfalden=$forfalden+$amount;}
				if (($forfaldsdag_plus8<=$currentdate)&&($forfaldsdag_plus30>$currentdate)){$forfalden_plus8=$forfalden_plus8+$amount;}
				if (($forfaldsdag_plus30<=$currentdate)&&($forfaldsdag_plus60>$currentdate)){$forfalden_plus30=$forfalden_plus30+$amount;}
				if (($forfaldsdag_plus60<=$currentdate)&&($forfaldsdag_plus90>$currentdate)){$forfalden_plus60=$forfalden_plus60+$amount;}
				if ($forfaldsdag_plus90<=$currentdate){$forfalden_plus90=$forfalden_plus90+$amount;}
			}
			$y=$y+$amount;
		}
		if (($y>0.01)||($udlignet=="0"))	{	
			if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		
			$forfaldsum=$forfaldsum+$forfalden;
			$forfaldsum_plus8=$forfaldsum_plus8+$forfalden_plus8;
			$forfaldsum_plus30=$forfaldsum_plus30+$forfalden_plus30;
			$forfaldsum_plus60=$forfaldsum_plus60+$forfalden_plus60;
			$forfaldsum_plus90=$forfaldsum_plus90+$forfalden_plus90;
			$sum=$sum+$y;
			print "<tr bgcolor=\"$linjebg\">";
			print "<td onClick=\"window.open('rapport.php?rapportart=kontokort&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$kontonr[$x]&konto_til=$kontonr[$x]&submit=ok','kreditorrapport','width=640,height=480,scrollbars=1,resizable=1')\" onMouseOver=\"this.style.cursor = 'pointer'\">$font<small><span title='Klik for detaljer' style=\"text-decoration: underline;\"><a>$kontonr[$x]</a></small></td>";
			print "<td>$font<small>$firmanavn[$x]</small></td>";
			$tmp=dkdecimal($forfalden_plus90);
			print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small>$tmp</small></td>";
			$tmp=dkdecimal($forfalden_plus60);
			print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small>$tmp</small></td>";
			$tmp=dkdecimal($forfalden_plus30);
			print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small>$tmp</small></td>";
			$tmp=dkdecimal($forfalden_plus8);
			print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small>$tmp</small></td>";
			$tmp=dkdecimal($forfalden);
			print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small>$tmp</small></td>";
			$tmp=dkdecimal($y);
			if (abs($y)<0.01) {
				print "<td align=right>$font<a href=\"rapport.php?submit=ok&rapportart=openpost&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til&udlign=$id[$x]\"><small>$tmp</small></a></td>";
			}
			else {print "<td align=right>$font<small>$tmp</small></td>";}
				if (($kontoudtog[$x]=='on')&&($art=="D")) {print "<td align=center><input type=checkbox name=kontoudtog[$x] checked>";}
				elseif($art=="D")  print "<td align=center><input type=checkbox name=kontoudtog[$x]>";
			print "</tr>\n";
		}
		print "<input type=hidden name=rykkerbelob[$x] value=$rykkerbelob>";
	}
	print "<tr><td colspan=10><hr></td></tr>\n";
	print "<tr><td><br></td><td>$font<small>I alt</small></td>";
	$tmp=dkdecimal($forfaldsum_plus90);
	print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small>$tmp</small></td>";
	$tmp=dkdecimal($forfaldsum_plus60);
	print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small>$tmp</small></td>";
	$tmp=dkdecimal($forfaldsum_plus30);
	print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small>$tmp</small></td>";
	$tmp=dkdecimal($forfaldsum_plus8);
	print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small>$tmp</small></td>";
	$tmp=dkdecimal($forfaldsum);
	print "<td align=right>$font<span style='color: rgb(255, 0, 0);'><small>$tmp</small></td>";
	$tmp=dkdecimal($sum);
	print "<td align=right>$font<small>$tmp</small></td>";
				
	print "<input type=hidden name=rapportart value=\"openpost\">";
	print "<input type=hidden name=regnaar value=$regnaar>";
	print "<input type=hidden name=maaned_fra value=$maaned_fra>";
	print "<input type=hidden name=maaned_til value=$maaned_til>";
	print "<input type=hidden name=konto_fra value=$konto_fra>";
	print "<input type=hidden name=konto_til value=$konto_til>";
	print "<input type=hidden name=kontoantal value=$kontoantal>";


	if ($art=='D') print "<tr><td colspan=10 align=center><small><input type=submit value=\"Mail kontoudtog\" name=\"submit\">&nbsp;<input type=submit value=\"Opret rykker\" name=\"submit\"></td></tr>\n";
	print "</form>\n";
	print "<tr><td colspan=10><hr></td></tr>\n";
 	
 	####################################### Rykkeroversigt ##############################################
 	
 	
	if ($art=='D' && db_fetch_array(db_select("select * from ordrer where art LIKE 'R%'"))) {
		print "<tr><td colspan=10><br></td></tr>\n";
		print "<tr><td colspan=10 align=center>$font Rykkeroversigt</td></tr>\n";
		print "<tr><td>$font<small>Ikke bogførte</small></td><td colspan=9><hr></td></tr>\n";
		print "<tr><td>$font<small>L&oslash;benr.</td><td>$font<small>Firmanavn</td><td colspan=2 align=center>$font<small>Dato</td><td align=center>$font<small>Rykkernr</td><td colspan=3 align=right>$font<small>Bel&oslash;b</td></tr>\n";	
		print "<tr><td colspan=10><hr></td></tr>\n";
 		$x=0;
 		$taeller=0;
 		$sum=array();
 		while ($taeller <2) {  
			$sum=array();
			$taeller++;
			if ($taeller==1) {$formnavn='rykker1'; $status= "< 3";}
			else  {$formnavn='rykker2'; $status= ">= 3";}
			print "<form name=$formnavn action=rapport.php method=post>";
 			$q1 = db_select("select * from ordrer where art LIKE 'R%' and status $status order by ordrenr desc");
			$x=0;
			while ($r1 = db_fetch_array($q1)) {
				$rykkernr=substr($r1['art'],-1);
				$belob=dkdecimal($r1['sum']);
				$x++;
				$sum[$x]=0;					
				$q2 = db_select("select * from ordrelinjer where ordre_id = '$r1[id]'");
				while ($r2 = db_fetch_array($q2)) {
#					$sum[$x]=$sum[$x]+$r2['pris'];
					if ($r2[enhed]) {
						$q3 = db_select("select amount, valutakurs from openpost where id = '$r2[enhed]'");
						while ($r3 = db_fetch_array($q3)) {
							if(!$r3['valutakurs']) $r3['valutakurs']=100;
							$sum[$x]=$sum[$x]+$r3['amount']*$r3['valutakurs']/100;
						}		
					} else $sum[$x]=$sum[$x]+$r2['pris'];
				} 
				print "<input type=hidden name=rykker_id[$x] value=$r1[id]>";
# echo "$sum[$x] - ";				
				$belob=dkdecimal($sum[$x]);
				if ($rykkernr==1) $color="#000000";
				elseif ($rykkernr==2) $color="#CC6600";
				elseif ($rykkernr==3) $color="#ff0000";
// 				else $color="#ff00ff";
				if ($linjebg!=$bgcolor) $linjebg=$bgcolor;
				elseif ($linjebg!=$bgcolor5) $linjebg=$bgcolor5;
				print "<tr style=\"background-color:$linjebg ; color: $color;\">";
				print "<td onClick=\"window.open('rykker.php?rykker_id=$r1[id]','rykker','width=640,height=480,scrollbars=1,resizable=1')\" onMouseOver=\"this.style.cursor = 'pointer'\">$font<small><span title='Klik for detaljer' style=\"text-decoration: underline;\"><a>$r1[ordrenr]</a></small></td>";
# echo "$belob<br>";				
				print "<td>$font<small>$r1[firmanavn]</td><td colspan=2 align=center>$font<small>$r1[ordredate]</td><td align=center>$font<small>$rykkernr</td><td colspan=3 align=right>$font<small>$belob</td>";	
				$tmp = $rykkernr+1;
				$tmp = "R".$tmp;
				if (!db_fetch_array(db_select("select * from ordrer where art = '$tmp' and ordrenr = '$r1[ordrenr]'"))) print "<td align=center><input type=checkbox name=rykkerbox[$x]>";
#		if ($art=="D") print "<td align=center><input type=checkbox name=rykkerbox[$x]>";
				print "</tr>\n";
			}
#			print "</form>";
#			print "<form name=$formnavn action=rapport.php method=post>";
			print "<input type=hidden name=rapportart value=\"openpost\">";
			print "<input type=hidden name=regnaar value=$regnaar>";
			print "<input type=hidden name=maaned_fra value=$maaned_fra>";
			print "<input type=hidden name=maaned_til value=$maaned_til>";
			print "<input type=hidden name=konto_fra value=$konto_fra>";
			print "<input type=hidden name=konto_til value=$konto_til>";
			print "<input type=hidden name=rykkerantal value=$x>";
			print "<input type=hidden name=kontoantal value=$x>";
			print "<tr><td colspan=10><hr></td></tr>\n";
			if ($x) {
			if ($taeller==1) print "<tr><td colspan=10 align=center><small><input type=submit value=\"  Slet  \" name=\"submit\" onClick=\"return confirmSubmit()\">&nbsp;";
			else print "<tr><td colspan=10 align=center><small>";
			print "<input type=submit value=\"Udskriv\" name=\"submit\">";
			if ($taeller>1) print "$font &nbsp;<input type=submit value=\"Ny rykker\" name=\"submit\">";
			if ($taeller==1) print "$font &nbsp;<input type=submit value=\"Bogf&oslash;r\" name=\"submit\" onClick=\"return confirmSubmit()\"></td></tr>\n";
			else print "</td></tr>\n";
			}
			if ($taeller==1) print "<tr><td>$font<small>Bogførte</small></td><td colspan=9><hr></td></tr>\n";
			else print "<tr><td colspan=10><hr></td></tr>\n";
			print "</form>\n";
		}
	}
 }
function bogfor_rykker($id) {
// Bemaerk at der ikke traekkes moms ved bogfoering af rykkergebyr - heller ikke selvom gebyret tilhorer en momsbelagt varegruppe.
	global $fakturadate; 
	global $font;
	$sum=0;
	$q = db_select("select antal, pris, rabat from ordrelinjer where ordre_id = $id and vare_id > 0");
	while ($r = db_fetch_array($q)) $sum=$sum+($r['antal']*$r['pris'])-($r['antal']*$r['pris']/100*$r['rabat']);
	if ($sum) db_modify("update ordrer set sum=$sum where id = '$id'");
	$q = db_select("select id, vare_id from ordrelinjer where ordre_id = $id and vare_id!=0");
	if ($r = db_fetch_array($q)) {
		$ordre_linje_id=$r[id];
		$pris = $r[pris];
		if ($vare_id=$r[vare_id]) {
			$q2 = db_select("select gruppe from varer where id = $vare_id");
			$r2 = db_fetch_array($q2);
			$gruppe=$r2['gruppe'];
			$q2 = db_select("select * from grupper where art='VG' and kodenr='$gruppe'");
			$r2 = db_fetch_array($q2);
			$box1=trim($r2[box1]); $box2=trim($r2[box2]); $box3=trim($r2[box3]); $box4=trim($r2[box4]); $box8=trim($r2[box8]); $box9=trim($r2[box9]);
			if ($rbox8!='on') {
				db_modify("update ordrelinjer set bogf_konto=$box4 where id=$ordre_linje_id");
				db_modify("update ordrer set status=3 where id=$id");
#				30.04.2008 Fjernet muligheden for bogføring gennem kassekladde de det giver bøvl v. flere fakt. niveauer.
#				$r = db_fetch_array($q = db_select("select box5 from grupper where art='DIV' and kodenr='2'"));
#				if ($r[box5]=='on') { # saa skal der bogfoeres nu - ellers bogfoeres gennem kassekladden.
					transaktion('begin');
					bogfor_nu($id);
					transaktion('commit');
				}
#			}
			else print "<BODY onLoad=\"javascript:alert('Der er anvendt en lagerf&oslash;rt vare som gebyr - rykker kan ikke bogf&oslash;res')\">";
		}
	} else {
		transaktion('begin');
		bogfor_nu($id);
		transaktion('commit');
	}
}
function bogfor_nu($id)
{
	$d_kontrol=0; 
	$k_kontrol=0;
	$logdate=date("Y-m-d");
	$logtime=date("H:i");
/*	
	$q = db_select("select box1, box2, box3, box4, box5 from grupper where art='RB'");
	if ($r = db_fetch_array($q)) {
		if (trim($r['box3'])=="on") $faktbill=1; 
		else {$faktbill=0;}
		if (trim($r['box4'])=="on") $modtbill=1; 
		else $modtbill=0;
		if (trim($r['box5'])=="on") {
			$no_faktbill=1;
			$faktbill=0;
		}	 
		else $no_faktbill=0;
	}
*/	
	$x=0;
# echo "select * from ordrer where id='$id'<br>";	
$q = db_select("select * from ordrer where id='$id'");
	if ($r = db_fetch_array($q)) {
#		list ($year, $month, $day) = split ('-', $r[fakturadate]);
#		$year=substr($year,-2);
#		$ym=$year.$month;
		$art=$r['art'];
		$konto_id=$r['konto_id'];
		$kontonr=str_replace(" ","",$r['kontonr']);
		$firmanavn=trim($r['firmanavn']);
		$modtagelse=$r['modtagelse'];
		$transdate=($r['fakturadate']);
		$fakturanr=$r['fakturanr'];
		$ordrenr=$r['ordrenr'];
		$valutakurs=$r['valutakurs'];
		$projekt=$r['projekt']*1;
		$refnr;
		if ($r['moms']) {$moms=$r['moms'];}
		else {$moms=round($r['sum']*$r['momssats']/100,2);}
		$sum=$r['sum']+$moms;
		$ordreantal=$x;
		if ($r= db_fetch_array(db_select("select afd from ansatte where navn = '$r[ref]'"))) $afd=$r['afd'];
		$afd=$afd*1; #sikkerhed for at 'afd' har en vaerdi 
		 
		$bilag=0;
/*
		if ($no_faktbill==1) $bilag='0';
		else $bilag=trim($fakturanr);
		if (substr($art,1,1)=='K') $beskrivelse ="Kreditnota - ".$fakturanr;
		else $beskrivelse ="Faktura - ".$fakturanr;
*/		
		$beskrivelse="Rykkergebyr, rykker nr: $ordrenr<br>";	
		if ($valutakurs) $sum=$sum*$valutakurs/100; # Omregning til DKR.

		if ($sum) db_modify("insert into openpost (konto_id, konto_nr, faktnr, refnr, amount, beskrivelse, udlignet, transdate, kladde_id) values ('$konto_id', '$kontonr', '$fakturanr', '$id','$sum', '$beskrivelse', '0', '$transdate', '0')");
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'"));
		$r = db_fetch_array(db_select("select box2 from grupper where art = 'DG' and kodenr='$r[gruppe]'"));
		$kontonr=$r['box2']; # Kontonr ændres fra at være leverandørkontonr til finanskontonr

		if ($sum>0) {$debet=$sum; $kredit='0';}
		else {$debet='0'; $kredit=$sum*-1;}
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
		if ($sum)	db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt', '$id')");
		$y=0;
		$bogf_konto = array();
		$q = db_select("select * from ordrelinjer where ordre_id=$id;");
		while ($r = db_fetch_array($q)) {
			if (!in_array($r['bogf_konto'], $bogf_konto)) {
			$y++;
			$bogf_konto[$y]=$r['bogf_konto'];
				$pris[$y]=$r['pris']*$r['antal']-round(($r['pris']*$r['antal']*$r['rabat']/100),2);
			}
			else {
				for ($a=1; $a<=$y; $a++) {
					if ($bogf_konto[$a]==$r['bogf_konto']) {
						$pris[$a]=$pris[$a]+($r['pris']*$r['antal']-round(($r['pris']*$r['antal']*$r['rabat']/100),2));
					}
				}		 
			}
		}
		$ordrelinjer=$y;
		for ($y=1;$y<=$ordrelinjer;$y++) {
			if ($bogf_konto[$y]) {
				if ($pris[$y]>0) {$kredit=$pris[$y];$debet=0;}
				else {$kredit=0; $debet=$pris[$y]*-1;}
				if ($valutakurs) {$kredit=$kredit*$valutakurs/100;$debet=$debet*$valutakurs/100;} # Omregning til DKR.
				$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
				if ($pris[$y]) db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', '$bogf_konto[$y]', '$fakturanr', '$debet', '$kredit', '0','$afd', '$logdate', '$logtime', '$projekt', '$id')");
			}
		}
/*		
		$query = db_select("select gruppe from adresser where id='$konto_id';");
		$row = db_fetch_array($query);
		$query = db_select("select box1 from grupper where art='DG' and kodenr='$row[gruppe]';");
		$row = db_fetch_array($query);
		$box1=substr(trim($row[box1]),1,1);
		$query = db_select("select box1 from grupper where art='SM' and kodenr='$box1'");
		$row = db_fetch_array($query);
		$box1=trim($row[box1]);
		if ($moms > 0) {$kredit=$moms; $debet='0';}
		else {$kredit='0'; $debet=$moms*-1;} 
		if ($valutakurs) {$kredit=$kredit*$valutakurs/100;$debet=$debet*$valutakurs/100;} # Omregning til DKR.
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
		db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', '$box1', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt', '$id')");
*/		
		db_modify("update ordrer set status=4 where id=$id");
		db_modify("delete from ordrelinjer where ordre_id=$id and posnr < 0");
	}
	$d_kontrol=round($d_kontrol,2);
	$k_kontrol=round($k_kontrol,2);
	if ($d_kontrol!=$k_kontrol) {
		print "<BODY onLoad=\"javascript:alert('Der er konstateret en uoverensstemmelse i posteringssummen, kontakt administrator')\">";
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		exit;
	} 
}

?>