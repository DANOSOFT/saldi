<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- inbox/index.php --- ai/mcp-assistant --- 2026.10.04
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
// "Opgaver": the automation inbox (SPEC 7.1). Proposals from Saldi Assist, other
// agents and the daily routines wait here until a person approves or rejects them.
// Controller first (POST approve / reject / undo / cancel with CSRF, thisDb, digest
// and tenant hash; Post/Redirect/Get), then the view. Every rule is enforced again
// by SaldiInboxService; the page only collects the human decision.
//
// 20261002 CL/LH Created.
// 20261002 CL/LH Browse past the newest 100 items: offset paging, one query per tab.
// 20261002 CL/LH Companion C5.1/C5.2: "Dagens overblik" card on the first page of Åbne, silent items
//                under their own "Stille" tab, interrupt items first with a "Vigtigt" badge, labels for
//                the companion kinds. Render helpers load without the page (SALDI_INBOX_PAGE_LIBRARY).
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.
// 20261004 CDX/LH Add companion part 2 routine toggles.
// 20261006 CL/LH Redesign: overview strip, Åbne/Stille/Afgjort views, grouped compact rows with the card in a
//                detail pane or unfolded in place (javascript/saldi-inbox-page.js), filter and keyboard shortcuts.

/** Kind filter labels: text id and Danish fallback. */
const SALDI_INBOX_KIND_LABELS = [
	'journal.draft_entry' => [5701, 'Kladdelinjer'], 'memory.fact' => [5702, 'Gem oplysning'], 'memory.retract' => [5703, 'Fjern oplysning'],
	'followup.task' => [5704, 'Opfølgning'], 'routine.finding' => [5705, 'Rutinefund'], 'rule.posting' => [5900, 'Bogføringsregel'],
	'rule.retire' => [5901, 'Stop bogføringsregel'], 'party.create' => [5902, 'Ny kunde'], 'purchase_order.draft' => [5904, 'Ordreforslag til leverandør'],
	'reminder.draft' => [5905, 'Rykker'], 'support.report' => [5906, 'Supporthenvendelse'],
	'settlement.draft' => [6100, 'Udligning'], 'purchase_order.invoice_stage' => [6101, 'Faktura klar til modtagelse'],
	'sales_order.draft' => [6102, 'Tilbud'], 'credit_note.draft' => [6104, 'Kreditnota'],
];

/**
 * The "Dagens overblik" strip (SPEC-companion C5.2) from SaldiDailyOverview::build(): one tile per
 * figure, each a SaldiScreens link opened through the shell, and the open interrupt items as "Vigtigt"
 * pills. Sections absent from $overview (no permission) are not shown; rows with nothing in them are
 * left out, except the VAT deadline, which is always useful.
 */
