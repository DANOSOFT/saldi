<?
// -------------------------------------------------------debitor/ordrer---patch0.971------------
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

#print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Kundeordre</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";
  ?>
    <script type="text/javascript">
    <!--
    var linje_id=0;
    var antal=0;
    function serienummer(linje_id, antal)
    {
      window.open("serienummer.php?linje_id="+ linje_id,"","left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no")
    }
    function batch(linje_id, antal)
    {
      window.open("batch.php?linje_id="+ linje_id,"","left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no")
    }
    //-->
    </script>
  <?
  $modulnr=5; 
  
  include("../includes/connect.php");
  include("../includes/online.php");
  include("../includes/dkdato.php");
  include("../includes/usdate.php");
  include("../includes/dkdecimal.php");
  include("../includes/usdecimal.php");
  $tidspkt=date("U");
    
  if ($tjek=$_GET['tjek'])
  {
    $query = db_select("select tidspkt, hvem from ordrer where status < 3 and id = $tjek and hvem != '$brugernavn'");
    if ($row = db_fetch_array($query))
    {
      if ($tidspkt-($row['tidspkt'])<3600) 
      {
        print "<BODY onLoad=\"javascript:alert('Ordren er i brug af $row[hvem]')\">";
        print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
      }
      else {
      db_modify("update ordrer set hvem = '$brugernavn', tidspkt='$tidspkt' where id = '$tjek'");}
    }
  }

  $id=$_GET['id'];
  $sort=$_GET['sort'];
  $fokus=$_GET['fokus'];
  $submit=$_GET['funktion'];
  $vis_kost=$_GET['vis_kost'];
  $bogfor=1;

  if (($kontakt=$_GET['kontakt'])&&($id)) {db_modify("update ordrer set kontakt='$kontakt' where id=$id");}

  if ($_GET['konto_id'])
  {
    $konto_id=$_GET['konto_id'];
    $query = db_select("select * from adresser where id = '$konto_id'");
    if ($row = db_fetch_array($query))
    {
      $kontonr=$row['kontonr'];
      $firmanavn=stripslashes(htmlentities($row['firmanavn']));
      $addr1=stripslashes(htmlentities($row['addr1']));
      $addr2=stripslashes(htmlentities($row['addr2']));
      $postnr=stripslashes(htmlentities($row['postnr']));
      $bynavn=stripslashes(htmlentities($row['bynavn']));
      $land=stripslashes(htmlentities($row['land']));
      $betalingsdage=$row['betalingsdage'];
      $betalingsbet=$row['betalingsbet'];
      $cvrnr=$row['cvrnr'];
      $ean=$row['ean'];
      $institution=stripslashes(htmlentities($row['institution']));
      $notes=stripslashes(htmlentities($row['notes']));
      $gruppe=$row['gruppe'];
   }
    if ($gruppe)
    {
      $query = db_select("select box1 from grupper where art='DG' and kodenr='$gruppe'");
      $row = db_fetch_array($query);
      $query = db_select("select box2 from grupper where art='SM' and kodenr='$row[box1]'");
      $row = db_fetch_array($query);
      $momssats=$row[box2];
      if (!$momssats)
      {
        print "<BODY onLoad=\"javascript:alert('Debitorgrupper forkert opsat')\">";
        print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">";
        exit;
      }
    }
  }
  if ((!$id)&&($firmanavn))
  {
    $query = db_select("select ordrenr from ordrer where art='DO' or art='DK' order by ordrenr desc");
    if ($row = db_fetch_array($query)) {$ordrenr=$row[ordrenr]+1;}
    else {$ordrenr=1;}
    $ordredate=date("Y-m-d");
    db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, betalingsdage, betalingsbet, cvrnr, ean, institution, notes, art, ordredate, momssats, hvem, tidspkt) values ($ordrenr, '$konto_id', '$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$betalingsdage', '$betalingsbet', '$cvrnr', '$ean', '$institution', '$notes', 'DO', '$ordredate', '$momssats', '$brugernavn', '$tidspkt')");
    $query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc");
    if ($row = db_fetch_array($query)) {$id=$row[id];}
  }
  elseif($firmanavn) {
    $query = db_select("select tidspkt from ordrer where id=$id and hvem='$brugernavn'");
    if ($row = db_fetch_array($query)) {
      db_modify("update ordrer set kontonr='$kontonr', kundeordnr='$kundeordnr', firmanavn='$firmanavn', addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', lev_navn='$lev_navn',  lev_addr1='$lev_addr1',  lev_addr2='$lev_addr2', lev_postnr='$lev_postnr', lev_bynavn='$lev_bynavn', lev_kontakt='$lev_kontakt', betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr', ean='$ean', institution='$institution', notes='$notes', hvem = '$brugernavn', tidspkt='$tidspkt' where id=$id");
    }
    else  {      
      $query = db_select("select hvem from ordrer where id=$id");
      if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Ordren er-  overtaget af $row[hvem]')\">";}
      else {print "<BODY onLoad=\"javascript:alert('Du er blevet smidt af')\">";}
      print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
    }  
  }

  if ($_GET['vare_id']) {
    $query = db_select("select status from ordrer where id = $id");
    $row = db_fetch_array($query);
    if ($row[status]>2) {
      print "Hmmm - har du brugt browserens opdater eller tilbageknap???";
      print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">";
      exit;
    }
    else {
      $vare_id=$_GET['vare_id'];
      $linjenr=substr($fokus,4);
      $query = db_select("select posnr from ordrelinjer where ordre_id = '$id' order by posnr desc");
      if ($row = db_fetch_array($query)) {$posnr=$row[posnr]+1;}
      else {$posnr=1;}

      $query = db_select("select * from varer where id = '$vare_id'");
      if ($row = db_fetch_array($query)) {
        if (!$varenr){$varenr=trim($row['varenr']);}
        if (!$beskrivelse){$beskrivelse=addslashes(trim($row['beskrivelse']));}
        if (!$enhed){$enhed=trim($row['enhed']);}
        if (!$pris){$pris=$row[salgspris];}
        if (!$rabat){$rabat=$row[rabat];}
        $serienr=$row['serienr'];
      }
      if ($r2 = db_fetch_array(db_select("select box7 from grupper where art = 'VG' and kodenr = '$row[gruppe]'"))) {$momsfri = $r2['box7'];}
      if(!$antal){$antal=1;}
      if (!$rabat){$rabat=0;}
      if ($linjenr==0) {db_modify("insert into ordrelinjer (vare_id, ordre_id, posnr, varenr, beskrivelse, enhed, pris, serienr, momsfri) values ($vare_id, '$id', '$posnr', '$varenr', '$beskrivelse', '$enhed', '$pris', '$serienr', '$momsfri')");}
    }
  }

  if ($HTTP_POST_VARS['submit']) {
    $fokus=$HTTP_POST_VARS['fokus'];
    $submit = $HTTP_POST_VARS['submit'];
    $id = $HTTP_POST_VARS['id'];
    $kred_ord_id = $HTTP_POST_VARS['kred_ord_id'];
    $art = $HTTP_POST_VARS['art'];
    $konto_id = trim($HTTP_POST_VARS['konto_id']);
    $kontonr = trim($HTTP_POST_VARS['kontonr']);
    $firmanavn = trim($HTTP_POST_VARS['firmanavn']);
    $addr1 = trim($HTTP_POST_VARS['addr1']);
    $addr2 = trim($HTTP_POST_VARS['addr2']);
    $postnr = trim($HTTP_POST_VARS['postnr']);
    $bynavn = trim($HTTP_POST_VARS['bynavn']);
    $land = trim($HTTP_POST_VARS['land']);
    $kontakt = trim($HTTP_POST_VARS['kontakt']);
    $kundeordnr =  trim($HTTP_POST_VARS['kundeordnr']);
    $lev_navn = trim($HTTP_POST_VARS['lev_navn']);
    $lev_addr1 = trim($HTTP_POST_VARS['lev_addr1']);
    $lev_addr2 = trim($HTTP_POST_VARS['lev_addr2']);
    $lev_postnr = trim($HTTP_POST_VARS['lev_postnr']);
    $lev_bynavn = trim($HTTP_POST_VARS['lev_bynavn']);
    $lev_kontakt = trim($HTTP_POST_VARS['lev_kontakt']);
    $ordredate = usdate($HTTP_POST_VARS['ordredato']);
    $levdato = trim($HTTP_POST_VARS['levdato']);
    $fakturadato = trim($HTTP_POST_VARS['fakturadato']);
    $cvrnr = trim($HTTP_POST_VARS['cvrnr']);
    $ean = trim($HTTP_POST_VARS['ean']);
    $institution = trim($HTTP_POST_VARS['institution']);
    $betalingsbet = $HTTP_POST_VARS['betalingsbet'];
    $betalingsdage = $HTTP_POST_VARS['betalingsdage']*1;
    $lev_adr = trim($HTTP_POST_VARS['lev_adr']);
    $sum=$HTTP_POST_VARS['sum'];
    $linjeantal = $HTTP_POST_VARS['linjeantal'];
    $linje_id = $HTTP_POST_VARS['linje_id'];
    $kred_linje_id = $HTTP_POST_VARS['kred_linje_id'];
    $posnr = $HTTP_POST_VARS['posnr'];
    $status = $HTTP_POST_VARS['status'];
    $godkend = $HTTP_POST_VARS['godkend'];
    $omdan_t_fakt = $HTTP_POST_VARS['omdan_t_fakt'];
    $kreditnota = $HTTP_POST_VARS['kreditnota'];
    $ref = trim($HTTP_POST_VARS['ref']);
    $fakturanr = trim($HTTP_POST_VARS['fakturanr']);
    $momssats = trim($HTTP_POST_VARS['momssats']);
    $vare_id = $HTTP_POST_VARS['vare_id'];
    $serienr = $HTTP_POST_VARS['serienr'];
  
    if (strstr($submit,'Slet')) {
      db_modify("delete from ordrelinjer where ordre_id=$id");
      db_modify("delete from ordrer where id=$id");
      print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
    }

    transaktion("begin");

    for ($x=0; $x<=$linjeantal;$x++) {
      $y="posn".$x;
      $posnr_ny[$x]=trim($HTTP_POST_VARS[$y]);
      $y="vare".$x;
      $varenr[$x]=trim($HTTP_POST_VARS[$y]);
      $y="anta".$x;
      $antal[$x]=trim($HTTP_POST_VARS[$y]);
      if ($antal[$x]){
        $antal[$x]=usdecimal($antal[$x]);
        if ($art=='DK') {$antal[$x]=$antal[$x]*-1;}
      }
      $y="leve".$x;
      $leveres[$x]=trim($HTTP_POST_VARS[$y]);
      if ($leveres[$x]){
        $leveres[$x]=usdecimal($leveres[$x]);
        if ($art=='DK') {$leveres[$x]=$leveres[$x]*-1;}
      }
      $y="beskrivelse".$x;
      $beskrivelse[$x]=trim($HTTP_POST_VARS[$y]);
#      if (strlen($beskrivelse[$x])>60){$beskrivelse[$x]=substr($beskrivelse[$x],0,60);}
      $y="pris".$x;
      if (($x!=0)||($HTTP_POST_VARS[$y])||($HTTP_POST_VARS[$y]=='0')) {$pris[$x]=usdecimal($HTTP_POST_VARS[$y]);}
      $y="raba".$x;
      $rabat[$x]=usdecimal($HTTP_POST_VARS[$y]);
      if (($x>0)&&(!$rabat[$x])){$rabat=0;}
      $y="ialt".$x;
      $ialt[$x]=$HTTP_POST_VARS[$y];
      if (($godkend == "on")&&($status==0)) {$leveres[$x]=$antal[$x];}
    }
    if ($levdato) {$levdate=usdate($levdato);}
    if ($fakturadato) {$fakturadate=usdate($fakturadato);}
    
    if (($konto_id)&&(!$ref)&&($status<3)) {
      print "<BODY onLoad=\"javascript:alert('Vor ref. SKAL udfyldes')\">";
    }
    
    $bogfor=1;

    if (($godkend == "on")||($omdan_t_fakt == "on")) {$status=$status+1;}
    if ($status==1) {
      if ($levdato) {$levdate=usdate($levdato);}
      if (!$levdate) {
        print "<BODY onLoad=\"javascript:alert('Leveringsdato sat til dags dato.')\">";
        $levdate=date("Y-m-d");
      }
      elseif ($levdate<$ordredate) {
        print "<BODY onLoad=\"javascript:alert('Leveringsdato er f&oslash;r ordredato')\">";
        $status=0;
      }
    }

    if (strstr($submit, "Kred")) {
      $art='DK';
      $query = db_select("select id from ordrer where kred_ord_id = $id");
      if ($row = db_fetch_array($query)) {
        print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$row[id]\">";
        exit;
      }
      elseif ($kred_ord_id) {
        $id='';
        $status=0;
      }
      else {
        $kred_ord_id=$id;
        $id='';
        $status=0;
      }
    }
    elseif (strstr($submit, "Kopi")){
      $id='';
      $status=0;
    }
    elseif (!$art) {$art='DO';}

    if (strlen($ordredate)<6){$ordredate=date("Y-m-d");}
    
    if (($kontonr)&&(!$firmanavn)) {
      $query = db_select("select * from adresser where kontonr = '$kontonr'");
      if ($row = db_fetch_array($query)) {
        $konto_id=$row['id'];
        $firmanavn=$row['firmanavn'];
        $addr1=$row['addr1'];
        $addr2=$row['addr2'];
        $postnr=$row['postnr'];
        $bynavn=$row['bynavn'];
        $land=$row['land'];
        $kontakt=$row['kontakt'];
        $betalingsdage=$row['betalingsdage'];
        $betalingsbet=$row['betalingsbet'];
        $cvrnr=$row['cvrnr'];
        $notes=$row['notes'];
        $gruppe=$row['gruppe'];
      }
      if ($gruppe) {
        $query = db_select("select box2 from grupper where art='KM' and kodenr='$gruppe'");
        $row = db_fetch_array($query);
        $momssats=$row[box2];
      }
     }
    if ((!$id)&&($firmanavn)){
      $query = db_select("select ordrenr from ordrer where art='DO' or art='DK' order by ordrenr desc");
      if ($row = db_fetch_array($query)) {$ordrenr=$row[ordrenr]+1;}
      else {$ordrenr=1;}

      $qtext="insert into ordrer (ordrenr, konto_id, kontonr, kundeordnr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, lev_navn,  lev_addr1,  lev_addr2,  lev_postnr,  lev_bynavn, lev_kontakt, betalingsdage, betalingsbet, cvrnr, ean, institution, notes, art, ordredate, momssats, status, ref, lev_adr) values ($ordrenr, '$konto_id', '$kontonr', '$kundeordnr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$kontakt', '$lev_navn',  '$lev_addr1',  '$lev_addr2',  '$lev_postnr',  '$lev_bynavn', '$lev_kontakt', '$betalingsdage', '$betalingsbet', '$cvrnr', '$ean', '$institution', '$notes', '$art', '$ordredate', '$momssats', $status, '$ref', '$lev_adr')";
      db_modify($qtext);
      $query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc");
      if ($row = db_fetch_array($query)) {$id=$row[id];}
    }
    elseif(($firmanavn)&&($status<3)) {
      $sum=0;
      for($x=1; $x<=$linjeantal; $x++) {
        if (!$varenr[$x]) {$antal[$x]=0; $pris[$x]=0; $rabat[$x]=0;}
        if ((($antal[$x]>0)&&($leveres[$x]<0))||(($antal[$x]<0)&&($leveres[$x]>0))) {
          print "<BODY onLoad=\"javascript:alert('Der skal v&aelig;re samme fortegen i antal og l&eacute;ver! (Pos. $posnr_ny[$x] nulstillet)')\">";
          $leveres[$x]=0;
        }
        elseif ($vare_id[$x]) {
          if ($art=='DK') {
            if ($antal[$x]>0) {
              $antal[$x]=$antal[$x]*-1;
              print "<BODY onLoad=\"javascript:alert('Der kan ikke krediteres et negativt antal. Antal reguleret (Varenr: $varenr[$x])')\">";
            }           
            $query = db_select("select antal from ordrelinjer where id = $kred_linje_id[$x] and vare_id=$vare_id[$x]"); #Vare_id er med for ikke at taelle delvarer med v. samlevarer.
            $row = db_fetch_array($query);
            if ($antal[$x]+$row[antal]<0) {
            $antal[$x]=$row[antal]*-1;
            print "<BODY onLoad=\"javascript:alert('Der kan max krediteres $row[antal]. Antal reguleret (Varenr: $varenr[$x])')\">";
            }
          } #endif ($art=='DK')
          $tidl_lev[$x]=0;
          $query = db_select("select antal from batch_salg where linje_id = $linje_id[$x] and vare_id=$vare_id[$x]"); #Vare_id er med for ikke at taelle delvarer med v. samlevarer.
          while ($row = db_fetch_array($query)) {$tidl_lev[$x]=$tidl_lev[$x]+$row[antal];}
          if ((($tidl_lev[$x]<0)&&($antal[$x]>$tidl_lev[$x]))||(($tidl_lev[$x]>0)&&($antal[$x]<$tidl_lev[$x]))){
            $antal[$x]=$tidl_lev[$x];
            print "<BODY onLoad=\"javascript:alert('Der er allerede leveret $tidl_lev[$x]. Antal reguleret (Varenr: $varenr[$x])')\">";
          }  
          else {
            if (($tidl_lev[$x]<$antal[$x])&&($status>1)) {
              if ($omdan_t_fakt == "on") {print "<BODY onLoad=\"javascript:alert('Du kan ikke fakturere f&oslash;r alt er leveret')\">";}
              else {print "<BODY onLoad=\"javascript:alert('Faktura omdannet til ordre grundet &aelig;ndring af antal')\">";}
              $status=1;
            }
            $query = db_select("select antal from reservation where linje_id = $linje_id[$x]");
            while ($row = db_fetch_array($query)) {$reserveret[$x]=$reserveret[$x]+$row[antal];}
            if (($antal[$x]<$tidl_lev[$x]+$reserveret[$x])&&($antal[$x]>0)) {
              $diff=$tidl_lev[$x]+$reserveret[$x]-$antal[$x];
              while ($diff>0) {
                $query = db_select("select * from reservation where linje_id = $linje_id[$x] order by batch_kob_id desc");
                $row = db_fetch_array($query);
                if ($diff < $row[antal]) {
                  $temp = $row[antal] - $diff;
                  db_modify("update reservation set antal = $temp where linje_id=$linje_id[$x] and batch_kob_id=$row[batch_kob_id] and antal=$row[antal] and vare_id=$row[vare_id]");
                  $diff=0;                              
                }  
                elseif ($diff == $row[antal]) {
                  db_modify("delete from reservation where linje_id=$linje_id[$x] and batch_kob_id=$row[batch_kob_id] and antal=$row[antal] and vare_id=$row[vare_id]");
                  $diff=0;              
                } 
                elseif ($diff > $row[antal]) {
                  db_modify("delete from reservation where linje_id=$linje_id[$x] and batch_kob_id=$row[batch_kob_id] and antal=$row[antal] and vare_id=$row[vare_id]");
                  $diff=$diff - $row[antal];              
                } 
              } 
            }  
          } 
          $query = db_select("select antal from batch_salg where linje_id = $linje_id[$x]");
          while ($row = db_fetch_array($query)) {$modtaget[$x]=$modtaget[$x]+$row[antal];}
          if (($antal[$x]>$modtaget[$x])&&($modtaget[$x]<0)) {
            $antal[$x]=$modtaget[$x];
            print "<BODY onLoad=\"javascript:alert('Der er allerede modtaget $temp. Antal reguleret (Varenr: $varenr[$x])')\">";
          }  
        }
       if ($posnr_ny[$x]=='-') {
          if ($vare_id[$x]) {
            $query = db_select("select * from batch_kob where linje_id = $linje_id[$x] and ordre_id=$id and vare_id = $vare_id[$x]");
            if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Du kan ikke slette en varelinje n&aring;r der &eacute;r modtaget vare(r)')\">";}
            else 
            {
              $query = db_select("select * from batch_salg where linje_id = $linje_id[$x] and ordre_id=$id and vare_id = $vare_id[$x]");
              if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Du kan ikke slette en varelinje n&aring;r der &eacute;r leveret vare(r)')\">";}
              else 
              {
                db_modify("delete from ordrelinjer where id='$linje_id[$x]'");
                db_modify("delete from reservation where linje_id='$linje_id[$x]'");
              }
            }
          }
          else {db_modify("delete from ordrelinjer where id='$linje_id[$x]'");}
         }
        elseif ((!strstr($submit,"Kopi"))&&(!strstr($submit,"Udskriv")))
        {
          $posnr_ny[$x]=round($posnr_ny[$x],0);
          if (!$antal[$x]){$antal[$x]=1;}
          $sum=$sum+($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
          if (!$leveres[$x]){$leveres[$x]=0;}
          if ($posnr_ny[$x]>=1) {db_modify("update ordrelinjer set posnr='$posnr_ny[$x]' where id='$linje_id[$x]'");}
          else {print "<BODY onLoad=\"javascript:alert('Hint!  Du skal s&aelig;tte et - (minus) som pos nr for at slette en varelinje')\">";}
     
          db_modify("update ordrelinjer set  varenr='$varenr[$x]', beskrivelse='$beskrivelse[$x]', antal='$antal[$x]', leveres='$leveres[$x]', pris='$pris[$x]', rabat='$rabat[$x]' where id='$linje_id[$x]'");
        }
        if (strlen($fakturadate)>5){db_modify("update ordrer set fakturadate='$fakturadate' where id=$id");}
      }
      if ($posnr_ny[0]) {
        if ($varenr[0]) {
          $tmp=strtoupper($varenr[0]);
          $query = db_select("SELECT * FROM varer WHERE upper(varenr) = '$tmp'");
          if ($row = db_fetch_array($query)) {
            $varenr[0]=$row['varenr'];
            $vare_id[0]=$row[id];
            if (!$beskrivelse[0]){$beskrivelse[0]=$row['beskrivelse'];}
            if (!$enhed[0]){$enhed[0]=$row['enhed'];}
            if (!$pris[0]){$pris[0]=$row[salgspris];}
            if (!$pris[0]){$pris[0]=0;}
            if (!$rabat[0]){$rabat[0]=$row[rabat];}
            if(!$antal_ny[0]){$antal_ny[0]=1;}
            if (!$rabat[0]){$rabat[0]=0;}
            $serienr=$row['serienr'];
            if ($r2 = db_fetch_array(db_select("select box7 from grupper where art = 'VG' and kodenr = '$row[gruppe]'"))) {$momsfri[0] = $r2['box7'];}
            db_modify("insert into ordrelinjer (vare_id, ordre_id, posnr, varenr, beskrivelse, enhed, antal, pris, rabat, serienr, momsfri) values ($vare_id[0], '$id', '$posnr_ny[0]', '$varenr[0]', '$beskrivelse[0]', '$enhed[0]', '$antal_ny[0]', '$pris[0]', '$rabat[0]', '$serienr[0]', '$momsfri[0]')");
          }
        }
        elseif ($beskrivelse[0]) {db_modify("insert into ordrelinjer (ordre_id, posnr, beskrivelse) values ('$id', '$posnr_ny[0]', '$beskrivelse[0]')");}
      }
      $query = db_select("select tidspkt, hvem from ordrer where status < 3 and id = $id and hvem != '$brugernavn'");
      if ($row = db_fetch_array($query)) {
        if ($tidspkt-($row['tidspkt'])<3600) {
          print "<BODY onLoad=\"javascript:alert('Orderen er overtaget af $row[hvem]')\">";
          print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
        }
      }
      else  {
        if (strlen($levdate)<6){$opdat="update ordrer set kontonr='$kontonr', kundeordnr='$kundeordnr', firmanavn='$firmanavn', addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', kontakt='$kontakt', lev_navn='$lev_navn',  lev_addr1='$lev_addr1',  lev_addr2='$lev_addr2', lev_postnr='$lev_postnr', lev_bynavn='$lev_bynavn', lev_kontakt='$lev_kontakt', betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr', ean='$ean', institution='$institution', notes='$notes', ordredate='$ordredate', status=$status, ref='$ref', fakturanr='$fakturanr', lev_adr='$lev_adr', hvem = '$brugernavn', tidspkt='$tidspkt' where id=$id";}
        else {$opdat="update ordrer set kontonr='$kontonr', kundeordnr='$kundeordnr', firmanavn='$firmanavn', addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', kontakt='$kontakt', lev_navn='$lev_navn',  lev_addr1='$lev_addr1',  lev_addr2='$lev_addr2', lev_postnr='$lev_postnr', lev_bynavn='$lev_bynavn', lev_kontakt='$lev_kontakt', betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr', ean='$ean', institution='$institution', notes='$notes', ordredate='$ordredate', levdate='$levdate', status=$status, ref='$ref', fakturanr='$fakturanr', lev_adr='$lev_adr' ,hvem = '$brugernavn', tidspkt='$tidspkt' where id=$id";}
        db_modify($opdat);
      }
    }
    
    if ((strstr($submit,'Kopi'))||(strstr($submit,'Kred')))  {
      if ((strstr($submit,'Kred'))&&($kred_ord_id)) {db_modify("update ordrer set kred_ord_id='$kred_ord_id' where id='$id'");}
      for($x=1; $x<=$linjeantal; $x++) {
        if (!$vare_id[$x]) {
          $query = db_select("select id from varer where varenr = '$varenr[$x]'");
          if ($row = db_fetch_array($query)) {$vare_id[$x]=$row[id];}
        }
        if ($vare_id[$x]){
          if (strstr($submit,'Kopi')) {$tmp=$antal[$x];}
          else {$tmp=$antal[$x]*-1;}
          if ($r1 =  db_fetch_array(db_select("select gruppe from varer where id = '$vare_id[$x]'"))) {
            if ($r2 = db_fetch_array(db_select("select box7 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'"))) {$momsfri[$x] = $r2['box7'];}
          }  
          db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, antal, pris, rabat, lev_varenr, serienr, kred_linje_id, momsfri) values ('$id', '$posnr_ny[$x]', '$varenr[$x]', '$vare_id[$x]', '$beskrivelse[$x]', '$enhed[$x]', $tmp, '$pris[$x]', '$rabat[$x]', '$lev_varenr[$x]', '$serienr[$x]', '$linje_id[$x]', '$momsfri[$x]')");
        }
        else {db_modify("insert into ordrelinjer (ordre_id, posnr, beskrivelse) values ('$id', '$posnr_ny[$x]', '$beskrivelse[$x]')");}
      }
    }
  transaktion("commit");
  }

