<?php
//                         ___   _   _   ___  _
//                        / __| / \ | | |   \| |
//                        \__ \/ _ \| |_| |) | |
//                        |___/_/ \_|___|___/|_|

// --------includes/rapportfunc.php ----- lap 3.6.4 ---- 2016.02.26 ---------------------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2016 saldi.dk aps
// ----------------------------------------------------------------------

// 2012.11.06 Kontrol for aktivt regnskabsaar v. bogføring af rykker.Søg 20121106  
// 2013.02.10 Div. fejl i forb. med udling af ørediff + break ændret til break 1
// 2013.05.05	Div tilretninger i forb. med omskrivning af udlign_openpost.php 
// 2014.05.03 Indsat valutakurs=100 ved DKK.(PHR Danosoft) Søg 20140503
// 2014.05.05 Fjerner udligning hvis udligningssum er skæv.(PHR Danosoft) Søg 20140505
// 2014.05.05 Indsat $valutakode*=1; (PHR Danosoft) Søg $valutakode*=1 & 20140505
// 2014.06.28	Indsat valutakurs v. oprettelse af openpost i funktion bogfor_nu Søg 20140628
// 2014.07.16 Ændret bredden af knapperne, så der var plads til "Betalingslister". ca
// 2015.10.19	Fjernet ";" fra tekst da den gav falsk SQL injektion fejl
// 2015.10.26	indsat mulighed for at ophæve udligning. søg "uudlign"
// 2015.11.04	Betalingslister v debitor
// 2016.02.26	Rettet så link til ret_valutadiff.php kun vises for posteringer i aktivt regnskabsår. Søg område ver ret_valutadiff.php 

