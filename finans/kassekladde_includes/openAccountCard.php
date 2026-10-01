<?php
// 20260929 CL/SZ SD-698: Target of the card button in the kassekladde account lookup popup. The popup only
//                knows art + kontonr, so this resolves the debitorkort/kreditorkort/kontospec and redirects there
//                with a returside back to the journal; an unknown account goes straight back to the journal.

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
$url       = kk_account_card_url(ifset($_GET, 'art', ''), ifset($_GET, 'kontonr', ''), $regnaar, $journal);

// the URLs are relative to finans/, and this script sits one folder deeper
header('Location: ../' . ($url ? $url : $journal));
exit;
