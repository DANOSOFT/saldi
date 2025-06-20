<?php
@session_start();
$s_id=session_id();

// ----------------------------------------------------------------------050423----------
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
include("../includes/db_query.php");

db_modify("delete from online where session_id = '$s_id'");
print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/index.php\">";
exit;
  
?>
