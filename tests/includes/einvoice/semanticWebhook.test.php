<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/einvoice/semanticWebhook.test.php --- ver 5.1.0 --- 2026-11-09 ---
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
// 20261109 CL/MMR SD-815 Created (draft). The checks behind debitor/semanticWebhook.php without a server or database: the URL token, Semantic's signature and the payload.
//                 Also how Semantic's statuses become the words on the order.

declare(strict_types=1);

namespace SaldiTests\Einvoice;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/einvoice/semanticStatus.php';

final class semanticWebhook extends TestCase
{
    private string $secret = '';

    protected function setUp(): void
    {
        $this->secret = bin2hex(random_bytes(32));
    }

    /**
     * @param array<string, mixed> $get
     * @param list<string>         $signingSecrets
     * @return array{httpStatus: int, reason: string, db: string|null, payload: array<string, mixed>|null}
     */
    private function preflight(array $get, string $body, string $method = 'POST', array $signingSecrets = array(), ?callable $verifier = null): array
    {
        return semantic_webhook_preflight($method, $body, $get, $this->secret, array('content-type' => 'application/json'), $signingSecrets, $verifier, 2048);
    }

    private function validGet(string $db = 'test_42'): array
    {
        return array('db' => $db, 't' => semantic_webhook_token($db, $this->secret));
    }

    public function testTokenIsAcceptedOnlyForItsOwnDatabase(): void
    {
        $token = semantic_webhook_token('test_42', $this->secret);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertTrue(semantic_webhook_token_valid('test_42', $token, $this->secret));
        $this->assertTrue(semantic_webhook_token_valid('test_42', strtoupper($token), $this->secret));
        $this->assertFalse(semantic_webhook_token_valid('test_43', $token, $this->secret), 'another database');
        $this->assertFalse(semantic_webhook_token_valid('test_42', $token, bin2hex(random_bytes(32))), 'another server secret');
        $this->assertFalse(semantic_webhook_token_valid('test_42', substr($token, 0, 63) . 'x', $this->secret));
        $this->assertFalse(semantic_webhook_token_valid('test_42', '', $this->secret));
        $this->assertFalse(semantic_webhook_token_valid('test_42', array($token), $this->secret));
        $this->assertFalse(semantic_webhook_token_valid('test_42', $token, null), 'no secret configured');
        $this->assertFalse(semantic_webhook_token_valid('test_42', semantic_webhook_token('test_42', 'short'), 'short'), 'a short secret is no secret');
        $this->assertFalse(semantic_webhook_token_valid("test_42' OR 1=1", semantic_webhook_token("test_42' OR 1=1", $this->secret), $this->secret), 'not a database name');
    }

    public function testWebhookUrlCarriesTheDatabaseAndItsToken(): void
    {
        $url = semantic_webhook_url('https://server.example.invalid/finans/', 'test_42', $this->secret);
        $this->assertStringStartsWith('https://server.example.invalid/finans/debitor/semanticWebhook.php?db=test_42&t=', $url);
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        $this->assertTrue(semantic_webhook_token_valid($query['db'], $query['t'], $this->secret));
    }

    public function testPreflightAcceptsAValidNotification(): void
    {
        $result = $this->preflight($this->validGet(), '{"documentId": 123456, "documentStatus": 5140, "documentNumber": "1001"}');
        $this->assertSame(200, $result['httpStatus']);
        $this->assertSame('test_42', $result['db']);
        $this->assertSame('123456', $result['payload']['documentId']);
        $this->assertSame(5140, $result['payload']['statusCode']);
        $this->assertSame('1001', $result['payload']['documentNumber']);
    }

    /**
     * @return array<string, array{array<string, mixed>|null, string, string, int, string}>
     */
    public static function refusals(): array
    {
        return array(
            'GET' => array(null, '{"documentId":1}', 'GET', 405, 'method_not_allowed'),
            'no db' => array(array('t' => 'x'), '{"documentId":1}', 'POST', 400, 'bad_request'),
            'db with SQL' => array(array('db' => "x'; DROP TABLE ordrer; --", 't' => 'x'), '{"documentId":1}', 'POST', 400, 'bad_request'),
            'wrong token' => array(array('db' => 'test_42', 't' => str_repeat('0', 64)), '{"documentId":1}', 'POST', 403, 'forbidden'),
            'no token' => array(array('db' => 'test_42'), '{"documentId":1}', 'POST', 403, 'forbidden'),
            'too large' => array(null, '{"documentId":1,"pad":"' . str_repeat('x', 3000) . '"}', 'POST', 413, 'too_large'),
            'not JSON' => array(null, 'documentId=1', 'POST', 400, 'bad_payload'),
            'JSON list' => array(null, '[1,2]', 'POST', 400, 'bad_payload'),
            'no id' => array(null, '{"documentStatus":5140}', 'POST', 400, 'bad_payload'),
            'id zero' => array(null, '{"documentId":0}', 'POST', 400, 'bad_payload'),
            'id text' => array(null, '{"documentId":"12 OR 1=1"}', 'POST', 400, 'bad_payload'),
            'empty uuid' => array(null, '{"ublDocumentId":"00000000-0000-0000-0000-000000000000"}', 'POST', 400, 'bad_payload'),
        );
    }

