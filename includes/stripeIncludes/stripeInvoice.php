<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/stripeInvoice.php --- 2026.10.19
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
// Copyright (c) 2003-2026 Saldi.dk ApS
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

include_once(__DIR__ . '/stripeSettings.php');
include_once(__DIR__ . '/stripeLink.php');
include_once(__DIR__ . '/stripeHttp.php');
include_once(__DIR__ . '/stripeAlertMail.php');

// ---------------------------------------------------------------- texts (Danish v1; ids reserved for tekster.csv later)
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
			'bank_line'         => 'Betal med kort i panelet, eller via bankoverførsel til reg. %reg konto %konto med fakturanummeret %nr som reference.',
			'bank_line_nocard'  => 'Betal via bankoverførsel til reg. %reg konto %konto med fakturanummeret %nr som reference.',
			'due_pill'          => 'Forfalder %date',
			'overdue_pill'      => 'Forfaldt %date',
			'paid_pill'         => 'Betalt %date',
			'credited_pill'     => 'Krediteret %date',
			'lab_payment'       => 'Betaling',
			'h_open'            => 'Betal fakturaen',
			'lead_open'         => 'Betal med kort, Apple Pay eller Google Pay. Du kan også overføre beløbet via bank.',
			'h_recurring'       => 'Betal og slå automatisk betaling til',
			'lead_recurring'    => 'Betal fakturaen med kort, og gem kortet, så dine næste fakturaer fra %brand betales af sig selv.',
			'h_enrolled'        => 'Trækkes automatisk',
			'lead_enrolled'     => 'Du er tilmeldt automatisk betaling. Fakturaen trækkes på forfaldsdatoen, og du får en kvittering på mail.',
			'enrolled_on'       => 'Trækkes den %date',
			'h_pending'         => 'Betalingen behandles',
			'lead_pending'      => 'Vi venter på bekræftelse fra Stripe. Det tager normalt få sekunder.',
			'pending_box'       => 'Behandles …',
			'pending_sub'       => 'Siden opdaterer sig selv. Får du ingen bekræftelse inden for et minut, sender vi kvitteringen på mail, når betalingen er gennemført.',
			'h_paid'            => 'Tak for din betaling',
			'lead_paid'         => 'Fakturaen er betalt. Kvitteringen er sendt til %email.',
			'paid_box'          => 'Betalt %date',
			'h_paidbank'        => 'Fakturaen er betalt',
			'lead_paidbank'     => 'Betalingen er registreret.',
			'h_overdue'         => 'Fakturaen er forfalden',
			'lead_overdue'      => 'Forfaldsdatoen var %date. Betal nu for at undgå rykker.',
			'h_failed'          => 'Betalingen blev afvist',
			'lead_failed'       => 'Vi kunne ikke trække beløbet på dit gemte kort. Betal med et andet kort, og vælg om det skal bruges fremover.',
			'failed_box'        => '%card blev afvist den %date',
			'h_nocard'          => 'Betal via bankoverførsel',
			'lead_nocard'       => 'Kortbetaling er ikke slået til for jeres aftale. Overfør beløbet med fakturanummeret som reference.',
			'h_credited'        => 'Beløbet er krediteret',
			'lead_credited'     => 'Kreditnotaen modregnes i din næste faktura.',
			'to_pay'            => 'At betale',
			'amount_lbl'        => 'Beløb',
			'pay_btn'           => 'Betal %amount',
			'pay_save_btn'      => 'Betal %amount og gem kortet',
			'pay_now_btn'       => 'Betal %amount nu',
			'chk_title'         => 'Betal fremtidige fakturaer automatisk',
			'chk_sub'           => 'Kortet trækkes på forfaldsdatoen. Du får stadig besked, når en faktura er klar, og kan fjerne kortet når som helst i Saldi.',
			'chk_replace_title' => 'Brug dette kort fremover',
			'chk_replace_sub'   => 'Erstatter %card til dine næste fakturaer.',
			'trust'             => 'Sikker betaling via Stripe. Kortoplysningerne går direkte til Stripe og gemmes aldrig hos %brand. Kvittering sendes til %email.',
			'manage_hint'       => 'Vil du skifte kort eller afmelde automatisk betaling? Det gør du i Saldi under Indstillinger › Abonnement & fakturering.',
			'find_in_saldi'     => 'Du finder også fakturaen i Saldi under Indstillinger › Abonnement & fakturering, hvor du kan lægge den direkte i bilagspuljen.',
			'questions'         => 'Spørgsmål til fakturaen? Kontakt os på %email%phone.',
			'bank_box_title'    => 'Betal via bankoverførsel',
			'bank_box_ref'      => 'Reference: %nr',
			'bank_box_note'     => 'Betalingen registreres, når vi har modtaget den.',
			'card_saved_row'    => 'Kortet er gemt til fremtidige fakturaer',
			'already_paid'      => 'Fakturaen er allerede betalt.',
			'pay_in_progress'   => 'En betaling er allerede i gang. Vent et øjeblik, og opdater siden.',
			'link_invalid'      => 'Linket er ikke gyldigt',
			'link_invalid_txt'  => 'Fakturaen kunne ikke findes ud fra linket. Åbn linket fra den seneste mail, eller kontakt os.',
			'not_active'        => 'Online betaling er ikke slået til',
			'not_active_txt'    => 'Fakturaen er stadig gyldig og kan betales via bankoverførsel som angivet på fakturaen.',
			// mails
			'mail_label_invoice'=> 'Faktura %nr',
			'mail_h_new_month'  => 'Din faktura for %month er klar',
			'mail_h_new'        => 'Din faktura er klar',
			'mail_subj_new'     => 'Faktura %nr fra %brand',
			'mail_body_new'     => 'Hej %fornavn. Åbn fakturaen, og betal den med kort på samme side.',
			'mail_body_new_rec' => ' Gemmer du kortet, betales dine næste fakturaer automatisk.',
			'mail_btn_new'      => 'Se faktura og betal',
			'mail_note_new'     => 'Virker knappen ikke, så åbn %url i din browser. Foretrækker du bankoverførsel, står oplysningerne på fakturaen.',
			'mail_subj_paid'    => 'Tak for din betaling – faktura %nr',
			'mail_h_paid'       => 'Tak for din betaling',
			'mail_body_paid'    => 'Hej %fornavn. Betalingen af faktura %nr er gennemført.',
			'mail_paid_box'     => '%amount betalt den %date',
			'mail_btn_pdf'      => 'Hent faktura (PDF)',
			'mail_subj_failed'  => 'Betalingen af faktura %nr kunne ikke gennemføres',
			'mail_h_failed'     => 'Betalingen kunne ikke gennemføres',
			'mail_body_failed'  => 'Hej %fornavn. Vi forsøgte at trække beløbet for faktura %nr på dit gemte kort, men banken afviste betalingen.',
			'mail_failed_box'   => '%card blev afvist den %date',
			'mail_btn_failed'   => 'Betal nu / skift kort',
			'mail_note_failed'  => 'Betaler du ikke, prøver vi igen %retry. Lykkes det ikke, sender vi en rykker.',
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

