<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/kreditor/creditorReceive.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Extracted creditor receipt validation, batches and reservation rules.
// 20261001 CDX/LH Use relative item, variant and warehouse stock writes under the item lock.
// 20261001 CDX/LH Preserve database decimal quantities and explicitly serialize calculated batch quantities.
// 20261007 CDX/LH Restore the shared tenant SQL modification trail after writes.

require_once __DIR__ . '/../db_query.php';
require_once __DIR__ . '/../lager/stockLocks.php';

/**
 * Execute receipt SQL without legacy database helpers' HTML/exit failure paths.
 * The caller owns the transaction, including rollback after an exception.
 *
 * @param list<mixed> $params Bound SQL values.
 * @return list<array<string,string|null>> Selected rows; empty for writes.
 */
function creditor_receive_query(string $sql, array $params = []): array
{
    $connection = stock_lock_connection();
    if (!pg_send_query_params($connection, $sql, $params)) {
        db_modify_query_trail($connection, $sql, $params, __FILE__, false, pg_last_error($connection));
        throw new RuntimeException('Receipt database command failed');
    }
    $result = pg_get_result($connection);
    while (pg_get_result($connection) !== false) {
    }
    $ok = $result !== false && in_array(pg_result_status($result), [PGSQL_COMMAND_OK, PGSQL_TUPLES_OK], true);
    db_modify_query_trail($connection, $sql, $params, __FILE__, $ok,
        $ok ? '' : ($result ? pg_result_error($result) : pg_last_error($connection)));
    if (!$result || !in_array(pg_result_status($result), [PGSQL_COMMAND_OK, PGSQL_TUPLES_OK], true)) {
        throw new RuntimeException('Receipt database command failed');
    }
    return pg_result_status($result) === PGSQL_TUPLES_OK ? pg_fetch_all($result) : [];
}

/**
 * Use item_has_due_date()'s boolean rule with a checked read instead of db_select()'s exit path.
 * @internal Receipt validation only; SQL errors leave rollback to the caller.
 */
function creditor_receive_has_due_date(int $vareId): bool
{
    $flag = creditor_receive_query('SELECT has_due_date FROM varer WHERE id=$1', [$vareId])[0]['has_due_date'] ?? null;
    return $flag === 't' || $flag === true || $flag == 1;
}

/** @return array{reason:string,message:string,lines:array{},sync:array{}} Caller must roll back this refusal. */
function creditor_receive_refusal(string $reason, string $message): array
{
    return ['reason' => $reason, 'message' => $message, 'lines' => [], 'sync' => []];
}

/**
 * Receive the web-prepared quantities, preserving modtag.php's batch and reservation rules.
 * Requires the selected tenant transaction and its order row lock, held by the caller.
 * Locks every participating item in ascending id order before stock reads. Never begins, commits,
 * rolls back, prints or exits. On a reason or exception the caller MUST roll back the whole transaction.
 * Context globals regnaar, bruger_id and brugernavn come from online.php or EngineContext.
 *
 * @param array{defer_shop_sync?:bool} $opts True collects targets for stock_shop_sync() after commit.
 * @return array{
 *   reason:?string, message:?string,
 *   lines:list<array{linje_id:int,vare_id:int,variant_id:int,lager:int,received:string}>,
 *   sync:list<array{vare_id:int,variant_id:int,lager:int}>
 * }
 */
