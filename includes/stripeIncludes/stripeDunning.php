<?php
// 20261022 CDX/LH Exclude captured and in-flight invoice collections from reminder selection.
// 20261022 CDX/LH Release abandoned page payments to reminders after three days; retain processing and saved-card authentication protection.

/**
 * Predicate for an openpost row which can enter dunning. This lightweight include does
 * not initialize Stripe or require Stripe settings on ordinary customer installations.
 * Captures stay protected while their journal is unposted; failed/canceled attempts do not.
 *
 * @param string $alias Trusted SQL alias for openpost.
 * @return string SQL predicate; 1=1 for installations without stripe_payments.
 */
function stripe_dunning_openpost_sql($alias = 'openpost') {
	if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $alias)) throw new InvalidArgumentException('Invalid openpost alias');
	$schema = in_array($GLOBALS['db_type'], array('mysql', 'mysqli'), true) ? ' and table_schema = database()' : '';
	$exists = db_select("select column_name from information_schema.columns where table_name = 'stripe_payments' and column_name = 'payment_intent_id'$schema", __FILE__ . ' linje ' . __LINE__);
	if ($exists === false) throw new RuntimeException('Cannot check Stripe dunning protection');
	if (!db_fetch_array($exists)) return '1=1';
	$recent = in_array($GLOBALS['db_type'], array('mysql', 'mysqli'), true)
		? 's_dp.created_at > CURRENT_TIMESTAMP - INTERVAL 3 DAY'
		: "s_dp.created_at > CURRENT_TIMESTAMP - interval '3 days'";
	return "not exists (select 1 from stripe_payments s_dp join ordrer s_do on s_do.id = s_dp.ordre_id
		where s_do.konto_id = $alias.konto_id and s_do.fakturanr = $alias.faktnr and s_do.art = 'DO'
		and (s_dp.status in ('succeeded', 'duplicate_paid', 'refunded', 'disputed', 'unmapped', 'processing')
			or (s_dp.status = 'pending' and $recent)
			or (s_dp.status = 'requires_action' and (s_dp.source <> 'page' or $recent))))";
}

/**
 * Hold an existing reminder containing a protected invoice, including its escalation fees.
 *
 * @param string $alias Trusted SQL alias for the reminder order.
 * @return string SQL predicate for reminder selection.
 */
function stripe_dunning_reminder_sql($alias = 'ordrer') {
	if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $alias)) throw new InvalidArgumentException('Invalid reminder alias');
	$eligible = stripe_dunning_openpost_sql('s_op');
	$idText = in_array($GLOBALS['db_type'], array('mysql', 'mysqli'), true) ? 'cast(s_op.id as char(30))' : 'cast(s_op.id as varchar(30))';
	return "not exists (select 1 from ordrelinjer s_dl join openpost s_op on s_dl.enhed = $idText
		where s_dl.ordre_id = $alias.id and not ($eligible))";
}
