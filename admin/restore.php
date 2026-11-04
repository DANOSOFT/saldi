<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- admin/restore.php --- ver 5.1.0 --- 2026-11-04 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20160609 PHR if ($POST) fungerer ikke mere, hvis ikke det angives hvad der postes.  
// 20200308 PHR Varius changes related til Centos 8 / mariadb /postgresql 9x
// 20222706 MSC - Implementing new design
// 20250201 Add hostname to psql
// 20250201 removed init of $uploadedfile which was never used
// 20250201 $brugernavn is never set near the end of the restore function
// 20250426 LOE Modified the javascript confirm function, added language cookie for the sprog_Id and used for updating some parts.
// 20250427 LOE Now accepts .sql file if available.
// 20250428 LOE When converting from mysql to postgres, users have the option to fill in the auth details.
// 20250503 LOE - reordered mix-up text_id from tekster.csv in findtekst()
// 20250504 LOE Updated to allow for mysql db conversion to psql, default texts if tekster table not found yet; must backup first
// 20250511 LOE Various changes to ehance user's experience
// 20260127 LOE Updated migrateMySQLToPostgreSQL for some isolated fixes.
// 20260129 PHR Added some str_replace  and a call to connect.php before lookup in 'regnskab'
// 20260702 CX/PHR Close target PostgreSQL connection and terminate active sessions before DROP DATABASE in restore
// 20261015 CDX/PHR Copy the restored tenant version to the master database before the next login.
// 20261027 CL/NTR Catch restore failures at both call sites and show them to the admin instead of a blank page.
// 20261008 SS Restore (settings decision 18): online.php always runs; ?db= needs a Saldi-admin session with access to that ledger and an existing database (none is created), else 403.
//             Only the file uploaded in the same request is restored, after a required confirmation and a CSRF check; no file path is taken from POST and the work folder is removed when the request ends.
//             Shell arguments are escaped and database passwords passed through the environment.
//             The MySQL migration is shown and accepted only for a Saldi-admin with the admin right on an empty ledger, with password and audit log.
//             Texts 6930-6943.
// 20261031 CL/ASR The 20261027 failure handling sits in restore_handle_upload(), the only restore() caller in the single-request flow.
//                 The failure text no longer says the backup files are kept, as the per-request work folder is removed when the request ends.
// 20261102 CL/ASR In a ledger (not the admin panel's ?db=) the head of every settings page: the shared Tilbage button top-left above the title (settings_back_button(), css/saldi-kit.css) back to Indstillinger > Sikkerhedskopi, and the title on the page head (page_bar), instead of the bar with Luk to the old main menu.
//                 A popup window (popup=1) is closed, as before; Alt+L kept.
//                 The admin panel's restore keeps its old bar.
// 20261102 CL/ASR In a ledger the standard settings layout (settings_layout_open(), includes/settings/layout.php; owner, 2 Nov 2026: every page reached from Indstillinger keeps the left menu).
//                 The left menu of Finans with Sikkerhedskopi marked, Tilbage above the title back to Sikkerhedskopi, the breadcrumb Indstillinger / Finans / Sikkerhedskopi / this page.
//                 A popup window (popup=1) has no menu and Tilbage closes it, as before; the upload form keeps popup=1, so the window stays without the menu after a failed upload.
//                 The div that centres the page's content opens inside the content column; the restore itself is unchanged, and the admin panel's restore (?db=) keeps its old bar.
// 20261102 CL/ASR Review: a file that is not a Saldi backup returns to backup.php with popup=1 in a popup window, so the window stays without the settings menu.
// 20261104 CL/ASR Two-step restore (owner, 4 Nov 2026: keep the safe handling, but show company and date after the upload with a final confirmation).
//                 The upload is checked as before and kept in a work folder of its own outside the web root (sys_get_temp_dir(), private to the web server, saldi_restore_<db>_<32 hex>, 0700), known only to the session with a single-use token; no path is taken from the request.
//                 The confirmation shows the ledger, the backup's company, date and time, Saldi version, file name and size, warns when the backup belongs to another company or has no company and date, and takes the required checkbox with Gendan or Afbryd.
//                 Gendan restores once, with a valid CSRF token, this ledger's token from the session younger than 30 minutes and the checkbox; Afbryd and expiry remove the folder, and every visit removes expired folders.
//                 The upload, the confirmation and the cancel are audit-logged; the MySQL migration in the admin panel stays one step.
//                 Texts 7808-7819.
// 20261104 CL/ASR QA round 3: an archive with anything but plain files and folders (a link, a device) is refused before and after it is unpacked, and restore() writes its converted dump to a new file with a random name chosen by the server, created only when no file or link has that name.
//                 A file that is not a PostgreSQL dump, or an archive of an unknown type, is refused at the upload with text 7820 instead of reaching the confirmation.
//                 A successful restore writes its own audit row (restore_done) to the restored ledger, whose earlier rows the backup replaced, and the login page says the ledger was restored and from when (texts 7821-7822).
//                 Work folders are also cleaned up from Sikkerhedskopi and at logout (includes/restorePending.php), a refused confirmation removes its pending upload, and the admin panel's audit rows no longer carry the master database's user id.
//                 The warning about another company compares the backup's ledger name and says so (text 7815), the English sentence loses its double full stop, and the card uses the page's font with the checkbox on the line of its label.

@session_start();
$s_id=session_id();
ini_set('display_errors',0);
ob_start();

include("../includes/connect.php");
include("../includes/std_func.php");

$title=findtekst('1247|Indlæs sikkerhedskopi', $sprog_id);
$adminRestore = isset($_GET['db']) && $_GET['db'] !== '';
// An admin-panel session lives in the master database, where online.php closes the window when $modulnr is set.
$modulnr = $adminRestore ? NULL : 11;
$permission_key = $adminRestore ? 'any' : 'system.backup.restore'; // 20260916 Sawaneh phase 3: restore has its own (dangerous) key; the admin panel is checked below
$permission_level = 'write';
$css="../css/standard.css";
$backupdate=$backupdb=$backupver=$backupnavn=$filnavn=$menu=$regnskab=$timezone=$popup=NULL;
$tmpDb = NULL;
$adminRegnskabId = 0;
$restoreAdmin = NULL;
$restoreKeepDirs = array(); // pending work folders that stay after the request (restore_remove_dir_unless_kept())
include_once("../includes/restorePending.php"); // RESTORE_PENDING_TTL (30 minutes) and the clean-up of the work folders, shared with backup.php and logud.php

include("../includes/online.php");

if ($adminRestore) {
	$tmpDb = (string) $_GET['db'];
	$r = NULL;
	if ($db === $sqdb && $tmpDb !== $sqdb && preg_match('/^[A-Za-z0-9_]+$/', $tmpDb)) {
		$r = db_fetch_array(db_select("select id, regnskab from regnskab where db = '" . db_escape_string($tmpDb) . "'", __FILE__ . " linje " . __LINE__));
	}
	$restoreAdmin = $r ? restore_admin_rights((int) $r['id']) : NULL;
	if (!$restoreAdmin) {
		restore_deny(findtekst('6932|Adgang nægtet', $sprog_id));
	}
	if (!db_exists($tmpDb)) {
		restore_deny(findtekst('6933|Regnskabets database findes ikke. Opret regnskabet, før en sikkerhedskopi indlæses.', $sprog_id));
	}
	$adminRegnskabId = (int) $r['id'];
	$regnskab = $r['regnskab'];
	$db = $tmpDb;
	$connection = db_connect($sqhost, $squser, $sqpass, $db, __FILE__ . " linje " . __LINE__);
}
ob_end_flush();
if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

?>
<script LANGUAGE="JavaScript">
<!--

function confirmSubmit(messageProvider) {
    var message = typeof messageProvider === 'function' ? messageProvider() : messageProvider;
    var agree = confirm(message);
    return agree;
}

// -->
</script>
<?php
if(isset($_COOKIE['languageId'])){
	$sprog_id = $_COOKIE['languageId'];
}
 
 

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";
$formAction = $adminRestore ? "restore.php?db=" . rawurlencode($tmpDb) : "restore.php" . (!empty($_GET['popup']) ? "?popup=1" : ""); // a popup window stays without the settings menu

if (!file_exists("../temp/$db")) mkdir("../temp/$db", 0775);

