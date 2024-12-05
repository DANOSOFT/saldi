<?php
// ------------- debitor/korntofusion.php ---------- lap 3.2.6 ----2012-01-09-----------
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
// Copyright (c) 2003-2012 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$modulnr=6;
$title="Fusioner debitorer";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$fusion=if_isset($_POST['fusion']);

$returside=if_isset($_GET['returside']);
$ordre_id=if_isset($_GET['ordre_id']);
$fokus=if_isset($_GET['fokus']);

$id=if_isset($_GET['id']);
$kontonr=if_isset($_GET['kontonr']);

if ($fusion == "Fortryd") {
#cho "fortryd<br>";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?returside=$returside&ordre_id=$ordre_id&id=$id&fokus=$fokus\">\n";
	exit;
}
#cho "select id from adresser where art='D' and kontonr='$kontonr' and id != '$id'<br>";
$r=db_fetch_array(db_select("select id,lukket from adresser where art='D' and kontonr='$kontonr' and id != '$id'",__FILE__ . " linje " . __LINE__));
($r['lukket'])?$ny_id=NULL:$ny_id=$r['id'];

if (!$ny_id) {
	$alerttekst="Der findes ikke andre debotirer med kontonr: $kontonr";  
#	$alerttekst=findtekst(345,$sprog_id);
	print "<BODY onLoad=\"javascript:alert('$alerttekst')\"><!--tekst 345-->";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?returside=$returside&ordre_id=$ordre_id&id=$id&fokus=$fokus\">\n";
} 

if ($id&&$kontonr&&$fusion=='OK') {
	transaktion('begin');
	db_modify("update historik set konto_id='$ny_id' where konto_id='$id'",__FILE__ . " linje " . __LINE__);  
	db_modify("update ansatte set konto_id='$ny_id' where konto_id='$id'",__FILE__ . " linje " . __LINE__);  
	db_modify("update ordrer set konto_id='$ny_id' where konto_id='$id'",__FILE__ . " linje " . __LINE__);  
	db_modify("update openpost set konto_id='$ny_id' where konto_id='$id'",__FILE__ . " linje " . __LINE__);  
	db_modify("update jobkort set konto_id='$ny_id' where konto_id='$id'",__FILE__ . " linje " . __LINE__);  
	db_modify("update pbs_kunder set konto_id='$ny_id' where konto_id='$id'",__FILE__ . " linje " . __LINE__);  
	db_modify("update shop_adresser set saldi_id='$ny_id' where saldi_id='$id'",__FILE__ . " linje " . __LINE__);  
	db_modify("delete from adresser where id='$id'",__FILE__ . " linje " . __LINE__);  
	transaktion('commit');
	print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?returside=$returside&ordre_id=$ordre_id&id=$ny_id&fokus=$fokus\">\n";
} else {
	$r=db_fetch_array(db_select("select * from adresser where id = '$id'",__FILE__ . " linje " . __LINE__));
	$gl_firmanavn=$r['firmanavn'];
	$gl_addr1=$r['addr1'];
	$gl_addr2=$r['addr2'];
	$gl_postnr=$r['postnr'];
	$gl_bynavn=$r['bynavn'];
	$gl_cvrnr=$r['cvrnr'];
	$gl_tlf=$r['tlf'];
	$r=db_fetch_array(db_select("select * from adresser where id = '$ny_id'",__FILE__ . " linje " . __LINE__));
	$ny_firmanavn=$r['firmanavn'];
	$ny_addr1=$r['addr1'];
	$ny_addr2=$r['addr2'];
	$ny_postnr=$r['postnr'];
	$ny_bynavn=$r['bynavn'];
	$ny_cvrnr=$r['cvrnr'];
	$ny_tlf=$r['tlf'];

	print "<form name=\"kontofusion\" action=\"kontofusion.php?returside=$returside&ordre_id=$ordre_id&id=$id&kontonr=$kontonr&fokus=$fokus\" method=\"post\">\n";
	print "<table><tbody>";
	print "<tr><td colspan=2><b>Klik OK for at flytte kontakter,ordrer,historik mm fra konto:</b></td></tr>";
	print "<tr><td>Navn</td><td>$gl_firmanavn</td></tr>";
	print "<tr><td>Addresse</td><td>$gl_addr1</td></tr>";
	print "<tr><td></td><td>$gl_addr2</td></tr>";
	print "<tr><td>Postnr & By</td><td>$gl_postnr $gl_bynavn</td></tr>";
	print "<tr><td>Cvrnr</td><td>$gl_cvrnr</td></tr>";
	print "<tr><td>Tlf</td><td>$gl_tlf</td></tr>";
	print "<tr><td colspan=2><b>til</b></td></tr>";
	print "<tr><td>Navn</td><td>$ny_firmanavn</td></tr>";
	print "<tr><td>Addresse</td><td>$ny_addr1</td></tr>";
	print "<tr><td></td><td>$ny_addr2</td></tr>";
	print "<tr><td>Postnr & By</td><td>$ny_postnr $ny_bynavn</td></tr>";
	print "<tr><td>Cvrnr</td><td>$ny_cvrnr</td></tr>";
	print "<tr><td>Tlf</td><td>$ny_tlf</td></tr>";
	print "<tr><td colspan=2><b>Eller klik Fortryd for at afbryde</b></td></tr>";
	print "<tr><td colspan=2 align = center><input style=\"width:40px;\" type=\"submit\" value=\"Fortryd\" name=\"fusion\"><input style=\"width:40px;\" type=\"submit\" value=\"OK\" name=\"fusion\"></td>";
	print "</tbody></table>";
	print "</form>";
}
?> 
