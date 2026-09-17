<?php
// #----------------- shop_connect.php -----ver 3.2.3---- 2011.10.11 ----------
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
$link = mysql_connect("localhost","xxx","xxx");
if (!$link) die('Could not connect: ' . mysql_error());
mysql_select_db('xxx');
$datotid=date("Y-m-d H:i:s");
# Rigtig shop
$products_tax_class_id=1;
$language_id=4;
# Test shop
$products_tax_class_id=2;
$language_id=2;
?>