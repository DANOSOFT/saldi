<?php
// --------systemdata/logoupload.php------------patch 3.2.9-----2012-04-16-------------
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
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$css="../css/standard.css";
$title="SALDI - logoindl&aelig;sning";

include("../includes/connect.php");
include("../includes/settings.php");
include("../includes/online.php");
include("../includes/db_query.php");

print "<div align=\"center\">";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><a href=\"formularkort.php\" accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund>Indl&aelig;s logo</td>";
print "<td width=\"10%\" $top_bund><br></td>";
print "</tbody></table>";
print "</td></tr>";


if($_POST) {
	$fejl = $_FILES['uploadedfile']['error'];
	if ($fejl) {
		switch ($fejl) {
			case 2: print "<BODY onLoad=\"javascript:alert('Desv&aelig;rre - dit logo er alt for stort. Der acceptereres kun op til 100 kb')\">";
		}
		upload();
		exit;
	}
	$fil_stoerrelse = $_FILES['uploadedfile']['size'];
	$filetype = $_FILES['uploadedfile']['type'];
	$fileName= $_FILES['uploadedfile']['name'];
	$fra = $_FILES["uploadedfile"]["tmp_name"];
	$fil_stoerrelse = $_FILES["uploadedfile"]["size"];
#echo "filtype $filetype<br>";
	if ((strpos($filetype, 'eps'))||(strpos($fileName, '.eps'))) {
		$til = "../logolib/logo_$db_id.eps";
		if($fil_stoerrelse > 500000) {
			$tmp=ceil($fil_stoerrelse);
			print "<BODY onLoad=\"javascript:alert('Desv&aelig;rre - dit logo er for stort. Der acceptereres kun op til 500 kb, og logoet fylder $tmp kb')\">";
			upload();
			exit;
		} else $filetype="eps";
	} elseif ((strpos($filetype, 'jpeg'))||(strpos($fileName, '.jpg'))||(strpos($fileName, 'jpeg'))) {
		$til = "../logolib/logo_$db_id.jpg";
		if($fil_stoerrelse > 100000) {
			$tmp=ceil($fil_stoerrelse);
			print "<BODY onLoad=\"javascript:alert('Desv&aelig;rre - dit logo er for stort. Der acceptereres kun op til 100 kb, og logoet fylder $tmp kb')\">";
			upload();
			exit;
		} else $filetype="jpg";
	} elseif ((strpos($filetype,'pdf'))||(strpos($fileName,'.PDF'))||(strpos($fileName,'pdf'))) {
		if($fil_stoerrelse > 1024000) {
			$tmp=ceil($fil_stoerrelse);
			system ("rm $filename");
			$tmp/=1024;
			print "<BODY onLoad=\"javascript:alert('Desv&aelig;rre - din PDF er for stor. Der acceptereres kun op til 1 MB, og den fylder $tmp MB')\">";
			upload();
			exit;
		}
		if (!file_exists("../logolib/$db_id")) system ("mkdir ../logolib/$db_id");
		$til = "../logolib/$db_id/bg.pdf";
	} else {
		echo "Filformatet er ikke genkendt<br>";
		upload();
		exit;
	}
	if (move_uploaded_file($fra, $til)) {
		if ($filetype=="jpg") {
			$tmp=str_replace(".jpg","",$til);
			$fra=$tmp.".jpg";
			$til=$tmp.".eps";
			if (file_exists($convert)) {
				system ("$convert $fra $til");
				print "<BODY onLoad=\"javascript:alert('Logoet er indl&aelig;st.')\">";
			} else print "<BODY onLoad=\"javascript:alert('ImageMagic er ikke installeret - logo kan ikke indl&aelig;ses')\">";
			unlink ($fra);
 		} else {
			print "<!-- kommentar for at skjule uddata til siden \n";
			$pdftk=system("which pdftk");
			print "-->\n";
			if ($pdftk) {
				print "<BODY onLoad=\"javascript:alert('Siden er indl&aelig;st.')\">";
			} elseif (file_exists($pdf2ps)) {
				$pdffil=$til;
				$pdffil=str_replace("../logolib/$db_id/","",$pdffil);
				$psfil=str_replace(".pdf",".ps",$pdffil);
#				system ("cd ../logolib/$db_id/\nrm $psfil\n$pdf2ps $pdffil");
				print "<BODY onLoad=\"javascript:alert('Siden er indl&aelig;st.')\">";
			}
			else print "<BODY onLoad=\"javascript:alert('Hverken PDFTK (anbefales) eller PDF2PS er ikke installeret - logo kan ikke indl&aelig;ses')\">";
		}
	} else {
		print "<BODY onLoad=\"javascript:alert('Der er sket en fejl under indl&aelig;sningen. Pr&oslash;v venligst igen')\">";
		echo "Der er sket en fejl under indl&aelig;sningen. Pr&oslash;v venligst igen";
		upload();
	}
} else upload();
print "</tbody></table>";
################################################################################################################
function upload(){
	global $font;

	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td align=center>Du har mulighed for at oploade en logo i form af en jgp eller eps fil. </td></tr>";
	print "<tr><td align=center>Eller du kan lave en hel side i PDF format og bruge den som baggrund for tilbud, ordrer og fakturaer</td></tr>";
	print "<tr><td align=center>Brug f.eks <a href=\"http://da.libreoffice.org\" target=\"blank\">Libre Office</a> som kan gemme i direkte PDF</td></tr>";
	print "<tr><td align=center>Max str. er 100 kb for jpg og 500 kb for eps & PDF<br><br><br><hr width=\"50%\"><br></td></tr>";
	print "<form enctype=\"multipart/form-data\" action=\"logoupload.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"10000000\">";
	print "<input type=\"hidden\" name=\"filtype\" value='logo'>";
	print "<tr><td width=100% align=center>$font V&aelig;lg jpg / eps fil til logo: <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"Indl&aelig;s\"></td></tr>";
	print "<tr><td></form></td></tr>";
	print "<tr><td align=\"center\"><br><hr width=\"50%\"><br></td></tr>";
	print "<form enctype=\"multipart/form-data\" action=\"logoupload.php\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"10000000\">";
	print "<input type=\"hidden\" name=\"filtype\" value='PDF'>";
	print "<tr><td width=100% align=center>$font V&aelig;lg PDF fil til baggrund: <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"Indl&aelig;s\"></td></tr>";
	print "<tr><td></form></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
}



print "</tbody></table>";
print "</td></tr>";

?>
