<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/core/Subject.php --- ver 5.0.0 --- 2026.10.01
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
// 20261001 CDX/LH Address B1 identity, migration and security review findings.

final class Subject
{
    /** One registry identity shared by web, CLI and every tenant; cached per process. */
    public static function installation(): string
    {
        static $installation = null;
        if ($installation !== null) {
            return $installation;
        }
        global $sqhost, $squser, $sqpass, $sqdb, $connection, $db_type, $db_encode;
        require_once __DIR__ . '/../../includes/db_query.php';
        require_once __DIR__ . '/../../includes/connect.php';
        require_once __DIR__ . '/MobileSchema.php';
        $previous = $connection ?? null;
        try {
            $registry = db_connect($sqhost, $squser, $sqpass, $sqdb, __FILE__ . ' linje ' . __LINE__);
            $rows = (new MobileSchema($registry))->rows("SELECT var_value FROM settings WHERE var_grp='mobile' AND var_name='installation_id' AND user_id=0 ORDER BY id DESC LIMIT 1");
            $value = ifset($rows, [0, 'var_value'], '');
            self::validateInstallation($value);
            $installation = $value;
        } finally {
            $connection = $previous;
        }
        return $installation;
    }

    /** Build the canonical identity; tenant identity is carried separately. */
    public static function build(string $principal, int $userId, ?string $installation = null): string
    {
        $installation = $installation ?? self::installation();
        self::validateInstallation($installation);
        if (!in_array($principal, ['account', 'registry'], true) || $userId < 1) {
            throw new InvalidArgumentException('Invalid principal or user id');
        }
        return "$installation/$principal/$userId";
    }

    /** @return array{installation:string, principal:string, user_id:int} */
    public static function parse(string $subject): array
    {
        if (!preg_match('~^([A-Za-z0-9][A-Za-z0-9._:-]*)/(account|registry)/([1-9][0-9]*)$~D', $subject, $match)
            || filter_var($match[3], FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('Invalid subject');
        }
        return ['installation' => $match[1], 'principal' => $match[2], 'user_id' => (int)$match[3]];
    }

    /** Reject slashes, whitespace and unsafe header/log characters. */
    private static function validateInstallation(string $installation): void
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D', $installation)) {
            throw new InvalidArgumentException('Invalid installation identity');
        }
    }
}
