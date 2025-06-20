<?php
  @session_start();
  $s_id=session_id();
  include("../includes/connect.php");
  include("../includes/online.php");

  $fp=fopen("../temp/$db.formular.txt","w");

  $query=db_select("select * from formularer ");
  while ($row=db_fetch_array($query)){
    fwrite($fp, $row[formular].chr(9).$row[art].chr(9).$row[beskrivelse].chr(9).$row[xa].chr(9).$row[ya].chr(9).$row[xb].chr(9).$row[yb].chr(9).$row[placering].chr(9).$row[str].chr(9).$row[color].chr(9).$row[font] .chr(9).$row[fed].chr(9).$row[kursiv].chr(9).$row[side]."\n"); 
  }
  fclose($fp);
?>