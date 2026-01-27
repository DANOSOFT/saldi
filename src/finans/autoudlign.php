<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -----------------finans/autoudlign.php------------lap 3.9.5--------2020.11.07----------
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
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------
// 20170607 PHR genkender nu også kontonr. Søg 20170707
// 2018.12.20 MSC - Rettet isset fejl og rettet topmenu design til
// 2019.03.12 MSC - Rettet db argument fejl og isset fejl
// 2019.03.13 PHR - Rettet db argument fejl 
// 2020.07.10 PHR - Added recognition af payment ID ($betalings_id) 
// 2020.08.20 PHR - Added recognition of outgoing payments from Cultura Sparebank, Norway 20200820
// 2020.09.11 PHR - Added query without Payment ID if no marching order found. 20200911 
// 2020.09.14 PHR - Added search for account if 'afr:' in text
// 2020.11.07 PHR - Added controle for duplicates when displaying matching openposts 'distinct(openpost.id)'


@session_start();
$s_id=session_id();
$title="Autoudligning";
$er_afmaerket=0;
$debet='';
$kredit='';
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$kladde_id = if_isset($_GET['kladde_id']);
$id = if_isset($_GET['id'])*1;

if (isset($_POST['submit']) && $_POST['submit']=='Udlign') {
		list($kontonr,$art,$faktnr)=explode(":-:",$_POST['udlign']);
		if ($art && $kontonr) {
			if($_GET['amount']<0) $qtxt="update kassekladde set d_type='$art', debet='$kontonr', faktura='$faktnr' where id = $id";
			else $qtxt="update kassekladde set k_type='$art', kredit='$kontonr', faktura='$faktnr' where id = $id";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
}
print "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
if ($menu=='T') {
	$leftbutton="<a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>Luk</a>";
	$rightbutton="";
	include("../includes/top_header.php");
	include("../includes/top_menu.php");
} elseif ($menu=='S') {
	include("../includes/sidemenu.php");
} else {
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund>Autoudligning</td>";
	print "<td width=\"10%\" $top_bund><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
}
if ($kladde_id)	{
	$x=0;
	$brugt=array();
	$q = db_select("select * from kassekladde where kladde_id=$kladde_id",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($r['faktura']) {
			$x++;
			$brugt[$x]=$r['faktura'];
		}
	} 
	$x=0;
	$q = db_select("select * from kassekladde where kladde_id=$kladde_id and id > $id order by id",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$amount=0;
		if ($r['debet'] && !$r['kredit']) $amount=$r['amount']*1;
		elseif (!$r['debet'] && $r['kredit']) $amount=$r['amount']*-1;
		if ($amount) {
			$x++;
			udlign($kladde_id,$r['id'],$r['transdate'],$r['beskrivelse'],$amount);
			exit;
		}
	} 
}
print "</td></tr></tbody></table>";
print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";

function udlign($kladde_id,$id,$transdate,$beskrivelse,$amount) {
global $er_afmaerket;
global $bgcolor5;
global $bgcolor;
global $brugt;

$linjebg=$bgcolor;
$kontrol=array();
$kontrol=explode(" ",$beskrivelse);
print "<tr><td><table valign=top><tbody>";
print "<form name=udlign action=autoudlign.php?kladde_id=$kladde_id&id=$id&amount=$amount method=post>";
$tmp=number_format($amount,2,',','.');
print "<tr><td><b>$transdate</b></td><td><b>$beskrivelse</b></td><td align=right><b>$tmp</b></td></tr>";
print "<tr><td colspan=\"4\"	><hr></td></tr>";
# -> 2009.05.04
$min=$amount-0.005; 
$max=$amount+0.005;


$qtxt = "select distinct(openpost.id),openpost.konto_nr,openpost.faktnr,openpost.transdate,openpost.amount,adresser.firmanavn,";
$qtxt.= "adresser.art,adresser.bank_reg,adresser.bank_konto,ordrer.betalings_id";
$qtxt.= " from openpost,adresser,ordrer ";
$qtxt.= "where adresser.id=openpost.konto_id ";
$qtxt.= "and openpost.amount >= '$min' and openpost.amount <= '$max' and openpost.udlignet='0' ";
$qtxt.= "and ordrer.konto_id=openpost.konto_id and ordrer.fakturanr=openpost.faktnr ";
$qtxt.= "order by adresser.firmanavn";
if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { #20200911
	$qtxt = "select openpost.id,openpost.konto_nr,openpost.faktnr,openpost.transdate,openpost.amount,adresser.firmanavn,adresser.art,";
	$qtxt.= "adresser.bank_reg,adresser.bank_konto";
	$qtxt.= " from openpost,adresser ";
	$qtxt.= "where adresser.id=openpost.konto_id ";
	$qtxt.= "and openpost.amount >= '$min' and openpost.amount <= '$max' and openpost.udlignet='0' ";
	$qtxt.= "order by adresser.firmanavn";
}
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
# <- 2009.05.04
$x=0;
while ($r = db_fetch_array($q)){
	$a=$r['amount'];
	$u=$r['udlignet']*1;
#		echo "!$u && ($min < 0 && $a >= $min && $a <= $max || $min > 0 && $a <= $min && $a >= $max)<br>";
#	if (!$u && ($min < 0 && $a >= $min && $a <= $max || $min > 0 && $a <= $min && $a >= $max) ){
			if (($r['faktnr'] && !in_array($r['faktnr'],$brugt)) || !$r['faktnr']) {
			if (!$er_afmaerket && in_array($r['faktnr'],$kontrol)) {
				$afmaerk='checked';
				$er_afmaerket=1;
			} else {
				$afmaerk='';
				$tmp='';
				for ($z=0;$z<=strlen($beskrivelse);$z++) {
					if (is_numeric(substr($beskrivelse,$z,1))) $tmp.=substr($beskrivelse,$z,1);
					else $tmp='';
					if ($tmp && $tmp==$r['faktnr']) $afmaerk='checked';
					elseif ($tmp && $tmp==$r['betalings_id']) $afmaerk='checked'; #20170707
					elseif ($tmp && $tmp==$r['konto_nr']) $afmaerk='checked'; #20170707
				} 
			}
			($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
			print "<tr bgcolor=\"$linjebg\">";
			print "<td>$r[transdate]</td>";
			print "<td>$r[konto_nr] - $r[firmanavn]</td>";
			print "<td align=right>$r[faktnr]</td>";
			print "<td align=right>$r[betalings_id]</td>";
			print "<td><input type=radio name=udlign value=\"$r[konto_nr]:-:$r[art]:-:$r[faktnr]\" title='' $afmaerk></td>";
			print "</tr>";
			$x++;
		}
}
if ($x==0) { #20200820
	$qtxt = "select * from adresser where bank_konto != '' order by firmanavn";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)){
		$bank=trim($r['bank_reg'].$r['bank_konto']);
		if ($bank && strpos($beskrivelse,$bank) && !$x) {
			$x++;
			if (!$er_afmaerket) {
					$afmaerk='checked';
					$er_afmaerket=1;
			} else {
				$afmaerk='';
			}
			($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
			print "<tr bgcolor=\"$linjebg\">";
			print "<td>$r[kontonr] - $r[firmanavn]</td>";
			print "<td align=right>$bank</td>";
			print "<td><input type=radio name=udlign value=\"$r[kontonr]:-:$r[art]:-:\" title='' $afmaerk></td>";
			print "</tr>";
			$tmp=$bank;
		}
	}
}
if ($x==0) print "<meta http-equiv=\"refresh\" content=\"0;URL=autoudlign.php?kladde_id=$kladde_id&id=$id\">";
else {
	print "<tr><td><input type=submit accesskey=\"u\" value=\"Udlign\" name=\"submit\"></td></td>
	<td><input type=submit accesskey=\"n\" value=\"N&aelig;ste\" name=\"next\"></td></tr>";
}
print "</form></tbody></table>";
} # endfunc udlign
print "<script language=\"javascript\">";
if (!$er_afmaerket) print "document.udlign.udlign.focus()";
else print "document.udlign.submit.focus()";
print "</script>";

?>

