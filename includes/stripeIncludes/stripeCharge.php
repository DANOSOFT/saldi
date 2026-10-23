<?php
// 20261022 CDX/LH Shared charge runner, durable authentication recovery and synchronous/asynchronous backup handling.
// 20261022 CL/LH S5 Greeting and invoice details in the authentication mail; customer and raw Stripe code in bookkeeper alerts.
// 20261022 CDX/LH Share the full repair/collection job and exit code with tenant-only regression tests.

/**
 * Run the CLI's tenant work after bootstrap and acquisition of the whole-job lock.
 * Schema initialization may write even in dry-run; repair-only never collects.
 *
 * @return array{stats: array<string, int>, exit_code: int} Summary counters and process exit code.
 */
function stripe_charge_job($today, $dryRun = false, $repairOnly = false) {
	$previous = $GLOBALS['stripe_charge_today'] ?? null;
	$GLOBALS['stripe_charge_today'] = $today;
	try {
		stripe_payments_ensure();
		$repair = stripe_payment_repair($dryRun);
		$mailErrors = stripe_unenrol_mail_retry(0, $dryRun);
		$stats = $repairOnly ? array('errors' => 0) : stripe_charge_run($today, $dryRun);
		$stats['repair_found'] = $repair['found'];
		$stats['repaired'] = $repair['repaired'];
		$stats['repair_failed'] = $repair['failed'];
		$stats['mail_failed'] = $mailErrors;
		$stats['errors'] += $repair['failed'] + $mailErrors;
		return array('stats' => $stats, 'exit_code' => $stats['errors'] > 0 ? 1 : 0);
	} finally {
		if ($previous === null) {
			unset($GLOBALS['stripe_charge_today']);
		} else {
			$GLOBALS['stripe_charge_today'] = $previous;
		}
	}
}

/**
 * Hold this handle for the entire CLI run, including repairs. Never unlink a flock file.
 *
 * @return resource|false Lock handle, or false when another process owns it.
 */
function stripe_charge_job_lock($ledger, $directory = null) {
	$file = ($directory ?? sys_get_temp_dir()) . '/saldi-stripe-charge-' . hash('sha256', dirname(__DIR__, 2) . ':' . $ledger) . '.lock';
	$handle = fopen($file, 'c');
	if ($handle === false) throw new RuntimeException('Cannot open Stripe job lock');
	if (!flock($handle, LOCK_EX | LOCK_NB)) { fclose($handle); return false; }
	return $handle;
}

/** @return bool Whether automatic collection was authorized for this issued invoice. */
function stripe_auto_charge_allowed($inv) {
	if (!$inv || !stripe_invoice_collectable($inv) || !$inv['enrolled']
		|| !stripe_feature_on('invoice_payments') || !stripe_feature_on('enrolment') || stripe_setting('enabled', 'off') !== 'on'
		|| trim((string)$inv['o']['udskriv_til']) !== 'Stripe') return false;
	$since = substr((string)($inv['debtor']['stripe_date'] ?? ''), 0, 10);
	if ($since === '' || substr((string)$inv['o']['fakturadate'], 0, 10) < $since) return false;
	foreach ($inv['payments'] as $row) {
		if (!empty($row['action_required_at'])) return false;
	}
	return true;
}

/**
 * Record a retryable technical failure separately from bank declines and alert after two calendar days.
 *
 * @return void
 */
function stripe_charge_error($ordreId, $error) {
	$id = (int)$ordreId;
	$row = db_fetch_array(stripe_payment_select("select * from stripe_charge_errors where ordre_id = $id"));
	if (!$row) {
		stripe_payment_modify("insert into stripe_charge_errors (ordre_id, first_date, last_error) values ($id, '" . db_escape_string(stripe_today()) . "', '" . db_escape_string(substr($error, 0, 1000)) . "')");
		$first = stripe_today();
	} else {
		$first = $row['first_date'];
		stripe_payment_modify("update stripe_charge_errors set last_error = '" . db_escape_string(substr($error, 0, 1000)) . "' where ordre_id = $id");
	}
	if (stripe_date_add($first, 2) <= stripe_today()) {
		stripeAlertMail(stripe_txt('alert_technical_subject', array('id' => $id)),
			stripe_txt('alert_technical_body', array('id' => $id, 'date' => $first, 'error' => $error)), 'stripe_technical_' . $id, 86400);
	}
}

