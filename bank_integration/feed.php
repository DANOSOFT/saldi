<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/feed.php --- ver 5.1.1 --- 2026-11-18 ---
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
// 20261006 CL/LH Initial version. Bank feed per account: status panel (access expiry, last fetch/import, queue, errors, deferral), Hent nu / Forny adgang / Afbryd, first-batch review with overlap hints, and the review tabs with per-transaction actions. Forms carry only ids and an action.
// 20261007 CDX/LH Added review retries, used complete UI data and translated errors without holding session locks.
// 20261007 CDX/LH Paginated first review, separated page skips from bounded approval and exposed review import.
// 20261007 CDX/LH Carried frozen review boundaries and versions through pagination, skips and approval.
// 20261028 CDX/LH Handle manual picking, date filters, auto import and pause without onboarding.
// 20261028 CL/LH Pick list under Nye (checkboxes, select all, date filter, action bar, overlap hints never preselected), Import Manuel/Automatisk switch, Pause/Genoptag, disconnect confirm with the waiting count. Hent nu returns to the journal only for automatic accounts; picked deferrals and revoke failures get their own messages.
// 20261028 CL/LH The valørdato button is an explicit import (Importér med valørdato + confirm), and the automatic-import promises only show on active accounts: paused waits for Genoptag, disconnected rows are picked by hand.
// 20261028 CL/LH Genoptag says when fetching stays stopped; bulk actions return to the first page.
// 20261116 CL/LH Pull model: picked lines go into the journal the user came from (kladde_id, "Læg valgte i kladde N", ebImportIntoJournal()) and the user is sent back to it. Without a journal there is only a hint and skip.
//                No automatic import switch, bank journal or deferral texts; texts through findtekst() (bank.feed.*).
// 20261116 CL/LH The actor is the unescaped user name, so a name with an apostrophe matches its own journal lock.
// 20261117 CL/LH The "Godkend ændring" button sends a hash of the change shown, so a newer change from the bank is never approved unseen.
// 20261118 CL/LH Forbind igen / Forny adgang posts as the whole window (target _top): the bank login cannot be shown in Saldi's frame.
// 20261118 CL/LH UI pass: one summary line per account (ledger-first name, Senest hentet, Hent nu, Administrér) instead of the status panel; Forny adgang only in the expired or action-required notice, Pause/Genoptag and Fjern bankforbindelse on the accounts page.
//                Ind and Ud columns, a sticky pick bar with count and sums, a click on the row ticks it (shift-click for a range), account tabs only for accounts in use.
//                Fetch feedback with the time and the number of new lines, the time Hent nu can be used again, support codes in a title attribute; dd-mm-åååå date filter; Tilbage til kladde N.

/**
 * Injected by ../includes/online.php, included below:
 * @var int|string $bruger_id
 * @var string $brugernavn
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}
$s_id    = session_id();
$title   = 'Bankposteringer';
$modulnr = 2;
$header  = 'nix';
$css     = '../css/standard.css';
ob_start();

include(__DIR__ . '/../includes/connect.php');
include(__DIR__ . '/../includes/online.php');
include(__DIR__ . '/../includes/std_func.php');
require_once __DIR__ . '/includes/ebAccess.php';

const EB_FEED_TABS     = array('pending', 'review', 'imported', 'ignored', 'pre_cutover');
const EB_FEED_PAGE     = 200;
const EB_FEED_TX_OPS   = array('ignore', 'restore', 'reimport', 'import', 'accept_change', 'import_value_date');
const EB_FEED_LIVE     = array('active');

/**
 * URL of this page with the current selection, optionally overridden.
 *
 * @param array<string, int|string|null> $over Parameters to change (null removes one).
 * @return string Relative URL.
 */
function ebFeedUrl(array $over = array()): string {
	global $ebFeedParams;
	$params = array_merge($ebFeedParams, $over);
	$params = array_filter($params, fn($v) => $v !== null && $v !== '' && $v !== 0);
	return 'feed.php' . ($params ? '?' . http_build_query($params) : '');
}

/**
 * Count with the singular or plural noun, e.g. "1 postering", "3 posteringer".
 *
 * @param int $n Count.
 * @return string UTF-8 text.
 */
function ebFeedCount(int $n): string {
	return $n === 1 ? ebT(findtekst('bank.feed.count_one|1 postering', ebLang()))
		: sprintf(ebT(findtekst('bank.feed.count_many|%s posteringer', ebLang())), $n);
}

/**
 * Journal URL in the format the journal list uses (it takes the edit lock again).
 *
 * @param int $kladdeId kladdeliste.id
 * @return string Relative URL.
 */
function ebFeedJournalUrl(int $kladdeId): string {
	return '../finans/kassekladde.php?tjek=' . $kladdeId . '&kladde_id=' . $kladdeId;
}

/**
 * Date cell: booking date, else value date marked as such.
 *
 * @param array<string, mixed> $tx bank_transactions row.
 * @return string HTML.
 */
function ebFeedDateCell(array $tx): string {
	if (!empty($tx['date_override'])) {
		return ebH(ebFormatDate($tx['date_override'])) . "<span class='eb-sub'>" . ebHT(findtekst('bank.feed.value_date_chosen|valørdato valgt', ebLang())) . "</span>";
	}
	if (!empty($tx['booking_date'])) {
		return ebH(ebFormatDate($tx['booking_date']));
	}
	if (!empty($tx['value_date'])) {
		return ebH(ebFormatDate($tx['value_date'])) . "<span class='eb-sub'>" . ebHT(findtekst('bank.feed.value_date|valørdato', ebLang())) . "</span>";
	}
	return "<span class='eb-muted'>" . ebHT(findtekst('bank.feed.no_date|Mangler dato', ebLang())) . "</span>";
}

/**
 * Text cell: description, counterparty and bank reference.
 *
 * @param array<string, mixed> $tx bank_transactions row.
 * @return string HTML.
 */
function ebFeedTextCell(array $tx): string {
	$description = trim((string)($tx['description'] ?? ''));
	$html = $description !== '' ? ebH($description) : "<span class='eb-muted'>" . ebHT(findtekst('bank.feed.no_text|(ingen tekst fra banken)', ebLang())) . "</span>";
	$sub = array();
	$counterparty = trim((string)($tx['counterparty'] ?? ''));
	if ($counterparty !== '' && mb_stripos($description, $counterparty) === false) {
		$sub[] = $counterparty;
	}
	$reference = trim((string)($tx['bank_reference'] ?? ''));
	if ($reference !== '') {
		$sub[] = sprintf(ebT(findtekst('bank.feed.reference|Ref. %s', ebLang())), $reference);
	}
	if ($sub) {
		$html .= "<span class='eb-sub'>" . ebH(implode(' · ', $sub)) . "</span>";
	}
	if (!empty($tx['identical_group']) && (int)$tx['identical_group'] === 1) {
		$html .= "<span class='eb-hint'>" . ebHT(findtekst('bank.feed.identical|Der findes en ens postering samme dag - begge er hentet fra banken.', ebLang())) . "</span>";
	}
	return $html;
}

