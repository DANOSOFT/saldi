<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileAttachmentIdentityTest.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Regress basename collisions, historical rename targets, repaired PDFs and concurrent legacy attachment.
// 20261007 CDX/LH Verify web document link/unlink and missing-pool modification trails.

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../restapi/models/attachment/DurableAttachmentModel.php';
require_once __DIR__ . '/../../includes/docsIncludes/poolFileTransitions.php';
require_once __DIR__ . '/../../includes/docsIncludes/attachmentPdfRenderer.php';
require_once __DIR__ . '/../../includes/db_query.php';

require_once __DIR__ . '/../characterization/support/ModificationTrail.php';

final class MobileAttachmentIdentityTest extends TestCase
{
    private static $conn;
    private static array $headers;
    private static array $other;
    private static array $finance;
    private static array $savedGlobals;
    private static array $savedServer;
    private static string $root;
    private const ROUTE = '/restapi/endpoints/v1/attachment/';

    public static function setUpBeforeClass(): void
    {
        if ($reason = RestApiEnv::unavailableReason()) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        self::$conn = RestApiEnv::connect(RestApiEnv::testDb());
        mobileTenantUpdates(self::$conn);
        RestApiEnv::rows(self::$conn, 'UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2', ['0000000000000000', RestApiEnv::user()]);
        foreach (['b5_other' => '0000000000000000', 'b5_finance' => '2000000000000000'] as $name => $rights) {
            RestApiEnv::rows(self::$conn, 'INSERT INTO brugere (brugernavn,kode,email,rettigheder,status) VALUES ($1,$2,$3,$4,true)',
                [$name, md5(TestCredentials::password($name)), $name . '@example.invalid', $rights]);
            $login = RestApiEnv::login($name, TestCredentials::password($name), RestApiEnv::accountOpen());
            $headers = ['Authorization: Bearer ' . $login['json']['data']['access_token']];
            if ($name === 'b5_other') {
                self::$other = $headers;
            } else {
                self::$finance = $headers;
            }
        }
        self::$headers = RestApiEnv::authHeaders();
        self::$savedServer = array_intersect_key($_SERVER, array_flip(['REQUEST_URI', 'REMOTE_ADDR']));
        $_SERVER['REQUEST_URI'] = '/saldi/index.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        self::$savedGlobals = [];
        foreach (['connection', 'db_type', 'db', 'db_skriv_id', 'sqdb', 'brugernavn'] as $key) {
            self::$savedGlobals[$key] = $GLOBALS[$key] ?? null;
        }
        $GLOBALS['connection'] = self::$conn;
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['db'] = RestApiEnv::testDb();
        $GLOBALS['db_skriv_id'] = 0;
        $GLOBALS['sqdb'] = RestApiEnv::masterDb();
        $GLOBALS['brugernavn'] = RestApiEnv::user();
        self::$root = dirname(poolPuljePath(RestApiEnv::testDb()));
        mkdir(self::$root, 0777, true);
        chmod(self::$root, 0777);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$conn) {
            pg_close(self::$conn);
            self::remove(self::$root);
            $temporary = dirname(__DIR__, 2) . '/temp/' . RestApiEnv::testDb();
            if (is_dir($temporary)) {
                self::remove($temporary);
            }
            unset($_SERVER['REQUEST_URI'], $_SERVER['REMOTE_ADDR']);
            $_SERVER = array_replace($_SERVER, self::$savedServer);
            foreach (self::$savedGlobals as $key => $value) {
                if ($value === null) {
                    unset($GLOBALS[$key]);
                } else {
                    $GLOBALS[$key] = $value;
                }
            }
        }
        RestApiEnv::teardownTenant();
    }

    private static function remove(string $path): void
    {
        foreach (new FilesystemIterator($path) as $file) {
            if ($file->isDir() && !$file->isLink()) {
                self::remove($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($path);
    }

    /** @return string Complete PDF with real xref and configurable page dimensions. */
    private function pdf(int $pages = 2, int $size = 100): string
    {
        $kids = implode(' ', array_map(fn($id) => "$id 0 R", range(3, 2 + $pages)));
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', "<< /Type /Pages /Kids [$kids] /Count $pages >>"];
        for ($i = 0; $i < $pages; $i++) {
            $streamId = 3 + $pages + $i;
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $size $size] /Resources << >> /Contents $streamId 0 R >>";
        }
        for ($i = 0; $i < $pages; $i++) {
            $color = $i % 2 === 0 ? '1 0 0' : '0 0 1';
            $stream = "$color rg 0 0 $size $size re f\n";
            $objects[] = '<< /Length ' . strlen($stream) . ">>\nstream\n$stream" . 'endstream';
        }
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n$object\nendobj\n";
        }
        $xref = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 $count\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        return $pdf . "trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    }

    /** @return array<string,mixed> A real durable upload through HTTP. */
    private function upload(?string $pdf = null): array
    {
        $pdf = $pdf ?? $this->pdf();
        $response = RestApiEnv::http('POST', self::ROUTE, ['id' => RequestId::resolve(null),
            'image_base64' => base64_encode($pdf)], self::$headers);
        $this->assertSame(201, $response['status'], $response['body']);
        return $response['json']['data'];
    }

    private function source(array $row): string
    {
        return poolPuljePath(RestApiEnv::testDb()) . '/' . $row['filename'];
    }

    private function download(int $id): array
    {
        return RestApiEnv::http('GET', self::ROUTE . '?id=' . $id . '&download=true', null, self::$headers);
    }

    public function testWebDocumentsLinkAndUnlinkAppendModificationTrail(): void
    {
        $row = $this->upload();
        $user = (int)RestApiEnv::loginData()['user']['id'];
        $lines = RestApiEnv::rows(self::$conn, 'INSERT INTO kassekladde(kladde_id,bilag) VALUES (98,981),(98,982) RETURNING id');
        $first = (int)$lines[0]['id'];
        $second = (int)$lines[1]['id'];
        $folder = self::$root . '/finance/98/' . $first;
        mkdir($folder, 0777, true);
        $destination = $folder . '/' . $row['filename'];
        PoolFileTransitions::insertDocument(RestApiEnv::testDb(), $this->source($row), $destination, 1, 'kassekladde', $first, $user);
        $original = RestApiEnv::rows(self::$conn, "SELECT id FROM documents WHERE source='kassekladde' AND source_id=$1", [$first])[0];
        $session = 'intadoc' . bin2hex(random_bytes(12));
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        RestApiEnv::rows($master, "INSERT INTO online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id)
            VALUES($1,$2,$3,$4,$5,1,$6,false,0)", [$session,RestApiEnv::user(),RestApiEnv::testDb(),RestApiEnv::pgUser(),str_repeat('9',50),(string)time()]);
        try {
            $before = ModificationTrail::read(RestApiEnv::testDb());
            $this->documentsPage(['source'=>'kassekladde','sourceId'=>$second,'kladde_id'=>98,'doLink'=>'1','linkDocId'=>$original['id']], $session);
            ModificationTrail::assertWrite(RestApiEnv::testDb(), $before, 'INSERT INTO documents\b');
            $copy = RestApiEnv::rows(self::$conn, "SELECT id FROM documents WHERE source='kassekladde' AND source_id=$1", [$second]);
            $this->assertCount(1, $copy);
            $before = ModificationTrail::read(RestApiEnv::testDb());
            $this->documentsPage(['source'=>'kassekladde','sourceId'=>$second,'kladde_id'=>98,'unlinkDoc'=>$copy[0]['id']], $session);
            ModificationTrail::assertWrite(RestApiEnv::testDb(), $before, 'DELETE FROM documents\b');
            $this->assertSame([], RestApiEnv::rows(self::$conn, 'SELECT id FROM documents WHERE id=$1', [$copy[0]['id']]));
        } finally {
            RestApiEnv::rows($master, 'DELETE FROM online WHERE session_id=$1', [$session]);
            pg_close($master);
        }
    }

    private function documentsPage(array $get, string $session): void
    {
        $worker = <<<'PHP'
error_reporting(E_ALL);
$_GET = json_decode($argv[1], true, 512, JSON_THROW_ON_ERROR);
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $_SERVER['PHP_SELF'] = '/saldi/includes/documents.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'trail-test';
ini_set('session.save_path', sys_get_temp_dir());
session_id($argv[2]);
chdir($argv[3] . '/includes');
require $argv[3] . '/includes/documents.php';
PHP;
        $process = proc_open([PHP_BINARY,'-r',$worker,json_encode($get, JSON_THROW_ON_ERROR),$session,dirname(__DIR__,2)],
            [0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        $this->assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $stderr . $stdout);
        $this->assertStringNotContainsString('Fatal error', $stdout . $stderr);
    }

    public function testWebPoolSyncLogsMissingFileTombstone(): void
    {
        require_once __DIR__ . '/../../includes/docsIncludes/docPool.php';
        $missing = RestApiEnv::rows(self::$conn, "INSERT INTO pool_files(filename,status,updated) VALUES ('inta-missing.pdf','ubehandlet',now()-interval '2 minutes') RETURNING id")[0];
        $before = ModificationTrail::read(RestApiEnv::testDb());
        $GLOBALS['db_skriv_id'] = 2;
        try {
            syncPuljeFilesToDatabase(dirname(self::$root), RestApiEnv::testDb());
            ModificationTrail::assertWrite(RestApiEnv::testDb(), $before, 'UPDATE pool_files\b');
            $this->assertSame('deleted', RestApiEnv::rows(self::$conn, 'SELECT status FROM pool_files WHERE id=$1', [$missing['id']])[0]['status']);
            $added = substr(ModificationTrail::read(RestApiEnv::testDb()), strlen($before));
            $this->assertStringContainsString("status='deleted'", $added);
        } finally {
            $GLOBALS['db_skriv_id'] = 0;
        }
    }

    public function test_legacy_basename_lookup_does_not_claim_a_voucher_file(): void
    {
        $name = 'NETS-' . bin2hex(random_bytes(4)) . '.pdf';
        $pool = poolPuljePath(RestApiEnv::testDb()) . '/' . $name;
        if (!is_dir(dirname($pool))) {
            mkdir(dirname($pool), 0777, true);
        }
        chmod(dirname($pool), 0777);
        file_put_contents($pool, $this->pdf());
        $id = (int)RestApiEnv::rows(self::$conn, "INSERT INTO pool_files(filename,subject) VALUES($1,$2) RETURNING id", [$name, 'Manual vendor edit'])[0]['id'];
        $folder = self::$root . '/finance/1/23';
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        $voucher = $folder . '/' . $name;
        file_put_contents($voucher, $this->pdf());
        $this->assertSame($id, (int)PoolFileTransitions::row(RestApiEnv::testDb(), $pool)['id']);
        $this->assertNull(PoolFileTransitions::row(RestApiEnv::testDb(), $voucher));
        PoolFileTransitions::attach(RestApiEnv::testDb(), $voucher, 1);
        $this->assertTrue(PoolFileTransitions::delete(RestApiEnv::testDb(), $voucher));
        $stored = RestApiEnv::rows(self::$conn, 'SELECT * FROM pool_files WHERE id=$1', [$id])[0];
        $this->assertSame('ubehandlet', $stored['status']);
        $this->assertSame('Manual vendor edit', $stored['subject']);
        $this->assertNull($stored['storage_path']);
        $this->assertFileExists($pool);
    }

    public function test_historical_names_are_immediately_free_and_old_aliases_allow_rename(): void
    {
        foreach (['attached', 'deleted', 'missing'] as $status) {
            $old = $this->upload();
            $original = $this->source($old);
            if ($status === 'attached') {
                $folder = self::$root . '/finance/history/' . $old['id'];
                mkdir($folder, 0777, true);
                $path = $folder . '/' . $old['filename'];
                PoolFileTransitions::move(RestApiEnv::testDb(), $original, $path);
                PoolFileTransitions::attach(RestApiEnv::testDb(), $path, 1);
            } elseif ($status === 'deleted') {
                PoolFileTransitions::delete(RestApiEnv::testDb(), $original);
            } else {
                unlink($original);
                PoolFileTransitions::missing('id=' . $old['id']);
            }
            $history = RestApiEnv::rows(self::$conn, 'SELECT * FROM pool_files WHERE id=$1', [$old['id']])[0];
            $this->assertStringStartsWith('.history-' . $old['id'] . '-', $history['filename']);
            $this->assertCount(0, RestApiEnv::rows(self::$conn, 'SELECT id FROM pool_files WHERE filename=$1', [$old['filename']]));
            // Simulate a historical row retained by the earlier deployment: move must free it too.
            RestApiEnv::rows(self::$conn, 'UPDATE pool_files SET filename=$1 WHERE id=$2', [$old['filename'], $old['id']]);
            $current = $this->upload();
            $this->assertTrue(PoolFileTransitions::move(RestApiEnv::testDb(), $this->source($current), $original));
            $after = RestApiEnv::rows(self::$conn, 'SELECT * FROM pool_files WHERE id=$1', [$old['id']])[0];
            $this->assertSame($history['storage_path'], $after['storage_path']);
            $this->assertSame($history['client_id'], $after['client_id']);
            $this->assertSame($history['filename'], $after['filename']);
            $this->assertSame($this->pdf(), $this->download($current['id'])['body']);
        }
    }

    public function test_conversion_releases_a_retained_historical_destination_alias(): void
    {
        $old = $this->upload();
        $target = $this->source($old);
        PoolFileTransitions::delete(RestApiEnv::testDb(), $target);
        RestApiEnv::rows(self::$conn, 'UPDATE pool_files SET filename=$1 WHERE id=$2', [$old['filename'], $old['id']]);
        $current = $this->upload();
        file_put_contents($target, $this->pdf());
        $this->assertTrue(PoolFileTransitions::converted(RestApiEnv::testDb(), $this->source($current), $target));
        $history = RestApiEnv::rows(self::$conn, 'SELECT * FROM pool_files WHERE id=$1', [$old['id']])[0];
        $this->assertStringStartsWith('.history-' . $old['id'] . '-', $history['filename']);
        $this->assertFileExists(self::$root . '/' . $history['storage_path']);
        $this->assertSame($this->pdf(), $this->download($current['id'])['body']);
    }

    public function test_failed_sibling_insert_rolls_back_references_and_restores_pool_file(): void
    {
        $row = $this->upload();
        $lines = RestApiEnv::rows(self::$conn, 'INSERT INTO kassekladde(kladde_id,bilag) VALUES(82,820),(82,820) RETURNING id');
        $sourceId = (int)$lines[0]['id'];
        $siblingId = (int)$lines[1]['id'];
        $folder = self::$root . '/finance/82/' . $sourceId;
        mkdir($folder, 0777, true);
        $destination = $folder . '/' . $row['filename'];
        RestApiEnv::rows(self::$conn, 'ALTER TABLE documents ADD CONSTRAINT b5_reject_sibling CHECK(source_id <> ' . $siblingId . ')');
        try {
            try {
                PoolFileTransitions::insertDocument(RestApiEnv::testDb(), $this->source($row), $destination, 1,
                    'kassekladde', $sourceId, (int)RestApiEnv::loginData()['user']['id']);
                $this->fail('A rejected sibling insertion must fail the attachment');
            } catch (RuntimeException $error) {
                $this->assertSame('Pool identity write failed', $error->getMessage());
            }
            $this->assertFileExists($this->source($row));
            $this->assertFileDoesNotExist($destination);
            $this->assertCount(0, RestApiEnv::rows(self::$conn, 'SELECT id FROM documents WHERE filename=$1', [$row['filename']]));
            $stored = RestApiEnv::rows(self::$conn, 'SELECT * FROM pool_files WHERE id=$1', [$row['id']])[0];
            $this->assertSame('ubehandlet', $stored['status']);
            $this->assertSame('pulje/' . $row['filename'], $stored['storage_path']);
        } finally {
            RestApiEnv::rows(self::$conn, 'ALTER TABLE documents DROP CONSTRAINT b5_reject_sibling');
        }
    }

    public function test_repairable_startxref_is_accepted_on_upload_and_both_previews(): void
    {
        $pdf = preg_replace_callback('/startxref\n([0-9]+)/', fn($match) => "startxref\n" . ((int)$match[1] + 3), $this->pdf());
        $row = $this->upload($pdf);
        $this->assertSame($pdf, $this->download($row['id'])['body']);
        foreach (['thumbnail.php', 'page.php'] as $endpoint) {
            $response = RestApiEnv::http('GET', self::ROUTE . $endpoint . '?id=' . $row['id'] . '&n=1', null, self::$headers);
            $this->assertSame(200, $response['status'], $response['body']);
            $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($response['body'])[2]);
        }
    }

    public function test_legacy_attachment_blocks_rest_delete_through_sibling_insertion(): void
    {
        $row = $this->upload();
        $lines = RestApiEnv::rows(self::$conn, 'INSERT INTO kassekladde(kladde_id,bilag) VALUES(81,810),(81,810) RETURNING id');
        $sourceId = (int)$lines[0]['id'];
        $folder = self::$root . '/finance/81/' . $sourceId;
        mkdir($folder, 0777, true);
        $destination = $folder . '/' . $row['filename'];
        RestApiEnv::rows(self::$conn, "CREATE FUNCTION b5_wait_document() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN
            PERFORM pg_advisory_xact_lock(NEW.source_id::bigint); RETURN NEW; END $$");
        RestApiEnv::rows(self::$conn, 'CREATE TRIGGER b5_wait_document BEFORE INSERT ON documents FOR EACH ROW EXECUTE FUNCTION b5_wait_document()');
        // Pause at the sibling INSERT after the target reference has already been inserted.
        $siblingId = (int)$lines[1]['id'];
        RestApiEnv::rows(self::$conn, 'SELECT pg_advisory_lock($1::bigint)', [$siblingId]);
        $worker = <<<'PHP'
require $argv[1] . '/tests/restapi/support/RestApiEnv.php';
require $argv[1] . '/includes/db_query.php';
require $argv[1] . '/includes/docsIncludes/poolFileTransitions.php';
$GLOBALS['connection'] = RestApiEnv::connect($argv[2]);
$GLOBALS['db_type'] = 'postgresql';
$GLOBALS['db'] = $argv[2];
$GLOBALS['db_skriv_id'] = 0;
$ok = PoolFileTransitions::insertDocument($argv[2], $argv[3], $argv[4], 1, 'kassekladde', (int)$argv[5], (int)$argv[6]);
echo $ok ? 'attached' : 'failed';
PHP;
        $process = proc_open([PHP_BINARY, '-r', $worker, dirname(__DIR__, 2), RestApiEnv::testDb(),
            $this->source($row), $destination, (string)$sourceId, (string)RestApiEnv::loginData()['user']['id']],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $multi = curl_multi_init();
        $handle = curl_init(RestApiEnv::baseUrl() . self::ROUTE . '?id=' . $row['id']);
        curl_setopt_array($handle, [CURLOPT_CUSTOMREQUEST => 'DELETE', CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => self::$headers, CURLOPT_TIMEOUT => 10]);
        try {
            $paused = false;
            $deadline = microtime(true) + 8;
            do {
                $paused = RestApiEnv::rows(self::$conn, "SELECT pid FROM pg_stat_activity WHERE datname=$1
                    AND wait_event='advisory' AND query LIKE 'INSERT INTO documents%'", [RestApiEnv::testDb()]) !== [];
                usleep(10000);
            } while (!$paused && microtime(true) < $deadline);
            $this->assertTrue($paused, 'Legacy sibling insertion did not reach the pause');
            $this->assertFileExists($destination);
            curl_multi_add_handle($multi, $handle);
            $blocked = false;
            do {
                curl_multi_exec($multi, $running);
                $blocked = RestApiEnv::rows(self::$conn, "SELECT pid FROM pg_stat_activity WHERE datname=$1
                    AND wait_event_type='Lock' AND query LIKE 'SELECT * FROM pool_files%'", [RestApiEnv::testDb()]) !== [];
                if ($running) {
                    curl_multi_select($multi, 0.01);
                }
            } while (!$blocked && $running && microtime(true) < $deadline);
            $this->assertTrue($blocked, 'REST DELETE must wait for the whole legacy attachment');
            RestApiEnv::rows(self::$conn, 'SELECT pg_advisory_unlock($1::bigint)', [$siblingId]);
            do {
                curl_multi_exec($multi, $running);
                if ($running) {
                    curl_multi_select($multi, 0.01);
                }
            } while ($running);
            $body = curl_multi_getcontent($handle);
            $this->assertSame(409, curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $body);
            $this->assertSame('attached', stream_get_contents($pipes[1]), stream_get_contents($pipes[2]));
            $this->assertCount(2, RestApiEnv::rows(self::$conn, 'SELECT id FROM documents WHERE pool_file_id=$1', [$row['id']]));
            $this->assertSame($this->pdf(), $this->download($row['id'])['body']);
        } finally {
            RestApiEnv::rows(self::$conn, 'SELECT pg_advisory_unlock($1::bigint)', [$siblingId]);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
            curl_multi_close($multi);
            RestApiEnv::rows(self::$conn, 'DROP TRIGGER b5_wait_document ON documents');
            RestApiEnv::rows(self::$conn, 'DROP FUNCTION b5_wait_document()');
        }
    }

    public function test_attach_preserves_download_and_links_target_and_siblings(): void
    {
        $row = $this->upload();
        $folder = self::$root . '/finance/42/' . $row['id'];
        mkdir($folder, 0777, true);
        $destination = $folder . '/' . $row['filename'];
        $this->assertTrue(PoolFileTransitions::move(RestApiEnv::testDb(), $this->source($row), $destination));
        $ids = [];
        foreach ([1, 2] as $line) {
            $journal = RestApiEnv::rows(self::$conn, 'INSERT INTO kassekladde (kladde_id,bilag) VALUES ($1,$2) RETURNING id', [42, 1042]);
            $sourceId = (int)$journal[0]['id'];
            $doc = RestApiEnv::rows(self::$conn, 'INSERT INTO documents (filename,filepath,source,source_id,timestamp,user_id) VALUES ($1,$2,$3,$4,$5,$6) RETURNING id',
                [$row['filename'], '/finance/42/' . $row['id'], 'kassekladde', $sourceId, time(), RestApiEnv::loginData()['user']['id']]);
            $ids[] = (int)$doc[0]['id'];
        }
        PoolFileTransitions::attach(RestApiEnv::testDb(), $destination, (int)RestApiEnv::loginData()['user']['id']);
        $stored = RestApiEnv::rows(self::$conn, 'SELECT * FROM pool_files WHERE id=$1', [$row['id']])[0];
        $this->assertSame('attached', $stored['status']);
        $this->assertSame('1042', $stored['bilag_nr']);
        $this->assertNotEmpty($stored['attached_at']);
        $this->assertSame($stored['uploaded_by'], $stored['attached_by']);
        $this->assertSame($ids, json_decode($stored['document_ids'], true));
        $this->assertCount(2, RestApiEnv::rows(self::$conn, 'SELECT id FROM documents WHERE pool_file_id=$1', [$row['id']]));
        $this->assertSame($this->pdf(), $this->download($row['id'])['body']);
        PoolFileTransitions::missing("filename='" . db_escape_string($row['filename']) . "'");
        $this->assertSame('attached', RestApiEnv::rows(self::$conn, 'SELECT status FROM pool_files WHERE id=$1', [$row['id']])[0]['status']);
    }

    public function test_rename_keeps_server_and_client_identity_and_download(): void
    {
        $row = $this->upload();
        $destination = poolPuljePath(RestApiEnv::testDb()) . '/renamed-' . $row['id'] . '.pdf';
        $this->assertTrue(PoolFileTransitions::move(RestApiEnv::testDb(), $this->source($row), $destination));
        $stored = RestApiEnv::rows(self::$conn, 'SELECT * FROM pool_files WHERE id=$1', [$row['id']])[0];
        $this->assertSame($row['client_id'], $stored['client_id']);
        $this->assertSame('pulje/' . basename($destination), $stored['storage_path']);
        $this->assertSame($this->pdf(), $this->download($row['id'])['body']);
    }

    public function test_web_delete_and_restore_preserve_id_and_bytes(): void
    {
        $row = $this->upload();
        $source = $this->source($row);
        $this->assertTrue(PoolFileTransitions::delete(RestApiEnv::testDb(), $source));
        chmod(poolPuljePath(RestApiEnv::testDb()) . '/.deleted', 0777);
        $this->assertTrue(PoolFileTransitions::delete(RestApiEnv::testDb(), $source));
        $this->assertSame(410, $this->download($row['id'])['status']);
        $stored = RestApiEnv::rows(self::$conn, 'SELECT * FROM pool_files WHERE id=$1', [$row['id']])[0];
        $this->assertSame('deleted', $stored['status']);
        $this->assertFileExists(self::$root . '/' . $stored['storage_path']);
        $this->assertTrue(PoolFileTransitions::restore(RestApiEnv::testDb(), self::$root . '/' . $stored['storage_path'], $source));
        $this->assertSame($this->pdf(), $this->download($row['id'])['body']);
        $this->assertSame('ubehandlet', RestApiEnv::rows(self::$conn, 'SELECT status FROM pool_files WHERE id=$1', [$row['id']])[0]['status']);
    }

    public function test_rest_delete_can_be_restored_through_shared_transition(): void
    {
        $row = $this->upload();
        $response = RestApiEnv::http('DELETE', self::ROUTE . '?id=' . $row['id'], null, self::$headers);
        $this->assertSame(200, $response['status'], $response['body']);
        $stored = RestApiEnv::rows(self::$conn, 'SELECT * FROM pool_files WHERE id=$1', [$row['id']])[0];
        $this->assertTrue(PoolFileTransitions::restore(RestApiEnv::testDb(), self::$root . '/' . $stored['storage_path'], $this->source($row)));
        $this->assertSame($this->pdf(), $this->download($row['id'])['body']);
    }

    public function test_thumbnail_cache_and_second_page_render_real_pdf(): void
    {
        $row = $this->upload();
        foreach (['thumbnail.php?id=' . $row['id'] => 200, 'page.php?id=' . $row['id'] . '&n=2' => 1600] as $query => $edge) {
            $response = RestApiEnv::http('GET', self::ROUTE . $query, null, self::$headers);
            $this->assertSame(200, $response['status'], $response['body']);
            $dimensions = getimagesizefromstring($response['body']);
            $this->assertSame([$edge, $edge, IMAGETYPE_JPEG], array_slice($dimensions, 0, 3));
            if ($edge === 200) {
                $cached = RestApiEnv::rows(self::$conn, 'SELECT thumbnail_path FROM pool_files WHERE id=$1', [$row['id']])[0]['thumbnail_path'];
                $this->assertFileExists(self::$root . '/' . $cached);
                $again = RestApiEnv::http('GET', self::ROUTE . $query, null, self::$headers);
                $this->assertSame($response['body'], $again['body']);
                $this->assertSame($cached, RestApiEnv::rows(self::$conn, 'SELECT thumbnail_path FROM pool_files WHERE id=$1', [$row['id']])[0]['thumbnail_path']);
            }
        }
        $path = $this->source($row);
        $this->assertSame(['page_count' => 2, 'decoded_pixels' => 80000], mobileAttachmentInspectPdf($path));
    }

    public function test_ownership_is_checked_even_on_cached_thumbnail_and_finance_reads_are_allowed(): void
    {
        $row = $this->upload();
        foreach (['thumbnail.php', 'page.php'] as $endpoint) {
            $query = $endpoint . '?id=' . $row['id'] . '&n=1';
            $this->assertSame(200, RestApiEnv::http('GET', self::ROUTE . $query, null, self::$headers)['status']);
            $denied = RestApiEnv::http('GET', self::ROUTE . $query, null, self::$other);
            $this->assertSame(404, $denied['status']);
            $this->assertSame('validation_failed', $denied['json']['data']['error']);
            $this->assertSame(200, RestApiEnv::http('GET', self::ROUTE . $query, null, self::$finance)['status']);
        }
    }

    public function test_malformed_pdf_fails_cleanly_on_both_preview_routes(): void
    {
        $row = $this->upload();
        file_put_contents($this->source($row), "%PDF-1.4\nmalformed\n%%EOF\n");
        foreach (['thumbnail.php', 'page.php'] as $endpoint) {
            $response = RestApiEnv::http('GET', self::ROUTE . $endpoint . '?id=' . $row['id'] . '&n=1', null, self::$headers);
            $this->assertSame(422, $response['status'], $response['body']);
            $this->assertSame('validation_failed', $response['json']['data']['error']);
            $this->assertStringNotContainsString('Ghostscript', $response['body']);
        }
    }

    public function test_upload_rejects_page_and_pixel_caps_before_finalization(): void
    {
        foreach ([$this->pdf(21), $this->pdf(1, 20000), "%PDF-1.4\nbroken\n%%EOF\n"] as $pdf) {
            $response = RestApiEnv::http('POST', self::ROUTE, ['id' => RequestId::resolve(null), 'image_base64' => base64_encode($pdf)], self::$headers);
            $this->assertSame(422, $response['status'], $response['body']);
            $this->assertSame('validation_failed', $response['json']['data']['error']);
        }
    }

    public function test_conflicting_rename_preserves_both_files_and_identity(): void
    {
        $row = $this->upload();
        $target = poolPuljePath(RestApiEnv::testDb()) . '/collision.pdf';
        file_put_contents($target, 'existing document');
        $this->assertFalse(PoolFileTransitions::move(RestApiEnv::testDb(), $this->source($row), $target));
        $this->assertSame('existing document', file_get_contents($target));
        $this->assertSame($this->pdf(), $this->download($row['id'])['body']);
    }

    public function test_previews_reject_a_replaced_symlink_after_upload_path_was_resolved(): void
    {
        $row = $this->upload();
        $source = $this->source($row);
        $outside = tempnam(sys_get_temp_dir(), 'b5_foreign_');
        file_put_contents($outside, $this->pdf());
        unlink($source);
        symlink($outside, $source);
        try {
            foreach (['thumbnail.php', 'page.php'] as $endpoint) {
                $response = RestApiEnv::http('GET', self::ROUTE . $endpoint . '?id=' . $row['id'] . '&n=1', null, self::$headers);
                $this->assertSame(404, $response['status'], $response['body']);
            }
        } finally {
            unlink($source);
            unlink($outside);
        }
    }

    public function test_reused_filename_frees_alias_without_deleting_historical_identity(): void
    {
        $row = $this->upload();
        $source = $this->source($row);
        $this->assertTrue(PoolFileTransitions::delete(RestApiEnv::testDb(), $source));
        file_put_contents($source, $this->pdf());
        PoolFileTransitions::releaseName(RestApiEnv::testDb(), $source);
        $stored = RestApiEnv::rows(self::$conn, 'SELECT * FROM pool_files WHERE id=$1', [$row['id']])[0];
        $this->assertSame('deleted', $stored['status']);
        $this->assertSame($row['client_id'], $stored['client_id']);
        $this->assertNotSame($row['filename'], $stored['filename']);
        $this->assertFileExists(self::$root . '/' . $stored['storage_path']);
        $this->assertFileExists($source);
        $this->assertCount(0, RestApiEnv::rows(self::$conn, 'SELECT id FROM pool_files WHERE filename=$1', [$row['filename']]));
    }

    public function test_renderer_cpu_and_memory_limits_fail_cleanly(): void
    {
        foreach (['1000000000 array pop quit', '{} loop'] as $program) {
            $started = microtime(true);
            try {
                mobileAttachmentGs(['-dNODISPLAY', '-c', $program]);
                $this->fail('Unbounded renderer program succeeded');
            } catch (AttachmentException $error) {
                $this->assertSame(422, $error->getStatusCode());
                $this->assertSame('validation_failed', $error->error);
                $this->assertLessThan(14, microtime(true) - $started);
            }
        }
    }

    public function test_preview_validation_and_tombstones(): void
    {
        $row = $this->upload();
        foreach (['page.php?id=' . $row['id'] . '&n=3', 'page.php?id=' . $row['id'] . '&n=0',
            'page.php?id=' . $row['id'], 'thumbnail.php?file=../connect.php', 'thumbnail.php?id[]=1'] as $query) {
            $this->assertSame(422, RestApiEnv::http('GET', self::ROUTE . $query, null, self::$headers)['status']);
        }
        PoolFileTransitions::delete(RestApiEnv::testDb(), $this->source($row));
        foreach (['thumbnail.php', 'page.php'] as $endpoint) {
            $this->assertSame(410, RestApiEnv::http('GET', self::ROUTE . $endpoint . '?id=' . $row['id'] . '&n=1', null, self::$headers)['status']);
        }
    }
}
