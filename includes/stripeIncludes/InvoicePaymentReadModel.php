<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/InvoicePaymentReadModel.php --- ver 5.0.0 --- 2026.10.13
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
// 20261012 CL/LH Read-only REST payment projections on the authenticated tenant connection.
// 20261012 CL/LH Include real hosted card declines while excluding technical errors.
// 20261013 CL/LH Exclude settled historical positive items from REST due dates to match the worker.

namespace Saldi\Stripe;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

require_once __DIR__ . '/InvoicePaymentStore.php';
require_once __DIR__ . '/InvoicePaymentMath.php';
require_once __DIR__ . '/InvoicePaymentEligibility.php';

/** Database facts only: no runtime, Stripe, DDL, bookkeeping or notifications. */
final class InvoicePaymentReadModel
{
    private InvoicePaymentStore $db;
    private ?bool $available = null;
    private string $mode = '';
    private bool $invoiceEnabled = false;
    private bool $autopayEnabled = false;
    private const HARD_DECLINES = ['authentication_required', 'expired_card', 'card_not_supported', 'lost_card', 'stolen_card', 'pickup_card'];

    public function __construct(PDO $pdo)
    {
        $this->db = new InvoicePaymentStore($pdo);
    }

    /**
     * Null means unavailable schema/configuration, not an absent saved card.
     * @param list<int> $kontoIds
     * @return array<int, null|array{
     *   mode:'test'|'live', invoicePayEnabled:bool, autopayEnabled:bool, eligible:bool,
     *   blockedReasons:list<string>,
     *   card:null|array{brand:string,last4:string,expMonth:int,expYear:int,consentAt:?string,consentOrderId:?int,termsVersion:?string,brokenAt:?string,brokenReason:?string,revokedAt:?string},
     *   lastPayment:null|array{orderId:int,invoiceNo:?string,kind:'manual'|'auto',amountOre:int,receivedAt:?string,settlement:'pending'|'manual'|'posted'|'posted_manual'|'error'},
     *   lastDecline:null|array{orderId:int,invoiceNo:?string,kind:'manual'|'auto',failedAt:?string,code:?string,hard:bool},
     *   unresolvedSettlementCount:int
     * }>
     */
    public function debtorStatuses(array $kontoIds): array
    {
        $ids = $this->ids($kontoIds);
        $out = array_fill_keys($ids, null);
        if (!$ids || !$this->isAvailable()) return $out;
        $reasons = (new InvoicePaymentEligibility($this->db))->autopayReasonsFor($ids);
        $cards = [];
        foreach ($this->batch('SELECT * FROM stripe_invoice_cards WHERE mode=? AND konto_id IN (%s)', $ids, [$this->mode]) as $card) {
            $cards[(int)$card['konto_id']] = $card;
        }
        $payments = $this->payments('konto_id', $ids);
        $invoices = $this->invoiceFacts(array_column($payments, 'order_id'));
        foreach ($ids as $id) {
            $out[$id] = [
                'mode' => $this->mode, 'invoicePayEnabled' => $this->invoiceEnabled,
                'autopayEnabled' => $this->autopayEnabled, 'eligible' => $reasons[$id] === [],
                'blockedReasons' => $reasons[$id], 'card' => isset($cards[$id]) ? $this->card($cards[$id]) : null,
                'lastPayment' => null, 'lastDecline' => null, 'unresolvedSettlementCount' => 0,
            ];
        }
        $lastReceipts = [];
        $lastDeclines = [];
        foreach ($payments as $p) {
            $id = (int)$p['konto_id'];
            $invoice = $invoices[(int)$p['order_id']] ?? null;
            // 20261012 CL/LH An order reassigned to another debtor cannot leak payment facts.
            if (!$invoice || $invoice['order']['art'] !== 'DO' || (int)$invoice['order']['konto_id'] !== $id) continue;
            if ($p['status'] === 'succeeded') {
                $settlement = $this->settlement($p, $invoice);
                if (in_array($settlement, ['pending', 'manual', 'error'], true)) $out[$id]['unresolvedSettlementCount']++;
                if ($this->newer($p, $lastReceipts[$id] ?? null, 'received_at')) {
                    $lastReceipts[$id] = $p;
                    $out[$id]['lastPayment'] = [
                        'orderId' => (int)$p['order_id'], 'invoiceNo' => $this->optional($invoice['order']['fakturanr']),
                        'kind' => (string)$p['kind'], 'amountOre' => (int)$p['amount_ore'],
                        'receivedAt' => $this->time($p['received_at']), 'settlement' => $settlement,
                    ];
                }
            }
            if ($this->isDecline($p) && $this->newer($p, $lastDeclines[$id] ?? null, 'failed_at')) {
                $lastDeclines[$id] = $p;
                $code = $this->code($p['error_code']);
                $out[$id]['lastDecline'] = [
                    'orderId' => (int)$p['order_id'], 'invoiceNo' => $this->optional($invoice['order']['fakturanr']),
                    'kind' => (string)$p['kind'], 'failedAt' => $this->time($p['failed_at']), 'code' => $code,
                    'hard' => in_array($code, self::HARD_DECLINES, true),
                ];
            }
        }
        return $out;
    }

