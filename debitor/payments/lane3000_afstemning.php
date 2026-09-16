<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- payments/flatpay.php --- lap 4.1.0 --- 2024.02.27 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2024-2024 saldi.dk aps
// ----------------------------------------------------------------------
// 20240209 PHR Added indbetaling
// 20240227 PHR Added $printfile and call to saldiprint.php
// 20260914 CDX/LH SST-788 Show reconciliation completion and preserve receipt/print failures.

/**
 * Injected by ../../includes/online.php, included below:
 * @var string $db
 * @var string $regnaar
 * @var int $sprog_id
 */

@session_start();
$s_id = session_id();

$css = "../../css/flatpay.css";
$modulnr = 5;

include (__DIR__ . "/../../includes/connect.php");
include (__DIR__ . "/../../includes/online.php");
include (__DIR__ . "/../../includes/std_func.php");

$ordre_id = intval($_GET['id'] ?? 1000);
$kasse = intval($_COOKIE['saldi_pos'] ?? 0);
$fiscalYear = intval($regnaar);

// Fetch printserver
$r = db_fetch_array(db_select("select box3 from grupper where art = 'POS' and kodenr='2' and fiscal_year = '$fiscalYear'", __FILE__ . " linje " . __LINE__));
$x = $kasse - 1;
$tmp = explode(chr(9), $r['box3'] ?? '');
$printserver = trim($tmp[$x] ?? '');
if (!$printserver) {
	$printserver = 'localhost';
} elseif ($printserver == 'box' || $printserver == 'saldibox') {
	$filnavn = "http://saldi.dk/kasse/" . $_SERVER['REMOTE_ADDR'] . ".ip";
	if ($fp = fopen($filnavn, 'r')) {
		$printserver = trim(fgets($fp));
		fclose($fp);
	}
}

# Get terminal id
$qtxt = "SELECT box4 FROM grupper WHERE beskrivelse = 'Pos valg' AND kodenr = '2' and fiscal_year = '$fiscalYear'";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
$terminal_id = explode(chr(9), db_fetch_array($q)[0] ?? '')[$kasse-1] ?? '';

# Print setup
$printfile = 'https://'.$_SERVER['SERVER_NAME'];
$printfile.= str_replace('debitor/payments/lane3000_afstemning.php',"temp/$db/receipt_$kasse.txt",$_SERVER['PHP_SELF']);
$messages = [
	'heading' => findtekst('5235|Lane3000 terminal afstemmer.', $sprog_id),
	'authorizing' => findtekst('5236|Autoriserer...', $sprog_id),
	'reconciling' => findtekst('5237|Afstemmer...', $sprog_id),
	'printing' => findtekst('5238|Printer...', $sprog_id),
	'completed' => findtekst('5239|Afstemning gennemført. Kvittering sendt til print.', $sprog_id),
	'popupBlocked' => findtekst('5240|Afstemning gennemført, men printvinduet blev blokeret. Tillad pop op-vinduer for at udskrive kvitteringen.', $sprog_id),
	'missingCredentials' => findtekst('5241|Manglende brugernavn eller adgangskode i indstillinger', $sprog_id),
	'missingToken' => findtekst('5242|Ingen token modtaget fra server', $sprog_id),
	'authFailed' => findtekst('5243|Autorisation fejlede', $sprog_id),
	'receiptFailed' => findtekst('5244|Printforberedelse fejlede', $sprog_id),
	'printFailed' => findtekst('5245|Print fejlede', $sprog_id),
	'missingApiKey' => findtekst('5246|Ingen API nøgle tilgængelig', $sprog_id),
	'missingTerminal' => findtekst('5247|Terminal ID ikke fundet', $sprog_id),
	'terminalError' => findtekst('5248|Ukendt fejl fra terminal', $sprog_id),
	'invalidResponse' => findtekst('5249|Ugyldigt svar fra terminal', $sprog_id),
	'missingReceipt' => findtekst('5250|Kvittering mangler i terminalens svar', $sprog_id),
	'reconciliationFailed' => findtekst('5251|Afstemning fejlede', $sprog_id),
	'unexpectedError' => findtekst('5252|Uventet fejl', $sprog_id),
	'back' => findtekst('30|Tilbage', $sprog_id),
	'error' => findtekst('3124|Fejl', $sprog_id),
];
?>

<div id="container">
    <span><?= htmlspecialchars($messages['heading'], ENT_QUOTES, 'UTF-8') ?></span>
    <div id="status" role="status" aria-live="polite" style="background-color: #fbbc04"><?= htmlspecialchars($messages['authorizing'], ENT_QUOTES, 'UTF-8') ?></div>
    <button id="continue" class="btn" onclick="failed();" disabled style="display: block"><?= htmlspecialchars($messages['back'], ENT_QUOTES, 'UTF-8') ?></button>
