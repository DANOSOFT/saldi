<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- payments/lane3000_afstemning.php --- ver 5.0.0 --- 2026.10.07 ---
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
// Copyright (c) 2024-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20240209 PHR Added indbetaling
// 20240227 PHR Added $printfile and call to saldiprint.php
// 20260917 PHR Chaget ordre_id to 0 instead of 1000 if not set, as it returned to odrer 1000.
// 20261007 CL/SZ SST-843 The Nets Connect@Cloud username and password are no longer written into the page.
//                The login and the reconciliation run in lane3000_proxy.php, so the bearer token stays on the server too.

@session_start();
$s_id = session_id();

#print '<head>';
#print '<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400&display=swap" rel="stylesheet">';
#print '</head>';

$css = "../../css/flatpay.css";

include ("../../includes/connect.php");
include ("../../includes/online.php");
include ("../../includes/std_func.php");
include ("../../includes/stdFunc/dkDecimal.php");
include ("../../includes/stdFunc/usDecimal.php");

$raw_amount = (float) usdecimal(if_isset($_GET['amount'], 0));
$pretty_amount = dkdecimal($raw_amount, 2);
$ordre_id    = if_isset($_GET['id'], 0);
$indbetaling = if_isset($_GET['indbetaling'], 0);
$kasse = $_COOKIE['saldi_pos'];
print "<div id='container'>";
print "<span>Lane3000 terminal afstemmer.</span>";
print "<div id='status' style='background-color: #fbbc04' >Afventer kort...</div>";
print "<button id='continue' class='btn' onClick='failed();' disabled style='display: block'>Tilbage</button>";
print "<button id='continue-success' class='btn' onClick='successed();'>Tilbage</button>";
print "</div>";
print "<div id='bg'></div>";

$type = ($raw_amount < 0) ? "returnOfGoods" : "purchase";
$amount = abs($raw_amount) * 100;

// Fetch printserver
$r = db_fetch_array(db_select("select box3 from grupper where art = 'POS' and kodenr='2' and fiscal_year = '$regnaar'", __FILE__ . " linje " . __LINE__));
$x = $kasse - 1;
$tmp = explode(chr(9), $r['box3']);
$printserver = trim($tmp[$x]);
if (!$printserver) $printserver = 'localhost';
elseif ($printserver == 'box' || $printserver == 'saldibox') {
	$filnavn = "http://saldi.dk/kasse/" . $_SERVER['REMOTE_ADDR'] . ".ip";
	if ($fp = fopen($filnavn, 'r')) {
		$printserver = trim(fgets($fp));
		fclose($fp);
	}
}

# Get settings
$q=db_select("select var_value from settings where var_name = 'flatpay_auth'",__FILE__ . " linje " . __LINE__);
$guid = db_fetch_array($q)[0];

# Get terminal id
$qtxt = "SELECT box4 FROM grupper WHERE beskrivelse = 'Pos valg' AND kodenr = '2' and fiscal_year = '$regnaar'";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
$terminal_id = explode(chr(9),db_fetch_array($q)[0])[$kasse-1];

# Print setup
$printfile = 'https://'.$_SERVER['SERVER_NAME'];
$printfile.= str_replace('debitor/payments/lane3000_afstemning.php',"temp/$db/receipt_$kasse.txt",$_SERVER['PHP_SELF']);

# Session-bound token that lane3000_proxy.php checks; the Nets login itself is read there
if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];
?>

<script>

var counting = false;
var finished = false;

function countdown(i) {
    document.getElementById("timestatus").innerText = i;
    if (i != 0 && counting) {
        setTimeout(() => {
            countdown(i-1);
        }, 1000);
    }
}

const failed = (event) => {
    window.location.replace('../pos_ordre.php?id=<?php print $ordre_id; ?>&godkendt=afvist')
}

// FAIL CONDITION
function fail(err) {
    console.error('Error occurred:', err);
    var elm = document.getElementById('status');
    elm.style.backgroundColor = '#ea3a3a';
    elm.innerText = `Fejl: ${err}`;
    document.getElementById('bg').style.backgroundColor = '#fb9389';
    document.getElementById('continue').style.display = 'block';
    document.getElementById('continue').disabled = false;
    finished = true; // Prevent further execution
}

function leave() {
    if (!finished) {
        setTimeout(function() { leave(); }, 2500);
    } else {
        document.getElementById('continue').style.display = 'block';
        document.getElementById('continue').disabled = false;
    }
}

// Nets is called through lane3000_proxy.php, which logs in with this register's Connect@Cloud login
// and starts the reconciliation on the server. It answers with the terminal's own status and body; a failed
// login is marked with the X-Nets-Stage: login header.
function netsApi(action, params) {
    return fetch('lane3000_proxy.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?php print json_encode($csrf_token); ?>
        },
        body: JSON.stringify(Object.assign({ action: action, kasse: <?php print (int) $kasse; ?> }, params))
    });
}

async function print_str(data) {
    try {
        document.getElementById('status').innerText = "Printer...";
        
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
                    type: 'move3500'
                })
            }
        );
        
        if (!response.ok) {
            throw new Error(`Print forberedelse fejlede: ${response.status}`);
        }
        
        // Try to open print window
        const printWindow = window.open("http://<?php echo $printserver; ?>/saldiprint.php?bruger_id=99&bonantal=1&printfil=<?php print $printfile; ?>&skuffe=0&gem=1", '', 'width=200,height=100');
        
        if (!printWindow) {
            console.warn('Print vindue blev blokeret af browser');
        }
        
        finished = true;
    } catch (error) {
        fail(`Print fejl: ${error.message}`);
    }
}

// START afstemning ON TERMINAL
async function afstem() {
    try {
        const terminalId = "<?php print $terminal_id; ?>";
        if (!terminalId) {
            throw new Error("Terminal ID ikke fundet");
        }
        
        var res = await netsApi('reconciliation', {});

        if (res.headers.get('X-Nets-Stage') === 'login') {
            const loginError = await res.json().catch(() => ({}));
            fail(`Autorisation fejlede: ${loginError.error || res.status}`);
            return;
        }

        if (!res.ok) {
            const errorText = await res.text();
            throw new Error(`HTTP ${res.status}: ${errorText}`);
        }

        var jsondata = await res.json();
        
        if (jsondata.failure) {
            throw new Error(jsondata.failure.error || 'Ukendt fejl fra terminal');
        }
        
        if (!jsondata.result || !jsondata.result.reconciliation) {
            throw new Error('Ugyldig svar fra terminal');
        }
        
        console.log('Afstemning resultat:', jsondata);
        const lines = jsondata.result.reconciliation.printText?.Text || '';
        
        await print_str(lines);
        leave();
        
    } catch (error) {
        fail(`Afstemning fejlede: ${error.message}`);
    }
}

async function start() {
    try {
        document.getElementById('status').innerText = "Afstemmer...";
        await afstem();
        
    } catch (error) {
        fail(`Generel fejl: ${error.message}`);
    }
}

// Add error handler for unhandled promises
window.addEventListener('unhandledrejection', function(event) {
    console.error('Unhandled promise rejection:', event.reason);
    fail(`Uventet fejl: ${event.reason}`);
});

start();

</script>

