<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/batch_udfyld.php --- ver 5.0.0 --- 2026-10-11 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// Fill in batch number and expiry date on batches already in stock, and split a batch
// into several lots. Made for accounts that turn on batch/expiry handling with stock
// received before the feature existed: those batches have no batch_no/due_date, and
// FEFO sorts them last. Only batch_kob metadata and the split of rest/antal change -
// quantities, cost prices and postings stay the same.
//
// 20261011 CL/ASR Created.
// 20261013 CL/ASR Review: Post/Redirect/Get after saving, no LIKE ESCAPE clause (MySQL), leave guard
//                 on the filter link and search form, lock only the batch row.

@session_start();
$s_id = session_id();
$css = "../css/standard.css";
$modulnr = 12;

global $menu, $bruger_id, $buttonStyle, $topStyle;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

$title = batch_txt('title');

if (!function_exists('batch_udfyld_returside')) {
	/**
	 * Return page for the back link: a plain page in this folder with a simple query string,
	 * otherwise the expiry report, so the value cannot become an open redirect.
	 *
	 * @param string $value Raw returside request value.
	 * @return string Safe link target.
	 */
	function batch_udfyld_returside($value) {
		$value = trim((string)$value);
		if ($value === '' || !preg_match('/^[A-Za-z0-9_]+\.php(\?[A-Za-z0-9_=&%.+-]*)?$/', $value)) {
			return 'udlobsrapport.php';
		}
		return $value;
	}
}

if (!function_exists('batch_udfyld_date')) {
	/**
	 * Normalises a posted date (Y-m-d from <input type=date>, or dd-mm-yyyy typed by hand).
	 *
	 * @param string $value Posted value.
	 * @return string|null|false Y-m-d, NULL for empty, false when invalid.
	 */
	function batch_udfyld_date($value) {
		$value = trim((string)$value);
		if ($value === '') return NULL;
		if (preg_match('/^(\d{1,2})[-.\/](\d{1,2})[-.\/](\d{4})$/', $value, $m)) $value = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return false;
		return $value;
	}
}

$returside = batch_udfyld_returside(ifset($_GET, 'returside', ''));
if ($popup) $returside = "../includes/luk.php";
$returhref = htmlspecialchars($returside, ENT_QUOTES, 'UTF-8');

$vare_id = (int)ifset($_GET, 'vare_id', 0);
$vis_alle = ($vare_id || ifset($_GET, 'alle', '')) ? 1 : 0;
$sog = mb_substr(trim((string)ifset($_GET, 'sog', '')), 0, 60);
$side = max(1, (int)ifset($_GET, 'side', 1));
$pr_side = 100;
# Query string for links back to this view; the page number is added where needed
$viewparam = "vare_id=$vare_id&alle=$vis_alle&sog=" . urlencode($sog) . "&returside=" . urlencode($returside);
$selfurl = "batch_udfyld.php?$viewparam&side=$side";

if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

$fejl = array();
$gemt = 0;
$genvis = false;

