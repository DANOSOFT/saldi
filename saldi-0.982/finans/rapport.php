<?

// --------------------------------------------finans/rapport.php--------------patch 0.982----------
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
  }
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head>
  <title>Finansrapport</title>
  <meta http-equiv="content-type" content="text/html; charset=ISO-8859-1">

</head><body text="#000000" bgcolor="#edede2" link="#000099" vlink="#990099" alink="#000099"><center>

<br>
<?
if ($HTTP_POST_VARS['submit'])
{
  include("../includes/usdate.php");
  include("../includes/dkdato.php");
  include("../includes/dkdecimal.php");
  include("../includes/db_query.php");
  

  $submit=strtolower(trim($HTTP_POST_VARS['submit']));
  $rapportart=strtolower(trim($HTTP_POST_VARS['rapportart']));
  $maaned_fra=$HTTP_POST_VARS['maaned_fra'];
  $maaned_til=$HTTP_POST_VARS['maaned_til'];
  $md=$HTTP_POST_VARS['md'];
  list ($afd, $beskrivelse) = split(":", $HTTP_POST_VARS['afd']);
  $afd=trim($afd);
  list ($konto_fra, $beskrivelse) = split(":", $HTTP_POST_VARS['konto_fra']);
  list ($konto_til, $beskrivelse) = split(":", $HTTP_POST_VARS['konto_til']);
  list ($regnaar, $beskrivelse)= split("-", $HTTP_POST_VARS['regnaar']);

}
else
{
  $rapportart=$_GET['rapportart'];
  $maaned_fra=$_GET['maaned_fra'];
  $maaned_til=$_GET['maaned_til'];
  $konto_fra=$_GET['konto_fra'];
  $konto_til=$_GET['konto_til'];
  $regnaar=$_GET['regnaar'];
  $afd=$_GET['afd'];
}
$md[1]="januar"; $md[2]="februar"; $md[3]="marts"; $md[4]="april"; $md[5]="maj"; $md[6]="juni"; $md[7]="juli"; $md[8]="august"; $md[9]="september"; $md[10]="oktober"; $md[11]="november"; $md[12]="december";

if ($submit != 'ok') {$submit='forside';}
else {if ($rapportart){$submit=$rapportart;}}

