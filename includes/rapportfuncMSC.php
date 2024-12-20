<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/rapportfunc.php --- lap 4.0.2 --- 2021.04.22 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003 - 2021 Saldi.dk ApS
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
// 2016.04.13	Tilføjet link til at rette dkksum til 0 pr dd hvis dd er i aktivt regnskabsår og valutasum er 0.
// 2016.04.14 Sorterer nu på ID for reg og faktnr, der giver mere mening //20160414
// 2016.05.03 Ved visning af kontokort fra flere konti blev dkkamount forkert //20160503
// 2017.03.03 Tilføjet inkasso.
// 2017.03.16 Tilføjet 'flueben' ved PBS kunder. Søg $pbs.
// 2017.04.03 Debitorrapportvisning oprettes i grupper hvis den ikke findes. 20170403
// 2018.02.07 PHR Tilføjet mulig for udligning af alle med saldo 0,00. Søg udlign.
// 2018.11.26 PHR Definition af div. variabler.
// 2018.12.14 PHR Rettet fejl i kald til kreditor/betalingsliste
// 2019.01.08 MSC - Rettet isset fejl
// 2019.01.18 PHR - function kontosaldo. Ændret tablewidth fra 1010% til 100%
// 2019.11.07	PHR - function 'kontoprint' Added email option. Search 20191107
// 2020.02.03 PHR - function 'vis_aabne_poster. "where udlignet = '0'" must not be used if todate is prior to actual date ; #20200103
// 2020.01.09 PHR - function 'bogfor_nu' Returns if allready accounted - 20200109
// 2021.04.22 PHR - Sum now rounded - look in 'debitor/ny_rykker.php' 20210422 
// 2021.04.27 PHR - Corrected error in currency (period sum) 
// 20210701 - LOE - Translated some of these texts from Danish to English and Norsk
// 20210816 PHR Changed query to not use cast - 20210816
// 20210824 MSC - Implementing new design
// 20210831 MSC - Implementing new design
// 20210901 MSC - Implementing new design
// 20210902 MSC - Implementing new design
// 20210915 MSC - Implementing new design
// 20210928 MSC - Implementing new design
// 20210930 MSC - Implementing new design
// 20211012 MSC - Implementing new design
// 20211014 MSC - Implementing new design
// 20211020 MSC - Implementing new design
// 20211021 MSC - Implementing new design
// 20211028 MSC - Implementing new design
// 20211101 MSC - Implementing new design
// 20220901 MSC - Implementing new design
// 20220905 MSC - Implementing new design
// 20230111 MSC - Implementing new design

