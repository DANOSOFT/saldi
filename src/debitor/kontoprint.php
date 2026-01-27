<?php #topkode_start
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------------debitor/kontoprint.php-----lap 4.1.1---2025.09.25-------
// LICENS
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2023 - 2025 Saldi.dk ApS
// ----------------------------------------------------------------------
// 2013.05.10 Tjekker om formular er oprettet og opretter hvis den ikke er.
// 2013.05.12 Virker nu også når der er mere end 1 konto
// 2015.03.16 
// 2015.04.09 Sidste side blev ikke udskrevet v. flere sider. Ændrer $side til $side-1. 20150409
// 2016.11.24 Hvis konto_fra=konto_til søges specifikt på kontonr.
// 2018.12.10 Oprydning af variabler og tilpasning til ny formfunk med htm 
// 2019.06.18 PHR Valuta is now calculated according to exchange rate.
// 2019.06.19 PHR To date was not implemented.
// 2025.09.25 LOE Kilde added to kontoprint function:formfunk.php and version and license text updated 
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