########## Save ##########
if (isset($_POST['gem'])) {
	if (!hash_equals($csrf_token, (string)ifset($_POST, 'csrf_token', ''))) {
		$fejl[] = batch_txt('expired');
	} else {
		$p_batch_no = ifset($_POST, 'batch_no', array());
		$p_due_date = ifset($_POST, 'due_date', array());
		$p_split = ifset($_POST, 'split', array());
		$ids = array_unique(array_map('intval', array_merge(array_keys((array)$p_batch_no), array_keys((array)$p_due_date), array_keys((array)$p_split))));
		$userName = db_escape_string(substr($brugernavn, 0, 10));
		$logtime = date('U');

		# Validate everything first: either all changes are saved or none, so a typo on one
		# line never leaves the page half saved.
		$ops = array();
		transaktion('begin');
		# A split reads the reservations on the batch before lowering its rest. Holding this lock
		# until commit stops a reservation being written in between. It is taken before any
		# batch_kob row lock, and only when something is split, so plain edits never wait.
		if ($p_split && $db_type != 'mysql' && $db_type != 'mysqli') {
			db_modify("lock table reservation in share row exclusive mode", __FILE__ . " linje " . __LINE__);
		}
		foreach ($ids as $bk_id) {
			if ($bk_id <= 0) continue;
			$r = db_fetch_array(db_select("select bk.*, v.varenr from batch_kob bk join varer v on v.id = bk.vare_id where bk.id = '$bk_id' and bk.rest > 0 for update of bk", __FILE__ . " linje " . __LINE__));
			if (!$r) {
				# Sold out or removed by someone else since the page was loaded
				$fejl[] = batch_txt('gone') . " (batch $bk_id)";
				continue;
			}
			$varenr = htmlspecialchars($r['varenr'], ENT_QUOTES, 'UTF-8');
			$old_no = (string)$r['batch_no'];
			$old_due = (string)$r['due_date'];
			$new_no = trim((string)ifset($p_batch_no, $bk_id, $old_no));
			if (mb_strlen($new_no) > 100) $new_no = mb_substr($new_no, 0, 100);
			$due_input = (string)ifset($p_due_date, $bk_id, $old_due);
			$new_due = batch_udfyld_date($due_input);
			if ($new_due === false) {
				$fejl[] = "$varenr: " . batch_txt('bad_date') . " (" . htmlspecialchars($due_input, ENT_QUOTES, 'UTF-8') . ")";
				continue;
			}

			# Split: each part becomes a new batch row with the same purchase line, date and cost price
			$parts = array();
			$part_sum = 0;
			foreach ((array)ifset($p_split, $bk_id, array()) as $part) {
				$antal = (float)usdecimal(trim((string)ifset($part, 'antal', '')));
				$p_no = trim((string)ifset($part, 'batch_no', ''));
				if (mb_strlen($p_no) > 100) $p_no = mb_substr($p_no, 0, 100);
				$p_due = batch_udfyld_date(ifset($part, 'due_date', ''));
				if ($antal == 0 && $p_no === '' && !$p_due) continue;
				if ($antal <= 0 || $p_due === false) {
					$fejl[] = "$varenr: " . batch_txt('bad_split');
					continue 2;
				}
				$parts[] = array($antal, $p_no, $p_due);
				$part_sum += $antal;
			}
			if ($parts) {
				$r2 = db_fetch_array(db_select("select coalesce(sum(antal),0) as antal from reservation where batch_kob_id = '$bk_id'", __FILE__ . " linje " . __LINE__));
				$max = (float)$r['rest'] - (float)$r2['antal'];
				if ($part_sum > $max + 0.0001) {
					$fejl[] = "$varenr: " . batch_txt('max_split') . " " . dkdecimal($max) . " (" . findtekst('5008|Restlager', $sprog_id) . " " . dkdecimal($r['rest']) . ")";
					continue;
				}
			}
			$changed = ($new_no !== $old_no || (string)$new_due !== $old_due);
			if ($changed || $parts) $ops[] = array($bk_id, $r['vare_id'], $changed, $old_no, $old_due, $new_no, $new_due, $parts);
		}

		if (!$fejl) {
			foreach ($ops as $op) {
				list($bk_id, $op_vare_id, $changed, $old_no, $old_due, $new_no, $new_due, $parts) = $op;
				$log = array();
				if ($changed) {
					$qtxt = "update batch_kob set batch_no = " . ($new_no === '' ? "NULL" : "'" . db_escape_string($new_no) . "'");
					$qtxt .= ", due_date = " . ($new_due ? "'$new_due'" : "NULL") . " where id = '$bk_id'";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					$log[] = "batchnr. '$old_no' -> '$new_no', udløb '$old_due' -> '$new_due'";
				}
				foreach ($parts as $part) {
					list($antal, $p_no, $p_due) = $part;
					$qtxt = "insert into batch_kob (kobsdate,fakturadate,vare_id,variant_id,linje_id,ordre_id,pris,antal,rest,lager,due_date,batch_no) ";
					$qtxt .= "select kobsdate,fakturadate,vare_id,variant_id,linje_id,ordre_id,pris,'$antal','$antal',lager,";
					$qtxt .= ($p_due ? "'$p_due'" : "NULL") . "," . ($p_no === '' ? "NULL" : "'" . db_escape_string($p_no) . "'");
					$qtxt .= " from batch_kob where id = '$bk_id'";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					db_modify("update batch_kob set antal = antal - '$antal', rest = rest - '$antal' where id = '$bk_id'", __FILE__ . " linje " . __LINE__);
					$log[] = "opdelt " . dkdecimal($antal) . " til batchnr. '$p_no', udløb '$p_due'";
				}
				$reason = db_escape_string("Batch $bk_id: " . implode('; ', $log));
				db_modify("insert into stocklog (item_id,username,initials,correction,reason,logtime) values ('$op_vare_id','$userName','','0','$reason','$logtime')", __FILE__ . " linje " . __LINE__);
				$gemt++;
			}
		}
		transaktion($fejl ? 'rollback' : 'commit');
		# On error the page shows what was typed, so nothing has to be entered again
		if ($fejl) $genvis = true;
	}
	# After a commit, redirect (Post/Redirect/Get) so a browser reload cannot send the same
	# split again; the message comes from the URL. On error the page renders from the POST.
	if (!$fejl) {
		$url = "$selfurl&gemt=$gemt";
		if (!headers_sent()) {
			header("Location: $url", true, 303);
		} else {
			print "<meta http-equiv='refresh' content='0;URL=" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "'>";
		}
		exit;
	}
}
$gemt = max($gemt, (int)ifset($_GET, 'gemt', 0));