    /**
     * Ledger settlement and card receipt are independent facts. Existing REST paid is untouched.
     * @param list<int> $orderIds
     * @return array<int, null|array{
     *   ledgerPaid:bool, ledgerOpenOre:int, dueDate:?string, receivedUnpostedOre:int,
     *   processing:bool, collectionHold:bool,
     *   latestAttempt:null|array{kind:'manual'|'auto',status:string,at:?string,code:?string}
     * }>
     */
    public function invoicePayments(array $orderIds): array
    {
        $ids = $this->ids($orderIds);
        $out = array_fill_keys($ids, null);
        if (!$ids || !$this->isAvailable()) return $out;
        $invoices = $this->invoiceFacts($ids);
        foreach ($invoices as $id => $invoice) {
            if ($invoice['order']['art'] !== 'DO') continue;
            $out[$id] = [
                'ledgerPaid' => $invoice['paid'], 'ledgerOpenOre' => $invoice['open'],
                'dueDate' => $this->dueDate($invoice['order'], $invoice['items']), 'receivedUnpostedOre' => 0,
                'processing' => false, 'collectionHold' => false, 'latestAttempt' => null,
            ];
        }
        $latest = [];
        foreach ($this->payments('order_id', $ids) as $p) {
            $id = (int)$p['order_id'];
            if ($out[$id] === null || (int)$invoices[$id]['order']['konto_id'] !== (int)$p['konto_id']) continue;
            if ($p['status'] === 'succeeded' && in_array($this->settlement($p, $invoices[$id]), ['pending', 'manual', 'error'], true)) {
                $out[$id]['receivedUnpostedOre'] += (int)$p['amount_ore'];
            }
            if (in_array($p['status'], ['created', 'processing'], true)) $out[$id]['processing'] = true;
            if ((int)$p['collection_hold'] === 1) $out[$id]['collectionHold'] = true;
            if ($this->newer($p, $latest[$id] ?? null, 'created_at')) {
                $latest[$id] = $p;
                $at = $p['status'] === 'succeeded' ? $p['received_at'] : ($p['status'] === 'failed' ? $p['failed_at'] : $p['created_at']);
                $out[$id]['latestAttempt'] = ['kind' => (string)$p['kind'], 'status' => (string)$p['status'],
                    'at' => $this->time($at), 'code' => $this->code($p['error_code'])];
            }
        }
        return $out;
    }

