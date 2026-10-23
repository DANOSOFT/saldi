<?php
// 20261022 CDX/LH Share classic recipient splitting, CC and sender/employee archive copies across Stripe mail.

/**
 * Parse Saldi's comma/semicolon email lists, removing spaces as classic mail does.
 * Validate the whole list before constructing a message so an invalid CC cannot be silently lost.
 *
 * @param string $addresses Saldi address field.
 * @return list<string>|null Valid recipients, or null for an empty/invalid list.
 */
function stripe_mail_recipients($addresses) {
	$parts = preg_split('/[;,]/', str_replace(' ', '', (string)$addresses));
	foreach ($parts as $address) {
		if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
			return null;
		}
	}
	return array_values(array_unique($parts));
}

/**
 * Build the classic envelope: first recipient To, remaining recipients CC, archive copies BCC.
 *
 * @param string $to Recipient field.
 * @param array{archive?: string, employee?: string} $sender Sender archive and optional employee copy.
 * @return array{to: string, cc: list<string>, bcc: list<string>}|null Null if any address is invalid.
 */
function stripe_mail_envelope($to, $sender) {
	$recipients = stripe_mail_recipients($to);
	if (!$recipients) {
		return null;
	}
	$bcc = array();
	foreach (array($sender['archive'] ?? '', $sender['employee'] ?? '') as $copy) {
		if ($copy === '') {
			continue;
		}
		$parsed = stripe_mail_recipients($copy);
		if (!$parsed) {
			return null;
		}
		$bcc = array_merge($bcc, $parsed);
	}
	return array('to' => $recipients[0], 'cc' => array_slice($recipients, 1), 'bcc' => array_values(array_unique($bcc)));
}

/**
 * Apply the validated envelope to PHPMailer (or an in-memory recipient recorder in tests).
 *
 * @param object $mail Mail object exposing AddAddress, AddCC and AddBCC.
 * @param array{to: string, cc: list<string>, bcc: list<string>} $envelope Validated envelope.
 * @return void
 */
function stripe_mail_apply_envelope($mail, $envelope) {
	$mail->AddAddress($envelope['to']);
	foreach ($envelope['cc'] as $address) {
		$mail->AddCC($address);
	}
	foreach ($envelope['bcc'] as $address) {
		$mail->AddBCC($address);
	}
}
