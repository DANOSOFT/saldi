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
// LN 20190709 Make function that sets the "Gem bestilling" site for the take away

function getDataFromDatabase()
{
    $selectFields = "id, status, bynavn, lev_navn, addr1, lev_addr1, ordredate, ordrenr, sum,";
    $selectFields .= "modtagelse, lev_adr, tidspkt, betalt, datotid, kontakt_tlf";
    $queryTxt = "select " . $selectFields . " from ordrer where status='-1' order by datotid asc, tidspkt asc";
    $query = db_select($queryTxt  ,__FILE__ . " linje " . __LINE__);
    return $query;
}

function getFinishedTakeAwayData()
{
    $selectFields = "id, status, bynavn, lev_navn, addr1, lev_addr1, ordredate, ordrenr, sum,";
    $selectFields .= "modtagelse, lev_adr, tidspkt, betalt, datotid, kontakt_tlf";
    $queryTxt = "select " . $selectFields . " from ordrer where status='3' order by id asc";
    $query = db_select($queryTxt  ,__FILE__ . " linje " . __LINE__);

    while($order = db_fetch_array($query)) {
        $mandatoryFields = isset($order['lev_navn']) && isset($order['kontakt_tlf']) && isset($order['datotid']) && isset($order['tidspkt']);
        $radioFields = isset($order['modtagelse']) && isset($order['lev_addr1']) && isset($order['betalt']);
        if ($mandatoryFields && $radioFields) {
          $temp = $order['lev_navn'];
            $returnArr[] = $order;
        }
    }
    return $returnArr;
}

function getDeletedTakeAwayData()
{
    $selectFields = "id, status, bynavn, lev_navn, addr1, lev_addr1, ordredate, ordrenr, sum,";
    $selectFields .= "modtagelse, lev_adr, tidspkt, betalt, datotid, kontakt_tlf";
    $queryTxt = "select " . $selectFields . " from ordrer where status='-2' order by id asc";
    $query = db_select($queryTxt  ,__FILE__ . " linje " . __LINE__);

    while($order = db_fetch_array($query)) {
        $mandatoryFields = isset($order['lev_navn']) && isset($order['kontakt_tlf']) && isset($order['datotid']) && isset($order['tidspkt']);
        $radioFields = isset($order['modtagelse']) && isset($order['lev_addr1']) && isset($order['betalt']);
        if ($mandatoryFields && $radioFields) {
          $temp = $order['lev_navn'];
            $returnArr[] = $order;
        }
    }
    return $returnArr;
}

function setReceived($savedOrders)
{
    $temp = $savedOrders['modtagelse'];
    $received = ($temp == '0') ? "Walk-in" : (($temp == '1') ? "Web" : "Telefon");
    return $received;
}

function setBorder($savedOrders)
{
    $temp = $savedOrders['lev_addr1'];

    if ($temp == "Afhentning") {
        return"style=\"background-color: #80d4ff; border: 3px solid transparent;\"";
    } elseif ($temp == "Spises her") {
        return "style=\"background-color: #70db70; border: 3px solid transparent;\"";
    } elseif ($temp == "Leveres") {
        return "style=\"background-color: #ff6666; border: 3px solid transparent;\"";
    }
}

function getJQueryColor()
{
  print "<script>
              var rowId;
              $(\".orderRow\").click(function() {
                  if(typeof rowId == 'undefined') {
                      $(\".editButton\").removeAttr(\"disabled\");
                  } else {
                      $(\"#\" + rowId).css('color', 'black'  );
                      var delForm = $(\"#\" + rowId).find(\"#deliverForm\").html();
                      if (delForm == 'Afhentning') {
                        $(\"#\" + rowId).css('background-color', '#80d4ff');
                      } else if (delForm == 'Spises her') {
                        $(\"#\" + rowId).css('background-color', '#70db70');
                      } else if (delForm == 'Leveres') {
                        $(\"#\" + rowId).css('background-color', '#ff6666');
                      }
                  }

                  rowId = $(this).attr('id');
                  var orderNr = $(this).attr('value');
                  $(\"#submitId\").val(orderNr);

                  $(this).css({
                    'background-color' : '#404040',
                    'color' : 'white'
                  });
              });
          </script>";
}

function setDateFormat($orderDate, $time)
{
  $diff = floor((strtotime(formatDate($orderDate)) - strtotime(date("Y-m-d")))/(60*60*24));
  if($diff == 0) {
      return "I dag kl: " . $time;
  } elseif ($diff == 1) {
      return "I morgen kl: " . $time;
  } else {
    return $orderDate . " kl: " . $time;
  }


}








?>
