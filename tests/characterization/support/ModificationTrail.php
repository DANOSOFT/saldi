<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/ModificationTrail.php --- ver 5.0.0 --- 2026.10.07
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
// 20261007 CDX/LH Created tenant modification-trail assertions.

/** Inspect only new bytes from a disposable tenant's legacy SQL log. */
final class ModificationTrail
{
    /** @return string Absolute tenant trail path, independent of the page runner's cwd. */
    public static function path(string $db): string
    {
        return dirname(__DIR__, 3) . '/temp/' . $db . '/.ht_modify.log';
    }

    public static function read(string $db): string
    {
        $path = self::path($db);
        return is_file($path) ? file_get_contents($path) : '';
    }

    public static function assertWrite(string $db, string $before, string $sqlPattern): string
    {
        $added = substr(self::read($db), strlen($before));
        \PHPUnit\Framework\Assert::assertMatchesRegularExpression(
            '/-- [^\n]+ \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}: [^\n]+: (?:[2-9]|[1-9][0-9]+)\n\s*' . $sqlPattern . '/i', $added);
        return $added;
    }
}