/** @return string requires_action|ignored|delivery_error|database_error Durable authentication state and one customer mail. */
function stripe_payment_require_action($pi, $eventId = '') {
	$locked = false;
	try {
		$row = db_fetch_array(stripe_payment_select("select * from stripe_payments where payment_intent_id = '" . db_escape_string((string)$pi['id']) . "'"));
		if (!$row || in_array($row['status'], array('succeeded', 'duplicate_paid', 'refunded', 'disputed', 'unmapped', 'canceled'), true)) return 'ignored';
		stripe_invoice_collection_lock((int)$row['ordre_id']);
		$locked = true;
		$offSession = in_array($row['source'], array('auto', 'backup', 'manual'), true);
		stripe_payment_modify("update stripe_payments set status = 'requires_action', action_required_at = coalesce(action_required_at, CURRENT_TIMESTAMP),
			last_error = 'authentication_required', last_event_id = '" . db_escape_string($eventId) . "' where id = " . (int)$row['id']
			. " and status not in ('succeeded', 'duplicate_paid', 'refunded', 'disputed', 'unmapped', 'canceled')");
		$row = db_fetch_array(stripe_payment_select('select * from stripe_payments where id = ' . (int)$row['id']));
		if ($offSession && !$row['action_mail_sent_at'] && $row['status'] === 'requires_action') {
			$inv = stripe_invoice_load((int)$row['ordre_id']);
			$url = stripe_invoice_url($inv['id']);
			$body = stripe_mail_greeting($inv) . "\n\n" . stripe_txt('mail_body_action', array('nr' => $inv['nr']));
			$rows = array(array(stripe_txt('amount_lbl'), stripe_fmt_kr($inv['total_ore']) . ' kr.'), array(stripe_txt('due_date'), stripe_fmt_date($inv['due'])));
			$html = stripe_mail_layout($inv, stripe_txt('mail_label_invoice', array('nr' => $inv['nr'])), stripe_txt('mail_h_action'), $body, null, $rows, stripe_txt('action_btn'), $url, '', '', '');
			$text = stripe_mail_text_version(stripe_txt('mail_h_action'), $body, $rows, stripe_txt('action_btn'), $url, '');
			if (!$url || !stripe_mail_send($inv['email'], stripe_txt('mail_subj_action', array('nr' => $inv['nr'])), $html, $text)) return 'delivery_error';
			stripe_payment_modify('update stripe_payments set action_mail_sent_at = CURRENT_TIMESTAMP where id = ' . (int)$row['id']);
		}
		return 'requires_action';
	} catch (Throwable $e) {
		stripe_log('Authentication transition failed: ' . $e->getMessage());
		return 'database_error';
	} finally {
		if ($locked) stripe_invoice_collection_lock((int)$row['ordre_id'], true);
	}
}

/**
 * Retrieve and resume the invoice's existing authentication intent. Only called from the signed POST.
 * Off-session authentication_required can leave requires_payment_method; confirm that same intent
 * on-session with its original card, then let Stripe.js perform the bank's next action.
 *
 * @return array{ok: bool, pi?: string, status?: string, client_secret?: string, error?: string}
 */
