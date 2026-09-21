<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * easyubl_interpret_response() is what debitor/api.php getInvoicesOrder() uses to decide whether
 * EasyUBL produced a document or the send failed. The fixtures are the reply shapes seen in
 * temp/<db>/fakture-result-raw-*.txt: EasyUBL's documented {errNo, message,
 * base64EncodedDocumentXml} object, an empty HTTP 500, an HTML error page, and cURL failures.
 *
 * No database, no network.
 *
 * History:
 * 20260914 Sawaneh Created (JOB-141, EXIT-SOUND invoice 129629 "tomt eller ugyldigt svar").
 * 20260915 Sawaneh PR #619 review: errNo != 0 with a document is an error, not a success.
 * 20260921 Sawaneh PR #619 review: a bare 'message' without errNo is informational when a document came.
 */
final class EasyUblResponseTest extends TestCase
{
    private const XML = '<?xml version="1.0"?><Invoice><ID>129629</ID></Invoice>';

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/stdFunc/easyUblResponse.php';
    }

    private static function reply(array $fields): string
    {
        return json_encode(array_merge(
            ['documentType' => 'Invoice', 'conveyer' => 'Nemhandel', 'errNo' => 0, 'message' => '', 'base64EncodedDocumentXml' => ''],
            $fields
        ));
    }

    public function testSuccessfulReplyYieldsDocument(): void
    {
        $out = easyubl_interpret_response(200, self::reply(['base64EncodedDocumentXml' => base64_encode(self::XML)]));
        self::assertSame('ok', $out['kind']);
        self::assertSame(self::XML, $out['xml']);
        self::assertSame(base64_encode(self::XML), $out['base64']);
        self::assertSame(0, $out['err_no']);
    }

    public function testSuccessWithInformationalMessageIsStillOk(): void
    {
        $out = easyubl_interpret_response(200, self::reply(['message' => 'OK', 'base64EncodedDocumentXml' => base64_encode(self::XML)]));
        self::assertSame('ok', $out['kind']);
        self::assertSame('OK', $out['message']);
    }

    public function testMessageWithoutErrNoIsOnlyAnErrorWhenNoDocumentCame(): void
    {
        $out = easyubl_interpret_response(200, '{"message":"Document queued","base64EncodedDocumentXml":"' . base64_encode(self::XML) . '"}');
        self::assertSame('ok', $out['kind']);
        self::assertNull($out['err_no']);
        self::assertSame(self::XML, $out['xml']);

        $out = easyubl_interpret_response(200, '{"message":"CompanyID unknown"}');
        self::assertSame('api_error', $out['kind']);
        self::assertSame('CompanyID unknown', $out['message']);
    }

    public function testValidJsonErrorCarriesEasyUblMessage(): void
    {
        $out = easyubl_interpret_response(200, self::reply(['errNo' => 4003, 'message' => 'Recipient 5790001272180 is not registered in Nemhandel']));
        self::assertSame('api_error', $out['kind']);
        self::assertSame(4003, $out['err_no']);
        self::assertSame('Recipient 5790001272180 is not registered in Nemhandel', $out['message']);
        self::assertSame('', $out['xml']);
    }

    public function testExplicitErrorWithDocumentIsStillAnError(): void
    {
        $out = easyubl_interpret_response(200, self::reply(['errNo' => 4003, 'message' => 'Recipient not registered', 'base64EncodedDocumentXml' => base64_encode(self::XML)]));
        self::assertSame('api_error', $out['kind']);
        self::assertSame(4003, $out['err_no']);
        self::assertSame('', $out['xml']);
        self::assertSame('', $out['base64']);

        $out = easyubl_interpret_response(200, '{"errorMessage":"Rejected","base64EncodedDocumentXml":"' . base64_encode(self::XML) . '"}');
        self::assertSame('api_error', $out['kind']);
        self::assertSame('', $out['xml']);
    }

    public function testHttp400WithJsonErrorIsApiError(): void
    {
        $out = easyubl_interpret_response(400, '{"errNo": 12, "message": "Invalid companyId"}');
        self::assertSame('api_error', $out['kind']);
        self::assertSame(400, $out['http_code']);
        self::assertSame('Invalid companyId', $out['message']);
    }

    public function testLegacyErrorKeysAreStillRead(): void
    {
        self::assertSame('Bad EAN', easyubl_interpret_response(200, '{"errorMessage":"Bad EAN"}')['message']);
        $out = easyubl_interpret_response(200, '{"error":{"code":"E-APS24003","text":"Address missing"}}');
        self::assertSame('api_error', $out['kind']);
        self::assertStringContainsString('E-APS24003', $out['message']);
    }

    #[DataProvider('emptyBodies')]
    public function testEmptyBodyIsEmpty($body): void
    {
        $out = easyubl_interpret_response(500, $body);
        self::assertSame('empty', $out['kind']);
        self::assertSame(500, $out['http_code']);
        self::assertSame('', $out['xml']);
    }

    public static function emptyBodies(): array
    {
        return ['empty string' => [''], 'whitespace' => ["  \r\n"], 'curl_exec false' => [false], 'null' => [null]];
    }

    public function testHtmlErrorPageIsInvalidJsonWithExcerpt(): void
    {
        $html = "<!DOCTYPE html>\n<html><head><title>500 - Internal server error.</title></head>\n<body>\n<h1>Server Error</h1>\n<p>The page cannot be displayed.</p>\n</body></html>";
        $out = easyubl_interpret_response(500, $html);
        self::assertSame('invalid_json', $out['kind']);
        self::assertSame('500 - Internal server error. Server Error The page cannot be displayed.', $out['detail']);
        self::assertStringNotContainsString('<', $out['detail']);
    }

    public function testJsonNullLiteralIsInvalidJson(): void
    {
        self::assertSame('invalid_json', easyubl_interpret_response(200, 'null')['kind']);
        self::assertSame('invalid_json', easyubl_interpret_response(200, '"just a string"')['kind']);
    }

    public function testHttp500WithJsonButNoMessageIsHttpError(): void
    {
        $out = easyubl_interpret_response(500, self::reply([]));
        self::assertSame('http_error', $out['kind']);
        self::assertSame('', $out['message']);
        self::assertNotSame('', $out['detail']);
    }

    public function testHttp500WithDocumentIsNotTreatedAsSuccess(): void
    {
        $out = easyubl_interpret_response(500, self::reply(['base64EncodedDocumentXml' => base64_encode(self::XML)]));
        self::assertSame('http_error', $out['kind']);
        self::assertSame('', $out['xml']);
    }

    #[DataProvider('documentlessSuccessBodies')]
    public function testHttp200WithoutUsableDocumentIsNoDocument(string $body): void
    {
        $out = easyubl_interpret_response(200, $body);
        self::assertSame('no_document', $out['kind']);
        self::assertSame('', $out['xml']);
    }

    public static function documentlessSuccessBodies(): array
    {
        return [
            'no base64 field' => [self::reply([])],
            'base64 not decodable' => [self::reply(['base64EncodedDocumentXml' => '!!!not-base64!!!'])],
            'base64 of blank' => [self::reply(['base64EncodedDocumentXml' => base64_encode("  \n")])],
            'errNo 0 with message but no document' => [self::reply(['message' => 'Queued'])],
        ];
    }

    public function testTransportErrorWinsOverBody(): void
    {
        $out = easyubl_interpret_response(0, false, 28, 'Operation timed out after 30001 milliseconds');
        self::assertSame('transport', $out['kind']);
        self::assertSame('Operation timed out after 30001 milliseconds', $out['message']);
        self::assertSame(0, $out['http_code']);
    }

    public function testExcerptIsCappedAt200Characters(): void
    {
        $out = easyubl_interpret_response(502, str_repeat('x', 500));
        self::assertSame(200, mb_strlen($out['detail']));
    }
}
