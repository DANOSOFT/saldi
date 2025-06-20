<?php #topkode_start
// ----------------debitor/rykkerprint-----lap 3.4.2---2014.06.28-------
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
// Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------

// 20120815 søg 20120815 V. Logoplacering blev ikke fundet v. opslag. 
// 20130114 Tilføjet 0 som 1. parameter i "send mails"
// 20140628 Indsat afrund for korrekt sum. Søg 20140628

@session_start();
$s_id=session_id();

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

# 20120815 næste 8 linjer remmet, bliver vist ikke brigt til noget
#$query = db_select("select * from formularer where formular = $formular and art = 1 and beskrivelse = 'LOGO'",__FILE__ . " linje " . __LINE__);
#if ($row = db_fetch_array($query)) {
#	$logo_X=$row['xa']*2.86;
#	$logo_Y=$row['ya']*2.86;
#} else {
#	$logo_X=430;
#	$logo_Y=758;
#}
$fsize=filesize("../includes/faktinit.ps");
$fp=fopen("../includes/faktinit.ps","r");
$initext=fread($fp,$fsize);
fclose($fp);
		
print "<!-- kommentar for at skjule uddata til siden \n";
if (!file_exists("../logolib/$db_id")) mkdir("../logolib/$db_id"); 
if (system("which pdftk") && file_exists("../logolib/$db_id/bg.pdf")) {
	$logoart='PDF';
} elseif (file_exists("../logolib/$db_id/$formular.ps")) {
	$logo="../logolib/$db_id/$formular.ps";
	$logoart='PS';
} elseif (file_exists("../logolib/$db_id/bg.ps")) {
	$logo="../logolib/$db_id/bg.ps";
	$logoart='PS';
} else {

# 20120815 næste 3 linjer tilføjet, og $formularsprog ændret til $tmp i query.
$formularsprog=strtolower($formularsprog);
if (!$formularsprog || $formularsprog=='dansk') $tmp="'dansk' or sprog=''";
else $tmp="'".$formularsprog."'";
#cho "select * from formularer where formular = '$formular' and art = '1' and beskrivelse = 'LOGO' and lower(sprog)=$tmp<br>";
	$query = db_select("select * from formularer where formular = '$formular' and art = '1' and beskrivelse = 'LOGO' and lower(sprog)=$tmp",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {$logo_X=$row['xa']*2.86; 	$logo_Y=$row['ya']*2.86;}
	else {$logo_X=430; $logo_Y=758;}
	if (file_exists("../logolib/logo_$db_id.eps")) $logo="../logolib/logo_$db_id.eps";
	else $logo="../logolib/logo.eps";
	$logoart='EPS';
}
print "-->\n";
#cho "Logo $logo | $logoart<br>";;
if ($logoart != 'PDF') {
	$fsize=filesize($logo);
	$logofil=fopen($logo,"r");
	$translate=0;
	$logo="";
#cho "Logo $logo | $logoart<br>";;
	while (!feof($logofil)) {
		$linje=fgets($logofil);
		if ($logoart=='EPS')	{
			if (substr($linje,0,2)!="%!") {
				if (strstr($linje, "translate")&&(!$translate)) {
					$linje="$logo_X $logo_Y translate \n";
#cho "<br>Linle $linje<br>";
#xit;
					$translate=1;
				}
				$logo=$logo.$linje;
			} 
		} else {
			if (strstr($linje,'showpage')) $linje='';
			if (strstr($linje,'%%PageTrailer')) $linje='';
			if (strstr($linje,'%%Trailer')) $linje='';
			if (strstr($linje,'%%Pages:')) $linje='';
			if (strstr($linje,'%%EOF')) $linje='';
			$logo=$logo.$linje;
		}
	}
	fclose($logofil);
}

$mappe="../temp/$db/$bruger_id"."_*";
system("rm -r $mappe");
$mappe="../temp/$db/".abs($bruger_id)."_".date("his");
mkdir("$mappe", 0775);
if ($rykkernr[0]) $printfilnavn=abs($bruger_id)."_".date("his")."/"."$rykkernr[0]";
else $printfilnavn=abs($bruger_id)."_".date("his")."/"."rykker";
$fp1=fopen("../temp/$db/$printfilnavn","w");

#$printfilnavn="$db_id"."$bruger_id";
#$fp1=fopen("../temp/$db/$printfilnavn","w");
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
		if ($r2=db_fetch_array(db_select("select kurs from grupper, valuta where grupper.art='VK' and grupper.box1='$deb_valuta' and valuta.gruppe = ".nr_cast("grupper.kodenr")." and valuta.valdate <= '$rykkerdate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
			$deb_valutakurs=$r2['kurs'];

		} 
		$x=0;
		$sum=0;
		$momssum=0;
		$tmp=0;
		$y=$ya;
		$forfalden=0;
		$dkkforfalden=0;
		$amount=0;
		$q1 = db_select("select serienr as forfaldsdato, beskrivelse, pris as amount, enhed as openpost_id from ordrelinjer where ordre_id = '$rykker_id[$q]' order by serienr,varenr desc",__FILE__ . " linje " . __LINE__);
		while ($r1 = db_fetch_array($q1)) {
			if ($r1['openpost_id']) {
				if ($r2 = db_fetch_array(db_select("select faktnr, amount, valuta, valutakurs, transdate from openpost where id = '$r1[openpost_id]'",__FILE__ . " linje " . __LINE__))) {
					$r1['faktnr']=$r2['faktnr'];
					if (!$r2['valuta']) $r2['valuta']='DKK';
					if (!$r2['valutakurs']) $r2['valutakurs']=100;
					$valuta=$r2['valuta'];
					$valutakurs=$r2['valutakurs']*1;
					$dkkamount=$r2['amount']*100/$valutakurs;
					if ($deb_valuta!="DKK" && $deb_valuta!=$valuta) $amount=$dkkamount*100/$deb_valutakurs;
					elseif ($deb_valuta==$valuta) $amount=$r2['amount'];
					else $amount=$dkkamount;
				}
			} else {
				$dkkamount=$r1['amount']*100/$valutakurs;
				$amount=$r1['amount'];
			}

			if ($deb_valuta=='DKK') $amount=$dkkamount;

			$forfalden+=afrund($amount,2); #20140628
			$dkkforfalden+=afrund($dkkamount,2); #20140628
			$belob=dkdecimal($amount);
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
			}	
			$y=$y-4;
		}
		formulartekst($rykker_id[$q],$formular,$formularsprog); 		 
		$ialt=dkdecimal($forfalden);
		find_form_tekst("$rykker_id[$q]", "S","$formular","0","$linjeafstand","");
		bundtekst($konto_id[$q]);
		
	}
}
fclose($fp);
if ($mailantal>0) {
	ini_set("include_path", ".:../phpmailer");
	require("class.phpmailer.php");
        if (!isset($exec_path)) $exec_path="/usr/bin";
	for($x=1;$x<=$mailantal;$x++) {
		print "<!-- kommentar for at skjule uddata til siden \n";
		system ("$exec_path/ps2pdf ../temp/$db/$pfliste[$x] ../temp/$db/$pfliste[$x].pdf");
		if ($logoart=='PDF') {
			$out="../temp/$db/".$pfliste[$x]."x.pdf";
			system ("$exec_path/pdftk ../temp/$db/$pfliste[$x].pdf background ../logolib/$db_id/bg.pdf output $out");
			unlink ("$mappe/$pfliste[$x].pdf");
			system  ("mv $out $mappe/$pfliste[$x].pdf");
		} else {
			unlink ("$mappe/$pfliste[$x].pdf");
			system ("mv ../temp/$db/$pfliste[$x].pdf $mappe/$pfliste[$x].pdf");
		}
		print "--> \n";
		$svar=send_mails(0,"$mappe/$pfliste[$x].pdf",$email[$x],$mailsprog[$x],$form_nr[$x]);
	}
} 
if ($nomailantal>0) {
 	print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/udskriv.php?ps_fil=$db/$printfilnavn&udskriv_til=PDF\">";
	exit;
}
print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
exit;

?>




