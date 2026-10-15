<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/assist_bankfil.php --- ai/mcp-assistant --- 2026.10.05
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
// Upload or paste bank statements, preview deterministic matches and propose drafts for Opgaver.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Correct companion review findings and preserve legacy accounting behavior.
// 20261007 CL/LH V1 Stille: steps, drop zone with paste fallback, line picker grouped by confidence (Høj
//                selected, Middel opt-in, Ingen sikker modpost, Allerede i en kassekladde, Andre linjer) with
//                counter, group toggles and arrow keys; clear empty and error states (css/saldi_assist_pages.css).
// 20261007 CL/LH Review: the submit button is enabled server-side (script disables it on an empty selection), so
//                a medium-only batch works without JS; "Vælg alle" only on the Høj group; singular counter;
//                errors split by reason and shown at the field they belong to; done steps and summary action.

/**
 * Variables injected by ../includes/online.php during normal page requests:
 * @var int|string $bruger_id
 * @var string $db
 * @var int|string $sprog_id
 * @var string $buttonColor
 * @var string $buttonTxtColor
 */
require_once __DIR__ . '/../includes/runtime/bootstrap.php';
require_once __DIR__ . '/../includes/automation/approvalCard.php';
require_once __DIR__ . '/../includes/automation/inboxCsrf.php';
require_once __DIR__ . '/../includes/tools/SaldiBankTools.php';

/** @return array{content:string,filename:?string} Validated upload or bounded pasted statement. */
function saldi_bankfil_input(array $post, array $files): array
{
	$file = ifset($files, 'statement', []);
	if (!is_array($file)) {
		throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid upload');
	}
	$error = ifset($file, 'error', UPLOAD_ERR_NO_FILE);
	if ($error !== UPLOAD_ERR_NO_FILE) {
		if ($error !== UPLOAD_ERR_OK || !is_string(ifset($file, 'tmp_name', null))
			|| !is_uploaded_file($file['tmp_name']) || (int)ifset($file, 'size', 0) > 2 * 1024 * 1024) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid upload');
		}
		$content = file_get_contents($file['tmp_name'], false, null, 0, 2 * 1024 * 1024 + 1);
		if ($content === false || strlen($content) > 2 * 1024 * 1024) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid upload');
		}
		return ['content' => $content, 'filename' => mb_substr(basename((string)ifset($file, 'name', '')), 0, 255)];
	}
	$content = ifset($post, 'content', '');
	if (!is_string($content) || mb_strlen($content) > 200000 || strlen($content) > 2 * 1024 * 1024) {
		throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid pasted statement');
	}
	return ['content' => $content, 'filename' => null];
}

/** @return ?array Company/user-bound page state, never a batch supplied by the browser. */
function saldi_bankfil_state(?array $state, string $company, int $user): ?array
{
	return $state !== null && ifset($state, 'db', '') === $company && ifset($state, 'user', 0) === $user ? $state : null;
}

/**
 * CSRF and company binding precede every write. Services recheck the human's rights.
 * @return array{state:?array,flash:array{tone:string,text:string}}
 */
