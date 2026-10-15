<?php
// 20260914 CDX/LH Save document corrections atomically and reject stale manual edits.
// 20261001 CDX/LH Reject metadata updates after attachment or deletion.
// 20261001 CDX/LH Extract caller-owned metadata application and attribute web audit actors.
// 20261001 CDX/LH Refuse financial changes to generated mileage statements and release withdrawn trips.
// 20261006 CL/LH Mileage-statement alert uses tekster id 5460, inside the branch range, and is returned as UTF-8.
// 20261001 CDX/LH Refuse generated web edits before renaming without moving unrelated destination aliases.
require_once __DIR__ . '/poolAmountNormalizer.php';
require_once __DIR__ . '/poolDateNormalizer.php';
require_once __DIR__ . '/poolAudit.php';

/** @return array<string,mixed>|null Stored claim identity; older web tenants need no claims schema. */
function poolMetadataClaim($connection, int $id): ?array {
    $store = new MobileSchema($connection);
    $schema = $connection instanceof mysqli ? 'table_schema=DATABASE()' : "table_schema='public'";
    if (!$store->rows("SELECT 1 FROM information_schema.tables WHERE $schema AND table_name='mobile_expense_claims'")) {
        return null;
    }
    return $store->rows("SELECT * FROM mobile_expense_claims WHERE pool_file_id=$id")[0] ?? null;
}

/** @return bool Whether this request changes an immutable field of a generated statement. */
function poolMetadataGeneratedChange(array $row, array $input, $connection = null): bool {
    $claim = poolMetadataClaim($connection ?? $GLOBALS['connection'], (int)$row['id']);
    if (($claim['kind'] ?? null) !== 'koersel') {
        return false;
    }
    foreach (['amount', 'file_date', 'currency', 'description', 'invoice_number'] as $field) {
        if (array_key_exists($field, $input) && (!is_scalar($input[$field]) && $input[$field] !== null
            || (string)$input[$field] !== (string)($row[$field] ?? ''))) {
            return true;
        }
    }
    return false;
}

/** Caller has tombstoned and locked the pool row; withdrawal shares the deletion transaction. */
function poolMetadataWithdrawStatement($connection, array $row): void {
    $claim = poolMetadataClaim($connection, (int)$row['id']);
    if (($claim['kind'] ?? null) === 'koersel') {
        require_once __DIR__ . '/../employeeClaims/mileageStatement.php';
        mileage_statement_withdraw($connection, (int)$claim['statement_id']);
    }
}

/** @return string Translated refusal as UTF-8; findtekst() returns ISO-8859-1 text on LATIN9 databases and the pool page is UTF-8. */
function poolMetadataGeneratedMessage(int $language): string {
    $message = findtekst('5460|Kørselsbilag er lavet af Saldi og kan ikke rettes.', $language);
    return mb_check_encoding($message, 'UTF-8') ? $message : mb_convert_encoding($message, 'UTF-8', 'ISO-8859-1');
}

/**
 * Check both legacy web writers before any file rename or metadata write.
 * @return bool Whether this edit was refused.
 */
function poolMetadataRefuseWebGeneratedEdit(array $row, array $input): bool {
    return poolMetadataGeneratedChange($row, $input);
}

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
	$connection = $GLOBALS['connection'];
	if (($connection instanceof mysqli && ($connection->server_status & 1))
		|| (!$connection instanceof mysqli && pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE)) {
		throw new LogicException('Metadata save owns its transaction; use poolMetadataApply inside a transaction');
	}
	return pool_audit_actor_scope(pool_audit_web_actor(), null, function () use ($filename, $input, $manual, $version, $year): array {
		$filenameSql = db_escape_string($filename);
		transaktion('begin');
		try {
			$row = db_fetch_array(db_select("SELECT * FROM pool_files WHERE filename = '$filenameSql' FOR UPDATE", __FILE__ . ' line ' . __LINE__));
			if (!$row) {
				throw new RuntimeException('Document changed', 409);
			}
			$result = poolMetadataApply($row, $input, $manual, $version, $year);
			transaktion('commit');
			return ['success' => true, 'skipped' => $result['skipped'], 'version' => $result['version']];
		} catch (Throwable $error) {
			transaktion('rollback');
			throw $error;
		}
	});
}

/**
 * Apply the existing metadata rules to the caller's locked row without committing.
 * The caller must lock the pool identity before passing the database row.
 * @return array{skipped:bool,version:string,row:array<string,mixed>}
 */
function poolMetadataApply(array $row, array $input, bool $manual, ?string $version, int $year): array {
	$connection = $GLOBALS['connection'];
	$inside = $connection instanceof mysqli ? (bool)($connection->server_status & 1)
		: pg_transaction_status($connection) === PGSQL_TRANSACTION_INTRANS;
	if (!$inside) {
		throw new LogicException('Metadata application requires a caller-owned transaction and locked row');
	}
	if (isset($row['status']) && $row['status'] !== 'ubehandlet') {
		throw new RuntimeException('Document changed', 409);
	}
	$currentVersion = poolMetadataVersion($row);
	if ($manual && ($version === null || !hash_equals($currentVersion, $version))) {
		throw new RuntimeException('Document changed', 409);
	}
	if (!$manual && poolMetadataIsManual($row)) {
		return ['skipped' => true, 'version' => $currentVersion, 'row' => $row];
	}
	if (poolMetadataGeneratedChange($row, $input)) {
		throw new RuntimeException('generated_document', 409);
	}
	$original = $row;
	$generated = (poolMetadataClaim($connection, (int)$row['id'])['kind'] ?? null) === 'koersel';
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
	// Preserve generated bytes, including legacy nulls, when only display/account fields change.
	if ($generated) {
		foreach (['amount', 'file_date', 'currency', 'description', 'invoice_number'] as $field) {
			$row[$field] = $original[$field];
		}
	}
	$assignments = [];
	foreach ($fields as $field) {
		$assignments[] = $row[$field] === null ? "$field = NULL" : "$field = '" . db_escape_string($row[$field]) . "'";
	}
	$assignments[] = 'norm_amount = ' . ($amount === null ? 'NULL' : db_escape_string((string)$amount));
	$assignments[] = 'manually_edited = ' . ($manual ? 'true' : 'false');
	$assignments[] = 'updated = CURRENT_TIMESTAMP';
	(new MobileSchema($GLOBALS['connection']))->execute('UPDATE pool_files SET ' . implode(', ', $assignments) . " WHERE id = '" . (int)$row['id'] . "'");
	$saved = db_fetch_array(db_select("SELECT * FROM pool_files WHERE id = '" . (int)$row['id'] . "'", __FILE__ . ' line ' . __LINE__));
	return ['skipped' => false, 'version' => poolMetadataVersion($saved), 'row' => $saved];
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
