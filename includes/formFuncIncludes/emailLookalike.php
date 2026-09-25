<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/formFuncIncludes/emailLookalike.php --- patch 5.0.0 --- 2026-09-09 ---
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260909 Sawaneh SST-759: Explain why a recipient address is rejected (character,
//                  code point, position), suggest the ASCII equivalent for known
//                  lookalikes and let the user apply it explicitly on the order.
// 20260910 Sawaneh SST-759: Apply only when the stored recipient is unchanged since it was
//                  shown (conditional update + re-read), per CodeRabbit review on PR #583.

if (!function_exists('emailLookalikeTable')) {
	/**
	 * Known non-ASCII characters that are pasted into addresses by mistake.
	 *
	 * @return array<int, array{0: string, 1: string}> codepoint => [ASCII replacement ('' = remove), Unicode name]
	 */
	function emailLookalikeTable() {
		static $table = null;
		if ($table !== null) {
			return $table;
		}
		$table = [
			0x0009 => ['', 'CHARACTER TABULATION'],
			0x000A => ['', 'LINE FEED'],
			0x000D => ['', 'CARRIAGE RETURN'],
			0x00A0 => ['', 'NO-BREAK SPACE'],
			0x00AD => ['', 'SOFT HYPHEN'],
			0x061C => ['', 'ARABIC LETTER MARK'],
			0x1680 => ['', 'OGHAM SPACE MARK'],
			0x180E => ['', 'MONGOLIAN VOWEL SEPARATOR'],
			0x2000 => ['', 'EN QUAD'],
			0x2001 => ['', 'EM QUAD'],
			0x2002 => ['', 'EN SPACE'],
			0x2003 => ['', 'EM SPACE'],
			0x2004 => ['', 'THREE-PER-EM SPACE'],
			0x2005 => ['', 'FOUR-PER-EM SPACE'],
			0x2006 => ['', 'SIX-PER-EM SPACE'],
			0x2007 => ['', 'FIGURE SPACE'],
			0x2008 => ['', 'PUNCTUATION SPACE'],
			0x2009 => ['', 'THIN SPACE'],
			0x200A => ['', 'HAIR SPACE'],
			0x200B => ['', 'ZERO WIDTH SPACE'],
			0x200C => ['', 'ZERO WIDTH NON-JOINER'],
			0x200D => ['', 'ZERO WIDTH JOINER'],
			0x200E => ['', 'LEFT-TO-RIGHT MARK'],
			0x200F => ['', 'RIGHT-TO-LEFT MARK'],
			0x2010 => ['-', 'HYPHEN'],
			0x2011 => ['-', 'NON-BREAKING HYPHEN'],
			0x2012 => ['-', 'FIGURE DASH'],
			0x2013 => ['-', 'EN DASH'],
			0x2014 => ['-', 'EM DASH'],
			0x2015 => ['-', 'HORIZONTAL BAR'],
			0x2024 => ['.', 'ONE DOT LEADER'],
			0x2028 => ['', 'LINE SEPARATOR'],
			0x2029 => ['', 'PARAGRAPH SEPARATOR'],
			0x202A => ['', 'LEFT-TO-RIGHT EMBEDDING'],
			0x202B => ['', 'RIGHT-TO-LEFT EMBEDDING'],
			0x202C => ['', 'POP DIRECTIONAL FORMATTING'],
			0x202D => ['', 'LEFT-TO-RIGHT OVERRIDE'],
			0x202E => ['', 'RIGHT-TO-LEFT OVERRIDE'],
			0x202F => ['', 'NARROW NO-BREAK SPACE'],
			0x2043 => ['-', 'HYPHEN BULLET'],
			0x205F => ['', 'MEDIUM MATHEMATICAL SPACE'],
			0x2060 => ['', 'WORD JOINER'],
			0x2212 => ['-', 'MINUS SIGN'],
			0x3000 => ['', 'IDEOGRAPHIC SPACE'],
			0x3002 => ['.', 'IDEOGRAPHIC FULL STOP'],
			0xFE63 => ['-', 'SMALL HYPHEN-MINUS'],
			0xFE6B => ['@', 'SMALL COMMERCIAL AT'],
			0xFEFF => ['', 'ZERO WIDTH NO-BREAK SPACE'],
			0xFF0D => ['-', 'FULLWIDTH HYPHEN-MINUS'],
			0xFF0E => ['.', 'FULLWIDTH FULL STOP'],
			0xFF20 => ['@', 'FULLWIDTH COMMERCIAL AT'],
			0xFF3F => ['_', 'FULLWIDTH LOW LINE'],
			0xFF61 => ['.', 'HALFWIDTH IDEOGRAPHIC FULL STOP'],
			0x0391 => ['A', 'GREEK CAPITAL LETTER ALPHA'],
			0x0392 => ['B', 'GREEK CAPITAL LETTER BETA'],
			0x0395 => ['E', 'GREEK CAPITAL LETTER EPSILON'],
			0x0396 => ['Z', 'GREEK CAPITAL LETTER ZETA'],
			0x0397 => ['H', 'GREEK CAPITAL LETTER ETA'],
			0x0399 => ['I', 'GREEK CAPITAL LETTER IOTA'],
			0x039A => ['K', 'GREEK CAPITAL LETTER KAPPA'],
			0x039C => ['M', 'GREEK CAPITAL LETTER MU'],
			0x039D => ['N', 'GREEK CAPITAL LETTER NU'],
			0x039F => ['O', 'GREEK CAPITAL LETTER OMICRON'],
			0x03A1 => ['P', 'GREEK CAPITAL LETTER RHO'],
			0x03A4 => ['T', 'GREEK CAPITAL LETTER TAU'],
			0x03A5 => ['Y', 'GREEK CAPITAL LETTER UPSILON'],
			0x03A7 => ['X', 'GREEK CAPITAL LETTER CHI'],
			0x03BF => ['o', 'GREEK SMALL LETTER OMICRON'],
			0x0410 => ['A', 'CYRILLIC CAPITAL LETTER A'],
			0x0412 => ['B', 'CYRILLIC CAPITAL LETTER VE'],
			0x0415 => ['E', 'CYRILLIC CAPITAL LETTER IE'],
			0x041A => ['K', 'CYRILLIC CAPITAL LETTER KA'],
			0x041C => ['M', 'CYRILLIC CAPITAL LETTER EM'],
			0x041D => ['H', 'CYRILLIC CAPITAL LETTER EN'],
			0x041E => ['O', 'CYRILLIC CAPITAL LETTER O'],
			0x0420 => ['P', 'CYRILLIC CAPITAL LETTER ER'],
			0x0421 => ['C', 'CYRILLIC CAPITAL LETTER ES'],
			0x0422 => ['T', 'CYRILLIC CAPITAL LETTER TE'],
			0x0425 => ['X', 'CYRILLIC CAPITAL LETTER HA'],
			0x0430 => ['a', 'CYRILLIC SMALL LETTER A'],
			0x0435 => ['e', 'CYRILLIC SMALL LETTER IE'],
			0x043E => ['o', 'CYRILLIC SMALL LETTER O'],
			0x0440 => ['p', 'CYRILLIC SMALL LETTER ER'],
			0x0441 => ['c', 'CYRILLIC SMALL LETTER ES'],
			0x0443 => ['y', 'CYRILLIC SMALL LETTER U'],
			0x0445 => ['x', 'CYRILLIC SMALL LETTER HA'],
			0x0455 => ['s', 'CYRILLIC SMALL LETTER DZE'],
			0x0456 => ['i', 'CYRILLIC SMALL LETTER BYELORUSSIAN-UKRAINIAN I'],
			0x0458 => ['j', 'CYRILLIC SMALL LETTER JE'],
			0x04BB => ['h', 'CYRILLIC SMALL LETTER SHHA'],
			0x0501 => ['d', 'CYRILLIC SMALL LETTER KOMI DE'],
		];
		return $table;
	}
}

if (!function_exists('emailLookalikeUtf8')) {
	/**
	 * Returns the address as valid UTF-8 so it can be walked character by character.
	 *
	 * @param string $email
	 * @return string
	 */
	function emailLookalikeUtf8($email) {
		if (mb_check_encoding($email, 'UTF-8')) {
			return $email;
		}
		return mb_convert_encoding($email, 'UTF-8', 'Windows-1252');
	}
}

if (!function_exists('emailLookalikeAnalyse')) {
	/**
	 * Finds every non-ASCII character in an address and the ASCII address it would become.
	 *
	 * @param string $email
	 * @return array{
	 *   input: string,
	 *   suggested: string,
	 *   fixable: bool,
	 *   issues: array<int, array{
	 *     position: int,
	 *     char: string,
	 *     codepoint: string,
	 *     name: string,
	 *     replacement: string,
	 *     known: bool
	 *   }>
	 * }
	 */
	function emailLookalikeAnalyse($email) {
		$email = emailLookalikeUtf8($email);
		$table = emailLookalikeTable();
		$issues = [];
		$suggested = '';
		$allKnown = true;
		$chars = preg_split('//u', $email, -1, PREG_SPLIT_NO_EMPTY);
		foreach ($chars as $position => $char) {
			$cp = mb_ord($char, 'UTF-8');
			if ($cp >= 0x20 && $cp < 0x7F) {
				$suggested .= $char;
				continue;
			}
			if (isset($table[$cp])) {
				$replacement = $table[$cp][0];
				$name = $table[$cp][1];
				$known = true;
			} elseif ($cp >= 0xFF01 && $cp <= 0xFF5E) {
				$replacement = chr($cp - 0xFEE0);
				$name = 'FULLWIDTH ' . strtoupper($replacement);
				$known = true;
			} else {
				$replacement = $char;
				$name = '';
				$known = false;
				$allKnown = false;
			}
			$suggested .= $replacement;
			$issues[] = [
				'position' => $position + 1,
				'char' => $char,
				'codepoint' => sprintf('U+%04X', $cp),
				'name' => $name,
				'replacement' => $replacement,
				'known' => $known,
			];
		}
		$fixable = count($issues) > 0 && $allKnown && $suggested !== $email
			&& filter_var($suggested, FILTER_VALIDATE_EMAIL) !== false;
		return [
			'input' => $email,
			'suggested' => $suggested,
			'fixable' => $fixable,
			'issues' => $issues,
		];
	}
}

if (!function_exists('emailLookalikeIssueText')) {
	/**
	 * One plain-text line per offending character, e.g.
	 * "Ugyldigt tegn '−' (U+2212 MINUS SIGN) position 7 erstattes med '-'".
	 *
	 * @param array $analysis Result of emailLookalikeAnalyse()
	 * @param int $sprog_id
	 * @return string[]
	 */
	function emailLookalikeIssueText($analysis, $sprog_id) {
		$lines = [];
		foreach ($analysis['issues'] as $issue) {
			$name = $issue['name'] !== '' ? $issue['name'] : findtekst('5217|Ukendt tegn', $sprog_id);
			$line = findtekst('5211|Ugyldigt tegn', $sprog_id) . " '" . $issue['char'] . "' (" . $issue['codepoint'] . " " . $name . ") "
				. findtekst('5212|position', $sprog_id) . " " . $issue['position'];
			if ($issue['known']) {
				if ($issue['replacement'] === '') {
					$line .= " " . findtekst('5219|fjernes', $sprog_id);
				} else {
					$line .= " " . findtekst('5218|erstattes med', $sprog_id) . " '" . $issue['replacement'] . "'";
				}
			}
			$lines[] = $line;
		}
		return $lines;
	}
}

if (!function_exists('emailLookalikeOrderEmail')) {
	/**
	 * The raw recipient value stored on an order.
	 *
	 * @param int $ordre_id
	 * @return string '' when the order does not exist or has no recipient
	 */
	function emailLookalikeOrderEmail($ordre_id) {
		$ordre_id = (int)$ordre_id;
		if ($ordre_id <= 0) {
			return '';
		}
		$r = db_fetch_array(db_select("select email from ordrer where id = '$ordre_id'", __FILE__ . " linje " . __LINE__));
		if (!$r || $r['email'] === null) {
			return '';
		}
		return $r['email'];
	}
}

if (!function_exists('emailLookalikeSplit')) {
	/**
	 * Splits a stored recipient list the same way send_mails() does.
	 *
	 * @param string $email
	 * @return string[]
	 */
	function emailLookalikeSplit($email) {
		if ($email === '') {
			return [];
		}
		$parts = explode(';', str_replace([' ', ','], ['', ';'], emailLookalikeUtf8($email)));
		return array_values(array_filter($parts, 'strlen'));
	}
}

if (!function_exists('emailLookalikeOrderParts')) {
	/**
	 * The recipient list stored on an order, split the same way send_mails() splits it.
	 *
	 * @param int $ordre_id
	 * @return string[]
	 */
	function emailLookalikeOrderParts($ordre_id) {
		return emailLookalikeSplit(emailLookalikeOrderEmail($ordre_id));
	}
}

if (!function_exists('emailLookalikeApply')) {
	/**
	 * Replaces one recipient on an order with its server-derived ASCII suggestion.
	 * Only the address the user was shown is replaced; anything else on the order is untouched.
	 *
	 * @param int $ordre_id
	 * @param string $from The rejected address exactly as it was shown to the user
	 * @return string The new recipient list, or '' when the order no longer holds $from, it is not fixable,
	 *                or the order was saved by someone else between the read and the write
	 */
	function emailLookalikeApply($ordre_id, $from) {
		$ordre_id = (int)$ordre_id;
		$from = emailLookalikeUtf8($from);
		$stored = emailLookalikeOrderEmail($ordre_id);
		$parts = emailLookalikeSplit($stored);
		$index = array_search($from, $parts, true);
		if ($index === false) {
			return '';
		}
		$analysis = emailLookalikeAnalyse($from);
		if (!$analysis['fixable']) {
			return '';
		}
		$parts[$index] = $analysis['suggested'];
		$newEmail = implode(';', $parts);
		$qtxt = "update ordrer set email = '" . db_escape_string($newEmail) . "' where id = '$ordre_id'"
			. " and email = '" . db_escape_string($stored) . "'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		if (emailLookalikeOrderEmail($ordre_id) !== $newEmail) {
			return '';
		}
		return $newEmail;
	}
}

