<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/divFuncs/keyboard.php ---------- lap 3.7.7----2019.07.09-------
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
// LN 20190709 Make function that prints the keyboard on the "Hent bestilling" site for the take away

function printKeyboard() {

  print "<div class=\"keyboard\" style=\"margin-top: 0px;\">";
      print "<div class=\"section-a\" style=\"width:770px; height:260px; float:left;\">";
          print "<div class=\"keyStyle num\" style=\"margin-left:5px;\">1</div>";
          print "<div class=\"keyStyle num\">2</div>";
          print "<div class=\"keyStyle num\">3</div>";
          print "<div class=\"keyStyle num\">4</div>";
          print "<div class=\"keyStyle num\">5</div>";
          print "<div class=\"keyStyle num\">6</div>";
          print "<div class=\"keyStyle num\">7</div>";
          print "<div class=\"keyStyle num\">8</div>";
          print "<div class=\"keyStyle num\">9</div>";
          print "<div class=\"keyStyle num\">0</div>";
          print "<div class=\"keyStyle backspace\" style=\"width:84px;\"> Backspace </div>";

          print "<div class=\"keyStyle letter q\" style=\"margin-left:25px; width:40px;\">Q</div>";
          print "<div class=\"keyStyle letter w\">W</div>";
          print "<div class=\"keyStyle letter e\">E</div>";
          print "<div class=\"keyStyle letter r\">R</div>";
          print "<div class=\"keyStyle letter t\">T</div>";
          print "<div class=\"keyStyle letter y\">Y</div>";
          print "<div class=\"keyStyle letter u\">U</div>";
          print "<div class=\"keyStyle letter i\">I</div>";
          print "<div class=\"keyStyle letter o\">O</div>";
          print "<div class=\"keyStyle letter p\">P</div>";
          print "<div class=\"keyStyle letter å\">Å</div>";

          print "<div class=\"keyStyle letter a\" style=\"margin-left:25px;\">A</div>";
          print "<div class=\"keyStyle letter s\">S</div>";
          print "<div class=\"keyStyle letter d\">D</div>";
          print "<div class=\"keyStyle letter f\">F</div>";
          print "<div class=\"keyStyle letter g\">G</div>";
          print "<div class=\"keyStyle letter h\">H</div>";
          print "<div class=\"keyStyle letter j\">J</div>";
          print "<div class=\"keyStyle letter k\">K</div>";
          print "<div class=\"keyStyle letter l\">L</div>";
          print "<div class=\"keyStyle letter æ\">Æ</div>";
          print "<div class=\"keyStyle letter ø\">Ø</div>";

          print "<div class=\"keyStyle shift\" style=\"margin-left:15px; width:60px;\">Shift</div>";
          print "<div class=\"keyStyle letter z\">Z</div>";
          print "<div class=\"keyStyle letter x\">X</div>";
          print "<div class=\"keyStyle letter c\">C</div>";
          print "<div class=\"keyStyle letter v\">V</div>";
          print "<div class=\"keyStyle letter b\">B</div>";
          print "<div class=\"keyStyle letter n\">N</div>";
          print "<div class=\"keyStyle letter m\">M</div>";
          print "<div class=\"keyStyle comma\">,</div>";
          print "<div class=\"keyStyle period\">.</div>";
          print "<div class=\"keyStyle questionMark\">?</div>";
          print "<div class=\"keyStyle space\" style=\"margin-left:250px; width:260px;\">";

      print "</div>";
  print "</div>";


  print "<script>";
      print "var cursorField;";

      print "$(\".shift\").click(function(){
        var smallAlphabet = \"abcdefghijklmnopqrstuvwxyzæøå\".split(\"\");
        var largeAlphabet = \"ABCDEFGHIJKLMNOPQRSTUVWXYZÆØÅ\".split(\"\");
        if ($(\".q\").text() == $(\".q\").text().toUpperCase()) {
            smallAlphabet.forEach(function(letter) {
                $(\".\" + letter).text(letter);
            });
        } else {
            largeAlphabet.forEach(function(letter) {
                $(\".\" + letter).text(letter);
            });
                $(\".å\").text(\"Å\"); $(\".ø\").text(\"Ø\"); $(\".æ\").text(\"Æ\");
        }
      });";

      print "$(\".backspace\").click(function(){
          var nameVar = $(\"#\" + cursorField).val();
          nameVar = nameVar.substring(0, nameVar.length - 1);
          $(\"#\" + cursorField).val(nameVar);
      });";

      print "$(\".space\").click(function(){
          var nameVar = $(\"#\" + cursorField).val();
          nameVar = nameVar + \" \";
          $(\"#\" + cursorField).val(nameVar);
      });";

      print "$(\".num\").click(function(){
          var num = $(this).text();
          $(\"#\" + cursorField).val(function() {
            return this.value + num;
          });
        });";

      print "$(\".textField\").click(function() {
        cursorField = $(this).attr(\"id\");
        });";

      print "$(\".letter\").click(function(){
          var letter = $(this).text();
          $(\"#\" + cursorField).val(function() {
            return this.value + letter;
          });
        });";

   print "</script>";

   print "<style>
             .keyboard{
               width:790px; height: 200px;
               background-color: #A9A9A9;
               margin: 50px auto;
               border-radius: 9px;
               padding: 30px;
             }
             .keyStyle {
               width: 40px; height:40px;
               display:block;
               background-color:#D3D3D3;
               text-align: left;
               padding-left: 20px;
               line-height: 29px;
               border-radius:4px;
               float:left; margin-left: 6px;
               margin-bottom:3px;
               cursor: pointer;
               font-size: 12px;
             }
             .keyStyle:hover {
               box-shadow:0px 0px 10px #14B524;
               z-index:1000;
             }
         </style>";

}


?>