function saldi_inbox_overview_html(array $overview, ?SaldiDb $db, int $lang): string
{
	$t = static fn(int $id, string $fallback, array $vars = []): string => saldi_ui_h(saldi_ui_text($db, $lang, $id, $fallback, $vars));
	$date = static fn(?string $iso): string => $iso === null ? '' : saldi_ui_h(saldi_ui_cell($iso)['text']);
	$link = static function (string $label, ?string $screen): string {
		if ($screen === null || !SaldiScreens::isAllowed($screen)) {
			return '<span class="sai-ov__label">' . $label . '</span>';
		}
		return '<a class="sai-ov__label" href="../' . saldi_ui_h($screen) . '" data-saldi-screen="' . saldi_ui_h($screen) . '">' . $label . '</a>';
	};
	$tile = static fn(string $label, string $value, string $detail = '', string $modifier = '', string $title = ''): string => '<li class="sai-ov' . ($modifier !== '' ? ' sai-ov--' . $modifier : '') . '"' . ($title !== '' ? ' title="' . $title . '"' : '') . '>'
		. '<span class="sai-ov__value">' . $value . '</span>' . $label . ($detail !== '' ? '<span class="sai-ov__detail">' . $detail . '</span>' : '') . '</li>';
	$tiles = '';
	$flags = '';
	if (isset($overview['open_items'])) {
		$open = $overview['open_items'];
		$kinds = [];
		foreach ($open['by_kind'] ?? [] as $kind) {
			$kinds[] = (int)$kind['count'] . ' ' . saldi_ui_h((string)$kind['label']);
		}
		$tiles .= $tile('<span class="sai-ov__label">' . $t(5951, 'Åbne opgaver') . '</span>', (string)(int)$open['count'], implode(' &#183; ', $kinds), '', implode(' &#183; ', $kinds));
		foreach (array_slice($open['interrupts'] ?? [], 0, 3) as $interrupt) {
			$flags .= '<a class="sai-vig" href="index.php?item=' . (int)$interrupt['item_id'] . '"><span class="sai-flag">' . $t(5958, 'Vigtigt') . '</span>' . saldi_ui_h((string)$interrupt['title']) . '</a>';
		}
	}
	if (isset($overview['overdue_debtors']) && (int)$overview['overdue_debtors']['count'] > 0) {
		$debtors = $overview['overdue_debtors'];
		$detail = saldi_ui_h(saldi_ui_cell((string)$debtors['amount'])['text'] . ' ' . $debtors['currency']);
		if (empty($debtors['converted'])) {
			$detail .= ' <span class="sai-ov__hint">' . $t(6093, 'Poster uden valutakurs er ikke regnet med.') . '</span>';
		}
		$tiles .= $tile($link($t(5952, 'Forfaldne debitorposter'), SaldiScreens::link('reminders')), (string)(int)$debtors['count'], $detail);
	}
	if (isset($overview['open_journals']) && (int)$overview['open_journals']['count'] > 0) {
		$journals = $overview['open_journals'];
		$detail = $journals['oldest_date'] !== null ? $t(6094, 'ældste {date}', ['date' => saldi_ui_cell((string)$journals['oldest_date'])['text']]) : '';
		$tiles .= $tile($link($t(5953, 'Kassekladder, der ikke er bogført'), SaldiScreens::link('journal_list')), (string)(int)$journals['count'], $detail);
	}
	if (isset($overview['vat'])) {
		$vat = $overview['vat'];
		$days = (int)$vat['days_left'];
		$missing = (int)$vat['missing_documents'];
		$detail = $date($vat['deadline']);
		if ($missing > 0) {
			$detail .= ' &#183; ' . $t(5940, 'Bilag mangler før momsfristen') . ': ' . $missing;
		}
		// The period and the "always check skat.dk" note stay on the tile as its tooltip.
		$detail .= '<span class="sai-ov__hint sai-visually-hidden">' . $t(5975, 'Momsperiode') . ' ' . $date($vat['period']['from']) . ' &#8211; ' . $date($vat['period']['to']) . '. ' . saldi_ui_h((string)$vat['note']) . '</span>';
		$tiles .= $tile($link($t(5954, 'Momsfrist'), SaldiScreens::link('vat_periods')), (string)$days . ' ' . $t(5969, 'dage'), $detail, $days <= 14 && $missing > 0 ? 'attention' : '', $t(5975, 'Momsperiode') . ' ' . $date($vat['period']['from']) . ' &#8211; ' . $date($vat['period']['to']) . '. ' . saldi_ui_h((string)$vat['note']));
	}
	if (isset($overview['pool_documents']) && (int)$overview['pool_documents']['count'] > 0) {
		$tiles .= $tile($link($t(5955, 'Bilag i puljen'), SaldiScreens::link('document_pool')), (string)(int)$overview['pool_documents']['count']);
	}
	if (isset($overview['low_stock']) && (int)$overview['low_stock']['count'] > 0) {
		// No product list screen is on the navigation allowlist; the low-stock routine links each product.
		$tiles .= $tile('<span class="sai-ov__label">' . $t(5956, 'Varer under minimum') . '</span>', (string)(int)$overview['low_stock']['count']);
	}
	$out = '<section class="sai-overview" aria-labelledby="sai-overview-title">';
	$out .= '<h2 class="sai-overview__title sai-visually-hidden" id="sai-overview-title">' . $t(5950, 'Dagens overblik') . ' <span class="sai-overview__date">' . $date($overview['date'] ?? null) . '</span></h2>';
	if (!empty($overview['nothing_to_do'])) {
		$out .= '<p class="sai-overview__calm">' . $t(5957, 'Intet kræver din opmærksomhed i dag.') . '</p>';
	}
	if ($tiles !== '') {
		$out .= '<ul class="sai-overview__rows">' . $tiles . '</ul>';
	}
	if ($flags !== '') {
		$out .= '<p class="sai-overview__flags">' . $flags . '</p>';
	}
	return $out . '</section>';
}

/** Card markup for one list item: open interrupt items carry the "Vigtigt" badge (C5.1). */
function saldi_inbox_item_html(array $item, array $cardOpts, ?SaldiDb $db, int $lang): string
{
	$item = SaldiInboxService::publicItem($item);
	$card = saldi_approval_card_html($item, $cardOpts);
	if (($item['status'] ?? '') !== 'open' || ($item['attention'] ?? '') !== 'interrupt') {
		return $card;
	}
	return '<div class="sai-item sai-item--interrupt"><span class="sai-flag sai-item__flag">' . saldi_ui_h(saldi_ui_text($db, $lang, 5958, 'Vigtigt')) . '</span>' . $card . '</div>';
}

/**
 * Time of an item as the list shows it, relative to $now (Copenhagen): the clock on the same day,
 * "i går", or "2. okt." Older than a year adds the year. Also tells which day group the time falls in.
 *
 * @return array{text:string, day:string} day is 'today', 'yesterday' or 'earlier'
 */
function saldi_inbox_when(?string $isoUtc, DateTimeImmutable $now, ?SaldiDb $db, int $lang): array
{
	if ($isoUtc === null || $isoUtc === '') {
		return ['text' => '', 'day' => 'earlier'];
	}
	try {
		$local = (new DateTimeImmutable($isoUtc, new DateTimeZone('UTC')))->setTimezone($now->getTimezone());
	} catch (Exception $e) {
		return ['text' => '', 'day' => 'earlier'];
	}
	$day = $local->format('Y-m-d');
	if ($day === $now->format('Y-m-d')) {
		return ['text' => $local->format('H.i'), 'day' => 'today'];
	}
	if ($day === $now->modify('-1 day')->format('Y-m-d')) {
		return ['text' => saldi_ui_text($db, $lang, 6321, 'i går'), 'day' => 'yesterday'];
	}
	return ['text' => saldi_inbox_short_date($local, $now, $lang), 'day' => 'earlier'];
}

