<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/assistent_rapport.php --- ai/mcp-assistant --- 2026.10.04
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
// "Assist-rapport" (SPEC-companion C8.4, K9): what Saldi Assist did in the last 30 or 90 days, from
// SaldiAssistReport::build(): proposals per kind and how they were decided, kassekladde suggestions,
// tool calls, routines and posting rules. Read only. Administrators only (settings.admin); everyone
// else gets a notice. The time saved is an estimate from stated assumptions, never a measurement,
// and is labelled so on the page (text 6004) with the assumptions and the arithmetic shown.
//
// 20261002 CL/LH Created.
// 20261004 CDX/LH Add companion part 2 routine toggles.
// 20261007 CL/LH V1 Stille: page head, period segment, summary strip, captioned cards with hairline tables
//                (css/saldi_assist_pages.css, scoped .sai-v2).
// 20261007 CL/LH Review: tools by Danish label (SALDI_ASSIST_REPORT_TOOLS), the ten busiest first and the rest
//                behind "Vis alle værktøjer"; unknown suggestion source says "Ukendt"; one Udskriv style;
//                full-sentence lead; comma in the routine title (6378).

/** Kind labels: text id and Danish fallback. */
const SALDI_ASSIST_REPORT_KINDS = [
	'journal.draft_entry' => [5701, 'Kladdelinjer'], 'memory.fact' => [5702, 'Gem oplysning'], 'memory.retract' => [5703, 'Fjern oplysning'],
	'followup.task' => [5704, 'Opfølgning'], 'routine.finding' => [5705, 'Rutinefund'], 'rule.posting' => [5900, 'Bogføringsregel'],
	'rule.retire' => [5901, 'Stop bogføringsregel'], 'party.create' => [5902, 'Ny kunde'], 'purchase_order.draft' => [5904, 'Ordreforslag til leverandør'],
	'reminder.draft' => [5905, 'Rykker'], 'support.report' => [5906, 'Supporthenvendelse'],
	'settlement.draft' => [6100, 'Udligning'], 'purchase_order.invoice_stage' => [6101, 'Faktura klar til modtagelse'],
	'sales_order.draft' => [6102, 'Tilbud'], 'credit_note.draft' => [6104, 'Kreditnota'],
];

/** Routine labels: text id and Danish fallback (the routine titles). */
const SALDI_ASSIST_REPORT_ROUTINES = [
	'overdue_debtors' => [5720, 'Forfaldne debitorposter'], 'stale_open_journals' => [6378, 'Kassekladder, der ikke er bogført'],
	'low_stock' => [5722, 'Varer under minimumsbeholdning'], 'vat_missing_documents' => [5940, 'Bilag mangler før momsfristen'],
	'supplier_bank_change' => [5941, 'Leverandørers bankoplysninger er ændret'], 'expiring_batches' => [5942, 'Batcher tæt på udløbsdato'],
	'till_difference' => [5943, 'Kassedifference ved kasseopgørelse'], 'posting_rule_candidates' => [5944, 'Gentagne posteringer, der kan blive til regler'],
	'reorder_suggestions' => [6170, 'Genbestilling ud fra salg'], 'master_data_hygiene' => [6171, 'Stamdata, der bør ryddes op'],
];

/**
 * Tool labels: text id and Danish fallback, and whether the tool makes a proposal ("Forslag: …"). An
 * unknown tool shows its id. Labels reuse the screen and kind names users already see in Saldi.
 */
