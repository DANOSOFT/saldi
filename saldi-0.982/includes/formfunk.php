<?
// ---------------------------------/includes/formfunk.php ----------patch 0.936-------------------
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



function skriv($str, $fed, $italic, $color, $tekst, $tekstinfo, $x, $y, $format, $form_font)
{
  global $formular;
  global $side;
  global $connection;
  global $fp;
  global $printerid;
  global $form_id;
  global $subtotal;
  global $id;
  global $sum;
  global $ref;
 
  if (!$incr_x) {$incr_x=0;}
  if (!$incr_y) {$incr_y=0;}
  
  $format=strtoupper($format);

  $tekst=str_replace("(","\\(",$tekst);
  $tekst=str_replace(")","\\)",$tekst);

  if ($color!='0'){$color="$color setrgbcolor";}
  else {$color="0 setgray";}

  $x=$x*2.86;
  $y2=$y*2.86;
  if ($format=='H'){$format="$color dup stringwidth pop\nneg 0 rmoveto";}
  elseif ($format=='C'){$format="$color dup stringwidth pop\n2 div neg 0 rmoveto";}
  else {$format="$color";}
  if (($fed=='on')&&($italic!='on')) {$form_font=$form_font.'-Bold-ISOLatin1 findfont';}
  elseif (($fed!='on')&&($italic=='on')) {$form_font=$form_font.'-Italic-ISOLatin1 findfont';}
  elseif (($fed=='on')&&($italic=='on')) {$form_font=$form_font.'-BoldItalic-ISOLatin1 findfont';}
  else {$form_font=$form_font.'-ISOLatin1 findfont';}
  if (strstr($tekstinfo, 'ordrelinjer')) {
    list ($tekstinfo, $Opkt)=split("_", $tekstinfo);
    if ($y <= $Opkt) {
      $nextside = $side+1;
      $transportsum = dkdecimal($sum);
      $query = db_select("select * from formularer where formular = $formular and side = '!S'");
      while ($row = db_fetch_array($query)) {
        if ($row[color]!='0'){$row[color]="$color setrgbcolor";} 
        else {$color="0 setgray";} 
        if ($row['placering']=='H'){$format="$color dup stringwidth pop\nneg 0 rmoveto";}
        elseif ($row['placering']=='C'){$format="$color dup stringwidth pop\n2 div neg 0 rmoveto";}
        else {$format="$color";}

        $z=-1;
        $n=0; 
        $streng=array(); 
        for ($x=0; $x<strlen($row['beskrivelse']); $x++){
          if ((substr($row['beskrivelse'],$x,1)=="$")||($z==$n)){
            if ($z!=$n) {$n++;} 
            $z=$n;
            if (substr($row['beskrivelse'],$x,1)!=" "){$streng[$n]=$streng[$n].substr($row['beskrivelse'],$x,1);}
            else { 
              $n++;
              $streng[$n]=" ";
            }
          }
          else { $streng[$n]=$streng[$n].substr($row['beskrivelse'],$x,1);}
        }
        $streng_antal=$n;  
        $ny_streng="";
        for ($x=0; $x<=$streng_antal; $x++){
          if (substr($streng[$x],0,1)=="$") {
            $streng[$x]=substr($streng[$x],1);
            list($tabel, $variabel)=split("_",$streng[$x],2);
            if ($tabel=="ordre") {$q2 = db_select("select $variabel from ordrer where id=$id");}
            elseif (($tabel=="eget") || ($tabel=="egen")) {$q2 = db_select("select $variabel from adresser where art='S'");}
            elseif (($tabel=="ansat")&&($ref)) {
              $r2 = db_fetch_array(db_select("select id from adresser where art='S'"));
              $q2 = db_select("select $variabel from ansatte where konto_id=$r2[id] and navn = '$ref'");
            }
            if ($tabel!="formular") {
              $r2 = db_fetch_array($q2);
              $streng[$x]=$r2[$variabel];
            }
            else {
              if ($variabel=='ialt') {$streng[$x]=$ialt;}
              if ($variabel=='moms') {$streng[$x]=$moms;}
              if ($variabel=='nextside') {$streng[$x]=$nextside;}
              if ($variabel=='side') {$streng[$x]=$side;}
              if ($variabel=='sum') {$streng[$x]=$sum;}
              if ($variabel=='transportsum') {$streng[$x]=$transportsum;}
              if ($variabel=='forfaldsdato') {$streng[$x]=$forfaldsdato;}
            }
          }     
          $ny_streng=$ny_streng.$streng[$x];
        }
        if ($ny_streng) {
    #skriv ("$row[str]", "$row[fed]", "$row[kursiv]", "$row[color]", "$ny_streng", 'header', "$row[xa]", "$row[ya]","$row[placering]", "$row[font]");
         fwrite($fp,"/$form_font\n$row[str] scalefont\nsetfont\nnewpath\n".$row[xa]*2.86." ".$row[ya]*2.86." moveto ($ny_streng) $format show\n");
#      fwrite($fp,"/$form_font\n$str scalefont\nsetfont\nnewpath\n523.38 200 moveto ($transportsum) dup stringwidth pop\nneg 0 rmoveto show\n");  
        }
      }     
      bundtekst($id);
      formulartekst($id);
    }
  }
  fwrite($fp,"/$form_font\n$str scalefont\nsetfont\nnewpath\n$x $y2 moveto (".$tekst.") $format show\n");
  return $y;
}

