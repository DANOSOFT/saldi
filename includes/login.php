<?php
ob_start(); //Starter output buffering
// --------------includes/login.php----------ver 5.1.1------2026-11-27------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 2013.09.19 Tjekkede ikke om der var opdateringer ved login i "hovedregnskab" Søg 20130919
// 2014.01.06	Tilføjet opslag i tmp_kode. Søg tmp_kode
// 2014.09.20	Tilføjet db_escape_string foran brugernavn og regnskab så det også fungerer med apostrof i disse.
// 2015.01.04 Initerer variablen $nextver så den bypasser versionskontrol i online.php
// 2015.01.14 PK - Tilføjet session_unset,session_destroy, som tømmer alle sessions variabler
// 2015.01.27 PHR	- Tilføjet rettigheder da man ellers bliver smidt af ved forsøg på login med tmp kode.  
// 20261029 CDX/NTR Share login logs and temporary directories with the web server.
// 20261127 CL/LH The page no longer logs in: it wrote the session before checking the password and had no two-factor step.
//                It sends the browser to the login page in index/.
header('Location: ../index/index.php');
exit;
