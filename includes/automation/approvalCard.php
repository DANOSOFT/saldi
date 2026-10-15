<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/approvalCard.php --- ai/mcp-assistant --- 2026.10.04
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
//
// HTML view of the shared ApprovalCard model (SPEC 4.5), used by the Opgaver page
// (inbox/index.php) and by the shell's approval modal (inbox/action.php preview).
// One renderer for both, so the human approves exactly the markup the page shows.
// Everything that comes from a proposer (title, why, rows, field values, evidence
// labels) is untrusted and escaped here; evidence links are re-checked against
// SaldiScreens. Also holds the Danish status, receipt and error wording the two
// surfaces share. Pure functions: no session, no globals, no SQL of its own.
//
// 20261002 CL/LH Created.
// 20261002 CDX/LH Translate conflict reasons and render escaped decision names and notes.
// 20261002 CDX/LH Render informational notices neutrally without warning icons.
// 20261004 CDX/LH Show applied and undone receipts for settlement, purchasing, sales and credit drafts.
// 20261007 CL/LH Raw database quantities with 4+ decimals ('0.00000') render as Danish figures ('0'), so
//                no card shows a raw discount or quantity; the receipt button names the document
//                ("Åbn tilbuddet", "Åbn kreditnotaen").

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';

/** HTML-escape untrusted text for element content and attribute values. */
function saldi_ui_h(?string $value): string
{
	return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Translated UI text (tenant override, then importfiler/tekster.csv, then $fallback)
 * with {name} placeholders replaced verbatim. Escape the result when printing it.
 *
 * @param array<string, string|int> $vars
 */
function saldi_ui_text(?SaldiDb $db, int $languageId, int $textId, string $fallback, array $vars = []): string
{
	$text = SaldiText::get($db, $textId, $languageId, $fallback);
	if (!$vars) {
		return $text;
	}
	$pairs = [];
	foreach ($vars as $key => $value) {
		$pairs['{' . $key . '}'] = (string)$value;
	}
	return strtr($text, $pairs);
}

/** ISO 8601 UTC ('2026-10-15T10:00:00Z') shown as Copenhagen local '15.10.2026 kl. 12:00'. */
function saldi_ui_local_time(?string $isoUtc, bool $withTime = true): string
{
	if ($isoUtc === null || $isoUtc === '') {
		return '';
	}
	try {
		$time = new DateTimeImmutable($isoUtc, new DateTimeZone('UTC'));
	} catch (Exception $e) {
		return '';
	}
	$local = $time->setTimezone(new DateTimeZone('Europe/Copenhagen'));
	return $withTime ? $local->format('d.m.Y') . ' kl. ' . $local->format('H:i') : $local->format('d.m.Y');
}

/**
 * Display form of one change-table cell. Dates ('2026-10-01') and API money
 * ('1250.00', '-80.50 DKK') get Saldi's Danish notation by string handling only,
 * so the value shown is exactly the stored value. Raw database quantities and
 * percentages with four or more decimals ('0.00000', '2.50000') lose their trailing
 * zeros ('0', '2,5'); three decimals stay as they are, because '1.250' is also how
 * a Danish figure for 1250 looks. Everything else is left as is.
 *
 * @return array{text:string, numeric:bool, date:bool}
 */
function saldi_ui_cell(mixed $value): array
{
	$text = is_scalar($value) ? (string)$value : '';
	if (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $text, $m)) {
		return ['text' => $m[3] . '-' . $m[2] . '-' . $m[1], 'numeric' => false, 'date' => true];
	}
	if (preg_match('/^(-?)([0-9]{1,15})\.([0-9]{2})(?: ([A-Z]{3}))?$/D', $text, $m)) {
		$whole = ltrim($m[2], '0') === '' ? '0' : ltrim($m[2], '0');
		$grouped = strrev(implode('.', str_split(strrev($whole), 3)));
		return ['text' => $m[1] . $grouped . ',' . $m[3] . (isset($m[4]) ? ' ' . $m[4] : ''), 'numeric' => true, 'date' => false];
	}
	if (preg_match('/^(-?)([0-9]{1,15})\.([0-9]{4,9})$/D', $text, $m)) {
		$whole = ltrim($m[2], '0') === '' ? '0' : ltrim($m[2], '0');
		$fraction = rtrim($m[3], '0');
		$sign = $whole === '0' && $fraction === '' ? '' : $m[1];
		$grouped = strrev(implode('.', str_split(strrev($whole), 3)));
		return ['text' => $sign . $grouped . ($fraction === '' ? '' : ',' . $fraction), 'numeric' => true, 'date' => false];
	}
	return ['text' => $text, 'numeric' => false, 'date' => false];
}

