<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * includes/opretEmailFunc.php - the welcome email editor (admin/opret_email.php) and the
 * server-to-server send endpoint (admin/opret_email_send.php) share this module.
 *
 * Pure helpers are called in-process. opret_email_build()/opret_email_send() run in a child
 * process (tests/fixtures/opret_email/send_harness.php) with the two db-backed lookups stubbed
 * and mail() captured through sendmail_path, so the message that would reach the MTA - headers
 * and both MIME parts - can be asserted. No database, no mail leaves the machine.
 *
 * History:
 * 20260924 Sawaneh Created (PR #458 review): placeholder substitution, header injection,
 *                  CSRF gate, price parsing, log masking, the delivered message and error codes.
 */
final class OpretEmailTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    private static string $mailFile = '';
    private static string $scenarioFile = '';

    public static function setUpBeforeClass(): void
    {
        require_once self::ROOT . '/includes/std_func.php';
        require_once self::ROOT . '/includes/opretEmailFunc.php';
        self::$mailFile = tempnam(sys_get_temp_dir(), 'oemail');
        self::$scenarioFile = tempnam(sys_get_temp_dir(), 'oescn');
    }

    public static function tearDownAfterClass(): void
    {
        foreach (array(self::$mailFile, self::$scenarioFile) as $file) {
            if ($file !== '' && is_file($file)) {
                unlink($file);
            }
        }
    }

    private const PACKAGE = array('navn' => 'Finans', 'pris' => 1299.5);

    private static function render(string $html, array $vars = array(), ?array $package = self::PACKAGE): string
    {
        return opret_email_render($html, $package, $vars);
    }

    // ---- placeholder substitution -------------------------------------------------------

    public function testEveryPlaceholderIsSubstituted(): void
    {
        $out = self::render(
            '{{navn}}|{{ cvrnr }}|{{tlf}}|{{email}}|{{pakke_navn}}|{{pakke_pris}}',
            array('navn' => 'Jens', 'cvrnr' => '12345678', 'tlf' => '22334455', 'email' => 'j@example.com')
        );
        self::assertSame('Jens|12345678|22334455|j@example.com|Finans|1.299,50 kr.', $out);
    }

    public function testValuesAreHtmlEscaped(): void
    {
        $out = self::render('<p>{{navn}}</p>', array('navn' => '<script>alert(1)</script>" onmouseover="x'));
        self::assertStringNotContainsString('<script>', $out);
        self::assertStringNotContainsString('" onmouseover', $out);
        self::assertSame('<p>&lt;script&gt;alert(1)&lt;/script&gt;&quot; onmouseover=&quot;x</p>', $out);
    }

    public static function backreferenceLookalikes(): array
    {
        return array('$0' => array('A$0B'), '\1' => array('A\1B'), '${1}' => array('A${1}B'), '$1' => array('$1'));
    }

    #[DataProvider('backreferenceLookalikes')]
    public function testRegexReplacementSyntaxInAValueIsInsertedLiterally(string $navn): void
    {
        self::assertSame("Hej $navn", self::render('Hej {{navn}}', array('navn' => $navn)));
    }

    public function testAPlaceholderInsideAValueIsNotSubstitutedAgain(): void
    {
        $out = self::render('{{navn}} / {{email}}', array('navn' => '{{email}}', 'email' => 'x@example.com'));
        self::assertSame('{{email}} / x@example.com', $out);
    }

    public function testUnknownPlaceholdersAreLeftAlone(): void
    {
        self::assertSame('{{kodeord}} {{navn', self::render('{{kodeord}} {{navn', array('navn' => 'Jens')));
    }

    public function testMissingPackageAndValuesRenderEmpty(): void
    {
        self::assertSame('[][][]', self::render('[{{pakke_navn}}][{{pakke_pris}}][{{navn}}]', array(), null));
    }

    public function testEveryPlaceholderLabelExistsInTekster(): void
    {
        $ids = self::teksterIds();
        foreach (opret_email_placeholders() as $name => $textId) {
            self::assertArrayHasKey($textId, $ids, "label text id for {{{$name}}}");
        }
    }

    // ---- header injection ---------------------------------------------------------------

    public function testHeaderValueStripsLineBreaksAndNul(): void
    {
        self::assertSame(
            'Velkommen X  Bcc: victim@example.com',
            opret_email_header_value("Velkommen X\r\nBcc: victim@example.com\0")
        );
    }

    // ---- CSRF gate ----------------------------------------------------------------------

    private static function csrf(array $server, string $sessionToken = 'tok'): bool
    {
        $savedServer = $_SERVER;
        $savedSession = $_SESSION ?? null;
        $_SERVER = $server + array('HTTP_HOST' => 'ssl12.saldi.dk');
        $_SESSION = array('opret_email_csrf' => $sessionToken);
        try {
            return opret_email_csrf_ok();
        } finally {
            $_SERVER = $savedServer;
            if ($savedSession === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $savedSession;
            }
        }
    }

    public function testCsrfAcceptsTheEditorsOwnRequest(): void
    {
        self::assertTrue(self::csrf(array('CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => 'tok')));
        self::assertTrue(self::csrf(array(
            'CONTENT_TYPE' => 'application/json; charset=UTF-8', 'HTTP_X_CSRF_TOKEN' => 'tok',
            'HTTP_ORIGIN' => 'https://ssl12.saldi.dk',
        )));
        self::assertTrue(self::csrf(array(
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => 'tok',
            'HTTP_ORIGIN' => 'https://ssl12.saldi.dk:8443', 'HTTP_HOST' => 'ssl12.saldi.dk:8443',
        )));
    }

    public static function rejectedCsrf(): array
    {
        $ok = array('CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => 'tok');
        return array(
            'wrong token'        => array(array('HTTP_X_CSRF_TOKEN' => 'other') + $ok, 'tok'),
            'missing token'      => array(array('CONTENT_TYPE' => 'application/json'), 'tok'),
            'no session token'   => array($ok, ''),
            'urlencoded form'    => array(array('CONTENT_TYPE' => 'application/x-www-form-urlencoded') + $ok, 'tok'),
            'multipart form'     => array(array('CONTENT_TYPE' => 'multipart/form-data; boundary=x') + $ok, 'tok'),
            'text/plain form'    => array(array('CONTENT_TYPE' => 'text/plain') + $ok, 'tok'),
            'foreign origin'     => array(array('HTTP_ORIGIN' => 'https://evil.example') + $ok, 'tok'),
            'lookalike origin'   => array(array('HTTP_ORIGIN' => 'https://ssl12.saldi.dk.evil.example') + $ok, 'tok'),
            'origin other port'  => array(array('HTTP_ORIGIN' => 'https://ssl12.saldi.dk:8443') + $ok, 'tok'),
        );
    }

    #[DataProvider('rejectedCsrf')]
    public function testCsrfRejects(array $server, string $sessionToken): void
    {
        self::assertFalse(self::csrf($server, $sessionToken));
    }

    public function testCsrfTokenIsEmptyWithoutASession(): void
    {
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame('', opret_email_csrf_token());
    }

    // ---- price parsing ------------------------------------------------------------------

    public static function prices(): array
    {
        return array(
            'grouped with decimals' => array('1.299,50', 1299.5),
            'plain with decimals'   => array('1299,50', 1299.5),
            'plain integer'         => array('1299', 1299.0),
            'one decimal'           => array(' 99,5 ', 99.5),
            'US decimal point'      => array('1299.50', null),
            'three decimals'        => array('1,999', null),
            'letters'               => array('abc', null),
            'empty'                 => array('', null),
            'negative'              => array('-5', null),
            'bad grouping'          => array('12.99', null),
        );
    }

    #[DataProvider('prices')]
    public function testParsePrice(string $input, ?float $expected): void
    {
        self::assertSame($expected, opret_email_parse_price($input));
    }

    // ---- delivery log -------------------------------------------------------------------

    public static function maskedAddresses(): array
    {
        return array(
            'normal'     => array('jens@example.com', 'j***@example.com'),
            'empty'      => array('  ', '(tom)'),
            'no at'      => array('jens.example.com', '***'),
            'leading at' => array('@example.com', '***'),
            'line break' => array("j\r\nFAKE@example.com", 'j***@example.com'),
        );
    }

    #[DataProvider('maskedAddresses')]
    public function testLogMasksTheAddress(string $email, string $expected): void
    {
        self::assertSame($expected, opret_email_maskeret_email($email));
    }

    public function testLogValueCannotStartANewLine(): void
    {
        self::assertSame('finans  opret_email_send: forged', opret_email_log_value("finans\r\nopret_email_send: forged"));
    }

    // ---- editor output ------------------------------------------------------------------

    public function testQuillClassesBecomeInlineStyles(): void
    {
        self::assertSame(
            '<p class="keep" style="text-align:center;padding-left:6em">x</p>',
            opret_email_inline_styles('<p class="ql-align-center keep ql-indent-2">x</p>')
        );
        self::assertSame(
            '<p style="color:red;font-size:1.5em">x</p>',
            opret_email_inline_styles('<p class="ql-size-large" style="color:red;">x</p>')
        );
        self::assertSame('<p class="other">x</p>', opret_email_inline_styles('<p class="other">x</p>'));
    }

    public function testPlaintextWritesLinksOutAndKeepsParagraphs(): void
    {
        self::assertSame(
            "Hej Jens & co\n\nLog ind (https://saldi.dk/login)\nlinje 2",
            opret_email_plaintext('<p>Hej Jens &amp; co</p><p><a href="https://saldi.dk/login">Log ind</a><br>linje 2</p>')
        );
    }

    public function testErrorCodesMapToTheirTexts(): void
    {
        $ids = self::teksterIds();
        $expected = array(
            'invalid_email'   => 'Ugyldig emailadresse.',
            'unknown_package' => 'Ukendt pakke.',
            'empty_template'  => 'Skabelonen er tom - mailen blev ikke sendt.',
            'mail_failed'     => 'Mailserveren afviste beskeden.',
        );
        foreach ($expected as $code => $danish) {
            $id = opret_email_error_textid($code);
            self::assertSame($danish, $ids[$id] ?? null, $code);
        }
        self::assertSame(0, opret_email_error_textid('nope'));
    }

    // ---- build and send (child process) -------------------------------------------------

    /**
     * @return array{0: array, 1: string}  The function's return value and the captured mail.
     */
    private function runHarness(array $scenario, string $sendmail = ''): array
    {
        $scenario += array(
            'action'   => 'send',
            'settings' => array('emne' => 'Velkommen {{navn}}', 'html' => '<p>Hej {{navn}}, du har valgt {{pakke_navn}} til {{pakke_pris}}.</p>', 'afsender' => 'Saldi <info@saldi.dk>'),
            'package'  => self::PACKAGE,
            'to'       => 'jens@example.com',
            'kode'     => 'finans',
            'vars'     => array('navn' => 'Jens'),
        );
        file_put_contents(self::$scenarioFile, json_encode($scenario));
        file_put_contents(self::$mailFile, '');
        $sendmail = $sendmail !== '' ? $sendmail : 'cat >> ' . escapeshellarg(self::$mailFile);
        $cmd = escapeshellarg(PHP_BINARY) . ' -d ' . escapeshellarg('sendmail_path=' . $sendmail)
            . ' ' . escapeshellarg(self::ROOT . '/tests/fixtures/opret_email/send_harness.php')
            . ' ' . escapeshellarg(self::$scenarioFile) . ' 2>&1';
        $raw = (string) shell_exec($cmd);
        $out = json_decode($raw, true);
        self::assertIsArray($out, "harness output: $raw");
        return array($out, (string) file_get_contents(self::$mailFile));
    }

    private static function headers(string $mail): string
    {
        return preg_split("/\r?\n\r?\n/", $mail, 2)[0];
    }

    public function testSendDeliversOneMultipartMessage(): void
    {
        [$r, $mail] = $this->runHarness(array());
        self::assertTrue($r['success']);
        self::assertSame('', $r['error_code']);
        self::assertSame('Velkommen Jens', $r['emne']);
        self::assertSame('Finans', $r['pakke']);

        self::assertSame(1, substr_count($mail, 'MIME-Version: 1.0'), 'exactly one message');
        $headers = self::headers($mail);
        self::assertMatchesRegularExpression('/^To: jens@example\.com\r?$/m', $headers);
        self::assertMatchesRegularExpression('/^From: Saldi <info@saldi\.dk>\r?$/m', $headers);
        self::assertMatchesRegularExpression('/^Subject: Velkommen Jens\r?$/m', $headers);
        self::assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $mail);
        self::assertStringContainsString('Content-Type: text/html; charset=UTF-8', $mail);
        self::assertStringContainsString('Hej Jens, du har valgt Finans til 1.299,50 kr.', $mail);
    }

    public function testNameCannotInjectHeaders(): void
    {
        [$r, $mail] = $this->runHarness(array('vars' => array('navn' => "X\r\nBcc: victim@example.com")));
        self::assertTrue($r['success']);
        self::assertDoesNotMatchRegularExpression('/^Bcc:/mi', self::headers($mail));
    }

    public function testSenderSettingCannotInjectHeaders(): void
    {
        [, $mail] = $this->runHarness(array('settings' => array(
            'emne' => 'Velkommen', 'html' => '<p>Hej</p>', 'afsender' => "Saldi <info@saldi.dk>\r\nBcc: victim@example.com",
        )));
        self::assertDoesNotMatchRegularExpression('/^Bcc:/mi', self::headers($mail));
    }

    public function testUtf8SubjectIsMimeEncoded(): void
    {
        [, $mail] = $this->runHarness(array('vars' => array('navn' => 'Søren')));
        self::assertStringContainsString('=?UTF-8?B?', self::headers($mail));
        self::assertStringNotContainsString('Søren', self::headers($mail));
    }

    public function testSubjectShowsEntitiesDecodedButBodyKeepsThemEscaped(): void
    {
        [$r, $mail] = $this->runHarness(array('vars' => array('navn' => 'Foo & <Bar>')));
        // the subject is plain text: the name comes out literally, not as entities
        self::assertSame('Velkommen Foo & <Bar>', $r['emne']);
        self::assertStringContainsString('Hej Foo &amp; &lt;Bar&gt;', $mail);
    }

    public static function refusedSends(): array
    {
        return array(
            'invalid address'    => array(array('to' => 'not-an-address'), 'invalid_email'),
            'address with CRLF'  => array(array('to' => "jens@example.com\r\nBcc: v@example.com"), 'invalid_email'),
            'unknown package'    => array(array('package' => null), 'unknown_package'),
            'empty template'     => array(array('settings' => array('emne' => 'Velkommen', 'html' => '')), 'empty_template'),
            'blank editor body'  => array(array('settings' => array('emne' => 'Velkommen', 'html' => '<p><br></p>')), 'empty_template'),
        );
    }

    #[DataProvider('refusedSends')]
    public function testRefusedSendsMailNothing(array $scenario, string $code): void
    {
        [$r, $mail] = $this->runHarness($scenario);
        self::assertFalse($r['success']);
        self::assertSame($code, $r['error_code']);
        self::assertSame('', $mail, 'no mail may be handed to the MTA');
    }

    public function testMtaRefusalIsMailFailed(): void
    {
        // '/bin/false', not 'false' - the ini parser reads a bare false as an empty value
        [$r] = $this->runHarness(array(), '/bin/false');
        self::assertFalse($r['success']);
        self::assertSame('mail_failed', $r['error_code']);
    }

    // ---- helpers ------------------------------------------------------------------------

    /** @return array<int, string>  tekster.csv text id => Danish text. */
    private static function teksterIds(): array
    {
        $ids = array();
        foreach (file(self::ROOT . '/importfiler/tekster.csv', FILE_IGNORE_NEW_LINES) as $line) {
            $cols = explode("\t", $line);
            if (isset($cols[1]) && ctype_digit($cols[0])) {
                $ids[(int) $cols[0]] = $cols[1];
            }
        }
        return $ids;
    }
}
