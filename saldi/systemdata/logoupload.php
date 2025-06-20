<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------systemdata/logoupload.php------------patch 3.2.9-----2017-02-23-------------
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2017 saldi.dk aps
// ----------------------------------------------------------------------
//
// 2013.11.18 PK Har ændret upload af baggrund. Det er nu muligt at vælge forskellige baggrund til Tilbud, Ordrer og Faktura
// 2013.11.18 PK Har fjernet upload af jpg og eps logo og tilføjet pdf bilag til mail (Tilbud, Ordrer og Faktura)
// 2013.11.18 PK Man kan preview og slette den enkelte uploadede fil. Ved preview er der oprettet et nyt document 'view_logoupload.php'
// 2016.11.23 PK Har ændret upload størrelse fra 1mb til 10mb
// 2017.02.24 PHR	Tilføjet mulighed for upload af generel baggrund.
// 2019.02.25 MSC - Rettet topmenu design og isset fejl

@session_start();
$s_id=session_id();
$css="../css/standard.css";
$title="SALDI - logoindl&aelig;sning";

include("../includes/connect.php");
include("../includes/settings.php");
include("../includes/online.php");
include("../includes/db_query.php");

if (!isset ($_POST['bilagfil'])) $_POST['bilagfil'] = null;

global $db_id;
global $menu;

print "<div align=\"center\">";
if ($menu=='T') {
	#	print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">";
		include_once '../includes/top_header.php';
		include_once '../includes/top_menu.php';
		print "<div id=\"header\">\n";
		print "<div class=\"headerbtnLft\"></div>\n";
	#	print "<span class=\"headerTxt\">Systemsetup</span>\n";     
	#	print "<div class=\"headerbtnRght\"><!--<a href=\"index.php?page=../debitor/debitorkort.php;title=debitor\" class=\"button green small right\">Ny debitor</a>--></div>";       
		print "</div><!-- end of header -->";
		print "<div id=\"leftmenuholder\">";
		include_once 'left_menu.php';
		print "</div><!-- end of leftmenuholder -->\n";
		print "<div class=\"maincontentLargeHolder\">\n";
		print "<table border=\"1\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable2\"><tbody>";
	} else {
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=\"formularkort.php\" accesskey=\"L\">Luk</a></td>";
print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Indl&aelig;s Fil</td>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
print "</tbody></table>";
print "</td></tr>";
	}
if (!file_exists("../logolib/$db_id")) {
	echo "opretter ../logolib/$db_id<br>";
	mkdir("../logolib/$db_id",0744);
	exit;
} #else echo "../logolib/$db_id eksisterer<br>";

if (isset($_GET['slet_bilag'])) {
	$slet_bilag=$_GET['slet_bilag'].".pdf";
	unlink("../logolib/$db_id/$slet_bilag");
	upload();
	exit;
}
	
