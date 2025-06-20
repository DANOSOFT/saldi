<? #topkode_start
@session_start();
$s_id=session_id();

// -------------------------------------udskriftsvalg.php---------------------------------
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

$id=$_GET['id'];
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
  if ($valg==-1)  {$ps_fil="formularprint.php?id=$id&formular=2";}
  else {$ps_fil="formularprint.php?id=$id&formular=3";}

#  if ((!file_exists($ps_fil))&&($ps_fil!="udskriftsvalg.php"))  {
#    if (!file_exists("../formularer/$db_id")) {mkdir("../formularer/$db_id",0777);}
#    $kildefil=str_replace("/$db_id", "", $ps_fil);
#    copy($kildefil, $ps_fil);
#  }
  if ($valg!=-1) {$ps_fil="formularprint.php?id=$id&formular=3&lev_nr=$valg";}
  echo "<meta http-equiv=refresh content=0;url=$ps_fil>";
  exit;
#  print "<BODY onLoad=\"JavaScript:window.open('$ps_fil&id=$id' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
#  print "<body onload=\"javascript:window.close();\">";
}


  print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
  print "<tr><td align=\"center\" valign=\"top\">";
  print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
  print "<td width=\"25%\" bgcolor=\"$bgcolor2\">$font<small><a href=udskriftsvalg.php?valg=tilbage accesskey=T>Tilbage</a></small></td>";
  print "<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\">$font<small>Udskriftsvalg</small></td>";
  print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align = \"right\"></td>";
  print "</tbody></table>";
  print "</td></tr>";
  print "<td align = center valign = top>";
  print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

  $lev_nr=0; 
  $query = db_select("select lev_nr from batch_salg where ordre_id = $id");
  while ($row = db_fetch_array($query)) {
    if ($row[lev_nr]>$lev_nr) {$lev_nr=$row[lev_nr];}
  }
  print "<tr><td align=center>$font <a href='udskriftsvalg.php?id=$id&valg=-1'>Ordrebekr&aelig;ftelse</a></td></tr>";
  for ($x=1; $x<=$lev_nr; $x++) {
    print "<tr><td align=center>$font <a href='udskriftsvalg.php?id=$id&valg=$x'>F&oslash;lgeseddel $x</a></td></tr>";
  }
  print "</tbody></table></td>";
  print "</tbody></table>";


exit;


