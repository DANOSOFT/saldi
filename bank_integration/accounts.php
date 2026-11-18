<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/accounts.php --- ver 5.1.1 --- 2026-11-18 ---
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
// 20261006 CL/LH Initial version. Linked bank accounts: map each DKK account to a ledger account and an import start date (ebMapAccount), then start the first fetch for review.
// 20261007 CDX/LH Allowed mapping edits and released session locks before fetching bank data.
// 20261007 CDX/LH Blocked mapping forms when the tenant base currency is not DKK.
// 20261028 CDX/LH Handle connection disconnection and remove first-batch mapping approval.
// 20261028 CL/LH Bank connection panel with Afbryd per connection (also when every account is unsupported), the waiting count in the confirm and the unconfirmed-revoke warning; texts for manual picking.
// 20261116 CL/LH Texts through findtekst() (bank.accounts.*); the pull model's wording (a kassekladde, not the bank journal).
// 20261118 CL/LH Forbind igen / Forny adgang posts as the whole window (target _top): the bank login cannot be shown in Saldi's frame.

/**
 * Injected by ../includes/online.php, included below:
 * @var int|string $bruger_id
 * @var string $brugernavn
 * @var int|string $regnaar
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}
$s_id    = session_id();
$title   = 'Bankkonti';
$modulnr = 1;
$header  = 'nix';
$css     = '../css/standard.css';
ob_start();

include(__DIR__ . '/../includes/connect.php');
include(__DIR__ . '/../includes/online.php');
include(__DIR__ . '/../includes/std_func.php');
require_once __DIR__ . '/includes/ebAccess.php';

/**
 * Validates a posted Y-m-d date that must not lie in the future.
 *
 * @param mixed $value Raw POST value.
 * @return string|null The date, or null when invalid.
 */
function ebAccountsValidDate($value): ?string {
	if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
		return null;
	}
	if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1]) || $value > date('Y-m-d')) {
		return null;
	}
	return $value;
}

// ---------------------------------------------------------------------
// Controller
// ---------------------------------------------------------------------
$connectionFilter = (int)($_GET['connection'] ?? 0);
$selfUrl          = 'accounts.php' . ($connectionFilter > 0 ? '?connection=' . $connectionFilter : '');
$unavailable      = ebUnavailableReason();
$canManage        = ebCanManage();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	ebCsrfCheck();
	if ($unavailable !== null) {
		ebFlash('error', $unavailable);
		ebRedirect($selfUrl);
	}
	if (!$canManage) {
		ebFlash('error', ebT(findtekst('bank.accounts.no_right|Du har ikke adgang til at sætte bankkonti op. Det kræver adgang til Indstillinger.', ebLang())));
		ebRedirect($selfUrl);
	}
	if (($_POST['action'] ?? '') === 'disconnect_connection') {
		session_write_close();
		try {
			$client = null;
			try {
				$client = new EnableBankingClient(ebConfig());
			} catch (Throwable $e) {
				$client = null;
			}
			$disconnectId = (int)($_POST['connection'] ?? 0);
			ebDisconnect($disconnectId, (string)$brugernavn, $client);
			ebFlash('ok', ebT(findtekst('bank.accounts.disconnected|Bankforbindelsen er afbrudt. Importerede og bogførte posteringer er bevaret.', ebLang())));
			$revokeError = (string)(ebIndexById(ebListConnections())[$disconnectId]['last_error'] ?? '');
			if ($revokeError !== '') {
				ebFlash('warn', $revokeError);
			}
		} catch (Throwable $e) {
			ebFlash('error', ebErrorMessage($e));
		}
		ebRedirect($selfUrl);
	}
	$accountId  = (int)($_POST['account'] ?? 0);
	$ledger     = (int)($_POST['ledger_account'] ?? 0);
	$importFrom = ebAccountsValidDate($_POST['import_from'] ?? null);
	if (($_POST['action'] ?? '') !== 'map' || $accountId <= 0) {
		ebRedirect($selfUrl);
	}
	if ($ledger <= 0) {
		ebFlash('error', ebT(findtekst('bank.accounts.choose_ledger|Vælg den konto i kontoplanen, som bankkontoen skal bogføres på.', ebLang())));
		ebRedirect($selfUrl . ($connectionFilter > 0 ? '&' : '?') . 'account=' . $accountId . '#konto-' . $accountId);
	}
	if ($importFrom === null) {
		ebFlash('error', ebT(findtekst('bank.accounts.invalid_start|Startdatoen skal være en gyldig dato, som ikke ligger ude i fremtiden.', ebLang())));
		ebRedirect($selfUrl . ($connectionFilter > 0 ? '&' : '?') . 'account=' . $accountId . '#konto-' . $accountId);
	}
	try {
		ebMapAccount($accountId, $ledger, $importFrom, (string)$brugernavn);
	} catch (Throwable $e) {
		ebFlash('error', ebErrorMessage($e));
		ebRedirect($selfUrl . ($connectionFilter > 0 ? '&' : '?') . 'account=' . $accountId . '#konto-' . $accountId);
	}
	$accountStatus = ebAccountStatus($accountId);
	ebFlash('ok', ebT(findtekst('bank.accounts.saved|Indstillingerne er gemt. Linjer, der allerede er lagt i en kassekladde, ændres ikke.', ebLang())));
	if ($accountStatus['state'] !== 'paused') {
		// Release the session before fetching; ebFlash reopens it only to save a message.
		session_write_close();
		try {
			$run = ebSyncAccount($accountId, 'manual', ebPsuHeadersFromRequest());
			$runStatus = (string)($run['status'] ?? '');
			if ($runStatus !== 'ok') {
				$runMessage = !empty($run['error_code']) ? ebErrorText($run['error_code'], $run['error_kind'] ?? null) : ebRunStatusLabel($runStatus);
				if (!empty($run['error_code']) && !str_contains($runMessage, '(kode ')) {
					$runMessage .= ' (kode ' . strtoupper($run['error_code']) . ')';
				}
				ebFlash('warn', $runMessage);
			}
		} catch (Throwable $e) {
			ebFlash('warn', ebErrorMessage($e));
		}
	}
	ebRedirect('feed.php?account=' . $accountId);
}