########## Header ##########
if ($menu == 'T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">";
	print "<div class=\"headerbtnLft headLink\"><a href='$returhref' accesskey='L'><i class='fa fa-close fa-lg'></i> &nbsp;" . findtekst('30|Tilbage', $sprog_id) . "</a></div>";
	print "<div class=\"headerTxt\">$title</div>";
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";
	print "</div>";
	print "<div class='content-noside'>";
} elseif ($menu == 'S') {
	print "<table width='100%' cellspacing='2'><tbody>";
	print "<tr><td>";
	print "<table width='100%' cellspacing='2'><tbody>";
	print "<td width='10%'><a href='$returhref' accesskey='L'><button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">" . findtekst('30|Tilbage', $sprog_id) . "</button></a></td>";
	print "<td width='80%' align='center' style='$topStyle'>$title</td>";
	print "<td width='10%' align='center' style='$topStyle'><br></td>";
	print "</tbody></table></td></tr></tbody></table>";
} else {
	print "<table width='100%' cellspacing='2'><tbody>";
	print "<tr><td>";
	print "<table width='100%' cellspacing='2'><tbody>";
	print "<td width='10%' $top_bund><a href='$returhref' accesskey='L'>Luk</a></td>";
	print "<td width='80%' $top_bund>$title</td>";
	print "<td width='10%' $top_bund><br></td>";
	print "</tbody></table></td></tr></tbody></table>";
}

