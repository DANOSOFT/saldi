<?php
// #----------------- soapfunc.php -----ver 3.2.9---- 2012.10.09 ----------
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
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------

if (!function_exists('logon')) {
	function logon($regnskab,$username,$password) {
		global $url;
#		include("saldi_connect.php");
		(strpos(getcwd(),'soapklient'))?$folder="":$folder="soapklient/";
		$wsdl='logon.wsdl';
#		if (file_exists("$folder".$wsdl)) unlink("$folder".$wsdl);
#		if (file_exists("$folder".$wsdl."*")) unlink("$folder".$wsdl."*");
		$fp1=fopen($url.$wsdl,"r");
		$fp2=fopen($folder.$wsdl,"w");
		while($linje=fgets($fp1)){
			$linje=str_replace('%SERVERURL%',$url,$linje);
			fwrite($fp2,$linje);
		}
		fclose($fp1);
		fclose($fp2);
#		($folder)?system("cd $folder\nwget ".$url.$wsdl):system("wget ".$url.$wsdl); 
		$soap = new SoapClient("$folder".$wsdl);
		list($error,$svar)=explode(chr(9),$soap->logon("$regnskab".chr(9)."$username".chr(9)."$password"));
		if ($error) {
			return ("1".chr(9)."Adgang n&aelig;gtet: $svar"); 
		} else {
			return ("0".chr(9)."$svar");
		}
	}
}
if (!function_exists('multiselect')) {
	function multiselect($s_id,$query) {
		global $url;
		$query=utf8_encode($query);
		$string="$s_id".chr(9)."$query";
		(strpos(getcwd(),'soapklient'))?$folder="":$folder="soapklient/";
		$wsdl='multiselect.wsdl';
#		unlink("$folder".$wsdl);
#		unlink("$folder".$wsdl."*");
#		($folder)?system("cd $folder\nwget ".$url.$wsdl):system("wget ".$url.$wsdl); 
		$fp1=fopen($url.$wsdl,"r");
		$fp2=fopen($folder.$wsdl,"w");
		while($linje=fgets($fp1)){
			$linje=str_replace('%SERVERURL%',$url,$linje);
			fwrite($fp2,$linje);
		}
		fclose($fp1);
		fclose($fp2);
		$soap = new SoapClient("$folder".$wsdl);
		return ($soap->multiselect("$string"));
	}
}

if (!function_exists('singleselect')) {
	function singleselect($s_id,$query) {
		global $url;
		$query=utf8_encode($query);
		$string="$s_id".chr(9)."$query";
		(strpos(getcwd(),'soapklient'))?$folder="":$folder="soapklient/";
		$wsdl='singleselect.wsdl';
#		unlink("$folder".$wsdl);
#		unlink("$folder".$wsdl."*");
#		($folder)?system("cd $folder\nwget ".$url.$wsdl):system("wget ".$url.$wsdl); 
		$fp1=fopen($url.$wsdl,"r");
		$fp2=fopen($folder.$wsdl,"w");
		while($linje=fgets($fp1)){
			$linje=str_replace('%SERVERURL%',$url,$linje);
			fwrite($fp2,$linje);
		}
		fclose($fp1);
		fclose($fp2);
		$soap = new SoapClient("$folder".$wsdl);
		return ($soap->singleselect("$string"));
	}
}

if (!function_exists('singleinsert')) {
	function singleinsert($s_id,$query) {
		global $url;
		$query=utf8_encode($query);
		$string="$s_id".chr(9)."$query";
		(strpos(getcwd(),'soapklient'))?$folder="":$folder="soapklient/";
		$wsdl='singleinsert.wsdl';
#		unlink("$folder".$wsdl);
#		unlink("$folder".$wsdl."*");
#		($folder)?system("cd $folder\nwget ".$url.$wsdl):system("wget ".$url.$wsdl); 
#		system("cd $folder\nwget ".$url.$wsdl); 
		$fp1=fopen($url.$wsdl,"r");
		$fp2=fopen($folder.$wsdl,"w");
		while($linje=fgets($fp1)){
			$linje=str_replace('%SERVERURL%',$url,$linje);
			fwrite($fp2,$linje);
		}
		fclose($fp1);
		fclose($fp2);
		$soap = new SoapClient("$folder".$wsdl);
		return ($soap->singleinsert("$string"));
	}
}

if (!function_exists('singleupdate')) {
	function singleupdate($s_id,$query) {
		global $url;
		$query=utf8_encode($query);
		$string="$s_id".chr(9)."$query";
		(strpos(getcwd(),'soapklient'))?$folder="":$folder="soapklient/";
		$wsdl='singleupdate.wsdl';
#		unlink("$folder".$wsdl);
#		unlink("$folder".$wsdl."*");
#		($folder)?system("cd $folder\nwget ".$url.$wsdl):system("wget ".$url.$wsdl); 
#		system("cd $folder\nwget ".$url.$wsdl); 
		$fp1=fopen($url.$wsdl,"r");
		$fp2=fopen($folder.$wsdl,"w");
		while($linje=fgets($fp1)){
			$linje=str_replace('%SERVERURL%',$url,$linje);
			fwrite($fp2,$linje);
		}
		fclose($fp1);
		fclose($fp2);
		$soap = new SoapClient("$folder".$wsdl);
		return ($soap->singleupdate("$string"));
	}
}

if (!function_exists('addorderline')) {
	function addorderline($s_id,$ordre_id,$products_model,$products_name,$products_quantity,$products_price,$products_tax,$posnr) {
		global $url;
		$string="$s_id".chr(9)."$ordre_id".chr(9)."$products_model".chr(9)."$products_name".chr(9)."$products_quantity".chr(9)."$products_price".chr(9)."$products_tax".chr(9)."$posnr";
		$string=utf8_encode($string);
		(strpos(getcwd(),'soapklient'))?$folder="":$folder="soapklient/";
		$wsdl='addorderline.wsdl';
#		unlink("$folder".$wsdl);
#		unlink("$folder".$wsdl."*");
#		($folder)?system("cd $folder\nwget ".$url.$wsdl):system("wget ".$url.$wsdl); 
		$fp1=fopen($url.$wsdl,"r");
		$fp2=fopen($folder.$wsdl,"w");
		while($linje=fgets($fp1)){
			$linje=str_replace('%SERVERURL%',$url,$linje);
			fwrite($fp2,$linje);
		}
		fclose($fp1);
		fclose($fp2);
		$soap = new SoapClient("$folder".$wsdl);
		return ($soap->addorderline("$string"));
	}
}

if (!function_exists('logoff')) {
	function logoff($s_id) {
		global $url;
		(strpos(getcwd(),'soapklient'))?$folder="":$folder="soapklient/";
		$wsdl='logoff.wsdl';
#		unlink($wsdl);
#		unlink("$folder".$wsdl."*");
#		($folder)?system("cd $folder\nwget ".$url.$wsdl):system("wget ".$url.$wsdl); 
		$fp1=fopen($url.$wsdl,"r");
		$fp2=fopen($folder.$wsdl,"w");
		while($linje=fgets($fp1)){
			$linje=str_replace('%SERVERURL%',$url,$linje);
			fwrite($fp2,$linje);
		}
		fclose($fp1);
		fclose($fp2);
		$soap = new SoapClient($folder.$wsdl);
		return ($soap->logoff("$s_id"));
	}
}

?>