##########################UDSKRIFT#################################

  if (strstr($submit,"Udskriv")) {
    if ($status>=3)       {$temp="aktura";  $formular=4; $ps_fil="formularprint.php";}
    elseif($status>=1) {
      $query = db_select("select lev_nr from batch_salg where ordre_id=$id and lev_nr=1");
      if ($row = db_fetch_array($query)) {$formular=3; $ps_fil="udskriftsvalg.php";}
      else {$temp="rdrebek";  $formular=2; $ps_fil="formularprint.php";}
     }
#    elseif($status==2) {$temp="seddel";$ps_fil="../formularer/$db_id/ps_flgs.php";}
    else {$temp="ilbud"; $formular=1;    $ps_fil="formularprint.php";}

#    if ((!file_exists($ps_fil))&&($ps_fil!="udskriftsvalg.php"))  {
#      if (!file_exists("../formularer/$db_id")) {mkdir("../formularer/$db_id",0777);}
#      $kildefil=str_replace("/$db_id", "", $ps_fil);
#      copy($kildefil, $ps_fil);
#    }
    print "<BODY onLoad=\"JavaScript:window.open('$ps_fil?id=$id&formular=$formular' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
  }  

##########################OPSLAG################################

  if (strstr($submit,'Opslag')) {
    if ((strstr($fokus,'kontonr'))&&(!$kontonr)) {kontoopslag($sort, $fokus, $id);}
    if ((strstr($fokus,'vare'))&&($art!='DK')) {vareopslag($sort, $fokus, $id, $vis_kost, $ref);}
    if (strstr($fokus,'kontakt')){ansatopslag($sort, $fokus, $id, $vis);}
  }

