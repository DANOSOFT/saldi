<?php
// includes/opdat_func/stripeAdjustmentAlerts.php — ver 5.1.1 — 2026.11.26
// Copyright (c) 2026 Danosoft ApS. GNU GPL version 2 or later.
// 20261126 ASR Payment review (GPT-P-4): the bookkeeper alert for each refund or dispute of a Stripe invoice payment is kept until it is sent; installed by betweenUpdates.php only.

/**
 * Install stripe_adjustment_alerts: one row per refund or dispute event of a Stripe invoice payment, whose status
 * stripe_payment_apply_adjustment() has applied, until the bookkeeper's alert about it is sent (sent_at). The webhook
 * redelivery and the charge job's repair pass send what is still unsent (includes/stripeIncludes/stripePaymentBooking.php).
 * PostgreSQL only, like stripe_payments.
 *
 * @return void
 */
function stripe_adjustment_alerts_schema_ensure(): void
{
    if (in_array($GLOBALS['db_type'], array('mysql', 'mysqli'), true)) return;
    db_modify("CREATE TABLE IF NOT EXISTS stripe_adjustment_alerts (
        event_id varchar(255) PRIMARY KEY, payment_id integer NOT NULL, event_type varchar(40) NOT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP, sent_at timestamp
    )", __FILE__ . ' line ' . __LINE__);
}