/**
 * The Ind and Ud cells: money in on the left, money out on the right, both without a sign; currency when not DKK.
 *
 * @param array<string, mixed> $tx bank_transactions row.
 * @return string HTML (two td elements).
 */
function ebFeedAmountCells(array $tx): string {
	$amount = trim((string)($tx['amount'] ?? ''));
	$currency = (string)($tx['currency'] ?? '');
	$out = str_starts_with($amount, '-');
	$text = ebH(ebFormatAmount(ltrim($amount, '+-'))) . ($currency !== '' && $currency !== 'DKK' ? ' ' . ebH($currency) : '');
	return $out ? "<td class='num'></td><td class='num'>$text</td>" : "<td class='num'>$text</td><td class='num'></td>";
}

/**
 * Newer values the bank sent for a transaction changed after import.
 *
 * @param array<string, mixed> $tx bank_transactions row.
 * @return string HTML ('' when nothing to show).
 */
function ebFeedChangedCell(array $tx): string {
	$changed = json_decode((string)($tx['changed_payload'] ?? ''), true);
	if (!is_array($changed)) {
		return '';
	}
	$parts = array();
	if (!empty($changed['booking_date'])) {
		$parts[] = ebFormatDate((string)$changed['booking_date']);
	}
	if (isset($changed['amount']) && $changed['amount'] !== '') {
		$parts[] = ebFormatAmount((string)$changed['amount']);
	}
	if (!empty($changed['description'])) {
		$parts[] = (string)$changed['description'];
	}
	return $parts ? "<span class='eb-hint'>" . ebH(sprintf(ebT(findtekst('bank.feed.bank_now_shows|Banken viser nu: %s', ebLang())), implode(' · ', $parts))) . "</span>" : '';
}

/**
 * Action buttons for one transaction row outside the pick list.
 *
 * @param array<string, mixed> $tx bank_transactions row (with link disposition when imported).
 * @param string $disposition Link disposition for imported rows.
 * @return string HTML.
 */
function ebFeedRowActions(array $tx, string $disposition): string {
	$id = (int)$tx['id'];
	$state = (string)($tx['review_state'] ?? '');
	$reason = (string)($tx['review_reason'] ?? '');
	$url = ebFeedUrl();
	$buttons = array();
	$button = function (string $op, string $label, string $class = 'small secondary', string $confirm = '', array $extra = array()) use ($id, $url): string {
		return ebPostButton($url, array('action' => 'tx', 'tx' => $id, 'op' => $op) + $extra, $label, $class, $confirm);
	};
	$skip = ebT(findtekst('bank.feed.skip|Spring over', ebLang()));
	if ($state === 'review') {
		if ($reason === 'after_review' || $reason === 'possible_duplicate') {
			$buttons[] = $button('import', ebT(findtekst('bank.feed.move_to_new|Flyt til Nye', ebLang())), 'small',
				$reason === 'possible_duplicate' ? ebT(findtekst('bank.feed.move_duplicate_confirm|Flyt posteringen til Nye, selv om en ens postering allerede findes?', ebLang())) : '');
		} elseif ($reason === 'changed_after_import') {
			// The approval names the change shown here, so a newer change from the bank is never approved unseen.
			$buttons[] = $button('accept_change', ebT(findtekst('bank.feed.accept_change|Godkend ændring', ebLang())), 'small', '',
				array('change' => hash('sha256', (string)($tx['changed_payload'] ?? ''))));
		} elseif ($reason === 'missing_date' && !empty($tx['value_date'])) {
			$buttons[] = $button('import_value_date', sprintf(ebT(findtekst('bank.feed.use_value_date|Brug valørdato %s', ebLang())), ebFormatDate($tx['value_date'])), 'small');
		}
		if ($reason !== 'changed_after_import') {
			$buttons[] = $button('ignore', $skip);
		}
	} elseif ($state === 'pending') {
		$buttons[] = $button('ignore', $skip);
	} elseif ($state === 'ignored') {
		$buttons[] = $button('restore', ebT(findtekst('bank.feed.restore|Gendan', ebLang())));
	} elseif ($state === 'imported' && $disposition === 'deleted') {
		$buttons[] = $button('reimport', ebT(findtekst('bank.feed.reimport|Flyt til Nye igen', ebLang())), 'small',
			ebT(findtekst('bank.feed.reimport_confirm|Linjen er slettet i kassekladden. Skal posteringen ligge under Nye igen, så den kan lægges i en kassekladde?', ebLang())));
	}
	return implode(' ', $buttons);
}

/**
 * Journal cell for imported rows.
 *
 * @param array{kladde_id: int, bilag: int, disposition: string}|null $link Link data.
 * @return string HTML.
 */
function ebFeedLinkCell(?array $link): string {
	if (!$link || $link['kladde_id'] <= 0) {
		return '';
	}
	$journal = "<a href='" . ebH(ebFeedJournalUrl($link['kladde_id'])) . "'>" . ebH(sprintf(ebT(findtekst('bank.feed.journal_n|Kassekladde %s', ebLang())), $link['kladde_id'])) . "</a>";
	$bilag = $link['bilag'] > 0 ? ebH(sprintf(ebT(findtekst('bank.feed.voucher_n|, bilag %s', ebLang())), $link['bilag'])) : '';
	switch ($link['disposition']) {
		case 'posted':
			return ebHT(findtekst('bank.feed.posted|Bogført', ebLang())) . " - $journal$bilag";
		case 'deleted':
			return "<span class='eb-hint'>" . ebHT(findtekst('bank.feed.line_deleted|Linjen er slettet i kassekladden', ebLang())) . "</span>$journal";
		case 'replaced':
			return "<span class='eb-muted'>" . ebHT(findtekst('bank.feed.replaced|Erstattet af en ny import', ebLang())) . "</span>";
		default:
			return $journal . $bilag;
	}
}

// ---------------------------------------------------------------------
// Controller
// ---------------------------------------------------------------------
$accountId     = (int)($_GET['account'] ?? 0);
$tab           = in_array($_GET['tab'] ?? '', EB_FEED_TABS, true) ? (string)$_GET['tab'] : '';
$page          = max(0, (int)($_GET['page'] ?? 0));
$fromInput     = trim(is_string($_GET['from'] ?? null) ? $_GET['from'] : '');
$toInput       = trim(is_string($_GET['to'] ?? null) ? $_GET['to'] : '');
$from          = ebParseDate($fromInput);
$to            = ebParseDate($toInput);
// A date that cannot be read filters nothing and is shown back as typed, with a message.
$badDates      = array_values(array_filter(array($from === null ? $fromInput : '', $to === null ? $toInput : ''), fn($v) => $v !== ''));
$kladdeId      = max(0, (int)($_GET['kladde_id'] ?? 0));
$unavailable   = ebUnavailableReason();
$canView       = ebCanViewFeed();
$canAct        = ebCanActOnFeed();
$canManage     = ebCanManage();