function stripe_payment_resume($ordreId) {
	stripe_invoice_collection_lock((int)$ordreId);
	try {
		$inv = stripe_invoice_load($ordreId);
		if (!$inv || $inv['paid'] || $inv['paid_bank'] || !stripe_invoice_collectable($inv) || !$inv['fully_open']
			|| !stripe_feature_on('invoice_payments') || stripe_setting('enabled', 'off') !== 'on') return array('ok' => false, 'error' => 'not_payable');
		$row = $inv['pending'];
		if (!$row || $row['status'] !== 'requires_action') return array('ok' => false, 'error' => 'in_progress');
		$path = '/v1/payment_intents/' . rawurlencode($row['payment_intent_id']);
		$r = stripeHttpRequest('GET', $path);
		if (!$r['ok'] || ($r['body']['id'] ?? '') !== $row['payment_intent_id']) return array('ok' => false, 'error' => 'reconcile_failed');
		$pi = $r['body'];
		if ($pi['status'] !== 'succeeded' && ((int)($pi['amount'] ?? 0) !== (int)$inv['total_ore']
			|| strtolower((string)($pi['currency'] ?? '')) !== strtolower((string)$inv['currency']))) return array('ok' => false, 'error' => 'not_payable');
		if (in_array($pi['status'], array('requires_payment_method', 'requires_confirmation'), true)) {
			$method = $pi['last_payment_error']['payment_method'] ?? ($pi['payment_method'] ?? $row['pm_id']);
			$pm = is_array($method) ? ($method['id'] ?? '') : $method;
			if (!$pm) return array('ok' => false, 'error' => 'missing_payment_method');
			$r = stripeHttpRequest('POST', $path . '/confirm', array('payment_method' => $pm, 'off_session' => 'false', 'use_stripe_sdk' => 'true'));
			$pi = $r['ok'] ? $r['body'] : ($r['body']['error']['payment_intent'] ?? null);
			if (!$pi || ($pi['id'] ?? '') !== $row['payment_intent_id']) return array('ok' => false, 'error' => 'reconcile_failed');
		}
		$result = stripe_payment_apply_status($pi);
		if (in_array($result, array('database_error', 'delivery_error', 'error'), true)) return array('ok' => false, 'error' => $result);
		return array('ok' => true, 'pi' => $pi['id'], 'status' => $pi['status'], 'client_secret' => (string)($pi['client_secret'] ?? ''));
	} finally {
		stripe_invoice_collection_lock((int)$ordreId, true);
	}
}

/**
 * Complete durable failure work for BOTH sync and async primary declines. The invoice lock plus
 * persisted backup reservation prevents simultaneous webhook/job attempts or duplicate mails.
 *
 * @return string idle|handled|pending|error Follow-up outcome, never a capture-success indicator.
 */
