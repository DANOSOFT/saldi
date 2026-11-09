<?php
// 20261109 CL/ASR Created: delivery of one rendered reminder from rykkerprint(), mirroring formSendMailEntry() (invoiceMailDelivery.php).
//                 Every reminder first passes the legal check (renteloven § 9 b, reminderLegal.php).
//                 A link reminder (a draft/sent row in stripe_reminders) is mailed with payment links (stripeReminderMail.php), else the classic PDF mail.
//                 A classic reminder goes to the classic sender exactly as before.
//                 Installations without the reminder files or tables keep the classic behaviour.

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

/** @return string The reminder's number for staff messages (ordrer.fakturanr, e.g. "17-1"), or its id when it has none. */
function formReminderNumber(int $rykkerId): string {
	$r = db_fetch_array(db_select("select fakturanr from ordrer where id = " . $rykkerId, __FILE__ . " linje " . __LINE__));
	$nr = trim((string)($r['fakturanr'] ?? ''));
	return $nr !== '' ? $nr : (string)$rykkerId;
}

/**
 * The legal check before a reminder is printed or mailed (fee cap, fees on at most 3 reminders, 10 days between reminders).
 *
 * @param int $rykkerId ordrer.id of the R1/R2/R3 order.
 * @return string '' when it may be sent, else "Rykker 17-2 er ikke sendt: <reason from reminder_legal_check()>".
 */
function formReminderRefusal(int $rykkerId): string {
	formReminderIncludes();
	if (!function_exists('reminder_legal_check')) return '';
	$reason = trim((string)reminder_legal_check($rykkerId));
	return $reason === '' ? '' : 'Rykker ' . formReminderNumber($rykkerId) . ' er ikke sendt: ' . $reason;
}

/**
 * The stripe_reminders row of a reminder that is (still) sent as a link reminder: status draft or sent.
 * A charged or waived reminder, or one without a row, is a classic reminder here.
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
	return (is_array($row) && in_array((string)$row['status'], array('draft', 'sent'), true)) ? $row : null;
}

/** @return bool Whether the reminder PDF marks fee and interest as charged on the next invoice and leaves them out of "I alt". */
function formReminderFeeCarried(int $rykkerId): bool {
	$row = formReminderLinkRow($rykkerId);
	return $row !== null && (string)$row['fee_mode'] === 'next';
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
			return array('result' => false, 'message' => 'Rykker ' . $nr . ' er ikke sendt: alle fakturaer på rykkeren er betalt eller under betaling.',
				'sent' => false, 'channel' => '');
		}
		if (stripe_mail_reminder($rykkerId, $pdf, $email)) {
			formReminderMarkSent($rykkerId, 'link', $email);
			return array('result' => 'OK', 'message' => 'Rykker ' . $nr . ' er sendt med betalingslink til ' . $email . '.', 'sent' => true, 'channel' => 'link');
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
		return array('result' => $result, 'message' => 'Rykker ' . $nr . ' er sendt som almindelig PDF-mail, fordi mailen med betalingslink ikke kunne sendes.',
			'sent' => true, 'channel' => 'classic');
	}
	return array('result' => $result, 'message' => 'Rykker ' . $nr . ' er ikke sendt: hverken mailen med betalingslink eller den almindelige PDF-mail kunne sendes.',
		'sent' => false, 'channel' => '');
}

/**
 * One alert with all staff messages of a print/send batch, printed before the page redirects.
 *
 * @param array<int, string> $messages Messages from formReminderRefusal()/formSendReminderEntry().
 * @return string A script tag, or '' without messages.
 */
function formReminderAlertHtml(array $messages): string {
	$messages = array_values(array_filter(array_map('strval', $messages), 'strlen'));
	if (!$messages) return '';
	$js = json_encode(implode("\n", $messages), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	return "<script type='text/javascript'>alert(" . $js . ");</script>\n";
}
