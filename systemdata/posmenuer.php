<?php
// -------------------- systemdata/posmenuer.php ------ patch 3.0.6--2010-10-22--------
// LICENS..
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
$title="POS knap menu";
$modulnr=1;
$css="../css/pos.css";

$diffkto=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");


$menuvalg=if_isset($_POST['menuvalg']);
$menu_id=if_isset($_POST['menu_id']);
$beskrivelse=if_isset($_POST['beskrivelse']);
$cols=if_isset($_POST['cols'])*1;
$rows=if_isset($_POST['rows'])*1;
$height=if_isset($_POST['height'])*1;
$fontsize=if_isset($_POST['fontsize'])*1;
$width=if_isset($_POST['width'])*1;
$master=if_isset($_POST['master']);
$begin=if_isset($_POST['begin']);
$end=if_isset($_POST['end']);
$projekt=if_isset($_POST['projekt']);
$buttxt=if_isset($_POST['buttxt']);
$butcolor=if_isset($_POST['butcolor']);
$butfunc=if_isset($_POST['butfunc']);
$butvnr=if_isset($_POST['butvnr']);

$beskrivelse=addslashes($beskrivelse);

if ($master) {
	$begin=tid($begin);
	$end=tid($end);
	if ($end=='00:00') $end='24:00';
}

if ($menuvalg || ($id && $beskrivelse)) {
	if ($menuvalg=='ny') {
		$id=0;
		$x=0;
		$q=db_select("select kodenr from grupper where art='POSBUT' order by kodenr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (!$id) {
				$x++;
				if ($x!=$r['kodenr']) {
					$id=$x;
				}
			}
		}
		if (!$id) $id=$x+1;
		if ($id && $beskrivelse) {
			db_modify("insert into grupper(beskrivelse,art,kodenr,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10) values ('POS menu knapper','POSBUT','$id','$beskrivelse','$cols','$rows','$height','$width','$master','$begin','$end','$projekt','$fontsize')",__FILE__ . " linje " . __LINE__);
		}
  } else {
		list($id,$tmp)=explode(":",$menuvalg);
		if ($menu_id==$id && $cols && $rows) {
			db_modify("update grupper set box1='$beskrivelse',box2='$cols',box3='$rows',box4='$height',box5='$width',box6='$master',box7='$begin',box8='$end',box9='$projekt',box10='$fontsize' where art='POSBUT' and kodenr='$id'",__FILE__ . " linje " . __LINE__);
			for ($x=1;$x<=$rows;$x++) {
				for ($y=1;$y<=$cols;$y++) {
					$a=$buttxt[$x][$y];$b=substr($butcolor[$x][$y],0,6);$c=$butfunc[$x][$y]*1;$d=$butvnr[$x][$y];
					$a=addslashes($a);
					$a=str_replace("<br>","\n",$a);
					if ($c==1) {
						$r=db_fetch_array(db_select("select id from varer where varenr='$d' and lukket !='on'"));
						$d=$r['id']*1;
					} elseif ($c==3) {
						$r=db_fetch_array(db_select("select id from adresser where kontonr='$d' and lukket !='on'"));
						$d=$r['id']*1;
					} else $d=$d*1;
 					if ($r=db_fetch_array(db_select("select id from pos_buttons where menu_id=$id and row='$x' and col='$y'"))) {
#echo "update pos_buttons set beskrivelse='$a',color='$b',funktion='$c',vare_id='$d' where id='$r[id]'<br>";
						db_modify("update pos_buttons set beskrivelse='$a',color='$b',funktion='$c',vare_id='$d' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
					} elseif ($a || $b || $c || $d) {
						db_modify("insert into pos_buttons (menu_id,row,col,beskrivelse,color,funktion,vare_id,colspan,rowspan) values ('$id','$x','$y','$a','$b','$c','$d','1','1')",__FILE__ . " linje " . __LINE__);
					}
				}
			}
		} elseif ($menu_id==$id) {
			db_modify("delete from grupper where art='POSBUT' and kodenr='$id'",__FILE__ . " linje " . __LINE__);
		}
 	}
}

