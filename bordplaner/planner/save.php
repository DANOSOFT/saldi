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

$tables = array();

$post = json_decode(file_get_contents('php://input'));

$qtxt = "select id from table_plan";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
$x = 0;
# Loop over all the tables and add their html
while ($r = db_fetch_array($q)) {
  $tables[$x] = $r['id'];
  $x++;
}

# Loop over all the tables sent over
for ($i = 0; $i < count($post); $i++) {
  $id =      $post[$i]->{'id'};
  $height =  $post[$i]->{'height'};
  $width =   $post[$i]->{'width'};
  $posx =    $post[$i]->{'posX'};
  $posy =    $post[$i]->{'posY'};
  $name =    $post[$i]->{'name'};
  $tooltip = $post[$i]->{'tooltip'};
  $type =    $post[$i]->{'type'};
  $page =    $post[$i]->{'page'};

  # If the table is already in the database
  if (in_array($id, $tables)){
    $qtxt = "UPDATE table_plan SET height = $height, width = $width, posx = $posx, posy = $posy, name = '$name', tooltip = '$tooltip', pageid = '$page' WHERE id = $id";
    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
  }
  else {
    $qtxt = "insert into table_plan(height, width, posx, posy, name, tooltip,	type, pageid) values ($height, $width, $posx, $posy, '$name', '$tooltip', '$type', $page)";
    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
  }
  $tables_new[$i] = $post[$i]->{'id'};
}
# Loop over all the tables sent over
for ($i = 0; $i < count($tables); $i++) {
  # If the table is already in the database
  if (!in_array($tables[$i], $tables_new)){
    $qtxt = "DELETE FROM table_plan WHERE id = $tables[$i]";
    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
  }
}
?>

