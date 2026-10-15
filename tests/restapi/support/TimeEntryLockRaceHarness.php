<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/support/TimeEntryLockRaceHarness.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Created.
// 20261001 CDX/LH Add a deterministic membership-restoration barrier for the conditional row lock.

require_once __DIR__ . '/../../../restapi/services/TimeEntryService.php';

/** Inject a web action after the real locking SQL, without production hooks or timing-dependent sleeps. */
final class TimeEntryLockRaceHarness
{
    public static ?Closure $afterLock = null;

    /** Load the real service with only its query calls routed through the deterministic race barrier. */
    public static function load(): void
    {
        if (class_exists('TimeEntryLockRaceService', false)) {
            return;
        }
        $class = new ReflectionClass(TimeEntryService::class);
        $lines = file($class->getFileName());
        $source = implode('', array_slice($lines, $class->getStartLine() - 1, $class->getEndLine() - $class->getStartLine() + 1));
        $source = str_replace('final class TimeEntryService', 'final class TimeEntryLockRaceService', $source);
        $source = str_replace('loen_timer_query(', 'TimeEntryLockRaceHarness::query(', $source);
        eval($source);
    }

    /** @param list<mixed> $params @return list<array<string,mixed>> */
    public static function query(string $sql, array $params = []): array
    {
        $rows = loen_timer_query($sql, $params);
        if (str_contains($sql, 'FOR UPDATE') && self::$afterLock !== null) {
            $callback = self::$afterLock;
            self::$afterLock = null;
            $callback($rows);
        }
        return $rows;
    }
}