function creditor_receive(int $orderId, array $opts = []): array
{
    if (pg_transaction_status(stock_lock_connection()) !== PGSQL_TRANSACTION_INTRANS) {
        throw new LogicException('Receipt requires an open transaction and order row lock');
    }
    $yearId = (int)ifset($GLOBALS, 'regnaar', 0);
    $order = creditor_receive_query('SELECT * FROM ordrer WHERE id=$1', [$orderId])[0] ?? null;
    if (!$order) {
        return creditor_receive_refusal('order_missing', '');
    }
    $art = $order['art'];
    if ((int)$order['status'] > 2) {
        return creditor_receive_refusal('order_closed', 'Hmmm - har du brugt browserens opdater eller tilbageknap???');
    }
    if (!$order['levdate']) {
        return creditor_receive_refusal('delivery_date_missing', 'Leveringsdato ikke udfyldt');
    }
    if ($order['levdate'] < $order['ordredate']) {
        return creditor_receive_refusal('delivery_date_before_order', 'Leveringsdato er f&oslash;r ordredato');
    }
    $levdate = $order['levdate'];
    $year = creditor_receive_query("SELECT box1,box2,box3,box4 FROM grupper WHERE art='RA' AND kodenr=$1", [$yearId])[0] ?? null;
    $aarstart = $year ? str_replace(' ', '', substr(str_replace(' ', '', (string)$year['box2']), -2) . $year['box1']) : '';
    $aarslut = $year ? str_replace(' ', '', substr(str_replace(' ', '', (string)$year['box4']), -2) . $year['box3']) : '';
    $ym = substr(str_replace('-', '', $levdate), 2, 4);
    if (!$aarstart || !$aarslut || $ym < $aarstart || $ym > $aarslut) {
        return creditor_receive_refusal('outside_fiscal_year', 'Leveringsdato udenfor regnskabs&aring;r');
    }
    $lines = [];
    foreach (creditor_receive_query('SELECT * FROM ordrelinjer WHERE ordre_id=$1', [$orderId]) as $line) {
        if ((int)$line['posnr'] > 0 && strlen(trim((string)$line['varenr'])) > 0) {
            foreach (['id','vare_id','variant_id','lager','kred_linje_id','posnr'] as $field) {
                $line[$field] = (int)$line[$field];
            }
            $line['lager'] = $line['lager'] ?: 1;
            // Keep PostgreSQL's decimal text: implicit float binding uses PHP's precision setting.
            $line['leveres'] = (string)($line['leveres'] ?? '0');
            $line['serienr'] = trim((string)$line['serienr']);
            $lines[] = $line;
        }
    }
    $items = [];
    foreach ($lines as $line) {
        if ($line['vare_id'] > 0 && $line['leveres'] != 0) {
            $items[] = $line['vare_id'];
        }
    }
    $items = array_values(array_unique($items));
    sort($items, SORT_NUMERIC);
    foreach ($items as $itemId) {
        stock_lock_item($itemId);
    }
    foreach ($lines as $line) {
        $vare_id = $line['vare_id'];
        $leveres = $line['leveres'];
        if ($leveres > 0 && $line['serienr'] && $art !== 'KK') {
            $serials = creditor_receive_query('SELECT id FROM serienr WHERE kobslinje_id=$1 AND batch_kob_id=0', [$line['id']]);
            if ($leveres > count($serials)) {
                return creditor_receive_refusal('serial_numbers_missing', 'Serienumre ikke udfyldt');
            }
        }
        $dueDateTracked = creditor_receive_has_due_date($vare_id);
        $r = creditor_receive_query("SELECT g.box9 FROM varer v JOIN grupper g ON g.kodenr=v.gruppe AND g.art='VG' AND g.fiscal_year=$1 WHERE v.id=$2", [$yearId,$vare_id])[0] ?? null;
        $batchNoTracked = $dueDateTracked || trim((string)($r['box9'] ?? '')) === 'on';
        $batchDueDateEmpty = $line['batch_due_date'] === null || $line['batch_due_date'] === '';
        $batchBatchNoEmpty = $line['batch_batch_no'] === null || $line['batch_batch_no'] === '';
        if ($leveres > 0 && $art !== 'KK' && (($dueDateTracked && $batchDueDateEmpty) || ($batchNoTracked && $batchBatchNoEmpty))) {
            return creditor_receive_refusal('batch_info_missing', 'Batchoplysninger ikke udfyldt');
        }
        if ($leveres < 0 && $line['serienr']) {
            $sql = $art === 'KK' ? 'SELECT id FROM serienr WHERE kobslinje_id=$1 AND batch_salg_id<=0'
                : 'SELECT id FROM serienr WHERE salgslinje_id=$1 AND batch_salg_id<=0';
            $serials = creditor_receive_query($sql, [$art === 'KK' ? -$line['kred_linje_id'] : $line['id']]);
            if ($leveres != -count($serials)) {
                return creditor_receive_refusal('serial_numbers_not_selected', 'Serienumre ikke valgt');
            }
        }
    }
    $received = $sync = [];
    foreach ($lines as $line) {
        if (!$line['vare_id'] || $line['leveres'] == 0) {
            continue;
        }
        $vareId = $line['vare_id'];
        $variantId = $line['variant_id'];
        $lager = $line['lager'];
        $quantity = $line['leveres'];
        $lineId = $line['id'];
        $item = creditor_receive_query('SELECT gruppe FROM varer WHERE id=$1', [$vareId])[0] ?? null;
        $group = creditor_receive_query("SELECT * FROM grupper WHERE art='VG' AND kodenr=$1 AND fiscal_year=$2", [(int)($item['gruppe'] ?? 0),$yearId])[0] ?? null;
        $account = trim((string)($group['box3'] ?? ''));
        if (trim((string)($group['box8'] ?? '')) !== 'on') {
            if (!$account) {
                return creditor_receive_refusal('product_group_missing', "Varenr {$line['varenr']} (Pos nr: {$line['posnr']}) er ikke tilnykttet nogen varegruppe, modtagelse afbrudt");
            }
            creditor_receive_query('UPDATE ordrelinjer SET bogf_konto=$1 WHERE id=$2', [$account,$lineId]);
            creditor_receive_query('INSERT INTO batch_kob(vare_id,variant_id,linje_id,kobsdate,ordre_id,antal,lager,due_date,batch_no) VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9)',
                [$vareId,$variantId,$lineId,$levdate,$orderId,$quantity,$lager,$line['batch_due_date'] ?: null,$line['batch_batch_no'] === '' ? null : $line['batch_batch_no']]);
        } else {
            creditor_receive_query('UPDATE varer SET beholdning=COALESCE(beholdning,0)+$1 WHERE id=$2', [$quantity,$vareId]);
            if ($variantId) {
                creditor_receive_query('UPDATE variant_varer SET variant_beholdning=COALESCE(variant_beholdning,0)+$1 WHERE id=$2', [$quantity,$variantId]);
            }
            $warehouse = creditor_receive_query('SELECT * FROM lagerstatus WHERE vare_id=$1 AND variant_id=$2 AND lager=$3', [$vareId,$variantId,$lager])[0] ?? null;
            if ($warehouse) {
                creditor_receive_query('UPDATE lagerstatus SET beholdning=COALESCE(beholdning,0)+$1 WHERE id=$2', [$quantity,(int)$warehouse['id']]);
            } else {
                creditor_receive_query('INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES($1,$2,$3,$4)', [$vareId,$variantId,$lager,$quantity]);
            }
            if (trim((string)$group['box9']) === 'on') {
                $failure = $quantity < 0
                    ? creditor_receive_return($orderId,$lineId,$quantity,$vareId,$variantId,$line['serienr'],$lager,$line['kred_linje_id'],$levdate)
                    : creditor_receive_reservation($orderId,$lineId,$quantity,$vareId,$line['serienr'],$lager,$levdate);
                if ($failure !== null) {
                    return $failure;
                }
            } else {
                creditor_receive_query('UPDATE ordrelinjer SET bogf_konto=$1 WHERE id=$2', [$account,$lineId]);
                creditor_receive_query('INSERT INTO batch_kob(vare_id,variant_id,linje_id,kobsdate,ordre_id,antal,rest,lager,due_date,batch_no) VALUES($1,$2,$3,$4,$5,$6,$6,$7,$8,$9)',
                    [$vareId,$variantId,$lineId,$levdate,$orderId,$quantity,$lager,$line['batch_due_date'] ?: null,$line['batch_batch_no'] === '' ? null : $line['batch_batch_no']]);
            }
        }
        creditor_receive_query('UPDATE ordrelinjer SET leveres=0 WHERE id=$1', [$lineId]);
        if (creditor_receive_query("SELECT id FROM settings WHERE var_name='confirmStockChange' AND var_grp='items'")) {
            $actor = creditor_receive_query('SELECT ansatte.initialer FROM ansatte JOIN brugere ON ansatte.id=brugere.ansat_id WHERE brugere.id=$1', [(int)ifset($GLOBALS,'bruger_id',0)])[0] ?? null;
            // Preserve the page's username storage and ten-byte initials limit.
            $username = (string)ifset($GLOBALS,'brugernavn','');
            creditor_receive_query('INSERT INTO stocklog(item_id,username,initials,correction,reason,logtime) VALUES($1,$2,$3,$4,$5,$6)',
                [$vareId,$username,trim(substr((string)($actor['initialer'] ?? ''),0,10)),$quantity,'indkøbsordre ' . $order['ordrenr'],(string)time()]);
        }
        $received[] = ['linje_id' => $lineId,'vare_id' => $vareId,'variant_id' => $variantId,'lager' => $lager,
            'received' => str_contains($quantity,'.') ? rtrim(rtrim($quantity,'0'),'.') : $quantity];
        if (ifset($opts,'defer_shop_sync',false)) {
            $sync[] = ['vare_id' => $vareId,'variant_id' => $variantId,'lager' => $lager];
        } else {
            sync_shop_vare($vareId,$variantId,$lager);
        }
    }
    creditor_receive_reconcile($orderId,$levdate,$lines);
    return ['reason' => null,'message' => null,'lines' => $received,'sync' => $sync];
}

