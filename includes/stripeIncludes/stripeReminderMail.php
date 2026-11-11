<?php
// 20261109 CL/ASR Created: link reminders (Linkrykker), the reminder as a mail in the invoice-mail layout (stripe_mail_layout()).
//                 Every invoice on the reminder gets its own "Betal" link to the existing payment page (api/stripe/faktura.php).
//                 No new payment code: a card payment pays exactly one invoice through the existing PaymentIntent paths.
//                 The reminder PDF stays attached.
//                 Invoices that are paid, credited, settled or in flight are left out at send time.
//                 Sent by formSendReminderEntry() (includes/formFuncIncludes/reminderMailDelivery.php) from rykkerprint().
// 20261109 CL/ASR Review fixes: the fee of an earlier classic level of the chain is a row "Rykkergebyr fra 1. rykker" paid by bank transfer, counted in "At betale nu", with the bank details (D1).
//                 Interest is named as interest in the offer; "Betal med dit gemte kort" only when the page offers the saved card; a row that cannot be paid by card links "Se faktura ›".

include_once(__DIR__ . '/stripeInvoice.php');
include_once(__DIR__ . '/stripeDunning.php');
include_once(__DIR__ . '/stripeManualFee.php');
if (is_file(__DIR__ . '/stripeReminder.php')) include_once(__DIR__ . '/stripeReminder.php');

/**
 * The invoices a link reminder can still ask the customer to pay, loaded as the payment page sees them.
 * Left out: paid by card or by bank, settled open item (udlignet), protected by a payment in flight or a card-on-file
 * hold (stripe_dunning_openpost_sql()), or credited.
 *
 * @param int $rykkerId ordrer.id of the R1/R2/R3 order.
 * @return array{
 *   invoices: array<int, array>,  Oldest due date first. Each entry is the invoice as stripe_invoice_load() returns it
 *     (id, nr, state, ...) plus the reminder's line: ordre_id: int, openpost_id: int, fakturanr: string,
 *     amount: float (kr., sum of the reminder's open items for that invoice), forfaldsdate: string (Y-m-d).
 *   credits: float,  Sum of the reminder's open credit lines (negative open items), 0.0 when none.
 *   prior_fees: array<int, array{openpost_id: int, faktnr: string, amount: float, level: int}>,  Fees of earlier
 *     levels of the chain posted as open items (stripe_reminder_lines()) that are not settled; paid by bank transfer.
 *   skipped: array<int, string>,  ordre_id => paid|paid_bank|settled|in_flight|credited|missing.
 * }
 */
function stripe_reminder_invoices(int $rykkerId): array {
	$out = array('invoices' => array(), 'credits' => 0.0, 'prior_fees' => array(), 'skipped' => array());
	if (!function_exists('stripe_reminder_lines')) return $out;
	$lines = stripe_reminder_lines($rykkerId);
	$protected = stripe_dunning_openpost_sql('openpost');
	$kept = array();
	foreach ($lines['invoices'] as $line) {
		$ordreId = (int)$line['ordre_id'];
		$opId = (int)$line['openpost_id'];
		$inv = stripe_invoice_load($ordreId);
		$reason = '';
		if (!$inv || !empty($inv['is_credit'])) {
			$reason = 'missing';
		} elseif ($inv['paid']) {
			$reason = 'paid';
		} elseif ($inv['paid_bank']) {
			$reason = 'paid_bank';
		} elseif (!db_fetch_array(stripe_payment_select("select id from openpost where id = $opId and coalesce(udlignet, '0') <> '1'"))) {
			$reason = 'settled';
		} elseif (!db_fetch_array(stripe_payment_select("select id from openpost where id = $opId and $protected"))) {
			$reason = 'in_flight';
		} elseif (db_fetch_array(stripe_payment_select("select id from ordrer where art = 'DK' and kred_ord_id = $ordreId limit 1"))) {
			$reason = 'credited';
		}
		if ($reason !== '') {
			$out['skipped'][$ordreId] = $reason;
			unset($kept[$ordreId]);
			continue;
		}
		if (isset($out['skipped'][$ordreId])) continue;
		if (isset($kept[$ordreId])) {
			// Two open items of the same invoice (split due dates): one row, both amounts.
			$kept[$ordreId]['amount'] += (float)$line['amount'];
			continue;
		}
		$kept[$ordreId] = $inv + array('ordre_id' => $ordreId, 'openpost_id' => $opId, 'fakturanr' => (string)$line['fakturanr'],
			'amount' => (float)$line['amount'], 'forfaldsdate' => (string)($inv['due'] ?: substr((string)$line['forfaldsdate'], 0, 10)));
	}
	foreach ($lines['credits'] as $credit) {
		if (db_fetch_array(stripe_payment_select("select id from openpost where id = " . (int)$credit['openpost_id'] . " and coalesce(udlignet, '0') <> '1'"))) {
			$out['credits'] += (float)$credit['amount'];
		}
	}
	foreach ($lines['prior_fees'] ?? array() as $fee) {
		if (db_fetch_array(stripe_payment_select("select id from openpost where id = " . (int)$fee['openpost_id'] . " and coalesce(udlignet, '0') <> '1'"))) {
			$out['prior_fees'][] = $fee;
		}
	}
	$kept = array_values($kept);
	usort($kept, function ($a, $b) {
		return array($a['forfaldsdate'], $a['fakturanr']) <=> array($b['forfaldsdate'], $b['fakturanr']);
	});
	$out['invoices'] = $kept;
	return $out;
}

