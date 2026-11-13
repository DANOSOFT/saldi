<?php
// 20261113 CL/SZ SST-987 Created: the EasyUBL webhook key.
//                A call is valid only with the account's saved key, webhookKeyRequired decides whether an invalid call is refused, a saved key is never replaced, and the key is blanked in debug text.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * includes/stdFunc/easyUblWebhookKey.php against an in-memory settings table.
 * $GLOBALS['ubl_test_settings'] holds "var_grp:var_name" => var_value, and every write is recorded.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class easyUblWebhookKey extends TestCase
{
	private const KEY = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

	protected function setUp(): void
	{
		$GLOBALS['ubl_test_settings'] = [];
		$GLOBALS['ubl_test_writes'] = [];

		function get_settings_value($var_name, $var_grp, $default, $user = null, $kasse = null, $global = false)
		{
			return $GLOBALS['ubl_test_settings']["$var_grp:$var_name"] ?? $default;
		}

		function update_settings_value($var_name, $var_grp, $var_value, $var_description, $user = null, $posid = null, $global = false)
		{
			$GLOBALS['ubl_test_writes'][] = "$var_grp:$var_name";
			$GLOBALS['ubl_test_settings']["$var_grp:$var_name"] = $var_value;
		}

		require_once __DIR__ . '/../../includes/stdFunc/easyUblWebhookKey.php';
	}

	private function saveKey(string $key = self::KEY): void
	{
		$GLOBALS['ubl_test_settings']['easyUBL:webhookKey'] = $key;
	}

	private function requireKey(): void
	{
		$GLOBALS['ubl_test_settings']['easyUBL:webhookKeyRequired'] = '1';
	}

	public function testOnlyTheSavedKeyIsValid(): void
	{
		$this->saveKey();
		$this->assertSame(['valid' => true, 'reason' => 'ok', 'required' => false], easyubl_webhook_key_check(self::KEY));

		$cases = [
			'no key'            => [null, 'no key in request'],
			'empty key'         => ['', 'no key in request'],
			'array key'         => [[self::KEY], 'no key in request'],
			'wrong key'         => [str_repeat('f', 64), 'wrong key'],
			'one character off' => [substr(self::KEY, 0, 63), 'wrong key'],
			'key plus more'     => [self::KEY . 'x', 'wrong key'],
		];
		foreach ($cases as $label => [$given, $reason]) {
			$result = easyubl_webhook_key_check($given);
			$this->assertFalse($result['valid'], "$label was accepted");
			$this->assertSame($reason, $result['reason'], $label);
		}
	}

	public function testWithoutASavedKeyNothingIsValid(): void
	{
		$result = easyubl_webhook_key_check(self::KEY);
		$this->assertFalse($result['valid']);
		$this->assertSame('no key saved', $result['reason']);

		$result = easyubl_webhook_key_check('');
		$this->assertFalse($result['valid']);
	}

	public function testRequiredFollowsWebhookKeyRequired(): void
	{
		$this->saveKey();
		// Log-only by default: an invalid call is reported as not required, so easyUBL.php lets it through
		$this->assertFalse(easyubl_webhook_key_check(null)['required']);
		$this->assertFalse(easyubl_webhook_key_check(str_repeat('f', 64))['required']);

		$GLOBALS['ubl_test_settings']['easyUBL:webhookKeyRequired'] = 'on';
		$this->assertFalse(easyubl_webhook_key_check(null)['required'], "only '1' switches it on");

		$this->requireKey();
		foreach ([null, str_repeat('f', 64), [self::KEY]] as $given) {
			$result = easyubl_webhook_key_check($given);
			$this->assertFalse($result['valid']);
			$this->assertTrue($result['required']);
		}
		$this->assertSame(['valid' => true, 'reason' => 'ok', 'required' => true], easyubl_webhook_key_check(self::KEY));
	}

	public function testCheckingNeverCreatesAKey(): void
	{
		$this->requireKey();
		easyubl_webhook_key_check(null);
		easyubl_webhook_key_check(self::KEY);
		$this->assertSame([], $GLOBALS['ubl_test_writes']);
		$this->assertArrayNotHasKey('easyUBL:webhookKey', $GLOBALS['ubl_test_settings']);
	}

	public function testKeyIsCreatedOnceAndThenKept(): void
	{
		$first = easyubl_webhook_key();
		$this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $first);
		$this->assertSame(['easyUBL:webhookKey'], $GLOBALS['ubl_test_writes']);

		$this->assertSame($first, easyubl_webhook_key());
		$this->assertSame($first, easyubl_webhook_key());
		$this->assertCount(1, $GLOBALS['ubl_test_writes'], 'a saved key was written again');
		$this->assertTrue(easyubl_webhook_key_check($first)['valid']);
	}

	public function testExistingKeyIsNeverReplaced(): void
	{
		$this->saveKey();
		$this->assertSame(self::KEY, easyubl_webhook_key());
		$this->assertSame('https://ssl8.saldi.dk/laja/debitor/easyUBL.php?key=' . self::KEY, easyubl_webhook_url('https://ssl8.saldi.dk', 'laja'));
		$this->assertSame([], $GLOBALS['ubl_test_writes']);
	}

	public function testRedactBlanksTheKey(): void
	{
		$this->saveKey();
		$json = json_encode(['webhookUrl' => easyubl_webhook_url('https://ssl8.saldi.dk', 'laja')]);
		$redacted = easyubl_webhook_redact($json);
		$this->assertStringNotContainsString(self::KEY, $redacted);
		$this->assertStringContainsString('key=REDACTED', $redacted);
	}

	public function testRedactWithoutASavedKeyLeavesTextAlone(): void
	{
		$this->assertSame('{"companyID":"x"}', easyubl_webhook_redact('{"companyID":"x"}'));
		$this->assertSame([], $GLOBALS['ubl_test_writes']);
	}
}
