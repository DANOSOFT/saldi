<?php
// -------------systemdata/lev_rabat.php----ver 3.1.3-------2011.02.20-----------
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
// Copyright (c) 2004-2011 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$modulnr=2;
$title="Prisgrupper";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$id=if_isset($_GET['id']);
$lev_id=if_isset($_GET['lev_id']);
$prisliste=if_isset($_GET['prisliste']);

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; #tabel 1 
print "<tr><td colspan=\"2\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr><td>"; # tabel 1.1
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody><tr>"; # tabel 1.1.1
print "<td width=\"120px\" $top_bund><a href=\"diverse.php?sektion=prislister\" accesskey=\"L\">Luk</a></td>
        <td $top_bund>$prisliste prisgrupper<br></td>
        <td width=\"120px\" $top_bund>$rightoptxt<br></td></tr>
      </tbody></table></td></tr>"; # <- tabel 1.1.1
#print "</tr></tbody></table></td></tr>


if (isset($_POST['gem']) && !$lev_id) {
	$kontonr=if_isset($_POST['kontonr']);
	if($kontonr) {
		$r=db_fetch_array(db_select("select * from adresser where art = 'K' and kontonr = '$kontonr'",__FILE__ . " linje " . __LINE__));
		$lev_id=$r['id'];
		$firmanavn=$r['firmanavn'];
		if ($lev_id) {
			db_modify("update grupper set  box1='$lev_id' where id='$id'",__FILE__ . " linje " . __LINE__);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=diverse.php?sektion=prislister\">\n";
			exit;
		} else {
			$title=str_replace('$kontonr',$kontonr,findtekst(433,$sprog_id));
			print "<BODY onLoad=\"javascript:alert('Ingen leverand&oslash;r med kontonummer $kontonr')\"><!--tekst 433-->\n";
		}
	} else {
		db_modify("update grupper set  box4='' where id='$id'",__FILE__ . " linje " . __LINE__);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=diverse.php?sektion=prislister\">\n";
	}
} elseif (isset($_POST['gem']) && $lev_id) {
	if ($prisgruppeantal=if_isset($_GET['prisgruppeantal'])) {
		$std_rabat=trim(if_isset($_POST['std_rabat']));
		$ny_rabat=if_isset($_POST['ny_rabat']);
		$prisgruppe=if_isset($_POST['prisgruppe']);
		$std_varegruppe=trim(if_isset($_POST['std_varegruppe']));
		$ny_gruppe=if_isset($_POST['ny_gruppe']);
		for ($x=1;$x<=$prisgruppeantal;$x++) {
			$ny_rabat[$x]*=1;
			if($ny_rabat[$x]!=$std_rabat) {
				($rabat)?$rabat.=chr(9).$prisgruppe[$x]."|".$ny_rabat[$x]:$rabat=chr(9).$prisgruppe[$x]."|".$ny_rabat[$x];
			}
		}
#		db_modify("update grupper set  box5='$rabat' where id='$id'",__FILE__ . " linje " . __LINE__);
		for ($x=1;$x<=$prisgruppeantal;$x++) {
			$ny_gruppe[$x]=trim($ny_gruppe[$x]);
			if($ny_gruppe[$x] && $ny_gruppe[$x]!=$std_varegruppe) {
				($varegruppe)?$varegruppe.=chr(9).$prisgruppe[$x]."|".$ny_gruppe[$x]:$varegruppe=chr(9).$prisgruppe[$x]."|".$ny_gruppe[$x];
			}
		}
		db_modify("update grupper set  box5='$rabat',box7='$varegruppe' where id='$id'",__FILE__ . " linje " . __LINE__);
	}
}

