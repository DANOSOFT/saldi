<?php
// #----------------- soapserver/invoice.php -----ver 3.2.4---- 2011.10.25 ----------
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

function invoice($string) {
	$webservice='1';

	$fp=fopen("../temp/invoice.log","w");
		
	list($s_id,$tmp)=explode(chr(9),$string);
	if (!$s_id) return('1'.chr(9)."Missing session ID");
#	include("../includes/select.php");
	include ("../includes/connect.php");
	include ("../includes/online.php");
	include ("../includes/std_func.php");
	include ("../includes/ordrefunc.php");
	include ("../includes/formfunk.php");

	$linje=NULL;
	$ordre_id=str_replace($s_id,"",$string);
	$ordre_id=str_replace(chr(9),"",$ordre_id);
	$ordre_id=str_replace(chr(10),"",$ordre_id);
	$ordre_id=str_replace(chr(13),"",$ordre_id);
#	$ordre_id=str_replace(" ","",$ordre_id);
	$ordre_id=strtolower($ordre_id);
	list($table,$tmp)=explode("set",$ordre_id,2);
	$table=trim($table);
	$r=db_fetch_array(db_select("select momssats from ordrer where id = '$ordre_id'",__FILE__ . " linje " . __LINE__));
	$momssats=$r['momssats']*1;
	$x=0;
	$ordresum=0;
	$momssum=0;
	$kostsum=0;
	$momsdiff=0;
	$q=db_select("select * from ordrelinjer where ordre_id = '$ordre_id'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['vare_id']) {
			$linjesum=$r['pris']*$r['antal']-($r['pris']*$r['antal']*$r['rabat']/100);
			$ordresum+=$linjesum;
			$linjemoms=$linjesum*$r['momssats']/100;
			$momssum+=$linjemoms;
			if ($r['momssats']!=$momssats || $r['momsfri']) $momsdiff=1;
			$kostsum+=$r['kostpris']*$r['antal'];
		}
		$ordresum=afrund($ordresum,2);
		if (!$momsdiff) $momssum=$ordresum*$momssats/100;
		$momssum=afrund($momssum,2);
	}	
	
	transaktion('begin');
	$linje="update ordrer set status = '2',levdate = ordredate,fakturadate = ordredate,sum='$ordresum',moms='$momssum',udskriv_til='email',mail_fakt='on',momssats='$momssats',kostpris='$kostsum',projekt='' where id = '$ordre_id'";
	fwrite($fp,$linje."\n");
	$svar=db_modify($linje,__FILE__ . " linje " . __LINE__);
	list($fejl,$svar)=explode(chr(9),$svar);
	if ($fejl) return($fejl.chr(9).$svar);
	$linje="update ordrelinjer set projekt = '' where ordre_id = '$ordre_id'";
	fwrite($fp,$linje."\n");
	$svar=db_modify($linje,__FILE__ . " linje " . __LINE__);
	list($fejl,$svar)=explode(chr(9),$svar);
	if ($fejl) return($fejl.chr(9).$svar);
	$linje="update ordrelinjer set leveres = antal where ordre_id = '$ordre_id' and vare_id>'0'";
	fwrite($fp,$linje."\n");
	$svar=db_modify($linje,__FILE__ . " linje " . __LINE__);
	list($fejl,$svar)=explode(chr(9),$svar);
	if ($fejl) return($fejl.chr(9).$svar);
	$linje="levering($ordre_id,'on','','on')";
	fwrite($fp,$linje."\n");
	$svar=levering($ordre_id,'on','','on');
	if ($svar!='OK') return('1'.chr(9).$svar);
	$linje="update ordrelinjer set leveret = antal,leveres='0' where ordre_id = '$ordre_id' and vare_id>'0'";
	fwrite($fp,$linje."\n");
	$svar=db_modify($linje,__FILE__ . " linje " . __LINE__);
	$linje="bogfor($ordre_id,'on')";
	fwrite($fp,$linje."\n");
	$svar=bogfor($ordre_id,'on');
	list($fejl,$svar)=explode(chr(9),$svar);
	fwrite($fp,$fejl." ".$svar."\n");
	if ($fejl!='OK') {
		$linje="$fejl";
#		fwrite($fp,$linje."\n");
		return('1'.chr(9).$fejl);
	} else transaktion('commit');
	$linje="formularprint($ordre_id,'4','1',$charset,'email')";
	fwrite($fp,$linje."\n");
	$svar=formularprint($ordre_id,'4','1',$charset,'email');
	fwrite($fp,$linje."Svar ".$svar."\n");
	if ($svar && $svar!='OK') return('1'.chr(9).$svar);
	else {
		fclose($fp);
		return('0'.chr(9).$ordre_id);
	}
}
$server = new SoapServer("invoice.wsdl");
$server->addFunction("invoice");
$server->handle();

?>