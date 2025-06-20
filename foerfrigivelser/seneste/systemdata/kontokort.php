<?php
// ---------systemdata/kontokort.php---------lap 1.9.2----26.03.2008--------
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
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------



@session_start();
$s_id=session_id();
$title="Kontokort";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/db_query.php");
include("../includes/genberegn.php");

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
	$til_kto=$_POST['kontonr'];
	$genvej=$_POST['genvej'];
	$lukket=$_POST['lukket'];
	if ($kontotype=='Overskrift'){
		$kontotype='H';
		$moms="";
		$fra_kto=0;
		$til_kto=0;
	}
	elseif ($kontotype=='Drift') $kontotype='D';
	elseif ($kontotype=='Status') $kontotype='S';
	elseif ($kontotype=='Lukket') $kontotype='L';
	elseif ($kontotype=='Sum'){
		$kontotype='Z';
		$moms="";
	}
	elseif ($kontotype=='Sideskift') $kontotype='X';
	

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
	genberegn($regnaar);
}
if ($id > 0){
	$query = db_select("select * from kontoplan where id = '$id'");
	if ($row = db_fetch_array($query)){
		$id=$row['id'];
		$kontonr=$row['kontonr']*1;
		$beskrivelse=htmlentities(stripslashes($row['beskrivelse']));
		$kontotype=$row['kontotype'];
		$katagori=$row['katagori'];
		$moms=$row['moms'];
		$fra_kto=$row['fra_kto'];
#		$til_kto=$row['til_kto'];
		$genvej=$row['genvej'];
		$lukket=$row['lukket'];
		$saldo=$row['saldo'];
	
		$r=db_fetch_array(db_select("select id from kontoplan where kontonr < '$kontonr' order by kontonr desc"));
		$forrige=$r['id']*1;	
		$r=db_fetch_array(db_select("select id from kontoplan where kontonr > '$kontonr' order by kontonr"));
		$naeste=$r['id']*1;	
	}
	if (!$kontonr) {
		print "<META HTTP-EQUIV=REFRESH CONTENT=\"0; URL=kontoplan.php\">";
		exit;
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

	$query = db_select("select id from transaktioner where transdate>'$regnstart' and transdate<'$regnslut' and kontonr='$kontonr'");
	if ($row = db_fetch_array($query)){$slet=0;}
	else {$slet=1;}

}

if ($kontotype=='H'){$kontotype='Overskrift';}
elseif($kontotype=='D'){$kontotype='Drift';}
elseif($kontotype=='S'){$kontotype='Status';}
elseif($kontotype=='Z'){$kontotype='Sum';}
elseif($kontotype=='X'){$kontotype='Sideskift';}


print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\"  height=1% valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<tr><td width=\"10%\" $top_bund >$font<small><a href=kontoplan.php accesskey=L>Luk</a></small></td>";
print "<td width=\"80%\"  $top_bund > $font <small>Kontokort</small></td>";
print "<td width=\"10%\"  $top_bund > $font<small><a href=kontokort.php accesskey=N>Ny</a><br></small></td></tr>";
if ($forrige) print "<tr><td colspan=2><a href='kontokort.php?id=$forrige'><img src=../ikoner/left.gif style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
else print "<tr><td colspan=2></td>";
if ($naeste) print "<td align=\"right\"><a href='kontokort.php?id=$naeste'><img src=../ikoner/right.gif style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
else print "<td></td></tr>";
print "</tbody></table>";
print "</td></tr>";
print "<td align = center valign = center height=99%>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";



print "<form name=kontokort action=kontokort.php method=post>";
print "<input type=hidden name=id value='$id'>";

if ($id && $saldo) {
	print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Kontonr</td><td><br></td><td colspan=2>$font $kontonr</td></tr>\n";
	print "<input type=hidden name=kontonr value=\"$kontonr\">";
}
else {print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Kontonr</td><td><br></td><td colspan=2><input type=text size=6 name=kontonr value=\"$kontonr\"></td></tr>\n";}
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Beskrivelse</td><td><br></td><td colspan=2><input type=text size=25 name=beskrivelse value=\"$beskrivelse\"></td></tr>\n";
print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Kontotype</td><td><br></td>";
print "<td colspan=2><SELECT NAME=kontotype>";
#print "<td>sa $saldo sl $slet<br></td>";
if ($kontotype) print "<option>$kontotype</option>\n";
if (!$saldo) {
	if ($kontotype!='Overskrift') {print "<option>Overskrift</option>\n";}
	if ($kontotype!='Drift') {print "<option>Drift</option>\n";}
	if ($kontotype!='Status') {print "<option>Status</option>\n";}
	if ($kontotype!='Sum') {print "<option>Sum</option>\n";}
	$r=db_fetch_array($query = db_select("select id from kontoplan where regnskabsaar = '$regnaar' and kontotype='X'"));
	if ((!$r[id]) && ($kontotype!='Sideskift')) {print "<option>Sideskift</option>\n";}
}
print "</SELECT></td></tr>\n";

if ($kontotype=='Drift'||$kontotype=='Status') {
	print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Moms</td><td><br></td>";
	print "<td colspan=2><SELECT NAME=moms>";
	print "<option>$moms</option>\n";
	$query = db_select("select kode, kodenr from grupper where art = 'KM' or art = 'SM' or art = 'EM'");
	if ($moms) print "<option></option>\n";
	while ($row = db_fetch_array($query)) {
		$tmp=$row['kode'].$row['kodenr'];
		if ($moms!=$tmp) print "<option>$tmp</option>\n";
	}
	print "</SELECT></td></tr>\n";
}

if ($kontotype=='Sum') {
	print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Konto fra</td><td><br></td><td><input type=text size=6 name=fra_kto value='$fra_kto'></td></tr>\n";
#	print "<tr><td><font face=\"Helvetica, Arial, sans-serif\">Konto til</td><td><br></td><td><input type=text size=6 name=til_kto value='$til_kto'></td></tr>\n";
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
	print "<option>$genvej</option>\n";
	for ($x=0; $x<25; $x++) {
		if (!in_array($alfabet[$x], $tmp)) print "<option>$alfabet[$x]</option>\n";
	}
	print "</SELECT></td>";
}
if ($lukket=='on')  $lukket="checked";
if (($kontotype=='Overskrift')||($kontotype=='Sum')) print "<tr><td colspan= 2>$font  Lukket</td><td>";
else print "<td align=right>$font Lukket &nbsp;";
print "<input type=checkbox name=lukket $lukket></td></tr>\n";
print "<tr><td><br></td></tr>\n";
print "<tr><td><br></td></tr>\n";
print "<tr><td colspan=4 align=center>";
print "<table width=\"100%\" cellpadding=\"0\" cellspaci ng=\"0\" border=\"0\"><tbody>";
print "<td align=center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"gem\"></td>";
if ($slet==1) print "<td align = center><input type=submit accesskey=\"s\" value=\"&nbsp;&nbsp;&nbsp;&nbsp;Slet&nbsp;&nbsp;&nbsp;&nbsp;\" name=\"slet\"></td>";
print "</tr>\n</tbody></table>";

?>
</tbody>
</table>
</td></tr>
<tr><td align = "center" valign = "bottom">
		<table width="100%" align="center" border="1" cellspacing="0" cellpadding="0"><tbody>
			<td width="100%" <?php echo $top_bund ?>><font face="Helvetica, Arial, sans-serif" color="#000066"><small><br></small></td>
		</tbody></table>
</td></tr>
</tbody></table>
</body></html>
