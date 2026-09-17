<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------- lager/emballage.php -------------------------------------------
// Packaging tax (producentansvar) bill of materials per product card.
// LICENS - Same as the rest of the project (GPL v2 with restrictions).
// Copyright (c) 2003-2026 saldi.dk ApS
// ----------------------------------------------------------------------

@session_start();
$s_id = session_id();

$modulnr = 9;
$title = "Emballage";
$css = "../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
include("../includes/grid.php");
include_once("../includes/emballage_schema.php");

$packagingModuleEnabled = (get_settings_value("packagingModuleEnabled", "items", "off") === "on");
$EN = (isset($sprog_id) && $sprog_id == 2);
if (!$packagingModuleEnabled) {
	print "<p style='padding:1em;'>" . ($EN ? "The packaging module is not active. Activate it under Systemdata &rarr; Miscellaneous &rarr; Order-related options." : "Emballagemodulet er ikke aktiveret. Aktivér det under Systemdata &rarr; Diverse &rarr; Ordrerelaterede valg.") . "</p>";
	exit;
}
ensure_emballage_schema();

$id = (int) if_isset($_GET['id']);
if (!$id && isset($_POST['varer_id'])) $id = (int) $_POST['varer_id'];

if (!$id) {
	print "<p style='padding:1em;'>" . ($EN ? "Missing product id." : "Mangler vare-id.") . "</p>";
	exit;
}

$r = db_fetch_array(db_select("SELECT varenr, beskrivelse FROM varer WHERE id=$id", __FILE__ . " linje " . __LINE__));
if (!$r) {
	print "<p style='padding:1em;'>" . ($EN ? "Product not found." : "Vare ikke fundet.") . "</p>";
	exit;
}
$varenr = htmlspecialchars($r['varenr']);
$varebeskrivelse = htmlspecialchars($r['beskrivelse']);

if (!empty($_POST['delete_id'])) {
	$del = (int) $_POST['delete_id'];
	db_modify("DELETE FROM emballage WHERE id=$del AND varer_id=$id", __FILE__ . " linje " . __LINE__);
	header("Location: emballage.php?id=$id");
	exit;
}

