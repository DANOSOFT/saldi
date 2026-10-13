<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/InvoicePaymentOperations.php --- ver 5.0.0 --- 2026.10.13
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
// 20261012 CL/LH Durable immutable creating requests with tenant/mode keys and a 20-hour replay limit.
// 20261012 CL/LH Preserve definitive rejections and prepare fresh keys; ambiguous requests retain their cutoff.
// 20261012 CL/LH Persist dispatch ambiguity before Stripe calls; later rejections require reconciliation instead of fresh keys.
// 20261013 CL/LH Treat explicit local no-dispatch refusals as rejections while preserving earlier dispatch ambiguity.

namespace Saldi\Stripe;

use RuntimeException;
use Throwable;

final class InvoicePaymentOperations
{
    public const REPLAY_SECONDS = 20 * 3600;
    // 20261013 CL/LH Explicit no-dispatch refusals returned by stripeHttpRequest before curl_exec.
    private const LOCAL_REFUSALS = ['config_mode', 'config_key', 'config_mode_mismatch', 'config_method'];
    private InvoicePaymentStore $db;
    private string $tenant;

    public function __construct(InvoicePaymentStore $db, string $tenant)
    {
        $this->db = $db;
        $this->tenant = $tenant;
    }

    /**
     * First preparation wins: mutable customer/invoice data never rebuild a retry.
     * Must commit before dispatch; external_id identifies the local operation.
     * @return array<string,mixed> Stored operation, including the immutable request.
     */
    public function prepare(string $kind, string $externalId, string $path, array $params, int $order = 0, int $amount = 0, ?int $generation = null, ?int $payment = null): array
    {
        return $this->db->transaction(function () use ($kind, $externalId, $path, $params, $order, $amount, $generation, $payment) {
            $mode = $this->db->mode();
            $key = 'saldi-' . hash('sha256', $this->tenant) . '-' . $mode . '-' . $kind . '-' . $externalId;
            $this->db->query("INSERT INTO stripe_invoice_operations (mode,kind,external_id,order_id,amount_ore,data_json,request_path,request_json,idempotency_key,card_generation,payment_id,status,created_at,updated_at) VALUES (?,?,?,?,?,'{}',?,?,?,?,?,'prepared',?,?) ON CONFLICT (mode,kind,external_id) DO NOTHING",
                [$mode, $kind, $externalId, $order, $amount, $path, InvoicePaymentStore::json($params), $key, $generation, $payment, $this->db->now(), $this->db->now()]);
            $row = $this->db->row('SELECT * FROM stripe_invoice_operations WHERE mode=? AND kind=? AND external_id=?', [$mode, $kind, $externalId]);
            // 20261012 CL/LH A confirmed rejection created no usable object; retain its audit and use a new key.
            return $row['status'] === 'rejected' ? $this->replacement($row) : $row;
        });
    }

    /** @return array<string,mixed> Current operation, replacing a definitive rejection before callers handle its result. */
    public function resume(int $id): array
    {
        $row = $this->db->row('SELECT * FROM stripe_invoice_operations WHERE id=?', [$id]);
        if (!$row) throw new RuntimeException('Betalingsoperation mangler');
        return $row['status'] === 'rejected' ? $this->replacement($row) : $row;
    }

    /** @return array<string,mixed> Fresh durable operation after a definitive Stripe rejection. */
    private function replacement(array $row): array
    {
        if ($row['mode'] !== $this->db->mode()) throw new RuntimeException('Betalingsoperationen tilhører en anden Stripe-tilstand');
        return $this->prepare($row['kind'], 'retry-' . $row['id'], $row['request_path'],
            json_decode($row['request_json'], true, 512, JSON_THROW_ON_ERROR), (int)$row['order_id'],
            (int)$row['amount_ore'], $row['card_generation'] === null ? null : (int)$row['card_generation'],
            $row['payment_id'] === null ? null : (int)$row['payment_id']);
    }