$accounts    = array();
$connections = array();
$loadError   = '';
$journalNotice = '';
if ($unavailable === null && $canView) {
	try {
		$accounts    = ebIndexById(ebListAccounts());
		$connections = ebIndexById(ebListConnections());
		// The journal the user came from (the kassekladde button "Bankposteringer"): picked lines go there.
		if ($kladdeId > 0 && ebRow('SELECT id FROM kladdeliste WHERE id=' . $kladdeId . " AND bogfort='-'") === null) {
			$journalNotice = sprintf(ebT(findtekst('bank.feed.journal_unavailable|Kassekladde %s findes ikke eller er bogført, så der kan ikke lægges posteringer i den.', ebLang())), $kladdeId);
			$kladdeId = 0;
		}
	} catch (Throwable $e) {
		$loadError = ebErrorMessage($e);
	}
}

// Accounts with lines to work on get a tab: those in use, and removed ones that still have fetched lines.
// An account without a ledger account (not in use yet) or in a foreign currency is set up on the accounts page.
$feedAccounts = array();
foreach ($accounts as $id => $row) {
	$rowState = (string)($row['state'] ?? '');
	if (in_array($rowState, array('active', 'paused'), true)) {
		$feedAccounts[$id] = $row;
	} elseif ($rowState === 'disconnected') {
		try {
			if (array_sum(ebCountsByState((int)$id)) > 0) {
				$feedAccounts[$id] = $row;
			}
		} catch (Throwable $e) {
			// Without counts the removed account simply gets no tab.
		}
	}
}

// Pick the account: explicit id, else the only one with a tab.
if ($accountId > 0 && !isset($accounts[$accountId])) {
	$accountId = 0;
}
if ($accountId === 0 && count($feedAccounts) === 1) {
	$accountId = (int)array_key_first($feedAccounts);
}
$account    = $accountId > 0 ? $accounts[$accountId] : null;
$bankConnection = $account ? ($connections[(int)($account['connection_id'] ?? 0)] ?? array()) : array();

$ebFeedParams = array(
	'account'   => $accountId ?: null,
	'tab'       => $tab ?: null,
	'page'      => $page ?: null,
	'from'      => $from,
	'to'        => $to,
	'kladde_id' => $kladdeId ?: null,
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	ebCsrfCheck();
	$action = (string)($_POST['action'] ?? '');
	if ($unavailable !== null) {
		ebFlash('error', $unavailable);
		ebRedirect(ebFeedUrl());
	}
	if (!$account) {
		ebFlash('error', ebT(findtekst('bank.feed.choose_account|Vælg en bankkonto.', ebLang())));
		ebRedirect(ebFeedUrl(array('account' => null)));
	}
	$needsManage = in_array($action, array('disconnect', 'pause', 'resume'), true);
	if (($needsManage && !$canManage) || (!$needsManage && !$canAct)) {
		ebFlash('error', $needsManage
			? ebT(findtekst('bank.feed.no_manage_right|Du har ikke adgang til at ændre bankforbindelsen. Det kræver adgang til Indstillinger.', ebLang()))
			: ebT(findtekst('bank.feed.read_only|Du har kun læseadgang til kassekladden og kan ikke ændre bankposteringer.', ebLang())));
		ebRedirect(ebFeedUrl());
	}
	session_write_close();
	$actor = (string)($brugernavn_raw ?? $brugernavn);
	try {
		if ($action === 'refresh') {
			$pendingBefore = (int)(ebCountsByState($accountId)['pending'] ?? 0);
			$run = ebSyncAccount($accountId, 'manual', ebPsuHeadersFromRequest());
			$runStatus = (string)($run['status'] ?? '');
			$runMessage = !empty($run['error_code'])
				? ebErrorText($run['error_code'], $run['error_kind'] ?? null)
				: trim((string)($run['message'] ?? ''));
			if (!empty($run['error_code']) && !str_contains($runMessage, '(kode ')) {
				$runMessage .= ' (kode ' . strtoupper($run['error_code']) . ')';
			}
			if ($runStatus === 'ok') {
				$fresh = max(0, (int)(ebCountsByState($accountId)['pending'] ?? 0) - $pendingBefore);
				$freshText = $fresh === 0 ? ebT(findtekst('bank.feed.new_none|ingen nye posteringer', ebLang()))
					: ($fresh === 1 ? ebT(findtekst('bank.feed.new_one|1 ny postering', ebLang()))
						: sprintf(ebT(findtekst('bank.feed.new_many|%s nye posteringer', ebLang())), $fresh));
				ebFlash('ok', sprintf(ebT(findtekst('bank.feed.fetched_at|Hentet kl. %s - %s', ebLang())), date('H:i'), $freshText));
			} elseif ($runStatus === 'skipped') {
				// Hent nu works once every 5 minutes (ebSyncAccount()); say when it can be used again.
				$lastManual = (string)(ebRow('SELECT last_manual_at FROM bank_accounts WHERE id=' . (int)$accountId)['last_manual_at'] ?? '');
				$lastManualTs = $lastManual !== '' ? strtotime($lastManual) : false;
				if ($lastManualTs !== false && $lastManualTs > time() - 300) {
					$runMessage = sprintf(ebT(findtekst('bank.feed.fetch_wait|Der blev hentet kl. %s. Du kan hente igen kl. %s.', ebLang())),
						date('H:i', $lastManualTs), date('H:i', $lastManualTs + 300));
				}
				ebFlash('info', $runMessage !== '' ? $runMessage : ebRunStatusLabel($runStatus) . '.');
			} elseif (in_array($runStatus, array('failed', 'partial'), true)) {
				ebFlash('error', $runMessage !== '' ? sprintf(ebT(findtekst('bank.feed.fetch_failed_reason|Hentningen fejlede: %s', ebLang())), $runMessage)
					: ebT(findtekst('bank.feed.fetch_failed|Hentningen fejlede. Prøv igen senere.', ebLang())));
			} else {
				ebFlash('error', $runMessage !== '' ? $runMessage : ebRunStatusLabel($runStatus) . '.');
			}
		} elseif ($action === 'disconnect') {
			$connectionId = (int)($account['connection_id'] ?? 0);
			if ($connectionId <= 0) {
				ebFlash('error', ebT(findtekst('bank.feed.no_connection|Kontoen har ingen aktiv bankforbindelse.', ebLang())));
			} else {
				$client = null;
				try {
					$client = new EnableBankingClient(ebConfig());
				} catch (Throwable $e) {
					$client = null; // Disconnect locally even when the provider cannot be reached.
				}
				ebDisconnect($connectionId, $actor, $client);
				ebFlash('ok', sprintf(ebT(findtekst('bank.feed.disconnected|Forbindelsen til %s er afbrudt. Importerede og bogførte posteringer er bevaret.', ebLang())),
					($bankConnection['aspsp_name'] ?? '') ?: ebT(findtekst('bank.feed.the_bank|banken', ebLang()))));
				$revokeError = (string)(ebIndexById(ebListConnections())[$connectionId]['last_error'] ?? '');
				if ($revokeError !== '') {
					ebFlash('warn', $revokeError);
				}
			}
		} elseif ($action === 'import_selected') {
			$ebFeedParams['page'] = null;
			if ($kladdeId <= 0) {
				throw new EbUserError(ebT(findtekst('bank.feed.open_journal_first_postings|Åbn den kassekladde, posteringerne skal i, og klik Bankposteringer.', ebLang())));
			}
			$result = ebImportIntoJournal($kladdeId, $accountId, (array)($_POST['ids'] ?? array()), $actor);
			$skipped = count($result['skipped']);
			$message = $skipped === 0 ? '' : sprintf(ebT(findtekst('bank.feed.import_skipped|%s kunne ikke lægges i kassekladden og ligger stadig under Nye, hvor årsagen står.', ebLang())), ebFeedCount($skipped));
			if ($result['imported'] > 0) {
				// Back to the journal the lines went into, which tells what happened once.
				ebJournalNotice($kladdeId, trim(sprintf(ebT(findtekst('bank.feed.imported_into|%s er lagt i kassekladde %s.', ebLang())), ebFeedCount($result['imported']), $kladdeId) . ' ' . $message));
				ebRedirect(ebFeedJournalUrl($kladdeId));
			}
			ebFlash('warn', $message !== '' ? $message : ebT(findtekst('bank.feed.nothing_imported|Ingen posteringer er lagt i kassekladden.', ebLang())));
		} elseif ($action === 'ignore_selected') {
			$ebFeedParams['page'] = null;
			$count = ebIgnoreSelected($accountId, (array)($_POST['ids'] ?? array()), $actor);
			ebFlash('ok', sprintf(ebT(findtekst('bank.feed.ignored|%s er sprunget over. De kan gendannes under Sprunget over.', ebLang())), ebFeedCount($count)));
		} elseif ($action === 'pause' || $action === 'resume') {
			$scheduled = ebSetPaused($accountId, $action === 'pause', $actor);
			ebFlash('ok', $action === 'pause' ? ebT(findtekst('bank.feed.paused|Hentningen er sat på pause.', ebLang()))
				: ($scheduled ? ebT(findtekst('bank.feed.resumed|Hentningen er genoptaget. Saldi henter fra banken ved næste kørsel.', ebLang()))
					: ebT(findtekst('bank.feed.resumed_blocked|Hentningen er genoptaget, men Saldi kan ikke hente fra banken, før fejlen herunder er løst.', ebLang()))));
		} elseif ($action === 'tx') {
			$txId = (int)($_POST['tx'] ?? 0);
			$op = (string)($_POST['op'] ?? '');
			if ($txId <= 0 || !in_array($op, EB_FEED_TX_OPS, true)) {
				ebFlash('error', ebT(findtekst('bank.feed.unknown_action|Ukendt handling.', ebLang())));
			} else {
				// Binds the action to the selected account (see ebTransactionAction()).
				$GLOBALS['ebActionAccountId'] = $accountId;
				$seenChange = isset($_POST['change']) && is_string($_POST['change']) ? $_POST['change'] : null;
				$result = ebTransactionAction($txId, $op, $actor, $seenChange);
				ebFlash('ok', (string)$result['message']);
			}
		}
	} catch (Throwable $e) {
		ebFlash('error', ebErrorMessage($e));
	}
	ebRedirect(ebFeedUrl());
}

