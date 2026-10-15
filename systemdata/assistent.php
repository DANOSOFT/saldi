<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/assistent.php --- ai/mcp-assistant --- 2026.10.05
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
// "Indstillinger for Saldi Assist" (SPEC 7.3): what the assistant remembers, the
// personal AI access keys (MCP grants) and, for administrators, the tenant switches.
// Every logged-in tenant user reaches it ($modulnr = 0). Each mutation is POST only
// with CSRF + thisDb, Post/Redirect/Get, and the services enforce the rules again
// (SaldiMemoryStore, SaldiGrantService, SaldiSettings). A new access key is shown
// exactly once, from a one-shot session flash, and is never stored in clear.
//
// 20261002 CDX/LH Add companion routine toggles.
// 20261002 CL/LH Created.
// 20261002 CL/LH The one-time key flash is bound to the issuing company and user and names the company.
//                The MCP URL comes from the shared request-origin helper.
// 20261002 CL/LH Companion C8.3: Bogføringsregler (active rules, "Stop regel" for journal.draft users),
//                per-user daily overview mail, and the admin settings for kassekladde suggestions, VAT
//                period, interrupt budget and till-difference threshold; link to the Assist report.
//                Helpers load without the page (SALDI_ASSISTENT_LIBRARY).
// 20261004 CDX/LH Add companion part 2 routine toggles.
// 20261004 CDX/LH Add lead days, atomic settings saves and the year-end report link.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.
// 20261007 CL/LH V1 Stille: page head, status strip, section segment, captioned cards, switches and choice
//                pills, hairline tables, sticky save bar (css/saldi_assist_pages.css, scoped .sai-v2).
// 20261007 CL/LH Review: one save pattern (Dagens overblik uses the same save bar as Indstillinger), card
//                sub-heads instead of a second caption level, "Alle adgange" only when it adds grants, full
//                sentence lead and rules link, comma in the routine title (6378), one empty state.

/** Tenant on/off switches: group, name, label id + fallback, help id + fallback (0 = none). */
const SALDI_ASSIST_SWITCHES = [
	['assistant', 'tools_enabled', 5864, 'Saldi Assist må slå op i regnskabet og lave forslag', 5865, ''],
	['assistant', 'external_agents', 5866, 'Eksterne AI-værktøjer må få adgang', 5867, ''],
	['assistant', 'journal_suggestions', 5973, 'Forslag i kassekladden', 0, ''],
	['automation', 'routine.overdue_debtors', 5720, 'Forfaldne debitorposter', 0, ''],
	['automation', 'routine.stale_open_journals', 6378, 'Kassekladder, der ikke er bogført', 0, ''],
	['automation', 'routine.low_stock', 5722, 'Varer under minimumsbeholdning', 0, ''],
	['automation', 'routine.vat_missing_documents', 5940, 'Bilag mangler før momsfristen', 0, ''],
	['automation', 'routine.supplier_bank_change', 5941, 'Leverandørers bankoplysninger er ændret', 6098, 'Kører hver time.'],
	['automation', 'routine.expiring_batches', 5942, 'Batcher tæt på udløbsdato', 0, ''],
	['automation', 'routine.till_difference', 5943, 'Kassedifference ved kasseopgørelse', 0, ''],
	['automation', 'routine.reorder_suggestions', 6170, 'Genbestilling ud fra salg', 6099, 'Kører hver mandag.'],
	['automation', 'routine.master_data_hygiene', 6171, 'Stamdata, der bør ryddes op', 6099, 'Kører hver mandag.'],
	['automation', 'routine.posting_rule_candidates', 5944, 'Gentagne posteringer, der kan blive til regler', 6099, 'Kører hver mandag.'],
];

/** VAT period choices for assistant/vat_period (C5.2). */
const SALDI_ASSIST_VAT_PERIODS = ['month' => [5976, 'Måned'], 'quarter' => [5977, 'Kvartal'], 'half' => [5978, 'Halvår']];

/**
 * The admin value settings from the posted form, checked before anything is saved (SaldiSettings::set
 * checks them again). Null when any value is malformed.
 *
 * @return array{vat_period:string, interrupt_budget:string, till_difference_threshold:string, lead_days:string}|null
 */
function saldi_assistent_admin_values(array $post): ?array
{
	$vat = is_string($post['vat_period'] ?? null) ? $post['vat_period'] : '';
	$budget = is_string($post['interrupt_budget'] ?? null) ? trim($post['interrupt_budget']) : '';
	$threshold = is_string($post['till_difference_threshold'] ?? null) ? trim($post['till_difference_threshold']) : '';
	$lead = is_string($post['lead_days'] ?? '14') ? trim($post['lead_days'] ?? '14') : '';
	if (!preg_match('/^[0-9]{1,3}$/D', $lead) || (int)$lead < 1 || (int)$lead > 120
		|| !isset(SALDI_ASSIST_VAT_PERIODS[$vat]) || !preg_match('/^[0-9]{1,2}$/D', $budget) || (int)$budget > 10
		|| !preg_match('/^[0-9]{1,6}$/D', $threshold) || (int)$threshold > 100000) {
		return null;
	}
	return ['vat_period' => $vat, 'interrupt_budget' => (string)(int)$budget, 'till_difference_threshold' => (string)(int)$threshold, 'lead_days' => (string)(int)$lead];
}

/**
 * Handle one posted change. The services check ownership, journal.draft and settings.admin again.
 *
 * @return array{flash: array{tone:string, text:string}, token: ?array{token:string, grant_id:int}}
 */
