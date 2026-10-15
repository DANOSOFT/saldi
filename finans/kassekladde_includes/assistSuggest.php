<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/assistSuggest.php --- ai/mcp-assistant --- 2026.10.04
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
// Same-origin, CSRF-protected journal suggestions and bounded session telemetry.
// Authenticates the same legacy online session as inbox/action.php via SaldiDb,
// without legacy SQL helpers, redirects or request descriptions in logs.
// Define SALDI_JOURNAL_SUGGEST_LIBRARY to use the testable controller only.
//
// 20261002 CDX/LH Created.
// 20261003 CDX/LH Fix confirmed Assist review findings and preserve approval and undo semantics.
// 20261004 CDX/LH Add read-only journal check using the same session and CSRF guards.

declare(strict_types=1);

require_once __DIR__ . '/../../includes/automation/SaldiPostingSuggester.php';
require_once __DIR__ . '/../../includes/automation/inboxCsrf.php';
require_once __DIR__ . '/../../includes/automation/SaldiJournalCheck.php';

/** @return array{ctx:SaldiTenantContext,db:SaldiDb} Live, positive tenant user from a legacy session. */
function saldi_journal_suggest_session(string $sessionId): array
{
	if ($sessionId === '') {
		throw new SaldiRuntimeError('UNAUTHENTICATED', 'Session unavailable');
	}
	$online = saldi_runtime_master()->one('SELECT brugernavn, db, revisor, language_id FROM online WHERE session_id = ? ORDER BY logtime DESC LIMIT 1', [$sessionId]);
	if (!$online || empty($online['brugernavn'])) {
		throw new SaldiRuntimeError('UNAUTHENTICATED', 'Session unavailable');
	}
	if (in_array((string)$online['revisor'], ['1', 't', 'true', 'on'], true)) {
		throw new SaldiRuntimeError('PERMISSION_DENIED', 'Positive tenant user required');
	}
	$entry = saldi_runtime_tenant_by_db($online['db']);
	$user = $entry['db']->one('SELECT id FROM brugere WHERE brugernavn = ? AND id > 0 ORDER BY id LIMIT 1', [$online['brugernavn']]);
	if (!$user) {
		throw new SaldiRuntimeError('UNAUTHENTICATED', 'Session unavailable');
	}
	$ctx = saldi_runtime_context_for_user($entry['db'], $entry['regnskab'], (int)$user['id'], 'user:' . $user['id']);
	$languageId = in_array((int)$online['language_id'], [1, 2, 3], true) ? (int)$online['language_id'] : 1;
	$ctx = new SaldiTenantContext($ctx->installId, $ctx->regnskabId, $ctx->db, $ctx->dbType, $ctx->userId, $ctx->username, $ctx->rights, $ctx->fiscalYear, $languageId, $ctx->principal, null, [], $ctx->baseCurrency);
	return ['ctx' => $ctx, 'db' => $entry['db']];
}

/** @return bool Origin/Referer check using the inbox endpoint's proxy-aware origin rules. */
function saldi_journal_suggest_same_origin(array $server): bool
{
	SaldiPostingRules::helpers();
	$own = saldi_runtime_request_origin($server);
	$source = (string)(ifset($server, 'HTTP_ORIGIN', ''));
	if ($source === '') {
		$source = (string)(ifset($server, 'HTTP_REFERER', ''));
	}
	$origin = $source === '' ? null : saldi_runtime_origin($source);
	return $own !== null && $origin !== null && hash_equals($own, $origin);
}

/** @return array{ok:bool,enabled?:bool,suggestions?:list<array>,duplicates?:list<array>,journal_id?:int,status?:string,ready?:bool,errors?:list<array>,warnings?:list<array>} */
function saldi_journal_suggest_perform(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
{
	SaldiPostingRules::helpers();
	if (!$ctx->isHuman()) {
		throw new SaldiRuntimeError('PERMISSION_DENIED', 'Human session required');
	}
	SaldiPermissionService::require($ctx, $db, 'journal.read');
	if (ifset($input, 'op') === 'check') {
		$id = ifset($input, 'kladde_id', ifset($input, 'journal_id'));
		if (!is_int($id) || $id < 1) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid journal id');
		}
		return ['ok' => true] + SaldiJournalCheck::run($ctx, $db, $id);
	}
	if (SaldiSettings::get($db, 'assistant', 'journal_suggestions') !== 'on') {
		return ['ok' => true, 'enabled' => false];
	}
	if ((ifset($input, 'op', '')) === 'suggest') {
		if (!is_int(ifset($input, 'kladde_id')) || $input['kladde_id'] < 1 || !is_array(ifset($input, 'line'))) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid journal line');
		}
		if ($db->value('SELECT id FROM kladdeliste WHERE id = ?', [$input['kladde_id']]) === null) {
			throw new SaldiRuntimeError('NOT_FOUND', 'Journal not found');
		}
		$result = (new SaldiPostingSuggester($ctx, $db))->suggest(array_merge($input['line'], ['kladde_id' => $input['kladde_id']]));
		$now = time();
		$ring = array_values(array_filter(if_array($_SESSION, 'journal_assist_suggestions'), static fn(array $entry): bool => $entry['at'] >= $now - 1800));
		foreach ($result['suggestions'] as $suggestion) {
			$subject = $suggestion['source'] === 'rule' ? 'rule:' . $suggestion['rule_id'] : 'kladde:' . $input['kladde_id'];
			$ring[] = ['at' => $now, 'tenant' => $ctx->db, 'user' => $ctx->userId, 'subject' => $subject, 'source' => $suggestion['source']];
		}
		$_SESSION['journal_assist_suggestions'] = array_slice($ring, -60);
		return ['ok' => true] + $result;
	}
	if ((ifset($input, 'op', '')) !== 'event') {
		throw new SaldiRuntimeError('VALIDATION_ERROR', 'Unknown operation');
	}
	$event = ifset($input, 'event', '');
	$subject = ifset($input, 'subject');
	$source = ifset($input, 'source');
	if (!in_array($event, ['suggestion_shown', 'suggestion_used', 'suggestion_dismissed', 'duplicate_warned', 'ask_from_row'], true)
		|| ($source !== null && !in_array($source, ['rule', 'history'], true))) {
		throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid suggestion event');
	}
	$measured = in_array($event, ['suggestion_used', 'suggestion_shown'], true);
	if ($measured && (!is_string($subject) || !preg_match('/^(?:rule|kladde):[1-9][0-9]{0,9}$/D', $subject))) {
		return ['ok' => true];
	}
	if (!is_string($subject) || !preg_match('/^(?:rule|kladde|line|item):[1-9][0-9]{0,9}$/D', $subject)) {
		throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid suggestion subject');
	}
	$now = time();
	if ($measured) {
		$seen = false;
		foreach (if_array($_SESSION, 'journal_assist_suggestions') as $entry) {
			if ($entry['at'] >= $now - 1800 && $entry['tenant'] === $ctx->db && $entry['user'] === $ctx->userId && $entry['subject'] === $subject && ($source === null || $entry['source'] === $source)) {
				$seen = true;
				break;
			}
		}
		if (!$seen) {
			return ['ok' => true];
		}
	}
	$times = array_values(array_filter(if_array($_SESSION, 'journal_assist_events'), static fn(int $timestamp): bool => $timestamp > $now - 3600));
	if (count($times) >= 120) {
		$_SESSION['journal_assist_events'] = $times;
		return ['ok' => true];
	}
	$db->insert('assist_events', ['created_at' => gmdate('Y-m-d H:i:s', $now), 'user_id' => $ctx->userId, 'event' => $event, 'subject' => $subject, 'source' => $source, 'detail' => null]);
	$times[] = $now;
	$_SESSION['journal_assist_events'] = $times;
	return ['ok' => true];
}

