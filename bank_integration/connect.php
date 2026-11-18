<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/connect.php --- ver 5.1.1 --- 2026-11-18 ---
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
// 20261006 CL/LH Initial version. Choose a Danish bank (Enable Banking ASPSP list) and start the bank login, or renew access for an existing connection (POST purpose=reconnect).
// 20261007 CDX/LH Released session locks before bank requests and rendered support codes separately.
// 20261028 CL/LH Explain that Enable Banking Oy fetches read-only account data on Saldi's behalf, link the privacy policy and describe manual picking of fetched lines.
// 20261028 CDX/LH Report connect and renewal failures as authorization errors with safe support codes.
// 20261028 CL/LH Singular/plural for active connections and an en dash in the read-only note.
// 20261116 CL/LH Texts through findtekst() (bank.connect.*); the lines go into a kassekladde from its Bankforbindelse button.
// 20261118 CL/LH The privacy policy link opens the published PDF (saldi.dk/assets/dokumenter/privatlivspolitik.pdf); saldi.dk/privatlivspolitik is a 404.
// 20261118 CL/LH The bank choice posts as the whole window (target _top), since banks refuse to be shown in Saldi's frame; errors return into the frame layout (ebShellRedirect()).

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

const EB_PSU_LABELS = array('business' => true, 'personal' => true);

/**
 * Banks from the provider, sorted by name, with only the PSU types Saldi supports.
 *
 * @param EnableBankingClient $client Provider client.
 * @return array<int, array{name: string, country: string, psu_types: array<int, string>, beta: bool}>
 */
function ebConnectBankList(EnableBankingClient $client): array {
	$banks = array();
	foreach ($client->aspsps('DK') as $aspsp) {
		if (!is_array($aspsp) || trim((string)($aspsp['name'] ?? '')) === '') {
			continue;
		}
		$types = array();
		foreach ((array)($aspsp['psu_types'] ?? array()) as $type) {
			if (isset(EB_PSU_LABELS[$type])) {
				$types[] = (string)$type;
			}
		}
		if (!$types) {
			continue;
		}
		$banks[] = array(
			'name'      => (string)$aspsp['name'],
			'country'   => (string)($aspsp['country'] ?? 'DK'),
			'psu_types' => $types,
			'beta'      => !empty($aspsp['beta']),
		);
	}
	usort($banks, fn($a, $b) => strcasecmp($a['name'], $b['name']));
	return $banks;
}

// ---------------------------------------------------------------------
// Controller
// ---------------------------------------------------------------------
$unavailable = ebUnavailableReason();
$canManage   = ebCanManage();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	ebCsrfCheck();
	$purpose       = (($_POST['purpose'] ?? '') === 'reconnect') ? 'reconnect' : 'connect';
	$returnAccount = (int)($_POST['return_account'] ?? 0);
	$errorTarget   = $returnAccount > 0 ? 'feed.php?account=' . $returnAccount : 'connect.php';
	// The form posts as the whole window (target _top), so every way back goes through the frame layout.
	if ($unavailable !== null) {
		ebFlash('error', $unavailable);
		ebShellRedirect($errorTarget);
	}
	if (!$canManage) {
		ebFlash('error', ebT(findtekst('bank.connect.no_right|Du har ikke adgang til at oprette eller forny bankforbindelser. Det kræver adgang til Indstillinger.', ebLang())));
		ebShellRedirect($errorTarget);
	}
	session_write_close();
	try {
		$client       = new EnableBankingClient(ebConfig());
		$connectionId = null;
		if ($purpose === 'reconnect') {
			$connectionId = (int)($_POST['connection'] ?? 0);
			$bankConnection   = ebIndexById(ebListConnections())[$connectionId] ?? null;
			if (!$bankConnection) {
				ebFlash('error', ebT(findtekst('bank.connect.gone|Bankforbindelsen findes ikke længere. Opret en ny forbindelse.', ebLang())));
				ebShellRedirect($errorTarget);
			}
			// The bank is taken from the stored connection, never from the form.
			$aspspName = (string)($bankConnection['aspsp_name'] ?? '');
			$country   = (string)(($bankConnection['aspsp_country'] ?? '') ?: 'DK');
			$psuType   = (string)($bankConnection['psu_type'] ?? '');
		} else {
			$aspspName = trim((string)($_POST['aspsp'] ?? ''));
			$psuType   = (string)($_POST['psu_type'] ?? '');
			$country   = 'DK';
			$chosen    = null;
			foreach (ebConnectBankList($client) as $bank) {
				if ($bank['name'] === $aspspName && in_array($psuType, $bank['psu_types'], true)) {
					$chosen = $bank;
					break;
				}
			}
			if (!$chosen) {
				ebFlash('error', ebT(findtekst('bank.connect.choose_bank|Vælg en bank og kontotype fra listen.', ebLang())));
				ebShellRedirect('connect.php');
			}
			$country = $chosen['country'];
		}
		$url = ebStartAuthorization($client, $aspspName, $country, $psuType, $purpose, $connectionId, (int)$bruger_id, (string)$brugernavn);
		if (!preg_match('#^https://#i', $url)) {
			ebFlash('error', ebT(findtekst('bank.connect.bad_url|Banken returnerede ikke en gyldig login-adresse. Prøv igen senere.', ebLang())));
			ebShellRedirect($errorTarget);
		}
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		header('Location: ' . $url, true, 302);
		exit;
	} catch (Throwable $e) {
		ebFlash('error', ebErrorMessage($e, 'authorization'));
		ebShellRedirect($errorTarget);
	}
}

