<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/rapportfunc.php --- patch 4.0.8 --- 2024-03-30 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2024 Saldi.dk ApS
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
// 2015.10.26	indsat mulighed for at ophæve udligning. søg "unAlign"
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
// 20230522+20230616 PHR php8
// 20230620 PHR outcommented section to reduce load. Guess it is not nessecary
// 20230723 Moved some functions to reportFunc.php
// 20230824 MSC - Copy pasted new design into code
// 20240330 PHR	- Corrections in open post when fromdate != currentdate

include("../includes/reportFunc/showOpenPosts.php");
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
		print "<div style='padding:5px;height:12px;'><center><select name=\"aabenpostmode\"
		onchange=\"window.open(this.options[this.selectedIndex].value,'_top')\"></div>\n";
		if ($kun_debet=='on') print "<option>".findtekst(925,$sprog_id)."</option>\n";
		elseif ($kun_kredit=='on') print "<option>".findtekst(926,$sprog_id)."</option>\n";
		elseif ($vis_aabenpost=='on') print "<option>".findtekst(924,$sprog_id)."</option>\n";
		else print "<option>".findtekst(927,$sprog_id)."</option>\n";
		if ($vis_aabenpost!='on') print "<option value=\"rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&vis_aabenpost=on\">".findtekst(924,$sprog_id)."</option>\n"; #20210701
		if ($kun_debet!='on') print "<option value=\"rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&kun_debet=on\">".findtekst(925,$sprog_id)."</option>\n";
		if ($kun_kredit!='on') print "<option  value=\"rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&kun_kredit=on\">".findtekst(926,$sprog_id)."</option>\n";
		if ($skjul_aabenpost != 'on') print "<option  value=\"rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&skjul_aabenpost=on\">".findtekst(927,$sprog_id)."</option>\n";
		print "</select></center>\n";
		if ($menu) print "<td>\n";
		else print "</div>\n";
		print "</tr>";
	if ($menu!='T') print "</tbody></table></td></tr><!--Tabel 1.2 slut-->\n\n"; // <- Tabel 1.2
	if ($skjul_aabenpost!='on') vis_aabne_poster($dato_fra,$dato_til,$konto_fra,$konto_til,$rapportart,$kontoart,$kun_debet,$kun_kredit);

 	//-------------------------------------- Rykkeroversigt ----------------------------------------------
	if (usdate($dato_til) >= date("Y-m-d")) {
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
				print "<tr  bgcolor='$bgcolor5'>";
				print "<td width=10% align=center class='sub-title-kund-left'><br></td>";
				print "<td colspan='6' class='sub-title-kund' width=80% align=center>".findtekst(1130,$sprog_id)."</td>";
				print "<td class='sub-title-link-kund sub-title-kund' width=10% align=center>\n";
				if ($vis_aaben_rykker=='on') print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&vis_aaben_rykker=off>".findtekst(1132,$sprog_id)." ▲</a><td class='sub-title-kund-right'></td></tr>\n";
				else print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&vis_aaben_rykker=on>".findtekst(1133,$sprog_id)." ▾</a><td class='sub-title-kund-right'></td></tr></thead></table></div><br>\n";
			} elseif ($taeller==2) {
				print "<tr bgcolor = '$bgcolor5'><td width=10% align=center class='sub-title-kund-left'><br></td><td colspan='6' class='sub-title-kund' width=80% align=center>".findtekst(1135,$sprog_id)."</td><td class='sub-title-link-kund sub-title-kund' width=10% align=center>\n";
				if ($vis_inkasso=='on') print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&vis_inkasso=off>".findtekst(1132,$sprog_id)." ▲</a><td class='sub-title-kund-right'></tr>\n";
				else print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&vis_inkasso=on>".findtekst(1133,$sprog_id)." ▾</a><td class='sub-title-kund-right'></tr></thead></table></div><br>\n";
			} elseif ($taeller==3) {
				print "<tr bgcolor = '$bgcolor5'><td width=10% align=center class='sub-title-kund-left'><br></td><td colspan='6' class='sub-title-kund' width=80% align=center>".findtekst(1136,$sprog_id)."</td><td class='sub-title-link-kund sub-title-kund' width=10% align=center>\n";
				if ($vis_bogfort_rykker=='on') print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&vis_bogfort_rykker=off>".findtekst(1132,$sprog_id)." ▲</a><td class='sub-title-kund-right'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td></tr>\n";
				else print "<a href=rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&vis_bogfort_rykker=on>".findtekst(1133,$sprog_id)." ▾</a><td class='sub-title-kund-right'></td></tr></thead></table></div><br>\n";
			} else  {
				print "<tr bgcolor = '$bgcolor5'><td width=10% align=center class='sub-title-kund-left'><br></td><td colspan='6' class='sub-title-kund' width=80% align=center>".findtekst(1137,$sprog_id)."</td><td class='sub-title-link-kund sub-title-kund' width=10% align=center>\n";
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

		if ($menu == 'T') {
			include_once '../includes/topmenu/footer.php';
		} else {
			include_once '../includes/oldDesign/footer.php';
		}

	}

}}

