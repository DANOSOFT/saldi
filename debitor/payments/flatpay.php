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

$raw_amount = (float) usdecimal(if_isset($_GET['amount'], 0));
$pretty_amount = dkdecimal($raw_amount, 2);
$ordre_id    = if_isset($_GET['id'], 0);
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

$type = ($raw_amount < 0) ? "REFUND" : "SALE";
$amount = abs($raw_amount) * 100;

# Get settings
$q=db_select("select var_value from settings where var_name = 'flatpay_auth'",__FILE__ . " linje " . __LINE__);
$guid = db_fetch_array($q)[0];
# $q=db_select("select var_value from settings where var_name = 'flatpay_terminal_id' and group_id = $kasse",__FILE__ . " linje " . __LINE__);
# $terminal_id = db_fetch_array($q)[0];
$qtxt = "SELECT box4 FROM grupper WHERE beskrivelse = 'Pos valg' AND kodenr = '2' and fiscal_year = '$regnaar'";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
$terminal_id = explode(chr(9),db_fetch_array($q)[0])[$kasse-1];
if (file_exists("../../temp/$db/receipt_$kasse.txt")) unlink("../../temp/$db/receipt_$kasse.txt");
if ($db=='pos_10' || $db=='laja_15') {
  $printfile = 'https://'.$_SERVER['SERVER_NAME'];
  $printfile.= str_replace('debitor/payments/flatpay.php',"temp/$db/receipt_$kasse.txt",$_SERVER['PHP_SELF']);
} else $printfile = NULL;

print "
<script>
  const data = {
    'amount': '$amount',
    'disableTerminalPrints': false,
    'guid': '$guid',
    'language': 'da_DK',
    'reference': '$ordre_id',
    'terminalId': '$terminal_id',
    'transactionType': '$type'
  }

  cardScheme = 'unkowen';

  var count = 120-1;
  var paused = false;

  const successed = (event) => {
    console.log(cardScheme);
    window.location.replace(`../pos_ordre.php?id=$ordre_id&godkendt=OK&indbetaling=$indbetaling&amount=$raw_amount&cardscheme=\${cardScheme}`)
  };
  const failed = (event) => {
    console.log('Failed click');
    window.location.replace('../pos_ordre.php?id=$ordre_id&godkendt=afvist')
  }

  function countdown(i) {
    setTimeout(() => {
      document.getElementById('continue-success').textContent = `Tilbage (\${i})`;
      console.log(`Tilbage (\${i})`);
      if (i == 0) {
        successed(null);
      } else {
        countdown(i-1);
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
      if (count != -1) {
        count_down();
      }
    }, 1000)
  }

  var idx = 0;
  async function get_pos() {
    try {
      var res = await fetch(
        'https://socket.flatpay.dk/socket/transactionlistener',
        {
          method: 'post',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(data),
        }
      )
      if (res.status == 200) {
        console.log('Letgoo 200');
        paused = true;
        var json_data = await res.json();

        await fetch(
          'save_receipt.php',
          {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              data: json_data, 
              id: '$ordre_id',
              type: 'flatpay'
            })
          }
        );
        window.open(\"http://localhost/saldiprint.php?bruger_id=99&bonantal=1&printfil=$printfile&skuffe=0&gem=1','','width=200,height=100\")
        cardScheme = json_data.cardScheme;
        console.log(json_data);

        var elm = document.getElementById('status');

        if (json_data.transApproved) {
          elm.style.backgroundColor = '#51e87d';
          elm.innerText = 'Success';
          countdown(1);
          document.getElementById('continue-success').style.display = 'block';
          document.getElementById('continue').style.display = 'none';
        } else if (json_data.transCancelled) {
          elm.style.backgroundColor = '#ea3a3a';
          elm.innerText = 'Fejl: Transaktion Anulleret';
          document.getElementById('bg').style.backgroundColor = '#fb9389';
          document.getElementById('continue').style.display = 'block';
        document.getElementById('continue').disabled = false;
        } else {
          elm.style.backgroundColor = '#ea3a3a';
          elm.innerText = `Fejl: \${json_data.errorText}`;
          document.getElementById('bg').style.backgroundColor = '#fb9389';
          document.getElementById('continue').style.display = 'block';
        document.getElementById('continue').disabled = false;
        }
      } else {
        var json_data = await res.text();
        var elm = document.getElementById('status');
        elm.style.backgroundColor = '#ea3a3a';
        elm.innerText = `Fejl: \${json_data}`;
        document.getElementById('bg').style.backgroundColor = '#fb9389';
        document.getElementById('continue').style.display = 'block';
        document.getElementById('continue').disabled = false;
      }
    } catch (error) {
      console.log(error);
      console.log('Retrying');
      await get_pos();
    }
  }
  
  count_down();
  get_pos();
</script>
";
?>