function ombryd($str, $fed, $italic, $color, $tekst, $tekstinfo, $x, $y, $format, $form_font, $laengde)   
{
  $tekst=wordwrap($tekst, $laengde,"\n ");
  $nytekst="";
  for ($z=1; $z<=strlen($tekst); $z++)
  {
     $tegn = substr($tekst,$z-1,1);
     $nytekst = $nytekst . $tegn;
     if (strstr($tegn,"\n"))
     {
        $nytekst=trim($nytekst);
        if (strlen($nytekst)>=1)
        {
          $y=skriv($str, $fed, $italic, $color, $nytekst, $tekstinfo, $x, $y, $format, $form_font);
          $y=$y-4;
        }
        $nytekst="";
     }
  }
  $nytekst=trim($nytekst);
  if (strlen($nytekst)>=1) 
  {
    $y=skriv($str, $fed, $italic, $color, $nytekst, $tekstinfo, $x, $y, $format, $form_font);
#    $y=$y-4;
  }
  return $y;
}

function find_tekst($id, $sidetekst, $formular) 
{
  global $sum;
  global $moms;
  global $ialt;
  global $side;
  global $lev_nr;
  global $ref;

 $query = db_select("select * from formularer where formular = $formular and side = '$sidetekst'");
  while ($row = db_fetch_array($query)) {
#  $streng=spliti (" ", $row['beskrivelse']);
    $z=-1;
    $y=0; 
    $streng=array(); 
    for ($x=0; $x<strlen($row['beskrivelse']); $x++){
      if (((substr($row['beskrivelse'],$x,1)=="$")||($z==$y))&&(substr($row['beskrivelse'],0,4)!="+73<")){
        if ($z!=$y) {$y++;} 
        $z=$y;
        if ((substr($row['beskrivelse'],$x,1)!=" ")&&(substr($row['beskrivelse'],$x,1)!=";")){$streng[$y]=$streng[$y].substr($row['beskrivelse'],$x,1);}
        else { 
          $y++;
          if (substr($row['beskrivelse'],$x,1)!=";") {$streng[$y]=" ";}
        }
      }
      else {
        $streng[$y]=$streng[$y].substr($row['beskrivelse'],$x,1);
      }
    }
    $streng_antal=$y;  
    $ny_streng="";
    for ($x=0; $x<=$streng_antal; $x++){
      if (substr($streng[$x],0,1)=="$") {
        $streng[$x]=substr($streng[$x],1);
        list($tabel, $variabel)=split("_",$streng[$x],2);
        if (($formular==3)&&($tabel=="ordre")&&(($variabel=="lev_navn")||($variabel=="lev_addr1")||($variabel=="lev_addr2")||($variabel=="lev_postnr")||($variabel=="lev_bynavn")||($variabel=="lev_kontakt"))) {
          $variabel=tjek_lev_addr($variabel, $id);
        }
        if ($tabel=="ordre") {$q2 = db_select("select $variabel from ordrer where id=$id");}
        elseif (($tabel=="eget") || ($tabel=="egen")) {$q2 = db_select("select $variabel from adresser where art='S'");}
        elseif ($tabel=="levering") {$q2 = db_select("select $variabel from batch_salg where ordre_id=$id and lev_nr=$lev_nr");}
        elseif ($tabel=="ansat") {
          $r2 = db_fetch_array(db_select("select id from adresser where art='S'"));
          $q2 = db_select("select $variabel from ansatte where konto_id=$r2[id] and navn='$ref'");
        }
        if ($tabel!="formular") {
          $r2 = db_fetch_array($q2);
          if (strstr($variabel, 'date')) {$streng[$x]=dkdato($r2[$variabel]);}
          else {$streng[$x]=$r2[$variabel];}
        }
        else {
          if ($variabel=='ialt') {$streng[$x]=$ialt;}
          if ($variabel=='moms') {$streng[$x]=$moms;}
          if ($variabel=='nextside') {$streng[$x]=$nextside;}
          if ($variabel=='side') {$streng[$x]=$side;}
          if ($variabel=='sum') {$streng[$x]=$sum;}
          if ($variabel=='transportsum') {$streng[$x]=$transportsum;}
          if ($variabel=='forfaldsdato') {$streng[$x]=find_forfaldsdato($id);}
          if ($variabel=='lev_nr') {$streng[$x]=$lev_nr;}
        }
      }     
    $ny_streng=$ny_streng.$streng[$x];
    }
    if ($ny_streng) {skriv ("$row[str]", "$row[fed]", "$row[kursiv]", "$row[color]", "$ny_streng", 'header', "$row[xa]", "$row[ya]", "$row[placering]", "$row[font]");}
  }
}

