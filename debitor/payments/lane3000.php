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

// Add logging function
function writeLog($message, $level = 'INFO') {
    $logFile = 'lane3000_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

$raw_amount = (float) usdecimal(if_isset($_GET['amount'], 0));
$pretty_amount = dkdecimal($raw_amount, 2);
$ordre_id    = if_isset($_GET['id'], 0);
$indbetaling = if_isset($_GET['indbetaling'], 0);
$kasse = $_COOKIE['saldi_pos'];

// Log initialization
writeLog("Lane3000 payment started - Amount: $raw_amount, Order ID: $ordre_id, Kasse: $kasse, Session: $s_id");

print "<div id='container'>";
print "<span>Lane3000 terminal startet, afventer kort.</span>";
print "<h3>$pretty_amount kr.</h3>";
print "<div id='status' style='background-color: #fbbc04' >Afventer kort...</div>";
print "<span>Terminalen timer ud om </span><span id='timestatus'>120</span><span> sekunder</span><br>";
print "<button id='continue' class='btn' onClick='failed();' disabled style='display: block'>Tilbage</button>";
print "<button id='continue-success' class='btn' onClick='successed();'>Tilbage</button>";
print "</div>";
print "<div id='bg'></div>";

$type = ($raw_amount < 0) ? "returnOfGoods" : "purchase";
$amount = abs($raw_amount) * 100;

writeLog("Transaction type: $type, Amount in cents: $amount");

# Get settings
$q=db_select("select var_value from settings where var_name = 'flatpay_auth'",__FILE__ . " linje " . __LINE__);
$guid = db_fetch_array($q)[0];

# Get terminal id
$qtxt = "SELECT box4 FROM grupper WHERE beskrivelse = 'Pos valg' AND kodenr = '2' and fiscal_year = '$regnaar'";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
$terminal_id = explode(chr(9),db_fetch_array($q)[0])[$kasse-1];

writeLog("Terminal ID retrieved: $terminal_id");

# Print setup
$printfile = 'https://'.$_SERVER['SERVER_NAME'];
$printfile.= str_replace('debitor/payments/lane3000.php',"temp/$db/receipt_$kasse.txt",$_SERVER['PHP_SELF']);

writeLog("Print file URL: $printfile");
?>

<script>

var counting = false;

// Add client-side logging function
function logToServer(message, level = 'INFO') {
    fetch('log_lane3000.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            message: message,
            level: level,
            timestamp: new Date().toISOString(),
            ordre_id: '<?php print $ordre_id; ?>'
        })
    }).catch(err => console.error('Logging failed:', err));
}

function countdown(i) {
    document.getElementById("timestatus").innerText = i;
    if (i != 0 && counting) {
        setTimeout(() => {
            countdown(i-1);
        }, 1000);
    } else if (i === 0) {
        logToServer('Countdown reached zero - timeout', 'WARNING');
    }
}

const failed = (event) => {
    logToServer('User clicked failed/back button', 'INFO');
    window.location.replace('../pos_ordre.php?id=<?php print $ordre_id; ?>&godkendt=afvist')
}

// Variable used to check weather or not to leave the page
var finished = false;

// FAIL CONDITION
function fail(err) {
    logToServer(`Payment failed: ${err}`, 'ERROR');
    var elm = document.getElementById('status');
    elm.style.backgroundColor = '#ea3a3a';
    elm.innerText = `Fejl: ${err}`;
    document.getElementById('bg').style.backgroundColor = '#fb9389';
    document.getElementById('continue').style.display = 'block';
    document.getElementById('continue').disabled = false;
}

function leave(cardScheme) {
    if (!finished) {
        setTimeout(function() { leave(cardScheme); }, 2500);
    } else {
        logToServer(`Payment successful, redirecting with card scheme: ${cardScheme}`, 'INFO');
        window.location.replace(`../pos_ordre.php?id=<?php print $ordre_id; ?>&godkendt=OK&indbetaling=<?php print $indbetaling; ?>&amount=<?php print $raw_amount; ?>&cardscheme=${cardScheme}`);
    }
}

