<?php 

// ----------includes/gls.php----------lap 3.2.9-----2012-04-26-------
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
// Copyright (c) 2003-2012 DANOSOFT ApS
// ----------------------------------------------------------------------

// den eneste dette script gør at konvertere variabler til iso-8859-1 og sender dem videre til GLS - kaldes fra debitor/ordre.php
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding

print "<html xmlns=\"http://www.w3.org/1999/xhtml\" dir=\"ltr\" lang=\"da\">
<head>
	<meta http-equiv=\"Content-Type\" content=\"text/html; charset=ISO-8859-1\" />
</head><body>";

if ($_POST) {
	$txtConsigneeNo=trim(mb_convert_encoding($_POST['txtConsigneeNo'], 'ISO-8859-1', 'UTF-8'));
	$txtReferenceNo=trim(mb_convert_encoding($_POST['txtReferenceNo'], 'ISO-8859-1', 'UTF-8'));
	$txtConsigneeEmail=trim(mb_convert_encoding($_POST['txtConsigneeEmail'], 'ISO-8859-1', 'UTF-8'));
	$txtName1=trim(mb_convert_encoding($_POST['txtName1'], 'ISO-8859-1', 'UTF-8'));
	$txtZipCodeDisplay=trim(mb_convert_encoding($_POST['txtZipCodeDisplay'], 'ISO-8859-1', 'UTF-8'));
	$txtCity=trim(mb_convert_encoding($_POST['txtCity'], 'ISO-8859-1', 'UTF-8'));
	$txtStreet=trim(mb_convert_encoding($_POST['txtStreet'], 'ISO-8859-1', 'UTF-8'));
	$txtName2=trim(mb_convert_encoding($_POST['txtName2'], 'ISO-8859-1', 'UTF-8'));
	$txtContact=trim(mb_convert_encoding($_POST['txtContact'], 'ISO-8859-1', 'UTF-8'));

	print "<table style=\"width:100%;text-align: center; position:fixed;top:90%;\">\n";
	print "<tr><td>";
	print "<form name=\"glslabel_form\" action=\"http://www.your-gls.eu/276-I-PORTAL-WEB/content/GLS/DK01/DA/15005.htm\" id=\"maiddddnform\" method=\"POST\">".
	"\n<input type=\"hidden\" name=\"txtAction\" value=\"70120\">".			//this is a must!
	"\n<input type=\"hidden\" name=\"txtConsigneeNo\" value=\"".$txtConsigneeNo."\">".		//this is a must!
	"\n<input type=\"hidden\" name=\"txtWeight\" value=\"1\">".
	"\n<input type=\"hidden\" name=\"txtCountryNum\" value=\"208\">".		//country codes can be fund in source on GLS site.
	"\n<input type=\"hidden\" name=\"txtReferenceNo\" value=\"".$txtReferenceNo."\">".
	"\n<input type=\"hidden\" name=\"txtConsigneeEmail\" value=\"".$txtConsigneeEmail."\">";
	print "\n<input type=\"hidden\" name=\"txtName1\" value=\"".$txtName1."\">";
	print "\n<input type=\"hidden\" name=\"txtZipCodeDisplay\" value=\"".$txtZipCodeDisplay."\">";
	print "\n<input type=\"hidden\" name=\"txtCity\" value=\"".$txtCity."\">";
	print "\n<input type=\"hidden\" name=\"txtStreet\" value=\"".$txtStreet."\">";
	print "\n<input type=\"hidden\" name=\"txtName2\" value=\"".$txtName2."\">";
	print "\n<input type=\"hidden\" name=\"txtContact\" value=\"".$txtContact."\">";
	print "\n<input type=\"submit\" value=\"Send til GLS\">".
	"\n</form>";
	print "<BODY onload=\"javascript:document.forms['glslabel_form'].submit();\">";
}
print "<meta http-equiv=\"refresh\" content=\"0;URL=\"../includes/luk.php\">";
exit;
print "\n</body>";
?>
