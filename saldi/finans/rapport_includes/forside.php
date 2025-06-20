<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------ finans/rapport_includes/forside.php -------- lap 3.8.2 --- 2020-03-5 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20190820 PHR Option 'medtag lagerbevægelser' removed if stock is locked in actual year.
// 20190924 PHR Added option 'Poster uden afd". when "afdelinger" is used. $afd='0' 
// 20200305 LOE Year selected is now kept without clicking 'opdater' [Opdater] removed 

function forside($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,$simulering,$lagerbev){
	global $connection;
	global $brugernavn;
	global $top_bund;
	global $md;
	global $popup;
	global $revisor;
	global $db_encode;
	global $menu;

	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk
	$konto_fra=$konto_fra*1;
	$konto_til=$konto_til*1;
	
	($simulering)?$simulering="checked":$simulering=NULL;
	($lagerbev)?$lagerbev="checked":$lagerbev=NULL;
	if (!$regnaar) {
		$r = db_fetch_array(db_select("select regnskabsaar from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
		$regnaar = $r['regnskabsaar'];
	}
	$query = db_select("select * from grupper where art = 'RA' order by box2 desc",__FILE__ . " linje " . __LINE__);
	$x=0;
	while ($row = db_fetch_array($query)) {
		$x++;
		$regnaar_id[$x]=$row['id'];
		$regn_beskrivelse[$x]=$row['beskrivelse'];
		$start_md[$x]=$row['box1']*1;
		$start_aar[$x]=$row['box2']*1;
		$slut_md[$x]=$row['box3']*1;
		$slut_aar[$x]=$row['box4']*1;
		$regn_kode[$x]=$row['kodenr'];
		$lagerLaas[$x]=$row['box9'];
		if ($regnaar==$row['kodenr']){$aktiv=$x;}
	}
	$antal_regnaar=$x;
	
	if ($start_aar[$aktiv] != $slut_aar[$aktiv]){
		$antal_mdr=0;
		for ($x=$start_aar[$aktiv];$x<=$slut_aar[$aktiv];$x++){
			if ($x==$start_aar[$aktiv]) {
				$antal_mdr=$antal_mdr+13-$start_md[$aktiv]; #13-12=1;
		} elseif ($x==$slut_aar[$aktiv]) $antal_mdr=$antal_mdr+$slut_md[$aktiv];
			else $antal_mdr=$antal_mdr+12; #Hypotetisk
		}
	} else $antal_mdr=$slut_md[$aktiv]+1-$start_md[$aktiv]; #12+1-1=12;
	
	$query = db_select("select * from kontoplan where regnskabsaar='$regnaar' order by kontonr",__FILE__ . " linje " . __LINE__);
	$x=0;
	while ($row = db_fetch_array($query)) {
		$x++;
		$konto_id[$x]=$row['id'];
		$kontonr[$x]=$row['kontonr'];
		$konto_beskrivelse[$x]=$row['beskrivelse'];
		if ($kontonr[$x]==$konto_fra){$konto_fra=$kontonr[$x]." : ".$konto_beskrivelse[$x];}
		if ($kontonr[$x]==$konto_til){$konto_til=$kontonr[$x]." : ".$konto_beskrivelse[$x];}
	}
	$antal_konti=$x;
	if (!$maaned_fra){$maaned_fra=$md[$start_md[$aktiv]];}
	if (!$maaned_til){$maaned_til=$md[$slut_md[$aktiv]];}
	if ($rapportart=='balance'||$rapportart=='regnskab'||!$konto_fra){$konto_fra=$kontonr[1]." : ".$konto_beskrivelse[1];}
	if (($rapportart=='resultat'||$rapportart=='budget')||!$konto_til){$konto_til=$kontonr[$antal_konti]." : ".$konto_beskrivelse[$antal_konti];}

	$query = db_select("select * from grupper where art='AFD' order by kodenr",__FILE__ . " linje " . __LINE__);
	$x=0;
	while ($row = db_fetch_array($query)) {
		$x++;
		$afdeling[$x]=$row['kodenr'];
		$afd_navn[$x]=$row['beskrivelse'];
#		if ($afd == $afdeling[$x]) {$afd = $afdeling[$x]." : ".$afd_navn[$x];}
	}
	$antal_afd=$x;

	$q = db_select("select * from grupper where art='PRJ' order by kodenr",__FILE__ . " linje " . __LINE__);
	$x=0;
	while ($r = db_fetch_array($q)) {
		if ($r['kodenr']=='0') $prj_cfg=$r['box1']; 
		else {
			$x++;
			$projektnr[$x]=$r['kodenr'];
			$prj_navn[$x]=$r['beskrivelse'];
			if ($projekt_fra == $projektnr[$x] && $projektnr[$x]) $prj_fra = $projektnr[$x]." : ".$prj_navn[$x];
			if ($projekt_til == $projektnr[$x] && $projektnr[$x]) $prj_til = $projektnr[$x]." : ".$prj_navn[$x];
		}
	}
	$antal_prj=$x;
	
	if ($r=db_fetch_array(db_select("select id from adresser where art='S'",__FILE__ . " linje " . __LINE__))) {
		$q= db_select("select * from ansatte where konto_id='$r[id]' order by initialer, navn",__FILE__ . " linje " . __LINE__);
		$x=0;
		while ($r = db_fetch_array($q)) {
			$x++;
			$ansat_id[$x]=$r['id'];
			$ansat_navn[$x]=$r['navn'];
			$ansat_init[$x]=$r['initialer'];
			if ($ansat_fra == $ansat_id[$x]) $ansat_fra = $ansat_init[$x]." : ".$ansat_navn[$x];
			if ($ansat_til == $ansat_id[$x]) $ansat_til = $ansat_init[$x]." : ".$ansat_navn[$x];
		}
		$antal_ansatte=$x;
	} else $antal_ansatte=0;
	if ($menu=='T') {
		include("../includes/top_header.php");
		include("../includes/top_menu.php");
		print "<div id=\"header\"> 
		<div class=\"headerbtnLft\"></div>
		<span class=\"headerTxt\"></span>";     
		print "<div class=\"headerbtnRght\"></div>";       
		print "</div><!-- end of header -->
			<div class=\"maincontentLargeHolder\">\n";
	} elseif ($menu=='S') {
		include("../includes/sidemenu.php");
	} else {
		print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; #A
		print "<tr>";
#	print "<table width=\"100%\" align=\"center\" border=\"10\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund>";
		if ($popup) print "<a href=../includes/luk.php accesskey=L>Luk</a></td>";
		else print "<a href=../index/menu.php accesskey=L>Luk</a></td>";
		print "<td width=\"80%\" $top_bund> Finansrapport - forside </td>";
		print "<td width=\"10%\" $top_bund><br></td>";
	}
#	print "</tbody></table>"; #B slut
	print "</tr><tr><td height=99%></td><td align='center' valign='top'>\n\n";
	print "<form name='regnskabsaar' action='rapport.php' method='post'>\n";
	if ($menu=='T') {
		print "<table class='dataTable2' cellpadding=\"1\" cellspacing=\"5\" border=\"0\" align=\"center\"><tbody>\n"; #C
	} else {
		print "<table cellpadding=\"1\" cellspacing=\"5\" border=\"1\" align=\"center\"><tbody>\n"; #C
	}
	print "<tr><td align=center><h3>Finansrapport</font><br></h3></td></tr>\n";
	print "<td><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width='100%' align=center><tbody>\n"; #D
	print "<tr><td>Regnskabs&aring;r</td><td><select name='regnaar'>\n";
	print "<option>$regnaar - $regn_beskrivelse[$aktiv]</option>\n";
	for ($x=1; $x<=$antal_regnaar;$x++) {
		if ($x!=$aktiv) {print "<option>$regn_kode[$x] - $regn_beskrivelse[$x]</option>\n";}
	}
	/* 20200305 ->	
	print "</select></td><td><input class='button gray small' type=submit value=\"Opdat&eacute;r\" name=\"submit\"></td></tr>\n";
	print "</form>\n\n";
	print "<form name=rapport action=rapport.php method=post>\n"; */
	if ($r=db_fetch_array(db_select("select id from kladdeliste where bogfort='S'",__FILE__ . " linje " . __LINE__))) {
		print "<tr><td title=\"Medtag simulerede kladder i rapporter\">Simulering</td><td title=\"Medtag simulerede kladder i rapporter\"><input class='checkmark' type=\"checkbox\" name=\"simulering\" $simulering></td></tr>";
	}
	print "</tr><td>Rapporttype</td><td><select name=rapportart>\n";
	if ($rapportart=="kontokort") print "<option title=\"".findtekst(509,$sprog_id)."\" value=\"kontokort\">".findtekst(515,$sprog_id)."</option>\n";
	elseif ($rapportart=="kontokort_moms") print "<option title=\"".findtekst(510,$sprog_id)."\" value=\"kontokort_moms\">".findtekst(516,$sprog_id)."</option>\n";
	elseif ($rapportart=="balance") print "<option title=\"".findtekst(511,$sprog_id)."\" value=\"balance\">".findtekst(517,$sprog_id)."</option>\n";
	elseif ($rapportart=="resultat") print "<option title=\"".findtekst(512,$sprog_id)."\" value=\"resultat\">".findtekst(518,$sprog_id)."</option>\n";
	if ($rapportart=="regnskab") print "<option title=\"".findtekst(850,$sprog_id)."\" value=\"regnskab\">".findtekst(849,$sprog_id)."</option>\n";
	elseif ($rapportart=="budget") print "<option title=\"".findtekst(513,$sprog_id)."\" value=\"budget\">".findtekst(519,$sprog_id)."</option>\n";
	elseif ($rapportart=="lastYear") print "<option title=\"".findtekst(871,$sprog_id)."\" value=\"lastYear\">".findtekst(872,$sprog_id)."</option>\n";
	elseif ($rapportart=="momsangivelse") print "<option title=\"".findtekst(514,$sprog_id)."\" value=\"momsangivelse\">".findtekst(520,$sprog_id)."</option>\n";
#	elseif ($rapportart=="momskontrol") print "<option title=\"".findtekst(514,$sprog_id)."\" value=\"momskontrol\">momskontrol</option>\n";
	listeangivelser($regnaar, $rapportart, "matcher");
	if ($rapportart!="kontokort") print "<option title=\"".findtekst(509,$sprog_id)."\" value=\"kontokort\">".findtekst(515,$sprog_id)."</option>\n";
	if ($rapportart!="kontokort_moms") print "><option title=\"".findtekst(510,$sprog_id)."\" value=\"kontokort_moms\">".findtekst(516,$sprog_id)."</option>\n";
	if ($rapportart!="balance") print "<option title=\"".findtekst(511,$sprog_id)."\" value=\"balance\">".findtekst(517,$sprog_id)."</option>\n";
	if ($rapportart!="resultat") print "<option title=\"".findtekst(512,$sprog_id)."\" value=\"resultat\">".findtekst(518,$sprog_id)."</option>\n";
	if ($rapportart!="regnskab") print "<option title=\"".findtekst(850,$sprog_id)."\" value=\"regnskab\">".findtekst(849,$sprog_id)."</option>\n";
	if ($rapportart!="budget") print "<option title=\"".findtekst(513,$sprog_id)."\" value=\"budget\">".findtekst(519,$sprog_id)."</option>\n";
	if ($rapportart!="lastYear" && $regnaar!= 1) print "<option title=\"".findtekst(871,$sprog_id)."\" value=\"lastYear\">".findtekst(872,$sprog_id)."</option>\n";
	if ($rapportart!="momsangivelse") print "<option title=\"".findtekst(514,$sprog_id)."\" value=\"momsangivelse\">".findtekst(520,$sprog_id)."</option>\n";
#	if ($rapportart!="momskontrol") print "<option title=\"".findtekst(514,$sprog_id)."\" value=\"momskontrol\">momskontrol</option>\n";
	listeangivelser($regnaar, $rapportart, "alle andre");

	print "</select></td>\n";
	if ($lagerLaas[$aktiv]) {
		print "<td><input type=\"hidden\" name=\"lagerbev\" value=''></td></tr>";
	} else {
	print "<td>Medtag lagerbevægelser&nbsp;";
	print "<input class='checkmark' type=\"checkbox\" name=\"lagerbev\" $lagerbev></td>";  
	print "</tr>\n";
	}
	if ($antal_afd) {
		$afdeling[0] = '0';
		$afd_navn[0] = 'Kun poster uden afd.';
		print "<tr><td> Afdeling</td><td><select name=afd>\n";
		if ($afd || $afd=='0') { 
			for ($x=0; $x<=$antal_afd; $x++) {
				if ($afd == $afdeling[$x]) print "<option>$afdeling[$x] : $afd_navn[$x]</option>\n";
			}
		}
		print "<option></option>\n";
		for ($x=0; $x<=$antal_afd; $x++) {
			 if ($afd != $afdeling[$x]) print "<option>$afdeling[$x] : $afd_navn[$x]</option>\n";
		}
		print "</select></td></tr>";
	}
	if (!isset ($prj_cfg)) $prj_cfg = 0;
	if ($antal_prj) {
		($projekt_til && $projekt_fra != $projekt_til)?$tmpprj='':$tmpprj=$projekt_fra;
		print "<tr><td>Projekt</td>";
		if (strpos($prj_cfg,'|')) {
			$prcfg=explode("|",$prj_cfg);
			$cols=count($prcfg);
			$pos=0;
			print "<td>";
			for($y=0;$y<$cols;$y++) {
				$width=$prcfg[$y]*10;
				$width=$width."px";
				print "<input class=\"inputbox\" type=\"text\" name=\"delprojekt[$y]\" style=\"width:$width\" value=\"".mb_substr($tmpprj,$pos,$prcfg[$y],$db_encode)."\">";
				$pos+=$prcfg[$y];
			}
			print "<input type=\"hidden\" name=\"prj_cfg\" value=\"$prj_cfg\">";
			print "</td></tr><tr><td></td>";
#			print "<td><input type=\"text\"> - </td><td><input type=\"text\"></td>";
		} 
		if (!strstr($projekt_fra,'?')) {
			print "<td><select name=projekt_fra>\n";
			print "<option value=\"$projekt_fra\">$projekt_fra</option>\n";
			if ($projekt_fra) print "<option></option>\n";
			for ($x=1; $x<=$antal_prj; $x++) {
				if ($projekt_fra != $projektnr[$x]) print "<option value=\"$projektnr[$x]\">$projektnr[$x] : $prj_navn[$x]</option>\n";
			}
			print "</select> -</td>";
			print "<td><select name=projekt_til>\n";
			print "<option value=\"$projekt_til\">$projekt_til</option>\n";
			if ($projekt_til) {print "<option></option>\n";}
			for ($x=1; $x<=$antal_prj; $x++) {
			 if ($projekt_til != $projektnr[$x]) print "<option value=\"$projektnr[$x]\">$projektnr[$x] : $prj_navn[$x]</option>\n";
			}
			print "</select></td></tr>";
		}
#		print "</tr>";
	}
	if ($antal_ansatte) {
		print "<tr><td>  Ansat</td><td colspan=\"2\"><select name=ansat_fra>\n";
		print "<option>$ansat_fra</option>\n";
		if ($ansat_fra) {print "<option></option>\n";}
		for ($x=1; $x<=$antal_ansatte; $x++) {
			if ($ansat_fra != $ansat_id[$x]) {print "<option>$ansat_init[$x] : $ansat_navn[$x]</option>\n";}
		}
		print "</select>";
		print " (evt. til  <select name=ansat_til>\n";
		print "<option>$ansat_til</option>\n";
		if ($ansat_fra && $ansat_til) {print "<option></option>\n";}
		for ($x=1; $x<=$antal_ansatte; $x++) {
			if ($ansat_til != $ansat_id[$x]) {print "<option>$ansat_init[$x] : $ansat_navn[$x]</option>\n";}
		}
		print "</select>)</td></tr>";
		for ($x=1; $x<=$antal_ansatte; $x++) {
			print "<input type = hidden name = ansat_id[$x] value = \"$ansat_id[$x]\">";
			print "<input type = hidden name = ansat_init[$x] value = \"$ansat_init[$x]\">";
		}
	}
	print "<input type = hidden name = antal_ansatte value = $antal_ansatte>";
	print "<tr><td>  Periode</td><td colspan=2>Fra <select name=maaned_fra>\n";
	if (!$aar_fra) $aar_fra=$start_aar[$aktiv];
	print "<option value='$aar_fra $maaned_fra'>$aar_fra $maaned_fra</option>\n";
	$x=$start_md[$aktiv]-1;
	$z=$start_aar[$aktiv];
	for ($y=1; $y <= $antal_mdr; $y++) {
		if ($x>=12) { 
			$z++;
			$x=1;
		} else $x++;
		print "<option value='$z $md[$x]'>$z $md[$x]</option>\n";
	}
#	if (($start_md[$aktiv]>1)&&($slut_md[$aktiv]<12)) {
#		for ($x=1; $x<=$slut_md[$aktiv]; $x++) print "<option>$slut_aar[$aktiv] $md[$x]</option>\n";
#	}
	print "</select>";
	if (!$dato_fra) $dato_fra=1;
	print "<select name=dato_fra>\n";
	print "<option value=\"$dato_fra\">$dato_fra</option>\n";
	for ($x=1; $x <= 31; $x++) print "<option value=\"$x\">$x.</option>\n";
	print "</select>";
	print "&nbsp;til&nbsp;";
	print "<select name=maaned_til>\n";
	if (!$aar_til) $aar_til=$slut_aar[$aktiv];
	print "<option value='$aar_til $maaned_til'>$aar_til $maaned_til</option>\n";
	$x=$start_md[$aktiv]-1;
	$z=$start_aar[$aktiv];
	for ($y=1; $y <= $antal_mdr; $y++) {
		if ($x>=12) { 
			$z++;
			$x=1;
		} else $x++;
		$md[$x]=trim($md[$x]);
		print "<option $z $md[$x]>$z $md[$x]</option>\n";
	}
#	for ($x=$start_md[$aktiv]; $x <= 12; $x++) print "<option>$start_aar[$aktiv] $md[$x]</option>\n";
#	if (($start_md[$aktiv]>1)&&($slut_md[$aktiv]<12)) {
#		for ($x=1; $x<=$slut_md[$aktiv]; $x++) print "<option>$slut_aar[$aktiv] $md[$x]</option>\n";
#	}
	print "</select>";
	if (!$dato_til) $dato_til=31;
	print "<select name=dato_til>\n";
	print "<option value=\"$dato_til\">$dato_til</option>\n";
	for ($x=1; $x <= 31; $x++) print "<option value=\"$x\">$x.</option>\n";
	print "</select>";
	print "</td></tr>\n";
	print "<tr><td>  Konto (fra)</td><td colspan=2><select name=konto_fra>\n";
	print "<option>$konto_fra</option>\n";
	for ($x=1; $x<=$antal_konti; $x++) print "<option>$kontonr[$x] : $konto_beskrivelse[$x]</option>\n";
	print "</td>";
#	print "<td><input type=\"tekst\" name=\"$konto_fra2\" value=\"$konto_fra2\"></td>";
	print "</tr>\n";
	print "<tr><td>  Konto (til)</td><td colspan=2><select name=konto_til>\n";
	print "<option>$konto_til</option>\n";
	for ($x=1; $x<=$antal_konti; $x++) print "<option>$kontonr[$x] : $konto_beskrivelse[$x]</option>\n";
	print "</td></tr>\n";
#	print "<input type=hidden name=regnaar value=$regnaar>\n"; #20200305
	print "<tr><td colspan=3 align=center><input class='button green medium' type=submit value=\" OK \" name=\"submit\"></td></tr>\n";
	print "</tbody></table>\n"; #D
	print "</td></tr><tr>";
	print "<td colspan=3 ALIGN=center><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>\n"; #E
	if ($popup) {
		print "<tr><td colspan=3 ALIGN=center onClick=\"javascript:kontrolspor=window.open('kontrolspor.php','kontrolspor','scrollbars=1,resizable=1');kontrolspor.focus();\"><span title='Vilk&aring;rlig s&oslash;gning i transaktioner'><input class='button orange medium' type=submit value=\"Kontrolspor\" name=\"submit\"></span></td></tr>";
		print "<tr><td colspan=3 ALIGN=center onClick=\"javascript:provisionsrapport=window.open('provisionsrapport.php','provisionsrapport','scrollbars=1,resizable=1');provisionsrapport.focus();\"><span title='Rapport over medarbejdernes provisionsindtjening'><input class='button blue medium' type=submit value=\"Provisionsrapport\" name=\"submit\"></span></td></tr>";
	} else {
		print "<tr><td colspan=3 ALIGN=center><span title='Vilk&aring;rlig s&oslash;gning i transaktioner'><input class='button orange medium' type=submit value=\"Kontrolspor\" name=\"submit\"></span></td></tr>";
		print "<tr><td colspan=3 ALIGN=center><span title='Rapport over medarbejdernes provisionsindtjening'>  <input class='button blue medium' type=submit value=\"Provisionsrapport\" name=\"submit\"></span></td></tr>";
	} 
	print "</form>\n";
	print "</tbody></table>\n"; #E
	print "</td></tr>";
	print "</tbody></table>\n"; #C slut
	print "</td></tr>";
	print "</tbody></table>\n"; #C slut


}
# endfunc forside
?>
