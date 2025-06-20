<?php
// -------------systemdata/rabatgrupper.php----ver 3.0.3-------2010.05.31-----------
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
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$modulnr=2;
$title="rabatgrupper";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

include("top.php");

$selfdef=if_isset($_GET['selfdef']);

if(isset($_POST['gem'])) {
	
	$id=$_POST['id'];
	$rabat=$_POST['rabat'];
	$drg_antal=$_POST['drg_antal'];
	$dg_antal=$_POST['dg_antal'];
	$drgnavn=if_isset($_POST['drgnavn']);
	$drg_nr=if_isset($_POST['drg_nr']);
	$ny_rabat=$_POST['ny_rabat'];
	$vg_antal=$_POST['vg_antal'];


	for ($x=0;$x<$drg_antal;$x++) {
		if ($drgnavn[$x] && $drgnavn[$x]!='-') db_modify("update grupper set box1 = '".$drgnavn[$x]."' where art = 'DRG' and kodenr = '".$drg_nr[$x]."'",__FILE__ . " linje " . __LINE__);
		elseif ($drgnavn[$x]=='-') {
			if (!db_fetch_array(db_select("select * from rabat where debitor = '".$drg_nr[$x]."'",__FILE__ . " linje " . __LINE__))) {
				db_modify("delete from grupper where art = 'DRG' and kodenr = '".$drg_nr[$x]."'",__FILE__ . " linje " . __LINE__);
			}
		}
	}
	if (!$drg_antal) $drg_antal++;
	if ($drgnavn[$drg_antal]) {
		$kodenr=0;
		$x=0;
		$q=db_select("select * from grupper where art = 'DRG' order by ".nr_cast(kodenr)."",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$x++;
		echo "if ($x!=$r[kodenr]*1)<br>";		
			if ($x!=$r['kodenr']*1) $kodenr=$x;
		}	
		if (!$kodenr) $kodenr=$x+1;
		echo "KNR = $kodenr<br>";
		db_modify("insert into grupper (beskrivelse,art,kodenr,box1) values ('Debitorrabatgrupper','DRG','$kodenr','$drgnavn[$drg_antal]')",__FILE__ . " linje " . __LINE__);
	}
/*	
	for ($x=1;$x<=$drg_antal;$x++) {
		for ($y=1;$y<=$vg_antal;$y++){
			$ny_rabat[$x][$y]=usdecimal($ny_rabat[$x][$y])*1;
			$rabat[$x][$y]=$rabat[$x][$y]*1;
			if ($ny_rabat[$x][$y]<0) $ny_rabat[$x][$y]=0;
			if ($ny_rabat[$x][$y]>100) $ny_rabat[$x][$y]=100;
			if ($ny_rabat[$x][$y] != $rabat[$x][$y]) {
				if ($id[$x][$y]) {
					if ($ny_rabat[$x][$y]) db_modify("update rabat set rabat = '".$ny_rabat[$x][$y]."' where id = '".$id[$x][$y]."'",__FILE__ . " linje " . __LINE__);
					else {
						db_modify("delete from rabat where id = '".$id[$x][$y]."'",__FILE__ . " linje " . __LINE__);
					}
				} elseif ($ny_rabat[$x][$y]) db_modify("insert into rabat (rabat,debitorart,debitor,vareart,vare) values ('".$ny_rabat[$x][$y]."','DG','$x','VG',$y)",__FILE__ . " linje " . __LINE__);
			}
		}
		$dg_antal=0;
	}
*/	
	for ($x=1;$x<=$dg_antal;$x++) {
		for ($y=1;$y<=$vg_antal;$y++){
			$ny_rabat[$x][$y]=usdecimal($ny_rabat[$x][$y])*1;
			$rabat[$x][$y]=$rabat[$x][$y]*1;
			if ($ny_rabat[$x][$y]<0) $ny_rabat[$x][$y]=0;
			if ($ny_rabat[$x][$y]>100) $ny_rabat[$x][$y]=100;
			if ($ny_rabat[$x][$y] != $rabat[$x][$y]) {
				if ($id[$x][$y]) {
					if ($ny_rabat[$x][$y]) db_modify("update rabat set rabat = '".$ny_rabat[$x][$y]."' where id = '".$id[$x][$y]."'",__FILE__ . " linje " . __LINE__);
					else db_modify("delete from rabat where id = '".$id[$x][$y]."'",__FILE__ . " linje " . __LINE__);
				} elseif ($ny_rabat[$x][$y]) db_modify("insert into rabat (rabat,debitorart,debitor,vareart,vare) values ('".$ny_rabat[$x][$y]."','DG','$x','VG',$y)");
 			}
		}
	}
}