/**
 * How the customer can pay one invoice, from the payment page's state for it.
 *
 * @param array $inv Loaded invoice (stripe_invoice_load()).
 * @return string auto (card, offer automatic payment) | pay (card only) | saved (enrolled: saved card, as the page's
 *                #paynow in the overdue and enrolled states) | failed (saved card declined: new card) | action (bank
 *                must approve) | bank (no card possible) | view.
 */
function stripe_reminder_variant(array $inv): string {
	$state = (string)$inv['state'];
	if ($state === 'requires_action') return 'action';
	if ($state === 'failed' && in_array((string)($inv['failed']['source'] ?? ''), array('auto', 'backup', 'manual'), true)) return 'failed';
	if (in_array($state, array('manual', 'nocard'), true)) return 'bank';
	// Only where the page shows the saved card (#paynow); a declined card form payment ('failed', source page) shows the card form.
	if (in_array($state, array('overdue', 'enrolled'), true) && stripe_invoice_pay_now_allowed($inv)) return 'saved';
	if (in_array($state, array('open', 'open_recurring', 'overdue', 'failed'), true)) {
		return (empty($inv['enrolled']) && stripe_invoice_save_allowed($inv)) ? 'auto' : 'pay';
	}
	return 'view';
}

/**
 * "Betal senest": the reminder's date plus its payment days, or 8 days when it has none.
 *
 * @param array $r ordrer row of the reminder (fakturadate, ordredate, betalingsdage).
 * @return string YYYY-MM-DD.
 */
function stripe_reminder_deadline(array $r): string {
	$date = substr((string)(($r['fakturadate'] ?? '') ?: ($r['ordredate'] ?? '')), 0, 10);
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = stripe_today();
	$days = (int)($r['betalingsdage'] ?? 0);
	return stripe_date_add($date, $days > 0 ? $days : 8);
}

/**
 * The reminder's rows as an HTML table, so each invoice row can carry its own "Betal ›" link ("Se faktura ›" when it
 * cannot be paid by card: link_label).
 *
 * @param array<int, array{label: string, value: string, link?: string, link_label?: string, note?: string, strong?: bool}> $rows
 * @return string HTML for stripe_mail_layout()'s $listHtml.
 */
function stripe_reminder_list_html(array $rows): string {
	$td = 'padding:14px 0;border-bottom:1px solid #e6e2da;';
	$h = '<table role="presentation" style="width:100%;border-collapse:collapse;margin:28px 0;border-top:1px solid #e6e2da">';
	foreach ($rows as $row) {
		$strong = !empty($row['strong']);
		$h .= '<tr><td style="' . $td . ($strong ? 'color:#111318;font-weight:700' : 'color:#4b5563') . '">' . stripe_h($row['label']) . '</td>'
			. '<td style="' . $td . 'padding-left:12px;text-align:right;white-space:nowrap;font-weight:' . ($strong ? '700' : '600') . '">' . stripe_h($row['value']) . '</td>'
			. '<td style="' . $td . 'padding-left:16px;text-align:right;white-space:nowrap;font-size:14px">';
		if (!empty($row['link'])) {
			$h .= '<a href="' . stripe_h($row['link']) . '" style="color:#1b68bd;font-weight:700;text-decoration:none">' . stripe_h($row['link_label'] ?? stripe_txt('mail_rem_pay_link')) . '</a>';
		} elseif (!empty($row['note'])) {
			$h .= '<span style="color:#6b7280">' . stripe_h($row['note']) . '</span>';
		}
		$h .= '</td></tr>';
	}
	return $h . '</table>';
}

