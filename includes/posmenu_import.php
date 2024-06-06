<?php
// --- /systemdata/posmenu_import.php ---------- vers. 4.0.5 -- 2022-03-07 --
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
// Copyright (c) 2005-2022 Saldi.dk ApS
// --------------------------------------------------------------------------
// 20211124 CA  Import POS menus from a file
// 20220307 PHR Set db_escape_string on 'beskrivelse'

function posmenu_import($filnavn) {

global $db_encode;

$fp=fopen($filnavn,"r");
if ($fp) {
	$qtxt="delete from pos_buttons";
	db_modify("$qtxt",__FILE__ . " linje " . __LINE__); # 20130819
	$qtxt="delete from grupper where art = 'POSBUT'";
	db_modify("$qtxt",__FILE__ . " linje " . __LINE__); # 20130819
	$importtable=0;
	while (!feof($fp)) {
		$linje=fgets($fp);
		$linje=str_replace("\n","",$linje);
		if ( strlen($linje) < 3 ) { 
			$importtable++;
		} else {
			if ($db_encode=="UTF8") $linje=utf8_encode($linje);
			if ( $importtable===1 ) { # Import table pos_buttons
				list($menu_id, $row, $col, $beskrivelse, $color, $funktion, $vare_id, $colspan, $rowspan) = explode(chr(9),$linje);
				if ((substr($menu_id,0,1))=="'"&&(substr($menu_id,-1))=="'") $menu_id=(substr($menu_id,1,strlen($menu_id)-2));
				if ((substr($row,0,1))=="'"&&(substr($row,-1))=="'") $row=(substr($row,1,strlen($row)-2));
				if ((substr($col,0,1))=="'"&&(substr($col,-1))=="'") $col=(substr($col,1,strlen($col)-2));
				if ((substr($beskrivelse,0,1))=="'"&&(substr($beskrivelse,-1))=="'") $beskrivelse=(substr($beskrivelse,1,strlen($beskrivelse)-2));
				if ((substr($color,0,1))=="'"&&(substr($color,-1))=="'") $color=(substr($color,1,strlen($color)-2));
				if ((substr($funktion,0,1))=="'"&&(substr($funktion,-1))=="'") $funktion=(substr($funktion,1,strlen($funktion)-2));
				if ((substr($vare_id,0,1))=="'"&&(substr($vare_id,-1))=="'") $vare_id=(substr($vare_id,1,strlen($vare_id)-2));
				if ((substr($colspan,0,1))=="'"&&(substr($colspan,-1))=="'") $colspan=(substr($colspan,1,strlen($colspan)-2));
				if ((substr($rowspan,0,1))=="'"&&(substr($rowspan,-1))=="'") $rowspan=(substr($rowspan,1,strlen($rowspan)-2));
				$beskrivelse=addslashes($beskrivelse);
				$color=trim($color); 
				$menu_id=$menu_id*1; $col= $col*1; $row= $row*1; $colspan= $colspan*1; $rowspan=$rowspan*1; $funktion=$funktion*1; $vare_id=$vare_id*1;
				$qtxt = "insert into pos_buttons (menu_id,row,col,beskrivelse,color,funktion,vare_id,colspan,rowspan) values ";
				$qtxt.= "('$menu_id','$row','$col','". db_escape_string($beskrivelse) ."','$color','$funktion','$vare_id','$colspan','$rowspan')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
			} else if ( $importtable===2 ) { # Import table grupper
				list($beskrivelse, $art, $kode, $kodenr, $box1, $box2, $box3, $box4, $box5, $box6, $box7, $box8, $box9, $box10, $box11, $box12) = explode(chr(9),$linje);
				if ((substr($beskrivelse,0,1))=="'"&&(substr($menu_id,-1))=="'") $menu_id=(substr($menu_id,1,strlen($menu_id)-2));
				if ((substr($art,0,1))=="'"&&(substr($art,-1))=="'") $art=(substr($art,1,strlen($art)-2));
				if ((substr($kode,0,1))=="'"&&(substr($kode,-1))=="'") $kode=(substr($kode,1,strlen($kode)-2));
				if ((substr($kodenr,0,1))=="'"&&(substr($kodenr,-1))=="'") $kodenr=(substr($kodenr,1,strlen($kodenr)-2));
				if ((substr($box1,0,1))=="'"&&(substr($box1,-1))=="'") $box1=(substr($box1,1,strlen($box1)-2));
				if ((substr($box2,0,1))=="'"&&(substr($box2,-1))=="'") $box2=(substr($box2,1,strlen($box2)-2));
				if ((substr($box3,0,1))=="'"&&(substr($box3,-1))=="'") $box3=(substr($box3,1,strlen($box3)-2));
				if ((substr($box4,0,1))=="'"&&(substr($box4,-1))=="'") $box4=(substr($box4,1,strlen($box4)-2));
				if ((substr($box5,0,1))=="'"&&(substr($box5,-1))=="'") $box5=(substr($box5,1,strlen($box5)-2));
				if ((substr($box6,0,1))=="'"&&(substr($box6,-1))=="'") $box6=(substr($box6,1,strlen($box6)-2));
				if ((substr($box7,0,1))=="'"&&(substr($box7,-1))=="'") $box7=(substr($box7,1,strlen($box7)-2));
				if ((substr($box8,0,1))=="'"&&(substr($box8,-1))=="'") $box8=(substr($box8,1,strlen($box8)-2));
				if ((substr($box9,0,1))=="'"&&(substr($box9,-1))=="'") $box9=(substr($box9,1,strlen($box9)-2));
				if ((substr($box10,0,1))=="'"&&(substr($box10,-1))=="'") $box10=(substr($box10,1,strlen($box10)-2));
				if ((substr($box11,0,1))=="'"&&(substr($box11,-1))=="'") $box11=(substr($box11,1,strlen($box11)-2));
				if ((substr($box12,0,1))=="'"&&(substr($box12,-1))=="'") $box12=(substr($box12,1,strlen($box12)-2));
				$beskrivelse=addslashes($beskrivelse);
				$art=trim($art); $box1=trim($box1); $box6=trim($box6); $box7=trim($box7); $box8=trim($box8); $box9=trim($box); $box12=trim($box12);
				$xa= $xa*1; $ya= $ya*1; $xb= $xb*1; $yb=$yb*1; $str=$str*1; $color=$color*1;
				db_modify("insert into grupper (beskrivelse,art,kode,kodenr,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12)values('$beskrivelse','$art','$kode','$kodenr','$box1','$box2','$box3','$box4','$box5','$box6','$box7','$box8','$box9','$box10','$box11','$box12')",__FILE__ . " linje " . __LINE__); 
			}
		}
	}
	fclose($fp);
}
}
?>
</tbody></table>
</body></html>
