<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/einvoice/semanticSend.test.php --- ver 5.1.0 --- 2026-11-09 ---
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
// 20261109 CL/MMR SD-815 Created (draft). The decisions of semantic_send_document() as pure functions: the duplicate guard, the copy guard, onboarding, the order check and the network,
//                 plus the send log's SQL and XML archive, without a database or the network.

declare(strict_types=1);

namespace SaldiTests\Einvoice;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/einvoice/semanticSend.php';

final class semanticSend extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>|null, string, bool, bool, string}>
     */
    public static function duplicates(): array
    {
        return array(
            'first send' => array(null, '', false, true, 'first'),
            'first after the old provider failed' => array(null, 'SendFailed', false, true, 'first'),
            'sent by the old provider' => array(null, 'Sent', false, false, 'duplicate'),
            'old provider still pending' => array(null, 'Pending', false, false, 'duplicate'),
            'old provider, confirmed resend' => array(null, 'Sent', true, true, 'resend'),
            'log says pending' => array(array('id' => 4, 'status' => 'Pending', 'created' => '2026-11-09 10:15:00'), '', false, false, 'duplicate'),
            'log says sent' => array(array('id' => 4, 'status' => 'Sent'), 'Sent', false, false, 'duplicate'),
            'log says rejected by receiver' => array(array('id' => 4, 'status' => 'Rejected'), 'Rejected', false, false, 'duplicate'),
            'log outcome unknown' => array(array('id' => 4, 'status' => 'Unknown'), 'SendFailed', false, false, 'duplicate'),
            'log crashed mid-call' => array(array('id' => 4, 'status' => 'Submitting'), '', false, false, 'duplicate'),
            'log validation failed' => array(array('id' => 4, 'status' => 'ValidationFailed'), 'SendFailed', false, true, 'after_failure'),
            'log send failed' => array(array('id' => 4, 'status' => 'SendFailed'), 'SendFailed', false, true, 'after_failure'),
            'log error at Semantic' => array(array('id' => 4, 'status' => 'Error'), 'Error', false, true, 'after_failure'),
            'log sent, confirmed resend' => array(array('id' => 4, 'status' => 'Sent'), 'Sent', true, true, 'resend'),
        );
    }

    /**
     * @param array<string, mixed>|null $log
     */
    #[DataProvider('duplicates')]
    public function testDuplicateGuard(?array $log, string $digital, bool $resend, bool $allowed, string $reason): void
    {
        $result = semantic_duplicate_guard($log, $digital, $resend);
        $this->assertSame($allowed, $result['allowed']);
        $this->assertSame($reason, $result['reason']);
        if (!$allowed) {
            $this->assertStringEndsWith('Vil du sende det igen?', $result['message']);
        }
    }

    public function testDuplicateMessageSaysWhenAndWhy(): void
    {
        $sent = semantic_duplicate_guard(array('status' => 'Pending', 'created' => '2026-11-09 10:15:42.123'), '', false);
        $this->assertStringContainsString('status: Pending', $sent['message']);
        $this->assertStringContainsString('2026-11-09 10:15', $sent['message']);
        $unknown = semantic_duplicate_guard(array('status' => 'Unknown'), '', false);
        $this->assertStringContainsString('kan være modtaget', $unknown['message']);
    }

    /**
     * @return array<string, array{array<string, string>, string, bool, string}>
     */
    public static function copies(): array
    {
        return array(
            'never registered' => array(array('companyId' => '', 'dbName' => ''), 'test_7', true, 'not_registered'),
            'registered here' => array(array('companyId' => 'c', 'dbName' => 'test_7'), 'test_7', true, 'same_db'),
            'restored as another name' => array(array('companyId' => 'c', 'dbName' => 'test_7'), 'test_7_copy', false, 'copy'),
            'half-finished registration copied' => array(array('companyId' => '', 'dbName' => 'test_7'), 'test_8', false, 'copy'),
            'ids without a database name' => array(array('companyId' => 'c', 'dbName' => ''), 'test_7', false, 'db_unknown'),
            'no current database' => array(array('companyId' => 'c', 'dbName' => 'test_7'), '', false, 'copy'),
            'case matters' => array(array('companyId' => 'c', 'dbName' => 'test_7'), 'TEST_7', false, 'copy'),
        );
    }

    /**
     * @param array<string, string> $settings
     */
    #[DataProvider('copies')]
    public function testCopyGuard(array $settings, string $db, bool $allowed, string $reason): void
    {
        $result = semantic_copy_guard($settings, $db);
        $this->assertSame($allowed, $result['allowed']);
        $this->assertSame($reason, $result['reason']);
        $this->assertSame($allowed, $result['message'] === '');
    }

    public function testOnboardingDecision(): void
    {
        $empty = array_fill_keys(semantic_settings_names(), '');
        $this->assertSame('continue', semantic_onboarding_decision($empty, '', 'test_7')['action']);
        $this->assertSame('new', semantic_onboarding_decision($empty, '', 'test_7')['reason']);

        $elsewhere = semantic_onboarding_decision($empty, '0e2c1d7a-0000-4000-8000-000000000001', 'test_7');
        $this->assertSame('refuse', $elsewhere['action']);
        $this->assertSame('registered_elsewhere', $elsewhere['reason']);
        $this->assertStringNotContainsStringIgnoringCase('easyubl', $elsewhere['message']);

        $started = array('companyIdCandidate' => 'x', 'dbName' => 'test_7') + $empty;
        $this->assertSame('continue', semantic_onboarding_decision($started, 'old-id', 'test_7')['action'], 'our own half-finished onboarding goes on');

        $partial = array('companyId' => 'c', 'endpointId' => 'e', 'dbName' => 'test_7') + $empty;
        $this->assertSame('partial', semantic_onboarding_decision($partial, '', 'test_7')['reason']);
        $ready = array('defaultEndpointSet' => '1', 'peppolRegistered' => '1') + $partial;
        $this->assertSame('ready', semantic_onboarding_decision($ready, '', 'test_7')['action']);
        $this->assertSame('copy', semantic_onboarding_decision($ready, '', 'test_7_restored')['reason']);
    }

    /**
     * @return array<string, array{array<string, mixed>|null, string, bool, string}>
     */
    public static function orders(): array
    {
        $invoice = array('art' => 'DO', 'status' => '3', 'fakturanr' => '1001');
        $credit = array('art' => 'DK', 'status' => 4, 'fakturanr' => '1001');
        return array(
            'invoice' => array($invoice, 'invoice', true, 'ok'),
            'credit note' => array($credit, 'creditnote', true, 'ok'),
            'invoice sent as credit note' => array($invoice, 'creditnote', false, 'art'),
            'credit note sent as invoice' => array($credit, 'invoice', false, 'art'),
            'not invoiced' => array(array('status' => 2) + $invoice, 'invoice', false, 'not_invoiced'),
            'no invoice number' => array(array('fakturanr' => ' ') + $invoice, 'invoice', false, 'not_invoiced'),
            'missing order' => array(null, 'invoice', false, 'not_found'),
            'order type' => array($invoice, 'order', false, 'type'),
        );
    }

    /**
     * @param array<string, mixed>|null $order
     */
    #[DataProvider('orders')]
    public function testOrderSendable(?array $order, string $type, bool $allowed, string $reason): void
    {
        $result = semantic_order_sendable($order, $type);
        $this->assertSame($allowed, $result['allowed']);
        $this->assertSame($reason, $result['reason']);
    }

    public function testNetworkDecision(): void
    {
        $this->assertSame(3, semantic_network_decide(true, null)['networkType']);
        $this->assertSame(3, semantic_network_decide(true, true)['networkType'], 'Peppol first when the receiver is in both');
        $this->assertSame(4, semantic_network_decide(false, true)['networkType']);
        $this->assertSame('not_found', semantic_network_decide(false, false)['reason']);
        $this->assertNull(semantic_network_decide(false, false)['networkType']);
        $this->assertSame('lookup_failed', semantic_network_decide(null, true)['reason'], 'a failed lookup is not a no');
        $this->assertSame('lookup_failed', semantic_network_decide(false, null)['reason']);
    }

    public function testNetworkLookupAsksNemhandelOnlyWhenPeppolSaysNo(): void
    {
        $calls = array();
        $answers = array('false', 'true');
        $client = new \SemanticClient('https://semantic.example.invalid', 't', 'k', function ($method, $url) use (&$calls, &$answers) {
            $calls[] = $url;
            return array('status' => 200, 'body' => (string)array_shift($answers), 'error' => null);
        });
        $result = semantic_choose_network($client, array('scheme' => '0088', 'id' => '5790000000005'), 'creditnote');
        $this->assertSame(4, $result['networkType']);
        $this->assertSame(array(
            'https://semantic.example.invalid/api/Endpoint/isEndpointInPeppol/5790000000005/0088/120',
            'https://semantic.example.invalid/api/Endpoint/isEndpointInNemHandel/0088/5790000000005/120',
        ), $calls);

        $calls = array();
        $answers = array('true');
        $this->assertSame(3, semantic_choose_network($client, array('scheme' => '0184', 'id' => '22222222'), 'invoice')['networkType']);
        $this->assertCount(1, $calls);

        $calls = array();
        $this->assertSame('no_endpoint', semantic_choose_network($client, array('scheme' => '', 'id' => ''), 'invoice')['reason']);
        $this->assertSame(array(), $calls);
    }

    public function testPrecheckAnswersBecomeMessages(): void
    {
        $this->assertSame(array(), semantic_precheck_messages(array()));
        $this->assertSame(array('Mangler CVR', 'Mangler adresse'), semantic_precheck_messages(array('Mangler CVR', ' ', 'Mangler adresse ')));
        $this->assertSame(array('A', 'B'), semantic_precheck_messages(array(array('message' => 'A'), array('text' => 'B'), array('code' => 1))));
        $this->assertSame(array('C'), semantic_precheck_messages(array('ok' => false, 'messages' => array('C'))));
        $this->assertSame(array(), semantic_precheck_messages(null));
    }

    public function testValidationReportReading(): void
    {
        $this->assertTrue(semantic_validation_report_verdict('No errors found in the document'));
        $this->assertTrue(semantic_validation_report_verdict(array('isValid' => true, 'errors' => array('x'))));
        $this->assertFalse(semantic_validation_report_verdict("[BR-CO-15] fatal: Invoice total amount with VAT ..."));
        $this->assertFalse(semantic_validation_report_verdict(array('errors' => array('BR-CO-15'))));
        $this->assertTrue(semantic_validation_report_verdict(array('errors' => array())));
        $this->assertNull(semantic_validation_report_verdict(''));
        $this->assertNull(semantic_validation_report_verdict('Report 42'));
        $this->assertNull(semantic_validation_report_verdict(5));
    }

    public function testResendTokenIsBoundToSessionOrderAndShownSend(): void
    {
        $session = bin2hex(random_bytes(16));
        $token = semantic_resend_token($session, 12, 4);
        $this->assertTrue(semantic_resend_token_valid($session, 12, 4, $token));
        $this->assertFalse(semantic_resend_token_valid($session, 13, 4, $token), 'another order');
        $this->assertFalse(semantic_resend_token_valid($session, 12, 5, $token), 'a newer send happened since');
        $this->assertFalse(semantic_resend_token_valid(bin2hex(random_bytes(16)), 12, 4, $token), 'another session');
        $this->assertFalse(semantic_resend_token_valid('', 12, 4, semantic_resend_token('', 12, 4)), 'no session');
        $this->assertFalse(semantic_resend_token_valid($session, 12, 4, null));
    }

    public function testProviderSwitchValues(): void
    {
        $this->assertTrue(semantic_provider_is_semantic('semantic'));
        $this->assertTrue(semantic_provider_is_semantic(' Semantic '));
        $this->assertFalse(semantic_provider_is_semantic('easyubl'));
        $this->assertFalse(semantic_provider_is_semantic(''));
        $this->assertFalse(semantic_provider_is_semantic(null));
        $this->assertSame('DO', semantic_document_art('invoice'));
        $this->assertSame('DK', semantic_document_art('creditnote'));
        $this->assertNull(semantic_document_art('order'));
    }

    public function testSendLogInsertEscapesCastsAndCuts(): void
    {
        $escape = function (string $value): string {
            return str_replace("'", "''", $value);
        };
        $sql = einvoice_sendlog_insert_sql(array(
            'ordre_id' => '17 OR 1=1',
            'art' => "DO'",
            'fakturanr' => "1001'; DROP TABLE ordrer; --",
            'provider' => 'semantic',
            'network' => '3',
            'status' => 'Submitting',
            'status_text' => null,
            'created_by' => 'Æble Ø',
            'not_a_column' => 'x',
        ), $escape);
        $this->assertSame(
            "INSERT INTO einvoice_sendlog (ordre_id, art, fakturanr, provider, network, status, status_text, created_by, created, updated) "
            . "VALUES (17, 'DO', '1001''; DROP TABLE or', 'semantic', 3, 'Submitting', NULL, 'Æble Ø', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
            $sql
        );
        $this->assertStringNotContainsString('not_a_column', $sql);
    }

    public function testSendLogUpdateTouchesOnlyKnownColumns(): void
    {
        $escape = function (string $value): string {
            return addslashes($value);
        };
        $this->assertSame(
            "UPDATE einvoice_sendlog SET status = 'Pending', semantic_doc_id = '123', updated = CURRENT_TIMESTAMP WHERE id = 5",
            einvoice_sendlog_update_sql('5', array('status' => 'Pending', 'semantic_doc_id' => '123', 'id' => 99, 'created' => 'x'), $escape)
        );
        $this->assertSame('', einvoice_sendlog_update_sql(5, array('bogus' => 1), $escape));
        $this->assertSame('', einvoice_sendlog_update_sql(0, array('status' => 'Sent'), $escape));
    }

    public function testFindOpenAsksOnlyForSemanticRowsWithAnId(): void
    {
        $sql = einvoice_sendlog_find_open_sql(10000, '2026-10-10 00:00:00', function (string $value): string {
            return $value;
        });
        $this->assertStringContainsString("provider = 'semantic' AND semantic_doc_id IS NOT NULL AND semantic_doc_id <> ''", $sql);
        $this->assertStringContainsString("'Pending'", $sql);
        $this->assertStringNotContainsString("'Sent'", $sql);
        $this->assertStringContainsString("created >= '2026-10-10 00:00:00'", $sql);
        $this->assertStringEndsWith('ORDER BY id ASC LIMIT 500', $sql);
        $this->assertStringNotContainsString(' xml,', einvoice_sendlog_select_columns() . ',');
    }

    public function testArchivedXmlSurvivesExactly(): void
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Invoice><cbc:Note>Looks like new; 'quoted' æøå</cbc:Note></Invoice>";
        $packed = einvoice_sendlog_pack_xml($xml);
        $this->assertMatchesRegularExpression('/^(gz|b64):[A-Za-z0-9+\/=]+$/', $packed, 'no spaces, quotes or " like " for db_modify() to touch');
        $this->assertSame($xml, einvoice_sendlog_unpack_xml($packed));
        $this->assertSame($xml, einvoice_sendlog_unpack_xml('b64:' . base64_encode($xml)));
        $this->assertNull(einvoice_sendlog_unpack_xml('<Invoice/>'));
        $this->assertNull(einvoice_sendlog_unpack_xml('gz:!!!'));
        $this->assertSame(64, strlen(hash('sha256', $xml)));
    }

    public function testFailedStatusesAreNotOpenAndViceVersa(): void
    {
        $this->assertSame(array(), array_intersect(einvoice_sendlog_failed_statuses(), einvoice_sendlog_open_statuses()));
        $this->assertNotContains('Unknown', einvoice_sendlog_failed_statuses(), 'an unknown outcome needs a confirmed resend');
    }

    public function testCompanyPayloadForSemantic(): void
    {
        $own = array('name' => 'Eksempel Sælger ApS', 'addr1' => 'Eksempelvej 1', 'addr2' => '', 'postnr' => '1234', 'bynavn' => 'Eksempelby',
            'cvr' => '11111111', 'gln' => '', 'email' => '', 'tlf' => '12345678');
        $payload = semantic_company_payload($own, 'c0ffee00-0000-4000-8000-000000000000', 'https://server.example.invalid/x');
        $this->assertSame('11111111', $payload['cvr'], 'CVR without DK');
        $this->assertSame('DK', $payload['countryCode']);
        $this->assertSame('https://server.example.invalid/x', $payload['webHookUrl']);
        $this->assertNull($payload['contacts'][0]['email'], 'an empty value is sent as null');
        $this->assertArrayNotHasKey('endpoints', $payload);
        $this->assertSame('c0ffee00-0000-4000-8000-000000000000', semantic_id_from_response('"C0FFEE00-0000-4000-8000-000000000000"', 'companyId'));
        $this->assertSame('c0ffee00-0000-4000-8000-000000000000', semantic_id_from_response(array('companyId' => 'c0ffee00-0000-4000-8000-000000000000'), 'companyId'));
        $this->assertNull(semantic_id_from_response('00000000-0000-0000-0000-000000000000', 'companyId'));
        $this->assertNull(semantic_id_from_response(null, 'companyId'));
        $this->assertTrue(\SemanticClient::isUuid(semantic_uuid4()));
    }
}
