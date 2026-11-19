<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/einvoice/einvoiceStatusView.test.php --- ver 5.1.1 --- 2026-11-12 ---
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
// 20261112 CL/MMR SD-815 Created. The e-invoice status texts, the order list's one-query status lookup, the send script and the status page after a send, without a database or the network.

declare(strict_types=1);

namespace SaldiTests\Einvoice;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/einvoice/einvoiceStatusView.php';
require_once __DIR__ . '/../../../includes/einvoice/semanticPeppolPage.php';

final class einvoiceStatusView extends TestCase
{
    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private static function row(string $status, string $text = '', array $extra = array()): array
    {
        return $extra + array(
            'id' => 41, 'ordre_id' => 7, 'art' => 'DO', 'fakturanr' => '1001', 'network' => 3, 'semantic_doc_id' => '9001',
            'ubl_doc_id' => '', 'status' => $status, 'status_text' => $text, 'created' => '2026-11-12 09:30:12', 'updated' => '',
        );
    }

    /**
     * @return array<string, array{string, string, string, string}>
     */
    public static function statuses(): array
    {
        return array(
            'submitting' => array('Submitting', '', 'pending', 'Afsendes'),
            'pending' => array('Pending', '', 'pending', 'Afsendes'),
            'validating' => array('PendingValidating', '', 'pending', 'Afsendes'),
            'sending' => array('Sending', '', 'pending', 'Afsendes'),
            'sent' => array('Sent', '', 'sent', 'Sendt'),
            'error after sent keeps sent' => array('Sent', 'Error: late', 'sent', 'Sendt'),
            'received' => array('Received', '', 'delivered', 'Leveret'),
            'confirmed' => array('Confirmed', '', 'delivered', 'Bekræftet af modtager'),
            'approved' => array('Approved', '', 'delivered', 'Godkendt af modtager'),
            'rejected with reason' => array('Rejected', 'Forkert ordrenummer', 'rejected', 'Afvist af modtager: Forkert ordrenummer'),
            'rejected without reason' => array('Rejected', '', 'rejected', 'Afvist af modtager'),
            'validation failed' => array('ValidationFailed', 'BR-DK-01 mangler', 'error', 'Fejl: BR-DK-01 mangler'),
            'send failed' => array('SendFailed', 'HTTP 401', 'error', 'Fejl: HTTP 401'),
            'error' => array('Error', '', 'error', 'Fejl'),
            'unknown outcome' => array('Unknown', 'timeout', 'unknown', 'Ukendt: intet svar ved afsendelsen'),
            'unnamed number' => array('Status5213', '', 'unknown', 'Status 5213'),
        );
    }

    #[DataProvider('statuses')]
    public function testStatusText(string $status, string $text, string $kind, string $label): void
    {
        $view = einvoice_status_view(self::row($status, $text), 1);
        $this->assertNotNull($view);
        $this->assertSame($kind, $view['kind']);
        $this->assertSame($label, $view['label']);
        $this->assertSame(41, $view['logId']);
        $this->assertSame(7, $view['orderId']);
    }

    public function testNoRowNoStatus(): void
    {
        $this->assertNull(einvoice_status_view(null, 1));
        $this->assertNull(einvoice_status_view(array('id' => 0, 'status' => 'Sent'), 1));
    }

    public function testErrorTextIsShortAndNeverNamesTheProvider(): void
    {
        $view = einvoice_status_view(self::row('SendFailed', 'Semantic afviste adgangen. ' . str_repeat('x', 400)), 1, 60);
        $this->assertNotNull($view);
        $this->assertStringStartsWith('Fejl: E-faktura-tjenesten afviste adgangen.', $view['label']);
        $this->assertLessThanOrEqual(66, mb_strlen($view['label']));
        $this->assertStringEndsWith('...', $view['label']);
        $this->assertStringNotContainsString('Semantic', $view['label'] . $view['detail']);
        $this->assertFalse($view['pdf'], 'a failed send has no document to show');
    }

    public function testPdfOnlyWhenTheProviderHasTheDocument(): void
    {
        $this->assertTrue(einvoice_status_view(self::row('Pending'), 1)['pdf']);
        $this->assertTrue(einvoice_status_view(self::row('Sent', '', array('semantic_doc_id' => '', 'ubl_doc_id' => 'u')), 1)['pdf']);
        $this->assertFalse(einvoice_status_view(self::row('Submitting', '', array('semantic_doc_id' => '')), 1)['pdf']);
    }

    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function receiverAnswers(): array
    {
        return array(
            'RE with reason' => array(array('receiver_status' => 'RE', 'receiver_status_text' => 'Ukendt køber'), 'rejected', 'Afvist af modtager: Ukendt køber'),
            'rejected word' => array(array('receiver_status' => 'rejected'), 'rejected', 'Afvist af modtager'),
            'AP' => array(array('receiver_status' => 'AP'), 'delivered', 'Godkendt af modtager'),
            'PD paid' => array(array('receiver_status' => 'PD'), 'delivered', 'Godkendt af modtager'),
            'IP in process' => array(array('receiver_status' => 'IP'), 'sent', 'Under behandling hos modtager'),
            'other column names are ignored' => array(array('response_status' => 'RE', 'response_reason' => 'Dublet'), 'sent', 'Sendt'),
            'empty answer' => array(array('receiver_status' => '', 'receiver_status_text' => ''), 'sent', 'Sendt'),
            'unknown code' => array(array('receiver_status' => 'ZZ'), 'sent', 'Sendt'),
        );
    }

