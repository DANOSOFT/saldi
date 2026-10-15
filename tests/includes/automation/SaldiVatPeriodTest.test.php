<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiVatPeriodTest.test.php --- ai/mcp-assistant --- 2026.10.03
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// Isolated Watch v2 regression coverage using synthetic fixtures only.
//
// 20261002 CDX/LH Created.
// 20261003 CDX/LH Add regression coverage for the confirmed Assist review findings.

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/automation/SaldiVatPeriod.php';

final class SaldiVatPeriodTest extends PHPUnit\Framework\TestCase
{
    public function testEveryMonthlyDeadlineAndJuneException(): void
    {
        $expected = ['2026-02-25', '2026-03-25', '2026-04-27', '2026-05-25', '2026-06-25',
            '2026-08-17', '2026-08-25', '2026-09-25', '2026-10-26', '2026-11-25', '2026-12-25', '2027-01-25'];
        foreach ($expected as $index => $deadline) {
            $period = SaldiVatPeriod::period('month', sprintf('2026-%02d-15', $index + 1));
            self::assertSame($deadline, $period['deadline']);
            self::assertSame(sprintf('2026-%02d-01', $index + 1), $period['from']);
        }
        self::assertSame('2024-08-19', SaldiVatPeriod::period('month', '2024-06-15')['deadline']);
        self::assertSame('2026-02-28', SaldiVatPeriod::period('month', '2026-02-15')['to']);
        self::assertSame('2024-02-29', SaldiVatPeriod::period('month', '2024-02-15')['to']);
    }

    public function testQuarterAndHalfTablesIncludingWeekendShift(): void
    {
        foreach (
            [2026 => ['2026-06-01', '2026-09-01', '2026-12-01', '2027-03-01'],
            2024 => ['2024-06-03', '2024-09-02', '2024-12-02', '2025-03-03']] as $year => $deadlines
        ) {
            foreach ($deadlines as $index => $deadline) {
                $period = SaldiVatPeriod::period('quarter', sprintf('%d-%02d-10', $year, $index * 3 + 1));
                self::assertSame($deadline, $period['deadline']);
            }
            self::assertSame($deadlines[1], SaldiVatPeriod::period('half', $year . '-01-01')['deadline']);
            self::assertSame($deadlines[3], SaldiVatPeriod::period('half', $year . '-07-01')['deadline']);
        }
        self::assertSame('2026-06-30', SaldiVatPeriod::period('half', '2026-03-20')['to']);
    }

    public function testCurrentUsesLatestEndedUnexpiredPeriodOtherwiseRunningPeriod(): void
    {
        self::assertSame(['from' => '2026-07-01', 'to' => '2026-09-30', 'deadline' => '2026-12-01',
            'days_left' => 60], SaldiVatPeriod::current('quarter', '2026-10-02'));
        self::assertSame('2026-04-01', SaldiVatPeriod::current('quarter', '2026-09-01')['from']);
        self::assertSame('2026-07-01', SaldiVatPeriod::current('quarter', '2026-09-02')['from']);
        self::assertSame('2026-01-01', SaldiVatPeriod::current('month', '2026-02-25')['from']);
        self::assertSame('2026-02-01', SaldiVatPeriod::current('month', '2026-02-26')['from']);
        self::assertSame('2026-01-01', SaldiVatPeriod::current('half', '2026-09-01')['from']);
        self::assertSame('2026-07-01', SaldiVatPeriod::current('half', '2026-09-02')['from']);
        self::assertSame('2025-10-01', SaldiVatPeriod::current('quarter', '2026-01-01')['from']);
        self::assertSame('2026-07-01', SaldiVatPeriod::current('invalid-setting', '2026-10-02')['from']);
    }

    public function testImpossibleDateIsRejected(): void
    {
        $this->expectException(SaldiRuntimeError::class);
        SaldiVatPeriod::current('month', '2026-02-30');
    }
    public function testEarliestEndedPeriodIncludesOverlappingJuneDeadlineAndQuarterHalfEdges(): void
    {
        foreach (['2026-08-01', '2026-08-10', '2026-08-17'] as $today) {
            $period = SaldiVatPeriod::current('month', $today);
            self::assertSame('2026-06-01', $period['from']);
            self::assertSame('2026-06-30', $period['to']);
            self::assertSame('2026-08-17', $period['deadline']);
        }
        self::assertSame('2026-07-01', SaldiVatPeriod::current('month', '2026-08-18')['from']);
        foreach (['quarter', 'half'] as $type) {
            self::assertSame('2026-01-01', SaldiVatPeriod::current($type, $type === 'quarter' ? '2026-06-01' : '2026-09-01')['from']);
            self::assertSame($type === 'quarter' ? '2026-04-01' : '2026-07-01', SaldiVatPeriod::current($type, $type === 'quarter' ? '2026-06-02' : '2026-09-02')['from']);
        }
    }

}
