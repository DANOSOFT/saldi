<?php
// --------------------------------------------------lager/vareliste.php     patch 0.971----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// SQL finans maa kun efter skriftelig aftale med ITz ApS anvendes som
// vaert for andre virksomheders regnskaber.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2006 ITz ApS
// ----------------------------------------------------------------------
// 20260920 CDX/LUI Route legacy item-list bookmarks to the maintained inventory page.

// This retired page only handled GET navigation; varer.php owns authentication
// and the current inventory renderer. Re-encode parameters for a safe header.
$query = http_build_query($_GET, '', '&', PHP_QUERY_RFC3986);
header('Location: varer.php' . ($query !== '' ? '?' . $query : ''), true, 302);
exit;
