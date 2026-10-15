<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/runtime/SaldiRuntimeDbTest.test.php --- ai/mcp-assistant --- 2026.10.08
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
//
// Postgres savepoints, schema compatibility, live context, locks and lookup precedence.
//
// 20261001 CDX/LH Created.
// 20261002 CL/LH An interrupted step (table without indexes) is repaired by the next run.
// 20261002 CDX/LH Cover companion foundation contracts.
// 20261002 CDX/LH Cover companion foundation contracts.
// 20261004 CDX/LH Add companion part 2 foundation contracts.
// 20261008 CL/LH Cover additive mobile Assist rights and family migration.

declare(strict_types=1);

require_once __DIR__ . '/SaldiRuntimeDbTestCase.php';

final class SaldiRuntimeDbTest extends SaldiRuntimeDbTestCase
{
    public function testSavepointsAndInsertIgnorePreserveOuterTransaction(): void
    {
        $db = $this->env->db;
        $db->exec('CREATE TABLE runtime_test_rows (id serial PRIMARY KEY, label text UNIQUE)');
        $db->transaction(function () use ($db): void {
            self::assertTrue($db->inTransaction());
            $id = $db->insert('runtime_test_rows', ['label' => 'outer']);
            self::assertNull($db->insertIgnore('runtime_test_rows', ['label' => 'outer'], ['label']));
            try {
                $db->transaction(function () use ($db): void {
                    $db->insert('runtime_test_rows', ['label' => 'inner']);
                    $db->transaction(fn() => $db->insert('runtime_test_rows', ['label' => 'nested']));
                    throw new RuntimeException('rollback inner');
                });
            } catch (RuntimeException $e) {
                self::assertSame('rollback inner', $e->getMessage());
            }
            self::assertSame($id, (int)$db->value('SELECT id FROM runtime_test_rows WHERE label = ?', ['outer']));
            self::assertSame(1, (int)$db->value('SELECT COUNT(*) FROM runtime_test_rows'));
            $db->transaction(fn() => $db->insert('runtime_test_rows', ['label' => 'committed inner']));
        });
        self::assertFalse($db->inTransaction());
        self::assertSame(2, (int)$db->value('SELECT COUNT(*) FROM runtime_test_rows'));
        try {
            $db->transaction(function () use ($db): void {
                $db->insert('runtime_test_rows', ['label' => 'rolled outer']);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }
        self::assertSame(2, (int)$db->value('SELECT COUNT(*) FROM runtime_test_rows'));
        $this->expectException(LogicException::class);
        $db->lock('outside');
    }

    public function testSchemaAllTablesWatermarkAndOneSelectReplay(): void
    {
        $db = $this->env->db;
        foreach (['agent_grants' => 'token_hash', 'inbox_items' => 'open_key', 'decision_log' => 'idempotency_key', 'tool_calls' => 'args_hash', 'assistant_memory' => 'retracted_at', 'automation_jobs' => 'lease_token', 'posting_rules' => 'match_type', 'assist_events' => 'event', 'automation_state' => 'state_key', 'bank_import_lines' => 'line_hash'] as $table => $column) {
            self::assertTrue($db->hasTable($table));
            self::assertTrue($db->hasColumn($table, $column));
        }
        self::assertSame('s93_agent_grants_family_id', $db->value("SELECT var_value FROM settings WHERE var_name = 'plan_updates_last_step'"));
        $selects = 0;
        $steps = [];
        foreach (saldi_plan_update_steps() as $name => $step) {
            $steps[$name] = fn($type) => $step($type, fn($sql) => self::fail('No writes expected'), fn($sql) => self::fail('No step queries expected'));
        }
        $result = plan_updates_run($steps, $db->dbType(), function ($sql) use ($db, &$selects) {
            $selects++;
            return $db->one($sql);
        }, fn($sql) => self::fail('No watermark writes expected'));
        self::assertSame(1, $selects);
        self::assertSame([], $result['applied']);
        saldi_runtime_ensure_schema($db);
    }

    public function testTableLeftWithoutIndexesIsRepairedByTheNextRun(): void
    {
        $fresh = new SaldiRuntimeTestEnv(false);
        try {
            $db = $fresh->db;
            // A login run that died after CREATE TABLE: only the table statements landed, no watermark.
            $steps = saldi_plan_update_steps();
            foreach (['s90_inbox_items', 's90_automation_jobs'] as $name) {
                $steps[$name]($db->dbType(), function (string $sql) use ($db): void {
                    if (str_starts_with($sql, 'CREATE TABLE')) {
                        $db->exec($sql);
                    }
                }, fn(string $sql) => $db->one($sql));
            }
            $indexes = fn(string $table): array => array_column($db->all('SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? ORDER BY indexname', [$table]), 'indexname');
            self::assertSame(['inbox_items_pkey'], $indexes('inbox_items'));
            saldi_runtime_ensure_schema($db);
            self::assertSame(['inbox_items_open_key_idx', 'inbox_items_owner_idx', 'inbox_items_pkey', 'inbox_items_request_key_idx', 'inbox_items_status_idx'], $indexes('inbox_items'));
            self::assertSame(['automation_jobs_dedupe_idx', 'automation_jobs_due_idx', 'automation_jobs_pkey'], $indexes('automation_jobs'));
            self::assertSame('s93_agent_grants_family_id', $db->value("SELECT var_value FROM settings WHERE var_name = 'plan_updates_last_step'"));
        } finally {
            $fresh->close();
        }
    }

    public function testIncompatibleTableRollsBackSchemaAndWatermark(): void
    {
        $fresh = new SaldiRuntimeTestEnv(false);
        try {
            $fresh->db->exec('CREATE TABLE inbox_items (id serial PRIMARY KEY)');
            $this->error('UNAVAILABLE', fn() => saldi_runtime_ensure_schema($fresh->db));
            self::assertFalse($fresh->db->hasTable('agent_grants'));
            self::assertNull($fresh->db->value("SELECT var_value FROM settings WHERE var_name = 'plan_updates_last_step'"));
            $fresh->db->exec('DROP TABLE inbox_items');
            saldi_runtime_ensure_schema($fresh->db);
            self::assertTrue($fresh->db->hasColumn('inbox_items', 'open_key'));
        } finally {
            $fresh->close();
        }
    }

    public function testLiveUsersFiscalYearAndSessionContext(): void
    {
        $db = $this->env->db;
        $ctx = $this->env->ctx();
        self::assertSame("O'Brien", $ctx->username);
        self::assertSame(4, $ctx->fiscalYear);
        $db->exec("UPDATE brugere SET regnskabsaar = 999, rettigheder = '2' WHERE id = ?", [$ctx->userId]);
        self::assertSame(4, $this->env->ctx()->fiscalYear);
        self::assertSame('2', $this->env->ctx()->rights);
        $db->exec("UPDATE grupper SET box10 = 'on' WHERE art = 'RA' AND kodenr = 4");
        self::assertSame(3, $this->env->ctx()->fiscalYear);
        $this->error('PERMISSION_DENIED', fn() => saldi_runtime_context_for_user($db, $this->env->regnskab, 0, 'user:0'));
        $this->error('PERMISSION_DENIED', fn() => saldi_runtime_context_for_user($db, $this->env->regnskab, $ctx->userId, 'rule:low_stock'));
        $GLOBALS['bruger_id'] = $ctx->userId;
        $GLOBALS['db_id'] = $ctx->regnskabId;
        $GLOBALS['sprog_id'] = 3;
        $session = saldi_runtime_context_for_session();
        self::assertSame(3, $session['ctx']->languageId);
        $session['db']->close();
        unset($GLOBALS['bruger_id'], $GLOBALS['db_id'], $GLOBALS['sprog_id']);
    }

    public function testTenantRegistryRejectsClosedAndMaster(): void
    {
        $this->error('UNAUTHENTICATED', fn() => saldi_runtime_tenant(1));
        $entry = saldi_runtime_tenant_by_db($this->env->name);
        self::assertSame($this->env->name, $entry['db']->name());
        $entry['db']->close();
        $this->env->master->exec("UPDATE regnskab SET lukket = 'on' WHERE id = ?", [$this->env->regnskab['id']]);
        $this->error('UNAUTHENTICATED', fn() => saldi_runtime_tenant($this->env->regnskab['id']));
        $this->env->master->exec("UPDATE regnskab SET lukket = '', lukkes = ? WHERE id = ?", ['2000-01-01', $this->env->regnskab['id']]);
        $this->error('UNAUTHENTICATED', fn() => saldi_runtime_tenant($this->env->regnskab['id']));
    }

    public function testTextTenantCsvFallbackAndReadOnly(): void
    {
        $db = $this->env->db;
        $count = (int)$db->value('SELECT COUNT(*) FROM tekster');
        $db->insert('tekster', ['tekst_id' => 5741, 'sprog_id' => 2, 'tekst' => 'Tenant override']);
        self::assertSame('Tenant override', SaldiText::get($db, 5741, 2, 'fallback'));
        self::assertSame('fallback', SaldiText::get($db, 99999999, 1, 'fallback'));
        self::assertSame($count + 1, (int)$db->value('SELECT COUNT(*) FROM tekster'));
        $db->exec('DROP TABLE tekster');
        self::assertSame('Date', SaldiText::get($db, 5741, 2, 'fallback'));
    }

    public function testAdvisoryLockBlocksIndependentConnection(): void
    {
        $db = $this->env->db;
        $other = SaldiDb::connect(saldi_runtime_config(), $db->name());
        try {
            $db->transaction(function () use ($db, $other): void {
                $db->lock('runtime_lock');
                self::assertFalse((bool)$other->value('SELECT pg_try_advisory_lock(hashtext(?))', ['runtime_lock']));
            });
            self::assertTrue((bool)$other->value('SELECT pg_try_advisory_lock(hashtext(?))', ['runtime_lock']));
            $other->value('SELECT pg_advisory_unlock(hashtext(?))', ['runtime_lock']);
        } finally {
            $other->close();
        }
    }

    public function testRuntimeUpToDateActuallyExecutesOneSelect(): void
    {
        $cfg = saldi_runtime_config();
        $pdo = new class ('pgsql:host=' . $cfg['host'] . ';port=' . $cfg['port'] . ';dbname=' . $this->env->name, $cfg['user'], $cfg['password']) extends PDO {
            public array $statements = [];
            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                $this->statements[] = $query;
                return parent::prepare($query, $options);
            }
        };
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $property = new ReflectionProperty(SaldiDb::class, 'pdo');
        $property->setValue($this->env->db, $pdo);
        saldi_runtime_ensure_schema($this->env->db);
        self::assertCount(1, $pdo->statements);
        self::assertStringContainsString('plan_updates_last_step', $pdo->statements[0]);
    }

    public function testWatermarkWriteFailureRollsBackRuntimeMigration(): void
    {
        $fresh = new SaldiRuntimeTestEnv(false);
        try {
            $fresh->db->exec("ALTER TABLE settings ADD CONSTRAINT runtime_reject_watermark CHECK (var_name <> 'plan_updates_last_step')");
            $this->error('UNAVAILABLE', fn() => saldi_runtime_ensure_schema($fresh->db));
            self::assertFalse($fresh->db->hasTable('agent_grants'));
            self::assertNull($fresh->db->value("SELECT id FROM settings WHERE var_name = 'plan_updates_last_step'"));
            $fresh->db->exec('ALTER TABLE settings DROP CONSTRAINT runtime_reject_watermark');
            saldi_runtime_ensure_schema($fresh->db);
            self::assertTrue($fresh->db->hasTable('automation_jobs'));
        } finally {
            $fresh->close();
        }
    }
    public function testContextTokenEndpointRereadsRawUsernameFromOnline(): void
    {
        $ctx = $this->env->ctx();
        $session = $this->env->session($ctx);
        $code = 'require ' . var_export(__DIR__ . '/../../../includes/saldi_assist_context_token.php', true) . ';'
            . <<<'PHP'
function db_select($sql, $trace) { return false; }
function db_fetch_array($row) { return false; }
$input = json_decode(stream_get_contents(STDIN), true);
$s_id = $input['session'];
$db = $input['db'];
$db_id = $input['regnskab_id'];
$brugernavn = "O''Brien";
$rettigheder = '1'; $languageID = 1; $version = '5.0.0'; $menu = 'S';
$SALDI_ASSIST_KEYS = [$SALDI_ASSIST_KID => bin2hex(random_bytes(32))];
$_GET['embed_session'] = bin2hex(random_bytes(16));
saldi_assist_token_endpoint();
PHP;
        $run = function () use ($code, $session): array {
            $pipes = [];
            $process = proc_open([PHP_BINARY, '-r', $code], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            fwrite($pipes[0], json_encode(['session' => $session, 'db' => $this->env->name, 'regnskab_id' => $this->env->regnskab['id']]));
            fclose($pipes[0]);
            $out = stream_get_contents($pipes[1]);
            self::assertSame('', stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process));
            return json_decode($out, true, 512, JSON_THROW_ON_ERROR);
        };
        $response = $run();
        $parts = explode('.', $response['token']);
        $claims = json_decode(base64_decode(strtr($parts[2], '-_', '+/')), true);
        self::assertSame($ctx->userHash(), $claims['user_hash']);
        self::assertSame($ctx->tenantHash(), $claims['tenant_hash']);
        self::assertSame($ctx->installId, $claims['install']);
        $this->env->master->exec('DELETE FROM online WHERE session_id = ?', [$session]);
        self::assertSame(['error' => 'no_session'], $run());
    }
}
