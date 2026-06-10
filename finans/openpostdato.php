<?php
// ----------------------------------------------------------------------050422----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg

// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2005 DANOSOFT ApS
// ----------------------------------------------------------------------

$regnskabsaar=$_GET['regnskabsaar'];
  
@session_start();
$s_id=session_id();
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
  
$x=0;
$query = db_select("select * from openpost");
while ($row = db_fetch_array($query))
{
  $x++;
  $id[$x]=$row['id'];
  $bilag[$x]=$row['refnr'];
  $kladde_id[$x]=$row['kladde_id'];
  $fakt_nr[$x]=trim($row['faktnr']);
  $amount[$x]=$row['amount'];
  $konto_nr[$x]=trim($row['konto_nr']);
  $transdate[$x]=$row['transdate'];
  if ($amount[$x]<0){$amount[$x]=$amount[$x]*-1;}
}
$postantal=$x;

transaktion('begin');
for ($x=1; $x<=$postantal; $x++)
{
#  echo "select transdate from kassekladde where bilag='$bilag[$x]' and kladde_id='$kladde_id[$x]' and amount = '$amount[$x]' and faktura = '$fakt_nr[$x]' and debet = '$konto_nr[$x]' and transdate != '$transdate[$x]'<br>";
  $query = db_select ("select transdate from kassekladde where bilag='$bilag[$x]' and kladde_id='$kladde_id[$x]' and amount = '$amount[$x]' and faktura = '$fakt_nr[$x]' and transdate != '$transdate[$x]' and (debet = '$konto_nr[$x]' or kredit = '$konto_nr[$x]')");
  if ($row = db_fetch_array($query)) {db_modify("update openpost set transdate = '$row[transdate]' where id = $id[$x]");}
}
transaktion('commit');
?>
