<?php
// -------------systemdata/prislister.php ----------- lap 3.5.5 -- 08.05.2015 -
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------------

@session_start();
$s_id=session_id();

$modulnr=2;
$title="valutakort";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");
include("../includes/top.php");

#if ($menu=='T') {
#        include_once '../includes/top_header.php';
#        include_once '../includes/top_menu.php';
#        print "<div id=\"header\">\n";
#        print "<div class=\"headerbtnLft\"></div>\n";
#        print "</div><!-- end of header -->";
#        print "<div id=\"leftmenuholder\">";
#        include_once 'left_menu.php';
#        print "</div><!-- end of leftmenuholder -->\n";
#        print "<div class=\"maincontent\">\n";
#        print "<table border=\"1\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\"><tbody>";
#} else {
#        include("top.php");
#        print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" align=\"center\"><tbody>";
#}

$splitter=$_POST['splitter'];
$feltantal=$_POST['feltantal'];
$feltlaengde=$_POST['feltlaengde'];

$splitterliste=array("komma","semikolon","tabulator","fast længde");

print "\n<table><tbody>\n";
print "<form name=\"prislister\" action=\"prislister.php?id=$id\" method=\"post\" autocomplete=\"off\">\n";
print "<tr><td>Antal felter</td><td>Feltesperator</td></tr>\n";
print "<tr>\n";
print "  <td><input name=\"feltantal\" value=\"$feltantal\"></td>\n";
print "  <td><select name=\"splitter\">\n";
for ($x=0;$x<count($splitterliste);$x++) {
	if ($splitter == $x) print "    <option value=\"$x\">$splitterliste[$x]</option>\n";
}
for ($x=0;$x<count($splitterliste);$x++) {
	if ($splitter != $x) print "    <option value=\"$x\">$splitterliste[$x]</option>\n";
}
print "  </select>\n</td></tr>\n";
if ($splitter==3) {
	for ($x=0;$x<$feltantal;$x++) {
		$y=$x+1;
		print "<tr><td><input name=\"feltlaengde[$x]\" value=\"$feltlaengde[$x]\" placeholder=\"Længde felt $y\"></td></tr>\n";
	}
}
print "<tr><td><input type=\"submit\" name=\"ok\" value=\"OK\"></td></tr>\n";
print "</tbody></table>\n\n";
# value=\"$separator\"></td></tr>

include_once '../includes/bund.php';
?>