function saldi_bankfil_post(array $post, array $files, string $company, ?array $open, ?array $state, int $lang): array
{
	$db = $open['db'] ?? null;
	$fail = static fn(string $text): array => ['state' => $state, 'flash' => ['tone' => 'warn', 'text' => $text]];
	if (!saldi_inbox_csrf_check(is_string(ifset($post, 'csrf', null)) ? $post['csrf'] : null)) {
		return $fail(saldi_ui_text($db, $lang, 5888, 'Siden er udløbet. Genindlæs den, og prøv igen.'));
	}
	if (ifset($post, 'thisDb', null) !== $company) {
		return $fail(saldi_ui_text($db, $lang, 5883, 'Du har skiftet regnskab i et andet vindue. Genindlæs siden.'));
	}
	if ($open === null) {
		return $fail(saldi_ui_text(null, $lang, 5740, 'Funktionen er midlertidigt utilgængelig.'));
	}
	$ctx = $open['ctx'];
	try {
		if (!$ctx->isHuman()) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Human required');
		}
		SaldiPermissionService::require($ctx, $db, 'journal.draft');
		SaldiPermissionService::require($ctx, $db, 'journal.read');
		if (ifset($post, 'op', '') === 'import') {
			$input = saldi_bankfil_input($post, $files);
			$account = SaldiBankImport::bankAccount($ctx, $db, ifset($post, 'bank_account', null));
			$parsed = SaldiBankStatementParser::parse($input['content'], $input['filename']);
			if (!$parsed['rows']) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'No statement rows');
			}
			$import = $db->transaction(static function () use ($ctx, $db, $account, $parsed): array {
				$result = SaldiBankImport::stage($ctx, $db, $account, $parsed);
				SaldiSettings::setUser($db, $ctx, 'assistant', 'bank_account', $account);
				return $result;
			});
			return ['state' => ['db' => $company, 'user' => $ctx->userId, 'batch' => $import['batch_id'], 'account' => $account,
				'warnings' => $import['warnings'], 'request' => bin2hex(random_bytes(16)), 'completed' => [], 'item' => 0],
				'flash' => ['tone' => 'neutral', 'text' => '']];
		}
		$state = saldi_bankfil_state($state, $company, $ctx->userId);
		if (ifset($post, 'op', '') !== 'propose' || $state === null
			|| ifset($post, 'batch', '') !== $state['batch'] || !is_string(ifset($post, 'request', null))
			|| ($post['request'] !== $state['request'] && !isset($state['completed'][$post['request']]))) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid statement state');
		}
		$ids = ifset($post, 'line_ids', []);
		if (!is_array($ids) || !array_is_list($ids) || !$ids || count($ids) > 200) {
			// The button is enabled without JS, so an empty selection is answered here.
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Expected 1 to 200 lines', ['reason' => is_array($ids) && count($ids) > 200 ? 'too_many_lines' : 'no_selection']);
		}
		foreach ($ids as &$id) {
			if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,9}$/D', $id)) {
				throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid line id');
			}
			$id = (int)$id;
		}
		unset($id);
		$result = SaldiBankTools::propose($ctx, $db, ['batch_id' => $state['batch'], 'line_ids' => $ids, 'request_id' => $post['request'],
			'explanation' => saldi_ui_text($db, $lang, 6120, 'Bankfil til kassekladde')])['data'];
		$state['item'] = (int)$result['id'];
		$state['completed'][$post['request']] = $state['item'];
		if ($post['request'] === $state['request']) {
			$state['request'] = bin2hex(random_bytes(16));
		}
		return ['state' => $state, 'flash' => ['tone' => 'neutral', 'text' => saldi_ui_text($db, $lang, 6128, 'Forslaget ligger i Opgaver. Intet er bogført.')]];
	} catch (Throwable $e) {
		if (!$e instanceof SaldiRuntimeError) {
			error_log('finans/assist_bankfil.php: ' . get_class($e) . ' ' . $e->getMessage());
		}
		if ($e instanceof SaldiRuntimeError && $e->errorCode === 'VALIDATION_ERROR') {
			$failure = saldi_bankfil_failure($e, ifset($post, 'op', '') === 'import' ? 'import' : 'propose', $db, $lang);
			return ['state' => $state, 'flash' => ['tone' => 'warn', 'text' => $failure['text'], 'field' => $failure['field']]];
		}
		return $fail(saldi_approval_error($e, $db, $lang)['message_da']);
	}
}

/**
 * A failed import or proposal, by reason: the Danish message and the form field it belongs to
 * ('bank_account', 'statement' or '' for the page). Unknown reasons keep the general hint (6303).
 * @return array{text:string,field:string}
 */
