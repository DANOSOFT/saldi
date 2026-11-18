<?php
// Included by finans/topLineFinans.php, finans/kassekladde_includes/topLineKassekladde.php and
// index/dashboardIncludes/bank_authenticated.php. Renders the bank feed status indicator.
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/auth_check_icon.php --- ver 5.1.1 --- 2026-11-16 ---
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

include_once __DIR__ . '/enabled.php';

if (!function_exists('ebStatusIndicator')) {
	/**
	 * Works out the overall bank feed status over all linked accounts.
	 *
	 * @return array{
	 *   level: int,              0 = nothing to show, 1 = ok, 2 = needs attention, 3 = error.
	 *   reasons: array<int, string>,  Danish status details, shown as tooltip.
	 *   href: string             Link target relative to a first-level directory.
	 * }
	 */
	function ebStatusIndicator(): array {
		$result = array('level' => 0, 'reasons' => array(), 'href' => '../bank_integration/feed.php');
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
		if (count($accounts) === 1) {
			$result['href'] .= '?account=' . (int)$accounts[0]['id'];
		}
		$connections = ebIndexById(ebListConnections());
		$level = 1;
		$overdue = false;
		foreach ($accounts as $account) {
			$name = trim((string)($account['display_name'] ?? '')) ?: ebT(findtekst('bank.feed.bank_account|Bankkonto', ebLang()));
			$connection = $connections[(int)($account['connection_id'] ?? 0)] ?? array();
			$status = ebNormalizeStatus(ebAccountStatus((int)$account['id']), $account, $connection);
			$failed = in_array($status['last_run_status'], array('failed', 'partial'), true);
			if ($status['expired']) {
				$level = 3;
				$result['reasons'][] = "$name: " . ebErrorText($status['last_error_code'], 'consent');
			} elseif ($failed || $status['connection_status'] === 'error') {
				$level = 3;
				$result['reasons'][] = "$name: " . ebErrorText($status['last_error_code'], null);
			} elseif ($status['last_error_code'] === 'ASPSP_PSU_ACTION_REQUIRED') {
				$level = 3;
				$result['reasons'][] = "$name: " . ebErrorText($status['last_error_code'], 'action_required');
			} else {
				if ($status['days_left'] !== null && $status['days_left'] <= 14) {
					$level = max($level, 2);
					$result['reasons'][] = "$name: " . ebConsentText($status);
				}
				if ($status['state'] === 'new') {
					$level = max($level, 2);
					$result['reasons'][] = "$name: " . ebAccountStateLabel($status['state']);
				}
				if ($status['review'] > 0) {
					$level = max($level, 2);
					$result['reasons'][] = "$name: " . ($status['review'] === 1 ? ebT(findtekst('bank.status.review_one|1 postering til gennemsyn', ebLang())) : sprintf(ebT(findtekst('bank.status.review_many|%s posteringer til gennemsyn', ebLang())), $status['review']));
				}
				if ($status['last_run_status'] === 'rate_limited') {
					$level = max($level, 2);
					$result['reasons'][] = "$name: " . ebErrorText($status['last_error_code'], 'rate_limit');
				}
			}
			if ($status['waiting'] > 0) {
				$oldest = $status['waiting_oldest'];
				$result['reasons'][] = "$name: " . ($status['waiting'] === 1 ? ebT(findtekst('bank.status.new_one|1 ny bankpostering', ebLang())) : sprintf(ebT(findtekst('bank.status.new_many|%s nye bankposteringer', ebLang())), $status['waiting']))
					. ($oldest === null ? '' : ' ' . sprintf(ebT(findtekst('bank.status.oldest|(ældste %s)', ebLang())), date('d.m.Y', strtotime($oldest))));
				if ($oldest !== null && $oldest < date('Y-m-d', strtotime(ebToday() . ' -14 days'))) {
					$level = max($level, 2);
				}
			}
			if ($status['state'] === 'paused') {
				$result['reasons'][] = "$name: " . ebT(findtekst('bank.status.paused|Automatisk hentning er sat på pause', ebLang()));
			} elseif ($status['state'] === 'active') {
				// Only an overdue scheduled fetch counts as stale: right after Genoptag or a renewal the next
				// fetch is due now, and stopped accounts (expired access, period errors) report their own reason.
				$fetchOverdue = $status['next_fetch_at'] !== '' && strtotime($status['next_fetch_at']) < time() - 3600;
				if ($fetchOverdue && $status['stale_hours'] !== null && $status['stale_hours'] > 13) {
					$level = max($level, $status['stale_hours'] > 30 ? 3 : 2);
					$result['reasons'][] = "$name: " . sprintf(ebT(findtekst('bank.status.stale|Ingen hentning i %s timer', ebLang())), $status['stale_hours']);
				}
				if ($fetchOverdue) {
					$overdue = true;
				}
			}
			if ($status['last_error_code'] !== '') {
				$result['reasons'][] = sprintf(ebT(findtekst('bank.error.code|Kode: %s', ebLang())), strtoupper($status['last_error_code']));
			}
		}
		if ($overdue) {
			$heartbeatAge = ebWorkerHeartbeatAge();
			if ($heartbeatAge === null || $heartbeatAge > 3600) {
				$level = 3;
				$result['reasons'][] = ebT(findtekst('bank.status.worker_down|Den automatiske hentning kører ikke. Kontakt Saldi-support.', ebLang()));
			}
		}
		$result['level'] = $level;
		return $result;
	}

	/**
	 * The icon is a personal choice (Indstillinger » Personlige indstillinger » Vis bankstatus i kassekladden,
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
	 * Prints the status indicator (nothing when the feed is not in use or the user may not see it).
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
		$icons = array(
			1 => '../ikoner/circle_check_filled.png',
			2 => '../ikoner/circle_check_filled_yellow.png',
			3 => '../ikoner/circle_no_check_filled.png',
		);
		$titles = array(
			1 => ebT(findtekst('bank.status.ok|Bankforbindelse: alt er i orden', ebLang())),
			2 => ebT(findtekst('bank.status.attention|Bankforbindelse kræver opmærksomhed', ebLang())),
			3 => ebT(findtekst('bank.status.error|Bankforbindelse: fejl', ebLang())),
		);
		$title = $titles[$status['level']];
		if ($status['reasons']) {
			$title .= "\n" . implode("\n", $status['reasons']);
		}
		// Printed inside the kassekladde and finans pages, which use the tenant's charset.
		print "<a class='auth-status-icon' href='" . ebH($status['href']) . "' title='" . ebPageH($title) . "'>";
		print "<span class='auth-login-label'>" . ebPageH(ebT(findtekst('bank.status.label|Bank:', ebLang()))) . "</span><img src='" . ebH($icons[$status['level']]) . "' alt='" . ebPageH($titles[$status['level']]) . "'>";
		print "</a>";
	}
}

ebRenderStatusIndicator();
