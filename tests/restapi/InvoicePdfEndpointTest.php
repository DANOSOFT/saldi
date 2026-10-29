<?php
// tests/restapi/InvoicePdfEndpointTest.php
//
// GET /restapi/endpoints/v1/debitor/invoices/?id=<id>&pdf=1 (SD-751, K45 REST PDF part): a posted
// invoice of the caller's account comes back as its PDF, rendered by render_document(), with the
// document's own number in the file name; a missing, unposted or non-invoice id gives 404 and no
// document. The posted-invoice case needs pdftk and ghostscript on the web server; when the
// render fails there (500) and this machine has no pdftk either, that case is skipped with the reason.
//
// History:
// 20261029 CL/SZ SD-751: created.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/RestApiEnv.php';

final class InvoicePdfEndpointTest extends TestCase
{
    private const INVOICES = '/restapi/endpoints/v1/debitor/invoices/';

    private static int $debtorId = 0;
    private static int $next = 9300;

    public static function setUpBeforeClass(): void
    {
        $reason = RestApiEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        RestApiEnv::bootstrapTenantOnce();
        $conn = RestApiEnv::connect(RestApiEnv::testDb());
        $rows = RestApiEnv::rows($conn, "INSERT INTO adresser (kontonr, firmanavn, addr1, postnr, bynavn, email, art, gruppe, betalingsbet, betalingsdage)"
            . " VALUES (91930, 'Pdf Kunde', 'Pdfvej 9', '8000', 'Aarhus', 'pdf@example.invalid', 'D', 1, 'Netto', 8) RETURNING id");
        self::$debtorId = (int)$rows[0]['id'];
        pg_close($conn);
    }

    public static function tearDownAfterClass(): void
    {
        RestApiEnv::teardownTenant();
    }

    /** @return array{id: int, fakturanr: string} an order of $art (DO invoice, DK credit note) at $status, 2 x 125.00 at 25% VAT */
    private function order(string $art = 'DO', int $status = 3): array
    {
        $nr = (string)++self::$next;
        $conn = RestApiEnv::connect(RestApiEnv::testDb());
        $rows = RestApiEnv::rows($conn, "INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsbet, betalingsdage, kontonr, art, valuta,"
            . " ordredate, levdate, fakturadate, ordrenr, fakturanr, sum, moms, momssats, status, konto_id, udskriv_til, sprog)"
            . " VALUES ('Pdf Kunde', 'Pdfvej 9', '8000', 'Aarhus', 'pdf@example.invalid', 'Netto', 8, 91930, $1, 'DKK',"
            . " '2026-10-01', '2026-10-01', '2026-10-01', $2, $3, 250, 62.5, 25, $4, $5, 'email', 'Dansk') RETURNING id",
            [$art, (int)$nr, $status >= 3 ? $nr : '', $status, self::$debtorId]);
        $id = (int)$rows[0]['id'];
        RestApiEnv::rows($conn, "INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, beskrivelse, enhed, posnr, antal, pris, rabat, momssats, procent)"
            . " VALUES ($1, 0, 'X1', 'Pdfvare', 'stk', 1, 2, 125.00, 0, 25, 100)", [$id]);
        pg_close($conn);
        return ['id' => $id, 'fakturanr' => $nr];
    }

    /** @return array{status: int, type: string, disposition: string, body: string, json: ?array} */
    private function getPdf(int $id): array
    {
        $headers = [];
        $ch = curl_init(RestApiEnv::baseUrl() . self::INVOICES . "?id=$id&pdf=1");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . RestApiEnv::accessToken()],
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$headers): int {
                if (strpos($line, ':') !== false) {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            },
        ]);
        $body = (string)curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $json = json_decode($body, true);
        return ['status' => $status, 'type' => $headers['content-type'] ?? '', 'disposition' => $headers['content-disposition'] ?? '',
            'body' => $body, 'json' => is_array($json) ? $json : null];
    }

    public function test_a_posted_invoice_comes_back_as_its_pdf(): void
    {
        $invoice = $this->order();

        $res = $this->getPdf($invoice['id']);

        if ($res['status'] === 500 && trim((string)shell_exec('command -v pdftk 2>/dev/null')) === '') {
            $this->markTestSkipped('the server could not render the PDF and pdftk is not installed here: ' . $res['body']);
        }
        $this->assertSame(200, $res['status'], $res['body']);
        $this->assertSame('application/pdf', $res['type']);
        $this->assertSame("inline; filename=\"fakt{$invoice['fakturanr']}.pdf\"", $res['disposition']);
        $this->assertStringStartsWith('%PDF-', $res['body']);
    }

    public function test_a_missing_unposted_or_non_invoice_id_gives_no_document(): void
    {
        $draft = $this->order('DO', 1);
        $numberless = $this->order();
        $conn = RestApiEnv::connect(RestApiEnv::testDb());
        RestApiEnv::rows($conn, "UPDATE ordrer SET fakturanr = '' WHERE id = $1", [$numberless['id']]);
        pg_close($conn);
        $credit = $this->order('DK');

        $cases = ['missing' => 999999, 'unposted' => $draft['id'], 'status 3 without invoice number' => $numberless['id'], 'credit note' => $credit['id'], 'zero' => 0];
        foreach ($cases as $case => $id) {
            $res = $this->getPdf($id);
            if ($case === 'zero') {
                // id 0 is no id: the endpoint lists invoices instead of looking one up
                $this->assertStringNotContainsString('%PDF-', $res['body'], $case);
                continue;
            }
            $this->assertSame(404, $res['status'], "$case: {$res['body']}");
            $this->assertSame('application/json', $res['type'], $case);
            $this->assertFalse($res['json']['success'], $case);
            $this->assertStringNotContainsString('%PDF-', $res['body'], $case);
        }
    }
}