function saldi_assistent_post(array $post, string $sessionDb, ?array $open, int $lang): array
{
	$db = $open['db'] ?? null;
	$fail = static fn(string $text): array => ['flash' => ['tone' => 'warn', 'text' => $text], 'token' => null];
	if (!saldi_inbox_csrf_check(is_string($post['csrf'] ?? null) ? $post['csrf'] : null)) {
		return $fail(saldi_ui_text($db, $lang, 5888, 'Siden er udløbet. Genindlæs den, og prøv igen.'));
	}
	if (!is_string($post['thisDb'] ?? null) || $post['thisDb'] !== $sessionDb) {
		return $fail(saldi_ui_text($db, $lang, 5883, 'Du har skiftet regnskab i et andet vindue. Genindlæs siden.'));
	}
	if ($open === null) {
		return $fail(saldi_ui_text(null, $lang, 5740, 'Funktionen er midlertidigt utilgængelig.'));
	}
	$ctx = $open['ctx'];
	$int = static fn(string $key): int => isset($post[$key]) && is_string($post[$key]) && preg_match('/^[1-9][0-9]{0,9}$/D', $post[$key]) ? (int)$post[$key] : 0;
	$op = is_string($post['op'] ?? null) ? $post['op'] : '';
	$saved = ['flash' => ['tone' => 'neutral', 'text' => saldi_ui_text($db, $lang, 3322, 'Gemt') . '.'], 'token' => null];
	try {
		switch ($op) {
			case 'retract':
				SaldiMemoryStore::retract($db, $ctx, $int('memory_id'), 'settings');
				return ['flash' => ['tone' => 'neutral', 'text' => saldi_ui_text($db, $lang, 5891, 'Oplysningen er fjernet fra Saldi Assists hukommelse.')], 'token' => null];
			case 'revoke':
				(new SaldiGrantService(saldi_runtime_master(), $db, $ctx))->revoke($int('grant_id'));
				return ['flash' => ['tone' => 'neutral', 'text' => saldi_ui_text($db, $lang, 5855, 'Tilbagekaldt') . '.'], 'token' => null];
			case 'create':
				$scopes = ($post['scope'] ?? '') === 'draft' ? ['read', 'draft'] : ['read'];
				$days = in_array($post['ttl'] ?? '', ['30', '90', '365'], true) ? (int)$post['ttl'] : 0;
				$label = is_string($post['label'] ?? null) ? trim($post['label']) : '';
				$issued = (new SaldiGrantService(saldi_runtime_master(), $db, $ctx))->issuePersonal($label, $scopes, $days);
				return ['flash' => ['tone' => 'done', 'text' => ''], 'token' => ['token' => $issued['token'], 'grant_id' => (int)$issued['grant']['id']]];
			case 'retire_rule':
				$ruleId = $int('rule_id');
				if ($ruleId <= 0) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid rule');
				}
				SaldiPostingRules::retire($db, $ctx, $ruleId, 'retired');
				return ['flash' => ['tone' => 'neutral', 'text' => saldi_ui_text($db, $lang, 6097, 'Reglen er stoppet.')], 'token' => null];
			case 'digest':
				$email = is_string($post['digest_email'] ?? null) ? trim($post['digest_email']) : '';
				if (mb_strlen($email) > 100) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid user preference');
				}
				// The address first: a rejected address leaves both preferences as they were.
				SaldiSettings::setUser($db, $ctx, 'assistant', 'digest_email', $email);
				SaldiSettings::setUser($db, $ctx, 'assistant', 'digest', ($post['digest'] ?? '') === 'daily' ? 'daily' : 'off');
				return $saved;
			case 'switches':
				$values = saldi_assistent_admin_values($post);
				if ($values === null) {
					throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid tenant setting');
				}
				SaldiPermissionService::require($ctx, $db, 'settings.admin');
				$db->transaction(static function () use ($post, $values, $db, $ctx): void {
					$on = is_array($post['on'] ?? null) ? $post['on'] : [];
					foreach (SALDI_ASSIST_SWITCHES as [$group, $name]) {
						$value = in_array($group . '/' . $name, $on, true) ? 'on' : 'off';
						if (SaldiSettings::get($db, $group, $name) !== $value) {
							SaldiSettings::set($db, $ctx, $group, $name, $value);
						}
					}
					$defaults = ['vat_period' => 'quarter', 'interrupt_budget' => '3', 'till_difference_threshold' => '50', 'lead_days' => '14'];
					foreach ($values as $name => $value) {
						if (SaldiSettings::get($db, 'assistant', $name, $defaults[$name]) !== $value) {
							SaldiSettings::set($db, $ctx, 'assistant', $name, $value);
						}
					}
				});
				return ['flash' => ['tone' => 'neutral', 'text' => saldi_ui_text($db, $lang, 3322, 'Gemt') . '.'], 'token' => null];
		}
		throw new SaldiRuntimeError('VALIDATION_ERROR', 'Unknown operation');
	} catch (Throwable $e) {
		if (!$e instanceof SaldiRuntimeError) {
			error_log('systemdata/assistent.php: ' . get_class($e) . ' ' . $e->getMessage());
		}
		if ($e instanceof SaldiRuntimeError && $e->getMessage() === 'Personal grant limit reached') {
			return $fail(saldi_ui_text($db, $lang, 5879, 'Du har allerede 5 aktive adgange. Tilbagekald en, før du opretter en ny.'));
		}
		return $fail(saldi_approval_error($e, $db, $lang)['message_da']);
	}
}

/**
 * "Bogføringsregler" table (C8.3) from SaldiRuleTools::listRules rows. "Stop regel" only when the
 * viewer may retire rules (journal.draft); the service checks that again.
 */
function saldi_assistent_rules_html(array $rules, bool $canRetire, string $hidden, ?SaldiDb $db, int $lang): string
{
	$t = static fn(int $id, string $fallback, array $vars = []): string => saldi_ui_h(saldi_ui_text($db, $lang, $id, $fallback, $vars));
	if (!$rules) {
		return '<p class="sap-empty">' . $t(5982, 'Ingen aktive regler. Assist foreslår regler, når den ser de samme posteringer flere gange.') . '</p>';
	}
	$confirm = saldi_ui_text($db, $lang, 6096, 'Stop reglen? Den giver ikke flere forslag. Tidligere posteringer ændres ikke.');
	$out = '<div class="sac-table-wrap"><table class="sai-table"><caption class="sai-visually-hidden">' . $t(5971, 'Aktive regler') . '</caption><thead><tr>'
		. '<th scope="col">' . $t(5920, 'Når') . '</th><th scope="col">' . $t(5921, 'Konto') . '</th><th scope="col">' . $t(5922, 'Moms') . '</th>'
		. '<th scope="col">' . $t(5923, 'Tekst på linjen') . '</th><th scope="col">' . $t(65, 'Oprettet') . '</th><th scope="col" class="sai-num">' . $t(6095, 'Brugt 90 dage') . '</th>'
		. ($canRetire ? '<th scope="col"><span class="sai-visually-hidden">' . $t(5972, 'Stop regel') . '</span></th>' : '') . '</tr></thead><tbody>';
	foreach ($rules as $rule) {
		$vat = (string)($rule['vat_code'] ?? '');
		$vatText = $vat === '' ? $t(5934, 'Kontoens momskode') : ($vat === 'none' ? $t(5935, 'Momsfri') : saldi_ui_h($vat));
		$note = trim((string)($rule['note'] ?? ''));
		$out .= '<tr><td>' . saldi_ui_h((string)$rule['match']['label']) . ($note !== '' ? '<span class="sai-facts__meta">' . saldi_ui_h($note) . '</span>' : '') . '</td>'
			. '<td>' . saldi_ui_h(trim($rule['account'] . ' ' . $rule['account_name'])) . '</td>'
			. '<td>' . $vatText . '</td>'
			. '<td>' . ((string)($rule['description'] ?? '') !== '' ? saldi_ui_h((string)$rule['description']) : '&#8211;') . '</td>'
			. '<td class="sap-tabnum">' . saldi_ui_h(saldi_ui_local_time((string)$rule['created_at'], false)) . '</td>'
			. '<td class="sai-num">' . (int)$rule['uses_90d'] . '</td>';
		if ($canRetire) {
			$out .= '<td class="sap-actions"><form method="post" action="assistent.php" data-confirm="' . saldi_ui_h($confirm) . '">' . $hidden
				. '<input type="hidden" name="op" value="retire_rule"><input type="hidden" name="rule_id" value="' . (int)$rule['rule_id'] . '">'
				. '<button type="submit" class="sac-btn sac-btn--secondary sap-btn--sm">' . $t(5972, 'Stop regel') . '<span class="sai-visually-hidden">: ' . saldi_ui_h((string)$rule['match']['label']) . '</span></button></form></td>';
		}
		$out .= '</tr>';
	}
	return $out . '</tbody></table></div>';
}

