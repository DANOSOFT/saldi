<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/einvoice/semanticClient.test.php --- ver 5.1.0 --- 2026-11-09 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261109 CL/MMR SD-815 Created (draft). SemanticClient against a fake transport: headers, paths, the base64 body, the classification of answers, and that the key never reaches a message.
//                 The class sits in a namespace because PHPUnit wants it named like the file, and SemanticClient is the class under test.

declare(strict_types=1);

namespace SaldiTests\Einvoice;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/einvoice/semanticClient.php';

final class semanticClient extends TestCase
{
    private const BASE = 'https://semantic.example.invalid';

    /** @var string Made per test, so no credential-looking literal is in the file. */
    private string $tenantId = '';
    /** @var string */
    private string $apiKey = '';
    /** @var list<array{method: string, url: string, headers: list<string>, body: ?string}> */
    private array $calls = array();
    /** @var list<array{status: int, body: string, error: ?string}|\Throwable> */
    private array $answers = array();

    protected function setUp(): void
    {
        $this->tenantId = self::uuid();
        $this->apiKey = self::uuid();
        $this->calls = array();
        $this->answers = array();
    }

    private static function uuid(): string
    {
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
    }

    private function client(): \SemanticClient
    {
        $transport = function (string $method, string $url, array $headers, ?string $body): array {
            $this->calls[] = array('method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body);
            $answer = array_shift($this->answers) ?? array('status' => 200, 'body' => '', 'error' => null);
            if ($answer instanceof \Throwable) {
                throw $answer;
            }
            return $answer;
        };
        return new \SemanticClient(self::BASE, $this->tenantId, $this->apiKey, $transport);
    }

    private function answer(int $status, string $body = '', ?string $error = null): void
    {
        $this->answers[] = array('status' => $status, 'body' => $body, 'error' => $error);
    }

    public function testAuthorizationHeaderIsTenantAndKeyFromOnePlace(): void
    {
        $this->answer(200, '5140');
        $this->client()->getOutgoingDocumentStatus(42);
        $this->assertCount(1, $this->calls);
        $expected = 'Authorization: ' . \SemanticClient::authorizationValue($this->tenantId, $this->apiKey);
        $this->assertSame($this->tenantId . '&' . $this->apiKey, \SemanticClient::authorizationValue($this->tenantId, $this->apiKey));
        $this->assertContains($expected, $this->calls[0]['headers']);
        $this->assertContains('Accept: application/json', $this->calls[0]['headers']);
        foreach ($this->calls[0]['headers'] as $header) {
            $this->assertStringNotContainsString('Bearer', $header);
        }
    }

    public function testCreateOutgoingDocumentSendsBase64Utf8XmlAndSender(): void
    {
        $sender = self::uuid();
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Invoice><cbc:Note>Æblegrød på Østergade</cbc:Note></Invoice>";
        $this->answer(200, '987654321');
        $result = $this->client()->createOutgoingDocument($sender, $xml, \SemanticClient::NETWORK_PEPPOL);

        $call = $this->calls[0];
        $this->assertSame('POST', $call['method']);
        $this->assertSame(self::BASE . '/api/OutgoingDocument/CreateOutgoingDocument?senderEndpointId=' . $sender, $call['url']);
        $this->assertContains('Content-Type: application/json', $call['headers']);
        $body = json_decode((string)$call['body'], true);
        $this->assertSame(array('base64EncodedUtf8DocumentXml', 'networkType'), array_keys($body));
        $this->assertSame($xml, base64_decode($body['base64EncodedUtf8DocumentXml'], true));
        $this->assertSame(3, $body['networkType']);

        $this->assertTrue($result['ok']);
        $this->assertSame('ok', $result['kind']);
        $this->assertSame(987654321, $result['data']);
        $this->assertSame('987654321', \SemanticClient::documentIdFrom($result['data']));
    }

    public function testCreateSendsStatusOnlyWhenGiven(): void
    {
        $this->answer(200, '1');
        $this->client()->createOutgoingDocument(self::uuid(), '<Invoice/>', \SemanticClient::NETWORK_NEMHANDEL, 2);
        $body = json_decode((string)$this->calls[0]['body'], true);
        $this->assertSame(4, $body['networkType']);
        $this->assertSame(2, $body['status']);
    }

    public function testCreateRefusesBadInputWithoutAnyCall(): void
    {
        $client = $this->client();
        $cases = array(
            $client->createOutgoingDocument(self::uuid(), "<Invoice>\xE6</Invoice>", 3),
            $client->createOutgoingDocument(self::uuid(), '<Invoice/>', 5),
            $client->createOutgoingDocument(self::uuid(), '   ', 3),
            $client->createOutgoingDocument('not-a-uuid', '<Invoice/>', 3),
            $client->getOutgoingDocumentStatus('12 OR 1=1'),
            $client->registerInPeppol('../Company'),
            $client->setDefaultEndpoint(self::uuid(), 'x'),
        );
        foreach ($cases as $result) {
            $this->assertFalse($result['ok']);
            $this->assertSame('validation', $result['kind']);
            $this->assertSame(0, $result['httpStatus']);
        }
        $this->assertSame(array(), $this->calls);
    }

    public function testValidateIsAPostOfTheSameHeaderAndReturnsTheReport(): void
    {
        $this->answer(200, 'No errors found');
        $result = $this->client()->validatePeppolDocument('<Invoice/>', 3);
        $this->assertSame('POST', $this->calls[0]['method']);
        $this->assertSame(self::BASE . '/api/OutgoingDocument/ValidatePeppolDocument', $this->calls[0]['url']);
        $this->assertSame(array('base64EncodedUtf8DocumentXml', 'networkType'), array_keys(json_decode((string)$this->calls[0]['body'], true)));
        $this->assertSame('No errors found', $result['data']);
    }

    public function testStatusAndDocumentAreGetsOnTheDocumentId(): void
    {
        $this->answer(200, '5140');
        $this->answer(200, '{"outgoingDocumentId":123,"documentStatus":5101,"errorText":"BR-CO-15"}');
        $client = $this->client();
        $status = $client->getOutgoingDocumentStatus('123');
        $document = $client->getOutgoingDocument(123);
        $this->assertSame(array('GET', self::BASE . '/api/OutgoingDocument/status/123', null),
            array($this->calls[0]['method'], $this->calls[0]['url'], $this->calls[0]['body']));
        $this->assertSame(self::BASE . '/api/OutgoingDocument/123', $this->calls[1]['url']);
        $this->assertSame(5140, $status['data']);
        $this->assertSame('BR-CO-15', $document['data']['errorText']);
    }

    public function testGetOutgoingDocumentsIsAGetWithAJsonBody(): void
    {
        $this->answer(200, '[]');
        $this->client()->getOutgoingDocuments(array('documentNumber' => '1001', 'pageNumber' => 1, 'pageSize' => 10));
        $this->assertSame('GET', $this->calls[0]['method']);
        $this->assertSame(self::BASE . '/api/OutgoingDocument/GetOutgoingDocuments', $this->calls[0]['url']);
        $this->assertSame(array('documentNumber' => '1001', 'pageNumber' => 1, 'pageSize' => 10), json_decode((string)$this->calls[0]['body'], true));
    }

    public function testRegistrationCallsUseTheSwaggerPathsAndVerbs(): void
    {
        $company = self::uuid();
        $endpoint = self::uuid();
        $client = $this->client();
        $client->createCompany(array('companyId' => $company, 'name' => 'Eksempel ApS', 'cvr' => '11111111', 'countryCode' => 'DK'));
        $client->createEndpoint(array('companyId' => $company, 'endpointType' => '0184', 'endpointIdentifier' => '11111111'));
        $client->setDefaultEndpoint($company, $endpoint);
        $client->registerInPeppol($endpoint);
        $client->registerInNemHandel($endpoint);
        $client->getEndpointByIdentifier('11111111', '0184');
        $client->getCompanies(array('vatIdentifier' => '11111111', 'unknown' => 'x'));

        $seen = array_map(function ($call) {
            return $call['method'] . ' ' . substr($call['url'], strlen(self::BASE));
        }, $this->calls);
        $this->assertSame(array(
            'POST /api/Company',
            'POST /api/Endpoint',
            "PATCH /api/Company/$company/defaultEndpoint/$endpoint",
            "PUT /api/Endpoint/registerInPeppol/$endpoint",
            "PUT /api/Endpoint/registerInNemHandel/$endpoint",
            'GET /api/Endpoint/getEndpointId/11111111/0184',
            'GET /api/Company?vatIdentifier=11111111',
        ), $seen);
        $this->assertSame('Eksempel ApS', json_decode((string)$this->calls[0]['body'], true)['name']);
        $this->assertNull($this->calls[3]['body'], 'registerInPeppol has no body');
    }

    public function testLookupsKeepTheirOwnSegmentOrderAndReturnBooleans(): void
    {
        $this->answer(200, 'true');
        $this->answer(200, 'false');
        $this->answer(200, '"maybe"');
        $client = $this->client();
        $peppol = $client->isEndpointInPeppol('0184', '12345678', \SemanticClient::DOCUMENT_TYPE_INVOICE);
        $nemhandel = $client->isEndpointInNemHandel('0088', '5790000000005', \SemanticClient::DOCUMENT_TYPE_CREDITNOTE);
        $unclear = $client->isEndpointInNemHandel('0184', '12345678');

        // Peppol: identifier, then scheme. Nemhandel: scheme, then identifier.
        $this->assertSame(self::BASE . '/api/Endpoint/isEndpointInPeppol/12345678/0184/110', $this->calls[0]['url']);
        $this->assertSame(self::BASE . '/api/Endpoint/isEndpointInNemHandel/0088/5790000000005/120', $this->calls[1]['url']);
        $this->assertTrue($peppol['ok']);
        $this->assertTrue($peppol['data']);
        $this->assertTrue($nemhandel['ok']);
        $this->assertFalse($nemhandel['data']);
        $this->assertFalse($unclear['ok']);
        $this->assertSame('unknown', $unclear['kind']);
        $this->assertNull($unclear['data']);
    }

    public function testPathSegmentsAreEncoded(): void
    {
        $this->answer(200, 'false');
        $this->client()->isEndpointInPeppol('0184', 'a/b c?d', 110);
        $this->assertSame(self::BASE . '/api/Endpoint/isEndpointInPeppol/a%2Fb%20c%3Fd/0184/110', $this->calls[0]['url']);
    }

    public function testPdfIsDecodedFromBase64(): void
    {
        $ubl = self::uuid();
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $this->answer(200, json_encode(base64_encode($pdf)));
        $this->answer(200, '"not base64 !!"');
        $client = $this->client();
        $result = $client->getUblDocumentPdf($ubl);
        $broken = $client->getUblDocumentPdf($ubl);
        $this->assertSame(self::BASE . '/api/UblDocument/pdf/' . $ubl, $this->calls[0]['url']);
        $this->assertTrue($result['ok']);
        $this->assertSame($pdf, $result['data']);
        $this->assertFalse($broken['ok']);
        $this->assertNull($broken['data']);
    }

    /**
     * @return array<string, array{int, string, ?string, string}>
     */
    public static function answers(): array
    {
        return array(
            '200' => array(200, '1', null, 'ok'),
            '204 empty' => array(204, '', null, 'ok'),
            '400' => array(400, '{"title":"Bad"}', null, 'validation'),
            '409 duplicate' => array(409, 'Duplicate', null, 'validation'),
            '413' => array(413, '', null, 'validation'),
            '422' => array(422, '{}', null, 'validation'),
            '401' => array(401, '', null, 'auth'),
            '403' => array(403, 'Forbidden', null, 'auth'),
            '404' => array(404, '', null, 'unknown'),
            '302' => array(302, '', null, 'unknown'),
            '408' => array(408, '', null, 'transient'),
            '429' => array(429, 'Too many', null, 'transient'),
            '500' => array(500, '<html><body>Server Error</body></html>', null, 'transient'),
            '503' => array(503, '', null, 'transient'),
            'no answer' => array(0, '', 'curl 28: Operation timed out', 'transient'),
        );
    }

    #[DataProvider('answers')]
    public function testAnswersAreClassified(int $status, string $body, ?string $error, string $kind): void
    {
        $this->answer($status, $body, $error);
        $result = $this->client()->getOutgoingDocumentStatus(7);
        $this->assertSame($kind, $result['kind']);
        $this->assertSame($kind === 'ok', $result['ok']);
        $this->assertSame($status, $result['httpStatus']);
        if ($kind !== 'ok') {
            $this->assertNull($result['data']);
            $this->assertNotSame('', $result['message']);
        }
    }

    public function testProblemDetailsBecomeAShortMessage(): void
    {
        $this->answer(400, '{"type":"https://tools.ietf.org/html/rfc9110#section-15.5.1","title":"One or more validation errors occurred.","status":400,"errors":{"Xml":["The document is not valid."]}}');
        $result = $this->client()->validatePeppolDocument('<Invoice/>', 3);
        $this->assertSame('validation', $result['kind']);
        $this->assertStringStartsWith('HTTP 400: One or more validation errors occurred.', $result['message']);
        $this->assertStringContainsString('Xml: The document is not valid.', $result['message']);
    }

    public function testKeyAndTenantNeverReachAMessage(): void
    {
        $auth = \SemanticClient::authorizationValue($this->tenantId, $this->apiKey);
        $this->answer(401, 'Authorization "' . $auth . '" is not valid; key ' . strtoupper($this->apiKey) . ' unknown');
        $this->answer(0, '', 'proxy said: ' . rawurlencode($auth));
        $this->answers[] = new \RuntimeException('transport failed with header Authorization: ' . $auth);
        $client = $this->client();
        $results = array(
            $client->getOutgoingDocumentStatus(1),
            $client->getOutgoingDocumentStatus(2),
            $client->getOutgoingDocumentStatus(3),
        );
        $this->assertSame(array('auth', 'transient', 'transient'), array_column($results, 'kind'));
        foreach ($results as $result) {
            $this->assertStringNotContainsStringIgnoringCase($this->apiKey, $result['message']);
            $this->assertStringNotContainsStringIgnoringCase($this->tenantId, $result['message']);
            $this->assertStringNotContainsStringIgnoringCase(rawurlencode($auth), $result['message']);
            $this->assertStringContainsString('***', $result['message']);
        }
    }

    public function testTheXmlNeverReachesAMessage(): void
    {
        $xml = '<?xml version="1.0"?><Invoice>' . str_repeat('<cac:InvoiceLine><cbc:ID>1</cbc:ID><cbc:Note>Linjetekst</cbc:Note></cac:InvoiceLine>', 40) . '</Invoice>';
        $this->answer(400, $xml);
        $result = $this->client()->createOutgoingDocument(self::uuid(), $xml, 3);
        $this->assertLessThanOrEqual(\SemanticClient::MESSAGE_MAX, mb_strlen($result['message'], 'UTF-8'));
        $this->assertStringNotContainsString('<cac:InvoiceLine>', $result['message']);
        $this->assertStringNotContainsString('<Invoice', $result['message']);
    }

    public function testDumpsDoNotShowTheKey(): void
    {
        $client = $this->client();
        ob_start();
        var_dump($client);
        $dump = (string)ob_get_clean() . print_r($client, true);
        $this->assertStringNotContainsString($this->apiKey, $dump);
        $this->assertStringNotContainsString($this->tenantId, $dump);
    }

    public function testConstructorWantsHttpsAndCredentials(): void
    {
        foreach (array(array('http://semantic.example.invalid', 'x', 'y'), array(self::BASE, '', 'y'), array(self::BASE, 'x', ' ')) as $args) {
            try {
                new \SemanticClient($args[0], $args[1], $args[2], function () {
                    return array();
                });
                $this->fail('accepted ' . $args[0]);
            } catch (InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
        $this->assertInstanceOf(\SemanticClient::class, new \SemanticClient('http://localhost:8080/', 'x', 'y', function () {
            return array();
        }));
    }

    public function testDocumentIdIsDigitsOnly(): void
    {
        $this->assertSame('42', \SemanticClient::documentIdFrom(42));
        $this->assertSame('9223372036854775807', \SemanticClient::documentIdFrom('9223372036854775807'));
        $this->assertSame('17', \SemanticClient::documentIdFrom('"17"'));
        $this->assertNull(\SemanticClient::documentIdFrom(0));
        $this->assertNull(\SemanticClient::documentIdFrom(-5));
        $this->assertNull(\SemanticClient::documentIdFrom('12a'));
        $this->assertNull(\SemanticClient::documentIdFrom(null));
        $this->assertNull(\SemanticClient::documentIdFrom(array(1)));
    }

    public function testCredentialsComeFromTheEnvironmentFirstThenTheKeyFile(): void
    {
        $names = array('SALDI_SEMANTIC_BASE_URL', 'SALDI_SEMANTIC_TENANT_ID', 'SALDI_SEMANTIC_API_KEY', 'SALDI_SEMANTIC_WEBHOOK_SECRET', 'SALDI_SEMANTIC_SIGNING_SECRETS');
        $saved = array();
        foreach ($names as $name) {
            $saved[$name] = getenv($name);
            putenv($name);
        }
        $file = tempnam(sys_get_temp_dir(), 'sd815keys');
        $fileKey = self::uuid();
        $secret = bin2hex(random_bytes(20));
        file_put_contents($file, "<?php\n\$otherProviderKey = 'x';\n\$semanticBaseUrl = 'https://dev.semantic.example.invalid/';\n"
            . "\$semanticTenantId = 'tenant-from-file';\n\$semanticApiKey = '$fileKey';\n\$semanticWebhookSecret = '$secret';\n"
            . "\$semanticSigningSecrets = array('s1', ' ', 's2');\necho 'printed text is swallowed';\n");
        try {
            $this->assertSame(
                array('baseUrl' => 'https://dev.semantic.example.invalid', 'tenantId' => 'tenant-from-file', 'apiKey' => $fileKey),
                semantic_credentials($file)
            );
            $this->assertSame($secret, semantic_webhook_secret($file));
            $this->assertSame(array('s1', 's2'), semantic_signing_secrets($file));
            $this->assertArrayNotHasKey('otherProviderKey', semantic_key_file_values($file));

            $envKey = self::uuid();
            putenv('SALDI_SEMANTIC_BASE_URL=https://env.example.invalid');
            putenv('SALDI_SEMANTIC_TENANT_ID=tenant-from-env');
            putenv("SALDI_SEMANTIC_API_KEY=$envKey");
            putenv('SALDI_SEMANTIC_SIGNING_SECRETS=a, b');
            $this->assertSame(array('baseUrl' => 'https://env.example.invalid', 'tenantId' => 'tenant-from-env', 'apiKey' => $envKey), semantic_credentials($file));
            $this->assertSame(array('a', 'b'), semantic_signing_secrets($file));

            // Not all three in the environment: the file, never a mix.
            putenv('SALDI_SEMANTIC_API_KEY');
            $this->assertSame('tenant-from-file', semantic_credentials($file)['tenantId']);

            putenv('SALDI_SEMANTIC_WEBHOOK_SECRET=too-short');
            $this->assertNull(semantic_webhook_secret($file));
            $this->assertNull(semantic_credentials($file . '.missing'));
        } finally {
            unlink($file);
            foreach ($saved as $name => $value) {
                putenv($value === false ? $name : "$name=$value");
            }
        }
    }

    public function testKeyFileIsOneLevelAboveTheInstallation(): void
    {
        $this->assertSame(dirname(__DIR__, 4) . '/.ht_keys.txt', semantic_key_file_path());
    }
}
