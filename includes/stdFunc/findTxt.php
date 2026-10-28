<?php

 // 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20250829 PHR Returns textId if table tekster doeesn't exist
// 20250909 LOE checks first that db functions exist and error log added
// 20260320 LOE Updated $sessionVar to only allow values that won't corrupt the session array.
// 20260512 PHR CL/PHR Enabled cache.
// 20260518 CL/PHR Session-cache genaktiveret for findtekst() for bedre performance.
// 20260825 NTR fixed indentation and block bracket convention. (no logic change)
// 20260903 NTR moved sessionVar behind pipe | filter to avoid session key collisions when using the same textId in different contexts (e.g., "5001|Udl&oslash;bsdato" vs. "5001|Udløbs dato").
// 20261022 CL/NTR findtekst() now accepts descriptive string keys (tekst_key) alongside numeric tekst_id, with key-first resolution, self-heal insert and a placeholder rule. See doc/ai/convention_findtekst_keys.md.
// 20261028 CL/NTR Schema-scoped the tekst_key column probe; $db_type pulled in via global.
// 20261028 CL/NTR Session cache key moved to findtekst_session_var() so editors can clear it after a change.
// 20261028 CL/NTR findtekst_session_var() no longer lets different tokens collapse to one key after sanitizing.
// 20261028 CDX/NTR Return the pipe fallback when an unresolved key cannot self-heal.


if (!function_exists('findtekst_session_var')) {
	/**
	 * The $_SESSION key findtekst() caches a text under.
	 *
	 * Unset this key after changing a text so the editing user sees the new value straight away.
	 *
	 * Distinct tokens never share a key. A token of only letters, digits, '.' and '-' with a numeric
	 * language (every numeric tekst_id and keys like 'common.accept') keeps the readable
	 * 'text_<token>_<language>' form; '_' is left out because it separates token and language.
	 * Anything else ('common_accept', literal text with spaces or æøå) would be ambiguous once
	 * sanitized, so it gets the separate 'texth_' prefix and a hash of the original token and language.
	 * findtekst() strips the '|fallback' part before calling this. As a safeguard anything from a
	 * remaining '|' onwards is dropped here too, because a '|' in a session key stops the php session
	 * serializer from saving the whole session.
	 *
	 * @param string|int $textId    Numeric tekst_id or tekst_key, without any '|fallback' suffix.
	 * @param int        $languageID Language id ($sprog_id).
	 * @return string The session key.
	 */
	function findtekst_session_var($textId, $languageID) {
		$textId = explode('|', (string)$textId, 2)[0];
		$languageID = (string)$languageID;
		if (preg_match('/^[a-zA-Z0-9_.-]+$/', $textId) && ctype_digit($languageID)) {
			return 'text_' . $textId . '_' . $languageID;
		}
		$readable = preg_replace('/[^a-zA-Z0-9_.-]/', '_', substr($textId, 0, 40));
		return 'texth_' . $readable . '_' . hash('sha256', $textId . "\0" . $languageID);
	}
}

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
	 * Key resolution order: tekst_key row -> legacy tekst-content match -> CSV seed files ->
	 * self-heal (insert the key so it surfaces for translation in systemdata/tekster.php).
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
		$b = null;

		if (strpos($textId,'|')) {
			// Left of the pipe is the lookup token (numeric id or string key); right is fallback text.
			list($a,$b) = explode('|',$textId,2);
			$textId = $a;
		}
		
		$sessionVar = findtekst_session_var($textId, $languageID);
		if (isset($_SESSION[$sessionVar])) {
			return ($_SESSION[$sessionVar]);
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

		if (!preg_match('/^[0-9]+$/', $textId)) {
			$lang = (!$languageID || $languageID > 3) ? 1 : $languageID;
			$placeholderId = 0;

			// 1. Resolve as a tekst_key. A row whose tekst equals its key (or is '-') is a
			//    not-yet-translated placeholder and must not be returned as a real translation.
			if ($hasKeyColumn) {
				$keyEsc = db_escape_string($textId);
				$qtxt = "select id,tekst from tekster where tekst_key='$keyEsc' and sprog_id='$lang'";
				if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					if ($r['tekst'] === '-') {
						$_SESSION[$sessionVar] = '';
						return '';
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

			// 3. Legacy fallback: look the token up in the CSV seed files.
			if (!$matched) {
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

			// 4. Still an unresolved key: self-heal so it surfaces in the admin UI for translation.
			//    Store the pipe fallback text when given, otherwise the key itself as a placeholder.
			if ($hasKeyColumn && $db != $sqdb && !preg_match('/^[0-9]+$/', $textId) && preg_match('/^[a-z0-9][a-z0-9_.-]{0,189}$/', $textId)) {
				$candidate = ($b !== null && $b !== '') ? $b : $textId;
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
				$_SESSION[$sessionVar] = $candidate;
				return $candidate;
			}
		}

		if (!preg_match('/^[0-9]+$/', $textId)) {
			if ($b !== null && $b !== '') {
				if ($db_encode != 'UTF8') {
					$b = mb_convert_encoding($b, 'ISO-8859-1', 'UTF-8');
				}
				return $b;
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
		if (!is_numeric($textId)){
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
				elseif (file_exists(("../includes/stdFunc/locateDir.php"))) include("../includes/stdFunc/locateDir.php");
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
			if ($db_encode != "UTF8") {
				$newTxt = mb_convert_encoding($newTxt, 'ISO-8859-1', 'UTF-8');
			}
			$newTxt = str_replace('\n\n', "\n\n", $newTxt);
			$tmp = db_escape_string($newTxt); #20140505
			if ($id)
				$qtxt = "update tekster set tekst='$tmp' where id=$id";
			else
				$qtxt = "insert into tekster(sprog_id,tekst_id,tekst) values ('$languageID','$textId','$tmp')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			$tekst = $newTxt;
		} elseif ($db == $sqdb) {
			$tekst = $newTxt;
		}
		if (!$tekst) {
			$tekst = "Tekst nr: $textId";
		}
		elseif ($tekst == "-") {
			$tekst = '';
		}
		$_SESSION[$sessionVar] = $tekst;
		return ($tekst);
	} //end of findtekst
}
?>
