<?php
// -------------------------------------------systemdata/logoupload.php------------patch 1.1.2------------------
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
// Copyright (c) 2004-2007 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$title="SALDI - logoupload";

include("../includes/connect.php");
include("../includes/settings.php");
include("../includes/online.php");
include("../includes/db_query.php");

print "<div align=\"center\">";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=\"formularkort.php\" accesskey=L>Luk</a></small></td>";
print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>Upload logo</small></td>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"></td>";
print "</tbody></table>";
print "</td></tr>";


if($_POST) {
	$fejl = $_FILES['uploadedfile']['error'];
	if ($fejl) {
		switch ($fejl) {
			case 2: print "<BODY onLoad=\"javascript:alert('Desv&aelig;rre - dit logo er alt for stort. Der acceptereres kun 60 kb')\">";
		}
		upload();
		exit;
	}
	$fil_stoerrelse = $_FILES['uploadedfile']['size'];
	$filetype = $_FILES['uploadedfile']['type'];
	$fileName= $_FILES['uploadedfile']['name'];
	$fra = $_FILES["uploadedfile"]["tmp_name"];
	$til = "../logolib/logo_$db_id.eps";
	$fil_stoerrelse = $_FILES["uploadedfile"]["size"];
	if ((strpos($filetype, 'eps'))||(strpos($fileName, '.eps'))) {
		if($fil_stoerrelse > 100000) {
			$tmp=ceil($fil_stoerrelse);
			print "<BODY onLoad=\"javascript:alert('Desv&aelig;rre - dit logo er for stort. Der acceptereres kun 100 kb, og logoet fylder $tmp kb')\">";
			upload();
			exit;
		} else $filetype="eps";
	} elseif ((strpos($filetype, 'jpeg'))||(strpos($fileName, '.jpg'))||(strpos($fileName, 'jpeg'))) {
		if($fil_stoerrelse > 100000) {
			$tmp=ceil($fil_stoerrelse);
			print "<BODY onLoad=\"javascript:alert('Desv&aelig;rre - dit logo er for stort. Der acceptereres kun 100 kb, og logoet fylder $tmp kb')\">";
			upload();
			exit;
		} else $filetype="jpg";
	} else {
		echo "Filformatet er ikke genkendt<br>";
		upload();
		exit;
	}
	if (move_uploaded_file($fra, $til)) {
		if ($filetype=="jpg") {
			$tmp=str_replace(".eps",".jpg",$til);
			rename ($til, $tmp);
			system ("$convert $tmp ps:$til");
			unlink ($tmp);
		}
		print "<BODY onLoad=\"javascript:alert('Logo er uploaded')\">";
	} else {
		print "<BODY onLoad=\"javascript:alert('Der er sket en fejl under hentningen, pr&oslash;v venligst igen')\">";
		echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
		upload();
	}
} else upload();
print "</tbody></table>";
################################################################################################################
function upload(){
	global $font;

	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"logoupload.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"10000000\">";
	print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
	print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
	print "<tr><td width=100% align=center>$font V&aelig;lg datafil: <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"Indl&aelig;s\"></td></tr>";
	print "<tr><td></form></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
}



print "</tbody></table>";
print "</td></tr>";

?>