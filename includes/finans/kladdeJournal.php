<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/finans/kladdeJournal.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Require server-owned approval journal identity before reusing a settings ID.
// 20261007 CDX/LH Restore the shared tenant SQL modification trail after writes.

require_once __DIR__ . '/../db_query.php';
require_once __DIR__ . '/../std_func.php';

/** @return \PgSql\Connection Selected tenant link supplied by the legacy engine context. */
function kladde_journal_connection(): \PgSql\Connection
{
    $connection = ifset($GLOBALS, 'connection');
    if (!$connection instanceof \PgSql\Connection) {
        throw new RuntimeException('Mobile journal creation requires PostgreSQL');
    }
    return $connection;
}

/**
 * Execute checked, parameterized SQL and retain SQLSTATE for the owning retry loop.
 *
 * @return list<array<string,mixed>> Selected rows, empty for commands.
 */
function kladde_journal_query(string $sql, array $params = []): array
{
    $connection = kladde_journal_connection();
    if (!pg_send_query_params($connection, $sql, $params)) {
        db_modify_query_trail($connection, $sql, $params, __FILE__, false, pg_last_error($connection));
        throw new RuntimeException('Journal database command failed');
    }
    $result = pg_get_result($connection);
    // libpq reports ACTIVE until the async command's terminating null result is consumed.
    while (pg_get_result($connection) !== false) {
    }
    $ok = $result !== false && in_array(pg_result_status($result), [PGSQL_COMMAND_OK, PGSQL_TUPLES_OK], true);
    db_modify_query_trail($connection, $sql, $params, __FILE__, $ok,
        $ok ? '' : ($result ? pg_result_error($result) : pg_last_error($connection)));
    if (!$result || !in_array(pg_result_status($result), [PGSQL_COMMAND_OK, PGSQL_TUPLES_OK], true)) {
        $state = $result ? pg_result_error_field($result, PGSQL_DIAG_SQLSTATE) : null;
        throw new RuntimeException('Journal database command failed', $state === '23505' ? 23505 : 0);
    }
    return pg_num_fields($result) > 0 ? pg_fetch_all($result) : [];
}

/**
 * Active web lease, including a lease owned by the approving user's own browser.
 * Accepts the web's microtime "fraction epoch" and its older epoch-only format.
 *
 * @return array{holder:string,since:string}|null Holder and ISO 8601 UTC acquisition time.
 */
function kladde_lease_holder(int $kladdeId): ?array
{
    $rows = kladde_journal_query("SELECT hvem, CASE
        WHEN trim(regexp_replace(COALESCE(tidspkt,''),'^.* ','')) ~ '^[0-9]+(\\.[0-9]+)?$'
        THEN trim(regexp_replace(tidspkt,'^.* ',''))::numeric ELSE NULL END AS epoch
        FROM kladdeliste WHERE id=$1 AND bogfort IN ('-','S') AND COALESCE(hvem,'') <> ''", [$kladdeId]);
    if (!$rows || $rows[0]['epoch'] === null) {
        return null;
    }
    $epoch = (float)$rows[0]['epoch'];
    $active = kladde_journal_query('SELECT $1::numeric > extract(epoch FROM now())-3600 AS active', [$epoch]);
    if ($active[0]['active'] !== 't') {
        return null;
    }
    return ['holder' => $rows[0]['hvem'], 'since' => (new DateTimeImmutable('@' . (int)$epoch))->format(DateTimeInterface::ATOM)];
}

/** @return int New open journal id; caller must own a usable transaction and commit it. */
function kladde_journal_insert(string $note, string $brugernavn): int
{
    if (pg_transaction_status(kladde_journal_connection()) !== PGSQL_TRANSACTION_INTRANS) {
        throw new LogicException('Journal insertion requires a caller transaction');
    }
    kladde_journal_query("SELECT pg_advisory_xact_lock(hashtext('saldi:kladdeliste'))");
    $rows = kladde_journal_query("INSERT INTO kladdeliste(id,kladdedate,kladdenote,bogfort,oprettet_af)
        SELECT COALESCE(MAX(id),0)+1,CURRENT_DATE,$1,'-',$2 FROM kladdeliste RETURNING id", [$note, $brugernavn]);
    return (int)$rows[0]['id'];
}

/**
 * Own one short transaction; retry a legacy MAX(id)+1 collision up to three times.
 *
 * @return int Committed journal id.
 */
function kladde_journal_transaction(callable $create): int
{
    if (pg_transaction_status(kladde_journal_connection()) !== PGSQL_TRANSACTION_IDLE) {
        throw new LogicException('Journal creation requires an idle connection');
    }
    for ($attempt = 0; $attempt <= 3; $attempt++) {
        kladde_journal_query('BEGIN');
        try {
            $id = $create();
            kladde_journal_query('COMMIT');
            return $id;
        } catch (Throwable $error) {
            kladde_journal_query('ROLLBACK');
            if ($error->getCode() !== 23505 || $attempt === 3) {
                throw $error;
            }
        }
    }
    throw new LogicException('Journal retry exhausted');
}

/** @return int Committed journal id, resolved before opening a business transaction. */
function kladde_journal_create(string $note, string $brugernavn): int
{
    return kladde_journal_transaction(fn() => kladde_journal_insert($note, $brugernavn));
}

/** @return int Marked rolling mobile journal id, open and without any active browser lease. */
function mobile_approval_journal(string $brugernavn): int
{
    return kladde_journal_transaction(function () use ($brugernavn): int {
        kladde_journal_query("SELECT pg_advisory_xact_lock(hashtext('saldi:mobile_approval_journal'))");
        $setting = kladde_journal_query("SELECT var_value FROM settings
            WHERE var_grp='mobile' AND var_name='approval_kladde_id' AND user_id=0 ORDER BY id DESC LIMIT 1");
        $id = (int)ifset($setting, [0, 'var_value'], 0);
        $journal = kladde_journal_query("SELECT id FROM kladdeliste
            WHERE id=$1 AND bogfort='-' AND mobile_approval_journal=true FOR UPDATE", [$id]);
        if ($journal && kladde_lease_holder($id) === null) {
            return $id;
        }
        $note = findtekst('5440|Godkendte bilag fra Saldi Mobile', (int)ifset($GLOBALS, 'sprog_id', 0));
        if ($note === 'Tekst nr: 5440') {
            $note = 'Godkendte bilag fra Saldi Mobile';
        }
        $id = kladde_journal_insert($note, $brugernavn);
        kladde_journal_query('UPDATE kladdeliste SET mobile_approval_journal=true WHERE id=$1', [$id]);
        kladde_journal_query("DELETE FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id' AND user_id=0");
        kladde_journal_query("INSERT INTO settings(var_grp,var_name,var_value,user_id)
            VALUES ('mobile','approval_kladde_id',$1,0)", [(string)$id]);
        return $id;
    });
}
