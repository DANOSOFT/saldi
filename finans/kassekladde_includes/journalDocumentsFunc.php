<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/journalDocumentsFunc.php --- ver 5.0.0 --- 2026.10.12 ---
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
// 20261012 CL/SZ SD-730 Documents for the journal itself (e.g. a bank statement), not for a line: rows in documents with
//                 source 'kladdeliste' and source_id = kladde_id, files in <docFolder>/<db>/finance/<kladde_id>/journal.

/**
 * The document folder as seen from finans/ (owncloud, bilag or documents, as insertDoc.php picks it).
 */
function journalDocumentsRoot()
{
	foreach (array('owncloud', 'bilag', 'documents') as $folder) {
		if (is_dir(__DIR__ . "/../../$folder")) return "../$folder";
	}
	return '../bilag';
}

/**
 * Folder of a journal's documents, relative to finans/. "journal" is not a number, so it can't meet a
 * line's folder (finance/<kladde_id>/<line id>).
 */
function journalDocumentsFolder($db, $kladde_id)
{
	return journalDocumentsRoot() . '/' . basename((string)$db) . '/finance/' . (int)$kladde_id . '/journal';
}

/**
 * documents.filepath for a journal's documents.
 */
function journalDocumentsFilepath($kladde_id)
{
	return '/finance/' . (int)$kladde_id . '/journal';
}

/**
 * The journal's documents, oldest first.
 *
 * @return array<int, array{id: int, filename: string, timestamp: string}>
 */
function journalDocumentsList($kladde_id)
{
	$docs = array();
	$qtxt = "select id, filename, timestamp from documents where source = 'kladdeliste' and source_id = '" . (int)$kladde_id . "' order by id";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$docs[] = array('id' => (int)$r['id'], 'filename' => $r['filename'], 'timestamp' => $r['timestamp']);
	}
	return $docs;
}

/**
 * The clip next to Bemærkning: paper when the journal has documents, clip when it has none, inactive on a
 * journal that hasn't been saved yet. Unsaved lines get the usual "not saved" prompt before the page opens.
 */
function journalDocumentsClip($kladde_id, $sprog_id)
{
	$kladde_id = (int)$kladde_id;
	$style = "style='padding-left: 10px; vertical-align: middle;'";
	if (!$kladde_id) {
		$title = htmlspecialchars(findtekst('5432|Gem kladden, før du vedhæfter dokumenter til den', $sprog_id), ENT_QUOTES);
		return "<td $style title='$title'><img src='../ikoner/clip.png' style='width:20px;height:20px;opacity:0.4;' alt=''></td>";
	}
	$count = count(journalDocumentsList($kladde_id));
	if ($count) {
		$icon  = 'paper.png';
		$title = findtekst('5430|Dokumenter til kladden', $sprog_id) . " ($count)";
	} else {
		$icon  = 'clip.png';
		$title = findtekst('5431|Vedhæft dokument til kladden, f.eks. kontoudtog fra banken', $sprog_id);
	}
	$title = htmlspecialchars($title, ENT_QUOTES);
	$href  = "journalDocuments.php?kladde_id=$kladde_id";
	return "<td $style title='$title'><span id='kkJournalDocs' onclick=\"confirmClose('$href', bilagUnsavedTxt)\" style='cursor:pointer;display:inline-block;'>"
		. "<img src='../ikoner/$icon' style='width:20px;height:20px;' alt=''></span></td>";
}

/**
 * Remove a journal's documents (files, folder and rows). For deleting an empty journal in kladdeliste.php.
 */
function journalDocumentsDelete($db, $kladde_id)
{
	$folder = journalDocumentsFolder($db, $kladde_id);
	foreach (journalDocumentsList($kladde_id) as $doc) {
		$file = $folder . '/' . basename($doc['filename']);
		if (is_file($file)) unlink($file);
	}
	if (is_dir($folder)) @rmdir($folder);
	// finance/<kladde_id> too, when it's empty (an empty journal has no line folders)
	if (is_dir(dirname($folder))) @rmdir(dirname($folder));
	db_modify("delete from documents where source = 'kladdeliste' and source_id = '" . (int)$kladde_id . "'", __FILE__ . " linje " . __LINE__);
}
