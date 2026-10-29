<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// 20261029 CL/MJ SST-885 Pins that "Bemærkning til ordre" (ordrer.notes) reaches an e-invoice, e-credit
//                 note or e-order only when the company has ticked "Send 'Bemærkning til ordre' med på
//                 e-fakturaer" (settings sendOrderNote/easyUBL). Customers keep internal remarks there;
//                 the PDF prints it only when the form has the field, but every e-document carried it
//                 (MEDSHOP: 92 of 844 e-invoices since April).
//
//                 debitor/api.php opens a session and a database connection when included, so
//                 easyUblOrderNote() is lifted out of the source, renamed, and run with its settings
//                 read answered by the test, and the two payloads are checked to call it.
final class EInvoiceOrderNoteTest extends TestCase
{
    private const API = __DIR__ . '/../../../debitor/api.php';

    /** @var array<string, string> var_grp/var_name => var_value, read by setting() */
    public static array $settings = array();

    private static function source(): string
    {
        $src = file_get_contents(self::API);
        self::assertNotFalse($src, 'could not read debitor/api.php');
        return $src;
    }

    /**
     * The settings read the lifted helper makes, answered from $settings.
     */
    public static function setting(string $varName, string $varGrp, string $default): string
    {
        return self::$settings["$varGrp/$varName"] ?? $default;
    }

    public static function setUpBeforeClass(): void
    {
        // The real get_settings_value() may already be loaded by another test in the same run,
        // so the lifted copy is renamed and pointed at setting() instead of a global stand-in.
        if (!function_exists('liftedEasyUblOrderNote')) {
            $src = self::source();
            $a = strpos($src, 'function easyUblOrderNote(');
            self::assertNotFalse($a, 'easyUblOrderNote() is gone from api.php');
            $b = strpos($src, "\n    }\n", $a);
            self::assertNotFalse($b, 'end of easyUblOrderNote() not found');
            $body = substr($src, $a, $b - $a) . "\n    }\n";
            self::assertSame(1, substr_count($body, 'get_settings_value('), 'the helper no longer reads one setting');
            $body = str_replace('get_settings_value(', 'EInvoiceOrderNoteTest::setting(', $body);
            eval(str_replace('function easyUblOrderNote(', 'function liftedEasyUblOrderNote(', $body));
        }
    }

    protected function setUp(): void
    {
        self::$settings = array();
    }

    public function testTheNoteIsNotSentByDefault(): void
    {
        self::assertSame('', liftedEasyUblOrderNote('Godkendt SE nummer til import uden moms'));
    }

    public function testTheNoteIsNotSentWhenTheSettingIsOff(): void
    {
        self::$settings['easyUBL/sendOrderNote'] = 'off';
        self::assertSame('', liftedEasyUblOrderNote('Faktura sendes på EAN'));
    }

    public function testTheNoteIsSentWhenTheSettingIsOn(): void
    {
        self::$settings['easyUBL/sendOrderNote'] = 'on';
        self::assertSame('Faktura sendes på EAN', liftedEasyUblOrderNote('Faktura sendes på EAN'));
    }

    public function testAnEmptyNoteStaysEmpty(): void
    {
        self::$settings['easyUBL/sendOrderNote'] = 'on';
        self::assertSame('', liftedEasyUblOrderNote(null));
        self::assertSame('', liftedEasyUblOrderNote(''));
    }

    public function testBothPayloadsGoThroughTheSetting(): void
    {
        $src = self::source();
        // sendInvoice() and sendOrder() must both send the note through easyUblOrderNote(),
        // and no payload may send ordrer.notes unconditionally.
        self::assertSame(2, substr_count($src, '"note" => easyUblOrderNote($r_faktura["notes"])'));
        self::assertStringNotContainsString('"note" => $r_faktura["notes"]', $src);
    }
}
