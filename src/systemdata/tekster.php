<?php
// ----------------------------- systemdata/tekster.php ----------------- ver 4.0.1 -- 2021-08-20 --
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2021 saldi.dk aps
// ----------------------------------------------------------------------
// 20210818 LOE Updated some line here
// 20210819 Added some blocks of codes and added an option to download edited text as csv file

@session_start();
$s_id=session_id();
$modulnr=1;
$css="../css/standard.css";
$title="Tekster";
#$rightoptxt="<a href=\"tekster.php?ryd=1\" >ryd</a>"; #20210819 

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if (!isset ($sprog)) $sprog = null;
global $db; 
if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">\n";
	print "<div class=\"headerbtnLft\"></div>\n";
#	print "<span class=\"headerTxt\">Systemsetup</span>\n";     
#	print "<div class=\"headerbtnRght\"><!--<a href=\"index.php?page=../debitor/debitorkort.php;title=debitor\" class=\"button green small right\">Ny debitor</a>--></div>";       
	print "</div><!-- end of header -->";
	print "<div id=\"leftmenuholder\">";
	include_once 'left_div_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontentLargeHolder\">\n";
} else include("top.php");


// if($mod = if_isset($_GET['mod'])){ #20210819
// 	$x=0;
// 		$q = db_select("select * from tekster where tekst='Danish' order by id",__FILE__ . " linje " . __LINE__);
// 		while ($r = db_fetch_array($q)) {
// 			$x++;
// 			$id[$x]=$r['id'];
// 			$Dtekst_id[$x]=$r['tekst_id'];
// 			$Dtekst[$x]=$r['tekst'];
// 			$Danish_Langid = $r['sprog_id'];
// 		}
// 	$tekstantal=$x;

// 		print "<form name=\"tekster\" action=\"tekster.php?sprog_id=$sprog_id&sort=$sort\" method=\"post\">";
// 		print "<input type=hidden name=tekstantal value=\"$tekstantal\">";
// 		print "<table class='dataTable2'><tbody>";
// 	   #print "<tr><td><a href=tekster.php?sprog_id=$sprog&sort=tekst_id>Id</a></td>";
// 	   print "<tr><td><a href=tekster.php?sprog_id=$Danish_Langid&sort=tekst_id>Id</a></td>"; #20210819
// 	   #print "<td width=400><a href=tekster.php?sprog_id=$sprog&sort=tekst>".findtekst('31|Nuværende tekst', $sprog_id)."</a></td>";
// 	   print "<td width=400><a href=tekster.php?sprog_id=$Danish_Langid&sort=tekst>".findtekst('31|Nuværende tekst', $sprog_id)."</a></td>"; 
// 	   print "<td title=\"".findtekst('33|Skriv nyt tekstforslag', $sprog_id)."\">".findtekst('32|Ny tekst', $sprog_id)."</td>";
// 	   for($x=1; $x<=$tekstantal; $x++){
// 		   print "<input type=hidden name=id[$x] value=\"$id[$x]\">";
// 		   #print "<input type=hidden name=tekst[$x] value=\"$tekst[$x]\">";
// 		   print "<tr><td><input type=hidden name=tekst[$x] value=\"$tekst[$x]\"></td>"; #20210819 Without adding the td tags...it breaks the page
// 		   print "<tr><td>$tekst_id[$x]</td><td>$tekst[$x]</td>";
// 	   #	print "<td><textarea class=\"inputbox\" name=\"danish_tekst[$x]\" rows=\"3\" cols=\"85\">$tekst[$x]</textarea></td>";
// 		   print "<td><input type=text class=\"inputbox\" name=\"ny_tekst[$x]\" size=\"90\" value=\"$tekst[$x]\"></td>";
// 	   }
// 	   print "<tr><td colspan=3 align=center><input class='button blue medium' type=submit accesskey=\"o\" value=\"OK\" name=\"submit\"></td></tr>";
// 	   print "</form>";
// 	   print "</tbody></table>";


// }


if ($ryd=if_isset($_GET['ryd'])) {
	db_modify("delete from tekster where sprog_id='$sprog_id'",__FILE__ . " linje " . __LINE__);
}
$sort=if_isset($_GET['sort']);
if (!$sort) $sort="tekst";