    /**
     * @param array<string, string> $receiver
     */
    #[DataProvider('receiverAnswers')]
    public function testTheReceiversAnswerWins(array $receiver, string $kind, string $label): void
    {
        $view = einvoice_status_view(self::row('Sent', '', $receiver), 1);
        $this->assertSame($kind, $view['kind']);
        $this->assertSame($label, $view['label']);
    }

    public function testReceiverColumnsAreReadOnlyWhenTheyExist(): void
    {
        $base = einvoice_status_base_columns();
        $this->assertSame($base, einvoice_status_columns_from_schema(array_merge($base, array('xml', 'xml_sha256'))), 'never the XML');
        $this->assertSame(array_merge($base, array('receiver_status', 'receiver_status_text')),
            einvoice_status_columns_from_schema(array_merge(array('RECEIVER_STATUS_TEXT', 'receiver_status'), $base)));
        $this->assertSame(array(), einvoice_status_columns_from_schema(array()), 'no table, no query');
        $this->assertSame(array(), einvoice_status_columns_from_schema(array('id', 'ordre_id')), 'an incomplete table is not asked');
    }

    public function testOrderListAsksOnceForAllVisibleOrders(): void
    {
        $columns = einvoice_status_base_columns();
        $queries = array();
        $rows = array(
            self::row('Sent', '', array('id' => 10, 'ordre_id' => 5)),
            self::row('SendFailed', 'x', array('id' => 12, 'ordre_id' => 5)),
            self::row('Pending', '', array('id' => 11, 'ordre_id' => 5)),
            self::row('Sent', '', array('id' => 13, 'ordre_id' => 6, 'art' => 'DK')),
            self::row('Rejected', '', array('id' => 9, 'ordre_id' => 6, 'art' => 'DO')),
        );
        $fetch = function (string $sql) use (&$queries, $rows): array {
            $queries[] = $sql;
            return $rows;
        };
        $ids = array_merge(range(1, 50), array('5', '5', '-3', "6' OR 1=1", 0));
        $latest = einvoice_status_for_orders($ids, $fetch, $columns);
        $this->assertCount(1, $queries, 'one query for the whole page, not one per row');
        $this->assertStringContainsString('WHERE ordre_id IN (1, 2, 3,', $queries[0]);
        $this->assertStringNotContainsString('OR 1=1', $queries[0]);
        $this->assertStringNotContainsString('-3', $queries[0]);
        $this->assertSame(50, substr_count($queries[0], ',') - count($columns) + 2, 'each id once');
        $this->assertStringNotContainsString('xml', $queries[0]);
        $this->assertStringEndsWith('ORDER BY id DESC', $queries[0]);
        $this->assertSame(12, (int)$latest[5]['DO']['id'], 'the newest row per order and kind');
        $this->assertSame(13, (int)$latest[6]['DK']['id']);
        $this->assertSame(9, (int)$latest[6]['DO']['id']);

        $queries = array();
        $this->assertSame(array(), einvoice_status_for_orders(array(), $fetch, $columns));
        $this->assertSame(array(), einvoice_status_for_orders(array(0, -1, 'x'), $fetch, $columns));
        $this->assertSame(array(), einvoice_status_for_orders(array(1), $fetch, array()), 'no table, no query');
        $this->assertCount(0, $queries);
    }

    public function testLatestSqlOnlyTakesKnownColumnNames(): void
    {
        $this->assertSame('SELECT id, status FROM einvoice_sendlog WHERE ordre_id IN (3) ORDER BY id DESC',
            einvoice_status_latest_sql(array(3), array('id', 'status', 'x; DROP TABLE ordrer', 'Bad')));
        $this->assertSame('', einvoice_status_latest_sql(array(), array('id')));
    }

    public function testPickLatestIgnoresBrokenRows(): void
    {
        $latest = einvoice_status_pick_latest(array(
            array('id' => '3', 'ordre_id' => '8', 'art' => 'do', 0 => 'numeric keys dropped', 'status_text' => null),
            array('id' => '0', 'ordre_id' => '8', 'art' => 'DO'),
            array('id' => '4', 'ordre_id' => '0', 'art' => 'DO'),
            'not a row',
        ));
        $this->assertSame(array(8), array_keys($latest));
        $this->assertSame('', $latest[8]['DO']['status_text']);
        $this->assertArrayNotHasKey(0, $latest[8]['DO']);
    }

