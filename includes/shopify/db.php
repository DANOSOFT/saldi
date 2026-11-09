<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/shopify/db.php --- ver 5.0.0 --- 2026.10.23
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
// 20261022 Sawaneh SD-734 CodeRabbit: every statement goes through the extended protocol, one statement per call.
// 20261022 Sawaneh SD-734 review (Saul, Codex): an operation that ends the transaction itself is ShopifyDbMisuse
//                 (work already final, never retried); error messages are built from SQLSTATE and identifier
//                 fields only, never the server text, which quotes values in many classes; COMMIT failures
//                 (deferred constraints) are read as fields too; depth counted after BEGIN succeeds.
// 20261023 CDX/LH Detect transaction replacement and commit-before-throw as non-retryable misuse.
// 20261023 CL/LH Also detect a replacement transaction that is already aborted (savepoint marker probe).

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
 * The operation given to shopify_db_transaction() ended the transaction itself. Its work is
 * already committed or rolled back by that call, so this is a programming error to fix, never
 * a failure to retry.
 */
final class ShopifyDbMisuse extends LogicException
{
}

/**
 * Runs one statement with bound parameters ($1, $2, ...) on the tenant connection and
 * returns every row. Writes return the RETURNING rows, or an empty list. Parameters are
 * scalars, null or DateTimeInterface; anything else, and any NUL byte, is refused before the
 * statement is sent, and a value is never interpolated into the SQL. One statement per call; a
 * second one in the string is refused. A failure is reported with the SQLSTATE, the table,
 * column and constraint names and a statement excerpt, never with the server's message text,
 * so bound values cannot appear in the error (see shopify_db_error_from_result()).
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
	// Always the extended protocol, also without parameters: it carries one statement only, so a
	// second statement in the string is refused instead of run unchecked. The asynchronous API
	// hands back a result on failure too, so the error can be read as fields: the primary message
	// and SQLSTATE, never the DETAIL line that quotes bound values.
	if (!@pg_send_query_params($connection, $sql, $bound)) {
		throw new ShopifyDbError('statement could not be sent: ' . shopify_db_sql_excerpt($sql));
	}
	$result = pg_get_result($connection);
	while (pg_get_result($connection) !== false) {
		// the extended protocol carries one statement, so this only clears the asynchronous state
	}
	if ($result === false) {
		throw new ShopifyDbError('no result for statement: ' . shopify_db_sql_excerpt($sql));
	}
	if (shopify_db_result_failed($result)) {
		throw shopify_db_error_from_result($result, $sql);
	}
	$rows = pg_fetch_all($result, PGSQL_ASSOC);
	return $rows === false ? array() : $rows;
}

/**
 * Runs $op inside a transaction: BEGIN when the connection is idle, a SAVEPOINT when the
 * caller already holds one. The work is committed (or the savepoint released) when $op
 * returns, and rolled back to where it started when $op throws; the exception is rethrown.
 * $op must leave the transaction open: one that commits or rolls back itself (legacy
 * transaktion('commit') does) has already decided its work's fate, and the call ends with
 * ShopifyDbMisuse, a programming error that must not be retried.
 *
 * @param callable(): mixed $op
 * @return mixed whatever $op returns
 * @throws ShopifyDbError when the connection is already in an aborted transaction, a statement
 *   inside $op failed unhandled (the work is rolled back), or the commit itself fails
 * @throws ShopifyDbMisuse when $op ended the transaction itself (its work is already final)
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
	$savepoint = 'shopify_tx_' . ($depth + 1);
	shopify_db_exec($connection, $own ? 'BEGIN' : "SAVEPOINT $savepoint");
	if ($own) {
		// Marker inside the own transaction: in an aborted state only ROLLBACK TO SAVEPOINT can
		// tell the original transaction from a replacement (see shopify_db_transaction_ended()).
		shopify_db_exec($connection, "SAVEPOINT $savepoint");
	}
	$transactionId = shopify_db_rows('SELECT txid_current() AS id')[0]['id'];
	$depth++;
	try {
		$value = $op();
		$after = pg_transaction_status($connection);
		if (shopify_db_transaction_ended($connection, $transactionId, $savepoint)) {
			// $op ended or replaced the original transaction. Its work, including the caller's
			// earlier uncommitted work, is final; a replacement transaction must be left alone.
			$depth--;
			throw new ShopifyDbMisuse('the operation ended the transaction itself; its work is already committed or rolled back, fix the operation and do not retry');
		}
		if ($after !== PGSQL_TRANSACTION_INTRANS) {
			// COMMIT in an aborted transaction answers ROLLBACK without an error, so it is refused here.
			throw new ShopifyDbError('a statement inside the transaction failed and was not handled; the work is rolled back');
		}
	} catch (ShopifyDbMisuse $e) {
		throw $e;
	} catch (Throwable $e) {
		$depth--;
		if (shopify_db_transaction_ended($connection, $transactionId, $savepoint)) {
			throw new ShopifyDbMisuse('the operation ended the transaction itself; its work is already committed or rolled back, fix the operation and do not retry', 0, $e);
		}
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
 * Detects an operation that ended or replaced the transaction, before any cleanup is sent.
 * An open transaction is compared by txid. An aborted one cannot run that query, so it is
 * probed with ROLLBACK TO SAVEPOINT on the wrapper's marker: that only succeeds inside the
 * original transaction (which it leaves rolled back to the marker, ready for the normal
 * cleanup); in a replacement the savepoint does not exist (3B001) and nothing is changed.
 *
 * @param resource|\PgSql\Connection $connection
 * @param string $transactionId transaction identity recorded after BEGIN or SAVEPOINT
 * @param string $savepoint the marker savepoint created by shopify_db_transaction()
 * @return bool whether the original transaction has ended
 */
