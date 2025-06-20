<?php #topkode_start
@session_start();
$s_id=session_id();

// -------includes/udskriv.php----lap 2.0.9----2010.05.95-------------------
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
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------

$css="../css/standard.css";		
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if (!isset($exec_path)) $exec_path="/usr/bin";

$ps_fil=if_isset($_GET['ps_fil']);
$valg=if_isset($_GET['valg']);
$id=if_isset($_GET['id']);

if ($valg=="tilbage") {
  if ($popup || !$id) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
  	exit;
	} else {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/ordre.php?id=$id\">";
  	exit;
	}
}

if (!$valg) {
	$row = db_fetch_array($query = db_select("select box1 from grupper where art='PV'",__FILE__ . " linje " . __LINE__));
	if (!$row[box1]) $valg="pdf";
}

if ($valg) {
  $query = db_select("select box1, box2 from grupper where art='PV'",__FILE__ . " linje " . __LINE__);
  $row = db_fetch_array($query);
  if ($valg=="pdf")  {
    if ($row[box2]) system ("$row[box2] ../temp/$ps_fil ../temp/$ps_fil.pdf");
 		else system ("$exec_path/ps2pdf ../temp/$ps_fil ../temp/$ps_fil.pdf");
		system ("$exec_path/gs -q -dNOPAUSE -dBATCH -sDEVICE=tiffg4 -r200 -sPAPERSIZE=a4 -sOutputFile=../temp/$ps_fil.tiff ../temp/$ps_fil");
		if (file_exists("../temp/$ps_fil.pdf")) {
			print "<table width=100% height=100%><tbody>";
  		print "<td width=\"10%\" height=\"1%\" $top_bund><a href=udskriv.php?valg=tilbage&id=$id accesskey=L>Luk</a></td>";
# 			else print "<td width=\"10%\" height=\"1%\" $top_bund><a href='#' accesskey=L onClick=\"history.go(-2)\">Luk</a></td>";
			print "<td width=\"80%\" $top_bund align=\"center\" title=\"Klik her for at &aring;bne filen i nyt vindue, h&oslash;jreklik her for at gemme.\"><a href=../temp/$ps_fil.pdf target=blank>Vis udskrift</a></td>";
  		print "<td width=\"10%\" $top_bund align = \"right\"title=\"Klik her for at &aring;bne filen i tiff format\"><a href=\"../temp/$ps_fil.tiff\">TIFF-version</a></td>";
			print "<tr><td width=100% height=99% align=\"center\" valign=\"middle\" colspan=\"3\"><iframe frameborder=\"no\" width=\"100%\" height=\"100%\" scrolling=\"auto\" src=\"../temp/$ps_fil.pdf\"></iframe></td></tr>";
#			print "<tr><td width=100% colspan=\"3\"><a href=../temp/$ps_fil.pdf target=blank>H&oslash;jreklik her for at gemme filen</a></td></tr>";
			print "</tbody></table>";
			print exit;
#			print "<meta http-equiv=\"refresh\" content=\"0;URL=../temp/$ps_fil.pdf\">";
		} else print "<BODY onLoad=\"javascript:alert('PDF-fil ikke fundet - er PS2PDF installeret?')\">";
#		print "<meta http-equiv=\"refresh\" content=\"10;URL=../includes/luk.php\">";
	}
  if ($valg=="printer") {
    system ("$row[box1] ../temp/$ps_fil");
    system ("rm ../../temp/$ps_fil");
    print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
    exit;
  }
#  print "<meta http-equiv=\"refresh\" content=\"10;URL=../includes/luk.php\">";
  exit;
}


  print "<table width=\"100%\" height=\"75%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
  print "<tr><td height=\"1%\" align=\"center\" valign=\"top\">";
  print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"1\" cellpadding=\"0\"><tbody>";
  print "<td width=\"10%\" $top_bund><a href=udskriv.php?valg=tilbage accesskey=L>Luk</a></td>";
  print "<td width=\"80%\" $top_bund align=\"center\">Udskriftsvalg</td>";
  print "<td width=\"10%\" $top_bund align = \"right\"><br></td>";
  print "</tbody></table>";
  print "</td></tr>";
  print "<tr><td height=\"99%\" align = center valign = middle>";
  print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

  print "<tr><td align=center> <a href='udskriv.php?valg=pdf&ps_fil=$ps_fil'>PDF</a></td></tr>";
  print "<tr><td align=center> <a href='udskriv.php?valg=printer&ps_fil=$ps_fil'>Printer</a></td></tr>";
  print "</tbody></table></td>";
  print "</tbody></table>";


exit;


