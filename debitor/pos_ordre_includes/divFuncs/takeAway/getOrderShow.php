<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/divFuncs/showGetOrder.php ---------- lap 3.7.7----2019.07.09-------
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
// LN 20190709 Make function that sets the "Hent bestilling" site for the take away

function showGetOrderPage()
{
	  $query = getDataFromDatabase();
	  print "<form name=pos_ordre action=\"pos_ordre.php?id=$id&getOrder=true\" method=\"post\" autocomplete=\"off\">\n";
	  print "<center><table id=\"getOrderTable\">";
      print "<div>
                <b style=\"font-size:40px; margin-left:80px;\"> TakeAway bestillinger </b>
            </div>";
      print "</center>";

      print "<tbody style=\"margin-buttom: 20px;\"><table cellspacing=\"30\">
      <tr>
      <td>
      <input class=\"finishButton editButton\" type=\"submit\" style=\"background-color: #80d4ff;\" name=\"submit\" value=\"Historik\">
      </td>
      <td>
      <input class=\"finishButton editButton\" type=\"submit\" style=\"background-color: #70db70;\" disabled name=\"submit\" value=\"Print\">
      </td>
      <td>
      <input class=\"finishButton editButton\" type=\"submit\" style=\"background-color: #ff6666;\" disabled name=\"submit\" value=\"Slet\">
      </td>
      <td>
      <input class=\"finishButton\" type=\"submit\" style=\"background-color: #ffff66;\" name=\"submit\" value=\"Tilbage\">
      </td>
      <td>
      <input class=\"finishButton editButton\" type=\"submit\" style=\"background-color: #70db70;\" disabled name=\"submit\" value=\"Rediger\">
      </td>
      <td>
      <input class=\"finishButton editButton\" type=\"submit\" style=\"background-color: #70db70;\" disabled name=\"submit\" value=\"Afslut\">
      </td>
      </tr>
      </table></tbody>";
      print "<tbody><table>";

      print "<tr>
                <td class=\"infoLine\"><center>
                  <b class=\"headline\"> Nr </b>
                </center></td>
                <td class=\"infoLine\"><center>
                      <b class=\"headline\"> Tidspunkt </b>
                </center></td>
                <td class=\"infoLine\"><center>
                      <b class=\"headline\"> Navn </b>
                </center></td>
                <td class=\"infoLine\"><center>
                      <b class=\"headline\"> Telefon </b>
                </center></td>
                <td class=\"infoLine\"><center>
                      <b class=\"headline\"> Leveringsform </b>
                </center></td>
                <td class=\"infoLine\"><center>
                      <b class=\"headline\"> Modtaget via </b>
                </center></td>
                <td class=\"infoLine\"><center>
                      <b class=\"headline\"> Betalt </b>
                </center></td>
                <td class=\"infoLine\"><center>
                      <b class=\"headline\"> Adresse </b>
                </center></td>
            </tr>";
    $x = 0;
    while ($savedOrders = db_fetch_array($query)) {
          $received = setReceived($savedOrders);
          $borderStyle = setBorder($savedOrders);
          $payed = $savedOrders['betalt'] == "no" ? "Nej" : "Ja";

          print "<tr id=\"orderRow$x\" $borderStyle class=\"orderRow\" value=\"$savedOrders[ordrenr]\">
                    <td class=\"infoLine\">
                      <center>";
          print            $savedOrders['ordrenr'];
          print       "</center>
                    </td>
                  <td class=\"infoLine\">
                          <center>";
          print $savedOrders['datotid'];
          print "</center>
                    </td>
                    <td class=\"infoLine\">
                          <center>";
          print $savedOrders['lev_navn'];
          print "</center>
                    </td>
                    <td class=\"infoLine\">
                          <center>";
          print $savedOrders['kontakt_tlf'];
          print "</center>
                    </td>
                    <td class=\"infoLine\">
                          <center id=\"deliverForm\">";
          print $savedOrders['lev_addr1'];
          print "</center>
                    </td>
                    <td class=\"infoLine\">
                          <center>";
          print $received;
          print "</center>
                    </td>
                    <td class=\"infoLine\">
                          <center>";
          print $payed;
          print "</center>
                    </td>
                    <td class=\"infoLine\">
                          <center>";
          print $savedOrders['addr1'];
          print "</center> </td> </tr>";
          $x++;
        }
  print "</tbody> </table>";

    getJQueryColor();

    print "<input type=\"hidden\" name=\"orderNr\" id=\"submitId\">";

    print "<style>
              .headline {
                  font-size: 20px;
              }
              .orderRow:hover {
                  transform: scale(1.05);
              }
              .finishButton {
                  width:150px;
                  border: none;
                  padding: 25px 10px;
                  font-size: 24px;
                  cursor: pointer;
                  border-radius: 6px;
              }
              .infoLine {
                border: 1px solid #dddddd;
                padding: 8px;
                font-size: 16px;
              }
              #getOrderTable {
                 margin-top: 20px;
                 width: 80%;
                 border-collapse: collapse;
                 font-family: arial, sans-serif;
              }
          </style>";
          exit;
}

?>
