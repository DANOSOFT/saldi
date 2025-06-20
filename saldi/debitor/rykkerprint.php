<?php #topkode_start
@session_start();
$s_id=session_id();

// -------------------------------------------------------debitor/rykkerprint-----patch 1.0.4----------
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
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------

$kontoliste=$_GET['kontoliste'];
$konto_antal=$_GET['kontoantal'];
$maaned_fra=$_GET['maaned_fra'];
$maaned_til=$_GET['maaned_til'];
$regnaar=$_GET['regnaar'];
#$id=$_GET['id'];
$formular=6;
#$lev_nr=$_GET['lev_nr'];
$bg="nix";

$konto_id = explode(";", $_GET['kontoliste']);

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdato.php");
include("../includes/usdate.php");
include("../includes/dkdecimal.php");
include("../includes/formfunk.php");
include("../includes/db_query.php");
include("../includes/forfaldsdag.php");

$query = db_select("select * from formularer where formular = $formular and art = 1 and beskrivelse = 'LOGO'");
if ($row = db_fetch_array($query)) {
	$logo_X=$row[xa]*2.86;
	$logo_Y=$row[ya]*2.86;
}
else {
	$logo_X=430;
	$logo_Y=758;
}
$fsize=filesize("../includes/faktinit.ps");
$fp=fopen("../includes/faktinit.ps","r");
$initext=fread($fp,$fsize);
fclose($fp);
		
if (file_exists("../logolib/logo_$db_id.eps")){$logo="../logolib/logo_$db_id.eps";}
else {$logo="../logolib/logo.eps";}
	
$fsize=filesize($logo);
$fp=fopen($logo,"r");
$logo="";
while (!feof($fp)){
	 $linje=fgets($fp);
	 if (substr($linje,0,2)!="%!")
	 {
		 if (strstr($linje, "translate")&&(!$translate))
		 {
			 $linje="$logo_X $logo_Y translate \n"; 
			 $translate=1;
		 }
		 $logo=$logo.$linje;
	} 
}
fclose($fp);

$printfilnavn=str_replace(" ","_",$brugernavn);
$fp=fopen("../temp/$db.$printfilnavn","w");

