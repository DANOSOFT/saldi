<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/rapport_includes/emballage.php ---
// Packaging tax report (producentansvar). Detailed and Summary views over
// invoiced packaging within a date range, filtered by delivery country.
// Copyright (c) 2003-2026 saldi.dk ApS

function emballage($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til,
                   $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart,
                   $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,
                   $simulering, $lagerbev) {
	global $db, $menu, $sprog_id;
	include_once("../includes/emballage_schema.php");
	ensure_emballage_schema();

	$view = if_isset($_GET['view'], if_isset($_POST['view'], 'detailed'));
	$land = if_isset($_GET['land'], if_isset($_POST['land'], 'Denmark'));

	$startdato = ($dato_fra && $maaned_fra && $aar_fra) ? sprintf('%04d-%02d-%02d', $aar_fra, $maaned_fra, $dato_fra) : '';
	$slutdato  = ($dato_til && $maaned_til && $aar_til) ? sprintf('%04d-%02d-%02d', $aar_til, $maaned_til, $dato_til) : '';

	// Country code translation: form sends "Denmark" but ordrer.land may be 'DK' or empty (default)
	$landFilter = '';
	if ($land === 'Denmark') {
		$landFilter = "AND (o.land = 'DK' OR o.land = 'Danmark' OR o.land = 'Denmark' OR o.land IS NULL OR o.land = '')";
	} elseif ($land) {
		$le = db_escape_string($land);
		$landFilter = "AND o.land = '$le'";
	}

	$dateFilter = '';
	if ($startdato && $slutdato) {
		$dateFilter = "AND o.fakturadate >= '$startdato' AND o.fakturadate <= '$slutdato'";
	}

	$csvfile = "../temp/$db/rapport.csv";
	$csv = fopen($csvfile, "w");

	$EN = (isset($sprog_id) && $sprog_id == 2);
	$title = $EN ? "Report &bull; Packaging" : "Rapport &bull; Emballage";
	include("../includes/topline_settings.php");

	$lbl_close = $EN ? 'Close' : 'Luk';
	$lbl_back  = $EN ? 'Back'  : findtekst('30|Tilbage', $sprog_id);

	// Grid-framework sticky toolbar (matches kontokort_moms / Credit-card report)
	print "<div style=\"position: sticky; top: 0; z-index: 100; background-color: #eeeef0;\">";
	$tilbage_icon  = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';
	print "<table bgcolor='#eeeef0' width='100%' cellpadding='0' cellspacing='0' border='0' id='tableA'><tbody>";
	print "<tr><td colspan=8 align=center>";
	print "<table width='100%' align='center' border='0' cellspacing='4' cellpadding='0'><tbody>";
	print "<td width='5%'>
		<a href=\"rapport.php?rapportart=emballage\" accesskey='L'>
		<button class='headerbtn' type='button' style='$buttonStyle; width: 100%' onMouseOver=\"this.style.cursor = 'pointer'\">$tilbage_icon$lbl_back</button></a></td>";
	print "<td width='75%' align='center' style='$topStyle'>$title</td>";
	print "<td width='5%' align='center' style='$buttonStyle'><a href='$csvfile' style='color:#ffffff'>csv</a></td>";
	print "</tbody></table>";
	print "</td></tr>";
	?>
		<style>
		.headerbtn, .center-btn {
			display: flex;
			align-items: center;
			text-decoration: none;
			gap: 5px;
		}
		a:link { text-decoration: none; }
		</style>
	<?php
	print "</tbody></table>";
	print "</div>";

	// Filter form: POST with submit=ok so the dispatcher re-runs the report
	print "<form method='post' action='rapport.php'>";
	print "<input type='hidden' name='rapportart' value='emballage'>";
	print "<input type='hidden' name='regnaar' value='$regnaar'>";
	print "<input type='hidden' name='submit' value='OK'>";
	print "<input type='hidden' name='dato_fra' value='$dato_fra'>";
	print "<input type='hidden' name='maaned_fra' value='$maaned_fra'>";
	print "<input type='hidden' name='aar_fra' value='$aar_fra'>";
	print "<input type='hidden' name='dato_til' value='$dato_til'>";
	print "<input type='hidden' name='maaned_til' value='$maaned_til'>";
	print "<input type='hidden' name='aar_til' value='$aar_til'>";
	print "<table cellpadding='4'>";
	print "<tr>";
	$startdato_h = htmlspecialchars($startdato);
	$slutdato_h  = htmlspecialchars($slutdato);
	$lbl_from   = $EN ? 'From date' : 'Fra dato';
	$lbl_to     = $EN ? 'To date'   : 'Til dato';
	$lbl_land   = $EN ? 'Country'   : 'Land';
	$lbl_view   = $EN ? 'View'      : 'Visning';
	$lbl_update = $EN ? 'Update'    : 'Opdater';
	print "<td>$lbl_from</td><td><input type='date' value='$startdato_h' onchange=\"var f=this.form;var p=this.value.split('-');f.aar_fra.value=p[0];f.maaned_fra.value=p[1];f.dato_fra.value=p[2];\"></td>";
	print "<td>$lbl_to</td><td><input type='date' value='$slutdato_h' onchange=\"var f=this.form;var p=this.value.split('-');f.aar_til.value=p[0];f.maaned_til.value=p[1];f.dato_til.value=p[2];\"></td>";
	print "<td>$lbl_land</td><td><select name='land'>";
	$countries = array('Denmark', 'Sweden', 'Germany', 'Norway');
	foreach ($countries as $c) {
		$sel = ($c == $land) ? ' selected' : '';
		print "<option value='$c'$sel>$c</option>";
	}
	print "</select></td>";
	print "<td>$lbl_view</td><td><select name='view'>";
	$views = $EN
		? array('detailed' => 'Detailed', 'summary' => 'Summary')
		: array('detailed' => 'Specificeret', 'summary' => 'Summering');
	foreach ($views as $v => $vlabel) {
		$sel = ($v == $view) ? ' selected' : '';
		print "<option value='$v'$sel>$vlabel</option>";
	}
	print "</select></td>";
	print "<td><input type='submit' value='$lbl_update'></td>";
	print "</tr></table>";
	print "</form>";

	if (!$startdato || !$slutdato) {
		print "<p>" . ($EN ? "Select a date range to display the report." : "Vælg en dato-periode for at vise rapporten.") . "</p>";
		fclose($csv);
		return;
	}

	if ($view === 'summary') {
		emballage_summary_view($csv, $dateFilter, $landFilter, $startdato, $slutdato, $EN);
	} else {
		emballage_detailed_view($csv, $dateFilter, $landFilter, $startdato, $slutdato, $EN);
	}

	fclose($csv);
}