#####################
$translations = [
    2422 => [
        1 => "Du er ved at overskrive dit regnskab",
        2 => "You are overwriting your account.",
        3 => "Du overskriver kontoen din."
    ],
    2423 => [
        1 => "med en sikkerhedskopi af regnskabet",
        2 => "with a backup copy of the accounts",
        3 => "med en sikkerhetskopi av kontoene"
    ],
    2424 => [
        1 => "fra den",
        2 => "from the",
        3 => "fra den"
    ],
    2425 => [
        1 => "Bemærk at alle brugere skal være logget ud",
        2 => "Please note that all users must be logged out.",
        3 => "Vær oppmerksom på at alle brukere må være logget ut."
    ],
    2426 => [
        1 => "med en sikkerhedskopi fra den",
        2 => "with a backup copy from the",
        3 => "med en sikkerhetskopi fra"
    ],
    2427 => [
        1 => "Der er sket en fejl under hentningen - prøv venligst igen.",
        2 => "An error occurred during the download - please try again.",
        3 => "Det oppsto en feil under nedlastingen – prøv på nytt."
	],
	1360 => [
		1 => "Indlæs",
		2 => "Load",
		3 => "Laste"
	],
	1364 => [
		1 => "V&aelig;lg datafil",
		2 => "Select data file",
		3 => "Velg datafil"
	]
	
];


####################

include("../includes/topline_settings.php");

if ($menu!='T' && !$adminRestore) {
	// In a ledger the standard settings layout (includes/settings/layout.php): Tilbage above the title back to Sikkerhedskopi, the left
	// menu of Finans with Sikkerhedskopi marked. A popup window (popup=1) gets no menu and Tilbage closes it.
	include_once("../includes/settings/layout.php");
	settings_layout_open(!empty($_GET['popup']) ? array('back' => '../includes/luk.php') : array());
}
print "<div align=\"center\">";
if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\"><a href=backup.php accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst('30|Tilbage', $sprog_id)."</a></div>";     
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
	print "<div id=\"leftmenuholder\">";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<div class='divSys'>";
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTableSys\"><tbody>"; # -> 1
} elseif (!$adminRestore) {
	print "<table width='100%' border='0' cellspacing='0' cellpadding='0'><tbody>";
} elseif ($menu=='S') {
	print "<table width='100%' height='30%' border='0' cellspacing='0' cellpadding='0'><tbody>";
	print "<tr><td height = '25' align='center' valign='top'>";
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>";

	print "<td width='10%'><a href='$returside' accesskey=L>";
	print "<button style='$buttonStyle; width:100%'onMouseOver=\"this.style.cursor='pointer'\">".findtekst('2172|Luk', $sprog_id)." </button></a></td>";

	print "<td width='80%' style='$topStyle' align='center'>".findtekst('1247|Indlæs sikkerhedskopi', $sprog_id)."</td>";

	print "<td width='10%' style='$topStyle' align='center'<br></td>";
	print "</tbody></table>";
	print "</td></tr>";
} else {
	print "<table width=\"100%\" height=\"30%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=\"$returside\" accesskey=L>".findtekst('2172|Luk', $sprog_id)."</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">".findtekst('1247|Indlæs sikkerhedskopi', $sprog_id)."</td>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
}
$restoreMsg = '';
$restoreMsgOk = false;
$restoreRan = false;
$restoreConfirm = NULL; // the pending upload whose confirmation is shown
restore_pending_housekeeping($db);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!$_POST && !$_FILES && (int) ifset($_SERVER, 'CONTENT_LENGTH', 0) > 0) {
		$restoreMsg = 'Filen er for stor - Kontroller upload_max_filesize i php.ini';
	} elseif (!hash_equals((string) $_SESSION['csrf_token'], (string) ifset($_POST, 'csrf_token', ''))) {
		$restoreMsg = findtekst('6940|Formularen er udløbet. Hent siden igen, og prøv igen.', $sprog_id);
	} elseif (isset($_POST['migrate'])) {
		list($restoreRan, $restoreMsg) = restore_handle_migration();
	} elseif (isset($_POST['restore_upload'])) {
		list($restoreMsg, $restoreConfirm) = restore_handle_upload();
	} elseif (isset($_POST['restore_cancel'])) {
		$restoreMsg = restore_handle_cancel();
		$restoreMsgOk = true;
	} elseif (isset($_POST['restore_confirm'])) {
		list($restoreRan, $restoreMsg, $restoreConfirm) = restore_handle_confirm();
	}
}
if ($restoreMsg !== '') {
	print "<tr><td align='center' style='color:" . ($restoreMsgOk ? 'var(--st-good, #15803d)' : 'red') . "; padding:8px'>$restoreMsg</td></tr>";
}
if ($restoreConfirm) {
	restore_render_confirm($formAction, $restoreConfirm);
} elseif (!$restoreRan) {
	upload($formAction);
	if (restore_migration_allowed()) renderRestoreForm($formAction);
}
print "</tbody></table></div>";
if (function_exists('settings_layout_close')) settings_layout_close(); // the layout is not opened for the admin panel's restore
################################################################################################################
function upload($formAction){
	global $sprog_id;
	global $connection;
	global $translations;
	global $db_type;
	global $buttonStyle;

	if ($db_type=='mysql' or $db_type=='mysqli') {
		echo '<span style="color:red;">This is not available yet!</span>';
		exit;
	}
	
	
	
		$textc      = $translations[2425][$sprog_id];
		$load       = $translations[1360][$sprog_id];
		$selectdfil = $translations[1364][$sprog_id];
	

	error_log("Textup: ".$load);
	

	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"" . htmlspecialchars($formAction, ENT_QUOTES) . "\" method=\"POST\">";
	print "<input type=\"hidden\" name=\"csrf_token\" value=\"" . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) . "\">";
#	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"99999999\">";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<tr><td width=100% align=center>".$textc."</td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<tr><td width=100% align=center><hr width=50%></td></tr>";
	print "<tr><td width=100% align=center></td></tr>";
	print "<tr><td width=100% align=center>\"".$selectdfil."\": <input class=\"inputbox\" NAME=\"uploadedfile\" type=\"file\" accept=\".sdat,.sql\" required></td></tr>";
	print "<tr><td width=100% align=center style=\"padding-top:6px\">".findtekst('7819|Når filen er indlæst, viser næste trin firma og dato i sikkerhedskopien. Intet bliver overskrevet, før du bekræfter.', $sprog_id)."</td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" name=\"restore_upload\" style=\"$buttonStyle\" value=\"".$load."\"></td></tr>";
	print "<tr><td></form></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
}

/**
 * Stops the request with 403 before anything of the page is sent.
 */
function restore_deny($txt) {
	while (ob_get_level()) ob_end_clean();
	http_response_code(403);
	print "<p>$txt</p>";
	exit;
}

/**
 * The admin-panel user's access to a ledger, from the master database ('admin,oprette,slette,ids' as vis_regnskaber.php reads it).
 *
 * @return array{backup: bool, id: int, kode: string}|null null when the user may not open the ledger
 */
function restore_admin_rights($regnskabId) {
	global $brugernavn;
	$r = db_fetch_array(db_select("select id, kode, rettigheder from brugere where brugernavn = '" . db_escape_string((string) $brugernavn) . "'", __FILE__ . " linje " . __LINE__));
	if (!$r) return NULL;
	list($admin, , , $tmp) = array_pad(explode(",", (string) $r['rettigheder'], 4), 4, '');
	if (!$admin && !in_array($regnskabId, array_map('trim', explode(",", $tmp)))) return NULL;
	return array('backup' => (bool) $admin, 'id' => (int) $r['id'], 'kode' => (string) $r['kode']);
}

/**
 * The admin-panel user's own password (master database, md5 legacy or saldikrypt).
 */
function restore_password_ok($typed) {
	global $restoreAdmin;
	$typed = (string) $typed;
	if ($typed === '' || !$restoreAdmin || $restoreAdmin['id'] <= 0 || $restoreAdmin['kode'] === '') return false;
	$stored = strtolower(trim($restoreAdmin['kode']));
	return hash_equals($stored, md5($typed)) || hash_equals($stored, saldikrypt($restoreAdmin['id'], $typed));
}

function restore_ledger_empty() {
	foreach (array('transaktioner', 'ordrer', 'kassekladde') as $table) {
		if (tbl_exists($table) && db_fetch_array(db_select("select 1 as found from $table limit 1", __FILE__ . " linje " . __LINE__))) return false;
	}
	return true;
}

/**
 * MySQL -> PostgreSQL migration: Saldi-admin session with the admin right (settings.backup there) on an empty ledger.
 */
function restore_migration_allowed() {
	global $adminRestore, $restoreAdmin, $db_type;
	return $adminRestore && !empty($restoreAdmin['backup']) && $db_type == 'postgresql' && restore_ledger_empty();
}

