<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/lager/stockMovement.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Resolve legacy warehouse and variant aliases under the item lock.

require_once __DIR__ . '/stockLocks.php';

/** @return string A finite quantity normalized to at most three decimal places. */
function stock_quantity(string $value): string
{
    if (!preg_match('/^-?\d{1,12}(?:\.\d{1,3})?$/D', $value)) {
        throw new InvalidArgumentException('Invalid stock quantity');
    }
    $quantity = rtrim(rtrim(number_format((float)$value, 3, '.', ''), '0'), '.');
    return $quantity === '-0' ? '0' : $quantity;
}

/** @return string SQL scope for a validated warehouse; legacy 0/NULL denotes warehouse one. */
function stock_warehouse_scope(int $lager): string
{
    return $lager === 1 ? '(lager=1 OR lager=0 OR lager IS NULL)' : "lager=$lager";
}

/**
 * Consolidate one warehouse/variant before adjusting it, retaining a requested existing row id.
 * NULL variants denote variant zero. Locations stay on the retained row; quantities are summed in SQL.
 * The caller owns the transaction, and batch consumption uses the same compatible predicates.
 *
 * @return array{id:int,beholdning:string}|null The canonical stock row, or null when absent.
 */
function stock_resolve_quantity_row(int $vareId, int $variantId, int $lager, ?int $preferredId = null): ?array
{
    stock_lock_item($vareId);
    $scope = stock_warehouse_scope($lager);
    $where = "vare_id=$vareId AND COALESCE(variant_id,0)=$variantId AND $scope";
    $query = db_select("SELECT id,lager,variant_id,COALESCE(beholdning,0) AS beholdning FROM lagerstatus WHERE $where ORDER BY id FOR UPDATE", __FILE__ . ' ' . __LINE__);
    $rows = [];
    $retained = null;
    while ($row = db_fetch_array($query)) {
        $rows[] = $row;
        if ($retained === null || (int)$row['id'] === $preferredId) {
            $retained = $row;
        }
    }
    if ($retained === null) {
        return null;
    }
    $id = (int)$retained['id'];
    $quantity = stock_quantity((string)$retained['beholdning']);
    if (count($rows) > 1 || $retained['lager'] === null || (int)$retained['lager'] !== $lager || $retained['variant_id'] === null) {
        $sum = db_fetch_array(db_select("SELECT COALESCE(SUM(beholdning),0) AS quantity FROM lagerstatus WHERE $where", __FILE__ . ' ' . __LINE__));
        $quantity = stock_quantity((string)$sum['quantity']);
        db_modify("UPDATE lagerstatus SET lager=$lager,variant_id=$variantId,beholdning='$quantity' WHERE id=$id", __FILE__ . ' ' . __LINE__);
        db_modify("DELETE FROM lagerstatus WHERE $where AND id<>$id", __FILE__ . ' ' . __LINE__);
    }
    return ['id'=>$id,'beholdning'=>$quantity];
}

/**
 * Read an item's warehouse quantity and movement fingerprint in one database snapshot.
 * Warehouse one includes pre-warehouse rows (0/NULL); stocklog has no warehouse column.
 * forUpdate requires the caller's transaction and takes the item lock before locking rows.
 *
 * @return array{quantity:string,token:string}
 */
function stock_snapshot(int $vareId, int $lager, bool $forUpdate = false): array
{
    if ($vareId < 1 || $lager < 1) {
        throw new InvalidArgumentException('Invalid stock snapshot target');
    }
    $scope = stock_warehouse_scope($lager);
    if ($forUpdate) {
        stock_lock_item($vareId);
        $rows = db_select("SELECT id FROM lagerstatus WHERE vare_id=$vareId AND $scope FOR UPDATE", __FILE__ . ' ' . __LINE__);
        while (db_fetch_array($rows)) {
        }
    }
    $sql = "SELECT
        COALESCE((SELECT SUM(beholdning) FROM lagerstatus WHERE vare_id=$vareId AND $scope),0) AS quantity,
        COALESCE((SELECT MAX(id) FROM batch_kob WHERE vare_id=$vareId AND $scope),0) AS kob_id,
        COALESCE((SELECT MAX(id) FROM batch_salg WHERE vare_id=$vareId AND $scope),0) AS salg_id,
        COALESCE((SELECT SUM(rest) FROM batch_kob WHERE vare_id=$vareId AND $scope),0) AS rest,
        COALESCE((SELECT MAX(id) FROM stocklog WHERE item_id=$vareId),0) AS log_id";
    $row = db_fetch_array(db_select($sql, __FILE__ . ' ' . __LINE__));
    $quantity = stock_quantity((string)$row['quantity']);
    $rest = stock_quantity((string)$row['rest']);
    return ['quantity'=>$quantity,'token'=>hash('sha256', implode('|', [$vareId,$lager,$quantity,$row['kob_id'],$row['salg_id'],$rest,$row['log_id']]))];
}