function saldi_bankfil_failure(SaldiRuntimeError $e, string $op, ?SaldiDb $db, int $lang): array
{
	$message = $e->getMessage();
	$fields = is_array($e->detail['fields'] ?? null) ? $e->detail['fields'] : [];
	if (isset($e->detail['warning_da'])) {
		$text = isset($e->detail['warning_id']) ? vsprintf(saldi_ui_text($db, $lang, (int)$e->detail['warning_id'], $e->detail['warning_da']), $e->detail['warning_args'] ?? []) : $e->detail['warning_da'];
		return ['text' => $text, 'field' => 'statement'];
	}
	if ($op === 'import') {
		if (in_array('bank_account', $fields, true)) {
			return ['text' => saldi_ui_text($db, $lang, 6392, 'Vælg en åben bankkonto i det aktive regnskabsår.'), 'field' => 'bank_account'];
		}
		if (in_array($message, ['No statement rows', 'Statement has no data rows'], true)) {
			return ['text' => saldi_ui_text($db, $lang, 6358, 'Filen indeholdt ingen linjer.'), 'field' => 'statement'];
		}
		if (in_array($message, ['Statement exceeds 5000 rows', 'Expected at most 5000 statement rows'], true)) {
			return ['text' => saldi_ui_text($db, $lang, 6395, 'Filen har mere end 5000 linjer. Del den op i mindre filer.'), 'field' => 'statement'];
		}
		return ['text' => saldi_ui_text($db, $lang, 6391, 'Filen kunne ikke læses. Brug CSV eller camt.053 på højst 2 MB.'), 'field' => 'statement'];
	}
	return match (true) {
		($e->detail['reason'] ?? '') === 'no_selection' => ['text' => saldi_ui_text($db, $lang, 6393, 'Vælg mindst én linje.'), 'field' => ''],
		($e->detail['reason'] ?? '') === 'too_many_lines' => ['text' => saldi_ui_text($db, $lang, 6353, 'Højst 200 linjer ad gangen.'), 'field' => ''],
		$message === 'Invalid statement state' => ['text' => saldi_ui_text($db, $lang, 5888, 'Siden er udløbet. Genindlæs den, og prøv igen.'), 'field' => ''],
		($e->detail['reason'] ?? '') === 'no_match', in_array($message, ['Selected lines do not belong to this batch', 'Invalid line id', 'Invalid bank line id'], true)
			=> ['text' => saldi_ui_text($db, $lang, 6394, 'En af de valgte linjer kan ikke foreslås. Læs filen igen, og vælg linjerne på ny.'), 'field' => ''],
		default => ['text' => saldi_ui_text($db, $lang, 6303, 'Tjek bankkonto, fil og valgte linjer. Brug CSV eller camt.053 (højst 2 MB) og vælg 1–200 linjer med en modpost.'), 'field' => ''],
	};
}

/** Which picker group a staged line belongs to: high, medium, none, journal or other. */
function saldi_bankfil_group(array $row): string
{
	if (!empty($row['journal_duplicate'])) {
		return 'journal';
	}
	if (($row['status'] ?? '') !== 'new') {
		return 'other';
	}
	if (($row['match'] ?? null) === null) {
		return 'none';
	}
	return $row['match']['confidence'] === 'high' ? 'high' : 'medium';
}

/**
 * The line picker: one table with a group per confidence tier. Statement text is escaped. Only lines
 * with a counter account can be picked, and only high confidence is selected beforehand (at most 200).
 */
