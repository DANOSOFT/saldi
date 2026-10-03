<?php
// 20260929 CL/SZ SD-698: Target of the card button in the kassekladde account lookup popup. The popup only
//                knows art + kontonr, so this resolves the debitorkort/kreditorkort/kontospec and redirects there
//                with a returside back to the journal; an unknown account goes straight back to the journal.
// 20261003 CL/SZ SD-698: With tab=1 (the card opened in its own tab from the journal) the card's Tilbage closes that tab,
//                and an unknown account closes it too, instead of opening a second copy of the journal there.

ob_start();

@session_start();
$s_id = session_id();
$title = "openAccountCard";
$modulnr = 0;
$bg = "nix";
$header = "nix";

chdir(dirname(__FILE__) . '/..');

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
require_once __DIR__ . '/accountCard.php';

ob_end_clean();

$kladde_id = (int)ifset($_GET, 'kladde_id', 0);
$journal   = "../finans/kassekladde.php?tjek=$kladde_id&kladde_id=$kladde_id";
// In its own tab (tab=1) the card must not lead back into a second copy of the journal: Tilbage, and an
// unknown account, close the tab instead
$returnTo  = (ifset($_GET, 'tab') == '1') ? '../includes/luk.php' : $journal;
$url       = kk_account_card_url(ifset($_GET, 'art', ''), ifset($_GET, 'kontonr', ''), $regnaar, $returnTo);

// the URLs are relative to finans/, and this script sits one folder deeper
header('Location: ../' . ($url ? $url : $returnTo));
exit;
