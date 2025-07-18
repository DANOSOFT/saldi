<?php
//                         ___   _   _   __  _
//                        / __| / \ | | |  \| |
//                        \__ \/ _ \| |_| | | |
//                        |___/_/ \_|___|__/|_|
//
// -- systemdata/admin_brugere.php ------------- lap 4.0.8 -- 2023-02-27 --
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
// Copyright (c) 2003-2023 Saldi.dk ApS
// ------------------------------------------------------------------------
// 20210328 PHR Some cleanup.
// 20210917 LOE translated some texts
// 20230227 CA  Add missing parameters on some calls to db_select & db_modify
// 20230323 PBLM Fixed some minor errors

@session_start();
$s_id=session_id();

$modulnr=104;
$css="../css/standard.css";

include("../includes/std_func.php");
#$title=findtekst("Brugere", $sprog_id);
include("../includes/connect.php");
include("../includes/online.php");

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\" height=\"25\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><a href=../index/admin_menu.php accesskey=L>".findtekst(30, $sprog_id)."</a></td>"; #20210917
print "<td width=\"80%\" $top_bund align=\"center\">".findtekst(1927, $sprog_id)."</td>";
print "<td width=\"10%\" $top_bund align = \"right\"><br></td>";
print "</tbody></table>";
print "</td></tr>\n";
print "<td align = center valign = center>";
print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";


$ret_id = if_isset($_GET['ret_id'], 0);
$slet_id=if_isset($_GET['slet_id'], 0);

