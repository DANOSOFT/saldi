<? #topkode_start
@session_start();
$s_id=session_id();

// ----------------------------------------------------------------------
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
// Copyright (c) 2004-2005 ITz ApS
// ----------------------------------------------------------------------

$ps_fil=$_GET['ps_fil'];
$valg=$_GET['valg'];
#$bg="nix";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");

if ($valg=="tilbage")
{
  print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
  exit;
}


if ($valg)
{
  $query = db_select("select box1, box2 from grupper where art='PV'");
  $row = db_fetch_array($query);
  if ($valg=="pdf")  {
    if ($row[box2]) {system ("$row[box2] ../temp/$ps_fil ../temp/$ps_fil.pdf");}
    else {system ("/usr/bin/ps2pdf ../temp/$ps_fil ../temp/$ps_fil.pdf");}
    print "<meta http-equiv=\"refresh\" content=\"0;URL=../temp/$ps_fil.pdf\">";
    print "<meta http-equiv=\"refresh\" content=\"10;URL=../includes/luk.php\">";
  }
  if ($valg=="printer") {
    system ("$row[box1] ../temp/$ps_fil");
    system ("rm ../../temp/$ps_fil");
    print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
    exit;
  }
  print "<meta http-equiv=\"refresh\" content=\"10;URL=../includes/luk.php\">";
  exit;
}


  print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
  print "<tr><td align=\"center\" valign=\"top\">";
  print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
  print "<td width=\"25%\" bgcolor=\"$bgcolor2\">$font<small><a href=udskriv.php?valg=tilbage accesskey=T>Tilbage</a></small></td>";
  print "<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\">$font<small>Udskriftsvalg</small></td>";
  print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align = \"right\"></td>";
  print "</tbody></table>";
  print "</td></tr>";
  print "<td align = center valign = top>";
  print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

  print "<tr><td align=center>$font <a href='udskriv.php?valg=pdf&ps_fil=$ps_fil'>PDF</a></td></tr>";
  print "<tr><td align=center>$font <a href='udskriv.php?valg=printer&ps_fil=$ps_fil'>Printer</a></td></tr>";
  print "</tbody></table></td>";
  print "</tbody></table>";


exit;


