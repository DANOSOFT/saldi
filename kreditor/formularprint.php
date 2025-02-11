<?php #topkode_start
@session_start();
$s_id=session_id();

// ---------kreditor/formularprint.php-----patch 3.2.9---2013-06-18------
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
	$r=db_fetch_array(db_select("select id from formularer where formular = $formular and art = 1 and beskrivelse ='LOGO'",__FILE__ . " linje " . __LINE__));
	if (!$r['id']) {
		include("../includes/formularimport.php");
		formularimport("../importfiler/formular.txt",$formular);
	}
	
	$svar=formularprint($id,$formular,$lev_nr,$charset,$udskriv_til);
	if ($svar && $svar!='OK') {
		print "<BODY onLoad=\"javascript:alert('$svar')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../kreditor/ordre.php?id=$id\">";
		exit;
	}
}
if ($popup) print "<meta http-equiv=\"refresh\" content=\"1;URL=../includes/luk.php\">";
else print "<meta http-equiv=\"refresh\" content=\"1;URL=ordreliste.php?id=$id\">";
?>


