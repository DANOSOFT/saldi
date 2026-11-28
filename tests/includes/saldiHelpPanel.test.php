<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- tests/includes/saldiHelpPanel.test.php --- ver 5.1.1 --- 2026-11-24 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261107 CL/ASR Created: the help panel's pilot switch (SALDI_HELP_PANEL with the tenant row help/panel) and the "Skriv til os" recipient (includes/saldi_help_panel.php).
// 20261107 CL/ASR Review round 2: saldi_help_chrome_pick(), the row rules of the chrome text prefetch, and saldi_help_chrome_ids() against topbar_help_host().
// 20261115 CL/ASR Integration: the no-database prefetch test runs in its own process; lab and master tests now load the db layer into the shared PHPUnit process first.
// 20261124 CL/ASR Fictive fixture names and numbers; no change to what the test checks.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/saldi_help_panel.php';

final class saldiHelpPanel extends TestCase
{
	/** @var array<string, string|false> environment values before each test, restored after it */
	private array $env = array();

	protected function setUp(): void
	{
		foreach (array('SALDI_HELP_SUPPORT_TO') as $name) {
			$this->env[$name] = getenv($name);
		}
		unset($GLOBALS['helpSupportTo']);
	}

	protected function tearDown(): void
	{
		foreach ($this->env as $name => $value) {
			putenv($value === false ? $name : $name . '=' . $value);
		}
		unset($GLOBALS['helpSupportTo']);
	}

	/**
	 * @return array<string, array{string, ?string, bool}>
	 */
	public static function modes(): array
	{
		return array(
			'unset without a row keeps the menu'   => array('', null, false),
			'unset with on shows the panel'        => array('', 'on', true),
			'unset with off keeps the menu'        => array('', 'off', false),
			'pilot with on shows the panel'        => array('pilot', 'on', true),
			'pilot without a row keeps the menu'   => array('pilot', null, false),
			'pilot with off keeps the menu'        => array('pilot', 'off', false),
			'on without a row shows the panel'     => array('on', null, true),
			'on with off keeps the menu'           => array('on', 'off', false),
			'off keeps the menu even with on'      => array('off', 'on', false),
			'upper-case ON is unknown, menu stays' => array('ON', 'on', false),
			'Pilot is unknown, menu stays'         => array('Pilot', 'on', false),
		);
	}

	#[DataProvider('modes')]
	public function testPanelPerMode(string $mode, ?string $tenantSetting, bool $expected): void
	{
		self::assertSame($expected, saldi_help_panel_enabled($mode, $tenantSetting));
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public static function tenantModes(): array
	{
		return array(
			'unset reads the row' => array('', true),
			'pilot reads the row' => array('pilot', true),
			'on reads the row'    => array('on', true),
			'off needs no row'    => array('off', false),
		);
	}

	#[DataProvider('tenantModes')]
	public function testTenantRowIsReadOnlyWhenItMatters(string $mode, bool $expected): void
	{
		self::assertSame($expected, saldi_help_panel_needs_tenant($mode));
	}

	public function testSupportRecipientFromTheEnvironment(): void
	{
		putenv('SALDI_HELP_SUPPORT_TO= support@saldi.dk ');
		self::assertSame('support@saldi.dk', saldi_help_support_to());
	}

	public function testInvalidSupportRecipientSwitchesTheFormOff(): void
	{
		putenv('SALDI_HELP_SUPPORT_TO=not an address');
		self::assertSame('', saldi_help_support_to());
	}

	public function testNoSupportRecipientWhenNothingIsSet(): void
	{
		putenv('SALDI_HELP_SUPPORT_TO');
		self::assertSame('', saldi_help_support_to());
	}

	public function testSupportRecipientFromConnectWhenTheEnvironmentIsUnset(): void
	{
		putenv('SALDI_HELP_SUPPORT_TO');
		$GLOBALS['helpSupportTo'] = 'ann@example.com';
		self::assertSame('ann@example.com', saldi_help_support_to());
	}

	public function testErrorReportRecipientIsNeverUsed(): void
	{
		putenv('SALDI_HELP_SUPPORT_TO');
		$GLOBALS['errorReportTo'] = 'support@danosoft.dk';
		try {
			self::assertSame('', saldi_help_support_to());
		} finally {
			unset($GLOBALS['errorReportTo']);
		}
	}

	public function testOnlyTheFirstUsableRowPerChromeIdIsPicked(): void
	{
		$rows = array(
			array('tekst_id' => '8700', 'tekst' => 'Hjælp og Saldi AI'),
			array('tekst_id' => '8700', 'tekst' => 'Second row'),   // a duplicate: the first row wins, as findtekst() reads it
			array('tekst_id' => 8703, 'tekst' => 'Om siden'),       // an integer column comes back as int on some drivers
			array('tekst_id' => '8704', 'tekst' => ''),             // empty: findtekst() seeds it from tekster.csv
			array('tekst_id' => '8704', 'tekst' => 'Later text'),   // not used: the first row was empty
			array('tekst_id' => '8705', 'tekst' => '-'),            // '-' is findtekst()'s own case
			array('tekst_id' => '8706', 'tekst' => null),
			array('tekst_id' => '', 'tekst' => 'no id'),
			array('tekst_id' => 'help.kk.name', 'tekst' => 'a key, not an id'),
			array('tekst_id' => '92', 'tekst' => 'Vejledning'),
		);
		self::assertSame(
			array('8700' => 'Hjælp og Saldi AI', '8703' => 'Om siden', '92' => 'Vejledning'),
			saldi_help_chrome_pick($rows)
		);
		self::assertSame(array(), saldi_help_chrome_pick(array()));
	}

	public function testThePrefetchListMatchesTheTextsOfTheHelpHost(): void
	{
		$src = (string) file_get_contents(__DIR__ . '/../../index/mainIncludes/topbar.php');
		$start = strpos($src, 'function topbar_help_host(');
		self::assertNotFalse($start);
		$body = substr($src, (int) $start);
		preg_match_all("/'(\\d+)' => \\\$f\\(findtekst\\('(\\d+)\\|/", $body, $m, PREG_SET_ORDER);
		self::assertGreaterThan(70, count($m));
		$ids = array();
		foreach ($m as $hit) {
			self::assertSame($hit[1], $hit[2], 'the key is the id it reads');
			$ids[] = (int) $hit[1];
		}
		$list = saldi_help_chrome_ids();
		sort($ids);
		sort($list);
		self::assertSame($ids, $list);
	}

	// In its own process: in a full run other classes load includes/db_query.php and the findtekst functions first.
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testThePrefetchNeedsADatabaseAndNeverRunsWithoutOne(): void
	{
		// No db functions in the unit test: it returns before any query, and no session key is written.
		$_SESSION = array();
		saldi_help_chrome_prefetch(saldi_help_chrome_ids(), 1);
		self::assertSame(array(), $_SESSION);
	}
}
