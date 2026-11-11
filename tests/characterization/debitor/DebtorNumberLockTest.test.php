<?php
// tests/characterization/debitor/DebtorNumberLockTest.test.php
//
// SD-800: a new debtor saved on a card while the Shopify worker is creating one gets a
// different number. The worker (create_debtor() / allocate_debtor_number()) chooses and
// inserts its number under pg_advisory_xact_lock(hashtext('saldi:debtor_number')), held
// until it commits. The test holds that lock with an uncommitted debtor on the first free
// number, saves a new debtor with an empty number on the real card page in a child process,
// then commits. A card that doesn't wait for the lock reads the free numbers without the
// worker's debtor and takes the same number.
//
// Two cards: the Debitorkort (debitor/debitorkort.php, its own save) and the Sager customer
// card (sager/kunder.php, which includes debitor/debkort_save.php). Data is synthetic.
// Requires the docker-compose stack (see README) - skips cleanly otherwise.
//
// History:
// 20261111 CL/SZ Created (SD-800).

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

use PHPUnit\Framework\Attributes\DataProvider;

final class DebtorNumberLockTest extends CharacterizationTestCase
{
	private const PREFIX = 'SD800 ';

	protected function setUp(): void
	{
		self::rows("DELETE FROM adresser WHERE art = 'D' AND firmanavn LIKE 'SD800 %'");
	}

	protected function tearDown(): void
	{
		self::rows("DELETE FROM adresser WHERE art = 'D' AND firmanavn LIKE 'SD800 %'");
	}

	/** The page, its query string and the fields the browser posts for a new debtor with an empty number. */
	public static function cards(): array
	{
		$debtor = ['id' => '0', 'firmanavn' => self::PREFIX . 'card', 'ny_kontonr' => '', 'gruppe' => '1', 'betalingsdage' => '8'];
		// Every field the Debitorkort form posts for a new company debtor; the page reads them all.
		$blank = array_fill_keys(['felt_1', 'felt_2', 'felt_3', 'felt_4', 'felt_5', 'kontonr', 'fokus', 'kontakt', 'pbs_date',
			'addr1', 'addr2', 'postnr', 'bynavn', 'land', 'web', 'betalingsbet', 'cvrnr', 'tlf', 'mobile', 'ean', 'institution',
			'kreditmax', 'bank_reg', 'bank_konto', 'swift', 'kontoansvarlig', 'status', 'lev_firmanavn', 'lev_addr1', 'lev_addr2',
			'lev_postnr', 'lev_bynavn', 'lev_land', 'lev_kontakt', 'lev_tlf', 'newCatName', 'notes'], '');
		$debitorkort = $debtor + $blank + ['ordre_id' => '0', 'returside' => 'debitor.php', 'gl_kontotype' => 'privat',
			'kontotype' => 'erhverv', 'vis_lev_addr' => 'on', 'kontakt_email_val' => [1 => ''], 'kontakt_email_id' => [1 => '0'],
			'kontakt_email_type' => [1 => 'hoved'], 'kontakt_emails_json' => '[]', 'status_id' => [0 => ''],
			'status_beskrivelse' => [0 => ''], 'delivery_addresses_json' => '[]', 'productlimit' => '0', 'ans_ant' => '0',
			'submit' => 'Gem/opdatér'];
		return [
			'Debitorkort' => ['debitor/debitorkort.php', [], $debitorkort],
			'Sager customer card' => ['sager/kunder.php', ['funktion' => 'ret_kunde'],
				$debtor + ['gem_rettelse' => '1', 'submit' => 'Gem']],
		];
	}

	#[DataProvider('cards')]
	public function testACardSaveWhileTheWorkerHoldsTheNumberLockGetsAnotherNumber(string $page, array $get, array $post): void
	{
		$worker = CharacterizationEnv::connect(CharacterizationEnv::testDb());
		$output = (string)tempnam(sys_get_temp_dir(), 'sd800-card');
		try {
			// The worker's side of create_debtor(): the lock, the first free number, the insert, no commit yet.
			pg_query($worker, 'BEGIN');
			pg_query($worker, "SELECT pg_advisory_xact_lock(hashtext('saldi:debtor_number'))");
			$used = array_column(self::rows("SELECT kontonr FROM adresser WHERE art = 'D'"), 'kontonr');
			for ($free = 1000; in_array((string)$free, $used, true); $free++);
			pg_query_params($worker, "INSERT INTO adresser (kontonr, firmanavn, art, gruppe) VALUES ($1, $2, 'D', 1)",
				[(string)$free, self::PREFIX . 'worker']);

			$card = $this->startCard($page, $get, $post, $output);
			// A card that doesn't wait is done within a few seconds; one that waits stays blocked.
			$finishedBeforeCommit = $this->waitFor($card['proc'], 8.0);
			pg_query($worker, 'COMMIT');
			$this->waitFor($card['proc'], 30.0);
			proc_close($card['proc']);
		} finally {
			pg_close($worker);
		}

		$rows = self::rows("SELECT kontonr, firmanavn FROM adresser WHERE art = 'D' AND firmanavn LIKE 'SD800 %' ORDER BY id");
		$page_output = substr(strip_tags((string)@file_get_contents($output)), 0, 400);
		@unlink($output);
		$this->assertCount(2, $rows, "$page saved no debtor. Page output: $page_output");
		$this->assertNotSame($rows[0]['kontonr'], $rows[1]['kontonr'],
			"$page took number {$rows[1]['kontonr']} while the worker held it uncommitted"
			. ($finishedBeforeCommit ? ' (the save did not wait for the lock)' : ''));
	}

	/** @return array{proc: resource} the card page running in a child process, not waited for */
	private function startCard(string $page, array $get, array $post, string $output): array
	{
		$runner = __DIR__ . '/../support/run_legacy_shop_page.php';
		$cmd = [PHP_BINARY, $runner, $page, CharacterizationEnv::SESSION_ID, json_encode(['get' => $get, 'post' => $post]), $output];
		$proc = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
		$this->assertIsResource($proc, 'could not start the card page');
		return ['proc' => $proc];
	}

	/** Whether the child process exited within $seconds. */
	private function waitFor($proc, float $seconds): bool
	{
		$until = microtime(true) + $seconds;
		while (proc_get_status($proc)['running']) {
			if (microtime(true) >= $until) {
				return false;
			}
			usleep(100000);
		}
		return true;
	}
}
