<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- payments/vibrant.php --- lap 4.1.0 --- 2024.02.09 ---
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
// 20240301 PHR Added $printfile and call to saldiprint.php

@session_start();
$s_id = session_id();

#print '<head>';
#print '<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400&display=swap" rel="stylesheet">';
#print '</head>';

$css = "../../css/flatpay.css";

include("../../includes/connect.php");
include("../../includes/online.php");
include("../../includes/std_func.php");
include("../../includes/stdFunc/dkDecimal.php");
include("../../includes/stdFunc/usDecimal.php");

$logentry = "--------------------------------------------------------\n";
$logentry .= "Date: " . date("Y-m-d H:i:s") . "\n";
$logentry .= "vibrant.php called with:\n";
foreach ($_GET as $key => $value) {
    $logentry .= "  $key = $value\n";
}
file_put_contents("../../temp/$db/vibrant.log", $logentry, FILE_APPEND);

$raw_amount = (float) usdecimal(if_isset($_GET['amount'], 0));
$pretty_amount = dkdecimal($raw_amount, 2);
$ordre_id = if_isset($_GET['id'], 0);
$kasse = $_COOKIE['saldi_pos'];
$indbetaling = if_isset($_GET['indbetaling'], 0);

# Get printserver
$qtxt = "select box3,box4,box5,box6 from grupper where art = 'POS' and kodenr='2' and fiscal_year = '$regnaar'";
$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
$x = $kasse - 1;
$tmp = explode(chr(9), $r['box3']);
$printserver = trim($tmp[$x]);

print '<meta name="viewport" content="width=device-width, initial-scale=1">';

print "<div id='container'>";
print "<span>Vibrant terminal startet, afventer kort.</span>";
print "<h3>$pretty_amount kr.</h3>";
print "<div id='status' style='background-color: #fbbc04' >Afventer kort...</div>";
print "<span>Terminalen timer ud om </span><span id='timestatus'>40</span><span> sekunder</span><br>";
print "<button id='continue' class='btn' onClick='failed();' disabled style='display: block'>Tilbage</button>";
print "<button id='continue-success' class='btn' onClick='successed();'>Tilbage</button>";
print "<button id='continue-error' class='btn' onClick='errored();' style='display: none'>Tilbage</button>";
print "</div>";
print "<div id='bg'></div>";

$type = ($raw_amount < 0) ? "process_refund" : "process_payment_intent";
$amount = abs($raw_amount) * 100;

if($type == "process_refund") {
  $query = db_select("SELECT betalings_id FROM ordrer WHERE id = $ordre_id", __FILE__ . " linje " . __LINE__);
  $row = db_fetch_array($query);
  $payment_id = $row['betalings_id'];
  // Also store receipt_id to extract chargeId if needed
  $receipt_parts = explode('-', isset($row['receipt_id']) ? $row['receipt_id'] : '');
  $charge_id = ($receipt_parts[0]) ? $receipt_parts[0] : '';
}
# Get settings
$q = db_select("select var_value from settings where var_name = 'vibrant_auth'", __FILE__ . " linje " . __LINE__);
$APIKEY = db_fetch_array($q)[0];

# $q=db_select("SELECT name, terminal_id FROM vibrant_terms WHERE pos_id=$kasse",__FILE__ . " linje " . __LINE__);
$q = db_select("SELECT var_value FROM settings WHERE pos_id=$kasse AND var_grp='vibrant_terms'", __FILE__ . " linje " . __LINE__);
$terminal_id = db_fetch_array($q)["var_value"];

if (!$terminal_id) {
  $qtxt = "SELECT box4 FROM grupper WHERE beskrivelse = 'Pos valg' AND kodenr = '2' and fiscal_year = '$regnaar'";
  $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
  $terminal_id = explode(chr(9), db_fetch_array($q)[0])[$kasse - 1];
}

if (file_exists("../../temp/$db/receipt_$kasse.txt"))
  unlink("../../temp/$db/receipt_$kasse.txt");
$printfile = 'https://' . $_SERVER['SERVER_NAME'];
$printfile .= str_replace('debitor/payments/vibrant.php', "temp/$db/receipt_$kasse.txt", $_SERVER['PHP_SELF']);

?>