function saldi_bankfil_preview_html(array $rows, bool $canPropose, ?SaldiDb $db, int $lang): string
{
	$t = static fn(int $id, string $fallback, array $vars = []): string => saldi_ui_h(saldi_ui_text($db, $lang, $id, $fallback, $vars));
	if (!$rows) {
		return '<p class="sap-empty">' . $t(6358, 'Filen indeholdt ingen linjer.') . '</p>';
	}
	$groups = ['high' => [], 'medium' => [], 'none' => [], 'journal' => [], 'other' => []];
	foreach ($rows as $row) {
		$groups[saldi_bankfil_group($row)][] = $row;
	}
	$heads = [
		'high' => [$t(6346, 'Høj sikkerhed'), $t(6348, 'Valgt på forhånd. Fravælg dem, du ikke vil have med.')],
		'medium' => [$t(6347, 'Middel sikkerhed'), $t(6349, 'Vælg selv de linjer, der skal med.')],
		'none' => [$t(6127, 'Ingen sikker modpost'), $t(6350, 'Konteres i kassekladden som i dag.')],
		'journal' => [$t(6126, 'Allerede i en kassekladde'), ''],
		'other' => [$t(6351, 'Andre linjer'), ''],
	];
	$out = '<table class="sap-lines" data-sap-lines><caption class="sai-visually-hidden">' . $t(6125, 'Linjer i filen') . ': ' . count($rows) . '</caption><thead><tr>'
		. '<th scope="col"><span class="sai-visually-hidden">' . $t(586, 'Vælg') . '</span></th><th scope="col">' . $t(438, 'Dato') . '</th><th scope="col">' . $t(1163, 'Tekst') . '</th>'
		. '<th scope="col">' . $t(6299, 'Foreslået modpost') . '</th><th scope="col">' . $t(6296, 'Sikkerhed') . '</th><th scope="col" class="sai-num">' . $t(934, 'Beløb') . '</th></tr></thead>';
	$selected = 0;
	foreach ($groups as $key => $groupRows) {
		if (!$groupRows) {
			continue;
		}
		$body = '';
		$pickable = 0;
		$picked = 0;
		foreach ($groupRows as $row) {
			$match = $row['match'];
			$eligible = $canPropose && ($key === 'high' || $key === 'medium');
			$checked = $eligible && $key === 'high' && $selected++ < 200;
			$pickable += $eligible ? 1 : 0;
			$picked += $checked ? 1 : 0;
			$counter = $match['party'] ?? $match['account'] ?? null;
			$counterText = $counter === null ? '<span class="sap-lines__nopick">&#8211;</span>' : saldi_ui_h($counter['account'] . ' ' . $counter['name']);
			if ($match !== null && $match['invoice'] !== null) {
				$counterText .= '<span class="sai-facts__meta">' . saldi_ui_h(sprintf(saldi_ui_text($db, $lang, 6129, 'Kunde/leverandør betaling, faktura %s'), $match['invoice'])) . '</span>';
			}
			if ($key === 'other') {
				$state = match ($row['status']) {
					'duplicate' => rtrim(saldi_ui_text($db, $lang, 5965, 'Mulig dublet:'), ': '),
					'ignored' => saldi_ui_text($db, $lang, 6145, 'Dato uden for regnskabsåret'),
					default => saldi_ui_text($db, $lang, 5991, 'Forslag'),
				};
				$confidence = '<span class="sai-tag">' . saldi_ui_h($state) . '</span>';
			} elseif ($match === null) {
				$confidence = '<span class="sap-lines__nopick">&#8211;</span>';
			} else {
				$confidence = $match['confidence'] === 'high' ? '<span class="sap-conf">' . $t(6297, 'Høj') . '</span>' : '<span class="sap-conf sap-conf--medium">' . $t(6298, 'Middel') . '</span>';
			}
			$body .= '<tr class="is-row' . ($eligible ? '' : ' is-muted') . ($checked ? ' is-picked' : '') . '"><td>'
				. ($eligible ? '<input type="checkbox" class="sap-cb" name="line_ids[]" value="' . (int)$row['line_id'] . '" aria-label="' . saldi_ui_h($row['date'] . ' ' . $row['description']) . '"' . ($checked ? ' checked' : '') . '>' : '<span class="sap-lines__nopick">&#8211;</span>') . '</td>'
				. '<td class="sap-lines__date">' . saldi_ui_h(saldi_ui_cell($row['date'])['text']) . '</td><td class="sap-lines__text">' . saldi_ui_h($row['description']) . '</td>'
				. '<td>' . $counterText . '</td><td>' . $confidence . '</td>'
				. '<td class="sai-num">' . saldi_ui_h(saldi_ui_cell($row['amount'] . ' ' . $row['currency'])['text']) . '</td></tr>';
		}
		[$title, $lead] = $heads[$key];
		// Only the Høj group can be (de)selected at once; Middel lines are chosen one by one.
		$toggle = $pickable > 0 && $key === 'high'
			? '<button type="button" class="sap-btn sap-btn--quiet sap-btn--sm sap-lines__toggle sai-js-only" data-sap-toggle data-on="' . $t(89, 'Vælg alle') . '" data-off="' . $t(90, 'Fravælg alle') . '">'
				. ($picked === $pickable ? $t(90, 'Fravælg alle') : $t(89, 'Vælg alle')) . '</button>'
			: '';
		$out .= '<tbody data-sap-group="' . $key . '"><tr class="sap-lines__group"><th colspan="6" scope="colgroup"><div class="sap-lines__grouphead">'
			. '<span class="sap-cap">' . $title . ' <span class="sap-cap__count">' . count($groupRows) . '</span></span>'
			. ($lead !== '' && ($pickable > 0 || $key === 'none') ? '<p>' . $lead . '</p>' : '') . $toggle . '</div></th></tr>' . $body . '</tbody>';
	}
	return $out . '</table>';
}

