<?php

 // 20250130 migrate utf8_en-/decode() to mb_convert_encoding
 // 20250829 PHR Returns textId if table tekster doeesn't exist
 // 20250909 LOE checks first that db functions exist and error log added
 // 20260320 LOE Updated $sessionVar to only allow values that won't corrupt the session array.
 // 20260512 PHR CL/PHR Enabled cache.
 // 20260518 CL/PHR Session-cache genaktiveret for findtekst() for bedre performance.
 // 20260825 NTR fixed indentation and block bracket convention. (no logic change)
 // 20260903 NTR moved sessionVar behind pipe | filter to avoid session key collisions when using the same textId in different contexts (e.g., "5001|Udl&oslash;bsdato" vs. "5001|Udløbs dato").
if (!function_exists('findtekst')) {
	/**
	 * Look up a translated text for a language.
	 *
	 * The first argument may be:
	 *   - a numeric legacy id   -> matched against tekster.tekst_id (unchanged behaviour), or
	 *   - a descriptive string key (e.g. 'common.accept') -> matched against tekster.tekst_key, or
	 *   - either of the above suffixed with '|fallback text' (the left side is the lookup token,
	 *     the right side is fallback text used when nothing is translated yet).
	 *
	 * Key resolution order: tekst_key row -> legacy tekst-content match -> custom/standard keyed TSV seed ->
	 * legacy numeric CSV literal match -> self-heal insert.
	 * A row whose tekst equals its tekst_key is a not-yet-translated placeholder and is never
	 * returned as an authoritative translation.
	 *
	 * @param string|int $textId    Numeric tekst_id, a tekst_key, or either with a '|fallback' suffix.
	 * @param int        $languageID Language id ($sprog_id); 1=da, 2=en, 3=no. Out-of-range falls back to 1.
	 * @return string The text in the database encoding (use findtekst_utf8() when UTF-8 is required).
	 */
	function findtekst($textId, $languageID) {
		global $bruger_id;
		global $db, $db_encode, $db_type;
		global $sqdb;
		global $webservice;
		$id = 0;

		if (strpos($textId,'|')) {
			list($a,$b) = explode('|',$textId);
			if (preg_match('/^[0-9]+$/', $a)) $textId = $a;
		}
		
		$sessionVar = findtekst_session_var($textId, $languageID);
		if (isset($_SESSION[$sessionVar])) {
			return ($_SESSION[$sessionVar]);
		}
		// A fallback (the call's default text or the "Tekst nr" placeholder) is cached per default text (SST-890)
		$fallbackVar = $sessionVar . '_d' . crc32($defaultText);
		if (isset($_SESSION[$fallbackVar])) {
			return ($_SESSION[$fallbackVar]);
		}
		$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='tekster'";

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

		// Detect the new descriptive-key column once per request. During rollout a tenant that
		// has not yet run betweenUpdates.php won't have it, so fall back to the legacy behaviour.
		static $hasKeyColumn = null;
		if ($hasKeyColumn === null) {
			$colq = "SELECT column_name FROM information_schema.columns WHERE table_name='tekster' AND column_name='tekst_key'";
			$colq .= in_array($db_type, ['mysql', 'mysqli'], true) ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
			$hasKeyColumn = (bool) db_fetch_array(db_select($colq, __FILE__ . " linje " . __LINE__));
		}

		if (!ctype_digit((string)$textId)) {
			$lang = (!$languageID || $languageID > 3) ? 1 : $languageID;
			$placeholderId = 0;

			// 1. Resolve as a tekst_key. A row whose tekst equals its key (or is '-') is a
			//    not-yet-translated placeholder.
			if ($hasKeyColumn) {
				$keyEsc = db_escape_string($textId);
				$qtxt = "select id,tekst from tekster where tekst_key='$keyEsc' and sprog_id='$lang'";
				if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					if ($r['tekst'] === '-') {
						return ($b !== null && $b !== '') ? $b : $textId;
					}
					if ($r['tekst'] !== '' && $r['tekst'] !== null && $r['tekst'] !== $textId) {
						$_SESSION[$sessionVar] = $r['tekst'];
						return $r['tekst'];
					}
					$placeholderId = $r['id'];
				}
			}

			// 2. Legacy fallback: the caller passed literal text content. Exclude placeholder rows
			//    (tekst == tekst_key) so a self-healed key can't match itself here.
			$matched = false;
			$contentEsc = db_escape_string($textId);
			$qtxt = "select tekst_id from tekster where tekst = '$contentEsc'";
			if ($hasKeyColumn) {
				$qtxt .= " and (tekst_key is null or tekst <> tekst_key)";
			}
			if (($r = db_fetch_array(db_select($qtxt, ''))) && $r['tekst_id'] !== null && $r['tekst_id'] !== '') {
				$textId = $r['tekst_id'];
				$matched = true;
			}

			// 3. Resolve a descriptive key from the keyed TSV seed file. Column 0 is
			//    always a tekst_key; columns 1-3 are Danish, English and Norwegian.
			if (!$matched) {
				$keyedText = findtekst_keyed_seed($textId, $lang);
			}

			// 4. Legacy fallback: a caller may pass literal translated text. The token is
			//    nonnumeric and column 0 is integer-only, so a strict row match can only be
			//    in translation columns 1-3; resolve it to the id in column 0.
			if (!$matched && $keyedText === null) {
				if (file_exists("../importfiler/egnetekster.csv")) {
					$fileName = "../importfiler/egnetekster.csv";
				} else {
					$fileName = "../importfiler/tekster.csv";
				}
				$txtlines = explode("\n", file_get_contents($fileName));
				for ($i = 0; $i < count($txtlines); $i++) {
					$texts = explode("\t", rtrim($txtlines[$i], "\r"));
					if (in_array($textId, $texts, true)) {
						$textId = $texts[0];
						break;
					}
				}
			}

			// 5. Still an unresolved key: self-heal so it surfaces in the admin UI for translation.
			//    Prefer the keyed TSV translation, then the pipe fallback, then a key placeholder.
			if ($hasKeyColumn && $db != $sqdb && !ctype_digit((string)$textId) && preg_match('/^[a-z0-9][a-z0-9_.-]{0,189}$/', $textId)) {
				$candidate = $keyedText ?? (($b !== null && $b !== '') ? $b : $textId);
				if ($db_encode != "UTF8") {
					$candidate = mb_convert_encoding($candidate, 'ISO-8859-1', 'UTF-8');
				}
				$keyEsc = db_escape_string($textId);
				$candEsc = db_escape_string($candidate);
				if ($placeholderId) {
					// Only upgrade a placeholder once we have something better than the key itself.
					if ($candidate !== $textId) {
						db_modify("update tekster set tekst='$candEsc' where id='$placeholderId'", __FILE__ . " linje " . __LINE__);
					}
				} else {
					db_modify("insert into tekster(sprog_id,tekst_key,tekst) values ('$lang','$keyEsc','$candEsc')", __FILE__ . " linje " . __LINE__);
				}
				$resolvedText = ($candidate === '-') ? '' : $candidate;
				$_SESSION[$sessionVar] = $resolvedText;
				return $resolvedText;
			}
		}

		if (!ctype_digit((string)$textId)) {
			$candidate = $keyedText ?? $b;
			if ($candidate !== null && $candidate !== '') {
				if ($db_encode != 'UTF8') {
					$candidate = mb_convert_encoding($candidate, 'ISO-8859-1', 'UTF-8');
				}
				return ($candidate === '-') ? '' : $candidate;
			}
			return $textId; # If other characters than digits in textID then return textID - used when developing # 20230224
		}
		#echo "L $languageID B $bruger_id<br>";

		$linje = $newTxt = $tekst = $tmp = NULL;
		$textId = trim($textId);
		if (!$languageID || $languageID > 3) {
			$languageID = 1;
			$qtxt = "update brugere set language_id = '$languageID' where id = '$bruger_id'";
		}
		if (!ctype_digit((string)$textId)) {
			$textId = 0;
		}
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
						if (strpos($linje, chr(9))) {
							$tmp = explode(chr(9), $linje);
						}
						if ($textId == $tmp[0]) {
							$newTxt = $tmp[$languageID]; # Linjen efter 1. tab. 
						}
					}
				}
				fclose($fp);
			}
		}
		if (($tekst === null || $tekst === '') && $textId) { # 20210304
			$newTxt = NULL;
			if (file_exists("../importfiler/egne_tekster.csv")) {
				$fp = fopen("../importfiler/egne_tekster.csv", "r");
				// A translation "0" is a text too, so presence is tested on null/'' and not on truthiness (SST-890)
				while (!feof($fp) && ($newTxt === null || $newTxt === '')) {
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
				include_once(__DIR__ . '/locateDir.php');
			}
			$fiE3E = locateDir("importfiler");
			$fp = fopen("$fiE3E/tekster.csv", "r");
			while (!feof($fp) && ($newTxt === null || $newTxt === '')) {
				if ($linje = trim(fgets($fp))) {
					$a = explode("\t", $linje);
					if ($a[0] == $textId) {
						// trim() also strips the tabs of an empty line ("4981\t\t\t"), so the language column may be gone (SST-890)
						$newTxt = $a[$languageID] ?? '';
					}
				}
			}
		}
		if ($db != $sqdb && $newTxt !== null && $newTxt !== '' && $newTxt !== '-') {
			if ($db_encode != "UTF8") {
				$newTxt = mb_convert_encoding($newTxt, 'ISO-8859-1', 'UTF-8');
			}
			$newTxt = str_replace('\n\n', "\n\n", $newTxt);
			$tmp = db_escape_string($newTxt); #20140505
			if ($id) {
				$qtxt = "update tekster set tekst='$tmp' where id=$id";
			} else {
				$qtxt = "insert into tekster(sprog_id,tekst_id,tekst) values ('$languageID','$textId','$tmp')";
			}
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			$tekst = $newTxt;
		} elseif ($db == $sqdb) {
			$tekst = $newTxt;
		}
		// A fallback text (the call's default or the placeholder) is cached under $fallbackVar, not under the text id,
		// because another call with the same id may carry another default (SST-890).
		$isFallback = false;
		if (($tekst === null || $tekst === '') && $defaultText !== '') {
			// SST-890: the text is missing or empty in tekster.csv on this server (e.g. a new id whose csv
			// line is not deployed yet). Show the call's default text instead of "Tekst nr: N". It is not
			// stored in tekster, so the csv text is still picked up once it is there.
			$tekst = $defaultText;
			$isFallback = true;
			if ($db != $sqdb && $db_encode != "UTF8") {
				$tekst = mb_convert_encoding($tekst, 'ISO-8859-1', 'UTF-8');
			}
		}
		if ($tekst === null || $tekst === '') {
			$tekst = "Tekst nr: $textId";
			$isFallback = true;
		}
		elseif ($tekst == "-") {
			$tekst = '';
		}
		if ($isFallback) {
			$_SESSION[$fallbackVar] = $tekst;
		} else {
			$_SESSION[$sessionVar] = $tekst;
		}
		return ($tekst);
	} //end of findtekst
}
?>
