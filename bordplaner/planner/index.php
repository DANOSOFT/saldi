<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- bordplaner/bordplan.php ---------- lap 3.9.9----2023.03.15-------
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
// Copyright (c) 2012-2023 saldi.dk aps
// ----------------------------------------------------------------------
@session_start();
$s_id = session_id();

include ("../../includes/connect.php");
include ("../../includes/online.php");
include ("../../includes/std_func.php");

print '<head>
    <title></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="style.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400&display=swap" rel="stylesheet">
</head>';

$css = "./style.css";


# Setup the toppbar
print "<div id='appbar'>";
$y = 0;
$q = db_select("select id, name from table_pages ORDER BY id", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
  echo "<div id='switch$r[id]' onClick='open_page($r[id])'>$r[name]</div>";
  $pages[$y] = $r["id"];
  $y++;
}
print "</div>";

print '
    <div id="base"></div>

    <div id="contextMenu" class="context-menu" style="display: none">
      <ul>
        <li id="open-modal">
          <img src="assets/edit_icon.svg" alt="" height="18" width="18" />
          <span>Rediger</span>
        </li>
        <li id="delete-context">
          <img src="assets/trash_2_icon.svg" alt="" height="18" width="18" />
          <span>Slet</span>
        </li>
        <li id="add-context">
          <img src="assets/plus_circle_icon.svg" alt="" height="18" width="18" />
          <span>Tilføj Nyt Bord</span>
        </li>
        <li id="add-figure">
          <img src="assets/plus_circle_icon.svg" alt="" height="18" width="18" />
          <span>Tilføj Ny Figur</span>
        </li>
      </ul>
    </div>

    <!-- Modal content -->
    <div id="myModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <span class="close">&times;</span>
          <h2 id="modal-header">Rediger Bord</h2>
        </div>
        <div class="modal-body">
          <div id="modal-main">
            <p id="modal-description">Her kan du redigere dit bord</p>
            <div id="table-settings">
              <div>
                <span>Navn</span>
                <input type="text" size=19 id="name-inp">
              </div>
              <div>
                <span>Bord Tekst</span>
                <input type="text" size=19 id="table-text-inp">
              </div>
            </div>
            <div>
              <span>Størrelse</span>
              <input type="number" size=7 min=20 max=1200 step=20 id="size-x-inp">
              x
              <input type="number" size=7 min=20 max=800 step=20 id="size-y-inp">
            </div>
            <div>
              <span>Lokale</span>
              <select name="pages" id="pages">';
$y = 0;
$q = db_select("select id, name from table_pages ORDER BY id", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
  echo "<option value='$r[id]'>$r[name]</option>";
  $y++;
}
print '              </select>
            </div>
          </div>
          <div id="modal-preview">
            <div class="table" style="width: 100px; height: 200px;">
              <span class="table-text">7</span>
              <div class="table-tooltip" id="example-tooltip">he ho</div>
            </div>
          </div>
        </div>
        <div style="margin-top: 20px">
          <button class="success-button" id="save">Opdater</button>
          <button class="close-modal">Annuler</button>
          <button class="failure-button close-modal" id="delete-modal">Slet</button>
        </div>
      </div>
    </div>

    <div id="taskbar">
      <button class="success-button" id="save-doc">Gem</button>
      <button id="close-doc" onClick="history.back()">Luk</button>
    </div>

    <script src="js/ExampleTable.js"></script>
    <script src="js/Table.js"></script>
    <script src="js/Figure.js"></script>
    <script src="js/script.js"></script>
    <script>
      var tables = [
  ';

$qtxt = "select * from table_plan";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
$x = 0;
# Loop over all the tables and add their html
while ($r = db_fetch_array($q)) {
  if ($r["type"] == "table") {
    echo "new Table($r[id], base, $r[posx], $r[posy], $r[width], $r[height], \"$r[name]\", \"$r[tooltip]\", $r[pageid]),";
  }
  if ($r["type"] == "rect") {
    echo "new Figure($r[id], base, $r[posx], $r[posy], $r[width], $r[height], $r[pageid]),";
  }
  $x++;
}

$page = $pages[0];
#$page = 0;

print "
      ]
    </script>
    <script>
      open_page($page)
    </script>
    <script src='js/save.js'></script>
    <script src='js/modal.js'></script>
";

?>

