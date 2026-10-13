<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/InvoicePaymentLedger.php --- ver 5.0.0 --- 2026.10.13
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
// 20261007 CL/LH Created: posts a card payment against an existing Saldi
//                invoice (clearing account debit, debitor collective account
//                credit, a negative openpost row) and settles the invoice's
//                open items when they balance. Ported from the period-billing
//                branch's BillingLedger::receipt(); the service-invoice import
//                and Stripe balance posting were not ported. Off unless
//                settings stripe/posting_enabled = on.
// 20261012 CL/LH Refuse test-mode ledger writes regardless of the posting switch.

// 20261012 CL/LH Lock shared accounting tables during checks/posting and propagate subsequent opening balances.
// 20261013 CL/LH Recalculate successor-year D/S, Z ranges and R references using the journal rules in the receipt transaction.

namespace Saldi\Stripe;

use DateTimeImmutable;
use RuntimeException;

final class InvoicePaymentLedger
{
    private InvoicePaymentStore $db;

    public function __construct(InvoicePaymentStore $db)
    {
        $this->db = $db;
    }

    public function enabled(): bool
    {
        // 20261012 CL/LH Defense in depth: even direct receipt() calls cannot post test money.
        return $this->db->mode() === 'live' && $this->db->setting('posting_enabled', 'off') === 'on';
    }

    /** @return array<string,mixed>|null */
    public function operation(string $kind, string $id): ?array
    {
        return $this->db->row('SELECT * FROM stripe_invoice_operations WHERE mode=? AND kind=? AND external_id=?', [$this->db->mode(), $kind, $id]);
    }

    private function remember(string $kind, string $id, int $order, int $amount, array $data): void
    {
        $this->db->query('INSERT INTO stripe_invoice_operations (mode,kind,external_id,order_id,amount_ore,data_json,created_at) VALUES (?,?,?,?,?,?,?)',
            [$this->db->mode(), $kind, $id, $order, $amount, InvoicePaymentStore::json($data), $this->db->now()]);
    }

    /** The open fiscal year (grupper art RA kodenr) containing $date; refuses closed years and closed VAT months. */
    private function year(string $date): int
    {
        InvoicePaymentMath::date($date);
        $matches = [];
        foreach ($this->db->rows("SELECT kodenr,box1,box2,box3,box4,box5 FROM grupper WHERE art='RA'") as $r) {
            $start = sprintf('%04d-%02d-01', $r['box2'], $r['box1']);
            $end = (new DateTimeImmutable(sprintf('%04d-%02d-01', $r['box4'], $r['box3'])))->format('Y-m-t');
            if ($date >= $start && $date <= $end) $matches[] = $r;
        }
        if (count($matches) !== 1) throw new RuntimeException('Bogføringsdatoen skal tilhøre præcis ét regnskabsår');
        if (trim((string)$matches[0]['box5']) !== 'on') throw new RuntimeException('Regnskabsåret er lukket');
        if ($this->db->driver() === 'pgsql') {
            $exists = $this->db->row("SELECT to_regclass('moms_periode_luk') AS t");
        } else {
            $exists = $this->db->row("SELECT name AS t FROM sqlite_master WHERE name='moms_periode_luk'");
        }
        if (!empty($exists['t']) && $this->db->row("SELECT kalender_aar FROM moms_periode_luk WHERE kalender_aar=? AND kalender_maaned=? AND status='closed'", [(int)substr($date, 0, 4), (int)substr($date, 5, 2)])) throw new RuntimeException('Momsperioden er lukket');
        return (int)$matches[0]['kodenr'];
    }

    private function account(string $setting, int $year): string
    {
        $number = $this->db->setting($setting);
        if (!preg_match('/^\d+$/', $number)) throw new RuntimeException('Konfigurér konto: ' . $setting);
        $r = $this->db->row('SELECT kontonr,kontotype,lukket FROM kontoplan WHERE kontonr=? AND regnskabsaar=?', [$number, $year]);
        if (!$r || !in_array($r['kontotype'], ['D', 'S'], true) || trim((string)$r['lukket']) !== '') throw new RuntimeException('Ugyldig eller lukket konto: ' . $setting);
        return $number;
    }

