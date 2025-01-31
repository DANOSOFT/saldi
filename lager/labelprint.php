<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------/lager/labelprint.php----------------lap 3.9.9---2021-02-04 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2016-2021 saldi.dk aps
// ----------------------------------------------------------------------
// 
// 2014.06.17 Tilføjet pris pr. enhed på etiketter, hvis de er der. PHR - Danosoft. 20140617
// 2014.09.01 Tilføjet opsætning til Cognitive printer - Anvendes hvis det ikke er beskrivelse.
// 2015.09.02 Indsat kontrol for uønsket sql kald.
// 2016.05.05 PHR $salgspris mm blev ikke sat hvis $incl_moms ikke var sat. #20160505
// 2017.01.17 PHR Tilføjet $notes. #20170117
// 2017.06.28 PHR Tilføjet $lokation & $lev_varenr. #20170628
// 2020.04.07 PHR Barcode created if not defined.  #20200407
// 2020.07.02	PHR Added support for more labels.
// 2020-09-15 PHR If mylabel cookie session is written to online
// 2020-11-06 PHR img created if not exist; 20201106
// 2021-02-04 PHR Added brotherTD
// 2021-04-07	PHR Added  '&& $stregkode' as labelprint from creditororder failed with line disabled and and failed with line enabled. 

@session_start();
$s_id=session_id();
$title="Labelprint";
$modulnr=9;
$css="../css/standard.css";
$bg='nix';
$bottom=$diffkto=NULL;
$variant=$variant_type=NULL;
$labelNames = array();

include("../includes/connect.php");
if (isset($_COOKIE['mylabel'])) {
	list($account,$db)=explode("|",$_COOKIE['mylabel']);
	$qtxt = "select * from online where session_id = '$s_id'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		setcookie("mylabel","$account|$db",time()-60,"/");
		$account=NULL;
	} else {
		$qtxt = "insert into online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor)";
		$qtxt.= " values ";
		$qtxt.= "('$s_id','". db_escape_string($account) ."','". db_escape_string($db) ."','". db_escape_string($squser) ."',";
		$qtxt.= "'0',0,'". date('U') ."','0')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
}
include("../includes/online.php");
include("../includes/std_func.php");

$id        = if_isset($_GET['id'])*1;
$labelId   = if_isset($_GET['labelId']);
$img       = if_isset($_GET['src']);
$stregkode = if_isset($_GET['stregkode']);
$varenr    = if_isset($_GET['varenr']);
$account   = if_isset($_GET['account'])*1;
$condition = if_isset($_GET['condition']);
$page      = if_isset($_GET['page']);
$labelName = if_isset($_GET['labelName']);
$printIds  = if_isset($_GET['printIds']);
$print     = strtolower(if_isset($_GET['print']));
$qty       = if_isset($_POST['qty']);
if (!$labelName) $labelName=if_isset($_POST['labelName']);

if (!$labelName) {
	$x=0;
	$qtxt="select labeltype,labelname,labeltext from labels where account_id=0 order by labelname";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
		if ($account) {
			if ($print == $r['labeltype'] && strpos($r['labeltext'],'minpris')) {
				$labelNames[$x]=$r['labelname'];
				$x++;
			}
		} else {
			$labelNames[$x]=$r['labelname'];
			$x++;
		}
	}
	if ($x > 1) {
		$action = "labelprint.php?id=$id&varenr=$varenr&account=$account&condition=$condition&page=$page&printIds=$printIds";
		$action.= "&stregkode=$stregkode&src=$img&labelId=$labelId";
		print "<form action='$action' method='POST'>";
		print "<center><br><br>Vælg label layout<br>";
		print "<select name='labelName' onchange='javascript:this.form.submit()'>";
		print "<option></option>";
		for ($x=0;$x<count($labelNames);$x++) print "<option value='$labelNames[$x]'>$labelNames[$x]</option>";
		print "</select></form>";
		if (isset($_COOKIE['mylabel']) && $_COOKIE['mylabel'] && $account && $db) {
			include ('../includes/connect.php');
			$qtxt = "delete from online where session_id='$s_id' and brugernavn='$account' and db='$db'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		exit;
	} elseif ($labelNames[0]) $labelName = $labelNames[0];
} elseif ((strtolower(substr($labelName,0,9)) == 'brothertd') && !$qty) {
	$action = "labelprint.php?id=$id&varenr=$varenr&account=$account&condition=$condition&page=$page&printIds=$printIds";
	$action.= "&stregkode=$stregkode&src=$img&labelId=$labelId";
	print "<form action='$action' method='POST'>";
	print "<input type='hidden' name='labelName'value='$labelName'>";
	print "<center><br><br>Vælg antal &nbsp;";
	print "<input type=text name='qty' value='1'> &nbsp;";
	print "<input type=submit name='go' value='Udskriv'>";
	for ($x=0;$x<count($labelNames);$x++) print "<option value='$labelNames[$x]'>$labelNames[$x]</option>";
	print "</select></form>";
	if (isset($_COOKIE['mylabel']) && $_COOKIE['mylabel'] && $account && $db) {
		include ('../includes/connect.php');
		$qtxt = "delete from online where session_id='$s_id' and brugernavn='$account' and db='$db'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	exit;
}  

$banned=array('<?','<?php','?>');
$qtxt="select labeltext from labels where labelname='$labelName' and account_id='0'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
if ($r['labeltext']) $txt=$r['labeltext'];
else {
	$r=db_fetch_array(db_select("select box1 from grupper where art='LABEL'",__FILE__ . " linje " . __LINE__));
	$txt=$r['box1'];
}
(strtolower(substr($labelName,0,9)) == 'brothertd')?$brotherTD=1:$brotherTD=0;
$mylabel=0;
if ($brotherTD) {
	$mylabel=1;
} elseif ($printIds) $mylabel=1;
elseif (strpos($txt,'$minpris') || strpos($txt,'$minbeskrivelse') || strpos($txt,'$rows')) $mylabel=1; 
if (strpos($txt,'$kundenr')) {
	if (!$account && !$varenr && $id) {
		$r=db_fetch_array(db_select("select varenr from varer where id = '$id'",__FILE__ . " linje " . __LINE__));
		$varenr = $r['varenr'];
		$account=substr($varenr,5);
	}
	$txt = str_replace('$kundenr',$account,$txt);
}
for ($x=0;$x<count($banned);$x++) {
	if (strpos($txt,$banned[$x])) {
		print "<BODY onLoad=\"JavaScript:alert('Illegal værdi i labeltekst')\">";
		exit;
	}
}
if (!$stregkode && $varenr) $stregkode = $varenr;
if (!$img && $stregkode) $img=barcode($stregkode); #20210407
$filename="../temp/$db/label".rand(100, 999)."html";
if ($mylabel) include("labelprint_includes/newlabel.php");
else include("labelprint_includes/oldlabel.php");
if ($brotherTD) include("labelprint_includes/BrotherTD.php");
else {
	include ($filename);
	print "<body onLoad=\"javascript:window.print();\">\n";
}
if (isset($_COOKIE['mylabel']) && $_COOKIE['mylabel'] && $account && $db) {
	include ('../includes/connect.php');
	$qtxt = "delete from online where session_id='$s_id' and brugernavn='$account' and db='$db'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
print " <br>\n";
print "<BODY onload=\"javascript:window.close()\">\n";
?>
