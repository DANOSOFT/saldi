<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------------debitor/inkassoprint-----lap 3.7.0---2017.03.24-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2017 saldi.dk aps
// ----------------------------------------------------------------------


@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/formfunk.php");

$inkasso=if_isset($_GET['inkasso']);
$rykker_id=if_isset($_GET['rykker_id']);
$bg="nix";
$fakturasum=0;

$qtxt="select * from ordrer where id='$rykker_id'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$txt="<b><big>Inkasso</big></b><br><br>\n";
$txt.="<b>Rykker id:</b><span style=\"position:absolute;left:100px;\">$r[ordrenr]</span><br>\n";
$txt.="<hr>";
$txt.="<b><big>Klient</big></b><br><br>\n";
$qtxt="select * from adresser where art='S'";
$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$eget_firma=$r2['firmanavn'];
$egen_email=$r2['email'];
$txt.="CVR<span style=\"position:absolute;left:100px;\">$r2[cvrnr]</span><br>\n";
$txt.="Firma<span style=\"position:absolute;left:100px;\">$r2[firmanavn]</span><br>\n";
$txt.="Adresse<span style=\"position:absolute;left:100px;\">$r2[addr1]</span><br>\n";
if ($r2['addr2']) $txt.="<span style=\"position:absolute;left:100px;\">$r2[addr2]</span><br>\n";
$txt.="<span style=\"position:absolute;left:100px;\">$r2[postnr] $r2[bynavn]</span><br>\n";
$txt.="email<span style=\"position:absolute;left:100px;\">$r2[email]</span><br>\n";

$txt.="Tlf.<span style=\"position:absolute;left:100px;\">$r2[tlf]</span><br>\n";
$txt.="Reg.<span style=\"position:absolute;left:100px;\">$r2[bank_reg]</span><br>\n";
$txt.="Kto.<span style=\"position:absolute;left:100px;\">$r2[bank_konto]</span><br>\n";