    /**
     * Dispatch only committed operations. Every retry uses the stored bytes/key.
     * @param callable $api Service API adapter (method, path, params, key).
     * @param callable $reconcile Enqueues a durable alert inside the expiry transaction.
     * @return array<string,mixed> Stripe response (client secrets are not persisted).
     */
    public function dispatch(int $id, callable $api, callable $reconcile): array
    {
        if ($this->db->inTransaction()) throw new RuntimeException('Stripe-dispatch kræver en gemt operation uden åben transaktion');
        return $this->db->serialized('stripe-operation-' . $this->tenant . '-' . $id, function () use ($id, $api, $reconcile) {
            $previous = $this->db->row('SELECT * FROM stripe_invoice_operations WHERE id=?', [$id]);
            if ($previous && $previous['status'] === 'rejected') {
                $fresh = $this->replacement($previous);
                return $this->dispatch((int)$fresh['id'], $api, $reconcile);
            }
            $row = $this->db->transaction(function () use ($id, $reconcile) {
                $row = $this->db->row('SELECT * FROM stripe_invoice_operations WHERE id=?', [$id]);
                if (!$row || !$row['request_json']) throw new RuntimeException('Betalingsoperation mangler');
                if ($row['status'] === 'needs_reconcile' || ($row['first_dispatch_at'] !== null && $this->db->now() - (int)$row['first_dispatch_at'] >= self::REPLAY_SECONDS)) {
                    $this->db->query("UPDATE stripe_invoice_operations SET status='needs_reconcile',updated_at=? WHERE id=?", [$this->db->now(), $id]);
                    $reconcile($row);
                    return null;
                }
                // 20261012 CL/LH A crash or lost response leaves a durable unknown outcome; only this dispatch can prove otherwise.
                $this->db->query("UPDATE stripe_invoice_operations SET first_dispatch_at=COALESCE(first_dispatch_at,?),ambiguous_dispatch=1,status='dispatched',updated_at=? WHERE id=?", [$this->db->now(), $this->db->now(), $id]);
                return $row;
            });
            if (!$row) throw new RuntimeException('Betaling er allerede i gang. Betalingen skal afstemmes hos Stripe.');
            try {
                $response = $api('POST', $row['request_path'], json_decode($row['request_json'], true, 512, JSON_THROW_ON_ERROR), $row['idempotency_key']);
                $this->db->query("UPDATE stripe_invoice_operations SET status='succeeded',result_id=?,ambiguous_dispatch=?,last_error=NULL,updated_at=? WHERE id=?", [(string)($response['id'] ?? ''), (int)$row['ambiguous_dispatch'], $this->db->now(), $id]);
                return $response;
            } catch (Throwable $e) {
                // 20261012 CL/LH A definitive rejection proves only this delivery failed, never an earlier unknown outcome.
                // 20261013 CL/LH Status zero is definitive only for known local refusals, never arbitrary transport errors.
                $rejected = $e instanceof InvoicePaymentApiError && (
                    ($e->status === 0 && in_array($e->stripeCode, self::LOCAL_REFUSALS, true))
                    || ($e->status >= 400 && $e->status < 500 && !$e->transient()
                        && !in_array($e->status, [409, 429], true) && is_array($e->body['error'] ?? null))
                );
                $ambiguous = (int)$row['ambiguous_dispatch'] === 1 || !$rejected;
                $status = $rejected ? ($ambiguous ? 'needs_reconcile' : 'rejected') : 'dispatched';
                $this->db->transaction(function () use ($id, $row, $e, $ambiguous, $status, $reconcile) {
                    $changed = $this->db->query('UPDATE stripe_invoice_operations SET status=?,ambiguous_dispatch=?,last_error=?,updated_at=? WHERE id=? AND status=\'dispatched\' AND result_id IS NULL',
                        [$status, $ambiguous ? 1 : 0, mb_substr($e->getMessage(), 0, 500), $this->db->now(), $id])->rowCount();
                    if ($changed && $status === 'needs_reconcile') $reconcile($row);
                });
                throw $e;
            }
        });
    }
}
