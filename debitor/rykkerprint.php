<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------------debitor/rykkerprint-----lap 3.7.4---2019.10.16-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2019 saldi.dk aps
// ----------------------------------------------------------------------

// 20120815 søg 20120815 V. Logoplacering blev ikke fundet v. opslag. 
// 20130114 Tilføjet 0 som 1. parameter i "send mails"
// 20140628 Indsat afrund for korrekt sum. Søg 20140628
// 20170206	Søger efter teksten inkasso i felt 5 og sender mail til inkassofirma hvis den findes
// 20170324 PHR Tilføjet id i kald til udskriv.php
// 20190116 PHR kke nædret noget, blot en datoopdatering da der ligger forskellige versioner fra 2017 på ssl.   

@session_start();
$s_id=session_id();

$kontoliste=isset($_GET['kontoliste'])? $_GET['kontoliste']:Null;
$konto_antal=isset($_GET['kontoantal'])? $_GET['kontoantal']:Null;
$maaned_fra=isset($_GET['maaned_fra'])? $_GET['maaned_fra']:Null;
$maaned_til=isset($_GET['maaned_til'])? $_GET['maaned_til']:Null;
$regnaar=isset($_GET['regnaar'])? $_GET['regnaar']:Null;
$rykkernr=isset($_GET['rykkernr'])? $_GET['rykkernr']:Null;
$rykker_id=explode(";", $_GET['rykker_id']);
$konto_id = explode(";", $kontoliste);


include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/formfunk.php");

rykkerprint($konto_id,$rykker_id,$rykkernr,$maaned_fra,$maaned_til,$regnaar,0);

# 20120815 næste 8 linjer remmet, bliver vist ikke brigt til noget
#$query = db_select("select * from formularer where formular = $formular and art = 1 and beskrivelse = 'LOGO'",__FILE__ . " linje " . __LINE__);
#if ($row = db_fetch_array($query)) {
#	$logo_X=$row['xa']*2.86;
#	$logo_Y=$row['ya']*2.86;
#} else {
#	$logo_X=430;
#	$logo_Y=758;
#}

?>




