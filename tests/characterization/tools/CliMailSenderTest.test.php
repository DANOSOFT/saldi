<?php
// tests/characterization/tools/CliMailSenderTest.test.php
//
// The CLI jobs' mails get the sender a web request gives them (review round 1, I6). Through this server's own
// mailer (no mail server under Indstillinger > E-mail), a web request on a saldi.dk server sends from
// <ledger>@<server name>, which the server may send for, and not from the company's own address. A cron job has
// no SERVER_NAME, so tools/reminder_due.php and tools/stripe_charge_due.php sent from the company's address
// (envelope included for stripe_mail_send()), which the receiver's SPF check refuses. Both jobs now call
// cli_server_name() once connected to the ledger: the server name is their --host, or else the host of the
// ledger's Stripe public_base_url.
//
// Each case runs support/run_cli_mail.php in a child process as the jobs start, with a sendmail_path that keeps
// the message (support/fake_sendmail.php), and reads the From header and the envelope sender (-f) from it.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261126 CL/ASR Created.
// 20261127 CL/ASR Fictive server names in the test data.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class CliMailSenderTest extends CharacterizationTestCase
{
    private const COMPANY = 'firma@example.com';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        if (!self::one("SELECT id FROM adresser WHERE art = 'S'")) {
            self::rows("INSERT INTO adresser (art, firmanavn, email) VALUES ('S', 'Firma ApS', $1)", [self::COMPANY]);
        }
        self::rows("UPDATE adresser SET firmanavn = 'Firma ApS', email = $1, felt_1 = '', felt_2 = '', felt_3 = '', felt_4 = '' WHERE art = 'S'", [self::COMPANY]);
        self::rows("DELETE FROM settings WHERE var_grp = 'email_settings' AND var_name IN ('sender_email', 'sender_name')");
    }

    protected function setUp(): void
    {
        self::publicBaseUrl('https://cli-base.saldi.dk/saldi');
    }

    public function testStripeMailFromAJobGetsTheServerSenderFromThePublicBaseUrl(): void
    {
        $ledger = CharacterizationEnv::testDb();
        $mail = $this->send('stripe', '');
        self::assertSame('cli-base.saldi.dk', $mail['server']);
        self::assertSame("$ledger@cli-base.saldi.dk", $mail['from']);
        self::assertSame("$ledger@cli-base.saldi.dk", $mail['envelope'], 'the envelope sender is the From address (#1167)');
    }

    public function testTheHostOptionWinsOverThePublicBaseUrl(): void
    {
        $ledger = CharacterizationEnv::testDb();
        $mail = $this->send('stripe', 'SRV2.saldi.dk');
        self::assertSame('srv2.saldi.dk', $mail['server']);
        self::assertSame("$ledger@srv2.saldi.dk", $mail['from']);
    }

    public function testClassicMailFromAJobGetsTheServerSender(): void
    {
        $ledger = CharacterizationEnv::testDb();
        $mail = $this->send('classic', '');
        self::assertSame("$ledger@cli-base.saldi.dk", $mail['from']);
        $mail = $this->send('classic', 'srv2.saldi.dk');
        self::assertSame("$ledger@srv2.saldi.dk", $mail['from']);
    }

    public function testWithoutAServerNameOrOffSaldiTheCompanyAddressStays(): void
    {
        self::publicBaseUrl(null);
        foreach (['stripe', 'classic'] as $kind) {
            $mail = $this->send($kind, '');
            self::assertSame('', $mail['server'], $kind);
            self::assertSame(self::COMPANY, $mail['from'], "$kind: no --host and no public_base_url");
        }
        $mail = $this->send('stripe', 'mail.example.org');
        self::assertSame('mail.example.org', $mail['server']);
        self::assertSame(self::COMPANY, $mail['from'], 'a server outside saldi.dk sends as before');
    }

    public function testBothJobsSetTheServerNameOnceConnectedAndRefuseAnInvalidHost(): void
    {
        foreach (['reminder_due.php', 'stripe_charge_due.php'] as $tool) {
            $source = (string)file_get_contents(CharacterizationEnv::repoRoot() . '/tools/' . $tool);
            self::assertStringContainsString("stripe_settings_flush();\ncli_server_name(\$host);", $source, $tool);
            $res = CharacterizationEnv::runChild(CharacterizationEnv::repoRoot() . '/tools/' . $tool, ['--db=' . CharacterizationEnv::testDb(), '--host=not a host', '--dry-run']);
            self::assertSame(1, $res['exit'], $tool);
            self::assertStringContainsString('host', $res['stderr'], $tool);
        }
        self::assertStringContainsString("' --host=' . escapeshellarg(\$host)", (string)file_get_contents(CharacterizationEnv::repoRoot() . '/tools/stripe_charge_due.php'),
            '--all passes --host on to each ledger');
    }

    private static function publicBaseUrl(?string $url): void
    {
        self::rows("DELETE FROM settings WHERE var_grp = 'stripe' AND var_name = 'public_base_url'");
        if ($url !== null) {
            self::rows("INSERT INTO settings (var_grp, var_name, var_value, user_id, group_id) VALUES ('stripe', 'public_base_url', $1, NULL, 0)", [$url]);
        }
    }

    /**
     * Send one mail from a child process started as the jobs start.
     *
     * @return array{server: string, from: string, envelope: string} What cli_server_name() set, the From address and the -f sender.
     */
    private function send(string $kind, string $host): array
    {
        $capture = (string)tempnam(sys_get_temp_dir(), 'saldi-cli-mail-');
        try {
            $sendmail = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../support/fake_sendmail.php') . ' ' . escapeshellarg($capture);
            $err = tmpfile();
            $proc = proc_open([PHP_BINARY, '-d', 'sendmail_path=' . $sendmail, __DIR__ . '/../support/run_cli_mail.php', CharacterizationEnv::testDb(), $kind, $host],
                [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => $err], $pipes);
            self::assertIsResource($proc);
            $out = (string)stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $exit = proc_close($proc);
            rewind($err);
            $stderr = (string)stream_get_contents($err);
            fclose($err);
            self::assertSame(0, $exit, "$kind: $out $stderr");
            self::assertMatchesRegularExpression('/^SENT (true|\'Mail sent to kunde@example\.com\')$/m', $out, "$kind: $out $stderr");
            $lines = array_values(array_filter(explode("\n", (string)file_get_contents($capture))));
            self::assertCount(1, $lines, "$kind: one message reached the mail program");
            $sent = json_decode($lines[0], true);
            self::assertMatchesRegularExpression('/^From: .*$/m', $sent['message']);
            preg_match('/^From: (?:.*<)?([^<>\s]+@[^<>\s]+?)>?\s*$/m', $sent['message'], $from);
            $envelope = '';
            foreach ($sent['args'] as $arg) {
                if (str_starts_with($arg, '-f')) {
                    $envelope = substr($arg, 2);
                }
            }
            preg_match('/^SERVER (.*)$/m', $out, $server);
            return ['server' => trim($server[1] ?? ''), 'from' => $from[1] ?? '', 'envelope' => $envelope];
        } finally {
            @unlink($capture);
        }
    }
}