function openpost($dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $kontoart) {
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
	<?php

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

	if ($dato_fra && $dato_til) {
		$fromdate=usdate($dato_fra);
		$todate=usdate($dato_til);
	}	elseif ($dato_fra && !$dato_til) {
//		$fromdate=usdate($dato_fra);
		$todate=usdate($dato_fra);
	}
	
	($kontoart=='D')?$tekst='DRV':$tekst='KRV';

	db_modify("update ordrer set art = 'R1' where art = 'RB'",__FILE__ . " linje " . __LINE__); // 20091012 - er overfloedig
		
	$r=db_fetch_array(db_select("select * from grupper where art = '$tekst' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
	if (!$r['id']) { //20170403
		db_modify("insert into grupper(beskrivelse,kodenr,art) values ('Debitorrapportvisning','1','$tekst')",__FILE__ . " linje " . __LINE__);
	}
	list($a,$b,$c,$d,$e,$f,$g)=explode(';',$r['box7']);
	
	if (isset($_GET['vis_aabenpost'])) {
		$a=$_GET['vis_aabenpost'];
		$f=NULL;
		$g=NULL;
	} elseif (isset($_GET['skjul_aabenpost']))  {
		$a=NULL;
		$f=NULL;
		$g=NULL;
	} elseif (isset($_GET['kun_debet']))  {
		$a=NULL;
		$f=$_GET['kun_debet'];
		$g=NULL;
	} elseif (isset($_GET['kun_kredit'])) {
		$a=NULL;
		$f=NULL;
		$g=$_GET['kun_kredit'];
	}	
	if (isset($_GET['vis_aaben_rykker']))     $b=$_GET['vis_aaben_rykker'];
	if (isset($_GET['vis_inkasso']))          $c=$_GET['vis_inkasso'];
	if (isset($_GET['vis_bogfort_rykker']))   $d=$_GET['vis_bogfort_rykker'];
	if (isset($_GET['vis_afsluttet_rykker'])) $e=$_GET['vis_afsluttet_rykker'];
	
	$box7="$a;$b;$c;$d;$e;$f;$g";
	$qtxt="update grupper set box7='$box7' where art='$tekst' and kodenr='1'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);

	$vis_aabenpost=$a;
	$vis_aaben_rykker=$b;
	$vis_inkasso=$c;
	$vis_bogfort_rykker=$d;
	$vis_afsluttet_rykker=$e;
	$kun_debet=$f;
	$kun_kredit=$g;
	($a || $f || $g)?$skjul_aabenpost=NULL:$skjul_aabenpost='on';
	
	if ($ny_rykker) {
		print "<meta http-equiv=\"refresh\" content=\"1;URL=rapport.php?ny_rykker=1&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&rapportart=$rapportart\">"; 
	}

	if ($r=db_fetch_array(db_select("select * from grupper where art = '$tekst' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__))){
		$dato_fra=$r['box2'];
		$dato_til=$r['box3'];
		$konto_fra=$r['box4'];
		$konto_til=$r['box5'];
		$rapportart=$r['box6'];
	} 

	if ($vis_aabenpost=='on') {
		$title = "Åbne poster";
	}

	if ($kun_debet=='on') {
		$title = "Kun konti i debet";
	}

	if ($kun_kredit=='on') {
		$title = "Kun konti i kredit";
	}

	if ($skjul_aabenpost=='on') {
		$title = "Skjul åbne poster";
	}

	if ($menu=='T') {
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\">"; 
		print "<div class=\"headerbtnLft headLink\"><a href=rapport.php accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst(30,$sprog_id)."</a></div>";     
		print "<div class=\"headerTxt\">$title</div>";     
		print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
		print "</div>";
		print "<div class='content-noside'>";
		print "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align=\"center\" ><tbody><!--Tabel 1 start-->\n";
	} else {
		print "<tr><td width=100% height=\"8\">\n";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody><!--Tabel 1.2 start-->\n"; // tabel 1.2
		print "<td width=\"10%\" $top_bund><a accesskey=l href=\"rapport.php\">".findtekst(30,$sprog_id)."</a></td>\n";
		print "<td width=\"80%\" $top_bund>".findtekst(1142,$sprog_id)." - $rapportart</td>\n";
		print "<td width=\"10%\" $top_bund>\n";
	}
		print "<div style='padding:5px;'><center><select class=\"inputbox\" name=\"aabenpostmode\" onchange=\"window.open(this.options[this.selectedIndex].value,'_top')\"></div>\n";
		if ($kun_debet=='on') print "<option>".findtekst(925,$sprog_id)."</option>\n";
		elseif ($kun_kredit=='on') print "<option>".findtekst(926,$sprog_id)."</option>\n";
		elseif ($vis_aabenpost=='on') print "<option>".findtekst(924,$sprog_id)."</option>\n";
		else print "<option>".findtekst(927,$sprog_id)."</option>\n";
		if ($vis_aabenpost!='on') print "<option value=\"rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&vis_aabenpost=on\">".findtekst(924,$sprog_id)."</option>\n"; #20210701
		if ($kun_debet!='on') print "<option value=\"rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&kun_debet=on\">".findtekst(925,$sprog_id)."</option>\n";
		if ($kun_kredit!='on') print "<option  value=\"rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&kun_kredit=on\">".findtekst(926,$sprog_id)."</option>\n";
		if ($skjul_aabenpost != 'on') print "<option  value=\"rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_aabenpost=on\">".findtekst(927,$sprog_id)."</option>\n";
		print "</select></center>\n";
		if ($menu) print "<td></tr>\n";
		else print "</div>\n";
	if ($menu!='T') print "</tbody></table></td></tr><!--Tabel 1.2 slut-->\n\n"; // <- Tabel 1.2
	if ($skjul_aabenpost!='on') vis_aabne_poster($dato_fra,$dato_til,$konto_fra,$konto_til,$rapportart,$kontoart,$kun_debet,$kun_kredit);

 	//-------------------------------------- Rykkeroversigt ----------------------------------------------
 	
	if (is_numeric($konto_fra) && is_numeric($konto_til)) {
		$qtxt = "select * from ordrer where ".nr_cast('kontonr').">='$konto_fra' and ".nr_cast('kontonr')."<='$konto_til' and art LIKE 'R%' order by ".nr_cast('kontonr')."";
	} elseif ($konto_fra && $konto_fra!='*') {
		$konto_fra=str_replace("*","%",$konto_fra);
		$tmp1=strtolower($konto_fra);
		$tmp2=strtoupper($konto_fra);
		$qtxt = "select * from ordrer where (firmanavn like '$konto_fra' or lower(firmanavn) like '$tmp1' or upper(firmanavn) like '$tmp2') and art LIKE 'R%' order by firmanavn";
	}	else $qtxt = "select * from ordrer where art LIKE 'R%' order by firmanavn";

	if ($menu=='T') {
		$top_bund = "style='color:white;'";
	}

	if ($kontoart=='D' && db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__))) {
 		$x=0;
 		$taeller=0;
 		$sum=array();
 		while ($taeller <4) {  
			$sum=array();
			$taeller++;
			print "<tr><td><div class='dataTablediv'><table width=100% cellpadding=\"0\" cellspacing=\"3\" border=\"0\" class='dataTable'><thead><!--Tabel 1.3 start-->\n"; // Tabel 1.3 ->
			if ($taeller==1) {
				print "<tr><td width=10% align=center $top_bund class='sub-title-kund-left'><br></td><td colspan='6' class='sub-title-kund' width=80% align=center $top_bund>".findtekst(1130,$sprog_id)."</td><td class='sub-title-link-kund sub-title-kund' width=10% align=center $top_bund>\n";
				if ($vis_aaben_rykker=='on') print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&vis_aaben_rykker=off>".findtekst(1132,$sprog_id)." ▲</a><td class='sub-title-kund-right'></td></tr>\n";
				else print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&vis_aaben_rykker=on>".findtekst(1133,$sprog_id)." ▾</a><td class='sub-title-kund-right'></td></tr></thead></table></div><br>\n";	
			} elseif ($taeller==2) {
				print "<tr><td width=10% align=center $top_bund class='sub-title-kund-left'><br></td><td colspan='6' class='sub-title-kund' width=80% align=center $top_bund>".findtekst(1135,$sprog_id)."</td><td class='sub-title-link-kund sub-title-kund' width=10% align=center $top_bund>\n";
				if ($vis_inkasso=='on') print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&vis_inkasso=off>".findtekst(1132,$sprog_id)." ▲</a><td class='sub-title-kund-right'></tr>\n";
				else print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&vis_inkasso=on>".findtekst(1133,$sprog_id)." ▾</a><td class='sub-title-kund-right'></tr></thead></table></div><br>\n";	
			} elseif ($taeller==3) {
				print "<tr><td width=10% align=center $top_bund class='sub-title-kund-left'><br></td><td colspan='6' class='sub-title-kund' width=80% align=center $top_bund>".findtekst(1136,$sprog_id)."</td><td class='sub-title-link-kund sub-title-kund' width=10% align=center $top_bund>\n";
				if ($vis_bogfort_rykker=='on') print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&vis_bogfort_rykker=off>".findtekst(1132,$sprog_id)." ▲</a><td class='sub-title-kund-right'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td></tr>\n";
				else print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&vis_bogfort_rykker=on>".findtekst(1133,$sprog_id)." ▾</a><td class='sub-title-kund-right'></td></tr></thead></table></div><br>\n";	
			} else  {
				print "<tr><td width=10% align=center $top_bund class='sub-title-kund-left'><br></td><td colspan='6' class='sub-title-kund' width=80% align=center $top_bund>".findtekst(1137,$sprog_id)."</td><td class='sub-title-link-kund sub-title-kund' width=10% align=center $top_bund>\n";
				if ($vis_afsluttet_rykker=='on') print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&vis_afsluttet_rykker=off>".findtekst(1132,$sprog_id)." ▲</a><td class='sub-title-kund-right'></td></tr>\n";
				else print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&vis_afsluttet_rykker=on>".findtekst(1133,$sprog_id)." ▾</a><td class='sub-title-kund-right'></td></tr></thead></table></div><br>\n";	
			}
			if (($taeller==1 && $vis_aaben_rykker=='on')||($taeller==2 && $vis_inkasso=='on')||($taeller==3 && $vis_bogfort_rykker=='on')||($taeller==4 && $vis_afsluttet_rykker=='on')) {
			print "<tr><th>".findtekst(1134,$sprog_id)."</th><th>".findtekst(360,$sprog_id)."</th><th colspan=2>".findtekst(635,$sprog_id)."</th><th align=center>".findtekst(1131,$sprog_id)."</th><th colspan=3 align=left>".findtekst(934,$sprog_id)."</th><th colspan=1 align=left></th></tr>\n";	
			if ($menu=='T'){
				print "</thead><tbody>";
			} else {
				print "<tr><td colspan=9><hr></td></tr>\n";
			}
			if ($taeller==1) {$formnavn='rykker1'; $status= "< 3";}
			else  {$formnavn='rykker2'; $status= ">= 3";}
			if ($taeller==2) $inkasso="and felt_5 = 'inkasso'";
			elseif ($taeller==3) $inkasso="and (felt_5 != 'inkasso' or felt_5 is NULL)";
			else $inkasso=NULL;
			if ($taeller==4) $betalt="and betalt = 'on'";
			else $betalt="and betalt != 'on'";
			print "<form name=$formnavn action=rapport.php method=post>";

			if (is_numeric($konto_fra) && is_numeric($konto_til)) {
				$qtxt = "select * from ordrer where ".nr_cast('kontonr').">='$konto_fra' and ".nr_cast('kontonr')."<='$konto_til' and art LIKE 'R%' $betalt $inkasso and status $status order by ".nr_cast('kontonr')."";
			} elseif ($konto_fra && $konto_fra!='*') {
				$konto_fra=str_replace("*","%",$konto_fra);
				$tmp1=strtolower($konto_fra);
				$tmp2=strtoupper($konto_fra);
				$qtxt = "select * from ordrer where (firmanavn like '$konto_fra' or lower(firmanavn) like '$tmp1' or upper(firmanavn) like '$tmp2') and art LIKE 'R%' $betalt $inkasso and status $status order by firmanavn";
			}	else $qtxt = "select * from ordrer where art LIKE 'R%' $betalt $inkasso and status $status order by firmanavn";

			$q1 = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
			$x=0;
			while ($r1 = db_fetch_array($q1)) {
				$rykkernr=substr($r1['art'],-1);
				$x++;
				$sum[$x]=0;
				$udlignet=1;
				$delsum=0;
				$q2 = db_select("select * from ordrelinjer where ordre_id = '$r1[id]'",__FILE__ . " linje " . __LINE__);
				while ($r2 = db_fetch_array($q2)) {
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
				$belob=dkdecimal($sum[$x],2);
				if ($rykkernr==1) $color="#000000";
				elseif ($rykkernr==2) $color="#CC6600";
				elseif ($rykkernr==3) $color="#ff0000";
				if ($linjebg!=$bgcolor) $linjebg=$bgcolor;
				elseif ($linjebg!=$bgcolor5) $linjebg=$bgcolor5;
				print "<tr style=\"background-color:$linjebg ; color: $color;\">";
				print "<td><span title='Klik for detaljer' og for at sende rykker pr mail><a href=\"rykker.php?rykker_id=$r1[id]\">$r1[ordrenr]</a></td>";
				print "<td>$r1[firmanavn]</td><td colspan=2 align=left>$r1[ordredate]</td><td align=left>$rykkernr</td>";
				if ($udlignet || $delsum >= $sum[$x]) {
					$color="#00aa00";
					$title="Alle poster på rykkeren er betalt";
				} elseif ($delsum) {
					$color="#0000aa";
					$title="Rykkeren er delvist betalt med kr ".dkdecimal($delsum,2)."";
				} else $title="";
				print "<td colspan=3 align=left style=\"background-color:$linjebg ; color: $color;\" title='$title'>$belob</td>";	
				$tmp = $rykkernr+1;
				$tmp = "R".$tmp;
				if (!db_fetch_array(db_select("select * from ordrer where art = '$tmp' and ordrenr = '$r1[ordrenr]' and betalt != 'on'",__FILE__ . " linje " . __LINE__))) print "<td align=center><label class='checkContainerOrdreliste'><input type=checkbox name=rykkerbox[$x]><span class='checkmarkOrdreliste'></span></label>";
				else db_modify("update ordrer set betalt = 'on' where id = '$r1[id]'",__FILE__ . " linje " . __LINE__);
 
				print "</tr>\n";
			}
			if ($menu=='T') {
				print "</tbody><tfoot>";
			} else {
				print "";
			}
			print "<input type=hidden name=rapportart value=\"openpost\">";
			print "<input type=hidden name=dato_fra value=$dato_fra>";
			print "<input type=hidden name=dato_til value=$dato_til>";
			print "<input type=hidden name=konto_fra value=$konto_fra>";
			print "<input type=hidden name=konto_til value=$konto_til>";
			print "<input type=hidden name=rykkerantal value=$x>";
			print "<input type=hidden name=kontoantal value=$x>";
			if ($x) {
				if ($menu=='T'){
					print "";
				} else {
					print "<tr><td colspan=10><hr></td></tr>\n";
				}
				if ($taeller==1) print "<tr><td colspan=10 align=center><input type=submit value=\"  ".findtekst(1099,$sprog_id)." \" name=\"submit\" onClick=\"return confirmSubmit('Slet valgte ?')\">&nbsp;&nbsp;";
				else print "<tr><td colspan=10 align=center>";
				if ($taeller==2) {
					print " &nbsp;<span title='Registrerer afmærkede sager som afsluttet og fjerner dem fra listen'><input type=submit value=\"".findtekst(1138,$sprog_id)."\" name=\"submit\" onClick=\"return confirmSubmit('Afslut valgte ?')\"></span>";
				}
				else print "<input type=submit value=\"".findtekst(880,$sprog_id)."\" name=\"submit\" onClick=\"return confirmSubmit('Udskriv valgte ?')\">";
				if ($taeller==3) {
					print " &nbsp;<span title='Registrerer rykker som afsluttet og fjerner den fra listen'><input type=submit value=\"".findtekst(1138,$sprog_id)."\" name=\"submit\" onClick=\"return confirmSubmit('Afslut valgte ?')\"></span>";
					print " &nbsp;<input type=submit value=\"".findtekst(1139,$sprog_id)."\" name=\"submit\">";
				}
				if ($taeller==1) print " &nbsp;<input type=submit value=\"".findtekst(1065,$sprog_id)."\" name=\"submit\" onClick=\"return confirmSubmit('Bogf&oslash;r valgte ?')\"></td></tr>\n";
				else print "</td></tr>\n";
			}
			

			print "</form>\n";
				if ($menu=='T') {
					print "</tfoot></table></div><br></td></tr>";
				} else {
					print "</tbody></table></td></tr>";
				}
			}
		}
		print "</tbody></table>";

		if ($menu=='T') {
			print "<center><input type='button' onclick=\"location.href='rapport.php'\" accesskey='L' value='".findtekst(30,$sprog_id)."'></center>";
			include_once '../includes/topmenu/footer.php';
		} else {
			include_once '../includes/oldDesign/footer.php';
		}
		
	}
	
}


function vis_aabne_poster($dato_fra,$dato_til,$konto_fra,$konto_til,$rapportart,$kontoart,$kun_debet,$kun_kredit) {
//cho "$dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $kontoart<br>";

	global $bgcolor;
	global $bgcolor5;
	global $bruger_id;
	global $sprog_id;
	global $menu;

	if ($menu=='T') {
		$top_bund = "";
		$padding = "style='padding: 25px 20px 10px 20px;'";
	} else {
		$top_bund = $top_bund;
		$padding = "";
	}
	
	$forfaldsum=$forfaldsum_plus8=$forfaldsum_plus30=$forfaldsum_plus60=$forfaldsum_plus90=$fromdate=$linjebg=$popup=$todate=NULL;
	
	
	if ($menu=='T') {
		print "<tr><td><div class='dataTablediv'><table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\" class='dataTable'><thead>\n";
		print "<tr><th>Kontonr.</th><th>PBS</th><th>".findtekst(360,$sprog_id)."</th><th align=right class='text-right'>>90</th><th align=right  class='text-right'>60-90</th><th align=right class='text-right'>30-60</th><th align=right class='text-right'>8-30</th><th align=right class='text-right'>0-8</th><th align=right class='text-right'>I alt</th><th align=right></th>";
		print "</thead><tbody>";
	} else {
		print "<tr><td><table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>\n";
		print "<tr><td>Kontonr.</th><th>PBS</td><td>".findtekst(360,$sprog_id)."</td><td align=right>>90</td><td align=right>60-90</td><td align=right>30-60</td><td align=right>8-30</td><td align=right>0-8</td><td align=right>I alt</td><td></td>";
	}

	$currentdate=date("Y-m-d");
	if ($dato_fra && $dato_til) {
		$fromdate=usdate($dato_fra);
		$todate=usdate($dato_til);
	}	elseif ($dato_fra && !$dato_til) {
		$todate=usdate($dato_fra);
	}

	print "<form name=aabenpost action=rapport.php method=post>";

	if ($menu=='T') {
		print "";
	} else {
		print "<tr><td colspan=10><hr></td></tr>\n";
	}
		
	$x=0;
	$x=0;
  $qtxt = "select distinct(konto_id) as konto_id from openpost ";
  if (!$todate || $todate >= $currentdate) $qtxt.= "where udlignet = '0'"; #20200103
	$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$op_id[$x]=$r['konto_id'];
		$x++;
	}

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
	$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (in_array($r['id'],$op_id)) {
		$x++;
		$konto_id[$x]=$r['id'];
		if (!isset($kontoudtog[$x])) $kontoudtog[$x]=NULL;
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
		$pbs[$x]=trim($r['pbs']);
		$pbs_nr[$x]=trim($r['pbs_nr']);
		($pbs[$x] && $pbs_nr[$x])?$pbs[$x]='&#10004;':$pbs[$x]=NULL;
	}
	}
	$kontoantal=$x;	
	$sum=0;
	$kontrolsum=0;
	$udlign=NULL;
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

		$ks=0;
		while ($r=db_fetch_array($q)) {
      if ($r['valutakurs']*1 && $r['valuta']!='-') {
				$kontrol+=afrund($r['amount']*$r['valutakurs']/100,2); //2012.03.30 afrunding rettet til 2 (Ørediff hos saldi_390) 
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
				
				if ($r['valuta']) $valuta=$r['valuta']; // <- 2009.05.05
				else $valuta='DKK';
				if ($r['valutakurs']) $valutakurs=$r['valutakurs'];
				else $valutakurs=100;
				$udlignet="0";
				($valuta=='DKK')?$amount=afrund($r['amount'],2):$amount=afrund($r['amount'],3); //2012.04.03 se saldi_ 
				if (!$forfaldsdag && $kontoart=='D' && $amount < 0) $forfaldsdag=$r['transdate'];
				elseif (!$forfaldsdag && $kontoart=='K' && $amount > 0) $forfaldsdag=$r['transdate'];
				elseif (!$forfaldsdag) $forfaldsdag=$r['forfaldsdate'];
				if ($r['faktnr'] && !$r['kladde_id'] && ($amount > 0 || ($amount < 0 && strstr($r['beskrivelse'],"Lev. fakt"))) && $r['refnr']>1) { //rettet 20090715
					if ($r2=db_fetch_array(db_select("select betalingsbet, betalingsdage from ordrer where id='$r[refnr]' and fakturanr = '$r[faktnr]'",__FILE__ . " linje " . __LINE__))){
						$betalingsbet[$x]=trim($r2['betalingsbet']);
						$betalingsdage[$x]=trim($r2['betalingsdage']);
						$tmp=usdate(forfaldsdag($transdate, $betalingsbet[$x], $betalingsdage[$x]));
						if ($tmp!=$forfaldsdag) {
							$forfaldsdag=$tmp;
							db_modify("update openpost set forfaldsdate = '$forfaldsdag' where id='$oid'",__FILE__ . " linje " . __LINE__);
						}
					}
				} 
				$amount*=$valutakurs/100;
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
			print "<td><a href=rapport.php?rapportart=kontokort&kilde=openpost&kto_fra=$konto_fra&kilde_kto_til=$konto_til&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$kontonr[$x]&konto_til=$kontonr[$x]&submit=ok>";
			print "<span title='Klik for detaljer'>$kontonr[$x]</span></a></td>";
			print "<td>$pbs[$x]</td>";
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
			$tmp=dkdecimal($forfalden_plus90,2);
			print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			if (($kontoart=='D' && $forfalden_plus60 > 0) || ($kontoart=='K' && $forfalden_plus60 < 0)) $color="rgb(255, 0, 0)";
			else $color="rgb(0, 0, 0)";
			$tmp=dkdecimal($forfalden_plus60,2);
			print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			if (($kontoart=='D' && $forfalden_plus30 > 0) || ($kontoart=='K' && $forfalden_plus30 < 0)) $color="rgb(255, 0, 0)";
			else $color="rgb(0, 0, 0)";
			$tmp=dkdecimal($forfalden_plus30,2);
			print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			if (($kontoart=='D' && $forfalden_plus8 > 0) || ($kontoart=='K' && $forfalden_plus8 < 0)) $color="rgb(255, 0, 0)";
			else $color="rgb(0, 0, 0)";
			$tmp=dkdecimal($forfalden_plus8,2);
			print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			if (($kontoart=='D' && $forfalden > 0) || ($kontoart=='K' && $forfalden < 0)) $color="rgb(255, 0, 0)";
			else $color="rgb(0, 0, 0)";
			$tmp=dkdecimal($forfalden,2);
			print "<td align=right><span style='color: $color;'>$tmp</span></td>";
			if (afrund($kontrol,2)!=afrund($y,2)) {
				ret_openpost($konto_id[$x]);
				$tmp=dkdecimal($kontrol,2);
			} else $tmp=dkdecimal($y,2);
			if (abs($y)<0.01 && abs($kontrol)<0.01) {
				$udlign.=$konto_id[$x].",";
				print "<td align=right title=\"Klik her for at udligne &aring;bne poster\"><a href=\"rapport.php?submit=ok&rapportart=openpost&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&udlign=$konto_id[$x]\">$tmp</a></td>";
			}
			else {print "<td align=right>$tmp</td>";}
			if (($kontoudtog[$x]=='on')&&($kontoart=="D")) print "<td align=center><label class='checkContainerOrdreliste'><input type=checkbox name=kontoudtog[$x] checked><span class='checkmarkOrdreliste'></span></label>";
			elseif($kontoart=="D")  { print "<td align=center><label class='checkContainerOrdreliste'><input type=checkbox name=kontoudtog[$x]><span class='checkmarkOrdreliste'></span></label>"; }
			else { print "<td></td>"; }
			print "</tr>\n";
		}
		print "<input type=hidden name=rykkerbelob[$x] value=$rykkerbelob>";
	}

	if (!isset ($forfaldsum_plus90)) $forfaldsum_plus90 = NULL;
	if (!isset ($forfaldsum_plus60)) $forfaldsum_plus60 = NULL;
	if (!isset ($forfaldsum_plus30)) $forfaldsum_plus30 = NULL;
	if (!isset ($forfaldsum_plus8)) $forfaldsum_plus8 = NULL;
	if (!isset ($forfaldsum)) $forfaldsum = NULL;

	$forfaldsum_plus90=afrund($forfaldsum_plus90,2);
	$forfaldsum_plus60=afrund($forfaldsum_plus60,2);
	$forfaldsum_plus30=afrund($forfaldsum_plus30,2);
	$forfaldsum_plus8=afrund($forfaldsum_plus8,2);

	if ($menu=='T') {
		print "</tbody><tfoot>";
		print "<tr><td colspan='2'><br></td><td><b>I alt</b></td>";
	} else {
		print "<tr><td colspan=10><hr></td></tr>\n";
		print "<tr><td colspan='2'><br></td><td><b>I alt</b></td>";
	}

	if ($forfaldsum_plus90 != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfaldsum_plus90,2);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	if ($forfaldsum_plus60 != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfaldsum_plus60,2);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	if ($forfaldsum_plus60 != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfaldsum_plus30,2);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	if ($forfaldsum_plus30 != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfaldsum_plus8,2);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	if ($forfaldsum != 0) $color="rgb(255, 0, 0)";
	else $color="rgb(0, 0, 0)";
	$tmp=dkdecimal($forfaldsum,2);
	print "<td align=right><span style='color: $color;'>$tmp</span></td>";
	$color="rgb(0, 0, 0)";
  ($sum<=$kontrolsum)?$tmp=dkdecimal($kontrolsum,2):$tmp=dkdecimal($sum,2);
	print "<td align=right><span style='color: $color;'>$tmp</span>";
	print "<td align=right></td>";
	print "<input type=hidden name=rapportart value=\"openpost\">";
	print "<input type=hidden name=dato_fra value=$dato_fra>";
	print "<input type=hidden name=dato_til value=$dato_til>";
	print "<input type=hidden name=konto_fra value=$konto_fra>";
	print "<input type=hidden name=konto_til value=$konto_til>";
	print "<input type=hidden name=kontoantal value=$kontoantal></td></tr>";

	if ($kontoart=='D') {
		$overlib4="<span class='CellComment'>".findtekst(242,$sprog_id)."</span>";
		print "<tr><td colspan='10' align='center' class='border-hr-top'><span title=\"Klik her for at maile kontoudtog til de modtagere som er afm&aelig;rket herover\">";
		print "<input type=submit value=\"Mail kontoudtog\" class='button blue medium' name=\"submit\"></span>&nbsp;&nbsp;";
		print "<span title='Klik her for at oprette rykker til de som er afm&aelig;rkede herover'>";
		print "<input type=submit value=\"Opret rykker\" class='button green medium' name=\"submit\"></span>&nbsp;&nbsp;";
		if ($udlign) {
			$udlign=trim($udlign,"'");
			print "	<input class='button blue medium' type='button' onclick=\"location.href='rapport.php?rapportart=openpost&udlign=$udlign';\" title='Klik her for at udligne alle med saldoen' value='Udlign alle' />&nbsp;&nbsp;";
			print "<span class='CellWithComment'><input type=submit class='button red medium' value=\"Ryk alle\" name=\"submit\"> $overlib4</span></td>"; 
		} else {
			print "<span class='CellWithComment'><input type=submit class='button red medium' value=\"Ryk alle\" name=\"submit\"> $overlib4</span></td>";
		}
		print "</tr>\n";
	}
	print "</form>\n";

	if ($menu=='T') {
		print "</tfoot></table></div>";
	} else {
		print "<tr><td colspan=10><hr></td></tr>\n";
		print "</tbody></table>";
	}

	if ($menu=='T') {
		print "</tfoot></table>";
		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}

	
} //endfunc vis_aabne_poster


//--------------------------------------------------------------------------------------
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

function bogfor_nu($id) {
	
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
	$q = db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
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
		$afd=$afd*1; //sikkerhed for at 'afd' har en vaerdi 
		 
		$bilag=0;
		if (!$valutakurs && $valuta != 'DKK') { //20140628
				if ($r2=db_fetch_array(db_select("select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe = ".nr_cast("grupper.kodenr")." and valuta.valdate <= '$fakturadate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
				$valutakurs=$r2['kurs'];
			} else {
				print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs i $valuta d. $fakturadate')\">";	
				return("Ups - ingen valutakurs i $valuta d. $fakturadate");
			}
		}

		if ($valutakurs && $valutakurs!=100) $sum=$sum*$valutakurs/100; // Omregning til DKK.
		$beskrivelse="Gebyr mm. fra tidligere rykker";	
		
		$qtxt = "select id from openpost where konto_id = '$konto_id' and faktnr = '$fakturanr' and refnr = '$id' and amount = '$sum' ";
		$qtxt.= "and beskrivelse = '$beskrivelse' and udlignet = '0' and transdate = '$transdate' and kladde_id = '0'";
		if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { #20200109
			return($id);
			exit;
		}
		if ($sum) {
			$qtxt = "insert into openpost "; 
			$qtxt.= " (konto_id, konto_nr, faktnr, refnr, amount, beskrivelse, udlignet, transdate, kladde_id,valuta,valutakurs)";
			$qtxt.= " values "; #20210422 - Addad afrund in next line
			$qtxt.= "('$konto_id', '$kontonr', '$fakturanr', '$id','". afrund($sum,2) ."', '$beskrivelse', '0', '$transdate', '0','DKK','100')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$r = db_fetch_array(db_select("select box2 from grupper where art = 'DG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
		$kontonr=$r['box2']; // Kontonr ændres fra at være leverandørkontonr til finanskontonr

		if ($sum>0) {$debet=$sum; $kredit='0';}
		else {$debet='0'; $kredit=$sum*-1;}
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
		if ($sum)	db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt', '$id')",__FILE__ . " linje " . __LINE__);
		$y=0;
		$bogf_konto = array();
		$q = db_select("select * from ordrelinjer where ordre_id='$id'",__FILE__ . " linje " . __LINE__); // 20151019
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
				if ($valutakurs) {$kredit=$kredit*$valutakurs/100;$debet=$debet*$valutakurs/100;} // Omregning til DKR.
				$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
				if ($pris[$y]) db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', '$bogf_konto[$y]', '$fakturanr', '$debet', '$kredit', '0','$afd', '$logdate', '$logtime', '$projekt', '$id')",__FILE__ . " linje " . __LINE__);
			}
		}
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


// ------------------------------------------------------------------------------------------------------------
function forside($dato_fra,$dato_til,$konto_fra,$konto_til,$rapportart,$kontoart) {
	global $brugernavn;
	global $bruger_id;
	global $top_bund;
	global $md;
//	global $returside;
	global $jsvars;
	global $popup;
	global $menu;
	global $rettigheder;
	$husk="";

	print "<script LANGUAGE=\"JavaScript\" TYPE=\"text/javascript\" SRC=\"../javascript/overlib.js\"></script>";
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
if (!isset ($sprog_id)) $sprog_id = NULL;
	($kontoart=='D')?$title=findtekst(449,$sprog_id):$title=findtekst(450,$sprog_id);
	($popup)?$returside="../includes/luk.php":$returside="../index/menu.php";

	if ($menu=='T') {
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\">"; 
		print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";     
		print "<div class=\"headerTxt\">$title</div>";     
		print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
		print "</div>";
		print "<div class='content-noside'>";
		print "<div class='dataTablediv' style='width:700px; margin: auto;'><table width='100%' cellpadding=\"1\" cellspacing=\"1\" border=\"0\" align=\"center\" class='dataTableSmall'><tbody>\n";
	} else {
		print "<table cellpadding=\"1\" cellspacing=\"3\" border=\"0\" width=100% height=100% valign=\"top\"><tbody>";
		include("../includes/oldDesign/header.php");
		print "<tr><td height=\"8\" width=\"10%\" $top_bund><a href=$returside accesskey=L>".findtekst(30,$sprog_id)."</a></td>";
		print "<td width=\"80%\" $top_bund>$title</td>";
		print "<td width=\"10%\" $top_bund><br></td>";
		print "</tr><tr><td height=99%><br></td></td>";
		print "<td valign='top' align='center'><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" align=\"center\"><tbody>\n";
		print "<tr><td align=center colspan=\"5\"><big><b>$title</b></big><br><br></td></tr>";
	}

	if ($menu=='T') {
			$dato=$dato_fra;
			if ($dato_til) $dato.=":$dato_til";
			$konto=$konto_fra;
			if ($konto_til) $konto.=":$konto_til";
		
			$tekst1=findtekst(437,$sprog_id);
			$tekst2=findtekst(438,$sprog_id);
			$tekst3=findtekst(439,$sprog_id);
#			if (strpos($tekst3,'kundenr')) db_modify("update tekster set tekst = '' where tekst_id = 439",__FILE__ . " linje " . __LINE__);
			$tekst4=findtekst(440,$sprog_id);
			$tekst5=findtekst(451,$sprog_id);
			$tekst6=findtekst(452,$sprog_id);
			$overlib1="<span class='CellComment'>$tekst1</span>";
			$overlib2="<span class='CellComment'>$tekst3</span>";
			$overlib3="<span class='CellComment'>$tekst5</span>";
		print "<form name=\"regnskabsaar\" action=\"rapport.php\" method=\"post\">";
		print "<tr>";
		print "<td align=\"center\" colspan='2' class='CellWithComment'><b>$tekst2:</b> &nbsp; &nbsp; <input class=\"inputbox\" style=\"width:129px\" type=\"text\" name=\"dato\" value=\"$dato\"> $overlib1</td>";
		print "<td align=\"center\" colspan='2' class='CellWithComment'><b>$tekst4:</b> &nbsp; &nbsp; <input class=\"inputbox\" style=\"width:129px\" type=\"text\" name=\"konto\" value=\"$konto\"> $overlib2</td>";
		print "</tr>";
		print "<tr>";
			$tekst1=findtekst(441,$sprog_id);
			$tekst2=findtekst(444,$sprog_id);
		print "<td align=\"center\"><input style=\"width:130px\" type=\"submit\" value=\"$tekst1\" name=\"openpost\" title=\"$tekst2\"></td>";
			$tekst1=findtekst(442,$sprog_id);
			$tekst2=findtekst(445,$sprog_id);
		print "<td align=\"center\"><input style=\"width:115px\" type=\"submit\" value=\"$tekst1\" name=\"kontosaldo\" title=\"$tekst2\"></td>";
			$tekst1=findtekst(443,$sprog_id);
			$tekst2=findtekst(446,$sprog_id);
		print "<td align=\"center\"><input style=\"width:115px\" type=\"submit\" value=\"$tekst1\" name=\"kontokort\" title=\"$tekst2\"></td>";
		print "<td align=\"center\" class='CellWithComment'><b>$tekst6:</b>  &nbsp; &nbsp; <label class='checkContainerVisning'><input class=\"inputbox\" type=\"checkbox\" name=\"husk\" $husk><span class='checkmarkVisning'></span></label> $overlib3</td>";
		print "</tr>";
		print "<tr><td></td></tr>";
		print "<tr><td colspan=5 class='border-hr-top'></td></tr>\n";
		print "<tr>";
		if ($kontoart=='D') {
				$tekst1=findtekst(447,$sprog_id);
				$tekst2=findtekst(448,$sprog_id);
				$tekst3=findtekst(455,$sprog_id);
			print "<td align=\"center\"><span title=\"$tekst1\" onClick=\"window.location.href='top100.php'\"><input style=\"width:115px\" type=button value=\"$tekst2\" name=\"submit\"></span></td>";
			if (db_fetch_array(db_select("select id from grupper where art = 'POS' and box2 >= '1'",__FILE__ . " linje " . __LINE__))) {
				print "<td align=\"center\"><input title=\"".findtekst(918,$sprog_id)."\" style=\"width:115px\" type=\"submit\" value=\"".findtekst(918,$sprog_id)."\" name=\"salgsstat\"></td>";
				print	"<td align=center><a href=\"kassespor.php\"><input title=\"Oversigt over POS transaktioner\" style=\"width:115px\" type=\"button\" value=\"$tekst3\"></a></td>";
			} else {
				print "<td align=\"center\" colspan='2'><input title=\"".findtekst(918,$sprog_id)."\" style=\"width:115px\" type=\"submit\" value=\"".findtekst(918,$sprog_id)."\" name=\"salgsstat\"></td>";
			}
			if (db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box10 >= 'on'",__FILE__ . " linje " . __LINE__))) {
				$tekst1=findtekst(531,$sprog_id);
				$tekst2=findtekst(532,$sprog_id);
				print	"<td align=center><span onClick=\"javascript:location.href='../debitor/betalingsliste.php'\"><input title=\"$tekst1\" style=\"width:145px\" type=\"button\" value=\"$tekst2\"></span></td>\n";
			} elseif (file_exists("../debitor/multiroute.php")) {
				print "<td align=center><span onclick=\"javascript:location.href='../debitor/multiroute.php'\"><input title=\"Multiroute\" style=\"width:135px\" type=\"button\" value=\"".findtekst(923,$sprog_id)."\"></span></td>\n";
			} 
			print	"</tr>\n";
		} else {
			$tekst1=findtekst(531,$sprog_id);
			$tekst2=findtekst(532,$sprog_id);
			print "<td align=\"center\" colspan='2'>";
			if (db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box10 >= 'on'",__FILE__ . " linje " . __LINE__))) {
				print	"<span onClick=\"javascript:location.href='../kreditor/betalingsliste.php'\">\n";
				print "<input title=\"$tekst1\" style=\"width:150px\" type=\"button\" value=\"$tekst2\">\n";
				print "</span></td>\n";
			}
			print "<td align='center' colspan='2'><input title=\"Salgsstat\" style=\"width:115px\" type=\"submit\" value=\"".ucfirst(findtekst(918,$sprog_id))."\" name=\"salgsstat\"></td>\n";
		}
		print	"</td></tr>\n</form>\n";
		print "</tbody></table></div>";
	} else {
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
			$overlib1="<span class='CellComment'>$tekst1</span>";
			$overlib2="<span class='CellComment'>$tekst3</span>";
			$overlib3="<span class='CellComment'>$tekst5</span>";
		print "<tr><td align=\"center\" class='CellWithComment'><b>$tekst2</b> $overlib1</td><td align=\"center\" colspan=3 class='CellWithComment'><b>$tekst4</b> $overlib2</td><td align=\"center\" class='CellWithComment'><b>$tekst6</b> $overlib3</td></tr>";
		print "<form name=\"regnskabsaar\" action=\"rapport.php\" method=\"post\">";
		print "<tr><td align=\"center\" class='CellWithComment'><input class=\"inputbox\" style=\"width:129px\" type=\"text\" name=\"dato\" value=\"$dato\"> $overlib1</td>";
		print "<td align=\"center\" class='CellWithComment' colspan=3><input class=\"inputbox\" style=\"width:129px\" type=\"text\" name=\"konto\" value=\"$konto\"> $overlib2</td>";
		print "<td align=\"center\" class='CellWithComment'><label class='checkContainerVisning'><input class=\"inputbox\" type=\"checkbox\" name=\"husk\" $husk><span class='checkmarkVisning'></span></label> $overlib3</td></tr>";
		$tekst1=findtekst(441,$sprog_id);
		$tekst2=findtekst(444,$sprog_id);
		print "<tr><td align=center><input style=\"width:120px\" type=\"submit\" value=\"$tekst1\" name=\"openpost\" title=\"$tekst2\"></td>";
		$tekst1=findtekst(442,$sprog_id);
		$tekst2=findtekst(445,$sprog_id);
		print "<td align=center colspan=3><input style=\"width:115px\" type=\"submit\" value=\"$tekst1\" name=\"kontosaldo\" title=\"$tekst2\"></td>";
		$tekst1=findtekst(443,$sprog_id);
		$tekst2=findtekst(446,$sprog_id);
		print "<td align=center><input style=\"width:115px\" type=\"submit\" value=\"$tekst1\" name=\"kontokort\" title=\"$tekst2\"></td></tr>";
		if ($kontoart=='D') print "<tr><td colspan=\"6\"><hr></td></tr>";
		if ($kontoart=='D') {
			$tekst1=findtekst(447,$sprog_id);
			$tekst2=findtekst(448,$sprog_id);
			$tekst3=findtekst(455,$sprog_id);
			print "<tr>";
			if ($popup) {
				print "<td align=center><span onClick=\"javascript:top100=window.open('top100.php','top100','$jsvars');top100.focus();\" title=\"a $tekst1\"><input style=\"width:115px\" type=submit value=\"$tekst2\" name=\"submit\"></span></td>";
				if (db_fetch_array(db_select("select id from grupper where art = 'POS' and box2 >= '1'",__FILE__ . " linje " . __LINE__))) {
					print "<td colspan=3 align=center><span onClick=\"javascript:kassespor=window.open('kassespor.php','kassespor','$jsvars');kassespor.focus();\" title=\"$tekst1\"><input style=\"width:115px\" type=submit value=\"$tekst3\" name=\"submit\"></span></td>";
				}
			} else {
				print "<td align=center><span title=\"$tekst1\" onClick=\"window.location.href='top100.php'\"><input style=\"width:115px\" type=button value=\"$tekst2\" name=\"submit\"></span></td>";
				print "<td align=center><input title=\"".findtekst(918,$sprog_id)."\" style=\"width:115px\" type=\"submit\" value=\"".findtekst(918,$sprog_id)."\" name=\"salgsstat\"></td>";
				if (db_fetch_array(db_select("select id from grupper where art = 'POS' and box2 >= '1'",__FILE__ . " linje " . __LINE__))) {
					print	"<td colspan=2 align=center><a href=\"kassespor.php\"><input title=\"Oversigt over POS transaktioner\" style=\"width:115px\" type=\"button\" value=\"$tekst3\"></a></td>";
				}
			}
			if (db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box10 >= 'on'",__FILE__ . " linje " . __LINE__))) {
				$tekst1=findtekst(531,$sprog_id);
				$tekst2=findtekst(532,$sprog_id);
				print	"<td><span onClick=\"javascript:location.href='../debitor/betalingsliste.php'\"><input title=\"$tekst1\" style=\"width:135px\" type=\"button\" value=\"$tekst2\"></span></td>\n";
			} elseif (file_exists("../debitor/multiroute.php")) {
				print "<td><span onclick=\"javascript:location.href='../debitor/multiroute.php'\"><input title=\"Multiroute\" style=\"width:135px\" type=\"button\" value=\"".findtekst(923,$sprog_id)."\"></span></td>\n";
			} 
			print	"</tr>\n";
		} else {
			$tekst1=findtekst(531,$sprog_id);
			$tekst2=findtekst(532,$sprog_id);
			print "<tr><td colspan=\"3\" align=center>\n";
				if (db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box10 >= 'on'",__FILE__ . " linje " . __LINE__))) {
				print	"<span onClick=\"javascript:location.href='../kreditor/betalingsliste.php'\">\n";
				print "<input title=\"$tekst1\" style=\"width:115px\" type=\"button\" value=\"$tekst2\">\n";
				print "</span>\n";
			}
			print "<input title=\"Salgsstat\" style=\"width:115px\" type=\"submit\" value=\"".ucfirst(findtekst(918,$sprog_id))."\" name=\"salgsstat\">\n";
		}
		print	"</td></tr>\n</form>\n";
		print "</tbody></table>";
		print "</tbody></table>";
	}

	if ($menu=='T') {
		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}
	
	
}

//------------------------------------------------------------------------------------------------------------
function kontokort($dato_fra,$dato_til,$konto_fra,$konto_til,$rapportart,$kontoart) {

//	global $connection;
	global $bruger_id;
	global $top_bund;
	global $md;
	global $popup;
//	global $returside;
//	global $rapportart;
	global $bgcolor;
	global $bgcolor5;
	global $regnaar;
	global $menu;

	$title = "Kontokort";
	
	$email=$forfaldsum=$fromdate=$kto_fra=$kto_til=$returside=$todate=NULL;

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
	($kontoart=='D')?$tekst='DRV':$tekst='KRV';
	$qtxt = "select * from grupper where art = '$tekst' and kodenr = '$bruger_id'";
	if(isset($_GET['returside'])) $returside= $_GET['returside'];
	elseif ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
		$dato_fra=$r['box2'];
		$dato_til=$r['box3'];
		$konto_fra=$r['box4'];
		$konto_til=$r['box5'];
		$rapportart=$r['box6'];
	} 
	if ($r=db_fetch_array(db_select("select id from grupper where art = 'PRJ'",__FILE__ . " linje " . __LINE__))) $prj='Projekt';
	else $prj='';

	if ($tmp && $tmp!=$konto_fra && !$returside) {
		$returside="rapport.php?rapportart=$rapportart"; //&submit=ok&regnaar=$regnaar&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til";
		$konto_fra=$tmp;
		$konto_til=$konto_fra;
	} elseif (!$returside) $returside="rapport.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til";

	if ($dato_fra && $dato_til) {
		$fromdate=usdate($dato_fra);
		$todate=usdate($dato_til);
	}	elseif ($dato_fra && !$dato_til) {
		$todate=usdate($dato_fra);
	}
	$kontonr=array();
	$kto_id=array();
	$x=0;
	if (is_numeric($konto_fra) && is_numeric($konto_til)) { #changed 20210816
#		$qtxt = "select id from adresser where ".nr_cast('kontonr').">='$konto_fra' and ".nr_cast('kontonr')."<='$konto_til' and art = '$kontoart' order by ".nr_cast('kontonr')."";
		$qtxt = "select id,kontonr from adresser where art = '$kontoart' order by kontonr";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if ($konto_fra <= $r['kontonr'] && $konto_til >= $r['kontonr']) {
				$x++;
				$konto_id[$x]=$r['id'];
			}
		}
	} else {
		if ($konto_fra && $konto_fra!='*') {
			$konto_fra=str_replace("*","%",$konto_fra);
			$tmp1=strtolower($konto_fra);
			$tmp2=strtoupper($konto_fra);
			$qtxt = "select id from adresser where (firmanavn like '$konto_fra' or lower(firmanavn) like '$tmp1' or ";
			$qtxt = "upper(firmanavn) like '$tmp2') and art = '$kontoart' order by firmanavn";
		}	else $qtxt = "select id from adresser where art = '$kontoart' order by firmanavn";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$konto_id[$x]=$r['id'];
		}
	}
	$kontoantal=$x;
	$x=0;
	// finder alle konti med bevaegelser i den anfoerte periode eller aabne poster fra foer perioden
	if ($kontoantal==1) { //20140505 - Fjerner udligning hvis udligningssum er skæv.
		$y=0;
		$qtxt="select distinct(udlign_id) from openpost where udlignet = '1' and udlign_id>'0' and konto_id='$konto_id[1]'";
		$q = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$udlign_id[$y]=$r['udlign_id']*1;
			$y++;
		}
	}
	
	for ($y=1;$y<=$kontoantal;$y++) {
#		if ($todate) $qtxt="select amount from openpost where transdate<='$todate' and konto_id='$konto_id[$y]'";
#		else $qtxt="select amount from openpost where konto_id='$konto_id[$y]'";
#		$q = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
#		while ($r = db_fetch_array($q)) {
			if (!in_array($konto_id[$y],$kto_id)) {
				$x++;
				$kto_id[$x]=$konto_id[$y];
			}
#		}
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
		$valutakode*=1;//20140505 


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
		$firstdate=date("Y-m-d");
		$lastdate='1970-01-01';
		
		$y=0;
		$qtxt="select max(id) as max_valdif_id from openpost where konto_id='$kto_id[$x]' and abs(amount) = '0.001'";
		$r2 = db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
		$max_valdif_id=$r2['max_valdif_id'];
						
		if ($todate) $qtxt="select * from openpost where konto_id='$kto_id[$x]' and transdate<='$todate' order by transdate,id,faktnr,refnr"; //20160414
		else $qtxt= "select * from openpost where konto_id='$kto_id[$x]' order by transdate,id,faktnr,refnr"; //20160414
		$q2 = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$y++;
			($baggrund==$bgcolor)?$baggrund=$bgcolor5:$baggrund=$bgcolor;
			$oppid[$y]=$r2['id'];
			$amount[$y]=afrund($r2['amount'],2);
			$amount[$y]=$r2['amount'];
			$beskrivelse[$y]=$r2['beskrivelse'];
			$valutakurs[$y]=$r2['valutakurs']*1;
			$oppvaluta[$y]=$r2['valuta'];
			$faktnr[$y]=$r2['faktnr'];
			if (!$oppvaluta[$y]) {
				$oppvaluta[$y]='DKK';
				$valutakurs[$y]=100; //20140503
			}
			$forfaldsdag[$y]=$r2['forfaldsdate'];
			$kladde_id[$y]=$r2['kladde_id'];
			($r2['projekt'])?$projekt[$y]=$r2['projekt']:$projekt[$y]='';
			($r2['kladde_id'])?$refnr[$y]=$r2['refnr']:$refnr[$y]='';
			if (!strlen($valutakurs[$y])) $valutakurs[$y]=100;
			$transdate[$y]=$r2['transdate'];
			if ($firstdate > $transdate[$y]) $firstdate = $transdate[$y];
			if ($firstdate < $transdate[$y]) $lastdate = $transdate[$y];
			$udlignet[$y]=$r2['udlignet'];
			$udlign_id[$y]=$r2['udlign_id'];

			if ($oppvaluta[$y]!='DKK' && $valutakurs[$y]==100) {
				$r3=db_fetch_array(db_select("select kodenr from grupper where box1 = '$oppvaluta[$y]' and art='VK'",__FILE__ . " linje " . __LINE__));
				$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__));
					$valutakurs[$y]=$r3['kurs']*1;
					$dkkamount[$y]=$amount[$y]*$valutakurs[$y]/100;
					$beskrivelse[$y] = $r2['beskrivelse']." - (Omregnet fra DKK til $valuta".dkdecimal($dkkamount[$y],2).", kurs ".dkdecimal($valutakurs[$y],2).")";
			} elseif ($valuta!="DKK" && $valutakurs[$y]==100) {
				if ($r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__))) {
					$dkkamount[$y]=$amount[$y];
					$amount[$y]=$amount[$y]*100/$r3['kurs'];
					$beskrivelse[$y] = $r2['beskrivelse']." - (Omregnet til $valuta fra DKK ".dkdecimal($dkkamount[$y],2).", kurs ".dkdecimal($r3['kurs'],2).")";
				} elseif ($r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' order by valdate",__FILE__ . " linje " . __LINE__))) {
					$amount[$y]=$amount[$y]*100/$r3['kurs'];
					$beskrivelse[$y] = $r2['beskrivelse']." - (Omregnet til $valuta fra DKK ".dkdecimal($dkkamount[$y],2).", kurs ".dkdecimal($r3['kurs'],2).")";
				}
			} elseif (($oppvaluta[$y]!='DKK' && $valuta=="DKK" && $valutakurs[$y]!=100)) {
					$beskrivelse[$y] = $r2['beskrivelse']." - (Omregnet til DKK fra ".$oppvaluta[$y]." ".dkdecimal($amount[$y],2).", kurs ".dkdecimal($valutakurs[$y],2).")";
					$amount[$y]=$amount[$y]*$valutakurs[$y]/100;
			} elseif ($valuta!="DKK" && $valuta==$oppvaluta[$y] && $valutakurs[$y]!=100) {
				$valutakurs[$y]*=1;
				if (!$valutakurs[$y] && $oppvaluta[$y] && $oppvaluta[$y]!='-') {
					$r3=db_fetch_array(db_select("select kodenr from grupper where box1 = '$oppvaluta[$y]' and art='VK'",__FILE__ . " linje " . __LINE__));
					$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__));
					$valutakurs[$y]=$r3['kurs']*1;
				} 
				$dkkamount[$y]=$amount[$y]*$valutakurs[$y]/100;
					if ($oppvaluta[$y]!='-' && abs($amount[$y])>=0.005) {
						 if (!strpos($beskrivelse[$y],'Udligning af valutadiff')) $beskrivelse[$y] = $r2['beskrivelse']." - (DKK ".dkdecimal($dkkamount[$y],2).")";
					} elseif (abs($amount[$y])<0.005) $beskrivelse[$y] = $r2['beskrivelse'];
					else $beskrivelse[$y] = $r2['beskrivelse']." - (DKK ".dkdecimal($amount[$y],2).")";
					} elseif($oppvaluta[$y]!=$valuta && $oppvaluta[$y] != '-') {
				if (!$valutakurs[$y]) {
					$r3=db_fetch_array(db_select("select kodenr from grupper where box1 = '$oppvaluta[$y]' and art='VK'",__FILE__ . " linje " . __LINE__));
					$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__));
					$valutakurs[$y]=$r3['kurs']*1;
				}
				$r3=db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta' and art='VK'",__FILE__ . " linje " . __LINE__));
				$r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__));
				$dagskurs=$r3['kurs']*1;
				$beskrivelse[$y].=" $oppvaluta[$y] ".dkdecimal($amount[$y],2)." Kurs $valutakurs[$y]";
				$amount[$y]*=$valutakurs[$y]/$dagskurs;
				$dkkamount[$y]=$amount[$y]*$valutakurs[$y]/100;
			} else {
				$beskrivelse[$y] = $r2['beskrivelse'];
				$dkkamount[$y]=$amount[$y]; //20160503
			}
			if ($oppvaluta[$y]=="-") {
				$dkkamount[$y]=$amount[$y];
				$amount[$y]=0;
				$forfaldsdate[$y]='';
			}
		}

		$luk= "<a accesskey=L href=\"$returside\">";

	if ($menu=='T') {
		print "";
	} else {
		print "<center><table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	}
	if ($menu=='T' && $x==1) {
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\">"; 
		print "<div class=\"headerbtnLft headLink\"><a href=$returside accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst(30,$sprog_id)."</a></div>";     
		print "<div class=\"headerTxt\">$title</div>";     
		print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
		print "</div>";
		print "<div class='content-noside'>";
	} elseif ($x==1) {
		include("../includes/oldDesign/header.php");
		print "<tr><td colspan=\"9\" height=\"8\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; //B
		print "<td width=\"10%\" $top_bund>$luk ".findtekst(30,$sprog_id)."</a></td>";
		if ($kontoart=='K') $tekst="".findtekst(1140,$sprog_id)." - ".lcfirst(findtekst(133,$sprog_id))."";
		else $tekst="".findtekst(1141,$sprog_id)." - ".lcfirst(findtekst(133,$sprog_id))."";
		print "<td width=\"80%\" $top_bund>$tekst</td>";
		($kontoantal==1)?$w=5:$w=10;
		print "<td width=\"w%\" $top_bund onClick=\"javascript:kontoprint=window.open('kontoprint.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&kontoart=$kontoart','kontoprint','left=0,top=0,width=1000%,height=700%, scrollbars=yes,resizable=yes,menubar=no,location=no');\"onMouseOver=\"this.style.cursor = 'pointer'\" title=\"Udskriv kontoudtog som PDF (Åbner i popup)\">".findtekst(880,$sprog_id)."</td>\n";
		if ($kontoantal==1) { # 2019-11-07
			if ($fromdate) $firstdate=$fromdate;
			if ($todate) $lastdate=$todate;
			print "<td width=\"$w%\" $top_bund onClick=\"javascript:kontoprint=window.open('mail_kontoudtog.php?dato_fra=".dkdato($firstdate);
			print "&dato_til=".dkdato($lastdate)."&kontoantal=1&kontoliste=$kto_id[$x]','kontomail' ,'left=0,top=0,width=1000%,height=700%,";
			print "scrollbars=yes,resizable=yes,menubar=no,location=no');\" onMouseOver=\"this.style.cursor = 'pointer'\"";
			print "title=\"Send som mail (Åbner i popup)\">Email</td>\n";
		}
		print "</tbody></table>"; //B slut
		print "</td></tr>\n";
	}

	if ($menu=='T') {

		print "<div class='sub-title-kund-radius'>".stripslashes($r['firmanavn'])." • $r[kontonr]</div>";
		print "<div class='dataTablediv'><table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\" class='dataTable'><tbody>"; //B
		print "<tr>";
		print "<td width='10%' align=right><b>Firmanavn:</b></td> <td width='70%'>".stripslashes($r['firmanavn'])."</td>";
		print "<td align=right><b>Konto nr.:</b></td>";
		print "<td align=left>$r[kontonr]</td>";
		print "</tr>";
		print "<tr>";
		print "<td width='10%' align=right><b>Adresse:</b></td> <td width='70%'> ".stripslashes($r['addr1'])."</td>";
		print "<td align=right><b>Dato:</b></td>";
		print "<td align=left>".date('d-m-Y')."</td>";
		print "</tr>";
		print "<tr>";
		print "<td width='10%' align=right><b>Adresse 2:</b></td> <td width='70%'> ".stripslashes($r['addr2'])."</td>";
		print "<td align=right><b>Valuta:</b></td>";
		print "<td align=left>$valuta</td>";
		print "</tr>";
		print "<tr>";
		print "<td width='10%' align=right><b>Postnr - By:</b></td> <td width='70%'>".stripslashes($r['postnr'])."&nbsp;".stripslashes($r['bynavn'])."</td>";
		print "<td colspan=2></td>";
		print "</tr>";
		print "<tr>";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class='dataTableNTH'><thead>";
		print "<tr><td colspan='20' class='border-hr-bottom'></td></tr>";
		print "<tr>";
		print "<th>".findtekst(635,$sprog_id)."</th>";
		print "<th>".findtekst(671,$sprog_id)."</th>";
		print "<th>".findtekst(643,$sprog_id)."</th>";
		print "<th>".findtekst(1163,$sprog_id)."</th>";
		print "<th>$prj</th>";
		print "<th>".findtekst(1164,$sprog_id)."</th>";
		print "<th align=right class='text-right'>".findtekst(1000,$sprog_id)."</th>";
		print "<th align=right class='text-right'>".findtekst(1001,$sprog_id)."</th>";
		print "<th align=right class='text-right'>".findtekst(1073,$sprog_id)."</th>";
		print "</tr></thead><tbody>";
	
		$kontosum=0;
		$primo=0;
		$pre_openpost=0;
		for ($y=1;$y<=count($oppid);$y++) {
			$diff=0;
			if ($transdate[$y]<$fromdate) {
				 $primoprint[$x]=0;
				 $kontosum+=$amount[$y];
				$dkksum+=$dkkamount[$y];
				} else {
				if ($primoprint[$x]==0) {
					$tmp=dkdecimal($kontosum,2);
					$tmp2="";
					if ($valuta!='DKK') $tmp2="&nbsp;&nbsp;&nbsp;-&nbsp;&nbsp;&nbsp;Bel&oslash;b kan v&aelig;re omregnet fra DKK";
					print "<tr><td><br></td><td><br></td><td><br></td><td>".findtekst(1165,$sprog_id)." $tmp2<br></td><td><br></td><td><br></td><td><br></td><td><br></td><td align=right title=\"DKK ".dkdecimal($dkksum,2)."\">$tmp<br></td></tr>\n";
					$primoprint[$x]=1;
				}
				if ($kladde_id[$y]) {
					$js="<a style='cursor: pointer;' onclick=\"window.open('../finans/kassekladde.php?kladde_id=$kladde_id[$y]&visipop=on')\">";
					$rt="title='Kladde ID: $kladde_id[$y]'";
				} else {
					$js=NULL;
					$rt=NULL;
				}
				print "<tr><td valign=\"top\">".dkdato($transdate[$y])."<br></td><td valign=\"top\" $rt> $js $refnr[$y] </a><br></td><td valign=\"top\">$faktnr[$y]<br></td><td valign=\"top\">".stripslashes($beskrivelse[$y])."<br></td><td valign=\"top\">$projekt[$y]</td>";
				if ($amount[$y] < 0) $tmp=0-$amount[$y];
				else $tmp=$amount[$y];
				$tmp=dkdecimal($tmp,2);
				if (!$forfaldsdag[$y]) $forfaldsdag[$y]=usdate(forfaldsdag($transdate[$y], $betalingsbet, $betalingsdage));
				if ($amount[$y]>0) {// (($kontoart=='D' && $amount>0) || ($kontoart=='K' && $amount<0)) {
				($kontoart=='D')?$ffdag=dkdato($forfaldsdag[$y]):$ffdag=NULL;
				if ($udlignet[$y]!='1') {
						$pre_openpost=1;
						print "<td valign=\"top\">$ffdag<br></td><td valign=\"top\" align=\"right\" title=\"Klik her for at udligne &aring;bne poster\"><a href=\"../includes/udlign_openpost.php?post_id=$oppid[$y]&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=".$returnpath."rapport.php\">$tmp</a><br></td><td style=\"text-align:right\">0</td>";
					} else {
						$titletag="Udlign id=$udlign_id[$y]. Klik for at ophæve udligning"; 
						$alink="rapport.php?rapportart=kontokort&kilde=openpost&kto_fra=$kto_fra&kilde_kto_til=$kto_til=&dato_fra$dato_fra=&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&submit=ok&uudlign=$udlign_id[$y]";
						$onclick="return confirm('Vil du ophæve udligningen af dette beløb samt modstående med udlign id $udlign_id[$y]')";
						print "<td valign=\"top\">$ffdag<br></td><td title=\"$titletag\" valign=\"top\" align=\"right\"><a onclick=\"$onclick\" href=\"$alink\" >$tmp<br></a></td><td style=\";text-align:right\">0</td>";
					}
					$forfaldsum=$forfaldsum+$amount[$y];
				} else {
					($kontoart=='K')?$ffdag=dkdato($forfaldsdag[$y]):$ffdag=NULL;
					if ($udlignet[$y]!='1') {
						print "<td>$ffdag<br></td><td style=\";text-align:right\">0</td><td valign=\"top\" align=right title=\"Klik her for at udligne &aring;bne poster\"><a href=\"../includes/udlign_openpost.php?post_id=$oppid[$y]&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=".$returnpath."rapport.php\">$tmp</a><br></td>";
						$pre_openpost=1;
					} else {
						$titletag="Udlign id=$udlign_id[$y]. Klik for at ophæve udligning"; 
						$alink="rapport.php?rapportart=kontokort&kilde=openpost&kto_fra=$kto_fra&kilde_kto_til=$kto_til=&dato_fra$dato_fra=&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&submit=ok&uudlign=$udlign_id[$y]";
						$onclick="return confirm('Vil du ophæve udligningen af dette beløb samt modstående med udlign id $udlign_id[$y]')";
						print "<td>$ffdag<br></td><td style=\";text-align:right\">0</td><td title=\"$titletag\" valign=\"top\" align=\"right\"><a onclick=\"$onclick\" href=\"$alink\">$tmp<br></a></td>";
					}
				}
				$kontosum+=afrund($amount[$y],2);
				$dkksum+=$dkkamount[$y];
				$dkksum=afrund($dkksum,2);
				$tmp=dkdecimal($kontosum,2);
				$dkktmp=dkdecimal($dkksum,2);
				if ($valuta!='DKK' && $kontosum!=$dkksum) $title="DKK: $dkktmp";
				else $title="";
				if ($valuta!='DKK' && !$difflink) {
					if ($r=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__))) {
						$dagskurs=$r['kurs'];
						$chkamount=$kontosum*$dagskurs/100;
						$diff=afrund($chkamount-$dkksum,2);
					}
				}
				$regulering=afrund($diff,2);
				if($regulering && !$difflink && $valuta!='DKK' && ($oppvaluta[$y]!='-' || $y==count($oppid)) && $transdate[$y]>=usdate($regnstart) && $transdate[$y]<=usdate($regnslut)) { // && $transdate>=$regnstart && $transdate<=$regnslut
					$vis_difflink=1;
					for ($i=1;$i<=count($oppid);$i++){
						if ($transdate[$i]==$transdate[$y] && $oppvaluta[$i]=='-') $vis_difflink=0;
					}
					if ($y==count($oppid) && !$kontosum) $vis_difflink=1;
						if ($oppid[$y]>=$max_valdif_id && ($vis_difflink && (abs($regulering)>0.01 || $y==count($oppid)))) {
						$difflink=1;
						if ($regnstart<=date("Y-m-d") && $regnslut>=date("Y-m-d")) {
							$title.="Klik for at regulere værdien i DKK fra ".dkdecimal($dkksum,2)." til ".dkdecimal($dkksum+$regulering,2)." pr. ".dkdato($transdate[$y]);
							$tmp2="<a href=\"../includes/ret_valutadiff.php?bfdate=$transdate[$y]&";
							$tmp2.="valuta=$valuta&diff=$regulering&post_id=$oppid[$y]&dato_fra=$dato_fra&dato_til=$dato_til&";
							$tmp2.="konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=".$returnpath."rapport.php\" ";
							$tmp2.="onclick=\"confirmSubmit($confirm)\">$tmp</a>";
							$tmp=$tmp2;
						} else $title=NULL;
					}
				} elseif ($y==count($oppid) && abs($tmp)<0.01 && abs($dkksum) > 0.01 && $regnslut>=date("Y-m-d")) {
					$title.="Klik for at regulere værdien i DKK fra ".dkdecimal($dkksum,2)." til ".dkdecimal($dkksum+$regulering,2)." pr. ".date("d-m-Y");
					$tmp2="<a href=\"../includes/ret_valutadiff.php?bfdate=".date("Y-m-d")."&";
					$tmp2.="valuta=$valuta&diff=$regulering&post_id=$oppid[$y]&dato_fra=$dato_fra&dato_til=$dato_til&";
					$tmp2.="konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=".$returnpath."rapport.php\" ";
					$tmp2.="onclick=\"confirmSubmit($confirm)\">$tmp</a>";
					$tmp=$tmp2;
				}
				print "<td valign=\"top\" align=right title=\"$title\">$tmp<br></td>";
				print "</tr>\n";
			}
		}
		if ($primoprint[$x]==0) {
			$tmp=dkdecimal($kontosum,2);
			print "<tr><td><br></td><td><br></td><td><br></td><td>Primosaldo<br></td><td><br></td><td><br></td><td><br></td><td><br></td><td align=right title=\"DKK sum $dkktmp\">$tmp<br></td></tr>\n";
		}

		print "</tbody><tfoot>";
		print "<tr><td colspan=10>";
		print "<center><input type='button' onclick=\"javascript:kontoprint=window.open('kontoprint.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&kontoart=$kontoart','kontoprint','left=0,top=0,width=1000%,height=700%, scrollbars=yes,resizable=yes,menubar=no,location=no');\"onMouseOver=\"this.style.cursor = 'pointer'\" title=\"Udskriv kontoudtog som PDF (Åbner i popup)\" accesskey='L' value='".findtekst(880,$sprog_id)."'></center>";
		print "</td></tr>";
		print "</tfoot></table></div><br>";

	} else {
			print "<tr><td colspan=9><hr></td></tr>\n";
			print "<tr><td><br></td></tr>\n";
			print "<tr><td><br></td></tr>\n";
			print "<tr><td colspan=3>".stripslashes($r['firmanavn'])."</td></tr>\n";
			print "<tr><td colspan=3>".stripslashes($r['addr1'])."</td></tr>\n";
			print "<tr><td colspan=3>".stripslashes($r['addr2'])."</td><td colspan=5 align=right>Konto nr.</td><td align=right>$r[kontonr]</td></tr>\n";
			print "<tr><td colspan=3>".stripslashes($r['postnr'])."&nbsp;".stripslashes($r['bynavn'])."</td><td colspan=5 align=right>Dato</td><td align=right>".date('d-m-Y')."</td></tr>\n";
			print "<tr><td colspan=8 align=right>Valuta</td><td align=right>$valuta</td></tr>\n";
			print "<tr><td><br></td></tr>\n";
			print "<tr><td><br></td></tr>\n";
			print "<tr><td>".findtekst(635,$sprog_id)."</td><td>".findtekst(671,$sprog_id)."</td><td>".findtekst(643,$sprog_id)."</td><td>".findtekst(1163,$sprog_id)."</td><td>$prj</td><td>".findtekst(1164,$sprog_id)."</td><td align=right>".findtekst(1000,$sprog_id)."</td><td align=right>".findtekst(1001,$sprog_id)."</td><td align=right>".findtekst(1073,$sprog_id)."</td></tr>\n";
			print "<tr><td colspan=9><hr></td></tr>\n";

			$kontosum=0;
			$primo=0;
			$pre_openpost=0;
			for ($y=1;$y<=count($oppid);$y++) {
				$diff=0;
				if ($transdate[$y]<$fromdate) {
					 $primoprint[$x]=0;
					 $kontosum+=$amount[$y];
					$dkksum+=$dkkamount[$y];
					} else {
					if ($primoprint[$x]==0) {
						$tmp=dkdecimal($kontosum,2);
						$tmp2="";
						if ($valuta!='DKK') $tmp2="&nbsp;&nbsp;&nbsp;-&nbsp;&nbsp;&nbsp;Bel&oslash;b kan v&aelig;re omregnet fra DKK";
						print "<tr><td><br></td><td><br></td><td><br></td><td>".findtekst(1165,$sprog_id)." $tmp2<br></td><td><br></td><td><br></td><td><br></td><td><br></td><td align=right title=\"DKK ".dkdecimal($dkksum,2)."\">$tmp<br></td></tr>\n";
						$primoprint[$x]=1;
					}
					if ($kladde_id[$y]) {
						$js="onclick=\"window.open('../finans/kassekladde.php?kladde_id=$kladde_id[$y]&visipop=on')\"";
						$rt="title='Kladde ID: $kladde_id[$y]'";
					} else {
						$js=NULL;
						$rt=NULL;
					}
					print "<tr bgcolor=\"$baggrund\"><td valign=\"top\">".dkdato($transdate[$y])."<br></td><td valign=\"top\" $rt $js>$refnr[$y]<br></td><td valign=\"top\">$faktnr[$y]<br></td><td valign=\"top\">".stripslashes($beskrivelse[$y])."<br></td><td valign=\"top\">$projekt[$y]</td>";
					if ($amount[$y] < 0) $tmp=0-$amount[$y];
					else $tmp=$amount[$y];
					$tmp=dkdecimal($tmp,2);
					if (!$forfaldsdag[$y]) $forfaldsdag[$y]=usdate(forfaldsdag($transdate[$y], $betalingsbet, $betalingsdage));
					if ($amount[$y]>0) {// (($kontoart=='D' && $amount>0) || ($kontoart=='K' && $amount<0)) {
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
					$tmp=dkdecimal($kontosum,2);
					$dkktmp=dkdecimal($dkksum,2);
					if ($valuta!='DKK' && $kontosum!=$dkksum) $title="DKK: $dkktmp";
					else $title="";
					if ($valuta!='DKK' && !$difflink) {
						if ($r=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' and valdate <= '$transdate[$y]' order by valdate desc",__FILE__ . " linje " . __LINE__))) {
							$dagskurs=$r['kurs'];
							$chkamount=$kontosum*$dagskurs/100;
							$diff=afrund($chkamount-$dkksum,2);
						}
					}
					$regulering=afrund($diff,2);
					if($regulering && !$difflink && $valuta!='DKK' && ($oppvaluta[$y]!='-' || $y==count($oppid)) && $transdate[$y]>=usdate($regnstart) && $transdate[$y]<=usdate($regnslut)) { // && $transdate>=$regnstart && $transdate<=$regnslut
						$vis_difflink=1;
						for ($i=1;$i<=count($oppid);$i++){
							if ($transdate[$i]==$transdate[$y] && $oppvaluta[$i]=='-') $vis_difflink=0;
						}
						if ($y==count($oppid) && !$kontosum) $vis_difflink=1;
							if ($oppid[$y]>=$max_valdif_id && ($vis_difflink && (abs($regulering)>0.01 || $y==count($oppid)))) {
							$difflink=1;
							if ($regnstart<=date("Y-m-d") && $regnslut>=date("Y-m-d")) {
								$title.="Klik for at regulere værdien i DKK fra ".dkdecimal($dkksum,2)." til ".dkdecimal($dkksum+$regulering,2)." pr. ".dkdato($transdate[$y]);
								$tmp2="<a href=\"../includes/ret_valutadiff.php?bfdate=$transdate[$y]&";
								$tmp2.="valuta=$valuta&diff=$regulering&post_id=$oppid[$y]&dato_fra=$dato_fra&dato_til=$dato_til&";
								$tmp2.="konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=".$returnpath."rapport.php\" ";
								$tmp2.="onclick=\"confirmSubmit($confirm)\">$tmp</a>";
								$tmp=$tmp2;
							} else $title=NULL;
						}
					} elseif ($y==count($oppid) && abs($tmp)<0.01 && abs($dkksum) > 0.01 && $regnslut>=date("Y-m-d")) {
						$title.="Klik for at regulere værdien i DKK fra ".dkdecimal($dkksum,2)." til ".dkdecimal($dkksum+$regulering,2)." pr. ".date("d-m-Y");
						$tmp2="<a href=\"../includes/ret_valutadiff.php?bfdate=".date("Y-m-d")."&";
						$tmp2.="valuta=$valuta&diff=$regulering&post_id=$oppid[$y]&dato_fra=$dato_fra&dato_til=$dato_til&";
						$tmp2.="konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=".$returnpath."rapport.php\" ";
						$tmp2.="onclick=\"confirmSubmit($confirm)\">$tmp</a>";
						$tmp=$tmp2;
					}
					print "<td valign=\"top\" align=right title=\"$title\">$tmp<br></td>";
					print "</tr>\n";
				}
			}
			if ($primoprint[$x]==0) {
				$tmp=dkdecimal($kontosum,2);
				print "<tr><td><br></td><td><br></td><td><br></td><td>Primosaldo<br></td><td><br></td><td><br></td><td><br></td><td><br></td><td align=right title=\"DKK sum $dkktmp\">$tmp<br></td></tr>\n";
			}
			print "<tr><td colspan=9><hr></td></tr>\n";
		}
	}
	print "</tbody></table>";

	if ($menu=='T') {

		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}
}

