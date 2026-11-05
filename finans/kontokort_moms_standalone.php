<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kontokort_moms_standalone.php-----ver 5.1.0 ----2026-11-03-----
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
//20260430 LOE Created standalone version of kontokort repoort for easy navigation
// 20260916 SS Declared $permission_key (roles & permissions, phase 3)
// 20261030 CL/LAH per_page defaults to 0 = all lines.
// 20261103 LOE SST-936 Resolve the employee range into the id list kontokort_moms() filters by.
//              The employee list the report page resolved is used when the print link carries it.

@session_start();
$s_id = session_id();

$css = "../css/standard.css";
include("../includes/var_def.php");
include("../includes/connect.php");
$permission_key = 'finans.regnskab';
include("../includes/online.php");
 include("../includes/std_func.php");



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
$ansatte_id_param = if_isset($_GET, null, 'ansatte_id');
$afd         = if_isset($_GET, null, 'afd');
$projekt_fra = if_isset($_GET, null, 'projekt_fra');
$projekt_til = if_isset($_GET, null, 'projekt_til');
$simulering  = if_isset($_GET, null, 'simulering');
$lagerbev    = if_isset($_GET, null, 'lagerbev');
$page        = max(1, (int) if_isset($_GET, 1, 'page'));
$per_page    = max(0, (int) ifset($_GET, 'per_page', 0)); // 0 = all lines

// kontokort_moms() filters the employee list it is given, and the report page builds that list
// from its form. This page receives the two bounds, so the ids between them are resolved here,
// read between the smaller and the larger id so the direction of the bounds does not matter.
// The report page resolves the employee range in the form's own order - the employees between the
// two chosen initials - and passes that list here, because rebuilding it from the two ids would take
// every id in between and could include, or miss, an employee the form did not select. A link that
// carries only the bounds (a bookmark, or an older print window) still resolves the range by id.
$ansatte_id = NULL;
if (is_string($ansatte_id_param) && preg_match('/^\d+(,\d+)*$/', $ansatte_id_param)) {
    $ansatte_id = $ansatte_id_param;
} elseif ($ansat_fra && $ansat_til && preg_match('/^\d+$/', (string)$ansat_fra) && preg_match('/^\d+$/', (string)$ansat_til)) {
    $ansatLav = min((int)$ansat_fra, (int)$ansat_til);
    $ansatHoej = max((int)$ansat_fra, (int)$ansat_til);
    $ansatteIder = array();
    $qtxt = "select id from ansatte where id >= '" . $ansatLav . "' and id <= '" . $ansatHoej . "' order by id";
    $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($q)) {
        $ansatteIder[] = (int)$r['id'];
    }
    if ($ansatteIder) {
        $ansatte_id = implode(",", $ansatteIder);
    }
}

include("rapport_includes/kontokort_moms.php");

kontokort_moms(
    $regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til,
    $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart,
    $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,
    $simulering, $lagerbev, $page, $per_page   
);


