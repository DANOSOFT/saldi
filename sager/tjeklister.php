<?php
@session_start();
$s_id = session_id();
// ---- sager/tjeklister.php --- lap 5.0.0 --- 2026.10.09 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20261009 Sawaneh Checklists for cases moved out of Indstillinger into the Sager module (settings redesign §4
//                  "(Checklists)", phase 4e): the editor that lived in systemdata/diverse.php?sektion=tjekliste and
//                  sys_div_func.php tjekliste(). Phases are renumbered on save, not at render time (B-T1).

$bg = "nix";
$header = 'nix';
$menu_sager = 'id="menuActive"';
$menu_planlaeg = $menu_dagbog = $menu_kunder = $menu_loen = $menu_ansatte = $menu_certificering = $menu_medarbejdermappe = NULL;
$modulnr = 0;
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$funktion = 'tjeklister';
$sag_id = 0;
$sort = $nysort = $vis = NULL;
$ret = (int) if_isset($_GET['ret'], 0);
$canEdit = substr((string) $sag_rettigheder, 2, 1) ? true : false;

if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && hash_equals($csrf_token, (string) $_POST['csrf_token'])) {
	sager_tjeklister_save($_POST);
	header("Location: tjeklister.php");
	exit;
}

/**
 * Store the posted checklist changes: renamed points, new lists/groups/points, and renumber the phases afterwards.
 *
 * @param array<string, mixed> $post
 */
function sager_tjeklister_save(array $post): void
{
	$id            = isset($post['id']) && is_array($post['id']) ? $post['id'] : array();
	$tjekantal     = (int) if_isset($post['tjekantal'], 0);
	$fase          = isset($post['fase']) && is_array($post['fase']) ? $post['fase'] : array();
	$tjekpunkt     = isset($post['tjekpunkt']) && is_array($post['tjekpunkt']) ? $post['tjekpunkt'] : array();
	$ny_tjekgruppe = isset($post['ny_tjekgruppe']) && is_array($post['ny_tjekgruppe']) ? $post['ny_tjekgruppe'] : array();
	$nyt_tjekpunkt = isset($post['nyt_tjekpunkt']) && is_array($post['nyt_tjekpunkt']) ? $post['nyt_tjekpunkt'] : array();
	$liste_id      = isset($post['liste_id']) && is_array($post['liste_id']) ? $post['liste_id'] : array();
	$gruppe_id     = isset($post['gruppe_id']) && is_array($post['gruppe_id']) ? $post['gruppe_id'] : array();
	$retId         = (int) if_isset($post['ret'], 0);

	$ny_tjekliste = trim((string) if_isset($post['ny_tjekliste'], ''));
	if ($ny_tjekliste !== '') {
		$r = db_fetch_array(db_select("select max(fase) as fase from tjekliste where assign_to = 'sager'", __FILE__ . " linje " . __LINE__));
		$nf = (int) $r['fase'] + 1;
		db_modify("insert into tjekliste (tjekpunkt, assign_id, assign_to, fase) values ('" . db_escape_string($ny_tjekliste) . "', '0', 'sager', '$nf')", __FILE__ . " linje " . __LINE__);
	}
	for ($x = 1; $x <= $tjekantal; $x++) {
		$rowId = (int) if_isset($id[$x], 0);
		if (!$rowId) continue;
		$rowFase = (float) if_isset($fase[$x], 0);
		if ($retId && $retId == $rowId && trim((string) if_isset($tjekpunkt[$x], '')) !== '') {
			db_modify("update tjekliste set tjekpunkt = '" . db_escape_string(trim((string) $tjekpunkt[$x])) . "' where id = '$rowId'", __FILE__ . " linje " . __LINE__);
		}
		if (trim((string) if_isset($ny_tjekgruppe[$x], '')) !== '') {
			db_modify("insert into tjekliste (tjekpunkt, assign_id, assign_to, fase) values ('" . db_escape_string(trim((string) $ny_tjekgruppe[$x])) . "', '" . (int) if_isset($liste_id[$x], 0) . "', 'sager', '$rowFase')", __FILE__ . " linje " . __LINE__);
		}
		if (trim((string) if_isset($nyt_tjekpunkt[$x], '')) !== '') {
			db_modify("insert into tjekliste (tjekpunkt, assign_id, assign_to, fase) values ('" . db_escape_string(trim((string) $nyt_tjekpunkt[$x])) . "', '" . (int) if_isset($gruppe_id[$x], 0) . "', 'sager', '$rowFase')", __FILE__ . " linje " . __LINE__);
		}
	}
	sager_tjeklister_renumber();
}

