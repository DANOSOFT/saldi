<?php
// #----------------- soapserver/singleupdate.php -----ver 3.2.4---- 2011.10.25 ----------
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

function singleupdate($string) {
	$webservice='1';

	list($s_id,$tmp)=explode(chr(9),$string);
	if (!$s_id) return('1'.chr(9)."Missing session ID");
#	include("../includes/select.php");
	include ("../includes/connect.php");
	include ("../includes/online.php");

	$linje=NULL;
	$tabels=array('grupper','varianter','variant_typer','shop_ordrer','shop_varer','adresser','shop_adresser');
	$singleupdate=str_replace($s_id,"",$string);
	$singleupdate=str_replace(chr(9),"",$singleupdate);
	$singleupdate=str_replace(chr(10),"",$singleupdate);
	$singleupdate=str_replace(chr(13),"",$singleupdate);
#	$singleupdate=str_replace(" ","",$singleupdate);
	$singleupdate=strtolower($singleupdate);
	list($table,$tmp)=explode("set",$singleupdate,2);
	$table=trim($table);
#if ($table!='adresser')	return('1'.chr(9).$table);
	if (!in_array($table,$tabels)) return ('1'.chr(9).'Updating '.$table.' is not accepted');
	
#if ($table!='adresser')	return('1'.chr(9).$svar.":".$singleupdate);
	transaktion('begin');
	$svar=(db_modify("update $table $singleupdate",__FILE__ . " linje " . __LINE__));
	list($fejl,$svar)=explode(chr(9),$svar);
	if ($fejl) return($fejl.chr(9).$svar);
	else {
		transaktion('commit');
		return('0'.chr(9).$id);
	}
}
$server = new SoapServer("singleupdate.wsdl");
$server->addFunction("singleupdate");
$server->handle();

?>