//--------------------------------------------------------------------------------------
function bogfor_rykker($id)
{
	global $bgcolor,$bgcolor5;
	global $regnaar;
	global $fakturadate;
	global $dato_fra;
	global $dato_til;
	global $konto_fra;
	global $konto_til;


	// Bemaerk at der ikke traekkes moms ved bogfoering af rykkergebyr - heller ikke selvom gebyret tilhorer en momsbelagt varegruppe.
	// 20121106 ->
	$r = db_fetch_array(db_select("select fakturadate from ordrer where id = '$id'", __FILE__ . " linje " . __LINE__));
	$rykkerdate = $r['fakturadate'];
	$q = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'", __FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		$year = trim($r['box2']);
		$aarstart = str_replace(" ", "", $year . $r['box1']);
		$year = trim($r['box4']);
		$aarslut = str_replace(" ", "", $year . $r['box3']);
	}
	list($year, $month, $day) = explode('-', $rykkerdate);
	$year = trim($year);
	$ym = $year . $month;
	if (($ym < $aarstart || $ym > $aarslut)) {
		print "<BODY onLoad=\"javascript:alert('Rykkerdato udenfor regnskabs&aring;r')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til\">";
		exit;
	}
	// <- 20121106

	$fejl = 0;
	$sum = 0;
	$q = db_select("select antal, pris, rabat from ordrelinjer where ordre_id = '$id' and vare_id > '0'", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q))
		$sum = $sum + ($r['antal'] * $r['pris']) - ($r['antal'] * $r['pris'] / 100 * $r['rabat']);
	if ($sum)
		db_modify("update ordrer set sum=$sum where id = '$id'", __FILE__ . " linje " . __LINE__);
	$x = 0;
	$q = db_select("select id, vare_id from ordrelinjer where ordre_id = '$id' and vare_id > '0'", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$ordre_linje_id[$x] = $r['id'];
		$pris[$x] = $r['pris'];
		if ($vare_id[$x] = $r['vare_id']) {
			$q2 = db_select("select gruppe from varer where id = $vare_id[$x]", __FILE__ . " linje " . __LINE__);
			$r2 = db_fetch_array($q2);
			$gruppe[$x] = $r2['gruppe'];
			$q2 = db_select("select * from grupper where art='VG' and kodenr='$gruppe[$x]'", __FILE__ . " linje " . __LINE__);
			$r2 = db_fetch_array($q2);
			$box1[$x] = trim($r2['box1']);
			$box2[$x] = trim($r2['box2']);
			$box3[$x] = trim($r2['box3']);
			$box4[$x] = trim($r2['box4']);
			$box8[$x] = trim($r2['box8']);
			$box9[$x] = trim($r2['box9']);
			if ($rbox8[$x] != 'on') {
				db_modify("update ordrelinjer set bogf_konto=$box4[$x] where id=$ordre_linje_id[$x]", __FILE__ . " linje " . __LINE__);
				db_modify("update ordrer set status=3 where id=$id", __FILE__ . " linje " . __LINE__);
			} else {
				$fejl = 1;
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

	$d_kontrol = 0;
	$k_kontrol = 0;
	$logdate = date("Y-m-d");
	$logtime = date("H:i");
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
	$x = 0;
	$q = db_select("select * from ordrer where id='$id'", __FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		$kontoart = $r['art'];
		$konto_id = $r['konto_id'];
		$kontonr = str_replace(" ", "", $r['kontonr']);
		$firmanavn = trim($r['firmanavn']);
		$modtagelse = $r['modtagelse'];
		$transdate = ($r['fakturadate']);
		$fakturanr = $r['fakturanr'];
		$fakturadate = $r['fakturadate'];
		$ordrenr = $r['ordrenr'];
		$valutakurs = $r['valutakurs'];
		$valuta = $r['valuta'];
		$projekt = $r['projekt'] * 1;
		$refnr;
		if ($r['moms']) {
			$moms = $r['moms'];
		} else {
			$moms = afrund($r['sum'] * $r['momssats'] / 100, 2);
		}
		$sum = $r['sum'] + $moms;
		$ordreantal = $x;
		if ($r = db_fetch_array(db_select("select afd from ansatte where navn = '$r[ref]'", __FILE__ . " linje " . __LINE__)))
			$afd = $r['afd'];
		$afd = $afd * 1; //sikkerhed for at 'afd' har en vaerdi 

		$bilag = 0;
		if (!$valutakurs && $valuta != 'DKK') { //20140628
			if ($r2 = db_fetch_array(db_select("select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe = " . nr_cast("grupper.kodenr") . " and valuta.valdate <= '$fakturadate' order by valuta.valdate desc", __FILE__ . " linje " . __LINE__))) {
				$valutakurs = $r2['kurs'];
			} else {
				print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs i $valuta d. $fakturadate')\">";
				return ("Ups - ingen valutakurs i $valuta d. $fakturadate");
			}
		}

		if ($valutakurs && $valutakurs != 100)
			$sum = $sum * $valutakurs / 100; // Omregning til DKK.
		$beskrivelse = "Gebyr mm. fra tidligere rykker";

		$qtxt = "select id from openpost where konto_id = '$konto_id' and faktnr = '$fakturanr' and refnr = '$id' and amount = '$sum' ";
		$qtxt .= "and beskrivelse = '$beskrivelse' and udlignet = '0' and transdate = '$transdate' and kladde_id = '0'";
		if (db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) { #20200109
			return ($id);
			exit;
		}
		if ($sum) {
			$qtxt = "insert into openpost ";
			$qtxt .= " (konto_id, konto_nr, faktnr, refnr, amount, beskrivelse, udlignet, transdate, kladde_id,valuta,valutakurs)";
			$qtxt .= " values "; #20210422 - Addad afrund in next line
			$qtxt .= "('$konto_id', '$kontonr', '$fakturanr', '$id','" . afrund($sum, 2) . "', '$beskrivelse', '0', '$transdate', '0','DKK','100')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'", __FILE__ . " linje " . __LINE__));
		$r = db_fetch_array(db_select("select box2 from grupper where art = 'DG' and kodenr='$r[gruppe]'", __FILE__ . " linje " . __LINE__));
		$kontonr = $r['box2']; // Kontonr ændres fra at være leverandørkontonr til finanskontonr

		if ($sum > 0) {
			$debet = $sum;
			$kredit = '0';
		} else {
			$debet = '0';
			$kredit = $sum * -1;
		}
		$d_kontrol = $d_kontrol + $debet;
		$k_kontrol = $k_kontrol + $kredit;
		if ($sum)
			db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt', '$id')", __FILE__ . " linje " . __LINE__);
		$y = 0;
		$bogf_konto = array();
		$q = db_select("select * from ordrelinjer where ordre_id='$id'", __FILE__ . " linje " . __LINE__); // 20151019
		while ($r = db_fetch_array($q)) {
			if (!in_array($r['bogf_konto'], $bogf_konto)) {
				$y++;
				$bogf_konto[$y] = $r['bogf_konto'];
				$pris[$y] = $r['pris'] * $r['antal'] - afrund(($r['pris'] * $r['antal'] * $r['rabat'] / 100), 2);
			} else {
				for ($a = 1; $a <= $y; $a++) {
					if ($bogf_konto[$a] == $r['bogf_konto']) {
						$pris[$a] = $pris[$a] + ($r['pris'] * $r['antal'] - afrund(($r['pris'] * $r['antal'] * $r['rabat'] / 100), 2));
					}
				}
			}
		}
		$ordrelinjer = $y;
		for ($y = 1; $y <= $ordrelinjer; $y++) {
			if ($bogf_konto[$y]) {
				if ($pris[$y] > 0) {
					$kredit = $pris[$y];
					$debet = 0;
				} else {
					$kredit = 0;
					$debet = $pris[$y] * -1;
				}
				if ($valutakurs) {
					$kredit = $kredit * $valutakurs / 100;
					$debet = $debet * $valutakurs / 100;
				} // Omregning til DKR.
				$d_kontrol = $d_kontrol + $debet;
				$k_kontrol = $k_kontrol + $kredit;
				if ($pris[$y])
					db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', '$bogf_konto[$y]', '$fakturanr', '$debet', '$kredit', '0','$afd', '$logdate', '$logtime', '$projekt', '$id')", __FILE__ . " linje " . __LINE__);
			}
		}
		db_modify("update ordrer set status=4 where id=$id", __FILE__ . " linje " . __LINE__);
		db_modify("delete from ordrelinjer where ordre_id=$id and posnr < 0", __FILE__ . " linje " . __LINE__);
	}
	$d_kontrol = afrund($d_kontrol, 2);
	$k_kontrol = afrund($k_kontrol, 2);
	if ($d_kontrol != $k_kontrol) {
		print "<BODY onLoad=\"javascript:alert('Der er konstateret en uoverensstemmelse i posteringssummen, kontakt administrator')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=rapport.php?id=$id\">";
		exit;
	}
}

if (!function_exists('find_maaned_nr')) {
	function find_maaned_nr($maaned)
	{
		$maaned = trim($maaned);
		$aar = date("Y");
		if (is_numeric($maaned))
			return ($aar . " " . $maaned);

		if (strstr($maaned, " "))
			list($aar, $maaned) = explode(" ", $maaned);
		if ($maaned == "januar")
			$maaned = "01";
		elseif ($maaned == "februar")
			$maaned = "02";
		elseif ($maaned == "marts")
			$maaned = "03";
		elseif ($maaned == "april")
			$maaned = "04";
		elseif ($maaned == "maj")
			$maaned = "05";
		elseif ($maaned == "juni")
			$maaned = "06";
		elseif ($maaned == "juli")
			$maaned = "07";
		elseif ($maaned == "august")
			$maaned = "08";
		elseif ($maaned == "september")
			$maaned = "09";
		elseif ($maaned == "oktober")
			$maaned = "10";
		elseif ($maaned == "november")
			$maaned = "11";
		elseif ($maaned == "december")
			$maaned = "12";
		return ($aar . " " . $maaned);
	}

}



// ------------------------------------------------------------------------------------------------------------
function forside($dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $kontoart)
{
	global $bgcolor,$bgcolor5;
	global $brugernavn;
	global $bruger_id;
	global $top_bund;
	global $md;
	//	global $returside;
	global $jsvars;
	global $popup;
	global $menu;
	global $rettigheder;

	$husk = "";
	print "<script LANGUAGE=\"JavaScript\" TYPE=\"text/javascript\" SRC=\"../javascript/overlib.js\"></script>";
	($kontoart == 'D') ? $tekst = 'DRV' : $tekst = 'KRV';
	if ($r = db_fetch_array(db_select("select * from grupper where art = '$tekst' and kodenr = '$bruger_id'", __FILE__ . " linje " . __LINE__))) {
		if ($r['box1']) {
			$husk = 'checked';
			$dato_fra = $r['box2'];
			$dato_til = $r['box3'];
			$konto_fra = $r['box4'];
			$konto_til = $r['box5'];
			$rapportart = $r['box6'];
		}
	} else
		db_modify("insert into grupper (beskrivelse,kodenr,art) values ('Debitorrapportvisning','$bruger_id','$tekst')", __FILE__ . " linje " . __LINE__);
	if (!isset($sprog_id))
		$sprog_id = NULL;
	($kontoart == 'D') ? $title = findtekst(449, $sprog_id) : $title = findtekst(450, $sprog_id);
	($popup) ? $returside = "../includes/luk.php" : $returside = "../index/menu.php";

	if ($menu == 'T') {
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
		$butCol = '#009578';
		$topStyle = "border:1;border-color:#fefefe;border-radius:5px;width:100%;height:100%;background:url('../img/knap_bg.gif');";
		print "<table cellpadding='1' cellspacing='3' border='0' width='100%' height='100%' valign=\"top\"><tbody>";
		include("../includes/oldDesign/header.php");
		print "<tr><td height='23px' width ='10%' align = 'center'>
		<a href=$returside accesskey=L><button style = \"$topStyle\">" . findtekst(30, $sprog_id) . "</button></a></td>";
		print "<td width ='80%' align = 'center'><button style = \"$topStyle\">$title</button></td>";
		print "<td width ='10%' align = 'center'><button style = \"$topStyle\"></button><br></td>";
		print "</tr><tr><td height=99%><br></td></td>";
		print "<td valign='top' align='center'><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" align=\"center\"><tbody>\n";
		print "<tr><td align=center colspan=\"5\"><big><b>$title</b></big><br><br></td></tr>";
	}

	if ($menu == 'T') {
		include_once ("../includes/reportFunc/frontPageTopMenu.php");
	} else {
		include_once ("../includes/reportFunc/frontPageOldMenu.php");
	}
	if ($menu == 'T') {
		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}


}


//------------------------------------------------------------------------------------------------------------
function kontokort($dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $kontoart)
{

	//	global $connection;
	global $bgcolor, $bgcolor5, $bruger_id;
	global $md, $menu;
	global $popup;
	global $regnaar;
	global $sprog_id;
	global $top_bund;

	$title = "Kontokort";

	$email = $forfaldsum = $fromdate = $kto_fra = $kto_til = $returside = $todate = NULL;

	#cho "$_GET[unAlign]<br>";
	$unAlign = if_isset($_GET['unAlign'], NULL);
	$unAlignAccount = if_isset($_GET['unAlignAccount'], 0);
	$unAlignId = if_isset($_GET['oppId'], 0);
	#cho "UA $unAlign | $unAlignAccount<br>";
	if ($unAlign || $unAlignId) {
		$qtxt = "update openpost set udlignet='0',udlign_id='0' where konto_id = '$unAlignAccount'";
		if ($unAlign)
			$qtxt .= " and udlign_id='$unAlign'";
		elseif ($unAlignId)
			$qtxt .= " and id = '$unAlignId'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	$r = db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'", __FILE__ . " linje " . __LINE__));
	$regnstart = "01-" . trim($r['box1']) . "-" . trim($r['box2']);
	$tmp = 31;
	while (!checkdate(trim($r['box3']), $tmp, trim($r['box4']))) {
		$tmp--;
		if ($tmp < 28)
			break 1;
	}
	$regnslut = $tmp . "-" . trim($r['box3']) . "-" . trim($r['box4']);

	$difflink = 0;
	$kontoart = trim($kontoart);
	$kilde = if_isset($_GET['kilde']);
	$kilde_kto_fra = if_isset($_GET['kilde_kto_fra']);
	$kilde_kto_til = if_isset($_GET['kilde_kto_til']);

	if ($kontoart == 'K')
		$returnpath = "../kreditor/";
	else
		$returnpath = "../debitor/";

	$tmp = $konto_fra;
	($kontoart == 'D') ? $tekst = 'DRV' : $tekst = 'KRV';
	$qtxt = "select * from grupper where art = '$tekst' and kodenr = '$bruger_id'";
	if (isset($_GET['returside']))
		$returside = $_GET['returside'];
	elseif ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$dato_fra = $r['box2'];
		$dato_til = $r['box3'];
		$konto_fra = $r['box4'];
		$konto_til = $r['box5'];
		$rapportart = $r['box6'];
	}
	if ($r = db_fetch_array(db_select("select id from grupper where art = 'PRJ'", __FILE__ . " linje " . __LINE__)))
		$prj = 'Projekt';
	else
		$prj = '';

	if ($tmp && $tmp != $konto_fra && !$returside) {
		$returside = "rapport.php?rapportart=$rapportart"; //&submit=ok&regnaar=$regnaar&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til";
		$konto_fra = $tmp;
		$konto_til = $konto_fra;
	} elseif (!$returside)
		$returside = "rapport.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til";

	if ($dato_fra && $dato_til) {
		$fromdate = usdate($dato_fra);
		$todate = usdate($dato_til);
	} elseif ($dato_fra && !$dato_til) {
		$todate = usdate($dato_fra);
	}
	$kontonr = array();
	$kto_id = array();
	$x = 0;
	if (is_numeric($konto_fra) && is_numeric($konto_til)) { #changed 20210816
#		$qtxt = "select id from adresser where ".nr_cast('kontonr').">='$konto_fra' and ".nr_cast('kontonr')."<='$konto_til' and art = '$kontoart' order by ".nr_cast('kontonr')."";
		$qtxt = "select id,kontonr from adresser where art = '$kontoart' order by kontonr";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if ($konto_fra <= $r['kontonr'] && $konto_til >= $r['kontonr']) {
				$x++;
				$konto_id[$x] = $r['id'];
			}
		}
	} else {
		if ($konto_fra && $konto_fra != '*') {
			$konto_fra = str_replace("*", "%", $konto_fra);
			$tmp1 = strtolower($konto_fra);
			$tmp2 = strtoupper($konto_fra);
			$qtxt = "select id from adresser where (firmanavn like '$konto_fra' or lower(firmanavn) like '$tmp1' or ";
			$qtxt.= "upper(firmanavn) like '$tmp2') and art = '$kontoart' order by firmanavn";
		} else
			$qtxt = "select id from adresser where art = '$kontoart' order by firmanavn";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$konto_id[$x] = $r['id'];
		}
	}
	$kontoantal = $x;
	$x = 0;
	// finder alle konti med bevaegelser i den anfoerte periode eller aabne poster fra foer perioden
	if ($kontoantal == 1) { //20140505 - Fjerner udligning hvis udligningssum er skæv.
		$y = 0;
		$qtxt = "select distinct(udlign_id) from openpost where udlignet = '1' and udlign_id>'0' and konto_id='$konto_id[1]'";
		$q = db_select("$qtxt", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$udlign_id[$y] = $r['udlign_id'] * 1;
			$y++;
		}
	}

	for ($y = 1; $y <= $kontoantal; $y++) {
		#		if ($todate) $qtxt="select amount from openpost where transdate<='$todate' and konto_id='$konto_id[$y]'";
#		else $qtxt="select amount from openpost where konto_id='$konto_id[$y]'";
#		$q = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
#		while ($r = db_fetch_array($q)) {
		if (!in_array($konto_id[$y], $kto_id)) {
			$x++;
			$kto_id[$x] = $konto_id[$y];
		}
		#		}
	}
	$kontoantal = $x;
	for ($x = 1; $x <= $kontoantal; $x++) {
		$q = db_select("select * from adresser where id=$kto_id[$x]", __FILE__ . " linje " . __LINE__);
		$r = db_fetch_array($q);
		$art = trim($r['art']) . "G";
		$betalingsbet = trim($r['betalingsbet']);
		$betalingsdage = $r['betalingsdage'];

		$r2 = db_fetch_array(db_select("select box3 from grupper where art='$art' and kodenr='$r[gruppe]'", __FILE__ . " linje " . __LINE__));
		$valuta = trim($r2['box3']);
		if (!$valuta)
			$valuta = 'DKK';
		else {
			$r2 = db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta' and art='VK'", __FILE__ . " linje " . __LINE__));
			$valutakode = (!empty($r2) ? $r2['kodenr'] : 0);
		}
		$valutakode *= 1; //20140505 


		$kontosum = 0;
		$primo = 0;
		$oppId = array();
		$amount = array();
		$beskrivelse = array();
		$valutakurs = array();
		$oppvaluta = array();
		$faktnr = array();
		$forfaldsdag = array();
		$primoprint[$x] = 0;
		$baggrund = $bgcolor;
		$dkksum = 0;
		$firstdate = date("Y-m-d");
		$lastdate = '1970-01-01';

		$y = 0;
		$qtxt = "select max(id) as max_valdif_id from openpost where konto_id='$kto_id[$x]' and abs(amount) = '0.001'";
		$r2 = db_fetch_array(db_select("$qtxt", __FILE__ . " linje " . __LINE__));
		$max_valdif_id = $r2['max_valdif_id'];

		if ($todate)
			$qtxt = "select * from openpost where konto_id='$kto_id[$x]' and transdate<='$todate' order by transdate,id,faktnr,refnr"; //20160414
		else
			$qtxt = "select * from openpost where konto_id='$kto_id[$x]' order by transdate,id,faktnr,refnr"; //20160414
#cho "$qtxt<br>";
		$q2 = db_select("$qtxt", __FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$y++;
			($baggrund == $bgcolor) ? $baggrund = $bgcolor5 : $baggrund = $bgcolor;
			$oppId[$y] = $r2['id'];
			$amount[$y] = afrund($r2['amount'], 2);
			$amount[$y] = $r2['amount'];
			$beskrivelse[$y] = $r2['beskrivelse'];
			$valutakurs[$y] = $r2['valutakurs'] * 1;
			$oppvaluta[$y] = $r2['valuta'];
			$faktnr[$y] = $r2['faktnr'];
			if (!$oppvaluta[$y]) {
				$oppvaluta[$y] = 'DKK';
				$valutakurs[$y] = 100; //20140503
			}
			$forfaldsdag[$y] = $r2['forfaldsdate'];
			$kladde_id[$y] = $r2['kladde_id'];
			($r2['projekt']) ? $projekt[$y] = $r2['projekt'] : $projekt[$y] = '';
			($r2['kladde_id']) ? $refnr[$y] = $r2['refnr'] : $refnr[$y] = '';
			if (!strlen($valutakurs[$y]))
				$valutakurs[$y] = 100;
			$transdate[$y] = $r2['transdate'];
			if ($firstdate > $transdate[$y])
				$firstdate = $transdate[$y];
			if ($firstdate < $transdate[$y])
				$lastdate = $transdate[$y];
			$udlignet[$y] = $r2['udlignet'];
			$udlign_id[$y] = $r2['udlign_id'];

			if ($oppvaluta[$y] != 'DKK' && $valutakurs[$y] == 100) {
				$r3 = db_fetch_array(db_select("select kodenr from grupper where box1 = '$oppvaluta[$y]' and art='VK'", __FILE__ . " linje " . __LINE__));
				$r3 = db_fetch_array(db_select("select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc", __FILE__ . " linje " . __LINE__));
				$valutakurs[$y] = $r3['kurs'] * 1;
				$dkkamount[$y] = $amount[$y] * $valutakurs[$y] / 100;
				$beskrivelse[$y] = $r2['beskrivelse'] . " - (Omregnet fra DKK til $valuta" . dkdecimal($dkkamount[$y], 2) . ", kurs " . dkdecimal($valutakurs[$y], 2) . ")";
			} elseif ($valuta != "DKK" && $valutakurs[$y] == 100) {
				if ($r3 = db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' and valdate <= '$transdate[$y]' order by valdate desc", __FILE__ . " linje " . __LINE__))) {
					$dkkamount[$y] = $amount[$y];
					$amount[$y] = $amount[$y] * 100 / $r3['kurs'];
					$beskrivelse[$y] = $r2['beskrivelse'] . " - (Omregnet til $valuta fra DKK " . dkdecimal($dkkamount[$y], 2) . ", kurs " . dkdecimal($r3['kurs'], 2) . ")";
				} elseif ($r3 = db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' order by valdate", __FILE__ . " linje " . __LINE__))) {
					$amount[$y] = $amount[$y] * 100 / $r3['kurs'];
					$beskrivelse[$y] = $r2['beskrivelse'] . " - (Omregnet til $valuta fra DKK " . dkdecimal($dkkamount[$y], 2) . ", kurs " . dkdecimal($r3['kurs'], 2) . ")";
				}
			} elseif (($oppvaluta[$y] != 'DKK' && $valuta == "DKK" && $valutakurs[$y] != 100)) {
				$beskrivelse[$y] = $r2['beskrivelse'] . " - (Omregnet til DKK fra " . $oppvaluta[$y] . " " . dkdecimal($amount[$y], 2) . ", kurs " . dkdecimal($valutakurs[$y], 2) . ")";
				$amount[$y] = $amount[$y] * $valutakurs[$y] / 100;
			} elseif ($valuta != "DKK" && $valuta == $oppvaluta[$y] && $valutakurs[$y] != 100) {
				$valutakurs[$y] *= 1;
				if (!$valutakurs[$y] && $oppvaluta[$y] && $oppvaluta[$y] != '-') {
					$r3 = db_fetch_array(db_select("select kodenr from grupper where box1 = '$oppvaluta[$y]' and art='VK'", __FILE__ . " linje " . __LINE__));
					$r3 = db_fetch_array(db_select("select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc", __FILE__ . " linje " . __LINE__));
					$valutakurs[$y] = $r3['kurs'] * 1;
				}
				$dkkamount[$y] = $amount[$y] * $valutakurs[$y] / 100;
				if ($oppvaluta[$y] != '-' && abs($amount[$y]) >= 0.005) {
					if (!strpos($beskrivelse[$y], 'Udligning af valutadiff'))
						$beskrivelse[$y] = $r2['beskrivelse'] . " - (DKK " . dkdecimal($dkkamount[$y], 2) . ")";
				} elseif (abs($amount[$y]) < 0.005)
					$beskrivelse[$y] = $r2['beskrivelse'];
				else
					$beskrivelse[$y] = $r2['beskrivelse'] . " - (DKK " . dkdecimal($amount[$y], 2) . ")";
			} elseif ($oppvaluta[$y] != $valuta && $oppvaluta[$y] != '-') {
				if (!$valutakurs[$y]) {
					$r3 = db_fetch_array(db_select("select kodenr from grupper where box1 = '$oppvaluta[$y]' and art='VK'", __FILE__ . " linje " . __LINE__));
					$r3 = db_fetch_array(db_select("select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc", __FILE__ . " linje " . __LINE__));
					$valutakurs[$y] = $r3['kurs'] * 1;
				}
				$r3 = db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta' and art='VK'", __FILE__ . " linje " . __LINE__));
				$r3 = db_fetch_array(db_select("select kurs from valuta where gruppe ='$r3[kodenr]' and valdate <= '$transdate[$y]' order by valdate desc", __FILE__ . " linje " . __LINE__));
				$dagskurs = $r3['kurs'] * 1;
				$beskrivelse[$y] .= " $oppvaluta[$y] " . dkdecimal($amount[$y], 2) . " Kurs $valutakurs[$y]";
				$amount[$y] *= $valutakurs[$y] / $dagskurs;
				$dkkamount[$y] = $amount[$y] * $valutakurs[$y] / 100;
			} else {
				$beskrivelse[$y] = $r2['beskrivelse'];
				$dkkamount[$y] = $amount[$y]; //20160503
			}
			if ($oppvaluta[$y] == "-") {
				$dkkamount[$y] = $amount[$y];
				$amount[$y] = 0;
				$forfaldsdate[$y] = '';
			}
		}

		$luk = "<a accesskey=L href=\"$returside\">";

		if ($menu == 'T') {
			print "";
		} else {
			print "<center><table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
		}
		if ($menu == 'T' && $x == 1) {
			include_once '../includes/top_header.php';
			include_once '../includes/top_menu.php';
			print "<div id=\"header\">";
			print "<div class=\"headerbtnLft headLink\"><a href=$returside accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst(30, $sprog_id) . "</a></div>";
			print "<div class=\"headerTxt\">$title</div>";
			print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
			print "</div>";
			print "<div class='content-noside'>";
		} elseif ($x == 1) {
			include("../includes/oldDesign/header.php");
			print "<tr><td colspan=\"9\" height='30px'>";
			print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; //B
			print "<tr bgcolor = '$bgcolor5'><td width ='10%' align = 'center'>$luk " . findtekst(30, $sprog_id) . "</a></td>";
			if ($kontoart == 'K')
				$tekst = findtekst(1140, $sprog_id) . " - " . lcfirst(findtekst(133, $sprog_id));
			else
				$tekst = findtekst(1141, $sprog_id) . " - " . lcfirst(findtekst(133, $sprog_id));
			print "<td width ='80%' align = 'center'>$tekst</td>";
			($kontoantal == 1) ? $w = 5 : $w = 10;
			print "<td width=\"w%\" onClick=\"javascript:kontoprint=window.open('kontoprint.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&kontoart=$kontoart','kontoprint','left=0,top=0,width=1000%,height=700%, scrollbars=yes,resizable=yes,menubar=no,location=no');\"onMouseOver=\"this.style.cursor = 'pointer'\" title=\"Udskriv kontoudtog som PDF (Åbner i popup)\">" . findtekst(880, $sprog_id) . "</td>\n";
			if ($kontoantal == 1) { # 2019-11-07
				if ($fromdate)
					$firstdate = $fromdate;
				if ($todate)
					$lastdate = $todate;
				print "<td width=\"$w%\" onClick=\"javascript:kontoprint=window.open('mail_kontoudtog.php?dato_fra=" . dkdato($firstdate);
				print "&dato_til=" . dkdato($lastdate) . "&kontoantal=1&kontoliste=$kto_id[$x]','kontomail' ,'left=0,top=0,width=1000%,height=700%,";
				print "scrollbars=yes,resizable=yes,menubar=no,location=no');\" onMouseOver=\"this.style.cursor = 'pointer'\"";
				print "title=\"Send som mail (Åbner i popup)\">Email</td>\n";
			}
			print "</tbody></table>"; //B slut
			print "</td></tr>\n";
		}

		if ($menu == 'T') {

			print "<div class='sub-title-kund-radius'>" . stripslashes($r['firmanavn']) . " • $r[kontonr]</div>";
			print "<div class='dataTablediv'><table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\" class='dataTable'><tbody>"; //B
			print "<tr>";
			print "<td width='10%' align=right><b>Firmanavn:</b></td> <td width='70%'>" . stripslashes($r['firmanavn']) . "</td>";
			print "<td align=right><b>Konto nr.:</b></td>";
			print "<td align=left>$r[kontonr]</td>";
			print "</tr>";
			print "<tr>";
			print "<td width='10%' align=right><b>Adresse:</b></td> <td width='70%'> " . stripslashes($r['addr1']) . "</td>";
			print "<td align=right><b>Dato:</b></td>";
			print "<td align=left>" . date('d-m-Y') . "</td>";
			print "</tr>";
			print "<tr>";
			print "<td width='10%' align=right><b>Adresse 2:</b></td> <td width='70%'> " . stripslashes($r['addr2']) . "</td>";
			print "<td align=right><b>Valuta:</b></td>";
			print "<td align=left>$valuta</td>";
			print "</tr>";
			print "<tr>";
			print "<td width='10%' align=right><b>Postnr - By:</b></td> <td width='70%'>" . stripslashes($r['postnr']) . "&nbsp;" . stripslashes($r['bynavn']) . "</td>";
			print "<td colspan=2></td>";
			print "</tr>";
			print "<tr>";
			print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class='dataTableNTH'><thead>";
			print "<tr><td colspan='20' class='border-hr-bottom'></td></tr>";
			print "<tr>";
			print "<th>" . findtekst(635, $sprog_id) . "</th>";
			print "<th>" . findtekst(671, $sprog_id) . "</th>";
			print "<th>" . findtekst(643, $sprog_id) . "</th>";
			print "<th>" . findtekst(1163, $sprog_id) . "</th>";
			print "<th>$prj</th>";
			print "<th>" . findtekst(1164, $sprog_id) . "</th>";
			print "<th align=right class='text-right'>" . findtekst(1000, $sprog_id) . "</th>";
			print "<th align=right class='text-right'>" . findtekst(1001, $sprog_id) . "</th>";
			print "<th align=right class='text-right'>" . findtekst(1073, $sprog_id) . "</th>";
			print "</tr></thead><tbody>";

			$kontosum = 0;
			$primo = 0;
			$pre_openpost = 0;
			for ($y = 1; $y <= count($oppId); $y++) {
				$diff = 0;
				if ($transdate[$y] < $fromdate) {
					$primoprint[$x] = 0;
					$kontosum += $amount[$y];
					$dkksum += $dkkamount[$y];
				} else {
					if ($primoprint[$x] == 0) {
						$tmp = dkdecimal($kontosum, 2);
						$tmp2 = "";
						if ($valuta != 'DKK')
							$tmp2 = "&nbsp;&nbsp;&nbsp;-&nbsp;&nbsp;&nbsp;Bel&oslash;b kan v&aelig;re omregnet fra DKK";
						print "<tr><td><br></td><td><br></td><td><br></td><td>" . findtekst(1165, $sprog_id) . " $tmp2<br></td><td><br></td><td><br></td><td><br></td><td><br></td><td align=right title=\"DKK " . dkdecimal($dkksum, 2) . "\">$tmp<br></td></tr>\n";
						$primoprint[$x] = 1;
					}
					if ($kladde_id[$y]) {
						$js = "<a style='cursor: pointer;' onclick=\"window.open('../finans/kassekladde.php?kladde_id=$kladde_id[$y]&visipop=on')\">";
						$rt = "title='Kladde ID: $kladde_id[$y]'";
					} else {
						$js = NULL;
						$rt = NULL;
					}
					print "<tr><td valign=\"top\">" . dkdato($transdate[$y]) . "<br></td><td valign=\"top\" $rt> $js $refnr[$y] </a><br></td><td valign=\"top\">$faktnr[$y]<br></td><td valign=\"top\">" . stripslashes($beskrivelse[$y]) . "<br></td><td valign=\"top\">$projekt[$y]</td>";
					if ($amount[$y] < 0)
						$tmp = 0 - $amount[$y];
					else
						$tmp = $amount[$y];
					$tmp = dkdecimal($tmp, 2);
					if (!$forfaldsdag[$y])
						$forfaldsdag[$y] = usdate(forfaldsdag($transdate[$y], $betalingsbet, $betalingsdage));
					if ($amount[$y] > 0) { // (($kontoart=='D' && $amount>0) || ($kontoart=='K' && $amount<0)) {
						($kontoart == 'D') ? $ffdag = dkdato($forfaldsdag[$y]) : $ffdag = NULL;
						if ($udlignet[$y] != '1') {
							$pre_openpost = 1;
							print "<td valign=\"top\">$ffdag<br></td><td valign=\"top\" align=\"right\" title=\"Klik her for at udligne &aring;bne poster\"><a href=\"../includes/udlign_openpost.php?post_id=$oppId[$y]&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=" . $returnpath . "rapport.php\">$tmp</a><br></td><td style=\"text-align:right\">0</td>";
						} else {
							$titletag = "Udlign id=$udlign_id[$y]. Klik for at ophæve udligning";
							$alink = "rapport.php?rapportart=kontokort&kilde=openpost&kto_fra=$kto_fra&kilde=$kilde
						&kto_til=$kto_til&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til
						&submit=ok&unAlign=$udlign_id[$y]&oppId=$oppId[$y]&unAlignAccount=$kto_id[$x]";
							$onclick = "return confirm('Vil du ophæve udligningen af dette beløb samt modstående med udlign id $udlign_id[$y]')";
							print "<td valign=\"top\">$ffdag<br></td><td title=\"$titletag\" valign=\"top\" align=\"right\"><a onclick=\"$onclick\" href=\"$alink\" >$tmp<br></a></td><td style=\";text-align:right\">0</td>";
						}
						$forfaldsum = $forfaldsum + $amount[$y];
					} else {
						($kontoart == 'K') ? $ffdag = dkdato($forfaldsdag[$y]) : $ffdag = NULL;
						if ($udlignet[$y] != '1') {
							print "<td>$ffdag<br></td><td style=\"text-align:right;\">0</td><td valign=\"top\" align=right title=\"Klik her for at udligne &aring;bne poster\"><a href=\"../includes/udlign_openpost.php?post_id=$oppId[$y]&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=" . $returnpath . "rapport.php\">$tmp</a><br></td>";
							$pre_openpost = 1;
						} else {
							$titletag = "Udlign id=$udlign_id[$y]. Klik for at ophæve udligning";
							$alink = "rapport.php?rapportart=kontokort&kilde=openpost&kto_fra=$kto_fra&kilde=$kilde
						&kto_til=$kto_til&dato_fra=$dato_fra=&dato_til=$dato_til&konto_fra=$konto_fra
						&konto_til=$konto_til&submit=ok&unAlign=$udlign_id[$y]&oppId=$oppId[$y]&unAlignAccount=$kto_id[$x]";
							$onclick = "return confirm('Vil du ophæve udligningen af dette beløb samt modstående med udlign id $udlign_id[$y]')";
							print "<td>$ffdag<br></td><td style=\";text-align:right\">0</td><td title=\"$titletag\" valign=\"top\" align=\"right\"><a onclick=\"$onclick\" href=\"$alink\">$tmp<br></a></td>";
						}
					}
					$kontosum += afrund($amount[$y], 2);
					$dkksum += $dkkamount[$y];
					$dkksum = afrund($dkksum, 2);
					$tmp = dkdecimal($kontosum, 2);
					$dkktmp = dkdecimal($dkksum, 2);
					if ($valuta != 'DKK' && $kontosum != $dkksum)
						$title = "DKK: $dkktmp";
					else
						$title = "";
					if ($valuta != 'DKK' && !$difflink) {
						if ($r = db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' and valdate <= '$transdate[$y]' order by valdate desc", __FILE__ . " linje " . __LINE__))) {
							$dagskurs = $r['kurs'];
							$chkamount = $kontosum * $dagskurs / 100;
							$diff = afrund($chkamount - $dkksum, 2);
						}
					}
					$regulering = afrund($diff, 2);
					if ($regulering && !$difflink && $valuta != 'DKK' && ($oppvaluta[$y] != '-' || $y == count($oppId)) && $transdate[$y] >= usdate($regnstart) && $transdate[$y] <= usdate($regnslut)) { // && $transdate>=$regnstart && $transdate<=$regnslut
						$vis_difflink = 1;
						for ($i = 1; $i <= count($oppId); $i++) {
							if ($transdate[$i] == $transdate[$y] && $oppvaluta[$i] == '-')
								$vis_difflink = 0;
						}
						if ($y == count($oppId) && !$kontosum)
							$vis_difflink = 1;
						if ($oppId[$y] >= $max_valdif_id && ($vis_difflink && (abs($regulering) > 0.01 || $y == count($oppId)))) {
							$difflink = 1;
							if ($regnstart <= date("Y-m-d") && $regnslut >= date("Y-m-d")) {
								$title .= "Klik for at regulere værdien i DKK fra " . dkdecimal($dkksum, 2) . " til " . dkdecimal($dkksum + $regulering, 2) . " pr. " . dkdato($transdate[$y]);
								$tmp2 = "<a href=\"../includes/ret_valutadiff.php?bfdate=$transdate[$y]&";
								$tmp2 .= "valuta=$valuta&diff=$regulering&post_id=$oppId[$y]&dato_fra=$dato_fra&dato_til=$dato_til&";
								$tmp2 .= "konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=" . $returnpath . "rapport.php\" ";
								$tmp2 .= "onclick=\"confirmSubmit($confirm)\">$tmp</a>";
								$tmp = $tmp2;
							} else
								$title = NULL;
						}
					} elseif ($y == count($oppId) && abs($tmp) < 0.01 && abs($dkksum) > 0.01 && $regnslut >= date("Y-m-d")) {
						$title .= "Klik for at regulere værdien i DKK fra " . dkdecimal($dkksum, 2) . " til " . dkdecimal($dkksum + $regulering, 2) . " pr. " . date("d-m-Y");
						$tmp2 = "<a href=\"../includes/ret_valutadiff.php?bfdate=" . date("Y-m-d") . "&";
						$tmp2 .= "valuta=$valuta&diff=$regulering&post_id=$oppId[$y]&dato_fra=$dato_fra&dato_til=$dato_til&";
						$tmp2 .= "konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=" . $returnpath . "rapport.php\" ";
						$tmp2 .= "onclick=\"confirmSubmit($confirm)\">$tmp</a>";
						$tmp = $tmp2;
					}
					print "<td valign=\"top\" align=right title=\"$title\">$tmp<br></td>";
					print "</tr>\n";
				}
			}
			if ($primoprint[$x] == 0) {
				$tmp = dkdecimal($kontosum, 2);
				print "<tr><td><br></td><td><br></td><td><br></td><td>Primosaldo<br></td><td><br></td><td><br></td><td><br></td><td><br></td><td align=right title=\"DKK sum $dkktmp\">$tmp<br></td></tr>\n";
			}

			print "</tbody><tfoot>";
			print "<tr><td colspan=10>";
			print "<center><input type='button' onclick=\"javascript:kontoprint=window.open('kontoprint.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&kontoart=$kontoart','kontoprint','left=0,top=0,width=1000%,height=700%, scrollbars=yes,resizable=yes,menubar=no,location=no');\"onMouseOver=\"this.style.cursor = 'pointer'\" title=\"Udskriv kontoudtog som PDF (Åbner i popup)\" accesskey='L' value='" . findtekst(880, $sprog_id) . "'></center>";
			print "</td></tr>";
			print "</tfoot></table></div><br>";

		} else {
			print "<tr><td colspan=9><hr></td></tr>\n";
			print "<tr><td><br></td></tr>\n";
			print "<tr><td><br></td></tr>\n";
			print "<tr><td colspan=3>" . stripslashes($r['firmanavn']) . "</td></tr>\n";
			print "<tr><td colspan=3>" . stripslashes($r['addr1']) . "</td></tr>\n";
			print "<tr><td colspan=3>" . stripslashes($r['addr2']) . "</td><td colspan=5 align=right>Konto nr.</td><td align=right>$r[kontonr]</td></tr>\n";
			print "<tr><td colspan=3>" . stripslashes($r['postnr']) . "&nbsp;" . stripslashes($r['bynavn']) . "</td><td colspan=5 align=right>Dato</td><td align=right>" . date('d-m-Y') . "</td></tr>\n";
			print "<tr><td colspan=8 align=right>Valuta</td><td align=right>$valuta</td></tr>\n";
			print "<tr><td><br></td></tr>\n";
			print "<tr><td><br></td></tr>\n";
			print "<tr><td>" . (isset($sprog_id) ? findtekst(635, $sprog_id) : "") . "</td><td>" . (isset($sprog_id) ? findtekst(671, $sprog_id) : "") . "</td><td>" . (isset($sprog_id) ? findtekst(643, $sprog_id) : "") . "</td><td>" . (isset($sprog_id) ? findtekst(1163, $sprog_id) : "") . "</td><td>$prj</td><td>" . (isset($sprog_id) ? findtekst(1164, $sprog_id) : "") . "</td><td align=right>" . (isset($sprog_id) ? findtekst(1000, $sprog_id) : "") . "</td><td align=right>" . (isset($sprog_id) ? findtekst(1001, $sprog_id) : "") . "</td><td align=right>" . (isset($sprog_id) ? findtekst(1073, $sprog_id) : "") . "</td></tr>\n";
			print "<tr><td colspan=9><hr></td></tr>\n";

			$kontosum = 0;
			$primo = 0;
			$pre_openpost = 0;
			for ($y = 1; $y <= count($oppId); $y++) {
				$diff = 0;
				if ($transdate[$y] < $fromdate) {
					$primoprint[$x] = 0;
					$kontosum += $amount[$y];
					$dkksum += $dkkamount[$y];
				} else {
					if ($primoprint[$x] == 0) {
						$tmp = dkdecimal($kontosum, 2);
						$tmp2 = "";
						if ($valuta != 'DKK')
							$tmp2 = "&nbsp;&nbsp;&nbsp;-&nbsp;&nbsp;&nbsp;Bel&oslash;b kan v&aelig;re omregnet fra DKK";
						print "<tr><td><br></td><td><br></td><td><br></td><td>" . (isset($sprog_id) ? findtekst(1165, $sprog_id) : "") . " $tmp2<br></td><td><br></td><td><br></td><td><br></td><td><br></td><td align=right title=\"DKK " . dkdecimal($dkksum, 2) . "\">$tmp<br></td></tr>\n";
						$primoprint[$x] = 1;
					}
					if ($kladde_id[$y]) {
						$js = "onclick=\"window.open('../finans/kassekladde.php?kladde_id=$kladde_id[$y]&visipop=on')\"";
						$rt = "title='Kladde ID: $kladde_id[$y]'";
					} else {
						$js = NULL;
						$rt = NULL;
					}
					print "<tr bgcolor=\"$baggrund\"><td valign=\"top\">" . dkdato($transdate[$y]) . "<br></td><td valign=\"top\" $rt $js>$refnr[$y]<br></td><td valign=\"top\">$faktnr[$y]<br></td><td valign=\"top\">" . stripslashes($beskrivelse[$y]) . "<br></td><td valign=\"top\">$projekt[$y]</td>";
					if ($amount[$y] < 0)
						$tmp = 0 - $amount[$y];
					else
						$tmp = $amount[$y];
					$tmp = dkdecimal($tmp, 2);
					if (!$forfaldsdag[$y])
						$forfaldsdag[$y] = usdate(forfaldsdag($transdate[$y], $betalingsbet, $betalingsdage));
					if ($amount[$y] > 0) { // (($kontoart=='D' && $amount>0) || ($kontoart=='K' && $amount<0)) {
						($kontoart == 'D') ? $ffdag = dkdato($forfaldsdag[$y]) : $ffdag = NULL;
						if ($udlignet[$y] != '1') {
							$pre_openpost = 1;
							print "<td valign=\"top\"><span style='color: rgb(255, 0, 0);'>$ffdag<br></td><td  valign=\"top\" align=\"right\" title=\"Klik her for at udligne &aring;bne poster\"><span style='color: rgb(255, 0, 0);'><a href=\"../includes/udlign_openpost.php?post_id=$oppId[$y]&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=" . $returnpath . "rapport.php\">$tmp</a><br></td><td style=\"color:$baggrund;text-align:right\">0</td>";
						} else {
							$titletag = "Udlign id=$udlign_id[$y]. Klik for at ophæve udligning";
							$alink = "rapport.php?rapportart=kontokort&kilde=openpost&kto_fra=$kto_fra&kilde=$kilde
							&kto_til=$kto_til&&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra
							&konto_til=$konto_til&submit=ok&unAlign=$udlign_id[$y]&oppId=$oppId[$y]&unAlignAccount=$kto_id[$x]";
							$onclick = "return confirm('Vil du ophæve udligningen af dette beløb samt modstående med udlign id $udlign_id[$y]')";
							print "<td valign=\"top\"><span style='color: rgb(0, 0, 0);'>$ffdag<br></td><td title=\"$titletag\" valign=\"top\" align=\"right\"><span style=\"color: rgb(0, 0, 0);\"><a onclick=\"$onclick\" href=\"$alink\"style=\"text-decoration:none;\" >$tmp<br></a></span></td><td style=\"color:$baggrund;text-align:right\">0</td>";
						}
						$forfaldsum = $forfaldsum + $amount[$y];
					} else {
						($kontoart == 'K') ? $ffdag = dkdato($forfaldsdag[$y]) : $ffdag = NULL;
						if ($udlignet[$y] != '1') {
							print "<td><span style='color: rgb(255, 0, 0);'>$ffdag<br></td><td style=\"color:$baggrund;text-align:right\">0</td><td valign=\"top\" align=right title=\"Klik her for at udligne &aring;bne poster\"><span style='color: rgb(255, 0, 0);'><a href=\"../includes/udlign_openpost.php?post_id=$oppId[$y]&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=" . $returnpath . "rapport.php\">$tmp</a><br></td>";
							$pre_openpost = 1;
						} else {
							$titletag = "Udlign id=$udlign_id[$y]. Klik for at ophæve udligning";
							$alink = "rapport.php?rapportart=kontokort&kilde=openpost&kto_fra=$kto_fra&kilde=$kilde
							&kto_til=$kto_til&dato_fra=$dato_fra&&dato_til=$dato_til&konto_fra=$konto_fra
							&konto_til=$konto_til&submit=ok&unAlign=$udlign_id[$y]&oppId=$oppId[$y]&unAlignAccount=$kto_id[$x]";
							$onclick = "return confirm('Vil du ophæve udligningen af dette beløb samt modstående med udlign id $udlign_id[$y]')";
							print "<td>$ffdag<br></td><td style=\"color:$baggrund;text-align:right\">0</td><td title=\"$titletag\" valign=\"top\" align=\"right\"><span style=\"color: rgb(0, 0, 0);\"><a onclick=\"$onclick\" href=\"$alink\"style=\"text-decoration:none;\" >$tmp<br></a></span></td>";
						}
					}
					$kontosum += afrund($amount[$y], 2);
					$dkksum += $dkkamount[$y];
					$dkksum = afrund($dkksum, 2);
					$tmp = dkdecimal($kontosum, 2);
					$dkktmp = dkdecimal($dkksum, 2);
					if ($valuta != 'DKK' && $kontosum != $dkksum)
						$title = "DKK: $dkktmp";
					else
						$title = "";
					if ($valuta != 'DKK' && !$difflink) {
						if ($r = db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' and valdate <= '$transdate[$y]' order by valdate desc", __FILE__ . " linje " . __LINE__))) {
							$dagskurs = $r['kurs'];
							$chkamount = $kontosum * $dagskurs / 100;
							$diff = afrund($chkamount - $dkksum, 2);
						}
					}
					$regulering = afrund($diff, 2);
					if (
						$regulering && !$difflink && $valuta != 'DKK'
						&& ($oppvaluta[$y] != '-' || $y == count($oppId))
						&& $transdate[$y] >= usdate($regnstart)
						&& $transdate[$y] <= usdate($regnslut)
					) { // && $transdate>=$regnstart && $transdate<=$regnslut
						$vis_difflink = 1;
						for ($i = 1; $i <= count($oppId); $i++) {
							if ($transdate[$i] == $transdate[$y] && $oppvaluta[$i] == '-')
								$vis_difflink = 0;
						}
						if ($y == count($oppId) && !$kontosum)
							$vis_difflink = 1;
						if ($oppId[$y] >= $max_valdif_id && ($vis_difflink && (abs($regulering) > 0.01 || $y == count($oppId)))) {
							$difflink = 1;
							if ($regnstart <= date("Y-m-d") && $regnslut >= date("Y-m-d")) {
								$title .= "Klik for at regulere værdien i DKK fra " . dkdecimal($dkksum, 2) . " til " . dkdecimal($dkksum + $regulering, 2) . " pr. " . dkdato($transdate[$y]);
								$tmp2 = "<a href=\"../includes/ret_valutadiff.php?bfdate=$transdate[$y]&";
								$tmp2 .= "valuta=$valuta&diff=$regulering&post_id=$oppId[$y]&dato_fra=$dato_fra&dato_til=$dato_til&";
								$tmp2 .= "konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=" . $returnpath . "rapport.php\" ";
								$tmp2 .= "onclick=\"confirmSubmit($confirm)\">$tmp</a>";
								$tmp = $tmp2;
							} else
								$title = NULL;
						}
					} elseif ($y == count($oppId) && abs(intval($tmp)) < 0.01 && abs($dkksum) > 0.01 && $regnslut >= date("Y-m-d")) {
						$title .= "Klik for at regulere værdien i DKK fra " . dkdecimal($dkksum, 2) . " til " . dkdecimal($dkksum + $regulering, 2) . " pr. " . date("d-m-Y");
						$tmp2 = "<a href=\"../includes/ret_valutadiff.php?bfdate=" . date("Y-m-d") . "&";
						$tmp2 .= "valuta=$valuta&diff=$regulering&post_id=$oppId[$y]&dato_fra=$dato_fra&dato_til=$dato_til&";
						$tmp2 .= "konto_fra=$konto_fra&konto_til=$konto_til&returside=$returside&retur=" . $returnpath . "rapport.php\" ";
						$tmp2 .= "onclick=\"confirmSubmit($confirm)\">$tmp</a>";
						$tmp = $tmp2;
					}
					print "<td valign=\"top\" align=right title=\"$title\">$tmp<br></td>";
					print "</tr>\n";
				}
			}
			if ($primoprint[$x] == 0) {
				$tmp = dkdecimal($kontosum, 2);
				print "<tr><td><br></td><td><br></td><td><br></td><td>Primosaldo<br></td><td><br></td><td><br></td><td><br></td><td><br></td><td align=right title=\"DKK sum " . (isset($dkktmp) ? $dkktmp : "") . "\">$tmp<br></td></tr>\n";
			}
			print "<tr><td colspan=9><hr></td></tr>\n";
		}
	}
	print "</tbody></table>";

	if ($menu == 'T') {
		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}
}