// GET API KEY
async function get_api_key(baseurl) {
    logToServer('Starting API key request', 'INFO');
    document.getElementById('status').innerText = "Authorizer...";
    const data = {
        "username": "<?php print get_settings_value("username", "move3500", "");?>",
        "password": "<?php print get_settings_value("password", "move3500", "");?>"
    }
    
    try {
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

        var jsondata = await res.json();
        
        logToServer(`API key request response - Status: ${res.status}, Data: ${JSON.stringify(jsondata)}`, 'INFO');

        if (res.status != 200) {
            logToServer(`API key request failed - Status: ${res.status}, Error: ${jsondata.error}`, 'ERROR');
            fail(jsondata.error);
            return null;
        }

        logToServer('API key retrieved successfully', 'INFO');
        return jsondata.token;
    } catch (error) {
        logToServer(`API key request exception: ${error.message}`, 'ERROR');
        fail(`Network error: ${error.message}`);
        return null;
    }
}

async function print_str(baseurl, apikey, data) {
    logToServer('Starting receipt printing', 'INFO');
    document.getElementById('status').innerText = "Printer...";
    
    try {
        await fetch(
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
        
        logToServer('Receipt saved successfully', 'INFO');
        window.open("http://localhost/saldiprint.php?bruger_id=99&bonantal=1&printfil=<?php print $printfile; ?>&skuffe=0&gem=1','','width=200,height=100");
        logToServer('Print command sent', 'INFO');
        finished = true;
    } catch (error) {
        logToServer(`Receipt printing failed: ${error.message}`, 'ERROR');
        // Continue anyway, don't fail the whole transaction
        finished = true;
    }
}

// START PAYMENT ON TERMINAL
async function start_payment(baseurl, apikey, amount) {
    logToServer(`Starting payment on terminal - Amount: ${amount}`, 'INFO');
    const data = {
        "transactionType": "<?php print $type; ?>",
        "amount": <?php print $amount; ?>
    }
    
    try {
        var res = await fetch(
            `${baseurl}terminal/<?php print $terminal_id; ?>/transaction`,
            {
                method: 'post',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `bearer ${apikey}`
                },
                body: JSON.stringify(data),
            }
        );

        counting = false;
        var jsondata = await res.json();
        
        logToServer(`Payment response - Status: ${res.status}, Data: ${JSON.stringify(jsondata)}`, 'INFO');
        
        if (res.status != 201) {
            logToServer(`Payment failed - Status: ${res.status}, Error: ${jsondata.failure?.error || 'Unknown error'}`, 'ERROR');
            fail(jsondata.failure?.error || 'Payment failed');
        } else {
            const cardScheme = jsondata.result[0].cardType;
            logToServer(`Payment successful - Card type: ${cardScheme}`, 'INFO');
            
            jsondata.result[0].customerReceipt.replace("\r", "");
            var lines = jsondata.result[0].customerReceipt.split("\r\n");
            lines = lines.join("\n");

            if (true) {
                await print_str(baseurl, apikey, lines);
            } else {
                finished = true;
            }
            
            leave(cardScheme);
        }
    } catch (error) {
        logToServer(`Payment request exception: ${error.message}`, 'ERROR');
        fail(`Network error: ${error.message}`);
    }
}

async function start() {
    logToServer('Payment process started', 'INFO');
    const baseurl = "https://connectcloud.aws.nets.eu/v1/";
    var elm = document.getElementById('status');

    const apikey = await get_api_key(baseurl);
    if (!apikey || elm.innerText.includes("Fejl:")) {
        logToServer('Failed to get API key, stopping process', 'ERROR');
        return;
    }
    
    counting = true;
    countdown(121);
    document.getElementById('status').innerText = "Afventer kort...";
    await start_payment(baseurl, apikey, <?php print $amount; ?>);
    if (elm.innerText.includes("Fejl:")) {
        logToServer('Payment process completed with error', 'ERROR');
        return;
    }
    
    logToServer('Payment process completed successfully', 'INFO');
}

logToServer('Page loaded, starting payment process', 'INFO');
start();

</script>