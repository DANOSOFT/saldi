<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/db.php --- ver 5.0.0 --- 2026.10.22
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// The one SQL adapter of the Shopify module (WP02, interface sheet 2.2): parameterized
// PostgreSQL access on the tenant connection and a savepoint-aware transaction. Every
// package reads and writes through these two functions; nothing here goes through
// db_modify(), so secrets never reach the legacy modify log.
//
// 20261022 Sawaneh SD-734: created (WP02-1).
// 20261022 Sawaneh SD-734 review: commit only a healthy transaction, DateTime bound as local wall-clock time.
// 20261022 Sawaneh SD-734 review 2: errors carry the primary message and SQLSTATE, never the DETAIL with bound
//                 values; NUL bytes in a statement or a parameter are refused.

/**
 * Injected by includes/connect.php and the bootstrap:
 * @var string $db_type
 * @var resource|\PgSql\Connection $connection
 */

final class ShopifyDbError extends RuntimeException
{
	/** @var string SQLSTATE of the failed statement, '' when the failure was not a server error. */
	public string $sqlstate = '';

	/** @var string Name of the violated constraint, '' when none. */
	public string $constraint = '';
}

/**
 * Runs one statement with bound parameters ($1, $2, ...) on the tenant connection and
 * returns every row. Writes return the RETURNING rows, or an empty list. Parameters are
 * scalars, null or DateTimeInterface; anything else, and any NUL byte, is refused before the
 * statement is sent, and a value is never interpolated into the SQL. A failure is reported with
 * the server's primary message and SQLSTATE only, so bound values never appear in the error.
 *
 * @param string $sql
 * @param list<scalar|null|DateTimeInterface> $params
 * @return list<array<string, string|null>>
 * @throws ShopifyDbError when the statement fails or the connection is not usable
 */
function shopify_db_rows(string $sql, array $params = []): array {
	$connection = shopify_db_connection();
	if (strpos($sql, "\0") !== false) {
		throw new InvalidArgumentException('the statement contains a NUL byte');
	}
	$bound = array();
	foreach (array_values($params) as $i => $value) {
		$bound[] = shopify_db_param($value, $i + 1);
	}
	// The asynchronous API hands back a result on failure too, so the error can be read as
	// fields: the primary message and SQLSTATE, never the DETAIL line that quotes bound values.
	$sent = $bound === array() ? @pg_send_query($connection, $sql) : @pg_send_query_params($connection, $sql, $bound);
	if (!$sent) {
		throw new ShopifyDbError('statement could not be sent: ' . shopify_db_sql_excerpt($sql));
	}
	$result = pg_get_result($connection);
	while (pg_get_result($connection) !== false) {
		// one statement was sent; anything further is drained so the connection stays usable
	}
	if ($result === false) {
		throw new ShopifyDbError('no result for statement: ' . shopify_db_sql_excerpt($sql));
	}
	$status = pg_result_status($result);
	if ($status === PGSQL_FATAL_ERROR || $status === PGSQL_NONFATAL_ERROR || $status === PGSQL_BAD_RESPONSE) {
		$error = new ShopifyDbError('statement failed: ' . trim((string)pg_result_error_field($result, PGSQL_DIAG_MESSAGE_PRIMARY)) . ' -- ' . shopify_db_sql_excerpt($sql));
		$error->sqlstate = (string)pg_result_error_field($result, PGSQL_DIAG_SQLSTATE);
		$error->constraint = (string)pg_result_error_field($result, PGSQL_DIAG_CONSTRAINT_NAME);
		throw $error;
	}
	$rows = pg_fetch_all($result, PGSQL_ASSOC);
	return $rows === false ? array() : $rows;
}

/**
 * Runs $op inside a transaction: BEGIN when the connection is idle, a SAVEPOINT when the
 * caller already holds one. The work is committed (or the savepoint released) when $op
 * returns, and rolled back to where it started when $op throws; the exception is rethrown.
 *
 * @param callable(): mixed $op
 * @return mixed whatever $op returns
 * @throws ShopifyDbError when the connection is already in an aborted transaction
 */
