<?
// -------------------------------------------------------------index/install.php-------0.951--------------
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
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
  <meta content="text/html; charset=ISO-8859-1" http-equiv="content-type">
  <title>SALDI - det frie danske finansprogram</title>
<?

# include("../includes/connect.php");
include("../includes/db_query.php");

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" bgcolor=#dddddd><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<td width=\"100%\" bgcolor=\"#bbbbbb\" align = \"center\"><font face=\"Arial,Helvetica\"><a href=komigang.html><small>Vejledning</small></td>\n";
print "</tbody></table></td></tr><tr><td align=\"center\" valign=\"center\">\n";

if ($HTTP_POST_VARS['opret'])
{
  $db_navn=trim($HTTP_POST_VARS['db_navn']);
  $db_bruger=trim($HTTP_POST_VARS['db_bruger']);
  $db_password=trim($HTTP_POST_VARS['db_password']);
  $adm_navn=trim($HTTP_POST_VARS['adm_navn']);
  $adm_password=md5(trim($HTTP_POST_VARS['adm_password']));

  $host="localhost";
  $tempdb="template1";
  $connection = db_connect ("host=$host dbname=$tempdb user=$db_bruger password=$db_password");
  if (!$connection)
  {
    die( "Kan ikke oprette forbindelse til PostgreSQL\n");
  }
  db_modify("CREATE DATABASE $db_navn with encoding = 'LATIN9'");
  db_close($connection);
  $connection = db_connect ("host=$host dbname=$db_navn user=$db_bruger");
  transaktion("begin");

  db_modify("CREATE TABLE brugere(id serial NOT NULL, brugernavn varchar, kode varchar, status varchar, regnskabsaar smallint, rettigheder varchar, PRIMARY KEY (id))");
  db_modify("INSERT INTO brugere (brugernavn, kode, rettigheder) values ('$adm_navn' ,'$adm_password', '11111111111111111111')");
  db_modify("CREATE TABLE regnskab (id serial NOT NULL,  regnskab varchar, dbhost varchar, dbuser varchar, dbpass varchar, db varchar, PRIMARY KEY (id))");
  db_modify("INSERT INTO regnskab (regnskab, dbhost, dbuser, db) values ('$db_navn' ,'$host', '$db_bruger', '$db_navn')");
  db_modify("CREATE TABLE online (session_id varchar,  brugernavn varchar, db varchar, dbuser varchar, rettigheder varchar, regnskabsaar smallint)");

  transaktion("commit");
#  rename("../includes/connect", "../includes/connect.php");
  if ( $fp=fopen("../includes/connect.php","w") ) {
    fwrite($fp," \n");


    fwrite($fp,"<?\n");
    fwrite($fp,"// ----------------------------------------------------------------------050506----------\n");
    fwrite($fp,"// LICENS\n");
    fwrite($fp,"//\n");
    fwrite($fp,"// Dette program er fri software. Du kan gendistribuere det og / eller\n");
    fwrite($fp,"// modificere det under betingelserne i GNU General Public License (GPL)\n");
    fwrite($fp,"// som er udgivet af The Free Software Foundation; enten i version 2\n");
    fwrite($fp,"// af denne licens eller en senere version efter eget valg\n");
    fwrite($fp,"//\n");
    fwrite($fp,"// Dette program er udgivet med haab om at det vil vaere til gavn,\n");
    fwrite($fp,"// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se\n");
    fwrite($fp,"// GNU General Public Licensen for flere detaljer.\n");
    fwrite($fp,"//\n");
    fwrite($fp,"// En dansk oversaettelse af licensen kan laeses her:\n");
    fwrite($fp,"// http://www.fundanemt.com/gpl_da.html\n");
    fwrite($fp,"//\n");
    fwrite($fp,"// Copyright (c) 2004-2005 ITz ApS\n");
    fwrite($fp,"// ----------------------------------------------------------------------\n");
    fwrite($fp,"\n");
    fwrite($fp,"if (file_exists(\"../includes/db_query.php\"))\n");
    fwrite($fp,"{\n");
    fwrite($fp,"  include(\"../includes/db_query.php\");\n");
    fwrite($fp,"  include(\"../includes/version.php\");\n");
    fwrite($fp,"}\n");
    fwrite($fp,"elseif (file_exists(\"../../includes/db_query.php\"))\n");
    fwrite($fp,"{\n");
    fwrite($fp,"  include(\"../../includes/db_query.php\");\n");
    fwrite($fp,"  include(\"../../includes/version.php\");\n");
    fwrite($fp,"}\n");
    fwrite($fp,"\n");
    fwrite($fp,"\$sqhost = \"$host\";\n");
    fwrite($fp,"\$squser	= \"$db_bruger\";\n");
    fwrite($fp,"\$sqpass = \"$db_password\";\n");
    fwrite($fp,"\$sqdb     = \"$db_navn\";\n");
    fwrite($fp,"\n");
    fwrite($fp,"#\$login = \"\";\n");
    fwrite($fp,"#\$login = \"dropdown\";\n");
    fwrite($fp,"\$login = \"cookie\";\n");
    fwrite($fp,"\n");
    fwrite($fp,"# \$brug_timestamp=\"y\";\n");
    fwrite($fp,"\n");
    fwrite($fp,"\$font = \"<font face='Arial, Helvetica, sans-serif'>\";\n");
    fwrite($fp,"\n");
    fwrite($fp,"\$connection = db_connect (\"host=\$sqhost dbname=\$sqdb user=\$squser\");\n");
    fwrite($fp,"if (!\$connection)\n");
    fwrite($fp,"{\n");
    fwrite($fp,"  die( \"Unable to connect\");\n");
    fwrite($fp,"}\n");
    fwrite($fp,"\n");
    fwrite($fp,"\$textcolor=\"#000000\";\n");
    fwrite($fp,"\$textcolor2=\"#009900\";\n");
    fwrite($fp,"\$bgcolor=\"#dddddd\"; #alm baggrund\n");
    fwrite($fp,"\$bgcolor2=\"#bbbbbb\"; #top & bundlinjer\n");
    fwrite($fp,"\$bgcolor3=\"#cccccc\";\n");
    fwrite($fp,"\$bgcolor4=\"#aaaaaa\";\n");
    fwrite($fp,"\$bgcolor5=\"#d2d2d2\";\n");
    fwrite($fp,"\n");
    fwrite($fp,"if (\$bg!='nix') {PRINT \"<body bgcolor=\$bgcolor link='#000000' vlink='#000000' alink='#000000' center=''>\";}\n");
    fwrite($fp,"\n");
    fwrite($fp,"?>\n");
  
    fclose($fp);
  
    print "<a href=../index/index.php><small>Forts&aelig;t</small>";
  } else {
    print "<p>Der er ikke skriveadgang til kataloget \"includes\", hvor \"connect.php\" skal oprettes.</p>\n\n";
    print "<p>Sørg for at der er skriveadgang for den bruger, som den besøgende kører som. til katalogerne \n";
    print "\"includes\", \"temp\" og \"logolib\". Se hvordan i installeringsvejledningen INSTALLATION.txt.</p>\n\n";
    print "</td></tr></table></body></html>";
    exit;
  }	  
} else {
?>
<table width=40% align=center border="0" cellspacing="0" cellpadding="0"><tbody>
  <tr><td colspan="5" align="center"><font face="Helvetica, Arial, sans-serif"><big><b>Velkommen til SALDI</b></big></td></tr>
  <tr><td colspan="5" align="center"> <font face="Helvetica, Arial, sans-serif">Hvis du har installeret Apache, PHP og PostgreSQL kan du nu installere SALDI</td></tr>
<FORM name="opret" METHOD=POST ACTION="install.php">
<tr><td colspan=2><br></td></tr>

<tr><td><font face="Arial,Helvetica"><font>Database navn</td><td><INPUT TYPE=TEXT NAME=db_navn VALUE = "saldi"> <td><td width=5%></td></tr>
<tr><td><br></td></tr>
<tr><td><font face="Arial,Helvetica"><font>Postgres brugernavn</td> <td ><INPUT TYPE=TEXT NAME=db_bruger VALUE = "postgres"></td><td></td></tr>
<tr><td><br></td></tr>
<tr><td><font face="Arial,Helvetica"><font>db_password</td><td><INPUT TYPE=password NAME=db_password></td><td></td></tr>
<tr><td><br></td></tr>
<tr><td><font face="Arial,Helvetica"><font>Administrator navn</td><td><INPUT TYPE=TEXT NAME=adm_navn  VALUE = "admin"></td><td></td></tr>
<tr><td><br></td></tr>
<tr><td><font face="Arial,Helvetica"><font>Administrator password</td><td><INPUT TYPE=password NAME=adm_password></td><td></td></tr>
<tr><td><br></td></tr>
<tr><td colspan=2 align=center><INPUT TYPE=submit name=opret VALUE=installer></td></tr>

<tr><td><br></td></tr>
<tr>
</tr>
</FORM>
    </tbody></table>
  </td></tr>
  <tr><td align="center" valign="bottom">
    <table width="100%" align="center" border="0" cellspacing="0" cellpadding="0"><tbody>
      <td width="25%" bgcolor=#bbbbbb><font face="Helvetica, Arial, sans-serif"><small>Copyright (C) 2004-2005 ITz ApS</small></td>
      <td width="50%" bgcolor=#bbbbbb align = "center"><font face="Helvetica, Arial, sans-serif"></td>
      <td width="25%" bgcolor=#bbbbbb align = "right"><font face="Helvetica, Arial, sans-serif"></td>
    </tbody></table>
  </td></tr>
</tbody></table>
<?}?>
</head>
</body></html>
