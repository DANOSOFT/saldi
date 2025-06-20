<?php
// #----------------- soapserver/logoff.php -----ver 3.2.3---- 2011.10.11 ----------
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
ini_set("soap.wsdl_cache_enabled", "1");

function logoff($s_id) {	
	$modulnr=1;
	include("../includes/connect.php");
	if ($r = db_fetch_array(db_select("select * from online where session_id='$s_id'",__FILE__ . " linje " . __LINE__))) {
		db_modify("delete from online where session_id='$s_id'",__FILE__ . " linje " . __LINE__); 
		return ('0'.chr(9).'GoodBye');
	} else return ('1'.chr(9).'No active session');
}
$server = new SoapServer("logoff.wsdl");
$server->addFunction("logoff");
$server->handle();
?>
