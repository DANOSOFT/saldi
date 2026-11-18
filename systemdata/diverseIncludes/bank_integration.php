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
// 20261118 CL/LH Rewritten for Enable Banking: company toggle, server configuration status, connections and accounts with links to connect/accounts/feed. The Aiia date method is gone. bankIntegrationSave() handles the POST (CSRF and the settings.finance right, then redirect) before diverse.php prints anything. Texts through findtekst() (bank.settings.*), printed in the page's charset (ebPageH()).
// 20261118 CL/LH Live port: each user switches the bank status icon in the journal on or off here (settings bank_integration/show_status), as live has no personal settings page.

require_once __DIR__ . '/../../bank_integration/includes/ebAccess.php';

/**
 * Saves the company toggle and redirects back (Post/Redirect/Get). Called by diverse.php before output.
 *
 * @return never
 */
function bankIntegrationSave(): never {
	global $brugernavn;
	$self = 'diverse.php?sektion=bank_integration';
	ebCsrfCheck();
	if (isset($_POST['eb_show_status_submit'])) {
		// Personal choice of the logged-in user (live has no Personlige indstillinger page); read by ebStatusIconWanted().
		$userId = (int)($GLOBALS['bruger_id'] ?? 0);
		if ($userId > 0) {
			$value = empty($_POST['eb_show_status']) ? '0' : '1';
			$row = ebRow("SELECT id FROM settings WHERE var_grp='bank_integration' AND var_name='show_status' AND user_id=" . $userId);
			if ($row) {
				ebUpdate('settings', (int)$row['id'], array('var_value' => $value));
			} else {
				ebInsert('settings', array('var_grp' => 'bank_integration', 'var_name' => 'show_status', 'var_value' => $value,
					'var_description' => 'Vis bankstatus i kassekladden', 'user_id' => $userId));
			}
		}
		ebRedirect($self);
	}
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
		ebSetCompanyEnabled($on, (string)$brugernavn);
		ebFlash('ok', $on
			? ebT(findtekst('bank.settings.enabled|Bankforbindelse er slået til. Forbind en bank for at komme i gang.', ebLang()))
			: ebT(findtekst('bank.settings.disabled|Bankforbindelse er slået fra. Der hentes ikke flere posteringer, men forbindelser og data er bevaret.', ebLang())));
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
	?>
	<tr><td colspan="6"><hr></td></tr>
	<tr><td colspan="6"><b><?= $t(findtekst('bank.settings.heading|Bankforbindelse (Enable Banking)', ebLang())) ?></b></td></tr>
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
		.ebset .muted-badge { background: #eceef2; color: #555; }
		.ebset .flash { border-radius: 6px; padding: 6px 10px; margin: 6px 0; }
		.ebset .flash-ok { background: #e6f4ea; border: 1px solid #9cd3ad; }
		.ebset .flash-info { background: #e8effa; border: 1px solid #a8bfe6; }
		.ebset .flash-warn { background: #fff6dd; border: 1px solid #e8cf7a; }
		.ebset .flash-error { background: #fde8e6; border: 1px solid #eba59c; }
	</style>
	<div class="ebset">
	<?php
	foreach (ebTakeFlash() as $f) {
		$type = in_array($f['type'] ?? '', array('ok', 'info', 'warn', 'error'), true) ? $f['type'] : 'info';
		print "<div class='flash flash-$type'>" . ebPageH((string)($f['text'] ?? '')) . "</div>";
	}
	print "<p>" . $t(findtekst('bank.settings.intro|Med bankforbindelse henter Saldi automatisk posteringerne fra virksomhedens bankkonti. I kassekladden vælger du med knappen Bankforbindelse, hvilke posteringer der lægges i kladden som ikke-bogførte linjer. Du udfylder modkonto, vedhæfter bilag og bogfører som altid. Saldi kan kun læse posteringer - der kan ikke flyttes penge.', ebLang())) . "</p>";

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
	$environment = (string)($config['environment'] ?? '');
	print "<p>" . $t(findtekst('bank.settings.server|Server:', ebLang())) . " <span class='badge ok'>" . $t(findtekst('bank.settings.ready|Klar', ebLang())) . "</span> ";
	print $environment === 'SANDBOX' ? "<span class='badge warn'>" . $t(findtekst('bank.settings.sandbox|Testmiljø (sandbox)', ebLang())) . "</span>"
		: "<span class='badge muted-badge'>" . $t(findtekst('bank.settings.production|Produktion', ebLang())) . "</span>";
	print "</p>";

	print "<form method='post' action='diverse.php?sektion=bank_integration'>" . ebCsrfField();
	$disabled = $canManage ? '' : ' disabled';
	print "<p><label><input type='checkbox' name='eb_enabled' value='1'" . ($enabled ? ' checked' : '') . "$disabled> " . $t(findtekst('bank.settings.toggle|Aktiver bankforbindelse (Enable Banking)', ebLang())) . "</label> ";
	if ($canManage) {
		print "<input type='submit' value='" . $t(findtekst('bank.settings.save|Gem', ebLang())) . "'>";
	}
	print "</p></form>";
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

	// The same per-user row ebStatusIconWanted() (bank_integration/includes/auth_check_icon.php) reads.
	$showStatus = ebRow("SELECT id FROM settings WHERE var_grp='bank_integration' AND var_name='show_status' AND user_id="
		. (int)($GLOBALS['bruger_id'] ?? 0) . " AND var_value='1'") !== null;
	print "<form method='post' action='diverse.php?sektion=bank_integration'>" . ebCsrfField();
	print "<p><label><input type='checkbox' name='eb_show_status' value='1'" . ($showStatus ? ' checked' : '') . "> "
		. $t(findtekst('bank.settings.show_status|Vis bankstatus i kassekladden (kun for dig)', ebLang())) . "</label> ";
	print "<input type='submit' name='eb_show_status_submit' value='" . $t(findtekst('bank.settings.save|Gem', ebLang())) . "'></p></form>";

	$feedLabel = $t(findtekst('bank.feed.title|Bankposteringer', ebLang()));
	print "<p>";
	if ($canManage) {
		print "<a href='" . ebH($base . 'connect.php') . "'>" . $t(findtekst('bank.settings.add_connection|Tilføj bankforbindelse', ebLang())) . "</a> &nbsp;&middot;&nbsp; ";
		print "<a href='" . ebH($base . 'accounts.php') . "'>" . $t(findtekst('bank.settings.accounts|Bankkonti', ebLang())) . "</a> &nbsp;&middot;&nbsp; ";
	}
	print "<a href='" . ebH($base . 'feed.php') . "'>$feedLabel</a></p>";

	if (!$connections) {
		print "<p class='muted'>" . $t(findtekst('bank.settings.no_connections|Der er ingen bankforbindelser endnu.', ebLang())) . "</p>";
		print "</div></td></tr>\n";
		return;
	}

	print "<table><thead><tr><th>" . $t(findtekst('bank.feed.bank|Bank', ebLang())) . "</th><th>" . $t(findtekst('bank.settings.col_connection|Forbindelse', ebLang())) . "</th><th>"
		. $t(findtekst('bank.settings.col_accounts|Konti', ebLang())) . "</th></tr></thead><tbody>";
	foreach ($connections as $connection) {
		$cid = (int)$connection['id'];
		$status = (string)($connection['status'] ?? '');
		if ($status === 'replaced' && empty($accountsByConnection[$cid])) {
			continue;
		}
		$tone = $status === 'active' ? 'ok' : (in_array($status, array('expired', 'revoked', 'error'), true) ? 'bad' : 'muted-badge');
		print "<tr><td>" . ebPageH((string)($connection['aspsp_name'] ?? ''));
		print ($connection['psu_type'] ?? '') === 'personal' ? " <span class='muted'>(" . $t(findtekst('bank.settings.personal|privat', ebLang())) . ")</span>" : '';
		print "</td><td><span class='badge $tone'>" . ebPageH(ebConnectionStatusLabel($status)) . "</span>";
		if ($status === 'active') {
			$consent = ebNormalizeStatus(array('valid_until' => $connection['valid_until'] ?? null), array(), $connection);
			$consentClass = ($consent['expired'] || ($consent['days_left'] !== null && $consent['days_left'] <= 14)) ? 'badge warn' : 'muted';
			print "<br><span class='$consentClass'>" . ebPageH(ebConsentText($consent)) . "</span>";
		}
		if (!empty($connection['last_error'])) {
			print '<br>' . ebPageH(ebErrorText(null, in_array($status, array('expired', 'revoked'), true) ? 'consent' : null));
		}
		print "</td><td>";
		foreach ($accountsByConnection[$cid] ?? array() as $account) {
			$aid = (int)$account['id'];
			$state = (string)($account['state'] ?? '');
			$stateTone = ebAccountStateTone($state);
			$stateTone = $stateTone === 'muted' ? 'muted-badge' : $stateTone;
			print ebPageH((string)(($account['display_name'] ?? '') ?: ebT(findtekst('bank.feed.bank_account|Bankkonto', ebLang())))) . " <span class='muted'>" . ebPageH((string)($account['display_identity'] ?? '')) . "</span> ";
			print "<span class='badge $stateTone'>" . ebPageH(ebAccountStateLabel($state)) . "</span> ";
			if ($state === 'new' && $canManage) {
				print "<a href='" . ebH($base . "accounts.php?connection=$cid&account=$aid#konto-$aid") . "'>" . $t(findtekst('bank.settings.set_up|Sæt op', ebLang())) . "</a>";
			} elseif ($state !== 'unsupported') {
				print "<a href='" . ebH($base . "feed.php?account=$aid") . "'>$feedLabel</a>";
			}
			if (!empty($account['last_error'])) {
				print '<br>' . ebPageH(ebErrorText($account['last_error_code'], null));
			}
			print "<br>";
		}
		print "</td></tr>\n";
	}
	print "</tbody></table>";
	print "</div></td></tr>\n";
}
