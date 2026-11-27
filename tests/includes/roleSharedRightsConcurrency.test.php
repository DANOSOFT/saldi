<?php
// 20261127 ASR Created. $sharedWithLive: the repair of stored rights strings at login takes the locks of role administration first.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * perm_store_shared_rights() renders again, at login, the stored rights string of a user with a role that still holds a
 * read position. It locks the role rows and then the user rows, in the order role administration locks them
 * (perm_lock_roles(), then brugere), and reads the user's role and the role's permissions only after that. An
 * assignment or a change of a role's permissions that is under way on another connection is therefore waited for, and
 * the repair never writes a string computed from what the user or the role had before.
 *
 * The administrator works on this test's connection (the real user_set_role() and perm_edit_role(), inside an open
 * transaction); the repair runs at the same time on a second connection, in a child process
 * (support/run_shared_rights_repair.php). The administrator commits once the repair waits for a lock.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class roleSharedRightsConcurrency extends TestCase
{
	private ?PDO $db = null;
	private ?PDO $observer = null;
	private string $schema = '';

	protected function setUp(): void
	{
		$dsn = getenv('SALDI_ROLE_TEST_DSN');
		if (!$dsn || !extension_loaded('pdo_pgsql')) {
			self::markTestSkipped('Requires pdo_pgsql and SALDI_ROLE_TEST_DSN/USER/PASSWORD for an isolated test database');
		}
		preg_match_all('/(?:^|;)dbname=([^;]+)/', $dsn, $databases);
		if (count($databases[1]) !== 1 || !preg_match('/^(?:roles_test_|saldi_chartest)[A-Za-z0-9_]*$/', $databases[1][0])) {
			throw new RuntimeException('Role tests require an explicitly named disposable database');
		}
		$this->db = self::connect();
		$this->observer = self::connect();
		$this->schema = 'roles_repair_' . bin2hex(random_bytes(8));
		$this->db->exec('create schema ' . $this->schema);
		$this->db->exec('set search_path to ' . $this->schema);
		$this->db->exec("create table roles (id serial primary key, role_key text, navn text, beskrivelse text, is_built_in boolean);
			create table role_permissions (role_id int, permission_key text, level text, primary key (role_id, permission_key));
			create table brugere (id serial primary key, brugernavn text, role_id int, rettigheder text, status boolean, ansat_id int default 0, kode text, regnskabsaar int, ip_address text, tlf text, twofactor boolean, email text);
			create table settings (id serial primary key, user_id int, var_grp text, var_name text, var_value text);
			create table audit_log (id serial primary key, bruger_id int, brugernavn text, handling text, detaljer text, ip text, objekt_type text, objekt_id text, kilde text);");
		$GLOBALS['role_test_db'] = $this->db;
		$GLOBALS['sharedWithLive'] = true;
		$GLOBALS['bruger_id'] = 1;
		$GLOBALS['brugernavn'] = 'actor';
		$GLOBALS['rettigheder'] = str_repeat('1', 16);
		$GLOBALS['revisor'] = false;
		$GLOBALS['role_test_fail'] = '';
		$GLOBALS['db_modify_fejl'] = false;
		require_once __DIR__ . '/support/RoleDatabase.php';
		require_once __DIR__ . '/../../includes/roleManagement.php';
		// 1 Administrator (the actor), 2 Team (journal write, orders read: the member's role), 3 Reader (journal read only).
		$this->db->exec("insert into roles (id, role_key, navn, beskrivelse, is_built_in) values (1,'administrator','Administrator','All',true),(2,null,'Team','Original',false),(3,null,'Reader','Fewer rights',false);
			insert into brugere (id,brugernavn,role_id,rettigheder,status) values (1,'actor',1,'1111111111111111',true),(2,'member',2,'0000000000000000',true),(3,'other-admin',1,'1111111111111111',true)");
		$this->db->exec("select setval(pg_get_serial_sequence('roles','id'), 3)");
		$this->db->exec("select setval(pg_get_serial_sequence('brugere','id'), 3)");
		$this->seedLevels(1, permission_default_roles()['administrator']['levels']);
		$this->seedLevels(2, array('finans.kassekladde' => 'write', 'debitor.ordre' => 'read'));
		$this->seedLevels(3, array('finans.kassekladde' => 'read'));
		// The member's string as it was stored before the installation shared its ledgers: read as '2'.
		$this->db->exec("update brugere set rettigheder = '" . perm_legacy_string(perm_levels_from_role(2)) . "' where id = 2");
		perm_current_levels(true);
	}

	protected function tearDown(): void
	{
		if ($this->db && $this->schema !== '') {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			$this->db->exec('drop schema ' . $this->schema . ' cascade');
		}
		unset($GLOBALS['sharedWithLive']);
	}

	private static function connect(): PDO
	{
		return new PDO((string) getenv('SALDI_ROLE_TEST_DSN'), getenv('SALDI_ROLE_TEST_USER') ?: '', getenv('SALDI_ROLE_TEST_PASSWORD') ?: '', array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
	}

	private function seedLevels(int $id, array $levels): void
	{
		$stmt = $this->db->prepare('insert into role_permissions values (?, ?, ?)');
		foreach ($levels as $key => $level) {
			$stmt->execute(array($id, $key, $level));
		}
	}

	private function stored(int $id): string
	{
		return (string) $this->db->query('select rettigheder from brugere where id = ' . $id)->fetchColumn();
	}

	private static function journalPosition(): int
	{
		return permission_registry()['finans.kassekladde']['legacy'][0];
	}

	/**
	 * Start the repair on its own connection and return once it waits for a lock this test's open transaction holds.
	 *
	 * @return array{proc: resource, pipes: array<int, resource>}
	 */
	private function startRepairAndWaitForItsLock(): array
	{
		$name = 'saldi_repair_' . bin2hex(random_bytes(4));
		$proc = proc_open(array(PHP_BINARY, __DIR__ . '/support/run_shared_rights_repair.php', $this->schema, $name),
			array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		self::assertIsResource($proc);
		$deadline = microtime(true) + 20;
		$stmt = $this->observer->prepare("select count(*) from pg_stat_activity where application_name = ? and wait_event_type = 'Lock'");
		while (true) {
			$stmt->execute(array($name));
			if ((int) $stmt->fetchColumn() > 0) {
				return array('proc' => $proc, 'pipes' => $pipes);
			}
			$status = proc_get_status($proc);
			if (!$status['running'] || microtime(true) > $deadline) {
				$out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
				proc_terminate($proc);
				self::fail('the repair did not wait for the administrator\'s locks: ' . $out);
			}
			usleep(20000);
		}
	}

	/** @return array{exit: int, out: string} */
	private static function finish(array $child): array
	{
		$out = stream_get_contents($child['pipes'][1]);
		$err = stream_get_contents($child['pipes'][2]);
		fclose($child['pipes'][1]);
		fclose($child['pipes'][2]);
		return array('exit' => proc_close($child['proc']), 'out' => $out . $err);
	}

	public function test_an_assignment_under_way_is_not_overwritten_by_the_repair(): void
	{
		self::assertStringContainsString('2', $this->stored(2), 'the member still holds a read position, so the repair takes the member');
		$child = null;
		perm_atomic(function () use (&$child) {
			self::assertSame('', user_set_role(2, 3, 1), 'the member gets the role with fewer rights');
			$child = $this->startRepairAndWaitForItsLock();
		});
		$result = self::finish($child);
		self::assertSame(0, $result['exit'], $result['out']);

		$row = $this->db->query('select role_id, rettigheder from brugere where id = 2')->fetch(PDO::FETCH_ASSOC);
		self::assertSame(3, (int) $row['role_id']);
		self::assertSame(perm_legacy_string_stored(perm_levels_from_role(3)), $row['rettigheder'], 'the string of the role the member has now');
		self::assertSame('0', substr($row['rettigheder'], self::journalPosition(), 1), 'no journal write from the earlier role');
	}

	public function test_a_reduction_of_the_role_under_way_is_not_overwritten_by_the_repair(): void
	{
		$role = $this->db->query('select * from roles where id = 2')->fetch(PDO::FETCH_ASSOC);
		$levels = perm_levels_from_role(2);
		$post = array('id' => 2, 'navn' => $role['navn'], 'beskrivelse' => $role['beskrivelse'], 'level' => $levels, 'role_revision' => perm_role_revision($role, $levels));
		$post['level']['finans.kassekladde'] = 'read';
		$child = null;
		perm_atomic(function () use ($post, &$child) {
			self::assertSame('', perm_edit_role($post, 1)['error'], 'the role loses journal write');
			$child = $this->startRepairAndWaitForItsLock();
		});
		$result = self::finish($child);
		self::assertSame(0, $result['exit'], $result['out']);

		self::assertSame('read', perm_levels_from_role(2)['finans.kassekladde']);
		self::assertSame(perm_legacy_string_stored(perm_levels_from_role(2)), $this->stored(2), 'the string of the reduced role');
		self::assertSame('0', substr($this->stored(2), self::journalPosition(), 1), 'no journal write from before the reduction');
	}

	public function test_a_repair_with_nothing_under_way_renders_the_string_once(): void
	{
		$child = proc_open(array(PHP_BINARY, __DIR__ . '/support/run_shared_rights_repair.php', $this->schema, 'saldi_repair_alone'),
			array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		$result = self::finish(array('proc' => $child, 'pipes' => $pipes));
		self::assertSame(0, $result['exit'], $result['out']);
		self::assertStringContainsString('{"rendered":1}', $result['out']);
		self::assertSame(perm_legacy_string_stored(perm_levels_from_role(2)), $this->stored(2));
		self::assertStringNotContainsString('2', $this->stored(2));
		self::assertSame('1111111111111111', $this->stored(1), 'a string without a read position is left as it is');
	}
}