/** "19. okt." in the viewer's language; the year is added when it differs from $now's. */
function saldi_inbox_short_date(DateTimeImmutable $local, DateTimeImmutable $now, int $lang): string
{
	$months = [
		2 => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
		3 => ['jan.', 'feb.', 'mars', 'apr.', 'mai', 'juni', 'juli', 'aug.', 'sep.', 'okt.', 'nov.', 'des.'],
	][$lang] ?? ['jan.', 'feb.', 'mar.', 'apr.', 'maj', 'jun.', 'jul.', 'aug.', 'sep.', 'okt.', 'nov.', 'dec.'];
	$text = ($lang === 2 ? $local->format('j') . ' ' : $local->format('j') . '. ') . $months[(int)$local->format('n') - 1];
	return $local->format('Y') === $now->format('Y') ? $text : $text . ' ' . $local->format('Y');
}

/**
 * One line under the row title: the card's fields ("Kunde: Astrid Fejrø · Gebyr: 0,00 kr"), or the
 * start of the proposer's explanation when there are no fields. Plain text, cut to about 110 characters.
 */
function saldi_inbox_row_summary(array $card): string
{
	$parts = [];
	foreach (is_array($card['fields'] ?? null) ? $card['fields'] : [] as $field) {
		if (is_array($field) && trim((string)($field['value'] ?? '')) !== '') {
			$parts[] = trim((string)($field['label'] ?? '')) . ': ' . saldi_ui_cell($field['value'])['text'];
		}
	}
	$text = $parts ? implode(' · ', $parts) : trim(preg_replace('/\s+/u', ' ', (string)($card['why'] ?? '')) ?? '');
	return mb_strlen($text) > 110 ? rtrim(mb_substr($text, 0, 109)) . '…' : $text;
}

/**
 * One list row: the compact head the list shows, and the full ApprovalCard underneath it (shown in
 * place, or moved into the detail pane by javascript/saldi-inbox-page.js). Decided items show their
 * state instead of the expiry.
 */
function saldi_inbox_row_html(array $item, string $cardHtml, DateTimeImmutable $now, ?SaldiDb $db, int $lang): string
{
	$card = is_array($item['card'] ?? null) ? $item['card'] : [];
	$id = (int)$item['id'];
	$status = (string)($item['status'] ?? 'open');
	$provenance = (string)($card['provenance'] ?? '');
	$title = (string)($card['title'] ?? $item['title'] ?? '');
	$when = saldi_inbox_when((string)($status === 'open' ? $item['created_at'] : ($item['decided_at'] ?? $item['created_at'])), $now, $db, $lang);
	$out = '<li class="sai-row" id="sai-row-' . $id . '" data-item-id="' . $id . '" data-filter="' . saldi_ui_h(mb_strtolower($title . ' ' . (string)($card['kind_label'] ?? ''))) . '">';
	$out .= '<div class="sai-row__head" role="button" tabindex="0" aria-expanded="false" aria-controls="sai-row-card-' . $id . '">';
	$out .= '<span class="sai-row__meta"><span class="sac-kind sac-kind--' . (in_array($provenance, ['agent', 'ai'], true) ? 'assist' : 'neutral') . '">' . saldi_ui_h((string)($card['kind_label'] ?? '')) . '</span>'
		. '<span class="sai-row__who">' . saldi_ui_h((string)($card['provenance_label'] ?? '')) . '</span></span>';
	$out .= '<span class="sai-row__title">' . saldi_ui_h($title) . '</span>';
	$summary = saldi_inbox_row_summary($card);
	if ($summary !== '') {
		$out .= '<span class="sai-row__sub">' . saldi_ui_h($summary) . '</span>';
	}
	$out .= '<span class="sai-row__when"><b>' . saldi_ui_h($when['text']) . '</b>';
	if ($status === 'open') {
		if (!empty($card['expires_at'])) {
			try {
				$expires = (new DateTimeImmutable((string)$card['expires_at'], new DateTimeZone('UTC')))->setTimezone($now->getTimezone());
				$out .= '<span title="' . saldi_ui_h(saldi_ui_text($db, $lang, 5819, 'Udløber {date}', ['date' => saldi_ui_local_time((string)$card['expires_at'])])) . '">' . saldi_ui_h(saldi_inbox_short_date($expires, $now, $lang)) . '</span>';
			} catch (Exception $e) {
				// No expiry shown.
			}
		}
	} else {
		$view = saldi_approval_status_view($card, $db, $lang);
		$out .= '<span class="sac-status sac-status--' . saldi_ui_h($view['tone']) . '">' . saldi_ui_h($view['label']) . '</span>';
	}
	$out .= '</span></div>';
	$out .= '<div class="sai-row__card" id="sai-row-card-' . $id . '">' . $cardHtml . '</div>';
	return $out . '</li>';
}

