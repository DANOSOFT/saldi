<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileT3FoundationTest.php --- ver 5.0.0 --- 2026.10.05
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.
// 20261001 CDX/LH Regress recycled web journal IDs and mobile journal identity migration.
// 20261006 CL/LH Derive the latest tenant marker so later tiers can append steps.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../includes/mobileUpdates.php';
require_once __DIR__ . '/../../includes/mobileRegistryUpdates.php';
require_once __DIR__ . '/../../includes/db_query.php';
require_once __DIR__ . '/../../includes/finans/kladdeJournal.php';
require_once __DIR__ . '/../../includes/docsIncludes/poolReviewVersion.php';
require_once __DIR__ . '/../../includes/docsIncludes/poolVendorMatcher.php';
require_once __DIR__ . '/../../restapi/services/MobileEventOutbox.php';
require_once __DIR__ . '/../../restapi/models/attachment/DurableAttachmentModel.php';

final class MobileT3FoundationTest extends TestCase
{
    private $tenant;
    private array $globals;
    private array $server;
    private string $cwd;
    private static ?string $registryDb = null;

    public static function setUpBeforeClass(): void
    {
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        self::$registryDb = 'm3_registry_' . bin2hex(random_bytes(4));
        RestApiEnv::rows($master, 'CREATE DATABASE ' . self::$registryDb . ' TEMPLATE template0');
        pg_close($master);
        $registry = RestApiEnv::connect(self::$registryDb);
        RestApiEnv::rows($registry, 'CREATE TABLE settings(id serial PRIMARY KEY,var_grp text,var_name text,var_value text,user_id integer)');
        pg_close($registry);
    }

    public static function tearDownAfterClass(): void
    {
        RestApiEnv::teardownTenant();
        if (self::$registryDb !== null) {
            $master = RestApiEnv::connect(RestApiEnv::masterDb());
            RestApiEnv::rows($master, 'DROP DATABASE ' . self::$registryDb);
            pg_close($master);
        }
    }

    protected function setUp(): void
    {
        foreach (['connection','db_type','db','sqdb','bruger_id','sprog_id','db_transaktion_depth','db_modify_fejl'] as $key) {
            $this->globals[$key] = [array_key_exists($key, $GLOBALS), ifset($GLOBALS, $key)];
        }
        $this->server = $_SERVER;
        $_SERVER['REQUEST_URI'] = '/saldi/finans/kassekladde.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $this->cwd = getcwd();
        chdir(dirname(__DIR__, 2) . '/finans');
        $this->tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $GLOBALS['connection'] = $this->tenant;
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['db'] = RestApiEnv::testDb();
        $GLOBALS['sqdb'] = RestApiEnv::masterDb();
        $GLOBALS['bruger_id'] = 0;
        $GLOBALS['sprog_id'] = 1;
        mobileTenantUpdates($this->tenant);
    }

    protected function tearDown(): void
    {
        if (pg_transaction_status($this->tenant) !== PGSQL_TRANSACTION_IDLE) {
            RestApiEnv::rows($this->tenant, 'ROLLBACK');
        }
        pg_close($this->tenant);
        foreach ($this->globals as $key => [$exists, $value]) {
            if ($exists) {
                $GLOBALS[$key] = $value;
            } else {
                unset($GLOBALS[$key]);
            }
        }
        $_SERVER = $this->server;
        chdir($this->cwd);
    }

    private function rows(string $sql, array $params = []): array
    {
        return RestApiEnv::rows($this->tenant, $sql, $params);
    }

    private function pool(string $status = 'ubehandlet'): array
    {
        return $this->rows('INSERT INTO pool_files(filename,status,uploaded_by) VALUES ($1,$2,$3) RETURNING *',
            [bin2hex(random_bytes(8)) . '.pdf', $status, 'foundation/account/1'])[0];
    }

    private function scans($conn): int
    {
        return (int)RestApiEnv::rows($conn, "SELECT seq_scan+idx_scan AS n FROM pg_stat_xact_user_tables WHERE relname='settings'")[0]['n'];
    }

