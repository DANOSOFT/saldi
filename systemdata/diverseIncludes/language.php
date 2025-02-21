<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/diverseIncludes/language.php --- ver 4.0.3 --- 2021-10-09 --
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2021 saldi.dk aps
// ----------------------------------------------------------------------

// 20210517 LOE Converted bruger_id from negative for admin and used to query the database
// 20210818 LOE Added the active language id to the url
// 20210819 LOE Did some documentations
// 20210828 LOE Added some codes; This checks if the language already exists in tekster table
// 20211009 PHR simplyfying and cleanup


function language () {
	include("../includes/languages.php"); #20210112
	global $bgcolor,$bgcolor5,$bruger_id,$brugernavn;
	global $db;
	global $s_id,$sprog_id; 

	$languageId=$sprog_id;
	
	$csvfile = "../importfiler/tekster.csv";
	$g1 =csv_to_array($csvfile);
	$x=0;
	$user_id = null;
	$user_id = (abs($bruger_id)); //20210517
	

	print "<form name=diverse action=diverse.php?sektion=sprog method=post>";
	print "<tr><td colspan='6'><hr></td></tr>";
	print "<tr bgcolor='$bgcolor5'><td colspan='6'><b><u>".findtekst(801,$sprog_id)."</b></u></td></tr>"; // 20210303
	print "<tr><td colspan='6'><br></td></tr>";
	if (isset($_POST['newLanguageId']) && $_POST['newLanguageId'])  {
		$newLanguageId = $_POST['newLanguageId'];
		$qtxt = "select id from settings where var_name = 'languageId' and user_id='0'";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$qtxt = "update settings set var_value = '$newLanguageId' where id = '$r[id]'";
		} else {
			$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id) values ";
			$qtxt.= "('languageId','globals','$newLanguageId','Active default language','0')";
		}
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$languageId = $newLanguageId;
		include("../includes/connect.php");
		$qtxt = "update online set language_id = '$languageId' where session_id='$s_id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=diverse.php?sektion=sprog\">";
		exit;
	}
	include("../includes/connect.php");
	$qtxt = "select id, var_value from settings where var_name = 'languages' order by id limit 1";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$languages=explode(chr(9),$r['var_value']);
	include("../includes/online.php");
	
	$tekst1=findtekst(1,$sprog_id);
	$tekst2=findtekst(2,$sprog_id);
	for ($x=1; $x<count($languages);$x++) {
		if ($languageId == $x) $languageName  = $languages[$x];
	}
	if (!$languageId) {
		$languageId = 1;
		$languageName = 'Dansk';
	}
	#print "<tr><td title='Klik her for at rette tekster'><a href=tekster.php?sprog_id=1>$tekst1</a></td>";
	print "<tr><td title='Klik her for at rette tekster'><a href=tekster.php?sprog_id=$languageId>$languageName</a></td>"; #20210818
	print "<td><SELECT class='inputbox' NAME='newLanguageId' title='$tekst2'>";
	#		if ($box3[$x]) print"<option>$box3[$x]</option>";
	for ($x=1; $x<count($languages);$x++) {
		if ($languageId == $x) print "<option value='$x'>$languages[$x]</option>";
	}
	for ($x=1; $x<count($languages);$x++) {
		if ($languageId != $x) print "<option value='$x'>$languages[$x]</option>";
	}
	print "</SELECT></td></tr>";
#	}
	print "<tr><td><br></td></tr>";

	$tekst1=findtekst(3,$sprog_id);
	print "<tr><td align = right colspan='4'><input class='button green medium' type=submit value='$tekst1' name='submit'></td></tr>";
	#	print "<td align = center><input type=submit value='$tekst2' name='submit'></td>";
	#	print "<td align = center><input type=submit value='$tekst3' name='submit'></td><tr>";
	/*
	print "</tbody></table></td></tr>";
	*/
	print "</form>";
} # endfunc sprog







 
?>
