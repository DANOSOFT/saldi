<?php
// 20261127 ASR Created. $allowedLedgers in includes/connect.php (includes/ledgerAllowList.php, includes/installSettings.php).
// 20261127 ASR Fixtures use fictive names.
// 20261127 ASR The master database is known without $GLOBALS['sqdb'] (connect.php's text, the assistant's SALDI_ASSIST_DB_MASTER).
// 20261127 ASR create_regnskab() and signup_activate() create nothing with the setting, before the master database is read or written.
// 20261127 ASR Slet regnskab and the save and Beregn of vis_regnskaber act on listed ledgers only.
// 20261127 ASR Every entry point checks a resolved ledger against the list before it becomes the working one, the master database too.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * An installation that sets $allowedLedgers opens only the listed ledgers: saldi_ledger_allowed() for a ledger or a
 * session, saldi_ledger_connect_allowed() in db_connect() (the master database too). The setting is read from the
 * global variable, or from connect.php's own text where the request has not included it.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ledgerAllowList extends TestCase
{
	protected function setUp(): void
	{
		require_once __DIR__ . '/../../includes/ledgerAllowList.php';
	}

	public function test_without_the_setting_every_ledger_opens(): void
	{
		unset($GLOBALS['allowedLedgers']);
		$GLOBALS['sqdb'] = 'saldi';
		if (saldi_install_settings_file() !== [] && array_key_exists('allowedLedgers', saldi_install_settings_file())) {
			self::markTestSkipped('this checkout\'s includes/connect.php sets $allowedLedgers');
		}
		self::assertNull(saldi_allowed_ledgers());
		self::assertTrue(saldi_ledger_allowed('saldi_7'));
		self::assertTrue(saldi_ledger_allowed('saldi'));
		self::assertTrue(saldi_ledger_connect_allowed('template1'));
	}

	public function test_a_list_opens_only_its_ledgers_and_the_master_connection(): void
	{
		$GLOBALS['allowedLedgers'] = array('saldi_901');
		$GLOBALS['sqdb'] = 'saldi';
		self::assertTrue(saldi_ledger_allowed('saldi_901'));
		self::assertTrue(saldi_ledger_allowed("'saldi_901'"), 'quoted as older call sites pass it');
		self::assertFalse(saldi_ledger_allowed('saldi_7'));
		self::assertFalse(saldi_ledger_allowed('saldi'), 'no master-admin session unless the master is listed');
		self::assertFalse(saldi_ledger_allowed(''));
		self::assertTrue(saldi_ledger_connect_allowed('saldi'), 'every request reads the master database');
		self::assertTrue(saldi_ledger_connect_allowed("'saldi_901'"));
		self::assertFalse(saldi_ledger_connect_allowed('saldi_7'));
		self::assertFalse(saldi_ledger_connect_allowed('template1'), 'no other database');
		$GLOBALS['allowedLedgers'] = array('saldi_901', 'saldi');
		self::assertTrue(saldi_ledger_allowed('saldi'), 'the master database when it is listed');
	}

	public function test_one_name_or_a_comma_list_is_a_list_too(): void
	{
		$GLOBALS['allowedLedgers'] = 'saldi_901';
		self::assertSame(array('saldi_901'), saldi_allowed_ledgers());
		$GLOBALS['allowedLedgers'] = ' saldi_901 , saldi_2 ';
		self::assertSame(array('saldi_901', 'saldi_2'), saldi_allowed_ledgers());
		$GLOBALS['allowedLedgers'] = array();
		self::assertSame(array(), saldi_allowed_ledgers(), 'an empty list opens nothing');
		self::assertFalse(saldi_ledger_allowed('saldi_901'));
	}

	public function test_the_settings_are_read_from_connect_php_without_running_it(): void
	{
		$source = <<<'PHP'
<?php
if (!isset($bg)) $bg='';
$db_type = "postgresql";
$sqhost = "localhost";
$sqdb   = "saldi";
// $allowedLedgers = array('commented_out');
# $otherSetting = 'COMMENTED';
/* $sharedWithLive = false; */
$allowedLedgers = array('saldi_901', "saldi_2",);
$otherSetting = 'SALDIB';
$sharedWithLive = true;
$port = 5432;
$computed = getenv('X');
$interpolated = "$sqhost-x";
if ($sqdb) {
	$insideBlock = 'no';
}
$short = ['a', 'b'];
$mixed = array('a', $sqhost);
$later = 'first';
$later = 'second';
?>
PHP;
		$settings = saldi_install_settings_file($source);
		self::assertSame(array('saldi_901', 'saldi_2'), $settings['allowedLedgers']);
		self::assertSame('SALDIB', $settings['otherSetting']);
		self::assertTrue($settings['sharedWithLive']);
		self::assertSame(5432, $settings['port']);
		self::assertSame('postgresql', $settings['db_type']);
		self::assertSame(array('a', 'b'), $settings['short']);
		self::assertSame('second', $settings['later']);
		foreach (array('computed', 'interpolated', 'insideBlock', 'mixed', 'bg') as $left) {
			self::assertArrayNotHasKey($left, $settings, "$left is not a literal at the top level");
		}
	}

	public function test_the_global_variable_wins_over_the_file(): void
	{
		$GLOBALS['allowedLedgers'] = array('from_global');
		self::assertSame(array('from_global'), saldi_install_setting('allowedLedgers'));
	}

	public function test_the_master_database_is_known_where_connect_php_was_not_included(): void
	{
		unset($GLOBALS['sqdb']);
		$file = saldi_install_settings_file();
		if (!isset($file['sqdb']) || !is_string($file['sqdb']) || $file['sqdb'] === '') {
			self::markTestSkipped('this checkout has no includes/connect.php with $sqdb');
		}
		$GLOBALS['allowedLedgers'] = array('saldi_901');
		self::assertTrue(saldi_ledger_connect_allowed($file['sqdb']), 'the master database from connect.php\'s text');
		self::assertFalse(saldi_ledger_allowed($file['sqdb']), 'as a session it still needs to be listed');
		self::assertFalse(saldi_ledger_connect_allowed('saldi_7'));
		self::assertTrue(saldi_ledger_connect_allowed('saldi_901'));
	}

	public function test_the_assistant_opens_its_master_database_and_only_listed_ledgers(): void
	{
		require_once __DIR__ . '/../../includes/assist/RecordAuth.php';
		unset($GLOBALS['sqdb']);
		$GLOBALS['allowedLedgers'] = array('saldi_901');
		putenv('SALDI_ASSIST_DB_MASTER=assist_master_x');
		putenv('SALDI_ASSIST_DB_HOST');
		$reason = static function (string $database): string {
			try {
				saldi_assist_read_connection($database);
				return 'opened';
			} catch (SaldiAssistFailure $failure) {
				return $failure->getMessage();
			}
		};
		// Past the list, the connection stops at the missing database host: no database is opened in this test.
		self::assertSame('not_configured', $reason('assist_master_x'), 'the master database the endpoint names');
		self::assertSame('not_configured', $reason('saldi_901'), 'a listed ledger');
		self::assertSame('company_unavailable', $reason('saldi_7'), 'any other database');
		putenv('SALDI_ASSIST_DB_MASTER');
		self::assertSame('company_unavailable', $reason('assist_master_x'), 'without the variable it is an unlisted database');
	}

	/** Stand-ins for the database functions create_regnskab() and signup_activate() use: each call is recorded. */
	private static function recordDatabaseCalls(): void
	{
		$GLOBALS['ledgerAllowListCalls'] = array();
		$GLOBALS['ledgerAllowListFetches'] = 0;
		if (!function_exists('db_select')) {
			function db_select($sql, $where = '') { $GLOBALS['ledgerAllowListCalls'][] = $sql; return 'result'; }
			function db_modify($sql, $where = '') { $GLOBALS['ledgerAllowListCalls'][] = $sql; return true; }
			function db_fetch_array($result) { return ($GLOBALS['ledgerAllowListFetches']++ === 0) ? false : array('id' => 0, 'status' => 'used'); }
			function transaktion($what) { $GLOBALS['ledgerAllowListCalls'][] = $what; }
			function signup_log($text) { $GLOBALS['ledgerAllowListLog'][] = $text; }
			function signup_setting($name, $default = '') { $GLOBALS['ledgerAllowListCalls'][] = "setting $name"; return $default; }
		}
	}

	public function test_no_ledger_is_created_while_the_setting_is_there(): void
	{
		self::recordDatabaseCalls();
		require_once __DIR__ . '/../../includes/opretfunc.php';
		$GLOBALS['sqdb'] = 'saldi';
		$GLOBALS['allowedLedgers'] = array('saldi_901', 'saldi');
		$result = create_regnskab('Nyt firma', 'admin', 'secret', 'info@example.invalid', 1000, 2, 'on');
		self::assertFalse($result['ok']);
		self::assertSame('not_allowed', $result['error']);
		self::assertSame(array(), $GLOBALS['ledgerAllowListCalls'], 'the master database is neither read nor written');

		unset($GLOBALS['allowedLedgers']);
		if (array_key_exists('allowedLedgers', saldi_install_settings_file())) {
			self::markTestSkipped('this checkout\'s includes/connect.php sets $allowedLedgers');
		}
		$result = create_regnskab('Nyt firma', 'admin', 'secret', 'info@example.invalid', 1000, 2, 'on');
		self::assertNotSame('not_allowed', $result['error'], 'without the setting the master row is reserved as before');
		self::assertStringStartsWith('select id from regnskab', $GLOBALS['ledgerAllowListCalls'][0]);
		self::assertContains('begin', $GLOBALS['ledgerAllowListCalls']);
	}

	public function test_no_signup_is_activated_while_the_setting_is_there(): void
	{
		self::recordDatabaseCalls();
		require_once __DIR__ . '/../../includes/signupIncludes/signupFunc.php';
		$GLOBALS['allowedLedgers'] = array('saldi_901');
		$result = signup_activate(array('id' => 7, 'pakke' => 'basis', 'email' => 'info@example.invalid'), 'Nyt firma', 'secret');
		self::assertFalse($result['ok']);
		self::assertSame('create', $result['error']);
		self::assertSame(array(), $GLOBALS['ledgerAllowListCalls'], 'nothing is reserved, read or written');
		self::assertStringContainsString('$allowedLedgers', implode("\n", $GLOBALS['ledgerAllowListLog']));
	}

	public function test_the_signup_pages_are_refused_while_the_setting_is_there(): void
	{
		$source = (string) file_get_contents(__DIR__ . '/../../includes/signupIncludes/signupBootstrap.php');
		$guard = strpos($source, "if (saldi_allowed_ledgers() !== null) {\n\tsaldi_ledger_refuse('');");
		self::assertNotFalse($guard, 'the shared start of every signup page refuses');
		self::assertLessThan(strpos($source, '$db = $sqdb;'), $guard, 'before the master database is used');
		self::assertLessThan(strpos($source, 'signup_schema_ready()'), $guard);
	}

	public function test_the_admin_pages_that_act_on_a_ledger_follow_the_list(): void
	{
		$root = __DIR__ . '/../../admin/';
		$slet = (string) file_get_contents($root . 'slet_regnskab.php');
		$guard = strpos($slet, "if (!\$listedRow || !saldi_ledger_allowed(\$listedRow['db']) || trim((string) \$listedRow['db']) !== trim((string) \$db_navn[\$x])) {");
		self::assertNotFalse($guard, 'Slet regnskab checks the row the master database holds for the id');
		self::assertLessThan(strpos($slet, 'DROP DATABASE IF EXISTS'), $guard, 'before the database is dropped');
		self::assertStringContainsString("&& saldi_ledger_allowed(\$r1['db'])) {", $slet, 'only listed ledgers are offered');
		$vis = (string) file_get_contents($root . 'vis_regnskaber.php');
		self::assertStringContainsString("if (\$id[\$x] && \$vr_rowAllowed) db_modify(\$qtxt", $vis, 'the save writes only rows of listed ledgers');
		$beregn = strpos($vis, "if (!saldi_ledger_allowed(\$db_navn[\$x])) continue;");
		self::assertNotFalse($beregn);
		self::assertLessThan(strpos($vis, 'db_create($db_navn[$x]);'), $beregn, 'Beregn counts and creates listed ledgers only');
	}

	/**
	 * Every place that makes a resolved ledger the working one checks the list first (the master database too), before
	 * it connects to it or writes anything for it. The jobs, REST and Stripe are run for real in
	 * LedgerAllowListMasterJobsTest and LedgerAllowListRestTest; these are the other entry points.
	 *
	 * @return list<array{0: string, 1: string, 2: string}> file, the check, the first thing that must come after it
	 */
	private static function guardedEntryPoints(): array
	{
		return array(
			array('includes/stripeIncludes/stripeBootstrap.php', 'saldi_ledger_guard($stripe_target_db);', '$connection = db_connect($sqhost, $squser, $sqpass, $stripe_target_db);'),
			array('soapserver/logon.php', 'if (!saldi_ledger_allowed($db)) return "1".chr(9).saldi_ledger_refusal_text();', 'db_modify("insert into online'),
			array('api/bet_func.php', 'if (!saldi_ledger_allowed($db)) return "1".chr(9).saldi_ledger_refusal_text();', '$connection = db_connect ($sqhost,$squser,$sqpass,$db);'),
			array('api/pbs_tilmelding.php', 'if (!saldi_ledger_allowed($db)) return "1".chr(9).saldi_ledger_refusal_text();', '$connection = db_connect ($sqhost,$squser,$sqpass,$db);'),
			array('rotary/pbs_tilmelding2.php', 'if (!saldi_ledger_allowed($db)) return "1".chr(9).saldi_ledger_refusal_text();', '$connection = db_connect ($sqhost,$squser,$sqpass,$db);'),
			array('api/rest_api.php', 'saldi_ledger_guard($db);', 'mkdir("../temp/$db"'),
			array('api/crm/ical.php', "if (!saldi_ledger_allowed((string) \$r['db'])) {", '$connection = db_connect($sqhost, $squser, $sqpass, $db);'),
			array('api/v2/includes/ApiKeyManager.php', 'if (!saldi_ledger_allowed($database)) {', 'mysqli_select_db($this->connection, $database)'),
			array('restapi/core/auth.php', 'if (isset($db) && !saldi_ledger_allowed($db)) {', 'write_log("Getting db'),
			array('restapi/endpoints/v1/notifications/register.php', 'if ($tenantDb && !saldi_ledger_allowed($tenantDb)) {', '$this->db = $tenantDb;'),
			array('restapi/endpoints/v1/user/tenants.php', "if (!saldi_ledger_allowed(\$r['db'])) {", "\$db_conn = db_connect(\$sqhost, \$squser, \$sqpass, \$r['db']"),
			array('mysale/shopLookup.php', "|| !saldi_ledger_allowed(\$login['db'])) {", 'db_modify("UPDATE online SET logtime'),
			array('mysale/mysale.php', 'saldi_ledger_guard($db); // $allowedLedgers', '$qtxt = "delete from online where rettigheder'),
			array('mysale/mysale.php', '$tmpLinks && saldi_ledger_allowed($c) && ($tmpConnection = db_connect(', 'mySaleSignIn($_SESSION, $s_id, $tmpAccountId, $c);'),
			array('mysale/mylabel.php', 'saldi_ledger_guard($db);', '$qtxt = "insert into online'),
			array('mysale/mylabelX.php', 'saldi_ledger_guard($db);', '$qtxt = "insert into online'),
			array('lager/labelprint.php', 'saldi_ledger_guard($db);', '$qtxt = "insert into online'),
			array('booking/index.php', 'saldi_ledger_guard($db);', '$qtxt = "insert into online'),
			array('includes/_docPoolData.php', 'if (!saldi_ledger_allowed($db)) {', '$connection = db_connect($sqhost, $squser, $sqpass, $db'),
			array('includes/docsIncludes/extractInvoiceHandler.php', 'if (!saldi_ledger_allowed($db)) {', '$connection = db_connect($sqhost, $squser, $sqpass, $db'),
			array('includes/poolFolderSync.php', 'if (!saldi_ledger_allowed($db)) {', '$connection = db_connect($sqhost, $squser, $sqpass, $db'),
			array('finans/kladdeliste.php', 'saldi_ledger_guard($db);', '$connection = db_connect($sqhost, $squser, $sqpass, $db'),
			array('admin/verify_moms_periode_luk.php', 'if (!saldi_ledger_allowed($tenantDb)) {', '$connection = @db_connect($sqhost, $squser, $sqpass, $tenantDb);'),
			array('includes/publicTenantDb.php', 'if (!saldi_ledger_allowed($db)) {', 'return $db;'),
		);
	}

	public function test_every_entry_point_checks_the_ledger_before_it_becomes_the_working_one(): void
	{
		$root = __DIR__ . '/../../';
		foreach (self::guardedEntryPoints() as $case) {
			list($file, $guard, $after) = $case;
			$source = (string) file_get_contents($root . $file);
			$at = strpos($source, $guard);
			self::assertNotFalse($at, "$file checks the list");
			$next = strpos($source, $after, $at);
			self::assertNotFalse($next, "$file: '$after' comes after the check");
		}
		$tools = array('tools/stripe_charge_due.php' => '$jobLock = stripe_charge_job_lock($targetDb);', 'tools/reminder_due.php' => 'select id,db from regnskab',
			'tools/stripe_invoice_pdf.php' => 'select id, db from regnskab', 'admin/stripe_setup.php' => '$target = stripe_ledger_db($argDb);');
		foreach ($tools as $file => $after) {
			$source = (string) file_get_contents($root . $file);
			$at = strpos($source, 'saldi_ledger_guard(');
			self::assertNotFalse($at, "$file checks the ledger it is named");
			self::assertLessThan(strpos($source, $after), $at, "$file: before '$after'");
		}
	}

	public function test_a_public_endpoint_gets_the_refusal_as_json(): void
	{
		$cmd = array(PHP_BINARY, '-r', '$GLOBALS["allowedLedgers"] = array("saldi_901"); require "' . addslashes(__DIR__ . '/../../includes/publicTenantDb.php') . '"; echo public_tenant_db($argv[1]);');
		foreach (array('saldi_7' => false, 'saldi_901' => true) as $name => $allowed) {
			$proc = proc_open(array_merge($cmd, array($name)), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
			$stdout = stream_get_contents($pipes[1]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			$exit = proc_close($proc);
			self::assertSame(0, $exit);
			if ($allowed) {
				self::assertSame('saldi_901', $stdout);
			} else {
				self::assertSame(array('success' => false, 'error' => saldi_ledger_refusal_text()), json_decode((string) $stdout, true));
			}
		}
	}

	/** @return array{exit: int, stdout: string, stderr: string} */
	private static function connect(array $allowed, string $db): array
	{
		$cmd = array(PHP_BINARY, __DIR__ . '/support/run_db_connect_allowlist.php', json_encode($allowed), 'saldi', $db);
		$proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		return array('exit' => proc_close($proc), 'stdout' => $stdout, 'stderr' => $stderr);
	}

	public function test_db_connect_refuses_a_database_outside_the_list_before_connecting(): void
	{
		$refused = self::connect(array('saldi_901'), 'saldi_7');
		self::assertSame(1, $refused['exit'], $refused['stdout'] . $refused['stderr']);
		self::assertStringContainsString('is not in $allowedLedgers', $refused['stderr']);
		self::assertStringNotContainsString('tried', $refused['stdout']);

		foreach (array('saldi_901', "'saldi_901'", 'saldi') as $open) {
			$tried = self::connect(array('saldi_901'), $open);
			self::assertSame(0, $tried['exit'], "$open: " . $tried['stdout'] . $tried['stderr']);
			self::assertStringContainsString('tried', $tried['stdout'], "$open is opened");
		}
	}
}