    public function test_fresh_steps_replay_and_current_runners_cost_one_select(): void
    {
        foreach (['pool_file_audit','bank_line_imports','bank_lines','bank_line_journal_links',
            'classification_suggestions','classification_feedback','mobile_events','notification_preferences',
            'mobile_scanner_state','mobile_low_stock_state','mobile_journal_lines','pool_file_moves'] as $table) {
            $this->assertSame($table, $this->rows('SELECT to_regclass($1) AS name', [$table])[0]['name']);
        }
        $this->rows("UPDATE settings SET var_value='m2_12_app_login_legacy_schema' WHERE var_name='mobile_tenant_step' AND var_grp='mobile'");
        mobileTenantUpdates($this->tenant);
        $this->rows('BEGIN READ ONLY');
        $before = $this->scans($this->tenant);
        mobileTenantUpdates($this->tenant);
        $this->assertSame($before + 1, $this->scans($this->tenant));
        $this->rows('ROLLBACK');
        // Re-run from the installed baseline, retaining previously created data and triggers.
        $this->rows("DELETE FROM settings WHERE var_name='mobile_tenant_step' AND var_grp='mobile'");
        mobileTenantUpdates($this->tenant);
        $this->assertSame(array_key_last(mobileTenantUpdateSteps()), $this->rows("SELECT var_value FROM settings WHERE var_name='mobile_tenant_step' AND var_grp='mobile'")[0]['var_value']);
        $registry = RestApiEnv::connect(self::$registryDb);
        try {
            mobileRegistryUpdates($registry);
            $this->assertSame('mobile_summary_cache', RestApiEnv::rows($registry,"SELECT to_regclass('mobile_summary_cache') AS name")[0]['name']);
            RestApiEnv::rows($registry, 'BEGIN READ ONLY');
            $before = $this->scans($registry);
            mobileRegistryUpdates($registry);
            $this->assertSame($before + 1, $this->scans($registry));
            RestApiEnv::rows($registry, 'ROLLBACK');
            RestApiEnv::rows($registry,"UPDATE settings SET var_value='r2_02_cvr_cache' WHERE var_name='mobile_registry_step' AND var_grp='mobile'");
            mobileRegistryUpdates($registry);
            $this->assertSame('r3_01_summary_cache',RestApiEnv::rows($registry,"SELECT var_value FROM settings WHERE var_name='mobile_registry_step'")[0]['var_value']);
        } finally {
            pg_close($registry);
        }
    }

    public function test_vat_step_repairs_the_admin_opret_baseline_and_replay_is_a_noop(): void
    {
        $this->rows('BEGIN');
        foreach (['kassekladde','tmpkassekl'] as $table) {
            $this->rows("ALTER TABLE $table DROP COLUMN IF EXISTS debetvat, DROP COLUMN IF EXISTS kreditvat");
        }
        $steps = mobileTenantUpdateSteps();
        $schema = new MobileSchema($this->tenant);
        $steps['m3_07_kassekladde_vat']($schema);
        $this->assertCount(4, $this->rows("SELECT column_name FROM information_schema.columns WHERE table_schema='public'
            AND table_name IN ('kassekladde','tmpkassekl') AND column_name IN ('debetvat','kreditvat')"));
        $this->rows("INSERT INTO tmpkassekl(id,debetvat,kreditvat) VALUES (987,'K1','S1')");
        $steps['m3_07_kassekladde_vat']($schema);
        $this->assertSame(['debetvat'=>'K1','kreditvat'=>'S1'], $this->rows('SELECT debetvat,kreditvat FROM tmpkassekl WHERE id=987')[0]);
    }

    /** Execute deliberate rejected SQL without suppressing PHP warnings; inspect PostgreSQL SQLSTATE. */
    private function rejected(string $sql, string $state): void
    {
        $this->rows('SAVEPOINT expected_rejection');
        $this->assertTrue(pg_send_query($this->tenant, $sql));
        $result = pg_get_result($this->tenant);
        $this->assertSame(PGSQL_FATAL_ERROR, pg_result_status($result));
        $this->assertSame($state, pg_result_error_field($result, PGSQL_DIAG_SQLSTATE));
        $this->rows('ROLLBACK TO SAVEPOINT expected_rejection');
    }

    private function chainOk(int $id): bool
    {
        $rows = $this->rows("SELECT *, encode(sha256(convert_to(COALESCE(prev_sha256,'') ||
            jsonb_build_array(id,pool_file_id,event,actor,changes::jsonb,reason_code,note,request_id,
                to_char(created_at AT TIME ZONE 'UTC','YYYY-MM-DD\"T\"HH24:MI:SS.US\"Z\"'))::text,'UTF8')),'hex') AS expected
            FROM pool_file_audit WHERE pool_file_id=$1 ORDER BY id", [$id]);
        $previous = null;
        foreach ($rows as $row) {
            if ($row['prev_sha256'] !== $previous || !hash_equals($row['expected'], $row['row_sha256'])) {
                return false;
            }
            $previous = $row['row_sha256'];
        }
        return true;
    }

