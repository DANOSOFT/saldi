<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/core/Paging.php --- ver 5.0.0 --- 2026.10.02
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

require_once __DIR__ . '/Idempotency.php';

final class Paging
{
    public int $limit;
    public int $offset;

    public function __construct(array $query = [], bool $catalogue = false)
    {
        $this->limit = self::integer(ifset($query, 'limit', $catalogue ? 500 : 50), 1);
        $this->limit = min($this->limit, $catalogue ? 1000 : 100);
        $this->offset = self::integer(ifset($query, 'offset', 0), 0);
    }

    /** @return array{items:array,total:int,limit:int,offset:int} */
    public function response(array $items, int $total): array
    {
        return ['items' => $items, 'total' => $total, 'limit' => $this->limit, 'offset' => $this->offset];
    }

    /** Trusted whitelist expressions only; all list orders finish with an id tie breaker. */
    public static function order(string $sort, array $allowed, string $id = 'id', bool $descending = false): string
    {
        if (!array_key_exists($sort, $allowed) || !preg_match('/^[a-z_][a-z0-9_.]*$/iD', $id)) {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'invalid_sort']);
        }
        $direction = $descending ? ' DESC' : ' ASC';
        return $allowed[$sort] . $direction . ', ' . $id . $direction;
    }

    /** SQL fragment made exclusively from validated integers. */
    public function sql(): string
    {
        return 'LIMIT ' . $this->limit . ' OFFSET ' . $this->offset;
    }

    private static function integer($value, int $minimum): int
    {
        if ((!is_int($value) && (!is_string($value) || !preg_match('/^(0|[1-9][0-9]*)$/D', $value)))
            || strlen((string)$value) > 10 || (int)$value < $minimum || (int)$value > 2147483647) {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'invalid_paging']);
        }
        return (int)$value;
    }
}
