<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileT4FoundationTest.php --- ver 5.0.0 --- 2026.10.07
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
// 20261007 CDX/LH Cover tenant-configured claims capability in auth me.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../includes/mobileUpdates.php';
require_once __DIR__ . '/../../restapi/core/Rights.php';
require_once __DIR__ . '/../../restapi/core/EmployeeContext.php';
require_once __DIR__ . '/../../includes/lager/stockLocks.php';

final class MobileT4FoundationTest extends TestCase
{
    private $tenant;
    private array $globals = [];

    public static function setUpBeforeClass(): void
    {
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
    }

    public static function tearDownAfterClass(): void
    {
        RestApiEnv::teardownTenant();
    }

    protected function setUp(): void
    {
        foreach (['connection','db_type','db_transaktion_depth','db_modify_fejl'] as $key) {
            $this->globals[$key] = [array_key_exists($key, $GLOBALS), ifset($GLOBALS, $key)];
        }
        $this->tenant = RestApiEnv::connect(RestApiEnv::testDb());
        $GLOBALS['connection'] = $this->tenant;
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['db_transaktion_depth'] = 0;
        $GLOBALS['db_modify_fejl'] = false;
        mobileTenantUpdates($this->tenant);
    }

    protected function tearDown(): void
    {
        if (pg_transaction_status($this->tenant) !== PGSQL_TRANSACTION_IDLE) {
            $this->rows('ROLLBACK');
        }
        pg_close($this->tenant);
        foreach ($this->globals as $key => [$exists, $value]) {
            if ($exists) {
                $GLOBALS[$key] = $value;
            } else {
                unset($GLOBALS[$key]);
            }
        }
    }

    /** @return list<array<string,mixed>> */
    private function rows(string $sql, array $params = []): array
    {
        return RestApiEnv::rows($this->tenant, $sql, $params);
    }

    private function uuid(): string
    {
        $hex = bin2hex(random_bytes(16));
        return substr($hex,0,8) . '-' . substr($hex,8,4) . '-4' . substr($hex,13,3) . '-8' . substr($hex,17,3) . '-' . substr($hex,20);
    }

    /** Execute deliberate rejected SQL without hiding PHP warnings. */
    private function rejected(string $sql, string $state = '55000', ?string $message = null): void
    {
        $this->rows('SAVEPOINT expected_rejection');
        $this->assertTrue(pg_send_query($this->tenant, $sql));
        $result = pg_get_result($this->tenant);
        while (pg_get_result($this->tenant) !== false) {
        }
        $this->assertSame(PGSQL_FATAL_ERROR, pg_result_status($result));
        $this->assertSame($state, pg_result_error_field($result, PGSQL_DIAG_SQLSTATE));
        if ($message !== null) {
            $this->assertStringContainsString($message, pg_result_error($result));
        }
        $this->rows('ROLLBACK TO SAVEPOINT expected_rejection');
    }

    public function test_steps_apply_to_a_fresh_t4_clone_preserve_rows_on_replay_and_cost_one_select_when_current(): void
    {
        foreach (['mobile_expense_claims','mobile_mileage_trips','mobile_mileage_allocations','mobile_mileage_statements',
            'mobile_booking_history','mobile_bookings','mobile_time_entries','mobile_goods_receipt_lines','mobile_goods_receipts',
            'mobile_stock_count_lines','mobile_stock_counts'] as $table) {
            $this->rows("DROP TABLE $table");
        }
        $this->rows("UPDATE settings SET var_value='m3_09_mobile_journal_identity'
            WHERE var_grp='mobile' AND var_name='mobile_tenant_step' AND user_id=0");
        mobileTenantUpdates($this->tenant);
        $this->assertSame('m4_06_fefo_columns', array_key_last(mobileTenantUpdateSteps()));
        $this->rows("INSERT INTO mobile_time_entries(loen_id,client_id,subject,ansat_id) VALUES (1,$1,'fixture',1)", [$this->uuid()]);
        // Full replay of the runner, including T1/T2/T3, cannot rewrite prior steps/data.
        $this->rows("DELETE FROM settings WHERE var_grp='mobile' AND var_name='mobile_tenant_step' AND user_id=0");
        mobileTenantUpdates($this->tenant);
        $this->assertSame('1', $this->rows('SELECT count(*) FROM mobile_time_entries WHERE loen_id=1')[0]['count']);
        $this->assertSame('m4_06_fefo_columns', $this->rows("SELECT var_value FROM settings WHERE var_name='mobile_tenant_step' AND var_grp='mobile' AND user_id=0")[0]['var_value']);
        $this->rows('BEGIN READ ONLY');
        $sql = "SELECT seq_scan+idx_scan AS n FROM pg_stat_xact_user_tables WHERE relname='settings'";
        $before = (int)$this->rows($sql)[0]['n'];
        mobileTenantUpdates($this->tenant);
        $this->assertSame($before + 1, (int)$this->rows($sql)[0]['n']);
        $this->assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status($this->tenant));
    }

