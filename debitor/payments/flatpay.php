<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- payments/flatpay.php --- lap 4.1.1 --- 2025.09.16 ---
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
// Copyright (c) 2024-2025 saldi.dk aps
// ----------------------------------------------------------------------
// 20240209 PHR Added indbetaling
// 20240227 PHR Added $printfile and call to saldiprint.php
// 20250512 Updated to use new Flatpay API endpoints
// 20250523 printserver lookup
// 20250531 PHR added $flatpayPrint
// 20250912 PHR added print if canceled

@session_start();
$s_id = session_id();

$css = "../../css/flatpay.css";

include ("../../includes/connect.php");
include ("../../includes/online.php");
include ("../../includes/std_func.php");
include ("../../includes/stdFunc/dkDecimal.php");
include ("../../includes/stdFunc/usDecimal.php");

$raw_amount = (float) usdecimal(if_isset($_GET['amount'], 0));
$pretty_amount = dkdecimal($raw_amount, 2);
$ordre_id = if_isset($_GET['id'], 0);
$indbetaling = if_isset($_GET['indbetaling'], 0);
$kasse = $_COOKIE['saldi_pos'];

print "<div id='container'>";
print "<span>Flatpay terminal startet, afventer kort.</span>";
print "<h3>$pretty_amount kr.</h3>";
print "<div id='status' style='background-color: #fbbc04' >Afventer kort...</div>";
print "<span>Terminalen timer ud om </span><span id='timestatus'>120</span><span> sekunder</span><br>";
print "<button id='continue' class='btn' onClick='failed();' disabled style='display: block'>Tilbage</button>";
print "<button id='continue-success' class='btn' onClick='successed();'>Tilbage</button>";
print "</div>";
print "<div id='bg'></div>";

// Fetch GUID from settings
$q = db_select("select var_value from settings where var_name = 'flatpay_auth'", __FILE__ . " linje " . __LINE__);
$guid = db_fetch_array($q)[0];

$q = db_select("select var_value from settings where var_name = 'flatpay_print'", __FILE__ . " linje " . __LINE__);
$flatpayPrint = db_fetch_array($q)[0];

$q = db_select("select var_value from settings where var_name = 'flatpay_terminal_print'", __FILE__ . " linje " . __LINE__);
$terminal_print = db_fetch_array($q)[0];

if($terminal_print == 0){
  $terminal_print = true;
} else {
  $terminal_print = false;
}

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

// Get terminal ID
$qtxt = "SELECT box4 FROM grupper WHERE beskrivelse = 'Pos valg' AND kodenr = '2' and fiscal_year = '$regnaar'";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
$terminal_id = explode(chr(9), db_fetch_array($q)[0])[$kasse-1];

// Prepare transaction type and amount
$type = ($raw_amount < 0) ? "REFUND" : "SALE";
$amount = (string)(abs($raw_amount) * 100); // Convert to pennies as string

// Prepare printfile
if (file_exists("../../temp/$db/receipt_$kasse.txt")) unlink("../../temp/$db/receipt_$kasse.txt");
if ($flatpayPrint) {
  $printfile = 'https://'.$_SERVER['SERVER_NAME'];
  $printfile.= str_replace('debitor/payments/flatpay.php', "temp/$db/receipt_$kasse.txt", $_SERVER['PHP_SELF']);
} else $printfile = NULL;

