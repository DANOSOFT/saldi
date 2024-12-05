<?php
//                         ___   _   _   ___  _
//                        / __| / \ | | |   \| |
//                        \__ \/ _ \| |_| |) | |
//                        |___/_/ \_|___|___/|_|

// ------------includes/ret_valutadiff.php-------patch 3.6.6----2016-04-14--------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2016 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20151116 - Funktion sat ud af drift.
// 20160226 - Rutiner omskrevet og opdateret
// 20160413 - at bogføringsdato hentes med $_GET søg $bfdate
// 20160414 - Viste netativt fortegn på skærm

@session_start();
$s_id=session_id();


$modulnr=12;
$kontonr=array();$post_id=array();
$linjebg=NULL;
$title="Reguler valutadiff";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$post_id=if_isset($_GET['post_id']);
$dato_fra=if_isset($_GET['dato_fra']);
$dato_til=if_isset($_GET['dato_til']);
$konto_fra=if_isset($_GET['konto_fra']);
$konto_til=if_isset($_GET['konto_til']); 
$retur=if_isset($_GET['retur']);
$returside=if_isset($_GET['returside']);
$ny_kurs=if_isset($_GET['ny_kurs']);
if ($ny_kurs) $ny_kurs=afrund($ny_kurs,3);
$dkkdiff=if_isset($_GET['diff']);
$valuta=if_isset($_GET['valuta']);
$bfdate=if_isset($_GET['bfdate']);
$godkend=if_isset($_POST['godkend']);
$afbryd=if_isset($_POST['afbryd']);

if ($afbryd) {
	print "<meta http-equiv=\"refresh\" content=\"0;";
	print "URL=$retur?rapportart=Kontokort&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&submit=ok\">";
	exit;
}

$logdate=date("Y-m-d");
$logtime=date("H:i");

$query = db_select("select * from openpost where id='$post_id'",__FILE__ . " linje " . __LINE__); #$post_id er den post som skal udlignes.
if ($row = db_fetch_array($query)) {
	$konto_id=$row['konto_id'];
	$refnr=$row['refnr'];
	$amount=afrund($row['amount'],2);
	$transdate=$row['transdate'];
	$faktnr=$row['faktnr'];
	$kontonr=$row['konto_nr'];
	$beskrivelse=$row['beskrivelse']." (Valutadiff (DKK ".dkdecimal($dkkdiff)."))";
	$valutakurs=afrund($row['valutakurs']*1,3);
	if (!$valutakurs) $valutakurs=100;
	$udlign='on';
	print "<input type = hidden name=konto_id value=$konto_id>";
} else print "<meta http-equiv=\"refresh\" content=\"0;URL=$retur?rapport=kontokort.php\">";
	$r=db_fetch_array(db_select("select box3 from grupper where art='VK' and box1='$valuta'",__FILE__ . " linje " . __LINE__));
 if (!$diffkto=$r['box3']) {
	print "<BODY onLoad=\"javascript:alert('Kontonummer for valutadifferencer ikke fundet')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$retur?rapport=kontokort.php\">";
	exit;
 }
#}