const SALDI_ASSIST_REPORT_TOOLS = [
	'get_company_context' => [5836, 'Virksomhedens oplysninger', false], 'get_memory' => [5832, 'Hukommelse', false],
	'search_accounts' => [113, 'Kontoplan', false], 'get_account_balance' => [442, 'Kontosaldo', false],
	'list_ledger_entries' => [1910, 'Posteringer', false], 'get_trial_balance' => [6161, 'Saldobalance', false],
	'list_journals' => [639, 'Kladdeliste', false], 'get_journal' => [601, 'Kassekladde', false],
	'list_open_items' => [441, 'Åbne poster', false], 'search_parties' => [6384, 'Kunder og leverandører', false],
	'get_party' => [6382, 'Kunde eller leverandør', false], 'search_invoices' => [1777, 'Fakturaer', false],
	'get_invoice' => [643, 'Faktura', false], 'search_products' => [110, 'Varer', false],
	'list_inbox_items' => [5800, 'Opgaver', false], 'get_inbox_item' => [2800, 'Opgave', false],
	'propose_journal_entry' => [5701, 'Kladdelinjer', true], 'propose_memory_fact' => [5702, 'Gem oplysning', true],
	'propose_memory_retraction' => [5703, 'Fjern oplysning', true], 'create_followup_task' => [5704, 'Opfølgning', true],
	'list_posting_rules' => [5970, 'Bogføringsregler', false], 'suggest_posting' => [6388, 'Konteringsforslag', false],
	'propose_posting_rule' => [5900, 'Bogføringsregel', true], 'propose_rule_retirement' => [5901, 'Stop bogføringsregel', true],
	'list_documents' => [5955, 'Bilag i puljen', false], 'get_document' => [671, 'Bilag', false],
	'propose_supplier_invoice' => [580, 'Købsfaktura', true], 'propose_payment_registration' => [935, 'Betaling', true],
	'propose_party' => [5902, 'Ny kunde', true], 'propose_purchase_order' => [5904, 'Ordreforslag til leverandør', true],
	'propose_reminder' => [5905, 'Rykker', true], 'get_daily_overview' => [5950, 'Dagens overblik', false],
	'get_vat_summary' => [770, 'Moms', false], 'get_period_status' => [6386, 'Periodestatus', false],
	'list_procedures' => [6385, 'Vejledninger', false], 'get_procedure' => [92, 'Vejledning', false],
	'report_problem' => [5906, 'Supporthenvendelse', true], 'import_bank_statement' => [6120, 'Bankfil til kassekladde', false],
	'list_bank_lines' => [6125, 'Linjer i filen', false], 'propose_bank_entries' => [6120, 'Bankfil til kassekladde', true],
	'check_journal' => [6140, 'Tjek kladden', false], 'suggest_settlements' => [6100, 'Udligning', false],
	'propose_settlement' => [6100, 'Udligning', true], 'match_purchase_order' => [4986, 'Indkøbsordre', false],
	'propose_purchase_invoice_stage' => [6101, 'Faktura klar til modtagelse', true], 'suggest_reorders' => [6170, 'Genbestilling ud fra salg', false],
	'propose_sales_order' => [6102, 'Tilbud', true], 'propose_credit_note' => [6104, 'Kreditnota', true],
	'get_customer_statement' => [1803, 'Kontoudtog', false], 'compare_periods' => [6387, 'Sammenlign perioder', false],
	'get_year_end_pack' => [6160, 'Revisorpakke', false], 'get_till_summary' => [2261, 'Kasseoptælling', false],
	'get_stock_count' => [1956, 'Lageroptælling', false], 'check_master_data' => [6171, 'Stamdata, der bør ryddes op', false],
];

/** The busiest tools shown before "Vis alle værktøjer". */
const SALDI_ASSIST_REPORT_TOP_TOOLS = 10;

/** Danish label for a tool id (escaped); "Forslag: …" for tools that propose. Unknown ids show as they are. */
function saldi_assist_report_tool_label(string $tool, ?SaldiDb $db, int $lang): string
{
	if (!isset(SALDI_ASSIST_REPORT_TOOLS[$tool])) {
		return saldi_ui_h($tool);
	}
	[$id, $fallback, $proposes] = SALDI_ASSIST_REPORT_TOOLS[$tool];
	$label = saldi_ui_text($db, $lang, $id, $fallback);
	return saldi_ui_h($proposes ? saldi_ui_text($db, $lang, 5991, 'Forslag') . ': ' . $label : $label);
}

/** Kinds whose approval counts as "another draft" in the estimate (SaldiAssistReport). */
const SALDI_ASSIST_REPORT_OTHER_DRAFTS = ['party.create', 'purchase_order.draft', 'reminder.draft'];

/** Report window in days from the query: 30 (default) or 90. */
function saldi_assist_report_days(array $query): int
{
	return ($query['dage'] ?? '') === '90' ? 90 : 30;
}

