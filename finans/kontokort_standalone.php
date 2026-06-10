<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kontokort_standalone.php-----patch 5.0.0 ----2026-04-29-----
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
//
//20260429 LOE Created standalone version of kontokort repoort for easy navigation
@session_start();
$s_id = session_id();

$css = "../css/standard.css";
include("../includes/var_def.php");
include("../includes/connect.php");
include("../includes/online.php");
 include("../includes/std_func.php");

// include("../includes/row-hover-style.js.php");

// --- Read all params from GET (so refresh works correctly) --- 
$regnaar     = (int) if_isset($_GET, null, 'regnaar');
$maaned_fra  = if_isset($_GET, null, 'maaned_fra');
$maaned_til  = if_isset($_GET, null, 'maaned_til');
$aar_fra     = if_isset($_GET, null, 'aar_fra');
$aar_til     = if_isset($_GET, null, 'aar_til');
$dato_fra    = if_isset($_GET, null, 'dato_fra');
$dato_til    = if_isset($_GET, null, 'dato_til');
$konto_fra   = if_isset($_GET, null, 'konto_fra');
$konto_til   = if_isset($_GET, null, 'konto_til');
$rapportart  = if_isset($_GET, null, 'rapportart');
$ansat_fra   = if_isset($_GET, null, 'ansat_fra');
$ansat_til   = if_isset($_GET, null, 'ansat_til');
$afd         = if_isset($_GET, null, 'afd');
$projekt_fra = if_isset($_GET, null, 'projekt_fra');
$projekt_til = if_isset($_GET, null, 'projekt_til');
$simulering  = if_isset($_GET, null, 'simulering');
$lagerbev    = if_isset($_GET, null, 'lagerbev');
$page        = max(1, (int) if_isset($_GET, 1, 'page'));
$per_page = (int) if_isset($_GET, 50, 'per_page');
if ($per_page < 1) $per_page = 50; 

include("rapport_includes/kontokort.php");

kontokort(
    $regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til,
    $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart,
    $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,
    $simulering, $lagerbev, $page, $per_page   
);