/** @return int The product-card correction's stocklog id, in the caller's transaction. */
function stock_log_correction(int $vareId, string $username, string $initials, string $reason, string $correction): int
{
    stock_lock_item($vareId);
    $username = db_escape_string(mb_substr($username, 0, 10));
    $initials = db_escape_string(mb_substr($initials, 0, 10));
    $reason = db_escape_string($reason);
    $correction = stock_quantity($correction);
    $time = (string)time();
    $sql = "INSERT INTO stocklog(item_id,username,initials,reason,correction,logtime)
        VALUES($vareId,'$username','$initials','$reason','$correction','$time') RETURNING id";
    $row = db_fetch_array(db_select($sql, __FILE__ . ' ' . __LINE__));
    return (int)$row['id'];
}

/**
 * Set one variant's quantity through the characterised product-card engine under its item lock.
 * Deferred targets are synchronized with stock_shop_sync() only after the caller commits.
 *
 * @return array{before:string,after:string,delta:string,sync:array{vare_id:int,variant_id:int,lager:int}|null}
 */
function stock_set_quantity(int $vareId, int $variantId, int $lager, string $newQty, string $kostpris, string $transdate, bool $deferShopSync = false): array
{
    if ($vareId < 1 || $variantId < 0) {
        throw new InvalidArgumentException('Invalid stock movement target');
    }
    $lager = max(1, $lager);
    $newQty = stock_quantity($newQty);
    $kostpris = stock_quantity($kostpris);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $transdate);
    if (!$date || $date->format('Y-m-d') !== $transdate) {
        throw new InvalidArgumentException('Invalid stock movement date');
    }
    $result = stock_item_section([$vareId], static function () use ($vareId,$variantId,$lager,$newQty,$kostpris,$transdate): array {
        $row = stock_resolve_quantity_row($vareId, $variantId, $lager);
        $before = stock_quantity((string)ifset($row, 'beholdning', '0'));
        $delta = stock_quantity(number_format((float)$newQty - (float)$before, 3, '.', ''));
        lagerreguler($vareId, $newQty, $kostpris, $lager, $transdate, $variantId, true);
        return ['before'=>$before,'after'=>$newQty,'delta'=>$delta,
            'sync'=>$delta === '0' ? null : ['vare_id'=>$vareId,'variant_id'=>$variantId,'lager'=>$lager]];
    });
    if (!$deferShopSync) {
        sync_shop_vare($vareId, $variantId, $lager);
        $result['sync'] = null;
    }
    return $result;
}

/** @return 'variant'|'serial'|'assembled'|'not_stock_item'|'closed'|null Why an item cannot be counted by the app. */
function stock_item_countable(int $vareId): ?string
{
    global $regnaar;
    if ($vareId < 1) {
        return 'not_stock_item';
    }
    $row = db_fetch_array(db_select("SELECT gruppe,serienr,samlevare,lukket FROM varer WHERE id=$vareId", __FILE__ . ' ' . __LINE__));
    if (!$row) {
        return 'not_stock_item';
    }
    if (db_fetch_array(db_select("SELECT id FROM variant_varer WHERE vare_id=$vareId LIMIT 1", __FILE__ . ' ' . __LINE__))) {
        return 'variant';
    }
    if (trim((string)$row['serienr']) !== '') {
        return 'serial';
    }
    if ($row['samlevare'] === 'on') {
        return 'assembled';
    }
    $group = (int)$row['gruppe'];
    $year = (int)$regnaar;
    $tracked = db_fetch_array(db_select("SELECT box8 FROM grupper WHERE art='VG' AND kodenr='$group' AND fiscal_year=$year ORDER BY id DESC LIMIT 1", __FILE__ . ' ' . __LINE__));
    if (ifset($tracked, 'box8') !== 'on') {
        return 'not_stock_item';
    }
    return trim((string)$row['lukket']) !== '' ? 'closed' : null;
}
