<?php
// 20261126 CL/ASR Created (review round 1, I6): server_mail_host(), server_mail_from() and cli_server_name() of includes/stdFunc/serverMailSender.php.
// 20261127 CL/ASR Fictive server names in the test data.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/stdFunc/serverMailSender.php';

/**
 * The sender of mails through this server's own mailer: <ledger>@<server> on a saldi.dk server, the company's
 * address elsewhere. No database or mail: tests/characterization/tools/CliMailSenderTest.test.php sends the CLI
 * jobs' mails through PHP's mail() and reads the sender there.
 */
final class serverMailSender extends TestCase
{
    private array $server;
    private array $globals;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->globals = array_key_exists('db', $GLOBALS) ? ['db' => $GLOBALS['db']] : [];
        $GLOBALS['db'] = 'kunde_42';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        unset($GLOBALS['db']);
        if ($this->globals) {
            $GLOBALS['db'] = $this->globals['db'];
        }
    }

    public function testServerMailHostAcceptsOnlyHostNames(): void
    {
        $this->assertSame('ssl3.saldi.dk', server_mail_host(' SSL3.Saldi.DK '));
        $this->assertSame('mail.example.org', server_mail_host('mail.example.org'));
        foreach (['', 'localhost', 'ssl3.saldi.dk:443', 'a b.saldi.dk', 'x_y.saldi.dk', '-a.saldi.dk', 'ssl3.saldi.dk/path', null, false] as $bad) {
            $this->assertSame('', server_mail_host($bad), var_export($bad, true));
        }
    }

    public function testServerMailFromUsesTheLedgerOnASaldiServerOnly(): void
    {
        $_SERVER['SERVER_NAME'] = 'ssl3.saldi.dk';
        $this->assertSame('kunde_42@ssl3.saldi.dk', server_mail_from('firma@example.com'));
        $_SERVER['SERVER_NAME'] = 'saldi.example.org';
        $this->assertSame('firma@example.com', server_mail_from('firma@example.com'), 'a server outside saldi.dk');
        unset($_SERVER['SERVER_NAME']);
        $this->assertSame('firma@example.com', server_mail_from('firma@example.com'), 'no server name (a CLI job before cli_server_name())');
        $_SERVER['SERVER_NAME'] = 'ssl3.saldi.dk';
        $GLOBALS['db'] = '';
        $this->assertSame('firma@example.com', server_mail_from('firma@example.com'), 'no ledger');
    }

    public function testCliServerNameSetsTheServerNameFromTheHostOption(): void
    {
        unset($_SERVER['SERVER_NAME'], $_SERVER['HTTP_HOST']);
        $this->assertSame('app2.saldi.dk', cli_server_name('APP2.saldi.dk'));
        $this->assertSame('app2.saldi.dk', $_SERVER['SERVER_NAME']);
        $this->assertSame('app2.saldi.dk', $_SERVER['HTTP_HOST']);
        $this->assertSame('kunde_42@app2.saldi.dk', server_mail_from('firma@example.com'));
    }
}
