<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/InvoicePaymentSchema.php --- 2026.10.12
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
// 20261007 CL/LH Created: tables for paying a Saldi invoice by card on the
//                hosted invoice page and charging a saved card on later due
//                dates. includes/betweenUpdates.php and admin/opret.php run
//                these same statements; the tests run them on SQLite and PG.
// 20261012 CL/LH Add event-time/consent columns, serialized non-fatal provisioning and full schema assertions.

// 20261012 CL/LH Add immutable Stripe dispatches and the notification outbox; allow posted_manual settlement.
// 20261012 CL/LH Record the consent version and text hash the customer accepted on the payment row.
// 20261012 CL/LH Persist consent-grant time independently of intent creation and the Stripe error type for decline classification.
// 20261012 CL/LH Persist sticky dispatch ambiguity and failed-charge identity for stable API recovery facts.

namespace Saldi\Stripe;

final class InvoicePaymentSchema
{
    /** @return list<string> Idempotent DDL for the given PDO driver name ('pgsql' or 'sqlite'). */
    public static function statements(string $driver): array
    {
        if (!in_array($driver, ['pgsql', 'sqlite'], true)) throw new \RuntimeException('Kortbetaling af fakturaer kræver PostgreSQL');
        $id = $driver === 'pgsql' ? 'id BIGSERIAL PRIMARY KEY' : 'id INTEGER PRIMARY KEY AUTOINCREMENT';
        return [
            // One Stripe customer per debitor and mode; local truth so a lost
            // response never creates a second customer for the same debitor.
            "CREATE TABLE IF NOT EXISTS stripe_invoice_customers ($id, mode VARCHAR(4) NOT NULL, konto_id INTEGER NOT NULL, customer_id VARCHAR(64) NOT NULL, created_at BIGINT NOT NULL, UNIQUE (mode, konto_id))",
            // The saved card and the customer's consent to automatic payment.
            "CREATE TABLE IF NOT EXISTS stripe_invoice_cards ($id, mode VARCHAR(4) NOT NULL, konto_id INTEGER NOT NULL, customer_id VARCHAR(64) NOT NULL, payment_method_id VARCHAR(64) NOT NULL, brand VARCHAR(32) NOT NULL DEFAULT '', last4 VARCHAR(4) NOT NULL DEFAULT '', exp_month INTEGER NOT NULL DEFAULT 0, exp_year INTEGER NOT NULL DEFAULT 0, autopay SMALLINT NOT NULL DEFAULT 0, generation INT NOT NULL DEFAULT 1, terms_version VARCHAR(30) NULL, consent_text_sha256 VARCHAR(64) NULL, consent_at BIGINT, consent_order_id INTEGER, revoked_at BIGINT, revoked_by VARCHAR(100), broken_at BIGINT, broken_reason VARCHAR(200), updated_at BIGINT NOT NULL, UNIQUE (mode, konto_id))",
            // One row per PaymentIntent. An auto row exists before its intent
            // (payment_intent_id NULL) so a crash between insert and Stripe
            // call is resumed with the same idempotency key.
            "CREATE TABLE IF NOT EXISTS stripe_invoice_payments ($id, mode VARCHAR(4) NOT NULL, order_id INTEGER NOT NULL, konto_id INTEGER NOT NULL, payment_intent_id VARCHAR(64), amount_ore BIGINT NOT NULL, kind VARCHAR(8) NOT NULL, attempt INTEGER NOT NULL DEFAULT 1, status VARCHAR(32) NOT NULL, save_card SMALLINT NOT NULL DEFAULT 0, terms_version VARCHAR(30) NULL, consent_text_sha256 VARCHAR(64) NULL, consent_granted_at BIGINT NULL, received_at BIGINT NULL, failed_at BIGINT NULL, failed_charge VARCHAR(255) NULL, event_created BIGINT NULL, payment_method VARCHAR(255) NULL, card_generation INT NULL, open_ore_at_receipt BIGINT NULL, collection_hold SMALLINT NOT NULL DEFAULT 0, hold_reason VARCHAR(255) NULL, error_code VARCHAR(64), error_type VARCHAR(32) NULL, advice_code VARCHAR(64), error_message VARCHAR(500), settlement VARCHAR(16) NOT NULL DEFAULT 'pending', settle_error VARCHAR(500), settled_at BIGINT, created_at BIGINT NOT NULL, updated_at BIGINT NOT NULL, UNIQUE (mode, payment_intent_id))",
            "CREATE INDEX IF NOT EXISTS stripe_invoice_payments_order ON stripe_invoice_payments (mode, order_id)",
            "CREATE INDEX IF NOT EXISTS stripe_invoice_payments_received ON stripe_invoice_payments (mode, konto_id, received_at, id)",
            "CREATE INDEX IF NOT EXISTS stripe_invoice_payments_failed ON stripe_invoice_payments (mode, konto_id, failed_at, id)",
            // Ledger idempotency: one receipt per PaymentIntent, ever.
            "CREATE TABLE IF NOT EXISTS stripe_invoice_operations ($id, mode VARCHAR(4) NOT NULL, kind VARCHAR(32) NOT NULL, external_id VARCHAR(255) NOT NULL, order_id INTEGER NOT NULL DEFAULT 0, amount_ore BIGINT NOT NULL DEFAULT 0, data_json TEXT NOT NULL, request_path VARCHAR(255) NULL, request_json TEXT NULL, idempotency_key VARCHAR(255) NULL, first_dispatch_at BIGINT NULL, ambiguous_dispatch SMALLINT NOT NULL DEFAULT 0, card_generation INT NULL, payment_id BIGINT NULL, status VARCHAR(32) NOT NULL DEFAULT 'succeeded', result_id VARCHAR(255) NULL, last_error VARCHAR(500) NULL, updated_at BIGINT NULL, created_at BIGINT NOT NULL, UNIQUE (mode, kind, external_id))",
            "CREATE TABLE IF NOT EXISTS stripe_invoice_notifications ($id, kind VARCHAR(64) NOT NULL, dedupe_key VARCHAR(255) NOT NULL UNIQUE, subject VARCHAR(255) NOT NULL, body TEXT NOT NULL, created_at BIGINT NOT NULL, sent_at BIGINT NULL, attempts INT NOT NULL DEFAULT 0, last_error VARCHAR(500) NULL)",
            "CREATE TABLE IF NOT EXISTS stripe_invoice_audit ($id, entity_type VARCHAR(32) NOT NULL, entity_id INTEGER NOT NULL, action VARCHAR(64) NOT NULL, actor VARCHAR(100) NOT NULL, data_json TEXT NOT NULL, created_at BIGINT NOT NULL)",
        ];
    }

