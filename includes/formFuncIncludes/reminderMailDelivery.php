<?php
// 20261109 CL/ASR Created: delivery of one rendered reminder from rykkerprint(), mirroring formSendMailEntry() (invoiceMailDelivery.php).
//                 Every reminder first passes the legal check (renteloven § 9 b, reminderLegal.php).
//                 A link reminder (a draft/sent row in stripe_reminders) is mailed with payment links (stripeReminderMail.php), else the classic PDF mail.
//                 A classic reminder goes to the classic sender exactly as before.
//                 Installations without the reminder files or tables keep the classic behaviour.
// 20261109 CL/ASR Review fixes: the reminder PDF treats a fee that goes on the next invoice by the link reminder row in any status ("opkræves på din næste faktura" / "opkrævet på faktura X" / "frafaldet", never in "I alt"), and leaves out the invoices the link mail leaves out (D3; formReminderPdfTreatment()).
//                 A draft link reminder printed on paper counts as sent (formReminderPrinted()).
//                 Staff messages are texts 8135-8139 (tekster.csv) and the alert is built with jsString(), so a legacy (non-UTF8) database shows them right.
// 20261111 CL/ASR Review round 2: a link reminder whose fee was waived before it was ever sent ("Frafald", or its fee line deleted) still goes as a link mail and gets its mailed_at; a link reminder with no invoice left to pay is refused when printed too (not only when mailed), so no fee is carried for it.
// 20261111 CDX/ASR Match link reminder PDF payment terms to the mail deadline.
// 20261117 CDX/ASR Claim tracked automation deliveries before SMTP and record their outcome.
// 20261126 ASR Review I5: formReminderLetterFee(): the reminder letter states the reminder's own fee (its fee lines, capped when it was made), not today's fee item price.
// 20261126 ASR Review I7 (owner default Q10): a reminder the automatic flow tracks is refused with a message while the flow is off in this ledger on this installation (formReminderAutomationOffRefusal()), instead of the flow's error.
// 20261126 ASR Review M1: formReminderAlertThenGo(): one message, and the page moves on only after it is read (Saldi's message box does not block like alert()).
// 20261126 ASR Review R2: the Q10 refusal follows the job's state: a draft may be sent from the flow or deleted (8211), a sent one cannot be sent again (8213), a held, failed or uncertain one is handled on the flow's page (8214).
// 20261126 ASR Review GPT-R-4: a reminder that is mailed or printed records the day it first went out (reminder_dispatch_record()), so the 10 days to the next reminder with a fee count from then, not from the day it was made.
// 20261126 ASR Review GPT-R-10: the PDF leaves out the open items the link mail leaves out, item by item (stripe_reminder_invoices()' skipped_items), so a settled installment does not take the rest of its invoice with it.
// 20261127 ASR Review GPT-H-1: the last check before a reminder is mailed (formSendReminderUntracked()) or printed (formReminderPrintChecked()) and the record of the day it went out run under the customer's dispatch lock (reminder_dispatch_locked()).
//              Two first dispatches for the same invoice at once can then not both use the last fee renteloven § 9 b allows.

/**
 * Replace standard Danish fixed payment terms in a link reminder's form with its actual deadline.
 * Custom forms can use $rykker_frist directly. Classic reminders retain their configured wording.
 *
 * @param int $rykkerId Reminder order id.
 * @param string $text Form text before variable expansion, in the tenant's encoding.
 * @return string Form text with the same deadline as the link mail.
 */
function formReminderDeadlineText(int $rykkerId, string $text): string {
	$pattern = '/\b(indbetalt|betal)\s+(?:inden\s+[0-9]+\s+dage|straks(?:\s+dage)?)\b/i';
	if (strpos($text, '$rykker_frist') === false && !preg_match($pattern, $text)) return $text;
	formReminderIncludes();
	if (!function_exists('stripe_reminder_row') || !stripe_reminder_row($rykkerId)) return $text;
	$r = db_fetch_array(db_select("select fakturadate, ordredate, betalingsdage from ordrer where id = " . $rykkerId, __FILE__ . " linje " . __LINE__));
	if (!$r) return $text;
	$deadline = (new DateTimeImmutable(stripe_reminder_deadline($r)))->format('d.m.Y');
	$text = str_replace('$rykker_frist', $deadline, $text);
	return preg_replace($pattern, '${1} senest ' . $deadline, $text);
}

