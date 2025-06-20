<?php
#Her saettes baggrundsfarver mm. 
$textcolor="#000000";
$textcolor2="#009900";
$bgcolor="#dddddd"; #alm baggrund
$bgcolor2="#bbbbbb"; #top & bundlinjer
$bgcolor3="#cccccc";
$bgcolor4="#aaaaaa";
$bgcolor5="#d2d2d2";
$font = "<font face='Arial, Helvetica, sans-serif'>";

$convert="/usr/bin/convert";

if (!isset($header)) $header="yep";

if ($header!='nix') {
	PRINT "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n<html>\n<head><title>SALDI - $title</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";
}
if ($bg!='nix') {PRINT "<body bgcolor=$bgcolor link='#000000' vlink='#000000' alink='#000000' center=''>";}
?>