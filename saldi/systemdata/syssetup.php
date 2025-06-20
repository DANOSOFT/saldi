<?php
// -----------------------------------------------------------systemdata/syssetup.php----patch 1.0.8------
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
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------
@session_start();
$s_id=session_id();
	
$modulnr=1;
$title="Systemsetup";
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/usdecimal.php");
include("../includes/dkdecimal.php");
#		include("../includes/db_query.php");
include("top.php");
	
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";

$valg=$_GET['valg'];

if ($_POST){ 
	$id=$_POST['id'];
	$beskrivelse=$_POST['beskrivelse'];
	$kodenr=$_POST['kodenr'];
	$kode=$_POST['kode'];
	$art=$_POST['art'];
	$box1=$_POST['box1'];
	$box2=$_POST['box2'];
	$box3=$_POST['box3'];
	$box4=$_POST['box4'];
	$box5=$_POST['box5'];
	$box6=$_POST['box6'];
	$box7=$_POST['box7'];
	$box8=$_POST['box8'];
	$box9=$_POST['box9'];
	$antal=$_POST['antal'];
	$valg=$_POST['valg'];
	
	$s_art=array();
	$artantal=0;
	transaktion('begin');
	for($x=0; $x<=$antal; $x++) {
		########## Til brug for sortering ########
		 if (($art[$x])&&(!in_array($art[$x],$s_art))) {
				$artantal++;
				$s_art[$artantal]=$art[$x];
				$s_kode[$artantal]=$kode[$x];
			}
			################################
			$beskrivelse[$x]=addslashes(trim($beskrivelse[$x]));
			$kodenr[$x]=trim($kodenr[$x]);
			$box1[$x]=trim($box1[$x]);
			$box2[$x]=trim($box2[$x]);
			$box3[$x]=trim($box3[$x]);
			$box4[$x]=trim($box4[$x]);
			$box5[$x]=trim($box5[$x]);
			$box6[$x]=trim($box6[$x]);
			$box7[$x]=trim($box7[$x]);
			$box8[$x]=trim($box8[$x]);
			$box9[$x]=trim($box9[$x]);
			if (($art[$x]=='VG')&&($box8[$x]!='on')) $box9='';
			if (($art[$x]=='SM')||($art[$x]=='KM')||($art[$x]=='EM')){$box2[$x]=usdecimal($box2[$x]);} 
			if (($kode[$x])||($id[$x])) {
				tjek ($id [$x], $beskrivelse[$x], $kodenr[$x], $kode[$x], $art[$x], $box1[$x],$box2[$x], $box3[$x], $box4[$x],$box5[$x], $box6[$x],$box7[$x], $box8[$x], $box9[$x]);
				if (($id[$x]==0)&&($kode[$x])&&($kodenr[$x])&&($art[$x])) {
					$query = db_SELECT("SELECT id FROM grupper WHERE kodenr = '$kodenr[$x]' and kode = '$kode[$x]' and art = '$art[$x]'");
					if ($row = db_fetch_array($query)) {
						if ($art[$x]=='SM'){print "<big><b>Der findes allerede en salgsmomskonto med nr: $kodenr[$x]</b></big><br>"; $nopdat=1;}
						if ($art[$x]=='KM'){print "<big><b>Der findes allerede en k&oslash;bssmomskonto med nr: $kodenr[$x]</b></big><br>"; $nopdat=1;}
						if ($art[$x]=='EM'){print "<big><b>Der findes allerede en erhvervelsesmomskonto med nr: $kodenr[$x]</b></big><br>"; $nopdat=1;}
						if ($art[$x]=='SD'){print "<big><b>Der findes allerede en debitor-samlekonto nr: $kodenr[$x]</b></big><br>"; $nopdat=1;}
						if ($art[$x]=='KD'){print "<big><b>Der findes allerede en kreditor-samlekonto nr: $kodenr[$x]</b></big><br>"; $nopdat=1;}
					}
					elseif ($art[$x]=='RA'){nytaar($beskrivelse[$x], $kodenr[$x], $kode[$x], $art[$x], $box1[$x],$box2[$x], $box3[$x], $box4[$x],$box5[$x], $box6[$x]);}
					elseif ($art[$x]!='PV') {
						db_modify("insert into grupper (beskrivelse, kodenr, kode, art, box1, box2, box3, box4, box5, box6, box7, box8, box9) values ('$beskrivelse[$x]', '$kodenr[$x]', '$kode[$x]', '$art[$x]', '$box1[$x]','$box2[$x]', '$box3[$x]', '$box4[$x]','$box5[$x]', '$box6[$x]','$box7[$x]', '$box8[$x]', '$box9[$x]')");
						if ($art[$x]=='LG'){
							if (!db_fetch_array(db_SELECT("SELECT * FROM lagerstatus"))) {
								$q1=db_select("SELECT id, beholdning FROM varer WHERE beholdning !='0' order by id");
								while ($r1=db_fetch_array($q1)) {
									db_modify("insert into lagerstatus (beholdning, vare_id, lager) values ('$r1[beholdning]', '$r1[id]', '0')"); 
								}
							}
						}
					}
				}
				elseif ((($id[$x]>0)&&($kodenr[$x])&&($kodenr[$x]!='-'))&&($art[$x])){ # &&(($box1[$x])||($box3[$x])||($art[$x]=='VK')))
					if ($art[$x]=='PV') {db_modify("update grupper set box1 = '$box1[$x]', box2 = '$box2[$x]', box3 = '$box3[$x]' WHERE id = '$id[$x]'");}
					else {
						db_modify("update grupper set beskrivelse = '$beskrivelse[$x]', kode = '$kode[$x]', box1 = '$box1[$x]', box2 = '$box2[$x]', box3 = '$box3[$x]', box4 = '$box4[$x]', box5 = '$box5[$x]', box6 = '$box6[$x]', box7 = '$box7[$x]', box8 = '$box8[$x]', box9 = '$box9[$x]' WHERE id = '$id[$x]'");
					}
				}
				elseif (($id[$x]>0)&&($kodenr[$x]=="-")&&($art[$x]!='PV')) {
					if ($art[$x]=='LG') {
						$r1=db_fetch_array(db_SELECT("SELECT kodenr FROM grupper WHERE id=$id[$x]"));
						$q2=db_select("SELECT beholdning, vare_id FROM lagerstatus WHERE lager =  '$r1[kodenr]'");
						while ($r2=db_fetch_array($q2)) {
							if ($r3=db_fetch_array(db_SELECT("SELECT * FROM lagerstatus WHERE lager = '0' and vare_id = '$r2[vare_id]'"))) {
								db_modify("update lagerstatus set beholdning = $r3[beholdning]+$r2[beholdning] WHERE id = $r3[id]");
							} else {
							db_modify("insert into lagerstatus (beholdning, vare_id, lager) values ('$r2[beholdning]', '$r2[vare_id]', '0')"); 
							}
						} 
						db_modify("delete FROM lagerstatus WHERE lager = '$r1[kodenr]'");
							db_modify("update batch_kob set lager = 0 WHERE lager =  '$r1[kodenr]'");
						db_modify("delete FROM grupper WHERE id = '$id[$x]'");
						$q1=db_SELECT("SELECT kodenr FROM grupper WHERE art='LG' and kodenr > '$r1[kodenr]' order by kodenr");
						while ($r1=db_fetch_array($q1)) {
							db_modify("update lagerstatus set lager = $r1[kodenr]-1 WHERE lager = '$r1[kodenr]'");
							db_modify("update batch_kob set lager = $r1[kodenr]-1 WHERE lager =  '$r1[kodenr]'");
						}	
						if (!db_fetch_array(db_SELECT("SELECT kodenr FROM grupper WHERE art='LG'"))) db_modify("delete FROM lagerstatus");	
					}
					else 	db_modify("delete FROM grupper WHERE id = '$id[$x]'");
				}
			}
		}
/*		############### Sorterring paa posnr" og fjernelse af tomrum##########################
		for ($x=1; $x<=$artantal; $x++){		
			$y=0;
			$query = db_SELECT("SELECT id FROM grupper WHERE kode = '$s_kode[$x]' and art = '$s_art[$x]' order by kodenr");
			while ($row = db_fetch_array($query)) {
				$y++;
				db_modify("update grupper set kodenr = '$y' WHERE id = $row[id]");
			}	
		}
*/		########################################################################
	transaktion('commit');
	}
	if ($nopdat!=1){
		$x=0;
		$query = db_SELECT("SELECT * FROM grupper order by kodenr");
		while ($row = db_fetch_array($query)){
			$x++;
			$id[$x]=$row['id'];
			$beskrivelse[$x]=htmlentities(stripslashes($row['beskrivelse']));
			$kodenr[$x]=$row['kodenr'];
			$kode[$x]=$row['kode'];
			$art[$x]=$row['art'];
			$box1[$x]=$row['box1'];
			$box2[$x]=$row['box2'];
			$box3[$x]=$row['box3'];
			$box4[$x]=$row['box4'];
			$box5[$x]=$row['box5'];
			$box6[$x]=$row['box6'];
			$box7[$x]=$row['box7'];
			$box8[$x]=$row['box8'];
			$box9[$x]=$row['box9'];
		}
	}
	if (!$valg) {$valg='moms';}

	$y=$x+1;
	print "<tr><td valign = top><table><tbody>";
	print "<form name=syssetup action=syssetup.php method=post>";


	if ($valg=='moms'){
		print "<tr><td></td><td colspan=3>$font<small><b>Salgsmoms</td></tr>\n";
		print "<tr><td></td><td>$font<small>Nr</td><td align=center>$font<small>Beskrivelse</td><td align=center>$font<small>Konto</td><td align=center>$font<small>Sats</td></tr>\n";		
		$y=udskriv('SM', $x, $y, $art, $id, 'S', $kodenr, $beskrivelse, $box1, '6' , $box2, '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6');
		print "<tr><td><br></td></tr>\n";
		print "<tr><td></td><td colspan=3>$font<small><b>k&oslash;bsmoms</td></tr>\n";
		print "<tr><td></td><td>$font<small>Nr</td><td align=center>$font<small>Beskrivelse</td><td align=center>$font<small>Konto</td><td align=center>$font<small>Sats</td></tr>\n";
		$y=udskriv('KM', $x, $y, $art, $id, "K", $kodenr, $beskrivelse, $box1, '6', $box2, '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6');
		print "<tr><td><br></td></tr>\n";
		print "<tr><td></td><td colspan=3>$font<small><b>Erhvervelsesmoms (k&oslash;b i udlandet)</td></tr>\n";
		print "<tr><td></td><td>$font<small>Nr</td><td align=center>$font<small>Beskrivelse</td><td align=center>$font<small>Konto</td><td align=center>$font<small>Sats</td><td align=center> <span title='Konto til postering af k&oslash;bsmoms for k&oslash;b i udlandet'>$font<small>Modkonto</span></td></tr>\n";
		$y=udskriv('EM', $x, $y, $art, $id, "E", $kodenr, $beskrivelse, $box1, '6', $box2, '6', $box3, '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6');		print "<tr><td><br></td></tr>\n";
		print "<tr><td></td><td colspan=3>$font<small><b>Momsrapport (konti som skal indg&aring; i momsrapport)</td></tr>\n";
		print "<tr><td></td><td>$font<small>Nr</td><td align=center>$font<small>Beskrivelse</td><td align=center>$font<small><span title='F&oslash;rste kontonummer som skal indg&aring; i rapporten'>Fra</span></small></td><td align=center>$font<small><span title='Sidste kontonummer som skal indg&aring; i rapporten'>Til</span></small></td></tr>\n";
		$y=udskriv('MR', $x, $y, $art, $id, "R", $kodenr, $beskrivelse, $box1, '6', $box2, '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6');
	}
	elseif($valg=='valuta'){
		print "<tr><td></td><td colspan=3>$font<small><b>Valutakoder</td></tr>\n";
		print "<tr><td></td><td>$font<small>Nr</td><td align=center>$font<small>Beskrivelse</td><td align=center>$font<small>Kode</td><td align=center>$font<small>Kurs</td></tr>\n";
		$y=udskriv('VK', $x, $y, $art, $id, '&nbsp;', $kodenr, $beskrivelse, $box1, '6', $box2, '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6');
	}
	elseif($valg=='debitor'){
		print "<tr><td></td><td colspan=2>$font<small><b>Debitorgrupper</td><td></td></tr>\n";
		print "<tr><td></td><td>$font<small>Nr</td><td align=center>$font<small>Beskrivelse</td><td align=center>$font<small>Momsgrp</td><td align=center>$font<small>Samlekt.</td><td align=center>$font<small>Valuta</td>";
		print "<td align=center>$font<small><span title=\"Modkonto ved udligning af &aring;bne poster\">Modkonto</td>";
		$spantilte="RABAT!\nHer angives rabatsatsen i procent for kundegruppen.";
		print "<td align=center><span title = \"$spantilte\">$font<small>Rabat</small></td>";
		$spantilte="Provisionsprocent!\nHer angives hvor stor en procentdel af d&aelig;kningsbidraget det medg&aring;r ved beregning af provision.";
		print "<td alicn=center><span title = \"$spantilte\">$font<small>Provision</small></td></tr>\n";
		$y=udskriv('DG', $x, $y, $art, $id, 'D', $kodenr, $beskrivelse, $box1, '6', $box2, '6', $box3, '6', $box4, '6', $box5, '2', $box6, '2', '-', '6', '-', '6', '-', '6');
		print "<tr><td><br></td></tr>\n";
		print "<tr><td></td><td colspan=2>$font<small><b>Kreditorgrupper</td><td></td></tr>\n";
		print "<tr><td></td><td>$font<small>Nr</td><td align=center>$font<small>Beskrivelse</td><td align=center>$font<small>Momsgrp</td><td align=center>$font<small>Samlekt.</td><td align=center>$font<small>Valuta</td><td align=center>$font<small><span title=\"Modkonto ved udligning af &aring;bne poster\">Modkonto</td></tr>\n";
		$y=udskriv('KG', $x, $y, $art, $id, 'K', $kodenr, $beskrivelse, $box1, '6', $box2, '6', $box3, '6', $box4, '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6');
	}
