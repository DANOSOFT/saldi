<?php
// ------------- lager/happyhour.php ---------- ver 3.6.1----2016.01.06-------
// LICENS
//		if ($bordnr) $bordnr=$_COOKIE['saldi_bordnr'];

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
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
@session_start();
$s_id=session_id();

$modulnr=9;
$title="HappyHour";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$submit=if_isset($_POST['submit']);
$vare_id=if_isset($_GET['vare_id']);

if (isset($_POST['salgspris']) && $_POST['salgspris']) {
	$id=$_POST['id'];
	$startdato=$_POST['startdato'];
	$slutdato=$_POST['slutdato'];
	$starttid=$_POST['starttid'];
	$sluttid=$_POST['sluttid'];
	$salgspris=$_POST['salgspris'];
	$kostpris=$_POST['kostpris'];
	$ugedag=$_POST['ugedag'];
	$slet=$_POST['slet'];

	for ($x=0;$x<=count($id)+1;$x++) {
		if ($slet[$x] && $id[$x]) db_modify("delete from varetilbud where id=$id[$x]",__FILE__ . " linje " . __LINE__);
		else {
			$startdag[$x]=strtotime(usdate($startdato[$x]));
			$slutdag[$x]=strtotime(usdate($slutdato[$x]));
			if (!$starttid[$x]) $starttid[$x]="00:00:00";
			else $starttid[$x]=tjektid($starttid[$x]);
			if (!$sluttid[$x]) $sluttid[$x]="24:00:00";
			else $sluttid[$x]=tjektid($sluttid[$x]);
			$salgspris[$x]*=0.8;
			$kostpris[$x]*=1;
			if ($id[$x]) {
				$qtxt="update varetilbud set";
				$qtxt.=" startdag='$startdag[$x]',slutdag='$slutdag[$x]',starttid='$starttid[$x]',sluttid='$sluttid[$x]',ugedag='$ugedag[$x]',";
				$qtxt.="salgspris=".usdecimal($salgspris[$x]).",kostpris=".usdecimal($kostpris[$x]);
				$qtxt.=" where id='$id[$x]'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			} elseif ($startdag[$x] && $slutdag[$x] && $starttid[$x] && $sluttid[$x] && $ugedag[$x] && $salgspris[$x]) {
				$qtxt="insert into varetilbud";
				$qtxt.=" (vare_id,startdag,slutdag,starttid,sluttid,ugedag,salgspris,kostpris)";
				$qtxt.=" values ";
				$qtxt.="('$vare_id','$startdag[$x]','$slutdag[$x]','$starttid[$x]','$sluttid[$x]','$ugedag[$x]','".usdecimal($salgspris[$x])."',";
				$qtxt.="'".usdecimal($kostpris[$x])."')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
	}
}


$r=db_fetch_array(db_select("select beskrivelse, salgspris, kostpris from varer where id = $vare_id",__FILE__ . " linje " . __LINE__));
$varenavn=$r['beskrivelse'];
$varepris=$r['salgspris'];
$varekost=$r['kostpris'];

$x=0;
$id=array();
$startdato[$x]=array();
$slutdato[$x]=array();
$starttid[$x]=array();
$sluttid[$x]=array();
$salgspris[$x]=array();
$kostpris[$x]=array();
$ugedag[$x]=array();

$qtxt="select * from varetilbud where vare_id='$vare_id' order by startdag,ugedag,starttid";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while($r=db_fetch_array($q)) {
	$id[$x]=$r['id'];
	$startdato[$x]=date("d-m-Y",$r['startdag']);
	$slutdato[$x]=date("d-m-Y",$r['slutdag']);
	$starttid[$x]=$r['starttid'];
	$sluttid[$x]=$r['sluttid'];
	$salgspris[$x]=dkdecimal($r['salgspris']/0.8);
	$kostpris[$x]=dkdecimal($r['kostpris']);
	$ugedag[$x]=$r['ugedag'];
	$x++;
}
print "<form name=\"happyhour\" action=\"happyhour.php?vare_id=$vare_id\" method=\"POST\">";
print "<div align=\"center\">\n";
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>\n";
print "<td width=\"10%\" $top_bund><a href=\"varekort.php?id=$vare_id\" accesskey=\"L\">Luk</a></td>\n";
print "<td width=\"80%\" $top_bund>$title</td>\n";
print "<td width=\"10%\" $top_bund><br></td>\n";
print "</tbody></table>\n";
print "</td></tr>\n";
print "<tr><td align=\"center\"><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td colspan=\"2\"></td><td colspan=\"2\" align=\"center\"><b><big>$varenavn</big><b></td></tr>";
print "<tr><td colspan=\"2\"></td><td>Salgspris</td><td align=\"right\">".dkdecimal($varepris,2)."</td></tr>";
print "<tr><td colspan=\"2\"></td><td>Kostpris</td><td align=\"right\">".dkdecimal($varekost,2)."</td></tr>";
print "<tr><td colspan=\"7\"><hr></td></tr>";
print "<tr><td align=\"center\">Aktiv fra</td><td align=\"center\">Aktiv til</td><td align=\"center\">Fra kl</td><td align=\"center\">Til kl.</td><td align=\"center\">Tilbudspris<br>(Incl. moms)</td><td align=\"center\">Kostpris<br>(Ex. moms)</td><td align=\"center\">Ugedag</td><td align=\"center\">Slet</td></tr>";

for ($x=0;$x<count($id);$x++){
	print "<tr>";
	print "<td>";
	print "<input type=\"hidden\" name=\"id[$x]\" value=\"$id[$x]\">";
	print "<input type=\"text\" style=\"width:90px\" name=\"startdato[$x]\" value=\"$startdato[$x]\">";
	print "</td>";
	print "<td><input type=\"text\" style=\"width:90px\" name=\"slutdato[$x]\" value=\"$slutdato[$x]\"></td>";
	print "<td><input type=\"text\" style=\"width:60px\" name=\"starttid[$x]\" value=\"$starttid[$x]\"></td>";
	print "<td><input type=\"text\" style=\"width:60px\" name=\"sluttid[$x]\" value=\"$sluttid[$x]\"></td>";
	print "<td><input type=\"text\" style=\"width:80px;text-align:right\" name=\"salgspris[$x]\" value=\"$salgspris[$x]\"></td>";
	print "<td><input type=\"text\" style=\"width:80px;text-align:right\" name=\"kostpris[$x]\" value=\"$kostpris[$x]\"></td>";
	print "<td><input type=\"text\" style=\"width:50px;text-align:right\" name=\"ugedag[$x]\" value=\"$ugedag[$x]\"></td>";
	print "<td><input type=\"checkbox\" name=\"slet[$x]\"></td>";
	print "</tr>";
}
	$x++;
	print "<tr>";
	print "<td>";
	print "<input type=\"text\" style=\"width:90px\" name=\"startdato[$x]\" value=\"\">";
	print "</td>";
	print "<td><input type=\"text\" style=\"width:90px\" name=\"slutdato[$x]\" value=\"\"></td>";
	print "<td><input type=\"text\" style=\"width:60px\" name=\"starttid[$x]\" value=\"\"></td>";
	print "<td><input type=\"text\" style=\"width:60px\" name=\"sluttid[$x]\" value=\"\"></td>";
	print "<td><input type=\"text\" style=\"width:80px;text-align:right\" name=\"salgspris[$x]\" value=\"\"</td>";
	print "<td><input type=\"text\" style=\"width:80px;text-align:right\" name=\"kostpris[$x]\" value=\"\"></td>";
	print "<td><input type=\"text\" style=\"width:50px;text-align:right\" name=\"ugedag[$x]\" value=\"\"></td>";
	print "</tr>";
print "<tr><td colspan=\"8\" align=\"center\"><br></td></tr>";
print "<tr><td colspan=\"8\" align=\"center\"><input type=\"submit\" name=\"opdater\" value=\"Opdater\"></td></tr>";
print "</td></tr></tbody></table>\n";
print "</td></tr></tbody></table>\n";
print "</form>";

function tjektid($tid){
	list($h,$i,$s)=explode(":",$tid);
	$h*=1;
	if ($h>24) $h=24;
	$i*=1;
	if ($i>59) $i=59;
	$h*=1;
	if ($s>59) $s=59;
	if ($h==24) {
		$i=0;
		$s=0;
	}
	$tid="$h:$i:$s";
	return($tid);
}
?>