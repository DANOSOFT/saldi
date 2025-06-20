<?php
#Her saettes baggrundsfarver mm. 

if (!isset($db_encode)) $db_encode=NULL;
$textcolor="#000077";
$textcolor2="#009900";
$bgcolor="#eeeef0"; #alm baggrund
$bgcolor2="#BEBCCE"; #top & bundlinjer
$bgcolor3="#cccccc";
$bgcolor4="#bcbcff";
$bgcolor5="#d2d2ff";
$font = "<font face='Arial, Helvetica, sans-serif'>";
$top_bund="style=\"border: 1px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\" align=\"center\" background=\"../img/knap_bg.gif\";";
$stor_knap_bg="style=\"border: 1px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\" align=\"center\" background=\"../img/stor_knap_bg.gif\";";
$knap_ind="style=\"border: 1px solid rgb(220, 220, 255); padding: 0pt 0pt 1px;\" align=\"center\" background=\"../img/knap_ind.gif\";";
$stor_knap_ind="style=\"border: 1px solid rgb(2, 180, 255); padding: 0pt 0pt 1px;\" align=\"center\" background=\"../img/stor_knap_ind.gif\";";

$convert="/usr/bin/convert";

if (!isset($header)) $header=NULL;
if (!isset($bg)) $bg=NULL;
if (!isset($css)) $css=NULL;

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