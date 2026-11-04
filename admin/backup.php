<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------/admin/backup.php---ver 5.1.0--2026-11-04---------------------
//                           LICENSE
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
// 2019.07.04 RG (Rune Grysbæk) Mysqli implementation 
// 2020.03.08 PHR SQL dump created on demand.
// 20250427 LOE User can now delete backup file saved if they changed their minds.
// 20250511 LOE Backupfile save a copy of current db on saldi
// 20261102 CL/ASR The head of every settings page (opened from Indstillinger > Data & sikkerhedskopi > Sikkerhedskopi): the shared Tilbage button top-left above the title (settings_back_button(), css/saldi-kit.css) and the title on the page head (page_bar), instead of the bar with Tilbage to the old main menu.
//                 Tilbage goes back to the Sikkerhedskopi section; in a popup window (popup=1) it closes it, as before.
//                 Alt+L kept.
// 20261102 CL/ASR The standard settings layout (settings_layout_open(), includes/settings/layout.php; owner, 2 Nov 2026: every page reached from Indstillinger keeps the left menu).
//                 The left menu of Finans with Sikkerhedskopi marked, Tilbage above the title back to Sikkerhedskopi, the breadcrumb Indstillinger / Finans / Sikkerhedskopi / this page.
//                 A popup window (popup=1) has no menu and Tilbage closes it, as before; the page's own links (create, cancel, restore) keep popup=1, so the window stays without the menu.
//                 The div that centres the page's content opens inside the content column; the backup itself is unchanged.
// 20261102 CL/ASR Review: after Opret sikkerhedskopi the closing of the tables and the layout is printed only outside the old top menu ($menu T), whose page stays as it was.
// 20261103 CL/ASR QA round 2: the page needs Sikkerhedskopi (settings.backup), and creating or deleting a backup needs write.
//                 The dump command with the database password is no longer printed into the page.
//                 The plain dump and backup.info are deleted once archived, the kept copy gets a random part in its name and is downloaded through backup_download.php, and Annullér deletes only that copy.
// 20261104 CL/ASR Every visit removes expired work folders of the two-step restore (includes/restorePending.php), as restore.php does.
// 20261104 CL/ASR Final QA: a backup that was created is written to the audit log (setting.action on import_export.backup, with the file name and size), as the restore's steps are.

@session_start();
$s_id = session_id();

