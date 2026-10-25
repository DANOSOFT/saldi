<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/stripeInvoice.php --- 2026.10.22
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261019 CL/ASR Created. "Saldi invoices, Stripe collects" (Requirements_invoice_email_payment_page_EN.md):
//                 one library for the invoice & payment page, the link-format mails, the
//                 PaymentIntent per Saldi invoice, the webhook handlers, the journal line and
//                 the enrolment (card on file). Everything runs against the CURRENT ledger
//                 connection ($db) - the public endpoints connect through stripeBootstrap.php,
//                 the print engine and the settings pages through online.php.
//
// Tables: stripe_payments (one row per PaymentIntent; partial unique index: at most one
// 'succeeded' per invoice), plus the enrolment columns on adresser (stripe, stripe_customer_id,
// stripe_pm_id, stripe_date). DDL is idempotent (stripe_payments_ensure).
// 20261022 CDX/LH Gate invoice collection and delivery; add state clock and independent mail transport guard for tests.
// 20261022 CDX/LH Claim captured payments once, validate identity, and repair atomic bookkeeping before receipts and enrolment.
// 20261022 CDX/LH Retry duplicate-capture alerts on verified success replays without rewriting capture or adjustment fields.
// 20261022 CDX/LH Persist collection identities; separate declines, technical errors and authentication; use Copenhagen calendar dates.
// 20261022 CDX/LH Split mail recipients into To/CC, retain archive BCC and return transport failures for PDF fallback.
// 20261022 CDX/LH Repair indexes independently, use private PDF storage, enforce due-date collection and match form discount totals.
// 20261022 CL/LH S5 customer texts: decline codes in Danish, first-name greeting, last-attempt text, pre-charge notice mail for enrolled debtors.
// 20261022 CDX/LH Match consent to the configured charge date and refuse notices for invoices the job cannot collect.
// 20261022 CDX/LH Reconcile caller changes before collection, block debtor credits and remove the unavailable Saldi menu hint.

include_once(__DIR__ . '/../std_func.php');
include_once(__DIR__ . '/stripeMailRecipients.php');
include_once(__DIR__ . '/stripeSettings.php');
include_once(__DIR__ . '/stripePdf.php');
include_once(__DIR__ . '/stripeInvoiceSchema.php');
include_once(__DIR__ . '/stripeLink.php');
include_once(__DIR__ . '/stripeHttp.php');
include_once(__DIR__ . '/stripeAlertMail.php');
include_once(__DIR__ . '/stripePaymentBooking.php');
include_once(__DIR__ . '/stripeCollection.php');
include_once(__DIR__ . '/stripeChargeSchema.php');
include_once(__DIR__ . '/stripeAttempts.php');
include_once(__DIR__ . '/stripeCharge.php');

// 20261022 CDX/LH Reconcile attempts before collection; restrict eligibility and full-open amounts; support manual payment state.
// 20261025 CL/ASR Texts for the two pay buttons (automatic payment / this invoice only) and the fee for manual payment.
// 20261025 CL/ASR Mails from Saldi's own ledger show the Saldi logo instead of the name.

