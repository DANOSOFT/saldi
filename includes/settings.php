<?php
#----------------- includes/settings.php ------3.1.8--- 2011-04-12 ----------
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
// ----------------------------------------------------------------------

#Her saettes baggrundsfarver mm. 

if (!isset($db_encode)) $db_encode=NULL;
$textcolor="#000077";
$textcolor2="#009900";
$textcolor3="#6666aa"; # Svagere tekst til det som er mindre vigtigt
if (!isset($bgcolor)) $bgcolor="#eeeef0"; #alm baggrund
$bgcolor2="#BEBCCE"; #top & bundlinjer
$bgcolor3="#cccccc";
$bgcolor4="#d0d0f0";
$bgcolor5="#e0e0f0";
$bgnuance1="+01+01-55"; # Aendring af nuancen til gult ved skiftende linjer
$font = "<font face='Arial, Helvetica, sans-serif'>";
$top_bund="style=\"border: 1px solid rgb(180, 180, 255);padding:0pt 0pt 1px;background:url(../img/knap_bg.gif);\" align=\"center\"";
$stor_knap_bg="style=\"border: 1px solid rgb(180, 180, 255);padding: 0pt 0pt 1px;background:url(../img/stor_knap_bg.gif);\" align=\"center\"";
$knap_ind="style=\"border: 1px solid rgb(220, 220, 255);padding: 0pt 0pt 1px;background:url(../img/knap_ind.gif);\" align=\"center\"";
$stor_knap_ind="style=\"border: 1px solid rgb(2, 180, 255);padding: 0pt 0pt 1px;background:url(../img/stor_knap_ind.gif);\" align=\"center\"";

if (!isset($exec_path)) $exec_path="/usr/bin";
if (!isset($sprog_id)) $sprog_id="1";

$convert="$exec_path/convert";
$pdf2ps="$exec_path/pdf2ps";
if (!isset($timezone) || !$timezone) $timezone='Europe/Copenhagen';
if (phpversion()>="5") date_default_timezone_set($timezone);

if (!isset($header)) $header=NULL;
if (!isset($bg)) $bg=NULL;
if (!isset($css)) $css=NULL;

if ($db_encode=="UTF8") $charset="UTF-8";
else $charset="ISO-8859-1";
?>
