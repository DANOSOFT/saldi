<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/postApproved.php --- ver 5.0.0 --- 2026-10-19 ---
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20261019 ASR Delvis bogføring ("Bogfør godkendte"): de linjer, der er markeret godkendt, flyttes til en
//              ny kladde, som derefter bogføres med den eksisterende motor (bogfor.php røres ikke). De
//              ikke-godkendte linjer bliver stående i den oprindelige kladde, som beholder sit id og navn.
//              Et bilag bogføres helt eller slet ikke: et bilag med både godkendte og ikke-godkendte linjer
//              bliver stående og rapporteres tilbage ('mixed'). Skriver journal.partial_post i audit_log.
// 20261023 ASR Review (#817): the read, the move and the audit entry run in one transaction with the journal and
//              its lines locked (FOR UPDATE), so an approval changed in another tab meanwhile waits for the move
//              and cannot affect the moved lines; a failed write rolls everything back ('db').

include_once(__DIR__ . '/../../includes/auditLog.php');

/**
 * Postgres gives 't'/'f' for a boolean column, MySQL 1/0; both mean the same here.
 *
 * @param mixed $value The raw column value.
 * @return bool
 */
function kk_godkendt_bool($value) {
	return $value === true || $value === 't' || $value === 1 || $value === '1';
}

/**
 * Split an open journal: move every fully approved bilag to a new journal so it can be posted on its own.
 *
 * Lines are grouped on their bilag number. A group is approved only when every line in it is approved;
 * a group with both approved and unapproved lines ('mixed') is left in the original journal, because
 * posting half a bilag would unbalance it. Lines without a bilag number are grouped by line.
 *
 * @param int    $kladde_id   The open journal (kladdeliste.bogfort = '-').
 * @param string $brugernavn  The user doing it; recorded as oprettet_af on the new journal.
 * @param int    $sprog_id    Language for the new journal's note.
 * @return array{new_kladde_id:int, moved:int, bilag:int, mixed:array<int,string>, error:string}
 *         new_kladde_id is 0 and error is set ('not_open' | 'none' | 'db') when nothing was moved.
 */
function kk_post_approved($kladde_id, $brugernavn, $sprog_id) {
	global $db_modify_fejl;
	$kladde_id = (int)$kladde_id;
	$result = array('new_kladde_id' => 0, 'moved' => 0, 'bilag' => 0, 'mixed' => array(), 'error' => '');

	// One transaction from the first read to the commit. The journal row and its lines are locked (FOR UPDATE), so
	// an approval saved from another tab meanwhile waits until the move is done and cannot touch the moved lines.
	transaktion('begin');
	$r = db_fetch_array(db_select("select bogfort, kladdenote from kladdeliste where id = '$kladde_id' for update", __FILE__ . " linje " . __LINE__));
	if (!$r || $r['bogfort'] != '-') {
		transaktion('rollback');
		$result['error'] = 'not_open';
		return $result;
	}
	$kladdenote = (string)$r['kladdenote'];

	$groups = array();
	$q = db_select("select id, bilag, godkendt from kassekladde where kladde_id = '$kladde_id' order by bilag, id for update", __FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)) {
		$key = ((int)$row['bilag'] > 0) ? 'b' . (int)$row['bilag'] : 'l' . (int)$row['id'];
		if (!isset($groups[$key])) $groups[$key] = array('bilag' => (int)$row['bilag'], 'ids' => array(), 'approved' => 0);
		$groups[$key]['ids'][] = (int)$row['id'];
		if (kk_godkendt_bool($row['godkendt'])) $groups[$key]['approved']++;
	}

	$moveIds = array();
	foreach ($groups as $g) {
		$n = count($g['ids']);
		if ($g['approved'] == $n) {
			$moveIds = array_merge($moveIds, $g['ids']);
			$result['bilag']++;
		} elseif ($g['approved'] > 0) {
			$result['mixed'][] = (string)$g['bilag'];
		}
	}
	if (!$moveIds) {
		transaktion('rollback');
		$result['error'] = 'none';
		return $result;
	}

	// Same id allocation as copy2new.php: kladdeliste.id is assigned by the application, not a sequence.
	$ny_kladde_id = 1;
	$q = db_select("select max(id) as maxid from kladdeliste", __FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($q)) $ny_kladde_id = (int)$row['maxid'] + 1;
	$suffix = findtekst('5568|godkendte fra kladde', $sprog_id) . " $kladde_id " . date('d-m-Y');
	$ny_note = trim($kladdenote) !== '' ? trim($kladdenote) . " – " . $suffix : ucfirst($suffix);
	$ny_note_sql = db_escape_string($ny_note);
	$brugernavn_sql = db_escape_string((string)$brugernavn);
	$kladdedate = date('Y-m-d');
	db_modify("insert into kladdeliste (id, kladdenote, kladdedate, bogfort, oprettet_af) values ('$ny_kladde_id', '$ny_note_sql', '$kladdedate', '-', '$brugernavn_sql')", __FILE__ . " linje " . __LINE__);
	$idList = implode(',', array_map('intval', $moveIds));
	// kladde_id in the WHERE: a line that another session moved or deleted meanwhile is not taken along.
	db_modify("update kassekladde set kladde_id = '$ny_kladde_id' where kladde_id = '$kladde_id' and id in ($idList)", __FILE__ . " linje " . __LINE__);
	if (!empty($db_modify_fejl)) { // a failed insert/update (SD-595 pattern): nothing is moved
		transaktion('rollback');
		$result['error'] = 'db';
		return $result;
	}

	$result['new_kladde_id'] = $ny_kladde_id;
	$result['moved'] = count($moveIds);

	// The audit entry is written before the commit, so the move and its entry commit or roll back together.
	audit_log_write('journal.partial_post', 'kladde', (string)$kladde_id, audit_log_details_json(array(
		'after' => array(
			'new_kladde_id' => $ny_kladde_id,
			'lines' => $result['moved'],
			'bilag' => $result['bilag'],
			'mixed_bilag_left' => $result['mixed'],
		),
	)));
	transaktion('commit');
	return $result;
}
