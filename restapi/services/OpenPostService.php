<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/OpenPostService.php --- ver 5.0.0 --- 2026.10.02
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

require_once __DIR__ . '/../../includes/openPostsService.php';
require_once __DIR__ . '/../core/Paging.php';

/** Thin HTTP-facing adapter over the shared accounting reader. */
final class OpenPostService
{
    private OpenPostsReader $reader;

    public function __construct($connection)
    {
        $this->reader = openposts_reader($connection);
    }

    public static function id($value): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)
            || strlen((string)$value) > 10 || (int)$value > 2147483647) {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'invalid_id']);
        }
        return (int)$value;
    }

    /** @return array{items:array,total:int,limit:int,offset:int,summary:array,accounts:array} */
    public function list(array $query): array
    {
        $paging = new Paging($query);
        $filter = [];
        foreach (['status', 'bucket', 'sort', 'q'] as $name) {
            $value = ifset($query, $name);
            if ($value !== null && !is_string($value)) {
                throw new MobileT2Error('validation_failed', 422, ['reason' => 'invalid_filter']);
            }
            if ($value !== null && $value !== '') {
                $filter[$name] = $value;
            }
        }
        if (ifset($query, 'konto_id') !== null && $query['konto_id'] !== '') {
            $filter['konto_id'] = self::id($query['konto_id']);
        }
        if (!in_array(ifset($filter, 'status', 'all'), ['all', 'open', 'overdue', 'not_due'], true)
            || !in_array(ifset($filter, 'bucket', 'not_due'), ['not_due', 'd0_30', 'd31_60', 'd61_90', 'd90_plus'], true)
            || mb_strlen(ifset($filter, 'q', '')) > 200) {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'invalid_filter']);
        }
        if (!in_array(ifset($filter, 'sort', 'days_overdue'), ['days_overdue', 'rest', 'due_date', 'customer_name', 'id'], true)) {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'invalid_sort']);
        }
        $result = $this->reader->items($filter, $paging->limit, $paging->offset);
        $result['accounts'] = array_map(static fn(int $id, array $account): array => ['konto_id' => $id] + $account,
            array_keys($result['accounts']), array_values($result['accounts']));
        return $paging->response($result['items'], $result['total'])
            + ['summary' => $result['summary'], 'accounts' => $result['accounts']];
    }

    /** @return array<string,mixed> */
    public function detail($id): array
    {
        $item = $this->reader->detail(self::id($id));
        if (!$item) {
            throw new MobileT2Error('not_found', 404);
        }
        return $item;
    }

    /** @return array<string,mixed> */
    public function summary(?int $kontoId = null): array
    {
        return $this->reader->summary($kontoId);
    }
}
