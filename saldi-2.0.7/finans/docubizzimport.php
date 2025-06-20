<?php

// -----------finans/docubizzimport.php--- lap 2.0.3 ---- 2008.11.26 -----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$modulnr=8;
$title="Ducubizz - import";
$header="nix";
$bg="nix";

include("../includes/var_def.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/db_query.php");

$kladde_id=if_isset($_GET['kladde_id']);
if (!$kladde_id) {
	print "<body onLoad=\"javascript:alert('Kladde ID ikke registreret')\">";
	exit;
}
$datotid=date("Ymd_his");

if ($r=db_fetch_array(db_select("select box1, box2, box3 from grupper where art='DocBiz'",__FILE__ . " linje " . __LINE__))) {
	$ftpnavn=$r['box1'];
	$ftpbruger=$r['box2'];
	$ftpkode=$r['box3'];
} else {
	print "<body onLoad=\"javascript:alert('FTP information ikke fundet')\">";
}
		
$filnavn="../temp/$datotid.sh";
if ($fp=fopen("$filnavn","w")) {
	fwrite ($fp, "cd ../temp\n");
	fwrite ($fp, "#!/bin/sh\n");
	fwrite ($fp, "echo rename DocuBizz2Erp.csv DocuBizz2Erp_$datotid.csv >> $datotid\n");
	fwrite ($fp, "echo get DocuBizz2Erp_$datotid.csv >> $datotid\n");
	fwrite ($fp, "echo bye >> $datotid\n");
	fwrite ($fp, "/usr/bin/ncftp ftp://$ftpbruger:$ftpkode@$ftpnavn < $datotid > ftplog.txt\n");
	fwrite ($fp, "rm $datotid\n");
	fclose ($fp);
}
#system("cd ../temp\nchmod a+x $datotid.sh\n./$datotid.sh\n");
system("cd ../temp\nchmod a+x $datotid.sh\n./$datotid.sh\nrm $datotid.sh\nrm $datotid\n");

$r=db_fetch_array(db_select("select id from adresser where art='S'",__FILE__ . " linje " . __LINE__));
$konto_id=$r['id']*1;


$filnavn="../temp/DocuBizz2Erp_$datotid.csv";
if ($fp=fopen("$filnavn","r")) {
	while ($linje=trim(fgets($fp))) {
		$x++;
		$projekt=NULL;
		$extra=NULL;
		list($felt1,$tmp)=split(chr(9), $linje);
		if ($felt1=='"H"') {
			list($felt1,$k_kontonr,$bilag,$faktnr,$faktdate,$betaldate,$transdate,$belob,$moms,$art,$valuta,$betal_id,$beskrivelse,$tmp,$tmp)=split(chr(9), $linje);
			$k_kontonr=str_replace("\"","",$k_kontonr);
			$faktnr=str_replace("\"","",$faktnr);
			$beskrivelse=str_replace("\"","",$beskrivelse);
			$valuta=str_replace("\"","",$valuta);
			$betal_id=str_replace("\"","",$betal_id);
			$faktdato=substr($faktdate,4,2).substr($faktdate,2,2).substr($faktdate,0,2);
			$faktdate=usdate($faktdato);
			$betaldato=substr($betaldate,4,2).substr($betaldate,2,2).substr($betaldate,0,2);
			$forfaldsdate=usdate($betaldato);
			$amount=usdecimal($belob);
			if ($art=='"K"') $amount=$amount*-1;
			$r=db_fetch_array(db_select("select kodenr from grupper where art='VK' and box1='$valuta'",__FILE__ . " linje " . __LINE__));
			$valutakode=$r['kodenr']*1;
			db_modify("insert into kassekladde(bilag,transdate,beskrivelse,k_type,faktura,amount,kladde_id,kredit,valuta,forfaldsdate,betal_id) values ('$bilag','$faktdate','$beskrivelse','K','$faktnr','$amount','$kladde_id','$k_kontonr','$valutakode','$forfaldsdate','$betal_id')",__FILE__ . " linje " . __LINE__);	
		} elseif ($felt1=='"K"') {
			list($felt1,$kontonr,$projekt,$ansat,$beskrivelse,$belob,$moms)=split(chr(9), $linje);
			$kontonr=str_replace("\"","",$kontonr);
			$projekt=str_replace("\"","",$projekt);
			$beskrivelse=str_replace("\"","",$beskrivelse);
			if ($projekt) {
				$projekt=$projekt*1;
				$extra=",projekt";
				$projekt=",'".$projekt."'";
			} else $projekt='';
			$ansat=str_replace("\"","",$ansat);
			$moms=str_replace("\"","",$moms);
			$amount=usdecimal($belob);
			if ($moms=='E1') $momsfri='';
			elseif ($moms) {
				$amount=$amount*1.25;
				$momsfri='';
			} else $momsfri='on';
			$r=db_fetch_array(db_select("select id from ansatte where konto_id='$konto_id' and initialer='$ansat'",__FILE__ . " linje " . __LINE__));
			$ansat_id=$r['id']*1;
			db_modify("insert into kassekladde(bilag,transdate,beskrivelse,faktura,amount,kladde_id,momsfri,debet,valuta,ansat $extra) values ('$bilag','$faktdate','$beskrivelse','$faktnr','$amount','$kladde_id','$momsfri','$kontonr','$valutakode','$ansat_id' $projekt)",__FILE__ . " linje " . __LINE__);	
		}
	}
	print "<body onLoad=\"javascript:alert('DocuBizz data overf&oslash;rt')\">";
} else print "<body onLoad=\"javascript:alert('Ingen data til overf&oslash;rsel')\">";
print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";
?>
