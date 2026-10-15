<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/debitor/debtorAccount.php --- ver 5.0.0 --- 2026.10.03
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.
// 20261001 CDX/LH Share primary-card defaults and return the inserted customer identity.
// 20261008 CL/LH MySQL takes the named lock saldi_adresser_D (from ai/mcp-assistant's debkort_save).

require_once __DIR__ . '/../db_query.php';
require_once __DIR__ . '/../std_func.php';

/**
 * Allocate the card's first free number, retaining advisory lock 5130513 (MySQL: saldi_adresser_D) until
 * debtor_create() finishes its insert (autocommit), or the caller commits/rolls back.
 * Standalone autocommit callers must call debtor_unlock_kontonr().
 * @throws DomainException A typed number is already used.
 * @return string Customer account number.
 */
function debtor_allocate_kontonr(?string $wanted): string
{
    if ($wanted !== null && (!ctype_digit($wanted) || (int)$wanted < 1)) {
        throw new InvalidArgumentException('Invalid customer number');
    }
    if (!in_array(ifset($GLOBALS, 'db_type', 'postgresql'), ['mysql','mysqli'], true)) {
        $connection = ifset($GLOBALS, 'connection');
        $transaction = $connection instanceof \PgSql\Connection && pg_transaction_status($connection) === PGSQL_TRANSACTION_INTRANS;
        db_select('SELECT ' . ($transaction ? 'pg_advisory_xact_lock' : 'pg_advisory_lock') . '(5130513)', __FILE__ . ' linje ' . __LINE__);
    } else {
        $locked = db_fetch_array(db_select("SELECT GET_LOCK('saldi_adresser_D', 10) AS acquired", __FILE__ . ' linje ' . __LINE__));
        if ((int)ifset($locked, 'acquired', 0) !== 1) {
            throw new RuntimeException('Customer allocation lock unavailable');
        }
    }
    $numbers = [];
    $query = db_select("SELECT kontonr FROM adresser WHERE art='D'", __FILE__ . ' linje ' . __LINE__);
    while ($row = db_fetch_array($query)) {
        $numbers[] = $row['kontonr'];
    }
    if ($wanted !== null && in_array($wanted, $numbers)) {
        debtor_unlock_kontonr();
        throw new DomainException('Customer number already used');
    }
    $number = $wanted === null ? 1000 : (int)$wanted;
    while (in_array($number, $numbers)) {
        $number++;
    }
    return (string)$number;
}

/**
 * Release only the allocation lock, never the caller's transaction.
 * @return void
 */
function debtor_unlock_kontonr(): void
{
    if (!in_array(ifset($GLOBALS, 'db_type', 'postgresql'), ['mysql','mysqli'], true)) {
        $connection = ifset($GLOBALS, 'connection');
        if (!$connection instanceof \PgSql\Connection || pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE) {
            db_select('SELECT pg_advisory_unlock(5130513)', __FILE__ . ' linje ' . __LINE__);
        }
    } else {
        db_select("SELECT RELEASE_LOCK('saldi_adresser_D')", __FILE__ . ' linje ' . __LINE__);
    }
}

/**
 * Insert a server-built, escaped card INSERT while its allocation lock is held.
 * @param string $sql SQL constructed by the card or debtor_create(), never request SQL.
 * @return int Identity from the inserted row, independent of duplicate historical numbers.
 */
function debtor_insert_row(string $sql): int
{
    if (in_array(ifset($GLOBALS, 'db_type', 'postgresql'), ['mysql','mysqli'], true)) {
        db_modify($sql, __FILE__ . ' linje ' . __LINE__);
        $row = db_fetch_array(db_select('SELECT LAST_INSERT_ID() AS id', __FILE__ . ' linje ' . __LINE__));
    } else {
        $row = db_fetch_array(db_select($sql . ' RETURNING id', __FILE__ . ' linje ' . __LINE__));
    }
    $id = (int)ifset($row, 'id', 0);
    if ($id < 1 || ifset($GLOBALS, 'db_modify_fejl', false)) {
        throw new RuntimeException('Customer insert failed');
    }
    return $id;
}

/**
 * Payment defaults from the primary card's new-customer form.
 * Retain its selection order and day-count rule for existing tenants.
 * @return array{betalingsbet:string,betalingsdage:int} Raw form defaults.
 */
