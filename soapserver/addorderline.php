<?php
// #----------------- soapserver/addordreline.php -----ver 3.4.1---- 2014.04.26 ----------
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
// Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2014.03.15 Indsat ,"100" (procent) før ,'DO' i opret_ordrelinje grundet ændring af funktion
// 2014.04.26 - Indsat "" foran $varenr i kald til opret_ordrelinje grundet ændring i funktionen (PHR - Danosoft) Søg 20140426 


ini_set("soap.wsdl_cache_enabled", "1");

function addorderline($string) {
	global $webservice;
	$webservice='1';

	list($s_id,$tmp)=explode(chr(9),$string);
	if (!$s_id) return('1'.chr(9)."Missing session ID");
	include ("../includes/connect.php");
	include ("../includes/online.php");
	include ("../includes/ordrefunc.php");

	$addorderline=trim(str_replace($s_id,"",$string));
	$addorderline=str_replace(chr(10),"",$addorderline);
	$addorderline=str_replace(chr(13),"",$addorderline);
	list($ordre_id,$varenr,$beskrivelse,$antal,$salgspris,$momssats,$posnr)=explode(chr(9),$addorderline);
	$svar=opret_ordrelinje($ordre_id,"",$varenr,$antal,$beskrivelse,$salgspris,"0","100","DO","0",$posnr,0,"on","","","");
	if (is_numeric($svar)) return('0'.chr(9).$svar);
	else return('1'.chr(9).$svar);
}
$server = new SoapServer("addorderline.wsdl");
$server->addFunction("addorderline");
$server->handle();

?>