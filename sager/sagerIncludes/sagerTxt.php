<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- sager/sagerIncludes/sagerTxt.php --- ver 5.1.0 --- 2026-11-03 ---
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
// Copyright (c) 2026-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261103 CL/MMR SD-799 Created.
//                 Shared display texts for the Stillads module, so values stored in Danish (case status, "Opgave N") and the texts of the module's JavaScript follow the user's language.

if (!function_exists('sag_status_txt')) {
	/**
	 * Translates a case or task status for display. The Danish word stays the stored value and the key
	 * that the code compares, sorts and colours on; only the shown text changes.
	 *
	 * @param string|null $status Stored status (sager.status / opgaver.status), e.g. 'Montage'.
	 * @return string The status in the user's language, or the stored value when it is not one of the
	 *                eight fixed statuses.
	 */
	function sag_status_txt($status) {
		global $sprog_id;
		$status = (string)$status;
		switch ($status) {
			case 'Beregning':        return findtekst('2936|Beregning', $sprog_id);
			case 'Tilbud':           return findtekst('812|Tilbud', $sprog_id);
			case 'Ordrebekræftelse': return findtekst('575|Ordrebekræftelse', $sprog_id);
			case 'Montage':          return findtekst('2872|Montage', $sprog_id);
			case 'Godkendt':         return findtekst('2937|Godkendt', $sprog_id);
			case 'Afmeldt':          return findtekst('2938|Afmeldt', $sprog_id);
			case 'Drivgods':         return findtekst('2939|Drivgods', $sprog_id);
			case 'Afsluttet':        return findtekst('2940|Afsluttet', $sprog_id);
		}
		return $status;
	}
}

if (!function_exists('opgave_navn_txt')) {
	/**
	 * Translates a stored task name of the form "Opgave N" for display. Any other name is the user's
	 * own text and is returned unchanged.
	 *
	 * @param string|null $navn Stored task name, e.g. tjekskema.opg_navn.
	 * @return string "Task N" in English, "Opgave N" in Danish, or the name as it was.
	 */
	function opgave_navn_txt($navn) {
		global $sprog_id;
		$navn = (string)$navn;
		if (preg_match('/^Opgave (\d+)$/', $navn, $m)) {
			return findtekst('2800|Opgave', $sprog_id) . ' ' . $m[1];
		}
		return $navn;
	}
}

if (!function_exists('sager_pagination_label')) {
	/**
	 * The info label for jquery.pajinate lists, e.g. "Showing {0}-{1} of {2}"; pajinate fills in the
	 * placeholders. Same texts as sager.php and mm_kontrolskemaer.php use.
	 *
	 * @return string The label with the {0}, {1} and {2} placeholders.
	 */
	function sager_pagination_label() {
		global $sprog_id;
		return findtekst('2954|Viser', $sprog_id) . ' {0}-{1} ' . lcfirst(findtekst('2767|Af', $sprog_id)) . ' {2}';
	}
}

if (!function_exists('sager_js_tekst')) {
	/**
	 * Prints texts for the module's JavaScript as a global object, e.g. var notatTekst = {...};
	 * The scripts read it with the Danish text as fallback, so a page without the object still works.
	 *
	 * @param string $name  Name of the JavaScript variable (letters only).
	 * @param array<string, string> $texts Key => translated text.
	 * @return string A complete <script> element.
	 */
	function sager_js_tekst($name, array $texts) {
		$name = preg_replace('/[^A-Za-z]/', '', $name);
		$json = json_encode($texts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		return "<script type=\"text/javascript\">var $name = $json;</script>\n";
	}
}

if (!function_exists('sager_datepicker_locale')) {
	/**
	 * The jQuery UI datepicker texts for the user's language. Danish and Norwegian users keep the Danish
	 * regional file as before. English users get jQuery UI's built-in English texts with the same date
	 * format and first weekday, because the pages parse the dates as dd-mm-yy.
	 *
	 * @return string The <script> element(s) to put after jquery-ui in the page head.
	 */
	function sager_datepicker_locale() {
		global $sprog_id;
		if ((int)$sprog_id === 2) {
			return "<script type=\"text/javascript\">jQuery(function($){\$.datepicker.setDefaults(\$.extend({}, \$.datepicker.regional[''], {dateFormat: 'dd-mm-yy', firstDay: 1}));});</script>\n";
		}
		return "<script type=\"text/javascript\" src=\"../javascript/ui.datepicker-da.js\"></script>\n";
	}
}

if (!function_exists('sager_js_sagertekst')) {
	/**
	 * Prints the shared label object sagerTekst that the module's scripts (jquery.sager.js, jquery.notat.js,
	 * jquery.kunder.js, jquery.ansatte.js, jquery.kontrolskemaer.js, jquery.medarbejdermappe.js) use for
	 * autocomplete labels and the list info line. The scripts add the colon themselves.
	 *
	 * @return string A complete <script> element defining var sagerTekst.
	 */
	function sager_js_sagertekst() {
		global $sprog_id;
		return sager_js_tekst('sagerTekst', array(
			'kunde'       => findtekst('35|Kunde', $sprog_id),
			'adresse'     => findtekst('140|Adresse', $sprog_id),
			'nr'          => rtrim(findtekst('2248|Nr.', $sprog_id), '.'),
			'status'      => rtrim(findtekst('494|Status', $sprog_id), '.'),
			'firma'       => findtekst('5381|Firma', $sprog_id),
			'navn'        => findtekst('138|Navn', $sprog_id),
			'sagsnr'      => rtrim(findtekst('2819|Sagsnr.', $sprog_id), '.'),
			'af'          => findtekst('8415|Af', $sprog_id),
			'beskrivelse' => findtekst('914|Beskrivelse', $sprog_id),
			'dato'        => findtekst('438|Dato', $sprog_id),
			'skema'       => findtekst('2824|Skema', $sprog_id),
			'opgave'      => findtekst('2800|Opgave', $sprog_id),
			'opdateret'   => findtekst('8416|Posten er opdateret', $sprog_id),
			'visning'     => sager_pagination_label(),
		));
	}
}