print "<table><tbody>";
if (!$lev_id && $prisliste) {
	print "<form name=\"diverse\" action=\"lev_rabat.php?id=$id&prisliste=$prisliste\" method=\"post\">";
	print "<tr><td>Skriv kontonummer for $prisliste</td>";
	print "<td><INPUT CLASS=\"inputbox\" TYPE=text style=\"text-align:right;width:100px\" name=kontonr value=\"$kontonr\"></td></tr>";
	print "<tr><td colspan=\"2\"><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"gem\"></td></tr>";
	print "</form>";
} elseif ($lev_id) {
	$rabatter=array();
	$rabat=array();
	$r=db_fetch_array(db_select("select * from grupper where art = 'PL' and beskrivelse = '$prisliste'",__FILE__ . " linje " . __LINE__));
	$prisfil=trim($r['box2']);
	$rabatter=explode(chr(9),$r['box5']);
	$std_rabat=$r['box6']*1;
	$varegrupper=explode(chr(9),$r['box7']);
	$std_varegruppe=$r['box8']*1;
	$rabatantal=count($rabatter);
	for ($x=0;$x<$rabatantal;$x++) {
		list($rabatgruppe[$x],$rabat[$x])=explode("|",$rabatter[$x]);
	}
	$gruppeantal=count($varegrupper);
	for ($x=0;$x<$gruppeantal;$x++) {
		list($gruppe[$x],$varegruppe[$x])=explode("|",$varegrupper[$x]);
	}
	$prisgruppe=array();
	$fp=fopen("$prisfil","r");
	if ($fp) {
		$x=0;
		while (!feof($fp)) {
			$linje=fgets($fp);
			if (substr($linje,0,1)>=5) {
				$tmp=substr($linje,85,4);
				if ($tmp && !in_array($tmp,$prisgruppe)) {
					$x++;
					$prisgruppe[$x]=$tmp;
				}
			}
		}
	}
	fclose($fp);
	$prisgruppeantal=$x;
	sort($prisgruppe);

	$gpantal=0;
	$q=db_select("select * from grupper where art = 'VG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$gpantal++;
		$vgrp[$gpantal]=$r['kodenr'];
		$vgbesk[$gpantal]=$r['beskrivelse'];
		if ($std_varegruppe==$vgrp[$gpantal]) $std_vgbesk=$vgbesk[$gpantal];
	}
	print "<form name=\"diverse\" action=\"lev_rabat.php?prisliste=$prisliste&id=$id&lev_id=$lev_id&prisgruppeantal=$prisgruppeantal\" method=\"post\">";
	print "<input type=\"hidden\" name=\"std_rabat\" value=\"$std_rabat\">";
	print "<input type=\"hidden\" name=\"std_varegruppe\" value=\"$std_varegruppe\">";
	print "<tr><td colspan=\"3\" align=\"center\">Skriv rabatsats for relevante prisgrupper fra $prisliste</td></tr>";
	print "<tr><td colspan=\"3\" align=\"center\">og v&aelig;lg varegruppe for de prisgrupper som ikke tilh&oslash;rer den generelle varegruppe</td></tr>";
	print "<tr><td colspan=\"3\" align=\"center\"><hr></td></tr>";
	print "<tr><td align=\"right\">Prisgruppe</td><td align=\"right\">Rabat</td><td align=\"center\">Varegruppe</td></tr>";

	for ($x=0;$x<$prisgruppeantal;$x++) {
		print "<input type=\"hidden\" name=\"prisgruppe[$x]\" value=\"$prisgruppe[$x]\">";
		$ny_rabat[$x]=$std_rabat;
		for ($y=0;$y<$rabatantal;$y++) {
			if ($rabatgruppe[$y]==$prisgruppe[$x]) $ny_rabat[$x]=$rabat[$y];
		}
		print "<tr><td align=\"right\">$prisgruppe[$x]</td>";
		print "<td align=\"right\"><INPUT CLASS=\"inputbox\" TYPE=text style=\"text-align:right\" size=1 name=ny_rabat[$x] value=\"$ny_rabat[$x]\"></td>";

		print "<td align=\"center\" title=\"".findtekst(426,$sprog_id)."\"><!--tekst 426--><select CLASS=\"inputbox\" name=\"ny_gruppe[$x]\">";
#		$ny_varegruppe[$x]=$std_varegruppe;
#		$ny_vgbesk[$x]=$std_vgbesk;
#		for ($y=1;$y<=$gpantal;$y++) {
#			if ($gruppe[$y]==$prisgruppe[$x]) {
#				$ny_varegruppe[$x]=$varegruppe[$x];
#				$ny_vgbesk[$x]=$varegruppe[$x];
#					
#			}
#		}
		$tjek=0;
		for ($y=1;$y<=$gpantal;$y++) {
			if ($gruppe[$y]==$prisgruppe[$x]) {
				print "<option value=\"$vgrp[$y]\">$vgrp[$y]: $vgbesk[$y]</option>";
				$tjek=1;
			}
		}
		if (!$tjek) print "<option value=\"$std_varegruppe\">$std_varegruppe: $std_vgbesk</option>";		
		for ($y=1;$y<=$gpantal;$y++) {
			if ($tjek && $gruppe[$y]!=$prisgruppe[$x]) print "<option value=\"$vgrp[$y]\">$vgrp[$y]: $vgbesk[$y]</option>";
			elseif (!$tjek && $vgrp[$y]!=$std_varegruppe)	print "<option value=\"$vgrp[$y]\">$vgrp[$y]: $vgbesk[$y]</option>";
	}
		print "</select></td>";
		print "</tr>";
	}
	print "<tr><td colspan=\"3\" align=\"center\"><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"gem\"></td></tr>";
	print "</form>";
}
print "<tbody><table>";
	
?>
</body></html>
