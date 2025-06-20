<?php
// ----------------------------------/systemdata/kontokort.php---------------------lap 1.0.02-------------
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
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------
// 050805 Indsat "htmlentities" under "http_post_vars" for ar undgaa problemer med DK char



@session_start();
$s_id=session_id();
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><title>SALDI - kontokort</title><meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"></head>
<body bgcolor="#339999" link="#000000" vlink="#000000" alink="#000000" center="">
<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0"><tbody>
	<tr><td align="center" valign="top">
		<table width="100%" align="center" border="0" cellspacing="0" cellpadding="0"><tbody>
			<td width="25%" bgcolor="<?php echo $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><a href=kontoplan.php	accesskey=T>Tilbage</a></small></td>
			<td width="50%" bgcolor="<?php echo $bgcolor2 ?>" align="center"><font face="Helvetica, Arial, sans-serif" color="#000066"><small>Kontokort</small></td>
			<td width="25%" bgcolor="<?php echo $bgcolor2 ?>" align = "right"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><a href=kontokort.php	accesskey=N>Ny</a><br></small></td>
		</tbody></table>
	</td></tr>
<td align = center valign = center>
<table cellpadding="1" cellspacing="1" border="0"><tbody>
<?php

$id = $_GET['id'];



if ($_POST['slet']){
	$id=$_POST['id'];
	$query = db_modify("delete from kontoplan where id = $id");
	$id=0;
}
elseif ($_POST['gem']){
	$id=$_POST['id'];
	$kontonr=$_POST['kontonr'];
	$beskrivelse=addslashes($_POST['beskrivelse']);
	$kontotype=$_POST['kontotype'];
	$katagori=$_POST['katagori'];
	$moms=$_POST['moms'];
	$fra_kto=$_POST['fra_kto'];
	$til_kto=$_POST['til_kto'];
	$genvej=$_POST['genvej'];
	$lukket=$_POST['lukket'];
	if ($kontotype=='Overskrift'){
		$kontotype='H';
		$moms="";
		$fra_kto=0;
		$til_kto=0;
	}
	elseif ($kontotype=='Drift'){$kontotype='D';}
	elseif ($kontotype=='Status'){$kontotype='S';}
	elseif ($kontotype=='Lukket'){$kontotype='L';}
	elseif ($kontotype=='Sum'){
		$kontotype='Z';
		$moms="";
	}

	if ($kontonr<1) {echo "$font<big><b>Kontonummer skal v&aelig;re et positivt heltal</b></big><br><br>";}
	elseif ($id==0) {
		$query = db_select("select id from kontoplan where kontonr = $kontonr and regnskabsaar = '$regnaar'");
		if ($row = db_fetch_array($query)){
			echo "$font<big><b>Der findes allerede en konto med nr: $kontonr</b></big><br><br>";
			$id=0;
		}
		else {
			$x=0;
			$query = db_select("select kodenr from grupper where art = 'RA' order by kodenr");
			while ($row = db_fetch_array($query)) {
				if ($row['kodenr']>=$x){$x=$row['kodenr'];}
			}
			for ($y=$regnaar; $y<=$x; $y++) {
				$query = db_select("select id from kontoplan where kontonr = $kontonr and regnskabsaar = '$y'");
				if(!$row = db_fetch_array($query)) {
					db_modify("insert into kontoplan (kontonr, beskrivelse, kontotype, primo, regnskabsaar, genvej) values ($kontonr, '$beskrivelse', '$kontotype', '0', '$y', '$genvej')");
				}
			}
			$query = db_select("select id from kontoplan where kontonr = $kontonr and regnskabsaar = '$regnaar'");
			$row = db_fetch_array($query);
			$id = $row[id];
		}
	}
	elseif ($id > 0) {
		if (!$fra_kto){$fra_kto=0;}
		if (!$til_kto){$til_kto=0;}
		$query = db_modify("update kontoplan set kontonr = $kontonr, beskrivelse = '$beskrivelse', kontotype = '$kontotype', moms = '$moms', fra_kto = '$fra_kto', til_kto = '$til_kto', genvej='$genvej', lukket = '$lukket' where id = '$id'");
	}

}
if ($id > 0){
	$query = db_select("select * from kontoplan where id = '$id'");
	if ($row = db_fetch_array($query)){
		$id=$row['id'];
		$kontonr=$row['kontonr'];
		$beskrivelse=htmlentities(stripslashes($row['beskrivelse']));
		$kontotype=$row['kontotype'];
		$katagori=$row['katagori'];
		$moms=$row['moms'];
		$fra_kto=$row['fra_kto'];
		$til_kto=$row['til_kto'];
		$genvej=$row['genvej'];
		$lukket=$row['lukket'];
	}
	
	$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'");
	$row = db_fetch_array($query);
	$startmaaned=$row[box1]*1;
	$startaar=$row[box2]*1;
	$slutmaaned=$row[box3]*1;
	$slutaar=$row[box4]*1;
	$slutdato=31;

	while (!checkdate($slutmaaned,$slutdato,$slutaar)) {
		$slutdato=$slutdato-1;
		if ($slutdato<28){break;}
	}

	$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
	$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

	$query = db_select("select id from transaktioner where transdate>'$regnstart' and transdate<'$regnslut' and kontonr=$kontonr");
	if ($row = db_fetch_array($query)){$slet=0;}
	else {$slet=1;}
}
if ($kontotype=='H'){$kontotype='Overskrift';}
elseif($kontotype=='D'){$kontotype='Drift';}
elseif($kontotype=='S'){$kontotype='Status';}
elseif($kontotype=='Z'){$kontotype='Sum';}
elseif($kontotype=='L'){$kontotype='Lukket';}