$id=array();$dg=array();$dgnavn=array();$rabat=array();$vg=array();$vgnavn=array();

$x=0;$y=0;
$q=db_select("select * from grupper where art = 'DRG' order by ".nr_cast(kodenr)."",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$x++;
	$dg_id[$x][0]=$r['id'];
	$dg[$x][0]=$r['kodenr'];
	$dgnavn[$x][0]=$r['box1'];
}	
$drg_antal=$x;
if ($drg_antal || $selfdef) $drg_antal++;
if (!$drg_antal) {
	$q=db_select("select * from grupper where art = 'DG' order by ".nr_cast(kodenr)."",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$x++;
		$dg[$x][0]=$r['kodenr'];
		$dgnavn[$x][0]=$r['beskrivelse'];
	}	
	$dg_antal=$x;
} else $dg_antal=$drg_antal;
$q=db_select("select * from grupper where art = 'VG' order by ".nr_cast(kodenr)."",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$y++;
	$vg[0][$y]=$r['kodenr'];
	$vgnavn[0][$y]=$r['beskrivelse'];
}
$vg_antal=$y;
$colspan=$vg_antal+3;
$x=0;
$y=0;
$rabatantal=0;
$q=db_select("select * from rabat order by debitor,vare",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$rabatantal++;
	$x=$r['debitor']*1;
	$y=$r['vare']*1;
	$id[$x][$y]=$r['id']*1;
	$rabat[$x][$y]=$r['rabat']*1;
}
print "<form name=rabat action=rabatgrupper.php method=post>";
print "<input type=hidden name=dg_antal value=\"".$dg_antal."\">";
print "<input type=hidden name=vg_antal value=\"".$vg_antal."\">";
print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"border:solid 1px $bgcolor5\"><tbody>"; #tabel 1.1.3 ->
if (!$drg_antal && !$rabatantal && !$selfdef) {
#	echo "valgmulighed"
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=\"$colspan\" align=\"center\"><a href=\"rabatgrupper.php?selfdef=1\">Definer selv debitorrabatgrupper</a></td>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=\"$colspan\" align=\"center\"><hr></td>";
}
print "<tr bgcolor=\"$bgcolor5\"><td colspan=\"2\" align=\"center\">Debitorgrp \ Varegrp</td>";
for ($y=1;$y<=$vg_antal;$y++) print "<td title=\"".$vgnavn[0][$y]."\">&nbsp;VG$y</td>";
print "<td>&nbsp;</td></tr>\n";
print "<tr><td colspan=\"$colspan\"><hr></td></tr>";
for ($x=1;$x<=$dg_antal;$x++){
	($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
	print "<tr bgcolor=\"$linjebg\">";
	if ($drg_antal) {
		print "<input type=\"hidden\" name=\"drg_nr[$x]\" value = \"".$dg[$x][0]."\">";
		print "<td colspan=\"2\"><input type=\"text\" size= \"25\" name=\"drgnavn[$x]\" value = \"".$dgnavn[$x][0]."\"></td>";
	} else {
		print "<td align=\"right\">".$dg[$x][0]."</td>";
		print "<td>&nbsp;".$dgnavn[$x][0]."</td>";
	}
	for ($y=1;$y<=$vg_antal;$y++) {
		if ($dg[$x][0]) {
			if ($id[$x][$y]) $rabat[$x][$y]=str_replace(".",",",$rabat[$x][$y]);
			else $rabat[$x][$y]=NULL;
			print "<input type=\"hidden\" name=\"id[$x][$y]\" value=\"".$id[$x][$y]."\">";
			print "<input type=\"hidden\" name=\"rabat[$x][$y]\" value=\"".$rabat[$x][$y]."\">";
			print "<input type=\"hidden\" name=\"drg_antal\" value=\"".$drg_antal."\">";
			print "<td align=\"center\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" name=\"ny_rabat[$x][$y]\" size=2 value=\"".$rabat[$x][$y]."\"</td>";
		} else print "<td colspan=\"vg_antal\"><br></td>";
	}
	print "<td>&nbsp;</td></tr>\n";
}
print "<tr><td colspan=\"$colspan\" align = \"center\"><input STYLE=\"width: 100%;height: 1.5em;margin-bottom:1px;padding: 1px 1px;border: 1px solid #DDDDDD;background:url('../img/knap_bg.gif');\" type=submit accesskey=\"g\" value=\"Gem\" name=\"gem\" onclick=\"javascript:docChange = false;\"></td></tr>\n";
print "</form>";

print "</tbody></table></td></tr>"; # <- tabel 1.1.3
print "</tbody></table>"; # <- tabel 1.1
print "</td></tbody></table>"; # <- tabel 1
?>
</body></html>