function emballage_detailed_view($csv, $dateFilter, $landFilter, $startdato, $slutdato, $EN = false) {
	$qtxt = "SELECT o.fakturadate AS dato, e.category, e.type, e.waste_sorting, e.niveau,
	                COALESCE(NULLIF(e.end_user,''), a.enduser_type) AS slutbruger,
	                (e.weight * ol.antal) AS vaegt,
	                o.land
	         FROM ordrelinjer ol
	         JOIN ordrer o ON ol.ordre_id = o.id
	         JOIN emballage e ON e.varer_id = ol.vare_id
	         LEFT JOIN adresser a ON a.id = o.konto_id
	         WHERE o.status = '3' $dateFilter $landFilter
	         ORDER BY o.fakturadate, e.category, e.type";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

	if ($EN) {
		fwrite($csv, "Date;Category;Type;WasteSorting;Level;EndUser;Weight;Country\n");
		$h_title = "Detailed &mdash; $startdato to $slutdato";
		$th = "<th>Date</th><th>Category</th><th>Type</th><th>Waste sorting</th><th>Level</th><th>End user</th><th>Weight</th><th>Country</th>";
	} else {
		fwrite($csv, "Dato;Kategori;Type;Affaldssortering;Niveau;Slutbruger;Vaegt;Land\n");
		$h_title = "Specificeret &mdash; $startdato til $slutdato";
		$th = "<th>Dato</th><th>Kategori</th><th>Type</th><th>Affaldssortering</th><th>Niveau</th><th>Slutbruger</th><th>Vægt</th><th>Land</th>";
	}
	print "<h3 style='padding-left:6px;'>$h_title</h3>";
	print "<table border='1' cellspacing='0' cellpadding='3' style='border-collapse:collapse; margin-left:6px;'>";
	print "<tr style='background:#eef;'>$th</tr>";

	$total = 0; $rows = 0;
	while ($r = db_fetch_array($q)) {
		$dato = htmlspecialchars($r['dato']);
		$cat  = htmlspecialchars($r['category']);
		$typ  = htmlspecialchars($r['type']);
		$ws   = htmlspecialchars($r['waste_sorting']);
		$niv  = htmlspecialchars($r['niveau']);
		$slu  = htmlspecialchars($r['slutbruger']);
		$wRaw = (float) $r['vaegt'];
		$w    = number_format($wRaw, 4, ',', '.');
		$wCsv = number_format($wRaw, 4, '.', '');
		$la   = htmlspecialchars($r['land']);
		print "<tr><td>$dato</td><td>$cat</td><td>$typ</td><td>$ws</td><td>$niv</td><td>$slu</td><td align='right'>$w</td><td>$la</td></tr>";
		fwrite($csv, "$dato;$cat;$typ;$ws;$niv;$slu;$wCsv;$la\n");
		$total += $wRaw;
		$rows++;
	}
	print "</table>";
	$lbl_lines = $EN ? "Number of lines" : "Antal linjer";
	$lbl_total = $EN ? "Total weight"    : "Samlet vægt";
	print "<p style='padding-left:6px;'>$lbl_lines: $rows. $lbl_total: " . number_format($total, 4, ',', '.') . " kg.</p>";
	if ($total > 0) {
		fwrite($csv, ";;;;;Total;" . number_format($total, 4, '.', '') . ";\n");
	}
}

