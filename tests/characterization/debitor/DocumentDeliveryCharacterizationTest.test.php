<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/debitor/DocumentDeliveryCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.02
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

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';
require_once __DIR__ . '/../../fixtures/easyubl/DocumentFixture.php';

final class DocumentDeliveryCharacterizationTest extends TestCase
{
    private static $tenant;
    private static $stub;
    private static int $port;

    public static function setUpBeforeClass(): void
    {
        if (($reason = CharacterizationEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant();
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        DocumentFixture::configureMail(self::$tenant);
        CharacterizationEnv::rows(self::$tenant, "DELETE FROM settings WHERE var_grp='easyUBL'");
        CharacterizationEnv::rows(self::$tenant,
            "INSERT INTO settings(var_grp,var_name,var_value) VALUES('easyUBL','companyID','local-company'),('easyUBL','updatedCompany','true'),('easyUBL','base_url','')");
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::$port = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        self::$stub = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . self::$port, __DIR__ . '/../../fixtures/easyubl/easyubl_stub.php'],
            [0 => ['pipe','r'], 1 => ['file','/tmp/saldi-b5-stub.log','a'], 2 => ['file','/tmp/saldi-b5-stub.log','a']], $pipes);
        usleep(200000);
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$stub)) {
            proc_terminate(self::$stub);
            proc_close(self::$stub);
        }
        if (self::$tenant) {
            pg_close(self::$tenant);
            CharacterizationEnv::teardownTenant();
        }
    }

    private function runDocument(string $scenario, int $orderId): array
    {
        $child = CharacterizationEnv::runChild(__DIR__ . '/../support/run_formularprint.php',
            [$scenario, $orderId, CharacterizationEnv::testDb(), self::$port]);
        $lines = array_values(array_filter(explode("\n", trim($child['stdout']))));
        $result = json_decode(end($lines), true);
        self::assertIsArray($result, $child['stdout'] . $child['stderr']);
        self::assertSame(0, $child['exit'], $child['stderr']);
        return $result;
    }

    public function testInvoiceAndQuoteMailUseTheTenantTemplateAndAttachPdf(): void
    {
        foreach (['invoice_email' => 3, 'tilbud_email' => 0] as $scenario => $status) {
            $fixture = DocumentFixture::seed(self::$tenant, $status);
            $result = $this->runDocument($scenario, $fixture['order_id']);
            self::assertSame('OK', $result['result']);
            $messages = DocumentFixture::messages($fixture['recipient']);
            self::assertSame(1, $messages['total']);
            self::assertSame('Document subject', $messages['messages'][0]['Subject']);
            self::assertSame(1, $messages['messages'][0]['Attachments']);
        }
    }

    public function testDunningMailsOnceAndKeepsWebRedirect(): void
    {
        $fixture = DocumentFixture::seed(self::$tenant, 2, 'R1');
        $result = $this->runDocument('rykker_email', $fixture['order_id']);
        self::assertStringContainsString('rapport.php?rapportart=openpost', $result['output']);
        self::assertSame(1, DocumentFixture::messages($fixture['recipient'])['total']);
    }

    public function testInkassoReturnsPdfWithoutMailAndWebPrintKeepsRedirect(): void
    {
        $fixture = DocumentFixture::seed(self::$tenant);
        $result = $this->runDocument('inkasso', $fixture['order_id']);
        self::assertStringEndsWith('.pdf', $result['result']);
        $path = CharacterizationEnv::repoRoot() . '/debitor/' . $result['result'];
        self::assertStringStartsWith('%PDF-', file_get_contents($path));
        self::assertSame(0, DocumentFixture::messages($fixture['recipient'])['total']);
        $result = $this->runDocument('web_print', $fixture['order_id']);
        self::assertSame('OK', $result['result']);
        self::assertStringContainsString('../includes/udskriv.php?', $result['output']);
    }

    public function testEasyUblSuccessAndSendFailedAgainstOnlyLocalStub(): void
    {
        $fixture = DocumentFixture::seed(self::$tenant);
        $result = $this->runDocument('ean_success', $fixture['order_id']);
        self::assertSame((string)$fixture['order_id'], (string)$result['result']);
        $result = $this->runDocument('ean_api_error', $fixture['order_id']);
        self::assertStringContainsString('<script>', $result['output']);
        $row = CharacterizationEnv::one(self::$tenant, 'SELECT digital_status FROM ordrer WHERE id=$1', [$fixture['order_id']]);
        self::assertSame('SendFailed', $row['digital_status']);
    }
}