/**
 * POST JSON controller; opens the session only after method, origin, CSRF and body checks.
 *
 * @param callable $session fn(): array{ctx:SaldiTenantContext,db:SaldiDb}
 * @return array{status:int,body:array{ok:bool,error?:array{code:string,message_da:string,detail:array}}}
 */
function saldi_journal_suggest_handle(array $server, string $rawBody, int $brugerId, callable $session, int $languageId = 1): array
{
	SaldiPostingRules::helpers();
	$db = null;
	try {
		if ((ifset($server, 'REQUEST_METHOD', '')) !== 'POST') {
			return ['status' => 405, 'body' => ['ok' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message_da' => SaldiText::get(null, 5732, $languageId, 'Kontrollér de indtastede oplysninger.'), 'detail' => []]]];
		}
		if (!saldi_journal_suggest_same_origin($server)) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Same origin required');
		}
		if ($brugerId <= 0) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'Positive tenant user required');
		}
		if (!saldi_inbox_csrf_check(ifset($server, 'HTTP_X_SALDI_CSRF'))) {
			throw new SaldiRuntimeError('PERMISSION_DENIED', 'CSRF check failed', ['reason' => 'csrf']);
		}
		if (strtolower(trim(explode(';', (string)(ifset($server, 'CONTENT_TYPE', '')))[0])) !== 'application/json' || strlen($rawBody) > 8192) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Bounded JSON body required');
		}
		try {
			$input = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
		} catch (JsonException $error) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid JSON');
		}
		if (!is_array($input) || array_is_list($input) || !in_array(ifset($input, 'op', ''), ['suggest', 'event', 'check'], true)) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Unknown operation');
		}
		$open = $session();
		$ctx = $open['ctx'];
		$db = $open['db'];
		$languageId = $ctx->languageId;
		if (!$ctx->isHuman() || $ctx->userId !== $brugerId) {
			throw new SaldiRuntimeError('UNAUTHENTICATED', 'Session changed');
		}
		return ['status' => 200, 'body' => saldi_journal_suggest_perform($ctx, $db, $input)];
	} catch (Throwable $error) {
		if (!$error instanceof SaldiRuntimeError) {
			error_log('assistSuggest.php: request failed (' . get_class($error) . ')');
			$error = new SaldiRuntimeError('UNAVAILABLE', 'Suggestions unavailable');
		}
		return ['status' => $error->httpStatus(), 'body' => ['ok' => false, 'error' => ['code' => $error->errorCode, 'message_da' => $error->messageDa($db, $languageId), 'detail' => $error->detail]]];
	}
}

if (!defined('SALDI_JOURNAL_SUGGEST_LIBRARY')) {
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}
	try {
		$journalSuggestSession = saldi_journal_suggest_session(session_id());
		$journalSuggestResponse = saldi_journal_suggest_handle($_SERVER, (string)file_get_contents('php://input'), $journalSuggestSession['ctx']->userId, static function () use ($journalSuggestSession): array {
			saldi_runtime_ensure_schema($journalSuggestSession['db']);
			return $journalSuggestSession;
		}, $journalSuggestSession['ctx']->languageId);
	} catch (Throwable $error) {
		$runtimeError = $error instanceof SaldiRuntimeError ? $error : new SaldiRuntimeError('UNAVAILABLE', 'Suggestions unavailable');
		$journalSuggestResponse = ['status' => $runtimeError->httpStatus(), 'body' => ['ok' => false, 'error' => ['code' => $runtimeError->errorCode, 'message_da' => $runtimeError->messageDa()]]];
	}
	http_response_code($journalSuggestResponse['status']);
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	header('X-Content-Type-Options: nosniff');
	echo json_encode($journalSuggestResponse['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}
