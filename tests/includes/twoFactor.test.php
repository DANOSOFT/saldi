<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/twoFactor.php';

/**
 * The rules of two-factor login that need no database (includes/twoFactor.php): which ways a code can reach a user,
 * which one is used, what the screen shows of the address, and how the code sent last is stored.
 */
final class twoFactor extends TestCase
{
    public function testOnIsReadFromEveryFormTheDatabaseAndTheFormsUse(): void
    {
        foreach (array('t', true, '1', 1, 'on') as $on) {
            $this->assertTrue(twofactor_is_on($on));
        }
        foreach (array('f', false, '0', 0, '', null) as $off) {
            $this->assertFalse(twofactor_is_on($off));
        }
    }

    public function testACodeCanGoToAPhoneNumberAndToAnEmail(): void
    {
        $this->assertSame(array('sms' => '12 34 56 78', 'email' => 'a@example.dk'), twofactor_methods(' 12 34 56 78 ', ' a@example.dk '));
        $this->assertSame(array('email' => 'a@example.dk'), twofactor_methods('', 'a@example.dk'));
        $this->assertSame(array('sms' => '+4512345678'), twofactor_methods('+4512345678', ''));
    }

    public function testANumberTooShortOrAnAddressThatIsNoneCountsAsMissing(): void
    {
        $this->assertSame(array(), twofactor_methods('1234', 'ikke en adresse'));
        $this->assertSame(array(), twofactor_methods(null, null));
    }

    public function testWithoutAChoiceSmsComesBeforeEmailAsItAlwaysDid(): void
    {
        $both = twofactor_methods('12345678', 'a@example.dk');
        $this->assertSame('sms', twofactor_pick($both));
        $this->assertSame('email', twofactor_pick(twofactor_methods('', 'a@example.dk')));
    }

    public function testTheUsersOwnChoiceDecides(): void
    {
        $both = twofactor_methods('12345678', 'a@example.dk');
        $this->assertSame('email', twofactor_pick($both, 'email'));
        $this->assertSame('sms', twofactor_pick($both, 'sms'));
    }

    public function testWhatIsAskedForAtLoginComesBeforeTheChoice(): void
    {
        $both = twofactor_methods('12345678', 'a@example.dk');
        $this->assertSame('sms', twofactor_pick($both, 'email', 'sms'));
        $this->assertSame('email', twofactor_pick($both, 'sms', 'email'));
    }

    public function testAWayWithNowhereToSendIsNeverPicked(): void
    {
        $onlyMail = twofactor_methods('', 'a@example.dk');
        $this->assertSame('email', twofactor_pick($onlyMail, 'sms'));
        $this->assertSame('email', twofactor_pick($onlyMail, 'sms', 'sms'));
        $this->assertSame('email', twofactor_pick($onlyMail, '', 'post'));
        $this->assertSame('', twofactor_pick(array(), 'sms', 'email'));
    }

    public function testTheScreenShowsOnlyTheEndOfANumberAndTheStartOfAnAddress(): void
    {
        $this->assertSame('** ** ** 78', twofactor_mask_phone('+45 12 34 56 78'));
        $this->assertSame('a***@example.com', twofactor_mask_email('ann@example.com'));
        $this->assertSame('x***@example.dk', twofactor_mask_email(' x@example.dk '));
        $this->assertSame('***', twofactor_mask_email('ingen adresse'));
        $this->assertStringNotContainsString('ann', twofactor_mask_email('ann@example.com'));
    }

