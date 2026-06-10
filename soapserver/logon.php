<?php
// #----------------- soapserver/logon.php -----ver 3.2.3---- 2011.10.11 ----------
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

function logon($string) {
	@session_start();
	$s_id=session_id();
	list($regnskab,$brugernavn,$password)=explode(chr(9),$string);
	$password=md5($password);
	$unixtime=date("U");
	include("../includes/db_query.php");
	include ("../includes/connect.php");
	db_modify("delete from online where  session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array(db_select("select * from regnskab where regnskab = '$regnskab'",__FILE__ . " linje " . __LINE__))) {
		if ($db = trim($r['db'])) {
			$connection = db_connect ($sqhost,$squser,$sqpass,$sqdb);
			if ($connection) {
				db_modify("insert into online (session_id, brugernavn, db, dbuser) values ('$s_id', '$brugernavn', '$db', '$squser')",__FILE__ . " linje " . __LINE__);
				include ("../includes/online.php");
				if ($r = db_fetch_array(db_select("select * from brugere where brugernavn = '$brugernavn' and kode='$password'",__FILE__ . " linje " . __LINE__))) {
					$rettigheder=trim($r['rettigheder']);
					$regnskabsaar=$r['regnskabsaar']*1;
					include ("../includes/connect.php");
			$fp=fopen("../temp/.ht_$db.log","a");
			fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": OK jeg er inde ".$s_id."\n");
			fclose($fp);
					db_modify("update online set regnskabsaar='$regnskabsaar', rettigheder='$rettigheder' where session_id='$s_id'",__FILE__ . " linje " . __LINE__);
					$return='0'.chr(9).$s_id;
				} else {
					db_modify("delete from online where  session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
					$return="1".chr(9)."Username or password error";
				}	
			} else $return="1".chr(9)."Connection to database failed";
		} else $return="1".chr(9)."Unknown finacial report";
	} else return $return="1".chr(9)."Unknown finacial report";
	return ($return);
}
$server = new SoapServer("logon.wsdl");
$server->addFunction("logon");
$server->handle();
?>
