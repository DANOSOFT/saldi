<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// 20261005 MJ SST-853 Pins the character set on settlement mails from a betalingsliste.
//                  debitor/mail_modtagere.php send_mail() assembles a UTF-8 body whose meta
//                  tag declares utf-8, but PHPMailer's CharSet defaults to iso-8859-1, so the
//                  header contradicted the bytes and Green Kids' recipients read "KÃ¦re" for
//                  "Kære". The failure is silent - the mail sends, it just looks like spam -
//                  so nothing but a test will catch it coming back.
//
//                  send_mail() is defined inside a page script that opens a session and
//                  prints on include, so the assignment is read out of the source rather than
//                  by loading the file, and the value found there is then fed to a real
//                  PHPMailer to confirm it actually produces a UTF-8 header.
final class SettlementMailCharsetTest extends TestCase
{
    private const PAGE = __DIR__ . '/../../../debitor/mail_modtagere.php';

    /** Returns the body of send_mail(), lifted from the page script by brace-walking. */
    private function sendMailBody(): string
    {
        $src = file_get_contents(self::PAGE);
        self::assertNotFalse($src, 'could not read mail_modtagere.php');

        $start = strpos($src, 'function send_mail(');
        self::assertNotFalse($start, 'send_mail() is gone from mail_modtagere.php');

        $open = strpos($src, '{', $start);
        self::assertNotFalse($open, 'send_mail() has no body');

        $depth = 0;
        for ($p = $open; $p < strlen($src); $p++) {
            if ($src[$p] === '{') {
                $depth++;
            } elseif ($src[$p] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($src, $start, $p - $start + 1);
                }
            }
        }

        self::fail('could not find the end of send_mail()');
    }

    /** The charset send_mail() assigns to PHPMailer, as written in the source. */
    private function assignedCharset(): string
    {
        $body = $this->sendMailBody();
        self::assertSame(1, preg_match('/\$mail->CharSet\s*=\s*([\'"])([^\'"]+)\1\s*;/', $body, $m),
            'send_mail() does not assign a literal $mail->CharSet - without it PHPMailer '
            . 'defaults to iso-8859-1 and the UTF-8 body is mislabelled');
        return $m[2];
    }

    public function testSendMailDeclaresUtf8(): void
    {
        self::assertSame('UTF-8', $this->assignedCharset());
    }

    /**
     * The assignment has to happen before the mail is handed to PHPMailer to send, or it has
     * no effect on the headers that go out.
     */
    public function testTheCharsetIsSetBeforeTheMailIsSent(): void
    {
        $body = $this->sendMailBody();

        $assignment = strpos($body, '$mail->CharSet');
        self::assertNotFalse($assignment, 'no CharSet assignment in send_mail()');

        $send = strpos($body, '$mail->Send()');
        self::assertNotFalse($send, 'send_mail() no longer calls $mail->Send()');

        self::assertLessThan($send, $assignment,
            'the charset is assigned after Send(), so the mail goes out with the default');
    }

    /**
     * The HTML part hardcodes its own meta charset. If that and the header disagree the bug
     * is back in a different disguise, so they are pinned together.
     */
    public function testTheHtmlMetaAgreesWithTheHeader(): void
    {
        $body = $this->sendMailBody();

        self::assertSame(1, preg_match('/charset=([\w-]+)[\\\\"\s;]/i', $body, $m),
            'the HTML part no longer declares a charset');

        self::assertSame(
            strtolower($this->assignedCharset()),
            strtolower($m[1]),
            'the meta tag and the PHPMailer charset disagree, which is what caused SST-853'
        );
    }

    /**
     * The value in the source actually yields a UTF-8 mail. Asserting the assignment alone
     * would pass for a string PHPMailer does not recognise.
     *
     * Builds and assembles a mail without sending it, the same way send_mail() does.
     */
    public function testTheAssignedCharsetProducesAUtf8MailWithReadableDanish(): void
    {
        // PHPMailer is vendored out of band - it is not in composer.json, and both
        // includes/vendor and vendor are gitignored - so it is absent from a fresh clone.
        // Probe for it rather than requiring a fixed path, which would fail the suite
        // instead of skipping this one test.
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            foreach (array(
                __DIR__ . '/../../../includes/vendor/autoload.php',
                __DIR__ . '/../../../vendor/autoload.php',
                __DIR__ . '/../../../../vendor/autoload.php',
            ) as $autoload) {
                if (!is_file($autoload)) {
                    continue;
                }
                require_once $autoload;
                // Keep going rather than stopping at the first autoloader that merely
                // exists: the root composer.json pulls in phpunit and php_codesniffer but
                // not PHPMailer, so vendor/autoload.php can load without providing it.
                if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                    break;
                }
            }
        }

        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            self::markTestSkipped(
                'PHPMailer is not vendored in this checkout, so the assembled-mail assertions '
                . 'cannot run here. The three source-level tests above still cover the fix.'
            );
        }

        // The customer's own wording from the ticket, in body and subject.
        $subject = 'Afregning for perioden - Ønsker god uge';
        $text = 'Kære Jonna (Konto: 1001)<br>Pengene for denne perioden vil bli overført '
            . 'til kontoen din i løpet av 1-3 virkedager.<br>Ønsker deg en fortsatt fin uke!';

        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->CharSet = $this->assignedCharset();
        $mail->From = 'no_18@saldi.dk';
        $mail->FromName = 'Green Kids Second Hand';
        $mail->addAddress('jonna@example.invalid');
        $mail->WordWrap = 50;
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = "<html><body>\n$text\n</body></html>";

        self::assertTrue($mail->preSend(), 'PHPMailer could not assemble the mail: ' . $mail->ErrorInfo);
        $raw = $mail->getSentMIMEMessage();

        self::assertMatchesRegularExpression('/Content-Type:[^\r\n]*charset=UTF-8/i', $raw,
            'the assembled mail does not declare UTF-8');

        // The subject survives its encoded-word round trip.
        self::assertSame(1, preg_match('/^Subject:.*$/im', $raw, $m), 'no Subject header');
        $decodedSubject = mb_decode_mimeheader(trim(substr($m[0], strlen('Subject:'))));
        self::assertSame($subject, trim($decodedSubject),
            'the subject does not decode back to what was set');
        self::assertStringNotContainsString('iso-8859-1', strtolower($m[0]),
            'the subject is still labelled iso-8859-1, which is the reported bug');

        // And the body reads as Danish rather than as mojibake.
        $parts = preg_split("/\r\n\r\n/", $raw, 2);
        $decoded = quoted_printable_decode($parts[1] ?? '');
        foreach (array('Kære', 'overført', 'Ønsker') as $word) {
            self::assertStringContainsString($word, $decoded, "the body lost '$word'");
        }
        // The exact mojibake the customer reported must not appear.
        foreach (array('KÃ¦re', 'overfÃ¸rt', 'Ã˜nsker') as $mojibake) {
            self::assertStringNotContainsString($mojibake, $decoded,
                "the body contains '$mojibake', the symptom SST-853 reported");
        }
    }
}