    public function testOnlyTheNoticesOfTheTextMessageReachThePage(): void
    {
        $first = "<script>alert('Dette er den første SMS der afsendes fra Saldi i dit regnskab. 0,89 kr. pr. sms')</script>";
        $setup = "<script>alert('SMS network not setup on this server:\\n\\nGlobal settings: \\\"apikey\\\"');</script>";
        $printed = "<!DOCTYPE html>\n<html><head><title>login</title><script src=\"../javascript/jquery.js\"></script>"
            . "<script>window.SALDI_DIALOG_CFG = {\"csrf\":\"abc\"};</script></head><body bgcolor=\"#eeeef0\">" . $first . "Error:timeout" . $setup;
        $this->assertSame($first . "\n" . $setup, twofactor_notices($printed));
        $this->assertSame('', twofactor_notices('<html><head><script>alert(document.cookie)</script></head>'));
        $this->assertSame('', twofactor_notices(''));
        $this->assertSame('', twofactor_notices(false));
    }

    /**
     * On a LATIN9 ledger findtekst() gives its texts single-byte. send_sms() JSON-encodes the message and send_email()
     * sends UTF-8: json_encode() refused the text and htmlspecialchars(..., 'UTF-8') emptied it, so the text message
     * failed and the e-mail came without the code.
     */
    #[RunInSeparateProcess]
    public function testOnALatin9LedgerTheCodeReachesTheTextMessageAndTheEmail(): void
    {
        $GLOBALS['db_encode'] = 'LATIN9';
        $GLOBALS['__tfa_sent'] = [];
        eval('
            function findtekst($id, $languageId) {   // the fallback text as a LATIN9 ledger stores it
                $parts = explode("|", (string) $id, 2);
                return mb_convert_encoding($parts[1] ?? $parts[0], "ISO-8859-1", "UTF-8");
            }
            function send_sms($from, $to, $message) {
                $GLOBALS["__tfa_sent"][] = ["sms", json_encode(["message" => $message])];
                return true;
            }
            function send_email($to, $subject, $body) {
                $GLOBALS["__tfa_sent"][] = ["email", $subject, $body];
                return true;
            }
        ');

        $this->assertTrue(twofactor_send('sms', '+4512345678', '123456', 1));
        $this->assertTrue(twofactor_send('email', 'bruger@example.invalid', '654321', 1));

        [$sms, $mail] = $GLOBALS['__tfa_sent'];
        $this->assertNotFalse($sms[1], 'the text message could not be encoded');
        $this->assertSame('Din kode til Saldi er 123456. Den gælder i 3 minutter.', json_decode($sms[1], true)['message']);
        $this->assertSame('Din kode til Saldi', $mail[1]);
        $this->assertSame('<p>Din kode til Saldi er 654321. Den gælder i 3 minutter.</p><p>Har du ikke selv forsøgt at logge ind, så skift din adgangskode.</p>', $mail[2]);
    }

    public function testACodeIsSixDigits(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->assertMatchesRegularExpression('/^[1-9][0-9]{5}$/', twofactor_new_code());
        }
    }

    public function testTheStoredCodeComesBackAsItWasSaved(): void
    {
        $state = array('expire' => 1794660000, 'code' => '123456', 'method' => 'email', 'sent' => array('sms' => 1794659000, 'email' => 1794659820));
        $this->assertSame('1794660000|123456|email|1794659000|1794659820', twofactor_state_format($state));
        $this->assertSame($state, twofactor_state_parse(twofactor_state_format($state)));
    }

    public function testAnEmptyOrDamagedValueIsNoCode(): void
    {
        $none = array('expire' => 0, 'code' => '', 'method' => '', 'sent' => array('sms' => 0, 'email' => 0));
        $this->assertSame($none, twofactor_state_parse(''));
        $this->assertSame($none, twofactor_state_parse(null));
        $this->assertSame('', twofactor_state_parse("1794660000|12'; drop|pigeon|x|y")['code']);
        $this->assertSame('', twofactor_state_parse('1794660000|123456|pigeon')['method']);
    }

    public function testAUsedCodeIsStoredAsNoCodeButKeepsWhenItWasSent(): void
    {
        $state = twofactor_state_parse('1794660000|123456|sms|1794659820|0');
        $state['code'] = '';
        $state['expire'] = 0;
        $this->assertSame('0||sms|1794659820|0', twofactor_state_format($state));
    }
}