/*	elseif($valg=='rabat'){
		print "<tr><td></td><td colspan=2>$font<small><b>Rabatgrupper</td><td></td></tr>\n";
		print "<tr><td></td><td>$font<small>Nr</td><td align=center>$font<small>Beskrivelse</td><td align=center>$font<small>Rabat</td><td align=center>$font<small></td><td align=center>$font<small></td><td align=center>$font<small></td></tr>\n";
		$y=udskriv('RG', $x, $y, $art, $id, '&nbsp', $kodenr, $beskrivelse, $box1, '2', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6');
	}
*/	elseif($valg=='afdelinger'){
		print "<tr><td></td><td colspan=3 align=center>$font<small><b>Afdelinger</td></tr>\n";
		print "<tr><td></td><td>$font<small>Nr</td><td>$font<small>Beskrivelse</td></tr>\n";
		$y=udskriv('AFD', $x, $y, $art, $id, '&nbsp;', $kodenr, $beskrivelse, '-', '2', "-", '2', "-", '2', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6');
	}
	elseif($valg=='lagre'){
		print "<tr><td></td><td colspan=3 align=center>$font<small><b>Lagre</td></tr>\n";
		print "<tr><td></td><td>$font<small>Nr</td><td>$font<small>Beskrivelse</td><td align=center>$font<small>Afd.</td></tr>\n";
		$y=udskriv('LG', $x, $y, $art, $id, '&nbsp;', $kodenr, $beskrivelse, $box1, '2', "-", '2', "-", '2', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6');
	}
	elseif($valg=='varer'){
		print "<tr><td></td><td colspan=10 align=center>$font<b>Varegrupper</td></tr><tr><td colspan=10><hr></td></tr>\n";
		print "<tr><td align=center>$font</td><td></td><td></td><td align=center>$font<small>Lager-</td><td align=center>$font<small>Lager-</td><td align=center>$font<small>Vare-</td><td align=center>$font<small>Vare-</td><td align=center>$font<small>Lager-</td><td>$font<small>Max</td><td align=center>$font<small>Moms-</td><td align=center>$font<small>Lager-</td><td align=center>$font<small>Batch-</td></tr>\n";
		print "<tr><td></td><td>$font<small>Nr</td><td align=center>$font<small>Beskrivelse</td><td align=center>$font<small>tilgang</td><td align=center>$font<small>tr&aelig;k</td><td align=center>$font<small>k&oslash;b</td><td align=center>$font<small>salg</td><td align=center>$font<small>regulering</td><td align=center>$font<small>rabat %</td><td align=center>$font<small>fri</td><td align=center>$font<small>f&oslash;rt</td><td align=center>$font<small>kontrol</td></tr>\n";
		$y=udskriv('VG', $x, $y, $art, $id, '&nbsp;', $kodenr, $beskrivelse, $box1, '6', $box2, '6', $box3, '6', $box4, '6', $box5, '6', $box6, '2', $box7, 'checkbox', $box8, 'checkbox', $box9, 'checkbox');
	}
	elseif($valg=='formularer'){
		print "<tr><td></td><td colspan=5 align=center>$font<small><b>Formularer</td></tr>\n";
		print "<tr><td></td><td colspan=5 align=center>$font<small><small><a href=\"logoupload.php?upload=Yes\">Hent logo</a></td></tr>\n";
		print "<tr><td></td><td></td><td align=center>$font<small>Beskrivelse</td><td align=center>$font<small>Printkommando</td><td align=center>$font<small>PDF-kommando</td><td align=center>$font<small></td><td align=center>$font<small></td></tr>\n";
		$y=udskriv('PV', $x, $y, $art, $id, '&nbsp;', $kodenr, $beskrivelse, $box1, '20',$box2, '20', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6', '-', '6');
	}
	print "<tr><td><br></td></tr>\n";
	print "</tbody></table></td>";
	print "<input type = hidden name=antal value=$y><input type = hidden name=valg value=$valg>";
	print "<tr><td colspan = 3 align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\"></td></tr>\n";
	print "</form>";

###########################################################################################################################
function udskriv($a, $x, $y, $art, $id, $k, $kodenr, $beskrivelse, $box1, $b1, $box2, $b2, $box3, $b3, $box4, $b4, $box5, $b5, $box6, $b6, $box7, $b7, $box8, $b8, $box9, $b9){
global $font;

	for ($i=0; $i<=$x; $i++)	{
	if ($art[$i]=='MR') $momsrapport=$i;		
		if ((($art[$i]=='SM')||($art[$i]=='KM')||($art[$i]=='EM'))&&($box2!='-')) $box2[$i]=dkdecimal($box2[$i]);
		if (($art[$i]=='DG')&&($box6[$i]!=NULL)) $box5[$i]=dkdecimal($box5[$i]);
		if (($art[$i]=='DG')&&($box6[$i]!=NULL)) $box6[$i]=dkdecimal($box6[$i]);
		if ($art[$i]==$a){
		print "<tr>";
			print "<td>$font<small>$k</td>";
			print "<td>$font<input type=text style=\"text-align:right\" size=2 name=kodenr[$i] value=$kodenr[$i]></td>";
			print "<td>$font<input type=text size=40 name=beskrivelse[$i] value=\"$beskrivelse[$i]\"></td>";
			if (($box1!="-") &&($b1!="checkbox")){print "<td>$font<input type=text style=\"text-align:right\" size=$b1 name=box1[$i] value='$box1[$i]'></td>";}
			elseif($b1=="checkbox") {
				if (strstr($box1[$i],'on')){print "<td>$font<input type=checkbox name=box1[$i] checked></td>";}
				else {print "<td>$font<input type=checkbox name=box1[$i]></td>";}
			}
			print "<input type = hidden name=id[$i] value='$id[$i]'><input type = hidden name=art[$i] value='$art[$i]'><input type = hidden name=kode[$i] value='$k'>";
			if (($box2!="-") &&($b2!="checkbox")){print "<td>$font<input type=text style=\"text-align:right\" size=$b2 name=box2[$i] value='$box2[$i]'></td>";}
			elseif($b2=="checkbox"){
				if (strstr($box2[$i],'on')){print "<td>$font<input type=checkbox name=box2[$i] checked></td>";}
				else {print "<td>$font<input type=checkbox name=box2[$i]></td>";}
			}
			print "<input type = hidden name=id[$i] value='$id[$i]'><input type = hidden name=art[$i] value='$art[$i]'><input type = hidden name=kode[$i] value='$k'>";
			if (($box3!="-") &&($b3!="checkbox")){print "<td>$font<input type=text style=\"text-align:right\" size=$b3 name=box3[$i] value='$box3[$i]'></td>";}
			elseif($b3=="checkbox"){
				if (strstr($box3[$i],'on')){print "<td>$font<input type=checkbox name=box3[$i] checked></td>";}
				else {print "<td>$font<input type=checkbox name=box3[$i]></td>";}
			}
			print "<input type = hidden name=id[$i] value='$id[$i]'><input type = hidden name=art[$i] value='$art[$i]'><input type = hidden name=kode[$i] value='$k'>";
			if (($box4!="-") &&($b4!="checkbox")){print "<td>$font<input type=text style=\"text-align:right\" size=$b4 name=box4[$i] value='$box4[$i]'></td>";}
			elseif($b4=="checkbox"){
				if (strstr($box4[$i],'on')){print "<td>$font<input type=checkbox name=box4[$i] checked></td>";}
				else {print "<td>$font<input type=checkbox name=box4[$i]></td>";}
			}
			print "<input type = hidden name=id[$i] value='$id[$i]'><input type = hidden name=art[$i] value='$art[$i]'><input type = hidden name=kode[$i] value='$k'>";
			if ($box5!="-") {print "<td>$font<input type=text style=\"text-align:right\" size=$b5 name=box5[$i] value='$box5[$i]'></td>";}
			if ($box6!="-") {print "<td>$font<input type=text style=\"text-align:right\" size=$b6 name=box6[$i] value='$box6[$i]'></td>";}
			if (($box7!="-")&&($b7!="checkbox")) {print "<td>$font<input type=text style=\"text-align:right\" size=$b7 name=box7[$i] value='$box7[$i]'></td>";}
			elseif($b7=="checkbox"){
				if (strstr($box7[$i],'on')){print "<td align=center>$font<input type=checkbox name=box7[$i] checked></td>";}
				else {print "<td align=center>$font<input type=checkbox name=box7[$i]></td>";}
			}
			if (($box8!="-")&&($b8!="checkbox")) {print "<td>$font<input type=text style=\"text-align:right\" size=$b8 name=box8[$i] value='$box8[$i]'></td>";}
			elseif($b8=="checkbox"){
				if (strstr($box8[$i],'on')){print "<td align=center>$font<input type=checkbox name=box8[$i] checked></td>";}
				else {print "<td align=center>$font<input type=checkbox name=box8[$i]></td>";}
			}
			if (($box9!="-")&&($b9!="checkbox")) {print "<td>$font<input type=text style=\"text-align:right\" size=$b9 name=box9[$i] value='$box9[$i]'></td>";}
			elseif($b9=="checkbox"){
				if (strstr($box9[$i],'on')){print "<td align=center>$font<input type=checkbox name=box9[$i] checked></td>";}
				else {print "<td align=center>$font<input type=checkbox name=box9[$i]></td>";}
			}
			print "</tr>\n";
			print "<input type = hidden name=id[$i] value='$id[$i]'><input type = hidden name=art[$i] value='$art[$i]'><input type = hidden name=kode[$i] value='$k'>";
		}
	}
	if (($k!='R')||(!$momsrapport)) {
		$y++;
		print "<tr>";
		print "<td>$font<small>$k</td>";
		print "<td>$font<input type=text size=2 name=kodenr[$y]></td>";
		print "<td>$font<input type=text size=40 name=beskrivelse[$y]></td>";
		if (($box1!="-")&&($b1!="checkbox")) {print "<td>$font<input type=text style=\"text-align:right\" size=$b1 name=box1[$y]></td>";}
		elseif($b1=="checkbox") {print "<td>$font<input type=checkbox name=box1[$y]></td>";}
		print "<input type = hidden name=id[$y] value='0'><input type = hidden name=kode[$y] value='$k'><input type = hidden name=art[$y] value=$a>";
		if (($box2!="-")&&($b2!="checkbox")) {print "<td>$font<input type=text style=\"text-align:right\" size=$b2 name=box2[$y]></td>";}
		elseif($b2=="checkbox") {print "<td>$font<input type=checkbox name=box2[$y]></td>";}
		print "<input type = hidden name=id[$y] value='0'><input type = hidden name=kode[$y] value='$k'><input type = hidden name=art[$y] value=$a>";
		if (($box3!="-")&&($b3!="checkbox")) {print "<td>$font<input type=text style=\"text-align:right\" size=$b3 name=box3[$y]></td>";}
		elseif($b3=="checkbox") {print "<td>$font<input type=checkbox name=box3[$y]></td>";}
		print "<input type = hidden name=id[$y] value='0'><input type = hidden name=kode[$y] value='$k'><input type = hidden name=art[$y] value=$a>";
		if (($box4!="-")&&($b4!="checkbox")) {print "<td>$font<input type=text style=\"text-align:right\" size=$b4 name=box4[$y]></td>";}
		elseif($b4=="checkbox") {print "<td>$font<input type=checkbox name=box4[$y]></td>";}
		print "<input type = hidden name=id[$y] value='0'><input type = hidden name=kode[$y] value='$k'><input type = hidden name=art[$y] value=$a>";
		if ($box5!="-") {print "<td>$font<input type=text style=\"text-align:right\" size=$b5 name=box5[$y]></td>";}
		if ($box6!="-") {print "<td>$font<input type=text style=\"text-align:right\" size=$b6 name=box6[$y]></td>";}
		if (($box7!="-")&&($b7!="checkbox")) {print "<td align=center>$font<input type=text style=\"text-align:right\" size=$b7 name=box7[$y]></td>";}
		elseif($b7=="checkbox") {print "<td align=center>$font<input type=checkbox name=box7[$y]></td>";}
		if (($box8!="-")&&($b8!="checkbox")) {print "<td align=center>$font<input type=text style=\"text-align:right\" size=$b8 name=box8[$y]></td>";}
		elseif($b8=="checkbox") {print "<td align=center>$font<input type=checkbox name=box8[$y]></td>";}
		if (($box9!="-")&&($b9!="checkbox")) {print "<td align=center>$font<input type=text style=\"text-align:right\" size=$b9 name=box9[$y]></td>";}
		elseif($b9=="checkbox") {print "<td align=center>$font<input type=checkbox name=box9[$y]></td>";}

		print "<input type = hidden name=id[$y] value='0'><input type = hidden name=kode[$y] value='$k'><input type = hidden name=art[$y] value=$a>";
		print "</tr>\n";
	}
	return $y;
}

###########################################################################################################################
function nytaar($beskrivelse, $kodenr, $kode, $art, $box1, $box3, $box3, $box4, $box5, $box6)
{
	$query = db_SELECT("SELECT id FROM grupper WHERE art = 'RA'");
	print "<form name=nytaar action=syssetup.php method=post>";
	print "<tr><td colspan=4 align = center>$font<big><b>Opret Regnskabs&aring;r: $beskrivelse</td></tr>\n";
	if (!$row = db_fetch_array($query))
	{
		print "<tr><td colspan=2 align=center>$font Intast primotal for 1. regnskabs&aring;r:</td><td align = center>debet</td><td align = center>kredit</td></tr>\n";
		$query = db_SELECT("SELECT id, kontonr, beskrivelse FROM kontoplan WHERE kontotype='D' or kontotype='S' order by kontonr");
		while ($row = db_fetch_array($query))
		{
			print "<tr><input type=hidden name=kontonr[$y] value=$row[kontonr]><td>$row[kontonr]</td><td>$row[beskrivelse]</td><td width=10 align=right><input type=text size=10 name=debet[$y]></td><td align=right><input type=text size=10 name=kredit[$y]></td></tr>\n";
		}
	}
	else
	{
		print "<tr><td>$font Overf&oslash;r &aring;bningsbalance</td><td><input type=checkbox name=aabn_bal></td></tr>\n";
	}
	print "<tr><td colspan = 4 align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\"></td></tr>\n";
	print "</form>";
	exit;
}

###########################################################################################################################
function tjek ($id, $beskrivelse, $kodenr, $kode, $art, $box1, $box2, $box3, $box4,$box5, $box6,$box7, $box8, $box9)
{
	if ($beskrivelse)
	{
		if ($art=='VG')
		{
			if ($box8=='on')
			{
				if (!$box1) {print "<BODY onLoad=\"javascript:alert('Lager Tilgang\" skal udfyldes n&aring;r \"Lagerf&oslash;rt\" er afm&aelig;rket')\">";}
				else {$fejl=kontotjek($box1);}
				if (!$box2) {print "<BODY onLoad=\"javascript:alert('Lager Tr&aelig;k\" skal udfyldes n&aring;r \"Lagerf&oslash;rt\" er afm&aelig;rket')\">";}
				else {$fejl=kontotjek($box2);}
			}		
			if (!$box3) {print "<BODY onLoad=\"javascript:alert('\"Varek&oslash;b\" skal udfyldes')\">";}
			else {$fejl=kontotjek($box3);}
			if (!$box4) {print "<BODY onLoad=\"javascript:alert('\"Varesalg\" skal udfyldes')\">";}
			else {$fejl=kontotjek($box4);}
			if ($box5) {$fejl=kontotjek($box5);}
			if ($box6) {$fejl=kontotjek($box6);}
		}
		if (($art=='DS')||($art=='KS')||($art=='KM')||($art=='SM')) {$fejl=kontotjek($box1);}
		if (($art=='DG')||($art=='KG')) {$fejl=momsktotjek($art, $box1);}
		if (($art=='DG')||($art=='KG')) {$fejl=kontotjek($box2);}
		if ($art=='LG') {$fejl=afdelingstjek($box1);}
				
		return $fejl;	
	}
}

###########################################################################################################################
function kontotjek ($konto)
{ 
	$konto=$konto*1;	
	$query = db_SELECT("SELECT id FROM kontoplan WHERE kontonr = '$konto'");
	if (!db_fetch_array($query)) { 
		print "<BODY onLoad=\"javascript:alert('Kontonr: $konto findes ikke!!')\">";
		$fejl=1;
	}
	return $fejl;
}

###########################################################################################################################
function momsktotjek ($art, $konto)
{
	if ($art=='DG') {$momsart="art='SM'";}
	if ($art=='KG') {$momsart="(art='KM' or art='EM')";}
	$kode=substr($konto,0,1);
	$kodenr=substr($konto,1,1);
	$query = db_SELECT("SELECT id FROM grupper WHERE $momsart and kodenr = '$kodenr' and kode = '$kode'");
	if (!db_fetch_array($query))	{ 
		if ($art=='DG')	{print "<BODY onLoad=\"javascript:alert('salgsmomsgruppe: $konto findes ikke!!')\">";}
		if ($art=='KG')	{print "<BODY onLoad=\"javascript:alert('k&oslash;bsmomskonto: $konto findes ikke!!')\">";}
		$fejl=1;
	}
	return $fejl;
}
###########################################################################################################################
function afdelingstjek ($konto)
{
	$query = db_SELECT("SELECT id FROM grupper WHERE art='AFD' and kodenr = '$konto'");
	if (!db_fetch_array($query))	{
		print "<BODY onLoad=\"javascript:alert('Afdeling: $konto findes ikke!!')\">";
		$fejl=1;
	}
	return $fejl;
}

?>
</tbody>
</table>
</td></tr>
<tr><td align = "center" valign = "bottom">
		<table width="100%" align="center" border="1" cellspacing="0" cellpadding="0"><tbody>
			<td width="100%" bgcolor=<?php echo $bgcolor2 ?>><font face="Helvetica, Arial, sans-serif" color="#000066"><small><br></small></td>
		</tbody></table>
</td></tr>
</tbody></table>
</body></html>
