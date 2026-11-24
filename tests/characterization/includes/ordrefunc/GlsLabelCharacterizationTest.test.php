<?php
// 20261113 CDX/NTR Characterize the GLS request and downloaded label before extracting it.
// 20261113 CDX/NTR Isolate cURL calls on PHP builds that compile the extension into the binary.

use PHPUnit\Framework\TestCase;

final class GlsLabelCharacterizationTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/saldi-gls-' . bin2hex(random_bytes(8));
        mkdir($this->directory . '/run', 0700, true);
        mkdir($this->directory . '/temp/fixture', 0700, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/temp/fixture/*') as $file) {
            unlink($file);
        }
        rmdir($this->directory . '/temp/fixture');
        rmdir($this->directory . '/temp');
        rmdir($this->directory . '/run');
        rmdir($this->directory);
    }

    /** @return array{stdout:string, stderr:string, request:array, transport:array} */
    private function label(string $scenario): array
    {
        // PHP 8 permits userland replacements for disabled built-in functions.
        // This also works when cURL is compiled into the binary and -n cannot disable it.
        $process = proc_open(
            [
                PHP_BINARY,
                '-d',
                'disable_functions=curl_init,curl_setopt_array,curl_exec,curl_errno,curl_error,curl_close',
                __DIR__ . '/../../support/run_gls_label.php',
                $this->directory,
                $scenario,
            ],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stdout . $stderr);
        return [
            'stdout' => $stdout,
            'stderr' => $stderr,
            'request' => json_decode(file_get_contents($this->directory . '/temp/fixture/pdf.json'), true),
            'transport' => json_decode(file_get_contents($this->directory . '/temp/fixture/transport.json'), true),
        ];
    }

    public function test_billing_address_and_minimum_weight_are_sent_and_the_pdf_is_downloaded(): void
    {
        $out = $this->label('billing');
        self::assertSame('', $out['stderr']);
        self::assertSame('%PDF-1.4 fixture label', $out['stdout']);
        self::assertSame([
            'Name1' => 'Billing & Company',
            'Street1' => 'Test "Street" 1',
            'CountryNum' => '752',
            'ZipCode' => '12345',
            'City' => 'Malmö',
            'Contact' => "O'Brien",
            'Email' => 'shipping@example.invalid',
        ], $out['request']['Addresses']['Delivery']);
        self::assertSame([['Weight' => 1, 'Reference' => 'customer-reference']], $out['request']['Parcels']);
        self::assertSame(123, $out['request']['Reference']);
        self::assertSame(date('Ymd'), $out['request']['ShipmentDate']);
        self::assertSame('http://api.gls.dk/ws/DK/V1/CreateShipment/', $out['transport']['url']);
        self::assertSame(['Content-type: application/json; charset=ISO-8859-1'], $out['transport']['headers']);
        self::assertTrue($out['transport']['return_transfer']);
        self::assertSame($out['request'], $out['transport']['request']);
        self::assertSame(
            ['PDF' => base64_encode('%PDF-1.4 fixture label')],
            json_decode(file_get_contents($this->directory . '/temp/fixture/pdf-res.json'), true)
        );
    }

    public function test_delivery_address_replaces_billing_address_and_weight_rounds_up(): void
    {
        $out = $this->label('delivery');
        self::assertSame('', $out['stderr']);
        self::assertSame('%PDF-1.4 fixture label', $out['stdout']);
        self::assertSame([
            'Name1' => str_repeat('N', 39),
            'Street1' => 'Delivery & Street 2',
            'CountryNum' => '208',
            'ZipCode' => '8000',
            'City' => 'Århus',
            'Contact' => str_repeat('C', 39),
            'Email' => 'shipping@example.invalid',
        ], $out['request']['Addresses']['Delivery']);
        self::assertSame([['Weight' => 3, 'Reference' => 'customer-reference']], $out['request']['Parcels']);
    }

    public function test_invalid_carrier_response_does_not_emit_a_pdf(): void
    {
        $out = $this->label('invalid');
        self::assertSame('', $out['stderr']);
        self::assertSame('Invalid response from GLS API', $out['stdout']);
        self::assertSame('{}', file_get_contents($this->directory . '/temp/fixture/pdf-res.json'));
    }

    public function test_transport_error_does_not_emit_a_pdf(): void
    {
        $out = $this->label('error');
        self::assertSame('', $out['stderr']);
        self::assertSame('Curl error: fixture transport failure', $out['stdout']);
        self::assertFileDoesNotExist($this->directory . '/temp/fixture/pdf-res.json');
    }
}
