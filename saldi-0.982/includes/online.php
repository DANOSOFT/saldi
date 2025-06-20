  <?

// ----------------------------------------------------------------------050423----------
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

#if (file_exists("../includes/db_query.php")){include("../includes/db_query.php");}
#elseif (file_exists("../../includes/db_query.php")){include("../../includes/db_query.php");}

$query = db_select("select * from online where session_id = '$s_id'");
if ($row = db_fetch_array($query)) 
{
#  $dbhost = $sqhost;
  $dbuser = trim($row['dbuser']);
  $db  = trim($row['db']);
  $regnaar = trim($row['regnskabsaar']);
  $brugernavn = trim($row['brugernavn']);
  $rettigheder = trim($row['rettigheder']);
}
else
{
#  print "Du skal logge ind f&oslash;rst<br>";
  print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/index.php\">";
  exit;
}
$query = db_select("select id, regnskab from regnskab where db = '$db'");
if ($row = db_fetch_array($query))
{
  $db_id = $row[id];
  $regnskab = $row['regnskab'];
}

db_connect ("host=$sqhost dbname=$db user=$dbuser");
if (!$rettigheder)
{
  $query = db_select("select * from brugere where brugernavn  = '$brugernavn'");
  if ($row = db_fetch_array($query)) {$rettigheder = trim($row['rettigheder']);}
}
elseif ((($rettigheder)&&($modulnr))&&(substr($rettigheder,$modulnr,1)!='1'))
{ 
  print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/index.php\">";
  exit;
}


?>
   
