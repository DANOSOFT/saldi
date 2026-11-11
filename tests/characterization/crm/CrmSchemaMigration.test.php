<?php
// 20261110 CDX/ASR Cover CRM first/repeated upgrades, legacy history preservation, incomplete schemas and migration locks.
// 20261111 CDX/ASR Cover isolated CRM notifications, per-recipient uniqueness and index repair.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CrmSchemaMigration extends TestCase
{
    private ?CrmSchemaFixture $fixture = null;
    private string $dsn;

    protected function setUp(): void
    {
        $this->dsn = (string) getenv('SALDI_CHAR_PG_DSN');
        if ($this->dsn === '') {
            self::markTestSkipped('Set SALDI_CHAR_PG_DSN to the disposable PostgreSQL fixture database.');
        }
        self::assertTrue(extension_loaded('pgsql'));
        require_once __DIR__ . '/../support/CrmSchemaFixture.php';
        $this->fixture = new CrmSchemaFixture($this->dsn);
    }

    protected function tearDown(): void
    {
        if ($this->fixture !== null) {
            $this->fixture->close();
        }
    }

    public function testFirstUpgradePreservesLatestFollowupAndDatelessNotes(): void
    {
        $this->fixture->query("insert into historik (konto_id, notat, kontaktes) values
            (10, 'older dated follow-up', '2026-11-01'),
            (10, 'latest dated follow-up', '2026-11-05'),
            (10, 'later note without date', null),
            (20, 'other customer', '2026-11-03')");
        self::assertFalse(crm_schema_ready());
        self::assertTrue(crm_ensure_schema('postgresql'));
        self::assertTrue(crm_schema_ready());
        self::assertSame(['t', null, null, null], array_column($this->fixture->rows('select completed from historik order by id'), 'completed'));
        self::assertSame(['older dated follow-up', 'latest dated follow-up', 'later note without date', 'other customer'], array_column($this->fixture->rows('select notat from historik order by id'), 'notat'));
        self::assertSame('1', $this->fixture->rows("select var_value from settings where var_name = 'crm_legacy_followups' and var_grp = 'crm'")[0]['var_value']);
        self::assertSame(4, count($this->fixture->rows("select indexname from pg_indexes where schemaname = current_schema() and indexname in ('historik_konto_idx', 'crm_deals_konto_idx', 'crm_participants_ref_idx', 'crm_participants_ansat_idx')")));
        // The newly installed tables support the relationships written by the actual CRM.
        $this->fixture->query("insert into crm_deals (konto_id, title) values (10, 'First case')");
        $this->fixture->query("insert into crm_meetings (historik_id, start_time) values (2, '10:00')");
        $this->fixture->query("insert into crm_participants (kind, ref_id, ansat_id) values ('h', 2, 11)");
        $this->fixture->query("insert into crm_ical (bruger_id, token) values (1, 'test-token')");
        $this->fixture->query("insert into crm_notifications (event_key, bruger_id, title) values ('meeting:2', 11, 'First invitation')");
        self::assertSame('open', $this->fixture->rows('select state from crm_deals')[0]['state']);
        self::assertSame('60', $this->fixture->rows('select duration from crm_meetings')[0]['duration']);
        self::assertSame('f', $this->fixture->rows('select seen from crm_notifications')[0]['seen']);
        self::assertSame(null, $this->fixture->rows('select expires from crm_notifications')[0]['expires']);
    }

    public function testSecondUpgradeDoesNotRewriteHistoryOrDuplicateSettings(): void
    {
        self::assertTrue(crm_ensure_schema('postgresql'));
        $this->fixture->query("insert into historik (konto_id, kontaktes, completed) values
            (10, '2026-11-01', null), (10, '2026-11-02', false), (10, '2026-11-03', true)");
        $this->fixture->writes = [];
        self::assertTrue(crm_ensure_schema('postgresql'));
        self::assertSame([], $this->fixture->writes);
        self::assertSame([null, 'f', 't'], array_column($this->fixture->rows('select completed from historik order by id'), 'completed'));
        self::assertCount(1, $this->fixture->rows("select id from settings where var_name = 'crm_legacy_followups' and var_grp = 'crm'"));
    }

    public function testIncompleteUpgradeRepairsColumnsAndIndexesBeforeBecomingReady(): void
    {
        self::assertTrue(crm_ensure_schema('postgresql'));
        $this->fixture->query('alter table crm_deals drop column ordre_id');
        $this->fixture->query('alter table historik drop column completed_at');
        $this->fixture->query('drop table crm_ical');
        $this->fixture->query('drop table crm_notifications');
        $this->fixture->query('drop index crm_participants_ref_idx');
        self::assertFalse(crm_schema_ready());
        self::assertTrue(crm_ensure_schema('postgresql'));
        self::assertTrue(crm_schema_ready());
        self::assertCount(1, $this->fixture->rows("select indexname from pg_indexes where schemaname = current_schema() and indexname = 'crm_participants_ref_idx'"));
    }

    public function testNotificationsDeduplicatePerRecipientAndRepairMissingIndexes(): void
    {
        $this->fixture->query('create temporary table notifications (id integer, body text)');
        $this->fixture->query("insert into notifications values (77, 'Existing system notification')");
        self::assertTrue(crm_ensure_schema('postgresql'));
        $this->fixture->query("insert into crm_notifications (event_key, bruger_id, title) values
            ('meeting:2', 11, 'First invitation'), ('meeting:2', 12, 'Second recipient')");
        $this->fixture->query("insert into crm_notifications (event_key, bruger_id, title) values
            ('meeting:2', 11, 'Duplicate event') on conflict (event_key, bruger_id) do nothing");
        self::assertCount(2, $this->fixture->rows('select id from crm_notifications'));
        self::assertSame([['id' => '77', 'body' => 'Existing system notification']], $this->fixture->rows('select * from notifications'));
        $this->fixture->query('drop index crm_notifications_user_idx');
        $this->fixture->query('drop index crm_notifications_event_user_idx');
        self::assertTrue(crm_ensure_schema('postgresql'));
        self::assertCount(2, $this->fixture->rows("select indexname from pg_indexes where schemaname = current_schema() and indexname in ('crm_notifications_user_idx', 'crm_notifications_event_user_idx')"));
        $this->fixture->query("insert into crm_notifications (event_key, bruger_id) values ('meeting:2', 11) on conflict (event_key, bruger_id) do nothing");
        self::assertCount(2, $this->fixture->rows('select id from crm_notifications'));
        self::assertSame([['id' => '77', 'body' => 'Existing system notification']], $this->fixture->rows('select * from notifications'));
    }

    public function testIndexInAnotherSessionSchemaDoesNotSuppressTheTenantIndex(): void
    {
        $other = pg_connect($this->dsn, PGSQL_CONNECT_FORCE_NEW);
        self::assertNotFalse($other);
        try {
            self::assertNotFalse(pg_query($other, 'set search_path to pg_temp'));
            self::assertNotFalse(pg_query($other, 'create temporary table historik (konto_id integer)'));
            self::assertNotFalse(pg_query($other, 'create index historik_konto_idx on historik (konto_id)'));
            self::assertTrue(crm_ensure_schema('postgresql'));
            self::assertCount(1, $this->fixture->rows("select indexname from pg_indexes where schemaname = current_schema() and indexname = 'historik_konto_idx'"));
        } finally {
            pg_close($other);
        }
    }

    public function testInstallerTransactionRemainsOwnedByTheCaller(): void
    {
        $this->fixture->query('begin');
        self::assertTrue(crm_ensure_schema('postgresql'));
        self::assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status($this->fixture->connection));
        $this->fixture->query('rollback');
        self::assertFalse(crm_schema_ready());
        self::assertSame([], $this->fixture->rows('select * from settings'));
        self::assertTrue(crm_ensure_schema('postgresql'));
        self::assertTrue(crm_schema_ready());
    }

    public function testFailedLegacyWriteDoesNotMarkMigrationCompleteAndCanRetry(): void
    {
        $this->fixture->query("insert into historik (konto_id, kontaktes) values (10, '2026-11-01'), (10, '2026-11-02')");
        $this->fixture->failResultOn = 'update historik h set completed';
        $GLOBALS['webservice'] = false;
        try {
            crm_ensure_schema('postgresql');
            self::fail('Expected a returned write failure to abort the migration.');
        } catch (RuntimeException $error) {
            self::assertSame('CRM schema migration failed.', $error->getMessage());
        }
        self::assertFalse($GLOBALS['webservice']);
        self::assertSame([], $this->fixture->rows('select * from settings'));
        self::assertSame([null, null], array_column($this->fixture->rows('select completed from historik order by id'), 'completed'));
        $this->fixture->failResultOn = null;
        self::assertTrue(crm_ensure_schema('postgresql'));
        self::assertSame(['t', null], array_column($this->fixture->rows('select completed from historik order by id'), 'completed'));
        self::assertCount(1, $this->fixture->rows("select id from settings where var_name = 'crm_legacy_followups' and var_value = '1'"));
    }

    public function testFailedUpgradeReleasesLockAndCanResume(): void
    {
        $this->fixture->failOn = 'CREATE TABLE crm_deals';
        try {
            crm_ensure_schema('postgresql');
            self::fail('Expected injected migration failure.');
        } catch (RuntimeException $error) {
            self::assertSame('Injected CRM migration failure.', $error->getMessage());
        }
        self::assertFalse(crm_schema_ready());
        self::assertSame([], $this->fixture->rows('select * from settings'));
        $schema = $this->fixture->rows('select current_schema() as schema')[0]['schema'];
        $other = pg_connect($this->dsn, PGSQL_CONNECT_FORCE_NEW);
        self::assertNotFalse($other);
        try {
            $result = pg_query_params($other, "select pg_try_advisory_lock(hashtext('saldi_crm_schema'), hashtext($1)) as locked", [$schema]);
            self::assertSame('t', pg_fetch_assoc($result)['locked']);
        } finally {
            pg_close($other);
        }
        $this->fixture->failOn = null;
        self::assertTrue(crm_ensure_schema('postgresql'));
        self::assertTrue(crm_schema_ready());
    }
}
