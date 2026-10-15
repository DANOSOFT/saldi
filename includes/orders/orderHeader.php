<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/orders/orderHeader.php --- ver 5.0.0 --- 2026.10.02
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
 * Create the header formerly inserted by ordre.php. Caller owns the transaction when one is open.
 * opts.fields overrides raw customer defaults; opts.web_fields is exclusively the page's already-escaped snapshot.
 * @return int New order id; a quote passes allocateNumber=false and never invokes the numbering lock.
 */
function order_create_header(int $kontoId, int $status, array $opts, bool $allocateNumber = true): int
{
    global $regnaar, $connection;
    require_once __DIR__ . '/../stdFunc/getKontaktEmail.php';
    $customer = db_fetch_array(db_select("SELECT * FROM adresser WHERE id=$kontoId AND art='D'",__FILE__.' linje '.__LINE__));
    if (!$customer) { throw new InvalidArgumentException('Customer missing'); }
    $groupNo = (int)$customer['gruppe']; $year = (int)$regnaar;
    $group = db_fetch_array(db_select("SELECT * FROM grupper WHERE art='DG' AND kodenr=$groupNo AND fiscal_year=$year",__FILE__.' linje '.__LINE__));
    if (!$group) { throw new InvalidArgumentException('Customer group missing'); }
    $vatCode = (int)substr((string)$group['box1'],1,1);
    $vat = db_fetch_array(db_select("SELECT box2 FROM grupper WHERE art='SM' AND kodenr=$vatCode AND fiscal_year=$year",__FILE__.' linje '.__LINE__));
    $division = db_fetch_array(db_select("SELECT box13 FROM grupper WHERE art='DIV' AND kodenr=3",__FILE__.' linje '.__LINE__));
    $surcharge = explode(chr(9),(string)ifset($division,'box13','0'))[0];
    $values = ['konto_id'=>$kontoId,'art'=>'DO','status'=>$status,'restordre'=>0,'ordredate'=>date('Y-m-d'),
        'momssats'=>(float)ifset($vat,'box2',0),'tidspkt'=>time(),'ref'=>'','performed_by'=>'','afd'=>0,'vis_lev_addr'=>'',
        'valuta'=>(string)$group['box3'],'sprog'=>(string)$group['box4'],'procenttillag'=>(float)str_replace(',','.',$surcharge),
        'omvbet'=>$group['box9'] ? 'on' : ''];
    foreach (['kontonr','firmanavn','addr1','addr2','postnr','bynavn','land','betalingsdage','betalingsbet','cvrnr','ean','institution','email','notes','kontakt','pbs',
        'lev_addr1','lev_addr2','lev_postnr','lev_bynavn','lev_land','lev_email','lev_kontakt'] as $key) {
        $values[$key] = (string)ifset($customer,$key,'');
    }
    $values['email'] = getKontaktEmail($kontoId,'faktura');
    $values['mail_fakt'] = (string)$customer['mailfakt'];
    $values['phone'] = substr((string)$customer['tlf'],0,15);
    $values['lev_navn'] = (string)$customer['lev_firmanavn'];
    foreach (range(1,5) as $i) {
        $values['felt_'.$i] = findtekst((string)(243+$i).'|Ordrefelt '.$i,0) === findtekst((string)(254+$i).'|Ekstrafelt '.$i,0) ? (string)$customer['felt_'.$i] : '';
    }
    $fields=ifset($opts,'fields',[]);
    if (array_diff(array_keys($fields),array_merge(array_keys($values),['levdate']))) { throw new InvalidArgumentException('Invalid header column'); }
    $values = array_replace($values,$fields);
    // Restrict the override to the same header columns, never request-selected SQL identifiers.
    $allowed = array_keys($values);
    $webFields = ifset($opts,'web_fields',[]);
    foreach ($webFields as $key=>$value) {
        if (!in_array($key,$allowed,true)) { throw new InvalidArgumentException('Invalid header column'); }
        $values[$key] = $value;
    }
    $number = $allocateNumber ? get_next_order_number('DO', !($connection instanceof \PgSql\Connection && pg_transaction_status($connection)===PGSQL_TRANSACTION_INTRANS)) : 0;
    $sqlValues = [];
    foreach ($values as $key=>$value) {
        $sqlValues[] = "'".(array_key_exists($key,$webFields) ? (string)$value : db_escape_string((string)$value))."'";
    }
    $sql = 'INSERT INTO ordrer (ordrenr,'.implode(',',array_keys($values)).') VALUES ('.(int)$number.','.implode(',',$sqlValues).')';
    if ($connection instanceof \PgSql\Connection) {
        $row = db_fetch_array(db_select($sql.' RETURNING id',__FILE__.' linje '.__LINE__));
        if (!$row) { throw new RuntimeException('Order insert failed'); }
        return (int)$row['id'];
    }
    db_modify($sql,__FILE__.' linje '.__LINE__);
    $row = db_fetch_array(db_select("SELECT id FROM ordrer WHERE ordrenr=$number AND art='DO' ORDER BY id DESC",__FILE__.' linje '.__LINE__));
    return (int)$row['id'];
}
