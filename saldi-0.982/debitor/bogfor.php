<?
  @session_start();
  $s_id=session_id();
// --------------------------------------------------debitor/bogfor.php--------------------patch 0.935---------
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
// 060905 - Indsat kontrol for om en varemodtagelse er bogfoert, for at for at sikre korrekt flytning fra lager til varekoeb


  $modulnr=5;

  include("../includes/connect.php");
  include("../includes/online.php");
  include("../includes/usdate.php");
  include("../includes/usdecimal.php");
#  include("../includes/db_query.php");

  $id=$_GET['id'];

  $query = db_select("select * from ordrer where id = $id");
  $row = db_fetch_array($query);
  if ($row[status]!=2)
  {
    print "Hmmm - har du brugt browserens opdater eller tilbageknap???";
    print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
    exit;
  }
  $levdate=$row['levdate'];
  $art=$row['art'];
  $kred_ord_id=$row[kred_ord_id];
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
  if (!$row[levdate])
  {
      print "<BODY onLoad=\"javascript:alert('Leveringsdato SKAL udfyldes')\">";
      print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
     exit;
  }
  elseif (!$row[fakturadate])
  {
     print "<BODY onLoad=\"javascript:alert('Fakturadato SKAL udfyldes')\">";
     print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
     exit;
  }
  else
  {
    $fakturadate=$row['fakturadate'];
    $fejl=0;
    if ($row[levdate]<$row[ordredate])
    {
       print "<BODY onLoad=\"javascript:alert('Leveringsdato er f&oslash;r ordredato')\">";
       print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
       exit;
    }
    if ($row[fakturadate]<$row[levdate])
    {
      print "<BODY onLoad=\"javascript:alert('Leveringsdato er f&oslash;r leveringsdato')\">";
      print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
      exit;
    }
    list ($year, $month, $day) = split ('-', $row[fakturadate]);
    $year=substr($year,-2);
    $ym=$year.$month;
    if (($ym<$aarstart)||($ym>$aarslut))
    {
      print "<BODY onLoad=\"javascript:alert('Fakturadato udenfor regnskabs&aring;r')\">";
      print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
      exit;
    }
    if ($fejl==0)
    {
      transaktion("begin");
      echo "bogf&oslash;rer nu!........";
      $fakturanr=1;
      $query = db_select("select fakturanr from ordrer where art = 'DO' or art = 'DK'");
      while ($row = db_fetch_array($query))
      {
        if ($fakturanr <= $row[fakturanr]) {$fakturanr = $row[fakturanr]+1;}
      }
      batch_kob($id, $art); 
      batch_salg($id);
      db_modify("update ordrer set status=3, fakturanr=$fakturanr where id=$id");
      transaktion("commit");
 #  exit;  
  }
    ##################
/*
    $ps_fil="../formularer/$db_id/ps_fakt.php"; $htm_fil="../formularer/$db_id/htm_fakt.php";   
    $query = db_select("select * from grupper where art  = 'PV' and beskrivelse='Faktura'");
    $row = db_fetch_array($query);
    $printerkommando=trim($row['box1']);
    $postscript=trim($row['box2']);
    $html=trim($row['box3']);
    
    if ($postscript=='on')
    {
      if (!file_exists($ps_fil)) 
      {
          $kildefil=str_replace("/$db_id", "", $ps_fil);
          copy($kildefil, $ps_fil);
      }
     print "<BODY onLoad=\"JavaScript:window.open('$ps_fil?id=$id' , '' , 'width=1,height=1');\">";
    }
    if ($html=='on')
    {
      if (!file_exists($htm_fil)) 
      {
          $kildefil=str_replace("/$db_id", "", $htm_fil);
          copy($kildefil, $htm_fil);
      }
      print "<body onload=\"javascript:window.open('$htm_fil?id=$id', '', 'width=600,height=600');\">";
    }
*/
   ###################
    print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
 }

