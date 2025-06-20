<?php
#----------------- includes/settings.php ------2.1.8--- 2010.04.15 ----------
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
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------

#Her saettes baggrundsfarver mm. 

if (!isset($db_encode)) $db_encode=NULL;
$textcolor="#000077";
$textcolor2="#009900";
$bgcolor="#eeeef0"; #alm baggrund
$bgcolor2="#BEBCCE"; #top & bundlinjer
$bgcolor3="#cccccc";
$bgcolor4="#d0d0f0";
$bgcolor5="#e0e0f0";
$font = "<font face='Arial, Helvetica, sans-serif'>";
$top_bund="style=\"border: 1px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\" align=\"center\" background=\"../img/knap_bg.gif\";";
$stor_knap_bg="style=\"border: 1px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\" align=\"center\" background=\"../img/stor_knap_bg.gif\";";
$knap_ind="style=\"border: 1px solid rgb(220, 220, 255); padding: 0pt 0pt 1px;\" align=\"center\" background=\"../img/knap_ind.gif\";";
$stor_knap_ind="style=\"border: 1px solid rgb(2, 180, 255); padding: 0pt 0pt 1px;\" align=\"center\" background=\"../img/stor_knap_ind.gif\";";

$convert="/usr/bin/convert";

if (!isset($header)) $header=NULL;
if (!isset($bg)) $bg=NULL;
if (!isset($css)) $css=NULL;

if ($db_encode=="UTF8") $charset="UTF-8";
else $charset="ISO-8859-1";
if ($header!='nix') {
	if ($db_encode=="UTF8") $charset="UTF-8";
	else $charset="ISO-8859-1";
	PRINT "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n
	<html>\n
	<head><title>$title</title><meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\">\n";
	if ($css) PRINT "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\" />";
	PRINT "</head>";
}
if ($bg!='nix') {PRINT "<body bgcolor=$bgcolor link='#000000' vlink='#000000' alink='#000000' center=''>";}
?>