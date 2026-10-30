<?php

declare(strict_types=1);

namespace Saldi\Tests\PostingWarehouse;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261021 CL/MJ SST-883 Pins which warehouse stock is posted to.
//
//                  An order line keeps the warehouse it was written with. Havemøbelland's
//                  warehouse 10 «V» was taken out of use, and five days later a credit note -
//                  which copies the warehouse from the order it credits - and an order from
//                  the webshop API posted stock to it again. The row was created without
//                  complaint, because nothing checked that the warehouse still existed.
//
//                  Stock there is invisible and unmovable: the warehouse columns on the item
//                  list and the rows on the product card are both built from grupper art='LG',
//                  so it has no column, but «i alt» and the totalStock sent to the three
//                  webshops are SUM(beholdning) over all of lagerstatus, so it still counts.
//                  lagerflyt cannot move it either, because that page lists art='LG' too.
//
//                  The two posting writers both live in includes/ordrefunc.php - linjeopdat()
//                  writes a row directly and lagerstatus() is reached from batch_salg_lev() -
//                  so both resolve the warehouse through one shared helper now. The helper is
//                  lifted out of its own file and evaluated into this namespace, where the
//                  database calls are stubbed, so the test runs the shipped source.
//
//                  Fixture only; no customer data. The warehouse numbers are the ones named in
//                  the ticket: 4 is Havemøbelland's M, 10 is the «V» that was removed.
// 20261027 CDX/MJ SST-883 Cover primary department mapping and removed fallback mappings.
// 20261030 CL/MJ SST-883 An account without warehouses keeps levering()'s default warehouse 1.

function db_select($sql, $location = '')
{
	$GLOBALS['pwQueries'][] = $sql;

	if (strpos($sql, "from grupper where art='LG' limit 1") !== false) {
		return $GLOBALS['pwDefined'] === array() ? false : array('id' => 100);
	}
	if (strpos($sql, "from grupper where art='LG' and kodenr=") !== false) {
		if (preg_match('/kodenr=(-?\d+)/', $sql, $m)) {
			$kodenr = (int) $m[1];
			return in_array($kodenr, $GLOBALS['pwDefined'], true) ? array('id' => 100 + $kodenr) : false;
		}
		return false;
	}
	if (strpos($sql, 'from ansatte') !== false) {
		return $GLOBALS['pwEmployeeAfd'] === null ? false : array('afd' => $GLOBALS['pwEmployeeAfd']);
	}
	if (strpos($sql, "and art='AFD'") !== false) {
		return $GLOBALS['pwPrimaryWarehouse'] === null ? false : array('box1' => $GLOBALS['pwPrimaryWarehouse']);
	}
	if (strpos($sql, "from grupper where box1=") !== false) {
		return $GLOBALS['pwAfdWarehouse'] === null ? false : array('kodenr' => $GLOBALS['pwAfdWarehouse']);
	}
	return false;
}

function db_fetch_array($row)
{
	return $row;
}

function db_escape_string($value)
{
	return str_replace("'", "''", (string) $value);
}

require_once __DIR__ . '/../../../includes/std_func.php';

final class PostingWarehouseTest extends TestCase
{
	private const HELPER = __DIR__ . '/../../../includes/stdFunc/warehouse.php';
	private const ORDREFUNC = __DIR__ . '/../../../includes/ordrefunc.php';

	private static bool $loaded = false;

	/**
	 * Load the shipped helper into this namespace, where the database calls are stubbed.
	 *
	 * eval() does not inherit the caller's namespace, so the namespace is restated - the same
	 * approach PerformedByTest uses.
	 *
	 * The file guards each function with function_exists(), which asks about the *global* name.
	 * Running this file on its own that guard is false and the namespaced copies get defined;
	 * in a full suite run another test has already loaded the real file globally, the guard is
	 * true, nothing is defined here, and the calls below fall through to the real db_select()
	 * and reach for a database connection. So the guards are neutralised in the lifted copy -
	 * brace-balanced, so the bodies are still the shipped ones.
	 */
	public static function setUpBeforeClass(): void
	{
		if (self::$loaded) {
			return;
		}
		$src = file_get_contents(self::HELPER);
		self::assertNotFalse($src, 'could not read includes/stdFunc/warehouse.php');
		$src = preg_replace('/^<\?php/', '', $src, 1);
		$src = preg_replace(
			'/if \(!function_exists\(\'[A-Za-z_][A-Za-z0-9_]*\'\)\) \{/', 'if (true) {', $src, -1, $guards);
		self::assertSame(2, $guards,
			'warehouse.php no longer guards exactly two functions with function_exists(), so the lift is stale');
		eval('namespace ' . __NAMESPACE__ . '; ' . $src);
		self::$loaded = true;
	}