$css     = "../css/standard.css";
$title   = "Sikkerhedskopi";
$modulnr = 11;
// Opening the page is a read on Sikkerhedskopi; creating a backup (backup=1) or deleting the copy (POST) is a write.
$permission_key = 'settings.backup';
if (isset($_GET['backup']) || (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST')) {
	$permission_level = 'write';
}

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
include_once("../includes/restorePending.php");
restore_pending_sweep(); // an unconfirmed restore upload older than 30 minutes

if (!isset($exec_path)) $exec_path="/usr/bin";

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";

$dump_filnavn = "../temp/".trim($db.".sql");
$info_filnavn = "../temp/backup.info";
$tar_filnavn  = "../temp/".trim($db."_".date("Ymd-Hi")).".tar";
$gz_filnavn   = "../temp/".trim($db."_".date("Ymd-Hi")).".tar.gz";
$dat_filnavn  = "../temp/".trim($db."_".date("Ymd-Hi")).".sdat";
$backup       = TRUE;
$timestamp    = date("Ymd-Hi");
$r            = db_fetch_array(db_select("select box1 from grupper where art = 'VE'",__FILE__ . " linje " . __LINE__));
$dbver        = $r['box1'];
// The files a backup leaves under temp/ are served by the web server without a login, so nothing stays there under a
// guessable name: the plain dump and the info file older than ten minutes (an interrupted run, or one from before
// 2026-11-03) are removed, and a kept copy without a random part in its name gets one.
foreach (array($dump_filnavn, $info_filnavn) as $staleFile) {
	if (is_file($staleFile) && filemtime($staleFile) < time() - 600) @unlink($staleFile);
}
foreach (glob("../temp/" . basename((string) $db) . "_*.sdat") ?: array() as $staleFile) {
	if (filemtime($staleFile) < time() - 600) @unlink($staleFile);
}
foreach (glob("../temp/backup/" . basename((string) $db) . "/*.sdat") ?: array() as $staleFile) {
	if (!preg_match('/_[0-9a-f]{16}\.sdat$/', $staleFile)) @rename($staleFile, substr($staleFile, 0, -5) . '_' . bin2hex(random_bytes(8)) . '.sdat');
}
if (isset($_GET['backup']) && $_GET['backup']==1) {
	$fp = fopen($info_filnavn,"w");
	if ($fp) {
		fwrite($fp,"$timestamp".chr(9)."$db".chr(9)."$dbver".chr(9)."$regnskab".chr(9)."$db_encode".chr(9)."$db_type");
	} 
	fclose($fp);
	if ($db_type=='mysql' or $db_type=='mysqli') $dumpcmd="$exec_path/mysqldump -h $sqhost -u $squser --password=$sqpass -n $db --result-file=../temp/$dump_filnavn"; #RG_mysqli
	else $dumpcmd="export PGPASSWORD=$sqpass\n$exec_path/pg_dump -h $sqhost -U $squser -f $dump_filnavn $db";
	print "<!-- Saldi-kommentar for at skjule uddata til siden \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
	system ($dumpcmd);
	system ("tar -cf $tar_filnavn $dump_filnavn $info_filnavn");
	system ("gzip $tar_filnavn");
	system ("mv $gz_filnavn $dat_filnavn");
	// The plain dump and the info file are inside the archive now.
	@unlink($dump_filnavn);
	@unlink($info_filnavn);
	print "--> \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
}

// popup=1 (a popup window from the old main menu) is kept on the page's own links, so the window never gets the settings menu.
$popupQs = !empty($_GET['popup']) ? '&popup=1' : '';
if ($menu!='T') {
	// The standard settings layout (includes/settings/layout.php): Tilbage above the title back to Sikkerhedskopi, the left menu of
	// Finans with Sikkerhedskopi marked. A popup window (popup=1) gets no menu and Tilbage closes it.
	include_once("../includes/settings/layout.php");
	settings_layout_open($popupQs ? array('back' => '../includes/luk.php') : array());
}
print "<div align='center'>";
if ($menu=='T') {
#	print "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>";
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id='header'>";
	print "<div class='headerbtnLft'></div>\n";
#	print "<span class='headerTxt'>Systemsetup</span>\n";     
#	print "<div class='headerbtnRght'><!--<a href='index.php?page=../debitor/debitorkort.php;title=debitor' class='button green small right'>Ny debitor</a>--></div>";       
	print "</div><!-- end of header -->";
	print "<div id='leftmenuholder'>";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class='maincontent'>\n";

	print "<table border='0' cellspacing='0' id='dataTable' class='dataTable'><tbody>"; # -> 1
} else {
	print "<table width='100%' height='100%' border='0' cellspacing='0' cellpadding='0'><tbody>";
}

if ($menu=='T' || $menu=='S') {
	$style = "";
	$buttonStart = "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
	$buttonEnd = "</button>";
} else {
	$style = $top_bund;
	$buttonStart = "";
	$buttonEnd = "";
}

########################Handle the cancel instruction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel'])) {
	// Only this ledger's kept copy can be deleted, never another path sent with the form.
	$file_path = "../temp/backup/" . basename((string) $db) . "/" . basename((string) if_isset($_POST,NULL,'file_path'));
	error_log("Attempting to delete file: $file_path");
	if (file_exists($file_path)) {
        error_log("Deleting file: $file_path");
		unlink($file_path);
        $file_path = "../temp/$dat_filnavn";
                if (file_exists($file_path)) {
                    if (unlink($file_path)) {   
                        error_log("File deleted successfully: $file_path");
                    }else {
                        error_log("Error deleting file: $file_path");
                    } 
                }
         $backup=FALSE;
         echo '<meta http-equiv="refresh" content="0;url=backup.php' . ($popupQs ? '?popup=1' : '') . '" />';
        exit;

	} else {
		error_log("Error: Attempted to delete a non-existing file: $file_path");
	}
}
############################
print "<td align='center' valign='middle'>";
print "<table cellpadding='1' cellspacing='1' border='0'><tbody>";

