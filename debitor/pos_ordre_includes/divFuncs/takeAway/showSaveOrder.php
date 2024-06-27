<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/divFuncs/showSaveOrder.php ---------- lap 3.7.7----2019.07.09-------
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
// LN 20190709 Make function that sets the "Gem bestilling" site for the take away

function saveOrderPage($products, $sizeOfProductsArray, $previousInfo, $ordreId)
{
     	print "<form name=pos_ordre action=\"pos_ordre.php?id=$id&getOrder=true\" method=\"post\" autocomplete=\"off\">\n";
      print "<input type=\"hidden\" name=\"takeAwayOrderId\" value=\"$ordreId\">";
      print "<center><table cellspacing=\"15\" style=\"display: inline-block;\">";

      if (isset($_SESSION['takeAwayOrderNr'])) {
        $ordrenr = $_SESSION['takeAwayOrderNr'];
        print "<thead>
                  <b style=\"font-size:40px;\"> TakeAway ændring af ordre $ordrenr </b>
              </thead>";
      } else {
          print "<thead>
                    <b style=\"font-size:40px;\"> TakeAway bestilling </b>
                </thead>";
      }
      print "</center>";

      // print "<center style=\"margin-top: 10px;\"><div class=\"orderInfo\">";
      // foreach ($products as $key => $value) {
      //   print "<input type=\"hidden\" name=\"takeAwayOrders[$key]\" value=\"$value\">";
      //   print "$value <br>";
      // }
      // print "</div></center>";

      $name = $previousInfo['lev_navn'];
      $date = formatDate($previousInfo['datotid']);
      $phone = $previousInfo['kontakt_tlf'];
      $adr = $previousInfo['addr1'];
      $city = $previousInfo['bynavn'];
      $postnr = $previousInfo['postnr'];
      print "<div style=\"margin-top: 40px; margin-left: -256px;\">
              <b class=\"textInfo\" style=\"margin-left: 32px;\">
                  Navn:
              </b>
              <input class=\"textField\" placeholder=\"Navn\" name=\"takeAwayName\" type=\"text\" id=\"nameField\" required value=\"$name\">
              <b class=\"textInfo\" style=\"margin-left: 32px;\">
                  Tlf:
              </b>
              <input class=\"textField\" placeholder=\"Tlf nr.\" type=\"tel\" id=\"phoneField\" name=\"takeAwayPhone\" value=\"$phone\">";
              takeAwayPayedFields($previousInfo);
      print "</div>  <div style=\"margin-top: 25px; margin-left: -243px;\">
              <b class=\"textInfo\"> Adr: </b> <input class=\"textField\" placeholder=\"Adresse\" type=\"text\" id=\"adressField\" name=\"takeAwayAdress\" value=\"$adr\">
              <b class=\"textInfo\"> Post nr: </b> <input class=\"textField\" placeholder=\"Postnummer\" type=\"text\" id=\"postField\" name=\"takeAwayPostNr\" value=\"$postnr\">";
              takeAwayReceivedFields($previousInfo);
      print " </div> <div style=\"margin-top: 15px; margin-left: -190px;\">
              <b class=\"textInfo\" style=\"margin-left: 17px;\">
                  By:
              </b>
              <input class=\"textField\" placeholder=\"By\" type=\"text\" id=\"cityField\" name=\"takeAwayCity\" value=\"$city\">
              <b class=\"textInfo\" style=\"margin-left: 17px;\">
                  Dato:
              </b>
              <input class=\"textField\" type=\"date\" id=\"dateField\" name=\"takeAwayDate\" required value=\"$date\">";
              takeAwayDeliverFields($previousInfo);
      print "</div><div>
              <b class=\"textInfo\"> Tidspkt: </b>";
              setTimeField($previousInfo['tidspkt']);
      print "</div></table>";
      ordertable($products, $sizeOfProductsArray);
      // print "<script> If phone field should be required
      //         $(\"#takeAwayWalkIn\").click(function() {
      //               $(\"#phoneField\").attr(\"required\", false);
      //         });
      //         $(\"#takeAwayPhone\").click(function() {
      //               $(\"#phoneField\").attr(\"required\", true);
      //         });
      //         $(\"#takeAwayWeb\").click(function() {
      //               $(\"#phoneField\").attr(\"required\", true);
      //         });
      //         </script>";

        printKeyboard();
        if(empty($date)) {
            print "<script>
                      Date.prototype.toDateInputValue = (function() {
                      var local = new Date(this);
                      local.setMinutes(this.getMinutes() - this.getTimezoneOffset());
                      return local.toJSON().slice(0,10);
                        });
                      $('#dateField').val(new Date().toDateInputValue());
                  </script>";
            }

        print "<center><tbody style=\"margin-buttom: 20px;\"><table cellspacing=\"30\">
                  <tr>
                      <td>
                      <input class=\"finishButton\" type=\"submit\" style=\"background-color: #ffff66;\" name=\"submit\" value=\"Tilbage\" formnovalidate>
                      </td>
                      <td>
                          <input class=\"finishButton\" type=\"submit\" style=\"background-color: #70db70;\" name=\"submit\" value=\"Gem bestilling\">
                      </td>
                  </tr>
              </table></tbody>";
        print "</center>";
        print "</form>";

        print "<style>
                .finishButton {
                    width:200px;
                    border: none;
                    padding: 20px 10px;
                    font-size: 25px;
                    cursor: pointer;
                    border-radius: 6px;
                    margin-top: -50px;
                }
                .textField {
                    width:180px;
                    border: 1px solid #ccc;
                    padding: 12px 20px;
                  }
                .textInfo {
                  font-size: 15 px;
                }
              </style>";

}


?>
