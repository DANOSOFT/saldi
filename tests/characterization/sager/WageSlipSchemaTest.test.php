<?php
// 20261125 CDX/NTR Exercise piecework rejection on a fresh tenant and repeatable repair of the old tenant schema.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class WageSlipSchemaTest extends TestCase {
	private ?TestDatabase $database = null;
	private $connection;
	private const MISSING_COLUMNS = array('procent', 'op_30', 'ned_30', 'op_70', 'ned_70', 'op_100', 'ned_100', 'op_160', 'ned_160');

	protected function setUp(): void {
		$reason = TestDatabase::unavailableReason();
		if ($reason !== null) {
			self::markTestSkipped($reason);
		}
		$this->database = TestDatabase::create(static::class);
		$this->connection = TestDatabase::connect($this->database->name());
		$GLOBALS['wageSlipSchemaConnection'] = $this->connection;
		$GLOBALS['db_type'] = 'postgresql';
		$GLOBALS['sqdb'] = TestDatabase::masterDb();
		if (!function_exists('db_select')) {
			function db_select($sql, $location = '') {
				return TestDatabase::query($GLOBALS['wageSlipSchemaConnection'], $sql);
			}
			function db_modify($sql, $location = '') {
				return db_select($sql, $location);
			}
			function db_fetch_array($result) {
				return pg_fetch_assoc($result);
			}
			function db_escape_string($value) {
				return pg_escape_string($GLOBALS['wageSlipSchemaConnection'], (string)$value);
			}
		}
	}

	protected function tearDown(): void {
		if ($this->connection) {
			pg_close($this->connection);
		}
		$this->database?->release();
		unset($GLOBALS['wageSlipSchemaConnection'], $GLOBALS['db_type'], $GLOBALS['sqdb']);
	}

	#[TestWith(['akkord'])]
	#[TestWith(['akk_afr'])]
	public function testFreshTenantCanRejectPieceworkAndKeepEveryPercentageAndSurcharge(string $type): void {
		require_once __DIR__ . '/../../../sager/loenIncludes/loenAccess.php';
		$slip = db_fetch_array(db_select("INSERT INTO loen (nummer, art, afsluttet) VALUES (12, '$type', '123') RETURNING *"));
		$id = (int)$slip['id'];
		db_modify("INSERT INTO loen_enheder (loen_id, vare_id, tekst, procent, op_30, ned_30, op_70, ned_70, op_100, ned_100, op_160, ned_160) VALUES ($id, 5, 'Piecework', 87.500, 1.250, 2.500, 3.750, 4.125, 5.500, 6.750, 7.125, 8.500)");
		$original = db_fetch_array(db_select("SELECT * FROM loen_enheder WHERE loen_id=$id"));
		db_modify('BEGIN');
		loenRejectSlip($slip, 'Correct the quantities', 'reviewer');
		db_modify('COMMIT');
		$rejected = db_fetch_array(db_select("SELECT * FROM loen WHERE id=$id"));
		self::assertNotEmpty($rejected['afvist']);
		$copy = db_fetch_array(db_select("SELECT * FROM loen WHERE nummer=12 AND id!=$id"));
		self::assertSame('', $copy['afsluttet']);
		self::assertSame('Correct the quantities', $copy['afvist_pga']);
		$copyId = (int)$copy['id'];
		$line = db_fetch_array(db_select("SELECT * FROM loen_enheder WHERE loen_id=$copyId"));
		unset($original['id'], $original['loen_id'], $line['id'], $line['loen_id']);
		self::assertSame($original, $line);
	}

	public function testRepairOfAnOldTenantCanRunTwiceWithoutChangingStoredValues(): void {
		foreach (self::MISSING_COLUMNS as $column) {
			db_modify("ALTER TABLE loen_enheder DROP COLUMN $column");
		}
		db_modify("INSERT INTO loen_enheder (loen_id, op, pris_op, tekst) VALUES (52, 1.250, 1250.500, 'Keep this line')");
		require_once __DIR__ . '/../../../includes/structure/loenEnhederSchema.php';
		loen_enheder_schema();
		db_modify('UPDATE loen_enheder SET procent=87.500, op_70=0.005');
		$original = db_fetch_array(db_select('SELECT * FROM loen_enheder'));
		loen_enheder_schema();
		self::assertSame($original, db_fetch_array(db_select('SELECT * FROM loen_enheder')));
		self::assertSame('1.250', $original['op']);
		self::assertSame('1250.500', $original['pris_op']);
		foreach (self::MISSING_COLUMNS as $column) {
			self::assertArrayHasKey($column, $original);
		}
		self::assertSame('87.500', $original['procent']);
		self::assertSame('0.005', $original['op_70']);
	}
}