function restore_remove_dir($dir) {
	if ($dir && is_dir($dir)) system("rm -rf " . escapeshellarg($dir));
}

function restore_remove_dir_unless_kept($dir) {
	global $restoreKeepDirs;
	if (empty($restoreKeepDirs[$dir])) restore_remove_dir($dir);
}

function restore_inside($path, $dir) {
	$real = realpath($path);
	$base = realpath($dir);
	return $real && $base && strpos($real, $base . '/') === 0 && is_file($real);
}

/**
 * Takes the file uploaded in this request into a work folder of its own and finds the dump in it.
 * $pending (the two-step restore): the folder is made outside the web root in the system temp folder (private to the web
 * server), saldi_restore_<db>_<32 hex>, 0700 with files 0600, and kept for the confirmation once the caller has stored it
 * in the session; otherwise (the MySQL migration) it is under ../temp/<db>/ and removed when the request ends.
 *
 * @return array{error: string, file: string, dir: string, encode: ?string, dbtype: ?string, kind: string, info: bool,
 *               backupdate: string, backupdb: string, backupver: string, backupnavn: string, name: string, size: int}
 */
function restore_prepare_upload($db, $pending = false) {
	global $sprog_id;
	$res = array('error' => '', 'file' => '', 'dir' => '', 'encode' => NULL, 'dbtype' => NULL, 'kind' => '', 'info' => false,
		'backupdate' => '', 'backupdb' => '', 'backupver' => '', 'backupnavn' => '', 'name' => '', 'size' => 0);
	$up = if_array($_FILES, 'uploadedfile');
	$err = (int) ifset($up, 'error', UPLOAD_ERR_NO_FILE);
	if ($err === UPLOAD_ERR_INI_SIZE) $res['error'] = 'Filen er for stor - Kontroller upload_max_filesize i php.ini';
	elseif ($err === UPLOAD_ERR_FORM_SIZE) $res['error'] = 'Filen er for stor - er det en SALDI-sikkerhedskopi?';
	elseif ($err === UPLOAD_ERR_NO_FILE) $res['error'] = findtekst('1364|Vælg datafil', $sprog_id);
	elseif ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) ifset($up, 'tmp_name', ''))) $res['error'] = findtekst(2427, $sprog_id);
	if ($res['error'] !== '') return $res;

	$res['name'] = substr(basename((string) ifset($up, 'name', '')), 0, 200);
	$res['size'] = (int) ifset($up, 'size', 0);
	$extension = strtolower(pathinfo(basename((string) ifset($up, 'name', '')), PATHINFO_EXTENSION));
	if ($extension !== 'sdat' && $extension !== 'sql') {
		$res['error'] = 'Only .sdat or .sql files are allowed.';
		return $res;
	}
	if ($pending) {
		$tmp = realpath(sys_get_temp_dir());
		$workDir = $tmp ? $tmp . '/saldi_restore_' . restore_pending_db_part($db) . '_' . bin2hex(random_bytes(16)) : '';
	} else {
		$workDir = "../temp/$db/restore_" . bin2hex(random_bytes(8));
	}
	if ($workDir === '' || !mkdir($workDir, 0700)) {
		$res['error'] = findtekst(2427, $sprog_id);
		return $res;
	}
	chmod($workDir, 0700);
	$res['dir'] = realpath($workDir);
	// A pending folder stays only when the caller has stored it in the session ($restoreKeepDirs).
	register_shutdown_function($pending ? 'restore_remove_dir_unless_kept' : 'restore_remove_dir', $res['dir']);

	if ($extension == 'sql') {
		$file = "$workDir/upload.sql";
		if (!move_uploaded_file($up['tmp_name'], $file)) {
			$res['error'] = findtekst(2427, $sprog_id);
			return $res;
		}
	} else {
		$gzFile = "$workDir/restore.gz";
		if (!move_uploaded_file($up['tmp_name'], $gzFile)) {
			$res['error'] = findtekst(2427, $sprog_id);
			return $res;
		}
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mimeType = finfo_file($finfo, $gzFile);
		finfo_close($finfo);
		if ($mimeType == 'application/gzip' || $mimeType == 'application/x-gzip') {
			system("gunzip " . escapeshellarg($gzFile), $exitCode);
			if ($exitCode !== 0) error_log("Extraction failed with exit code $exitCode.");
		} elseif ($mimeType != 'application/x-tar') {
			error_log("restore.php: unsupported or unknown file type " . (string) $mimeType);
			$res['error'] = restore_not_backup_text();
			return $res;
		}
		$file = file_exists("$workDir/restore") ? "$workDir/restore" : $gzFile;
		// Only plain files and folders: a link in the archive could make a later write land outside the work folder.
		if (!restore_tar_plain($file)) {
			$res['error'] = restore_not_backup_text();
			restore_remove_dir($res['dir']);
			return $res;
		}
		system("/bin/tar -C " . escapeshellarg($workDir) . " -xf " . escapeshellarg($file));
		if (!restore_tree_plain($workDir)) {
			$res['error'] = restore_not_backup_text();
			restore_remove_dir($res['dir']);
			return $res;
		}
		$infofil = "$workDir/temp/backup.info";
		if (restore_inside($infofil, $workDir) && $fp = fopen($infofil, "r")) {
			$linje = trim((string) fgets($fp));
			fclose($fp);
			list($backupdate, $backupdb, $backupver, $backupnavn, $backup_encode, $backup_dbtype) = array_pad(explode(chr(9), $linje), 6, '');
			$backupdb = basename(trim($backupdb));
			$sqlFile = "$workDir/temp/$backupdb.sql";
			if ($backupdb === '' || $backupdb[0] === '.' || !restore_inside($sqlFile, $workDir)) {
				$res['error'] = findtekst(2427, $sprog_id);
				return $res;
			}
			$file = $sqlFile;
			$res['encode'] = trim($backup_encode);
			$res['dbtype'] = trim($backup_dbtype);
			$res['info'] = true;
			$res['backupdate'] = substr(trim($backupdate), 0, 40);
			$res['backupdb'] = substr($backupdb, 0, 100);
			$res['backupver'] = substr(trim($backupver), 0, 40);
			$res['backupnavn'] = substr(trim($backupnavn), 0, 200);
		}
	}
	if ($pending) restore_private_tree($workDir);
	$res['file'] = $file;
	$handle = fopen($file, 'r');
	$result = $handle ? trim((string) findDumpInFirstThreeLines($handle)) : '';
	if (stripos($result, 'MySQL dump') !== false) $res['kind'] = 'mysql';
	elseif (stripos($result, 'PostgreSQL') !== false) $res['kind'] = 'postgresql';
	return $res;
}

/**
 * Folders 0700 and files 0600 below $dir. An archive with links never gets here (restore_tar_plain(), restore_tree_plain());
 * a link is still skipped, never followed.
 */
function restore_private_tree($dir) {
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
	foreach ($it as $path => $info) {
		if (is_link($path)) continue;
		@chmod($path, is_dir($path) ? 0700 : 0600);
	}
}

/**
 * Whether every member of the tar archive is a plain file or a folder (tar -tv type '-' or 'd'): no symbolic or hard link,
 * device or fifo. A file tar cannot list is refused too.
 */
function restore_tar_plain($archive) {
	$out = array();
	$code = 1;
	exec("/bin/tar -tvf " . escapeshellarg((string) $archive) . " 2>/dev/null", $out, $code);
	if ($code !== 0 || !$out) return false;
	foreach ($out as $line) {
		$type = substr((string) $line, 0, 1);
		if ($type !== '-' && $type !== 'd') return false;
	}
	return true;
}

/**
 * Whether everything below $dir is a plain file or a folder, links included in the check (they are never followed).
 */
function restore_tree_plain($dir) {
	try {
		$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
		foreach ($it as $path => $info) {
			if (is_link($path) || (!is_dir($path) && !is_file($path))) return false;
		}
	} catch (Exception $e) {
		return false;
	}
	return true;
}

function restore_not_backup_text() {
	global $sprog_id;
	return findtekst('7820|Filen er ikke en SALDI-sikkerhedskopi. Intet er ændret.', $sprog_id);
}

/**
 * Step 1: checks the uploaded file and keeps it in a private work folder for the confirmation (nothing is restored yet).
 *
 * @return array{0: string, 1: ?array} a message, and the pending upload whose confirmation is shown
 */