    /** Capability checks use metadata, so an old PostgreSQL tenant never aborts a transaction. */
    private function isAvailable(): bool
    {
        if ($this->available !== null) return $this->available;
        $this->available = false;
        if (!in_array($this->db->driver(), ['pgsql', 'sqlite'], true)) return false;
        $required = [
            'settings' => ['var_grp', 'var_name', 'var_value'],
            'adresser' => ['id', 'pbs', 'stripe_fravalg'],
            'ordrer' => ['id', 'konto_id', 'art', 'fakturanr', 'fakturadate', 'due_date', 'betalingsbet', 'betalingsdage'],
            'openpost' => ['id', 'konto_id', 'faktnr', 'refnr', 'amount', 'udlignet', 'forfaldsdate'],
            'stripe_invoice_cards' => ['mode', 'konto_id', 'brand', 'last4', 'exp_month', 'exp_year', 'autopay', 'payment_method_id', 'consent_at', 'consent_order_id', 'terms_version', 'broken_at', 'broken_reason', 'revoked_at'],
            'stripe_invoice_payments' => ['id', 'mode', 'konto_id', 'order_id', 'kind', 'status', 'amount_ore', 'received_at', 'failed_at', 'open_ore_at_receipt', 'collection_hold', 'error_code', 'error_type', 'settlement', 'created_at'],
        ];
        $columns = [];
        if ($this->db->driver() === 'pgsql') {
            foreach ($this->db->rows("SELECT table_name,column_name FROM information_schema.columns WHERE table_schema=current_schema() AND table_name IN ('settings','adresser','ordrer','openpost','stripe_invoice_cards','stripe_invoice_payments','stripe_customers')") as $r) {
                $columns[$r['table_name']][] = $r['column_name'];
            }
        } else {
            foreach (array_merge(array_keys($required), ['stripe_customers']) as $table) {
                $columns[$table] = array_column($this->db->rows('PRAGMA table_info(' . $table . ')'), 'name');
            }
        }
        foreach ($required as $table => $names) {
            if (array_diff($names, $columns[$table] ?? [])) return false;
        }
        if (!empty($columns['stripe_customers']) && array_diff(['konto_id', 'status'], $columns['stripe_customers'])) return false;
        // 20261012 CL/LH Read only nonsecret switches, once per read model and tenant request.
        $settings = [];
        foreach ($this->db->rows("SELECT var_name,var_value FROM settings WHERE var_grp='stripe' AND var_name IN ('mode','invoice_pay_enabled','autopay_enabled')") as $r) {
            $settings[$r['var_name']] = $r['var_value'];
        }
        $this->mode = (string)($settings['mode'] ?? '');
        if (!in_array($this->mode, ['test', 'live'], true)) return false;
        $this->invoiceEnabled = ($settings['invoice_pay_enabled'] ?? 'off') === 'on';
        $this->autopayEnabled = ($settings['autopay_enabled'] ?? 'off') === 'on';
        return $this->available = true;
    }

