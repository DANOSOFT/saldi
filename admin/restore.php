<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- admin/restore.php --- lap 5.0.0 --- 2026-10-08 ---
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
// Copyright (c) 2003-2026 Saldi.dk ApS
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
// 20261006 Sawaneh WP-6.3: Luk returns to the admin account page after a ?db= restore, otherwise to the calling page/backup.php.
// 20261008 Sawaneh Restore (settings decision 18): online.php always runs; ?db= needs a Saldi-admin session with access to that ledger and an existing database (none is created), else 403.
//                  Only the file uploaded in the same request is restored, after a required confirmation and a CSRF check; no file path is taken from POST and the work folder is removed when the request ends.
//                  Shell arguments are escaped and database passwords passed through the environment.
//                  The MySQL migration is shown and accepted only for a Saldi-admin with the admin right on an empty ledger, with password and audit log. Texts 6930-6943.
// 20261008 Sawaneh Settings 4e: the old top-menu ($menu=='T') branches removed; the else legs stay (spec §7.6 R3).

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
 
 

// WP-6.3: from the admin panel (?db=) Luk returns to that account's admin page (it reads db_id), else the account list;
// otherwise to the page the user came from, luk.php in a popup, else backup.php (was the main menu in both cases).
$returnGet = nav_sanitize_returside(if_isset($_GET, NULL, 'returside'));
if ($adminRestore) {
	$returside = !empty($adminRegnskabId) ? "aaben_regnskab.php?db_id=" . (int) $adminRegnskabId : "vis_regnskaber.php";
	$formAction = "restore.php?db=" . rawurlencode($tmpDb);
} else {
	$returside = $returnGet;
	if (!$returside) $returside = $popup ? "../includes/luk.php" : "backup.php";
	$formAction = $returnGet ? "restore.php?returside=" . rawurlencode($returnGet) : "restore.php";
}

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

print "<div align=\"center\">";
if ($menu=='S') {
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
$restoreRan = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!$_POST && !$_FILES && (int) ifset($_SERVER, 'CONTENT_LENGTH', 0) > 0) {
		$restoreMsg = 'Filen er for stor - Kontroller upload_max_filesize i php.ini';
	} elseif (!hash_equals((string) $_SESSION['csrf_token'], (string) ifset($_POST, 'csrf_token', ''))) {
		$restoreMsg = findtekst('6940|Formularen er udløbet. Hent siden igen, og prøv igen.', $sprog_id);
	} elseif (isset($_POST['migrate'])) {
		list($restoreRan, $restoreMsg) = restore_handle_migration();
	} elseif (isset($_POST['restore_upload'])) {
		list($restoreRan, $restoreMsg) = restore_handle_upload();
	}
}
if ($restoreMsg !== '') {
	print "<tr><td align='center' style='color:red; padding:8px'>$restoreMsg</td></tr>";
}
if (!$restoreRan) {
	upload($formAction);
	if (restore_migration_allowed()) renderRestoreForm($formAction);
}
print "</tbody></table></div>";
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
	
	
	
		$textup     = $translations[2422][$sprog_id];
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
	print "<tr><td width=100% align=center><label><input type=\"checkbox\" name=\"confirm_restore\" value=\"1\" required> ".findtekst('6930|Jeg forstår, at regnskabet bliver overskrevet med sikkerhedskopien.', $sprog_id)."</label></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" name=\"restore_upload\" style=\"$buttonStyle\" value=\"".$load."\" onClick=\"return confirmSubmit(" . htmlspecialchars(json_encode($textup), ENT_QUOTES) . ")\"></td></tr>";
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

function restore_inside($path, $dir) {
	$real = realpath($path);
	$base = realpath($dir);
	return $real && $base && strpos($real, $base . '/') === 0 && is_file($real);
}

/**
 * Takes the file uploaded in this request into a work folder of its own (removed when the request ends) and finds the dump in it.
 *
 * @return array{error: string, file: string, encode: ?string, dbtype: ?string, kind: string}
 */
