<?php

 // 20250130 migrate utf8_en-/decode() to mb_convert_encoding
 // 20250829 PHR Returns textId if table tekster doeesn't exist
 // 20250909 LOE checks first that db functions exist and error log added
if (!function_exists('findtekst')) {
	function findtekst($textId, $languageID) {
		
	$sessionVar = 'text_'. $textId .'_'. $languageID;
#	if (isset($_SESSION[$sessionVar])) return ($_SESSION[$sessionVar]);

		global $bruger_id;
		global $db, $db_encode;
		global $sqdb;
		global $webservice;
		$id = 0;

		if (strpos($textId,'|')) {
			list($a,$b) = explode('|',$textId);
			if (preg_match('/^[0-9]+$/', $a)) $textId = $a;
		} #else $a = $textId;
		 $qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='tekster'";
		// if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		// 	if ($b) return $b;
		// 	else return $textId;
		// }

		########################
		if (function_exists('db_fetch_array') && function_exists('db_select')) {
			if (!$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				if ($b) return $b;
				else return $textId;
			}
		} else {
			// Handle the error gracefully if the function doesn't exist
			error_log("Missing required database functions: db_fetch_array or db_select, textId: $textId");
			return $textId; 
		}
		######################

		if (!preg_match('/^[0-9]+$/', $textId)) {
		$qtxt = "select tekst_id from tekster where tekst = '$textId'";
		if ($r = db_fetch_array(db_select($qtxt, ''))) {
			$textId = $r['tekst_id'];
		} else {
			$txtlines = array();
			if (file_exists("../importfiler/egnetekster.csv")) $fileName = "../importfiler/egnetekster.csv";
			else $fileName = "../importfiler/tekster.csv";
			$txtlines = explode("\n",file_get_contents($fileName));
			for ($i = 0; $i < count($txtlines); $i++) {
				$texts = explode("\t",$txtlines[$i]);
				if (in_array($textId,$texts)) {
					for ($i2 = 1; $i2 < count($texts); $i2++) {
						if ($textId == $texts[$i2]) $textId = $texts[0];
						break 2;
					}
				}
			}
		}
	}

	if (!preg_match('/^[0-9]+$/', $textId)) {
		return $textId; # If other characters than digits in textID then return textID - used when developing # 20230224
	}
		#echo "L $languageID B $bruger_id<br>";

		$linje = $newTxt = $tekst = $tmp = NULL;
		$textId = trim($textId);
		if (!$languageID || $languageID > 3) {
			$languageID = 1;
			$qtxt = "update brugere set language_id = '$languageID' where id = '$bruger_id'";
		}
		if (!is_numeric($textId))
			$textId = 0;
		$qtxt = "select id,tekst from tekster where tekst_id='$textId' and sprog_id = '$languageID'";
		if ($db != $sqdb && $r = db_fetch_array(db_select($qtxt, ''))) {
			$tekst = $r['tekst'];
			$id = $r['id'];
		} elseif (file_exists("../importfiler/egnetekster.csv")) {
			$fp = fopen("../importfiler/egnetekster.csv", "r");
			if ($fp) {
				$tmp = array();
				while (!feof($fp)) {
					if ($linje = trim(fgets($fp))) {
						if (strpos($linje, chr(9)))
							$tmp = explode(chr(9), $linje);
						if ($textId == $tmp[0])
							$newTxt = $tmp[$languageID]; # Linjen efter 1. tab. 
					}
				}
				fclose($fp);
			}
		}
		if (!$tekst && $textId) { # 20210304
			$newTxt = NULL;
			if (file_exists("../importfiler/egne_tekster.csv")) {
				$fp = fopen("../importfiler/egne_tekster.csv", "r");
				while (!feof($fp) && !$newTxt) {
					if ($linje = trim(fgets($fp))) {
						$a = explode("\t", $linje);
						if ($a[0] == $textId) {
							$newTxt = $a[$languageID];
						}
					}
				}
				fclose($fp);
			}
			if (!function_exists('locateDir')) {
				if (file_exists(("../stdFunc/locateDir.php"))) include("../stdFunc/locateDir.php");
				else if (file_exists(("../includes/stdFunc/locateDir.php"))) include("../includes/stdFunc/locateDir.php");
			}
			$fiE3E = locateDir("importfiler");
			$fp = fopen("$fiE3E/tekster.csv", "r");
			while (!feof($fp) && !$newTxt) {
				if ($linje = trim(fgets($fp))) {
					$a = explode("\t", $linje);
					if ($a[0] == $textId) {
						$newTxt = $a[$languageID];
					}
				}
			}
		}
		if ($db != $sqdb && $newTxt && $newTxt != '-') {
			if ($db_encode != "UTF8")
				$newTxt = mb_convert_encoding($newTxt, 'ISO-8859-1', 'UTF-8');
			$newTxt = str_replace('\n\n', "\n\n", $newTxt);
			$tmp = db_escape_string($newTxt); #20140505
			if ($id)
				$qtxt = "update tekster set tekst='$tmp' where id=$id";
			else
				$qtxt = "insert into tekster(sprog_id,tekst_id,tekst) values ('$languageID','$textId','$tmp')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			$tekst = $newTxt;
		} elseif ($db == $sqdb)
			$tekst = $newTxt;
		if (!$tekst)
			$tekst = "Tekst nr: $textId";
		elseif ($tekst == "-")
			$tekst = '';
		$_SESSION[$sessionVar] = $tekst;
		return ($tekst);
	} //end of findtekst
}
?>