function kontosaldo($dato_fra,$dato_til,$konto_fra,$konto_til,$rapportart,$kontoart) {
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
		$todate=usdate($dato_fra);
	}
	if ($menu=='T') {
		print "";
	} else {
		print "<center><table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	}
	if ($menu=='T') {
		$title = "Konto Saldo";
		if ($kontoart=='K') $returnpath="../kreditor/";
		else $returnpath="../debitor/";
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\">"; 
		print "<div class=\"headerbtnLft headLink\"><a href=$returside accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst(30,$sprog_id)."</a></div>";     
		print "<div class=\"headerTxt\">$title</div>";     
		print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
		print "</div>";
		print "<div class='content-noside'>";
		print "<div class='dataTablediv'><table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\" class='dataTableNTH'>\n";
	} else {
		include("../includes/oldDesign/header.php");
		print "<tr><td colspan=\"8\" height=\"8\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; //B
		print "<td width=\"10%\" $top_bund>$luk ".findtekst(30,$sprog_id)."</a></td>";
		if ($kontoart=='K') $tekst="Kreditorrapport - kontosaldo";
		else $tekst="Debitorapport - kontosaldo";
		print "<td width=\"80%\" $top_bund>$tekst</td>";
		print "<td width=\"10%\" $top_bund><br></td>";
		print "</tbody></table>"; //B slut
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
	$kontonr=array();
	$x=0;
	$query = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$x++;
		$konto_id[$x]=$row['id'];
	}
	$kto_id=array();
	$kontoantal=$x;
	$x=0;
	for ($y=1;$y<=$kontoantal;$y++) {
		if (isset($todate)) $qtxt="select amount from openpost where transdate<='$todate' and konto_id='$konto_id[$y]'";
		else $qtxt="select amount from openpost where konto_id='$konto_id[$y]'";
		$query = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if (!in_array($konto_id[$y],$kto_id)) {
				$x++;
				$kto_id[$x]=$konto_id[$y];
			}
		}
	}
	
	$kontoantal=$x;

	if (!isset ($todate)) $todate = NULL;
	if (!isset ($totalsum)) $totalsum = NULL;
	if (!isset ($linjebg)) $linjebg = NULL;

	if ($kontoart == 'D') {
		print "<thead><tr><th>Konto nr.</th><th>Kunde</th><th align=\"right\" class='text-right'>Saldo</th></thead>";
	} else {
		print "<thead><tr><th>Konto nr.</th><th>Leverandører</th><th align=\"right\" class='text-right'>Saldo</th></thead>";
	}

	if ($menu=='T') {
		print "<tbody>";
	} else {
		print "<tr><td colspan=3><hr></td></tr>\n";
	}


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
		$q2 = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$amount=afrund($r2['amount'],2);
			$oppvaluta=$r2['valuta'];
			if (!$oppvaluta) $oppvaluta='DKK';
			$oppkurs=$r2['valutakurs']*1;
			if (!$oppkurs) $oppkurs=100;
			$dkkamount=$amount;
			if ($oppvaluta=='DKK') $belob=dkdecimal($amount,2);
			else $belob = dkdecimal($amount*100/$oppkurs,2);
			$forfaldsdag=$r2['forfaldsdate'];
			$transdate=$r2['transdate'];
			if ($oppvaluta!='DKK' && $oppkurs!=100) { //postering foert i anden valuta end Debitors som er DKK
 					$amount=$amount*$oppkurs/100;
			}
			$kontosum[$x]=$kontosum[$x]+$amount;
		}
		$totalsum=$totalsum+$kontosum[$x];
		if (afrund($kontosum[$x],2)) {
			if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			else {$linjebg=$bgcolor5; $color='#000000';}
			print "<tr bgcolor=\"$linjebg\"><td>$kontonr[$x]</td><td>$firmanavn[$x]</td>";
			$tmp=dkdecimal($kontosum[$x],2);
			print "<td align=right> $tmp</td></tr>\n";
		}
	}

	if (!isset ($totalsum)) $totalsum = NULL;

	$tmp=dkdecimal($totalsum,2);
	if ($menu=='T') {
		print "</tbody>";
	} else {
		print "<tr><td colspan=3><hr></td></tr>\n";
	}
	print "<tfoot><tr><td><b>I alt</b></td><td  colspan=\"2\" align=\"right\"><b>$tmp</b></td></tr></tfoot>\n";
	if ($menu=='T') {
		print "</table></div>";
	} else {
		print "";
	}

	if ($menu=='T') {
		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}

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