if (defined('SALDI_BANKFIL_LIBRARY')) {
	return;
}


ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}
$s_id = session_id();
$modulnr = 0;
$title = 'Bankfil til kassekladde';
include __DIR__ . '/../includes/connect.php';
include __DIR__ . '/../includes/online.php';
ob_end_clean();
$lang = in_array((int)($sprog_id ?? 1), [1, 2, 3], true) ? (int)$sprog_id : 1;
$open = null;
$error = null;
$rows = $accounts = [];
$account = '';
try {
	$open = saldi_runtime_context_for_session();
	saldi_runtime_ensure_schema($open['db']);
	if (!$open['ctx']->isHuman()) {
		throw new SaldiRuntimeError('PERMISSION_DENIED', 'Human required');
	}
	SaldiPermissionService::require($open['ctx'], $open['db'], 'journal.draft');
	SaldiPermissionService::require($open['ctx'], $open['db'], 'journal.read');
} catch (Throwable $e) {
	$error = saldi_approval_error($e, $open['db'] ?? null, $lang)['message_da'];
	$open = null;
}
$state = saldi_bankfil_state(is_array($_SESSION['bankfil_state'] ?? null) ? $_SESSION['bankfil_state'] : null, (string)$db, (int)($bruger_id ?? 0));
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	$posted = is_array($_POST) ? $_POST : ['csrf' => $_SESSION['inbox_csrf'] ?? '', 'thisDb' => ''];
	$handled = saldi_bankfil_post($posted, $_FILES, (string)$db, $open, $state, $lang);
	$_SESSION['bankfil_state'] = $handled['state'];
	$_SESSION['bankfil_flash'] = ['db' => (string)$db, 'user' => (int)($bruger_id ?? 0)] + $handled['flash'];
	header('Location: assist_bankfil.php#bank-preview', true, 303);
	exit;
}
$flash = saldi_bankfil_state(is_array($_SESSION['bankfil_flash'] ?? null) ? $_SESSION['bankfil_flash'] : null, (string)$db, (int)($bruger_id ?? 0));
unset($_SESSION['bankfil_flash']);
$tdb = $open['db'] ?? null;
if ($open !== null) {
	try {
		$accounts = $tdb->all("SELECT kontonr, beskrivelse FROM kontoplan WHERE regnskabsaar = ? AND kontotype = 'S' AND COALESCE(lukket, '') <> 'on' ORDER BY kontonr", [$open['ctx']->fiscalYear]);
		$account = SaldiSettings::getUser($tdb, $open['ctx'], 'assistant', 'bank_account', '');
		if ($state !== null) {
			$rows = SaldiBankTools::listLines($open['ctx'], $tdb, ['batch_id' => $state['batch']])['data']['rows'];
			$duplicates = $tdb->all("SELECT id, proposal FROM bank_import_lines WHERE batch_id = ? AND status = 'duplicate' AND proposal IS NOT NULL", [$state['batch']]);
			$duplicateIds = array_column($duplicates, 'id');
			foreach ($rows as &$row) {
				$row['journal_duplicate'] = in_array($row['line_id'], $duplicateIds);
			}
			unset($row);
		}
	} catch (Throwable $e) {
		$error = saldi_approval_error($e, $tdb, $lang)['message_da'];
	}
}
$t = static fn(int $id, string $fallback, array $vars = []): string => saldi_ui_h(saldi_ui_text($tdb, $lang, $id, $fallback, $vars));
$hidden = '<input type="hidden" name="csrf" value="' . saldi_ui_h(saldi_inbox_csrf_token()) . '"><input type="hidden" name="thisDb" value="' . saldi_ui_h((string)$db) . '">';
$accent = is_string($buttonColor ?? null) && preg_match('/^#[0-9a-fA-F]{3,8}$/D', $buttonColor) ? $buttonColor : '#114691';
$accentText = is_string($buttonTxtColor ?? null) && preg_match('/^#[0-9a-fA-F]{3,8}$/D', $buttonTxtColor) ? $buttonTxtColor : '#ffffff';
$pickable = array_filter($rows, static fn(array $r): bool => in_array(saldi_bankfil_group($r), ['high', 'medium'], true));
$preselected = min(200, count(array_filter($pickable, static fn(array $r): bool => saldi_bankfil_group($r) === 'high')));
$step = $state === null ? 1 : ($state['item'] > 0 ? 3 : 2);
$accountName = '';
foreach ($accounts as $a) {
	if ($state !== null && (string)$a['kontonr'] === (string)$state['account']) {
		$accountName = $a['kontonr'] . ' ' . $a['beskrivelse'];
	}
}
// A failed import is shown at the field it belongs to; other messages stay in the page banner.
$fieldError = $flash !== null && $flash['tone'] === 'warn' && in_array($flash['field'] ?? '', ['bank_account', 'statement'], true) ? $flash : null;
$errorAt = static fn(string $field, string $id): string => $fieldError !== null && $fieldError['field'] === $field
	? '<p class="sap-field__error" id="' . $id . '" role="alert">' . saldi_ui_h($fieldError['text']) . ' <strong>' . $t(5822, 'Intet er ændret.') . '</strong></p>' : '';
