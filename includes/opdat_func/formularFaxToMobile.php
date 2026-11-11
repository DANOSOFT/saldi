<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_func/formularFaxToMobile.php --- ver 5.1.1 --- 2026-11-11 ---
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
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261111 CL/MMR SST-898 Created. SD-532 turned the fax field into the mobile field (adresser.mobile), but the forms kept "Fax: $egen_fax".
//                 The forms now name the field "Mobil" and use $egen_mobile / $adresser_mobile (Morten 2026-11-11: «der er ingen der bruger Fax længere»).

if (!function_exists('formular_fax_to_mobile_text')) {
	/**
	 * One form text (formularer.beskrivelse) with the fax variables replaced by the mobile ones.
	 *
	 * $egen_fax / $eget_fax become $egen_mobile and $adresser_fax becomes $adresser_mobile; includes/formfunk.php
	 * printed all of them from adresser.mobile already, so the printed number does not change. Where the standard
	 * label "Fax:" (or Telefax/Faks) stands right before the variable, possibly with a dialling code such as
	 * "(+45)" in between, it becomes the mobile label of the form's language. A label of the customer's own, such
	 * as "Mobile Pay.: $egen_fax;", is kept and only the variable changes.
	 *
	 * @param string $text  The form text.
	 * @param string $sprog The form's language (formularer.sprog), e.g. 'Dansk', 'English', 'Norsk'.
	 * @return string The text with mobile variables and labels; unchanged when it holds no fax variable.
	 */
	function formular_fax_to_mobile_text($text, $sprog) {
		$text = (string)$text;
		$lang = strtolower(trim((string)$sprog));
		if (in_array($lang, array('english', 'engelsk'), true)) {
			$label = 'Mobile';
		} elseif ($lang === 'spansk') {
			$label = 'Móvil';
		} else {
			$label = 'Mobil';
		}
		$var = '\$(egen|eget|adresser)_fax(?![A-Za-z0-9_])';
		// the standard label right before the variable: "Fax: ", "Telefax.: ", "Fax: (+45)"
		$text = preg_replace(
			'/(?<![A-Za-z])(?:Telefax|Fax|FAX|Faks)(\.?:\s*(?:\(\+?[0-9]+\)\s*)?)' . $var . '/u',
			$label . '$1\$$2_fax',
			$text
		);
		return preg_replace_callback('/' . $var . '/u', function ($m) {
			return '$' . ($m[1] === 'adresser' ? 'adresser' : 'egen') . '_mobile';
		}, $text);
	}
}

if (!function_exists('formularFaxToMobile')) {
	/**
	 * Rewrites the account's form texts that still use a fax variable (see formular_fax_to_mobile_text()).
	 * Runs from includes/betweenUpdates.php at every login: it reads only rows that mention "fax", and a
	 * rewritten row no longer holds a fax variable, so a second run (or a concurrent login) changes nothing.
	 *
	 * @return int The number of form texts changed.
	 */
	function formularFaxToMobile() {
		$changed = 0;
		$q = db_select("SELECT id, sprog, beskrivelse FROM formularer WHERE LOWER(beskrivelse) LIKE '%fax%'", __FILE__ . " linje " . __LINE__);
		while ($q && ($r = db_fetch_array($q))) {
			$new = formular_fax_to_mobile_text($r['beskrivelse'], $r['sprog']);
			if ($new !== (string)$r['beskrivelse']) {
				db_modify("UPDATE formularer SET beskrivelse = '" . db_escape_string($new) . "' WHERE id = '" . (int)$r['id'] . "'", __FILE__ . " linje " . __LINE__);
				$changed++;
			}
		}
		return $changed;
	}
}