if (!function_exists('emailRecipientRejected')) {
	/**
	 * Feedback for a recipient that failed FILTER_VALIDATE_EMAIL. Shows which character is
	 * wrong and, when the address sits on the order and can be fixed, an explicit
	 * "use suggested address" form. The form case keeps the page open (exit) so the
	 * user can decide; every other case returns the message like before.
	 *
	 * @param string $address The rejected address
	 * @param int $ordre_id
	 * @param int $sprog_id
	 * @param int $mailantal Number of mails in the current batch
	 * @return string Plain-text error message for the caller
	 */
	function emailRecipientRejected($address, $ordre_id, $sprog_id, $mailantal) {
		global $formular;
		$ordre_id = (int)$ordre_id;
		$analysis = emailLookalikeAnalyse($address);
		$lines = emailLookalikeIssueText($analysis, $sprog_id);
		$message = findtekst('5210|Ugyldig e-mailadresse', $sprog_id) . ": " . $analysis['input'];
		if ($lines) {
			$message .= "\n" . implode("\n", $lines);
		}
		if ($analysis['fixable']) {
			$message .= "\n" . findtekst('5213|Foreslået rettelse', $sprog_id) . ": " . $analysis['suggested'];
		}
		echo "<script type='text/javascript'>alert(" . json_encode($message, JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG) . ");</script>\n";

		$debitorPopup = strpos(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '', '/debitor/formularprint.php') !== false;
		$onOrder = $debitorPopup && $analysis['fixable'] && $ordre_id > 0 && (int)$mailantal <= 1
			&& in_array($analysis['input'], emailLookalikeOrderParts($ordre_id), true);
		if (!$onOrder) {
			return $message;
		}

		$h = function ($s) {
			return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		};
		$marked = '';
		$chars = preg_split('//u', $analysis['input'], -1, PREG_SPLIT_NO_EMPTY);
		foreach ($chars as $char) {
			$marked .= (ord($char) < 0x80) ? $h($char) : "<mark>" . $h($char) . "</mark>";
		}
		echo "<div style=\"padding:10px;font-family:sans-serif\">\n";
		echo "<b>" . $h(findtekst('5210|Ugyldig e-mailadresse', $sprog_id)) . ":</b> <code>$marked</code><br>\n";
		foreach ($lines as $line) {
			echo $h($line) . "<br>\n";
		}
		echo "<br><b>" . $h(findtekst('5213|Foreslået rettelse', $sprog_id)) . ":</b> <code>" . $h($analysis['suggested']) . "</code><br><br>\n";
		echo "<form method=\"post\" action=\"formularprint.php\" accept-charset=\"UTF-8\">\n";
		echo "<input type=\"hidden\" name=\"id\" value=\"$ordre_id\">\n";
		echo "<input type=\"hidden\" name=\"formular\" value=\"" . (int)$formular . "\">\n";
		echo "<input type=\"hidden\" name=\"email_fix_from\" value=\"" . $h($analysis['input']) . "\">\n";
		echo "<input type=\"submit\" value=\"" . $h(findtekst('5214|Brug den foreslåede adresse og send igen', $sprog_id)) . "\">\n";
		echo " <input type=\"button\" value=\"" . $h(findtekst('2172|Luk', $sprog_id)) . "\" onclick=\"document.location.href='ordre.php?id=$ordre_id'\">\n";
		echo "</form>\n";
		echo "<small>" . $h(findtekst('5215|Ret adressen manuelt på ordren eller debitorkortet', $sprog_id)) . "</small>\n";
		echo "</div>\n";
		exit;
	}
}