/**
 * Visible state of a card. Only 'applied' is ever green ('done'); everything that
 * did not change the books is neutral or amber and says so in words.
 *
 * @return array{tone:string, label:string, detail:string}
 */
function saldi_approval_status_view(array $card, ?SaldiDb $db, int $lang): array
{
	$nothing = saldi_ui_text($db, $lang, 5822, 'Intet er ændret.');
	switch ($card['status'] ?? 'open') {
		case 'applied':
			$label = in_array($card['kind'] ?? '', ['followup.task', 'routine.finding'], true)
				? (string)($card['approve_label'] ?? '')
				: saldi_ui_text($db, $lang, 5823, 'Godkendt');
			return ['tone' => 'done', 'label' => $label, 'detail' => ''];
		case 'rejected':
			return ['tone' => 'closed', 'label' => saldi_ui_text($db, $lang, 5803, 'Afvist'), 'detail' => $nothing];
		case 'expired':
			return ['tone' => 'warn', 'label' => saldi_ui_text($db, $lang, 5804, 'Udløbet'), 'detail' => $nothing];
		case 'cancelled':
			return ['tone' => 'closed', 'label' => saldi_ui_text($db, $lang, 5825, 'Trukket tilbage'), 'detail' => $nothing];
		case 'undone':
			return ['tone' => 'closed', 'label' => saldi_ui_text($db, $lang, 5824, 'Fortrudt'), 'detail' => ''];
		default:
			return ['tone' => 'open', 'label' => '', 'detail' => ''];
	}
}

/**
 * Plain-language receipt for a decided item, from the stored apply()/undo() result.
 * The screen (when present) is an allowlisted SaldiScreens link.
 *
 * @return array{text:string, screen:?string, screen_label:?string}|null
 */