/**
 * Preserve the batch-tracked receipt's allocation and serial links.
 * @internal Called only by creditor_receive() under the caller's transaction and item lock.
 *
 * @param string $quantity Database decimal quantity, preserved for SQL binding.
 * @return array{reason:string,message:string,lines:array{},sync:array{}}|null Refusal, or null on success.
 */
function creditor_receive_reservation(int $id, int $linjeId, string $quantity, int $vareId, string $serial, int $lager, string $levdate): ?array
{
    $priority = creditor_receive_query('SELECT antal FROM reservation WHERE linje_id=$1 AND batch_salg_id<0', [$linjeId]);
    if ($quantity < array_sum(array_column($priority,'antal'))) {
        return creditor_receive_refusal('reservations_exceed_receipt','Der er reserveret flere varer end der modtages - foretag proiritering');
    }
    $sum = 0;
    $batchId = 0;
    foreach (creditor_receive_query('SELECT batch_kob_id,antal,batch_salg_id FROM reservation WHERE linje_id=$1', [$linjeId]) as $row) {
        $batchId = (int)$row['batch_kob_id'];
        if ((int)$row['batch_salg_id'] > 0) {
            $sum += (float)$row['antal'];
        }
    }
    $rest = number_format($quantity - $sum,3,'.','');
    $line = creditor_receive_query('SELECT batch_due_date,batch_batch_no FROM ordrelinjer WHERE id=$1', [$linjeId])[0];
    $due = $line['batch_due_date'] ?: null;
    $batchNo = $line['batch_batch_no'] === '' ? null : $line['batch_batch_no'];
    if (!$batchId) {
        creditor_receive_query('INSERT INTO batch_kob(linje_id,ordre_id,vare_id,kobsdate,antal,rest,lager,due_date,batch_no) VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9)',
            [$linjeId,$id,$vareId,$levdate,$quantity,$rest,$lager,$due,$batchNo]);
        // Retain the web's matching-row lookup and reservation behavior.
        $batchId = (int)creditor_receive_query('SELECT id FROM batch_kob WHERE linje_id=$1 AND ordre_id=$2 AND kobsdate=$3 AND antal=$4 AND rest=$5',
            [$linjeId,$id,$levdate,$quantity,$rest])[0]['id'];
    } else {
        creditor_receive_query('UPDATE batch_kob SET kobsdate=$1,ordre_id=$2,vare_id=$3,antal=$4,rest=$5,due_date=$6,batch_no=$7 WHERE id=$8',
            [$levdate,$id,$vareId,$quantity,$rest,$due,$batchNo,$batchId]);
    }
    creditor_receive_query('DELETE FROM reservation WHERE batch_kob_id=$1 AND linje_id=$2', [$batchId,$linjeId]);
    foreach (creditor_receive_query('SELECT batch_salg_id FROM reservation WHERE linje_id=$1 AND batch_salg_id<0', [$linjeId]) as $row) {
        creditor_receive_query('UPDATE reservation SET linje_id=$1,batch_kob_id=$2 WHERE batch_salg_id=$3', [-(int)$row['batch_salg_id'],$batchId,(int)$row['batch_salg_id']]);
    }
    creditor_receive_query('UPDATE reservation SET batch_salg_id=0 WHERE batch_kob_id=$1', [$batchId]);
    if ($serial) {
        creditor_receive_query('UPDATE serienr SET batch_kob_id=$1 WHERE kobslinje_id=$2', [$batchId,$linjeId]);
    }
    return null;
}

