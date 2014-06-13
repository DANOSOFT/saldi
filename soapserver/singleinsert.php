<?php
// // #----------------- soapserver/singleinsert.php -----ver 3.2.8---- 2014.01.06 ----------
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
// 2014.01.06 Fejl hvis paranteser i variabel. Søg 20140106

ini_set("soap.wsdl_cache_enabled", "1");

function singleinsert($string) {
	$webservice='1';
	global $webservice;

	list($s_id,$tmp)=explode(chr(9),$string);
	if (!$s_id) return('1'.chr(9)."Missing session ID");
#	include("../includes/select.php");
	include ("../includes/connect.php");
	include ("../includes/online.php");

	$linje=NULL;
	$fp=fopen("../temp/soap.log","a");
	$tabels=array('ordrer','shop_ordrer','ordrelinjer','varer','shop_varer','vare_lev','adresser','ansatte','shop_adresser','kladdeliste','kassekladde','grupper');
	$singleinsert=str_replace($s_id,"",$string);
	$singleinsert=str_replace(chr(9),"",$singleinsert);
	$singleinsert=str_replace(chr(10),"",$singleinsert);
	$singleinsert=str_replace(chr(13),"",$singleinsert);
#	$singleinsert=str_replace(" ","",$singleinsert);
	list($table,$tmp)=explode("(",$singleinsert,2);
	$table=trim($table);
#if ($table!='adresser')	return('1'.chr(9).$table);
	if (!in_array($table,$tabels)) return ('1'.chr(9).'Inserting into '.$table.' is not accepted');
	$singleinsert=str_replace("values","VALUES",$singleinsert);
	list($part1,$part2)=explode("VALUES",$singleinsert);
	list($part1,$tmp)=explode(")",$part1);
	list($tmp,$part1)=explode("(",$part1);
#	$part1=str_replace(" ","",$part1);
	fwrite($fp,"Part 1:".$part1."\n");
	$fields=explode(",",$part1);	
#	list($tmp,$part2)=explode("(",$part2); #20140106 + næste 3 linjer
#	list($part2,$tmp)=explode(")",$part2);
	$part2=trim($part2);
	$part2=substr($part2,1,strlen($part2)-2);
	
	fwrite($fp,$part2."\n");
	$part2=str_replace("<TAB>",chr(9),$part2);
	fwrite($fp,$part2."\n");
	$part2=str_replace("','","'',''",$part2);
	$values=explode("','",$part2);
	fwrite($fp,$part2."\n");
	if (!$fields || !$values) return('1'.chr(9)."Missing fields or values");
/*
	if ($table=='adresser') {
		for ($x=0;$x<count($fields);$x++) {
			if ($fields[$x]=='kontonr') {
				$values[$x]=str_replace(" ","",$values[$x]);
				$values[$x]*=1;
				if (!$values[$x]) {
					$y=0;
					$q=db_select("select kontonr from adresser",__FILE__ . " linje " . __LINE__);
					while ($r=db_fetch_array($q)) {
						$kontonr[$y]=$r['kontonr'];
						$y++;
					}
					$tmp=1000;
					while (in_array($tmp,$kontonr)) $tmp++;
					$values[$x]=$tmp;
				}
			}
		}
	}
*/	
	$singleinsert=str_replace("<TAB>",chr(9),$singleinsert);
	$singleinsert="insert into ".$singleinsert;
	fwrite($fp,$singleinsert."\n");
	transaktion('begin');
	$svar=(db_modify("$singleinsert",__FILE__ . " linje " . __LINE__));
	list($fejl,$svar)=explode(chr(9),$svar);
	fwrite($fp,"Fejl".$fejl.":".$svar."\n");
	if ($fejl) return($fejl.chr(9).$svar);
	else {
		$select="select max(id) as id from $table where ";
		for ($i=0;$i<count($fields);$i++) {
			if ($i>0) $select.=" and "; 
			$select.= $fields[$i]."=".$values[$i];
		} 
		fwrite($fp,$select."\n");
		fclose($fp);
		$r=db_fetch_array(db_select($select,__FILE__ . " linje " . __LINE__));
		$id=$r['id'];
		if (!$id) return('1'.chr(9)."Insert failed\n".$select."\n".$singleinsert);
		transaktion('commit');
		if ($id) return('0'.chr(9).$id);
	}
}
$server = new SoapServer("singleinsert.wsdl");
$server->addFunction("singleinsert");
$server->handle();

?>