function find_forfaldsdato($id)
{
  $query = db_select("select fakturadate, betalingsbet, betalingsdage from ordrer where id=$id");
  $row = db_fetch_array($query);
  $fakturadate=$row[fakturadate];
  $betalingsbet=$row[betalingsbet];
  $betalingsdage=$row[betalingsdage];

  list($faktaar, $faktmd, $faktdag) = split("-", $fakturadate);
  $forfaldsaar=$faktaar;
  $forfaldsmd=$faktmd;
  $forfaldsdag=$faktdag;
  $slutdag=31;

  if (($fakturadate)&&($betalingsbet!="Efterkrav")) {
    while (!checkdate($forfaldsmd, $slutdag, $forfaldsaar)) {
      $slutdag--;
      if ($slutdag<27) {break;}
    }
    if ($betalingsbet!="Netto"){$forfaldsdag=$slutdag;} # Saa maa det vaere lb. md
    $forfaldsdag=$forfaldsdag+$betalingsdage;
    while ($forfaldsdag>$slutdag) {
      $forfaldsmd++;
      if ($forfaldsmd>12) {
        $forfaldsaar++;
        $forfaldsmd=1;
      }
      $forfaldsdag=$forfaldsdag-$slutdag;
      $slutdag=31;
      while (!checkdate($forfaldsmd, $slutdag, $forfaldsaar)) {
        $slutdag--;
        if ($slutdag<27) {break;}
      }
    }     
  }
  $forfaldsdato=dkdato($forfaldsaar."-".$forfaldsmd."-".$forfaldsdag); 
  return $forfaldsdato;
}
function tjek_lev_addr($variabel, $id)
{
   if ($variabel=="lev_navn"){$tmp="firmanavn";}
   else {$tmp=substr($variabel, 4);}
   $query = db_select("select $tmp from ordrer where id=$id and lev_navn!='' and lev_addr1!='' and lev_postnr!='' and lev_bynavn!=''");
   if ($row=db_fetch_array($query)) {return $variabel;}
   else {return $tmp;}
}
?>