/**
 * Preserve the web-only return path, including the original batch-rest calculation.
 * @internal Called only by creditor_receive() under the caller's transaction and item lock.
 *
 * @param string $quantity Database decimal quantity, preserved for SQL binding.
 * @return array{reason:string,message:string,lines:array{},sync:array{}}|null Refusal, or null on success.
 */
function creditor_receive_return(int $id, int $linjeId, string $quantity, int $vareId, int $variantId, string $serial, int $lager, int $creditedLine, string $levdate): ?array
{
    if (!$creditedLine) {
        return creditor_receive_refusal('batch_not_selected','Batch ikke valgt');
    }
    foreach (creditor_receive_query('SELECT * FROM batch_kob WHERE linje_id=$1', [$creditedLine]) as $batch) {
        if ((float)$batch['rest'] + $quantity >= 0) {
            creditor_receive_query('UPDATE batch_kob SET rest=$1 WHERE id=$2', [number_format((float)$batch['rest'] + $quantity,3,'.',''),(int)$batch['id']]);
            if ($serial) {
                creditor_receive_query('UPDATE serienr SET batch_kob_id=$1 WHERE batch_kob_id=$2 AND kobslinje_id=$3', [-(int)$batch['id'],(int)$batch['id'],-$creditedLine]);
            }
        }
    }
    $line = creditor_receive_query('SELECT batch_due_date,batch_batch_no FROM ordrelinjer WHERE id=$1', [$linjeId])[0];
    creditor_receive_query('INSERT INTO batch_kob(linje_id,ordre_id,vare_id,variant_id,kobsdate,antal,rest,lager,due_date,batch_no) VALUES($1,$2,$3,$4,$5,$6,0,$7,$8,$9)',
        [$linjeId,$id,$vareId,$variantId,$levdate,$quantity,$lager,$line['batch_due_date'] ?: null,$line['batch_batch_no'] === '' ? null : $line['batch_batch_no']]);
    return null;
}