/** @return array{0:string, 1:string} First and last Copenhagen date of a window ending today. */
function saldi_assist_report_range(int $days, ?DateTimeImmutable $today = null): array
{
	$today ??= new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen'));
	return [$today->modify('-' . ($days - 1) . ' days')->format('Y-m-d'), $today->format('Y-m-d')];
}

/** Danish number with up to $decimals decimals (trailing zeros dropped). */
function saldi_assist_report_number(float $value, int $decimals = 1): string
{
	$text = number_format($value, $decimals, ',', '.');
	return $decimals > 0 ? rtrim(rtrim($text, '0'), ',') : $text;
}

/** The report body: one table per section, then the clearly labelled estimate. */
function saldi_assist_report_html(array $report, ?SaldiDb $db, int $lang): string
{
	$t = static fn(int $id, string $fallback, array $vars = []): string => saldi_ui_h(saldi_ui_text($db, $lang, $id, $fallback, $vars));
	$dash = '&#8211;';
	$num = static fn(mixed $n): string => saldi_ui_h(saldi_assist_report_number((float)$n, 0));
	$share = static fn(?float $s): string => $s === null ? '&#8211;' : saldi_ui_h(saldi_assist_report_number($s * 100, 0) . ' %');
	$minutes = static fn(?float $m): string => $m === null ? '&#8211;' : saldi_ui_h(saldi_assist_report_number($m, $m < 10 ? 1 : 0) . ' min.');
	$empty = '<p class="sap-empty">' . $t(6077, 'Ingen data i perioden.') . '</p>';
	$table = static function (array $head, array $rows, array $numeric, string $caption = '', string $foot = '', int $visible = 0, string $more = '') use ($empty): string {
		if (!$rows) {
			return $empty;
		}
		$out = '<div class="sac-table-wrap"><table class="sai-table">' . ($caption !== '' ? '<caption class="sai-visually-hidden">' . $caption . '</caption>' : '') . '<thead><tr>';
		foreach ($head as $i => $label) {
			$out .= '<th scope="col"' . (in_array($i, $numeric, true) ? ' class="sai-num"' : '') . '>' . $label . '</th>';
		}
		$out .= '</tr></thead><tbody>';
		foreach ($rows as $index => $row) {
			// Rows past $visible go in their own tbody. Script folds it behind "Vis alle"; without script,
			// and in print, every row shows.
			if ($visible > 0 && $index === $visible) {
				$out .= '</tbody><tbody class="sap-more" data-sap-more>';
			}
			$out .= '<tr>';
			foreach ($row as $i => $cell) {
				$out .= $i === 0 ? '<th scope="row">' . $cell . '</th>' : '<td' . (in_array($i, $numeric, true) ? ' class="sai-num"' : '') . '>' . $cell . '</td>';
			}
			$out .= '</tr>';
		}
		$out .= '</tbody>' . $foot . '</table></div>';
		if ($visible > 0 && count($rows) > $visible) {
			$out .= '<div class="sap-card__body sap-more__bar sai-js-only"><button type="button" class="sap-btn sap-btn--quiet sap-btn--sm" data-sap-more-toggle aria-expanded="false">' . $more . '</button></div>';
		}
		return $out;
	};
	// A caption over one lifted card; the lead and loose text sit in the card's padded body, tables run edge to edge.
	$section = static fn(string $id, string $title, string $body, string $lead = ''): string => '<section class="sap-section" aria-labelledby="' . $id . '"><div class="sap-section__head"><h2 class="sap-cap" id="' . $id . '">' . $title . '</h2></div>'
		. '<div class="sap-card">' . ($lead !== '' ? '<div class="sap-card__body"><p class="sap-lead">' . $lead . '</p></div>' : '') . $body . '</div></section>';

	$kindRows = [];
	$applied = [];
	foreach ($report['kinds'] ?? [] as $kind) {
		$name = (string)$kind['kind'];
		$applied[$name] = (int)$kind['applied'];
		[$labelId, $labelFallback] = SALDI_ASSIST_REPORT_KINDS[$name] ?? [0, $name];
		$label = $labelId ? $t($labelId, $labelFallback) : saldi_ui_h($name);
		if ($name === 'party.create') {
			$label .= ' / ' . $t(5903, 'Ny leverandør');
		}
		if ($name === 'sales_order.draft') {
			$label .= ' / ' . $t(6103, 'Ordre');
		}
		$kindRows[] = [$label, $num($kind['proposed']), $num($kind['applied']), $num($kind['rejected']), $num($kind['expired']), $num($kind['cancelled']), $num($kind['undone']),
			$share(isset($kind['accept_share']) ? (float)$kind['accept_share'] : null), $minutes(isset($kind['median_minutes']) ? (float)$kind['median_minutes'] : null)];
	}
	$out = $section('sai-r-kinds', $t(6071, 'Forslag pr. type'), $table(
		[$t(2995, 'Type'), $t(5991, 'Forslag'), $t(5992, 'Godkendt'), $t(5993, 'Afvist'), $t(5994, 'Udløbet'), $t(5825, 'Trukket tilbage'), $t(5995, 'Fortrudt'), $t(5996, 'Godkendt andel'), $t(5997, 'Median tid til beslutning')],
		$kindRows, [1, 2, 3, 4, 5, 6, 7, 8], $t(6071, 'Forslag pr. type')
	));

	$sources = ['rule' => $t(5963, 'Fra regel'), 'history' => $t(6073, 'Tidligere posteringer')];
	$suggestionRows = [];
	$used = 0;
	foreach ($report['suggestions'] ?? [] as $row) {
		$used += (int)$row['used'];
		$suggestionRows[] = [$sources[(string)($row['source'] ?? '')] ?? $t(6389, 'Ukendt'), $num($row['shown']), $num($row['used']), $num($row['dismissed'])];
	}
	$duplicates = (int)($report['duplicates_warned'] ?? 0);
	$out .= $section('sai-r-suggestions', $t(6000, 'Forslag i kassekladden'), $table([$t(6072, 'Kilde'), $t(6001, 'Vist'), $t(6002, 'Brugt'), $t(6074, 'Skjult')], $suggestionRows, [1, 2, 3], $t(6000, 'Forslag i kassekladden'))
		. ($duplicates > 0 || $suggestionRows ? '<div class="sap-card__body"><p class="sai-hint" style="margin: 0">' . $t(5965, 'Mulig dublet:') . ' ' . $num($duplicates) . '</p></div>' : ''));

	$toolRows = [];
	$tools = $report['tools'] ?? [];
	// Busiest first, so the ten shown before "Vis alle værktøjer" are the ones that matter.
	usort($tools, static fn(array $a, array $b): int => [(int)$b['calls'], (string)$a['tool']] <=> [(int)$a['calls'], (string)$b['tool']]);
	foreach ($tools as $tool) {
		$p50 = $tool['p50_duration_ms'] ?? null;
		$p90 = $tool['p90_duration_ms'] ?? null;
		$toolRows[] = [saldi_assist_report_tool_label((string)$tool['tool'], $db, $lang), $num($tool['calls']), $num($tool['errors']) . ' (' . $share((float)$tool['error_share']) . ')',
			$p50 === null ? $dash : $num(round((float)$p50)) . ' / ' . ($p90 === null ? $dash : $num(round((float)$p90)))];
	}
	$out .= $section('sai-r-tools', $t(5998, 'Værktøjskald'), $table([$t(6075, 'Værktøj'), $t(5998, 'Værktøjskald'), $t(5999, 'Fejl'), $t(6076, 'Svartid median / 90 % (ms)')], $toolRows, [1, 2, 3], $t(5998, 'Værktøjskald'), '', SALDI_ASSIST_REPORT_TOP_TOOLS, $t(6390, 'Vis alle værktøjer ({n})', ['n' => count($toolRows)])));

	$routineRows = [];
	foreach ($report['routines'] ?? [] as $routine) {
		$name = (string)$routine['routine'];
		[$labelId, $labelFallback] = SALDI_ASSIST_REPORT_ROUTINES[$name] ?? [0, $name];
		$routineRows[] = [$labelId ? $t($labelId, $labelFallback) : saldi_ui_h($name), $num($routine['created']), $num($routine['acknowledged']), $num($routine['rule_proposals'])];
	}
	$out .= $section('sai-r-routines', $t(6078, 'Rutiner'), $table([$t(5711, 'Rutine'), $t(5705, 'Rutinefund'), $t(5802, 'Udført'), $t(5991, 'Forslag')], $routineRows, [1, 2, 3], $t(6078, 'Rutiner')));

	$rules = $report['rules'] ?? ['active_count' => 0, 'uses' => 0];
	$out .= $section('sai-r-rules', $t(5970, 'Bogføringsregler'), '<div class="sap-card__body"><p style="margin: 0">' . $t(5971, 'Aktive regler') . ': <strong class="sap-tabnum">' . $num($rules['active_count']) . '</strong> &#183; ' . $t(6002, 'Brugt') . ': <strong class="sap-tabnum">' . $num($rules['uses']) . '</strong></p></div>');

	// The estimate: counts x assumed minutes, every factor on screen, labelled as not measured.
	$estimate = $report['estimated_time_saved'] ?? ['minutes' => 0, 'assumptions' => []];
	$assume = $estimate['assumptions'] ?? [];
	$other = 0;
	foreach (SALDI_ASSIST_REPORT_OTHER_DRAFTS as $name) {
		$other += $applied[$name] ?? 0;
	}
	$lines = [
		[$t(6000, 'Forslag i kassekladden') . ' (' . $t(6002, 'Brugt') . ')', $used, (float)($assume['minutes_per_suggestion'] ?? 0.5)],
		[$t(5701, 'Kladdelinjer') . ' (' . $t(5992, 'Godkendt') . ')', $applied['journal.draft_entry'] ?? 0, (float)($assume['minutes_per_draft'] ?? 2.0)],
		[$t(5902, 'Ny kunde') . ', ' . $t(5903, 'Ny leverandør') . ', ' . $t(5904, 'Ordreforslag til leverandør') . ', ' . $t(5905, 'Rykker') . ' (' . $t(5992, 'Godkendt') . ')', $other, (float)($assume['minutes_per_other_draft'] ?? 1.0)],
	];
	$estimateRows = [];
	foreach ($lines as [$label, $count, $each]) {
		$estimateRows[] = [$label, $num($count), saldi_ui_h(saldi_assist_report_number($each, 2)), saldi_ui_h(saldi_assist_report_number($count * $each, 1))];
	}
	$total = saldi_ui_h(saldi_assist_report_number((float)$estimate['minutes'], 1) . ' min.');
	$foot = '<tfoot><tr><td>' . $t(2373, 'I alt') . '</td><td></td><td></td><td class="sai-num">' . $total . '</td></tr></tfoot>';
	$out .= $section('sai-r-estimate', $t(6003, 'Anslået sparet tid'),
		'<div class="sap-card__body"><p class="sap-estimate"><strong>' . $total . '</strong></p>'
		. '<p class="sap-lead" style="margin: 2px 0 0">' . $t(6004, 'Anslået ud fra antagelserne herunder, ikke målt.') . '</p></div>'
		. '<div class="sac-table-wrap"><table class="sai-table"><caption class="sai-visually-hidden">' . $t(6003, 'Anslået sparet tid') . '</caption><thead><tr><th scope="col"></th><th scope="col" class="sai-num">' . $t(916, 'Antal') . '</th><th scope="col" class="sai-num">' . $t(6079, 'Minutter pr. stk. (antagelse)') . '</th><th scope="col" class="sai-num">' . $t(2373, 'I alt') . '</th></tr></thead><tbody>'
		. implode('', array_map(static fn(array $r): string => '<tr><td>' . $r[0] . '</td><td class="sai-num">' . $r[1] . '</td><td class="sai-num">' . $r[2] . '</td><td class="sai-num">' . $r[3] . '</td></tr>', $estimateRows))
		. '</tbody>' . $foot . '</table></div>',
		'');
	return $out;
}

