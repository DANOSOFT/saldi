<?php
// -----------index/glemt_kode.php-----------ver 5.1.1------2026-11-14---
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
// 2015.01.27 tilføjet $bruger_id da man eller får en tom mail hvis brugernavnet ikke er en 
//		mailadresse og der ikke er tilknyttet en mailadresse 20150137 
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20261103 CL/ASR The password-reset token in brugere.tmp_kode starts with 'reset|', the shared format the user system reads (invitations use 'invite|').
// 20261105 CL/LH Recovery mail goes through the regnskab's own SMTP server: the stamdata lookup reads felt_1-felt_4 and firmanavn, not only email.
//                It read them from an unset $row, so it fell back to localhost on every regnskab.
//                Only that server gets the login (felt_2/felt_3) and the stamdata e-mail as sender, set by stamdata_smtp(); Saldi's own on localhost stays anonymous.
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
// 20261114 CL/ASR This old copy of Glemt adgangskode is not linked from anywhere. It now sends the visitor to index/glemt_kode.php, which has the fixes.
header('Location: ../index/glemt_kode.php');
exit;
