<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/stdFunc/einvoiceCvr.php';
require_once __DIR__ . '/../../../debitor/debitorkortIncludes/einvoiceCvrBanner.php';

// 20261023 CL/MMR SD-774 Pins when a digital invoice to an EAN must carry the receiver's CVR, and that sendInvoice() stops before anything is logged or sent when it does not.
//                 A Danish receiver reached on a GLN rejects an invoice without its CVR (DK-R-017 / E-APS24003), and foreign receivers are not covered by that rule.
//                 The rule lives in includes/stdFunc/einvoiceCvr.php so that the send block, the popup on the order and the warning on the debitor card cannot disagree, and those functions are tested directly here.
//                 Two parity tests run the matching blocks lifted out of sendInvoice() next to the helpers, so the helpers cannot drift from what is actually sent.
//                 sendInvoice() opens a database connection and posts to EasyUBL, so the order of its steps is asserted on the source with comments stripped, because the comments around the block name the same functions.
final class EInvoiceCvrRequirementTest extends TestCase
{
    private const API = __DIR__ . '/../../../debitor/api.php';

    /** A municipal GLN, the one the SST-860 tests use. */
    private const GLN = '5798009003553';

    private static ?string $src = null;

    /** @var list<array{0: int|string, 1: string}>|null */
    private static ?array $tokens = null;

    // ------------------------------------------------------------------ normalisation

    /** @return array<string, array{?string, string}> */
    public static function rawCvrs(): array
    {
        return [
            'already clean'                     => ['53383211', '53383211'],
            'spaces'                            => [' 5338 3211 ', '53383211'],
            'tabs and newlines'                 => ["\t53383211\n", '53383211'],
            'DK prefix'                         => ['DK53383211', '53383211'],
            'lower-case dk prefix with spaces'  => ['dk 5338 3211', '53383211'],
            'DK before fewer than 8 digits'     => ['DK1234', 'DK1234'],
            'DK before 9 digits'                => ['DK123456789', 'DK123456789'],
            'foreign prefix kept, upper-cased'  => ['se232100025501', 'SE232100025501'],
            'Norwegian number with MVA'         => ['no 987654321 mva', 'NO987654321MVA'],
            'empty'                             => ['', ''],
            'whitespace only'                   => ['   ', ''],
            'null'                              => [null, ''],
        ];
    }

    /**
     * The DK prefix is only dropped in front of exactly 8 digits. A foreign number keeps its
     * prefix, because sendInvoice() reads it to tell a foreign number from a Danish one.
     */
    #[DataProvider('rawCvrs')]
    public function testACvrIsNormalisedTheWayItIsComparedAndSaved(?string $raw, string $expected): void
    {
        self::assertSame($expected, einvoice_normalize_cvr($raw),
            'normalising ' . var_export($raw, true));
    }

    // ------------------------------------------------------------------ modulus 11

    /** @return array<string, array{string, bool}> */
    public static function checkDigits(): array
    {
        return [
            '53383211'                      => ['53383211', true],
            '29190925'                      => ['29190925', true],
            '12345674'                      => ['12345674', true],
            '12345678, wrong check digit'   => ['12345678', false],
            '9 digits'                      => ['123456789', false],
            '7 digits'                      => ['1234567', false],
            'DK prefix is not stripped here' => ['DK53383211', false],
            'a letter'                      => ['5338321A', false],
            'empty'                         => ['', false],
        ];
    }

    #[DataProvider('checkDigits')]
    public function testTheCvrCheckDigitIsModulus11(string $cvr, bool $expected): void
    {
        self::assertSame($expected, einvoice_cvr_mod11_ok($cvr), "modulus 11 on '$cvr'");
    }

    // ------------------------------------------------------------------ new input