function emballage_summary_view($csv, $dateFilter, $landFilter, $startdato, $slutdato, $EN = false) {
	// Periode = YYYYMM of fakturadate (e.g. '202412')
	$qtxt = "SELECT to_char(o.fakturadate, 'YYYYMM') AS periode,
	                e.category, e.waste_sorting,
	                COALESCE(NULLIF(e.end_user,''), a.enduser_type) AS slutbruger,
	                SUM(e.weight * ol.antal) AS vaegt
	         FROM ordrelinjer ol
	         JOIN ordrer o ON ol.ordre_id = o.id
	         JOIN emballage e ON e.varer_id = ol.vare_id
	         LEFT JOIN adresser a ON a.id = o.konto_id
	         WHERE o.status = '3' $dateFilter $landFilter
	         GROUP BY periode, e.category, e.waste_sorting, slutbruger
	         ORDER BY periode, e.category, e.waste_sorting, slutbruger";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

	if ($EN) {
		fwrite($csv, "Period;Category;WasteSorting;EndUser;Weight\n");
		$h_title = "Summary &mdash; $startdato to $slutdato";
		$th = "<th>Period</th><th>Category</th><th>Waste sorting</th><th>End user</th><th>Weight</th>";
	} else {
		fwrite($csv, "Periode;Kategori;Affaldssortering;Slutbruger;Vaegt\n");
		$h_title = "Summering &mdash; $startdato til $slutdato";
		$th = "<th>Periode</th><th>Kategori</th><th>Affaldssortering</th><th>Slutbruger</th><th>Vægt</th>";
	}
	print "<h3 style='padding-left:6px;'>$h_title</h3>";
	print "<table border='1' cellspacing='0' cellpadding='3' style='border-collapse:collapse; margin-left:6px;'>";
	print "<tr style='background:#eef;'>$th</tr>";

	$total = 0; $rows = 0;
	while ($r = db_fetch_array($q)) {
		$pe   = htmlspecialchars($r['periode']);
		$cat  = htmlspecialchars($r['category']);
		$ws   = htmlspecialchars($r['waste_sorting']);
		$slu  = htmlspecialchars($r['slutbruger']);
		$wRaw = (float) $r['vaegt'];
		$w    = number_format($wRaw, 4, ',', '.');
		$wCsv = number_format($wRaw, 4, '.', '');
		print "<tr><td>$pe</td><td>$cat</td><td>$ws</td><td>$slu</td><td align='right'>$w</td></tr>";
		fwrite($csv, "$pe;$cat;$ws;$slu;$wCsv\n");
		$total += $wRaw;
		$rows++;
	}
	print "</table>";
	$lbl_groups = $EN ? "Number of groups" : "Antal grupper";
	$lbl_total  = $EN ? "Total weight"     : "Samlet vægt";
	print "<p style='padding-left:6px;'>$lbl_groups: $rows. $lbl_total: " . number_format($total, 4, ',', '.') . " kg.</p>";
	if ($total > 0) {
		fwrite($csv, ";;;Total;" . number_format($total, 4, '.', '') . "\n");
	}
}