/**
 * Phases 1..n in list order; groups and points take their list's phase. The old page did this while rendering.
 */
function sager_tjeklister_renumber(): void
{
	$fasenr = 0;
	$q = db_select("select id, fase from tjekliste where assign_to = 'sager' and assign_id = '0' order by fase, id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$fasenr++;
		$listId = (int) $r['id'];
		if ((float) $r['fase'] != $fasenr) {
			db_modify("update tjekliste set fase = '$fasenr' where id = '$listId'", __FILE__ . " linje " . __LINE__);
		}
		db_modify("update tjekliste set fase = '$fasenr' where assign_to = 'sager' and (assign_id = '$listId' or assign_id in (select id from tjekliste where assign_to = 'sager' and assign_id = '$listId')) and fase != '$fasenr'", __FILE__ . " linje " . __LINE__);
	}
}

/**
 * The editor: one block per checklist with its groups and points, rename on click, new rows at the bottom of each block.
 */
function sager_tjeklister_form(int $ret, bool $canEdit, string $csrf): void
{
	global $sprog_id;
	$h = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
	$id = $tjekpunkt = $fase = $assign_id = $punkt_id = $gruppe_id = $liste_id = array();
	$x = 0;
	$q = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' order by fase, id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x] = $r['id']; $tjekpunkt[$x] = $r['tjekpunkt']; $fase[$x] = $r['fase'] * 1; $assign_id[$x] = 0;
		$punkt_id[$x] = 0; $gruppe_id[$x] = 0; $liste_id[$x] = $id[$x];
		$q2 = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '$id[$x]' order by tjekpunkt", __FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$x++;
			$id[$x] = $r2['id']; $tjekpunkt[$x] = $r2['tjekpunkt']; $assign_id[$x] = $r2['assign_id'] * 1; $fase[$x] = $fase[$x - 1];
			$punkt_id[$x] = 0; $gruppe_id[$x] = $id[$x]; $liste_id[$x] = $liste_id[$x - 1];
			$q3 = db_select("select * from tjekliste where id != $id[$x] and assign_to = 'sager' and assign_id = '$id[$x]' order by tjekpunkt", __FILE__ . " linje " . __LINE__);
			while ($r3 = db_fetch_array($q3)) {
				$x++;
				$id[$x] = $r3['id']; $tjekpunkt[$x] = $r3['tjekpunkt']; $assign_id[$x] = $r3['assign_id'] * 1; $fase[$x] = $fase[$x - 1];
				$punkt_id[$x] = $id[$x]; $gruppe_id[$x] = $gruppe_id[$x - 1]; $liste_id[$x] = $liste_id[$x - 1];
			}
		}
	}
	$n = count($id);
	$ro = $canEdit ? '' : ' disabled';
	print "<form name='tjeklister' action='tjeklister.php' method='post'>\n";
	print "<input type='hidden' name='csrf_token' value='" . $h($csrf) . "'>\n";
	print "<input type='hidden' name='tjekantal' value='$n'>\n";
	print "<input type='hidden' name='ret' value='$ret'>\n";
	print "<table class='tableSager' cellspacing='0' cellpadding='4' style='width:100%'><tbody>\n";
	for ($x = 1; $x <= $n; $x++) {
		print "<input type='hidden' name='id[$x]' value='" . (int) $id[$x] . "'>\n";
		print "<input type='hidden' name='fase[$x]' value='" . $h($fase[$x]) . "'>\n";
		$link = "<a href='tjeklister.php?ret=" . (int) $id[$x] . "' title='" . $h(findtekst('1727|Klik for at ændre navnet', $sprog_id)) . "' style='text-decoration:none'>" . $h($tjekpunkt[$x]) . "</a>";
		$input = "<input class='inputbox' type='text' name='tjekpunkt[$x]' size='30' value='" . $h($tjekpunkt[$x]) . "'$ro>";
		if (!$gruppe_id[$x] && !$punkt_id[$x]) {
			print "<tr><td colspan='2'><hr></td></tr>\n";
			print "<tr><td colspan='2'><big><b>" . findtekst('2836|Fase', $sprog_id) . " " . (int) $fase[$x] . ": " . ($ret == $id[$x] ? $input : $link) . "</b></big></td></tr>\n";
		} elseif ($gruppe_id[$x] && !$punkt_id[$x]) {
			print "<tr><td style='padding-left:24px'><b>" . ($ret == $id[$x] ? $input : $link) . "</b></td><td></td></tr>\n";
		} else {
			print "<tr><td style='padding-left:48px'>" . ($ret == $id[$x] ? $input : $link) . "</td><td></td></tr>\n";
		}
		if ($gruppe_id[$x] && (!isset($gruppe_id[$x + 1]) || $gruppe_id[$x] != $gruppe_id[$x + 1])) {
			print "<input type='hidden' name='gruppe_id[$x]' value='" . (int) $gruppe_id[$x] . "'>\n";
			print "<tr><td style='padding-left:48px'>" . findtekst('7022|Nyt tjekpunkt', $sprog_id) . "</td><td><input class='inputbox' type='text' name='nyt_tjekpunkt[$x]' size='30' value=''$ro></td></tr>\n";
		}
		if (!isset($liste_id[$x + 1]) || $liste_id[$x] != $liste_id[$x + 1]) {
			print "<input type='hidden' name='liste_id[$x]' value='" . (int) $liste_id[$x] . "'>\n";
			print "<tr><td style='padding-left:24px'><b>" . findtekst('1334|Ny tjek gruppe', $sprog_id) . "</b></td><td><input class='inputbox' type='text' name='ny_tjekgruppe[$x]' size='30' value=''$ro></td></tr>\n";
		}
	}
	print "<tr><td colspan='2'><hr></td></tr>\n";
	print "<tr><td><b>" . findtekst('1333|Ny tjekliste', $sprog_id) . "</b></td><td><input class='inputbox' type='text' name='ny_tjekliste' size='30' value=''$ro></td></tr>\n";
	if ($canEdit) {
		print "<tr><td colspan='2' style='padding-top:12px'><input class='button green medium' type='submit' accesskey='g' value='" . $h(findtekst('471|Gem/opdatér', $sprog_id)) . "' name='submit'></td></tr>\n";
	}
	print "</tbody></table>\n</form>\n";
}

