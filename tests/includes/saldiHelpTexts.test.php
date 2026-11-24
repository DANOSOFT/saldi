<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- tests/includes/saldiHelpTexts.test.php --- ver 5.1.1 --- 2026-11-07 ---
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
// 20261107 CL/ASR Created: the key collection behind index/helpTexts.php (includes/saldi_help_texts.php).
//                 Run: php vendor/bin/phpunit tests/includes/saldiHelpTexts.test.php
// 20261107 CL/ASR Review: saldi_help_text_pick(), the row rules of the one-query prefetch.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/saldi_help_texts.php';

final class saldiHelpTexts extends TestCase
{
    public function testKeysOfTheGroupAndCommonInFileOrder(): void
    {
        $standard = "common.accept\tAcceptér\tAccept\tGodta\n"
            . "help.common.shortcuts_note\tA\tB\tC\n"
            . "help.kk.about.ny\tA\tB\tC\n"
            . "\n"
            . "help.kkx.name\tA\tB\tC\n"
            . "help.kl.name\tA\tB\tC\r\n"
            . "help.kk.name\tA\tB\tC\r\n"
            . "helpx.kk.name\tA\tB\tC\n";
        self::assertSame(
            array('help.common.shortcuts_note', 'help.kk.about.ny', 'help.kk.name'),
            saldi_help_text_keys('', $standard, 'kk')
        );
        self::assertSame(array('help.common.shortcuts_note', 'help.kl.name'), saldi_help_text_keys('', $standard, 'kl'));
        self::assertSame(array('help.common.shortcuts_note'), saldi_help_text_keys('', $standard, 'zz'));
        self::assertSame(array(), saldi_help_text_keys('', '', 'kk'));
    }

    public function testACustomKeyComesFirstAndOnlyOnce(): void
    {
        $custom = "help.kk.name\tEget navn %s\tOwn name %s\tEget navn %s\n";
        $standard = "help.kk.about.ny\tA\tB\tC\nhelp.kk.name\tKassekladde %s\tCash journal %s\tKontantjournal %s\n";
        self::assertSame(array('help.kk.name', 'help.kk.about.ny'), saldi_help_text_keys($custom, $standard, 'kk'));
    }

    public function testTheRealSeedFileHasBothPilotGroups(): void
    {
        $tsv = (string) file_get_contents(__DIR__ . '/../../importfiler/tekster_keyed.tsv');
        foreach (array('kl' => 50, 'kk' => 150) as $group => $atLeast) {
            $keys = saldi_help_text_keys('', $tsv, $group);
            self::assertGreaterThan($atLeast, count($keys), $group);
            self::assertContains('help.common.shortcuts_note', $keys);
            self::assertContains('help.' . $group . '.name', $keys);
            foreach ($keys as $key) {
                self::assertMatchesRegularExpression('/^help\.(common|' . $group . ')\.[a-z0-9_.]+$/', $key);
            }
        }
    }

    public function testOnlyTheFirstUsableRowPerKeyIsPicked(): void
    {
        $rows = array(
            array('tekst_key' => 'help.kk.name', 'tekst' => 'Kassekladde %s'),
            array('tekst_key' => 'help.kk.name', 'tekst' => 'Second row'),          // a duplicate: the first row wins
            array('tekst_key' => 'help.kk.about.ny', 'tekst' => 'help.kk.about.ny'), // a placeholder: left to findtekst()
            array('tekst_key' => 'help.kk.about.ny', 'tekst' => 'Later text'),       // not used: the first row was the placeholder
            array('tekst_key' => 'help.kk.key.01', 'tekst' => '-'),                  // '-' is findtekst()'s own case
            array('tekst_key' => 'help.kk.key.02', 'tekst' => ''),
            array('tekst_key' => 'help.kk.key.03', 'tekst' => null),
            array('tekst_key' => '', 'tekst' => 'no key'),
            array('tekst_key' => 'help.common.shortcuts_note', 'tekst' => 'Alt-genvejene'),
        );
        self::assertSame(
            array('help.kk.name' => 'Kassekladde %s', 'help.common.shortcuts_note' => 'Alt-genvejene'),
            saldi_help_text_pick($rows)
        );
        self::assertSame(array(), saldi_help_text_pick(array()));
    }

    public function testAMalformedOrUnknownGroupResolvesNothing(): void
    {
        // Both return before any text is looked up, so no database is needed here.
        self::assertSame(array(), saldi_help_texts('KK', 1));
        self::assertSame(array(), saldi_help_texts('k', 1));
        self::assertSame(array(), saldi_help_texts('kk/../x', 1));
        self::assertSame(array(), saldi_help_texts('common', 1), 'help.common.* alone is no group');
        self::assertSame(array(), saldi_help_texts('zz', 1));
    }
}
