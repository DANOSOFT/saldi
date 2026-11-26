<?php
// --- includes/database/ensureTable.php --- ver 5.1.1 --- 2026-11-25 ---
// LICENSE: GNU GPL v2 or later. Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261125 CDX/NTR Declare portable table columns by type and repair missing or incorrectly typed columns.

require_once __DIR__ . '/compatibility.php';

/** Portable column declarations for db_ensure_table(); see doc/database-schema.md. */
final class DB_ENSURE {
	public const INTEGER = 'integer';
	public const BIGINT = 'bigint';
	public const FLOAT3 = 'numeric(15,3)';
	public const TEXT = 'text';
	public const BOOLEAN = 'boolean';
	public readonly string $type;
	/** @var list<string> */
	public readonly array $columns;
	/** @var Closure(string, string, string, string): void|null */
	public readonly ?Closure $beforeAlter;

	/**
	 * Declare a group of columns with one portable type and optional conversion hook.
	 * @param string $type One of this class's type constants.
	 * @param list<string> $columns Column names with this type.
	 * @param callable(string, string, string, string): void|null $beforeAlter Receives table, column, old SQL type and new SQL type; may normalize data or throw.
	 */
	public function __construct(string $type, array $columns, ?callable $beforeAlter = null) {
		if (!in_array($type, array(self::INTEGER, self::BIGINT, self::FLOAT3, self::TEXT, self::BOOLEAN), true) || !$columns || !array_is_list($columns)) {
			throw new InvalidArgumentException('Use a DB_ENSURE constant and a nonempty list of column names.');
		}
		foreach ($columns as $column) {
			if (!is_string($column)) {
				throw new InvalidArgumentException('Column names must be strings.');
			}
			self::identifier($column);
		}
		$this->type = $type;
		$this->columns = $columns;
		$this->beforeAlter = $beforeAlter === null ? null : Closure::fromCallable($beforeAlter);
	}

	/**
	 * Apply a validated declaration to the current tenant. Use db_ensure_table() at call sites.
	 * @param string $table Unqualified table name in the current schema/database.
	 * @param list<DB_ENSURE> $groups Column declarations.
	 * @return bool True if schema changed; false when already correct.
	 * @throws InvalidArgumentException For invalid identifiers, types or duplicate columns.
	 * @throws RuntimeException For incompatible data, unsupported column attributes or failed SQL.
	 */
	public static function ensure(string $table, array $groups): bool {
		global $db_transaktion_depth;
		self::identifier($table);
		$columns = self::declaration($groups);
		if ($db_transaktion_depth ?? 0) {
			throw new LogicException('db_ensure_table() must run outside application transactions.');
		}
		if (!self::changes($columns, self::metadata($table))) {
			return false;
		}
		$lock = 'db_ensure_table:' . $table;
		if (!db_lock($lock)) {
			throw new RuntimeException("Could not lock schema for $table.");
		}
		$transaction = $tableLock = false;
		$mode = null;
		try {
			$metadata = self::metadata($table);
			$changes = self::changes($columns, $metadata);
			if (!$changes) {
				return false;
			}
			$name = self::identifier($table);
			if (!$metadata) {
				$definitions = array();
				foreach ($columns as $column => $declaration) {
					$definitions[] = self::identifier($column) . ' ' . self::sqlType($declaration->type);
				}
				self::write("CREATE TABLE $name (" . implode(', ', $definitions) . ')');
				return true;
			}
			if (db_is_mysql()) {
				$mode = db_fetch_array(self::read('SELECT @@SESSION.sql_mode AS mode'))['mode'];
				self::write("SET SESSION sql_mode = '" . db_escape_string($mode . ',STRICT_ALL_TABLES') . "'");
				self::write("LOCK TABLES $name WRITE");
				$tableLock = true;
			} else {
				self::write('BEGIN');
				$transaction = true;
				self::write("LOCK TABLE $name IN ACCESS EXCLUSIVE MODE");
			}
			// Re-read after taking the table lock; another schema writer may have finished meanwhile.
			$metadata = self::metadata($table);
			$actions = array();
			foreach (self::changes($columns, $metadata) as $column => $declaration) {
				$type = $declaration->type;
				$quoted = self::identifier($column);
				$sqlType = self::sqlType($type);
				if (!isset($metadata[$column])) {
					$actions[] = "ADD COLUMN $quoted $sqlType";
					continue;
				}
				$row = $metadata[$column];
				if ($declaration->beforeAlter !== null) {
					$oldType = db_is_mysql() ? $row['column_type'] : $row['data_type'];
					if (!db_is_mysql() && $oldType === 'numeric' && $row['numeric_precision'] !== null) {
						$oldType .= '(' . (int)$row['numeric_precision'] . ',' . (int)$row['numeric_scale'] . ')';
					}
					($declaration->beforeAlter)($table, $column, $oldType, $sqlType);
				}
				if (db_is_mysql()) {
					$actions[] = "MODIFY COLUMN $quoted $sqlType" . self::mysqlAttributes($table, $column, $type, $row);
				} else {
					if (($row['is_generated'] ?? 'NEVER') !== 'NEVER') {
						throw new RuntimeException("Generated column $table.$column needs an explicit migration.");
					}
					$default = $row['column_default'];
					if ($default !== null) {
						$actions[] = "ALTER COLUMN $quoted DROP DEFAULT";
					}
					$actions[] = "ALTER COLUMN $quoted TYPE $sqlType USING $quoted::$sqlType";
					if ($default !== null) {
						$actions[] = "ALTER COLUMN $quoted SET DEFAULT (($default)::$sqlType)";
					}
				}
			}
			if ($actions) {
				self::write("ALTER TABLE $name " . implode(', ', $actions));
			}
			if ($transaction) {
				self::write('COMMIT');
				$transaction = false;
			}
			return (bool)$actions;
		} finally {
			try {
				if ($transaction) {
					self::write('ROLLBACK');
				}
				if ($tableLock) {
					self::write('UNLOCK TABLES');
				}
				if ($mode !== null) {
					self::write("SET SESSION sql_mode = '" . db_escape_string($mode) . "'");
				}
			} finally {
				db_unlock($lock);
			}
		}
	}

