<?php
// 20261110 CDX/NTR SST-980 Validate wage-slip identifiers and authorize stored ownership and state.

/**
 * Read an identifier without accepting SQL, signs, fractions, arrays or an overflowing integer.
 *
 * @param mixed $value A request identifier; an empty value represents a new row.
 * @return int A nonnegative database identifier.
 * @throws InvalidArgumentException When the identifier is malformed.
 */
function loenRequestId($value): int {
	if ($value === null || $value === '') {
		return 0;
	}
	if ((!is_int($value) && !is_string($value)) || !preg_match('/\A[0-9]+\z/', (string)$value)) {
		throw new InvalidArgumentException('Invalid wage-slip identifier');
	}
	$digits = ltrim((string)$value, '0');
	if (strlen($digits) > 10 || (strlen($digits) === 10 && strcmp($digits, '2147483647') > 0)) {
		throw new InvalidArgumentException('Invalid wage-slip identifier');
	}
	return (int)$digits;
}

/**
 * Resolve the submitted action and validate every row identifier before any database operation.
 *
 * @param array<string, mixed> $get Query parameters.
 * @param array<string, mixed> $post Form fields; identifiers are normalized in place.
 * @return array{id: int, action: string} The authoritative form target and action.
 * @throws InvalidArgumentException When fields or action combinations are invalid.
 */
function loenRequest(array $get, array &$post): array {
	$getId = loenRequestId($get['id'] ?? null);
	$actions = array();
	foreach (array('gem', 'afslut', 'afvis', 'godkend', 'slet') as $action) {
		if (!empty($post[$action])) {
			if (!is_string($post[$action])) {
				throw new InvalidArgumentException('Invalid wage-slip action');
			}
			$actions[] = $action;
		}
	}
	if (count($actions) > 1) {
		throw new InvalidArgumentException('Ambiguous wage-slip action');
	}
	$action = $actions[0] ?? 'view';
	foreach (array('id', 'sag_id', 'sag_nr', 'opg_id', 'gl_opg_id', 'opg_nr', 'loen_nr', 'listevalg', 'listevalg_ny', 'tr_id', 'telt_id') as $key) {
		if (array_key_exists($key, $post)) {
			$post[$key] = loenRequestId($post[$key]);
		}
	}
	foreach (array('enhed_id', 'a_id', 'ansat_id', 'vare_id') as $key) {
		if (!array_key_exists($key, $post)) {
			continue;
		}
		if (!is_array($post[$key])) {
			throw new InvalidArgumentException('Invalid wage-slip rows');
		}
		foreach ($post[$key] as &$value) {
			$value = loenRequestId($value);
		}
		unset($value);
	}
	$id = $action === 'view' ? $getId : ($post['id'] ?? 0);
	if (!$id && in_array($action, array('afvis', 'godkend', 'slet'), true)) {
		throw new InvalidArgumentException('Missing wage-slip identifier');
	}
	return array('id' => $id, 'action' => $action);
}

/**
 * Authorize an action from the stored slip, never from posted ownership or status fields.
 *
 * @param array<string, mixed>|false $slip Stored loen row; false for a new or missing slip.
 * @param bool $owner Whether the authenticated user created this slip.
 * @param bool $approver Whether the authenticated user has wage approval permission (bit 6).
 * @param string $action The validated action from loenRequest().
 * @return bool Whether this action is permitted.
 */
function loenMayChange($slip, bool $owner, bool $approver, string $action): bool {
	if (!$slip || trim((string)($slip['afregnet'] ?? '')) || trim((string)($slip['afvist'] ?? ''))) {
		return false;
	}
	$transferred = trim((string)($slip['afsluttet'] ?? '')) !== '';
	$approved = trim((string)($slip['godkendt'] ?? '')) !== '';
	if ($action === 'godkend') {
		return $approver && $transferred && !$approved;
	}
	if ($action === 'afvis') {
		return $approver && ($transferred || $approved);
	}
	return in_array($action, array('gem', 'afslut', 'slet'), true) && ($owner || $approver) && !$transferred && !$approved;
}

