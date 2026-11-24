<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/diverseIncludes/posSaleSettings.php --- ver 5.1.1 ---- 2026-11-24 ----
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
// 20261120 CL/LH K33 The delivery choices the POS offers (pos_leveringsvalg, Shopify schema v1) are maintained here, below the terminals on POS-valg: name, type (taget med, afhentning, levering), the value and tags the store order is sent to Shopify with, order and active.
//                A choice an order already uses is never rewritten: a change of name, type, Shopify value or tags deactivates it and adds a new version, and delete only deactivates it.
//                The section shows only where the Shopify schema is installed, so MySQL installations are untouched.
//
// 20261124 CL/LH K33 PoS-valg is retired (#1191, #1192), so the delivery choices are kept on systemdata/posDeliveryChoices.php,
//                linked from Indstillinger -> Kasse -> Kasseskærm -> Salg; pos_render_delivery_choices_settings() takes the page's charset.
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

/**
 * Whether this tenant has the delivery choice table and order columns at all. They are part of the
 * Shopify schema, which is installed on PostgreSQL tenants only; a MySQL tenant answers false
 * without a query.
 *
 * @return bool
 */
function pos_delivery_supported() {
	static $supported = null;
	if ($supported === null) {
		// The table and the order columns are part of the Shopify schema manifest.
		include_once(__DIR__ . '/../../includes/shopify/schema.php');
		$supported = shopify_schema_ready();
		if ($supported) {
			// Bound parameters for every write: db_modify() turns " like " into " ilike " even inside
			// a quoted value, so a Shopify value such as "ship like usual" would be stored changed.
			include_once(__DIR__ . '/../../includes/shopify/db.php');
		}
	}
	return $supported;
}

/**
 * The page charset a string is printed in: ISO-8859-15 on a LATIN9 installation, UTF-8 otherwise.
 * htmlspecialchars() answers an empty string for text that is not valid in the charset it is told,
 * so every printed choice name goes through pos_delivery_html().
 *
 * @return string
 */
function pos_delivery_charset() {
	global $db_encode;
	return (isset($db_encode) && $db_encode !== 'UTF8') ? 'ISO-8859-15' : 'UTF-8';
}

/**
 * Text from the database or a translation, escaped for HTML.
 *
 * On a LATIN9 installation the text is ISO-8859-15, but a page may still be declared UTF-8 (the
 * top-menu pages are); $utf8Page converts it for such a page, so a name like "Mårum" is not shown
 * as "M?rum" and then saved back changed with the next unrelated settings save.
 *
 * @param mixed     $text
 * @param bool|null $utf8Page TRUE when the page is UTF-8; NULL for the installation's own charset.
 * @return string
 */
function pos_delivery_html($text, $utf8Page = null) {
	$text = (string) $text;
	if ($utf8Page === true) {
		if (!mb_check_encoding($text, 'UTF-8')) {
			$text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-15');
		}
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
	return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, pos_delivery_charset());
}

/**
 * A fingerprint of a stored choice as the settings form showed it. The form posts it back, and a
 * row whose fingerprint no longer matches was changed by someone else since the page was opened:
 * saving it would reactivate a historical version or add a second replacement.
 *
 * @param array $choice From pos_delivery_choices(false).
 * @return string
 */
function pos_delivery_revision($choice) {
	$aktiv = in_array($choice['aktiv'], array(true, 't', '1', 1), true) ? 't' : 'f';
	return md5(implode(chr(9), array($choice['navn'], $choice['type'], $choice['shopify_vaerdi'], $choice['tags'], $aktiv, (int) $choice['sort'])));
}

/**
 * A posted string in the connection's encoding. A LATIN9 installation prints most pages in
 * ISO-8859-1, but the top-menu pages declare UTF-8, so the same form can arrive in either. Text
 * that is valid UTF-8 and not plain ASCII is taken as UTF-8 and converted to ISO-8859-15; anything
 * else is already in the connection's encoding. On a UTF-8 installation the text is left as it is.
 *
 * @param mixed $text
 * @return string
 */