/**
 * Rows grouped the way the list shows them: open interrupt items under "Vigtigt", then today's and
 * earlier items; decided items by decision day. Groups with nothing in them are left out.
 *
 * @return list<array{key:string, label:string, items:list<array>}>
 */
function saldi_inbox_groups(array $items, string $tab, DateTimeImmutable $now, ?SaldiDb $db, int $lang): array
{
	$groups = [
		'important' => ['key' => 'important', 'label' => saldi_ui_text($db, $lang, 5958, 'Vigtigt'), 'items' => []],
		'today' => ['key' => 'today', 'label' => saldi_ui_text($db, $lang, 6312, 'I dag'), 'items' => []],
		'earlier' => ['key' => 'earlier', 'label' => saldi_ui_text($db, $lang, 6313, 'Tidligere'), 'items' => []],
	];
	foreach ($items as $item) {
		$status = (string)($item['status'] ?? 'open');
		if ($tab === 'open' && $status === 'open' && ($item['attention'] ?? '') === 'interrupt') {
			$groups['important']['items'][] = $item;
			continue;
		}
		$when = saldi_inbox_when((string)($status === 'open' ? $item['created_at'] : ($item['decided_at'] ?? $item['created_at'])), $now, $db, $lang);
		$groups[$when['day'] === 'today' ? 'today' : 'earlier']['items'][] = $item;
	}
	return array_values(array_filter($groups, static fn(array $g): bool => $g['items'] !== []));
}

/** One overview_viewed event per user and day (Europe/Copenhagen), best effort. */
function saldi_inbox_overview_viewed(SaldiDb $db, SaldiTenantContext $ctx, string $date): bool
{
	if (!$db->hasTable('assist_events')) {
		return false;
	}
	return $db->transaction(static function () use ($db, $ctx, $date): bool {
		$db->lock('saldi_overview_viewed:' . $ctx->userId);
		if ($db->value("SELECT 1 FROM assist_events WHERE event = 'overview_viewed' AND user_id = ? AND subject = ?", [$ctx->userId, 'overview:' . $date]) !== null) {
			return false;
		}
		$db->insert('assist_events', ['created_at' => gmdate('Y-m-d H:i:s'), 'user_id' => $ctx->userId, 'event' => 'overview_viewed', 'subject' => 'overview:' . $date]);
		return true;
	});
}

if (defined('SALDI_INBOX_PAGE_LIBRARY')) {
	// Tests: the helpers above only, with action.php loaded as a library by the caller.
	require_once __DIR__ . '/action.php';
	require_once __DIR__ . '/../includes/automation/SaldiDailyOverview.php';
	return;
}

ob_start();
@session_start();
$s_id = session_id();
$modulnr = 0;
$title = 'Opgaver';
include __DIR__ . '/../includes/connect.php';
include __DIR__ . '/../includes/online.php';
define('SALDI_INBOX_ACTION_LIBRARY', true);
require_once __DIR__ . '/action.php';
require_once __DIR__ . '/../includes/automation/SaldiDailyOverview.php';
// online.php printed the legacy head; this page renders its own document.
ob_end_clean();

/**
 * Injected by ../includes/online.php, included above:
 * @var int|string $bruger_id
 * @var string $db
 * @var int|string $sprog_id
 * @var string $buttonColor
 * @var string $buttonTxtColor
 */

// Tabs, view state and paging live in action.php (one tested library for the page and the modal).

/**
 * Handle one posted decision. Returns the flash for the next GET and the redirect target.
 *
 * @return array{flash: array, location: string}
 */
