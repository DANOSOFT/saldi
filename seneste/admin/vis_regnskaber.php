<?php
@session_start();
$s_id=session_id();

// --------/admin/vis_regnskaber.php-----patch 3.2.9------2012.05.15--------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------

$css="../css/standard.css";
$title="vis regnskaber";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$rediger=if_isset($_GET['rediger']);
$beregn=if_isset($_GET['beregn']);
$sort=if_isset($_GET['sort']);
$sort2=if_isset($_GET['sort2']);
$desc=if_isset($_GET['desc']);
$modulnr=102;

if ($db != $sqdb) {
	print "<BODY onLoad=\"javascript:alert('Hmm du har vist ikke noget at g&oslash;re her! Dit IP nummer, brugernavn og regnskab er registreret!')\">";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">";
	exit;
}
if (isset($_POST['submit'])) {
	$rediger="1";
	$db_antal=if_isset($_POST['db_antal']);
	$id=if_isset($_POST['id']);
	$gl_brugerantal=if_isset($_POST['gl_brugerantal']);
	$gl_posteringer=if_isset($_POST['gl_posteringer']);
	$brugerantal=if_isset($_POST['brugerantal']);
	$posteringer=if_isset($_POST['posteringer']);
	$gl_lukket=if_isset($_POST['gl_lukket']);
	$lukket=if_isset($_POST['lukket']);
	$gl_lukkes=if_isset($_POST['gl_lukkes']);
	$lukkes=if_isset($_POST['lukkes']);
	$gl_betalt_til=if_isset($_POST['gl_betalt_til']);
	$betalt_til=if_isset($_POST['betalt_til']);
	$gl_logintekst=if_isset($_POST['gl_logintekst']);
	$logintekst=if_isset($_POST['logintekst']);


	for ($x=1;$x<=$db_antal; $x++) {
#		if (!isset($gl_lukket[$x])) echo "HMM";
		if (!isset($lukket[$x])) $lukket[$x]=NULL;
		if (!$lukkes[$x]) $lukkes[$x]="2099-12-31"; 
		else $lukkes[$x]=usdate($lukkes[$x]);
		if (!$betalt_til[$x]) $betalt_til[$x]="2099-12-31"; 
		else $betalt_til[$x]=usdate($betalt_til[$x]);
		if (
				$gl_brugerantal[$x]!=$brugerantal[$x] ||
				$gl_posteringer[$x]!=$posteringer[$x] ||
				$gl_lukket[$x]!=$lukket[$x] ||
			 	$gl_lukkes[$x]!=$lukkes[$x] ||
				$gl_betalt_til[$x]!=$betalt_til[$x] ||
				$gl_logintekst[$x]!=$logintekst[$x]
			 ){
			if ($saldiregnskab) $modify="update regnskab set brugerantal='$brugerantal[$x]',posteringer='$posteringer[$x]',lukket='$lukket[$x]',lukkes='$lukkes[$x]',betalt_til='$betalt_til[$x]',logintekst='$logintekst[$x]' where id = '$id[$x]'";
			else $modify="update regnskab set	brugerantal='$brugerantal[$x]',posteringer='$posteringer[$x]',lukket='$lukket[$x]'where id = '$id[$x]'";
			if ($id[$x]) db_modify($modify,__FILE__ . " linje " . __LINE__);
		}
	}
}

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\" height=\"25\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><a href=../index/admin_menu.php accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund align=\"center\">Vis regnskaber</td>";
if ($rediger) print "<td width=\"10%\" $top_bund align = \"right\"><a href=vis_regnskaber.php accesskey=F>Vis	</a></td>";
else print "<td width=\"10%\" $top_bund align = \"right\"><a href=vis_regnskaber.php?rediger=yes accesskey=R>Rediger</a></td>";
print "</tbody></table>";
print "</td></tr>";
print "<td align = center valign = center>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";

$id=array(); $regnskab=array(); $db_navn=array();