if (isset($_POST['save'])) {
	$line_ids = isset($_POST['line_id']) ? $_POST['line_id'] : array();
	$category = isset($_POST['category']) ? $_POST['category'] : array();
	$type = isset($_POST['type']) ? $_POST['type'] : array();
	$waste_sorting = isset($_POST['waste_sorting']) ? $_POST['waste_sorting'] : array();
	$niveau = isset($_POST['niveau']) ? $_POST['niveau'] : array();
	$end_user = isset($_POST['end_user']) ? $_POST['end_user'] : array();
	$weight = isset($_POST['weight']) ? $_POST['weight'] : array();
	$lineText = isset($_POST['line_text']) ? $_POST['line_text'] : array();

	$count = count($category);
	for ($i = 0; $i < $count; $i++) {
		$lid = isset($line_ids[$i]) ? (int) $line_ids[$i] : 0;
		$cat = db_escape_string(trim($category[$i]));
		$typ = db_escape_string(trim($type[$i]));
		$ws  = db_escape_string(trim($waste_sorting[$i]));
		$niv = db_escape_string(trim($niveau[$i]));
		$eu  = db_escape_string(trim($end_user[$i]));
		$w_raw = trim($weight[$i]);
		$w_raw = str_replace(',', '.', $w_raw);
		$w   = ($w_raw === '') ? 'NULL' : "'" . db_escape_string($w_raw) . "'";
		$txt = db_escape_string(trim($lineText[$i]));

		// Skip completely empty rows on insert
		if (!$lid && !$cat && !$typ && !$ws && !$niv && !$eu && $w_raw === '' && !$txt) continue;

		if ($lid) {
			$qtxt = "UPDATE emballage SET category='$cat', type='$typ', waste_sorting='$ws', niveau='$niv', end_user='$eu', weight=$w, text='$txt' WHERE id=$lid AND varer_id=$id";
		} else {
			$qtxt = "INSERT INTO emballage (varer_id, category, type, waste_sorting, niveau, end_user, weight, text) VALUES ($id, '$cat', '$typ', '$ws', '$niv', '$eu', $w, '$txt')";
		}
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	header("Location: emballage.php?id=$id&saved=1");
	exit;
}

$lines = array();
$q = db_select("SELECT * FROM emballage WHERE varer_id=$id ORDER BY id", __FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($q)) $lines[] = $row;

$h_title = ($EN ? "Packaging" : "Emballage") . " &mdash; $varenr: $varebeskrivelse";
$h_back  = $EN ? 'Back' : findtekst('30|Tilbage', $sprog_id);
$h_saved = $EN ? "Saved." : "Gemt.";
$tekst   = findtekst('154|Dine ændringer er ikke blevet gemt! Tryk OK for at forlade siden uden at gemme.', $sprog_id);
print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>\n";

print "<style>
table.emb { border-collapse:collapse; margin-top:10px; }
table.emb th, table.emb td { border:1px solid #ccc; padding:4px 6px; vertical-align:middle; }
table.emb th { background:#eef; }
table.emb input[type=text], table.emb select { width:100%; box-sizing:border-box; }
table.emb input.weight { text-align:right; }
.emb-actions { margin-top:10px; }
.emb-saved { color:green; margin:6px 0; }
</style>";

$lbl_help = $EN ? 'Help' : findtekst('2564|Hjælp', $sprog_id);

if ($menu == 'T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">";
	print "<div class=\"headerbtnLft headLink\"><a href=\"javascript:confirmClose('varekort.php?id=$id','$tekst')\" accesskey=L title=\"$h_back\"><i class='fa fa-close fa-lg'></i> &nbsp;$h_back</a></div>";
	print "<div class=\"headerTxt\">$h_title</div>";
	print "<div class=\"headerbtnRght headLink\"><a href='varekort.php?id=$id' title='$lbl_help'><i class='fa fa-question-circle fa-lg'></i></a></div>";
	print "</div>";
	print "<div class='content-noside'>";
} else {
	$icon_back = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="24" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';
	$help_icon = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="20px" fill="#FFFFFF"><path d="M478-240q21 0 35.5-14.5T528-290q0-21-14.5-35.5T478-340q-21 0-35.5 14.5T428-290q0 21 14.5 35.5T478-240Zm-36-154h74q0-33 7.5-52t42.5-52q26-26 41-49.5t15-56.5q0-56-41-86t-97-30q-57 0-92.5 30T342-618l66 26q5-18 22.5-39t53.5-21q32 0 48 17.5t16 38.5q0 20-12 37.5T506-526q-44 39-54 59t-10 73Zm38 314q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg>';

	print "<table width='100%' border='0' cellspacing='0' cellpadding='0'><tbody>";
	print "<tr><td align='center' valign='top'>";
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>";

	print "<td width='8%'>
		<a href=\"javascript:confirmClose('varekort.php?id=$id','$tekst')\" accesskey=L>
		<button class='center-btn' style='$buttonStyle; width:100%' type='button' onMouseOver=\"this.style.cursor = 'pointer'\">"
		. $icon_back . $h_back . "</button></a></td>";

	print "<td width='84%' style='$topStyle' align='center'>$h_title</td>";

	print "<td id='tutorial-help' width='8%' style='$buttonStyle'>";
	print "<button class='center-btn' style='$buttonStyle; width:100%' type='button' onMouseOver=\"this.style.cursor='pointer'\">";
	print $help_icon . $lbl_help . "</button></td>";

	print "</tbody></table></td></tr></tbody></table>";
	?>
	<style>
		.headerbtn, .center-btn { display: flex; align-items: center; text-decoration: none; gap: 5px; }
		a:link { text-decoration: none; }
	</style>
	<?php
}

print "<div style='padding:10px;'>";
if (if_isset($_GET['saved'])) print "<div class='emb-saved'>$h_saved</div>";

$cat_opts = emballage_cat_options('category');
$type_opts = emballage_cat_options('type');
$ws_opts = emballage_cat_options('waste_sorting');
$niv_opts = emballage_cat_options('niveau');
$eu_opts = emballage_cat_options('end_user');

print "<form method='post' action='emballage.php?id=$id' id='embForm'>";
print "<input type='hidden' name='varer_id' value='$id'>";
print "<input type='hidden' name='delete_id' id='embDeleteId' value=''>";
print "<script>
function embDelete(lid, msg){ if(!confirm(msg)) return false; document.getElementById('embDeleteId').value=lid; document.getElementById('embForm').submit(); return false; }
</script>";
print "<table class='emb'>";
print "<thead><tr>";
if ($EN) {
	print "<th>Category</th><th>Type</th><th>Waste sorting</th><th>Level</th><th>End user</th><th>Weight (kg)</th><th>Description</th><th></th>";
} else {
	print "<th>Kategori</th><th>Type</th><th>Affaldssortering</th><th>Niveau</th><th>Slutbruger</th><th>Vægt (kg)</th><th>Beskrivelse</th><th></th>";
}
print "</tr></thead><tbody>";

$render_row = function($idx, $line) {
	global $id;
	$lid = isset($line['id']) ? (int) $line['id'] : 0;
	$cat = isset($line['category']) ? $line['category'] : '';
	$typ = isset($line['type']) ? $line['type'] : '';
	$ws  = isset($line['waste_sorting']) ? $line['waste_sorting'] : '';
	$niv = isset($line['niveau']) ? $line['niveau'] : '';
	$eu  = isset($line['end_user']) ? $line['end_user'] : '';
	$w   = isset($line['weight']) && $line['weight'] !== null && $line['weight'] !== '' ? number_format((float)$line['weight'], 4, '.', '') : '';
	$txt = isset($line['text']) ? htmlspecialchars($line['text'], ENT_QUOTES) : '';

	$cat_o = emballage_cat_options('category', $cat);
	$type_o = emballage_cat_options('type', $typ);
	$ws_o = emballage_cat_options('waste_sorting', $ws);
	$niv_o = emballage_cat_options('niveau', $niv);
	$eu_o = emballage_cat_options('end_user', $eu);

	print "<tr>";
	print "<td><input type='hidden' name='line_id[$idx]' value='$lid'><select name='category[$idx]'>$cat_o</select></td>";
	print "<td><select name='type[$idx]'>$type_o</select></td>";
	print "<td><select name='waste_sorting[$idx]'>$ws_o</select></td>";
	print "<td><select name='niveau[$idx]'>$niv_o</select></td>";
	print "<td><select name='end_user[$idx]'>$eu_o</select></td>";
	print "<td><input class='weight' type='text' name='weight[$idx]' value='$w' placeholder='0.0000'></td>";
	print "<td><input type='text' name='line_text[$idx]' value='$txt'></td>";
	if ($lid) {
		global $EN;
		$btn_del = $EN ? 'Delete' : 'Slet';
		$confirm_del = $EN ? 'Delete this packaging line?' : 'Slet denne emballagelinje?';
		print "<td><button type='button' onclick=\"return embDelete($lid, '$confirm_del');\">$btn_del</button></td>";
	} else {
		print "<td>&nbsp;</td>";
	}
	print "</tr>";
};

$idx = 0;
foreach ($lines as $line) {
	$render_row($idx, $line);
	$idx++;
}
// Always render two empty rows for adding new lines
for ($k = 0; $k < 2; $k++) {
	$render_row($idx, array());
	$idx++;
}

print "</tbody></table>";
print "<div class='emb-actions'>";
$btn_save   = $EN ? 'Save' : 'Gem';
$btn_cancel = $EN ? 'Cancel' : 'Annullér';
print "<input type='submit' name='save' value='$btn_save' class='button green medium'>";
print " <a href='emballage.php?id=$id'>$btn_cancel</a>";
print "</div>";
print "</form>";

print "</div>";
if ($menu == 'T') print "</div>"; // close content-noside
