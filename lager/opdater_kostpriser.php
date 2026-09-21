<?php
// ---------------------------------/lager/opdater_kostpriser.php ----patch 0.935---------------
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
// Copyright (c) 2004-2005 DANOSOFT ApS
// ----------------------------------------------------------------------

// 20260920 CDX/LUI Require an authenticated CSRF-checked POST before updating supplier costs.
@session_start();
$s_id = session_id();
$modulnr = 9;
$title = "Opdater kostpriser";
include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
require_once(__DIR__ . "/../includes/std_func.php");

if (empty($_SESSION['cost_update_csrf'])) {
    $_SESSION['cost_update_csrf'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)ifset($_POST, 'csrf_token', '');
    if (!hash_equals($_SESSION['cost_update_csrf'], $token)) {
        http_response_code(403);
        print "<p>Ugyldig formular. Åbn siden igen og prøv på ny.</p></body></html>";
        exit;
    }
    $query = db_select("select vare_id, kostpris from vare_lev", __FILE__ . " linje " . __LINE__);
    while ($row = db_fetch_array($query)) {
        $itemId = (int)$row['vare_id'];
        $costPrice = (float)$row['kostpris'];
        db_modify("update varer set kostpris = $costPrice where id = $itemId", __FILE__ . " linje " . __LINE__);
    }
    $_SESSION['cost_update_csrf'] = bin2hex(random_bytes(32));
    print "<p>Kostpriser er opdateret.</p>";
}
$token = htmlspecialchars($_SESSION['cost_update_csrf'], ENT_QUOTES, 'UTF-8');
print "<h1>Opdater kostpriser</h1><p>Opdater varernes kostpriser fra leverandørernes priser.</p>";
print "<form method='post'><input type='hidden' name='csrf_token' value='$token'>";
print "<button type='submit'>Opdater kostpriser</button></form>";
print "<p><a href='varer.php'>Tilbage til varer</a></p></body></html>";
