<?php
// 20261022 CDX/LH Durable collection requests and recoverable charge/authentication/delivery work.
// 20261025 CL/ASR stripe_manual_fees (fee for manual payment, stripeManualFee.php).

/** @return void Provision the S3 schema on the current tenant, also called by betweenUpdates. */
function stripe_charge_schema_ensure() {
	$exists = db_fetch_array(stripe_payment_select("select table_name from information_schema.tables where table_name = 'stripe_payments'"));
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
	foreach (array('action_required_at' => 'timestamp', 'action_mail_sent_at' => 'timestamp',
		'failure_handled_at' => 'timestamp', 'attempt_date' => 'date') as $column => $type) {
		$exists = db_fetch_array(stripe_payment_select("select column_name from information_schema.columns
			where table_name = 'stripe_payments' and column_name = '$column'"));
		if (!$exists) stripe_payment_modify("ALTER TABLE stripe_payments ADD COLUMN " . ($mysql ? '' : 'IF NOT EXISTS ') . "$column $type");
	}
}