function batch_salg($id) {
  global $fakturadate; 
  
  $x=0;
  $query = db_select("select * from batch_salg where ordre_id = '$id'");
  while ($row = db_fetch_array($query))
  {
    $x++;
    $batch_id[$x]=$row[id];
    $vare_id[$x]=$row[vare_id];  
    $antal[$x]=$row[antal];
    $serienr[$x]=$row['serienr'];
    $batch_kob_id[$x]=$row[batch_kob_id];
    $batch_linje_id[$x]=$row[linje_id];
  }
  $linjeantal=$x;  
  

  for ($x=1; $x<=$linjeantal; $x++) {
    $query = db_select("select id, pris, rabat from ordrelinjer where id = $batch_linje_id[$x]");
    $row = db_fetch_array($query);
    $ordre_linje_id=$row[id];
    $pris = $row[pris]-($row[pris]*$row[rabat]/100);

    db_modify("update batch_salg set pris=$pris, fakturadate='$fakturadate' where id=$batch_id[$x]"); 
    if ($batch_kob_id[$x]) {     
      $query = db_select("select pris, ordre_id from batch_kob where id = $batch_kob_id[$x]");
      if ($row = db_fetch_array($query)) {
        $kobspris=$row['pris'];
        if ($row[ordre_id]) {
          $query = db_select("select status from ordrer where id = $row[ordre_id]");
          $row = db_fetch_array($query);
          if ($row[status]){$kobsstatus=$row[status];}
        }  
        else {$kobsstatus=0;}
      }
    }
#    else {#if ($batch_kob_id[$x]) 
  
    $query2 = db_select("select gruppe from varer where id = $vare_id[$x]");
    $row2 = db_fetch_array($query2);
    $gruppe=$row2['gruppe'];
    $query2 = db_select("select * from grupper where art='VG' and kodenr='$gruppe'");
    $row2 = db_fetch_array($query2);
    $box1=trim($row2[box1]); $box2=trim($row2[box2]); $box3=trim($row2[box3]); $box4=trim($row2[box4]); $box8=trim($row2[box8]);
    db_modify("update ordrelinjer set bogf_konto=$box4 where id=$ordre_linje_id");
    if ($box8=='on')
    {
      if (!$batch_kob_id[$x]) {
        $query = db_select("select linje_id, lager from reservation where batch_salg_id = $batch_id[$x]");
        $row = db_fetch_array($query);
        $res_antal=$res_antal+$row[antal]; 
        $res_linje_id=$row[linje_id];
        $lager=$row[lager];
        $query = db_select("select ordre_id from ordrelinjer where id = $res_linje_id");
        $row = db_fetch_array($query);
        $kob_ordre_id = $row[ordre_id];
      # Hvis levering er sket i flere omgange vil der være flere batch_salg linjer på samme kobs linje, derfor nedenstående.   
        if ($row = db_fetch_array(db_select("select id from batch_kob where linje_id=$res_linje_id and vare_id=$vare_id[$x] and ordre_id=$kob_ordre_id"))) {
          $batch_kob_id[$x]=$row[id];
        }
        else {
           db_modify("insert into batch_kob (linje_id, vare_id, ordre_id, pris, lager) values ($res_linje_id, $vare_id[$x], $kob_ordre_id, $pris, $lager)"); #Antal indsaettes ikke - dette styres i "reservation"
          $row = db_fetch_array(db_select("select id from batch_kob where linje_id=$res_linje_id and vare_id=$vare_id[$x] and ordre_id=$kob_ordre_id"));
          $batch_kob_id[$x]=$row[id];
        } 
        db_modify("update reservation set batch_kob_id=$batch_kob_id[$x] where linje_id = $res_linje_id");
        db_modify("update batch_salg set batch_kob_id=$batch_kob_id[$x] where id=$batch_id[$x]");    
      }
      $row = db_fetch_array(db_select("select pris from batch_kob where id=$batch_kob_id[$x]"));
      if (!$row['pris']) {db_modify("update batch_kob set pris='$pris' where id=$batch_kob_id[$x]");}    
      else {$pris=$row['pris'];} 
      db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1',$antal[$x], '$pris', 0, $id, $box2)");
      $pris=$pris*-1;
      db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1',$antal[$x], '$pris', 0, $id, $box3)");
    }
/*
      if (($box8=='on') && ($kobsstatus < 3)) {
      db_modify("update batch_kob set pris=$pris where id=$batch_kob_id[$x]");
echo "insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1',$antal[$x], '$pris', 0, $id, $box2)<br>";        
      db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1',$antal[$x], '$pris', 0, $id, $box2)");
      $pris=$pris*-1;
echo "insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1',$antal[$x], '$pris', 0, $id, $box3)<br>";        
      db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1',$antal[$x], '$pris', 0, $id, $box3)");
    }
*/
/*
    elseif ($box8=='on') {
      $query = db_select("select pris from batch_kob where id = $batch_kob_id[$x]");
      $row = db_fetch_array($query);
      $kobspris = $row['pris'];
    if ($kobspris){
echo "insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1',$antal[$x], '$kobspris', 0, $id, $box2)<br>";
      db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1',$antal[$x], '$kobspris', 0, $id, $box2)");
      $kobspris=$kobspris*-1;
echo "insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1',$antal[$x], '$kobspris', 0, $id, $box3)<br>";
      db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1',$antal[$x], '$kobspris', 0, $id, $box3)");
      db_modify("delete from reservation where linje_id=$ordre_linje_id");
      }}
*/
#    }
  }
}
  function batch_kob($id, $art) {
  global $fakturadate; 

  $query = db_select("select * from batch_kob where ordre_id = '$id'");
  while ($row = db_fetch_array($query))
  {
    $x++;
    $batch_id=$row[id];
    $vare_id=$row[vare_id];
    $antal=$row[antal];
    $serienr=$row['serienr'];
    $batch_kob_id=$row[batch_kob_id]; 
    $query2 = db_select("select id, pris, rabat from ordrelinjer where id = $row[linje_id]");
    $row2 = db_fetch_array($query2);
    $ordre_linje_id=$row2[id];
    $pris = $row2[pris]-($row2[pris]*$row2[rabat]/100);
    if ($row[pris]) {$diff = $pris-$row[pris];}
    db_modify("update batch_kob set pris=$pris, fakturadate='$fakturadate' where id=$batch_id");
 /*
          $tmp = db_fetch_array(db_select("select id from ordrelinjer where ordre_id = $kred_ord_id and vare_id = $vare_id"));
          $tmp = db_fetch_array(db_select("select batch_kob_id from batch_salg where linje_id = $tmp[id] and vare_id = $vare_id"));
          $tmp = db_fetch_array(db_select("select pris from batch_kob where id = $tmp[batch_kob_id] and vare_id = $vare_id"));
          $kobspris=$tmp[pris]; #-($tmp[pris]*$tmp[rabat]/100);
*/
    $query2 = db_select("select gruppe from varer where id = $vare_id");
    $row2 = db_fetch_array($query2);
    $gruppe=$row2['gruppe'];
    $query2 = db_select("select * from grupper where art='VG' and kodenr='$gruppe'");
    $row2 = db_fetch_array($query2);
    $box1=trim($row2[box1]); $box2=trim($row2[box2]); $box3=trim($row2[box3]); $box4=trim($row2[box4]); $box8=trim($row2[box8]);
    db_modify("update ordrelinjer set bogf_konto=$box4 where id=$ordre_linje_id");
    if ($box8=='on')
    {
#echo "$pris - ";
      $pris=$pris-$diff;
#echo "$pris - ";
      if (!$pris){$pris=0;}       
#      if ($art="DK"){$pris=$pris*-1;}
#echo "$pris - ";
      db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1', $antal, $pris, 0, $id, $box3)");
      $pris=$pris*-1;
      db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto) values ('-1', $antal, $pris, 0, $id, $box2)");
#exit;
    }
  }


}


?>      
</body></html>
