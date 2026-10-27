<?php
// 20261027 CL/LH #877 (CodeRabbit): Cover the login-time deletion of the removed Aiia integration's OAuth client credentials.

namespace Saldi\Tests\AiiaCredentialCleanup;

use PHPUnit\Framework\TestCase;

/**
 * Stands in for includes/db_query.php's db_select(): a $global query goes to the master
 * database, any other query to the tenant database, both in-memory SQLite.
 */
function db_select($sql, $location, $global = false)
{
	$state = $GLOBALS['aiiaCredentialCleanup'];
	$state->queries[] = ['sql' => $sql, 'global' => $global];
	if (strpos($sql, 'information_schema.columns') !== false) {
		$hasVarGrp = $global ? $state->masterHasVarGrp : true;
		return new \ArrayIterator($hasVarGrp ? [['?column?' => 1]] : []);
	}
	$pdo = $global ? $state->master : $state->tenant;
	return new \ArrayIterator($pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC));
}

function db_fetch_array($result)
{
	if (!$result->valid()) {
		return false;
	}
	$row = $result->current();
	$result->next();
	return $row;
}

function db_modify($sql, $location, $global = false)
{
	$state = $GLOBALS['aiiaCredentialCleanup'];
	$state->writes[] = ['sql' => $sql, 'global' => $global];
	($global ? $state->master : $state->tenant)->exec($sql);
}

final class AiiaCredentialCleanupTest extends TestCase
{
	/** Rows every database starts with, as [var_grp, var_name, var_value, user_id]. */
	private const SEED = [
		['OAuth', 'Client ID', 'aiia-client-id', 0],
		['OAuth', 'Client Secret', 'aiia-client-secret', 0],
		['OAuth', 'oauth_data', 'encrypted-session', 3],
		['bank_integration', 'show_status', '1', 3],
		['bank_integration', 'date_method', 'last_quarter', 3],
		['globals', 'timezone', 'Europe/Copenhagen', 0],
		['mobilepay', 'client_id', 'mobilepay-client-id', 0],
	];

	protected function setUp(): void
	{
		if (!extension_loaded('pdo_sqlite')) {
			self::markTestSkipped('pdo_sqlite is not loaded.');
		}
		$GLOBALS['aiiaCredentialCleanup'] = (object) [
			'master' => $this->database(true),
			'tenant' => $this->database(true),
			'masterHasVarGrp' => true,
			'queries' => [],
			'writes' => [],
		];
	}

	protected function tearDown(): void
	{
		unset($GLOBALS['aiiaCredentialCleanup']);
	}

	public function testDeletesOnlyTheAiiaClientCredentialsFromTheMasterDatabase(): void
	{
		$this->cleanup('postgresql');
		$state = $GLOBALS['aiiaCredentialCleanup'];

		$expected = array_values(array_filter(self::SEED, function (array $row): bool {
			return !($row[0] === 'OAuth' && in_array($row[1], ['Client ID', 'Client Secret'], true));
		}));
		self::assertSame($this->expectedRows($expected), $this->rows($state->master));
		self::assertSame($this->expectedRows(self::SEED), $this->rows($state->tenant), 'tenant settings must not change');
		foreach (array_merge($state->queries, $state->writes) as $query) {
			self::assertTrue($query['global'], 'every statement must run against the master database: ' . $query['sql']);
		}
	}

	public function testALaterLoginOnlyReads(): void
	{
		$this->cleanup('postgresql');
		$this->cleanup('postgresql');
		self::assertCount(1, $GLOBALS['aiiaCredentialCleanup']->writes);
	}

	public function testMasterWithoutCredentialsIsNotWritten(): void
	{
		$GLOBALS['aiiaCredentialCleanup']->master->exec("DELETE FROM settings WHERE var_grp = 'OAuth' AND var_name IN ('Client ID', 'Client Secret')");
		$this->cleanup('postgresql');
		self::assertSame([], $GLOBALS['aiiaCredentialCleanup']->writes);
	}

	public function testMasterSettingsWithoutVarGrpIsLeftAlone(): void
	{
		$state = $GLOBALS['aiiaCredentialCleanup'];
		$state->master = $this->database(false);
		$state->masterHasVarGrp = false;
		$this->cleanup('postgresql');
		self::assertCount(1, $state->queries, 'only the column check may run');
		self::assertSame([], $state->writes);
	}

	public function testColumnCheckIsScopedToTheCurrentSchemaOrDatabase(): void
	{
		foreach (['postgresql' => 'table_schema = current_schema()', 'mysql' => 'table_schema = DATABASE()', 'mysqli' => 'table_schema = DATABASE()'] as $driver => $scope) {
			$this->setUp();
			$this->cleanup($driver);
			$state = $GLOBALS['aiiaCredentialCleanup'];
			self::assertStringContainsString($scope, $state->queries[0]['sql'], $driver);
			self::assertCount(1, $state->writes, $driver);
		}
	}

	/**
	 * A settings table seeded with self::SEED; without var_grp it has only the pre-4.0.3 columns.
	 */
	private function database(bool $withVarGrp): \PDO
	{
		$pdo = new \PDO('sqlite::memory:');
		$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		if (!$withVarGrp) {
			$pdo->exec('CREATE TABLE settings (id INTEGER PRIMARY KEY, var_name TEXT, var_value TEXT, var_description TEXT)');
			$pdo->exec("INSERT INTO settings (var_name, var_value) VALUES ('timezone', 'Europe/Copenhagen')");
			return $pdo;
		}
		$pdo->exec('CREATE TABLE settings (id INTEGER PRIMARY KEY, var_name TEXT, var_grp TEXT, var_value TEXT, var_description TEXT, user_id INTEGER)');
		$insert = $pdo->prepare('INSERT INTO settings (var_grp, var_name, var_value, user_id) VALUES (?, ?, ?, ?)');
		foreach (self::SEED as $row) {
			$insert->execute($row);
		}
		return $pdo;
	}

	/**
	 * @return list<array{var_grp: string, var_name: string, var_value: string, user_id: int}>
	 */
	private function rows(\PDO $pdo): array
	{
		return $pdo->query('SELECT var_grp, var_name, var_value, user_id FROM settings ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * @param list<array{0: string, 1: string, 2: string, 3: int}> $seed
	 * @return list<array{var_grp: string, var_name: string, var_value: string, user_id: int}>
	 */
	private function expectedRows(array $seed): array
	{
		return array_map(function (array $row): array {
			return ['var_grp' => $row[0], 'var_name' => $row[1], 'var_value' => $row[2], 'user_id' => $row[3]];
		}, $seed);
	}

	/**
	 * Runs only the Aiia credential block of includes/betweenUpdates.php, not the other pending updates.
	 */
	private function cleanup(string $db_type): void
	{
		$source = file_get_contents(__DIR__ . '/../../../includes/betweenUpdates.php');
		self::assertNotFalse($source, 'could not read includes/betweenUpdates.php');
		$start = strpos($source, '$aiiaCredentialsMysql =');
		self::assertNotFalse($start, 'the Aiia credential cleanup is missing from includes/betweenUpdates.php');
		$end = strpos($source, "\n}\n", $start);
		self::assertNotFalse($end);
		eval('namespace ' . __NAMESPACE__ . '; ' . substr($source, $start, $end + 3 - $start));
	}
}
