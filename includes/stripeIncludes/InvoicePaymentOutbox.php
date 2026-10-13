<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/InvoicePaymentOutbox.php --- 2026.10.12
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
// 20261012 CL/LH Persist required notifications with business facts and retry delivery after commit.

namespace Saldi\Stripe;

use RuntimeException;
use Throwable;

final class InvoicePaymentOutbox
{
    private InvoicePaymentStore $db;
    private $send;
    private array $attempted = [];

    public function __construct(InvoicePaymentStore $db, callable $send)
    {
        $this->db = $db;
        $this->send = $send;
    }

    /** Enqueue within the caller's business transaction; duplicate facts send once. */
    public function enqueue(string $kind, string $key, string $subject, string $body): void
    {
        $this->db->query('INSERT INTO stripe_invoice_notifications (kind,dedupe_key,subject,body,created_at) VALUES (?,?,?,?,?) ON CONFLICT (dedupe_key) DO NOTHING',
            [$kind, $key, $subject, $body, $this->db->now()]);
    }

    /**
     * Attempt each pending mail once per request, independently of business writes.
     * A new worker/request retries failures. Delivery is at least once if a process
     * dies between mail acceptance and sent_at; never replay money to resend mail.
     * @return int Successfully delivered notifications.
     */
    public function flush(): int
    {
        if ($this->db->inTransaction()) return 0;
        $sent = 0;
        foreach ($this->db->rows('SELECT id FROM stripe_invoice_notifications WHERE sent_at IS NULL ORDER BY attempts,id LIMIT 100') as $candidate) {
            $id = (int)$candidate['id'];
            if (isset($this->attempted[$id])) continue;
            $this->attempted[$id] = true;
            $sent += $this->db->serialized('stripe-notification-' . $id, function () use ($id) {
                $row = $this->db->row('SELECT * FROM stripe_invoice_notifications WHERE id=? AND sent_at IS NULL', [$id]);
                if (!$row) return 0;
                $this->db->query('UPDATE stripe_invoice_notifications SET attempts=attempts+1 WHERE id=?', [$id]);
                try {
                    // The durable queue owns dedupe; filesystem mail throttling must not consume retries.
                    if (($this->send)($row['subject'], $row['body'], '', 0) === false) {
                        throw new RuntimeException('Mail kunne ikke sendes');
                    }
                    $this->db->query('UPDATE stripe_invoice_notifications SET sent_at=?,last_error=NULL WHERE id=?', [$this->db->now(), $id]);
                    return 1;
                } catch (Throwable $e) {
                    $this->db->query('UPDATE stripe_invoice_notifications SET last_error=? WHERE id=?', [mb_substr($e->getMessage(), 0, 500), $id]);
                    return 0;
                }
            });
        }
        return $sent;
    }
}
