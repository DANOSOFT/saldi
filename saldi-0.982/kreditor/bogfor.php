<?
  @session_start();
  $s_id=session_id();
// -------------------------------------kreditor/bogfor.php-------patch 0.934-------------------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaetelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
// ----------------------------------------------------------------------

 if ((!$sqhost)||(!$dbuser)||(!$db))
 {
   include("../includes/connect.php");
   include("../includes/online.php");
  }
  include("../includes/usdate.php");
  include("../includes/dkdecimal.php");
  include("../includes/db_query.php");

  $id=$_GET['id'];
  
  ?>
    <script language="JavaScript">
    <!--
    function fejltekst(tekst)
    {
      alert(tekst);
      window.location.replace("ordre.php?id=<?echo $id?>");
    }
-->
</script>
<?

  $query = db_select("select levdate, status from ordrer where id = $id");
  $row = db_fetch_array($query);
  if ($row[status]>2)
  {
    print "Hmmm - har du brugt browserens opdater eller tilbageknap???";
    print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
    exit;
  }

 
  $query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'");
  if ($row = db_fetch_array($query))
  {
    $year=substr(str_replace(" ","",$row['box2']),-2);
    $aarstart=str_replace(" ","",$year.$row['box1']);
    $year=substr(str_replace(" ","",$row['box4']),-2);
    $aarslut=str_replace(" ","",$year.$row['box3']);
  }

  $query = db_select("select * from ordrer where id = '$id'");
  $row = db_fetch_array($query);
  $art=$row[art];
  $kred_ord_id=$row[kred_ord_id];
#  if ($row[status]>=2)
#  {
#    print "Hov hov du - du har bogf&oslash;rt den en gang";
#    print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
#    exit;
#  }
  if (!$row[levdate])
  {
    print "<BODY onLoad=\"fejltekst('Leveringsdato SKAL udfyldes')\">";
    print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
    exit;
  }
  elseif (strlen(trim($row[fakturanr]))<1)
  {
     print "<BODY onLoad=\"fejltekst('Fakturanummer SKAL udfyldes')\">";
     print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
     exit;
  }
  else
  {
    $fejl=0;
    if ($row[levdate]<$row[ordredate])
    {
       print "<BODY onLoad=\"fejltekst('Leveringsdato er f&oslash;r ordredato')\">";
       print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
       exit;
    }
    $levdate=$row[levdate];
    list ($year, $month, $day) = split ('-', $row[levdate]);
    $year=substr($year,-2);
    $ym=$year.$month;
    if (($ym<$aarstart)||($ym>$aarslut))
    {
      print "<BODY onLoad=\"fejltekst('Leveringsdato udenfor regnskabs&aring;r')\">";
      print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
      exit;
    }
    if ($fejl==0)
    {
      echo "bogf&oslash;rer nu!........";
      transaktion("begin");
      $x=0;
      $query = db_select("select * from ordrelinjer where ordre_id = '$id'");
      while ($row = db_fetch_array($query))
      {
        if (($row[posnr]>0)&&(strlen(trim(($row[varenr])))>0))
        {
          $x++;
          $linje_id[$x]=$row['id'];
          $kred_linje_id[$x]=$row['kred_linje_id'];
          $varenr[$x]=$row['varenr'];
          $antal[$x]=$row['antal'];
          $pris[$x]=$row[pris]-($row[pris]*$row[rabat]/100);
          $serienr[$x]=$row['serienr'];
        }
      }
      $linjeantal=$x;
      for ($x=1; $x<=$linjeantal; $x++)
      {
        $query = db_select("select id, gruppe from varer where varenr='$varenr[$x]'");
        $row = db_fetch_array($query);
        $vare_id[$x]=$row[id];
        $gruppe[$x]=$row[gruppe];
      }
      for ($x=1; $x<=$linjeantal; $x++)
      {
        if (($vare_id[$x])&&($antal[$x]!=0))
        {
          $query = db_select("select * from grupper where art='VG' and kodenr='$gruppe[$x]'");
          $row = db_fetch_array($query);
          $box1=trim($row[box1]); $box2=trim($row[box2]); $box3=trim($row[box3]); $box4=trim($row[box4]); $box8=trim($row[box8]);
          if ($box8!='on'){db_modify("update ordrelinjer set bogf_konto='$box3' where id='$linje_id[$x]'");}
          else
	  {
            db_modify("update ordrelinjer set bogf_konto='$box1' where id='$linje_id[$x]'");
            if ($antal[$x]>0) {
              $query = db_select("select * from batch_kob where linje_id=$linje_id[$x]");
              if ($row = db_fetch_array($query)) {
                $batch_id=$row[id];
                if ($row[pris]!=0) {
                  $diff=$pris[$x]-$row[pris];
                  $batch_antal=$row[antal];
                  $batch_rest=$row[rest];
                  db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1', $batch_antal-$batch_rest, '$diff', 0, $id, $box3)");
                  $diff=$diff*-1;
                  db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1', $batch_antal-$batch_rest, '$diff', 0, $id, $box2)");
                  }
                  db_modify("update batch_kob set pris = '$pris[$x]', fakturadate='$levdate' where id=$batch_id");
                }
              }  
              else {
               $query = db_select("select * from batch_kob where linje_id=$kred_linje_id[$x]");
                if ($row = db_fetch_array($query)) {
                  $batch_id=$row[id];
                  $diff=$pris[$x]-$row[pris];
                 db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1', $antal[$x], '$diff', 0, $id, $box1)");
                  $diff=$diff*-1;
                  db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1', $antal[$x], '$diff', 0, $id, $box3)");
                }
                $query = db_select("select * from batch_kob where vare_id=$vare_id[$x] and linje_id=$linje_id[$x]");
                while ($row = db_fetch_array($query)) {
                  db_modify("update batch_kob set pris = '$pris[$x]', fakturadate='$levdate' where id=$row[id]");
                }
              }
            }
         }
     }
      $modtagelse=1;
      $query = db_select("select modtagelse from ordrer order by modtagelse");
      while ($row = db_fetch_array($query))
      {  
          if ($row[modtagelse] >=$modtagelse) {$modtagelse = $row[modtagelse]+1;}
      }
      if ($modtagelse==1)
      {
        $query = db_select("select box2 from grupper where art = 'RB'");
        if ($row = db_fetch_array($query))  {$modtagelse = $row[box2]*1;}
      }
       db_modify("update ordrer set status=3, fakturadate='$levdate', modtagelse = $modtagelse where id=$id");
       transaktion("commit");
 #    exit;
    }
  }
#  print "<a href=ordre.php?id=$id accesskey=T>retur</a>";
  print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";

?>
</tbody></table>
</td></tr>
</tbody></table>
</body></html>