/**
 * The fee a reminder letter states ($rykker_gebyr, $forfalden_gebyr in find_form_tekst()): the reminder's own fee lines
 * (reminder_charge_lines()), which ny_rykker.php capped when it made the reminder (renteloven § 9 b) and which are in the
 * reminder's currency, so a capped, reduced or reprinted reminder names the fee it carries. Only for an order that is no
 * reminder, the level's fee item price in the debtor's currency, capped the same way.
 *
 * @param int $id ordrer.id the form is printed for.
 * @param int $formular Form number (6, 7, 8 for the reminder levels).
 * @param string $formularsprog Form language, lower case.
 * @param string $valuta The debtor's currency ('' = DKK).
 * @param float $valutakurs The debtor's rate (DKK per 100 units; 0 = 100).
 * @return float The fee in the debtor's currency.
 */
function formReminderLetterFee(int $id, int $formular, string $formularsprog, string $valuta, float $valutakurs): float {
	formReminderIncludes();
	if ($id > 0 && function_exists('reminder_charge_lines') && ($charge = reminder_charge_lines($id)) !== null) {
		return (float)$charge['fee'];
	}
	$qtxt = "select salgspris from varer where id IN (select xb from formularer where beskrivelse='GEBYR' and formular='" . $formular . "' and lower(sprog)='" . db_escape_string($formularsprog) . "')";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$fee = (float)($r['salgspris'] ?? 0);
	if (!$valutakurs) $valutakurs = 100;
	if ($valutakurs != 100) $fee *= 100 / $valutakurs; # 20140628 in the debtor's currency
	return function_exists('reminder_legal_cap_fee') ? (float)reminder_legal_cap_fee($fee, $valuta, $valutakurs)['fee'] : $fee;
}

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
				if (isset($set['skipped_items'][(int)$invoice['openpost_id']])) $out['skip'][] = (int)$invoice['openpost_id'];   // 20261126 ASR Review GPT-R-10
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
 * (formReminderRefusal()). Every printed reminder records the day it first went out (formReminderDispatched()).
 *
 * @param int $rykkerId ordrer.id of the R1/R2/R3 order.
 * @return void
 */
function formReminderPrinted(int $rykkerId): void {
	$row = formReminderLinkRow($rykkerId);
	if ($row !== null && trim((string)($row['mailed_at'] ?? '')) === '') formReminderMarkSent($rykkerId, 'classic', '');
	formReminderDispatched($rykkerId, 'print');
}

/**
 * The last check before a reminder is printed on paper (rykkerprint()), and its record: renteloven § 9 b and the link
 * reminder's invoices again (formReminderRefusal()), and when it may go, it counts as sent (formReminderPrinted()) before
 * the customer's dispatch lock is let go (reminder_dispatch_locked()). So a reminder for the same invoice that is mailed or
 * printed at the same time is checked after this one counts.
 *
 * @param int $rykkerId ordrer.id of the R1/R2/R3 order.
 * @return string '' when it is printed and recorded, else the refusal ("Rykker 17-2 er ikke sendt: ...").
 */
function formReminderPrintChecked(int $rykkerId): string {
	formReminderIncludes();
	$work = function () use ($rykkerId): string {
		$refusal = formReminderRefusal($rykkerId);
		if ($refusal === '') formReminderPrinted($rykkerId);
		return $refusal;
	};
	return function_exists('reminder_dispatch_locked') ? (string)reminder_dispatch_locked($rykkerId, $work) : $work();
}

