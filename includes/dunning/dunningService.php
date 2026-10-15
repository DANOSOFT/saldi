<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/dunning/dunningService.php --- ver 5.0.0 --- 2026.10.03
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

/** Caller owns every write and the numbering/eligibility lock through commit or rollback. */
function dunning_lock(): void
{
    global $connection, $db_type, $db_transaktion_depth;
    if ((int)$db_transaktion_depth < 1) {
        throw new LogicException('Dunning requires a caller-owned transaction');
    }
    if ($db_type === 'postgresql') {
        if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_INTRANS) {
            throw new LogicException('Dunning requires an open PostgreSQL transaction');
        }
        db_select("SELECT pg_advisory_xact_lock(hashtext('saldi:dunning'))", __FILE__ . ' linje ' . __LINE__);
    }
    // Non-PostgreSQL web callers retain legacy allocation without a concurrency guarantee.
}

/** @return array{rate:float,fee:array|null,interest:array|null,days2:int,days3:int} */
function dunning_configuration(int $level): array
{
    $form = 5 + $level;
    $rate = 0;
    $templates = db_select("SELECT str FROM formularer WHERE beskrivelse='GEBYR' AND formular=$form ORDER BY formular", __FILE__ . ' linje ' . __LINE__);
    while ($template = db_fetch_array($templates)) {
        $rate = (float)$template['str'];
    }
    $fee = db_fetch_array(db_select("SELECT * FROM varer WHERE id IN
        (SELECT xb FROM formularer WHERE beskrivelse='GEBYR' AND formular=$form)", __FILE__ . ' linje ' . __LINE__));
    $interest = db_fetch_array(db_select("SELECT * FROM varer WHERE id IN
        (SELECT yb FROM formularer WHERE beskrivelse='GEBYR' AND formular=$form)", __FILE__ . ' linje ' . __LINE__));
    $days = db_fetch_array(db_select("SELECT box6,box7 FROM grupper WHERE art='DIV' AND kodenr=4", __FILE__ . ' linje ' . __LINE__));
    return ['rate'=>$rate, 'fee'=>$fee ?: null, 'interest'=>$interest ?: null,
        'days2'=>(int)ifset($days, 'box6', 0), 'days3'=>(int)ifset($days, 'box7', 0)];
}

/** Escape all persisted values, including card/line text copied from another record. @return int */
function dunning_insert(string $table, array $fields): int
{
    global $connection, $db_type;
    if (!in_array($table, ['ordrer','ordrelinjer'], true)) {
        throw new InvalidArgumentException('Invalid dunning table');
    }
    $values = [];
    foreach ($fields as $column => $value) {
        if (!preg_match('/^[a-z_0-9]+$/D', $column)) {
            throw new InvalidArgumentException('Invalid dunning column');
        }
        $values[] = $value === null ? 'NULL' : "'" . db_escape_string((string)$value) . "'";
    }
    $query = 'INSERT INTO ' . $table . '(' . implode(',', array_keys($fields)) . ') VALUES(' . implode(',', $values) . ')';
    if ($db_type === 'mysqli' || $db_type === 'mysql') {
        db_modify($query, __FILE__ . ' linje ' . __LINE__);
        return (int)mysqli_insert_id($connection);
    }
    $query .= ' RETURNING id';
    $result = db_fetch_array(db_select($query, __FILE__ . ' linje ' . __LINE__));
    if (!$result) {
        throw new RuntimeException('Dunning insert failed');
    }
    return (int)$result['id'];
}

/** Copy the web's customer snapshot and actor fields. @return array<string,mixed> */
function dunning_header(array $customer, string $date): array
{
    global $brugernavn;
    $fields = [];
    foreach (['kontonr','firmanavn','addr1','addr2','postnr','bynavn','land','betalingsdage','betalingsbet','cvrnr','ean',
        'institution','notes','kontakt','email'] as $column) {
        $fields[$column] = (string)ifset($customer, $column, '');
    }
    return array_replace($fields, ['konto_id'=>(int)$customer['id'], 'ordredate'=>$date, 'levdate'=>$date,
        'fakturadate'=>$date, 'momssats'=>0, 'hvem'=>(string)$brugernavn, 'tidspkt'=>date('U'), 'status'=>2, 'betalt'=>'-']);
}

/** @return float|null Null when the tenant has no exchange rate for this date. */
function dunning_exchange_rate(string $currency, string $date, bool $advance = false): ?float
{
    if ($currency === '' || $currency === 'DKK') {
        return 100;
    }
    $currencySql = db_escape_string($currency);
    $dateSql = db_escape_string($date);
    // Preserve the original first-level oldest-rate lookup; advancement uses the latest rate.
    $order = $advance ? ' DESC LIMIT 1' : '';
    $rate = db_fetch_array(db_select("SELECT valuta.kurs FROM valuta,grupper WHERE grupper.art='VK'
        AND grupper.box1='$currencySql' AND valuta.gruppe=grupper.kodenr::INT AND valuta.valdate<='$dateSql'
        ORDER BY valuta.valdate$order", __FILE__ . ' linje ' . __LINE__));
    return $rate && (float)$rate['kurs'] > 0 ? (float)$rate['kurs'] : null;
}

/**
 * Manual mode has today's strict due-date cutoff; the automatic web loop supplies its grace cutoff.
 * @return array{rykker_id:int|null,level:int,skipped:string|null,legacy_sum?:float}
 */
function dunning_create_for_account(int $kontoId, string $rykkerdate, ?string $cutoff = null): array
{
    dunning_lock();
    $cutoffSql = db_escape_string($cutoff ?? $rykkerdate);
    $customer = db_fetch_array(db_select("SELECT * FROM adresser WHERE id=$kontoId", __FILE__ . ' linje ' . __LINE__));
    if (!$customer) {
        return ['rykker_id'=>null, 'level'=>1, 'skipped'=>'not_found'];
    }
    $config = dunning_configuration(1);
    $group = (int)ifset($customer, 'gruppe', 0);
    $groupRow = db_fetch_array(db_select("SELECT box3,box4 FROM grupper WHERE art='DG' AND kodenr=$group", __FILE__ . ' linje ' . __LINE__));
    $currency = trim((string)ifset($groupRow, 'box3', '')) ?: 'DKK';
    $rate = dunning_exchange_rate($currency, $rykkerdate);
    if ($rate === null) {
        return ['rykker_id'=>null, 'level'=>1, 'skipped'=>'missing_exchange_rate'];
    }
    $posts = [];
    $query = db_select("SELECT * FROM openpost WHERE udlignet='0' AND ((forfaldsdate<'$cutoffSql' AND amount>0) OR amount<0)
        AND konto_id=$kontoId ORDER BY id", __FILE__ . ' linje ' . __LINE__);
    while ($post = db_fetch_array($query)) {
        $postId = (int)$post['id'];
        $ref = (int)$post['refnr'];
        $onRykker = db_fetch_array(db_select("SELECT o.id FROM ordrer o,ordrelinjer l WHERE l.enhed='$postId'
            AND o.id=l.ordre_id AND o.betalt!='on'", __FILE__ . ' linje ' . __LINE__));
        $feeOnRykker = db_fetch_array(db_select("SELECT id FROM ordrer WHERE id=$ref AND art LIKE 'R%' AND betalt!='on'", __FILE__ . ' linje ' . __LINE__));
        if (!$onRykker && !$feeOnRykker) {
            $posts[] = $post;
        }
    }
    if (!$posts) {
        return ['rykker_id'=>null, 'level'=>1, 'skipped'=>'nothing_to_dun'];
    }
    $number = db_fetch_array(db_select("SELECT MAX(ordrenr) AS number FROM ordrer WHERE art LIKE 'R%'", __FILE__ . ' linje ' . __LINE__));
    $number = (int)ifset($number, 'number', 0) + 1;
    $header = dunning_header($customer, $rykkerdate);
    $ref = (string)ifset($customer, 'kontoansvarlig', '');
    // The legacy reference lookup ultimately reads the card's 'navn' rather than the employee row.
    if ($ref) {
        $ref = (string)ifset($customer, 'navn', '');
    }
    if ($config['days2']) {
        $header['betalingsdage'] = $config['days2'];
        $header['betalingsbet'] = 'Netto';
    }
    $id = dunning_insert('ordrer', array_replace($header, ['ordrenr'=>$number, 'art'=>'R1', 'valuta'=>$currency,
        'valutakurs'=>$rate, 'fakturanr'=>$number . '-1', 'ref'=>$ref, 'felt_5'=>'']));
    $position = 0;
    $sum = 0;
    foreach ($posts as $post) {
        $description = (string)$post['beskrivelse'];
        $postCurrency = trim((string)$post['valuta']) ?: 'DKK';
        if (($postCurrency !== 'DKK' || $currency !== 'DKK') && $postCurrency !== $currency) {
            $description .= ' (' . $postCurrency . ' ' . dkdecimal($post['amount']) . ')';
        }
        dunning_insert('ordrelinjer', ['posnr'=>++$position, 'enhed'=>(int)$post['id'], 'ordre_id'=>$id,
            'serienr'=>$post['transdate'], 'beskrivelse'=>$description]);
        $interest = $config['rate'] ? dunning_find_rente($config['rate'], $post['forfaldsdate'] ?: $post['transdate'], (float)$post['amount']) : 0;
        if ($interest && $config['interest']) {
            dunning_insert('ordrelinjer', ['posnr'=>++$position, 'ordre_id'=>$id, 'vare_id'=>(int)$config['interest']['id'],
                'varenr'=>$config['interest']['varenr'], 'serienr'=>date('Y-m-d'), 'beskrivelse'=>$config['interest']['beskrivelse'], 'antal'=>1, 'pris'=>$interest]);
            $sum += $interest;
        }
    }
    if ($config['fee']) {
        $fee = (float)$config['fee']['salgspris'] * 100 / $rate;
        dunning_insert('ordrelinjer', ['posnr'=>++$position, 'ordre_id'=>$id, 'varenr'=>$config['fee']['varenr'],
            'vare_id'=>(int)$config['fee']['id'], 'beskrivelse'=>$config['fee']['beskrivelse'], 'antal'=>1, 'pris'=>$fee, 'serienr'=>$rykkerdate]);
        $sum += $fee;
        db_modify("UPDATE ordrer SET sum=" . (float)$sum . " WHERE id=$id", __FILE__ . ' linje ' . __LINE__);
    }
    return ['rykker_id'=>$id, 'level'=>1, 'skipped'=>null, 'legacy_sum'=>(float)$sum];
}

/**
 * Advance exactly the selected web series; an existing successor prevents racing duplicate creates.
 * Fee posting remains a separate web operation. The legacy base-header sum assignment is preserved.
 * The web wrapper alone supplies its batch accumulator; standalone and mobile calls start at zero.
 * @return array{rykker_id:int|null,level:int,skipped:string|null,legacy_sum?:float}
 */
function dunning_advance(int $rykkerId, string $rykkerdate, float $legacySum = 0): array
{
    dunning_lock();
    $base = db_fetch_array(db_select("SELECT * FROM ordrer WHERE id=$rykkerId AND art IN ('R1','R2','R3')", __FILE__ . ' linje ' . __LINE__));
    if (!$base) {
        return ['rykker_id'=>null, 'level'=>0, 'skipped'=>'not_found'];
    }
    $level = (int)substr($base['art'], 1) + 1;
    if ($level > 3) {
        return ['rykker_id'=>null, 'level'=>3, 'skipped'=>'top_level_reached'];
    }
    $number = (int)$base['ordrenr'];
    $kontoId = (int)$base['konto_id'];
    $successor = db_fetch_array(db_select("SELECT id FROM ordrer WHERE konto_id=$kontoId AND ordrenr=$number
        AND art>'" . db_escape_string($base['art']) . "' AND art IN ('R2','R3')", __FILE__ . ' linje ' . __LINE__));
    if ($successor) {
        return ['rykker_id'=>null, 'level'=>$level, 'skipped'=>'level_changed'];
    }
    $customer = db_fetch_array(db_select("SELECT * FROM adresser WHERE id=$kontoId", __FILE__ . ' linje ' . __LINE__));
    if (!$customer) {
        return ['rykker_id'=>null, 'level'=>$level, 'skipped'=>'not_found'];
    }
    $config = dunning_configuration($level);
    $currency = trim((string)$base['valuta']) ?: 'DKK';
    $rate = dunning_exchange_rate($currency, $rykkerdate, true);
    if ($rate === null) {
        return ['rykker_id'=>null, 'level'=>$level, 'skipped'=>'missing_exchange_rate'];
    }
    $id = dunning_insert('ordrer', array_replace(dunning_header($customer, $rykkerdate), ['ordrenr'=>$number,
        'betalingsdage'=>$config['days3'], 'betalingsbet'=>'Netto', 'art'=>'R'.$level, 'ref'=>$base['ref'],
        'valuta'=>$currency, 'valutakurs'=>$rate, 'fakturanr'=>$number.'-'.$level, 'email'=>$base['email'],
        'kontakt'=>$base['kontakt'], 'mail_fakt'=>$base['mail_fakt']]));
    $position = 0;
    $interestBase = (float)$base['sum'];
    $lines = db_select("SELECT * FROM ordrelinjer WHERE ordre_id=$rykkerId ORDER BY posnr,id", __FILE__ . ' linje ' . __LINE__);
    while ($line = db_fetch_array($lines)) {
        if (!$line['vare_id'] && $line['enhed']) {
            dunning_insert('ordrelinjer', ['posnr'=>++$position, 'enhed'=>$line['enhed'], 'ordre_id'=>$id,
                'serienr'=>$line['serienr'], 'beskrivelse'=>$line['beskrivelse']]);
            $postId = (int)$line['enhed'];
            $post = db_fetch_array(db_select("SELECT amount FROM openpost WHERE id=$postId", __FILE__ . ' linje ' . __LINE__));
            $interestBase += (float)ifset($post, 'amount', 0);
        }
    }
    if ((float)$base['sum']) {
        // Preserve ny_rykker's fee-reference lookup; a not-yet-posted fee has an empty reference.
        $feeAmount = (float)$base['sum'];
        if ((float)$base['valutakurs'] && (float)$base['valutakurs'] != 100) {
            $feeAmount *= (float)$base['valutakurs'] / 100;
        }
        $feeAmount = (float)afrund($feeAmount,2);
        $post = db_fetch_array(db_select("SELECT * FROM openpost WHERE refnr=$rykkerId AND konto_id=$kontoId AND amount=$feeAmount", __FILE__ . ' linje ' . __LINE__));
        dunning_insert('ordrelinjer', ['posnr'=>++$position, 'enhed'=>ifset($post, 'id', ''), 'ordre_id'=>$id,
            'serienr'=>$base['fakturadate'], 'beskrivelse'=>ifset($post, 'beskrivelse', '')]);
    }
    $sum = $legacySum;
    $interest = $config['rate'] ? dunning_find_rente($config['rate'], $base['fakturadate'], $interestBase) : 0;
    if ($interest && $config['interest']) {
        dunning_insert('ordrelinjer', ['posnr'=>++$position, 'ordre_id'=>$id, 'vare_id'=>(int)$config['interest']['id'],
            'varenr'=>$config['interest']['varenr'], 'serienr'=>date('Y-m-d'), 'beskrivelse'=>$config['interest']['beskrivelse'], 'antal'=>1, 'pris'=>$interest]);
        $sum += $interest;
    }
    if ($config['fee']) {
        $fee = (float)$config['fee']['salgspris'];
        $sum += $fee;
        $fee = (float)afrund($fee * 100 / $rate,2);
        $sum = (float)afrund($sum * 100 / $rate,2);
        // The old next-level fee line has no posnr, and its sum update targets the base id.
        dunning_insert('ordrelinjer', ['ordre_id'=>$id, 'varenr'=>$config['fee']['varenr'], 'vare_id'=>(int)$config['fee']['id'],
            'beskrivelse'=>$config['fee']['beskrivelse'], 'antal'=>1, 'pris'=>$fee, 'serienr'=>$rykkerdate]);
        db_modify("UPDATE ordrer SET sum=$sum WHERE id=$rykkerId", __FILE__ . ' linje ' . __LINE__);
    }
    return ['rykker_id'=>$id, 'level'=>$level, 'skipped'=>null, 'legacy_sum'=>(float)$sum];
}

/** Compound monthly interest, including the started month, as in the original page. @return float */
function dunning_find_rente(float $rentesats, string $forfaldsdate, float $amount): float
{
    [$year,$month,$day] = array_map('intval', explode('-', $forfaldsdate));
    $months = (int)date('m') - $month + ((int)date('Y') - $year) * 12;
    if ((int)date('d') >= $day) {
        $months++;
    }
    $interest = 0;
    for ($index = 1; $index <= $months; $index++) {
        $interest += ($amount + $interest) * $rentesats / 100;
    }
    return round($interest,2);
}
