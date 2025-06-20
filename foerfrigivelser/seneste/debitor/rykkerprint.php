<?php #topkode_start
@session_start();
$s_id=session_id();

// ----------------debitor/rykkerprint-----lap 1.9.2a---08.04.2008-------
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
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------


$kontoliste=isset($_GET['kontoliste'])? $_GET['kontoliste']:Null;
$konto_antal=isset($_GET['kontoantal'])? $_GET['kontoantal']:Null;
$maaned_fra=isset($_GET['maaned_fra'])? $_GET['maaned_fra']:Null;
$maaned_til=isset($_GET['maaned_til'])? $_GET['maaned_til']:Null;
$regnaar=isset($_GET['regnaar'])? $_GET['regnaar']:Null;
$rykkernr=isset($_GET['rykkernr'])? $_GET['rykkernr']:Null;
$formular=6;
$bg="nix";

$rykker_id=explode(";", $_GET['rykker_id']);
$konto_id = explode(";", $kontoliste);

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
		}
		$r=db_fetch_array(db_select("select ordrer.art as art, ordrer.sprog as sprog, grupper.box3 as valuta from ordrer, adresser, grupper where ordrer.id = $rykker_id[$q] and adresser.id=ordrer.konto_id and grupper.kodenr = adresser.gruppe and grupper.art = 'DG'"));
		$sprog=strtolower($r['sprog']);
		$art=$r['art'];
		$valuta=$r['valuta'];
		if (!$valuta) $valuta='DKK';
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
# 	$q1 = db_select("select ordrelinjer.varenr as forfaldsdato, ordrelinjer.beskrivelse as beskrivelse, openpost.faktnr as faktnr, openpost.amount as amount from ordrelinjer, openpost where ordrelinjer konto_id = '$rykker_id[$q]' and openpost.id=ordrelinjer.vare_id");		
		$q1 = db_select("select serienr as forfaldsdato, beskrivelse, pris as amount, enhed as openpost_id from ordrelinjer where ordre_id = '$rykker_id[$q]' order by varenr desc");
		while ($r1 = db_fetch_array($q1)) {
			if ($r1['openpost_id']) {
				if ($r2 = db_fetch_array(db_select("select faktnr, amount, valuta, valutakurs, transdate from openpost where id = '$r1[openpost_id]'"))) {
					$r1['faktnr']=$r2['faktnr'];
					if (!$r2['valuta']) $r2['valuta']='DKK';
					if (!$r2['valutakurs']) $r2['valutakurs']=100;
					if ($valuta=='DKK' && $r2['valuta']!='DKK') $r1['amount']=$r2['amount']*$r2['valutakurs']/100;
					elseif ($valuta!='DKK' && $r2['valuta']=='DKK') {
						if ($r3=db_fetch_array(db_select("select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe = grupper.kodenr and valuta.valdate <= '$r2[transdate]' order by valuta.valdate desc"))) {
							$r1['amount']=$r2['amount']*100/$r3['kurs'];
						} else print "<BODY onLoad=\"javascript:alert('Ingen valutakurs for faktura $r2[faktnr]')\">";	
					}
					elseif ($valuta!='DKK' && $r2['valuta']!='DKK' && $r2['valuta']!=$valuta) {
						$tmp==$r2['amount']*$r2['valuta']/100;
			 			$r1['amount']=$tmp*100/$r2['valutakurs'];
					}	else $r1['amount']=$r2['amount'];
				}
			}
			$forfalden=$forfalden+$r1['amount'];
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
		$ialt=dkdecimal($forfalden);
		find_tekst($id, 'S', $formular);
		bundtekst($konto_id[$q]);
		
	}
}
 print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/udskriv.php?ps_fil=$db.$printfilnavn\">";
# exit;
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





