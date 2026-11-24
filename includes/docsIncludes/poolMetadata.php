<?php
// 20260914 CDX/LH Save document corrections atomically and reject stale manual edits.
// 20261027 CL/LH Account 0 is "not set", as the journal stores an empty account. A stored account the journal can't use
//                (0, another year's, closed, or an app's suggestion that isn't in kontoplan) no longer refuses "Indsæt" and
//                Bilagsmatch with 422; the line keeps its empty Debet. A typed Debet/Kredit is still checked (#732).
// 20261124 CL/LH The pool row and card editors post the stored account back with every save, so a stored suggestion the kontoplan doesn't have (the Saldi app stores its own unchecked) refused any edit of that row with 422.
//                An account left as stored is saved as it is; only a changed account is checked (poolMetadataEditedAccount()).
//                poolMetadataErrorText() says which field a refused save was refused for.
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
	// The journal stores an empty account as 0, and apps send it the same way: not set, not an account that doesn't exist
	if ($account === '' || (ctype_digit($account) && (int)$account === 0)) {
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
 * The account an editor's save keeps: an account left as it was stored is not checked again.
 *
 * The row and card editors post the stored account back with every save. That account may be a
 * suggestion (the Saldi app's, or one saved for another year) the kontoplan doesn't have, and
 * checking it would refuse an edit of the date or the amount for an account the user never touched.
 * The journal leaves such a suggestion out when the document is attached (poolJournalSuggestedAccount()).
 *
 * @param mixed $account Account as posted.
 * @param mixed $storedAccount Account the row holds now.
 * @param int $year Active fiscal year.
 * @return string The account to save: the stored one unchanged, or a changed one that passed poolMetadataAccount().
 */
function poolMetadataEditedAccount($account, $storedAccount, int $year): string {
	if (is_scalar($account) && is_scalar($storedAccount)) {
		$account = trim((string)$account);
		$unset = $account === '' || (ctype_digit($account) && (int)$account === 0);
		if (!$unset && $account === trim((string)$storedAccount)) {
			return $account;
		}
	}
	return poolMetadataAccount($account, $year);
}

/**
 * What a refused metadata save tells the user: which field was refused, or that the document changed.
 *
 * @param Throwable $error What poolMetadataSave() or the editor's own checks threw.
 * @param mixed $languageId The user's sprog_id.
 * @return string Text for the user.
 */
function poolMetadataErrorText(Throwable $error, $languageId): string {
	if ($error->getCode() === 409) {
		return findtekst('5253|Dokumentet er ændret. Genindlæs det før du gemmer.', $languageId);
	}
	switch ($error->getMessage()) {
		case 'Invalid account':
			return findtekst('pool.save.invalid_account|Kontoen findes ikke i kontoplanen for regnskabsåret, eller den er lukket. Ingen ændringer er gemt.', $languageId);
		case 'Invalid amount':
			return findtekst('pool.save.invalid_amount|Beløbet kan ikke læses. Ingen ændringer er gemt.', $languageId);
		case 'Invalid date':
			return findtekst('pool.save.invalid_date|Datoen findes ikke. Ingen ændringer er gemt.', $languageId);
	}
	return findtekst('5254|Kontrollér konto, beløb og dato. Ingen ændringer er gemt.', $languageId);
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
		$storedAccount = $row['account'] ?? '';
		$fields = ['subject', 'account', 'amount', 'file_date', 'invoice_number', 'description', 'currency'];
		foreach ($fields as $field) {
			$value = array_key_exists($field, $input) ? $input[$field] : ($row[$field] ?? '');
			if (!is_scalar($value) && $value !== null) {
				throw new InvalidArgumentException('Invalid value', 422);
			}
			$row[$field] = trim((string)$value);
		}
		$row['account'] = poolMetadataEditedAccount($row['account'], $storedAccount, $year);
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
		if ((float)$target['debet'] != 0) {
			return '';
		}
	}
	// The stored account is only a suggestion (an app's or an AI scan's, or saved for another year).
	// One the journal can't use is left out, and the line keeps its empty Debet; the attach goes on.
	try {
		return poolMetadataAccount($account, $year);
	} catch (InvalidArgumentException $error) {
		return '';
	}
}