function pos_delivery_input($text) {
	global $db_encode;
	$text = str_replace("\0", '', (string) $text);
	if (isset($db_encode) && $db_encode !== 'UTF8' && preg_match('/[\x80-\xff]/', $text) && mb_check_encoding($text, 'UTF-8')) {
		$text = mb_convert_encoding($text, 'ISO-8859-15', 'UTF-8');
	}
	return $text;
}

/**
 * The three kinds of delivery the store-order export knows (K33/K36), with their label.
 *
 * @param int $sprog_id
 * @return array<string,string> type => label
 */
function pos_delivery_types($sprog_id) {
	return array(
		'taget_med'  => findtekst('pos.delivery.type.taget_med|Taget med', $sprog_id),
		'afhentning' => findtekst('pos.delivery.type.afhentning|Afhentning', $sprog_id),
		'levering'   => findtekst('pos.delivery.type.levering|Levering', $sprog_id),
	);
}

/**
 * The delivery choices, in the order the POS offers them.
 *
 * @param bool $activeOnly Only the choices a cashier can pick.
 * @return array<int, array{id: int, navn: string, type: string, shopify_vaerdi: string, tags: string, aktiv: bool, sort: int}>
 */
function pos_delivery_choices($activeOnly = true) {
	if (!pos_delivery_supported()) {
		return array();
	}
	// The tags are read as text rather than as jsonb, so they arrive in the connection's own
	// encoding, ready to print.
	$qtxt = "select id, navn, type, coalesce(shopify_vaerdi, '') as shopify_vaerdi, aktiv, sort,";
	$qtxt.= " array_to_string(array(select jsonb_array_elements_text(tags)), ', ') as tags";
	$qtxt.= " from pos_leveringsvalg";
	if ($activeOnly) {
		$qtxt.= " where aktiv";
	}
	$qtxt.= " order by sort, id";
	$choices = array();
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$choices[] = array(
			'id' => (int) $r['id'],
			'navn' => (string) $r['navn'],
			'type' => (string) $r['type'],
			'shopify_vaerdi' => (string) $r['shopify_vaerdi'],
			'tags' => (string) $r['tags'],
			'aktiv' => in_array($r['aktiv'], array(true, 't', '1', 1), true),
			'sort' => (int) $r['sort'],
		);
	}
	return $choices;
}

/**
 * Whether the POS shows the delivery fields: the schema is there and at least one choice is active.
 * Without a choice set up, every sale stays "taget med" exactly as before K33.
 *
 * @return bool
 */
function pos_delivery_enabled() {
	if (!pos_delivery_supported()) {
		return false;
	}
	$r = db_fetch_array(db_select("select id from pos_leveringsvalg where aktiv limit 1", __FILE__ . " linje " . __LINE__));
	return (bool) $r;
}

/**
 * Tags typed as a comma separated list: trimmed, without empty and repeated tags.
 *
 * @param string $typed
 * @return array<int,string>
 */
function pos_delivery_tags_list($typed) {
	$tags = array();
	foreach (explode(',', (string) $typed) as $tag) {
		$tag = trim($tag);
		if ($tag !== '' && !in_array($tag, $tags, true)) {
			$tags[] = $tag;
		}
	}
	return $tags;
}

/**
 * Tags as the jsonb array pos_leveringsvalg.tags stores. The JSON is kept pure ASCII (non-ASCII
 * letters as \u escapes), so it reads the same over a LATIN9 and a UTF-8 connection.
 *
 * @param array<int,string> $tags From pos_delivery_tags_list(), in the connection's encoding.
 * @return string
 */
function pos_delivery_tags_json($tags) {
	$utf8 = array();
	foreach ($tags as $tag) {
		$utf8[] = mb_check_encoding($tag, 'UTF-8') ? $tag : mb_convert_encoding($tag, 'UTF-8', 'ISO-8859-15');
	}
	return (string) json_encode($utf8);
}

/**
 * One posted choice row, cleaned: trimmed text with tabs and line breaks gone, an empty Shopify
 * value as NULL, and the tags as their JSON.
 *
 * @param array $row
 * @return array{navn: string, type: string, shopify_vaerdi: ?string, tags: string, tags_typed: string, sort: int, aktiv: bool, slet: bool, revision: string}
 */
