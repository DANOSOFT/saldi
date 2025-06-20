<?php
  @session_start();
  $s_id=session_id();

// --------------------------------/admin/formularimport.php-----patch 0.935----------------------------------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2005 ITz ApS
// ----------------------------------------------------------------------
// 120905 - Tilfjet feltet kostpris i tabellen "ordrer"

if ((!$sqhost)||(!$dbuser)||(!$db))
{
  include("../includes/connect.php");
  include("../includes/online.php");
  include("../includes/db_query.php");

}
$fp=fopen("../importfiler/formular.txt","r");
if ($fp) {
  while (!feof($fp)) {
    list($formular, $art, $beskrivelse, $placering, $xa, $ya, $xb, $yb, $str, $color, $font, $fed, $kursiv, $side) = split(chr(9), fgets($fp));
    if ($xa>0) {
      $placering=trim($placering); $form=trim($font); $fed=trim($fed); $kursiv=trim($kursiv); $side=trim($side); 
      $xa= $xa*1; $ya= $ya*1; $xb= $xb*1; $yb=$yb*1; $str=$str*1; $color=$color*1;
      db_modify("insert into formularer (formular, art, beskrivelse, xa, ya, xb, yb, placering, str, color, font, fed, kursiv, side) values  ('$formular', '$art', '$beskrivelse', '$xa', '$ya', '$xb', '$yb', '$placering', '$str', '$color', '$font', '$fed', '$kursiv', '$side')"); 
    }
  }
  fclose($fp);
}
?>
</tbody></table>
</body></html>