if (!$sort) $sort='regnskab';
if (!$sort2) $sort2='id';
if ($sort==$sort2) {
	if (!$desc) {
		$order="order by $sort desc";
		$desc='on';
	} else {
		$order="order by $sort";
		$desc='';
	}
} else {
	$order="order by $sort,$sort2";
	$desc='';
}
print "<tr><td><b><a href=vis_regnskaber.php?sort=id&sort2=$sort&desc=$desc&rediger=$rediger>id</a></b></td>
	<td><b><a href=vis_regnskaber.php?sort=regnskab&sort2=$sort&desc=$desc&rediger=$rediger>Regnskab</a></b></td>
	<td><a href=vis_regnskaber.php?sort=brugerantal&sort2=$sort&desc=$desc&rediger=$rediger>brugerantal</a></td>
	<td><a href=vis_regnskaber.php?sort=posteringer&sort2=$sort&desc=$desc&rediger=$rediger>Posteringer</a></td>
	<td><a href=vis_regnskaber.php?sort=posteret&sort2=$sort&desc=$desc&rediger=$rediger>Posteret</a></td>
	<td><a href=vis_regnskaber.php?sort=sidst&sort2=$sort&desc=$desc&rediger=$rediger>Sidst</a></td>
	<td><a href=vis_regnskaber.php?sort=lukket&sort2=$sort&desc=$desc&rediger=$rediger>Lukket</a></td>";
if ($saldiregnskab) {
	print "<td><a href=vis_regnskaber.php?sort=lukkes&sort2=$sort&desc=$desc&rediger=$rediger>Lukkes</a></td>
		<td><a href=vis_regnskaber.php?sort=betalt_til&sort2=$sort&desc=$desc&rediger=$rediger>Betalt til</a></td>
		<td><a href=vis_regnskaber.php?sort=logintekst&sort2=$sort&desc=$desc&rediger=$rediger>Logintekst</a></td>";
}
print "</tr>";