function pos_delivery_posted_choice($row) {
	$clean = function ($value) {
		return trim(str_replace(array(chr(9), chr(10), chr(13)), ' ', pos_delivery_input(is_scalar($value) ? $value : '')));
	};
	$sort = (int) ifset($row, 'sort', 0);
	$vaerdi = $clean(ifset($row, 'shopify_vaerdi', ''));
	$tags = pos_delivery_tags_list($clean(ifset($row, 'tags', '')));
	return array(
		'navn' => $clean(ifset($row, 'navn', '')),
		'type' => $clean(ifset($row, 'type', '')),
		'shopify_vaerdi' => ($vaerdi === '') ? null : $vaerdi,
		'tags' => pos_delivery_tags_json($tags),
		'tags_typed' => implode(', ', $tags),
		// pos_leveringsvalg.sort is an integer column; a larger number would fail the insert.
		'sort' => max(-2147483648, min(2147483647, $sort)),
		'aktiv' => (bool) ifset($row, 'aktiv', ''),
		'slet' => (bool) ifset($row, 'slet', ''),
		'revision' => is_scalar(ifset($row, 'rev', '')) ? (string) ifset($row, 'rev', '') : '',
	);
}

/**
 * Runs $op in one transaction (shopify_db_transaction()). A failed statement rolls the work back and
 * is logged; the page carries on and reports what was not saved instead of ending in a fatal error.
 *
 * @param callable $op
 * @return bool Whether the work was committed.
 */
function pos_delivery_try($op) {
	try {
		shopify_db_transaction($op);
		return true;
	} catch (Throwable $e) {
		error_log('K33 delivery choice not saved: ' . $e->getMessage());
		return false;
	}
}

/**
 * Stores the delivery choices from a submitted POS settings form.
 *
 * Called from systemdata/posDeliveryChoices.php with its POST. A form without the delivery fields
 * leaves the choices alone. A row without a name or with an unknown
 * type is refused and reported, and the other rows are still saved.
 *
 * A choice that an order already points at keeps its meaning: changing its name, type, Shopify
 * value or tags deactivates it and adds the edited choice as a new row, and deleting it only
 * deactivates it. Order and active change in place, because they do not change what an order was
 * sold with. An unused choice is edited or deleted in place.
 *
 * Each row is changed in one transaction that holds the row lock while it checks whether an order
 * uses it. pos_save_sale_delivery() takes a share lock on the same row before attaching it, so a
 * choice cannot be rewritten or deleted between the check and the change, and a version's
 * deactivation and its replacement are committed together. Each row carries the revision the form
 * showed (pos_delivery_revision()); a row changed by someone else since then is not saved.
 *
 * @param array $post The submitted form, normally $_POST.
 * @return void
 */
