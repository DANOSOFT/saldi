<?php
// 20261127 ASR Created. Every value includes/tmpCode.php writes to brugere.tmp_kode, read the way each installation that shares the database reads it.
// 20261127 ASR Every written value goes through the two-factor step too; a temporary password starts with '0'; a new password clears the field.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/tmpCode.php';

/**
 * brugere.tmp_kode is read by every installation that opens the ledger. The lines copied below read the field as the
 * password login (index/login.php and includes/login.php), the API login (restapi/endpoints/v1/auth/login.php) and the
 * two-factor step (index/login.php, "<code>|<expire>") of the version that shares the database. Each value this code
 * can write, and each value tmp_code_storage_form() rewrites an earlier one to, goes through them: a temporary password
 * logs in with its own code only and only until its expiry, every other value logs in with nothing, and the two-factor
 * step sends a new code for every value.
 */
final class tmpCodeSharedReaders extends TestCase
{
	/** The password login, as index/login.php and includes/login.php read the field. Returns true when it logs in. */
	private static function readerLogin(?string $stored, string $password, int $now): bool
	{
		$r = array('tmp_kode' => $stored);
		$loggedIn = false;
		if (isset($r['tmp_kode'])) {
			$parts = explode("|", $r['tmp_kode']);
			if (count($parts) < 2) {
				return false; // list() would leave $tmp_kode NULL with a warning; NULL == a non-empty password is false
			}
			list($tidspkt, $tmp_kode) = $parts;
			if ((string) $now <= $tidspkt) { // date("U") is a string
				if ($tmp_kode == $password) {
					$loggedIn = true;
				}
			}
		}
		return $loggedIn;
	}