	/** @return array<string, DB_ENSURE> Column name => validated declaration. */
	private static function declaration(array $groups): array {
		$columns = $seen = array();
		foreach ($groups as $declaration) {
			if (!$declaration instanceof self) {
				throw new InvalidArgumentException('Pass an array of DB_ENSURE declarations.');
			}
			foreach ($declaration->columns as $name) {
				if (!is_string($name)) {
					throw new InvalidArgumentException('Column names must be strings.');
				}
				self::identifier($name);
				$key = strtolower($name);
				if (isset($seen[$key])) {
					throw new InvalidArgumentException("Duplicate column $name.");
				}
				$seen[$key] = true;
				$columns[$name] = $declaration;
			}
		}
		if (!$columns) {
			throw new InvalidArgumentException('Declare at least one column.');
		}
		return $columns;
	}

	/** @return string Safely quoted, portable identifier; qualified names and SQL fragments are refused. */
	private static function identifier(string $name): string {
		if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,62}\z/', $name)) {
			throw new InvalidArgumentException("Invalid schema identifier: $name.");
		}
		$quote = db_is_mysql() ? '`' : '"';
		return $quote . $name . $quote;
	}

	/** @return string SQL definition for a validated constant. FLOAT3 is exact decimal, not binary float. */
	private static function sqlType(string $type): string {
		return db_is_mysql() && $type === self::TEXT ? 'longtext' : $type;
	}

	/** @return array<string, array<string, mixed>> Metadata restricted to the current schema/database. */
	private static function metadata(string $table): array {
		$fields = array('column_name', 'data_type', 'numeric_precision', 'numeric_scale', 'is_nullable', 'column_default');
		$fields = array_merge($fields, db_is_mysql() ? array('column_type', 'extra', 'character_set_name', 'collation_name', 'column_comment') : array('is_generated'));
		// MySQL 8 reports INFORMATION_SCHEMA field names in uppercase unless aliases are explicit.
		$select = implode(', ', array_map(static fn($field) => "$field AS $field", $fields));
		$result = self::read("SELECT $select FROM information_schema.columns WHERE table_name = '" . db_escape_string($table) . "'" . opdat_schema_filter(db_type()));
		$columns = array();
		while ($row = db_fetch_array($result)) {
			$columns[$row['column_name']] = $row;
		}
		return $columns;
	}

	/** @return array<string, DB_ENSURE> Missing or differently typed declared columns. */
	private static function changes(array $columns, array $metadata): array {
		return array_filter($columns, static function ($declaration, $column) use ($metadata) {
			$type = $declaration->type;
			if (!isset($metadata[$column])) {
				return true;
			}
			$row = $metadata[$column];
			$actual = strtolower($row['data_type']);
			if (str_contains($row['column_type'] ?? '', 'unsigned')) {
				return true;
			}
			if ($type === self::FLOAT3) {
				return !in_array($actual, array('numeric', 'decimal'), true) || (int)$row['numeric_precision'] !== 15 || (int)$row['numeric_scale'] !== 3;
			}
			if ($type === self::INTEGER) {
				return !in_array($actual, array('int', 'integer'), true);
			}
			if ($type === self::BOOLEAN && db_is_mysql()) {
				return !preg_match('/\Atinyint\(1\)\z/', $row['column_type']);
			}
			return $actual !== self::sqlType($type);
		}, ARRAY_FILTER_USE_BOTH);
	}

	/** @return string Existing MySQL attributes, which MODIFY would otherwise discard. */
	private static function mysqlAttributes(string $table, string $column, string $type, array $row): string {
		$extra = strtolower($row['extra']);
		if ($extra !== '' && $extra !== 'auto_increment' && $extra !== 'default_generated') {
			throw new RuntimeException("Column $table.$column has attributes that need an explicit migration.");
		}
		if ($extra === 'auto_increment' && !in_array($type, array(self::INTEGER, self::BIGINT), true)) {
			throw new RuntimeException("Cannot change auto-increment column $table.$column to $type.");
		}
		$attributes = '';
		if ($type === self::TEXT && $row['collation_name'] !== null) {
			$attributes .= ' CHARACTER SET ' . self::identifier($row['character_set_name']) . ' COLLATE ' . self::identifier($row['collation_name']);
		}
		$attributes .= $row['is_nullable'] === 'YES' ? ' NULL' : ' NOT NULL';
		// SHOW COLUMNS returns literal defaults without MariaDB's information_schema quoting.
		$shown = db_fetch_array(self::read('SHOW FULL COLUMNS FROM ' . self::identifier($table) . " WHERE Field = '" . db_escape_string($column) . "'"));
		if ($shown['Default'] !== null) {
			$default = $extra === 'default_generated' ? $shown['Default'] : "'" . db_escape_string($shown['Default']) . "'";
			$attributes .= " DEFAULT ($default)";
		}
		if ($extra === 'auto_increment') {
			$attributes .= ' AUTO_INCREMENT';
		}
		return $attributes . " COMMENT '" . db_escape_string($row['column_comment']) . "'";
	}

	/** @return mixed A successful legacy database result; false never reaches metadata readers. */
	private static function read(string $sql) {
		$result = db_select($sql, __FILE__ . ' linje ' . __LINE__);
		if ($result === false) {
			throw new RuntimeException('Schema query failed.');
		}
		return $result;
	}

	/** @return void Stop on a legacy write failure instead of reporting a successful repair. */
	private static function write(string $sql): void {
		$result = db_modify($sql, __FILE__ . ' linje ' . __LINE__);
		if ($result === false || (is_string($result) && str_starts_with($result, "1\t"))) {
			throw new RuntimeException('Schema change failed.');
		}
	}
}

/**
 * Ensure declared table columns exist with their portable types in the current tenant.
 * Call only from install/update paths or includes/structure builders, outside transactions.
 * @param string $table Unqualified table name.
 * @param list<DB_ENSURE> $types Validated column declarations and optional conversion hooks.
 * @return bool True when the schema changed; false on a repeat with matching types.
 * @throws InvalidArgumentException For an invalid declaration.
 * @throws RuntimeException When conversion is unsafe or a database operation fails.
 */
function db_ensure_table(string $table, array $types): bool {
	return DB_ENSURE::ensure($table, $types);
}
