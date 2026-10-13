<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/InvoicePaymentEligibility.php --- 2026.10.12
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
// 20261012 CL/LH Shared read-only eligibility prevents competing collection channels.

namespace Saldi\Stripe;

final class InvoicePaymentEligibility
{
    private InvoicePaymentStore $db;

    public function __construct(InvoicePaymentStore $db)
    {
        $this->db = $db;
    }

    /** @return list<string> All collection blockers, in stable priority order. */
    public function collectionReasons(int $kontoId): array
    {
        return $this->collectionReasonsFor([$kontoId])[$kontoId];
    }

    /** @return list<string> Collection blockers followed by switch and saved-card blockers. */
    public function autopayReasons(int $kontoId): array
    {
        return $this->autopayReasonsFor([$kontoId])[$kontoId];
    }

    /**
     * Batch reads against the caller's tenant connection; no runtime or Stripe dependency.
     * @param list<int> $kontoIds
     * @return array<int,list<string>> Reasons keyed by debtor id (empty list = eligible).
     */
    public function collectionReasonsFor(array $kontoIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $kontoIds)));
        if (!$ids) return [];
        $out = array_fill_keys($ids, []);
        $enabled = $this->db->setting('invoice_pay_enabled', 'off') === 'on';
        // 20261012 CL/LH Chunk the batch to stay below SQLite's parameter limit.
        foreach (array_chunk($ids, 500) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '?'));
            $customers = [];
            foreach ($this->db->rows("SELECT id,pbs,stripe_fravalg FROM adresser WHERE id IN ($marks)", $chunk) as $row) {
                $customers[(int)$row['id']] = $row;
            }
            $subscribers = [];
            if ($this->tableExists('stripe_customers')) {
                foreach ($this->db->rows("SELECT konto_id FROM stripe_customers WHERE konto_id IN ($marks) AND status IN ('active','trialing','past_due')", $chunk) as $row) {
                    $subscribers[(int)$row['konto_id']] = true;
                }
            }
            foreach ($chunk as $id) {
                $customer = $customers[$id] ?? [];
                if (in_array(strtolower(trim((string)($customer['pbs'] ?? ''))), ['on', '1', 't', 'true', 'yes'], true)) $out[$id][] = 'pbs';
                if (isset($subscribers[$id])) $out[$id][] = 'subscription';
                if (trim((string)($customer['stripe_fravalg'] ?? '')) !== '') $out[$id][] = 'optout';
                if (!$enabled) $out[$id][] = 'disabled';
            }
        }
        return $out;
    }

    /**
     * @param list<int> $kontoIds
     * @return array<int,list<string>> All reasons including saved-card state, keyed by debtor id.
     */
    public function autopayReasonsFor(array $kontoIds): array
    {
        $out = $this->collectionReasonsFor($kontoIds);
        if (!$out) return [];
        $enabled = $this->db->setting('autopay_enabled', 'off') === 'on';
        $cards = [];
        if ($this->tableExists('stripe_invoice_cards')) {
            $mode = $this->db->mode();
            foreach (array_chunk(array_keys($out), 500) as $chunk) {
                $marks = implode(',', array_fill(0, count($chunk), '?'));
                foreach ($this->db->rows("SELECT * FROM stripe_invoice_cards WHERE mode=? AND konto_id IN ($marks)", array_merge([$mode], $chunk)) as $row) {
                    $cards[(int)$row['konto_id']] = $row;
                }
            }
        }
        foreach ($out as $id => &$reasons) {
            $card = $cards[$id] ?? null;
            if (!$enabled) $reasons[] = 'autopay_disabled';
            if (!$card || (int)$card['autopay'] !== 1 || empty($card['consent_at']) || trim((string)$card['payment_method_id']) === '') $reasons[] = 'no_card';
            if ($card && $card['broken_at'] !== null) $reasons[] = 'card_broken';
            if ($card && $card['revoked_at'] !== null) $reasons[] = 'card_revoked';
        }
        unset($reasons);
        return $out;
    }

    private function tableExists(string $table): bool
    {
        if ($this->db->driver() === 'pgsql') {
            $row = $this->db->row('SELECT to_regclass(?) AS t', [$table]);
        } else {
            $row = $this->db->row("SELECT name AS t FROM sqlite_master WHERE type='table' AND name=?", [$table]);
        }
        return !empty($row['t']);
    }
}
