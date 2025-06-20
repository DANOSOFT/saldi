<?php #topkode_start
@session_start();
$s_id=session_id();

// ----------------debitor/rykkerprint-----lap 3.2.4---2011.11.03-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2011 DANOSOFT ApS
// ----------------------------------------------------------------------

$mailantal=0; $nomailantal=0;

$kontoliste=isset($_GET['kontoliste'])? $_GET['kontoliste']:Null;
$konto_antal=isset($_GET['kontoantal'])? $_GET['kontoantal']:Null;
$maaned_fra=isset($_GET['maaned_fra'])? $_GET['maaned_fra']:Null;
$maaned_til=isset($_GET['maaned_til'])? $_GET['maaned_til']:Null;
$regnaar=isset($_GET['regnaar'])? $_GET['regnaar']:Null;
$rykkernr=isset($_GET['rykkernr'])? $_GET['rykkernr']:Null;
$formular=$rykkernr+5;
if ($formular<6) $formular=6;
$bg="nix";

$rykker_id=explode(";", $_GET['rykker_id']);
$konto_id = explode(";", $kontoliste);


include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/formfunk.php");

$query = db_select("select * from formularer where formular = $formular and art = 1 and beskrivelse = 'LOGO'",__FILE__ . " linje " . __LINE__);
if ($row = db_fetch_array($query)) {
	$logo_X=$row['xa']*2.86;
	$logo_Y=$row['ya']*2.86;
} else {
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

$printfilnavn="$db_id"."$bruger_id";
$fp1=fopen("../temp/$db/$printfilnavn","w");

for ($q=0; $q<$konto_antal; $q++) {
	$fp=$fp1;
	$x=0;
	$query = db_select("select * from formularer where formular = $formular and art = 3",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
			if ($row['beskrivelse']=='generelt') {	
				$antal_ordrelinjer=$row['xa'];
				$ya=$row['ya'];
				$linjeafstand=$row['xb'];
				$Opkt=$ya-($antal_ordrelinjer*$linjeafstand);	 
			}
			else {
				$x++;
				$variabel[$x]=$row['beskrivelse'];
				$justering[$x]=$row['justering'];
				$xa[$x]=$row['xa'];
				$str[$x]=$row['str'];
				$laengde[$x]=$row['xb'];
				$color[$x]=$row['color'];
				$fed[$x]=$row['fed'];
				$kursiv[$x]=$row['kursiv'];
				$form_font[$x]=$row['font'];
		}
		$var_antal=$x;
	}
	$side=1;
	$forfalden=0;
	if (($konto_id[$q])||($rykker_id[$q])) {
		if (!$rykker_id[$q]) {
		}
		$r=db_fetch_array(db_select("select ordrer.mail_fakt as mailfakt,ordrer.email as email,ordrer.art,ordrer.art as art,ordrer.ordredate as rykkerdate,ordrer.sprog as sprog, ordrer.valuta as valuta from ordrer, adresser, grupper where ordrer.id = $rykker_id[$q] and adresser.id=ordrer.konto_id and ".nr_cast("grupper.kodenr")." = adresser.gruppe and grupper.art = 'DG'",__FILE__ . " linje " . __LINE__));
		$mailfakt=$r['mailfakt'];
		if ($mailfakt) {
			$mailantal++;		
			$pfnavn="Rykker".$rykker_id[$q];
			$pfliste[$mailantal]=$pfnavn;
			$pfnavn=$db."/".$pfnavn;
			$fp2=fopen("../temp/$pfnavn","w");
			$fp=$fp2;
			$email[$mailantal]=$r['email'];
			$mailsprog[$mailantal]=strtolower($r['sprog']);
#			$form_nr[$mailantal]=$formular;
		} else $nomailantal++;
		fwrite($fp,$initext);
		$formularsprog=strtolower($r['sprog']);
		$art=$r['art'];
		$rykkerdate=$r['rykkerdate'];	
		$deb_valuta=$r['valuta'];
		if (!$valuta) $valuta='DKK';
		if ($art=='R2') $formular=7;
		elseif ($art=='R3') $formular=8;
		$form_nr[$mailantal]=$formular;
		if (!$formularsprog) $formularsprog="dansk";
#echo "select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$deb_valuta' and valuta.gruppe = ".nr_cast("grupper.kodenr")." and valuta.valdate <= '$rykkerdate' order by valuta.valdate desc<br>";
		if ($r2=db_fetch_array(db_select("select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$deb_valuta' and valuta.gruppe = ".nr_cast("grupper.kodenr")." and valuta.valdate <= '$rykkerdate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
			$deb_valutakurs=$r2['kurs'];

#echo "DVK $deb_valutakurs<br>";

		} 
		$x=0;
		$sum=0;
		$momssum=0;
		$tmp=0;
		$y=$ya;
		$forfalden=0;
		$dkkforfalden=0;
		$amount=0;
# 	$q1 = db_select("select ordrelinjer.varenr as forfaldsdato, ordrelinjer.beskrivelse as beskrivelse, openpost.faktnr as faktnr, openpost.amount as amount from ordrelinjer, openpost where ordrelinjer konto_id = '$rykker_id[$q]' and openpost.id=ordrelinjer.vare_id",__FILE__ . " linje " . __LINE__);		
		$q1 = db_select("select serienr as forfaldsdato, beskrivelse, pris as amount, enhed as openpost_id from ordrelinjer where ordre_id = '$rykker_id[$q]' order by varenr desc",__FILE__ . " linje " . __LINE__);
		while ($r1 = db_fetch_array($q1)) {
			if ($r1['openpost_id']) {
				if ($r2 = db_fetch_array(db_select("select faktnr, amount, valuta, valutakurs, transdate from openpost where id = '$r1[openpost_id]'",__FILE__ . " linje " . __LINE__))) {
					$r1['faktnr']=$r2['faktnr'];
					if (!$r2['valuta']) $r2['valuta']='DKK';
					if (!$r2['valutakurs']) $r2['valutakurs']=100;
					$valuta=$r2['valuta'];
					$valutakurs=$r2['valutakurs']*1;
					$dkkamount=$r2['amount']*100/$valutakurs;
#echo "amount $r2[amount]<br>";
#echo "A $rykkerdate $deb_valuta $deb_valutakurs $valutakurs $r2[amount] $dkkamount $amount<br>"; 
					if ($deb_valuta!="DKK" && $deb_valuta!=$valuta) $amount=$dkkamount*100/$deb_valutakurs;
					elseif ($deb_valuta==$valuta) $amount=$r2['amount'];
					else $amount=$dkkamount;
#echo "B >$deb_valuta==$valuta<  $dkkamount $amount<br>"; 

/*
					if ($deb_valuta=='DKK' && $valuta!='DKK') {#$r1['amount']=$r2['amount']*$r2['valutakurs']/100;
						$amount=$r2['amount'];
						$dkkamount=$amount*$valutakurs/100; 
echo "amount $amount  dkk $dkkamount<br>";
					} elseif ($deb_valuta!='DKK' && $valuta=='DKK') {
#echo "select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$deb_valuta' and valuta.gruppe = ".nr_cast("grupper.kodenr")." and valuta.valdate <= '$r2[transdate]' order by valuta.valdate desc<br>"; 
#						if ($r3=db_fetch_array(db_select("select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$deb_valuta' and valuta.gruppe = ".nr_cast("grupper.kodenr")." and valuta.valdate <= '$r2[transdate]' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
							$dkkamount=$r2['amount'];
							$amount=$dkkamount*100/$deb_valutakurs;
#						} else print "<BODY onLoad=\"javascript:alert('Ingen valutakurs for faktura $r2[faktnr]')\">";	
					} elseif ($deb_valuta!='DKK' && $valuta!='DKK' && $valuta!=$deb_valuta) {
						$dkkamount=$r2['amount']*$valutakurs/100;
			 			$amount=$dkkamount*100/$valutakurs;
					}	else {
						$dkkamount=$r2['amount'];
						$amount=$r2['amount'];
					}
*/
				}
			} else {
				$dkkamount=$r1['amount']*100/$valutakurs;
				$amount=$r1['amount'];
			}

#echo "Y $dkkamount $amount<br>"; 
			if ($deb_valuta=='DKK') $amount=$dkkamount;
#echo "Z $dkkamount $amount<br>"; 
#echo "$amount => <br>";
# echo "FF $forfalden amount $amount<br>";
			$forfalden+=$amount;
			$dkkforfalden+=$dkkamount;
			$belob=dkdecimal($amount);
# exit;
# echo "FF $forfalden amount $amount<br>";
#if ($deb_valuta=='DKK') $belob="DKK $belob";
#echo "Forfalden $forfalden<br>";
#exit;
		for ($z=1; $z<=$var_antal; $z++) {
 				if ($variabel[$z]=="dato") {
 					$z_dato=$z;
 					skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", dkdato($r1['forfaldsdato']), "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
				}
				if ($variabel[$z]=="faktnr") {
					$z_faktnr=$z;
					skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$r1[faktnr]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
				}
				if ($variabel[$z]=="beskrivelse") {
					$z_beskrivelse=$z;
					skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$r1[beskrivelse]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
				}
				if (strstr($variabel[$z],"bel") && $belob) {
					$z_belob=$z;
					skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", $belob, "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
				}
#				if (strstr($variabel[$z],"bel") && $dkkamount) {
#					$z_belob=$z;
#					skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", dkdecimal($dkkamount), "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
#				}
			}	
			$y=$y-4;
		}
		formulartekst($rykker_id[$q],$formular,$formularsprog); 		 
		$ialt=dkdecimal($forfalden);
		find_form_tekst("$rykker_id[$q]", "S","$formular","0","$linjeafstand","");
		bundtekst($konto_id[$q]);
		
	}
}
#if ($mailantal>0) include("mail_faktura.php");
if ($mailantal>0) {
	ini_set("include_path", ".:../phpmailer");
	require("class.phpmailer.php");
        if (!isset($exec_path)) $exec_path="/usr/bin";
	for($x=1;$x<=$mailantal;$x++) {
		system ("$exec_path/ps2pdf ../temp/$db/$pfliste[$x] ../temp/$db/$pfliste[$x].pdf");
		$svar=send_mails("../temp/$db/$pfliste[$x].pdf",$email[$x],$mailsprog[$x],$form_nr[$x]);	
	}
} #else print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/udskriv.php?ps_fil=$db/$printfilnavn\">";
if ($nomailantal>0) {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/udskriv.php?ps_fil=$db/$printfilnavn\">";
	exit;
}
print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
exit;

?>




