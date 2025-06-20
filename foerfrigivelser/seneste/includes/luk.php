<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Luk</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>
<?php
  @session_start();
  $s_id=session_id();
 // -------------------------------------------includes/luk.php-----lap 1.0.7----------------------
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
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------
if (!isset($_GET['luk'])) print "<meta http-equiv=\"refresh\" content=\"1;URL=luk.php?luk=luk\">";
include("../includes/std_func.php");
if(($tabel=if_isset($_GET['tabel']))&&($id=if_isset($_GET['id']))) {
	include("../includes/connect.php");
	include("../includes/online.php");
	db_modify("update $tabel set tidspkt='', hvem='' where id=$id");
}
# print "<body onload=\"javascript:Kassekladde.focus();\">";
if ($_GET['luk']) {
	print "<body onload=\"javascript:window.opener.focus();window.close();\">";
 }
 print "<body onload=\"javascript:window.opener.focus();window.close();\">";

?>
