<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/StripeEventLedger.php --- 2026.10.12
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
// 20261012 CL/LH Own webhook event leases, recoverable retries and operator requeue.

namespace Saldi\Stripe;

require_once __DIR__ . '/InvoicePaymentStore.php';

use PDO;
use RuntimeException;

final class StripeEventLedger
{
    public const LEASE_SECONDS = 300;
    public const TERMINAL = ['processed', 'recorded', 'ignored', 'ignored_mode', 'failed_permanent'];
    private InvoicePaymentStore $db;

    public function __construct(PDO $pdo, ?callable $clock = null)
    {
        $this->db = new InvoicePaymentStore($pdo, $clock);
        if (!in_array($this->db->driver(), ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException('Stripe-webhook kræver PostgreSQL');
        }
    }

    /**
     * Insert once, then atomically claim a non-terminal event for five minutes.
     * @return array{action:'process',id:int,token:string}|array{action:'duplicate'|'busy'}
     */
    public function receive(string $eventId, string $type, string $payloadB64, int $eventCreated): array
    {
        $now = $this->db->now();
        $stamp = gmdate('Y-m-d H:i:s', $now);
        // 20261012 CL/LH ON CONFLICT avoids aborting a PostgreSQL transaction on duplicate delivery.
        $this->db->query("INSERT INTO stripe_events (event_id,event_type,payload,status,received_at,event_created) VALUES (?,?,?,'received',?,?) ON CONFLICT (event_id) DO NOTHING",
            [$eventId, $type, $payloadB64, $stamp, $eventCreated]);
        $token = bin2hex(random_bytes(32));
        $terminal = "'" . implode("','", self::TERMINAL) . "'";
        $this->db->query("UPDATE stripe_events SET claim_token=?,claimed_at=?,status='received' WHERE event_id=? AND status NOT IN ($terminal) AND (claim_token IS NULL OR claimed_at IS NULL OR claimed_at<?)",
            [$token, $stamp, $eventId, gmdate('Y-m-d H:i:s', $now - self::LEASE_SECONDS)]);
        $row = $this->db->row('SELECT id,status,claim_token FROM stripe_events WHERE event_id=?', [$eventId]);
        if (!$row) throw new RuntimeException('Stripe-event kunne ikke gemmes');
        if (in_array($row['status'], self::TERMINAL, true)) return ['action' => 'duplicate'];
        if ($row['claim_token'] !== $token) return ['action' => 'busy'];
        return ['action' => 'process', 'id' => (int)$row['id'], 'token' => $token];
    }

    /**
     * Finish only the current owner's terminal outcome.
     * @param array{saldi_order_id?:int,invoice_number?:string,error?:string|null} $fields
     * @return bool False for a stale owner; the row remains untouched.
     */
    public function finish(int $id, string $token, string $status, array $fields = []): bool
    {
        if (!in_array($status, self::TERMINAL, true)) throw new RuntimeException('Ugyldig terminal eventstatus');
        $sets = ['status=?', 'processed_at=?', 'claim_token=NULL', 'claimed_at=NULL'];
        $args = [$status, gmdate('Y-m-d H:i:s', $this->db->now())];
        foreach (['saldi_order_id', 'invoice_number', 'error'] as $field) {
            if (array_key_exists($field, $fields)) {
                $sets[] = $field . '=?';
                $args[] = $fields[$field];
            }
        }
        $args[] = $id;
        $args[] = $token;
        return $this->db->query('UPDATE stripe_events SET ' . implode(',', $sets) . ' WHERE id=? AND claim_token=?', $args)->rowCount() > 0;
    }

    /** Release a failed attempt immediately so the next delivery really processes it. */
    public function fail(int $id, string $token, string $error): void
    {
        $this->db->query("UPDATE stripe_events SET status='retry',error=?,processed_at=NULL,claim_token=NULL,claimed_at=NULL,attempts=COALESCE(attempts,0)+1 WHERE id=? AND claim_token=?",
            [$error, $id, $token]);
    }

    /**
     * Requeue a stuck event; the operator must then resend it from Stripe.
     * @return array{id:int,previousStatus:string}
     */
    public function requeue(string $eventId, bool $force = false): array
    {
        return $this->db->transaction(function () use ($eventId, $force) {
            $lock = $this->db->driver() === 'pgsql' ? ' FOR UPDATE' : '';
            $row = $this->db->row('SELECT id,status FROM stripe_events WHERE event_id=?' . $lock, [$eventId]);
            if (!$row) throw new RuntimeException('Stripe-event findes ikke');
            if (!$force && in_array($row['status'], self::TERMINAL, true)) {
                throw new RuntimeException('Eventet er afsluttet; brug --force for at genåbne det');
            }
            $this->db->query("UPDATE stripe_events SET status='retry',claim_token=NULL,claimed_at=NULL,processed_at=NULL WHERE id=?", [(int)$row['id']]);
            return ['id' => (int)$row['id'], 'previousStatus' => (string)$row['status']];
        });
    }
}