if ($_POST) {
	$submit=$_POST['submit'];
	$id=$_POST['id'];	
	$tmp=$_POST['random'];
	$ret_bruger=trim($_POST[$tmp]);
	$kode=trim($_POST['kode']);
	$kode2=trim($_POST['kode2']);
	$ret_bruger=trim($ret_bruger);
	$admin=$_POST['admin'];
	$oprette=if_isset($_POST['oprette'], 0);
	$slette=if_isset($_POST['slette'], 0);
	$adgang_til=addslashes(trim($_POST['adgang_til']));

	$rettigheder="$admin,$oprette,$slette,$adgang_til";

	if ($kode && $kode != $kode2) {
			$alerttext="Adgangskoder er ikke ens";
			print "<BODY onLoad=\"javascript:alert('$alerttext')\">";
			$kode=NULL;
			$ret_id=$id;
	}
	if (($kode) && (!strstr($kode,'**********'))) {
		$insetKode = $kode;
		$kode=saldikrypt($id,$kode);
	} elseif($kode)	{
		$query = db_select("select * from brugere where id = '$id'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query))
		$kode=trim($row['kode']);
	}
	if ((strstr($submit,'Tilf'))&&($ret_bruger)&&($ret_bruger!="-")) {
		$query = db_select("select id from brugere where brugernavn = '$ret_bruger'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$txt = findtekst(1928, $sprog_id); 
			$alerttext="$txt: $ret_bruger!";
			print "<BODY onLoad=\"javascript:alert('$alerttext')\">";
#			print "<tr><td align=center>Der findes allerede en bruger med brugenavn: $ret_bruger!</td></tr>\n";
		}	else {
			db_modify("insert into brugere (brugernavn,rettigheder) values ('$ret_bruger','$rettigheder')",__FILE__ . " linje " . __LINE__);
			$r=db_fetch_array(db_select("select id from brugere where brugernavn = '$ret_bruger'",__FILE__ . " linje " . __LINE__));
			$kode = saldikrypt($r['id'],$insetKode);
			db_modify("update brugere set kode='$kode' where id=$r[id]",__FILE__ . " linje " . __LINE__);
		}
	} elseif ((strstr($submit,'Opdat'))&&($ret_bruger)&&($ret_bruger!="-")) {
		db_modify("update brugere set brugernavn='$ret_bruger',kode='$kode',rettigheder='$rettigheder' where id=$id",__FILE__ . " linje " . __LINE__);
	}
	elseif (($id)&&($ret_bruger=="-")) {db_modify("delete from brugere where id = $id",__FILE__ . " linje " . __LINE__);}
}

print "<tr><td valign = top align=center>";
# print "<table border=><tbody>";
print "<form name=bruger action=admin_brugere.php method=post>";
$td="width=\"8px\" align=\"center\"";
print "<tr><td colspan=\"2\"></td><td title=\"".findtekst(337,$sprog_id)."\" colspan=\"4\" bgcolor=\"$bgcolor2\">".findtekst(332,$sprog_id)."</td></tr>\n";
print "<tr><td colspan=\"2\"></td><td title=\"".findtekst(336,$sprog_id)."\" colspan=\"3\" >".findtekst(331,$sprog_id)."</td><td bgcolor=\"$bgcolor2\"></td></tr>\n";
print "<tr><td colspan=\"2\"></td><td title=\"".findtekst(335,$sprog_id)."\" colspan=\"2\" bgcolor=\"$bgcolor2\">".findtekst(330,$sprog_id)."</td><td></td><td bgcolor=\"$bgcolor2\"></td></tr>\n";
print "<tr><td><br></td><td  style=\"width:170px\" title='Klik p&aring; brugernavn for at &aelig;ndre password eller slette bruger'><b>".findtekst(823, $sprog_id)."</b></td>\n";
print "<td title=\"".findtekst(334,$sprog_id)."\">".findtekst(329,$sprog_id)."</td><td bgcolor=\"$bgcolor2\"></td><td></td><td bgcolor=\"$bgcolor2\"></td></tr>\n";

print "<tr><td height=\"10px\" colspan=\"3\"><br></td><td bgcolor=\"$bgcolor2\"></td><td></td><td bgcolor=\"$bgcolor2\"></td></tr>\n";

$r = db_fetch_array(db_select("select * from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
$bruger_id=$r['id'];
list($br_admin,$tmp)=explode(",",$r['rettigheder'],2);
if (!$br_admin) {
	$ret_id=$bruger_id;
	$disabled="disabled";
} else $disabled="";

if ($br_admin) {
	$query = db_select("select * from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		if ($row['id']!=$ret_id) {
			list($admin,$oprette,$slette,$adgang_til)=explode(",",$row['rettigheder'],4);
			($admin)? $admin="checked":$admin="";
			($oprette)? $oprette="checked":$oprette="";
			($slette)? $slette="checked":$slette="";
			print "<tr><td><br></td><td><a href=admin_brugere.php?ret_id=$row[id]>$row[brugernavn]</a></td>\n";
			print "<td $td><input readonly=\"text\" style=\"width:170px\" name=\"adgang_til\" value=\"$adgang_til\"></td>\n";
			print "<td $td bgcolor=\"$bgcolor2\">";($admin)? print "&#10004":print"";	print "</td>\n";
			print "<td $td>";($oprette)? print "&#10004":print"";	print "</td>\n";
			print "<td $td bgcolor=\"$bgcolor2\">";($slette)? print "&#10004":print"";	print "</td>\n";
		}
	}
}
# print "<tr><td height=\"10px\" colspan=\"4\"></td><td></td><td bgcolor=\"$bgcolor2\"></td><td></td><td bgcolor=\"$bgcolor2\"></td></tr>\n";

if ($ret_id) {
	$query = db_select("select * from brugere where id = $ret_id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	list($admin,$oprette,$slette,$adgang_til)=explode(",",$row['rettigheder'],4);
	($admin)? $admin="checked":$admin="";
	($oprette)? $oprette="checked":$oprette="";
	($slette)? $slette="checked":$slette="";

	print "<tr><td>".findtekst(338,$sprog_id)."</td>";
	print "<input type=hidden name=id value=$ret_id>";
	print "<input type=hidden name=random value=$row[id]>";	#For at undgaa at browseren "husker" et forkert brugernavn.
	print "<td title='".findtekst(326,$sprog_id)."'><input type=\"text\" style=\"width:170px\" name=\"$row[id]\" value=\"$row[brugernavn]\"></td>\n";
	print "<td title=\"".findtekst(334,$sprog_id)."\"><input type=\"text\" style=\"width:170px\" name=\"adgang_til\" value=\"$adgang_til\" $disabled></td>\n";
	print "<td title=\"".findtekst(335,$sprog_id)."\" bgcolor=\"$bgcolor2\"><input type=\"checkbox\" name=\"admin\" $admin $disabled></td>\n";
	print "<td title=\"".findtekst(336,$sprog_id)."\"><input type=\"checkbox\" name=\"oprette\" $oprette $disabled></td>\n";
	print "<td title=\"".findtekst(337,$sprog_id)."\" bgcolor=\"$bgcolor2\"><input type=\"checkbox\" name=\"slette\" $slette $disabled></td></tr>\n";
	print "<tr><td>".findtekst(327,$sprog_id)."</td><td><input type=\"password\" style=\"width:170px\" name=\"kode\" value=\"********************\"></td>\n";
	print "<tr><td>".findtekst(328,$sprog_id)."</td><td><input type=\"password\" style=\"width:170px\" name=\"kode2\" value=\"********************\"></td></tr>\n";

	if ($disabled) {
		print "<input type=\"hidden\" name=\"adgang_til\" value=\"$adgang_til\"></td>\n";
		print "<input type=\"hidden\" name=\"admin\" value=\"$admin\"></td>\n";
		print "<input type=\"hidden\" name=\"oprette\" value=\"$oprette\"></td>\n";
		print "<input type=\"hidden\" name=\"slette\" value=\"$slette\"></td></tr>\n";
	}

	$x=0;
	print "<tr><td><br></td></tr>\n";
	print "<td colspan=12 align = center><input type=submit value=\"Opdat&eacute;r\" name=\"submit\"></td>";
} elseif ($br_admin) {
	$tmp="navn".rand(100,999); #For at undgaa at browseren "husker" et forkert brugernavn.
	print "<input type=hidden name=random value = $tmp>";
	print "<tr><td>".findtekst(333,$sprog_id)."</td>";
	print "<td><input type=text  style=\"width:170px\" name=\"$tmp\" value=\" \"></td>";
	print "<td title=\"".findtekst(334,$sprog_id)."\"><input type=\"text\" style=\"width:170px\" name=\"adgang_til\" value=\"*\"></td>\n";
	print "<td title=\"".findtekst(335,$sprog_id)."\" bgcolor=\"$bgcolor2\"><input type=\"checkbox\" name=\"admin\"></td>\n";
	print "<td title=\"".findtekst(336,$sprog_id)."\"><input type=\"checkbox\" name=\"oprette\" checked></td>\n";
	print "<td title=\"".findtekst(337,$sprog_id)."\" bgcolor=\"$bgcolor2\"><input type=\"checkbox\" name=\"slette\" checked></td></tr>\n";
	print "<tr><td>".findtekst(327,$sprog_id)."</td><td><input type=\"password\"  style=\"width:170px\" name=\"kode\" value=\"\"></td></tr>\n";
	print "<tr><td>".findtekst(328,$sprog_id)."</td><td><input type=\"password\"  style=\"width:170px\" name=\"kode2\" value=\"\"></td></tr>\n";
	print "<td colspan=12 align = center><input type=submit value=\"Tilf&oslash;j\" name=\"submit\"></td>";
}
print "</tr>\n";
# print "</tbody></table></td></tr>\n";

?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
