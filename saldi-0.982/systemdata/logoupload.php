<?
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

  @session_start();
  $s_id=session_id();
  include("../includes/connect.php");
  include("../includes/online.php");
  include("../includes/db_query.php");

  $id=$db_id;
 
 
  if ($_GET['id']) {$id = $_GET['id'];}
  if($_GET['returside'])
  {
    $returside= $_GET['returside'];
    $ordre_id = $_GET['ordre_id'];
    $fokus = $_GET['fokus'];
  }
  else {$returside="syssetup.php";}
  $navn=$_GET['navn'];


#list($major, $minor, $rev) = explode(".", phpversion());
#if($major < 4) {
 # die("Jeg kan kun arbejde med PHP 4.0.0 eller derover");
#}

#if($minor < 1)
#{
  $_FILES = $HTTP_POST_FILES;
  $_POST = $HTTP_POST_VARS;
  $_SERVER = $HTTP_SERVER_VARS;
#}

if($_FILES["upfil"]["size"] > 0) {

  $fra = $_FILES["upfil"]["tmp_name"];
#  if ($_POST['jpg']) {$til = "../formularer/$id/logo.jpg";}
  if ($_POST['eps']) {$til = "../logolib/logo_$id.eps";}
  /**
   *  Checker lige om filen er for stor til at vi vil acceptere den.
   *  Vi bruger ceil() i stedet for round(), saa vi ikke faar den skoere
   *  situation, at fejlmeldingen siger, at filen er for stor, men angiver
   *  samme stoerrelse for filen og den oevre graense.
   */
  $fil_stoerrelse = filesize($fra)/1024;
  if($fil_stoerrelse > 60) {
      die("Desv&aelig;rre - dit logo er for stort. Der acceptereres kun 60 kb, og logoet fylder " .
           ceil($fil_stoerrelse) . "kb");
  }
#  $indhold=addslashes(file_get_contents($fra));
  if(function_exists("move_uploaded_file")) {
    move_uploaded_file($fra, $til);
  } else {
    copy($fra, $til);
  }
#  db_modify("insert into billeder (indhold) values ('$indhold')");
}
upload($id, $font);

function upload($id, $font)
{
  global $bgcolor2;

  print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
  print "<tr><td align=\"center\" valign=\"top\">";
  print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
  print "<td width=\"25%\" bgcolor=\"$bgcolor2\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=formularkort.php? accesskey=T>Tilbage</a></small></td>";
  print "<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>Hent logo</small></td>";
  print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align = \"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><br></small></td>";
  print "</tbody></table>";
  print "</td></tr>";
  print "<td align = center valign = center>";
  print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" width=50%><tbody>";
  print "<form action=$_SERVER[PHP_SELF] method=\"post\" enctype=\"multipart/form-data\">";
  print "<table border=\"0\" cellpadding=\"5\" cellspacing=\"0\">";
  print "<tr><td align=\"right\">$font Hent logo-filen </td>";
  print "<td><input type=\"file\" name=\"upfil\"></td></tr>";
#  print "<tr><td></td><td><input type=\"submit\" value=\"upload (jpg)\" name=jpg>";
  print "<tr><td colspan=2 align=center><input type=\"submit\" value=\"Hent\" name=\"eps\"></td></tr>";
  print "<tr><td align=\"center\" colspan=2><small>$font Der accepteres kun .eps format</td></tr>";
  #print "<tr><td align=\"center\" colspan=2><small>$font Hint! Tryk [Ctrl]+[r] hvis logoet ikke ses p&aring;�formularen</td></tr>";
  print "</table></form>";
  print "<input type=hidden name=id value=$id>";
  print "</form></tr>";
}


?>
</td></tr>
<tr><td align = "center" valign = "bottom">
    <table width="100%" align="center" border="1" cellspacing="0" cellpadding="0"><tbody>
      <td width="100%" bgcolor="<? echo $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><br></small></td>
    </tbody></table>
</td></tr>
</tbody></table>
</body></html>