$qtxt="select ansat_id from brugere where brugernavn='$brugernavn'";
if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
	$qtxt="select navn from ansatte where id='$r2[ansat_id]'";
	$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$txt.="Kontakt<span style=\"position:absolute;left:100px;\">$r2[navn]</span><br>\n";
} else $txt.="Kontakt<span style=\"position:absolute;left:100px;\">$brugeranvn</span><br>\n";
$txt.="<hr>";
$txt.="<b><big>Debitor</big></b><br><br>\n";
$qtxt="select * from ordrer where id='$rykker_id'";
$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$qtxt="select * from adresser where id='$r[konto_id]'";
$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$txt.="CVR<span style=\"position:absolute;left:100px;\">";
($r2['cvrnr'])?$txt.=$r2['cvrnr']:$txt.=$r2['cvrnr'];
$txt.="</span><br>\n";
$txt.="Firma<span style=\"position:absolute;left:100px;\">$r2[firmanavn]</span><br>\n";
$txt.="Adresse<span style=\"position:absolute;left:100px;\">$r2[addr1]</span><br>\n";
if ($r2['addr2']) $txt.="<span style=\"position:absolute;left:100px;\">$r2[addr2]</span><br>\n";
$txt.="<span style=\"position:absolute;left:100px;\">$r2[postnr] $r2[bynavn]</span><br>\n";
$txt.="email<span style=\"position:absolute;left:100px;\">$r2[email]</span><br>\n";
$txt.="Tlf.<span style=\"position:absolute;left:100px;\">$r2[tlf]</span><br>\n";
$txt.="Kontakt<span style=\"position:absolute;left:100px;\">$r2[kontakt]</span><br>\n";
$txt.="<hr>";
$txt.="<b><big>Faktura</big></b><br><br>\n";
$qtxt="select * from ordrelinjer where ordre_id = '$rykker_id' and enhed != '' and beskrivelse like 'Faktura%' order by serienr, posnr";
$q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
$x=0;
while ($r2 = db_fetch_array($q2)) {
	if (($r2['enhed'])&&(is_numeric($r2['enhed']))) {
		$r3 = db_fetch_array(db_select("select * from openpost where id = '$r2[enhed]'",__FILE__ . " linje " . __LINE__));
		$txt.="Faktura nr.<span style=\"position:absolute;left:150px;\">$r3[faktnr]</span><br>\n";
		$txt.="Bel&oslash;b<span style=\"position:absolute;left:150px;\";>".dkdecimal($r3['amount'])."</span><br>\n";
		if ($r['udlignet']) {
			$txt.="Betalt<span style=\"position:absolute;left:150px;text-align:right;\">".dkdecimal($r3['amount'])."</span><br>\n";
		} else {
			$txt.="Betalt<span style=\"position:absolute;left:150px;\";>0,00</span><br>\n";
		}
		if ($r2['serienr']) {
			$txt.="Fakt. dato<span style=\"position:absolute;left:150px;\">".dkdato($r2['serienr'])."</span><br>\n";
		}
		if ($r3['forfaldsdate']) {
			$txt.="Forfaldsdato<span style=\"position:absolute;left:150px;\">".dkdato($r3['forfaldsdate'])."</span><br>\n";
		}
		$ffdage=floor((date('U')-strtotime($r3['forfaldsdate']))/(3600*24));
		$txt.="Forfaldsdage<span style=\"position:absolute;left:150px;\">$ffdage</span><br>\n";
		$r3 = db_fetch_array(db_select("select id from ordrer where fakturanr = '$r3[faktnr]'",__FILE__ . " linje " . __LINE__));
		$faktfil[$x]=formularprint($r3['id'],'4','0',$charset,'inkasso');
/*
#		print "<!-- kommentar for at skjule uddata til siden \n";
		system ("$exec_path/ps2pdf $faktfil[$x] $faktfil[$x].pdf");
if (file_exists("$faktfil[$x]")) echo __line__." $faktfil[$x] eksisterer<br>\n";
else echo __line__." $faktfil[$x] mangler<br>\n";
if (file_exists("$faktfil[$x].pdf")) echo __line__." $faktfil[$x].pdf eksisterer<br>\n";
else echo __line__." $faktfil[$x].pdf mangler<br>\n";
		if ($logoart=='PDF') {
			$out="../temp/$db/".$faktfil[$x]."x.pdf";
			system ("$exec_path/pdftk $faktfil[$x].pdf background ../logolib/$db_id/bg.pdf output $out");
#			unlink ("$mappe/$faktfil[$x].pdf");
			system  ("mv $out $mappe/$faktfil[$x].pdf");
if (file_exists("$faktfil[$x].x")) echo __line__." $faktfil[$x].x eksisterer<br>\n";
else echo __line__." $faktfil[$x].x mangler<br>\n";
if (file_exists("$faktfil[$x].pdf")) echo __line__." $faktfil[$x].pdf eksisterer<br>\n";
else echo __line__." $faktfil[$x].pdf mangler<br>\n";
		} else {
#			unlink ("$mappe/$faktfil[$x].pdf");
if (file_exists("$faktfil[$x].pdf")) echo __line__." $faktfil[$x].pdf eksisterer<br>\n";
else echo __line__." $faktfil[$x].pdf mangler<br>\n";
			system ("mv $faktfil[$x].pdf $mappe/$faktfil[$x].pdf");
if (file_exists("$mappe/$faktfil[$x].pdf")) echo __line__." $mappe/$db/$faktfil[$x].pdf eksisterer<br>\n";
else echo __line__." $mappe/$db/$faktfil[$x].pdf mangler<br>\n";
		}
#		print "--> \n";

echo $faktfil[$x];
*/
#xit;
		$x++;
	}
}
#xit;
$txt.="<hr>";
$txt.="<b><big>&Aring;bne poster</big></b><br><br>\n";
$qtxt="select * from openpost where konto_id = '$r[konto_id]' and udlignet = '0' order by transdate";
$q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r2 = db_fetch_array($q2)) {
	$txt.="$r2[transdate]  $r2[beskrivelse]<span style=\"position:absolute;left:350px;\">".dkdecimal($r2['amount'])."</span><br>\n";
}
list($f1,$f2)=explode('-',$r['fakturanr']);
$f1.="-%";
$qtxt="select ordrer.id,ordrer.fakturadate,sum(ordrelinjer.pris) as sum from ordrer,ordrelinjer where ordrer.fakturanr like '$f1' and ordrer.konto_id = '$r[konto_id]' and ordrelinjer.ordre_id=ordrer.id group by ordrer.id,ordrer.fakturadate order by ordrer.id";
$x=0;
$q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r2 = db_fetch_array($q2)) {
	$rykkersum=$r2['sum'];
	$qtxt="select enhed from ordrelinjer where ordre_id = '$r2[id]' and enhed != '' order by id";
#cho $qtxt."<br>\n";		
	$q3=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r3 = db_fetch_array($q3)) {
		$qtxt="select amount as sum from openpost where id = '$r3[enhed]'";
#cho $qtxt."<br>\n";
		$r4 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
#cho "Sum $r4[sum]<br>\n";
		$rykkersum+=$r4['sum'];
#cho __line__." $rykkersum Sum $r4[sum]<br>\n";
	}	
	$txt.="<hr>";
	$ry_nr=$x+1;
	$txt.="<b><big>Rykker $ry_nr</big></b><br><br>\n";
