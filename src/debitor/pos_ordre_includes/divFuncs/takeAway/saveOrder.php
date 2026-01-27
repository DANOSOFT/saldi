<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/divFuncs/takeAway/setup.php ---------- lap 3.7.7----2019.07.09-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
//
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190709 Make function that save the saved order to the database

function saveTheOrder()
{
    $ordreId = $_POST['takeAwayOrderId'];
    $name = $_POST['takeAwayName'];
    $date = $_POST['takeAwayDate'];
    $time = $_POST['takeAwayHour'] . ":" . $_POST['takeAwayMin'];
    $pay = $_POST['takeAwayPay'];
    $city = $_POST['takeAwayCity'];
    $adr = $_POST['takeAwayAdress'];
    $phone = $_POST['takeAwayPhone'];
    $postnr = $_POST['takeAwayPostNr'];
    $deliver = $_POST['takeAwayDeliver'];
    $received = formatReceived();
    foreach ($_POST['takeAwayOrders'] as $key => $value) {
        db_modify("update ordrer set lev_navn='$name', datotid='$date', tidspkt='$time',
                    betalt='$pay', bynavn='$city', addr1='$adr', kontakt_tlf='$phone',
                    modtagelse='$received', lev_addr1='$deliver', postnr='$postnr'
                    where id='$ordreId'", __FILE__ ."linje".__LINE__);
    }
}

function formatReceived()
{
    if ($_POST['takeAwayReceived'] == 'Walk-in') {
      return 0;
    } elseif ($_POST['takeAwayReceived'] == 'Web') {
      return 1;
    } elseif ($_POST['takeAwayReceived'] == 'Telefon') {
      return 2;
    }
}

?>
