<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/loginThrottle.php';

/**
 * The messages of includes/loginThrottle.php travel to the login page in the form field fejltxt. sanitize_input()
 * in index/login.php drops every character outside its whitelist and refuses more than 80 characters, so a message
 * that breaks either rule would arrive cut or not at all.
 */
final class loginThrottle extends TestCase
{
    /** @return array<int, string> */
    private function everyMessage(): array
    {
        $messages = array();
        foreach (array(1, 2, 3, 0, 'x') as $language) {
            foreach (array(-1, 0, 1, 2, 3) as $left) {
                $messages[] = login_throttle_message('wrong', $language, $left);
            }
            $messages[] = login_throttle_message('paused', $language, 1794660000);
            $messages[] = login_throttle_message('tfa_nocontact', $language);
        }
        return $messages;
    }

    public function testEveryMessageFitsTheFormField(): void
    {
        foreach ($this->everyMessage() as $message) {
            $this->assertNotSame('', $message);
            $this->assertLessThanOrEqual(80, mb_strlen($message, 'UTF-8'), $message);
        }
    }

    public function testEveryMessageSurvivesTheWhitelistOfTheLoginForm(): void
    {
        foreach ($this->everyMessage() as $message) {
            // The whitelist of sanitize_input() in index/login.php.
            $this->assertSame($message, preg_replace('/[^\p{L}\p{M}\p{N}\p{Sc} @._+\-!#%()*,:=?\[\]^{|}~`\/]/u', '', $message));
        }
    }

    public function testTheNumberOfAttemptsLeftIsShownFromTwoAndDown(): void
    {
        $this->assertSame('Fejl i brugernavn eller adgangskode', login_throttle_message('wrong', 1, 4));
        $this->assertSame('Fejl i brugernavn eller adgangskode', login_throttle_message('wrong', 1, 3));
        $this->assertSame('Fejl i brugernavn eller adgangskode. 2 forsøg tilbage.', login_throttle_message('wrong', 1, 2));
        $this->assertSame('Fejl i brugernavn eller adgangskode. 1 forsøg tilbage.', login_throttle_message('wrong', 1, 1));
        $this->assertSame('Wrong user name or password. 2 attempts left.', login_throttle_message('wrong', 2, 2));
    }

    public function testAnUnknownLanguageGetsDanish(): void
    {
        $this->assertSame(login_throttle_message('tfa_nocontact', 1), login_throttle_message('tfa_nocontact', 7));
    }

    public function testAUserIsTheSameWhateverTheCase(): void
    {
        $this->assertSame(login_throttle_user_key('Saldi Demo', 'Åse'), login_throttle_user_key(' saldi demo ', 'åse'));
        $this->assertNotSame(login_throttle_user_key('demo', 'ole'), login_throttle_user_key('demo', 'ola'));
        $this->assertNotSame(login_throttle_user_key('demo', 'ole'), login_throttle_user_key('demo2', 'ole'));
    }

    public function testNoKeyNamesTheUserOrTheAddress(): void
    {
        $this->assertMatchesRegularExpression('/^u:[0-9a-f]{40}$/', login_throttle_user_key('demo', 'ole'));
        $this->assertMatchesRegularExpression('/^i:[0-9a-f]{40}$/', login_throttle_ip_key('192.0.2.7'));
    }
}