    private function debtorAccount(int $customer, int $year): string
    {
        $r = $this->db->row("SELECT g.box2 FROM adresser a JOIN grupper g ON g.art='DG' AND g.kodenr=a.gruppe AND g.fiscal_year=? WHERE a.id=?", [$year, $customer]);
        $number = (string)($r['box2'] ?? '');
        $account = $this->db->row('SELECT kontotype,lukket FROM kontoplan WHERE kontonr=? AND regnskabsaar=?', [$number ?: '0', $year]);
        if (!$account || !in_array($account['kontotype'], ['D', 'S'], true) || trim((string)$account['lukket']) !== '') throw new RuntimeException('Debitorgruppen mangler en åben samlekonto');
        return $number;
    }

    /** @param array<string,int> $movements Signed debit amounts in øre, summing to zero. */
    private function post(array $movements, string $date, string $description, int $year, int $order, string $invoice, int $customer): void
    {
        if (array_sum($movements) !== 0) throw new RuntimeException('Posteringen balancerer ikke');
        foreach ($movements as $account => $ore) {
            if (!$ore) continue;
            $this->db->query("INSERT INTO transaktioner (kontonr,bilag,transdate,logdate,logtime,beskrivelse,debet,kredit,faktura,kladde_id,projekt,ansat,afd,ordre_id,valuta,valutakurs,moms,adresser_id,kasse_nr) VALUES (?,0,?,?,?,?,?,?,?,0,'',0,0,?,'DKK',100,0,?,0)",
                [(string)$account, $date, InvoicePaymentMath::localDate($this->db->now()), gmdate('H:i:s', $this->db->now()), $description, InvoicePaymentMath::decimal(max(0, $ore)), InvoicePaymentMath::decimal(max(0, -$ore)), $invoice, $order, $customer]);
            $changed = $this->db->query('UPDATE kontoplan SET saldo=COALESCE(saldo,0)+? WHERE kontonr=? AND regnskabsaar=?', [InvoicePaymentMath::decimal($ore), (string)$account, $year])->rowCount();
            if ($changed !== 1) throw new RuntimeException('Kontoplanen er ændret under bogføring');
        }
    }

    /**
     * Post one card payment against invoice $orderId, once per $intentId.
     * @return int Remaining open balance in øre after the payment (negative = overpaid).
     */
    public function receipt(int $orderId, string $intentId, int $ore, string $date): int
    {
        if (!$this->enabled()) throw new RuntimeException('Automatisk bogføring af kortbetalinger er slået fra');
        if ($ore <= 0) throw new RuntimeException('Indbetaling skal være positiv');
        return $this->db->transaction(function () use ($orderId, $intentId, $ore, $date) {
            // 20261012 CL/LH Legacy allocators use MAX(udlign_id); table locks coordinate their reads/writes.
            if ($this->db->driver() === 'pgsql') {
                $this->db->query('LOCK TABLE openpost, kontoplan, transaktioner IN ACCESS EXCLUSIVE MODE');
            }
            $this->db->lock('stripe-receipt-' . $this->db->mode() . '-' . $intentId);
            if ($old = $this->operation('receipt', $intentId)) {
                if ((int)$old['order_id'] !== $orderId || (int)$old['amount_ore'] !== $ore) throw new RuntimeException('Betalingsreferencen er allerede brugt med et andet beløb eller en anden faktura');
                return (int)(json_decode($old['data_json'], true)['remaining_ore'] ?? 0);
            }
            $suffix = $this->db->driver() === 'pgsql' ? ' FOR UPDATE' : '';
            $order = $this->db->row('SELECT * FROM ordrer WHERE id=?' . $suffix, [$orderId]);
            if (!$order || $order['art'] !== 'DO' || (int)$order['status'] < 3 || !in_array((string)$order['valuta'], ['', 'DKK'], true)) throw new RuntimeException('Indbetalingen kræver en bogført DKK debitorfaktura');
            foreach ($this->db->rows("SELECT valuta FROM openpost WHERE konto_id=? AND faktnr=? AND COALESCE(udlignet,'0')<>'1'", [$order['konto_id'], $order['fakturanr']]) as $item) {
                if (!in_array(strtoupper(trim((string)$item['valuta'])), ['', 'DKK'], true)) throw new RuntimeException('Fakturaens åbne poster skal være i DKK; afstem valuta manuelt');
            }
            $customer = $this->db->row('SELECT id,kontonr FROM adresser WHERE id=?', [$order['konto_id']]);
            if (!$customer) throw new RuntimeException('Debitor findes ikke');
            $year = $this->year($date);
            $ar = $this->debtorAccount((int)$order['konto_id'], $year);
            $clearing = $this->account('clearing_account', $year);
            if ($ar === $clearing) throw new RuntimeException('Clearingkonto og debitorsamlekonto skal være forskellige');
            $text = 'Kortbetaling faktura ' . $order['fakturanr'];
            $this->post([$clearing => $ore, $ar => -$ore], $date, $text, $year, $orderId, (string)$order['fakturanr'], (int)$order['konto_id']);
            $this->db->query("INSERT INTO openpost (konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,transdate,uxtid,kladde_id,valuta,valutakurs,forfaldsdate,betalings_id) VALUES (?,?,?,?,?,?,'0',?,?,0,'DKK',100,?,?)",
                [$customer['id'], $customer['kontonr'], $order['fakturanr'], InvoicePaymentMath::decimal(-$ore), $orderId, $text, $date, (string)$this->db->now(), $date, $intentId]);
            $balance = $this->allocate($order, $date);
            $this->followingOpeningBalances($year);
            $this->remember('receipt', $intentId, $orderId, $ore, ['date' => $date, 'remaining_ore' => $balance]);
            if ($balance < 0) $this->db->audit('order', $orderId, 'overpayment_review', 'Stripe', ['remaining_ore' => $balance, 'payment_intent' => $intentId]);
            return $balance;
        });
    }