function saldi_approval_receipt(array $item, ?SaldiDb $db, int $lang): ?array
{
	$result = is_array($item['result'] ?? null) ? $item['result'] : [];
	$kind = (string)($item['kind'] ?? '');
	$status = (string)($item['status'] ?? '');
	if ($status === 'undone') {
		$messages = [
			'journal.draft_entry' => [5894, 'Linjerne er fjernet fra kassekladden igen.'],
			'memory.fact' => [5891, 'Oplysningen er fjernet fra Saldi Assists hukommelse.'],
			'settlement.draft' => [6281, 'Udligningen er fortrudt. Posternes tidligere udligning er gendannet.'],
			'purchase_order.invoice_stage' => [6283, 'Indkøbsordrens tidligere fakturaoplysninger, antal og priser er gendannet.'],
			'sales_order.draft' => ($item['payload']['type'] ?? '') === 'offer'
				? [6286, 'Tilbuddet er fjernet igen.'] : [6287, 'Ordren er fjernet igen.'],
			'credit_note.draft' => [6289, 'Kreditnotakladden er fjernet igen.'],
		];
		if (!isset($messages[$kind])) {
			return null;
		}
		[$id, $fallback] = $messages[$kind];
		$text = saldi_ui_text($db, $lang, $id, $fallback);
		return ['text' => $text, 'screen' => null, 'screen_label' => null];
	}
	if ($status !== 'applied') {
		return null;
	}
	switch ($kind) {
		case 'journal.draft_entry':
			$kladde = (int)($result['kladde_id'] ?? 0);
			$lines = is_array($result['line_ids'] ?? null) ? count($result['line_ids']) : 0;
			if ($kladde <= 0) {
				return null;
			}
			$text = $lines === 1
				? saldi_ui_text($db, $lang, 5827, '1 linje lagt i kassekladde {kladde}. Intet er bogført.', ['kladde' => $kladde])
				: saldi_ui_text($db, $lang, 5826, '{n} linjer lagt i kassekladde {kladde}. Intet er bogført.', ['n' => $lines, 'kladde' => $kladde]);
			return ['text' => $text, 'screen' => SaldiScreens::link('journal', $kladde), 'screen_label' => saldi_ui_text($db, $lang, 5828, 'Åbn kassekladde {kladde}', ['kladde' => $kladde])];
		case 'settlement.draft':
			return ['text' => saldi_ui_text($db, $lang, 6280, 'Posterne er udlignet. Intet er betalt eller bogført.'), 'screen' => null, 'screen_label' => null];
		case 'purchase_order.invoice_stage':
		case 'sales_order.draft':
		case 'credit_note.draft':
			$order = (int)($result['order_id'] ?? 0);
			if ($order <= 0) {
				return null;
			}
			$messages = [
				'purchase_order.invoice_stage' => [6282, 'Fakturaoplysningerne er lagt på indkøbsordren. Intet er modtaget eller bogført.'],
				'sales_order.draft' => ($item['payload']['type'] ?? '') === 'offer'
					? [6284, 'Tilbuddet er oprettet. Intet er sendt eller faktureret.']
					: [6285, 'Ordren er oprettet. Intet er sendt eller faktureret.'],
				'credit_note.draft' => [6288, 'Kreditnotaen er oprettet som kladde. Intet er sendt eller bogført.'],
			];
			[$id, $fallback] = $messages[$kind];
			// The button names the document the person will land on.
			$open = match (true) {
				$kind === 'credit_note.draft' => [6331, 'Åbn kreditnotaen'],
				$kind === 'sales_order.draft' && ($item['payload']['type'] ?? '') === 'offer' => [6330, 'Åbn tilbuddet'],
				default => [6290, 'Åbn ordren'],
			};
			return ['text' => saldi_ui_text($db, $lang, $id, $fallback), 'screen' => SaldiScreens::link($kind === 'purchase_order.invoice_stage' ? 'purchase_order' : 'sales_order', $order), 'screen_label' => saldi_ui_text($db, $lang, $open[0], $open[1])];
		case 'memory.fact':
			return ['text' => saldi_ui_text($db, $lang, 5890, 'Oplysningen er gemt i Saldi Assists hukommelse.'), 'screen' => null, 'screen_label' => null];
		case 'memory.retract':
			return ['text' => saldi_ui_text($db, $lang, 5891, 'Oplysningen er fjernet fra Saldi Assists hukommelse.'), 'screen' => null, 'screen_label' => null];
		case 'followup.task':
			return ['text' => saldi_ui_text($db, $lang, 5892, 'Markeret som udført.'), 'screen' => null, 'screen_label' => null];
		case 'routine.finding':
			return ['text' => saldi_ui_text($db, $lang, 5893, 'Markeret som set.'), 'screen' => null, 'screen_label' => null];
	}
	return null;
}

/**
 * Danish message for a failed decision. A few core conflicts get a more precise
 * sentence than the generic per-code text; everything else uses the code's text.
 * Never exposes PDO or other internal messages.
 *
 * @return array{code:string, message_da:string, detail:array}
 */
function saldi_approval_error(Throwable $e, ?SaldiDb $db, int $lang): array
{
	if (!$e instanceof SaldiRuntimeError) {
		$e = new SaldiRuntimeError('UNAVAILABLE', 'Unavailable');
	}
	$precise = [
		'tenant_changed' => [5883, 'Du har skiftet regnskab i et andet vindue. Genindlæs siden.'],
		'expired' => [5887, 'Forslaget er udløbet.'],
		'not_open' => [5884, 'Forslaget er allerede behandlet eller ændret, siden du åbnede det.'],
		'digest_mismatch' => [5884, 'Forslaget er allerede behandlet eller ændret, siden du åbnede det.'],
		'journal_in_use' => [5885, 'Kassekladden er åben hos en anden bruger lige nu. Prøv igen om lidt.'],
		'journal_unsaved' => [5886, 'Kassekladden har ugemte ændringer. Gem eller luk den først.'],
		'undo_changed' => [5889, 'Linjerne er ændret eller bogført, så forslaget kan ikke fortrydes.'],
		'modal_open' => [5876, 'Der er allerede en godkendelse åben.'],
		'csrf' => [5888, 'Siden er udløbet. Genindlæs den, og prøv igen.'],
	];
	$reason = $e->detail['reason'] ?? '';
	$message = isset($precise[$reason])
		? saldi_ui_text($db, $lang, $precise[$reason][0], $precise[$reason][1])
		: $e->messageDa($db, $lang);
	return ['code' => $e->errorCode, 'message_da' => $message, 'detail' => $e->detail];
}