/** One switch row: the label and help on the left, a native checkbox drawn as a switch on the right. */
function saldi_assistent_switch_html(string $id, string $name, string $value, bool $on, string $label, string $help = ''): string
{
	return '<div class="sap-switch"><div class="sap-switch__text"><label for="' . $id . '">' . $label . '</label>'
		. ($help !== '' ? '<p id="' . $id . '-help">' . $help . '</p>' : '') . '</div>'
		. '<input type="checkbox" class="sap-toggle" role="switch" id="' . $id . '" name="' . $name . '" value="' . saldi_ui_h($value) . '"' . ($on ? ' checked' : '')
		. ($help !== '' ? ' aria-describedby="' . $id . '-help"' : '') . '></div>';
}

/**
 * The per-user daily overview mail form (C8.3 section 5). Same save pattern as the administrator form:
 * one Gem in the bar at the foot of the card, with the unsaved-changes hint. The address field is dimmed,
 * not disabled, while the mail is off, so the saved address is kept.
 */
function saldi_assistent_digest_html(array $digest, string $hidden, ?SaldiDb $db, int $lang): string
{
	$t = static fn(int $id, string $fallback, array $vars = []): string => saldi_ui_h(saldi_ui_text($db, $lang, $id, $fallback, $vars));
	return '<form method="post" action="assistent.php" class="sap-form sap-digest" data-sap-dirty>' . $hidden . '<input type="hidden" name="op" value="digest">'
		. '<div class="sap-card__body"><div class="sap-switches"><div class="sap-switch"><div class="sap-switch__text"><label for="sai-digest-on">' . $t(5974, 'Dagligt overblik på mail') . '</label></div>'
		. '<input type="checkbox" class="sap-toggle" role="switch" id="sai-digest-on" name="digest" value="daily"' . (!empty($digest['on']) ? ' checked' : '') . '></div></div>'
		. '<div class="sap-fields sap-digest__email" style="margin-top: 6px"><div class="sap-field"><label for="sai-digest-email">' . $t(5981, 'Mailadresse til overblikket') . '</label>'
		. '<input type="email" id="sai-digest-email" name="digest_email" maxlength="100" autocomplete="email" value="' . saldi_ui_h((string)($digest['email'] ?? '')) . '"'
		. ((string)($digest['default_email'] ?? '') !== '' ? ' placeholder="' . saldi_ui_h((string)$digest['default_email']) . '"' : '') . '></div></div></div>'
		. '<div class="sap-bar"><span class="sap-bar__hint" role="status" aria-live="polite" data-sap-dirty-hint data-text="' . $t(6343, 'Du har ændringer, der ikke er gemt.') . '"></span>'
		. '<span class="sap-bar__end"><button type="submit" class="sac-btn">' . $t(3, 'Gem') . '</button></span></div></form>';
}

/**
 * The status strip under the page head: whether Assist and external tools are on, and how many keys,
 * remembered facts, rules and routines there are. Each figure links to its section on the page.
 *
 * @param array{tools:bool, external:bool, grants:int, facts:int, rules:?int, routines_on:int, routines_total:int, admin:bool} $s
 */
function saldi_assistent_strip_html(array $s, ?SaldiDb $db, int $lang): string
{
	$t = static fn(int $id, string $fallback, array $vars = []): string => saldi_ui_h(saldi_ui_text($db, $lang, $id, $fallback, $vars));
	$tile = static function (string $value, string $label, string $anchor, bool $off = false): string {
		$label = $anchor !== '' ? '<a class="sai-ov__label" href="#' . $anchor . '">' . $label . '</a>' : '<span class="sai-ov__label">' . $label . '</span>';
		return '<li class="sai-ov' . ($off ? ' sai-ov--off' : '') . '"><span class="sai-ov__value">' . $value . '</span>' . $label . '</li>';
	};
	$switches = !empty($s['admin']) ? 'sai-switches' : '';
	$out = '<section class="sai-overview" aria-label="' . $t(5881, 'Indstillinger for Saldi Assist') . '"><ul class="sai-overview__rows">';
	$out .= $tile(!empty($s['tools']) ? $t(6337, 'Til') : $t(6338, 'Fra'), 'Saldi Assist', $switches, empty($s['tools']));
	$out .= $tile(!empty($s['external']) ? $t(6337, 'Til') : $t(6338, 'Fra'), $t(6345, 'Eksterne AI-værktøjer'), $switches, empty($s['external']));
	$out .= $tile((string)(int)$s['grants'], $t(6341, 'Aktive adgange'), 'sai-access');
	$out .= $tile((string)(int)$s['facts'], $t(6340, 'Huskede oplysninger'), 'sai-memory');
	if ($s['rules'] !== null) {
		$out .= $tile((string)(int)$s['rules'], $t(5971, 'Aktive regler'), 'sai-rules');
	}
	if (!empty($s['admin'])) {
		$out .= $tile($t(6342, '{n} af {total}', ['n' => (int)$s['routines_on'], 'total' => (int)$s['routines_total']]), $t(6078, 'Rutiner'), 'sai-routines');
	}
	return $out . '</ul></section>';
}

/**
 * The administrator form (op=switches): the assistant switches, the deadline and limit values and the
 * routine switches, saved together with one "Gem" in a sticky bar.
 *
 * @param array<string,bool> $switches "group/name" => on
 * @param array{vat_period:string, interrupt_budget:string, till_difference_threshold:string, lead_days:string} $values
 */
