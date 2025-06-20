<?php
// --------includes/openpost.php ----- lap 3.0.6 ---- 2010.09.26 ---------------------------
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
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------
function openpost($dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $art) {
	?>
	<script LANGUAGE="JavaScript">
	<!--
	function confirmSubmit(tekst)
	{
		var agree=confirm(tekst);
		if (agree) return true ;
		else return false ;
	}
	// -->
	</script>
	<script LANGUAGE="JavaScript" SRC="../javascript/overlib.js"></script>
	<?php

#echo "KF1 $konto_fra<br>";

	$forfaldsum=NULL;$forfaldsum_plus8=NULL;$forfaldsum_plus30=NULL;$forfaldsum_plus60=NULL;$forfaldsum_plus90=NULL;
	$linjebg=NULL;$tmp1=NULL;$tmp2=NULL;
	
	global $bgcolor;
	global $bgcolor5;
	global $top_bund;
	global $md;
	global $kontoudtog;
	global $ny_rykker;
	global $jsvars;
	global $popup;
	global $sprog_id;

	$fromdate=usdate($dato_fra);
	$todate=usdate($dato_til);
	
	$skjul_aabenpost=if_isset($_GET['skjul_aabenpost']);
	$skjul_aaben_rykker=if_isset($_GET['skjul_aaben_rykker']);
	$skjul_bogfort_rykker=if_isset($_GET['skjul_bogfort_rykker']);
	$skjul_afsluttet_rykker=if_isset($_GET['skjul_afsluttet_rykker']);
	
	if ($skjul_aabenpost) db_modify("update grupper set box7='$skjul_aabenpost' where art='DRV' and kodenr='1'",__FILE__ . " linje " . __LINE__);
	if ($skjul_aaben_rykker) db_modify("update grupper set box8='$skjul_aaben_rykker' where art='DRV' and kodenr='1'",__FILE__ . " linje " . __LINE__);
	if ($skjul_bogfort_rykker) db_modify("update grupper set box9='$skjul_bogfort_rykker' where art='DRV' and kodenr='1'",__FILE__ . " linje " . __LINE__);
	if ($skjul_afsluttet_rykker) db_modify("update grupper set box10='$skjul_afsluttet_rykker' where art='DRV' and kodenr='1'",__FILE__ . " linje " . __LINE__);
	
	db_modify("update ordrer set art = 'R1' where art = 'RB'",__FILE__ . " linje " . __LINE__); # 20091012 - er overfloedig
	
	if ($r=db_fetch_array(db_select("select * from grupper where art = 'DRV' and kodenr = '1' order by box2",__FILE__ . " linje " . __LINE__))){
		$skjul_aabenpost=$r['box7'];
		$skjul_aaben_rykker=$r['box8'];
		$skjul_bogfort_rykker=$r['box9'];
		$skjul_afsluttet_rykker=$r['box10'];
	}
	if ($ny_rykker) {
#		echo "1;URL=rapport.php?ny_rykker=1&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&rapportart=$rapportart"; 
		print "<meta http-equiv=\"refresh\" content=\"1;URL=rapport.php?ny_rykker=1&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&rapportart=$rapportart\">"; 
	}
/*
	if ($dato_fra && strstr($dato_fra," ")) list ($x,$tmp1) = explode(" ",$dato_fra);
	if ($dato_til && strstr($dato_til," ")) list ($x,$tmp2) = explode(" ",$dato_til);
	if ($tmp1 && $tmp2) {
		$dato_fra=$tmp1;
		$dato_til=$tmp2;
	}
*/	
	$maaned_fra=trim($maaned_fra);
	$maaned_til=trim($maaned_til);

	print "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
	print "<tr><td width=100% height=\"8\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
#	print "<td width=\"10%\" $top_bund><a accesskey=l href=\"rapport.php?rapportart=openpost&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til\">Luk</a></td>";
	print "<td width=\"10%\" $top_bund><a accesskey=l href=\"rapport.php\">Luk</a></td>";
	print "<td width=\"80%\" $top_bund>Rapport - $rapportart</td>";
	print "<td width=\"10%\" $top_bund>";
	if ($skjul_aabenpost=='on') print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$kotno_fra&konto_til=$konto_til&skjul_aabenpost=off>Vis</a><td></tr>";
	else print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_aabenpost=on>Skjul</a><td></tr>";	
#	<a accesskey=l href=\"rapport.php?rapportart=openpost&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til\"><br></a></td>";
	print "</tbody></table></td></tr>\n"; #B slut

#echo "XX $dato_fra,$dato_til,$konto_fra,$konto_til,$art<br>";
	if ($skjul_aabenpost!='on') vis_aabne_poster($dato_fra,$dato_til,$konto_fra,$konto_til,$art);

 	####################################### Rykkeroversigt ##############################################
 	
# if ($skjul_aaben_rykker!='on' || $skjul_bogfort_rykker!='on' || $skjul_afsluttet_rykker!='on') 
	if (is_numeric($konto_fra) && is_numeric($konto_til)) {
		$tekst = "select * from ordrer where ".nr_cast('kontonr').">='$konto_fra' and ".nr_cast('kontonr')."<='$konto_til' and art LIKE 'R%' order by ".nr_cast('kontonr')."";
	} elseif ($konto_fra && $konto_fra!='*') {
		$konto_fra=str_replace("*","%",$konto_fra);
		$tmp1=strtolower($konto_fra);
		$tmp2=strtoupper($konto_fra);
		$tekst = "select * from ordrer where (firmanavn like '$konto_fra' or lower(firmanavn) like '$tmp1' or upper(firmanavn) like '$tmp2') and art LIKE 'R%' order by firmanavn";
	}	else $tekst = "select * from ordrer where art LIKE 'R%' order by firmanavn";
#	echo "tekst $tekst<br>";



	if ($art=='D' && db_fetch_array(db_select("$tekst",__FILE__ . " linje " . __LINE__))) {
#	if ($art=='D' && db_fetch_array(db_select("select * from ordrer where art LIKE 'R%'",__FILE__ . " linje " . __LINE__))) {
#		print "<tr><td><br></td></tr>\n";
 		$x=0;
 		$taeller=0;
 		$sum=array();
 		while ($taeller <3) {  
			$sum=array();
			$taeller++;
			print "<tr><td><table width=100% cellpadding=\"0\" cellspacing=\"3\" border=\"0\"><tbody>\n";
			if ($taeller==1) {
				print "<tr><td width=10% align=center $top_bund><br></td><td width=80% align=center $top_bund>&Aring;bne&nbsp;rykkere</td><td width=10% align=center $top_bund>\n";
				if ($skjul_aaben_rykker=='on') print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$kotno_fra&konto_til=$konto_til&skjul_aaben_rykker=off>Skjul</a><td></tr>";
				else print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_aaben_rykker=on>Vis</a><td></tr>";	
			} elseif ($taeller==2) {
				print "<tr><td width=10% align=center $top_bund><br></td><td width=80% align=center $top_bund>Bogf&oslash;rte&nbsp;rykkere</td><td width=10% align=center $top_bund>\n";
				if ($skjul_bogfort_rykker=='on') print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$kotno_fra&konto_til=$konto_til&skjul_bogfort_rykker=off>Skjul</a><td></tr>";
				else print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_bogfort_rykker=on>Vis</a><td></tr>";	
			} else  {
				print "<tr><td width=10% align=center $top_bund><br></td><td width=80% align=center $top_bund>Afsluttede&nbsp;rykkere</td><td width=10% align=center $top_bund>\n";
				if ($skjul_afsluttet_rykker=='on') print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$kotno_fra&konto_til=$konto_til&skjul_afsluttet_rykker=off>Skjul</a><td></tr>";
				else print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_afsluttet_rykker=on>Vis</a><td></tr>";	
			}
			print "</tbody></table></td></tr>";
			if (($taeller==1 && $skjul_aaben_rykker=='on')||($taeller==2 && $skjul_bogfort_rykker=='on')||($taeller==3 && $skjul_afsluttet_rykker=='on')) {
			print "<tr><td width=100%>";
			print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #B
			print "<tr><td>L&oslash;benr.</td><td>Firmanavn</td><td colspan=2 align=center>Dato</td><td align=center>Rykkernr</td><td colspan=3 align=right>Bel&oslash;b</td></tr>\n";	
			print "<tr><td colspan=9><hr></td></tr>\n";
			if ($taeller==1) {$formnavn='rykker1'; $status= "< 3";}
			else  {$formnavn='rykker2'; $status= ">= 3";}
			if ($taeller==3) $betalt="and betalt = 'on'";
			else $betalt="and betalt != 'on'";
			print "<form name=$formnavn action=rapport.php method=post>";

			if (is_numeric($konto_fra) && is_numeric($konto_til)) {
				$tekst = "select * from ordrer where ".nr_cast('kontonr').">='$konto_fra' and ".nr_cast('kontonr')."<='$konto_til' and art LIKE 'R%' $betalt and status $status order by ".nr_cast('kontonr')."";
			} elseif ($konto_fra && $konto_fra!='*') {
				$konto_fra=str_replace("*","%",$konto_fra);
				$tmp1=strtolower($konto_fra);
				$tmp2=strtoupper($konto_fra);
				$tekst = "select * from ordrer where (firmanavn like '$konto_fra' or lower(firmanavn) like '$tmp1' or upper(firmanavn) like '$tmp2') and art LIKE 'R%' $betalt and status $status order by firmanavn";
			}	else $tekst = "select * from ordrer where art LIKE 'R%' $betalt and status $status order by firmanavn";
#			echo "tekst $tekst<br>";

			$q1 = db_select("$tekst",__FILE__ . " linje " . __LINE__);
#			$q1 = db_select("select * from ordrer where art LIKE 'R%' $betalt and status $status order by ordrenr desc",__FILE__ . " linje " . __LINE__);
			$x=0;
			while ($r1 = db_fetch_array($q1)) {
				$rykkernr=substr($r1['art'],-1);
#				$belob=dkdecimal($r1['sum']);
				$x++;
				$sum[$x]=0;
				$udlignet=1;
				$delsum=0;
				$q2 = db_select("select * from ordrelinjer where ordre_id = '$r1[id]'",__FILE__ . " linje " . __LINE__);
				while ($r2 = db_fetch_array($q2)) {
#					$sum[$x]=$sum[$x]+$r2['pris'];
						if ($r2['enhed']) {
						$q3 = db_select("select udlignet, amount, valutakurs from openpost where id = '$r2[enhed]'",__FILE__ . " linje " . __LINE__);
						while ($r3 = db_fetch_array($q3)) {
							if (!$r3['udlignet']) $udlignet=0;
							else $delsum=$r3['amount']*$r3['valutakurs']/100;;
							if(!$r3['valutakurs']) $r3['valutakurs']=100;
							$sum[$x]=$sum[$x]+$r3['amount']*$r3['valutakurs']/100;
						}		
					} else $sum[$x]=$sum[$x]+$r2['pris'];
				}
				print "<input type=hidden name=rykker_id[$x] value=$r1[id]>";
				$belob=dkdecimal($sum[$x]);
				if ($rykkernr==1) $color="#000000";
				elseif ($rykkernr==2) $color="#CC6600";
				elseif ($rykkernr==3) $color="#ff0000";
				if ($linjebg!=$bgcolor) $linjebg=$bgcolor;
				elseif ($linjebg!=$bgcolor5) $linjebg=$bgcolor5;
				print "<tr style=\"background-color:$linjebg ; color: $color;\">";
				print "<td onClick=\"window.open('rykker.php?rykker_id=$r1[id]','rykker','$jsvars')\" onMouseOver=\"this.style.cursor = 'pointer'\"><span title='Klik for detaljer' style=\"text-decoration: underline;\"><a>$r1[ordrenr]</a></td>";
				print "<td>$r1[firmanavn]</td><td colspan=2 align=center>$r1[ordredate]</td><td align=center>$rykkernr</td>";
				if ($udlignet || $delsum >= $sum[$x]) {
					$color="#00aa00";
					$title="Alle poster på rykkeren er betalt";
				} elseif ($delsum) {
					$color="#0000aa";
					$title="Rykkeren er delvist betalt med kr ".dkdecimal($delsum)."";
				} else $title="";
				print "<td colspan=3 align=right style=\"background-color:$linjebg ; color: $color;\" title='$title'>$belob</td>";	
				$tmp = $rykkernr+1;
				$tmp = "R".$tmp;
				if (!db_fetch_array(db_select("select * from ordrer where art = '$tmp' and ordrenr = '$r1[ordrenr]' and betalt != 'on'",__FILE__ . " linje " . __LINE__))) print "<td align=center><input type=checkbox name=rykkerbox[$x]>";
				else db_modify("update ordrer set betalt = 'on' where id = '$r1[id]'",__FILE__ . " linje " . __LINE__);
 
				print "</tr>\n";
			}
			print "<input type=hidden name=rapportart value=\"openpost\">";
			print "<input type=hidden name=regnaar value=$regnaar>";
			print "<input type=hidden name=dato_fra value=$dato_fra>";
			print "<input type=hidden name=dato_til value=$dato_til>";
			print "<input type=hidden name=konto_fra value=$konto_fra>";
			print "<input type=hidden name=konto_til value=$konto_til>";
			print "<input type=hidden name=rykkerantal value=$x>";
			print "<input type=hidden name=kontoantal value=$x>";
			if ($x) {
				print "<tr><td colspan=10><hr></td></tr>\n";
				if ($taeller==1) print "<tr><td colspan=10 align=center><input type=submit value=\"  Slet  \" name=\"submit\" onClick=\"return confirmSubmit('Slet valgte ?')\">&nbsp;";
				else print "<tr><td colspan=10 align=center>";
				print "<input type=submit value=\"Udskriv\" name=\"submit\" onClick=\"return confirmSubmit('Udskriv valgte ?')\">";
				if ($taeller==2) {
					print " &nbsp;<span title='Registrerer rykker som afsluttet og fjernde den fra listen'><input type=submit value=\"Afslut\" name=\"submit\" onClick=\"return confirmSubmit('Afslut valgte ?')\"></span>";
					print " &nbsp;<input type=submit value=\"Ny rykker\" name=\"submit\">";
				}
				if ($taeller==1) print " &nbsp;<input type=submit value=\"Bogf&oslash;r\" name=\"submit\" onClick=\"return confirmSubmit('Bogf&oslash;r valgte ?')\"></td></tr>\n";
				else print "</td></tr>\n";
				}
#		if ($taeller==1) print "<tr><td>Bogf&oslash;rte</td><td colspan=9><hr></td></tr>\n";
#			elseif ($taeller==2) print "<tr><td>Afsluttede</td><td colspan=9><hr></td></tr>\n";
#			else print "<tr><td colspan=10><hr></td></tr>\n";
			print "</form>\n";
			print "</tbody></table></td></tr>";
			}}
	}
}

