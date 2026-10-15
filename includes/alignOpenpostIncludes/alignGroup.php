<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/alignOpenpostIncludes/alignGroup.php --- ver 5.0.0 --- 2026-10-03 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created. Shared settlement with caller-owned transaction and latest date.

require_once __DIR__ . '/../openpost/udlignId.php';

/**
 * Settle the caller-validated zero-difference selection without committing.
 * The caller owns the transaction and verifies the sum in its accounting currency.
 * @param list<int> $ids Selected open-post ids.
 * @return int The shared settlement id.
 */
function openpost_align_group(array $ids, string $alignDate): int
{
    global $connection;
    if ($connection instanceof \PgSql\Connection && pg_transaction_status($connection) !== PGSQL_TRANSACTION_INTRANS) {
        throw new LogicException('Settlement requires a caller-owned transaction');
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $alignDate);
    if (!$parsed || $parsed->format('Y-m-d') !== $alignDate || !$ids) {
        throw new InvalidArgumentException('Invalid settlement selection');
    }
    $ids = array_values(array_unique(array_map('intval', $ids)));
    sort($ids, SORT_NUMERIC);
    if ($ids[0] <= 0) {
        throw new InvalidArgumentException('Invalid settlement ids');
    }
    $list = implode(',', $ids);
    $query = db_select("SELECT id,konto_id,transdate,udlignet FROM openpost WHERE id IN ($list) ORDER BY id FOR UPDATE", __FILE__ . ' line ' . __LINE__);
    $rows = [];
    while ($row = db_fetch_array($query)) {
        $rows[] = $row;
        $alignDate = max($alignDate, (string)$row['transdate']);
    }
    if (count($rows) !== count($ids) || count(array_unique(array_column($rows, 'konto_id'))) !== 1) {
        throw new InvalidArgumentException('Settlement rows must belong to one account');
    }
    foreach ($rows as $row) {
        if ((string)$row['udlignet'] === '1') {
            throw new RuntimeException('Settlement selection changed');
        }
    }
    $id = udlign_next_id();
    $date = db_escape_string($alignDate);
    db_modify("UPDATE openpost SET udlignet='1',udlign_id=$id,udlign_date='$date' WHERE id IN ($list)", __FILE__ . ' line ' . __LINE__);
    return $id;
}
