<?
ob_start(); //Starter output buffering
// --------------------------------------------------------index/login.php----------patch0.935----------
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
include("../includes/db_query.php");

if (($HTTP_POST_VARS)||($_GET['login']=='test'))
{
  if ($HTTP_POST_VARS)
  {
    $regnskab = trim($HTTP_POST_VARS['regnskab']);
    $brugernavn = trim($HTTP_POST_VARS['login']);
    $password = trim($HTTP_POST_VARS['password']); // password i formatet uppercase( md5( timestamp + uppercase( md5(original_password) ) ) )
    $timestamp = trim($HTTP_POST_VARS['timestamp']);
    $fortsaet = $HTTP_POST_VARS['fortsaet'];
    $afbryd = $HTTP_POST_VARS['afbryd'];
  }
  else
  {
     $regnskab = "test";
     $brugernavn = "test";
     $password = "test";
  }
  $query = db_select("select * from regnskab where regnskab = '$regnskab'");
  if ($row = db_fetch_array($query))  {
    $dbuser =  trim($row['dbuser']);
    $dbpass =  trim($row['dbpass']);
    $db     = trim($row['db']);
    $db_id  =  trim($row['id']);
    if (!$db)  {
      $db=$sqdb;
      db_modify("update regnskab set db='$sqdb' where dbuser='$dbuser'");
    }
    if ($fortsaet) {
       db_modify("delete from online where db='$db' and brugernavn='$brugernavn'");
    }
    if ($afbryd) {
    print "<meta http-equiv=\"refresh\" content=\"0;URL=index.php\">";
    }
  }
  else  {
    print "$font <b>Regnskab $regnskab findes ikke<br>";
    print "<meta http-equiv=\"refresh\" content=\"3;URL=index.php\">";
    exit;
  }
}
 if (!(($regnskab==test)&&($brugernavn==test)&&($password==test))){
  $query = db_select("select * from online where brugernavn = '$brugernavn' and db = '$db' and session_id != '$s_id'");
  if ($row = db_fetch_array($query)){
    online($regnskab, $brugernavn, $password, $timestamp, $s_id);
    exit;
  }
}
if (($regnskabsaar)&&($db)){
  db_modify("insert into online (session_id, brugernavn, db, dbuser, regnskabsaar) values ('$s_id', '$brugernavn', '$db', '$dbuser', '$regnskabsaar')");
}
elseif($db) {db_modify("insert into online (session_id, brugernavn, db, dbuser) values ('$s_id', '$brugernavn', '$db', '$dbuser')");}
else {db_modify("delete from online where db=''");}
## Versions kontrol / opdatering af database.

if (($regnskab)&&($regnskab!=$sqdb)) {
  include("../includes/online.php");

  $query = db_select("select box1 from grupper where art = 'VE'");
  if ($row = db_fetch_array($query)) {$dbver=$row[box1]*1;}
  else {
     $dbver=0;
     db_modify("insert into grupper (beskrivelse, art, box1) values ('Version', 'VE', '0')");
  }
  if ($dbver<$version)  {
    include ("../includes/opdater.php");
    opdater($version, $dbver);
    exit; 
    print "<meta http-equiv=\"refresh\" content=\"1;URL=index.php\">";
  }
}

if ($brug_timestamp) {$query = db_select("select * from brugere where brugernavn='$brugernavn' and (upper(md5('$timestamp' || upper(kode)))=upper('$password'))");}
else {
#echo $password;
  $password=md5($password);
#echo $password;
#exit;
  $query = db_select("select * from brugere where brugernavn='$brugernavn' and kode= '$password'");
}
if ($row = db_fetch_array($query)) {
  db_connect ("host=$sqhost dbname=$sqdb user=$squser");
  $rettigheder=trim($row['rettigheder']);
  $regnskabsaar=$row['regnskabsaar'];
  if (($regnskabsaar)&&($db)) {db_modify("update online set rettigheder='$rettigheder', regnskabsaar='$regnskabsaar' where session_id = '$s_id'");}
  else {db_modify("update online set rettigheder='$rettigheder' where session_id = '$s_id'");}
  db_connect ("host='$sqhost' dbname='$db' user='$dbuser'");
  if ($login=="cookie") {setcookie("saldi_std",$regnskab,time()+60*60*24*30);}
 }
else {$afbryd=1;}
ob_end_flush();  //Sender det "bufferede" output afsted...

#  echo "afbryd $afbryd<br>";
# exit;
  if(!$afbryd)
  {

    $fp=fopen("../temp/online.log","a");
    fwrite($fp,date("Y-m-d")." ".date("H:i:s")." ".getenv("remote_addr")." ".$s_id." ".$brugernavn."\n");
    fclose($fp);
    if ($regnskab==$sqdb) {
      print "<meta http-equiv=\"refresh\" content=\"0;URL=admin_menu.php\">";
      exit;
    }
#    elseif ($regnskabsaar)
#    {
#      print "<meta http-equiv=\"refresh\" content=\"0;URL=menu.php\">";
#    }
    else {
      print "<meta http-equiv=\"refresh\" content=\"0;URL=menu.php\">";
#      print "<meta http-equiv=\"refresh\" content=\"2;URL=../systemdata/regnskabskort.php\">";
    }
       ## Versions kontrol / opdatering af database.

    #else {print "<meta http-equiv=\"refresh\" content=\"0;URL=login.php\">";}
  }
  else  {
    include("../includes/connect.php");
    db_modify("delete from online where session_id='$s_id'");
    print "$font <b>fejl i brugernavn eller password<br>";
    print "<meta http-equiv=\"refresh\" content=\"2;URL=index.php\">";
    exit;
  }

  
function online($regnskab, $brugernavn, $password, $timestamp, $s_id)
{
  print "<FORM METHOD=POST NAME=\"online\" ACTION=\"login.php\">";
  print "<table width=50% align=center border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
  print "<tr><td colspan=\"2\" align=\"center\" valign=\"center\">$font <big><b>$brugernavn er allerede logget ind</b></big></td></tr>";
  print "<tr><td colspan=\"2\" align=\"center\">$font <big><b>vil du forts&aelig;tte ?</b></big></td></tr>";

  print "<tr>";
  print "<INPUT TYPE=hidden NAME=regnskab VALUE='$regnskab'>";
  print "<INPUT TYPE=hidden NAME=login VALUE='$brugernavn'>";
  print "<INPUT TYPE=hidden NAME=password VALUE='$password'>";
  print "<INPUT TYPE=hidden NAME=timestamp VALUE='$timestamp'>";
  print "<tr><td><br></td></tr>";
  print "<tr><td><br></td></tr>";
  print "<tr><td><br></td></tr>";
  print "<td align=center><INPUT TYPE=submit name=afbryd VALUE=Afbryd></td>";
  print "<td align=center><INPUT TYPE=submit name=fortsaet VALUE=Forts&aelig;t></td>";
  print "</tr>";
  print "</FORM>";
}
?>