    /** @return array<string, array{?string, string}> */
    public static function typedCvrs(): array
    {
        return [
            'empty'                                 => ['', 'missing'],
            'whitespace only'                       => ['   ', 'missing'],
            'null'                                  => [null, 'missing'],
            'valid Danish'                          => ['53383211', ''],
            'valid Danish with DK prefix and spaces' => ['DK 5338 3211', ''],
            'valid Danish, lower-case dk'           => ['dk53383211', ''],
            'wrong check digit'                     => ['12345678', 'invalid'],
            'DK prefix, wrong check digit'          => ['DK12345678', 'invalid'],
            'Swedish organisation number'           => ['SE232100025501', ''],
            'Norwegian number with MVA'             => ['NO987654321MVA', ''],
            'DK with too few digits'                => ['DK1234', 'invalid'],
            '10 digits'                             => ['1234567890', 'invalid'],
            '7 digits'                              => ['1234567', 'invalid'],
        ];
    }

    /**
     * What the user types now is held to a valid Danish number (8 digits, modulus 11) or a
     * number with a foreign country prefix. A Danish number cannot sneak past the check digit
     * by being written as "DK...".
     */
    #[DataProvider('typedCvrs')]
    public function testNewInputIsCheckedBeforeItIsAccepted(?string $raw, string $expected): void
    {
        self::assertSame($expected, einvoice_cvr_input_problem($raw),
            'checking typed CVR ' . var_export($raw, true));
    }

    // ------------------------------------------------------------------ GLN endpoint

    /** @return array<string, array{?string, bool}> */
    public static function eanFields(): array
    {
        return [
            'plain EAN'                     => [self::GLN, true],
            'plain EAN with spaces around'  => [' ' . self::GLN . ' ', true],
            'GLN: prefix'                   => ['GLN:' . self::GLN, true],
            '0184 CVR scheme'               => ['0184:53383211', false],
            '0088 scheme'                   => ['0088:' . self::GLN, false],
            '0007 Swedish scheme'           => ['0007:2321000255', false],
            'empty'                         => ['', false],
            'whitespace only'               => ['   ', false],
            'null'                          => [null, false],
        ];
    }

    /**
     * A plain EAN or a non-numeric scheme routes on a GLN. A numeric scheme already carries the
     * party's own identifier, so it is not what SD-774 is about.
     */
    #[DataProvider('eanFields')]
    public function testWhichEanFieldsRouteOnAGln(?string $ean, bool $expected): void
    {
        self::assertSame($expected, einvoice_is_gln_endpoint($ean), 'EAN field ' . var_export($ean, true));
    }

    // ------------------------------------------------------------------ receiver country

    /** @return array<string, array{string, string, string}> */
    public static function receivers(): array
    {
        return [
            'nothing known'                         => ['', '', 'DK'],
            'Danmark'                               => ['Danmark', '', 'DK'],
            'Sverige'                               => ['Sverige', '', 'SE'],
            'Grønland counts as DK'                 => ['Grønland', '', 'DK'],
            'Grønland in ISO-8859-1 counts as DK'   => ["Gr\xF8nland", '', 'DK'],
            'Færøerne counts as DK'                 => ['Færøerne', '', 'DK'],
            'unknown country'                       => ['Mars', '', 'DK'],
            'empty country, Swedish CVR'            => ['', 'SE232100025501', 'SE'],
            'empty country, Danish CVR'             => ['', '53383211', 'DK'],
            'empty country, Greenland CVR'          => ['', 'GL12345678', 'DK'],
            'country field wins over the CVR'       => ['Sverige', '53383211', 'SE'],
        ];
    }

    #[DataProvider('receivers')]
    public function testTheReceiverCountryComesFromTheCountryFieldThenTheCvr(string $land, string $cvr, string $expected): void
    {
        self::assertSame($expected, einvoice_receiver_country($land, $cvr),
            'receiver country for land ' . var_export($land, true) . ' and CVR ' . var_export($cvr, true));
    }

    // ------------------------------------------------------------------ the rule