#cho $txt;	
	$formular=$x+5;
	$txt.="Bel&oslash;b<span style=\"position:absolute;left:150px;\">".dkdecimal($rykkersum)."</span><br>\n";
	$txt.="Dato udstedt<span style=\"position:absolute;left:150px;\">".dkdato($r2['fakturadate'])."</span><br>\n";
/*
	$qtxt="select beskrivelse from formularer where formular = '$formular' and art = '2' and sprog = 'Dansk' order by ya desc";
#cho "$qtxt<br>\n";
	$q3 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r3 = db_fetch_array($q3)) {
		if (strlen($r3['beskrivelse'])>20) {
			$txt.="tekst<span style=\"position:absolute;left:100px;\">$r3[beskrivelse]</span><br>\n";
		}
	}
*/
	$ry_id=array($r2['id']);
	$rykkerfil[$x]=rykkerprint('',$ry_id,$ry_nr,'','','',$inkasso);


	$x++;
}

##cho __line__." $txt<br>\n";
#xit;
#$qtxt="select box9 from grupper where art = 'DIV' and kodenr = '4'";
##cho "$qtxt<br>\n";
#$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
#$inkasso_id=$r['box_9'];
$qtxt="select email from adresser where id = '$inkasso'";
#cho "$qtxt<br>\n";
$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$email=$r['email'];
#$mailsprog=strtolower($r['sprog']);
#fwrite($fp,$initext);
#$formularsprog=strtolower($r['sprog']);
#cho __line__."<br>\n";
if(!class_exists('phpmailer')) {
	ini_set("include_path","../phpmailer");
	require("class.phpmailer.php");
}
if (!isset($exec_path)) $exec_path="/usr/bin";
#print "<meta http-equiv=\"refresh\" content=\"0;URL=rapport.php?rapportart=openpost\">";
#exit;
#cho __line__."<br>\n";

#cho __line__."<br>\n";
	
