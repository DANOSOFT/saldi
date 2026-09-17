<?php
// -------------------------------------050423---------------------------------
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
// Copyright (c) 2004-2005 DANOSOFT ApS
// ----------------------------------------------------------------------

$kladde_id=$_GET['kladde_id'];
@session_start();
$s_id=session_id();
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/dkdato.php");
include("../includes/dkdecimal.php");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><title>SALDI - simuleret bogf&oslash;ring</title><meta http-equiv=\"refresh\" content=\"15;URL=../simuler.php?kladde_id=<?php echo $kladde_id?>\">
</script>
</head>
<body bgcolor="#339999" link="#000000" vlink="#000000" alink="#000000" center="">
<div align="center">
<?php

if($_GET)
{
  $kladde_id=$_GET['kladde_id'];

#  print "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";

    $x=0;
    $debetsum=0;
    $kreditsum=0;
      if ($kladde_id)
  {
    $posteringer=0;
    $query = db_select("select * from kassekladde where kladde_id = $kladde_id order by bilag");
    while ($row = db_fetch_array($query))
    {
      $posteringer++;
      $d_type[$posteringer]=$row['d_type'];
      $debet[$posteringer]=$row['debet'];
      $k_type[$posteringer]=$row['k_type'];
      $kredit[$posteringer]=$row['kredit'];
      $faktura[$posteringer]=$row['faktura'];
      $amount[$posteringer]=$row['amount'];
      $momsfri[$posteringer]=$row['momsfri'];
      if($debet[$posteringer]){$debetsum=$debetsum+$amount[$posteringer];}
      if($kredit[$posteringer]){$kreditsum=$kreditsum+$amount[$posteringer];}
    }
  }
  for ($y=1; $y<=$posteringer; $y++)
  {
    if (strlen($debet[$y])>0)
    {
      $debet[$y]=gruppeopslag($d_type[$y], $debet[$y]);
    }
    if (strlen($kredit[$y])>0)
    {
      $kredit[$y]=gruppeopslag($k_type[$y], $kredit[$y]);
    }
  }
  for ($y=1; $y<=$posteringer; $y++)
  {
    $momsfri[$y]=str_replace(" ","",$momsfri[$y]);
    $debet[$y]=str_replace(" ","",$debet[$y]);
    $kredit[$y]=str_replace(" ","",$kredit[$y]);
    if ($debet[$y]>0){$d_amount[$y]=$amount[$y];}
    if ($kredit[$y]>0){$k_amount[$y]=$amount[$y];}
    if ((!$momsfri[$y])&&($debet[$y]>0)&&($d_amount[$y]>0)) {list ($d_amount[$y], $d_moms[$y], $d_momskto[$y])=momsberegning($debet[$y], $d_amount[$y]);}
    if ((!$momsfri[$y])&&($kredit[$y]>0)&&($k_amount[$y]>0)){list ($k_amount[$y], $k_moms[$y], $k_momskto[$y])=momsberegning($kredit[$y], $k_amount[$y]);}
  }
  $kontoantal=0;
  $kontoliste=array()	;
  for ($y=1; $y<=$posteringer; $y++)
  {
    if ((!in_array($debet[$y], $kontoliste))&&($debet[$y]>0))
    {
      $kontoantal++;
      $kontoliste[$kontoantal]=$debet[$y];
    }
    if ((!in_array($kredit[$y], $kontoliste))&&($kredit[$y]>0))
    {
      $kontoantal++;
      $kontoliste[$kontoantal]=$kredit[$y];
    }
    if (($d_momskto[$y])&&(!in_array($d_momskto[$y], $kontoliste)))
    {
      $kontoantal++;
      $kontoliste[$kontoantal]=$d_momskto[$y];
    }
    if (($k_momskto[$y])&&(!in_array($k_momskto[$y], $kontoliste)))
    {
      $kontoantal++;
      $kontoliste[$kontoantal]=$k_momskto[$y];
    }
  }
  sort($kontoliste);
  $kontodebet=array();
  $kontokredit=array();
  for ($y=0; $y<$kontoantal; $y++)
  {
    for($z=1; $z<=$posteringer; $z++)
    {
      if ($kontoliste[$y]==$debet[$z]){$kontodebet[$y]=$kontodebet[$y]+$d_amount[$z];}
      if ($kontoliste[$y]==$kredit[$z]){$kontokredit[$y]=$kontokredit[$y]+$k_amount[$z];}
      if ($kontoliste[$y]==$d_momskto[$z]){$kontodebet[$y]=$kontodebet[$y]+$d_moms[$z];}
      if ($kontoliste[$y]==$k_momskto[$z]){$kontokredit[$y]=$kontokredit[$y]+$k_moms[$z];}
    }
  }
  print "<tr><td align = center><table border=1 cellspacing=0 cellpadding=0><tbody><tr><td width=60>Konto</td><td width=200>Beskrivelse</td><td width=60>Saldo</td><td width=60>Debet</td><td width=60>Kredit</td><td width=60>Ny saldo</td></tr>";
  for ($y=0; $y<$kontoantal; $y++)
  {
    $query = db_select("select * from kontoplan where kontonr='$kontoliste[$y]' and regnskabsaar='$regnaar'");
    $row = db_fetch_array($query);
    $saldo=$row[primo]+$row[md01]+$row[md02]+$row[md03]+$row[md04]+$row[md05]+$row[md06]+$row[md07]+$row[md08]+$row[md09]+$row[md10]+$row[md11]+$row[md12];
    $a=dkdecimal($saldo);
    $b=dkdecimal($kontodebet[$y]);
    $c=dkdecimal($kontokredit[$y]);
    $d=dkdecimal($saldo+$kontodebet[$y]-$kontokredit[$y]);
    print "<tr><td>$kontoliste[$y]</td><td>$row[beskrivelse]</td><td align=right>$a</td><td align=right>$b</td><td align=right>$c</td><td align=right>$d</td></tr>";
  }
  print "</tbody></table>";


    if (abs($debetsum-$kreditsum)>0.0099)
    {
      $diff=$debetsum-$kreditsum;
      print "OBS: Der er en diff. p&aring; $diff!!";
    }
print "</td></tr></tbody></table>";
}
######################################################################################################################################
function momsberegning($konto, $amount)
{
  global $connection;
  global $regnaar;

  $query = db_select("select moms from kontoplan where kontonr='$konto' and regnskabsaar='$regnaar'");
  if($row = db_fetch_array($query))
  {
    $a=substr($row[moms],0,1);
    $b=substr($row[moms],1);
    $c=$a.'M';
    $query = db_select("select box1, box2 from grupper where kode='$a' and kodenr='$b' and art='$c'");
    if($row = db_fetch_array($query))
    {
      $momskto=str_replace(" ","",$row['box1']);
      $x=str_replace(",",".",$row['box2']);
      $moms=$amount-($amount/((100+$x)/100));
      $amount=$amount-$moms;
    }
  }
  $svar=array($amount, $moms, $momskto);
  return $svar;
}
######################################################################################################################################
function gruppeopslag($type, $konto)
{
  global $connection;

#echo "type $type<br>";
  if ($type=='D'){$art='DG';}
  elseif ($type=='K'){$art='KG';}
  if ($art)
  {
    $query = db_select("select gruppe from adresser where kontonr = '$konto' and art = '$type'");
    if ($row = db_fetch_array($query))
    {
      $query = db_select("select box2 from grupper where art='$art' and kodenr='$row[gruppe]'");
      if ($row = db_fetch_array($query))
      {
        $konto=$row['box2'];
      }
    }
  }
#echo "kxonto=$konto<br>";
  return $konto;
}

?>
