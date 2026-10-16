<?php
// 20261005 Sawaneh G6.2 (audit L10): retired - it deleted four fixed files, not the per-form/background/department files;
//                  single files are removed on the upload page. This page only redirects.
@session_start();
$s_id = session_id();
include("../includes/connect.php");
$modulnr = 1;
$permission_key = 'system.indstillinger';
include("../includes/online.php");
header("Location: settingsSection.php?s=documents.backgrounds&moved=logoslet");
exit;