    public function testProviderNameIsReplaced(): void
    {
        $this->assertSame("E-faktura-tjenesten afviste adgangen.\nIngen forbindelse til e-faktura-tjenesten: timeout",
            einvoice_ui_user_text("Semantic afviste adgangen.\nIngen forbindelse til Semantic: timeout"));
        $this->assertSame('Semantics', einvoice_ui_user_text('Semantics'), 'only the whole word');
    }

    public function testSendScriptIsNeutralAndSafe(): void
    {
        $direct = einvoice_send_open_script(12, 'invoice', false, 1);
        $this->assertStringContainsString('window.open("peppol.php?id=12\u0026type=invoice", \'_blank\');', $direct);
        $this->assertStringNotContainsString('confirm(', $direct);
        $first = einvoice_send_open_script(13, 'creditnote', true, 1);
        $this->assertStringContainsString('confirm("F\u00f8rste gang du sender en e-faktura', $first, 'pure ASCII, so any page charset works');
        $this->assertStringContainsString('type=creditnote', $first);
        $this->assertDoesNotMatchRegularExpression('/nemhandel|easyubl|semantic/i', $first . $direct);
        $this->assertStringContainsString('type=invoice', einvoice_send_open_script(14, "creditnote'); alert(1);//", false, 1));
        $this->assertSame('"\u003C\/script\u003E\u0027"', einvoice_js_string("</script>'"));
        $this->assertSame('"\u00e6"', einvoice_js_string("\xE6"), 'Latin-1 text from a non-UTF-8 database');
    }

    public function testStatusHtmlEscapesAndLinksThePdf(): void
    {
        $view = einvoice_status_view(self::row('Rejected', "<b>'x'</b>"), 1);
        $html = einvoice_status_html($view, 1, 'UTF-8');
        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString("href='peppol.php?semanticLog=41'", $html);
        $this->assertStringContainsString("href='einvoicePdf.php?id=7&amp;log=41'", $html);
        $badge = einvoice_status_badge_html($view, 1);
        $this->assertStringContainsString("title='E-faktura: Afvist af modtager: &#039;x&#039;'", $badge);
        $this->assertStringContainsString("fill='#b00000'", $badge);
        $noPdf = einvoice_status_html(einvoice_status_view(self::row('Error'), 1), 1);
        $this->assertStringNotContainsString('einvoicePdf.php', $noPdf);
    }

    /**
     * @return array{action: string, orderId: int, type: string, logId: int, token: string}
     */
    private static function request(): array
    {
        return array('action' => 'send', 'orderId' => 7, 'type' => 'invoice', 'logId' => 0, 'token' => '');
    }

    /**
     * @return array{db: string, sprog_id: int, user: string, sessionId: string}
     */
    private static function env(): array
    {
        return array('db' => 'test_1', 'sprog_id' => 1, 'user' => 'u', 'sessionId' => 'session-a');
    }

    /**
     * @param array<string, mixed> $view
     */
    private static function rendered(array $view): string
    {
        $level = ob_get_level();
        ob_start();
        try {
            semanticPeppolRender($view, 1);
        } finally {
            $html = '';
            while (ob_get_level() > $level) {
                $html = (string)ob_get_clean() . $html;
            }
        }
        return $html;
    }

    public function testPageAfterASuccessfulSend(): void
    {
        $result = array('ok' => true, 'status' => 'pending', 'message' => "Dokumentet er afleveret til Semantic og sendes nu til modtageren.\nBemærk: GLN mangler.", 'sendLogId' => 41);
        $view = semanticPeppolSendView($result, self::row('Pending'), self::request(), self::env());
        $this->assertSame('ok', $view['tone']);
        $this->assertSame('E-fakturaen er sendt af sted', $view['title']);
        $this->assertSame(array('label' => 'Afsendes', 'kind' => 'pending'), $view['status']);
        $this->assertContains('Bemærk: GLN mangler.', $view['lines']);
        $this->assertContains('Afsendt 2026-11-12 09:30 via Peppol', $view['lines']);
        $this->assertSame('einvoicePdf.php?id=7&log=41', $view['pdfUrl']);
        $this->assertNull($view['resend']);
        $html = self::rendered($view);
        $this->assertStringContainsString('Vis e-fakturaen som PDF', $html);
        $this->assertStringNotContainsString('Semantic', $html);
    }