$tmpmappe="../temp/$db/".str_replace(" ","_",$brugernavn);
mkdir($tmpmappe);
mkdir("$tmpmappe/inkasso.txt");
#cho __line__."<br>\n";
if ($email && strpos($email, '@')) {	
#cho __line__."<br>\n";
	$mailtext = "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTMP 4.01 Transitional//EN\">\n";
	$mailtext .= "<html><head><meta content=\"text/html; charset=ISO-8859-15\" http-equiv=\"content-type\">\n";
	$row = db_fetch_array(db_select("select firmanavn from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
	$mailtext .= "<title>Ny sag fra $eget_firma</title></head>\n";
	$mailtext .= "<body bgcolor=$bgcolor link='#000000' vlink='#000000' alink='#000000' center=''>\n";
#	$mailtext .= $txt;
	
	
	$r = db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
			$afsendermail=$r['email'];
			$afsendernavn=$r['firmanavn'];

#cho "AFSM $afsendermail<br>\n";
			
			if ($charset=="UTF-8") {
				$subjekt=utf8_decode("Ny sag fra $eget_firma");
				$mailtext=utf8_decode($mailtext);
				$afsendernavn=utf8_decode($afsendernavn);
				$afsendermail=utf8_decode($afsendermail);
##cho "MM $mailtext<br>\n";
			}

			$fp=fopen("$tmpmappe/inkasso.html","w");
			fwrite($fp,$mailtext);
			fclose ($fp);

			$mail = new PHPMailer();
#cho __line__."<br>\n";
			$mail->IsSMTP();                                   // send via SMTP
			$mail->Host  = "localhost"; // SMTP servers
			$mail->SMTPAuth = false;     // turn on SMTP authentication

			if (strpos($_SERVER['SERVER_NAME'],'saldi.dk')) { #20121029
				if ($_SERVER['SERVER_NAME']=='ssl.saldi.dk') $mail->From = $db.'@ssl.saldi.dk'; #20140128
				elseif ($_SERVER['SERVER_NAME']=='ssl2.saldi.dk') $mail->From = $db.'@ssl2.saldi.dk'; #20140128
				elseif ($_SERVER['SERVER_NAME']=='ssl3.saldi.dk') $mail->From = $db.'@ssl3.saldi.dk'; #20140128
				else $mail->From = 'kanikkebesvares@saldi.dk'; #20140128
				$mail->FromName = $afsendernavn;
			} else {
				$mail->From = $afsendermail;
				$mail->FromName = $afsendernavn;
			}
#cho __line__."<br>\n";
			$mail->AddAddress($email); 
			$mail->AddBCC($afsendermail); 
			$mail->AddReplyTo($afsendermail,$afsendernavn);
#cho __line__."<br>\n";

			$mail->WordWrap = 50;                              // set word wrap
#cho __line__."<br>\n";
			$mail->AddAttachment("$tmpmappe/inkasso.html");      // attachment
#cho __line__."<br>\n";
			for ($x=0;$x<count($faktfil);$x++) {
				#cho "$faktfil[$x]<br>\n";
				$mail->AddAttachment("$faktfil[$x]");      // attachment
			}
#cho __line__."<br>\n";
			for ($x=0;$x<count($rykkerfil);$x++) {
				#cho __line__." $rykkerfil[$x]<br>\n";
				$mail->AddAttachment("$rykkerfil[$x]");      // attachment
			}
			$mail->IsHTML(true);                               // send as HTML

			$mail->Subject  =  "ny inkasso sag fra $afsendernavn";
			
#cho __line__."<br>\n";
			$mailbody = $txt;
#cho __line__."<br>\n";

			$mailaltbody = "Hermed fremsendes inkassosag fra ".$afsendernavn.".\n\n";
                        $mailaltbody .= "Den vedlagte fil er en HTML-fil og kan ses i din webbrowser eksempelvis \n";
			$mailaltbody .= "ved at dobbeltklikke på den.\n";
			if ($charset=="UTF-8"){
				$mailbody=utf8_decode($mailbody);
				$mailaltbody=utf8_decode($mailaltbody);
			}


			$mail->Body     =  $mailbody;
			$mail->AltBody  =  $mailaltbody;

##cho "ZZZ";
#xit;
			
			if(!$mail->Send()){
 				 echo "Fejl i afsendelse til $email<p>";
   				echo "Mailer Error: " . $mail->ErrorInfo;
  		 		exit;
			}
			echo "Sag sendt til $email<br>\n";
#			sleep(2);
		}	
		unlink("$tmpmappe/inkasso.html");
		rmdir($tmpmappe);
		print "<br><a href = rykker.php?rykker_id=$rykker_id>Tilbage</a><br>\n";
?>




