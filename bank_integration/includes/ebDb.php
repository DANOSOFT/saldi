<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/ebDb.php --- ver 5.1.1 --- 2026-11-16 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
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
//
// 20261006 CDX/LH Created Enable Banking ebDb backend service.
// 20261007 CDX/LH Used native tenant writes and SQLSTATE errors without legacy SQL logging.
// 20261116 CL/LH ebLang() and ebT() for the findtekst() texts of the bank feed.

/**
 * Database context is established by includes/connect.php and includes/online.php, or worker.php.
 * @var string $db_type
 * @var mixed $connection
 */

final class EbUserError extends RuntimeException {}

final class EbLeaseLostException extends RuntimeException {}

final class EbDbException extends RuntimeException
{
    /** @return void */
    public function __construct(public readonly bool $uniqueViolation = false)
    {
        parent::__construct('Bankforbindelse: databaseoperationen mislykkedes.');
    }
}

/** @return int Language id for findtekst(): the session's $sprog_id, Danish when unset. */
function ebLang(): int
{
    return max(1, (int)($GLOBALS['sprog_id'] ?? 1));
}

/**
 * The bank pages and their messages are UTF-8, while findtekst() answers in the tenant's encoding.
 * @param string $text findtekst() output.
 * @return string The text as UTF-8.
 */
function ebT(string $text): string
{
    return ($GLOBALS['db_encode'] ?? 'UTF8') !== 'UTF8' ? mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1') : $text;
}

/** @return bool Whether the active driver is mysqli. */
function ebMysql(): bool
{
    return in_array($GLOBALS['db_type'] ?? '', ['mysql', 'mysqli'], true);
}

/** @return string Server-local timestamp for tenant storage. */
function ebNow(): string
{
    return date('Y-m-d H:i:s');
}

/** @return string Server-local calendar date. */
function ebToday(): string
{
    return date('Y-m-d');
}

/** @return string|null Valid calendar date, without silently correcting invalid dates. */
function ebDate(?string $value): ?string
{
    if ($value === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
        return null;
    }
    $parts = array_map('intval', explode('-', $value));
    return checkdate($parts[1], $parts[2], $parts[0]) ? $value : null;
}

/** @return string Quoted and escaped SQL scalar; null becomes SQL NULL. */
function ebSql(string|int|bool|null $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_bool($value)) {
        return (string)(int)$value;
    }
    return "'" . db_escape_string($value) . "'";
}

/** @return void Write directly on the tenant connection, without logging SQL or inspecting bank text. */
function ebWrite(string $sql): void
{
    global $connection;
    try {
        if (ebMysql()) {
            if (mysqli_query($connection, $sql) === false) {
                throw new EbDbException(mysqli_errno($connection) === 1062);
            }
            return;
        }
        if (!pg_send_query($connection, $sql)) {
            throw new EbDbException();
        }
        $failed = false;
        $unique = false;
        while ($result = pg_get_result($connection)) {
            $state = pg_result_error_field($result, PGSQL_DIAG_SQLSTATE);
            $failed = $failed || in_array(pg_result_status($result), [PGSQL_BAD_RESPONSE, PGSQL_NONFATAL_ERROR, PGSQL_FATAL_ERROR], true);
            $unique = $unique || $state === '23505';
            pg_free_result($result);
        }
        if ($failed) {
            throw new EbDbException($unique);
        }
    } catch (mysqli_sql_exception $e) {
        throw new EbDbException($e->getCode() === 1062);
    }
}

/**
 * Use native reads because legacy db_select may alert/exit even in webservice mode.
 * @return list<array<string, string|null>> Associative result rows from the current tenant.
 */
function ebRows(string $sql): array
{
    global $connection;
    try {
        $result = ebMysql() ? mysqli_query($connection, $sql) : pg_query($connection, $sql);
        if ($result === false) {
            throw new EbDbException();
        }
        $rows = [];
        while ($row = ebMysql() ? mysqli_fetch_assoc($result) : pg_fetch_assoc($result)) {
            $rows[] = $row;
        }
        return $rows;
    } catch (mysqli_sql_exception $e) {
        throw new EbDbException($e->getCode() === 1062);
    }
}

/** @return array<string, string|null>|null First row, or null when absent. */
function ebRow(string $sql): ?array
{
    return ebRows($sql)[0] ?? null;
}