// ---------------------------------------------------------------- texts (Danish v1; ids reserved for tekster.csv later)
// Customer texts use the "du" form and never show Stripe's English codes; bookkeeper alerts keep them.
if (!function_exists('stripe_txt')) {
	function stripe_txt($key, $vars = array()) {
		static $t = array(
			'page_title'        => 'Faktura %nr fra %firma',
			'invoice'           => 'Faktura',
			'creditnote'        => 'Kreditnota',
			'download_pdf'      => 'Download PDF',
			'bill_to'           => 'Faktureres til',
			'credit_to'         => 'Krediteres til',
			'invoice_no'        => 'Fakturanr.',
			'creditnote_no'     => 'Kreditnotanr.',
			'customer_no'       => 'Kundenr.',
			'invoice_date'      => 'Fakturadato',
			'due_date'          => 'Forfaldsdato',
			'description'       => 'Beskrivelse',
			'qty'               => 'Antal',
			'price'             => 'Pris',
			'amount'            => 'Beløb',
			'subtotal'          => 'Subtotal',
			'vat'               => 'Moms %pct %',
			'total'             => 'Total %cur',
			'terms'             => 'Betalingsbetingelser:',
			'bank_line'         => 'Betal med kort i panelet, eller overfør beløbet til reg. %reg konto %konto med fakturanummeret %nr som reference.',
			'bank_line_nocard'  => 'Overfør beløbet til reg. %reg konto %konto med fakturanummeret %nr som reference.',
			'due_pill'          => 'Forfalder %date',
			'overdue_pill'      => 'Forfaldt %date',
			'paid_pill'         => 'Betalt %date',
			'credited_pill'     => 'Krediteret %date',
			'lab_payment'       => 'Betaling',
			'h_open'            => 'Betal fakturaen',
			'lead_open'         => 'Betal med kort, Apple Pay eller Google Pay. Du kan også overføre beløbet via din bank.',
			'h_recurring'       => 'Betal og slå automatisk betaling til',
			'lead_recurring'    => 'Betal fakturaen med kort. Vil du, kan du gemme kortet, så dine næste fakturaer fra %brand betales automatisk.',
			'h_enrolled'        => 'Betales automatisk',
			'lead_enrolled'     => 'Du betaler automatisk med dit gemte kort. Du får en kvittering på mail, når beløbet er trukket.',
			'enrolled_on'       => 'Trækkes den %date',
			'h_pending'         => 'Betalingen behandles',
			'lead_pending'      => 'Vi venter på svar fra din bank. Det tager normalt kun få sekunder.',
			'pending_box'       => 'Behandles …',
			'pending_sub'       => 'Siden opdaterer sig selv. Du får en kvittering på mail, så snart betalingen er gennemført.',
			'h_paid'            => 'Tak for din betaling',
			'lead_paid'         => 'Fakturaen er betalt. Vi sender en kvittering til %email.',
			'paid_box'          => 'Betalt %date',
			'h_paidbank'        => 'Fakturaen er betalt',
			'lead_paidbank'     => 'Vi har registreret din betaling.',
			'h_overdue'         => 'Fakturaen er forfalden',
			'lead_overdue'      => 'Fakturaen forfaldt den %date. Betal nu for at undgå en rykker.',
			'h_failed'          => 'Betalingen gik ikke igennem',
			'lead_failed'       => 'Vi kunne ikke trække beløbet på dit gemte kort. Du kan betale her med samme eller et andet kort.',
			'lead_failed_page'  => 'Prøv igen med samme eller et andet kort.',
			'failed_box'        => '%card blev afvist den %date',
			'h_manual'          => 'Kontakt os om betalingen',
			'lead_manual'       => 'Fakturaen kan ikke betales med kort her. Kontakt os, så hjælper vi dig med betalingen.',
			'h_nocard'          => 'Betal via bankoverførsel',
			'lead_nocard'       => 'Fakturaen kan ikke betales med kort. Overfør beløbet, og skriv fakturanummeret som reference.',
			'h_credited'        => 'Beløbet er krediteret',
			'lead_credited'     => 'Beløbet er krediteret din konto.',
			'to_pay'            => 'At betale',
			'amount_lbl'        => 'Beløb',
			'card_lbl'          => 'Kort',
			'charge_date_lbl'   => 'Trækkes den',
			'pay_btn'           => 'Betal %amount',
			'pay_save_btn'      => 'Betal %amount og gem kortet',
			'pay_auto_btn'      => 'Betal %amount og slå automatisk betaling til',
			'pay_once_btn'      => 'Betal kun denne faktura',
			'auto_title'        => 'Automatisk betaling',
			'fee_note'          => 'Uden automatisk betaling lægges et gebyr for manuel betaling på %fee på din næste faktura.',
			'pay_now_btn'       => 'Betal %amount nu',
			'chk_title'         => 'Betal mine næste fakturaer automatisk',
			'chk_sub'           => 'Kortet gemmes hos Stripe, og dine næste fakturaer fra %brand trækkes på forfaldsdatoen. Du får hver faktura på mail, før beløbet trækkes. Vil du fjerne kortet, så %kontakt.',
			'chk_sub_invoice_date' => 'Kortet gemmes hos Stripe, og dine næste fakturaer fra %brand trækkes på fakturadatoen. Du får hver faktura på mail, før beløbet trækkes. Vil du fjerne kortet, så %kontakt.',
			'chk_replace_title' => 'Brug dette kort fremover',
			'chk_replace_sub'   => 'Kortet erstatter %card til dine næste fakturaer.',
			'trust'             => 'Sikker betaling via Stripe. Kortoplysningerne sendes direkte til Stripe og gemmes aldrig hos %brand. Kvitteringen sendes til %email.',
			'manage_hint'       => 'Vil du skifte kort eller stoppe automatisk betaling, så %kontakt.',
			'contact_mail'      => 'kontakt os på %email',
			'contact_plain'     => 'kontakt os',
			'questions'         => 'Spørgsmål til fakturaen? Kontakt os på %email%phone.',
			'bank_box_title'    => 'Betal via bankoverførsel',
			'bank_box_ref'      => 'Reference: %nr',
			'bank_box_note'     => 'Betalingen registreres, når vi har modtaget den.',
			'card_saved_row'    => 'Kortet er gemt til dine næste fakturaer',
			'already_paid'      => 'Fakturaen er allerede betalt.',
			'pay_in_progress'   => 'En betaling er allerede i gang. Vent et øjeblik, og opdater siden.',
			'pay_not_due'       => 'Beløbet trækkes automatisk på forfaldsdatoen. Indtil da kan du ikke betale med det gemte kort.',
			'pay_start_failed'  => 'Betalingen kunne ikke startes. Prøv igen om lidt.',
			'pay_failed'        => 'Betalingen kunne ikke gennemføres. Prøv igen.',
			'network_error'     => 'Noget gik galt. Tjek din internetforbindelse, og prøv igen.',
			'bad_request'       => 'Siden er ikke længere gyldig. Genindlæs siden, og prøv igen.',
			'unavailable'       => 'Siden er midlertidigt utilgængelig',
			'unavailable_txt'   => 'Prøv igen om lidt.',
			'rate'              => 'For mange forsøg',
			'rate_txt'          => 'Vent et par minutter, og prøv igen.',
			'pdf_unavailable'   => 'PDF-filen er ikke klar',
			'pdf_unavailable_txt' => 'Fakturaen kunne ikke dannes som PDF lige nu. Prøv igen om lidt.',
			'link_invalid'      => 'Linket er ikke gyldigt',
			'link_invalid_txt'  => 'Fakturaen kunne ikke findes ud fra linket. Åbn linket fra den seneste mail, eller kontakt os.',
			'not_active'        => 'Online betaling er ikke slået til',
			'not_active_txt'    => 'Fakturaen er stadig gyldig og kan betales via bankoverførsel som angivet på fakturaen.',
			'h_action'          => 'Godkend betalingen',
			'lead_action'       => 'Din bank skal godkende betalingen, før den kan gennemføres. Tryk på knappen, og følg din banks anvisninger.',
			'action_btn'        => 'Godkend betalingen',
			'action_unavailable'=> 'Betalingen kunne ikke hentes lige nu. Prøv igen om lidt.',
			// Stripe decline/error codes -> customer sentences (stripe_decline_text). Raw codes go to bookkeeper alerts only.
			'decline_insufficient_funds'      => 'Der er ikke dækning på kortet.',
			'decline_expired_card'            => 'Kortet er udløbet.',
			'decline_incorrect_cvc'           => 'Kontrolcifrene (CVC) er forkerte.',
			'decline_incorrect_number'        => 'Kortnummeret er forkert.',
			'decline_processing_error'        => 'Der opstod en fejl hos banken. Prøv igen om lidt.',
			'decline_authentication_required' => 'Din bank skal godkende betalingen.',
			'decline_generic'                 => 'Banken har afvist betalingen. Kontakt din bank, eller brug et andet kort.',
			// bookkeeper alerts (internal; keep Stripe's raw codes)
			'alert_action_subject'    => 'Stripe: kunden har ikke godkendt betalingen - faktura %nr',
			'alert_action_body'       => "Kunde: %kunde (konto %konto)\nFaktura: %nr\nFakturaen er stadig ubetalt efter sidste forsøgsdato, fordi kunden ikke har godkendt betalingen hos sin bank (authentication_required).\nPaymentIntent: %pi\nFakturalink til kunden: %url",
			'alert_technical_subject' => 'Stripe: teknisk fejl - ordre %id',
			'alert_technical_body'    => 'Ordre %id har haft tekniske fejl siden %date. Seneste fejl: %error',
			'alert_declines_subject'  => 'Stripe: automatisk træk afvist 3 gange - faktura %nr',
			'alert_declines_body'     => "Kunde: %kunde (konto %konto)\nFaktura: %nr\nSidste fejl fra Stripe: %code\nDer trækkes ikke automatisk igen. Fakturaen følger den almindelige rykkervej.",
			// mails
			'mail_greeting'     => 'Hej %fornavn,',
			'mail_greeting_anon'=> 'Hej,',
			'mail_label_invoice'=> 'Faktura %nr',
			'mail_h_new_month'  => 'Din faktura for %month er klar',
			'mail_h_new'        => 'Din faktura er klar',
			'mail_subj_new'     => 'Faktura %nr fra %brand',
			'mail_body_new'     => 'Her er din faktura. Du kan se den og betale med kort på samme side.',
			'mail_body_new_rec' => ' Gemmer du kortet, når du betaler, betales dine næste fakturaer automatisk.',
			'mail_btn_new'      => 'Se faktura og betal',
			'mail_note_new'     => 'Virker knappen ikke, så åbn %url i din browser. Vil du hellere betale med bankoverførsel, står oplysningerne på fakturaen.',
			'mail_subj_notice'  => 'Faktura %nr fra %brand - trækkes den %date',
			'mail_body_notice'  => 'Her er din faktura. Du skal ikke gøre noget: Beløbet trækkes automatisk på dit kort den %date.',
			'mail_btn_notice'   => 'Se fakturaen',
			'mail_note_notice'  => 'Vil du betale på en anden måde eller fjerne kortet, så %kontakt inden den %date. Du får en kvittering, når beløbet er trukket.',
			'mail_card_saved'   => 'dit gemte kort',
			'mail_subj_paid'    => 'Tak for din betaling – faktura %nr',
			'mail_h_paid'       => 'Tak for din betaling',
			'mail_body_paid'    => 'Vi har modtaget betalingen af faktura %nr. Her er din kvittering.',
			'mail_paid_box'     => '%amount betalt den %date',
			'mail_btn_pdf'      => 'Hent faktura (PDF)',
			'mail_subj_failed'  => 'Betalingen af faktura %nr gik ikke igennem',
			'mail_h_failed'     => 'Betalingen gik ikke igennem',
			'mail_body_failed'  => 'Vi forsøgte at trække beløbet for faktura %nr på dit gemte kort, men betalingen gik ikke igennem.',
			'mail_body_failed_backup' => 'Vi forsøgte at trække beløbet for faktura %nr på dit gemte kort og på dit reservekort, men betalingen gik ikke igennem.',
			'mail_failed_box'   => '%card blev afvist den %date',
			'mail_btn_failed'   => 'Betal nu eller skift kort',
			'mail_note_failed'  => 'Vi prøver at trække beløbet igen den %retry. Vil du hellere betale nu eller bruge et andet kort, så brug knappen ovenfor.',
			'mail_note_failed_last' => 'Vi trækker ikke beløbet automatisk igen. Betal fakturaen via knappen ovenfor, eller kontakt os. Er fakturaen stadig ubetalt, sender vi en rykker.',
			'mail_subj_action'  => 'Godkend betalingen af faktura %nr',
			'mail_h_action'     => 'Din bank skal godkende betalingen',
			'mail_body_action'  => 'Vi har forsøgt at trække beløbet for faktura %nr på dit gemte kort, men din bank vil have, at du godkender betalingen først. Tryk på knappen, og følg din banks anvisninger. Vi trækker ikke beløbet, før du har godkendt det.',
			'mail_unenrol_body' => 'Automatisk kortbetaling er stoppet, så vi trækker ikke beløbet for faktura %nr på dit kort. Fakturaen er vedhæftet. Betal den med bankoverførsel som angivet på fakturaen.',
			'period'            => 'Periode',
			'next_invoice'      => 'Næste faktura',
			'auto_payment'      => 'automatisk betaling',
			'card_payment'      => 'kortbetaling',
			'bank_transfer'     => 'bankoverførsel',
			'journal_name'      => 'Stripe-betalinger',
			'journal_text'      => 'Stripe %card faktura %nr',
		);
		$s = isset($t[$key]) ? $t[$key] : $key;
		foreach ($vars as $k => $v) $s = str_replace('%' . $k, (string)$v, $s);
		return $s;
	}
}
if (!function_exists('stripe_decline_text')) {
	/**
	 * Customer sentence for a Stripe decline/error code. Never returns the raw code.
	 *
	 * @param string|null $code Stripe decline_code or error code as stored in stripe_payments.last_error.
	 * @return string Danish sentence; a generic decline sentence for unknown codes.
	 */
	function stripe_decline_text($code) {
		$map = array(
			'insufficient_funds' => 'insufficient_funds',
			'expired_card' => 'expired_card',
			'incorrect_cvc' => 'incorrect_cvc', 'invalid_cvc' => 'incorrect_cvc',
			'incorrect_number' => 'incorrect_number', 'invalid_number' => 'incorrect_number',
			'processing_error' => 'processing_error',
			'authentication_required' => 'authentication_required', 'payment_intent_authentication_failure' => 'authentication_required',
		);
		$code = strtolower(trim((string)$code));
		return stripe_txt('decline_' . (isset($map[$code]) ? $map[$code] : 'generic'));
	}
	/** @return string "Hej Anna," from the contact person's first name, otherwise "Hej,". */
	function stripe_mail_greeting($inv) {
		$name = trim((string)($inv['fornavn'] ?? ''));
		return $name !== '' ? stripe_txt('mail_greeting', array('fornavn' => $name)) : stripe_txt('mail_greeting_anon');
	}
	/** @return string "kontakt os på <invoicing company's e-mail>", or "kontakt os" without one. */
	function stripe_contact_phrase($inv) {
		$email = trim((string)($inv['firma']['email'] ?? ''));
		return $email !== '' ? stripe_txt('contact_mail', array('email' => $email)) : stripe_txt('contact_plain');
	}
	/** @return string Save-card consent matching the configured automatic collection date. */
	function stripe_invoice_consent($inv) {
		$key = stripe_setting('charge_on', 'due') === 'invoice_date' ? 'chk_sub_invoice_date' : 'chk_sub';
		return stripe_txt($key, array('brand' => $inv['brand'], 'kontakt' => stripe_contact_phrase($inv)));
	}
}

