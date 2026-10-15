<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/runtime/SaldiDb.php --- ai/mcp-assistant --- 2026.10.01
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
//
// Independent PDO access and transaction-scoped locks for the runtime.
//
// 20261001 CDX/LH Created.
// 20261005 CL/LH Postgres sessions run with jit off (seconds of JIT compile on mis-estimated queries).

declare(strict_types=1);

final class SaldiDb
{
	private int $depth = 0;
	private array $tables = [];
	private array $mysqlLocks = [];
	private function __construct(private ?PDO $pdo, private string $type, private string $database)
	{
	}
	/** Open an independent connection using data-only runtime configuration. */
	public static function connect(array $cfg, string $database, int $statementTimeoutMs = 5000): self
	{
		if (!preg_match('/^[A-Za-z0-9_-]{1,63}$/D', $database)) {
			throw new InvalidArgumentException('Invalid database name');
		}
		$type = in_array($cfg['db_type'], ['mysql', 'mysqli'], true) ? 'mysql' : 'postgresql';
		$host = (string)$cfg['host'];
		if (preg_match('/[;\x00-\x20]/', $host)) {
			throw new InvalidArgumentException('Invalid database host');
		}
		$port = (int)($cfg['port'] ?? ($type === 'mysql' ? 3306 : 5432));
		$dsn = $type === 'mysql' ? "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4" : "pgsql:host=$host;port=$port;dbname=$database;connect_timeout=3";
		$pdo = new PDO($dsn, $cfg['user'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 3]);
		$ms = max(1, $statementTimeoutMs);
		if ($type === 'postgresql') {
			$pdo->exec("SET statement_timeout = '$ms ms'");
			// Short interactive queries never repay JIT compilation; with stale statistics a
			// cheap query can be estimated above jit_above_cost and spend seconds compiling.
			$pdo->exec('SET jit = off');
			$pdo->exec("SET client_encoding = 'UTF8'");
			$pdo->exec("SET TIME ZONE 'UTC'");
		} else {
			$pdo->exec('SET NAMES utf8mb4');
			$pdo->exec("SET time_zone = '+00:00'");
			$pdo->exec("SET SESSION MAX_EXECUTION_TIME = $ms");
		}
		return new self($pdo, $type, $database);
	}
	private function query(string $sql, array $params): PDOStatement
	{
		if (!$this->pdo) {
			throw new RuntimeException('Connection closed');
		}
		$query = $this->pdo->prepare($sql);
		$query->execute($params);
		return $query;
	}
	/** @return array<string,mixed>|null */
	public function one(string $sql, array $params = []): ?array
	{
		return $this->query($sql, $params)->fetch() ?: null;
	}
	/** @return list<array<string,mixed>> */
	public function all(string $sql, array $params = []): array
	{
		return $this->query($sql, $params)->fetchAll();
	}
	/** Return the first column, or null when no row exists. */
	public function value(string $sql, array $params = []): mixed
	{
		$v = $this->query($sql, $params)->fetchColumn();
		return $v === false ? null : $v;
	}
	/** Execute a write and return its affected-row count. */
	public function exec(string $sql, array $params = []): int
	{
		$n = $this->query($sql, $params)->rowCount();
		if (preg_match('/^\s*(CREATE|DROP|ALTER)\b/i', $sql)) {
			$this->tables = [];
		}
		return $n;
	}
	private function identifier(string $name): string
	{
		if (!preg_match('/^[a-z][a-z0-9_]*$/D', $name)) {
			throw new InvalidArgumentException('Invalid SQL identifier');
		}
		return $this->isPostgres() ? '"' . $name . '"' : '`' . $name . '`';
	}
	private function insertSql(string $table, array $row): string
	{
		return 'INSERT INTO ' . $this->identifier($table) . ' (' . implode(',', array_map($this->identifier(...), array_keys($row))) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')';
	}
	/** Insert bound values and return the explicit or generated id. */
	public function insert(string $table, array $row): int
	{
		$sql = $this->insertSql($table, $row);
		if ($this->isPostgres()) {
			return (int)$this->value($sql . ' RETURNING id', array_values($row));
		}
		$this->exec($sql, array_values($row));
		return isset($row['id']) ? (int)$row['id'] : (int)$this->pdo->lastInsertId();
	}
	/** Return null on a unique conflict without aborting the transaction. */
	public function insertIgnore(string $table, array $row, array $conflictColumns): ?int
	{
		foreach ($conflictColumns as $column) {
			$this->identifier($column);
		}
		$sql = $this->insertSql($table, $row);
		if ($this->isPostgres()) {
			// No conflict target handles either independent unique key on inbox_items.
			$target = count($conflictColumns) === 1 ? ' (' . $this->identifier($conflictColumns[0]) . ')' : '';
			$id = $this->value($sql . ' ON CONFLICT' . $target . ' DO NOTHING RETURNING id', array_values($row));
			return $id === null ? null : (int)$id;
		}
		$n = $this->exec(str_replace('INSERT INTO', 'INSERT IGNORE INTO', $sql), array_values($row));
		return $n === 0 ? null : (isset($row['id']) ? (int)$row['id'] : (int)$this->pdo->lastInsertId());
	}
	/** Nest transactions with savepoints; release MySQL advisory locks at outer completion. */
	public function transaction(callable $fn): mixed
	{
		$depth = $this->depth;
		if ($depth === 0) {
			$this->pdo->beginTransaction();
		} else {
			$this->exec('SAVEPOINT sp_' . $depth);
		}
		$this->depth++;
		try {
			$result = $fn($this);
			if ($depth === 0) {
				// MySQL DDL can commit implicitly while the runtime schema lock stays held.
				if ($this->pdo->inTransaction()) {
					$this->pdo->commit();
				}
			} else {
				$this->exec('RELEASE SAVEPOINT sp_' . $depth);
			}
			return $result;
		} catch (Throwable $e) {
			if ($depth === 0 && $this->pdo->inTransaction()) {
				$this->pdo->rollBack();
			} elseif ($depth > 0) {
				$this->exec('ROLLBACK TO SAVEPOINT sp_' . $depth);
				$this->exec('RELEASE SAVEPOINT sp_' . $depth);
			}
			$this->tables = [];
			throw $e;
		} finally {
			$this->depth = $depth;
			if ($depth === 0) {
				foreach ($this->mysqlLocks as $name) {
					$this->value('SELECT RELEASE_LOCK(?)', [$name]);
				}
				$this->mysqlLocks = [];
			}
		}
	}
	/** Whether this wrapper currently owns a transaction. */
	public function inTransaction(): bool
	{
		return $this->depth > 0;
	}
	/** Acquire a transaction-scoped advisory lock; requires transaction(). */
	public function lock(string $name, int $timeoutSeconds = 10): void
	{
		if (!$this->inTransaction()) {
			throw new LogicException('Advisory locks require transaction()');
		}
		if ($this->isPostgres()) {
			$old = $this->value("SELECT current_setting('lock_timeout')");
			$this->exec("SET LOCAL lock_timeout = '" . max(1, $timeoutSeconds * 1000) . "ms'");
			$this->value('SELECT pg_advisory_xact_lock(hashtext(?))', [$name]);
			$this->value("SELECT set_config('lock_timeout', ?, true)", [$old]);
		} elseif (!in_array($name, $this->mysqlLocks, true)) {
			if ((int)$this->value('SELECT GET_LOCK(?, ?)', [$name, $timeoutSeconds]) !== 1) {
				throw new RuntimeException('Advisory lock unavailable');
			}
			$this->mysqlLocks[] = $name;
		}
	}
	/** Whether this handle uses PostgreSQL. */
	public function isPostgres(): bool
	{
		return $this->type === 'postgresql';
	}
	/** Normalized database backend name. */
	public function dbType(): string
	{
		return $this->type;
	}
	/** The validated database name bound to this handle. */
	public function name(): string
	{
		return $this->database;
	}
	/** Cached, schema-scoped table existence check. */
	public function hasTable(string $table): bool
	{
		$this->identifier($table);
		return $this->tables[$table] ??= $this->value('SELECT 1 FROM information_schema.tables WHERE table_schema = ' . ($this->isPostgres() ? 'current_schema()' : 'DATABASE()') . ' AND table_name = ?', [$table]) !== null;
	}
	/** Schema-scoped column existence check. */
	public function hasColumn(string $table, string $column): bool
	{
		$this->identifier($table);
		$this->identifier($column);
		return $this->value('SELECT 1 FROM information_schema.columns WHERE table_schema = ' . ($this->isPostgres() ? 'current_schema()' : 'DATABASE()') . ' AND table_name = ? AND column_name = ?', [$table, $column]) !== null;
	}
	/** Release this connection; forbidden during an active transaction. */
	public function close(): void
	{
		if ($this->inTransaction()) {
			throw new LogicException('Cannot close inside transaction');
		}
		$this->pdo = null;
		$this->tables = [];
	}
}
