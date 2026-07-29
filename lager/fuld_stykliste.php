<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><title>SALDI - varekort</title><meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"></head>
<?php

// -------------------fuld_stykliste.php------Patch 5.0.0--2026.07.28---
// LICENSE
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
//
// Copyright (c) 2004-2026 DANOSOFT ApS
// ----------------------------------------------------------------
// 20260729 LOE Redirects to vareliste.php if no id is provided in the URL. Added missing params to db function calls. 

@session_start();
$s_id=session_id();

$modulnr=9;

include("../includes/connect.php");
include("../includes/online.php");
if (!function_exists('dkdecimal')) {
    include("../includes/stdFunc/dkDecimal.php");
}
include("../includes/fuld_stykliste.php");

$id = $_GET['id'] ?? null;
if ($id === null || $id === '') {
    echo "<script>
        alert('A product ID is required to proceed.');
        window.location.href = 'lister/vareliste.php';
    </script>";
    exit;
}


fuld_stykliste($id, 'udskriv', '')

?>
