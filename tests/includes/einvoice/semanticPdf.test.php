<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/einvoice/semanticPdf.test.php --- ver 5.1.1 --- 2026-11-12 ---
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
// 20261112 CL/MMR SD-815 Created. The guards and headers of the e-invoice PDF endpoint (debitor/einvoicePdf.php), with a fake transport instead of the network.

declare(strict_types=1);

namespace SaldiTests\Einvoice;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/einvoice/semanticPdf.php';

final class semanticPdf extends TestCase
{
    private const BASE = 'https://semantic.example.invalid';
    private const PDF = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<<>>\nendobj\n";

    /** @var list<array{method: string, url: string}> */
    private array $calls = array();
    /** @var list<array{status: int, body: string, error: ?string}> */
    private array $answers = array();
    /** @var list<array{int, string}> */
    private array $stored = array();
    private string $ublId = '';

    protected function setUp(): void
    {
        $this->calls = array();
        $this->answers = array();
        $this->stored = array();
        $this->ublId = self::uuid();
    }

    private static function uuid(): string
    {
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
    }

    private function answer(int $status, string $body): void
    {
        $this->answers[] = array('status' => $status, 'body' => $body, 'error' => null);
    }

    private function client(): \SemanticClient
    {
        $transport = function (string $method, string $url, array $headers, ?string $body): array {
            $this->calls[] = array('method' => $method, 'url' => $url);
            return array_shift($this->answers) ?? array('status' => 0, 'body' => '', 'error' => 'no answer queued');
        };
        return new \SemanticClient(self::BASE, self::uuid(), self::uuid(), $transport);
    }

