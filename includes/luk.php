<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Luk</title><meta http-equiv=\"content-type\" content=\"text/html; charset=UTF-8\"></head>
<?php
  @session_start();
  $s_id=session_id();
 // -------------------includes/luk.php-----lap 3.1.67----2011.03.28------------------
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
// Copyright (c) 2004-2011 DANOSOFT ApS
// ------------------------------------------------------------------------------
?>
<head>

<script language="javascript" type="text/javascript">
function closeChrome() {
	window.open('', '_self', '');
	window.opener.focus();
	window.close();
	window.self.close();

}
</script>

<script language="javascript" type="text/javascript">
function closeFF() {
	window.open('','_parent','');
	window.opener.focus();
	window.close(); 
}
</script>

<script language="javascript" type="text/javascript">
function closeIE() {
	window.opener='X';
 	window.close(); 
	window.opener.focus();
}
</script> 

</head> 

<?php
include("../includes/std_func.php");
include("../includes/connect.php");
$kilde=if_isset($_GET['kilde']);
if ($kilde!='online.php') {
	include("../includes/online.php");
}
$browser=NULL;
if (strpos($_SERVER['HTTP_USER_AGENT'],'Chrome')) $browser='chrome';
elseif (strpos($_SERVER['HTTP_USER_AGENT'],'Firefox')) $browser='ff';
elseif (strpos($_SERVER['HTTP_USER_AGENT'],'MSIE')) $browser='ie';

$returside=if_isset($_GET['returside']);
$tabel=if_isset($_GET['tabel']);
$id=if_isset($_GET['id']);
if($tabel && $id) {
	db_modify("update $tabel set tidspkt='', hvem='' where id=$id",__FILE__ . " linje " . __LINE__);
}
if ($popup || !$returside) {
	if ($browser=='chrome') print  "<body onload=\"javascript:closeChrome();\">";	
	if ($browser=='ff') print  "<body onload=\"javascript:closeFF();\">";	
	if ($browser=='ie') print  "<body onload=\"javascript:closeIE();\">";	
	print "<body onload=\"javascript:window.opener.focus();window.close();\">";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/index.php\">";
} elseif ($returside) {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
}
?>
