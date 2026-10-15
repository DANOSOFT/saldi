<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/runtime/SaldiSchemaStepTest.test.php --- ai/mcp-assistant --- 2026.10.08
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
// DDL portability and legacy login migration locking/failure contracts.
//
// 20261001 CDX/LH Created.
// 20261002 CL/LH Existing tables are checked for missing indexes; login steps run in a transaction on PostgreSQL.
// 20261002 CDX/LH Cover companion foundation contracts.
// 20261004 CDX/LH Add companion part 2 foundation contracts.
// 20261008 CL/LH Cover additive mobile Assist rights and family migration.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/SaldiRuntimeTestEnv.php';

final class SaldiSchemaStepTest extends TestCase
{
    public function testBothDatabaseDdlsAndSignatures(): void
    {
        foreach (['postgresql', 'mysql'] as $type) {
            $sqls = [];
            foreach (saldi_plan_update_steps() as $step) {
                $step($type, function (string $sql) use (&$sqls): void {
                    $sqls[] = $sql;
                }, fn(string $sql) => null);
            }
            self::assertCount(10, array_filter($sqls, fn($sql) => str_starts_with($sql, 'CREATE TABLE')));
            foreach ($sqls as $sql) {
                self::assertStringNotContainsString(';', $sql);
                self::assertStringNotContainsString(' like ', strtolower($sql));
                self::assertStringNotContainsString('plpgsql', strtolower($sql));
                if ($type === 'mysql') {
                    self::assertStringNotContainsString('serial', $sql);
                    self::assertStringNotContainsString('timestamp', $sql);
                    self::assertStringNotContainsString('numeric', $sql);
                }
            }
        }
        $steps = saldi_plan_update_steps();
        self::assertSame(['s90_agent_grants', 's90_inbox_items', 's90_decision_log', 's90_tool_calls', 's90_assistant_memory', 's90_automation_jobs', 's91_posting_rules', 's91_assist_events', 's91_automation_state', 's92_bank_import_lines', 's93_agent_grants_family_id'], array_keys($steps));
        $indexCounts = ['s90_agent_grants' => 2, 's90_inbox_items' => 4, 's90_decision_log' => 2, 's90_tool_calls' => 1, 's90_assistant_memory' => 1, 's90_automation_jobs' => 2, 's91_posting_rules' => 1, 's91_assist_events' => 1, 's91_automation_state' => 0, 's92_bank_import_lines' => 2];
        foreach (array_diff_key($steps, ['s93_agent_grants_family_id' => true]) as $name => $step) {
            foreach (['postgresql' => 'pg_indexes', 'mysql' => 'information_schema.statistics'] as $type => $catalog) {
                $selects = [];
                $step($type, fn($sql) => self::fail('Compatible tables need no writes'), function ($sql) use (&$selects) {
                    $selects[] = $sql;
                    return ['exists' => 1];
                });
                self::assertCount(2 + $indexCounts[$name], $selects);
                self::assertStringContainsString($type === 'mysql' ? 'DATABASE()' : 'current_schema()', $selects[0]);
                self::assertCount($indexCounts[$name], array_filter($selects, fn($sql) => str_contains($sql, $catalog)));
            }
            // Table and signature present but indexes missing (a run that stopped after CREATE TABLE): create only those.
            $writes = [];
            $step('postgresql', function ($sql) use (&$writes): void {
                $writes[] = $sql;
            }, fn($sql) => str_contains($sql, 'information_schema') || str_contains($sql, 'inbox_items_open_key_idx') ? ['exists' => 1] : null);
            self::assertCount($name === 's90_inbox_items' ? 3 : $indexCounts[$name], $writes);
            foreach ($writes as $sql) {
                self::assertMatchesRegularExpression('/^CREATE (UNIQUE )?INDEX /', $sql);
            }
        }
        $this->expectExceptionMessage('incompatible existing table inbox_items');
        $steps['s90_inbox_items']('postgresql', fn($sql) => null, fn($sql) => str_contains($sql, 'information_schema.tables') ? ['exists' => 1] : null);
    }

