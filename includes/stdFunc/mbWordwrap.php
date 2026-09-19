<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/mbWordwrap.php --- patch 5.0.0 --- 2026-09-16 ---
// LICENS
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
// Copyright (c) 2003-2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20260916 CDX/MJ SST-784 Added mb_wordwrap(): wordwrap() counts bytes, so on UTF-8 text every
//                  ae/oe/aa costs two of the column budget and a Danish print line wraps early
//                  by exactly its number of non-ASCII characters. Same signature and same
//                  line-breaking rules as wordwrap(), measured in characters.
// 20260919 CDX/MJ SST-784 Chop an over-long word from a blank line, as wordwrap() does, instead
//                  of packing the first chunk into the space left on the current line. Caught in
//                  review on PR #623; the original differential test never generated a word
//                  longer than the width, so the case could not come up.
// 20260919 CDX/MJ SST-784 Stop reimplementing wordwrap()'s rules and delegate to it instead. Three
//                  attempts to reproduce the line breaking by hand each looked right and each
//                  diverged: on an over-long word after other text (Saul on #623), on runs of
//                  spaces (CodeRabbit on #623), and again on space runs at narrow widths. Every
//                  character now maps to one placeholder byte, so wordwrap()'s own byte arithmetic
//                  on the placeholder is character arithmetic on the original, and the breaks it
//                  chooses are mapped back onto the real characters.

if (!function_exists('mb_wordwrap')) {
	/**
	 * Character-counting equivalent of wordwrap() for UTF-8 text.
	 *
	 * Mirrors wordwrap()'s behaviour deliberately, so it can replace it without changing
	 * anything but the unit of measurement:
	 *   - existing newlines in $tekst start a fresh line and are preserved,
	 *   - a word longer than $width is only split when $cut is true,
	 *   - the break string is not counted towards the width.
	 * Falls straight back to wordwrap() when mbstring is unavailable, which keeps the old
	 * behaviour rather than fataling on an install without the extension.
	 *
	 * Counts codepoints, not grapheme clusters, so a decomposed 'a' + combining ring would be
	 * measured as two. Danish text is stored precomposed (NFC) throughout, so this does not arise
	 * in practice; noted because it is the next thing that would surprise someone here.
	 *
	 * @param string $tekst Text to wrap. UTF-8.
	 * @param int    $width Column budget, in characters.
	 * @param string $break Line break to insert.
	 * @param bool   $cut   Split words longer than $width.
	 * @return string
	 */
	function mb_wordwrap($tekst, $width = 75, $break = "\n", $cut = false) {
		$tekst = (string) $tekst;
		if ($tekst === '') return '';
		$width = (int) $width;
		if ($width < 1 || !function_exists('mb_strlen')) return wordwrap($tekst, max(1, $width), $break, $cut);

		// Text that is already pure ASCII wraps identically either way, so leave it to the
		// C implementation - that keeps every existing non-Danish printout byte-identical.
		if (!preg_match('/[\x80-\xFF]/', $tekst)) return wordwrap($tekst, $width, $break, $cut);

		// Only a single-character break can be handled below, because the placeholder has to
		// represent it in one byte. Anything else is left to the C implementation.
		if ($break === '' || mb_strlen($break, 'UTF-8') !== 1 || !function_exists('mb_str_split')) {
			return wordwrap($tekst, $width, $break, $cut);
		}

		// Let wordwrap() make every line-breaking decision, and change only the unit it counts.
		//
		// Reimplementing the rules was tried three times and diverged three times - on an
		// over-long word following other text, and on runs of spaces at narrow widths. They are
		// not reliably reconstructible from the outside, so none of them is reconstructed here.
		//
		// Instead each character becomes exactly one placeholder BYTE, so wordwrap()'s byte
		// arithmetic on the placeholder is character arithmetic on the original. Only spaces and
		// the break are significant to wordwrap(); every other character is opaque to it and can
		// be stood in for by 'x'. The breaks wordwrap() then chooses are mapped back onto the
		// real characters. Parity is therefore structural rather than something to be tested for.
		$tegn     = mb_str_split($tekst, 1, 'UTF-8');
		$MARKOER  = "\x01";   // stands in for $break; cannot occur in the placeholder
		$skygge   = '';
		foreach ($tegn as $t) {
			if ($t === ' ')         $skygge .= ' ';
			elseif ($t === $break)  $skygge .= $MARKOER;
			else                    $skygge .= 'x';
		}

		$brudt = wordwrap($skygge, $width, $MARKOER, $cut);

		// Walk the wrapped placeholder and re-emit the original characters. wordwrap() either
		// inserts a break (a cut mid-word, consuming nothing) or replaces the single space it
		// breaks at, so a marker consumes the current character only when that character is a
		// space or was itself a break.
		$ud = '';
		$i  = 0;
		for ($j = 0, $n = strlen($brudt); $j < $n; $j++) {
			if ($brudt[$j] === $MARKOER) {
				$ud .= $break;
				if (isset($tegn[$i]) && ($tegn[$i] === ' ' || $tegn[$i] === $break)) $i++;
			} else {
				$ud .= isset($tegn[$i]) ? $tegn[$i] : '';
				$i++;
			}
		}
		return $ud;
	}
}
?>
