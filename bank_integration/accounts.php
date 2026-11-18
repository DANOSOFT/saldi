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
// 20261118 CL/LH UI pass: one block per bank connection (status, access expiry, Forny adgang, Fjern bankforbindelse) with one line per account (ledger-first name, Sæt på pause / Genoptag, Bankposteringer, the settings behind Ret).
//                Unmapped accounts read "Ikke i brug", a removed connection says so once, a removed account never shows an old fetch error; the start date is typed as dd-mm-åååå.

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
 * Validates a posted start date (dd-mm-åååå and the other forms ebParseDate() reads) that must not lie in the future.
 *
 * @param mixed $value Raw POST value.
 * @return string|null The date as Y-m-d, or null when invalid.
 */
function ebAccountsValidDate($value): ?string {
	$date = is_string($value) ? ebParseDate($value) : null;
	return $date !== null && $date <= date('Y-m-d') ? $date : null;
}

/**
 * Text of the confirm() before a bank connection is removed, with the number of lines still waiting to be picked.
 *
 * @param string $bankName Bank name (UTF-8).
 * @param int $waiting Unlinked pending and review lines on the connection's accounts.
 * @return string Plain UTF-8 text.
 */
function ebAccountsRemoveConfirm(string $bankName, int $waiting): string {
	$confirm = sprintf(ebT(findtekst('bank.accounts.remove_confirm|Vil du fjerne forbindelsen til %s? Der hentes ikke flere posteringer. Allerede hentede og bogførte posteringer bevares.', ebLang())), $bankName);
	if ($waiting > 0) {
		$count = $waiting === 1 ? ebT(findtekst('bank.feed.count_one|1 postering', ebLang())) : sprintf(ebT(findtekst('bank.feed.count_many|%s posteringer', ebLang())), $waiting);
		$confirm .= "\n\n" . sprintf(ebT(findtekst('bank.accounts.remove_waiting|%s venter stadig på at blive lagt i en kassekladde. Du kan stadig vælge dem under Bankposteringer, når forbindelsen er fjernet.', ebLang())), $count);
	}
	return $confirm;
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
			$removedBank = (string)((ebIndexById(ebListConnections())[$disconnectId]['aspsp_name'] ?? '') ?: ebT(findtekst('bank.feed.the_bank|banken', ebLang())));
			ebDisconnect($disconnectId, (string)$brugernavn, $client);
			ebFlash('ok', sprintf(ebT(findtekst('bank.accounts.removed|Forbindelsen til %s er fjernet. Allerede hentede og bogførte posteringer er bevaret.', ebLang())), $removedBank));
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
	$backToAccount = $selfUrl . ($connectionFilter > 0 ? '&' : '?') . 'account=' . $accountId . '#konto-' . $accountId;
	if (in_array($_POST['action'] ?? '', array('pause', 'resume'), true) && $accountId > 0) {
		// Moved here from the feed page, same function and right (Indstillinger).
		$pause = $_POST['action'] === 'pause';
		try {
			$scheduled = ebSetPaused($accountId, $pause, (string)($brugernavn_raw ?? $brugernavn));
			ebFlash('ok', $pause ? ebT(findtekst('bank.feed.paused|Hentningen er sat på pause.', ebLang()))
				: ($scheduled ? ebT(findtekst('bank.feed.resumed|Hentningen er genoptaget. Saldi henter fra banken ved næste kørsel.', ebLang()))
					: ebT(findtekst('bank.accounts.resumed_blocked|Hentningen er genoptaget, men Saldi kan ikke hente fra banken, før adgangen er fornyet eller fejlen er løst.', ebLang()))));
		} catch (Throwable $e) {
			ebFlash('error', ebErrorMessage($e));
		}
		ebRedirect($backToAccount);
	}
	if (($_POST['action'] ?? '') !== 'map' || $accountId <= 0) {
		ebRedirect($selfUrl);
	}
	if ($ledger <= 0) {
		ebFlash('error', ebT(findtekst('bank.accounts.choose_ledger|Vælg den konto i kontoplanen, som bankkontoen skal bogføres på.', ebLang())));
		ebRedirect($backToAccount);
	}
	if ($importFrom === null) {
		ebFlash('error', ebT(findtekst('bank.accounts.invalid_start_date|Startdatoen skal være en gyldig dato, skrevet som dd-mm-åååå, og den må ikke ligge i fremtiden. Intet er gemt.', ebLang())));
		ebRedirect($backToAccount);
	}
	try {
		ebMapAccount($accountId, $ledger, $importFrom, (string)$brugernavn);
	} catch (Throwable $e) {
		ebFlash('error', ebErrorMessage($e));
		ebRedirect($backToAccount);
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
$blocks        = array();
$loadError     = '';
if ($unavailable === null) {
	try {
		$connections = ebIndexById(ebListConnections());
		$accounts    = ebListAccounts($connectionFilter > 0 ? $connectionFilter : null);
		if ($canManage) {
			$ledgerOptions = ebLedgerAccountOptions((int)$regnaar);
		}
		// One block per bank connection with its accounts; active connections first.
		$byConnection = array();
		foreach ($accounts as $account) {
			$account['line_count'] = ($account['state'] ?? '') === 'disconnected' ? array_sum(ebCountsByState((int)$account['id'])) : 0;
			$byConnection[(int)($account['connection_id'] ?? 0)][] = $account;
		}
		foreach ($connections as $cid => $link) {
			$linkStatus = (string)($link['status'] ?? '');
			if ($connectionFilter > 0 && $cid !== $connectionFilter) {
				continue;
			}
			// A removed connection is of interest while it has accounts or the bank has not confirmed the revoke.
			$unconfirmed = $linkStatus === 'disconnected' && trim((string)($link['last_error'] ?? '')) !== ''
				&& ($link['valid_until'] === null || strtotime((string)$link['valid_until']) > time());
			if (empty($byConnection[$cid]) && ($linkStatus === 'replaced' || ($linkStatus === 'disconnected' && !$unconfirmed))) {
				continue;
			}
			$link['unconfirmed'] = $unconfirmed;
			$link['waiting'] = in_array($linkStatus, array('disconnected', 'replaced'), true) ? 0 : ebWaitingCountForConnection($cid);
			$blocks[] = array('connection' => $link, 'accounts' => $byConnection[$cid] ?? array());
			unset($byConnection[$cid]);
		}
		foreach ($byConnection as $orphans) {
			$blocks[] = array('connection' => array(), 'accounts' => $orphans);
		}
		usort($blocks, fn($a, $b) => (int)(($b['connection']['status'] ?? '') === 'active') <=> (int)(($a['connection']['status'] ?? '') === 'active'));
	} catch (Throwable $e) {
		$loadError = ebErrorMessage($e);
	}
}
$defaultImportFrom = date('Y-m-01');
$highlight         = (int)($_GET['account'] ?? 0);
$canMap            = $canManage && $ledgerOptions && ebBaseCurrency() === 'DKK';

/**
 * The settings form of one account (ledger account and start date), shown behind Ret or "Vælg bogføringskonto".
 *
 * @param array<string, mixed> $account bank_accounts row.
 * @param array<int, array{kontonr:string, beskrivelse:string}> $ledgerOptions Eligible chart accounts.
 * @param string $selfUrl Form action.
 * @param string $defaultImportFrom Y-m-d start date for an account without one.
 * @return string HTML.
 */
function ebAccountsMapForm(array $account, array $ledgerOptions, string $selfUrl, string $defaultImportFrom): string {
	$id = (int)$account['id'];
	$ledger = (int)($account['ledger_account'] ?? 0);
	$html = "<form method='post' action='" . ebH($selfUrl) . "'>" . ebCsrfField();
	$html .= "<input type='hidden' name='action' value='map'><input type='hidden' name='account' value='$id'>";
	$html .= "<div class='eb-form-row'>";
	$html .= "<label>" . ebHT(findtekst('bank.accounts.search_ledger|Søg i kontoplanen', ebLang())) . "<input type='text' class='eb-ledger-search' data-target='ledger-$id' autocomplete='off' placeholder='" . ebHT(findtekst('bank.accounts.search_placeholder|Nummer eller navn', ebLang())) . "'></label>";
	$html .= "<label>" . ebHT(findtekst('bank.accounts.ledger_label|Bogføres på konto', ebLang())) . "<select name='ledger_account' id='ledger-$id' required>";
	$html .= "<option value=''>" . ebHT(findtekst('bank.accounts.choose_ledger_option|Vælg konto', ebLang())) . "</option>";
	foreach ($ledgerOptions as $option) {
		$kontonr = (int)($option['kontonr'] ?? 0);
		if ($kontonr <= 0) {
			continue;
		}
		$html .= "<option value='$kontonr'" . ($kontonr === $ledger ? ' selected' : '') . ">" . $kontonr . ' - ' . ebH($option['beskrivelse'] ?? '') . "</option>";
	}
	$html .= "</select></label>";
	$html .= "<label>" . ebHT(findtekst('bank.accounts.fetch_from|Hent posteringer fra', ebLang())) . "<input type='text' name='import_from' value='" . ebH(ebFormatDate((string)($account['import_from'] ?? $defaultImportFrom))) . "'"
		. " size='10' maxlength='10' autocomplete='off' placeholder='" . ebHT(findtekst('bank.feed.date_placeholder|dd-mm-åååå', ebLang())) . "' required></label>";
	$html .= "<button type='submit' class='eb-btn'>" . (($account['state'] ?? '') === 'new' ? ebHT(findtekst('bank.accounts.save_first|Gem og hent første posteringer', ebLang())) : ebHT(findtekst('bank.accounts.save|Gem ændringer', ebLang()))) . "</button>";
	$html .= "</div></form>";
	$html .= "<p class='eb-muted'>" . ebHT(findtekst('bank.accounts.tip_start|Tip: Vælg startdatoen lige efter den sidste dag, du allerede har bogført fra kontoudtog eller CSV-import, så samme postering ikke kommer med to gange.', ebLang())) . "</p>";
	if (($account['state'] ?? '') !== 'new') {
		$html .= "<p class='eb-muted'>" . ebHT(findtekst('bank.accounts.changes_note|Ændringer gælder posteringer, der lægges i en kassekladde fremover. Linjer, der allerede er lagt i en kassekladde, ændres ikke.', ebLang())) . "</p>";
	}
	return $html;
}

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
	print ebNoticeHtml($loadError, 'eb-flash eb-flash-error') . "\n";
} else {
	$filterConnection = $connectionFilter > 0 ? ($connections[$connectionFilter] ?? null) : null;
	print "<div class='eb-panel'>";
	if ($filterConnection) {
		print "<h2>" . ebH(sprintf(ebT(findtekst('bank.accounts.from_bank|Konti fra %s', ebLang())), ($filterConnection['aspsp_name'] ?? '') ?: ebT(findtekst('bank.feed.the_bank|banken', ebLang())))) . "</h2>";
	} else {
		print "<h2>" . ebHT(findtekst('bank.accounts.linked|Tilknyttede bankkonti', ebLang())) . "</h2>";
	}
	print "<p>" . ebHT(findtekst('bank.accounts.intro_short|Vælg for hver bankkonto, hvilken konto i kontoplanen posteringerne bogføres på, og fra hvilken dato de hentes.', ebLang())) . "</p>";
	print "<p class='eb-muted'>" . ebHT(findtekst('bank.accounts.intro_pick_postings|Du lægger posteringerne i en kassekladde med knappen Bankposteringer i kassekladden.', ebLang())) . "</p>";
	if (ebBaseCurrency() !== 'DKK') {
		print "<div class='eb-flash eb-flash-warn'>" . ebHT(findtekst('bank.accounts.base_currency|Bankimport kræver, at regnskabets basisvaluta er DKK.', ebLang())) . "</div>";
	} elseif (!$canManage) {
		print "<p class='eb-muted'>" . ebHT(findtekst('bank.accounts.ask_settings|Opsætningen kan ændres af en bruger med adgang til Indstillinger.', ebLang())) . "</p>";
	} elseif (!$ledgerOptions) {
		print "<p class='eb-muted'>" . ebHT(findtekst('bank.accounts.no_ledgers|Der er ingen drifts- eller statuskonti i kontoplanen for det aktuelle regnskabsår at vælge imellem.', ebLang())) . "</p>";
	}
	print "<div class='eb-actions'>";
	if ($filterConnection) {
		print "<a class='eb-btn secondary' href='accounts.php'>" . ebHT(findtekst('bank.accounts.show_all|Vis alle konti', ebLang())) . "</a>";
	}
	if ($canManage) {
		print "<a class='eb-btn secondary' href='connect.php'>" . ebHT(findtekst('bank.settings.add_connection|Tilføj bankforbindelse', ebLang())) . "</a>";
	}
	print "</div></div>\n";

	if (!$accounts) {
		print "<div class='eb-panel'><p>" . ebHT(findtekst('bank.accounts.none|Der er ingen bankkonti endnu.', ebLang()));
		if ($canManage) {
			print " <a href='connect.php'>" . ebHT(findtekst('bank.feed.connect_bank|Forbind en bank', ebLang())) . "</a>";
		}
		print "</p></div>\n";
	}

	foreach ($blocks as $block) {
		$link       = $block['connection'];
		$cid        = (int)($link['id'] ?? 0);
		$linkStatus = (string)($link['status'] ?? '');
		$linkName   = (string)(($link['aspsp_name'] ?? '') ?: ebT(findtekst('bank.feed.the_bank|banken', ebLang())));
		$removed    = $linkStatus === 'disconnected';
		$consent    = ebNormalizeStatus(array('valid_until' => (string)($link['valid_until'] ?? ''), 'connection_status' => $linkStatus));
		$soon       = $consent['expired'] || ($consent['days_left'] !== null && $consent['days_left'] <= 30);

		// Connection: bank, status, access expiry, Forny adgang and Fjern bankforbindelse.
		print "<div class='eb-panel'" . ($cid > 0 ? " id='forbindelse-$cid'" : '') . "><div class='eb-conn-head'><h2>";
		if ($cid > 0) {
			$psu = (string)($link['psu_type'] ?? '');
			print ebH($linkName);
			if ($psu !== '') {
				print " <span class='eb-summary-bank' style='font-weight:400'>· " . ($psu === 'business' ? ebHT(findtekst('bank.connect.business|Erhverv', ebLang())) : ebHT(findtekst('bank.connect.personal|Privat', ebLang()))) . "</span>";
			}
			if (!$removed) {
				$tone = $linkStatus === 'active' ? 'ok' : (in_array($linkStatus, array('expired', 'revoked', 'error'), true) ? 'bad' : 'muted');
				print " <span class='eb-badge $tone'>" . ebH(ebConnectionStatusLabel($linkStatus)) . "</span>";
			}
		} else {
			print ebHT(findtekst('bank.accounts.other_accounts|Andre bankkonti', ebLang()));
		}
		print "</h2><div class='eb-conn-meta'>";
		if ($removed) {
			print "<span class='eb-muted'>" . ebHT(findtekst('bank.accounts.removed_line|Fjernet. Allerede hentede posteringer kan stadig bruges.', ebLang())) . "</span>";
			if ($canManage) {
				print ebPostButton('connect.php', array('purpose' => 'reconnect', 'connection' => $cid), ebT(findtekst('bank.feed.reconnect|Forbind igen', ebLang())), 'secondary', '', '_top');
			}
		} elseif ($cid > 0 && $linkStatus !== 'replaced') {
			if (($link['valid_until'] ?? null) !== null) {
				print $soon ? "<span class='eb-badge " . ($consent['expired'] ? 'bad' : 'warn') . "'>" . ebH(ebConsentText($consent)) . "</span>"
					: "<span class='eb-muted'>" . ebH(ebConsentText($consent)) . "</span>";
			}
			if ($canManage) {
				print ebPostButton('connect.php', array('purpose' => 'reconnect', 'connection' => $cid), ebT(findtekst('bank.feed.renew|Forny adgang', ebLang())), $soon || $linkStatus !== 'active' ? '' : 'secondary', '', '_top');
				print ebPostButton($selfUrl, array('action' => 'disconnect_connection', 'connection' => $cid), ebT(findtekst('bank.accounts.remove|Fjern bankforbindelse', ebLang())), 'danger',
					ebAccountsRemoveConfirm($linkName, (int)$link['waiting']));
			}
		}
		print "</div></div>";
		if (!empty($link['unconfirmed'])) {
			// Written by ebDisconnect() only: the bank did not confirm that access was withdrawn.
			print "<div class='eb-flash eb-flash-warn' style='margin:8px 0 0 0'>" . ebH($link['last_error']) . "</div>";
		}

		// One line per account; the settings open behind Ret (or "Vælg bogføringskonto" for an account not in use).
		foreach ($block['accounts'] as $account) {
			$id       = (int)$account['id'];
			$state    = (string)($account['state'] ?? '');
			$currency = (string)($account['currency'] ?? '');
			$ledger   = (int)($account['ledger_account'] ?? 0);
			$foreign  = $state === 'unsupported' || ($currency !== '' && $currency !== 'DKK');
			$inUse    = in_array($state, array('active', 'paused'), true);
			print "<div class='eb-account" . ($id === $highlight ? ' eb-highlight' : '') . "' id='konto-$id'><div class='eb-account-line'><div class='eb-account-name'>";
			$last4 = ebAccountLast4($account['display_identity'] ?? '');
			if ($ledger > 0) {
				print "<strong>" . ebH(trim($ledger . ' ' . ebLedgerName($ledger))) . "</strong> <span class='eb-summary-bank'>· " . ebH(trim(($link['aspsp_name'] ?? '') . ' ' . $last4)) . "</span>";
			} else {
				print "<strong>" . ebH(ebAccountLabel($account, $link)) . "</strong>";
			}
			if ($foreign) {
				print " <span class='eb-badge muted'>" . ebH(ebAccountStateLabel('unsupported')) . "</span>";
			} elseif ($state !== 'active') {
				print " <span class='eb-badge " . ebH(ebAccountStateTone($state)) . "'>" . ebH(ebAccountStateLabel($state)) . "</span>";
			}
			$sub = array_filter(array(ebHolderName($account['display_name'] ?? ''), (string)($account['display_identity'] ?? ''), $currency !== 'DKK' ? $currency : ''));
			if ($inUse && !empty($account['import_from'])) {
				$sub[] = sprintf(ebT(findtekst('bank.accounts.start_from|Startdato %s', ebLang())), ebFormatDate($account['import_from']));
			}
			if ($inUse) {
				$sub[] = !empty($account['last_fetch_at']) ? sprintf(ebT(findtekst('bank.feed.last_fetched|Senest hentet %s', ebLang())), ebFormatDateTime($account['last_fetch_at']))
					: ebT(findtekst('bank.feed.never_fetched|Endnu ikke hentet', ebLang()));
			}
			print "<div class='eb-account-sub'>" . ebH(implode(' · ', $sub)) . "</div>";
			print "</div><div class='eb-account-actions'>";
			if ($inUse || ($state === 'disconnected' && (int)$account['line_count'] > 0)) {
				print "<a class='eb-btn secondary' href='feed.php?account=$id'>" . ebHT(findtekst('bank.feed.title|Bankposteringer', ebLang())) . "</a>";
			}
			if ($canManage && $state === 'active') {
				print ebPostButton($selfUrl, array('action' => 'pause', 'account' => $id), ebT(findtekst('bank.feed.pause|Sæt på pause', ebLang())), 'secondary',
					ebT(findtekst('bank.feed.pause_confirm|Sæt hentningen på pause? Saldi henter ikke posteringer fra banken, før du genoptager. Du kan stadig bruge de posteringer, der allerede er hentet.', ebLang())));
			} elseif ($canManage && $state === 'paused') {
				print ebPostButton($selfUrl, array('action' => 'resume', 'account' => $id), ebT(findtekst('bank.feed.resume|Genoptag', ebLang())), 'secondary');
			}
			print "</div></div>";

			// A fetch error only while the account is in use and the connection itself is fine (a removed account never shows an old one).
			if ($inUse && !empty($account['last_error_code']) && !in_array($linkStatus, array('expired', 'revoked', 'disconnected'), true)) {
				$errorText = ebErrorText((string)$account['last_error_code'], null);
				$safeCode = preg_replace('/[^A-Z0-9_]/', '', strtoupper((string)$account['last_error_code'])) ?? '';
				print ebNoticeHtml($errorText . (!str_contains($errorText, '(kode ') && $safeCode !== '' ? " (kode $safeCode)" : ''), 'eb-flash eb-flash-error');
			}

			if ($foreign) {
				print "<div class='eb-account-sub'>" . ebHT(findtekst('bank.accounts.unsupported|Saldi kan kun importere konti i danske kroner. Kontoen er forbundet, men dens posteringer kan ikke lægges i en kassekladde.', ebLang())) . "</div>";
			} elseif ($state === 'new') {
				if ($canMap) {
					$open = $id === $highlight || $connectionFilter > 0 ? ' open' : '';
					print "<details$open><summary>" . ebHT(findtekst('bank.accounts.take_into_use|Vælg bogføringskonto for at tage den i brug', ebLang())) . "</summary>"
						. ebAccountsMapForm($account, $ledgerOptions, $selfUrl, $defaultImportFrom) . "</details>";
				} else {
					print "<div class='eb-account-sub'>" . ebHT(findtekst('bank.accounts.take_into_use|Vælg bogføringskonto for at tage den i brug', ebLang())) . "</div>";
				}
			} elseif ($inUse && $canMap) {
				print "<details" . ($id === $highlight ? ' open' : '') . "><summary>" . ebHT(findtekst('bank.accounts.edit|Ret', ebLang())) . "</summary>"
					. ebAccountsMapForm($account, $ledgerOptions, $selfUrl, $defaultImportFrom) . "</details>";
			}
			print "</div>";
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
