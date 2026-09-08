<?php

use PHPUnit\Framework\TestCase;

$recordLockRows = array();
$recordLockQueries = array();
$db_type = 'pgsql';

// 20260908 CDX/MJ Only install these stubs when the real helpers are absent. The fake and the
// real db_* functions cannot coexist in one PHP process, and PHPUnit loads every test file
// before it applies --filter, so on a full-suite run includes/db_query.php (required by the
// finans suite, which sorts first) is already loaded - redeclaring here aborted the whole run
// with "Cannot redeclare db_escape_string()". When the stubs cannot be installed the tests
// below skip with an explanation instead.
if (!function_exists('db_escape_string')) {
define('RECORD_LOCK_TEST_STUBS', true);

function db_escape_string($value) {
	return str_replace("'", "''", (string)$value);
}

function db_select($query, $source = null) {
	global $recordLockRows;

	if (strpos($query, 'information_schema.tables') !== false) {
		return array(array('exists' => 1));
	}
	if (preg_match("/FROM record_locks WHERE tabel='([^']+)' AND record_id=(\d+)/", $query, $matches)) {
		$key = $matches[1] . ':' . $matches[2];
		return isset($recordLockRows[$key]) ? array($recordLockRows[$key]) : array();
	}

	throw new RuntimeException('Unhandled SELECT: ' . $query);
}

function db_fetch_array($result) {
	return array_shift($result);
}

function db_modify($query, $source = null) {
	global $recordLockRows, $recordLockQueries;
	$recordLockQueries[] = preg_replace('/\s+/', ' ', trim($query));

	if (preg_match("/INSERT INTO record_locks .*VALUES \('([^']+)', (\d+), '([^']*)', '([^']*)', (\d+)\)/s", $query, $matches)) {
		$key = $matches[1] . ':' . $matches[2];
		if (!isset($recordLockRows[$key])) {
			$recordLockRows[$key] = array(
				'tabel' => $matches[1],
				'record_id' => (int)$matches[2],
				'brugernavn' => $matches[3],
				'session_id' => $matches[4],
				'locked_at' => (int)$matches[5],
			);
		}
	} elseif (preg_match("/DELETE FROM record_locks WHERE tabel='([^']+)' AND record_id=(\d+) AND brugernavn='([^']*)' AND session_id='([^']*)'/", $query, $matches)) {
		$key = $matches[1] . ':' . $matches[2];
		if (isset($recordLockRows[$key]) && $recordLockRows[$key]['brugernavn'] === $matches[3] && $recordLockRows[$key]['session_id'] === $matches[4]) {
			unset($recordLockRows[$key]);
		}
	}
}

} // end stub installation

if (defined('RECORD_LOCK_TEST_STUBS')) {
	require_once __DIR__ . '/../../../../includes/record_lock.php';
}

final class RecordLockCharacterizationTest extends TestCase {
	public static function setUpBeforeClass(): void {
		if (!defined('RECORD_LOCK_TEST_STUBS')) {
			self::markTestSkipped(
				'includes/db_query.php is already loaded in this process, so the db_* stubs could '
				. 'not be installed. Run this file on its own to exercise these tests: '
				. 'vendor/bin/phpunit tests/characterization/includes/record_lock/'
			);
		}
	}

	protected function setUp(): void {
		global $recordLockRows, $recordLockQueries;
		$recordLockRows = array();
		$recordLockQueries = array();
	}

	public function testAcquireMirrorsTheOwnerToLegacyHvem(): void {
		global $recordLockQueries;

		$this->assertNull(order_lock_check_acquire('ordrer', 42, 'alice', 'session-a'));
		$this->assertMatchesRegularExpression(
			"/UPDATE ordrer SET hvem='alice', tidspkt=\d+ WHERE id=42/",
			implode("\n", $recordLockQueries)
		);
	}

	public function testConflictingAcquireDoesNotReplaceLegacyHvem(): void {
		global $recordLockQueries;

		order_lock_check_acquire('ordrer', 42, 'alice', 'session-a');
		$recordLockQueries = array();
		$conflict = order_lock_check_acquire('ordrer', 42, 'bob', 'session-b');

		$this->assertSame('alice', $conflict['brugernavn']);
		$this->assertStringNotContainsString("UPDATE ordrer SET hvem='bob'", implode("\n", $recordLockQueries));
	}

	public function testHeartbeatRefreshesLegacyOwnerAndTimestamp(): void {
		global $recordLockQueries;

		order_lock_check_acquire('ordrer', 42, 'alice', 'session-a');
		$recordLockQueries = array();
		order_lock_refresh('ordrer', 42, 'alice', 'session-a');

		$this->assertMatchesRegularExpression(
			"/UPDATE ordrer SET hvem='alice', tidspkt=\d+ WHERE id=42/",
			implode("\n", $recordLockQueries)
		);
	}

	public function testReleaseClearsHvemOnlyForTheOwningSession(): void {
		global $recordLockQueries;

		order_lock_check_acquire('ordrer', 42, 'alice', 'session-a');
		$recordLockQueries = array();
		order_lock_release('ordrer', 42, 'alice', 'session-b');
		$this->assertStringNotContainsString("UPDATE ordrer SET hvem=''", implode("\n", $recordLockQueries));

		$recordLockQueries = array();
		order_lock_release('ordrer', 42, 'alice', 'session-a');
		$this->assertStringContainsString(
			"UPDATE ordrer SET hvem='' WHERE id=42 AND hvem='alice'",
			implode("\n", $recordLockQueries)
		);
	}
}
