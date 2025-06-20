<?
// -------------------------------------------finans/hentortrer.pgp------------patch 0.956------------------
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

  if ((!$sqhost)||(!$dbuser)||(!$db))
  {
    include("../includes/connect.php");
    include("../includes/online.php");
    include("../includes/dkdato.php");
    include("../includes/dkdecimal.php");
    include("../includes/db_query.php");
    

  }
  $kladde_id = $HTTP_POST_VARS['kladde_id'];
  $antal_ny=$HTTP_POST_VARS['antal_ny'];
#  $h=$antal_ny*10;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><title>SALDI - Hent ordrer</title><meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">
<div align="center">
<?

if($_GET)
{
  $kladde_id=$_GET['kladde_id'];
  $ordre_id=$_GET['ordre_id'];

  print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
  print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
  print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
  print "<td width=\"25%\" bgcolor=\"$bgcolor2\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small><a href=kassekladde.php?kladde_id=$kladde_id accesskey=T>Tilbage</a></small></td>";
  print "<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>Kassekladde $kladde_id</small></td>";
  print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"></td>";
  print "</tbody></table>";
  print "</td></tr>";

  if (($kladde_id)&&($ordre_id))
  {
    flytordre($kladde_id, $ordre_id);
  }
  if ($kladde_id)
  {
    hentordrer($kladde_id);
  }
  print "</tbody></table>";
}
################################################################################################################
function hentordrer($kladde_id)
{
  global $regnaar;
  global $connection;
  global $aarstart;
  global $aarslut;

  if (!$aarstart);
  {
    $query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'");
    if ($row = db_fetch_array($query))
    {
      $year=substr(str_replace(" ","",$row['box2']),-2);
      $aarstart=str_replace(" ","",$year.$row['box1']);
      $year=substr(str_replace(" ","",$row['box4']),-2);
      $aarslut=str_replace(" ","",$year.$row['box3']);
    }
  }
  $x=0;
  print "<tr><td align = center><table border=1 cellspacing=0 cellpadding=0 width=80%><tbody>";
  print "<tr><td>Dato</td><Td>Beskrivelse</td><td><br></td><td>Debet</td><td><br></td><td>Kredit</td><td>Fakturanr</td><td align=center>Bel&oslash;b</td></tr>";
  $query = db_select("select * from ordrer where status=3");
  while ($row = db_fetch_array($query))
  {
#    list ($year, $month, $day) = split ('-', $row[fakturadate]);
#    $year=substr($year,-2);
#    $ym=$year.$month;
#    if (($ym>=$aarstart)&&($ym<=$aarslut))
#    {
      $x++;
      $id[$x]=$row[id];
      $art[$x]=$row[art];
      $konto_id[$x]=trim($row[konto_id]);
      $kontonr[$x]=trim($row[kontonr]);
      $firmanavn[$x]=trim($row[firmanavn]);
      $fakturadato[$x]=dkdato($row[fakturadate]);
      $fakturanr[$x]=trim($row[fakturanr]);
      if ($row[moms]) {$moms[$x]=$row[moms];}
      else {$moms[$x]=round($row[sum]*$row[momssats]/100,2);}
      $sum[$x]=$row[sum]+$moms[$x];
#    }
  
  }
  $ordreantal=$x;

  for ($x=1;$x<=$ordreantal;$x++)
  {
    print "<tr><td></td></tr>";
    $query = db_select("select * from ordrelinjer where ordre_id=$id[$x];");
    $y=0;
    $bogf_konto = array();
    while ($row = db_fetch_array($query))
    {
      if (!in_array($row[bogf_konto], $bogf_konto))
      {
        $y++;
        $bogf_konto[$y]=trim($row[bogf_konto]);
        $pris[$y]=$row[pris]*$row[antal]-round(($row[pris]*$row[antal]*$row[rabat]/100),2);
      }
      else
      {
        for ($a=1; $a<=$y; $a++)
        {
          if ($bogf_konto[$a]==$row[bogf_konto]) 
          {
            $pris[$a]=$pris[$a]+($row[pris]*$row[antal]-round(($row[pris]*$row[antal]*$row[rabat]/100),2));
          }
        }     
      }
    }
    if (substr($art[$x],0,1)=='K')
    {
      if ($sum[$x] < 0) {
        $dksum=dkdecimal($sum[$x]*-1);
        print "<tr><td>$fakturadato[$x]</td><td>$firmanavn[$x]</td><td>K</td><td>$kontonr[$x]</td><td><br></td><td><br></td><td>$fakturanr[$x]</td><td align=right>$dksum</td>";
      }
      else {
        $dksum=dkdecimal($sum[$x]);
        print "<tr><td>$fakturadato[$x]</td><td>$firmanavn[$x]</td><td><br></td><td><br></td><td>K</td><td>$kontonr[$x]</td><td>$fakturanr[$x]</td><td align=right>$sum[$x]</td>";
      }
      print "<td align=center><a href=hentordrer.php?kladde_id=$kladde_id&ordre_id=$id[$x]>Flyt til kladde</a></td></tr>";
      $ordrelinjer=$y;
      for ($y=1;$y<=$ordrelinjer;$y++)
      {
        if ($bogf_konto[$y])
        {
          $kontoart[$y]=$art[$x];
   #       if ($art[$x]=='KO')
  #        {
            if ($pris[$y]<0)
            {
              $pris[$y]=$pris[$y]*-1;
              $pris[$y]=dkdecimal($pris[$y]);
              print "<tr><td>$fakturadato[$x]</td><td>$firmanavn[$x]</td><td><br></td><td><br></td><td>F</td><td>$bogf_konto[$y]</td><td>$fakturanr[$x]</td><td align=right>$pris[$y]</td></tr>";
            }
            elseif ($pris[$y]>0) 
            {
               $pris[$y]=dkdecimal($pris[$y]);
               print "<tr><td>$fakturadato[$x]</td><td>$firmanavn[$x]</td><td>F</td><td>$bogf_konto[$y]</td><td><br></td><td><br></td><td>$fakturanr[$x]</td><td align=right>$pris[$y]</td></tr>";
            }
#          }
/*
          else
          {
            if ($pris[$y]<0)
            {
              $pris[$y]=$pris[$y]*-1;
              $pris[$y]=dkdecimal($pris[$y]);
              print "<tr><td>$fakturadato[$x]</td><td>$firmanavn[$x]</td><td>F</td><td>$bogf_konto[$y]</td><td><br></td><td><br></td><td>$fakturanr[$x]</td><td align=right>$pris[$y]</td></tr>";
            }
            elseif ($pris[$y]>0) 
            {
              $pris[$y]=dkdecimal($pris[$y]);
              print "<tr><td>$fakturadato[$x]</td><td>$firmanavn[$x]</td><td><br></td><td><br></td><td>F</td><td>$bogf_konto[$y]</td><td>$fakturanr[$x]</td><td align=right>$pris[$y]</td></tr>";
            } 
          }
*/
        }
      }
      $query = db_select("select gruppe from adresser where id='$konto_id[$x]';");
      $row = db_fetch_array($query);
      $query = db_select("select box1 from grupper where art='KG' and kodenr='$row[gruppe]';");
      $row = db_fetch_array($query);
      $box1=trim($row[box1]);
      $query = db_select("select box1 from grupper where art='KM' and kodenr='$box1'");
      $row = db_fetch_array($query);
      $box1=trim($row[box1]);
      
#      $moms[$x]=dkdecimal($moms[$x]);
      if ($moms[$x]<0){
        $dkmoms=dkdecimal($moms[$x]*-1);
        print "<tr><td>$fakturadato[$x]</td><td>$firmanavn[$x]</td><td><br></td><td><br></td><td>F</td><td>$box1</td><td>$fakturanr[$x]</td><td align=right>$dkmoms</td></tr>";
      }
      elseif ($moms[$x]>0) {
        $dkmoms=dkdecimal($moms[$x]);
        print "<tr><td>$fakturadato[$x]</td><td>$firmanavn[$x]</td><td>F</td><td>$box1</td><td><br></td><td><br></td><td>$fakturanr[$x]</td><td align=right>$dkmoms</td></tr>";
      }
    } 
    else
    { 
      if ($sum[$x]<0){
      $dksum=dkdecimal($sum[$x]*-1);
        print "<tr><td>$fakturadato[$x]</td><td>$firmanavn[$x]</td><td><br></td><td><br></td><td>D</td><td>$kontonr[$x]</td><td>$fakturanr[$x]</td><td align=right>$dksum</td>";
      }
      else {
        $dksum=dkdecimal($sum[$x]);
        print "<tr><td>$fakturadato[$x]</td><td>$firmanavn[$x]</td><td>D</td><td>$kontonr[$x]</td><td><br></td><td><br></td><td>$fakturanr[$x]</td><td align=right>$dksum</td>";
      }
#     print "<td align=center><input type=checkbox name=flyt[$y] checked></td></tr>";
      print "<td align=center><a href=hentordrer.php?kladde_id=$kladde_id&ordre_id=$id[$x]>Flyt til kladde</a></td></tr>";
      
      $ordrelinjer=$y;
      for ($y=1;$y<=$ordrelinjer;$y++)
      {
        if ($bogf_konto[$y])
        {
          $kontoart[$y]=$art[$x];
#          if ($art[$x]=='DO')
 #         {
            if ($pris[$y]<0)
            {
              $pris[$y]=$pris[$y]*-1;
              $pris[$y]=dkdecimal($pris[$y]);
              print "<tr><td>$fakturadato[$x]</td><td>$firmanavn[$x]</td><td>F</td><td>$bogf_konto[$y]</td><td><br></td><td><br></td><td>$fakturanr[$x]</td><td align=right>$pris[$y]</td></tr>";
            }
            else
            {      
              $pris[$y]=dkdecimal($pris[$y]);
              print "<tr><td>$fakturadato[$x]</td><td>$firmanavn[$x]</td><td><br></td><td><br></td><td>F</td><td>$bogf_konto[$y]</td><td>$fakturanr[$x]</td><td align=right>$pris[$y]</td></tr>";
            }
#          }
/*
          else
          {
            if ($pris[$y]<0)
            {
              $pris[$y]=$pris[$y]*-1;
              $pris[$y]=dkdecimal($pris[$y]);
              print "<tr><td>$fakturadato[$x]</td><td>$firmanavn[$x]</td><td><br></td><td><br></td><td>F</td><td>$bogf_konto[$y]</td><td>$fakturanr[$x]</td><td align=right>$pris[$y]</td></tr>";
            }
            else
            {      
              $pris[$y]=dkdecimal($pris[$y]);
              print "<tr><td>$fakturadato[$x]</td><td>$firmanavn[$x]</td><td>F</td><td>$bogf_konto[$y]</td><td><br></td><td><br></td><td>$fakturanr[$x]</td><td align=right>$pris[$y]</td></tr>";
            }
          }
*/
        }
      }
      $query = db_select("select gruppe from adresser where id='$konto_id[$x]';");
      $row = db_fetch_array($query);
      $query = db_select("select box1 from grupper where art='DG' and kodenr='$row[gruppe]';");
      $row = db_fetch_array($query);
      $box1=trim($row[box1]);
      $query = db_select("select box1 from grupper where art='SM' and kodenr='$box1'");
      $row = db_fetch_array($query);
      $box1=trim($row[box1]);
      $moms[$x]=dkdecimal($moms[$x]);
      if ($art[$x]=='DO') {print "<tr><td>$fakturadato[$x]</td><td>$firmanavn[$x]</td><td><br></td><td><br></td><td>F</td><td>$box1</td><td>$fakturanr[$x]</td><td align=right>$moms[$x]</td></tr>";}
      else {print "<tr><td>$fakturadato[$x]</td><td>$firmanavn[$x]</td><td>F</td><td>$box1</td><td><br></td><td><br></td><td>$fakturanr[$x]</td><td align=right>$moms[$x]</td></tr>";} 
    }
    $bilag=$bilag+1;
  }
  print "</tbody></table></td></tr>";
exit;
}
######################################################################################################################################
function flytordre($kladde_id, $ordre_id) {
  global $regnaar;
  global $connection;
  global $aarstart;
  global $aarslut;

  transaktion("begin");

  if (!$aarstart) {
    $query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'");
    if ($row = db_fetch_array($query)) {
    $year=substr(str_replace(" ","",$row['box2']),-2);
    $aarstart=str_replace(" ","",$year.$row['box1']);
    $year=substr(str_replace(" ","",$row['box4']),-2);
    $aarslut=str_replace(" ","",$year.$row['box3']);
    }
  }
  $query = db_select("select box1, box2, box3, box4 from grupper where art='RB'");
  if ($row = db_fetch_array($query)) {
    if (trim($row['box3'])=="ON") {$faktbill=1;} 
    else {$faktbill=0;}
    if (trim($row['box4'])=="ON") {$modtbill=1;} 
    else {$modtbill=0;}
  }
  
  $x=0;
  $query = db_select("select * from ordrer where status=3 and id='$ordre_id'");
  if ($row = db_fetch_array($query)) {
    list ($year, $month, $day) = split ('-', $row[fakturadate]);
    $year=substr($year,-2);
    $ym=$year.$month;
    if (($ym>=$aarstart)&&($ym<=$aarslut)) {
      $id=$row[id];
      $art=$row[art];
      $konto_id=$row[konto_id];
      $kontonr=str_replace(" ","",$row[kontonr]);
      $firmanavn=trim($row[firmanavn]);
      $modtagelse=$row[modtagelse];
      $transdate=($row[fakturadate]);
      $fakturanr=$row[fakturanr];
      if ($row[moms]) {$moms=$row[moms];}
      else {$moms=round($row[sum]*$row[momssats]/100,2);}
      $sum=$row[sum]+$moms;
      $ordreantal=$x;
      if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$row[ref]'"))) {$afd=$row[afd];}
      $afd=$afd*1; #sikkerhed for at 'afd' har en værdi 
       
      if (($modtagelse>0) && ($modtbill="ON")) {$bilag=$modtagelse;}
      elseif ($faktbill=='ON'){$bilag=trim($fakturanr);}
      else {
        $bilag=1;
        $query = db_select("select bilag from kassekladde");
        while ($row = db_fetch_array($query)) {
          if ($bilag<=$row['bilag']){$bilag=$row['bilag']+1;}
        }
      }
      if (strlen($firmanavn)+strlen($fakturanr) > 46) {$firmanavn=(substr($firmanavn, 0, (47-strlen($fakturanr))))." - ".$fakturanr;}
      else {$firmanavn=$firmanavn." - ".$fakturanr;}
      
      if (substr($art[$x],0,1)=='K') {
         db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd) values ('$bilag', '$transdate', '$firmanavn', 'K', '$kontonr', '$fakturanr', '$sum', 'on', '$kladde_id', $afd)");
        $query = db_select("select * from ordrelinjer where ordre_id=$id;");
        $y=0;
        $bogf_konto = array();
        while ($row = db_fetch_array($query)) {
          if (!in_array($row[bogf_konto], $bogf_konto)) {  
            $y++;
            $bogf_konto[$y]=trim($row[bogf_konto]);
            $pris[$y]=$row[pris]*$row[antal]-round(($row[pris]*$row[antal]*$row[rabat]/100),2);
          }
          else {
            for ($a=1; $a<=$y; $a++) {
              if ($bogf_konto[$a]==$row[bogf_konto]) {
                $pris[$a]=$pris[$a]+($row[pris]*$row[antal]-round(($row[pris]*$row[antal]*$row[rabat]/100),2));
              }
            }     
          }
        }
        $ordrelinjer=$y;
        for ($y=1;$y<=$ordrelinjer;$y++) {
          if ($bogf_konto[$y]) {
            $kontoart[$y]=$art;
            if ($pris[$y]<0) {     
              $pris[$y]=$pris[$y]*-1;
              db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd) values ('$bilag', '$transdate', '$firmanavn', 'F', '$bogf_konto[$y]', '$fakturanr', '$pris[$y]', 'on', '$kladde_id', $afd)");
            }
            elseif ($pris[$y]>0) {db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd) values ('$bilag', '$transdate', '$firmanavn', 'F', '$bogf_konto[$y]', '$fakturanr', '$pris[$y]', 'on', '$kladde_id', $afd)");}
          }
        }
        $query = db_select("select gruppe from adresser where id='$konto_id';");
        $row = db_fetch_array($query);
        $query = db_select("select box1 from grupper where art='KG' and kodenr='$row[gruppe]';");
        $row = db_fetch_array($query);
        $box1=trim($row[box1]);
        $query = db_select("select box1 from grupper where art='KM' and kodenr='$box1'");
        $row = db_fetch_array($query);
        $box1=trim($row[box1]);
        db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd) values ('$bilag', '$transdate', '$firmanavn', 'F', '$box1', '$fakturanr', '$moms', 'on', '$kladde_id', $afd)");
      }
      else  {
        if ($sum[$x]<0) {db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd) values ('$bilag', '$transdate', '$firmanavn', 'D', '$kontonr', '$fakturanr', '$sum', 'on', '$kladde_id', $afd)");}
        else {db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd) values ('$bilag', '$transdate', '$firmanavn', 'D', '$kontonr', '$fakturanr', '$sum', 'on', '$kladde_id', $afd)");}
        $query = db_select("select * from ordrelinjer where ordre_id=$id;");
        $y=0;
        $bogf_konto = array();
        while ($row = db_fetch_array($query)) {
          if (!in_array($row[bogf_konto], $bogf_konto)) {
            $y++;
            $bogf_konto[$y]=$row[bogf_konto];
            $pris[$y]=$row[pris]*$row[antal]-round(($row[pris]*$row[antal]*$row[rabat]/100),2);
          }
          else {
            for ($a=1; $a<=$y; $a++) {
              if ($bogf_konto[$a]==$row[bogf_konto]) {
                $pris[$a]=$pris[$a]+($row[pris]*$row[antal]-round(($row[pris]*$row[antal]*$row[rabat]/100),2));
              }
            }     
          }
        }
        $ordrelinjer=$y;
        for ($y=1;$y<=$ordrelinjer;$y++) {
          if ($bogf_konto[$y]) {
            if ($pris[$y]<0)
            {     
              $pris[$y]=$pris[$y]*-1;
              db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd) values ('$bilag', '$transdate', '$firmanavn', 'F', '$bogf_konto[$y]', '$fakturanr', '$pris[$y]', 'on', '$kladde_id', $afd)");
            }
            elseif ($pris[$y]>0) {db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd) values ('$bilag', '$transdate', '$firmanavn', 'F', '$bogf_konto[$y]', '$fakturanr', '$pris[$y]', 'on', '$kladde_id', $afd)");}
          }
        }
        $query = db_select("select gruppe from adresser where id='$konto_id';");
        $row = db_fetch_array($query);
        $query = db_select("select box1 from grupper where art='DG' and kodenr='$row[gruppe]';");
        $row = db_fetch_array($query);
        $box1=trim($row[box1]);
        $query = db_select("select box1 from grupper where art='SM' and kodenr='$box1'");
        $row = db_fetch_array($query);
        $box1=trim($row[box1]);
        db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd) values ('$bilag', '$transdate', '$firmanavn', 'F', '$box1', '$fakturanr', '$moms', 'on', '$kladde_id', $afd)");
      }
      db_modify("update ordrer set status=4 where id=$id");
      db_modify("delete from ordrelinjer where ordre_id=$id and posnr < 0");
    }
    else {Print "<BODY onLoad=\"javascript:alert('Ordredato uden for regnskabs&aring;r!')\">";}
  }
  transaktion("commit");
}
######################################################################################################################################