/**
 * The summary strip over the report: proposals, approvals, kassekladde suggestions used, tool calls, rules and
 * the time estimate (labelled as an estimate). Each figure links to its section.
 */
function saldi_assist_report_strip_html(array $report, ?SaldiDb $db, int $lang): string
{
	$t = static fn(int $id, string $fallback, array $vars = []): string => saldi_ui_h(saldi_ui_text($db, $lang, $id, $fallback, $vars));
	$num = static fn(mixed $n): string => saldi_ui_h(saldi_assist_report_number((float)$n, 0));
	$sum = static fn(array $rows, string $key): int => array_sum(array_map(static fn(array $r): int => (int)($r[$key] ?? 0), $rows));
	$proposed = $sum($report['kinds'] ?? [], 'proposed');
	$applied = $sum($report['kinds'] ?? [], 'applied');
	$tile = static fn(string $value, string $label, string $anchor, string $detail = ''): string => '<li class="sai-ov"><span class="sai-ov__value">' . $value . '</span>'
		. '<a class="sai-ov__label" href="#' . $anchor . '">' . $label . '</a>' . ($detail !== '' ? '<span class="sai-ov__detail">' . $detail . '</span>' : '') . '</li>';
	$share = $proposed > 0 ? $t(5996, 'Godkendt andel') . ' ' . saldi_ui_h(saldi_assist_report_number($applied / $proposed * 100, 0) . ' %') : '';
	$minutes = (float)($report['estimated_time_saved']['minutes'] ?? 0);
	return '<section class="sai-overview" aria-label="' . $t(5990, 'Assist-rapport') . '"><ul class="sai-overview__rows">'
		. $tile($num($proposed), $t(5991, 'Forslag'), 'sai-r-kinds')
		. $tile($num($applied), $t(5992, 'Godkendt'), 'sai-r-kinds', $share)
		. $tile($num($sum($report['suggestions'] ?? [], 'used')), $t(6000, 'Forslag i kassekladden'), 'sai-r-suggestions')
		. $tile($num($sum($report['tools'] ?? [], 'calls')), $t(5998, 'Værktøjskald'), 'sai-r-tools')
		. $tile($num($report['rules']['active_count'] ?? 0), $t(5971, 'Aktive regler'), 'sai-r-rules')
		. $tile(saldi_ui_h(saldi_assist_report_number($minutes, $minutes < 10 ? 1 : 0)) . '<small>min.</small>', $t(6003, 'Anslået sparet tid'), 'sai-r-estimate')
		. '</ul></section>';
}