    /**
     * @param array<string, mixed>|null $get Null = a valid db and token.
     */
    #[DataProvider('refusals')]
    public function testPreflightRefusesAndChangesNothing(?array $get, string $body, string $method, int $status, string $reason): void
    {
        $result = $this->preflight($get ?? $this->validGet(), $body, $method);
        $this->assertSame($status, $result['httpStatus']);
        $this->assertSame($reason, $result['reason']);
        $this->assertNull($result['db']);
        $this->assertNull($result['payload']);
    }

    public function testWithoutOurSecretTheWebhookIsNotConfigured(): void
    {
        $result = semantic_webhook_preflight('POST', '{"documentId":1}', $this->validGet(), null, array(), array());
        $this->assertSame(503, $result['httpStatus']);
        $this->assertSame('not_configured', $result['reason']);
    }

    public function testSemanticSignatureIsPluggableAndFailsClosed(): void
    {
        $body = '{"documentId":1}';
        $this->assertSame(array('ok' => true, 'checked' => false, 'reason' => 'signing_off'), semantic_webhook_verify_signature(array(), $body, array()));
        $this->assertNull(semantic_webhook_signature_verifier(), 'the scheme is unknown until Semantic documents it');

        $unknown = $this->preflight($this->validGet(), $body, 'POST', array('secret-1'));
        $this->assertSame(503, $unknown['httpStatus']);
        $this->assertSame('signature_not_supported', $unknown['reason']);

        $seen = array();
        $verifier = function (array $headers, string $raw, array $secrets) use (&$seen): bool {
            $seen = array($headers, $raw, $secrets);
            return in_array('secret-2', $secrets, true) && ($headers['x-test-signature'] ?? '') === hash_hmac('sha256', $raw, 'secret-2');
        };
        $headers = array('x-test-signature' => hash_hmac('sha256', $body, 'secret-2'));
        $this->assertTrue(semantic_webhook_verify_signature($headers, $body, array('secret-1', 'secret-2'), $verifier)['ok'], 'either secret during a rotation');
        $this->assertSame(array($headers, $body, array('secret-1', 'secret-2')), $seen);
        $this->assertSame('invalid', semantic_webhook_verify_signature(array('x-test-signature' => 'bad'), $body, array('secret-2'), $verifier)['reason']);

        $refused = $this->preflight($this->validGet(), $body, 'POST', array('secret-2'), $verifier);
        $this->assertSame(401, $refused['httpStatus']);
        $this->assertSame('bad_signature', $refused['reason']);
    }

    public function testHeadersAreReadFromServerVariables(): void
    {
        $headers = semantic_request_headers(array('HTTP_X_SEMANTIC_SIGNATURE' => 'abc', 'CONTENT_TYPE' => 'application/json', 'REQUEST_METHOD' => 'POST'));
        $this->assertSame(array('x-semantic-signature' => 'abc', 'content-type' => 'application/json'), $headers);
    }

    public function testPayloadFieldsAreReadCarefully(): void
    {
        $payload = semantic_webhook_parse_payload(json_encode(array(
            'tenantId' => '11111111-1111-4111-8111-111111111111',
            'notificationID' => 77,
            'documentId' => '9223372036854775807',
            'ublDocumentId' => 'AAAAAAAA-BBBB-4CCC-8DDD-EEEEEEEEEEEE',
            'message' => "<b>Fejl</b>\n i   linje 2",
            'documentNumber' => 1001,
            'actionCode' => 5010,
        )));
        $this->assertTrue($payload['ok']);
        $this->assertSame('outgoing', $payload['kind']);
        $this->assertSame('9223372036854775807', $payload['documentId']);
        $this->assertSame('aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', $payload['ublDocumentId']);
        $this->assertNull($payload['statusCode'], 'no status in the notification: the webhook asks the API');
        $this->assertSame('Fejl i linje 2', $payload['text']);
        $this->assertSame('1001', $payload['documentNumber']);

        $inner = semantic_webhook_parse_payload('{"documentId":5,"jsonMessage":"{\"documentStatusCode\":\"5101\",\"errorText\":\"BR-CO-15\"}"}');
        $this->assertSame(5101, $inner['statusCode']);
        $this->assertSame('BR-CO-15', $inner['text']);

        $incoming = semantic_webhook_parse_payload('{"incomingDocumentId":9,"documentId":9}');
        $this->assertSame('incoming', $incoming['kind']);

        $this->assertSame('not_json', semantic_webhook_parse_payload('')['reason']);
        $this->assertSame('not_object', semantic_webhook_parse_payload('"text"')['reason']);
        $this->assertSame('not_object', semantic_webhook_parse_payload('{}')['reason']);
    }