// Read-side bookkeeping before rendering: mark links whose journal line was deleted or posted.
if ($account && $unavailable === null && $canView) {
	try {
		ebReconcileLinks($accountId);
	} catch (Throwable $e) {
		// Not essential for showing the page; the worker reconciles too.
	}
}

$status      = null;
$counts      = array();
$rows        = array();
$overlap     = array();
$waitingForConnection = 0;
if ($account && $loadError === '') {
	try {
		$status = ebNormalizeStatus(ebAccountStatus($accountId));
		$counts = $status['counts'] ?: ebCountsByState($accountId);
		if ($tab === '') {
			$tab = ($counts['pending'] ?? 0) > 0 ? 'pending' : (($counts['review'] ?? 0) > 0 ? 'review' : 'imported');
		}
		$ebFeedParams['tab'] = $tab;
		$rows = ebListTransactions($accountId, $tab, EB_FEED_PAGE + 1, $page * EB_FEED_PAGE, $from, $to);
		if (!empty($account['connection_id'])) {
			$waitingForConnection = ebWaitingCountForConnection((int)$account['connection_id']);
		}
	} catch (Throwable $e) {
		$loadError = ebErrorMessage($e);
	}
}
$hasNextPage = count($rows) > EB_FEED_PAGE;
if ($hasNextPage) {
	array_pop($rows);
}

if ($account && $tab === 'pending' && $loadError === '') {
	try {
		$overlap = ebOverlapHints($accountId, array_map(static fn(array $tx): int => (int)$tx['id'], $rows));
	} catch (Throwable $e) {
		$overlap = array(); // Hints are advisory; the list must still be shown.
	}
}

if ($kladdeId > 0) {
	$backHref = ebFeedJournalUrl($kladdeId);
	$backLabel = sprintf(ebT(findtekst('bank.feed.back_to_journal|Tilbage til kladde %s', ebLang())), $kladdeId);
} elseif ($canManage) {
	$backHref = '../systemdata/diverse.php?sektion=bank_integration';
	$backLabel = '';
} else {
	$backHref = '../finans/kladdeliste.php';
	$backLabel = '';
}

// ---------------------------------------------------------------------
// View
// ---------------------------------------------------------------------
$feedTitle = ebT(findtekst('bank.feed.title|Bankposteringer', ebLang()));
$ledger = $account ? (int)($account['ledger_account'] ?? 0) : 0;
$ledgerTitle = $ledger > 0 ? trim($ledger . ' ' . ebLedgerName($ledger)) : ($account ? ebAccountLabel($account, $bankConnection) : '');
include(__DIR__ . '/includes/page_header.php');
print ebRenderTopline($feedTitle . ($ledgerTitle !== '' ? ' - ' . $ledgerTitle : ''), $backHref, $backLabel);
print "<div class='content-noside'>\n";
$flashHtml = ebRenderFlash();
print $flashHtml;
if ($journalNotice !== '') {
	print "<div class='eb-flash eb-flash-warn'>" . ebH($journalNotice) . "</div>\n";
}
$askSettings = ebT(findtekst('bank.feed.ask_settings_user|Bed en bruger med adgang til Indstillinger om det.', ebLang()));