function saldi_assistent_admin_html(array $switches, array $values, string $hidden, ?SaldiDb $db, int $lang): string
{
	$t = static fn(int $id, string $fallback, array $vars = []): string => saldi_ui_h(saldi_ui_text($db, $lang, $id, $fallback, $vars));
	$rows = ['assistant' => '', 'automation' => ''];
	$on = 0;
	foreach (SALDI_ASSIST_SWITCHES as $i => [$group, $name, $labelId, $labelFallback, $helpId, $helpFallback]) {
		$key = $group . '/' . $name;
		$on += $group === 'automation' && !empty($switches[$key]) ? 1 : 0;
		$rows[$group] .= saldi_assistent_switch_html('sai-switch-' . $i, 'on[]', $key, !empty($switches[$key]), $t($labelId, $labelFallback), $helpId ? $t($helpId, $helpFallback) : '');
	}
	$total = count(array_filter(SALDI_ASSIST_SWITCHES, static fn(array $s): bool => $s[0] === 'automation'));
	$vat = '';
	foreach (SALDI_ASSIST_VAT_PERIODS as $period => [$periodId, $periodFallback]) {
		$vat .= '<label><input type="radio" name="vat_period" value="' . saldi_ui_h($period) . '"' . ($values['vat_period'] === $period ? ' checked' : '') . '><span>' . $t($periodId, $periodFallback) . '</span></label>';
	}
	return '<form method="post" action="assistent.php" class="sap-form" data-sap-dirty>' . $hidden . '<input type="hidden" name="op" value="switches">'
		. '<div class="sap-card__body"><h3 class="sap-sub">Saldi Assist</h3><div class="sap-switches">' . $rows['assistant'] . '</div></div>'
		. '<div class="sap-card__body"><h3 class="sap-sub">' . $t(6344, 'Frister og grænser') . '</h3><div class="sap-fields" style="margin-top: 12px">'
		. '<div class="sap-field"><span class="sap-label" id="sai-vat-period-label">' . $t(5975, 'Momsperiode') . '</span><div class="sap-choice" role="radiogroup" aria-labelledby="sai-vat-period-label">' . $vat . '</div></div>'
		. '<div class="sap-field"><label for="sai-budget">' . $t(5979, 'Højst antal vigtige beskeder pr. dag') . '</label><input type="number" id="sai-budget" name="interrupt_budget" min="0" max="10" step="1" required value="' . saldi_ui_h($values['interrupt_budget']) . '"></div>'
		. '<div class="sap-field"><label for="sai-till">' . $t(5980, 'Grænse for kassedifference (kr.)') . '</label><input type="number" id="sai-till" name="till_difference_threshold" min="0" max="100000" step="1" required value="' . saldi_ui_h($values['till_difference_threshold']) . '"></div>'
		. '<div class="sap-field"><label for="sai-lead-days">' . $t(6300, 'Leveringstid ved genbestilling (dage)') . '</label><input type="number" id="sai-lead-days" name="lead_days" min="1" max="120" step="1" required value="' . saldi_ui_h($values['lead_days']) . '"></div>'
		. '</div><p class="sap-hint">' . $t(5968, 'Fristen er beregnet ud fra momsperioden i Assist-indstillinger. Tjek altid skat.dk.') . '</p></div>'
		. '<div class="sap-card__body" id="sai-routines"><h3 class="sap-sub">' . $t(6078, 'Rutiner') . ' <span class="sap-sub__meta">' . $t(6342, '{n} af {total}', ['n' => $on, 'total' => $total]) . '</span></h3>'
		. '<div class="sap-switches">' . $rows['automation'] . '</div></div>'
		. '<div class="sap-bar"><span class="sap-bar__hint" role="status" aria-live="polite" data-sap-dirty-hint data-text="' . $t(6343, 'Du har ændringer, der ikke er gemt.') . '"></span>'
		. '<span class="sap-bar__end"><button type="submit" class="sac-btn">' . $t(3, 'Gem') . '</button></span></div></form>';
}

if (defined('SALDI_ASSISTENT_LIBRARY')) {
	// Tests: the helpers above only.
	require_once __DIR__ . '/../includes/runtime/bootstrap.php';
	require_once __DIR__ . '/../includes/automation/SaldiMemoryStore.php';
	require_once __DIR__ . '/../includes/automation/SaldiPostingRules.php';
	require_once __DIR__ . '/../includes/tools/SaldiRuleTools.php';
	require_once __DIR__ . '/../includes/automation/inboxCsrf.php';
	require_once __DIR__ . '/../includes/automation/approvalCard.php';
	return;
}

ob_start();
@session_start();
$s_id = session_id();
$modulnr = 0;
$title = 'Saldi Assist';
include __DIR__ . '/../includes/connect.php';
include __DIR__ . '/../includes/online.php';
require_once __DIR__ . '/../includes/runtime/bootstrap.php';
require_once __DIR__ . '/../includes/automation/SaldiMemoryStore.php';
require_once __DIR__ . '/../includes/automation/SaldiPostingRules.php';
require_once __DIR__ . '/../includes/tools/SaldiRuleTools.php';
require_once __DIR__ . '/../includes/automation/inboxCsrf.php';
require_once __DIR__ . '/../includes/automation/approvalCard.php';
require_once __DIR__ . '/../includes/automation/assistTokenFlash.php';
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

// ---------------------------------------------------------------- controller
$lang = in_array((int)($sprog_id ?? 1), [1, 2, 3], true) ? (int)$sprog_id : 1;
$isUser = (int)($bruger_id ?? 0) > 0;
$open = null;
if ($isUser) {
	try {
		$open = saldi_runtime_context_for_session();
		saldi_runtime_ensure_schema($open['db']);
	} catch (Throwable $e) {
		error_log('systemdata/assistent.php: ' . get_class($e) . ' ' . $e->getMessage());
		$open = null;
	}
}
if ($isUser && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	// online.php empties $_POST when the session switched company since the form was rendered.
	$posted = is_array($_POST) ? $_POST : ['csrf' => $_SESSION['inbox_csrf'] ?? '', 'thisDb' => ''];
	$handled = saldi_assistent_post($posted, (string)$db, $open, $lang);
	$_SESSION['assistent_flash'] = $handled['flash'];
	if ($handled['token'] !== null) {
		$issuer = '';
		try {
			$issuer = (string)$open['db']->value("SELECT firmanavn FROM adresser WHERE art = 'S' ORDER BY id LIMIT 1");
		} catch (Throwable $e) {
			error_log('systemdata/assistent.php: ' . get_class($e) . ' ' . $e->getMessage());
		}
		saldi_assist_token_flash_put($_SESSION, $handled['token'], (string)$db, (int)$bruger_id, $issuer !== '' ? $issuer : (string)$db);
	}
	$anchor = ['create' => '#sai-secret', 'revoke' => '#sai-access', 'retract' => '#sai-memory', 'retire_rule' => '#sai-rules', 'digest' => '#sai-digest', 'switches' => '#sai-switches'][is_string($posted['op'] ?? null) ? $posted['op'] : ''] ?? '';
	header('Location: assistent.php' . $anchor, true, 303);
	exit;
}
$flash = isset($_SESSION['assistent_flash']) && is_array($_SESSION['assistent_flash']) ? $_SESSION['assistent_flash'] : null;
// The key is shown only in the company and to the user that created it (shared session, company switch).
$tokenFlash = saldi_assist_token_flash_take($_SESSION, (string)$db, (int)($bruger_id ?? 0));
$newToken = $tokenFlash['token'];
$foreignToken = $tokenFlash['foreign'];
unset($_SESSION['assistent_flash']);

