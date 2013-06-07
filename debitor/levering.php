<?php
	@session_start();
	$s_id=session_id();

// --------------debitor/levering.php--------lap 3.2.9-------2013.05.08--------------
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
// Copyright (c) 2004-2013 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2013.05.06 Rettet lidt i fejlhåndtering.
// 2013.05.08 Tilføjet "and status < 2" så status ikke bliver sat tilbage ved klik på tilbagenap på mus.

$id=NULL;	
if (isset($_GET['id'])) $id=($_GET['id']);

if ($id && $id>=1) { 
	$modulnr=5;
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	include("../includes/ordrefunc.php");
	include("../includes/fuld_stykliste.php");
	$hurtigfakt=if_isset($_GET['hurtigfakt']);
	$genfakt=if_isset($_GET['genfakt']);
	$pbs=if_isset($_GET['pbs']);
	$mail_fakt=if_isset($_GET['mail_fakt']);
	transaktion("begin");
	$svar=levering($id,$hurtigfakt,$genfakt,0);
	if ($svar=='OK') {
		transaktion("commit");
		if ($hurtigfakt=='on') {
			db_modify("update ordrer set status=2 where id='$id' and status<2",__FILE__ . " linje " . __LINE__);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=bogfor.php?id=$id&genfakt=$genfakt&mail_fakt=$mail_fakt&pbs=$pbs\">";
			exit;
		} else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	} else print "<BODY onLoad=\"javascript:alert('$svar')\">";
}

?>
</body></html>
