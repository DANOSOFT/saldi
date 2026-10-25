<?php
// 20261022 CDX/LH Dispatch each rendered mail using its recorded order id; report Stripe fallback failures.
// 20261022 CL/LH S5 Udskriv til = Stripe sends the pre-charge notice (invoice link, card, charge date); PDF mail on failure.
// 20261025 CL/ASR Link/Stripe mails also prepare the payment page's invoice page images (stripe_invoice_page_images).

/**
 * Deliver one rendered batch entry. The mail index is independent of the source order index.
 * Classic mail keeps its existing arguments and result; Stripe failures always attempt it too.
 *
 * @param array<int, int|string> $mailOrderIds Order ids recorded when the mail entries were queued.
 * @param int $mailnr One-based mail index, also passed through to the classic sender.
 * @param string $pdf PDF rendered for this mail entry.
 * @param string $email Entry's recipient list.
 * @param string $language Entry's form language.
 * @param int|string $form Entry's form number.
 * @param string $subject Classic subject/template override.
 * @param string $text Classic body/template override.
 * @param string $attachments Classic attachment override.
 * @param callable $classicSender Classic mail transport; injectable for regression tests.
 * @return array{result: mixed, error: string} Classic result and a persistent batch error after fallback.
 */
function formSendMailEntry($mailOrderIds, $mailnr, $pdf, $email, $language, $form, $subject, $text, $attachments, $classicSender = 'send_mails') {
	if (!isset($mailOrderIds[$mailnr]) || (int)$mailOrderIds[$mailnr] <= 0) {
		throw new InvalidArgumentException('Missing order id for batch mail entry');
	}
	$ordreId = (int)$mailOrderIds[$mailnr];
	$failed = false;
	if ((int)$form === 4 && is_file(__DIR__ . '/../stripeIncludes/stripeInvoice.php')) {
		include_once(__DIR__ . '/../stripeIncludes/stripeInvoice.php');
		try {
			$delivery = stripe_invoice_delivery($ordreId);
			if ($delivery === 'link' || $delivery === 'stripe') {
				if (!stripe_invoice_pdf_cache($ordreId, $pdf)) {
					throw new RuntimeException('Invoice PDF cache failed');
				}
				// 20261025 CL/ASR prepare the payment page's invoice images now, not on the customer's first view
				stripe_invoice_page_images($ordreId, true);
				$inv = stripe_invoice_load($ordreId);
				if (!$inv) {
					throw new RuntimeException('Invoice not found for ' . $delivery . ' mail');
				}
				// Use the recipients queued with this PDF, as classic mail does.
				$inv['email'] = $email;
				if ($delivery === 'link' && !stripe_mail_invoice_link($inv)) {
					throw new RuntimeException('Invoice link mail failed');
				}
				// R18: an enrolled debtor still receives the invoice, with card and charge date, before the charge.
				if ($delivery === 'stripe' && !stripe_mail_invoice_notice($inv)) {
					throw new RuntimeException('Invoice pre-charge notice failed');
				}
				return array('result' => 'OK', 'error' => '');
			}
		} catch (Throwable $e) {
			$failed = true;
			stripe_log('invoice delivery order ' . $ordreId . ': ' . $e->getMessage());
		}
	}
	if (!$failed) {
		return array('result' => $classicSender((string)$ordreId, $pdf, $email, $language, $form, $subject, $text, $attachments, $mailnr), 'error' => '');
	}
	try {
		$result = $classicSender((string)$ordreId, $pdf, $email, $language, $form, $subject, $text, $attachments, $mailnr);
		$sent = is_string($result) && ($result === 'OK' || strpos($result, 'Mail sent to ') === 0);
	} catch (Throwable $e) {
		stripe_log('classic invoice fallback order ' . $ordreId . ': ' . $e->getMessage());
		$result = false;
		$sent = false;
	}
	return array('result' => $result, 'error' => 'Ordre ' . $ordreId . ': Fakturamailen med link kunne ikke sendes. '
		. ($sent ? 'Klassisk PDF-mail er forsøgt.' : 'Klassisk PDF-mail kunne heller ikke sendes.'));
}