if (defined('SALDI_ASSIST_REPORT_LIBRARY')) {
	// Tests: the helpers above only.
	require_once __DIR__ . '/../includes/runtime/bootstrap.php';
	require_once __DIR__ . '/../includes/automation/approvalCard.php';
	require_once __DIR__ . '/../includes/automation/SaldiAssistReport.php';
	return;
}

ob_start();
@session_start();
$s_id = session_id();
$modulnr = 0;
$title = 'Assist-rapport';
include __DIR__ . '/../includes/connect.php';
include __DIR__ . '/../includes/online.php';
require_once __DIR__ . '/../includes/runtime/bootstrap.php';
require_once __DIR__ . '/../includes/automation/approvalCard.php';
require_once __DIR__ . '/../includes/automation/SaldiAssistReport.php';
// online.php printed the legacy head; this page renders its own document.
ob_end_clean();

/**
 * Injected by ../includes/online.php, included above:
 * @var int|string $bruger_id
 * @var int|string $sprog_id
 * @var string $buttonColor
 * @var string $buttonTxtColor
 */

$lang = in_array((int)($sprog_id ?? 1), [1, 2, 3], true) ? (int)$sprog_id : 1;
$isUser = (int)($bruger_id ?? 0) > 0;
$days = saldi_assist_report_days(is_array($_GET) ? $_GET : []);
$tdb = null;
$isAdmin = false;
$report = null;
$loadFailed = false;
if ($isUser) {
	try {
		$open = saldi_runtime_context_for_session();
		saldi_runtime_ensure_schema($open['db']);
		$tdb = $open['db'];
		$isAdmin = SaldiPermissionService::allows($open['ctx'], $tdb, 'settings.admin');
		if ($isAdmin) {
			[$from, $to] = saldi_assist_report_range($days);
			$report = SaldiAssistReport::build($tdb, $from, $to);
		}
	} catch (Throwable $e) {
		error_log('systemdata/assistent_rapport.php: ' . get_class($e) . ' ' . $e->getMessage());
		$loadFailed = true;
	}
}