    public function test_fefo_step_repairs_six_missing_columns_and_preserves_values_on_repeat(): void
    {
        $this->rows('BEGIN');
        $columns = ['batch_kob' => ['due_date','batch_no'], 'varer' => ['has_due_date','default_shelf_life_days'],
            'ordrelinjer' => ['batch_due_date','batch_batch_no']];
        foreach ($columns as $table => $names) {
            foreach ($names as $name) {
                $this->rows("ALTER TABLE $table DROP COLUMN $name");
            }
        }
        $step = mobileTenantUpdateSteps()['m4_06_fefo_columns'];
        $step(new MobileSchema($this->tenant));
        $definitions = $this->rows("SELECT table_name,column_name,data_type,character_maximum_length,is_nullable,column_default
            FROM information_schema.columns WHERE table_schema='public' AND
            ((table_name='batch_kob' AND column_name IN ('due_date','batch_no'))
            OR (table_name='varer' AND column_name IN ('has_due_date','default_shelf_life_days'))
            OR (table_name='ordrelinjer' AND column_name IN ('batch_due_date','batch_batch_no'))) ORDER BY table_name,column_name");
        $this->assertCount(6, $definitions);
        foreach ($definitions as $column) {
            $this->assertSame('YES', $column['is_nullable']);
            $expected = str_contains($column['column_name'], 'batch_no') ? 'character varying'
                : (str_contains($column['column_name'], 'due_date') ? 'date' : 'integer');
            if ($column['column_name'] === 'has_due_date') {
                $expected = 'boolean';
                $this->assertSame('false', $column['column_default']);
            }
            $this->assertSame($expected, $column['data_type']);
            if ($expected === 'character varying') {
                $this->assertSame('100', $column['character_maximum_length']);
            }
        }
        $id = $this->rows("INSERT INTO varer(varenr,has_due_date,default_shelf_life_days) VALUES ($1,true,45) RETURNING id", ['fefo-' . $this->uuid()])[0]['id'];
        $step(new MobileSchema($this->tenant));
        $this->assertSame(['has_due_date'=>'t','default_shelf_life_days'=>'45'], $this->rows('SELECT has_due_date,default_shelf_life_days FROM varer WHERE id=$1', [$id])[0]);
        $this->assertSame($definitions, $this->rows("SELECT table_name,column_name,data_type,character_maximum_length,is_nullable,column_default
            FROM information_schema.columns WHERE table_schema='public' AND
            ((table_name='batch_kob' AND column_name IN ('due_date','batch_no'))
            OR (table_name='varer' AND column_name IN ('has_due_date','default_shelf_life_days'))
            OR (table_name='ordrelinjer' AND column_name IN ('batch_due_date','batch_batch_no'))) ORDER BY table_name,column_name"));
    }

    private function countRow(): int
    {
        return (int)$this->rows("INSERT INTO mobile_stock_counts(client_id,session_id,subject,lager,reason_code,line_count,adjusted_count,value_estimate)
            VALUES ($1,$2,'fixture',1,'optaelling',1,1,54) RETURNING id", [$this->uuid(),$this->uuid()])[0]['id'];
    }

    private function statement(): int
    {
        return (int)$this->rows("INSERT INTO mobile_mileage_statements(client_id,subject,period_from,period_to,km_total,amount,rates_json,lines_json,state)
            VALUES ($1,'fixture','2026-10-01','2026-10-31',42,159.18,'{}','[]','generating') RETURNING id", [$this->uuid()])[0]['id'];
    }

    public function test_all_lines_history_and_allocations_are_append_only_and_counts_allow_only_sync_updates(): void
    {
        $this->rows('BEGIN');
        $count = $this->countRow();
        $this->rows("INSERT INTO mobile_stock_count_lines(count_id,line_ref,vare_id,lager,counted_qty,before_qty,after_qty,delta,kostpris,outcome)
            VALUES ($1,$2,1,1,4,3,4,1,54,'adjusted')", [$count,$this->uuid()]);
        $receipt = $this->rows("INSERT INTO mobile_goods_receipts(client_id,ordre_id,subject,receipt_date) VALUES ($1,1,'fixture','2026-10-06') RETURNING id", [$this->uuid()])[0]['id'];
        $this->rows('INSERT INTO mobile_goods_receipt_lines(receipt_id,ordrelinje_id,lager,quantity) VALUES ($1,1,1,2)', [$receipt]);
        $booking = $this->rows("INSERT INTO mobile_bookings(client_id,booking_date,start_time,duration_minutes,ansat_id,customer_name,service,created_by)
            VALUES ($1,'2026-10-25','02:30',60,1,'Fixture','Visit','fixture') RETURNING id", [$this->uuid()])[0]['id'];
        $this->rows("INSERT INTO mobile_booking_history(booking_id,event,actor,after_json) VALUES ($1,'created','fixture','{}')", [$booking]);
        $this->rows("INSERT INTO mobile_mileage_allocations(statement_id,scope,year,km_high,km_low) VALUES ($1,'ansat:1',2026,42,0)", [$this->statement()]);
        foreach (['mobile_stock_count_lines'=>'counted_qty', 'mobile_goods_receipt_lines'=>'quantity',
            'mobile_booking_history'=>'booking_id', 'mobile_mileage_allocations'=>'km_high'] as $table => $field) {
            $this->rejected("UPDATE $table SET $field=$field");
            $this->rejected("DELETE FROM $table");
            $this->rejected("TRUNCATE $table CASCADE");
        }
        $this->rows("UPDATE mobile_stock_counts SET shop_sync='not_configured' WHERE id=$1", [$count]);
        $this->assertSame('not_configured', $this->rows('SELECT shop_sync FROM mobile_stock_counts WHERE id=$1', [$count])[0]['shop_sync']);
        $this->rejected("UPDATE mobile_stock_counts SET note='edited' WHERE id=$count");
        $this->rejected("UPDATE mobile_stock_counts SET value_estimate=55 WHERE id=$count");
        $this->rejected("DELETE FROM mobile_stock_counts WHERE id=$count");
        $this->rejected('TRUNCATE mobile_stock_counts CASCADE');
    }

    public function test_generated_documents_guard_all_financial_fields_but_allow_lifecycle_path_and_account(): void
    {
        $this->rows('BEGIN');
        $id = (int)$this->rows("INSERT INTO pool_files(filename,amount,file_date,currency,description,invoice_number)
            VALUES ($1,'159.18','2026-10-31','DKK','Statement','10') RETURNING id", [$this->uuid() . '.pdf'])[0]['id'];
        $this->rows("INSERT INTO mobile_expense_claims(client_id,pool_file_id,kind,subject,statement_id)
            VALUES ($1,$2,'koersel','fixture',$3)", [$this->uuid(),$id,$this->statement()]);
        foreach (['amount'=>"'160.00'", 'file_date'=>"'2026-11-01'", 'currency'=>"'EUR'", 'description'=>"'Changed'", 'invoice_number'=>"'11'"] as $field => $value) {
            $this->rejected("UPDATE pool_files SET $field=$value WHERE id=$id", '55000', 'generated_document');
            $this->rejected("UPDATE pool_files SET $field=NULL WHERE id=$id", '55000', 'generated_document');
        }
        $this->rows("UPDATE pool_files SET amount=amount,status='attached',storage_path='finance/1/1/statement.pdf',account='5800',subject='display label' WHERE id=$1", [$id]);
        $this->assertSame('159.18', $this->rows('SELECT amount FROM pool_files WHERE id=$1', [$id])[0]['amount']);
        $this->rows("UPDATE pool_files SET status='deleted' WHERE id=$1", [$id]);
        foreach ([null,'udlaeg'] as $kind) {
            $other = (int)$this->rows('INSERT INTO pool_files(filename) VALUES ($1) RETURNING id', [$this->uuid() . '.pdf'])[0]['id'];
            if ($kind !== null) {
                $this->rows('INSERT INTO mobile_expense_claims(client_id,pool_file_id,kind,subject) VALUES ($1,$2,$3,$4)', [$this->uuid(),$other,$kind,'fixture']);
            }
            $this->rows("UPDATE pool_files SET amount='200.00' WHERE id=$1", [$other]);
            $this->assertSame('200.00', $this->rows('SELECT amount FROM pool_files WHERE id=$1', [$other])[0]['amount']);
        }
    }

    public function test_instants_have_time_zones_and_booking_wall_times_remain_local(): void
    {
        $instants = $this->rows("SELECT table_name,column_name,data_type FROM information_schema.columns
            WHERE table_schema='public' AND table_name IN ('mobile_stock_counts','mobile_stock_count_lines',
            'mobile_goods_receipts','mobile_time_entries','mobile_bookings','mobile_booking_history',
            'mobile_mileage_statements','mobile_mileage_trips','mobile_expense_claims') AND
            (column_name LIKE '%\\_at' ESCAPE '\\' OR column_name='at')");
        $this->assertCount(20, $instants);
        foreach ($instants as $column) {
            $this->assertSame('timestamp with time zone', $column['data_type'], $column['table_name'] . '.' . $column['column_name']);
        }
        $this->assertSame('date', $this->rows("SELECT data_type FROM information_schema.columns WHERE table_name='mobile_bookings' AND column_name='booking_date'")[0]['data_type']);
        $this->assertSame('time without time zone', $this->rows("SELECT data_type FROM information_schema.columns WHERE table_name='mobile_bookings' AND column_name='start_time'")[0]['data_type']);
    }

    /** @return array{user_id:int,employee_id:int,group_id:int,company_id:int} */
    private function linkEmployee(string $digits = '10000010'): array
    {
        $own = $this->rows("SELECT id FROM adresser WHERE art='S' ORDER BY id LIMIT 1");
        $companyId = $own ? (int)$own[0]['id'] : (int)$this->rows("INSERT INTO adresser(art,firmanavn) VALUES ('S','Fixture company') RETURNING id")[0]['id'];
        $groupId = (int)$this->rows("INSERT INTO grupper(art,box2) VALUES ('ANSAT',$1) RETURNING id", [$digits])[0]['id'];
        $employeeId = (int)$this->rows("INSERT INTO ansatte(navn,initialer,nummer,gruppe,konto_id,lukket)
            VALUES ('Field employee','FE','42',$1,$2,'') RETURNING id", [$groupId,$companyId])[0]['id'];
        $userId = (int)$this->rows('SELECT id FROM brugere WHERE brugernavn=$1', [RestApiEnv::user()])[0]['id'];
        $this->rows('UPDATE brugere SET ansat_id=$1 WHERE id=$2', [$employeeId,$userId]);
        return ['user_id'=>$userId,'employee_id'=>$employeeId,'group_id'=>$groupId,'company_id'=>$companyId];
    }

    public function test_employee_context_link_closed_missing_group_foreign_company_and_registry_id_separation(): void
    {
        $this->rows('BEGIN');
        $fixture = $this->linkEmployee();
        $actor = new EmployeeContext($this->tenant, 'account', $fixture['user_id']);
        $this->assertTrue($actor->linked);
        $this->assertTrue($actor->ownCompany);
        $this->assertSame('10000010', $actor->caseDigits);
        $this->assertSame($fixture['employee_id'], $actor->ansatId);
        $this->assertSame('FE', $actor->employee['initialer']);
        $this->assertNull($actor->denialReason());
        $this->rows('UPDATE ansatte SET konto_id=$1 WHERE id=$2', [$fixture['company_id'] + 9999,$fixture['employee_id']]);
        $foreign = new EmployeeContext($this->tenant, 'account', $fixture['user_id']);
        $this->assertTrue($foreign->linked);
        $this->assertFalse($foreign->ownCompany);
        $this->rows('UPDATE ansatte SET gruppe=NULL WHERE id=$1', [$fixture['employee_id']]);
        $this->assertNull((new EmployeeContext($this->tenant, 'account', $fixture['user_id']))->caseDigits);
        $this->rows("UPDATE ansatte SET lukket='on' WHERE id=$1", [$fixture['employee_id']]);
        $closed = new EmployeeContext($this->tenant, 'account', $fixture['user_id']);
        $this->assertFalse($closed->linked);
        $this->assertFalse($closed->ownCompany);
        $this->assertSame('no_employee_link', $closed->denialReason());
        $this->rows('UPDATE brugere SET ansat_id=NULL WHERE id=$1', [$fixture['user_id']]);
        $unlinked = new EmployeeContext($this->tenant, 'account', $fixture['user_id']);
        $this->assertNull($unlinked->employee);
        $this->assertNull($unlinked->ansatId);
        $this->assertFalse($unlinked->linked);
        $this->rows('UPDATE brugere SET ansat_id=99999999 WHERE id=$1', [$fixture['user_id']]);
        $this->assertFalse((new EmployeeContext($this->tenant, 'account', $fixture['user_id']))->linked);
        $this->assertFalse((new EmployeeContext(null, 'registry', $fixture['user_id']))->linked);
    }

    public function test_case_offsets_do_not_depend_on_tenant_rights_and_registry_cannot_take_employee_rights(): void
    {
        foreach (['read','write'] as $action) {
            $this->assertTrue(Rights::allows(str_repeat('0',16), 'sager_loen', $action, '10000000'));
            $this->assertFalse(Rights::allows('', 'sager_loen', $action, null));
            $this->assertFalse(Rights::allows('', 'sager_loen', $action, '00100010'));
            $this->assertFalse(Rights::allows('', 'sager_loen', $action, '11111111', true));
        }
        $this->assertTrue(Rights::allows('', 'sager_godkend', 'write', '00000010'));
        $this->assertFalse(Rights::allows('', 'sager_godkend', 'write', null));
        $this->assertFalse(Rights::allows('', 'sager_godkend', 'read', '11111111'));
        $this->assertFalse(Rights::allows('', 'sager_godkend', 'write', '11111111', true));
        $this->assertTrue(Rights::allows('', 'employee', 'own'));
        $this->assertFalse(Rights::allows('', 'employee', 'own', null, true));
        $this->assertFalse(Rights::allows('', 'employee', 'read'));
        $this->assertFalse(Rights::allows('', 'sager_unknown', 'write', '11111111', true));
        $this->assertTrue(Rights::allows('', 'sager', 'read', '00100000'));
        $this->assertTrue(Rights::allows('', 'sager', 'read', null, true));
    }

    public function test_item_locks_require_transaction_and_sections_keep_caller_ownership(): void
    {
        try {
            stock_lock_item(1);
            $this->fail('Autocommit lock accepted');
        } catch (LogicException $error) {
            $this->assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status($this->tenant));
        }
        $this->rows('BEGIN');
        $GLOBALS['db_transaktion_depth'] = 4;
        $this->assertSame('result', stock_item_section([3,1,3,2], function (): string {
            $this->assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status($this->tenant));
            return 'result';
        }));
        $locks = $this->rows("SELECT objid FROM pg_locks WHERE locktype='advisory' AND pid=pg_backend_pid()
            AND classid=(hashtext('saldi:stock')::bigint & 4294967295) ORDER BY objid");
        $this->assertSame(['1','2','3'], array_column($locks, 'objid'));
        try {
            stock_item_section([1], static function (): void { throw new RuntimeException('callback failure'); });
            $this->fail('Exception lost');
        } catch (RuntimeException $error) {
            $this->assertSame('callback failure', $error->getMessage());
        }
        $this->assertSame(4, $GLOBALS['db_transaktion_depth']);
        $this->assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status($this->tenant));
    }

    public function test_owned_sections_commit_rollback_and_restore_legacy_transaction_flags(): void
    {
        $this->rows('CREATE TABLE IF NOT EXISTS t4_stock_probe(item integer PRIMARY KEY,quantity integer NOT NULL)');
        $this->rows('INSERT INTO t4_stock_probe VALUES (9001,0) ON CONFLICT(item) DO UPDATE SET quantity=0');
        $GLOBALS['db_transaktion_depth'] = 7;
        $GLOBALS['db_modify_fejl'] = true;
        $this->assertSame(9, stock_item_section([9001], function (): int {
            $this->assertSame(1, $GLOBALS['db_transaktion_depth']);
            $this->assertFalse($GLOBALS['db_modify_fejl']);
            $this->rows('UPDATE t4_stock_probe SET quantity=9 WHERE item=9001');
            return 9;
        }));
        $this->assertSame('9', $this->rows('SELECT quantity FROM t4_stock_probe WHERE item=9001')[0]['quantity']);
        foreach (['throw','write_flag'] as $failure) {
            try {
                stock_item_section([9001], function () use ($failure): void {
                    $this->rows('UPDATE t4_stock_probe SET quantity=10 WHERE item=9001');
                    if ($failure === 'throw') {
                        throw new RuntimeException('callback failure');
                    }
                    $GLOBALS['db_modify_fejl'] = true;
                });
                $this->fail('Failure committed');
            } catch (RuntimeException $error) {
                $this->assertSame('9', $this->rows('SELECT quantity FROM t4_stock_probe WHERE item=9001')[0]['quantity']);
            }
            $this->assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status($this->tenant));
            $this->assertSame(7, $GLOBALS['db_transaktion_depth']);
            $this->assertTrue($GLOBALS['db_modify_fejl']);
        }
    }

    /** @return array{0:resource,1:array<int,resource>} */
    private function startChild(string $code): array
    {
        $process = proc_open([PHP_BINARY,'-r',$code], [1=>['pipe','w'],2=>['pipe','w']], $pipes, dirname(__DIR__,2));
        $this->assertIsResource($process);
        return [$process,$pipes];
    }

    private function finishChild(array $child): string
    {
        [$process,$pipes] = $child;
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $error . $output);
        $this->assertSame('', $error);
        return trim($output);
    }

    private function childBootstrap(): string
    {
        return 'require __DIR__ . "/tests/restapi/support/RestApiEnv.php"; require __DIR__ . "/includes/lager/stockLocks.php"; '
            . '$c=RestApiEnv::connect(' . var_export(RestApiEnv::testDb(),true) . '); $GLOBALS["connection"]=$c;';
    }

    private function assertChildWaits(int $pid): void
    {
        $deadline = microtime(true) + 5;
        do {
            $waiting = $this->rows("SELECT wait_event FROM pg_stat_activity WHERE pid=$1", [$pid]);
            if ($waiting && $waiting[0]['wait_event'] === 'advisory') {
                $this->assertTrue(true);
                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('Second session did not wait on the item advisory lock');
    }

    public function test_two_sessions_serialize_one_item_with_each_transaction_ownership_combination(): void
    {
        $this->rows('CREATE TABLE IF NOT EXISTS t4_stock_probe(item integer PRIMARY KEY,quantity integer NOT NULL)');
        foreach ([false,true] as $parentCaller) {
            foreach ([false,true] as $childCaller) {
                $this->rows('INSERT INTO t4_stock_probe VALUES (9002,0) ON CONFLICT(item) DO UPDATE SET quantity=0');
                if ($parentCaller) {
                    $this->rows('BEGIN');
                }
                $child = null;
                try {
                    stock_item_section([9002], function () use ($childCaller, &$child): void {
                        $this->rows('UPDATE t4_stock_probe SET quantity=quantity+1 WHERE item=9002');
                        $code = $this->childBootstrap()
                            . ($childCaller ? 'RestApiEnv::rows($c,"BEGIN");' : '')
                            . 'echo pg_get_pid($c)."\n"; fflush(STDOUT); '
                            . '$n=stock_item_section([9002],function()use($c){return RestApiEnv::rows($c,"UPDATE t4_stock_probe SET quantity=quantity+1 WHERE item=9002 RETURNING quantity")[0]["quantity"];});'
                            . ($childCaller ? 'RestApiEnv::rows($c,"COMMIT");' : '') . 'echo $n;';
                        $child = $this->startChild($code);
                        $read = [$child[1][1]];
                        $write = $except = [];
                        $this->assertSame(1, stream_select($read,$write,$except,5));
                        $pid = (int)trim(fgets($child[1][1]));
                        $this->assertGreaterThan(0, $pid);
                        $this->assertChildWaits($pid);
                    });
                    if ($parentCaller) {
                        $this->assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status($this->tenant));
                        $this->rows('COMMIT');
                    }
                    $this->assertSame('2', $this->finishChild($child));
                    $child = null;
                    $this->assertSame('2', $this->rows('SELECT quantity FROM t4_stock_probe WHERE item=9002')[0]['quantity']);
                } finally {
                    if (pg_transaction_status($this->tenant) !== PGSQL_TRANSACTION_IDLE) {
                        $this->rows('ROLLBACK');
                    }
                    if ($child !== null) {
                        proc_terminate($child[0]);
                        foreach ($child[1] as $pipe) {
                            fclose($pipe);
                        }
                        proc_close($child[0]);
                    }
                }
            }
        }
    }

    public function test_shop_sync_deduplicates_targets_isolates_throw_and_error_and_refuses_an_open_transaction(): void
    {
        $code = 'function sync_shop_vare($item,$variant,$lager){$GLOBALS["calls"][]=[$item,$variant,$lager]; '
            . 'if($item===2){throw new RuntimeException("stub failure");} if($item===3){return "sync errors: 1";} return $item===4?"no api":"OK";}'
            . $this->childBootstrap()
            . '$targets=[["vare_id"=>1,"variant_id"=>0,"lager"=>1],["vare_id"=>2,"variant_id"=>0,"lager"=>1],'
            . '["vare_id"=>3,"variant_id"=>0,"lager"=>1],["vare_id"=>4,"variant_id"=>0,"lager"=>1],'
            . '["vare_id"=>1,"variant_id"=>0,"lager"=>1],["vare_id"=>1,"variant_id"=>9,"lager"=>2]];'
            . '$result=stock_shop_sync($targets); RestApiEnv::rows($c,"BEGIN"); $guard=false; '
            . 'try{stock_shop_sync($targets);}catch(LogicException $e){$guard=true;} RestApiEnv::rows($c,"ROLLBACK");'
            . 'echo json_encode(["results"=>$result,"calls"=>$GLOBALS["calls"],"guard"=>$guard]);';
        $result = json_decode($this->finishChild($this->startChild($code)),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame(['done','failed','failed','no api','done'], array_column($result['results'],'state'));
        $this->assertSame([[1,0,1],[2,0,1],[3,0,1],[4,0,1],[1,9,2]], $result['calls']);
        $this->assertTrue($result['guard']);
    }

    /** Evaluate the real middleware in a CLI child without adding a production HTTP probe. */
    private function declaration(string $module, string $action, string $principal, int $userId): array
    {
        $code = '$_SERVER["REQUEST_URI"]="/saldi/restapi/endpoints/v1/t4-probe.php"; $_SERVER["REMOTE_ADDR"]="127.0.0.1"; require __DIR__ . "/restapi/core/BaseEndpoint.php"; class T4Probe extends BaseEndpoint {'
            . 'private array $right; protected function requiredRight():?array{return $this->right;} '
            . 'public function check(array $r,string $p,int $id,string $db):bool{$this->right=$r;$this->principal=$p;$this->db=$db;return $this->enforceDeclaredRight(["user_id"=>$id]);}'
            . 'protected function sendResponse($success,$data=null,$message="",$httpCode=200){throw new RuntimeException(json_encode(["status"=>$httpCode,"data"=>$data]));}} '
            . 'try{$ok=(new T4Probe())->check(' . var_export(['module'=>$module,'action'=>$action],true) . ','
            . var_export($principal,true) . ',' . $userId . ',' . var_export(RestApiEnv::testDb(),true) . ');echo json_encode(["allowed"=>$ok]);}'
            . 'catch(RuntimeException $e){echo $e->getMessage();}';
        return json_decode($this->finishChild($this->startChild($code)),true,512,JSON_THROW_ON_ERROR);
    }

    public function test_middleware_loads_both_case_offsets_checks_employee_links_and_rejects_registry(): void
    {
        $fixture = $this->linkEmployee();
        $id = $fixture['user_id'];
        foreach ([['sager_loen','read'],['sager_loen','write'],['sager_godkend','write'],['employee','own']] as [$module,$action]) {
            $this->assertSame(['allowed'=>true], $this->declaration($module,$action,'account',$id));
            $this->assertSame(['status'=>403,'data'=>['details'=>['reason'=>'no_employee_link'],'error'=>'permission_denied']], $this->declaration($module,$action,'registry',$id));
        }
        $this->rows("UPDATE grupper SET box2='00000000' WHERE id=$1", [$fixture['group_id']]);
        $this->assertSame(['allowed'=>false], $this->declaration('sager_loen','read','account',$id));
        $this->assertSame(['allowed'=>false], $this->declaration('sager_godkend','write','account',$id));
        $this->rows('UPDATE ansatte SET gruppe=NULL WHERE id=$1', [$fixture['employee_id']]);
        $this->assertSame(['allowed'=>false], $this->declaration('sager_loen','write','account',$id));
        $this->assertSame(['allowed'=>true], $this->declaration('employee','own','account',$id));
        $this->rows("UPDATE ansatte SET lukket='on' WHERE id=$1", [$fixture['employee_id']]);
        $this->assertSame(['status'=>403,'data'=>['details'=>['reason'=>'no_employee_link'],'error'=>'permission_denied']], $this->declaration('employee','own','account',$id));
        $this->rows('UPDATE brugere SET ansat_id=NULL WHERE id=$1', [$id]);
        $this->assertSame(['status'=>403,'data'=>['details'=>['reason'=>'no_employee_link'],'error'=>'permission_denied']], $this->declaration('sager_loen','read','account',$id));
    }

    public function test_me_adds_employee_and_precise_permissions_without_activating_unbuilt_capabilities(): void
    {
        $fixture = $this->linkEmployee();
        $headers = RestApiEnv::authHeaders();
        $me = RestApiEnv::http('GET','/restapi/endpoints/v1/auth/me.php',null,$headers);
        $this->assertSame(200, $me['status'], $me['body']);
        $data = $me['json']['data'];
        $this->assertTrue($data['employee_link']);
        $this->assertSame($fixture['employee_id'], $data['ansat_id']);
        $this->assertSame('Field employee', $data['user']['name']);
        $this->assertSame(['read'=>true,'write'=>true], $data['permissions']['sager_loen']);
        $this->assertSame(['read'=>false,'write'=>true], $data['permissions']['sager_godkend']);
        $this->assertSame(['read'=>true,'write'=>true], $data['permissions']['bookings_all']);
        $this->assertSame('v2', $data['capabilities']['attachments']);
        foreach (['stock_count','goods_receipt','time_entries','bookings','claims'] as $capability) {
            $installed = [];
            foreach (glob(__DIR__ . '/../../restapi/core/capabilities.d/*.php') ?: [] as $file) {
                $installed += require $file;
            }
            if (array_key_exists($capability, $installed)) {
                $expected = $installed[$capability];
                if ($capability === 'claims') {
                    $connection = RestApiEnv::connect(RestApiEnv::testDb());
                    try {
                        require_once __DIR__ . '/../../restapi/services/ClaimsService.php';
                        $claims = new ClaimsService($connection, RestApiEnv::testDb(), $data['subject'], 'account', $fixture['user_id']);
                        if (!$claims->capability()) {
                            $expected = false;
                        }
                    } finally {
                        pg_close($connection);
                    }
                }
                $this->assertSame($expected, $data['capabilities'][$capability]);
            } else {
                $this->assertArrayNotHasKey($capability, $data['capabilities']);
            }
        }
        $this->rows("UPDATE ansatte SET lukket='on' WHERE id=$1", [$fixture['employee_id']]);
        $closed = RestApiEnv::http('GET','/restapi/endpoints/v1/auth/me.php',null,$headers);
        $this->assertSame(200, $closed['status'], $closed['body']);
        $this->assertFalse($closed['json']['data']['employee_link']);
        foreach (['sager_loen','sager_godkend','bookings_all'] as $module) {
            $this->assertSame(['read'=>false,'write'=>false], $closed['json']['data']['permissions'][$module]);
        }
    }

    public function test_me_booking_authority_is_current_admin_or_leader_and_unlinked_users_have_no_employee_permissions(): void
    {
        $fixture = $this->linkEmployee('10000000');
        $headers = RestApiEnv::authHeaders();
        $this->rows('UPDATE brugere SET rettigheder=$1 WHERE id=$2', [str_repeat('0',16),$fixture['user_id']]);
        $me = RestApiEnv::http('GET','/restapi/endpoints/v1/auth/me.php',null,$headers);
        $this->assertSame(200, $me['status'], $me['body']);
        $this->assertTrue($me['json']['data']['employee_link']);
        $this->assertSame(['read'=>true,'write'=>true], $me['json']['data']['permissions']['sager_loen']);
        $this->assertSame(['read'=>false,'write'=>false], $me['json']['data']['permissions']['bookings_all']);
        $this->rows("UPDATE grupper SET box2='00000010' WHERE id=$1", [$fixture['group_id']]);
        $leader = RestApiEnv::http('GET','/restapi/endpoints/v1/auth/me.php',null,$headers);
        $this->assertSame(['read'=>true,'write'=>true], $leader['json']['data']['permissions']['bookings_all']);
        $this->assertSame(['read'=>false,'write'=>false], $leader['json']['data']['permissions']['sager_loen']);
        $this->rows('UPDATE brugere SET ansat_id=NULL WHERE id=$1', [$fixture['user_id']]);
        $unlinked = RestApiEnv::http('GET','/restapi/endpoints/v1/auth/me.php',null,$headers);
        $this->assertFalse($unlinked['json']['data']['employee_link']);
        $this->assertNull($unlinked['json']['data']['ansat_id']);
        $this->assertSame(RestApiEnv::user(), $unlinked['json']['data']['user']['name']);
        foreach (['sager_loen','sager_godkend','bookings_all'] as $module) {
            $this->assertSame(['read'=>false,'write'=>false], $unlinked['json']['data']['permissions'][$module]);
        }
        $this->rows('UPDATE brugere SET rettigheder=$1 WHERE id=$2', [str_repeat('1',16),$fixture['user_id']]);
    }

    public function test_registry_me_has_no_employee_permissions_before_or_after_tenant_exchange(): void
    {
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        $name = 't4_' . bin2hex(random_bytes(6));
        $password = TestCredentials::password('t4foundationregistry');
        $tenantId = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
        $userId = RestApiEnv::rows($master, 'INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES ($1,$2,$3,true) RETURNING id',
            [$name,md5($password),',,,' . $tenantId])[0]['id'];
        try {
            $login = RestApiEnv::http('POST','/restapi/endpoints/v1/auth/registry-login.php',
                ['username'=>$name,'password'=>$password,'device_id'=>'t4-registry']);
            $this->assertSame(200, $login['status'], $login['body']);
            $headers = ['Authorization: Bearer ' . $login['json']['data']['access_token']];
            $exchange = RestApiEnv::http('POST','/restapi/endpoints/v1/auth/tenant-token.php', ['tenant_id'=>$tenantId],$headers);
            $this->assertSame(200, $exchange['status'], $exchange['body']);
            foreach ([$headers,['Authorization: Bearer ' . $exchange['json']['data']['access_token']]] as $identity) {
                $me = RestApiEnv::http('GET','/restapi/endpoints/v1/auth/me.php',null,$identity);
                $this->assertSame(200, $me['status'], $me['body']);
                $this->assertFalse($me['json']['data']['employee_link']);
                $this->assertNull($me['json']['data']['ansat_id']);
                foreach (['sager_loen','sager_godkend','bookings_all'] as $module) {
                    $this->assertSame(['read'=>false,'write'=>false], $me['json']['data']['permissions'][$module]);
                }
            }
        } finally {
            foreach (['refresh_sessions','auth_challenges','auth_versions'] as $table) {
                RestApiEnv::rows($master, "DELETE FROM $table WHERE subject LIKE $1", ['%/registry/' . $userId]);
            }
            RestApiEnv::rows($master, 'DELETE FROM brugere WHERE id=$1', [$userId]);
            pg_close($master);
        }
    }

    public function test_fixture_stubs_have_complete_json_shapes_and_valid_client_ids(): void
    {
        $files = glob(__DIR__ . '/fixtures/t4/*.json');
        $this->assertGreaterThanOrEqual(36, count($files));
        foreach ($files as $file) {
            $fixture = json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
            if (str_ends_with($file, '.request.json')) {
                $this->assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $fixture['client_id'], $file);
            } else {
                $this->assertIsBool($fixture['success'], $file);
                $this->assertIsString($fixture['message'], $file);
                $this->assertIsArray($fixture['data'], $file);
            }
        }
        $entry = json_decode(file_get_contents(__DIR__ . '/fixtures/t4/time-entry.json'),true)['data'];
        $this->assertSame(['id','nummer','case','task','date','hours','overtime_50','overtime_100','hour_type','text',
            'state','rejection','created_in_app','editable','read_only_reason','etag'], array_keys($entry));
        $this->assertSame('7.50', $entry['hours']);
        $snapshot = json_decode(file_get_contents(__DIR__ . '/fixtures/t4/count-snapshot.json'),true)['data'];
        $this->assertSame(64, strlen($snapshot['items'][0]['token']));
    }
}