print "<style>
#bu-table td{padding:2px 6px;vertical-align:middle}
#bu-table tr.bu-part td{background:#f4f6fb}
#bu-table input.bu-suggest{font-style:italic;color:#6a6a6a}
.bu-msg{padding:.6em 1em;margin:.6em 0;border-radius:4px}
.bu-ok{background:#e6f4ea;border-left:4px solid #2e7d32}
.bu-err{background:#fdecea;border-left:4px solid #c62828}
.bu-info{background:#eef3fb;border-left:4px solid #4785ff}
</style>";

# Defined before the filter link and search form, which use it even when the list is empty
print "<script>
function buLeave() {
	return (typeof docChange === 'undefined' || !docChange) || confirm(" . json_encode(batch_txt('unsaved'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ");
}
</script>";

foreach ($fejl as $f) print "<div class='bu-msg bu-err'>" . $f . "</div>";
if ($gemt && !$fejl) print "<div class='bu-msg bu-ok'>" . batch_txt('saved') . "</div>";

########## Data ##########
$lagernavn = array();
$q = db_select("select kodenr, beskrivelse from grupper where art='LG' order by kodenr", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) $lagernavn[$r['kodenr']] = $r['beskrivelse'];
if (!isset($lagernavn[1])) $lagernavn[1] = findtekst('5012|Hovedlager', $sprog_id);

# Items with expiry tracking (has_due_date) or lot tracking on the product group (box9)
$from = "from batch_kob bk join varer v on v.id = bk.vare_id ";
$from .= "left join grupper g on g.art = 'VG' and g.kodenr = v.gruppe and g.fiscal_year = '" . (int)$regnaar . "' ";
$from .= "where bk.rest > 0 ";
if ($vare_id) {
	$from .= "and bk.vare_id = '$vare_id' ";
} else {
	$from .= "and (v.has_due_date = true or g.box9 = 'on') ";
	if (!$vis_alle) $from .= "and ((v.has_due_date = true and bk.due_date is null) or coalesce(bk.batch_no,'') = '') ";
}
if ($sog !== '') {
	$like = db_escape_string('%' . db_escape_like_pattern(mb_strtolower($sog)) . '%');
	# Backslash is the default LIKE escape on Postgres and MySQL, so no ESCAPE clause (it is a syntax error on MySQL)
	$from .= "and (lower(v.varenr) like '$like' or lower(v.beskrivelse) like '$like') ";
}
# Large accounts can have thousands of batches in stock, so the list comes in pages
$r = db_fetch_array(db_select("select count(*) as antal $from", __FILE__ . " linje " . __LINE__));
$total = $r ? (int)$r['antal'] : 0;
$sider = max(1, (int)ceil($total / $pr_side));
if ($side > $sider) $side = $sider;
$qtxt = "select bk.id, bk.vare_id, bk.kobsdate, bk.rest, bk.lager, bk.due_date, bk.batch_no, ";
$qtxt .= "v.varenr, v.beskrivelse, v.has_due_date, v.default_shelf_life_days, ";
$qtxt .= "coalesce((select sum(r.antal) from reservation r where r.batch_kob_id = bk.id),0) as reserveret ";
$qtxt .= $from;
$qtxt .= "order by v.varenr, bk.lager, case when bk.due_date is null then 1 else 0 end, bk.due_date, bk.kobsdate, bk.id ";
$qtxt .= "limit $pr_side offset " . (($side - 1) * $pr_side);
$rows = array();
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) $rows[] = $r;

if (!$vare_id) {
	print "<div class='bu-msg bu-info'>";
	print batch_txt('intro');
	print "<br><a href='batch_udfyld.php?alle=" . ($vis_alle ? 0 : 1) . "&sog=" . urlencode($sog) . "&returside=" . urlencode($returside) . "' onclick='return buLeave()'>";
	print $vis_alle ? batch_txt('only_missing') : batch_txt('show_all');
	print "</a></div>";
	print "<form method='get' action='batch_udfyld.php' style='margin:.4em 0' onsubmit='return buLeave()'>";
	print "<input type='hidden' name='alle' value='$vis_alle'>";
	print "<input type='hidden' name='returside' value='" . htmlspecialchars($returside, ENT_QUOTES, 'UTF-8') . "'>";
	print "<input class='inputbox' type='search' name='sog' style='width:260px' placeholder='" . batch_txt('search') . "' value='" . htmlspecialchars($sog, ENT_QUOTES, 'UTF-8') . "'> ";
	print "<input type='submit' value='" . findtekst('913|Søg', $sprog_id) . "' style='$buttonStyle'>";
	print "</form>";
}

if (!$rows) {
	print "<p>" . ($sog !== '' ? batch_txt('no_match') : batch_txt('none')) . "</p>";
	if ($menu == 'T') print "</div>";
	print "</body></html>";
	exit;
}

$har_forslag = false;
foreach ($rows as $r) {
	if (!$r['due_date'] && $r['default_shelf_life_days'] && $r['kobsdate']) $har_forslag = true;
}

print "<form method='post' action='" . htmlspecialchars($selfurl, ENT_QUOTES, 'UTF-8') . "' onsubmit='docChange=false'>";
print "<input type='hidden' name='csrf_token' value='$csrf_token'>";
if ($har_forslag) {
	print "<p><button type='button' class='LightButton' onclick='buSuggest()'>" . batch_txt('suggest') . "</button> ";
	print "<span style='color:#6a6a6a'>" . batch_txt('suggest_note') . "</span></p>";
}

print "<table id='bu-table' width='100%' cellspacing='0'><tbody>";
print "<tr>";
print "<td><b>" . findtekst('917|Varenr', $sprog_id) . "</b></td>";
print "<td><b>" . findtekst('914|Beskrivelse', $sprog_id) . "</b></td>";
print "<td><b>" . findtekst('5010|Lager', $sprog_id) . "</b></td>";
print "<td><b>" . findtekst('5009|K&oslash;bsdato', $sprog_id) . "</b></td>";
print "<td align='right'><b>" . findtekst('5008|Restlager', $sprog_id) . "</b></td>";
print "<td><b>" . findtekst('5005|Batchnr.', $sprog_id) . "</b></td>";
print "<td><b>" . findtekst('5001|Udl&oslash;bsdato', $sprog_id) . "</b></td>";
print "<td></td>";
print "</tr>";
print "<tr><td colspan='8'><hr></td></tr>";

foreach ($rows as $r) {
	$id = (int)$r['id'];
	$lager_nr = $r['lager'] ? $r['lager'] : 1;
	$lager_name = isset($lagernavn[$lager_nr]) ? $lagernavn[$lager_nr] : $lager_nr;
	$suggest = '';
	if (!$r['due_date'] && $r['default_shelf_life_days'] && $r['kobsdate']) {
		$suggest = date('Y-m-d', strtotime($r['kobsdate'] . " +" . (int)$r['default_shelf_life_days'] . " days"));
	}
	$max_split = (float)$r['rest'] - (float)$r['reserveret'];
	$v_no = ($genvis && isset($p_batch_no[$id])) ? $p_batch_no[$id] : $r['batch_no'];
	$v_due = ($genvis && isset($p_due_date[$id])) ? $p_due_date[$id] : $r['due_date'];
	print "<tr id='bu-row-$id'>";
	print "<td>" . htmlspecialchars($r['varenr'], ENT_QUOTES, 'UTF-8') . "</td>";
	print "<td>" . htmlspecialchars($r['beskrivelse'], ENT_QUOTES, 'UTF-8') . "</td>";
	print "<td>" . htmlspecialchars($lager_name, ENT_QUOTES, 'UTF-8') . "</td>";
	print "<td>" . ($r['kobsdate'] ? dkdato($r['kobsdate']) : '-') . "</td>";
	print "<td align='right'>" . dkdecimal($r['rest']);
	if ((float)$r['reserveret'] > 0) print "<br><small title='" . batch_txt('reserved') . "'>(" . dkdecimal($r['reserveret']) . " res.)</small>";
	print "</td>";
	print "<td><input class='inputbox' type='text' maxlength='100' style='width:120px' name='batch_no[$id]' value='" . htmlspecialchars((string)$v_no, ENT_QUOTES, 'UTF-8') . "' onchange='docChange=true'></td>";
	print "<td><input class='inputbox' type='date' style='width:140px' name='due_date[$id]' value='" . htmlspecialchars((string)$v_due, ENT_QUOTES, 'UTF-8') . "' data-suggest='$suggest' onchange=\"docChange=true;this.classList.remove('bu-suggest')\"></td>";
	print "<td>";
	if ($max_split > 0) print "<button type='button' class='LightButton' onclick='buSplit($id)' title='" . batch_txt('split_title') . "'>" . batch_txt('split') . "</button>";
	print "</td>";
	print "</tr>";
}
print "<tr><td colspan='8'><hr></td></tr>";
print "</tbody></table>";
if ($total > $pr_side) {
	$fra = ($side - 1) * $pr_side + 1;
	$til = min($total, $side * $pr_side);
	print "<p>" . str_replace(array('$a', '$b', '$n'), array($fra, $til, $total), batch_txt('showing'));
	# Unsaved input on this page is lost when changing page, so the links ask first
	if ($side > 1) print " &nbsp; <a href='batch_udfyld.php?$viewparam&side=" . ($side - 1) . "' onclick='return buLeave()'>&laquo; " . batch_txt('prev') . "</a>";
	if ($side < $sider) print " &nbsp; <a href='batch_udfyld.php?$viewparam&side=" . ($side + 1) . "' onclick='return buLeave()'>" . batch_txt('next') . " &raquo;</a>";
	print "</p>";
}
print "<p><input type='submit' name='gem' value='" . findtekst('3|Gem', $sprog_id) . "' style='$buttonStyle'></p>";
print "</form>";

$t_antal = batch_txt('quantity');
$t_fjern = batch_txt('remove');
$t_split = batch_txt('new_batch');
print "<script>
var buSeq = {};
function buSuggest() {
	document.querySelectorAll('#bu-table input[data-suggest]').forEach(function (el) {
		if (!el.value && el.dataset.suggest) { el.value = el.dataset.suggest; el.classList.add('bu-suggest'); docChange = true; }
	});
}
function buSplit(id, v) {
	var n = buSeq[id] = (buSeq[id] || 0) + 1;
	var anchor = document.getElementById('bu-row-' + id);
	while (anchor.nextElementSibling && anchor.nextElementSibling.classList.contains('bu-part-' + id)) anchor = anchor.nextElementSibling;
	var tr = document.createElement('tr');
	tr.className = 'bu-part bu-part-' + id;
	var p = 'split[' + id + '][' + n + ']';
	tr.innerHTML = '<td></td><td colspan=\"3\" align=\"right\">&#8627; " . addslashes($t_split) . "</td>'
		+ '<td align=\"right\"><input class=\"inputbox\" type=\"text\" style=\"width:60px;text-align:right\" placeholder=\"" . addslashes($t_antal) . "\" name=\"' + p + '[antal]\"></td>'
		+ '<td><input class=\"inputbox\" type=\"text\" maxlength=\"100\" style=\"width:120px\" name=\"' + p + '[batch_no]\"></td>'
		+ '<td><input class=\"inputbox\" type=\"date\" style=\"width:140px\" name=\"' + p + '[due_date]\"></td>'
		+ '<td><button type=\"button\" class=\"LightButton\" onclick=\"this.closest(\\'tr\\').remove()\">" . addslashes($t_fjern) . "</button></td>';
	anchor.parentNode.insertBefore(tr, anchor.nextElementSibling);
	var f = tr.querySelectorAll('input');
	if (v) { f[0].value = v.antal || ''; f[1].value = v.batch_no || ''; f[2].value = v.due_date || ''; }
	else f[0].focus();
	docChange = true;
}
</script>";
if ($genvis) {
	# Put the split lines typed before the error back on the page
	print "<script>";
	foreach ((array)$p_split as $bk_id => $parts) {
		foreach ((array)$parts as $part) {
			$v = array('antal' => (string)ifset($part, 'antal', ''), 'batch_no' => (string)ifset($part, 'batch_no', ''), 'due_date' => (string)ifset($part, 'due_date', ''));
			if ($v['antal'] === '' && $v['batch_no'] === '' && $v['due_date'] === '') continue;
			if (!(int)$bk_id) continue;
			print "if (document.getElementById('bu-row-" . (int)$bk_id . "')) buSplit(" . (int)$bk_id . ", " . json_encode($v, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ");";
		}
	}
	print "</script>";
}

if ($menu == 'T') print "</div>";
?>
</body></html>
