<?php
// 20261125 CDX/ASR Verify idempotent catalogue migration, database edits, allocation expiry and master isolation on PostgreSQL.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EntitlementDatabaseTest extends TestCase
{
	public function testMigrationAndRuntimeUseOnlyTheMasterAndPreserveOperatorChanges(): void
	{
		require_once __DIR__ . '/../characterization/support/database/TestDatabase.php';
		if (($reason = TestDatabase::unavailableReason()) !== null) self::markTestSkipped($reason);
		$db = TestDatabase::sqlFixture(static::class);
		$master = $db->pdo();
		$tenant = $db->pdo();
		$prefix = 'ent_' . bin2hex(random_bytes(6));
		$master->exec("CREATE SCHEMA {$prefix}_master");
		$master->exec("CREATE SCHEMA {$prefix}_tenant");
		try {
			$master->exec("SET search_path = {$prefix}_master");
			$tenant->exec("SET search_path = {$prefix}_tenant");
			$GLOBALS['ent_test_master'] = $master;
			$GLOBALS['ent_test_tenant'] = $tenant;
			$GLOBALS['db_type'] = 'postgres';
			$GLOBALS['sqdb'] = $prefix . '_master';
			$GLOBALS['db'] = $prefix . '_tenant';
			$GLOBALS['db_modify_fejl'] = false;
			require_once __DIR__ . '/support/entitlementDatabase.php';
			require_once __DIR__ . '/../../includes/structure/operatorPanelSchema.php';
			require_once __DIR__ . '/../../includes/structure/entitlementSchema.php';
			require_once __DIR__ . '/../../includes/entitlements.php';
			$master->exec('CREATE TABLE settings (id serial PRIMARY KEY, var_grp text, var_name text, var_value text, var_description text)');
			$master->exec('CREATE TABLE regnskab (id serial PRIMARY KEY, pakke text, db text)');
			$master->exec('CREATE TABLE online (db text, brugernavn text, logtime varchar(30), revisor boolean)');
			$master->exec("INSERT INTO regnskab (pakke, db) VALUES ('finans', 'legacy_signup'), (NULL, 'legacy_manual')");
			self::assertTrue(operator_panel_schema());
			self::assertTrue(entitlement_schema());
			self::assertTrue(ent_runtime_ready());
			self::assertGreaterThan(0, (int) $master->query('SELECT count(*) FROM posting_tiers')->fetchColumn());
			$now = time();
			$expired = $now - 14401;
			$master->exec("INSERT INTO online VALUES ('ledger', 'Anne', $now, NULL), ('ledger', 'anne', $now, false), ('ledger', 'Bo', $now, false), ('ledger', 'Auditor', $now, true), ('ledger', 'Old', $expired, false), ('other', 'Else', $now, false)");
			self::assertSame(2, ent_concurrent_users('ledger'));
			self::assertSame(1, ent_concurrent_users('ledger', 'ANNE'));
			self::assertSame('observe', $master->query('SELECT enforcement FROM regnskab_entitlements WHERE regnskab_id = 1')->fetchColumn());
			self::assertSame(0, (int) $master->query('SELECT count(*) FROM regnskab_entitlements WHERE regnskab_id = 2')->fetchColumn());
			self::assertSame(0, (int) $tenant->query('SELECT count(*) FROM information_schema.tables WHERE table_schema = current_schema()')->fetchColumn());
			ent_load(1);
			self::assertFalse(ent_allows('finans.budget'));
			self::assertTrue(ent_allows('moms.eu_reverse'));
			self::assertSame(1, ent_limit('max_users'));
			self::assertSame(0, ent_limit('max_items'));
			self::assertNull(ent_login_lock(), 'Observe mode does not serialize or refuse logins.');
			$master->exec("UPDATE regnskab_entitlements SET enforcement = 'enforce' WHERE regnskab_id = 1");
			ent_resolve_flush(1);
			$lock = ent_login_lock();
			try {
				self::assertSame('entitlement_login_1', $lock);
				self::assertFalse($tenant->query("SELECT pg_try_advisory_lock(hashtext('entitlement_login_1'))")->fetchColumn(), 'A second admission cannot pass while the first holds the count/insert lock.');
			} finally {
				db_unlock($lock, true);
			}
			ent_write_plan(2, 'business', date('Y-m-d'), 'manual');
			ent_load(2);
			self::assertSame(12, ent_limit('max_users'));
			self::assertSame(1, ent_limit('max_webshops'));
			$master->exec("INSERT INTO regnskab_addons (regnskab_id, addon_key, expires_at) VALUES (2, 'pos_location', current_date - 1)");
			ent_resolve_flush(2);
			self::assertFalse(ent_allows('pos.kasse'), 'expired add-on does not grant access');
			$master->exec("UPDATE regnskab_addons SET expires_at = current_date WHERE regnskab_id = 2");
			ent_resolve_flush(2);
			self::assertTrue(ent_allows('pos.kasse'), 'last day of the allocation is included');
			$master->exec("UPDATE plans SET max_users = 15 WHERE plan_key = 'business'");
			$master->exec("UPDATE plan_features SET level = 'none' WHERE feature_key = 'finans.budget'");
			self::assertTrue(entitlement_schema());
			unset($GLOBALS['ent_catalog_cache']);
			ent_resolve_flush(2);
			self::assertSame(15, ent_limit('max_users'), 'rerunning migration must preserve operator edits');
			self::assertFalse(ent_allows('finans.budget'));
			ent_write_plan(2, 'erp', date('Y-m-d'), 'manual');
			self::assertNull(ent_limit('max_users'));
			self::assertNull(ent_limit('max_webshops'), 'SQL NULL survives resolution, not cast to zero');
			ent_load(3);
			self::assertTrue(ent_allows('pos.kasse'));
			self::assertSame(2, (int) $master->query('SELECT count(*) FROM regnskab_entitlements')->fetchColumn());
			$master->beginTransaction();
			ent_write_plan(4, 'professionel', date('Y-m-d'), 'signup');
			$master->rollBack();
			self::assertSame(0, (int) $master->query('SELECT count(*) FROM regnskab_entitlements WHERE regnskab_id = 4')->fetchColumn(), 'Package assignment shares the signup reservation transaction.');
			$master->exec("INSERT INTO regnskab_plan_changes (regnskab_id, plan_key, effective_from, created_by) VALUES (2, 'professionel', current_date, 'test')");
			ent_resolve_flush(2);
			ent_load(2);
			self::assertSame(6, ent_limit('max_users'));
			self::assertSame(1, (int) $master->query("SELECT count(*) FROM partner_log WHERE action = 'entitlement.plan_applied'")->fetchColumn(), 'Scheduled changes are audited on the master even from a tenant request.');
			// Reproduce an interrupted policy rollout: failure must roll back data and leave readiness absent.
			$master->exec("DELETE FROM settings WHERE var_name = 'entitlement_policy'");
			$master->exec("DELETE FROM plan_features WHERE feature_key = 'sales.quick_invoice_required'");
			$master->exec("ALTER TABLE plan_features ADD CONSTRAINT reject_test_feature CHECK (feature_key <> 'sales.quick_invoice_required')");
			$master->exec("UPDATE plans SET max_users = 77 WHERE plan_key = 'finans'");
			try {
				entitlement_schema();
				self::fail('The test constraint must reject the seed.');
			} catch (PDOException $expected) {
				self::assertSame('23514', $expected->getCode());
			}
			self::assertSame(77, (int) $master->query("SELECT max_users FROM plans WHERE plan_key = 'finans'")->fetchColumn());
			self::assertSame('enforce', $master->query('SELECT enforcement FROM regnskab_entitlements WHERE regnskab_id = 1')->fetchColumn());
			self::assertSame(0, (int) $master->query("SELECT count(*) FROM settings WHERE var_name = 'entitlement_policy'")->fetchColumn());
		} finally {
			$master->exec("DROP SCHEMA {$prefix}_master CASCADE");
			$master->exec("DROP SCHEMA {$prefix}_tenant CASCADE");
		}
	}
}