/**
 * Render one ApprovalCard.
 *
 * $item is the SaldiInboxService item shape (4.3) with its 'card' (4.5).
 * Options:
 *   db, lang       text lookup (tenant override + CSV)
 *   mode           'page' (Opgaver, with decision forms) or 'modal' (shell dialog, no controls;
 *                  the shell owns Godkend/Annuller)
 *   csrf, this_db  hidden fields for the page forms
 *   form_action    page form target (already escaped by this function)
 *   return_query   opaque PRG target passed back as hidden field 'return'
 *   error          ['code', 'message_da'] from the last attempt on this item
 *   user_id        viewer's tenant user id ('Træk tilbage' only for own proposals)
 *   heading_level  2 or 3
 */
function saldi_approval_card_html(array $item, array $opts = []): string
{
	$card = is_array($item['card'] ?? null) ? $item['card'] : [];
	$db = $opts['db'] ?? null;
	$lang = (int)($opts['lang'] ?? 1);
	$mode = ($opts['mode'] ?? 'page') === 'modal' ? 'modal' : 'page';
	$id = (int)($card['item_id'] ?? $item['id'] ?? 0);
	$status = (string)($card['status'] ?? 'open');
	$error = is_array($opts['error'] ?? null) ? $opts['error'] : null;
	$view = saldi_approval_status_view($card, $db, $lang);
	$tone = $error ? 'warn' : $view['tone'];
	$hl = (int)($opts['heading_level'] ?? 2) === 3 ? 'h3' : 'h2';
	$t = static fn(int $textId, string $fallback, array $vars = []): string => saldi_ui_h(saldi_ui_text($db, $lang, $textId, $fallback, $vars));
	$prefix = $mode === 'modal' ? 'sac-m' : 'sac';

	$out = '<article class="sac sac--' . $tone . '" id="' . $prefix . '-item-' . $id . '" data-item-id="' . $id . '" aria-labelledby="' . $prefix . '-title-' . $id . '">';

	// Header: who proposed it, what kind, title, state.
	$provenance = (string)($card['provenance'] ?? '');
	$out .= '<header class="sac-head"><div class="sac-tags">';
	$out .= '<span class="sac-pill sac-pill--' . (in_array($provenance, ['agent', 'ai'], true) ? 'assist' : 'neutral') . '">' . saldi_ui_h((string)($card['provenance_label'] ?? '')) . '</span>';
	$out .= '<span class="sac-kind">' . saldi_ui_h((string)($card['kind_label'] ?? '')) . '</span>';
	if (!empty($item['created_at'])) {
		$out .= '<span class="sac-meta">' . $t(5896, 'Foreslået {date}', ['date' => saldi_ui_local_time((string)$item['created_at'])]) . '</span>';
	}
	if ($status !== 'open' && !empty($item['decided_at'])) {
		$out .= '<span class="sac-meta">' . $t(5807, 'Behandlet {date}', ['date' => saldi_ui_local_time((string)$item['decided_at'])]) . '</span>';
	}
	$out .= '</div>';
	if ($view['label'] !== '') {
		$icon = ['done' => '&#10003;', 'warn' => '!', 'closed' => '&#8211;'][$view['tone']] ?? '';
		$out .= '<span class="sac-status sac-status--' . $view['tone'] . '"><span aria-hidden="true" class="sac-status__icon">' . $icon . '</span>' . saldi_ui_h($view['label']) . '</span>';
	}
	$out .= '</header>';
	$out .= '<' . $hl . ' class="sac-title" id="' . $prefix . '-title-' . $id . '">' . saldi_ui_h((string)($card['title'] ?? '')) . '</' . $hl . '>';

	if ($status !== 'open') {
		if ((string)($item['decided_by_name'] ?? '') !== '') {
			$out .= '<p class="sac-meta">' . $t(5754, 'Behandlet af {name}', ['name' => $item['decided_by_name']]) . '</p>';
		}
		if ((string)($item['decision_note'] ?? '') !== '') {
			$out .= '<p class="sac-decision-note">' . $t(5755, 'Bemærkning') . ': ' . saldi_ui_h((string)$item['decision_note']) . '</p>';
		}
	}

	// Outcome lines: error from the last attempt, closed state, receipt.
	if ($error) {
		$out .= '<div class="sac-alert sac-alert--warn" role="alert"><p>' . saldi_ui_h((string)($error['message_da'] ?? '')) . '</p><p class="sac-alert__strong">' . $t(5822, 'Intet er ændret.') . '</p></div>';
	} elseif ($view['detail'] !== '') {
		$out .= '<p class="sac-outcome">' . saldi_ui_h($view['detail']) . '</p>';
	}
	$receipt = saldi_approval_receipt($item + ['status' => $status], $db, $lang);
	if ($receipt) {
		$out .= '<p class="sac-receipt">' . saldi_ui_h($receipt['text']);
		if ($receipt['screen'] !== null && SaldiScreens::isAllowed($receipt['screen'])) {
			$out .= ' <a class="sac-link" href="../' . saldi_ui_h($receipt['screen']) . '" data-saldi-screen="' . saldi_ui_h($receipt['screen']) . '">' . saldi_ui_h((string)$receipt['screen_label']) . '</a>';
		}
		$out .= '</p>';
	}

	// The exact change.
	$columns = is_array($card['columns'] ?? null) ? array_values($card['columns']) : [];
	$rows = is_array($card['rows'] ?? null) ? $card['rows'] : [];
	if ($columns && $rows) {
		$caption = ($card['action_class'] ?? '') === 'read_only' ? $t(5818, 'Indhold') : $t(5817, 'Ændringen');
		$numericColumns = [];
		foreach ($rows as $row) {
			foreach (array_values(is_array($row) ? $row : []) as $i => $cell) {
				if (saldi_ui_cell($cell)['numeric']) {
					$numericColumns[$i] = true;
				}
			}
		}
		$out .= '<div class="sac-table-wrap"><table class="sac-table"><caption>' . $caption . '</caption><thead><tr>';
		foreach ($columns as $i => $column) {
			$out .= '<th scope="col"' . (isset($numericColumns[$i]) ? ' class="sac-num"' : '') . '>' . saldi_ui_h(is_scalar($column) ? (string)$column : '') . '</th>';
		}
		$out .= '</tr></thead><tbody>';
		foreach ($rows as $row) {
			$out .= '<tr>';
			$cells = array_values(is_array($row) ? $row : []);
			foreach ($columns as $i => $column) {
				$cell = saldi_ui_cell($cells[$i] ?? '');
				$class = isset($numericColumns[$i]) ? 'sac-num' : ($cell['date'] ? 'sac-nowrap' : '');
				$out .= '<td' . ($class !== '' ? ' class="' . $class . '"' : '') . '>' . saldi_ui_h($cell['text']) . '</td>';
			}
			$out .= '</tr>';
		}
		$out .= '</tbody></table></div>';
	}
	$fields = [];
	foreach (is_array($card['fields'] ?? null) ? $card['fields'] : [] as $field) {
		if (is_array($field) && trim((string)($field['value'] ?? '')) !== '') {
			$fields[] = $field;
		}
	}
	if ($fields) {
		$out .= '<dl class="sac-fields">';
		foreach ($fields as $field) {
			$out .= '<div><dt>' . saldi_ui_h((string)($field['label'] ?? '')) . '</dt><dd>' . saldi_ui_h(saldi_ui_cell($field['value'])['text']) . '</dd></div>';
		}
		$out .= '</dl>';
	}

	// The proposer's explanation: untrusted text, clearly attributed.
	$why = trim((string)($card['why'] ?? ''));
	if ($why !== '') {
		$byAssist = in_array($provenance, ['agent', 'ai'], true);
		$whyLabel = $byAssist ? $t(5814, 'Saldi Assists forklaring') : $t(5897, 'Forklaring');
		$out .= '<section class="sac-why" aria-label="' . $whyLabel . '">';
		$out .= '<p class="sac-why__label">' . $whyLabel . '</p>';
		$out .= '<p class="sac-why__text">' . saldi_ui_h($why) . '</p>';
		if ($byAssist) {
			$out .= '<p class="sac-why__hint">' . $t(5815, 'Teksten er skrevet af Saldi Assist. Det er oplysningerne ovenfor, der gælder.') . '</p>';
		}
		$out .= '</section>';
	}
	$links = [];
	foreach (is_array($card['evidence'] ?? null) ? $card['evidence'] : [] as $link) {
		$screen = is_array($link) ? (string)($link['screen'] ?? '') : '';
		if ($screen !== '' && SaldiScreens::isAllowed($screen)) {
			$label = trim((string)($link['label'] ?? ''));
			$links[] = '<li><a class="sac-link" href="../' . saldi_ui_h($screen) . '" data-saldi-screen="' . saldi_ui_h($screen) . '">' . saldi_ui_h($label !== '' ? $label : $screen) . '</a></li>';
		}
	}
	if ($links) {
		$out .= '<div class="sac-evidence"><p class="sac-evidence__label">' . $t(5816, 'Kilder') . '</p><ul>' . implode('', $links) . '</ul></div>';
	}
	foreach (is_array($card['notices'] ?? null) ? $card['notices'] : [] as $notice) {
		if (is_array($notice) && trim((string)($notice['text'] ?? '')) !== '') {
			if (($notice['kind'] ?? '') === 'info') {
				$out .= '<p class="sac-info">' . saldi_ui_h((string)$notice['text']) . '</p>';
			} else {
				$out .= '<p class="sac-notice"><span class="sac-notice__icon" aria-hidden="true">!</span>' . saldi_ui_h((string)$notice['text']) . '</p>';
			}
		}
	}
	if ($status === 'open') {
		if (!empty($card['next_step'])) {
			$out .= '<p class="sac-next">' . saldi_ui_h((string)$card['next_step']) . '</p>';
		}
		if (!empty($card['expires_at'])) {
			$out .= '<p class="sac-expiry">' . $t(5819, 'Udløber {date}', ['date' => saldi_ui_local_time((string)$card['expires_at'])]) . '</p>';
		}
	}

	if ($mode === 'page') {
		$out .= saldi_approval_card_actions($item, $card, $opts, $db, $lang);
	}
	return $out . '</article>';
}

