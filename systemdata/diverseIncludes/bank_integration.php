<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/diverseIncludes/bank_integration.php --- ver 5.1.1 --- 2026-11-18 ---
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
// but WITHOUT ANY WARRANTY OF ANY KIND.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// 20260609 NTR - Initial version. Bank integration settings.
// 20261004 SS show_status is a personal setting now (Indstillinger » Personlige indstillinger, settings redesign G2.7); only the date method stays here.
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
// 20261116 CL/LH Rewritten for Enable Banking: company toggle, server configuration status, connections and accounts with links to connect/accounts/feed. The Aiia date method is gone. bankIntegrationSave() handles the POST (CSRF and the settings.finance right, then redirect) before diverse.php prints anything. Texts through findtekst() (bank.settings.*), printed in the page's charset (ebPageH()).
// 20261118 CL/LH UI pass: heading "Bankforbindelse" and one intro sentence names Enable Banking; no "Server: Klar", and the sandbox pill only outside production.
//                The company switch and Gem are shown to managers only, and the switch is only written and audited when it changes.
//                "Tilføj bankforbindelse" is a button, Bankkonti and Bankposteringer secondary buttons.
//                Accounts are named ledger-first (ebAccountLabel()) with the holders joined (ebHolderName()), an unmapped one reads "Ikke i brug", a removed connection says so once, and no stale fetch errors.
// 20261118 CL/LH Switching the feed back on with a bank still connected says that fetching resumes, not "Forbind en bank".

require_once __DIR__ . '/../../bank_integration/includes/ebAccess.php';

/**
 * Saves the section's form and redirects back (Post/Redirect/Get). Called by diverse.php before output.
 * The form is only shown to users who may change the company switch (ebCanManage()), which is checked again here.
 * The personal status switch lives in Indstillinger » Personlige indstillinger.
 *
 * @return never
 */
function bankIntegrationSave(): never {
	global $brugernavn;
	$self = 'diverse.php?sektion=bank_integration';
	ebCsrfCheck();
	$saved = ebT(findtekst('bank.settings.saved|Indstillingen er gemt.', ebLang()));
	if (!ebCanManage()) {
		ebFlash('error', ebT(findtekst('bank.settings.no_right|Du har ikke adgang til at ændre indstillingen. Det kræver adgang til Indstillinger.', ebLang())));
		ebRedirect($self);
	}
	if (!ebFeatureAvailable()) {
		ebFlash('error', ebT(findtekst('bank.unavailable.server|Bankforbindelse er ikke sat op på denne Saldi-server. Kontakt Saldi-support, hvis du vil bruge den.', ebLang())));
		ebRedirect($self);
	}
	$on = !empty($_POST['eb_enabled']);
	try {
		if (!ebSchemaReady()) {
			// The tables are created at login (includes/betweenUpdates.php), never by a page.
			throw new EbUserError(ebT(findtekst('bank.unavailable.schema|Bankforbindelse er ikke klar i dette regnskab endnu. Log ud og ind igen, eller kontakt Saldi-support.', ebLang())));
		}
		// Gem with an unchanged switch writes (and audits) nothing.
		if ($on !== ebCompanyEnabled()) {
			ebSetCompanyEnabled($on, (string)$brugernavn);
			// Switched back on with a bank still connected: fetching simply resumes, so do not ask for a new connection.
			$connected = $on && ebRow("SELECT id FROM bank_connections WHERE status='active'") !== null;
			ebFlash('ok', $connected
				? ebT(findtekst('bank.settings.enabled_connected|Bankforbindelse er slået til. Saldi henter igen fra de forbundne bankkonti.', ebLang()))
				: ($on ? ebT(findtekst('bank.settings.enabled|Bankforbindelse er slået til. Forbind en bank for at komme i gang.', ebLang()))
				: ebT(findtekst('bank.settings.disabled|Bankforbindelse er slået fra. Der hentes ikke flere posteringer, men forbindelser og data er bevaret.', ebLang()))));
		} else {
			ebFlash('ok', $saved);
		}
	} catch (Throwable $e) {
		ebFlash('error', ebErrorMessage($e));
	}
	ebRedirect($self);
}

