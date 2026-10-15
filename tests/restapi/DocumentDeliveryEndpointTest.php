<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/DocumentDeliveryEndpointTest.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Cover frozen customer snapshots, SMTP acceptance stages, concurrent mail and print-file isolation.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../fixtures/easyubl/DocumentFixture.php';
require_once __DIR__ . '/../../includes/mobileUpdates.php';
require_once __DIR__ . '/../../includes/db_query.php';
require_once __DIR__ . '/../../restapi/services/DocumentService.php';

final class DocumentDeliveryEndpointTest extends TestCase
{
    private const DELIVER = '/restapi/endpoints/v1/debitor/sales/deliver.php';
    private const PDF = '/restapi/endpoints/v1/debitor/documents/pdf.php';
    private static $tenant;
    private static $stub;
    private static int $port;
    private array $fixture;
    private array $server;
    private ?array $formRows = null;

    public static function setUpBeforeClass(): void
    {
        if (($reason = RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenant();
        self::$tenant = RestApiEnv::connect(RestApiEnv::testDb());
        mobileTenantUpdates(self::$tenant);
        self::$port = self::freePort();
        self::$stub = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . self::$port, __DIR__ . '/../fixtures/easyubl/easyubl_stub.php'],
            [0 => ['pipe','r'], 1 => ['file','/tmp/saldi-b5-rest-stub.log','a'], 2 => ['file','/tmp/saldi-b5-rest-stub.log','a']], $pipes);
        usleep(200000);
        RestApiEnv::accessToken();
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$stub)) {
            proc_terminate(self::$stub);
            proc_close(self::$stub);
        }
        if (self::$tenant) {
            pg_close(self::$tenant);
            RestApiEnv::teardownTenant();
        }
    }

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $_SERVER['REQUEST_URI'] = self::DELIVER;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        DocumentFixture::configureMail(self::$tenant);
        $this->fixture = DocumentFixture::seed(self::$tenant);
        RestApiEnv::rows(self::$tenant, "DELETE FROM settings WHERE var_grp='easyUBL'");
        foreach (['companyID' => 'local-company', 'updatedCompany' => 'true', 'apiKey' => bin2hex(random_bytes(16)),
            'base_url' => 'http://127.0.0.1:' . self::$port . '/success'] as $name => $value) {
            RestApiEnv::rows(self::$tenant, "INSERT INTO settings(var_grp,var_name,var_value) VALUES('easyUBL',$1,$2)", [$name, $value]);
        }
    }

    protected function tearDown(): void
    {
        if ($this->formRows !== null) {
            RestApiEnv::rows(self::$tenant, "DELETE FROM formularer WHERE art='3'");
            foreach ($this->formRows as $row) {
                RestApiEnv::rows(self::$tenant, 'INSERT INTO formularer SELECT * FROM json_populate_record(NULL::formularer,$1::json)',
                    [json_encode($row, JSON_THROW_ON_ERROR)]);
            }
            $this->formRows = null;
        }
        $_SERVER = $this->server;
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        return $port;
    }

    private function body(string $channel = 'email'): array
    {
        $random = bin2hex(random_bytes(16));
        $clientId = substr($random, 0, 8) . '-' . substr($random, 8, 4) . '-' . substr($random, 12, 4)
            . '-' . substr($random, 16, 4) . '-' . substr($random, 20);
        return ['client_id' => $clientId, 'order_id' => $this->fixture['order_id'], 'channel' => $channel];
    }

    /** @return array{status:int,json:array|null,body:string,headers:array} */
    private function request(string $method, string $path, ?array $body = null, bool $authenticated = true): array
    {
        $headers = [];
        $requestHeaders = $authenticated ? RestApiEnv::authHeaders() : [];
        $handle = curl_init(RestApiEnv::baseUrl() . $path);
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 40,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            }]);
        if ($body !== null) {
            $requestHeaders[] = 'Content-Type: application/json';
            curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        }
        curl_setopt($handle, CURLOPT_HTTPHEADER, $requestHeaders);
        $raw = curl_exec($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        self::assertIsString($raw, $error);
        return ['status' => $status, 'json' => json_decode($raw, true), 'body' => $raw, 'headers' => $headers];
    }

    private function assertInvoiceStands(): void
    {
        $row = RestApiEnv::rows(self::$tenant, 'SELECT status,fakturanr,sum,moms FROM ordrer WHERE id=$1', [$this->fixture['order_id']])[0];
        self::assertSame('3', $row['status']);
        self::assertSame('91901', $row['fakturanr']);
        self::assertSame(250.0, (float)$row['sum']);
        self::assertSame(62.5, (float)$row['moms']);
    }

    private function assertOutcome(array $response, string $state, ?string $reason): array
    {
        self::assertSame(200, $response['status'], $response['body']);
        self::assertStringStartsWith('application/json', $response['headers']['content-type']);
        self::assertTrue($response['json']['success']);
        $delivery = $response['json']['data']['delivery'];
        self::assertSame($state, $delivery['state'], $response['body']);
        self::assertSame($reason, $delivery['error_reason']);
        $row = RestApiEnv::rows(self::$tenant, 'SELECT state,error_reason,finished_at FROM document_deliveries WHERE id=$1', [$delivery['id']])[0];
        self::assertSame($state === 'none' ? 'sent' : $state, $row['state']);
        self::assertSame($reason, $row['error_reason']);
        self::assertNotNull($row['finished_at']);
        $this->assertInvoiceStands();
        return $delivery;
    }

    public function testEmailDeliveryReplaySendsExactlyOneMailAndANewIntentSendsOnceMore(): void
    {
        $body = $this->body();
        $first = $this->request('POST', self::DELIVER, $body);
        $delivery = $this->assertOutcome($first, 'sent', null);
        $replay = $this->request('POST', self::DELIVER, $body);
        self::assertSame($first['json'], $replay['json']);
        self::assertSame('true', $replay['headers']['idempotent-replayed']);
        self::assertSame(1, DocumentFixture::messages($this->fixture['recipient'])['total']);
        self::assertSame(1, (int)RestApiEnv::rows(self::$tenant, 'SELECT count(*) FROM document_deliveries WHERE id=$1', [$delivery['id']])[0]['count']);
        $this->assertOutcome($this->request('POST', self::DELIVER, $this->body()), 'sent', null);
        self::assertSame(2, DocumentFixture::messages($this->fixture['recipient'])['total']);
    }

    public function testEmailOverrideDoesNotChangeTheOrderAndPdfOpens(): void
    {
        $override = 'override-' . bin2hex(random_bytes(6)) . '@example.invalid';
        $body = $this->body();
        $body['email'] = $override;
        $this->assertOutcome($this->request('POST', self::DELIVER, $body), 'sent', null);
        self::assertSame(1, DocumentFixture::messages($override)['total']);
        self::assertSame(0, DocumentFixture::messages($this->fixture['recipient'])['total']);
        $response = $this->request('GET', self::PDF . '?order_id=' . $this->fixture['order_id']);
        self::assertSame(200, $response['status'], $response['body']);
        self::assertSame('application/pdf', $response['headers']['content-type']);
        self::assertSame('inline; filename="Faktura-91901.pdf"', $response['headers']['content-disposition']);
        self::assertStringStartsWith('%PDF-', $response['body']);
        file_put_contents('/tmp/saldi-b5-invoice.pdf', $response['body']);
        $info = shell_exec('/usr/bin/pdfinfo /tmp/saldi-b5-invoice.pdf');
        self::assertMatchesRegularExpression('/Pages:\s+[1-9][0-9]*/', $info);
        $process = proc_open(['/usr/bin/gs','-q','-dBATCH','-dNOPAUSE','-sDEVICE=nullpage','/tmp/saldi-b5-invoice.pdf'],
            [0 => ['pipe','r'], 1 => ['file','/tmp/saldi-b5-pdf.log','a'], 2 => ['file','/tmp/saldi-b5-pdf.log','a']], $pipes);
        self::assertSame(0, proc_close($process));
    }

    public function testPdfForTilbudOrderAndDunningDoesNotSendMail(): void
    {
        foreach ([[0,'DO'],[1,'DO'],[2,'R1'],[2,'R2'],[2,'R3']] as [$status, $art]) {
            $fixture = DocumentFixture::seed(self::$tenant, $status, $art);
            $response = $this->request('GET', self::PDF . '?order_id=' . $fixture['order_id']);
            self::assertSame(200, $response['status'], $response['body']);
            self::assertStringStartsWith('%PDF-', $response['body']);
            self::assertSame(0, DocumentFixture::messages($fixture['recipient'])['total']);
        }
    }

    public function testNoneAndClientIdReuse(): void
    {
        $body = $this->body('none');
        $this->assertOutcome($this->request('POST', self::DELIVER, $body), 'none', null);
        $body['channel'] = 'email';
        $response = $this->request('POST', self::DELIVER, $body);
        self::assertSame(409, $response['status']);
        self::assertSame('conflict', $response['json']['data']['error']);
        self::assertSame('client_id_reused', $response['json']['data']['details']['reason']);
        self::assertSame(0, DocumentFixture::messages($this->fixture['recipient'])['total']);
    }

    public function testMissingIdentityInvalidInputsAndUnconfiguredEanRemainJson(): void
    {
        self::assertSame(401, $this->request('POST', self::DELIVER, $this->body(), false)['status']);
        self::assertSame(401, $this->request('GET', self::PDF . '?order_id=1', null, false)['status']);
        $body = $this->body();
        unset($body['client_id']);
        $response = $this->request('POST', self::DELIVER, $body);
        self::assertSame(422, $response['status']);
        self::assertSame('client_id_required', $response['json']['data']['details']['reason']);
        $body = $this->body();
        $body['email'] = "invalid\r\nrecipient";
        $response = $this->request('POST', self::DELIVER, $body);
        self::assertSame(422, $response['status']);
        self::assertSame('invalid_email', $response['json']['data']['details']['reason']);
        RestApiEnv::rows(self::$tenant, "DELETE FROM settings WHERE var_grp='easyUBL' AND var_name='companyID'");
        $response = $this->request('POST', self::DELIVER, $this->body('ean'));
        self::assertSame(422, $response['status']);
        self::assertSame('not_configured', $response['json']['data']['error']);
        self::assertSame(0, (int)RestApiEnv::rows(self::$tenant, 'SELECT count(*) FROM document_deliveries WHERE ordre_id=$1', [$this->fixture['order_id']])[0]['count']);
        self::assertSame(422, $this->request('GET', self::PDF . '?order_id[]=1')['status']);
        self::assertSame(404, $this->request('GET', self::PDF . '?order_id=2147483647')['status']);
        self::assertSame(405, $this->request('GET', self::DELIVER)['status']);
        self::assertSame(405, $this->request('POST', self::PDF, [])['status']);
    }

    public function testUnreachableSmtpIsFailedAndReplayDoesNotRetry(): void
    {
        $port = self::freePort();
        RestApiEnv::rows(self::$tenant, "UPDATE adresser SET felt_1=$1 WHERE art='S'", ['127.0.0.1:' . $port]);
        $body = $this->body();
        $response = $this->request('POST', self::DELIVER, $body);
        $this->assertOutcome($response, 'failed', 'smtp_failed');
        self::assertSame($response['json'], $this->request('POST', self::DELIVER, $body)['json']);
    }

    public function testMailpitRefusedRecipientReturnsFailedWithoutASecondAttempt(): void
    {
        $binary = getenv('SALDI_B5_MAILPIT_BINARY') ?: '/tmp/saldi-b5-mailpit';
        if (!is_executable($binary)) {
            self::markTestSkipped('An isolated Mailpit binary is required via SALDI_B5_MAILPIT_BINARY');
        }
        $smtpPort = self::freePort();
        $httpPort = self::freePort();
        $process = proc_open([$binary, '--smtp', '127.0.0.1:' . $smtpPort, '--listen', '127.0.0.1:' . $httpPort,
            '--smtp-allowed-recipients', '@allowed.invalid$', '--disable-version-check', '--smtp-disable-rdns'],
            [0 => ['pipe','r'], 1 => ['file','/tmp/saldi-b5-mailpit.log','a'], 2 => ['file','/tmp/saldi-b5-mailpit.log','a']], $pipes);
        try {
            usleep(300000);
            RestApiEnv::rows(self::$tenant, "UPDATE adresser SET felt_1=$1 WHERE art='S'", ['127.0.0.1:' . $smtpPort]);
            $body = $this->body();
            $response = $this->request('POST', self::DELIVER, $body);
            $this->assertOutcome($response, 'failed', 'smtp_failed');
            self::assertSame($response['json'], $this->request('POST', self::DELIVER, $body)['json']);
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
    }

    /** @return array<string,array{string,string,string|null}> */
    public static function eanOutcomes(): array
    {
        return ['success' => ['success','sent',null], 'API rejection' => ['api_error','failed','api_error'],
            'empty response' => ['empty','unknown','empty'], 'invalid response' => ['invalid','unknown','invalid_json'],
            'company setup' => ['company_failure','failed','company_setup_failed'], 'missing fields' => ['missing_fields','failed','missing_fields'],
            'company ID error' => ['company_id_error','failed','company_setup_failed'],
            'missing lines' => ['missing_lines','failed','missing_fields'],
            'missing routing' => ['missing_routing','failed','missing_fields'], 'transport' => ['transport','unknown','transport']];
    }

    #[DataProvider('eanOutcomes')]
    public function testEanOutcomesAreStateAccurateJsonAndKeepTheInvoice(string $scenario, string $state, ?string $reason): void
    {
        $url = 'http://127.0.0.1:' . self::$port . '/' . $scenario;
        if ($scenario === 'transport') {
            $url = 'http://127.0.0.1:' . self::freePort();
        }
        RestApiEnv::rows(self::$tenant, "UPDATE settings SET var_value=$1 WHERE var_grp='easyUBL' AND var_name='base_url'", [$url]);
        if ($scenario === 'company_failure') {
            RestApiEnv::rows(self::$tenant, "DELETE FROM settings WHERE var_grp='easyUBL' AND var_name='updatedCompany'");
        } elseif ($scenario === 'company_id_error') {
            RestApiEnv::rows(self::$tenant, "UPDATE settings SET var_value='error' WHERE var_grp='easyUBL' AND var_name='companyID'");
        } elseif ($scenario === 'missing_lines') {
            RestApiEnv::rows(self::$tenant, 'DELETE FROM ordrelinjer WHERE ordre_id=$1', [$this->fixture['order_id']]);
        } elseif ($scenario === 'missing_fields') {
            RestApiEnv::rows(self::$tenant, "UPDATE ordrer SET addr1='',postnr='',bynavn='',lev_addr1='',lev_postnr='',lev_bynavn='' WHERE id=$1", [$this->fixture['order_id']]);
        } elseif ($scenario === 'missing_routing') {
            RestApiEnv::rows(self::$tenant, "UPDATE ordrer SET ean='',cvrnr='' WHERE id=$1", [$this->fixture['order_id']]);
            RestApiEnv::rows(self::$tenant, "UPDATE adresser SET ean='' WHERE id=$1", [$this->fixture['customer_id']]);
        }
        $body = $this->body('ean');
        $response = $this->request('POST', self::DELIVER, $body);
        $this->assertOutcome($response, $state, $reason);
        $replay = $this->request('POST', self::DELIVER, $body);
        self::assertSame($response['json'], $replay['json']);
        self::assertSame('true', $replay['headers']['idempotent-replayed']);
        if (in_array($scenario, ['api_error','empty','invalid','transport'], true)) {
            $row = RestApiEnv::rows(self::$tenant, 'SELECT digital_status FROM ordrer WHERE id=$1', [$this->fixture['order_id']])[0];
            self::assertSame('SendFailed', $row['digital_status']);
        }
        if (str_starts_with($scenario, 'missing_')) {
            self::assertNotEmpty($response['json']['data']['delivery']['missing_fields']);
        }
    }

    public function testSendingReplayIsInProgressThenExpiresToUnknownWithoutMail(): void
    {
        $body = $this->body('none');
        $response = $this->request('POST', self::DELIVER, $body);
        $deliveryId = $response['json']['data']['delivery']['id'];
        RestApiEnv::rows(self::$tenant, "UPDATE document_deliveries SET state='sending',finished_at=NULL WHERE id=$1", [$deliveryId]);
        $stored = $response['json'];
        $stored['data']['delivery']['state'] = 'sending';
        RestApiEnv::rows(self::$tenant, 'UPDATE mobile_idempotency SET response_json=$1 WHERE resource_id=$2', [json_encode($stored), $deliveryId]);
        $replay = $this->request('POST', self::DELIVER, $body);
        self::assertSame(409, $replay['status']);
        self::assertSame('in_progress', $replay['json']['data']['details']['reason']);
        RestApiEnv::rows(self::$tenant, "UPDATE document_deliveries SET created_at=now()-interval '11 minutes' WHERE id=$1", [$deliveryId]);
        $replay = $this->request('POST', self::DELIVER, $body);
        $this->assertOutcome($replay, 'unknown', null);
        self::assertSame('true', $replay['headers']['idempotent-replayed']);
        self::assertSame(0, DocumentFixture::messages($this->fixture['recipient'])['total']);
    }

    public function testParallelFirstSubmissionsCommitOneIntentBeforeOneOutsideCall(): void
    {
        $scenario = 'slow-' . bin2hex(random_bytes(8));
        RestApiEnv::rows(self::$tenant, "UPDATE settings SET var_value=$1 WHERE var_grp='easyUBL' AND var_name='base_url'",
            ['http://127.0.0.1:' . self::$port . '/' . $scenario]);
        $body = $this->body('ean');
        $multi = curl_multi_init();
        $handles = [];
        for ($index = 0; $index < 2; $index++) {
            $handle = curl_init(RestApiEnv::baseUrl() . self::DELIVER);
            curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 15,
                CURLOPT_POSTFIELDS => json_encode($body), CURLOPT_HTTPHEADER => array_merge(RestApiEnv::authHeaders(), ['Content-Type: application/json'])]);
            $handles[] = $handle;
            curl_multi_add_handle($multi, $handle);
        }
        try {
            do {
                curl_multi_exec($multi, $running);
                if ($running) {
                    curl_multi_select($multi, 0.1);
                }
            } while ($running);
            $statuses = [];
            foreach ($handles as $handle) {
                $statuses[] = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
                $result = json_decode(curl_multi_getcontent($handle), true);
                self::assertIsArray($result);
            }
            sort($statuses);
            self::assertSame([200,409], $statuses);
        } finally {
            foreach ($handles as $handle) {
                curl_multi_remove_handle($multi, $handle);
                curl_close($handle);
            }
            curl_multi_close($multi);
        }
        $requests = file('/tmp/saldi-b5-stub-events.log', FILE_IGNORE_NEW_LINES);
        $sendCalls = array_filter($requests, static fn(string $path): bool => str_contains($path, '/' . $scenario . '/api/SendDocuments/'));
        self::assertCount(1, $sendCalls);
        self::assertSame(1, (int)RestApiEnv::rows(self::$tenant, 'SELECT count(*) FROM document_deliveries WHERE ordre_id=$1', [$this->fixture['order_id']])[0]['count']);
        $this->assertOutcome($this->request('POST', self::DELIVER, $body), 'sent', null);
    }

    public function testStoredDeliveryReplaysAfterTheWebDeletesTheOrder(): void
    {
        $body = $this->body('none');
        $response = $this->request('POST', self::DELIVER, $body);
        RestApiEnv::rows(self::$tenant, 'DELETE FROM ordrelinjer WHERE ordre_id=$1', [$this->fixture['order_id']]);
        RestApiEnv::rows(self::$tenant, 'DELETE FROM ordrer WHERE id=$1', [$this->fixture['order_id']]);
        $replay = $this->request('POST', self::DELIVER, $body);
        self::assertSame(200, $replay['status']);
        self::assertSame($response['json'], $replay['json']);
        self::assertSame('true', $replay['headers']['idempotent-replayed']);
    }

    public function testPdfReadAndDeliveryWriteRequireCurrentDebitorordreRights(): void
    {
        $user = RestApiEnv::rows(self::$tenant, 'SELECT id,rettigheder FROM brugere WHERE brugernavn=$1', [RestApiEnv::user()])[0];
        try {
            RestApiEnv::rows(self::$tenant, "UPDATE brugere SET rettigheder='0000020000000000' WHERE id=$1", [$user['id']]);
            self::assertSame(200, $this->request('GET', self::PDF . '?order_id=' . $this->fixture['order_id'])['status']);
            $response = $this->request('POST', self::DELIVER, $this->body('none'));
            self::assertSame(403, $response['status']);
            self::assertSame('permission_denied', $response['json']['data']['error']);
            RestApiEnv::rows(self::$tenant, "UPDATE brugere SET rettigheder='0010000000000000' WHERE id=$1", [$user['id']]);
            self::assertSame(403, $this->request('GET', self::PDF . '?order_id=' . $this->fixture['order_id'])['status']);
            self::assertSame(403, $this->request('POST', self::DELIVER, $this->body('none'))['status']);
        } finally {
            RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET rettigheder=$1 WHERE id=$2', [$user['rettigheder'], $user['id']]);
        }
    }

    public function testDunningCreationAndDeliveryCanShareOneAtomicReplayEnvelope(): void
    {
        $user = RestApiEnv::rows(self::$tenant, 'SELECT id FROM brugere WHERE brugernavn=$1', [RestApiEnv::user()])[0];
        $context = new EngineContext(self::$tenant, RestApiEnv::testDb(), RestApiEnv::regnskabId(RestApiEnv::accountOpen()),
            RestApiEnv::user(), (int)$user['id'], date('Y-m-d'));
        $service = new DocumentService($context, 'test/account/' . $user['id']);
        $request = $this->body();
        unset($request['order_id'], $request['channel']);
        $request['konto_id'] = $this->fixture['customer_id'];
        $request['deliver'] = 'email';
        RestApiEnv::rows(self::$tenant, 'BEGIN');
        $dunning = DocumentFixture::seed(self::$tenant, 2, 'R1');
        $response = $service->deliverCreated($dunning['order_id'], 'email', $dunning['recipient'], $request, 'dunning.create',
            $request['konto_id'], ['rykker_id' => $dunning['order_id'], 'level' => 1]);
        self::assertSame(PGSQL_TRANSACTION_IDLE, pg_transaction_status(self::$tenant));
        self::assertSame(201, $response['status']);
        self::assertSame('sent', $response['body']['data']['delivery']['state']);
        self::assertSame($dunning['order_id'], $response['body']['data']['rykker_id']);
        RestApiEnv::rows(self::$tenant, 'BEGIN');
        $replay = (new Idempotency(self::$tenant))->replay('test/account/' . $user['id'], 'dunning.create', $request['konto_id'], $request);
        RestApiEnv::rows(self::$tenant, 'COMMIT');
        self::assertSame($response['body'], $replay['body']);
        self::assertSame(1, DocumentFixture::messages($dunning['recipient'])['total']);
    }

    public function testMissingFormConfigurationReturnsJsonAndDoesNotTerminateTheRequest(): void
    {
        $this->formRows = RestApiEnv::rows(self::$tenant, "SELECT * FROM formularer WHERE art='3'");
        RestApiEnv::rows(self::$tenant, "DELETE FROM formularer WHERE art='3'");
        $response = $this->request('GET', self::PDF . '?order_id=' . $this->fixture['order_id']);
        self::assertSame(422, $response['status']);
        self::assertSame('not_configured', $response['json']['data']['error']);
        $response = $this->request('POST', self::DELIVER, $this->body());
        $this->assertOutcome($response, 'failed', 'form_not_configured');
        self::assertSame(0, DocumentFixture::messages($this->fixture['recipient'])['total']);
    }

    public function testPdfRetainsOtherLanguageAndGenericFormFallbacks(): void
    {
        $this->formRows = RestApiEnv::rows(self::$tenant, "SELECT * FROM formularer WHERE art='3'");
        RestApiEnv::rows(self::$tenant, "UPDATE formularer SET sprog='engelsk' WHERE formular='4' AND art='3'");
        $response = $this->request('GET', self::PDF . '?order_id=' . $this->fixture['order_id']);
        self::assertSame(200, $response['status'], $response['body']);
        self::assertStringStartsWith('%PDF-', $response['body']);
        RestApiEnv::rows(self::$tenant, "UPDATE formularer SET formular='3' WHERE formular='4' AND art='3'");
        $response = $this->request('GET', self::PDF . '?order_id=' . $this->fixture['order_id']);
        self::assertSame(200, $response['status'], $response['body']);
        self::assertStringStartsWith('%PDF-', $response['body']);
        self::assertSame(0, DocumentFixture::messages($this->fixture['recipient'])['total']);
    }

    public function testQuotedMailSubjectsDoNotBreakTheLegacyDuplicateGuard(): void
    {
        $subject = "Customer's invoice";
        RestApiEnv::rows(self::$tenant, 'UPDATE ordrer SET mail_subj=$1 WHERE id=$2', [$subject, $this->fixture['order_id']]);
        $this->assertOutcome($this->request('POST', self::DELIVER, $this->body()), 'sent', null);
        $this->assertOutcome($this->request('POST', self::DELIVER, $this->body()), 'sent', null);
        $messages = DocumentFixture::messages($this->fixture['recipient']);
        self::assertSame(2, $messages['total']);
        self::assertSame($subject, $messages['messages'][0]['Subject']);
    }
    /** @return array{process:resource,path:string} */
    private function startSmtp(string $scenario, ?int $replacementCustomer = null): array
    {
        $port = self::freePort();
        $path = tempnam('/tmp', 'b5-smtp-');
        unlink($path);
        $process = proc_open([PHP_BINARY, __DIR__ . '/../fixtures/easyubl/smtp_stub.php', (string)$port, $scenario,
            $path, RestApiEnv::testDb(), (string)$this->fixture['order_id'], (string)($replacementCustomer ?? 0)],
            [0 => ['pipe','r'], 1 => ['file',$path . '.log','a'], 2 => ['file',$path . '.log','a']], $pipes);
        for ($i = 0; $i < 100 && !is_file($path . '.ready'); $i++) {
            usleep(10000);
        }
        self::assertFileExists($path . '.ready');
        RestApiEnv::rows(self::$tenant, "UPDATE adresser SET felt_1=$1 WHERE art='S'", ['127.0.0.1:' . $port]);
        return ['process' => $process, 'path' => $path];
    }

    private function stopSmtp(array $stub): void
    {
        proc_terminate($stub['process']);
        proc_close($stub['process']);
        foreach (glob($stub['path'] . '*') as $path) {
            unlink($path);
        }
    }

    /** @return array<string,array{string,string,string|null}> */
    public static function smtpStages(): array
    {
        return ['mixed RCPT' => ['mixed', 'unknown', 'smtp_unknown'],
            'sender BCC refused' => ['bcc', 'unknown', 'smtp_unknown'],
            'lost DATA acknowledgement' => ['disconnect', 'unknown', 'smtp_unknown'],
            'all recipients refused' => ['all_rejected', 'failed', 'smtp_failed'],
            'explicit DATA refusal' => ['data_rejected', 'failed', 'smtp_failed']];
    }

    #[DataProvider('smtpStages')]
    public function testSmtpPartialAndUncertainAcceptanceCannotEncourageResending(string $scenario, string $state, ?string $reason): void
    {
        $stub = $this->startSmtp($scenario);
        try {
            $body = $this->body();
            if ($scenario === 'mixed') {
                $body['email'] = $this->fixture['recipient'] . ';reject-cc@example.invalid';
            } elseif ($scenario === 'bcc') {
                RestApiEnv::rows(self::$tenant, "UPDATE adresser SET email='reject-sender@example.invalid' WHERE art='S'");
            }
            $response = $this->request('POST', self::DELIVER, $body);
            $this->assertOutcome($response, $state, $reason);
            self::assertSame($response['json'], $this->request('POST', self::DELIVER, $body)['json']);
            $captured = json_decode(file_get_contents($stub['path']), true, 512, JSON_THROW_ON_ERROR);
            if ($scenario === 'all_rejected') {
                self::assertSame('', $captured['data']);
            } else {
                self::assertStringContainsString('Document subject', $captured['data']);
                self::assertTrue($captured['recipients'][$this->fixture['recipient']]);
            }
            if ($scenario === 'mixed' || $scenario === 'bcc') {
                self::assertContains(false, $captured['recipients']);
            }
        } finally {
            $this->stopSmtp($stub);
        }
    }

    /**
     * Hold rendering at its form query, queue a competing edit, then release rendering.
     * An implementation that commits before rendering permits the edit to finish at this barrier.
     * @return array{status:int,body:string,json:array}
     */
    private function deliverWhileCustomerChanges(array $body, int $replacementCustomer): array
    {
        $barrier = RestApiEnv::connect(RestApiEnv::testDb());
        $editor = RestApiEnv::connect(RestApiEnv::testDb());
        $multi = curl_multi_init();
        $handle = curl_init(RestApiEnv::baseUrl() . self::DELIVER);
        try {
            RestApiEnv::rows($barrier, 'BEGIN');
            RestApiEnv::rows($barrier, 'LOCK TABLE formularer IN ACCESS EXCLUSIVE MODE');
            curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => array_merge(RestApiEnv::authHeaders(), ['Content-Type: application/json']),
                CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR)]);
            curl_multi_add_handle($multi, $handle);
            $waiting = [];
            for ($i = 0; $i < 200; $i++) {
                curl_multi_exec($multi, $running);
                $waiting = RestApiEnv::rows(self::$tenant,
                    "SELECT pid FROM pg_stat_activity WHERE datname=$1 AND wait_event='relation' AND query LIKE '%formularer%'",
                    [RestApiEnv::testDb()]);
                if ($waiting) {
                    break;
                }
                usleep(10000);
            }
            self::assertNotEmpty($waiting, 'Delivery must reach the rendering barrier');
            self::assertTrue(pg_send_query_params($editor,
                "UPDATE ordrer SET konto_id=$1,email='changed@example.invalid',firmanavn='Changed customer',
                    mail_subj='Changed subject',mail_text='Changed body',status=3 WHERE id=$2",
                [$replacementCustomer, $this->fixture['order_id']]));
            // Wait until the editor either completes or queues behind the delivery's row lock.
            $editStarted = false;
            for ($i = 0; $i < 200; $i++) {
                pg_consume_input($editor);
                $activity = RestApiEnv::rows(self::$tenant,
                    'SELECT wait_event_type FROM pg_stat_activity WHERE pid=$1', [pg_get_pid($editor)])[0];
                if (!pg_connection_busy($editor) || $activity['wait_event_type'] === 'Lock') {
                    $editStarted = true;
                    break;
                }
                usleep(10000);
            }
            self::assertTrue($editStarted, 'Concurrent customer edit must start before rendering resumes');
            self::assertTrue(pg_connection_busy($editor), 'The order lock must protect rendering and mail preparation');
            self::assertSame('Lock', $activity['wait_event_type']);
            RestApiEnv::rows($barrier, 'COMMIT');
            do {
                curl_multi_exec($multi, $running);
                if ($running) {
                    curl_multi_select($multi, 0.1);
                }
            } while ($running);
            $raw = curl_multi_getcontent($handle);
            $editResult = pg_get_result($editor);
            self::assertSame(PGSQL_COMMAND_OK, pg_result_status($editResult));
            return ['status' => (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $raw,
                'json' => json_decode($raw, true, 512, JSON_THROW_ON_ERROR)];
        } finally {
            RestApiEnv::rows($barrier, 'ROLLBACK');
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
            curl_multi_close($multi);
            pg_close($barrier);
            pg_close($editor);
        }
    }

    public function testCustomerChangeAfterIntentCommitUsesTheFrozenQuoteAndMail(): void
    {
        $replacement = DocumentFixture::seed(self::$tenant, 0);
        RestApiEnv::rows(self::$tenant, 'UPDATE ordrer SET status=0,mail_subj=$1,mail_text=$2 WHERE id=$3',
            ['$firmanavn', 'Customer $firmanavn $kontonr', $this->fixture['order_id']]);
        $stub = $this->startSmtp('snapshot', $replacement['customer_id']);
        try {
            $body = $this->body();
            $response = $this->deliverWhileCustomerChanges($body, $replacement['customer_id']);
            self::assertSame(200, $response['status'], $response['body']);
            $delivery = $response['json']['data']['delivery'];
            self::assertSame('sent', $delivery['state'], $response['body'] . (is_file($stub['path'] . '.log') ? file_get_contents($stub['path'] . '.log') : ''));
            self::assertSame($this->fixture['recipient'], $delivery['recipient']);
            $capture = json_decode(file_get_contents($stub['path']), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(1, $capture['intent_committed']);
            self::assertStringContainsString('Subject: Document customer', $capture['data']);
            self::assertStringContainsString('Customer Document customer', $capture['data']);
            self::assertStringNotContainsString('Changed subject', $capture['data']);
            self::assertStringNotContainsString('Changed body', $capture['data']);
            self::assertSame(1, preg_match('/Content-Type: application\/pdf[^\r\n]*\r?\n(?:(?!\r?\n\r?\n).)*?\r?\n\r?\n([A-Za-z0-9+\/=\r\n]+)/s', $capture['data'], $attachment));
            $pdf = base64_decode(preg_replace('/\s+/', '', $attachment[1]), true);
            self::assertStringStartsWith('%PDF-', $pdf);
            $pdfPath = $stub['path'] . '.pdf';
            file_put_contents($pdfPath, $pdf);
            $text = shell_exec('/usr/bin/gs -q -dBATCH -dNOPAUSE -sDEVICE=txtwrite -o - ' . escapeshellarg($pdfPath));
            self::assertStringContainsString('Tilbud', $text);
            self::assertStringContainsString('Document line', $text);
            self::assertStringNotContainsString('Changed line', $text);
            $current = RestApiEnv::rows(self::$tenant, 'SELECT konto_id,firmanavn FROM ordrer WHERE id=$1', [$this->fixture['order_id']])[0];
            self::assertSame((string)$replacement['customer_id'], $current['konto_id']);
            self::assertSame('Changed customer', $current['firmanavn']);
            self::assertSame($response['json'], $this->request('POST', self::DELIVER, $body)['json']);
        } finally {
            $this->stopSmtp($stub);
        }
    }

    public function testDunningFileRenderingPreservesPendingBrowserDownloads(): void
    {
        $fixture = DocumentFixture::seed(self::$tenant, 2, 'R1');
        $user = RestApiEnv::rows(self::$tenant, 'SELECT id FROM brugere WHERE brugernavn=$1', [RestApiEnv::user()])[0];
        $directory = __DIR__ . '/../../temp/' . RestApiEnv::testDb() . '/' . $user['id'] . '_pending';
        mkdir($directory, 0775, true);
        $pending = $directory . '/browser.pdf';
        file_put_contents($pending, 'Pending browser print');
        try {
            $response = $this->request('GET', self::PDF . '?order_id=' . $fixture['order_id']);
            self::assertSame(200, $response['status'], $response['body']);
            self::assertStringStartsWith('%PDF-', $response['body']);
            self::assertSame('Pending browser print', file_get_contents($pending));
        } finally {
            if (is_file($pending)) {
                unlink($pending);
                rmdir($directory);
            }
        }
    }

    public function testMultipageCreditNoteResolvesCreditedInvoiceFooterAndCompletesMail(): void
    {
        $this->formRows = RestApiEnv::rows(self::$tenant, "SELECT * FROM formularer WHERE art='3'");
        $original = DocumentFixture::seed(self::$tenant);
        $credit = DocumentFixture::seed(self::$tenant, 3, 'DK');
        RestApiEnv::rows(self::$tenant, "UPDATE ordrer SET kred_ord_id=$1 WHERE id=$2", [$original['order_id'], $credit['order_id']]);
        RestApiEnv::rows(self::$tenant,
            "INSERT INTO formularer(formular,art,side,sprog,beskrivelse,xa,ya,str,fed,kursiv,justering,font,color)
             VALUES(5,3,'!S','dansk',$1,30,30,10,'','','V','Helvetica','000000000')", ['Credited invoice $ordre_creditedinvoice']);
        for ($i = 2; $i <= 100; $i++) {
            RestApiEnv::rows(self::$tenant,
                "INSERT INTO ordrelinjer(ordre_id,varenr,beskrivelse,enhed,posnr,antal,pris,rabat,momssats,procent)
                 VALUES($1,'DOC','Credit note line','stk',$2,-1,1,0,25,100)", [$credit['order_id'], $i]);
        }
        $response = $this->request('GET', self::PDF . '?order_id=' . $credit['order_id']);
        self::assertSame(200, $response['status'], $response['body']);
        self::assertStringStartsWith('%PDF-', $response['body']);
        $path = tempnam('/tmp', 'b5-credit-');
        try {
            file_put_contents($path, $response['body']);
            $text = shell_exec('/usr/bin/gs -q -dBATCH -dNOPAUSE -sDEVICE=txtwrite -o - ' . escapeshellarg($path));
            self::assertStringContainsString('Credited invoice 91901', $text);
            self::assertGreaterThanOrEqual(2, substr_count($text, 'Credited invoice 91901'));
        } finally {
            unlink($path);
        }
        $body = $this->body();
        $body['order_id'] = $credit['order_id'];
        $response = $this->request('POST', self::DELIVER, $body);
        self::assertSame(200, $response['status'], $response['body']);
        self::assertSame('sent', $response['json']['data']['delivery']['state']);
        self::assertSame(1, DocumentFixture::messages($credit['recipient'])['total']);
        self::assertSame($response['json'], $this->request('POST', self::DELIVER, $body)['json']);
    }

    public function testSimultaneousEmailDeliveriesWithDistinctClientIdsEachSendOnce(): void
    {
        $bodyA = $this->body();
        $bodyB = $this->body();
        $multi = curl_multi_init();
        $handles = [];
        try {
            foreach ([$bodyA, $bodyB] as $body) {
                $handle = curl_init(RestApiEnv::baseUrl() . self::DELIVER);
                curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => array_merge(RestApiEnv::authHeaders(), ['Content-Type: application/json']),
                    CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR), CURLOPT_TIMEOUT => 30]);
                curl_multi_add_handle($multi, $handle);
                $handles[] = $handle;
            }
            do {
                curl_multi_exec($multi, $running);
                if ($running) {
                    curl_multi_select($multi, 1);
                }
            } while ($running);
            foreach ($handles as $handle) {
                $raw = curl_multi_getcontent($handle);
                self::assertSame(200, (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $raw);
                self::assertSame('sent', json_decode($raw, true, 512, JSON_THROW_ON_ERROR)['data']['delivery']['state']);
            }
        } finally {
            foreach ($handles as $handle) {
                curl_multi_remove_handle($multi, $handle);
                curl_close($handle);
            }
            curl_multi_close($multi);
        }
        self::assertSame(2, DocumentFixture::messages($this->fixture['recipient'])['total']);
        foreach ([$bodyA, $bodyB] as $body) {
            $this->assertOutcome($this->request('POST', self::DELIVER, $body), 'sent', null);
        }
        self::assertSame(2, DocumentFixture::messages($this->fixture['recipient'])['total']);
    }

}
