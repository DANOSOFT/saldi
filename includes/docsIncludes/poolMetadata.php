<?php
// 20260914 CDX/LH Save document corrections atomically and reject stale manual edits.
require_once __DIR__ . '/poolAmountNormalizer.php';
require_once __DIR__ . '/poolDateNormalizer.php';

/** @return bool Whether the metadata was explicitly accepted or corrected by a user. */
function poolMetadataIsManual(array $row): bool {
	return in_array($row['manually_edited'] ?? null, ['t', true, 1, '1'], true);
}

/** @return string Version of the displayed metadata, independent of database fetch mode. */
function poolMetadataVersion(array $row): string {
	$values = [];
	foreach (['id', 'filename', 'subject', 'account', 'amount', 'file_date', 'invoice_number', 'description', 'currency', 'updated'] as $field) {
		$values[$field] = (string)($row[$field] ?? '');
	}
	$values['manually_edited'] = poolMetadataIsManual($row);
	return hash('sha256', serialize($values));
}

/** @return string Valid account number for the active fiscal year, or an empty selection. */
function poolMetadataAccount($account, int $year): string {
	if (!is_scalar($account) && $account !== null) {
		throw new InvalidArgumentException('Invalid account', 422);
	}
	$account = trim((string)$account);
	if ($account === '') {
		return '';
	}
	if (!ctype_digit($account) || !db_fetch_array(db_select(
		"SELECT kontonr FROM kontoplan WHERE kontonr = '" . db_escape_string($account) . "' AND regnskabsaar = '$year' AND kontotype IN ('D', 'S') AND (lukket IS NULL OR lukket != 'on') LIMIT 1",
		__FILE__ . ' line ' . __LINE__
	))) {
		throw new InvalidArgumentException('Invalid account', 422);
	}
	return $account;
}

/**
 * Persist metadata under a row lock. Manual requests must name the version shown to the user.
 *
 * @return array{success: bool, skipped: bool, version: string}
 */
function poolMetadataSave(string $filename, array $input, bool $manual, ?string $version, int $year): array {
	if ($filename === '' || basename($filename) !== $filename || str_contains($filename, '\\')) {
		throw new InvalidArgumentException('Invalid filename', 422);
	}
	$filenameSql = db_escape_string($filename);
	transaktion('begin');
	try {
		$row = db_fetch_array(db_select("SELECT * FROM pool_files WHERE filename = '$filenameSql' FOR UPDATE", __FILE__ . ' line ' . __LINE__));
		if (!$row) {
			// Files in the editor are synchronized into pool_files before display. A missing
			// row means it was moved/deleted; never recreate it from a stale browser tab.
			throw new RuntimeException('Document changed', 409);
		}
		$currentVersion = poolMetadataVersion($row);
		if ($manual && ($version === null || !hash_equals($currentVersion, $version))) {
			throw new RuntimeException('Document changed', 409);
		}
		if (!$manual && poolMetadataIsManual($row)) {
			transaktion('commit');
			return ['success' => true, 'skipped' => true, 'version' => $currentVersion];
		}
		$fields = ['subject', 'account', 'amount', 'file_date', 'invoice_number', 'description', 'currency'];
		foreach ($fields as $field) {
			$value = array_key_exists($field, $input) ? $input[$field] : ($row[$field] ?? '');
			if (!is_scalar($value) && $value !== null) {
				throw new InvalidArgumentException('Invalid value', 422);
			}
			$row[$field] = trim((string)$value);
		}
		$row['account'] = poolMetadataAccount($row['account'], $year);
		$amount = normalizePoolAmount($row['amount']);
		if ($row['amount'] !== '' && $amount === null) {
			throw new InvalidArgumentException('Invalid amount', 422);
		}
		$date = normalizeDateFormat($row['file_date']);
		if ($row['file_date'] !== '' && (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $parts)
			|| !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1]))) {
			throw new InvalidArgumentException('Invalid date', 422);
		}
		$row['file_date'] = $date;
		$row['currency'] = normalizePoolCurrency($row['currency']) ?? '';
		$assignments = [];
		foreach ($fields as $field) {
			$assignments[] = "$field = '" . db_escape_string($row[$field]) . "'";
		}
		$assignments[] = 'norm_amount = ' . ($amount === null ? 'NULL' : db_escape_string((string)$amount));
		$assignments[] = 'manually_edited = ' . ($manual ? 'true' : 'false');
		$assignments[] = 'updated = CURRENT_TIMESTAMP';
		db_modify('UPDATE pool_files SET ' . implode(', ', $assignments) . " WHERE id = '" . (int)$row['id'] . "'", __FILE__ . ' line ' . __LINE__);
		$saved = db_fetch_array(db_select("SELECT * FROM pool_files WHERE id = '" . (int)$row['id'] . "'", __FILE__ . ' line ' . __LINE__));
		transaktion('commit');
		return ['success' => true, 'skipped' => false, 'version' => poolMetadataVersion($saved)];
	} catch (Throwable $error) {
		transaktion('rollback');
		throw $error;
	}
}

/** @return string Suggested debit account, preserving explicit and existing classifications. */
function poolJournalSuggestedAccount($account, string $source, int $sourceId, int $journalId, $explicitDebit, int $year): string {
	if ($source !== 'kassekladde' || !empty($explicitDebit)) {
		return '';
	}
	if ($sourceId > 0) {
		$target = db_fetch_array(db_select("SELECT debet FROM kassekladde WHERE id = '$sourceId' AND kladde_id = '$journalId'", __FILE__ . ' line ' . __LINE__));
		if (!$target) {
			throw new InvalidArgumentException('Invalid journal line', 422);
		}
		if (!empty($target['debet'])) {
			return '';
		}
	}
	return poolMetadataAccount($account, $year);
}
