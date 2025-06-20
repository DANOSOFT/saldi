<?php
  @session_start();
  $s_id=session_id();
  include("../includes/connect.php");
  include("../includes/online.php");

  transaktion("begin");

  db_modify("delete from formularer where formular != 4");

  $x=0;
  
  $query=db_select("select * from formularer ");
  while ($row=db_fetch_array($query)){
    $x++;
    $art[$x]=$row['art']*1;
    $beskrivelse[$x]=$row['beskrivelse'];
    $xa[$x]=$row['xa']*1;
    $ya[$x]=$row['ya']*1;
    $xb[$x]=$row['xb']*1;
    $yb[$x]=$row['yb']*1;
    $placering[$x]=$row['placering'];
    $str[$x]=$row['str']*1;
    $color[$x]=$row['color']*1;
    $formularfont[$x]=$row['font'];
    $fed[$x]=trim($row['fed']);
    $kursiv[$x]=trim($row['kursiv']);
    $side[$x]=$row['side'];
  }
  $antal=$x;
  
  for ($y=1; $y<=5; $y++) {
    if ($y==4) {$y++;}
    for ($x=1;$x<=$antal; $x++) {
echo "insert into formularer (formular, art, beskrivelse, xa, ya, xb, yb, placering, str, color, font, fed, kursiv, side) values  ('$y', '$art[$x]', '$beskrivelse[$x]', '$xa[$x]', '$ya[$x]', '$xb[$x]', '$yb[$x]', '$placering[$x]', '$str[$x]', '$color[$x]', '$formularfont[$x]', '$fed[$x]', '$kursiv[$x]', '$side[$x]')<br>";   
    db_modify("insert into formularer (formular, art, beskrivelse, xa, ya, xb, yb, placering, str, color, font, fed, kursiv, side) values  ('$y', '$art[$x]', '$beskrivelse[$x]', '$xa[$x]', '$ya[$x]', '$xb[$x]', '$yb[$x]', '$placering[$x]', '$str[$x]', '$color[$x]', '$formularfont[$x]', '$fed[$x]', '$kursiv[$x]', '$side[$x]')"); 
    }
}
transaktion("commit");
?>