// ---------------------------------------------------------------- DDL
if (!function_exists('stripe_payments_ensure')) {
	function stripe_payments_ensure() {
		global $db_type;
		if (in_array($db_type, array('mysql', 'mysqli'), true)) return;
		static $done = false;
		if ($done) {
			stripe_payment_indexes_ensure();
			return;
		}
		$chk = db_fetch_array(db_select("SELECT column_name FROM information_schema.columns WHERE table_name='stripe_payments' AND column_name='payment_intent_id'", __FILE__ . " linje " . __LINE__));
		if (!$chk) {
			db_modify("CREATE TABLE IF NOT EXISTS stripe_payments (id SERIAL PRIMARY KEY, ordre_id integer NOT NULL, fakturanr varchar(30),
				stripe_customer_id varchar(255), payment_intent_id varchar(255) NOT NULL,
				amount_ore integer NOT NULL, currency varchar(3) NOT NULL DEFAULT 'DKK',
				status varchar(20) NOT NULL, source varchar(10) NOT NULL DEFAULT 'page',
				save_card boolean NOT NULL DEFAULT false, pm_id varchar(255), pm_brand varchar(20), pm_last4 varchar(4),
				attempt smallint NOT NULL DEFAULT 1, last_error text,
				created_at timestamp DEFAULT CURRENT_TIMESTAMP, succeeded_at timestamp,
				journal_line_id integer, confirmation_sent_at timestamp, last_event_id varchar(255))", __FILE__ . " linje " . __LINE__);
		}
		stripe_payment_indexes_ensure();
		foreach (array('stripe' => 'varchar(2)', 'stripe_customer_id' => 'varchar(255)', 'stripe_pm_id' => 'varchar(255)', 'stripe_date' => 'date') as $col => $type) {
			$c = db_fetch_array(db_select("SELECT column_name FROM information_schema.columns WHERE table_name='adresser' AND column_name='$col'", __FILE__ . " linje " . __LINE__));
			if (!$c) db_modify("ALTER TABLE adresser ADD COLUMN IF NOT EXISTS $col $type", __FILE__ . " linje " . __LINE__);
		}
		stripe_charge_schema_ensure();
		$done = true;
	}
}

// ---------------------------------------------------------------- formatting
if (!function_exists('stripe_bool')) {
	// PostgreSQL returns 't'/'f' for boolean columns; MySQL 1/0.
	function stripe_bool($v) { return $v === true || $v === 't' || $v === 1 || $v === '1' || $v === 'true' || $v === 'on'; }
}
if (!function_exists('stripe_fmt_kr')) {
	function stripe_fmt_kr($ore) { return number_format(((int)$ore) / 100, 2, ',', '.'); }
	function stripe_fmt_date($ymd) { return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string)$ymd, $m) ? "$m[3].$m[2].$m[1]" : (string)$ymd; }
	function stripe_fmt_date_long($ymd) {
		$mnd = array(1 => 'januar', 'februar', 'marts', 'april', 'maj', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'december');
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string)$ymd, $m)) return (string)$ymd;
		return (int)$m[3] . '. ' . $mnd[(int)$m[2]] . ' ' . $m[1];
	}
	function stripe_month_name($ymd) {
		$mnd = array(1 => 'januar', 'februar', 'marts', 'april', 'maj', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'december');
		return preg_match('/^(\d{4})-(\d{2})/', (string)$ymd, $m) ? $mnd[(int)$m[2]] . ' ' . $m[1] : '';
	}
	function stripe_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
	function stripe_card_label($brand, $last4) {
		$b = strtolower((string)$brand);
		$names = array('visa' => 'Visa', 'mastercard' => 'Mastercard', 'amex' => 'American Express', 'dankort' => 'Dankort');
		$name = isset($names[$b]) ? $names[$b] : ucfirst($b ? $b : 'Kort');
		return $last4 ? "$name •••• $last4" : $name;
	}
}

// ---------------------------------------------------------------- settings: delivery format
if (!function_exists('stripe_invoice_format')) {
	/**
	 * Resolve the tenant-wide invoice mail format, respecting the invoice rollout switch.
	 *
	 * @return string pdf (default/off) or link.
	 */
	function stripe_invoice_format() {
		global $db;
		if (!$db || $db !== stripeConfigDb()) return 'pdf';
		if (!stripe_feature_on('invoice_payments') || stripe_setting('enabled', 'off') !== 'on') {
			return 'pdf';
		}
		$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'email_settings' and var_name = 'invoice_format' and (user_id is null or user_id = 0)", __FILE__ . " linje " . __LINE__));
		return ($r && trim((string)$r['var_value']) === 'link') ? 'link' : 'pdf';
	}

	function stripe_invoice_link_text() {
		$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'email_settings' and var_name = 'invoice_link_text' and (user_id is null or user_id = 0)", __FILE__ . " linje " . __LINE__));
		return ($r && trim((string)$r['var_value']) !== '') ? trim((string)$r['var_value']) : '';
	}
	// What happens when an invoice is issued: 'pdf' (classic mail), 'link' (link mail), 'stripe'
	// (enrolled debtor: pre-charge notice, charged on the configured date), 'none' (not an e-mail invoice).
	function stripe_invoice_delivery($ordreId) {
		$o = db_fetch_array(db_select("select * from ordrer where id = " . (int)$ordreId, __FILE__ . " linje " . __LINE__));
		if (!$o) return 'none';
		if (!stripe_invoice_collectable(array('o' => $o))) return 'pdf';
		if (trim((string)$o['udskriv_til']) === 'Stripe') {
			$debtor = db_fetch_array(stripe_payment_select('select stripe, stripe_pm_id from adresser where id = ' . (int)$o['konto_id']));
			return $debtor && $debtor['stripe'] === 'on' && !empty($debtor['stripe_pm_id']) && stripe_setting('enabled', 'off') === 'on' && stripe_feature_on('invoice_payments') && stripe_feature_on('enrolment') ? 'stripe' : 'pdf';
		}
		if (trim((string)$o['udskriv_til']) !== 'email') return 'none';
		if ($o['art'] !== 'DO') return 'pdf';           // credit notes etc. stay classic in v1 (decision D6/O6)
		return stripe_invoice_format();
	}
}

/**
 * Shared invoice-flow eligibility, including pre-invoicing delivery decisions.
 * Subscription customers remain exclusively in the legacy subscription flow.
 *
 * @param array{o: array<string, mixed>} $inv Order-backed invoice (may be unissued).
 * @return bool Whether this tenant, currency, debtor and payment terms allow card collection.
 */
