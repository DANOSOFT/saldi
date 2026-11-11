<?php
// 20261109 CL/ASR Created: delivery of one rendered reminder from rykkerprint(), mirroring formSendMailEntry() (invoiceMailDelivery.php).
//                 Every reminder first passes the legal check (renteloven § 9 b, reminderLegal.php).
//                 A link reminder (a draft/sent row in stripe_reminders) is mailed with payment links (stripeReminderMail.php), else the classic PDF mail.
//                 A classic reminder goes to the classic sender exactly as before.
//                 Installations without the reminder files or tables keep the classic behaviour.
// 20261109 CL/ASR Review fixes: the reminder PDF treats a fee that goes on the next invoice by the link reminder row in any status ("opkræves på din næste faktura" / "opkrævet på faktura X" / "frafaldet", never in "I alt"), and leaves out the invoices the link mail leaves out (D3; formReminderPdfTreatment()).
//                 A draft link reminder printed on paper counts as sent (formReminderPrinted()).
//                 Staff messages are texts 8135-8139 (tekster.csv) and the alert is built with jsString(), so a legacy (non-UTF8) database shows them right.
// 20261111 CL/ASR Review round 2: a link reminder whose fee was waived before it was ever sent ("Frafald", or its fee line deleted) still goes as a link mail and
//                 gets its mailed_at; a link reminder with no invoice left to pay is refused when printed too (not only when mailed), so no fee is carried for it.

/** @return void Loads the reminder helpers this installation has: the legal rules and the link reminder register. */
function formReminderIncludes(): void {
	foreach (array('/../reminderLegal.php', '/../stripeIncludes/stripeReminder.php') as $file) {
		if (is_file(__DIR__ . $file)) include_once(__DIR__ . $file);
	}
}

/** @return void Log a reminder delivery problem where the Stripe code logs, else in the PHP error log. */
function formReminderLog(string $line): void {
	if (function_exists('stripe_log')) stripe_log($line);
	else error_log('Saldi reminder: ' . $line);
}

/**
 * A staff text of the send path (reminder_txt(): tekster.csv, Danish fallback), also without reminderLegal.php.
 *
 * @param int $textId Id in importfiler/tekster.csv.
 * @param string $danish The Danish text.
 * @param array<string, string> $vars {name} placeholders.
 * @return string
 */
function formReminderText(int $textId, string $danish, array $vars = array()): string {
	formReminderIncludes();
	if (function_exists('reminder_txt')) return reminder_txt($textId, $danish, $vars);
	foreach ($vars as $name => $value) $danish = str_replace('{' . $name . '}', (string)$value, $danish);
	return $danish;
}

/** @return string The reminder's number for staff messages (ordrer.fakturanr, e.g. "17-1"), or its id when it has none. */
function formReminderNumber(int $rykkerId): string {
	$r = db_fetch_array(db_select("select fakturanr from ordrer where id = " . $rykkerId, __FILE__ . " linje " . __LINE__));
	$nr = trim((string)($r['fakturanr'] ?? ''));
	return $nr !== '' ? $nr : (string)$rykkerId;
}

/**
 * The checks before a reminder is printed or mailed: the legal check (fee cap, fees on at most 3 reminders, 10 days
 * between reminders), and for a link reminder that at least one invoice on it is still to be paid (printed or mailed
 * alike: a printed one counts as sent and its fee would go on the next invoice).
 *
 * @param int $rykkerId ordrer.id of the R1/R2/R3 order.
 * @return string '' when it may be sent, else "Rykker 17-2 er ikke sendt: <reason from reminder_legal_check()>" or
 *                "Rykker 17-2 er ikke sendt: alle fakturaer på rykkeren er betalt eller under betaling."
 */
function formReminderRefusal(int $rykkerId): string {
	formReminderIncludes();
	$reason = function_exists('reminder_legal_check') ? trim((string)reminder_legal_check($rykkerId)) : '';
	if ($reason !== '') {
		return formReminderText(8135, 'Rykker {rykker} er ikke sendt: {tekst}', array('rykker' => formReminderNumber($rykkerId), 'tekst' => $reason));
	}
	if (formReminderLinkRow($rykkerId) !== null) {
		try {
			include_once(__DIR__ . '/../stripeIncludes/stripeReminderMail.php');
			if (!stripe_reminder_invoices($rykkerId)['invoices']) {
				return formReminderText(8136, 'Rykker {rykker} er ikke sendt: alle fakturaer på rykkeren er betalt eller under betaling.', array('rykker' => formReminderNumber($rykkerId)));
			}
		} catch (Throwable $e) {
			formReminderLog('reminder invoices ' . $rykkerId . ': ' . $e->getMessage());
		}
	}
	return '';
}

/**
 * The stripe_reminders row of a reminder that is (still) sent as a link reminder: status draft or sent, or waived
 * before it was ever sent (staff pressed "Frafald" on a booked draft or deleted its fee lines): it still goes as a
 * link mail, without the fee. A charged reminder, one waived after it was sent, or one without a row, is a classic
 * reminder here.
 *
 * @param int $rykkerId ordrer.id of the R1/R2/R3 order.
 * @return array<string, mixed>|null The row, or null.
 */
