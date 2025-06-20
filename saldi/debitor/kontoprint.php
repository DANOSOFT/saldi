<?php #topkode_start
// ----------------debitor/kontoprint-----lap 3.5.5---2015.04.09-------
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
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
//2013.05.10 Tjekker om formular er oprettet og opretter hvis den ikke er.
//2013.05.12 Virker nu også når der er mere end 1 konto
//2015.03.16 
//2015.04.09 Sidste side blev ikke udskrevet v. flere sider. Ændrer $side til $side-1. 20150409

@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/formfunk.php");
include("../includes/forfaldsdag.php");

$konto_fra=if_isset($_GET['konto_fra']);
$konto_til=if_isset($_GET['konto_til']);
$dato_fra=if_isset($_GET['dato_fra']);
$dato_til=if_isset($_GET['dato_til']);
$kontoart=if_isset($_GET['kontoart']);

($dato_fra)?$dato_fra=usdate($dato_fra):$dato_fra="1970-01-01";
$dato_til=usdate($dato_til);

$formular=11;
$fsize=filesize("../includes/faktinit.ps");
$fp=fopen("../includes/faktinit.ps","r");
$initext=fread($fp,$fsize);
fclose($fp);

if (!$formularsprog) $formularsprog='dansk';
$r=db_fetch_array(db_select("select count(id) as antal from formularer where formular = '$formular' and lower(sprog)='$formularsprog'",__FILE__ . " linje " . __LINE__));
if ($r['antal']<5) {
	include("../includes/formularimport.php");
	formularimport("../importfiler/formular.txt",'11');
}

