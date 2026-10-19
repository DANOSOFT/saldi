<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/journalDocuments.php --- ver 5.0.0 --- 2026.10.12 ---
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
//
// Copyright (c) 2003-2026 saldi.dk ApS
// ----------------------------------------------------------------------
// 20261012 CL/SZ SD-730 Documents for the journal itself (e.g. a bank statement), opened from the clip next to Bemærkning.
//                 Upload (PDF, JPG, PNG), list, view and delete. No pool: such a document is not a voucher for a line.
//                 On a posted journal a document older than 24 hours can't be deleted, as for line documents.
//                 (CodeRabbit) The file's content must match its PDF/JPG/PNG name; the name is reserved atomically (fopen 'x'),
//                 so two uploads of one name can't both write it; a file that can't be deleted keeps its row.

ob_start();
@session_start();
$s_id = session_id();
$title = "Kassekladde";
$modulnr = 2;
$css = "../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
require_once __DIR__ . '/kassekladde_includes/journalDocumentsFunc.php';

$kladde_id = (int)if_isset($_GET, 0, 'kladde_id');
$journal = $kladde_id ? db_fetch_array(db_select("select id, bogfort from kladdeliste where id = '$kladde_id'", __FILE__ . " linje " . __LINE__)) : null;
if (!$journal) {
	header('Location: kladdeliste.php', true, 303);
	exit;
}
$posted = ($journal['bogfort'] == 'V');
$folder = journalDocumentsFolder($db, $kladde_id);
$self   = "journalDocuments.php?kladde_id=$kladde_id";
$notice = '';

// Controller: upload or delete, then back to this page (a reload can't repeat it)
if (isset($_FILES['uploadedFile']['name']) && $_FILES['uploadedFile']['name'] !== '') {
	$file = $_FILES['uploadedFile'];
	$ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
	$name = strtr(basename($file['name']), array('æ' => 'ae', 'Æ' => 'Ae', 'ø' => 'oe', 'Ø' => 'Oe', 'å' => 'aa', 'Å' => 'Aa'));
	$name = ltrim(preg_replace('/[^A-Za-z0-9._-]+/', '_', $name), '.');
	if (!in_array($ext, array('pdf', 'jpg', 'jpeg', 'png'), true)) {
		$notice = findtekst('5434|Kun PDF, JPG og PNG kan vedhæftes', $sprog_id);
	} elseif ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
		// e.g. larger than upload_max_filesize
		$notice = findtekst('5436|Dokumentet kunne ikke gemmes', $sprog_id);
	} elseif (journalDocumentsType($file['tmp_name']) !== ($ext == 'jpeg' ? 'jpg' : $ext)) {
		// The content has to be what the name says: a renamed file of another kind is refused
		$notice = findtekst('5434|Kun PDF, JPG og PNG kan vedhæftes', $sprog_id);
	} else {
		$exists = db_fetch_array(db_select("select id from documents where source = 'kladdeliste' and source_id = '$kladde_id' and filename = '" . db_escape_string($name) . "'", __FILE__ . " linje " . __LINE__));
		if (!$exists && !is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
			$notice = findtekst('5436|Dokumentet kunne ikke gemmes', $sprog_id);
		} elseif ($exists || !($reserved = @fopen("$folder/$name", 'x'))) {
			// fopen 'x' creates the file only if it doesn't exist, so two uploads of one name can't both get it
			$notice = findtekst('5435|Kladden har allerede et dokument med det navn', $sprog_id);
		} elseif (!fclose($reserved) || !move_uploaded_file($file['tmp_name'], "$folder/$name")) {
			@unlink("$folder/$name");
			$notice = findtekst('5436|Dokumentet kunne ikke gemmes', $sprog_id);
		} else {
			$r = db_fetch_array(db_select("select var_value from settings where var_name = 'globalId'", __FILE__ . " linje " . __LINE__));
			$globalId = $r ? (int)$r['var_value'] : 1;
			$qtxt = "insert into documents (global_id, filename, filepath, source, source_id, timestamp, user_id) values ";
			$qtxt.= "('$globalId', '" . db_escape_string($name) . "', '" . journalDocumentsFilepath($kladde_id) . "', 'kladdeliste', '$kladde_id', '" . date('U') . "', '" . (int)$bruger_id . "')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			header("Location: $self&vis=" . urlencode($name), true, 303);
			exit;
		}
	}
} elseif (isset($_POST['deleteDoc'])) {
	$docId = (int)$_POST['deleteDoc'];
	$doc = db_fetch_array(db_select("select filename, timestamp from documents where id = '$docId' and source = 'kladdeliste' and source_id = '$kladde_id'", __FILE__ . " linje " . __LINE__));
	if ($doc && $posted && date('U') - (int)$doc['timestamp'] > 86400) {
		$notice = findtekst('5433|Kladden er bogført, og dokumentet er ældre end 24 timer', $sprog_id);
	} elseif ($doc) {
		$file = "$folder/" . basename($doc['filename']);
		if (is_file($file) && !@unlink($file)) {
			// The row stays, so the document can still be found and deleted later
			$notice = findtekst('5438|Dokumentet kunne ikke slettes', $sprog_id);
		} else {
			db_modify("delete from documents where id = '$docId'", __FILE__ . " linje " . __LINE__);
			header("Location: $self", true, 303);
			exit;
		}
	}
}