ini_set("display_errors", "0");
print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
	<head>
		<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
		<meta name=\"viewport\" content=\"width=1024\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/main.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/search.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/form.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/print.css\" media=\"print\">
		<script type=\"text/javascript\" src=\"../javascript/jquery-1.8.0.min.js\"></script>
		<title>" . findtekst('796|Tjeklister', $sprog_id) . "</title>
	</head>
	<body>
		<div id=\"wrapper\">";
include("../includes/sagsmenu.php");
print "<div id=\"breadcrumbbar\"><ul id=\"breadcrumb\">
	<li><a href=\"sager.php\" title=\"" . findtekst('2781|Hjem', $sprog_id) . "\"><img src=\"../img/home.png\" alt=\"" . findtekst('2781|Hjem', $sprog_id) . "\" class=\"home\" /></a></li>
	<li><a href=\"sager.php\">" . findtekst('2774|Sager', $sprog_id) . "</a></li>
	<li>" . findtekst('796|Tjeklister', $sprog_id) . "</li>
</ul></div><!-- end of breadcrumbbar -->
<div id=\"leftmenuholder\">";
include("leftmenu.php");
print "</div><!-- end of leftmenuholder -->
<div class=\"maincontent\">
	<h2>" . findtekst('796|Tjeklister', $sprog_id) . "</h2>
	<p>" . findtekst('7021|Tjeklister til sager: faser, grupper og tjekpunkter. Flyttet hertil fra Indstillinger.', $sprog_id) . "</p>";
sager_tjeklister_form($ret, $canEdit, $csrf_token);
print "</div><!-- end of maincontent -->
		</div><!-- end of wrapper -->
	</body>
</html>";
