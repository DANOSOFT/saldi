<?php

declare(strict_types=1);

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

/**
 * The item import (systemdata/importer_varer.php) reading a campaign sheet into a real tenant: the page runs as a
 * child process (support/run_importer_varer_page.php) with the form its Importer button posts.
 *
 * Pins what a campaign-only file writes (special_price ex moms by the item group's VAT, the offer period with whole-day
 * defaults, specialtype 'price') and what it must leave alone (normal and cost price, group, item number, cost price
 * history, other items), plus the line checks from the Codex review: short lines, columns mapped twice, ambiguous
 * item numbers, a byte order mark and the header line.
 *
 * History:
 * 20261117 CL/LH Created (Havemøbelland campaign import, Codex review round 1).
 * 20261117 CL/LH Codex review 2: a headerless ordinary file with a label-like item number, a quoted first cell, a new item twice, a bad cost price and 777.0.
 */
final class ImporterVarerCampaignTest extends CharacterizationTestCase
{
	private const CAMPAIGN_COLUMNS = ['special_price', 'special_from_date', 'special_to_date', 'special_from_time', 'special_to_time', 'specialtype'];

	/** @var list<string> */
	private static array $files = [];

	public static function tearDownAfterClass(): void
	{
		foreach (self::$files as $file) {
			@unlink($file);
		}
		parent::tearDownAfterClass();
	}

	/** An item group with a VAT-coded sales account, and its rate by the chain find_pris() uses. */
	private static function vatGroup(): array
	{
		$row = self::one(
			"SELECT g.kodenr, k.moms FROM grupper g JOIN kontoplan k ON k.kontonr::text = g.box4 AND k.regnskabsaar = g.fiscal_year
			 WHERE g.art = 'VG' AND g.fiscal_year = $1 AND COALESCE(g.box7, '') = '' AND COALESCE(k.moms, '') <> ''
			 ORDER BY g.kodenr::int LIMIT 1",
			[self::$regnaar]
		);
		self::assertNotNull($row, 'the installed tenant has an item group with a VAT-coded sales account');
		$rate = self::one(
			"SELECT box2 FROM grupper WHERE art = $1 AND kodenr = $2 AND fiscal_year = $3",
			[substr($row['moms'], 0, 1) . 'M', substr($row['moms'], 1), self::$regnaar]
		);
		return ['gruppe' => (int)$row['kodenr'], 'rate' => (float)$rate['box2']];
	}

	/** A VAT-free copy (box7 on) of the VAT group. */
	private static function vatFreeGroup(int $from): int
	{
		$kodenr = 97;
		if (self::one("SELECT 1 FROM grupper WHERE art = 'VG' AND kodenr = '97' AND fiscal_year = $1", [self::$regnaar]) === null) {
			self::rows(
				"INSERT INTO grupper (id, beskrivelse, kodenr, art, box4, box7, fiscal_year)
				 SELECT $1, 'Momsfri test', '97', 'VG', box4, 'on', fiscal_year FROM grupper WHERE art = 'VG' AND kodenr = $2 AND fiscal_year = $3",
				[CharacterizationEnv::nextId(self::$tenant, 'grupper'), (string)$from, self::$regnaar]
			);
		}
		return $kodenr;
	}

