<?php
@session_start();
$s_id=session_id();
// ------------includes/ret_valutadiff.php-------patch 3.2.9----2012-03-30--------
// LICENS>
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
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------
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

#cho "dkkdiff $dkkdiff<br>";

$logdate=date("Y-m-d");
$logtime=date("H:i");

#cho "select * from openpost where id='$post_id'<br>";
$query = db_select("select * from openpost where id='$post_id'",__FILE__ . " linje " . __LINE__); #$post_id er den post som skal udlignes.
if ($row = db_fetch_array($query)) {
	$konto_id=$row['konto_id'];
	$refnr=$row['refnr'];
	$amount=afrund($row['amount'],2);
	$transdate=$row['transdate'];
	$faktnr=$row['faktnr'];
	$kontonr=$row['konto_nr'];
	$beskrivelse=$row['beskrivelse']." (Valutadiff)";
#	$valuta=$row['valuta'];
#	if (!$valuta)$valuta='DKK';
	$valutakurs=afrund($row['valutakurs']*1,3);
	if (!$valutakurs) $valutakurs=100;
	$udlign='on';
	print "<input type = hidden name=konto_id value=$konto_id>";
} else print "<meta http-equiv=\"refresh\" content=\"0;URL=$retur?rapport=kontokort.php\">";
#if ($valuta!='DKK')	{
#cho "select box3 from grupper where art='VK' and box1='$valuta'<br>";
 $r=db_fetch_array(db_select("select box3 from grupper where art='VK' and box1='$valuta'",__FILE__ . " linje " . __LINE__));
#cho "$diffkto=$r[box3]<br>";
 if (!$diffkto=$r['box3']) {
	print "<BODY onLoad=\"javascript:alert('Kontonummer for valutadifferencer ikke fundet')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$retur?rapport=kontokort.php\">";
	exit;
 }
#}
$r=db_fetch_array(db_select("select gruppe,art from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__));
$gruppe=trim($r['gruppe']);
$art=trim($r['art']);
if (substr($art,0,1)=='D') $art='DG';
else $art='KG';
#cho "select box2 from grupper where art='$art' and kodenr='$gruppe'<br>";
$r=db_fetch_array(db_select("select box2 from grupper where art='$art' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
#cho "$samlekonto=$r[box2]<br>";
$samlekonto=$r['box2'];
$dkkdiff=afrund($dkkdiff,2);
$r=db_fetch_array(db_select("select sum(debet) as debet, sum(kredit) as kredit from transaktioner where kontonr='$samlekonto'",__FILE__ . " linje " . __LINE__));
$tmp=$r['debet']-$r['kredit'];
#cho "saldo $tmp<br>";
transaktion('begin');
if ($dkkdiff >= 0.01) {
#cho "insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, kladde_id,afd, ansat, projekt)values('$diffkto', '0', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$dkkdiff', '0', '0', '0', '0')<br>"; 
	db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, kladde_id,afd, ansat, projekt)values('$diffkto', '0', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$dkkdiff', '0', '0', '0', '0')",__FILE__ . " linje " . __LINE__);
#cho "insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, kladde_id,afd, ansat, projekt)values('$samlekonto', '0', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$dkkdiff', '0', '0', '0', '0')<br>";
	db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, kladde_id,afd, ansat, projekt)values('$samlekonto', '0', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$dkkdiff', '0', '0', '0', '0')",__FILE__ . " linje " . __LINE__);
	$tmp=$dkkdiff*-1;
} elseif ($dkkdiff <= -0.01) {
	$tmp=$dkkdiff*-1;
#cho "insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, kladde_id,afd, ansat, projekt)values($diffkto, '0', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$tmp', '0', '0', '0', '0')<br>";
	db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, kladde_id,afd, ansat, projekt)values($diffkto, '0', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$tmp', '0', '0', '0', '0')",__FILE__ . " linje " . __LINE__);
#cho "insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, kladde_id,afd, ansat, projekt)values('$samlekonto', '0', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$tmp', '0', '0', '0', '0')<br>";
	db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, kladde_id,afd, ansat, projekt)values('$samlekonto', '0', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$tmp', '0', '0', '0', '0')",__FILE__ . " linje " . __LINE__);
}
$r=db_fetch_array(db_select("select sum(debet) as debet, sum(kredit) as kredit from transaktioner where kontonr='$samlekonto'",__FILE__ . " linje " . __LINE__));
$tmp=$r['debet']-$r['kredit'];
#cho "saldo $tmp<br>";
#cho "insert into openpost (konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr,valuta,valutakurs,udlign_id,udlign_date) values ('$konto_id', '$kontonr', '$dkkdiff', '$beskrivelse', '1', '$transdate', '0', '0','-','0','0','$transdate')<br>";
	db_modify("insert into openpost (konto_id, konto_nr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr,valuta,valutakurs,udlign_id,udlign_date) values ('$konto_id', '$kontonr', '$dkkdiff', '$beskrivelse', '1', '$transdate', '0', '0','-','0','0','$transdate')",__FILE__ . " linje " . __LINE__);
#exit;
transaktion('commit');
print "<meta http-equiv=\"refresh\" content=\"0;URL=$retur?rapportart=Kontokort&dato_fra=$dato_fra&dato_til=$dato_til&konto_fra=$konto_fra&konto_til=$konto_til&submit=ok\">";
?>