// ---------------------------------------------------------------- DDL
if (!function_exists('stripe_payments_ensure')) {
	function stripe_payments_ensure() {
		static $done = false;
		if ($done) return;
		$done = true;
		$chk = db_fetch_array(db_select("SELECT column_name FROM information_schema.columns WHERE table_name='stripe_payments' AND column_name='payment_intent_id'", __FILE__ . " linje " . __LINE__));
		if (!$chk) {
			db_modify("CREATE TABLE stripe_payments (id SERIAL PRIMARY KEY, ordre_id integer NOT NULL, fakturanr varchar(30),
				stripe_customer_id varchar(255), payment_intent_id varchar(255) NOT NULL,
				amount_ore integer NOT NULL, currency varchar(3) NOT NULL DEFAULT 'DKK',
				status varchar(20) NOT NULL, source varchar(10) NOT NULL DEFAULT 'page',
				save_card boolean NOT NULL DEFAULT false, pm_id varchar(255), pm_brand varchar(20), pm_last4 varchar(4),
				attempt smallint NOT NULL DEFAULT 1, last_error text,
				created_at timestamp DEFAULT CURRENT_TIMESTAMP, succeeded_at timestamp,
				journal_line_id integer, confirmation_sent_at timestamp, last_event_id varchar(255))", __FILE__ . " linje " . __LINE__);
			db_modify("CREATE UNIQUE INDEX stripe_payments_pi_uidx ON stripe_payments (payment_intent_id)", __FILE__ . " linje " . __LINE__);
			db_modify("CREATE UNIQUE INDEX stripe_payments_succeeded_uidx ON stripe_payments (ordre_id) WHERE status = 'succeeded'", __FILE__ . " linje " . __LINE__);
			db_modify("CREATE INDEX stripe_payments_ordre_idx ON stripe_payments (ordre_id)", __FILE__ . " linje " . __LINE__);
		}
		foreach (array('stripe' => 'varchar(2)', 'stripe_customer_id' => 'varchar(255)', 'stripe_pm_id' => 'varchar(255)', 'stripe_date' => 'date') as $col => $type) {
			$c = db_fetch_array(db_select("SELECT column_name FROM information_schema.columns WHERE table_name='adresser' AND column_name='$col'", __FILE__ . " linje " . __LINE__));
			if (!$c) db_modify("ALTER TABLE adresser ADD COLUMN $col $type", __FILE__ . " linje " . __LINE__);
		}
	}
}

// ---------------------------------------------------------------- formatting
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
	// G6.3 documents.email.invoice_format: 'pdf' (classic) or 'link'. Read straight from settings
	// so it works in the print engine, the public page and the CLI alike.
	function stripe_invoice_format() {
		static $fmt = null;
		if ($fmt !== null) return $fmt;
		$fmt = 'pdf';
		$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'email_settings' and var_name = 'invoice_format' and (user_id is null or user_id = 0)", __FILE__ . " linje " . __LINE__));
		if ($r && trim((string)$r['var_value']) === 'link' && stripe_setting('enabled', 'off') === 'on') $fmt = 'link';
		return $fmt;
	}
	function stripe_invoice_link_text() {
		$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'email_settings' and var_name = 'invoice_link_text' and (user_id is null or user_id = 0)", __FILE__ . " linje " . __LINE__));
		return ($r && trim((string)$r['var_value']) !== '') ? trim((string)$r['var_value']) : '';
	}
	// What happens when an invoice is issued: 'pdf' (classic mail), 'link' (link mail), 'stripe'
	// (enrolled debtor: no mail, charged on the due date), 'none' (not an e-mail invoice).
	function stripe_invoice_delivery($ordreId) {
		$o = db_fetch_array(db_select("select id, art, udskriv_til, konto_id from ordrer where id = " . (int)$ordreId, __FILE__ . " linje " . __LINE__));
		if (!$o) return 'none';
		if (trim((string)$o['udskriv_til']) === 'Stripe') return 'stripe';
		if (trim((string)$o['udskriv_til']) !== 'email') return 'none';
		if ($o['art'] !== 'DO') return 'pdf';           // credit notes etc. stay classic in v1 (decision D6/O6)
		return stripe_invoice_format();
	}
}