    /** @return array<string, array{?string, ?string, ?string, bool}> */
    public static function invoices(): array
    {
        return [
            'Danish GLN receiver without a CVR'         => [self::GLN, '', 'Danmark', true],
            'GLN receiver, empty country field'         => [self::GLN, '', '', true],
            'GLN: prefix, no CVR'                       => ['GLN:' . self::GLN, '', 'Danmark', true],
            'whitespace-only CVR counts as none'        => [self::GLN, '   ', 'Danmark', true],
            'Greenland counts as Danish'                => [self::GLN, '', 'Grønland', true],
            'unknown country falls back to DK'          => [self::GLN, '', 'Mars', true],
            'Swedish GLN receiver'                      => ['7322730089999', '', 'Sverige', false],
            'Norwegian GLN receiver'                    => [self::GLN, '', 'Norge', false],
            'Danish GLN receiver with a CVR'            => [self::GLN, '53383211', 'Danmark', false],
            'EAN typed into the CVR field (243109)'     => ['5798007466206', '5798007466206', 'Danmark', true],
            'other GLN in the CVR field, with spaces'   => [self::GLN, ' 5798007466206 ', 'Danmark', true],
            'GLN in the CVR field, foreign receiver'    => ['7322730089999', '5798007466206', 'Sverige', false],
            'GLN receiver with a foreign CVR'           => [self::GLN, 'SE232100025501', '', false],
            'no EAN at all'                             => ['', '', 'Danmark', false],
            'numeric scheme 0184'                       => ['0184:53383211', '', 'Danmark', false],
            'numeric scheme 0088'                       => ['0088:' . self::GLN, '', 'Danmark', false],
            'nothing at all'                            => [null, null, null, false],
        ];
    }

    /**
     * Only a Danish receiver reached on a GLN with no CVR anywhere is caught. Without an EAN the
     * older "neither CVR nor EAN" guard in sendInvoice() applies, and foreign receivers are
     * reached on their own identifiers.
     */
    #[DataProvider('invoices')]
    public function testOnlyADanishGlnReceiverWithoutACvrIsCaught(?string $ean, ?string $cvr, ?string $land, bool $expected): void
    {
        self::assertSame($expected, einvoice_cvr_missing($ean, $cvr, $land),
            'EAN ' . var_export($ean, true) . ', CVR ' . var_export($cvr, true) . ', land ' . var_export($land, true));
    }

    /**
     * Deliberate: a stored CVR that fails modulus 11 does not block the send, although the same
     * number typed now is refused. Such invoices have been accepted by their receivers, so
     * blocking them would stop invoices that work today. Only new input is held to the check digit.
     */
    public function testAStoredCvrThatFailsModulus11IsNotBlocked(): void
    {
        self::assertSame('invalid', einvoice_cvr_input_problem('12345678'),
            'the number used here is meant to fail modulus 11');
        self::assertFalse(einvoice_cvr_missing(self::GLN, '12345678', 'Danmark'),
            'a stored CVR failing modulus 11 now blocks the send, which stops invoices that are accepted today');
    }

    /**
     * MEDSHOP faktura 243109: the CVR field on order and card held the receiver's EAN, and Kalundborg
     * Kommune rejected the invoice. A 13-digit number in the CVR field is an EAN/GLN, not a CVR.
     */
    public function testAGlnInTheCvrFieldIsNoCvr(): void
    {
        self::assertTrue(einvoice_cvr_is_gln('5798007466206'));
        self::assertTrue(einvoice_cvr_is_gln(' 5798007466206 '));
        self::assertFalse(einvoice_cvr_is_gln('29189595'));
        self::assertFalse(einvoice_cvr_is_gln('SE232100025501'));
        self::assertFalse(einvoice_cvr_is_gln(''));
        self::assertFalse(einvoice_cvr_present('5798007466206'));
        self::assertFalse(einvoice_cvr_present('   '));
        self::assertTrue(einvoice_cvr_present('29189595'));
        self::assertTrue(einvoice_cvr_present('12345678'), 'a stored CVR failing modulus 11 still counts as present');
        self::assertTrue(einvoice_cvr_present('SE232100025501'));
    }

