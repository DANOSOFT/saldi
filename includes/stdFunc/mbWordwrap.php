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

		$ud = array();
		// wordwrap() treats an existing newline as a hard break; splitting on it first and
		// wrapping each piece reproduces that, instead of measuring across the break.
		foreach (explode("\n", $tekst) as $afsnit) {
			$linje = '';
			foreach (explode(' ', $afsnit) as $ord) {
				// A word too long for a line of its own is chopped, but only when wordwrap()
				// would chop it too. wordwrap() always starts chopping from a BLANK line: it
				// flushes whatever is on the current line first and never packs the first
				// chunk into the space left over. Getting that wrong does not overflow the
				// column, it just breaks in different places than wordwrap() would - which
				// contradicts the whole point of this function.
				//   wordwrap('ab loooongword', 5) with cut -> ab | loooo | ngwor | d
				// The remainder after the last chunk is shorter than $width and stays on the
				// line, so a following word can still join it ('... | d ab').
				if ($cut && mb_strlen($ord, 'UTF-8') > $width) {
					if ($linje !== '') {
						$ud[]  = $linje;
						$linje = '';
					}
					while (mb_strlen($ord, 'UTF-8') > $width) {
						$ud[] = mb_substr($ord, 0, $width, 'UTF-8');
						$ord  = mb_substr($ord, $width, NULL, 'UTF-8');
					}
				}
				if ($linje === '') {
					$linje = $ord;
				} elseif (mb_strlen($linje, 'UTF-8') + 1 + mb_strlen($ord, 'UTF-8') <= $width) {
					$linje .= ' ' . $ord;
				} else {
					$ud[]  = $linje;
					$linje = $ord;
				}
			}
			$ud[] = $linje;
		}
		return implode($break, $ud);
	}
}
?>
