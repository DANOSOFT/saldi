<?php
// 20261022 CDX/LH Durable collection requests and recoverable charge/authentication/delivery work.
// 20261025 CL/ASR stripe_manual_fees (fee for manual payment, stripeManualFee.php).
// 20261109 CL/ASR stripe_reminders (link reminders, stripeReminder.php).
// 20261126 CL/ASR Review round 1 (SM-3): the information_schema checks filter on this database's schema (saldi_schema_filter()); on MySQL the view spans every database the user can see.
// 20261126 ASR Payment review (GPT-P-1): stripe_payments.card_state records whether a payment's "save the card" was applied to the customer, apart from its receipt.
//              The captures made before the column are classified once, when it is added (stripe_payment_card_state_backfill()).
// 20261126 ASR Payment review (GPT-G-1): a row from before card_state is NULL until it is classified, and a payment made after it is written with '' (stripe_payment_record()).
//              Every call classifies the rows still NULL, so a run that stopped between adding the column and classifying is completed by the next one, and a later payment is never classified as an earlier one.

/** @return void Provision the S3 schema on the current tenant, also called by betweenUpdates. */
function stripe_charge_schema_ensure() {
	$exists = db_fetch_array(stripe_payment_select("select table_name from information_schema.tables where table_name = 'stripe_payments'" . saldi_schema_filter()));
	if (!$exists) return; // MySQL tenants without the invoice schema keep their existing login path.
	$mysql = in_array($GLOBALS['db_type'], array('mysql', 'mysqli'), true);
	$serial = $mysql ? 'integer NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'SERIAL PRIMARY KEY';
	stripe_payment_modify("CREATE TABLE IF NOT EXISTS stripe_collection_attempts (
		id $serial, ordre_id integer NOT NULL, identity_no integer NOT NULL, primary_no integer NOT NULL,
		source varchar(10) NOT NULL, save_card boolean NOT NULL, params text NOT NULL,
		status varchar(20) NOT NULL, payment_intent_id varchar(255), charge_date date NOT NULL,
		created_at timestamp DEFAULT CURRENT_TIMESTAMP, request_started_at timestamp,
		UNIQUE (ordre_id, identity_no))");
	stripe_payment_modify("CREATE TABLE IF NOT EXISTS stripe_charge_errors (
		ordre_id integer PRIMARY KEY, first_date date NOT NULL, last_error text NOT NULL)");
	stripe_payment_modify("CREATE TABLE IF NOT EXISTS stripe_unenrol_mail (
		ordre_id integer PRIMARY KEY, sent_at timestamp)");
	// 20261025 CL/ASR fee for manual payment: one decision (charged | waived) per paid invoice
	stripe_payment_modify("CREATE TABLE IF NOT EXISTS stripe_manual_fees (
		ordre_id integer PRIMARY KEY, fee_ordre_id integer NOT NULL, status varchar(10) NOT NULL,
		created_at timestamp DEFAULT CURRENT_TIMESTAMP)");
	// 20261109 CL/ASR link reminders: one row per reminder order (draft | sent | charged | waived)
	require_once __DIR__ . '/stripeReminder.php';
	stripe_payment_modify(stripe_reminder_table_ddl());
	foreach (array('action_required_at' => 'timestamp', 'action_mail_sent_at' => 'timestamp',
		'failure_handled_at' => 'timestamp', 'attempt_date' => 'date', 'card_state' => 'varchar(12)') as $column => $type) {
		$exists = db_fetch_array(stripe_payment_select("select column_name from information_schema.columns
			where table_name = 'stripe_payments' and column_name = '$column'" . saldi_schema_filter()));
		if (!$exists) stripe_payment_modify("ALTER TABLE stripe_payments ADD COLUMN " . ($mysql ? '' : 'IF NOT EXISTS ') . "$column $type");
	}
	stripe_payment_card_state_backfill();
}

/**
 * stripe_payments.card_state: '' while a captured payment's "save the card" is still to be applied to the customer, then
 * applied, skipped (switches off, invoice not collectable by card) or superseded (the customer was unenrolled, or
 * a later payment's card was applied, before this one could be). A receipt retry never applies the card again.
 *
 * The rows from before the column are NULL until they are classified here, and a payment made after it is written with ''
 * (stripe_payment_record(), the one insert). NULL is the marker of what is still to be classified, so every
 * stripe_charge_schema_ensure() (the login update and the payment path, for a ledger a cron job or webhook reaches first)
 * classifies the rows still NULL: a run that stopped after the column was added is completed by the next call, and a
 * payment made since is never classified as an earlier one. stripe_payment_apply_card() classifies first too, for a retry
 * that comes before the next call. One UPDATE, run only when such rows exist.
 *
 * A captured payment that saved its card: applied when that card is the customer's card now; skipped when its receipt was
 * sent (decided then); superseded while its receipt is still pending, as the customer may have been unenrolled or got
 * another card since, so the retry sends only the receipt. Every other row gets '': a payment still under way applies its
 * card when it succeeds, as a new one does. Rows that already have a value, '' included, are left as they are.
 *
 * @return void
 */
function stripe_payment_card_state_backfill() {
	if (!db_fetch_array(stripe_payment_select("select id from stripe_payments where card_state is null limit 1"))) return;
	stripe_payment_modify("update stripe_payments set card_state = case
		when save_card = true and status in ('succeeded', 'refunded', 'disputed') then case
			when exists (select 1 from ordrer o join adresser a on a.id = o.konto_id where o.id = stripe_payments.ordre_id
				and a.stripe = 'on' and a.stripe_pm_id = stripe_payments.pm_id) then 'applied'
			when confirmation_sent_at is not null then 'skipped' else 'superseded' end
		else '' end
		where card_state is null");
}