if ($unavailable !== null) {
	print "<div class='eb-panel'><p>" . ebH($unavailable) . "</p></div>\n";
} elseif (!$canView) {
	print "<div class='eb-panel'><p>" . ebHT(findtekst('bank.feed.no_view_right|Du har ikke adgang til bankposteringer. Det kræver adgang til Kassekladde.', ebLang())) . "</p></div>\n";
} elseif ($loadError !== '' && !$account) {
	print ebNoticeHtml($loadError, 'eb-flash eb-flash-error') . "\n";
} elseif (!$accounts) {
	print "<div class='eb-panel'><h2>" . ebHT(findtekst('bank.feed.none_title|Ingen bankforbindelse endnu', ebLang())) . "</h2>";
	print "<p>" . ebHT(findtekst('bank.feed.none_text|Når en bankkonto er forbundet, henter Saldi kontoens posteringer fra banken flere gange i døgnet. Du vælger selv, hvilke posteringer der skal lægges i kassekladden, hvor du udfylder modkonto og vedhæfter bilag.', ebLang())) . "</p>";
	if ($canManage) {
		print "<div class='eb-actions'><a class='eb-btn' href='connect.php'>" . ebHT(findtekst('bank.feed.connect_bank|Forbind en bank', ebLang())) . "</a></div>";
	} else {
		print "<p class='eb-muted'>" . ebHT(findtekst('bank.feed.ask_to_connect|Bed en bruger med adgang til Indstillinger om at forbinde banken.', ebLang())) . "</p>";
	}
	print "</div>\n";
} else {
	// Account tabs: only accounts with lines to work on, named ledger-first.
	if (count($feedAccounts) > 1 || ($feedAccounts && !isset($feedAccounts[$accountId]))) {
		print "<div class='eb-tabs'>";
		foreach ($feedAccounts as $id => $row) {
			$rowState = (string)($row['state'] ?? '');
			$rowConnection = $connections[(int)($row['connection_id'] ?? 0)] ?? array();
			$badge = '';
			if ($rowState === 'active' && in_array((string)($rowConnection['status'] ?? ''), array('expired', 'revoked'), true)) {
				$badge = " <span class='eb-badge bad'>" . ebHT(findtekst('bank.connection_status.expired|Adgangen er udløbet', ebLang())) . "</span>";
			} elseif ($rowState !== 'active') {
				$badge = " <span class='eb-badge " . ebH(ebAccountStateTone($rowState)) . "'>" . ebH(ebAccountStateLabel($rowState)) . "</span>";
			}
			$active = $id === $accountId ? " class='active'" : '';
			print "<a$active href='" . ebH(ebFeedUrl(array('account' => $id, 'tab' => null, 'page' => null, 'from' => null, 'to' => null))) . "'>"
				. ebH(ebAccountLabel($row, $rowConnection)) . "$badge</a>";
		}
		print "</div>\n";
	}

	if (!$account && $feedAccounts) {
		print "<div class='eb-panel'><p>" . ebHT(findtekst('bank.feed.choose_account_above|Vælg en bankkonto ovenfor.', ebLang())) . "</p></div>\n";
	} elseif (!$account) {
		// Connected, but no bank account has a ledger account yet.
		print "<div class='eb-panel'><p>" . ebHT(findtekst('bank.feed.none_in_use|Ingen bankkonti er i brug endnu. Vælg en bogføringskonto til bankkontoen for at tage den i brug.', ebLang())) . "</p>";
		print $canManage ? "<div class='eb-actions'><a class='eb-btn' href='accounts.php'>" . ebHT(findtekst('bank.feed.choose_ledger_link|Vælg bogføringskonto', ebLang())) . "</a></div>"
			: "<p class='eb-muted'>" . ebH($askSettings) . "</p>";
		print "</div>\n";
	} elseif ($loadError !== '' || !$status) {
		print ebNoticeHtml($loadError ?: ebT(findtekst('bank.feed.status_failed|Status for kontoen kunne ikke hentes.', ebLang())), 'eb-flash eb-flash-error') . "\n";
	} else {
		$state          = $status['state'];
		$live           = in_array($state, EB_FEED_LIVE, true);
		$connectionId   = (int)($bankConnection['id'] ?? 0);
		$connActive     = ($bankConnection['status'] ?? '') === 'active';
		$bankName       = (string)($bankConnection['aspsp_name'] ?? '');
		$actionRequired = $status['last_error_code'] === 'ASPSP_PSU_ACTION_REQUIRED';
		$showConsent    = $connectionId > 0 && !in_array($state, array('disconnected', 'unsupported'), true);
		$pendingCount   = (int)($counts['pending'] ?? 0);
		$renewButton    = $canManage && $connectionId > 0
			? ebPostButton('connect.php', array('purpose' => 'reconnect', 'connection' => $connectionId, 'return_account' => $accountId),
				ebT(findtekst('bank.feed.renew|Forny adgang', ebLang())), '', '', '_top')
			: '';

		// Notices: problems stay loud, the most urgent first.
		if (ebBaseCurrency() !== 'DKK') {
			print "<div class='eb-flash eb-flash-warn'>" . ebHT(findtekst('bank.feed.base_currency|Bankimport kræver, at regnskabets basisvaluta er DKK. Posteringerne kan ikke lægges i kassekladden.', ebLang())) . "</div>";
		}
		if ($showConsent && ($status['expired'] || $actionRequired)) {
			$text = $status['expired']
				? ebT(findtekst('bank.feed.consent_expired|Adgangen til banken er udløbet. Saldi henter ikke flere posteringer, før adgangen er fornyet.', ebLang()))
				: ebT(findtekst('bank.feed.action_required_renew|Banken kræver, at adgangen godkendes igen, før der kan hentes flere posteringer.', ebLang()));
			print "<div class='eb-flash eb-flash-error eb-flash-row'><span>" . ebH($text) . ($renewButton === '' ? ' ' . ebH($askSettings) : '') . "</span>$renewButton</div>";
		}
		if ($state === 'new') {
			print "<div class='eb-flash eb-flash-info'>" . ebHT(findtekst('bank.feed.account_unused|Bankkontoen er ikke i brug. Vælg en bogføringskonto for at tage den i brug.', ebLang()));
			print $canManage ? " <a href='" . ebH('accounts.php?account=' . $accountId . '#konto-' . $accountId) . "'>" . ebHT(findtekst('bank.feed.choose_ledger_link|Vælg bogføringskonto', ebLang())) . "</a>"
				: ' ' . ebH($askSettings);
			print "</div>";
		} elseif ($state === 'unsupported') {
			print "<div class='eb-flash eb-flash-info'>" . ebH(sprintf(ebT(findtekst('bank.feed.account_unsupported|Kontoen er i %s. Saldi kan kun lægge posteringer fra konti i danske kroner (DKK) i kassekladden.', ebLang())), (string)($account['currency'] ?? ''))) . "</div>";
		} elseif ($state === 'disconnected') {
			print "<div class='eb-flash eb-flash-info'>" . ebHT(findtekst('bank.feed.account_removed|Bankforbindelsen er fjernet. Der hentes ikke flere posteringer, og allerede hentede og bogførte posteringer er bevaret.', ebLang()));
			if ($pendingCount > 0) {
				print " " . ebHT(findtekst('bank.feed.still_pick|Du kan stadig lægge de hentede posteringer under Nye i en kassekladde.', ebLang()));
			}
			print "</div>";
			if (($bankConnection['status'] ?? '') === 'disconnected' && trim((string)($bankConnection['last_error'] ?? '')) !== '') {
				// Written by ebDisconnect() only: the bank did not confirm that access was withdrawn.
				print "<div class='eb-flash eb-flash-warn'>" . ebH($bankConnection['last_error']) . "</div>";
			}
		} elseif ($state === 'paused') {
			print "<div class='eb-flash eb-flash-info'>" . ebHT(findtekst('bank.feed.account_paused|Hentningen er sat på pause. Saldi henter ikke nye posteringer fra banken, før den genoptages. Du kan stadig bruge de posteringer, der allerede er hentet.', ebLang()));
			print $canManage ? '' : ' ' . ebH($askSettings);
			print "</div>";
		}
		if ($status['last_run_status'] === 'rate_limited') {
			print "<div class='eb-flash eb-flash-info'>" . ebHT(findtekst('bank.feed.rate_limited|Banken begrænser antallet af automatiske hentninger. Saldi prøver igen ved næste planlagte hentning.', ebLang())) . "</div>";
		}
		if (in_array($status['last_run_status'], array('failed', 'partial'), true) && $status['last_error_code'] !== ''
			&& !$status['expired'] && !$actionRequired && $state !== 'disconnected') {
			$failText = ebErrorText($status['last_error_code'], null);
			$safeCode = preg_replace('/[^A-Z0-9_]/', '', strtoupper($status['last_error_code'])) ?? '';
			$failText = sprintf(ebT(findtekst('bank.feed.fetch_failed_reason|Hentningen fejlede: %s', ebLang())), $failText)
				. (!str_contains($failText, '(kode ') && $safeCode !== '' ? " (kode $safeCode)" : '');
			// The same failure was just reported by Hent nu: say it once.
			if (!str_contains($flashHtml, ebH(preg_replace('/ \(kode [A-Z0-9_]+\)$/', '', $failText) ?? $failText))) {
				print ebNoticeHtml($failText, 'eb-flash eb-flash-error');
			}
		}

		// One summary line: which account, when it was fetched, Hent nu, Administrér.
		$holder = ebHolderName($account['display_name'] ?? '');
		$last4 = ebAccountLast4($account['display_identity'] ?? '');
		print "<div class='eb-panel eb-summary'><div class='eb-summary-name'" . ($holder !== '' ? " title='" . ebH($holder) . "'" : '') . ">";
		if ($ledger > 0) {
			print "<strong>" . ebH($ledgerTitle) . "</strong> <span class='eb-summary-bank'>· " . ebH(trim($bankName . ' ' . $last4)) . "</span>";
		} else {
			print "<strong>" . ebH(ebAccountLabel($account, $bankConnection)) . "</strong>" . ($bankName !== '' ? " <span class='eb-summary-bank'>· " . ebH($bankName) . "</span>" : '');
		}
		if ($state !== 'active') {
			print " <span class='eb-badge " . ebH(ebAccountStateTone($state)) . "'>" . ebH(ebAccountStateLabel($state)) . "</span>";
		}
		if ($showConsent && ($status['expired'] || ($status['days_left'] !== null && $status['days_left'] <= 30))) {
			print " <span class='eb-badge " . ($status['expired'] ? 'bad' : 'warn') . "'>" . ebH(ebConsentText($status)) . "</span>";
		}
		print "</div><div class='eb-summary-meta'>";
		if ($state !== 'new') {
			print "<span class='eb-muted'>" . ebH($status['last_fetch_at'] !== ''
				? sprintf(ebT(findtekst('bank.feed.last_fetched|Senest hentet %s', ebLang())), ebFormatDateTime($status['last_fetch_at']))
				: ebT(findtekst('bank.feed.never_fetched|Endnu ikke hentet', ebLang()))) . "</span>";
		}
		if ($canAct && $live) {
			$disabled = ($status['expired'] || !$connActive) ? " disabled title='" . ebHT(findtekst('bank.feed.renew_first|Adgangen skal fornys først', ebLang())) . "'"
				: " title='" . ebHT(findtekst('bank.feed.fetch_schedule|Saldi henter nye posteringer fra banken op til fire gange i døgnet. Hent nu kan bruges én gang hvert 5. minut.', ebLang())) . "'";
			print "<form method='post' action='" . ebH(ebFeedUrl()) . "' class='eb-inline'>" . ebCsrfField();
			print "<input type='hidden' name='action' value='refresh'><button type='submit' class='eb-btn'$disabled>" . ebHT(findtekst('bank.feed.fetch_now|Hent nu', ebLang())) . "</button></form>";
		}
		if ($canManage) {
			print "<a class='eb-link' href='" . ebH('accounts.php?account=' . $accountId . '#konto-' . $accountId) . "'>" . ebHT(findtekst('bank.feed.manage|Administrér', ebLang())) . "</a>";
		}
		print "</div></div>\n";

		// Tabs (nothing to show before the first transactions arrive).
		$hasTransactions = array_sum(array_map('intval', $counts)) > 0;
		if (!$hasTransactions && $state !== 'new') {
			print "<div class='eb-panel'><p>" . ebHT(findtekst('bank.feed.nothing_yet|Der er endnu ikke hentet nogen posteringer for kontoen.', ebLang())) . "</p>";
			if ($live) {
				print "<p class='eb-muted'>" . ebHT(findtekst('bank.feed.nothing_yet_hint|Brug Hent nu, eller vent på næste automatiske hentning. Bagefter vælger du under Nye, hvilke posteringer der skal i kassekladden.', ebLang())) . "</p>";
			}
			print "</div>\n";
		}
		if ($hasTransactions) {
			print "<div class='eb-tabs'>";
			foreach (EB_FEED_TABS as $t) {
				$n = (int)($counts[$t] ?? 0);
				$active = $t === $tab ? " class='active'" : '';
				print "<a$active href='" . ebH(ebFeedUrl(array('tab' => $t, 'page' => null))) . "'>" . ebH(ebReviewStateLabel($t)) . " ($n)</a>";
			}
			print "</div>\n";

			$filtered = $from !== null || $to !== null;
			$pickList = $tab === 'pending' && $canAct && $rows;
			if ($tab === 'pending') {
				if (!$canAct) {
					$intro = findtekst('bank.feed.intro_pending_read|Nye posteringer fra banken, som endnu ikke er lagt i en kassekladde.', ebLang());
				} elseif ($kladdeId > 0) {
					$intro = sprintf(findtekst('bank.feed.intro_pick_journal|Sæt flueben ved posteringerne til kassekladde %s, og spring over dem, der allerede er bogført.', ebLang()), $kladdeId);
				} else {
					$intro = findtekst('bank.feed.intro_pick|Nye posteringer fra banken - spring over dem, der allerede er bogført.', ebLang());
				}
			} else {
				$intros = array(
					'review'      => findtekst('bank.feed.intro_review|Posteringer, du skal tage stilling til, før de kan lægges i en kassekladde.', ebLang()),
					'imported'    => findtekst('bank.feed.intro_imported_short|Posteringer, der er lagt i en kassekladde.', ebLang()),
					'ignored'     => findtekst('bank.feed.intro_ignored_short|Posteringer, der er sprunget over - gendan dem, der alligevel skal i en kassekladde.', ebLang()),
					'pre_cutover' => findtekst('bank.feed.intro_pre_cutover_short|Posteringer fra før startdatoen, som ikke lægges i en kassekladde.', ebLang()),
				);
				$intro = $intros[$tab] ?? '';
			}
			print "<div class='eb-panel eb-list'>";
			if ($tab === 'pending' && $canAct && $kladdeId === 0) {
				print "<p class='eb-hint eb-journal-hint'>" . ebHT(findtekst('bank.feed.hint_open_journal_postings|Posteringerne lægges i den kassekladde, du arbejder i: åbn kassekladden, og klik Bankposteringer.', ebLang()))
					. " <a href='../finans/kladdeliste.php'>" . ebHT(findtekst('bank.feed.go_journals|Gå til kassekladder', ebLang())) . "</a></p>";
			}

			// Intro and date filter on one line (GET, works without JavaScript).
			$placeholder = ebHT(findtekst('bank.feed.date_placeholder|dd-mm-åååå', ebLang()));
			print "<div class='eb-list-head'><p class='eb-muted'>" . ebHT($intro) . "</p>";
			print "<form method='get' action='feed.php' class='eb-form-row eb-filter'>";
			foreach (array('account' => $accountId ?: null, 'tab' => $tab, 'kladde_id' => $kladdeId ?: null) as $name => $value) {
				if ($value !== null) {
					print "<input type='hidden' name='$name' value='" . ebH($value) . "'>";
				}
			}
			print "<label>" . ebHT(findtekst('bank.feed.from_date|Fra dato', ebLang())) . "<input type='text' name='from' size='10' maxlength='10' autocomplete='off' placeholder='$placeholder' value='"
				. ebH($from !== null ? ebFormatDate($from) : $fromInput) . "'></label>";
			print "<label>" . ebHT(findtekst('bank.feed.to_date|Til dato', ebLang())) . "<input type='text' name='to' size='10' maxlength='10' autocomplete='off' placeholder='$placeholder' value='"
				. ebH($to !== null ? ebFormatDate($to) : $toInput) . "'></label>";
			print "<button type='submit' class='eb-btn secondary'>" . ebHT(findtekst('bank.feed.show|Vis', ebLang())) . "</button>";
			if ($filtered || $badDates) {
				print "<a class='eb-btn secondary' href='" . ebH(ebFeedUrl(array('from' => null, 'to' => null, 'page' => null))) . "'>" . ebHT(findtekst('bank.feed.all_dates|Vis alle datoer', ebLang())) . "</a>";
			}
			print "</form></div>";
			foreach ($badDates as $badDate) {
				print "<div class='eb-flash eb-flash-warn'>" . ebH(sprintf(ebT(findtekst('bank.feed.bad_date|%s er ikke en gyldig dato. Skriv datoen som dd-mm-åååå, fx 01-10-2026.', ebLang())), '"' . $badDate . '"')) . "</div>";
			}

			if (!$rows) {
				if ($filtered) {
					print "<p>" . ebHT(findtekst('bank.feed.none_in_period|Der er ingen posteringer i den valgte periode.', ebLang())) . "</p>";
				} elseif ($tab === 'pending') {
					print "<p>" . ebHT(findtekst('bank.feed.none_new|Der er ingen nye posteringer lige nu.', ebLang())) . "</p>";
				} else {
					print "<p>" . ebHT(findtekst('bank.feed.none_here|Ingen posteringer her.', ebLang())) . "</p>";
				}
			} else {
				$showLink = $tab === 'imported';
				$showReason = $tab === 'review';
				$showActions = $canAct && !$pickList;
				if ($pickList) {
					print "<form method='post' action='" . ebH(ebFeedUrl()) . "' id='eb-pick'>" . ebCsrfField();
					print "<div class='eb-actions eb-pickbar'>";
					if ($kladdeId > 0) {
						print "<button type='submit' name='action' value='import_selected' class='eb-btn'>"
							. ebH(sprintf(ebT(findtekst('bank.feed.import_into|Læg valgte i kladde %s', ebLang())), $kladdeId)) . "</button>";
					}
					print "<button type='submit' name='action' value='ignore_selected' class='eb-btn secondary' data-confirm='"
						. ebHT(findtekst('bank.feed.skip_confirm|Spring de valgte posteringer over? De lægges ikke i en kassekladde. Du kan gendanne dem under Sprunget over.', ebLang())) . "'>"
						. ebHT(findtekst('bank.feed.skip_selected|Spring valgte over', ebLang())) . "</button>";
					print "<span class='eb-pick-count'></span>";
					print "</div>";
				}
				print "<table class='eb-table" . ($pickList ? ' eb-pick-table' : '') . "'><thead><tr>";
				if ($pickList) {
					$selectAll = ebHT(findtekst('bank.feed.select_all|Vælg alle på siden', ebLang()));
					print "<th class='eb-check'><input type='checkbox' id='eb-pick-all' hidden title='$selectAll' aria-label='$selectAll'></th>";
				}
				print "<th>" . ebHT(findtekst('bank.feed.col_date|Dato', ebLang())) . "</th><th>" . ebHT(findtekst('bank.feed.col_text|Tekst', ebLang())) . "</th>";
				print "<th class='num'>" . ebHT(findtekst('bank.feed.col_in|Ind', ebLang())) . "</th><th class='num'>" . ebHT(findtekst('bank.feed.col_out|Ud', ebLang())) . "</th>";
				print $showReason ? "<th>" . ebHT(findtekst('bank.feed.col_reason|Årsag', ebLang())) . "</th>" : '';
				print $showLink ? "<th>" . ebHT(findtekst('bank.feed.col_journal|Kassekladde', ebLang())) . "</th>" : '';
				print $showActions ? "<th></th>" : '';
				print "</tr></thead><tbody>";
				$pickLabel = ebHT(findtekst('bank.feed.pick_one|Vælg posteringen', ebLang()));
				foreach ($rows as $tx) {
					$id = (int)$tx['id'];
					$link = null;
					if ($showLink) {
						$link = array('kladde_id' => (int)$tx['kladde_id'], 'bilag' => (int)$tx['bilag'], 'disposition' => (string)$tx['disposition']);
					}
					print "<tr>";
					if ($pickList) {
						// Overlap hints are advice only: a row is never preselected.
						print "<td class='eb-check'><input type='checkbox' name='ids[]' value='$id' id='eb-tx-$id' data-amount='" . ebH(trim((string)($tx['amount'] ?? ''))) . "' aria-label='$pickLabel'></td>";
						print "<td class='nowrap'><label for='eb-tx-$id'>" . ebFeedDateCell($tx) . "</label></td>";
					} else {
						print "<td class='nowrap'>" . ebFeedDateCell($tx) . "</td>";
					}
					print "<td>" . ebFeedTextCell($tx);
					if ($tab === 'pending') {
						if (isset($overlap[$id])) {
							print "<span class='eb-hint'>" . ebH($overlap[$id]) . "</span>";
						}
						if (!empty($tx['review_reason'])) {
							// Set by ebImportIntoJournal() when the line could not go into the journal.
							print "<span class='eb-hint eb-skip-reason'>" . ebH(sprintf(ebT(findtekst('bank.feed.not_imported_reason|Ikke lagt i kassekladden: %s', ebLang())), ebReviewReasonLabel($tx['review_reason']))) . "</span>";
						}
					}
					print "</td>";
					print ebFeedAmountCells($tx);
					if ($showReason) {
						print "<td>" . ebH(ebReviewReasonLabel($tx['review_reason'] ?? '')) . ebFeedChangedCell($tx) . "</td>";
					}
					if ($showLink) {
						print "<td>" . ebFeedLinkCell($link) . "</td>";
					}
					if ($showActions) {
						print "<td class='nowrap'>" . ebFeedRowActions($tx, (string)($link['disposition'] ?? '')) . "</td>";
					}
					print "</tr>\n";
				}
				print "</tbody></table>";
				if ($pickList) {
					print "</form>";
				}
			}
			if ($page > 0 || $hasNextPage) {
				print "<div class='eb-actions'>";
				if ($page > 0) {
					print "<a class='eb-btn secondary small' href='" . ebH(ebFeedUrl(array('page' => $page - 1 ?: null))) . "'>" . ebHT(findtekst('bank.feed.previous_page|Forrige side', ebLang())) . "</a>";
				}
				if ($hasNextPage) {
					print "<a class='eb-btn secondary small' href='" . ebH(ebFeedUrl(array('page' => $page + 1))) . "'>" . ebHT(findtekst('bank.feed.next_page|Næste side', ebLang())) . "</a>";
				}
				print "</div>";
			}
			print "</div>\n";
			if ($pickList) {
				$pickTexts = array(
					'count' => ebT(findtekst('bank.feed.picked_count|%s valgt', ebLang())),
					'in'    => ebT(findtekst('bank.feed.picked_in|Ind %s', ebLang())),
					'out'   => ebT(findtekst('bank.feed.picked_out|Ud %s', ebLang())),
					'none'  => ebT(findtekst('bank.feed.pick_at_least_one|Vælg mindst én postering.', ebLang())),
				);
				?>
<script>
(function () {
	var texts = <?= json_encode($pickTexts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
	var form = document.getElementById('eb-pick');
	var all = document.getElementById('eb-pick-all');
	if (!form || !all) {
		return;
	}
	var boxes = Array.prototype.slice.call(form.querySelectorAll("input[name='ids[]']"));
	var counters = form.querySelectorAll('.eb-pick-count');
	var last = null;
	// Amounts in thousandths, as the bank sends them (3 decimals), so the sums never meet float rounding.
	function thousandths(text) {
		var m = /^([+-]?)(\d*)(?:\.(\d*))?$/.exec(String(text || '').trim());
		if (!m) {
			return 0;
		}
		var value = parseInt(m[2] || '0', 10) * 1000 + parseInt(((m[3] || '') + '000').slice(0, 3), 10);
		return m[1] === '-' ? -value : value;
	}
	function money(value) {
		var whole = String(Math.floor(value / 1000)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
		var frac = String(value % 1000);
		frac = '000'.slice(frac.length) + frac;
		return whole + ',' + (frac.charAt(2) === '0' ? frac.slice(0, 2) : frac);
	}
	function selected() {
		return boxes.filter(function (b) { return b.checked; }).length;
	}
	function update() {
		var n = 0, money_in = 0, money_out = 0;
		boxes.forEach(function (b) {
			var row = b.closest('tr');
			if (row) {
				row.classList.toggle('eb-picked', b.checked);
			}
			if (b.checked) {
				var v = thousandths(b.getAttribute('data-amount'));
				n++;
				if (v < 0) {
					money_out -= v;
				} else {
					money_in += v;
				}
			}
		});
		all.checked = n > 0 && n === boxes.length;
		all.indeterminate = n > 0 && n < boxes.length;
		var parts = [];
		if (n > 0) {
			parts.push(texts.count.replace('%s', n));
			if (money_in > 0) {
				parts.push(texts['in'].replace('%s', money(money_in)));
			}
			if (money_out > 0) {
				parts.push(texts.out.replace('%s', money(money_out)));
			}
		}
		Array.prototype.forEach.call(counters, function (c) {
			c.textContent = parts.join(' · ');
		});
	}
	// Shift-click ticks or clears every row between the last one clicked and this one.
	function pick(box, shift) {
		var i = boxes.indexOf(box);
		if (shift && last !== null && last !== i) {
			var a = Math.min(last, i), b = Math.max(last, i);
			for (var k = a; k <= b; k++) {
				boxes[k].checked = box.checked;
			}
		}
		last = i;
		update();
	}
	all.hidden = false;
	all.addEventListener('change', function () {
		boxes.forEach(function (b) { b.checked = all.checked; });
		last = null;
		update();
	});
	boxes.forEach(function (b) {
		b.addEventListener('click', function (e) { pick(b, e.shiftKey); });
	});
	var body = form.querySelector('tbody');
	// A shift-click selects a range of rows, not the text in them.
	body.addEventListener('mousedown', function (e) {
		if (e.shiftKey) {
			e.preventDefault();
		}
	});
	// A click anywhere on the row ticks it, except on links, buttons and the box itself (or while text is selected).
	body.addEventListener('click', function (e) {
		if (e.target.closest('a, button, input, label, select, textarea')) {
			return;
		}
		if (window.getSelection && String(window.getSelection()).length > 0) {
			return;
		}
		var row = e.target.closest('tr');
		var box = row ? row.querySelector("input[name='ids[]']") : null;
		if (!box) {
			return;
		}
		box.checked = !box.checked;
		pick(box, e.shiftKey);
	});
	form.addEventListener('submit', function (e) {
		if (selected() === 0) {
			e.preventDefault();
			alert(texts.none);
			return;
		}
		var question = e.submitter ? e.submitter.getAttribute('data-confirm') : null;
		if (question && !confirm(question)) {
			e.preventDefault();
		}
	});
	update();
})();
</script>
<?php
			}
		}
	}
}
print "</div>\n</body>\n</html>\n";