$accounts      = array();
$connections   = array();
$ledgerOptions = array();
$shownConnections = array();
$loadError     = '';
if ($unavailable === null) {
	try {
		$connections = ebIndexById(ebListConnections());
		$accounts    = ebListAccounts($connectionFilter > 0 ? $connectionFilter : null);
		if ($canManage) {
			$ledgerOptions = ebLedgerAccountOptions((int)$regnaar);
		}
		foreach ($connections as $cid => $link) {
			$linkStatus = (string)($link['status'] ?? '');
			if (($connectionFilter > 0 && $cid !== $connectionFilter) || $linkStatus === 'replaced') {
				continue;
			}
			// A disconnected connection is only of interest while the bank has not confirmed the revoke.
			$unconfirmed = trim((string)($link['last_error'] ?? '')) !== ''
				&& ($link['valid_until'] === null || strtotime((string)$link['valid_until']) > time());
			if ($linkStatus === 'disconnected' && !$unconfirmed) {
				continue;
			}
			$link['waiting'] = $linkStatus === 'disconnected' ? 0 : ebWaitingCountForConnection($cid);
			$shownConnections[$cid] = $link;
		}
	} catch (Throwable $e) {
		$loadError = ebErrorMessage($e);
	}
}
$ledgerNames = array();
foreach ($ledgerOptions as $option) {
	$ledgerNames[(int)($option['kontonr'] ?? 0)] = (string)($option['beskrivelse'] ?? '');
}
$defaultImportFrom = date('Y-m-01');
$highlight         = (int)($_GET['account'] ?? 0);

// ---------------------------------------------------------------------
// View
// ---------------------------------------------------------------------
include(__DIR__ . '/includes/page_header.php');
print ebRenderTopline(ebT(findtekst('bank.accounts.title|Bankforbindelse - bankkonti', ebLang())), '../systemdata/diverse.php?sektion=bank_integration');
print "<div class='content-noside'>\n";
print ebRenderFlash();

