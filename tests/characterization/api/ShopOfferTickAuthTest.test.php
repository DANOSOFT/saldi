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

	/** The dispatcher runs the tick in one place only, inside the branch access_check() granted; a refused call gets the refusal. */
	public function testTheDispatcherRunsTheTickOnlyAfterAccessCheck(): void
	{
		$src = (string)file_get_contents(self::API);
		$check = strpos($src, '$svar = access_check();');
		$granted = $check === false ? false : strpos($src, "if (\$svar == 'OK') {", $check);
		$refused = $granted === false ? false : strpos($src, '} else $value=$svar;', $granted);
		$this->assertNotFalse($refused, 'the dispatcher\'s access branch is where it was');
		$branch = substr($src, (int)$granted, (int)$refused - (int)$granted);
		$action = strpos($branch, "\$action == 'shop_offer_tick'");
		$this->assertNotFalse($action, 'the action is dispatched in the granted branch');
		$next = strpos($branch, '##############################################', (int)$action); // the separator before the next action
		$own = substr($branch, (int)$action, ($next === false ? strlen($branch) : $next) - (int)$action);
		$this->assertStringContainsString('shopOfferReconcileTick()', $own, 'and that action runs the tick');
		$this->assertSame(1, substr_count($src, 'shopOfferReconcileTick('), 'and nowhere else in the file');
	}

	public function testTheSaldiuserIsStillRequired(): void
	{
		unset($_GET['saldiuser']);
		$_POST = ['key' => 'test-key-1'];
		$this->assertSame('Missing saldiuser', access_check());
	}
}
