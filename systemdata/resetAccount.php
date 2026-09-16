<?php
// --- systemdata/resetAccount.php --- 2026-09-16 ---
// Copyright (c) 2003-2026 Danosoft ApS
// 20260916 CDX/PHR Build an explicit reset plan using only existing tenant tables.
// 20260916 CDX/PHR Clear item texts when items are not retained.
// 20260916 CDX/PHR Activate year 1 for all users and current account sessions after reset.
// 20260916 CDX/PHR Retain the latest undeleted financial year and renumber it and its setup to 1.

/**
 * Build reset statements. Unlisted tables are never cleared implicitly.
 * @return string[]
 */
function accountResetStatements(array $existingTables, $keepAccounts, $keepItems, $dbType, $retainedYear = 1) {
	$retainedYear = (int)$retainedYear;
	if ($retainedYear < 1) {
		throw new InvalidArgumentException('Intet gyldigt regnskabsår at bevare.');
	}
	$tables = array(
		'ansatmappe', 'ansatmappebilag', 'batch_kob', 'batch_salg', 'betalinger', 'betalingsliste',
		'bilag', 'bilag_tjekskema', 'budget', 'corrections', 'crm', 'deleted_order',
		'drawer', 'gavekort', 'gavekortbrug', 'historik', 'jobkort', 'jobkort_felter',
		'kassekladde', 'kladdeliste', 'kontokort', 'kostpriser', 'loen', 'loen_enheder',
		'lagerstatus', 'mappe', 'mappebilag', 'misc_meta_data', 'modtageliste', 'modtagelser',
		'navigator', 'noter', 'openpost', 'opgaver', 'ordrelinjer', 'ordrer',
		'ordretekster', 'pbs_kunder', 'pbs_linjer', 'pbs_liste', 'pbs_ordrer', 'pos_betalinger',
		'price_correction', 'proforma', 'provision', 'queries', 'rabat', 'regulering',
		'reservation', 'returnings', 'sager', 'sagstekster', 'serienr', 'shop_adresser',
		'shop_ordrer', 'shop_varer', 'simulering', 'tabeller', 'tidsreg', 'tjekpunkter',
		'tmpkassekl', 'transaktioner', 'report', 'valuta', 'documents', 'paperflow',
		'pool_files', 'betalingslink', 'voucher', 'voucheruse', 'stripe_catalog', 'stripe_customers',
		'stripe_events', 'stripe_import_failures', 'stocklog', 'stockmovement', 'order_stock_warning_log', 'materialer',
		'emballage', 'emballage_cat', 'rental', 'rentalclosed', 'rentalitems', 'rentalmail',
		'rentalpayment', 'rentalperiod', 'rentalremote', 'rentalremoteperiods', 'rentalreserved', 'rentalsettings',
		'kds_records', 'pos_events', 'labels', 'mylabel', 'timereg_breaks', 'timereg_sessions',
		'moms_periode_luk', 'notifications', 'datatables', 'tekster',
	);
	if (!$keepAccounts) {
		$tables = array_merge($tables, array('ansatte', 'vare_lev'));
	}
	if (!$keepItems) {
		$tables = array_merge($tables, array('styklister', 'varer', 'vare_lev', 'varetekster', 'varetilbud', 'variant_typer', 'variant_varer', 'varianter'));
	}
	$tables = array_values(array_intersect(array_unique($tables), $existingTables));
	$mysql = in_array(strtolower($dbType), array('mysql', 'mysqli'), true);
	$statements = array();
	if ($tables) {
		if ($mysql) {
			foreach ($tables as $table) {
				$statements[] = "TRUNCATE TABLE `$table`";
			}
		} else {
			$statements[] = 'TRUNCATE ' . implode(',', $tables) . ' RESTART IDENTITY';
		}
	}
	if (in_array('grupper', $existingTables, true)) {
		$statements[] = "DELETE FROM grupper WHERE (art='RA' AND kodenr!='$retainedYear') OR (art!='RA' AND fiscal_year>0 AND fiscal_year!=$retainedYear) OR art IN ('USET','DLV','KLV','DRV','KRV','VV','OLV')";
		$statements[] = "UPDATE grupper SET fiscal_year=1 WHERE fiscal_year=$retainedYear";
		$statements[] = "UPDATE grupper SET kodenr='1', box5='on', box10='' WHERE art='RA' AND kodenr='$retainedYear'";
	}
	if (in_array('kontoplan', $existingTables, true)) {
		$statements[] = "DELETE FROM kontoplan WHERE regnskabsaar!='$retainedYear'";
		$statements[] = "UPDATE kontoplan SET regnskabsaar='1' WHERE regnskabsaar='$retainedYear'";
	}
	if (in_array('brugere', $existingTables, true)) {
		$statements[] = "UPDATE brugere SET regnskabsaar='1'";
	}
	if ($keepItems && in_array('varer', $existingTables, true)) {
		$statements[] = "UPDATE varer SET beholdning='0'";
	}
	if (in_array('settings', $existingTables, true)) {
		$statements[] = "DELETE FROM settings WHERE var_grp IN ('debitor','mySale') AND var_name IN ('mailSubject','mailText')";
	}
	if (!$keepAccounts && in_array('adresser', $existingTables, true)) {
		$statements[] = "DELETE FROM adresser WHERE art IS NULL OR art!='S'";
	}
	return $statements;
}

