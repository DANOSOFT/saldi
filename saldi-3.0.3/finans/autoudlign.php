<?php
// -----------------finans/autoudlign.php------------lap 2.0.9--------2009.09.01----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere $$detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2009 DANOSOFT ApS
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
		list($kontonr,$art,$faktnr)=split(":-:",$_POST['udlign']);
		if ($art && $kontonr) {
			if($_GET['amount']<0) db_modify("update kassekladde set d_type='$art', debet='$kontonr', faktura='$faktnr' where id = $id");
			else db_modify("update kassekladde set k_type='$art', kredit='$kontonr', faktura='$faktnr' where id = $id");
		}
	}
}
print "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund>Autoudligning</td>";
print "<td width=\"10%\" $top_bund><br></td>";
print "</tbody></table>";
print "</td></tr>";

if ($kladde_id)	{
	$x=0;
	$brugt=array();
	$q = db_select("select * from kassekladde where kladde_id=$kladde_id and id > $id order by id");
	while ($r = db_fetch_array($q)) {
		$amount=0;
		if ($r['debet'] && !$r['kredit']) $amount=$r['amount']*1;
		elseif (!$r['debet'] && $r['kredit']) $amount=$r['amount']*-1;
		if ($amount) {
			$x++;
			udlign($kladde_id, $r['id'], $r['beskrivelse'], $amount);
			exit;
		}
	} 
}
print "</td></tr></tbody></table>";
print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";

function udlign($kladde_id, $id, $beskrivelse, $amount) {
global $er_afmaerket;
$kontrol=array();
$kontrol=explode(" ",$beskrivelse);
print "<tr><td><table valign=top><tbody>";
print "<form name=udlign action=autoudlign.php?kladde_id=$kladde_id&id=$id&amount=$amount method=post>";
$tmp=number_format($amount,2,',','.');
print "<tr><td colspan=2><b>$beskrivelse</b></td><td align=right><b>$tmp</b></td></tr>";
print "<tr><td colspan=4><hr></td></tr>";
# -> 2009.05.04
$min=$amount-0.005; 
$max=$amount+0.005;
$q = db_select("select id, konto_id, konto_nr, faktnr, transdate, amount from openpost where amount >= '$min' and amount <= '$max' and udlignet='0' order by konto_id");
# <- 2009.05.04
$x=0;
while ($r = db_fetch_array($q)){
	$x++;
	if (!$er_afmaerket && in_array($r['faktnr'],$kontrol)) {
		$afmaerk='checked';
		$er_afmaerket=1;
	} else $afmaerk='';
	$r2=db_fetch_array(db_select("select firmanavn, art from adresser where id = $r[konto_id]"));
	print "<tr><td>$r[transdate]</td><td>$r2[firmanavn]</td><td align=right>$r[faktnr]</td>
	<td><input type=radio name=udlign value=\"$r[konto_nr]:-:$r2[art]:-:$r[faktnr]\" title='' $afmaerk></td>
</tr>";
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