for ($q=0; $q<$konto_antal; $q++) {
	$x=0;
	$query = db_select("select * from formularer where formular = $formular and art = 3");
	while ($row = db_fetch_array($query)) {
			if ($row['beskrivelse']=='generelt') {	
				$antal_ordrelinjer=$row[xa];
				$ya=$row[ya];
				$linjeafstand=$row[xb];
				$Opkt=$ya-($antal_ordrelinjer*$linjeafstand);	 
			}
			else {
				$x++;
				$variabel[$x]=$row['beskrivelse'];
				$placering[$x]=$row[placering];
				$xa[$x]=$row[xa];
				$str[$x]=$row[str];
				$laengde[$x]=$row[xb];
				$color[$x]=$row[color];
				$fed[$x]=$row[fed];
				$kursiv[$x]=$row[kursiv];
				$form_font[$x]=$row[font];
		}
		$var_antal=$x;
	}
	$side=1;
	fwrite($fp,$initext);
	formulartekst($konto_id[$q]); 
	$forfalden=0;
	if ($konto_id[$q]){
		$id=$konto_id[$q];
		$x=0;
		$sum=0;
		$momssum=0;
		$tmp=0;
		$y=$ya;
		$q2 = db_select("select * from openpost where konto_id = $konto_id[$q] and udlignet != 1");
		while ($r2 = db_fetch_array($q2)) {
			$q3 = db_select("select betalingsbet, betalingsdage from ordrer where fakturanr='$r2[faktnr]'");
			$r3 = db_fetch_array($q3);
			$forfaldsdag=usdate(forfaldsdag($r2[transdate], $r3[betalingsbet], $r3[betalingsdage]));
			if ($forfaldsdag<date("Y-m-d")) $forfalden=$forfalden+$r2[amount];
			for ($z=1; $z<=$var_antal; $z++) {
 				if ($forfaldsdag<date("Y-m-d"))	{
 					if ($variabel[$z]=="dato") {
 						$z_dato=$z;
 						skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", dkdato($r2[transdate]), "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$placering[$z]", "$form_font[$z]");
					}
					if ($variabel[$z]=="faktnr") {
						$z_faktnr=$z;
						skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$r2[faktnr]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$placering[$z]", "$form_font[$z]");
					}
					if ($variabel[$z]=="beskrivelse") {
						$z_beskrivelse=$z;
						skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$r2[beskrivelse]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$placering[$z]", "$form_font[$z]");
					}
					if (strstr($variabel[$z],"bel")) {
						$z_belob=$z;
						skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", dkdecimal($r2[amount]), "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$placering[$z]", "$form_font[$z]");
					}
				}
			}	
			if ($forfaldsdag<date("Y-m-d")) $y=$y-4;
		}
 		
 		$q2 = db_select("select xb from formularer where beskrivelse='GEBYR'");
		if ($r2 = db_fetch_array($q2)) {
 			$dato=date("d-m-Y");
 			if (isset($z_dato)) skriv($str[$z_dato], "$fed[$z_dato]", "$kursiv[$z_dato]", "$color[$z_dato]", "$dato", "ordrelinjer_".$Opkt, "$xa[$z_dato]", "$y", "$placering[$z_dato]", "$form_font[$z_dato]");
#			if (isset ($z_fatknr)) skriv($str[$z_faktnr], "$fed[$z_faktnr]", "$kursiv[$z_faktnr]", "$color[$z_faktnr]", "$r2[faktnr]", "ordrelinjer_".$Opkt, "$xa[$z_faktnr]", "$y", "$placering[$z_faktnr]", "$form_font[$z_faktnr]");
			if (isset ($z_beskrivelse)) skriv($str[$z_beskrivelse], "$fed[$z_beskrivelse]", "$kursiv[$z_beskrivelse]", "$color[$z_beskrivelse]", "Rykkergebyr", "ordrelinjer_".$Opkt, "$xa[$z_beskrivelse]", "$y", "$placering[$z_beskrivelse]", "$form_font[$z_beskrivelse]");
			if (isset ($z_belob)) skriv($str[$z_belob], "$fed[$z_belob]", "$kursiv[$z_belob]", "$color[$z_belob]", dkdecimal($r2[xb]), "ordrelinjer_".$Opkt, "$xa[$z_belob]", "$y", "$placering[$z_belob]", "$form_font[$z_belob]");
			$forfalden=$forfalden+$r2[xb];
		}
		$ialt=dkdecimal($forfalden);
		find_tekst($id, 'S', $formular);
		bundtekst($konto_id[$q]);
	}
}
 print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/udskriv.php?ps_fil=$db.$printfilnavn\">";

###################################### FAKTURAHOVED ######################################

function formulartekst($id)
{
	global $formular;
	global $momssats;
	global $dkdato;
	global $connection;
	global $fp;
	global $side;

	include("../includes/ordreopslag.php");
	
	if ($art=="DO") {$art="Faktura";}
	else {$art="Kreditnota";}
	
	
	$query = db_select("select * from ordrelinjer where ordre_id = $id and rabat > 0");
	if($row = db_fetch_array($query)) {$rabat="y";}

	$faktdato=dkdato($fakturadate);
	$query = db_select("select * from ordrelinjer where ordre_id = $id and rabat > 0");
	if($row = db_fetch_array($query)) {$rabat="y";}

	$query = db_select("select * from formularer where formular = $formular and art = 1 and beskrivelse != 'LOGO'");
	while ($row = db_fetch_array($query)) {
		$xa=$row[xa]*2.86;
		$ya=$row[ya]*2.86;
		$xb=$row[xb]*2.86;
		$yb=$row[yb]*2.86;
		$lw=$row[str];
		fwrite($fp," $xa $ya moveto $xb $yb lineto $lw setlinewidth stroke \n");
	}

find_tekst($id, 'A', $formular);
	
return $rabat;	
}




function bundtekst($id)
{
global $logo;
global $fp;
global $nextside;
global $side;
global $y;
global $ya;

 $y=$ya;
$side=$side+1;

fwrite($fp, $logo);
# fwrite($fp,"showpage");
}
################################ UDSKRIV #########################################





