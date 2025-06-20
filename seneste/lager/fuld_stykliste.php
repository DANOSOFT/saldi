<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><title>SALDI - varekort</title><meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"></head>
<?php

// ----------------------------------------------------------------------050306----------
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
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------


@session_start();
$s_id=session_id();

$modulnr=9;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdecimal.php");
 include("../includes/fuld_stykliste.php");

fuld_stykliste($_GET['id'], 'udskriv', '')
?>