    public function test_audit_chain_lifecycle_actor_and_forced_edit_detection(): void
    {
        $this->rows('BEGIN');
        $row = $this->pool('staged');
        $id = (int)$row['id'];
        $this->assertSame([], $this->rows('SELECT * FROM pool_file_audit WHERE pool_file_id=$1', [$id]));
        $this->rows("UPDATE pool_files SET status='ubehandlet' WHERE id=$1", [$id]);
        $this->assertSame('foundation/account/1', $this->rows('SELECT actor FROM pool_file_audit WHERE pool_file_id=$1',[$id])[0]['actor']);
        $this->rows("SELECT set_config('saldi.actor','foundation/account/2',true), set_config('saldi.request_id',$1,true)", ['aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa']);
        $this->rows("UPDATE pool_files SET amount='250.00', description='changed' WHERE id=$1",[$id]);
        $this->rows("UPDATE pool_files SET status='attached' WHERE id=$1",[$id]);
        $this->rows("UPDATE pool_files SET status='ubehandlet' WHERE id=$1",[$id]);
        $this->rows("UPDATE pool_files SET status='deleted' WHERE id=$1",[$id]);
        $audit = $this->rows('SELECT * FROM pool_file_audit WHERE pool_file_id=$1 ORDER BY id',[$id]);
        $this->assertSame(['uploaded','metadata_changed','attached','returned','deleted'],array_column($audit,'event'));
        $this->assertSame('foundation/account/2',$audit[1]['actor']);
        $this->assertSame('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',$audit[1]['request_id']);
        $this->assertEquals(['from'=>null,'to'=>'250.00'],json_decode($audit[1]['changes'],true)['amount']);
        $this->assertTrue($this->chainOk($id));
        $this->rows("UPDATE pool_files SET updated=now() WHERE id=$1",[$id]);
        $this->assertCount(5,$this->rows('SELECT * FROM pool_file_audit WHERE pool_file_id=$1',[$id]));
        $this->rows('ALTER TABLE pool_file_audit DISABLE TRIGGER mobile_audit_immutable');
        $this->rows("UPDATE pool_file_audit SET actor='forced edit' WHERE id=$1",[$audit[1]['id']]);
        $this->rows('ALTER TABLE pool_file_audit ENABLE TRIGGER mobile_audit_immutable');
        $this->assertFalse($this->chainOk($id));
    }