</div>
<div id="bg"></div>

<script>

const messages = <?= json_encode($messages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var finished = false;

const failed = () => {
    window.location.replace('../pos_ordre.php?id=<?php print $ordre_id; ?>&godkendt=afvist')
}

// FAIL CONDITION
function fail(err) {
    console.error('Error occurred:', err);
    var elm = document.getElementById('status');
    elm.style.backgroundColor = '#ea3a3a';
    elm.innerText = `${messages.error}: ${err}`;
    document.getElementById('bg').style.backgroundColor = '#fb9389';
    document.getElementById('continue').style.display = 'block';
    document.getElementById('continue').disabled = false;
    finished = true; // Prevent further execution
}

function complete(printWindow) {
    const status = document.getElementById('status');
    status.innerText = printWindow ? messages.completed : messages.popupBlocked;
    status.style.backgroundColor = printWindow ? '#34a853' : '#fbbc04';
    document.getElementById('bg').style.backgroundColor = printWindow ? '#b7e1cd' : '#fff2cc';
    document.getElementById('continue').disabled = false;
    finished = true;
}

// GET API KEY
async function get_api_key(baseurl) {
    try {
        document.getElementById('status').innerText = messages.authorizing;

        const username = <?= json_encode(get_settings_value("username", "move3500", "", null, $kasse), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const password = <?= json_encode(get_settings_value("password", "move3500", "", null, $kasse), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        if (!username || !password) {
            throw new Error(messages.missingCredentials);
        }

        const data = {
            "username": username,
            "password": password
        }

        var res = await fetch(
            `${baseurl}login`,
            {
                method: 'post',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            }
        );

        if (!res.ok) {
            const errorText = await res.text();
            throw new Error(`HTTP ${res.status}: ${errorText}`);
        }

        var jsondata = await res.json();

        if (!jsondata.token) {
            throw new Error(messages.missingToken);
        }

        return jsondata.token;
    } catch (error) {
        fail(`${messages.authFailed}: ${error.message}`);
        return null;
    }
}

async function print_str(data) {
    try {
        document.getElementById('status').innerText = messages.printing;

        const response = await fetch(
            'save_receipt.php',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    data: data,
                    id: '<?php print $ordre_id; ?>',
                    type: 'move3500',
                    confirm_saved: true
                })
            }
        );

        if (!response.ok) {
            throw new Error(`${messages.receiptFailed}: HTTP ${response.status}`);
        }

        const receipt = await response.json();
        if (receipt.saved !== true) {
            throw new Error(messages.receiptFailed);
        }

        // Opening the window confirms handoff, not physical printing.
        const printWindow = window.open(<?= json_encode("http://$printserver/saldiprint.php?bruger_id=99&bonantal=1&printfil=$printfile&skuffe=0&gem=1", JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, '', 'width=200,height=100');
        complete(printWindow);
    } catch (error) {
        fail(`${messages.printFailed}: ${error.message}`);
    }
}

// START afstemning ON TERMINAL
async function afstem(baseurl, apikey) {
    try {
        if (!apikey) {
            throw new Error(messages.missingApiKey);
        }

        const terminalId = <?= json_encode($terminal_id, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        if (!terminalId) {
            throw new Error(messages.missingTerminal);
        }

        const data = {
            "action": "reconciliation",
        }

        var res = await fetch(
            `${baseurl}terminal/${terminalId}/administration`,
            {
                method: 'post',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `bearer ${apikey}`
                },
                body: JSON.stringify(data),
            }
        );

        if (!res.ok) {
            const errorText = await res.text();
            throw new Error(`HTTP ${res.status}: ${errorText}`);
        }

        var jsondata = await res.json();

        if (jsondata.failure) {
            throw new Error(jsondata.failure.error || messages.terminalError);
        }

        if (!jsondata.result || !jsondata.result.reconciliation) {
            throw new Error(messages.invalidResponse);
        }

        const lines = jsondata.result.reconciliation.printText?.Text;
        if (typeof lines !== 'string' || !lines.trim()) {
            throw new Error(messages.missingReceipt);
        }

        await print_str(lines);

    } catch (error) {
        fail(`${messages.reconciliationFailed}: ${error.message}`);
    }
}

async function start() {
    try {
        const baseurl = "https://connectcloud.aws.nets.eu/v1/";

        const apikey = await get_api_key(baseurl);
        if (!apikey || finished) {
            return;
        }

        document.getElementById('status').innerText = messages.reconciling;
        await afstem(baseurl, apikey);

    } catch (error) {
        fail(`${messages.unexpectedError}: ${error.message}`);
    }
}

// Add error handler for unhandled promises
window.addEventListener('unhandledrejection', function(event) {
    console.error('Unhandled promise rejection:', event.reason);
    fail(`${messages.unexpectedError}: ${event.reason}`);
});

start();

</script>
