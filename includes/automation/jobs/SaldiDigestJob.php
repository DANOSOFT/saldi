<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/jobs/SaldiDigestJob.php --- ai/mcp-assistant --- 2026.10.02
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
// Send permission-filtered daily overviews only to opted-in users, with honest empty runs.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Add Copenhagen scheduling, bounded digests and local transport seams.

declare(strict_types=1);

require_once __DIR__ . '/../../runtime/bootstrap.php';
require_once __DIR__ . '/../SaldiMailer.php';
require_once __DIR__ . '/../SaldiInboxService.php';

final class SaldiDigestJob
{
	/** @return array{status:string,result:array{value:string,sent?:int,empty?:int},error?:string} */
	public static function run(SaldiDb $db, array $job, ?SaldiMailer $mailer = null, ?callable $overviewBuilder = null, ?callable $contextFactory = null): array
	{
		if (getenv('SALDI_DIGEST_DISABLED') === '1' || getenv('SALDI_MAIL_DISABLED') === '1') {
			return ['status' => 'skipped', 'result' => ['value' => 'disabled']];
		}
		$payload = json_decode($job['payload'], true, 32, JSON_THROW_ON_ERROR);
		$date = $payload['scheduled_date'] ?? '';
		$parsed = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
		if (!$parsed || $parsed->format('Y-m-d') !== $date) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid digest date');
		}
		$users = $db->all("SELECT u.id, u.email FROM brugere u WHERE EXISTS (SELECT 1 FROM settings s WHERE s.user_id = u.id AND s.var_grp = 'assistant' AND s.var_name = 'digest' AND COALESCE(s.pos_id, 0) = 0 AND s.var_value = 'daily' AND s.id = (SELECT MAX(latest.id) FROM settings latest WHERE latest.user_id = u.id AND latest.var_grp = 'assistant' AND latest.var_name = 'digest' AND COALESCE(latest.pos_id, 0) = 0)) ORDER BY u.id");
		if (!$users) {
			return ['status' => 'skipped', 'result' => ['value' => 'no subscribers']];
		}
		if ($overviewBuilder === null) {
			$file = __DIR__ . '/../SaldiDailyOverview.php';
			if (!is_file($file)) {
				throw new SaldiRuntimeError('UNAVAILABLE', 'Daily overview unavailable');
			}
			require_once $file;
			$overviewBuilder = SaldiDailyOverview::build(...);
		}
		$regnskab = $contextFactory === null ? saldi_runtime_tenant_by_db($db->name())['regnskab'] : null;
		$mailer ??= new SaldiMailer();
		$deliveryDb = $db->inTransaction() ? SaldiDb::connect(saldi_runtime_config(), $db->name()) : $db;
		$company = (string)($db->value("SELECT firmanavn FROM adresser WHERE art = 'S' ORDER BY id LIMIT 1") ?? '');
		$inbox = new SaldiInboxService($db, SaldiKindRegistry::default());
		$sent = 0;
		$empty = 0;
		foreach ($users as $user) {
			$userId = (int)$user['id'];
			if ($db->value("SELECT id FROM assist_events WHERE event = 'digest_sent' AND user_id = ? AND subject = ? LIMIT 1", [$userId, 'digest:' . $date]) !== null) {
				continue;
			}
			$ctx = $contextFactory ? $contextFactory($userId) : saldi_runtime_context_for_user($db, $regnskab, $userId, 'user:' . $userId);
			if (!$ctx instanceof SaldiTenantContext || !$ctx->isHuman() || $ctx->userId !== $userId || $ctx->db !== $db->name()) {
				throw new SaldiRuntimeError('PERMISSION_DENIED', 'Invalid digest user context');
			}
			$overview = $overviewBuilder($ctx, $db);
			$count = $inbox->countOpen($ctx);
			if (($overview['nothing_to_do'] ?? false) === true && $count === 0) {
				$empty++;
				continue;
			}
			$email = $db->value("SELECT var_value FROM settings WHERE user_id = ? AND var_grp = 'assistant' AND var_name = 'digest_email' AND COALESCE(pos_id, 0) = 0 ORDER BY id DESC LIMIT 1", [$userId]);
			$email = trim((string)$email) ?: (string)$user['email'];
			$text = static fn(int $id, string $fallback): string => SaldiText::get($db, $id, $ctx->languageId, $fallback);
			$lines = [sprintf($text(6011, 'Du har %d åbne opgaver i Saldi.'), $count)];
			foreach (self::overviewLines($overview, $text) as $line) {
				$lines[] = $line;
			}
			foreach ($inbox->list($ctx, ['status' => 'open', 'limit' => 10]) as $item) {
				$lines[] = '- ' . preg_replace('/[\x00-\x1f\x7f]/u', '', $item['title']);
			}
			$url = getenv('SALDI_PUBLIC_URL');
			$linkIndex = null;
			if ($url && saldi_runtime_origin($url) !== null && !preg_match('/[\r\n]/', $url)) {
				$linkIndex = count($lines);
				$lines[] = $text(6012, 'Åbn Saldi') . ': ' . $url;
			}
			$lines[] = '';
			$lines[] = $text(6013, 'Du får denne mail, fordi du har slået dagligt overblik til under Saldi Assist. Du slår det fra samme sted.');
			$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
			$htmlLines = array_map($escape, $lines);
			if ($linkIndex !== null) {
				$htmlLines[$linkIndex] = '<a href="' . $escape($url) . '">' . $escape($text(6012, 'Åbn Saldi')) . '</a>';
			}
			$html = '<html><body><p>' . implode("<br>\n", $htmlLines) . '</p></body></html>';
			$outcome = $deliveryDb->transaction(static function () use ($deliveryDb, $db, $mailer, $userId, $date, $email, $text, $company, $lines, $html): array {
				$deliveryDb->lock('saldi_digest:' . $userId . ':' . $date);
				if ($deliveryDb->value("SELECT id FROM assist_events WHERE event = 'digest_sent' AND user_id = ? AND subject = ? LIMIT 1", [$userId, 'digest:' . $date]) !== null) {
					return ['status' => 'skipped'];
				}
				$outcome = $mailer->send($db, $email, sprintf($text(6010, 'Saldi: dagens overblik for %s'), $company), implode("\n", $lines), $html);
				if ($outcome['status'] === 'sent') {
					$deliveryDb->insert('assist_events', ['created_at' => gmdate('Y-m-d H:i:s'), 'user_id' => $userId, 'event' => 'digest_sent', 'subject' => 'digest:' . $date]);
				}
				return $outcome;
			});
			if ($outcome['status'] === 'failed') {
				return ['status' => 'failed', 'error' => 'digest delivery failed', 'result' => ['value' => 'delivery failed', 'sent' => $sent, 'empty' => $empty]];
			}
			if ($outcome['status'] === 'sent') {
				$sent++;
			}
		}
		return ['status' => 'done', 'result' => ['value' => $sent ? 'sent' : 'none', 'sent' => $sent, 'empty' => $empty]];
	}
	/** @return list<string> */
	private static function overviewLines(array $overview, callable $text): array
	{
		$lines = [];
		foreach (['overdue_debtors' => [5952, 'Forfaldne kunder'], 'open_journals' => [5953, 'Åbne kladder'], 'pool_documents' => [5955, 'Bilag i puljen'], 'low_stock' => [5956, 'Lav lagerbeholdning']] as $key => [$id, $fallback]) {
			if (isset($overview[$key])) {
				$section = $overview[$key];
				$line = $text($id, $fallback) . ': ' . (int)$section['count'];
				if (isset($section['amount'], $section['currency'])) {
					$line .= ' (' . $section['amount'] . ' ' . $section['currency'] . ')';
				}
				$lines[] = $line;
			}
		}
		if (isset($overview['vat'])) {
			$vat = $overview['vat'];
			$lines[] = $text(5954, 'Moms') . ': ' . $vat['deadline'] . ' (' . (int)$vat['days_left'] . ' ' . $text(5969, 'dage') . '), ' . (int)$vat['missing_documents'];
			$lines[] = (string)$vat['note'];
		}
		return $lines;
	}
	public static function shouldEnqueue(SaldiDb $db, DateTimeImmutable $nowLocal): bool
	{
		if (getenv('SALDI_DIGEST_DISABLED') === '1' || getenv('SALDI_MAIL_DISABLED') === '1' || (int)$nowLocal->setTimezone(new DateTimeZone('Europe/Copenhagen'))->format('H') < 7) {
			return false;
		}
		return $db->value("SELECT s.id FROM settings s JOIN brugere u ON u.id = s.user_id WHERE s.var_grp = 'assistant' AND s.var_name = 'digest' AND s.var_value = 'daily' AND COALESCE(s.pos_id, 0) = 0 AND s.id = (SELECT MAX(latest.id) FROM settings latest WHERE latest.user_id = s.user_id AND latest.var_grp = 'assistant' AND latest.var_name = 'digest' AND COALESCE(latest.pos_id, 0) = 0) LIMIT 1") !== null;
	}
}
