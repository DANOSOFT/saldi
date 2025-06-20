<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><title>SALDI - backup</title><meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"></head>
<?
// --------------------------------------/index/backup.php---patch 0941----------------------------
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
// Copyright (c) 2004-2006 ITz ApS
// ----------------------------------------------------------------------

  @session_start();
  $s_id=session_id();
  include("../includes/connect.php");
  include("../includes/online.php");
  include("../includes/db_query.php");

  $returside="../index/menu.php";

  $dump_filnavn=trim($db."_".date("Y-m-d")).".sql";

  # print "pg_dump -h $sqhost -U $squser -W $sqpass -f '../temp/$dump_filnavn' $dump_filnavn =(".system("pg_dump -h $sqhost -U $squser -W $sqpass -f '../temp/$dump_filnavn' $db").")";
  # system("pg_dump -h $sqhost -U $squser -W $sqpass -f '../temp/$dump_filnavn' $db");
  print "<!-- Saldi-kommentar for at skjule uddata til siden \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
  system("pg_dump -h $sqhost -U $squser -f '../temp/$dump_filnavn' $db");
  print "--> \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres

  print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
  print "<tr><td align=\"center\" valign=\"top\">";
  print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
  print "<td width=\"25%\" bgcolor=\"$bgcolor2\">$font<small><a href=../index/menu.php accesskey=T>Tilbage</a></small></td>";
  print "<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\">$font<small>Backup</small></td>";
  print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align = \"right\"></td>";
  print "</tbody></table>";
  print "</td></tr>";
  print "<td align = center valign = center>";
  print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

  print "<tr><td align=center>$font H&oslash;jreklik her: <a href='../temp/$dump_filnavn'>$dump_filnavn</a></td></tr>";
  print "<tr><td align=center>$font V&aelig;lg \"gem link som\" (eller \"save link as\")</td></tr>";
  print "<tr><td align=center>$font og gem backup'en et passende sted</td></tr>";

  print "</tbody></table>";




?>
</tbody>
</table>
</td></tr>
<tr><td align = "center" valign = "bottom">
    <table width="100%" align="center" border="1" cellspacing="0" cellpadding="0"><tbody>
      <td width="100%" bgcolor="<? echo $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><br></small></td>
    </tbody></table>
</td></tr>
</tbody></table>
</body></html>
