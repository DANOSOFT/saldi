<?php
// ----------------------------------------------------------------------
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

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");

  
  $x=0;
  $query = db_select("select kontonr, beskrivelse, kontotype, katagori, moms, fra_kto, til_kto from kontoplan where regnskabsaar='1' order by kontonr");
  while ($row = db_fetch_array($query))
  {
    $x++;
    $kontonr[$x]=$row['kontonr'];
    $beskrivelse[$x]=$row['beskrivelse'];
    $kontotype[$x]=$row['kontotype'];
    $katagori[$x]=$row['katagori'];
    $moms[$x]=$row['moms'];
    $fra_kto[$x]=$row['fra_kto'];
    $til_kto[$x]=$row['til_kto'];
  }

 transaktion("begin");
 for ($y=1;$y<=$x;$y++)
  {
    if (!$fra_kto[$y]){$fra_kto[$y]=0;}
    if (!$til_kto[$y]){$til_kto[$y]=0;}
    db_modify("insert into kontoplan (kontonr, beskrivelse, kontotype, katagori, moms, fra_kto, til_kto) values ($kontonr[$y], '$beskrivelse[$y]', '$kontotype[$y]', '$katagori[$y]', '$moms[$y]', $fra_kto[$y], $til_kto[$y])");
  }
  transaktion("commit")
?>
