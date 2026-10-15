<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/orders/orderApprove.php --- ver 5.0.0 --- 2026.10.03
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

/**
 * Resolve the web's status-1 delivery date without rendering or writing.
 * @return array{status:int,levdate:?string,defaulted:bool,error:?string}
 */
function order_approval_state(int $status, ?string $levdate, string $ordredate, string $art, bool $quick): array
{
    $defaulted = false;
    $error = null;
    if ($status === 1) {
        if (!$levdate) {
            $levdate = $quick ? $ordredate : date('Y-m-d');
            $defaulted = !$quick;
        } elseif (delivery_date_before_order_date($art, $levdate, $ordredate)) {
            $status = 0;
            $error = 'Leveringsdato er før ordredato';
        }
    }
    return ['status'=>$status,'levdate'=>$levdate,'defaulted'=>$defaulted,'error'=>$error];
}

/**
 * Approve/reserve an order in the caller's transaction; never commit or render.
 * The web supplies reservationLineId during its form loop, before saving edited
 * quantities. That phase reserves only the persisted line, preserving web timing.
 * @param int|null $reservationLineId Internal web-save reservation phase.
 * @return string OK, or the legacy delivery-date refusal.
 */
function order_approve(int $ordreId, ?string $levdate, ?int $reservationLineId = null): string
{
    if ($reservationLineId !== null) {
        $line = db_fetch_array(db_select("SELECT id FROM ordrelinjer WHERE id=$reservationLineId AND ordre_id=$ordreId",__FILE__.' linje '.__LINE__));
        if ($line) { batch($reservationLineId); }
        return 'OK';
    }
    $order = db_fetch_array(db_select("SELECT * FROM ordrer WHERE id=$ordreId",__FILE__.' linje '.__LINE__));
    if (!$order || (int)$order['status']>=3) { return 'Ordren kan ikke godkendes'; }
    $quick = db_fetch_array(db_select("SELECT box4 FROM grupper WHERE art='DIV' AND kodenr=3",__FILE__.' linje '.__LINE__));
    $state = order_approval_state(max(1,(int)$order['status']),$levdate ?: $order['levdate'],(string)$order['ordredate'],
        order_becomes_credit_note($ordreId,$order['art']) ? 'DK' : $order['art'],ifset($quick,'box4')==='on');
    if ($state['error']) { return $state['error']; }
    $date = $state['levdate'] ? "'".db_escape_string($state['levdate'])."'" : 'NULL';
    $status = (int)$state['status'];
    db_modify("UPDATE ordrelinjer SET leveres=antal WHERE ordre_id=$ordreId",__FILE__.' linje '.__LINE__);
    $query = db_select("SELECT id FROM ordrelinjer WHERE ordre_id=$ordreId AND varenr<>'' ORDER BY posnr,id",__FILE__.' linje '.__LINE__);
    while ($line = db_fetch_array($query)) { batch((int)$line['id']); }
    db_modify("UPDATE ordrer SET status=$status,levdate=$date WHERE id=$ordreId",__FILE__.' linje '.__LINE__);
    return 'OK';
}