/**
 * Build and send the link reminder mail (SPEC_link_reminders §2.4) with the reminder PDF attached as
 * "Rykker <nr>.pdf". The primary button opens the oldest invoice; its text follows that invoice's page state.
 *
 * @param int $rykkerId ordrer.id of the R1/R2/R3 order.
 * @param string $pdf Rendered reminder PDF (must exist).
 * @param string $email The reminder's recipient list (ordrer.email; ';' or ',' separated), as classic mail uses it.
 * @return bool Whether the mail was sent. false (nothing sent) when the link mail cannot be made: no invoice left,
 *              payment page off, missing link, missing PDF or recipient, or a mail/database error.
 */
function stripe_mail_reminder(int $rykkerId, string $pdf, string $email): bool {
	try {
		if (!function_exists('stripe_reminder_lines') || !function_exists('stripe_reminder_settings')) return false;
		if (trim($email) === '' || !is_file($pdf) || !stripe_invoice_link_available()) return false;
		$r = db_fetch_array(stripe_payment_select("select id, art, fakturanr, ordredate, fakturadate, betalingsdage from ordrer where id = " . $rykkerId));
		if (!$r || !in_array((string)$r['art'], array('R1', 'R2', 'R3'), true)) return false;
		$set = stripe_reminder_invoices($rykkerId);
		if (!$set['invoices']) return false;
		$urls = array();
		foreach ($set['invoices'] as $i => $item) {
			$urls[$i] = stripe_invoice_url($item['ordre_id']);
			if ($urls[$i] === '') return false;
		}
		$lines = stripe_reminder_lines($rykkerId);
		$row = function_exists('stripe_reminder_row') ? stripe_reminder_row($rykkerId) : null;
		$settings = stripe_reminder_settings();
		$level = $row ? (int)$row['level'] : (int)substr((string)$r['art'], 1);
		$feeNext = $row && (string)$row['fee_mode'] === 'next';
		$status = $row ? (string)$row['status'] : 'draft';
		$waived = $status === 'waived';
		$feeOre = $waived ? 0 : (int)round((float)$lines['fee'] * 100);
		$interestOre = $waived ? 0 : (int)round((float)$lines['interest'] * 100);
		$carried = $feeNext && in_array($status, array('draft', 'sent'), true);

		$first = $set['invoices'][0];
		$inv = $first;
		$count = count($set['invoices']);
		$multi = $count > 1;
		$variant = stripe_reminder_variant($inv);
		$vars = array('nr' => $inv['nr'], 'brand' => $inv['brand'], 'count' => $count, 'level' => $level);
		$subject = stripe_txt(($level > 1 ? 'mail_subj_rem_again' : 'mail_subj_rem') . ($multi ? '_multi' : ''), $vars);
		$label = stripe_txt($multi ? 'mail_label_rem_multi' : 'mail_label_rem', $vars);
		$h1 = stripe_txt($level > 1 ? 'mail_h_rem_again' : 'mail_h_rem');

		// Body: greeting, what is missing, and (when it can be turned on) what automatic payment does.
		$dueVars = array('nr' => $inv['nr'], 'date' => stripe_fmt_date($first['forfaldsdate']));
		if ($variant === 'failed') {
			$para = stripe_txt('mail_body_rem_failed', array('nr' => $inv['nr']));
		} elseif ($variant === 'bank') {
			$para = stripe_txt($multi ? 'mail_body_rem_bank_multi' : 'mail_body_rem_bank', $dueVars);
		} else {
			$para = stripe_txt($multi ? 'mail_body_rem_multi' : 'mail_body_rem', $dueVars);
		}
		$body = stripe_mail_greeting($inv) . "\n\n" . $para;
		if ($variant === 'auto') {
			$body .= "\n\n" . stripe_txt(stripe_setting('charge_on', 'due') === 'invoice_date' ? 'mail_body_rem_auto_invoice_date' : 'mail_body_rem_auto');
		}

		// Boxes: the close warning on a 3rd reminder (red) and the offer to save the carried fee (green).
		$deadline = stripe_reminder_deadline($r);
		$close = null;
		if ($level >= 3 && !empty($settings['close_warning']) && trim((string)$settings['close_text']) !== '') {
			$close = array('kind' => 'bad', 'title' => str_replace('{frist}', stripe_fmt_date($deadline), trim((string)$settings['close_text'])), 'sub' => '');
		}
		$offer = null;
		if ($variant === 'auto' && $carried && !empty($settings['waive']) && $feeOre + $interestOre > 0) {
			$words = stripe_reminder_fee_words($feeOre, $interestOre);   // interest is not called "rykkergebyret"
			$offer = array('kind' => 'offer', 'title' => stripe_txt('mail_rem_offer_title', $words), 'sub' => stripe_txt('mail_rem_offer_sub', $words));
		}

		// Rows: each invoice with its own link, credit, fee and interest, the amount to pay now and the deadline.
		$rows = array();
		$totalOre = 0;
		foreach ($set['invoices'] as $i => $item) {
			$ore = (int)round($item['amount'] * 100);
			$totalOre += $ore;
			$cardless = in_array(stripe_reminder_variant($item), array('bank', 'view'), true);   // its page offers no card payment
			$rows[] = array('label' => stripe_txt('mail_rem_row_invoice', array('nr' => $item['nr'], 'date' => stripe_fmt_date($item['forfaldsdate']))),
				'value' => stripe_fmt_kr($ore) . ' kr.', 'link' => $urls[$i],
				'link_label' => stripe_txt($cardless ? 'mail_rem_view_link' : 'mail_rem_pay_link'), 'link_text' => stripe_txt($cardless ? 'mail_rem_view_text' : 'mail_rem_pay_text'));
		}
		// The fee of an earlier level that was posted as an open item (fee_mode classic): owed now, by bank transfer (D1).
		$priorNumbers = array();
		foreach ($set['prior_fees'] as $prior) {
			$ore = (int)round($prior['amount'] * 100);
			$totalOre += $ore;
			$priorNumbers[] = $prior['faktnr'];
			$rows[] = array('label' => stripe_txt('mail_rem_row_prior_fee', array('level' => (int)$prior['level'])), 'value' => stripe_fmt_kr($ore) . ' kr.',
				'note' => stripe_txt('mail_rem_prior_fee_note'));
		}
		$creditOre = (int)round($set['credits'] * 100);
		if ($creditOre < 0) {
			$totalOre += $creditOre;
			$rows[] = array('label' => stripe_txt('mail_rem_row_credit'), 'value' => stripe_fmt_kr($creditOre) . ' kr.');
		}
		$feeNote = stripe_txt($feeNext ? 'mail_rem_fee_next' : 'mail_rem_fee_classic');
		if ($feeOre > 0) $rows[] = array('label' => stripe_txt('mail_rem_row_fee'), 'value' => stripe_fmt_kr($feeOre) . ' kr.', 'note' => $feeNote);
		if ($interestOre > 0) $rows[] = array('label' => stripe_txt('mail_rem_row_interest'), 'value' => stripe_fmt_kr($interestOre) . ' kr.', 'note' => $feeNote);
		if (!$feeNext) $totalOre += $feeOre + $interestOre;
		$rows[] = array('label' => stripe_txt('mail_rem_row_total'), 'value' => stripe_fmt_kr(max(0, $totalOre)) . ' kr.', 'strong' => true);
		$rows[] = array('label' => stripe_txt('mail_rem_row_deadline'), 'value' => stripe_fmt_date($deadline));

		// Bank data as on the payment page and in the invoice: the invoicing company's reg./konto.
		$reg = trim((string)($inv['firma']['bank_reg'] ?? ''));
		$konto = trim((string)($inv['firma']['bank_konto'] ?? ''));
		$hasBank = $reg !== '' && $konto !== '';
		$bankLines = array();
		if ($variant === 'bank' && $hasBank) {
			$bankLines[] = stripe_txt($multi ? 'mail_rem_bank_box_multi' : 'mail_rem_bank_box', array('reg' => $reg, 'konto' => $konto, 'nr' => $inv['nr']));
		}
		if ($priorNumbers && $hasBank) {
			$bankLines[] = stripe_txt('mail_rem_bank_box_fee', array('reg' => $reg, 'konto' => $konto, 'nr' => implode(', ', $priorNumbers)));
		}

		$buttons = array('auto' => 'mail_btn_rem_auto', 'pay' => 'mail_btn_new', 'saved' => 'mail_btn_rem_saved', 'failed' => 'mail_btn_rem_failed',
			'action' => 'mail_btn_rem_action', 'bank' => 'mail_btn_rem_view', 'view' => 'mail_btn_rem_view');
		$btnText = stripe_txt($buttons[$variant]);
		$btnUrl = $urls[0];

		// Note: pay-once choice, the fee for manual payment (as on the payment page), bank, PDF, contact.
		$notes = array();
		if ($variant === 'auto') $notes[] = stripe_txt('mail_rem_note_once');
		if (empty($inv['enrolled']) && in_array($variant, array('auto', 'pay'), true)) {
			$manualFeeOre = stripe_manual_fee_ore((float)$inv['o']['momssats'], (string)$inv['o']['fakturadate']);
			if ($manualFeeOre > 0) $notes[] = stripe_txt('fee_note', array('fee' => stripe_fmt_kr($manualFeeOre) . ' kr.'));
		}
		if ($hasBank && !$bankLines) $notes[] = stripe_txt('mail_rem_note_bank', array('reg' => $reg, 'konto' => $konto));
		$notes[] = stripe_txt('mail_rem_note_end');
		$contactMail = stripe_contact_email($inv['firma']);
		$notes[] = $contactMail !== '' ? stripe_txt('mail_rem_note_contact', array('email' => $contactMail)) : stripe_txt('mail_rem_note_contact_plain');
		if ($multi && in_array($variant, array('auto', 'saved', 'failed'), true)) $notes[] = stripe_txt('mail_rem_note_multi');
		$noteText = implode(' ', $notes);

		$box = $close ?: $offer;
		$listHtml = ($close && $offer) ? stripe_mail_box_html($offer) : '';
		$listHtml .= stripe_reminder_list_html($rows);
		if ($bankLines) {
			$listHtml .= '<div style="margin:0 0 22px;padding:16px 18px;border-radius:12px;background:#f3eee6"><div style="font-weight:600;color:#111318">'
				. stripe_h(stripe_txt('bank_box_title')) . '</div>';
			foreach ($bankLines as $bankLine) $listHtml .= '<div style="font-size:15px;color:#4b5563">' . stripe_h($bankLine) . '</div>';
			$listHtml .= '</div>';
		}
		$html = stripe_mail_layout($inv, $label, $h1, $body, $box, array(), $btnText, $btnUrl, '', '', stripe_h($noteText), $listHtml);

		// Plain-text alternative with the same content and every link written out.
		$textBody = $body;
		foreach (array_filter(array($close, $offer)) as $b) {
			$textBody .= "\n\n" . $b['title'] . ($b['sub'] !== '' ? "\n" . $b['sub'] : '');
		}
		$textRows = array();
		foreach ($rows as $row) {
			$value = $row['value'];
			if (!empty($row['link'])) $value .= ' – ' . ($row['link_text'] ?? stripe_txt('mail_rem_pay_text')) . ': ' . $row['link'];
			elseif (!empty($row['note'])) $value .= ' (' . $row['note'] . ')';
			$textRows[] = array($row['label'], $value);
		}
		foreach ($bankLines as $bankLine) $textRows[] = array(stripe_txt('bank_box_title'), $bankLine);
		$text = stripe_mail_text_version($h1, $textBody, $textRows, $btnText, $btnUrl, $noteText);

		$ok = stripe_mail_send($email, $subject, $html, $text, array(array('path' => $pdf, 'name' => 'Rykker ' . trim((string)$r['fakturanr']) . '.pdf')));
		if ($ok) {
			$details = json_encode(array('to' => $email, 'nr' => (string)$r['fakturanr'], 'variant' => $variant,
				'invoices' => array_map(function ($item) { return $item['nr']; }, $set['invoices']),
				'skipped' => $set['skipped']));
			if (!function_exists('audit_log_write') && is_file(__DIR__ . '/../auditLog.php')) include_once(__DIR__ . '/../auditLog.php');
			if (function_exists('audit_log_write') && function_exists('tbl_exists')) audit_log_write('stripe.reminder_mailed', 'ordre', (string)$rykkerId, (string)$details, 'stripe');
			else stripe_log('reminder mailed ' . $rykkerId . ': ' . $details);
		}
		return $ok;
	} catch (Throwable $e) {
		stripe_log('reminder mail ' . $rykkerId . ': ' . $e->getMessage());
		return false;
	}
}