    /**
     * @param array<string, mixed> $log
     * @return array<string, mixed>
     */
    private function deps(?array $log, array $overrides = array()): array
    {
        return $overrides + array(
            'enabled' => true,
            'db' => 'test_15',
            'sprogId' => 1,
            'loadLog' => function (int $logId) use ($log): ?array {
                return ($log !== null && $logId === (int)$log['id']) ? $log : null;
            },
            'orderExists' => function (int $orderId): bool {
                return $orderId === 7;
            },
            'storeUblId' => function (int $logId, string $ublId): void {
                $this->stored[] = array($logId, $ublId);
            },
            'client' => $this->client(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function log(array $extra = array()): array
    {
        return $extra + array('id' => 41, 'ordre_id' => 7, 'art' => 'DO', 'fakturanr' => '1001', 'db_name' => 'test_15',
            'semantic_doc_id' => '9001', 'ubl_doc_id' => $this->ublId, 'status' => 'Sent');
    }

    /**
     * @param array{status: int, headers: list<string>, body: string} $response
     */
    private function assertMessage(int $status, string $text, array $response): void
    {
        $this->assertSame($status, $response['status']);
        $this->assertContains('Content-Type: text/html; charset=UTF-8', $response['headers']);
        $this->assertStringContainsString($text, $response['body']);
        $this->assertStringNotContainsString('Semantic', $response['body']);
        $this->assertStringNotContainsString('%PDF', $response['body']);
    }

    public function testRequestTakesOnlyCastIds(): void
    {
        $this->assertSame(array('orderId' => 7, 'logId' => 41), semantic_pdf_request(array('id' => '7', 'log' => '41', 'file' => '../../etc/passwd')));
        $this->assertSame(array('orderId' => 0, 'logId' => 0), semantic_pdf_request(array('id' => "7' OR 1=1", 'log' => array('41'))));
        $this->assertSame(array('orderId' => 0, 'logId' => 0), semantic_pdf_request(array('id' => '-7', 'log' => '4.1')));
        $this->assertSame(array('orderId' => 0, 'logId' => 0), semantic_pdf_request(array()));
    }

    public function testSuccessSendsThePdfWithSafeHeaders(): void
    {
        $this->answer(200, json_encode(base64_encode(self::PDF)));
        $response = semantic_pdf_response(array('orderId' => 7, 'logId' => 41), $this->deps($this->log(array('fakturanr' => '10/01 "x"'))));
        $this->assertSame(200, $response['status']);
        $this->assertSame(self::PDF, $response['body']);
        $this->assertSame(array(
            'Content-Type: application/pdf',
            'Content-Disposition: inline; filename="faktura-1001x.pdf"',
            'Content-Length: ' . strlen(self::PDF),
            'Cache-Control: private, no-store',
            'X-Content-Type-Options: nosniff',
        ), $response['headers']);
        $this->assertCount(1, $this->calls);
        $this->assertSame(self::BASE . '/api/UblDocument/pdf/' . $this->ublId, $this->calls[0]['url']);
        $this->assertSame(array(), $this->stored);
    }

    public function testCreditNoteName(): void
    {
        $this->assertSame('kreditnota-55.pdf', semantic_pdf_filename('DK', '55'));
        $this->assertSame('faktura.pdf', semantic_pdf_filename('DO', '../..'));
    }

    public function testWrongOrderOrRegnskabGivesNotFoundWithoutCallingTheProvider(): void
    {
        $cases = array(
            'row of another order' => array(array('orderId' => 8, 'logId' => 41), $this->log()),
            'row does not exist' => array(array('orderId' => 7, 'logId' => 42), $this->log()),
            'row made in another database (a copy)' => array(array('orderId' => 7, 'logId' => 41), $this->log(array('db_name' => 'test_16'))),
            'row without database' => array(array('orderId' => 7, 'logId' => 41), $this->log(array('db_name' => ''))),
            'order not in this regnskab' => array(array('orderId' => 9, 'logId' => 41), $this->log(array('ordre_id' => 9))),
        );
        foreach ($cases as $name => $case) {
            $response = semantic_pdf_response($case[0], $this->deps($case[1]));
            $this->assertSame(404, $response['status'], $name);
            $this->assertStringContainsString('Dokumentet findes ikke i dette regnskab.', $response['body'], $name);
        }
        $this->assertMessage(400, 'Dokumentet findes ikke', semantic_pdf_response(array('orderId' => 0, 'logId' => 41), $this->deps($this->log())));
        $this->assertMessage(400, 'Dokumentet findes ikke', semantic_pdf_response(array('orderId' => 7, 'logId' => 0), $this->deps($this->log())));
        $this->assertSame(array(), $this->calls);
    }

    public function testRegnskabWithoutTheSwitchGetsNoPdf(): void
    {
        $response = semantic_pdf_response(array('orderId' => 7, 'logId' => 41), $this->deps($this->log(), array('enabled' => false)));
        $this->assertMessage(404, 'kan ikke vises i dette regnskab', $response);
        $this->assertSame(array(), $this->calls);
    }

    public function testMissingDocumentIdsSayNotReady(): void
    {
        $response = semantic_pdf_response(array('orderId' => 7, 'logId' => 41), $this->deps($this->log(array('ubl_doc_id' => '', 'semantic_doc_id' => ''))));
        $this->assertMessage(404, 'Der er endnu ingen PDF', $response);
        $this->assertSame(array(), $this->calls);
    }

    public function testMissingUblIdIsLookedUpAndStored(): void
    {
        $this->answer(200, json_encode(array('documentStatus' => 5140, 'ublDocumentId' => strtoupper($this->ublId))));
        $this->answer(200, json_encode(base64_encode(self::PDF)));
        $response = semantic_pdf_response(array('orderId' => 7, 'logId' => 41), $this->deps($this->log(array('ubl_doc_id' => ''))));
        $this->assertSame(200, $response['status']);
        $this->assertSame(self::BASE . '/api/OutgoingDocument/9001', $this->calls[0]['url']);
        $this->assertSame(self::BASE . '/api/UblDocument/pdf/' . $this->ublId, $this->calls[1]['url']);
        $this->assertSame(array(array(41, $this->ublId)), $this->stored);
    }

    public function testNoUblIdYetAtTheProvider(): void
    {
        $this->answer(200, json_encode(array('documentStatus' => 5110, 'ublDocumentId' => '00000000-0000-0000-0000-000000000000')));
        $response = semantic_pdf_response(array('orderId' => 7, 'logId' => 41), $this->deps($this->log(array('ubl_doc_id' => ''))));
        $this->assertMessage(404, 'Der er endnu ingen PDF', $response);
        $this->assertSame(array(), $this->stored);
        $this->assertCount(1, $this->calls);
    }

    public function testProviderErrorsGiveADanishMessage(): void
    {
        $this->answer(500, '{"title":"Semantic internal error"}');
        $this->assertMessage(502, 'kunne ikke hentes lige nu', semantic_pdf_response(array('orderId' => 7, 'logId' => 41), $this->deps($this->log())));
        $this->answer(404, '');
        $this->assertMessage(404, 'Der er endnu ingen PDF', semantic_pdf_response(array('orderId' => 7, 'logId' => 41), $this->deps($this->log())));
        $this->answer(200, json_encode(base64_encode('<html>not a pdf</html>')));
        $this->assertMessage(502, 'kunne ikke hentes lige nu', semantic_pdf_response(array('orderId' => 7, 'logId' => 41), $this->deps($this->log())));
        $this->answers[] = array('status' => 0, 'body' => '', 'error' => 'timeout');
        $this->assertMessage(502, 'kunne ikke hentes lige nu', semantic_pdf_response(array('orderId' => 7, 'logId' => 41), $this->deps($this->log())));
        $this->answer(401, '');
        $this->assertMessage(502, 'kunne ikke hentes lige nu',
            semantic_pdf_response(array('orderId' => 7, 'logId' => 41), $this->deps($this->log(array('ubl_doc_id' => '')))));
    }

    public function testServerWithoutSemanticSaysSo(): void
    {
        $response = semantic_pdf_response(array('orderId' => 7, 'logId' => 41), $this->deps($this->log(), array('client' => null)));
        $this->assertMessage(503, 'E-faktura er ikke sat op på denne server', $response);
    }

    public function testErrorPageEscapes(): void
    {
        $page = semantic_pdf_error_page(404, "<script>alert('x')</script>", 1);
        $this->assertStringNotContainsString('<script>', $page['body']);
        $this->assertContains('Cache-Control: no-store', $page['headers']);
    }
}
