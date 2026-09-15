<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Luk</title><meta http-equiv=\"content-type\" content=\"text/html; charset=UTF-8\"></head>
<?php
  @session_start();
  $s_id=session_id();
 // -------------------includes/luk.php-----lap 3.1.67----2011.03.28------------------
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
// Copyright (c) 2004-2011 DANOSOFT ApS
// ------------------------------------------------------------------------------
// 20260907 CDX/LH Restrict record unlocking to supported tables and escape refresh targets.
?>
<head>

<script language="javascript" type="text/javascript">
function closeChrome() {
	window.open('', '_self', '');
	window.opener.focus();
	window.close();
	window.self.close();

}
</script>

<script language="javascript" type="text/javascript">
function closeFF() {
	window.open('','_parent','');
	window.opener.focus();
	window.close(); 
}
</script>

<script language="javascript" type="text/javascript">
function closeIE() {
	window.opener='X';
 	window.close(); 
	window.opener.focus();
}
</script> 

</head> 

<?php
include(__DIR__ . "/std_func.php");
include(__DIR__ . "/connect.php");
require_once __DIR__ . '/stdFunc/unlockRecord.php';
$kilde = $_GET['kilde'] ?? null;
if ($kilde!='online.php') {
	include(__DIR__ . "/online.php");
}
$browser=NULL;
if (strpos($_SERVER['HTTP_USER_AGENT'],'Chrome')) $browser='chrome';
elseif (strpos($_SERVER['HTTP_USER_AGENT'],'Firefox')) $browser='ff';
elseif (strpos($_SERVER['HTTP_USER_AGENT'],'MSIE')) $browser='ie';

// 20260904 Sawaneh WP-1: returside sanitised (was reflected XSS/open redirect), popup=1
//                  request flag also closes, blocked-close fallback goes to the returside
//                  instead of the login page, and the unlock SQL params are cast/whitelisted.
// 20260908 SZ SST-755: unlock_record() now also takes the releasing tab's own $brugernavn
//                  and $tidspkt, so a release only takes effect while it still names the
//                  lock's current owner and tidspkt - a stale tab's delayed release can no
//                  longer clobber a lock a newer tab has since acquired.
// 20260910 SZ SST-755 (CodeRabbit): a locking-table release now requires a real, non-empty
//                  $brugernavn - previously a request with ?kilde=online.php skips the
//                  includes/online.php include above, leaving $brugernavn null, and
//                  unlock_record() silently drops the owner check entirely when null,
//                  falling back to id+tidspkt-only matching (CWE-862: missing authorization).
if (!function_exists('nav_sanitize_returside')) {
	include(__DIR__ . "/stdFunc/navStack.php");
}
$returside = nav_sanitize_returside($_GET['returside'] ?? null);
$tabel = $_GET['tabel'] ?? null;
$id = (int)($_GET['id'] ?? 0);
$tidspkt = $_GET['tidspkt'] ?? null;
$isLockingTable = in_array($tabel, ['ordrer', 'kladdeliste'], true);
// A locking table's release must carry both the tab's own tidspkt and a real, authenticated
// owner - skip the unlock entirely rather than release the lock without verifying either
// (SST-755). Anything else (most callers, which don't hold a lock at all) is unaffected -
// unlock_record() already no-ops for a $tabel outside its own allowlist.
if (!$isLockingTable) {
	unlock_record($tabel, $id, $brugernavn ?? null, $tidspkt);
} elseif ($tidspkt && !empty($brugernavn)) {
	unlock_record($tabel, $id, $brugernavn, $tidspkt);
}
if (!isset($popup)) $popup = NULL;
if (!empty($_GET['popup'])) $popup = 1; // request flag: this window IS a popup regardless of the user's popup preference
if ($popup || !$returside) {
	if ($browser=='chrome') print  "<body onload=\"javascript:closeChrome();\">";
	if ($browser=='ff') print  "<body onload=\"javascript:closeFF();\">";
	if ($browser=='ie') print  "<body onload=\"javascript:closeIE();\">";
	print "<body onload=\"javascript:window.opener.focus();window.close();\">";
	// When window.close() is blocked (page not script-opened, e.g. inside the
	// new-design iframe), fall back to the returside instead of the login page.
	$lukFallback = $returside ? $returside : "../index/index.php";
	$lukFallback = htmlspecialchars($lukFallback, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	print "<meta http-equiv=\"refresh\" content=\"1;URL=$lukFallback\">";
} elseif ($returside) {
	$returside = htmlspecialchars($returside, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
}
?>
