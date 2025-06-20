<?php
	@session_start();
	$s_id=session_id();

// --------------debitor/fakturadato.php--------lap 2.0.9-----2009.08.28-----------------
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
// Copyright (c) 2004-2009 DANOSOFT ApS
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

$modulnr=5; 
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
	
$id=if_isset($_GET['id']);	
$pbs=if_isset($_GET['pbs']);
$mail_fakt=if_isset($_GET['mail_fakt']);
$returside=if_isset($_GET['returside']);
$hurtigfakt=if_isset($_GET['hurtigfakt']);

if ($_POST['submit']) {
	
	$fakturadato=($_POST['fakturadato']);
	list($day, $month, $year)=split ("-",$fakturadato);
	if ((strlen($day)==6&&!$month&&!$year&&!is_numeric($day))&&(!checkdate($month,$day,$year))) {
		print "<body onLoad=\"fejltekst('$fakturadato -- er ikke en gyldig dato')\">";
	} else {
		$fakturadate=usdate($fakturadato);
		$r=db_fetch_array(db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'"));
		$year=substr(str_replace(" ","",$r['box2']),-2);
		$aarstart=str_replace(" ","",$year.$r['box1']);
		$year=substr(str_replace(" ","",$r['box4']),-2);
		$aarslut=str_replace(" ","",$year.$r['box3']);
		list($year, $month, $day)=split ("-",$fakturadate);
		$ym=substr($year,-2).$month;
		if (($ym<$aarstart)||($ym>$aarslut)) print "<BODY onLoad=\"fejltekst('Leveringsdato uden for regnskabs&aring;r')\">";
		elseif (checkdate($month,$day,$year)) {
			$tmp=$year."-".$month."-".$day;
			db_modify("update ordrer set fakturadate='$tmp', levdate='$tmp' where id='$id'");
				print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside?id=$id&pbs=$pbs&mail_fakt=$mail_fakt&hurtigfakt=$hurtigfakt\">";

# 			print "<BODY onLoad=\"javascript:window.location = '$returside?id=$id&pbs=$pbs&mail_fakt=$mail_fakt&hurtigfakt=$hurtigfakt	'\">";
			exit;
		} else print "<BODY onLoad=\"fejltekst('Fakturadato ikke gyldig')\">";

	}
}
if (!$fakturadato) $fakturadato=date("d-m-Y");
/*
?>
<script language="Javascript">
var name = prompt("Angiv fakturadato","<?php echo $fakturadato ?>");
</script>
<?php
*/
$r=db_fetch_array(db_select("select art from ordrer where id = '$id'"));
$art=$r['art'];

print "<form name=ordre action=fakturadato.php?returside=$returside&id=$id&pbs=$pbs&mail_fakt=$mail_fakt&hurtigfakt=$hurtigfakt method=post>";
print "<table><tbody>";
if ($art=='DO') print "<tr><td>Angiv fakturadato</td>";
else print "<tr><td>Angiv dato for kreditnota</td>";
print "<td><input type=text name=fakturadato value=$fakturadato></td></tr>";
print "<tr><td align=center colspan=2><input type=submit value=\"&nbsp;OK&nbsp;\" name=\"submit\"></td></tr>";
print"</tbody></table>";
?>

</body></html>