function shopify_db_transaction_ended($connection, string $transactionId, string $savepoint): bool {
	$status = pg_transaction_status($connection);
	if ($status === PGSQL_TRANSACTION_IDLE) {
		return true;
	}
	if ($status === PGSQL_TRANSACTION_INTRANS) {
		return shopify_db_rows('SELECT txid_current() AS id')[0]['id'] !== $transactionId;
	}
	if ($status === PGSQL_TRANSACTION_INERROR) {
		try {
			shopify_db_exec($connection, "ROLLBACK TO SAVEPOINT $savepoint");
		} catch (ShopifyDbError $probe) {
			return $probe->sqlstate === '3B001';
		}
	}
	return false;
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
 * now() hold. shopify_bootstrap() pairs PHP's zone with the tenant PostgreSQL session zone.
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

/** Runs a transaction control statement and throws when it fails, e.g. a deferred constraint at COMMIT. */
function shopify_db_exec($connection, string $sql): void {
	if (!@pg_send_query_params($connection, $sql, array())) {
		throw new ShopifyDbError("$sql could not be sent");
	}
	$result = pg_get_result($connection);
	while (pg_get_result($connection) !== false) {
		// clears the asynchronous state
	}
	if ($result === false || shopify_db_result_failed($result)) {
		throw $result === false ? new ShopifyDbError("$sql gave no result") : shopify_db_error_from_result($result, $sql);
	}
}

/** @param resource|\PgSql\Result $result */
function shopify_db_result_failed($result): bool {
	$status = pg_result_status($result);
	return $status === PGSQL_FATAL_ERROR || $status === PGSQL_NONFATAL_ERROR || $status === PGSQL_BAD_RESPONSE;
}

/**
 * Builds the error for a failed result without the server's message text. PostgreSQL quotes
 * the offending input in its primary message for many classes (invalid input syntax, tsquery
 * syntax, unknown relation or type named by a cast, ...), so no class of message is safe to copy
 * when the values can be secrets. The error carries the SQLSTATE with its condition name, the
 * table, column and constraint names from the diagnostic fields (identifiers, never values),
 * and the statement excerpt.
 *
 * @param resource|\PgSql\Result $result
 */
function shopify_db_error_from_result($result, string $sql): ShopifyDbError {
	$sqlstate = (string)pg_result_error_field($result, PGSQL_DIAG_SQLSTATE);
	$parts = array(shopify_db_condition_name($sqlstate) . " (SQLSTATE $sqlstate)");
	foreach (array('table' => PGSQL_DIAG_TABLE_NAME, 'column' => PGSQL_DIAG_COLUMN_NAME, 'constraint' => PGSQL_DIAG_CONSTRAINT_NAME) as $label => $field) {
		$name = (string)pg_result_error_field($result, $field);
		if ($name !== '') {
			$parts[] = "$label $name";
		}
	}
	$error = new ShopifyDbError('statement failed: ' . implode(', ', $parts) . ' -- ' . shopify_db_sql_excerpt($sql));
	$error->sqlstate = $sqlstate;
	$error->constraint = (string)pg_result_error_field($result, PGSQL_DIAG_CONSTRAINT_NAME);
	return $error;
}

/** PostgreSQL's condition name for the SQLSTATEs the module meets; the bare code otherwise. */
function shopify_db_condition_name(string $sqlstate): string {
	$names = array(
		'23505' => 'unique_violation',
		'23503' => 'foreign_key_violation',
		'23502' => 'not_null_violation',
		'23514' => 'check_violation',
		'22P02' => 'invalid_text_representation',
		'22007' => 'invalid_datetime_format',
		'22003' => 'numeric_value_out_of_range',
		'22001' => 'string_data_right_truncation',
		'22012' => 'division_by_zero',
		'42601' => 'syntax_error',
		'42703' => 'undefined_column',
		'42P01' => 'undefined_table',
		'42704' => 'undefined_object',
		'42883' => 'undefined_function',
		'40001' => 'serialization_failure',
		'40P01' => 'deadlock_detected',
		'55P03' => 'lock_not_available',
		'25P02' => 'in_failed_sql_transaction',
		'57014' => 'query_canceled',
		'08006' => 'connection_failure',
	);
	return $names[$sqlstate] ?? 'error';
}

/** The first line of a statement, shortened, for error messages; never the bound values. */
function shopify_db_sql_excerpt(string $sql): string {
	$line = trim(strstr($sql, "\n", true) ?: $sql);
	return strlen($line) > 160 ? substr($line, 0, 157) . '...' : $line;
}