/**
 * Reconcile earlier receipts and sales using the page's existing batch-rest arithmetic.
 * @internal Called only by creditor_receive() under the caller's transaction and item locks.
 * In particular, keep its return pass and same-order allocation rules unchanged.
 *
 * @param list<array<string,mixed>> $lines The original prepared line quantities.
 */
function creditor_receive_reconcile(int $orderId, string $levdate, array $lines): void
{
    foreach ($lines as $line) {
        if ($line['leveres'] == 0) {
            continue;
        }
        $params = [$orderId,$line['vare_id'],$line['id']];
        $batches = creditor_receive_query('SELECT * FROM batch_kob WHERE ordre_id=$1 AND vare_id=$2 AND linje_id=$3', $params);
        foreach ($batches as $batch) {
            $rest = (float)$batch['rest'];
            if ($line['leveres'] < 0) {
                $sql = 'SELECT * FROM batch_kob WHERE ordre_id<>$1 AND vare_id=$2 AND rest>0 AND linje_id<>$3 AND lager=$4 ORDER BY id';
                if ($rest != 0) {
                    foreach (creditor_receive_query($sql, [...$params,$line['lager']]) as $other) {
                        if ((float)$other['rest'] + $rest >= 0) {
                            $otherRest = (float)$batch['rest'] - $rest;
                            $rest = 0;
                        } else {
                            $otherRest = 0;
                            $rest += (float)$batch['rest'];
                        }
                        creditor_receive_batch_rest($other,$otherRest,$levdate);
                        creditor_receive_batch_rest($batch,$rest,$levdate);
                    }
                }
                if ($rest != 0) {
                    foreach (creditor_receive_query($sql, [...$params,$line['lager']]) as $other) {
                        if ($rest != 0) {
                            if ((float)$other['rest'] + $rest >= 0) {
                                $otherRest = (float)$other['rest'] + $rest;
                                $rest = 0;
                            } else {
                                $otherRest = 0;
                                $rest += (float)$other['rest'];
                            }
                            creditor_receive_batch_rest($other,$otherRest,$levdate);
                            creditor_receive_batch_rest($batch,$rest,$levdate);
                        }
                    }
                }
            } else {
                if ($rest != 0) {
                    $sql = 'SELECT * FROM batch_kob WHERE ordre_id=$1 AND vare_id=$2 AND rest<0 AND linje_id<>$3';
                    foreach (creditor_receive_query($sql, $params) as $other) {
                        if ($rest + (float)$other['rest'] >= 0) {
                            $otherRest = $rest + (float)$other['rest'];
                            $rest = 0;
                        } else {
                            $otherRest = 0;
                            $rest += (float)$other['rest'];
                        }
                        creditor_receive_batch_rest($other,$otherRest,$levdate);
                        creditor_receive_batch_rest($batch,$rest,$levdate);
                    }
                }
                if ($rest != 0) {
                    $sql = 'SELECT * FROM batch_kob WHERE antal=0 AND ordre_id=0 AND vare_id=$1 AND rest<0 AND linje_id<>$2 AND lager=$3';
                    foreach (creditor_receive_query($sql, [$line['vare_id'],$line['id'],$line['lager']]) as $other) {
                        if ($rest != 0 && (float)$other['rest'] + $rest >= 0) {
                            $moved = abs((float)$other['rest']);
                            $rest += (float)$other['rest'];
                            creditor_receive_query('UPDATE batch_kob SET kobsdate=$1,antal=$2,ordre_id=$3,linje_id=$4,rest=0 WHERE id=$5',
                                [$other['kobsdate'] ?: $levdate,number_format($moved,3,'.',''),$orderId,$line['id'],(int)$other['id']]);
                            creditor_receive_query('UPDATE batch_kob SET kobsdate=$1,antal=antal-$2,rest=$3 WHERE id=$4',
                                [$batch['kobsdate'] ?: $levdate,number_format($moved,3,'.',''),number_format($rest,3,'.',''),(int)$batch['id']]);
                        }
                    }
                }
                if ($rest != 0) {
                    $sql = 'SELECT * FROM batch_kob WHERE ordre_id<>0 AND ordre_id<>$1 AND vare_id=$2 AND rest<0 AND linje_id<>$3';
                    foreach (creditor_receive_query($sql, $params) as $other) {
                        if ($rest != 0) {
                            if ((float)$other['rest'] + $rest >= 0) {
                                $otherRest = 0;
                                $rest += (float)$other['rest'];
                            } else {
                                $otherRest = $rest + (float)$other['rest'];
                                $rest = 0;
                            }
                            creditor_receive_batch_rest($other,$otherRest,$levdate);
                            creditor_receive_batch_rest($batch,$rest,$levdate);
                        }
                    }
                }
            }
        }
    }
}

/** @param array<string,string|null> $batch Original batch row, supplying its id and fallback date. */
function creditor_receive_batch_rest(array $batch, float $rest, string $levdate): void
{
    creditor_receive_query('UPDATE batch_kob SET kobsdate=$1,rest=$2 WHERE id=$3', [$batch['kobsdate'] ?: $levdate,number_format($rest,3,'.',''),(int)$batch['id']]);
}
