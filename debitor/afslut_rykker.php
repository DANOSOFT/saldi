<?php #topkode_start

// -----------------------debitor/afslut_rykker.php-----ver 5.1.1--2026.11.13-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaetelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261110 CDX/ASR Require module write access before this mutation endpoint runs.
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
@session_start();
$s_id=session_id();

$topniveau=NULL;
$rykker_id=array();

include(__DIR__ . '/../includes/connect.php');
$permission_key = 'debitor.konti';
$permission_level = 'write';
include(__DIR__ . '/../includes/online.php');
include(__DIR__ . '/../includes/std_func.php');
include(__DIR__ . '/../includes/openpost.php');

$reminderIds = explode(';', (string)($_GET['rykker_id'] ?? ''));
foreach ($reminderIds as $reminderId) {
	$reminderId = (int)$reminderId;
	if ($reminderId > 0) {
		db_modify("update ordrer set betalt='on' where id='$reminderId'", __FILE__ . " linje " . __LINE__);
	}
}
print "<body onload=\"javascript:window.opener.focus();window.close();\">";

?>