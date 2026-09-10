<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pbs_gensend.php --- patch 5.0.0 --- 2026-09-08 ---
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
//
// 20260908 CL/Sawaneh SST-763: PBS history of one invoice (every attempt, batch and registered
//                     result) and an explicit "Gensend til PBS" for invoices Nets rejected.
//                     Post/Redirect/Get; the write itself is in pbs_gensend() (includes/pbsfunc.php).
// 20260910 CL/NTR SST-763: tekst ids 5170-5190 moved to 3385-3404; 5180 replaced by existing 828 (Fakturanr.).

@session_start();
$s_id = session_id();

$modulnr = 5;
$title = "PBS";
$css = "../css/standard.css";
$header = "nix";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/forfaldsdag.php");
include(__DIR__ . "/../includes/pbsfunc.php");

/** @var string $rettigheder set by includes/online.php */
/** @var int $sprog_id set by includes/online.php */
/** @var string $db_encode set by includes/connect.php */

$charset = ($db_encode == "UTF8") ? "UTF-8" : "ISO-8859-1";

$id = intval(if_isset($_GET, 0, 'id'));
if (!$id) {
	$id = intval(if_isset($_POST, 0, 'id'));
}

if (isset($_POST['sidste_id'])) {
	if (substr($rettigheder, $modulnr, 1) < '1') {
		$_SESSION['pbs_gensend_besked'] = findtekst('3400|Du har ikke rettigheder til at gensende til PBS', $sprog_id);
	} else {
		$svar = pbs_gensend($id, if_isset($_POST, '', 'ref'), if_isset($_POST, 0, 'sidste_id'));
		$_SESSION['pbs_gensend_besked'] = $svar['besked'];
	}
	print "<meta http-equiv=\"refresh\" content=\"0;URL=pbs_gensend.php?id=$id\">";
	exit;
}

$besked = if_isset($_SESSION, '', 'pbs_gensend_besked');
unset($_SESSION['pbs_gensend_besked']);

$s = pbs_ordre_status($id);
$o = $s['ordre'];

print "<!DOCTYPE html>\n<html>\n<head><title>$title</title><meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\">\n";
print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\" />";
print "</head><body>";

if ($popup) $returside = "../includes/luk.php";
else $returside = "ordre.php?id=$id";

print "<table width=\"100%\" border=\"0\"><tbody>";
print "<tr><td height=\"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><a href=\"$returside\" accesskey=\"L\">" . findtekst('2172|Luk', $sprog_id) . "</a></td>";
print "<td width=\"80%\" $top_bund>" . findtekst('3394|PBS-historik', $sprog_id) . "</td>";
print "<td width=\"10%\" $top_bund><br></td>";
print "</tbody></table>";
print "</td></tr>\n";

if ($besked) {
	print "<tr><td align=\"center\"><b>" . htmlspecialchars($besked) . "</b></td></tr>";
}

if ($o) {
	list($fdd, $fmm, $fyy) = explode("-", forfaldsdag($o['fakturadate'], $o['betalingsbet'], $o['betalingsdage']));
	print "<tr><td><table><tbody>";
	print "<tr><td>" . findtekst('828|Fakturanr.', $sprog_id) . "</td><td><b>" . htmlspecialchars($o['fakturanr']) . "</b></td></tr>";
	print "<tr><td>" . findtekst('35|Kunde', $sprog_id) . "</td><td>" . htmlspecialchars($o['kontonr']) . " " . htmlspecialchars($o['firmanavn']) . "</td></tr>";
	print "<tr><td>" . findtekst('934|Beløb', $sprog_id) . "</td><td>" . dkdecimal($o['sum'] + $o['moms'], 2) . "</td></tr>";
	print "<tr><td>" . findtekst('1094|Fakturadato', $sprog_id) . "</td><td>" . dkdato($o['fakturadate']) . "</td></tr>";
	print "<tr><td>" . findtekst('1164|Forfaldsdato', $sprog_id) . "</td><td>$fdd-$fmm-$fyy</td></tr>";
	print "</tbody></table></td></tr>";
}

print "<tr><td><hr></td></tr>";
print "<tr><td>" . htmlspecialchars($s['forklaring']) . "</td></tr>";

if (count($s['forsoeg'])) {
	print "<tr><td><br><table width=\"100%\" border=\"0\" cellspacing=\"1\"><tbody>";
	print "<tr><td>#</td><td>" . findtekst('3395|Leverance', $sprog_id) . "</td><td>" . findtekst('438|Dato', $sprog_id) . "</td>";
	print "<td>" . findtekst('65|Oprettet', $sprog_id) . "</td><td>" . findtekst('990|Bruger', $sprog_id) . "</td>";
	print "<td>" . findtekst('494|Status', $sprog_id) . "</td><td>" . findtekst('3399|Gensendt fra forsøg', $sprog_id) . "</td></tr>";
	foreach ($s['forsoeg'] as $f) {
		if (!$f['afsendt']) {
			$status = findtekst('3402|I kø', $sprog_id);
		} elseif ($f['resultat'] == 'afvist') {
			$status = findtekst('3011|Afvist', $sprog_id) . " " . dkdato($f['resultat_dato']) . ": " . htmlspecialchars($f['resultat_ref']);
		} else {
			$status = findtekst('3396|Afsendt', $sprog_id) . " - " . findtekst('3397|Afventer svar', $sprog_id);
		}
		$bruger = htmlspecialchars(if_isset($f, '', 'brugernavn'));
		$oprettet = $f['oprettet'] ? substr($f['oprettet'], 0, 16) : '';
		$liste_dato = $f['liste_date'] ? dkdato($f['liste_date']) : '';
		$fra = $f['gensendt_fra'] ? $f['gensendt_fra'] : '';
		print "<tr><td>$f[id]</td><td>$f[liste_id]</td><td>$liste_dato</td><td>$oprettet</td><td>$bruger</td><td>$status</td><td>$fra</td></tr>";
	}
	print "</tbody></table></td></tr>";
}

if ($s['kan_gensendes']) {
	print "<tr><td><br><form method=\"post\" action=\"pbs_gensend.php\" onsubmit=\"this.gensend.disabled=true;\">";
	print "<input type=\"hidden\" name=\"id\" value=\"$id\">";
	print "<input type=\"hidden\" name=\"sidste_id\" value=\"" . intval($s['sidste']['id']) . "\">";
	print findtekst('3392|Afvisning fra Nets (advis/fejlkode og dato)', $sprog_id) . "<br>";
	print "<textarea name=\"ref\" rows=\"3\" cols=\"60\"" . ($s['kraever_ref'] ? " required" : "") . "></textarea><br>";
	print "<input type=\"submit\" class=\"button gray medium\" name=\"gensend\" value=\"" . findtekst('3385|Gensend til PBS', $sprog_id) . "\">";
	print "</form></td></tr>";
}
print "</tbody></table>";
print "</body></html>";
