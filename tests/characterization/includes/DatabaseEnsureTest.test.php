<?php
// 20261125 CDX/NTR Exercise grouped schema declarations against PostgreSQL and optional MySQL/MariaDB fixtures.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DatabaseEnsureTest extends TestCase {
	private ?PDO $connection = null;
	private ?TestDatabase $owned = null;
	private ?string $mysqlDatabase = null;

	/** @return array<string, array{string}> Real database adapters; MySQL uses explicit test credentials. */
	public static function drivers(): array {
		return array('PostgreSQL' => array('postgresql'), 'MySQL' => array('mysqli'));
	}

	private function boot(string $driver): void {
		if ($driver === 'postgresql') {
			$reason = TestDatabase::unavailableReason();
			if ($reason !== null) {
				self::markTestSkipped($reason);
			}
			$this->connection = TestDatabase::sqlFixture(static::class)->pdo();
			$this->connection->exec('BEGIN');
			$schema = 'ensure_' . bin2hex(random_bytes(8));
			$this->connection->exec("CREATE SCHEMA $schema");
			$this->connection->exec("SET search_path TO $schema");
			$this->connection->exec('COMMIT');
		} else {
			$dsn = getenv('SALDI_SCHEMA_MYSQL_DSN');
			if (!$dsn || !extension_loaded('pdo_mysql')) {
				self::markTestSkipped('pdo_mysql and SALDI_SCHEMA_MYSQL_DSN are required for MySQL schema tests');
			}
			$this->connection = new PDO($dsn, getenv('SALDI_SCHEMA_MYSQL_USER') ?: '', getenv('SALDI_SCHEMA_MYSQL_PASSWORD') ?: '');
			$this->mysqlDatabase = 'ensure_' . bin2hex(random_bytes(8));
			$this->connection->exec("CREATE DATABASE {$this->mysqlDatabase}");
			$this->connection->exec("USE {$this->mysqlDatabase}");
		}
		$this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$GLOBALS['ensureTestConnection'] = $this->connection;
		$GLOBALS['ensureTestWrites'] = array();
		$GLOBALS['db_type'] = $driver;
		$GLOBALS['sqdb'] = 'schema_test';
		$GLOBALS['db_transaktion_depth'] = 0;
		if (!function_exists('db_select')) {
			function db_select($sql, $location = '') { return $GLOBALS['ensureTestConnection']->query($sql); }
			function db_modify($sql, $location = '') {
				$GLOBALS['ensureTestWrites'][] = $sql;
				$GLOBALS['ensureTestConnection']->exec($sql);
				return "0\tquery accepted";
			}
			function db_fetch_array($result) { return $result->fetch(PDO::FETCH_ASSOC); }
			function db_escape_string($value) { return substr($GLOBALS['ensureTestConnection']->quote((string)$value), 1, -1); }
		}
		require_once __DIR__ . '/../../../includes/database/ensureTable.php';
	}

	protected function tearDown(): void {
		if ($this->connection !== null) {
			if ($this->connection->inTransaction()) {
				$this->connection->rollBack();
			}
			if ($this->mysqlDatabase !== null) {
				$this->connection->exec("DROP DATABASE {$this->mysqlDatabase}");
			} else {
				$schema = $this->connection->query('SELECT current_schema()')->fetchColumn();
				if (is_string($schema) && str_starts_with($schema, 'ensure_')) {
					$this->connection->exec("DROP SCHEMA $schema CASCADE");
				}
			}
		}
		unset($GLOBALS['ensureTestConnection'], $GLOBALS['ensureTestWrites'], $GLOBALS['db_type'], $GLOBALS['sqdb'], $GLOBALS['db_transaktion_depth']);
		$this->connection = null;
	}

	#[DataProvider('drivers')]
	public function testCreatesGroupedColumnsAndARepeatDoesNoDdl(string $driver): void {
		$this->boot($driver);
		$types = array(new DB_ENSURE(DB_ENSURE::FLOAT3, array('procent', 'op_30')), new DB_ENSURE(DB_ENSURE::INTEGER, array('quantity')), new DB_ENSURE(DB_ENSURE::BIGINT, array('external_id')), new DB_ENSURE(DB_ENSURE::TEXT, array('description')), new DB_ENSURE(DB_ENSURE::BOOLEAN, array('enabled')));
		self::assertTrue(db_ensure_table('ensure_lines', $types));
		$this->connection->exec("INSERT INTO ensure_lines (procent, op_30, quantity, external_id, description, enabled) VALUES (1250.500, 0.005, -1, 9223372036854775807, 'æøå', " . ($driver === 'postgresql' ? 'TRUE' : '1') . ')');
		$before = $this->connection->query('SELECT * FROM ensure_lines')->fetch(PDO::FETCH_ASSOC);
		$GLOBALS['ensureTestWrites'] = array();
		self::assertFalse(db_ensure_table('ensure_lines', $types));
		self::assertSame(array(), $GLOBALS['ensureTestWrites']);
		self::assertSame($before, $this->connection->query('SELECT * FROM ensure_lines')->fetch(PDO::FETCH_ASSOC));
	}

	#[DataProvider('drivers')]
	public function testRepairsTypesAndAddsColumnsWhilePreservingDefaultsAndConstraints(string $driver): void {
		$this->boot($driver);
		$this->connection->exec("CREATE TABLE ensure_lines (amount varchar(40) NOT NULL DEFAULT '1.250', untouched varchar(40))");
		$this->connection->exec("INSERT INTO ensure_lines VALUES ('1250.500', 'keep')");
		self::assertTrue(db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::FLOAT3, array('amount', 'extra')))));
		$row = $this->connection->query('SELECT * FROM ensure_lines')->fetch(PDO::FETCH_ASSOC);
		self::assertSame('1250.500', $row['amount']);
		self::assertSame('keep', $row['untouched']);
		self::assertNull($row['extra']);
		$this->connection->exec("INSERT INTO ensure_lines (untouched) VALUES ('default')");
		self::assertSame('1.250', $this->connection->query("SELECT amount FROM ensure_lines WHERE untouched='default'")->fetchColumn());
		self::assertFalse(db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::FLOAT3, array('amount', 'extra')))));
		$this->expectException(PDOException::class);
		$this->connection->exec('INSERT INTO ensure_lines (amount) VALUES (NULL)');
	}

	#[DataProvider('drivers')]
	public function testDefaultConversionCorrectsPrecisionAndUsesDatabaseRounding(string $driver): void {
		$this->boot($driver);
		$this->connection->exec('CREATE TABLE ensure_lines (amount numeric(18,4))');
		$this->connection->exec('INSERT INTO ensure_lines VALUES (1250.5000), (0.0050), (-1.2500), (0.0005), (NULL)');
		self::assertTrue(db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::FLOAT3, array('amount')))));
		self::assertSame(array('1250.500', '0.005', '-1.250', '0.001', null), $this->connection->query('SELECT amount FROM ensure_lines')->fetchAll(PDO::FETCH_COLUMN));
		self::assertFalse(db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::FLOAT3, array('amount')))));
	}

	/** @return array<string, array{string, string}> Data the database cannot convert in strict mode. */
	public static function badNumbers(): array {
		$cases = array();
		foreach (self::drivers() as $label => [$driver]) {
			foreach (array('1000000000000', 'not a number', '') as $value) {
				$cases[$label . ' ' . $value] = array($driver, $value);
			}
		}
		return $cases;
	}

	#[DataProvider('badNumbers')]
	public function testInvalidConversionsLeaveEveryColumnAndRowIntact(string $driver, string $value): void {
		$this->boot($driver);
		$this->connection->exec('CREATE TABLE ensure_lines (amount varchar(40))');
		$this->connection->exec('INSERT INTO ensure_lines VALUES (' . $this->connection->quote($value) . ')');
		try {
			db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::TEXT, array('new_column')), new DB_ENSURE(DB_ENSURE::FLOAT3, array('amount'))));
			self::fail('Unsafe conversion was accepted');
		} catch (PDOException $error) {
			self::assertNotEmpty($error->getMessage());
		}
		self::assertSame($value, $this->connection->query('SELECT amount FROM ensure_lines')->fetchColumn());
		self::assertFalse(db_column_exists('ensure_lines', 'new_column'));
		self::assertTrue(db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::TEXT, array('amount')))));
	}

	#[DataProvider('drivers')]
	public function testInvalidDeclarationsFailBeforeAnyWrite(string $driver): void {
		$this->boot($driver);
		foreach (array(array('bad;table', array(new DB_ENSURE(DB_ENSURE::TEXT, array('name')))), array('ensure_lines', array('SQL INJECTION' => array('name'))), array('ensure_lines', array(new DB_ENSURE(DB_ENSURE::TEXT, array('name', 'NAME')))), array('ensure_lines', array())) as [$table, $types]) {
			try {
				db_ensure_table($table, $types);
				self::fail('Invalid declaration accepted');
			} catch (InvalidArgumentException $error) {
				self::assertSame(array(), $GLOBALS['ensureTestWrites']);
			}
		}
	}

	#[DataProvider('drivers')]
	public function testCallbackNormalizesOnlyExistingColumnsWhoseTypeChanges(string $driver): void {
		$this->boot($driver);
		$this->connection->exec('CREATE TABLE ensure_lines (amount varchar(40), percentage varchar(40))');
		$this->connection->exec("INSERT INTO ensure_lines VALUES ('1,250', '0,005')");
		$calls = array();
		$before = function (string $table, string $column, string $oldType, string $newType) use (&$calls): void {
			$calls[] = array($table, $column, $oldType, $newType);
			// Both identifiers come from the validated declaration in this fixture.
			db_modify("UPDATE $table SET $column = REPLACE($column, ',', '.')");
		};
		$types = array(new DB_ENSURE(DB_ENSURE::FLOAT3, array('amount', 'percentage', 'extra'), $before));
		self::assertTrue(db_ensure_table('ensure_lines', $types));
		self::assertCount(2, $calls);
		self::assertSame('ensure_lines', $calls[0][0]);
		self::assertSame('amount', $calls[0][1]);
		self::assertNotEmpty($calls[0][2]);
		self::assertSame('numeric(15,3)', $calls[0][3]);
		self::assertSame(array('amount' => '1.250', 'percentage' => '0.005', 'extra' => null), $this->connection->query('SELECT * FROM ensure_lines')->fetch(PDO::FETCH_ASSOC));
		self::assertFalse(db_ensure_table('ensure_lines', $types));
		self::assertCount(2, $calls);
	}

	#[DataProvider('drivers')]
	public function testCallbackCanVetoAConversionAndTheNextCallCanRetry(string $driver): void {
		$this->boot($driver);
		$this->connection->exec('CREATE TABLE ensure_lines (amount numeric(18,4))');
		$this->connection->exec('INSERT INTO ensure_lines VALUES (0.0005)');
		$before = static function (): void { throw new RuntimeException('Needs manual conversion'); };
		try {
			db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::FLOAT3, array('amount', 'extra'), $before)));
			self::fail('Callback did not stop the conversion');
		} catch (RuntimeException $error) {
			self::assertSame('Needs manual conversion', $error->getMessage());
		}
		self::assertSame('0.0005', $this->connection->query('SELECT amount FROM ensure_lines')->fetchColumn());
		self::assertFalse(db_column_exists('ensure_lines', 'extra'));
		self::assertTrue(db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::FLOAT3, array('amount')))));
	}

	#[DataProvider('drivers')]
	public function testIntegerTypesAreRepairedAndCurrentSchemaIsRespected(string $driver): void {
		$this->boot($driver);
		$this->connection->exec('CREATE TABLE ensure_lines (value smallint)');
		$this->connection->exec('INSERT INTO ensure_lines VALUES (-1), (0), (NULL)');
		self::assertTrue(db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::BIGINT, array('value')))));
		self::assertFalse(db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::BIGINT, array('value')))));
		self::assertTrue(db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::INTEGER, array('value')))));
		self::assertFalse(db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::INTEGER, array('value')))));
		if ($driver === 'postgresql') {
			$this->connection->exec('CREATE SCHEMA other_schema');
			try {
				$this->connection->exec('SET search_path TO other_schema');
				self::assertTrue(db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::TEXT, array('value')))));
			} finally {
				$this->connection->exec('DROP SCHEMA other_schema CASCADE');
			}
		}
	}

	#[DataProvider('drivers')]
	public function testBooleanConversionAndApplicationTransactionRefusal(string $driver): void {
		$this->boot($driver);
		$this->connection->exec('CREATE TABLE ensure_lines (enabled integer)');
		$this->connection->exec('INSERT INTO ensure_lines VALUES (0), (1), (NULL)');
		self::assertTrue(db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::BOOLEAN, array('enabled')))));
		self::assertFalse(db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::BOOLEAN, array('enabled')))));
		$GLOBALS['db_transaktion_depth'] = 1;
		$this->expectException(LogicException::class);
		db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::TEXT, array('extra'))));
	}

	#[DataProvider('drivers')]
	public function testMysqlRepairsRetainColumnCommentsCollationAndAutoIncrement(string $driver): void {
		$this->boot($driver);
		if ($driver === 'postgresql') {
			$this->connection->exec("CREATE TABLE ensure_lines (id serial PRIMARY KEY, description varchar(40) NOT NULL DEFAULT 'keep')");
		} else {
			$this->connection->exec("CREATE TABLE ensure_lines (id int AUTO_INCREMENT PRIMARY KEY, description varchar(40) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT 'keep' COMMENT 'column note')");
		}
		self::assertTrue(db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::BIGINT, array('id')), new DB_ENSURE(DB_ENSURE::TEXT, array('description')))));
		$this->connection->exec('INSERT INTO ensure_lines (id) VALUES (DEFAULT)');
		self::assertSame('keep', $this->connection->query('SELECT description FROM ensure_lines')->fetchColumn());
		if ($driver !== 'postgresql') {
			$columns = $this->connection->query('SHOW FULL COLUMNS FROM ensure_lines')->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
			self::assertSame('auto_increment', $columns['id']['Extra']);
			self::assertSame('latin1_bin', $columns['description']['Collation']);
			self::assertSame('column note', $columns['description']['Comment']);
		}
	}

	#[DataProvider('drivers')]
	public function testNumericToIntegerUsesTheDatabasesOwnConversion(string $driver): void {
		$this->boot($driver);
		$this->connection->exec('CREATE TABLE ensure_lines (amount numeric(15,3))');
		$this->connection->exec('INSERT INTO ensure_lines VALUES (1.500), (-1.500)');
		self::assertTrue(db_ensure_table('ensure_lines', array(new DB_ENSURE(DB_ENSURE::INTEGER, array('amount')))));
		self::assertSame(array(2, -2), array_map('intval', $this->connection->query('SELECT amount FROM ensure_lines')->fetchAll(PDO::FETCH_COLUMN)));
	}
}