$accent = is_string($buttonColor ?? null) && preg_match('/^#[0-9a-fA-F]{3,8}$/D', $buttonColor) ? $buttonColor : '#114691';
$accentText = is_string($buttonTxtColor ?? null) && preg_match('/^#[0-9a-fA-F]{3,8}$/D', $buttonTxtColor) ? $buttonTxtColor : '#ffffff';
$t = static fn(int $id, string $fallback, array $vars = []): string => saldi_ui_h(saldi_ui_text($tdb, $lang, $id, $fallback, $vars));
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="<?= $lang === 2 ? 'en' : ($lang === 3 ? 'nb' : 'da') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $t(5990, 'Assist-rapport') ?></title>
<link rel="stylesheet" href="../css/approval_card.css?v=20261007">
<link rel="stylesheet" href="../css/saldi_assist_pages.css?v=20261007">
<script>document.documentElement.className += ' js';</script>
</head>
<body class="sai-body sai-v2" style="--sac-accent: <?= saldi_ui_h($accent) ?>; --sac-accent-text: <?= saldi_ui_h($accentText) ?>;">
<div class="sap-page">
	<header class="sai-head">
		<div>
			<h1 class="sai-h1"><?= $t(5990, 'Assist-rapport') ?></h1>
			<?php if ($report !== null) { ?><p class="sai-lead"><?= $t(6383, 'Her ser du, hvad Saldi Assist har lavet i perioden. Siden ændrer ikke noget i regnskabet.') ?></p><?php } ?>
		</div>
		<nav class="sap-head__links" aria-label="Saldi Assist">
			<a class="sai-head__link" href="assistent.php"><?= $t(5871, 'Assist-indstillinger') ?></a>
			<?php if ($isUser) { ?><a class="sai-head__link" href="../inbox/index.php"><?= $t(5800, 'Opgaver') ?></a><?php } ?>
		</nav>
	</header>