function kontosaldo($dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $kontoart)
{
	global $bgcolor, $bgcolor5;
	global $menu, $md;
	global $popup;
	global $returside;
	global $top_bund;
	global $sprog_id;

	$kilde = if_isset($_GET['kilde']);
	$kilde_kto_fra = if_isset($_GET['kilde_kto_fra']);
	$kilde_kto_til = if_isset($_GET['kilde_kto_til']);
	if ($popup)
		$returside = "../includes/luk.php";
	elseif ($kilde == 'openpost')
		$returside = "rapport.php?rapportart=openpost&submit=ok&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$kilde_kto_fra&konto_til=$kilde_kto_til";
	else
		$returside = "rapport.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til";
	$returside = "rapport.php?dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til";
	$luk = "<a accesskey=L href=\"$returside\">";
	$currentdate = date("Y-m-d");

	if ($dato_fra && $dato_til) {
		$fromdate = usdate($dato_fra);
		$todate = usdate($dato_til);
	} elseif ($dato_fra && !$dato_til) {
		$todate = usdate($dato_fra);
	}
	if ($menu == 'T') {
		print "";
	} else {
		print "<center><table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	}
	if ($menu == 'T') {
		$title = "Konto Saldo";
		if ($kontoart == 'K')
			$returnpath = "../kreditor/";
		else
			$returnpath = "../debitor/";
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\">";
		print "<div class=\"headerbtnLft headLink\"><a href=$returside accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst(30, $sprog_id) . "</a></div>";
		print "<div class=\"headerTxt\">$title</div>";
		print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
		print "</div>";
		print "<div class='content-noside'>";
		print "<div class='dataTablediv'><table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\" class='dataTableNTH'>\n";
	} else {
		include("../includes/oldDesign/header.php");
		print "<tr><td colspan=\"8\" height='30px'>";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; //B
		print "<tr bgcolor = '$bgcolor5'><td width ='10%' align = 'center'>$luk " . findtekst(30, $sprog_id) . "</a></td>";
		if ($kontoart == 'K')
			$tekst = "Kreditorrapport - kontosaldo";
		else
			$tekst = "Debitorapport - kontosaldo";
		print "<td width ='80%' align = 'center'>$tekst</td>";
		print "<td width ='10%' align = 'center'><br></td>";
		print "</tbody></table>"; //B slut
		print "</td></tr>\n";
	}
	if (is_numeric($konto_fra) && is_numeric($konto_fra)) {
		$qtxt = "select adresser.id from adresser,openpost where adresser.kontonr >= '$konto_fra' ";
		$qtxt .= "and adresser.kontonr <= '$konto_til' and adresser.art = '$kontoart' and openpost.konto_id = adresser.id ";
		$qtxt .= "order by kontonr";
	} elseif ($konto_fra && $konto_fra != '*') {
		$konto_fra = str_replace("*", "%", $konto_fra);
		$tmp1 = strtolower($konto_fra);
		$tmp2 = strtoupper($konto_fra);
		$qtxt = "select id from adresser where (firmanavn like '$konto_fra' or lower(firmanavn) like '$tmp1' or upper(firmanavn) like '$tmp2') and art = '$kontoart' order by firmanavn";
	} else {
		$qtxt = "select openpost.konto_id as id from adresser,openpost where adresser.art = '$kontoart' ";
		$qtxt .= "and openpost.konto_id = adresser.id and openpost.udlignet != '2' group by openpost.konto_id, adresser.firmanavn ";
		$qtxt .= "order by adresser.firmanavn";
	}
	$kontonr = array();
	$x = 0;
	$q = db_select("$qtxt", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$konto_id[$x] = $r['id'];
	}
	$kto_id = array();
	$kontoantal = $x;
	$x = 0;
	for ($y = 1; $y <= $kontoantal; $y++) {
		if (isset($todate)) {
			$qtxt = "select sum(amount) as amount from openpost where transdate<='$todate' and konto_id='$konto_id[$y]'";
		} else {
			$qtxt = "select sum(amount) as amount from openpost where konto_id='$konto_id[$y]'";
		}
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($q)) {
			if (!in_array($konto_id[$y], $kto_id)) {
				$x++;
				$kto_id[$x] = $konto_id[$y];
			}
		}
	}

	$kontoantal = $x;

	if (!isset($todate))
		$todate = NULL;
	if (!isset($totalsum))
		$totalsum = NULL;
	if (!isset($linjebg))	
		$linjebg = NULL;


	if ($menu == 'T') {
		($kontoart == 'D') ? $tmp = 'Kunde' : $tmp = 'Leverandør';
		print "<thead><tr><th>Konto nr.</th><th>$tmp</th><td align=\"right\" class='text-right'>Saldo</th></tr></thead>";
		print "<tbody>";
	} else {
		($kontoart == 'D') ? $tmp = 'Kunde' : $tmp = 'Leverandør';
		print "<tr><td><b>Konto nr.</b></td><td><b>$tmp</b></td><td align=\"right\" class='text-right'><b>Saldo</b></td></tr>";
		print "<tr><td colspan=3><hr></td></tr>\n";
	}


	for ($x = 1; $x <= $kontoantal; $x++) {
		$r = db_fetch_array(db_select("select	* from adresser where id=$kto_id[$x]", __FILE__ . " linje " . __LINE__));
		$kontonr[$x] = stripslashes($r['kontonr']);
		$firmanavn[$x] = stripslashes($r['firmanavn']);
		$kontosum[$x] = 0;
		$primo[$x] = 0;
		$primoprint[$x] = 0;
		$bgcolor = '';

		if ($todate) {
			$qtxt = "select * from openpost where konto_id='$kto_id[$x]' and transdate<='$todate' order by transdate, faktnr, refnr";
		} else
			$qtxt = "select * from openpost where konto_id='$kto_id[$x]' order by transdate, faktnr, refnr";
		$q2 = db_select("$qtxt", __FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$amount = afrund($r2['amount'], 2);
			$oppvaluta = $r2['valuta'];
			if (!$oppvaluta)
				$oppvaluta = 'DKK';
			$oppkurs = $r2['valutakurs'] * 1;
			if (!$oppkurs)
				$oppkurs = 100;
			$dkkamount = $amount;
			if ($oppvaluta == 'DKK')
				$belob = dkdecimal($amount, 2);
			else
				$belob = dkdecimal($amount * 100 / $oppkurs, 2);
			$forfaldsdag = $r2['forfaldsdate'];
			$transdate = $r2['transdate'];
			if ($oppvaluta != 'DKK' && $oppkurs != 100) { //postering foert i anden valuta end Debitors som er DKK
				$amount = $amount * $oppkurs / 100;
			}
			$kontosum[$x] += afrund($amount, 2);
			#cho "$kontosum[$x]+= $amount<br>";
		}
		$totalsum = $totalsum + $kontosum[$x];
		if (afrund($kontosum[$x], 2)) {
			if ($linjebg != $bgcolor) {
				$linjebg = $bgcolor;
				$color = '#000000';
			} else {
				$linjebg = $bgcolor5;
				$color = '#000000';
			}
			print "<tr bgcolor=\"$linjebg\"><td>$kontonr[$x]</td><td>$firmanavn[$x]</td>";
			$tmp = dkdecimal($kontosum[$x], 2);
			print "<td align=right> $tmp</td></tr>\n";
		}
	}

	if (!isset($totalsum))
		$totalsum = NULL;

	$tmp = dkdecimal($totalsum, 2);
	if ($menu == 'T') {
		print "</tbody>";
	} else {
		print "<tr><td colspan=3><hr></td></tr>\n";
	}
	print "<tfoot><tr><td><b>I alt</b></td><td  colspan=\"2\" align=\"right\"><b>$tmp</b></td></tr></tfoot>\n";
	if ($menu == 'T') {
		print "</table></div>";
	} else {
		print "";
	}

	if ($menu == 'T') {
		include_once '../includes/topmenu/footer.php';
	} else {
		include_once '../includes/oldDesign/footer.php';
	}

}


function ret_openpost($konto_id)
{
	$x = 0;
	$q = db_select("select distinct(udlign_id) from openpost where konto_id='$konto_id' and udlignet='1'", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$udlign_id[$x] = $r['udlign_id'];
	}
	$antal = $x;
	for ($x = 1; $x <= $antal; $x++) {
		$min_udlign_date = "2999-12-31";
		$max_udlign_date = "1970-01-01";
		$max_transdate = "1970-01-01";

		if ($udlign_id[$x]) {
			$q = db_select("select transdate, udlign_date from openpost where udlign_id='$udlign_id[$x]'", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$transdate = $r['transdate'];
				$udlign_date = $r['udlign_date'];
				if ($transdate > $max_transdate)
					$max_transdate = $transdate;
				if ($udlign_date > $max_udlign_date)
					$max_udlign_date = $udlign_date;
				if ($udlign_date < $min_udlign_date)
					$min_udlign_date = $udlign_date;
			}
			if ($max_transdate > $max_udlign_date || $max_udlign_date > $min_udlign_date) {
				db_modify("update openpost set udlign_date = '$max_transdate' where udlign_id='$udlign_id[$x]'", __FILE__ . " linje " . __LINE__);
			}
		}

	}
}

?>
