<?php
// 20261022 CDX/LH Recreate each missing PostgreSQL invoice payment index independently of table creation.

/** @return void Repair missing payment indexes on PostgreSQL; leave MySQL's existing schema untouched. */
function stripe_payment_indexes_ensure() {
	global $db_type;
	if (in_array($db_type, array('mysql', 'mysqli'), true)) return;
	$indexes = array(
		'stripe_payments_pi_uidx' => 'UNIQUE INDEX IF NOT EXISTS stripe_payments_pi_uidx ON stripe_payments (payment_intent_id)',
		'stripe_payments_succeeded_uidx' => "UNIQUE INDEX IF NOT EXISTS stripe_payments_succeeded_uidx ON stripe_payments (ordre_id) WHERE status = 'succeeded'",
		'stripe_payments_ordre_idx' => 'INDEX IF NOT EXISTS stripe_payments_ordre_idx ON stripe_payments (ordre_id)',
	);
	foreach ($indexes as $name => $definition) {
		$exists = db_fetch_array(db_select("select indexname from pg_indexes where schemaname = current_schema()
			and tablename = 'stripe_payments' and indexname = '$name'", __FILE__ . ' linje ' . __LINE__));
		if (!$exists) {
			db_modify('CREATE ' . $definition, __FILE__ . ' linje ' . __LINE__);
		}
	}
}
