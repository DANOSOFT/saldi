<?php
// #----------------- opdat_beholdning.php -----ver 3.2.3---- 2011.10.11 ----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2011 DANOSOFT ApS
// ----------------------------------------------------------------------

$vare_id=$_GET['vare_id'];
$products_id=$_GET['shop_id'];
$beholdning=$_GET['beholdning'];

# echo "$vare_id || $varenr || $beskrivelse || $shop_kat_id[0]<br>";

if ($vare_id&&$products_id) {
	include("shop_connect.php");
	$fp=fopen("vareopdat.log","a");
	fwrite($fp,"update products set products_quantity='$beholdning' where products_id='$products_id'\r\n");
	fclose($fp);  
	
# echo "update products set products_quantity='$beholdning' where products_id='$products_id'<br>";
	mysql_query("update products set products_quantity='$beholdning' where products_id='$products_id'");
}
# print "<body onload=\"javascript:window.close();\">";
?>