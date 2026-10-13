<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/InvoicePaymentService.php --- 2026.10.12
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
// 20261007 CL/LH Created: invoice-first card payments (Adam's mockup
//                2026-10-07). A Saldi invoice is paid on a hosted page via a
//                Stripe PaymentIntent; the customer may save the card, after
//                which the autopay worker charges later invoices off-session on
//                their due date. Saldi stays the invoicing system, Stripe is only
//                the payment rail. Stripe calls go through an injected transport
//                (stripeHttpRequest() in production, a fake in tests).
// 20261012 CL/LH Enforce shared collection eligibility, daily skip alerts and isolate test receipts from posting.
// 20261012 CL/LH Validate and order event facts, scope card changes by generation, and audit collection holds.

// 20261012 CL/LH Commit durable requests/receipts, reconcile manual posting, serialize authorization and retry notifications.
// 20261012 CL/LH Record versioned consent, expose effective autopay state and report card removal outcomes separately.
// 20261012 CL/LH Recover hosted intents with Stripe receipt dates, record consent grant time and bind early corrections.
// 20261012 CL/LH Keep API recovery separate from signed event ordering and preserve unchanged decline times by charge identity.

namespace Saldi\Stripe;

require_once __DIR__ . '/InvoicePaymentEligibility.php';
require_once __DIR__ . '/InvoicePaymentOutbox.php';
require_once __DIR__ . '/InvoicePaymentOperations.php';
require_once __DIR__ . '/InvoicePaymentConsent.php';

use RuntimeException;
use Throwable;

final class InvoicePaymentApiError extends RuntimeException
{
    public string $stripeCode;
    /** @var array<string,mixed> */
    public array $body;
    public int $status;

    public function __construct(string $message, string $code, array $body, int $status)
    {
        parent::__construct($message);
        $this->stripeCode = $code;
        $this->body = $body;
        $this->status = $status;
    }

    /** True when transport/server failure leaves the creating request outcome ambiguous. */
    public function transient(): bool
    {
        return $this->status === 0 || $this->status >= 500 || $this->stripeCode === 'network';
    }
}

final class InvoicePaymentService
{
    public const MAX_AUTO_ATTEMPTS = 3;
    public const RETRY_SECONDS = 3 * 86400;
    public const RESEND_SECONDS = 600;
    /** Off-session declines that will not succeed on retry without the customer. */
    private const CARD_BROKEN = ['authentication_required', 'expired_card', 'card_not_supported', 'lost_card', 'stolen_card', 'pickup_card'];
    private const OPEN_PI = ['requires_payment_method', 'requires_confirmation', 'requires_action'];

    public InvoicePaymentStore $db;
    private $transport;
    private InvoicePaymentLedger $ledger;
    private InvoicePaymentEligibility $eligibility;
    private InvoicePaymentOutbox $outbox;
    private InvoicePaymentOperations $operations;
    private string $dbName;
    /** @var list<callable> Work to run after the outermost transaction commits (mails, detach calls). */
    private array $after = [];

    public function __construct(InvoicePaymentStore $db, callable $transport, InvoicePaymentLedger $ledger, callable $alert, string $dbName)
    {
        $this->db = $db;
        $this->transport = $transport;
        $this->ledger = $ledger;
        $this->eligibility = new InvoicePaymentEligibility($db);
        $this->outbox = new InvoicePaymentOutbox($db, $alert);
        $this->operations = new InvoicePaymentOperations($db, $dbName);
        $this->dbName = $dbName;
    }

    // ---------------------------------------------------------------- plumbing

    /** @return array<string,mixed> */
    public function api(string $method, string $path, array $params = [], string $idempotencyKey = ''): array
    {
        $r = ($this->transport)($method, $path, $params, $idempotencyKey !== '' ? ['idempotency_key' => $idempotencyKey] : []);
        if (!empty($r['ok']) && is_array($r['body'])) return $r['body'];
        $body = is_array($r['body'] ?? null) ? $r['body'] : [];
        throw new InvoicePaymentApiError((string)($r['error'] ?? 'Stripe-fejl'), (string)($r['error_code'] ?? ''), $body, (int)($r['status'] ?? 0));
    }

    private function tx(callable $operation)
    {
        $outer = !$this->db->inTransaction();
        try {
            $result = $this->db->transaction($operation);
        } catch (Throwable $e) {
            if ($outer) $this->after = [];
            throw $e;
        }
        if ($outer) $this->flush();
        return $result;
    }

    private function later(callable $work): void
    {
        $this->after[] = $work;
        if (!$this->db->inTransaction()) $this->flush();
    }

    private function flush(): void
    {
        $work = $this->after;
        $this->after = [];
        foreach ($work as $w) {
            try { $w(); } catch (Throwable $e) {
                $this->notify('Kortadministration kunne ikke afsluttes', $e->getMessage(), '', 3600, 'configuration');
            }
        }
        $this->flushNotifications();
    }

    /** Delivery failures remain visible in the durable outbox, without rolling back money. */
    public function flushNotifications(): int
    {
        return $this->outbox->flush();
    }

    private function notify(string $subject, string $body, string $key = '', int $throttle = 3600, string $kind = 'accounting'): void
    {
        $dedupe = $this->dbName . '-' . $this->db->mode() . '-' . ($key !== '' ? $key : hash('sha256', $subject . $body));
        // Daily skip/error notices may repeat; receipt/event facts have permanent keys.
        if ($throttle > 0 && (str_starts_with($key, 'collection-') || str_starts_with($key, 'credit-') || str_starts_with($key, 'transport-') || str_starts_with($key, 'settle-') || str_starts_with($key, 'recover-'))) {
            $dedupe .= '-' . intdiv($this->db->now(), $throttle);
        }
        $this->outbox->enqueue($kind, hash('sha256', $dedupe), $subject, $body);
        if (!$this->db->inTransaction()) $this->flushNotifications();
    }

    /** @return array<string,mixed> Response from a committed, immutable creating operation. */
    private function dispatchOperation(array $operation): array
    {
        try {
            return $this->operations->dispatch((int)$operation['id'], [$this, 'api'], function ($row) {
                $this->notify('Betaling skal afstemmes hos Stripe', 'Operation ' . $row['id'] . ', ordre ' . $row['order_id'] . ': svar på betalingen er ukendt. Afstem den oprindelige betaling hos Stripe; der sendes ikke en ny betaling.', 'reconcile-' . $row['id'], 0, 'needs_reconcile');
            });
        } finally {
            $this->flushNotifications();
        }
    }

    /** A single debtor lock coordinates page, worker, enrollment and removal across commits. */
    private function debtorLock(int $kontoId, callable $work)
    {
        return $this->db->serialized('stripe-card-' . $this->db->mode() . '-' . $kontoId, $work);
    }

    // ------------------------------------------------------------- read model