$q = db_select("select * from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
$r = db_fetch_array($q);
list($admin,$oprette,$slette,$tmp)=explode(",",$r['rettigheder'],4);
$adgang_til=explode(",",$tmp);
$x=0;
$q=db_select("select * from regnskab where db != '$sqdb' $order",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($admin || in_array($r['id'],$adgang_til)) {
		$x++;
		$id[$x]=$r['id'];
		$regnskab[$x]=$r['regnskab'];
		$db_navn[$x]=$r['db'];
		$posteringer[$x]=$r['posteringer']*1;
		$posteret[$x]=$r['posteret']*1;
		$brugerantal[$x]=$r['brugerantal']*1;
		$sidst[$x]=$r['sidst'];
#		$oprettet[$x]=date("d-m-Y",$r['oprettet']);
		$lukket[$x]=if_isset($r['lukket']);
		$lukkes[$x]=if_isset($r['lukkes']);
		$betalt_til[$x]=if_isset($r['betalt_til']);
		$logintekst[$x]=if_isset($r['logintekst']);
		if($lukkes[$x]) $lukkes[$x]=dkdato($lukkes[$x]);
		if($betalt_til[$x]) $betalt_til[$x]=dkdato($betalt_til[$x]);
	}
}
$antal=$x;
if ($beregn) {
	$y=date("Y")-1;
	$m=date("m");
	$d=date("d");
	$dd=$y."-".$m."-".$d;
	for ($x=1;$x<=$antal;$x++) {
		db_connect ("$sqhost", "$squser", "$sqpass", "$db_navn[$x]", __FILE__ . " linje " . __LINE__);
#		db_connect ("host=$sqhost dbname=$db_navn user=$squser password=$sqpass");
		$r=db_fetch_array(db_select("select count(id) as transantal from transaktioner where logdate >= '$dd'",__FILE__ . " linje " . __LINE__));
		$posteringer[$x]=$r['transantal']*1;
		if ($r=db_fetch_array(db_select("select max(logdate) as logdate from transaktioner",__FILE__ . " linje " . __LINE__))) {
#echo "Logdate ".$r['logdate']."<br>";
			$sidst[$x]=strtotime($r['logdate']);
		} #else 
	}
	include("../includes/connect.php");
}
if ($rediger)	print "<form name=regnskaber action=vis_regnskaber.php method=post>";
for ($x=1;$x<=$antal;$x++) {
		if ($rediger) {
			print "<input type=hidden name=\"id[$x]\" value=\"$id[$x]\">";
			print "<input type=hidden name=\"gl_lukket[$x]\" value=\"$lukket[$x]\">";
			print "<input type=hidden name=\"gl_lukkes[$x]\" value=\"$lukkes[$x]\">";
			print "<input type=hidden name=\"gl_brugerantal[$x]\" value=\"$brugerantal[$x]\">";
			print "<input type=hidden name=\"gl_posteringer[$x]\" value=\"$posteringer[$x]\">";
			print "<input type=hidden name=\"gl_betalt_til[$x]\" value=\"$betalt_til[$x]\">";
			print "<input type=hidden name=\"gl_logintekst[$x]\" value=\"$logintekst[$x]\">";
			print "<tr><td align=\"right\"> $id[$x]</td><td><a href=aaben_regnskab.php?db_id=$id[$x]>$regnskab[$x]</a></td>";
			print "<td><input type=text size=\"5\" style=\"text-align:right\" name=\"brugerantal[$x]\" value=\"$brugerantal[$x]\"></td>";
			print "<td><input type=text size=\"5\" style=\"text-align:right\" name=\"posteringer[$x]\" value=\"$posteringer[$x]\"</td>";
			print "<td align=\"right\">$posteret[$x]</td>";
			print "<td align=\"right\">".date("d-m-Y",$sidst[$x])."</td>";
			if ($lukket[$x]) $lukket[$x]="checked";
			print "<td align=center><input type=checkbox name=lukket[$x] $lukket[$x]></td>";
			if ($saldiregnskab) {
				print "<td><input type=text size='8' style=\"text-align:right\" name=\"lukkes[$x]\" value=\"$lukkes[$x]\"</td>";
				print "<td><input type=text size='8' style=\"text-align:right\" name=\"betalt_til[$x]\" value=\"$betalt_til[$x]\"</td>";
				print "<td><input type=text size='25' style=\"text-align:right\" name=\"logintekst[$x]\" value=\"$logintekst[$x]\"</td>";
			}
			print "</tr>";
		} else {
#				if ($admin || in_array($r['id'],$adgang_til)) {
#					if ($beregn) echo "update regnskab set posteret='$posteringer[$x]' sidst='$sidst[$x]' where id='$id[$x]'<br>";
#cho "update regnskab set posteret='$posteringer[$x]',sidst='$sidst[$x]' where id='$id[$x]'<br>";
					if ($beregn) db_modify("update regnskab set posteret='$posteringer[$x]',sidst='$sidst[$x]' where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
					print "<tr><td align=\"right\"> $id[$x]</td><td><a href=aaben_regnskab.php?db_id=$id[$x]>$regnskab[$x]</a></td>";
					print "<td>$brugerantal[$x]<br></td>";
					print "<td>$posteringer[$x]<br></td>";
					print "<td align=\"right\">$posteret[$x]<br></td>";
					print "<td align=\"right\">".date("d-m-Y",$sidst[$x])."<br></td>";
					if ($lukket[$x]) $lukket[$x]="&#10004;";
					print "<td align=center>$lukket[$x]<br></td>";
					if ($saldiregnskab) {
						print "<td align=\"right\">$betalt_til[$x]<br></td>";
// 						print "<td align=\"right\">$lukkes[$x]<br></td>";
						print "<td align=\"right\">$logintekst[$x]<br></td>";
					}
					print "</tr>";
#				}
			}
			print "<input type=\"hidden\" name=\"db_antal\" value=\"$x\">";
#		}
#	}
}
if ($rediger) {
	if ($saldiregnskab) $colspan=10;
	else $colspan=7;
	print "<input type=hidden name=\"db_antal\" value=\"$x\">";
	print "<tr><td colspan=\"$colspan\" align=\"center\"><input type=\"submit\" value=\"Opdater\" name=\"submit\"></td></tr>";
	print "</form></tbody></table>";
} else {
	print "</tbody></table>";
	print "<a href=\"vis_regnskaber.php?beregn=1\">Genberegn posteringer</a>";
}
?>
</body></html>
