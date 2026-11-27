<?php
// 20261127 ASR Created. $sharedWithLive: the rights string stored for a user with a role holds read as '0', and this installation takes the levels from the role.
// 20261127 ASR The daily summary, the assistant's record reads and the SOAP login take the levels from the role too.
// 20261127 ASR The copied gate is named for what it does: it reads the string position by position, without roles.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * An installation with $sharedWithLive = true in includes/connect.php opens ledgers that another installation serves too
 * through the same master database, one that reads brugere.rettigheder position by position and does not know roles.
 * positionGateOpens() is that module gate (includes/online.php there). The string stored in brugere.rettigheder opens
 * a position there exactly where the role gives write; this installation still gives read where the role gives read.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class roleSharedRights extends TestCase
{
	private ?PDO $db = null;
	private string $schema = '';

	/** The module gate that reads the string position by position: true when the page at $modulnr opens. */
	private static function positionGateOpens(string $rettigheder, int $modulnr): bool
	{
		if (($rettigheder) && ($modulnr) && (substr($rettigheder, $modulnr, 1) < '1')) {
			return false;
		}
		return true;
	}

	/** @return array<int, array<string, string>> level maps: each key alone at read and at write, and random mixes */
	private static function levelMaps(): array
	{
		$keys = array_keys(permission_registry());
		$maps = array();
		foreach ($keys as $key) {
			$maps[] = array($key => 'read');
			$maps[] = array($key => 'write');
		}
		mt_srand(20261127);
		for ($i = 0; $i < 300; $i++) {
			$map = array();
			foreach ($keys as $key) {
				$map[$key] = array('none', 'read', 'write')[mt_rand(0, 2)];
			}
			$maps[] = $map;
		}
		return $maps;
	}

	/** @param array<string, string> $levels */
	private static function writePositions(array $levels): array
	{
		$open = array();
		foreach (permission_registry() as $key => $def) {
			if (($levels[$key] ?? 'none') === 'write') {
				foreach ($def['legacy'] as $pos) {
					$open[$pos] = true;
				}
			}
		}
		return $open;
	}

	public function test_shared_the_stored_string_opens_only_what_the_role_gives_as_write(): void
	{
		require_once __DIR__ . '/../../includes/permissions.php';
		$GLOBALS['sharedWithLive'] = true;
		foreach (self::levelMaps() as $levels) {
			$stored = perm_legacy_string_stored($levels);
			self::assertStringNotContainsString('2', $stored);
			$write = self::writePositions($levels);
			for ($pos = 1; $pos < 16; $pos++) {
				self::assertSame(isset($write[$pos]), self::positionGateOpens($stored, $pos), json_encode($levels) . " position $pos");
			}
			self::assertSame(str_replace('2', '0', perm_legacy_string($levels)), $stored, 'only read positions change');
		}
	}

	public function test_not_shared_the_stored_string_is_the_rendered_string(): void
	{
		require_once __DIR__ . '/../../includes/permissions.php';
		unset($GLOBALS['sharedWithLive']);
		self::assertFalse(perm_shared_with_live());
		foreach (self::levelMaps() as $levels) {
			self::assertSame(perm_legacy_string($levels), perm_legacy_string_stored($levels));
		}
		$GLOBALS['sharedWithLive'] = false;
		self::assertFalse(perm_shared_with_live());
		self::assertSame('0120', perm_session_rights(array('rettigheder' => ' 0120 ', 'role_id' => 5)), 'the stored string, trimmed');
	}

	/** A disposable schema with the role tables (tests/includes/support/RoleDatabase.php). */
	private function database(): void
	{
		$dsn = getenv('SALDI_ROLE_TEST_DSN');
		if (!$dsn || !extension_loaded('pdo_pgsql')) {
			self::markTestSkipped('Requires pdo_pgsql and SALDI_ROLE_TEST_DSN/USER/PASSWORD for an isolated test database');
		}
		preg_match_all('/(?:^|;)dbname=([^;]+)/', $dsn, $databases);
		if (count($databases[1]) !== 1 || !preg_match('/^(?:roles_test_|saldi_chartest)[A-Za-z0-9_]*$/', $databases[1][0])) {
			throw new RuntimeException('Role tests require an explicitly named disposable database');
		}
		$this->db = new PDO($dsn, getenv('SALDI_ROLE_TEST_USER') ?: '', getenv('SALDI_ROLE_TEST_PASSWORD') ?: '', array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
		$this->schema = 'roles_shared_' . bin2hex(random_bytes(8));
		$this->db->exec('create schema ' . $this->schema);
		$this->db->exec('set search_path to ' . $this->schema);
		$this->db->exec("create table roles (id serial primary key, role_key text, navn text, beskrivelse text, is_built_in boolean);
			create table role_permissions (role_id int, permission_key text, level text, primary key (role_id, permission_key));
			create table brugere (id serial primary key, brugernavn text, role_id int, rettigheder text, status boolean, ansat_id int default 0, kode text, regnskabsaar int, ip_address text, tlf text, twofactor boolean, email text);
			create table settings (id serial primary key, user_id int, var_grp text, var_name text, var_value text);");
		$GLOBALS['role_test_db'] = $this->db;
		require_once __DIR__ . '/support/RoleDatabase.php';
		require_once __DIR__ . '/../../includes/permissions.php';
		$this->db->exec("insert into roles (id, role_key, navn, beskrivelse, is_built_in) values (1, 'kunvisning', 'Kun visning', '', true), (2, null, 'Mixed', '', false);
			insert into brugere (id, brugernavn, role_id, rettigheder, status) values (1, 'reader', 1, '0222222222222222', true), (2, 'mixed', 2, '0000000000000000', true), (3, 'norole', null, '0120', true)");
		$stmt = $this->db->prepare('insert into role_permissions values (?, ?, ?)');
		foreach (array('finans.kassekladde', 'debitor.ordre', 'lager.varer') as $key) {
			$stmt->execute(array(1, $key, 'read'));
		}
		$stmt->execute(array(2, 'finans.kassekladde', 'write'));
		$stmt->execute(array(2, 'debitor.ordre', 'read'));
	}

	protected function tearDown(): void
	{
		if ($this->db && $this->schema !== '') {
			$this->db->exec('drop schema ' . $this->schema . ' cascade');
		}
		unset($GLOBALS['sharedWithLive']);
	}

	private function stored(int $id): string
	{
		return (string) $this->db->query('select rettigheder from brugere where id = ' . $id)->fetchColumn();
	}

	public function test_shared_sync_stores_read_as_zero_and_the_session_reads_the_role(): void
	{
		$this->database();
		$GLOBALS['sharedWithLive'] = true;
		perm_sync_role(1);
		perm_sync_user(2);
		self::assertStringNotContainsString('2', $this->stored(1));
		self::assertStringNotContainsString('2', $this->stored(2));
		$kassekladde = permission_registry()['finans.kassekladde']['legacy'][0];
		$ordre = permission_registry()['debitor.ordre']['legacy'][0];
		self::assertTrue(self::positionGateOpens($this->stored(2), $kassekladde), 'write opens there');
		self::assertFalse(self::positionGateOpens($this->stored(2), $ordre), 'read does not');
		self::assertFalse(self::positionGateOpens($this->stored(1), $ordre));

		$reader = $this->db->query('select * from brugere where id = 1')->fetch(PDO::FETCH_ASSOC);
		$session = perm_session_rights($reader);
		self::assertSame(perm_legacy_string(perm_levels_from_role(1)), $session, 'this installation reads the role');
		self::assertSame('2', substr($session, $ordre, 1), 'read stays read here');
		$noRole = $this->db->query('select * from brugere where id = 3')->fetch(PDO::FETCH_ASSOC);
		self::assertSame('0120', perm_session_rights($noRole), 'a user without a role keeps the stored string');
	}

	public function test_shared_a_stored_read_position_is_rendered_again_at_login(): void
	{
		$this->database();
		$GLOBALS['sharedWithLive'] = true;
		self::assertSame(1, perm_store_shared_rights(), 'the reader holds a read position; the user without a role is left alone');
		self::assertStringNotContainsString('2', $this->stored(1));
		self::assertSame('0120', $this->stored(3));
		self::assertSame(0, perm_store_shared_rights(), 'nothing left the next time');
	}

	public function test_shared_the_assistant_reads_the_role(): void
	{
		$this->database();
		require_once __DIR__ . '/../../includes/assist/RecordAuth.php';
		$GLOBALS['sharedWithLive'] = true;
		perm_sync_role(1);
		$ordre = permission_registry()['debitor.ordre']['legacy'][0];
		$reader = $this->db->query('select * from brugere where id = 1')->fetch(PDO::FETCH_ASSOC);
		self::assertSame('0', substr((string) $reader['rettigheder'], $ordre, 1), 'stored as no access');
		$rights = saldi_assist_rights($this->db, $reader);
		self::assertSame(perm_legacy_string(perm_levels_from_role(1)), $rights, 'the role, as a page request reads it');
		self::assertSame('2', substr($rights, $ordre, 1));
		$noRole = $this->db->query('select * from brugere where id = 3')->fetch(PDO::FETCH_ASSOC);
		self::assertSame('0120', saldi_assist_rights($this->db, $noRole), 'a user without a role keeps the stored string');
		self::assertSame('', saldi_assist_rights($this->db, null));
		unset($GLOBALS['sharedWithLive']);
		self::assertSame((string) $reader['rettigheder'], saldi_assist_rights($this->db, $reader), 'not shared: the stored string');
	}

	public function test_shared_the_daily_summary_and_the_soap_login_read_the_session_string(): void
	{
		$root = __DIR__ . '/../../';
		$digest = (string) file_get_contents($root . 'tools/notification_digest.php');
		self::assertStringContainsString('notif_refresh($bruger_id, perm_session_rights($u), $sprog);', $digest);
		self::assertStringNotContainsString("(string) \$u['rettigheder'], \$sprog", $digest);
		$soap = (string) file_get_contents($root . 'soapserver/logon.php');
		self::assertMatchesRegularExpression("/\\\$rettigheder = perm_session_rights\\(\\\$r\\);\\s*\\}\\s*\\\$regnskabsaar=/", $soap);
	}

	public function test_not_shared_nothing_changes(): void
	{
		$this->database();
		unset($GLOBALS['sharedWithLive']);
		self::assertSame(0, perm_store_shared_rights());
		self::assertSame('0222222222222222', $this->stored(1));
		perm_sync_user(2);
		self::assertSame(perm_legacy_string(perm_levels_from_role(2)), $this->stored(2), 'read is stored as 2');
		$reader = $this->db->query('select * from brugere where id = 1')->fetch(PDO::FETCH_ASSOC);
		self::assertSame('0222222222222222', perm_session_rights($reader), 'the stored string');
	}
}