$tdb = $open['db'] ?? null;
$ctx = $open['ctx'] ?? null;
$isAdmin = false;
$canSeeYearEnd = false;
$memory = ['company' => [], 'user' => []];
$myGrants = [];
$allGrants = [];
$userNames = [];
$switches = [];
$values = ['vat_period' => 'quarter', 'interrupt_budget' => '3', 'till_difference_threshold' => '50', 'lead_days' => '14'];
$rules = null;
$canRetire = false;
$digest = ['on' => false, 'email' => '', 'default_email' => ''];
$loadFailed = $isUser && $open === null;
if ($open !== null) {
	try {
		$isAdmin = SaldiPermissionService::allows($ctx, $tdb, 'settings.admin');
		$canSeeYearEnd = $isAdmin || SaldiPermissionService::allows($ctx, $tdb, 'ledger.read');
		$memory = SaldiMemoryStore::active($tdb, $ctx->userId);
		$grants = new SaldiGrantService(saldi_runtime_master(), $tdb, $ctx);
		$personal = static fn(array $rows): array => array_values(array_filter($rows, static fn(array $g): bool => $g['kind'] === 'personal'));
		$myGrants = $personal($grants->listMine());
		if ($isAdmin) {
			$allGrants = $personal($grants->listAll());
			foreach ($tdb->all('SELECT id, brugernavn FROM brugere') as $row) {
				$userNames[(int)$row['id']] = (string)$row['brugernavn'];
			}
		}
		foreach (SALDI_ASSIST_SWITCHES as [$group, $name]) {
			$switches[$group . '/' . $name] = SaldiSettings::get($tdb, $group, $name) === 'on';
		}
		foreach ($values as $name => $default) {
			$values[$name] = SaldiSettings::get($tdb, 'assistant', $name, $default);
		}
		if (SaldiPermissionService::allows($ctx, $tdb, 'rules.read') && $tdb->hasTable('posting_rules')) {
			$rules = SaldiRuleTools::listRules($ctx, $tdb, ['status' => 'active', 'limit' => 100])['data']['rows'];
			$canRetire = SaldiPermissionService::allows($ctx, $tdb, 'journal.draft');
		}
		$digest = [
			'on' => SaldiSettings::getUser($tdb, $ctx, 'assistant', 'digest', 'off') === 'daily',
			'email' => SaldiSettings::getUser($tdb, $ctx, 'assistant', 'digest_email', ''),
			'default_email' => (string)$tdb->value('SELECT email FROM brugere WHERE id = ?', [$ctx->userId]),
		];
	} catch (Throwable $e) {
		error_log('systemdata/assistent.php: ' . get_class($e) . ' ' . $e->getMessage());
		$loadFailed = true;
	}
}
$accessEnabled = !empty($switches['assistant/tools_enabled']) && !empty($switches['assistant/external_agents']);