function stripe_auto_failure_followup($ordreId) {
	$id = (int)$ordreId;
	if (!$id) return 'idle';
	stripe_invoice_collection_lock($id);
	try {
		$inv = stripe_invoice_load($id);
		if (!$inv) return 'idle';
		$failed = array_filter($inv['payments'], function ($p) {
			return in_array($p['source'], array('auto', 'backup'), true) && stripe_payment_attempt_failed($p) && empty($p['failure_handled_at']);
		});
		if (!$failed) return 'idle';
		if ($inv['paid'] || $inv['paid_bank']) {
			stripe_payment_modify("update stripe_payments set failure_handled_at = CURRENT_TIMESTAMP where ordre_id = $id and source in ('auto', 'backup') and status in ('failed', 'canceled')");
			return 'handled';
		}
		if ($inv['pending']) return 'pending';
		$primary = stripe_primary_declines($inv);
		if (!$primary) return 'idle';
		$payment = $primary[count($primary) - 1];
		$backups = array_values(array_filter($inv['payments'], function ($p) { return $p['source'] === 'backup'; }));
		$request = stripe_attempt_unresolved($id);
		$backupReserved = db_fetch_array(stripe_payment_select("select id from stripe_collection_attempts where ordre_id = $id and source = 'backup' limit 1"));
		if (stripe_auto_charge_allowed($inv) && (!$backups && (!$backupReserved || ($request && $request['source'] === 'backup')))) {
			$pm = '';
			if ($request && $request['source'] === 'backup') {
				$params = json_decode($request['params'], true);
				$pm = (string)$params['payment_method'];
			} else {
				$cus = (string)$inv['debtor']['stripe_customer_id'];
				$list = stripeHttpRequest('GET', '/v1/customers/' . rawurlencode($cus) . '/payment_methods', array('type' => 'card', 'limit' => 100));
				if (!$list['ok']) throw new RuntimeException('backup_lookup_failed');
				foreach (($list['body']['data'] ?? array()) as $method) {
					if (($method['metadata']['saldi_role'] ?? '') === 'backup' && $method['id'] !== $inv['debtor']['stripe_pm_id']) { $pm = (string)$method['id']; break; }
				}
			}
			if ($pm !== '') {
				$result = stripe_payment_create($inv, 'backup', false, $pm);
				if (!$result['ok'] && ($result['kind'] ?? 'technical') !== 'decline') throw new RuntimeException($result['error'] ?? 'backup_error');
				if ($result['ok'] && $result['status'] === 'succeeded') {
					$sync = stripe_payment_sync($result['pi']);
					if (in_array($sync, array('error', 'database_error', 'delivery_error'), true)) throw new RuntimeException('backup_sync_failed');
				}
				$inv = stripe_invoice_load($id);
				$backups = array_values(array_filter($inv['payments'], function ($p) { return $p['source'] === 'backup'; }));
			}
		}
		if ($inv['paid'] || $inv['paid_bank'] || ($inv['pending'] && $inv['pending']['status'] === 'requires_action')) {
			stripe_payment_modify("update stripe_payments set failure_handled_at = CURRENT_TIMESTAMP where ordre_id = $id and source in ('auto', 'backup') and status in ('failed', 'canceled')");
			return 'handled';
		}
		if ($inv['pending']) return 'pending';
		if (count($primary) >= 3 && !stripeAlertMail(stripe_txt('alert_declines_subject', array('nr' => $inv['nr'])),
			stripe_txt('alert_declines_body', array('nr' => $inv['nr'], 'kunde' => (string)($inv['debtor']['firmanavn'] ?? ''),
				'konto' => (string)($inv['debtor']['kontonr'] ?? ''), 'code' => (string)($primary[count($primary) - 1]['last_error'] ?? ''))),
			'auto3_' . $id, 86400)) throw new RuntimeException('decline_alert_failed');
		if ($backups && empty($backups[0]['failure_handled_at']) && stripe_payment_attempt_failed($backups[0])) $payment = $backups[0];
		if (!stripe_mail_failed($inv, $payment)) throw new RuntimeException('failure_mail_failed');
		stripe_payment_modify("update stripe_payments set failure_handled_at = CURRENT_TIMESTAMP where ordre_id = $id and source in ('auto', 'backup') and status in ('failed', 'canceled')");
		return 'handled';
	} catch (Throwable $e) {
		stripe_log('Automatic failure follow-up: ' . $e->getMessage());
		stripe_charge_error($id, $e->getMessage());
		return 'error';
	} finally {
		stripe_invoice_collection_lock($id, true);
	}
}

/** @return void Alert when authentication remains unpaid after the invoice's last retry date. */
function stripe_auto_action_alert($inv) {
	if ($inv['paid'] || $inv['paid_bank']) return;
	$base = stripe_setting('charge_on', 'due') === 'invoice_date' ? substr((string)$inv['o']['fakturadate'], 0, 10) : $inv['due'];
	foreach ($inv['payments'] as $p) {
		if (!empty($p['action_required_at']) && in_array($p['source'], array('auto', 'backup', 'manual'), true)) {
			$first = stripe_attempt_date($p);
			foreach ($inv['payments'] as $earlier) {
				if ($earlier['source'] === 'auto') $first = min($first, stripe_attempt_date($earlier));
			}
			$lastDate = stripe_date_add(max($base, $first), 7);
			if (stripe_today() > $lastDate && !stripeAlertMail(stripe_txt('alert_action_subject', array('nr' => $inv['nr'])),
				stripe_txt('alert_action_body', array('nr' => $inv['nr'], 'pi' => $p['payment_intent_id'], 'url' => stripe_invoice_url($inv['id']),
					'kunde' => (string)($inv['debtor']['firmanavn'] ?? ''), 'konto' => (string)($inv['debtor']['kontonr'] ?? ''))),
				'action_unpaid_' . $inv['id'], 86400)) throw new RuntimeException('Authentication alert failed');
			return;
		}
	}
}

/**
 * Deterministic job body shared with tests. Per-invoice failures never stop later invoices.
 * Reconciliation runs before every automatic charge, including old pending/failed intents.
 *
 * @return array{due: int, charged: int, succeeded: int, declined: int, requires_action: int, pending: int, skipped: int, errors: int}
 */
