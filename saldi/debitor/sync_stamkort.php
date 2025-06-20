<?php
	@session_start();
	$s_id=session_id();

// --------------debitor/sync_stamkort.php--------lap 3.2.5-------2011.01.29--------------
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



include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$ordre_id=if_isset($_GET['ordre_id']);
$konto_id=if_isset($_GET['konto_id']);
$retning=if_isset($_GET['retning']);

if ($ordre_id && $konto_id) {
	if ($retning=='op') $r=db_fetch_array(db_select("select * from ordrer where id='$ordre_id'",__FILE__ . " linje " . __LINE__));
	else $r=db_fetch_array(db_select("select * from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
	$firmanavn=addslashes($r['firmanavn']);
	$addr1=addslashes($r['addr1']);
	$addr2=addslashes($r['addr2']);
	$postnr=addslashes($r['postnr']);
	$bynavn=addslashes($r['bynavn']);
	$land=addslashes($r['land']);
	$cvrnr=addslashes($r['cvrnr']);
	$betalingsbet=addslashes($r['betalingsbet']);
	$betalingsdage=$r['betalingsdage']*1;
	$email=addslashes($r['email']);
	$ean=addslashes($r['ean']);
	$institution=addslashes($r['institution']);
	if ($retning=='op') db_modify("update adresser set firmanavn='$firmanavn',addr1='$addr1',addr2='$addr2',postnr='$postnr',bynavn='$bynavn',land='$land',cvrnr='$cvrnr',betalingsbet='$betalingsbet',betalingsdage='$betalingsdage',email='$email',ean='$ean',institution='$institution' where id='$konto_id'",__FILE__ . " linje " . __LINE__);
	else db_modify("update ordrer set firmanavn='$firmanavn',addr1='$addr1',addr2='$addr2',postnr='$postnr',bynavn='$bynavn',land='$land',cvrnr='$cvrnr',betalingsbet='$betalingsbet',betalingsdage='$betalingsdage',email='$email',ean='$ean',institution='$institution' where id='$ordre_id'",__FILE__ . " linje " . __LINE__);
}
print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$ordre_id\">";

?>
</body></html>