	protected function setUp(): void
	{
		// Havemøbelland's shape: warehouses 1 and 4 exist, 10 was removed. Every department
		// uses 4 = M, per the ticket.
		$GLOBALS['pwDefined'] = array(1, 4);
		$GLOBALS['pwEmployeeAfd'] = '2';
		$GLOBALS['pwAfdWarehouse'] = '4';
		$GLOBALS['pwPrimaryWarehouse'] = null;
		$GLOBALS['pwQueries'] = array();
		$GLOBALS['ref'] = 'lotte';
	}

	// ------------------------------------------------------------ the reported case

	/** A credit note copying warehouse 10 from the original order must not post there. */
	public function testAWarehouseThatNoLongerExistsIsNotPostedTo(): void
	{
		$got = resolvePostingWarehouse(10, 2003, 55);

		self::assertSame(4, $got,
			'stock is still posted to warehouse 10, where no column can show it and lagerflyt cannot move it');
	}

	/** The warehouse the document names is kept when it is a real one. */
	public function testAWarehouseThatExistsIsKept(): void
	{
		self::assertSame(4, resolvePostingWarehouse(4, 2003, 55),
			'a valid warehouse was replaced, so stock moved to another warehouse on its own');
		self::assertSame(1, resolvePostingWarehouse(1, 2003, 55),
			'a valid warehouse was replaced, so stock moved to another warehouse on its own');
	}

	// ------------------------------------------------------------ what must not change

	/**
	 * An empty warehouse still takes the department's.
	 *
	 * This is the behaviour the posting code has always had - "if (!$lager)" with the lookup
	 * inline - and it is the reason the helper tests $lager separately instead of relying on
	 * warehouseIsDefined(), which accepts 0.
     */
	#[DataProvider('emptyWarehouses')]
	public function testAnEmptyWarehouseStillTakesTheDepartments($empty): void
	{
		self::assertSame(4, resolvePostingWarehouse($empty, 2003, 55),
			'an empty warehouse stopped falling back to the department, so stock lands on 0');
	}

	/** @return array<string, array{mixed}> */
	public static function emptyWarehouses(): array
	{
		return array(
			'zero'          => array(0),
			'zero as text'  => array('0'),
			'empty string'  => array(''),
			'null'          => array(null),
		);
	}

	/** With no department warehouse to fall back on, 0 is where stock goes - as before. */
	public function testWithoutADepartmentWarehouseItFallsToZero(): void
	{
		$GLOBALS['pwAfdWarehouse'] = null;

		self::assertSame(0, resolvePostingWarehouse(10, 2003, 55),
			'a removed warehouse was kept when the department had none to offer');
		self::assertSame(0, resolvePostingWarehouse('', 2003, 55),
			'an empty warehouse no longer falls to 0');
	}

	/** No employee row either - still 0, never the warehouse that is gone. */
	public function testWithoutAnEmployeeRowItFallsToZero(): void
	{
		$GLOBALS['pwEmployeeAfd'] = null;

		self::assertSame(0, resolvePostingWarehouse(10, 2003, 55),
			'a removed warehouse was kept when there was no employee to take a department from');
	}

	/** The department can point at a warehouse that was removed as well. */
	public function testADepartmentPointingAtARemovedWarehouseFallsToZero(): void
	{
		$GLOBALS['pwAfdWarehouse'] = '10';

		self::assertSame(0, resolvePostingWarehouse(10, 2003, 55),
			'the fallback handed back a warehouse that does not exist either');
	}

	/** @return array<string, array{mixed, mixed, int}> */
	public static function departmentMappings(): array
	{
		return [
			'primary only' => ['4', null, 4],
			'primary preferred' => ['1', '4', 1],
			'removed primary uses legacy' => ['10', '4', 4],
			'empty primary uses legacy' => ['', '4', 4],
			'both removed' => ['10', '10', 0],
			'no warehouses' => [null, null, 0],
		];
	}

