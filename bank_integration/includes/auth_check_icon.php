<?php
// Included by finans/topLineFinans.php, finans/kassekladde_includes/topLineKassekladde.php and
// index/dashboardIncludes/bank_authenticated.php. Renders the bank feed status indicator.
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/auth_check_icon.php --- ver 5.1.1 --- 2026-11-18 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
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
// 20260526 NTR - Initial version. Shows the authentication status icon.
// 20260915 CL/NTR Render nothing unless the bank integration API credentials are
//                 configured (bankIntegrationEnabled()).
// 20261006 CL/LH Rewritten as a provider-neutral bank feed status indicator for Enable Banking: green when all accounts are fine, yellow when access expires within 14 days or something waits for the user, red on errors or expired access. Links to the bank feed page.
// 20261007 CDX/LH Used Danish error explanations and support codes in bank status tooltips.
// 20261028 CDX/LH Remove the retired onboarding account state from the status indicator.
// 20261028 CDX/LH Show waiting rows, paused fetching, stale feeds and missing worker heartbeats.
// 20261028 CL/LH Singular "1 ny bankpostering" and "N posteringer til gennemsyn" in the tooltip.
// 20261028 CL/LH Stale warning only for an overdue scheduled fetch; support codes in one casing.
// 20261116 CL/LH Shown to users who switched on "Vis bankstatus i kassekladden" (Personlige indstillinger), as the old icon was.
// 20261116 CL/LH The personal setting is read first, so a user without the icon costs one query and no bank schema checks.
//                Texts through findtekst() (bank.status.*).
// 20261118 CL/LH Live port: the switch for the icon is in the Bankforbindelse section (live has no Personlige indstillinger).
// 20261118 CL/LH The coloured dot is a short text link, "Bank: 186 nye", "Bank: ajour" or "Bank: forny adgang", amber or red when something needs the user (ebStatusLabel()).
//                The link opens the feed on the account that needs the user most, for the journal on screen; with unsaved changes in the journal it goes through the Bankposteringer button, which saves first.
//                Accounts are named ledger-first in the tooltip (ebAccountLabel()).
// 20261118 CL/LH CodeRabbit: an action-required run (stored as failed) shows "forny adgang", not "fejl"; the link opens the account behind the most urgent problem shown (renew, error, late, renew soon), the first one on a tie.

include_once __DIR__ . '/enabled.php';