function formReminderLinkRow(int $rykkerId): ?array {
	formReminderIncludes();
	if (!function_exists('stripe_reminder_row')) return null;
	try {
		$row = stripe_reminder_row($rykkerId);
	} catch (Throwable $e) {
		formReminderLog('reminder row ' . $rykkerId . ': ' . $e->getMessage());
		return null;
	}
	return (is_array($row) && formReminderSendsAsLink($row)) ? $row : null;
}

/**
 * @param array<string, mixed> $row stripe_reminder_row().
 * @return bool Whether the row is sent as a link reminder: draft or sent, or waived and never sent.
 */
function formReminderSendsAsLink(array $row): bool {
	$status = (string)$row['status'];
	return in_array($status, array('draft', 'sent'), true) || ($status === 'waived' && trim((string)($row['mailed_at'] ?? '')) === '');
}

/**
 * How the reminder PDF (rykkerprint()) shows a link reminder. Fee and interest that go on the next invoice
 * (fee_mode next, any status: bogfor_rykker() never posted them) stay out of "I alt" and say where they are:
 * draft/sent " – opkræves på din næste faktura", charged " – opkrævet på faktura <number>", waived " – frafaldet".
 * While it is sent as a link reminder (draft/sent) the open items the link mail leaves out (paid, settled, in flight,
 * credited invoices; settled credits and earlier fees) are left out of the PDF too, so PDF and mail agree (D3).
 * A classic reminder (or fee_mode classic) is printed as before.
 *
 * @param int $rykkerId ordrer.id of the R1/R2/R3 order.
 * @return array{
 *   carried: bool,           Fee and interest lines are not part of "I alt".
 *   suffix: string,          Added to their text.
 *   skip: array<int, int>,   openpost ids whose lines are left out.
 * }
 */
function formReminderPdfTreatment(int $rykkerId): array {
	$out = array('carried' => false, 'suffix' => '', 'skip' => array());
	formReminderIncludes();
	if (!function_exists('stripe_reminder_row')) return $out;
	try {
		$row = stripe_reminder_row($rykkerId);
		if (!$row) return $out;
		if ($row['fee_mode'] === 'next') {
			$out['carried'] = true;
			if ($row['status'] === 'waived') {
				$out['suffix'] = ' – frafaldet';
			} elseif ($row['status'] === 'charged') {
				$o = $row['fee_ordre_id'] > 0 ? db_fetch_array(db_select("select fakturanr from ordrer where id = " . (int)$row['fee_ordre_id'], __FILE__ . " linje " . __LINE__)) : null;
				$nr = trim((string)($o['fakturanr'] ?? ''));
				$out['suffix'] = $nr !== '' ? ' – opkrævet på faktura ' . $nr : ' – opkrævet på en senere faktura';
			} else {
				$out['suffix'] = ' – opkræves på din næste faktura';
			}
		}
		if (formReminderSendsAsLink($row) && is_file(__DIR__ . '/../stripeIncludes/stripeReminderMail.php')) {
			include_once(__DIR__ . '/../stripeIncludes/stripeReminderMail.php');
			$lines = stripe_reminder_lines($rykkerId);
			$set = stripe_reminder_invoices($rykkerId);
			foreach ($lines['invoices'] as $invoice) {
				if (isset($set['skipped'][(int)$invoice['ordre_id']])) $out['skip'][] = (int)$invoice['openpost_id'];
			}
			$kept = array_map(function ($fee) { return (int)$fee['openpost_id']; }, $set['prior_fees']);
			foreach ($lines['prior_fees'] as $fee) {
				if (!in_array((int)$fee['openpost_id'], $kept, true)) $out['skip'][] = (int)$fee['openpost_id'];
			}
			foreach ($lines['credits'] as $credit) {
				$op = db_fetch_array(db_select("select udlignet from openpost where id = " . (int)$credit['openpost_id'], __FILE__ . " linje " . __LINE__));
				if ($op && trim((string)$op['udlignet']) === '1') $out['skip'][] = (int)$credit['openpost_id'];
			}
		}
	} catch (Throwable $e) {
		formReminderLog('reminder pdf ' . $rykkerId . ': ' . $e->getMessage());
	}
	return $out;
}

/**
 * A reminder printed on paper (no mail): a link reminder that was never sent is sent now, so its fee goes on the next
 * invoice as the PDF says (stripe_reminder_fee_bogfor() takes sent rows; a waived one keeps its status and gets its
 * mailed_at). One that was sent before is left as it is. rykkerprint() refuses it first when no invoice is left to pay
 * (formReminderRefusal()).
 *
 * @param int $rykkerId ordrer.id of the R1/R2/R3 order.
 * @return void
 */
function formReminderPrinted(int $rykkerId): void {
	$row = formReminderLinkRow($rykkerId);
	if ($row !== null && trim((string)($row['mailed_at'] ?? '')) === '') formReminderMarkSent($rykkerId, 'classic', '');
}

/** @return bool Whether the classic sender (send_mails) reports a sent mail. */
function formReminderClassicSent($result): bool {
	return is_string($result) && ($result === 'OK' || strpos($result, 'Mail sent to ') === 0);
}

