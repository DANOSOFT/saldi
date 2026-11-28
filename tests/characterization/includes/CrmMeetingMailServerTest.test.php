<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * The CRM's meeting invitations go through the company's mail server as the rest of Saldi's mail does
 * (includes/crmFunctions.php crm_flush_mail(), pre-check on #1199, 2026-11-24).
 *
 * Glemt adgangskode and user_send_mail() choose their server and sender with stamdata_smtp() since 20261105: the
 * company's own server (stamdata felt_1) with its login and its own address as the sender, or Saldi's own server
 * without a login. crm_flush_mail() still had the code before that fix: it logged in whenever a login was left in
 * felt_2, also on Saldi's own server, and sent from kan_ikke_besvares@saldi.dk through the company's own server, which
 * refuses senders that are not its own.
 *
 * Pins: crm_flush_mail() as it is, with PHPMailer replaced by a stand-in that keeps each mail as it was set up and the
 * company's stamdata row given directly; nothing is sent and no database is used.
 *
 * History:
 * 20261124 CL/ASR Created.
 */
#[RunTestsInSeparateProcesses]
final class CrmMeetingMailServerTest extends TestCase
{
    /**
     * @param array<string, string> $firm the stamdata row (adresser art 'S')
     * @return object the mail as crm_flush_mail() set it up
     */
    private static function invitation(array $firm): object
    {
        if (!is_file(__DIR__ . '/../../../vendor/autoload.php')) {
            self::markTestSkipped('crm_flush_mail() needs composer\'s vendor/autoload.php');
        }
        eval('namespace PHPMailer\PHPMailer;
            class PHPMailer {
                public $SMTPOptions; public $CharSet; public $Host; public $SMTPAuth; public $Username; public $Password;
                public $SMTPSecure = ""; public $Timeout; public $From; public $FromName; public $Subject; public $Body;
                public $AltBody; public $Ical; public $replyTo = []; public $to = [];
                public function IsSMTP() {}
                public function IsHTML($html) {}
                public function AddReplyTo($address, $name = "") { $this->replyTo[] = $address; }
                public function AddAddress($address, $name = "") { $this->to[] = $address; }
                public function Send() { $GLOBALS["__crm_sent"][] = $this; return true; }
            }');
        $GLOBALS['__crm_firm'] = $firm;
        eval('
            function db_select($q, $where = "") { return "stamdata"; }
            function db_fetch_array($r) { return $GLOBALS["__crm_firm"]; }
        ');
        $GLOBALS['db_encode'] = 'UTF8';
        require_once __DIR__ . '/../../../includes/crmFunctions.php';

        $GLOBALS['crm_mail_queue'] = [['kunde@example.invalid', 'Møde', 'Vi ses', "BEGIN:VCALENDAR\r\nEND:VCALENDAR"]];
        $GLOBALS['__crm_sent'] = [];
        self::assertSame(['sent' => 1, 'failed' => 0], crm_flush_mail());
        self::assertCount(1, $GLOBALS['__crm_sent']);
        return $GLOBALS['__crm_sent'][0];
    }

    public function test_an_invitation_goes_through_the_companys_own_server_with_its_login_and_from_its_own_address(): void
    {
        $mail = self::invitation(['firmanavn' => 'Firma ApS', 'email' => 'info@firma.example', 'felt_1' => 'smtp.firma.example',
            'felt_2' => 'bruger', 'felt_3' => 'kode', 'felt_4' => 'TLS']);

        $this->assertSame('smtp.firma.example', $mail->Host);
        $this->assertTrue($mail->SMTPAuth);
        $this->assertSame('bruger', $mail->Username);
        $this->assertSame('kode', $mail->Password);
        $this->assertSame('tls', $mail->SMTPSecure);
        $this->assertSame('info@firma.example', $mail->From, 'a company server refuses senders that are not its own');
        $this->assertSame('Firma ApS', $mail->FromName);
        $this->assertSame(['info@firma.example'], $mail->replyTo);
        $this->assertSame(['kunde@example.invalid'], $mail->to);
    }

    public function test_on_saldis_own_server_an_invitation_does_not_log_in_with_a_login_left_in_stamdata(): void
    {
        $mail = self::invitation(['firmanavn' => 'Firma ApS', 'email' => 'info@firma.example', 'felt_1' => '',
            'felt_2' => 'gammel', 'felt_3' => 'kode', 'felt_4' => '']);

        $this->assertSame('localhost', $mail->Host);
        $this->assertFalse($mail->SMTPAuth, "Saldi's own server offers no login, and a login left in felt_2 got the mail refused");
        $this->assertSame('kan_ikke_besvares@saldi.dk', $mail->From);
        $this->assertSame(['info@firma.example'], $mail->replyTo);
    }
}
