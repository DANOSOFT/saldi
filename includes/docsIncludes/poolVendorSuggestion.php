<?php
// --- includes/docsIncludes/poolVendorSuggestion.php ---
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
// 20260922 CL/LAH Kreditor-forslag (kravspec "Bilagsflow" afsnit 6): what the bilagsflow does with a
//                  pool file's vendor match. poolVendorSuggestion() is pure and covered by
//                  tests/characterization/includes/docsIncludes/PoolVendorSuggestionCharacterizationTest;
//                  poolVendorKontonrById() is the one DB lookup the server-side insert path needs.
//                  Kept apart from poolVendorMatcher.php (which produces the match) on purpose.

if (!function_exists('poolVendorSuggestion')) {
	/**
	 * Turn a docData vendor object into what the bilagsflow should do with the Kredit field
	 * (kravspec afsnit 6): cvr and bank matches, and name matches at or above the auto
	 * threshold, are filled in as a suggestion; weaker name matches are offered for one
	 * click; ambiguous matches get the candidate list; everything else does nothing.
	 *
	 * @param array|null $vendor The 'vendor' object from _docPoolData.php (or null).
	 * @param float $autoThreshold Name score needed to fill automatically (default 0.80).
	 * @return array{
	 *   mode: string,            auto | suggest | choose | none
	 *   kredit: string|null,     "K" + kontonr for auto/suggest, null otherwise.
	 *   kontonr: string|null,
	 *   firmanavn: string|null,
	 *   score: float,
	 *   candidates: array<int,array{kontoId:int,kontonr:string,firmanavn:string,kredit:string}>  Only for choose.
	 * }
	 */
	function poolVendorSuggestion($vendor, $autoThreshold = 0.80) {
		$none = array('mode' => 'none', 'kredit' => null, 'kontonr' => null, 'firmanavn' => null, 'score' => 0.0, 'candidates' => array());
		if (!is_array($vendor)) return $none;
		$match = (string) ($vendor['match'] ?? 'none');
		$score = round((float) ($vendor['score'] ?? 0), 3);
		if ($match === 'ambiguous') {
			$candidates = array();
			foreach ((array) ($vendor['candidates'] ?? array()) as $c) {
				if (!isset($c['kontonr']) || trim((string) $c['kontonr']) === '') continue;
				$candidates[] = array(
					'kontoId' => (int) ($c['kontoId'] ?? 0),
					'kontonr' => (string) $c['kontonr'],
					'firmanavn' => (string) ($c['firmanavn'] ?? ''),
					'kredit' => 'K' . $c['kontonr'],
				);
			}
			if (!$candidates) return $none;
			return array('mode' => 'choose', 'kredit' => null, 'kontonr' => null, 'firmanavn' => null, 'score' => 0.0, 'candidates' => $candidates);
		}
		$kontonr = isset($vendor['kontonr']) ? trim((string) $vendor['kontonr']) : '';
		if ($kontonr === '' || !in_array($match, array('cvr', 'bank', 'name'), true)) return $none;
		$mode = ($match === 'name' && $score < (float) $autoThreshold) ? 'suggest' : 'auto';
		return array(
			'mode' => $mode,
			'kredit' => 'K' . $kontonr,
			'kontonr' => $kontonr,
			'firmanavn' => isset($vendor['firmanavn']) ? (string) $vendor['firmanavn'] : null,
			'score' => $score,
			'candidates' => array(),
		);
	}
}

if (!function_exists('poolVendorKontonrById')) {
	/**
	 * kontonr of an open kreditor by adresser.id, or null when it no longer exists or is closed.
	 *
	 * @param int $kontoId
	 * @return string|null
	 */
	function poolVendorKontonrById($kontoId) {
		$kontoId = (int) $kontoId;
		if ($kontoId <= 0) return null;
		$q = db_select("SELECT kontonr FROM adresser WHERE id = $kontoId AND art = 'K' AND (lukket IS NULL OR lukket != 'on')", __FILE__ . " linje " . __LINE__);
		$r = $q ? db_fetch_array($q) : null;
		$kontonr = $r ? trim((string) ($r['kontonr'] ?? '')) : '';
		return $kontonr !== '' ? $kontonr : null;
	}
}