// ---------------------------------------------------------------- view
$accent = is_string($buttonColor ?? null) && preg_match('/^#[0-9a-fA-F]{3,8}$/D', $buttonColor) ? $buttonColor : '#114691';
$accentText = is_string($buttonTxtColor ?? null) && preg_match('/^#[0-9a-fA-F]{3,8}$/D', $buttonTxtColor) ? $buttonTxtColor : '#ffffff';
$t = static fn(int $id, string $fallback, array $vars = []): string => saldi_ui_h(saldi_ui_text($tdb, $lang, $id, $fallback, $vars));
$csrf = $isUser ? saldi_inbox_csrf_token() : '';
$hidden = '<input type="hidden" name="csrf" value="' . saldi_ui_h($csrf) . '"><input type="hidden" name="thisDb" value="' . saldi_ui_h((string)$db) . '">';
$appPath = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/systemdata/assistent.php'), 2)), '/');
$mcpUrl = (saldi_runtime_request_origin($_SERVER) ?? 'http://localhost') . $appPath . '/mcp/';
$used = static fn(array $facts): int => array_sum(array_map(static fn(array $f): int => mb_strlen((string)$f['fact']), $facts));
$isActiveGrant = static fn(array $g): bool => $g['revoked_at'] === null && $g['expires_at'] > gmdate('Y-m-d H:i:s');
$grantStatus = static function (array $g) use ($t): string {
	if ($g['revoked_at'] !== null) {
		return '<span class="sai-tag">' . $t(5855, 'Tilbagekaldt') . '</span>';
	}
	if ($g['expires_at'] <= gmdate('Y-m-d H:i:s')) {
		return '<span class="sai-tag">' . $t(5804, 'Udløbet') . '</span>';
	}
	return '<span class="sai-tag sai-tag--active">' . $t(430, 'Aktiv') . '</span>';
};
$grantRows = static function (array $rows, bool $withUser) use ($t, $hidden, $grantStatus, $userNames, $isActiveGrant): string {
	$out = '';
	foreach ($rows as $g) {
		$out .= '<tr>';
		if ($withUser) {
			$out .= '<td>' . saldi_ui_h($userNames[(int)$g['user_id']] ?? ('#' . (int)$g['user_id'])) . '</td>';
		}
		$out .= '<td>' . saldi_ui_h((string)$g['label']) . '</td>';
		$out .= '<td>' . ($g['scopes'] === 'read draft' ? $t(5849, 'Læse og lave forslag') : $t(5848, 'Kun læse')) . '</td>';
		$out .= '<td>' . saldi_ui_h(saldi_ui_local_time((string)$g['created_at'], false)) . '</td>';
		$out .= '<td>' . ($g['last_used_at'] === null ? '&#8211;' : saldi_ui_h(saldi_ui_local_time((string)$g['last_used_at']))) . '</td>';
		$out .= '<td>' . saldi_ui_h(saldi_ui_local_time((string)$g['expires_at'], false)) . '</td>';
		$out .= '<td>' . $grantStatus($g) . '</td><td class="sap-actions">';
		if ($isActiveGrant($g)) {
			$out .= '<form method="post" action="assistent.php">' . $hidden . '<input type="hidden" name="op" value="revoke"><input type="hidden" name="grant_id" value="' . (int)$g['id'] . '">'
				. '<button type="submit" class="sac-btn sac-btn--secondary sap-btn--sm">' . $t(5856, 'Tilbagekald') . '<span class="sai-visually-hidden"> ' . saldi_ui_h((string)$g['label']) . '</span></button></form>';
		}
		$out .= '</td></tr>';
	}
	return $out;
};
$grantHead = static function (bool $withUser) use ($t): string {
	return '<thead><tr>' . ($withUser ? '<th scope="col">' . $t(5714, 'Bruger') . '</th>' : '') . '<th scope="col">' . $t(138, 'Navn') . '</th><th scope="col">' . $t(5847, 'Adgang') . '</th><th scope="col">' . $t(65, 'Oprettet') . '</th><th scope="col">' . $t(5853, 'Sidst brugt') . '</th><th scope="col">' . $t(5854, 'Udløber') . '</th><th scope="col">' . $t(5806, 'Status') . '</th><th scope="col"><span class="sai-visually-hidden">' . $t(5856, 'Tilbagekald') . '</span></th></tr></thead>';
};
$factList = static function (array $facts, bool $canRemove, string $emptyText) use ($t, $hidden): string {
	if (!$facts) {
		return '<p class="sap-empty">' . $emptyText . '</p>';
	}
	$out = '<ul class="sai-facts">';
	foreach ($facts as $fact) {
		$out .= '<li><div><span class="sai-facts__text">' . saldi_ui_h((string)$fact['fact']) . '</span><span class="sai-facts__meta">' . saldi_ui_h(saldi_ui_local_time((string)$fact['created_at'], false)) . '</span></div>';
		if ($canRemove) {
			$out .= '<form method="post" action="assistent.php">' . $hidden . '<input type="hidden" name="op" value="retract"><input type="hidden" name="memory_id" value="' . (int)$fact['id'] . '">'
				. '<button type="submit" class="sac-btn sac-btn--secondary sap-btn--sm">' . $t(5840, 'Fjern') . '<span class="sai-visually-hidden">: ' . saldi_ui_h((string)$fact['fact']) . '</span></button></form>';
		}
		$out .= '</li>';
	}
	return $out . '</ul>';
};
// "Brugt 120 af 800 tegn" with a small bar; amber from 90 %.
$meter = static function (int $usedChars, int $cap) use ($t): string {
	$share = $cap > 0 ? min(1, $usedChars / $cap) : 0;
	return '<span class="sap-meter' . ($share >= 0.9 ? ' sap-meter--full' : '') . '"><span class="sap-meter__bar" aria-hidden="true"><i style="width: ' . round($share * 100) . '%"></i></span>'
		. $t(5841, 'Brugt {used} af {cap} tegn', ['used' => $usedChars, 'cap' => $cap]) . '</span>';
};
$section = static fn(string $title, string $titleId, string $body, string $sectionId = ''): string => '<section class="sap-section"' . ($sectionId !== '' ? ' id="' . $sectionId . '"' : '') . ' aria-labelledby="' . $titleId . '">'
	. '<div class="sap-section__head"><h2 class="sap-cap" id="' . $titleId . '">' . $title . '</h2></div>' . $body . '</section>';
$strip = [
	'tools' => !empty($switches['assistant/tools_enabled']), 'external' => !empty($switches['assistant/external_agents']),
	'grants' => count(array_filter($isAdmin ? $allGrants : $myGrants, $isActiveGrant)), 'facts' => count($memory['user']) + count($memory['company']),
	'rules' => $rules === null ? null : count($rules), 'admin' => $isAdmin,
	'routines_on' => count(array_filter(SALDI_ASSIST_SWITCHES, static fn(array $s): bool => $s[0] === 'automation' && !empty($switches[$s[0] . '/' . $s[1]]))),
	'routines_total' => count(array_filter(SALDI_ASSIST_SWITCHES, static fn(array $s): bool => $s[0] === 'automation')),
];
$nav = ['sai-memory' => $t(5832, 'Hukommelse'), 'sai-access' => $t(5842, 'AI-adgang')];
if ($rules !== null) {
	$nav['sai-rules'] = $t(5970, 'Bogføringsregler');
}
$nav['sai-digest'] = $t(5950, 'Dagens overblik');
if ($isAdmin) {
	$nav['sai-switches'] = $t(122, 'Indstillinger');
}
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="<?= $lang === 2 ? 'en' : ($lang === 3 ? 'nb' : 'da') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $t(5881, 'Indstillinger for Saldi Assist') ?></title>
<link rel="stylesheet" href="../css/approval_card.css?v=20261007">
<link rel="stylesheet" href="../css/saldi_assist_pages.css?v=20261007">
<script>document.documentElement.className += ' js';</script>
</head>
<body class="sai-body sai-v2" style="--sac-accent: <?= saldi_ui_h($accent) ?>; --sac-accent-text: <?= saldi_ui_h($accentText) ?>;">
<div class="sap-page">
	<header class="sai-head">
		<div>
			<h1 class="sai-h1"><?= $t(5881, 'Indstillinger for Saldi Assist') ?></h1>
			<?php if ($isUser) { ?><p class="sai-lead"><?= $t(6380, 'Her ser du, hvad Saldi Assist husker, hvem der har adgang, og hvad der kører af sig selv.') ?></p><?php } ?>
		</div>
		<?php if ($isUser) { ?>
		<nav class="sap-head__links" aria-label="Saldi Assist">
			<a class="sai-head__link" href="../inbox/index.php"><?= $t(5800, 'Opgaver') ?></a>
			<?php if ($canSeeYearEnd) { ?><a class="sai-head__link" href="revisorpakke.php"><?= $t(6160, 'Revisorpakke') ?></a><?php } ?>
			<?php if ($isAdmin) { ?><a class="sai-head__link" href="assistent_rapport.php"><?= $t(5990, 'Assist-rapport') ?></a><?php } ?>
		</nav>
		<?php } ?>
	</header>
