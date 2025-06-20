<?php
ob_start(); //Starter output buffering
// ------------------finans/kassekladde.php------------lap 2.0.0f---05.05.08----
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
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------


@session_start();
$s_id=session_id();
$title="kassekladde";
$modulnr=2;

$kontonr=array();$amount=array();
$fejl=0; $kontrolkonto=NULL; $kontrolsaldo=NULL; $simuler=NULL; $kladde_id=NULL; $bogfort=NULL; $kladdenote=NULL; $regnstart=NULL; 
$sletstart=NULL; $sletslut=NULL;$opslag_id=NULL; $lukket=NULL; $linjebg=NULL; $antal_ex=NULL; $submit=NULL;


include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if ($tjek=if_isset($_GET['tjek'])) {
	$tidspkt=microtime() ;
	list ($a,$b)=explode(" ",$tidspkt);
	$query = db_select("select tidspkt, hvem from kladdeliste where bogfort = '-' and id = $tjek");
	if ($row = db_fetch_array($query) && isset($row['tidspkt'])) {
		list ($a,$c)=explode(" ",$row['tidspkt']);
		if (($b-$c<3600)&&($row['hvem']!=$brugernavn)){
			print "<body onLoad=\"javascript:alert('Kladden er i brug af $row[hvem]')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
		}
		else {
			$a--;
			$tidspkt=$a." ".$b; #der fratraekkes 1. sec af hensyn til refreshtjek;
			db_modify("update kladdeliste set hvem = '$brugernavn', tidspkt='$tidspkt' where id = '$tjek'");
		}
	}	
	if (db_fetch_array(db_select("select id from tmpkassekl where kladde_id='$tjek'"))) $fejl=1;
	else $fejl=0;
}
$kladde_id=isset($_POST['kladde_id'])? $_POST['kladde_id']:Null;
$antal_ny=isset($_POST['antal_ny'])? $_POST['antal_ny']:0;
$h=$antal_ny*10;

?>
<script language="javascript" type="text/javascript" src="../javascript/up_down.js"></script>
<script type="text/javascript">
<!--
function simuler(){
window.open("bogfor.php?kladde_id=<?php echo $kladde_id?>&funktion=simuler","","width=600,height=<?php echo $h?>,scrollbars=1,resizable=1")
}
//-->
</script>
<script type="text/javascript">
<!--
function bogfor() {
	window.open("bogfor.php?kladde_id=<?php echo $kladde_id?>","","width=600,height=<?php echo $h?>,scrollbars=1,resizable=1")
}
//-->
</script>
<div align="center">
<?php

if($_GET) {
	$returside=if_isset($_GET['returside']);
	if (!$returside){$returside="kladdeliste.php";}
	$fokus=if_isset($_GET['fokus']);
	$sort=if_isset($_GET['sort']);
	$funktion=if_isset($_GET['funktion']);
	$x=if_isset($_GET['x']);
	$id[$x]=if_isset($_GET['id']);
	$lobenr[$x]=if_isset($_GET['lobenr']);
	$kladde_id=if_isset($_GET['kladde_id']);
	$bilag[$x]=if_isset($_GET['bilag']);
	$dato[$x]=if_isset($_GET['dato']);
	$beskrivelse[$x]=str_replace("!og!", "&",if_isset($_GET['beskrivelse']));
	$beskrivelse[$x]=str_replace("!apostrof!", "'",$beskrivelse[$x]);
	$d_type[$x]=if_isset($_GET['d_type']);
	$debet[$x]=if_isset($_GET['debet']);
	$k_type[$x]=if_isset($_GET['k_type']);
	$kredit[$x]=if_isset($_GET['kredit']);
	$faktura[$x]=trim(if_isset($_GET['faktura']));
	$belob[$x]=if_isset($_GET['belob']);
	$momsfri[$x]=if_isset($_GET['momsfri']);
	$afd[$x]=if_isset($_GET['afd']);
	$projekt[$x]=if_isset($_GET['projekt']);
	$ansat[$x]=if_isset($_GET['ansat']);
	$valuta[$x]=if_isset($_GET['valuta']);
	
	$beskrivelse[$x]=trim($beskrivelse[$x]);
	$d_type[$x]=trim($d_type[$x]);
	$debet[$x]=trim($debet[$x]);
	$k_type[$x]=trim($k_type[$x]);
	$kredit[$x]=trim($kredit[$x]);
	$faktura[$x]=trim($faktura[$x]);
	$belob[$x]=trim($belob[$x]);

	if (($sort)&&($funktion)) {
		$funktion($sort, $fokus, $x, $id[$x], $kladde_id, $bilag[$x], $dato[$x], $beskrivelse[$x], $d_type[$x], $debet[$x], $k_type[$x], $kredit[$x], $faktura[$x], $belob[$x], $momsfri[$x], $afd[$x], $projekt[$x], $ansat[$x], $valuta[$x], $lobenr[$x]);
	}
	$y=0;
	$query = db_select("select kontonr from kontoplan where kontotype != 'H' and kontotype != 'Z' and regnskabsaar=$regnaar");
	while($row = db_fetch_array($query)) {
		$y++;
		$kontonr[$y]=trim($row['kontonr']);
	}
	if ($kladde_id) {	
		$row =db_fetch_array(db_select("select bogfort from kladdeliste where id = $kladde_id"));
		if (($row['bogfort']=='-')&&(($id[$x])||($lobenr[$x]||($x)))) {
			if ($id[$x]) db_modify("update tmpkassekl set d_type='$d_type[$x]', debet='$debet[$x]', k_type='$k_type[$x]', kredit='$kredit[$x]', faktura='$faktura[$x]', amount='$belob[$x]', momsfri='$momsfri[$x]', afd='$afd[$x]', projekt='$projekt[$x]', ansat='$ansat[$x]', valuta='$valuta[$x]' where id='$id[$x]' and kladde_id='$kladde_id'");
			elseif ($lobenr[$x]) {
				db_modify("update tmpkassekl set d_type='$d_type[$x]', debet='$debet[$x]', k_type='$k_type[$x]', kredit='$kredit[$x]', faktura='$faktura[$x]', amount='$belob[$x]', momsfri='$momsfri[$x]', afd='$afd[$x]', projekt='$projekt[$x]', ansat='$ansat[$x]', valuta='$valuta[$x]' where lobenr='$lobenr[$x]' and kladde_id='$kladde_id'");
			}
			else {
				db_modify("update tmpkassekl set d_type='$d_type[$x]', debet='$debet[$x]', k_type='$k_type[$x]', kredit='$kredit[$x]', faktura='$faktura[$x]', amount='$belob[$x]', momsfri='$momsfri[$x]', afd='$afd[$x]', projekt='$projekt[$x]', ansat='$ansat[$x]', valuta='$valuta[$x]' where lobenr='$x' and kladde_id='$kladde_id'");
			}
			kontroller($id[$x], $bilag[$x], $dato[$x], $beskrivelse[$x], $d_type[$x], $debet[$x], $k_type[$x], $kredit[$x], $faktura[$x], $belob[$x], $momsfri[$x], $kontonr, $kladde_id, $afd[$x], $projekt[$x], $ansat[$x], $valuta[$x], $x);
		}
		if ($fejl) $submit="Gem";
	}
}

