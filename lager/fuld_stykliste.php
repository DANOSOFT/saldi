<?php

// ----------------------------------------------------------------------050306----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20260920 CDX/LUI Validate the selected item and reuse the current decimal formatter.


@session_start();
$s_id=session_id();

$modulnr=9;

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
require_once(__DIR__ . "/../includes/std_func.php");
require_once(__DIR__ . "/../includes/fuld_stykliste.php");

$id = (int)ifset($_GET, 'id', 0);
if ($id > 0 && db_fetch_array(db_select("SELECT id FROM varer WHERE id=$id", __FILE__ . " linje " . __LINE__))) {
    fuld_stykliste($id, 'udskriv', '');
} else {
    print "<h1>Fuld stykliste</h1><p>Vælg en vare fra varekortet for at se den fulde stykliste.</p>";
    print "<p><a href='varer.php'>Tilbage til varer</a></p>";
}
print "</body></html>";
