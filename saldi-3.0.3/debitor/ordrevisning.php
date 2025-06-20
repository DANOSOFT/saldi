<?php

// --------debitor/ordrevisning.php-----lap 2.1.7-------2010.04.12-----------
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

if (isset($_GET['valg'])) $valg=($_GET['valg']);
else $valg="ordrer";

if ($valg=="tilbud") $title="Tilbudsvisning";
elseif ($valg=="ordrer") $title="Ordrevisning";
else $title="Fakturavisning";

$modulnr=6;
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/std_func.php");

#$side=if_isset($_GET['side']);
$sort=trim(if_isset($_GET['sort']));
#$valg=trim(if_isset($_GET['valg']));

if ($popup) $returside="../includes/luk.php"; 
else $returside="$side.php";
	

if (isset($_POST) && $_POST) {
	$vis_feltantal=if_isset($_POST['vis_feltantal']);
	$vis_linjeantal=if_isset($_POST['vis_linjeantal']);
	$vis_felt=if_isset($_POST['vis_felt']);
	$feltbredde=if_isset($_POST['feltbredde']);
	$justering=if_isset($_POST['justering']);
	$feltnavn=if_isset($_POST['feltnavn']);
	
#	if (!isset($vis_felt[0])) $vis_felt[0]="";
	$box3='ordrenr';
	$box4=$feltbredde[0]*1;
	$box5=$justering[0];
	$box6=addslashes($feltnavn[0]);
	$box7=$vis_linjeantal*1;
	if (!$vis_linjeantal) $vis_linjeantal=50; 
	for ($x=1;$x<=$vis_feltantal;$x++) {
		if (!isset($vis_felt[$x])) $vis_felt[$x]="";
		$box3=$box3.",".$vis_felt[$x];
		$feltbredde[$x]=$feltbredde[$x]*1;
		$box4=$box4.",".$feltbredde[$x];
		$box5=$box5.",".$justering[$x];
		$box6=$box6.",".addslashes($feltnavn[$x]);
	}
	
	db_modify("update grupper set box3='$box3',box4='$box4',box5='$box5',box6='$box6',box7='$vis_linjeantal' where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__);
}

print "<div align=\"center\">
<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
	<tr><td height = \"25\" align=\"center\" valign=\"top\">
		<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"4\" cellpadding=\"0\"><tbody>
			<td width=\"10%\" align=center><div class=\"top_bund\"><a href=ordreliste.php?valg=$valg&sort=$sort accesskey=L>Luk</a></div></td>
			<td width=\"80%\" align=center><div class=\"top_bund\">$title</a></div></td>
			<td width=\"10%\" align=center><div class=\"top_bund\"><br></div></td>
			 </tr>
			</tbody></table>
	</td></tr>
 <tr><td valign=\"top\">
<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">
<tbody>";

print "<form name=ordrevisning action=ordrevisning.php?sort=$sort&valg=$valg method=post>";
$felter=array("konto_id","firmanavn","addr1","addr2","postnr","bynavn","land","kontakt","kundeordnr","lev_navn","lev_addr1","lev_addr2","lev_postnr","lev_bynavn","lev_kontakt","ean","institution","betalingsbet","betalingsdage","kontonr","cvrnr","art","ordredate","levdate","fakturadate","notes","sum","momssats","status","ref","fakturanr","modtagelse","kred_ord_id","lev_adr","kostpris","moms","hvem","tidspkt","nextfakt","betalt","projekt","valuta","valutakurs","sprog","email","mail_fakt","pbs","mail","mail_cc","mail_bcc","mail_subj","mail_text","felt_1","felt_2","felt_3","felt_4","felt_5","vis_lev_addr","udskriv_til","restordre");

sort($felter);
$feltantal=count($felter);
print "<tr><td colspan=6>V&aelig;lg hvilke felter der skal v&aelig;re synlige p&aring; oversigten</td></tr>";
print "<tr><td colspan=6>Ordrenr kan ikke frav&aelig;lges</td></tr>";
print "<tr><td colspan=6><hr></td></tr>";

$r = db_fetch_array(db_select("select box3,box4,box5,box6,box7 from grupper where art = 'OLV' and kode ='$valg' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__));
$vis_felt=split(",",$r['box3']);
$feltbredde=split(",",$r['box4']);
$justering=split(",",$r['box5']);
$feltnavn=split(",",$r['box6']);
$vis_linjeantal=$r['box7'];
$vis_feltantal=count($vis_felt)-1;

#$tmp=$vis_feltantal-1;
#echo "ZZ $vis_felt[$tmp]<br>";
/*
if (!in_array("kontonr",$vis_felt)) {
	$vis_felt[$vis_feltantal]="kontonr";
	$vis_feltantal++;
}
*/
print "<tr><td>Antal felter p&aring; fakturaoversigten</td><td><input type=text style=\"text-align:right\" size=2 name=vis_feltantal value=$vis_feltantal></td></tr>";
print "<tr><td>Antal linjer p&aring; fakturaoversigten</td><td><input type=text style=\"text-align:right\" size=2 name=vis_linjeantal value=$vis_linjeantal></td></tr>";
print "<tr><td colspan=6><hr></td></tr>";	
print "<tr><td colspan=2><b>Felt</b></td><td><b>Valgfri overskrift</b></td><td><b>Feltbredde</b></td><td><b>Justering</b></td></tr>";
if (!$feltnavn[0]) $feltnavn[0]="Ordrenr";
print "<tr><td colspan=2>Ordrenr</td>";
print "<td><input name=feltnavn[0] size=20 value=$feltnavn[0]></td>";
print "<td><input name=feltbredde[0] style=\"text-align:right\" size=2 value=$feltbredde[0]></td>";
print "<td><SELECT NAME=justering[0]>";
if ($justering[0]) print "<option>$justering[0]</option>";
if ($justering[0] != "L") print "<option value=\"left\">left</option>"; 
if ($justering[0] != "C") print "<option value=\"center\">center</option>"; 
if ($justering[0] != "R") print "<option value=\"right\">right</option>"; 
print "</SELECT></td></tr>";
for ($x=1;$x<=$vis_feltantal;$x++) {
if (!$feltnavn[$x]) $feltnavn[$x]=$vis_felt[$x];
	print "<tr><td colspan=2><SELECT NAME=vis_felt[$x]>";
	print "<option>$vis_felt[$x]</option>";
	for ($y=0;$y<$feltantal;$y++) {
		if ($felter[$y]!=$vis_felt[$x]) print "<option>$felter[$y]</option>";
	}
	print "</SELECT></td>";
	print "<td><input name=feltnavn[$x] size=20 value=$feltnavn[$x]></td>";
	print "<td><input name=feltbredde[$x] size=2 style=\"text-align:right\" value=$feltbredde[$x]></td>";
	print "<td><SELECT NAME=justering[$x]>";
	if ($justering[$x]) print "<option value=\"$justering[$x]\">$justering[$x]</option>";
	if ($justering[$x] != "L") print "<option value=\"left\">left</option>"; 
	if ($justering[$x] != "C") print "<option value=\"center\">center</option>"; 
	if ($justering[$x] != "R") print "<option value=\"right\">right</option>"; 
	print "</SELECT></td></tr>";
}
print "<tr><td colspan=6><hr></td></tr>\n";
print "<tr><td colspan=6 align = center><input type=submit accesskey=\"a\" value=\"OK\" name=\"submit\"></td></tr>\n";
print "</form>"
?>
</tbody></table>

</body></html>