if(isset($_POST['bgfil'])||($_POST['bilagfil'])) {
	
	
	$fejl = $_FILES['uploadedfile']['error'];
	if ($fejl) {
		switch ($fejl) {
			case 2: print "<BODY onLoad=\"javascript:alert('Desv&aelig;rre - dit logo er alt for stort. Der acceptereres kun op til 100 kb')\">";
		}
		upload();
		exit;
	}
	if (!isset ($_POST['bilag_valg'])) $_POST['bilag_valg'] = null;
	if (!isset ($_POST['bg_valg'])) $_POST['bg_valg'] = null;
	$bilag_valg = $_POST['bilag_valg'];
	$bg_valg = $_POST['bg_valg'];
	$fil_stoerrelse = $_FILES['uploadedfile']['size'];
	$filetype = $_FILES['uploadedfile']['type'];
	$fileName= $_FILES['uploadedfile']['name'];
	$fra = $_FILES["uploadedfile"]["tmp_name"];
	$fil_stoerrelse = $_FILES["uploadedfile"]["size"];
	($bg_valg)?$valg=$bg_valg:$valg=$bilag_valg;
	//echo "valg: $valg"; exit;
//echo "filtype $filetype<br>";
//echo "filename $fileName<br>";
//echo "bg_valg: $bg_valg";
/*
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
	} else*/if ((strpos($filetype,'pdf'))||(strpos($fileName,'.PDF'))||(strpos($fileName,'pdf'))) {
		if($fil_stoerrelse > 10485760) {
			$tmp=ceil($fil_stoerrelse);
			system ("rm $filename");
			$tmp/=1024;
			print "<BODY onLoad=\"javascript:alert('Desv&aelig;rre - din PDF er for stor. Der acceptereres kun op til 10 MB, og den fylder $tmp MB')\">";
			upload();
			exit;
		}
		if (!file_exists("../logolib/$db_id")) system ("mkdir ../logolib/$db_id");
		$til = "../logolib/$db_id/$valg.pdf";
	} else {
		print "<BODY onLoad=\"javascript:alert('Filformatet skal være PDF')\">";
		//echo "Filformatet er ikke genkendt<br>";
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

 		#			print "<!-- kommentar for at skjule uddata til siden \n";
			$pdftk=system("which pdftk");
#			print "-->\n";
			if ($pdftk) {
				print "<BODY onLoad=\"javascript:alert('Siden er indl&aelig;st.')\">";
				upload();
				exit;
			} elseif (file_exists($pdf2ps)) {
				$pdffil=$til;
				$pdffil=str_replace("../logolib/$db_id/","",$pdffil);
				$psfil=str_replace(".pdf",".ps",$pdffil);
				system ("cd ../logolib/$db_id/\nrm $psfil\n$pdf2ps $pdffil");
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
	global $db_id;
	

	if(file_exists("../logolib/$db_id/bg.pdf")) {
		$bg="<a href=\"view_logoupload.php?vis=bg\">vis baggrund</a>";
		$slet_bg="<a href=\"logoupload.php?slet_bilag=bg\" onclick=\"return confirm('Vil du slette denne baggrund alle formularer?')\">slet</a>";
	} else {
		$bg="<i>Ingen baggrund</i>";
		$slet_bg=NULL;
	}
	if(file_exists("../logolib/$db_id/tilbud_bg.pdf")) {
		$tilbud_bg="<a href=\"view_logoupload.php?vis=tilbud_bg\">vis baggrund til tilbud</a>";
		$slet_tilbud_bg="<a href=\"logoupload.php?slet_bilag=tilbud_bg\" onclick=\"return confirm('Vil du slette denne baggrund til tilbud?')\">slet</a>";
	} else {
		$tilbud_bg="<i>Ingen baggrund</i>";
		$slet_tilbud_bg=NULL;
	}
	if(file_exists("../logolib/$db_id/ordrer_bg.pdf")) {
		$ordrer_bg="<a href=\"view_logoupload.php?vis=ordrer_bg\">vis baggrund til ordrer</a>";
		$slet_ordrer_bg="<a href=\"logoupload.php?slet_bilag=ordrer_bg\" onclick=\"return confirm('Vil du slette denne baggrund til ordrer?')\">slet</a>";
	} else {
		$ordrer_bg="<i>Ingen baggrund</i>";
		$slet_ordrer_bg=NULL;
	}
	if(file_exists("../logolib/$db_id/faktura_bg.pdf")) {
		$faktura_bg="<a href=\"view_logoupload.php?vis=faktura_bg\">vis baggrund til faktura</a>";
		$slet_faktura_bg="<a href=\"logoupload.php?slet_bilag=faktura_bg\" onclick=\"return confirm('Vil du slette denne baggrund til faktura?')\">slet</a>";
	} else {
		$faktura_bg="<i>Ingen baggrund</i>";
		$slet_faktura_bg=NULL;
	}
	if(file_exists("../logolib/$db_id/tilbud_bilag.pdf")) {
		$tilbud_bilag="<a href=\"view_logoupload.php?vis=tilbud_bilag\">vis bilag til tilbud</a>";
		$slet_tilbud_bilag="<a href=\"logoupload.php?slet_bilag=tilbud_bilag\" onclick=\"return confirm('Vil du slette dette bilag til tilbud?')\">slet</a>";
	} else {
		$tilbud_bilag="<i>Ingen bilag</i>";
		$slet_tilbud_bilag=NULL;
	}
	if(file_exists("../logolib/$db_id/ordrer_bilag.pdf")) {
		$ordrer_bilag="<a href=\"view_logoupload.php?vis=ordrer_bilag\">vis bilag til ordrer</a>";
		$slet_ordrer_bilag="<a href=\"logoupload.php?slet_bilag=ordrer_bilag\" onclick=\"return confirm('Vil du slette dette bilag til ordrer?')\">slet</a>";
	} else {
		$ordrer_bilag="<i>Ingen bilag</i>";
		$slet_ordrer_bilag=NULL;
	}
	if(file_exists("../logolib/$db_id/faktura_bilag.pdf")) {
		$faktura_bilag="<a href=\"view_logoupload.php?vis=faktura_bilag\">vis bilag til faktura</a>";
		$slet_faktura_bilag="<a href=\"logoupload.php?slet_bilag=faktura_bilag\" onclick=\"return confirm('Vil du slette dette bilag til faktura?')\">slet</a>";
	} else {
		$faktura_bilag="<i>Ingen bilag</i>";
		$slet_faktura_bilag=NULL;
	}
	print "<tr><td width=\"100%\" align=\"center\">";
	print "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">";
	print "<colgroup>
						<col width=\"15%\">
						<col width=\"20%\">
						<col width=\"30%\">
						<col width=\"5%\">
						<col width=\"12%\">
						<col width=\"3%\">
						<col width=\"15%\">
				</colgroup>";
	print "<tbody>";
	//print "<tr><td>&nbsp;</td><td colspan=\"5\" align=center>$font Du har mulighed for at oploade en logo i form af en jpg eller eps fil. </td><td>&nbsp;</td></tr>";
	//print "<tr><td>&nbsp;</td><td colspan=\"5\" align=center>$font Eller du kan lave en hel side i PDF format og bruge den som baggrund for tilbud, ordrer og fakturaer</td><td>&nbsp;</td></tr>";
	//print "<tr><td>&nbsp;</td><td colspan=\"5\"align=center>$font Brug f.eks <a href=\"http://da.libreoffice.org\" target=\"blank\">Libre Office</a> som kan gemme direkte til PDF</td><td>&nbsp;</td></tr>";
	//print "<tr><td>&nbsp;</td><td colspan=\"5\"align=center>$font Max str. er 100 kb for jpg og 500 kb for eps &amp; PDF<br><br><br><hr width=\"100%\"><br></td><td>&nbsp;</td></tr>";
	print "<tr><td colspan=\"2\">&nbsp;</td><td align=\"justify\">$font Du har mulighed for at oploade en hel side i PDF format som baggrund for alle formularer eller specifikt for tilbud, ordrer og fakturaer.<br><br>Det er ogs&aring; muligt at oploade et bilag i PDF format, som vedh&aelig;ftet fil i mail for tilbud, ordrer og fakturaer.<br><br>Brug f.eks <a href=\"http://da.libreoffice.org\" target=\"blank\">Libre Office</a> som kan gemme direkte til PDF.<br>St&oslash;rrelsen p&aring; PDF m&aring; max v&aelig;re 10mb.<br><br></td><td colspan=\"4\">&nbsp;</td></tr>";
	print "<tr><td>&nbsp;</td><td colspan=\"5\" align=\"center\">$font<hr width=\"100%\"><br></td><td>&nbsp;</td></tr>";
	print "</tbody>";
	
	print "<form enctype=\"multipart/form-data\" action=\"logoupload.php\" method=\"POST\">";
	print "<tbody>";
	print "<tr><td>&nbsp;";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"10000000\">";
	print "<input type=\"hidden\" name=\"filtype\" value='PDF'></td>";
	print "<td align=left>$font V&aelig;lg PDF fil til baggrund for:&nbsp;</td>";
	print "<td><select name=\"bg_valg\">
					<option value=\"bg\">Alle formularer</option>
					<option value=\"tilbud_bg\">Tilbud</option>
					<option value=\"ordrer_bg\">Ordrer</option>
					<option value=\"faktura_bg\">Fakturaer</option>
				</select>";
	print "<input name=\"uploadedfile\" type=\"file\" /><br /></td><td>$font Alle&nbsp;formularer:&nbsp;</td><td>$font $bg&nbsp;</td><td>$font $slet_bg&nbsp;</td><td>&nbsp;</td></td></tr>";
	print "<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>$font Tilbud:&nbsp;</td><td>$font $tilbud_bg&nbsp;</td><td>$font $slet_tilbud_bg&nbsp;</td><td>&nbsp;</td></tr>";
	print "<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>$font Ordrer:&nbsp;</td><td>$font $ordrer_bg&nbsp;</td><td>$font $slet_ordrer_bg&nbsp;</td><td>&nbsp;</td></tr>";
	print "<tr><td>&nbsp;</td><td>&nbsp;</td><td align=center><input class='button green medium' type=\"submit\" name=\"bgfil\" value=\"Indl&aelig;s\"></td><td>$font Fakturaer:&nbsp;</td><td>$font $faktura_bg&nbsp;</td><td>$font $slet_faktura_bg&nbsp;</td><td>&nbsp;</td></tr>";
	//print "<tr><td width=20%>&nbsp;</td><td>&nbsp;</td><td width=20%>&nbsp;</td></tr>";
	print "<tr><td>&nbsp;</td><td colspan=\"5\" align=\"center\"><br><hr width=\"100%\"><br></td><td>&nbsp;</td></tr>";
	print "</tbody>";
	print "</form>";
	
	print "<form enctype=\"multipart/form-data\" action=\"logoupload.php\" method=\"POST\">";
	print "<tbody>";
	print "<tr><td>&nbsp;";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"10000000\">";
	print "<input type=\"hidden\" name=\"filtype\" value='logo'></td>";
	print "<td align=left>$font V&aelig;lg PDF som bilag i mail til:&nbsp;</td>";
	print "<td><select name=\"bilag_valg\">
					<option value=\"tilbud_bilag\">Tilbud</option>
					<option value=\"ordrer_bilag\">Ordrer</option>
					<option value=\"faktura_bilag\">Fakturaer</option>
				</select>";
	print "<input name=\"uploadedfile\" type=\"file\" /><br /></td><td>$font Tilbud:&nbsp;</td><td>$font $tilbud_bilag&nbsp;</td><td>$font $slet_tilbud_bilag&nbsp;</td><td>&nbsp;</td></tr>";
	print "<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>$font Ordrer:&nbsp;</td><td>$font $ordrer_bilag&nbsp;</td><td>$font $slet_ordrer_bilag&nbsp;</td><td>&nbsp;</td></tr>";
	print "<tr><td>&nbsp;</td><td>&nbsp;</td><td align=\"center\"><input class='button green medium' type=\"submit\" name=\"bilagfil\" value=\"Indl&aelig;s\"></td><td width=5%>$font Fakturaer:&nbsp;</td><td>$font $faktura_bilag&nbsp;</td><td>$font $slet_faktura_bilag&nbsp;</td><td>&nbsp;</td></tr>";
	//print "<tr><td width=20%>&nbsp;</td><td>&nbsp;</td><td width=20%>&nbsp;</td></tr>";
	print "<tr><td>&nbsp;</td><td colspan=\"5\" align=\"center\"><br><hr width=\"100%\"><br></td><td>&nbsp;</td></tr>";
	print "</tbody>";
	print "</form>";
	
	print "</table>";
	print "</td></tr>";
}



print "</tbody></table>";
print "</td></tr>";

?>
