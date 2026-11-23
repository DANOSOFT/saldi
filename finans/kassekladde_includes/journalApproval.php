<?php
// 20261122 CDX/ASR Bind approvals to the displayed posting values and validate them under row locks.

/**
 * Fingerprint the stored values shown when a line is reviewed, excluding approval metadata.
 * @param array<string, mixed> $row Stored journal line.
 * @return string SHA-256 content fingerprint, stable across approval toggles.
 */
function kk_approval_snapshot(array $row) {
	$values = array();
	foreach (array('id', 'kladde_id', 'bilag', 'transdate', 'beskrivelse', 'd_type', 'debet', 'k_type', 'kredit', 'faktura', 'amount', 'debetvat', 'kreditvat', 'momsfri', 'afd', 'projekt', 'ansat', 'medarb', 'valuta', 'valutakurs', 'forfaldsdate', 'betal_id') as $field) {
		$values[$field] = (string)($row[$field] ?? '');
	}
	return hash('sha256', json_encode($values));
}

/**
 * Approve only the stored content the caller reviewed; unapproval needs no fingerprint.
 * The caller holds kkJournalLock(), as the ordinary journal save does. Row locks also
 * serialize this operation with document-pool and settlement writes.
 * @param int $journal Open journal ID.
 * @param array<int, int> $ids Requested line IDs; foreign-journal IDs are ignored.
 * @param bool $approved Desired approval state.
 * @param array<int, string> $snapshots Fingerprints rendered with the requested checkboxes.
 * @param string $user Authenticated approver name.
 * @return array{ok: bool, error: string, ids: array<int, int>} Outcome and changed IDs.
 */
function kk_set_approval($journal, array $ids, $approved, array $snapshots, $user) {
	$journal = (int)$journal;
	$ids = array_values(array_filter(array_unique(array_map('intval', $ids)), function ($id) { return $id > 0; }));
	$result = array('ok' => false, 'error' => '', 'ids' => array());
	if (!$ids || $journal <= 0) {
		$result['error'] = 'missing';
		return $result;
	}
	transaktion('begin');
	try {
		$row = db_fetch_array(db_select("select bogfort from kladdeliste where id = '$journal' for update", __FILE__ . " linje " . __LINE__));
		if (!$row || $row['bogfort'] !== '-') {
			transaktion('rollback');
			$result['error'] = 'not_open';
			return $result;
		}
		$list = implode(',', $ids);
		$q = db_select("select * from kassekladde where kladde_id = '$journal' and id in ($list) order by id for update", __FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($q)) {
			$id = (int)$row['id'];
			if ($approved && (!isset($snapshots[$id]) || !is_string($snapshots[$id]) || !hash_equals(kk_approval_snapshot($row), $snapshots[$id]))) {
				transaktion('rollback');
				$result['error'] = 'changed';
				return $result;
			}
			$result['ids'][] = $id;
		}
		if ($result['ids']) {
			$list = implode(',', $result['ids']);
			$user = db_escape_string((string)$user);
			$set = $approved ? "godkendt = TRUE, godkendt_af = '$user', godkendt_tid = CURRENT_TIMESTAMP" : 'godkendt = FALSE, godkendt_af = NULL, godkendt_tid = NULL';
			$write = db_modify("update kassekladde set $set where kladde_id = '$journal' and id in ($list)", __FILE__ . " linje " . __LINE__);
			if (strncmp((string)$write, "0\t", 2) !== 0) throw new RuntimeException('Approval write failed');
			audit_log_write($approved ? 'journal.lines_approved' : 'journal.lines_unapproved', 'kladde', (string)$journal, audit_log_details_json(array('after' => array('ids' => $result['ids'], 'godkendt' => (bool)$approved))));
			if (!empty($GLOBALS['db_modify_fejl'])) throw new RuntimeException('Approval audit failed');
		}
		transaktion('commit');
		$result['ok'] = true;
		return $result;
	} catch (Throwable $error) {
		transaktion('rollback');
		$result['error'] = 'db';
		$result['ids'] = array();
		return $result;
	}
}