$sprog_id=if_isset($_GET['sprog_id']);
$kopier=if_isset($_GET['kopier']);
#$title= findtekst('30|Tilbage', $sprog_id); #20210818

if($_POST) {
	$tekstantal=if_isset($_POST['tekstantal']); 
	$id=if_isset($_POST['id']); 
	$tekst=if_isset($_POST['tekst']); 
	$ny_tekst=if_isset($_POST['ny_tekst']); 
	for ($x=1;$x<=$tekstantal;$x++) {
		$tmp=db_escape_string(trim($ny_tekst[$x]));
		if ($id[$x] && $tmp && $tekst[$x]!=$ny_tekst[$x]) db_modify("update tekster set tekst='$tmp' where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
	}
}
#if (!$sprog_id) $sprog_id=1;
if (!$sprog_id) echo "Sprog_id not found"; #20210818



$x=0;
if ($kopier) {
	$q = db_select("select * from tekster where sprog_id=$kopier order by tekst",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$tekst_id[$x]=$r['tekst_id'];
		$tekst[$x]=$r['tekst'];
	}
}	
$tekstantal=$x;
for($x=1; $x<=$tekstantal; $x++){
	if (!$r=db_fetch_array(db_select("select id from tekster where sprog_id=$sprog_id and tekst_id=$tekst_id[$x] order by $sort",__FILE__ . " linje " . __LINE__))){
		db_modify("insert into tekster (sprog_id,tekst_id,tekst)values('$sprog_id','$tekst_id[$x]','$tekst[$x]')"); 
	} else {
		db_modify("update tekster set tekst='$tekst[$x]' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
	}
}
$x=0;
$q = db_select("select * from tekster where sprog_id=$sprog_id order by $sort",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$x++;
	$id[$x]=$r['id'];
	$tekst_id[$x]=$r['tekst_id'];
	$tekst[$x]=$r['tekst'];
}
$tekstantal=$x;

#$csvfile="../$db/importfiler/teksterA.csv";
$csvfile="../importfiler/teksterA.csv"; #20210819 


print "<form name=\"tekster\" action=\"tekster.php?sprog_id=$sprog_id&sort=$sort\" method=\"post\">";
 print "<input type=hidden name=tekstantal value=\"$tekstantal\">";
 print "<table class='dataTable2'><tbody>";
#print "<tr><td><a href=tekster.php?sprog_id=$sprog&sort=tekst_id>Id</a></td>";
print "<tr><td><a href=tekster.php?sprog_id=$sprog_id&sort=tekst_id>Id</a></td>"; #20210819
#print "<td width=400><a href=tekster.php?sprog_id=$sprog&sort=tekst>".findtekst('31|Nuværende tekst', $sprog_id)."</a></td>";
print "<td width=400><a href=tekster.php?sprog_id=$sprog_id&sort=tekst>".findtekst('31|Nuværende tekst', $sprog_id)."</a></td>"; 
print "<td title=\"".findtekst('33|Skriv nyt tekstforslag', $sprog_id)."\">".findtekst('32|Ny tekst', $sprog_id)."</td>";
for($x=1; $x<=$tekstantal; $x++){
	print "<input type=hidden name=id[$x] value=\"$id[$x]\">";
	#print "<input type=hidden name=tekst[$x] value=\"$tekst[$x]\">";
	print "<tr><td><input type=hidden name=tekst[$x] value=\"$tekst[$x]\"></td>"; #20210819 Without adding the td tags...it breaks the page
	print "<tr><td>$tekst_id[$x]</td><td>$tekst[$x]</td>";
#	print "<td><textarea class=\"inputbox\" name=\"ny_tekst[$x]\" rows=\"3\" cols=\"85\">$tekst[$x]</textarea></td>";
	print "<td><input type=text class=\"inputbox\" name=\"ny_tekst[$x]\" size=\"90\" value=\"$tekst[$x]\"></td>";
	fputcsv($csv, [$tekst_id[$x], $tekst[$x]]);
}
print "<tr><td colspan=3 align=center><input class='button blue medium' type=submit accesskey=\"o\" value=\"OK\" name=\"submit\"></td></tr>";
print "</form>";
print "<td width=\"10%\" $top_bund><a href='$csvfile'>csv</a></td>"; #20210819
fclose($csv);
print "</tbody></table>";

?>
</body></html>
