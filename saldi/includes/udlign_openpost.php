<?php
@session_start();
$s_id=session_id();
// -----------------------------------------------------debitor/udlign_openpost.php-------patch 1.0.8------------
// LICENS><small><small>
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
$modulnr=12;
$title="&Aring;benpostudligning";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdecimal.php");
include("../includes/dkdato.php");
include("../includes/usdate.php");
include("../includes/forfaldsdag.php");

if ($_POST['submit']) {
 	$submit=strtolower(trim($_POST['submit']));
	$post_id=$_POST['post_id'];
	$udlign=$_POST['udlign'];
	$regnaar=$_POST['regnaar'];
	$maaned_fra=$_POST['maaned_fra'];
	$maaned_til=$_POST['maaned_til'];
	$konto_fra=$_POST['konto_fra'];
	$konto_til=$_POST['konto_til']; 
	$retur=$_POST['retur'];
} else {
	$post_id[0]=$_GET['post_id'];
	$regnaar=$_GET['regnaar'];
	$maaned_fra=$_GET['maaned_fra'];
	$maaned_til=$_GET['maaned_til'];
	$konto_fra=$_GET['konto_fra'];
	$konto_til=$_GET['konto_til']; 
	$retur=$_GET['retur'];
}

$query = db_select("select * from openpost where id='$post_id[0]'");
if ($row = db_fetch_array($query)) {
	$konto_id[0]=$row[konto_id];
	$refnr[0]=$row['refnr'];
	$amount[0]=$row['amount'];
	$transdate[0]=$row['transdate'];
	$faktnr[0]=$row['faktnr'];
	$kontonr[0]=$row['kontonr'];
	$beskrivelse[0]=$row['beskrivelse'];
	$diff=$amount[0];
	$udlign[0]='on';
	print "<input type = hidden name=konto_id[0] value=$konto_id[0]>";
} else print "<meta http-equiv=\"refresh\" content=\"0;URL=$retur?rapport=kontokort.php\">";

$udlign_date="$transdate[0]";
$x=0;
$query = db_select("select * from openpost where id!='$post_id[0]' and konto_id=$konto_id[0] and udlignet != '1'");
while ($row = db_fetch_array($query)){
	$x++;
	$post_id[$x]=$row[id];
	$refnr[$x]=$row['refnr'];
	$amount[$x]=$row['amount'];
	$transdate[$x]=$row['transdate'];
	$faktnr[$x]=$row['faktnr'];
	$kontonr[$x]=$row['kontonr'];
	$beskrivelse[$x]=$row['beskrivelse'];
	if ($udlign[$x]=='on') {
		$diff=$diff+$amount[$x];	
		if ($transdate[$x]>$udlign_date) $udlign_date=$transdate[$x];
	}
}
$postantal=$x;

print "<table width = 100% cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
print "<tr><td colspan=8 align=center><a href=$retur?rapportart=Kontokort&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til&submit=ok>$font<small><small>Luk</small></small></a></td></tr>";
print "<tr><td><br></td></tr>";
#
if ($submit=='udlign') {
	for ($x=0; $x<=$postantal; $x++) {
		$query = db_select("select MAX(udlign_id) as udlign_id from openpost");
		if ($row = db_fetch_array($query)) $udlign_id=$row['udlign_id']+1;
		if ($udlign[$x]=='on') db_modify("UPDATE openpost set udlignet='1', udlign_id='$udlign_id', udlign_date='$udlign_date' where id = $post_id[$x]");
	}
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$retur?rapportart=Kontokort&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til&submit=ok\">";
	exit;
}
#
if ($diff==0) print "<tr><td colspan=6>$font<small><small>F&oslash;lgende poster vil blive udlignet:</small></small></td></tr>";
else print "<tr><td colspan=6>$font<small><small>S&aelig;t \"flueben\" ud for de posteringer der skal udligne f&oslash;lgende post:</small></small></td></tr>";
print "<tr><td></td></tr><tr bgcolor=\"$linjebg\"><td>$font<small><small>".dkdato($transdate[0])."</small></small></td>
	<td>$font<small><small>$refnr[0]</small></small></td>
	<td>$font<small><small>$faktnr[0]</small></small></td>
	<td>$font<small><small>$beskrivelse[0]</small></small></td>
	<td align=right><span style='color: rgb(0, 0, 0);'>$font<small><small>".dkdecimal($amount[0])."</small></small></td></tr>";
if ($diff!=0) print "<tr><td colspan=6><hr></td></tr>";
print "<form name=kontoudtog action=../includes/udlign_openpost.php method=post>";
if ($diff!=0) {
	for ($x=1; $x<=$postantal; $x++) {
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\"><td>$font<small><small>".dkdato($transdate[$x])."</small></small></td>
			<td>$font<small><small>$refnr[$x]</small></small></td>
			<td>$font<small><small>$faktnr[$x]</small></small></td>
			<td>$font<small><small>$beskrivelse[$x]</small></small></td>
			<td align=right><span style='color: rgb(0, 0, 0);'>$font<small><small>".dkdecimal($amount[$x])."</small></small></td>";
		if ($udlign[$x]!='on') print "<td align=center><input type=checkbox name=udlign[$x]></td></tr>";
		else print "<td align=center><input type=checkbox name=udlign[$x] checked></td></tr>";
	}
} else {
	for ($x=1; $x<=$postantal; $x++) {
		if ($udlign[$x]=='on') {
			if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
			print "<tr bgcolor=\"$linjebg\"><td>$font<small><small>".dkdato($transdate[$x])."</small></small></td>
				<td>$font<small><small>$refnr[$x]</small></small></td>
				<td>$font<small><small>$faktnr[$x]</small></small></td>
				<td>$font<small><small>$beskrivelse[$x]</small></small></td>
				<td align=right><span style='color: rgb(0, 0, 0);'>$font<small><small>".dkdecimal($amount[$x])."</small></small></td>";
			print "<input type = hidden name=udlign[$x] value=$udlign[$x]>";
		}
	}
}
print "<tr><td colspan=6><hr></td></tr>";
print "<tr><td colspan=3></td><td>$font<small><small>Difference</small></small></td><td align=right>$font<small><small>".dkdecimal($diff)."</small></small></td></tr>";
print "<tr><td colspan=6><hr></td></tr>";

print "<input type = hidden name=post_id[0] value=$post_id[0]>";
print "<input type = hidden name=maaned_fra value=$maaned_fra>";
print "<input type = hidden name=maaned_til value=$maaned_til>";
print "<input type = hidden name=konto_fra value=$konto_fra>";
print "<input type = hidden name=konto_til value=$konto_til>";
print "<input type = hidden name=regnaar value=$regnaar>";
print "<input type = hidden name=retur value=$retur>";
if ($diff==0) print "<tr><td colspan=10 align=center>$font<small><small><input type=submit value=\"Udlign\" name=\"submit\"></td></tr>";
else print "<tr><td colspan=10 align=center>$font<small><small><input type=submit value=\"Vis\" name=\"submit\"></td></tr>";

print "</form>\n";

?>

