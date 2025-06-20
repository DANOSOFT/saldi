<?php
  @session_start();
  $s_id=session_id();
  include("../includes/connect.php");
  include("../includes/online.php");

  db_modify("delete from formularer");
  $fp=fopen("../temp/$db.formular.txt","r");
  if ($fp) {
    while (!feof($fp)) {
      list($formular, $art, $beskrivelse, $xa, $ya, $xb, $yb, $placering, $str, $color, $font, $fed, $kursiv, $side) = split(chr(9), fgets($fp));
      if ($xa>0) {
        $placering=trim($placering); $form=trim($font); $fed=trim($fed); $kursiv=trim($kursiv); $side=trim($side); 
        $xa= $xa*1; $ya= $ya*1; $xb= $xb*1; $yb=$yb*1; $str=$str*1; $color=$color*1;
echo "insert into formularer (formular, art, beskrivelse, xa, ya, xb, yb, placering, str, color, font, fed, kursiv, side) values  ('$formular', '$art', '$beskrivelse', '$xa', '$ya', '$xb', '$yb', '$placering', '$str', '$color', '$font', '$fed', '$kursiv', '$side')<br>";
       db_modify("insert into formularer (formular, art, beskrivelse, xa, ya, xb, yb, placering, str, color, font, fed, kursiv, side) values  ('$formular', '$art', '$beskrivelse', '$xa', '$ya', '$xb', '$yb', '$placering', '$str', '$color', '$font', '$fed', '$kursiv', '$side')"); 
      }
    }
    fclose($fp);
  }
?>