/** @return void Record the day a reminder first went out (reminder_dispatch_record()); a failure is logged, the reminder is already out. */
function formReminderDispatched(int $rykkerId, string $channel): void {
	formReminderIncludes();
	if (!function_exists('reminder_dispatch_record')) return;
	try {
		reminder_dispatch_record($rykkerId, $channel);
	} catch (Throwable $e) {
		formReminderLog('reminder dispatch ' . $rykkerId . ': ' . $e->getMessage());
	}
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
    require_once __DIR__ . '/../reminderAutomation.php';
    $automation = reminder_automation_service();
    if ($automation && $automation->job((int)$rykkerId)) {
        // 20261126 ASR Review I7 (owner default Q10): the flow sends it; while the flow is off here, a message instead of its error
        $off = formReminderAutomationOffRefusal((int)$rykkerId, $automation);
        if ($off !== '') return array('result' => false, 'message' => $off, 'sent' => false, 'channel' => '');
        return $automation->send((int)$rykkerId, function () use ($rykkerId, $pdf, $email, $language, $form, $classicSender) {
            if (!is_file($pdf) || filesize($pdf) < 5 || file_get_contents($pdf, false, null, 0, 5) !== '%PDF-') {
                throw new RuntimeException('Rykkerens PDF mangler eller er ugyldig.');
            }
            return formSendReminderUntracked($rykkerId, $pdf, $email, $language, $form, $classicSender, false);
        }, (string)($GLOBALS['brugernavn'] ?? 'scheduler'));
    }
    return formSendReminderUntracked($rykkerId, $pdf, $email, $language, $form, $classicSender);
}

/**
 * A reminder the automatic flow tracks (a reminder_automation_jobs row) is sent by the flow only. While the flow is off in
 * this ledger on this installation (switched off, a copied or restored ledger, another installation folder), a manual
 * Udskriv or Send is refused with a message instead of the flow's error (owner default Q10, 26 Nov 2026).
 * The message follows the job's state: only a draft (approval, queued) may be sent from the flow or deleted (8211).
 * One the flow has sent cannot be sent again (8213), and one that is held or whose delivery failed or is uncertain is
 * handled on the flow's page (8214); neither offers to delete it.
 *
 * @param int $rykkerId ordrer.id of the reminder.
 * @param ReminderAutomation|null $automation The ledger's automation (reminder_automation_service()); null on MySQL.
 * @return string '' when the flow does not track it or is on here, else the staff message (text 8211, 8213 or 8214).
 */
function formReminderAutomationOffRefusal(int $rykkerId, $automation): string {
	$job = $automation ? $automation->job($rykkerId) : null;
	if (!$job) return '';
	$config = $automation->config();
	if ($config['armed'] && $config['mode'] !== 'off') return '';
	$nr = formReminderNumber($rykkerId);
	$state = (string)($job['state'] ?? '');
	if (in_array($state, array('approval', 'queued'), true)) {
		return formReminderText(8211, 'Rykker {rykker} er ikke sendt: den følges af de automatiske rykkere, som ikke er slået til i dette regnskab på denne installation. Slå de automatiske rykkere til her, og send den derfra, eller slet kladden.',
			array('rykker' => $nr));
	}
	if ($state === 'sent') {
		$date = substr(trim((string)(($job['sent_date'] ?? '') ?: ($job['sent_at'] ?? ''))), 0, 10);
		if ($date !== '' && function_exists('dkdato')) $date = dkdato($date);
		return formReminderText(8213, 'Rykker {rykker} er sendt af de automatiske rykkere den {dato} og kan ikke sendes igen herfra.',
			array('rykker' => $nr, 'dato' => $date));
	}
	return formReminderText(8214, 'Rykker {rykker} er ikke sendt: den følges af de automatiske rykkere, som ikke er slået til i dette regnskab på denne installation. Den er sat på pause, eller dens afsendelse fejlede eller er uafklaret. Slå de automatiske rykkere til her, og se den under Automatiske rykkere.',
		array('rykker' => $nr));
}

/**
 * Existing delivery implementation, under the customer's dispatch lock (reminder_dispatch_locked()): the last legal check,
 * the mail and the record of the day it went out (formReminderDispatched()) run before another first dispatch for the
 * customer is checked.
 *
 * @return array{result: mixed, message: string, sent: bool, channel: string}
 */
