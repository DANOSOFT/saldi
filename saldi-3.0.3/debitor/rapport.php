<?php

// ------------------------debitor/rapport.php-------patch 2.1.8----2010-04-21-----------
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
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------


@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Debitorrapport";
$modulnr=12;
$md[1]="januar"; $md[2]="februar"; $md[3]="marts"; $md[4]="april"; $md[5]="maj"; $md[6]="juni"; $md[7]="juli"; $md[8]="august"; $md[9]="september"; $md[10]="oktober"; $md[11]="november"; $md[12]="december";$rapportart='';$submit='';$maaned_fra='';$maaned_til='';
$konto_fra='';$konto_til='';

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/forfaldsdag.php");
include("../includes/autoudlign.php");
include("../includes/openpost.php");

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";

if (isset($_GET['ny_rykker'])) {
	$maaned_fra=$_GET['maaned_fra'];
	$maaned_til=$_GET['maaned_til'];
	$konto_fra=$_GET['konto_fra'];
	$konto_til=$_GET['konto_til'];
	$regnaar=$_GET['regnaar'];
	openpost($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til, $rapportart, 'D');
	exit;
}
if (isset($_POST['submit'])) {
	$submit=strtolower(trim($_POST['submit']));
	$rapportart=strtolower(trim($_POST['rapportart']));
	$maaned_fra=$_POST['maaned_fra'];
	$maaned_til=$_POST['maaned_til'];
#	$md=$_POST['md'];
#	if (isset($_POST['konto_fra']) && strpos($_POST['konto_fra'],":")) {
		list ($konto_fra, $firmanavn) = split(":", $_POST['konto_fra']);
		$konto_fra = trim($konto_fra);
#	}
#	if (isset($_POST['konto_til']) && strpos($_POST['konto_til'],":")) {
		list ($konto_til, $firmanavn) = split(":", $_POST['konto_til']);
		$konto_til = trim($konto_til);
#	}
#	if (isset($_POST['regnaar']) && strpos($_POST['regnaar'],"-")) {
		list ($regnaar, $firmanavn)= split("-", $_POST['regnaar']);
		$firmanavn = trim($firmanavn);
#	}
	if (($submit=="mail kontoudtog")||($submit=="opret rykker")||($submit=="ryk alle")){
		$kontoantal=$_POST['kontoantal'];
		$konto_id=$_POST['konto_id'];
		$kontoudtog=$_POST['kontoudtog'];
		$rykkerbelob=$_POST['rykkerbelob'];
		$y=0;
		for($x=1; $x<=$kontoantal; $x++){
			if (($kontoudtog[$x]=='on')&&(($submit=="mail kontoudtog")||($rykkerbelob[$x]>0))) {
				$tmp=$tmp.$konto_id[$x].";";
				$y++;			
			}
		}
		$kontoantal=$y;
		if ($tmp){
			if ($submit=="mail kontoudtog") {
				print "<BODY onLoad=\"window.open('mail_kontoudtog.php?kontoliste=$tmp&maaned_fra=$maaned_fra&maaned_til=$maaned_til&regnaar=$regnaar&kontoantal=$kontoantal','','$jsvars')\">";
			} else {
				print "<BODY onLoad=\"window.open('ny_rykker.php?kontoliste=$tmp&kontoantal=$kontoantal','','$jsvars')\">";
				$ny_rykker=1;
			} 
		} elseif ($submit=="ryk alle") {
			print "<BODY onLoad=\"window.open('ny_rykker.php?kontoliste=alle&kontoantal=max','','$jsvars')\">";
			$ny_rykker=1;
		} else {
			if ($submit=="mail kontoudtog") {print "<BODY onLoad=\"javascript:alert('Der er ikke afm&aelig;rket nogen konti til modtagelse af kontoudtog')\">";}
			else {
				print "<BODY onLoad=\"javascript:alert('Der er ikke afm&aelig;rket nogen konti til modtagelse af rykker eller bel&oslash;bet er ikke forfaldent til betaling')\">";
			}
		}
		if (!strstr($maaned_fra," ")) { 
			if ($md[$maaned_fra]) $maaned_fra=$regnaar." ".$md[$maaned_fra];
			else $maaned_fra=$regnaar." ".$maaned_fra;
			if ($md[$maaned_til]) $maaned_til=$regnaar." ".$md[$maaned_til];
			else $maaned_til=$regnaar." ".$maaned_til;
		}
		$submit='ok';
	}elseif ( $submit=="slet" || $submit=="udskriv" || strstr($submit,"bogf") || $submit=="ny rykker" || $submit=="afslut") {
		$rykkerantal=if_isset($_POST['rykkerantal']);
		$rykker_id=if_isset($_POST['rykker_id']);
		$rykkerbox=if_isset($_POST['rykkerbox']);
		if ($submit=="slet") {
			for($x=1; $x<=$rykkerantal; $x++){
				if (isset($rykkerbox[$x]) && $rykkerbox[$x]=='on') {
					db_modify("delete from ordrelinjer where ordre_id=$rykker_id[$x]",__FILE__ . " linje " . __LINE__);	
					db_modify("delete from ordrer where id=$rykker_id[$x]",__FILE__ . " linje " . __LINE__);	
				}
			}
		} elseif ($submit=="udskriv" || $submit=="ny rykker" || $submit=="afslut") {
			$tmp='';
			$tmp2=0;
			for($x=1; $x<=$rykkerantal; $x++){
				if ($rykkerbox[$x]=='on') {
					if ($tmp) $tmp=$tmp.";";
					$tmp=$tmp.$rykker_id[$x];
					$tmp2++;	
				}
			}
			if ($submit=="udskriv" && $tmp2>0) print "<BODY onLoad=\"window.open('rykkerprint.php?rykker_id=$tmp&kontoantal=$tmp2','','$jsvars')\">";
			elseif ($submit=="ny rykker" && $tmp2>0) {
				print "<BODY onLoad=\"window.open('ny_rykker.php?rykker_id=$tmp&kontoantal=$tmp2','','$jsvars')\">";
				$ny_rykker=1;
			} elseif ($submit=="afslut" && $tmp2>0) {
				print "<BODY onLoad=\"window.open('afslut_rykker.php?rykker_id=$tmp&kontoantal=$tmp2','','$jsvars')\">";
				$ny_rykker=1;
			} 
		} elseif (strstr($submit,"bogf")) {
			for($x=1; $x<=$rykkerantal; $x++){
				if ($rykkerbox[$x]=='on') bogfor_rykker($rykker_id[$x]);
			}
		}
		if (!strstr($maaned_fra," ")) { 
			if ($md[$maaned_fra]) $maaned_fra=$regnaar." ".$md[$maaned_fra];
			else $maaned_fra=$regnaar." ".$maaned_fra;
			if ($md[$maaned_til]) $maaned_til=$regnaar." ".$md[$maaned_til];
			else $maaned_til=$regnaar." ".$maaned_til;
		}
		$submit='ok';
	}
} elseif(isset($_GET['konto_fra'])) {
	$rapportart=$_GET['rapportart'];
	$maaned_fra=$_GET['maaned_fra'];
	$maaned_til=$_GET['maaned_til'];
	$konto_fra=$_GET['konto_fra'];
	$konto_til=$_GET['konto_til'];
	$regnaar=$_GET['regnaar'];
	$submit=$_GET['submit'];
	if ($udlign=$_GET['udlign']) autoudlign($udlign);
}	elseif (isset($_GET['kontonr'])){
	$konto_fra=$_GET['kontonr'];
	$konto_til=$_GET['kontonr'];
	$submit="ok";
	$rapportart=$_GET['rapportart'];
	$row = db_fetch_array(db_select("select * from grupper where art = 'RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__));
		$start_md[$x]=$row['box1']*1;
		$start_aar[$x]=$row['box2']*1;
		$slut_md[$x]=$row['box3']*1;
		$slut_aar[$x]=$row['box4']*1;
		$maaned_fra="$row[box2] $row[box1]";
		$maaned_til="$row[box4] $row[box3]";
} 
if ($maaned_fra) $maaned_fra=find_maaned_nr($maaned_fra); 
if ($maaned_til) $maaned_til=find_maaned_nr($maaned_til); 

if (strstr($rapportart, "ben post")) $rapportart="openpost";
if ($submit != 'ok') $submit='forside';
elseif ($rapportart) $submit=$rapportart;

$submit($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til, $rapportart, 'D');

#############################################################################################################
function forside($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til, $rapportart) {

	#global $connection;
	global $brugernavn;
	global $top_bund;
	global $md;
	global $returside;
	global $jsvars;
	global $popup;
			
	if ($regnaar!='0')	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaare numerisk
	$konto_fra=$konto_fra*1;
	$konto_til=$konto_til*1;

	if ($maaned_fra) $maaned_fra=find_maaned_navn($maaned_fra); 
	if ($maaned_til) $maaned_til=find_maaned_navn($maaned_til); 
	
	if($r=db_fetch_array(db_select("select * from grupper where art = 'DRV' and kodenr = '1' order by box2",__FILE__ . " linje " . __LINE__))){
		if (!$regnaar && $regnaar !='0') $regnaar=$r['box1'];
		if (!$maaned_fra) $maaned_fra=$r['box2'];
		if (!$maaned_til) $maaned_til=$r['box3'];
		if (!$konto_fra) $konto_fra=$r['box4'];
		if (!$konto_til) $konto_til=$r['box5'];
		if (!$rapportart) $rapportart=$r['box6'];
	} else db_modify("insert into grupper (beskrivelse,kodenr,art) values ('Debitorrapportvisning','1','DRV')",__FILE__ . " linje " . __LINE__);
	db_modify("update grupper set box1='$regnaar',box2='$maaned_fra',box3='$maaned_til',box4='$konto_fra',box5='$konto_til',box6='$rapportart' where art='DRV' and kodenr='1'");

	if ($regnaar=='0') {
		$aktiv='0';
		$regn_beskrivelse[0]="Alle regnskabs&aring;r"; 
	}elseif (!$regnaar)	{
		$query = db_select("select regnskabsaar from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$regnaar = $row['regnskabsaar'];
	}
	$query = db_select("select * from grupper where art = 'RA' order by box2",__FILE__ . " linje " . __LINE__);
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
		if ($regnaar==$row['kodenr']){$aktiv=$x;}
	}
	$antal_regnaar=$x;

	$query = db_select("select * from adresser where art = 'D' order by ".nr_cast('kontonr')."",__FILE__ . " linje " . __LINE__);
	$x=0;
	while ($row = db_fetch_array($query)) {
		$x++;
		$konto_id[$x]=$row['id'];
		$kontonr[$x]=$row['kontonr'];
		$firmanavn[$x]=$row['firmanavn'];
		if ($kontonr[$x]==$konto_fra){$konto_fra=$kontonr[$x]." : ".$firmanavn[$x];}
		if (($kontonr[$x]>0)&&($kontonr[$x]==$konto_til)){$konto_til=$kontonr[$x]." : ".$firmanavn[$x];}
	}
	$antal_konti=$x;
	
	if (!$maaned_fra){$maaned_fra=$md[$start_md[$aktiv]];}
	if (!$maaned_til){$maaned_til=$md[$slut_md[$aktiv]];}
	if (!$konto_fra){$konto_fra=$kontonr[1]." : ".$firmanavn[1];}
	if (!$konto_til){$konto_til=$kontonr[$antal_konti]." : ".$firmanavn[$antal_konti];}

	print "<table cellpadding=\"1\" cellspacing=\"3\" border=\"0\" width=100% height=100% valign=\"top\"><tbody>";
	print "<tr><td height=\"8\" width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=$returside accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Debitorrapport</td>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
	print "</tr><tr><td height=99%><br></td><td>";
	print "<form name=regnskabsaar action=rapport.php method=post>";
	print "<table cellpadding=\"1\" cellspacing=\"5\" border=\"1\" align=\"center\"><tbody>\n"; #C
	print "<tr><td align=center colspan=4><h3>Rapporter</font><br></h3></td></tr>";
	print "<td><table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
	
	print "<tr><td>$font Regnskabs&aring;r</td><td width=100><select name=regnaar>";
	print "<option>$regnaar - $regn_beskrivelse[$aktiv]</option>";
	for ($x=1; $x<=$antal_regnaar;$x++)
	{
		if ($x!=$aktiv) {print "<option>$regn_kode[$x] - $regn_beskrivelse[$x]</option>";}
	}
	print "<option>0 - Alle regnskabs&aring;r</option>";
	print "</select>";
	
	print "</td><td width=100 align=center><input type=submit value=\"Opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
	print "<form name=rapport action=rapport.php method=post>";

	print "<td> Rapport art</td><td><select name=rapportart>";
	if ($rapportart=="openpost") {print "<option value=\"openpost\">&Aring;ben post</option>";}
	elseif ($rapportart) {print "<option>$rapportart</option>";}
	if ($rapportart!="openpost") {print "<option value=\"openpost\">&Aring;ben post</option>";}
	if ($rapportart!="Kontokort") {print "<option value=\"Kontokort\">Kontokort</option>";}
	if ($rapportart!="Kontosaldo") {print "<option value=\"Kontosaldo\">Kontosaldo</option>";}
	print "</td></tr>";
/*
	print "<td>Rapporttype</td><td><select name=rapportart>";
	if ($rapportart!="Kontokort") {
		print "<option>&Aring;ben post</option>" ;
		print "<option>Kontokort</option>";
	}
	else {
		print "<option>Kontokort</option>";
		print "<option>&Aring;ben post</option>";
	}
	print "</td></tr>";
*/	
	if ($regnaar) {
		print "<tr><td>Periode</td><td colspan=2><select name=maaned_fra>";
		print "<option>$start_aar[$aktiv] $maaned_fra</option>";
		for ($x=$start_md[$aktiv]; $x <= 12; $x++) {
			print "<option>$start_aar[$aktiv] $md[$x]</option>";
		}
		if (($start_md[$aktiv]>1)&&($slut_md[$aktiv]<12)) {
			for ($x=1; $x<=$slut_md[$aktiv]; $x++) {
				print "<option>$slut_aar[$aktiv] $md[$x]</option>";
			}
		}
		print "</td>";
		print "<td colspan=2><select name=maaned_til>";
		print "<option>$slut_aar[$aktiv] $maaned_til</option>";
		for ($x=$start_md[$aktiv]; $x <= 12; $x++) {
			print "<option>$start_aar[$aktiv] $md[$x]</option>";
		}
		if (($start_md[$aktiv]>1)&&($slut_md[$aktiv]<12)) {
			for ($x=1; $x<=$slut_md[$aktiv]; $x++) {
				print "<option>$slut_aar[$aktiv] $md[$x]</option>";
			}
		}
		print "</td></tr>";
	}
	print "<tr><td>Konto (fra)</td><td colspan=4><select name=konto_fra>";
	print "<option>$konto_fra</option>";
	for ($x=1; $x<=$antal_konti; $x++) {
		print "<option>$kontonr[$x] : $firmanavn[$x]</option>";
	}
	print "</td></tr>";
	print "<tr><td>Konto (til)</td><td colspan=4><select name=konto_til>";
	print "<option>$konto_til</option>";
	for ($x=1; $x<=$antal_konti; $x++) {
		print "<option>$kontonr[$x] : $firmanavn[$x]</option>";
	}
	print "</tbody></table>";
	print "<input type=hidden name=regnaar value=$regnaar>";
	print "<tr><td colspan=5 align=center><input type=submit value=\"  OK  \" name=\"submit\"></td></tr>";
	print "</form>";
	print "<tr><td colspan=5 ALIGN=center onClick=\"javascript:top100=window.open('top100.php','top100','$jsvars');top100.focus();\"><span title='Liste over de 100 debitorer med den h&oslash;jeste oms&aelig;tning de seneste 12 m&aring;neder.'><input type=submit value=\"Top 100\" name=\"submit\"></span></td></tr>";	
	print "</tbody></table>";
	print "<td><br></td></tr>";
	print "</tbody></table>";
	}

#############################################################################################################
function kontokort($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til) {
#	global $connection;
	global $top_bund;
	global $md;
	global $popup;
#	global $returside;
#	global $rapportart;
	global $bgcolor;
	global $bgcolor5;
	
	$konto_fra=$konto_fra*1;
	$konto_til=$konto_til*1;
	
	list ($maaned_fra,$aar_fra) = split(" ", $maaned_fra);
	list ($maaned_til,$aar_til) = split(" ", $maaned_til);
	if (strlen($maaned_fra)>2) {
		$tmp=$maaned_fra;
		$maaned_fra=$aar_fra;
		$aar_fra=$tmp;
		$tmp=$maaned_til;
		$maaned_til=$aar_til;
		$aar_til=$tmp;
	}
	
	$kilde=if_isset($_GET['kilde']);
	$kilde_kto_fra=if_isset($_GET['kilde_kto_fra']);
	$kilde_kto_til=if_isset($_GET['kilde_kto_til']);
#	if ($popup) $returside="../includes/luk.php";
	if ($kilde=='openpost') $returside="rapport.php?rapportart=openpost&submit=ok&regnaar=$regnaar&maaned_fra=$aar_fra $maaned_fra&maaned_til=$aar_til $maaned_til&konto_fra=$kilde_kto_fra&konto_til=$kilde_kto_til";
	elseif($_GET['returside']) $returside= $_GET['returside'];
	else $returside="rapport.php?regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til";
	if (strstr("debitorkort.php",$returside)) $regnaar=0;

	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk
	
/*
echo "MF $maaned_fra";
$currentdate=date("Y-m-d");
	if (strlen($maaned_fra)>2) {
		list ($aar_fra, $maaned_fra) = split(" ", $maaned_fra);
		list ($aar_til, $maaned_til) = split(" ", $maaned_til);
		if (strlen($maaned_fra)>2) {
			$tmp=$maaned_fra;
			$maaned_fra=$aar_fra;
			$aar_fra=$tmp;
			$tmp=$maaned_til;
			$maaned_til=$aar_til;
			$aar_til=$tmp;
		}
*/		
		if (strlen($maaned_fra)>2) $luk= "<a accesskey=L href=\"rapport.php?rapportart=Kontokort&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til\">";
		else $luk= "<a accesskey=L href=\"$returside\">";
#	}
#	else $luk= "<a accesskey=L href=\"$returside\">";

 # $maaned_fra=$maaned_fra*1;
 # $maaned_til=$maaned_til*1;

	$maaned_fra=trim($maaned_fra);
	$maaned_til=trim($maaned_til);
	
	print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	print "<tr><td colspan=\"8\" height=\"8\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$luk Luk</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Debitorapport - kontokort</td>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
	print "</tbody></table>"; #B slut
	print "</td></tr>";

	for ($x=1; $x<=12; $x++) {
		if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
		if ($maaned_til==$md[$x]){$maaned_til=$x;}
		if (strlen($maaned_fra)==1){$maaned_fra="0".$maaned_fra;}
		if (strlen($maaned_til)==1){$maaned_til="0".$maaned_til;}
	}
	$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$startmaaned=$row['box1']*1;
	$startaar=$row['box2']*1;
	$slutmaaned=$row['box3']*1;
	$slutaar=$row['box4']*1;
	$slutdato=31;

	if ($maaned_fra) {$startmaaned=$maaned_fra;}
	if ($maaned_til) {$slutmaaned=$maaned_til;}
#	if ($aar_fra) {$startaar=$aar_fra;}
#	if ($aar_til) {$slutaar=$aar_til;}

#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}
	
	while (!checkdate($slutmaaned,$slutdato,$slutaar))	{
		$slutdato=$slutdato-1;
		if ($slutdato<28){break;}
	}
	if (strlen($startmaaned)==1) $startmaaned="0".$startmaaned;
	
	$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;
	
	$kontonr=array();
	$x=0;
	$query = db_select("select id from adresser where ".nr_cast('kontonr').">='$konto_fra' and ".nr_cast('kontonr')."<='$konto_til' and art = 'D' order by ".nr_cast('kontonr')."",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$x++;
		$konto_id[$x]=$row[id];
	}
	$kto_id=array();
	$kontoantal=$x;
	$x=0;
	# finder alle konti med bevaegelser i den anfoerte periode eller aabne poster fra foer perioden
	for ($y=1;$y<=$kontoantal;$y++) {
		if ($regnaar) $query = db_select("select amount from openpost where transdate<='$regnslut' and konto_id='$konto_id[$y]'",__FILE__ . " linje " . __LINE__);
		else $query = db_select("select amount from openpost where konto_id='$konto_id[$y]'",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if (!in_array($konto_id[$y],$kto_id)) {
				$x++;
				$kto_id[$x]=$konto_id[$y];
			}
		}
	}
	$kontoantal=$x;
	
#	 print "<tr><td colspan=8><hr></td></tr>";
#	print "<tr><td width=10%>$font Dato</td><td width=10%>$font Bilag</td><td width=50%>$font Tekst</td><td width=10% align=right>$font Debet</td><td width=10% align=right>$font Kredit</td><td width=10% align=right>$font Saldo</td></tr>";

	for ($x=1; $x<=$kontoantal; $x++) {
		$q = db_select("select * from adresser where id=$kto_id[$x]",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array($q);
		$betalingsbet=trim($r['betalingsbet']);
		$betalingsdage=$r['betalingsdage'];
		$r2 = db_fetch_array(db_select("select box3 from grupper where art='DG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
		$valuta=trim($r2['box3']);
		if (!$valuta) $valuta='DKK';
		else {
			$r2 = db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta' and art='VK'",__FILE__ . " linje " . __LINE__));
			$valutakode=$r2['kodenr'];
		}
#		print "<tr><td colspan=8><hr></td></tr>";
		print "<tr><td colspan=8><hr></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td colspan=3>".stripslashes($r['firmanavn'])."</td></tr>";
		print "<tr><td colspan=3>".stripslashes($r['addr1'])."</td></tr>";
		print "<tr><td colspan=3>".stripslashes($r['addr2'])."</td><td colspan=4 align=right>Kontonr</td><td align=right>$r[kontonr]</td></tr>";
		print "<tr><td colspan=3>".stripslashes($r['postnr'])."&nbsp;".stripslashes($r['bynavn'])."</td><td colspan=4 align=right>Dato</td><td align=right>".date('d-m-Y')."</td></tr>";
		print "<tr><td colspan=7 align=right>Valuta</td><td align=right>$valuta</td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td width=10%>Dato</td><td width=5%>Bilag</td><td width=5%>Faktura</td><td width=40%>Tekst</td><td>Forfaldsdato</td><td width=10% align=right>Debet</td><td width=10% align=right>Kredit</td><td width=10% align=right>Saldo</td></tr>";
		print "<tr><td colspan=8><hr></td></tr>";
				
		$kontosum=0;
		$primo=0;
		$primoprint=0;
		if ($regnaar) $q2 = db_select("select * from openpost where konto_id=$kto_id[$x] and transdate<='$regnslut' order by transdate, faktnr, refnr",__FILE__ . " linje " . __LINE__);
		else $q2 = db_select("select * from openpost where konto_id=$kto_id[$x] order by transdate, faktnr, refnr",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			if ($r2['amount']>0) $amount=round($r2['amount']+0.001,2); #2009.10.08
			else $amount=round($r2['amount']-0.001,2);
			$amount=$r2['amount'];
			$beskrivelse=$r2['beskrivelse'];
			$valutakurs=$r2['valutakurs']*1;
			$forfaldsdag=$r2['forfaldsdate'];
			if ($r2['kladde_id']) $refnr=$r2['refnr'];
			else $refnr=NULL;
			if (!$valutakurs) $valutakurs=100;
			$transdate=$r2['transdate'];
			if ($valuta!="DKK" && $valutakurs==100) {
				if ($r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' and valdate <= '$transdate' order by valdate desc",__FILE__ . " linje " . __LINE__))) {
					$amount=$amount*100/$r3['kurs'];
				$beskrivelse = $r2['beskrivelse']." - (Omregnet til $valuta fra DKK, kurs $r2[valutakurs])";
				} elseif ($r3=db_fetch_array(db_select("select kurs from valuta where gruppe ='$valutakode' order by valdate",__FILE__ . " linje " . __LINE__))) {
					$amount=$amount*100/$r3['kurs'];
 					$beskrivelse = $r2['beskrivelse']." - (Omregnet til $valuta fra DKK, kurs $r2[valutakurs])";
				}
			} elseif (($r2['valuta'])&&($r2['valuta']!='DKK' && $valuta=="DKK" && $valutakurs!=100)) {
					$amount=$amount*$valutakurs/100;
					$beskrivelse = $r2['beskrivelse']." - (Omregnet til DKK fra $r2[valuta], kurs $valutakurs)";
			} else $beskrivelse = $r2['beskrivelse'];
			if ($transdate<$regnstart) {
				 $primoprint=0;
				 $kontosum=$kontosum+$amount;
			 }
			 else { 
				if ($primoprint==0) {
					$tmp=dkdecimal($kontosum);
					$tmp2="";
					if ($valuta!='DKK') $tmp2="&nbsp;&nbsp;&nbsp;-&nbsp;&nbsp;&nbsp;Bel&oslash;b kan v&aelig;re omregnet fra DKK";
					print "<tr><td><br></td><td><br></td><td><br></td><td>Primosaldo $tmp2<br></td><td><br></td><td><br></td><td><br></td><td align=right>$tmp<br></td></tr>";
					$primoprint=1;
				}
				print "<tr><td>".dkdato($transdate)."<br></td><td>$refnr<br></td><td>$r2[faktnr]<br></td><td>".stripslashes($beskrivelse)."<br></td>";
 				if ($amount < 0) {$tmp=0-$amount;}
				else {$tmp=$amount;}
				$tmp=dkdecimal($tmp);
				if (!$forfaldsdag) $forfaldsdag=usdate(forfaldsdag($transdate, $betalingsbet, $betalingsdage));
#				if (($row[udlignet]!='1')&&($forfaldsdag<$currentdate)){$stil="<span style='color: rgb(255, 0, 0);'>";}
#				else {$stil="<span style='color: rgb(0, 0, 0);'>";}
				
				if ($amount > 0) {
				if ($r2[udlignet]!='1') print "<td><span style='color: rgb(255, 0, 0);'>".dkdato($forfaldsdag)."<br></td><td align=right title=\"Klik her for at udligne &aring;bne poster\"><span style='color: rgb(255, 0, 0);'><a href=\"../includes/udlign_openpost.php?post_id=$r2[id]&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til&retur=../debitor/rapport.php\">$tmp</a><br></td><td><br></td>";
					else print "<td><span style='color: rgb(0, 0, 0);'>".dkdato($forfaldsdag)."<br></td><td align=right><span style='color: rgb(0, 0, 0);'>$tmp<br></td><td><br></td>";
					$forfaldsum=$forfaldsum+$amount;
				}
				else {
					if ($r2[udlignet]!='1') print "<td><span style='color: rgb(255, 0, 0);'><br></td><td></td><td align=right title=\"Klik her for at udligne &aring;bne poster\"><span style='color: rgb(255, 0, 0);'><a href=\"../includes/udlign_openpost.php?post_id=$r2[id]&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til&retur=../debitor/rapport.php\">$tmp</a><br></td>";
					else print "<td><br></td><td><br></td><td align=right>$tmp<br></td>";
				}
				$kontosum=$kontosum+$amount;
				$tmp=dkdecimal($kontosum);
				print "<td align=right>$tmp<br></td>";
				print "</tr>";
			}
		}
		if ($primoprint==0) {
			$tmp=dkdecimal($kontosum);
			print "<tr><td><br></td><td><br></td><td><br></td><td>Primosaldo<br></td><td><br></td><td><br></td><td><br></td><td align=right>$tmp<br></td></tr>";
		}
	}
	print "<tr><td colspan=8><hr></td></tr>";
	print "</tbody></table>";
}
function kontosaldo($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til)
{
#	global $connection;
	global $top_bund;
	global $md;
	global $returside;
	global $popup;
	global $bgcolor;
	global $bgcolor5;
	
	$kilde=if_isset($_GET['kilde']);
	$kilde_kto_fra=if_isset($_GET['kilde_kto_fra']);
	$kilde_kto_til=if_isset($_GET['kilde_kto_til']);
	if ($popup) $returside="../includes/luk.php";
	elseif ($kilde=='openpost') $returside="rapport.php?rapportart=openpost&submit=ok&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$kilde_kto_fra&konto_til=$kilde_kto_til";
	else $returside="rapport.php?regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til";
	$returside="rapport.php?regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til";

	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk
	$currentdate=date("Y-m-d");
	if (strlen($maaned_fra)>2) {
		list ($x, $maaned_fra) = split(" ", $maaned_fra);
		list ($x, $maaned_til) = split(" ", $maaned_til);
		if (strlen($maaned_fra)>2) $luk= "<a accesskey=L href=\"rapport.php?rapportart=Kontosaldo&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til\">";
		else $luk= "<a accesskey=L href=\"$returside\">";
	}
	else $luk= "<a accesskey=L href=\"$returside\">";
	
	
 # $maaned_fra=$maaned_fra*1;
 # $maaned_til=$maaned_til*1;

	$maaned_fra=trim($maaned_fra);
	$maaned_til=trim($maaned_til);

	
	print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	print "<tr><td colspan=\"8\" height=\"8\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
	print "<td width=\"10%\" $top_bund>$luk Luk</a></td>";
	print "<td width=\"80%\" $top_bund>Debitorrapport - Kontosaldo</td>";
	print "<td width=\"10%\" $top_bund><br></td>";
	print "</tbody></table>"; #B slut
	print "</td></tr>";

	for ($x=1; $x<=12; $x++) {
		if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
		if ($maaned_til==$md[$x]){$maaned_til=$x;}
		if (strlen($maaned_fra)==1){$maaned_fra="0".$maaned_fra;}
		if (strlen($maaned_til)==1){$maaned_til="0".$maaned_til;}
	}

	$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$startmaaned=$row[box1]*1;
	$startaar=$row[box2]*1;
	$slutmaaned=$row[box3]*1;
	$slutaar=$row[box4]*1;
	$slutdato=31;

	if ($maaned_fra) {$startmaaned=$maaned_fra;}
	if ($maaned_til) {$slutmaaned=$maaned_til;}

#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}
	
	while (!checkdate($slutmaaned,$slutdato,$slutaar)) {
		$slutdato=$slutdato-1;
		if ($slutdato<28) break;
	}

	$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;
	#$regnslut = "2005-05-15";
#	print "<tr><td colspan=5>Firmanavn</td></tr>";
	$kontonr=array();
	$x=0;
	$query = db_select("select id from adresser where ".nr_cast('kontonr').">='$konto_fra' and ".nr_cast('kontonr')."<='$konto_til' and art = 'D' order by firmanavn",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$x++;
		$konto_id[$x]=$row[id];
	}
	$kto_id=array();
	$kontoantal=$x;
	$x=0;
	# finder alle konti med bevaegelser i den anfoerte periode eller aabne poster fra foer perioden
	for ($y=1;$y<=$kontoantal;$y++) {
		if ($regnaar) $query = db_select("select amount from openpost where transdate<='$regnslut' and konto_id='$konto_id[$y]'",__FILE__ . " linje " . __LINE__);
		else $query = db_select("select amount from openpost where konto_id='$konto_id[$y]'",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if (!in_array($konto_id[$y],$kto_id)) {
				$x++;
				$kto_id[$x]=$konto_id[$y];
			}
		}
	}
	$kontoantal=$x;
	
#	 print "<tr><td colspan=8><hr></td></tr>";
#	print "<tr><td width=10%> Dato</td><td width=10%> Bilag</td><td width=50%> Tekst</td><td width=10% align=right> Debet</td><td width=10% align=right> Kredit</td><td width=10% align=right> Saldo</td></tr>";

	for ($x=1; $x<=$kontoantal; $x++) {
		$r = db_fetch_array(db_select("select	* from adresser where id=$kto_id[$x]",__FILE__ . " linje " . __LINE__));
		$kontonr[$x]=stripslashes($r['kontonr']);
		$firmanavn[$x]=stripslashes($r['firmanavn']);
		$kontosum[$x]=0;
		$primo[$x]=0;
		$primoprint[$x]=0;
		$bgcolor='';
		if ($regnaar) $q2 = db_select("select * from openpost where konto_id=$kto_id[$x] and transdate<='$regnslut' order by transdate",__FILE__ . " linje " . __LINE__);
		else $q2 = db_select("select * from openpost where konto_id=$kto_id[$x] order by transdate",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
# -> 2009.05.05			
			$amount=afrund($r2['amount'],2);
			$oppvaluta=$r2['valuta'];
			if (!$oppvaluta) $oppvaluta='DKK';
			$oppkurs=$r2['valutakurs']*1;
			if (!$oppkurs) $oppkurs=100;
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
			print "<td align=right> $tmp</td></tr>";
}
	}
			$tmp=dkdecimal($totalsum);
			print "<tr><td colspan=\"3\"><hr></td></tr>";
			print "<tr><td><b>ialt</b></td><td  colspan=\"3\" align=\"right\"><b>$tmp</b><td></tr>";
	print "</tbody></table>";
		
}

function find_maaned_nr($maaned) {
	$maaned=trim($maaned);
	$aar=date("Y");
	if (is_numeric($maaned)) return($aar." ".$maaned); 
	
	if (strstr($maaned," ")) list($aar,$maaned)=split(" ",$maaned);
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
function find_maaned_navn($maaned) {

	$maaned=trim($maaned);
	if (is_numeric($maaned)) return($maaned);
	if (strstr($maaned," ")) {
		list($tmp1,$tmp2)=split(" ",$maaned);
		if ($tmp1 <= 12) {
			$maaned=$tmp1;
			$aar=$tmp2;
		}
		else {
			$aar=$tmp1;
			$maaned=$tmp2;
		}
	} 
	if ($maaned==1) $maaned="januar";	
	elseif ($maaned==2) $maaned="februar";	
	elseif ($maaned==3) $maaned="marts";	
	elseif ($maaned==4) $maaned="april";	
	elseif ($maaned==5) $maaned="maj";	
	elseif ($maaned==6) $maaned="juni";	
	elseif ($maaned==7) $maaned="juli";	
	elseif ($maaned==8) $maaned="august";	
	elseif ($maaned==9) $maaned="september";	
	elseif ($maaned==10) $maaned="oktober";	
	elseif ($maaned==11) $maaned="november";	
	else $maaned="december";	
	
	return($maaned);
}
?>
	
</html>