if ($_POST) {
	$submit =trim(if_isset($_POST['submit']));
	$tidspkt =if_isset($_POST['tidspkt']);
	$kladde_id =if_isset($_POST['kladde_id']);
	$ny_dato =if_isset($_POST['ny_dato']);
	$kontrolkonto=trim(if_isset($_POST['kontrolkonto']));
	$bilagsnr=if_isset($_POST['bilagsnr']);
	$kladdenote = addslashes(trim(if_isset($_POST['kladdenote'])));
	$ny_kladdenote = addslashes(trim(if_isset($_POST['ny_kladdenote'])));
	$antal_ny=if_isset($_POST['antal_ny']);
	$antal_ex=if_isset($_POST['antal_ex']);
#	$momsfri=if_isset($_POST['momsfri']);
	$id=if_isset($_POST['id']);
	if ($kladde_id) db_modify("delete from tmpkassekl where kladde_id=$kladde_id");
	for ($x=1; $x<=$antal_ny;$x++) {
		$dato[$x]=NULL; $beskrivelse[$x]=NULL; $d_type[$x]=NULL; $debet[$x]=NULL; $k_type[$x]=NULL; $kredit[$x]=NULL; $faktura[$x]=NULL;
		$belob[$x]=NULL; $momsfri[$x]=NULL; $afd[$x]=NULL; $projekt[$x]=NULL; $ansat[$x]=NULL; $valuta[$x]=NULL;

		$y="bila".$x;
		$bilag[$x]=trim(if_isset($_POST[$y]));
		if (!$bilag[$x]) $bilag[$x]="0";  # PHR 02.09.06
		$y="dato".$x;
		$dato[$x]=trim(if_isset($_POST[$y]));
		$y="besk".$x;
		$beskrivelse[$x]=addslashes(trim(if_isset($_POST[$y])));
		while (strpos($beskrivelse[$x],"  ")) $beskrivelse[$x]=str_replace("  "," ",$beskrivelse[$x]);
		$y="d_ty".$x;
		$d_type[$x]=strtoupper(if_isset($_POST[$y]));
		$y="debe".$x;
		$debet[$x]=trim(if_isset($_POST[$y]));
		$y="k_ty".$x;
		$k_type[$x]=strtoupper(if_isset($_POST[$y]));
		$y="kred".$x;
		$kredit[$x]=trim(if_isset($_POST[$y]));
		$y="fakt".$x;
		$faktura[$x]=addslashes(trim(if_isset($_POST[$y])));
		$y="belo".$x;
		$belob[$x]=if_isset($_POST[$y]);
		$y="afd_".$x;
		$afd[$x]=if_isset($_POST[$y]);
		$y="proj".$x;
		$projekt[$x]=if_isset($_POST[$y]);
		$y="meda".$x;
		$ansat[$x]=if_isset($_POST[$y]);
		$y="valu".$x;
		$valuta[$x]=strtoupper(if_isset($_POST[$y]));
		$y="moms".$x;
		$momsfri[$x]=if_isset($_POST[$y]);
		if ($bilag[$x]=="=") $bilag[$x] = $bilag[$x-1];
		if ($bilag[$x]=="+") $bilag[$x] = $bilag[$x-1]+1;
		if (substr($bilag[$x],0,1)=="+") $bilag[$x] = $bilag[$x-1]+1;
		if ($dato[$x]=="=") $dato[$x] = $dato[$x-1];
		if ($beskrivelse[$x]=="=") $beskrivelse[$x] = $beskrivelse[$x-1];
		if ($d_type[$x]=="=") $d_type[$x] = $d_type[$x-1];
		if ($debet[$x]=="=") $debet[$x] = $debet[$x-1];
		if ($k_type[$x]=="=") $k_type[$x] = $k_type[$x-1];
		if ($kredit[$x]=="=") $kredit[$x] = $kredit[$x-1];
		if ($faktura[$x]=="=") $faktura[$x] = $faktura[$x-1];
		if ($belob[$x]=="=") $belob[$x] = $belob[$x-1];
		if ($afd[$x]=="=") $afd[$x] = $afd[$x-1];
		if ($ansat[$x]=="=") $ansat[$x] = $ansat[$x-1];
		if ($projekt[$x]=="=") $projekt[$x] = $projekt[$x-1];

		if ((!$dato[$x])&&(($beskrivelse[$x])||($debet[$x])||($kredit[$x]))) $dato[$x]=date("d-m-Y");
		if ((!$sletslut) && ($bilag[$x]=="->")) {
			$sletstart=$x;
			$bilag[$x]="-";
		}  
		if ($bilag[$x]=="<-") {
			$bilag[$x]="-";
			if ((!$sletslut) && ($sletstart)) $sletslut=$x;
		}
		if (($sletstart)&&($sletslut)&&($sletstart<$sletslut)) {
			for ($y=$sletstart; $y<=$sletslut; $y++) {
				$bilag[$y]="-";
				db_modify("update tmpkassekl set bilag= '$bilag[$y]' where lobenr='$y' and kladde_id='$kladde_id'");
			}
			$sletstart=''; $sletslut='';
		}
		if (strtoupper($debet[$x]) == 'D') $d_type[$x]='D';
		if (strtoupper($debet[$x]) == 'K') $d_type[$x]='K';
		if (strtoupper($kredit[$x]) == 'D') $k_type[$x]='D';
		if (strtoupper($kredit[$x]) == 'K') $k_type[$x]='K';
		if (($debet[$x])&&(($d_type[$x]!="F")||(strlen($debet[$x])>1))) {
			 if ($debet[$x]!=$debet[$x]*1) {
				 $alerttekst="Ulovlig v&aelig;rdi i debetfelt (Bilag nr $bilag[$x]) \n kladde ikke gemt!";
				 print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;	
				#$debet[$x]=$debet[$x]*1;
			}
		}
		if (($kredit[$x])&&(($k_type[$x]!="F")||(strlen($kredit[$x])>1))) {
			 if ($kredit[$x]!=$kredit[$x]*1) {
				 print "<BODY onLoad=\"javascript:alert('Ulovlig v&aelig;rdi i kreditfelt (Bilag nr $bilag[$x])')\">";
				$fejl=1;
				#$kredit[$x]=$kredit[$x]*1;
			}
		}
		if (($kredit[$x])&&(($k_type[$x]!="F")||(strlen($kredit[$x])>1))) {
			 if ($kredit[$x]!=$kredit[$x]*1) {
				 print "<BODY onLoad=\"javascript:alert('Ulovlig v&aelig;rdi i kreditfelt (Bilag nr $bilag[$x])')\">";
				$fejl=1;
				#$kredit[$x]=$kredit[$x]*1;
			}
		}
		if (($bilag[$x] && $bilag[$x]!='-' && $bilag[$x]==$bilag[$x-1])&&($dato[$x]!=$dato[$x-1])) {
			if (usdate($dato[$x])!=usdate($dato[$x-1])) print "<BODY onLoad=\"javascript:alert('Forskellige datoer i bilag $bilag[$x]')\">";
		}
		if ((strpos($bilag[$x],'+'))&&($kladde_id)) indsaet_linjer($kladde_id, $bilag[$x], $dato[$x], $beskrivelse[$x], $d_type[$x], $debet[$x]*1, $k_type[$x], $kredit[$x]*1, $faktura[$x], $belob[$x], $afd[$x]*1, $ansat[$x]*1, $projekt[$x]*1, $valuta[$x]);
		if (($bilag[$x])&&($bilag[$x]!="-")&&($bilag[$x]!="->")&&($bilag[$x]!="<-")&&(!strpos($bilag[$x],'+'))&&(!is_numeric($bilag[$x]))) {
			 print "<BODY onLoad=\"javascript:alert('Ulovlig v&aelig;rdi i bilagsfelt (Bilag nr $bilag[$x])')\">";
			$fejl=1;
		} elseif (($bilag[$x])&&($bilag[$x]!="-")&&($bilag[$x]!="->")&&($bilag[$x]!="<-")) $bilag[$x]=$bilag[$x]*1;
		if ($bilag[$x] == "-") {$dato[$x]=''; $beskrivelse[$x]=''; $d_type[$x]=''; $debet[$x]=''; $k_type[$x]=''; $kredit[$x]=''; $faktura[$x]=''; $belob[$x]=''; $momsfri[$x]=''; $afd[$x]=''; $projekt[$x]=''; $ansat[$x]=''; $valuta[$x]='';}
		if (!isset($id[$x])) $id[$x]='0';
		if (!$id[$x]) $id[$x]='0';
		if (!$kladde_id) {
			$tidspkt=microtime();
			$row = db_fetch_array(db_select("select MAX(id) AS ID from kladdeliste"));
			$kladde_id=$row['id']+1;
			$kladdedate=date("Y-m-d");	# OBS I naeste linje indsaettes tidspkt fratrukket 1 sek. Ellers bliver 1. gemning afvist af	"Refresktjek"
			db_modify("insert into kladdeliste (id, kladdenote, kladdedate, bogfort, hvem, oprettet_af, tidspkt) values ('$kladde_id', '$ny_kladdenote', '$kladdedate', '-', '$brugernavn', '$brugernavn', '$tidspkt')");
			$tidspkt=microtime();
		}
		if ($kladde_id) {
			$bilag[$x]=addslashes($bilag[$x]); $dato[$x]=addslashes($dato[$x]); $beskrivelse[$x]=addslashes($beskrivelse[$x]); $d_type[$x]=addslashes($d_type[$x]); $debet[$x]=addslashes($debet[$x]); $k_type[$x]=addslashes($k_type[$x]); $kredit[$x]=addslashes($kredit[$x]); $faktura[$x]=addslashes($faktura[$x]); $belob[$x]=addslashes($belob[$x]); $momsfri[$x]=addslashes($momsfri[$x]); $afd[$x]=addslashes($afd[$x]); $projekt[$x]=addslashes($projekt[$x]); $ansat[$x]=addslashes($ansat[$x]); $valuta[$x]=addslashes($valuta[$x]);
			db_modify("insert into tmpkassekl (lobenr, id, bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, faktura, amount, momsfri, afd, kladde_id, projekt, ansat, valuta) values ('$x', '$id[$x]', '$bilag[$x]', '$dato[$x]', '$beskrivelse[$x]', '$d_type[$x]', '$debet[$x]', '$k_type[$x]', '$kredit[$x]', '$faktura[$x]', '$belob[$x]', '$momsfri[$x]', '$afd[$x]', '$kladde_id', '$projekt[$x]', '$ansat[$x]', '$valuta[$x]')");
		}
		if ($fejl) $submit="Gem";
	}
	if ($fejl) $submit="Gem";
	if ($submit=="Kopier til ny") {
		kopier_til_ny($kladde_id, $bilagsnr, $ny_dato);
	}
	$fokus=$_POST['fokus'];
	if ($kladde_id) {
		$row = db_fetch_array(db_select("select tidspkt from kladdeliste where id=$kladde_id"));
		if ($tidspkt==$row['tidspkt']) { #Refreshtjek"
			print "<BODY onLoad=\"javascript:alert('Brug af refresh konstateret - handling ignoreret')\">";
		}
		else {
			db_modify("update kladdeliste set kladdenote = '$ny_kladdenote', hvem = '$brugernavn', tidspkt='$tidspkt' where id = '$kladde_id'");
			$kladdenote = $ny_kladdenote;
			if (!$kontonr) {
				$x=0;
				$query = db_select("select kontonr from kontoplan where kontotype != 'H' and kontotype != 'Z' and regnskabsaar=$regnaar");
				while($row = db_fetch_array($query)) {
					$x++;
					$kontonr[$x]=trim($row[kontonr]);
				}
				$acc_ant=$x;
			}
			if ($submit == "Opslag") $opslag_id=substr($fokus,4,strlen($fokus)-4);

			if ($kladde_id) {	
				$row =db_fetch_array(db_select("select bogfort from kladdeliste where id = $kladde_id"));
				if ($row['bogfort']=='-') {
					for ($x=0; $x<=$antal_ny; $x++) {
						if (!isset($bilag[$x]))$bilag[$x]=NULL;			if (!isset($dato[$x]))$dato[$x]=NULL; 			if (!isset($beskrivelse[$x]))$beskrivelse[$x]=NULL;	
						if (!isset($d_type[$x]))$d_type[$x]=NULL;		if (!isset($debet[$x]))$debet[$x]=NULL;			if (!isset($k_type[$x]))$k_type[$x]=NULL;
						if (!isset($kredit[$x]))$kredit[$x]=NULL;		if (!isset($faktura[$x]))$faktura[$x]=NULL;	if (!isset($belob[$x]))$belob[$x]=NULL;
						if (!isset($momsfri[$x]))$momsfri[$x]=NULL;	if (!isset($afd[$x]))$afd[$x]=NULL;
						if ((!$fejl)&&($x!=$opslag_id)&&(($beskrivelse[$x])||($debet[$x])||($kredit[$x]))) {
							kontroller($id[$x], $bilag[$x], $dato[$x], $beskrivelse[$x], $d_type[$x], $debet[$x], $k_type[$x], $kredit[$x], $faktura[$x], $belob[$x], $momsfri[$x], $kontonr, $kladde_id, $afd[$x], $projekt[$x], $ansat[$x], $valuta[$x], $x);
						}
						elseif ((!$fejl)&&($x!=$opslag_id)&&($bilag[$x]=="-")) {
							kontroller($id[$x], $bilag[$x], $dato[$x], $beskrivelse[$x], $d_type[$x], $debet[$x], $k_type[$x], $kredit[$x], $faktura[$x], $belob[$x], $momsfri[$x], $kontonr, $kladde_id, $afd[$x], $projekt[$x], $ansat[$x], $valuta[$x], $x );
						}
					}
					if ($fejl) $submit="Gem";
				}
			}
#******************************
			if ($submit == "Opslag") {
				if (strtoupper($debet[$opslag_id])=="K") {$d_type[$opslag_id]="K";}
				elseif (strtoupper($debet[$opslag_id])=="D") {$d_type[$opslag_id]="D";}
#				else {$d_type[$opslag_id]="F";}
				if (strtoupper($kredit[$opslag_id])=="K") {$k_type[$opslag_id]="K";}
				elseif (strtoupper($kredit[$opslag_id])=="D") {$k_type[$opslag_id]="D";}
#				else {$k_type[$opslag_id]="F";}
				$d_type[$opslag_id]=trim(strtoupper($d_type[$opslag_id]));
				$k_type[$opslag_id]=trim(strtoupper($k_type[$opslag_id]));
				if ((strstr($fokus,"debe"))||(strstr($fokus,"d_ty"))) {
					if($d_type[$opslag_id]=="K") kreditoropslag('firmanavn', $fokus, $opslag_id, $id[$opslag_id], $kladde_id, $bilag[$opslag_id], $dato[$opslag_id], $beskrivelse[$opslag_id], $d_type[$opslag_id], $debet[$opslag_id], $k_type[$opslag_id], $kredit[$opslag_id], $faktura[$opslag_id], $belob[$opslag_id], $momsfri[$opslag_id], $afd[$opslag_id], $projekt[$x], $ansat[$x], $valuta[$x], $opslag_id);
					elseif($d_type[$opslag_id]=="D") debitoropslag('firmanavn', $fokus, $opslag_id, $id[$opslag_id], $kladde_id, $bilag[$opslag_id], $dato[$opslag_id], $beskrivelse[$opslag_id], $d_type[$opslag_id], $debet[$opslag_id], $k_type[$opslag_id], $kredit[$opslag_id], $faktura[$opslag_id], $belob[$opslag_id], $momsfri[$opslag_id], $afd[$opslag_id], $projekt[$x], $ansat[$x], $valuta[$x], $opslag_id);
					else finansopslag($fokus, $opslag_id);
				}
				if ((strstr($fokus,"kred"))||(strstr($fokus,"k_ty"))) {
					if($k_type[$opslag_id]=="K") kreditoropslag('firmanavn', $fokus, $opslag_id, $id[$opslag_id], $kladde_id, $bilag[$opslag_id], $dato[$opslag_id], $beskrivelse[$opslag_id], $d_type[$opslag_id], $debet[$opslag_id], $k_type[$opslag_id], $kredit[$opslag_id], $faktura[$opslag_id], $belob[$opslag_id], $momsfri[$opslag_id], $afd[$opslag_id], $projekt[$x], $ansat[$x], $valuta[$x], $opslag_id);
					if($k_type[$opslag_id]=="D") debitoropslag('firmanavn', $fokus, $opslag_id, $id[$opslag_id], $kladde_id, $bilag[$opslag_id], $dato[$opslag_id], $beskrivelse[$opslag_id], $d_type[$opslag_id], $debet[$opslag_id], $k_type[$opslag_id], $kredit[$opslag_id], $faktura[$opslag_id], $belob[$opslag_id], $momsfri[$opslag_id], $afd[$opslag_id], $projekt[$x], $ansat[$x], $valuta[$x], $opslag_id);
					else {finansopslag($fokus, $opslag_id);}
				}
				if ((strstr($fokus,"fakt"))||(strstr($fokus,"belo"))) openpost ('firmanavn', $fokus, $opslag_id, $id[$opslag_id], $kladde_id, $bilag[$opslag_id], $dato[$opslag_id], $beskrivelse[$opslag_id], $d_type[$opslag_id], $debet[$opslag_id], $k_type[$opslag_id], $kredit[$opslag_id], $faktura[$opslag_id], $belob[$opslag_id], $momsfri[$opslag_id], $afd[$opslag_id], $projekt[$x], $ansat[$x], $valuta[$x], $opslag_id);
				if (strstr($fokus,"afd")) {afd_opslag ($fokus, $opslag_id, $opslag_id);}
				if (strstr($fokus,"meda")) {ansat_opslag ($fokus, $opslag_id, $opslag_id);}
				if (strstr($fokus,"proj")) {projekt_opslag ($fokus, $opslag_id, $opslag_id);}
				if (strstr($fokus,"valu")) {valuta_opslag ($fokus, $opslag_id, $opslag_id);}
		 	}
			if (strstr($submit,"Simul")) {
				?>
				<body onload="simuler()">
				<?php
			}
			if (strstr($submit,"Bogf"))	{
				print "<meta http-equiv=\"refresh\" content=\"0;URL=bogfor.php?kladde_id=$kladde_id&funktion=bogfor\">";
			}
			if (strstr($submit,"Tilbagef")){
				tilbagefor($kladde_id);
			}
			if (strstr($submit,"Hent")) {
				print "<meta http-equiv=\"refresh\" content=\"0;URL=hentordrer.php?kladde_id=$kladde_id\">";
			}
			if (strstr($submit,"Impor")) {
				print "<meta http-equiv=\"refresh\" content=\"0;URL=bankimport.php?kladde_id=$kladde_id&bilagsnr=$bilagsnr\">";
			}
			if (strstr($submit,"Udlig")) {
				print "<meta http-equiv=\"refresh\" content=\"0;URL=autoudlign.php?kladde_id=$kladde_id\">";
			}
		}	 
	}
}# endif ($_POST)
if ($r=db_fetch_array(db_select("select id from adresser where art = 'S'"))) {
	$egen_kto_id = $r['id'];
	$z=0;
	$q=db_select("select id, initialer from ansatte where konto_id = $egen_kto_id and lukket != 'on' order by id");
	while ($r=db_fetch_array($q)) {
		$z++;
		$vis_ansat=1;
		$ansat_id[$z]=$r['id'];
		$ansat_init[$z]=$r['initialer'];
	}
}
if (db_fetch_array(db_select("select * from grupper where ART = 'AFD'"))) $vis_afd=1;
if (db_fetch_array(db_select("select * from grupper where ART = 'PRJ'"))) $vis_projekt=1;
if (db_fetch_array(db_select("select * from grupper where ART = 'VK'"))) $vis_valuta=1;

