<?php

// -------------finans/rapport.php-------lap 3.3.4------2013-09-29-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2013 DANOSOFT ApS
// ----------------------------------------------------------------------

// 20120927 Hvis budgettal indsat og konto lukket blev konto alligevel vist under budget
// 20130210 Break ændret til break 1
// 20130918	Diverse tilretninger til simulering - Søg $simulering
// 20130919	Fejl i søgefunktion ved opdelte projektnumre. Søg 20130919

$title="Finansrapport";
@session_start();
$s_id=session_id();

$title="Finansrapport";
$modulnr=4;
$css="../css/standard.css";

include("../includes/var_def.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print "<div><center>";

if ($_POST){

	$submit=str2low(trim($_POST['submit']));
	if (!$popup) {
		if ($submit=='kontrolspor') {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=kontrolspor.php\">";
			exit;
		} elseif ($submit=='provisionsrapport') {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=provisionsrapport.php\">";
			exit;
		}
	}
	$rapportart=if_isset($_POST['rapportart']);
	$aar_fra=if_isset($_POST['aar_fra']);
	$aar_til=if_isset($_POST['aar_til']);
	$maaned_fra=if_isset($_POST['maaned_fra']);
	$maaned_til=if_isset($_POST['maaned_til']);
	$dato_fra=if_isset($_POST['dato_fra']);
	$dato_til=if_isset($_POST['dato_til']);
	$md=if_isset($_POST['md']);
	$ansat_id=if_isset($_POST['ansat_id']);
	$ansat_init=if_isset($_POST['ansat_init']);
	$antal_ansatte=if_isset($_POST['antal_ansatte']);
	$ansat_fra=if_isset($_POST['ansat_fra']);
	$projekt_fra=if_isset($_POST['projekt_fra']);
	$projekt_til=if_isset($_POST['projekt_til']);
	$simulering=if_isset($_POST['simulering']);
	

#cho "prj_navn_fra $prj_navn_fra -> $projekt_fra<br>";
	if ( stristr($rapportart,"Listeangivelse") ) {
			$kvartal=preg_replace('/[^0-9.]*/','',$rapportart);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=listeangivelse.php?kvartal=$kvartal\">";
			exit;
	}	

	if ($ansat_fra) {
		list ($tmp, $tmp2) = explode(":", $ansat_fra);
		$tmp=trim($tmp);
		for ($x=1; $x<=$antal_ansatte; $x++) {
			if ($tmp==$ansat_init[$x]) {
				$ansat_fra=$ansat_id[$x];
				$ansat_init_fra=$ansat_init[$x];
				$ansatte=$tmp;
			}
		}
	}
	$ansat_til=if_isset($_POST['ansat_til']);
	if ($ansat_til) {
		$ansatte_id=$ansat_fra;
		list ($tmp, $tmp2) = explode(":", $ansat_til);
		$tmp=trim($tmp);
		for ($x=1; $x<=$antal_ansatte; $x++) {
			if ($tmp==$ansat_init[$x]) {
				$ansat_til=$ansat_id[$x];
				if ($ansat_init_fra!=$tmp) {
					$ansatte=$ansatte.",".$tmp;
					$ansatte_id=$ansatte_id.",".$ansat_id[$x];
				}
				$x=$antal_ansatte;
			} elseif ($ansat_init[$x]>$ansat_init_fra) {
				$ansatte=$ansatte.",".$ansat_init[$x];
				$ansatte_id=$ansatte_id.",".$ansat_id[$x];
			}
		}
	}
	$afd=if_isset($_POST['afd']);
	if ($afd) {
		list ($afd, $afd_navn) = explode(":", $afd);
		$afd=trim($afd);
	}
	$delprojekt=if_isset($_POST['delprojekt']);
	if ($projekt_til) $delprojekt=NULL;	 
	else {
		$find=0; #20130919 +næste 5 linjer
		for ($a=0;$a<count($delprojekt);$a++) {
			if ($delprojekt[$a]) $find=1;
		}
	}
	if ($find) {
		$prj_cfg=if_isset($_POST['prj_cfg']);
		$prcfg=explode("|",$prj_cfg);
		$b=count($delprojekt);
		$projekt_fra=NULL;
		for ($a=0;$a<$b;$a++) {
			$c=strlen($delprojekt[$a]);
			if ($c>$prcfg[$a]) $delprojekt[$a]=mb_substr($delprojekt[$a],0,$prcfg[$a],$db_encode);
			for($d=$c;$d<$prcfg[$a];$d++) {
				$delprojekt[$a]="?".$delprojekt[$a];  
			}
			$projekt_fra.=$delprojekt[$a];
		}
		$projekt_til=$projekt_fra;
	} else {
		$projekt_fra=if_isset($_POST['projekt_fra']);
		if (strpos(":",$projekt_fra)) {
			list ($projekt_fra, $prj_navn_fra) = explode(":", $projekt_fra);
			$projekt_fra=trim($projekt_fra);
		}
		$projekt_til=if_isset($_POST['projekt_til']);
		if (strpos(":",$projekt_til)) {
			list ($projekt_til, $prj_navn_til) = explode(":", $projekt_til);
			$projekt_til=trim($projekt_til);
		}
		if ($projekt_fra && ! $prj_navn_fra) {
			$r=db_fetch_array(db_select("select beskrivelse from grupper where kodenr = '$projekt_fra'",__FILE__ . " linje " . __LINE__));
			$prj_navn_fra=$r['beskrivelse'];
		}
		if ($projekt_til && ! $prj_navn_til) {
			$r=db_fetch_array(db_select("select beskrivelse from grupper where kodenr = '$projekt_til'",__FILE__ . " linje " . __LINE__));
			$prj_navn_til=$r['beskrivelse'];
		}
		
	}
#cho "135 $projekt_fra $prj_navn_fra<br>";
#cho "135 $projekt_til $prj_navn_til<br>";
	$tmp=str_replace("?","",$projekt_fra);
	if (!$tmp) {
		$projekt_fra=NULL;
		$projekt_til=NULL;
	}
	$konto_fra=if_isset($_POST['konto_fra']);
	if ($konto_fra) list ($konto_fra, $beskrivelse) = explode(":", $konto_fra);
	$konto_til=if_isset($_POST['konto_til']);
	if ($konto_til) list ($konto_til, $beskrivelse) = explode(":", $konto_til);
	$regnaar=if_isset($_POST['regnaar']);
	if ($regnaar && !is_numeric($regnaar)) list ($regnaar, $beskrivelse)= explode("-", $regnaar);
} else {
	$rapportart=if_isset($_GET['rapportart']);
	$dato_fra=if_isset($_GET['dato_fra']);
	$dato_til=if_isset($_GET['dato_til']);
	$aar_fra=if_isset($_GET['aar_fra']);
	$aar_til=if_isset($_GET['aar_til']);
	$maaned_fra=if_isset($_GET['maaned_fra']);
	$maaned_til=if_isset($_GET['maaned_til']);
	$konto_fra=if_isset($_GET['konto_fra']);
	$konto_fra2=if_isset($_GET['konto_fra']);
	if ($konto_fra2) $konto_fra=$konto_fra2;
	$konto_til=if_isset($_GET['konto_til']);
	if (isset($_GET['regnaar'])) $regnaar=$_GET['regnaar'];
	$afd=if_isset($_GET['afd']);
	$ansat_fra=if_isset($_GET['ansat_fra']);
	$ansat_til=if_isset($_GET['ansat_til']);
	$projekt_fra=if_isset($_GET['projekt_fra']);
	$projekt_til=if_isset($_GET['projekt_til']);
	$simulering=if_isset($_GET['simulering']);
}
$md[1]="januar"; $md[2]="februar"; $md[3]="marts"; $md[4]="april"; $md[5]="maj"; $md[6]="juni"; $md[7]="juli"; $md[8]="august"; $md[9]="september"; $md[10]="oktober"; $md[11]="november"; $md[12]="december";

if ($submit != 'ok') $submit='forside';
elseif ($rapportart){
	if ($rapportart=="balance"||$rapportart=="resultat"||$rapportart=="budget"){
		if ($r = db_fetch_array(db_select("select kontonr from kontoplan where regnskabsaar='$regnaar' and kontotype='X'",__FILE__ . " linje " . __LINE__))) {
			if ($rapportart!="balance") {
				$konto_til=$r['kontonr']-1;
			}
			else $konto_fra=$r['kontonr']+1;
		} else print "<BODY onLoad=\"javascript:alert('Sideskiftkonto ikke defineret i kontoplan - Balance & Resultat kan ikke adskilles')\">";

		$submit="regnskab";
	} else $submit=str2low($rapportart);
}

if ($maaned_fra && (!$aar_fra||!$aar_til)) {
	list ($aar_fra, $maaned_fra) = explode(" ", $maaned_fra);
	list ($aar_til, $maaned_til) = explode(" ", $maaned_til);
}
#cho "186 $projekt_fra<br>";
$submit($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,$simulering);
##################################################################################################
function forside($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,$simulering){
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
	if (!$regnaar) {
#cho "select regnskabsaar from brugere where brugernavn = '$brugernavn'<br>";
		$query = db_select("select regnskabsaar from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$regnaar = $row['regnskabsaar'];
#cho "regnaar $regnaar<br>";
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
	if ($rapportart=='balance'||!$konto_fra){$konto_fra=$kontonr[1]." : ".$konto_beskrivelse[1];}
	if (($rapportart=='resultat'||$rapportart=='budget')||!$konto_til){$konto_til=$kontonr[$antal_konti]." : ".$konto_beskrivelse[$antal_konti];}

	$query = db_select("select * from grupper where art='AFD' order by kodenr",__FILE__ . " linje " . __LINE__);
	$x=0;
	while ($row = db_fetch_array($query)) {
		$x++;
		$afdeling[$x]=$row['kodenr'];
		$afd_navn[$x]=$row['beskrivelse'];
		if ($afd == $afdeling[$x]) {$afd = $afdeling[$x]." : ".$afd_navn[$x];}
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
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; #A
	if ($menu=='T') {
		$leftbutton="<a title=\"Klik her for at komme til forsiden kladdelisten\" href=\"../index/menu.php\" accesskey=\"L\">LUK</a>";
		$rightbutton="";
		include("../includes/topmenu.php");
	} elseif ($menu=='S') {
		include("../includes/sidemenu.php");
	} else {
		print "<tr>";
#	print "<table width=\"100%\" align=\"center\" border=\"10\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund>";
		if ($popup) print "<a href=../includes/luk.php accesskey=L>Luk</a></td>";
		else print "<a href=../index/menu.php accesskey=L>Luk</a></td>";
		print "<td width=\"80%\" $top_bund> Finansrapport - forside </td>";
		print "<td width=\"10%\" $top_bund><br></td>";
	}
#	print "</tbody></table>"; #B slut
	print "</tr><tr><td height=99%></td><td align=center>\n\n";
	print "<form name='regnskabsaar' action='rapport.php' method='post'>\n";
	print "<table cellpadding=\"1\" cellspacing=\"5\" border=\"1\" align=\"center\"><tbody>\n"; #C
	print "<tr><td align=center><h3>  Finansrapport</font><br></h3></td></tr>\n";
	print "<td><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=100% align=center><tbody>\n"; #D
	print "<tr><td>Regnskabs&aring;r</td><td><select name='regnaar'>\n";
	print "<option>$regnaar. - $regn_beskrivelse[$aktiv]</option>\n";
	for ($x=1; $x<=$antal_regnaar;$x++) {
		if ($x!=$aktiv) {print "<option>$regn_kode[$x] - $regn_beskrivelse[$x]</option>\n";}
	}
	print "</select></td><td><input type=submit value=\"Opdat&eacute;r\" name=\"submit\"></td></tr>\n";
	print "</form>\n\n";
	print "<form name=rapport action=rapport.php method=post>\n";
	if ($r=db_fetch_array(db_select("select id from kladdeliste where bogfort='S'",__FILE__ . " linje " . __LINE__))) {
		print "<tr><td title=\"Medtag simulerede kladder i rapporter\">Simulering</td><td title=\"Medtag simulerede kladder i rapporter\"><input type=\"checkbox\" name=\"simulering\" $simulering></td></tr>";
	}
	print "</tr><td>Rapporttype</td><td><select name=rapportart>\n";
	if ($rapportart=="kontokort") print "<option title=\"".findtekst(509,$sprog_id)."\" value=\"kontokort\">".findtekst(515,$sprog_id)."</option>\n";
	elseif ($rapportart=="kontokort_moms") print "<option title=\"".findtekst(510,$sprog_id)."\" value=\"kontokort_moms\">".findtekst(516,$sprog_id)."</option>\n";
	elseif ($rapportart=="balance") print "<option title=\"".findtekst(511,$sprog_id)."\" value=\"balance\">".findtekst(517,$sprog_id)."</option>\n";
	elseif ($rapportart=="resultat") print "<option title=\"".findtekst(512,$sprog_id)."\" value=\"resultat\">".findtekst(518,$sprog_id)."</option>\n";
	elseif ($rapportart=="budget") print "<option title=\"".findtekst(513,$sprog_id)."\" value=\"budget\">".findtekst(519,$sprog_id)."</option>\n";
	elseif ($rapportart=="momsangivelse") print "<option title=\"".findtekst(514,$sprog_id)."\" value=\"momsangivelse\">".findtekst(520,$sprog_id)."</option>\n";
	listeangivelser($regnaar, $rapportart, "matcher");
	if ($rapportart!="kontokort") print "<option title=\"".findtekst(509,$sprog_id)."\" value=\"kontokort\">".findtekst(515,$sprog_id)."</option>\n";
	if ($rapportart!="kontokort_moms") print "><option title=\"".findtekst(510,$sprog_id)."\" value=\"kontokort_moms\">".findtekst(516,$sprog_id)."</option>\n";
	if ($rapportart!="balance") print "<option title=\"".findtekst(511,$sprog_id)."\" value=\"balance\">".findtekst(517,$sprog_id)."</option>\n";
	if ($rapportart!="resultat") print "<option title=\"".findtekst(512,$sprog_id)."\" value=\"resultat\">".findtekst(518,$sprog_id)."</option>\n";
	if ($rapportart!="budget") print "<option title=\"".findtekst(513,$sprog_id)."\" value=\"budget\">".findtekst(519,$sprog_id)."</option>\n";
	if ($rapportart!="momsangivelse") print "<option title=\"".findtekst(514,$sprog_id)."\" value=\"momsangivelse\">".findtekst(520,$sprog_id)."</option>\n";
	listeangivelser($regnaar, $rapportart, "alle andre");

	print "</select></td><tr>\n\n";
	if ($antal_afd) {
		print "<tr><td>  Afdeling</td><td><select name=afd>\n";
		print "<option>$afd</option>\n";
		if ($afd) {print "<option></option>\n";}
		for ($x=1; $x<=$antal_afd; $x++) {
			 if ($afd != $afdeling[$x]) {print "<option>$afdeling[$x] : $afd_navn[$x]</option>\n";}
		}
		print "</select></td></tr>";
	}
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
	print "<option>$aar_fra $maaned_fra</option>\n";
	$x=$start_md[$aktiv]-1;
	$z=$start_aar[$aktiv];
	for ($y=1; $y <= $antal_mdr; $y++) {
		if ($x>=12) { 
			$z++;
			$x=1;
		} else $x++;
		print "<option>$z $md[$x]</option>\n";
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
	print "<option>$aar_til $maaned_til</option>\n";
	$x=$start_md[$aktiv]-1;
	$z=$start_aar[$aktiv];
	for ($y=1; $y <= $antal_mdr; $y++) {
		if ($x>=12) { 
			$z++;
			$x=1;
		} else $x++;
		print "<option>$z $md[$x]</option>\n";
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
	print "<input type=hidden name=regnaar value=$regnaar>\n";
	print "<tr><td colspan=3 align=center><input type=submit value=\" OK \" name=\"submit\"></td></tr>\n";
	print "</tbody></table>\n"; #D
	print "</td></tr><tr>";
	print "<td colspan=3 ALIGN=center><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>\n"; #E
	if ($popup) {
		print "<tr><td colspan=3 ALIGN=center onClick=\"javascript:kontrolspor=window.open('kontrolspor.php','kontrolspor','scrollbars=1,resizable=1');kontrolspor.focus();\"><span title='Vilk&aring;rlig s&oslash;gning i transaktioner'><input type=submit value=\"Kontrolspor\" name=\"submit\"></span></td></tr>";
		print "<tr><td colspan=3 ALIGN=center onClick=\"javascript:provisionsrapport=window.open('provisionsrapport.php','provisionsrapport','scrollbars=1,resizable=1');provisionsrapport.focus();\"><span title='Rapport over medarbejdernes provisionsindtjening'><input type=submit value=\"Provisionsrapport\" name=\"submit\"></span></td></tr>";
	} else {
		print "<tr><td colspan=3 ALIGN=center><span title='Vilk&aring;rlig s&oslash;gning i transaktioner'><input type=submit value=\"Kontrolspor\" name=\"submit\"></span></td></tr>";
		print "<tr><td colspan=3 ALIGN=center><span title='Rapport over medarbejdernes provisionsindtjening'>  <input type=submit value=\"Provisionsrapport\" name=\"submit\"></span></td></tr>";
	} 
	print "</form>\n";
	print "</tbody></table>\n"; #E
	print "</td></tr>";
	print "</tbody></table>\n"; #C slut
	print "</td></tr>";
	print "</tbody></table>\n"; #C slut


}
# endfunc forside
#################################################################################################
function kontokort($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,$simulering) {

	global $connection;
	global $top_bund;
	global $md;
	global $ansatte;
	global $ansatte_id;
	global $afd_navn;
	global $prj_navn_fra;
	global $prj_navn_til;
	global $bgcolor;
	global $bgcolor4;
	global $bgcolor5;
	global $menu;
	
#cho "493 $prj_navn_fra :: $prj_navn_til<br>";
#cho "494 $projekt_fra :: $projekt_til<br>";

	$query = db_select("select firmanavn from adresser where art='S'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {$firmanavn=$row['firmanavn'];}

	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk

#	list ($aar_fra, $maaned_fra) = explode(" ", $maaned_fra);
#	list ($aar_til, $maaned_til) = explode(" ", $maaned_til);

	$maaned_fra=trim($maaned_fra);
	$maaned_til=trim($maaned_til);
	$aar_fra=trim($aar_fra);
	$aar_til=trim($aar_til);

	$konto_fra=trim($konto_fra);
	$konto_til=trim($konto_til);
	
	$mf=$maaned_fra;
	$mt=$maaned_til;

	for ($x=1; $x<=12; $x++){
		if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
		if ($maaned_til==$md[$x]){$maaned_til=$x;}
		if (strlen($maaned_fra)==1){$maaned_fra="0".$maaned_fra;}
		if (strlen($maaned_til)==1){$maaned_til="0".$maaned_til;}
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
	$regnaarstart= $startaar. "-" . $startmaaned . "-" . '01';
	
	if ($aar_fra) {$startaar=$aar_fra;}
	if ($aar_til) {$slutaar=$aar_til;}
	if ($maaned_fra) {$startmaaned=$maaned_fra;}
	if ($maaned_til) {$slutmaaned=$maaned_til;}
	if ($dato_fra) {$startdato=$dato_fra;}
	if ($dato_til) {$slutdato=$dato_til;}

	while (!checkdate($startmaaned,$startdato,$startaar)) {
		$startdato=$startdato-1;
		if ($startdato<28) break 1;
	}
	
	while (!checkdate($slutmaaned,$slutdato,$slutaar)) {
		$slutdato=$slutdato-1;
		if ($slutdato<28) break 1;
	}

	$regnstart = $startaar. "-" . $startmaaned . "-" . $startdato;
	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;


#	print "  <a accesskey=L href=\"rapport.php?rapportart=Kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&dato_til=$slutdato&maaned_til=$mt&konto_fra=$konto_fra&konto_til=$konto_til&afd=$afd\">Luk</a><br><br>";

	print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	if ($menu=='T') {
		$leftbutton="<a title=\"Klik her for at komme til forsiden af rapporter\" href=\"rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering\" accesskey=\"L\">LUK</a>";
		$rightbutton="";
		include("../includes/topmenu.php");
	} elseif ($menu=='S') {
		include("../includes/sidemenu.php");
	} else {
		print "<tr><td colspan=\"6\" height=\"8\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund><a accesskey=L href=\"rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering\">Luk</a></td>";
		print "<td width=\"80%\" $top_bund> Rapport - kontokort </td>";
		print "<td width=\"10%\" $top_bund><br></td>";
		print "</tbody></table>"; #B slut
		print "</td></tr>";
		($simulering)?$tmp="Simuleret kontokort":$tmp="Kontokort";
		print "<tr><td colspan=\"4\"><big><big><big>  $tmp</span></big></big></big></td>";
	}
	print "<td colspan=2 align=right><table style=\"text-align: left; width: 100%;\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody><tr>";
	print "<td>Regnskabs&aring;r</span></td>";
	print "<td>$regnaar.</span></td></tr>";
	print "<tr><td>Periode</span></td>";
	## Finder start og slut paa regnskabsaar
	if ($startdato < 10) $startdato="0".$startdato;	
	print "<td>Fra ".$startdato.". $mf<br />Til ".$slutdato.". $mt</span></td></tr>";
	if ($ansat_fra) {
		if (!$ansat_til || $ansat_fra==$ansat_til) print "<tr><td>Medarbejder</span></td><td>$ansatte</span></td></tr>";
		else print "<tr><td>Medarbejdere</span></td><td>$ansatte</span></td></tr>";
	}
	if ($afd) print "<tr><td>Afdeling</span></td><td>$afd_navn</span></td></tr>";
#cho "586 $projekt_fra $projekt_til<br>";
	if ($projekt_fra) {
		print "<td>Projekt:</td><td>";
#		print "<tr><td>Projekt $prj_navn_fra</td>";
		if (!strstr($projekt_fra,"?")) {
			if ($projekt_til && $projekt_fra != $projekt_til) print "Fra: $projekt_fra, $prj_navn_fra<br>Til : $projekt_til, $prj_navn_til";
			else print "$projekt_fra, $prj_navn_fra"; 
		} else print "$projekt_fra, $prj_navn_fra";
		print "</td></tr>";
	}
	print "</tbody></table></td></tr>";
	print "<tr><td colspan=5><big><b>$firmanavn</b></big></td></tr>";
	
	
	$dim='';
	if ($afd||$ansat_fra||$projekt_fra) {
		if ($afd) $dim = "and afd = $afd ";
		if ($ansat_fra && $ansat_til) {
			$tmp=str_replace(","," or ansat=",$ansatte_id);
			$dim = $dim." and (ansat=$tmp) ";
		}
		elseif ($ansat_fra) $dim = $dim."and ansat = '$ansat_fra' ";
		$projekt_fra=str2low($projekt_fra);
		$projekt_til=str2low($projekt_til);
#cho "610 $projekt_fra $projekt_til<br>";
		if ($projekt_fra && $projekt_til && $projekt_fra!=$projekt_til) $dim = $dim." and lower(projekt) >= '$projekt_fra' and lower(projekt) <= '$projekt_til' ";
		elseif ($projekt_fra) {
			$tmp=str_replace("?","_",$projekt_fra);
			if (substr($tmp,-1)=='_') {
				while (substr($tmp,-1)=='_') $tmp=substr($tmp,0,strlen($tmp)-1);
				$tmp=str2low($tmp)."%";
			}
			$dim = $dim."and lower(projekt) LIKE '$tmp' ";
		}
	}	
	$x=0;
	$qtxt="select * from kontoplan where regnskabsaar='$regnaar' and kontonr>='$konto_fra' and kontonr<='$konto_til' order by kontonr";
#cho "$qtxt<br>";
	$q= db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)){
		$kontonr[$x]=$row['kontonr']*1;
		$kontobeskrivelse[$x]=$row['beskrivelse'];
		$kontomoms[$x]=$row['moms'];
		if (!$dim && $row['kontotype']=="S") $primo[$x]=afrund($row['primo'],2);
		else $primo[$x]=0;
		$x++;
	}
	$ktonr=array();
	$x=0;
	$qtxt = "select distinct(kontonr) as kontonr from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' and kontonr>='$konto_fra' and kontonr<='$konto_til' $dim";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ktonr[$x]=$r['kontonr'];
		$x++;
	}
	if ($simulering) {
		$qtxt = "select distinct(kontonr) as kontonr from simulering where transdate>='$regnstart' and transdate<='$regnslut' and kontonr>='$konto_fra' and kontonr<='$konto_til' $dim";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (!in_array($r['kontonr'],$ktonr)) {
				$ktonr[$x]=$r['kontonr'];
				$x++;
			}
		}
	}
	sort($kontonr);
	$kontosum=0;
	$founddate=false;
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr><td width=10%>  Dato</td><td width=10%>  Bilag </td><td width=50%>  Tekst </td><td width=10% align=right>  Debet </td><td width=10% align=right>  Kredit </td><td width=10% align=right>  Saldo </td></tr>";
	
	for ($x=0;$x<count($kontonr);$x++){
		$linjebg=$bgcolor5;
		if (in_array($kontonr[$x],$ktonr)||$primo[$x]){
			print "<tr><td colspan=6><hr></td></tr>";
			print "<tr bgcolor=\"$bgcolor5\"><td></td><td></td><td colspan=4>$kontonr[$x] : $kontobeskrivelse[$x] : $kontomoms[$x]</tr>";
			print "<tr><td colspan=6><hr></td></tr>";
			$kontosum=$primo[$x];
			$query = db_select("select debet, kredit from transaktioner where kontonr=$kontonr[$x] and transdate>='$regnaarstart' and transdate<'$regnstart' $dim order by transdate,bilag,id",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)){
			 	$kontosum= $kontosum+afrund($row['debet'],2)-afrund($row['kredit'],2);
			}
			$query = db_select("select debet, kredit from simulering where kontonr=$kontonr[$x] and transdate>='$regnaarstart' and transdate<'$regnstart' $dim order by transdate,bilag,id",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)){
			 	$kontosum= $kontosum+afrund($row['debet'],2)-afrund($row['kredit'],2);
			}
			$tmp=dkdecimal($kontosum);
			if (!$dim) print "<tr bgcolor=\"$linjebg\"><td></td><td></td><td>  Primosaldo </td><td></td><td></td><td align=right>$tmp </td></tr>";
			$print=1;
			$tr=0;
			$transdate=array();
			$query = db_select("select * from transaktioner where kontonr=$kontonr[$x] and transdate>='$regnstart' and transdate<='$regnslut' $dim order by transdate,bilag,id",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)){
				$transdate[$tr]=$row['transdate'];
				$bilag[$tr]=$row['bilag'];
				$beskrivelse[$tr]=$row['beskrivelse'];
				$debet[$tr]=$row['debet'];
				$kredit[$tr]=$row['kredit'];
				$tr++;
			}
			if ($simulering) {
				$sim=0;
				$sim_kontonr=array();
				$q = db_select("select * from simulering where kontonr=$kontonr[$x] and transdate>='$regnstart' and transdate<='$regnslut' $dim order by transdate,bilag,id",__FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)){
					$sim_id[$sim]=$r['id'];
					$sim_transdate[$sim]=$r['transdate'];
					$sim_bilag[$sim]=$r['bilag'];
					$sim_kontonr[$sim]=$r['kontonr'];
					$sim_beskrivelse[$sim]=$r['beskrivelse'];
					$sim_debet[$sim]=$r['debet'];
					$sim_kredit[$sim]=$r['kredit'];
#					if (!in_array($sim_transdate[$sim],$transdate)) {
					$a=0;
					while($a<=count($transdate) and $sim_transdate[$sim]>$transdate[$a]) {
#						for ($a=0;$a<=count($transdate);$a++) {
#cho "$a Pre start $sim_transdate[$sim] | $transdate[$a]<br>";	
#							if ($sim_transdate[$sim]<$transdate[$a]) {
#cho "$a Starter $sim_transdate[$sim]>$transdate[$a]<br>";	
#								break 1;
#							}
$a++;
}
#					}	
						for ($b=count($transdate);$b>$a;$b--) {

#cho "$b 1: $transdate[$b] ".$transdate[$b-1]." $sim_transdate[$sim]<br>";
							$transdate[$b]=$transdate[$b-1];
#cho "$b 2: $transdate[$b] ".$transdate[$b-1]." $sim_transdate[$sim]<br>";
							$bilag[$b]=$bilag[$b-1];
							$beskrivelse[$b]=$beskrivelse[$b-1];
							$debet[$b]=$debet[$b-1];
							$kredit[$b]=$kredit[$b-1];
						}
						
						$transdate[$b]=$sim_transdate[$sim];
#cho "$b 3: $transdate[$b] ".$transdate[$b-1]." $sim_transdate[$sim]<br>";
						$bilag[$b]=$sim_bilag[$sim];
						$beskrivelse[$b]=$sim_beskrivelse[$sim]."(Simuleret)";
						$debet[$b]=$sim_debet[$sim];
						$kredit[$b]=$sim_kredit[$sim];
						$sim_transdate[$sim]=NULL;
#						break 1;
					
					$sim++;
				}
			}	
			for ($tr=0;$tr<count($transdate)+count($sim_transdate);$tr++) {		
				if ($transdate[$tr]) {
					($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
					print "<tr bgcolor=\"$linjebg\"><td>  ".dkdato($transdate[$tr])." </td><td>$bilag[$tr] </td><td>$kontonr[$x] : $beskrivelse[$tr] </td>";
					$tmp=dkdecimal($debet[$tr]);
					print "<td align=right>$tmp </td>";
					$tmp=dkdecimal($kredit[$tr]);
					print "<td align=right>$tmp </td>";
					$kontosum=$kontosum+afrund($debet[$tr],2)-afrund($kredit[$tr],2);
					$tmp=dkdecimal($kontosum);
					print "<td align=right>$tmp </td></tr>";
				} 
/*
				if (in_array($kontonr[$x],$sim_kontonr) && ($transdate[$tr]!=$transdate[$tr+1])) {
				for ($sim=0;$sim<count($sim_kontonr);$sim++) {
						if ($kontonr[$x]==$sim_kontonr[$sim] && ($transdate[$tr] == $sim_transdate[$sim])) {
							($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
							print "<tr bgcolor=\"$linjebg\"><td>  ".dkdato($sim_transdate[$sim])." </td><td>$sim_bilag[$sim] </td><td>$sim_kontonr[$sim] : $sim_beskrivelse[$sim] (simuleret) </td>";
							$tmp=dkdecimal($sim_debet[$sim]);
							print "<td align=right>$tmp </td>";
							$tmp=dkdecimal($sim_kredit[$sim]);
							print "<td align=right>$tmp </td>";
							$kontosum=$kontosum+afrund($sim_debet[$sim],2)-afrund($sim_kredit[$sim],2);
							$tmp=dkdecimal($kontosum);
							print "<td align=right>$tmp </td></tr>";
						}
					}
				}
*/				
			}
		}
	}
	print "<tr><td colspan=6><hr></td></tr>";
	print "</tbody></table>";
}
#################################################################################################
function kontokort_moms($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,$simulering) {

	global $connection;
	global $top_bund;
	global $md;
	global $ansatte;
	global $ansatte_id;
	global $afd_navn;
	global $prj_navn_fra;
	global $prj_navn_til;
	global $bgcolor;
	global $bgcolor4;
	global $bgcolor5;
	global $menu;
	
	$query = db_select("select firmanavn from adresser where art='S'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {$firmanavn=$row['firmanavn'];}

	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk

#	list ($aar_fra, $maaned_fra) = explode(" ", $maaned_fra);
#	list ($aar_til, $maaned_til) = explode(" ", $maaned_til);

	$maaned_fra=trim($maaned_fra);
	$maaned_til=trim($maaned_til);
	$aar_fra=trim($aar_fra);
	$aar_til=trim($aar_til);

	$konto_fra=trim($konto_fra);
	$konto_til=trim($konto_til);
	
	$mf=$maaned_fra;
	$mt=$maaned_til;

	for ($x=1; $x<=12; $x++){
		if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
		if ($maaned_til==$md[$x]){$maaned_til=$x;}
		if (strlen($maaned_fra)==1){$maaned_fra="0".$maaned_fra;}
		if (strlen($maaned_til)==1){$maaned_til="0".$maaned_til;}
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
	$regnaarstart= $startaar. "-" . $startmaaned . "-" . '01';
	
	if ($aar_fra) {$startaar=$aar_fra;}
	if ($aar_til) {$slutaar=$aar_til;}
	if ($maaned_fra) {$startmaaned=$maaned_fra;}
	if ($maaned_til) {$slutmaaned=$maaned_til;}
	if ($dato_fra) {$startdato=$dato_fra;}
	if ($dato_til) {$slutdato=$dato_til;}

	while (!checkdate($startmaaned,$startdato,$startaar)) {
		$startdato=$startdato-1;
		if ($startdato<28) break 1;
	}
	
	while (!checkdate($slutmaaned,$slutdato,$slutaar)) {
		$slutdato=$slutdato-1;
		if ($slutdato<28) break 1;
	}

	$regnstart = $startaar. "-" . $startmaaned . "-" . $startdato;
	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

	$x=0;
	$momsq=NULL;
	$q=db_select("select * from grupper where art='SM' or ART='KM' or art='EM' order by art");
	while ($r = db_fetch_array($q)){
		if (trim($r['box1'])) {
			$x++;
			$momsart[$x]=$r['kode'];
			$momskonto[$x]=trim($r['box1']);
			$momssats[$x]=$r['box2'];
			if (!strpos($momsq,$momskonto[$x])) {
				($momsq)?$momsq.=" or kontonr = '$momskonto[$x]'":$momsq.="and (kontonr = '$momskonto[$x]'"; 
			}	
		}
	}
	if ($momsq) $momsq.=")";
	$momsantal=$x;

#	print "  <a accesskey=L href=\"rapport.php?rapportart=Kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&dato_til=$slutdato&maaned_til=$mt&konto_fra=$konto_fra&konto_til=$konto_til&afd=$afd\">Luk</a><br><br>";

	print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	if ($menu=='T') {
		$leftbutton="<a title=\"Klik her for at komme til forsiden af rapporter\" href=\"rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering\" accesskey=\"L\">LUK</a>";
		$rightbutton="";
		include("../includes/topmenu.php");
	} elseif ($menu=='S') {
		include("../includes/sidemenu.php");
	} else {
		print "<tr><td colspan=\"6\" height=\"8\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund><a accesskey=L href=\"rapport.php?rapportart=kontokort_moms&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering\">Luk</a></td>";
		print "<td width=\"80%\" $top_bund> Rapport - kontokort men moms</td>";
		print "<td width=\"10%\" $top_bund><br></td>";
		print "</tbody></table>"; #B slut
		print "</td></tr>";
	}
	print "<tr><td colspan=\"4\"><big><big><big>".findtekst(516,$sprog_id)."</span></big></big></big></td>";

	print "<td colspan=2 align=right><table style=\"text-align: left; width: 100%;\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody><tr>";
	print "<td>Regnskabs&aring;r</span></td>";
	print "<td>$regnaar.</span></td></tr>";
	print "<tr><td>Periode</span></td>";
	## Finder start og slut paa regnskabsaar
	if ($startdato < 10) $startdato="0".$startdato;	
	print "<td>Fra ".$startdato.". $mf<br />Til ".$slutdato.". $mt</span></td></tr>";
	if ($ansat_fra) {
		if (!$ansat_til || $ansat_fra==$ansat_til) print "<tr><td>Medarbejder</span></td><td>$ansatte</span></td></tr>";
		else print "<tr><td>Medarbejdere</span></td><td>$ansatte</span></td></tr>";
	}
	if ($afd) print "<tr><td>Afdeling</span></td><td>$afd_navn</span></td></tr>";
	if ($projekt_fra) {
		print "<td>Projekt:</td><td>";
#		print "<tr><td>Projekt $prj_navn_fra</td>";
		if (!strstr($projekt_fra,"?")) {
			if ($projekt_til && $projekt_fra != $projekt_til) print "Fra: $projekt_fra, $prj_navn_fra<br>Til : $projekt_til, $prj_navn_til";
			else print "$projekt_fra, $prj_navn_fra"; 
		} else print "$projekt_fra, $prj_navn_fra";
		print "</td></tr>";
	}
	print "</tbody></table></td></tr>";
	print "<tr><td colspan=5><big><b>$firmanavn</b></big></td></tr>";
	
	$dim='';
	if ($afd||$ansat_fra||$projekt_fra) {
		if ($afd) $dim = "and afd = $afd ";
		if ($ansat_fra && $ansat_til) {
			$tmp=str_replace(","," or ansat=",$ansatte_id);
			$dim = $dim." and (ansat=$tmp) ";
		}
		elseif ($ansat_fra) $dim = $dim."and ansat = '$ansat_fra' ";
		$projekt_fra=str2low($projekt_fra);
		$projekt_til=str2low($projekt_til);
		if ($projekt_fra && $projekt_til && $projekt_fra!=$projekt_til) $dim = $dim." and lower(projekt) >= '$projekt_fra' and lower(projekt) <= '$projekt_til' ";
		elseif ($projekt_fra) {
			$tmp=str_replace("?","_",$projekt_fra);
			if (substr($tmp,-1)=='_') {
				while (substr($tmp,-1)=='_') $tmp=substr($tmp,0,strlen($tmp)-1);
				$tmp=str2low($tmp)."%";
			}
			$dim = $dim."and lower(projekt) LIKE '$tmp' ";
		}
	}	
	$x=0;$kontonr=array();
	$qtxt="select * from kontoplan where regnskabsaar='$regnaar' and kontonr>='$konto_fra' and kontonr<='$konto_til' order by kontonr";
#cho "$qtxt<br>";
	$q= db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)){
		if (!in_array($row['kontonr'],$kontonr) && (trim($row['moms']) || $simulering)) {
			$x++;
			$kontonr[$x]=$row['kontonr']*1;
#cho "$kontonr[$x]<br>";
			$kontobeskrivelse[$x]=$row['beskrivelse'];
			$kontomoms[$x]=$row['moms'];
			if (!$dim && $row['kontotype']=="S") $primo[$x]=afrund($row['primo'],2);
			else $primo[$x]=0;
		}
	}
	$kontoantal=$x;
	$ktonr=array();
	$x=0;
	$qtxt = "select kontonr,projekt from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' $dim order by transdate,bilag,id";
#cho "$qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)){
#cho "$row[projekt]<br>";
		if (!in_array($row['kontonr'],$ktonr)) {
			$x++;
			$ktonr[$x]=$row['kontonr'];
#cho "$ktonr[$x]<br>";
		}
	}
	$kontosum=0;

	$founddate=false;
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr><td width=10%>  Dato</td><td width=10%>  Bilag </td><td width=50%>  Tekst </td><td width=10% align=right>  Bel&oslash;b </td><td width=10% align=right>  Moms </td><td width=10% align=right>  Incl. moms </td></tr>";
	
	for ($x=1; $x<=$kontoantal; $x++){
		$linjebg=$bgcolor5;
		if (in_array($kontonr[$x], $ktonr)||$primo[$x]){
			print "<tr><td colspan=6><hr></td></tr>";
			print "<tr bgcolor=\"$bgcolor5\"><td></td><td></td><td colspan=4>$kontonr[$x] : $kontobeskrivelse[$x] : $kontomoms[$x]</tr>";
			print "<tr><td colspan=6><hr></td></tr>";
			$kontosum=$primo[$x];
			$query = db_select("select debet, kredit from transaktioner where kontonr=$kontonr[$x] and transdate>='$regnaarstart' and transdate<'$regnstart' $dim order by transdate,bilag,id",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)){
			 	$kontosum+=afrund($row['debet'],2)-afrund($row['kredit'],2);
			}
			$query = db_select("select debet, kredit from simulering where kontonr=$kontonr[$x] and transdate>='$regnaarstart' and transdate<'$regnstart' $dim order by transdate,bilag,id",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)){
			 	$kontosum+=afrund($row['debet'],2)-afrund($row['kredit'],2);
			}
#			$tmp=dkdecimal($kontosum);
#			if (!$dim) print "<tr bgcolor=\"$linjebg\"><td></td><td></td><td>  Primosaldo </td><td></td><td></td><td align=right>$tmp </td></tr>";
			$print=1;
			$sim=0;
#cho 			"select * from simulering where kontonr='$kontonr[$x]' and transdate>='$regnstart' and transdate<='$regnslut' $dim order by transdate,bilag,id<br>";
			$q = db_select("select * from simulering where kontonr=$kontonr[$x] and transdate>='$regnstart' and transdate<='$regnslut' $dim order by transdate,bilag,id",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)){
				$sim_transdate[$sim]=$r['transdate'];
				$sim_bilag[$sim]=$r['bilag'];
				$sim_kontonr[$sim]=$r['kontonr'];
				$sim_beskrivelse[$sim]=$r['beskrivelse'];
				$sim_xmoms[$sim]=$r['debet']-$r['kredit'];
				$sim_moms[$sim]=$r['moms'];
#cho "S $sim_kontonr[$sim]<br>";
				$sim++;
			}	
			$tr=0;$transdate=array();
			$q = db_select("select * from transaktioner where kontonr='$kontonr[$x]' and transdate>='$regnstart' and transdate<='$regnslut' $dim order by transdate,bilag,id",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)){
				$transdate[$tr]=$r['transdate'];
				$bilag[$tr]=$r['bilag'];
				$beskrivelse[$tr]=$r['beskrivelse'];
				$debet[$tr]=$r['debet'];
				$kredit[$tr]=$r['kredit'];
				$kladde_id[$tr]=$r['kladde_id'];
				$moms[$tr]=$r['moms'];
				$logdate[$tr]=$r['logdate'];
				$logtime[$tr]=$r['logtime'];

				$tr++;
			}
			for ($tr=0;$tr<count($transdate);$tr++) {		
				($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
				print "<tr bgcolor=\"$linjebg\"><td>  ".dkdato($transdate[$tr])." $kladde_id[$tr]</td><td onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:kassekladde=window.open('kassekladde.php?id=$kladde_id[$tr]&returside=../includes/luk.php','kassekladde','$jsvars')\">$bilag[$tr]</td><td>$kontonr[$x] : $beskrivelse[$tr]</td>";
				$xmoms=$debet[$tr]-$kredit[$tr];
				print "<td align=right>".dkdecimal($xmoms)."</td>";
#				$moms=$moms[$tr];
				if (!$moms[$tr] && $moms[$tr]!='0.000' && $bilag[$tr]&& $kladde_id[$tr]) {
					$q2=db_select("select * from transaktioner where transdate='$transdate[$tr]' and bilag='$bilag[$tr]' and logdate='$logdate[$tr]' and logtime='$logtime[$tr]'and beskrivelse='$beskrivelse[$tr]' $momsq",__FILE__ . " linje " . __LINE__);
					while ($r2=db_fetch_array($q2)){
						$amount=$r2['debet']-$r2['kredit'];
						for ($i=1;$i<=$momsantal;$i++) {
							$tmp=round(abs($xmoms-$amount*100/$momssats[$i]),2);
#cho "$r2[kontonr] == $momskonto[$i] && $tmp<0.1<br>";
							if ($r2['kontonr'] == $momskonto[$i] && $tmp<0.1) $moms=$amount; 
						}
					}
				}
				print "<td align=right>".dkdecimal($moms[$tr])."</td>";
				$mmoms=$xmoms+$moms[$tr];
				print "<td align=right>".dkdecimal($mmoms)."</td></tr>";
#cho "$kontonr[$x] - $transdate[$tr]<br>";
				if (in_array($kontonr[$x],$sim_kontonr) && $transdate[$tr]!=$transdate[$tr+1]) {
					for ($sim=0;$sim<count($sim_kontonr);$sim++) {
#cho "$kontonr[$x]==$sim_kontonr[$sim] && $transdate[$tr] == $sim_transdate[$sim]<br>";
						if ($kontonr[$x]==$sim_kontonr[$sim] && $transdate[$tr] == $sim_transdate[$sim]) {
							print "<tr bgcolor=\"$linjebg\"><td>  ".dkdato($sim_transdate[$sim])." </td><td>$sim_bilag[$sim] </td><td>$sim_kontonr[$sim] : $sim_beskrivelse[$sim] (simuleret) </td>";
							$tmp=dkdecimal($sim_debet[$sim]);
							print "<td align=right>$tmp </td>";
							$tmp=dkdecimal($sim_kredit[$sim]);
							print "<td align=right>$tmp </td>";
							$kontosum=$kontosum+afrund($sim_debet[$sim],2)-afrund($sim_kredit[$sim],2);
							$tmp=dkdecimal($kontosum);
							print "<td align=right>$tmp </td></tr>";
						}
					}
				}
			}
		}
	}
	print "<tr><td colspan=6><hr></td></tr>";
	print "</tbody></table>";
}
#################################################################################################
function regnskab($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,$simulering) {
	print "<!--Function regnskab start-->\n";
	global $connection;
	global $top_bund;
	global $md;
	global $ansatte;
	global $ansatte_id;
	global $afd_navn;
	global $prj_navn_fra;
	global $prj_navn_til;
	global $bgcolor;
	global $bgcolor4;
	global $bgcolor5;
	global $menu;

	$periodesum=array();
	$kto_periode=array();

#cho "942 $projekt_fra $prj_navn_fra - $projekt_til $prj_navn_til<br>"; 

	if ($rapportart=='budget') {
		$budget=1;
		$cols1=2;$cols2=3;$cols3=4;$cols4=5;$cols5=6;$cols6=7;
	} else {
		$budget=0;
		$cols1=1;$cols2=2;$cols3=3;$cols4=4;$cols5=5;$cols6=6;
	}

	if ($row = db_fetch_array(db_select("select firmanavn from adresser where art='S'",__FILE__ . " linje " . __LINE__))) {$firmanavn=$row['firmanavn'];}
	if (($afd)&&($row = db_fetch_array(db_select("select beskrivelse from grupper where art='AFD' and kodenr='$afd'",__FILE__ . " linje " . __LINE__)))) {$afd_navn=$row['beskrivelse'];}

	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk

	$maaned_fra=trim($maaned_fra);
	$maaned_til=trim($maaned_til);
	$konto_fra=trim($konto_fra);
	$konto_til=trim($konto_til);

	$mf=$maaned_fra;
	$mt=$maaned_til;

	for ($x=1; $x<=12; $x++){
		if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
		if ($maaned_til==$md[$x]){$maaned_til=$x;}
		if (strlen($maaned_fra)==1){$maaned_fra="0".$maaned_fra;}
		if (strlen($maaned_til)==1){$maaned_til="0".$maaned_til;}
		if (strlen($dato_fra)==1){$dato_fra="0".$dato_fra;}
		if (strlen($dato_til)==1){$dato_til="0".$dato_til;}
	}

	$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
#	$regnaar=$row[kodenr];
	$startmaaned=$row['box1']*1;
	$startaar=$row['box2']*1;
	$slutmaaned=$row['box3']*1;
	$slutaar=$row['box4']*1;
	$slutdato=31;

	if ($rapportart=='budget') {
		$startmd=$maaned_fra-$startmaaned+1;
		$slutmd=$maaned_til-$startmaaned+1;
		if ($slutaar>$startaar && $maaned_fra>$maaned_til) $slutmd=$slutmd+12;
	}

	if (strlen($startmaaned)==1){$startmaaned="0".$startmaaned;}
	if (strlen($slutmaaned)==1){$slutmaaned="0".$slutmaaned;}

	$regnaarstart= $startaar. "-" . $startmaaned . "-" . '01';

	if ($maaned_fra) {$startmaaned=$maaned_fra;}
	if ($maaned_til) {$slutmaaned=$maaned_til;}
	if ($dato_fra) {$startdato=$dato_fra;}
	if ($dato_til) {$slutdato=$dato_til;}

	while (!checkdate($startmaaned,$startdato,$startaar)) {
		$startdato=$startdato-1;
		if ($startdato<28) break 1;
	}

	while (!checkdate($slutmaaned,$slutdato,$slutaar)) {
		$slutdato=$slutdato-1;
		if ($slutdato<28) break 1;
	}
#cho "1008 $projekt_fra $prj_navn_fra - $projekt_til $prj_navn_til<br>"; 

	$regnstart = $aar_fra. "-" . $startmaaned . "-" . $startdato;
	$regnslut = $aar_til . "-" . $slutmaaned . "-" . $slutdato;

	print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	if ($menu=='T') {
		$leftbutton="<a title=\"Klik her for at komme til forsiden af rapporter\" href=\"rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering\" accesskey=\"L\">LUK</a>";
		$rightbutton="";
		include("../includes/topmenu.php");
	} elseif ($menu=='S') {
		include("../includes/sidemenu.php");
	} else {
		print "<tr><td colspan=\"$cols6\" height=\"8\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund><a accesskey=L href=\"rapport.php?rapportart=$rapportart&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering\">Luk</a></td>";
		print "<td width=\"80%\" $top_bund> Rapport - $rapportart </td>";
		print "<td width=\"10%\" $top_bund><br></td>";
		print "</tbody></table>"; #B slut
		print "</td></tr>";
	}		
	if ($rapportart=='resultat') {
		($simulering)?$tmp="Simuleret resultat":$tmp="Resultat";
	} elseif ($rapportart=='budget') {
		($simulering)?$tmp="Simuleret resultat/budget":$tmp="Resultat/budget";
	} else {
		($simulering)?$tmp="Simuleret Balance":$tmp="Balance";
	}
	print "<tr><td colspan=\"$cols4\"><big><big>$tmp</span></big></big></td>";

	print "<td colspan=\"$cols2\" align=right><table style=\"text-align: left; width: 100%;\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody><tr>";
	if ($afd) {
		print "<td>Afdeling</span></td>";
		print "<td>$afd: $afd_navn</span></td></tr>";
	}
	print "<td>Regnskabs&aring;r</span></td>";
	print "<td>$regnaar.</span></td></tr>";
	print "<tr><td>Periode</span></td>";
	if ($startdato < 10) $startdato="0".$startdato*1;
	print "<td>Fra ".$startdato.". $mf $aar_fra<br />Til ".$slutdato.". $mt $aar_til</span></td></tr>";
	if ($ansat_fra) {
		if (!$ansat_til || $ansat_fra==$ansat_til) print "<tr><td>Medarbejder</span></td><td>$ansatte</span></td></tr>";
		else print "<tr><td>Medarbejdere</span></td><td>$ansatte</span></td></tr>";
	}
	if ($afd) print "<tr><td>Afdeling</span></td><td>$afd_navn</span></td></tr>";
	if ($projekt_fra) {
		print "<td>Projekt:</td><td>";
#		print "<tr><td>Projekt $prj_navn_fra</td>";
		if (!strstr($projekt_fra,"?")) {
			if ($projekt_til && $projekt_fra != $projekt_til) print "Fra: $projekt_fra, $prj_navn_fra<br>Til : $projekt_til, $prj_navn_til";
			else print "$projekt_fra, $prj_navn_fra"; 
		} else print "$projekt_fra, $prj_navn_fra";
		print "</td></tr>";
	}
	print "</tbody></table></td></tr>";
	print "<tr><td colspan=\"4\"><big><b>$firmanavn</b></big></td>";
	print "<td align=right> Perioden </td>";
	if ($rapportart=='budget') {
		print "<td align=right> Budget </td><td align=right> Afvigelse </td></tr>";
	}else {
		print "<td align=right> &Aring;r til dato </td></tr>";
	}

	print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
	$x=0;
	$query = db_select("select * from kontoplan where regnskabsaar='$regnaar' order by kontonr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$x++;
		$kontonr[$x]=$row['kontonr']*1;
		$ktonr[$x]=$kontonr[$x];
		$kontobeskrivelse[$x]=$row['beskrivelse'];
		$kontotype[$x]=$row['kontotype'];
		$fra_kto[$x]=$row['fra_kto']*1;
		$primo[$x]=afrund($row['primo'],2);
		$saldo[$x]=$row['saldo']*1;
		$lukket[$x]=$row['lukket']; #20120927
		$aarsum[$x]=0;
		$kto_aar[$x]=0;
		$kto_periode[$x]=0;
		$vis_kto[$x]=0;
	}
	$kontoantal=$x;
	$dim='';
	if (($afd||$ansat_fra||$projekt_fra) && $rapportart!='budget') {
		if ($afd) $dim = "and afd = '$afd' ";
		if ($ansat_fra && $ansat_til) {
			$tmp=str_replace(","," or ansat=",$ansatte_id);
			$dim = $dim." and (ansat=$tmp) ";
		}
		elseif ($ansat_fra) $dim = $dim."and ansat = '$ansat_fra' ";
		$projekt_fra=str2low($projekt_fra);
		$projekt_til=str2low($projekt_til);
		if ($projekt_fra && $projekt_til && $projekt_fra!=$projekt_til) $dim = $dim." and lower(projekt) >= '$projekt_fra' and lower(projekt) <= '$projekt_til' ";
		elseif ($projekt_fra) {
			$tmp=str_replace("?","_",$projekt_fra);
			if (substr($tmp,-1)=='_') {
				while (substr($tmp,-1)=='_') $tmp=substr($tmp,0,strlen($tmp)-1);
				$tmp=str2low($tmp)."%";
			}
			$dim = $dim."and lower(projekt) LIKE '$tmp' ";
		}
	}
	$x=0;
	for ($x=1; $x<=$kontoantal; $x++) {
		if ($r=db_fetch_array(db_select("select * from transaktioner where transdate>='$regnaarstart' and transdate<='$regnslut' $dim and kontonr=$ktonr[$x]",__FILE__ . " linje " . __LINE__))) {
			$vis_kto[$x]=1;
		}
		if(db_fetch_array(db_select("select * from transaktioner where transdate>='$regnaarstart' and transdate<='$regnslut' and kontonr='$ktonr[$x]' $dim",__FILE__ . " linje " . __LINE__))) {
			$vis_kto[$x]=1;
		}
		if ($simulering) {
			if ($r=db_fetch_array(db_select("select * from simulering where transdate>='$regnaarstart' and transdate<='$regnslut' $dim and kontonr=$ktonr[$x]",__FILE__ . " linje " . __LINE__))) {
				$vis_kto[$x]=1;
			}
			if(db_fetch_array(db_select("select * from simulering where transdate>='$regnaarstart' and transdate<='$regnslut' and kontonr='$ktonr[$x]' $dim",__FILE__ . " linje " . __LINE__))) {
				$vis_kto[$x]=1;
			}
		}
		if ($kontotype[$x]=='R') $vis_kto[$x]=1;
	}
	if ($rapportart=='budget') {
		for ($x=1; $x<=$kontoantal; $x++) {
			if (!$lukket[$x]) { #20120927	
				if ($r=db_fetch_array(db_select("select sum(amount) as amount from budget where regnaar='$regnaar' and kontonr='$ktonr[$x]' and md >= '$startmd' and md <= '$slutmd'",__FILE__ . " linje " . __LINE__))) {
					$vis_kto[$x]=1;
				}
			}
		}
	}
	for ($x=1; $x<=$kontoantal; $x++) {
		$kto_aar[$x]=0;
		$kto_periode[$x]=0;  # Herunder tilfoejes primovaerdi.
			if (($rapportart=='balance'&&!$afd && !$projekt_fra && !$ansat_fra) && ($r2 = db_fetch_array(db_select("select primo from kontoplan where regnskabsaar='$regnaar' and kontonr=$ktonr[$x] and kontotype='S'",__FILE__ . " linje " . __LINE__)))) {
				$kto_aar[$x]=afrund($r2['primo'],2);
			}
		$query = db_select("select * from transaktioner where transdate>='$regnaarstart' and transdate<='$regnslut' and kontonr='$ktonr[$x]' $dim",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if ($row['transdate']>=$regnstart) $kto_periode[$x]=$kto_periode[$x]+afrund($row['debet'],2)-afrund($row['kredit'],2);
			if ($rapportart!='budget') {
				$kto_aar[$x]=$kto_aar[$x]+afrund($row['debet'],2)-afrund($row['kredit'],2);
			}
		}
		if ($simulering) {
			$query = db_select("select * from simulering where transdate>='$regnaarstart' and transdate<='$regnslut' and kontonr='$ktonr[$x]' $dim",__FILE__ . " linje " . __LINE__);
			while ($row = db_fetch_array($query)) {
#cho "$kto_periode[$x] --> ";
				if ($row['transdate']>=$regnstart) $kto_periode[$x]=$kto_periode[$x]+afrund($row['debet'],2)-afrund($row['kredit'],2);
#cho "$kto_periode[$x]<br>";
				if ($rapportart!='budget') {
					$kto_aar[$x]=$kto_aar[$x]+afrund($row['debet'],2)-afrund($row['kredit'],2);
				}
			}
		}
	}
	if ($rapportart=='budget') {
		for ($x=1; $x<=$kontoantal; $x++) {
			if ($vis_kto[$x]) { #20120927
				$r2=db_fetch_array(db_select("select sum(amount) as amount from budget where regnaar='$regnaar' and kontonr='$ktonr[$x]' and md >= '$startmd' and md <= '$slutmd'",__FILE__ . " linje " . __LINE__));
				$kto_aar[$x]=afrund($r2['amount'],2);
			}
		}
	} #else $kto_aar[$x]=$kto_aar[$x]+afrund($row['debet'],2)-afrund($row['kredit'],2);
		$kto_antal=$kontoantal;


	for ($x=1; $x<=$kontoantal; $x++) { # Her fanges konti med primovaerdi og ingen bevaegelser i perioden.
		if (!in_array($kontonr[$x], $ktonr)&& !$afd && !$projekt_fra && !$ansat_fra) {
			if ($primo[$x]) {
				$kto_antal++;
				$ktonr[$kto_antal]=$kontonr[$x];
				$kto_aar[$kto_antal]=$primo[$x];
			}
		}
	}

	for ($x=1; $x<=$kontoantal; $x++) {
		if ($kontotype[$x]=='R') {
			$aarsum[$x]=$saldo[$x];
			$periodesum[$x]=$saldo[$x];
			$kto_aar[$x]=$saldo[$x];
			$kto_periode[$x]=$saldo[$x];
		}

	if (!isset($periodesum[$x])) $periodesum[$x]=0;
		for ($y=1; $y<=$kto_antal; $y++) {
			if (!isset($kto_periode[$y])) $kto_periode[$y]=0;
			if (($kontotype[$x] == 'D')||($kontotype[$x] == 'S')) {
				if ($kontonr[$x]==$ktonr[$y]) {
					$aarsum[$x]=$aarsum[$x]+$kto_aar[$y];
					$periodesum[$x]=$periodesum[$x]+$kto_periode[$y];
				}
			 } elseif ($kontotype[$x] == 'Z') {
				if (($fra_kto[$x]<=$ktonr[$y])&&($kontonr[$x]>=$ktonr[$y])&&($kontonr[$x]!=$ktonr[$y])) {
					$aarsum[$x]=$aarsum[$x]+$kto_aar[$y];
					$periodesum[$x]=$periodesum[$x]+$kto_periode[$y];
				}
			}
		}
	}
	for ($x=1; $x<=$kontoantal; $x++) {
		if ($kontonr[$x]>=$konto_fra && $kontonr[$x]<=$konto_til && ($aarsum[$x] || $periodesum[$x] || $kontotype[$x] == 'H' || $kontotype[$x] == 'R')) {
			if ($kontotype[$x] == 'H') {
				$linjebg=$bgcolor;
				print "<tr><td><br></td></tr>";
				$tmp = kontobemaerkning($kontobeskrivelse[$x]);
				print "<tr bgcolor=\"$bgcolor5\"><td $tmp colspan=\"$cols6\"><b>$kontobeskrivelse[$x]</b></td>";
				print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
			} elseif ($kontotype[$x] == 'Z') {
				print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
				$tmp = kontobemaerkning($kontobeskrivelse[$x]);
				if (!$budget) print "<td><br></td>";
				print "<td $tmp colspan=\"$cols3\"><b> $kontobeskrivelse[$x] </b></td>";
				$tmp=dkdecimal($periodesum[$x]);
				print "<td align=\"right\"><b>$tmp </b></td>";
				$tmp=dkdecimal($aarsum[$x]);
				print "<td align=\"right\"><b>$tmp </b></td>";
				if ($rapportart=='budget') {
					if ($aarsum[$x]) $tmp=dkdecimal(($periodesum[$x]-$aarsum[$x])*100/$aarsum[$x]);
					else $tmp="--";
					print "<td align=right><b>$tmp%</b></td>";
				}
				print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
			} else {
				($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
				print "<tr bgcolor=\"$linjebg\"><td>$kontonr[$x]</td>";
				$tmp = kontobemaerkning($kontobeskrivelse[$x]);
				print "<td $tmp colspan=\"3\">$kontobeskrivelse[$x]</td>";
				$tmp=dkdecimal($periodesum[$x]);
				print "<td align=right>$tmp </td>";
				$tmp=dkdecimal($aarsum[$x]); #aar til dato
				print "<td align=right>$tmp</td>";
				if ($rapportart=='budget') {
					if ($aarsum[$x]) $tmp=dkdecimal(($periodesum[$x]-$aarsum[$x])*100/$aarsum[$x]);
					else $tmp="--";
					print "<td align=right>$tmp%</td>"; #afvigelse fra budget
				}
				print "</tr>";
			}
		}
	}
	print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
	print "</tbody></table>";
	print "<!--Function regnskab slut-->\n";
}
#################################################################################################
function regnskab0($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,$simulering) 
{
	global $connection;
	global $top_bund;
	global $md;
	global $ansatte;
	global $ansatte_id;
	global $afd_navn;
	global $prj_navn_fra;
	global $prj_navn_til;
	global $bgcolor;
	global $bgcolor4;
	global $bgcolor5;
	global $menu;

	$periodesum=array();
	$kto_periode=array();
	
	if ($rapportart=='budget') {
		$budget=1;
		$cols1=2;$cols2=3;$cols3=4;$cols4=5;$cols5=6;$cols6=7;
	} else {
		$budget=0;
		$cols1=1;$cols2=2;$cols3=3;$cols4=4;$cols5=5;$cols6=6;
	}
	
	if ($row = db_fetch_array(db_select("select firmanavn from adresser where art='S'",__FILE__ . " linje " . __LINE__))) {$firmanavn=$row['firmanavn'];}
	if (($afd)&&($row = db_fetch_array(db_select("select beskrivelse from grupper where art='AFD' and kodenr='$afd'",__FILE__ . " linje " . __LINE__)))) {$afd_navn=$row['beskrivelse'];}

	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk

#	list ($x, $maaned_fra) = explode(" ", $maaned_fra);
#	list ($x, $maaned_til) = explode(" ", $maaned_til);

	$maaned_fra=trim($maaned_fra);
	$maaned_til=trim($maaned_til);
	$konto_fra=trim($konto_fra);
	$konto_til=trim($konto_til);

	$mf=$maaned_fra;
	$mt=$maaned_til;

	for ($x=1; $x<=12; $x++){
		if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
		if ($maaned_til==$md[$x]){$maaned_til=$x;}
		if (strlen($maaned_fra)==1){$maaned_fra="0".$maaned_fra;}
		if (strlen($maaned_til)==1){$maaned_til="0".$maaned_til;}
		if (strlen($dato_fra)==1){$dato_fra="0".$dato_fra;}
		if (strlen($dato_til)==1){$dato_til="0".$dato_til;}
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
		
	if (strlen($startmaaned)==1){$startmaaned="0".$startmaaned;}
	if (strlen($slutmaaned)==1){$slutmaaned="0".$slutmaaned;}
	
	$regnaarstart= $startaar. "-" . $startmaaned . "-" . '01';
	
	if ($maaned_fra) {$startmaaned=$maaned_fra;}
	if ($maaned_til) {$slutmaaned=$maaned_til;}
	if ($dato_fra) {$startdato=$dato_fra;}
	if ($dato_til) {$slutdato=$dato_til;}

#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}
		
	while (!checkdate($startmaaned,$startdato,$startaar)) {
		$startdato=$startdato-1;
		if ($startdato<28) break 1;
	}
	
	while (!checkdate($slutmaaned,$slutdato,$slutaar)) {
		$slutdato=$slutdato-1;
		if ($slutdato<28) break 1;
	}
	
	$regnstart = $aar_fra. "-" . $startmaaned . "-" . $startdato;
	$regnslut = $aar_til . "-" . $slutmaaned . "-" . $slutdato;
 	
 #	print "  <a accesskey=L href=\"rapport.php?rapportart=$rapportart&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&dato_til=$slutdato&maaned_til=$mt&konto_fra=$konto_fra&konto_til=$konto_til&afd=$afd\">Luk</a><br><br>";

	print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	if ($menu=='T') {
		$leftbutton="<a title=\"Klik her for at komme til forsiden af rapporter\" href=\"rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering\" accesskey=\"L\">LUK</a>";
		$rightbutton="";
		include("../includes/topmenu.php");
	} elseif ($menu=='S') {
		include("../includes/sidemenu.php");
	} else {
		print "<tr><td colspan=\"$cols6\" height=\"8\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund><a accesskey=L href=\"rapport.php?rapportart=$rapportart&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering\">Luk</a></td>";
		print "<td width=\"80%\" $top_bund> Rapport - $rapportart </td>";
		print "<td width=\"10%\" $top_bund><br></td>";
		print "</tbody></table>"; #B slut
		print "</td></tr>";
	}
	if ($rapportart=='resultat') $tmp="Resultat";
	elseif ($rapportart=='budget') $tmp="Resultat/budget";
	else $tmp="Balance";
 	print "<tr><td colspan=\"$cols4\"><big><big>$tmp</span></big></big></td>";

	print "<td colspan=\"$cols2\" align=right><table style=\"text-align: left; width: 100%;\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody><tr>";
	if ($afd) {
		print "<td>Afdeling</span></td>";
		print "<td>$afd: $afd_navn</span></td></tr>";
	}
	print "<td>Regnskabs&aring;r</span></td>";
	print "<td>$regnaar.</span></td></tr>";
	print "<tr><td>Periode</span></td>";
	if ($startdato < 10) $startdato="0".$startdato*1;	
	print "<td>Fra ".$startdato.". $mf $aar_fra<br />Til ".$slutdato.". $mt $aar_til</span></td></tr>";
	if ($ansat_fra) {
		if (!$ansat_til || $ansat_fra==$ansat_til) print "<tr><td>Medarbejder</span></td><td>$ansatte</span></td></tr>";
		else print "<tr><td>Medarbejdere</span></td><td>$ansatte</span></td></tr>";
	}
	if ($afd) print "<tr><td>Afdeling</span></td><td>$afd_navn</span></td></tr>";
	if ($projekt_fra) {
		print "<tr><td>Projekt</span></td><td>$prj_navn_fra ";
		if ($projekt_til) print "- $prj_navn_til ";
		print "</span></td></tr>";
	}	
	print "</tbody></table></td></tr>";
	print "<tr><td colspan=\"4\"><big><b>$firmanavn</b></big></td>";
	print "<td align=right> Perioden </td>";
	if ($rapportart=='budget') {
		print "<td align=right> Budget </td><td align=right> Afvigelse </td></tr>";
	}else {
		print "<td align=right> &Aring;r til dato </td></tr>";
	}
	
	print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
	$x=0;
	$query = db_select("select * from kontoplan where regnskabsaar='$regnaar' order by kontonr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$x++;
		$kontonr[$x]=$row['kontonr']*1;
		$kontobeskrivelse[$x]=$row['beskrivelse'];
		$kontotype[$x]=$row['kontotype'];
		$fra_kto[$x]=$row['fra_kto']*1;
#		$til_kto[$x]=$row['til_kto']*1;
		$primo[$x]=afrund($row['primo'],2);
#		if ((!$afd)&&($row[kontotype]=="S")) {$aarsum[$x]=$row[primo];}
#		else {$primo[$x]=0;}
	$aarsum[$x]=0;
	}
	$kontoantal=$x;
	$kto_aar[$x]=0;
	$kto_periode[$x]=0;
	$ktonr=array();
	$x=0;
	$dim='';
	if ($afd||$ansat_fra||$projekt_fra) {
		if ($afd) $dim = "and afd = '$afd' ";
		if ($ansat_fra && $ansat_til) {
			$tmp=str_replace(","," or ansat=",$ansatte_id);
			$dim = $dim." and (ansat=$tmp) ";
		}
		elseif ($ansat_fra) $dim = $dim."and ansat = '$ansat_fra' ";
		$projekt_fra=str2low($projekt_fra);
		$projekt_til=str2low($projekt_til);
		if ($projekt_fra && $projekt_til && $projekt_fra!=$projekt_til) $dim = $dim." and lower(projekt) >= '$projekt_fra' and lower(projekt) <= '$projekt_til' ";
		elseif ($projekt_fra) {
			$tmp=str_replace("?","_",$projekt_fra);
			if (substr($tmp,-1)=='_') {
				while (substr($tmp,-1)=='_') $tmp=substr($tmp,0,strlen($tmp)-1);
				$tmp=str2low($tmp)."%";
			}
			$dim = $dim."and lower(projekt) LIKE '$tmp' ";
		}
	}	
	
#cho "select * from transaktioner where transdate>='$regnaarstart' and transdate<='$regnslut' $dim order by kontonr<br>";		
	$query = db_select("select * from transaktioner where transdate>='$regnaarstart' and transdate<='$regnslut' $dim order by kontonr",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
		if (!in_array($row['kontonr'], $ktonr)) { # Her fanges konto med bevaegelser i perioden.
			$x++;
			$ktonr[$x]=$row['kontonr']*1;
			$kto_aar[$x]=0;
			$kto_periode[$x]=0;  # Herunder tilfoejes primovaerdi.
			if (($rapportart=='balance'&&!$afd && !$projekt_fra && !$ansat_fra) && ($r2 = db_fetch_array(db_select("select primo from kontoplan where regnskabsaar='$regnaar' and kontonr=$ktonr[$x] and kontotype='S'",__FILE__ . " linje " . __LINE__)))) {
				$kto_aar[$x]=afrund($r2['primo'],2);
			}
		}
		if ($rapportart=='budget') {
			$r2=db_fetch_array(db_select("select sum(amount) as amount from budget where regnaar='$regnaar' and kontonr='$ktonr[$x]' and md >= '$maaned_fra' and md <= '$maaned_til'",__FILE__ . " linje " . __LINE__));
			$kto_aar[$x]=afrund($r2['amount'],2);
		} else $kto_aar[$x]=$kto_aar[$x]+afrund($row['debet'],2)-afrund($row['kredit'],2);
		if ($row['transdate']>=$regnstart) $kto_periode[$x]=$kto_periode[$x]+afrund($row['debet'],2)-afrund($row['kredit'],2);
	
	}
	$kto_antal=$x;	

	for ($x=1; $x<=$kontoantal; $x++) { # Her fanges konti med primovaerdi og ingen bevaegelser i perioden.
		if (!in_array($kontonr[$x], $ktonr)&& !$afd && !$projekt_fra && !$ansat_fra) {
			if ($primo[$x]) {
				$kto_antal++;
				$ktonr[$kto_antal]=$kontonr[$x];
				$kto_aar[$kto_antal]=$primo[$x];
			} 
		}
	}

	for ($x=1; $x<=$kontoantal; $x++) {
	if (!isset($periodesum[$x])) $periodesum[$x]=0;
		for ($y=1; $y<=$kto_antal; $y++) {
		if (!isset($kto_periode[$y])) $kto_periode[$y]=0;
			if (($kontotype[$x] == 'D')||($kontotype[$x] == 'S')) {
				if ($kontonr[$x]==$ktonr[$y]) {
					$aarsum[$x]=$aarsum[$x]+$kto_aar[$y];
					$periodesum[$x]=$periodesum[$x]+$kto_periode[$y];
				}
			 }
			 elseif ($kontotype[$x] == 'Z') {
				if (($fra_kto[$x]<=$ktonr[$y])&&($kontonr[$x]>=$ktonr[$y])&&($kontonr[$x]!=$ktonr[$y])) {
					$aarsum[$x]=$aarsum[$x]+$kto_aar[$y];
					$periodesum[$x]=$periodesum[$x]+$kto_periode[$y];
				}
			}
		}
	}
	for ($x=1; $x<=$kontoantal; $x++) {
		if (($kontonr[$x]>=$konto_fra)&&($kontonr[$x]<=$konto_til)&&(($aarsum[$x])||($periodesum[$x])||($kontotype[$x] == 'H'))) {
			if ($kontotype[$x] == 'H') {
				$linjebg=$bgcolor;
				print "<tr><td><br></td></tr>";
				$tmp = kontobemaerkning($kontobeskrivelse[$x]);
				print "<tr bgcolor=\"$bgcolor5\"><td $tmp colspan=\"$cols6\"><b>$kontobeskrivelse[$x]</b></td>";
				print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
			} elseif ($kontotype[$x] == 'Z') {
				print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
				$tmp = kontobemaerkning($kontobeskrivelse[$x]);
				if (!$budget) print "<td><br></td>";
				print "<td $tmp colspan=\"$cols3\"><b> $kontobeskrivelse[$x] </b></td>";
				$tmp=dkdecimal($periodesum[$x]);
				print "<td align=\"right\"><b>$tmp </b></td>";
				$tmp=dkdecimal($aarsum[$x]);
				print "<td align=\"right\"><b>$tmp </b></td>";
				if ($rapportart=='budget') {
					if ($aarsum[$x]) $tmp=dkdecimal(($periodesum[$x]-$aarsum[$x])*100/$aarsum[$x]);
					else $tmp="--";
					print "<td align=right><b>$tmp% </b></td>";
				}
				print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
			} else {
				($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
				print "<tr bgcolor=\"$linjebg\"><td>$kontonr[$x] </td>";
				$tmp = kontobemaerkning($kontobeskrivelse[$x]);
				print "<td $tmp colspan=\"3\">$kontobeskrivelse[$x] </td>";
				$tmp=dkdecimal($periodesum[$x]);
				print "<td align=right>$tmp </td>";
				$tmp=dkdecimal($aarsum[$x]);
				print "<td align=right>$tmp </td>";
				if ($rapportart=='budget') {
					if ($aarsum[$x]) $tmp=dkdecimal(($periodesum[$x]-$aarsum[$x])*100/$aarsum[$x]);
					else $tmp="--";
					print "<td align=right>$tmp% </td>";
				}
				print "</tr>";
			}
		}
	}

	print "<tr><td colspan=\"$cols6\"><hr></td></tr>";
	print "</tbody></table>";
}
#################################################################################################
function momsangivelse ($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,$simulering)
{
	global $connection;
	global $top_bund;
	global $md;
	global $ansatte;
	global $ansatte_id;
	global $afd_navn;
	global $prj_navn_fra;
	global $prj_navn_til;
	global $menu;

	$medtag_primo=if_isset($_GET['medtag_primo']);

	if ($row = db_fetch_array(db_select("select firmanavn from adresser where art='S'",__FILE__ . " linje " . __LINE__))) $firmanavn=$row['firmanavn'];
	if (($afd)&&($row = db_fetch_array(db_select("select beskrivelse from grupper where art='AFD' and kodenr='$afd'",__FILE__ . " linje " . __LINE__)))) $afd_navn=$row['beskrivelse'];

	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk

#	list ($x, $maaned_fra) = explode(" ", $maaned_fra);
#	list ($x, $maaned_til) = explode(" ", $maaned_til);

	$maaned_fra=trim($maaned_fra);
	$maaned_til=trim($maaned_til);
	$konto_fra=trim($konto_fra);
	$konto_til=trim($konto_til);

	$mf=$maaned_fra;
	$mt=$maaned_til;

	for ($x=1; $x<=12; $x++){
		if ($maaned_fra==$md[$x]) $maaned_fra=$x;
		if ($maaned_til==$md[$x]) $maaned_til=$x;
		if (strlen($maaned_fra)==1) $maaned_fra="0".$maaned_fra;
		if (strlen($maaned_til)==1) $maaned_til="0".$maaned_til;
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
	$regnaarstart= $startaar. "-" . $startmaaned . "-" . '01';

	if ($maaned_fra) $startmaaned=$maaned_fra;
	if ($maaned_til) $slutmaaned=$maaned_til;
	if ($dato_fra) $startdato=$dato_fra;
	if ($dato_til) $slutdato=$dato_til;

#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}

	while (!checkdate($startmaaned,$startdato,$startaar)){
		$startdato=$startdato-1;
		if ($startdato<28) break 1;
	}

	while (!checkdate($slutmaaned,$slutdato,$slutaar))	{
		$slutdato=$slutdato-1;
		if ($slutdato<28) break 1;
	}
	if (strlen($startdato)<2) $startdato="0".$startdato;


	$regnstart = $aar_fra. "-" . $startmaaned . "-" . $startdato;
	$regnslut = $aar_til . "-" . $slutmaaned . "-" . $slutdato;
#	$regnstart = $startaar. "-" . $startmaaned . "-" . $startdato;
#	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

#	print "  <a accesskey=L href=\"rapport.php?rapportart=$rapportart&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&dato_til=$slutdato&maaned_til=$mt&konto_fra=$konto_fra&konto_til=$konto_til&afd=$afd\">Luk</a><br><br>";

	print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
		if ($menu=='T') {
		$leftbutton="<a title=\"Klik her for at komme til forsiden af rapporter\" href=\"rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering\" accesskey=\"L\">LUK</a>";
		$rightbutton="";
		include("../includes/topmenu.php");
	} elseif ($menu=='S') {
		include("../includes/sidemenu.php");
	} else {
		print "<tr><td colspan=\"6\" height=\"8\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
		print "<td width=\"10%\" $top_bund><a accesskey=L href=\"rapport.php?rapportart=$rapportart&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&ansat_til=$ansat_til&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering\">Luk</a></td>";
		print "<td width=\"80%\" $top_bund> Rapport - ".ucfirst($rapportart)."</td>";
		print "<td width=\"10%\" $top_bund><br></td>";
		print "</tbody></table>"; #B slut
		print "</td></tr>";
	}
 	print "<tr><td colspan=\"4\"><big><big>".ucfirst($rapportart)."</span></big></big></td>";
	print "<td colspan=2 align=right><table style=\"text-align: left; width: 400px;\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody><tr>";
	if ($afd) {
		print "<td>Afdeling</span></td>";
		print "<td>$afd: $afd_navn</span></td></tr>";
	}
	print "<td>Regnskabs&aring;r</span></td>";
	print "<td>$regnaar.</span></td></tr>";
	print "<tr><td>Periode</span></td>";
	print "<td>Fra </td><td>".dkdato($regnstart)."</td></tr><tr><td></td><td>Til &nbsp;&nbsp;</td><td>".dkdato($regnslut)."</span></td></tr>";
	if ($ansat_fra) {
		if (!$ansat_til || $ansat_fra==$ansat_til) print "<tr><td>Medarbejder</span></td><td>$ansatte</span></td></tr>";
		else print "<tr><td>Medarbejdere</span></td><td>$ansatte</span></td></tr>";
	}
	if ($afd) print "<tr><td>Afdeling</span></td><td>$afd_navn</span></td></tr>";
	if ($projekt_fra) {
		print "<tr><td>Projekt</td>";
		if (!strstr($projekt_fra,"?")) {
			print "<td>$prj_navn_fra ";
			if ($projekt_til && $projekt_fra != $projekt_til) print "- $prj_navn_til ";
		} else print "<td>$projekt_fra ";
		print "</td></tr>";
	}	
	print "</tbody></table></td></tr>";

	print "<tr><td colspan=4><big><b>$firmanavn</b></big></td></tr>";
	print "<tr><td colspan=6><hr></td></tr>";

	$dim='';
	if ($afd||$ansat_fra||$projekt_fra) {
		if ($afd) $dim = "and afd = $afd ";
		if ($ansat_fra) $dim = $dim."and ansat = '$ansat_fra' ";
		$projekt_fra=str2low($projekt_fra);
		$projekt_til=str2low($projekt_til);
		if ($projekt_fra && $projekt_til && $projekt_fra!=$projekt_til) $dim = $dim." and lower(projekt) >= '$projekt_fra' and lower(projekt) <= '$projekt_til' ";
		elseif ($projekt_fra) {
			$tmp=str_replace("?","_",$projekt_fra);
			if (substr($tmp,-1)=='_') {
				while (substr($tmp,-1)=='_') $tmp=substr($tmp,0,strlen($tmp)-1);
				$tmp=str2low($tmp)."%";
			}
			$dim = $dim."and lower(projekt) LIKE '$tmp' ";
		}
	}

	$row = db_fetch_array($query = db_select("select box1, box2 from grupper where art='MR'",__FILE__ . " linje " . __LINE__));
	if (($row[box1]) && ($row[box2])) {
		$konto_fra=$row['box1'];
		$konto_til=$row['box2'];

		$x=0;
		$query = db_select("select * from kontoplan where regnskabsaar='$regnaar' and kontonr>=$konto_fra and kontonr<=$konto_til order by kontonr",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)){
			$x++;
			$kontonr[$x]=$row['kontonr']*1;
			$kontobeskrivelse[$x]=$row['beskrivelse'];
			$kontotype[$x]=$row['kontotype'];
			$primo[$x]=$row['primo'];
			$aarsum[$x]=0;
		}

			
		$kontoantal=$x;
		$kto_aar[$x]=0;
		$kto_periode[$x]=0;
		$ktonr=array();
		$x=0;
		$query = db_select("select * from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' and kontonr>='$konto_fra' and kontonr<='$konto_til' $dim order by kontonr",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if (!in_array($row['kontonr'], $ktonr)) { # Her fanges konto med bevaegelser i perioden.
				$x++;
				$ktonr[$x]=$row['kontonr']*1;
				$kto_aar[$x]=0;
				if (($medtag_primo && !$afd) && ($r2 = db_fetch_array(db_select("select primo from kontoplan where regnskabsaar='$regnaar' and kontonr=$ktonr[$x] and kontotype='S'",__FILE__ . " linje " . __LINE__)))) {
					$kto_aar[$x]=afrund($r2['primo'],2);
				}
			}
			$kto_aar[$x]=$kto_aar[$x]+afrund($row['debet'],2)-afrund($row['kredit'],2);
		}
		$kto_antal=$x;
		if ($medtag_primo && !$afd) {
			for ($x=1; $x<=$kontoantal; $x++) { # Her fanges konto med primovaerdi og ingen bevaegelser i perioden.
				if (!in_array($kontonr[$x], $ktonr)) {
					if ($primo[$x]) {
						$kto_antal++;
						$ktonr[$kto_antal]=$kontonr[$x];
						$kto_aar[$kto_antal]=$primo[$x];
					}
				}
			}
		}
		for ($x=1; $x<=$kontoantal; $x++) {
			for ($y=1; $y<=$kto_antal; $y++) {
				if (($kontotype[$x] == 'D')||($kontotype[$x] == 'S')) {
					if ($kontonr[$x]==$ktonr[$y]) {
						$aarsum[$x]=$aarsum[$x]+$kto_aar[$y];
					}
				 } elseif ($kontotype[$x] == 'Z') {
					if (($fra_kto[$x]<=$ktonr[$y])&&($kontonr[$x]>=$ktonr[$y])&&($kontonr[$x]!=$ktonr[$y])) {
						$aarsum[$x]=$aarsum[$x]+$kto_aar[$y];
					}
				}
			}
		}

		for ($x=1; $x<=$kontoantal; $x++) {
			if (($kontonr[$x]>=$konto_fra)&&($kontonr[$x]<=$konto_til)) {
				print "<tr>";
				$aarsum[$x]=afrund($aarsum[$x],0);
				print "<td>$kontonr[$x] </td>";
				$tmp = kontobemaerkning($kontobeskrivelse[$x]);
				print "<td $tmp colspan=3>$kontobeskrivelse[$x] </td>";
				$row = db_fetch_array($query = db_select("select art from grupper where box1='$kontonr[$x]' and art<>'MR'",__FILE__ . " linje " . __LINE__));		
				if (($row[art]=='SM')||($row[art]=='YM')||($row[art]=='EM')) {
					print "<td>&nbsp;</td>";
					$tmp=dkdecimal($aarsum[$x]*-1);
				} else $tmp=dkdecimal($aarsum[$x]);
				print "<td align=right>$tmp </td>";
			print "</tr>\n";
			$afgiftssum=$afgiftssum+$aarsum[$x];
			}
		}
		$tmp=dkdecimal($afgiftssum*-1);
		print "<tr><td colspan=6><hr></td></tr>";
		print "<tr><td></td><td>  Afgiftsbel&oslash;b i alt </td><td colspan=4 align=right>$tmp </td></tr>";
		print "<tr><td colspan=6><hr></td></tr>";

# Kommentering fjernes, naar Rubrik-konti er klar
#		# Tilfoejer de fem Rubrik-konti: A1, A2, B1, B2 og C
#		$row = db_fetch_array($query = db_select("select box3, box4, box5, box6, box7 from grupper where art='MR'",__FILE__ . " linje " . __LINE__));
#
#		momsrubrik($row[box3], "Rubrik A. Værdien uden moms af varekøb i andre EU-lande (EU-erhvervelser)", $regnaar, $regnstart, $regnslut);
#		momsrubrik($row[box4], "Rubrik A. Værdien uden moms af ydelseskøb i andre EU-lande", $regnaar, $regnstart, $regnslut);
#		momsrubrik($row[box5], "Rubrik B. Værdien af varesalg uden moms til andre EU-lande (EU-leverancer)", $regnaar, $regnstart, $regnslut);
#		momsrubrik($row[box6], "Rubrik B. Værdien af visse ydelsessalg uden moms til andre EU-lande", $regnaar, $regnstart, $regnslut);
#		momsrubrik($row[box7], "Rubrik C. Værdien af andre varer og ydelser, der leveres uden afgift", $regnaar, $regnstart, $regnslut);

		$x=0;
			


		print "<tr><td colspan=6><hr></td></tr>";
		print "</tbody></table>";
	} else {
		print "<BODY onLoad=\"javascript:alert('Rapportspecifikation ikke defineret (Indstillinger -> Moms)')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=rapport.php?rapportart=kontokort&regnaar=$regnaar&dato_fra=$startdato&maaned_fra=$mf&dato_til=$slutdato&maaned_til=$mt&konto_fra=$konto_fra&konto_til=$konto_til&ansat_fra=$ansat_fra&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til&simulering=$simulering\">";
	}
}

function kontobemaerkning ( $l_kontonavn ) {
	if (strstr( $l_kontonavn, "RESULTAT")) {
		$retur = "title=\"Negativt resultat betyder overskud. Positivt resultat betyder underskud.\"";
	} elseif ($l_kontonavn=="Balancekontrol") {
		$retur = "title=\"Balancekontrollen viser det forel&oslash;bige eller periodens resultat, n&aring;r regnskabet ikke er afsluttet. Positivt viser et overskud. Negativt et underskud.\"";
	}
	return $retur;
}

function momsrubrik($rubrik_konto, $rubrik_navn, $regnaar, $regnstart, $regnslut) {
		print "<tr><td>".$rubrik_konto."</td><td colspan='3'>".$rubrik_navn."</td>";
		if ( $rubrik_konto ) {
			$q = db_select("select * from kontoplan where regnskabsaar='$regnaar' and kontonr=$rubrik_konto",__FILE__ . " linje " . __LINE__);
			$r = db_fetch_array($q);
#			$kontobeskrivelse[$x]=$r['beskrivelse'];
			$rubriksum=0;
			$q = db_select("select * from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' and kontonr=$rubrik_konto",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$rubriksum+=afrund($r['debet'],2)-afrund($r['kredit'],2);
			}
			print "<td align='right'>".dkdecimal($rubriksum)."</td>";
		} else {
			print "<td align='right'><span title='Intet bel&oslash;b i den angivne periode.'>-</span></td>";
		}
		print "<td>&nbsp;</td></tr>\n";
		return;
}

function listeangivelser ($regnaar, $rapportart, $option_type) {
#	print "<option>Rap.art: ".$rapportart."</option>\n";
  $query = db_select("select box1, box2, box3, box4 from grupper where art = 'RA' and kodenr = '$regnaar' order by box2, box1 desc",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$x=1;

#$row['box1']="02";
#$row['box2']="2010";
#$row['box3']="01";
#$row['box4']="2011";

	# Foerste kvartal
#	print "<option>Aar: ".$row['box1']." og ".$regnaar."</option>";
	$kvartal_aar[$x] = $row['box2']*1;
	$kvartal_fuld[$x] = "nej";
	if ( $row['box1'] === "01" || $row['box1'] === "04" || $row['box1'] === "07" || $row['box1'] === "10" ) {
		$kvartal_startmd[$x] = $row['box1']*1;
		$kvartal_kvartal[$x] = (2+$row['box1']*1)/3;
		$kvartal_fuld[$x] = "ja";
	} elseif ( $row['box1'] === "02" ||  $row['box1'] === "03" ) {
		$kvartal_startmd[$x] = 1;
		$kvartal_kvartal[$x] = 1;
	} elseif ( $row['box1'] === "05" ||  $row['box1'] === "06" ) {
		$kvartal_startmd[$x] = 4;
		$kvartal_kvartal[$x] = 2;
	} elseif ( $row['box1'] === "08" ||  $row['box1'] === "09" ) {
		$kvartal_startmd[$x] = 7;
		$kvartal_kvartal[$x] = 3;
	} elseif ( $row['box1'] === "11" ||  $row['box1'] === "12" ) {
		$kvartal_startmd[$x] = 10;
		$kvartal_kvartal[$x] = 4;
	}
	
	$kvartal_rapportart[$x] = "Listeangivelse ".$kvartal_kvartal[$x].". kvartal ".$kvartal_aar[$x];
	$kvartal_aarmd[$x] = ($kvartal_aar[$x].$row['box1'])*1+2;
	$slut_regnaar = ($row['box4'].$row['box3'])*1;

#	print "<option>while ( ".$kvartal_aarmd[$x]." <= ".$slut_regnaar.") {</option>\n";
#	print "<option>Listeangivelse ".$kvartal_kvartal[$x].". ".$kvartal_aar[$x]."</option>\n";
	while ( $kvartal_aarmd[$x] <= $slut_regnaar ) {
#	print "<option>while ( ".$kvartal_aarmd[$x]." <= ".$slut_regnaar.") {</option>\n";
		$w=$x;
		$x++;
		if ($kvartal_kvartal[$w] == 4) {
			$kvartal_kvartal[$x] = 1;
			$kvartal_startmd[$x] = 1;
#			print "<option>V: ".$kvartal_aar[$x]." = ".$kvartal_aar[$w]."+1</option>\n";
			$kvartal_aar[$x] = ($kvartal_aar[$w]*1)+1;
		} else {
			$kvartal_kvartal[$x] = ($kvartal_kvartal[$w]*1)+1;
			$kvartal_startmd[$x] = ($kvartal_startmd[$w]*1)+3;
#			print "<option>W: ".$kvartal_aar[$x]." (".$x.") = ".$kvartal_aar[$w]." (".$w.")</option>\n";
			$kvartal_aar[$x] = $kvartal_aar[$w];
		}

		$kvartal_rapportart[$x] = "Listeangivelse ".$kvartal_kvartal[$x].". kvartal ".$kvartal_aar[$x];
#		print "<option>L: ".$kvartal_rapportart[$x]."</option>\n";

		$kvartal_slutmd[$x] = ($kvartal_startmd[$x]*1);
		if ( $kvartal_slutmd[$x] < 10 ) $kvartal_slutmd[$x] = "0".$kvartal_slutmd[$x];

		$kvartal_aarmd[$x] = $kvartal_aar[$x].$kvartal_slutmd[$x];
#	print "<option>while ( ".$kvartal_aarmd[$x]." <= ".$slut_regnaar.") {</option>\n";
	}

	$retur = "";

	$x--;
	
	#if ( $kvartal_slutmd[$x] > $regnaar_slutmd ) $kvartal_fuld
	for ($i=1; $i <= $x; $i++) {
#		print "<option>LL: ".$kvartal_rapportart[$i]."</option>\n";
		if ( $rapportart && $option_type == "matcher" && $rapportart == $kvartal_rapportart[$i] ) {
		#	$retur.= "<option value=\"".$kvartal_rapportart[$i]."\">".$kvartal_rapportart[$i]."</option>\n";
			print "<option value=\"".$kvartal_rapportart[$i]."\" title=\"Listeangivelser er pr. kvartal og uafh&aelig;ngig af regnskabs&aring;ret.\">".$kvartal_rapportart[$i]."</option>\n";
		}
	}

	for ($i=1; $i <= $x; $i++) {
		if ( $option_type == "alle andre" && ( !$rapportart || !($rapportart == $kvartal_rapportart[$i]) ) ) {
		#	$retur.= "<option value=\"".$kvartal_rapportart[$i]."\">".$kvartal_rapportart[$i]."</option>\n";
			print "<option value=\"".$kvartal_rapportart[$i]."\" title=\"Listeangivelser er pr. kvartal og uafh&aelig;ngig af regnskabs&aring;ret.\">".$kvartal_rapportart[$i]."</option>\n";
		}
	}

	return $retur;
}


?>
</html>

