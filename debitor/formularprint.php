<?php #topkode_start
@session_start();
$s_id=session_id();

// ---------debitor/formularprint-----patch 3.2.9---2013-01-17------
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
// 
// 17.01.2013 Oprydning i forb. med fejlsøgning i ret_genfakt.php

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/formfunk.php");
include("../includes/var2str.php");

if (isset($_GET['id']) && $_GET['id']){
	$id=if_isset($_GET['id']);
	$formular=if_isset($_GET['formular']);
	$lev_nr=if_isset($_GET['lev_nr']);
	$udskriv_til=if_isset($_GET['udskriv_til']);
	$bg="nix";
#	$subjekt=if_isset($_POST['subjekt']);
#	$mailtext=if_isset($_POST['mailtext']);
#exit;
	formularprint($id,$formular,$lev_nr,$charset,$udskriv_til);
}
if ($popup) print "<meta http-equiv=\"refresh\" content=\"1;URL=../includes/luk.php\">";
else print "<meta http-equiv=\"refresh\" content=\"1;URL=ordreliste.php?id=$id\">";
?>


