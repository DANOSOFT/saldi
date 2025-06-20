<?php #topkode_start

#----------------- debitor/formprintfunc.php --------- 2010.03.21 ----------
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

function formularprint($id,$formular,$lev_nr,$charset) {
	
include("../includes/std_func.php");
include("../includes/formfunk.php");
include("../includes/var2str.php");
	
	global $db;
	global $db_id;
	global $dkdato;
	global $form_id;
#	global $formular;
	global $formularsprog;
	global $fp;
	global $gebyr;
	global $ialt;
#	global $id;
	global $lev_nr;
	global $linjeafstand;	
	global $logo;
	global $moms;
	global $momsgrundlag;
	global $momssats;
	global $nextside;
	global $printerid;
	global $ref;
	global $side;
	global $subtotal;
	global $sum;
	global $transportsum;
	global $y;
	global $ya;
	
$fp1=NULL;$kommentarprint=NULL;$skjul_nul_lin=NULL;
$folgeseddel=0;$mailantal=0;$nomailantal=0;

$form=array();

if ($id==-1){	# Saa er der flere fakturaer
	$ordre_antal = $_GET['ordre_antal'];
	$ordre_id = explode(",", $_GET['skriv']);
	if (strpos($formular,",")) {
	 list($formular,$folgeseddel)=explode(",",$formular);
	}
} else {
	$ordre_id[0]=$id;
	$ordre_antal=1;	
}
if ($formular==3) $folgeseddel=1;
if ($formular!=3 && $folgeseddel) {
	for ($q=0; $q<$ordre_antal; $q++) {
		$form[$q]=$formular;
		$r=db_fetch_array(db_select("select lev_addr1, lev_postnr from ordrer where id = $ordre_id[$q]",__FILE__ . " linje " . __LINE__));
		if ($r['lev_addr1'] && $r['lev_postnr']) {
			$form[$q]=3;
			$ordre_antal++;
			for ($z=$ordre_antal; $z>$q; $z--) {
				$ordre_id[$z]=$ordre_id[$z-1];	
			} 
			$q++;
			$form[$q]=$formular;
		}
	}
}
$r=db_fetch_array(db_select("select * from grupper where art='DIV' and kodenr='3'",__FILE__ . " linje " . __LINE__)); 
$incl_moms=$r['box1'];
if ($folgeseddel) {
	$kommentarprint=$r['box3'];
	$skjul_nul_lin=$r['box8'];
} 
$fsize=filesize("../includes/faktinit.ps");
$initfil=fopen("../includes/faktinit.ps","r");
$initext=fread($initfil,$fsize);
fclose($initfil);	
		
if ( ! file_exists("../temp/$db") ) mkdir("../temp/$db", 0775);

if ($ordre_antal>1) {
	if ($formular<=1) $printfilnavn="tilbud";
	if ($formular==2) $printfilnavn="ordrebek";
	if ($formular==3) $printfilnavn="flg_seddel";
	if ($formular==4) $printfilnavn="faktura";
	if (!$fp=fopen("../temp/$db/$printfilnavn","w")) {
		return ("kan ikke skrive til $pfnavn");
	} 
}
for ($q=0; $q<$ordre_antal; $q++) {
	$fp=$fp1;
	if (isset($form[$q])) $formular=$form[$q];
	$varenr=array(); $vare_id=array(); $linje_id=array(); $antal=array(); $tidl_lev=array(); $rest=array();
	$enhed=array(); $rabat=array(); $pris=array(); $l_sum=array(); $linjesum=array(); 
	$sum='';$transportsum=0;
	$query = db_select("select email,ordrenr,fakturanr,mail_fakt,pbs,art,ref,sprog from ordrer where id = '$ordre_id[$q]'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$ref=$row['ref'];
	$ordrenr=$row['ordrenr'];
	$fakturanr=$row['fakturanr'];
	$mail_fakt=$row['mail_fakt'];
	$email[0]=$row['email'];
	$pbs=$row['pbs'];
	$formularsprog=strtolower($row['sprog']);
	if (!$formularsprog) $formularsprog="dansk";
	if (($formular==4)||($formular==5)) {
		if ($row['art']=="DK") $formular=5;
		else $formular=4;
	}
	$y=185;
	$antal_ordrelinjer=25;
	$x=0;
	
	$query = db_select("select * from formularer where formular = '$formular' and art = '1' and beskrivelse = 'LOGO' and lower(sprog)='$formularsprog'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {$logo_X=$row['xa']*2.86; 	$logo_Y=$row['ya']*2.86;}
	else {$logo_X=430; $logo_Y=758;}
	if (file_exists("../logolib/logo_$db_id.eps")){$logo="../logolib/logo_$db_id.eps";}
	else {$logo="../logolib/logo.eps";}
	$fsize=filesize($logo);
	$logofil=fopen($logo,"r");
	$translate=0;
	$logo="";
	while (!feof($logofil)) {
		 $linje=fgets($logofil);
		 if (substr($linje,0,2)!="%!") {
			 if (strstr($linje, "translate")&&(!$translate)) {
				 $linje="$logo_X $logo_Y translate \n"; 
				 $translate=1;
			 }
			 $logo=$logo.$linje;
		} 
	}
	fclose($logofil);
	$query = db_select("select * from formularer where formular = '$formular' and art = '3' and lower(sprog)='$formularsprog'",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		if ($row['beskrivelse']=='generelt') {	
			$antal_ordrelinjer=$row['xa'];
			$ya=$row['ya'];
			$linjeafstand=$row['xb'];
	#		$Opkt=$y-($antal_ordrelinjer*$linjeafstand);	 
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
	if ($formular==3 && !$lev_nr) {
		$r2 = db_fetch_array(db_select("select MAX(lev_nr) as lev_nr from batch_salg where ordre_id = $ordre_id[$q]",__FILE__ . " linje " . __LINE__));
		$lev_nr=$r2['lev_nr']*1;
	}
	if ($mail_fakt && $formular!=3) {
		$mailantal++;		
		if ($formular<=1) $pfnavn="tilbud".$ordrenr;
		if ($formular==2) $pfnavn="ordrebek".$ordrenr;
		if ($formular==4) $pfnavn="fakt".$fakturanr;
		if ($formular==5) $pfnavn="kn".$fakturanr;
		$email[$mailantal]=$email[0];
		$mailsprog[$mailantal]=$formularsprog;
		$form_nr[$mailantal]=$formular;
		$pfliste[$mailantal]=$pfnavn;
		$pfnavn="../temp/".$db."/".$pfnavn;
		if (!$fp=fopen("$pfnavn","w")) {
			return ("kan ikke skrive til $pfnavn");
		} 
		$fp=$fp2;
	} else {
		$nomailantal++;
		if ($ordre_antal<=1) { #mere sige navn til udskrifter.
			if ($formular<=1) $printfilnavn="tilbud".$ordrenr;
			if ($formular==2) $printfilnavn="ordrebek".$ordrenr;
			if ($formular==3) $printfilnavn="flgs".$ordrenr."_".$lev_nr;
			if ($formular==4) $printfilnavn="fakt".$fakturanr;
			if ($formular==5) $printfilnavn="kn".$fakturanr;
			$pfnavn="../temp/".$db."/".$printfilnavn;
			if (!$fp=fopen("$pfnavn","w")) {
				return ("kan ikke skrive til $pfnavn");
			} 
		}
	}
	$side=1;
	fwrite($fp,$initext);
	$rabat[0]=formulartekst($ordre_id[$q],$formular,$formularsprog); 
	if ($ordre_id[$q]){
		$id=$ordre_id[$q];
		$x=0;
		$sum=0;
		$momssum=0;
		$tmp=0;
		$query = db_select("select * from ordrelinjer where ordre_id = $ordre_id[$q] order by posnr",__FILE__ . " linje " . __LINE__);
		while($row = db_fetch_array($query)){
			if ($row[posnr]>0){
				$x++;
				$varenr[$x]=trim($row['varenr']);
				$projekt[$x]=($row['projekt']);
				$beskrivelse[$x]=stripslashes(trim($row['beskrivelse']));
				if ($charset=="utf-8") {
					$varenr[$x]=utf8_decode($varenr[$x]);
					$beskrivelse[$x]=utf8_decode($beskrivelse[$x]);
				}
				if (strpos($beskrivelse[$x],"\$ultimo")||strpos($beskrivelse[$x],"\$maaned")||strpos($beskrivelse[$x],"\$aar")){
					$beskrivelse[$x]=var2str($beskrivelse[$x],$ordre_id[$q]);
				}
				if ($varenr[$x]){
					$vare_id[$x]=$row['vare_id'];
					$linje_id[$x]=$row[id];
					$antal[$x]=$row['antal']*1;
					$dkantal[$x]=str_replace(".",",",$antal[$x]);
					list($a,$b)=split(",",$dkantal[$x]);
					$b=$b*1;
					if ($b) $dkantal[$x]="$a,$b";
					else $dkantal[$x]=$a;
					if ($formular==5){$antal[$x]=$antal[$x]*-1;}
					if ($formular==3){
						$lev_antal[$x]=0;
						$q2 = db_select("select antal from batch_salg where linje_id = $linje_id[$x] and lev_nr = $lev_nr",__FILE__ . " linje " . __LINE__);
						while ($r2 = db_fetch_array($q2)){$lev_antal[$x]=$lev_antal[$x]+$r2['antal'];}
						$tidl_lev[$x]=0;
						$q2 = db_select("select antal from batch_salg where linje_id = $linje_id[$x] and lev_nr < $lev_nr",__FILE__ . " linje " . __LINE__);
						while ($r2 = db_fetch_array($q2)){$tidl_lev[$x]=$tidl_lev[$x]+$r2['antal'];}
						$rest[$x]=$antal[$x]-$lev_antal[$x]-$tidl_lev[$x];
					}
					$enhed[$x]=$row['enhed'];
					$pris[$x]=dkdecimal($row['pris']);
					$rabat[$x]=dkdecimal($row['rabat']);
					$l_sum[$x]=round(($row['pris']*$antal[$x]-($row['pris']*$antal[$x]*$row[rabat]/100))+0.0001,2); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
					$linjesum[$x]=dkdecimal($row['pris']*$antal[$x]-($row['pris']*$antal[$x]*$row[rabat]/100));
					if ($row[momsfri]!='on') {
						$momssum=$momssum+round(($row['pris']*$antal[$x]-($row['pris']*$antal[$x]*$row[rabat]/100))+0.0001,2); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
if ($incl_moms) {
							$tmp=round(($row['pris']+$row['pris']*$momssats/100)+0.0001,2);
							$pris[$x]=dkdecimal($tmp);
							$linjesum[$x]=dkdecimal($tmp*$antal[$x]-($tmp*$antal[$x]*$row[rabat]/100));
						}
					}
					$sum=$sum+round(($row['pris']*$antal[$x]-($row['pris']*$antal[$x]*$row[rabat]/100))+0.0001,2); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
					if ($formular==3 && $skjul_nul_lin && !$lev_antal[$x]) $varenr[$x]=NULL; #  
				}
			}
			$linjeantal=$x;
		}
		$y=$ya;
		$Opkt=$y-($antal_ordrelinjer*$linjeafstand);	 
		for ($x=1;$x<=$linjeantal; $x++) {
			$transportsum=$transportsum+$l_sum[$x-1];
			if ($kommentarprint=='on'||$formular!=3||$varenr[$x]) {	#Fordi tekst uden varenr ikke skal med paa foelgesedlen med mindre det er angivet i "formularprint";
				for ($z=1; $z<=$var_antal; $z++) {
					if ($variabel[$z]=="varenr") $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular"); # ellers kommer varenummer ikke med paa 1. linje paa side 2 . og 3
					if ($variabel[$z]=="varenr")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$varenr[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="projekt")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$projekt[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="antal")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$dkantal[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="antal")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$dkantal[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular"); #ellers kommer antal ikke med paa 1. linje paa side 2 . og 3
					if ($variabel[$z]=="lev_antal")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$lev_antal[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="tidl_lev")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$tidl_lev[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="lev_rest")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$rest[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="pris")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$pris[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="enhed")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$enhed[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="rabat")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$rabat[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="linjesum")  $svar=skriv("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$linjesum[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]","$formular");
					if ($variabel[$z]=="beskrivelse") $skriv_beskriv[$x]=$z; 
				}
					if ($z=$skriv_beskriv[$x]) $y2=ombryd("$str[$z]", "$fed[$z]", "$kursiv[$z]", "$color[$z]", "$beskrivelse[$x]", "ordrelinjer_".$Opkt, "$xa[$z]", "$y", "$justering[$z]", "$form_font[$z]",$laengde[$z]);
				$y=$y2;
				if ($y==0) $y=$ya;
				$y=$y-4;
			}
		}
 		$moms=dkdecimal($momssum*$momssats/100);
		$momsgrundlag=dkdecimal($momssum);
		$momssum=round($momssum+0.0001,2);
		$sum=round($sum+0.0001,2);
		$ialt=dkdecimal($sum+$momssum*$momssats/100); 
		$sum=dkdecimal($sum);
	}
	find_form_tekst($id, 'S', $formular); # Sum paa sidste side.

	bundtekst($ordre_id[$q]); # Uden denne skrives kun  side 1
	if ($mail_fakt) fclose($fp2);
}
fclose($fp);
#exit;
if ($mailantal>0) {
	ini_set("include_path", ".:../phpmailer");
	require("class.phpmailer.php");
	for($x=1;$x<=$mailantal;$x++) {
		system ("/usr/bin/ps2pdf ../temp/$db/$pfliste[$x] ../temp/$db/$pfliste[$x].pdf");
		$svar=send_mails("../temp/$db/$pfliste[$x].pdf",$email[$x],$mailsprog[$x],$form_nr[$x],$charset);	
return($svar);
	}
}
#if ($mailantal>0) include("mail_faktura.php");
#if ($nomailantal>0) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/udskriv.php?ps_fil=$db/$printfilnavn&id=$id\">";
#elseif ($popup) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
#else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	return($svar);
} #endfunc formularprint;
###################################### FAKTURAHOVED ######################################

function formulartekst($id,$formular,$formularsprog){
	
#	global $formular;
	global $momssats;
	global $dkdato;
	global $connection;
	global $fp;
	global $side;
#	global $formularsprog;
	
	$rabat=0;
	include("../includes/ordreopslag.php");
	
	if ($art=="DO") {$art="Faktura";}
	else {$art="Kreditnota";}
	
	$query = db_select("select * from ordrelinjer where ordre_id = '$id' and rabat > '0'",__FILE__ . " linje " . __LINE__);
	if($row = db_fetch_array($query)) {$rabat="y";}

	$faktdato=dkdato($fakturadate);
	$query = db_select("select * from ordrelinjer where ordre_id = '$id' and rabat > '0'",__FILE__ . " linje " . __LINE__);
	if($row = db_fetch_array($query)) {$rabat="y";}

	$query = db_select("select * from formularer where formular = '$formular' and art = '1' and beskrivelse != 'LOGO' and lower(sprog)='$formularsprog'",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$xa=$row['xa']*2.86;
		$ya=$row['ya']*2.86;
		$xb=$row['xb']*2.86;
		$yb=$row['yb']*2.86;
		$lw=$row['str'];
		$color=$row['color'];
		$tmp=strlen($color);
		for ($a=$tmp;$a<9;$a++) $color="0".$color;
		$tmp1=substr($color,-9,3)/100;
		$tmp2=substr($color,-6,3)/100;
		$tmp3=substr($color,-3,3)/100;
		$color="$tmp1 $tmp2 $tmp3 setrgbcolor";

		fwrite($fp," $xa $ya moveto $xb $yb lineto $lw setlinewidth $color stroke \n");
	}

	find_form_tekst($id, 'A', $formular);

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
global $linjeafstand;

$y=$ya-$linjeafstand;
$side=$side+1;

fwrite($fp, $logo);
return $y;
# fwrite($fp,"showpage");
}

function send_mails($filnavn,$email,$mailsprog,$form_nr,$charset) {
	global $db;
	global $mailantal;
	
	$q=db_select("select * from formularer where formular='$form_nr' and art='5'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($r['xa']=='1') $subjekt=$r['beskrivelse'];	
		elseif ($r['xa']=='2') $mailtext=$r['beskrivelse'];
	}
	$row = db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
	$afsendermail=$row['email'];
	$afsendernavn=$row['firmanavn'];
	if (!$afsendermail || !$afsendernavn) {
		return("Missing sender mail");
#		print "<BODY onLoad=\"javascript:alert('Firmanavn eller e-mail for afsender ikke udfyldt.\\nSe (Indstillinger -> stamdata).\\nMail ikke afsendt!')\">";
#		return;
	}
	
	if ($charset=="UTF-8") {
		$subjekt=utf8_decode($subjekt);
		$mailtext=utf8_decode($mailtext);
		$afsendernavn=utf8_decode($afsendernavn);
	}
/*
echo "<br>Fra $afsendernavn | $afsendermail <br>";
echo "Til $email<br>";
echo "Emne: $subjekt<br>";
echo "tekst	$mailtext<br>";
*/	
	
	$mail = new PHPMailer();

	$mail->IsSMTP();                                   // send via SMTP
	$mail->Host  = "localhost"; // SMTP servers
	$mail->SMTPAuth = false;     // turn on SMTP authentication
			#	$mail->Username = "jswan";  // SMTP username
			#	$mail->Password = "secret"; // SMTP password
			
	$mail->From     = $afsendermail;
	$mail->FromName = $afsendernavn;
	$mail->AddAddress($email); 
	$mail->AddBCC($afsendermail); 
	#	$mail->AddAddress("ellen@site.com");               // optional name
	#	$mail->AddReplyTo("info@site.com","Information");
	
$mail->WordWrap = 50;  // set word wrap
#	$mail->AddAttachment("../temp/$db/mailtext.html");
	$mail->AddAttachment("$filnavn");      // attachment
#	$mail->AddAttachment("/tmp/image.jpg", "new.jpg"); 
	$mail->IsHTML(true);                               // send as HTML

	$ren_text=html_entity_decode($mailtext,ENT_COMPAT,$charset);

	$mail->Subject  =  "$subjekt";
	$mail->Body     =  "$mailtext";
	$mail->AltBody  =  "$ren_text";
	

	if(!$mail->Send()){
			 echo "Fejl i afsendelse til $email<p>";
 				echo "Mailer Error: " . $mail->ErrorInfo;
		 		exit;
	}
#	if ($mailantal==1) print "<BODY onLoad=\"javascript:alert('Mail sendt til $email')\">";
#	else echo "Mail sendt til $email<br>";
	return("Mail sent to $email");
}	




