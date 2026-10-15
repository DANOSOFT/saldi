<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiVatPeriod.php --- ai/mcp-assistant --- 2026.10.03
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
// Deterministic Assist VAT periods and weekend-adjusted indicative deadlines.
// Holidays are deliberately excluded; users must verify the deadline at skat.dk.
//
// 20261002 CDX/LH Created.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';
require_once __DIR__ . '/../tools/SaldiToolRuntime.php';

final class SaldiVatPeriod
{
	/** @return array{from:string,to:string,deadline:string,days_left:int} */
	public static function current(string $type, string $today): array
	{
		SaldiToolRuntime::date($today);
		$months = ['month' => 1, 'quarter' => 3, 'half' => 6][$type] ?? 3;
		$month = intdiv((int)substr($today, 5, 2) - 1, $months) * $months + 1;
		$running = substr($today, 0, 4) . sprintf('-%02d-01', $month);
		$period = self::period($type, $running);
		// June's monthly deadline overlaps July's: inspect both ended periods, oldest first.
		for ($offset = 2; $offset >= 1; $offset--) {
			$previous = (new DateTimeImmutable($running))->modify('-' . ($months * $offset) . ' months')->format('Y-m-d');
			$candidate = self::period($type, $previous);
			if ($candidate['to'] < $today && $candidate['deadline'] >= $today) {
				$period = $candidate;
				break;
			}
		}
		$period['days_left'] = (int)(new DateTimeImmutable($today))->diff(new DateTimeImmutable($period['deadline']))->format('%r%a');
		return $period;
	}
	/** @return array{from:string,to:string,deadline:string} Period containing the supplied date. */
	public static function period(string $type, string $date): array
	{
		SaldiToolRuntime::date($date);
		$months = ['month' => 1, 'quarter' => 3, 'half' => 6][$type] ?? 3;
		$month = intdiv((int)substr($date, 5, 2) - 1, $months) * $months + 1;
		$start = new DateTimeImmutable(substr($date, 0, 4) . sprintf('-%02d-01', $month), new DateTimeZone('Europe/Copenhagen'));
		$next = $start->modify('+' . $months . ' months');
		if ($months === 1) {
			$deadline = $month === 6 ? $start->setDate((int)$start->format('Y'), 8, 17) : $next->modify('+24 days');
		} else {
			$deadline = $next->modify('+2 months');
		}
		$weekday = (int)$deadline->format('N');
		if ($weekday >= 6) {
			$deadline = $deadline->modify('+' . (8 - $weekday) . ' days');
		}
		return ['from' => $start->format('Y-m-d'), 'to' => $next->modify('-1 day')->format('Y-m-d'), 'deadline' => $deadline->format('Y-m-d')];
	}
}
