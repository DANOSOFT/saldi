<?php
// #----------------- soapserver/multiselect.php -----ver 3.2.3---- 2011.10.11 ----------
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
ini_set("soap.wsdl_cache_enabled", "0");

function multiselect($string) {
	list($s_id,$multiselect)=explode(chr(9),$string);
	$webservice='1';
#	include("../includes/select.php");
	include ("../includes/connect.php");
	include ("../includes/online.php");

	$linje=NULL;
	$filnavn="../temp/$db/$bruger_id.csv";
	if(!file_exists("../temp/$db")) mkdir("../temp/$db");
	$fp=fopen($filnavn,"w");
	$multiselect="select ".$multiselect;

	$r=0;
	$q=db_select("$multiselect",__FILE__ . " linje " . __LINE__);
	while ($r < db_num_fields($q)) {
		$fieldName = db_field_name($q,$r); 
		$fieldType = db_field_type($q,$r); 
		($linje)?$linje.=chr(9).$fieldName."(".$fieldType.")":$linje=$fieldName."(".$fieldType.")"; 
		$r++;
	}
	if (!$linje) return ('1'.chr(9).'fejl i query ('.$multiselect.')');
	if ($fp) {
		fwrite ($fp, "$linje\n");
	}

	$q=db_select("$multiselect",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$linje=NULL;
		$arraysize=count($r);
		for ($x=0;$x<$arraysize;$x++) {
			if (isset($r[$x])) {
				$r[$x]=str_replace(chr(9),"<TAB>",$r[$x]);
				$r[$x]=str_replace(chr(10),"<LF>",$r[$x]);
				$r[$x]=str_replace(chr(13),"<CR>",$r[$x]);
				($linje)?$linje.=chr(9).$r[$x]:$linje=$r[$x]; 
			}
		}
		if ($fp) {
			fwrite ($fp, "$linje\n");
		}
	}
	fclose($fp);
	return ("0".chr(9)."$filnavn");

/*
#return ("$selectquery");
	$q=db_select("$selectquery");
	while ($r < db_num_fields($q)) {
		$fieldName = db_field_name($q, $r); {
			$svar.=$fieldName .chr(9); 
			$r++;
		}
	}
	return ("$svar");
*/
}
$server = new SoapServer("multiselect.wsdl");
$server->addFunction("multiselect");
$server->handle();

?>