<?php
// ------- systemdata/designer.php -------- lap 2.0.7 ----2009-05-15-------
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

$modulnr=6;
$kortnr=1;
$title="designer";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/db_query.php");

if ($ny=if_isset($_GET['ny'])) ny($ny);

	
	if ($_POST) {
	$antal_tekster=$_POST['tekstantal'];
	$id=$_POST['id'];
	$style=$_POST['style'];
	$tekst_id[$x]=$_POST['tekst_id'];
}
 
$x=0;
$q=db_select("select * from design where art='JOBKORT' order by pos",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)){
	$x++;
	$id[$x]=$r['id'];
	$style[$x]=$r['style'];
	$tekst_id[$x]=$r['tekst_id'];
	$tekst[$x]=findtekst($tekst_id,$sprog_id);
}
$antal_tekster=$x;
print "<input type=hidden name=antal_tekster value=$antal_tekster>";
print "<input type=hidden name=antal_rammer value=$antal_rammer>";
print "<input type=hidden name=antal_input value=$antal_input>";
print "<input type=hidden name=antal_variabler value=$antal_variabler>";

print "<div style=\"color: rgb(0, 0, 0); position:absolute;left:10px;top:10px\"><a href=designer.php?ny=ramme>Ny ramme</a></div>"; 
print "<div style=\"color: rgb(0, 0, 0); position:absolute;left:100px;top:10px\"><a href=designer.php?ny=tekst>Ny tekst</a></div>"; 
print "<div style=\"color: rgb(0, 0, 0); position:absolute;left:200px;top:10px\"><a href=designer.php?ny=ramme>Ny variabel</a></div>";

print "<div style=\"color: rgb(0, 0, 0); position:absolute;left:10px;top:150px\">Pos</div>";
print "<div style=\"color: rgb(0, 0, 0); position:absolute;left:60px;top:150px\">CSS-stil</div>";
print "<div style=\"color: rgb(0, 0, 0); position:absolute;left:620px;top:150px\">Tekst</div>";
print "<div style=\"color: rgb(0, 0, 0); position:absolute;left:10px;top:175px\"><input type=text size=1 name=pos[0]></div>";
print "<div style=\"color: rgb(0, 0, 0); position:absolute;left:60px;top:175px\"><input type=text size=65 name=style[0]></div>";
print "<div style=\"color: rgb(0, 0, 0); position:absolute;left:620px;top:175px\"><input type=text size=65 name=tekst[0]></div>";
for($x=1;$x<=$antal_tekster;$x++){
	print "Pos <input type=text size=2 name=pos[$x] value=$x>";
	print "CSS-stil <input type=text size=25 name=style[$x] value=$style[$x]>";
	print "Tekst <input type=text size=25 name=tekst[$x] value=$tekst[$x]><br>";
}
print "<br><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"><br>";
print "</form>";

function ny($ny) {
	
	print "<form name=diverse action=designer.php method=post>";

	if ($ny=='ramme') {
		print "<div style=\"color: rgb(0, 0, 0); position:absolute;left:10px;top:150px\">Pos</div>";
		print "<div style=\"color: rgb(0, 0, 0); position:absolute;left:60px;top:150px\">CSS Style</div>";
		print "<div style=\"color: rgb(0, 0, 0); position:absolute;left:620px;top:150px\">Tekst</div>";
		print "<div style=\"color: rgb(0, 0, 0); position:absolute;left:10px;top:175px\"><input type=text size=1 name=pos[0]></div>";
		print "<div style=\"color: rgb(0, 0, 0); position:absolute;left:60px;top:175px\"><input type=text size=65 name=style[0]></div>";
		print "<div style=\"color: rgb(0, 0, 0); position:absolute;left:620px;top:175px\"><input type=text size=65 name=tekst[0]></div>";
	}
	print "<br><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"><br>";
	print "</form>";
}
		

?>