if ($unavailable !== null) {
	print "<div class='eb-panel'><p>" . ebH($unavailable) . "</p></div>\n";
} elseif ($loadError !== '') {
	print "<div class='eb-flash eb-flash-error'>" . ebMessageHtml($loadError) . "</div>\n";
} else {
	$filterConnection = $connectionFilter > 0 ? ($connections[$connectionFilter] ?? null) : null;
	print "<div class='eb-panel'>";
	if ($filterConnection) {
		print "<h2>" . ebH(sprintf(ebT(findtekst('bank.accounts.from_bank|Konti fra %s', ebLang())), ($filterConnection['aspsp_name'] ?? '') ?: ebT(findtekst('bank.feed.the_bank|banken', ebLang())))) . "</h2>";
	} else {
		print "<h2>" . ebHT(findtekst('bank.accounts.linked|Tilknyttede bankkonti', ebLang())) . "</h2>";
	}
	print "<p>" . ebHT(findtekst('bank.accounts.intro|For hver bankkonto vælger du den konto i kontoplanen, posteringerne skal bogføres på (bankkontoen), og datoen, hentningen skal starte fra. Posteringer før startdatoen hentes ikke til en kassekladde.', ebLang())) . "</p>";
	print "<p>" . ebHT(findtekst('bank.accounts.intro_pick|Hentede posteringer vises under Bankposteringer. Du lægger dem i en kassekladde fra kassekladden med knappen Bankforbindelse.', ebLang())) . "</p>";
	print "<p class='eb-muted'>" . ebHT(findtekst('bank.accounts.tip_start|Tip: Vælg startdatoen lige efter den sidste dag, du allerede har bogført fra kontoudtog eller CSV-import, så samme postering ikke kommer med to gange.', ebLang())) . "</p>";
	print "<div class='eb-actions'>";
	if ($filterConnection) {
		print "<a class='eb-btn secondary' href='accounts.php'>" . ebHT(findtekst('bank.accounts.show_all|Vis alle konti', ebLang())) . "</a>";
	}
	if ($canManage) {
		print "<a class='eb-btn secondary' href='connect.php'>" . ebHT(findtekst('bank.settings.add_connection|Tilføj bankforbindelse', ebLang())) . "</a>";
	}
	print "</div></div>\n";

	if ($shownConnections) {
		print "<div class='eb-panel'><h2>" . ebHT(findtekst('bank.accounts.connections|Bankforbindelser', ebLang())) . "</h2>";
		print "<table class='eb-table'><thead><tr><th>" . ebHT(findtekst('bank.feed.bank|Bank', ebLang())) . "</th><th>" . ebHT(findtekst('bank.accounts.col_status|Status', ebLang())) . "</th><th>" . ebHT(findtekst('bank.accounts.col_access_until|Adgang til', ebLang())) . "</th>" . ($canManage ? "<th></th>" : '') . "</tr></thead><tbody>";
		foreach ($shownConnections as $cid => $link) {
			$linkStatus = (string)($link['status'] ?? '');
			$linkName = (string)(($link['aspsp_name'] ?? '') ?: ebT(findtekst('bank.feed.the_bank|banken', ebLang())));
			$tone = $linkStatus === 'active' ? 'ok' : (in_array($linkStatus, array('expired', 'revoked', 'error'), true) ? 'bad' : 'muted');
			print "<tr><td>" . ebH($linkName) . "</td>";
			print "<td><span class='eb-badge $tone'>" . ebH(ebConnectionStatusLabel($linkStatus)) . "</span>";
			if ($linkStatus === 'disconnected' && trim((string)($link['last_error'] ?? '')) !== '') {
				// Written by ebDisconnect() only: the bank did not confirm that access was withdrawn.
				print "<span class='eb-hint'>" . ebH($link['last_error']) . "</span>";
			}
			print "</td><td class='nowrap'>" . ebH(ebFormatDate($link['valid_until'] ?? '')) . "</td>";
			if ($canManage) {
				print "<td class='nowrap'>";
				if ($linkStatus !== 'disconnected') {
					$linkWaiting = (int)$link['waiting'];
					$confirm = sprintf(ebT(findtekst('bank.feed.disconnect_confirm|Afbryd forbindelsen til %s? Alle konti fra denne bankforbindelse stopper med at hente posteringer. Importerede og bogførte posteringer bevares.', ebLang())), $linkName);
					if ($linkWaiting > 0) {
						$confirm .= "\n\n" . sprintf(ebT(findtekst('bank.feed.disconnect_waiting|%s venter stadig på at blive lagt i en kassekladde. Du kan fortsat vælge dem her, efter forbindelsen er afbrudt.', ebLang())),
							$linkWaiting === 1 ? ebT(findtekst('bank.feed.count_one|1 postering', ebLang())) : sprintf(ebT(findtekst('bank.feed.count_many|%s posteringer', ebLang())), $linkWaiting));
					}
					print ebPostButton($selfUrl, array('action' => 'disconnect_connection', 'connection' => $cid), ebT(findtekst('bank.feed.disconnect|Afbryd', ebLang())), 'small danger', $confirm);
				}
				print "</td>";
			}
			print "</tr>\n";
		}
		print "</tbody></table></div>\n";
	}

	if (!$accounts) {
		print "<div class='eb-panel'><p>" . ebHT(findtekst('bank.accounts.none|Der er ingen bankkonti endnu.', ebLang()));
		if ($canManage) {
			print " <a href='connect.php'>" . ebHT(findtekst('bank.feed.connect_bank|Forbind en bank', ebLang())) . "</a>";
		}
		print "</p></div>\n";
	}

	foreach ($accounts as $account) {
		$id             = (int)$account['id'];
		$state          = (string)($account['state'] ?? '');
		$currency       = (string)($account['currency'] ?? '');
		$bankConnection = $connections[(int)($account['connection_id'] ?? 0)] ?? array();
		$ledger         = (int)($account['ledger_account'] ?? 0);
		$panelStyle     = $id === $highlight ? " style='border-color:#114691'" : '';

		print "<div class='eb-panel' id='konto-$id'$panelStyle>";
		print "<h2>" . ebH(($account['display_name'] ?? '') ?: ebT(findtekst('bank.feed.bank_account|Bankkonto', ebLang()))) . " <span class='eb-badge " . ebH(ebAccountStateTone($state)) . "'>" . ebH(ebAccountStateLabel($state)) . "</span></h2>";
		print "<dl class='eb-grid'>";
		print "<dt>" . ebHT(findtekst('bank.accounts.account|Konto', ebLang())) . "</dt><dd>" . ebH($account['display_identity'] ?? '') . "</dd>";
		print "<dt>" . ebHT(findtekst('bank.feed.bank|Bank', ebLang())) . "</dt><dd>" . ebH($bankConnection['aspsp_name'] ?? '') . "</dd>";
		print "<dt>" . ebHT(findtekst('bank.accounts.currency|Valuta', ebLang())) . "</dt><dd>" . ebH($currency);
		if ($currency !== '' && $currency !== 'DKK') {
			print " <span class='eb-badge muted'>" . ebH(ebAccountStateLabel('unsupported')) . "</span>";
		}
		print "</dd>";
		if ($ledger > 0) {
			print "<dt>" . ebHT(findtekst('bank.feed.ledger|Bogføres på', ebLang())) . "</dt><dd>" . $ledger . (isset($ledgerNames[$ledger]) && $ledgerNames[$ledger] !== '' ? ' - ' . ebH($ledgerNames[$ledger]) : '') . "</dd>";
		}
		if (!empty($account['import_from'])) {
			print "<dt>" . ebHT(findtekst('bank.accounts.start_date|Startdato', ebLang())) . "</dt><dd>" . ebH(ebFormatDate($account['import_from'])) . "</dd>";
		}
		print "</dl>";
		if (!empty($account['last_error'])) {
			print "<p>" . ebErrorHtml($account['last_error_code']) . "</p>";
		}

		if (ebBaseCurrency() !== 'DKK') {
			print "<p class='eb-muted'>" . ebHT(findtekst('bank.accounts.base_currency|Bankimport kræver, at regnskabets basisvaluta er DKK.', ebLang())) . "</p>";
		} elseif ($state === 'unsupported' || ($currency !== '' && $currency !== 'DKK')) {
			print "<p class='eb-muted'>" . ebHT(findtekst('bank.accounts.unsupported|Saldi kan kun importere konti i danske kroner. Kontoen er forbundet, men dens posteringer kan ikke lægges i en kassekladde.', ebLang())) . "</p>";
		} elseif (in_array($state, array('new', 'active', 'paused'), true)) {
			if (!$canManage) {
				print "<p class='eb-muted'>" . ebHT(findtekst('bank.accounts.ask_settings|Opsætningen kan ændres af en bruger med adgang til Indstillinger.', ebLang())) . "</p>";
			} elseif (!$ledgerOptions) {
				print "<p class='eb-muted'>" . ebHT(findtekst('bank.accounts.no_ledgers|Der er ingen drifts- eller statuskonti i kontoplanen for det aktuelle regnskabsår at vælge imellem.', ebLang())) . "</p>";
			} else {
				print "<form method='post' action='" . ebH($selfUrl) . "'>" . ebCsrfField();
				print "<input type='hidden' name='action' value='map'>";
				print "<input type='hidden' name='account' value='$id'>";
				print "<div class='eb-form-row' style='margin-top:8px'>";
				print "<label>" . ebHT(findtekst('bank.accounts.search_ledger|Søg i kontoplanen', ebLang())) . "<input type='text' class='eb-ledger-search' data-target='ledger-$id' autocomplete='off' placeholder='" . ebHT(findtekst('bank.accounts.search_placeholder|Nummer eller navn', ebLang())) . "'></label>";
				print "<label>" . ebHT(findtekst('bank.accounts.ledger_label|Bogføres på konto', ebLang())) . "<select name='ledger_account' id='ledger-$id' required>";
				print "<option value=''>" . ebHT(findtekst('bank.accounts.choose_ledger_option|Vælg konto', ebLang())) . "</option>";
				foreach ($ledgerOptions as $option) {
					$kontonr = (int)($option['kontonr'] ?? 0);
					if ($kontonr <= 0) {
						continue;
					}
					$selected = $kontonr === $ledger ? ' selected' : '';
					print "<option value='$kontonr'$selected>" . $kontonr . ' - ' . ebH($option['beskrivelse'] ?? '') . "</option>";
				}
				print "</select></label>";
				print "<label>" . ebHT(findtekst('bank.accounts.fetch_from|Hent posteringer fra', ebLang())) . "<input type='date' name='import_from' value='" . ebH($account['import_from'] ?? $defaultImportFrom) . "' max='" . ebH(date('Y-m-d')) . "' required></label>";
				print "<button type='submit' class='eb-btn'>" . ($state === 'new' ? ebHT(findtekst('bank.accounts.save_first|Gem og hent første posteringer', ebLang())) : ebHT(findtekst('bank.accounts.save|Gem ændringer', ebLang()))) . "</button>";
				print "</div></form><p class='eb-muted'>" . ebHT(findtekst('bank.accounts.changes_note|Ændringer gælder posteringer, der lægges i en kassekladde fremover. Linjer, der allerede er lagt i en kassekladde, ændres ikke.', ebLang())) . "</p>";
			}
			if ($state !== 'new') {
				print "<div class='eb-actions'><a class='eb-btn' href='feed.php?account=$id'>" . ebHT(findtekst('bank.feed.title|Bankposteringer', ebLang())) . "</a></div>";
			}
		} elseif ($state === 'disconnected') {
			print "<p class='eb-muted'>" . ebHT(findtekst('bank.accounts.disconnected_note|Forbindelsen er afbrudt. Bogførte og importerede posteringer er bevaret. Hentede posteringer, der ikke er lagt i en kassekladde, kan stadig bruges.', ebLang())) . "</p>";
			print "<div class='eb-actions'><a class='eb-btn secondary' href='feed.php?account=$id'>" . ebHT(findtekst('bank.feed.title|Bankposteringer', ebLang())) . "</a>";
			if ($canManage && !empty($bankConnection)) {
				print ebPostButton('connect.php', array('purpose' => 'reconnect', 'connection' => (int)$bankConnection['id']), ebT(findtekst('bank.feed.reconnect|Forbind igen', ebLang())), 'secondary', '', '_top');
			}
			print "</div>";
		} else {
			print "<div class='eb-actions'><a class='eb-btn' href='feed.php?account=$id'>" . ebHT(findtekst('bank.feed.title|Bankposteringer', ebLang())) . "</a></div>";
		}
		print "</div>\n";
	}
	?>
<script>
(function () {
	document.querySelectorAll('.eb-ledger-search').forEach(function (input) {
		var select = document.getElementById(input.getAttribute('data-target'));
		if (!select) {
			return;
		}
		input.addEventListener('input', function () {
			var q = input.value.trim().toLowerCase();
			var firstMatch = null;
			Array.prototype.forEach.call(select.options, function (opt) {
				if (opt.value === '') {
					return;
				}
				var hit = q === '' || opt.text.toLowerCase().indexOf(q) !== -1;
				opt.hidden = !hit;
				if (hit && firstMatch === null) {
					firstMatch = opt;
				}
			});
			if (q !== '' && firstMatch && (select.selectedIndex <= 0 || select.options[select.selectedIndex].hidden)) {
				firstMatch.selected = true;
			}
		});
	});
})();
</script>
	<?php
}
print "</div>\n</body>\n</html>\n";
