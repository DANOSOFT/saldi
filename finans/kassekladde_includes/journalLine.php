<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/journalLine.php --- ver 5.0.0 --- 2026.10.05
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
// 20261005 CL/LH Pad stored fiscal-year months in the engine, not only in the web adapter.

require_once __DIR__ . '/vatCodes.php';
require_once __DIR__ . '/bilagNumber.php';
require_once __DIR__ . '/../../includes/finans/kladdeJournal.php';

/**
 * Validate resolved account numbers and an ISO date against the selected fiscal year.
 * Empty/zero accounts are allowed on an unbalanced draft. No lookup UI, writes or output.
 * The web adapter retains its text/shortcut lookups and validation ordering.
 *
 * @param array{kladde_id?:int,transdate?:string,d_type?:string,debet?:int|string,k_type?:string,kredit?:int|string,afd?:int|string,projekt?:int|string,valuta?:string,base_currency?:string} $line
 * @param int $regnaar Internal grupper RA kodenr, not a calendar year.
 * @return list<array{field:string,reason:string}>
 */
function kladde_line_check(array $line, int $regnaar): array
{
    $errors = [];
    $journalId = (int)ifset($line, 'kladde_id', 0);
    if ($journalId) {
        $journal = db_fetch_array(db_select("SELECT bogfort FROM kladdeliste WHERE id=$journalId", __FILE__ . ' linje ' . __LINE__));
        if (!$journal || $journal['bogfort'] !== '-') {
            $errors[] = ['field'=>'kladde_id', 'reason'=>'journal_posted'];
        }
    }
    foreach (['debet'=>'d_type', 'kredit'=>'k_type'] as $field => $typeField) {
        $number = scalar_input_text(ifset($line, $field, ''));
        if ($number === '' || $number === '0') {
            continue;
        }
        $type = strtoupper(scalar_input_text(ifset($line, $typeField, 'F')));
        $type = in_array($type, ['D','K'], true) ? $type : 'F';
        $reason = $type === 'F' ? 'account_invalid' : ($type === 'D' ? 'debitor_invalid' : 'kreditor_invalid');
        if (!is_account_number($number)) {
            $errors[] = ['field'=>$field, 'reason'=>$reason];
            continue;
        }
        if ($type === 'F') {
            $account = (int)$number;
            $row = db_fetch_array(db_select("SELECT lukket FROM kontoplan WHERE kontonr=$account
                AND regnskabsaar=$regnaar AND kontotype != 'H' AND kontotype != 'Z'", __FILE__ . ' linje ' . __LINE__));
            if (!$row) {
                $errors[] = ['field'=>$field, 'reason'=>'account_invalid'];
            } elseif ($row['lukket']) {
                $errors[] = ['field'=>$field, 'reason'=>'account_closed'];
            }
        } else {
            $numbers = [];
            $addresses = db_select("SELECT kontonr FROM adresser WHERE art LIKE '%$type%'", __FILE__ . ' linje ' . __LINE__);
            while ($address = db_fetch_array($addresses)) {
                $numbers[] = trim($address['kontonr']);
            }
            // The web uses loose numeric membership (including leading zeroes).
            if (!in_array($number, $numbers)) {
                $errors[] = ['field'=>$field, 'reason'=>$reason];
            }
        }
    }
    $date = scalar_input_text(ifset($line, 'transdate', ''));
    $yearMonth = str_replace('-', '', substr($date, 0, 7));
    $open = false;
    $years = db_select("SELECT box1,box2,box3,box4 FROM grupper WHERE art='RA' AND box5='on' AND kodenr=$regnaar", __FILE__ . ' linje ' . __LINE__);
    while ($year = db_fetch_array($years)) {
        // Keep checkOpenFiscalYear()'s selected-year, month-level boundary rule; pad months stored as '7' like the web adapter does.
        $start = $year['box2'] . str_pad(trim($year['box1']), 2, '0', STR_PAD_LEFT);
        $end = $year['box4'] . str_pad(trim($year['box3']), 2, '0', STR_PAD_LEFT);
        if ($yearMonth >= $start && $yearMonth <= $end) {
            $open = true;
        }
    }
    if (!$open) {
        $errors[] = ['field'=>'transdate', 'reason'=>'outside_fiscal_year'];
    }
    foreach (['afd'=>['AFD','department_invalid'], 'projekt'=>['PRJ','project_invalid']] as $field => [$art,$reason]) {
        $number = scalar_input_text(ifset($line, $field, ''));
        if ($number !== '' && $number !== '0') {
            $number = db_escape_string($number);
            if (!db_fetch_array(db_select("SELECT id FROM grupper WHERE art='$art' AND kodenr='$number'", __FILE__ . ' linje ' . __LINE__))) {
                $errors[] = ['field'=>$field, 'reason'=>$reason];
            }
        }
    }
    $baseCurrency = (string)ifset($line, 'base_currency', ifset($GLOBALS, 'baseCurrency', 'DKK'));
    $currency = scalar_input_text(ifset($line, 'valuta', $baseCurrency));
    if ($currency !== '' && $currency !== $baseCurrency) {
        $currency = db_escape_string($currency);
        if (!db_fetch_array(db_select("SELECT kodenr FROM grupper WHERE art='VK' AND box1='$currency'", __FILE__ . ' linje ' . __LINE__))) {
            $errors[] = ['field'=>'valuta', 'reason'=>'currency_unknown'];
        }
    }
    return $errors;
}

/**
 * Saldi's last recorded rate at or before the ISO date; missing currency/rate returns null.
 *
 * @param string $currency ISO currency name from grupper VK.box1.
 * @param string $date ISO date, already validated by the caller.
 * @return array{rate:string,rate_date:string,source:'saldi_valuta'}|null
 */
function kladde_line_rate(string $currency, string $date): ?array
{
    $currency = db_escape_string($currency);
    $date = db_escape_string($date);
    $group = db_fetch_array(db_select("SELECT kodenr FROM grupper WHERE art='VK' AND box1='$currency'", __FILE__ . ' linje ' . __LINE__));
    if (!$group) {
        return null;
    }
    $code = (int)$group['kodenr'];
    $rate = db_fetch_array(db_select("SELECT kurs,valdate FROM valuta WHERE gruppe=$code AND valdate <= '$date' ORDER BY valdate DESC LIMIT 1", __FILE__ . ' linje ' . __LINE__));
    return $rate ? ['rate'=>(string)$rate['kurs'], 'rate_date'=>$rate['valdate'], 'source'=>'saldi_valuta'] : null;
}

/**
 * Insert one resolved draft, allocate its voucher number and shift subsequent positions.
 * Negative decimal amounts swap accounts, types and both VAT codes. VAT values are the
 * caller's resolved values; momsfri='on' is the web checkbox value. Owns no transaction.
 * Voucher fallback uses the caller's $regnaar from the legacy engine context.
 *
 * @param int $kladdeId Journal selected/locked by the caller.
 * @param array{transdate:string,amount:int|float|string,bilag?:int|string,beskrivelse?:string,d_type?:string,debet?:int|string,k_type?:string,kredit?:int|string,debetvat?:string,kreditvat?:string,faktura?:string,momsfri?:string,afd?:int|string,projekt?:int|string,ansat?:int,valuta?:int,forfaldsdate?:?string,betal_id?:string,valutakurs?:int|float|string} $fields Amount is decimal, valuta is VK.kodenr (0 for base), ansat is the resolved employee id. Raw, unescaped text only.
 * @return int Connection-local inserted id, or zero on a failed legacy write.
 */
function kassekladde_insert_line(int $kladdeId, array $fields): int
{
    $amount = (float)$fields['amount'];
    if ($amount < 0) {
        foreach ([['debet','kredit'], ['d_type','k_type'], ['debetvat','kreditvat']] as [$debit,$credit]) {
            [$fields[$debit], $fields[$credit]] = [ifset($fields, $credit, ''), ifset($fields, $debit, '')];
        }
        $amount *= -1;
    }
    $bilag = (int)ifset($fields, 'bilag', 0);
    if ($bilag <= 0) {
        $bilag = bilagNextNumberForJournal($kladdeId);
    }
    $date = db_escape_string($fields['transdate']);
    $position = db_fetch_array(db_select("SELECT COALESCE(MAX(pos),0) AS max_pos FROM kassekladde
        WHERE kladde_id=$kladdeId AND (bilag < $bilag OR (bilag=$bilag AND transdate <= '$date'))", __FILE__ . ' linje ' . __LINE__));
    $nextPos = (int)$position['max_pos'] + 1;
    $status = db_modify("UPDATE kassekladde SET pos=pos+1 WHERE kladde_id=$kladdeId AND pos >= $nextPos", __FILE__ . ' linje ' . __LINE__);
    if (substr($status, 0, 1) !== '0') {
        return 0;
    }
    $values = ['bilag'=>(string)$bilag, 'transdate'=>"'$date'", 'amount'=>(string)$amount,
        'kladde_id'=>(string)$kladdeId, 'pos'=>(string)$nextPos];
    foreach (['debet','kredit','afd','ansat','valuta'] as $field) {
        $values[$field] = (string)(int)ifset($fields, $field, 0);
    }
    foreach (['beskrivelse','d_type','k_type','debetvat','kreditvat','faktura','momsfri','projekt'] as $field) {
        $values[$field] = "'" . db_escape_string((string)ifset($fields, $field, '')) . "'";
    }
    if (ifset($fields, 'forfaldsdate')) {
        $values['forfaldsdate'] = "'" . db_escape_string($fields['forfaldsdate']) . "'";
        $values['betal_id'] = "'" . db_escape_string((string)ifset($fields, 'betal_id', '')) . "'";
    }
    if (array_key_exists('valutakurs', $fields)) {
        $values['valutakurs'] = (string)(float)$fields['valutakurs'];
    }
    $status = db_modify('INSERT INTO kassekladde (' . implode(',', array_keys($values)) . ') VALUES (' . implode(',', $values) . ')', __FILE__ . ' linje ' . __LINE__);
    if (substr($status, 0, 1) !== '0') {
        return 0;
    }
    if (in_array(ifset($GLOBALS, 'db_type'), ['mysql','mysqli'], true)) {
        return (int)mysqli_insert_id(db_query_connection(false));
    }
    $row = db_fetch_array(db_select("SELECT currval(pg_get_serial_sequence('kassekladde','id')) AS id", __FILE__ . ' linje ' . __LINE__));
    return (int)ifset($row, 'id', 0);
}

/**
 * Web lease rule: the same username may reopen its own journal.
 * Mobile approval must use kladde_lease_holder() directly, including self leases.
 *
 * @return string|null Active other user's name, or null.
 */
function kladde_lock_holder(int $kladdeId, string $brugernavn): ?string
{
    $lease = kladde_lease_holder($kladdeId);
    return $lease !== null && $lease['holder'] !== $brugernavn ? $lease['holder'] : null;
}