/**
 * Renders the Bankforbindelse section inside the diverse.php settings table.
 *
 * @return void
 */
function bankIntegrationSettings(): void {
	$canManage   = ebCanManage();
	$available   = false;
	$config      = array();
	$enabled     = false;
	$ready       = false;
	$connections = array();
	$accounts    = array();
	$loadError   = '';
	try {
		$config    = ebConfig();
		$available = ebFeatureAvailable();
		$ready     = $available && ebSchemaReady();
		$enabled   = $ready && ebCompanyEnabled();
		if ($ready) {
			$connections = ebListConnections();
			$accounts    = ebListAccounts();
		}
	} catch (Throwable $e) {
		$loadError = ebErrorMessage($e);
	}
	$accountsByConnection = array();
	foreach ($accounts as $account) {
		$accountsByConnection[(int)($account['connection_id'] ?? 0)][] = $account;
	}
	$base = '../bank_integration/';
	$t = static fn(string $text): string => ebPageH(ebT($text));
	// Only outside production: a test installation says so next to the heading.
	$sandbox = $available && (string)($config['environment'] ?? '') !== 'PRODUCTION'
		? " <span class='ebset-env'>" . $t(findtekst('bank.settings.sandbox|Testmiljø (sandbox)', ebLang())) . "</span>" : '';
	?>
	<tr><td colspan="6"><hr></td></tr>
	<tr><td colspan="6"><b><?= $t(findtekst('bank.settings.menu|Bankforbindelse', ebLang())) ?></b><?= $sandbox ?></td></tr>
	<tr><td colspan="6">
	<style>
		.ebset { max-width: 820px; font-size: 13px; }
		.ebset p { margin: 6px 0; }
		.ebset .muted { color: #666; font-size: 12px; }
		.ebset table { border-collapse: collapse; width: 100%; margin: 6px 0 10px 0; }
		.ebset th { text-align: left; background: rgba(17,70,145,0.09); padding: 4px 8px; font-weight: 600; }
		.ebset td { padding: 4px 8px; border-top: 1px solid #e3e6ee; vertical-align: top; }
		.ebset .badge { display: inline-block; border-radius: 10px; padding: 0 8px; font-size: 12px; }
		.ebset .ok { background: #e6f4ea; color: #1a6b3a; }
		.ebset .warn { background: #fff3cd; color: #7a5a00; }
		.ebset .bad { background: #fde8e6; color: #9b2a1c; }
		.ebset .muted-badge { background: #fff; color: #555; box-shadow: inset 0 0 0 1px #c9ced8; }
		.ebset .flash { border-radius: 6px; padding: 6px 10px; margin: 6px 0; }
		.ebset .flash-ok { background: #e6f4ea; border: 1px solid #9cd3ad; }
		.ebset .flash-info { background: #e8effa; border: 1px solid #a8bfe6; }
		.ebset .flash-warn { background: #fff6dd; border: 1px solid #e8cf7a; }
		.ebset .flash-error { background: #fde8e6; border: 1px solid #eba59c; }
		.ebset .switches label { display: block; margin: 3px 0; }
		.ebset .actions { margin: 10px 0 6px 0; }
		.ebset a.btn { display: inline-block; padding: 1px 8px; margin-right: 6px; border: 1px solid #114691; background: #114691; color: #fff; text-decoration: none; }
		.ebset a.btn.secondary { background: #fff; color: #114691; }
		.ebset a.btn:hover { text-decoration: underline; }
		.ebset tr.conn td { background: rgba(17,70,145,0.09); border-top: 0; }
		.ebset tr + tr.conn td { border-top: 8px solid transparent; background-clip: padding-box; }
		.ebset td.link { text-align: right; white-space: nowrap; }
		.ebset .bad-text { color: #9b2a1c; font-size: 12px; }
		.ebset-env { display: inline-block; margin-left: 8px; border-radius: 10px; padding: 0 8px; font-size: 12px; font-weight: normal; background: #fff3cd; color: #7a5a00; }
	</style>
	<div class="ebset">
	<?php
	foreach (ebTakeFlash() as $f) {
		$type = in_array($f['type'] ?? '', array('ok', 'info', 'warn', 'error'), true) ? $f['type'] : 'info';
		print "<div class='flash flash-$type'>" . ebPageH((string)($f['text'] ?? '')) . "</div>";
	}
	print "<p>" . $t(findtekst('bank.settings.intro_provider|Med bankforbindelse henter Saldi automatisk posteringerne fra virksomhedens bankkonti gennem Enable Banking. I kassekladden vælger du med knappen Bankposteringer, hvilke posteringer der lægges i kladden som ikke-bogførte linjer. Du udfylder modkonto, vedhæfter bilag og bogfører som altid. Saldi kan kun læse posteringer - der kan ikke flyttes penge.', ebLang())) . "</p>";

	if ($loadError !== '') {
		print "<div class='flash flash-error'>" . ebPageH($loadError) . "</div>";
	}
	if (!$available) {
		print "<p><span class='badge bad'>" . $t(findtekst('bank.settings.not_set_up|Ikke sat op', ebLang())) . "</span> ";
		print $t(findtekst('bank.unavailable.server|Bankforbindelse er ikke sat op på denne Saldi-server. Kontakt Saldi-support, hvis du vil bruge den.', ebLang()));
		if (!empty($config['problem'])) {
			print " (" . ebPageH($config['problem']) . ")";
		}
		print "</p>";
		print "</div></td></tr>\n";
		return;
	}

	// One form and one Gem for the company switch; users without the right see its state only.
	if ($canManage) {
		print "<form method='post' action='diverse.php?sektion=bank_integration'>" . ebCsrfField();
	}
	$disabled = $canManage ? '' : ' disabled';
	print "<div class='switches'><label><input type='checkbox' name='eb_enabled' value='1'" . ($enabled ? ' checked' : '') . "$disabled> "
		. $t(findtekst('bank.settings.toggle_plain|Aktiver bankforbindelse', ebLang())) . "</label>";
	print "</div>";
	if ($canManage) {
		print "<input type='hidden' name='eb_enabled_form' value='1'>";
		print "<p><input type='submit' value='" . $t(findtekst('bank.settings.save|Gem', ebLang())) . "'></p></form>";
	}
	if (!$canManage) {
		print "<p class='muted'>" . $t(findtekst('bank.settings.manage_only|Kun brugere med fuld adgang til Indstillinger kan ændre bankforbindelsen.', ebLang())) . "</p>";
	}

	if (!$enabled) {
		if ($connections) {
			print "<p class='muted'>" . $t(findtekst('bank.settings.disabled|Bankforbindelse er slået fra. Der hentes ikke flere posteringer, men forbindelser og data er bevaret.', ebLang())) . "</p>";
		}
		print "</div></td></tr>\n";
		return;
	}

	$feedLabel = $t(findtekst('bank.feed.title|Bankposteringer', ebLang()));
	print "<p class='actions'>";
	if ($canManage) {
		print "<a class='btn' href='" . ebH($base . 'connect.php') . "'>" . $t(findtekst('bank.settings.add_connection|Tilføj bankforbindelse', ebLang())) . "</a>";
		print "<a class='btn secondary' href='" . ebH($base . 'accounts.php') . "'>" . $t(findtekst('bank.settings.accounts|Bankkonti', ebLang())) . "</a>";
	}
	print "<a class='btn secondary' href='" . ebH($base . 'feed.php') . "'>$feedLabel</a></p>";

	if (!$connections) {
		print "<p class='muted'>" . $t(findtekst('bank.settings.no_connections|Der er ingen bankforbindelser endnu.', ebLang())) . "</p>";
		print "</div></td></tr>\n";
		return;
	}

	// One block per bank connection, one line per account, as on Bankkonti.
	print "<table><tbody>";
	foreach ($connections as $connection) {
		$cid = (int)$connection['id'];
		$status = (string)($connection['status'] ?? '');
		if ($status === 'replaced' && empty($accountsByConnection[$cid])) {
			continue;
		}
		print "<tr class='conn'><td colspan='3'><b>" . ebPageH((string)($connection['aspsp_name'] ?? '')) . "</b>";
		print ($connection['psu_type'] ?? '') === 'personal' ? " <span class='muted'>(" . $t(findtekst('bank.settings.personal|privat', ebLang())) . ")</span>" : '';
		if ($status === 'disconnected') {
			// Said once per bank, as on Bankkonti; a removed connection shows no old errors.
			print " &nbsp;<span class='muted'>" . $t(findtekst('bank.accounts.removed_line|Fjernet. Allerede hentede posteringer kan stadig bruges.', ebLang())) . "</span>";
		} else {
			$tone = $status === 'active' ? 'ok' : (in_array($status, array('expired', 'revoked', 'error'), true) ? 'bad' : 'muted-badge');
			print " <span class='badge $tone'>" . ebPageH(ebConnectionStatusLabel($status)) . "</span>";
			if ($status === 'active') {
				$consent = ebNormalizeStatus(array('valid_until' => $connection['valid_until'] ?? null), array(), $connection);
				$consentClass = ($consent['expired'] || ($consent['days_left'] !== null && $consent['days_left'] <= 14)) ? 'badge warn' : 'muted';
				print " <span class='$consentClass'>" . ebPageH(ebConsentText($consent)) . "</span>";
			}
			if (!empty($connection['last_error'])) {
				print '<br>' . ebPageH(ebErrorText(null, in_array($status, array('expired', 'revoked'), true) ? 'consent' : null));
			}
		}
		print "</td></tr>\n";
		foreach ($accountsByConnection[$cid] ?? array() as $account) {
			$aid = (int)$account['id'];
			$state = (string)($account['state'] ?? '');
			$inUse = in_array($state, array('active', 'paused'), true);
			// Ledger-first, as on the bank pages ("9800 Bank & giro · Nordea …8028"), with the holders after it.
			$label = function_exists('ebAccountLabel') ? ebAccountLabel($account, $connection)
				: (string)(($account['display_name'] ?? '') ?: ebT(findtekst('bank.feed.bank_account|Bankkonto', ebLang())));
			$holder = function_exists('ebHolderName') ? ebHolderName($account['display_name'] ?? '') : '';
			print "<tr><td>" . ebPageH($label) . ($holder !== '' ? " <span class='muted'>" . ebPageH($holder) . "</span>" : '');
			// A fetch error only while the account is in use and its connection is fine (no stale errors).
			if ($inUse && !empty($account['last_error']) && !in_array($status, array('expired', 'revoked', 'disconnected'), true)) {
				print "<br><span class='bad-text'>" . ebPageH(ebErrorText($account['last_error_code'], null)) . "</span>";
			}
			print "</td><td>";
			if ($state !== 'active') {
				$stateTone = ebAccountStateTone($state);
				$stateTone = $stateTone === 'muted' ? 'muted-badge' : $stateTone;
				print "<span class='badge $stateTone'>" . ebPageH(ebAccountStateLabel($state)) . "</span>";
			}
			print "</td><td class='link'>";
			if ($state === 'new' && $canManage) {
				print "<a href='" . ebH($base . "accounts.php?connection=$cid&account=$aid#konto-$aid") . "' title='"
					. $t(findtekst('bank.accounts.take_into_use|Vælg bogføringskonto for at tage den i brug', ebLang())) . "'>" . $t(findtekst('bank.settings.take_into_use|Tag i brug', ebLang())) . "</a>";
			} elseif ($inUse || ($state === 'disconnected' && array_sum(ebCountsByState($aid)) > 0)) {
				print "<a href='" . ebH($base . "feed.php?account=$aid") . "'>$feedLabel</a>";
			}
			print "</td></tr>\n";
		}
	}
	print "</tbody></table>";
	print "</div></td></tr>\n";
}