/** @return void Record how a link reminder went out (stripe_reminder_mark_sent); a failure is logged, the mail is already sent. */
function formReminderMarkSent(int $rykkerId, string $channel, string $email): void {
	if (!function_exists('stripe_reminder_mark_sent')) return;
	try {
		stripe_reminder_mark_sent($rykkerId, $channel, $email);
	} catch (Throwable $e) {
		formReminderLog('reminder mark sent ' . $rykkerId . ': ' . $e->getMessage());
	}
}

/**
 * Deliver one rendered reminder. Classic reminders keep the classic sender's arguments and result.
 *
 * @param int $rykkerId ordrer.id of the R1/R2/R3 order.
 * @param string $pdf PDF rendered for this reminder.
 * @param string $email The reminder's recipient list (ordrer.email).
 * @param string $language Form language, passed on to the classic sender.
 * @param int|string $form Form number (6, 7 or 8), passed on to the classic sender.
 * @param callable $classicSender Classic mail transport, called as rykkerprint() always did:
 *   ($rykkerId as string, $pdf, $email, $language, $form, '', '', '', 0). Injectable for tests.
 * @return array{
 *   result: mixed,    The classic sender's result; 'OK' for a link mail; false when refused.
 *   message: string,  Danish staff message for the one alert after the batch; '' for a classic reminder (its sender reports itself).
 *   sent: bool,       Whether a mail went out.
 *   channel: string,  link | classic | '' when nothing was sent.
 * }
 */
function formSendReminderEntry(int $rykkerId, string $pdf, string $email, string $language, $form, $classicSender = 'send_mails'): array {
	$refusal = formReminderRefusal($rykkerId);
	if ($refusal !== '') {
		return array('result' => false, 'message' => $refusal, 'sent' => false, 'channel' => '');
	}
	$row = formReminderLinkRow($rykkerId);
	if ($row === null) {
		$result = $classicSender((string)$rykkerId, $pdf, $email, $language, $form, '', '', '', 0);
		return array('result' => $result, 'message' => '', 'sent' => formReminderClassicSent($result), 'channel' => 'classic');
	}
	$nr = formReminderNumber($rykkerId);
	try {
		include_once(__DIR__ . '/../stripeIncludes/stripeReminderMail.php');
		$set = stripe_reminder_invoices($rykkerId);
		if (!$set['invoices']) {
			return array('result' => false, 'message' => formReminderText(8136, 'Rykker {rykker} er ikke sendt: alle fakturaer på rykkeren er betalt eller under betaling.', array('rykker' => $nr)),
				'sent' => false, 'channel' => '');
		}
		if (stripe_mail_reminder($rykkerId, $pdf, $email)) {
			formReminderMarkSent($rykkerId, 'link', $email);
			return array('result' => 'OK', 'message' => formReminderText(8137, 'Rykker {rykker} er sendt med betalingslink til {email}.', array('rykker' => $nr, 'email' => $email)),
				'sent' => true, 'channel' => 'link');
		}
	} catch (Throwable $e) {
		formReminderLog('reminder link mail ' . $rykkerId . ': ' . $e->getMessage());
	}
	// The link mail could not be sent: the classic PDF mail instead, so the customer still gets the reminder.
	try {
		$result = $classicSender((string)$rykkerId, $pdf, $email, $language, $form, '', '', '', 0);
		$sent = formReminderClassicSent($result);
	} catch (Throwable $e) {
		formReminderLog('classic reminder fallback ' . $rykkerId . ': ' . $e->getMessage());
		$result = false;
		$sent = false;
	}
	if ($sent) {
		formReminderMarkSent($rykkerId, 'classic', $email);
		return array('result' => $result, 'message' => formReminderText(8138, 'Rykker {rykker} er sendt som almindelig PDF-mail, fordi mailen med betalingslink ikke kunne sendes.', array('rykker' => $nr)),
			'sent' => true, 'channel' => 'classic');
	}
	return array('result' => $result, 'message' => formReminderText(8139, 'Rykker {rykker} er ikke sendt: hverken mailen med betalingslink eller den almindelige PDF-mail kunne sendes.', array('rykker' => $nr)),
		'sent' => false, 'channel' => '');
}

/**
 * One alert with all staff messages of a print/send batch, printed before the page redirects. The messages are in the
 * database's encoding (findtekst()/reminder_txt()); jsString() turns them into an ASCII literal for either page charset.
 *
 * @param array<int, string> $messages Messages from formReminderRefusal()/formSendReminderEntry().
 * @return string A script tag, or '' without messages.
 */
function formReminderAlertHtml(array $messages): string {
	$messages = array_values(array_filter(array_map('strval', $messages), 'strlen'));
	if (!$messages) return '';
	if (!function_exists('jsString') && is_file(__DIR__ . '/../stdFunc/jsString.php')) include_once(__DIR__ . '/../stdFunc/jsString.php');
	$js = function_exists('jsString') ? jsString(array(implode("\n", $messages)))
		: json_encode(implode("\n", $messages), JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	return "<script type='text/javascript'>alert(" . $js . ");</script>\n";
}
