<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/core/ETag.php --- ver 5.0.0 --- 2026.10.02
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

final class ETag
{
    /** A strong, quoted validator over the fields that affect an order edit. */
    public static function order(array $header, array $lines): string
    {
        $select = static function (array $row, array $fields): array {
            $result = [];
            foreach ($fields as $field) {
                $result[$field] = ifset($row, $field);
            }
            return $result;
        };
        usort($lines, static fn(array $a, array $b): int => (int)$a['id'] <=> (int)$b['id']);
        $lines = array_map(static fn(array $line): array => $select($line, ['id','posnr','vare_id','antal','pris','rabat']), $lines);
        return self::hash([$select($header, ['id','status','sum','moms','kostpris','fakturanr','hvem','tidspkt']), $lines]);
    }

    /** Customers use the database modtime; include identity so validators cannot cross rows. */
    public static function customer(array $customer): string
    {
        return self::hash([ifset($customer, 'id'), ifset($customer, 'modtime')]);
    }

    /** Check under the caller's resource row lock before any mutation. Weak validators never match. */
    public static function requireMatch(?string $ifMatch, string $current): void
    {
        if ($ifMatch === null || trim($ifMatch) === '') {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'if_match_required']);
        }
        foreach (explode(',', $ifMatch) as $candidate) {
            if (hash_equals($current, trim($candidate))) {
                return;
            }
        }
        throw new MobileT2Error('precondition_failed', 412);
    }

    private static function hash(array $value): string
    {
        return '"' . hash('sha256', Idempotency::canonicalJson($value)) . '"';
    }
}
