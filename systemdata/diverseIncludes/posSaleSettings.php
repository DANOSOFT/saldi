<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/diverseIncludes/posSaleSettings.php --- ver 5.0.0 ---- 2026-10-26 ----
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261021 CL/MJ SD-758 [S12] WP12: the sale settings a POS terminal sells under. For now that is
//                  the default brand per terminal, read by pos_terminal_sale_defaults() in
//                  debitor/pos_ordre_includes/saleHeaderFunctions.php when a sale is created.
//                  The delivery choices that belong on this page (K33) wait for pos_leveringsvalg,
//                  which is WP01's table and is not in the schema yet.
//
// 20261022 CL/MJ SD-758 Only a brand that differs from the one the terminal already holds can be
//                  refused. The field keeps a brand selected after its form variant has been
//                  deleted, so the page posts it back on every save, and refusing it would report
//                  an edit the administrator never made.
//
// 20261021 CL/MJ SD-758 The refused brand names are escaped before alert() puts them in a
//                  javascript string; includes/std_func.php interpolates the message raw.
//
// 20261021 CL/MJ SD-758 The brand is chosen from the form variants in formularer rather than typed,
//                  and a brand with no variant behind it is refused on save. ordrer.sprog selects
//                  the form variant an invoice is printed from, and debitor/formprintfunc.php falls
//                  back to dansk only when it is empty - not when the variant is missing.
//
// 20261026 CL/SZ SD-758 Texts moved from 5439-5441 to 7200-7202, because master already uses 5439 (finans/kassekladde.php) and 5440/5441 (finans/bogfor.php).
//                findtekst() keeps one text per id, so the POS-valg brand heading showed the kassekladde voucher text.
//
// The brand is kept as a tab separated list indexed by terminal number less one, in
// grupper art='POS' kodenr='4' box1 - the same shape as every other per-terminal POS setting
// (kodenr 1, 2 and 3; see posOptions.php). kodenr='4' was unused, and grupper has no box column
// left on kodenr='1', so this needs no schema change of its own.

/** The grupper row holding the POS sale settings, created on first use. */
define('POS_SALE_SETTINGS_KODENR', '4');

/**
 * The default brand of each terminal, as a list indexed by terminal number less one.
 *
 * @param int $regnaar Fiscal year the settings belong to.
 * @return array<int,string>
 */
function pos_sale_brands($regnaar) {
	$regnaar = (int) $regnaar;
	$qtxt = "select box1 from grupper where art = 'POS' and kodenr = '" . POS_SALE_SETTINGS_KODENR . "'";
	$qtxt.= " and fiscal_year = '$regnaar'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		return explode(chr(9), (string) $r['box1']);
	}
	return array();
}

/**
 * The form variants a brand can be - the same list debitor/ordre.php:4054 offers for an order's
 * sprog, and rykker.php:358 and finans/ordre.php:1359 for theirs.
 *
 * A brand is stored in ordrer.sprog, which picks the form variant an invoice is printed from. The
 * printer falls back to dansk only when sprog is empty (debitor/formprintfunc.php:145), not when the
 * named variant is missing: the layout read at :179 then returns no rows and the invoice is printed
 * from unset variables. Offering only existing variants keeps that state unreachable.
 *
 * @return array<int,string>
 */
function pos_sale_brand_variants() {
	$variants = array();
	$q = db_select("select distinct sprog from formularer order by sprog", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$sprog = trim((string) $r['sprog']);
		if ($sprog !== '') {
			$variants[] = $sprog;
		}
	}
	return $variants;
}

/**
 * The column heading for the brand field, printed in the folding per-terminal block.
 *
 * @param int $sprog_id
 * @return void
 */
function pos_render_sale_brand_header($sprog_id) {
	$text  = findtekst('7200|Brand', $sprog_id);
	$title = findtekst('7201|Brand salget oprettes med på denne kasse. Gemmes som ordrens sprog/formularvariant', $sprog_id);
	print "<td title='$title'><!--Tekst 7201-->$text<!--Tekst 7200--></td>\n";
}

/**
 * The brand field for one terminal: the form variants, with an empty choice first.
 *
 * Empty is the default and leaves the sale exactly as it was before SD-758. A brand configured
 * before its form variant was deleted is still shown, and still selected, so merely drawing this
 * page cannot change what a terminal sells under.
 *
 * @param array<int,string> $brands   From pos_sale_brands().
 * @param int               $index    Terminal number less one.
 * @param array<int,string> $variants From pos_sale_brand_variants(); passed in so the list is read
 *                                    once per page rather than once per terminal.
 * @return void
 */
