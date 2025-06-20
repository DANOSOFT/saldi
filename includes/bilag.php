<?php
// ----------finans/importer.php------------patch 3.2.5-----2011.12.28-----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2011 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Kassebillag";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print "<div align=\"center\">";

if(($_GET)||($_POST)) {

	if ($_GET) {
		$kilde_id=if_isset($_GET['kilde_id']);
		$kilde=if_isset($_GET['kilde']);
		$bilag_id=if_isset($_GET['bilag_id']);
		$bilag=if_isset($_GET['bilag']);
		$fokus=if_isset($_GET['fokus']);
		$ny=if_isset($_GET['ny']);
		$vis=if_isset($_GET['vis']);
		$filnavn=if_isset($_GET['filnavn']);
	} else {
		$submit=$_POST['submit'];
		$kilde_id=$_POST['kilde_id'];
		$kilde=$_POST['kilde'];
		$bilag_id=$_POST['bilag_id'];
		$bilag=$_POST['bilag'];
		$fokus=$_POST['fokus'];
	}
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	if ($kilde=="kassekladde") $tmp="../finans/kassekladde.php?kladde_id=$kilde_id&fokus=$fokus";
	else $tmp="../debitor/historikkort.php?id=$kilde_id";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=$tmp accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$title $bilag</td>";
	print "<td width=\"10%\" $top_bund ><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
	print "</tbody></table>";
	print "</td></tr>";

	if ($filnavn) {
		vis_bilag($kilde_id,$kilde,$bilag_id,$fokus,$filnavn);
	} elseif ($filnavn=basename($_FILES['uploadedfile']['name'])) {
		$filnavn=htmlentities($filnavn,ENT_COMPAT,$charset);
		$tmp="../temp/".$db."/".$filnavn;
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'],"$tmp")) {
			upload_til_ftp($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn);
		}	else {
			echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
			upload($kilde_id,$kilde,$bilag_id,$bilag,$fokus);
		}
	} else upload($kilde_id,$kilde,$bilag_id,$bilag,$fokus);
}
print "</tbody></table>";
################################################################################################################
function upload($kilde_id,$kilde,$bilag_id,$bilag,$fokus){
global $charset;

print "<tr><td width=100% align=center><table width=\"500px\" height=\"200px\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\"><tbody>";
print "<tr><td width=100% align=center>Vedh&aelig;ft bilag</td></tr>";
print "<tr><td width=100% align=center><br></td></tr>";
print "<form enctype=\"multipart/form-data\" action=\"bilag.php\" method=\"POST\">";
print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000000\">";
print "<input type=\"hidden\" name=\"kilde_id\" value=$kilde_id>";
print "<input type=\"hidden\" name=\"kilde\" value=$kilde>";
print "<input type=\"hidden\" name=\"bilag_id\" value=$bilag_id>";
print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
print "<input type=\"hidden\" name=\"fokus\" value=$fokus>";
print "<tr><td width=100% align=center> V&aelig;lg bilag: <input class=\"inputbox\" name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td align=center><input type=\"submit\" value=\"Hent\" /></td></tr>";
print "<tr><td></form></td></tr>";
}

function upload_til_ftp($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn){
	global $charset;
	global $db;
	global $bruger_id;
	global $exec_path;

	if (!isset($exec_path)) $exec_path="/usr/bin";
	
	if (file_exists("../temp/$db/$filnavn")) {
		
		$ftpfilnavn="bilag_".$bilag_id;
		
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
			fwrite ($fp, "mkdir $mappe\ncd $mappe\nmkdir $undermappe\ncd $undermappe\nput $ftpfilnavn\nbye\n");
		}
		fclose($fp);
		$fp=fopen("../temp/$db/ftplog","w");
		fwrite ($fp, "cd ../temp/$db\n\rmv \"$filnavn\" \"$ftpfilnavn\"\n\r$exec_path/ncftp ftp://$box2:$box3@$box1\n\rrm $ftpfilnavn\n\r");
		fclose($fp);
		$kommando="cd ../temp/$db\nmv \"$filnavn\" \"$ftpfilnavn\"\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$box1." < ftpscript.$bruger_id >> ftplog\nrm $ftpfilnavn\n";#rm ftpscript.$bruger_id";
		system ($kommando);
		$fp=fopen("../temp/$db/ftpscript.$bruger_id","w");
		if ($fp) {
			fwrite ($fp, "cd $mappe\ncd $undermappe\nget $ftpfilnavn\nbye\n");
		}
		fclose($fp);
		$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$box1." < ftpscript.$bruger_id >> ftplog\n";
		system ($kommando);
		$langt_filnavn="../temp/$db/".$ftpfilnavn;
		if (file_exists($langt_filnavn)) {
			$filnavn=addslashes($filnavn);
			db_modify("update $kilde set dokument='$filnavn' where id='$bilag_id'",__FILE__ . " linje " . __LINE__);
			print "<BODY onLoad=\"javascript:alert('$filnavn er indl&aelig;st')\">";
		} else {
			print "<BODY onLoad=\"javascript:alert('indl&aelig;sning af $filnavn fejlet')\">";
		}
	} print "<BODY onLoad=\"javascript:alert('indl&aelig;sning af $filnavn fejlet')\">";
	if ($kilde=="kassekladde") $tmp="../finans/kassekladde.php?kladde_id=$kilde_id&fokus=$fokus";
	else $tmp="../debitor/historikkort.php?id=$kilde_id";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">";
}

function vis_bilag($kilde_id,$kilde,$bilag_id,$fokus,$filnavn){

	global $charset;
	global $db;
	global $bruger_id;
	global $exec_path;
	
	if (!isset($exec_path)) $exec_path="/usr/bin";

	$ftpfilnavn="bilag_".$bilag_id;
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
		fwrite ($fp, "cd $mappe\ncd $undermappe\nget $ftpfilnavn\nbye\n");
	}
	fclose($fp);
	$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$box1." < ftpscript.$bruger_id > ftplog\nmv \"$ftpfilnavn\" \"$filnavn\"\n";
	system ($kommando);
	print "<tr><td width=100% height=100% align=\"center\" valign=\"middle\"><iframe frameborder=\"no\" width=\"100%\" height=\"100%\" scrolling=\"auto\" src=\"vis_bilag.php?filnavn=$filnavn&db=$db&bilag_id=$bilag_id&kilde_id=$kilde_id&kilde=$kilde\"></iframe></td></tr>";
#	print "<a href=\"../temp/$db/$filnavn\">$filnavn</a>";
}
