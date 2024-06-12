<?php
// -------saldiprint.php----lap 3.0.6----2010.10.13-------------------
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

$printercmd="lpr -P BIXOLON_SRP-350plus";
$url=$_GET['url'];
$bruger_id=$_GET['bruger_id'];
$bonantal=$_GET['bonantal'];
if (!$bonantal) $bonantal=1;
$printfil=$url.$_GET['printfil'];
$bixfil=$url."/includes/bixprint.bin";
$filnavn=$bruger_id.".txt";

for ($x=1;$x<=$bonantal;$x++) {
#	echo "cd /tmp\nrm $filnavn\nwget --no-check $printfil\n$printercmd $filnavn";
	system ("cd /tmp\nrm $filnavn\nwget --no-check $printfil\n$printercmd $filnavn\n");

#	system ("cd /tmp\nrm $filnavn\nwget $printfil\n$printercmd $filnavn");
	system ("cd /tmp\nrm bixprint.bin\nwget --no-check $bixfil\n$printercmd bixprint.bin");
}
print  "<body onload=\"javascript:window.close();\">";
?>
