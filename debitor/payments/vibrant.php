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
echo $ordre_id;
if($type == "process_refund") {
  $query = db_select("SELECT betalings_id FROM ordrer WHERE id = $ordre_id", __FILE__ . " linje " . __LINE__);
  $row = db_fetch_array($query);
  $payment_id = $row['betalings_id'];
  // Also store receipt_id to extract chargeId if needed
  $receipt_parts = explode('-', $row['receipt_id'] ?? '');
  $charge_id = $receipt_parts[0] ?? '';
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
  const successed = (event) => {
    console.log(cardScheme);
    window.location.replace(`../pos_ordre.php?id=<?php print $ordre_id; ?>&godkendt=OK&indbetaling=<?php print $indbetaling; ?>&amount=<?php print $raw_amount; ?>&cardscheme=${cardScheme}&receipt_id=${receipt_id}&payment_id=${payment_id}`);
  }

  const failed = (event) => {
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
          documentgetElementById('continue').style.display = 'none';
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
    console.log('Setting refund data');
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
    
    var cardScheme = 'unknown';
    
    async function process_refund() {
      if ("<?php print $terminal_id; ?>" == "dummy") {
        cardScheme = "Dankort";
        payment_id = "pi_dummy_refund";
        receipt_id = "pi_dummy_refund-dummy";
        successed();
        return;
      }
      
      try {
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
          payment_id = json_data['id'];
          
          paused = true;
          var elm = document.getElementById('status');
          elm.style.backgroundColor = '#51e87d';
          elm.innerText = 'Refund gennemført';
          
          countdown(1);
          
          document.getElementById('continue-success').style.display = 'block';
          document.getElementById('continue').style.display = 'none';
        }
      } catch (error) {
        console.log(error);
        paused = true;
        var elm = document.getElementById('status');
        elm.style.backgroundColor = '#ea3a3a';
        elm.innerText = `Fejl: ${error.message}`;
        document.getElementById('bg').style.backgroundColor = '#fb9389';
        document.getElementById('continue').style.display = 'block';
        document.getElementById('continue').disabled = false;
      }
    }
    
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
        var res = await fetch(
          `https://pos.api.vibrant.app/pos/v1/payment_intents/${pid}`,
          {
            method: 'get',
            headers: {
              'apikey': '<?php print $APIKEY; ?>'
            }
          }
        )

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