function pos_save_delivery_choices($post) {
	if (!pos_delivery_supported() || !is_array($post)) {
		return;
	}
	$rows = (isset($post['leveringsvalg']) && is_array($post['leveringsvalg'])) ? $post['leveringsvalg'] : array();
	$new = (isset($post['leveringsvalg_ny']) && is_array($post['leveringsvalg_ny'])) ? $post['leveringsvalg_ny'] : null;
	if (!$rows && $new === null) {
		return;
	}
	$sprog_id = isset($GLOBALS['sprog_id']) ? $GLOBALS['sprog_id'] : 1;
	$types = array_keys(pos_delivery_types($sprog_id));
	$refused = array();
	$failed = array();
	$valid = function ($choice) use ($types, &$refused) {
		if ($choice['navn'] === '' || !in_array($choice['type'], $types, true)) {
			$refused[] = ($choice['navn'] === '') ? '-' : $choice['navn'];
			return false;
		}
		return true;
	};
	$insert = function ($choice, $aktiv) {
		shopify_db_rows('INSERT INTO pos_leveringsvalg (navn, type, shopify_vaerdi, tags, aktiv, sort) VALUES ($1, $2, $3, $4::jsonb, $5, $6)',
			array($choice['navn'], $choice['type'], $choice['shopify_vaerdi'], $choice['tags'], (bool) $aktiv, $choice['sort']));
	};
	foreach ($rows as $id => $row) {
		$id = (int) $id;
		if ($id < 1 || !is_array($row)) {
			continue;
		}
		$choice = pos_delivery_posted_choice($row);
		if (!$choice['slet'] && !$valid($choice)) {
			continue;
		}
		$saved = pos_delivery_try(function () use ($id, $choice, $insert) {
			$qtxt = "SELECT navn, type, coalesce(shopify_vaerdi, '') AS shopify_vaerdi, aktiv, sort,";
			$qtxt.= " array_to_string(array(SELECT jsonb_array_elements_text(tags)), ', ') AS tags";
			$qtxt.= " FROM pos_leveringsvalg WHERE id = $1 FOR UPDATE";
			$before = shopify_db_rows($qtxt, array($id));
			if (!$before) {
				return;
			}
			$before = $before[0];
			if ($choice['revision'] !== '' && $choice['revision'] !== pos_delivery_revision($before)) {
				throw new RuntimeException("delivery choice $id was changed by someone else since the page was opened");
			}
			$used = (bool) shopify_db_rows('SELECT 1 FROM ordrer WHERE leveringsvalg_id = $1 LIMIT 1', array($id));
			if ($choice['slet']) {
				shopify_db_rows($used ? 'UPDATE pos_leveringsvalg SET aktiv = false WHERE id = $1' : 'DELETE FROM pos_leveringsvalg WHERE id = $1', array($id));
				return;
			}
			$meaningChanged = $choice['navn'] !== $before['navn'] || $choice['type'] !== $before['type']
				|| (string) $choice['shopify_vaerdi'] !== $before['shopify_vaerdi'] || $choice['tags_typed'] !== $before['tags'];
			if ($meaningChanged && $used) {
				shopify_db_rows('UPDATE pos_leveringsvalg SET aktiv = false WHERE id = $1', array($id));
				$insert($choice, $choice['aktiv']);
				return;
			}
			shopify_db_rows('UPDATE pos_leveringsvalg SET navn = $2, type = $3, shopify_vaerdi = $4, tags = $5::jsonb, aktiv = $6, sort = $7 WHERE id = $1',
				array($id, $choice['navn'], $choice['type'], $choice['shopify_vaerdi'], $choice['tags'], $choice['aktiv'], $choice['sort']));
		});
		if (!$saved) {
			$failed[] = $choice['navn'];
		}
	}
	if ($new !== null) {
		$choice = pos_delivery_posted_choice($new);
		// The empty row for a new choice is posted with every save; only a typed name makes it a choice.
		if ($choice['navn'] !== '' || $choice['shopify_vaerdi'] !== null) {
			if ($valid($choice) && !pos_delivery_try(function () use ($choice, $insert) { $insert($choice, true); })) {
				$failed[] = $choice['navn'];
			}
		}
	}
	if ($refused) {
		$txt = findtekst('pos.delivery.settings.refused|Leveringsmåde ikke gemt (navn og type skal udfyldes)', $sprog_id);
		// includes/std_func.php's alert() puts the message raw into a javascript string, and the
		// prefix is a translation an installation can change, so the whole message is escaped.
		alert(pos_sale_brand_for_alert($txt . ': ' . implode(', ', $refused)));
	}
	if ($failed) {
		$txt = findtekst('pos.delivery.settings.not_saved|Leveringsmåde ikke gemt, fordi den er ændret af en anden. Genindlæs siden og prøv igen', $sprog_id);
		alert(pos_sale_brand_for_alert($txt . ': ' . implode(', ', $failed)));
	}
}

/**
 * The delivery choices table: one row per stored choice and an empty row for a new one, printed as
 * rows of an enclosing table (systemdata/posDeliveryChoices.php). Prints nothing where the Shopify
 * schema is not installed.
 *
 * @param int       $sprog_id
 * @param bool|null $utf8Page TRUE when the page is printed as UTF-8, FALSE when in the installation's
 *                            own charset; NULL decides as the old top-menu pages did.
 * @return void
 */
