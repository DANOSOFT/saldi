<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/DbModificationTrailTest.php --- ver 5.0.0 --- 2026.10.07
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
// 20261007 CDX/LH Verify DML trails, rendered parameters and db_modify byte identity.
// 20261007 CDX/LH Regress registry writes and mismatched tenant logging contexts.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../characterization/support/ModificationTrail.php';
require_once __DIR__ . '/../../includes/db_query.php';
require_once __DIR__ . '/../../includes/kreditor/creditorReceive.php';
require_once __DIR__ . '/../../includes/finans/bankLines.php';
require_once __DIR__ . '/../../includes/finans/kladdeJournal.php';
require_once __DIR__ . '/../../includes/sager/loenTimer.php';
require_once __DIR__ . '/../../includes/docsIncludes/poolAttach.php';

final class DbModificationTrailTest extends TestCase
{
    private static $connection;
    private array $savedGlobals;
    private array $savedServer;
    private string $cwd;

    public static function setUpBeforeClass(): void
    {
        if ($reason = RestApiEnv::unavailableReason()) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        self::$connection = RestApiEnv::connect(RestApiEnv::testDb());
        RestApiEnv::rows(self::$connection, "CREATE TABLE inta_trail (id serial PRIMARY KEY, note text CHECK(note <> 'reject'), other text)");
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$connection) {
            pg_close(self::$connection);
            $directory = dirname(ModificationTrail::path(RestApiEnv::testDb()));
            if (is_dir($directory)) {
                foreach (new FilesystemIterator($directory) as $file) {
                    if ($file->isFile()) { unlink($file->getPathname()); }
                }
                rmdir($directory);
            }
        }
        RestApiEnv::teardownTenant();
    }

    protected function setUp(): void
    {
        $this->savedGlobals = [];
        foreach (['connection','db','sqdb','db_type','db_skriv_id','brugernavn','webservice','db_modify_fejl'] as $key) {
            $this->savedGlobals[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
        }
        $this->savedServer = $_SERVER;
        $this->cwd = getcwd();
        chdir(dirname(__DIR__, 2));
        $_SERVER['REQUEST_URI'] = '/saldi/index.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $GLOBALS['connection'] = self::$connection;
        $GLOBALS['db'] = RestApiEnv::testDb();
        $GLOBALS['sqdb'] = RestApiEnv::masterDb();
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['db_skriv_id'] = 2;
        $GLOBALS['brugernavn'] = 'trail-test';
        $GLOBALS['webservice'] = true;
        $directory = dirname(ModificationTrail::path(RestApiEnv::testDb()));
        if (!is_dir($directory)) { mkdir($directory, 0777, true); }
        RestApiEnv::rows(self::$connection, 'DELETE FROM inta_trail');
    }

    protected function tearDown(): void
    {
        foreach ($this->savedGlobals as $key => [$exists, $value]) {
            if ($exists) { $GLOBALS[$key] = $value; } else { unset($GLOBALS[$key]); }
        }
        $_SERVER = $this->savedServer;
        chdir($this->cwd);
    }

    public function testDbSelectLogsCommentPrefixedDmlAndNeverSelectForUpdate(): void
    {
        $before = ModificationTrail::read(RestApiEnv::testDb());
        $sql = " \n-- web write\n/* outer /* nested */ end */ InSeRt INTO inta_trail(note) VALUES ('select-write') RETURNING id";
        $row = db_fetch_array(db_select($sql, 'db-select-trail'));
        $this->assertGreaterThan(0, (int)$row['id']);
        $added = substr(ModificationTrail::read(RestApiEnv::testDb()), strlen($before));
        $this->assertStringContainsString(': db-select-trail: 2' . "\n" . $sql . ";\n", $added);
        foreach (["-- comment\nUPDATE inta_trail SET note='changed'", '/* comment */ DELETE FROM inta_trail'] as $write) {
            $before = ModificationTrail::read(RestApiEnv::testDb());
            db_select($write, 'db-select-trail');
            $this->assertStringContainsString($write . ";\n", substr(ModificationTrail::read(RestApiEnv::testDb()), strlen($before)));
        }
        $before = ModificationTrail::read(RestApiEnv::testDb());
        db_select('SELECT * FROM inta_trail FOR UPDATE', 'read-trail');
        db_select("/* INSERT */ SELECT 'UPDATE DELETE'", 'read-trail');
        $this->assertSame($before, ModificationTrail::read(RestApiEnv::testDb()));
    }

    /** @return array<string,array{string}> */
    public static function helpers(): array
    {
        return array_combine(['creditor_receive_query','bank_line_query','kladde_journal_query','loen_timer_query','pool_attach_query'],
            array_map(static fn(string $name): array => [$name], ['creditor_receive_query','bank_line_query','kladde_journal_query','loen_timer_query','pool_attach_query']));
    }

    #[DataProvider('helpers')]
    public function testCheckedHelpersRenderSuccessAndFailureAndSkipReads(string $helper): void
    {
        $before = ModificationTrail::read(RestApiEnv::testDb());
        $text = "Supplier's \\ path\nreceipt; $1";
        $sql = 'INSERT INTO inta_trail(note,other) VALUES ($1,$2) RETURNING id';
        $params = [$text, null];
        if ($helper === 'pool_attach_query') {
            $sql = 'INSERT INTO inta_trail(note,other) VALUES (' . pg_escape_literal(self::$connection, $text) . ',NULL) RETURNING id';
            $params = [];
        }
        $rows = $helper($sql, $params);
        $this->assertCount(1, $rows);
        $expected = "INSERT INTO inta_trail(note,other) VALUES (" . pg_escape_literal(self::$connection, $text) . ",NULL) RETURNING id;\n";
        $added = substr(ModificationTrail::read(RestApiEnv::testDb()), strlen($before));
        $this->assertStringContainsString($expected, $added);
        $this->assertSame($text, RestApiEnv::rows(self::$connection, 'SELECT note FROM inta_trail')[0]['note']);
        $before = ModificationTrail::read(RestApiEnv::testDb());
        $helper('SELECT * FROM inta_trail FOR UPDATE');
        $this->assertSame($before, ModificationTrail::read(RestApiEnv::testDb()));
        try {
            $helper("INSERT INTO inta_trail(note) VALUES ('reject')");
            $this->fail('A rejected write must keep the helper exception');
        } catch (RuntimeException $error) {
            $added = substr(ModificationTrail::read(RestApiEnv::testDb()), strlen($before));
            $this->assertStringContainsString("INSERT INTO inta_trail(note) VALUES ('reject');\n", $added);
            $this->assertStringContainsString("-- Fejl!! INSERT INTO inta_trail(note) VALUES ('reject') | ERROR:", $added);
            $this->assertStringContainsString('inta_trail_note_check', $added);
        }
    }

    public function testLoenSeddelFailureLogsTheLegacySourceOnceAfterExecution(): void
    {
        RestApiEnv::rows(self::$connection, 'BEGIN');
        RestApiEnv::rows(self::$connection, "ALTER TABLE loen ADD CONSTRAINT inta_loen_reject CHECK(tekst <> 'inta-reject')");
        $before = ModificationTrail::read(RestApiEnv::testDb());
        try {
            loen_seddel_insert(['art'=>'timer','tekst'=>'inta-reject'], 'web-loen-source');
            $this->fail('Rejected payroll insertion must throw');
        } catch (RuntimeException $error) {
            $added = substr(ModificationTrail::read(RestApiEnv::testDb()), strlen($before));
            $this->assertStringContainsString(": web-loen-source: 2\nINSERT INTO loen (art,tekst) VALUES ('timer','inta-reject') RETURNING id;\n", $added);
            $this->assertStringContainsString("-- Fejl!! INSERT INTO loen (art,tekst) VALUES ('timer','inta-reject') RETURNING id | ERROR:", $added);
            $this->assertStringContainsString('inta_loen_reject', $added);
            $this->assertSame(1, substr_count($added, ': web-loen-source: 2'));
        } finally {
            RestApiEnv::rows(self::$connection, 'ROLLBACK');
        }
    }

    public function testMobileSchemaExecuteAndRowsLogDmlOnce(): void
    {
        $store = new MobileSchema(self::$connection);
        $before = ModificationTrail::read(RestApiEnv::testDb());
        $store->execute("INSERT INTO inta_trail(note) VALUES ('schema-execute')");
        $rows = $store->rows("UPDATE inta_trail SET note='schema-rows' RETURNING id");
        $this->assertCount(1, $rows);
        $added = substr(ModificationTrail::read(RestApiEnv::testDb()), strlen($before));
        $this->assertSame(1, substr_count($added, "INSERT INTO inta_trail(note) VALUES ('schema-execute');\n"));
        $this->assertSame(1, substr_count($added, "UPDATE inta_trail SET note='schema-rows' RETURNING id;\n"));
        $before = ModificationTrail::read(RestApiEnv::testDb());
        $store->rows('SELECT * FROM inta_trail FOR UPDATE');
        $store->execute('CREATE TEMP TABLE inta_trail_ddl (id int)');
        $this->assertSame($before, ModificationTrail::read(RestApiEnv::testDb()));
    }

    public function testMobileSchemaLogsCatalogueRetentionCteButSkipsReadCte(): void
    {
        $store = new MobileSchema(self::$connection);
        $store->execute("INSERT INTO inta_trail(note) VALUES ('expired')");
        $before = ModificationTrail::read(RestApiEnv::testDb());
        $sql = "WITH removed AS (DELETE FROM inta_trail WHERE note='expired' RETURNING id) SELECT id FROM removed";
        $this->assertCount(1, $store->rows($sql));
        $added = substr(ModificationTrail::read(RestApiEnv::testDb()), strlen($before));
        $this->assertStringContainsString($sql . ";\n", $added);
        $before = ModificationTrail::read(RestApiEnv::testDb());
        $store->rows("WITH read_rows AS (SELECT 'DELETE FROM inta_trail' AS note) SELECT * FROM read_rows FOR UPDATE");
        $this->assertSame($before, ModificationTrail::read(RestApiEnv::testDb()));
    }

    /** @return array<string,array{bool}> */
    public static function foreignConnections(): array
    {
        return ['registry' => [true], 'mismatched-database' => [false]];
    }

    #[DataProvider('foreignConnections')]
    public function testMobileSchemaForeignWritesNeverEnterActiveTenantTrail(bool $registry): void
    {
        $foreign = RestApiEnv::connect(RestApiEnv::masterDb());
        try {
            RestApiEnv::rows($foreign, 'CREATE TEMP TABLE inta_registry_trail (note text)');
            if (!$registry) {
                $GLOBALS['sqdb'] = 'other_registry';
            }
            $this->assertSame(self::$connection, $GLOBALS['connection']);
            $this->assertSame(RestApiEnv::testDb(), $GLOBALS['db']);
            $this->assertGreaterThan(1, $GLOBALS['db_skriv_id']);
            $tenantStore = new MobileSchema(self::$connection);
            $tenantStore->execute("INSERT INTO inta_trail(note) VALUES ('tenant-before-registry')");
            $before = ModificationTrail::read(RestApiEnv::testDb());
            $this->assertStringContainsString('tenant-before-registry', $before);
            $store = new MobileSchema($foreign);
            $store->execute("INSERT INTO inta_registry_trail(note) VALUES ('registry-execute')");
            $this->assertSame([['note' => 'registry-rows']],
                $store->rows("UPDATE inta_registry_trail SET note='registry-rows' RETURNING note"));
            $this->assertSame([['note' => 'registry-rows']],
                $store->rows('WITH removed AS (DELETE FROM inta_registry_trail RETURNING note) SELECT note FROM removed'));
            $this->assertSame([], $store->rows('SELECT * FROM inta_registry_trail'));
            $this->assertSame($before, ModificationTrail::read(RestApiEnv::testDb()));
            $tenantStore->execute("INSERT INTO inta_trail(note) VALUES ('tenant-after-registry')");
            ModificationTrail::assertWrite(RestApiEnv::testDb(), $before,
                "INSERT INTO inta_trail\\(note\\) VALUES \\('tenant-after-registry'\\)");
        } finally {
            pg_close($foreign);
        }
    }

    public function testRendererDoesNotReplaceQuotedOrCommentedPlaceholders(): void
    {
        $sql = <<<'SQL'
INSERT INTO inta_trail(note,other) VALUES ($1,$10) -- $2
/* outer /* $3 */ $6 */ RETURNING '$1', E'escaped \' $2', "$3", $$ $4 $$, $tag$ $5 $tag$, $2, $1
SQL;
        $params = ["O'Neil $10", null, 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];
        $expected = <<<'SQL'
INSERT INTO inta_trail(note,other) VALUES ('O''Neil $10','ten') -- $2
/* outer /* $3 */ $6 */ RETURNING '$1', E'escaped \' $2', "$3", $$ $4 $$, $tag$ $5 $tag$, NULL, 'O''Neil $10'
SQL;
        $this->assertSame($expected, db_modify_render_sql(self::$connection, $sql, $params));
    }

    public function testDbModifySuccessAndFailureAreByteIdenticalToFrozenImplementation(): void
    {
        // Isolate both implementations with the same clock and database result; retain real file appends.
        // This also exercises the disabled-id/master guards and the legacy unconditional error block.
        $worker = <<<'PHP'
$root = $argv[1];
$dir = sys_get_temp_dir() . '/saldi-trail-' . bin2hex(random_bytes(8));
mkdir($dir);
chdir($dir);
mkdir('temp');
$source = file_get_contents($root . '/includes/db_query.php');
$stubs = <<<'STUBS'
function date($format) { return $format === 'U' ? '1791374400' : '2026-10-07 12:00:00'; }
function injecttjek($sql) { return $sql; }
function get_relative() { return ''; }
function db_query_connection($global) { return null; }
function pg_query($connection, $sql) { return $GLOBALS['trail_ok']; }
function pg_last_error($connection) { return 'test failure'; }
function db_log_append($path, $lines, $mode='a') { return file_put_contents($path, implode('',(array)$lines), $mode==='a' ? FILE_APPEND : 0); }
STUBS;
eval('namespace SaldiTrailBaseline;' . $stubs);
eval('namespace SaldiTrailCurrent;' . $stubs);
require $root . '/tests/restapi/fixtures/modify-trail/legacy_db_modify.php';
foreach (['db_modify','db_modify_trail'] as $name) {
    preg_match('/^\tfunction ' . $name . '\([^\n]+[\s\S]*?^\t}\n/m', $source, $matches);
    eval('namespace SaldiTrailCurrent;' . $matches[0]);
}
$results = [];
try {
    foreach ([true,false] as $trail_ok) {
        foreach ([0,1,2] as $db_skriv_id) {
            foreach (['registry','tenant'] as $target) {
                foreach (['modify','includes/opdat'] as $spor) {
                    $db = $target;
                    $sqdb = 'registry';
                    $db_type = 'postgresql';
                    $brugernavn = 'trail-user';
                    $connection = null;
                    $webservice = true;
                    if (!is_dir('temp/' . $db)) { mkdir('temp/' . $db); }
                    file_put_contents('temp/' . $db . '/modifyfejl.txt', dateValue());
                    file_put_contents('temp/' . $db . '/opdatfejl.txt', dateValue());
                    $outputs = [];
                    foreach (['SaldiTrailBaseline','SaldiTrailCurrent'] as $namespace) {
                        $log = 'temp/' . $db . '/.ht_modify.log';
                        if (is_file($log)) { unlink($log); }
                        $db_modify_fejl = false;
                        $return = ($namespace . '\\db_modify')("UPDATE inta_trail SET note='literal'", $spor);
                        $outputs[] = [is_file($log) ? file_get_contents($log) : '', $return, $db_modify_fejl];
                    }
                    $results[] = ['case'=>[$trail_ok,$db_skriv_id,$target,$spor],'old'=>$outputs[0],'new'=>$outputs[1]];
                }
            }
        }
    }
    echo json_encode($results, JSON_THROW_ON_ERROR);
} finally {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    chdir(sys_get_temp_dir());
    rmdir($dir);
}
function dateValue() { return '1791374400'; }
PHP;
        $process = proc_open([PHP_BINARY, '-r', $worker, dirname(__DIR__, 2)],
            [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        $this->assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $stderr . $stdout);
        $this->assertSame('', $stderr);
        $results = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(24, $results);
        foreach ($results as $result) {
            $this->assertSame($result['old'], $result['new'], json_encode($result['case']));
        }
    }
}
