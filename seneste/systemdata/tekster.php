<?php
// -------------------------------------------- systemdata/tekster.php ------ patch 2.0.9 -----2009.06.25---------------
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

@session_start();
$s_id=session_id();
$modulnr=1;
$css="../css/standard.css";
$title="Tekster";
$rightoptxt="<a href=\"tekster.php?ryd=1\" >ryd</a>";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("top.php");

if ($ryd=if_isset($_GET['ryd'])) {
	db_modify("delete from tekster where sprog_id='$sprog_id'",__FILE__ . " linje " . __LINE__);
}
$sort=if_isset($_GET['sort']);
if (!$sort) $sort="tekst";
$sprog_id=if_isset($_GET['sprog_id']);
$kopier=if_isset($_GET['kopier']);
$title="findtekst(30,$sprog_id)";

if($_POST) {
	$tekstantal=if_isset($_POST['tekstantal']); 
	$id=if_isset($_POST['id']); 
	$tekst=if_isset($_POST['tekst']); 
	$ny_tekst=if_isset($_POST['ny_tekst']); 
	for ($x=1;$x<=$tekstantal;$x++) {
		$tmp=addslashes(trim($ny_tekst[$x]));
		if ($id[$x] && $tmp && $tekst[$x]!=$ny_tekst[$x]) db_modify("update tekster set tekst='$tmp' where id='$id[$x]'",__FILE__ . " linje " . __LINE__);
	}
}
if (!$sprog_id) $sprog_id=1;

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

print "<form name=\"tekster\" action=\"tekster.php?sprog_id=$sprog_id&sort=$sort\" method=\"post\">";
print "<input type=hidden name=tekstantal value=\"$tekstantal\">";

print "<table border=1><tbody>";
print "<tr><td><a href=tekster.php?sprog_id=$sprog&sort=tekst_id>Id</a></td>";
print "<td width=400><a href=tekster.php?sprog_id=$sprog&sort=tekst>".findtekst(31,$sprog_id)."</a></td>";
print "<td title=\"".findtekst(33,$sprog_id)."\">".findtekst(32,$sprog_id)."</td>";
for($x=1; $x<=$tekstantal; $x++){
	print "<input type=hidden name=id[$x] value=\"$id[$x]\">";
	print "<input type=hidden name=tekst[$x] value=\"$tekst[$x]\">";
	print "<tr><td>$tekst_id[$x]</td><td>$tekst[$x]</td>";
#	print "<td><textarea class=\"inputbox\" name=\"ny_tekst[$x]\" rows=\"3\" cols=\"85\">$tekst[$x]</textarea></td>";
	print "<td><input type=text class=\"inputbox\" name=\"ny_tekst[$x]\" size=\"90\" value=\"$tekst[$x]\"></td>";
}
print "<tr><td colspan=3 align=center><input type=submit accesskey=\"o\" value=\"OK\" name=\"submit\"></td></tr>";
print "</form>";
print "</tbody></table>";
?>
</body></html>
