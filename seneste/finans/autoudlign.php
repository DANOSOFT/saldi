<?php
// -----------------finans/autoudlign.php------------lap 3.2.9--------2012.06.06----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere $$detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------

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

if($_POST[submit]=='Udlign') {
	if ($_POST['udlign']) {
		list($kontonr,$art,$faktnr)=explode(":-:",$_POST['udlign']);
		if ($art && $kontonr) {
			if($_GET['amount']<0) db_modify("update kassekladde set d_type='$art', debet='$kontonr', faktura='$faktnr' where id = $id");
			else db_modify("update kassekladde set k_type='$art', kredit='$kontonr', faktura='$faktnr' where id = $id");
		}
	}
}
print "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
if ($menu=='T') {
	$leftbutton="<a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>Luk</a>";
	$rightbutton="";
	include("../includes/topmenu.php");
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
	$q = db_select("select * from kassekladde where kladde_id=$kladde_id");
	while ($r = db_fetch_array($q)) {
		if ($r['faktura']) {
			$x++;
			$brugt[$x]=$r['faktura'];
		}
	} 
	$x=0;
	$q = db_select("select * from kassekladde where kladde_id=$kladde_id and id > $id order by id");
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
$q = db_select("select openpost.id,openpost.konto_nr,openpost.faktnr,openpost.transdate,openpost.amount,adresser.firmanavn,adresser.art from openpost,adresser where adresser.id=openpost.konto_id and openpost.amount >= '$min' and openpost.amount <= '$max' and openpost.udlignet='0' order by adresser.firmanavn");
# <- 2009.05.04
$x=0;
while ($r = db_fetch_array($q)){
	if (!in_array($r['faktnr'],$brugt)) {
	$x++;
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
		}
	}
#	$r2=db_fetch_array(db_select("select firmanavn,art from adresser where id = $r[konto_id]"));
	($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
	print "<tr bgcolor=\"$linjebg\"><td>$r[transdate]</td><td>$r[firmanavn]</td><td align=right>$r[faktnr]</td>
	<td><input type=radio name=udlign value=\"$r[konto_nr]:-:$r[art]:-:$r[faktnr]\" title='' $afmaerk></td>
</tr>";
}
}
	if ($x==0) print "<meta http-equiv=\"refresh\" content=\"0;URL=autoudlign.php?kladde_id=$kladde_id&id=$id\">";
else {
	print "<tr><td><input type=submit accesskey=\"u\" value=\"Udlign\" name=\"submit\"></td></td>
	<td><input type=submit accesskey=\"u\" value=\"N&aelig;ste\" name=\"next\"></td></tr>";
}
print "</form></tbody></table>";
} # endfunc udlign
print "<script language=\"javascript\">";
if (!$er_afmaerket) print "document.udlign.udlign.focus()";
else print "document.udlign.submit.focus()";
print "</script>";

?>