function saldi_inbox_page_post(array $post, string $sessionDb, ?array $open, ?Throwable $openError, array $kinds, int $lang): array
{
	parse_str(is_string($post['return'] ?? null) ? $post['return'] : '', $returnQuery);
	$state = saldi_inbox_view_state(is_array($returnQuery) ? $returnQuery : [], $kinds);
	$itemId = isset($post['item_id']) && is_string($post['item_id']) && preg_match('/^[1-9][0-9]{0,9}$/D', $post['item_id']) ? (int)$post['item_id'] : 0;
	$op = is_string($post['op'] ?? null) && in_array($post['op'], ['approve', 'reject', 'undo', 'cancel'], true) ? $post['op'] : '';
	$location = 'index.php' . (($q = saldi_inbox_view_query($state)) !== '' ? '?' . $q : '') . ($itemId > 0 ? '#sac-item-' . $itemId : '');
	$db = $open['db'] ?? null;
	$flash = ['item_id' => $itemId, 'op' => $op, 'ok' => false];
	if (!saldi_inbox_csrf_check(is_string($post['csrf'] ?? null) ? $post['csrf'] : null)) {
		$flash['error'] = ['code' => 'PERMISSION_DENIED', 'message_da' => saldi_ui_text($db, $lang, 5888, 'Siden er udløbet. Genindlæs den, og prøv igen.')];
		return ['flash' => $flash, 'location' => $location];
	}
	if (!is_string($post['thisDb'] ?? null) || $post['thisDb'] !== $sessionDb) {
		$flash['error'] = ['code' => 'CONFLICT', 'message_da' => saldi_ui_text($db, $lang, 5883, 'Du har skiftet regnskab i et andet vindue. Genindlæs siden.')];
		return ['flash' => $flash, 'location' => $location];
	}
	if ($open === null || $op === '' || $itemId <= 0) {
		$flash['error'] = saldi_approval_error($openError ?? new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid decision'), $db, $lang);
		return ['flash' => $flash, 'location' => $location];
	}
	$note = is_string($post['note'] ?? null) ? trim($post['note']) : '';
	try {
		$inbox = new SaldiInboxService($open['db'], SaldiKindRegistry::default());
		$result = saldi_inbox_perform($inbox, $open['ctx'], $op, $itemId, is_string($post['digest'] ?? null) ? $post['digest'] : '', is_string($post['tenant_hash'] ?? null) ? $post['tenant_hash'] : '', $note === '' ? null : mb_substr($note, 0, 255));
		$flash['ok'] = true;
		$flash['status'] = $result['item']['status'];
	} catch (Throwable $e) {
		if (!$e instanceof SaldiRuntimeError) {
			error_log('inbox/index.php: ' . get_class($e) . ' ' . $e->getMessage());
		}
		$flash['error'] = saldi_approval_error($e, $db, $lang);
	}
	return ['flash' => $flash, 'location' => $location];
}

// ---------------------------------------------------------------- controller
$lang = in_array((int)($sprog_id ?? 1), [1, 2, 3], true) ? (int)$sprog_id : 1;
$isUser = (int)($bruger_id ?? 0) > 0;
$open = null;
$openError = null;
$kinds = [];
if ($isUser) {
	try {
		$open = saldi_runtime_context_for_session();
		saldi_runtime_ensure_schema($open['db']);
		$registry = SaldiKindRegistry::default();
		$kinds = $registry->names();
	} catch (Throwable $e) {
		error_log('inbox/index.php: ' . get_class($e) . ' ' . $e->getMessage());
		$open = null;
		$openError = $e;
	}
}
if ($isUser && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	// online.php empties $_POST when the session switched company since the form was rendered.
	$posted = is_array($_POST) ? $_POST : ['csrf' => $_SESSION['inbox_csrf'] ?? '', 'thisDb' => ''];
	$handled = saldi_inbox_page_post($posted, (string)$db, $open, $openError, $kinds, $lang);
	$_SESSION['inbox_flash'] = $handled['flash'];
	header('Location: ' . $handled['location'], true, 303);
	exit;
}
$flash = null;
if (isset($_SESSION['inbox_flash']) && is_array($_SESSION['inbox_flash'])) {
	$flash = $_SESSION['inbox_flash'];
	unset($_SESSION['inbox_flash']);
}

$state = saldi_inbox_view_state(is_array($_GET) ? $_GET : [], $kinds);
$tdb = $open['db'] ?? null;
$items = [];
$focusMissing = false;
$openCount = null;
$tabCounts = ['open' => null, 'silent' => 0];
$overview = null;
$hasMore = false;
$loadError = $isUser && $open === null ? saldi_approval_error($openError ?? new SaldiRuntimeError('UNAVAILABLE', 'Unavailable'), null, $lang) : null;
if ($open !== null) {
	try {
		$inbox = new SaldiInboxService($open['db'], SaldiKindRegistry::default());
		// The shell badge keeps counting every open item; the tabs split them by attention.
		$openCount = $inbox->countOpen($open['ctx']);
		$tabCounts = saldi_inbox_tab_counts($open['db'], $open['ctx']);
		if ($state['item'] > 0) {
			try {
				$items = [$inbox->get($open['ctx'], $state['item'])];
			} catch (SaldiRuntimeError $e) {
				if ($e->errorCode !== 'NOT_FOUND') {
					throw $e;
				}
				$focusMissing = true;
			}
		} else {
			['items' => $items, 'has_more' => $hasMore] = saldi_inbox_page_items($inbox, $open['ctx'], $state, $open['db']);
		}
	} catch (Throwable $e) {
		error_log('inbox/index.php: ' . get_class($e) . ' ' . $e->getMessage());
		$loadError = saldi_approval_error($e, $tdb, $lang);
		$items = [];
	}
	if ($loadError === null && $state['tab'] === 'open' && $state['item'] === 0 && $state['offset'] === 0 && $state['kind'] === '') {
		// The overview is extra: if it fails, the list still shows.
		try {
			$overview = SaldiDailyOverview::build($open['ctx'], $open['db']);
			saldi_inbox_overview_viewed($open['db'], $open['ctx'], (string)$overview['date']);
		} catch (Throwable $e) {
			error_log('inbox/index.php overview: ' . get_class($e) . ' ' . $e->getMessage());
		}
	}
}

// ---------------------------------------------------------------- view
$accent = is_string($buttonColor ?? null) && preg_match('/^#[0-9a-fA-F]{3,8}$/D', $buttonColor) ? $buttonColor : '#114691';
$accentText = is_string($buttonTxtColor ?? null) && preg_match('/^#[0-9a-fA-F]{3,8}$/D', $buttonTxtColor) ? $buttonTxtColor : '#ffffff';
$t = static fn(int $id, string $fallback, array $vars = []): string => saldi_ui_h(saldi_ui_text($tdb, $lang, $id, $fallback, $vars));
$csrf = $isUser ? saldi_inbox_csrf_token() : '';
$returnQuery = saldi_inbox_view_query($state);
$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen'));
$decidedCount = null;
if ($open !== null && $loadError === null) {
	try {
		$decidedCount = saldi_inbox_decided_count($open['db'], $open['ctx']);
	} catch (Throwable $e) {
		$decidedCount = null;
	}
}

// The outcome banner after a redirect. Errors on a card that is still shown go on the card.
$cardError = [];
$banner = null;
if ($flash) {
	$shownIds = array_map(static fn(array $i): int => (int)$i['id'], $items);
	if (!empty($flash['error'])) {
		if (in_array((int)$flash['item_id'], $shownIds, true)) {
			$cardError[(int)$flash['item_id']] = $flash['error'];
		} else {
			$banner = ['tone' => 'warn', 'text' => $flash['error']['message_da'], 'strong' => saldi_ui_text($tdb, $lang, 5822, 'Intet er ændret.'), 'item' => (int)$flash['item_id']];
		}
	} elseif (!empty($flash['ok'])) {
		$decided = null;
		if ($open !== null && (int)$flash['item_id'] > 0) {
			try {
				$decided = (new SaldiInboxService($open['db'], SaldiKindRegistry::default()))->get($open['ctx'], (int)$flash['item_id']);
			} catch (Throwable $e) {
				$decided = null;
			}
		}
		$view = $decided ? saldi_approval_status_view($decided['card'], $tdb, $lang) : ['label' => '', 'detail' => ''];
		$receipt = $decided ? saldi_approval_receipt($decided, $tdb, $lang) : null;
		// Follow-ups and routine findings: the receipt already says it ("Markeret som udført.").
		$labelled = $decided && !in_array($decided['kind'], ['followup.task', 'routine.finding'], true);
		$banner = [
			'tone' => ($flash['status'] ?? '') === 'applied' ? 'done' : 'neutral',
			'text' => trim(($labelled || !$receipt ? $view['label'] . '. ' : '') . ($receipt['text'] ?? $view['detail']), '. ') . '.',
			'strong' => '',
			'screen' => $receipt['screen'] ?? null,
			'screen_label' => $receipt['screen_label'] ?? null,
			'item' => $state['item'] > 0 ? 0 : (int)$flash['item_id'],
		];
	}
}

// Three views: Åbne, Stille (only while silent items exist) and Afgjort. The older per-status tabs
// (done, rejected, expired) still answer their URLs and show as Afgjort.
$tabLabels = ['open' => [5801, 'Åbne'], 'silent' => [6091, 'Stille'], 'decided' => [6310, 'Afgjort']];
if ($tabCounts['silent'] === 0 && $state['tab'] !== 'silent') {
	unset($tabLabels['silent']);
}
$currentTab = in_array($state['tab'], ['open', 'silent'], true) ? $state['tab'] : 'decided';
$kindLabels = SALDI_INBOX_KIND_LABELS;
$groups = $items ? saldi_inbox_groups($items, $state['tab'], $now, $tdb, $lang) : [];
$cardOpts = [
	'db' => $tdb, 'lang' => $lang, 'mode' => 'page', 'csrf' => $csrf, 'this_db' => (string)$db,
	'form_action' => 'index.php' . ($returnQuery !== '' ? '?' . $returnQuery : ''), 'return_query' => $returnQuery,
	'user_id' => (int)$bruger_id,
];
?>
<!DOCTYPE html>
<html lang="<?= $lang === 2 ? 'en' : ($lang === 3 ? 'nb' : 'da') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $t(5800, 'Opgaver') ?></title>
<link rel="stylesheet" href="../css/approval_card.css?v=20261006">
<script>document.documentElement.className += ' js';</script>
</head>
<body class="sai-body sai-v2" style="--sac-accent: <?= saldi_ui_h($accent) ?>; --sac-accent-text: <?= saldi_ui_h($accentText) ?>;">
<div class="sai-page sai-page--wide">
	<header class="sai-head">
		<div>
			<h1 class="sai-h1"><?= $t(5800, 'Opgaver') ?></h1>
			<?php if ($isUser) { ?>
			<p class="sai-lead"><?= $t(5809, 'Forslag fra Saldi Assist og fund fra de daglige rutiner lander her. Intet ændres i regnskabet, før du godkender det.') ?></p>
			<?php } ?>
		</div>
		<?php if ($isUser) { ?>
		<a class="sai-head__link" href="../systemdata/assistent.php"><?= $t(5881, 'Indstillinger for Saldi Assist') ?></a>
		<?php } ?>
	</header>
<?php if (!$isUser) { ?>
	<div class="sai-panel sai-empty" role="status">
		<p class="sai-empty__title"><?= $t(5811, 'Opgaver kræver et brugerlogin.') ?></p>
		<p><?= $t(5812, 'Du er logget ind som revisor eller administrator. Log ind som bruger i regnskabet for at se og behandle opgaver.') ?></p>
	</div>
<?php } else { ?>
	<?php if ($overview !== null) {
		print saldi_inbox_overview_html($overview, $tdb, $lang) . "\n";
	} ?>
	<?php if ($state['item'] > 0) { ?>
	<p class="sai-back"><a href="index.php"><?= $t(5880, 'Vis alle opgaver') ?></a></p>
	<?php } else { ?>
	<div class="sai-toolbar">
		<nav class="sai-seg" aria-label="<?= $t(5898, 'Visning') ?>">
			<?php foreach ($tabLabels as $tab => [$textId, $fallback]) {
				$current = $currentTab === $tab;
				$query = saldi_inbox_view_query($state, ['tab' => $tab, 'item' => 0, 'limit' => 25, 'offset' => 0]);
				$tabCount = $tab === 'open' ? ($tabCounts['open'] ?? $openCount) : ($tab === 'silent' ? $tabCounts['silent'] : $decidedCount);
				$count = $tabCount !== null ? ' <span class="sai-seg__count">' . (int)$tabCount . '</span>' : '';
				print '<a class="sai-seg__tab' . ($current ? ' is-current' : '') . '" href="index.php' . ($query !== '' ? '?' . saldi_ui_h($query) : '') . '"' . ($current ? ' aria-current="page"' : '') . '>' . $t($textId, $fallback) . $count . '</a>';
			} ?>
		</nav>
		<form class="sai-filter" method="get" action="index.php" data-sai-kind-form>
			<?php if ($state['tab'] !== 'open') { ?><input type="hidden" name="tab" value="<?= saldi_ui_h($state['tab']) ?>"><?php } ?>
			<label for="sai-kind" class="sai-visually-hidden"><?= $t(2995, 'Type') ?></label>
			<select id="sai-kind" name="kind" class="sai-select">
				<option value=""><?= $t(5805, 'Alle typer') ?></option>
				<?php foreach ($kinds as $kind) {
					[$textId, $fallback] = $kindLabels[$kind] ?? [0, $kind];
					$label = $textId ? $t($textId, $fallback) : saldi_ui_h($kind);
					if ($kind === 'sales_order.draft') {
						$label .= ' / ' . $t(6103, 'Ordre');
					}
					print '<option value="' . saldi_ui_h($kind) . '"' . ($state['kind'] === $kind ? ' selected' : '') . '>' . $label . '</option>';
				} ?>
			</select>
			<button type="submit" class="sac-btn sac-btn--secondary sai-js-hide"><?= $t(1133, 'Vis') ?></button>
		</form>
		<label class="sai-search sai-js-only">
			<span class="sai-visually-hidden"><?= $t(6311, 'Filtrér listen') ?></span>
			<input type="search" id="sai-search" placeholder="<?= $t(6311, 'Filtrér listen') ?>" autocomplete="off">
		</label>
		<p class="sai-keys sai-js-only" aria-label="<?= $t(6322, 'Genvejstaster') ?>"><kbd>&uarr;</kbd><kbd>&darr;</kbd> <?= $t(6314, 'vælg') ?> <kbd>&crarr;</kbd> <?= $t(6318, 'fold ud') ?><?php if ($currentTab !== 'decided') { ?> <kbd>G</kbd> <?= $t(6315, 'godkend') ?> <kbd>A</kbd> <?= $t(6316, 'afvis') ?> <kbd>N</kbd> <?= $t(6317, 'note') ?><?php } ?></p>
	</div>
	<?php } ?>
	<?php if ($banner) { ?>
	<div class="sai-banner sai-banner--<?= saldi_ui_h($banner['tone']) ?>" role="<?= $banner['tone'] === 'warn' ? 'alert' : 'status' ?>" tabindex="-1" id="sai-banner">
		<p><?= saldi_ui_h($banner['text']) ?><?php if ($banner['strong'] !== '') { ?> <strong><?= saldi_ui_h($banner['strong']) ?></strong><?php } ?></p>
		<p class="sai-banner__links">
			<?php if (!empty($banner['screen']) && SaldiScreens::isAllowed($banner['screen'])) { ?>
			<a class="sac-link" href="../<?= saldi_ui_h($banner['screen']) ?>" data-saldi-screen="<?= saldi_ui_h($banner['screen']) ?>"><?= saldi_ui_h((string)$banner['screen_label']) ?></a>
			<?php } ?>
			<?php if ($banner['item'] > 0) { ?>
			<a class="sac-link" href="index.php?item=<?= (int)$banner['item'] ?>"><?= $t(5873, 'Vis opgaven') ?></a>
			<?php } ?>
		</p>
	</div>
	<?php } ?>
	<?php if ($state['tab'] === 'silent' && $state['item'] === 0) { ?>
	<p class="sai-hint sai-tab-lead"><?= $t(6092, 'Stille opgaver kræver ikke handling nu. De vises ikke under Åbne og giver ingen besked.') ?></p>
	<?php } elseif ($currentTab === 'decided' && $state['item'] === 0) { ?>
	<p class="sai-hint sai-tab-lead"><?= $t(6323, 'Afgjorte opgaver viser, hvad der blev gjort. De kan ikke ændres her.') ?></p>
	<?php } ?>
	<main class="sai-list" id="sai-main" data-sai-list>
	<?php if ($loadError) { ?>
		<div class="sai-panel sai-empty" role="alert">
			<p class="sai-empty__title"><?= $t(5878, 'Opgaver er midlertidigt utilgængelige. Prøv igen om lidt.') ?></p>
		</div>
	<?php } elseif ($focusMissing) { ?>
		<div class="sai-panel sai-empty" role="status">
			<p class="sai-empty__title"><?= $t(5813, 'Opgaven findes ikke, eller du har ikke adgang til den.') ?></p>
		</div>
	<?php } elseif (!$items) { ?>
		<div class="sai-panel sai-empty" role="status">
			<p class="sai-empty__title"><?= $state['tab'] === 'open' && $state['kind'] === '' ? $t(5808, 'Ingen åbne opgaver.') : $t(5810, 'Der er ingen opgaver i denne visning.') ?></p>
		</div>
	<?php } elseif ($state['item'] > 0) {
		// One item: the card on its own, as the shell's "Vis opgaven" and the banner link expect.
		foreach ($items as $item) {
			print saldi_inbox_item_html($item, $cardOpts + ['error' => $cardError[(int)$item['id']] ?? null], $tdb, $lang) . "\n";
		}
	} else { ?>
		<div class="sai-rows" data-sai-rows>
		<?php foreach ($groups as $group) { ?>
			<section class="sai-group sai-group--<?= saldi_ui_h($group['key']) ?>" aria-labelledby="sai-group-<?= saldi_ui_h($group['key']) ?>">
				<h2 class="sai-group__title" id="sai-group-<?= saldi_ui_h($group['key']) ?>"><?= saldi_ui_h($group['label']) ?> <span class="sai-group__count"><?= count($group['items']) ?></span></h2>
				<ul class="sai-group__rows">
				<?php foreach ($group['items'] as $item) {
					$cardHtml = saldi_inbox_item_html($item, $cardOpts + ['error' => $cardError[(int)$item['id']] ?? null], $tdb, $lang);
					print saldi_inbox_row_html($item, $cardHtml, $now, $tdb, $lang) . "\n";
				} ?>
				</ul>
			</section>
		<?php } ?>
			<p class="sai-empty sai-rows__none" hidden data-sai-none><?= $t(6319, 'Intet matcher filteret.') ?></p>
		<?php if ($hasMore || $state['offset'] > 0) { ?>
			<p class="sai-more">
				<?php if ($state['offset'] > 0) { ?><a href="index.php<?= ($q = saldi_inbox_view_query($state, ['limit' => 25, 'offset' => 0])) !== '' ? '?' . saldi_ui_h($q) : '' ?>"><?= $t(5758, 'Vis nyeste') ?></a><?php } ?>
				<?php if ($hasMore) { ?><a href="index.php?<?= saldi_ui_h(saldi_inbox_view_query($state, saldi_inbox_next_page($state))) ?>"><?= $t(5882, 'Vis flere') ?></a><?php } ?>
			</p>
		<?php } ?>
		</div>
		<aside class="sai-detail sai-js-only" aria-label="<?= $t(6324, 'Valgt opgave') ?>" data-sai-detail>
			<p class="sai-detail__empty"><?= $t(6325, 'Vælg en opgave i listen.') ?></p>
		</aside>
	<?php } ?>
	</main>
<?php } ?>
</div>
<script src="../javascript/saldi-inbox-page.js?v=20261006" defer></script>
<script>
(function () {
	'use strict';
	var openCount = <?= $openCount === null ? 'null' : (int)$openCount ?>;
	var inShell = window.parent && window.parent !== window;
	// Keep the shell's sidebar badge in step with what this page just counted.
	if (openCount !== null && inShell) {
		try { if (window.parent.SaldiInbox && window.parent.SaldiInbox.setBadge) { window.parent.SaldiInbox.setBadge(openCount); } } catch (e) { /* not the Saldi shell */ }
	}
	// Evidence and receipt links go through the shell's own navigation (unsaved-changes guard, history).
	document.addEventListener('click', function (event) {
		var link = event.target && event.target.closest ? event.target.closest('a[data-saldi-screen]') : null;
		if (!link || !inShell) { return; }
		try {
			if (typeof window.parent.update_iframe === 'function') {
				event.preventDefault();
				window.parent.update_iframe('/' + link.getAttribute('data-saldi-screen'));
			}
		} catch (e) { /* fall back to the plain link */ }
	});
	// One decision per click: lock the card's buttons once the form is on its way.
	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!form || !form.hasAttribute('data-sac-form')) { return; }
		if (form.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
		form.setAttribute('aria-busy', 'true');
		window.setTimeout(function () {
			Array.prototype.forEach.call(form.querySelectorAll('button'), function (b) { b.disabled = true; });
		}, 0);
	});
	var banner = document.getElementById('sai-banner');
	if (banner) { banner.focus(); }
})();
</script>
</body>
</html>