// ---------------------------------------------------------------- loading an invoice
if (!function_exists('stripe_invoice_load')) {
	function stripe_invoice_due($o) {
		$d = substr((string)$o['fakturadate'], 0, 10);
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return '';
		$days = (int)$o['betalingsdage'];
		$bet = strtolower(trim((string)$o['betalingsbet']));
		$ts = strtotime($d);
		if (strpos($bet, 'lb') === 0 || strpos($bet, 'løb') === 0) $ts = strtotime(date('Y-m-t', $ts)); // løbende måned + dage
		return date('Y-m-d', $ts + $days * 86400);
	}
	function stripe_invoice_total_ore($o) {
		return (int)round(((float)$o['sum'] + (float)$o['moms']) * 100);
	}
	function stripe_invoice_load($ordreId) {
		stripe_payments_ensure();
		$o = db_fetch_array(db_select("select * from ordrer where id = " . (int)$ordreId, __FILE__ . " linje " . __LINE__));
		if (!$o || !in_array($o['art'], array('DO', 'DK')) || !(int)$o['fakturanr']) return null;
		$inv = array('o' => $o, 'id' => (int)$o['id'], 'is_credit' => ($o['art'] === 'DK'));
		$inv['nr'] = (string)$o['fakturanr'];
		$inv['total_ore'] = stripe_invoice_total_ore($o);
		$inv['due'] = stripe_invoice_due($o);
		$inv['currency'] = strtoupper(trim((string)$o['valuta'])) ?: 'DKK';
		$inv['lines'] = array();
		$q = db_select("select varenr, beskrivelse, antal, pris, rabat, enhed, posnr from ordrelinjer where ordre_id = " . (int)$ordreId . " order by posnr, id", __FILE__ . " linje " . __LINE__);
		while ($l = db_fetch_array($q)) {
			$antal = (float)$l['antal']; $pris = (float)$l['pris']; $rabat = (float)$l['rabat'];
			$sum = $antal * $pris * (1 - $rabat / 100);
			$inv['lines'][] = array('varenr' => trim((string)$l['varenr']), 'tekst' => trim((string)$l['beskrivelse']), 'antal' => $antal, 'pris' => $pris, 'rabat' => $rabat, 'sum' => $sum, 'is_text' => (trim((string)$l['varenr']) === '' && $antal == 0));
		}
		$inv['firma'] = db_fetch_array(db_select("select firmanavn, addr1, addr2, postnr, bynavn, land, cvrnr, email, tlf, bank_reg, bank_konto from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__)) ?: array();
		$inv['brand'] = trim((string)($inv['firma']['firmanavn'] ?? '')) ?: 'Saldi';
		if (stripos($inv['brand'], 'saldi') !== false || stripos($inv['brand'], 'danosoft') !== false) $inv['brand'] = 'Saldi';
		$inv['debtor'] = (int)$o['konto_id'] ? (db_fetch_array(db_select("select id, kontonr, firmanavn, kontakt, email, stripe_fravalg, stripe, stripe_customer_id, stripe_pm_id, stripe_date, udskriv_til from adresser where id = " . (int)$o['konto_id'], __FILE__ . " linje " . __LINE__)) ?: array()) : array();
		$inv['email'] = trim((string)$o['email']) ?: trim((string)($inv['debtor']['email'] ?? ''));
		$inv['fornavn'] = trim((string)(explode(' ', trim((string)$o['kontakt']) ?: trim((string)($inv['debtor']['kontakt'] ?? '')))[0]));
		$inv['no_card'] = !empty($inv['debtor']['stripe_fravalg']);
		$inv['enrolled'] = (($inv['debtor']['stripe'] ?? '') === 'on') && !empty($inv['debtor']['stripe_pm_id']);
		$inv['recurring'] = trim((string)$o['nextfakt']) !== '' && $o['art'] === 'DO';
		// payments
		$inv['paid'] = null; $inv['pending'] = null; $inv['failed'] = null; $inv['payments'] = array();
		$q = db_select("select * from stripe_payments where ordre_id = " . (int)$ordreId . " order by id desc", __FILE__ . " linje " . __LINE__);
		while ($p = db_fetch_array($q)) {
			$inv['payments'][] = $p;
			if ($p['status'] === 'succeeded' && !$inv['paid']) $inv['paid'] = $p;
			if ($p['status'] === 'pending' && !$inv['pending'] && (time() - strtotime($p['created_at'])) < 900) $inv['pending'] = $p;
			if ($p['status'] === 'failed' && !$inv['failed']) $inv['failed'] = $p;
		}
		// paid outside Stripe: open item settled or betalt flag
		$inv['paid_bank'] = false;
		if (!$inv['paid'] && !$inv['is_credit']) {
			$op = db_fetch_array(db_select("select sum(amount) as rest from openpost where faktnr = '" . db_escape_string($inv['nr']) . "' and konto_id = " . (int)$o['konto_id'] . " and udlignet = 0", __FILE__ . " linje " . __LINE__));
			$anyOp = db_fetch_array(db_select("select count(*) as n from openpost where faktnr = '" . db_escape_string($inv['nr']) . "' and konto_id = " . (int)$o['konto_id'], __FILE__ . " linje " . __LINE__));
			if ($anyOp && (int)$anyOp['n'] > 0 && $op && abs((float)$op['rest']) < 0.01) $inv['paid_bank'] = true;
			if (trim((string)$o['betalt']) === 'on') $inv['paid_bank'] = true;
		}
		$inv['state'] = stripe_invoice_state($inv);
		return $inv;
	}
	function stripe_invoice_state($inv) {
		if ($inv['is_credit']) return 'credited';
		if ($inv['paid']) return 'paid';
		if ($inv['paid_bank']) return 'paidbank';
		if ($inv['pending']) return 'pending';
		if ($inv['no_card']) return 'nocard';
		if ($inv['failed']) return 'failed';
		if ($inv['enrolled']) return 'enrolled';
		if ($inv['due'] && $inv['due'] < date('Y-m-d')) return 'overdue';
		return $inv['recurring'] ? 'open_recurring' : 'open';
	}
	function stripe_invoice_payable($state) { return in_array($state, array('open', 'open_recurring', 'overdue', 'failed', 'enrolled')); }
	function stripe_invoice_url($ordreId) {
		$base = rtrim((string)stripe_setting('public_base_url'), '/');
		$sig = stripe_link_sign($ordreId);
		if (!$base || $sig === '') return '';
		return $base . '/api/stripe/faktura.php?o=' . (int)$ordreId . '&s=' . $sig;
	}
}

// ---------------------------------------------------------------- PDF (cache + CLI renderer)
if (!function_exists('stripe_invoice_pdf_path')) {
	function stripe_invoice_pdf_dir() {
		global $db;
		$dir = dirname(__DIR__, 2) . '/temp/' . $db . '/stripe_pdf';
		if (!is_dir($dir)) @mkdir($dir, 02775, true);
		return $dir;
	}
	function stripe_invoice_pdf_cache($ordreId, $sourcePdf) {
		if (!is_file($sourcePdf)) return false;
		$dst = stripe_invoice_pdf_dir() . '/' . (int)$ordreId . '.pdf';
		if (@copy($sourcePdf, $dst)) { @chmod($dst, 0664); return true; }
		return false;
	}
	function stripe_invoice_pdf_path($ordreId, $generate = true) {
		global $db;
		$pdf = stripe_invoice_pdf_dir() . '/' . (int)$ordreId . '.pdf';
		if (is_file($pdf) && filesize($pdf) > 100) return $pdf;
		if (!$generate) return '';
		$php = defined('PHP_BINARY') && PHP_BINARY && php_sapi_name() === 'cli' ? PHP_BINARY : '/usr/bin/php';
		$cmd = escapeshellarg($php) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/tools/stripe_invoice_pdf.php') . ' --db=' . escapeshellarg($db) . ' --id=' . (int)$ordreId . ' 2>&1';
		exec($cmd, $out, $rc);
		if ($rc !== 0) { stripe_log('pdf render failed for ' . (int)$ordreId . ': ' . implode(' ', $out)); return ''; }
		return (is_file($pdf) && filesize($pdf) > 100) ? $pdf : '';
	}
}

// ---------------------------------------------------------------- Stripe customer for a debtor
if (!function_exists('stripe_customer_for_debtor')) {
	function stripe_customer_for_debtor($inv) {
		global $db;
		$d = $inv['debtor'];
		if (!$d) return '';
		if (!empty($d['stripe_customer_id'])) return (string)$d['stripe_customer_id'];
		$r = db_fetch_array(db_select("select stripe_customer_id from stripe_customers where konto_id = " . (int)$d['id'] . " order by updated_at desc nulls last, id desc limit 1", __FILE__ . " linje " . __LINE__));
		$cus = $r ? (string)$r['stripe_customer_id'] : '';
		if ($cus === '') {
			$resp = stripeHttpRequest('POST', '/v1/customers', array(
				'name' => (string)$d['firmanavn'], 'email' => $inv['email'],
				'metadata' => array('saldi_konto_id' => (int)$d['id'], 'saldi_kontonr' => (string)$d['kontonr'], 'saldi_db' => (string)$db)));
			if (!$resp['ok']) { stripe_log('customer create failed: ' . $resp['error']); return ''; }
			$cus = (string)$resp['body']['id'];
			db_modify("insert into stripe_customers (stripe_customer_id, konto_id, kontonr, status, updated_at) values ('" . db_escape_string($cus) . "', " . (int)$d['id'] . ", '" . db_escape_string((string)$d['kontonr']) . "', 'active', CURRENT_TIMESTAMP)", __FILE__ . " linje " . __LINE__);
		}
		db_modify("update adresser set stripe_customer_id = '" . db_escape_string($cus) . "' where id = " . (int)$d['id'], __FILE__ . " linje " . __LINE__);
		return $cus;
	}
}

// ---------------------------------------------------------------- PaymentIntent per Saldi invoice
if (!function_exists('stripe_payment_create')) {
	// Returns ['ok'=>bool,'client_secret'=>..,'pi'=>..,'error'=>..]. $source: page|auto|manual.
	// $confirmPm: a saved payment method -> confirmed off-session immediately (auto charge / "Betal nu").
	function stripe_payment_create($inv, $source = 'page', $saveCard = false, $confirmPm = '') {
		global $db;
		if (!stripe_invoice_payable($inv['state']) || $inv['total_ore'] <= 0) return array('ok' => false, 'error' => 'not_payable', 'state' => $inv['state']);
		$cus = stripe_customer_for_debtor($inv);
		if ($cus === '') return array('ok' => false, 'error' => 'customer');
		$attempt = 1;
		foreach ($inv['payments'] as $p) if ($p['status'] === 'failed') $attempt = max($attempt, (int)$p['attempt'] + 1);
		$params = array(
			'amount' => $inv['total_ore'], 'currency' => strtolower($inv['currency']), 'customer' => $cus,
			'description' => stripe_txt('invoice') . ' ' . $inv['nr'],
			'metadata' => array('saldi_ordre_id' => $inv['id'], 'saldi_fakturanr' => $inv['nr'], 'saldi_konto_id' => (int)$inv['o']['konto_id'], 'saldi_db' => (string)$db, 'saldi_source' => $source),
		);
		if ($confirmPm !== '') {
			$params['payment_method'] = $confirmPm; $params['confirm'] = 'true'; $params['off_session'] = 'true';
		} else {
			$params['automatic_payment_methods'] = array('enabled' => 'true');
			if ($saveCard) $params['setup_future_usage'] = 'off_session';
		}
		$idem = ($confirmPm !== '' ? 'auto' : 'pi') . ':' . $db . ':' . $inv['id'] . ':' . $attempt . ':' . ($saveCard ? 's' : 'n');
		$resp = stripeHttpRequest('POST', '/v1/payment_intents', $params, array('idempotency_key' => $idem));
		if (!$resp['ok']) {
			// off-session confirm can fail synchronously (card declined): record it like the webhook would
			if ($confirmPm !== '' && !empty($resp['body']['error']['payment_intent']['id'])) {
				$pi = $resp['body']['error']['payment_intent'];
				stripe_payment_record($inv, $pi, $source, $saveCard, $attempt);
				stripe_payment_apply_failed($pi, '');
			}
			return array('ok' => false, 'error' => $resp['error'] ?: 'stripe', 'error_code' => $resp['error_code']);
		}
		$pi = $resp['body'];
		stripe_payment_record($inv, $pi, $source, $saveCard, $attempt);
		return array('ok' => true, 'client_secret' => (string)$pi['client_secret'], 'pi' => (string)$pi['id'], 'status' => (string)$pi['status']);
	}
	function stripe_payment_record($inv, $pi, $source, $saveCard, $attempt) {
		$piId = db_escape_string((string)$pi['id']);
		$ex = db_fetch_array(db_select("select id from stripe_payments where payment_intent_id = '$piId'", __FILE__ . " linje " . __LINE__));
		if ($ex) return (int)$ex['id'];
		$status = ((string)$pi['status'] === 'succeeded') ? 'pending' : 'pending'; // success is applied by the webhook / apply_succeeded
		db_modify("insert into stripe_payments (ordre_id, fakturanr, stripe_customer_id, payment_intent_id, amount_ore, currency, status, source, save_card, attempt) values ("
			. (int)$inv['id'] . ", '" . db_escape_string($inv['nr']) . "', '" . db_escape_string((string)($pi['customer'] ?? '')) . "', '$piId', " . (int)$pi['amount'] . ", '"
			. db_escape_string(strtoupper((string)$pi['currency'])) . "', '$status', '" . db_escape_string($source) . "', " . ($saveCard ? 'true' : 'false') . ", " . (int)$attempt . ")", __FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select id from stripe_payments where payment_intent_id = '$piId'", __FILE__ . " linje " . __LINE__));
		return $r ? (int)$r['id'] : 0;
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
	// Idempotent. Called by the webhook (payment_intent.succeeded) and by the state endpoint
	// when it sees a succeeded intent before the webhook arrived. Returns 'applied'|'duplicate'|'unknown'.
	function stripe_payment_apply_succeeded($pi, $eventId = '') {
		stripe_payments_ensure();
		$piId = db_escape_string((string)$pi['id']);
		$ordreId = (int)(isset($pi['metadata']['saldi_ordre_id']) ? $pi['metadata']['saldi_ordre_id'] : 0);
		$row = db_fetch_array(db_select("select * from stripe_payments where payment_intent_id = '$piId'", __FILE__ . " linje " . __LINE__));
		if (!$row && $ordreId) {
			$inv0 = stripe_invoice_load($ordreId);
			if ($inv0) { stripe_payment_record($inv0, $pi, (string)($pi['metadata']['saldi_source'] ?? 'page'), !empty($pi['setup_future_usage']), 1); $row = db_fetch_array(db_select("select * from stripe_payments where payment_intent_id = '$piId'", __FILE__ . " linje " . __LINE__)); }
		}
		if (!$row) return 'unknown';
		if ($row['status'] === 'succeeded') return 'duplicate';
		$ordreId = (int)$row['ordre_id'];
		list($pm, $brand, $last4) = stripe_pi_card($pi);
		$ok = db_modify("update stripe_payments set status = 'succeeded', succeeded_at = CURRENT_TIMESTAMP, pm_id = '" . db_escape_string($pm) . "', pm_brand = '" . db_escape_string($brand) . "', pm_last4 = '" . db_escape_string($last4) . "', last_event_id = '" . db_escape_string($eventId) . "' where id = " . (int)$row['id'] . " and status <> 'succeeded'", __FILE__ . " linje " . __LINE__, true);
		if (!$ok) return 'duplicate';                                      // partial unique index: another intent already succeeded
		$chk = db_fetch_array(db_select("select status from stripe_payments where id = " . (int)$row['id'], __FILE__ . " linje " . __LINE__));
		if (!$chk || $chk['status'] !== 'succeeded') return 'duplicate';
		db_modify("update ordrer set betalt = 'on' where id = $ordreId", __FILE__ . " linje " . __LINE__);
		$inv = stripe_invoice_load($ordreId);
		$payment = db_fetch_array(db_select("select * from stripe_payments where id = " . (int)$row['id'], __FILE__ . " linje " . __LINE__));
		if ($inv) {
			$lineId = stripe_journal_add_payment($inv, $payment);
			if ($lineId) db_modify("update stripe_payments set journal_line_id = $lineId where id = " . (int)$row['id'], __FILE__ . " linje " . __LINE__);
			if (!$payment['confirmation_sent_at']) {
				if (stripe_mail_confirmation($inv, $payment)) db_modify("update stripe_payments set confirmation_sent_at = CURRENT_TIMESTAMP where id = " . (int)$row['id'], __FILE__ . " linje " . __LINE__);
			}
			if (!empty($payment['save_card']) && $pm !== '' && function_exists('stripe_enrol_debtor')) stripe_enrol_debtor($inv, $pm, $brand, $last4);
		}
		if (function_exists('audit_log_write') && function_exists('tbl_exists')) audit_log_write('stripe.payment_succeeded', 'ordre', (string)$ordreId, json_encode(array('pi' => (string)$pi['id'], 'amount_ore' => (int)$row['amount_ore'], 'card' => stripe_card_label($brand, $last4))), 'stripe');
		return 'applied';
	}
	function stripe_payment_apply_failed($pi, $eventId = '') {
		stripe_payments_ensure();
		$piId = db_escape_string((string)$pi['id']);
		$row = db_fetch_array(db_select("select * from stripe_payments where payment_intent_id = '$piId'", __FILE__ . " linje " . __LINE__));
		if (!$row || $row['status'] === 'succeeded') return 'ignored';
		$err = '';
		if (!empty($pi['last_payment_error'])) $err = (string)(($pi['last_payment_error']['decline_code'] ?? '') ?: ($pi['last_payment_error']['code'] ?? '') ?: ($pi['last_payment_error']['message'] ?? ''));
		list($pm, $brand, $last4) = stripe_pi_card($pi);
		db_modify("update stripe_payments set status = 'failed', last_error = '" . db_escape_string(substr($err, 0, 200)) . "', pm_id = '" . db_escape_string($pm) . "', pm_brand = '" . db_escape_string($brand) . "', pm_last4 = '" . db_escape_string($last4) . "', last_event_id = '" . db_escape_string($eventId) . "' where id = " . (int)$row['id'], __FILE__ . " linje " . __LINE__);
		if ($row['source'] === 'auto') {
			$inv = stripe_invoice_load((int)$row['ordre_id']);
			$payment = db_fetch_array(db_select("select * from stripe_payments where id = " . (int)$row['id'], __FILE__ . " linje " . __LINE__));
			if ($inv) stripe_mail_failed($inv, $payment);
		}
		if (function_exists('audit_log_write') && function_exists('tbl_exists')) audit_log_write('stripe.payment_failed', 'ordre', (string)$row['ordre_id'], json_encode(array('pi' => (string)$pi['id'], 'error' => $err)), 'stripe');
		return 'applied';
	}
	// Used by faktura_state.php: when the browser returned from Stripe but the webhook has not
	// arrived yet, ask Stripe directly (same handler, idempotent).
	function stripe_payment_sync($piId) {
		$resp = stripeHttpRequest('GET', '/v1/payment_intents/' . rawurlencode($piId), array('expand' => array('latest_charge')));
		if (!$resp['ok']) return 'error';
		$pi = $resp['body'];
		if ($pi['status'] === 'succeeded') return stripe_payment_apply_succeeded($pi, 'sync');
		if (in_array($pi['status'], array('requires_payment_method', 'canceled')) && !empty($pi['last_payment_error'])) return stripe_payment_apply_failed($pi, 'sync');
		return (string)$pi['status'];
	}
}

// ---------------------------------------------------------------- journal line (debit card clearing, credit debtor)
if (!function_exists('stripe_journal_add_payment')) {
	function stripe_journal_add_payment($inv, $payment) {
		global $regnaar;
		$clr = db_fetch_array(db_select("select box10 from grupper where art = 'DIV' and kodenr = '3' order by fiscal_year desc nulls last limit 1", __FILE__ . " linje " . __LINE__));
		$clearing = $clr ? trim((string)$clr['box10']) : '';
		if ($clearing === '') { stripeAlertMail('Stripe-betaling uden kortclearingkonto', "Faktura " . $inv['nr'] . " er betalt, men kortclearingkontoen er ikke sat (Ordrerelaterede valg).\nKladdelinjen er IKKE oprettet.", 'noclearing', 3600); return 0; }
		$kladde = db_fetch_array(db_select("select id from kladdeliste where kladdenote = '" . db_escape_string(stripe_txt('journal_name')) . "' and (bogfort = '-' or bogfort = '' or bogfort is null) order by id desc limit 1", __FILE__ . " linje " . __LINE__));
		if ($kladde) $kladdeId = (int)$kladde['id'];
		else {
			$m = db_fetch_array(db_select("select coalesce(max(id),0)+1 as n from kladdeliste", __FILE__ . " linje " . __LINE__));
			$kladdeId = (int)$m['n'];
			db_modify("insert into kladdeliste (id, kladdedate, kladdenote, bogfort, oprettet_af) values ($kladdeId, '" . date('Y-m-d') . "', '" . db_escape_string(stripe_txt('journal_name')) . "', '-', 'stripe')", __FILE__ . " linje " . __LINE__);
		}
		$b = db_fetch_array(db_select("select coalesce(max(bilag),0)+1 as n from kassekladde where kladde_id = $kladdeId", __FILE__ . " linje " . __LINE__));
		$bilag = (int)$b['n'];
		$date = date('Y-m-d', $payment['succeeded_at'] ? strtotime($payment['succeeded_at']) : time());
		$amount = number_format(((int)$payment['amount_ore']) / 100, 2, '.', '');
		$text = stripe_txt('journal_text', array('card' => stripe_card_label($payment['pm_brand'], $payment['pm_last4']), 'nr' => $inv['nr']));
		$kontonr = db_escape_string((string)($inv['debtor']['kontonr'] ?? $inv['o']['kontonr']));
		db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, amount, kladde_id, faktura, ordre_id) values ($bilag, '$date', '" . db_escape_string($text) . "', 'F', '" . db_escape_string($clearing) . "', 'D', '$kontonr', '$amount', $kladdeId, '" . db_escape_string($inv['nr']) . "', " . (int)$inv['id'] . ")", __FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select max(id) as id from kassekladde where kladde_id = $kladdeId and bilag = $bilag", __FILE__ . " linje " . __LINE__));
		return $r ? (int)$r['id'] : 0;
	}
}

// ---------------------------------------------------------------- mails (fixed templates)
if (!function_exists('stripe_mail_send')) {
	function stripe_mail_sender() {
		$s = db_fetch_array(db_select("select firmanavn, email, felt_1, felt_2, felt_3, felt_4 from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__)) ?: array();
		$name = ''; $from = '';
		$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'email_settings' and var_name = 'sender_email' order by group_id limit 1", __FILE__ . " linje " . __LINE__));
		if ($r && trim((string)$r['var_value']) !== '') $from = trim((string)$r['var_value']);
		$r = db_fetch_array(db_select("select var_value from settings where var_grp = 'email_settings' and var_name = 'sender_name' order by group_id limit 1", __FILE__ . " linje " . __LINE__));
		if ($r && trim((string)$r['var_value']) !== '') $name = trim((string)$r['var_value']);
		if ($from === '') $from = trim((string)($s['email'] ?? ''));
		if ($name === '') $name = trim((string)($s['firmanavn'] ?? 'Saldi'));
		return array('from' => $from, 'name' => $name, 'smtp' => trim((string)($s['felt_1'] ?? '')), 'user' => trim((string)($s['felt_2'] ?? '')), 'pwd' => (string)($s['felt_3'] ?? ''), 'enc' => trim((string)($s['felt_4'] ?? '')));
	}
	function stripe_mail_send($to, $subject, $html, $text) {
		if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { stripe_log("mail: invalid recipient '$to'"); return false; }
		$snd = stripe_mail_sender();
		$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
		if (!is_file($autoload)) { stripe_log('mail: vendor/autoload.php missing'); return false; }
		require_once $autoload;
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
		if (filter_var($snd['from'], FILTER_VALIDATE_EMAIL)) { $mail->AddReplyTo($snd['from'], $snd['name']); $mail->ReturnPath = $snd['from']; }
		$mail->AddAddress($to);
		$mail->Subject = $subject;
		$mail->IsHTML(true);
		$mail->Body = $html;
		$mail->AltBody = $text;
		if (!$mail->Send()) { stripe_log('mail: send failed to ' . $to . ': ' . $mail->ErrorInfo); return false; }
		return true;
	}
	// Fixed template (design_fakturamail.png). $rows = [[label, value], ...]; $box = ['kind'=>good|bad,'title'=>..,'sub'=>..] or null.
	function stripe_mail_layout($inv, $label, $h1, $bodyText, $box, $rows, $btnText, $btnUrl, $btn2Text, $btn2Url, $noteHtml) {
		$f = $inv['firma'];
		$footer = stripe_h(trim((string)($f['firmanavn'] ?? ''))) . ' · CVR ' . stripe_h($f['cvrnr'] ?? '') . ' · ' . stripe_h(trim(($f['addr1'] ?? '') . ', ' . ($f['postnr'] ?? '') . ' ' . ($f['bynavn'] ?? ''), ' ,'));
		$h = '<!doctype html><html lang="da"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . stripe_h($h1) . '</title></head>';
		$h .= '<body style="margin:0;padding:40px 16px;background:#f7f5f0;font-family:Inter,Segoe UI,Helvetica,Arial,sans-serif;color:#111318;font-size:16px;line-height:1.6">';
		$h .= '<div style="max-width:600px;margin:0 auto;background:#fff;border:1px solid #e6e2da;border-radius:16px;padding:44px 44px 40px">';
		$h .= '<div style="font-size:26px;font-weight:700;letter-spacing:-.02em;margin-bottom:32px">' . stripe_h($inv['brand']) . '</div>';
		$h .= '<div style="font-size:12px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#9a5b1a;margin-bottom:12px">' . stripe_h($label) . '</div>';
		$h .= '<h1 style="font-size:32px;line-height:1.15;font-weight:700;margin:0 0 14px;letter-spacing:-.01em">' . stripe_h($h1) . '</h1>';
		$h .= '<p style="margin:0;color:#4b5563">' . stripe_h($bodyText) . '</p>';
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
		$url = stripe_invoice_url($inv['id']);
		if ($url === '' || $inv['email'] === '') return false;
		$month = stripe_month_name($inv['o']['fakturadate']);
		$h1 = ($inv['recurring'] && $month) ? stripe_txt('mail_h_new_month', array('month' => $month)) : stripe_txt('mail_h_new');
		$body = stripe_invoice_link_text();
		if ($body === '') $body = stripe_txt('mail_body_new', array('fornavn' => $inv['fornavn'] ?: 'kunde')) . ($inv['recurring'] ? stripe_txt('mail_body_new_rec') : '');
		else $body = str_replace(array('[Fornavn]', '[fornavn]', '$fornavn'), $inv['fornavn'] ?: 'kunde', $body);
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
		$pdfUrl = $url ? str_replace('/faktura.php?', '/faktura_pdf.php?', $url) : '';
		$card = stripe_card_label($payment['pm_brand'], $payment['pm_last4']);
		$when = stripe_fmt_date_long(substr((string)$payment['succeeded_at'], 0, 10) ?: date('Y-m-d'));
		$box = array('kind' => 'good', 'title' => stripe_txt('mail_paid_box', array('amount' => stripe_fmt_kr($payment['amount_ore']) . ' kr.', 'date' => $when)), 'sub' => $card . ' · ' . stripe_txt($payment['source'] === 'auto' ? 'auto_payment' : 'card_payment'));
		$rows = array(array(stripe_txt('invoice'), $inv['nr']));
		$month = stripe_month_name($inv['o']['fakturadate']); if ($inv['recurring'] && $month) $rows[] = array(stripe_txt('period'), ucfirst($month));
		if ($inv['recurring'] && trim((string)$inv['o']['nextfakt']) !== '') $rows[] = array(stripe_txt('next_invoice'), stripe_fmt_date_long(substr((string)$inv['o']['nextfakt'], 0, 10)));
		$body = stripe_txt('mail_body_paid', array('fornavn' => $inv['fornavn'] ?: 'kunde', 'nr' => $inv['nr']));
		$html = stripe_mail_layout($inv, stripe_txt('mail_label_invoice', array('nr' => $inv['nr'])), stripe_txt('mail_h_paid'), $body, $box, $rows, stripe_txt('mail_btn_pdf'), $pdfUrl, '', '', stripe_h(stripe_txt('find_in_saldi')));
		$text = stripe_mail_text_version(stripe_txt('mail_h_paid'), $body . "\n" . $box['title'] . ' (' . $box['sub'] . ')', $rows, stripe_txt('mail_btn_pdf'), $pdfUrl, stripe_txt('find_in_saldi'));
		return stripe_mail_send($inv['email'], stripe_txt('mail_subj_paid', array('nr' => $inv['nr'])), $html, $text);
	}
	function stripe_mail_failed($inv, $payment) {
		if ($inv['email'] === '') return false;
		$url = stripe_invoice_url($inv['id']);
		$card = stripe_card_label($payment['pm_brand'], $payment['pm_last4']);
		$when = stripe_fmt_date_long(date('Y-m-d'));
		$box = array('kind' => 'bad', 'title' => stripe_txt('mail_failed_box', array('card' => $card, 'date' => $when)), 'sub' => $payment['last_error'] ? 'Årsag fra banken: ' . $payment['last_error'] : '');
		$retryDays = array(1 => 3, 2 => 4); $attempt = (int)$payment['attempt'];
		$retry = isset($retryDays[$attempt]) ? 'den ' . stripe_fmt_date_long(date('Y-m-d', time() + $retryDays[$attempt] * 86400)) : 'ikke igen';
		$rows = array(array(stripe_txt('amount_lbl'), stripe_fmt_kr($payment['amount_ore']) . ' kr.'), array(stripe_txt('due_date'), stripe_fmt_date($inv['due'])));
		$body = stripe_txt('mail_body_failed', array('fornavn' => $inv['fornavn'] ?: 'kunde', 'nr' => $inv['nr']));
		$note = stripe_h(stripe_txt('mail_note_failed', array('retry' => $retry)));
		$html = stripe_mail_layout($inv, stripe_txt('mail_label_invoice', array('nr' => $inv['nr'])), stripe_txt('mail_h_failed'), $body, $box, $rows, stripe_txt('mail_btn_failed'), $url, '', '', $note);
		$text = stripe_mail_text_version(stripe_txt('mail_h_failed'), $body . "\n" . $box['title'], $rows, stripe_txt('mail_btn_failed'), $url, strip_tags($note));
		return stripe_mail_send($inv['email'], stripe_txt('mail_subj_failed', array('nr' => $inv['nr'])), $html, $text);
	}
}

// ---------------------------------------------------------------- webhook entry (payment_intent.*)
if (!function_exists('stripe_webhook_payment_intent')) {
	function stripe_webhook_payment_intent($eventType, $pi, $eventId) {
		if ($eventType === 'payment_intent.succeeded') return stripe_payment_apply_succeeded($pi, $eventId);
		if ($eventType === 'payment_intent.payment_failed') return stripe_payment_apply_failed($pi, $eventId);
		if ($eventType === 'payment_intent.canceled') {
			$piId = db_escape_string((string)$pi['id']);
			db_modify("update stripe_payments set status = 'canceled', last_event_id = '" . db_escape_string($eventId) . "' where payment_intent_id = '$piId' and status <> 'succeeded'", __FILE__ . " linje " . __LINE__);
			return 'applied';
		}
		return 'ignored';
	}
}
