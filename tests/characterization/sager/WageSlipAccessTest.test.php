<?php
// 20261110 CDX/NTR SST-980 Exercise malformed targets, stored authorization, lifecycle and safe controller writes.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
final class WageSlipAccessTest extends TestCase {
	private function boot(array $changes = array()): void {
		require_once(__DIR__ . '/support/WageSlipHarness.php');
		require_once(__DIR__ . '/../../../includes/std_func.php');
		require_once(__DIR__ . '/../../../sager/loenIncludes/retLoen.php');
		WageSlipHarness::$slip = array_replace(array(
			'id' => 52, 'nummer' => 12, 'sag_id' => 7, 'opg_id' => 8,
			'oprettet_af' => 'installer', 'oprettet' => '1700000000',
			'afsluttet' => '', 'godkendt' => '', 'afregnet' => '', 'afvist' => '',
			'afsluttet_af' => '', 'godkendt_af' => '', 'afvist_af' => ''
		), $changes);
		$GLOBALS['brugernavn'] = 'installer';
		$GLOBALS['sag_rettigheder'] = '0000000';
		$GLOBALS['overtid_50pct'] = $GLOBALS['overtid_100pct'] = 0;
		$GLOBALS['sprog_id'] = 1;
		$_GET = array('id' => '52');
		$_POST = array();
	}

	private function request(array $post): string {
		$_POST = $post;
		http_response_code(200);
		ob_start();
		try {
			ret_loen();
			return (string)ob_get_contents();
		} finally {
			ob_end_clean();
		}
	}

	public function testMalformedTargetsAreRejectedBeforeAnyWageSlipDatabaseOperation(): void {
		$this->boot();
		foreach (array("52' or id='53", '52abc', '1e2', '-1', '52.0', array('52'), '2147483648') as $id) {
			foreach (array('gem', 'afslut', 'godkend', 'afvis', 'slet') as $action) {
				$this->request(array('id' => $id, $action => 'Submit'));
				self::assertSame(400, http_response_code());
				self::assertSame(array(), WageSlipHarness::$queries);
				self::assertSame(array(), WageSlipHarness::$writes);
			}
		}
		$_GET['id'] = "52' or id='53";
		$this->request(array());
		self::assertSame(400, http_response_code());
	}

	public function testConflictingActionsCannotSelectADifferentAuthorizationPath(): void {
		$this->boot();
		$this->request(array('id' => '52', 'gem' => 'Gem', 'slet' => 'Slet'));
		self::assertSame(400, http_response_code());
		self::assertSame(array(), WageSlipHarness::$queries);
		self::assertSame(array(), WageSlipHarness::$writes);
	}

	public function testOtherOwnersAndFinalizedSlipsCannotBeChanged(): void {
		$this->boot();
		foreach (array(array('oprettet_af' => 'another'), array('afsluttet' => '123'), array('godkendt' => '123'), array('afregnet' => '123'), array('afvist' => '123')) as $state) {
			WageSlipHarness::$slip = array_replace(WageSlipHarness::$slip, array('oprettet_af' => 'installer', 'afsluttet' => '', 'godkendt' => '', 'afregnet' => '', 'afvist' => ''), $state);
			foreach (array('gem', 'afslut', 'godkend', 'afvis', 'slet') as $action) {
				$this->request(array('id' => '52', $action => 'Submit', 'loendato' => '10-11-2026', 'oprettet_af' => 'installer', 'afsluttet' => '', 'afregnet' => ''));
				self::assertSame(403, http_response_code(), json_encode(array($state, $action)));
				self::assertSame(array(), WageSlipHarness::$writes);
			}
		}
	}

	public function testAnOwnerCanDeleteOnlyTheSubmittedOpenSlip(): void {
		$this->boot();
		$_GET['id'] = '53';
		$this->request(array('id' => '52', 'slet' => 'Slet'));
		self::assertSame(200, http_response_code());
		self::assertSame(array("delete from loen where id='52'", "delete from loen_enheder where loen_id='52'"), WageSlipHarness::$writes);
		self::assertSame(array('begin', 'commit'), WageSlipHarness::$transactions);
	}

	public function testApprovalUsesStoredCaseAndTaskAndRequiresTheApprovalRight(): void {
		$this->boot(array('afsluttet' => '123', 'oprettet_af' => 'another'));
		$GLOBALS['sag_rettigheder'] = '0000001';
		$this->request(array('id' => '52', 'godkend' => 'Godkend', 'sag_id' => '90', 'opg_id' => '91'));
		self::assertCount(3, WageSlipHarness::$writes);
		self::assertStringContainsString("master_id='52' and sag_id='7' and opg_id ='8'", WageSlipHarness::$writes[1]);
		self::assertStringContainsString("coalesce(afregnet,'')=''", WageSlipHarness::$writes[1]);
		self::assertStringNotContainsString("sag_id='90'", implode('\n', WageSlipHarness::$writes));
	}

