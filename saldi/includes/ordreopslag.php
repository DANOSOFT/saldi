<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----includes/ordreopslag.php ------patch 3.7.9----2019-04-10-------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2019 saldi.dk aps
// ------------------------------------------------------------------------
//
// 20130210 break ændret til break 1
// 20190410 PHR if fakturadate is not set ordredate is used. 

$query = db_select("select * from adresser where art = 'S'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$eget_firmanavn=trim($row['firmanavn']);
$egen_addr1=trim($row['addr1']);
$egen_addr2=trim($row['addr2']);
$eget_postnr=trim($row['postnr']);
$eget_bynavn=trim($row['bynavn']);
$eget_cvrnr=trim($row['cvrnr']);
$egen_tlf=trim($row['tlf']);
$egen_fax=trim($row['fax']);
$egen_bank_navn=trim ($row['bank_navn']);
$egen_bank_reg=trim ($row['bank_reg']);
$egen_bank_konto=trim ($row['bank_konto']);

if ($id) {
  $query = db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__);
  $row = db_fetch_array($query);
  $firmanavn=trim($row['firmanavn']);
  $addr1=trim($row['addr1']);
  $addr2=trim($row['addr2']);
  $postnr=trim($row['postnr']);
  $bynavn=trim($row['bynavn']);
  $kontakt=trim($row['kontakt']);
  $kundeordnr=trim($row['kundeordnr']);
  $momssats=$row['momssats'];
  $ordredate=$row['ordredate'];
  $ordredato=dkdato($ordredate);
  $leveringsdato=dkdato($row['levdate']);
  $fakturanr=trim($row['fakturanr']);
  $kontonr=$row['kontonr'];
  $ordrenr=$row['ordrenr'];
  $fakturadate=trim($row['fakturadate']);
  $betalingsbet=trim($row['betalingsbet']);
  $betalingsdage=$row['betalingsdage'];
  $art=trim($row['art']);
  $lev_navn=trim($row['lev_navn']);
  $lev_addr1=trim($row['lev_addr1']);
  $lev_addr2=trim($row['lev_addr2']);
  $lev_postnr=trim($row['lev_postnr']);
  $lev_bynavn=trim($row['lev_bynavn']);
  $lev_kontakt=trim($row['lev_kontakt']);

  if ($fakturadate) list($faktaar, $faktmd, $faktdag) = explode("-", $fakturadate);
  else list($faktaar, $faktmd, $faktdag) = explode("-", $ordredate);
	$forfaldsaar=$faktaar;
	$forfaldsmd=$faktmd;
	$forfaldsdag=$faktdag;
	$slutdag=31;

	if ( $fakturadate && $betalingsbet!="Efterkrav" ) 
  {
    while (!checkdate($forfaldsmd, $slutdag, $forfaldsaar))
    {
      $slutdag--;
      if ($slutdag<27) break 1;
    }
    if ($betalingsbet=="Lb.Md") $forfaldsdag=$slutdag; # Saa maa det vaere lb. md
    $forfaldsdag=$forfaldsdag+$betalingsdage;
    while ($forfaldsdag>$slutdag)
    {
      $forfaldsmd++;
      if ($forfaldsmd>12)
      {
        $forfaldsaar++;
        $fortfaldsmd=1;
      }
      $forfaldsdag=$forfaldsdag-$slutdag;
      $slutdag=31;
      while (!checkdate($forfaldsmd, $slutdag, $forfaldsaar))
      {
        $slutdag--;
        if ($slutdag<27) break 1;
      }
    }      
  }
	$forfaldsdato=dkdato($forfaldsaar."-".$forfaldsmd."-".$forfaldsdag);
	$fakturadato=dkdato($fakturadate);
}
?>
