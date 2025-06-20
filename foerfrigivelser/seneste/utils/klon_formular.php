<?php
@session_start();
$s_id=session_id();
include("../includes/connect.php");
include("../includes/online.php");
exit;
db_modify("delete from formularer where formular != '1' and formular != '6'");
$query=db_select("select * from formularer where formular = '1'");
while ($row=db_fetch_array($query)){
echo ".";
	db_modify("insert into formularer (formular, art, beskrivelse, xa, ya, xb, yb, placering, str, color, font, fed, kursiv, side) values ('2', '$row[art]', '$row[beskrivelse]', '$row[xa]', '$row[ya]', '$row[xb]', '$row[yb]', '$row[placering]', '$row[str]', '$row[color]', '$row[font]', '$row[fed]', '$row[kursiv]', '$row[side]')");
	db_modify("insert into formularer (formular, art, beskrivelse, xa, ya, xb, yb, placering, str, color, font, fed, kursiv, side) values ('3', '$row[art]', '$row[beskrivelse]', '$row[xa]', '$row[ya]', '$row[xb]', '$row[yb]', '$row[placering]', '$row[str]', '$row[color]', '$row[font]', '$row[fed]', '$row[kursiv]', '$row[side]')");
	db_modify("insert into formularer (formular, art, beskrivelse, xa, ya, xb, yb, placering, str, color, font, fed, kursiv, side) values ('4', '$row[art]', '$row[beskrivelse]', '$row[xa]', '$row[ya]', '$row[xb]', '$row[yb]', '$row[placering]', '$row[str]', '$row[color]', '$row[font]', '$row[fed]', '$row[kursiv]', '$row[side]')");
	db_modify("insert into formularer (formular, art, beskrivelse, xa, ya, xb, yb, placering, str, color, font, fed, kursiv, side) values ('5', '$row[art]', '$row[beskrivelse]', '$row[xa]', '$row[ya]', '$row[xb]', '$row[yb]', '$row[placering]', '$row[str]', '$row[color]', '$row[font]', '$row[fed]', '$row[kursiv]', '$row[side]')");
  }
Echo "<br>slut<br>";
?>