function stripe_invoice_collectable($inv) {
	global $db;
	if (!$db || $db !== stripeConfigDb()) return false;
	$o = $inv['o'];
	if (($o['art'] ?? '') !== 'DO' || !empty($inv['is_credit'])) return false;
	if ((strtoupper(trim((string)($o['valuta'] ?? ''))) ?: 'DKK') !== 'DKK') return false;
	if (in_array(strtolower(trim((string)($o['betalingsbet'] ?? ''))), array('kontant', 'kreditkort'), true)) return false;
	if (strtoupper(trim((string)($o['pbs'] ?? ''))) === 'BS') return false;
	$kontoId = (int)($o['konto_id'] ?? 0);
	if (!$kontoId) return false;
	$d = db_fetch_array(stripe_payment_select("select pbs_nr, stripe_fravalg from adresser where id = $kontoId"));
	if (!$d || (float)$d['pbs_nr'] > 0 || stripe_bool($d['stripe_fravalg'])) return false;
	$subscriber = db_fetch_array(stripe_payment_select("select id from stripe_customers where konto_id = $kontoId
		and coalesce(stripe_subscription_id, '') <> '' and status in ('active', 'trialing', 'past_due') limit 1"));
	return !$subscriber;
}

// ---------------------------------------------------------------- loading an invoice
if (!function_exists('stripe_invoice_load')) {
	/** @return string Stored due date when supplied, otherwise invoice date plus calendar days. */
	function stripe_invoice_due($o) {
		$stored = substr((string)($o['forfaldsdate'] ?? ''), 0, 10);
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $stored)) return $stored;
		$date = substr((string)($o['fakturadate'] ?? ''), 0, 10);
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return '';
		return stripe_date_add($date, (int)($o['betalingsdage'] ?? 0));
	}
	function stripe_invoice_total_ore($o) {
		return (int)round(((float)$o['sum'] + (float)$o['moms']) * 100);
	}
	/**
	 * Load an issued invoice from the current ledger.
	 *
	 * @param int $ordreId Saldi order id.
	 * @param string|null $today Optional YYYY-MM-DD date for state evaluation.
	 * @return array{
	 *   o: array<string, mixed>, id: int, is_credit: bool, nr: string,
	 *   total_ore: int, due: string, currency: string,
	 *   lines: array<int, array{varenr: string, tekst: string, antal: float, pris: float, rabat: float, sum: float, is_text: bool}>,
	 *   firma: array<string, mixed>, brand: string, debtor: array<string, mixed>,
	 *   email: string, fornavn: string, no_card: bool, enrolled: bool, recurring: bool,
	 *   paid: array|null, pending: array|null, failed: array|null, payments: array,
	 *   paid_bank: bool, open_count: int, open_rest_ore: int, fully_open: bool, state: string
	 * }|null Invoice and derived state; null if the order is not invoiced.
	 */
	function stripe_invoice_load($ordreId, $today = null) {
		stripe_payments_ensure();
		$o = db_fetch_array(stripe_payment_select("select * from ordrer where id = " . (int)$ordreId, __FILE__ . " linje " . __LINE__));
		if (!$o || !in_array($o['art'], array('DO', 'DK')) || !(int)$o['fakturanr']) return null;
		$inv = array('o' => $o, 'id' => (int)$o['id'], 'is_credit' => ($o['art'] === 'DK'));
		$inv['nr'] = (string)$o['fakturanr'];
		$inv['total_ore'] = stripe_invoice_total_ore($o);
		// Saldi stores the booked due date on openpost, not ordrer. Only a single
		// unambiguous date may override the calendar fallback.
		$due = db_fetch_array(stripe_payment_select("select min(forfaldsdate) as due, count(distinct forfaldsdate) as n
			from openpost where konto_id = " . (int)$o['konto_id'] . " and faktnr = '" . db_escape_string($inv['nr']) . "'"));
		$inv['due'] = stripe_invoice_due(((int)$due['n'] === 1 && !empty($due['due'])) ? array_merge($o, array('forfaldsdate' => $due['due'])) : $o);
		$inv['currency'] = strtoupper(trim((string)$o['valuta'])) ?: 'DKK';
		$inv['lines'] = array();
		$q = stripe_payment_select("select varenr, beskrivelse, antal, pris, rabat, rabatart, procent, enhed, posnr from ordrelinjer where ordre_id = " . (int)$ordreId . " order by posnr, id", __FILE__ . " linje " . __LINE__);
		while ($l = db_fetch_array($q)) {
			$antal = (float)$l['antal']; $pris = (float)$l['pris']; $rabat = (float)$l['rabat'];
			$sum = stripe_invoice_line_sum($l);
			$inv['lines'][] = array('varenr' => trim((string)$l['varenr']), 'tekst' => trim((string)$l['beskrivelse']), 'antal' => $antal, 'pris' => $pris, 'rabat' => $rabat, 'sum' => $sum, 'is_text' => (trim((string)$l['varenr']) === '' && $antal == 0));
		}
		$inv['firma'] = db_fetch_array(stripe_payment_select("select firmanavn, addr1, addr2, postnr, bynavn, land, cvrnr, email, tlf, bank_reg, bank_konto from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__)) ?: array();
		$inv['brand'] = trim((string)($inv['firma']['firmanavn'] ?? '')) ?: 'Saldi';
		if (stripos($inv['brand'], 'saldi') !== false || stripos($inv['brand'], 'danosoft') !== false) $inv['brand'] = 'Saldi';
		$inv['debtor'] = (int)$o['konto_id'] ? (db_fetch_array(stripe_payment_select("select id, kontonr, firmanavn, kontakt, email, stripe_fravalg, stripe, stripe_customer_id, stripe_pm_id, stripe_date, pbs_nr, udskriv_til from adresser where id = " . (int)$o['konto_id'], __FILE__ . " linje " . __LINE__)) ?: array()) : array();
		$inv['email'] = trim((string)$o['email']) ?: trim((string)($inv['debtor']['email'] ?? ''));
		// Greet the contact person by first name; a contact field holding the company name is not a person.
		$kontakt = trim((string)$o['kontakt']) ?: trim((string)($inv['debtor']['kontakt'] ?? ''));
		if (strcasecmp($kontakt, trim((string)$o['firmanavn'])) === 0) $kontakt = '';
		$inv['fornavn'] = trim((string)explode(' ', $kontakt)[0]);
		$inv['no_card'] = stripe_bool($inv['debtor']['stripe_fravalg'] ?? '') || (($inv['debtor']['stripe_fravalg'] ?? '') === 'on');
		$inv['enrolled'] = (($inv['debtor']['stripe'] ?? '') === 'on') && !empty($inv['debtor']['stripe_pm_id']);
		$inv['recurring'] = trim((string)$o['nextfakt']) !== '' && $o['art'] === 'DO' && !$inv['enrolled'];
		// payments
		$inv['paid'] = null; $inv['pending'] = null; $inv['failed'] = null; $inv['payments'] = array();
		$recent = stripe_payment_mysql() ? "created_at > CURRENT_TIMESTAMP - INTERVAL 15 MINUTE" : "created_at > CURRENT_TIMESTAMP - interval '15 minutes'";
		$q = stripe_payment_select("select *, ($recent) as recent from stripe_payments where ordre_id = " . (int)$ordreId . " order by id desc", __FILE__ . " linje " . __LINE__);
		while ($p = db_fetch_array($q)) {
			$inv['payments'][] = $p;
			if ($p['status'] === 'succeeded' && !$inv['paid']) $inv['paid'] = $p;
			if (!$inv['pending'] && (in_array($p['status'], array('processing', 'requires_action'), true)
				|| ($p['status'] === 'pending' && stripe_bool($p['recent'])))) $inv['pending'] = $p;
			if ($p['status'] === 'failed' && !$inv['failed']) $inv['failed'] = $p;
		}
		$op = db_fetch_array(stripe_payment_select("select count(*) as n,
			coalesce(sum(case when coalesce(udlignet, '0') <> '1' then amount else 0 end), 0) as rest
			from openpost where faktnr = '" . db_escape_string($inv['nr']) . "' and konto_id = " . (int)$o['konto_id']));
		$inv['open_count'] = (int)$op['n'];
		$inv['open_rest_ore'] = (int)round((float)$op['rest'] * 100);
		$credit = db_fetch_array(stripe_payment_select("select id from openpost where konto_id = " . (int)$o['konto_id']
			. " and coalesce(udlignet, '0') <> '1' and amount < 0 limit 1"));
		$creditOrder = db_fetch_array(stripe_payment_select("select id from ordrer where art = 'DK' and kred_ord_id = " . (int)$ordreId . " limit 1"));
		$inv['fully_open'] = $inv['open_count'] > 0 && $inv['total_ore'] > 0
			&& abs($inv['open_rest_ore'] - $inv['total_ore']) <= 1 && !$credit && !$creditOrder;
		// An invoice-number-less remainder after split settlement cannot be attributed
		// safely here. Only a verified full open amount may enter the card flow.
		$inv['paid_bank'] = !$inv['paid'] && !$inv['is_credit'] && trim((string)$o['betalt']) === 'on';
		$inv['state'] = stripe_invoice_state($inv, $today);
		return $inv;
	}
	/**
	 * Derive the invoice page state without changing payment reconciliation.
	 *
	 * @param array $inv Loaded invoice.
	 * @param string|null $today Optional YYYY-MM-DD clock for deterministic callers.
	 * @return string Invoice page state.
	 */
	function stripe_invoice_state($inv, $today = null) {
		if ($inv['is_credit']) return 'credited';
		if ($inv['paid']) return 'paid';
		if ($inv['paid_bank']) return 'paidbank';
		if ($inv['pending']) return $inv['pending']['status'] === 'requires_action' ? 'requires_action' : 'pending';
		foreach ($inv['payments'] as $payment) {
			if (in_array($payment['status'], array('duplicate_paid', 'refunded', 'disputed', 'unmapped'), true)) return 'manual';
		}
		if (!stripe_invoice_collectable($inv) || empty($inv['fully_open'])) return 'manual';
		if ($inv['no_card'] || !stripe_feature_on('invoice_payments') || stripe_setting('enabled', 'off') !== 'on') return 'nocard';
		if ($inv['failed']) return 'failed';
		if (stripe_feature_on('enrolment') && $inv['enrolled'] && trim((string)$inv['o']['udskriv_til']) === 'Stripe') return 'enrolled'; // only Stripe invoices are charged by the job
		if ($inv['due'] && $inv['due'] < ($today ?? stripe_today())) return 'overdue';
		return $inv['recurring'] && stripe_feature_on('enrolment') ? 'open_recurring' : 'open';
	}
	/**
	 * Gate new invoice collection without preventing sync/webhook reconciliation.
	 *
	 * @param array $inv Loaded invoice; state-only inputs cannot authorize collection.
	 * @return bool Whether a new invoice payment may be started.
	 */
	function stripe_invoice_payable($inv) {
		if (!is_array($inv) || !stripe_invoice_collectable($inv) || empty($inv['fully_open'])) return false;
		$state = stripe_invoice_state($inv);
		return stripe_setting('enabled', 'off') === 'on' && stripe_feature_on('invoice_payments')
			&& in_array($state, array('open', 'open_recurring', 'overdue', 'failed', 'enrolled'));
	}
	/** @return bool Saved-card payment may start only on or after the invoice due date. */
	function stripe_invoice_pay_now_allowed($inv, $today = null) {
		return !empty($inv['enrolled']) && stripe_feature_on('enrolment') && stripe_invoice_payable($inv)
			&& !empty($inv['due']) && $inv['due'] <= ($today ?? stripe_today());
	}
	/**
	 * Date the charge job will collect an enrolled invoice: the next scheduled primary date, never before today.
	 *
	 * @param array $inv Loaded invoice.
	 * @return string YYYY-MM-DD, or '' when no automatic charge is scheduled.
	 */
	function stripe_invoice_charge_date($inv) {
		$next = stripe_auto_next_date($inv);
		if ($next === null || $next === '') return '';
		return max($next, stripe_today());
	}
	/** @return float Net displayed line total, using the invoice form's unit discount and percentage rules. */
	function stripe_invoice_line_sum($line) {
		$price = (float)$line['pris'];
		$quantity = (float)$line['antal'];
		$discount = (float)$line['rabat'];
		$sum = ($line['rabatart'] ?? '') === 'amount'
			? ($price - $discount) * $quantity
			: $price * $quantity - ($price * $quantity * $discount / 100);
		// The form rounds before applying procent and rounds that result again.
		$sum = afrund($sum, 2);
		$percent = $line['procent'] ?? null;
		if ($percent !== null && $percent !== '') {
			$sum = afrund($sum - ($sum * (100 - (float)$percent) / 100), 2);
		}
		return (float)$sum;
	}
	function stripe_invoice_url($ordreId) {
		global $db;
		if (!$db || $db !== stripeConfigDb()) return '';
		$base = rtrim((string)stripe_setting('public_base_url'), '/');
		$sig = stripe_link_sign($ordreId, 'invoice');
		if (!$base || $sig === '') return '';
		return $base . '/api/stripe/faktura.php?o=' . (int)$ordreId . '&s=' . $sig;
	}
}

// ---------------------------------------------------------------- Stripe customer for a debtor
if (!function_exists('stripe_customer_for_debtor')) {
	function stripe_customer_for_debtor($inv) {
		global $db;
		$d = $inv['debtor'];
		if (!$d) return '';
		if (!empty($d['stripe_customer_id'])) return (string)$d['stripe_customer_id'];
		$customerOrder = stripe_payment_mysql() ? 'updated_at desc, id desc' : 'updated_at desc nulls last, id desc';
		$r = db_fetch_array(stripe_payment_select("select stripe_customer_id from stripe_customers where konto_id = " . (int)$d['id'] . " order by $customerOrder limit 1"));
		$cus = $r ? (string)$r['stripe_customer_id'] : '';
		if ($cus === '') {
			$resp = stripeHttpRequest('POST', '/v1/customers', array(
				'name' => (string)$d['firmanavn'], 'email' => $inv['email'],
				'metadata' => array('saldi_konto_id' => (int)$d['id'], 'saldi_kontonr' => (string)$d['kontonr'], 'saldi_db' => (string)$db)),
				array('idempotency_key' => 'cus:' . $db . ':' . (int)$d['id']));
			if (!$resp['ok']) { stripe_log('customer create failed: ' . $resp['error']); return ''; }
			$cus = (string)$resp['body']['id'];
			stripe_payment_modify("insert into stripe_customers (stripe_customer_id, konto_id, kontonr, status, updated_at) values ('" . db_escape_string($cus) . "', " . (int)$d['id'] . ", '" . db_escape_string((string)$d['kontonr']) . "', 'active', CURRENT_TIMESTAMP)", __FILE__ . " linje " . __LINE__);
		}
		stripe_payment_modify("update adresser set stripe_customer_id = '" . db_escape_string($cus) . "' where id = " . (int)$d['id'], __FILE__ . " linje " . __LINE__);
		return $cus;
	}
}

// ---------------------------------------------------------------- PaymentIntent per Saldi invoice
if (!function_exists('stripe_payment_create')) {
	/**
	 * Serialize collection per invoice across page, manual, auto and backup callers.
	 * Re-read invoice/open items under the lock; never trust a caller's stale page state.
	 *
	 * @return array{ok: bool, error?: string, state?: string, client_secret?: string, pi?: string, status?: string, error_code?: string}
	 */
	function stripe_payment_create($inv, $source = 'page', $saveCard = false, $confirmPm = '') {
		$ordreId = (int)$inv['id'];
		$locked = false;
		try {
			stripe_invoice_collection_lock($ordreId);
			$locked = true;
			$inv = stripe_invoice_load($ordreId);
			if (!$inv || !stripe_invoice_collectable($inv)) return array('ok' => false, 'error' => 'not_collectable');
			if (!stripe_feature_on('enrolment') && ($confirmPm !== '' || in_array($source, array('auto', 'backup', 'manual'), true))) {
				return array('ok' => false, 'error' => 'enrolment_disabled');
			}
			if ($source === 'manual' && !stripe_invoice_pay_now_allowed($inv)) return array('ok' => false, 'error' => 'not_due');
			if (stripe_payment_clearing_account() === '') return array('ok' => false, 'error' => 'missing_clearing_account');
			$saveCard = $saveCard && stripe_invoice_save_allowed($inv);
			$error = stripe_attempt_reconcile_caller($inv, $source, $saveCard, $confirmPm);
			if ($error !== '') return array('ok' => false, 'error' => $error);
			$error = stripe_payment_reconcile_invoice($ordreId);
			if ($error !== '') return array('ok' => false, 'error' => $error);
			if (in_array($source, array('auto', 'backup'), true) && !stripe_auto_charge_allowed(stripe_invoice_load($ordreId))) return array('ok' => false, 'error' => 'auto_stopped');
			$inv = stripe_invoice_load($ordreId);
			if (!stripe_invoice_payable($inv) || $inv['total_ore'] <= 0) return array('ok' => false, 'error' => 'not_payable', 'state' => $inv['state']);
			return stripe_payment_create_attempt($inv, $source, $saveCard, $confirmPm);
		} catch (Throwable $e) {
			stripe_log('invoice collection failed: ' . $e->getMessage());
			return array('ok' => false, 'error' => 'reconcile_failed');
		} finally {
			if ($locked) stripe_invoice_collection_lock($ordreId, true);
		}
	}

	/**
	 * Create one reconciled attempt; called only while holding the invoice lock.
	 *
	 * @return array{ok: bool, client_secret?: string, pi?: string, status?: string, error?: string, error_code?: string}
	 */
	function stripe_payment_create_attempt($inv, $source, $saveCard, $confirmPm) {
		$saveCard = $saveCard && stripe_invoice_save_allowed($inv);
		$request = stripe_attempt_prepare($inv, $source, $saveCard, $confirmPm);
		return stripe_attempt_send($inv, $request);
	}
	function stripe_payment_record($inv, $pi, $source, $saveCard, $attempt) {
		$piId = db_escape_string((string)$pi['id']);
		$ex = db_fetch_array(stripe_payment_select("select id from stripe_payments where payment_intent_id = '$piId'", __FILE__ . " linje " . __LINE__));
		if ($ex) return (int)$ex['id'];
		$status = in_array(($pi['status'] ?? ''), array('processing', 'requires_action'), true) ? $pi['status'] : 'pending'; // Captures/failures are applied separately.
		stripe_payment_modify("insert into stripe_payments (ordre_id, fakturanr, stripe_customer_id, payment_intent_id, amount_ore, currency, status, source, save_card, attempt, attempt_date) values ("
			. (int)$inv['id'] . ", '" . db_escape_string($inv['nr']) . "', '" . db_escape_string((string)($pi['customer'] ?? '')) . "', '$piId', " . (int)$inv['total_ore'] . ", '"
			. db_escape_string(strtoupper((string)$inv['currency'])) . "', '$status', '" . db_escape_string($source) . "', " . ($saveCard ? 'true' : 'false') . ", " . (int)$attempt . ", '" . db_escape_string(stripe_today()) . "')", __FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(stripe_payment_select("select id from stripe_payments where payment_intent_id = '$piId'", __FILE__ . " linje " . __LINE__));
		if (!$r) throw new RuntimeException('Payment record insert returned no row');
		return (int)$r['id'];
	}
	function stripe_pi_card($pi) {
		$brand = ''; $last4 = ''; $pm = '';
		if (!empty($pi['payment_method'])) $pm = is_array($pi['payment_method']) ? (string)$pi['payment_method']['id'] : (string)$pi['payment_method'];
		$ch = null;
		if (!empty($pi['latest_charge']) && is_array($pi['latest_charge'])) $ch = $pi['latest_charge'];
		elseif (!empty($pi['charges']['data'][0])) $ch = $pi['charges']['data'][0];
		if ($ch && !empty($ch['payment_method_details']['card'])) { $brand = (string)$ch['payment_method_details']['card']['brand']; $last4 = (string)$ch['payment_method_details']['card']['last4']; }
		if (($brand === '' || $last4 === '') && $pm !== '') {
			$resp = stripeHttpRequest('GET', '/v1/payment_methods/' . rawurlencode($pm));
			if ($resp['ok'] && !empty($resp['body']['card'])) { $brand = (string)$resp['body']['card']['brand']; $last4 = (string)$resp['body']['card']['last4']; }
		}
		return array($pm, $brand, $last4);
	}
	/**
	 * Claim a captured PaymentIntent once; a durable claim remains repairable if booking fails.
	 *
	 * @return string applied|duplicate|duplicate_paid|unknown|unmapped|database_error|delivery_error.
	 */
	function stripe_payment_apply_succeeded($pi, $eventId = '') {
		global $db;
		$lockedOrder = 0;
		try {
			$orderToLock = (int)($pi['metadata']['saldi_ordre_id'] ?? 0);
			if ($orderToLock) {
				stripe_invoice_collection_lock($orderToLock);
				$lockedOrder = $orderToLock;
			}
			stripe_payments_ensure();
			$piId = db_escape_string((string)$pi['id']);
			$ordreId = (int)($pi['metadata']['saldi_ordre_id'] ?? 0);
			$row = db_fetch_array(stripe_payment_select("select * from stripe_payments where payment_intent_id = '$piId'"));
			if (!$row && $ordreId) {
				$inv = stripe_invoice_load($ordreId);
				if (!$inv) return 'unknown';
				stripe_payment_record($inv, $pi, (string)($pi['metadata']['saldi_source'] ?? 'page'), !empty($pi['setup_future_usage']), (int)($pi['metadata']['saldi_primary_attempt'] ?? 1));
				$row = db_fetch_array(stripe_payment_select("select * from stripe_payments where payment_intent_id = '$piId'"));
			}
			if (!$row) return 'unknown';
			$inv = stripe_invoice_load((int)$row['ordre_id']);
			if (!$inv || (string)($pi['metadata']['saldi_db'] ?? '') !== (string)$db
				|| $ordreId !== (int)$row['ordre_id']
				|| strtolower((string)($pi['currency'] ?? '')) !== strtolower((string)$row['currency'])
				|| strtolower((string)$row['currency']) !== strtolower($inv['currency'])
				|| !isset($pi['amount_received']) || (int)$pi['amount_received'] !== (int)$row['amount_ore']
				|| (int)$row['amount_ore'] !== (int)$inv['total_ore']) {
				stripe_payment_modify("update stripe_payments set status = 'unmapped', last_error = 'payment_identity_mismatch', last_event_id = '"
					. db_escape_string($eventId) . "' where id = " . (int)$row['id'] . " and status not in ('succeeded', 'duplicate_paid', 'refunded', 'disputed', 'unmapped')");
				stripeAlertMail('Stripe-betaling matcher ikke Saldi-faktura',
					"PaymentIntent: " . (string)$pi['id'] . "\nFaktura: " . $row['fakturanr'] . "\nTenant, valuta eller modtaget beløb matcher ikke. Afstem betalingen manuelt.",
					'unmapped_pi_' . (string)$pi['id'], 3600);
				return 'unmapped';
			}
			if ($row['status'] === 'duplicate_paid') {
				return stripe_payment_complete((int)$row['id']);
			}
			if (in_array($row['status'], array('succeeded', 'refunded', 'disputed', 'unmapped'), true)) return 'duplicate';
			list($pm, $brand, $last4) = stripe_pi_card($pi);
			$set = "status = 'succeeded', succeeded_at = CURRENT_TIMESTAMP, pm_id = '" . db_escape_string($pm)
				. "', pm_brand = '" . db_escape_string($brand) . "', pm_last4 = '" . db_escape_string($last4)
				. "', last_event_id = '" . db_escape_string($eventId) . "', last_error = null";
			return stripe_payment_complete((int)$row['id'], $set);
		} catch (Throwable $e) {
			stripe_log('payment success failed: ' . $e->getMessage());
			return 'database_error';
		} finally {
			if ($lockedOrder) stripe_invoice_collection_lock($lockedOrder, true);
		}
	}

	/** @return string Durable transition; automatic follow-up runs outside reconciliation. */
	function stripe_payment_apply_failed($pi, $eventId = '') {
		try {
			$kind = stripe_payment_error_kind($pi);
			if ($kind === 'action') return stripe_payment_require_action($pi, $eventId);
			$row = db_fetch_array(stripe_payment_select("select * from stripe_payments where payment_intent_id = '" . db_escape_string((string)$pi['id']) . "'"));
			if (!$row || in_array($row['status'], array('succeeded', 'duplicate_paid', 'refunded', 'disputed', 'unmapped', 'canceled'), true)) return 'ignored';
			if ($row['status'] === 'failed') return 'duplicate';
			$error = $pi['last_payment_error'] ?? array();
			$err = (string)(($error['decline_code'] ?? '') ?: ($error['code'] ?? 'technical_error'));
			$method = $error['payment_method'] ?? ($pi['payment_method'] ?? array());
			$pm = is_array($method) ? (string)($method['id'] ?? '') : (string)$method;
			$card = is_array($method) ? ($method['card'] ?? array()) : array();
			if (!$card && $pm !== '') {
				list($pm, $brand, $last4) = stripe_pi_card($pi);
				$card = array('brand' => $brand, 'last4' => $last4);
			}
			$status = $kind === 'decline' ? 'failed' : 'technical_error';
			$claimed = stripe_payment_update_returning("status = '$status', last_error = '" . db_escape_string(substr($err, 0, 200))
				. "', pm_id = '" . db_escape_string($pm) . "', pm_brand = '" . db_escape_string((string)($card['brand'] ?? ''))
				. "', pm_last4 = '" . db_escape_string((string)($card['last4'] ?? '')) . "', last_event_id = '" . db_escape_string($eventId) . "'",
				"id = " . (int)$row['id'] . " and status not in ('succeeded', 'failed', 'duplicate_paid', 'refunded', 'disputed', 'unmapped', 'canceled')");
			return $claimed ? 'applied' : 'duplicate';
		} catch (Throwable $e) {
			stripe_log('payment failure update failed: ' . $e->getMessage());
			return 'database_error';
		}
	}

	// Used by faktura_state.php: when the browser returned from Stripe but the webhook has not
	// arrived yet, ask Stripe directly (same handler, idempotent).
	function stripe_payment_sync($piId) {
		$resp = stripeHttpRequest('GET', '/v1/payment_intents/' . rawurlencode($piId), array('expand' => array('latest_charge')));
		if (!$resp['ok']) return 'error';
		return stripe_payment_apply_status($resp['body']);
	}
}

// ---------------------------------------------------------------- journal line (debit card clearing, credit debtor)
if (!function_exists('stripe_journal_add_payment')) {
	/** @return int Inserted journal line id; throws on an incomplete booking. */
	function stripe_journal_add_payment($inv, $payment) {
		return stripe_payment_journal_insert($inv, $payment);
	}

}

// ---------------------------------------------------------------- mails (fixed templates)
if (!function_exists('stripe_mail_sender')) {
	/**
	 * Resolve the sender and archive copies using the classic mail settings.
	 *
	 * @param int $employeeId Current employee (ansat_id), if one is selected.
	 * @return array{from: string, name: string, smtp: string, user: string, pwd: string, enc: string, archive: string, employee: string}
	 */
	function stripe_mail_sender($employeeId = 0) {
		$s = db_fetch_array(db_select("select firmanavn, email, mailfakt, felt_1, felt_2, felt_3, felt_4 from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__)) ?: array();
		$name = ''; $from = '';
		$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'email_settings' and var_name = 'sender_email' order by group_id limit 1", __FILE__ . " linje " . __LINE__));
		if ($r && trim((string)$r['var_value']) !== '') $from = trim((string)$r['var_value']);
		$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'email_settings' and var_name = 'sender_name' order by group_id limit 1", __FILE__ . " linje " . __LINE__));
		if ($r && trim((string)$r['var_value']) !== '') $name = trim((string)$r['var_value']);
		if ($from === '') $from = trim((string)($s['email'] ?? ''));
		if ($name === '') $name = trim((string)($s['firmanavn'] ?? 'Saldi'));
		$archive = $from;
		$senders = stripe_mail_recipients($from);
		if ($senders) {
			$from = $senders[0];
		}
		$employee = '';
		if (!empty($s['mailfakt']) && (int)$employeeId > 0) {
			$r = db_fetch_array(db_select("select email from ansatte where id = " . (int)$employeeId, __FILE__ . " linje " . __LINE__));
			$employee = trim((string)($r['email'] ?? ''));
		}
		return array('from' => $from, 'name' => $name, 'smtp' => trim((string)($s['felt_1'] ?? '')), 'user' => trim((string)($s['felt_2'] ?? '')), 'pwd' => (string)($s['felt_3'] ?? ''), 'enc' => trim((string)($s['felt_4'] ?? '')), 'archive' => $archive, 'employee' => $employee);
	}
}
if (!function_exists('stripe_mail_send')) {
	/** @return bool Whether recipient validation and mail delivery succeeded. */
	function stripe_mail_send($to, $subject, $html, $text, $attachments = array()) {
		try {
			$snd = stripe_mail_sender((int)($GLOBALS['ansat_id'] ?? 0));
			$envelope = stripe_mail_envelope($to, $snd);
			if (!$envelope) {
				stripe_log('mail: invalid recipient or archive address');
				return false;
			}
			// PHPMailer via composer: the map's own vendor/ or the shared one one level up (sendMail.php uses ../../vendor from debitor/)
			$autoload = '';
			foreach (array(dirname(__DIR__, 2) . '/vendor/autoload.php', dirname(__DIR__, 3) . '/vendor/autoload.php') as $cand) {
				if (!is_file($cand)) continue;
				require_once $cand;
				if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) { $autoload = $cand; break; }
			}
			if ($autoload === '') { stripe_log('mail: PHPMailer not found via vendor/autoload.php'); return false; }
			$mail = new PHPMailer\PHPMailer\PHPMailer();
			$mail->CharSet = 'UTF-8';
			$mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));
			$from = $snd['from'];
			if ($snd['smtp'] !== '' && $snd['smtp'] !== 'localhost') {
				$mail->IsSMTP(); $mail->Host = $snd['smtp']; $mail->Timeout = 10;
				if ($snd['user'] !== '') { $mail->SMTPAuth = true; $mail->Username = $snd['user']; $mail->Password = $snd['pwd']; if ($snd['enc'] !== '') $mail->SMTPSecure = $snd['enc']; }
			} else {
				$mail->IsMail();
				if (isset($_SERVER['SERVER_NAME']) && strpos((string)$_SERVER['SERVER_NAME'], 'saldi.dk') !== false) $from = $GLOBALS['db'] . '@' . $_SERVER['SERVER_NAME'];
			}
			if (!filter_var($from, FILTER_VALIDATE_EMAIL)) $from = 'noreply@saldi.dk';
			$mail->From = $from; $mail->FromName = $snd['name'];
			if (filter_var($snd['from'], FILTER_VALIDATE_EMAIL)) { $mail->AddReplyTo($snd['from'], $snd['name']); $mail->Sender = $snd['from']; }
			stripe_mail_apply_envelope($mail, $envelope);
			foreach ($attachments as $attachment) $mail->AddAttachment($attachment);
			$mail->Subject = $subject;
			$mail->IsHTML(true);
			$mail->Body = $html;
			$mail->AltBody = $text;
			if (!$mail->Send()) { stripe_log('mail: send failed to ' . $to . ': ' . $mail->ErrorInfo); return false; }
			return true;
		} catch (Throwable $e) {
			stripe_log('mail: ' . $e->getMessage());
			return false;
		}
	}
}
if (!function_exists('stripe_mail_layout')) {
	// Fixed template (design_fakturamail.png). $rows = [[label, value], ...]; $box = ['kind'=>good|bad,'title'=>..,'sub'=>..] or null.
	function stripe_mail_layout($inv, $label, $h1, $bodyText, $box, $rows, $btnText, $btnUrl, $btn2Text, $btn2Url, $noteHtml) {
		$f = $inv['firma'];
		$footer = stripe_h(trim((string)($f['firmanavn'] ?? ''))) . ' · CVR ' . stripe_h($f['cvrnr'] ?? '') . ' · ' . stripe_h(trim(($f['addr1'] ?? '') . ', ' . ($f['postnr'] ?? '') . ' ' . ($f['bynavn'] ?? ''), ' ,'));
		$h = '<!doctype html><html lang="da"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . stripe_h($h1) . '</title></head>';
		$h .= '<body style="margin:0;padding:40px 16px;background:#f7f5f0;font-family:Inter,Segoe UI,Helvetica,Arial,sans-serif;color:#111318;font-size:16px;line-height:1.6">';
		$h .= '<div style="max-width:600px;margin:0 auto;background:#fff;border:1px solid #e6e2da;border-radius:16px;padding:44px 44px 40px">';
		// 20261025 CL/ASR Saldi's own invoices show the Saldi logo (public image, alt text when images are blocked)
		$base = rtrim((string)stripe_setting('public_base_url'), '/');
		$brandHtml = ($inv['brand'] === 'Saldi' && $base !== '')
			? '<img src="' . stripe_h($base . '/img/SaldiMainLogo.png') . '" width="128" alt="Saldi" style="display:block;width:128px;height:auto;border:0">'
			: stripe_h($inv['brand']);
		$h .= '<div style="font-size:26px;font-weight:700;letter-spacing:-.02em;margin-bottom:32px">' . $brandHtml . '</div>';
		$h .= '<div style="font-size:12px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#9a5b1a;margin-bottom:12px">' . stripe_h($label) . '</div>';
		$h .= '<h1 style="font-size:32px;line-height:1.15;font-weight:700;margin:0 0 14px;letter-spacing:-.01em">' . stripe_h($h1) . '</h1>';
		// A blank line separates paragraphs (greeting, then text); the text version keeps the same breaks.
		foreach (preg_split('/\n{2,}/', (string)$bodyText) as $i => $para) {
			$h .= '<p style="margin:' . ($i ? '12px' : '0') . ' 0 0;color:#4b5563">' . nl2br(stripe_h($para)) . '</p>';
		}
		if ($box) {
			$bg = $box['kind'] === 'bad' ? '#fdeee8' : '#e9f7ee'; $fg = $box['kind'] === 'bad' ? '#c2410c' : '#15803d';
			$h .= '<div style="margin:26px 0 6px;padding:16px 18px;border-radius:12px;background:' . $bg . '"><div style="font-weight:600;color:#111318">' . stripe_h($box['title']) . '</div><div style="font-size:14px;color:#4b5563">' . stripe_h($box['sub']) . '</div></div>';
		}
		if ($rows) {
			$h .= '<table role="presentation" style="width:100%;border-collapse:collapse;margin:28px 0;border-top:1px solid #e6e2da">';
			foreach ($rows as $r) $h .= '<tr><td style="padding:14px 0;border-bottom:1px solid #e6e2da;color:#4b5563">' . stripe_h($r[0]) . '</td><td style="padding:14px 0;border-bottom:1px solid #e6e2da;text-align:right;font-weight:600">' . stripe_h($r[1]) . '</td></tr>';
			$h .= '</table>';
		}
		if ($btnText && $btnUrl) $h .= '<a href="' . stripe_h($btnUrl) . '" style="display:block;text-align:center;color:#fff;text-decoration:none;font-weight:700;font-size:18px;padding:18px;border-radius:12px;background:#1b68bd;background-image:linear-gradient(90deg,#1b68bd,#8b33d4)">' . stripe_h($btnText) . '</a>';
		if ($btn2Text && $btn2Url) $h .= '<a href="' . stripe_h($btn2Url) . '" style="display:block;text-align:center;color:#111318;text-decoration:none;font-weight:600;font-size:16px;padding:14px;border-radius:12px;border:1px solid #e6e2da;margin-top:12px">' . stripe_h($btn2Text) . '</a>';
		if ($noteHtml) $h .= '<p style="margin:26px 0 0;color:#4b5563;font-size:15px">' . $noteHtml . '</p>';
		$h .= '</div><p style="max-width:600px;margin:24px auto 0;color:#6b7280;font-size:13px">' . $footer . '</p></body></html>';
		return $h;
	}
	function stripe_mail_text_version($h1, $bodyText, $rows, $btnText, $btnUrl, $noteText) {
		$t = $h1 . "\n\n" . $bodyText . "\n\n";
		foreach ((array)$rows as $r) $t .= $r[0] . ': ' . $r[1] . "\n";
		if ($btnText && $btnUrl) $t .= "\n" . $btnText . ": " . $btnUrl . "\n";
		if ($noteText) $t .= "\n" . $noteText . "\n";
		return $t;
	}
	function stripe_mail_invoice_link($inv) {
		if (!stripe_feature_on('invoice_payments') || !stripe_invoice_collectable($inv)) return false;
		$url = stripe_invoice_url($inv['id']);
		if ($url === '' || $inv['email'] === '') return false;
		$month = stripe_month_name($inv['o']['fakturadate']);
		$h1 = ($inv['recurring'] && $month) ? stripe_txt('mail_h_new_month', array('month' => $month)) : stripe_txt('mail_h_new');
		$body = stripe_invoice_link_text();
		if ($body === '') {
			$body = stripe_mail_greeting($inv) . "\n\n" . stripe_txt('mail_body_new') . ($inv['recurring'] && stripe_feature_on('enrolment') ? stripe_txt('mail_body_new_rec') : '');
		} elseif ($inv['fornavn'] !== '') {
			$body = str_replace(array('[Fornavn]', '[fornavn]', '$fornavn'), $inv['fornavn'], $body);
		} else {
			// "Hej [Fornavn], ..." becomes "Hej, ..." without a contact name.
			$body = preg_replace('/\s*(\[Fornavn\]|\[fornavn\]|\$fornavn)/', '', $body);
		}
		$rows = array(array(stripe_txt('amount_lbl'), stripe_fmt_kr($inv['total_ore']) . ' kr.'), array(stripe_txt('due_date'), stripe_fmt_date($inv['due'])));
		$note = stripe_txt('mail_note_new', array('url' => '<a href="' . stripe_h($url) . '" style="color:#1b68bd">' . stripe_h($url) . '</a>'));
		$html = stripe_mail_layout($inv, stripe_txt('mail_label_invoice', array('nr' => $inv['nr'])), $h1, $body, null, $rows, stripe_txt('mail_btn_new'), $url, '', '', $note);
		$text = stripe_mail_text_version($h1, $body, $rows, stripe_txt('mail_btn_new'), $url, strip_tags(stripe_txt('mail_note_new', array('url' => $url))));
		$ok = stripe_mail_send($inv['email'], stripe_txt('mail_subj_new', array('nr' => $inv['nr'], 'brand' => $inv['brand'])), $html, $text);
		if ($ok && function_exists('audit_log_write') && function_exists('tbl_exists')) audit_log_write('stripe.invoice_link_mailed', 'ordre', (string)$inv['id'], json_encode(array('to' => $inv['email'], 'nr' => $inv['nr'])), 'stripe');
		return $ok;
	}
	function stripe_mail_confirmation($inv, $payment) {
		if ($inv['email'] === '') return false;
		$url = stripe_invoice_url($inv['id']);
		// vis=1: the button opens the PDF in the browser instead of a silent download (20261025)
		$pdfUrl = $url ? str_replace('/faktura.php?', '/faktura_pdf.php?', $url) . '&vis=1' : '';
		$card = stripe_card_label($payment['pm_brand'], $payment['pm_last4']);
		$when = stripe_fmt_date_long(substr((string)$payment['succeeded_at'], 0, 10) ?: date('Y-m-d'));
		$box = array('kind' => 'good', 'title' => stripe_txt('mail_paid_box', array('amount' => stripe_fmt_kr($payment['amount_ore']) . ' kr.', 'date' => $when)), 'sub' => $card . ' · ' . stripe_txt($payment['source'] === 'auto' ? 'auto_payment' : 'card_payment'));
		$rows = array(array(stripe_txt('invoice'), $inv['nr']));
		$month = stripe_month_name($inv['o']['fakturadate']); if ($inv['recurring'] && $month) $rows[] = array(stripe_txt('period'), ucfirst($month));
		if ($inv['recurring'] && trim((string)$inv['o']['nextfakt']) !== '') $rows[] = array(stripe_txt('next_invoice'), stripe_fmt_date_long(substr((string)$inv['o']['nextfakt'], 0, 10)));
		$body = stripe_mail_greeting($inv) . "\n\n" . stripe_txt('mail_body_paid', array('nr' => $inv['nr']));
		$html = stripe_mail_layout($inv, stripe_txt('mail_label_invoice', array('nr' => $inv['nr'])), stripe_txt('mail_h_paid'), $body, $box, $rows, stripe_txt('mail_btn_pdf'), $pdfUrl, '', '', '');
		$text = stripe_mail_text_version(stripe_txt('mail_h_paid'), $body . "\n" . $box['title'] . ' (' . $box['sub'] . ')', $rows, stripe_txt('mail_btn_pdf'), $pdfUrl, '');
		return stripe_mail_send($inv['email'], stripe_txt('mail_subj_paid', array('nr' => $inv['nr'])), $html, $text);
	}
	/**
	 * R18 pre-charge notice: an enrolled debtor still receives every invoice, with the card and
	 * the date the charge job will collect it. Sent at issue instead of the classic/link mail.
	 *
	 * @param array $inv Loaded invoice; $inv['email'] holds the queued recipients.
	 * @return bool Whether the notice was sent (false lets the caller fall back to the PDF mail).
	 */
	function stripe_mail_invoice_notice($inv) {
		if (!stripe_feature_on('invoice_payments') || !stripe_feature_on('enrolment') || stripe_setting('enabled', 'off') !== 'on') return false;
		if (!stripe_auto_charge_allowed($inv) || !stripe_invoice_payable($inv) || trim((string)$inv['email']) === '') return false;
		$url = stripe_invoice_url($inv['id']);
		$charge = stripe_invoice_charge_date($inv);
		if ($url === '' || $charge === '') return false;
		include_once(__DIR__ . '/stripeStaff.php');
		$card = stripe_staff_saved_card($inv['debtor']);
		$date = stripe_fmt_date_long($charge);
		$month = stripe_month_name($inv['o']['fakturadate']);
		$h1 = (trim((string)$inv['o']['nextfakt']) !== '' && $month) ? stripe_txt('mail_h_new_month', array('month' => $month)) : stripe_txt('mail_h_new');
		$body = stripe_mail_greeting($inv) . "\n\n" . stripe_txt('mail_body_notice', array('date' => $date));
		$rows = array(
			array(stripe_txt('amount_lbl'), stripe_fmt_kr($inv['total_ore']) . ' kr.'),
			array(stripe_txt('card_lbl'), $card !== '' ? $card : stripe_txt('mail_card_saved')),
			array(stripe_txt('charge_date_lbl'), $date),
		);
		$note = stripe_txt('mail_note_notice', array('kontakt' => stripe_contact_phrase($inv), 'date' => $date));
		$html = stripe_mail_layout($inv, stripe_txt('mail_label_invoice', array('nr' => $inv['nr'])), $h1, $body, null, $rows, stripe_txt('mail_btn_notice'), $url, '', '', stripe_h($note));
		$text = stripe_mail_text_version($h1, $body, $rows, stripe_txt('mail_btn_notice'), $url, $note);
		$ok = stripe_mail_send($inv['email'], stripe_txt('mail_subj_notice', array('nr' => $inv['nr'], 'brand' => $inv['brand'], 'date' => $date)), $html, $text);
		if ($ok && function_exists('audit_log_write') && function_exists('tbl_exists')) audit_log_write('stripe.invoice_notice_mailed', 'ordre', (string)$inv['id'], json_encode(array('to' => $inv['email'], 'nr' => $inv['nr'], 'charge' => $charge)), 'stripe');
		return $ok;
	}
	function stripe_mail_failed($inv, $payment) {
		if ($inv['email'] === '') return false;
		$url = stripe_invoice_url($inv['id']);
		$card = stripe_card_label($payment['pm_brand'], $payment['pm_last4']);
		$when = stripe_fmt_date_long(stripe_attempt_date($payment));
		$box = array('kind' => 'bad', 'title' => stripe_txt('mail_failed_box', array('card' => $card, 'date' => $when)), 'sub' => stripe_decline_text($payment['last_error'] ?? ''));
		$next = stripe_auto_next_date($inv);
		$retry = $next ? stripe_fmt_date_long($next) : '';
		$rows = array(array(stripe_txt('amount_lbl'), stripe_fmt_kr($payment['amount_ore']) . ' kr.'), array(stripe_txt('due_date'), stripe_fmt_date($inv['due'])));
		$body = stripe_mail_greeting($inv) . "\n\n" . stripe_txt(($payment['source'] ?? '') === 'backup' ? 'mail_body_failed_backup' : 'mail_body_failed', array('nr' => $inv['nr']));
		// Same schedule as the job (S3): retry dates while primary attempts remain, a separate text after the last one.
		$noteText = ($next === null || $retry === '') ? stripe_txt('mail_note_failed_last') : stripe_txt('mail_note_failed', array('retry' => $retry));
		$html = stripe_mail_layout($inv, stripe_txt('mail_label_invoice', array('nr' => $inv['nr'])), stripe_txt('mail_h_failed'), $body, $box, $rows, stripe_txt('mail_btn_failed'), $url, '', '', stripe_h($noteText));
		$text = stripe_mail_text_version(stripe_txt('mail_h_failed'), $body . "\n" . $box['title'] . ($box['sub'] !== '' ? "\n" . $box['sub'] : ''), $rows, stripe_txt('mail_btn_failed'), $url, $noteText);
		return stripe_mail_send($inv['email'], stripe_txt('mail_subj_failed', array('nr' => $inv['nr'])), $html, $text);
	}
}

// ---------------------------------------------------------------- webhook entry (payment_intent.*)
if (!function_exists('stripe_webhook_payment_intent')) {
	function stripe_webhook_payment_intent($eventType, $pi, $eventId) {
		if ($eventType === 'payment_intent.succeeded') return stripe_payment_apply_succeeded($pi, $eventId);
		if ($eventType === 'payment_intent.payment_failed') {
			$result = stripe_payment_apply_failed($pi, $eventId);
			if (in_array($result, array('applied', 'duplicate', 'ignored'), true)) {
				$outcome = stripe_auto_failure_followup((int)($pi['metadata']['saldi_ordre_id'] ?? 0));
				if ($outcome === 'error') return 'delivery_error';
			}
			return $result;
		}
		if ($eventType === 'payment_intent.requires_action') return stripe_payment_require_action($pi, $eventId);
		if ($eventType === 'payment_intent.canceled') {
			$piId = db_escape_string((string)$pi['id']);
			stripe_payment_modify("update stripe_payments set status = 'canceled', last_event_id = '" . db_escape_string($eventId) . "' where payment_intent_id = '$piId' and status not in ('succeeded', 'duplicate_paid', 'refunded', 'disputed', 'unmapped')");
			return 'applied';
		}
		return 'ignored';
	}
}
