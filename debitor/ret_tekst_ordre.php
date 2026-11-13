<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/ret_tekst_ordre.php --- ver 5.1.1 --- 2026-11-13 ---
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
// 20261111 CL/LH Added to git from the Stillads install, where PHR wrote it.
//                The pencil in the order-text lookup (tekstopslag() in includes/ordrefunc.php) links here; without the file it gave 404.
//                The ids are cast and the text is escaped before they reach SQL, and the page escapes what it prints.
// 20261113 CL/LH The page sets $modulnr = 5 before online.php, so only users with order rights can change a text.
//                Texts are escaped in the account's charset, so a LATIN9 text with Danish letters no longer shows empty and is not saved over with ''.
//                After Gem the page returns to the order with a meta refresh, as online.php has already printed output.
//                The sager header and menu variables are set, so the page renders without undefined-variable warnings.

// Edits one standard order text (ordretekster) from the text lookup on a case order.

/**
 * Session/account context supplied by includes/online.php.
 * @var string $db
 * @var string $brugernavn
 * @var string $db_encode   from includes/connect.php: 'UTF8' or 'LATIN9'
 */
@session_start();
$s_id = session_id();

// online.php reads these: $modulnr for the rights check, $bg/$header so it prints no page head of its own.
$bg     = "nix";
$header = 'nix';
$title  = 'Ret beskrivelse';

$modulnr = 5;

$menu_sager            = 'id="menuActive"';
$menu_planlaeg         = NULL;
$menu_dagbog           = NULL;
$menu_kunder           = NULL;
$menu_loen             = NULL;
$menu_ansatte          = NULL;
$menu_certificering    = NULL;
$menu_medarbejdermappe = NULL;
$viewport_width        = NULL;

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");

// online.php sets $charset only when it prints the page head; the sager header prints its own.
$charset = ($db_encode == 'UTF8') ? 'UTF-8' : 'ISO-8859-1';
$flags   = ENT_QUOTES | ENT_SUBSTITUTE;

$id       = (int)ifset($_GET, 'id', 0);
$tekst_id = (int)ifset($_GET, 'tekst_id', 0);

if (isset($_POST['opdater']) && $tekst_id) {
	$ordretekst = (string)ifset($_POST, 'ordretekst', '');
	db_modify("update ordretekster set tekst='" . db_escape_string($ordretekst) . "' where id = '$tekst_id'", __FILE__ . " linje " . __LINE__);
	// online.php has printed its button style block by now, so a location header would be too late.
	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id&amp;ret_tekst=$id\">\n";
	exit();
}

$r = db_fetch_array(db_select("select sag_id, konto_id, nr from ordrer where id='$id'", __FILE__ . " linje " . __LINE__));
$sag_id   = (int)ifset($r, 'sag_id', 0);
$konto_id = (int)ifset($r, 'konto_id', 0);
$nr       = htmlspecialchars((string)ifset($r, 'nr', ''), $flags, $charset);

$r = db_fetch_array(db_select("select * from sager where id='$sag_id'", __FILE__ . " linje " . __LINE__));
$sagsnr      = htmlspecialchars((string)ifset($r, 'sagsnr', ''), $flags, $charset);
$beskrivelse = htmlspecialchars((string)ifset($r, 'beskrivelse', ''), $flags, $charset);
$udf_addr1   = htmlspecialchars((string)ifset($r, 'udf_addr1', ''), $flags, $charset);
$udf_postnr  = htmlspecialchars((string)ifset($r, 'udf_postnr', ''), $flags, $charset);
$udf_bynavn  = htmlspecialchars((string)ifset($r, 'udf_bynavn', ''), $flags, $charset);

$r = db_fetch_array(db_select("select tekst from ordretekster where id='$tekst_id'", __FILE__ . " linje " . __LINE__));
$ordretekst = (string)ifset($r, 'tekst', '');

include_once(__DIR__ . '/../includes/top_header_sager.php');
include_once(__DIR__ . '/../includes/top_sagsmenu.php');
print "<div id=\"breadcrumbbar\">
	<ul id=\"breadcrumb\">
		<li><a href=\"../sager/sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a></li>
		<li><a href=\"../sager/sager.php?funktion=vis_sag&amp;sag_id=$sag_id&amp;konto_id=$konto_id\" title=\"Sag: $sagsnr, $beskrivelse, $udf_addr1, $udf_postnr $udf_bynavn\">Tilbage til sag $sagsnr</a></li>\n";
print "		<li><a href=\"ordre.php?sag_id=$sag_id&amp;konto_id=$konto_id&amp;tjek=$id&amp;id=$id&amp;returside=sager\" title=\"Tilbud til sag: $sagsnr, $beskrivelse, $udf_addr1, $udf_postnr $udf_bynavn\">Tilbud $nr</a></li>\n";
print "		<li><a href=\"ordre.php?id=$id&amp;ret_tekst=$id\">Opslag</a></li>\n";
print "		<li>Ret beskrivelse</li>
	</ul>
</div><!-- end of breadcrumbbar -->\n";

print "<div class=\"maincontentLargeHolder\">\n";
print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
print "<tbody><tr><td valign=\"top\" align=\"center\">\n";
print "<form name=\"ordre\" action=\"ret_tekst_ordre.php?id=$id&amp;tekst_id=$tekst_id\" method=\"post\">\n";
print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align=\"center\" >\n";
print "<tbody>\n";
print "<tr>\n";
print "<td valign=\"top\"><span style=\"display:block;margin-top:3px;\">Ret tekst</span></td><td><textarea class=\"textAreaSager autosize kontrolskema_font\" name=\"ordretekst\" rows=\"2\" cols=\"10\" style=\"height:16px;width:800px;\">" . htmlspecialchars($ordretekst, $flags, $charset) . "</textarea></td>\n";
print "<td valign=\"bottom\"><input type=\"submit\" class=\"button gray medium\" value=\"Gem/Opdater\" name=\"opdater\"></td>\n";
print "</tr>\n";
print "</tbody></table></form></td></tr></tbody></table>\n";
print "</div>";
print "</div><!-- end of wrapper -->\n";
print "</body>\n";
print "</html>\n";
?>
