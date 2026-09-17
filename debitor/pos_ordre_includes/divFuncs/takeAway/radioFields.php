<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/divFuncs/takeAway/radioFields.php ---------- lap 3.7.7----2019.07.09-------
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
// LN 20190709 Make function that sets the radio buttons for the "Gem bestilling" site, for the take away

function takeAwayReceivedFields($previousInfo) {

      $receive = $previousInfo['modtagelse'];
      if ($receive == 0) {
          $walkIn = "checked";
          $web = "";
          $phone = "";
      } elseif ($receive == 1) {
          // print "<script> If phone field should be required
          //     $(\"#phoneField\").attr(\"required\", true);
          // </script>";
          $walkIn = "";
          $web = "checked";
          $phone = "";
      } else {
          // print "<script> If phone field should be required
          //     $(\"#phoneField\").attr(\"required\", true);
          // </script>";
          $walkIn = "";
          $web = "";
          $phone = "checked";
      }
      print "<b style=\"left: 120px; top: -40px; font-size: 20px; position: relative;\"> Modtaget via: </b>
                    <label>
                        <input type=\"radio\" id=\"takeAwayWalkIn\" $walkIn name=\"takeAwayReceived\" value=\"Walk-in\">
                        <span class=\"receiveField\" style=\"margin-left: 520px; margin-top: -50px;\">
                            <div style=\"margin-left:-6px; margin-top: -2px; white-space: nowrap;\">
                                Walk-in
                            </div>
                        </span>
                    </label>
                    <label>
                        <input type=\"radio\" id=\"takeAwayPhone\" $phone name=\"takeAwayReceived\" value=\"Telefon\">
                        <span class=\"receiveField\" style=\"margin-left: 750px; margin-top:-60px;\">
                            <div style=\"margin-left:-5px; margin-top: -2px;\">
                                Telefon
                            </div>
                        </span>
                    </label>
                    <label>
                        <input type=\"radio\" id=\"takeAwayWeb\" $web name=\"takeAwayReceived\" value=\"Web\">
                        <span class=\"receiveField\" style=\"margin-left: 980px; margin-top: -70px\">
                            <div style=\"margin-left:4px; margin-top: -2px;\">
                                Web
                            </div>
                        </span>
                    </label>";
}

function takeAwayPayedFields($previousInfo) {
        $pay = $previousInfo['betalt'];
        if ($pay == "yes") {
            $yes = "checked";
            $no = "";
        } else {
            $yes = "";
            $no = "checked";
        }
      print "<b class=\"radioHeader\" style=\"left: 130;\"> Er ordren betalt: </b> <br>
                          <label>
                                <input type=\"radio\" $yes name=\"takeAwayPay\" value=\"yes\">
                                <span class=\"yesNoField\" style=\"margin-left: 640px; margin-top: -50px;\">
                                    <div style=\"margin-top: -3px\">
                                        Ja
                                    </div>
                                </span>
                            </label>
                            <label>
                                <input type=\"radio\" $no name=\"takeAwayPay\" value=\"no\">
                                <span class=\"yesNoField\" style=\"margin-left: 870px; margin-top: -60px\">
                                    <div style=\"margin-top: -3px\">
                                        Nej
                                    </div>
                                </span>
                            </label>";
}

function takeAwayDeliverFields($previousInfo) {
            $form = $previousInfo['lev_addr1'];
            if ($form == "Spises her") {
                $eatHere = "checked";
                $pick = "";
                $deliver = "";
            } elseif ($form == "Leveres") {
                $deliver = "checked";
                $pick = "";
                $eatHere = "";
            } else {
                $pick = "checked";
                $eatHere = "";
                $deliver = "";
            }

            print "<b class=\"radioHeader\" style=\"left: 120px; top:-40px;\"> Vælg leveringsform:  </b> <br>
                            <label>
                                <input type=\"radio\" $pick name=\"takeAwayDeliver\" value=\"Afhentning\">
                                <span class=\"deliverField\" style=\" margin-left: 470px; margin-top: -50px;\">
                                    <div style=\"margin-left:-15px; margin-top: -2px;\">
                                        Afhentning
                                    </div>
                                </span>
                            </label>
                            <label>
                                <input type=\"radio\" $eatHere name=\"takeAwayDeliver\" value=\"Spises her\">
                                <span class=\"deliverField\" style=\"margin-left: 700px; margin-top:-60px;\">
                                    <div style=\"margin-left:-15px; white-space: nowrap; margin-top: -2px;\">
                                        Spises her
                                    </div>
                                </span>
                            </label>
                            <label>
                                <input type=\"radio\" $deliver name=\"takeAwayDeliver\" value=\"Leveres\">
                                <span class=\"deliverField\" style=\"margin-left: 930px; margin-top: -70px\">
                                    <div style=\"margin-left:-7px; margin-top: -2px;\">
                                        Leveres
                                    </div>
                                </span>
                            </label>";

  function ordertable($products, $tableSize)
  {
    print "<div class=\"orderInfo\">";
    foreach ($products as $key => $value) {
      print "<input type=\"hidden\" name=\"takeAwayOrders[$key]\" value=\"$value\">";
      print "$value <br>";
    }
    print "</div>";
    $numberOfProducts = sizeof($products);
    $temp = $tableSize/$numberOfProducts;
    $height = 300 - $tableSize - 11;
    print "<div id=\"divFill\"> </div>
        <style>
            #divFill {
                margin-top: $height;
            }
            .orderInfo {
                margin: 0 auto;
                font: 100%/1.4 serif;
                font-size: 16px;
                width:130px;
                line-height:1.5em;
                overflow-y: auto;
                overflow-x: auto;
                padding:5px;
                background-color: #f2f2f2;
                outline: 1px solid slategrey;
                color: black;
                scrollbar-base-color:#DEBB07;
                box-shadow: inset 0 0 6px;
                margin-top: -300px;
                margin-right: 90px;
            }
        </style>";
  }

  print "<style>
          .yesNoField {
              width:35;
              font-size: 20px;
          }
          .deliverField, .receiveField {
          }
          .deliverField, .yesNoField, .receiveField {
              width: 40px;
              font-size: 15px;
              background-color: #b3b3b3;
              border-style: solid;
              border-width: thin;
              color: black;
              padding: 15px 22px;
              text-decoration: none;
              display: inline-block;
              margin: 6px 15px;
              border-radius: 100px;
              cursor: pointer;
              transform: scale(1.3);
              position: relative;
          }
          .radioHeader {
              font-size: 20px;
              position: relative;
              top: -40px;
          }
          label {
              display: block;
              padding: 5px;
              position: relative;
          }
          label span {
              height: 15px;
              position: absolute;
              left: 0;
          }
          label input {display: none;}
          input:checked + span {background: #00ff00; border-color: #1a1a1a;
          }
        </style>";
}

?>