$invalid = static fn(string $field, string $id): string => $fieldError !== null && $fieldError['field'] === $field ? ' aria-invalid="true" aria-describedby="' . $id . '"' : '';
// The import form: bank account, a drop zone over the native file input, and pasting as the fallback.
$importForm = static function () use ($t, $hidden, $accounts, $account, $errorAt, $invalid, $fieldError): string {
	$options = '<option value="">' . $t(6121, 'Vælg bankkonto') . '</option>';
	foreach ($accounts as $a) {
		$options .= '<option value="' . saldi_ui_h((string)$a['kontonr']) . '"' . ((string)$a['kontonr'] === $account ? ' selected' : '') . '>' . saldi_ui_h($a['kontonr'] . ' ' . $a['beskrivelse']) . '</option>';
	}
	return '<form method="post" action="assist_bankfil.php" enctype="multipart/form-data" class="sap-form">' . $hidden . '<input type="hidden" name="op" value="import">'
		. '<div class="sap-stack"><div class="sap-field"><label for="bank-account">' . $t(6121, 'Vælg bankkonto') . '</label><select id="bank-account" name="bank_account" required' . $invalid('bank_account', 'bank-account-error') . '>' . $options . '</select>' . $errorAt('bank_account', 'bank-account-error') . '</div>'
		. '<div class="sap-field"><span class="sap-label">' . $t(6122, 'Vælg fil (CSV eller camt.053)') . '</span>'
		. '<div class="sap-drop' . ($fieldError !== null && $fieldError['field'] === 'statement' ? ' is-invalid' : '') . '" data-sap-drop><input id="bank-file" type="file" name="statement" accept=".csv,.xml,.txt" aria-label="' . $t(6122, 'Vælg fil (CSV eller camt.053)') . '"' . $invalid('statement', 'bank-file-error') . '>'
		. '<span class="sap-drop__icon" aria-hidden="true"><svg viewBox="0 0 16 16"><path d="M8 10.5V2.5M5 5.5l3-3 3 3"/><path d="M2.5 10v2.25c0 .7.55 1.25 1.25 1.25h8.5c.7 0 1.25-.55 1.25-1.25V10"/></svg></span>'
		. '<span class="sap-drop__text"><b>' . $t(6354, 'Vælg fil, eller træk den hertil') . '</b><span data-sap-file data-empty="' . $t(6363, 'Ingen fil valgt') . '">' . $t(6363, 'Ingen fil valgt') . '</span></span></div>' . $errorAt('statement', 'bank-file-error') . '</div></div>'
		. '<details class="sap-disclose"><summary>' . $t(6355, 'Indsæt teksten i stedet') . '</summary>'
		. '<label class="sai-visually-hidden" for="bank-content">' . $t(1415, 'Indsæt') . ' CSV / camt.053</label>'
		. '<textarea id="bank-content" name="content" rows="6" maxlength="200000"></textarea></details>'
		. '<p style="margin: 16px 0 0"><button type="submit" class="sac-btn">' . $t(6123, 'Læs filen') . '</button></p></form>';
};
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="<?= $lang === 2 ? 'en' : ($lang === 3 ? 'nb' : 'da') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $t(6120, 'Bankfil til kassekladde') ?></title>
<link rel="stylesheet" href="../css/approval_card.css?v=20261007">
<link rel="stylesheet" href="../css/saldi_assist_pages.css?v=20261007">
<script>document.documentElement.className += ' js';</script>
</head>
<body class="sai-body sai-v2" style="--sac-accent: <?= saldi_ui_h($accent) ?>; --sac-accent-text: <?= saldi_ui_h($accentText) ?>;">
<main class="sap-page">
	<header class="sai-head">
		<div>
			<h1 class="sai-h1"><?= $t(6120, 'Bankfil til kassekladde') ?></h1>
			<p class="sai-lead"><?= $t(6361, 'Assist finder modposter ud fra kunder, leverandører og tidligere posteringer. Intet bogføres, før du godkender forslaget i Opgaver.') ?></p>
		</div>
		<nav class="sap-head__links" aria-label="<?= $t(6120, 'Bankfil til kassekladde') ?>">
			<a class="sai-head__link" href="kladdeliste.php"><?= $t(639, 'Kladdeliste') ?></a>
			<a class="sai-head__link" href="../inbox/index.php"><?= $t(5800, 'Opgaver') ?></a>
		</nav>
	</header>
	<?php if ($open !== null) { ?>
	<ol class="sap-steps">
		<?php foreach ([1 => $t(6123, 'Læs filen'), 2 => $t(6359, 'Vælg linjer'), 3 => $t(6360, 'Godkend i Opgaver')] as $n => $label) { ?>
		<li class="<?= $n < $step ? 'is-done' : ($n === $step ? 'is-current' : '') ?>"<?= $n === $step ? ' aria-current="step"' : '' ?>><b><?= $n ?></b><?= $label ?></li>
		<?php } ?>
	</ol>
	<?php } ?>
	<?php if ($error !== null) { ?>
	<div class="sai-banner sai-banner--warn" role="alert"><p><?= saldi_ui_h($error) ?></p></div>
	<?php } ?>
	<?php if ($flash !== null && $flash['text'] !== '' && $fieldError === null) { ?>
	<div class="sai-banner sai-banner--<?= $flash['tone'] === 'warn' ? 'warn' : 'neutral' ?>" role="<?= $flash['tone'] === 'warn' ? 'alert' : 'status' ?>" tabindex="-1" id="sai-banner">
		<p><?= saldi_ui_h($flash['text']) ?><?php if ($flash['tone'] === 'warn') { ?> <strong><?= $t(5822, 'Intet er ændret.') ?></strong><?php } ?></p>
		<?php if ($flash['tone'] !== 'warn' && $state !== null && $state['item'] > 0) { ?><a class="sap-link" href="../inbox/index.php?item=<?= (int)$state['item'] ?>#sac-item-<?= (int)$state['item'] ?>"><?= $t(5873, 'Vis opgaven') ?></a><?php } ?>
	</div>
	<?php } ?>
	<?php if ($open !== null) { ?>
	<?php if ($state === null) { ?>
	<section class="sap-section" aria-labelledby="bank-import-title">
		<div class="sap-section__head"><h2 class="sap-cap" id="bank-import-title"><?= $t(6123, 'Læs filen') ?></h2></div>
		<div class="sap-card"><div class="sap-card__body"><?= $importForm() ?></div></div>
	</section>
	<?php } else { ?>
	<section class="sap-section" aria-labelledby="bank-import-title">
		<div class="sap-section__head"><h2 class="sap-cap" id="bank-import-title"><?= $t(6123, 'Læs filen') ?></h2></div>
		<div class="sap-card"><div class="sap-card__body">
			<details class="sap-disclose" style="margin: 0"<?= $fieldError !== null ? ' open' : '' ?>>
				<summary><span class="sap-again__summary"><b><?= saldi_ui_h($accountName !== '' ? $accountName : (string)$state['account']) ?></b> &#183; <?= $t(6125, 'Linjer i filen') ?>: <?= count($rows) ?></span><span class="sap-again__action"><?= $t(6356, 'Læs en anden fil') ?></span></summary>
				<div style="margin-top: 14px"><?= $importForm() ?></div>
			</details>
		</div></div>
	</section>
	<section class="sap-section" id="bank-preview" tabindex="-1" aria-labelledby="bank-preview-title">
		<div class="sap-section__head">
			<h2 class="sap-cap" id="bank-preview-title"><?= $t(6359, 'Vælg linjer') ?> <span class="sap-cap__count"><?= count($rows) ?></span></h2>
			<?php if ($pickable && $error === null) { ?><p class="sap-keys sai-js-only" aria-hidden="true"><kbd>&uarr;</kbd><kbd>&darr;</kbd> <?= $t(6362, 'flyt') ?> <kbd><?= $t(6377, 'mellemrum') ?></kbd> <?= $t(6314, 'vælg') ?></p><?php } ?>
		</div>
		<?php if ($state['warnings']) { ?>
		<div class="sap-note sap-note--warn sap-warnings" role="status"><ul><?php foreach ($state['warnings'] as $warning) { ?><li><?= saldi_ui_h($warning) ?></li><?php } ?></ul></div>
		<?php } ?>
		<div class="sap-card">
			<?php if ($rows) { ?>
			<form method="post" action="assist_bankfil.php" class="sap-form" data-sap-pick><?= $hidden ?><input type="hidden" name="op" value="propose"><input type="hidden" name="batch" value="<?= saldi_ui_h($state['batch']) ?>"><input type="hidden" name="request" value="<?= saldi_ui_h($state['request']) ?>">
				<?= saldi_bankfil_preview_html($rows, $error === null, $tdb, $lang) ?>
				<div class="sap-bar">
					<?php if ($pickable && $error === null) { ?>
					<span class="sap-bar__hint"><span class="sap-counter" data-sap-count data-text="<?= $t(6352, '{n} linjer valgt') ?>" data-text-one="<?= $t(6376, '{n} linje valgt') ?>"><?= $preselected === 1 ? $t(6376, '{n} linje valgt', ['n' => 1]) : $t(6352, '{n} linjer valgt', ['n' => $preselected]) ?></span> &#183; <span data-sap-limit><?= $t(6353, 'Højst 200 linjer ad gangen.') ?></span></span>
					<span class="sap-bar__end"><button class="sac-btn" type="submit" data-sap-submit><?= $t(6124, 'Lad Assist foreslå konteringer') ?></button></span>
					<?php } else { ?>
					<span class="sap-bar__hint"><?= $t(6357, 'Ingen af linjerne kan foreslås. Kontér dem i kassekladden som i dag.') ?></span>
					<?php } ?>
				</div>
			</form>
			<?php } else { ?>
			<p class="sap-empty"><?= $t(6358, 'Filen indeholdt ingen linjer.') ?></p>
			<?php } ?>
		</div>
	</section>
	<?php } ?>
	<?php } ?>