function formSendReminderUntracked(int $rykkerId, string $pdf, string $email, string $language, $form, $classicSender = 'send_mails', bool $allowFallback = true): array {
	formReminderIncludes();
	$work = function () use ($rykkerId, $pdf, $email, $language, $form, $classicSender, $allowFallback): array {
		return formSendReminderChecked($rykkerId, $pdf, $email, $language, $form, $classicSender, $allowFallback);
	};
	// 20261127 ASR Review GPT-H-1
	return function_exists('reminder_dispatch_locked') ? reminder_dispatch_locked($rykkerId, $work) : $work();
}

/** @return array{result: mixed, message: string, sent: bool, channel: string} formSendReminderUntracked() inside its lock. */
function formSendReminderChecked(int $rykkerId, string $pdf, string $email, string $language, $form, $classicSender = 'send_mails', bool $allowFallback = true): array {
	$refusal = formReminderRefusal($rykkerId);
	if ($refusal !== '') {
		return array('result' => false, 'message' => $refusal, 'sent' => false, 'channel' => '');
	}
	$row = formReminderLinkRow($rykkerId);
	if ($row === null) {
		$result = $classicSender((string)$rykkerId, $pdf, $email, $language, $form, '', '', '', 0);
		$sent = formReminderClassicSent($result);
		if ($sent) formReminderDispatched($rykkerId, 'classic');   // 20261126 ASR Review GPT-R-4
		return array('result' => $result, 'message' => '', 'sent' => $sent, 'channel' => 'classic');
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
			formReminderDispatched($rykkerId, 'link');
			return array('result' => 'OK', 'message' => formReminderText(8137, 'Rykker {rykker} er sendt med betalingslink til {email}.', array('rykker' => $nr, 'email' => $email)),
				'sent' => true, 'channel' => 'link');
		}
	} catch (Throwable $e) {
		formReminderLog('reminder link mail ' . $rykkerId . ': ' . $e->getMessage());
	}
    if (!$allowFallback) {
        return array('result' => false, 'message' => 'Linkmailens afsendelse er uafklaret. Kontrollér mailserverens log før nyt forsøg.', 'sent' => false, 'channel' => '');
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
		formReminderDispatched($rykkerId, 'classic');
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

/**
 * One message for staff, then the page moves on to $url. Saldi's message box (javascript/saldi-dialog.js) does not block
 * like the browser's alert(), so a meta refresh printed after it took the page away before the message could be read:
 * the page now moves on when OK is pressed (SaldiDialog.alert(...).then()). Without the box the browser's alert() blocks
 * as before. Without script, and without a message, a meta refresh as before.
 *
 * @param array<int, string> $messages Messages in the database's encoding (findtekst()/reminder_txt()); empty ones are left out.
 * @param string $url Where the page goes next, as the page's meta refresh named it.
 * @return string HTML for the end of the page.
 */
function formReminderAlertThenGo(array $messages, string $url): string {
	$messages = array_values(array_filter(array_map('strval', $messages), 'strlen'));
	$meta = '<meta http-equiv="refresh" content="0;URL=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
	if (!$messages) return $meta . "\n";
	if (!function_exists('jsString') && is_file(__DIR__ . '/../stdFunc/jsString.php')) include_once(__DIR__ . '/../stdFunc/jsString.php');
	$flags = JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
	$msg = function_exists('jsString') ? jsString(array(implode("\n", $messages))) : json_encode(implode("\n", $messages), $flags);
	return "<script type='text/javascript'>(function (msg, url) { var go = function () { location.replace(url); };"
		. " if (window.SaldiDialog && SaldiDialog.alert) SaldiDialog.alert(msg).then(go); else { alert(msg); go(); } })("
		. $msg . ', ' . json_encode($url, $flags | JSON_UNESCAPED_SLASHES) . ");</script>\n<noscript>" . $meta . "</noscript>\n";
}