function pos_render_sale_brand_field($brands, $index, $variants) {
	$brand = trim((string) ifset($brands, $index, ''));
	print "<td align='center'><select class='inputbox' style='width:110px;' name='brand_navn[$index]'>\n";
	print "<option value=''></option>\n";
	$found = false;
	foreach ($variants as $variant) {
		$value = htmlspecialchars((string) $variant, ENT_QUOTES);
		if (!$found && (string) $variant === $brand) {
			print "<option value='$value' selected>$value</option>\n";
			$found = true;
		} else {
			print "<option value='$value'>$value</option>\n";
		}
	}
	// A brand whose variant has since been deleted stays selected rather than silently clearing.
	if ($brand !== '' && !$found) {
		$value = htmlspecialchars($brand, ENT_QUOTES);
		print "<option value='$value' selected>$value</option>\n";
	}
	print "</select></td>\n";
}

/**
 * A brand name made safe to sit inside alert()'s single-quoted javascript string.
 *
 * includes/std_func.php's alert() echoes "<script ...>alert('$msg');</script>" with the message
 * interpolated raw, so an apostrophe would end the string and a literal </script> would end the
 * block. The names reported here come from the submitted form, so they are escaped rather than
 * trusted. Tabs and line breaks are already gone by this point - they are stripped before a brand
 * is validated.
 *
 * @param string $name
 * @return string
 */
function pos_sale_brand_for_alert($name) {
	$search  = array('\\', "'", '<');
	$replace = array('\\\\', "\\'", '\u003C');
	return str_replace($search, $replace, (string) $name);
}

/**
 * Stores the brand of each terminal from a submitted POS settings form.
 *
 * Called from systemdata/diverse.php, which owns the posOptions POST. Terminals are numbered from
 * one and the form posts them keyed by terminal number less one, so the list is rebuilt by index
 * rather than by iterating the submitted array - a terminal left blank has to keep its place, or
 * every terminal after it would shift onto the wrong brand.
 *
 * @param array $post    The submitted form, normally $_POST.
 * @param int   $regnaar Fiscal year the settings belong to.
 * @return void
 */
function pos_save_sale_settings($post, $regnaar) {
	$regnaar = (int) $regnaar;
	// A form that carries no brand fields at all is not this section's form, and must leave the
	// stored brands alone - an absent field is not the same as a field submitted empty, which does
	// clear that terminal's brand.
	if (!is_array($post) || !array_key_exists('brand_navn', $post) || !is_array($post['brand_navn'])) {
		return;
	}
	$submitted = $post['brand_navn'];
	$kasseantal = (int) ifset($post, 'kasseantal', 0);
	if ($kasseantal < 1) {
		$kasseantal = $submitted ? max(array_keys($submitted)) + 1 : 0;
	}
	$variants = pos_sale_brand_variants();
	$stored = pos_sale_brands($regnaar);
	$refused = array();
	$brands = array();
	for ($x = 0; $x < $kasseantal; $x++) {
		// A brand is the order's sprog, so it must survive as typed; tabs are the list separator
		// and a newline would break the row, so both are stripped rather than stored.
		$brand = trim(str_replace(array(chr(9), chr(10), chr(13)), ' ', (string) ifset($submitted, $x, '')));
		$current = trim((string) ifset($stored, $x, ''));
		// A brand with no form variant behind it would print the terminal's invoices from unset
		// layout variables, so it is refused and that terminal keeps what it had. The field offers
		// only existing variants, so this answers a hand-made POST rather than normal use. The brand
		// already stored is never refused: the field keeps it selected after its variant has been
		// deleted, so the page posts it back on every save, and refusing it would report an edit the
		// administrator never made.
		if ($brand !== '' && $brand !== $current && !in_array($brand, $variants, true)) {
			$refused[$brand] = $brand;
			$brand = $current;
		}
		$brands[$x] = $brand;
	}
	$box1 = db_escape_string(implode(chr(9), $brands));
	$qtxt = "select id from grupper where art = 'POS' and kodenr = '" . POS_SALE_SETTINGS_KODENR . "'";
	$qtxt.= " and fiscal_year = '$regnaar'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		$id = (int) $r['id'];
		db_modify("update grupper set box1='$box1' where id = '$id'", __FILE__ . " linje " . __LINE__);
	} else {
		$qtxt = "insert into grupper (beskrivelse,kode,kodenr,art,box1,fiscal_year) values ";
		$qtxt.= "('POS salgsopsaetning','','" . POS_SALE_SETTINGS_KODENR . "','POS','$box1','$regnaar')";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
	if ($refused) {
		$txt = findtekst('7202|Ikke gemt, mangler formularvariant', $GLOBALS['sprog_id']);
		$safe = array();
		foreach ($refused as $name) {
			$safe[] = pos_sale_brand_for_alert($name);
		}
		alert($txt . ': ' . implode(', ', $safe));
	}
}
