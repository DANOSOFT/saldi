<?php #topkode_start
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------debitor/formularprint-----patch 3.6.9---2017-05-05------
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
// Copyright (c) 2003-2017 saldi.dk aps
// ----------------------------------------------------------------------
// 
// 17.01.2013 Oprydning i forb. med fejlsøgning i ret_genfakt.php
// 08.04.2014 Ændret returside til ordre.php
// 2015.01.06 Indsat "returside"
// 2017.05.05 Ved $udskriv_til=='ingen' returneres uden udskrift.

@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/formfunk.php");
include("../includes/var2str.php");

if (isset($_GET['id']) && $_GET['id']){
	$id=if_isset($_GET['id']);
	$returside=if_isset($_GET['returside']);
	$formular=if_isset($_GET['formular']);
	$lev_nr=if_isset($_GET['lev_nr']);
	$udskriv_til=if_isset($_GET['udskriv_til']);
	$bg="nix";
#	$subjekt=if_isset($_POST['subjekt']);
#	$mailtext=if_isset($_POST['mailtext']);
	if ($udskriv_til=='ingen') $svar='OK';
	else $svar=formularprint($id,$formular,$lev_nr,$charset,$udskriv_til);
	if ($svar && $svar!='OK') {
		print "<BODY onLoad=\"javascript:alert('$svar')\">";
		if ($returside) {
			print "<meta http-equiv=\"refresh\" content=\"1;URL=$returside\">";
			exit;
		}
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/ordre.php?id=$id\">";
		exit;
	}
}
if ($returside) {
	print "<meta http-equiv=\"refresh\" content=\"1;URL=$returside\">";
	exit;
} elseif ($popup) {
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../includes/luk.php\">";
	exit;
	#	else print "<meta http-equiv=\"refresh\" content=\"1;URL=ordreliste.php?id=$id\">";
} elseif (is_numeric($id) && $id > 1) {
	print "<meta http-equiv=\"refresh\" content=\"1;URL=ordre.php?id=$id\">";
	exit;
} else 	print "<meta http-equiv=\"refresh\" content=\"1;URL=ordreliste.php\">";

?>


