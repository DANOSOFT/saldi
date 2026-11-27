<?php
// tests/characterization/index/TwoFactorLoginSessionTest.test.php
//
// Two-factor login over HTTP: the password alone must not leave a session that
// other pages accept. Every page checks the session against the master table
// `online` (includes/online.php), so the tests look at that row and at a
// protected page (index/main.php) requested with the same session cookie.
//
// The stored code uses the old "<code>|<expire>" layout, which index/login.php
// still reads, so no SMS or e-mail is sent while a code is valid. An expired
// code makes the page send a new one by e-mail, which either fails or lands in
// the stack's local mail catcher; both end on the code step.
//
// History:
// 20261127 CL/LH Created for the two-factor session fix.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once dirname(__DIR__, 2) . '/TestCredentials.php';

final class TwoFactorLoginSessionTest extends CharacterizationTestCase
{
	private const CODE = '482913';
	private const CODE_FORM = 'Tofaktorgodkendelse';
	private const EXPIRED = 'URL=../index/logud.php';

	private static string $baseUrl;
	private static string $account;
	private static string $password;
	/** @var resource|\PgSql\Connection */
	private static $master;
	/** @var list<string> */
	private static array $jars = [];

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		self::$baseUrl = rtrim(getenv('SALDI_CHAR_BASE_URL') ?: 'http://localhost/saldi', '/');
		self::$account = 'test_' . CharacterizationEnv::testDb();
		self::$password = TestCredentials::password('twofactor_login');
		self::$master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
		$probe = self::http('GET', '/index/index.php', [], null);
		if ($probe['status'] === 0) {
			self::markTestSkipped('Saldi web server not reachable at ' . self::$baseUrl);
		}
		self::warmUp();
	}

	/**
	 * A new company gets its audit log (includes/betweenUpdates.php) during its first login and the page after it,
	 * so one ordinary login runs before the tests that count audit rows.
	 */
	private static function warmUp(): void
	{
		$name = 'tfawarm' . bin2hex(random_bytes(3));
		self::rows(
			'INSERT INTO brugere (id, brugernavn, kode, rettigheder, regnskabsaar, twofactor) VALUES ($1, $2, $3, $4, $5, $6)',
			[CharacterizationEnv::nextId(self::$tenant, 'brugere'), $name, md5(self::$password), str_repeat('1', 30), self::$regnaar, 'f']
		);
		$jar = tempnam(sys_get_temp_dir(), 'tfa-jar-');
		self::$jars[] = $jar;
		self::http('POST', '/index/login.php', ['regnskab' => self::$account, 'brugernavn' => $name, 'password' => self::$password], $jar);
		self::http('GET', '/index/main.php', [], $jar);
	}

	public static function tearDownAfterClass(): void
	{
		if (isset(self::$master) && self::$master) {
			CharacterizationEnv::rows(self::$master, 'DELETE FROM online WHERE db = $1', [CharacterizationEnv::testDb()]);
			pg_close(self::$master);
			self::$master = null;
		}
		foreach (self::$jars as $jar) {
			@unlink($jar);
		}
		self::$jars = [];
		parent::tearDownAfterClass();
	}

	public function testPasswordAloneDoesNotOpenASession(): void
	{
		$user = $this->createUser(true, self::CODE . '|' . (time() + 170));
		$jar = $this->newSession();

		$login = self::http('POST', '/index/login.php', $this->loginFields($user), $jar);

		$this->assertStringContainsString(self::CODE_FORM, $login['body'], 'the code form is shown');
		$this->assertNull($this->onlineRow($jar), 'no online row before the code is entered');
		$this->assertSame([], $this->onlineRowsOf($user), 'no row for the user is left under any key');
		$this->assertSessionRefused($jar);
		$this->assertAuditCount(0, 'login.success', $user);
	}

	public function testCorrectCodeLogsIn(): void
	{
		$user = $this->createUser(true, self::CODE . '|' . (time() + 170));
		$jar = $this->newSession();

		$first = self::http('POST', '/index/login.php', $this->loginFields($user), $jar);
		$this->assertStringContainsString(self::CODE_FORM, $first['body'], 'the password step asks for the code');
		$second = self::http('POST', '/index/login.php', $this->loginFields($user, self::CODE), $jar);

		$this->assertStringNotContainsString(self::CODE_FORM, $second['body'], 'the code is accepted');
		$row = $this->onlineRow($jar);
		$this->assertNotNull($row, 'the correct code opens the session');
		$this->assertSame($user, $row['brugernavn']);
		$this->assertSessionAccepted($jar);
		$this->assertAuditCount(1, 'login.success', $user);
	}

	public function testWrongCodeIsRefusedAndLogged(): void
	{
		$user = $this->createUser(true, self::CODE . '|' . (time() + 170));
		$jar = $this->newSession();

		$login = self::http('POST', '/index/login.php', $this->loginFields($user, '000000'), $jar);

		$this->assertStringContainsString('Ikke en valid kode', $login['body']);
		$this->assertNull($this->onlineRow($jar), 'a wrong code leaves no online row');
		$this->assertSessionRefused($jar);
		$this->assertAuditCount(1, 'login.2fa_failed', $user);
		$this->assertAuditCount(0, 'login.success', $user);
	}

	public function testExpiredCodeIsRefused(): void
	{
		$user = $this->createUser(true, self::CODE . '|' . (time() - 10));
		$jar = $this->newSession();

		$login = self::http('POST', '/index/login.php', $this->loginFields($user, self::CODE), $jar);

		$this->assertMatchesRegularExpression(
			'/' . self::CODE_FORM . '|afsendelse af email/',
			$login['body'],
			'the expired code leads to a new code (or a failed send), not into the app'
		);
		$this->assertNull($this->onlineRow($jar), 'an expired code leaves no online row');
		$this->assertSessionRefused($jar);
	}

	public function testSmsStepStillReachesTheCompany(): void
	{
		// send_sms() includes online.php and finds the company through the login's (pending) row. The test stack
		// has no SMS key, so the send stops after that lookup with its own message, not with "session expired".
		$user = $this->createUser(true, self::CODE . '|' . (time() - 10), false, '4512345678');
		$jar = $this->newSession();

		$login = self::http('POST', '/index/login.php', $this->loginFields($user), $jar);

		$this->assertStringContainsString('SMS network not setup', $login['body'], 'send_sms() ran in the company');
		$this->assertStringNotContainsString(self::EXPIRED, $login['body'], 'send_sms() found the login row');
		$this->assertNull($this->onlineRow($jar), 'no session after the SMS step');
		$this->assertSame([], $this->onlineRowsOf($user), 'no row for the user is left under any key');
		$this->assertSessionRefused($jar);
	}

	public function testTwoFactorUserWithoutPhoneOrEmailIsRefused(): void
	{
		$user = $this->createUser(true, null, false);
		$jar = $this->newSession();

		$login = self::http('POST', '/index/login.php', $this->loginFields($user), $jar);

		$this->assertStringContainsString('hverken telefonnummer eller e-mail', $login['body']);
		$this->assertNull($this->onlineRow($jar), 'no code can be sent, so no session');
		$this->assertSessionRefused($jar);
	}

	public function testUserWithoutTwoFactorIsUnaffected(): void
	{
		$user = $this->createUser(false, null);
		$jar = $this->newSession();

		$login = self::http('POST', '/index/login.php', $this->loginFields($user), $jar);

		$this->assertStringNotContainsString(self::CODE_FORM, $login['body']);
		$this->assertNotNull($this->onlineRow($jar), 'the password logs in a user without two-factor');
		$this->assertSessionAccepted($jar);
		$this->assertAuditCount(1, 'login.success', $user);
	}

	public function testLegacyLoginPageOpensNoSession(): void
	{
		$user = $this->createUser(true, self::CODE . '|' . (time() + 170));
		$jar = $this->newSession();

		// includes/login.php posted the user name as "login" and had no code step.
		$legacy = self::http('POST', '/includes/login.php', [
			'regnskab' => self::$account, 'login' => $user, 'password' => self::$password,
		], $jar);

		$this->assertSame(302, $legacy['status'], 'the legacy page sends the browser to the login page');
		$this->assertStringEndsWith('/index/index.php', $legacy['location']);
		$this->assertNull($this->onlineRow($jar, false), 'the legacy login page opens no session');
		$this->assertSessionRefused($jar);
	}

	private function createUser(bool $twoFactor, ?string $tmpCode, bool $withEmail = true, string $phone = ''): string
	{
		$name = 'tfa' . bin2hex(random_bytes(4));
		$id = CharacterizationEnv::nextId(self::$tenant, 'brugere');
		self::rows(
			'INSERT INTO brugere (id, brugernavn, kode, rettigheder, regnskabsaar, email, twofactor, tmp_kode, tlf)
			VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)',
			[
				$id, $name, md5(self::$password), str_repeat('1', 30), self::$regnaar,
				$withEmail ? $name . '@example.invalid' : '', $twoFactor ? 't' : 'f', $tmpCode, $phone,
			]
		);
		return $name;
	}

	/** @return array<string, string> */
	private function loginFields(string $user, ?string $code = null): array
	{
		$fields = ['regnskab' => self::$account, 'brugernavn' => $user, 'password' => self::$password];
		if ($code !== null) {
			foreach (str_split($code) as $i => $digit) {
				$fields['code_' . ($i + 1)] = $digit;
			}
		}
		return $fields;
	}

	/** @return string Cookie jar of a new browser session; PHP sets the session cookie on the first request. */
	private function newSession(): string
	{
		$jar = tempnam(sys_get_temp_dir(), 'tfa-jar-');
		self::$jars[] = $jar;
		return $jar;
	}

	/** @return array<string, string>|null Online row of the jar's session; null also when no session cookie was set and that is allowed. */
	private function onlineRow(string $jar, bool $cookieRequired = true): ?array
	{
		$sessionId = '';
		foreach (file($jar, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
			$parts = explode("\t", $line);
			if (count($parts) === 7 && $parts[5] === 'PHPSESSID') {
				$sessionId = $parts[6];
			}
		}
		if ($sessionId === '' && !$cookieRequired) {
			return null;
		}
		$this->assertNotSame('', $sessionId, 'the login page set a session cookie');
		return CharacterizationEnv::one(self::$master, 'SELECT * FROM online WHERE session_id = $1', [$sessionId]);
	}

	/** @return list<array<string, string>> Every online row of the user, whatever its session key. */
	private function onlineRowsOf(string $user): array
	{
		return CharacterizationEnv::rows(self::$master, 'SELECT * FROM online WHERE brugernavn = $1', [$user]);
	}

	/**
	 * Audit rows for the user, when this checkout writes that action (the live branch has no login audit yet).
	 */
	private function assertAuditCount(int $expected, string $action, string $user): void
	{
		$source = (string)file_get_contents(CharacterizationEnv::repoRoot() . '/index/login.php');
		if (!str_contains($source, "'$action'")) {
			return;
		}
		$table = self::one("SELECT to_regclass('audit_log') IS NOT NULL AS present");
		$this->assertSame('t', $table['present'], 'the test company has an audit log');
		$row = self::one('SELECT count(*) AS n FROM audit_log WHERE handling = $1 AND brugernavn = $2', [$action, $user]);
		$this->assertSame((string)$expected, (string)$row['n'], "$action rows for the user");
	}

	private function assertSessionRefused(string $jar): void
	{
		$page = self::http('GET', '/index/main.php', [], $jar);
		$this->assertStringContainsString(self::EXPIRED, $page['body'], 'a protected page refuses the session');
	}

	private function assertSessionAccepted(string $jar): void
	{
		$page = self::http('GET', '/index/main.php', [], $jar);
		$this->assertSame(200, $page['status']);
		$this->assertStringNotContainsString(self::EXPIRED, $page['body'], 'a protected page accepts the session');
		$this->assertStringContainsString('<title>Sidebar</title>', $page['body'], 'the app page itself is served');
	}

	/**
	 * @param array<string, string> $fields
	 * @return array{status: int, body: string, location: string}
	 */
	private static function http(string $method, string $path, array $fields, ?string $jar): array
	{
		$ch = curl_init(self::$baseUrl . $path);
		$options = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_CONNECTTIMEOUT => 3,
		];
		if ($jar !== null) {
			$options[CURLOPT_COOKIEFILE] = $jar;
			$options[CURLOPT_COOKIEJAR] = $jar;
		}
		if ($method === 'POST') {
			$options[CURLOPT_POST] = true;
			$options[CURLOPT_POSTFIELDS] = http_build_query($fields);
		}
		curl_setopt_array($ch, $options);
		$body = curl_exec($ch);
		$status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$location = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
		curl_close($ch);
		return ['status' => $status, 'body' => is_string($body) ? $body : '', 'location' => $location];
	}
}