if (!$fejl && $kladde_id) {
	opdater($kladde_id);	
	db_modify ("delete from tmpkassekl where kladde_id=$kladde_id");
}
if (strlen($kontrolkonto)==1) {
	$kontrolkonto=strtoupper($kontrolkonto);
	$query = db_select("select * from kontoplan where genvej='$kontrolkonto' and regnskabsaar='$regnaar'");
	if ($row = db_fetch_array($query)) $kontrolkonto=$row[kontonr];
	else {
		$kontrolkonto=' ';
		setcookie("saldi_ktrkto",$kontrolkonto,time()+0);
	}
}
if (strlen($kontrolkonto)>1) setcookie("saldi_ktrkto",$kontrolkonto,time()+60*60*24*30);
ob_end_flush();	//Sender det "bufferede" output afsted... 
if ($kladde_id) {
	$query = db_select("select kladdenote, bogfort from kladdeliste where id = $kladde_id");
	$row = db_fetch_array($query);
	$kladdenote = htmlentities(stripslashes($row['kladdenote']));
	$bogfort = $row['bogfort'];

}
$x=0;
if (!$simuler) {
	if ($returside != "regnskab"){$returside="kladdeliste.php";}
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height=\"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td onClick=\"JavaScript:opener.location.reload();\" width=\"10%\" $top_bund>$font<small >";
	if ($returside=="kladdeliste.php") 
	{
		 print "<a href=\"../includes/luk.php?tabel=kladdeliste&amp;id=$kladde_id\" accesskey=\"L\">Luk</a></small></td>";
#		print "<a href=\"../includes/luk.php?tabel=kladdeliste&amp;id=$kladde_id\" accesskey=\"T\" onClick=\"opener.location.reload(kladdeliste.php);self.close()\">Luk</a></small></td>";

	} else {
		print "<a href=\"$returside\" accesskey=\"L\">Luk</a></small></td>";}
	print "<td width=\"80%\" $top_bund>$font<small>Kassekladde $kladde_id</small></td>";
	print "<td width=\"10%\" $top_bund align=\"right\">$font<small><a href=kassekladde.php accesskey=N>Ny</a></small></td>";
	print "</tbody></table>";
	print "</td></tr>\n";
}
print "<tr><td valign=\"top\">";
if(($bogfort)&&($bogfort!='-')) {print "<table cellpadding=\"0\" cellspacing=\"1\" border=\"1\" align = \"center\" valign = \"top\">";}
else {print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align = \"center\" valign = \"top\" id=\"inputTable\" onkeydown=\"doKeyDown()\">";}
print "</tr>\n<tbody>";
print "<form name=\"kassekladde\" action=\"kassekladde.php\" method=\"post\">";
print "<input type=\"hidden\" name=\"kladde_id\" value=\"$kladde_id\">";
print "<input type=\"hidden\" name=\"kladdenote\" value=\"$kladdenote\">";
print "<tr>";
if (($bogfort)&&($bogfort!='-')){print "<td colspan=10 align=center><b>$font $kladdenote</b></td>";}
else {
	print "<td><small><b>$font <span title= 'Her kan skrives en bem&aelig;rkning til kladden'>Bem&aelig;rkning:</b></small></td>";
	print "<td colspan=10><input type=\"text\" size=95 name=ny_kladdenote value=\"$kladdenote\"></td>";
	if (isset($_COOKIE['saldi_ktrkto'])) $kontrolkonto = $_COOKIE['saldi_ktrkto'];
	if ($kontrolkonto == "-") $kontrolkonto = "";
	print "<td><span title= 'Angiv kontonummer til kontrol af kontobev&aelig;gelser'><input type=\"text\" style=text-align:right size=8 name=kontrolkonto value=\"$kontrolkonto\"></td>";
	print "</tr><tr><td colspan=13><hr></td></tr>\n";
}
print "<tr>";
print "<td align = center><small><b>$font Bilag</b></small></td>";
print "<td align = center><small><b>$font <span title= 'Angiv dato som ddmmyy (f.eks 241205)'>Dato</a></b></small></td>";
print "<td align = center><small><b>$font Bilagstekst</b></small></td>";
print "<td align = center><small><b>$font <span title= 'Angiv D for debitor, K for kreditor eller F for finanspostering'>D/K</b></small></td>";
print "<td align = center><small><b>$font <span title= 'Skriv D eller K og klik p&aring; [Opslag] for opslag i hhv, debitor- eller kreditorkartotek'>Debet</b></small></td>";
print "<td align = center><small><b>$font <span title= 'Angiv D for debitor, K for kreditor eller F for finanspostering'>D/K</b></small></td>";
print "<td align = center><small><b>$font <span title= 'Skriv D eller K og klik p&aring; [Opslag] for opslag i hhv, debitor- eller kreditorkartotek'>Kredit</b></small></td>";
print "<td align = center><small><b>$font <span title= 'Angiv fakturanummer - klik p&aring; opslag for at sl&aring; op i &aring;bne poster'>Fakturanr.</b></small></td>";
print "<td align = center><small><b>$font <span title= 'Angiv belob - klik p&aring; opslag for at sl&aring; op i &aring;bne poster'>Bel&oslash;b</b></small></td>";
if ($vis_afd) print "<td align = left><small><b>$font <span title= 'Angiv hvilken afdeling posteringen h&oslash;rer under'>Afd.</b></small></td>";
if ($vis_ansat) print "<td align = left><small><b>$font <span title= 'Angiv hvilket ansatejder posteringen h&oslash;rer under'>ansat.</b></small></td>";
if ($vis_projekt) print "<td align = left><small><b>$font <span title= 'Angiv hvilket projekt posteringen h&oslash;rer under'>Proj.</b></small></td>";
if ($vis_valuta )print "<td align = left><small><b>$font <span title= 'Angiv valuta for posteringen'>Valuta</b></small></td>";
print "<td align = left><small><b>$font <span title= 'Afm&aelig;rk her, hvis der ikke skal tr&aelig;kkes moms'>&nbsp;u/m</b></small></td>";
print "</tr>\n";

