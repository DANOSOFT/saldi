<?php
// ---------------------------------/lager/opdater_kostpriser.php ----patch 0.935---------------
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

@session_start();
$s_id=session_id();

$modulnr=9;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdecimal.php");
include("../includes/usdecimal.php");
# include("../includes/db_query.php");
 
 

$query = db_select("select vare_id, kostpris from vare_lev");
while ($row = db_fetch_array($query)) {
  db_modify("update varer set kostpris = '$row[kostpris]' where id = '$row[vare_id]'");
}
?>
