<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileAttachmentTest.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Regress reminder crash/sync recovery, reservation timestamps and legacy XML MIME.
// 20261001 CDX/LH Reject externally replaced symlinks after a cached safe path resolution.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../restapi/models/attachment/DurableAttachmentModel.php';
require_once __DIR__ . '/../../includes/mobileUpdates.php';
require_once __DIR__ . '/../../includes/db_query.php';

final class MobileAttachmentTest extends TestCase
{
    private static $conn;
    private static array $headers;
    private static array $otherHeaders;
    private static array $financeHeaders;
    private static array $foreignHeaders;
    private static ?string $foreignDb = null;
    private static ?int $foreignTenant = null;
    private static array $directories = [];
    private const ROUTE = '/restapi/endpoints/v1/attachment/';

    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        self::$conn = RestApiEnv::connect(RestApiEnv::testDb());
        mobileTenantUpdates(self::$conn);
        RestApiEnv::rows(self::$conn, 'ALTER TABLE pool_files ADD COLUMN IF NOT EXISTS norm_amount numeric(15,3)');
        RestApiEnv::rows(self::$conn, 'ALTER TABLE pool_files ADD COLUMN IF NOT EXISTS content_sha256 char(64)');
        RestApiEnv::rows(self::$conn, 'UPDATE brugere SET rettigheder=$1 WHERE brugernavn=$2', ['0000000000000000', RestApiEnv::user()]);
        foreach (['b4_other' => '0000000000000000', 'b4_finance' => '2000000000000000'] as $name => $rights) {
            RestApiEnv::rows(self::$conn, 'INSERT INTO brugere (brugernavn,kode,email,rettigheder,status) VALUES ($1,$2,$3,$4,true)',
                [$name, md5(TestCredentials::password($name)), $name . '@example.invalid', $rights]);
        }
        self::$headers = RestApiEnv::authHeaders();
        self::$otherHeaders = self::loginHeaders('b4_other');
        self::$financeHeaders = self::loginHeaders('b4_finance');
        $userId = RestApiEnv::loginData()['user']['id'];
        self::$foreignDb = 'mobile_b4_' . bin2hex(random_bytes(4));
        $master = RestApiEnv::connect(RestApiEnv::masterDb());
        RestApiEnv::rows($master, 'CREATE DATABASE ' . self::$foreignDb . ' TEMPLATE ' . RestApiEnv::templateDb());
        $rows = RestApiEnv::rows($master, 'INSERT INTO regnskab (regnskab,dbhost,dbuser,db,version,sidst,brugerantal,posteringer,lukket,administrator)
            SELECT $1,dbhost,dbuser,$2,version,sidst,brugerantal,posteringer,\'\',administrator FROM regnskab WHERE id=$3 RETURNING id',
            [self::$foreignDb, self::$foreignDb, RestApiEnv::regnskabId(RestApiEnv::accountOpen())]);
        self::$foreignTenant = (int)$rows[0]['id'];
        pg_close($master);
        $foreign = RestApiEnv::connect(self::$foreignDb);
        mobileTenantUpdates($foreign);
        RestApiEnv::rows($foreign, 'DELETE FROM brugere WHERE id=$1 OR brugernavn=$2', [$userId, RestApiEnv::user()]);
        RestApiEnv::rows($foreign, 'INSERT INTO brugere (id,brugernavn,kode,email,rettigheder,status) VALUES ($1,$2,$3,$4,$5,true)',
            [$userId, RestApiEnv::user(), md5(RestApiEnv::password()), 'b4_foreign@example.invalid', '2000000000000000']);
        pg_close($foreign);
        $login = RestApiEnv::login(RestApiEnv::user(), RestApiEnv::password(), self::$foreignDb);
        if ($login['status'] !== 200) {
            throw new RuntimeException('Foreign attachment test login failed: ' . $login['body']);
        }
        self::$foreignHeaders = ['Authorization: Bearer ' . $login['json']['data']['access_token']];
        self::$directories = [dirname(poolPuljePath(RestApiEnv::testDb())), dirname(poolPuljePath(self::$foreignDb))];
        foreach (self::$directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            chmod($directory, 0777);
        }
    }

    private static function loginHeaders(string $name): array
    {
        $response = RestApiEnv::login($name, TestCredentials::password($name), RestApiEnv::accountOpen());
        if ($response['status'] !== 200) {
            throw new RuntimeException('Attachment test login failed: ' . $response['body']);
        }
        return ['Authorization: Bearer ' . $response['json']['data']['access_token']];
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$conn) {
            pg_close(self::$conn);
        }
        if (self::$foreignDb !== null) {
            $master = RestApiEnv::connect(RestApiEnv::masterDb());
            RestApiEnv::rows($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname=$1 AND pid<>pg_backend_pid()', [self::$foreignDb]);
            RestApiEnv::rows($master, 'DROP DATABASE ' . self::$foreignDb);
            if (self::$foreignTenant !== null) {
                RestApiEnv::rows($master, 'DELETE FROM regnskab WHERE id=$1', [self::$foreignTenant]);
            }
            pg_close($master);
        }
        foreach (self::$directories as $directory) {
            self::removeDirectory($directory);
        }
        RestApiEnv::teardownTenant();
    }

    /** Remove only the process-owned test stores, never follow symlinks. */
    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (new FilesystemIterator($directory) as $file) {
            if ($file->isDir() && !$file->isLink()) {
                self::removeDirectory($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($directory);
    }

    /** @return string Small complete PDF with a real cross-reference table, usable by B5's renderer. */
    private function pdf(string $label = 'receipt'): string
    {
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] /Resources << >> /Contents 4 0 R >>',
            "<< /Length 0 >>\nstream\n\nendstream"];
        $pdf = '%PDF-1.4' . "\n% " . $label . "\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 5\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        return $pdf . "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    }

    private function payload(?string $id = null, ?string $pdf = null): array
    {
        $pdf = $pdf ?? $this->pdf();
        return ['id' => $id ?? RequestId::resolve(null), 'sha256' => hash('sha256', $pdf), 'size' => strlen($pdf),
            'image_base64' => base64_encode($pdf), 'filename' => 'receipt.pdf',
            'extracted_data' => ['total_amount' => '612.50', 'invoice_date' => '2026-10-01',
                'invoice_number' => '123', 'invoice_description' => 'Fuel', 'currency' => 'DKK', 'vendor' => 'Mobile receipt']];
    }

    private function request(string $method, string $suffix = '', ?array $data = null, ?array $headers = null): array
    {
        return RestApiEnv::http($method, self::ROUTE . $suffix, $data, $headers ?? self::$headers);
    }

    private function upload(?array $payload = null): array
    {
        $response = $this->request('POST', '', $payload ?? $this->payload());
        $this->assertSame(201, $response['status'], $response['body']);
        return $response['json']['data'];
    }

    public function test_pdf_upload_reconcile_download_and_metadata(): void
    {
        $payload = $this->payload();
        $row = $this->upload($payload);
        foreach (['id', 'client_id', 'status', 'uploaded_at', 'sha256'] as $key) {
            $this->assertArrayHasKey($key, $row);
        }
        $this->assertIsInt($row['id']);
        $this->assertSame($payload['id'], $row['client_id']);
        $this->assertSame($payload['sha256'], $row['sha256']);
        $this->assertSame('ubehandlet', $row['status']);
        $this->assertSame('612.50', $row['extracted_data']['total_amount']);
        $this->assertSame('Fuel', $row['metadata']['invoiceDescription']);
        $this->assertStringEndsWith('.pdf', $row['filename']);
        $this->assertArrayNotHasKey('storage_path', $row);
        $this->assertArrayNotHasKey('uploaded_by', $row);
        foreach (['?id=' . $row['id'], '?id=' . $row['client_id'], '?client_id=' . $row['client_id'], '?file=' . $row['filename']] as $alias) {
            $response = $this->request('GET', $alias);
            $this->assertSame(200, $response['status'], $response['body']);
            $this->assertSame($row, $response['json']['data']);
            $download = $this->request('GET', $alias . '&download=true');
            $this->assertSame(200, $download['status']);
            $this->assertSame($this->pdf(), $download['body']);
        }
        $retry = $this->request('POST', '', $payload);
        $this->assertSame(200, $retry['status'], $retry['body']);
        $this->assertSame($row, $retry['json']['data']);
        $dbRow = RestApiEnv::rows(self::$conn, 'SELECT * FROM pool_files WHERE id=$1', [$row['id']])[0];
        $this->assertSame('612.500', $dbRow['norm_amount']);
        $this->assertSame($row['sha256'], $dbRow['content_sha256']);
    }

    public function test_legacy_file_alias_preserves_spaces_and_unicode(): void
    {
        $row = $this->upload();
        $filename = 'Invoice Æ 2026.pdf';
        $root = poolPuljePath(RestApiEnv::testDb());
        rename($root . '/' . $row['filename'], $root . '/' . $filename);
        RestApiEnv::rows(self::$conn, 'UPDATE pool_files SET filename=$1,storage_path=$2 WHERE id=$3', [$filename, 'pulje/' . $filename, $row['id']]);
        $alias = '?file=' . rawurlencode($filename);
        $this->assertSame($row['id'], $this->request('GET', $alias)['json']['data']['id']);
        $this->assertSame($this->pdf(), $this->request('GET', $alias . '&download=true')['body']);
    }

    public function test_other_user_cannot_list_read_download_delete_or_reuse_id(): void
    {
        $payload = $this->payload();
        $row = $this->upload($payload);
        $list = $this->request('GET', '', null, self::$otherHeaders);
        $this->assertSame(200, $list['status']);
        $this->assertSame(0, $list['json']['data']['total']);
        foreach (['?id=' . $row['id'], '?client_id=' . $row['client_id'], '?file=' . $row['filename']] as $alias) {
            foreach (['', '&download=true'] as $download) {
                $this->assertSame(404, $this->request('GET', $alias . $download, null, self::$otherHeaders)['status']);
            }
        }
        $this->assertSame(404, $this->request('DELETE', '?id=' . $row['id'], null, self::$otherHeaders)['status']);
        $this->assertSame(404, $this->request('POST', '', $payload, self::$otherHeaders)['status']);
        $this->assertSame('ubehandlet', RestApiEnv::rows(self::$conn, 'SELECT status FROM pool_files WHERE id=$1', [$row['id']])[0]['status']);
    }

    public function test_finans_can_read_colleagues_but_cannot_delete_them_and_mine_filters(): void
    {
        $row = $this->upload();
        $this->assertSame(200, $this->request('GET', '?id=' . $row['id'], null, self::$financeHeaders)['status']);
        $download = $this->request('GET', '?file=' . $row['filename'] . '&download=true', null, self::$financeHeaders);
        $this->assertSame($this->pdf(), $download['body']);
        $this->assertSame(404, $this->request('DELETE', '?id=' . $row['id'], null, self::$financeHeaders)['status']);
        $this->assertSame(0, $this->request('GET', '?mine=1', null, self::$financeHeaders)['json']['data']['total']);
        $this->assertGreaterThan(0, $this->request('GET', '', null, self::$financeHeaders)['json']['data']['total']);
    }

    public function test_equal_principal_ids_do_not_share_ownership(): void
    {
        $row = $this->upload();
        $owner = RestApiEnv::rows(self::$conn, 'SELECT uploaded_by FROM pool_files WHERE id=$1', [$row['id']])[0]['uploaded_by'];
        $registryOwner = str_replace('/account/', '/registry/', $owner);
        $store = new DurableAttachmentModel(self::$conn, RestApiEnv::testDb(), $registryOwner, true);
        $this->assertSame($row['id'], (int)$store->find(['id' => $row['id']])['id']);
        $this->assertSame(0, $store->listing(50, 0, null, true)['total']);
        try {
            $store->delete(['id' => $row['id']]);
            $this->fail("Registry subject owned the account subject's row");
        } catch (AttachmentException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_foreign_tenant_with_equal_account_user_id_cannot_read_any_alias(): void
    {
        $row = $this->upload();
        foreach (['?id=' . $row['id'], '?id=' . $row['client_id'], '?client_id=' . $row['client_id'], '?file=' . $row['filename']] as $alias) {
            foreach (['', '&download=true'] as $download) {
                $this->assertSame(404, $this->request('GET', $alias . $download, null, self::$foreignHeaders)['status']);
            }
        }
    }

    public function test_traversal_on_every_alias_and_download(): void
    {
        foreach (['../secret.pdf', '..\\secret.pdf', '/etc/passwd', '..%2fsecret.pdf', "a\0.pdf", 'x/../y.pdf'] as $value) {
            foreach (['id', 'client_id', 'file'] as $field) {
                foreach (['', '&download=true'] as $download) {
                    $response = $this->request('GET', '?' . $field . '=' . rawurlencode($value) . $download);
                    $this->assertSame(422, $response['status'], $response['body']);
                    $this->assertSame('validation_failed', $response['json']['data']['error']);
                }
            }
        }
    }

    public function test_db_storage_path_and_symlink_cannot_escape_tenant(): void
    {
        $row = $this->upload();
        $foreignRoot = dirname(poolPuljePath(self::$foreignDb));
        if (!is_dir($foreignRoot)) {
            mkdir($foreignRoot, 0750, true);
        }
        file_put_contents($foreignRoot . '/foreign.pdf', $this->pdf('private'));
        $root = dirname(poolPuljePath(RestApiEnv::testDb()));
        $path = $root . '/pulje/' . $row['filename'];
        unlink($path);
        symlink($foreignRoot . '/foreign.pdf', $path);
        foreach (['?id=' . $row['id'], '?file=' . $row['filename'], '?client_id=' . $row['client_id']] as $alias) {
            $this->assertSame(404, $this->request('GET', $alias)['status']);
            $this->assertSame(404, $this->request('GET', $alias . '&download=true')['status']);
        }
        unlink($path);
        file_put_contents($path, $this->pdf());
        RestApiEnv::rows(self::$conn, 'UPDATE pool_files SET storage_path=$1 WHERE id=$2', ['../' . self::$foreignDb . '/foreign.pdf', $row['id']]);
        $this->assertSame(404, $this->request('GET', '?id=' . $row['id'])['status']);
    }

    public function test_external_symlink_replacement_after_cached_stat_cannot_escape_tenant(): void
    {
        $uploaded = $this->upload();
        $owner = RestApiEnv::rows(self::$conn, 'SELECT uploaded_by FROM pool_files WHERE id=$1', [$uploaded['id']])[0]['uploaded_by'];
        $model = new DurableAttachmentModel(self::$conn, RestApiEnv::testDb(), $owner);
        $row = $model->find(['id' => $uploaded['id']]);
        $path = $model->path($row);
        $this->assertTrue(is_file($path));
        $this->assertArrayHasKey($path, realpath_cache_get());
        $foreignPath = dirname(poolPuljePath(self::$foreignDb)) . '/cached-stat-foreign.pdf';
        file_put_contents($foreignPath, $this->pdf('external private document'));
        // A different process leaves this PHP process's cached stat/realpath untouched.
        $process = proc_open([PHP_BINARY, '-r', 'unlink($argv[1]); symlink($argv[2], $argv[1]);', $path, $foreignPath],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $output . $errors);
        $this->assertArrayHasKey($path, realpath_cache_get());
        $this->assertSame($path, realpath($path), 'The old safe resolution is still cached after the external replacement');
        try {
            $model->path($row);
            $this->fail('A cached safe path must not expose an externally replaced symlink');
        } catch (AttachmentException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertSame('validation_failed', $e->error);
        } finally {
            unlink($path);
            unlink($foreignPath);
        }
    }

    public function test_delete_tombstones_is_idempotent_and_reupload_is_gone(): void
    {
        $payload = $this->payload();
        $row = $this->upload($payload);
        $deleted = $this->request('DELETE', '?id=' . $row['id']);
        $this->assertSame(200, $deleted['status'], $deleted['body']);
        $this->assertSame('deleted', $deleted['json']['data']['status']);
        $this->assertNotNull($deleted['json']['data']['deleted_at']);
        $this->assertSame($deleted['json']['data'], $this->request('DELETE', '?id=' . $row['id'])['json']['data']);
        $this->assertSame(404, $this->request('GET', '?file=' . $row['filename'] . '&download=true')['status']);
        $this->assertSame(410, $this->request('GET', '?file=' . rawurlencode($deleted['json']['data']['filename']) . '&download=true')['status']);
        $this->assertStringStartsWith('.history-' . $row['id'] . '-', $deleted['json']['data']['filename']);
        $gone = $this->request('POST', '', $payload);
        $this->assertSame(410, $gone['status']);
        $this->assertSame('gone', $gone['json']['data']['error']);
        $this->assertSame('deleted', $this->request('GET', '?client_id=' . $row['client_id'])['json']['data']['status']);
        $root = dirname(poolPuljePath(RestApiEnv::testDb()));
        $this->assertFileExists($root . '/pulje/.deleted/' . $row['id'] . '.pdf');
        $this->assertFileDoesNotExist($root . '/pulje/' . $row['filename']);
        $this->assertSame(422, $this->request('DELETE', '?file=' . $row['filename'])['status']);
    }

    public function test_attached_file_remains_readable_and_delete_is_conflict(): void
    {
        $row = $this->upload();
        $root = dirname(poolPuljePath(RestApiEnv::testDb()));
        mkdir($root . '/archive', 0755);
        rename($root . '/pulje/' . $row['filename'], $root . '/archive/' . $row['filename']);
        RestApiEnv::rows(self::$conn, "UPDATE pool_files SET status='attached',storage_path=$1,bilag_nr=1042,attached_at=now() WHERE id=$2",
            ['archive/' . $row['filename'], $row['id']]);
        $response = $this->request('GET', '?id=' . $row['id']);
        $this->assertSame('attached', $response['json']['data']['status']);
        $this->assertSame(1042, $response['json']['data']['bilag_nr']);
        $this->assertSame($this->pdf(), $this->request('GET', '?id=' . $row['id'] . '&download=true')['body']);
        $this->assertSame(409, $this->request('DELETE', '?id=' . $row['id'])['status']);
    }

    public function test_changed_digest_is_conflict_and_original_bytes_survive(): void
    {
        $payload = $this->payload();
        $row = $this->upload($payload);
        $changed = $this->payload($row['client_id'], $this->pdf('changed'));
        $response = $this->request('POST', '', $changed);
        $this->assertSame(409, $response['status']);
        $this->assertSame('conflict', $response['json']['data']['error']);
        $this->assertSame($this->pdf(), $this->request('GET', '?id=' . $row['id'] . '&download=true')['body']);
    }

    public function test_db_pagination_filters_and_untracked_file_is_invisible(): void
    {
        $this->upload();
        $this->upload();
        $root = dirname(poolPuljePath(RestApiEnv::testDb()));
        file_put_contents($root . '/pulje/untracked.pdf', $this->pdf());
        $page = $this->request('GET', '?limit=1&offset=0&status=ubehandlet&mine=1')['json']['data'];
        $this->assertCount(1, $page['items']);
        $this->assertGreaterThan(1, $page['total']);
        $this->assertSame(1, $page['limit']);
        $next = $this->request('GET', '?limit=1&offset=1&status=ubehandlet&mine=1')['json']['data'];
        $this->assertNotSame($page['items'][0]['id'], $next['items'][0]['id']);
        $this->assertSame($page['total'], $next['total']);
        $this->assertSame(404, $this->request('GET', '?file=untracked.pdf')['status']);
        foreach (['?limit=0', '?limit=101', '?offset=-1', '?status=posted', '?mine=2', '?id[]=1', '?client_id[]=x', '?file[]=x', '?download=true', '?id=1&file=x.pdf'] as $query) {
            $this->assertSame(422, $this->request('GET', $query)['status']);
        }
    }

    public function test_limits_mime_and_invalid_uploads_have_precise_codes(): void
    {
        $cases = [];
        $payload = $this->payload();
        $cases[] = [array_replace($payload, ['size' => 1]), 422, 'validation_failed'];
        $cases[] = [array_replace($payload, ['sha256' => str_repeat('0', 64)]), 422, 'validation_failed'];
        $cases[] = [array_replace($payload, ['id' => '../escape']), 422, 'validation_failed'];
        $cases[] = [array_replace($payload, ['image_base64' => '%%%']), 422, 'validation_failed'];
        $cases[] = [array_replace($payload, ['extracted_data' => ['total_amount' => ['bad']]]), 422, 'validation_failed'];
        $cases[] = [array_replace($payload, ['extracted_data' => ['vendor' => "bad\0value"]]), 422, 'validation_failed'];
        $cases[] = [$this->payload(null, 'plain text'), 415, 'unsupported_media_type'];
        $cases[] = [$this->payload(null, "%PDF-1.4\nmissing EOF"), 422, 'validation_failed'];
        $cases[] = [$this->payload(null, $this->pdf() . str_repeat(' ', AttachmentUpload::MAX_BYTES)), 413, 'payload_too_large'];
        foreach ($cases as [$data, $status, $error]) {
            $response = $this->request('POST', '', $data);
            $this->assertSame($status, $response['status'], $response['body']);
            $this->assertSame($error, $response['json']['data']['error']);
        }
    }

    public function test_legacy_base64_without_identity_still_uploads_pdf(): void
    {
        $response = $this->request('POST', '', ['filename' => 'old.pdf', 'image_base64' => 'data:application/pdf;base64,' . base64_encode($this->pdf()),
            'extracted_data' => ['invoice_description' => 'Legacy']]);
        $this->assertSame(201, $response['status'], $response['body']);
        $this->assertSame('Legacy', $response['json']['data']['metadata']['invoiceDescription']);
        $this->assertSame(36, strlen($response['json']['data']['client_id']));
    }

    /** @return array{status:int,body:string,json:?array} */
    private function multipart(array $payload, string $content, array $overrides = []): array
    {
        $path = tempnam(sys_get_temp_dir(), 'b4_multipart_');
        file_put_contents($path, $content);
        unset($payload['image_base64']);
        $payload['extracted_data'] = json_encode($payload['extracted_data']);
        $payload['file'] = new CURLFile($path, 'application/pdf', 'receipt.pdf');
        $payload = array_replace($payload, $overrides);
        $ch = curl_init(RestApiEnv::baseUrl() . self::ROUTE);
        try {
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => self::$headers,
                CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 30]);
            $raw = curl_exec($ch);
            return ['status' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'body' => (string)$raw, 'json' => json_decode((string)$raw, true)];
        } finally {
            curl_close($ch);
            unlink($path);
        }
    }

    public function test_multipart_and_base64_have_identical_shapes_and_idempotency(): void
    {
        $payload = $this->payload();
        $multipart = $this->multipart($payload, $this->pdf());
        $this->assertSame(201, $multipart['status'], $multipart['body']);
        $base64 = $this->request('POST', '', $payload);
        $this->assertSame(200, $base64['status']);
        $this->assertSame($multipart['json']['data'], $base64['json']['data']);
        $bad = $this->multipart($this->payload(), $this->pdf(), ['extracted_data' => 'bad JSON']);
        $this->assertSame(422, $bad['status']);
        $this->assertSame('validation_failed', $bad['json']['data']['error']);
        $bad = $this->multipart($this->payload(null, 'plain text'), 'plain text');
        $this->assertSame(415, $bad['status']);
        $large = $this->pdf() . str_repeat(' ', AttachmentUpload::MAX_BYTES);
        $bad = $this->multipart($this->payload(null, $large), $large);
        $this->assertSame(413, $bad['status'], $bad['body']);
    }

    public function test_renderer_measurement_limits(): void
    {
        AttachmentUpload::checkInspection(['page_count' => 20, 'decoded_pixels' => 60000000]);
        $this->addToAssertionCount(1);
        foreach ([['page_count' => 21, 'decoded_pixels' => 1], ['page_count' => 1, 'decoded_pixels' => 60000001]] as $inspection) {
            try {
                AttachmentUpload::checkInspection($inspection);
                $this->fail('Renderer limit was accepted');
            } catch (AttachmentException $e) {
                $this->assertSame(422, $e->getStatusCode());
            }
        }
    }

    public function test_legacy_image_conversion_when_gd_available(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD is absent in the dev container; legacy JPEG/PNG conversion requires GD. PDF uploads are fully tested.');
        }
        $image = imagecreatetruecolor(20, 20);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $row = $this->upload($this->payload(null, $bytes));
        $this->assertSame('application/pdf', $row['mimeType']);
        $this->assertStringStartsWith('%PDF-', $this->request('GET', '?id=' . $row['id'] . '&download=true')['body']);
    }

    public function test_staged_reservation_and_partial_staging_file_can_be_completed(): void
    {
        $seed = $this->upload();
        foreach ([false, true] as $hasPartialFile) {
            $payload = $this->payload();
            $filename = $payload['id'] . '.pdf';
            $stage = 'pulje/.staging/' . $filename;
            $rows = RestApiEnv::rows(self::$conn, "INSERT INTO pool_files (client_id,sha256,uploaded_by,filename,status,storage_path)
                SELECT $1,$2,uploaded_by,$3,'staged',$4 FROM pool_files WHERE id=$5 RETURNING id",
                [$payload['id'], $payload['sha256'], $filename, $stage, $seed['id']]);
            $serverId = (int)$rows[0]['id'];
            if ($hasPartialFile) {
                file_put_contents(dirname(poolPuljePath(RestApiEnv::testDb())) . '/' . $stage, '%PDF-partial');
                chmod(dirname(poolPuljePath(RestApiEnv::testDb())) . '/' . $stage, 0666);
            }
            $this->assertSame(404, $this->request('GET', '?client_id=' . $payload['id'])['status']);
            $this->assertSame(409, $this->request('DELETE', '?id=' . $serverId)['status']);
            $retry = $this->request('POST', '', $payload);
            $this->assertSame(200, $retry['status'], $retry['body']);
            $this->assertSame($serverId, $retry['json']['data']['id']);
            $this->assertSame('ubehandlet', $retry['json']['data']['status']);
            $this->assertSame($this->pdf(), $this->request('GET', '?id=' . $serverId . '&download=true')['body']);
        }
    }

    public function test_missing_optional_legacy_pool_columns_do_not_break_uploads(): void
    {
        RestApiEnv::rows(self::$conn, 'ALTER TABLE pool_files DROP COLUMN norm_amount');
        RestApiEnv::rows(self::$conn, 'ALTER TABLE pool_files DROP COLUMN content_sha256');
        try {
            $row = $this->upload();
            $this->assertSame(hash('sha256', $this->pdf()), $row['sha256']);
        } finally {
            RestApiEnv::rows(self::$conn, 'ALTER TABLE pool_files ADD COLUMN norm_amount numeric(15,3)');
            RestApiEnv::rows(self::$conn, 'ALTER TABLE pool_files ADD COLUMN content_sha256 char(64)');
        }
    }

    public function test_staged_expiration_skips_active_locks_and_preserves_tombstones(): void
    {
        $payload = $this->payload();
        $old = $this->upload($payload);
        $recent = $this->upload();
        $unprocessed = $this->upload();
        RestApiEnv::rows(self::$conn, "UPDATE pool_files SET status='staged',uploaded_at=now()-interval '8 days' WHERE id=$1", [$old['id']]);
        RestApiEnv::rows(self::$conn, "UPDATE pool_files SET status='staged' WHERE id=$1", [$recent['id']]);
        RestApiEnv::rows(self::$conn, "UPDATE pool_files SET uploaded_at=now()-interval '8 days' WHERE id=$1", [$unprocessed['id']]);
        $store = new DurableAttachmentModel(self::$conn, RestApiEnv::testDb(), 'maintenance', true);
        $locked = RestApiEnv::connect(RestApiEnv::testDb());
        try {
            RestApiEnv::rows($locked, 'BEGIN');
            RestApiEnv::rows($locked, 'SELECT id FROM pool_files WHERE id=$1 FOR UPDATE', [$old['id']]);
            $this->assertSame(0, $store->expireStaged());
            RestApiEnv::rows($locked, 'ROLLBACK');
            $this->assertSame(1, $store->expireStaged());
        } finally {
            pg_close($locked);
        }
        $this->assertSame(0, $store->expireStaged());
        $this->assertSame('staged', RestApiEnv::rows(self::$conn, 'SELECT status FROM pool_files WHERE id=$1', [$recent['id']])[0]['status']);
        $this->assertSame('ubehandlet', RestApiEnv::rows(self::$conn, 'SELECT status FROM pool_files WHERE id=$1', [$unprocessed['id']])[0]['status']);
        $this->assertSame('deleted', $this->request('GET', '?id=' . $old['id'])['json']['data']['status']);
        $this->assertSame(410, $this->request('POST', '', $payload)['status']);
        $path = poolPuljePath(RestApiEnv::testDb()) . '/.deleted/' . $old['id'] . '.pdf';
        $this->assertSame($this->pdf(), file_get_contents($path));
    }

    public function test_debug_log_default_off(): void
    {
        $root = dirname(__DIR__, 2);
        $log = $root . '/bilag/attachment_debug.log';
        $before = is_file($log) ? file_get_contents($log) : null;
        $this->upload();
        $savedConnection = ifset($GLOBALS, 'connection');
        $savedType = ifset($GLOBALS, 'db_type');
        $savedUri = ifset($_SERVER, 'REQUEST_URI');
        try {
            $_SERVER['REQUEST_URI'] = '/saldi/index.php';
            $GLOBALS['connection'] = self::$conn;
            $GLOBALS['db_type'] = 'postgresql';
            (new ReflectionMethod(AttachmentModel::class, 'debugLog'))->invoke(null, 'B4 default-off test', ['contentFirst100' => 'test bytes']);
        } finally {
            $GLOBALS['connection'] = $savedConnection;
            $GLOBALS['db_type'] = $savedType;
            if ($savedUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $savedUri;
            }
        }
        $after = is_file($log) ? file_get_contents($log) : null;
        $this->assertSame($before, $after);
    }

    /** Run parallel Apache requests; the observer can inject a failure while they run. @return list<array{status:int,body:string,json:?array}> */
    private function parallel(array $payloads, ?Closure $observer = null): array
    {
        $multi = curl_multi_init();
        $handles = [];
        foreach ($payloads as $payload) {
            $handle = curl_init(RestApiEnv::baseUrl() . self::ROUTE);
            curl_setopt_array($handle, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array_merge(self::$headers, ['Content-Type: application/json']),
                CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 40]);
            curl_multi_add_handle($multi, $handle);
            $handles[] = $handle;
        }
        do {
            curl_multi_exec($multi, $running);
            if ($observer !== null) {
                $observer();
            }
            if ($running) {
                curl_multi_select($multi, 0.05);
            }
        } while ($running);
        $responses = [];
        foreach ($handles as $handle) {
            $raw = curl_multi_getcontent($handle);
            $responses[] = ['status' => (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $raw, 'json' => json_decode($raw, true)];
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }
        curl_multi_close($multi);
        return $responses;
    }

    public function test_concurrent_duplicates_serialize_to_one_row(): void
    {
        $payload = $this->payload();
        $responses = $this->parallel([$payload, $payload]);
        $statuses = array_column($responses, 'status');
        sort($statuses);
        $this->assertSame([200, 201], $statuses, json_encode($responses));
        $this->assertSame($responses[0]['json']['data'], $responses[1]['json']['data']);
        $this->assertCount(1, RestApiEnv::rows(self::$conn, 'SELECT * FROM pool_files WHERE client_id=$1', [$payload['id']]));
    }

    /** Run the actual legacy scanner against the disposable tenant, restoring ambient DB globals. */
    private function syncPool(): void
    {
        $savedServer = array_intersect_key($_SERVER, array_flip(['REQUEST_URI', 'REMOTE_ADDR']));
        $_SERVER['REQUEST_URI'] = '/saldi/index.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $values = ['connection' => self::$conn, 'db_type' => 'postgresql', 'db' => RestApiEnv::testDb(), 'db_skriv_id' => 0];
        $saved = [];
        foreach ($values as $key => $value) {
            $saved[$key] = $GLOBALS[$key] ?? null;
            $GLOBALS[$key] = $value;
        }
        try {
            require_once __DIR__ . '/../../includes/db_query.php';
            require_once __DIR__ . '/../../includes/std_func.php';
            require_once __DIR__ . '/../../includes/docsIncludes/docPool.php';
            RestApiEnv::rows(self::$conn, "DELETE FROM settings WHERE var_name='skip_sync' AND var_grp='docs'");
            syncPuljeFilesToDatabase(dirname(dirname(poolPuljePath(RestApiEnv::testDb()))), RestApiEnv::testDb());
        } finally {
            unset($_SERVER['REQUEST_URI'], $_SERVER['REMOTE_ADDR']);
            $_SERVER = array_replace($_SERVER, $savedServer);
            foreach ($saved as $key => $value) {
                if ($value === null) {
                    unset($GLOBALS[$key]);
                } else {
                    $GLOBALS[$key] = $value;
                }
            }
        }
    }

    public function test_pool_sync_preserves_attached_identity_when_the_file_remains_in_pulje(): void
    {
        $row = $this->upload();
        $savedServer = array_intersect_key($_SERVER, array_flip(['REQUEST_URI', 'REMOTE_ADDR']));
        $_SERVER['REQUEST_URI'] = '/saldi/index.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $saved = $GLOBALS['connection'] ?? null;
        $GLOBALS['connection'] = self::$conn;
        try {
            require_once __DIR__ . '/../../includes/docsIncludes/poolFileTransitions.php';
            PoolFileTransitions::attach(RestApiEnv::testDb(), poolPuljePath(RestApiEnv::testDb()) . '/' . $row['filename'], 0);
        } finally {
            unset($_SERVER['REQUEST_URI'], $_SERVER['REMOTE_ADDR']);
            $_SERVER = array_replace($_SERVER, $savedServer);
            $GLOBALS['connection'] = $saved;
        }
        $this->syncPool();
        $stored = RestApiEnv::rows(self::$conn, 'SELECT * FROM pool_files WHERE id=$1', [$row['id']])[0];
        $this->assertSame('attached', $stored['status']);
        $this->assertCount(0, RestApiEnv::rows(self::$conn, 'SELECT id FROM pool_files WHERE filename=$1', [$row['filename']]));
        $this->assertCount(1, RestApiEnv::rows(self::$conn, 'SELECT id FROM pool_files WHERE storage_path=$1', ['pulje/' . $row['filename']]));
        $this->assertSame($this->pdf(), $this->request('GET', '?id=' . $row['id'] . '&download=true')['body']);
    }

    public function test_pool_sync_adopts_a_replacement_at_a_missing_tombstones_old_path(): void
    {
        $row = $this->upload();
        $path = poolPuljePath(RestApiEnv::testDb()) . '/' . $row['filename'];
        unlink($path);
        $saved = $GLOBALS['connection'] ?? null;
        $GLOBALS['connection'] = self::$conn;
        try {
            require_once __DIR__ . '/../../includes/docsIncludes/poolFileTransitions.php';
            PoolFileTransitions::missing('id=' . $row['id']);
        } finally {
            $GLOBALS['connection'] = $saved;
        }
        file_put_contents($path, $this->pdf('different replacement'));
        $this->syncPool();
        $replacement = RestApiEnv::rows(self::$conn, 'SELECT * FROM pool_files WHERE filename=$1', [$row['filename']]);
        $this->assertCount(1, $replacement);
        $this->assertNotSame($row['id'], (int)$replacement[0]['id']);
        $this->assertSame('ubehandlet', $replacement[0]['status']);
        $this->assertSame('deleted', $this->request('GET', '?id=' . $row['id'])['json']['data']['status']);
    }

    public function test_legacy_xml_listing_download_and_pdf_only_previews(): void
    {
        $this->upload();
        $name = 'ubl-' . bin2hex(random_bytes(4)) . '.xml';
        $xml = '<?xml version="1.0"?><Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"><ID>42</ID></Invoice>';
        file_put_contents(poolPuljePath(RestApiEnv::testDb()) . '/' . $name, $xml);
        $id = (int)RestApiEnv::rows(self::$conn, 'INSERT INTO pool_files(filename) VALUES($1) RETURNING id', [$name])[0]['id'];
        $detail = $this->request('GET', '?id=' . $id, null, self::$financeHeaders);
        $this->assertSame('application/xml', $detail['json']['data']['mimeType']);
        $list = $this->request('GET', '?status=ubehandlet&limit=100', null, self::$financeHeaders);
        $rows = array_column($list['json']['data']['items'], null, 'id');
        $this->assertSame('application/xml', $rows[$id]['mimeType']);
        $handle = curl_init(RestApiEnv::baseUrl() . self::ROUTE . '?id=' . $id . '&download=true');
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => self::$financeHeaders]);
        $raw = curl_exec($handle);
        $headers = substr($raw, 0, curl_getinfo($handle, CURLINFO_HEADER_SIZE));
        $this->assertSame(200, curl_getinfo($handle, CURLINFO_RESPONSE_CODE));
        $this->assertStringContainsString('Content-Type: application/xml', $headers);
        $this->assertStringContainsString('attachment-' . $id . '.xml', $headers);
        $this->assertSame($xml, substr($raw, strlen($headers)));
        curl_close($handle);
        foreach (['thumbnail.php', 'page.php'] as $endpoint) {
            $this->assertSame(422, $this->request('GET', $endpoint . '?id=' . $id . '&n=1', null, self::$financeHeaders)['status']);
        }
    }

    public function test_finalization_refreshes_an_old_reservation_before_stale_scan_cleanup(): void
    {
        $payload = $this->payload();
        $ownerRow = $this->upload();
        RestApiEnv::rows(self::$conn, "INSERT INTO pool_files(client_id,sha256,uploaded_by,filename,status,storage_path,updated)
            SELECT $1,$2,uploaded_by,$3,'staged',$4,now()-interval '2 minutes' FROM pool_files WHERE id=$5",
            [$payload['id'], $payload['sha256'], $payload['id'] . '.pdf', 'pulje/.staging/' . $payload['id'] . '.pdf', $ownerRow['id']]);
        $response = $this->request('POST', '', $payload);
        $this->assertSame(200, $response['status'], $response['body']);
        // Reproduce the cleanup predicate from a scanner snapshot taken before rename.
        $saved = $GLOBALS['connection'] ?? null;
        $GLOBALS['connection'] = self::$conn;
        try {
            require_once __DIR__ . '/../../includes/docsIncludes/poolFileTransitions.php';
            PoolFileTransitions::missing("client_id='" . $payload['id'] . "' AND updated < now()-interval '60 seconds'");
        } finally {
            $GLOBALS['connection'] = $saved;
        }
        $this->assertSame('ubehandlet', $this->request('GET', '?client_id=' . $payload['id'])['json']['data']['status']);
    }

    public function test_killed_database_transaction_after_rename_leaves_staged_row_and_retry_completes(): void
    {
        $this->assertCrashRecovery($this->payload());
    }

    public function test_invalid_reminder_does_not_commit_a_reservation(): void
    {
        $payload = $this->payload();
        $payload['extracted_data']['reminder_id'] = 2147483647;
        $response = $this->request('POST', '', $payload);
        $this->assertSame(403, $response['status'], $response['body']);
        $this->assertCount(0, RestApiEnv::rows(self::$conn, 'SELECT id FROM pool_files WHERE client_id=$1', [$payload['id']]));
        $this->assertFileDoesNotExist(poolPuljePath(RestApiEnv::testDb()) . '/' . $payload['id'] . '.pdf');
    }

    public function test_reminder_crash_then_pool_sync_keeps_reservation_and_retry_resolves(): void
    {
        $owner = RestApiEnv::rows(self::$conn, 'SELECT uploaded_by FROM pool_files WHERE id=$1', [$this->upload()['id']])[0]['uploaded_by'];
        $reminder = (int)RestApiEnv::rows(self::$conn, "INSERT INTO reminders(target_subject,message,created_by) VALUES($1,'Crash regression',$1) RETURNING id", [$owner])[0]['id'];
        $notification = (int)RestApiEnv::rows(self::$conn, "INSERT INTO notifications(type,title,message,reminder_id,created_by)
            VALUES('reminder','Bilag','Crash regression',$1,$2) RETURNING id", [$reminder, $owner])[0]['id'];
        RestApiEnv::rows(self::$conn, 'INSERT INTO notification_recipients(notification_id,subject) VALUES($1,$2)', [$notification, $owner]);
        $payload = $this->payload();
        $payload['extracted_data']['reminder_id'] = $reminder;
        $this->assertCrashRecovery($payload);
        $this->assertNotNull(RestApiEnv::rows(self::$conn, 'SELECT resolved_at FROM notification_recipients WHERE notification_id=$1', [$notification])[0]['resolved_at']);
    }

    private function assertCrashRecovery(array $payload): void
    {
        $killed = false;
        RestApiEnv::rows(self::$conn, "CREATE FUNCTION b4_pause_upload() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN
            IF NEW.status='ubehandlet' AND OLD.status='staged' THEN PERFORM pg_sleep(30); END IF; RETURN NEW; END $$");
        RestApiEnv::rows(self::$conn, 'CREATE TRIGGER b4_pause_upload BEFORE UPDATE ON pool_files FOR EACH ROW EXECUTE FUNCTION b4_pause_upload()');
        try {
            $response = $this->parallel([$payload], function () use (&$killed, $payload) {
                if ($killed) {
                    return;
                }
                $rows = RestApiEnv::rows(self::$conn, "SELECT pid FROM pg_stat_activity WHERE datname=$1 AND wait_event='PgSleep' AND query LIKE 'UPDATE pool_files SET status=%'", [RestApiEnv::testDb()]);
                if ($rows !== []) {
                    $this->assertFileExists(poolPuljePath(RestApiEnv::testDb()) . '/' . $payload['id'] . '.pdf');
                    RestApiEnv::rows(self::$conn, 'SELECT pg_terminate_backend($1)', [(int)$rows[0]['pid']]);
                    $killed = true;
                }
            })[0];
            $this->assertTrue($killed, 'Finalization transaction was not reached');
            $this->assertSame(500, $response['status'], $response['body']);
            $rows = RestApiEnv::rows(self::$conn, 'SELECT status FROM pool_files WHERE client_id=$1', [$payload['id']]);
            $this->assertSame('staged', $rows[0]['status']);
            $this->syncPool();
            $reserved = RestApiEnv::rows(self::$conn, 'SELECT * FROM pool_files WHERE filename=$1', [$payload['id'] . '.pdf']);
            $this->assertCount(1, $reserved);
            $this->assertSame($payload['id'], $reserved[0]['client_id']);
            $this->assertNotEmpty($reserved[0]['uploaded_by']);
            $this->assertSame(404, $this->request('GET', '?client_id=' . $payload['id'])['status']);
        } finally {
            RestApiEnv::rows(self::$conn, 'DROP TRIGGER b4_pause_upload ON pool_files');
            RestApiEnv::rows(self::$conn, 'DROP FUNCTION b4_pause_upload()');
        }
        if (isset($payload['extracted_data']['reminder_id'])) {
            $recipient = RestApiEnv::rows(self::$conn, 'SELECT r.resolved_at FROM notification_recipients r JOIN notifications n ON n.id=r.notification_id WHERE n.reminder_id=$1', [$payload['extracted_data']['reminder_id']]);
            $this->assertNull($recipient[0]['resolved_at']);
        }
        $retry = $this->request('POST', '', $payload);
        $this->assertSame(200, $retry['status'], $retry['body']);
        $this->assertSame('ubehandlet', $retry['json']['data']['status']);
        $this->assertCount(1, RestApiEnv::rows(self::$conn, 'SELECT * FROM pool_files WHERE client_id=$1', [$payload['id']]));
        $this->assertSame($this->pdf(), $this->request('GET', '?client_id=' . $payload['id'] . '&download=true')['body']);
    }
}