function debtor_payment_defaults(): array
{
	$x = 0;
	$bb = array();
	$q = db_select("select distinct(betalingsbet) as betalingsbet from adresser", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$bb[$x] = $r['betalingsbet'];
		$x++;
	}
	$maxbb = 'Netto';
	for ($x = 0; $x < count($bb); $x++) {
		$term = db_escape_string((string)$bb[$x]);
		$qtxt = "select count(betalingsbet) as betalingsbet from adresser where art='D' and betalingsbet = '$term'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$betbet[$x] = $r['betalingsbet'];
		if ($x == 0) {
			$maxbb = $bb[$x];
		} elseif ($betbet[$x] > $betbet[$x - 1]) {
			$maxbb = $bb[$x];
		}
	}
	$bb = NULL;
	$x = 0;
	$bd = array();
	$q = db_select("select distinct(betalingsdage) as betalingsdage from adresser WHERE betalingsdage != 0", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$bd[$x] = $r['betalingsdage'];
		$x++;
	}
	if ($maxbb != 'Kontant' && $maxbb != 'Forud') {
		$maxbd = 8;
		for ($x = 0; $x < count($bd); $x++) {
			$days = (int)$bd[$x];
			$r = db_fetch_array(db_select("select count(betalingsdage) as betalingsdage from adresser where art='D' and betalingsdage = '$days'", __FILE__ . " linje " . __LINE__));
			$betdag[$x] = $r['betalingsdage'];
			if ($x && $betdag[$x] > $betdag[$x - 1]) {
				$maxbd = $bd[$x];
			}
		}
	} else {
		$maxbd = 0;
	}
    return ['betalingsbet'=>(string)$maxbb,'betalingsdage'=>(int)$maxbd];
}

/**
 * Insert the full card row from unescaped legacy field names and defaults.
 * Number allocation and insertion share the web lock; transaction ownership stays with the caller.
 * @param array<string,mixed> $fields Raw card values, optional kontonr and auto_kontonr.
 * @return int New adresser id.
 */
function debtor_create(array $fields): int
{
    $wanted = trim((string)ifset($fields, 'kontonr', ''));
    if ($wanted !== '' && (!ctype_digit($wanted) || (int)$wanted < 1)) {
        throw new InvalidArgumentException('Invalid customer number');
    }
    $auto = (int)ifset($fields, 'auto_kontonr', 0);
    // A number preassigned by the card starts there and moves forward on a collision.
    $number = debtor_allocate_kontonr($auto > 0 && (int)$wanted === $auto ? null : ($wanted ?: null));
    try {
        if ($auto > 0 && (int)$wanted === $auto) {
            $number = (string)$auto;
            while (db_fetch_array(db_select("SELECT id FROM adresser WHERE art='D' AND kontonr='" . db_escape_string($number) . "'", __FILE__ . ' linje ' . __LINE__))) {
                $number = (string)((int)$number + 1);
            }
        }
        $columns = ['firmanavn', 'addr1', 'addr2', 'postnr', 'bynavn', 'land', 'kontakt', 'tlf', 'mobile', 'email', 'mailfakt', 'web', 'betalingsdage', 'kreditmax', 'betalingsbet', 'cvrnr', 'ean', 'institution', 'notes', 'gruppe', 'kontoansvarlig', 'bank_reg', 'bank_konto', 'pbs_nr', 'pbs', 'kontotype', 'fornavn', 'efternavn', 'lev_firmanavn', 'lev_fornavn', 'lev_efternavn', 'lev_addr1', 'lev_addr2', 'lev_postnr', 'lev_bynavn', 'lev_land', 'lev_kontakt', 'lev_tlf', 'lev_email', 'felt_1', 'felt_2', 'felt_3', 'felt_4', 'felt_5', 'vis_lev_addr', 'lukket', 'stripe_fravalg', 'kategori', 'rabatgruppe', 'status'];
        $integers = ['betalingsdage','gruppe','kontoansvarlig','rabatgruppe','status'];
        $values = [];
        foreach ($columns as $column) {
            $value = ifset($fields, $column, in_array($column, $integers, true) || $column === 'kreditmax' ? 0 : '');
            if (in_array($column, $integers, true)) {
                $value = (int)$value;
            } elseif ($column === 'kreditmax') {
                $value = (float)$value;
            }
            $values[] = "'" . db_escape_string((string)$value) . "'";
        }
        $columns = array_merge(['kontonr','art','oprettet'], $columns);
        $values = array_merge(["'" . db_escape_string($number) . "'", "'D'", "'" . date('Y-m-d') . "'"], $values);
        $id = debtor_insert_row('INSERT INTO adresser (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')');
        $contact = trim((string)ifset($fields, 'kontakt', ''));
        if ($contact !== '') {
            db_modify("INSERT INTO ansatte(konto_id,navn) VALUES ($id,'" . db_escape_string($contact) . "')", __FILE__ . ' linje ' . __LINE__);
        }
        return $id;
    } finally {
        debtor_unlock_kontonr();
    }
}