function restore_handle_upload() {
	global $db, $sprog_id, $restoreKeepDirs;
	restore_pending_forget($db); // an earlier upload of this ledger that was not confirmed
	$up = restore_prepare_upload($db, true);
	if ($up['error'] !== '') return array($up['error'], NULL);
	if ($up['kind'] == 'mysql') {
		return array(findtekst('6934|Filen er en MySQL-sikkerhedskopi. Den kan kun indlæses som flytning fra MySQL i adminpanelet på et tomt regnskab.', $sprog_id), NULL);
	}
	if ($up['kind'] !== 'postgresql') {
		return array(restore_not_backup_text(), NULL);
	}
	$dir = realpath($up['dir']);
	$file = realpath($up['file']);
	if (!$dir || !$file || !restore_pending_dir_ok($dir, $db) || !restore_inside($file, $dir)) {
		return array(findtekst(2427, $sprog_id), NULL);
	}
	touch($dir); // the age of the pending upload counts from now
	$entry = array(
		'token' => bin2hex(random_bytes(16)),
		'dir' => $dir,
		'file' => $file,
		'encode' => $up['encode'],
		'dbtype' => $up['dbtype'],
		'kind' => $up['kind'],
		'info' => $up['info'],
		'backupnavn' => $up['backupnavn'],
		'backupdate' => $up['backupdate'],
		'backupver' => $up['backupver'],
		'backupdb' => $up['backupdb'],
		'name' => $up['name'],
		'size' => $up['size'],
		'created' => time(),
		'user' => restore_pending_user(),
	);
	$_SESSION['restore_pending'][$db] = $entry;
	$restoreKeepDirs[$dir] = true;
	restore_audit('setting.action', 'upload_prepared', $entry);
	return array('', $entry);
}

/**
 * Step 2, Gendan: restores the pending upload of this ledger once, with its single-use token and the checkbox.
 *
 * @return array{0: bool, 1: string, 2: ?array} whether a restore was run, a message, and the pending upload to show again
 */
function restore_handle_confirm() {
	global $db, $sprog_id;
	$p = restore_pending_get($db);
	$token = (string) ifset($_POST, 'restore_token', '');
	if (!$p || !preg_match('/^[0-9a-f]{32}$/', $token) || !hash_equals((string) $p['token'], $token)) {
		restore_audit('setting.action_refused', 'confirm_invalid_token', $p ? $p : array());
		restore_pending_forget($db); // a refused confirmation leaves no copy of the ledger behind
		return array(false, findtekst('7818|Bekræftelsen er ugyldig, udløbet eller allerede brugt. Intet er ændret. Indlæs sikkerhedskopien igen.', $sprog_id), NULL);
	}
	if (empty($_POST['confirm_restore'])) {
		return array(false, findtekst('6931|Sæt flueben for at bekræfte, at regnskabet må overskrives.', $sprog_id), $p);
	}
	// Single use: the token leaves the session before the restore starts, and the session is saved at once.
	unset($_SESSION['restore_pending'][$db]);
	if (empty($_SESSION['restore_pending'])) unset($_SESSION['restore_pending']);
	session_write_close();
	// The running restore's folder gets a name the housekeeping of other visits leaves alone; it is removed when the request ends.
	$runDir = dirname($p['dir']) . '/' . preg_replace('/^saldi_restore_/', 'saldi_restoring_', basename($p['dir']));
	if (!@rename($p['dir'], $runDir)) {
		restore_remove_dir($p['dir']);
		return array(false, findtekst(2427, $sprog_id), NULL);
	}
	register_shutdown_function('restore_remove_dir', $runDir);
	$file = $runDir . substr($p['file'], strlen($p['dir']));
	if (!restore_inside($file, $runDir)) {
		return array(false, findtekst(2427, $sprog_id), NULL);
	}
	restore_audit('setting.action', 'confirm_restore', $p);
	ignore_user_abort(true); // a restore that has started runs to its end, also when the browser leaves the page
	try {
		$done = restore($file, $p['encode'], $p['dbtype'], (string) ifset($p, 'backupdate', ''));
	} catch (RuntimeException $e) {
		// The ledger is already dropped and the connection may point anywhere: show the failure, not the forms again.
		printRestoreFailure($e);
		return array(true, '', NULL);
	}
	if ($done === NULL) {
		return array(false, findtekst(2427, $sprog_id), NULL); // nothing was changed
	}
	if ($done === true) {
		restore_audit_done($p);
	}
	return array(true, '', NULL);
}

/**
 * Step 2, Afbryd: removes the pending upload of this ledger.
 *
 * @return string the message
 */
function restore_handle_cancel() {
	global $db, $sprog_id;
	$p = restore_pending_get($db);
	$token = (string) ifset($_POST, 'restore_token', '');
	if ($p && preg_match('/^[0-9a-f]{32}$/', $token) && hash_equals((string) $p['token'], $token)) {
		restore_audit('setting.action', 'cancel', $p);
		restore_pending_forget($db);
	}
	return findtekst('7817|Gendannelsen er afbrudt. Intet er ændret.', $sprog_id);
}

/**
 * Who uploaded: the pending upload is only valid for the same user on the same path (ledger or admin panel).
 */
function restore_pending_user() {
	global $brugernavn, $adminRestore;
	return ($adminRestore ? 'admin:' : 'ledger:') . (string) $brugernavn;
}

/**
 * Whether $dir is a pending work folder of this ledger: directly in the system temp folder, named saldi_restore_<db>_<32 hex>.
 */
function restore_pending_dir_ok($dir, $db) {
	$tmp = realpath(sys_get_temp_dir());
	$real = realpath((string) $dir);
	return $tmp && $real && $real === (string) $dir && dirname($real) === $tmp && !is_link($real) && is_dir($real)
		&& preg_match('/^saldi_restore_' . preg_quote(restore_pending_db_part($db), '/') . '_[0-9a-f]{32}$/', basename($real));
}

function restore_pending_db_part($db) {
	return preg_replace('/[^A-Za-z0-9_]/', '', (string) $db);
}

/**
 * The pending upload of this ledger from the session, or NULL; an invalid or expired one is removed.
 */
function restore_pending_get($db) {
	$p = isset($_SESSION['restore_pending'][$db]) ? $_SESSION['restore_pending'][$db] : NULL;
	if (!is_array($p)) return NULL;
	$age = time() - (int) ifset($p, 'created', 0);
	$ok = preg_match('/^[0-9a-f]{32}$/', (string) ifset($p, 'token', '')) && $age >= 0 && $age <= RESTORE_PENDING_TTL
		&& (string) ifset($p, 'user', '') === restore_pending_user()
		&& restore_pending_dir_ok(ifset($p, 'dir', ''), $db) && restore_inside(ifset($p, 'file', ''), $p['dir'])
		&& realpath($p['file']) === $p['file'];
	if (!$ok) {
		restore_pending_forget($db);
		return NULL;
	}
	return $p;
}

/**
 * Removes the pending upload of this ledger: its work folder (only a folder of the pending pattern) and the session entry.
 */
function restore_pending_forget($db) {
	if (isset($_SESSION['restore_pending'][$db])) {
		$dir = (string) ifset($_SESSION['restore_pending'][$db], 'dir', '');
		if ($dir !== '' && restore_pending_dir_ok($dir, $db)) restore_remove_dir($dir);
		unset($_SESSION['restore_pending'][$db]);
	}
	if (isset($_SESSION['restore_pending']) && !$_SESSION['restore_pending']) unset($_SESSION['restore_pending']);
}

/**
 * Every visit: removes pending work folders older than 30 minutes (and folders of a restore that never ended, after a day),
 * and this ledger's session entry when its folder is gone or expired.
 */
function restore_pending_housekeeping($db) {
	restore_pending_sweep(); // includes/restorePending.php, also run from backup.php and logud.php
	restore_pending_get($db);
}

/**
 * Audit-log a step of the two-step restore (objekt import_export.backup.restore), and the server log.
 */
function restore_audit($handling, $step, array $p) {
	global $db, $adminRestore, $bruger_id;
	$detail = restore_audit_detail($step, $p);
	// In the admin panel the user id is the master database's, not one of this ledger's users: the name alone is kept.
	$ownId = $bruger_id;
	if ($adminRestore) $bruger_id = 0;
	audit_log($handling, $detail, 'indstilling', 'import_export.backup.restore');
	$bruger_id = $ownId;
	error_log("restore.php import_export.backup.restore $handling $db $detail");
}