function vis_aabne_poster($dato_fra,$dato_til,$konto_fra,$konto_til,$art) {
	
	global $bgcolor;
	global $bgcolor5;
	
	print "<tr><td><table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>\n";
	print "<tr><td>Kontonr</td><td>Firmanavn</td><td align=right>>90</td><td align=right>60-90</td><td align=right>30-60</td><td align=right>8-30</td><td align=right>0-8</td><td align=right>I alt</td><tr>";

	$currentdate=date("Y-m-d");
	$fromdate=usdate($dato_fra);
	$todate=usdate($dato_til);

# echo "SS $fromdate $todate KF $konto_fra<br>"; 

/*
	# Finder start og slut paa regnskabsaar
	for ($x=1; $x<=12; $x++) {
		if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
		if ($maaned_til==$md[$x]){$maaned_til=$x;}
	}

	$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
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

if (!is_numeric($startmaaned)) {
	if (strstr($startmaaned," ")) list($tmp,$startmaaned)=explode(" ",$startmaaned);
	if (!is_numeric($startmaaned)) list ($tmp,$startmaaned)=explode(" ",find_maaned_nr($startmaaned));
}
if (!is_numeric($slutmaaned)) {
	if (strstr($slutmaaned," ")) list($tmp,$slutmaaned)=explode(" ",$slutmaaned);
	if (!is_numeric($slutmaaned)) list ($tmp,$slutmaaned)=explode(" ",find_maaned_nr($slutmaaned));
}

	
	while (!checkdate($slutmaaned,$slutdato,$slutaar))	{
		$slutdato=$slutdato-1;
		if ($slutdato<28) break;
	}

#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}


$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;
*/ 


#$regnslut = "2005-05-04"; 
	print "<form name=aabenpost action=rapport.php method=post>";
	print "<tr><td colspan=10><hr></td></tr>\n";
		
	$x=0;

# echo "KF $konto_fra<br>";

	if (is_numeric($konto_fra) && is_numeric($konto_til)) {
		$tekst = "select * from adresser where ".nr_cast('kontonr').">='$konto_fra' and ".nr_cast('kontonr')."<='$konto_til' and art = 'D' order by ".nr_cast('kontonr')."";
	} elseif ($konto_fra && $konto_fra!='*') {
		$konto_fra=str_replace("*","%",$konto_fra);
		$tmp1=strtolower($konto_fra);
		$tmp2=strtoupper($konto_fra);
		$tekst = "select * from adresser where (firmanavn like '$konto_fra' or lower(firmanavn) like '$tmp1' or upper(firmanavn) like '$tmp2') and art = 'D' order by firmanavn";
	}	else $tekst = "select * from adresser where art = '$art' order by firmanavn";
# echo "tekst $tekst<br>";
	$kontonr=array();
	$x=0;
	$q=db_select("$tekst",__FILE__ . " linje " . __LINE__);

#	if ($konto_fra && $konto_til) $tmp=nr_cast('kontonr').">='$konto_fra' and ".nr_cast('kontonr')."<='$konto_til' and ";
#	elseif ($konto_fra) $tmp=nr_cast('kontonr').">='$konto_fra' and ";
#	elseif ($konto_til) $tmp=nr_cast('kontonr')."<='$konto_til'and ";
#	else $tmp="";
#	$q = db_select("select * from adresser where $tmp art = '$art' order by firmanavn",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$konto_id[$x]=$r['id'];
		print "<input type=hidden name=konto_id[$x] value=$konto_id[$x]>";
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
		$faktnr=array();
		$f=0;
		if ($art=='D') $tmp="";
		else $tmp="desc";

#		if ($fromdate && $todate) $q=db_select("select * from openpost where transdate>='$fromdate' and transdate<='$todate' and konto_id='$konto_id[$x]'",__FILE__ . " linje " . __LINE__);
#		elseif ($todate) $q=db_select("select * from openpost where transdate<='$todate' and konto_id='$konto_id[$x]'",__FILE__ . " linje " . __LINE__);
#		else $q=db_select("select * from openpost where konto_id='$konto_id[$x]'",__FILE__ . " linje " . __LINE__);

#		if ($fromdate && $todate) $tekst="select * from openpost where transdate>='$fromdate' and transdate<='$todate' and konto_id='$konto_id[$x]' order by faktnr,amount $tmp";
		if ($todate) $tekst="select * from openpost where transdate<='$todate' and konto_id='$konto_id[$x]' order by faktnr,amount $tmp";
		else $tekst="select * from openpost where konto_id='$konto_id[$x]' order by faktnr,amount $tmp";
		$q=db_select("$tekst",__FILE__ . " linje " . __LINE__);

#		if ($regnaar) $q=db_select("select * from openpost where konto_id=$id[$x] and transdate <= '$regnslut' order by faktnr,amount $tmp",__FILE__ . " linje " . __LINE__);
#		else $q=db_select("select * from openpost where konto_id=$id[$x] and udlignet!='1' order by faktnr,amount $tmp",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if ($r['udlignet']!=1 || ($r['transdate'] <= $todate && $r['udlign_date'] && $r['udlign_date'] > $todate)) {
				if ($r['faktnr'] && !in_array($r['faktnr'],$faktnr)) {
					$f++;
					$faktnr[$f]=$r['faktnr'];
					$forfaldsdag=$r['forfaldsdate'];
				} 
				elseif (!$r['faktnr']) $forfaldsdag=$r['transdate'];
				$oid=$r['id'];
				
				$transdate=$r['transdate'];
				
				if ($r['valuta']) $valuta=$r['valuta']; # <- 2009.05.05
				else $valuta='DKK';
				if ($r['valutakurs']) $valutakurs=$r['valutakurs'];
				else $valutakurs=100;
				$udlignet="0";
				$amount=$r['amount'];
				if ($amount > 0) $amount+=0.0001;
				else $amount-=0.0001;
				if (!$forfaldsdag && $art=='D' && $amount < 0) $forfaldsdag=$r['transdate'];
				elseif (!$forfaldsdag && $art=='K' && $amount > 0) $forfaldsdag=$r['transdate'];
				elseif (!$forfaldsdag) $forfaldsdag=$r['forfaldsdate'];
				if ($r['faktnr'] && !$r['kladde_id'] && ($amount > 0 || ($amount < 0 && strstr($r['beskrivelse'],"Lev. fakt"))) && $r['refnr']>1) { #rettet 20090715
					if ($r2=db_fetch_array(db_select("select betalingsbet, betalingsdage from ordrer where id='$r[refnr]' and fakturanr = '$r[faktnr]'",__FILE__ . " linje " . __LINE__))){
						$betalingsbet[$x]=trim($r2['betalingsbet']);
						$betalingsdage[$x]=trim($r2['betalingsdage']);
						$tmp=usdate(forfaldsdag($transdate, $betalingsbet[$x], $betalingsdage[$x]));
						if ($tmp!=$forfaldsdag) {
							$forfaldsdag=$tmp;
							db_modify("update openpost set forfaldsdate = '$forfaldsdag' where id='$oid'",__FILE__ . " linje " . __LINE__);
						}
					}
				} #elseif () 
				$amount=$amount*$valutakurs/100;
				if ($amount>0) $amount=round($amount+0.0001,2);
				else $amount=round($amount-0.0001,2);
### nedenstående er indført grundet en fejl i 2.0.3 som skrev forkert forfaldsdato i openpost	og fjernet i 2.0.8.
#				$tmp=usdate(forfaldsdag($transdate, $betalingsbet[$x], $betalingsdage[$x]));
#				if ($tmp && !$forfaldsdag) db_modify("update openpost set forfaldsdate='$tmp' where id='$oid'",__FILE__ . " linje " . __LINE__);
#				$forfaldsdag=$tmp;
################
				$fakt_utid=strtotime($transdate);
				$forf_utid=strtotime($forfaldsdag);
				$dage=round(($forf_utid-$fakt_utid)/86400,0);
				$forfaldsdag_plus8=usdate(forfaldsdag($transdate, 'netto',$dage+8));
				$forfaldsdag_plus30=usdate(forfaldsdag($transdate, 'netto',$dage+30));
				$forfaldsdag_plus60=usdate(forfaldsdag($transdate, 'netto',$dage+60));
				$forfaldsdag_plus90=usdate(forfaldsdag($transdate, 'netto',$dage+90));
				if ($forfaldsdag<$currentdate){$rykkerbelob=$rykkerbelob+$amount;}
				if (($forfaldsdag<$currentdate)&&($forfaldsdag_plus8>$currentdate)){$forfalden=$forfalden+$amount;}
				if (($forfaldsdag_plus8<=$currentdate)&&($forfaldsdag_plus30>$currentdate)){$forfalden_plus8=$forfalden_plus8+$amount;}
				if (($forfaldsdag_plus30<=$currentdate)&&($forfaldsdag_plus60>$currentdate)){$forfalden_plus30=$forfalden_plus30+$amount;}
				if (($forfaldsdag_plus60<=$currentdate)&&($forfaldsdag_plus90>$currentdate)){
					$forfalden_plus60=$forfalden_plus60+$amount;
				}
				if ($forfaldsdag_plus90<=$currentdate){
					$forfalden_plus90=$forfalden_plus90+$amount;
				}
			$y=$y+$amount;
			}
		}
		($y>0) ? $y=round($y+0.0001,2) : $y=round($y-0.0001,2);
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
			if ($popup) print "<td onClick=\"window.open('rapport.php?rapportart=kontokort&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$kontonr[$x]&konto_til=$kontonr[$x]&submit=ok','kreditorrapport','$jsvars')\" onMouseOver=\"this.style.cursor = 'pointer'\"><a>";
			else print "<td><a href=rapport.php?rapportart=kontokort&kilde=openpost&kto_fra=$konto_fra&kilde_kto_til=$konto_til&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$kontonr[$x]&konto_til=$kontonr[$x]&submit=ok>";
			print "<span title='Klik for detaljer' style=\"text-decoration: underline;\">$kontonr[$x]</span></a></td>";
			print "<td>$firmanavn[$x]</td>";