    /**
     * @return array<string, array{string, string|null, string|null}>
     */
    public static function statuses(): array
    {
        return array(
            '0' => array('0', 'NoStatus', null),
            '5101' => array('5101', 'Error', 'Error'),
            '5110' => array('5110', 'Pending', 'Pending'),
            '5115' => array('5115', 'PendingValidating', 'PendingValidating'),
            '5120' => array('5120', 'PendingValid', 'PendingValid'),
            '5130' => array('5130', 'Sending', 'Sending'),
            '5135' => array('5135', 'Error', 'Error'),
            '5140' => array('5140', 'Sent', 'Sent'),
            '5150' => array('5150', 'Received', 'Received'),
            '5160' => array('5160', 'Confirmed', 'Confirmed'),
            '5170' => array('5170', 'Rejected', 'Rejected'),
            '5180' => array('5180', 'Approved', 'Approved'),
            '5199' => array('5199', 'Parked', 'Parked'),
            '5213 unnamed' => array('5213', 'Status5213', null),
            '5100 unnamed' => array(' 5100 ', 'Status5100', null),
            'local Unknown' => array('Unknown', 'Unknown', 'SendFailed'),
            'local ValidationFailed' => array('ValidationFailed', 'ValidationFailed', 'SendFailed'),
            'local Submitting' => array('Submitting', 'Submitting', null),
            'garbage' => array('Sent; DROP', null, null),
            'empty' => array('', null, null),
        );
    }

    #[DataProvider('statuses')]
    public function testStatusesBecomeTheWordsTheOrderPageKnows(string $semantic, ?string $word, ?string $digital): void
    {
        $this->assertSame($word, semantic_status_word($semantic));
        if ($word !== null) {
            $this->assertSame($digital, semantic_status_digital($word));
            if ($digital !== null) {
                $this->assertLessThanOrEqual(25, strlen($digital), 'ordrer.digital_status is varchar(25)');
            }
        }
    }

    public function testTheOrderPageWordsAreKept(): void
    {
        // debitor/ordre.php asks before a resend on 'Sent' and 'SendFailed'; einvoiceCvrDigitalDelivered() knows these four.
        foreach (array('5140' => 'Sent', '5150' => 'Received', '5160' => 'Confirmed', '5180' => 'Approved') as $code => $word) {
            $this->assertSame($word, semantic_status_digital((string)semantic_status_word((string)$code)));
        }
        $this->assertSame('SendFailed', semantic_status_digital('SendFailed'));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function transitions(): array
    {
        return array(
            'same twice' => array('Sent', 'Sent', false),
            'forward' => array('Pending', 'Sent', true),
            'skip ahead' => array('Submitting', 'Received', true),
            'backwards' => array('Sent', 'Pending', false),
            'out of order validating' => array('PendingValid', 'PendingValidating', false),
            'error while on its way' => array('Sending', 'Error', true),
            'error after sent' => array('Sent', 'Error', true),
            'error after received' => array('Received', 'Error', false),
            'retry after error' => array('Error', 'Pending', true),
            'unknown outcome resolved' => array('Unknown', 'Pending', true),
            'confirmed then approved' => array('Confirmed', 'Approved', true),
            'approved is final' => array('Approved', 'Rejected', false),
            'rejected is final' => array('Rejected', 'Sent', false),
            'unnamed number recorded' => array('Pending', 'Status5213', true),
            'unnamed number after final' => array('Approved', 'Status5213', false),
            'first status' => array('', 'Pending', true),
        );
    }

    #[DataProvider('transitions')]
    public function testStatusesNeverMoveBackwards(string $current, string $next, bool $apply): void
    {
        $this->assertSame($apply, semantic_status_transition($current, $next));
    }

    public function testPlanIsIdempotentAndKeepsNewErrorTexts(): void
    {
        $row = array('id' => 9, 'ordre_id' => 5, 'art' => 'DO', 'status' => 'Pending', 'status_text' => '');
        $plan = semantic_status_plan($row, '5140', null);
        $this->assertTrue($plan['apply']);
        $this->assertSame(array('status' => 'Sent'), $plan['fields']);
        $this->assertSame('Sent', $plan['digitalStatus']);

        $again = semantic_status_plan(array('status' => 'Sent') + $row, '5140', null);
        $this->assertFalse($again['apply']);
        $this->assertSame('unchanged', $again['reason']);
        $this->assertNull($again['digitalStatus']);

        $error = semantic_status_plan($row, '5101', "  BR-CO-15:\n totals ");
        $this->assertSame(array('status' => 'Error', 'status_text' => 'BR-CO-15: totals'), $error['fields']);

        $textOnly = semantic_status_plan(array('status' => 'Error', 'status_text' => 'old') + $row, '5101', 'new');
        $this->assertSame('text_only', $textOnly['reason']);
        $this->assertSame(array('status_text' => 'new'), $textOnly['fields']);
        $this->assertNull($textOnly['digitalStatus']);

        $back = semantic_status_plan(array('status' => 'Sent') + $row, '5110', null);
        $this->assertSame('refused_backwards', $back['reason']);
        $this->assertSame('Sent', $back['status']);

        $this->assertSame('unknown_status', semantic_status_plan($row, 'nonsense', null)['reason']);
    }
}
