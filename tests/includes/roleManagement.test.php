<?php
// 20261110 CDX/ASR Database-backed role rollback, concurrent edits, transfer preflight and privilege regression tests.
// 20261114 CL/ASR The fixture's roles table names the built-in flag is_built_in, as roles.system is renamed (master d7cdeb81).
// 20261124 CL/ASR The tests get their database from TestDatabase (sqlFixture, private random schema), so CI runs them without SALDI_ROLE_TEST_DSN.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class roleManagement extends TestCase
{
	private ?PDO $db = null;
	/** Owned SQL fixture database; the lock test opens a second connection to it. */
	private ?TestDatabase $database = null;
	private string $schema = '';

	protected function setUp(): void
	{
		if (!extension_loaded('pdo_pgsql')) {
			self::markTestSkipped('Requires pdo_pgsql for the role database tests');
		}
		require_once __DIR__ . '/../characterization/support/database/TestDatabase.php';
		$reason = TestDatabase::unavailableReason();
		if ($reason !== null) {
			self::markTestSkipped($reason);
		}
		// TestDatabase generates and owns this empty database, so it is disposable by construction.
		// Every table lives in this test's private random schema, which tearDown drops.
		$this->database = TestDatabase::sqlFixture(static::class);
		$this->db = $this->database->pdo();
		$this->schema = 'roles_case_' . bin2hex(random_bytes(8));
		$this->db->exec('create schema ' . $this->schema);
		$this->db->exec('set search_path to ' . $this->schema);
		$this->db->exec("create table roles (id serial primary key, role_key text, navn text, beskrivelse text, is_built_in boolean);
			create table role_permissions (role_id int, permission_key text, level text, primary key (role_id, permission_key));
			create table brugere (id serial primary key, brugernavn text, role_id int, rettigheder text, status boolean, ansat_id int default 0, kode text, regnskabsaar int, ip_address text, tlf text, twofactor boolean, email text);
			create table settings (id serial primary key, user_id int, var_grp text, var_name text, var_value text);
			create table audit_log (id serial primary key, bruger_id int, brugernavn text, handling text, detaljer text, ip text, objekt_type text, objekt_id text, kilde text);");
		$GLOBALS['role_test_db'] = $this->db;
		$GLOBALS['bruger_id'] = 1;
		$GLOBALS['brugernavn'] = 'actor';
		$GLOBALS['rettigheder'] = str_repeat('1', 16);
		$GLOBALS['revisor'] = false;
		$GLOBALS['role_test_fail'] = '';
		$GLOBALS['db_modify_fejl'] = false;
		require_once __DIR__ . '/support/RoleDatabase.php';
		require_once __DIR__ . '/../../includes/roleManagement.php';
		$this->db->exec("insert into roles (id, role_key, navn, beskrivelse, is_built_in) values (1,'administrator','Administrator','All',true),(2,null,'Team','Original',false),(3,null,'Destination','Target',false);
			insert into brugere (id,brugernavn,role_id,rettigheder,status) values (1,'actor',1,'1111111111111111',true),(2,'member',2,'0000000000000000',true),(3,'other-admin',1,'1111111111111111',true)");
		$this->db->exec("select setval(pg_get_serial_sequence('roles','id'), 3)");
		$this->db->exec("select setval(pg_get_serial_sequence('brugere','id'), 3)");
		$this->seedLevels(1, permission_default_roles()['administrator']['levels']);
		$this->seedLevels(2, array('finans.kassekladde' => 'write', 'debitor.ordre' => 'read'));
		$this->seedLevels(3, array('finans.kassekladde' => 'read', 'lager.varer' => 'write'));
		perm_sync_role(2);
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
	}

	private function seedLevels(int $id, array $levels): void
	{
		$stmt = $this->db->prepare('insert into role_permissions values (?, ?, ?)');
		foreach ($levels as $key => $level) {
			$stmt->execute(array($id, $key, $level));
		}
	}

	private function post(int $id): array
	{
		$role = $this->db->query('select * from roles where id = ' . $id)->fetch(PDO::FETCH_ASSOC);
		$levels = perm_levels_from_role($id);
		return array('id' => $id, 'navn' => $role['navn'], 'beskrivelse' => $role['beskrivelse'], 'level' => $levels, 'role_revision' => perm_role_revision($role, $levels));
	}

	public function testNewCrmPermissionInheritsActualSourceOnceIncludingCustomizedStandardRoles(): void
	{
		$known = array_diff(array_keys(permission_registry()), array('crm.access'));
		$stmt = $this->db->prepare("insert into settings(var_grp,var_name,var_value) values ('permissions','known_keys',?)");
		$stmt->execute(array(implode(',', $known)));
		$this->db->exec("delete from role_permissions where permission_key='crm.access';
			delete from role_permissions where role_id=1 and permission_key='debitor.konti';
			insert into role_permissions values (2,'debitor.konti','read'),(3,'debitor.konti','write'),(3,'crm.access','none')");
		perm_seed_new_keys(array('administrator' => 1));
		self::assertSame('none', perm_levels_from_role(1)['crm.access'], 'Customized Administrator does not gain CRM');
		self::assertSame('read', perm_levels_from_role(2)['crm.access']);
		self::assertSame('none', perm_levels_from_role(3)['crm.access'], 'Explicit none survives upgrade');
		$this->db->exec("update role_permissions set level='write' where role_id=2 and permission_key='debitor.konti'");
		perm_seed_new_keys(array('administrator' => 1));
		self::assertSame('read', perm_levels_from_role(2)['crm.access'], 'Later debtor edits do not recouple CRM');
		self::assertSame('0', perm_legacy_string(array('crm.access' => 'write'))[6]);
		foreach (permission_default_roles() as $role) {
			self::assertSame($role['levels']['debitor.konti'] ?? 'none', $role['levels']['crm.access']);
		}
	}

	public function testOwnAdministratorCannotRemoveBackupAndRetainsExistingCustomizations(): void
	{
		$this->db->exec("delete from role_permissions where role_id=1 and permission_key='settings.email'");
		$post = $this->post(1);
		$post['level']['settings.backup'] = 'none';
		self::assertSame('ownrolereduction', perm_edit_role($post, 1)['error']);
		self::assertSame('write', perm_levels_from_role(1)['settings.backup']);
		self::assertSame('none', perm_levels_from_role(1)['settings.email']);
		self::assertSame(0, (int) $this->db->query('select count(*) from audit_log')->fetchColumn());
	}

	public function testStaleSaveCannotOverwriteAnotherAdministratorsCommittedChange(): void
	{
		$old = $this->post(2);
		$new = $old;
		$new['level']['finans.kassekladde'] = 'read';
		self::assertSame('', perm_edit_role($new, 1)['error']);
		$old['navn'] = 'Stale name';
		self::assertSame('rolestale', perm_edit_role($old, 1)['error']);
		self::assertSame('read', perm_levels_from_role(2)['finans.kassekladde']);
		self::assertSame('Team', $this->db->query('select navn from roles where id=2')->fetchColumn());
		self::assertStringContainsString('finans.kassekladde: write -> read', $this->db->query('select detaljer from audit_log')->fetchColumn());
		self::assertSame(perm_legacy_string(perm_levels_from_role(2)), $this->db->query('select rettigheder from brugere where id=2')->fetchColumn());
	}

	public function testFailedAuditRollsBackMetadataPermissionsAndLegacyRights(): void
	{
		$post = $this->post(2);
		$before = $post['level'];
		$legacy = $this->db->query('select rettigheder from brugere where id=2')->fetchColumn();
		$post['navn'] = 'Changed';
		$post['level']['finans.kassekladde'] = 'none';
		$GLOBALS['role_test_fail'] = 'insert into audit_log';
		try {
			perm_edit_role($post, 1);
			self::fail('The injected failure must abort saving');
		} catch (RuntimeException $error) {
			self::assertSame('Injected write failure', $error->getMessage());
		}
		self::assertSame($before, perm_levels_from_role(2));
		self::assertSame('Team', $this->db->query('select navn from roles where id=2')->fetchColumn());
		self::assertSame($legacy, $this->db->query('select rettigheder from brugere where id=2')->fetchColumn());
		self::assertSame(0, (int) $this->db->query('select count(*) from audit_log')->fetchColumn());
	}

	public function testDeletePreflightsOwnUserBeforeMovingAnyOtherHolder(): void
	{
		$this->db->exec('update brugere set role_id=2 where id=1');
		$this->seedLevels(2, array('settings.roles.manage' => 'write', 'settings.users.manage' => 'write', 'lager.varer' => 'write'));
		$post = $this->post(2);
		self::assertSame('ownrole', perm_delete_role(2, 3, 1, $post['role_revision']));
		self::assertSame(2, (int) $this->db->query('select role_id from brugere where id=2')->fetchColumn());
		self::assertSame(1, (int) $this->db->query('select count(*) from roles where id=2')->fetchColumn());
		self::assertSame(0, (int) $this->db->query('select count(*) from audit_log')->fetchColumn());
	}

	public function testDeleteMovesEveryHolderAndAuditsBothAddedAndRemovedCapabilities(): void
	{
		$post = $this->post(2);
		self::assertSame('', perm_delete_role(2, 3, 1, $post['role_revision']));
		self::assertSame(3, (int) $this->db->query('select role_id from brugere where id=2')->fetchColumn());
		self::assertSame(0, (int) $this->db->query('select count(*) from roles where id=2')->fetchColumn());
		$audit = $this->db->query("select detaljer from audit_log where handling='user.role_changed'")->fetchColumn();
		self::assertStringContainsString('finans.kassekladde: write -> read', $audit);
		self::assertStringContainsString('lager.varer: none -> write', $audit);
		self::assertStringContainsString('debitor.ordre: read -> none', $audit);
	}

	public function testDuplicateNamesIgnoreCaseAndSurroundingSpaces(): void
	{
		$post = $this->post(2);
		$post['navn'] = '  DESTINATION ';
		self::assertSame('rolenameexists', perm_edit_role($post, 1)['error']);
		self::assertSame('Team', $this->db->query('select navn from roles where id=2')->fetchColumn());
	}

	public function testResetUsesRevisionCheckAndPreservesMetadata(): void
	{
		$this->db->exec("update roles set role_key='bogholder', is_built_in=true where id=2");
		$post = $this->post(2);
		self::assertSame('', perm_edit_role($post, 1, true)['error']);
		self::assertSame(perm_normalize_levels(permission_default_roles()['bogholder']['levels']), perm_levels_from_role(2));
		self::assertSame('Team', $this->db->query('select navn from roles where id=2')->fetchColumn());
		self::assertSame('rolestale', perm_edit_role($post, 1, true)['error']);
	}

	public function testFailureAfterTransferRollsBackUsersRoleAndAuditTogether(): void
	{
		$post = $this->post(2);
		$legacy = $this->db->query('select rettigheder from brugere where id=2')->fetchColumn();
		$GLOBALS['role_test_fail'] = 'delete from roles';
		try {
			perm_delete_role(2, 3, 1, $post['role_revision']);
			self::fail('The injected failure must abort deletion');
		} catch (RuntimeException $error) {
			self::assertSame('Injected write failure', $error->getMessage());
		}
		self::assertSame(2, (int) $this->db->query('select role_id from brugere where id=2')->fetchColumn());
		self::assertSame($legacy, $this->db->query('select rettigheder from brugere where id=2')->fetchColumn());
		self::assertSame(1, (int) $this->db->query('select count(*) from roles where id=2')->fetchColumn());
		self::assertSame(0, (int) $this->db->query('select count(*) from audit_log')->fetchColumn());
	}

	public function testAssignmentRejectsMissingTargetAndLastAdministrator(): void
	{
		self::assertSame('norole', user_set_role(2, 99999, 1));
		$this->db->exec('update brugere set role_id=3 where id=3');
		$this->db->exec('delete from role_permissions where role_id=3');
		$this->seedLevels(3, permission_default_roles()['administrator']['levels']);
		$GLOBALS['bruger_id'] = 3;
		self::assertSame('lastadmin', user_set_role(1, 3, 3));
		self::assertSame(1, (int) $this->db->query('select role_id from brugere where id=1')->fetchColumn());
	}

	public function testDraftCopyIsCreatedOnlyBySaveAndClampsReadOnlyCapabilities(): void
	{
		$levels = perm_levels_from_role(2);
		$levels['dashboard.view'] = 'write';
		$post = array('id' => 0, 'role_source' => 2, 'navn' => 'Copy', 'beskrivelse' => 'Draft', 'level' => $levels);
		$result = perm_edit_role($post, 1);
		self::assertSame('', $result['error']);
		self::assertGreaterThan(3, $result['id']);
		self::assertSame('read', perm_levels_from_role($result['id'])['dashboard.view']);
		self::assertSame('write', perm_levels_from_role($result['id'])['finans.kassekladde']);
		self::assertStringContainsString('source role 2', $this->db->query('select detaljer from audit_log')->fetchColumn());
	}

	public function testCreatingUserSynchronizesAssignedRoleAndRecordsCapabilities(): void
	{
		$id = user_create(array('brugernavn' => 'new-member', 'role_id' => 2, 'kode' => ''));
		self::assertGreaterThan(3, $id);
		self::assertSame(perm_legacy_string(perm_levels_from_role(2)), $this->db->query('select rettigheder from brugere where id=' . $id)->fetchColumn());
		self::assertStringContainsString('finans.kassekladde: none -> write', $this->db->query("select detaljer from audit_log where handling='user.created'")->fetchColumn());
	}

	public function testLaterAccountSaveFailureAlsoRollsBackNestedRoleAssignment(): void
	{
		$legacy = $this->db->query('select rettigheder from brugere where id=2')->fetchColumn();
		try {
			perm_atomic(function () {
				self::assertSame('', user_set_role(2, 3, 1));
				perm_write("update brugere set email='changed@example.invalid' where id=2");
				throw new RuntimeException('Later profile/settings save failed');
			});
			self::fail('Account operation must roll back');
		} catch (RuntimeException $error) {
			self::assertSame('Later profile/settings save failed', $error->getMessage());
		}
		self::assertSame(2, (int) $this->db->query('select role_id from brugere where id=2')->fetchColumn());
		self::assertSame($legacy, $this->db->query('select rettigheder from brugere where id=2')->fetchColumn());
		self::assertNull($this->db->query('select email from brugere where id=2')->fetchColumn());
		self::assertSame(0, (int) $this->db->query('select count(*) from audit_log')->fetchColumn());
	}

	public function testLockSerializesConcurrentRoleWriters(): void
	{
		// pdo() opens a new, separate exception-reporting connection to the same database.
		$other = $this->database->pdo();
		$other->exec('set search_path to ' . $this->schema);
		$other->exec("set lock_timeout = '100ms'");
		perm_atomic(function () use ($other) {
			perm_lock_roles();
			try {
				$other->exec("update roles set navn='Concurrent' where id=2");
				self::fail('Concurrent writer must wait for the role transaction');
			} catch (PDOException $error) {
				self::assertSame('55P03', $error->getCode());
			}
		});
		$other->exec("update roles set navn='Concurrent' where id=2");
		self::assertSame('Concurrent', $this->db->query('select navn from roles where id=2')->fetchColumn());
	}
}