	/** The API login, as restapi/endpoints/v1/auth/login.php reads the field. */
	private static function readerApiLogin(?string $stored, string $password, int $now): bool
	{
		$user = array('tmp_kode' => $stored);
		if (isset($user['tmp_kode'])) {
			$parts = explode("|", $user['tmp_kode']);
			if (count($parts) < 2) {
				return false;
			}
			list($tidspkt, $tmp_kode) = $parts;
			if ((string) $now <= $tidspkt && $tmp_kode == $password) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The two-factor step, as index/login.php reads the field ("<code>|<expire>"): 'success' when the code passes,
	 * 'send' when the step sends a new code (and writes it over the field), 'ask' when it asks for the code it sent.
	 */
	private static function readerTwoFactor(?string $stored, string $code, int $now): string
	{
		$r = array('tmp_kode' => $stored);
		$real_code = null;
		$real_expire = 0;
		if (!empty($r["tmp_kode"])) {
			$tmp_kode_parts = explode("|", $r["tmp_kode"]);
			if (count($tmp_kode_parts) >= 2) {
				$real_code = $tmp_kode_parts[0];
				$real_expire = $tmp_kode_parts[1];
			}
		}
		$status = null;
		if ($code && $real_expire && $now <= $real_expire) {
			if ($real_code == $code) {
				$status = "success";
			} else {
				$status = "Ikke en valid kode, prøv igen";
			}
		}
		if ($status === "success") {
			return 'success';
		}
		if (!$real_expire || $now > $real_expire) {
			return 'send';
		}
		return 'ask';
	}

	/**
	 * Passwords and codes to try against a stored value: the real one, numbers near the expiry, the parts of the value,
	 * and strings that equal small numbers when compared with ==.
	 *
	 * @return array<int, string>
	 */
	private static function candidates(string $stored, int $expire, string $code): array
	{
		$list = array($code, (string) $expire, (string) ($expire - 1), (string) ($expire + 1), $expire . '.0', '+' . $expire, ' ' . $expire,
			'0', '00', '0.0', '-0', '0e1', ' 0', '', ' ', 'reset', 'invite', '2fa', $stored, strtoupper($code), $code . ' ', ' ' . $code,
			'O' . substr($code, 1));
		foreach (explode('|', $stored) as $part) {
			$list[] = $part;
		}
		return array_values(array_unique($list));
	}

	/**
	 * Every kind of value the code writes, with the password that may log in (null: none may).
	 *
	 * @return array<int, array{0: string, 1: int, 2: string, 3: string|null}> stored, expire, code, password that logs in
	 */
	private static function writtenValues(int $now): array
	{
		$values = array();
		for ($i = 0; $i < 50; $i++) {
			$expire = $now + 3600 - $i;
			$code = tmp_code_new_password();
			$values[] = array(tmp_code_make('reset', $expire, $code), $expire, $code, $code);
			$hash = hash('sha256', bin2hex(random_bytes(24)));
			$values[] = array(tmp_code_make('invite', $now + 72 * 3600, $hash), $now + 72 * 3600, $hash, null);
			$values[] = array(tmp_code_make('2fa', $now + 180, '482913'), $now + 180, '482913', null);
			// The earlier form, as tmp_code_storage_form() rewrites it at login. Its code was made by an earlier version,
			// with any first character: here one from 2-9 or a letter, as those were made.
			$earlier = substr('23456789abcXYZ', $i % 14, 1) . substr($code, 1);
			$values[] = array((string) tmp_code_storage_form('reset|' . $expire . '|' . $earlier), $expire, $earlier, null);
			$values[] = array((string) tmp_code_storage_form('invite|' . ($now + 72 * 3600) . '|' . $hash), $now + 72 * 3600, $hash, null);
		}
		return $values;
	}

	public function test_a_temporary_password_logs_in_with_its_own_code_only_and_only_until_it_expires(): void
	{
		$now = time();
		foreach (self::writtenValues($now) as list($stored, $expire, $code, $works)) {
			foreach (array($now, $expire, $expire + 1, $expire + 86400) as $at) {
				foreach (self::candidates($stored, $expire, $code) as $password) {
					$expected = ($works !== null && $password === $works && $at <= $expire);
					$this->assertSame($expected, self::readerLogin($stored, $password, $at), "login, value '$stored', password '$password', time $at");
					$this->assertSame($expected, self::readerApiLogin($stored, $password, $at), "API login, value '$stored', password '$password', time $at");
				}
			}
		}
	}

	public function test_the_two_factor_step_sends_a_new_code_for_every_written_value(): void
	{
		$now = time();
		foreach (self::writtenValues($now) as list($stored, $expire, $code)) {
			foreach (array($now - 86400 * 400, $now, $expire + 1, $now + 86400 * 30) as $at) {
				foreach (self::candidates($stored, $expire, $code) as $given) {
					$this->assertSame('send', self::readerTwoFactor($stored, $given, $at), "two-factor, value '$stored', code '$given', time $at");
				}
			}
		}
	}

	public function test_the_two_factor_step_reads_the_code_it_wrote(): void
	{
		$now = time();
		$this->assertSame('success', self::readerTwoFactor('482913|' . ($now + 180), '482913', $now));
		$this->assertSame('ask', self::readerTwoFactor('482913|' . ($now + 180), '', $now));
		$this->assertSame('ask', self::readerTwoFactor('482913|' . ($now + 180), '111111', $now));
		$this->assertSame('send', self::readerTwoFactor('482913|' . ($now - 1), '482913', $now));
		$this->assertSame('send', self::readerTwoFactor(null, '', $now));
	}

	public function test_the_written_forms(): void
	{
		$this->assertSame('1764000000|0bcDEF2345', tmp_code_make('reset', 1764000000, '0bcDEF2345'));
		$this->assertSame('0|0|invite|1764000000|' . str_repeat('a', 64), tmp_code_make('invite', 1764000000, str_repeat('a', 64)));
		$this->assertSame('0|0|2fa|1764000000|482913', tmp_code_make('2fa', 1764000000, '482913'));
	}

	public function test_this_code_reads_the_current_and_the_earlier_forms(): void
	{
		$future = time() + 600;
		$past = time() - 600;
		$this->assertSame('ok', tmp_code_check(tmp_code_make('reset', $future, '0bcDEF2345'), 'reset', '0bcDEF2345'));
		$this->assertSame('ok', tmp_code_check('reset|' . $future . '|abcDEF2345', 'reset', 'abcDEF2345'));
		$this->assertSame('ok', tmp_code_check(tmp_code_storage_form('reset|' . $future . '|abcDEF2345'), 'reset', 'abcDEF2345'), 'rewritten: works here until it expires');
		$this->assertSame('expired', tmp_code_check(tmp_code_storage_form('reset|' . $past . '|abcDEF2345'), 'reset', 'abcDEF2345'));
		$this->assertSame('expired', tmp_code_check(tmp_code_make('reset', $past, '0bcDEF2345'), 'reset', '0bcDEF2345'));
		$this->assertSame('', tmp_code_check(tmp_code_make('reset', $future, '0bcDEF2345'), 'reset', (string) $future));
		$hash = hash('sha256', 'x');
		$this->assertSame('ok', tmp_code_check(tmp_code_make('invite', $future, $hash), 'invite', $hash));
		$this->assertSame('ok', tmp_code_check('invite|' . $future . '|' . $hash, 'invite', $hash));
		$this->assertSame('', tmp_code_check(tmp_code_make('invite', $future, $hash), 'reset', $hash), 'an invitation is no temporary password');
		$this->assertSame('', tmp_code_check(tmp_code_make('reset', $future, '0bcDEF2345'), 'invite', '0bcDEF2345'));
		$this->assertSame('', tmp_code_check(tmp_code_storage_form('reset|' . $future . '|abcDEF2345'), 'invite', 'abcDEF2345'));
		$this->assertTrue(tmp_code_is_invite(tmp_code_make('invite', $future, $hash)));
		$this->assertTrue(tmp_code_is_invite('invite|' . $future . '|' . $hash));
		$this->assertFalse(tmp_code_is_invite(tmp_code_make('reset', $future, '0bcDEF2345')));
		$this->assertFalse(tmp_code_is_invite(tmp_code_storage_form('reset|' . $future . '|abcDEF2345')));
		$this->assertFalse(tmp_code_is_invite(null));
		$this->assertFalse(tmp_code_is_invite(''));
	}

	public function test_the_earlier_form_is_rewritten_to_the_current_one(): void
	{
		$hash = str_repeat('b', 64);
		$this->assertSame('0|0|reset|1764000000|abcDEF2345', tmp_code_storage_form('reset|1764000000|abcDEF2345'));
		$this->assertSame('0|0|invite|1764000000|' . $hash, tmp_code_storage_form('invite|1764000000|' . $hash));
		$this->assertNull(tmp_code_storage_form('2fa|1764000000|482913'), 'a two-factor code is cleared');
		$this->assertNull(tmp_code_storage_form('reset|soon|abcDEF2345'), 'no numeric expiry: cleared');
		$this->assertNull(tmp_code_storage_form('reset|1764000000|'), 'no code: cleared');
		$this->assertNull(tmp_code_storage_form('reset|1764000000|a|b'), 'more fields: cleared');
		foreach (array('1764000000|0bcDEF2345', '0|0|invite|1764000000|' . $hash, '0|0|reset|1764000000|abcDEF2345', '482913|1764000000', 'x') as $kept) {
			$this->assertSame($kept, tmp_code_storage_form($kept), "'$kept' is kept");
		}
		$this->assertNull(tmp_code_storage_form(null));
	}

	public function test_a_new_temporary_password_starts_with_zero_and_is_never_a_number(): void
	{
		for ($i = 0; $i < 2000; $i++) {
			$code = tmp_code_new_password();
			$this->assertSame(1, preg_match('/^0[a-km-zA-HJ-NP-Z2-9]{9}$/', $code), $code);
			$this->assertFalse(is_numeric($code), $code);
		}
	}

	public function test_a_new_password_clears_the_field(): void
	{
		// Every place a ledger user's password is set in this code: the pending temporary password or invitation in
		// brugere.tmp_kode stops working with it.
		$root = __DIR__ . '/../../';
		$places = array(
			'systemdata/personalSettings.php' => "/update brugere set kode = '\\\$hash', tmp_kode = null where id = \\\$brugerId/",
			'systemdata/usersRoles.php' => "/kode = '\" \. db_escape_string\(saldikrypt\(\\\$id, \\\$kode\)\) \. \"', tmp_kode = null\"/",
			'includes/userFunctions.php' => "/update brugere set kode = '\" \. db_escape_string\(saldikrypt\(\\\$id, \\\$password\)\) \. \"', tmp_kode = null where id/",
		);
		foreach ($places as $file => $pattern) {
			$this->assertSame(1, preg_match($pattern, (string) file_get_contents($root . $file)), $file);
		}
	}
}