<?php if (!$isUser) { ?>
	<div class="sap-card" role="status">
		<p class="sap-empty"><strong><?= $t(5830, 'Indstillinger for Saldi Assist kræver et brugerlogin.') ?></strong><br><?= $t(5831, 'Du er logget ind som revisor eller administrator. Log ind som bruger i regnskabet for at se hukommelse og adgange.') ?></p>
	</div>
<?php } elseif ($loadFailed) { ?>
	<div class="sap-card" role="alert">
		<p class="sap-empty"><?= $t(5740, 'Funktionen er midlertidigt utilgængelig.') ?></p>
	</div>
<?php } else { ?>
	<?= saldi_assistent_strip_html($strip, $tdb, $lang) ?>
	<div class="sap-toolbar">
		<nav class="sai-seg" aria-label="<?= $t(6339, 'Afsnit') ?>" data-sap-spy>
			<?php foreach ($nav as $anchor => $label) { ?><a class="sai-seg__tab" href="#<?= $anchor ?>"><?= $label ?></a><?php } ?>
		</nav>
	</div>
	<?php if ($flash && $flash['text'] !== '') { ?>
	<div class="sai-banner sai-banner--<?= $flash['tone'] === 'warn' ? 'warn' : 'neutral' ?>" role="<?= $flash['tone'] === 'warn' ? 'alert' : 'status' ?>" tabindex="-1" id="sai-banner">
		<p><?= saldi_ui_h($flash['text']) ?><?php if ($flash['tone'] === 'warn') { ?> <strong><?= $t(5822, 'Intet er ændret.') ?></strong><?php } ?></p>
	</div>
	<?php } ?>
	<?php if ($foreignToken) { ?>
	<div class="sai-banner sai-banner--warn" role="alert">
		<p><?= $t(5756, 'En ny adgangsnøgle (adgang {grant}) blev oprettet i regnskabet {company} og vises ikke her. Tilbagekald den i det regnskab, hvis du ikke nåede at kopiere den.', ['grant' => $foreignToken['grant_id'], 'company' => $foreignToken['company']]) ?></p>
	</div>
	<?php } ?>
	<?php if ($newToken) {
		$snippet = json_encode(['mcpServers' => ['saldi' => ['type' => 'http', 'url' => $mcpUrl, 'headers' => ['Authorization' => 'Bearer ' . $newToken['token']]]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		?>
	<section class="sai-secret" aria-labelledby="sai-secret-title" tabindex="-1" id="sai-secret">
		<h2 id="sai-secret-title"><?= $t(5859, 'Adgangsnøgle') ?></h2>
		<p class="sai-lead"><?= $t(5757, 'Regnskab: {company}', ['company' => $newToken['company']]) ?></p>
		<p class="sap-note sap-note--warn"><span><?= $t(5858, 'Kopiér nøglen nu. Den vises kun denne ene gang, og Saldi gemmer den ikke.') ?></span></p>
		<div class="sai-copy">
			<pre class="sai-copy__value" id="sai-token"><?= saldi_ui_h($newToken['token']) ?></pre>
			<button type="button" class="sac-btn" data-copy="sai-token"><?= $t(1493, 'Kopiér') ?><span class="sai-visually-hidden"> <?= $t(5859, 'Adgangsnøgle') ?></span></button>
		</div>
		<span class="sap-label"><?= $t(5860, 'Adresse til MCP-serveren') ?></span>
		<div class="sai-copy">
			<pre class="sai-copy__value" id="sai-url"><?= saldi_ui_h($mcpUrl) ?></pre>
			<button type="button" class="sac-btn sac-btn--secondary" data-copy="sai-url"><?= $t(1493, 'Kopiér') ?><span class="sai-visually-hidden"> <?= $t(5860, 'Adresse til MCP-serveren') ?></span></button>
		</div>
		<span class="sap-label"><?= $t(5861, 'Eksempel på opsætning i en AI-klient') ?></span>
		<div class="sai-copy">
			<pre class="sai-copy__value" id="sai-snippet"><?= saldi_ui_h((string)$snippet) ?></pre>
			<button type="button" class="sac-btn sac-btn--secondary" data-copy="sai-snippet"><?= $t(1493, 'Kopiér') ?><span class="sai-visually-hidden"> <?= $t(5861, 'Eksempel på opsætning i en AI-klient') ?></span></button>
		</div>
		<p class="sai-copy__status" role="status" aria-live="polite" id="sai-copy-status"></p>
	</section>
	<?php } ?>

	<?= $section($t(5832, 'Hukommelse'), 'sai-memory', '<div class="sap-card"><div class="sap-card__body">'
		. '<p class="sap-lead">' . $t(5833, 'Saldi Assist husker kun det, der er godkendt under Opgaver. Du kan fjerne en oplysning når som helst.') . '</p>'
		. '<h3 class="sap-sub">' . $t(5834, 'Mine oplysninger') . ' <span class="sap-sub__meta">' . $meter($used($memory['user']), 800) . '</span></h3>'
		. '<p class="sap-hint" style="margin: 6px 0 0">' . $t(5835, 'Kun du kan se dem.') . '</p>'
		. $factList($memory['user'], true, $t(5838, 'Saldi Assist husker ikke noget om dig endnu.'))
		. '<h3 class="sap-sub" style="margin-top: 22px">' . $t(5836, 'Virksomhedens oplysninger') . ' <span class="sap-sub__meta">' . $meter($used($memory['company']), 2000) . '</span></h3>'
		. '<p class="sap-hint" style="margin: 6px 0 0">' . $t(5837, 'Gælder for alle brugere i regnskabet. Kun administratorer kan fjerne dem.') . '</p>'
		. $factList($memory['company'], $isAdmin, $t(5839, 'Der er ingen fælles oplysninger om virksomheden.'))
		. '</div></div>') ?>

	<section class="sap-section" aria-labelledby="sai-access-title" id="sai-access">
		<div class="sap-section__head"><h2 class="sap-cap" id="sai-access-title"><?= $t(5842, 'AI-adgang') ?></h2></div>
		<div class="sap-card">
			<div class="sap-card__body">
				<p class="sap-lead"><?= $t(5843, 'Her kan du give et AI-værktøj, fx en AI-klient på din egen computer, adgang til regnskabet med dine rettigheder. Værktøjet kan læse og lave forslag, men intet ændres, før du godkender det under Opgaver.') ?></p>
				<?php if (!$accessEnabled) { ?>
				<p class="sap-note sap-note--warn"><span><?= $t(5844, 'Adgang for eksterne AI-værktøjer er slået fra i dette regnskab.') ?> <?= $isAdmin ? $t(5845, 'Du kan slå det til under Indstillinger nederst på siden.') : $t(5846, 'Kontakt en administrator, hvis du har brug for det.') ?></span></p>
				<?php } else { ?>
				<form method="post" action="assistent.php" class="sap-form sap-fields">
					<?= $hidden ?><input type="hidden" name="op" value="create">
					<div class="sap-field sap-field--grow">
						<label for="sai-label"><?= $t(138, 'Navn') ?></label>
						<input type="text" id="sai-label" name="label" maxlength="100" required autocomplete="off">
					</div>
					<div class="sap-field">
						<span class="sap-label" id="sai-scope-label"><?= $t(5847, 'Adgang') ?></span>
						<div class="sap-choice" role="radiogroup" aria-labelledby="sai-scope-label"><label><input type="radio" name="scope" value="read" checked><span><?= $t(5848, 'Kun læse') ?></span></label><label><input type="radio" name="scope" value="draft"><span><?= $t(5849, 'Læse og lave forslag') ?></span></label></div>
					</div>
					<div class="sap-field">
						<span class="sap-label" id="sai-ttl-label"><?= $t(5850, 'Gyldighed') ?></span>
						<div class="sap-choice" role="radiogroup" aria-labelledby="sai-ttl-label"><?php foreach ($isAdmin ? [30, 90, 365] : [30, 90] as $i => $days) { ?><label><input type="radio" name="ttl" value="<?= $days ?>"<?= $i === 0 ? ' checked' : '' ?>><span><?= $t(5851, '{n} dage', ['n' => $days]) ?></span></label><?php } ?></div>
					</div>
					<button type="submit" class="sac-btn"><?= $t(5852, 'Opret adgang') ?></button>
				</form>
				<?php } ?>
				<?php if (!$myGrants) { ?><p class="sap-empty"><?= $t(5857, 'Du har ingen adgange.') ?></p><?php } ?>
			</div>
			<?php if ($myGrants) { ?>
			<div class="sac-table-wrap"><table class="sai-grants"><caption class="sai-visually-hidden"><?= $t(5842, 'AI-adgang') ?></caption><?= $grantHead(false) ?><tbody><?= $grantRows($myGrants, false) ?></tbody></table></div>
			<?php } ?>
		</div>
		<?php if ($isAdmin && $allGrants && array_column($allGrants, 'id') != array_column($myGrants, 'id')) { // Hidden when it only repeats "Mine". ?>
		<div class="sap-card" style="margin-top: 14px"><div class="sap-card__body" style="padding-bottom: 0"><h3 class="sap-sub" style="border-bottom: 0"><?= $t(5863, 'Alle adgange i regnskabet') ?> <span class="sap-sub__meta"><?= count($allGrants) ?></span></h3></div>
		<div class="sac-table-wrap"><table class="sai-grants"><caption class="sai-visually-hidden"><?= $t(5863, 'Alle adgange i regnskabet') ?></caption><?= $grantHead(true) ?><tbody><?= $grantRows($allGrants, true) ?></tbody></table></div></div>
		<?php } ?>
	</section>

	<?php if ($rules !== null) {
		$rulesHtml = saldi_assistent_rules_html($rules, $canRetire, $hidden, $tdb, $lang);
		print $section($t(5970, 'Bogføringsregler') . ' <span class="sap-cap__count">' . count($rules) . '</span>', 'sai-rules', '<div class="sap-card"><div class="sap-card__body">'
			. '<p class="sap-lead"><a href="../inbox/index.php?kind=rule.posting">' . $t(6379, 'Se forslag til nye regler i Opgaver.') . '</a></p>' . ($rules ? '' : $rulesHtml) . '</div>'
			. ($rules ? $rulesHtml : '') . '</div>');
	} ?>

	<?= $section($t(5950, 'Dagens overblik'), 'sai-digest', '<div class="sap-card">' . saldi_assistent_digest_html($digest, $hidden, $tdb, $lang) . '</div>') ?>

	<?php if ($isAdmin) {
		print $section($t(122, 'Indstillinger'), 'sai-switches', '<div class="sap-card">' . saldi_assistent_admin_html($switches, $values, $hidden, $tdb, $lang) . '</div>');
	} ?>
<?php } ?>
</div>
<script>
(function () {
	'use strict';
	var status = document.getElementById('sai-copy-status');
	document.addEventListener('click', function (event) {
		var button = event.target && event.target.closest ? event.target.closest('button[data-copy]') : null;
		if (!button) { return; }
		var source = document.getElementById(button.getAttribute('data-copy'));
		if (!source) { return; }
		var text = source.textContent;
		var done = function () { if (status) { status.textContent = <?= json_encode(saldi_ui_text($tdb, $lang, 5862, 'Kopieret'), JSON_UNESCAPED_UNICODE) ?>; } };
		var fallback = function () {
			var range = document.createRange();
			range.selectNodeContents(source);
			var selection = window.getSelection();
			selection.removeAllRanges();
			selection.addRange(range);
			try { if (document.execCommand('copy')) { done(); } } catch (e) { /* the text stays selected for manual copy */ }
		};
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(done, fallback);
		} else {
			fallback();
		}
	});
	// "Stop regel" asks once before the form goes.
	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (form && form.hasAttribute && form.hasAttribute('data-confirm') && !window.confirm(form.getAttribute('data-confirm'))) {
			event.preventDefault();
		}
	});
	// The admin form says when it holds changes that are not saved yet.
	Array.prototype.forEach.call(document.querySelectorAll('form[data-sap-dirty]'), function (form) {
		var hint = form.querySelector('[data-sap-dirty-hint]');
		var mark = function () {
			if (hint && !hint.textContent) { hint.textContent = hint.getAttribute('data-text'); hint.className += ' sap-bar__hint--warn'; }
		};
		form.addEventListener('change', mark);
		form.addEventListener('input', mark);
	});
	// The section segment follows the reading position.
	var spy = document.querySelector('[data-sap-spy]');
	if (spy && 'IntersectionObserver' in window) {
		var tabs = Array.prototype.slice.call(spy.querySelectorAll('a'));
		var visible = {};
		var pick = function () {
			var current = null;
			tabs.forEach(function (tab) { if (!current && visible[tab.hash.slice(1)]) { current = tab; } });
			if (!current) { return; }
			tabs.forEach(function (tab) {
				var on = tab === current;
				tab.classList.toggle('is-current', on);
				if (on) { tab.setAttribute('aria-current', 'location'); } else { tab.removeAttribute('aria-current'); }
			});
		};
		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) { visible[entry.target.getAttribute('data-sap-anchor')] = entry.isIntersecting; });
			pick();
		}, { rootMargin: '-56px 0px -55% 0px' });
		tabs.forEach(function (tab) {
			var target = document.getElementById(tab.hash.slice(1));
			var block = target ? (target.closest('section') || target) : null;
			if (block) { block.setAttribute('data-sap-anchor', tab.hash.slice(1)); observer.observe(block); }
		});
	}
	var focus = document.getElementById('sai-secret') || document.getElementById('sai-banner');
	if (focus) { focus.focus(); }
})();
</script>
</body>
</html>