if (!function_exists('ebStatusIndicator')) {
	/**
	 * Works out the overall bank feed status over all linked accounts.
	 *
	 * @return array{
	 *   level: int,              0 = nothing to show, 1 = ok, 2 = needs attention, 3 = error.
	 *   reasons: array<int, string>,  Danish status details, shown as tooltip.
	 *   href: string,            Link target relative to a first-level directory: the feed, on the account that needs
	 *                            the user most, and for the journal on screen when there is one.
	 *   label: string            Short Danish status after "Bank:", e.g. "186 nye", "ajour", "forny adgang" (UTF-8).
	 * }
	 */
	function ebStatusIndicator(): array {
		$result = array('level' => 0, 'reasons' => array(), 'href' => '../bank_integration/feed.php', 'label' => '');
		if (!ebStatusIconWanted() || !bankIntegrationEnabled() || !ebCanViewFeed()) {
			return $result;
		}
		$accounts = array();
		foreach (ebListAccounts() as $account) {
			if (in_array((string)($account['state'] ?? ''), array('new', 'active', 'paused'), true)) {
				$accounts[] = $account;
			}
		}
		if (!$accounts) {
			return $result;
		}
		$connections = ebIndexById(ebListConnections());
		$level = 1;
		$overdue = false;
		// What the short label says, most urgent first (see ebStatusLabel()).
		$seen = array('renew' => false, 'error' => false, 'late' => false, 'renew_soon' => false, 'review' => 0,
			'rate_limited' => false, 'unused' => 0, 'paused' => false, 'waiting' => 0);
		$problemAccount = 0;
		$problemRank = 0;
		$waitingAccount = 0;
		$mostWaiting = 0;
		foreach ($accounts as $account) {
			$connection = $connections[(int)($account['connection_id'] ?? 0)] ?? array();
			$name = ebStatusAccountName($account, $connection);
			$status = ebNormalizeStatus(ebAccountStatus((int)$account['id']), $account, $connection);
			$failed = in_array($status['last_run_status'], array('failed', 'partial'), true);
			// How urgent this account's problem is, in ebStatusLabel()'s order: 4 renew, 3 error, 2 late, 1 renew soon, 0 none.
			$rank = 0;
			if ($status['expired']) {
				$level = 3;
				$seen['renew'] = true;
				$rank = 4;
				$result['reasons'][] = "$name: " . ebErrorText($status['last_error_code'], 'consent');
			} elseif ($status['last_error_code'] === 'ASPSP_PSU_ACTION_REQUIRED') {
				// Before the failed-run branch: an action-required run is stored as failed, and the label must say forny adgang.
				$level = 3;
				$seen['renew'] = true;
				$rank = 4;
				$result['reasons'][] = "$name: " . ebErrorText($status['last_error_code'], 'action_required');
			} elseif ($failed || $status['connection_status'] === 'error') {
				$level = 3;
				$seen['error'] = true;
				$rank = 3;
				$result['reasons'][] = "$name: " . ebErrorText($status['last_error_code'], null);
			} else {
				if ($status['days_left'] !== null && $status['days_left'] <= 14) {
					$level = max($level, 2);
					$seen['renew_soon'] = true;
					$rank = 1;
					$result['reasons'][] = "$name: " . ebConsentText($status);
				}
				if ($status['state'] === 'new') {
					$level = max($level, 2);
					$seen['unused']++;
					$result['reasons'][] = "$name: " . ebAccountStateLabel($status['state']);
				}
				if ($status['review'] > 0) {
					$level = max($level, 2);
					$seen['review'] += $status['review'];
					$result['reasons'][] = "$name: " . ($status['review'] === 1 ? ebT(findtekst('bank.status.review_one|1 postering til gennemsyn', ebLang())) : sprintf(ebT(findtekst('bank.status.review_many|%s posteringer til gennemsyn', ebLang())), $status['review']));
				}
				if ($status['last_run_status'] === 'rate_limited') {
					$level = max($level, 2);
					$seen['rate_limited'] = true;
					$result['reasons'][] = "$name: " . ebErrorText($status['last_error_code'], 'rate_limit');
				}
			}
			if ($status['waiting'] > 0) {
				$seen['waiting'] += $status['waiting'];
				if ($status['waiting'] > $mostWaiting) {
					$mostWaiting = $status['waiting'];
					$waitingAccount = (int)$account['id'];
				}
				$oldest = $status['waiting_oldest'];
				$result['reasons'][] = "$name: " . ($status['waiting'] === 1 ? ebT(findtekst('bank.status.new_one|1 ny bankpostering', ebLang())) : sprintf(ebT(findtekst('bank.status.new_many|%s nye bankposteringer', ebLang())), $status['waiting']))
					. ($oldest === null ? '' : ' ' . sprintf(ebT(findtekst('bank.status.oldest|(ældste %s)', ebLang())), date('d.m.Y', strtotime($oldest))));
				if ($oldest !== null && $oldest < date('Y-m-d', strtotime(ebToday() . ' -14 days'))) {
					$level = max($level, 2);
				}
			}
			if ($status['state'] === 'paused') {
				$seen['paused'] = true;
				$result['reasons'][] = "$name: " . ebT(findtekst('bank.status.paused|Automatisk hentning er sat på pause', ebLang()));
			} elseif ($status['state'] === 'active') {
				// Only an overdue scheduled fetch counts as stale: right after Genoptag or a renewal the next
				// fetch is due now, and stopped accounts (expired access, period errors) report their own reason.
				$fetchOverdue = $status['next_fetch_at'] !== '' && strtotime($status['next_fetch_at']) < time() - 3600;
				if ($fetchOverdue && $status['stale_hours'] !== null && $status['stale_hours'] > 13) {
					$level = max($level, $status['stale_hours'] > 30 ? 3 : 2);
					$seen['late'] = true;
					$rank = max($rank, 2);
					$result['reasons'][] = "$name: " . sprintf(ebT(findtekst('bank.status.stale|Ingen hentning i %s timer', ebLang())), $status['stale_hours']);
				}
				if ($fetchOverdue) {
					$overdue = true;
				}
			}
			// The link opens the account behind the most urgent problem shown; the first one on a tie.
			if ($rank > $problemRank && $status['state'] !== 'new') {
				$problemAccount = (int)$account['id'];
				$problemRank = $rank;
			}
			if ($status['last_error_code'] !== '') {
				$result['reasons'][] = sprintf(ebT(findtekst('bank.error.code|Kode: %s', ebLang())), strtoupper($status['last_error_code']));
			}
		}
		if ($overdue) {
			$heartbeatAge = ebWorkerHeartbeatAge();
			if ($heartbeatAge === null || $heartbeatAge > 3600) {
				$level = 3;
				$seen['error'] = true;
				$result['reasons'][] = ebT(findtekst('bank.status.worker_down|Den automatiske hentning kører ikke. Kontakt Saldi-support.', ebLang()));
			}
		}
		$result['level'] = $level;
		$result['label'] = ebStatusLabel($seen);
		// The feed opens on the account that needs the user most, and for the journal on screen.
		$params = array();
		$target = $problemAccount ?: ($waitingAccount ?: (count($accounts) === 1 ? (int)$accounts[0]['id'] : 0));
		if ($target > 0) {
			$params['account'] = $target;
		}
		$kladdeId = ebStatusJournalId();
		if ($kladdeId > 0) {
			$params['kladde_id'] = $kladdeId;
		}
		if ($params) {
			$result['href'] .= '?' . http_build_query($params);
		}
		return $result;
	}

	/**
	 * Name of an account in the status tooltip: ledger first like everywhere else ("9800 Bank & giro · Nordea …8028").
	 *
	 * @param array<string, mixed> $account bank_accounts row.
	 * @param array<string, mixed> $connection Its bank_connections row (empty when unknown).
	 * @return string Plain UTF-8 text.
	 */
	function ebStatusAccountName(array $account, array $connection): string {
		if (function_exists('ebAccountLabel')) {
			return ebAccountLabel($account, $connection ?: null);
		}
		$name = (string)($account['display_name'] ?? '');
		$name = function_exists('ebHolderName') ? ebHolderName($name) : trim($name);
		return $name !== '' ? $name : ebT(findtekst('bank.feed.bank_account|Bankkonto', ebLang()));
	}

	/**
	 * The short status after "Bank:" in the journal's top bar. New lines come first, as they are the daily work;
	 * anything that stops the feed or needs a decision is added after them, and with nothing waiting the most
	 * urgent thing is shown, else "ajour". The tooltip has the details per account.
	 *
	 * @param array{renew: bool, error: bool, late: bool, renew_soon: bool, review: int, rate_limited: bool, unused: int, paused: bool, waiting: int} $seen
	 * @return string Plain UTF-8 text, e.g. "186 nye · forny adgang".
	 */
	function ebStatusLabel(array $seen): string {
		$parts = array();
		if ($seen['waiting'] > 0) {
			$parts[] = $seen['waiting'] === 1 ? ebT(findtekst('bank.status.short_new_one|1 ny', ebLang()))
				: sprintf(ebT(findtekst('bank.status.short_new_many|%s nye', ebLang())), $seen['waiting']);
		}
		$attention = '';
		if ($seen['renew']) {
			$attention = ebT(findtekst('bank.status.short_renew|forny adgang', ebLang()));
		} elseif ($seen['error']) {
			$attention = ebT(findtekst('bank.status.short_error|fejl', ebLang()));
		} elseif ($seen['late']) {
			$attention = ebT(findtekst('bank.status.short_late|hentning forsinket', ebLang()));
		} elseif ($seen['renew_soon']) {
			$attention = ebT(findtekst('bank.status.short_renew|forny adgang', ebLang()));
		} elseif ($seen['review'] > 0) {
			$attention = sprintf(ebT(findtekst('bank.status.short_review|%s til gennemsyn', ebLang())), $seen['review']);
		} elseif (!$parts) {
			// Less urgent, so only when nothing is waiting.
			if ($seen['rate_limited']) {
				$attention = ebT(findtekst('bank.status.short_late|hentning forsinket', ebLang()));
			} elseif ($seen['unused'] > 0) {
				$attention = $seen['unused'] === 1 ? ebT(findtekst('bank.status.short_unused_one|1 konto ikke i brug', ebLang()))
					: sprintf(ebT(findtekst('bank.status.short_unused_many|%s konti ikke i brug', ebLang())), $seen['unused']);
			} elseif ($seen['paused']) {
				$attention = ebT(findtekst('bank.status.short_paused|sat på pause', ebLang()));
			}
		}
		if ($attention !== '') {
			$parts[] = $attention;
		}
		return $parts ? implode(' · ', $parts) : ebT(findtekst('bank.status.short_ok|ajour', ebLang()));
	}

	/**
	 * The unposted journal on screen, when the indicator is printed in the kassekladde (topLineKassekladde.php).
	 *
	 * @return int kladdeliste.id, 0 elsewhere.
	 */
	function ebStatusJournalId(): int {
		if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'kassekladde.php' || ($GLOBALS['bogfort'] ?? '') !== '-') {
			return 0;
		}
		return max(0, (int)($GLOBALS['kladde_id'] ?? 0));
	}


	/**
	 * The icon is a personal choice (Indstillinger » Diverse » Bankforbindelse » Vis bankstatus i kassekladden,
	 * settings 'bank_integration'/'show_status' of the user), off until the user switches it on.
	 *
	 * @return bool
	 */
	function ebStatusIconWanted(): bool {
		$userId = (int)($GLOBALS['bruger_id'] ?? 0);
		return $userId > 0 && ebRow("SELECT id FROM settings WHERE var_grp='bank_integration' AND var_name='show_status' AND user_id="
			. $userId . " AND var_value='1'") !== null;
	}

	/**
	 * Prints the status as a short text link to the feed, "Bank: 186 nye" (nothing when the feed is not in use or the
	 * user may not see it). Amber when something needs attention, red on errors; the tooltip has the details.
	 * In the kassekladde, a click with unsaved changes goes through the journal's Bankposteringer button, which saves first.
	 *
	 * @return void
	 */
	function ebRenderStatusIndicator(): void {
		try {
			$status = ebStatusIndicator();
		} catch (Throwable $e) {
			// A status icon must never break the page that includes it.
			return;
		}
		if ($status['level'] === 0) {
			return;
		}
		$titles = array(
			1 => ebT(findtekst('bank.status.ok|Bankforbindelse: alt er i orden', ebLang())),
			2 => ebT(findtekst('bank.status.attention|Bankforbindelse kræver opmærksomhed', ebLang())),
			3 => ebT(findtekst('bank.status.error|Bankforbindelse: fejl', ebLang())),
		);
		$title = $titles[$status['level']];
		if ($status['reasons']) {
			$title .= "\n" . implode("\n", $status['reasons']);
		}
		$onclick = ebStatusJournalId() > 0
			? " onclick=\"if (window.docChange) { var b = document.querySelector('input[name=bankfeed]'); if (b) { b.click(); return false; } }\""
			: '';
		// Printed inside the kassekladde and finans pages, which use the tenant's charset.
		print "<style>
			a.auth-status-icon.eb-status { display: inline-flex; align-items: center; gap: 4px; margin: 0 6px 0 auto; padding: 2px 10px;
				border-radius: 10px; font-size: 13px; line-height: 18px; white-space: nowrap; color: #fff; text-decoration: none; }
			a.auth-status-icon.eb-status:hover .eb-status-text { text-decoration: underline; }
			a.auth-status-icon.eb-status-2 { background: #ffe08a; color: #3d2f00; }
			a.auth-status-icon.eb-status-3 { background: #c62828; color: #fff; }
			a.auth-status-icon.eb-status .eb-status-text { font-weight: 600; }
		</style>";
		print "<a class='auth-status-icon eb-status eb-status-" . (int)$status['level'] . "' href='" . ebH($status['href']) . "' title='" . ebPageH($title) . "'$onclick>";
		print "<span class='auth-login-label'>" . ebPageH(ebT(findtekst('bank.status.label|Bank:', ebLang()))) . "</span>"
			. "<span class='eb-status-text'>" . ebPageH($status['label']) . "</span>";
		print "</a>";
	}
}

ebRenderStatusIndicator();
