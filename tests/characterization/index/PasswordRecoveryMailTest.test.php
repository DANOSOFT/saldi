<?php
// tests/characterization/index/PasswordRecoveryMailTest.test.php
//
// "Glemt adgangskode?" (index/glemt_kode.php) sends its mail through the SMTP
// server set in the regnskab's stamdata: adresser art 'S', felt_1 host[:port],
// felt_2 user, felt_3 password, felt_4 ssl/tls. Empty felt_1 is Saldi's own
// mail server on localhost.
//
// The page selected only the email column, so felt_1 was never set and
// every recovery mail went to localhost, which refused it (SMTP code 111):
// no recovery mail reached the lab's mail server on either leg (testlab
// round 1, login-sessions-recovery, DEVY-2 / DEVY-3).
//
// Pins: the recovery mail for a user without an employee address reaches the
// regnskab's SMTP server, logs in with felt_2/felt_3, is sent from the
// stamdata address (a server that takes only its own sender accepts it), goes
// to that address and carries the temporary code stored in brugere.tmp_kode;
// with felt_4 'tls' the page asks that server for STARTTLS. With felt_1 empty
// or 'localhost' the mail goes to Saldi's own relay on localhost:25 as before:
// anonymous, without STARTTLS and from kan_ikke_besvares@saldi.dk, whatever
// felt_2-felt_4 still hold, so a relay that offers no AUTH takes it.
//
// Requires the docker-compose stack - skips cleanly otherwise. The localhost
// case listens on port 25, which the stack's container (root) can.
//
// History:
// 20261105 CL/LH Created.

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/../../TestCredentials.php';

final class PasswordRecoveryMailTest extends CharacterizationTestCase
{
    private const COMPANY_MAIL = 'glemt-kode-chartest@test.invalid';
    private const SMTP_USER = 'glemt-kode-chartest';
    private const SALDI_SENDER = 'kan_ikke_besvares@saldi.dk';

    /** @var list<array<string, ?string>> The stamdata rows (adresser art 'S') before the test changed them; restored in tearDown(). */
    private array $company = [];

    /** @var list<int> brugere rows this test created; tearDown() deletes them. */
    private array $users = [];

    /** @var resource|null The running smtp_sink.php process. */
    private $sink = null;

    /** @var array<int, resource> Its stdin/stdout pipes. */
    private array $sinkPipes = [];

    private string $sinkFile = '';

    protected function setUp(): void
    {
        $this->company = self::rows("SELECT id, email, felt_1, felt_2, felt_3, felt_4 FROM adresser WHERE art = 'S' ORDER BY id");
        $this->assertNotSame([], $this->company, 'the template tenant has its own company row (adresser art S)');
    }

    protected function tearDown(): void
    {
        $this->stopSink();
        foreach ($this->company as $row) {
            self::rows(
                'UPDATE adresser SET email = $1, felt_1 = $2, felt_2 = $3, felt_3 = $4, felt_4 = $5 WHERE id = $6',
                [$row['email'], $row['felt_1'], $row['felt_2'], $row['felt_3'], $row['felt_4'], $row['id']]
            );
        }
        foreach ($this->users as $id) {
            self::rows('DELETE FROM brugere WHERE id = $1', [$id]);
        }
    }

