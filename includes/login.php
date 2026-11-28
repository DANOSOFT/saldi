<?php
// --------------includes/login.php----------ver 5.1.1------2026-11-24------
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
// 20261001 Sawaneh The temporary password is read with tmp_code_check() (type|expire|code, as in index/login.php).
// 20261029 CDX/NTR Share login logs and temporary directories with the web server.
// 20261105 CL/LH The version read at login goes to regnskab.version through tjek4opdat_skriv_version(), which writes only under the update lock, as in index/login.php.
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
// 20261114 CL/ASR This old copy of the login page is not linked from anywhere. It now sends the visitor to index/login.php.
// 20261124 CL/ASR Wording of the history line above; no code change.
header('Location: ../index/index.php');
exit;
