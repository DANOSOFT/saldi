<?php #topkode_start
@session_start();
$s_id=session_id();

// ---------debitor/formularprint-----patch 2.1.0---2009-10-20------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaetelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/formfunk.php");
include("../includes/var2str.php");

if (isset($_GET['id']) && $_GET['id']){
	$id=if_isset($_GET['id']);
	$formular=if_isset($_GET['formular']);
	$lev_nr=if_isset($_GET['lev_nr']);
	$bg="nix";
	formularprint($id,$formular,$lev_nr,$charset);
}
if ($popup) print "<meta http-equiv=\"refresh\" content=\"1;URL=../includes/luk.php\">";
else print "<meta http-equiv=\"refresh\" content=\"1;URL=ordre.php?id=$id\">";
?>


