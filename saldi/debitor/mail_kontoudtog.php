<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------------------debitor/mail_kontoudtog.php ------patch 3.8.9 ----2020-01-20--------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2020 saldi.dk aps
// ----------------------------------------------------------------------
// 2012.09.06 break ændret til break 1
// 2012.10.04 Gmail afviser mails hvor 'from' ikke er *.saldi.dk søg 20121029
// 2013.10.04 Tilføjet AddReplyTo. Søg AddReplyTo
// 2014.01.28 Ændret from til korrekt afsendermail. Søg 20140128 
// 2015.03.05 Finder selv sidste dato hvor saldi var 0. 20150305-1
// 2015.03.05 Kan nu håndtere flere mailadresser adskilt med ;  20150305-2
// 2015.05.05 Hvis der er forvalgte datoer overrules 20150305-1
// 2018.09.07 Kontoudtog kan nu sendes som vedhæftet PDF - del af indhold flyttet til function kontoprint i includes/formfunc
// 2019.06.18 valuta omregnes nu iht valutakurs.
// 2019.08.16 PHR Connection to external smtp server added. Search for $smtp
// 2019.11.06 PHR Minor changes in senders name / email
// 2019.11.06 PHR '(strpos($kontoliste,':')' replaced by '(strpos($kontoliste,';')'
// 2020.01.20 PHR $afsendernavn replaced by $r[firmanavn] in html file af senders name was represented instrea fo recipient 

@session_start();
$s_id=session_id();

$css="../css/standard.css";
$formular=11;
$modulnr=12;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/forfaldsdag.php");
include("../includes/formfunk.php");

$dato_fra=$dato_til=NULL;
$email=NULL;
$forfaldsum=$fra=$fromdate=NULL;
$konto_id=$kontoantal=$kontoliste=NULL;
$send_mails=$send_pdfs=NULL;
$til=NULL;

if (isset($_POST['retur']) && $_POST['retur']) {
	print "<body onload=\"javascript:opener.focus();window.close();\">";
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=rapport.php?rapportart=openpost\">";
	exit;
} elseif (isset($_POST) && $_POST) {
 	$send_mails=if_isset($_POST['send_mails']);
 	$send_pdfs=if_isset($_POST['send_pdfs']);
 	$kontoantal=$_POST['kontoantal'];
	$dato_fra=$_POST['dato_fra'];
	$dato_til=$_POST['dato_til'];
#	$regnaar=$_POST['regnaar'];
	$konto_id=$_POST['konto_id'];
	$email=$_POST['email'];
	$fra=$_POST['fra'];
	$til=$_POST['til'];
}
else {
	$kontoliste=if_isset($_GET['kontoliste']);
	$kontoantal=if_isset($_GET['kontoantal']);
	$dato_fra=if_isset($_GET['dato_fra']);
	$dato_til=if_isset($_GET['dato_til']);
#	$dato_til=$_GET['dato_til'];
	for($x=1;$x<=$kontoantal;$x++) { #20150505
		($dato_fra)?$fra[$x]=dkdato(usdate($dato_fra)):$fra[$x]=NULL;
		($dato_til)?$til[$x]=dkdato(usdate($dato_til)):$til[$x]=NULL;
	}
}