	private static function item(string $varenr, int $gruppe, float $salgspris, ?string $specialtype = 'price', string $beskrivelse = 'Testvare'): int
	{
		$row = self::one(
			"INSERT INTO varer (id, varenr, beskrivelse, gruppe, salgspris, kostpris, lukket, specialtype, special_price,
			                    special_from_date, special_to_date, special_from_time, special_to_time)
			 VALUES ($1, $2, $3, $4, $5, $6, '', $7, 0, '2024-01-01', '2024-01-01', '00:00:00', '00:00:00') RETURNING id",
			[CharacterizationEnv::nextId(self::$tenant, 'varer'), $varenr, $beskrivelse, $gruppe, $salgspris, round($salgspris / 2, 3), $specialtype]
		);
		return (int)$row['id'];
	}

	/** @param list<string> $lines */
	private static function csv(array $lines, string $prefix = ''): string
	{
		$file = tempnam(sys_get_temp_dir(), 'vareimp');
		file_put_contents($file, $prefix . implode("\r\n", $lines) . "\r\n");
		chmod($file, 0644);
		self::$files[] = $file;
		return $file;
	}

	/** @param list<string> $mapping */
	private static function import(string $csv, array $mapping, string $filter = 'all_products', string $tegnset = 'UTF-8'): string
	{
		CharacterizationEnv::resyncSequences(self::$tenant); // the fixtures insert explicit ids; the page's own inserts use the sequences
		$res = self::runChild('run_importer_varer_page.php', [$csv, CharacterizationEnv::sessionId(), implode(',', $mapping), $filter, $tegnset]);
		self::assertStringContainsString('CHARTEST_PAGE_DONE', $res['stdout'], "the page ran to the end\nstderr: {$res['stderr']}");
		self::assertStringNotContainsString('itemImport.php', $res['stderr'], 'no warning from the shared import helpers');
		self::assertStringNotContainsString('Query failed', $res['stderr'], 'every statement the import sent succeeded');
		$text = str_replace(['</td>', '</th>', '<br>'], [' | ', ' | ', ' '], $res['stdout']);
		return html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
	}

	/** @param list<string> $varenumre @return array<string, array<string, string|null>> varenr => row */
	private static function items(array $varenumre): array
	{
		$out = [];
		foreach (self::rows('SELECT * FROM varer WHERE varenr = ANY($1::text[]) ORDER BY varenr', ['{' . implode(',', array_map(fn($v) => '"' . $v . '"', $varenumre)) . '}']) as $row) {
			$out[$row['varenr']] = $row;
		}
		return $out;
	}

	/** A row without the campaign columns and the modtime a trigger stamps on every update, for "nothing else changed". */
	private static function withoutCampaign(array $row): array
	{
		return array_diff_key($row, array_flip(array_merge(self::CAMPAIGN_COLUMNS, ['modtime'])));
	}

	public function testACampaignSheetWritesOfferPricesAndNothingElse(): void
	{
		$vat = self::vatGroup();
		self::assertSame(25.0, $vat['rate'], 'the standard seed has 25 % sales VAT');
		$free = self::vatFreeGroup($vat['gruppe']);
		$a = self::item('CA1', $vat['gruppe'], 639.2);
		self::item('CA2', $vat['gruppe'], 399.2, null);
		self::item('CA3', $free, 500.0);
		self::item('000124', $vat['gruppe'], 800.0, null);
		self::item('CA5', $vat['gruppe'], 100.0, 'price', 'Not in the sheet');
		self::rows("INSERT INTO kostpriser (id, vare_id, kostpris, transdate) VALUES ($1, $2, 319.6, '2026-01-01')", [CharacterizationEnv::nextId(self::$tenant, 'kostpriser'), $a]);
		$all = ['CA1', 'CA2', 'CA3', '000124', 'CA5'];
		$before = self::items($all);
		$itemCount = self::one('SELECT count(*) AS n FROM varer')['n'];
		$costRows = self::rows('SELECT * FROM kostpriser ORDER BY id');

		$csv = self::csv([
			'Tilbudspris inkl. moms;Start;Slut;Varenr',
			'1.499,95;27-11-2026;30-11-2026;CA1',
			'199,00 kr.;17-11-2026 08:00;20.11.2026 20:00;CA2', // line 3 is full: the page counts the columns there
			'99,95;30-11-2026',                                // short: no item number, must not hit CA2 above it
			"999,00;2026-11-15;2026-11-25;CA3",
			'999;10/11/2026;17/11/2026 23:59;124',             // Excel dropped the zeros of 000124
			'149,95;27-11-2026;30-11-2026;NOPE',               // unknown
			'149,95;27-11-2026;31-11-2026;CA5',                // bad date
		], "\xEF\xBB\xBF");
		$page = self::import($csv, ['special_price_incl_moms', 'special_from_date', 'special_to_date', 'varenr']);

		$after = self::items($all);
		foreach ($all as $v) {
			self::assertSame(self::withoutCampaign($before[$v]), self::withoutCampaign($after[$v]), "$v: only campaign columns may change");
		}
		self::assertSame(self::withoutCampaign($before['CA5']) + array_intersect_key($before['CA5'], array_flip(self::CAMPAIGN_COLUMNS)),
			self::withoutCampaign($after['CA5']) + array_intersect_key($after['CA5'], array_flip(self::CAMPAIGN_COLUMNS)), 'the line with a bad date wrote nothing');
		self::assertSame(
			['special_price' => '1199.960', 'special_from_date' => '2026-11-27', 'special_to_date' => '2026-11-30',
			 'special_from_time' => '00:00:00', 'special_to_time' => '23:59:59', 'specialtype' => 'price'],
			array_intersect_key($after['CA1'], array_flip(self::CAMPAIGN_COLUMNS)),
			'CA1: ex moms, whole days'
		);
		self::assertSame(['159.200', '2026-11-17', '08:00:00', '2026-11-20', '20:00:00', 'price'],
			[$after['CA2']['special_price'], $after['CA2']['special_from_date'], $after['CA2']['special_from_time'],
			 $after['CA2']['special_to_date'], $after['CA2']['special_to_time'], $after['CA2']['specialtype']],
			'CA2: times from the date cells; specialtype NULL becomes price; the short line after it did not overwrite it');
		self::assertSame('999.000', $after['CA3']['special_price'], 'VAT-free group: the sheet price as it is');
		self::assertSame(['799.200', '23:59:59', 'price'],
			[$after['000124']['special_price'], $after['000124']['special_to_time'], $after['000124']['specialtype']],
			'124 found 000124, which keeps its item number');
		self::assertSame($itemCount, self::one('SELECT count(*) AS n FROM varer')['n'], 'no item created');
		self::assertSame($costRows, self::rows('SELECT * FROM kostpriser ORDER BY id'), 'cost price history untouched');

		self::assertStringContainsString('0 varer importeret, 4 varer opdateret', $page);
		self::assertMatchesRegularExpression('/findes ikke og er ikke oprettet:\s*NOPE/', $page);
		self::assertMatchesRegularExpression('/4 \|\s*\|\s*special_to_date \|/', $page, 'line 4 (short) is listed with its first missing column');
		self::assertMatchesRegularExpression('/8 \| CA5 \| special_to_date \| 31-11-2026 \|/', $page);
		self::assertStringNotContainsString('Tilbudspris inkl. moms', $page, 'the header line is not listed');
	}

	public function testAColumnMappedTwiceIsNotImported(): void
	{
		$vat = self::vatGroup();
		self::item('DUP1', $vat['gruppe'], 100.0);
		$before = self::items(['DUP1']);
		$csv = self::csv(['DUP1;garbage;149,95']);
		$page = self::import($csv, ['varenr', 'special_price', 'special_price']);
		self::assertSame($before, self::items(['DUP1']), 'nothing written');
		self::assertStringNotContainsString('varer opdateret', $page, 'the import did not run');
		$page = self::import($csv, ['varenr', 'special_price', 'special_price_incl_moms']);
		self::assertSame($before, self::items(['DUP1']), 'two fields that write one column count as a duplicate');
	}

	public function testAnAmbiguousItemNumberIsSkippedAndAnExactOneIsUsed(): void
	{
		$vat = self::vatGroup();
		$upper = self::item('AMB', $vat['gruppe'], 100.0);
		self::item('amb', $vat['gruppe'], 100.0);
		self::item('0042', $vat['gruppe'], 100.0);
		self::item('042', $vat['gruppe'], 100.0);
		$page = self::import(self::csv(['Amb;125,00', 'AMB;100,00', '42;50,00'], "\xEF\xBB\xBF"), ['varenr', 'special_price_incl_moms'], 'only_existing_product');
		$after = self::items(['AMB', 'amb', '0042', '042']);
		self::assertSame('80.000', $after['AMB']['special_price'], 'exact match');
		self::assertSame('0.000', $after['amb']['special_price']);
		self::assertSame('0.000', $after['0042']['special_price']);
		self::assertSame('0.000', $after['042']['special_price']);
		self::assertMatchesRegularExpression('/passer på flere varer og er ikke opdateret:\s*Amb, 42/', $page);
		self::assertSame($upper, (int)self::one("SELECT id FROM varer WHERE special_price = 80 AND varenr ILIKE 'amb'")['id']);
	}

	public function testAnOrdinaryHeaderlessImportKeepsEveryValidLine(): void
	{
		$vat = self::vatGroup();
		self::item('ORD4', $vat['gruppe'], 100.0, 'price', 'Old text 4');
		self::item('ORD5', $vat['gruppe'], 100.0, 'price', 'Old text 5');
		self::item('000777', $vat['gruppe'], 100.0, 'price', 'Old text 777');
		$page = self::import(self::csv([
			'SKU123;Garden chair;10,00',          // line 1 looks like a label but is an item number: created
			'"ORD4";"Quoted; text";20,00',        // a quoted first cell
			'NEW1;First;30,00',                   // created ...
			'NEW1;Second;31,00',                  // ... and then updated, not created twice
			'ORD5;Bad cost;abc',                  // the number check rejects it: listed
			'777.0;Numeric;40,00',                // finds 000777
		]), ['varenr', 'beskrivelse', 'kostpris']);
		self::assertSame('Garden chair', self::one("SELECT beskrivelse FROM varer WHERE varenr = 'SKU123'")['beskrivelse'] ?? null);
		$after = self::items(['ORD4', 'ORD5', '000777']);
		self::assertSame('Quoted; text', $after['ORD4']['beskrivelse']);
		self::assertSame('Old text 5', $after['ORD5']['beskrivelse']);
		self::assertSame('Numeric', $after['000777']['beskrivelse'], '777.0 found 000777, which keeps its item number');
		self::assertSame([['beskrivelse' => 'Second']], self::rows("SELECT beskrivelse FROM varer WHERE varenr = 'NEW1'"));
		self::assertNull(self::one("SELECT id FROM varer WHERE varenr = '777.0'"));
		self::assertStringContainsString('2 varer importeret, 3 varer opdateret', $page);
		self::assertMatchesRegularExpression('/5 \| ORD5 \| kostpris \| abc \|/', $page);
	}

	public function testAnOrdinaryImportSkipsShortLinesAndKeepsCostHistory(): void
	{
		$vat = self::vatGroup();
		$e = self::item('ORD1', $vat['gruppe'], 100.0, 'price', 'Old text 1');
		self::item('ORD2', $vat['gruppe'], 100.0, 'price', 'Old text 2');
		self::item('ORD3', $vat['gruppe'], 100.0, 'price', 'Old text 3');
		self::rows("INSERT INTO kostpriser (id, vare_id, kostpris, transdate) VALUES ($1, $2, 50, '2026-01-01')", [CharacterizationEnv::nextId(self::$tenant, 'kostpriser'), $e]);
		$costRows = self::rows('SELECT * FROM kostpriser WHERE vare_id = $1', [$e]);
		$page = self::import(
			self::csv(['Varenr;Beskrivelse;Salgspris', 'ORD1;New text 1;120,00', 'ORD3;New text 3;130,00', 'ORD2;New text 2']),
			['varenr', 'beskrivelse', 'salgspris_excl_moms']
		);
		$after = self::items(['ORD1', 'ORD2']);
		self::assertSame(['New text 1', '120.000'], [$after['ORD1']['beskrivelse'], $after['ORD1']['salgspris']]);
		self::assertSame(['Old text 2', '100.000'], [$after['ORD2']['beskrivelse'], $after['ORD2']['salgspris']], 'the short line wrote nothing');
		self::assertSame($costRows, self::rows('SELECT * FROM kostpriser WHERE vare_id = $1', [$e]), 'no 0 cost price row without a kostpris column');
		self::assertNull(self::one("SELECT id FROM varer WHERE varenr = 'Varenr'"), 'the header line did not become an item');
		self::assertMatchesRegularExpression('/4 \| ORD2 \| salgspris_excl_moms \|/', $page);
	}
}