$submit($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til, $rapportart, $afd);
##################################################################################################
function forside($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til, $rapportart, $afd){

  global $connection;
  global $brugernavn;
  global $font;
  global $md;

  $regnaar=$regnaar*1; #fordi den er i tekstformat og skal vï¿½e numerisk
  $konto_fra=$konto_fra*1;
  $konto_til=$konto_til*1;

  print "$font <a accesskey=h href=\"../includes/luk.php\">Hovedmenu</a><br><br>";

  if (!$regnaar) {
    $query = db_select("select regnskabsaar from brugere where brugernavn = '$brugernavn'");
    $row = db_fetch_array($query);
    $regnaar = $row['regnskabsaar'];
  }
  $query = db_select("select * from grupper where art = 'RA' order by box2");
  $x=0;
  while ($row = db_fetch_array($query)) {
    $x++;
    $regnaar_id[$x]=$row[id];
    $regn_beskrivelse[$x]=$row['beskrivelse'];
    $start_md[$x]=$row['box1']*1;
    $start_aar[$x]=$row['box2']*1;
    $slut_md[$x]=$row['box3']*1;
    $slut_aar[$x]=$row['box4']*1;
    $regn_kode[$x]=$row[kodenr];
    if ($regnaar==$row[kodenr]){$aktiv=$x;}
  }
  $antal_regnaar=$x;

  $query = db_select("select * from kontoplan where regnskabsaar='$regnaar' order by kontonr");
  $x=0;
  while ($row = db_fetch_array($query)) {
    $x++;
    $konto_id[$x]=$row[id];
    $kontonr[$x]=$row['kontonr'];
    $konto_beskrivelse[$x]=$row['beskrivelse'];
    if ($kontonr[$x]==$konto_fra){$konto_fra=$kontonr[$x]." : ".$konto_beskrivelse[$x];}
    if ($kontonr[$x]==$konto_til){$konto_til=$kontonr[$x]." : ".$konto_beskrivelse[$x];}
  }
  $antal_konti=$x;
  if (!$maaned_fra){$maaned_fra=$md[$start_md[$aktiv]];}
  if (!$maaned_til){$maaned_til=$md[$slut_md[$aktiv]];}
  if (!$konto_fra){$konto_fra=$kontonr[1]." : ".$konto_beskrivelse[1];}
  if (!$konto_til){$konto_til=$kontonr[$antal_konti]." : ".$konto_beskrivelse[$antal_konti];}

  $query = db_select("select * from grupper where art='AFD' order by kodenr");
  $x=0;
  while ($row = db_fetch_array($query)) {
    $x++;
    $afdeling[$x]=$row['kodenr'];
    $afd_navn[$x]=$row['beskrivelse'];
    if ($afd == $afdeling[$x]) {$afd = $afdeling[$x]." : ".$afd_navn[$x];}
  }
  $antal_afd=$x;

  print "<form name=regnskabsaar action=rapport.php method=post>";
  print "<table cellpadding=\"1\" cellspacing=\"5\" border=\"1\">";
  print "<tbody>";
  print "<tr><td align=center><h3>$font Rapporter</font><br></h3></td></tr>";
  print "<td><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
  print "<tr><td>$font<small>Regnskabs&aring;r</td><td><select name=regnaar>";
  print "<option>$regnaar - $regn_beskrivelse[$aktiv]</option>";
  for ($x=1; $x<=$antal_regnaar;$x++) {
    if ($x!=$aktiv) {print "<option>$regn_kode[$x] - $regn_beskrivelse[$x]</option>";}
  }

  print "</td><td><input type=submit value=\"Opdat&eacute;r\" name=\"submit\"></td></tr>";
  print "</form>";
  print "<form name=rapport action=rapport.php method=post>";

  print "</tr><td>$font<small>Rapport art</td><td><select name=rapportart>";
  if ($rapportart) {print "<option>$rapportart</option>";}
  if ($rapportart!="Kontokort") {print "<option>Kontokort</option>";}
  if ($rapportart!="Regnskab") {print "<option>Regnskab</option>";}
  print "</td><tr>";
    if ($antal_afd) {
    print "<tr><td>$font<small>Afdeling</td><td><select name=afd>";
    print "<option>$afd</option>";
    if ($afd) {print "<option></option>";}
    for ($x=1; $x<=$antal_afd; $x++) {
       if ($afd != $afdeling[$x]) {print "<option>$afdeling[$x] : $afd_navn[$x]</option>";}
    }
    print "</td></tr>";
  }
  print "<tr><td>$font<small>Periode</td><td colspan=2><select name=maaned_fra>";
  print "<option>$start_aar[$aktiv] $maaned_fra</option>";
  for ($x=$start_md[$aktiv]; $x <= 12; $x++)
  {
    print "<option>$start_aar[$aktiv] $md[$x]</option>";
  }
  if (($start_md[$aktiv]>1)&&($slut_md[$aktiv]<12))
  {
    for ($x=1; $x<=$slut_md[$aktiv]; $x++)
    {
      print "<option>$slut_aar[$aktiv] $md[$x]</option>";
    }
  }
  print "</select>&nbsp;-&nbsp;";
  print "<select name=maaned_til>";
  print "<option>$slut_aar[$aktiv] $maaned_til</option>";
  for ($x=$start_md[$aktiv]; $x <= 12; $x++)
  {
    print "<option>$start_aar[$aktiv] $md[$x]</option>";
  }
  if (($start_md[$aktiv]>1)&&($slut_md[$aktiv]<12))
  {
    for ($x=1; $x<=$slut_md[$aktiv]; $x++)
    {
      print "<option>$slut_aar[$aktiv] $md[$x]</option>";
    }
  }
  print "</select></td></tr>";
  print "<tr><td>$font<small>Konto (fra)</td><td colspan=2><select name=konto_fra>";
  print "<option>$konto_fra</option>";
  for ($x=1; $x<=$antal_konti; $x++)
  {
    print "<option>$kontonr[$x] : $konto_beskrivelse[$x]</option>";
  }
  print "</td></tr>";
  print "<tr><td>$font<small>Konto (til)</td><td colspan=2><select name=konto_til>";
  print "<option>$konto_til</option>";
  for ($x=1; $x<=$antal_konti; $x++)
  {
    print "<option>$kontonr[$x] : $konto_beskrivelse[$x]</option>";
  }
  print "</td></tr>";
  print "<input type=hidden name=regnaar value=$regnaar>";
  print "<tr><td colspan=3 align=center><input type=submit value=\" OK  \" name=\"submit\"></td></tr>";
  print "</form>";
  print "</tbody></table>";

}
# endfunc forside
#################################################################################################
function kontokort($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til, $rapportart, $afd) {
  global $connection;
  global $font;
  global $md;
  
  $query = db_select("select firmanavn from adresser where art='S'");
  if ($row = db_fetch_array($query)) {$firmanavn=$row[firmanavn];}

  $regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk

  list ($x, $maaned_fra) = split(" ", $maaned_fra);
  list ($x, $maaned_til) = split(" ", $maaned_til);

  $maaned_fra=trim($maaned_fra);
  $maaned_til=trim($maaned_til);
  $konto_fra=trim($konto_fra);
  $konto_til=trim($konto_til);



  print "$font <a accesskey=t href=\"rapport.php?rapportart=Kontokort&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til&afd=$afd\">Tilbage</a><br><br>";

  print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
  print "<tr><td colspan=\"4\"><big><big><big><span style=\"font-family: helvetica,arial,sans-serif;\">Kontokort</span></big></big></big></td>";

  print "<td colspan=2 align=right><table style=\"text-align: left; width: 100%;\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody><tr>";
  print "<td><small><span style=\"font-family: helvetica,arial,sans-serif;\">Regnskabsaar</span></small></td>";
  print "<td><small><span style=\"font-family: helvetica,arial,sans-serif;\">$regnaar</span></small></td></tr>";
  print "<tr><td><small><span style=\"font-family: helvetica,arial,sans-serif;\">Periode</span></small></td>";

  ## Finder start og slut paa regnskabsaar

  print "<td><small><span style=\"font-family: helvetica,arial,sans-serif;\">$maaned_fra - $maaned_til</span></small></td></tr>";
  print "</tbody></table></td></tr>";

  for ($x=1; $x<=12; $x++)
  {
    if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
    if ($maaned_til==$md[$x]){$maaned_til=$x;}
    if (strlen($maaned_fra)==1){$maaned_fra="0".$maaned_fra;}
    if (strlen($maaned_til)==1){$maaned_til="0".$maaned_til;}
  }

  $query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'");
  $row = db_fetch_array($query);
#  $regnaar=$row[kodenr];
  $startmaaned=$row[box1]*1;
  $startaar=$row[box2]*1;
  $slutmaaned=$row[box3]*1;
  $slutaar=$row[box4]*1;
  $slutdato=31;

  ##
  $regnaarstart= $startaar. "-" . $startmaaned . "-" . '01';
  
  if ($maaned_fra) {$startmaaned=$maaned_fra;}
  if ($maaned_til) {$slutmaaned=$maaned_til;}

#  if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}
  
  while (!checkdate($slutmaaned,$slutdato,$slutaar))
  {
    $slutdato=$slutdato-1;
    if ($slutdato<28){break;}
  }

  $regnstart = $startaar. "-" . $startmaaned . "-" . '01';
  $regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;
 #$regnslut = "2005-05-04";
  

  print "<tr><td colspan=5>$firmanavn</td></tr>";

  $x=0;
  $query = db_select("select * from kontoplan where regnskabsaar='$regnaar' and kontonr>='$konto_fra' and kontonr<='$konto_til' order by kontonr");
  while ($row = db_fetch_array($query))
  {
    $x++;
    $kontonr[$x]=$row[kontonr]*1;
    $kontobeskrivelse[$x]=$row['beskrivelse'];
#$primo[$x]=$row[primo];
    if ($row[kontotype]=="S") {$primo[$x]=$row[primo];}
    else {$primo[$x]=0;}
  }
  $kontoantal=$x;
  $ktonr=array();
  $x=0;
  $query = db_select("select kontonr from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' order by transdate");
  while ($row = db_fetch_array($query))
  {
    $x++;
    $ktonr[$x]=$row[kontonr];
  }


  $kontosum=0;
  $founddate=false;
  print "<tr><td colspan=6><hr></td></tr>";
  print "<tr><td width=10%><small>Dato</td><td width=10%><small>Bilag</small></td><td width=50%><small>Tekst</small></td><td width=10% align=right><small>Debet</small></td><td width=10% align=right><small>Kredit</small></td><td width=10% align=right><small>Saldo</small></td></tr>";

  for ($x=1; $x<=$kontoantal; $x++)
  {
    if (in_array($kontonr[$x], $ktonr))
    {
    print "<tr><td colspan=6><hr></td></tr>";
    print "<tr><td></td><td></td><td><small>$kontonr[$x] : $kontobeskrivelse[$x]</small></tr>";
    print "<tr><td colspan=6><hr></td></tr>";
    $kontosum=$primo[$x];
    $query = db_select("select debet, kredit from transaktioner where kontonr=$kontonr[$x] and transdate>='$regnaarstart' and transdate<'$regnstart' order by transdate");
    while ($row = db_fetch_array($query))
    {
         $kontosum= $kontosum+$row[debet]-$row[kredit];
    }
    $tmp=dkdecimal($kontosum);
    print "<tr><td></td><td></td><td><small>Primosaldo</small></td><td></td><td></td><td align=right><small>$tmp</small></td></tr>";
    $print=1;
    $query = db_select("select * from transaktioner where kontonr=$kontonr[$x] and transdate>='$regnstart' and transdate<='$regnslut' order by transdate");
    while ($row = db_fetch_array($query))
    {
      print "<tr><td><small>$row[transdate]</small></td><td><small>$row[bilag]</small></td><td><small>$row[beskrivelse]</small></td>";
      $tmp=dkdecimal($row[debet]);
      print "<td align=right><small>$tmp</small></td>";
      $tmp=dkdecimal($row[kredit]);
      print "<td align=right><small>$tmp</small></td>";
      $kontosum=$kontosum+$row[debet]-$row[kredit];
      $tmp=dkdecimal($kontosum);
      print "<td align=right><small>$tmp</small></td></tr>";
    }
    }
  }
  print "<tr><td colspan=6><hr></td></tr>";
  print "</tbody></table>";
}
#################################################################################################
function regnskab($regnaar, $maaned_fra, $maaned_til, $konto_fra, $konto_til, $rapportart, $afd) {
  global $connection;
  global $font;
  global $md;
  
  if ($row = db_fetch_array(db_select("select firmanavn from adresser where art='S'"))) {$firmanavn=$row[firmanavn];}
  if (($afd)&&($row = db_fetch_array(db_select("select beskrivelse from grupper where art='AFD' and kodenr=$afd")))) {$afd_navn=$row['beskrivelse'];}
  
  

  $regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk

  list ($x, $maaned_fra) = split(" ", $maaned_fra);
  list ($x, $maaned_til) = split(" ", $maaned_til);

  $maaned_fra=trim($maaned_fra);
  $maaned_til=trim($maaned_til);
  $konto_fra=trim($konto_fra);
  $konto_til=trim($konto_til);

  print "$font <a accesskey=t href=\"rapport.php?rapportart=Regnskab&regnaar=$regnaar&maaned_fra=$maaned_fra&maaned_til=$maaned_til&konto_fra=$konto_fra&konto_til=$konto_til&afd=$afd\">Tilbage</a><br><br>";

  print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
  print "<tr><td colspan=\"4\"><big><big><span style=\"font-family: helvetica,arial,sans-serif;\">Regnskab</span></big></big></td>";

  print "<td colspan=2 align=right><table style=\"text-align: left; width: 100%;\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody><tr>";
  if ($afd) {
    print "<td><small><span style=\"font-family: helvetica,arial,sans-serif;\">Afdeling</span></small></td>";
    print "<td><small><span style=\"font-family: helvetica,arial,sans-serif;\">$afd: $afd_navn</span></small></td></tr>";
  }
  print "<td><small><span style=\"font-family: helvetica,arial,sans-serif;\">Regnskabsaar</span></small></td>";
  print "<td><small><span style=\"font-family: helvetica,arial,sans-serif;\">$regnaar</span></small></td></tr>";
  print "<tr><td><small><span style=\"font-family: helvetica,arial,sans-serif;\">Periode</span></small></td>";

  ## Finder start og slut paaregnskabsaar

  print "<td><small><span style=\"font-family: helvetica,arial,sans-serif;\">$maaned_fra - $maaned_til</span></small></td></tr>";
  print "</tbody></table></td></tr>";

  for ($x=1; $x<=12; $x++)
  {
    if ($maaned_fra==$md[$x]){$maaned_fra=$x;}
    if ($maaned_til==$md[$x]){$maaned_til=$x;}
    if (strlen($maaned_fra)==1){$maaned_fra="0".$maaned_fra;}
    if (strlen($maaned_til)==1){$maaned_til="0".$maaned_til;}
  }

  $query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'");
  $row = db_fetch_array($query);
#  $regnaar=$row[kodenr];
  $startmaaned=$row[box1]*1;
  $startaar=$row[box2]*1;
  $slutmaaned=$row[box3]*1;
  $slutaar=$row[box4]*1;
  $slutdato=31;

  ##
  $regnaarstart= $startaar. "-" . $startmaaned . "-" . '01';
  
  if ($maaned_fra) {$startmaaned=$maaned_fra;}
  if ($maaned_til) {$slutmaaned=$maaned_til;}

#  if ($slutmaaned<10){$slutmaaned="0".$slutmaaned;}
  
  while (!checkdate($slutmaaned,$slutdato,$slutaar))
  {
    $slutdato=$slutdato-1;
    if ($slutdato<28){break;}
  }

  $regnstart = $startaar. "-" . $startmaaned . "-" . '01';
  $regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;
 #$regnslut = "2005-05-04";
  

  print "<tr><td colspan=4>$firmanavn</td>";
  print "<td align=right><small>Perioden</small></td>";
  print "<td align=right><small>&Aring;r til dato</small></td></tr>";
  print "<tr><td colspan=6><hr></td></tr>";

  $x=0;
  $query = db_select("select * from kontoplan where regnskabsaar='$regnaar' order by kontonr");
  while ($row = db_fetch_array($query))
  {
    $x++;
    $kontonr[$x]=$row['kontonr']*1;
    $kontobeskrivelse[$x]=$row['beskrivelse'];
    $kontotype[$x]=$row['kontotype'];
    $fra_kto[$x]=$row['fra_kto']*1;
    $til_kto[$x]=$row['til_kto']*1;
    $primo[$x]=$row[primo];
#    if ((!$afd)&&($row[kontotype]=="S")) {$aarsum[$x]=$row[primo];}
#    else {$primo[$x]=0;}
  $aarsum[$x]=0;
  }
  $kontoantal=$x;
      $kto_aar[$x]=0;
      $kto_periode[$x]=0;
  $ktonr=array();
  $x=0;
  if ($afd) {$query = db_select("select * from transaktioner where transdate>='$regnaarstart' and transdate<='$regnslut' and afd = $afd order by kontonr");}
  else {$query = db_select("select * from transaktioner where transdate>='$regnaarstart' and transdate<='$regnslut' order by kontonr");}
  while ($row = db_fetch_array($query))
  {
    if (!in_array($row[kontonr], $ktonr)) { # Her fanges konto med bevægelser i perioden.
      $x++;
      $ktonr[$x]=$row[kontonr]*1;
      $kto_aar[$x]=0;
      $kto_periode[$x]=0;                             # Herunder tilføjes primoværdi.
      if ((!$afd) && ($r2 = db_fetch_array(db_select("select primo from kontoplan where regnskabsaar='$regnaar' and kontonr=$ktonr[$x] and kontotype='S'")))) {
        $kto_aar[$x]=$r2[primo];
      }
    }
    $kto_aar[$x]=$kto_aar[$x]+$row['debet']-$row['kredit'];
    if ($row[transdate]>=$regnstart) {$kto_periode[$x]=$kto_periode[$x]+$row['debet']-$row['kredit'];}
  }
  $kto_antal=$x;  

  for ($x=1; $x<=$kontoantal; $x++) { # Her fanges konto med primoværdi og ingen bevægelser i perioden.
    if (!in_array($kontonr[$x], $ktonr)) {
      if ($primo[$x]) {
        $kto_antal++;
        $ktonr[$kto_antal]=$kontonr[$x];
        $kto_aar[$kto_antal]=$primo[$x];
      } 
    }
  }

  for ($x=1; $x<=$kontoantal; $x++) {
    for ($y=1; $y<=$kto_antal; $y++) {
      if (($kontotype[$x] == 'D')||($kontotype[$x] == 'S')) {
        if ($kontonr[$x]==$ktonr[$y]) {
          $aarsum[$x]=$aarsum[$x]+$kto_aar[$y];
          $periodesum[$x]=$periodesum[$x]+$kto_periode[$y];
        }
       }
       elseif ($kontotype[$x] == 'Z') {
        if (($fra_kto[$x]<=$ktonr[$y])&&($til_kto[$x]>=$ktonr[$y])&&($kontonr[$x]!=$ktonr[$y])) {
          $aarsum[$x]=$aarsum[$x]+$kto_aar[$y];
          $periodesum[$x]=$periodesum[$x]+$kto_periode[$y];
        }
      }
    }
  }

  for ($x=1; $x<=$kontoantal; $x++) {
    if (($kontonr[$x]>=$konto_fra)&&($kontonr[$x]<=$konto_til)&&(($aarsum[$x])||($periodesum[$x])||($kontotype[$x] == 'H'))) {
      print "<tr>";
      if ($kontotype[$x] == 'H')
      {
        print "<td colspan=3>$font<b>$kontobeskrivelse[$x]</b></td>";
        print "<tr><td colspan=6><hr></td></tr>";
      }
      elseif ($kontotype[$x] == 'Z') {
        print "<tr><td colspan=6><hr></td></tr>";
        print "<td colspan=4>$font<b><small>$kontobeskrivelse[$x]</small></b></td>";
        $tmp=dkdecimal($periodesum[$x]);
        print "<td align=right><b>$font<small> $tmp</small></b></td>";
        $tmp=dkdecimal($aarsum[$x]);
        print "<td align=right><b>$font<small> $tmp</small></b></td>";
        print "<tr><td colspan=6><hr></td></tr>";
      }
      else {
        print "<td>$font<small>$kontonr[$x]</small></td>";
        print "<td colspan=3>$font<small>$kontobeskrivelse[$x]</small></td>";
        $tmp=dkdecimal($periodesum[$x]);
        print "<td align=right>$font<small>$tmp</small></td>";
        $tmp=dkdecimal($aarsum[$x]);
        print "<td align=right>$font<small>$tmp</small></td>";
      }
    }
    print "</tr>";
  }

  print "<tr><td colspan=6><hr></td></tr>";
  print "</tbody></table>";
}
#################################################################################################

?>
</html>