if (file_exists("../temp/$dat_filnavn") && $backup==TRUE) {
	$dat_filnavn = basename($dat_filnavn);
	$backUpDir = "../temp/backup/$db/"; //use this to ensure database is backed up
	// A random part in the name, so the kept copy cannot be guessed from the ledger name and the time.
	$backUpFile = $backUpDir . substr($dat_filnavn, 0, -5) . '_' . bin2hex(random_bytes(8)) . '.sdat';
	$sourceFile = "../temp/$dat_filnavn";
	


	if (!is_dir($backUpDir)) {
		mkdir($backUpDir, 0755, true);
	}
	// No directory listing, and no web access where the server reads .htaccess; the file is fetched through backup_download.php.
	foreach (array("../temp/backup/", $backUpDir) as $guardDir) {
		if (is_dir($guardDir) && !file_exists($guardDir . "index.html")) @file_put_contents($guardDir . "index.html", "");
	}
	if (!file_exists("../temp/backup/.htaccess")) @file_put_contents("../temp/backup/.htaccess", "Require all denied\n");
	if (file_exists($sourceFile)) {
		if (is_dir($backUpDir)) {
			foreach (glob($backUpDir . '*.sdat') as $file) {
				if (is_file($file)) {
					unlink($file);
                    
				}
			}
		}
		try {
			error_log("dat_filnavn1: " . $dat_filnavn);
			error_log("Source file1: " . $sourceFile);
			error_log("backUp file1: " . $backUpFile);
			if (copy($sourceFile, $backUpFile)) {
				@unlink($sourceFile); // the copy under temp/backup/ is the one offered for download
				if (function_exists('audit_log')) {
					audit_log('setting.action', (string) json_encode(array('step' => 'backup_created', 'file' => basename($backUpFile), 'size' => (int) @filesize($backUpFile)), JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE), 'indstilling', 'import_export.backup');
				}
				#echo "Please click on save to continue..";
			} else {
				throw new Exception("File not saved at $backUpFile");
			}
		} catch (Exception $e) {
			error_log("Error copying file: " . $e->getMessage());
			echo "An error occurred. Check the logs for more details.\n";
		}
	}
    print "<tr><td align=center>".findtekst('1241|Klik her', $sprog_id).": </td>";
    print "<td $style title='".findtekst('1674|Her har du mulighed for danne en sikkerhedskopi som du kan gemme.', $sprog_id).".'>";
    print "<a href='backup_download.php?f=" . rawurlencode(basename($backUpFile)) . "'> $buttonStart";
    print findtekst('3|Gem', $sprog_id)." ".findtekst('614|Sikkerhedskopi', $sprog_id);
    print "$buttonEnd </a></td></tr>";
    print "<tr><td align=center colspan=2>".findtekst('1242|og gem sikkerhedskopien et passende sted.', $sprog_id)."</td></tr>";
    print "<tr><td align=center colspan=2>".findtekst('1243|Hvis din browser forsøger at åbne filen',   $sprog_id)."</td></tr>";
    print "<tr><td align=center colspan=2>".findtekst('1244|skal du højreklikke og vælge gem som',      $sprog_id)."</td></tr>";
    
    // Add Cancel button below
    error_log('Backup file path: ' . $backUpFile);
 	 print "<tr><td align=center colspan=2>";

	print "<div style='display: flex; justify-content: center; gap: 20px;'>";
	print "<form action='' method='post'>";
	print "<input type='hidden' name='file_path' value='$backUpFile' />";
	print "<input type='submit' name='cancel' style='$buttonStyle' value='".findtekst('5|Annullér', $sprog_id)."' />";
	print "</form>";

	print "<form action='restore.php' method='get'>";
	print "<input type='hidden' name='file_path' value='$backUpFile' />";
	if ($popupQs) print "<input type='hidden' name='popup' value='1' />";
	print "<input type='submit' style='$buttonStyle' value='".findtekst('3358|Gå til indlæsning', $sprog_id)."' />";
	print "</form>";
	print "</div>";
    print "</td></tr>";
	if ($menu!='T') { // 20261102 CL/ASR the old top menu's page is printed as before
		print "</tbody></table></tbody></table></div>";
		settings_layout_close();
	}


	return;
	
} else {
   
    print "<tr><td align=center>".findtekst('1241|Klik her', $sprog_id).": </td><td $style  title='".findtekst('1675|Her har du mulighed for at gemme sikkerhedskopien på din computer', $sprog_id)."'>";
    print "<a href='backup.php?backup=1$popupQs'> $buttonStart";
    print findtekst('1245|Opret sikkerhedskopi', $sprog_id);
    print "$buttonEnd </a></td></tr>";
    print "<tr><td align=center colspan=2>".findtekst('1246|for at danne en sikkerhedskopi', $sprog_id)."<br>".findtekst('1676|som du kan gemme på din computer', $sprog_id).".</td></tr>";
    print "<tr><td colspan=2><hr></td></tr>";
    print "<tr><td align=center> ".findtekst('1241|Klik her', $sprog_id).": </td>";
    print "<td $style title='".findtekst('1677|Her har du mulighed for at genindlæse en tidligere gemt sikkerhedskopi', $sprog_id)."'>";
    print "<a href='../admin/restore.php" . ($popupQs ? '?popup=1' : '') . "'> $buttonStart";
    print findtekst('1247|Indlæs sikkerhedskopi', $sprog_id);
    print "$buttonEnd </a></td></tr>";
    print "<tr><td align=center colspan=2>".findtekst('1248|for at indlæse en sikkerhedskopi<br>fra din computer.', $sprog_id)."</td></tr>";
}
print "</tbody></table></tbody></table>";

if ($menu=='T') print "</div></div>";
else {
	print "</div>";
	settings_layout_close();
}


if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

?>