    /** Rebuild following years with the journal transfer rules, on this same PDO transaction. */
    private function followingOpeningBalances(int $postedYear): void
    {
        $years = [];
        foreach ($this->db->rows("SELECT * FROM grupper WHERE art='RA'") as $r) $years[(int)$r['kodenr']] = $r;
        for ($sourceYear = $postedYear; isset($years[$sourceYear], $years[$sourceYear + 1]); $sourceYear++) {
            $targetYear = $sourceYear + 1;
            $source = $years[$sourceYear];
            if (($source['box10'] ?? '') === 'on' || ($years[$targetYear]['box10'] ?? '') === 'on') break;
            $start = sprintf('%04d-%02d-01', $source['box2'], $source['box1']);
            $endBase = sprintf('%04d-%02d-01', $source['box4'], $source['box3']);
            InvoicePaymentMath::date($start);
            InvoicePaymentMath::date($endBase);
            $end = (new DateTimeImmutable($endBase))->format('Y-m-t');
            $opening = [];
            foreach ($this->db->rows("SELECT kontonr FROM kontoplan WHERE regnskabsaar=? AND kontotype='S'", [$targetYear]) as $r) $opening[(int)$r['kontonr']] = 0;
            if (!$opening) break;
            $movement = [];
            foreach ($this->db->rows('SELECT kontonr,debet,kredit FROM transaktioner WHERE transdate>=? AND transdate<=?', [$start, $end]) as $r) {
                $account = (int)$r['kontonr'];
                $movement[$account] = ($movement[$account] ?? 0) + InvoicePaymentMath::fromDb($r['debet']) - InvoicePaymentMath::fromDb($r['kredit']);
            }
            $profit = 0;
            $result = null;
            $missing = false;
            foreach ($this->db->rows("SELECT kontonr,kontotype,primo,overfor_til FROM kontoplan WHERE regnskabsaar=? AND kontotype IN ('S','D','X') ORDER BY kontonr", [$sourceYear]) as $r) {
                $account = (int)$r['kontonr'];
                $amount = $movement[$account] ?? 0;
                if ($r['kontotype'] === 'D') $profit += InvoicePaymentMath::fromDb($r['primo']) + $amount;
                elseif ($r['kontotype'] === 'X' && $result === null) $result = $r;
                elseif ($r['kontotype'] === 'S') {
                    $destination = (int)$r['overfor_til'];
                    if (!isset($opening[$destination])) $destination = $account;
                    if (isset($opening[$destination])) $opening[$destination] += InvoicePaymentMath::fromDb($r['primo']) + $amount;
                    else $missing = true;
                }
            }
            if ($result !== null && isset($opening[(int)$result['overfor_til']])) {
                $opening[(int)$result['overfor_til']] += $profit + ($movement[(int)$result['kontonr']] ?? 0);
            } else $missing = true;
            if ($missing) break;
            foreach ($opening as $account => $amount) {
                $this->db->query("UPDATE kontoplan SET primo=? WHERE regnskabsaar=? AND kontonr=? AND kontotype='S'", [InvoicePaymentMath::decimal($amount), $targetYear, $account]);
            }
            $target = $years[$targetYear];
            $targetStart = sprintf('%04d-%02d-01', $target['box2'], $target['box1']);
            $targetEndBase = sprintf('%04d-%02d-01', $target['box4'], $target['box3']);
            InvoicePaymentMath::date($targetStart);
            InvoicePaymentMath::date($targetEndBase);
            $targetEnd = (new DateTimeImmutable($targetEndBase))->format('Y-m-t');
            $targetMovements = [];
            foreach ($this->db->rows('SELECT kontonr,debet,kredit FROM transaktioner WHERE transdate>=? AND transdate<=?', [$targetStart, $targetEnd]) as $r) {
                $account = (int)$r['kontonr'];
                $targetMovements[$account] = ($targetMovements[$account] ?? 0) + InvoicePaymentMath::fromDb($r['debet']) - InvoicePaymentMath::fromDb($r['kredit']);
            }
            $this->recalculateBalances($targetYear, $targetMovements);
        }
    }