    public function test_audit_and_both_classification_tables_reject_updates_deletes_and_truncates(): void
    {
        $this->rows('BEGIN');
        $row=$this->pool();
        $this->rows("INSERT INTO classification_suggestions(pool_file_id,inputs_sha256,basis,engine_version,requested_by)
            VALUES ($1,$2,'none','det-1','test')",[$row['id'],hash('sha256','inputs')]);
        $this->rows("INSERT INTO classification_feedback(pool_file_id,role,outcome,kontonr,actor) VALUES ($1,'reviewer','none',1,'test')",[$row['id']]);
        foreach (['pool_file_audit'=>'actor','classification_suggestions'=>'requested_by','classification_feedback'=>'actor'] as $table=>$field) {
            $this->rejected("UPDATE $table SET $field='edited'",'55000');
            $this->rejected("DELETE FROM $table",'55000');
            $this->rejected("TRUNCATE $table CASCADE",'55000');
        }
        $this->assertTrue($this->chainOk((int)$row['id']));
    }

    public function test_three_web_metadata_writer_shapes_and_vendor_only_rematch_are_audited(): void
    {
        $this->rows('BEGIN');
        // Installed old pool tables can lack optional vendor columns; seed the web matcher fixture.
        foreach (['vendor_name'=>'text','vendor_cvr'=>'varchar(20)','vendor_iban'=>'varchar(40)',
            'vendor_konto_id'=>'integer','vendor_match'=>'varchar(10)','vendor_score'=>'numeric(4,3)'] as $field=>$definition) {
            $this->rows("ALTER TABLE pool_files ADD COLUMN IF NOT EXISTS $field $definition");
        }
        $row=$this->pool(); $id=(int)$row['id'];
        $year=(int)$this->rows("SELECT max(kodenr) AS year FROM grupper WHERE art='RA'")[0]['year'];
        // Commit fixture setup because the real legacy save owns its BEGIN/COMMIT.
        $this->rows('COMMIT');
        poolMetadataSave($row['filename'], ['description'=>'service edit'], true, poolMetadataVersion($row), $year);
        // Direct rename writer and direct metadata writer from docPool.php use these assignments.
        $this->rows("UPDATE pool_files SET filename=$1,subject='renamed',manually_edited=true,updated=CURRENT_TIMESTAMP,
            amount='1.234,56',norm_amount=1234.56,file_date='2026-09-14',invoice_number='42',description='rename edit' WHERE id=$2",
            [bin2hex(random_bytes(8)).'.pdf',$id]);
        $this->rows("UPDATE pool_files SET updated=CURRENT_TIMESTAMP,manually_edited=true,subject='direct edit',
            account='',amount='250.00',file_date='2026-09-15',invoice_number='43',description='direct edit' WHERE id=$1",[$id]);
        $old=$this->rows('SELECT * FROM pool_files WHERE id=$1',[$id])[0];
        $sql=poolVendorUpdateSql(['name'=>'Supplier','cvr'=>'12345678','iban'=>'DK0000000000000000','kontoId'=>17,'match'=>'cvr','score'=>1]);
        $this->rows("UPDATE pool_files SET $sql WHERE id=$id");
        $new=$this->rows('SELECT * FROM pool_files WHERE id=$1',[$id])[0];
        $this->assertSame($old['updated'],$new['updated']);
        $this->assertSame(poolMetadataVersion($old),poolMetadataVersion($new));
        $this->assertNotSame(pool_review_version($old),pool_review_version($new));
        $audit=$this->rows("SELECT changes FROM pool_file_audit WHERE pool_file_id=$1 AND event='metadata_changed' ORDER BY id",[$id]);
        $this->assertCount(4,$audit);
        $this->assertArrayHasKey('vendor_konto_id',json_decode($audit[3]['changes'],true));
        $this->assertTrue($this->chainOk($id));
    }

    public function test_emit_deduplicates_and_retains_caller_transaction_and_notification_cleanup(): void
    {
        $key='event:'.bin2hex(random_bytes(8));
        $this->rows('BEGIN');
        $this->assertTrue(MobileEventOutbox::emit($this->tenant,'invoice_paid',$key,'openpost',42,['amount'=>'250.00'],null));
        $this->assertFalse(MobileEventOutbox::emit($this->tenant,'invoice_paid',$key,'openpost',42,['amount'=>'250.00'],null));
        $this->assertSame(PGSQL_TRANSACTION_INTRANS,pg_transaction_status($this->tenant));
        $notification=$this->rows("INSERT INTO notifications(type,title,message,created_by) VALUES ('invoice_paid','test','test','test') RETURNING id")[0]['id'];
        $this->rows('UPDATE mobile_events SET notification_id=$1 WHERE dedupe_key=$2',[$notification,$key]);
        $this->rows('DELETE FROM notifications WHERE id=$1',[$notification]);
        $this->assertNull($this->rows('SELECT notification_id FROM mobile_events WHERE dedupe_key=$1',[$key])[0]['notification_id']);
        $this->rows('ROLLBACK');
        $this->assertSame([],$this->rows('SELECT id FROM mobile_events WHERE dedupe_key=$1',[$key]));
    }

    public function test_journal_transaction_ownership_and_same_user_lease_rollover(): void
    {
        $this->rows('BEGIN');
        foreach (['kladde_journal_create','mobile_approval_journal'] as $function) {
            try {
                $function === 'mobile_approval_journal' ? $function('test') : $function('test','test');
                $this->fail('Nested journal transaction must fail');
            } catch (LogicException $error) {
                $this->assertSame(PGSQL_TRANSACTION_INTRANS,pg_transaction_status($this->tenant));
            }
        }
        $id=kladde_journal_insert('transaction owned','test');
        $this->assertSame(PGSQL_TRANSACTION_INTRANS,pg_transaction_status($this->tenant));
        $this->rows('ROLLBACK');
        $this->assertSame([],$this->rows('SELECT id FROM kladdeliste WHERE id=$1',[$id]));
        try {
            kladde_journal_insert('invalid','test');
            $this->fail('Autocommit insertion must fail');
        } catch (LogicException $error) {
            $this->assertSame(PGSQL_TRANSACTION_IDLE,pg_transaction_status($this->tenant));
        }
        $this->rows("DELETE FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id'");
        $id=mobile_approval_journal('bookkeeper');
        $this->assertSame($id,mobile_approval_journal('bookkeeper'));
        $this->rows("UPDATE kladdeliste SET hvem='bookkeeper',tidspkt='0.1 ' || extract(epoch FROM now())::bigint::text WHERE id=$1",[$id]);
        $this->assertSame('bookkeeper',kladde_lease_holder($id)['holder']);
        $next=mobile_approval_journal('bookkeeper');
        $this->assertNotSame($id,$next);
        $this->rows("UPDATE kladdeliste SET tidspkt=extract(epoch FROM now()-interval '2 hours')::bigint::text WHERE id=$1",[$id]);
        $this->assertNull(kladde_lease_holder($id));
        $this->rows("UPDATE kladdeliste SET tidspkt=extract(epoch FROM now())::bigint::text WHERE id=$1",[$id]);
        $this->assertSame('bookkeeper',kladde_lease_holder($id)['holder']);
        $this->rows("UPDATE kladdeliste SET tidspkt='invalid' WHERE id=$1",[$id]);
        $this->assertNull(kladde_lease_holder($id));
        $this->rows("UPDATE kladdeliste SET bogfort='V' WHERE id=$1",[$next]);
        $third=mobile_approval_journal('bookkeeper');
        $this->assertNotSame($next,$third);
        $this->rows('DELETE FROM kladdeliste WHERE id=$1',[$third]);
        $replacement=mobile_approval_journal('replacement');
        $this->assertSame('replacement',$this->rows('SELECT oprettet_af FROM kladdeliste WHERE id=$1',[$replacement])[0]['oprettet_af']);
    }

    public function test_approval_journal_does_not_reuse_a_deleted_id_recycled_by_the_web(): void
    {
        $this->rows("DELETE FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id'");
        $id = mobile_approval_journal('bookkeeper');
        $this->assertSame($id, (int)$this->rows('SELECT max(id) AS id FROM kladdeliste')[0]['id']);
        $this->rows('DELETE FROM kladdeliste WHERE id=$1', [$id]);
        $web = $this->rows("INSERT INTO kladdeliste(id,kladdedate,kladdenote,bogfort,oprettet_af)
            SELECT COALESCE(max(id),0)+1,CURRENT_DATE,'Unrelated web journal','-','web' FROM kladdeliste RETURNING *")[0];
        $this->assertSame($id, (int)$web['id']);
        $this->assertNull(kladde_lease_holder($id));

        $replacement = mobile_approval_journal('bookkeeper');
        $this->assertNotSame($id, $replacement, 'A recycled web ID must never become the mobile approval journal');
        $this->assertSame('f', $web['mobile_approval_journal']);
        $this->assertSame('t', $this->rows('SELECT mobile_approval_journal FROM kladdeliste WHERE id=$1', [$replacement])[0]['mobile_approval_journal']);
        $this->assertSame($web, $this->rows('SELECT * FROM kladdeliste WHERE id=$1', [$id])[0]);
        $this->assertSame($replacement, mobile_approval_journal('bookkeeper'));
        $this->assertSame((string)$replacement, $this->rows("SELECT var_value FROM settings
            WHERE var_grp='mobile' AND var_name='approval_kladde_id' AND user_id=0")[0]['var_value']);
    }

    public function test_mobile_journal_identity_upgrade_defaults_false_and_replay_preserves_marked_rows(): void
    {
        $legacy = kladde_journal_create('Godkendte bilag fra Saldi Mobile', 'bookkeeper');
        $this->rows("DELETE FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id'");
        $this->rows("INSERT INTO settings(var_grp,var_name,var_value,user_id)
            VALUES ('mobile','approval_kladde_id',$1,0)", [(string)$legacy]);
        $this->rows('ALTER TABLE kladdeliste DROP COLUMN mobile_approval_journal');
        $this->rows("UPDATE settings SET var_value='m3_08_pool_file_moves'
            WHERE var_grp='mobile' AND var_name='mobile_tenant_step' AND user_id=0");
        mobileTenantUpdates($this->tenant);
        $this->assertSame(array_key_last(mobileTenantUpdateSteps()), $this->rows("SELECT var_value FROM settings
            WHERE var_grp='mobile' AND var_name='mobile_tenant_step' AND user_id=0")[0]['var_value']);
        $this->assertSame(['data_type'=>'boolean', 'is_nullable'=>'NO', 'column_default'=>'false'],
            $this->rows("SELECT data_type,is_nullable,column_default FROM information_schema.columns
                WHERE table_schema='public' AND table_name='kladdeliste' AND column_name='mobile_approval_journal'")[0]);
        $this->assertSame([], $this->rows('SELECT id FROM kladdeliste WHERE mobile_approval_journal=true'));
        $old = $this->rows('SELECT * FROM kladdeliste WHERE id=$1', [$legacy])[0];
        $marked = mobile_approval_journal('bookkeeper');
        $this->assertNotSame($legacy, $marked);
        $this->assertSame($old, $this->rows('SELECT * FROM kladdeliste WHERE id=$1', [$legacy])[0]);
        $generic = kladde_journal_create('Generic journal', 'bookkeeper');
        $this->assertSame('f', $this->rows('SELECT mobile_approval_journal FROM kladdeliste WHERE id=$1', [$generic])[0]['mobile_approval_journal']);
        $before = $this->rows('SELECT * FROM kladdeliste ORDER BY id');
        $steps = mobileTenantUpdateSteps();
        $steps['m3_09_mobile_journal_identity'](new MobileSchema($this->tenant));
        $this->assertSame($before, $this->rows('SELECT * FROM kladdeliste ORDER BY id'));
        $this->assertSame($marked, mobile_approval_journal('bookkeeper'));
    }

    /** @return array{process:resource,pipes:array} Child with an independent tenant connection. */
    private function child(string $action, array $extra=[]): array
    {
        $source= <<<'PHP'
$input=json_decode(stream_get_contents(STDIN),true);
$_SERVER['REQUEST_URI']='/saldi/finans/kassekladde.php';
require getcwd().'/tests/restapi/support/RestApiEnv.php';
require getcwd().'/includes/db_query.php';
require getcwd().'/includes/finans/kladdeJournal.php';
$GLOBALS['connection']=RestApiEnv::connect($input['db']);
$GLOBALS['db_type']='postgresql';
$GLOBALS['db']=$input['db'];
$GLOBALS['sqdb']=RestApiEnv::masterDb();
$GLOBALS['bruger_id']=0;
$GLOBALS['sprog_id']=1;
chdir(getcwd().'/finans');
if ($input['action']==='approval') {
    echo mobile_approval_journal('parallel');
} else {
    echo kladde_journal_create('parallel',$input['username'] ?? 'parallel');
}
PHP;
        $process=proc_open([PHP_BINARY,'-r',$source],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,dirname(__DIR__,2));
        if (!is_resource($process)) {
            throw new RuntimeException('Child start failed');
        }
        fwrite($pipes[0],json_encode(['db'=>RestApiEnv::testDb(),'action'=>$action]+$extra));
        fclose($pipes[0]);
        return ['process'=>$process,'pipes'=>$pipes];
    }

    private function finish(array $child): int
    {
        $body=stream_get_contents($child['pipes'][1]);
        $errors=stream_get_contents($child['pipes'][2]);
        fclose($child['pipes'][1]); fclose($child['pipes'][2]);
        $this->assertSame(0,proc_close($child['process']),$errors);
        $this->assertSame('',$errors);
        $this->assertMatchesRegularExpression('/^[1-9][0-9]*$/',$body);
        return (int)$body;
    }

    public function test_ten_parallel_journal_creations_and_five_first_approvals(): void
    {
        $children=[];
        for ($i=0;$i<10;$i++) {
            $children[]=$this->child('create');
        }
        $ids=array_map(fn($child)=>$this->finish($child),$children);
        $this->assertCount(10,array_unique($ids));
        $this->rows("DELETE FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id'");
        $before=(int)$this->rows('SELECT count(*) AS n FROM kladdeliste')[0]['n'];
        $children=[];
        for ($i=0;$i<5;$i++) {
            $children[]=$this->child('approval');
        }
        $ids=array_map(fn($child)=>$this->finish($child),$children);
        $this->assertCount(1,array_unique($ids));
        $this->assertSame($before+1,(int)$this->rows('SELECT count(*) AS n FROM kladdeliste')[0]['n']);
        $this->assertCount(1,$this->rows("SELECT * FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id' AND user_id=0"));
    }

    public function test_legacy_max_insert_races_mobile_and_mobile_retries_primary_key_collision(): void
    {
        $id=(int)$this->rows('SELECT COALESCE(max(id),0)+1 AS id FROM kladdeliste')[0]['id'];
        $this->rows("CREATE FUNCTION t3_test_insert_pause() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN IF NEW.oprettet_af='mobile-race' AND NEW.id=$id THEN
            PERFORM pg_advisory_xact_lock(713003); END IF; RETURN NEW; END $$");
        $this->rows('CREATE TRIGGER t3_test_insert_pause BEFORE INSERT ON kladdeliste FOR EACH ROW EXECUTE FUNCTION t3_test_insert_pause()');
        $this->rows('SELECT pg_advisory_lock(713003)');
        $child=$this->child('create',['username'=>'mobile-race']);
        try {
            $deadline=microtime(true)+10;
            do {
                $waiting=$this->rows("SELECT 1 FROM pg_locks WHERE locktype='advisory' AND objid=713003 AND NOT granted");
                if ($waiting) {
                    break;
                }
                usleep(10000);
            } while (microtime(true)<$deadline);
            $this->assertNotEmpty($waiting,'Mobile insert must already have allocated the same MAX+1');
            $this->rows("INSERT INTO kladdeliste(id,kladdedate,bogfort,oprettet_af) SELECT COALESCE(max(id),0)+1,CURRENT_DATE,'-','web-race' FROM kladdeliste");
        } finally {
            $this->rows('SELECT pg_advisory_unlock(713003)');
            $result=$this->finish($child);
            $this->rows('DROP TRIGGER t3_test_insert_pause ON kladdeliste');
            $this->rows('DROP FUNCTION t3_test_insert_pause()');
        }
        $this->assertSame($id+1,$result);
        $this->assertSame('web-race',$this->rows('SELECT oprettet_af FROM kladdeliste WHERE id=$1',[$id])[0]['oprettet_af']);
    }

    public function test_attachment_fields_and_display_status_follow_current_document_links(): void
    {
        $this->rows('BEGIN');
        $row=$this->pool(); $id=(int)$row['id'];
        $model=new DurableAttachmentModel($this->tenant,RestApiEnv::testDb(),'foundation/account/1',true);
        $this->assertSame('sendt',$model->toArray($row)['display_status']);
        $row=$this->rows("UPDATE pool_files SET review_state='rejected',reviewed_by='web:Bookkeeper',reviewed_at=now(),
            rejection_code='other',rejection_note='Please correct',vat_code='K1' WHERE id=$1 RETURNING *",[$id])[0];
        $data=$model->toArray($row);
        $this->assertSame('afvist',$data['display_status']);
        $this->assertSame('Bookkeeper',$data['rejection']['by_name']);
        $this->assertSame('K1',$data['vat_code']);
        $this->assertSame('"'.pool_review_version($row).'"',$data['etag']);
        $row=$this->rows("UPDATE pool_files SET status='attached',review_state='pending' WHERE id=$1 RETURNING *",[$id])[0];
        $this->assertSame('vedhaeftet',$model->toArray($row)['display_status']);
        $row=$this->rows("UPDATE pool_files SET review_state='approved' WHERE id=$1 RETURNING *",[$id])[0];
        $this->assertSame('godkendt',$model->toArray($row)['display_status']);
        $journal=kladde_journal_insert('display','test');
        $line=$this->rows('INSERT INTO kassekladde(kladde_id) VALUES ($1) RETURNING id',[$journal])[0]['id'];
        $this->rows("INSERT INTO documents(source,source_id,pool_file_id) VALUES ('kassekladde',$1,$2)",[$line,$id]);
        $this->rows("UPDATE kladdeliste SET bogfort='V' WHERE id=$1",[$journal]);
        $this->assertSame('bogfoert',$model->toArray($row)['display_status']);
        $row=$this->rows("UPDATE pool_files SET status='deleted' WHERE id=$1 RETURNING *",[$id])[0];
        $this->assertSame('slettet',$model->toArray($row)['display_status']);
        $this->rows('ROLLBACK');
        // List projection uses one snapshot and still applies the existing owner filter.
        $row=$this->pool();
        $listed=$model->listing(100,0,'ubehandlet',true);
        $items=array_column($listed['items'],null,'id');
        $this->assertArrayHasKey($row['id'],$items);
        $this->assertSame('sendt',$items[$row['id']]['display_status']);
        $this->assertSame('"'.pool_review_version($row).'"',$items[$row['id']]['etag']);
        $foreign=$this->rows("UPDATE pool_files SET uploaded_by='foundation/account/3' WHERE id=$1 RETURNING *",[$row['id']])[0];
        $this->assertArrayNotHasKey($foreign['id'],array_column($model->listing(100,0,null,true)['items'],null,'id'));
    }

    public function test_t3_instant_columns_and_chain_are_timezone_independent(): void
    {
        $this->rows('BEGIN');
        $columns=$this->rows("SELECT table_name,column_name,data_type FROM information_schema.columns WHERE table_schema='public'
            AND (table_name IN ('pool_file_audit','bank_line_imports','bank_lines','bank_line_journal_links','classification_suggestions',
                'classification_feedback','mobile_events','notification_preferences','mobile_scanner_state','mobile_low_stock_state','mobile_journal_lines','pool_file_moves')
                OR (table_name='pool_files' AND column_name IN ('reviewed_at','resubmitted_at')))
            AND (column_name LIKE '%_at')");
        $this->assertCount(19,$columns);
        foreach ($columns as $column) {
            $this->assertSame('timestamp with time zone',$column['data_type'],$column['table_name'].'.'.$column['column_name']);
        }
        $row=$this->pool(); $id=(int)$row['id'];
        $model=new DurableAttachmentModel($this->tenant,RestApiEnv::testDb(),'foundation/account/1',true);
        foreach (['UTC','Europe/Copenhagen'] as $zone) {
            $this->rows("SELECT set_config('TimeZone',$1,true)",[$zone]);
            $this->rows("UPDATE pool_files SET description=$1 WHERE id=$2",[$zone,$id]);
            $this->assertTrue($this->chainOk($id));
            $this->rows("UPDATE pool_files SET reviewed_at='2026-10-25T01:30:00+02:00',resubmitted_at='2026-10-25T02:30:00+01:00' WHERE id=$1",[$id]);
            $this->assertSame('7200.000000',$this->rows('SELECT extract(epoch FROM resubmitted_at-reviewed_at) AS seconds FROM pool_files WHERE id=$1',[$id])[0]['seconds']);
            $review=$this->rows("UPDATE pool_files SET review_state='rejected' WHERE id=$1 RETURNING *",[$id])[0];
            $this->assertSame('2026-10-24T23:30:00+00:00',$model->toArray($review)['rejection']['at']);
        }
        $registry=RestApiEnv::connect(self::$registryDb);
        try {
            mobileRegistryUpdates($registry);
            $this->assertSame('timestamp with time zone',RestApiEnv::rows($registry,"SELECT data_type FROM information_schema.columns WHERE table_name='mobile_summary_cache' AND column_name='computed_at'")[0]['data_type']);
        } finally {
            pg_close($registry);
        }
    }

    public function test_registry_read_declaration_runs_tenantless_and_denies_account_principals(): void
    {
        $master=RestApiEnv::connect(RestApiEnv::masterDb());
        $password=TestCredentials::password('t3foundationregistry');
        $username='t3foundation_'.bin2hex(random_bytes(6));
        $user=RestApiEnv::rows($master,"INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES ($1,$2,'on,,,',true) RETURNING id",[$username,md5($password)])[0];
        try {
            $login=RestApiEnv::http('POST','/restapi/endpoints/v1/auth/registry-login.php',
                ['username'=>$username,'password'=>$password,'device_id'=>'t3foundation']);
            $this->assertSame(200,$login['status']);
            foreach ([RestApiEnv::accessToken()=>403,$login['json']['data']['access_token']=>200] as $token=>$expected) {
                $source= <<<'PHP'
$input=json_decode(stream_get_contents(STDIN),true);
function getallheaders() { global $input; return ['Authorization'=>'Bearer '.$input['token']]; }
$_SERVER['REQUEST_METHOD']='GET'; $_SERVER['REQUEST_URI']='/t3-registry-test'; $_SERVER['SCRIPT_NAME']='/t3-registry-test';
require getcwd().'/restapi/core/BaseEndpoint.php';
class T3RegistryProbe extends BaseEndpoint {
    protected function requiredRight(): ?array { return ['module'=>'registry','action'=>'read']; }
    protected function handleGet($id=null) { $this->sendResponse(true,['tenant'=>$this->db,'principal'=>$this->principal]); }
}
register_shutdown_function(function () { file_put_contents('php://fd/3',(string)http_response_code()); });
(new T3RegistryProbe())->handleRequestMethod();
PHP;
                $process=proc_open([PHP_BINARY,'-r',$source],[['pipe','r'],['pipe','w'],['pipe','w'],['pipe','w']],$pipes,dirname(__DIR__,2));
                fwrite($pipes[0],json_encode(['token'=>$token])); fclose($pipes[0]);
                $body=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); $status=(int)stream_get_contents($pipes[3]);
                foreach ([1,2,3] as $fd) {
                    fclose($pipes[$fd]);
                }
                $this->assertSame(0,proc_close($process),$error);
                $this->assertSame('',$error);
                $this->assertSame($expected,$status,$body);
                $data=json_decode($body,true)['data'];
                if ($expected===200) {
                    $this->assertNull($data['tenant']);
                    $this->assertSame('registry',$data['principal']);
                } else {
                    $this->assertSame('permission_denied',$data['error']);
                }
            }
        } finally {
            foreach (['refresh_sessions','auth_versions','auth_challenges'] as $table) {
                RestApiEnv::rows($master,"DELETE FROM $table WHERE subject LIKE $1",['%/registry/'.$user['id']]);
            }
            RestApiEnv::rows($master,'DELETE FROM brugere WHERE id=$1',[$user['id']]);
            pg_close($master);
        }
    }
}