$x=0;
if (!$id) list($id,$beskrivelse)=explode(":",$menuvalg);
$q=db_select("select * from grupper where art='POSBUT' and kodenr='$id'",__FILE__ . " linje " . __LINE__);
$r = db_fetch_array($q);
$id=$r['kodenr'];
$beskrivelse=str_replace("\n","<br>",$r['box1']);
$cols=$r['box2'];
$rows=$r['box3'];
$height=$r['box4'];
$width=$r['box5'];
$master=$r['box6'];
$begin=$r['box7'];
$end=$r['box8'];
$projekt=$r['box9'];
$fontsize=$r['box10'];
if ($master) {
	$master="checked";
	if (!$begin) $begin="00:00";
	if (!$end) $end="24:00";
}
if (!$cols) $cols=10;
if (!$rows) $rows=1;
if (!$height) $height=10;
if (!$width) $width=20;
if (!$fontsize) $fontsize=$height*$width/200;

print "<tr><td><a href=diverse.php?sektion=pos_valg>Luk</a></td></tr>";
print "<form name=\"posmenuer\" action=\"posmenuer.php\" method=\"post\">";
print "<table><tbody>";
print "<tr><td></td><td><select CLASS=\"inputbox\" name=\"menuvalg\">";
if ($id && $beskrivelse) $menuvalg=$id.":".$beskrivelse;
else $menuvalg=NULL;
$id=$id*1;
	print "<option value=\"$menuvalg\">$menuvalg</option>";
	$q = db_select("select * from grupper where art = 'POSBUT' order by box1",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$tmp=$r['kodenr'].":".$r['box1'];
		if ($tmp!=$menuvalg) print "<option value=\"$tmp\">$tmp</option>";
	}
	print "<option value=\"ny\">Opret ny</option>";
	print "</select></td></tr>";
	print "<input type=\"hidden\" name=\"menu_id\" value=\"$id\">";
	print "<tr><td>Menu ID</td><td>$id</td></tr>";
	print "<tr><td>Beskrivelse</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:300px\" name=\"beskrivelse\" value=\"$beskrivelse\"></td></tr>";
if ($beskrivelse) {
	print "<tr><td>Hovedmenu</td><td><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"master\" $master></td></tr>";
	if ($master) {
		print "<tr><td>Aktiv fra</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:50px;text-align:right\" name=\"begin\" value=\"$begin\"></td></tr>";
		print "<tr><td>Aktiv til</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:50px;text-align:right\" name=\"end\" value=\"$end\"></td></tr>";
		print "<tr><td>Projekt</td><td><SELECT CLASS=\"inputbox\" name=\"projekt\">";
		$r=db_fetch_array(db_select("select * from grupper where art='PRJ' and kodenr='$projekt'",__FILE__ . " linje " . __LINE__));
		print "<option value=\"$projekt\">$r[beskrivelse]</option>";
		$q=db_select("select * from grupper where art='PRJ'",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			if ($projekt!=$r['kodenr']) print "<option value=\"$r[kodenr]\">$r[beskrivelse]</option>";
		}
		if ($projekt) print "<option value=\"\"></option>";
		print "</select></td></tr>";
	}
	print "<tr><td>Antal menur&aelig;kker</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:50px;text-align:right\" name=\"rows\" value=\"$rows\"></td></tr>";
	print "<tr><td>Antal menukolonner</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:50px;text-align:right\" name=\"cols\" value=\"$cols\"></td></tr>";
	print "<tr><td>Knap h&oslash;jde</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:50px;text-align:right\" name=\"height\" value=\"$height\"></td></tr>";
	print "<tr><td>Knap bredde</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:50px;text-align:right\" name=\"width\" value=\"$width\"></td></tr>";
	print "<tr><td>tekst st&oslash;rrelse</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:50px;text-align:right\" name=\"fontsize\" value=\"$fontsize\"></td></tr>";
}
print "</tbody></table>";