function restore_prepare_upload($db) {
	global $sprog_id;
	$res = array('error' => '', 'file' => '', 'encode' => NULL, 'dbtype' => NULL, 'kind' => '');
	$up = if_array($_FILES, 'uploadedfile');
	$err = (int) ifset($up, 'error', UPLOAD_ERR_NO_FILE);
	if ($err === UPLOAD_ERR_INI_SIZE) $res['error'] = 'Filen er for stor - Kontroller upload_max_filesize i php.ini';
	elseif ($err === UPLOAD_ERR_FORM_SIZE) $res['error'] = 'Filen er for stor - er det en SALDI-sikkerhedskopi?';
	elseif ($err === UPLOAD_ERR_NO_FILE) $res['error'] = findtekst('1364|Vælg datafil', $sprog_id);
	elseif ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) ifset($up, 'tmp_name', ''))) $res['error'] = findtekst(2427, $sprog_id);
	if ($res['error'] !== '') return $res;

	$extension = strtolower(pathinfo(basename((string) ifset($up, 'name', '')), PATHINFO_EXTENSION));
	if ($extension !== 'sdat' && $extension !== 'sql') {
		$res['error'] = 'Only .sdat or .sql files are allowed.';
		return $res;
	}
	$workDir = "../temp/$db/restore_" . bin2hex(random_bytes(8));
	if (!mkdir($workDir, 0700)) {
		$res['error'] = findtekst(2427, $sprog_id);
		return $res;
	}
	register_shutdown_function('restore_remove_dir', realpath($workDir));

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
			echo "⚠️Unsupported or unknown file type: " . htmlspecialchars((string) $mimeType) . "\n";
		}
		$file = file_exists("$workDir/restore") ? "$workDir/restore" : $gzFile;
		system("/bin/tar -C " . escapeshellarg($workDir) . " -xf " . escapeshellarg($file));
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
		}
	}
	$res['file'] = $file;
	$handle = fopen($file, 'r');
	$result = $handle ? trim((string) findDumpInFirstThreeLines($handle)) : '';
	if (stripos($result, 'MySQL dump') !== false) $res['kind'] = 'mysql';
	elseif (stripos($result, 'PostgreSQL') !== false) $res['kind'] = 'postgresql';
	return $res;
}

/**
 * @return array{0: bool, 1: string} whether a restore was run, and a message
 */
function restore_handle_upload() {
	global $db, $sprog_id;
	if (empty($_POST['confirm_restore'])) {
		return array(false, findtekst('6931|Sæt flueben for at bekræfte, at regnskabet må overskrives.', $sprog_id));
	}
	$up = restore_prepare_upload($db);
	if ($up['error'] !== '') return array(false, $up['error']);
	if ($up['kind'] == 'mysql') {
		return array(false, findtekst('6934|Filen er en MySQL-sikkerhedskopi. Den kan kun indlæses som flytning fra MySQL i adminpanelet på et tomt regnskab.', $sprog_id));
	}
	restore($up['file'], $up['encode'], $up['dbtype']);
	return array(true, '');
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

function restore($filnavn,$backup_encode,$backup_dbtype){

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
	
	$filnavn2=$filnavn.".restore.sql";

	$restore="";
	$fp=fopen("$filnavn","r");
	$fp2=fopen("$filnavn2","w");
	
	
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
		if (substr($db_type,0,5)=='mysql') {
			if (file_exists("/usr/bin/mysql")) $mysql = "/usr/bin/mysql";
			elseif (file_exists("/bin/mysql")) $mysql = "/usr/mysql";
			else echo "mysql not found<br>";
			if ($mysql) {
				putenv("MYSQL_PWD=$sqpass");
				system(escapeshellarg($mysql) . " -u " . escapeshellarg($squser) . " -h " . escapeshellarg($sqhost) . " " . escapeshellarg($db) . " < " . escapeshellarg($filnavn2));
				putenv("MYSQL_PWD");
			}
		} else {
			if (file_exists("/usr/bin/psql")) $psql = "/usr/bin/psql";
			elseif (file_exists("/bin/psql")) $psql = "/usr/psql";
			else echo "psql not found<br>";
			if ($psql) {
				putenv("PGPASSWORD=$sqpass");
				system(escapeshellarg($psql) . " -h " . escapeshellarg($sqhost) . " -U " . escapeshellarg($squser) . " " . escapeshellarg($db) . " < " . escapeshellarg($filnavn2));
				putenv("PGPASSWORD");
			}
		}
		db_close($connection);
		print "<BODY ONLOAD=\"javascript:alert('Regnskabet er genskabt. Du skal logge ind igen!')\">";
	
		unlink($filnavn);
		unlink($filnavn2);
		print "--> \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
		if ($popup) {
			print "<BODY ONLOAD=\"JavaScript:opener.location.reload();\"";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
		} else print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/index.php?regnskab=".htmlentities($regnskab,ENT_COMPAT,$charset)."\">";
	 
	} else {
		unlink($filnavn);
		unlink($filnavn2);
		print "<BODY ONLOAD=\"javascript:alert('Det er ikke en SALDI-sikkerhedskopi, som fors&oslash;ges indl&aelig;st')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=backup.php\">";
	}
	
	print "</tbody></table>";
	}
	

print "</div></div></div>";
##########################
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


include_once '../includes/oldDesign/footer.php';
?>