    /**
     * Check every declared table column and index; no DDL in the runtime.
     * @throws \RuntimeException When provisioning is incomplete.
     */
    public static function assertProvisioned(InvoicePaymentStore $db): void
    {
        foreach (self::statements($db->driver()) as $sql) {
            if (preg_match('/^CREATE TABLE IF NOT EXISTS (\w+) \((.*)\)$/', $sql, $table)) {
                preg_match_all('/(?:^|, )([a-z_][a-z0-9_]*) (?:BIGSERIAL|INTEGER|BIGINT|SMALLINT|INT|VARCHAR|TEXT)\b/', $table[2], $columns);
                $db->query('SELECT ' . implode(',', $columns[1]) . ' FROM ' . $table[1] . ' LIMIT 0');
            } elseif (preg_match('/^CREATE INDEX IF NOT EXISTS (\w+)/', $sql, $index)) {
                $row = $db->driver() === 'pgsql'
                    ? $db->row('SELECT to_regclass(?) AS t', [$index[1]])
                    : $db->row("SELECT name AS t FROM sqlite_master WHERE type='index' AND name=?", [$index[1]]);
                if (empty($row['t'])) throw new \RuntimeException('Stripe-skemaet mangler indeks: ' . $index[1]);
            }
        }
    }

    /**
     * Idempotent provisioning on a dedicated connection; login must survive DDL failures.
     * @return bool True when all payment tables and indexes are ready, false after a logged failure.
     */
    public static function provision(InvoicePaymentStore $db, ?callable $log = null): bool
    {
        try {
            $db->transaction(function () use ($db) {
                // 20261012 CL/LH Concurrent logins serialize table/index creation.
                $db->lock('stripe-invoice-schema');
                foreach (self::statements($db->driver()) as $sql) $db->query($sql);
                self::assertProvisioned($db);
            });
            return true;
        } catch (\Throwable $e) {
            $message = 'Stripe-fakturabetaling er ikke klargjort: ' . $e->getMessage();
            if ($log !== null) $log($message);
            else error_log($message);
            return false;
        }
    }

    /**
     * stripe_events already exists in production, unlike the new invoice tables.
     * @return void Missing event-lease columns are added in the current tenant only.
     */
    public static function upgradeEvents(InvoicePaymentStore $db): void
    {
        $db->transaction(function () use ($db) {
            $db->lock('stripe-events-schema');
            $rows = $db->driver() === 'pgsql'
                ? $db->rows("SELECT column_name FROM information_schema.columns WHERE table_schema=current_schema() AND table_name='stripe_events'")
                : $db->rows('PRAGMA table_info(stripe_events)');
            if (!$rows) throw new \RuntimeException('Stripe-skemaet mangler stripe_events');
            $columns = array_column($rows, $db->driver() === 'pgsql' ? 'column_name' : 'name');
            foreach (['claim_token' => 'VARCHAR(64) NULL', 'claimed_at' => 'TIMESTAMP NULL', 'attempts' => 'INT NOT NULL DEFAULT 0', 'event_created' => 'BIGINT NULL'] as $column => $definition) {
                if (!in_array($column, $columns, true)) $db->query('ALTER TABLE stripe_events ADD COLUMN ' . $column . ' ' . $definition);
            }
        });
    }

}