if ($send_mails) {
#cho "($kontoantal, $konto_id, $email, $fra, $til)";
	send_htmlmails($kontoantal, $konto_id, $email, $fra, $til);
	print "<form name=luk action=../includes/luk.php method=post>";	
	print "<div style=\"text-align: center;\"><br><br><input type=submit value=\"Luk\" name=\"luk\">";
	print "</form></div>";
	exit;	
} elseif ($send_pdfs) {
	for ($x=1;$x<=count($konto_id);$x++) {
		$qtxt="select kontonr,art from adresser where id='$konto_id[$x]'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
#		echo "kontoprint($r[kontonr],$r[kontonr],$fra[$x],$til[$x],$r[art],$email[$x])<br>";
		$svar=kontoprint($r['kontonr'],$r['kontonr'],$fra[$x],$til[$x],$r['art'],$email[$x]);
	}
	print "<form name=luk action=../includes/luk.php method=post>";	
	print "<div style=\"text-align: center;\"><br><br><input type=submit value=\"Luk\" name=\"luk\">";
	print "</form></div>";
	exit;
}
#xit;
/*
$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
$r = db_fetch_array($query);
$startmaaned=$r['box1']*1;
$startaar=$r['box2']*1;
$slutmaaned=$r['box3']*1;
$slutaar=$r['box4']*1;
$slutdato=31;
*/
/*
if ($dato_fra) $slutmaaned=substr($dato_til,2,2);
if ($dato_til) $slutmaaned=substr($dato_til,2,2);

#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}
while (!checkdate($slutmaaned,$slutdato,$slutaar))	{
	$slutdato=$slutdato-1;
	if ($slutdato<28) break 1;
}

$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;
*/
print "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
/*
for ($x=1; $x<=12; $x++) {
	if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
	if ($maaned_til==$md[$x]){$maaned_til=$x;}
	if (strlen($maaned_fra)==1){$maaned_fra="0".$maaned_fra;}
	if (strlen($maaned_til)==1){$maaned_til="0".$maaned_til;}
}

$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
$r = db_fetch_array($query);
$startmaaned=$r['box1']*1;
$startaar=$r['box2']*1;
$slutmaaned=$r['box3']*1;
$slutaar=$r['box4']*1;
$slutdato=31;
*/
$currentdate=date("Y-m-d");
/*
if ($maaned_fra) {$startmaaned=$maaned_fra;}
if ($maaned_til) {$slutmaaned=$maaned_til;}

#	if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}
	
while (!checkdate($slutmaaned,$slutdato,$slutaar))	{
	$slutdato=$slutdato-1;
	if ($slutdato<28) break 1;
}

$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;
*/
print "<form name=kontoudtog action=mail_kontoudtog.php method=post>";