##########################FAKTURER################################

  if ((strstr($submit,'Faktur'))&&($bogfor!=0)) {
    $query = db_select("select * from ordrelinjer where ordre_id = '$id'");
    if (!$row = db_fetch_array($query)) {Print "Du kan ikke fakturere uden ordrelinjer";}
    else {print "<meta http-equiv=\"refresh\" content=\"0;URL=bogfor.php?id=$id\">";}
    #  else {bogfor($id);}
  }

############################LEVER################################

  if ((strstr($submit,'Lev'))&&($bogfor!=0)) {
    $query = db_select("select * from ordrelinjer where ordre_id = '$id'");
    if (!$row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Du kan ikke levere uden ordrelinjer')\">";}
    else {
    print "<meta http-equiv=\"refresh\" content=\"0;URL=levering.php?id=$id\">";}
#    echo "levering $id<br>";
#    exit;
    #  else {bogfor($id);}
  }
  print "<meta http-equiv=\"refresh\" content=\"3600;URL=../includes/luk.php\">";
  ordreside($id, $regnskab);


######################################################################################################################################

function ordreside($id, $regnskab)
{

  global $bgcolor;
  global $bgcolor5;
  global $font;
  global $bogfor;
  global $brugernavn;

  print "<form name=ordre action=ordre.php method=post>";
  if ($id) {
    $query = db_select("select * from ordrer where id = '$id'");
    $row = db_fetch_array($query);
    $konto_id = $row[konto_id];
    $kontonr = stripslashes($row[kontonr]);
    $firmanavn = stripslashes($row[firmanavn]);
    $addr1 = stripslashes($row[addr1]);
    $addr2 = stripslashes($row[addr2]);
    $postnr = stripslashes($row[postnr]);
    $bynavn = stripslashes($row[bynavn]);
    $land = stripslashes($row[land]);
    $kontakt = stripslashes($row[kontakt]);
    $kundeordnr = stripslashes($row[kundeordnr]);
    $lev_navn = stripslashes($row[lev_navn]);
    $lev_addr1 = stripslashes($row[lev_addr1]);
    $lev_addr2 = stripslashes($row[lev_addr2]);
    $lev_postnr = stripslashes($row[lev_postnr]);
    $lev_bynavn = stripslashes($row[lev_bynavn]);
    $lev_kontakt = stripslashes($row[lev_kontakt]);
    $levdato = $row[levdato];
    $cvrnr = $row[cvrnr];
    $ean = stripslashes($row[ean]);
    $institution = stripslashes($row[institution]);
    $betalingsbet = trim($row[betalingsbet]);
    $betalingsdage = $row[betalingsdage];
    $momssats = $momssats;
    $ref = trim(stripslashes($row[ref]));
    $fakturanr = stripslashes($row[fakturanr]);
    $lev_adr = stripslashes($row[lev_adr]);
    $ordrenr=$row[ordrenr];
    $kred_ord_id=$row[kred_ord_id];
    if($row['ordredate']) {$ordredato=dkdato($row['ordredate']);}
    else {$ordredato=date("d-m-y");}
    if ($row['levdate']) {$levdato=dkdato($row['levdate']);}
    if ($row['fakturadate']) {$fakturadato=dkdato($row['fakturadate']);}
    $momssats=$row[momssats];
    $status=$row[status];
    if (!$status){$status=0;}
    $kontonr=$row[kontonr];
    $art=$row['art'];
    $x=0;
    $krediteret='';
    $query = db_select("select id from ordrer where kred_ord_id = '$id'");
    while ($row2 = db_fetch_array($query)) {
      $x++;
      if ($x>1) {$krediteret=$krediteret.", ";}
      $krediteret=$krediteret."<a href=ordre.php?id=$row2[id]>$row2[id]</a>";
    }  
  }
  
  if ((strstr($submit,'Kred'))||($art=='DK')) {sidehoved($id, "ordreliste.php", "", "", "Kunde kreditnota $ordrenr (kreditering af ordre nr: <a href=ordre.php?id=$kred_ord_id>$kred_ord_id</a>)");}
  elseif ($krediteret) {sidehoved($id, "ordreliste.php", "", "", "Kundeordre $ordrenr ( krediteret p&aring; KN nr: $krediteret )");}
  else {
    if ($status<1) {$temp='Tilbud';}
    elseif ($status<2) {$temp='Ordre';}
    else {$temp='Faktura';}
    sidehoved($id, "ordreliste.php", "", "", "Kundeordre $ordrenr - $temp");
  }

  if (!$status){$status=0;}
  print "<input type=hidden name=status value=$status>";
  print "<input type=hidden name=id value=$id>";
  print "<input type=hidden name=art value=$art>";
  print "<input type=hidden name=kred_ord_id value=$kred_ord_id>";

  if ($status>=3) {
    print "<input type=hidden name=konto_id value=$konto_id>";
    print "<input type=hidden name=kontonr value=\"$kontonr\">";
    print "<input type=hidden name=firmanavn value=\"$firmanavn\">";
    print "<input type=hidden name=addr1 value=\"$addr1\">";
    print "<input type=hidden name=addr2 value=\"$addr2\">";
    print "<input type=hidden name=postnr value=\"$postnr\">";
    print "<input type=hidden name=bynavn value=\"$bynavn\">";
    print "<input type=hidden name=land value=\"$land\">";
    print "<input type=hidden name=kontakt value=\"$kontakt\">";
    print "<input type=hidden name=kundeordnr value=\"$kundeordnr\">";
    print "<input type=hidden name=lev_navn value=\"$lev_navn\">";
    print "<input type=hidden name=lev_addr1 value=\"$lev_addr1\">";
    print "<input type=hidden name=lev_addr2 value=\"$lev_addr2\">";
    print "<input type=hidden name=lev_postnr value=\"$lev_postnr\">";
    print "<input type=hidden name=lev_bynavn value=\"$lev_bynavn\">";
    print "<input type=hidden name=lev_kontakt value=\"$lev_kontakt\">";
    print "<input type=hidden name=levdato value=\"$levdato\">";
    print "<input type=hidden name=cvrnr value=\"$cvrnr\">";
    print "<input type=hidden name=ean value=\"$ean\">";
    print "<input type=hidden name=institution value=\"$institution\">";
    print "<input type=hidden name=betalingsbet value=\"$betalingsbet\">";
    print "<input type=hidden name=betalingsdage value=\"$betalingsdage\">";
    print "<input type=hidden name=momssats value=\"$momssats\">";
    print "<input type=hidden name=ref value=\"$ref\">";
    print "<input type=hidden name=fakturanr value=\"$fakturanr\">";
    print "<input type=hidden name=lev_adr value=\"$lev_adr\">";
  
    print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" valign = \"top\"><tbody>";
    $ordre_id=$id;
    print "<tr><td width=33%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
    print "<tr><td width=100>$font<small><b>Kontnr.</td><td width=100>$font<small>$kontonr</td></tr>\n";
    print "<tr><td>$font<small><b>Firmanavn</td><td>$font<small>$firmanavn</td></tr>\n";
    print "<tr><td>$font<small><b>Adresse</td><td>$font<small>$addr1</td></tr>\n";
    print "<tr><td>$font<small></td><td>$font<small>$addr2</td></tr>\n";
    print "<tr><td>$font<small><b>Postnr, by</td><td>$font<small>$postnr $bynavn</td></tr>\n";
    print "<tr><td>$font<small><b>Land</td><td>$font<small>$land</td></tr>\n";
    print "<tr><td>$font<small><b>Att.:</td><td>$font<small>$kontakt</td></tr>\n";
    print "<tr><td>$font<small><b>Ordre nr.</td><td>$font<small>$kundeordnr</td></tr>\n";
    print "</tbody></table></td>";
    print "<td width=33%><table cellpadding=0 cellspacing=0 border=0 width=100%>";
#    if ($art=='DK') {
#      print "<tr><td>$font<small><b>Kreditnota</td><td>$font<small>$kn_ord_nr</td></tr>\n";
#    }
    print "<tr><td>$font<small><b>CVR.nr</td><td>$font<small>$cvrnr</td></tr>\n";
    print "<tr><td>$font<small><b>EAN.nr</td><td>$font<small>$ean</td></tr>\n";
    print "<tr><td>$font<small><b>Institution</td><td>$font<small>$institution</td></tr>\n";
    print "<tr><td width=100>$font<small><b>Ordredato</td><td width=100>$font<small>$ordredato</td></tr>\n";
    print "<tr><td>$font<small><b>Lev. dato</td><td>$font<small>$levdato</td></tr>\n";
    print "<tr><td>$font<small><b>Fakturadato</td><td>$font<small>$fakturadato</td></tr>\n";
    print "<tr><td>$font<small><b>Betaling</td><td>$font<small>$betalingsbet&nbsp;+&nbsp;$betalingsdage</td>";
    print "<tr><td>$font<small><b>Vor ref.</td><td>$font<small>$ref</td></tr>\n";
    print "<tr><td>$font<small><b>Fakturanr</td><td>$font<small>$fakturanr</td></tr>\n";
    print "</tbody></table></td>";
    print "<td width=33%><table cellpadding=0 cellspacing=0 border = 0 width=240>";
    print "<tr><td>$font<small><b>Leveringsadresse.</td></tr>\n";
#    $lev_adr=str_replace(chr(10),"<br>",$lev_adr');
    print "<tr><td>$font<small>Firmanavn</td><td colspan=2>$font<small>$lev_navn</td></tr>\n";
    print "<tr><td>$font<small>Adresse</td><td colspan=2>$font<small>$lev_addr1</td></tr>\n";
    print "<tr><td>$font<small></td><td colspan=2>$font<small>$lev_addr2</td></tr>\n";
    print "<tr><td>$font<small>Postnr, By</td><td>$font<small>$lev_postnr $lev_bynavn</td></tr>\n";
    print "<tr><td>$font<small>Att.:</td><td colspan=2>$font<small>$lev_kontakt</td></tr>\n";
    print "</td></tr></tbody></table></td>";
    print "</td></tr><tr><td align=center colspan=3><table cellpadding=0 cellspacing=0 border=1 width=100%><tbody>";
    print "<tr><td colspan=7></td></tr><tr>";
    print "<td align=center>$font<small><b>pos</td><td align=center>$font<small><b>varenr</td><td align=center>$font<small><b>ant.</td><td align=center>$font<small><b>enhed</td><td align=center>$font<small><b>beskrivelse</td><td align=center>$font<small><b>pris</td><td align=center>$font<small><b>%</td><td align=center>$font<small><b>ialt</td><td></td>";
    print "</tr>\n";
#    print "<tr><td colspan=9><hr></td></tr>\n";
    $x=0;
    if (!$ordre_id){$ordre_id=0;}
    $query = db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by posnr");
    while ($row = db_fetch_array($query))  {
      if ($row[posnr]>0) {
        $x++;
        $linje_id[$x]=$row['id'];
        $vare_id[$x]=$row['vare_id'];
        $posnr[$x]=$row['posnr'];
        $varenr[$x]=stripslashes(htmlentities($row['varenr']));
        $lev_varenr[$x]=stripslashes(htmlentities($row['lev_varenr']));
        $beskrivelse[$x]=stripslashes(htmlentities($row['beskrivelse']));
        $enhed[$x]=stripslashes(htmlentities($row['enhed']));
        $pris[$x]=$row['pris'];
        $rabat[$x]=$row['rabat'];
        $antal[$x]=$row['antal'];
        $momsfri[$x]=$row['momsfri'];
        $serienr[$x]=stripslashes(htmlentities($row['serienr']));
      }
    }  
    $linjeantal=$x;
    print "<input type=hidden name=linjeantal value=$x>";
    $totalrest=0;
    $sum=0;
    for ($x=1; $x<=$linjeantal; $x++) {
      if (!$vare_id[$x]) {
        $query = db_select("select id from varer where varenr = '$varenr[$x]'");
        if ($row = db_fetch_array($query)) {$vare_id[$x]=$row[id];}
      }
      if (($varenr[$x])&&($vare_id[$x])) {
        $query = db_select("select provisionsfri from varer where id = '$vare_id[$x]' and provisionsfri='on'");
        if ($row = db_fetch_array($query)) {$provisionsfri[$x]=$row['provisionsfri'];}
        $query = db_select("select * from batch_salg where linje_id = '$linje_id[$x]'");
        while ($row = db_fetch_array($query)) {
          $r2 = db_fetch_array(db_select("select ordre_id, pris, fakturadate, linje_id from batch_kob where id = $row[batch_kob_id]"));
          if ($r2[ordre_id]) {
            $kobs_ordre_id[$x]=$r2[ordre_id];
            if ($r2['fakturadate']<2000-01-01) {$r2=db_fetch_array(db_select("select pris from ordrelinjer where id = $r2[linje_id]"));}  
            $kostpris[$x]=$kostpris[$x]+$r2[pris]*$row[antal];
          }
          else {
            $r2 = db_fetch_array(db_select("select kostpris from varer where id = '$vare_id[$x]'"));
            $kostpris[$x]=$kostpris[$x]+$r2['kostpris'];
          }
          if ($kobs_ordre__id[$x]) {
            $query = db_select("select ordrenr from ordrer where id = $kobs_ordre_id[$x]");
            $row = db_fetch_array($query);
            $kobs_ordre_nr[$x]=$row[ordrenr];
          }
        }
        $ialt=($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
        if ($provisionsfri[$x]) {$kostpris[$x]=$ialt;}

        $db[$x]=$ialt-$kostpris[$x];
        if ($ialt!=0) {
          $dg[$x]=$db[$x]*100/$ialt;
          $dk_dg[$x]=dkdecimal($dg[$x]);
        }
        $dk_db[$x]=dkdecimal($db[$x]);
        $dk_kostpris[$x]=dkdecimal($kostpris[$x]);
 #       $kostpris=$kostpris+($pris[$x]-$db[$x]);
         $sum=$sum+$ialt;
         if ($momsfri[$x]!='on') {$momssum=$momssum+$ialt;}
#        $ialt=dkdecimal($ialt);
        $dkpris=dkdecimal($pris[$x]);
        $dkrabat=dkdecimal($rabat[$x]);
        if ($antal[$x]) {
          if ($art=='DK') {$dkantal[$x]=dkdecimal($antal[$x]*-1);}
          else {$dkantal[$x]=dkdecimal($antal[$x]);}
          if (substr($dkantal[$x],-1)=='0'){$dkantal[$x]=substr($dkantal[$x],0,-1);}
          if (substr($dkantal[$x],-1)=='0'){$dkantal[$x]=substr($dkantal[$x],0,-2);}
        }
      }
      else {$antal[$x]=''; $dkpris=''; $dkrabat=''; $ialt='';}
      print "<tr bgcolor=\"$linjebg\">";
      print "<input type=hidden name=linje_id[$x] value=$linje_id[$x]>";
      print "<input type=hidden name=posn$x value=$posnr[$x]><td align=right>$font<small>$posnr[$x]</td>";
      print "<input type=hidden name=vare$x value=\"$varenr[$x]\"><td>$font<small>$varenr[$x]</td>";
      print "<input type=hidden name=anta$x value=$dkantal[$x]><td align=right>$font<small>$dkantal[$x]</td>";
      print "<td align=right>$font<small>$enhed[$x]</td>";
      print "<input type=hidden name=beskrivelse$x value=\"$beskrivelse[$x]\"><td>$font<small>$beskrivelse[$x]</td>";
      print "<input type=hidden name=pris$x value=$dkpris><td align=right>$font<small>$dkpris</td>";
      print "<input type=hidden name=raba$x value=$dkrabat><td align=right>$font<small>$dkrabat</td>";
      print "<input type=hidden name=serienr[$x] value=\"$serienr[$x]\"";
      print "<input type=hidden name=vare_id[$x] value=$vare_id[$x]>";
      print "<input type=hidden name=lev_varenr[$x] value=\"$lev_varenr[$x]\">";
#      $db[$x]=$db[$x]*$antal[$x];
      $dbsum=$dbsum+$db[$x]; 
#      $dk_db[$x]=dkdecimal($db[$x]);
      if ($ialt) {
        if ($art=='DK') {$ialt=$ialt*-1;}
        print "<td align=right>$font<span title= 'kostpris $dk_kostpris[$x] * db: $dk_db[$x] * dg: $dk_dg[$x]%'><small>".dkdecimal($ialt)."</td>";
      }
      else {print "<td><br></td>";}
   #   print "<td align=right>$font<small>$solgt[$x]</td>";
      if  ($kobs_ordre_id[$x]) {print "<td align=right onClick=\"javascript:k_ordre=window.open('../kreditor/ordre.php?id=$kobs_ordre_id[$x]','ordre' ,'left=10,top=10,width=800,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no');k_ordre.focus();\"><span title= 'K&oslash;bsordre '><img alt=\"K&oslash;bsordre\" src=../ikoner/opslag.png></td>";}
      else {print "<td><br></td>";}
      if ($serienr[$x]) {print "<td onClick=\"serienummer($linje_id[$x])\" align=right>$font<small><span title= 'Serienumre '><img alt=\"Serienummer\" src=../ikoner/serienr.png></td>";}
      else {print "<td><br></td>";}
#   print "</tr>\n";
    }
    if ($art=='DK') {$sum=$sum*-1;}
    $moms=round($momssum/100*$momssats,2);
    $ialt=$sum+$moms;
#    $sum=dkdecimal($sum);
#    $moms=dkdecimal($moms);
    print "<tr><td colspan=9><br></td></tr>\n";
    print "<tr><td colspan=7><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" width=100%><tbody>";
    print "<tr bgcolor=\"$bgcolor5\">";
    print "<td align=center>$font<small>Ordresum</td><td align=center>$font<small>".dkdecimal($sum)."</td>";
    $db=$dbsum;
    print "<td align=center>$font<small>D&aelig;kningsbidrag:&nbsp;".dkdecimal($db)."</td>";
    if ($sum) {$dg_sum=($dbsum*100/$sum);}
    else {$dg_sum=dkdecimal(0);}
    print "<td align=center>$font<small>D&aelig;kningsgrad;&nbsp;".dkdecimal($dg_sum)."%</td>";
    print "<td align=center>$font<small>Moms</td><td align=center>$font<small>".dkdecimal($moms)."</td>";
    print "<td align=center>$font<small>I alt</td><td align=right>$font<small>".dkdecimal($ialt)."</td>";
#    db_modify("update ordrer set kostpris=$sum-$dbsum where id=$id");
    print "</tbody></table></td></tr>\n";
    print "<tr><td align=center colspan=8>";
    print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody><tr>";
     if ($art!='DK') {print "<td align=center><input type=submit value=\"&nbsp;Kopier&nbsp;\" name=\"submit\"></td>";}
    print "<td align=center><input type=submit value=\"&nbsp;Udskriv&nbsp;\" name=\"submit\"></td>";
    if (($art!='DK')&&(!$krediteret)) {print "<td align=center><input type=submit value=\"Krediter\" name=\"submit\"></td>";}
  }
  else { ############################# ordren er ikke faktureret #################################
    print "<table cellpadding=\"1\" cellspacing=\"5\" border=\"1\"  valign = \"top\"><tbody>";
    if (!$fakturadato) {$fakturadato=dkdato(date("Y-m-d"));}
    $ordre_id=$id;
    print "<tr><td width=33%><table cellpadding=0 cellspacing=0 border=0>";
    print "<tr><td witdh=100>$font<small>Kontonr.</td><td colspan=2>$font<small>";
    if (trim($kontonr)) {print "<input readonly=readonly size=25 name=kontonr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontonr\"></td></tr>\n";}
    else {print "<input type=text size=25 name=kontonr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontonr\"></td></tr>\n";}
    print "<tr><td>$font<small>Firmanavn</td><td colspan=2>$font<small><input type=text size=25 name=firmanavn value=\"$firmanavn\"></td></tr>\n";
    print "<tr><td>$font<small>Adresse</td><td colspan=2>$font<small><input type=text size=25 name=addr1 value=\"$addr1\"></td></tr>\n";
    print "<tr><td>$font<small></td><td colspan=2>$font<small><input type=text size=25 name=addr2 value=\"$addr2\"></td></tr>\n";
    print "<tr><td>$font<small>Postnr, By</td><td>$font<small><input type=text size=4 name=postnr value=\"$postnr\"></td><td><input type=text size=19 name=bynavn value=\"$bynavn\"></td></tr>\n";
    print "<tr><td>$font<small>Land</td><td colspan=2>$font<small><input type=text size=25 name=land value=\"$land\"></td></tr>\n";
    print "<tr><td>$font<small>Att.:</td><td colspan=2>$font<small><input type=text size=25 name=kontakt onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kontakt\"></td></tr>\n";
    print "<tr><td>$font<small>Kunde_ordnr</td><td colspan=2>$font<small><input type=text size=25 name=kundeordnr onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$kundeordnr\"></td></tr>\n";
#    print "<tr><td>$font<small>Att.:</td><td colspan=2>$font<small><input type=text size=25 name=kontakt value=\"$kontakt\"></td></tr>\n";
    print "</tbody></table></td>";
    print "<td width=33%><table cellpadding=0 cellspacing=0 border=0  width=250>";
    print "<tr><td>$font<small>CVR.nr</td><td colspan=2>$font<small><input type=text size=10 name=cvrnr value=\"$cvrnr\"></td></tr>\n";
    print "<tr><td>$font<small>EAN.nr</td><td>$font<small><input type=text size=10 name=ean value=\"$ean\"></td></tr>\n";
    print "<tr><td>$font<small>Institution</td><td>$font<small><input type=text size=10 name=institution value=\"$institution\"></td></tr>\n";
    print "<tr><td width=20%>$font<small>Ordredato</td><td colspan=2>$font<small><input type=text size=10 name=ordredato value=\"$ordredato\"></td></tr>\n";
    print "<tr><td>$font<small>Lev. dato</td><td colspan=2>$font<small><input type=text size=10 name=levdato value=\"$levdato\"></td></tr>\n";
    if ($status>=2) {print "<tr><td>$font<small>Fakt. dato</td><td colspan=2>$font<small><input type=text size=10 name=fakturadato value=\"$fakturadato\"></td></tr>\n";}
    print "<tr><td>$font<small>Betaling</td>";
    print "<td><SELECT NAME=betalingsbet>";
    print "<option>$betalingsbet</option>";
    if ($betalingsbet!='Kontant')  {print "<option>Kontant</option>"; }
    if ($betalingsbet!='Efterkrav')  {print "<option>Efterkrav</option>"; }
    if ($betalingsbet!='Netto'){print "<option>Netto</option>"; }
    if ($betalingsbet!='Lb. md.'){print "<option>Lb. md.</option>";}
    if (($betalingsbet=='Kontant')||($betalingsbet=='Efterkrav')) {$betalingsdage='';}
    elseif (!$betalingsdage) {$betalingsdage='Nul';}
    if ($betalingsdage)  {
      if ($betalingsdage=='Nul') {$betalingsdage=0;}
      print "</SELECT>&nbsp;+<input type=text size=2 style=text-align:right  name=betalingsdage value=\"$betalingsdage\"></td>";
    }
    print "</tr>";
    print "<tr><td>$font<small>Vor ref.</td>";
    if (!$ref) {
      $row = db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'"));
      if ($row[ansat_id]) {
        $row = db_fetch_array(db_select("select navn from ansatte where id = $row[ansat_id]"));
        if ($row[navn]) {$ref=$row['navn'];}
      }
    }    
    print "<td><SELECT NAME=ref value=\"$ref\">";
    print "<option>$ref</option>";
    $query = db_select("select id from adresser where art = 'S'");
    if ($row = db_fetch_array($query)) {
     $query = db_select("select navn from ansatte where konto_id = $row[id] order by navn");
     while ($row = db_fetch_array($query)) {print "<option> $row[navn]</option>";}
    }
    print "</SELECT>";
    if ($status==0) {print "<tr><td colspan=2 witdh=200>$font<small>Omdan til ordre<input type=checkbox name=godkend></td></tr>\n";}
    if ($status==1) {print "<tr><td colspan=2 witdh=200>$font<small>Omdan til faktura<input type=checkbox name=omdan_t_fakt></td></tr>\n";}
    print "</tbody></table></td>";
    print "<td width=33%><table cellpadding=0 cellspacing=0 border=0 width=250>";
    print "<tr><td colspan=2 align=center><b>$font<small>Leveringsadresse.</td></tr>\n";
    print "<tr><td colspan=2 align=center><hr></td></tr>\n";
    print "<tr><td>$font<small>Firmanavn</td><td colspan=2>$font<small><input type=text size=25 name=lev_navn value=\"$lev_navn\"></td></tr>\n";
    print "<tr><td>$font<small>Adresse</td><td colspan=2>$font<small><input type=text size=25 name=lev_addr1 value=\"$lev_addr1\"></td></tr>\n";
    print "<tr><td>$font<small></td><td colspan=2>$font<small><input type=text size=25 name=lev_addr2 value=\"$lev_addr2\"></td></tr>\n";
    print "<tr><td>$font<small>Postnr, By</td><td>$font<small><input type=text size=4 name=lev_postnr value=\"$lev_postnr\"><input type=text size=19 name=lev_bynavn value=\"$lev_bynavn\"></td></tr>\n";
    print "<tr><td>$font<small>Att.:</td><td colspan=2>$font<small><input type=text size=25 name=lev_kontakt value=\"$lev_kontakt\"></td></tr>\n";
    #   print "<tr><td><textarea style=\"font-family: helvetica,arial,sans-serif;\" name=lev_adr rows=5 cols=35>$lev_adr</textarea></td></tr>\n";
    print "</td></tr></tbody></table></td>";
    print "</td></tr><tr><td align=center colspan=3><table cellpadding=0 cellspacing=0><tbody>";
  $query = db_select("select notes from adresser where kontonr = '$kontonr'");
  if ($row2 = db_fetch_array($query))
 {print "<tr><td colspan=9 witdh=100% align=center>$font <span style='color: rgb(255, 0, 0);'>$row2[notes]</td></tr><tr><td colspan=9 witdh=100%><hr></td></tr>\n";}
    if ($kontonr) {
      print "<tr>";
      if ($status==1) {print "<td align=center>$font<small>pos</td><td align=center>$font<small>varenr</td><td align=center>$font<small>antal/enhed</td><td align=center>$font<small>beskrivelse</td><td align=center>$font<small>pris</td><td align=center>$font<small>%</td><td align=center>$font<small>ialt</td><td colspan=2 align=center>$font<small>lev&egrave;r</td><td></td>";} #<td align=center>$font<small>serienr</td>";
      else {print "<td align=center>$font<small>pos</td><td align=center>$font<small>varenr</td><td align=center>$font<small>antal/enhed</td><td align=center>$font<small>beskrivelse</td><td align=center>$font<small>pris</td><td align=center>$font<small>%</td><td align=center>$font<small>ialt</td>";}
      print "</tr>\n";
    }
    if (!$status){$status=0;}
    print "<input type=hidden name=status value=$status>";
    print "<input type=hidden name=id value=$id>";

    $x=0;
    if (!$ordre_id){$ordre_id=0;}

    $kostpris=0;
    $query = db_select("select * from ordrelinjer where ordre_id = '$ordre_id' order by posnr");
    while ($row = db_fetch_array($query)) {
      if ($row[posnr]>0) {
        $x++;
        $linje_id[$x]=$row['id'];
        $kred_linje_id[$x]=$row['kred_linje_id'];
        $posnr[$x]=$row['posnr'];
        $varenr[$x]=stripslashes(htmlentities(trim($row['varenr'])));
        $beskrivelse[$x]=stripslashes(htmlentities(trim($row['beskrivelse'])));
        $enhed[$x]=stripslashes(htmlentities(trim($row['enhed'])));
        $pris[$x]=$row['pris'];
        $rabat[$x]=$row['rabat'];
        $antal[$x]=$row['antal'];
        $leveres[$x]=$row['leveres'];
        $vare_id[$x]=$row['vare_id'];
        $momsfri[$x]=$row['momsfri'];
        $serienr[$x]=stripslashes(htmlentities(trim($row['serienr'])));
        if ($vare_id[$x]) {
          $q2 = db_select("select kostpris, provisionsfri from varer where id = '$vare_id[$x]'");
          $row2 = db_fetch_array($q2);
          if ($row2[provisionsfri]) {
            $kostpris=$kostpris+$pris[$x];
            $db[$x]=0;
          }
          else {
            $kostpris=$kostpris+$row2[kostpris];
            $db[$x]=$pris[$x]-$row2[kostpris];
          } 
          if ($pris[$x]!=0) {$dg[$x]=$db[$x]*100/$pris[$x];}
          $dk_db[$x]=dkdecimal($db[$x]);
          $dk_dg[$x]=dkdecimal($dg[$x]);
        }
        if (($art=='DK')&&($antal[$x]<0)){$bogfor==0;}
      }
    }
    $linjeantal=$x;
    $sum=0;
    for ($x=1; $x<=$linjeantal; $x++) {
      if ($varenr[$x])  {
        $ialt=($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
        $sum=$sum+$ialt;
        if ($momsfri[$x]!='on') {$momssum=$momssum+$ialt;}
#        $ialt=dkdecimal($ialt);
        $dkpris=dkdecimal($pris[$x]);
        $dkrabat=dkdecimal($rabat[$x]);
        if ($antal[$x]) {
          if ($art=='DK') {$dkantal[$x]=dkdecimal($antal[$x]*-1);}
          else {$dkantal[$x]=dkdecimal($antal[$x]);}
          if (substr($dkantal[$x],-1)=='0'){$dkantal[$x]=substr($dkantal[$x],0,-1);}
          if (substr($dkantal[$x],-1)=='0'){$dkantal[$x]=substr($dkantal[$x],0,-2);}
#          if (substr($dkantal[$x]-1,1)==","){$dkantal[$x]=substr($dkantal[$x],0,(strlen($dkantal[$x])-1));}
        }
      }
      else {$antal[$x]=''; $dkpris=''; $dkrabat=''; $ialt='';}
      print "<input type=hidden name=linjeantal value=$linjeantal>";
      print "<input type=hidden name=linje_id[$x] value=$linje_id[$x]>";
      print "<input type=hidden name=kred_linje_id[$x] value=$kred_linje_id[$x]>";
      print "<input type=hidden name=vare_id[$x] value=$vare_id[$x]>";
      print "<input type=hidden name=serienr[$x] value=$serienr[$x]>";
      print "<tr>";
      print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=3 name=posn$x value=$x></td>";
      print "<td>$font<small><input readonly=readonly size=10 name=vare$x onfocus=\"document.forms[0].fokus.value=this.name;\" value=\"$varenr[$x]\"></td>";
      print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=3 name=anta$x value=$dkantal[$x]>&nbsp;$enhed[$x]</td>";
      print "<td>$font<small><input type=text size=60 name=beskrivelse$x value=\"$beskrivelse[$x]\"></td>";
      print "<td>$font<span title= 'db: $dk_db[$x] - dg: $dk_dg[$x]%'><small><input type=\"text\" style=\"text-align:right\" size=10 name=pris$x value=\"$dkpris\"></td>";
      print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=4 name=raba$x value=\"$dkrabat\"></td>";
      if ($rabat[$x]) {$db[$x]=$db[$x]-($pris[$x]/100*$rabat[$x]);}
      $db[$x]=$db[$x]*$antal[$x];
      if ($ialt!=0) {$dg[$x]=$db[$x]*100/$ialt;}
      else {$dg[$x]=0;}
      $dbsum=$dbsum+$db[$x]; 
      $dk_db[$x]=dkdecimal($db[$x]);
      $dk_dg[$x]=dkdecimal($dg[$x]);
      if ($ialt) {
       if ($art=='DK') {$ialt=$ialt*-1;}
       print "<td align=right>$font<span title= 'db: $dk_db[$x] - dg: $dk_dg[$x]%'><small>".dkdecimal($ialt)."</td>";
     }      
      else {print "<td></td>";}
      if ($status>=1)  {
        if ($vare_id[$x]){
          $batch="?";
          $tidl_lev[$x]=0;
          $query = db_select("select gruppe, beholdning from varer where id = $vare_id[$x]");
          $row = db_fetch_array($query);
          $beholdning[$x]=$row[beholdning];
          $query = db_select("select box8 from grupper where art='VG' and kodenr='$row[gruppe]'");
          $row = db_fetch_array($query);
          if ($row[box8]=='on'){$lagervare[$x]=1;}

          if ($antal[$x]>0) {
            $query = db_select("select * from batch_salg where linje_id = '$linje_id[$x]' and ordre_id=$id and vare_id = $vare_id[$x]");
            while($row = db_fetch_array($query)) {
              $y++;
              $batch='V';
              $tidl_lev[$x]=$tidl_lev[$x]+$row[antal];
            }
            if ($lagervare[$x]) { 
              $z=0;              
              $query = db_select("select * from reservation where vare_id = $vare_id[$x]");
              while ($row = db_fetch_array($query))  {
               if (($row[linje_id]==$linje_id[$x])||($row[batch_salg_id]==$linje_id[$x]*-1)) {
                  $z=$z+$row[antal];
                  $batch="V";
                }
                elseif ($row[batch_kob_id]<0) {$reserveret[$x]=$reserveret[$x]+$row[antal];}
                elseif ($row[batch_salg_id]==0) {$paavej[$x]=$paavej[$x]+$row[antal];}
              }
              if($z+$tidl_lev[$x]<$antal[$x]) {$batch="?";}
            }
            else {$batch="";}
            if (($tidl_lev[$x]<$antal[$x])||($batch=="?")) {$status=1;}
          }
         if ($antal[$x]<0) {
            $tidl_lev[$x]=0;
            $query = db_select("select * from batch_salg where linje_id = '$linje_id[$x]' and ordre_id=$id and vare_id = $vare_id[$x]");
            while($row = db_fetch_array($query)){$tidl_lev[$x]=$tidl_lev[$x]+$row[antal];}
           if ($tidl_lev[$x]!=$antal[$x]){$status=1;}
            if ($leveres[$x]>$tidl_lev[$x]+$antal[$x]) {$leveres[$x]=$antal[$x]-$tidl_lev[$x];}
            $query = db_select("select * from reservation where linje_id = '$linje_id[$x]'");
            if (($row = db_fetch_array($query))&&($beholdning[$x]>=0)) {
              if ($antal[$x]+$tidl_lev[$x]!=$row[antal]) {db_modify ("update reservation set antal=$antal[$x]*-1 where linje_id=$linje_id[$x] and batch_salg_id=0");} 
            }
            elseif ($antal[$x]-$tidl_lev[$x]!=0) {db_modify("insert into reservation (linje_id, vare_id, batch_salg_id, antal) values  ($linje_id[$x], $vare_id[$x], 0, $antal[$x]*-1)");}
          }
          elseif ($leveres[$x]+$tidl_lev[$x]>$antal[$x]){$leveres[$x]=$antal[$x]-$tidl_lev[$x];}
          if ($art=='DK') {$dklev[$x]=dkdecimal($leveres[$x]*-1);}
          else {$dklev[$x]=dkdecimal($leveres[$x]);}
          if (substr($dklev[$x],-1)=='0'){$dklev[$x]=substr($dklev[$x],0,-1);}
          if (substr($dklev[$x],-1)=='0'){$dklev[$x]=substr($dklev[$x],0,-2);}
          print "<td>$font<span title= 'Beholdning: $beholdning[$x]'><small><input type=\"text\" style=\"text-align:right\" size=2 name=leve$x value=\"$dklev[$x]\"></td>";
          if ($art=='DK') {$tidl_lev[$x]=dkdecimal($tidl_lev[$x]*-1);}
          else {$tidl_lev[$x]=dkdecimal($tidl_lev[$x]);}
          if (substr($tidl_lev[$x],-1)=='0'){$tidl_lev[$x]=substr($tidl_lev[$x],0,-1);}
          if (substr($tidl_lev[$x],-1)=='0'){$tidl_lev[$x]=substr($tidl_lev[$x],0,-2);}
          print "<td>$font<small>($tidl_lev[$x])</td>";
          $temp=$beholdning[$x]-$reserveret[$x];
          if ($lagervare[$x]) {print "<td align=center onClick=\"batch($linje_id[$x])\">$font<span title= 'V&aelig;lg fra k&oslash;bsordre'><small><img alt=\"Serienummer\" src=../ikoner/serienr.png></td></td>";}
          else {print "<td></td>";}
        }
      }
      print "</tr>\n";
    }
    if ($kontonr)
    {
      $x++;
      $posnr[0]=$linjeantal+1;
      print "<tr>";
      print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=3 name=posn0 value=$posnr[0]></td>";
      if ($art=='DK') {print "<td>$font<small><input readonly=readonly size=10 name=vare0 onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";}
      else {print "<td>$font<small><input  type=\"text\" size=10 name=vare0 onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";}
      print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=3 name=anta0></td>";
      print "<td>$font<small><input type=text size=60 name=beskrivelse0></td>";
      print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=10 name=pris0></td>";
      print "<td>$font<small><input type=\"text\" style=\"text-align:right\" size=4 name=raba0></td>";
      print "<td>$font<small></td>";
#  print "<td>$font<small><input type=text size=25 name=seri0></td>";
      print "</tr>\n";
      print "<input type=hidden size=3 name=sum value=$sum>";
      $moms=round($momssum/100*$momssats,2);
      db_modify("update ordrer set sum=$sum, kostpris=$kostpris, moms=$moms where id=$id");
      if ($art=='DK') {$sum=$sum*-1;}
      $ialt=($sum+$moms);
#      $sum=dkdecimal($sum);
#      $moms=dkdecimal($moms);
      print "<tr><td colspan=7><table border=\"1\" cellspacing=\"0\" cellpadding=\"0\" width=100%><tbody>";
      print "<tr>";
#  print "<td></td>";
      print "<td align=center>$font<small>Ordresum:&nbsp;".dkdecimal($sum)."</td>";
      $db=$dbsum;
      print "<td align=center>$font<small>D&aelig;kningsbidrag:&nbsp;".dkdecimal($db)."</td>";
      if ($sum) {$dg_sum=($dbsum*100/$sum);}
      else {$dg_sum=dkdecimal(0);}
      print "<td align=center>$font<small>D&aelig;kningsgrad;&nbsp;".dkdecimal($dg_sum)."%</td>";
      print "<td align=center>$font<small>Moms:&nbsp;".dkdecimal($moms)."</td>";
      print "<td align=center>$font<small>I alt:&nbsp;".dkdecimal($ialt)."</td>";
    }
    print "</tbody></table></td></tr>\n";
    print "<input type=\"hidden\" name=\"fokus\">";
    print "<tr><td align=center colspan=8>";
    print "<table width=100% border=\"1\" cellspacing=\"0\" cellpadding=\"1\"><tbody><tr>";
    if ($status < 3)
    {
     if ($status<1) {$width="33%";}
     elseif ($sum!=0) {$width="25%";}
     print "<input type=hidden name=status value=$status>";
     print "<td align=center width=$width><input type=submit accesskey=\"g\" value=\"&nbsp;&nbsp;Gem&nbsp;&nbsp;\" name=\"submit\"></td>";
     print "<td align=center width=$width><input type=submit accesskey=\"o\" value=\"Opslag\" name=\"submit\"></td>";
     if (($status==1)&&($bogfor!=0)) {print "<td align=center width=$width><input type=submit accesskey=\"l\" value=\"&nbsp;Lev&eacute;r&nbsp;\" name=\"submit\"></td>";}
#     if (($status==1)&&($bogfor!=0)) {print "<td align=center><input type=submit accesskey=\"m\" value=\"F&oslash;lgeseddel\" name=\"submit\"></td>";}
     if (($status==2)&&($bogfor!=0)) 
     {
#       print "<td align=center width=$width><input type=submit accesskey=\"u\" value=\"Udskriv f&oslash;lgeseddel\" name=\"submit\"></td>";
       print "<td align=center width=$width><input type=submit accesskey=\"f\" value=\"Faktur&eacute;r\" name=\"submit\"></td>";
     }
     elseif ($linjeantal>0)
     {
       print "<td align=center  width=$width><input type=submit value=\"&nbsp;Udskriv&nbsp;\" name=\"submit\"></td>";
     }
     if ($sum==0) {print "<td align=center><input type=submit value=\"&nbsp;&nbsp;Slet&nbsp;&nbsp;\" name=\"submit\"></td>";}
 
      print "</tbody></table></td></tr>\n";
      print "</form>";
      print "</tbody></table></td></tr></tbody></table></td></tr>\n";
      print "<tr><td></td></tr>\n";
    }
  }
}
######################################################################################################################################
function kontoopslag($sort, $fokus, $id)
{
  global $font;
  global $bgcolor;
  global $bgcolor5;
  
  sidehoved($id, "ordre.php", "../debitor/debitorkort.php", $fokus, "Kundeordre $id - Kontoopslag");
#  print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#  print"<tr><td valign=\"top\">";
  print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
  print"<tbody><tr>";
  print"<td><small><b>$font<a href=ordre.php?sort=kontonr&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Kundenr</b></small></td>";
  print"<td><small><b>$font<a href=ordre.php?sort=firmanavn&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Navn</b></small></td>";
  print"<td><small><b>$font<a href=ordre.php?sort=addr1&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Adresse</b></small></td>";
  print"<td><small><b>$font<a href=ordre.php?sort=addr2&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Adresse2</b></small></td>";
  print"<td><small><b>$font<a href=ordre.php?sort=postnr&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Postnr</b></small></td>";
  print"<td><small><b>$font<a href=ordre.php?sort=bynavn&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>bynavn</b></small></td>";
  print"<td><small><b>$font<a href=ordre.php?sort=land&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>land</b></small></td>";
  print"<td><small><b>$font<a href=ordre.php?sort=kontakt&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Kontaktperson</b></small></td>";
  print"<td><small><b>$font<a href=ordre.php?sort=tlf&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Telefon</b></small></td>";
  print" </tr>\n";


   $sort = $_GET['sort'];
   if (!$sort) {$sort = firmanavn;}

  $query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'D' order by $sort");
  while ($row = db_fetch_array($query))
  {
    $kontonr=str_replace(" ","",$row['kontonr']);
    if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
    else {$linjebg=$bgcolor5; $color='#000000';}
    print "<tr bgcolor=\"$linjebg\">";
    print "<td><small>$font<a href=ordre.php?fokus=$fokus&id=$id&konto_id=$row[id]>$row[kontonr]</a></small></td>";
    print "<td><small>$font $row[firmanavn]</small></td>";
    print "<td><small>$font $row[addr1]</small></td>";
    print "<td><small>$font $row[addr2]</small></td>";
    print "<td><small>$font $row[postnr]</small></td>";
    print "<td><small>$font $row[bynavn]</small></td>";
    print "<td><small>$font $row[land]</small></td>";
    print "<td><small>$font $row[kontakt]</small></td>";
    print "<td><small>$font $row[tlf]</small></td>";
    print "</tr>\n";
  }

  print "</tbody></table></td></tr></tbody></table>";
  exit;
}
######################################################################################################################################
function ansatopslag($sort, $fokus, $id)
{
  global $font;
  global $bgcolor;
  global $bgcolor5;

  $query = db_select("select konto_id from ordrer where id = $id");
  $row = db_fetch_array($query);
  $konto_id = $row[konto_id];
  
  $fokus=$fokus."&konto_id=".$konto_id;
  
  sidehoved($id, "ordre.php", "../debitor/ansatte.php", $fokus, "Debitorordre $id");
#  print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#  print"<tr><td valign=\"top\">";
  print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
  print"<tbody><tr>";
  print"<td><small><b>$font<a href=ordre.php?sort=navn&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Navn</b></small></td>";
  print"<td><small><b>$font<a href=ordre.php?sort=tlf&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Lokal</b></small></td>";
  print"<td><small><b>$font<a href=ordre.php?sort=mobil&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Mobil</b></small></td>";
  print"<td><small><b>$font<a href=ordre.php?sort=email&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>E-mail</b></small></td>";
  print" </tr>\n";


  $sort = $_GET['sort'];
  if (!$sort) {$sort = navn;}

  if (!$id) {exit;}
  $query = db_select("select * from ansatte where konto_id = $konto_id order by $sort");
  while ($row = db_fetch_array($query))
  {
    if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
    else {$linjebg=$bgcolor5; $color='#000000';}
    print "<tr bgcolor=\"$linjebg\">";
    print "<td><small>$font<a href='ordre.php?fokus=$fokus&id=$id&kontakt=$row[navn]'>$row[navn]</a></small></td>";
    print "<td><small>$font $row[tlf]</small></td>";
    print "<td><small>$font $row[mobil]</small></td>";
    print "<td><small>$font $row[email]</small></td>";
    print "</tr>\n";
  }
  print "</tbody></table></td></tr></tbody></table>";
  exit;
}
######################################################################################################################################
function vareopslag($sort, $fokus, $id, $vis_kost, $ref)
{
  global $font;
  global $bgcolor;
  global $bgcolor5;
 
  sidehoved($id, "ordre.php", "../lager/varekort.php", $fokus, "Kundeordre $id - Vareopslag");

#  print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#  print"<tr><td valign=\"top\">";
  print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
  print"<tbody><tr>";
  if ($vis_kost) {print "<tr><td colspan=8 align=center><a href=ordre.php?sort=varenr&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id>$font<small>Udelad kostpriser</a></td></tr>";}
  else {print "<tr><td colspan=4 align=center><a href=ordre.php?sort=varenr&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis_kost=on>$font<small>Vis kostpriser</a></td></tr>";}
  print"<td><small><b>$font<a href=ordre.php?sort=varenr&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis_kost=$vis_kost>Varenr</a></b></small></td>";
  print"<td><small><b>$font<a href=ordre.php?sort=beskrivelse&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis_kost=$vis_kost>Beskrivelse</a></b></small></td>";
  print"<td align=right><small><b>$font<a href=ordre.php?sort=salgspris&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id>Salgspris</a></b></small></td>";
  if ($vis_kost) {print"<td align=right><small><b>$font Kostpris</b></small></td>";}
  print"<td align=right><small><b>$font<a href=ordre.php?sort=beholdning&funktion=vareOpslag&x=$x&fokus=$fokus&id=$id&vis_kost=$vis_kost>Beh.</a></b></small></td>";
  print"<td><br></td>";
#  print"<td><br></td><td><small><b>$fontKunde</b></small></td>";
  print" </tr>\n";

  if ($ref){
    if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$ref'"))) {
      if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'"))) {$lager=$row['kodenr'];}
    }
  }
  $lager=$lager*1;
  if (!$sort) {$sort = varenr;}

  $query = db_select("select * from varer where lukket != '1' order by $sort");
  while ($row = db_fetch_array($query))
  {
    $query2 = db_select("select box8 from grupper where art='VG' and kodenr='$row[gruppe]'");
    $row2 =db_fetch_array($query2);
    if (($row2[box8]=='on')||($row[samlevare]=='on')){
      if (($row[beholdning]!='0')and(!$row[beholdning])){db_modify("update varer set beholdning=0 where id=$row[id]");}
    }
    elseif ($row[beholdning]){db_modify("update varer set beholdning='' where id=$row[id]");}

    if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
    else {$linjebg=$bgcolor5; $color='#000000';}
    print "<tr bgcolor=\"$linjebg\">";
    print "<td><small>$font<a href=\"ordre.php?vare_id=$row[id]&fokus=$fokus&id=$id\">$row[varenr]</a></small></td>";  
    print "<td><small>$font$row[beskrivelse]<br></small></td>";
    $salgspris=dkdecimal($row[salgspris]);
    print "<td align=right><small>$font$salgspris<br></small></td>";
    if ($vis_kost=='on') {
      $query2 = db_select("select kostpris from vare_lev where vare_id = $row[id] order by posnr");
      $row2 = db_fetch_array($query2);
      $kostpris=dkdecimal($row2[kostpris]);
      print "<td align=right><small>$font$kostpris<br></small></td>";
    }
    $reserveret=0;
#    $linjetext="<span title= 'Der er $y i tilbud og $z i ordre '>";
    if ($lager>=1){
      $q2 = db_select("select * from batch_kob where vare_id=$row[id] and rest>0 and lager=$lager");
      while ($r2 = db_fetch_array($q2)) {
        $q3 = db_select("select * from reservation where batch_kob_id=$r2[id]");
        while ($r3 = db_fetch_array($q3)) {$reserveret=$reserveret+$r3[antal];}
      }
      $linjetext="<span title= 'Reserveret: $reserveret'>";
      if ($r2= db_fetch_array(db_select("select beholdning from lagerstatus where vare_id=$row[id] and lager=$lager"))) {
        print "<td align=right>$linjetext<small>$font $r2[beholdning]</small></span></td>";
      } 
    }
    else { 
      $q2 = db_select("select * from batch_kob where vare_id=$row[id] and rest > 0");
      while ($r2 = db_fetch_array($q2)) {
        $q3 = db_select("select * from reservation where batch_kob_id=$r2[id]");
        while ($r3 = db_fetch_array($q3)) {$reserveret=$reserveret+$r3[antal];}
      }
      $linjetext="<span title= 'Reserveret: $reserveret'>";
      print "<td align=right>$linjetext<small>$font $row[beholdning]</small></span></td>";
    }
    print "</tr>\n";
  }
  print "</tbody></table></td></tr></tbody></table>";
  exit;
}
######################################################################################################################################
function sidehoved($id, $returside, $kort, $fokus, $tekst)
{
global $bgcolor2; 
global $font;
global $color; 

  print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Kundeordre</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";
  print "<body bgcolor=\"#339999\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\" center=\"\">";
  print "<div align=\"center\">";

  print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
  print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
  print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
  if ($returside != "ordre.php") {print "<td width=\"25%\" bgcolor=\"$bgcolor2\">$font $color<small><a href=../includes/luk.php?tabel=ordrer&id=$id accesskey=T>Tilbage</a></small></td>";}
  else {print "<td width=\"25%\" bgcolor=\"$bgcolor2\">$font $color<small><a href=ordre.php?id=$id accesskey=T>Tilbage</a></small></td>";}
  print "<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\">$font $color<small>$tekst</small></td>";
  if ($returside != "ordre.php") {print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\">$font $color<small><a href=ordre.php?returside=ordreliste.php accesskey=N>Ny</a></small></td>";}
  else {print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\">$font $color<small><a href=$kort?returside=../debitor/ordre.php&ordre_id=$id&fokus=$fokus accesskey=N>Ny</a></small></td>";}
  print "</tbody></table>";
  print "</td></tr>\n";
  print "<tr><td valign=\"top\" align=center>";
}

######################################################################################################################################
function find_vare_id ($varenr)
{
  $query = db_select("select id from varer where varenr = '$varenr'");
  if ($row = db_fetch_array($query)) {return $row[id];}
}

?>
</tbody></table>
</td></tr>
</tbody></table>
</body></html>