	public function testEvenApproversCannotEditDeleteOrRejectPaidSlips(): void {
		$this->boot(array('afregnet' => '123', 'afsluttet' => '123', 'godkendt' => '123'));
		$GLOBALS['sag_rettigheder'] = '0000001';
		foreach (array('gem', 'afslut', 'godkend', 'afvis', 'slet') as $action) {
			$this->request(array('id' => '52', $action => 'Submit', 'loendato' => '10-11-2026', 'afvist_pga' => 'Reason'));
			self::assertSame(403, http_response_code());
			self::assertSame(array(), WageSlipHarness::$writes);
		}
	}

	public function testRejectionCopiesStoredRowsAndIgnoresForgedContent(): void {
		$this->boot(array('afsluttet' => '123', 'oprettet_af' => 'another'));
		$GLOBALS['sag_rettigheder'] = '0000001';
		$this->request(array('id' => '52', 'afvis' => 'Afvis', 'afvist_pga' => "Wrong person's hours", 'sum' => "0' where id='53' --", 'sag_id' => '90', 'opg_id' => '91'));
		self::assertCount(4, WageSlipHarness::$writes);
		self::assertStringContainsString("Wrong person''s hours", WageSlipHarness::$writes[0]);
		self::assertStringContainsString("from loen where id='52'", WageSlipHarness::$writes[1]);
		self::assertStringContainsString("from loen_enheder where loen_id='52'", WageSlipHarness::$writes[2]);
		self::assertStringNotContainsString("where id='53'", implode('\n', WageSlipHarness::$writes));
	}

	public function testAConcurrentCaseMoveIsRefusedBeforeAnyWrite(): void {
		$this->boot(array('sag_id' => 9));
		WageSlipHarness::$initialCase = 7;
		$this->request(array('id' => '52', 'slet' => 'Slet'));
		self::assertSame(403, http_response_code());
		self::assertSame(array(), WageSlipHarness::$writes);
		self::assertStringContainsString("sager where id='7' for update", WageSlipHarness::$queries[1]);
	}

	/** @return array<string, mixed> A complete empty draft form, as submitted by Gem. */
	private function saveForm(): array {
		$form = array('id' => '52', 'gem' => 'Gem', 'gemt' => '1', 'loen_art' => 'timer', 'loendato' => '10-11-2026', 'loen_tekst' => "Installer's draft", 'loen_nr' => '12', 'sum' => '12', 'tr_antal' => '0', 'telt_antal' => '0');
		foreach (array('medarb_nr', 'medarb_navn', 'ansat_id', 'loen_date', 'hourType', 'a_id', 'a_stk', 'a_txt', 'a_pris', 'vare_tekst', 'op', 'ned', 'enhed_id') as $key) {
			$form[$key] = array();
		}
		return $form;
	}

	public function testAnOwnerCanSaveButCannotInjectAnotherTargetThroughSum(): void {
		$this->boot();
		WageSlipHarness::$stopBeforeRender = true;
		$form = $this->saveForm();
		$form['sum'] = "0' where id='53'; --";
		try {
			$this->request($form);
			self::fail('The draft did not reach rendering');
		} catch (WageSlipRenderReached $render) {
			self::assertCount(1, WageSlipHarness::$writes);
			self::assertStringContainsString("sum='0'", WageSlipHarness::$writes[0]);
			self::assertStringContainsString("tekst='Installer''s draft'", WageSlipHarness::$writes[0]);
			self::assertStringEndsWith("where id='52'", WageSlipHarness::$writes[0]);
			self::assertStringNotContainsString("id='53'", WageSlipHarness::$writes[0]);
		}
	}

	public function testPieceworkWritesRemainBoundToTheAuthorizedSlipWithQuotedText(): void {
		$this->boot();
		WageSlipHarness::$stopBeforeRender = true;
		$form = array_replace($this->saveForm(), array('loen_art' => 'akkord', 'vare_id' => array('5'), 'enhed_id' => array('99'), 'op' => array('1'), 'ned' => array('0'), 'vare_tekst' => array("forged' where id='99'; --"), 'varenr' => array("item's code"), 'pris_op' => array('1'), 'pris_ned' => array('0')));
		foreach (array('op_25', 'ned_25', 'op_30', 'ned_30', 'op_40', 'ned_40', 'op_60', 'ned_60', 'op_70', 'ned_70', 'op_100', 'ned_100', 'op_160', 'ned_160', 'op_30m', 'ned_30m') as $field) {
			$form[$field] = array('0');
		}
		try {
			$this->request($form);
			self::fail('The draft did not reach rendering');
		} catch (WageSlipRenderReached $render) {
			self::assertCount(2, WageSlipHarness::$writes);
			self::assertStringContainsString("tekst='forged'' where id=''99''; --'", WageSlipHarness::$writes[1]);
			self::assertStringEndsWith("where id='99' and loen_id='52'", WageSlipHarness::$writes[1]);
		}
	}

	public function testMalformedChildIdsAreRefusedBeforeTheSaveStarts(): void {
		$this->boot();
		foreach (array('enhed_id', 'a_id', 'tr_id', 'telt_id') as $field) {
			$value = "5' or id='99";
			$form = $this->saveForm();
			$form[$field] = in_array($field, array('enhed_id', 'a_id'), true) ? array($value) : $value;
			$this->request($form);
			self::assertSame(400, http_response_code());
			self::assertSame(array(), WageSlipHarness::$queries);
		}
	}
}
