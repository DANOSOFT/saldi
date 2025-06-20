<?php
// ----------finans/importer.php------------patch 3.4.3-----2014.07.01-----------
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
// Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20140701 Mange ændring i forbindelse med indførelse af owncloud bilagsopbevaring

@session_start();
$s_id=session_id();
$css="../css/standard.css";

	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");

print "<center><middle>";

$filnavn=$_GET['filnavn'];
$bilag_id=$_GET['bilag_id'];
$kilde_id=$_GET['kilde_id'];
$kilde=$_GET['kilde'];
$db=$_GET['db'];
if (isset($_GET['slet']) && $_GET['slet']=='ok') {
	slet_bilag($bilag_id,$filnavn,$kilde_id,$kilde);
	print "<BODY onLoad=\"javascript:alert('bilaget er slettet')\">";
	exit;
}
print "<br><br><br><br><br><table  width=\"500px\" height=\"200px\"style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\">";
print "<tbody><td width=100% align=center>";
print "Klik p&aring; filnavnet herunder for at &aring;bne, h&oslash;jreklik for at gemme<br><br>";
print "<a href=\"../temp/$db/$filnavn\">$filnavn</a><br><br><hr>";
print "<br><br>";
print "<a onclick=\"return confirm('Skal bilaget slettes?');\" href=\"vis_bilag.php?slet=ok&kilde=$kilde&kilde_id=$kilde_id&bilag_id=$bilag_id&db=$db&filnavn=$filnavn\">Klik her for at slette bilaget</a>";
print "</td></tbody></table>";


function slet_bilag($bilag_id,$filnavn,$kilde_id,$kilde){

	@session_start();
	$s_id=session_id();
	$css="../css/standard.css";

# Flyttet	
#	include("../includes/connect.php");
#	include("../includes/online.php");
#	include("../includes/std_func.php");

	$r=db_fetch_array(db_select("select * from grupper where art='FTP'",__FILE__ . " linje " . __LINE__));
	$box1=$r['box1'];
	$box2=$r['box2'];
	$box3=$r['box3'];
		if ($kilde=="kassekladde") {
			$mappe=$r['box4'];
			$undermappe="kladde_$kilde_id";
			$ftpfilnavn="bilag_".$bilag_id;
		} else {
			$mappe=$r['box5'];
			$undermappe="debitor_$kilde_id";
			$ftpfilnavn="doc_".$bilag_id;
		}
	
	$fp=fopen("../temp/$db/ftpscript.$bruger_id","w");
	if ($fp) {
		fwrite ($fp, "cd $mappe\ncd $undermappe\nrm $ftpfilnavn\nbye\n");
	}
	fclose($fp);
	if (!isset($exec_path)) $exec_path="/usr/bin";
	$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$box1." < ftpscript.$bruger_id > ftplog\n";
	system ($kommando);
	db_modify("update $kilde set dokument='' where id='$bilag_id'",__FILE__ . " linje " . __LINE__);

}
?>