#####################################  Output  #################################	
$x=0;
if ($kladde_id) {
$id=array(); $bilag=array(); $dato=array(); $beskrivelse=array(); $d_type=array(); $debet=array(); $k_type=array(); $kredit=array(); 
$faktura=array(); $belob=array(); $afd=array(); $ansat=array(); $ansat_id=array(); $projekt=array(); $valuta=array(); $momsfri=array();
	print "<meta http-equiv=\"refresh\" content=\"3600;URL=../includes/luk.php?tabel=kladdeliste&id=$kladde_id\">";
	$query = db_select("select * from tmpkassekl where kladde_id = $kladde_id order by lobenr");
	if ($row=	db_fetch_array($query)) {
		$query = db_select("select * from tmpkassekl where kladde_id = $kladde_id order by lobenr");
		$fejl=1;
	}
 	else	$query = db_select("select * from kassekladde where kladde_id = $kladde_id order by bilag, transdate, id");
	$bilagssum=0;
	while ($row = db_fetch_array($query)) {
/*
		if ($x>1 && $row['bilag'] == $bilag[$x] && !$fejl) {
			if ($row['kredit']) $bilagssum=$bilagssum-$row['amount'];
			if ($row['debet']) $bilagssum=$bilagssum+$row['amount'];
		} elseif ($x>1 && $bilagssum != 0 && !$fejl) {
			$y=$x;
			$x++;
			$bilag[$x]=$bilag[$x-1];$dato[$x]=dkdato($transdate[$x-1]);$beskrivelse[$x]=$beskrivelse[$x-1];$amount[$x]=abs($bilagssum);$belob[$x]=dkdecimal($amount[$x]);
			$afd[$x]=$afd[$x-1];$projekt[$x]=$projekt[$x-1];$medarb[$x]=$medarb[$x-1];$momsfri[$x]=$momsfri[$x-1];
			$bilagssum=0;
			if ($row['kredit']) $bilagssum=$bilagssum-$row['amount'];
			if ($row['debet']) $bilagssum=$bilagssum+$row['amount'];
		} elseif ($x>1 && !$fejl) {
#			$bilagssum=0;
			if ($row['kredit']) $bilagssum=$bilagssum-$row['amount'];
			if ($row['debet']) $bilagssum=$bilagssum+$row['amount'];
		} 
*/
		$x++;
		$valuta[$x]='DKK';
		$id[$x]=$row['id'];
		$bilag[$x]=$row['bilag'];
		if ($fejl) {
			$transdate[$x]=usdate($row['transdate']);
			$dato[$x]=$row['transdate'];
		}	else {
			$transdate[$x]=$row['transdate'];
			$dato[$x]=dkdato($row['transdate']);
		}
		$beskrivelse[$x]=htmlentities(stripslashes($row['beskrivelse']));
		$d_type[$x]=trim($row['d_type']);
		$debet[$x]=$row['debet'];
		$k_type[$x]=$row['k_type'];
		$kredit[$x]=$row['kredit'];
		$faktura[$x]=htmlentities(stripslashes($row['faktura']));
		$amount[$x]=$row['amount'];
		if ($fejl) $belob[$x] = $row['amount'];
		else $belob[$x]=dkdecimal($row['amount']);
		$momsfri[$x]=$row['momsfri'];
		$afd[$x]=$row['afd'];
		if ($fejl) $ansat=$row['ansat'];
		else $ansat_id[$x]=$row['ansat'];
		if ($ansat_id[$x]) {
			$r2 = db_fetch_array(db_select("select navn, initialer from ansatte where id='$ansat_id[$x]'"));
			$ansat[$x]=$r2['initialer'];
			$ansat_navn[$x]=$r2['navn'];
		}
		$projekt[$x]=$row['projekt'];
		if ($fejl) $valuta[$x]=$row['valuta'];
		else {
			$valutakode[$x]=$row['valuta'];
			if ($valutakode[$x]) {
				$r2 = db_fetch_array(db_select("select box1 from grupper where art='VK' and kodenr ='$valutakode[$x]'"));
				$valuta[$x]=$r2['box1'];
			}
		}
		if (!$beskrivelse) {$beskrivelse='';}
		if (($d_type[$x]=='F')&&($debet[$x])&&(!$fejl)) {
			$query2 = db_select("select beskrivelse, moms from kontoplan where kontonr='$debet[$x]'");
			$row2 = db_fetch_array($query2);
			$debettext[$x]=$row2['beskrivelse'];
			if (trim($row2['moms'])) $debettext[$x]=$debettext[$x]."&nbsp;-&nbsp;".trim($row2['moms']);
		}
		if ((($d_type[$x]=='D')||($d_type[$x]=='K'))&&($debet[$x])&&(!$fejl)) {
			$query2 = db_select("select firmanavn from adresser where kontonr='$debet[$x]' and art = '$d_type[$x]'");
			$row2 = db_fetch_array($query2);
			$debettext[$x]=trim($row2['firmanavn']);
		}
		if (($k_type[$x]=='F')&&($kredit[$x])&&(!$fejl)) {
			$query2 = db_select("select beskrivelse, moms from kontoplan where kontonr='$kredit[$x]'");
			$row2 = db_fetch_array($query2);
			$kredittext[$x]=trim($row2['beskrivelse']);
			if (trim($row2['moms'])) $kredittext[$x]=$kredittext[$x]."&nbsp;-&nbsp;".trim($row2['moms']);
		}
		if ((($k_type[$x]=='D')||($k_type[$x]=='K'))&&($kredit[$x])&&(!$fejl)) {
			$query2 = db_select("select firmanavn from adresser where kontonr='$kredit[$x]' and art = '$k_type[$x]'");
			$row2 = db_fetch_array($query2);
			$kredittext[$x]=trim($row2['firmanavn']);
		}
	}
	if (!$fejl) db_modify ("delete from tmpkassekl where kladde_id=$kladde_id");
}
for ($y=1; $y<=$x; $y++)
if (!$fejl) $antal_ex=$x;	
if (($bogfort)&&($bogfort!='-')) {
	for ($y=1; $y<=$x; $y++) {
		if (!$beskrivelse[$y]){$beskrivelse[$y]="&nbsp;";}
#		if (($d_type[$y]!="D")&&($d_type[$y]!="K")) $d_type[$y]="F"; #phr 20070801
		if ($debet[$y] < 1){
			$debet[$y]="&nbsp;";
			$d_type[$y]="&nbsp;"; #phr 20070801
		}
#		if (($k_type[$y]!="D")&&($k_type[$y]!="K")) $k_type[$y]="F"; #phr 20070801
		if ($kredit[$y] < 1){
			$kredit[$y]="&nbsp;";
			$k_type[$y]="&nbsp;"; #phr 20070801
		}
		if (!$faktura[$y]){$faktura[$y]="&nbsp;";}
		print "<tr>";
		print "<td><small>$font $bilag[$y]</small></td>";
		print "<td><small>$font $dato[$y]</td></small>";
		print "<td><small>$font $beskrivelse[$y]</small></td>";
		print "<td><small>$font $d_type[$y]</small></td>";
		print "<td align=right title='$debettext[$y]'><small>$font $debet[$y]</small></td>";
		print "<td><small>$font $k_type[$y]</small></td>";
		print "<td align=right title='$kredittext[$y]'><small>$font $kredit[$y]</small></td>";
		print "<td align=right><small>$font $faktura[$y]</small></td>";
		print "<td align=right><small>$font $belob[$y]</small></td>";
		if ($vis_afd) print "<td align=right><small>$font $afd[$y]</small></td>";
		if ($vis_ansat) print "<td align=right><small>$font $ansat[$y]</small></td>";
		if ($vis_projekt) print "<td align=right><small>$font $projekt[$y]</small></td>";
		if ($vis_valuta) print "<td align=right><small>$font $valuta[$y]</small></td>";
		if (strstr($momsfri[$y],"on")) {print "<td align=center><small>$font V</small></td>";}
		else {print "<td><small>$font <br></small></td>";}
		print "</tr>\n";
	}
	if ($bogfort=='V'){
		print "<input type=hidden name=ny_kladdenote value=\"$kladdenote\">";
		print "<tr><td colspan=9 align=center><input type=submit accesskey=\"k\" value=\"Kopier til ny\" name=\"submit\"></td></tr>\n";
		print "</form>";
		print "</tbody></table></td></tr>\n";
		print "</tbody></table>";
	}
	elseif ($bogfort=='!'){
		print "<input type=hidden name=ny_kladdenote value=\"$kladdenote\">";
		print "<tr><td colspan=9 align=center><input type=submit accesskey=\"b\" value=\"Tilbagef&oslash;r\" name=\"submit\"></td></tr>\n";
		print "</form>";
		print "</tbody></table></td></tr>\n";
		print "</tbody></table>";
	}
} else { ################################ Kladden er ikke bogfort ########################################
	$debetsum=0;
	$kreditsum=0;
	if ($kontrolkonto) {
		$kontrolkonto=$kontrolkonto*1;
		$query = db_select("select saldo from kontoplan where kontonr='$kontrolkonto' and regnskabsaar='$regnaar'");
		if ($row = db_fetch_array($query)) {
			$kontrolsaldo=$row['saldo'];
		}
		else { 
			$kontrolkonto='';
		}
	}
	if(!isset($bilag[0]))$bilag[0]=NULL; if(!isset($bilag[$x+1]))$bilag[$x+1]=NULL;
	if(!isset($dato[0]))$dato[0]=NULL; if(!isset($dato[$x+1]))$dato[$x+1]=NULL;
	for ($y=1; $y<=$x; $y++) {
		if (!isset($bilag[$y]))$bilag[$y]=NULL; if (!isset($dato[$y]))$dato[$y]=NULL;
		if (!isset($kredit[$y]))$kredit[$y]=NULL; if (!isset($debet[$y]))$debet[$y]=NULL;
		if (!isset($kredittext[$y]))$kredittext[$y]=NULL; if (!isset($debettext[$y]))$debettext[$y]=NULL;

		if ((!$fejl)&&((($bilag[$y])=="-")||(!$bilag[$y])&&(!$dato[$y]))) {
			$bilag[$y]=''; $dato[$y]=''; $beskrivelse[$y]=''; $d_type[$y]=''; $debet[$y]=''; $k_type[$y]=''; $kredit[$y]=''; $faktura[$y]=''; $belob[$y]=''; $momsfri[$y]=''; $afd[$y]=''; $projekt[$y]=''; $valuta[$y]='';
		}
		if ($fejl&&!$dato[$y]&&!$beskrivelse[$y]&&!$debet[$y]&&!$kredit[$y]&&!$faktura[$y]&&!$belob[$y]) $bilag[$y]='';
		if (!$fejl&&$debet[$y] < 1) $debet[$y]="";
		if (!$fejl&&$kredit[$y] < 1) $kredit[$y]="";
		if ($fejl) $amount[$y]=usdecimal($amount[$y]); # phr 20070801
		if (!$debet[$y]) $debet[$y]="";
		if (!$kredit[$y]) $kredit[$y]="";
		if($valuta[$y]&&$valuta[$y]!='DKK') {
		if ($r=db_fetch_array(db_select("select kodenr from grupper where art='VK' and box1='$valuta[$y]'"))) {
			if ($r=db_fetch_array(db_select("select kurs from valuta where gruppe='$r[kodenr]' and valdate < '$transdate[$y]' order by valdate desc"))) {
				$dk_amount=$amount[$y]*$r['kurs']/100;
				} else $dk_amount=0; 
			} else $dk_amount=$amount[$y];
		} else $dk_amount=$amount[$y];
		if ($debet[$y]==$kontrolkonto) $kontrolsaldo=$kontrolsaldo+$dk_amount;
		if ($kredit[$y]==$kontrolkonto) $kontrolsaldo=$kontrolsaldo-$dk_amount;
		print "<tr>";

		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=bila$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$bilag[$y]\"\">&nbsp;</td>";
		print "<td><input type=\"text\" size=10 name=dato$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$dato[$y]\">&nbsp;</td>";
		print "<td><input type=\"text\" size=25 name=besk$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$beskrivelse[$y]\">&nbsp;</td>";
		print "<td><input type=\"text\" size=1 name=d_ty$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$d_type[$y]\">&nbsp;</td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=debe$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$debet[$y]\" title=\"$debettext[$y]\">&nbsp;</td>";
		print "<td><input type=\"text\" size=1 name=k_ty$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$k_type[$y]\">&nbsp;</td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=kred$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$kredit[$y]\" title= \"$kredittext[$y]\">&nbsp;</td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=fakt$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$faktura[$y]\">&nbsp;</td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=belo$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$belob[$y]\">&nbsp;</td>";
		if ($vis_afd) print "<td><input type=\"text\" style=\"text-align:right\" size=5 name=afd_$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$afd[$y]\" >&nbsp;</td>";
		if ($vis_ansat) print "<td><input type=\"text\" style=\"text-align:right\" size=5 name=meda$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$ansat[$y]\" >&nbsp;</td>";
		if ($vis_projekt) print "<td><input type=\"text\" style=\"text-align:right\" size=5 name=proj$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$projekt[$y]\" >&nbsp;</td>";
		if ($vis_valuta) print "<td><input type=\"text\" size=4 name=valu$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$valuta[$y]\" ></td>";
		if ($momsfri[$y] == 'on') {print "<td><input type=checkbox name=moms$y checked></td>";}
		else {print"<td><input type=checkbox name=moms$y></td>";}
		if ($kontrolkonto) {print "<td align=right>$font<small>".dkdecimal($kontrolsaldo) ."</td>";}
		print "<input type=hidden name=id[$y] value=$id[$y]>";
		print "</tr>\n";
		if ($bilag[$y] != $bilag[$y-1]) {
			$debetsum=0;
			$kreditsum=0;
			$amount[$x+1]=0;
		}
		if ((($debet[$y])||($kredit[$y]))&&($amount[$y] > 0)) {
			if (($debet[$y])||($debet[$y]>0)) {$debetsum=$debetsum+$amount[$y];}
			if (($kredit[$y])||($kredit[$y]>0)) {$kreditsum=$kreditsum+$amount[$y];}
			if ((!$bilag[$x+1])||($bilag[$x+1]<$bilag[$y])) {$bilag[$x+1]=$bilag[$y];}
			if (!$dato[$x+1]) {$dato[$x+1]=$dato[$y];}
			$amount[$x+1]=$debetsum-$kreditsum;
		}
	}
	if (!isset($amount[$x+1]))$amount[$x+1]=0;
	if (abs($amount[$x+1])>0.01) {
	 	$beskrivelse[$x+1]=$beskrivelse[$x];
		$bilag[$x+1]=$bilag[$x];
		$dato[$x+1]=$dato[$x];
	}
#	else {$bilag[$x+1]=$bilag[$x]+1;}
# Udeladt 121207 - Har vis ingen funktion??
# Genindsat 060408 - Tildeler bilagsnummer hvis bilag i balance
	elseif ($bilag[$x+1]==$bilag[$x]) {
		if (($amount[$p] == 0)&&($amount[$x] > 0)) {
			$amount[$x+1]='';
			$bilag[$x+1]=$bilag[$x]+1;
			$dato[$x+1]=$dato[$x];
		}
	}#end if($bilag[$x+1]==$bilag[$x])

	if ($x > 20) {$y=$x+5;}
	else {$y=24;}
	$x++;
	if ($amount[$x]<0){$amount[$x]=$amount[$x]*-1;}
	if ($amount[$x]) $belob=dkdecimal($amount[$x]);
	else $belob="";
	if (!isset($amount[$x-1]))$amount[$x-1]=0;
	if (($amount[$x-1])&&($amount[$x-1]<0.01)) {
		$bilag[$x]="";
		$dato[$x]="";
		$belob="";
	}
	if ($fokus&&(strstr($fokus,"belo")||strstr($fokus,"afd"))&&strstr($submit,"Gem")) $fokus=nextfokus($fokus);
	if (!isset($dato[$y]))$dato[$y]=NULL;			if (!isset($beskrivelse[$y]))$beskrivelse[$y]=NULL;	if (!isset($debet[$y]))$debet[$y]=NULL;	
	if (!isset($kredit[$y]))$kredit[$y]=NULL;	if (!isset($faktura[$y]))$faktura[$y]=NULL; if (!isset($valuta[$y]))$valuta[$y]=NULL;					
	if (((($bilag[$x]=="-")||(!$dato[$y]&&!$beskrivelse[$y]&&!$debet[$y]&&!$kredit[$y]&&!$faktura[$y]&&!$amount[$x]))&&($x==1))||(!$kladde_id)) {
		$bilag[$x]=1;
		if (!$regnstart) list ($regnstart, $regnslut) = split(":",regnskabsaar($regnaar));
		$query = db_select("select MAX(bilag) as bilag from kassekladde where transdate>='$regnstart' and transdate<='$regnslut'");
		if ($row = db_fetch_array($query)) $bilag[$x]=$row['bilag']+1;
	}
	if (!isset($debet[$x-1]))$debet[$x-1]=NULL;	if (!isset($kredit[$x-1]))$kredit[$x-1]=NULL;

	if (($bilag[$x])&&(!$dato[$x])){$dato[$x]=dkdato(date("Y-m-d"));}
	if ($x<300 && (($debet[$x-1])||($kredit[$x-1])||$x==1)) {

	if (!isset($dato[$x]))$dato[$x]=NULL;			if (!isset($beskrivelse[$x]))$beskrivelse[$x]=NULL;	if (!isset($debet[$x]))$debet[$x]=NULL;	
	if (!isset($kredit[$x]))$kredit[$x]=NULL;	if (!isset($faktura[$x]))$faktura[$x]=NULL;					if (!isset($d_type[$x]))$d_type[$x]=NULL;	
	if (!isset($k_type[$x]))$k_type[$x]=NULL;	if (!isset($afd[$x]))$afd[$x]=NULL;									if (!isset($momsfri[$x]))$momsfri[$x]=NULL;
	if (!isset($projekt[$x]))$projekt[$x]=NULL;if (!isset($valuta[$x]))$valuta[$x]=NULL;
		print "<tr>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=bila$x onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$bilag[$x]\"></td>";
		print "<td><input type=\"text\" size=10 name=\"dato$x\" onfocus=\"document.forms[0].fokus.value=this.name;\"value =\"$dato[$x]\"></td>";
		print "<td><input type=\"text\" size=25 name=besk$x onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$beskrivelse[$x]\"></td>";
		print "<td><input type=\"text\" size=1 name=\"d_ty$x\" onfocus=\"document.forms[0].fokus.value=this.name;\" onBlur=\"validerFelt(this,'Gyldig v&aelig;rdi: D, K eller Blank');\" value =\"$d_type[$x]\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=debe$x onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$debet[$x]\"></td>";
		print "<td><input type=\"text\" size=1 name=k_ty$x onfocus=\"document.forms[0].fokus.value=this.name;\" onBlur=\"validerFelt(this,'Gyldig v&aelig;rdi: D, K eller Blank');\" value =\"$k_type[$x]\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=kred$x onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kredit[$x]\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=fakt$x onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$faktura[$x]\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=belo$x onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$belob\"></td>";
		if ($vis_afd) print "<td><input type=\"text\" style=\"text-align:right\" size=5 name=afd_$x onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$afd[$x]\"></td>";
		if ($vis_ansat) print "<td><input type=\"text\" style=\"text-align:right\" size=5 name=meda$x onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$ansat[$x]\" ></td>";
		if ($vis_projekt) print "<td><input type=\"text\" style=\"text-align:right\" size=5 name=proj$x onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$projekt[$x]\" ></td>";
		if ($vis_valuta) print "<td><input type=\"text\" size=4 name=valu$x onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$valuta[$x]\" ></td>";
		if ($momsfri[$x] == 'on') {print"<td><input type=checkbox name=moms$y checked></td>";}
		else {print"<td><input type=checkbox name=moms$y></td>";}
	}
	if ($x!=1 || $bilag[$x]) $bilagsnr=$bilag[$x];
	if ($x < 300) {
		if ($x > 20) {$y=$x+5;}
		else {$y=24;}
		if ($fejl) $y=$x;
	} else $y=$x-1; 
	$x++;
	if ($x==1) {$bilag[1]=''; $dato[1]=''; $beskrivelse[1]=''; $d_type[1]=''; $debet[1]=''; $k_type[1]=''; $kredit[1]=''; $faktura[1]=''; $belob[1]=''; $momsfri[1]=''; $afd[1]='';}
	for ($z=$x; $z<=$y; $z++) {
		print "<tr>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=bila$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td><input type=\"text\" size=10 name=dato$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td><input type=\"text\" size=25 name=besk$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td><input type=\"text\" size=1 name=d_ty$z onfocus=\"document.forms[0].fokus.value=this.name;\" onBlur=\"validerFelt(this,'Gyldig v&aelig;rdi: D, K eller Blank');\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=debe$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td><input type=\"text\" size=1 name=k_ty$z onfocus=\"document.forms[0].fokus.value=this.name;\" onBlur=\"validerFelt(this,'Gyldig v&aelig;rdi: D, K eller Blank');\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=kred$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=fakt$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=belo$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		if ($vis_afd) print "<td><input type=\"text\" style=\"text-align:right\" size=5 name=afd_$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		if ($vis_ansat) print "<td><input type=\"text\" style=\"text-align:right\" size=5 name=meda$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		if ($vis_projekt) print "<td><input type=\"text\" style=\"text-align:right\" size=5 name=proj$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		if ($vis_valuta) print "<td><input type=\"text\" size=4 name=valu$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td><input type=checkbox name=momsfri[$z]></td>";
		print "</tr>\n";
	}
	print "<input type=\"hidden\" name=\"fokus\" id=\"fokus\">";
	print "<input type=hidden name=kladde_id value=$kladde_id>";
	$tidspkt=microtime();
	print "<input type=hidden name=tidspkt value=$tidspkt>";
	print "<input type=hidden name=bilagsnr value=$bilagsnr>";
	print "<input type=hidden name=antal_ex value='$antal_ex'>";
	print "<input type=hidden name=antal_ny value='$y'>";
	print "<tr><td colspan=13><hr></td></tr>\n";
	print "<td align=center colspan=13>";
	print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"1\"><tbody><tr>";
	print "<td align=center><input type=submit accesskey=\"g\" value=\"&nbsp;&nbsp;Gem&nbsp;&nbsp;\" name=\"submit\"></td>";
	print "<td align=center><input type=submit accesskey=\"o\" value=\"Opslag\" name=\"submit\"></td>";
	if ($kladde_id && !$fejl) {
		print "<td align=center><input type=submit accesskey=\"s\" value=\"Simuler\" name=\"submit\"></td>";
		print "<td align=center><input type=submit accesskey=\"b\" value=\"Bogf&oslash;r\" name=\"submit\"></td>";
		if (!$fejl && db_fetch_array(db_select("select id from ordrer where status=3"))) {
			print "<td align=center><input type=submit accesskey=\"h\" value=\"Hent ordrer\" name=\"submit\"></td>";
		}
		print "<td align=center><input type=submit accesskey=\"i\" value=\"bankImport\" name=\"submit\"></td>";
		print "<td align=center><input type=submit accesskey=\"u\" value=\"Udlign\" name=\"submit\"></td>";
	}
	print "</form>";
	print "</tbody></table></td></tr>\n";
	print "</tbody></table>";
} #end if $bogfort...else