/** Decision forms for the Opgaver page (open: approve/reject + note; applied: undo). */
function saldi_approval_card_actions(array $item, array $card, array $opts, ?SaldiDb $db, int $lang): string
{
	$id = (int)($card['item_id'] ?? 0);
	$status = (string)($card['status'] ?? '');
	$canUndo = $status === 'applied' && !empty($card['can_undo']);
	$own = $status === 'open' && isset($opts['user_id']) && ($item['created_by'] ?? '') === 'user:' . (int)$opts['user_id'];
	if ($status !== 'open' && !$canUndo) {
		return '';
	}
	$t = static fn(int $textId, string $fallback): string => saldi_ui_h(saldi_ui_text($db, $lang, $textId, $fallback));
	$hidden = '<input type="hidden" name="csrf" value="' . saldi_ui_h((string)($opts['csrf'] ?? '')) . '">'
		. '<input type="hidden" name="thisDb" value="' . saldi_ui_h((string)($opts['this_db'] ?? '')) . '">'
		. '<input type="hidden" name="item_id" value="' . $id . '">'
		. '<input type="hidden" name="digest" value="' . saldi_ui_h((string)($card['digest'] ?? '')) . '">'
		. '<input type="hidden" name="tenant_hash" value="' . saldi_ui_h((string)($card['tenant_hash'] ?? '')) . '">'
		. '<input type="hidden" name="return" value="' . saldi_ui_h((string)($opts['return_query'] ?? '')) . '">';
	$action = saldi_ui_h((string)($opts['form_action'] ?? 'index.php'));
	$out = '<form method="post" action="' . $action . '" class="sac-actions" data-sac-form>' . $hidden;
	if ($status === 'open') {
		$noteId = 'sac-note-' . $id;
		$out .= '<details class="sac-note"><summary>' . $t(5820, 'Tilføj en note') . '</summary>'
			. '<label for="' . $noteId . '">' . $t(5895, 'Note til beslutningen (valgfri)') . '</label>'
			. '<textarea id="' . $noteId . '" name="note" maxlength="255" rows="2"></textarea></details>';
		$out .= '<div class="sac-buttons">';
		$out .= '<button type="submit" name="op" value="approve" class="sac-btn sac-btn--primary">' . saldi_ui_h((string)($card['approve_label'] ?? 'Godkend')) . '</button>';
		$out .= '<button type="submit" name="op" value="reject" class="sac-btn sac-btn--secondary">' . saldi_ui_h((string)($card['reject_label'] ?? 'Afvis')) . '</button>';
		if ($own) {
			$out .= '<button type="submit" name="op" value="cancel" class="sac-btn sac-btn--quiet">' . $t(5899, 'Træk tilbage') . '</button>';
		}
		$out .= '</div>';
	} else {
		$out .= '<div class="sac-buttons"><button type="submit" name="op" value="undo" class="sac-btn sac-btn--secondary">' . $t(5821, 'Fortryd') . '</button></div>';
	}
	return $out . '</form>';
}