for($x=1; $x<=$kontoantal; $x++) {
	if (isset($kontoliste)) {
		(strpos($kontoliste,';'))?list($konto_id[$x], $kontoliste)=explode(";", $kontoliste, 2):$konto_id[$x]=$kontoliste;
	}
	if (!isset($email[$x])) $email[$x]=NULL;
	if (!isset($fromdate[$x])) $fromdate[$x]=NULL;
	
#	if (!$fra[$x]) $fra[$x]=$dato_fra;
	if ($fra[$x]) $fromdate[$x]=usdate($fra[$x]);
#	$fra[$x]=dkdato($fromdate[$x]);
#	else $fromdate[$x]= $dato_fra;
	if (!$til[$x]) $til[$x]=$dato_til; 
	$todate[$x]=usdate($til[$x]);
	$til[$x]=dkdato($todate[$x]);
#	else {$todate[$x]= $dato_fra;}
/*	
	if ( $regnaar ) {
		$fra[$x]=dkdato($fromdate[$x]);
	} else { 
		# startdato i foerste regnskabsaar
		$query = db_select("select * from grupper where art='RA' order by box2, box1",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array($query);
		$startmaaned=$r['box1']*1;
		$startaar=$r['box2']*1;
		$fra[$x]=dkdato($startaar. "-" . $startmaaned . "-01");
	}
*/
	$til[$x]=dkdato($todate[$x]);
	$query = db_select("select * from adresser where id='$konto_id[$x]'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($query);
	if (!$email[$x]) $email[$x]=$r['email'];
	$accountId[$x]=$r['id'];
	$r2=db_fetch_array(db_select("select box3 from grupper where art='DG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
	$kontovaluta[$x]=$r2['box3'];
	if (!$kontovaluta[$x]) $kontovaluta[$x]='DKK';
	print "<tr><td colspan=8><hr style=\"height: 10px; background-color: rgb(200, 200, 200);\"></td></tr>";
	print "<tr><td colspan=\"5\">$r[firmanavn]</td><td colspan=\"2\" align=\"right\">Dato</td><td align=right> ".date('d-m-Y')."</td></tr>\n";
	print "<tr><td colspan=\"5\">$r[addr1]</td><td colspan=\"2\" align=\"right\">Kontonr.</td><td align=right> $r[kontonr]</td></tr>\n";
	if ( $r['addr2'] ) print "<tr><td colspan=\"8\">$r[addr2]</td></tr>\n";
	print "<tr><td colspan=\"8\">$r[postnr] $r[bynavn]</td></tr>\n";
	print "<tr><td colspan=\"6\">Tlf. $r[tlf]</td><td align=right>Valuta</td><td align=right>$kontovaluta[$x]</td></tr>\n";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td width=10%> Dato</td><td width=5%> Bilag</td><td width=5%> Faktura</td><td width=40% align=center> Tekst</td><td> Forfaldsdato</td><td width=10% align=right> Debet</td><td width=10% align=right> Kredit</td><td width=10% align=right> Saldo</td></tr>";
	print "<tr><td colspan=8><hr></td></tr>";
	$betalingsbet=trim($r['betalingsbet']);
	$betalingsdage=$r['betalingsdage'];
	$kontosum=0;
	$primo=0;
	$primoprint=0;
	if (!$fromdate[$x]) { #20150305-1
		$y=0;
		$qtxt="select * from openpost where konto_id=$konto_id[$x] and transdate<='$todate[$x]' order by transdate, faktnr";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$td[$y]=$r['transdate'];
			($y)?$am[$y]=$am[$y-1]+afrund($r['amount'],2):$am[$y]=afrund($r['amount'],2);
			$y++;
		}
		for ($y=0;$y<count($td);$y++) {
			if (!$y) $fromdate[$x]=$td[$y];
			if ($am[$y]==0 && $y<count($td)-1) $fromdate[$x]=$td[$y+1]; 
			$fra[$x]=dkdato($fromdate[$x]);
		}
	}
	if (!$fromdate[$x]) {
		$fromdate[$x]=usdate($fra[$x]);
	}
	if (strpos($email[$x]," ")) {
		$email[$x]=str_replace(" ","",$email[$x]);
		$qtxt="update adresser set email='$email[$x]' where id='$accountId[$x]'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$query = db_select("select * from openpost where konto_id=$konto_id[$x] and transdate<='$todate[$x]' order by transdate, faktnr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($query)) {
		$amount=afrund($r['amount'],2);
		$forfaldsdag=NULL;
		$forfaldsdate=NULL;
		$valuta=$r['valuta'];
		if (!$valuta) $valuta='DKK';
		$valutakurs=$r['valutakurs'];
		if (!$valutakurs) $valutakurs=100;
		if ($valuta!=$kontovaluta[$x]) $DKKamount=$amount*$valutakurs/100;
		else $DKKamount=$amount;
		if ($r['forfaldsdate']) {
			$forfaldsdate=$r['forfaldsdate'];
			$forfaldsdag=dkdato($forfaldsdate);
		}
		if ($r['transdate']<$fromdate[$x]) {
			$primoprint=0;
			
			$kontosum=$kontosum+$DKKamount;
		}		 
		else { 
			if ($primoprint==0) {
				$tmp=dkdecimal($kontosum,2);
				$linjebg=$bgcolor5; $color='#000000';
				print "<tr bgcolor=\"$linjebg\"><td colspan=\"3\"></td><td> Primosaldo</td><td colspan=\"3\"></td><td align=right> $tmp</td></tr>";
				$primoprint=1;
			}
		    	if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
			print "<tr bgcolor=\"$linjebg\"><td align=left> ".dkdato($r['transdate'])."&nbsp;</td><td> $r[refnr]</td>";
			print "<td align=right> $r[faktnr]&nbsp;</td><td> $r[beskrivelse]";
			if ($kontovaluta[$x] != $valuta) print " ($valuta $amount)";
			print "</td>";
			if ($DKKamount < 0) $tmp=0-$DKKamount;
			else $tmp=$DKKamount;
			$tmp=dkdecimal($tmp,2);
			if (!$forfaldsdag && $DKKamount > 0) {
				$forfaldsdag=forfaldsdag($r['transdate'], $betalingsbet, $betalingsdage);
				$forfaldsdate=usdate($forfaldsdag);
			}
			if (($r['udlignet']!='1')&&($forfaldsdate<$currentdate)) $stil="<span style='color: rgb(255, 0, 0);'>";
			else $stil="<span style='color: rgb(0, 0, 0);'>";
			if ($DKKamount > 0) {
				print "<td>$stil"."$forfaldsdag</td><td align=right>$stil $tmp</td><td></td>";
				$forfaldsum+=$DKKamount;
			}
			else {print "<td></td><td></td><td align=right> $tmp</td>";}
			
			$kontosum=$kontosum+$DKKamount;
			$tmp=dkdecimal($kontosum,2);
			print "<td align=right> $tmp</td>";
			print "</tr>";
		}
	}
	if ($primoprint==0) {
		$tmp=dkdecimal($kontosum,2);
		print "<tr><td></td><td></td><td></td><td> Primosaldo</td><td></td><td></td><td></td><td align=right> $tmp</td></tr>";
	}
	print "<tr><td colspan=8><hr></td></tr>";
 	print "<tr><td colspan=8> email til: <input type=text name=email[$x] value=$email[$x]> Periode: <input type=text style=\"text-align:right\" size=10 name=fra[$x] value=$fra[$x]> - <input type=text style=\"text-align:right\" size=10 name=til[$x] value=$til[$x]></td></tr>";
	print "<tr><td colspan=8><hr style=\"height: 10px; background-color: rgb(200, 200, 200);\"></td></tr>";
	print "<tr><td colspan=8><hr></td></tr>";
	print "<input type = hidden name=konto_id[$x] value=$konto_id[$x]>";
}
print "<input type = hidden name=kontoantal value=$kontoantal>";
print "<input type = hidden name=dato_fra value=$dato_fra>";
print "<input type = hidden name=dato_til value=$dato_til>";
#print "<input type = hidden name=regnaar value=$regnaar>";
print "<tr><td colspan=10 align=center>";
$spantxt="Klik for at se ændringer i perioder eller mailadresser"; 
print "<span title='$spantxt'><input type=\"submit\" style=\"width:110px;\" value=\"Opdat&eacute;r\" name=\"update\">&nbsp;</span>";
$spantxt="Send som html. Emne og mailtekst kan ikke ændres."; 
print "<span title='$spantxt'><input type=\"submit\" style=\"width:110px;\" value=\"Send mail(s)\" name=\"send_mails\">&nbsp;</span>";
$qtxt="select * from formularer where formular='11' and art='5' and lower(sprog)='dansk'";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
$mailtext=$subjekt=NULL;
while ($r = db_fetch_array($q)) {
	if (!$subjekt && $r['xa']=='1') $subjekt=$r['beskrivelse'];
	elseif (!$mailtext && $r['xa']=='2') $mailtext=$r['beskrivelse'];
}
if ($mailtext && $subjekt) {
	$disabled=NULL;
	$spantxt="Send som vedhæftet PDF. Emne og mailtekst jf. formularen \"Kontoudtog\""; 
} else {
	$disabled='disabled';
	$spantxt="Emne og mailtekst ikke udfyldt for formularen \"Kontoudtog\""; 
}
print "<span title='$spantxt'><input type=\"submit\" style=\"width:110px;\" value=\"Send som PDF\" name=\"send_pdfs\" $disabled>&nbsp;</span>";
$spantxt="Klik for at lukke"; 
print "<span title='$spantxt'><input type=\"submit\" style=\"width:110px;\" value=\"Retur\" name=\"retur\"></td></span>";
print "</form>\n";

function send_htmlmails($kontoantal, $konto_id, $email, $fra, $til) {
	 
	global $bgcolor,$bgcolor5,$brugernavn;
	global $charset,$currentdate;
	global $db;
	global $forfaldsum;
	global $subjekt;
	
	ini_set("include_path", "../phpmailer");
	require("class.phpmailer.php");
	
	$tmpmappe="../temp/$db/".str_replace(" ","_",$brugernavn);
	if (!file_exists($tmpmappe)) mkdir($tmpmappe);
	$r = db_fetch_array(db_select("select * from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
	$afsendermail=$r['email'];
	$afsendernavn=$r['firmanavn'];
	$from=$afsendermail;
	($r['felt_1'])?$smtp=$r['felt_1']:$smtp='localhost';
	($r['felt_2'])?$smtp_user=$r['felt_2']:$smtp_user=NULL;
	($r['felt_3'])?$smtp_pwd=$r['felt_3']:$smtp_pwd=NULL;
	($r['felt_4'])?$smtp_enc=$r['felt_4']:$smtp_enc=NULL;

	for($x=1; $x<=$kontoantal; $x++) {
		if (!file_exists("$tmpmappe/$x")) mkdir("$tmpmappe/$x");
		if (($konto_id[$x])&&($email[$x])&&($fra[$x])&&($til[$x])&&(strpos($email[$x], '@'))) {	
			$fromdate[$x]= usdate($fra[$x]);
			$todate[$x]=usdate($til[$x]);
			$mailtext = "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTMP 4.01 Transitional//EN\">\n";
			$mailtext .= "<html><head><meta content=\"text/html; charset=ISO-8859-15\" http-equiv=\"content-type\">\n";
			$mailtext .= "<title>Kontoudtog fra $afsendernavn</title></head>\n";
		 	$mailtext .= "<body bgcolor=$bgcolor link='#000000' vlink='#000000' alink='#000000' center=''>\n";
			$mailtext .= "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>\n";
		 	$mailtext .= "<tr><td colspan=\"5\"><b>$afsendernavn</b></td><td colspan=\"2\" align=\"right\">Dato</td><td align=right> ".date('d-m-Y')."</td></tr>\n";
			$mailtext .= "<tr><td colspan=8><hr></td></tr>\n";
			$mailtext .= "<tr><td colspan=\"8\" style=\"font-size:115%;font-weight:bold\">Kontoudtog</td></tr>\n";
			$r = db_fetch_array(db_select("select * from adresser where id=$konto_id[$x]",__FILE__ . " linje " . __LINE__));
			$r2 = db_fetch_array(db_select("select box3 from grupper where art='DG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
			$kontovaluta[$x]=$r2['box3'];
			if (!$kontovaluta[$x]) $kontovaluta[$x]='DKK';
			$mailtext .= "<tr><td colspan=\"5\">$r[firmanavn]</td>";
			$mailtext .= "<td colspan=\"2\" align=\"right\">Kontonr.</td><td align=right> $r[kontonr]</td></tr>\n";
			$mailtext .= "<tr><td colspan=\"5\">$r[addr1]</td>";
			$mailtext .= "<td colspan=\"2\" align=\"right\">Valuta</td><td align=right> $kontovaluta[$x]</td></tr>\n";
			if ( $r['addr2'] ) $mailtext .= "<tr><td colspan=\"8\">$r[addr2]</td></tr>\n";
			$mailtext .= "<tr><td colspan=\"8\">$r[postnr] $r[bynavn]</td></tr>\n";
			$mailtext .= "<tr><td><br></td></tr>\n";
			$mailtext .= "<tr><td width=\"10%\"> Dato</td><td width=\"5%\"> Bilag</td><td width=\"5%\"> Faktura</td><td width=\"40%\" align=\"center\"> Tekst</td><td> Forfaldsdato</td><td width=\"10%\" align=\"right\"> Debet</td><td width=\"10%\" align=\"right\"> Kredit</td><td width=\"10%\" align=\"right\"> Saldo</td></tr>\n";
			$mailtext .= "<tr><td colspan=8><hr></td></tr>\n";
			$betalingsbet=trim($r['betalingsbet']);
			$betalingsdage=$r['betalingsdage'];
			$kontosum=0;
			$primo=0;
			$primoprint=0;
			$query = db_select("select * from openpost where konto_id=$konto_id[$x] and transdate<='$todate[$x]' order by transdate, faktnr",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($query)) {
				$amount=afrund($r['amount'],2);
				$valuta=$r['valuta'];
				if (!$valuta) $valuta='DKK';
				$valutakurs=$r['valutakurs'];
				if (!$valutakurs) $valutakurs=100;
				if ($valuta!=$kontovaluta[$x]) $DKKamount=$amount*$valutakurs/100;
				else $DKKamount=$amount;
				
				$forfaldsdag=NULL;
				if ($r['forfaldsdate']) $forfaldsdag=dkdato($r['forfaldsdate']);
				if ($r['transdate']<$fromdate[$x]) {
					$primoprint=0;
					$kontosum=$kontosum+$DKKamount;
				}		 
				else { 
					if ($primoprint==0) {
						$tmp=dkdecimal($kontosum,2);
						$linjebg=$bgcolor5; $color='#000000';
						$mailtext .= "<tr bgcolor=\"$linjebg\"><td colspan=\"3\"></td><td>Primosaldo</td><td colspan=\"3\"></td><td align=right> $tmp</td></tr>\n";
						$primoprint=1;
					}
				    	if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
					elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
					$mailtext .= "<tr bgcolor=\"$linjebg\"><td> ".dkdato($r['transdate'])."&nbsp;</td><td> $r[refnr]&nbsp;</td>";
					$mailtext .= "<td> $r[faktnr]&nbsp;</td><td> $r[beskrivelse]";
					if ($kontovaluta[$x] != $valuta) $mailtext .= " ($valuta $amount)";
					$mailtext .= "</td>\n";
					if ($DKKamount < 0) $tmp=0-$DKKamount;
					else $tmp=$DKKamount;
					$tmp=dkdecimal($tmp,2);
					if (!$forfaldsdag) $forfaldsdag=forfaldsdag($r['transdate'], $betalingsbet, $betalingsdage);
					if ($r['udlignet'] != '1' && $forfaldsdag<$currentdate) $stil="<span style='color: rgb(255, 0, 0);'>";
					else {$stil="<span style='color: rgb(0, 0, 0);'>";}
					if ($DKKamount > 0) {
						$mailtext .= "<td>$stil$forfaldsdag</td><td align=right>$stil $tmp</td><td></td>\n";
						$forfaldsum=$forfaldsum+$DKKamount;
					}
					else $mailtext .= "<td></td><td></td><td align=right>$stil$tmp</td>\n";
			
					$kontosum=$kontosum+$DKKamount;
					$tmp=dkdecimal($kontosum,2);
					$mailtext .= "<td align=right> $tmp</td>\n";
					$mailtext .= "</tr>\n";
				}
			}
			if ($primoprint==0) {
				$tmp=dkdecimal($kontosum,2);
				$mailtext .= "<tr><td></td><td></td><td></td><td> Primosaldo</td><td></td><td></td><td></td><td align=right> $tmp</td></tr>\n";
			}
			$mailtext .= "<tr><td colspan=\"8\"><hr></td></tr>\n";
			$r = db_fetch_array(db_select("select * from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
			$mailtext .= "<tr><td colspan=\"8\">\n";
			if ( $r['bank_konto'] ) { 
				$mailtext .= "<p>Et eventuelt udest&aring;ende bedes indbetalt hurtigst muligt p&aring; vores bankkonto med\n";
				if ( $r['bank_reg'] ) $mailtext .= " reg.nr. ".$r['bank_reg']." og";
				$mailtext .= " kontonr. ".$r['bank_konto'];
				if ( $r['bank_navn'] )  $mailtext .= " i ".$r['bank_navn'];
				$mailtext .= ".</p>\n";
			}
			if ( $r['tlf'] ) {
				$mailtext .= "<p>Hvis du har sp&oslash;rgsm&aring;l, s&aring; kontakt os p&aring; telefon ".$r['tlf'];
				$mailtext .= ".</p>\n</td></tr>\n";
			}
			$mailtext .= "<tr><td colspan=\"8\"><hr></td></tr>\n";
			$mailtext .= "<tr><td colspan=\"8\" align=\"center\">\n";
			$mailtext .= "<p style=\"font-size:80%\">".$r['firmanavn'];
			if ( $r['addr1'] ) $mailtext .= " * ".$r['addr1'];
			if ( $r['addr2'] ) $mailtext .= " * ".$r['addr2'];
			if ( $r['postnr'] ) $mailtext .= " * ".$r['postnr']." ".$r['bynavn'];
			if ( $r['tlf'] ) $mailtext .= " * tlf ".$r['tlf'];
			if ( $r['fax'] ) $mailtext .= " * fax ".$r['fax'];
			if ( $r['cvrnr'] ) $mailtext .= " * cvr ".$r['cvrnr'];
			$mailtext .= "<p>\n</td></tr>\n";
			$mailtext .= "</table></body></html>\n";			
			
			$r = db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
			$afsendermail=$r['email'];
			$afsendernavn=$r['firmanavn'];

			if ($charset=="UTF-8") {
				$subjekt=utf8_decode($subjekt);
				$mailtext=utf8_decode($mailtext);
				$afsendernavn=utf8_decode($afsendernavn);
				$afsendermail=utf8_decode($afsendermail);
			}
			$from = $afsendermail;
			$fp=fopen("$tmpmappe/$x/kontoudtog.html","w");
			fwrite($fp,$mailtext);
			fclose ($fp);

			$mail = new PHPMailer();
			$mail->IsSMTP();                                   // send via SMTP
			$mail->SMTPDebug  = 2;
			$mail->Host  = $smtp; // SMTP servers 
			if ($smtp!='localhost') {
				if ($smtp_user) {
					$mail->SMTPAuth = true;     // turn on SMTP authentication
					$mail->Username = $smtp_user;  // SMTP username
					$mail->Password = $smtp_pwd; // SMTP password
					if ($smtp_enc) $mail->SMTPSecure = $smtp_enc; // SMTP kryptering
				}
			} else {
				$mail->SMTPAuth = false;
				if (strpos($_SERVER['SERVER_NAME'],'saldi.dk')) { #20121016
					if ($_SERVER['SERVER_NAME']=='ssl.saldi.dk') $from = $db.'@ssl.saldi.dk'; #20130731
					elseif ($_SERVER['SERVER_NAME']=='ssl2.saldi.dk') $from = $db.'@ssl2.saldi.dk'; #20130731
					else $from = 'kanikkebesvares@saldi.dk';
				}  
			}
			$mail->From = $from;
			$mail->FromName = $afsendernavn;
			$splitter=NULL;
			if (strpos($email[$x],";")) $splitter=';';
			elseif (strpos($email[$x],",")) $splitter=',';
			if ($splitter) { #20150305-2 + 20161114
				$tmp=array();
				$tmp=explode($splitter,$email[$x]);
				for ($i=0;$i<count($tmp);$i++) {
					if (strpos($tmp[$i],"@")) $mail->AddAddress($tmp[$i]); 
				}
			} else $mail->AddAddress($email[$x]);
			$mail->AddBCC($afsendermail); 
			$mail->AddReplyTo($afsendermail,$afsendernavn);

			$mail->WordWrap = 50;                              // set word wrap
			$mail->AddAttachment("$tmpmappe/$x/kontoudtog.html");      // attachment
			$mail->IsHTML(true);                               // send as HTML

			$mail->Subject  =  "Kontoudtog fra $afsendernavn";
			
			$mailbody = "<html><body>\n";
                        $mailbody .= "<p>Hermed fremsendes kontoudtog fra ".$afsendernavn.".</p>\n";
                        $mailbody .= "<p>Den vedlagte fil er en HTML-fil og kan ses i din webbrowser eksempelvis \n";
			$mailbody .= "ved at dobbeltklikke p&aring; den.</p>\n";
			$mailbody .= "<hr />\n<p>";
			$mailbody .= $r['firmanavn']."<br />\n";
			if ( $r['addr1'] ) $mailbody .= $r['addr1']."<br />\n";
			if ( $r['addr2'] ) $mailbody .= $r['addr2']."<br />\n";
			if ( $r['postnr'] ) $mailbody .= $r['postnr']." ".$r['bynavn']."<br />\n";
			if ( $r['tlf'] ) $mailbody .= "tlf ".$r['tlf'];
			if ( $r['fax'] ) $mailbody .= " * fax ".$r['fax'];
			if ( $r['cvrnr'] ) $mailbody .= " * cvr ".$r['cvrnr'];
			$mailbody .= "</p></body></html>";

			$mailaltbody = "Hermed fremsendes kontoudtog fra ".$afsendernavn.".\n\n";
                        $mailaltbody .= "Den vedlagte fil er en HTML-fil og kan ses i din webbrowser eksempelvis \n";
			$mailaltbody .= "ved at dobbeltklikke på den.\n";
			$mailaltbody .= "-- \n";
			$mailaltbody .= $r['firmanavn']."\n";
			if ( $r['addr1'] ) $mailaltbody .= $r['addr1']."\n";
			if ( $r['addr2'] ) $mailaltbody .= $r['addr2']."\n";
			if ( $r['postnr'] ) $mailaltbody .= $r['postnr']." ".$r['bynavn']."\n";
			if ( $r['tlf'] ) $mailaltbody .= "tlf ".$r['tlf'];
			if ( $r['fax'] ) $mailaltbody .= " * fax ".$r['fax'];
			if ( $r['cvrnr'] ) $mailaltbody .= " * cvr ".$r['cvrnr'];
			
			if ($charset=="UTF-8"){
				$mailbody=utf8_decode($mailbody);
				$mailaltbody=utf8_decode($mailaltbody);
			}


			$mail->Body     =  $mailbody;
			$mail->AltBody  =  $mailaltbody;
			echo "<!--";
			if(!$mail->Send()){
				echo "-->";
 				 echo "Fejl i afsendelse til $email[$x]<p>";
   				echo "Mailer Error: " . $mail->ErrorInfo;
  		 		exit;
			} 
			echo "-->";
			echo "Kontoudtog sendt til $email[$x]<br>";
#			sleep(2);
		}	
	}
	for($x=1; $x<=$kontoantal; $x++) {
		unlink("$tmpmappe/$x/kontoudtog.html");
		rmdir("$tmpmappe/$x");
	}
	#	unlink("$tmpmappe/kontoudtog.html");
	rmdir($tmpmappe);
}
?>