<?php if (!$isUser) { ?>
	<div class="sap-card" role="status"><p class="sap-empty"><?= $t(5830, 'Indstillinger for Saldi Assist kræver et brugerlogin.') ?></p></div>
<?php } elseif ($loadFailed) { ?>
	<div class="sap-card" role="alert"><p class="sap-empty"><?= $t(5740, 'Funktionen er midlertidigt utilgængelig.') ?></p></div>
<?php } elseif (!$isAdmin) { ?>
	<div class="sap-card" role="status"><p class="sap-empty"><?= $t(6070, 'Kun administratorer kan se Assist-rapporten.') ?></p></div>
<?php } else { ?>
	<div class="sap-toolbar">
		<nav class="sai-seg" aria-label="<?= $t(6005, 'Periode') ?>">
			<?php foreach ([30 => [6006, 'Sidste 30 dage'], 90 => [6007, 'Sidste 90 dage']] as $option => [$textId, $fallback]) {
				$current = $option === $days;
				print '<a class="sai-seg__tab' . ($current ? ' is-current' : '') . '" href="assistent_rapport.php' . ($option === 30 ? '' : '?dage=90') . '"' . ($current ? ' aria-current="page"' : '') . '>' . $t($textId, $fallback) . '</a>';
			} ?>
		</nav>
		<span class="sap-toolbar__meta"><?= saldi_ui_h(saldi_ui_cell($report['from'])['text'] . ' – ' . saldi_ui_cell($report['to'])['text']) ?></span>
		<span class="sap-toolbar__end"><button type="button" class="sap-btn sap-btn--secondary sap-btn--sm sai-js-only" data-sap-print><svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4.5 6V2.5h7V6"/><rect x="2.5" y="6" width="11" height="5.5" rx="1.25"/><path d="M4.5 9.5h7v4h-7z"/></svg><?= $t(6166, 'Udskriv') ?></button></span>
	</div>
	<?= saldi_assist_report_strip_html($report, $tdb, $lang) ?>
	<?= saldi_assist_report_html($report, $tdb, $lang) ?>
<?php } ?>
</div>
<script>
document.addEventListener('click', function (event) {
	var target = event.target && event.target.closest ? event.target : null;
	if (!target) { return; }
	if (target.closest('[data-sap-print]')) { window.print(); }
	var more = target.closest('[data-sap-more-toggle]');
	if (more) {
		var card = more.closest('.sap-card');
		var tail = card ? card.querySelector('[data-sap-more]') : null;
		if (tail) { tail.classList.add('is-open'); }
		more.parentNode.hidden = true;
	}
});
</script>
</body>
</html>
