<?php #topkode_start
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------------debitor/kontoprint.php-----lap 3.8.1---2019.06.19-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
// 2013.05.10 Tjekker om formular er oprettet og opretter hvis den ikke er.
// 2013.05.12 Virker nu også når der er mere end 1 konto
// 2015.03.16 
// 2015.04.09 Sidste side blev ikke udskrevet v. flere sider. Ændrer $side til $side-1. 20150409
// 2016.11.24 Hvis konto_fra=konto_til søges specifikt på kontonr.
// 2018.12.10 Oprydning af variabler og tilpasning til ny formfunk med htm 
// 2019.06.18 PHR Valuta is now calculated according to exchange rate.
// 2019.06.19 PHR To date was not implemented.

@session_start();
$s_id=session_id();
$formular=11;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/formfunk.php");
include("../includes/forfaldsdag.php");

$konto_fra=if_isset($_GET['konto_fra']);
$konto_til=if_isset($_GET['konto_til']);
$dato_fra=if_isset($_GET['dato_fra']);
$dato_til=if_isset($_GET['dato_til']);
$kontoart=if_isset($_GET['kontoart']);
$email=if_isset($_GET['email']);

kontoprint($konto_fra,$konto_til,$dato_fra,$dato_til,$kontoart,$email);
exit;
?>