/** @return int Generated id from the immediately preceding insert on the same connection. */
function ebInsertId(string $table): int
{
    global $connection;
    if (!preg_match('/^[a-z_]+$/D', $table)) {
        throw new InvalidArgumentException('Invalid table name');
    }
    return ebMysql() ? (int)mysqli_insert_id($connection)
        : (int)ebRow('SELECT currval(pg_get_serial_sequence(' . ebSql($table) . ", 'id')) AS id")['id'];
}

/**
 * Own a short transaction; callers must never make HTTP calls within the callback.
 * @template T
 * @param callable(): T $work
 * @return T
 */
function ebAtomic(callable $work): mixed
{
    global $db_transaktion_depth;
    if (($db_transaktion_depth ?? 0) > 0) {
        throw new LogicException('Nested bank transactions are not supported');
    }
    ebWrite('BEGIN');
    $db_transaktion_depth = 1;
    try {
        $value = $work();
        ebWrite('COMMIT');
        return $value;
    } catch (Throwable $e) {
        ebWrite('ROLLBACK');
        throw $e;
    } finally {
        $db_transaktion_depth = 0;
    }
}

/**
 * @param array<string, string|int|bool|null> $values
 * @return int Insert a trusted table/column map.
 */
function ebInsert(string $table, array $values): int
{
    ebWrite('INSERT INTO ' . ebIdentifier($table) . ' (' . implode(', ', array_map('ebIdentifier', array_keys($values))) . ') VALUES ('
        . implode(', ', array_map('ebSql', array_values($values))) . ')');
    return isset($values['id']) ? (int)$values['id'] : ebInsertId($table);
}

/**
 * @param array<string, string|int|bool|null> $values
 * @return void Update trusted columns by local id.
 */
function ebUpdate(string $table, int $id, array $values): void
{
    $sets = [];
    foreach ($values as $column => $value) {
        $sets[] = ebIdentifier($column) . '=' . ebSql($value);
    }
    ebWrite('UPDATE ' . ebIdentifier($table) . ' SET ' . implode(', ', $sets) . ' WHERE id=' . (int)$id);
}

/** @return void Audit controlled action labels; detail must contain no provider identifiers. */
function ebAudit(string $actor, string $action, ?int $accountId = null, ?int $connectionId = null, ?string $detail = null): void
{
    ebInsert('bank_audit_log', ['at' => ebNow(), 'actor' => mb_substr($actor, 0, 100), 'action' => $action,
        'account_id' => $accountId, 'connection_id' => $connectionId, 'detail' => $detail]);
}

/**
 * Session-level tenant-scoped lock for short schema/settings/connection operations.
 * @template T
 * @param callable(): T $work
 * @return T
 */
function ebNamedLock(string $name, callable $work): mixed
{
    $key = 'saldi_eb_' . substr(hash('sha256', ($GLOBALS['db'] ?? '') . ':' . $name), 0, 40);
    if (ebMysql()) {
        if ((int)ebRow('SELECT GET_LOCK(' . ebSql($key) . ', 30) AS locked')['locked'] !== 1) {
            throw new EbDbException();
        }
    } else {
        ebRows('SELECT pg_advisory_lock(hashtext(' . ebSql($key) . '))');
    }
    try {
        return $work();
    } finally {
        ebRows(ebMysql() ? 'SELECT RELEASE_LOCK(' . ebSql($key) . ')' : 'SELECT pg_advisory_unlock(hashtext(' . ebSql($key) . '))');
    }
}

/** @return string Quoted trusted SQL identifier, including the reserved trigger column. */
function ebIdentifier(string $name): string
{
    if (!preg_match('/^[a-z_]+$/D', $name)) {
        throw new InvalidArgumentException('Invalid SQL identifier');
    }
    return ebMysql() ? '`' . $name . '`' : '"' . $name . '"';
}

/**
 * Guard the legacy tbl_exists() missing-table dereference without suppressing errors.
 * @return bool Whether the table exists in this tenant's current schema.
 */
function ebTableExists(string $table): bool
{
    $present = ebRow(ebMysql()
        ? 'SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=' . ebSql($table)
        : 'SELECT 1 FROM pg_tables WHERE schemaname=current_schema() AND tablename=' . ebSql($table)) !== null;
    return $present && (bool)tbl_exists($table);
}