    /** @return array<string, array{string, string, string, string, string}> */
    public static function cards(): array
    {
        return [
            'business card, no CVR'                     => ['erhverv', self::GLN, '', 'Danmark', 'missing'],
            'empty type is shown as business'           => ['', self::GLN, '', 'Danmark', 'missing'],
            'old capitalised Erhverv'                   => ['Erhverv', self::GLN, '', 'Denmark', 'missing'],
            'EAN in the CVR field, empty type (243109)' => ['', '5798007466206', ' 5798007466206 ', 'Denmark', 'missing'],
            'private card is not checked'               => ['privat', self::GLN, '', 'Danmark', ''],
            'old capitalised Privat is not checked'     => ['Privat', self::GLN, '', 'Danmark', ''],
            'business card with a CVR'                  => ['erhverv', self::GLN, '29189595', 'Danmark', ''],
            'stored CVR failing modulus 11'             => ['erhverv', self::GLN, '12345678', 'Danmark', 'invalid'],
            'foreign receiver'                          => ['', '7322730089999', '', 'Sverige', ''],
        ];
    }

    /**
     * The debitor card shows every card that is not 'privat' as a business card and saves an
     * empty type as 'erhverv' on opening, so the warning must cover an empty type and old
     * 'Erhverv' too. In MEDSHOP 465 of the 2026 e-invoices went to cards with an empty type.
     */
    #[DataProvider('cards')]
    public function testTheCardWarningCoversEveryNonPrivateCard(string $type, string $ean, string $cvr, string $land, string $expected): void
    {
        self::assertSame($expected, debitorkortEinvoiceCvrProblem($type, $ean, $cvr, $land),
            'type ' . var_export($type, true) . ', CVR ' . var_export($cvr, true));
    }

    // ------------------------------------------------------------------ parity with sendInvoice()

    /** @return array<string, array{string, string, string}> */
    public static function receiversForParity(): array
    {
        return [
            'nothing known'                 => ['', '', ''],
            'Danmark'                       => ['Danmark', '', ''],
            'Sverige'                       => ['Sverige', '', ''],
            'Norge'                         => ['Norge', '', ''],
            'Grønland'                      => ['Grønland', '', ''],
            'Grønland in ISO-8859-1'        => ["Gr\xF8nland", '', ''],
            'Færøerne'                      => ['Færøerne', '', ''],
            'unknown country'               => ['Mars', '', ''],
            'Swedish CVR, no country'       => ['', 'SE232100025501', 'SE232100025501'],
            'Danish CVR, Swedish address'   => ['Sverige', '53383211', 'DK53383211'],
            'Greenland CVR, no country'     => ['', 'GL12345678', 'GL12345678'],
        ];
    }

    /**
     * einvoice_receiver_country() says it derives the country exactly as sendInvoice() does. This
     * runs the real derivation from sendInvoice() for the same receiver and compares.
     *
     * @param string $land        ordrer.land
     * @param string $cvr         The CVR field as typed
     * @param string $prefixedCvr The same CVR as sendInvoice() has prefixed it before the derivation
     */
    #[DataProvider('receiversForParity')]
    public function testTheReceiverCountryMatchesSendInvoice(string $land, string $cvr, string $prefixedCvr): void
    {
        $block = $this->lift('$cvrCountry = ($cvrnr_with_prefix !== "")', '$deliveryCountryCode = countryCodeIso2(');
        $r_faktura = array('land' => $land, 'lev_land' => '');
        $cvrnr_with_prefix = $prefixedCvr;
        $countryCode = null;

        eval($block);

        self::assertSame($countryCode, einvoice_receiver_country($land, $cvr),
            'the helper and sendInvoice() disagree on the country, so the popup and the send block can disagree');
    }

    /** @return array<string, array{string}> */
    public static function eanFieldsForParity(): array
    {
        return [
            'plain EAN'                     => [self::GLN],
            'plain EAN with spaces around'  => [' ' . self::GLN . ' '],
            'GLN: prefix'                   => ['GLN:' . self::GLN],
            '0184 CVR scheme'               => ['0184:53383211'],
            '0088 scheme'                   => ['0088:' . self::GLN],
            '0007 Swedish scheme'           => ['0007:2321000255'],
            'no EAN'                        => [''],
        ];
    }