function restore_audit_detail($step, array $p) {
	return (string) json_encode(array(
		'step' => $step,
		'file' => (string) ifset($p, 'name', ''),
		'size' => (int) ifset($p, 'size', 0),
		'backup_company' => (string) ifset($p, 'backupnavn', ''),
		'backup_date' => (string) ifset($p, 'backupdate', ''),
		'backup_db' => (string) ifset($p, 'backupdb', ''),
		'backup_version' => (string) ifset($p, 'backupver', ''),
	), JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
}

/**
 * After a successful restore: the restore in the restored ledger's own audit log, whose earlier rows (the upload and the
 * confirmation included) the backup replaced, and in the server log. The table and its columns are read from the restored
 * database (a backup may be older than them), the user id is that of the same login name there (0 in the admin panel).
 */
function restore_audit_done(array $p) {
	global $connection, $db, $sqhost, $squser, $sqpass, $adminRestore;
	$detail = restore_audit_detail('restore_done', $p);
	error_log("restore.php import_export.backup.restore setting.action $db $detail");
	$connection = db_connect($sqhost, $squser, $sqpass, $db, __FILE__ . " linje " . __LINE__);
	if (!$connection) return;
	$cols = array();
	$q = db_select("select column_name from information_schema.columns where table_name = 'audit_log' and table_schema = current_schema()", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $cols[(string) $r['column_name']] = true;
	if (!empty($cols['handling']) && !empty($cols['detaljer']) && !empty($cols['bruger_id']) && !empty($cols['brugernavn'])) {
		$name = function_exists('audit_user_name') ? audit_user_name() : '';
		$uid = 0;
		if (!$adminRestore && $name !== '') {
			$r = db_fetch_array(db_select("select id from brugere where brugernavn = '" . db_escape_string($name) . "'", __FILE__ . " linje " . __LINE__));
			if ($r) $uid = (int) $r['id'];
		}
		$values = array('bruger_id' => (string) $uid, 'brugernavn' => $name, 'handling' => 'setting.action', 'detaljer' => $detail);
		if (!empty($cols['ip'])) $values['ip'] = isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : '';
		if (!empty($cols['objekt_type']) && !empty($cols['objekt_id'])) {
			$values['objekt_type'] = 'indstilling';
			$values['objekt_id'] = 'import_export.backup.restore';
		}
		if (!empty($cols['kilde'])) $values['kilde'] = 'ui';
		$sql = array();
		foreach ($values as $col => $value) $sql[] = ($col === 'bruger_id') ? (string) (int) $value : "'" . db_escape_string($value) . "'";
		db_modify("insert into audit_log (" . implode(', ', array_keys($values)) . ") values (" . implode(', ', $sql) . ")", __FILE__ . " linje " . __LINE__);
	}
	db_close($connection);
}

/**
 * Text from the backup (untrusted) in the page's character set; escape it with restore_h() before printing.
 */
function restore_display($s) {
	global $charset;
	$s = (string) $s;
	if ($charset === 'UTF-8') {
		if (!mb_check_encoding($s, 'UTF-8')) $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-15');
	} elseif (preg_match('/[\x80-\xff]/', $s) && mb_check_encoding($s, 'UTF-8')) {
		$s = mb_convert_encoding($s, 'ISO-8859-15', 'UTF-8');
	}
	return $s;
}

function restore_h($s) {
	global $charset;
	return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, $charset ? $charset : 'UTF-8');
}

/**
 * The backup's time (backup.info: YYYYMMDD-HHMM or YYYYMMDDHHMM) as DD-MM-YYYY kl. HH:MM; anything else as it is.
 */
function restore_backup_when($backupdate) {
	global $sprog_id;
	$backupdate = trim((string) $backupdate);
	if (preg_match('/^(\d{4})(\d{2})(\d{2})\D?(\d{2})(\d{2})/', $backupdate, $m)) {
		return "$m[3]-$m[2]-$m[1] " . findtekst('7813|kl.', $sprog_id) . " $m[4]:$m[5]";
	}
	return $backupdate;
}

function restore_human_size($bytes) {
	$bytes = (int) $bytes;
	if ($bytes < 1024) return $bytes . ' B';
	if ($bytes < 1048576) return number_format($bytes / 1024, 1, ',', '.') . ' KB';
	if ($bytes < 1073741824) return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
	return number_format($bytes / 1073741824, 1, ',', '.') . ' GB';
}

/**
 * Lower case without spaces, to compare the backup's company with the ledger's name.
 */
function restore_name_key($s) {
	$s = (string) $s;
	if (!mb_check_encoding($s, 'UTF-8')) $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-15');
	return mb_strtolower((string) preg_replace('/\s+/u', '', html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8')), 'UTF-8');
}

/**
 * Step 2: what will happen (ledger, the backup's company, date, version, file), the checkbox, Gendan and Afbryd.
 */
function restore_render_confirm($formAction, array $p) {
	global $sprog_id, $regnskab, $adminRestore, $buttonStyle;
	$ledger = trim((string) $regnskab);
	$company = trim(restore_display($p['backupnavn']));
	$when = restore_backup_when($p['backupdate']);
	$version = trim(restore_display($p['backupver']));
	$otherCompany = $company !== '' && restore_name_key($company) !== restore_name_key($ledger);
	$box = 'margin:0 0 12px;padding:8px 12px;border:1px solid var(--st-border, #d6dbe3);border-radius:6px;background:var(--st-surface, #fff);color:var(--st-text, #1f2937);line-height:1.45';
	$th = "style='text-align:left;font-weight:500;color:var(--st-text-2, #555);padding:4px 16px 4px 0;white-space:nowrap;vertical-align:top'";
	$td = "style='padding:4px 0;overflow-wrap:anywhere'";

	print "<tr><td align='center'>";
	print "<div class='restore-confirm' style='font-family:inherit;max-width:660px;margin:16px auto;text-align:left;padding:12px 16px;border:1px solid var(--st-border, #d6dbe3);border-left:4px solid var(--st-bad, #c2410c);border-radius:6px;background:var(--st-surface, #fff);color:var(--st-text, #1f2937)'>";
	print "<h2 style='font-family:inherit;margin:0 0 8px;font-size:1.05rem;color:var(--st-bad, #c2410c)'>" . findtekst('7808|Bekræft gendannelse', $sprog_id) . "</h2>";
	print "<p style='margin:0 0 10px'>" . findtekst('7809|Filen er kontrolleret, men intet er ændret endnu. Kontrollér firma og dato, før du gendanner.', $sprog_id) . "</p>";

	$sentence = rtrim(findtekst('2422|Du er ved at overskrive dit regnskab', $sprog_id), '.') . ": <b>" . restore_h($ledger) . "</b>";
	if ($company !== '') {
		$sentence .= "<br>" . findtekst('2423|med en sikkerhedskopi af regnskabet', $sprog_id) . ": <b>" . restore_h($company) . "</b>";
		if ($when !== '') $sentence .= " " . findtekst('2424|fra den', $sprog_id) . " <b>" . restore_h($when) . "</b>";
	} elseif ($when !== '') {
		$sentence .= "<br>" . findtekst('2426|med en sikkerhedskopi fra den', $sprog_id) . " <b>" . restore_h($when) . "</b>";
	}
	print "<p style='margin:0 0 12px'>$sentence.</p>";

	if ($otherCompany) {
		$names = array('<b>' . restore_h($company) . '</b>', '<b>' . restore_h($ledger) . '</b>');
		$warn = preg_replace_callback('/%s/', function () use (&$names) { return $names ? array_shift($names) : ''; }, findtekst('7815|Sikkerhedskopien er taget af regnskabet %s, men dette regnskab hedder %s. Kontrollér, at det er det rigtige firma, før du gendanner.', $sprog_id)); // a translation with more or fewer %s never breaks the page
		print "<div role='alert' class='restore-other-company' style='$box;border-color:var(--st-bad, #c2410c);border-left:4px solid var(--st-bad, #c2410c);background:var(--st-bad-bg, #fdecec);font-weight:600'>$warn</div>";
	}
	if (empty($p['info'])) {
		print "<div class='restore-no-info' style='$box;border-left:4px solid var(--st-warn, #D9822B)'>" . findtekst('7816|Filen indeholder ingen oplysninger om firma og dato. Kontrollér, at det er den rigtige fil, før du gendanner.', $sprog_id) . "</div>";
	}

	print "<table class='restore-facts' style='border-collapse:collapse;margin:0 0 12px'><tbody>";
	print "<tr><th scope='row' $th>" . findtekst('7810|Regnskab, der overskrives', $sprog_id) . "</th><td $td><b>" . restore_h($ledger) . "</b></td></tr>";
	if (!empty($p['info'])) {
		print "<tr><th scope='row' $th>" . findtekst('7811|Firma i sikkerhedskopien', $sprog_id) . "</th><td $td><b>" . restore_h($company !== '' ? $company : '-') . "</b></td></tr>";
		print "<tr><th scope='row' $th>" . findtekst('7812|Sikkerhedskopien er taget', $sprog_id) . "</th><td $td><b>" . restore_h($when !== '' ? $when : '-') . "</b></td></tr>";
		if ($version !== '') print "<tr><th scope='row' $th>" . findtekst('7814|Saldi-version', $sprog_id) . "</th><td $td>" . restore_h($version) . "</td></tr>";
	}
	print "<tr><th scope='row' $th>" . findtekst('3284|Fil', $sprog_id) . "</th><td $td>" . restore_h(restore_display($p['name'])) . "</td></tr>";
	print "<tr><th scope='row' $th>" . findtekst('2030|Størrelse', $sprog_id) . "</th><td $td>" . restore_h(restore_human_size($p['size'])) . "</td></tr>";
	print "</tbody></table>";

	print "<form method='post' action='" . restore_h($formAction) . "' autocomplete='off'>";
	print "<input type='hidden' name='csrf_token' value='" . restore_h($_SESSION['csrf_token']) . "'>";
	print "<input type='hidden' name='restore_token' value='" . restore_h($p['token']) . "'>";
	print "<p style='margin:0 0 6px'>" . rtrim(findtekst('2425|Bemærk at alle brugere skal være logget ud', $sprog_id), '.') . ".</p>";
	print "<p style='margin:0 0 12px'><label style='display:flex;align-items:center;gap:6px'><input type='checkbox' name='confirm_restore' value='1' required style='margin:0'> " . findtekst('6930|Jeg forstår, at regnskabet bliver overskrevet med sikkerhedskopien.', $sprog_id) . "</label></p>";
	$restoreStyle = $adminRestore ? " style='" . restore_h($buttonStyle) . "background-color:#c2410c;color:#fff;padding:4px 12px'" : '';
	$cancelStyle = $adminRestore ? " style='" . restore_h($buttonStyle) . "padding:4px 12px'" : '';
	print "<p style='margin:0;display:flex;gap:8px'>";
	print "<button type='submit' name='restore_confirm' value='1' class='st-btn st-btn-danger-solid'$restoreStyle>" . findtekst('5339|Gendan', $sprog_id) . "</button>";
	print "<button type='submit' name='restore_cancel' value='1' class='st-btn' formnovalidate$cancelStyle>" . findtekst('81|Afbryd', $sprog_id) . "</button>";
	print "</p></form>";
	print "</div></td></tr>";
}

/**
 * @return array{0: bool, 1: string} whether the migration was run, and a message
 */
function restore_handle_migration() {
	global $db, $sprog_id, $sqhost, $squser, $sqpass;
	$audit = 'import_export.backup.migrate_mysql';
	if (!restore_migration_allowed()) {
		return array(false, findtekst('6939|Flytning fra MySQL kan kun ske fra adminpanelet på et tomt regnskab (uden posteringer, ordrer og kassekladdelinjer).', $sprog_id));
	}
	if (empty($_POST['confirm_restore'])) {
		return array(false, findtekst('6931|Sæt flueben for at bekræfte, at regnskabet må overskrives.', $sprog_id));
	}
	if (!restore_password_ok(ifset($_POST, 'password', ''))) {
		audit_log('setting.action_refused', 'password', 'indstilling', $audit);
		return array(false, findtekst('6938|Forkert adgangskode - intet er ændret.', $sprog_id));
	}
	$mysqlDb = trim((string) ifset($_POST, 'mysql_db', ''));
	$mysqlUser = trim((string) ifset($_POST, 'mysql_user', ''));
	$mysqlPass = (string) ifset($_POST, 'mysql_pass', '');
	if (!preg_match('/^[A-Za-z0-9_]+$/', $mysqlDb) || $mysqlUser === '') {
		return array(false, findtekst('6941|Udfyld MySQL-database (kun bogstaver, tal og _) og MySQL-bruger.', $sprog_id));
	}
	$up = restore_prepare_upload($db);
	if ($up['error'] !== '') return array(false, $up['error']);
	if ($up['kind'] != 'mysql') return array(false, findtekst('6942|Filen er ikke en MySQL-sikkerhedskopi.', $sprog_id));
	$detail = json_encode(array('mysql_db' => $mysqlDb, 'mysql_user' => $mysqlUser, 'file' => basename((string) $_FILES['uploadedfile']['name'])), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
	audit_log('setting.action', $detail, 'indstilling', $audit);
	error_log("restore.php $audit $db $detail");
	migrateMySQLToPostgreSQL($sqhost, $squser, $sqpass, $db, $sqhost, $mysqlUser, $mysqlPass, $mysqlDb, $up['file']);
	return array(true, '');
}

function restore($filnavn,$backup_encode,$backup_dbtype,$backupdate=''){

	global $connection;
	global $s_id;
	global $regnskab;
	global $db;
	global $sqdb;
	global $squser;
	global $sqpass;
	global $sqhost;
	global $db_encode;
	global $db_type;
	global $charset;
	global $sprog_id;
	
	if (!$db_encode) $db_encode="LATIN9";
	if (!$backup_encode) $backup_encode="UTF8";
	if (!$db_type) $db_type="postgresql";
	if (!$backup_dbtype) $backup_dbtype="postgresql";
	
	// The converted dump gets a new random name chosen here, in a folder that is not a link, and is only created when nothing
	// (no file, no link) has that name ('x'; PHP resolves links itself, so they are checked first): nothing in the archive
	// decides where it is written. NULL back when it cannot be made; nothing has been changed then.
	$filnavn2=dirname($filnavn)."/restore_".bin2hex(random_bytes(16)).".sql";

	$restore="";
	$fp=fopen("$filnavn","r");
	$fp2=($fp && !is_link(dirname($filnavn2)) && is_dir(dirname($filnavn2)) && !is_link($filnavn2) && !file_exists($filnavn2)) ? @fopen("$filnavn2","x") : false;
	if (!$fp || !$fp2) {
		if ($fp) fclose($fp);
		error_log("restore.php: the restore work file could not be created for $db");
		return NULL;
	}
	
	
	if ($fp) {
		while (!feof($fp)) {
			if ($linje=fgets($fp)) {
					if ($db_type=='mysql' or $db_type=='mysqli') {
					if (strpos($linje, "MySQL dump")) $dump = "OK";
				} elseif (strpos($linje, "PostgreSQL database dump")) $dump = "OK";
				if (strpos(strtolower($linje), "drop database")) {
					$restore = "NUL";
				}
				if (strpos(strtolower($linje), "drop database")) {
					$restore = "NUL";
				}
				if (strpos(strtolower($linje), "create database")) {
					$restore = "NUL";
				}
				if (strpos(strtolower($linje), "\\connect")) {
					$restore = "NUL";
				}
				if ($backup_encode!=$db_encode) {
					if ($db_encode=="UTF8" && $backup_encode=="LATIN9") {
						$linje=str_replace("SET client_encoding = 'LATIN9';","SET client_encoding = 'UTF8';",$linje);
						$ny_linje=utf8_encode($linje);
					}	elseif ($db_encode=="LATIN9" && $backup_encode=="UTF8") {
						$linje=str_replace("SET client_encoding = 'UTF8';","SET client_encoding = 'LATIN9';",$linje);
						$ny_linje=utf8_decode($linje);
					} else {
						$restore = "NUL";
					}
				} else $ny_linje=$linje;
			} else $ny_linje='';
			fwrite($fp2,"$ny_linje"); 
		}	
		if (!$restore && $dump) $restore="OK";
	} else echo "$filnavn ikke fundet";
	fclose($fp);
	fclose($fp2);
	if ($restore=='OK') {
		if ($db_type=='mysql') {
			mysql_select_db("$sqdb");
		} else if ($db_type=='mysqli') { #RG_mysqli
			$connection = db_connect ("$sqhost", "$squser", "$sqpass", "$sqdb");
			mysqli_select_db($connection, $sqdb);
		} else {
			db_close($connection);
			$connection = db_connect($sqhost, $squser, $sqpass, $sqdb, __FILE__ . " linje " . __LINE__);
		}

		// else {
		// 	db_close($connection);
		// }
		db_modify("delete from online where db='$db'",__FILE__ . " linje " . __LINE__);
		db_modify("update regnskab set version = '' where db='$db'",__FILE__ . " linje " . __LINE__);
		if ($db_type=='postgresql') {
			$escapedDb = pg_escape_string($connection, $db);
			db_select("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname='$escapedDb' AND pid <> pg_backend_pid()", __FILE__ . " linje " . __LINE__);
		}
		db_modify("DROP DATABASE $db",__FILE__ . " linje " . __LINE__);
		db_create($db);
		print "<!-- Saldi-kommentar for at skjule uddata til siden \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
		$mysql = $psql = NULL;
		$restoreExitCode = null;
		if (substr($db_type,0,5)=='mysql') {
			if (file_exists("/usr/bin/mysql")) $mysql = "/usr/bin/mysql";
			elseif (file_exists("/bin/mysql")) $mysql = "/usr/mysql";
			else echo "mysql not found<br>";
			if ($mysql) {
				putenv("MYSQL_PWD=$sqpass");
				system(escapeshellarg($mysql) . " -u " . escapeshellarg($squser) . " -h " . escapeshellarg($sqhost) . " " . escapeshellarg($db) . " < " . escapeshellarg($filnavn2), $restoreExitCode);
				putenv("MYSQL_PWD");
			}
		} else {
			if (file_exists("/usr/bin/psql")) $psql = "/usr/bin/psql";
			elseif (file_exists("/bin/psql")) $psql = "/usr/psql";
			else echo "psql not found<br>";
			if ($psql) {
				putenv("PGPASSWORD=$sqpass");
				system(escapeshellarg($psql) . " -h " . escapeshellarg($sqhost) . " -U " . escapeshellarg($squser) . " " . escapeshellarg($db) . " < " . escapeshellarg($filnavn2), $restoreExitCode);
				putenv("PGPASSWORD");
			}
		}
		if ($restoreExitCode !== 0) {
			throw new RuntimeException('Database restore failed. The existing data has already been replaced.');
		}
		// Read the version from the imported data, not the running application or old tenant.
		db_close($connection);
		$connection = db_connect($sqhost, $squser, $sqpass, $db, __FILE__ . ' linje ' . __LINE__);
		if ($db_type === 'mysql') {
			mysql_select_db($db);
		} elseif ($db_type === 'mysqli') {
			mysqli_select_db($connection, $db);
		}
		$restoredVersionRow = db_fetch_array(db_select("SELECT box1 FROM grupper WHERE art = 'VE'", __FILE__ . ' linje ' . __LINE__));
		$restoredVersion = trim((string) ifset($restoredVersionRow, 'box1', ''));
		if ($restoredVersion === '') {
			throw new RuntimeException('The restored database has no version. The existing data has already been replaced.');
		}
		db_close($connection);
		$connection = db_connect($sqhost, $squser, $sqpass, $sqdb, __FILE__ . ' linje ' . __LINE__);
		if ($db_type === 'mysql') {
			mysql_select_db($sqdb);
		} elseif ($db_type === 'mysqli') {
			mysqli_select_db($connection, $sqdb);
		}
		$restoredVersionSql = db_escape_string($restoredVersion);
		$restoredDbSql = db_escape_string($db);
		db_modify("UPDATE regnskab SET version = '$restoredVersionSql' WHERE db = '$restoredDbSql'", __FILE__ . ' linje ' . __LINE__);
		db_close($connection);
		print "<BODY ONLOAD=\"javascript:alert('Regnskabet er genskabt. Du skal logge ind igen!')\">";
	
		unlink($filnavn);
		unlink($filnavn2);
		print "--> \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
		if ($popup) {
			print "<BODY ONLOAD=\"JavaScript:opener.location.reload();\"";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
		} else print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/index.php?regnskab=".htmlentities($regnskab,ENT_COMPAT,$charset)."&amp;restored=".rawurlencode(preg_replace('/[^0-9-]/', '', substr((string) $backupdate, 0, 20)) ?: '1')."\">"; // 20261104 CL/ASR the login page says the ledger was restored
		print "</tbody></table>";
		return true;
	} else {
		unlink($filnavn);
		unlink($filnavn2);
		print "<BODY ONLOAD=\"javascript:alert('Det er ikke en SALDI-sikkerhedskopi, som fors&oslash;ges indl&aelig;st')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=backup.php" . (!empty($_GET['popup']) ? "?popup=1" : "") . "\">"; // 20261102 CL/ASR a popup window stays without the settings menu
	}
	
	print "</tbody></table>";
	return false;
	}
	

print "</div></div></div>";
##########################
/**
 * Shows a restore() failure to the admin. restore() throws after it has opened the
 * "<!--" comment that hides the import output, so the comment is closed first.
 *
 * @param RuntimeException $e
 * @return void
 */
function printRestoreFailure(RuntimeException $e) {
	$message = $e->getMessage();
	error_log('restore.php: ' . $message);
	print "--> \n";
	print "<p style='color: red; font-weight: bold;'>" . htmlspecialchars($message, ENT_QUOTES) . "</p>";
	print "<script>alert(" . json_encode($message) . ");</script>";
}

function renderRestoreForm($formAction) {
	global $sprog_id, $buttonStyle;
	print "<tr><td align='center'>";
	print "<div style='border:2px solid #c00; border-radius:6px; padding:12px 16px; margin:24px auto; max-width:560px; text-align:left'>";
	print "<b style='color:#c00'>".findtekst('6935|Flyt fra MySQL', $sprog_id)."</b>";
	print "<p>".findtekst('6936|Til kunder, der flytter fra deres egen installation: MySQL-sikkerhedskopien indlæses, og dette tomme regnskab erstattes. Tag først en sikkerhedskopi af regnskabet.', $sprog_id)."</p>";
	echo '<p style="color: red; font-weight: bold;">Please note: This operation may take up to 12 minutes to complete.</p>';

	print "<form enctype='multipart/form-data' action='" . htmlspecialchars($formAction, ENT_QUOTES) . "' method='post' autocomplete='off'>";
	print "<input type='hidden' name='csrf_token' value='" . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES) . "'>";
	echo "<table cellpadding='5' cellspacing='0' border='0' align='center'>";
	print "<tr><td colspan='2'><input class='inputbox' name='uploadedfile' type='file' accept='.sdat,.sql' required></td></tr>";

	echo "<tr>";
	echo "<td><label for='mysql_db'>MySQL Database Name:</label></td>";
	echo "<td><input type='text' id='mysql_db' name='mysql_db' pattern='[A-Za-z0-9_]+' required></td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td><label for='mysql_user'>MySQL Database User:</label></td>";
	echo "<td><input type='text' id='mysql_user' name='mysql_user' required></td>";
	echo "</tr>";

	echo "<tr>";
	echo "<td><label for='mysql_pass'>MySQL Password:</label></td>";
	echo "<td><input type='password' id='mysql_pass' name='mysql_pass' autocomplete='off' required></td>";
	echo "</tr>";

	echo "<tr><td colspan='2'><hr></td></tr>";

	print "<tr><td><label for='restore_password'>".findtekst('6937|Din adgangskode', $sprog_id).":</label></td>";
	print "<td><input type='password' id='restore_password' name='password' autocomplete='current-password' required></td></tr>";
	print "<tr><td colspan='2'><label><input type='checkbox' name='confirm_restore' value='1' required> ".findtekst('6930|Jeg forstår, at regnskabet bliver overskrevet med sikkerhedskopien.', $sprog_id)."</label></td></tr>";

	echo "<tr>";
	print "<td colspan='2' align='center'><input type='submit' name='migrate' style='$buttonStyle' value='".findtekst('6943|Start flytning', $sprog_id)."'></td>";
	echo "</tr>";

	echo "</table>";
	echo "</form>";
	print "</div></td></tr>";
}
function findDumpInFirstThreeLines($handle) {
    // Read the first three lines
    $firstLine = fgets($handle);
    $secondLine = fgets($handle);
    $thirdLine = fgets($handle);

    // Check for the string "dump" in each line
    if (strpos($firstLine, 'dump') !== false) {
        $result = $firstLine;
    } elseif (strpos($secondLine, 'dump') !== false) {
        $result = $secondLine;
    } elseif (strpos($thirdLine, 'dump') !== false) {
        $result = $thirdLine;
    } else {
        $result = null;
    }

    // Close the file
    fclose($handle);

    return $result;
}

#++++++++++++++++++++
function migrateMySQLToPostgreSQL(
    $pgHost, $pgUser, $pgPass, $pgDb,
    $mysqlHost, $mysqlUser, $mysqlPass, $mysqlDb,
    $backupfil
) {
    /* Check backup file */
    $backUpDir = "../temp/backup/$pgDb/";
    foreach (glob($backUpDir . $pgDb . '*.sdat') as $file) break;

    if (!isset($file)) {
        die("DEBUG: No backup found for database $pgDb");
    }

    /* Ensure MySQL database exists */
    $conn = mysqli_connect($mysqlHost, $mysqlUser, $mysqlPass);
    if (!$conn) die("MySQL connect failed: " . mysqli_connect_error());

    $res = mysqli_query($conn, "SHOW DATABASES LIKE '$mysqlDb'");
    if (mysqli_num_rows($res) == 0) {
        echo "DEBUG: Creating MySQL database $mysqlDb\n";
        mysqli_query(
            $conn,
            "CREATE DATABASE `$mysqlDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }
    mysqli_close($conn);

    /* Import MySQL backup */
    putenv("MYSQL_PWD=$mysqlPass");
    exec(
        "mysql -u " . escapeshellarg($mysqlUser) . " " . escapeshellarg($mysqlDb) . " < " . escapeshellarg($backupfil),
        $out,
        $ret
    );
    putenv("MYSQL_PWD");
    if ($ret !== 0) die("DEBUG: MySQL import failed");

    /* Recreate PostgreSQL database */
    $pgConn1 = pg_connect("host=$pgHost user=$pgUser password=$pgPass");
    if (!$pgConn1) die(pg_last_error());

    pg_query($pgConn1, "
        SELECT pg_terminate_backend(pid)
        FROM pg_stat_activity
        WHERE datname = '$pgDb' AND pid <> pg_backend_pid()
    ");
    pg_query($pgConn1, "DROP DATABASE IF EXISTS \"$pgDb\"");
    pg_query($pgConn1, "CREATE DATABASE \"$pgDb\"");
    pg_close($pgConn1);

    /* Connect to PostgreSQL and MySQL */
    $pgConn = pg_connect("host=$pgHost dbname=$pgDb user=$pgUser password=$pgPass");
    if (!$pgConn) die(pg_last_error());

    $mysqlConn = new mysqli($mysqlHost, $mysqlUser, $mysqlPass, $mysqlDb);
    if ($mysqlConn->connect_error) die($mysqlConn->connect_error);

    $tablesResult = $mysqlConn->query("SHOW TABLES");
    if (!$tablesResult) die($mysqlConn->error);

    pg_query($pgConn, "BEGIN");

    $sequenceTracking = [];
    $createdTables = [];

    try {

        /* Convert and create tables */
        while ($table = $tablesResult->fetch_row()) {
            $origTable = $table[0];
            $tableName = strtolower($origTable);

            echo "DEBUG: Processing table $tableName\n";

            $exists = pg_fetch_row(pg_query(
                $pgConn,
                "SELECT EXISTS (
                    SELECT 1 FROM information_schema.tables
                    WHERE table_schema='public' AND table_name='$tableName'
                )"
            ))[0];

            if ($exists === 't') continue;

            $ct = $mysqlConn->query("SHOW CREATE TABLE `$origTable`")->fetch_row()[1];

            /* Normalize identifiers */
            $ct = str_replace('`', '"', $ct);
            $ct = str_replace("\"$origTable\"", "\"$tableName\"", $ct);

            /* Remove MySQL-only table options */
            $ct = preg_replace('/ENGINE\s*=\s*\w+/i', '', $ct);
            $ct = preg_replace('/DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $ct);
            $ct = preg_replace('/AUTO_INCREMENT\s*=\s*\d+/i', '', $ct);
            $ct = preg_replace('/ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', '', $ct);
            $ct = preg_replace('/\s+UNSIGNED\b/i', '', $ct);

            /* Remove column-level charset and collation */
            $ct = preg_replace(
                '/\s+CHARACTER\s+SET\s+\w+(\s+COLLATE\s+[\w_]+)?/i',
                '',
                $ct
            );
            $ct = preg_replace('/\s+COLLATE\s+[\w_]+/i', '', $ct);

            /* AUTO_INCREMENT → SERIAL / BIGSERIAL (no PK here) */
            $ct = preg_replace_callback(
                '/"(\w+)"\s+(bigint|int)(?:\(\d+\))?\s+NOT\s+NULL\s+AUTO_INCREMENT/i',
                function ($m) use (&$sequenceTracking, $tableName) {
                    $type = strtolower($m[2]) === 'bigint' ? 'BIGSERIAL' : 'SERIAL';
                    $sequenceTracking[] = [$tableName, $m[1]];
                    return "\"{$m[1]}\" $type";
                },
                $ct
            );

            $ct = preg_replace('/\s+AUTO_INCREMENT\b/i', '', $ct);

            /* Type conversions */
            $ct = preg_replace('/ENUM\s*\([^)]+\)/i', 'TEXT', $ct);
            $ct = preg_replace('/tinyint\s*\(\s*1\s*\)/i', 'BOOLEAN', $ct);
            $ct = preg_replace('/\btinyint\b/i', 'SMALLINT', $ct);
            $ct = preg_replace('/\bDATETIME\b/i', 'TIMESTAMP', $ct);
            $ct = preg_replace('/decimal\s*\(\s*(\d+)\s*,\s*0\s*\)/i', 'DECIMAL($1)', $ct);


			// Remove MySQL integer length specifiers: int(11), bigint(20), etc.
			$ct = preg_replace(
				'/\b(int|integer|bigint|smallint|mediumint|tinyint)\s*\(\s*\d+\s*\)/i',
				'$1',
				$ct
			);

			// Remove DEFAULT NULL (PostgreSQL default is NULL)
			$ct = preg_replace('/\s+DEFAULT\s+NULL/i', '', $ct);

            /* Remove MySQL indexes */
            $ct = preg_replace('/UNIQUE\s+KEY\s+"?[\w_]+"\s*\([^)]+\)/i', '', $ct);
            $ct = preg_replace('/KEY\s+"?[\w_]+"\s*\([^)]+\)/i', '', $ct);

            /* Final cleanup */
            $ct = preg_replace('/\s+/', ' ', $ct);
            $ct = preg_replace('/,\s*,+/', ',', $ct);

            while (preg_match('/,\s*\)/', $ct)) {
                $ct = preg_replace('/,\s*\)/', ')', $ct);
            }

            $ct = rtrim($ct, " ;");

						$ct = str_replace('"','',$ct);
						$ct = str_replace('COLLATE=latin1_swedish_ci','',$ct);

            if (!pg_query($pgConn, $ct)) {
                pg_query($pgConn, "ROLLBACK");
                die("PG ERROR:\n" . pg_last_error($pgConn) . "\n\n$ct");
            }

            $createdTables[$tableName] = true;

            /* Copy data */
            $data = $mysqlConn->query("SELECT * FROM `$origTable`");
            if (!$data || $data->num_rows === 0) continue;

            while ($row = $data->fetch_assoc()) {
                $cols = array_keys($row);
                $vals = array_map(fn($v) => $v === '' ? null : $v, array_values($row));
                $ph   = array_map(fn($i) => '$' . ($i + 1), array_keys($cols));

                $sql = 'INSERT INTO public."' . $tableName . '" (' .
                       implode(',', $cols) . ') VALUES (' .
                       implode(',', $ph) . ')';

                if (!pg_query_params($pgConn, $sql, $vals)) {
                    pg_query($pgConn, "ROLLBACK");
                    die("INSERT ERROR: " . pg_last_error($pgConn));
                }
            }
        }

        /* Fix sequences */
        foreach ($sequenceTracking as [$t, $c]) {
            $seq = pg_fetch_row(pg_query(
                $pgConn,
                "SELECT pg_get_serial_sequence('public.\"$t\"','$c')"
            ))[0];

            $max = pg_fetch_row(pg_query(
                $pgConn,
                "SELECT MAX(\"$c\") FROM public.\"$t\""
            ))[0] ?? 1;

            pg_query($pgConn, "SELECT setval('$seq', $max)");
        }

        pg_query($pgConn, "COMMIT");
        echo "DEBUG: Migration completed successfully\n";

    } catch (Throwable $e) {
        pg_query($pgConn, "ROLLBACK");
        die("EXCEPTION: " . $e->getMessage());
    }

    /* Cleanup */
    $mysqlConn->close();
    pg_close($pgConn);
    system("rm -rf " . escapeshellarg("../temp/$pgDb") . "/*");
		print "<meta http-equiv=\"refresh\" content=\"4;URL=../index/logud.php\">";
}




#++++++++++++++++++++
#####################


if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}
?>