if ($row = db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__))){
		$regnstart=trim($row['box2'])."-".trim($row['box1'])."-01";
		$regnslut=usdate("31-".trim($row['box3'])."-".trim($row['box4']))	; #usdate bruges for at sikre korrekt dato.
} else {
	$alerttekst='Regnskabs&aring;r ikke oprettet!';
	print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$retur?rapport=kontokort.php\">";
}
if (!$bfdate) {
	if ($transdate<$regnstart) $bfdate=$regnstart;
	elseif ($transdate>$regnslut) $bfdate=$regnslut;
	else $bfdate=$transdate;
}
$qtxt="select gruppe,art from adresser where id = '$konto_id'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$gruppe=trim($r['gruppe']);
$art=trim($r['art']);
if (substr($art,0,1)=='D') $art='DG';
else $art='KG';
$qtxt="select box2 from grupper where art='$art' and kodenr='$gruppe'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
#cho "$samlekonto=$r[box2]<br>";
$samlekonto=$r['box2'];
$dkkdiff=afrund($dkkdiff,2);
$qtxt="select sum(debet) as debet, sum(kredit) as kredit from transaktioner where kontonr='$samlekonto'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$tmp=$r['debet']-$r['kredit'];
#cho "saldo $tmp<br>";
	if ($godkend) {
	transaktion('begin');
		if ($dkkdiff >= 0.01) {
		$qtxt="insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, kladde_id,afd, ansat, projekt, ordre_id)values('$diffkto','0','$bfdate','$logdate','$logtime','$beskrivelse','$dkkdiff','0','0','0','0','0')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, kladde_id,afd, ansat, projekt, ordre_id)values('$samlekonto','0','$bfdate','$logdate','$logtime','$beskrivelse','$dkkdiff','0','0','0','0','0')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$tmp=$dkkdiff*-1;
	} elseif ($dkkdiff <= -0.01) {
		$tmp=$dkkdiff*-1;
		$qtxt="insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, kladde_id,afd, ansat, projekt, ordre_id)values($diffkto, '0','$bfdate','$logdate','$logtime','$beskrivelse','$tmp','0','0','0','0','0')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, kladde_id,afd, ansat, projekt, ordre_id)values('$samlekonto','0','$bfdate','$logdate','$logtime','$beskrivelse','$tmp','0','0','0','0','0')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$qtxt="select sum(debet) as debet, sum(kredit) as kredit from transaktioner where kontonr='$samlekonto'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$tmp=$r['debet']-$r['kredit'];
	$qtxt="insert into openpost (konto_id,konto_nr,amount,beskrivelse,udlignet,transdate,kladde_id,refnr,valuta,valutakurs,udlign_id,udlign_date) ";
	$qtxt.="values ";
	$qtxt.="('$konto_id','$kontonr','$dkkdiff','$beskrivelse','1','$bfdate','0','0','-','0','0','$bfdate')";
#cho "$qtxt<br>";
#xit;
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#exit;
	transaktion('commit');
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$retur?rapportart=Kontokort&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&submit=ok\">";
} else {
	print "<center>";
	print "<form name=\"ret_valutadiff\" method=\"post\" autocomplete=\"off\" action=\"ret_valutadiff.php?bfdate=$bfdate&retur=$retur&post_id=$post_id&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&valuta=$valuta&diff=$dkkdiff\">";
	print "<table><tbody>";
	print "<tr><td colspan=\"3\">Nedenstående vil blive bogført pr. ".dkdato($bfdate)." hvis du klikker OK<br>";
	print "Kontroller tallene og afbryd hvis det ser forkert ud</td></td></tr>";
	print "<tr><td>Kontonr</td><td>kredit</td><td>debet</td></tr>"; 
	if ($dkkdiff >= 0.01) {
		print "<tr><td>$diffkto</td><td>".dkdecimal($dkkdiff)."</td><td></td></tr>"; 
		print "<tr><td>$samlekonto</td><td></td><td>".dkdecimal($dkkdiff)."</td></tr>"; 
	} else {
		print "<tr><td>$diffkto</td><td></td><td>".dkdecimal($dkkdiff*-1)."</td></tr>"; #20160414
		print "<tr><td>$samlekonto</td><td>".dkdecimal($dkkdiff*-1)."</td><td></td></tr>"; #20160414 
	}
	print "<tr><td colspan=\"3\" align=\"center\"><input type=\"submit\" style=\"width:100px\" name =\"afbryd\" value=\"Afbryd\"> <input type=\"submit\" style=\"width:100px\" name =\"godkend\" value=\"OK\"></td></tr>";
#		print "<meta http-equiv=\"refresh\" content=\"5;URL=$retur?rapportart=Kontokort&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&submit=ok\">";
	print "</tbody></table>";
	print "</form>";
}
?>