if ($beskrivelse) {
print "<table border=1><tbody>";
for ($x=1;$x<=$rows;$x++) {
	print "<tr>";
	print "<td style=\"width:140px;height:".$height."px;text-align:left\">
	<INPUT CLASS=\"inputbox\" READONLY=\"readonly\" style=\"width:140px;text-align:left\" value=\"Tekst\"><br>
	<INPUT CLASS=\"inputbox\" READONLY=\"readonly\" style=\"width:140px;text-align:left\" value=\"Farvekode\"><br>
	<INPUT CLASS=\"inputbox\" READONLY=\"readonly\" style=\"width:140px;text-align:left\" value=\"Vare-/menunr\"><br>
	<INPUT CLASS=\"inputbox\" READONLY=\"readonly\" style=\"width:140px;text-align:left\" value=\"Funktion\">
	</td>";
	for ($y=1;$y<=$cols;$y++) {
		$r=db_fetch_array(db_select("select * from pos_buttons where menu_id='$id' and row='$x' and col='$y'"));
		$a=str_replace("\n","<br>",$r['beskrivelse']);
		$b=$r['color'];
		$c=$r['vare_id']*1;
		$d=$r['funktion']*1;
		if ($d==1 && $c) {
			$r=db_fetch_array(db_select("select varenr from varer where id='$c' and lukket !='on'"));
			$c=$r['varenr'];
		}
		if ($d==3 && $c) {
			$r=db_fetch_array(db_select("select kontonr from adresser where id='$c' and lukket !='on'"));
			$c=$r['kontonr'];
		}
		if (!$c) $c='';
		print "<td style=\"width:".$width."px;height:".$height."px;text-align:center\">
		<INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:".$width."px;text-align:center\" name=\"buttxt[$x][$y]\" value=\"$a\"><br>
		<INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:".$width."px;text-align:center\" name=\"butcolor[$x][$y]\" value=\"$b\"><br>
		<INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:".$width."px;text-align:center\" name=\"butvnr[$x][$y]\" value=\"$c\"><br>
		<SELECT CLASS=\"inputbox\" style=\"width:".$width."px;\" name=\"butfunc[$x][$y]\">";
		if ($d==1) print "<OPTION value=\"1\">Varenr</OPTION>";
		if ($d==2) print "<OPTION value=\"2\">Menu</OPTION>";
		if ($d==3) print "<OPTION value=\"3\">Kundenr</OPTION>";
		if ($d==4) print "<OPTION value=\"4\">Specialfunktion</OPTION>";
		if ($d!=1) print "<OPTION value=\"1\">Varenr</OPTION>";
		if ($d!=2) print "<OPTION value=\"2\">Menu</OPTION>";
		if ($d!=3) print "<OPTION value=\"3\">Kundenr</OPTION>";
		if ($d!=4) print "<OPTION value=\"4\">Specialfunktion</OPTION>";
		print	"</SELECT></td>";
	}
	print "</tr>";
}
print "</tbody></table>";
}
print "<tr><td><input type=submit value=\"ok\" name=\"ok\"></td></tr>";
print "</form>";
print "<table border=\"0\" cellspacing=\"5\" cellpadding=\"5\"><tbody>";
for ($x=1;$x<=$rows;$x++) {
	print "<tr>";
	for ($y=1;$y<=$cols;$y++) {
		$r=db_fetch_array(db_select("select * from pos_buttons where menu_id='$id' and row='$x' and col='$y'"));
#		$a=str_replace("\n","<br>",$r['beskrivelse']);
		$a=$r['beskrivelse'];
		$b=$r['color'];
		$c=$r['vare_id']*1;
		$d=$r['funktion']*1;
		if ($d==1 && $c) {
			$r=db_fetch_array(db_select("select varenr from varer where id='$c' and lukket !='on'"));
			$c=$r['varenr'];
		}
		if (!$c) $c='';
#		$fontsize=$height*0.7;
		print "<td><input type=\"button\" style=\"width:".$width."px;height:".$height."px;text-align:center;font-size:".$fontsize."px; background-color:#$b;\" value= \"$a\"></td>";

#		print "<td style=\"width:".$width."px;height:".$height."px;text-align:center;font-size:".$fontsize."px;\" bgcolor=\"#$b\">$a</td>";
	}
	print "</tr>";
}
print "</tbody></table>";
function tid($tid) {
	list($a,$b)=explode(":",$tid);
	$a=$a*1;
	if ($a>24) $a=24;
	while (strlen($a)<2) $a="0".$a;
	if ($b>59) $b=59;
	while (strlen($b)<2) $b="0".$b;
	$tid=$a.":".$b;
	return($tid);
}

?>