######################################################################################################################################
function debitoropslag($sort, $fokus, $opslag_id, $id, $kladde_id, $bilag, $dato, $beskrivelse, $d_type, $debet, $k_type, $kredit, $faktura, $belob, $momsfri, $afd, $projekt, $ansat, $valuta, $lobenr) {
	
	global $bgcolor2;
	global $font;
	global $top_bund;
	
	$beskrivelse=(stripslashes($beskrivelse));
	$beskrivelse=(str_replace("&","!og!",$beskrivelse));
	$beskrivelse=(str_replace("'","!apostrof!",$beskrivelse));
	$d_type=trim($d_type);
	$debet=trim($debet);
	$k_type=trim($k_type);
	$kredit=trim($kredit);
	$faktura=trim(stripslashes($faktura));
	$belob=trim($belob);

	print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund>$font<small><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Luk</a></small></td>";
	print"<td width=\"80%\" $top_bund>$font<small>Debitorliste</small></td>";
#	print"<td width=\"10%\" $top_bund align=\"right\">$font<small><a href=../debitor/debitorkort.php?returside=../includes/luk.php target=blank accesskey=N>Ny</a></small></td>";
	print "<td width=\"10%\" $top_bund align=\"right\" onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"JavaScript:window.open('../debitor/debitorkort.php?returside=../includes/luk.php', '', 'statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,resizable=yes');\"><a href='kassekladde.php?sort=$sort&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>$font<small><u>Ny</u></small></a></td>";

	print"</tbody></table>";
	print"</td></tr>\n";
	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=kontonr&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Kundenr</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=firmanavn&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Navn</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=addr1&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Adresse</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=addr2&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Adresse2</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=postnr&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Postnr</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=bynavn&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>By</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=kontakt&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Kontaktperson</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=tlf&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Telefon</a></b></small></td>";
	print" </tr>\n";

	 $sort = $_GET['sort'];
	 if (!$sort) {$sort = firmanavn;}


	$query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, kontakt, tlf from adresser where art = 'D' order by $sort");
	while ($row = db_fetch_array($query)) {
		if (!$beskrivelse){
			$beskr=html_prep(stripslashes($row['firmanavn']));
		}
		else {$beskr=$beskrivelse;} 
		$kontonr=trim($row['kontonr']);
		print "<tr>";
		if ((strstr($fokus,"debe"))||(strstr($fokus,"d_ty"))){$tmp="<a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$kontonr&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>";}
		else {$tmp="<a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kontonr&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>";} 
		print "<td><small>$font $tmp $row[kontonr]</a><br></small></td>";
		print "<td><small>$font $tmp ". stripslashes($row[firmanavn]) ."</a><br></small></td>";
		print "<td><small>$font $tmp $row[addr1]</a><br></small></td>";
		print "<td><small>$font $tmp $row[addr2]</a><br></small></td>";
		print "<td><small>$font $tmp $row[postnr]</a><br></small></td>";
		print "<td><small>$font $tmp $row[bynavn]</a><br></small></td>";
		print "<td><small>$font $tmp $row[kontakt]</a><br></small></td>";
		print "<td><small>$font $tmp $row[tlf]</a><br></small></td>";
		print "</tr>\n";
	}

	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function kreditoropslag($sort, $fokus, $opslag_id, $id, $kladde_id, $bilag, $dato, $beskrivelse, $d_type, $debet, $k_type, $kredit, $faktura, $belob, $momsfri, $afd, $projekt, $ansat, $valuta, $lobenr) {
	global $bgcolor2;
	global $font;
	global $top_bund;
	
	$beskrivelse=(html_prep($beskrivelse));
	$d_type=trim($d_type);
	$debet=trim($debet);
	$k_type=trim($k_type);
	$kredit=trim($kredit);
	$faktura=(html_prep($faktura));
	$belob=trim($belob);

	print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund>$font<small><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri' accesskey='L'>Luk</a></small></td>";
	print"<td width=\"80%\" $top_bund>$font<small>kreditorliste</small></td>";
	print "<td width=\"10%\" $top_bund align=\"right\" onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"JavaScript:window.open('../kreditor/kreditorkort.php?returside=../includes/luk.php', '', 'statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,resizable=yes');\"><a href='kassekladde.php?sort=$sort&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>$font<small><u>Ny</u></small></a></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=kontonr&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Kundenr</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=firmanavn&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Navn</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=addr1&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Adresse</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=addr2&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Adresse2</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=postnr&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Postnr</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=bynavn&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>By</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=kontakt&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Kontaktperson</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=tlf&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Telefon</a></b></small></td>";
	print" </tr>\n";


	 $sort = $_GET['sort'];
	 if (!$sort) {$sort = firmanavn;}


	$query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, kontakt, tlf from adresser where art = 'K' order by $sort");
	while ($row = db_fetch_array($query)){
		if (!$beskrivelse){
			$beskr=html_prep(stripslashes($row['firmanavn']));
		}
		else {$beskr=$beskrivelse;} 
		$kontonr=trim($row['kontonr']);
		print "<tr>";
		if ((strstr($fokus,"debe"))||(strstr($fokus,"d_ty"))){$tmp = "<a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$kontonr&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>";}
		else {$tmp="<a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kontonr&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>";} 
		print "<td><small>$font $tmp $row[kontonr]</a><br></small></td>";
		print "<td><small>$font $tmp ". stripslashes($row[firmanavn]) ."</a><br></small></td>";
		print "<td><small>$font $tmp $row[addr1]</a><br></small></td>";
		print "<td><small>$font $tmp $row[addr2]</a><br></small></td>";
		print "<td><small>$font $tmp $row[postnr]</a><br></small></td>";
		print "<td><small>$font $tmp $row[bynavn]</a><br></small></td>";
		print "<td><small>$font $tmp $row[kontakt]</a><br></small></td>";
		print "<td><small>$font $tmp $row[tlf]</a><br></small></td>";
		print "</tr>\n";
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function openpost($sort, $fokus, $opslag_id, $id, $kladde_id, $bilag, $dato, $beskrivelse, $d_type, $debet, $k_type, $kredit, $faktura, $belob, $momsfri, $afd, $projekt, $ansat, $valuta, $lobenr) {
	global $bgcolor;
	global $bgcolor2;
	global $bgcolor5;
	global $font;
	global $top_bund;	

	$linjebg=NULL;
	
	$d_type=trim($d_type);
	$debet=trim($debet);
	$k_type=trim($k_type);
	$kredit=trim($kredit);
	if ($faktura) $faktura=html_prep($faktura);
	$belob=str_replace("-","",trim($belob));
	if ($beskrivelse) $beskrivelse=html_prep($beskrivelse);

	if (!isset($x))$x=NULL;
	if (!isset($lobenr))$lobenr=NULL;


#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund>$font<small><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Luk</a></small></td>";
	print"<td width=\"80%\" $top_bund>$font<small>&Aring;benposter</small></td>";
	print"<td width=\"10%\" $top_bund align=\"right\">$font<small></small></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=konto_nr&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Kundenr</a></b></small></td>";
#	print"<td><small><b>$font <a 'href=kassekladde.php?sort=konto_nr&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Kundenr</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=firmanavn&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Navn</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=faktnr&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Fakturanr</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=transdate&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Dato</a></b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=amount&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>Bel&oslash;b</a></b></small></td>";
	print" </tr>\n";


	$sort = $_GET['sort'];
	if (!$sort) {$sort = 'konto_nr';}

	$x=0;
	$query = db_select("select id, firmanavn, art, gruppe from adresser order by firmanavn");
	while ($row = db_fetch_array($query)) {
		$x++;
		$konto_id[$x]=$row['id'];
		$firmanavn[$x]=stripslashes($row['firmanavn']);
		$art[$x]=$row['art'];
		$gruppe=$row['gruppe'];
		$gruppeart=$art[$x]."G";
		$r2 = db_fetch_array(db_select("SELECT box5 FROM grupper WHERE art ='$gruppeart' AND kodenr	= '$gruppe'"));
		$modkonto[$x]=$r2['box5'];
	}
	
		$kriterie='where udlignet !=1';
		if ($faktura) {$kriterie=$kriterie." and faktnr='".$faktura."'";}	
		if ((($d_type=='K')|| ($d_type=='D')) and ($debet)) {$kriterie=$kriterie." and konto_nr=".$debet;}	
		elseif ((($k_type=='K')|| ($k_type=='D')) and ($kredit)) {$kriterie=$kriterie." and konto_nr=".$kredit;}	
		if ($belob) {
			$amount=usdecimal($belob);
			if ($amount != 0) {$kriterie=$kriterie." and (amount=".$amount." or amount=-".$amount.")";}
		}
		
		if ($sort=="firmanavn") {$sort='konto_nr';} 
	
		
		$query = db_select("select id, konto_id, konto_nr, faktnr, transdate, amount from openpost $kriterie order by $sort");
		while ($row = db_fetch_array($query)){
			for ($y=1;$y<=$x;$y++) {
			if ($row['konto_id']==$konto_id[$y]) {
				$firmanavn[0]=$firmanavn[$y];
				$art[0]=$art[$y];
			}
		}
		if (!$beskrivelse) {
#			$beskr=html_prep($firmanavn[0]);
			$beskr="Udligning, nota $faktura";
		}
		else	{$beskr=$beskrivelse;}
		$konto_nr=trim($row['konto_nr']);
		$dato=dkdato($row['transdate']);
			
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor;}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5;}

		print "<tr bgcolor=\"$linjebg\">";
			
		
		if ($row['amount']<0) {
			$belob=dkdecimal($row['amount']*-1);
			if (!$kredit) {$kredit=$modkonto[$x];}
			$tmp="<a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$art[0]&debet=$konto_nr&k_type=$k_type&kredit=$kredit&faktura=$row[faktnr]&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>";
			$belob=dkdecimal($row['amount']);
		}
		else 	{
			$belob=dkdecimal($row['amount']);
			if (!$debet) {$debet=$modkonto[$x];}
			$tmp="<a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$debet&k_type=$art[0]&kredit=$konto_nr&faktura=$row[faktnr]&belob=$belob&momsfri=$momsfri&afd=$afd&lobenr=$lobenr'>";
		}
			print "<td><small>$font $tmp $row[konto_nr]</a><br></small></td>";
#		print "<td><small>$font $row[id]<br></small></td>";
		#print "<td><small>$font $row[konto_nr]<br></small></td>";
		print "<td><small>$font $tmp $firmanavn[0]</a><br></small></td>";
		print "<td><small>$font $tmp $row[faktnr]</a><br></small></td>";
		print "<td><small>$font $tmp $dato</a><br></small></td>";
		print "<td><small>$font $tmp $belob</a><br></small></td>";
		print "</tr>\n";
	}
	
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function finansopslag($fokus, $x) {
	
	global $id;
	global $kladde_id;
	global $bilag;
	global $dato;
	global $beskrivelse;
	global $d_type;
	global $debet;
	global $k_type;
	global $kredit;
	global $faktura;
	global $belob;
	global $momsfri;
	global $afd;
	global $ansat;
	global $projekt;
	global $valuta;
	global $regnaar;
	global $bgcolor;
	global $bgcolor2;
	global $bgcolor5;
	global $fgcolor;
	global $font;
	global $top_bund;
	global $lobenr;	
	
	$linjebg=NULL;
	$spantekst=NULL;

	if (!isset($lobenr[$x]))$lobenr[$x]=NULL;
	$beskrivelse[$x]=html_prep($beskrivelse[$x]);
	$d_type[$x]=trim($d_type[$x]);
	$debet[$x]=trim($debet[$x]);
	$k_type[$x]=trim($k_type[$x]);
	$kredit[$x]=trim($kredit[$x]);
	$faktura[$x]=html_prep($faktura[$x]);
	$belob[$x]=trim($belob[$x]);

	print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund>$font<small><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]dato&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]'>Luk</a></small></td>";
	print"<td width=\"80%\" $top_bund>$font<small>Finansopslag</small></td>";
	print"<td width=\"10%\" $top_bund align=\"right\">$font<small></small></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
	?>
	<tr><td valign="top">
	<table cellpadding="0" cellspacing="0" border="0" width="100%" valign = "top">
	<tbody>
	<tr>
		<td width=10%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Kontonr</b></td>
		<td width=35%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Beskrivelse</a></b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Moms</a></b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Genvej</a></b></td>
		</tr>
		<tr><td colspan=4><hr></td></tr>
		<?php
		$i=0;
		$query = db_select("select * from grupper");
		while ($row = db_fetch_array($query)) {
			if (substr(trim($row['art']),1,1)=='M') {
				$i++;
				$moms[$x]=$row['kode'].$row['kodenr'];
			$momstekst[$x]=$row['beskrivelse'];
			}
			$momsantal=$i;
	}
	$query = db_select("select kontotype, kontonr, beskrivelse, moms, genvej, lukket from kontoplan where (kontotype ='D' or kontotype ='S'or kontotype ='H') and regnskabsaar='$regnaar' order by kontonr");
	while ($row = db_fetch_array($query)) {
		if ($row['lukket']!='on') {
			if ($row['moms']){
				for ($i=1; $i<=$momsantal; $i++){
					if (!isset($momstekst[$i])) $momstekst[$i]=NULL; if (!isset($moms[$i])) $moms[$i]=NULL;
					
					if ($moms[$i]==$row['moms']) $spantekst=$momstekst[$i];
				}
			}
			$kontonr=trim($row['kontonr']);
			if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
			print "<tr bgcolor=$linjebg>";
			$faktura[$x]=trim($faktura[$x]);
			$beskrivelse[$x]=trim($beskrivelse[$x]);

			 	
			if ((strstr($fokus,"debe"))||(strstr($fokus,"d_ty")))	{
				$href="<a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$kontonr&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]' accesskey=$row[genvej]>";
			}
			if ((strstr($fokus,"kred"))||(strstr($fokus,"k_ty"))) {
			$href="<a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kontonr&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]' accesskey=$row[genvej]>";
			}
			if ($row['kontotype']=='H') print "<td colspan=4>$font<b>$row[beskrivelse]</font></b></td>";
			else {
				print "<td><small><span title='Klik for at overf&oslash;re kontonr til kassekladde'>$href $font $kontonr</font></a><span></small></td>";
				print "<td><small><span title='Klik for at overf&oslash;re kontonr til kassekladde'>$href $font $row[beskrivelse]</font></a><span></small></td>";
				print "<td><span title='$spantekst'><small> $font $row[moms]</font></small></span></td>";
				print "<td><small><span title='Klik for at overf&oslash;re kontonr til kassekladde'>$href $font $row[genvej]</font></a><span></small></td>";
			}
			print "</tr>\n";
		}
	}
	exit;
}
######################################################################################################################################
function afd_opslag($fokus, $x) 
{

	global $id;
	global $kladde_id;
	global $bilag;
	global $dato;
	global $beskrivelse;
	global $d_type;
	global $debet;
	global $k_type;
	global $kredit;
	global $faktura;
	global $belob;
	global $afd;
	global $ansat;
	global $projekt;
	global $valuta;
	global $momsfri;
	global $regnaar;
	global $bgcolor;
	global $bgcolor2;
	global $bgcolor5;
	global $fgcolor;
	global $font;
	global $top_bund;

	$beskrivelse[$x]=html_prep($beskrivelse[$x]);
	$d_type[$x]=trim($d_type[$x]);
	$debet[$x]=trim($debet[$x]);
	$k_type[$x]=trim($k_type[$x]);
	$kredit[$x]=trim($kredit[$x]);
	$faktura[$x]=html_prep($faktura[$x]);
	$belob[$x]=trim($belob[$x]);

	 print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund>$font<small><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$art[$x]&debet=$debet[$x]&k_type=$k_type&debet=$debet[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]'>Luk</a></small></td>";
	print"<td width=\"80%\" $top_bund>$font<small>Afd. opslag</small></td>";
	print"<td width=\"10%\" $top_bund align=\"right\">$font<small></small></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
?>
		<tr><td valign="top">
	<table cellpadding="0" cellspacing="0" border="0" width="100%" valign = "top">
	<tbody>
	<tr>
		<td width=20%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Afdeling nr.</b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Beskrivelse</a></b></td>
	</tr>
	<?php
	$query = db_select("select kodenr, beskrivelse from grupper where art='AFD' order by kodenr");
	while ($row = db_fetch_array($query)) {
		$kodenr=trim($row['kodenr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=$linjebg>";
		print "<td><small><a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$kodenr&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]'> $font $kodenr</a><br></small></td>";
		print "<td><small> $font $row[beskrivelse]<br></small></td>";
		print "</tr>\n";

	}
	exit;
}
######################################################################################################################################
function projekt_opslag($fokus, $x) 
{

	global $id;
	global $kladde_id;
	global $bilag;
	global $dato;
	global $beskrivelse;
	global $d_type;
	global $debet;
	global $k_type;
	global $kredit;
	global $faktura;
	global $belob;
	global $afd;
	global $ansat;
	global $projekt;
	global $valuta;
	global $momsfri;
	global $regnaar;
	global $bgcolor;
	global $bgcolor2;
	global $bgcolor5;
	global $fgcolor;
	global $font;
	global $top_bund;

	$beskrivelse[$x]=html_prep($beskrivelse[$x]);
	$d_type[$x]=trim($d_type[$x]);
	$debet[$x]=trim($debet[$x]);
	$k_type[$x]=trim($k_type[$x]);
	$kredit[$x]=trim($kredit[$x]);
	$faktura[$x]=html_prep($faktura[$x]);
	$belob[$x]=trim($belob[$x]);

	 print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund>$font<small><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$art[$x]&debet=$debet[$x]&k_type=$k_type&debet=$debet[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]'>Luk</a></small></td>";
	print"<td width=\"80%\" $top_bund>$font<small>Projekt opslag</small></td>";
	print"<td width=\"10%\" $top_bund align=\"right\">$font<small></small></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
?>
		<tr><td valign="top">
	<table cellpadding="0" cellspacing="0" border="0" width="100%" valign = "top">
	<tbody>
	<tr>
		<td width=20%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Projekt nr.</b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Beskrivelse</a></b></td>
	</tr>
	<?php
	$query = db_select("select kodenr, beskrivelse from grupper where art='PRJ' order by kodenr");
	while ($row = db_fetch_array($query)) {
		$kodenr=trim($row['kodenr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=$linjebg>";
		print "<td><small><a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$kodenr&valuta=$valuta[$x]&lobenr=$lobenr[$x]'> $font $kodenr</a><br></small></td>";
		print "<td><small> $font $row[beskrivelse]<br></small></td>";
		print "</tr>\n";
	}
	exit;
}
######################################################################################################################################
function ansat_opslag($fokus, $x)
{

	global $id;
	global $kladde_id;
	global $bilag;
	global $dato;
	global $beskrivelse;
	global $d_type;
	global $debet;
	global $k_type;
	global $kredit;
	global $faktura;
	global $belob;
	global $afd;
	global $ansat;
	global $projekt;
	global $valuta;
	global $momsfri;
	global $regnaar;
	global $bgcolor;
	global $bgcolor2;
	global $bgcolor5;
	global $fgcolor;
	global $font;
	global $top_bund;

	$beskrivelse[$x]=html_prep($beskrivelse[$x]);
	$d_type[$x]=trim($d_type[$x]);
	$debet[$x]=trim($debet[$x]);
	$k_type[$x]=trim($k_type[$x]);
	$kredit[$x]=trim($kredit[$x]);
	$faktura[$x]=html_prep($faktura[$x]);
	$belob[$x]=trim($belob[$x]);

	$r=db_fetch_array(db_select("select id from adresser where art = 'S'"));
	$egen_kto_id = $r['id']*1;


	 print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund>$font<small><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$art[$x]&debet=$debet[$x]&k_type=$k_type&debet=$debet[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]'>Luk</a></small></td>";
	print"<td width=\"80%\" $top_bund>$font<small>Projekt opslag</small></td>";
	print"<td width=\"10%\" $top_bund align=\"right\">$font<small></small></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
?>
		<tr><td valign="top">
	<table cellpadding="0" cellspacing="0" border="0" width="100%" valign = "top">
	<tbody>
	<tr><td><br></td></tr><tr>
		<td width=20%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Initialer</b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Navn</a></b></td>
	</tr><tr><td><br></td></tr>
	<?php
	$query = db_select("select id, navn, initialer from ansatte where konto_id='$egen_kto_id' and lukket!='on' order by posnr");
	while ($row = db_fetch_array($query)) {
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=$linjebg>";
		print "<td><small><a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&ansat=$row[initialer]&projekt=$projekt[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]'> $font $row[initialer]</a><br></small></td>";
		print "<td><small><a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&ansat=$row[initialer]&projekt=$projekt[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]'> $font $row[navn]</a><br></small></td>";
		print "</tr>\n";
	}
	exit;
}
######################################################################################################################################
function valuta_opslag($fokus, $x) 
{
	global $id;
	global $kladde_id;
	global $bilag;
	global $dato;
	global $beskrivelse;
	global $d_type;
	global $debet;
	global $k_type;
	global $kredit;
	global $faktura;
	global $belob;
	global $momsfri;
	global $afd;
	global $ansat;
	global $projekt;
	global $valuta;
	global $regnaar;
	global $bgcolor;
	global $bgcolor2;
	global $bgcolor5;
	global $fgcolor;
	global $font;
	global $top_bund;

	$beskrivelse[$x]=html_prep($beskrivelse[$x]);
	$d_type[$x]=trim($d_type[$x]);
	$debet[$x]=trim($debet[$x]);
	$k_type[$x]=trim($k_type[$x]);
	$kredit[$x]=trim($kredit[$x]);
	$faktura[$x]=html_prep($faktura[$x]);
	$belob[$x]=trim($belob[$x]);

	 print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print"<td width=\"10%\" $top_bund>$font<small><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$art[$x]&debet=$debet[$x]&k_type=$k_type&debet=$debet[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$valuta[$x]&lobenr=$lobenr[$x]'>Luk</a></small></td>";
	print"<td width=\"80%\" $top_bund>$font<small>Valuta opslag</small></td>";
	print"<td width=\"10%\" $top_bund align=\"right\">$font<small></small></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
?>
		<tr><td valign="top">
	<table cellpadding="0" cellspacing="0" border="0" width="100%" valign = "top">
	<tbody>
	<tr>
		<td width=20%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Valuta.</b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Beskrivelse</a></b></td>
	</tr>
	<?php
	$query = db_select("select kodenr, box1, beskrivelse from grupper where art='VK' order by box1");
	while ($row = db_fetch_array($query)) {
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		$tmp="<a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]&projekt=$projekt[$x]&ansat=$ansat[$x]&valuta=$row[box1]'>";
		print "<tr bgcolor=$linjebg>";
		print "<td><small>$tmp $font $row[box1]</a><br></small></td>";
		print "<td><small>$tmp $font $row[beskrivelse]</a><br></small></td>";
		print "</tr>\n";

	}
	exit;
}
######################################################################################################################################
function kontroller($id, $bilag, $dato, $beskrivelse, $d_type, $debet, $k_type, $kredit, $faktura, $belob, $momsfri, $kontonr, $kladde_id, $afd, $projekt, $ansat, $valuta, $lobenr ) {
	global $fokus;
	global $debitornr;
	global $connection;
	global $regnaar;
	global $aarstart;
	global $aarslut;
	global $prebilag;
	global $fejl;
	
	$lukket=NULL;
	if ($kladde_id) {	
		$row =db_fetch_array(db_select("select bogfort from kladdeliste where id = $kladde_id"));
		if ($row['bogfort']!='-') {
			print "<BODY onLoad=\"javascript:alert('Kladden er allerede bogf&oslash;rt - kladden lukkes')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
			exit;
		}
	}

	if (!$aarstart) {
		$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'");
		if ($row = db_fetch_array($query)) {
			$year=trim($row['box2']);
			$aarstart=trim($year).trim($row['box1']);
			$year=trim($row['box4']);
			$aarslut=trim($year).trim($row['box3']);
	 	}
	}
#	(!$bilag) {$bilag=$prebilag;} PHR 02.10.06
#	if ($bilag=="-"){$bilag="";} PHR 02.10.06
	if (($bilag)&&($bilag!='0')&&($bilag!='-')) $bilag=$bilag*1;
	$debet=trim($debet);
	$kredit=trim($kredit);
	if (($bilag != "-")&&(($bilag)||($beskrivelse)||($kredit)||($debet)||($faktura)||($belob))) {
		if ((!$bilag)&&($bilag!='0')) {$bilag=$prebilag;}
		if (!$bilag) $bilag='0';
		if ((strstr($d_type,"d"))||(strstr($d_type,"D"))){$d_type="D";}
		elseif ((strstr($d_type,"k"))||(strstr($d_type,"K"))){$d_type="K";}
		else {$d_type="F";}

		if ((strstr($k_type,"d"))||(strstr($k_type,"D"))){$k_type="D";}
		elseif ((strstr($k_type,"k"))||(strstr($k_type,"K"))){$k_type="K";}
		else {$k_type="F";}
		if (!$debet) {$debet=0;}
		if (!$kredit) {$kredit=0;}
		if (!$lukket) {
			$lukket=array();
			$y=0;
			$query = db_select("select kontonr,lukket from kontoplan where kontotype != 'H' and kontotype != 'Z' and regnskabsaar=$regnaar");
			while($row = db_fetch_array($query)) {
				$y++;
				$kontonr[$y]=trim($row['kontonr']);
				if ($row['lukket']) {
					$lukket[$y]=$kontonr[$y];
				}
			}
		}

		if (($d_type=="D")||($k_type=="D")||($d_type=="K")||($k_type=="K")) {
			$x=0;
			$y=0;
			$query = db_select("select kontonr, art from adresser");
			while ($row = db_fetch_array($query)) {
				if (strstr($row['art'],"D")) {
					$x++;
					$debitornr[$x]=trim($row['kontonr']);
				}
				if (strstr($row['art'],"K")){
					$y++;
					$kreditornr[$y]=trim($row['kontonr']);
				}
			}

		}
		if (($d_type=="F")&&(strlen($debet)==1)&&($debet!='0')) {
			$debet=strtoupper($debet);
			$query = db_select("select kontonr from kontoplan where genvej='$debet'");
			if ($row = db_fetch_array($query)) $debet=$row[kontonr];
			else {
				$alerttekst=$debet.' er ikke defineret som genvejstast (Bilag nr '.$bilag.')\nKladden en IKKE gemt';
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			}
		}
		if (($d_type=="F")&&(strlen($debet)>1)&&(!is_numeric($debet))) {
			$tmp=$debet."%";
			$query = db_select("select kontonr from kontoplan where beskrivelse ilike '$tmp' and regnskabsaar='$regnaar' and lukket != 'on' order by beskrivelse");
			if ($row = db_fetch_array($query)) $debet=$row['kontonr'];
			else {
				$tmp="%".$debet."%";
				$query = db_select("select kontonr from kontoplan where beskrivelse ilike '$tmp' and regnskabsaar='$regnaar' and lukket != 'on' order by beskrivelse");
				if ($row = db_fetch_array($query)) $debet=$row['kontonr'];
				else {
					$alerttekst='Der er ingen konti som indeholder teksten '.$debet.' (Bilag nr '.$bilag.')\nKladden en IKKE gemt';
					print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
					$fejl=1;
				}
			}
		}
		if (($k_type=="F")&&(strlen($kredit)>1)&&(!is_numeric($kredit))) {
			$tmp=$kredit."%";
			$query = db_select("select kontonr from kontoplan where beskrivelse ilike '$tmp' and regnskabsaar='$regnaar' order by beskrivelse");
			if ($row = db_fetch_array($query)) $kredit=$row['kontonr'];
			else {
				$tmp="%".$kredit."%";
				$query = db_select("select kontonr from kontoplan where beskrivelse ilike '$tmp' and regnskabsaar='$regnaar' order by beskrivelse");
				if ($row = db_fetch_array($query)) $kredit=$row['kontonr'];
				else {
					$alerttekst='Der er ingen konti som indeholder teksten '.$kredit.' (Bilag nr '.$bilag.')\nKladden en IKKE gemt';
					print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
					$fejl=1;
				}
			}
		}
		if ((!$fejl)&&($k_type=="F")&&(strlen($kredit)==1)&&($kredit!='0')) {
			$kredit=strtoupper($kredit);
			$query = db_select("select kontonr from kontoplan where genvej='$kredit'");
			if ($row = db_fetch_array($query)) $kredit=$row['kontonr'];
			else {
				$alerttekst=$kredit.' er ikke defineret som genvejstast (Bilag nr '.$bilag.')\nKladden en IKKE gemt';
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			}
		}
		if ((!$fejl)&&($d_type=="F")&&($debet>0)) {
			$alerttekst='';

			if (!in_array($debet,$kontonr)) $alerttekst='Debetkonto '.$debet.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			elseif (in_array($debet,$lukket)) $alerttekst='Debetkonto '.$debet.' er l&aring;st og m&aring; ikke anvendes (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			if ($alerttekst) {
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			}
		}
		if ((!$fejl)&&($k_type=="F")&&($kredit>0)) {
			$alerttekst='';
			if (!in_array($kredit,$kontonr)) $alerttekst='Kreditkonto '.$kredit.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			elseif (in_array($kredit,$lukket)) $alerttekst='Kreditkonto '.$kredit.' er l&aring;st og m&aring; ikke anvendes (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			if ($alerttekst) print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
			if ($alerttekst) {
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			}
		}
#echo "1 Fejl $fejl && $kladde_id && $debet && $debitornr[$x]<br>";
#if ($debet=='park') echo "if ((!$fejl)&&($d_type==D)&&($debet)&&(!in_array($debet,$debitornr)))";
		if ((!$fejl)&&($d_type=="D")&&($debet)&&(!in_array($debet,$debitornr))) {
			$alerttekst='Debitor '.$debet.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
			$fejl=1;
		}
		if ((!$fejl)&&($k_type=="D")&&($kredit)&&(!in_array($kredit,$debitornr))) {
			$alerttekst='Debitor '.$kredit.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
			$fejl=1;
		}
		if ((!$fejl)&&($d_type=="K")&&($debet)&&(!in_array($debet,$kreditornr))) {
			$alerttekst='Kreditor '.$debet.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
			$fejl=1;
		}
		if ((!$fejl)&&($k_type=="K")&&($kredit)&&(!in_array($kredit,$kreditornr))) {
			$alerttekst='Kreditor '.$kredit.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
			$fejl=1;
		}
		if (($d_type=="K")&&(strtoupper($debet)=="K")) $debet=0;
		if (($d_type=="D")&&(strtoupper($debet)=="D")) $debet=0;
		if (($k_type=="K")&&(strtoupper($kredit)=="K")) $kredit=0;
		if (($k_type=="D")&&(strtoupper($kredit)=="D")) $kredit=0;
		$transdate=usdate($dato);
		list ($year, $month, $day) = split ('-', $transdate);
#		$year=substr($year,-2);
		$ym=$year.$month;
		if ((!$fejl)&&($ym<$aarstart)||($ym>$aarslut)) {
			$alerttekst='Dato udenfor regnskabs&aring;r (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
			$fejl=1;
#			$transdate=date("Y-m-d");
		}
		$afd=$afd*1;
		if (!$fejl&&$afd!='0') {
			if (!$row= db_fetch_array(db_select("select id from grupper where art='AFD' and kodenr='$afd'"))){
				$alerttekst='Afdeling '.$afd.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			} # else echo "ID=$row[id]";
		}
		$projekt=$projekt*1;
		if (!$fejl&&$projekt!='0') {
			if (!$row= db_fetch_array(db_select("select id from grupper where art='PRJ' and kodenr='$projekt'"))){
				$alerttekst='Projekt '.$projekt.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			} # else echo "ID=$row[id]";
		}
		if (!$valuta) $valuta='DKK';
		if (!$fejl&&$valuta!='DKK') {
			if (!$row= db_fetch_array(db_select("select kodenr from grupper where art='VK' and box1='$valuta'"))){
				$alerttekst='valuta '.$valuta.' eksisterer ikke (Bilag nr '.$bilag.')\nKladden er IKKE gemt!';
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
				$fejl=1;
			} else $valutakode=$row['kodenr'];
			$valdate=usdate($dato);
			if (!$fejl && $row= db_fetch_array(db_select("select kurs from valuta where gruppe='$valutakode' and valdate <= '$valdate' order by valdate"))) {
				db_modify("update kassekladde set valutakurs = '$row[kurs]' where id = '$id'");
			}
		}
		if ($lobenr) {
		db_modify("update tmpkassekl set bilag = '$bilag', transdate = '$dato', beskrivelse = '$beskrivelse', d_type = '$d_type', debet = '$debet', k_type = '$k_type', kredit = '$kredit', faktura = '$faktura', amount = '$belob', momsfri = '$momsfri', afd= '$afd', projekt= '$projekt', valuta= '$valuta' where lobenr = '$lobenr' and kladde_id='$kladde_id'");
		}
	}
	elseif (($id)&&($bilag=="-")) {
		db_modify("delete from kassekladde where id = $id");
	}
	$prebilag=$bilag;
}
######################################################################################################################################
function opdater($kladde_id)
{
	global $egen_kto_id;

	$valutakode='0';
	$q = db_select("select * from tmpkassekl where kladde_id = $kladde_id order by lobenr");
	while ($r=db_fetch_array($q)) {
		if (($r['bilag']!="-")&&($r['transdate'])) { 
			if ($r['transdate']) $transdate=usdate($r['transdate']);
			$amount=usdecimal($r['amount']);
			$momsfri=trim($r['momsfri']);
			$debet=$r['debet']*1;
			$kredit=$r['kredit']*1;
			$d_type=trim($r['d_type']);
			$k_type=trim($r['k_type']);
			$afd=$r['afd']*1;
			$ansat=strtolower($r['ansat']);
			$faktura=addslashes($r['faktura']);
			if ($egen_kto_id && $ansat) {
			$r2=db_fetch_array(db_select("select id from ansatte where lower(initialer) = '$ansat' and konto_id = '$egen_kto_id'"));
				$ansat_id=$r2['id']*1;
			} else $ansat_id=0;
			$projekt=$r['projekt']*1;
			$valuta=$r['valuta'];
			if ($valuta!='DKK') {
				$valuta=strtoupper($valuta);
				$r2=db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta'"));
				$valutakode=$r2['kodenr']*1;
			} else $valutakode=0; #Valutakode 0 er altid DKK
	
			$beskrivelse=addslashes($r['beskrivelse']);
			if ($amount < 0) {# Hvis beloebet er negativt, byttes om paa debet og kredit.
				$tmp=$kredit; $kredit=$debet;	$debet=$tmp;
				$tmp=$k_type; $k_type=$d_type; $d_type=$tmp;
				$amount=$amount*-1;
			}
			if ($r['id']) {
				db_modify("update kassekladde set bilag = '$r[bilag]', transdate = '$transdate', beskrivelse = '$beskrivelse', d_type = '$d_type', debet = '$debet', k_type = '$k_type', kredit = '$kredit', faktura = '$faktura', amount = '$amount', momsfri = '$momsfri', afd= '$afd', projekt= '$projekt', ansat= '$ansat_id', valuta= '$valutakode' where id = '$r[id]'");
			} elseif (($r['transdate'])&&(($r['beskrivelse'])||($debet)||($kredit)||($r['faktura']))) {
				$beskrivelse=addslashes($r['beskrivelse']);
				db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, faktura, amount, momsfri, afd, projekt, ansat, valuta, kladde_id) values ('$r[bilag]', '$transdate', '$beskrivelse', '$d_type', '$debet', '$k_type', '$kredit', '$r[faktura]', '$amount', '$momsfri', '$afd', '$projekt', '$ansat_id', '$valutakode', '$kladde_id')");
			}
		}
	}
}
######################################################################################################################################
function html_prep($temp)
{
	$temp=(trim($temp));
	$temp=(str_replace("&","!og!",$temp));
	$temp=(str_replace("'","!apostrof!",$temp));
	
	return $temp;
}
######################################################################################################################################
function tilbagefor($kladde_id)
{
	global $regnaar;
	global $connection;

	$query = db_select("select kladdenote from kladdeliste where id = '$kladde_id' and bogfort='!'");
	if ($row = db_fetch_array($query))
	{
		db_modify("delete from openpost where kladde_id = '$kladde_id'");
		db_modify("delete from transaktioner where kladde_id = '$kladde_id'");
		db_modify("update kladdeliste set bogfort = '-' where id = '$kladde_id'");
	}
}
######################################################################################################################################
function kopier_til_ny($kladde_id, $bilagsnr, $ny_dato)
{
	global $regnaar;
	global $connection;
	global $brugernavn;

	list ($regnstart, $regnslut) = split(":",regnskabsaar($regnaar));

	if ($bilagsnr) {
		$gl_bilag=0;
		$bilag=0;
		$query = db_select("select kladdenote from kladdeliste where id = '$kladde_id' and bogfort='V'");
		if ($row = db_fetch_array($query)){
			transaktion('begin');
			$kladdenote=$row['kladdenote'];
			$tidspkt=microtime();
			$kladdedate=date("Y-m-d");
			$ny_kladde_id=1;
			$query = db_select("select id from kladdeliste where id>=$kladde_id");
			while ($row = db_fetch_array($query)) {
				if ($ny_kladde_id<=$row['id']){$ny_kladde_id=$row['id']+1;}
			}
			db_modify("insert into kladdeliste (id, kladdenote, kladdedate, bogfort, oprettet_af) values ('$ny_kladde_id', '$kladdenote', '$kladdedate', '-', '$brugernavn')");
			$query = db_select("select * from kassekladde where kladde_id=$kladde_id order by bilag");
			while ($row = db_fetch_array($query)) {
#				if (!isset($b_diff) && $row['bilag']) $b_diff=$bilagsnr-$row['bilag'];
				$beskrivelse=addslashes($row['beskrivelse']);
				$faktura=addslashes($row['faktura']);				if ($bilagsnr != "=" && $row['bilag'] && $row['bilag']!=$gl_bilag) {
					if (!$bilag) $bilag=$bilagsnr;
					else $bilag++;
					$gl_bilag = $row['bilag'];
					$next_bilag = $bilag;
				}elseif ($bilagsnr=='=') $bilag=$row['bilag']*1; 
				$debet=$row['debet']*1;
				$kredit=$row['kredit']*1;
				$afd=$row['afd']*1;
				$ansat=$row['ansat']*1;
				$projekt=$row['projekt']*1;
				$valuta=$row['valuta']*1;
				if ($ny_dato && $ny_dato!="=") $date=usdate($ny_dato);
				else $date=$row['transdate'];
				db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, faktura, amount, momsfri, afd, ansat, projekt, valuta, kladde_id) values ('$bilag', '$date', '$beskrivelse', '$row[d_type]', '$debet', '$row[k_type]', '$kredit', '$faktura', '$row[amount]', '$row[momsfri]', '$afd', '$ansat', '$projekt', '$valuta', '$ny_kladde_id')");
			}
			transaktion('commit');
		}
		print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$ny_kladde_id\">";
		exit;
	} else {
# echo "($ym<$aarstart)||($ym>$aarslut))<br>";		
		$query = db_select("select MAX(bilag) as bilag from kassekladde where transdate>='$regnstart' and transdate<='$regnslut'");
		$row = db_fetch_array($query);
		$bilagsnr=$row['bilag']+1;
		$dato=date("d-m-y");
		print "<form name=\"Form1\" action=kassekladde.php method=post>";
		print "<span style=center title=\"Bilagsnummer for 1. bilag. De &oslash;vrige beregnes automatisk\">Skriv 1. bilagsnr <input type=\"text\" size=4 name=bilagsnr value=$bilagsnr><br><br><br></span>";
		print "<span style=center title=\"Hvis feltet er tomt, anvendes orginal bilagsdato\">Skriv dato for alle bilag <input type=\"text\" size=8 name=ny_dato value=$dato><br><br><br></span>";
		print "<input type=hidden name=kladde_id value=$kladde_id>";
		print "<input type=submit accesskey=\"k\" value=\"Kopier til ny\" name=\"submit\">&nbsp;<input type=button value=fortryd onClick=\"location.href='../includes/luk.php'\"><br></span>\n";
		exit;
	}
exit;
}
######################################################################################################################################
function nextfokus($fokus) 
{
	global $id;
	global $amount;
	if ($fokus) {
		$f_id=substr($fokus,4,(strlen($fokus)-4));
		if (strstr($fokus,"bila")) {$fokus="dato".$f_id;}
		elseif (strstr($fokus,"dato")) {$fokus="besk".$f_id;}
		elseif (strstr($fokus,"besk")) {$fokus="d_ty".$f_id;}
		elseif (strstr($fokus,"d_ty")) {$fokus="debe".$f_id;}
		elseif (strstr($fokus,"debe")) {$fokus="k_ty".$f_id;}
		elseif (strstr($fokus,"k_ty")) {$fokus="kred".$f_id;}
		elseif (strstr($fokus,"kred")) {$fokus="fakt".$f_id;}
		elseif (strstr($fokus,"fakt")) {$fokus="belo".$f_id;}
		elseif (strstr($fokus,"belo")||strstr($fokus,"afd")) {
			$f_id++;
			$fokus="bila".$f_id;
		}
	} #else $fokus="bila".$x;
# 	if ($amount[$x-1]>0) {$fokus="bila".$x;}

	return $fokus;
}
function regnskabsaar($regnaar)
{
	if ($row = db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'"))){
		$start=trim($row['box2'])."-".trim($row['box1'])."-01";
		$slut=usdate("31-".trim($row['box3'])."-".trim($row['box4']))	; #usdate bruges for at sikre korrekt dato.
	} else {
		$alerttekst='Regnskabs&aring;r ikke oprettet!';
		print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
		exit;
	}	
	return $start.":".$slut;
}

######################################################################################################################################
function indsaet_linjer($kladde_id, $bilag, $dato, $beskrivelse, $d_type, $debet, $k_type, $kredit, $faktura, $belob, $afd, $ansat, $projekt, $valuta)
{ 
	global $fejl;

	$date=usdate($dato);
	$amount=usdecimal($belob);
	$bilag = str_replace('+',':',$bilag); #jeg ved ikke hvorfor, men den vil ikke splitte med "+"
	list ($bilag, $antal) = split (':', $bilag);
	if ($valuta && $valuta!='DKK') {	
		$r = db_fetch_array(db_select("select kodenr from grupper where box1 = '$valuta' and art = 'VK'"));
		if ($r['kodenr']) $valutakode=$r['kodenr'];
		else {
			$fejl=1;
			print "<BODY onLoad=\"javascript:alert('Valuta $valuta eksisterer ikke (Bilag $bilag)')\">";	
		}
	} else $valutakode=0;
	if (!$fejl) {	
		if ($antal=="=") db_modify("insert into kassekladde (bilag, kladde_id, transdate, beskrivelse, d_type, debet, k_type, kredit, faktura, amount, afd, ansat, projekt, valuta) values ('$bilag', '$kladde_id', '$date', '$beskrivelse', '$d_type', '$debet', '$k_type', '$kredit', '$faktura', '$amount', '$afd', '$ansat', '$projekt', '$valutakode')");
		else ($antal=$antal*1);
		for ($x=1; $x<=$antal; $x++) {
			db_modify("insert into kassekladde (bilag, kladde_id, transdate) values ('$bilag', '$kladde_id', '$date')");
			db_modify("insert into tmpkassekl (bilag, kladde_id, transdate) values ('$bilag', '$kladde_id', '$dato')");
		}	
	}
	if (!$fokus)$fokus="ny_kladdenote";
}
?>

</tbody></table>

<script language="javascript">
document.kassekladde.<?php echo $fokus?>.focus();
</script>

</body></html>
