<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/stdFunc/stamdataSmtp.php';

/**
 * stamdata_smtp() (includes/stdFunc/stamdataSmtp.php) chooses the mail server, login and sender
 * for Glemt adgangskode and user_send_mail() from stamdata (adresser art 'S'). Saldi's own server on
 * localhost takes anonymous mail from kan_ikke_besvares@saldi.dk; the regnskab's own server gets
 * its login and the regnskab's address as sender. No database or network: tests/characterization/
 * index/PasswordRecoveryMailTest.test.php sends the page's mail to an SMTP sink.
 */
final class stamdataSmtp extends TestCase
{
    private const LOCAL_FROM = 'kan_ikke_besvares@saldi.dk';

    /**
     * A stand-in for PHPMailer with the properties stamdata_smtp() may set and PHPMailer's defaults.
     * A property the function sets that PHPMailer does not have shows up as an extra key in get_object_vars().
     */
    private static function mailer(): object
    {
        return new class {
            public string $Host = 'localhost';
            public bool $SMTPAuth = false;
            public string $Username = '';
            public string $Password = '';
            public string $SMTPSecure = '';
            public string $From = 'root@localhost';
        };
    }

    /** @param array<string, ?string>|false|null $stamdata */
    private function configure($stamdata): object
    {
        $mail = self::mailer();
        stamdata_smtp($mail, $stamdata, self::LOCAL_FROM);
        $this->assertSame(array_keys(get_object_vars(self::mailer())), array_keys(get_object_vars($mail)), 'only PHPMailer properties are set');
        return $mail;
    }

    /** @return array<string, array{0: array<string, ?string>|false|null}> */
    public static function saldisOwnServer(): array
    {
        $login = ['email' => 'firma@example.dk', 'felt_2' => 'smtp-user', 'felt_3' => 'smtp-secret', 'felt_4' => 'tls'];
        return [
            'felt_1 empty, login left over' => [['felt_1' => ''] + $login],
            'felt_1 null, login left over' => [['felt_1' => null] + $login],
            'felt_1 localhost, login left over' => [['felt_1' => 'localhost'] + $login],
            'felt_1 Localhost with spaces' => [['felt_1' => ' Localhost '] + $login],
            'no stamdata row' => [false],
            'no lookup (master database)' => [null],
        ];
    }

    /** @param array<string, ?string>|false|null $stamdata */
    #[DataProvider('saldisOwnServer')]
    public function testSaldisOwnServerGetsAnonymousMailFromSaldisSender($stamdata): void
    {
        $mail = $this->configure($stamdata);
        $this->assertSame('localhost', $mail->Host);
        $this->assertFalse($mail->SMTPAuth, 'a relay that offers no AUTH refuses a login');
        $this->assertSame('', $mail->Username);
        $this->assertSame('', $mail->Password);
        $this->assertSame('', $mail->SMTPSecure, 'felt_4 belongs to the server in felt_1');
        $this->assertSame(self::LOCAL_FROM, $mail->From);
    }

    public function testTheRegnskabsServerGetsItsLoginEncryptionAndOwnSender(): void
    {
        $mail = $this->configure(['email' => ' faktura@firma.dk; info@firma.dk', 'felt_1' => 'smtp.firma.dk:587', 'felt_2' => 'faktura@firma.dk', 'felt_3' => 'smtp-secret', 'felt_4' => 'TLS']);
        $this->assertSame('smtp.firma.dk:587', $mail->Host, 'a port stays in Host for PHPMailer to parse');
        $this->assertTrue($mail->SMTPAuth);
        $this->assertSame('faktura@firma.dk', $mail->Username);
        $this->assertSame('smtp-secret', $mail->Password);
        $this->assertSame('tls', $mail->SMTPSecure, 'PHPMailer knows ssl and tls in lower case only');
        $this->assertSame('faktura@firma.dk', $mail->From, 'the first stamdata address, as sendMail.php sends from');
    }

    public function testTheRegnskabsServerWithoutLoginStillGetsItsEncryptionAndSender(): void
    {
        $mail = $this->configure(['email' => 'info@firma.dk,salg@firma.dk', 'felt_1' => 'relay.firma.dk:465', 'felt_2' => '', 'felt_3' => 'left-over', 'felt_4' => 'ssl']);
        $this->assertSame('relay.firma.dk:465', $mail->Host);
        $this->assertFalse($mail->SMTPAuth);
        $this->assertSame('', $mail->Password, 'no password without a user');
        $this->assertSame('ssl', $mail->SMTPSecure);
        $this->assertSame('info@firma.dk', $mail->From);
    }

    public function testWithoutAValidStamdataAddressTheSenderStaysSaldis(): void
    {
        $this->assertSame(self::LOCAL_FROM, $this->configure(['email' => '', 'felt_1' => 'smtp.firma.dk', 'felt_2' => 'u', 'felt_3' => 'p', 'felt_4' => ''])->From);
        $this->assertSame(self::LOCAL_FROM, $this->configure(['email' => 'not an address', 'felt_1' => 'smtp.firma.dk', 'felt_2' => 'u', 'felt_3' => 'p', 'felt_4' => ''])->From);
    }
}