/*	
	if ($forfalden_plus90 != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfalden_plus90);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	if ($forfalden_plus60 != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfalden_plus60);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	if ($forfalden_plus60 != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfalden_plus30);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	if ($forfalden_plus30 != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfalden_plus8);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	if ($forfalden != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfalden);
*/
			if (($art=='D' && $forfalden_plus90 > 0) || ($art=='K' && $forfalden_plus90 < 0)) $color="rgb(255, 0, 0)";
			else $color="rgb(0, 0, 0)";
			$tmp=dkdecimal($forfalden_plus90);
			print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			if (($art=='D' && $forfalden_plus60 > 0) || ($art=='K' && $forfalden_plus60 < 0)) $color="rgb(255, 0, 0)";
			else $color="rgb(0, 0, 0)";
			$tmp=dkdecimal($forfalden_plus60);
			print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			if (($art=='D' && $forfalden_plus30 > 0) || ($art=='K' && $forfalden_plus30 < 0)) $color="rgb(255, 0, 0)";
			else $color="rgb(0, 0, 0)";
			$tmp=dkdecimal($forfalden_plus30);
			print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			if (($art=='D' && $forfalden_plus8 > 0) || ($art=='K' && $forfalden_plus8 < 0)) $color="rgb(255, 0, 0)";
			else $color="rgb(0, 0, 0)";
			$tmp=dkdecimal($forfalden_plus8);
			print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			if (($art=='D' && $forfalden > 0) || ($art=='K' && $forfalden < 0)) $color="rgb(255, 0, 0)";
			else $color="rgb(0, 0, 0)";
			$tmp=dkdecimal($forfalden);
			print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			$tmp=dkdecimal($y);
			if (abs($y)<0.01) {
				print "<td align=right title=\"Klik her for at udligne &aring;bne poster\"><a href=\"rapport.php?submit=ok&rapportart=openpost&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&udlign=$konto_id[$x]\">$tmp</a></td>";
			}
			else {print "<td align=right>$tmp</td>";}
				if (($kontoudtog[$x]=='on')&&($art=="D")) {print "<td align=center><input type=checkbox name=kontoudtog[$x] checked>";}
				elseif($art=="D")  print "<td align=center><input type=checkbox name=kontoudtog[$x]>";
			print "</tr>\n";
		}
		print "<input type=hidden name=rykkerbelob[$x] value=$rykkerbelob>";
	}
	print "<tr><td colspan=10><hr></td></tr>\n";
	print "<tr><td><br></td><td>I alt</td>";
	
	if ($forfaldsum_plus90 != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfaldsum_plus90);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	if ($forfaldsum_plus60 != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfaldsum_plus60);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	if ($forfaldsum_plus60 != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfaldsum_plus30);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	if ($forfaldsum_plus30 != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfaldsum_plus8);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	if ($forfaldsum != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfaldsum);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	$color="rgb(0, 0, 0)";
	$tmp=dkdecimal($sum);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
				
	print "<input type=hidden name=rapportart value=\"openpost\">";
	print "<input type=hidden name=regnaar value=$regnaar>";
	print "<input type=hidden name=dato_fra value=$dato_fra>";
	print "<input type=hidden name=dato_til value=$dato_til>";
	print "<input type=hidden name=konto_fra value=$konto_fra>";
	print "<input type=hidden name=konto_til value=$konto_til>";
	print "<input type=hidden name=kontoantal value=$kontoantal>";

	if ($art=='D') print "<tr><td colspan=10 align=center><span title=\"Klik her for at maile kontoudtog til de modtagere som er afm&aelig;rket herover\"><input type=submit value=\"Mail kontoudtog\" name=\"submit\"></span>&nbsp;
		<span title='Klik her for at oprette rykker til de som er afm&aelig;rkede herover'><input type=submit value=\"Opret rykker\" name=\"submit\"></span>&nbsp;
		<span onmouseover=\"return overlib('".findtekst(242,$sprog_id)."', WIDTH=800);\" onmouseout=\"return nd();\"><input type=submit value=\"Ryk alle\" name=\"submit\"></span></td></tr>\n";
	print "</form>\n";
	print "<tr><td colspan=10><hr></td></tr>\n";
	print "</tbody></table>";
} #endfunc vis_aabne_poster
####################################################################################### 
 function bogfor_rykker($id) {
// Bemaerk at der ikke traekkes moms ved bogfoering af rykkergebyr - heller ikke selvom gebyret tilhorer en momsbelagt varegruppe.
	global $fakturadate; 
	$fejl=0;
	$sum=0;
	$q = db_select("select antal, pris, rabat from ordrelinjer where ordre_id = '$id' and vare_id > '0'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $sum=$sum+($r['antal']*$r['pris'])-($r['antal']*$r['pris']/100*$r['rabat']);
	if ($sum) db_modify("update ordrer set sum=$sum where id = '$id'",__FILE__ . " linje " . __LINE__);
	$x=0;
	$q = db_select("select id, vare_id from ordrelinjer where ordre_id = '$id' and vare_id > '0'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$ordre_linje_id[$x]=$r['id'];
		$pris[$x] = $r['pris'];
		if ($vare_id[$x]=$r['vare_id']) {
			$q2 = db_select("select gruppe from varer where id = $vare_id[$x]",__FILE__ . " linje " . __LINE__);
			$r2 = db_fetch_array($q2);
			$gruppe[$x]=$r2['gruppe'];
			$q2 = db_select("select * from grupper where art='VG' and kodenr='$gruppe[$x]'",__FILE__ . " linje " . __LINE__);
			$r2 = db_fetch_array($q2);
			$box1[$x]=trim($r2['box1']); $box2[$x]=trim($r2['box2']); $box3[$x]=trim($r2['box3']); $box4[$x]=trim($r2['box4']); $box8[$x]=trim($r2['box8']); $box9[$x]=trim($r2['box9']);
			if ($rbox8[$x]!='on') {
				db_modify("update ordrelinjer set bogf_konto=$box4[$x] where id=$ordre_linje_id[$x]",__FILE__ . " linje " . __LINE__);
				db_modify("update ordrer set status=3 where id=$id",__FILE__ . " linje " . __LINE__);
#					transaktion('begin');
#					bogfor_nu($id);
#					transaktion('commit');
				} else {
				$fejl=1;
				print "<BODY onLoad=\"javascript:alert('Der er anvendt en lagerf&oslash;rt vare som gebyr - rykker kan ikke bogf&oslash;res')\">";
			}
		}
	} 
	if (!$fejl) {
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
	$q = db_select("select box1, box2, box3, box4, box5 from grupper where art='RB'",__FILE__ . " linje " . __LINE__);
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
	$q = db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
#		list ($year, $month, $day) = explode ('-', $r[fakturadate]);
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
		if ($r= db_fetch_array(db_select("select afd from ansatte where navn = '$r[ref]'",__FILE__ . " linje " . __LINE__))) $afd=$r['afd'];
		$afd=$afd*1; #sikkerhed for at 'afd' har en vaerdi 
		 
		$bilag=0;
/*
		if ($no_faktbill==1) $bilag='0';
		else $bilag=trim($fakturanr);
		if (substr($art,1,1)=='K') $beskrivelse ="Kreditnota - ".$fakturanr;
		else $beskrivelse ="Faktura - ".$fakturanr;
*/		
		$beskrivelse="Gebyr mm. fra tidligere rykker";	
		if ($valutakurs) $sum=$sum*$valutakurs/100; # Omregning til DKR.

		if ($sum) db_modify("insert into openpost (konto_id, konto_nr, faktnr, refnr, amount, beskrivelse, udlignet, transdate, kladde_id) values ('$konto_id', '$kontonr', '$fakturanr', '$id','$sum', '$beskrivelse', '0', '$transdate', '0')",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$r = db_fetch_array(db_select("select box2 from grupper where art = 'DG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
		$kontonr=$r['box2']; # Kontonr ændres fra at være leverandørkontonr til finanskontonr

		if ($sum>0) {$debet=$sum; $kredit='0';}
		else {$debet='0'; $kredit=$sum*-1;}
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
		if ($sum)	db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt', '$id')",__FILE__ . " linje " . __LINE__);
		$y=0;
		$bogf_konto = array();
		$q = db_select("select * from ordrelinjer where ordre_id=$id;",__FILE__ . " linje " . __LINE__);
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
				if ($pris[$y]) db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', '$bogf_konto[$y]', '$fakturanr', '$debet', '$kredit', '0','$afd', '$logdate', '$logtime', '$projekt', '$id')",__FILE__ . " linje " . __LINE__);
			}
		}
/*		
		$query = db_select("select gruppe from adresser where id='$konto_id';",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$query = db_select("select box1 from grupper where art='DG' and kodenr='$row[gruppe]';",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$box1=substr(trim($row[box1]),1,1);
		$query = db_select("select box1 from grupper where art='SM' and kodenr='$box1'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$box1=trim($row[box1]);
		if ($moms > 0) {$kredit=$moms; $debet='0';}
		else {$kredit='0'; $debet=$moms*-1;} 
		if ($valutakurs) {$kredit=$kredit*$valutakurs/100;$debet=$debet*$valutakurs/100;} # Omregning til DKR.
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
		db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', '$box1', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt', '$id')",__FILE__ . " linje " . __LINE__);
*/		
		db_modify("update ordrer set status=4 where id=$id",__FILE__ . " linje " . __LINE__);
		db_modify("delete from ordrelinjer where ordre_id=$id and posnr < 0",__FILE__ . " linje " . __LINE__);
	}
	$d_kontrol=round($d_kontrol,2);
	$k_kontrol=round($k_kontrol,2);
	if ($d_kontrol!=$k_kontrol) {
		print "<BODY onLoad=\"javascript:alert('Der er konstateret en uoverensstemmelse i posteringssummen, kontakt administrator')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=rapport.php?id=$id\">";
		exit;
	} 
}

if (!function_exists('find_maaned_nr')) {
	function find_maaned_nr($maaned) {
		$maaned=trim($maaned);
		$aar=date("Y");
		if (is_numeric($maaned)) return($aar." ".$maaned); 
	
		if (strstr($maaned," ")) list($aar,$maaned)=explode(" ",$maaned);
		if ($maaned=="januar") $maaned="01";	
		elseif ($maaned=="februar") $maaned="02";	
		elseif ($maaned=="marts") $maaned="03";	
		elseif ($maaned=="april") $maaned="04";	
		elseif ($maaned=="maj") $maaned="05";	
		elseif ($maaned=="juni") $maaned="06";	
		elseif ($maaned=="juli") $maaned="07";	
		elseif ($maaned=="august") $maaned="08";	
		elseif ($maaned=="september") $maaned="09";	
		elseif ($maaned=="oktober") $maaned="10";	
		elseif ($maaned=="november") $maaned="11";	
		elseif ($maaned=="december") $maaned="12";	
		return ($aar." ".$maaned);
	}
}

?>