    public function testCompanionSignaturesFailClosedOnBothEngines(): void
    {
        foreach (['posting_rules' => 'match_type', 'assist_events' => 'event', 'automation_state' => 'state_key'] as $table => $signature) {
            foreach (['postgresql', 'mysql'] as $type) {
                $queries = [];
                try {
                    saldi_plan_update_steps()['s91_' . $table](
                        $type,
                        fn($sql) => self::fail('Incompatible tables must never be written'),
                        function ($sql) use (&$queries) {
                            $queries[] = $sql;
                            return str_contains($sql, 'information_schema.tables') ? ['exists' => 1] : null;
                        }
                    );
                    self::fail('Missing signature accepted');
                } catch (RuntimeException $error) {
                    self::assertSame('incompatible existing table ' . $table, $error->getMessage());
                    self::assertStringContainsString("column_name = '" . $signature . "'", $queries[1]);
                }
            }
        }
    }

    private function login(string $mode): array
    {
        $source = '$mode = ' . var_export($mode, true) . ';' . <<<'PHP'
$db_type = 'postgresql';
$webservice = false;
$selects = []; $writes = []; $writeModes = []; $watermark = $mode === 'current' ? 's93_agent_grants_family_id' : null;
function db_select($sql, $trace) {
    global $selects, $mode, $watermark;
    $selects[] = $sql;
    if (str_contains($sql, 'plan_updates_last_step')) { return $watermark === null ? null : ['var_value' => $watermark]; }
    if (str_contains($sql, 'pg_try_advisory_lock')) { return ['acquired' => $mode !== 'busy']; }
    return null;
}
function db_fetch_array($row) { return $row; }
function db_modify($sql, $trace) {
    global $writes, $mode, $watermark, $writeModes, $webservice;
    $writes[] = $sql; $writeModes[] = $webservice;
    if ($mode === 'fail' && str_starts_with($sql, 'CREATE TABLE inbox_items')) { return '1 failed'; }
    if (preg_match('/s9[0123]_[a-z_]+/', $sql, $m) && str_contains($sql, 'plan_updates_last_step')) { $watermark = $m[0]; }
    return '0 accepted';
}
PHP;
        $source .= 'include ' . var_export(__DIR__ . '/../../../includes/planUpdates.php', true) . ';';
        $source .= 'echo json_encode(compact("selects", "writes", "writeModes", "watermark", "webservice"));';
        $pipes = [];
        $process = proc_open([PHP_BINARY, '-r', $source], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process));
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testLoginCurrentCostsOneSelectBusySkipsAndFailureUnlocks(): void
    {
        $current = $this->login('current');
        self::assertCount(1, $current['selects']);
        self::assertSame([], $current['writes']);
        $busy = $this->login('busy');
        self::assertSame([], $busy['writes']);
        self::assertCount(2, $busy['selects']);
        foreach (['fresh', 'fail'] as $mode) {
            $run = $this->login($mode);
            // Each PostgreSQL step is one transaction, so a step never lands half done.
            $begin = array_search('BEGIN', $run['writes'], true);
            self::assertNotFalse($begin);
            self::assertStringStartsWith('CREATE TABLE agent_grants', $run['writes'][$begin + 1]);
            if ($mode === 'fresh') {
                self::assertSame(11, count(array_keys($run['writes'], 'BEGIN', true)));
                self::assertSame(11, count(array_keys($run['writes'], 'COMMIT', true)));
                self::assertNotContains('ROLLBACK', $run['writes']);
            } else {
                $failed = array_search('CREATE TABLE inbox_items', array_map(fn($sql) => substr($sql, 0, 24), $run['writes']), true);
                self::assertSame('ROLLBACK', $run['writes'][$failed + 1]);
            }
            self::assertFalse($run['webservice']);
            self::assertNotContains(false, $run['writeModes']);
            self::assertStringContainsString('pg_advisory_unlock', end($run['selects']));
            self::assertSame($mode === 'fresh' ? 's93_agent_grants_family_id' : 's90_agent_grants', $run['watermark']);
        }
    }
}