function stripe_charge_run($today, $dryRun = false) {
	$previous = $GLOBALS['stripe_charge_today'] ?? null;
	$GLOBALS['stripe_charge_today'] = $today;
	$stats = array('due' => 0, 'charged' => 0, 'succeeded' => 0, 'declined' => 0, 'requires_action' => 0, 'pending' => 0, 'skipped' => 0, 'errors' => 0);
	try {
		if (!stripe_feature_on('invoice_payments') || !stripe_feature_on('enrolment') || stripe_setting('enabled', 'off') !== 'on') return $stats;
		$q = stripe_payment_select("select o.id from ordrer o join adresser a on a.id = o.konto_id
			where o.art = 'DO' and o.udskriv_til = 'Stripe' and coalesce(o.betalt, '') <> 'on'
			and coalesce(o.fakturanr, '') not in ('', '0') and a.stripe = 'on' and coalesce(a.stripe_pm_id, '') <> ''
			and o.fakturadate >= a.stripe_date order by o.fakturadate, o.id");
		$ids = array();
		while ($row = db_fetch_array($q)) $ids[] = (int)$row['id'];
		foreach ($ids as $id) {
			$locked = false;
			try {
				stripe_invoice_collection_lock($id);
				$locked = true;
				$inv = stripe_invoice_load($id, $today);
				if (!$inv || !stripe_invoice_collectable($inv)) { $stats['skipped']++; continue; }
				if ($dryRun) {
					$next = stripe_auto_next_date($inv);
					if (stripe_auto_charge_allowed($inv) && $next !== null && $next !== '' && $next <= $today) $stats['due']++;
					else $stats['skipped']++;
					continue;
				}
				$error = stripe_payment_reconcile_invoice($id);
				if ($error === 'reconcile_failed') throw new RuntimeException($error);
				$inv = stripe_invoice_load($id, $today);
				stripe_auto_action_alert($inv);
				$followup = stripe_auto_failure_followup($id);
				if ($followup === 'error') throw new RuntimeException('failure_followup_failed');
				$inv = stripe_invoice_load($id, $today);
				if ($inv['paid']) {
					stripe_payment_modify("delete from stripe_charge_errors where ordre_id = $id");
					$stats['succeeded']++;
					continue;
				}
				if ($inv['pending']) {
					if (!stripe_attempt_unresolved($id)) stripe_payment_modify("delete from stripe_charge_errors where ordre_id = $id");
					$stats[$inv['pending']['status'] === 'requires_action' ? 'requires_action' : 'pending']++;
					continue;
				}
				$next = stripe_auto_next_date($inv);
				if (!stripe_auto_charge_allowed($inv) || !stripe_invoice_payable($inv) || $next === null || $next === '' || $next > $today) { $stats['skipped']++; continue; }
				$stats['due']++;
				$stats['charged']++;
				$result = stripe_payment_create($inv, 'auto', false, (string)$inv['debtor']['stripe_pm_id']);
				if (!$result['ok'] && ($result['kind'] ?? 'technical') !== 'decline') throw new RuntimeException($result['error'] ?? 'create_failed');
				if ($result['ok'] && $result['status'] === 'succeeded') {
					$sync = stripe_payment_sync($result['pi']);
					if (in_array($sync, array('database_error', 'delivery_error', 'error'), true)) throw new RuntimeException('sync_failed');
				}
				if (stripe_auto_failure_followup($id) === 'error') throw new RuntimeException('failure_followup_failed');
				$inv = stripe_invoice_load($id, $today);
				if ($inv['paid']) $stats['succeeded']++;
				elseif ($inv['pending']) $stats[$inv['pending']['status'] === 'requires_action' ? 'requires_action' : 'pending']++;
				elseif (($result['kind'] ?? '') === 'decline') $stats['declined']++;
				else $stats['pending']++;
				stripe_payment_modify("delete from stripe_charge_errors where ordre_id = $id");
			} catch (Throwable $e) {
				$stats['errors']++;
				stripe_log('Charge invoice ' . $id . ': ' . $e->getMessage());
				stripe_charge_error($id, $e->getMessage());
			} finally {
				if ($locked) stripe_invoice_collection_lock($id, true);
			}
		}
		return $stats;
	} finally {
		if ($previous === null) unset($GLOBALS['stripe_charge_today']);
		else $GLOBALS['stripe_charge_today'] = $previous;
	}
}
