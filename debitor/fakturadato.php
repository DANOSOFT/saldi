<?php
	@session_start();
	$s_id=session_id();

// --------------debitor/fakturadato.php--------lap 3.2.7-----2012.01.26-----------------
// LICENS
//
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
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------
?>
<script language="JavaScript">
<!--
function fejltekst(tekst) {
	alert(tekst);
}
-->
</script>
<?php

$css="../css/standard.css";
$modulnr=5;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$id=if_isset($_GET['id']);
$pbs=if_isset($_GET['pbs']);
$mail_fakt=if_isset($_GET['mail_fakt']);
$returside=if_isset($_GET['returside']);
$hurtigfakt=if_isset($_GET['hurtigfakt']);

$submit=if_isset($_POST['submit']);
if ($submit=='OK') {

	$fakturadato=($_POST['fakturadato']);
	list($day, $month, $year)=explode ("-",$fakturadato);
	if ((strlen($day)==6&&!$month&&!$year&&!is_numeric($day))&&(!checkdate($month,$day,$year))) {
		print "<body onLoad=\"fejltekst('$fakturadato -- er ikke en gyldig dato')\">";
	} else {
		$fakturadate=usdate($fakturadato);
		if (!$hurtigfakt) {
			$r=db_fetch_array(db_select("select levdate from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
			$levdate=$r['levdate'];
		}
		$r=db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__));
		$year=substr(str_replace(" ","",$r['box2']),-2);
		$aarstart=str_replace(" ","",$year.$r['box1']);
		$year=substr(str_replace(" ","",$r['box4']),-2);
		$aarslut=str_replace(" ","",$year.$r['box3']);
		list($year, $month, $day)=explode("-",$fakturadate);
		$ym=substr($year,-2).$month;
		if (($ym<$aarstart)||($ym>$aarslut)) print "<BODY onLoad=\"fejltekst('Fakturadato uden for regnskabs&aring;r')\">";
		elseif (checkdate($month,$day,$year)) {
			$tmp=$year."-".$month."-".$day;
			if ($hurtigfakt) $levdate=$tmp;
			db_modify("update ordrer set fakturadate='$tmp', levdate='$levdate' where id='$id'",__FILE__ . " linje " . __LINE__);
				print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside?id=$id&pbs=$pbs&mail_fakt=$mail_fakt&hurtigfakt=$hurtigfakt\">";

# 			print "<BODY onLoad=\"javascript:window.location = '$returside?id=$id&pbs=$pbs&mail_fakt=$mail_fakt&hurtigfakt=$hurtigfakt	'\">";
			exit;
		} else print "<BODY onLoad=\"fejltekst('Fakturadato ikke gyldig')\">";

	}
} elseif ($submit=='Fortryd') {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/ordre.php?id=$id\">";
	exit;
}
if (!$fakturadato) $fakturadato=date("d-m-Y");
/*
?>
<script language="Javascript">
var name = prompt("Angiv fakturadato","<?php echo $fakturadato ?>");
</script>
<?php
*/
$r=db_fetch_array(db_select("select art from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
$art=$r['art'];

print "<form name=ordre action=fakturadato.php?returside=$returside&id=$id&pbs=$pbs&mail_fakt=$mail_fakt&hurtigfakt=$hurtigfakt method=post>";
print "<table><tbody>";
if ($art=='DO') print "<tr><td>Angiv fakturadato</td>";
else print "<tr><td>Angiv dato for kreditnota</td>";
print "<td><input class=\"inputbox\" type=\"text\" name=\"fakturadato\" value=\"$fakturadato\" style=\"text-align:left;width:80px\"></td></tr>";
print "<tr><td align=center><input style=\"width: 6em\" type=submit value=\"OK\" name=\"submit\"></td>";
print "<td align=center><input style=\"width: 6em\" type=submit value=\"Fortryd\" name=\"submit\"></td></tr>";
print"</tbody></table>";
?>

</body></html>