    /** @return list<int> */
    private function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
    }

    /** @return list<array<string,mixed>> */
    private function batch(string $sql, array $ids, array $prefix = []): array
    {
        $out = [];
        foreach (array_chunk($this->ids($ids), 500) as $chunk) {
            $marks = implode(',', array_fill(0, count($chunk), '?'));
            foreach ($this->db->rows(sprintf($sql, $marks), array_merge($prefix, $chunk)) as $row) $out[] = $row;
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    private function payments(string $column, array $ids): array
    {
        return $this->batch('SELECT id,order_id,konto_id,kind,status,amount_ore,received_at,failed_at,open_ore_at_receipt,collection_hold,error_code,error_type,settlement,created_at FROM stripe_invoice_payments WHERE mode=? AND ' . $column . ' IN (%s)', $ids, [$this->mode]);
    }

    /** @return array<int,array{order:array<string,mixed>,items:list<array<string,mixed>>,paid:bool,open:int}> */
    private function invoiceFacts(array $ids): array
    {
        $out = [];
        foreach ($this->batch('SELECT id,konto_id,art,fakturanr,fakturadate,due_date,betalingsbet,betalingsdage FROM ordrer WHERE id IN (%s)', $ids) as $order) {
            $out[(int)$order['id']] = ['order' => $order, 'items' => [], 'paid' => false, 'open' => 0];
        }
        foreach ($this->batch('SELECT o.id AS order_id,p.id,p.amount,p.udlignet,p.forfaldsdate,p.refnr FROM ordrer o JOIN openpost p ON p.konto_id=o.konto_id AND p.faktnr=o.fakturanr WHERE o.id IN (%s) ORDER BY p.id', $ids) as $item) {
            $id = (int)$item['order_id'];
            $out[$id]['items'][] = $item;
            if ((string)$item['udlignet'] !== '1') $out[$id]['open'] += InvoicePaymentMath::fromDb($item['amount']);
        }
        foreach ($out as &$invoice) {
            $positive = array_filter($invoice['items'], static fn($p) => (float)$p['amount'] > 0);
            $unsettled = array_filter($positive, static fn($p) => (string)$p['udlignet'] !== '1');
            $invoice['paid'] = $positive !== [] && $unsettled === [];
        }
        unset($invoice);
        return $out;
    }

    /** Read-only manual reconciliation also works before the worker has persisted posted_manual. */
    private function settlement(array $p, array $invoice): string
    {
        $state = (string)$p['settlement'];
        if ($state === 'manual' && ($invoice['paid'] || ($p['open_ore_at_receipt'] !== null
            && (int)$p['open_ore_at_receipt'] - $invoice['open'] >= (int)$p['amount_ore']))) return 'posted_manual';
        return $state;
    }

    private function newer(array $p, ?array $old, string $time): bool
    {
        if ($old === null) return true;
        if ($p[$time] === null && $old[$time] !== null) return false;
        if ($p[$time] !== null && $old[$time] === null) return true;
        return (int)$p[$time] > (int)$old[$time] || ((int)$p[$time] === (int)$old[$time] && (int)$p['id'] > (int)$old['id']);
    }

    private function isDecline(array $p): bool
    {
        // 20261012 CL/LH Hosted declines keep a retryable Stripe status; failure facts identify the decline.
        $hosted = $p['kind'] === 'manual' && $p['failed_at'] !== null
            && in_array($p['status'], ['requires_payment_method', 'requires_confirmation', 'requires_action'], true);
        if ($p['status'] !== 'failed' && !$hosted) return false;
        $code = $this->code($p['error_code']);
        // Decline facts have Stripe's failed_at; transport/configuration errors must not masquerade as card declines.
        if (in_array($code, ['network', 'api_key_invalid', 'resource_missing', 'rate_limit', 'authentication_error', 'api_error', 'invalid_request_error'], true)) return false;
        $type = (string)($p['error_type'] ?? '');
        if ($type !== '' && $type !== 'card_error') return false;
        if ($p['failed_at'] !== null && ($type === 'card_error' || !$hosted)) return true;
        // Historical decline codes remain useful; their unknown event time stays null.
        return in_array($code, array_merge(self::HARD_DECLINES, ['card_declined', 'insufficient_funds', 'do_not_honor', 'generic_decline', 'incorrect_cvc', 'incorrect_number', 'processing_error']), true);
    }

    /** @return array{brand:string,last4:string,expMonth:int,expYear:int,consentAt:?string,consentOrderId:?int,termsVersion:?string,brokenAt:?string,brokenReason:?string,revokedAt:?string} */
    private function card(array $card): array
    {
        return [
            'brand' => (string)$card['brand'], 'last4' => (string)$card['last4'],
            'expMonth' => (int)$card['exp_month'], 'expYear' => (int)$card['exp_year'],
            'consentAt' => $this->time($card['consent_at']),
            'consentOrderId' => $card['consent_order_id'] === null ? null : (int)$card['consent_order_id'],
            'termsVersion' => $this->optional($card['terms_version']), 'brokenAt' => $this->time($card['broken_at']),
            'brokenReason' => $this->code($card['broken_reason']), 'revokedAt' => $this->time($card['revoked_at']),
        ];
    }

    private function time($value): ?string
    {
        return $value === null ? null : (new DateTimeImmutable('@' . (int)$value))->setTimezone(new DateTimeZone('Europe/Copenhagen'))->format(DATE_ATOM);
    }

    private function optional($value): ?string
    {
        return $value === null || trim((string)$value) === '' ? null : (string)$value;
    }

    /** Codes only; never reflect free-form Stripe diagnostics or request payloads. */
    private function code($value): ?string
    {
        return is_string($value) && preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $value) ? $value : null;
    }

    private function validDate($value): ?string
    {
        $date = substr(trim((string)$value), 0, 10);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date ? $date : null;
    }

    /** Same fallback chain as InvoicePaymentService::dueDate, with invalid dates skipped. */
    private function dueDate(array $order, array $items): ?string
    {
        foreach ($items as $item) {
            // 20261013 CL/LH Match the worker: settled historical items cannot supply the outstanding due date.
            if ((int)$item['refnr'] === (int)$order['id'] && (float)$item['amount'] > 0 && (string)$item['udlignet'] !== '1') {
                $date = $this->validDate($item['forfaldsdate']);
                if ($date !== null) return $date;
            }
        }
        $date = $this->validDate($order['due_date']);
        if ($date !== null) return $date;
        $base = $this->validDate($order['fakturadate']);
        if ($base === null) return null;
        $date = new DateTimeImmutable($base);
        if (stripos((string)$order['betalingsbet'], 'lb') === 0) $date = $date->modify('last day of this month');
        return $date->modify('+' . max(0, (int)$order['betalingsdage']) . ' days')->format('Y-m-d');
    }
}