// View
$docs = journalDocumentsList($kladde_id);
$show = (string)if_isset($_GET, '', 'vis');
$current = null;
foreach ($docs as $doc) {
	if ($show === '' || $doc['filename'] === $show) { $current = $doc; break; }
}
$back = "kassekladde.php?kladde_id=$kladde_id&tjek=$kladde_id";
$heading = findtekst('5430|Dokumenter til kladden', $sprog_id) . " $kladde_id";
if (!isset($top_bund)) $top_bund = '';

if ($menu == 'T') {
	include_once '../includes/top_menu.php';
	include_once '../includes/top_header.php';
	print "<div id='header'><div class='headerbtnLft'>";
	print "<a href='$back' class='button red small left' accesskey='L'>" . findtekst('30|Tilbage', $sprog_id) . "</a></div>";
	print "<span class='headerTxt'>$heading</span><div class='headerbtnRght'></div></div>";
	print "<div class='maincontentLargeHolder'>";
} elseif ($menu == 'S') {
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody><tr>";
	print "<td width='10%'><a href='$back' accesskey='L'><button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">" . findtekst('30|Tilbage', $sprog_id) . "</button></a></td>";
	print "<td width='80%' style='$topStyle' align='center'>$heading</td><td width='10%' style='$topStyle'><br></td>";
	print "</tr></tbody></table>";
} else {
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody><tr>";
	print "<td width='10%' $top_bund><a href='$back' accesskey='L'>" . findtekst('30|Tilbage', $sprog_id) . "</a></td>";
	print "<td width='80%' $top_bund>$heading</td><td width='10%' $top_bund><br></td>";
	print "</tr></tbody></table>";
}
if ($notice) {
	print "<script>alert(" . json_encode($notice, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ");</script>";
}

print "<table width='100%' border='0' cellspacing='0' cellpadding='6' style='height:calc(100vh - 60px);'><tbody><tr>";
print "<td valign='top' style='width:320px;'>";
print "<table id='journalDocsList' width='100%' border='0' cellspacing='0' cellpadding='3'><tbody>";
foreach ($docs as $doc) {
	$name = htmlspecialchars($doc['filename'], ENT_QUOTES);
	$date = $doc['timestamp'] ? date('d-m-Y', (int)$doc['timestamp']) : '';
	$bold = ($current && $current['id'] == $doc['id']) ? 'font-weight:bold;' : '';
	print "<tr><td><a href='$self&vis=" . urlencode($doc['filename']) . "' style='$bold'>$name</a></td><td style='white-space:nowrap;'>$date</td>";
	$locked = $posted && date('U') - (int)$doc['timestamp'] > 86400;
	print "<td>";
	if (!$locked) {
		$confirm = htmlspecialchars(json_encode(findtekst('1099|Slet', $sprog_id) . ' ' . $doc['filename'] . '?'), ENT_QUOTES);
		print "<form method='post' action='$self' style='margin:0' onsubmit='return confirm($confirm)'>";
		print "<input type='hidden' name='deleteDoc' value='" . $doc['id'] . "'><input type='submit' value='" . findtekst('1099|Slet', $sprog_id) . "'></form>";
	}
	print "</td></tr>";
}
print "</tbody></table><br>";
print "<form id='journalDocsUpload' method='post' action='$self' enctype='multipart/form-data'>";
print "<b>" . findtekst('5431|Vedhæft dokument til kladden, f.eks. kontoudtog fra banken', $sprog_id) . "</b><br>";
print "<input type='file' name='uploadedFile' accept='.pdf,.jpg,.jpeg,.png'><br><br>";
print "<input type='submit' value='" . findtekst('3|Gem', $sprog_id) . "'></form>";
print "</td><td valign='top'>";
if ($current) {
	$src = htmlspecialchars("$folder/" . rawurlencode(basename($current['filename'])), ENT_QUOTES);
	if (strtolower(pathinfo($current['filename'], PATHINFO_EXTENSION)) == 'pdf') {
		print "<iframe id='journalDocsView' frameborder='no' width='100%' height='100%' scrolling='auto' src='$src'></iframe>";
	} else {
		print "<img id='journalDocsView' src='$src' style='max-width:100%;height:auto;'>";
	}
}
print "</td></tr></tbody></table>";
if ($menu == 'T') print "</div>";