print "
<script>
  // Generate a unique transaction reference (UUID)
  function generateUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      var r = Math.random() * 16 | 0, 
          v = c == 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }

  const transactionData = {
    terminalId: '$terminal_id',
    transactionType: '$type',
    amount: '$amount',
    guid: '$guid',
    disableTerminalPrints: $terminal_print,
    language: 'da_DK',
    reference: '$ordre_id',
    externalReference: '$ordre_id',
    transactionReference: generateUUID()
  };

  // Transaction state variables
  let cardScheme = 'unknown';
  let count = 120 - 1;
  let paused = false;

  // Success callback
  const successed = (event) => {
    console.log('Transaction successful with card scheme:', cardScheme);
    window.location.replace(`../pos_ordre.php?id=$ordre_id&godkendt=OK&indbetaling=$indbetaling&amount=$raw_amount&cardscheme=\${cardScheme}`);
  };

  // Failure callback
  const failed = (event) => {
    console.log('Transaction failed');
    window.location.replace('../pos_ordre.php?id=$ordre_id&godkendt=afvist');
  };

  // Countdown function for auto-redirect after success
  function countdown(i) {
    setTimeout(() => {
      document.getElementById('continue-success').textContent = `Tilbage (\${i})`;
      console.log(`Tilbage (\${i})`);
      if (i == 0) {
        successed(null);
      } else {
        countdown(i-1);
      }
    }, 1000);
  }
  
  // Countdown function for timeout
  function count_down() {
    setTimeout(() => {
      if (paused) {
        count_down();
        return;
      }
      document.getElementById('timestatus').innerText = count;  
      count--;
      if (count == 0) {
        cancelTransaction();
      }
      if (count != -1) {
        count_down();
      }
    }, 1000);
  }

  // Cancel transaction
  async function cancelTransaction() {
    try {
      const cancelResponse = await fetch('https://socket-api.flatpay.dk/socket/transaction/cancel', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Basic ' + btoa('$guid')
        },
        body: JSON.stringify({
          transactionReference: transactionData.transactionReference,
          terminalId: transactionData.terminalId,
          guid: transactionData.guid
        })
      });
      
      const result = await cancelResponse.json();
      console.log('Transaction cancelled:', result);
    } catch (error) {
      console.error('Error cancelling transaction:', error);
    }
    
    updateUIForFailure('Timeout: Transaktion annulleret');
  }

  // Start transaction
  async function startTransaction() {
    try {
      // Start transaction
      const startResponse = await fetch('https://socket-api.flatpay.dk/socket/transaction/start', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Basic ' + btoa('$guid')
        },
        body: JSON.stringify(transactionData)
      });
      
      if (!startResponse.ok) {
        const errorText = await startResponse.text();
        throw new Error(`Transaction start failed: \${errorText}`);
      }
      
      const startResult = await startResponse.json();
      console.log('Transaction started:', startResult);
      
      // Poll for transaction response
      await pollTransactionResponse();
      
    } catch (error) {
      console.error('Error starting transaction:', error);
      updateUIForFailure(`Fejl: \${error.message}`);
    }
  }

  // Poll transaction response
  async function pollTransactionResponse() {
    try {
      let completed = false;
      
      while (!completed && count > 0) {
        const responseResponse = await fetch(
          `https://socket-api.flatpay.dk/socket/transaction/response?transactionReference=\${transactionData.transactionReference}&guid=$guid&terminalId=$terminal_id`, 
          {
            method: 'GET',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': 'Basic ' + btoa('$guid')
            }
          }
        );
        
        if (!responseResponse.ok) {
          const errorText = await responseResponse.text();
          throw new Error(`Transaction response failed: \${errorText}`);
        }
        
        const transaction = await responseResponse.json();
        console.log('Transaction response:', transaction);
        
        // Check transaction status
        if (transaction.status === 'COMPLETED') {
          completed = true;
          paused = true;
          
          // Extract card scheme
          cardScheme = transaction.cardScheme || 'unknown';
          
          // Save receipt data
          await fetch('save_receipt.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              data: transaction, 
              id: '$ordre_id',
              type: 'flatpay',
              terminal_id: '$terminal_id'
            })
          });
          
          // Print receipt if printfile is specified
          if ('$printfile' !== '') {
            window.open(\"http://$printserver/saldiprint.php?bruger_id=99&bonantal=1&printfil=$printfile&skuffe=0&gem=1\", '', 'width=200,height=100');
          }
          
          // Update UI for success
          updateUIForSuccess();
          
        } else if (['CANCELLED', 'DECLINED', 'FAILED_CREATING'].includes(transaction.status)) {
          completed = true;
          paused = true;

          // tell the terminal to cancel the transaction
          await fetch('https://socket-api.flatpay.dk/socket/transaction/cancel', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              transactionReference: transactionData.transactionReference,
              terminalId: transactionData.terminalId,
              guid: transactionData.guid
            })
          });

          // Save receipt data
          await fetch('save_receipt.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              data: transaction,
              id: '$ordre_id',
              type: 'flatpay',
              terminal_id: '$terminal_id'
            })
          });

          // Print receipt if printfile is specified
          if ('$printfile' !== '') {
            window.open(\"http://$printserver/saldiprint.php?bruger_id=99&bonantal=1&printfil=$printfile&skuffe=0&gem=1\", '', 'width=200,height=100');
          }

          // Update UI for failure
          updateUIForFailure(`Fejl: \${transaction.status}`, transaction.errorText);
        }
        
        // If not completed, wait before polling again
        if (!completed) {
          await new Promise(resolve => setTimeout(resolve, 1000));
        }
      }
    } catch (error) {
      console.error('Error polling transaction response:', error);
      updateUIForFailure(`Fejl: \${error.message}`);
    }
  }

  // Update UI for successful transaction
  function updateUIForSuccess() {
    const elm = document.getElementById('status');
    elm.style.backgroundColor = '#51e87d';
    elm.innerText = 'Success';
    countdown(1);
    document.getElementById('continue-success').style.display = 'block';
    document.getElementById('continue').style.display = 'none';
  }

  // Update UI for failed transaction
  function updateUIForFailure(message, additionalDetails = '') {
    const elm = document.getElementById('status');
    elm.style.backgroundColor = '#ea3a3a';
    elm.innerText = message;
    if (additionalDetails) {
      elm.innerText += ` - \${additionalDetails}`;
    }
    document.getElementById('bg').style.backgroundColor = '#fb9389';
    document.getElementById('continue').style.display = 'block';
    document.getElementById('continue').disabled = false;
  }
  
  // Start the countdown
  count_down();
  
  // Start the transaction process
  startTransaction();
</script>
";
?>