function pos_render_delivery_choices_settings($sprog_id, $utf8Page = null) {
	if (!pos_delivery_supported()) {
		return;
	}
	global $db_encode, $menu;
	$types = pos_delivery_types($sprog_id);
	if ($utf8Page === null) {
		// The top-menu layout is printed as UTF-8 (includes/top_header.php), also on a LATIN9
		// installation; the stored names are converted for it.
		$utf8Page = (!isset($db_encode) || $db_encode === 'UTF8' || (isset($menu) && $menu === 'T'));
	}
	$h = function ($key) use ($sprog_id, $utf8Page) {
		return pos_delivery_html(findtekst($key, $sprog_id), $utf8Page);
	};
	$typeSelect = function ($name, $current) use ($types, $utf8Page) {
		$html = "<select class='inputbox' name='$name'>";
		foreach ($types as $type => $label) {
			$selected = ($type === $current) ? ' selected' : '';
			$html.= "<option value='$type'$selected>" . pos_delivery_html($label, $utf8Page) . "</option>";
		}
		return $html . "</select>";
	};
	print "<tr><td><table id='pos-leveringsvalg'><tbody>\n";
	print "<tr><td colspan='7'><b>" . $h('pos.delivery.settings.title|Leveringsmåder i kassen') . "</b></td></tr>\n";
	print "<tr><td colspan='7'>" . $h('pos.delivery.settings.help|Valgene i kassen. Uden valg er et salg "Taget med". En leveringsmåde, der er brugt på en ordre, gemmes som en ny version, når den rettes.') . "</td></tr>\n";
	print "<tr><td>" . $h('pos.delivery.settings.name|Navn') . "</td><td>" . $h('pos.delivery.settings.type|Type') . "</td>";
	print "<td>" . $h('pos.delivery.settings.shopify_value|Leveringsmetode i Shopify') . "</td>";
	print "<td>" . $h('pos.delivery.settings.tags|Tags (kommasepareret)') . "</td><td>" . $h('pos.delivery.settings.sort|Sortering') . "</td>";
	print "<td align='center'>" . $h('pos.delivery.settings.active|Aktiv') . "</td><td align='center'>" . $h('pos.delivery.settings.delete|Slet') . "</td></tr>\n";
	foreach (pos_delivery_choices(false) as $choice) {
		$n = "leveringsvalg[" . $choice['id'] . "]";
		$checked = $choice['aktiv'] ? ' checked' : '';
		print "<tr>";
		print "<td><input type='hidden' name='{$n}[rev]' value='" . pos_delivery_revision($choice) . "'>";
		print "<input class='inputbox' type='text' style='width:180px' name='{$n}[navn]' value='" . pos_delivery_html($choice['navn'], $utf8Page) . "'></td>";
		print "<td>" . $typeSelect("{$n}[type]", $choice['type']) . "</td>";
		print "<td><input class='inputbox' type='text' style='width:160px' name='{$n}[shopify_vaerdi]' value='" . pos_delivery_html($choice['shopify_vaerdi'], $utf8Page) . "'></td>";
		print "<td><input class='inputbox' type='text' style='width:160px' name='{$n}[tags]' value='" . pos_delivery_html($choice['tags'], $utf8Page) . "'></td>";
		print "<td><input class='inputbox' type='text' style='width:40px;text-align:right' name='{$n}[sort]' value='" . (int) $choice['sort'] . "'></td>";
		print "<td align='center'><input type='checkbox' name='{$n}[aktiv]'$checked></td>";
		print "<td align='center'><input type='checkbox' name='{$n}[slet]'></td>";
		print "</tr>\n";
	}
	$n = "leveringsvalg_ny";
	print "<tr>";
	print "<td><input class='inputbox' type='text' style='width:180px' name='{$n}[navn]' value='' placeholder='" . $h('pos.delivery.settings.new|Ny leveringsmåde') . "'></td>";
	print "<td>" . $typeSelect("{$n}[type]", 'levering') . "</td>";
	print "<td><input class='inputbox' type='text' style='width:160px' name='{$n}[shopify_vaerdi]' value=''></td>";
	print "<td><input class='inputbox' type='text' style='width:160px' name='{$n}[tags]' value=''></td>";
	print "<td><input class='inputbox' type='text' style='width:40px;text-align:right' name='{$n}[sort]' value=''></td>";
	print "<td></td><td></td>";
	print "</tr>\n";
	print "</tbody></table></td></tr>\n";
	print "<tr><td><hr></td></tr>\n";
}
