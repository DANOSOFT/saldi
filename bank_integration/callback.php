<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/callback.php --- ver 5.1.1 --- 2026-11-16 ---
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
// 20261006 CL/LH Initial version. Redirect URL registered with Enable Banking: the bank sends the user back here with code+state (or error). The state is single-use and bound to this company and user.
// 20261007 CDX/LH Released the session lock before authorization completion and translated bank errors.
// 20261028 CDX/LH Use authorization-specific error messages when a bank login cannot be completed.
// 20261116 CL/LH Texts through findtekst() (bank.callback.*).

/**
 * Injected by ../includes/online.php, included below:
 * @var int|string $bruger_id
 * @var string $brugernavn
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}
$s_id    = session_id();
$title   = 'Bankforbindelse';
$modulnr = 1;
$header  = 'nix';
$css     = '../css/standard.css';
ob_start();

include(__DIR__ . '/../includes/connect.php');
include(__DIR__ . '/../includes/online.php');
include(__DIR__ . '/../includes/std_func.php');
require_once __DIR__ . '/includes/ebAccess.php';

// ---------------------------------------------------------------------
// Controller
// ---------------------------------------------------------------------
$state     = is_string($_GET['state'] ?? null) ? trim($_GET['state']) : '';
$code      = is_string($_GET['code'] ?? null) ? trim($_GET['code']) : '';
$bankError = is_string($_GET['error'] ?? null) ? trim($_GET['error']) : '';
$message   = '';

$unavailable = ebUnavailableReason();
if ($unavailable !== null) {
	$message = $unavailable;
} elseif (!ebCanManage()) {
	$message = ebT(findtekst('bank.connect.no_right|Du har ikke adgang til at oprette eller forny bankforbindelser. Det kræver adgang til Indstillinger.', ebLang()));
} elseif ($bankError !== '') {
	// Burn the state so the same link cannot be completed later; its own errors do not matter here.
	if ($state !== '') {
		try {
			ebConsumeAuthorization($state, (int)$bruger_id);
		} catch (Throwable $e) {
			// The bank already reported the real problem.
		}
	}
	if ($bankError === 'access_denied') {
		$message = ebT(findtekst('bank.callback.cancelled|Godkendelsen blev annulleret i banken. Der er ikke oprettet nogen forbindelse.', ebLang()));
	} else {
		$errorCode = preg_replace('/[^a-z0-9_]/', '', strtolower($bankError)) ?? '';
		$message   = ebErrorText($errorCode, null, 'authorization');
	}
} elseif ($state === '' || $code === '') {
	$message = ebT(findtekst('bank.callback.incomplete|Linket fra banken er ufuldstændigt. Start forbindelsen forfra.', ebLang()));
} else {
	try {
		$stateRow     = ebConsumeAuthorization($state, (int)$bruger_id);
		$client       = new EnableBankingClient(ebConfig());
		session_write_close();
		$connectionId = ebCompleteAuthorization($client, $stateRow, $code, (string)$brugernavn);
		$bankName     = (string)($stateRow['aspsp_name'] ?? '') ?: ebT(findtekst('bank.feed.the_bank|banken', ebLang()));
		if (($stateRow['purpose'] ?? '') === 'reconnect') {
			ebFlash('ok', sprintf(ebT(findtekst('bank.callback.renewed|Adgangen til %s er fornyet. Hentningen fortsætter, hvor den slap.', ebLang())), $bankName));
		} else {
			ebFlash('ok', sprintf(ebT(findtekst('bank.callback.connected|Forbindelsen til %s er oprettet. Vælg bogføringskonto og startdato for hver bankkonto nedenfor.', ebLang())), $bankName));
		}
		ebRedirect('accounts.php?connection=' . (int)$connectionId);
	} catch (Throwable $e) {
		$message = ebErrorMessage($e, 'authorization');
	}
}

// ---------------------------------------------------------------------
// View (only reached when something went wrong)
// ---------------------------------------------------------------------
include(__DIR__ . '/includes/page_header.php');
print ebRenderTopline(ebT(findtekst('bank.journal.button|Bankforbindelse', ebLang())), '../systemdata/diverse.php?sektion=bank_integration');
print "<div class='content-noside'>\n";
print ebRenderFlash();
print "<div class='eb-panel'><h2>" . ebHT(findtekst('bank.callback.failed|Forbindelsen blev ikke oprettet', ebLang())) . "</h2>";
print "<p>" . ebMessageHtml($message) . "</p>";
print "<div class='eb-actions'>";
if ($unavailable === null && ebCanManage()) {
	print "<a class='eb-btn' href='connect.php'>" . ebHT(findtekst('bank.connect.try_again|Prøv igen', ebLang())) . "</a>";
}
print "<a class='eb-btn secondary' href='../systemdata/diverse.php?sektion=bank_integration'>" . ebHT(findtekst('bank.callback.to_settings|Til indstillinger', ebLang())) . "</a>";
print "</div></div>\n";
print "</div>\n</body>\n</html>\n";