function openpost($dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $kontoart) {
#cho "A $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $kontoart<br>";
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

#cho "KF1 $konto_fra<br>";

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
	global $bruger_id;
	global $menu;

#	$fromdate=usdate($dato_fra);
#	$todate=usdate($dato_til);

	if ($dato_fra && $dato_til) {
		$fromdate=usdate($dato_fra);
		$todate=usdate($dato_til);
	}	elseif ($dato_fra && !$dato_til) {
#		$fromdate=usdate($dato_fra);
		$todate=usdate($dato_fra);
	}

	
	$skjul_aabenpost=if_isset($_GET['skjul_aabenpost']);
	$skjul_aaben_rykker=if_isset($_GET['skjul_aaben_rykker']);
	$skjul_bogfort_rykker=if_isset($_GET['skjul_bogfort_rykker']);
	$skjul_afsluttet_rykker=if_isset($_GET['skjul_afsluttet_rykker']);
	$kun_debet=if_isset($_GET['kun_debet']);
	$kun_kredit=if_isset($_GET['kun_kredit']);
	
	($kontoart=='D')?$tekst='DRV':$tekst='KRV';

	if ($skjul_aabenpost) db_modify("update grupper set box7='$skjul_aabenpost',box11='',box12='' where art='$tekst' and kodenr='1'",__FILE__ . " linje " . __LINE__);
	if ($skjul_aaben_rykker) db_modify("update grupper set box8='$skjul_aaben_rykker' where art='$tekst' and kodenr='1'",__FILE__ . " linje " . __LINE__);
	if ($skjul_bogfort_rykker) db_modify("update grupper set box9='$skjul_bogfort_rykker' where art='$tekst' and kodenr='1'",__FILE__ . " linje " . __LINE__);
	if ($skjul_afsluttet_rykker) db_modify("update grupper set box10='$skjul_afsluttet_rykker' where art='$tekst' and kodenr='1'",__FILE__ . " linje " . __LINE__);
	if ($kun_debet) db_modify("update grupper set box11='$kun_debet',box12='' where art='$tekst' and kodenr='1'",__FILE__ . " linje " . __LINE__);
	if ($kun_kredit) db_modify("update grupper set box11='',box12='$kun_kredit' where art='$tekst' and kodenr='1'",__FILE__ . " linje " . __LINE__);
	
	db_modify("update ordrer set art = 'R1' where art = 'RB'",__FILE__ . " linje " . __LINE__); # 20091012 - er overfloedig
	
	if ($r=db_fetch_array(db_select("select * from grupper where art = '$tekst' and kodenr = '1' order by box2",__FILE__ . " linje " . __LINE__))){
		$skjul_aabenpost=$r['box7'];
		$skjul_aaben_rykker=$r['box8'];
		$skjul_bogfort_rykker=$r['box9'];
		$skjul_afsluttet_rykker=$r['box10'];
		$kun_debet=$r['box11'];
		$kun_kredit=$r['box12'];
	}
	if ($ny_rykker) {
#		#cho "1;URL=rapport.php?ny_rykker=1&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&rapportart=$rapportart"; 
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
#	$maaned_fra=trim($maaned_fra);
#	$maaned_til=trim($maaned_til);

	if ($r=db_fetch_array(db_select("select * from grupper where art = '$tekst' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__))){
		$dato_fra=$r['box2'];
		$dato_til=$r['box3'];
		$konto_fra=$r['box4'];
		$konto_til=$r['box5'];
		$rapportart=$r['box6'];
	} 

	print "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody><!--Tabel 1 start-->\n";
	if ($menu=='T') {
		$leftbutton="<a title=\"Klik her for at komme til startsiden\" href=\"../debitor/rapport.php\" accesskey=\"L\">LUK</a>";
		$rightbutton=NULL;
		$vejledning=NULL;
		include("../includes/topmenu.php");
		print "<div id=\"topmenu\" style=\"position:absolute;top:6px;right:0px\">";
	} elseif ($menu=='S') {
		include("../includes/sidemenu.php");
	} else {
		print "<tr><td width=100% height=\"8\">\n";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody><!--Tabel 1.2 start-->\n"; # tabel 1.2
#	print "<td width=\"10%\" $top_bund><a accesskey=l href=\"rapport.php?rapportart=openpost&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til\">Luk</a></td>";
		print "<td width=\"10%\" $top_bund><a accesskey=l href=\"rapport.php\">Luk</a></td>\n";
		print "<td width=\"80%\" $top_bund>Rapport - $rapportart</td>\n";
		print "<td width=\"10%\" $top_bund>\n";
	}
#	if ($menu=='T') print "<fieldset style=\"border:0px; border-top:1px solid black\"><legend>Test</legend></fieldset>";
		print "<select class=\"inputbox\" name=\"aabenpostmode\" onchange=\"window.open(this.options[this.selectedIndex].value,'_top')\">\n";
		if ($kun_debet=='on') print "<option>Kun konti i debet</option>\n";
		elseif ($kun_kredit=='on') print "<option>Kun konti i kredit</option>\n";
		elseif ($skjul_aabenpost=='on') print "<option>Skjul &aring;bne poster</option>\n";
		else print "<option>Vis &aring;bne poster</option>\n";
		if ($skjul_aabenpost=='on' || $kun_debet=='on' || $kun_kredit=='on') print "<option value=\"rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_aabenpost=off\">Vis &aring;bne poster</option>\n";
		if ($kun_debet!='on') print "<option value=\"rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_aabenpost=off&kun_debet=on\">Kun konti i debet</option>\n";
		if ($kun_kredit!='on') print "<option  value=\"rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_aabenpost=off&kun_kredit=on\">Kun konti i kredit</option>\n";
		if ($skjul_aabenpost!='on' && $kontoart=='D') print "<option  value=\"rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_aabenpost=on\">Skjul &aring;bne poster</option>\n";
		print "</select>\n";
		if ($menu) print "<td></tr>\n";
		else print "</div>\n";
#	} 
#	if ($skjul_aabenpost=='on') print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_aabenpost=off>Vis</a><td></tr>\n";
#	else print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_aabenpost=on>Skjul</a><td></tr>\n";	
	if ($menu!='T') print "</tbody></table></td></tr><!--Tabel 1.2 slut-->\n\n"; # <- Tabel 1.2
#cho "XX $dato_fra,$dato_til,$konto_fra,$konto_til,$kontoart<br>";
	if ($skjul_aabenpost!='on') vis_aabne_poster($dato_fra,$dato_til,$konto_fra,$konto_til,$rapportart,$kontoart,$kun_debet,$kun_kredit);

 	####################################### Rykkeroversigt ##############################################
 	
# if ($skjul_aaben_rykker!='on' || $skjul_bogfort_rykker!='on' || $skjul_afsluttet_rykker!='on') 
	if (is_numeric($konto_fra) && is_numeric($konto_til)) {
		$qtxt = "select * from ordrer where ".nr_cast('kontonr').">='$konto_fra' and ".nr_cast('kontonr')."<='$konto_til' and art LIKE 'R%' order by ".nr_cast('kontonr')."";
	} elseif ($konto_fra && $konto_fra!='*') {
		$konto_fra=str_replace("*","%",$konto_fra);
		$tmp1=strtolower($konto_fra);
		$tmp2=strtoupper($konto_fra);
		$qtxt = "select * from ordrer where (firmanavn like '$konto_fra' or lower(firmanavn) like '$tmp1' or upper(firmanavn) like '$tmp2') and art LIKE 'R%' order by firmanavn";
	}	else $qtxt = "select * from ordrer where art LIKE 'R%' order by firmanavn";
#cho "tekst $qtxt<br>";



	if ($kontoart=='D' && db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__))) {
#	if ($kontoart=='D' && db_fetch_array(db_select("select * from ordrer where art LIKE 'R%'",__FILE__ . " linje " . __LINE__))) {
#		print "<tr><td><br></td></tr>\n";
 		$x=0;
 		$taeller=0;
 		$sum=array();
 		while ($taeller <3) {  
			$sum=array();
			$taeller++;
			print "<tr><td><table width=100% cellpadding=\"0\" cellspacing=\"3\" border=\"0\"><tbody><!--Tabel 1.3 start-->\n"; # Tabel 1.3 ->
			if ($taeller==1) {
				print "<tr><td width=10% align=center $top_bund><br></td><td width=80% align=center $top_bund>&Aring;bne&nbsp;rykkere</td><td width=10% align=center $top_bund>\n";
				if ($skjul_aaben_rykker=='on') print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_aaben_rykker=off>Skjul</a><td></tr>\n";
				else print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_aaben_rykker=on>Vis</a><td></tr>\n";	
			} elseif ($taeller==2) {
				print "<tr><td width=10% align=center $top_bund><br></td><td width=80% align=center $top_bund>Bogf&oslash;rte&nbsp;rykkere</td><td width=10% align=center $top_bund>\n";
				if ($skjul_bogfort_rykker=='on') print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_bogfort_rykker=off>Skjul</a><td></tr>\n";
				else print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_bogfort_rykker=on>Vis</a><td></tr>\n";	
			} else  {
				print "<tr><td width=10% align=center $top_bund><br></td><td width=80% align=center $top_bund>Afsluttede&nbsp;rykkere</td><td width=10% align=center $top_bund>\n";
				if ($skjul_afsluttet_rykker=='on') print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_afsluttet_rykker=off>Skjul</a><td></tr>\n";
				else print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_afsluttet_rykker=on>Vis</a><td></tr>\n";	
			}
			print "</tbody></table></td></tr><!--Tabel 1.3 slut-->\n";
			if (($taeller==1 && $skjul_aaben_rykker=='on')||($taeller==2 && $skjul_bogfort_rykker=='on')||($taeller==3 && $skjul_afsluttet_rykker=='on')) {
			print "<tr><td width=100%>";
			print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><!--Tabel 1.4 start-->"; #B
			print "<tr><td>L&oslash;benr.</td><td>Firmanavn</td><td colspan=2 align=center>Dato</td><td align=center>Rykkernr</td><td colspan=3 align=right>Bel&oslash;b</td></tr>\n";	
			print "<tr><td colspan=9><hr></td></tr>\n";
			if ($taeller==1) {$formnavn='rykker1'; $status= "< 3";}
			else  {$formnavn='rykker2'; $status= ">= 3";}
			if ($taeller==3) $betalt="and betalt = 'on'";
			else $betalt="and betalt != 'on'";
			print "<form name=$formnavn action=rapport.php method=post>";

			if (is_numeric($konto_fra) && is_numeric($konto_til)) {
				$qtxt = "select * from ordrer where ".nr_cast('kontonr').">='$konto_fra' and ".nr_cast('kontonr')."<='$konto_til' and art LIKE 'R%' $betalt and status $status order by ".nr_cast('kontonr')."";
			} elseif ($konto_fra && $konto_fra!='*') {
				$konto_fra=str_replace("*","%",$konto_fra);
				$tmp1=strtolower($konto_fra);
				$tmp2=strtoupper($konto_fra);
				$qtxt = "select * from ordrer where (firmanavn like '$konto_fra' or lower(firmanavn) like '$tmp1' or upper(firmanavn) like '$tmp2') and art LIKE 'R%' $betalt and status $status order by firmanavn";
			}	else $qtxt = "select * from ordrer where art LIKE 'R%' $betalt and status $status order by firmanavn";
#			#cho "tekst $qtxt<br>";

			$q1 = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
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
					if (is_numeric($r2['enhed'])) {
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

function vis_aabne_poster($dato_fra,$dato_til,$konto_fra,$konto_til,$rapportart,$kontoart,$kun_debet,$kun_kredit) {
#cho "$dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $kontoart<br>";

	global $bgcolor;
	global $bgcolor5;
	global $bruger_id;
	
	print "<tr><td><table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>\n";
	print "<tr><td>Kontonr</td><td>Firmanavn</td><td align=right>>90</td><td align=right>60-90</td><td align=right>30-60</td><td align=right>8-30</td><td align=right>0-8</td><td align=right>I alt</td><tr>";

	$currentdate=date("Y-m-d");
	if ($dato_fra && $dato_til) {
		$fromdate=usdate($dato_fra);
		$todate=usdate($dato_til);
	}	elseif ($dato_fra && !$dato_til) {
#		$fromdate=usdate($dato_fra);
		$todate=usdate($dato_fra);
	}


	print "<form name=aabenpost action=rapport.php method=post>";
	print "<tr><td colspan=10><hr></td></tr>\n";
		
	$x=0;

# #cho "KF $konto_fra<br>";
	if (is_numeric($konto_fra) && is_numeric($konto_til)) {
		$qtxt = "select * from adresser where ".nr_cast('kontonr').">='$konto_fra' and ".nr_cast('kontonr')."<='$konto_til' and art = '$kontoart' order by ".nr_cast('kontonr')."";
	} elseif ($konto_fra && $konto_fra!='*') {
		$konto_fra=str_replace("*","%",$konto_fra);
		$tmp1=strtolower($konto_fra);
		$tmp2=strtoupper($konto_fra);
		$qtxt = "select * from adresser where (firmanavn like '$konto_fra' or lower(firmanavn) like '$tmp1' or upper(firmanavn) like '$tmp2') and art = '$kontoart' order by firmanavn";
	}	else $qtxt = "select * from adresser where art = '$kontoart' order by firmanavn";
	$kontonr=array();
	$x=0;
# #cho "$qtxt<br>";
	$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__);

#	if ($konto_fra && $konto_til) $tmp=nr_cast('kontonr').">='$konto_fra' and ".nr_cast('kontonr')."<='$konto_til' and ";
#	elseif ($konto_fra) $tmp=nr_cast('kontonr').">='$konto_fra' and ";
#	elseif ($konto_til) $tmp=nr_cast('kontonr')."<='$konto_til'and ";
#	else $tmp="";
#	$q = db_select("select * from adresser where $tmp art = '$kontoart' order by firmanavn",__FILE__ . " linje " . __LINE__);
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
	$kontrolsum=0;
	for ($x=1; $x<=$kontoantal; $x++) {
		$amount=0;
		$udlignet=1;
		$rykkerbelob=0;
		$forfalden=0;
		$forfalden_plus8=0;
		$forfalden_plus30=0;
		$forfalden_plus60=0;
		$forfalden_plus90=0;
		$kontrol=0;
		$y=0;
		$faktnr=array();
		$f=0;
		if ($kontoart=='D') $tmp="";
		else $tmp="desc";

		if ($todate) $qtxt="select * from openpost where transdate<='$todate' and konto_id='$konto_id[$x]' order by faktnr,amount $tmp";
		else $qtxt="select * from openpost where konto_id='$konto_id[$x]' order by faktnr,amount $tmp";
		$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__);

#		if ($regnaar) $q=db_select("select * from openpost where konto_id=$id[$x] and transdate <= '$regnslut' order by faktnr,amount $tmp",__FILE__ . " linje " . __LINE__);
#		else $q=db_select("select * from openpost where konto_id=$id[$x] and udlignet!='1' order by faktnr,amount $tmp",__FILE__ . " linje " . __LINE__);
$ks=0;
		while ($r=db_fetch_array($q)) {
      if ($r['valutakurs']*1 && $r['valuta']!='-') {
				$kontrol+=afrund($r['amount']*$r['valutakurs']/100,2); #2012.03.30 afrunding rettet til 2 (Ørediff hos saldi_390) 
			} else {
				$kontrol+=afrund($r['amount'],2);
			}
			$ks+=$kontrol;
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
				($valuta=='DKK')?$amount=afrund($r['amount'],2):$amount=afrund($r['amount'],3); #2012.04.03 se saldi_ 
#if ($konto_id[$x]==19) #cho "$valuta $valutakurs $amount | $kontrol<br>";
#				if ($amount > 0) $amount+=0.0001;
#				else $amount-=0.0001;
				if (!$forfaldsdag && $kontoart=='D' && $amount < 0) $forfaldsdag=$r['transdate'];
				elseif (!$forfaldsdag && $kontoart=='K' && $amount > 0) $forfaldsdag=$r['transdate'];
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
#if ($kontonr[$x]=='845644664') echo "$amount*=$valutakurs/100 -->";			
				$amount*=$valutakurs/100;
#if ($kontonr[$x]=='845644664') echo "$amount<br>";			
#				$amount=afrund($amount,2);
### nedenstående er indført grundet en fejl i 2.0.3 som skrev forkert forfaldsdato i openpost	og fjernet i 2.0.8.
#				$tmp=usdate(forfaldsdag($transdate, $betalingsbet[$x], $betalingsdage[$x]));
#				if ($tmp && !$forfaldsdag) db_modify("update openpost set forfaldsdate='$tmp' where id='$oid'",__FILE__ . " linje " . __LINE__);
#				$forfaldsdag=$tmp;
################
				$fakt_utid=strtotime($transdate);
				$forf_utid=strtotime($forfaldsdag);
				$dage=afrund(($forf_utid-$fakt_utid)/86400,0);
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
		if ($kun_debet && $y<=0) {$udlignet=1;$y=0;$kontrol=0;}  
		elseif ($kun_kredit && $y>=0) {$udlignet=1;$y=0;$kontrol=0;}  
		$kontrol=afrund($kontrol,2);
		($y>0) ? $y=afrund($y,2) : $y=afrund($y,2);
		if ($y>0.01||$udlignet=="0"||$kontrol)	{	
			if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		
			$forfaldsum=$forfaldsum+$forfalden;
			$forfaldsum_plus8=$forfaldsum_plus8+$forfalden_plus8;
			$forfaldsum_plus30=$forfaldsum_plus30+$forfalden_plus30;
			$forfaldsum_plus60=$forfaldsum_plus60+$forfalden_plus60;
			$forfaldsum_plus90=$forfaldsum_plus90+$forfalden_plus90;
			$sum=$sum+$y;
			$kontrolsum+=$kontrol;
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
			$forfalden_plus90=afrund($forfalden_plus90,2);
			$forfalden_plus60=afrund($forfalden_plus60,2);
			$forfalden_plus30=afrund($forfalden_plus30,2);
			$forfalden_plus8=afrund($forfalden_plus8,2);

			if (($kontoart=='D' && $forfalden_plus90 > 0) || ($kontoart=='K' && $forfalden_plus90 < 0)) $color="rgb(255, 0, 0)";
			else $color="rgb(0, 0, 0)";
			$tmp=dkdecimal($forfalden_plus90);
			print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			if (($kontoart=='D' && $forfalden_plus60 > 0) || ($kontoart=='K' && $forfalden_plus60 < 0)) $color="rgb(255, 0, 0)";
			else $color="rgb(0, 0, 0)";
			$tmp=dkdecimal($forfalden_plus60);
			print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			if (($kontoart=='D' && $forfalden_plus30 > 0) || ($kontoart=='K' && $forfalden_plus30 < 0)) $color="rgb(255, 0, 0)";
			else $color="rgb(0, 0, 0)";
			$tmp=dkdecimal($forfalden_plus30);
			print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			if (($kontoart=='D' && $forfalden_plus8 > 0) || ($kontoart=='K' && $forfalden_plus8 < 0)) $color="rgb(255, 0, 0)";
			else $color="rgb(0, 0, 0)";
			$tmp=dkdecimal($forfalden_plus8);
			print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			if (($kontoart=='D' && $forfalden > 0) || ($kontoart=='K' && $forfalden < 0)) $color="rgb(255, 0, 0)";
			else $color="rgb(0, 0, 0)";
			$tmp=dkdecimal($forfalden);
			print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			if (afrund($kontrol,2)!=afrund($y,2)) {
				ret_openpost($konto_id[$x]);
				$tmp=dkdecimal($kontrol);
			} else $tmp=dkdecimal($y);
			if (abs($y)<0.01 && abs($kontrol)<0.01) {
				print "<td align=right title=\"Klik her for at udligne &aring;bne poster\"><a href=\"rapport.php?submit=ok&rapportart=openpost&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&udlign=$konto_id[$x]\">$tmp</a></td>";
			}
			else {print "<td align=right>$tmp</td>";}
				if (($kontoudtog[$x]=='on')&&($kontoart=="D")) {print "<td align=center><input type=checkbox name=kontoudtog[$x] checked>";}
				elseif($kontoart=="D")  print "<td align=center><input type=checkbox name=kontoudtog[$x]>";
			print "</tr>\n";
		}
		print "<input type=hidden name=rykkerbelob[$x] value=$rykkerbelob>";
	}
	$forfaldsum_plus90=afrund($forfaldsum_plus90,2);
	$forfaldsum_plus60=afrund($forfaldsum_plus60,2);
	$forfaldsum_plus30=afrund($forfaldsum_plus30,2);
	$forfaldsum_plus8=afrund($forfaldsum_plus8,2);

	print "<tr><td colspan=10><hr></td></tr>\n";
	print "<tr><td><br></td><td>I alt</td>";

	if ($forfaldsum_plus90 != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfaldsum_plus90);
#if ($konto_id[$x]=='19') #cho "F90: $forfaldsum_plus90<br>";
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
#if ($konto_id[$x]=='19') #cho "K: $kontrolsum S: $sum<br>";
  ($sum<=$kontrolsum)?$tmp=dkdecimal($kontrolsum):$tmp=dkdecimal($sum);
# $tmp=dkdecimal($sum);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
				
	print "<input type=hidden name=rapportart value=\"openpost\">";
	print "<input type=hidden name=dato_fra value=$dato_fra>";
	print "<input type=hidden name=dato_til value=$dato_til>";
	print "<input type=hidden name=konto_fra value=$konto_fra>";
	print "<input type=hidden name=konto_til value=$konto_til>";
	print "<input type=hidden name=kontoantal value=$kontoantal>";

	if ($kontoart=='D') print "<tr><td colspan=10 align=center><span title=\"Klik her for at maile kontoudtog til de modtagere som er afm&aelig;rket herover\"><input type=submit value=\"Mail kontoudtog\" name=\"submit\"></span>&nbsp;
		<span title='Klik her for at oprette rykker til de som er afm&aelig;rkede herover'><input type=submit value=\"Opret rykker\" name=\"submit\"></span>&nbsp;
		<span onmouseover=\"return overlib('".findtekst(242,$sprog_id)."', WIDTH=800);\" onmouseout=\"return nd();\"><input type=submit value=\"Ryk alle\" name=\"submit\"></span></td></tr>\n";
	print "</form>\n";
	print "<tr><td colspan=10><hr></td></tr>\n";
	print "</tbody></table>";
} #endfunc vis_aabne_poster
####################################################################################### 
 function bogfor_rykker($id) {
	global $regnaar;
	global $fakturadate;
	global $dato_fra;
	global $dato_til;
	global $konto_fra;
	global $konto_til;

	
	// Bemaerk at der ikke traekkes moms ved bogfoering af rykkergebyr - heller ikke selvom gebyret tilhorer en momsbelagt varegruppe.
	// 20121106 ->
	$r = db_fetch_array(db_select("select fakturadate from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));  
	$rykkerdate=$r['fakturadate'];
	$q = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)){
		$year=trim($r['box2']);
		$aarstart=str_replace(" ","",$year.$r['box1']);
		$year=trim($r['box4']);
		$aarslut=str_replace(" ","",$year.$r['box3']);
	}
	list ($year, $month, $day) = explode ('-', $rykkerdate);
	$year=trim($year);
	$ym=$year.$month;
	if (($ym<$aarstart || $ym>$aarslut))	{
		print "<BODY onLoad=\"javascript:alert('Rykkerdato udenfor regnskabs&aring;r')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til\">";
		exit;
	}
	// <- 20121106
	
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
# #cho "select * from ordrer where id='$id'<br>";	
	$q = db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
#		list ($year, $month, $day) = explode ('-', $r[fakturadate]);
#		$year=substr($year,-2);
#		$ym=$year.$month;
		$kontoart=$r['art'];
		$konto_id=$r['konto_id'];
		$kontonr=str_replace(" ","",$r['kontonr']);
		$firmanavn=trim($r['firmanavn']);
		$modtagelse=$r['modtagelse'];
		$transdate=($r['fakturadate']);
		$fakturanr=$r['fakturanr'];
		$fakturadate=$r['fakturadate'];
		$ordrenr=$r['ordrenr'];
		$valutakurs=$r['valutakurs'];
		$valuta=$r['valuta'];
		$projekt=$r['projekt']*1;
		$refnr;
		if ($r['moms']) {$moms=$r['moms'];}
		else {$moms=afrund($r['sum']*$r['momssats']/100,2);}
		$sum=$r['sum']+$moms;
		$ordreantal=$x;
		if ($r= db_fetch_array(db_select("select afd from ansatte where navn = '$r[ref]'",__FILE__ . " linje " . __LINE__))) $afd=$r['afd'];
		$afd=$afd*1; #sikkerhed for at 'afd' har en vaerdi 
		 
		$bilag=0;
/*
		if ($no_faktbill==1) $bilag='0';
		else $bilag=trim($fakturanr);
		if (substr($kontoart,1,1)=='K') $beskrivelse ="Kreditnota - ".$fakturanr;
		else $beskrivelse ="Faktura - ".$fakturanr;
*/		
		if (!$valutakurs && $valuta != 'DKK') { #20140628
				if ($r2=db_fetch_array(db_select("select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe = ".nr_cast("grupper.kodenr")." and valuta.valdate <= '$fakturadate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
				$valutakurs=$r2['kurs'];
			} else {
				print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs i $valuta d. $fakturadate')\">";	
				return("Ups - ingen valutakurs i $valuta d. $fakturadate");
			}
		}

		if ($valutakurs && $valutakurs!=100) $sum=$sum*$valutakurs/100; # Omregning til DKK.
		$beskrivelse="Gebyr mm. fra tidligere rykker";	

		if ($sum) db_modify("insert into openpost (konto_id, konto_nr, faktnr, refnr, amount, beskrivelse, udlignet, transdate, kladde_id,valuta,valutakurs) values ('$konto_id', '$kontonr', '$fakturanr', '$id','$sum', '$beskrivelse', '0', '$transdate', '0','DKK','100')",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$r = db_fetch_array(db_select("select box2 from grupper where art = 'DG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
		$kontonr=$r['box2']; # Kontonr ændres fra at være leverandørkontonr til finanskontonr

		if ($sum>0) {$debet=$sum; $kredit='0';}
		else {$debet='0'; $kredit=$sum*-1;}
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
		if ($sum)	db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt', '$id')",__FILE__ . " linje " . __LINE__);
		$y=0;
		$bogf_konto = array();
		$q = db_select("select * from ordrelinjer where ordre_id='$id'",__FILE__ . " linje " . __LINE__); # 20151019
		while ($r = db_fetch_array($q)) {
			if (!in_array($r['bogf_konto'], $bogf_konto)) {
			$y++;
			$bogf_konto[$y]=$r['bogf_konto'];
				$pris[$y]=$r['pris']*$r['antal']-afrund(($r['pris']*$r['antal']*$r['rabat']/100),2);
			}
			else {
				for ($a=1; $a<=$y; $a++) {
					if ($bogf_konto[$a]==$r['bogf_konto']) {
						$pris[$a]=$pris[$a]+($r['pris']*$r['antal']-afrund(($r['pris']*$r['antal']*$r['rabat']/100),2));
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
	$d_kontrol=afrund($d_kontrol,2);
	$k_kontrol=afrund($k_kontrol,2);
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
#############################################################################################################
function forside($dato_fra,$dato_til,$konto_fra,$konto_til,$rapportart,$kontoart) {
	global $brugernavn;
	global $bruger_id;
	global $top_bund;
	global $md;
#	global $returside;
	global $jsvars;
	global $popup;
	global $menu;
	global $rettigheder;

	$husk="";
#
# #cho "0 DTF $dato_fra $dato_til<br>";
	print "<script LANGUAGE=\"JavaScript\" TYPE=\"text/javascript\" SRC=\"../javascript/overlib.js\"></script>";
/*
	$r=db_fetch_array(db_select("select regnskabsaar from brugere where brugernavn='$brugernavn'",__FILE__ . " linje " . __LINE__));
	$regnaar = $r['regnskabsaar'];
	$r=db_fetch_array(db_select("select * from grupper where art = 'RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__));
	$start_md=$r['box1']*1;
	$start_aar=$r['box2']*1;
	$slut_md=$r['box3']*1;
	$slut_aar=$r['box4']*1;
	if ($start_md<10) $start_md='0'.$start_md;
	if ($slut_md<10) $slut_md='0'.$slut_md;
*/
	($kontoart=='D')?$tekst='DRV':$tekst='KRV';
	if($r=db_fetch_array(db_select("select * from grupper where art = '$tekst' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__))){
		if ($r['box1']) {
			$husk='checked';
			$dato_fra=$r['box2'];
			$dato_til=$r['box3'];
			$konto_fra=$r['box4'];
			$konto_til=$r['box5'];
			$rapportart=$r['box6'];
		}
	} else db_modify("insert into grupper (beskrivelse,kodenr,art) values ('Debitorrapportvisning','$bruger_id','$tekst')",__FILE__ . " linje " . __LINE__);
#	db_modify("update grupper set box1='$regnaar',box2='$dato_fra',box3='$dato_til',box4='$konto_fra',box5='$konto_til',box6='$rapportart' where art='DRV' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__);

# #cho "1 DTF $dato_fra $dato_til<br>";
	($kontoart=='D')?$title=findtekst(449,$sprog_id):$title=findtekst(450,$sprog_id);
	($popup)?$returside="../includes/luk.php":$returside="../index/menu.php";

	print "<table cellpadding=\"1\" cellspacing=\"3\" border=\"0\" width=100% height=100% valign=\"top\"><tbody>";
	if ($menu=='T') {
		$leftbutton="<a title=\"Klik her for at komme til startsiden\" href=\"../index/menu.php\" accesskey=\"L\">LUK</a>";
		$rightbutton=NULL;
		include("../includes/topmenu.php");
	} elseif ($menu=='S') {
		include("../includes/sidemenu.php");
	} else {
		print "<tr><td height=\"8\" width=\"10%\" $top_bund><a href=$returside accesskey=L>Luk</a></td>";
		print "<td width=\"80%\" $top_bund>$title</td>";
		print "<td width=\"10%\" $top_bund><br></td>";
		print "</tr><tr><td height=99%><br></td></td>";
	}
	print "<td><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" align=\"center\"><tbody>\n";
	print "<tr><td align=center colspan=\"2\"><big><b>$title</b></big><br><br></td></tr>";
#	print "<tr><td colspan=\"2\"><hr></td></tr>";
		$dato=$dato_fra;
		if ($dato_til) $dato.=":$dato_til";
		$konto=$konto_fra;
		if ($konto_til) $konto.=":$konto_til";
	
		$tekst1=findtekst(437,$sprog_id);
		$tekst2=findtekst(438,$sprog_id);
		$tekst3=findtekst(439,$sprog_id);
		$tekst4=findtekst(440,$sprog_id);
		$tekst5=findtekst(451,$sprog_id);
		$tekst6=findtekst(452,$sprog_id);
		$overlib1="onmouseover=\"return overlib('".$tekst1."', WIDTH=800);\" onclick=\"return nd();\" onmouseout=\"return nd();\"";
		$overlib2="onmouseover=\"return overlib('".$tekst3."', WIDTH=800);\" onclick=\"return nd();\" onmouseout=\"return nd();\"";
		$overlib3="onmouseover=\"return overlib('".$tekst5."', WIDTH=800);\" onclick=\"return nd();\" onmouseout=\"return nd();\"";
		print "<tr><td align=\"center\" $overlib1>$tekst2</td><td align=\"center\" $overlib2>$tekst4</td><td align=\"center\" $overlib3>$tekst6</td></tr>";
	print "<form name=\"regnskabsaar\" action=\"rapport.php\" method=\"post\">";
	print "<tr><td align=\"center\" $overlib1><input class=\"inputbox\" style=\"width:129px\" type=\"text\" name=\"dato\" value=\"$dato\"></td>";
	print "<td align=\"center\" $overlib2><input class=\"inputbox\" style=\"width:129px\" type=\"text\" name=\"konto\" value=\"$konto\"></td>";
	print "<td align=\"center\" $overlib3><input class=\"inputbox\" type=\"checkbox\" name=\"husk\" $husk></td></tr>";
#	print "<input style=\"width:50px\" type=\"submit\" value=\"$tekst\" name=\"find\"></td>";
	$tekst1=findtekst(441,$sprog_id);
	$tekst2=findtekst(444,$sprog_id);
	print "<tr><td colspan=\"3\" align=center><input style=\"width:115px\" type=\"submit\" value=\"$tekst1\" name=\"openpost\" title=\"$tekst2\">&nbsp;";
	$tekst1=findtekst(442,$sprog_id);
	$tekst2=findtekst(445,$sprog_id);
	print "<input style=\"width:115px\" type=\"submit\" value=\"$tekst1\" name=\"kontosaldo\" title=\"$tekst2\">&nbsp;";
	$tekst1=findtekst(443,$sprog_id);
	$tekst2=findtekst(446,$sprog_id);
	print "<input style=\"width:115px\" type=\"submit\" value=\"$tekst1\" name=\"kontokort\" title=\"$tekst2\"></td></tr>";
	if ($kontoart=='D') print "<tr><td colspan=\"3\"><hr></td></tr>";
	if ($kontoart=='D') {
		$tekst1=findtekst(447,$sprog_id);
		$tekst2=findtekst(448,$sprog_id);
		$tekst3=findtekst(455,$sprog_id);
		print "<tr><td colspan=\"3\" align=center>";
		if ($popup) {
			print "<span onClick=\"javascript:top100=window.open('top100.php','top100','$jsvars');top100.focus();\" title=\"a $tekst1\"><input style=\"width:115px\" type=submit value=\"$tekst2\" name=\"submit\"></span>";
			#print "<span onClick=\"javascript:top100=window.open('top100.php','top100','$jsvars');top100.focus();\" title=\"$tekst1\"><input style=\"width:115px\" type=submit value=\"$tekst2\" name=\"submit\"></span>";
			if (db_fetch_array(db_select("select id from grupper where art = 'POS' and box2 >= '1'",__FILE__ . " linje " . __LINE__))) {
				print "<span onClick=\"javascript:kassespor=window.open('kassespor.php','kassespor','$jsvars');kassespor.focus();\" title=\"$tekst1\"><input style=\"width:115px\" type=submit value=\"$tekst3\" name=\"submit\"></span>";
			}
		} else {
			print "<span title=\"$tekst1\" onClick=\"window.location.href='top100.php'\"><input style=\"width:115px\" type=button value=\"$tekst2\" name=\"submit\"></span>";
			print "<input title=\"Salgsstat\" style=\"width:115px\" type=\"submit\" value=\"Salgsstat\" name=\"salgsstat\">";
			if (db_fetch_array(db_select("select id from grupper where art = 'POS' and box2 >= '1'",__FILE__ . " linje " . __LINE__))) {
				print	"<a href=\"kassespor.php\"><input title=\"Oversigt over POS transaktioner\" style=\"width:115px\" type=\"button\" value=\"$tekst3\"></a>";
			}
		}
		print	"</td></tr>";
		if (db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box10 >= 'on'",__FILE__ . " linje " . __LINE__))) {
			$tekst1=findtekst(531,$sprog_id);
			$tekst2=findtekst(532,$sprog_id);
			print "<tr><td colspan=\"3\" align=center>";
			print	"<span onClick=\"javascript:betalingsliste=window.open('betalingsliste.php','betalingsliste','$jsvars');betalingsliste.focus();\" title=\"$tekst1\"><input style=\"width:115px\" type=submit value=\"$tekst2\" name=\"betalingslister\"></span>";
			print	"</td></tr>";
		}
	} else {
		$tekst1=findtekst(531,$sprog_id);
		$tekst2=findtekst(532,$sprog_id);
		print "<tr><td colspan=\"3\" align=center>";
			if (db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box10 >= 'on'",__FILE__ . " linje " . __LINE__))) {
			print	"<span onClick=\"javascript:betalingsliste=window.open('betalingsliste.php','betalingsliste','$jsvars');betalingsliste.focus();\" title=\"$tekst1\"><input style=\"width:115px\" type=submit value=\"$tekst2\" name=\"betalingslister\"></span>";
		}
		print "<input title=\"Salgsstat\" style=\"width:115px\" type=\"submit\" value=\"Salgsstat\" name=\"salgsstat\">";
	}
	print	"</td></tr></form>";
	print "</tbody></table>";
	print "</tbody></table>";
}

#############################################################################################################
function kontokort($dato_fra,$dato_til,$konto_fra,$konto_til,$rapportart,$kontoart) {

#	global $connection;
	global $bruger_id;
	global $top_bund;
	global $md;
	global $popup;
#	global $returside;
#	global $rapportart;
	global $bgcolor;
	global $bgcolor5;
	global $regnaar;
	global $menu;

	$uudlign=if_isset($_GET['uudlign']);
	if ($uudlign) db_modify("update openpost set udlignet='0',udlign_id='0' where udlign_id='$uudlign'",__FILE__ . " linje " . __LINE__);
	$r=db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__));
	$regnstart="01-".trim($r['box1'])."-".trim($r['box2']);
	$tmp=31;
	while(!checkdate(trim($r['box3']),$tmp,trim($r['box4']))) {
		$tmp--;
		if ($tmp<28) break 1;
	}
	$regnslut=$tmp."-".trim($r['box3'])."-".trim($r['box4']);

	$difflink=0;
	$kontoart=trim($kontoart);
	$kilde=if_isset($_GET['kilde']);
	$kilde_kto_fra=if_isset($_GET['kilde_kto_fra']);
	$kilde_kto_til=if_isset($_GET['kilde_kto_til']);

	if ($kontoart=='K') $returnpath="../kreditor/";
	else $returnpath="../debitor/";

	$tmp=$konto_fra;
# #cho $_GET['returside'];
	($kontoart=='D')?$tekst='DRV':$tekst='KRV';
	if(isset($_GET['returside'])) $returside= $_GET['returside'];
	elseif ($r=db_fetch_array(db_select("select * from grupper where art = '$tekst' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__))){
		$dato_fra=$r['box2'];
		$dato_til=$r['box3'];
		$konto_fra=$r['box4'];
		$konto_til=$r['box5'];
		$rapportart=$r['box6'];
	} 
	if ($r=db_fetch_array(db_select("select id from grupper where art = 'PRJ'",__FILE__ . " linje " . __LINE__))) $prj='Projekt';
	else $prj='';

	if ($tmp && $tmp!=$konto_fra && !$returside) {
		$returside="rapport.php?rapportart=$rapportart"; #&submit=ok&regnaar=$regnaar&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til";
		$konto_fra=$tmp;
		$konto_til=$konto_fra;
	} elseif (!$returside) $returside="rapport.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til";

	if ($dato_fra && $dato_til) {
		$fromdate=usdate($dato_fra);
		$todate=usdate($dato_til);
	}	elseif ($dato_fra && !$dato_til) {
#		$fromdate=usdate($dato_fra);
		$todate=usdate($dato_fra);
	}

$luk= "<a accesskey=L href=\"$returside\">";

	print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	if ($menu=='T') {
		$leftbutton="<a title=\"Klik her for at komme til startsiden\" href=\"../debitor/rapport.php\" accesskey=\"L\">LUK</a>";
		$rightbutton=NULL;
		$vejledning=NULL;
		include("../includes/topmenu.php");
		print "<div id=\"topmenu\" style=\"position:absolute;top:6px;right:0px\">";
	} elseif ($menu=='S') {
		include("../includes/sidemenu.php");
	} else {
		print "<tr><td colspan=\"9\" height=\"8\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund>$luk Luk</a></td>";
		if ($kontoart=='K') $tekst="Kreditorrapport - kontokort";
		else $tekst="Debitorapport - kontokort";
		print "<td width=\"80%\" $top_bund>$tekst</td>";
#		print "<td width=\"10%\" $top_bund><a href=kontoprint.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&kontoart=$kontoart>Udskriv</a></td>";
		print "<td width=\"10%\" $top_bund onClick=\"javascript:kontoprint=window.open('kontoprint.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&kontoart=$kontoart','kontoprint' ,'left=10,top=10,width=800,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no');\"onMouseOver=\"this.style.cursor = 'pointer'\" title=\"'Udskriv kontoudtog som PDF'\">Udskriv</td>\n";

		print "</tbody></table>"; #B slut
		print "</td></tr>\n";
	}
	if (is_numeric($konto_fra) && is_numeric($konto_til)) {
		$tekst = "select id from adresser where ".nr_cast('kontonr').">='$konto_fra' and ".nr_cast('kontonr')."<='$konto_til' and art = '$kontoart' order by ".nr_cast('kontonr')."";
	} elseif ($konto_fra && $konto_fra!='*') {
		$konto_fra=str_replace("*","%",$konto_fra);
		$tmp1=strtolower($konto_fra);
		$tmp2=strtoupper($konto_fra);
		$tekst = "select id from adresser where (firmanavn like '$konto_fra' or lower(firmanavn) like '$tmp1' or upper(firmanavn) like '$tmp2') and art = '$kontoart' order by firmanavn";
	}	else $tekst = "select id from adresser where art = '$kontoart' order by firmanavn";
	$kontonr=array();
	$x=0;
	$query = db_select("$tekst",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$x++;
		$konto_id[$x]=$row['id'];
	}
	$kto_id=array();
	$kontoantal=$x;
	$x=0;
	# finder alle konti med bevaegelser i den anfoerte periode eller aabne poster fra foer perioden
	if ($kontoantal==1) { #20140505 - Fjerner udligning hvis udligningssum er skæv.
		$y=0;
		$qtxt="select distinct(udlign_id) from openpost where udlignet = '1' and udlign_id>'0' and konto_id='$konto_id[1]'";
		$q = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$udlign_id[$y]=$r['udlign_id']*1;
			$y++;
		}
/*
		for ($y=0;$y<count($udlign_id);$y++){
			$sum=0;
			$qtxt="select amount,valutakurs from openpost where udlign_id = '$udlign_id[$y]' and konto_id='$konto_id[1]'";
			$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				if ($r['valutakurs']) $sum+=afrund($r['amount']*$r['valutakurs']/100,2);
				else $sum+=afrund($r['amount'],2);
				}
			if (afrund($sum,2)*1) {
				db_modify("update openpost set udlignet='0', udlign_id='0' where udlign_id = '$udlign_id[$y]' and konto_id='$konto_id[1]'");
			}
		}
*/		
	}
	
	for ($y=1;$y<=$kontoantal;$y++) {
#		if ($fromdate && $todate) $query = db_select("select amount from openpost where transdate>='$fromdate' and transdate<='$todate' and konto_id='$konto_id[$y]'",__FILE__ . " linje " . __LINE__);
		if ($todate) $tekst="select amount from openpost where transdate<='$todate' and konto_id='$konto_id[$y]'";
		else $tekst="select amount from openpost where konto_id='$konto_id[$y]'";
#cho $tekst;
		$query = db_select("$tekst",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if (!in_array($konto_id[$y],$kto_id)) {
				$x++;
				$kto_id[$x]=$konto_id[$y];
			}
		}
	}
	$kontoantal=$x;
	
	for ($x=1; $x<=$kontoantal; $x++) {
		$q = db_select("select * from adresser where id=$kto_id[$x]",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array($q);
 		$art=trim($r['art'])."G";
		$betalingsbet=trim($r['betalingsbet']);
		$betalingsdage=$r['betalingsdage'];
		
		$r2 = db_fetch_array(db_select("select box3 from grupper where art='$art' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
		$valuta=trim($r2['box3']);
		if (!$valuta) $valuta='DKK';
		else {
			$r2 = db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta' and art='VK'",__FILE__ . " linje " . __LINE__));
			$valutakode=$r2['kodenr'];
		}
		$valutakode*=1;#20140505 
#		print "<tr><td colspan=8><hr></td></tr>\n";
		print "<tr><td colspan=9><hr></td></tr>\n";
		print "<tr><td><br></td></tr>\n";
		print "<tr><td><br></td></tr>\n";
		print "<tr><td colspan=3>".stripslashes($r['firmanavn'])."</td></tr>\n";
		print "<tr><td colspan=3>".stripslashes($r['addr1'])."</td></tr>\n";
		print "<tr><td colspan=3>".stripslashes($r['addr2'])."</td><td colspan=5 align=right>Kontonr</td><td align=right>$r[kontonr]</td></tr>\n";
		print "<tr><td colspan=3>".stripslashes($r['postnr'])."&nbsp;".stripslashes($r['bynavn'])."</td><td colspan=5 align=right>Dato</td><td align=right>".date('d-m-Y')."</td></tr>\n";
		print "<tr><td colspan=8 align=right>Valuta</td><td align=right>$valuta</td></tr>\n";
		print "<tr><td><br></td></tr>\n";
		print "<tr><td><br></td></tr>\n";
		print "<tr><td>Dato</td><td>Bilag</td><td>Faktura</td><td>Tekst</td><td>$prj</td><td>Forfaldsdato</td><td align=right>Debet</td><td align=right>Kredit</td><td align=right>Saldo</td></tr>\n";
		print "<tr><td colspan=9><hr></td></tr>\n";

		$kontosum=0;
		$primo=0;
		$oppid=array();
		$amount=array();
		$beskrivelse=array();
		$valutakurs=array();
		$oppvaluta=array();
		$faktnr=array();
		$forfaldsdag=array();
		$primoprint[$x]=0;
		$baggrund=$bgcolor;
		$dkksum=0;
		
		$y=0;
		$qtxt="select max(id) as max_valdif_id from openpost where konto_id='$kto_id[$x]' and abs(amount) = '0.001'";
		$r2 = db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
		$max_valdif_id=$r2['max_valdif_id'];
						
		if ($todate) $qtxt="select * from openpost where konto_id='$kto_id[$x]' and transdate<='$todate' order by transdate, faktnr, refnr";
		else $qtxt= "select * from openpost where konto_id='$kto_id[$x]' order by transdate, faktnr, refnr";
		$q2 = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$y++;
			($baggrund==$bgcolor)?$baggrund=$bgcolor5:$baggrund=$bgcolor;
			$oppid[$y]=$r2['id'];
			$amount[$y]=afrund($r2['amount'],2);
			$amount[$y]=$r2['amount'];
#cho "ID $r2[id] Amount: $amount[$y]=".$r2['amount']."|".$r2['valutakurs']."<br>";
			$beskrivelse[$y]=$r2['beskrivelse'];
#cho "$r2[valutakurs]<br>";
			$valutakurs[$y]=$r2['valutakurs']*1;
			$oppvaluta[$y]=$r2['valuta'];
			$faktnr[$y]=$r2['faktnr'];
			if (!$oppvaluta[$y]) {
				$oppvaluta[$y]='DKK';
				$valutakurs[$y]=100; #20140503
			}
			#cho "$dkkamount[$y]=afrund($amount[$y]*$valutakurs[$y]/100,2)<br>";
#cho "$amount[$y] $oppvaluta[$y] DKK $dkkamount[$y]<br>";
			$forfaldsdag[$y]=$r2['forfaldsdate'];
			($r2['projekt'])?$projekt[$y]=$r2['projekt']:$projekt[$y]='';
			($r2['kladde_id'])?$refnr[$y]=$r2['refnr']:$refnr[$y]='';
			if (!strlen($valutakurs[$y])) $valutakurs[$y]=100;
			$transdate[$y]=$r2['transdate'];
			$udlignet[$y]=$r2['udlignet'];
			$udlign_id[$y]=$r2['udlign_id'];

			if ($oppvaluta[$y]!='DKK' && $valutakurs[$y]==100) {
				$r3=db_fetch_array(db_select("select kodenr from grupper where box1 = '$oppvaluta[$y]' and art='VK'",__FILE__ . " linje " . __LINE__));
				$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__));
					$valutakurs[$y]=$r3['kurs']*1;
					$dkkamount[$y]=$amount[$y]*$valutakurs[$y]/100;
					$beskrivelse[$y] = $r2['beskrivelse']." - (Omregnet fra DKK til $valuta".dkdecimal($dkkamount[$y]).", kurs ".dkdecimal($valutakurs[$y]).")";
#					db_modify("update openpost set valutakurs='$valutakurs[$y]' where id='$oppid[$y]'");
			} elseif ($valuta!="DKK" && $valutakurs[$y]==100) {
				if ($r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__))) {
					$dkkamount[$y]=$amount[$y];
					$amount[$y]=$amount[$y]*100/$r3['kurs'];
					$beskrivelse[$y] = $r2['beskrivelse']." - (Omregnet til $valuta fra DKK ".dkdecimal($dkkamount[$y]).", kurs ".dkdecimal($r3['kurs']).")";
				} elseif ($r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' order by valdate",__FILE__ . " linje " . __LINE__))) {
					$amount[$y]=$amount[$y]*100/$r3['kurs'];
					$beskrivelse[$y] = $r2['beskrivelse']." - (Omregnet til $valuta fra DKK ".dkdecimal($dkkamount[$y]).", kurs ".dkdecimal($r3['kurs']).")";
				}
			} elseif (($oppvaluta[$y]!='DKK' && $valuta=="DKK" && $valutakurs[$y]!=100)) {
					$beskrivelse[$y] = $r2['beskrivelse']." - (Omregnet til DKK fra ".$oppvaluta[$y]." ".dkdecimal($amount[$y]).", kurs ".dkdecimal($valutakurs[$y]).")";
					$amount[$y]=$amount[$y]*$valutakurs[$y]/100;
#cho __line__." $amount[$y]=$amount[$y]*$valutakurs[$y]/100<br>";
			} elseif ($valuta!="DKK" && $valuta==$oppvaluta[$y] && $valutakurs[$y]!=100) {
			#cho "Opp $oppvaluta[$y] $valuta<br>";
				$valutakurs[$y]*=1;
				if (!$valutakurs[$y] && $oppvaluta[$y] && $oppvaluta[$y]!='-') {
				#cho "select kodenr from grupper where box1 = '$oppvaluta[$y]' and art='VK'<br>";
					$r3=db_fetch_array(db_select("select kodenr from grupper where box1 = '$oppvaluta[$y]' and art='VK'",__FILE__ . " linje " . __LINE__));
#cho "select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc<br>";
					$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__));
					$valutakurs[$y]=$r3['kurs']*1;
#			if ($valutakurs) #cho "update openpost set valutakurs='$valutakurs[$y]' where id='$oppid[$y]'<br>";
#					if ($valutakurs) db_modify("update openpost set valutakurs='$valutakurs[$y]' where id='$oppid[$y]'");
				} 
				$dkkamount[$y]=$amount[$y]*$valutakurs[$y]/100;
#cho "DKKA $dkkamount[$y]=A $amount[$y]*VK $valutakurs[$y]/100<br>";
#cho $r2['beskrivelse']."| $amount[$y]<br>";
					if ($oppvaluta[$y]!='-' && abs($amount[$y])>=0.005) {
						 if (!strpos($beskrivelse[$y],'Udligning af valutadiff')) $beskrivelse[$y] = $r2['beskrivelse']." - (DKK ".dkdecimal($dkkamount[$y]).")";
					} elseif (abs($amount[$y])<0.005) $beskrivelse[$y] = $r2['beskrivelse'];
					else $beskrivelse[$y] = $r2['beskrivelse']." - (DKK ".dkdecimal($amount[$y]).")";
					} elseif($oppvaluta[$y]!=$valuta && $oppvaluta[$y] != '-') {
				if (!$valutakurs[$y]) {
#cho "select kodenr from grupper where box1 = '$oppvaluta[$y]' and art='VK'<br>";
					$r3=db_fetch_array(db_select("select kodenr from grupper where box1 = '$oppvaluta[$y]' and art='VK'",__FILE__ . " linje " . __LINE__));
#cho "select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc<br>";
					$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__));
					$valutakurs[$y]=$r3['kurs']*1;
				}
				$r3=db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta' and art='VK'",__FILE__ . " linje " . __LINE__));
#cho "select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc<br>";
				$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__));
				$dagskurs=$r3['kurs']*1;
#cho "$dagskurs<br>";			
				$beskrivelse[$y].=" $oppvaluta[$y] ".dkdecimal($amount[$y])." Kurs $valutakurs[$y]";
				$amount[$y]*=$valutakurs[$y]/$dagskurs;
				$dkkamount[$y]=$amount[$y]*$valutakurs[$y]/100;
#cho "$dkkamount[$y]=$amount[$y]*$valutakurs[$y]/100<br>";
			} else $beskrivelse[$y] = $r2['beskrivelse'];
			if ($oppvaluta[$y]=="-") {
				$dkkamount[$y]=$amount[$y];
				$amount[$y]=0;
				$forfaldsdate[$y]='';
			}
		}
		$kontosum=0;
		$primo=0;
		$pre_openpost=0;
		for ($y=1;$y<=count($oppid);$y++) {
			$diff=0;
#cho "$amount[$y] <=> $dkkamount[$y]<br>";
			if ($transdate[$y]<$fromdate) {
				 $primoprint[$x]=0;
				 $kontosum+=$amount[$y];
				$dkksum+=$dkkamount[$y];
				} else {
				if ($primoprint[$x]==0) {
					$tmp=dkdecimal($kontosum);
					$tmp2="";
					if ($valuta!='DKK') $tmp2="&nbsp;&nbsp;&nbsp;-&nbsp;&nbsp;&nbsp;Bel&oslash;b kan v&aelig;re omregnet fra DKK";
					print "<tr><td><br></td><td><br></td><td><br></td><td>Primosaldo $tmp2<br></td><td><br></td><td><br></td><td><br></td><td><br></td><td align=right title=\"DKK ".dkdecimal($dkksum,2)."\">$tmp<br></td></tr>\n";
					$primoprint[$x]=1;
				}
				print "<tr bgcolor=\"$baggrund\"><td valign=\"top\">".dkdato($transdate[$y])."<br></td><td valign=\"top\">$refnr[$y]<br></td><td valign=\"top\">$faktnr[$y]<br></td><td valign=\"top\">".stripslashes($beskrivelse[$y])."<br></td><td valign=\"top\">$projekt[$y]</td>";
				if ($amount[$y] < 0) $tmp=0-$amount[$y];
				else $tmp=$amount[$y];
				$tmp=dkdecimal($tmp);
				if (!$forfaldsdag[$y]) $forfaldsdag[$y]=usdate(forfaldsdag($transdate[$y], $betalingsbet, $betalingsdage));
#				if (($row[udlignet]!='1')&&($forfaldsdag<$currentdate)){$stil="<span style='color: rgb(255, 0, 0);'>";}
#				else {$stil="<span style='color: rgb(0, 0, 0);'>";}
					
				if ($amount[$y]>0) {# (($kontoart=='D' && $amount>0) || ($kontoart=='K' && $amount<0)) {
				($kontoart=='D')?$ffdag=dkdato($forfaldsdag[$y]):$ffdag=NULL;
				if ($udlignet[$y]!='1') {
						$pre_openpost=1;
						print "<td valign=\"top\"><span style='color: rgb(255, 0, 0);'>$ffdag<br></td><td  valign=\"top\" align=\"right\" title=\"Klik her for at udligne &aring;bne poster\"><span style='color: rgb(255, 0, 0);'><a href=\"../includes/udlign_openpost.php?post_id=$oppid[$y]&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=".$returnpath."rapport.php\">$tmp</a><br></td><td style=\"color:$baggrund;text-align:right\">0</td>";
					} else {
						$titletag="Udlign id=$udlign_id[$y]. Klik for at ophæve udligning"; 
						$alink="rapport.php?rapportart=kontokort&kilde=openpost&kto_fra=$kto_fra&kilde_kto_til=$kto_til=&dato_fra$dato_fra=&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&submit=ok&uudlign=$udlign_id[$y]";
						$onclick="return confirm('Vil du ophæve udligningen af dette beløb samt modstående med udlign id $udlign_id[$y]')";
						print "<td valign=\"top\"><span style='color: rgb(0, 0, 0);'>$ffdag<br></td><td title=\"$titletag\" valign=\"top\" align=\"right\"><span style=\"color: rgb(0, 0, 0);\"><a onclick=\"$onclick\" href=\"$alink\"style=\"text-decoration:none;\" >$tmp<br></a></span></td><td style=\"color:$baggrund;text-align:right\">0</td>";
					}
					$forfaldsum=$forfaldsum+$amount[$y];
				} else {
					($kontoart=='K')?$ffdag=dkdato($forfaldsdag[$y]):$ffdag=NULL;
					if ($udlignet[$y]!='1') {
						print "<td><span style='color: rgb(255, 0, 0);'>$ffdag<br></td><td style=\"color:$baggrund;text-align:right\">0</td><td valign=\"top\" align=right title=\"Klik her for at udligne &aring;bne poster\"><span style='color: rgb(255, 0, 0);'><a href=\"../includes/udlign_openpost.php?post_id=$oppid[$y]&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=".$returnpath."rapport.php\">$tmp</a><br></td>";
						$pre_openpost=1;
					} else {
						$titletag="Udlign id=$udlign_id[$y]. Klik for at ophæve udligning"; 
						$alink="rapport.php?rapportart=kontokort&kilde=openpost&kto_fra=$kto_fra&kilde_kto_til=$kto_til=&dato_fra$dato_fra=&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&submit=ok&uudlign=$udlign_id[$y]";
						$onclick="return confirm('Vil du ophæve udligningen af dette beløb samt modstående med udlign id $udlign_id[$y]')";
						print "<td>$ffdag<br></td><td style=\"color:$baggrund;text-align:right\">0</td><td title=\"$titletag\" valign=\"top\" align=\"right\"><span style=\"color: rgb(0, 0, 0);\"><a onclick=\"$onclick\" href=\"$alink\"style=\"text-decoration:none;\" >$tmp<br></a></span></td>";
					}
				}
				$kontosum+=afrund($amount[$y],2);
				$dkksum+=$dkkamount[$y];
				$dkksum=afrund($dkksum,2);
#cho "$dkksum | $dkkamount[$y]<br>";
				$tmp=dkdecimal($kontosum);
				$dkktmp=dkdecimal($dkksum);
				if ($valuta!='DKK' && $kontosum!=$dkksum) $title="DKK: $dkktmp";
				else $title="";
#cho "$transdate[$y] | $dkkamount[$y] | $dkksum<br>";

				if ($valuta!='DKK' && !$difflink) {
#cho "select kurs from valuta where gruppe ='$valutakode' and valdate <= '$transdate[$y]' order by valdate desc<br>";
					if ($r=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__))) {
						$dagskurs=$r['kurs'];
						$chkamount=$kontosum*$dagskurs/100;
#cho "$kontosum*$dagskurs/100 Kontrolsum $chkamount<br>";
						$diff=afrund($chkamount-$dkksum,2);
#cho "Saldodiff: ".$chkamount."!=".$dkksum." rest: ".$diff."<br>";
#						if ($chkamount!=$dkksum) #cho "Saldodiff: ".$chkamount."!=".$dkksum." rest". $chkamount!=$dkksum."<br>"; 
#							#cho "tt $transdate[$y]<br>";
					}
				}


#				$diff=afrund($kontosum*$valutakurs/100-$dkksum,3);
#if ($diff && !$difflink) #cho "Diff m. 3 dec. $diff<br>"; 
#				$diff=afrund($diff,2);
#if (afrund($diff,2) && !$difflink) #cho "Diff m. 2 dec. ".afrund($diff,2)."<br>"; 
/*
if ($diff && !$difflink && $oppvaluta!='DKK') {
#cho "$diff && !$difflink<br>";
	$ny_kurs=$valutakurs;
	$ny_diff=afrund($kontosum*$ny_kurs/100-$dkksum,2);
	$gl_diff=$ny_diff;
#cho "A Ny diff $ny_diff<br>";
	while ($ny_diff && abs($ny_diff)<=abs($gl_diff)) {
		#cho "Ny diff $ny_diff Kurs $ny_kurs<br>";
		if (abs($ny_diff)>10) $ny_kurs+=0.1;
		else $ny_kurs+=0.001;
		$ny_diff=afrund($kontosum*$ny_kurs/100-$dkksum,2);
	}
#	$ny_kurs=$valutakurs;
#	$ny_diff=afrund($kontosum*$ny_kurs/100-$dkksum,2);
#cho "B Ny diff $ny_diff<br>";
	$gl_diff=$ny_diff;
	while ($ny_diff && abs($ny_diff)<=abs($gl_diff)) {
		#cho "Ny diff $ny_diff Kurs $ny_kurs<br>";
		if (abs($ny_diff)>10) $ny_kurs-=0.1;
		else $ny_kurs-=0.001;
		$ny_diff=afrund($kontosum*$ny_kurs/100-$dkksum,2);
	}
	if (abs($ny_diff)>0.01) {
		#cho "Kurs reguleres ikke<br>";  
		$ny_kurs=$valutakurs;
	} else #cho "Ny Diff $ny_diff Kurs reguleres fra $valutakurs til $ny_kurs<br>";
# #cho "Afrundet diff på summen".afrund($kontosum*$ny_kurs/100-$dkksum,3)." Kurs $valutakurs<br>";
#cho "Beløb på denne post DKK ".$dkkamount." Eur ".$amount."<br>";
#cho "Diff $diff | $valutakurs | $ny_kurs<br>";
#cho 
}
*/
#cho "($diff>0.005 && !$difflink && $valuta!='DKK' && $oppvaluta[$y]!='-')<br>";
				$regulering=afrund($diff,2);
#				else $regulering=afrund($dkksum+$diff,2)-afrund($dkksum,2);
				if($regulering && !$difflink && $valuta!='DKK' && ($oppvaluta[$y]!='-' || $y==count($oppid)) && $transdate[$y]>=usdate($regnstart) && $transdate[$y]<=usdate($regnslut)) { # && $transdate>=$regnstart && $transdate<=$regnslut
					$vis_difflink=1;
					for ($i=1;$i<=count($oppid);$i++){
						if ($transdate[$i]==$transdate[$y] && $oppvaluta[$i]=='-') $vis_difflink=0;
					}
					if ($y==count($oppid) && !$kontosum) $vis_difflink=1;
					#						$vis_difflink=0; #
						if ($oppid[$y]>=$max_valdif_id && ($vis_difflink && (abs($regulering)>0.01 || $y==count($oppid)))) {
						$difflink=1;
						if ($regnstart<=date("Y-m-d") && $regnslut>=date("Y-m-d")) {
							$title.="Klik for at regulere værdien i DKK fra ".dkdecimal($dkksum)." til ".dkdecimal($dkksum+$regulering)." pr. ".dkdato($transdate[$y]);
							$tmp2="<a href=\"../includes/ret_valutadiff.php?";
							$tmp2.="valuta=$valuta&diff=$regulering&post_id=$oppid[$y]&dato_fra=$dato_fra&dato_til=$dato_til&";
							$tmp2.="konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=".$returnpath."rapport.php\" ";
							$tmp2.="onclick=\"confirmSubmit($confirm)\">$tmp</a>";
							$tmp=$tmp2;
						} else $title=NULL;
					}
				}
				print "<td valign=\"top\" align=right title=\"$title\">$tmp<br></td>";
				print "</tr>\n";
			}
		}
		if ($primoprint[$x]==0) {
			$tmp=dkdecimal($kontosum);
			print "<tr><td><br></td><td><br></td><td><br></td><td>Primosaldo<br></td><td><br></td><td><br></td><td><br></td><td><br></td><td align=right title=\"DKK sum $dkktmp\">$tmp<br></td></tr>\n";
		}
	}
	print "<tr><td colspan=9><hr></td></tr>\n";
	print "</tbody></table>";
}
function kontosaldo($dato_fra,$dato_til,$konto_fra,$konto_til,$rapportart,$kontoart) {
#	global $connection;
	global $top_bund;
	global $md;
	global $returside;
	global $popup;
	global $bgcolor;
	global $bgcolor5;
	global $menu;

	$kilde=if_isset($_GET['kilde']);
	$kilde_kto_fra=if_isset($_GET['kilde_kto_fra']);
	$kilde_kto_til=if_isset($_GET['kilde_kto_til']);
	if ($popup) $returside="../includes/luk.php";
	elseif ($kilde=='openpost') $returside="rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$kilde_kto_fra&konto_til=$kilde_kto_til";
	else $returside="rapport.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til";
	$returside="rapport.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til";
	$luk= "<a accesskey=L href=\"$returside\">";
	$currentdate=date("Y-m-d");

	if ($dato_fra && $dato_til) {
		$fromdate=usdate($dato_fra);
		$todate=usdate($dato_til);
	}	elseif ($dato_fra && !$dato_til) {
#		$fromdate=usdate($dato_fra);
		$todate=usdate($dato_fra);
	}

	print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	if ($menu=='T') {
		if ($kontoart=='K') $returnpath="../kreditor/";
		else $returnpath="../debitor/";
		$leftbutton="<a title=\"Klik her for at komme til startsiden\" href=\"$returnpath/rapport.php\" accesskey=\"L\">LUK</a>";
		$rightbutton=NULL;
		$vejledning=NULL;
		include("../includes/topmenu.php");
		print "<div id=\"topmenu\" style=\"position:absolute;top:6px;right:0px\">";
	} elseif ($menu=='S') {
		include("../includes/sidemenu.php");
	} else {
		print "<tr><td colspan=\"8\" height=\"8\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund>$luk Luk</a></td>";
		if ($kontoart=='K') $tekst="Kreditorrapport - kontosaldo";
		else $tekst="Debitorapport - kontosaldo";
		print "<td width=\"80%\" $top_bund>$tekst</td>";
		print "<td width=\"10%\" $top_bund><br></td>";
		print "</tbody></table>"; #B slut
		print "</td></tr>\n";
	}
	if (is_numeric($konto_fra) && is_numeric($konto_fra)) {
		$qtxt = "select id from adresser where ".nr_cast('kontonr').">='$konto_fra' and ".nr_cast('kontonr')."<='$konto_til' and art = '$kontoart' order by ".nr_cast('kontonr')."";
	} elseif ($konto_fra && $konto_fra!='*') {
		$konto_fra=str_replace("*","%",$konto_fra);
		$tmp1=strtolower($konto_fra);
		$tmp2=strtoupper($konto_fra);
		$qtxt = "select id from adresser where (firmanavn like '$konto_fra' or lower(firmanavn) like '$tmp1' or upper(firmanavn) like '$tmp2') and art = '$kontoart' order by firmanavn";
	}	else $qtxt = "select id from adresser where art = '$kontoart' order by firmanavn";
# #cho "qtxt $qtxt<br>";
	$kontonr=array();
	$x=0;
	$query = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$x++;
		$konto_id[$x]=$row[id];
	}
	$kto_id=array();
	$kontoantal=$x;
	$x=0;
	# finder alle konti med bevaegelser i den anfoerte periode eller aabne poster fra foer perioden
	for ($y=1;$y<=$kontoantal;$y++) {
#		if ($fromdate && $todate) $qtxt="select amount from openpost where transdate>='$fromdate' and transdate<='$todate' and konto_id='$konto_id[$y]'";
		if ($todate) $qtxt="select amount from openpost where transdate<='$todate' and konto_id='$konto_id[$y]'";
		else $qtxt="select amount from openpost where konto_id='$konto_id[$y]'";
# #cho "Z $qtxt<br>";
		$query = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if (!in_array($konto_id[$y],$kto_id)) {
				$x++;
				$kto_id[$x]=$konto_id[$y];
			}
		}
	}
	$kontoantal=$x;

	for ($x=1; $x<=$kontoantal; $x++) {
		$r = db_fetch_array(db_select("select	* from adresser where id=$kto_id[$x]",__FILE__ . " linje " . __LINE__));
		$kontonr[$x]=stripslashes($r['kontonr']);
		$firmanavn[$x]=stripslashes($r['firmanavn']);
		$kontosum[$x]=0;
		$primo[$x]=0;
		$primoprint[$x]=0;
		$bgcolor='';


		if ($todate) $qtxt="select * from openpost where konto_id='$kto_id[$x]' and transdate<='$todate' order by transdate, faktnr, refnr";
		else $qtxt= "select * from openpost where konto_id='$kto_id[$x]' order by transdate, faktnr, refnr";
# #cho "$qtxt<br>";
		$q2 = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
# -> 2009.05.05
			$amount=afrund($r2['amount'],2);
			$oppvaluta=$r2['valuta'];
			if (!$oppvaluta) $oppvaluta='DKK';
			$oppkurs=$r2['valutakurs']*1;
			if (!$oppkurs) $oppkurs=100;
			$dkkamount=$amount;
			if ($oppvaluta=='DKK') $belob=dkdecimal($amount);
			else $belob = dkdecimal($amount*100/$oppkurs);
			$forfaldsdag=$r2['forfaldsdate'];
			$transdate=$r2['transdate'];
			if ($oppvaluta!='DKK' && $oppkurs!=100) { #postering foert i anden valuta end Debitors som er DKK
 					$amount=$amount*$oppkurs/100;
			}
			$kontosum[$x]=$kontosum[$x]+$amount;
	}
			$totalsum=$totalsum+$kontosum[$x];
if (afrund($kontosum[$x],2)) {
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\"><td width=\"200px\">$kontonr[$x]</td><td>$firmanavn[$x]</td>";
			$tmp=dkdecimal($kontosum[$x]);
			print "<td align=right> $tmp</td></tr>\n";
}
	}
	$tmp=dkdecimal($totalsum);
	print "<tr><td colspan=\"3\"><hr></td></tr>\n";
	print "<tr><td><b>ialt</b></td><td  colspan=\"3\" align=\"right\"><b>$tmp</b><td></tr>\n";
	print "</tbody></table>";

}
function ret_openpost($konto_id){
	$x=0;
	$q=db_select("select distinct(udlign_id) from openpost where konto_id='$konto_id' and udlignet='1'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$x++;
		$udlign_id[$x]=$r['udlign_id'];
	}
	$antal=$x;
	for($x=1;$x<=$antal;$x++) {
		$min_udlign_date="2999-12-31";
		$max_udlign_date="1970-01-01";
		$max_transdate="1970-01-01";

		if ($udlign_id[$x]) {
			$q=db_select("select transdate, udlign_date from openpost where udlign_id='$udlign_id[$x]'",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				$transdate=$r['transdate'];
				$udlign_date=$r['udlign_date'];
				if ($transdate>$max_transdate) $max_transdate=$transdate;
				if ($udlign_date>$max_udlign_date) $max_udlign_date=$udlign_date;
				if ($udlign_date<$min_udlign_date) $min_udlign_date=$udlign_date;
			}
			if ($max_transdate > $max_udlign_date || $max_udlign_date > $min_udlign_date)	{
				db_modify("update openpost set udlign_date = '$max_transdate' where udlign_id='$udlign_id[$x]'",__FILE__ . " linje " . __LINE__);
			}
		}
	}
}
?>