$banks       = array();
$loadError   = '';
$connections = array();
$environment = '';
if ($unavailable === null && $canManage) {
	try {
		$config      = ebConfig();
		$environment = (string)($config['environment'] ?? '');
		$connections = ebListConnections();
		ebCsrfToken();
		session_write_close();
		$banks       = ebConnectBankList(new EnableBankingClient($config));
	} catch (Throwable $e) {
		$loadError = ebErrorMessage($e, 'authorization');
	}
}
$activeConnections = array_values(array_filter($connections, fn($c) => ($c['status'] ?? '') === 'active'));

// ---------------------------------------------------------------------
// View
// ---------------------------------------------------------------------
include(__DIR__ . '/includes/page_header.php');
print ebRenderTopline(ebT(findtekst('bank.connect.title|Bankforbindelse - vælg bank', ebLang())), '../systemdata/diverse.php?sektion=bank_integration');
print "<div class='content-noside'>\n";
print ebRenderFlash();

if ($unavailable !== null) {
	print "<div class='eb-panel'><p>" . ebH($unavailable) . "</p></div>\n";
} elseif (!$canManage) {
	print "<div class='eb-panel'><p>" . ebH(ebT(findtekst('bank.connect.no_right|Du har ikke adgang til at oprette eller forny bankforbindelser. Det kræver adgang til Indstillinger.', ebLang()))) . "</p></div>\n";
} else {
	print "<div class='eb-panel'>";
	print "<h2>" . ebHT(findtekst('bank.connect.heading|Forbind en bankkonto', ebLang())) . "</h2>";
	print "<p>" . ebHT(findtekst('bank.connect.intro|Vælg din bank. Du sendes videre til bankens eget login, hvor du giver Saldi læseadgang til dine konti. Saldi kan kun læse posteringer – der kan ikke flyttes penge.', ebLang())) . "</p>";
	print "<p>" . ebHT(findtekst('bank.connect.provider|Forbindelsen går gennem Enable Banking Oy, som er en godkendt udbyder af kontooplysninger. Enable Banking henter på Saldis vegne posteringer fra de konti, du giver adgang til i banken, og kan kun læse dem.', ebLang()));
	print " <a href='https://saldi.dk/assets/dokumenter/privatlivspolitik.pdf' target='_blank' rel='noopener'>" . ebHT(findtekst('bank.connect.privacy|Læs mere i Saldis privatlivspolitik', ebLang())) . "</a>.</p>";
	print "<p class='eb-muted'>" . ebHT(findtekst('bank.connect.after|Bagefter vælger du, hvilken konto i kontoplanen hver bankkonto skal bogføres på, og fra hvilken dato posteringerne skal hentes. Du lægger de hentede posteringer i en kassekladde fra kassekladden med knappen Bankforbindelse. Kun konti i danske kroner (DKK) kan importeres.', ebLang())) . "</p>";
	if ($environment === 'SANDBOX') {
		print "<p><span class='eb-badge warn'>" . ebHT(findtekst('bank.settings.sandbox|Testmiljø (sandbox)', ebLang())) . "</span> <span class='eb-muted'>" . ebHT(findtekst('bank.connect.sandbox_note|Forbindelser her bruger bankernes testdata, ikke rigtige konti.', ebLang())) . "</span></p>";
	}
	if ($activeConnections) {
		$activeCount = count($activeConnections);
		print "<p class='eb-muted'>" . ebH($activeCount === 1 ? ebT(findtekst('bank.connect.active_one|Du har allerede 1 aktiv forbindelse.', ebLang())) : sprintf(ebT(findtekst('bank.connect.active_many|Du har allerede %s aktive forbindelser.', ebLang())), $activeCount))
			. " <a href='accounts.php'>" . ebHT(findtekst('bank.connect.see_accounts|Se tilknyttede konti', ebLang())) . "</a>.</p>";
	}
	print "</div>\n";

	if ($loadError !== '') {
		print "<div class='eb-flash eb-flash-error'>" . ebHT(findtekst('bank.connect.list_failed|Banklisten kunne ikke hentes:', ebLang())) . " " . ebMessageHtml($loadError) . "</div>";
		print "<p><a class='eb-btn secondary' href='connect.php'>" . ebHT(findtekst('bank.connect.try_again|Prøv igen', ebLang())) . "</a></p>\n";
	} elseif (!$banks) {
		print "<div class='eb-panel'><p>" . ebHT(findtekst('bank.connect.no_banks|Der er ingen banker at vælge imellem lige nu. Prøv igen senere.', ebLang())) . "</p></div>\n";
	} else {
		print "<div class='eb-panel'>";
		print "<div class='eb-form-row' style='margin-bottom:8px'><label>" . ebHT(findtekst('bank.connect.search|Søg efter bank', ebLang())) . "<input type='text' id='eb-bank-search' autocomplete='off' placeholder='" . ebHT(findtekst('bank.connect.search_placeholder|Fx Danske Bank', ebLang())) . "'></label></div>";
		print "<table class='eb-table' id='eb-bank-table'><thead><tr><th>" . ebHT(findtekst('bank.feed.bank|Bank', ebLang())) . "</th><th>" . ebHT(findtekst('bank.connect.connect_as|Forbind som', ebLang())) . "</th></tr></thead><tbody>";
		foreach ($banks as $bank) {
			print "<tr data-name='" . ebH(mb_strtolower($bank['name'])) . "'><td>" . ebH($bank['name']);
			if ($bank['beta']) {
				print " <span class='eb-badge muted' title='" . ebHT(findtekst('bank.connect.beta_title|Banken er ny hos udbyderen og kan opføre sig ustabilt', ebLang())) . "'>beta</span>";
			}
			print "</td><td class='nowrap'>";
			foreach ($bank['psu_types'] as $type) {
				print "<form method='post' action='connect.php' class='eb-inline' target='_top'>" . ebCsrfField();
				print "<input type='hidden' name='purpose' value='connect'>";
				print "<input type='hidden' name='aspsp' value='" . ebH($bank['name']) . "'>";
				print "<input type='hidden' name='psu_type' value='" . ebH($type) . "'>";
				print "<button type='submit' class='eb-btn small" . ($type === 'personal' ? ' secondary' : '') . "'>" . ($type === 'personal' ? ebHT(findtekst('bank.connect.personal|Privat', ebLang())) : ebHT(findtekst('bank.connect.business|Erhverv', ebLang()))) . "</button> ";
				print "</form>";
			}
			print "</td></tr>\n";
		}
		print "</tbody></table>";
		print "<p class='eb-muted'>" . ebHT(findtekst('bank.connect.psu_hint|Vælg Erhverv for virksomhedens konti (netbank erhverv) og Privat for en privat netbank.', ebLang())) . "</p>";
		print "</div>\n";
		?>
<script>
(function () {
	var input = document.getElementById('eb-bank-search');
	var rows = document.querySelectorAll('#eb-bank-table tbody tr');
	input.addEventListener('input', function () {
		var q = input.value.trim().toLowerCase();
		rows.forEach(function (row) {
			row.style.display = (q === '' || row.getAttribute('data-name').indexOf(q) !== -1) ? '' : 'none';
		});
	});
})();
</script>
		<?php
	}
}
print "</div>\n</body>\n</html>\n";