	#[DataProvider('departmentMappings')]
	public function testDepartmentMappingPriority($primary, $legacy, int $expected): void
	{
		$GLOBALS['pwPrimaryWarehouse'] = $primary;
		$GLOBALS['pwAfdWarehouse'] = $legacy;
		self::assertSame($expected, resolvePostingWarehouse(10, 2003, 55));
	}

	public function testDepartmentNumberIsCastBeforePrimaryLookup(): void
	{
		$GLOBALS['pwEmployeeAfd'] = "2' OR 1=1 --";
		resolvePostingWarehouse(10, 2003, 55);
		foreach ($GLOBALS['pwQueries'] as $sql) {
			self::assertStringNotContainsString('OR 1=1', $sql);
		}
	}

	// ------------------------------------------------------------ the predicate

	/**
	 * Warehouse 0 is always acceptable.
	 *
	 * An account with no art='LG' rows at all posts everything to 0. If the predicate refused
	 * it, stock would stop moving on every install that does not use warehouses.
	 */
	public function testWarehouseZeroIsAlwaysDefined(): void
	{
		$GLOBALS['pwDefined'] = array();

		self::assertTrue(warehouseIsDefined(0),
			'warehouse 0 is refused, which stops stock moving on an account without warehouses');
		self::assertTrue(warehouseIsDefined('0'),
			'warehouse 0 as text is refused');
	}

	/**
	 * An account without any warehouses posts exactly as before.
	 *
	 * levering() gives an order line without a warehouse warehouse 1. With no art='LG' rows that
	 * has always been where such an account's stock lives, and there are no warehouse columns for
	 * stock to go missing from, so nothing may be redirected to 0 - or the stock splits.
	 */
	public function testAnAccountWithoutWarehousesKeepsItsWarehouseNumbers(): void
	{
		$GLOBALS['pwDefined'] = array();

		self::assertTrue(warehouseIsDefined(1), "levering()'s default warehouse 1 is refused on an account without warehouses");
		self::assertSame(1, resolvePostingWarehouse(1, 2003, 55),
			'stock on an account without warehouses moved from warehouse 1 to 0');
		self::assertSame(10, resolvePostingWarehouse(10, 2003, 55),
			'an account without warehouses had a line warehouse redirected');
	}

	public function testThePredicateAnswersForRealAndRemovedWarehouses(): void
	{
		self::assertTrue(warehouseIsDefined(4), 'an existing warehouse is reported as missing');
		self::assertFalse(warehouseIsDefined(10), 'a removed warehouse is reported as existing');
		self::assertFalse(warehouseIsDefined('10'), 'a removed warehouse as text is reported as existing');
	}

	/**
	 * The lookup is not scoped to a fiscal year.
	 *
	 * Inventory is shared between fiscal years, and every other art='LG' read in the code is
	 * unfiltered - the item list and the product card both are. Adding a fiscal_year condition
	 * here would make a warehouse defined in another year look removed, and strand stock for
	 * the opposite reason.
	 */
	public function testTheLookupIsNotScopedToAFiscalYear(): void
	{
		warehouseIsDefined(4);

		self::assertNotEmpty($GLOBALS['pwQueries'], 'the predicate asked the database nothing');
		foreach ($GLOBALS['pwQueries'] as $sql) {
			self::assertStringNotContainsString('fiscal_year', $sql,
				'the warehouse lookup is scoped to a fiscal year, so a warehouse from another year reads as removed');
		}
	}

	/** The warehouse number reaches SQL as an integer, whatever arrives. */
	public function testTheWarehouseNumberIsIntCastIntoTheQuery(): void
	{
		warehouseIsDefined("10; drop table lagerstatus");

		self::assertNotEmpty($GLOBALS['pwQueries'], 'the predicate asked the database nothing');
		$sql = $GLOBALS['pwQueries'][0];
		self::assertStringContainsString('kodenr=10 ', $sql,
			'the warehouse number is not int-cast, so request input reaches the query');
		self::assertStringNotContainsString('drop table', $sql,
			'the warehouse number is interpolated raw');
	}

