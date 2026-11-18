<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// 20261117 CL/LH Created. access_check() in api/rest_api.php checks every shop call and the offer reconciler's tick
//                (action=shop_offer_tick) against the account's API key (grupper API box1) and IP list (box2).
//                The scheduler sends the key in a POST body, so it is in no URL (web server access logs) and on no command line
//                (curl --data-urlencode key@file); a key in the query string works as before.
//                The function is lifted from the source (including the file connects to a database) and run against stubs.
// 20261118 CL/LH CodeRabbit review of #1157: the dispatcher runs the tick only inside the branch access_check() granted.
//                CodeRabbit review of 63cf3d3f2: the tick is the shop_offer_tick action's own call, not merely in the branch.
// 20261118 CL/LH CodeRabbit review of #1158: the dispatcher itself runs, with a counting stub tick: once for an account with its key,
//                never for a refused call, another action or a master account.
#[RunTestsInSeparateProcesses]
final class ShopOfferTickAuthTest extends TestCase
{
	private const API = __DIR__ . '/../../../api/rest_api.php';
	private string $dir = '';

	protected function setUp(): void
	{
		$GLOBALS['__api'] = ['box1' => 'test-key-1', 'box2' => '*'];
		eval('
			function db_escape_string($s) { return str_replace("\'", "\'\'", (string)$s); }
			function db_connect($h, $u, $p, $d) { return true; }
			function db_select($q, $where = "") { return $q; }
			function db_fetch_array($q) {
				if (strpos($q, "from regnskab") !== false) return array("id" => 7, "lukket" => "");
				if (strpos($q, "art=\'RA\'") !== false) return array("kodenr" => 1);
				if (strpos($q, "art = \'API\'") !== false) return $GLOBALS["__api"];
				throw new RuntimeException("unexpected query: " . $q);
			}
		');
		$src = (string)file_get_contents(self::API);
		$a = strpos($src, 'function access_check(');
		self::assertNotFalse($a, 'access_check() is gone from api/rest_api.php');
		$b = strpos($src, "\n}\n", $a);
		eval(substr($src, $a, $b - $a + 2));
		// access_check() writes ../temp/<db>/rest_api.log
		$this->dir = sys_get_temp_dir() . '/shopoffer-auth-' . bin2hex(random_bytes(4));
		mkdir($this->dir . '/api', 0777, true);
		chdir($this->dir . '/api');
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		$_GET = ['action' => 'shop_offer_tick', 'db' => 'pos_50', 'saldiuser' => 'offer-scheduler'];
		$_POST = [];
	}

	protected function tearDown(): void
	{
		@unlink($this->dir . '/temp/pos_50/rest_api.log');
		@rmdir($this->dir . '/temp/pos_50');
		@unlink($this->dir . '/temp/pos/rest_api.log');
		@rmdir($this->dir . '/temp/pos');
		@rmdir($this->dir . '/temp');
		@rmdir($this->dir . '/api');
		@rmdir($this->dir);
	}

	public function testTheKeyInAPostBodyIsAccepted(): void
	{
		$_POST = ['key' => 'test-key-1'];
		$this->assertSame('OK', access_check());
	}

	public function testTheKeyInTheQueryStringWorksAsBefore(): void
	{
		$_GET['key'] = 'test-key-1';
		$this->assertSame('OK', access_check());
	}

	public function testAWrongOrMissingKeyIsRefused(): void
	{
		$this->assertSame('Access denied (key)', access_check(), 'no key');
		$_POST = ['key' => 'not-the-key'];
		$this->assertSame('Access denied (key)', access_check(), 'a wrong key in the body');
		$_POST = ['key' => 'not-the-key'];
		$_GET['key'] = 'test-key-1';
		$this->assertSame('Access denied (key)', access_check(), 'the body is read first');
	}

	public function testTheIpListStillApplies(): void
	{
		$GLOBALS['__api'] = ['box1' => 'test-key-1', 'box2' => '198.51.100.1,198.51.100.2'];
		$_POST = ['key' => 'test-key-1'];
		$this->assertSame('Access denied (ip)', access_check());
		$_SERVER['REMOTE_ADDR'] = '198.51.100.2';
		$this->assertSame('OK', access_check());
	}

	/**
	 * Runs the dispatcher of api/rest_api.php itself, lifted like access_check() (from its "$value = ..." to the else of an
	 * absent action), with the real access_check() above and a stub tick that counts its calls.
	 *
	 * @return array{0: mixed, 1: int} The answer the dispatcher would print, and how many ticks ran.
	 */
	private function dispatch(): array
	{
		if (!function_exists('shopOfferReconcileTick')) {
			eval('function shopOfferReconcileTick() { $GLOBALS["__ticks"]++; return array("result" => "ok"); }');
		}
		if (!function_exists('__shopOfferDispatch')) {
			$src = (string)file_get_contents(self::API);
			$a = strpos($src, '$value = "An error has occurred";');
			$b = $a === false ? false : strpos($src, "} else \$value= if_isset(\$_GET['action']);", $a);
			$this->assertNotFalse($b, 'the dispatcher is where it was');
			eval('function __shopOfferDispatch() { global $db, $brugernavn, $regnaar, $db_skriv_id, $webservice; '
				. substr($src, (int)$a, (int)$b - (int)$a) . '} else $value = null; return $value; }');
		}
		$GLOBALS['__ticks'] = 0;
		$value = __shopOfferDispatch();
		return [$value, $GLOBALS['__ticks']];
	}

	/**
	 * The tick runs once for an account that passed access_check() with its key, and never for a refused call, another action,
	 * or a master account, which access_check() lets in from Danosoft's address without a key.
	 */
	public function testTheDispatcherRunsTheTickOnceForAnAuthorisedAccountAndNeverOtherwise(): void
	{
		$_POST = ['key' => 'test-key-1'];
		$this->assertSame([['result' => 'ok'], 1], $this->dispatch(), 'an account with its key: one tick');
		$_POST = ['key' => 'not-the-key'];
		$this->assertSame(['Access denied (key)', 0], $this->dispatch(), 'a wrong key: no tick');
		$_POST = ['key' => 'test-key-1'];
		$_GET['action'] = 'no_such_action';
		$this->assertSame(['Illegal action (no_such_action)', 0], $this->dispatch(), 'another action: no tick');
		$_GET = ['action' => 'shop_offer_tick', 'db' => 'pos', 'saldiuser' => 'offer-scheduler'];
		$_SERVER['REMOTE_ADDR'] = '91.235.100.32';
		$_POST = [];
		$this->assertSame(['Access denied (the offer tick needs an account and its API key)', 0], $this->dispatch(),
			'a master account from Danosoft\'s address, without a key: no tick');
	}

	public function testTheSaldiuserIsStillRequired(): void
	{
		unset($_GET['saldiuser']);
		$_POST = ['key' => 'test-key-1'];
		$this->assertSame('Missing saldiuser', access_check());
	}
}