/**
 * Lock a slip after its case, preserving the case -> settlement -> Dyrtid lock order of SST-961.
 *
 * @param int $id Validated wage-slip identifier.
 * @param int $targetCaseNumber Validated destination case number during a save or transfer.
 * @return array<string, mixed>|false The locked stored row, or false if it does not exist.
 */
function loenLockSlip(int $id, int $targetCaseNumber = 0) {
	$cases = array();
	if ($id) {
		$row = db_fetch_array(db_select("select sag_id from loen where id='$id'", __FILE__ . ' linje ' . __LINE__));
		if ($row && (int)$row['sag_id']) {
			$cases[] = (int)$row['sag_id'];
		}
	}
	if ($targetCaseNumber) {
		$row = db_fetch_array(db_select("select id from sager where sagsnr='$targetCaseNumber'", __FILE__ . ' linje ' . __LINE__));
		if ($row) {
			$cases[] = (int)$row['id'];
		}
	}
	sort($cases, SORT_NUMERIC);
	foreach (array_unique($cases) as $caseId) {
		db_select("select id from sager where id='$caseId' for update", __FILE__ . ' linje ' . __LINE__);
	}
	if (!$id) {
		return false;
	}
	$slip = db_fetch_array(db_select("select * from loen where id='$id' for update", __FILE__ . ' linje ' . __LINE__));
	if ($slip && (int)$slip['sag_id'] && !in_array((int)$slip['sag_id'], $cases, true)) {
		return false; // A concurrent case move requires a fresh request with the correct lock order.
	}
	return $slip;
}

/**
 * Reject a locked slip and reopen a copy of its stored content and piecework lines.
 * The reviewer's form is read-only: only the rejection reason is accepted from it.
 *
 * @param array<string, mixed> $slip The stored row already authorized and locked by ret_loen().
 * @param string $reason The submitted explanation.
 * @param string $reviewer The SQL-escaped authenticated username supplied by online.php.
 * @return void
 */
function loenRejectSlip(array $slip, string $reason, string $reviewer): void {
	$id = (int)$slip['id'];
	$number = (int)$slip['nummer'];
	$reason = db_escape_string($reason);
	$stamp = time();
	db_modify("update loen set afvist='$stamp',afvist_af='$reviewer',afvist_pga='$reason',godkendt='' where id='$id'", __FILE__ . ' linje ' . __LINE__);
	$columns = 'art,kategori,nummer,hvem,sag_nr,sag_id,oprettet,oprettet_af,ansatte,datoer,fordeling,loen,timer,t50pct,t100pct,mentor,mentor_rate,skur,sum,loendate,tekst,korsel,opg_id,opg_nr,sag_ref,feriefra,ferietil,hourtype';
	$reset = 'afsluttet,afsluttet_af,godkendt,godkendt_af,afregnet,afregnet_af,afvist,afvist_af,afvist_pga,master_id';
	db_modify("insert into loen ($columns,$reset) select $columns,'','','','','','','','','$reason',0 from loen where id='$id'", __FILE__ . ' linje ' . __LINE__);
	$row = db_fetch_array(db_select("select max(id) as id from loen where nummer='$number' and id!='$id' and coalesce(afvist,'')=''", __FILE__ . ' linje ' . __LINE__));
	$newId = (int)$row['id'];
	$lines = 'vare_id,op,ned,op_25,ned_25,op_30,ned_30,op_40,ned_40,op_60,ned_60,op_70,ned_70,op_100,ned_100,op_160,ned_160,op_30m,ned_30m,pris_op,pris_ned,tekst,procent,varenr';
	db_modify("insert into loen_enheder (loen_id,$lines) select '$newId',$lines from loen_enheder where loen_id='$id'", __FILE__ . ' linje ' . __LINE__);
	db_modify("update loen set afsluttet='',afsluttet_af='',master_id='0' where master_id='$id' and coalesce(afregnet,'')=''", __FILE__ . ' linje ' . __LINE__);
}