/** Execute only the reset plan for the currently connected account. */
function resetAccount($keepAccounts, $keepItems, $dbType, $database) {
	$mysql = in_array(strtolower($dbType), array('mysql', 'mysqli'), true);
	$schema = $mysql ? 'DATABASE()' : 'current_schema()';
	$q = db_select("SELECT table_name FROM information_schema.tables WHERE table_schema=$schema AND table_type='BASE TABLE'", __FILE__ . ' linje ' . __LINE__);
	$existing = array();
	while ($row = db_fetch_array($q)) {
		$existing[] = $row['table_name'];
	}
	if (!$existing) {
		throw new RuntimeException('Kunne ikke finde regnskabets tabeller; nulstilling afbrudt.');
	}
	// PostgreSQL rolls back the entire reset on failure, including identity sequences.
	transaktion('begin');
	try {
		$q = db_select("SELECT kodenr,box1,box2,box3,box4,box10 FROM grupper WHERE art='RA'", __FILE__ . ' linje ' . __LINE__);
		$years = array();
		while ($row = db_fetch_array($q)) {
			$years[] = $row;
		}
		$retainedYear = accountResetLatestYear($years);
		$statements = accountResetStatements($existing, $keepAccounts, $keepItems, $dbType, $retainedYear);
		foreach ($statements as $sql) {
			$result = db_modify($sql, __FILE__ . ' linje ' . __LINE__);
			if (substr((string)$result, 0, 1) !== '0') {
				throw new RuntimeException('Nulstilling af regnskab fejlede.');
			}
		}
		transaktion('commit');
	} catch (Throwable $error) {
		transaktion('rollback');
		throw $error;
	}
	resetAccountSessionYear($database);
}

/** Select the chronologically latest undeleted year; fail before deleting any data if none exists. */
function accountResetLatestYear(array $years) {
	$latest = null;
	$number = null;
	foreach ($years as $year) {
		$deleted = trim((string)($year['box10'] ?? ''));
		if ($deleted !== '' && $deleted !== '0') {
			continue;
		}
		$startMonth = (int)$year['box1'];
		$startYear = (int)$year['box2'];
		$endMonth = (int)$year['box3'];
		$endYear = (int)$year['box4'];
		$yearNumber = (int)$year['kodenr'];
		if ($yearNumber < 1 || !checkdate($startMonth, 1, $startYear) || !checkdate($endMonth, 1, $endYear)) {
			continue;
		}
		$key = array($endYear, $endMonth, $startYear, $startMonth, $yearNumber);
		if ($latest === null || $key > $latest) {
			$latest = $key;
			$number = $yearNumber;
		}
	}
	if ($number === null) {
		throw new RuntimeException('Nulstilling afbrudt: intet gyldigt, ikke-slettet regnskabsår findes.');
	}
	return $number;
}

/** Update active sessions in the master database, restricted to the reset account. */
function resetAccountSessionYear($database) {
	$sql = "UPDATE online SET regnskabsaar='1' WHERE db='" . db_escape_string($database) . "'";
	$result = db_modify($sql, __FILE__ . ' linje ' . __LINE__, true);
	if (substr((string)$result, 0, 1) !== '0') {
		throw new RuntimeException('Regnskabet er nulstillet, men aktive sessioners regnskabsår kunne ikke opdateres.');
	}
}