    /**
     * Start smtp_sink.php and return the port it listens on, or null when it
     * could not listen there (a fixed port that is taken or needs root).
     *
     * @param list<string> $options smtp_sink.php options: --port=<n>, --no-auth, --sender=<addr>
     */
    private function startSink(array $options = []): ?int
    {
        $this->sinkFile = (string)tempnam(sys_get_temp_dir(), 'chartest-smtp-');
        $this->sink = proc_open(
            array_merge([PHP_BINARY, __DIR__ . '/../support/smtp_sink.php', $this->sinkFile, '60'], $options),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'a']],
            $this->sinkPipes
        );
        $this->assertIsResource($this->sink, 'the SMTP sink started');
        $line = (string)fgets($this->sinkPipes[1]);
        if (!preg_match('/^PORT (\d+)$/', trim($line), $m)) {
            $this->stopSink();
            @unlink($this->sinkFile);
            return null;
        }
        return (int)$m[1];
    }

    /**
     * What the sink was told. The page has finished, so a client that
     * connected has gone again and the sink is writing its result; a sink
     * still waiting after a few seconds never got a connection.
     *
     * @return array{connected: bool, commands: list<string>, auth: ?array{0: string, 1: string}, data: string}
     */
    private function sinkResult(): array
    {
        for ($i = 0; $i < 50 && proc_get_status($this->sink)['running']; $i++) {
            usleep(100000);
        }
        $this->stopSink();
        $json = json_decode((string)file_get_contents($this->sinkFile), true);
        @unlink($this->sinkFile);
        return is_array($json) ? $json : ['connected' => false, 'commands' => [], 'auth' => null, 'data' => ''];
    }

    private function stopSink(): void
    {
        if ($this->sink === null) {
            return;
        }
        if (proc_get_status($this->sink)['running']) {
            proc_terminate($this->sink);
        }
        foreach ($this->sinkPipes as $pipe) {
            fclose($pipe);
        }
        proc_close($this->sink);
        $this->sink = null;
        $this->sinkPipes = [];
    }

    /** The stamdata SMTP settings, the way Indstillinger > E-mail saves them, with a login in felt_2 / felt_3. */
    private function setCompanySmtp(string $server, string $encryption): string
    {
        $password = TestCredentials::password('glemt_kode_smtp');
        self::rows(
            "UPDATE adresser SET email = $1, felt_1 = $2, felt_2 = $3, felt_3 = $4, felt_4 = $5 WHERE art = 'S'",
            [self::COMPANY_MAIL, $server, self::SMTP_USER, $password, $encryption]
        );
        return $password;
    }

    /** A user with no employee (ansat_id), so the mail goes to the stamdata address. */
    private function user(): string
    {
        $id = CharacterizationEnv::nextId(self::$tenant, 'brugere');
        $name = "glemt-chartest-$id";
        self::rows('INSERT INTO brugere (id, brugernavn, rettigheder) VALUES ($1, $2, $3)', [$id, $name, '1']);
        $this->users[] = $id;
        return $name;
    }

    /** Fill in Regnskab and Brugernavn on the recovery page and press Send. */
    private function sendRecovery(string $user): string
    {
        $body = http_build_query(['regnskab' => 'chartest', 'navn' => $user, 'send' => 'Send']);
        $out = $this->runChildJson('run_glemt_kode_page.php', [$body]);
        return (string)($out['html'] ?? '');
    }

    public function test_recovery_mail_goes_through_the_regnskabs_smtp_server_from_its_own_address(): void
    {
        $port = $this->startSink(['--sender=' . self::COMPANY_MAIL]);
        $this->assertNotNull($port, 'the SMTP sink listens');
        $password = $this->setCompanySmtp("127.0.0.1:$port", '');
        $user = $this->user();

        $html = $this->sendRecovery($user);
        $mail = $this->sinkResult();

        $this->assertTrue($mail['connected'], "the page connected to the SMTP server in felt_1.\npage: " . strip_tags($html));
        $this->assertSame([self::SMTP_USER, $password], $mail['auth'], 'it logged in with felt_2 / felt_3');
        $this->assertContains('MAIL FROM:<' . self::COMPANY_MAIL . '>', $mail['commands'], "it sends from the stamdata address, the only sender this server takes.\npage: " . strip_tags($html));
        $this->assertContains('RCPT TO:<' . self::COMPANY_MAIL . '>', $mail['commands'], 'the mail goes to the stamdata address');
        $this->assertNotSame('', $mail['data'], 'the message was sent');
        $this->assertStringContainsString('Subject: Midlertidig adgangskode til Saldi', $mail['data']);

        $tmpKode = (string)self::one('SELECT tmp_kode FROM brugere WHERE brugernavn = $1', [$user])['tmp_kode'];
        $this->assertSame(1, preg_match('/^reset\|\d+\|(\w+)$/', $tmpKode, $m), "a temporary code is stored: $tmpKode");
        $this->assertTrue(str_contains(quoted_printable_decode($mail['data']), $m[1]), 'the mail carries the stored temporary code');
        $this->assertTrue(str_contains($html, 'Mail sendt til ' . self::COMPANY_MAIL), 'the page reports the mail as sent');
        $this->assertFalse(str_contains($html, 'Fejl i afsendelse'), 'and no send error');
    }

    public function test_recovery_mail_asks_for_tls_when_felt_4_says_so(): void
    {
        $port = $this->startSink();
        $this->assertNotNull($port, 'the SMTP sink listens');
        $this->setCompanySmtp("127.0.0.1:$port", 'tls');

        $this->sendRecovery($this->user());
        $mail = $this->sinkResult();

        $this->assertTrue($mail['connected'], 'the page connected to the SMTP server in felt_1');
        $this->assertContains('STARTTLS', $mail['commands'], 'felt_4 tls makes the page ask for STARTTLS');
    }

    /** @return array<string, array{0: string}> felt_1 values that mean Saldi's own mail server */
    public static function saldisOwnServer(): array
    {
        return ['felt_1 empty' => [''], 'felt_1 localhost' => ['localhost']];
    }

    #[DataProvider('saldisOwnServer')]
    public function test_saldis_own_relay_gets_anonymous_mail_whatever_felt_2_to_4_hold(string $server): void
    {
        $port = $this->startSink(['--port=25', '--no-auth']);
        if ($port === null) {
            $this->markTestSkipped('port 25 on 127.0.0.1, where PHPMailer reaches "localhost", is taken or needs root');
        }
        $this->setCompanySmtp($server, 'tls');

        $html = $this->sendRecovery($this->user());
        $mail = $this->sinkResult();

        $this->assertTrue($mail['connected'], "the page connected to the relay on localhost.\npage: " . strip_tags($html));
        $this->assertSame([], preg_grep('/^AUTH\b/i', $mail['commands']), 'it does not log in with the felt_2 / felt_3 left in stamdata');
        $this->assertNotContains('STARTTLS', $mail['commands'], 'nor ask for the encryption left in felt_4');
        $this->assertContains('MAIL FROM:<' . self::SALDI_SENDER . '>', $mail['commands'], "it sends from Saldi's own sender, as before.\npage: " . strip_tags($html));
        $this->assertContains('RCPT TO:<' . self::COMPANY_MAIL . '>', $mail['commands'], 'the mail goes to the stamdata address');
        $this->assertNotSame('', $mail['data'], 'the message was sent');
        $this->assertTrue(str_contains($html, 'Mail sendt til ' . self::COMPANY_MAIL), 'the page reports the mail as sent');
        $this->assertFalse(str_contains($html, 'Fejl i afsendelse'), 'and no send error');
    }
}