print "<form name=kontokort action=kontokort.php method=post>";
print "<input type=hidden name=id value='$id'>";

if ($id) {
	print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Kontonr</td><td><br></td><td>$font $kontonr</td></tr>";
	print "<input type=hidden name=kontonr value=\"$kontonr\">";
}
else {print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Kontonr</td><td><br></td><td><input type=text size=6 name=kontonr value=\"$kontonr\"></td></tr>";}
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Beskrivelse</td><td><br></td><td><input type=text size=25 name=beskrivelse value=\"$beskrivelse\"></td></tr>";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Kontotype</td><td><br></td>";
print "<td><SELECT NAME=kontotype>";
print "<option>$kontotype</option>";
if ($kontotype!='Overskrift') {print "<option>Overskrift</option>";}
if ($kontotype!='Drift') {print "<option>Drift</option>";}
if ($kontotype!='Status') {print "<option>Status</option>";}
if ($kontotype!='Sum') {print "<option>Sum</option>";}
if ($kontotype!='Lukket') {print "<option>Lukket</option>";}
print "</SELECT></td></tr>";

if ($kontotype=='Drift') {
	print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Moms</td><td><br></td>";
	print "<td><SELECT NAME=moms>";
	print "<option>$moms</option>";
	print "<option></option>";
	$query = db_select("select kode, kodenr from grupper where art = 'KM' or art = 'SM' or art = 'EM'");
	while ($row = db_fetch_array($query)) {
		print "<option>";
		print $row['kode'].$row['kodenr'];
		print "</option>";
	}
	print "</SELECT></td></tr>";
}

if ($kontotype=='Sum') {
	print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Konto fra</td><td><br></td><td><input type=text size=6 name=fra_kto value='$fra_kto'></td></tr>";
	print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Konto til</td><td><br></td><td><input type=text size=6 name=til_kto value='$til_kto'></td></tr>";
}

if (($kontotype=='Drift')||($kontotype=='Status')) {
	$x=0;
	$alfabet=array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "X", "Y", "Z"); 
	$tmp=array();
	$query = db_select("select genvej from kontoplan order by genvej");
	while ($row = db_fetch_array($query)) {
		$x++;
		$tmp[$x]=$row[genvej];
	}
	print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Genvej</td><td><br></td>";
	print "<td><SELECT NAME=genvej>";
	print "<option>$genvej</option>";
	for ($x=0; $x<25; $x++) {
		if (!in_array($alfabet[$x], $tmp)) print "<option>$alfabet[$x]</option>";
	}
	print "</SELECT></td></tr>";
}


print "<tr><td><br></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td colspan=3 align=center>";
print "<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
print "<td align=center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"gem\"></td>";
if ($slet==1) {print "<td align = center><input type=submit accesskey=\"s\" value=\"&nbsp;&nbsp;&nbsp;&nbsp;Slet&nbsp;&nbsp;&nbsp;&nbsp;\" name=\"slet\"></td>";}
print "</tr></tbody></table>";

?>
</tbody>
</table>
</td></tr>
<tr><td align = "center" valign = "bottom">
		<table width="100%" align="center" border="1" cellspacing="0" cellpadding="0"><tbody>
			<td width="100%" bgcolor="<?php echo $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><br></small></td>
		</tbody></table>
</td></tr>
</tbody></table>
</body></html>
