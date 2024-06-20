<?php
// #----------------- soapserver/singleselect.php -----ver 3.2.3---- 2011.10.11 ----------
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

function singleselect($string) {
	list($s_id,$singleselect)=explode(chr(9),$string,2);
	$webservice='1';
#	include("../includes/select.php");
	include ("../includes/connect.php");
	include ("../includes/online.php");
	$fp=fopen("../temp/soap.log","a");
	fwrite($fp,"A: ".$singleselect."\n");
	$linje=NULL;
	$singleselect="select ".$singleselect;
	fwrite($fp,"B: ".$singleselect."\n");
	$r=0;
	$q=db_select("$singleselect",__FILE__ . " linje " . __LINE__);
	while ($r < db_num_fields($q)) {
		$fieldName = db_field_name($q,$r); 
		$fieldType = db_field_type($q,$r); 
		($linje)?$linje.=chr(9).$fieldName."(".$fieldType.")":$linje=$fieldName."(".$fieldType.")"; 
		$r++;

	}	
	$linje=NULL;
	$arraysize=$r;
	$fp=fopen("../temp/soap.log","a");
	fwrite($fp,"C: ".$singleselect."\n");
	$q=db_select("$singleselect",__FILE__ . " linje " . __LINE__);
	if ($r=db_fetch_array($q)) {
		$linje=NULL;
		for ($x=0;$x<$arraysize;$x++) {
			($linje)?$linje.=chr(9).$r[$x]:$linje=$r[$x]; 
		}
	}
	fwrite($fp,$linje."\n");
	fclose ($fp);
	return ('0'.chr(9).$linje);
}
$server = new SoapServer("singleselect.wsdl");
$server->addFunction("singleselect");
$server->handle();

?>