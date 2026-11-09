<?php
// 20261022 CDX/LH Exclude captured and in-flight invoice collections from reminder selection.
// 20261022 CDX/LH Release abandoned page payments to reminders after three days; retain processing and saved-card authentication protection.
// 20261109 CL/ASR Card-on-file hold: an invoice the charge job still retries (card on file, fewer than 3 primary declines, or fewer than 3 days since the 3rd) cannot enter reminders.
//                 Saved-card authentication is protected for 14 days, no longer forever.

/**
 * Predicate for an openpost row which can enter dunning. This lightweight include does
 * not initialize Stripe or require Stripe settings on ordinary customer installations.
 * Captures stay protected while their journal is unposted; failed/canceled attempts do not.
 * Saved-card authentication (source other than the page) is protected for 14 days.
 * An invoice the charge job still collects is held (stripe_dunning_card_hold_sql()).
 *
 * @param string $alias Trusted SQL alias for openpost.
 * @return string SQL predicate; 1=1 for installations without stripe_payments.
 */
function stripe_dunning_openpost_sql($alias = 'openpost') {
	if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $alias)) throw new InvalidArgumentException('Invalid openpost alias');
	$mysql = in_array($GLOBALS['db_type'], array('mysql', 'mysqli'), true);
	$schema = $mysql ? ' and table_schema = database()' : '';
	$exists = db_select("select column_name from information_schema.columns where table_name = 'stripe_payments'
		and column_name in ('payment_intent_id', 'attempt_date', 'action_required_at')$schema", __FILE__ . ' linje ' . __LINE__);
	if ($exists === false) throw new RuntimeException('Cannot check Stripe dunning protection');
	$columns = array();
	while ($column = db_fetch_array($exists)) $columns[] = strtolower((string)$column['column_name']);
	if (!in_array('payment_intent_id', $columns, true)) return '1=1';
	$recent = $mysql
		? 's_dp.created_at > CURRENT_TIMESTAMP - INTERVAL 3 DAY'
		: "s_dp.created_at > CURRENT_TIMESTAMP - interval '3 days'";
	$recentAction = $mysql
		? 's_dp.created_at > CURRENT_TIMESTAMP - INTERVAL 14 DAY'
		: "s_dp.created_at > CURRENT_TIMESTAMP - interval '14 days'";
	return "not exists (select 1 from stripe_payments s_dp join ordrer s_do on s_do.id = s_dp.ordre_id
		where s_do.konto_id = $alias.konto_id and s_do.fakturanr = $alias.faktnr and s_do.art = 'DO'
		and (s_dp.status in ('succeeded', 'duplicate_paid', 'refunded', 'disputed', 'unmapped', 'processing')
			or (s_dp.status = 'pending' and $recent)
			or (s_dp.status = 'requires_action' and ((s_dp.source <> 'page' and $recentAction) or $recent))))"
		. stripe_dunning_card_hold_sql($alias, $columns);
}

/**
 * Card-on-file hold: a 'Stripe' invoice (Udskriv til) of a customer with automatic card payment, issued on or after
 * the enrolment, is collected by the charge job with retries; it cannot enter reminders while it has fewer than 3
 * primary declines, nor until 3 days after the 3rd. A primary decline is the SQL mirror of stripe_primary_declines()
 * and stripe_payment_attempt_failed(): source 'auto', no authentication request, failed or canceled with a decline
 * code (not a technical error). Declines are counted on their job date (attempt_date, else created_at); attempts are
 * chronological, so "the 3rd decline is at least 3 days old" equals "3 declines dated at least 3 days ago". Only
 * while Stripe card payment and automatic payment are on in the ledger (otherwise nothing collects the invoice).
 *
 * @param string $alias Trusted SQL alias for openpost.
 * @param array<int, string> $columns The stripe_payments columns found (attempt_date, action_required_at).
 * @return string ' and not exists (...)', or '' when the hold cannot apply.
 */
function stripe_dunning_card_hold_sql($alias, $columns) {
	$on = array();
	$q = db_select("select var_name, var_value from settings where var_grp = 'stripe' and var_name in ('enabled', 'invoice_payments', 'enrolment')", __FILE__ . ' linje ' . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (trim((string)$r['var_value']) === 'on') $on[$r['var_name']] = true;
	}
	if (count($on) < 3) return '';
	$schema = in_array($GLOBALS['db_type'], array('mysql', 'mysqli'), true) ? ' and table_schema = database()' : '';
	$r = db_fetch_array(db_select("select count(*) as n from information_schema.columns where table_name = 'adresser'
		and column_name in ('stripe', 'stripe_date')$schema", __FILE__ . ' linje ' . __LINE__));
	if (!$r || (int)$r['n'] < 2) return '';
	$today = function_exists('stripe_today') ? stripe_today() : (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
	$released = (new DateTimeImmutable($today, new DateTimeZone('Europe/Copenhagen')))->modify('-3 days')->format('Y-m-d');
	$day = in_array('attempt_date', $columns, true) ? 'coalesce(s_hp.attempt_date, cast(s_hp.created_at as date))' : 'cast(s_hp.created_at as date)';
	$noAction = in_array('action_required_at', $columns, true) ? ' and s_hp.action_required_at is null' : '';
	return " and not exists (select 1 from ordrer s_ho join adresser s_ha on s_ha.id = s_ho.konto_id
		where s_ho.konto_id = $alias.konto_id and s_ho.fakturanr = $alias.faktnr and s_ho.art = 'DO'
		and s_ho.udskriv_til = 'Stripe' and s_ha.stripe = 'on' and s_ha.stripe_date is not null and s_ho.fakturadate >= s_ha.stripe_date
		and (select count(*) from stripe_payments s_hp where s_hp.ordre_id = s_ho.id and s_hp.source = 'auto'$noAction
			and (s_hp.status = 'failed' or (s_hp.status = 'canceled' and coalesce(s_hp.last_error, '') <> ''
				and s_hp.last_error not in ('authentication_required', 'technical_error', 'api_error', 'network', 'rate_limit')))
			and $day <= '$released') < 3)";
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