    /**
     * Apply includes/genberegn.php's D/S balances, Z ranges and R references on the receipt transaction.
     * @param array<int,int> $movements Signed fiscal-year movements in øre, keyed by account number.
     */
    private function recalculateBalances(int $year, array $movements): void
    {
        // 20261013 CL/LH genberegn resets non-S openings and all balances, then rebuilds only D/S transactions.
        $accounts = $this->db->rows('SELECT id,kontonr,kontotype,primo,fra_kto FROM kontoplan WHERE regnskabsaar=? ORDER BY kontonr', [$year]);
        foreach ($accounts as &$account) {
            $primo = $account['kontotype'] === 'S' ? InvoicePaymentMath::fromDb($account['primo']) : 0;
            $account['balance_ore'] = in_array($account['kontotype'], ['D', 'S'], true)
                ? $primo + ($movements[(int)$account['kontonr']] ?? 0) : 0;
            $this->db->query('UPDATE kontoplan SET primo=?,saldo=? WHERE id=?',
                [InvoicePaymentMath::decimal($primo), InvoicePaymentMath::decimal($account['balance_ore']), $account['id']]);
        }
        unset($account);
        $preceding = [];
        foreach ($accounts as $account) {
            if ($account['kontotype'] === 'H') {
                continue;
            }
            // 20261013 CL/LH Z sums earlier non-H/non-Z balances (including R); R copies an earlier referenced Z.
            if (in_array($account['kontotype'], ['Z', 'R'], true)) {
                $balance = 0;
                foreach ($preceding as $prior) {
                    if ($account['kontotype'] === 'R') {
                        if ($prior['kontotype'] === 'Z' && (int)$prior['kontonr'] === (int)$account['fra_kto']) {
                            $balance = $prior['balance_ore'];
                        }
                    } elseif ($prior['kontotype'] !== 'Z' && (int)$prior['kontonr'] >= (int)$account['fra_kto']
                        && (int)$prior['kontonr'] <= (int)$account['kontonr']) {
                        $balance += $prior['balance_ore'];
                    }
                }
                $account['balance_ore'] = $balance;
                $this->db->query('UPDATE kontoplan SET saldo=? WHERE id=?', [InvoicePaymentMath::decimal($balance), $account['id']]);
            }
            $preceding[] = $account;
        }
    }

    /**
     * Mark every open item of the invoice settled once they sum to zero. Matches
     * on debitor + invoice number only, so payments booked through the
     * kassekladde (which carry faktnr but not our refnr) count too.
     */
    private function allocate(array $order, string $date): int
    {
        $suffix = $this->db->driver() === 'pgsql' ? ' FOR UPDATE' : '';
        $this->db->lock('stripe-openpost-allocation');
        $items = $this->db->rows("SELECT id,amount FROM openpost WHERE konto_id=? AND faktnr=? AND COALESCE(udlignet,'0')<>'1'" . $suffix, [$order['konto_id'], $order['fakturanr']]);
        $balance = 0;
        foreach ($items as $item) $balance += InvoicePaymentMath::fromDb($item['amount']);
        if ($balance === 0 && $items) {
            $allocation = (int)$this->db->row('SELECT COALESCE(MAX(udlign_id),0)+1 AS n FROM openpost')['n'];
            foreach ($items as $item) $this->db->query("UPDATE openpost SET udlignet='1',udlign_id=?,udlign_date=? WHERE id=?", [$allocation, $date, $item['id']]);
            $this->db->query('UPDATE ordrer SET betalt=? WHERE id=?', [$date, (int)$order['id']]);
        }
        return $balance;
    }
}