    /**
     * einvoice_is_gln_endpoint() mirrors the endpoint branches of sendInvoice(). The real
     * resolution runs with a CVR on file, because without one and without a GLN it shows an
     * alert and exits the process.
     *
     * Left out on purpose: a whitespace-only EAN field, which sendInvoice() routes as a GLN with
     * a blank id while the helper treats it as no EAN, and a lower-case or unknown non-numeric
     * scheme ("gln:", "XYZ:"), which the helper treats as a GLN while sendInvoice() keeps the
     * scheme as the endpoint type. Without a CVR, sendInvoice()'s older guard stops the latter first.
     */
    #[DataProvider('eanFieldsForParity')]
    public function testTheGlnTestMatchesSendInvoiceRouting(string $ean): void
    {
        $block = $this->lift('$cvrnr_with_prefix = "";', '$cvrCountry = ($cvrnr_with_prefix !== "")');
        $db = 'saldi_chartest';
        $id = 1;
        $r_faktura = array('ean' => $ean, 'cvrnr' => '53383211', 'firmanavn' => 'Test');
        $endpointId = $endpointType = null;

        eval($block);

        self::assertSame($endpointType === 'GLN', einvoice_is_gln_endpoint($ean),
            "sendInvoice() routes '$ean' as $endpointType, and the helper disagrees");
    }

    // ------------------------------------------------------------------ where sendInvoice() checks

    /**
     * The order of steps in sendInvoice() that makes the block correct.
     *
     * After the SST-884 card fallback, so a CVR on the debitor card counts. Before the payload is
     * logged to temp/<db>/data.json, so the log never shows an invoice that was not sent. Before
     * getInvoicesOrder(), so nothing reaches EasyUBL. The check reads the EAN and CVR the
     * fallback has filled in and the order's country, and the block runs only as the body of
     * that check.
     */
    public function testSendInvoiceChecksTheEffectiveCvrBeforeAnythingIsLoggedOrSent(): void
    {
        $code = self::code();

        $fnStart = self::at($code, 'function sendInvoice(', 0, 'sendInvoice() is gone');
        $fnEnd = self::at($code, 'function sendOrder(', $fnStart, 'cannot bound sendInvoice() - sendOrder() moved');
        $fallback = self::at($code, 'SELECT ean, cvrnr FROM adresser', $fnStart,
            'the SST-884 fallback to the debitor card is gone from sendInvoice()');
        $check = self::at($code, 'einvoice_cvr_missing(', $fnStart, 'sendInvoice() no longer calls einvoice_cvr_missing()');
        $block = self::at($code, 'easyUblCvrMissing(', $check,
            'sendInvoice() no longer stops through easyUblCvrMissing() after the check');
        $log = self::at($code, 'file_put_contents("../temp/$db/data.json"', $fnStart, 'the payload log is gone from sendInvoice()');
        $dispatch = self::at($code, 'getInvoicesOrder($data,', $fnStart, 'sendInvoice() no longer dispatches the payload');

        foreach (['fallback' => $fallback, 'check' => $check, 'block' => $block, 'log' => $log, 'dispatch' => $dispatch] as $what => $at) {
            self::assertLessThan($fnEnd, $at, "the $what is no longer inside sendInvoice()");
        }

        self::assertGreaterThan($fallback, $check,
            'the CVR is checked before the debitor card fallback, so a CVR on the card is ignored and the invoice is blocked for nothing');
        self::assertLessThan($log, $check,
            'the payload is logged before the CVR check, so data.json shows an invoice that was never sent');
        self::assertLessThan($dispatch, $check,
            'the payload is sent to EasyUBL before the CVR check, so the invoice the receiver rejects still goes out');
        self::assertLessThan($log, $block, 'easyUblCvrMissing() is reached only after the payload is logged');

        $call = substr($code, $check, $block - $check);
        self::assertStringContainsString('$r_faktura["ean"]', $call,
            'the check no longer reads the EAN that is sent');
        self::assertStringContainsString('$r_faktura["cvrnr"]', $call,
            'the check no longer reads the CVR that is sent');
        self::assertStringContainsString('$r_faktura["land"]', $call,
            'the check no longer reads the order\'s country, so foreign GLN receivers are blocked as if they were Danish');
        self::assertMatchesRegularExpression(
            '/if\s*\(\s*einvoice_cvr_missing\([^;{]*\)\s*\)\s*\{\s*easyUblCvrMissing\(/',
            substr($code, $fnStart, $fnEnd - $fnStart),
            'easyUblCvrMissing() is no longer the body of an if on einvoice_cvr_missing(), so it can block invoices that have a CVR');
    }

