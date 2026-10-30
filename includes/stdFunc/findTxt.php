<?php

// 20250130 PHR migrate utf8_en-/decode() to mb_convert_encoding
// 20250829 PHR Returns textId if table tekster doeesn't exist
// 20250909 LOE checks first that db functions exist and error log added
// 20260320 LOE Updated $sessionVar to only allow values that won't corrupt the session array.
// 20260512 PHR CL/PHR Enabled cache.
// 20260518 CL/PHR Session-cache genaktiveret for findtekst() for bedre performance.
// 20260825 NTR fixed indentation and block bracket convention. (no logic change)
// 20260903 NTR moved sessionVar behind pipe | filter to avoid session key collisions when using the same textId in different contexts (e.g., "5001|Udl&oslash;bsdato" vs. "5001|Udløbs dato").
// 20261029 CL/NTR findtekst() now accepts descriptive string keys (tekst_key) as well as legacy numeric tekst_id. A key may be suffixed with '|fallback text' to provide a default when no translation is found. See doc/ai/convention_findtekst_keys.md.
// 20261029 CDX/NTR Restored helper definitions and input initialization lost while merging master.
// 20261029 CL/MMR SST-909 A text found in egnetekster.csv is kept: egne_tekster.csv and tekster.csv are only read when it gave none (it was thrown away before).
//                  An empty line in egnetekster.csv or egne_tekster.csv no longer raises "Undefined array key".

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

if (!function_exists('findtekst_keyed_seed')) {
	/**
	 * Look up one descriptive key in importfiler/egne_tekster_keyed.tsv, then tekster_keyed.tsv.
	 *
	 * The parsed key index is retained for the request so multiple uncached lookups do not repeatedly
	 * read and scan the complete seed file.
	 *
	 * @param string $textId     Descriptive tekst_key.
	 * @param int    $languageID Language column: 1=da, 2=en, 3=no.
	 * @return string|null Seeded translation, or null when the key/language is absent.
	 */
	function findtekst_keyed_seed($textId, $languageID) {
		static $seedFiles = null;
		if ($seedFiles === null) {
			$seedFiles = array();
			$fileNames = array("../importfiler/egne_tekster_keyed.tsv", "../importfiler/tekster_keyed.tsv");
			foreach ($fileNames as $fileName) {
				$seedRows = array();
				if (file_exists($fileName)) {
					$lines = file($fileName, FILE_IGNORE_NEW_LINES);
					if ($lines !== false) {
						foreach ($lines as $line) {
							$texts = explode("\t", rtrim($line, "\r"));
							if (($texts[0] ?? '') !== '') {
								$seedRows[$texts[0]] = $texts;
							}
						}
					}
				}
				$seedFiles[] = $seedRows;
			}
		}
		foreach ($seedFiles as $seedRows) {
			if (isset($seedRows[$textId][$languageID])) {
				return $seedRows[$textId][$languageID];
			}
		}
		return null;
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
		$b = null;
		$keyedText = null;
		$defaultText = '';
		$textId = (string)$textId;

		if (strpos($textId, '|') !== false) {
			// Left of the pipe is the lookup token (numeric id or string key); right is fallback text.
			list($a, $b) = explode('|', $textId, 2);
			$textId = $a;
			if (ctype_digit($textId)) {
				$defaultText = trim((string)$b);
			}
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
						// Every line is split, also one without tabs (only the id), so the id compare never reads a missing column (SST-909)
						$tmp = explode(chr(9), $linje);
						if ($textId == $tmp[0]) {
							$newTxt = $tmp[$languageID] ?? ''; # Linjen efter 1. tab.
						}
					}
				}
				fclose($fp);
			}
		}
		// egnetekster.csv has precedence over egne_tekster.csv and tekster.csv: keep its text (SST-909)
		if (($tekst === null || $tekst === '') && ($newTxt === null || $newTxt === '') && $textId) { # 20210304
			$newTxt = NULL;
			if (file_exists("../importfiler/egne_tekster.csv")) {
				$fp = fopen("../importfiler/egne_tekster.csv", "r");
				// A translation "0" is a text too, so presence is tested on null/'' and not on truthiness (SST-890)
				while (!feof($fp) && ($newTxt === null || $newTxt === '')) {
					if ($linje = trim(fgets($fp))) {
						$a = explode("\t", $linje);
						if ($a[0] == $textId) {
							$newTxt = $a[$languageID] ?? '';
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