print "<!-- kommentar for at skjule uddata til siden \n";
if (!file_exists("../logolib/$db_id")) mkdir("../logolib/$db_id"); 
if (file_exists("../logolib/$db_id/bg.pdf")) {
	if (system("which pdftk")) $logoart='PDF';
	else {
		print "<BODY onLoad=\"JavaScript:alert('pdftk ikke fundet, PDF kan ikke anvendes som baggrund');\">";
	}
} elseif (file_exists("../logolib/$db_id/$formular.ps")) {
	$logo="../logolib/$db_id/$formular.ps";
	$logoart='PS';
} elseif (file_exists("../logolib/$db_id/bg.ps")) {
	$logo="../logolib/$db_id/bg.ps";
	$logoart='PS';
} else {
	$formularsprog=strtolower($formularsprog);
	
if ($formularsprog!='dansk') $tmp="'dansk' or sprog=''";	
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
#if ($rykkernr[0]) $printfilnavn=abs($bruger_id)."_".date("his")."/"."$rykkernr[0]";
#else 
$printfilnavn=abs($bruger_id)."_".date("his")."/"."kontoudtog";
$fp1=fopen("../temp/$db/$printfilnavn","w");
fwrite($fp,$initext);
#$printfilnavn="$db_id"."$bruger_id";
#$fp1=fopen("../temp/$db/$printfilnavn","w");

if (!$konto_til && $konto_fra) $konto_til = $konto_fra;
if (!$konto_til) $konto_til = '9999999999';
if (!$konto_fra) $konto_fra = '1';

$x=0;
if (is_numeric($konto_fra)) $qtxt="select id from adresser where kontonr>='$konto_fra' and kontonr<='$konto_til' and art = '$kontoart' and lukket != 'on'";
elseif ($konto_fra && $konto_fra!='*') {
		$konto_fra=str_replace("*","%",$konto_fra);
		$tmp1=strtolower($konto_fra);
		$tmp2=strtoupper($konto_fra);
		$qtxt = "select id from adresser where (firmanavn like '$konto_fra' or lower(firmanavn) like '$tmp1' or upper(firmanavn) like '$tmp2') and art = '$kontoart' order by firmanavn";
	} else $qtxt = "select id from adresser where art = '$kontoart' order by firmanavn";

$q=db_select("$qtxt",__FILE__ . " linje " . __LINE__);
while($r=db_fetch_array($q)){
	$konto_id[$x]=$r['id'];
	$x++;
}

for ($q=0;$q<count($konto_id);$q++) {
	$udskrevet=NULL;
	$fp=$fp1;
	$x=0;
	$query = db_select("select * from formularer where formular = '$formular' and art = '3'",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
			if ($row['beskrivelse']=='generelt') {	
				$antal_ordrelinjer=$row['xa'];
				$ya=$row['ya'];
				$linjeafstand=$row['xb'];
				$Opkt=$ya-($antal_ordrelinjer*$linjeafstand);	 
			} else {
				$x++;
				$variabel[$x]=$row['beskrivelse'];
				$justering[$x]=$row['justering'];
				$xa[$x]=$row['xa'];
				$str[$x]=$row['str'];
				$laengde[$x]=$row['xb'];		if ($art=='R2') $formular=7;
		elseif ($art=='R3') $formular=8;

				$color[$x]=$row['color'];
				$fed[$x]=$row['fed'];
				$kursiv[$x]=$row['kursiv'];
				$form_font[$x]=$row['font'];
		}
		$var_antal=$x;
	}
	$side=1;
	$forfalden=0;

		$qxt="select count(id) as postantal from openpost where konto_id='$konto_id[$q]' and transdate >= '$dato_fra'";
		$r = db_fetch_array(db_select("$qxt",__FILE__ . " linje " . __LINE__));
		$postantal[$q]=$r['postantal'];
		
	
	if ($konto_id[$q] && $postantal[$q]) {
		$q0=db_select("select * from adresser where id=$konto_id[$q]",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q0)) {
			$betalingsbet=$r['betalingsbet'];
			$betalingsdage=$r['betalingsdage'];
		if ($mailfakt) {
			$mailantal++;		
			$pfnavn="Kontoudtog".$konto_id[$q];
			$pfliste[$mailantal]=$pfnavn;
			$pfnavn=$db."/".$pfnavn;
			$fp2=fopen("../temp/$pfnavn","w");
			$fp=$fp2;
			$email[$mailantal]=$r['email'];
			$mailsprog[$mailantal]=strtolower($r['sprog']);
		} else $nomailantal++;
		fwrite($fp,$initext);
		$debet=0;
		$kredit=0;
#		$kontodate=$r['transdate'];	
#		$valuta=$r['valuta'];
#		($r['amount']>=0)?$debet=$r['amount']:$kredit=$r['amount']*-1;
#		if (!$valuta) $valuta='DKK';
		$form_nr[$mailantal]=$formular;
		if (!$formularsprog) $formularsprog="dansk";
		$y=$ya;
		if ($dato_fra>'1970-01-01') {
			$qxt="select sum(amount) as saldo from openpost where konto_id=$konto_id[$q] and transdate < '$dato_fra'";
			$r1 = db_fetch_array(db_select("$qxt",__FILE__ . " linje " . __LINE__));
			$saldo=$r1['saldo'];
			for ($z=1; $z<=$var_antal; $z++) {
				if ($variabel[$z]=="dato") {
					$z_dato=$z;
					$dkdato=dkdato($r1['transdate']);
					skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", "Primosaldo", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
				}
				if ($variabel[$z]=="saldo") {
					$z_saldo=$z;
					$dksaldo=dkdecimal($saldo);
					if (!$dksaldo)$dksaldo="0,00"; 
					skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$dksaldo", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
				}
			}
		} else {
			$saldo=0;
			$dksaldo="0,00";
		}
		$y=$y-4;
		$qxt="select * from openpost where konto_id='$konto_id[$q]'  and transdate >= '$dato_fra' order by transdate,id";
		$q1 = db_select("$qxt",__FILE__ . " linje " . __LINE__);
		while ($r1 = db_fetch_array($q1)) {
#cho "$r1[transdate] $saldo $r1[amount]<br>"; 
			$debet=0;
			$kredit=0;
			($r1['amount']>=0)?$debet=$r1['amount']:$kredit=$r1['amount']*-1;
			$saldo+=$debet-$kredit;
			if (!$r1['valuta']) $r1['valuta']='DKK';
			if (!$r1['valutakurs']) $r1['valutakurs']=100;
			$valuta=$r1['valuta'];
			$valutakurs=$r1['valutakurs']*1;
			$dkkamount=$r1['amount']*100/$valutakurs;
			if ($debet) $forfaldsdato=forfaldsdag($r1['transdate'], $betalingsbet, $betalingsdage);
			else $forfaldsdato=NULL;
			if ($deb_valuta!="DKK" && $deb_valuta!=$valuta) $amount=$dkkamount*100/$deb_valutakurs;
			elseif ($deb_valuta==$valuta) $amount=$r2['amount'];
			else $amount=$dkkamount;
			if ($deb_valuta=='DKK') $amount=$dkkamount;
#			$saldo+=$amount; 20150316
			$dkkforfalden+=$dkkamount;
			$belob=dkdecimal($amount);
			for ($z=1; $z<=$var_antal; $z++) {
 				if ($variabel[$z]=="dato") {
 					$z_dato=$z;
 					skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", dkdato($r1['transdate']), "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
				}
 				if ($variabel[$z]=="forfaldsdato") {
 					$z_forfaldsdato=$z;
 					skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", $forfaldsdato, "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
				}
				if ($variabel[$z]=="faktnr") {
					$z_faktnr=$z;
					skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$r1[faktnr]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
				}
				if ($variabel[$z]=="beskrivelse") {
					$z_beskrivelse=$z;
					skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$r1[beskrivelse]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
				}
				if ($variabel[$z]=="debet") {
					$z_debet=$z;
					if ($debet) skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", dkdecimal($debet), "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
				}
				if ($variabel[$z]=="kredit") {
					$z_kredit=$z;
					if ($kredit) skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", dkdecimal($kredit), "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
				}
				if ($variabel[$z]=="saldo") {
					$z_saldo=$z;
					$dksaldo=dkdecimal($saldo);
					if (!$dksaldo)$dksaldo="0,00"; 
					skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$dksaldo", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
				}
				if (strstr($variabel[$z],"bel") && $belob) {
					$z_belob=$z;
					skriv($str[$z], "$fed[$z]", "$kursiv[$z]", "$color[$z]", $belob, "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
				}
			}	
		$y=$y-4;
		if ($y<=$Opkt) {
			formulartekst($konto_id[$q],$formular,$formularsprog); 		 
			$ialt=dkdecimal($forfalden);
			find_form_tekst("$konto_id[$q]", "S","$formular","0","$linjeafstand","");
			bundtekst($konto_id[$q]);
			$udskrevet=$side-1; #20150410
 		}
#cho "$udskrevet!=$side<br>";
		}
		if ($udskrevet!=$side) {
			formulartekst($konto_id[$q],$formular,$formularsprog); 		 
			$ialt=dkdecimal($forfalden);
			find_form_tekst("$konto_id[$q]", "S","$formular","0","$linjeafstand","");
			bundtekst($konto_id[$q]);
		}
#echo "formulartekst($konto_id[$q],$formular,$formularsprog)<br>";
		
	}
}
		}
fclose($fp);
#xit;
#if ($mailantal>0) include("mail_faktura.php");
#cho "lukker nu<br>";
#xit;
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
} #else print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/udskriv.php?ps_fil=$db/$printfilnavn\">";
#xit;
if ($nomailantal>0) {
 	print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/udskriv.php?ps_fil=$db/$printfilnavn&udskriv_til=PDF\">";
	
}
#print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
exit;

?>




