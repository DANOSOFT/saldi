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

function setTimeField($time) {
    print "<select class=\"timeOptions\" name=\"takeAwayHour\" selected=\"selected\" required>
              <option value=\"\" hidden> Time </option>
              <option class=\"optionItem\" value=\"00\"> 00 </option>
              <option class=\"optionItem\" value=\"01\"> 01 </option>
              <option class=\"optionItem\" value=\"02\"> 02 </option>
              <option class=\"optionItem\" value=\"03\"> 03 </option>
              <option class=\"optionItem\" value=\"04\"> 04 </option>
              <option class=\"optionItem\" value=\"05\"> 05 </option>
              <option class=\"optionItem\" value=\"06\"> 06 </option>
              <option class=\"optionItem\" value=\"07\"> 07 </option>
              <option class=\"optionItem\" value=\"08\"> 08 </option>
              <option class=\"optionItem\" value=\"09\"> 09 </option>
              <option class=\"optionItem\" value=\"10\"> 10 </option>
              <option class=\"optionItem\" value=\"11\"> 11 </option>
              <option class=\"optionItem\" value=\"12\"> 12 </option>
              <option class=\"optionItem\" value=\"13\"> 13 </option>
              <option class=\"optionItem\" value=\"14\"> 14 </option>
              <option class=\"optionItem\" value=\"15\"> 15 </option>
              <option class=\"optionItem\" value=\"16\"> 16 </option>
              <option class=\"optionItem\" value=\"17\"> 17 </option>
              <option class=\"optionItem\" value=\"18\"> 18 </option>
              <option class=\"optionItem\" value=\"19\"> 19 </option>
              <option class=\"optionItem\" value=\"20\"> 20 </option>
              <option class=\"optionItem\" value=\"21\"> 21 </option>
              <option class=\"optionItem\" value=\"22\"> 22 </option>
              <option class=\"optionItem\" value=\"23\"> 23 </option>
          </select>
          <b style=\"font-size: 20px;\"> : </b>
          <select class=\"timeOptions\" name=\"takeAwayMin\" selected=\"selected\" required>
              <option value=\"\" hidden> Min </option>
              <option class=\"optionItem\" value=\"00\"> 00 </option>
              <option class=\"optionItem\" value=\"05\"> 05 </option>
              <option class=\"optionItem\" value=\"10\"> 10 </option>
              <option class=\"optionItem\" value=\"15\"> 15 </option>
              <option class=\"optionItem\" value=\"20\"> 20 </option>
              <option class=\"optionItem\" value=\"25\"> 25 </option>
              <option class=\"optionItem\" value=\"30\"> 30 </option>
              <option class=\"optionItem\" value=\"35\"> 35 </option>
              <option class=\"optionItem\" value=\"40\"> 40 </option>
              <option class=\"optionItem\" value=\"45\"> 45 </option>
              <option class=\"optionItem\" value=\"50\"> 50 </option>
              <option class=\"optionItem\" value=\"55\"> 55 </option>
          </select>";

      if (validateTime($time)) {
          $time = str_split($time);
          $hour = $time[0] . $time[1];
          $min = $time[3] . $time[4];
      } else {
          $time = setDefaultTime();
          $hour = $time['hour'];
          $min = $time['min'];
      }
      print "<script>
          $(\"[name='takeAwayHour']\").val(\"$hour\").attr(\"selected\",\"selected\");
          $(\"[name='takeAwayMin']\").val(\"$min\").attr(\"selected\",\"selected\");
      </script>";

      print "<style>
                .timeOptions {
                    width: 100px;
                    border: 1px solid #ccc;
                    padding: 12px 20px;
                    margin: 0 0;
                }
              .optionItem {
                    font-family: 'Open Sans', 'Helvetica Neue', 'Segoe UI', 'Calibri', 'Arial', sans-serif;
                    font-size: 18px;
                    color: #60666d;
              }
            </style>";
}

function setDefaultTime()
{
    $addingMinutes = 10;
    $hour = date("H");
    $min = date("i");
    $remainder = ($min % 5) > 0 ? ($min % 5) : 5;
    $min = (5 - $remainder) + $min;
    if (($addingMinutes + $min) >= 60) {
        $min = ($addingMinutes + $min) % 60;
        $hour = $hour + 1;
        $min = (preg_match("/\d/", $min) == 1) ? sprintf("%02d", $min) : $min;
    } else {
        $min = $min + $addingMinutes;
    }
    $hour = (preg_match("/\d/", $hour) == 1) ? sprintf("%02d", $hour) : $hour;
    return ['hour' => $hour, 'min' => $min];
}

function validateTime($time)
{
    $time = str_split($time);
    for ($i=0; $i<5; $i++) {
        $digit = $time[$i];
        if ((preg_match('~[0-9]+~', $digit) && $i != 2) ||  ($i == 2 && $digit == ":"))  {
            continue;
        } else {
            return false;
        }
    }
    return true;
}

?>
