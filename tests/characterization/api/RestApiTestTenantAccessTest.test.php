<?php
// tests/characterization/api/RestApiTestTenantAccessTest.test.php
//
// A test group's own database must look like a tenant to api/rest_api.php. The page tells the
// master database from a tenant by the underscore in <master>_<regnskab id>; a name without one
// is taken for the master and answered "Wrong IP" before the key and IP list are read.
//
// History:
// 20261111 CL/SZ MB-154 Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class RestApiTestTenantAccessTest extends CharacterizationTestCase
{
	/** The tenant's REST key for this run only, never a literal in the repository. */
	private static string $key;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		self::$key = bin2hex(random_bytes(16));
		self::rows("DELETE FROM grupper WHERE art = 'API'");
		self::rows(
			"INSERT INTO grupper (beskrivelse, art, kodenr, box1, box2) VALUES ($1, 'API', '1', $2, '*')",
			[Fixtures::TAG, self::$key]
		);
	}

	public function testATestDatabaseIsATenantToTheRestApi(): void
	{
		$answer = $this->call(self::$key);

		$this->assertNotSame('Wrong IP', $answer, 'the test database was taken for the master database');
		$this->assertIsArray($answer, 'a tenant with a valid key and IP list * gets the action\'s result');
		$this->assertMatchesRegularExpression('/^[a-z0-9]+_1$/', CharacterizationEnv::testDb(), 'shaped like <master>_<regnskab id>');
	}

	public function testAWrongKeyIsStillRefusedByTheTenantCheck(): void
	{
		$this->assertSame('Access denied (key)', $this->call(bin2hex(random_bytes(16))));
	}

	/** @return mixed The decoded JSON answer. */
	private function call(string $key)
	{
		$res = self::runChild('run_rest_api.php', [json_encode([
			'db' => CharacterizationEnv::testDb(),
			'saldiuser' => 'chartest',
			'key' => $key,
			'action' => 'get_all_labels',
		])]);
		$lines = array_values(array_filter(explode("\n", trim($res['stdout']))));
		$this->assertNotSame([], $lines, "rest_api.php wrote nothing.\nstderr: {$res['stderr']}");
		return json_decode(end($lines), true);
	}
}
