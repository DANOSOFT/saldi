<?php #topkode_start
@session_start();
$s_id=session_id();

// -------------------------------------------------------debitor/rykkerprint-----lap 1.1.0----------
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
// Copyright (c) 2004-2007 DANOSOFT ApS
// ----------------------------------------------------------------------

$kontoliste=$_GET['kontoliste'];
$konto_antal=$_GET['kontoantal'];
$maaned_fra=$_GET['maaned_fra'];
$maaned_til=$_GET['maaned_til'];
$regnaar=$_GET['regnaar'];
$rykkernr=$_GET['rykkernr'];
$formular=6;
$bg="nix";

$rykker_id=explode(";", $_GET['rykker_id']);
$konto_id = explode(";", $_GET['kontoliste']);

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
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
	$forfalden=0;
	if (($konto_id[$q])||($rykker_id[$q])) {
		if (!$rykker_id[$q]) {
/*			$rykkerdate=date("Y-m-d");
			$r_ordrenr=0;
			if ($r = db_fetch_array(db_select("select MAX(ordrenr) as r_ordrenr from ordrer where art='RB'"))) $r_ordrenr=$r['r_ordrenr'];
			$r_ordrenr++;
			$r= db_fetch_array(db_select("select * from adresser where id ='$konto_id[$q]'"));
			db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, betalingsdage, betalingsbet, cvrnr, ean, institution, notes, art, ordredate, levdate, fakturadate, momssats, hvem, tidspkt, ref, status) 
				values 
				('$r_ordrenr', '$konto_id[$q]', '$r[kontonr]', '$r[firmanavn]', '$r[addr1]', '$r[addr2]', '$r[postnr]', '$r[bynavn]', '$r[land]', '$r[betalingsdage]', '$r[betalingsbet]', '$r[cvrnr]', '$r[ean]', '$r[institution]', '$r[notes]', 'RB', '$rykkerdate', '$rykkerdate', '$rykkerdate', '0', '$brugernavn', '$tidspkt', '$r[ref]', '2')");
			$r= db_fetch_array(db_select("select id from ordrer where ordrenr ='$r_ordrenr' and art='RB'"));
			$q2 = db_select("select * from openpost where konto_id = $konto_id[$q] and udlignet != 1");
			$rykker_id[$q]=$r['id'];
			$id=$konto_id[$q];
			while ($r2 = db_fetch_array($q2)) {
				$q3 = db_select("select betalingsbet, betalingsdage from ordrer where fakturanr='$r2[faktnr]'");
				$r3 = db_fetch_array($q3);
				$forfaldsdag=usdate(forfaldsdag($r2[transdate], $r3[betalingsbet], $r3[betalingsdage]));
				if ($forfaldsdag<date("Y-m-d")) {
					db_modify("insert into ordrelinjer (enhed, ordre_id, serienr, beskrivelse) values ('$r2[id]', '$rykker_id[$q]', '$r2[transdate]', '$r2[beskrivelse]')");
					$forfalden=$forfalden+$r2['amount'];
				}
			} 
			$q2 = db_select ("select * from varer where id IN (select xb from formularer where beskrivelse='GEBYR')");
			if ($r2 = db_fetch_array($q2)) {				
				$gebyr=$r2['salgspris'];	
				db_modify("insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, pris, serienr) values ('$rykker_id[$q]', '$r2[varenr]', '$r2[id]', '$r2[beskrivelse]', '1', '$r2[salgspris]' , '$rykkerdate')");
					$forfalden=$forfalden+$r2['salgspris'];
					db_modify("update ordrer set sum='$r2[salgspris]' where id=$rykker_id[$q]");
			}
*/		
		}
		$r=db_fetch_array(db_select("select art, sprog from ordrer where id = $rykker_id[$q]"));
		$sprog=strtolower($r['sprog']);
		$art=$r['art'];
		if ($art=='R2') $formular=7;
		elseif ($art=='R3') $formular=8;
		if (!$sprog) $sprog="dansk";
		formulartekst($rykker_id[$q]); 		 
		$x=0;
		$sum=0;
		$momssum=0;
		$tmp=0;
		$y=$ya;
		$forfalden=0;
# 		$q1 = db_select("select ordrelinjer.varenr as forfaldsdato, ordrelinjer.beskrivelse as beskrivelse, openpost.faktnr as faktnr, openpost.amount as amount from ordrelinjer, openpost where ordrelinjer konto_id = '$rykker_id[$q]' and openpost.id=ordrelinjer.vare_id");		
		$q1 = db_select("select serienr as forfaldsdato, beskrivelse, pris as amount, enhed as openpost_id from ordrelinjer where ordre_id = '$rykker_id[$q]' order by varenr desc");
		while ($r1 = db_fetch_array($q1)) {
			if ($r1[openpost_id]) {
				if ($r2 = db_fetch_array(db_select("select faktnr, amount  from openpost where id = '$r1[openpost_id]'"))) {
					$r1[faktnr]=$r2[faktnr];
					$r1[amount]=$r2[amount];
				}
			}	
			$forfalden=$forfalden+$r1[amount];
			for ($z=1; $z<=$var_antal; $z++) {
 				if ($variabel[$z]=="dato") {
 					$z_dato=$z;
 					skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", dkdato($r1[forfaldsdato]), "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$placering[$z]", "$form_font[$z]");
				}
				if ($variabel[$z]=="faktnr") {
					$z_faktnr=$z;
					skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$r1[faktnr]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$placering[$z]", "$form_font[$z]");
				}
				if ($variabel[$z]=="beskrivelse") {
					$z_beskrivelse=$z;
					skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$r1[beskrivelse]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$placering[$z]", "$form_font[$z]");
				}
				if (strstr($variabel[$z],"bel")) {
					$z_belob=$z;
					skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", dkdecimal($r1[amount]), "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$placering[$z]", "$form_font[$z]");
				}
			}	
			$y=$y-4;
		}
/* 		$q2 = db_select("select xb from formularer where beskrivelse='GEBYR'");
		if ($r2 = db_fetch_array($q2)) {
 			$dato=date("d-m-Y");
 			if (isset($z_dato)) skriv($str[$z_dato], "$fed[$z_dato]", "$kursiv[$z_dato]", "$color[$z_dato]", "$dato", "ordrelinjer_".$Opkt, "$xa[$z_dato]", "$y", "$placering[$z_dato]", "$form_font[$z_dato]");
#			if (isset ($z_fatknr)) skriv($str[$z_faktnr], "$fed[$z_faktnr]", "$kursiv[$z_faktnr]", "$color[$z_faktnr]", "$r2[faktnr]", "ordrelinjer_".$Opkt, "$xa[$z_faktnr]", "$y", "$placering[$z_faktnr]", "$form_font[$z_faktnr]");
			if (isset ($z_beskrivelse)) {
				skriv($str[$z_beskrivelse], "$fed[$z_beskrivelse]", "$kursiv[$z_beskrivelse]", "$color[$z_beskrivelse]", "Rykkergebyr", "ordrelinjer_".$Opkt, "$xa[$z_beskrivelse]", "$y", "$placering[$z_beskrivelse]", "$form_font[$z_beskrivelse]");
			}
			if (isset ($z_belob)) {
				skriv($str[$z_belob], "$fed[$z_belob]", "$kursiv[$z_belob]", "$color[$z_belob]", dkdecimal($r2[xb]), "ordrelinjer_".$Opkt, "$xa[$z_belob]", "$y", "$placering[$z_belob]", "$form_font[$z_belob]");
			}
			$forfalden=$forfalden+$r2[xb];
		}
*/	
		$ialt=dkdecimal($forfalden);
		find_tekst($id, 'S', $formular);
		bundtekst($konto_id[$q]);
		
	}
}
 print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/udskriv.php?ps_fil=$db.$printfilnavn\">";
#exit;
###################################### FAKTURAHOVED ######################################

function formulartekst($id)
{
	global $formular;
	global $momssats;
	global $dkdato;
	global $connection;
	global $fp;
	global $side;
	global $gebyr;
	global $sprog;

	include("../includes/ordreopslag.php");
/*	
	$query = db_select("select * from ordrelinjer where ordre_id = $id and rabat > 0");
	if($row = db_fetch_array($query)) {$rabat="y";}
*/
	$faktdato=dkdato($fakturadate);

echo "select * from formularer where formular = $formular and art = 1 and beskrivelse != 'LOGO'<br>";	
	$query = db_select("select * from formularer where formular = $formular and art = 1 and beskrivelse != 'LOGO'");
	while ($row = db_fetch_array($query)) {
		$xa=$row[xa]*2.86;
		$ya=$row[ya]*2.86;
		$xb=$row[xb]*2.86;
		$yb=$row[yb]*2.86;
		$lw=$row[str];
echo 	" $xa $ya moveto $xb $yb lineto $lw setlinewidth stroke \n<br>";	
		fwrite($fp," $xa $ya moveto $xb $yb lineto $lw setlinewidth stroke \n");
	}

find_tekst($id, 'A', $formular);
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