</main>
<script>
(function () {
	'use strict';
	// Drop zone: show the chosen file and accept a dragged file.
	var drop = document.querySelector('[data-sap-drop]');
	if (drop) {
		var input = drop.querySelector('input[type=file]');
		var name = drop.querySelector('[data-sap-file]');
		var show = function () {
			var file = input.files && input.files[0];
			name.textContent = file ? file.name : name.getAttribute('data-empty');
			drop.classList.toggle('has-file', !!file);
		};
		input.addEventListener('change', show);
		['dragenter', 'dragover'].forEach(function (type) { drop.addEventListener(type, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
		['dragleave', 'drop'].forEach(function (type) { drop.addEventListener(type, function () { drop.classList.remove('is-over'); }); });
		drop.addEventListener('drop', function (e) {
			e.preventDefault();
			if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
				try { input.files = e.dataTransfer.files; } catch (err) { /* older browsers: use the button */ }
				show();
			}
		});
	}
	var banner = document.getElementById('sai-banner');
	var invalid = document.querySelector('[aria-invalid="true"]');
	if (invalid) { invalid.focus(); } else if (banner) { banner.focus(); }
	// The line picker: counter, the 200 limit, group toggles, row clicks and arrow keys.
	var form = document.querySelector('[data-sap-pick]');
	if (!form) { return; }
	var boxes = Array.prototype.slice.call(form.querySelectorAll('input[name="line_ids[]"]'));
	var count = form.querySelector('[data-sap-count]');
	var limit = form.querySelector('[data-sap-limit]');
	var submit = form.querySelector('[data-sap-submit]');
	var update = function () {
		var n = 0;
		boxes.forEach(function (box) {
			n += box.checked ? 1 : 0;
			var row = box.closest('tr');
			if (row) { row.classList.toggle('is-picked', box.checked); }
		});
		if (count) { count.textContent = count.getAttribute(n === 1 ? 'data-text-one' : 'data-text').replace('{n}', n); }
		if (limit) { limit.parentNode.classList.toggle('sap-bar__hint--warn', n > 200); }
		if (submit) { submit.disabled = n === 0 || n > 200; }
		Array.prototype.forEach.call(form.querySelectorAll('[data-sap-toggle]'), function (button) {
			var group = button.closest('tbody');
			var inGroup = group ? group.querySelectorAll('input[name="line_ids[]"]') : [];
			var all = inGroup.length > 0 && Array.prototype.every.call(inGroup, function (box) { return box.checked; });
			button.textContent = button.getAttribute(all ? 'data-off' : 'data-on');
		});
	};
	form.addEventListener('change', update);
	form.addEventListener('click', function (event) {
		var button = event.target.closest('[data-sap-toggle]');
		if (button) {
			var group = button.closest('tbody');
			var inGroup = Array.prototype.slice.call(group.querySelectorAll('input[name="line_ids[]"]'));
			var all = inGroup.every(function (box) { return box.checked; });
			inGroup.forEach(function (box) { box.checked = !all; });
			update();
			return;
		}
		var row = event.target.closest('tr.is-row');
		if (!row || event.target.closest('input, a, button')) { return; }
		var box = row.querySelector('input[name="line_ids[]"]');
		if (box) { box.checked = !box.checked; box.focus(); update(); }
	});
	form.addEventListener('keydown', function (event) {
		if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') { return; }
		var i = boxes.indexOf(document.activeElement);
		if (i < 0) { return; }
		var next = boxes[i + (event.key === 'ArrowDown' ? 1 : -1)];
		if (next) { event.preventDefault(); next.focus(); next.closest('tr').scrollIntoView({ block: 'nearest' }); }
	});
	form.addEventListener('submit', function (event) {
		if (form.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
		form.setAttribute('aria-busy', 'true');
		window.setTimeout(function () { if (submit) { submit.disabled = true; } }, 0);
	});
	update();
})();
</script>
</body>
</html>