<script>
  var count = 40 - 1
  var paused = false
  var receipt_id = 'None'
  var db = '<?php print $db; ?>'; // Pass db to JS

  function logToServer(msg) {
    const timestamp = new Date().toISOString();
    console.log(`[LOG] ${timestamp} ${msg}`);
    fetch('log_vibrant.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ db: db, message: msg })
    }).catch(err => console.error('Logging failed:', err));
  }

  logToServer(`Page loaded. Amount: <?php print $amount; ?>, Type: <?php print $type; ?>, OrdreID: <?php print $ordre_id; ?>`);

  const successed = (event) => {
    logToServer(`Successed called. CardScheme: ${cardScheme}`);
    console.log(cardScheme);
    window.location.replace(`../pos_ordre.php?id=<?php print $ordre_id; ?>&godkendt=OK&indbetaling=<?php print $indbetaling; ?>&amount=<?php print $raw_amount; ?>&cardscheme=${cardScheme}&receipt_id=${receipt_id}&payment_id=${payment_id}`);
  }

  const failed = (event) => {
    logToServer(`Failed called.`);
    console.log('Failed click');
    window.location.replace('../pos_ordre.php?id=<?php print $ordre_id; ?>&godkendt=afvist')
  }

  function countdown(i) {
    setTimeout(() => {
      document.getElementById('continue-success').textContent = `Tilbage (${i})`;
      console.log(`Tilbage (${i})`);
      if (i == 0) {
        successed(null);
      } else {
        countdown(i - 1);
      }
    }, 1000)
  }

  function count_down() {
    setTimeout(() => {
      if (paused) {
        count_down();
        return;
      }
      document.getElementById('timestatus').innerText = count;
      count--;
      if (count == 0) {
        setTimeout(() => {
          document.getElementById('continue').style.display = 'none';
          var elm = document.getElementById('continue-error');
          elm.style.display = 'block';
          var elm = document.getElementById('status');
          elm.style.backgroundColor = '#ea3a3a';
          elm.innerText = `Fejl: Intet svar fra terminalen, timeout`;
          document.getElementById('bg').style.backgroundColor = '#fb9389';
        }, 5000);
      }
      if (count != -1) {
        count_down();
      }
    }, 1000)
  }
 const type = "<?php print $type; ?>";
  if (type == 'process_refund') {
    console.log('=== REFUND DEBUGGING STARTED ===');
    console.log('Setting refund data');
    console.log('Order ID:', '<?php print $ordre_id; ?>');
    console.log('Amount:', <?php print $amount; ?>);
    console.log('Payment ID:', '<?php print $payment_id; ?>');
    console.log('Charge ID:', '<?php print ($charge_id) ? $charge_id : "N/A"; ?>');
    console.log('Terminal ID:', '<?php print $terminal_id; ?>');
    
    var refundData = {
      'refund': {
        'amount': <?php print $amount; ?>,
        'paymentIntentId': '<?php print $payment_id; ?>',
        <?php if (!empty($charge_id)) echo "'chargeId': '$charge_id',"; ?>
        'description': 'Refund Bon <?php print $ordre_id; ?>',
        'reason': 'requested_by_customer',
        'metadata': {
          'orderId': '<?php print $ordre_id; ?>'
        }
      }
    }
    
    console.log('Refund data object:', refundData);
    
    var cardScheme = 'unknown';
    var refund_id = null;
    var refund_check_count = 0;
    var max_refund_checks = 60; // 3 minutes at 3-second intervals
    
    // Function to check refund status
    async function get_refund_update(refundId) {
      refund_check_count++;
      console.log(`=== REFUND STATUS CHECK #${refund_check_count} ===`);
      console.log('Checking refund ID:', refundId);
      console.log('Time:', new Date().toISOString());
      
      if (refund_check_count > max_refund_checks) {
        console.error('Max refund checks exceeded, stopping');
        paused = true;
        var elm = document.getElementById('status');
        elm.style.backgroundColor = '#ea3a3a';
        elm.innerText = 'Timeout: Refund status kunne ikke bekræftes';
        document.getElementById('bg').style.backgroundColor = '#fb9389';
        document.getElementById('continue').style.display = 'block';
        document.getElementById('continue').disabled = false;
        return;
      }
      
      setTimeout(async () => {
        try {
          logToServer(`Checking refund status for ${refundId}`);
          var res = await fetch(
            `https://pos.api.vibrant.app/pos/v1/refunds/${refundId}`,
            {
              method: 'get',
              headers: {
                'apikey': '<?php print $APIKEY; ?>'
              }
            }
          );
          logToServer(`Refund status response: ${res.status}`);
          
          if (!res.ok) {
            paused = true;
            var elm = document.getElementById('status');
            elm.style.backgroundColor = '#ea3a3a';
            elm.innerText = `Fejl ved kontrol af refund: ${res.status} ${res.statusText}`;
            document.getElementById('bg').style.backgroundColor = '#fb9389';
            document.getElementById('continue').style.display = 'block';
            document.getElementById('continue').disabled = false;
            return;
          }
          
          var json_data = await res.json();

          if (json_data['status'] == 'succeeded') {

            // Refund was successful
            paused = true;
            var elm = document.getElementById('status');
            elm.style.backgroundColor = '#51e87d';
            elm.innerText = 'Refund gennemført og bekræftet';
            
            try {
              var receiptResponse = await fetch(
                'save_receipt.php',
                {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                  },
                  body: JSON.stringify({
                    data: json_data,
                    id: '<?php print $ordre_id; ?>',
                    type: 'vibrant_refund'
                  })
                }
              );
              console.log('Receipt save response status:', receiptResponse.status);
              if (!receiptResponse.ok) {
                console.error('Failed to save receipt:', receiptResponse.statusText);
              } else {
                console.log('✅ Receipt saved successfully');
              }
            } catch (receiptError) {
              console.error('Error saving receipt:', receiptError);
            }
            
            countdown(1);
            document.getElementById('continue-success').style.display = 'block';
            document.getElementById('continue').style.display = 'none';
            return;
            
          } else if (json_data['status'] == 'failed') {
            
            // Refund failed
            paused = true;
            var elm = document.getElementById('status');
            elm.style.backgroundColor = '#ea3a3a';
            elm.innerText = `Refund fejlede: ${json_data['failureReason'] || json_data['errorCode'] || 'Ukendt fejl'}`;
            document.getElementById('bg').style.backgroundColor = '#fb9389';
            document.getElementById('continue').style.display = 'block';
            document.getElementById('continue').disabled = false;
            return;
            
          } else if (json_data['status'] == 'canceled') {
            console.warn('⚠️ REFUND CANCELED');
            
            // Refund was canceled
            paused = true;
            var elm = document.getElementById('status');
            elm.style.backgroundColor = '#ea3a3a';
            elm.innerText = 'Refund blev annulleret';
            document.getElementById('bg').style.backgroundColor = '#fb9389';
            document.getElementById('continue').style.display = 'block';
            document.getElementById('continue').disabled = false;
            return;
            
          } else if (json_data['status'] == 'pending' || json_data['status'] == 'processing' || json_data["status"] == "requires_payment_method") {
            console.log(`⏳ Refund still ${json_data['status']}, continuing to check...`);
            
            // Still processing, check again
            var elm = document.getElementById('status');
            elm.style.backgroundColor = '#fbbc04';
            if(json_data['status'] == 'requires_payment_method') {
              elm.innerText = `Refund afventer bekræftelse...`;
            } else {
              elm.innerText = `Behandler refund... (${json_data['status']})`;
            }
            get_refund_update(refundId);
            
          } else {
            console.warn('⚠️ Unknown refund status:', json_data['status']);
            console.log('Will continue checking...');
            
            // Unknown status, keep checking but update UI
            var elm = document.getElementById('status');
            elm.style.backgroundColor = '#fbbc04';
            elm.innerText = `Ukendt status: ${json_data['status']}`;
            get_refund_update(refundId);
          }
          
        } catch (error) {
          
          // Continue checking on error, but show warning
          var elm = document.getElementById('status');
          elm.style.backgroundColor = '#fbbc04';
          elm.innerText = `Netværksfejl, prøver igen... (${refund_check_count}/${max_refund_checks})`;
          
          get_refund_update(refundId);
        }
      }, 3000); // Check every 3 seconds
    }
    
    async function process_refund() {
      console.log('=== STARTING REFUND PROCESS ===');
      
      if ("<?php print $terminal_id; ?>" == "dummy") {
        console.log('🧪 DUMMY MODE: Simulating successful refund');
        cardScheme = "Dankort";
        payment_id = "pi_dummy_refund";
        receipt_id = "pi_dummy_refund-dummy";
        setTimeout(() => {
          console.log('Dummy refund completed, calling success');
          successed();
        }, 2000);
        return;
      }
      
      try {
        console.log('Making refund request to Vibrant API...');
        console.log('Endpoint:', `https://pos.api.vibrant.app/pos/v1/terminals/<?php print $terminal_id; ?>/process_refund`);
        console.log('Request body:', JSON.stringify(refundData, null, 2));
        
        logToServer(`Initiating refund request to terminal ${"<?php print $terminal_id; ?>"}`);
        var start = Date.now();
        var res = await fetch(
          'https://pos.api.vibrant.app/pos/v1/terminals/<?php print $terminal_id; ?>/process_refund',
          {
            method: 'post',
            headers: {
              'Content-Type': 'application/json',
              'apikey': '<?php print $APIKEY; ?>'
            },
            body: JSON.stringify(refundData),
          }
        );
        var duration = Date.now() - start;
        logToServer(`Refund request completed in ${duration}ms. Status: ${res.status}`);
        
        console.log('=== REFUND REQUEST RESPONSE ===');
        console.log('Response status:', res.status);
        console.log('Response OK:', res.ok);
        console.log('Response status text:', res.statusText);
        console.log('Response headers:', Object.fromEntries(res.headers.entries()));
        
        if (!res.ok) {
          console.error('❌ Refund request failed');
          console.error('Status:', res.status);
          console.error('Status text:', res.statusText);
          
          // Try to get error details from response body
          try {
            var errorData = await res.text();
            console.error('Error response body:', errorData);
          } catch (e) {
            console.error('Could not read error response body');
          }
          
          paused = true;
          var elm = document.getElementById('status');
          elm.style.backgroundColor = '#ea3a3a';
          elm.innerText = `Fejl: ${res.status} ${res.statusText}`;
          document.getElementById('bg').style.backgroundColor = '#fb9389';
          document.getElementById('continue').style.display = 'block';
          document.getElementById('continue').disabled = false;
          return;
        }
        
        if (res.status == 201) {
          var json_data = await res.json();
          console.log('✅ Refund request accepted');
          console.log('Response data:', json_data);
          
          refund_id = json_data['objectIdToProcess'];
          console.log('Refund ID assigned:', refund_id);
          
          // Update status to show we're processing
          var elm = document.getElementById('status');
          elm.style.backgroundColor = '#fbbc04';
          elm.innerText = 'Refund startet, afventer bekræftelse...';
          
          console.log('Starting refund status monitoring...');
          // Start checking refund status
          get_refund_update(refund_id);
        } else {
          console.warn('⚠️ Unexpected response status:', res.status);
          var responseText = await res.text();
          console.log('Response body:', responseText);
        }
        
      } catch (error) {
        console.error('❌ CRITICAL ERROR in process_refund:', error);
        console.error('Error name:', error.name);
        console.error('Error message:', error.message);
        console.error('Error stack:', error.stack);
        
        paused = true;
        var elm = document.getElementById('status');
        elm.style.backgroundColor = '#ea3a3a';
        elm.innerText = `Kritisk fejl: ${error.message}`;
        document.getElementById('bg').style.backgroundColor = '#fb9389';
        document.getElementById('continue').style.display = 'block';
        document.getElementById('continue').disabled = false;
      }
    }
    
    console.log('Initiating refund process...');
    process_refund();
  } else {
    console.log('Setting data')
    var data = {
      'paymentIntent': {
        'amount': <?php print $amount; ?>,
        'description': 'Bon <?php print $ordre_id; ?>',
        'metadata': {
          'correlationId': '<?php print $ordre_id; ?>'
        }
      }
    }

    var cardScheme = 'unkowen';
    var payment_id = 'null';


    async function get_payment_update(pid) {
        payment_id = pid; // Store the payment intent ID
      setTimeout(async () => {
        logToServer(`Checking payment status for ${pid}`);
        var pStart = Date.now();
        var res = await fetch(
          `https://pos.api.vibrant.app/pos/v1/payment_intents/${pid}`,
          {
            method: 'get',
            headers: {
              'apikey': '<?php print $APIKEY; ?>'
            }
          }
        )
        logToServer(`Payment status check took ${Date.now() - pStart}ms. Status: ${res.status}`);

        if (!res.ok) {
          paused = true;
          var elm = document.getElementById('status');
          elm.style.backgroundColor = '#ea3a3a';
          elm.innerText = `Fejl: ${res.error}`;
          document.getElementById('bg').style.backgroundColor = '#fb9389';
          document.getElementById('continue').style.display = 'block';
          document.getElementById('continue').disabled = false;
          return;
        }

        var json_data = await res.json();
        if (json_data['status'] == 'succeeded') {

          // Get the cardtype
          var charge = await fetch(
            `https://pos.api.vibrant.app/pos/v1/charges/${json_data['latestCharge']}`,
            {
              method: 'get',
              headers: {
                'apikey': '<?php print $APIKEY; ?>'
              }
            }
          );
          var charge_json = await charge.json();

          await fetch(
            'save_receipt.php',
            {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
              },
              body: JSON.stringify({
                data: charge_json,
                id: '<?php print $ordre_id; ?>',
                type: 'vibrant'
              })
            }
          );
          window.open("<?php print ($printserver == 'android' ? "saldiprint://" : "http://$printserver"); ?>/saldiprint.php?bruger_id=99&bonantal=1&printfil=<?php print $printfile; ?>&skuffe=0&gem=1','','width=200,height=100")
          receipt_id = `${charge_json.id}-${charge_json.paymentIntent}`;

          cardScheme = charge_json['paymentMethodDetails']['cardPresent']['brand']

          paused = true;

          var elm = document.getElementById('status');
          elm.style.backgroundColor = '#51e87d';
          elm.innerText = 'Success';

          countdown(1);

          document.getElementById('continue-success').style.display = 'block';
          document.getElementById('continue').style.display = 'none';
          return;
        } else if (json_data['lastPaymentError'] != null) {
          paused = true;
          var elm = document.getElementById('status');
          elm.style.backgroundColor = '#ea3a3a';
          elm.innerText = `Fejl: ${json_data['lastPaymentError']['message']}`;
          document.getElementById('bg').style.backgroundColor = '#fb9389';
          document.getElementById('continue').style.display = 'block';
          document.getElementById('continue').disabled = false;
          return;
        } else if (json_data['status'] == 'canceled') {
          paused = true;
          var elm = document.getElementById('status');
          elm.style.backgroundColor = '#ea3a3a';
          elm.innerText = 'Fejl: Betalingen blev anuleret';
          document.getElementById('bg').style.backgroundColor = '#fb9389';
          document.getElementById('continue').style.display = 'block';
          document.getElementById('continue').disabled = false;
          return;
        }

        get_payment_update(pid);

      }, 3000)
    }

    var idx = 0;
    async function get_pos() {
      if ("<?php print $terminal_id; ?>" == "dummy") {
        cardScheme = "Dankort"
        payment_id = "pi_dummy"
        receipt_id = "pi_dummy-dummy"
        payment_id = "payment_id_dummy"
        successed();
        return;
      }


      try {
        logToServer(`Sending transaction to terminal ${"<?php print $terminal_id; ?>"} (${"<?php print $type; ?>"})`);
        var txStart = Date.now();
        var res = await fetch(
          'https://pos.api.vibrant.app/pos/v1/terminals/<?php print $terminal_id; ?>/<?php print $type; ?>',
          {
            method: 'post',
            headers: {
              'Content-Type': 'application/json',
              'apikey': '<?php print $APIKEY; ?>'
            },
            body: JSON.stringify(data),
          }
        )
        logToServer(`Transaction request took ${Date.now() - txStart}ms. Status: ${res.status}`);
        console.log(res);
        if (!res.ok) {
          paused = true;
          var elm = document.getElementById('status');
          elm.style.backgroundColor = '#ea3a3a';
          elm.innerText = `Fejl: ${res.statusText}`;
          document.getElementById('bg').style.backgroundColor = '#fb9389';
          document.getElementById('continue').style.display = 'block';
          document.getElementById('continue').disabled = false;
          return;
        }

        if (res.status == 201) {
          var json_data = await res.json();
          payment_id = json_data['objectIdToProcess'];
          get_payment_update(payment_id);
          <?php
          if ($printserver == "android") {
            print "window.location.href = 'vibrantio://a2a?callbackUrl=" . $_SERVER['REQUEST_URI'] . "&callback=hallo%20ther';";
          }
          ?>
        }

      } catch (error) {
        console.log(error);
        //console.log('Retrying');
        //await get_pos();
      }
    }

    get_pos();
  }

  count_down();
</script>