    /**
     * Everything the hosted page and the worker need about one invoice.
     * @return array{order:array<string,mixed>,customer:array<string,mixed>,seller:array<string,mixed>,lines:list<array{text:string,qty:string,unit:string,price:int,total:int,textOnly:bool}>,net:int,vat:int,total:int,due:int,customerBalance:int,dueDate:string,terms:string,email:string,card:?array<string,mixed>,enabled:bool,paid:bool,inFlight:bool,payable:bool,reason:string}|null
     *         null when the order is not a posted debitor invoice.
     */
    public function invoice(int $orderId, int $ignoreRow = 0): ?array
    {
        $order = $this->db->row('SELECT * FROM ordrer WHERE id=?', [$orderId]);
        if (!$order || $order['art'] !== 'DO' || (int)$order['status'] < 3) return null;
        $customer = $this->db->row('SELECT * FROM adresser WHERE id=?', [(int)$order['konto_id']]) ?? [];
        $seller = $this->db->row("SELECT firmanavn,addr1,postnr,bynavn,cvrnr,email,bank_reg,bank_konto FROM adresser WHERE art='S' ORDER BY id LIMIT 1") ?? [];

        $lines = [];
        foreach ($this->db->rows('SELECT varenr,beskrivelse,enhed,antal,pris,rabat FROM ordrelinjer WHERE ordre_id=? ORDER BY posnr,id', [$orderId]) as $l) {
            $textOnly = trim((string)$l['varenr']) === '' && (float)$l['antal'] == 0.0 && (float)$l['pris'] == 0.0;
            $price = InvoicePaymentMath::fromDb($l['pris']);
            $total = InvoicePaymentMath::lineTotal($l['pris'], $l['antal'], $l['rabat']);
            $qty = rtrim(rtrim(number_format((float)$l['antal'], 2, ',', '.'), '0'), ',');
            $lines[] = ['text' => (string)$l['beskrivelse'], 'qty' => $qty, 'unit' => (string)$l['enhed'], 'price' => $price, 'total' => $total, 'textOnly' => $textOnly];
        }

        $net = InvoicePaymentMath::fromDb($order['sum']);
        $vat = InvoicePaymentMath::fromDb($order['moms']);
        $open = $this->db->rows("SELECT * FROM openpost WHERE konto_id=? AND faktnr=? AND COALESCE(udlignet,'0')<>'1'", [(int)$order['konto_id'], (string)$order['fakturanr']]);
        $due = 0;
        $mixedCurrency = false;
        foreach ($open as $o) {
            if (!in_array(strtoupper(trim((string)($o['valuta'] ?? ''))), ['', 'DKK'], true)) $mixedCurrency = true;
            else $due += InvoicePaymentMath::fromDb($o['amount']);
        }
        $balance = 0;
        foreach ($this->db->rows("SELECT * FROM openpost WHERE konto_id=? AND COALESCE(udlignet,'0')<>'1'", [(int)$order['konto_id']]) as $o) {
            if (!in_array(strtoupper(trim((string)($o['valuta'] ?? ''))), ['', 'DKK'], true)) $mixedCurrency = true;
            else $balance += InvoicePaymentMath::fromDb($o['amount']);
        }
        // Card receipts the bookkeeper has not posted yet are already paid, even though openpost still shows them.
        $unposted = $this->db->row("SELECT COALESCE(SUM(amount_ore),0) AS s FROM stripe_invoice_payments WHERE mode=? AND konto_id=? AND status='succeeded' AND settlement NOT IN ('posted','posted_manual')", [$this->db->mode(), (int)$order['konto_id']]);
        $balance -= (int)($unposted['s'] ?? 0);

        $card = $this->card((int)$order['konto_id']);
        $email = trim((string)$order['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = trim((string)($customer['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';

        $mode = $this->db->mode();
        $flight = $this->db->row(
            "SELECT id,status FROM stripe_invoice_payments WHERE mode=? AND order_id=? AND id<>? AND (status='processing' OR (status='succeeded' AND settlement NOT IN ('posted','posted_manual')) OR status='created') ORDER BY id DESC LIMIT 1",
            [$mode, $orderId, $ignoreRow]);
        $enabled = $this->db->setting('invoice_pay_enabled', 'off') === 'on';
        $paid = $due <= 0 && ($open !== [] || trim((string)$order['betalt']) !== '' || ($flight && $flight['status'] === 'succeeded'));
        $held = $this->db->row('SELECT id FROM stripe_invoice_payments WHERE mode=? AND order_id=? AND collection_hold=1 LIMIT 1', [$mode, $orderId]);
        $reason = '';
        // 20261012 CL/LH One collection policy also protects already-issued links.
        $block = $this->collectionBlock((int)$order['konto_id']);
        if ($held) $reason = 'collection_hold';
        elseif ($block !== null) $reason = $block === 'optout' ? 'opted_out' : $block;
        elseif (!in_array(strtoupper(trim((string)$order['valuta'])), ['', 'DKK'], true)) $reason = 'currency';
        elseif ($net + $vat <= 0 || trim((string)$order['fakturanr']) === '') $reason = 'nothing_due';
        elseif ($mixedCurrency) $reason = 'currency';
        elseif ($flight) $reason = $flight['status'] === 'succeeded' ? 'received' : 'in_flight';
        elseif ($due <= 0) $reason = $paid ? 'paid' : 'nothing_due';

        return [
            'order' => $order, 'customer' => $customer, 'seller' => $seller, 'lines' => $lines,
            'net' => $net, 'vat' => $vat, 'total' => $net + $vat, 'due' => $due, 'customerBalance' => $balance,
            'dueDate' => $this->dueDate($order), 'terms' => $this->terms($order), 'email' => $email, 'card' => $card,
            'enabled' => $enabled, 'paid' => $paid, 'inFlight' => (bool)$flight, 'payable' => $reason === '', 'reason' => $reason,
        ];
    }

    /** Y-m-d: the invoice's open item due date, else ordrer.due_date, else fakturadate + betalingsdage. */
    private function dueDate(array $order): string
    {
        $items = $this->db->rows("SELECT forfaldsdate FROM openpost WHERE refnr=? AND konto_id=? AND amount>0 AND COALESCE(udlignet,'0')<>'1' ORDER BY id", [(int)$order['id'], (int)$order['konto_id']]);
        foreach (array_merge(array_column($items, 'forfaldsdate'), [$order['due_date'] ?? '']) as $d) {
            $d = substr(trim((string)$d), 0, 10);
            try { return InvoicePaymentMath::date($d); } catch (\InvalidArgumentException $e) { }
        }
        $base = substr(trim((string)$order['fakturadate']), 0, 10);
        try { InvoicePaymentMath::date($base); } catch (\InvalidArgumentException $e) { return ''; }
        $date = new \DateTimeImmutable($base);
        if (stripos((string)$order['betalingsbet'], 'lb') === 0) $date = $date->modify('last day of this month');
        return $date->modify('+' . max(0, (int)$order['betalingsdage']) . ' days')->format('Y-m-d');
    }

    private function terms(array $order): string
    {
        $term = trim((string)$order['betalingsbet']);
        $days = (int)$order['betalingsdage'];
        if (strcasecmp($term, 'netto') === 0) return 'Netto ' . $days . ' dage';
        if (stripos($term, 'lb') === 0) return 'Løbende måned + ' . $days . ' dage';
        return $term;
    }

    /** @return array<string,mixed>|null The customer's active saved card. */
    public function card(int $kontoId): ?array
    {
        return $this->db->row('SELECT * FROM stripe_invoice_cards WHERE mode=? AND konto_id=? AND revoked_at IS NULL', [$this->db->mode(), $kontoId]);
    }

    /** @return string|null First competing-channel or switch blocker, null when eligible. */
    public function collectionBlock(int $kontoId): ?string
    {
        return $this->eligibility->collectionReasons($kontoId)[0] ?? null;
    }

    /** @return string|null First collection, autopay switch or saved-card blocker. */
    public function autopayBlock(int $kontoId): ?string
    {
        return $this->eligibility->autopayReasons($kontoId)[0] ?? null;
    }

    /** @return list<string> Every collection, switch and saved-card blocker for the debtor (empty = autopay active). */
    public function autopayReasons(int $kontoId): array
    {
        return $this->eligibility->autopayReasons($kontoId);
    }

    /**
     * 20261012 CL/LH Effective automatic-payment state of one invoice, read-only
     * (AP-21, LIFE-37, LIFE-32). Mirrors the worker's checks so the page never
     * promises a charge the worker will not attempt.
     * State: none (no active card), blocked (collection/hold), paused
     * (autopay_enabled off), broken, inactive (card kept without enabled
     * consent), not_covered (invoice dated before the
     * consent date), exhausted (no automatic attempts left), effective.
     * @return array{state:string,reason:?string,card:?array<string,mixed>,cutoff:?string,lastFailed:bool}
     */
    public function autopayStatus(int $orderId): array
    {
        $out = ['state' => 'none', 'reason' => null, 'card' => null, 'cutoff' => null, 'lastFailed' => false];
        $order = $this->db->row('SELECT konto_id,fakturadate FROM ordrer WHERE id=?', [$orderId]);
        if (!$order) return $out;
        $kontoId = (int)$order['konto_id'];
        $card = $this->card($kontoId);
        if (!$card) return $out;
        $out['card'] = $card;
        if ($card['consent_at'] !== null) $out['cutoff'] = InvoicePaymentMath::localDate((int)$card['consent_at']);
        $mode = $this->db->mode();
        $reasons = $this->eligibility->autopayReasons($kontoId);
        $collection = array_values(array_intersect($reasons, ['pbs', 'subscription', 'optout', 'disabled']));
        $held = $this->db->row('SELECT hold_reason FROM stripe_invoice_payments WHERE mode=? AND order_id=? AND collection_hold=1 LIMIT 1', [$mode, $orderId]);
        if ($collection) return ['state' => 'blocked', 'reason' => $collection[0]] + $out;
        if ($held) return ['state' => 'blocked', 'reason' => 'collection_hold'] + $out;
        if (in_array('card_broken', $reasons, true)) return ['state' => 'broken', 'reason' => 'card_broken'] + $out;
        if (in_array('autopay_disabled', $reasons, true)) return ['state' => 'paused', 'reason' => 'autopay_disabled'] + $out;
        if ($reasons) return ['state' => 'inactive', 'reason' => $reasons[0]] + $out;
        if ($out['cutoff'] === null || substr(trim((string)$order['fakturadate']), 0, 10) < $out['cutoff']) return ['state' => 'not_covered'] + $out;
        $tries = $this->db->rows("SELECT status FROM stripe_invoice_payments WHERE mode=? AND order_id=? AND kind='auto' ORDER BY attempt DESC,id DESC", [$mode, $orderId]);
        $out['lastFailed'] = $tries !== [] && $tries[0]['status'] === 'failed';
        if (count($tries) >= self::MAX_AUTO_ATTEMPTS && $out['lastFailed']) return ['state' => 'exhausted'] + $out;
        return ['state' => 'effective'] + $out;
    }

    /** @return array<string,mixed>|null The configured-mode payment row of a PaymentIntent. */
    public function payment(string $intentId): ?array
    {
        return $this->db->row('SELECT * FROM stripe_invoice_payments WHERE mode=? AND payment_intent_id=?', [$this->db->mode(), $intentId]);
    }

    /** @return string Danish explanation for an eligibility reason. */
    private function collectionMessage(string $reason): string
    {
        return [
            'pbs' => 'Denne faktura opkræves via Betalingsservice.',
            'subscription' => 'Denne debitor betaler allerede via et Stripe-abonnement.',
            'optout' => 'Kortbetaling er fravalgt for denne debitor.',
            'disabled' => 'Kortbetaling af fakturaer er slået fra.',
            'autopay_disabled' => 'Automatisk kortbetaling er slået fra.',
            'no_card' => 'Der er ikke et godkendt kort til automatisk betaling.',
            'card_broken' => 'Det gemte kort er afvist.',
            'card_revoked' => 'Det gemte kort er fjernet.',
        ][$reason] ?? 'Fakturaen kan ikke betales online';
    }

    /** Alert at most once per debtor per 24 hours, including across worker runs. */
    private function notifyBlocked(int $kontoId, string $reason): void
    {
        $this->tx(function () use ($kontoId, $reason) {
            $mode = $this->db->mode();
            $this->db->lock('stripe-collection-' . $mode . '-' . $kontoId);
            if ($this->db->row("SELECT id FROM stripe_invoice_audit WHERE entity_type=? AND entity_id=? AND action='autopay_blocked' AND created_at>? LIMIT 1", ['collection_' . $mode, $kontoId, $this->db->now() - 86400])) return;
            $this->db->audit('collection_' . $mode, $kontoId, 'autopay_blocked', 'worker', ['reason' => $reason]);
            $this->notify('Automatisk kortbetaling sprunget over', 'Debitor-id ' . $kontoId . ': ' . $this->collectionMessage($reason), 'collection-' . $mode . '-' . $kontoId, 86400);
        });
    }

    // --------------------------------------------------------------- pay now

    private function customerId(array $customer): string
    {
        $mode = $this->db->mode();
        $konto = (int)$customer['id'];
        if ($r = $this->db->row('SELECT customer_id FROM stripe_invoice_customers WHERE mode=? AND konto_id=?', [$mode, $konto])) return (string)$r['customer_id'];
        $params = ['name' => mb_substr((string)$customer['firmanavn'], 0, 250), 'metadata' => ['saldi_konto_id' => (string)$konto, 'saldi_db' => $this->dbName]];
        if (filter_var(trim((string)($customer['email'] ?? '')), FILTER_VALIDATE_EMAIL)) $params['email'] = trim((string)$customer['email']);
        $op = $this->operations->prepare('customer_create', (string)$konto, '/v1/customers', $params);
        try {
            $created = !empty($op['result_id']) ? ['id' => $op['result_id']] : $this->dispatchOperation($op);
        } catch (InvoicePaymentApiError $e) {
            $this->notify('Kortbetaling kunne ikke startes - teknisk fejl', 'Debitor ' . $konto . ': Stripe-kunden kunne ikke oprettes. ' . $e->getMessage(), 'transport-' . $op['id'], 3600, $e->transient() ? 'transport' : 'configuration');
            throw $e;
        }
        $this->tx(function () use ($mode, $konto, $created) {
            $this->db->query('INSERT INTO stripe_invoice_customers (mode,konto_id,customer_id,created_at) VALUES (?,?,?,?) ON CONFLICT (mode,konto_id) DO NOTHING', [$mode, $konto, $created['id'], $this->db->now()]);
        });
        return (string)$this->db->row('SELECT customer_id FROM stripe_invoice_customers WHERE mode=? AND konto_id=?', [$mode, $konto])['customer_id'];
    }

    /**
     * Reconcile or cancel a prior intent before any replacement, shared by page/worker.
     * @return array{status:string,pi:?array} canceled permits replacement; all uncertainty blocks.
     */
    private function priorIntent(array $row, bool $cancel): array
    {
        try {
            $pi = $this->api('GET', '/v1/payment_intents/' . rawurlencode($row['payment_intent_id']), ['expand' => ['latest_charge']]);
            if (in_array($pi['status'], ['processing', 'succeeded'], true)) {
                $this->paymentIntentEvent($pi);
                return ['status' => $pi['status'], 'pi' => $pi];
            }
            if ($cancel && $pi['status'] !== 'canceled') {
                try {
                    $pi = $this->api('POST', '/v1/payment_intents/' . rawurlencode($row['payment_intent_id']) . '/cancel');
                } catch (InvoicePaymentApiError $e) {
                    $pi = $this->api('GET', '/v1/payment_intents/' . rawurlencode($row['payment_intent_id']), ['expand' => ['latest_charge']]);
                }
            }
            $this->paymentIntentEvent($pi);
            if (!$cancel || in_array($pi['status'], ['canceled', 'processing', 'succeeded'], true)) {
                return ['status' => (string)$pi['status'], 'pi' => $pi];
            }
        } catch (InvoicePaymentApiError $e) {
            $this->notify('Betaling er allerede i gang - afstem hos Stripe', 'Ordre ' . $row['order_id'] . ', Stripe ' . $row['payment_intent_id'] . ': ' . $e->getMessage(), 'ambiguous-' . $row['id'], 0, 'transport');
            return ['status' => 'blocked', 'pi' => null];
        }
        $this->notify('Betaling er allerede i gang - afstem hos Stripe', 'Ordre ' . $row['order_id'] . ': den tidligere betaling kunne ikke annulleres.', 'ambiguous-' . $row['id'], 0, 'transport');
        return ['status' => 'blocked', 'pi' => $pi];
    }

    /** @return array{status:string,client_secret:string,payment_intent:string,amount:int,currency:string,message:string} */
    private function paymentResult(string $status, ?array $pi = null): array
    {
        $messages = ['succeeded' => 'Vi har modtaget din betaling.', 'processing' => 'Betalingen behandles allerede.', 'blocked' => 'Betaling er allerede i gang. Prøv igen senere.'];
        return ['status' => $status, 'client_secret' => $status === 'ready' ? (string)($pi['client_secret'] ?? '') : '',
            'payment_intent' => (string)($pi['id'] ?? ''), 'amount' => (int)($pi['amount'] ?? 0), 'currency' => 'dkk', 'message' => $messages[$status] ?? ''];
    }

    /**
     * Create/reuse a hosted intent after durable preparation; reconciled normal
     * outcomes commit and return a structured status, never throw to roll them back.
     * @return array{status:string,client_secret:string,payment_intent:string,amount:int,currency:string,message:string}
     */
    public function startPayment(int $orderId, bool $saveCard, ?string $termsVersion = null): array
    {
        // 20261012 CL/LH The page sends the consent version it showed; null means the current text (internal callers).
        if ($saveCard && $termsVersion !== null && $termsVersion !== InvoicePaymentConsent::VERSION) {
            throw new RuntimeException('Teksten om automatisk betaling er ændret. Genindlæs siden, og prøv igen.');
        }
        $terms = $saveCard ? InvoicePaymentConsent::VERSION : null;
        $termsSha = $saveCard ? InvoicePaymentConsent::sha256() : null;
        $order = $this->db->row('SELECT konto_id FROM ordrer WHERE id=?', [$orderId]);
        if (!$order) throw new RuntimeException('Fakturaen kan ikke betales online');
        return $this->debtorLock((int)$order['konto_id'], function () use ($orderId, $saveCard, $order, $termsVersion, $terms, $termsSha) {
            $mode = $this->db->mode();
            $this->reconcileManualReceipts((int)$order['konto_id']);
            $inv = $this->invoice($orderId);
            if (($block = $this->collectionBlock((int)$order['konto_id'])) !== null) throw new RuntimeException($this->collectionMessage($block));
            if (!$inv || in_array($inv['reason'], ['collection_hold', 'currency'], true)) throw new RuntimeException('Fakturaen kan ikke betales online');
            $open = $this->db->row("SELECT * FROM stripe_invoice_payments WHERE mode=? AND order_id=? AND status NOT IN ('canceled','superseded') AND (status<>'succeeded' OR settlement NOT IN ('posted','posted_manual') OR ?<=0) ORDER BY id DESC LIMIT 1", [$mode, $orderId, $inv['due']]);
            if ($open && $open['status'] === 'succeeded') return $this->paymentResult('succeeded', ['id' => $open['payment_intent_id'], 'amount' => (int)$open['amount_ore']]);
            if ($open && $open['payment_intent_id'] !== null) {
                $prior = $this->priorIntent($open, false);
                if (in_array($prior['status'], ['succeeded', 'processing', 'blocked'], true)) return $this->paymentResult($prior['status'], $prior['pi']);
                $pi = $prior['pi'];
                if ($open['kind'] === 'manual' && in_array($prior['status'], self::OPEN_PI, true) && (int)$pi['amount'] === $inv['due']) {
                    $sfu = $saveCard ? 'off_session' : '';
                    if ((string)($pi['setup_future_usage'] ?? '') !== $sfu) $pi = $this->api('POST', '/v1/payment_intents/' . rawurlencode($pi['id']), ['setup_future_usage' => $sfu]);
                    $this->tx(function () use ($open, $saveCard, $terms, $termsSha) {
                        // 20261012 CL/LH A new grant on a reused intent belongs to the current authorization generation.
                        $generation = (int)($this->db->row('SELECT generation FROM stripe_invoice_cards WHERE mode=? AND konto_id=?', [$open['mode'], (int)$open['konto_id']])['generation'] ?? 0);
                        $this->db->query('UPDATE stripe_invoice_payments SET save_card=?,terms_version=?,consent_text_sha256=?,consent_granted_at=?,card_generation=?,updated_at=? WHERE id=?', [$saveCard ? 1 : 0, $terms, $termsSha, $saveCard ? $this->db->now() : null, $generation, $this->db->now(), $open['id']]);
                    });
                    return $this->paymentResult('ready', $pi);
                }
                if ($prior['status'] !== 'canceled') {
                    $prior = $this->priorIntent($open, true);
                    if ($prior['status'] !== 'canceled') return $this->paymentResult($prior['status'], $prior['pi']);
                }
                $open = null;
            }
            if ($open && $open['status'] === 'created') {
                // An unknown creation outcome may only replay its immutable original request.
                $op = $this->db->row("SELECT * FROM stripe_invoice_operations WHERE mode=? AND payment_id=? AND kind='intent_create' ORDER BY id DESC LIMIT 1", [$mode, $open['id']]);
                if (!$op || $open['kind'] !== 'manual') return $this->paymentResult('blocked');
                $result = $this->dispatchHosted($op);
                // Once the unknown creation is resolved, apply the customer's current save-card choice.
                return $result['status'] === 'ready' ? $this->startPayment($orderId, $saveCard, $termsVersion) : $result;
            }
            if (!$inv['payable'] && in_array($inv['reason'], ['paid', 'nothing_due', 'received'], true)) throw new RuntimeException('Fakturaen kan ikke betales online');
            $cus = $this->customerId($inv['customer']);
            $op = $this->tx(function () use ($orderId, $saveCard, $mode, $cus, $terms, $termsSha) {
                $this->db->lock('stripe-pay-' . $mode . '-' . $orderId);
                $inv = $this->invoice($orderId);
                if (!$inv || !$inv['payable']) throw new RuntimeException('Fakturaen kan ikke betales online');
                if ($inv['due'] < 250) throw new RuntimeException('Restbeløbet er for lille til kortbetaling. Betal via bankoverførsel.');
                $n = 1 + (int)$this->db->row('SELECT COUNT(*) AS n FROM stripe_invoice_payments WHERE mode=? AND order_id=?', [$mode, $orderId])['n'];
                $generation = (int)($this->db->row('SELECT generation FROM stripe_invoice_cards WHERE mode=? AND konto_id=?', [$mode, (int)$inv['order']['konto_id']])['generation'] ?? 0);
                $rowId = $this->db->insert("INSERT INTO stripe_invoice_payments (mode,order_id,konto_id,amount_ore,kind,attempt,status,save_card,terms_version,consent_text_sha256,consent_granted_at,card_generation,created_at,updated_at) VALUES (?,?,?,?,'manual',?,'created',?,?,?,?,?,?,?)", [$mode, $orderId, (int)$inv['order']['konto_id'], $inv['due'], $n, $saveCard ? 1 : 0, $terms, $termsSha, $saveCard ? $this->db->now() : null, $generation, $this->db->now(), $this->db->now()]);
                $params = ['amount' => $inv['due'], 'currency' => 'dkk', 'customer' => $cus, 'payment_method_types' => ['card'], 'description' => 'Faktura ' . $inv['order']['fakturanr'],
                    'metadata' => $this->metadata($inv, 'invoice_manual') + ['saldi_card_generation' => (string)$generation, 'saldi_payment_row' => (string)$rowId]];
                if ($saveCard) $params['setup_future_usage'] = 'off_session';
                if ($inv['email'] !== '') $params['receipt_email'] = $inv['email'];
                return $this->operations->prepare('intent_create', (string)$rowId, '/v1/payment_intents', $params, $orderId, $inv['due'], $generation, $rowId);
            });
            return $this->dispatchHosted($op);
        });
    }

    /** @return array{status:string,client_secret:string,payment_intent:string,amount:int,currency:string,message:string} */
    private function dispatchHosted(array $op): array
    {
        // 20261012 CL/LH Error handling must refer to the fresh operation after a definitive rejection.
        $op = $this->operations->resume((int)$op['id']);
        if (empty($op['result_id'])) {
            $inv = $this->invoice((int)$op['order_id'], (int)$op['payment_id']);
            if (!$inv || !$inv['payable'] || $inv['due'] !== (int)$op['amount_ore']) {
                $this->tx(function () use ($op) {
                    $this->db->query("UPDATE stripe_invoice_operations SET status='needs_reconcile',updated_at=? WHERE id=?", [$this->db->now(), $op['id']]);
                    $this->notify('Betaling skal afstemmes hos Stripe', 'Ordre ' . $op['order_id'] . ': restbeløbet er ændret efter forberedelse af betalingen.', 'reconcile-' . $op['id'], 0, 'needs_reconcile');
                });
                return $this->paymentResult('blocked');
            }
        }
        try {
            $pi = !empty($op['result_id']) ? $this->api('GET', '/v1/payment_intents/' . rawurlencode($op['result_id']), ['expand' => ['latest_charge']]) : $this->dispatchOperation($op);
            if (in_array($pi['status'], ['processing', 'succeeded'], true)) {
                $this->paymentIntentEvent($pi);
            } else {
                $this->tx(function () use ($op, $pi) {
                    $this->db->query("UPDATE stripe_invoice_payments SET payment_intent_id=?,status=?,updated_at=? WHERE id=? AND status='created'", [$pi['id'], $pi['status'], $this->db->now(), $op['payment_id']]);
                });
            }
            return $this->paymentResult(in_array($pi['status'], ['processing', 'succeeded'], true) ? $pi['status'] : ($pi['status'] === 'canceled' ? 'blocked' : 'ready'), $pi);
        } catch (InvoicePaymentApiError $e) {
            $this->notify('Kortbetaling kunne ikke startes - teknisk fejl', 'Ordre ' . $op['order_id'] . ': ' . $e->getMessage(), 'transport-' . $op['id'], 3600, $e->transient() ? 'transport' : 'configuration');
            throw $e;
        } catch (RuntimeException $e) {
            if ($this->db->row("SELECT id FROM stripe_invoice_operations WHERE id=? AND status='needs_reconcile'", [$op['id']])) return $this->paymentResult('blocked');
            throw $e;
        }
    }

    /** @return array<string,string> */
    private function metadata(array $inv, string $kind): array
    {
        return ['saldi_order_id' => (string)$inv['order']['id'], 'saldi_konto_id' => (string)$inv['order']['konto_id'], 'saldi_db' => $this->dbName, 'saldi_kind' => $kind, 'saldi_faktnr' => (string)$inv['order']['fakturanr']];
    }

    /**
     * Return-URL check: the PaymentIntent must belong to the signed order before
     * its status is trusted. @return string The PaymentIntent status.
     */
    public function confirmReturn(int $orderId, string $intentId): string
    {
        if (!preg_match('/^pi_[A-Za-z0-9]+$/', $intentId)) throw new RuntimeException('Ugyldig betalingsreference');
        $pi = $this->api('GET', '/v1/payment_intents/' . rawurlencode($intentId), ['expand' => ['latest_charge']]);
        if ((string)($pi['metadata']['saldi_order_id'] ?? '') !== (string)$orderId || ($pi['metadata']['saldi_db'] ?? '') !== $this->dbName) throw new RuntimeException('Betalingen hører ikke til denne faktura');
        $this->paymentIntentEvent($pi);
        return (string)$pi['status'];
    }

    // ---------------------------------------------------------------- events

    /** Webhook entry: payment_intent.succeeded/processing/payment_failed/canceled. */
    public function handleEvent(array $event): string
    {
        if (strpos((string)($event['type'] ?? ''), 'payment_intent.') !== 0) return 'ignored';
        return $this->paymentIntentEvent((array)($event['data']['object'] ?? []), isset($event['created']) ? (int)$event['created'] : null);
    }

    /** Record a PaymentIntent's state; on the first success save the card (if consented) and settle. */
    public function paymentIntentEvent(array $pi, ?int $eventCreated = null): string
    {
        if (($pi['status'] ?? '') !== 'succeeded' && ($pi['metadata']['saldi_db'] ?? '') === $this->dbName) {
            return $this->debtorLock((int)($pi['metadata']['saldi_konto_id'] ?? 0), function () use ($pi, $eventCreated) {
                return $this->applyPaymentIntent($pi, $eventCreated);
            });
        }
        return $this->applyPaymentIntent($pi, $eventCreated);
    }

    /** @return int Stripe charge time for API recovery, falling back to intent creation when unavailable. */
    private function receiptTime(array $pi): int
    {
        $charge = $pi['latest_charge'] ?? null;
        $created = (int)($pi['created'] ?? 0);
        $chargeCreated = is_array($charge) ? (int)($charge['created'] ?? 0) : 0;
        if (!array_key_exists('latest_charge', $pi) || is_string($charge) || ($created <= 0 && $chargeCreated <= 0)) {
            // 20261012 CL/LH Creation/cancellation responses may omit the expanded charge.
            $expanded = $this->api('GET', '/v1/payment_intents/' . rawurlencode((string)$pi['id']), ['expand' => ['latest_charge']]);
            $charge = $expanded['latest_charge'] ?? null;
            $chargeCreated = is_array($charge) ? (int)($charge['created'] ?? 0) : 0;
            if ($created <= 0) $created = (int)($expanded['created'] ?? 0);
        }
        if ($chargeCreated > 0) return $chargeCreated;
        return $created > 0 ? $created : $this->db->now();
    }

    /**
     * Identify the failed charge independently of intent creation or observation time.
     * @return array{charge:?string,created:?int} Stripe failure identity and time, null when unavailable.
     */
    private function failureFacts(array $pi, bool $retrieve): array
    {
        $errorCharge = $pi['last_payment_error']['charge'] ?? null;
        $latest = $pi['latest_charge'] ?? null;
        if ($retrieve && !is_array($errorCharge) && (!array_key_exists('latest_charge', $pi) || is_string($latest))) {
            // 20261012 CL/LH API snapshots need Stripe's expanded charge before assigning a failure time.
            $expanded = $this->api('GET', '/v1/payment_intents/' . rawurlencode((string)$pi['id']), ['expand' => ['latest_charge']]);
            $errorCharge = $errorCharge ?? ($expanded['last_payment_error']['charge'] ?? null);
            $latest = $expanded['latest_charge'] ?? null;
        }
        $charge = $errorCharge ?? $latest;
        $id = is_array($charge) ? ($charge['id'] ?? null) : $charge;
        // 20261012 CL/LH last_payment_error can name an older failed charge than latest_charge.
        if (is_array($latest) && $id !== null && ($latest['id'] ?? null) === $id && (!is_array($charge) || empty($charge['created']))) $charge = $latest;
        if ($retrieve && is_string($charge) && $charge !== '') {
            $charge = $this->api('GET', '/v1/charges/' . rawurlencode($charge));
        }
        $created = is_array($charge) ? (int)($charge['created'] ?? 0) : 0;
        return ['charge' => is_string($id) && $id !== '' ? $id : null, 'created' => $created > 0 ? $created : null];
    }

    private function applyPaymentIntent(array $pi, ?int $eventCreated): string
    {
        $meta = (array)($pi['metadata'] ?? []);
        $kind = (string)($meta['saldi_kind'] ?? '');
        if (!in_array($kind, ['invoice_manual', 'invoice_auto'], true) || ($meta['saldi_db'] ?? '') !== $this->dbName) return 'ignored';
        $mode = $this->db->mode();
        if ((bool)($pi['livemode'] ?? false) !== ($mode === 'live')) return 'ignored';
        $id = (string)($pi['id'] ?? '');
        $orderId = (int)($meta['saldi_order_id'] ?? 0);
        if (!preg_match('/^pi_[A-Za-z0-9]+$/', $id) || $orderId <= 0) return 'ignored';
        $status = (string)$pi['status'];
        // 20261012 CL/LH Only signed events advance event_created; API facts use their own Stripe timestamps.
        $hasFailure = !empty($pi['last_payment_error']) && !in_array($status, ['processing', 'succeeded', 'canceled'], true);
        $failure = $hasFailure ? $this->failureFacts($pi, $eventCreated === null) : ['charge' => null, 'created' => null];
        $factTime = $eventCreated ?? ($status === 'succeeded' ? $this->receiptTime($pi) : ($failure['created'] ?? $this->db->now()));

        // 20261012 CL/LH Receipt facts commit before any PaymentMethod lookup or ledger posting.
        $result = $this->tx(function () use ($pi, $meta, $kind, $mode, $id, $orderId, $status, $eventCreated, $factTime, $hasFailure, $failure) {
            $this->db->lock('stripe-pay-' . $mode . '-' . $orderId);
            $this->db->lock('stripe-pi-' . $mode . '-' . $id);
            $now = $this->db->now();
            $row = $this->db->row('SELECT * FROM stripe_invoice_payments WHERE mode=? AND payment_intent_id=?', [$mode, $id]);
            if (!$row && !empty($meta['saldi_payment_row'])) {
                $row = $this->db->row('SELECT * FROM stripe_invoice_payments WHERE id=? AND mode=? AND payment_intent_id IS NULL', [(int)$meta['saldi_payment_row'], $mode]);
            }
            // 20261012 CL/LH Identity must match durable local facts before any receipt/card write.
            $this->validatePaymentIdentity($pi, $row, $mode, $orderId, $kind);
            if (!$row) {
                $rowId = $this->db->insert('INSERT INTO stripe_invoice_payments (mode,order_id,konto_id,payment_intent_id,amount_ore,kind,attempt,status,save_card,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                    [$mode, $orderId, (int)($meta['saldi_konto_id'] ?? 0), $id, (int)$pi['amount'], $kind === 'invoice_auto' ? 'auto' : 'manual', (int)($meta['saldi_attempt'] ?? 1), 'created', ($pi['setup_future_usage'] ?? '') === 'off_session' ? 1 : 0, $now, $now]);
                $row = $this->db->row('SELECT * FROM stripe_invoice_payments WHERE id=?', [$rowId]);
            }
            // 20261012 CL/LH A validated webhook resolves a lost creation response before expiry.
            $this->db->query("UPDATE stripe_invoice_operations SET result_id=?,status='succeeded',last_error=NULL,updated_at=? WHERE mode=? AND payment_id=? AND kind='intent_create' AND status<>'rejected'", [$id, $now, $mode, $row['id']]);
            if ($row['status'] === 'succeeded') return 'duplicate';
            if ($status !== 'succeeded' && $eventCreated !== null && $row['event_created'] !== null && $eventCreated < (int)$row['event_created']) return 'stale';

            $error = (array)($pi['last_payment_error'] ?? []);
            $failed = $row['kind'] === 'auto' && $error && !in_array($status, ['processing', 'succeeded', 'canceled'], true);
            $newStatus = $failed ? 'failed' : $status;
            // A canceled retry intent still retains its historical decline in the REST projection.
            if ($row['kind'] === 'auto' && $row['status'] === 'failed' && $status === 'canceled') $newStatus = 'failed';
            $code = (string)($error['decline_code'] ?? ($error['code'] ?? ''));
            $amount = $status === 'succeeded' ? (int)($pi['amount_received'] ?? $pi['amount']) : (int)$pi['amount'];
            $method = is_array($pi['payment_method'] ?? null) ? (string)($pi['payment_method']['id'] ?? '') : (string)($pi['payment_method'] ?? '');
            $generation = $row['card_generation'] ?? ($meta['saldi_card_generation'] ?? null);
            // 20261012 CL/LH Repeated API observations of the same code/charge preserve the recorded failure time.
            $failedCharge = $failure['charge'] ?? $row['failed_charge'];
            $sameFailure = $row['failed_at'] !== null && $row['error_code'] === mb_substr($code, 0, 64) && $row['failed_charge'] === $failedCharge;
            $failedAt = $hasFailure && ($eventCreated !== null || !$sameFailure) ? $factTime : $row['failed_at'];
            $this->db->query('UPDATE stripe_invoice_payments SET payment_intent_id=?,status=?,amount_ore=?,error_code=?,error_type=?,error_message=?,received_at=COALESCE(received_at,?),failed_at=?,failed_charge=?,event_created=?,payment_method=?,card_generation=?,open_ore_at_receipt=COALESCE(open_ore_at_receipt,?),advice_code=?,updated_at=? WHERE id=?',
                [$id, $newStatus, $amount, $error ? mb_substr($code, 0, 64) : $row['error_code'], $error ? mb_substr((string)($error['type'] ?? ''), 0, 32) : $row['error_type'], $error ? mb_substr((string)($error['message'] ?? ''), 0, 500) : $row['error_message'], $status === 'succeeded' ? $factTime : null, $failedAt, $hasFailure ? $failedCharge : $row['failed_charge'], $eventCreated ?? $row['event_created'], $method !== '' ? $method : $row['payment_method'], $generation, $status === 'succeeded' ? $this->ledgerOpen($orderId) : null, $error['advice_code'] ?? $row['advice_code'], $now, $row['id']]);

            $row = $this->db->row('SELECT * FROM stripe_invoice_payments WHERE id=?', [$row['id']]);
            if ($failed && $row['failed_at'] !== null && !$this->db->row("SELECT id FROM stripe_invoice_audit WHERE entity_type='payment' AND entity_id=? AND action='auto_failed' LIMIT 1", [(int)$row['id']])) {
                $this->autoFailed($row, $id, $code, (string)($error['message'] ?? ''));
            }
            if ($status !== 'succeeded') return $newStatus;

            if ((int)$row['save_card'] === 1 && ($pi['setup_future_usage'] ?? '') === 'off_session' && is_string($pi['payment_method'] ?? null)) {
                $snapshot = $pi;
                unset($snapshot['client_secret']);
                $this->db->query("INSERT INTO stripe_invoice_operations (mode,kind,external_id,order_id,amount_ore,data_json,card_generation,payment_id,status,created_at,updated_at) VALUES (?,'card_enrichment',?,?,?,?,?,?,'pending',?,?) ON CONFLICT (mode,kind,external_id) DO NOTHING",
                    [$mode, $id, $orderId, (int)$row['amount_ore'], InvoicePaymentStore::json($snapshot), $row['card_generation'], $row['id'], $now, $now]);
            }
            return 'succeeded';
        });
        if (!$this->db->inTransaction() && in_array($result, ['succeeded', 'duplicate'], true)) {
            $this->tx(function () use ($id, $mode, $orderId) {
                $this->db->lock('stripe-pay-' . $mode . '-' . $orderId);
                $row = $this->db->row('SELECT * FROM stripe_invoice_payments WHERE mode=? AND payment_intent_id=?', [$mode, $id]);
                if ($row && $row['status'] === 'succeeded') $this->settle($row);
            });
            $this->enrichCards($id);
        }
        return $result;
    }

    /** Retry saved-card enrichment separately; received money is already committed. */
    public function enrichCards(?string $intentId = null): int
    {
        if ($this->db->inTransaction()) return 0;
        $args = [$this->db->mode()];
        $filter = '';
        if ($intentId !== null) { $filter = ' AND external_id=?'; $args[] = $intentId; }
        $done = 0;
        foreach ($this->db->rows("SELECT * FROM stripe_invoice_operations WHERE mode=? AND kind='card_enrichment' AND status IN ('pending','error')" . $filter . ' ORDER BY id LIMIT 100', $args) as $op) {
            try {
                $pi = json_decode($op['data_json'], true, 512, JSON_THROW_ON_ERROR);
                $done += $this->debtorLock((int)$pi['metadata']['saldi_konto_id'], function () use ($pi, $op) {
                    $current = $this->db->row('SELECT status FROM stripe_invoice_operations WHERE id=?', [$op['id']]);
                    if ($current['status'] === 'succeeded') return 0;
                    $pm = $this->api('GET', '/v1/payment_methods/' . rawurlencode($pi['payment_method']));
                    if (($pm['customer'] ?? '') !== ($pi['customer'] ?? '') || ($pm['type'] ?? '') !== 'card') throw new RuntimeException('Kortoplysninger matcher ikke betalingen');
                    return $this->tx(function () use ($pi, $pm, $op) {
                        $this->saveCard($pi, $pm, (int)$pi['metadata']['saldi_konto_id'], (int)$op['order_id']);
                        $this->db->query("UPDATE stripe_invoice_operations SET status='succeeded',last_error=NULL,updated_at=? WHERE id=?", [$this->db->now(), $op['id']]);
                        return 1;
                    });
                });
            } catch (Throwable $e) {
                $this->tx(function () use ($op, $e) {
                    $this->db->query("UPDATE stripe_invoice_operations SET status='error',last_error=?,updated_at=? WHERE id=?", [mb_substr($e->getMessage(), 0, 500), $this->db->now(), $op['id']]);
                    $this->notify('Betaling modtaget - kort kunne ikke gemmes', 'Ordre ' . $op['order_id'] . ', Stripe ' . $op['external_id'] . ': ' . $e->getMessage() . '. Kortet aktiveres først efter et vellykket forsøg.', 'enrichment-' . $op['id'], 0, 'card_enrichment');
                });
            }
        }
        return $done;
    }

    /** @return int|null DKK open balance, or null for mixed currency requiring manual review. */
    private function ledgerOpen(int $orderId): ?int
    {
        $order = $this->db->row('SELECT konto_id,fakturanr FROM ordrer WHERE id=?', [$orderId]);
        $amount = 0;
        foreach ($this->db->rows("SELECT * FROM openpost WHERE konto_id=? AND faktnr=? AND COALESCE(udlignet,'0')<>'1'", [$order['konto_id'], $order['fakturanr']]) as $r) {
            if (!in_array(strtoupper(trim((string)($r['valuta'] ?? ''))), ['', 'DKK'], true)) return null;
            $amount += InvoicePaymentMath::fromDb($r['amount']);
        }
        return $amount;
    }

    /**
     * Persist the agreed bookkeeping evidence without posting a second receipt.
     * Test money never becomes accounting evidence. Read-only GETs do not call this.
     * @return int Receipts reconciled in this invocation.
     */
    public function reconcileManualReceipts(?int $kontoId = null): int
    {
        if ($this->db->mode() === 'test') return 0;
        $args = [$this->db->mode()];
        $filter = '';
        if ($kontoId !== null) { $filter = ' AND konto_id=?'; $args[] = $kontoId; }
        $n = 0;
        foreach ($this->db->rows("SELECT id,order_id FROM stripe_invoice_payments WHERE mode=? AND status='succeeded' AND settlement='manual'" . $filter . ' ORDER BY id', $args) as $candidate) {
            $n += $this->tx(function () use ($candidate) {
                $this->db->lock('stripe-pay-' . $this->db->mode() . '-' . $candidate['order_id']);
                $row = $this->db->row("SELECT * FROM stripe_invoice_payments WHERE id=? AND settlement='manual'", [$candidate['id']]);
                if (!$row) return 0;
                $order = $this->db->row('SELECT konto_id,fakturanr FROM ordrer WHERE id=?', [$row['order_id']]);
                if (!$order || (int)$order['konto_id'] !== (int)$row['konto_id']) return 0;
                $settled = $this->db->row("SELECT id FROM openpost WHERE konto_id=? AND faktnr=? AND amount>0 AND udlignet='1' ORDER BY id LIMIT 1", [$row['konto_id'], $order['fakturanr']]);
                $open = $this->ledgerOpen((int)$row['order_id']);
                if ($open === null) return 0;
                if (!$settled && ($row['open_ore_at_receipt'] === null || (int)$row['open_ore_at_receipt'] - $open < (int)$row['amount_ore'])) return 0;
                $this->db->query("UPDATE stripe_invoice_payments SET settlement='posted_manual',settled_at=?,updated_at=? WHERE id=?", [$this->db->now(), $this->db->now(), $row['id']]);
                $this->db->audit('payment', (int)$row['id'], 'posted_manual', 'worker', ['openpost_id' => $settled['id'] ?? null, 'open_ore' => $open, 'open_ore_at_receipt' => $row['open_ore_at_receipt']]);
                return 1;
            });
        }
        return $n;
    }

    /** Expiry notices continue with collection off, including revoked cards and unknown customers. */
    public function expireAmbiguousOperations(): int
    {
        $n = 0;
        foreach ($this->db->rows("SELECT id FROM stripe_invoice_operations WHERE mode=? AND request_json IS NOT NULL AND status='dispatched' AND result_id IS NULL AND first_dispatch_at<=? ORDER BY id LIMIT 100", [$this->db->mode(), $this->db->now() - InvoicePaymentOperations::REPLAY_SECONDS]) as $candidate) {
            $n += $this->db->serialized('stripe-operation-' . $this->dbName . '-' . $candidate['id'], function () use ($candidate) {
                return $this->tx(function () use ($candidate) {
                    $row = $this->db->row("SELECT * FROM stripe_invoice_operations WHERE id=? AND status='dispatched' AND result_id IS NULL", [$candidate['id']]);
                    if (!$row) return 0;
                    $this->db->query("UPDATE stripe_invoice_operations SET status='needs_reconcile',updated_at=? WHERE id=?", [$this->db->now(), $row['id']]);
                    $this->notify('Betaling skal afstemmes hos Stripe', 'Operation ' . $row['id'] . ' (' . $row['kind'] . ', reference ' . $row['external_id'] . '), ordre ' . $row['order_id'] . ': svar på betalingen er ukendt efter 20 timer. Afstem hos Stripe; der sendes ikke en ny betaling.', 'reconcile-' . $row['id'], 0, 'needs_reconcile');
                    return 1;
                });
            });
        }
        return $n;
    }

    /** Retrieve aged known intents even with collection disabled; never create replacements. */
    public function reconcileIntents(): int
    {
        $n = 0;
        $this->tx(function () {
            foreach ($this->db->rows("SELECT p.id,o.result_id FROM stripe_invoice_payments p JOIN stripe_invoice_operations o ON o.payment_id=p.id AND o.mode=p.mode AND o.kind='intent_create' WHERE p.mode=? AND p.status='created' AND p.payment_intent_id IS NULL AND o.result_id IS NOT NULL", [$this->db->mode()]) as $known) {
                $this->db->query("UPDATE stripe_invoice_payments SET payment_intent_id=? WHERE id=? AND status='created' AND payment_intent_id IS NULL", [$known['result_id'], $known['id']]);
            }
        });
        foreach ($this->db->rows("SELECT * FROM stripe_invoice_payments WHERE mode=? AND status IN ('created','processing','requires_payment_method','requires_confirmation','requires_action') AND payment_intent_id IS NOT NULL AND updated_at<=? ORDER BY id LIMIT 100", [$this->db->mode(), $this->db->now() - 3600]) as $row) {
            try {
                $pi = $this->api('GET', '/v1/payment_intents/' . rawurlencode($row['payment_intent_id']), ['expand' => ['latest_charge']]);
                $this->paymentIntentEvent($pi);
                $n++;
            } catch (Throwable $e) {
                $this->notify('Betaling kunne ikke afstemmes hos Stripe', 'Ordre ' . $row['order_id'] . ', Stripe ' . $row['payment_intent_id'] . ': ' . $e->getMessage(), 'recover-' . $row['id'], 86400, 'transport');
            }
        }
        return $n;
    }

    /** Reject mismatched tenant/order/debtor, expected money, kind or Stripe customer. */
    private function validatePaymentIdentity(array $pi, ?array $row, string $mode, int $orderId, string $kind): void
    {
        $meta = (array)($pi['metadata'] ?? []);
        $order = $this->db->row('SELECT * FROM ordrer WHERE id=?', [$orderId]);
        $konto = (int)($meta['saldi_konto_id'] ?? 0);
        $mapping = $this->db->row('SELECT customer_id FROM stripe_invoice_customers WHERE mode=? AND konto_id=?', [$mode, $konto]);
        $customer = is_array($pi['customer'] ?? null) ? ($pi['customer']['id'] ?? '') : ($pi['customer'] ?? '');
        $expected = $row ? (int)$row['amount_ore'] : (int)($this->invoice($orderId)['due'] ?? 0);
        if (($meta['saldi_db'] ?? '') !== $this->dbName || !$order || $order['art'] !== 'DO' || (int)$order['status'] < 3
            || $konto <= 0 || (int)$order['konto_id'] !== $konto
            || ($row && ((int)$row['order_id'] !== $orderId || (int)$row['konto_id'] !== $konto || $row['kind'] !== ($kind === 'invoice_auto' ? 'auto' : 'manual')))
            || !in_array(strtoupper(trim((string)($order['valuta'] ?? ''))), ['', 'DKK'], true)
            || !$mapping || $mapping['customer_id'] !== $customer
            || strtolower((string)($pi['currency'] ?? '')) !== 'dkk' || $expected <= 0 || (int)($pi['amount'] ?? 0) !== $expected
            || (($pi['status'] ?? '') === 'succeeded' && (int)($pi['amount_received'] ?? $pi['amount']) !== $expected)) {
            throw new RuntimeException('Betalingsidentitet eller beløb matcher ikke fakturaen; afstem betalingen manuelt hos Stripe');
        }
    }

    private function saveCard(array $pi, array $pm, int $konto, int $orderId): void
    {
        // 20261012 CL/LH A collection-channel change during payment must not enroll autopay.
        if ($this->collectionBlock($konto) !== null) return;
        $mode = $this->db->mode();
        if (($pm['customer'] ?? '') !== ($pi['customer'] ?? '') || ($pm['type'] ?? '') !== 'card') return;
        // 20261012 CL/LH Saving, removing and breaking a card share one debtor lock.
        $this->db->lock('stripe-card-' . $mode . '-' . $konto);
        $old = $this->db->row('SELECT * FROM stripe_invoice_cards WHERE mode=? AND konto_id=?', [$mode, $konto]);
        $payment = $this->db->row('SELECT * FROM stripe_invoice_payments WHERE mode=? AND payment_intent_id=?', [$mode, (string)$pi['id']]);
        // 20261012 CL/LH Only a consent recorded by startPayment (version + text hash) may enroll the card.
        if (!$payment || (int)$payment['save_card'] !== 1 || trim((string)$payment['terms_version']) === '' || trim((string)$payment['consent_text_sha256']) === '') return;
        // 20261012 CL/LH Consent starts at the customer's choice, including reuse of an older intent.
        if ($payment['consent_granted_at'] === null) return;
        $started = (int)$payment['consent_granted_at'];
        if ($old && $old['revoked_at'] !== null && (int)($payment['card_generation'] ?? -1) !== (int)$old['generation']) return;
        if ($old && $old['consent_at'] !== null) {
            if ($started < (int)$old['consent_at']) return;
            if ($started === (int)$old['consent_at']) {
                $saved = $this->db->row("SELECT data_json FROM stripe_invoice_audit WHERE entity_type='card' AND entity_id=? AND action='card_saved' ORDER BY id DESC LIMIT 1", [$konto]);
                $savedId = (string)(json_decode((string)($saved['data_json'] ?? '{}'), true)['payment_intent'] ?? '');
                $previous = $this->db->row('SELECT id FROM stripe_invoice_payments WHERE mode=? AND payment_intent_id=?', [$mode, $savedId]);
                if ($previous && (int)$previous['id'] > (int)$payment['id']) return;
            }
        }
        // The customer removed the card after starting this payment: respect that.
        if ($old && $old['revoked_at'] !== null && (int)$old['revoked_at'] > $started) return;
        $c = (array)($pm['card'] ?? []);
        $now = $this->db->now();
        $this->db->query('INSERT INTO stripe_invoice_cards (mode,konto_id,customer_id,payment_method_id,brand,last4,exp_month,exp_year,autopay,consent_at,consent_order_id,revoked_at,revoked_by,broken_at,broken_reason,updated_at,generation) VALUES (?,?,?,?,?,?,?,?,1,?,?,NULL,NULL,NULL,NULL,?,?)'
            . ' ON CONFLICT (mode,konto_id) DO UPDATE SET customer_id=excluded.customer_id,payment_method_id=excluded.payment_method_id,brand=excluded.brand,last4=excluded.last4,exp_month=excluded.exp_month,exp_year=excluded.exp_year,autopay=1,consent_at=excluded.consent_at,consent_order_id=excluded.consent_order_id,revoked_at=NULL,revoked_by=NULL,broken_at=NULL,broken_reason=NULL,updated_at=excluded.updated_at,generation=excluded.generation',
            [$mode, $konto, (string)$pi['customer'], (string)$pm['id'], (string)($c['brand'] ?? ''), (string)($c['last4'] ?? ''), (int)($c['exp_month'] ?? 0), (int)($c['exp_year'] ?? 0), $started, $orderId, $now, $old ? (int)$old['generation'] + 1 : 1]);
        $this->db->query('UPDATE stripe_invoice_cards SET terms_version=?,consent_text_sha256=? WHERE mode=? AND konto_id=?', [(string)$payment['terms_version'], (string)$payment['consent_text_sha256'], $mode, $konto]);
        $this->db->audit('card', $konto, 'card_saved', 'kunde', ['payment_method' => $pm['id'], 'last4' => $c['last4'] ?? '', 'order_id' => $orderId, 'payment_intent' => $pi['id'],
            'terms_version' => (string)$payment['terms_version'], 'consent_text_sha256' => (string)$payment['consent_text_sha256'], 'consent_at' => $started]);
        if ($old && $old['revoked_at'] === null && $old['payment_method_id'] !== $pm['id']) {
            $this->later(function () use ($old) {
                try {
                    $this->detachUnusedCard($old);
                } catch (InvoicePaymentApiError $e) {
                    if (!in_array($e->stripeCode, ['payment_method_unexpected_state', 'resource_missing'], true)) {
                        $this->notify('Tidligere kort kunne ikke fjernes hos Stripe', 'Debitor ' . $old['konto_id'] . ', kort ' . $old['payment_method_id'] . ': det nye kort er gemt, men det tidligere kort skal kontrolleres hos Stripe. ' . $e->getMessage(), 'detach-' . $old['mode'] . '-' . $old['konto_id'] . '-' . $old['generation'], 0, 'card_detach');
                    }
                }
            });
        }
    }

    /** Post a succeeded payment, or hand it to the bookkeeper when automatic posting is off. */
    private function settle(array $row): void
    {
        if (!in_array($row['settlement'], ['pending', 'error'], true)) return;
        $order = $this->db->row('SELECT fakturanr,firmanavn FROM ordrer WHERE id=?', [(int)$row['order_id']]) ?? ['fakturanr' => '?', 'firmanavn' => ''];
        $what = 'Faktura ' . $order['fakturanr'] . ' (' . $order['firmanavn'] . '), ' . InvoicePaymentMath::display((int)$row['amount_ore']) . ', Stripe ' . $row['payment_intent_id'];
        $now = $this->db->now();
        // 20261012 CL/LH Test money is recorded but can never change the accounting ledger.
        if ($this->db->mode() === 'test') {
            $this->db->query("UPDATE stripe_invoice_payments SET settlement='manual',settle_error='test_mode',settled_at=?,updated_at=? WHERE id=?", [$now, $now, $row['id']]);
            if ($row['settle_error'] !== 'test_mode') $this->notify('Testbetaling modtaget - ingen bogføring', $what . "\n\nTestbetaling: ingen penge er modtaget, og regnskabet er ikke ændret.", 'test-receipt-' . $row['payment_intent_id'], 86400);
            return;
        }
        if (!$this->ledger->enabled()) {
            $this->db->query("UPDATE stripe_invoice_payments SET settlement='manual',settled_at=?,updated_at=? WHERE id=?", [$now, $now, $row['id']]);
            $this->notify('Kortbetaling modtaget - bogfør i kassekladden', $what . "\n\nBeløbet udbetales af Stripe. Bogfør indbetalingen på debitoren med fakturanummeret, så fakturaen udlignes.", 'receipt-' . $row['payment_intent_id'], 0);
            return;
        }
        try {
            $remaining = $this->ledger->receipt((int)$row['order_id'], (string)$row['payment_intent_id'], (int)$row['amount_ore'], InvoicePaymentMath::localDate((int)($row['received_at'] ?? $now)));
            $this->db->query("UPDATE stripe_invoice_payments SET settlement='posted',settle_error=NULL,settled_at=?,updated_at=? WHERE id=?", [$now, $now, $row['id']]);
            if ($remaining < 0) $this->notify('Kortbetaling gav overbetaling', $what . "\n\nFakturaen er overbetalt med " . InvoicePaymentMath::display(-$remaining) . '. Kontrollér debitoren.');
        } catch (Throwable $e) {
            $this->db->query("UPDATE stripe_invoice_payments SET settlement='error',settle_error=?,updated_at=? WHERE id=?", [mb_substr($e->getMessage(), 0, 500), $now, $row['id']]);
            $this->notify('Kortbetaling kunne ikke bogføres', $what . "\n\nFejl: " . $e->getMessage() . "\nBogføringen forsøges igen automatisk.", 'settle-' . $row['payment_intent_id'], 86400);
        }
    }

    /** Retry settlement of succeeded payments that were not posted (worker and staff action). */
    public function settlePending(): int
    {
        $this->reconcileManualReceipts();
        $n = 0;
        foreach ($this->db->rows("SELECT id,payment_intent_id FROM stripe_invoice_payments WHERE mode=? AND status='succeeded' AND settlement IN ('pending','error') ORDER BY id", [$this->db->mode()]) as $r) {
            $this->tx(function () use ($r) {
                $row = $this->db->row('SELECT order_id FROM stripe_invoice_payments WHERE id=?', [$r['id']]);
                $this->db->lock('stripe-pay-' . $this->db->mode() . '-' . $row['order_id']);
                $this->db->lock('stripe-pi-' . $this->db->mode() . '-' . $r['payment_intent_id']);
                $this->settle($this->db->row('SELECT * FROM stripe_invoice_payments WHERE id=?', [$r['id']]));
            });
            $n++;
        }
        return $n;
    }

    // ---------------------------------------------------------------- autopay

    /**
     * Charge saved cards for invoices due today or earlier. Only invoices dated on
     * or after the consent date qualify; three attempts, three days apart.
     * @return array{charged:int,failed:int,skipped:int}
     */
    public function autopay(bool $maintenance = true): array
    {
        $out = ['charged' => 0, 'failed' => 0, 'skipped' => 0];
        // Maintenance continues even when all collection switches are off.
        if ($maintenance) {
            $this->expireAmbiguousOperations();
            $this->reconcileIntents();
            $this->enrichCards();
            $this->settlePending();
            $this->flushNotifications();
        }
        if ($this->db->setting('autopay_enabled', 'off') !== 'on') return $out;
        $mode = $this->db->mode();
        $today = InvoicePaymentMath::localDate($this->db->now());
        foreach ($this->db->rows('SELECT konto_id FROM stripe_invoice_cards WHERE mode=? AND autopay=1 AND revoked_at IS NULL AND broken_at IS NULL ORDER BY id', [$mode]) as $selected) {
            $this->debtorLock((int)$selected['konto_id'], function () use ($selected, $mode, $today, &$out) {
                $kontoId = (int)$selected['konto_id'];
                $this->reconcileManualReceipts($kontoId);
                if (($block = $this->autopayBlock($kontoId)) !== null) {
                    $this->notifyBlocked($kontoId, $block);
                    $out['skipped']++;
                    return;
                }
                $card = $this->card($kontoId);
                $since = InvoicePaymentMath::localDate((int)$card['consent_at']);
                foreach ($this->db->rows("SELECT id FROM ordrer WHERE art='DO' AND status>=3 AND konto_id=? AND fakturadate>=? ORDER BY fakturadate,id", [$kontoId, $since]) as $o) {
                    // Never dispatch subsequent invoices with a cached broken/revoked generation.
                    if ($this->autopayBlock($kontoId) !== null) break;
                    $card = $this->card($kontoId);
                    $orderId = (int)$o['id'];
                    $order = $this->db->row('SELECT fakturadate FROM ordrer WHERE id=?', [$orderId]);
                    if ($order['fakturadate'] < InvoicePaymentMath::localDate((int)$card['consent_at'])) continue;
                    $tries = $this->db->rows("SELECT * FROM stripe_invoice_payments WHERE mode=? AND order_id=? AND kind='auto' ORDER BY attempt DESC,id DESC", [$mode, $orderId]);
                    $resend = null;
                    if ($tries && $tries[0]['status'] === 'created' && $tries[0]['payment_intent_id'] === null) {
                        if ($this->db->now() - (int)$tries[0]['updated_at'] < self::RESEND_SECONDS) { $out['skipped']++; continue; }
                        $resend = $tries[0];
                    } elseif (count($tries) >= self::MAX_AUTO_ATTEMPTS || ($tries && $this->db->now() - (int)$tries[0]['created_at'] < self::RETRY_SECONDS)) {
                        continue;
                    }
                    $inv = $this->invoice($orderId, $resend ? (int)$resend['id'] : 0);
                    if ($inv && $inv['reason'] === 'received') {
                        $this->notify('Automatisk kortbetaling afventer bogføring', 'Ordre ' . $orderId . ', faktura ' . $inv['order']['fakturanr'] . ': en modtaget kortbetaling er endnu ikke afstemt. Bogfør og udlign betalingen før ny opkrævning.', 'manual-unreconciled-' . $orderId, 0, 'manual_receipt');
                    }
                    if (!$inv || !$inv['payable'] || $inv['dueDate'] === '' || $inv['dueDate'] > $today) continue;
                    if ($inv['customerBalance'] < $inv['due']) {
                        $this->notify('Automatisk kortbetaling sprunget over', 'Faktura ' . $inv['order']['fakturanr'] . ' (' . $inv['order']['firmanavn'] . '): debitoren har kreditposter, så saldoen ' . InvoicePaymentMath::display($inv['customerBalance']) . ' er lavere end fakturaens ' . InvoicePaymentMath::display($inv['due']) . '. Udlign posterne; kortet trækkes ikke før.', 'credit-' . $orderId, 86400, 'credit');
                        $out['skipped']++;
                        continue;
                    }
                    $result = $this->chargeOffSession($card, $orderId, $resend);
                    $out[in_array($result, ['succeeded', 'processing'], true) ? 'charged' : ($result === 'failed' ? 'failed' : 'skipped')]++;
                    if ($this->autopayBlock($kontoId) !== null) break;
                }
            });
        }
        $this->flushNotifications();
        return $out;
    }

    /** One serialized off-session charge. @return string succeeded|processing|failed|skipped|pending */
    public function chargeOffSession(array $card, int $orderId, ?array $resend = null): string
    {
        return $this->debtorLock((int)$card['konto_id'], function () use ($card, $orderId, $resend) {
            $mode = $this->db->mode();
            $konto = (int)$card['konto_id'];
            $this->reconcileManualReceipts($konto);
            if (($block = $this->autopayBlock($konto)) !== null) { $this->notifyBlocked($konto, $block); return 'skipped'; }
            $fresh = $this->card($konto);
            if (!$fresh || (int)$fresh['generation'] !== (int)$card['generation'] || $fresh['payment_method_id'] !== $card['payment_method_id']) return 'skipped';
            $card = $fresh;
            $inv = $this->invoice($orderId, $resend ? (int)$resend['id'] : 0);
            if (!$inv || (int)$inv['order']['konto_id'] !== $konto || !$inv['payable'] || $inv['order']['fakturadate'] < InvoicePaymentMath::localDate((int)$card['consent_at'])) return 'skipped';
            foreach ($this->db->rows("SELECT * FROM stripe_invoice_payments WHERE mode=? AND order_id=? AND payment_intent_id IS NOT NULL AND status NOT IN ('canceled','succeeded','superseded')", [$mode, $orderId]) as $prior) {
                $result = $this->priorIntent($prior, true);
                if ($result['status'] !== 'canceled') return 'skipped';
            }
            $op = $this->tx(function () use ($card, $orderId, $resend, $mode) {
                $this->db->lock('stripe-pay-' . $mode . '-' . $orderId);
                $inv = $this->invoice($orderId, $resend ? (int)$resend['id'] : 0);
                if (!$inv || !$inv['payable'] || $this->autopayBlock((int)$card['konto_id']) !== null) return null;
                if ($inv['due'] < 250) {
                    $this->notify('Restbeløb skal betales via bankoverførsel', 'Faktura ' . $inv['order']['fakturanr'] . ': ' . InvoicePaymentMath::display($inv['due']) . ' er for lidt til kortbetaling.', 'minimum-' . $orderId, 0, 'configuration');
                    return null;
                }
                if ($inv['customerBalance'] < $inv['due']) return null;
                if ($resend) {
                    return $this->db->row("SELECT * FROM stripe_invoice_operations WHERE mode=? AND payment_id=? AND kind='intent_create' ORDER BY id DESC LIMIT 1", [$mode, $resend['id']]);
                }
                $attempt = 1 + (int)$this->db->row("SELECT COUNT(*) AS n FROM stripe_invoice_payments WHERE mode=? AND order_id=? AND kind='auto'", [$mode, $orderId])['n'];
                $now = $this->db->now();
                $row = $this->db->insert("INSERT INTO stripe_invoice_payments (mode,order_id,konto_id,amount_ore,kind,attempt,status,save_card,payment_method,card_generation,created_at,updated_at) VALUES (?,?,?,?,'auto',?,'created',0,?,?,?,?)", [$mode, $orderId, (int)$card['konto_id'], $inv['due'], $attempt, $card['payment_method_id'], (int)$card['generation'], $now, $now]);
                $params = ['amount' => $inv['due'], 'currency' => 'dkk', 'customer' => $card['customer_id'], 'payment_method' => $card['payment_method_id'], 'payment_method_types' => ['card'], 'confirm' => 'true', 'off_session' => 'true',
                    'description' => 'Faktura ' . $inv['order']['fakturanr'] . ' (automatisk betaling)', 'metadata' => $this->metadata($inv, 'invoice_auto') + ['saldi_attempt' => (string)$attempt, 'saldi_payment_row' => (string)$row, 'saldi_card_generation' => (string)$card['generation']]];
                if ($inv['email'] !== '') $params['receipt_email'] = $inv['email'];
                return $this->operations->prepare('intent_create', (string)$row, '/v1/payment_intents', $params, $orderId, $inv['due'], (int)$card['generation'], $row);
            });
            if (!$op) return 'skipped';
            $op = $this->operations->resume((int)$op['id']);
            // The amount and authorization are checked again at the dispatch boundary.
            $inv = $this->invoice($orderId, (int)$op['payment_id']);
            if (!$inv || !$inv['payable'] || $inv['due'] !== (int)$op['amount_ore'] || (int)$op['card_generation'] !== (int)$card['generation'] || $this->autopayBlock($konto) !== null) {
                $this->tx(function () use ($op) {
                    $this->db->query("UPDATE stripe_invoice_operations SET status='needs_reconcile',updated_at=? WHERE id=?", [$this->db->now(), $op['id']]);
                    $this->notify('Betaling skal afstemmes hos Stripe', 'Ordre ' . $op['order_id'] . ': beløb eller kortgodkendelse er ændret. Den tidligere betalingsoperation genbruges ikke.', 'reconcile-' . $op['id'], 0, 'needs_reconcile');
                });
                return 'pending';
            }
            try {
                $pi = !empty($op['result_id']) ? $this->api('GET', '/v1/payment_intents/' . rawurlencode($op['result_id']), ['expand' => ['latest_charge']]) : $this->dispatchOperation($op);
            } catch (InvoicePaymentApiError $e) {
                // 20261012 CL/LH A later card rejection cannot turn an earlier unknown charge into a retryable decline.
                if ($this->db->row("SELECT id FROM stripe_invoice_operations WHERE id=? AND status='needs_reconcile'", [$op['id']])) return 'pending';
                $err = (array)($e->body['error'] ?? []);
                if (!in_array($e->status, [400, 402], true) || (($err['type'] ?? '') !== 'card_error' && ($err['code'] ?? $e->stripeCode) !== 'card_declined')) {
                    $this->db->query("UPDATE stripe_invoice_payments SET updated_at=? WHERE id=? AND status='created'", [$this->db->now(), $op['payment_id']]);
                    $this->notify('Automatisk kortbetaling - teknisk fejl', 'Ordre ' . $orderId . ': ' . $e->getMessage() . '. Kortet er ikke markeret som afvist; den gemte operation afstemmes eller genforsøges.', 'transport-' . $op['id'], 3600, $e->transient() ? 'transport' : 'configuration');
                    return 'pending';
                }
                $code = (string)($err['decline_code'] ?? $e->stripeCode);
                $piId = (string)($err['payment_intent']['id'] ?? '');
                if ($piId === '') {
                    $this->tx(function () use ($op) {
                        $this->db->query("UPDATE stripe_invoice_operations SET status='needs_reconcile',updated_at=? WHERE id=?", [$this->db->now(), $op['id']]);
                        $this->notify('Betaling skal afstemmes hos Stripe', 'Ordre ' . $op['order_id'] . ': kortfejlen mangler en betalingsreference. Resultatet skal afstemmes før nyt træk.', 'reconcile-' . $op['id'], 0, 'needs_reconcile');
                    });
                    return 'pending';
                }
                $failedMethod = $err['payment_intent']['payment_method'] ?? null;
                $failedMethod = is_array($failedMethod) ? ($failedMethod['id'] ?? null) : $failedMethod;
                return $this->tx(function () use ($op, $piId, $code, $e, $mode, $failedMethod, $err) {
                    $this->db->lock('stripe-pay-' . $mode . '-' . $op['order_id']);
                    $row = $this->db->row('SELECT * FROM stripe_invoice_payments WHERE id=?', [$op['payment_id']]);
                    $taken = $piId !== '' && $this->db->row('SELECT id FROM stripe_invoice_payments WHERE mode=? AND payment_intent_id=? AND id<>?', [$mode, $piId, $row['id']]);
                    if ($taken && $row['status'] === 'created') {
                        $this->db->query("UPDATE stripe_invoice_operations SET status='needs_reconcile',updated_at=? WHERE id=?", [$this->db->now(), $op['id']]);
                        $this->notify('Betaling skal afstemmes hos Stripe', 'Ordre ' . $op['order_id'] . ': betalingsreferencen tilhører et andet forsøg. Afstem hos Stripe før nyt træk.', 'reconcile-' . $op['id'], 0, 'needs_reconcile');
                        return 'pending';
                    }
                    // 20261012 CL/LH A webhook success cannot lose to a synchronous error response.
                    $changed = $this->db->query("UPDATE stripe_invoice_payments SET payment_intent_id=?,status='failed',error_code=?,error_message=?,updated_at=?,failed_at=?,payment_method=?,advice_code=? WHERE id=? AND status='created'",
                        [$piId !== '' && !$taken ? $piId : null, mb_substr($code, 0, 64), mb_substr($e->getMessage(), 0, 500), $this->db->now(), $this->db->now(), is_string($failedMethod) ? $failedMethod : null, $err['advice_code'] ?? null, $row['id']])->rowCount();
                    if (!$changed) return (string)$row['status'];
                    $row = $this->db->row('SELECT * FROM stripe_invoice_payments WHERE id=?', [$row['id']]);
                    $this->db->query("UPDATE stripe_invoice_operations SET status='declined',result_id=?,updated_at=? WHERE id=?", [$piId !== '' ? $piId : null, $this->db->now(), $op['id']]);
                    $this->autoFailed($row, $piId, $code, $e->getMessage());
                    return 'failed';
                });
            } catch (RuntimeException $e) {
                if ($this->db->row("SELECT id FROM stripe_invoice_operations WHERE id=? AND status='needs_reconcile'", [$op['id']])) return 'pending';
                throw $e;
            }
            $this->paymentIntentEvent($pi);
            return (string)$pi['status'];
        });
    }

    private function autoFailed(array $row, string $piId, string $code, string $message): void
    {
        $mode = $this->db->mode();
        $order = $this->db->row('SELECT fakturanr,firmanavn FROM ordrer WHERE id=?', [(int)$row['order_id']]) ?? ['fakturanr' => '?', 'firmanavn' => ''];
        $text = 'Faktura ' . $order['fakturanr'] . ' (' . $order['firmanavn'] . '), ' . InvoicePaymentMath::display((int)$row['amount_ore']) . "\nForsøg " . $row['attempt'] . ' af ' . self::MAX_AUTO_ATTEMPTS . "\nÅrsag: " . ($code !== '' ? $code . ' - ' : '') . $message;
        $this->db->audit('payment', (int)$row['id'], 'auto_failed', 'Stripe', ['code' => $code, 'payment_intent' => $piId]);
        $this->db->lock('stripe-card-' . $mode . '-' . (int)$row['konto_id']);
        $broken = (in_array($code, self::CARD_BROKEN, true) || in_array($row['advice_code'] ?? '', ['do_not_try_again', 'confirm_card_data'], true)) && !empty($row['payment_method']) && $row['card_generation'] !== null
            && $this->db->query('UPDATE stripe_invoice_cards SET broken_at=?,broken_reason=?,updated_at=? WHERE mode=? AND konto_id=? AND payment_method_id=? AND generation=? AND revoked_at IS NULL', [$row['failed_at'] ?? $this->db->now(), mb_substr($code, 0, 64), $this->db->now(), $mode, (int)$row['konto_id'], $row['payment_method'], (int)$row['card_generation']])->rowCount() > 0;
        if ($broken) {
            $this->db->audit('card', (int)$row['konto_id'], 'card_broken', 'Stripe', ['code' => $code, 'payment_intent' => $piId]);
            $text .= "\n\nKortet kan ikke trækkes automatisk igen. Kunden skal betale via fakturalinket og gemme kortet på ny.";
        } elseif ((int)$row['attempt'] >= self::MAX_AUTO_ATTEMPTS) {
            $text .= "\n\nDer forsøges ikke igen. Kontakt kunden.";
        } else {
            $text .= "\n\nDer forsøges igen om " . (self::RETRY_SECONDS / 86400) . ' dage.';
        }
        $this->notify('Automatisk kortbetaling afvist', $text, 'auto-failed-' . $row['id'], 0, 'card_decline');
    }

    // --------------------------------------------------------- refund/dispute holds

    /**
     * Preserve the original receipt and place its invoice on an audited manual hold.
     * The router resolves a dispute's charge before calling this method.
     * @return bool True when charge.payment_intent belongs to this invoice flow.
     */
    public function chargeEvent(array $event, array $charge, ?array $resolvedIntent = null): bool
    {
        $type = (string)($event['type'] ?? '');
        if ($type !== 'charge.refunded' && !str_starts_with($type, 'charge.dispute.')) return false;
        $intent = $charge['payment_intent'] ?? '';
        $intentId = is_array($intent) ? (string)($intent['id'] ?? '') : (string)$intent;
        $mode = $this->db->mode();
        $payment = $this->db->row('SELECT * FROM stripe_invoice_payments WHERE mode=? AND payment_intent_id=?', [$mode, $intentId]);
        if (!$payment && $intentId !== '') {
            $pi = $resolvedIntent ?? (is_array($intent) && isset($intent['metadata']) ? $intent
                : $this->api('GET', '/v1/payment_intents/' . rawurlencode($intentId), ['expand' => ['latest_charge']]));
            $meta = (array)($pi['metadata'] ?? []);
            if (!in_array($meta['saldi_kind'] ?? '', ['invoice_manual', 'invoice_auto'], true) || ($meta['saldi_db'] ?? '') !== $this->dbName) return false;
            if (($pi['id'] ?? '') !== $intentId || (bool)($pi['livemode'] ?? false) !== ($mode === 'live')) throw new RuntimeException('Rettelsens betalingsreference matcher ikke regnskabet');
            $payment = $this->bindCorrectionIntent($pi);
        }
        if (!$payment) return false;
        return $this->tx(function () use ($event, $charge, $payment, $type, $mode) {
            $this->db->lock('stripe-pay-' . $mode . '-' . (int)$payment['order_id']);
            $reason = $type === 'charge.refunded' ? 'refund' : 'dispute';
            $eventId = (string)($event['id'] ?? '');
            if ($eventId === '') throw new RuntimeException('Stripe-event mangler id');
            // 20261012 CL/LH A crash after this commit may redeliver the same leased event.
            foreach ($this->db->rows("SELECT data_json FROM stripe_invoice_audit WHERE entity_type='payment' AND entity_id=? AND action=?", [(int)$payment['id'], $reason]) as $audit) {
                if ((json_decode($audit['data_json'], true)['event_id'] ?? '') === $eventId) return true;
            }
            $obj = (array)($event['data']['object'] ?? []);
            $amount = $reason === 'refund' ? (int)($charge['amount_refunded'] ?? 0) : (int)($obj['amount'] ?? 0);
            $order = $this->db->row('SELECT fakturanr,kontonr FROM ordrer WHERE id=?', [(int)$payment['order_id']]) ?? [];
            $this->db->query('UPDATE stripe_invoice_payments SET collection_hold=1,hold_reason=?,updated_at=? WHERE id=?', [$reason, $this->db->now(), (int)$payment['id']]);
            $this->db->audit('payment', (int)$payment['id'], $reason, 'Stripe', [
                'event_id' => $eventId, 'event_created' => (int)($event['created'] ?? 0), 'charge' => $charge['id'] ?? null,
                'payment_intent' => $payment['payment_intent_id'], 'amount_ore' => $amount, 'currency' => $obj['currency'] ?? $charge['currency'] ?? null,
                'refunds' => $charge['refunds']['data'] ?? [], 'dispute_id' => $reason === 'dispute' ? ($obj['id'] ?? null) : null,
                'dispute_status' => $reason === 'dispute' ? ($obj['status'] ?? null) : null,
                'evidence_due_by' => $obj['evidence_details']['due_by'] ?? null,
            ]);
            $this->notify($reason === 'refund' ? 'Kortbetaling refunderet - opkrævning sat på hold' : 'Kortbetaling bestridt - opkrævning sat på hold',
                'Konto: ' . ($order['kontonr'] ?? '?') . ' (id ' . $payment['konto_id'] . ")\nFaktura: " . ($order['fakturanr'] ?? '?')
                . "\nOrdre: " . $payment['order_id'] . ($reason === 'refund' ? "\nSamlet refunderet: " : "\nBeløb i indsigelse: ") . InvoicePaymentMath::display($amount)
                . "\nStripe: " . $payment['payment_intent_id'] . "\nCharge: " . ($charge['id'] ?? '?')
                . "\nEvent: " . $eventId . "\n\nAfstem og bogfør rettelsen manuelt. Frigiv først opkrævningen på debitorkort efter afstemning. Der trækkes ikke igen automatisk.",
                'hold-' . $eventId, 86400);
            return true;
        });
    }

    /**
     * Bind an early correction only to its validated durable creating request, without inventing a receipt.
     * @return array<string,mixed> Payment that will receive the hold; missing/conflicting facts remain retryable.
     */
    private function bindCorrectionIntent(array $pi): array
    {
        $meta = (array)$pi['metadata'];
        $mode = $this->db->mode();
        $orderId = (int)($meta['saldi_order_id'] ?? 0);
        return $this->tx(function () use ($pi, $meta, $mode, $orderId) {
            $this->db->lock('stripe-pay-' . $mode . '-' . $orderId);
            $this->db->lock('stripe-pi-' . $mode . '-' . $pi['id']);
            $row = $this->payment((string)$pi['id']);
            if ($row) return $row;
            $row = $this->db->row('SELECT * FROM stripe_invoice_payments WHERE id=? AND mode=? AND payment_intent_id IS NULL', [(int)($meta['saldi_payment_row'] ?? 0), $mode]);
            $op = $row ? $this->db->row("SELECT * FROM stripe_invoice_operations WHERE mode=? AND payment_id=? AND kind='intent_create' AND request_json IS NOT NULL ORDER BY id DESC LIMIT 1", [$mode, $row['id']]) : null;
            if (!$row || !$op) throw new RuntimeException('Rettelsen afventer binding til det gemte betalingsforsøg');
            $request = json_decode($op['request_json'], true, 512, JSON_THROW_ON_ERROR);
            foreach (['saldi_kind', 'saldi_db', 'saldi_order_id', 'saldi_konto_id', 'saldi_payment_row'] as $key) {
                if (!isset($meta[$key], $request['metadata'][$key]) || (string)$meta[$key] !== (string)$request['metadata'][$key]) {
                    throw new RuntimeException('Rettelsen matcher ikke den gemte betalingsoperation');
                }
            }
            $this->validatePaymentIdentity($pi, $row, $mode, $orderId, (string)$meta['saldi_kind']);
            if ((int)$op['order_id'] !== $orderId || (int)$op['amount_ore'] !== (int)$row['amount_ore']
                || (!empty($op['result_id']) && $op['result_id'] !== $pi['id'])) {
                throw new RuntimeException('Rettelsens operation eller beløb matcher ikke betalingsforsøget');
            }
            // 20261012 CL/LH Keep success recording for its own event/recovery; the correction applies the hold first.
            $this->db->query('UPDATE stripe_invoice_payments SET payment_intent_id=?,updated_at=? WHERE id=? AND payment_intent_id IS NULL', [$pi['id'], $this->db->now(), $row['id']]);
            $this->db->query("UPDATE stripe_invoice_operations SET result_id=?,status='succeeded',last_error=NULL,updated_at=? WHERE id=?", [$pi['id'], $this->db->now(), $op['id']]);
            return $this->payment((string)$pi['id']);
        });
    }

    /** @return list<array{id:int,order_id:int,fakturanr:mixed,hold_reason:mixed}> Staff-visible holds in this mode. */
    public function collectionHolds(int $kontoId): array
    {
        return $this->db->rows('SELECT p.id,p.order_id,o.fakturanr,p.hold_reason FROM stripe_invoice_payments p LEFT JOIN ordrer o ON o.id=p.order_id WHERE p.mode=? AND p.konto_id=? AND p.collection_hold=1 ORDER BY p.order_id,p.id', [$this->db->mode(), $kontoId]);
    }

    /** Release only the selected debtor's payment hold; settlement/history stay intact. */
    public function releaseCollectionHold(int $paymentId, int $kontoId, string $actor): bool
    {
        return $this->tx(function () use ($paymentId, $kontoId, $actor) {
            $mode = $this->db->mode();
            $row = $this->db->row('SELECT * FROM stripe_invoice_payments WHERE id=? AND mode=? AND konto_id=?', [$paymentId, $mode, $kontoId]);
            if (!$row) return false;
            $this->db->lock('stripe-pay-' . $mode . '-' . (int)$row['order_id']);
            $changed = $this->db->query('UPDATE stripe_invoice_payments SET collection_hold=0,hold_reason=NULL,updated_at=? WHERE id=? AND collection_hold=1', [$this->db->now(), $paymentId])->rowCount();
            if ($changed) $this->db->audit('payment', $paymentId, 'collection_released', $actor, ['order_id' => (int)$row['order_id'], 'reason' => $row['hold_reason']]);
            return $changed > 0;
        });
    }

    // ---------------------------------------------------------------- remove

    /** Recheck the current choice under the card lock before detaching a superseded method. */
    private function detachUnusedCard(array $card): void
    {
        $this->tx(function () use ($card) {
            $this->db->lock('stripe-card-' . $card['mode'] . '-' . (int)$card['konto_id']);
            $current = $this->db->row('SELECT payment_method_id,revoked_at FROM stripe_invoice_cards WHERE mode=? AND konto_id=?', [$card['mode'], (int)$card['konto_id']]);
            if ($current && $current['revoked_at'] === null && $current['payment_method_id'] === $card['payment_method_id']) return;
            $this->api('POST', '/v1/payment_methods/' . rawurlencode($card['payment_method_id']) . '/detach');
        });
    }

    /** Stop autopay and detach the card. @return bool false when no active card exists. */
    public function removeCard(int $kontoId, string $actor): bool
    {
        return $this->removeCardResult($kontoId, $actor)['stopped'];
    }

    /**
     * 20261012 CL/LH Separate outcomes so pages confirm only what happened (AP-30).
     * @return array{stopped:bool,detached:bool} stopped: Saldi will no longer charge the card;
     *         detached: Stripe confirmed the card is no longer attached (or already was not).
     */
    public function removeCardResult(int $kontoId, string $actor): array
    {
        return $this->debtorLock($kontoId, function () use ($kontoId, $actor) {
            return $this->removeCardLocked($kontoId, $actor);
        });
    }

    /** @return array{stopped:bool,detached:bool} */
    private function removeCardLocked(int $kontoId, string $actor): array
    {
        $mode = $this->db->mode();
        $card = $this->tx(function () use ($kontoId, $actor, $mode) {
            $this->db->lock('stripe-card-' . $mode . '-' . $kontoId);
            $card = $this->db->row('SELECT * FROM stripe_invoice_cards WHERE mode=? AND konto_id=? AND revoked_at IS NULL', [$mode, $kontoId]);
            if (!$card) return null;
            $now = $this->db->now();
            $this->db->query('UPDATE stripe_invoice_cards SET autopay=0,revoked_at=?,revoked_by=?,updated_at=?,generation=generation+1 WHERE id=?', [$now, mb_substr($actor, 0, 100), $now, $card['id']]);
            // 20261012 CL/LH Revocation cancels unsent charges and parks unknown dispatched outcomes.
            foreach ($this->db->rows("SELECT p.id,o.id AS operation_id,o.first_dispatch_at,o.status AS operation_status FROM stripe_invoice_payments p JOIN stripe_invoice_operations o ON o.payment_id=p.id AND o.kind='intent_create' AND o.mode=p.mode AND o.id=(SELECT MAX(latest.id) FROM stripe_invoice_operations latest WHERE latest.payment_id=p.id AND latest.mode=p.mode AND latest.kind='intent_create') WHERE p.mode=? AND p.konto_id=? AND p.kind='auto' AND p.status='created' AND p.payment_intent_id IS NULL", [$mode, $kontoId]) as $pending) {
                if ($pending['first_dispatch_at'] === null || $pending['operation_status'] === 'rejected') {
                    $this->db->query("UPDATE stripe_invoice_payments SET status='canceled',updated_at=? WHERE id=? AND status='created'", [$now, $pending['id']]);
                    $this->db->query("UPDATE stripe_invoice_operations SET status='canceled',updated_at=? WHERE id=? AND status<>'rejected'", [$now, $pending['operation_id']]);
                } else {
                    $this->db->query("UPDATE stripe_invoice_operations SET status='needs_reconcile',updated_at=? WHERE id=?", [$now, $pending['operation_id']]);
                    $this->notify('Betaling skal afstemmes hos Stripe', 'Debitor ' . $kontoId . ', betalingsforsøg ' . $pending['id'] . ': kortet er fjernet, men et tidligere træk har ukendt resultat. Afstem hos Stripe; forsøget sendes ikke igen.', 'reconcile-' . $pending['operation_id'], 0, 'needs_reconcile');
                }
            }
            $this->db->audit('card', $kontoId, 'card_removed', $actor, ['payment_method' => $card['payment_method_id'], 'last4' => $card['last4']]);
            return $card;
        });
        if (!$card) return ['stopped' => false, 'detached' => false];
        try {
            $this->detachUnusedCard($card);
        } catch (InvoicePaymentApiError $e) {
            if ($e->stripeCode !== 'payment_method_unexpected_state' && $e->stripeCode !== 'resource_missing') {
                $this->notify('Kort kunne ikke fjernes hos Stripe', 'Debitor-id ' . $kontoId . ', kort ' . $card['payment_method_id'] . ": automatisk betaling er stoppet i Saldi, men kortet er stadig gemt hos Stripe.\nFejl: " . $e->getMessage() . "\nFjern det i Stripe Dashboard.");
                return ['stopped' => true, 'detached' => false];
            }
        }
        return ['stopped' => true, 'detached' => true];
    }
}