function shopify_db_transaction(callable $op) {
	static $depth = 0;
	$connection = shopify_db_connection();
	$status = pg_transaction_status($connection);
	if ($status === PGSQL_TRANSACTION_INERROR) {
		throw new ShopifyDbError('the current transaction is aborted; roll it back before starting new work');
	}
	if ($status !== PGSQL_TRANSACTION_IDLE && $status !== PGSQL_TRANSACTION_INTRANS) {
		throw new ShopifyDbError('the connection is busy or lost (transaction status ' . $status . ')');
	}
	$own = ($status === PGSQL_TRANSACTION_IDLE);
	$savepoint = 'shopify_tx_' . (++$depth);
	shopify_db_exec($connection, $own ? 'BEGIN' : "SAVEPOINT $savepoint");
	try {
		$value = $op();
		// COMMIT in an aborted transaction answers ROLLBACK without an error; a transaction
		// that $op closed itself (legacy transaktion('commit')) would commit nothing. Both are failures.
		$after = pg_transaction_status($connection);
		if ($after !== PGSQL_TRANSACTION_INTRANS) {
			throw new ShopifyDbError($after === PGSQL_TRANSACTION_INERROR
				? 'a statement inside the transaction failed and was not handled; the work is rolled back'
				: 'the transaction was ended inside the operation; the work cannot be committed here');
		}
	} catch (Throwable $e) {
		$depth--;
		@pg_query($connection, $own ? 'ROLLBACK' : "ROLLBACK TO SAVEPOINT $savepoint");
		if (!$own) {
			@pg_query($connection, "RELEASE SAVEPOINT $savepoint");
		}
		throw $e;
	}
	$depth--;
	shopify_db_exec($connection, $own ? 'COMMIT' : "RELEASE SAVEPOINT $savepoint");
	return $value;
}

/**
 * The tenant connection, checked to be an open PostgreSQL connection.
 *
 * @return resource|\PgSql\Connection
 */
function shopify_db_connection() {
	global $db_type, $connection;
	if (in_array(strtolower((string)$db_type), array('mysql', 'mysqli'), true)) {
		throw new ShopifyDbError('the Shopify module runs on PostgreSQL tenants only');
	}
	if (!$connection || !function_exists('pg_connection_status') || pg_connection_status($connection) !== PGSQL_CONNECTION_OK) {
		throw new ShopifyDbError('no open PostgreSQL connection; run shopify_bootstrap() first');
	}
	return $connection;
}

/**
 * One bound value as libpq expects it: text, with booleans as t/f and dates as wall-clock text in
 * PHP's default time zone, which is what the manifest's timestamp columns (no time zone) and
 * now() hold.
 *
 * @param mixed $value
 * @return string|null
 */
function shopify_db_param($value, int $position) {
	if ($value === null) {
		return null;
	}
	if (is_bool($value)) {
		return $value ? 't' : 'f';
	}
	if (is_string($value) && strpos($value, "\0") !== false) {
		throw new InvalidArgumentException("parameter \$$position contains a NUL byte, which PostgreSQL text cannot hold");
	}
	if (is_int($value) || is_float($value) || is_string($value)) {
		return (string)$value;
	}
	if ($value instanceof DateTimeInterface) {
		return DateTimeImmutable::createFromInterface($value)->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s.u');
	}
	throw new InvalidArgumentException("parameter \$$position must be a scalar, null or DateTimeInterface, " . gettype($value) . ' given');
}

/** Runs a transaction control statement and throws when it fails. */
function shopify_db_exec($connection, string $sql): void {
	if (@pg_query($connection, $sql) === false) {
		throw new ShopifyDbError("$sql failed: " . trim((string)pg_last_error($connection)));
	}
}

/** The first line of a statement, shortened, for error messages; never the bound values. */
function shopify_db_sql_excerpt(string $sql): string {
	$line = trim(strstr($sql, "\n", true) ?: $sql);
	return strlen($line) > 160 ? substr($line, 0, 157) . '...' : $line;
}
