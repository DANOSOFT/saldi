<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/lager/stockLocks.php --- ver 5.0.0 --- 2026.10.06
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

require_once __DIR__ . '/../std_func.php';

/** @return \PgSql\Connection Selected tenant link supplied by the web page or LegacyEngine. */
function stock_lock_connection(): \PgSql\Connection
{
    $connection = ifset($GLOBALS, 'connection');
    if (!$connection instanceof \PgSql\Connection) {
        throw new RuntimeException('Stock locks require PostgreSQL');
    }
    return $connection;
}

/** Checked SQL with no legacy print/exit boundary; commands are server-owned constants. */
function stock_lock_query(\PgSql\Connection $connection, string $sql, array $params = []): void
{
    if (!pg_send_query_params($connection, $sql, $params)) {
        throw new RuntimeException('Stock database command failed');
    }
    $result = pg_get_result($connection);
    while (pg_get_result($connection) !== false) {
    }
    if (!$result || !in_array(pg_result_status($result), [PGSQL_COMMAND_OK, PGSQL_TUPLES_OK], true)) {
        throw new RuntimeException('Stock database command failed');
    }
}

/** Hold one item across all warehouses and variants until the caller's transaction ends. */
function stock_lock_item(int $vareId): void
{
    $connection = stock_lock_connection();
    if ($vareId < 1) {
        throw new InvalidArgumentException('Invalid stock item id');
    }
    if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_INTRANS) {
        throw new LogicException('Stock item locks require an open transaction');
    }
    stock_lock_query($connection, "SELECT pg_advisory_xact_lock(hashtext('saldi:stock'), $1::integer)", [$vareId]);
}

/**
 * Lock distinct item ids in ascending order, using the caller's transaction or owning a short one.
 * A caller-owned transaction is never committed or rolled back here, including on an exception.
 *
 * @param list<int> $vareIds Positive ids; nested sections must retain ascending lock order.
 * @return mixed The callback's result, after an owned transaction has committed.
 */
function stock_item_section(array $vareIds, callable $fn): mixed
{
    foreach ($vareIds as $id) {
        if (!is_int($id) || $id < 1) {
            throw new InvalidArgumentException('Invalid stock item id');
        }
    }
    $vareIds = array_values(array_unique($vareIds));
    sort($vareIds, SORT_NUMERIC);
    $connection = stock_lock_connection();
    $status = pg_transaction_status($connection);
    if (!in_array($status, [PGSQL_TRANSACTION_IDLE, PGSQL_TRANSACTION_INTRANS], true)) {
        throw new LogicException('Stock section requires an idle or open transaction');
    }
    $owns = $status === PGSQL_TRANSACTION_IDLE;
    $state = [];
    if ($owns) {
        foreach (['db_transaktion_depth','db_modify_fejl'] as $key) {
            $state[$key] = [array_key_exists($key, $GLOBALS), ifset($GLOBALS, $key)];
        }
        stock_lock_query($connection, 'BEGIN');
        $GLOBALS['db_transaktion_depth'] = 1;
        $GLOBALS['db_modify_fejl'] = false;
    }
    $depth = ifset($GLOBALS, 'db_transaktion_depth');
    try {
        foreach ($vareIds as $id) {
            stock_lock_item($id);
        }
        $result = $fn();
        if (stock_lock_connection() !== $connection || pg_transaction_status($connection) !== PGSQL_TRANSACTION_INTRANS
            || ifset($GLOBALS, 'db_transaktion_depth') !== $depth) {
            throw new LogicException('Stock callback changed transaction ownership');
        }
        if (ifset($GLOBALS, 'db_modify_fejl', false)) {
            throw new RuntimeException('Stock callback database write failed');
        }
        if ($owns) {
            stock_lock_query($connection, 'COMMIT');
        }
        return $result;
    } catch (Throwable $error) {
        if ($owns && pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) {
            stock_lock_query($connection, 'ROLLBACK');
        }
        throw $error;
    } finally {
        foreach ($state as $key => [$exists, $value]) {
            if ($exists) {
                $GLOBALS[$key] = $value;
            } else {
                unset($GLOBALS[$key]);
            }
        }
    }
}

/**
 * Synchronize distinct targets only after commit, in the web page's or LegacyEngine's context.
 * One failed target does not prevent the remaining targets from running; provider text is not exposed.
 *
 * @param list<array{vare_id:int,variant_id?:int,lager:int}> $targets
 * @return list<array{vare_id:int,variant_id:int,lager:int,state:'done'|'failed'|'no api'}>
 */
function stock_shop_sync(array $targets): array
{
    if (pg_transaction_status(stock_lock_connection()) !== PGSQL_TRANSACTION_IDLE) {
        throw new LogicException('Webshop synchronization requires a committed transaction');
    }
    $distinct = [];
    foreach ($targets as $target) {
        $vareId = ifset($target, 'vare_id');
        $variantId = ifset($target, 'variant_id', 0);
        $lager = ifset($target, 'lager');
        if (!is_int($vareId) || $vareId < 1 || !is_int($variantId) || $variantId < 0 || !is_int($lager) || $lager < 0) {
            throw new InvalidArgumentException('Invalid webshop synchronization target');
        }
        $distinct["$vareId:$variantId:$lager"] = ['vare_id' => $vareId, 'variant_id' => $variantId, 'lager' => $lager];
    }
    $results = [];
    foreach ($distinct as $target) {
        $state = 'failed';
        try {
            $result = sync_shop_vare($target['vare_id'], $target['variant_id'], $target['lager']);
            $state = $result === 'no api' ? 'no api' : (in_array($result, ['OK','no stock'], true) ? 'done' : 'failed');
        } catch (Throwable $error) {
            // The per-target result is the diagnostic; never include provider messages or credentials.
        }
        $results[] = $target + ['state' => $state];
    }
    return $results;
}
