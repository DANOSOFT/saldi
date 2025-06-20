<?php
// -----------debitor/rykkertjek.php---------lap 3.2.5.------2011-12-02------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2011 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$modulnr=5;
$title="Rykker";
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/forfaldsdag.php");

ini_set("include_path", "../phpmailer");
require("class.phpmailer.php");

$dd=date("Y-m-d");
#echo "select * from grupper where art='DIV' and kodenr= '4'<br>";
$r = db_fetch_array(db_select("select * from grupper where art='DIV' and kodenr= '4'",__FILE__ . " linje " . __LINE__));
$mailmodt_id=$r['box1'];
$email=$r['box2'];
$ffdage=$r['box5'];
$chkdate=$r['box8'];

if (!$ffdage || $chkdate==$dd) echo '';
else {
	$rykkerdate=usdate(forfaldsdag($dd,'netto',$ffdage));
	$x=0;
	$konto_id=array();
	$x=0;
#	$q=db_select("select openpost.* from openpost,adresser where openpost.udlignet = '0' and openpost.forfaldsdate >= '$rykkerdate' and openpost.amount>'0' and adresser.id=openpost.konto_id and adresser.art = 'D' order by openpost.konto_id",__FILE__ . " linje " . __LINE__);
	$q=db_select("select openpost.* from openpost,adresser where openpost.udlignet = '0' and openpost.amount>'0' and adresser.id=openpost.konto_id and adresser.art = 'D' order by openpost.konto_id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
# echo "$r[forfaldsdate] <= $dd<br>";
	$rykkerdate=usdate(forfaldsdag($r['forfaldsdate'],'netto',$ffdage));
#	echo "$rykkerdate <= $dd<br>";
	if ($rykkerdate <= $dd) {
		if (!db_fetch_array(db_select("select id from ordrelinjer where enhed = '$r[id]'",__FILE__ . " linje " . __LINE__))) { #Tjekker om der allerede eksisterer en rykker på ordren.
			if (!in_array($r['konto_id'],$konto_id)) {
				$konto_id[$x]=$r['konto_id']; #Liste over konto id numre der skal rykkes
				$x++;
			} }
		}
	}
	$ff_antal=$x;
#echo "$ff_antal $rykkerdate <br>";
#exit;
#echo "$ff_antal && $email && $bruger_id != $mailmodt_id<br>";
	if ($ff_antal && $email && $bruger_id != $mailmodt_id) {
		$subjekt=findtekst(238,$sprog_id);
		$mailtext=findtekst(239,$sprog_id);
#echo "send_mail($email,$subjekt,$mailtext)<br>";
		send_mail($email,$subjekt,$mailtext);
		db_modify("update grupper set box8='$dd' where art='DIV' and kodenr= '4'");
	}	elseif ($ff_antal && $bruger_id == $mailmodt_id) {
#echo "$ff_antal && $bruger_id == $mailmodt_id<br>";
		$tmp=findtekst(240,$sprog_id);
#echo "$tmp<br>";
		print "<BODY onLoad=\"javascript:alert('$tmp')\">";
	}
# exit;
}
#exit;
function send_mail($email,$subjekt,$mailtext) {
	
	$r = db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
	$afsendermail=$r['email'];
	$afsendernavn=$r['firmanavn'];
	
	
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
#	$mail->AddAttachment("$filnavn");      // attachment
#	$mail->AddAttachment("/tmp/image.jpg", "new.jpg"); 
	$mail->IsHTML(true);                               // send as HTML

	$mail->Subject  =  "$subjekt";
	$mail->Body     =  "$mailtext";
	$mail->AltBody  =  "$mailtext";

	if(!$mail->Send()){
		echo "Fejl i afsendelse til $email<p>";
		echo "Mailer Error: " . $mail->ErrorInfo;
		exit;
	}
#	if ($mailantal==1) print "<BODY onLoad=\"javascript:alert('Mail sendt til $email')\">";
#	else echo "Mail sendt til $email<br>";

}

?>
