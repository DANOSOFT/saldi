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

print '<head>';
print '<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400&display=swap" rel="stylesheet">';
print '</head>';

$css = "./planner/style.css";
$pages = $takenTables = array();

include ("../includes/connect.php");
include ("../includes/online.php");
include ("../includes/std_func.php");

$flyt = if_isset($_GET['flyt']);
$id = if_isset($_GET['id'], 0);
$delflyt = if_isset($_GET['delflyt']);

# Setup the toppbar
print "<div id='appbar'>";
$y = 0;
$q = db_select("select id, name from table_pages ORDER BY id", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
  echo "<div id='switch$r[id]' onClick='open_page(\"cat$r[id]\")'>$r[name]</div>";
  $pages[$y] = $r["id"];
  $y++;
}
print "</div>";

# Check what tables are taken so far
$y = 0;
$q = db_select("select distinct(nr) as nr from ordrer,ordrelinjer where art = 'PO' and status < 3 and ordrelinjer.ordre_id=ordrer.id", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
  $takenTables[$y] = $r['nr'];
  $y++;
}

# Select all the tables from the table plan table
$qtxt = "select * from table_plan";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
$x = 0;
# Loop over all the tables and add their html
while ($r = db_fetch_array($q)) {
  # If its the first page we show da thingy
  if ($r['pageid'] == $pages[0]) {
    $hidden = "block";
  } else {
    $hidden = "none";
  }

  if ($r['type'] == "table") {
    (in_array($r['id'], $takenTables)) ? $bgcolor = "#ea3a3a" : $bgcolor = "#51e87d";

    # If flyt is given the table needs to move the items on the table
    if ($flyt || $flyt == "0") {
      # All items on table needs to be moved to new table
      if ($r["id"] != $flyt && $delflyt) {
        $link = "window.location.replace('../debitor/pos_ordre.php?id=$id&flyt_til=$r[id]&delflyt=$delflyt')";
        
      # If the table is taken and the id you want to move to is not the same as the one you came from
      }
      elseif ((in_array($r['id'], $takenTables)) && $r['id'] != $flyt) {
        # Dont do anything
        $link = "";
        $cursor = "default";
        
      # The same table as the one you came from
      }
      elseif ($r['id'] == $flyt) {
        $bgcolor = "#6879d5";
        $link = "window.location.replace('../debitor/pos_ordre.php?bordnr=$r[id]')";
        $cursor = "pointer";
        
      # Only some of the items needs to be moved to the new table
      }
      else {
        $link = "window.location.replace('../debitor/pos_ordre.php?id=$id&flyt_til=$r[id]')";
        $cursor = "pointer";
      }
      
    # We just want to switch tables
    }
    else {
      $link = "window.location.replace('../debitor/pos_ordre.php?bordnr=$r[id]')";
      $cursor = "pointer";
    }

    # Calculate the font size for the element
    $fontsize = min($r['width'], $r['height']) * .60;

    echo "<div class='table cat$r[pageid]' style='height:$r[height]px; width: $r[width]px; left: $r[posx]px; top: $r[posy]px; background-color: $bgcolor; cursor: $cursor; display: $hidden;' onClick=\"$link\")'>";
    # Only include the tooltip if it exsists
    if ($r['tooltip'] !== "") {
      echo "<div class='table-tooltip'>$r[tooltip]</div>";
    }
    echo "<span class='table-text' style='font-size: $fontsize'>$r[name]</span>";
    echo "</div>";
  }
  elseif ($r["type"] == "rect") {
    echo "$r[id], ";
    echo "<div class='rect cat$r[pageid]' style='height:$r[height]px; width: $r[width]px; left: $r[posx]px; top: $r[posy]px; background-color: #000; display: $hidden;'></div>";
  }
  $x++;
}
print '<script defer>var pages = [';
# Let the client know how many / what cats we are working with
for ($i = 0; $i < count($pages); $i++)  {
  print "'cat$pages[$i]',";
}
print "];
function open_page(page) {
  if (document.getElementsByClassName('selected').length !== 0){
    document.getElementsByClassName('selected')[0].className = '';
  }
console.log('switch' + page.slice(3));
  document.getElementById('switch' + page.slice(3)).className = 'selected';

  for (let i = 0; i < pages.length; i++){
    var elms = document.getElementsByClassName(pages[i]);
    for (let x = 0; x < elms.length; x++){
      elms[x].style.display = 'none';
    }
  }

  var elms = document.getElementsByClassName(page);
  for (let x = 0; x < elms.length; x++){
    elms[x].style.display = 'block';
  }
}

// setTimeout(() => {open_page('aaa$pages[0]')}, 300);
open_page('cat$pages[0]');


</script>";
?>
