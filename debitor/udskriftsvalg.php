<?php #topkode_start
@session_start();
$s_id=session_id();

// ----------debitor/udskriftsvalg.php----------------lap 3.1.9----2014-01-12-------------
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
// Copyright (c) 2004-2013 DANOSOFT ApS
// ----------------------------------------------------------------------
// 
// 2013.01.17 Oprydning i forb. med fejlsøgning i ret_genfakt.php
// 2014.01.12 Fremover vises plukliste og følgeseddel kun for lagervarer.

$title="Udskriftsvalg";
$css="../css/standard.css";


include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$id=if_isset($_GET['id']);
$valg=if_isset($_GET['valg']);
$formular=if_isset($_GET['formular']);

#cho "P $popup<br>";

if ($valg=="tilbage") {
	if ($popup) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
	else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php??tjek=$id&id=$id\">";
	exit;
}

if ($valg) {
	$query = db_select("select box1, box2 from grupper where art='PV'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	if ($valg==-1)	$ps_fil="formularprint.php?id=$id&formular=$formular";
	else $ps_fil="formularprint.php?id=$id&formular=3";

#	if ((!file_exists($ps_fil))&&($ps_fil!="udskriftsvalg.php"))	{
#		if (!file_exists("../formularer/$db_id")) {mkdir("../formularer/$db_id",0777);}
#		$kildefil=str_replace("/$db_id", "", $ps_fil);
#		copy($kildefil, $ps_fil);
#	}
	if ($valg!=-1) $ps_fil="formularprint.php?id=$id&formular=3&lev_nr=$valg";
	echo "<meta http-equiv=refresh content=0;url=$ps_fil>";
	exit;
#	print "<BODY onLoad=\"JavaScript:window.open('$ps_fil&id=$id' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
#	print "<body onload=\"javascript:window.close();\">";
}

if (db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '3' and box4='on'",__FILE__ . " linje " . __LINE__))) {
#	$hurtigfakt='on';
	print "<meta http-equiv=refresh content=0;url='udskriftsvalg.php?id=$id&valg=-1&formular=2'>";
	exit;
}

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" height=\"1%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><a href=udskriftsvalg.php?valg=tilbage&id=$id accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund align=\"center\">Udskriftsvalg</td>";
print "<td width=\"10%\" $top_bund align = \"right\"><br></td>";
print "</tbody></table>";
print "</td></tr>";
print "<td align = \"center\" valign = \"middle\" height=\"99%\">";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
$leveres=0;
$q = db_select("select leveres from ordrelinjer where ordre_id = $id",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$leveres=$leveres+$r['leveres'];
}
$lev_nr=0; 
$q = db_select("select * from batch_salg where ordre_id = $id",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	if ($r['lev_nr']>$lev_nr) {$lev_nr=$r['lev_nr'];}
}
if ($leveres) print "<tr><td align=center> <a href='udskriftsvalg.php?id=$id&valg=-1&formular=9'>Plukliste</a></td></tr>";
print "<tr><td align=center> <a href='udskriftsvalg.php?id=$id&valg=-1&formular=2'>Ordrebekr&aelig;ftelse</a></td></tr>";
for ($x=1; $x<=$lev_nr; $x++) {
	print "<tr><td align=center> <a href='udskriftsvalg.php?id=$id&valg=$x&formular=3'>F&oslash;lgeseddel $x</a></td></tr>";
}
print "</tbody></table></td>";
print "</tbody></table>";


exit;