	/** The employee name is escaped; it is free text on the ansatte row. */
	public function testTheEmployeeNameIsEscaped(): void
	{
		$GLOBALS['ref'] = "o'brien";
		resolvePostingWarehouse(10, 2003, 55);

		$found = false;
		foreach ($GLOBALS['pwQueries'] as $sql) {
			if (strpos($sql, 'from ansatte') !== false) {
				$found = true;
				self::assertStringContainsString("navn = 'o''brien'", $sql,
					'the employee name is interpolated without escaping');
			}
		}
		self::assertTrue($found, 'the department lookup never asked for the employee');
	}

	// ------------------------------------------------------------ the two call sites

	/**
	 * Where the resolved warehouse is assigned inside the function starting at $fn.
	 *
	 * Matched as an assignment rather than by the function's name: the explaining comments
	 * beside both call sites mention resolvePostingWarehouse() too, so a plain substring search
	 * is satisfied by the prose and a removed call still reads as present. A mutation caught
	 * exactly that, and it is the same mistake CodeRabbit found in assignedBefore() on #809.
	 *
	 * @return int|false Offset of the call, or false when the function does not make one.
	 */
	private static function resolveCallIn(string $src, int $fn)
	{
		if (!preg_match('/\$lager\s*=\s*resolvePostingWarehouse\(/',
				substr($src, $fn), $m, PREG_OFFSET_CAPTURE)) {
			return false;
		}
		return $fn + $m[0][1];
	}

	/**
	 * Both writers resolve the warehouse before anything is written.
	 *
	 * The helper is lifted and evaluated above, so these tests cannot see that ordrefunc.php
	 * actually calls it. Without them, removing either call would leave every test above green.
	 */
	public function testLinjeopdatResolvesTheWarehouseBeforeItWritesStock(): void
	{
		$src = file_get_contents(self::ORDREFUNC);
		self::assertNotFalse($src, 'could not read includes/ordrefunc.php');

		$fn = strpos($src, 'function linjeopdat(');
		self::assertNotFalse($fn, 'linjeopdat() is gone from ordrefunc.php');
		$call = self::resolveCallIn($src, $fn);
		self::assertNotFalse($call, 'linjeopdat() no longer resolves the warehouse at all');
		$write = strpos($src, 'insert into lagerstatus (vare_id,variant_id,beholdning,lager)', $fn);
		self::assertNotFalse($write, 'linjeopdat() no longer writes a lagerstatus row where expected');
		self::assertLessThan($write, $call,
			'the warehouse is resolved after the stock row is written, which is too late');
	}

	public function testLagerstatusResolvesTheWarehouseBeforeItWritesStock(): void
	{
		$src = file_get_contents(self::ORDREFUNC);
		self::assertNotFalse($src, 'could not read includes/ordrefunc.php');

		$fn = strpos($src, 'function lagerstatus(');
		self::assertNotFalse($fn, 'lagerstatus() is gone from ordrefunc.php');
		$call = self::resolveCallIn($src, $fn);
		self::assertNotFalse($call, 'lagerstatus() no longer resolves the warehouse at all');
		$write = strpos($src, 'insert into lagerstatus (vare_id, variant_id, lager, beholdning)', $fn);
		self::assertNotFalse($write, 'lagerstatus() no longer writes a lagerstatus row where expected');
		self::assertLessThan($write, $call,
			'the warehouse is resolved after the stock row is written, which is too late');
	}

	/** The helper is included where it is used, not assumed to be loaded. */
	public function testOrdrefuncIncludesTheHelper(): void
	{
		$src = file_get_contents(self::ORDREFUNC);
		self::assertNotFalse($src, 'could not read includes/ordrefunc.php');

		self::assertStringContainsString("include_once(__DIR__ . '/stdFunc/warehouse.php')", $src,
			'ordrefunc.php does not include the helper, so it depends on another file having done it');
	}

	/** A refusal that cannot be honoured leaves a trace, but an empty warehouse is routine. */
	public function testOnlyAnUnusableWarehouseIsLogged(): void
	{
		$src = file_get_contents(self::HELPER);
		self::assertNotFalse($src, 'could not read includes/stdFunc/warehouse.php');

		$log = strpos($src, 'error_log(');
		self::assertNotFalse($log, 'a warehouse that could not be honoured leaves no trace at all');
		$guard = strrpos(substr($src, 0, $log), '$asked !== 0');
		self::assertNotFalse($guard,
			'the log line is not guarded, so every ordinary empty warehouse writes to the log');
	}
}