    /**
     * Nothing was sent, so there is no send result to record. digital_status SendFailed would show
     * a failed transmission on the order and make a resend ask first, for an invoice that never
     * left. The function also has to end the request, or sendInvoice() goes on to send.
     */
    public function testTheBlockNeitherSendsNorMarksTheOrder(): void
    {
        $body = self::functionBody('easyUblCvrMissing');

        self::assertStringNotContainsString('digital_status', $body,
            'easyUblCvrMissing() writes digital_status for an invoice that was never sent');
        self::assertStringNotContainsString('getInvoicesOrder(', $body, 'easyUblCvrMissing() sends the document');
        self::assertStringNotContainsString('data.json', $body, 'easyUblCvrMissing() writes the payload log');
        self::assertStringEndsWith('exit;', rtrim($body),
            'easyUblCvrMissing() can return, and sendInvoice() would then go on to send the invoice');
    }

    // ------------------------------------------------------------------ helpers

    private static function source(): string
    {
        if (self::$src === null) {
            $src = file_get_contents(self::API);
            self::assertNotFalse($src, 'could not read debitor/api.php');
            self::$src = $src;
        }
        return self::$src;
    }

    /**
     * api.php as tokens, comments dropped.
     *
     * The comments around the new block name einvoice_cvr_missing(), digital_status and the
     * payload log themselves, so a search over the raw source could be satisfied by a comment.
     *
     * @return list<array{0: int|string, 1: string}> [id, text]; id is a T_* constant, or the
     *         character itself for a single-character token.
     */
    private static function tokens(): array
    {
        if (self::$tokens === null) {
            $tokens = array();
            foreach (token_get_all(self::source()) as $t) {
                if (!is_array($t)) {
                    $tokens[] = array($t, $t);
                } elseif ($t[0] !== T_COMMENT && $t[0] !== T_DOC_COMMENT) {
                    $tokens[] = array($t[0], $t[1]);
                }
            }
            self::$tokens = $tokens;
        }
        return self::$tokens;
    }

    /** @return string api.php without its comments. */
    private static function code(): string
    {
        return implode('', array_column(self::tokens(), 1));
    }

    /**
     * The code between a function's outer braces, comments dropped.
     *
     * @param string $name The function name.
     * @return string
     */
    private static function functionBody(string $name): string
    {
        $tokens = self::tokens();
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i][0] !== T_FUNCTION) {
                continue;
            }
            $j = $i + 1;
            while ($j < $count && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            if ($j >= $count || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== $name) {
                continue;
            }
            while ($j < $count && $tokens[$j][0] !== '{') {
                $j++;
            }
            $depth = 0;
            $body = '';
            for ($k = $j; $k < $count; $k++) {
                $id = $tokens[$k][0];
                if ($id === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                    $depth++;
                } elseif ($id === '}') {
                    $depth--;
                    if ($depth === 0) {
                        return $body;
                    }
                }
                if ($k > $j) {
                    $body .= $tokens[$k][1];
                }
            }
            self::fail("$name() in api.php has no closing brace");
        }
        self::fail("$name() is gone from api.php");
    }

    /**
     * Position of $needle in $haystack from $offset; fails the test with $message when absent.
     */
    private static function at(string $haystack, string $needle, int $offset, string $message): int
    {
        $pos = strpos($haystack, $needle, $offset);
        self::assertNotFalse($pos, $message);
        return $pos;
    }

    /**
     * Lifts a contiguous run of sendInvoice()'s source between two code anchors, to be evaluated.
     */
    private function lift(string $from, string $to): string
    {
        $src = self::source();
        $fn = strpos($src, 'function sendInvoice(');
        self::assertNotFalse($fn, 'sendInvoice() is gone from api.php');
        $a = strpos($src, $from, $fn);
        self::assertNotFalse($a, "anchor is gone from sendInvoice(): $from");
        $b = strpos($src, $to, $a);
        self::assertNotFalse($b, "end anchor is gone from sendInvoice(): $to");
        return substr($src, $a, $b - $a);
    }
}