    public function testPageListsThePrecheckMessages(): void
    {
        $result = array('ok' => false, 'status' => 'precheck', 'message' => "Kunden mangler CVR-nummer.\nLinje 2 har ingen enhed.", 'sendLogId' => null);
        $view = semanticPeppolSendView($result, null, self::request(), self::env());
        $this->assertSame('E-fakturaen er ikke sendt', $view['title']);
        $this->assertSame('error', $view['tone']);
        $this->assertSame(array('Kunden mangler CVR-nummer.', 'Linje 2 har ingen enhed.'), $view['items']);
        $this->assertNull($view['pdfUrl']);
        $html = self::rendered($view);
        $this->assertStringContainsString('<li>Kunden mangler CVR-nummer.</li><li>Linje 2 har ingen enhed.</li>', $html);
    }

    public function testPageForAnAlreadySentDocumentOffersAResend(): void
    {
        $result = array('ok' => false, 'status' => 'duplicate', 'message' => 'Dokumentet er allerede sendt digitalt (status: Sent). Vil du sende det igen?', 'sendLogId' => 41);
        $view = semanticPeppolSendView($result, self::row('Sent'), self::request(), self::env());
        $this->assertSame('Dokumentet er allerede sendt', $view['title']);
        $this->assertSame('warning', $view['tone']);
        $this->assertSame('Sendt', $view['status']['label']);
        $this->assertSame('Vil du sende det igen?', end($view['lines']));
        $this->assertSame(41, $view['resend']['logId']);
        $this->assertTrue(semantic_resend_token_valid('session-a', 7, 41, $view['resend']['token']));
        $this->assertSame('einvoicePdf.php?id=7&log=41', $view['pdfUrl']);
        $html = self::rendered($view);
        $this->assertStringContainsString('Send igen', $html);
        $this->assertStringNotContainsString('status: Sent', $html, 'no raw status words');

        $unknown = semanticPeppolSendView($result, self::row('Unknown', '', array('semantic_doc_id' => '')), self::request(), self::env());
        $this->assertStringContainsString('intet svar', $unknown['lines'][0]);
        $this->assertNull($unknown['pdfUrl']);
    }

    public function testProviderAnswersGoToTheTechnicalDetails(): void
    {
        $result = array('ok' => false, 'status' => 'validation', 'message' => "Semantic afviste e-fakturaen ved valideringen, og den er ikke sendt.\nHTTP 400: BR-CO-15 fejl", 'sendLogId' => 41);
        $view = semanticPeppolSendView($result, self::row('ValidationFailed', 'HTTP 400: BR-CO-15 fejl'), self::request(), self::env());
        $this->assertSame(array('E-faktura-tjenesten afviste e-fakturaen ved valideringen, og den er ikke sendt.', 'Afsendt 2026-11-12 09:30 via Peppol'), $view['lines']);
        $this->assertSame(array('HTTP 400: BR-CO-15 fejl'), $view['details']);
        $html = self::rendered($view);
        $this->assertStringContainsString('<details><summary>Tekniske detaljer til support</summary>', $html);
        $this->assertStringNotContainsString('Semantic', $html);

        $unknown = semanticPeppolSendView(array('ok' => false, 'status' => 'unknown', 'message' => "Der kom intet brugbart svar fra Semantic.\nIngen forbindelse til Semantic: timeout", 'sendLogId' => null),
            null, self::request(), self::env());
        $this->assertSame('Afsendelsen fik intet svar', $unknown['title']);
        $this->assertSame('warning', $unknown['tone']);
        $this->assertStringNotContainsString('Semantic', self::rendered($unknown));

        $onboarding = semanticPeppolSendView(array('ok' => false, 'status' => 'onboarding', 'message' => "Semantic kunne ikke svare.\nHTTP 500: x", 'sendLogId' => null),
            null, self::request(), self::env());
        $this->assertSame(array('E-faktura-tjenesten kunne ikke svare.'), $onboarding['lines']);
        $this->assertSame(array('HTTP 500: x'), $onboarding['details']);
    }

    public function testShowPageForOneSend(): void
    {
        $view = semanticPeppolShowView(self::row('Rejected', 'Forkert EAN', array('art' => 'DK', 'network' => 4)), 1);
        $this->assertSame('Kreditnota 1001', $view['title']);
        $this->assertSame('error', $view['tone']);
        $this->assertSame('Afvist af modtager: Forkert EAN', $view['status']['label']);
        $this->assertContains('Afsendt 2026-11-12 09:30 via NemHandel', $view['lines']);
        $this->assertSame(array(), $view['details'], 'the reason is already in the status');
        $this->assertSame('Afsendelsen findes ikke', semanticPeppolShowView(null, 1)['title']);
        $late = semanticPeppolShowView(self::row('Sent', 'Error: modtagerens adgangspunkt svarede ikke'), 1);
        $this->assertSame(array('Error: modtagerens adgangspunkt svarede ikke'), $late['details']);
    }
}
