<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/kassekladde_includes/vatCodes.php --- ver 5.0.0 --- 2026.10.05
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.

/** @return array<string,string> Canonical VAT codes and labels used by the web journal. */
function kassekladde_vat_codes(): array {
    $vat_codes = array();
    $allowed_vat_prefixes = array('S', 'K', 'Y', 'E');
    $vat_q = db_select("select kode, kodenr, beskrivelse, art from grupper where substring(art,2,1)='M' order by kode, kodenr", __FILE__ . " linje " . __LINE__);
    while ($vat_r = db_fetch_array($vat_q)) {
        $vat_prefix = strtoupper(trim($vat_r['kode']));
        if (!in_array($vat_prefix, $allowed_vat_prefixes, true)) {
            continue;
        }
        $vat_code = $vat_prefix . trim($vat_r['kodenr']);
        if ($vat_code) {
            $vat_codes[$vat_code] = trim($vat_r['beskrivelse']);
        }
    }
    return $vat_codes;
}

/**
 * Request values arrive as arrays when a field name is posted with [] appended,
 * and PHP 8 raises a TypeError when such a value reaches trim(). Anything that is
 * not a string or a number is therefore treated as no input at all.
 *
 * @return string Trimmed scalar text, or an empty string for unsupported input.
 */
function scalar_input_text($value) {
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
        return '';
    }
    return trim((string)$value);
}

/** @return string A known canonical code, or an empty selection. */
function normalize_vat_code($value, $vat_codes) {
    $value = scalar_input_text($value);
    if ($value === '') {
        return '';
    }
    return array_key_exists($value, $vat_codes) ? $value : '';
}

/** @return string|null Saved code, preserving the absence of a historical override. */
function get_saved_vat_code($row, $field) {
    if (!is_array($row) || !array_key_exists($field, $row) || $row[$field] === null) {
        return null;
    }
    return trim((string)$row[$field]);
}

/**
 * Tells whether a cash journal debit/credit value is an actual account number.
 *
 * The debit/credit fields also accept shortcuts such as '=' (copy from the line
 * above), 'D'/'K' (debtor/creditor lookup) and the single letter shortcuts held
 * in kontoplan.genvej. Those are translated later in the request, so any value
 * reaching an account number query before that point may still be a shortcut.
 * kontoplan.kontonr is numeric, so a shortcut would abort the query.
 *
 * @return bool  True when the value can be used as kontoplan.kontonr.
 */
function is_account_number($value) {
    if (!is_string($value) && !is_int($value)) {
        return false;
    }
    return ctype_digit(trim((string)$value));
}

/** @return string Finance account default, including a resolved shortcut, or an empty selection. */
function lookup_account_vat_code($account_no, $account_type, $regnaar, $vat_codes) {
    $account_no = scalar_input_text($account_no);
    $account_type = strtoupper(scalar_input_text($account_type));

    if ($account_no === '' || ($account_type !== '' && $account_type !== 'F')) {
        return '';
    }
    if (!is_account_number($account_no)) {
        if (strlen($account_no) != 1) {
            return '';
        }
        $qtxt = "select kontonr from kontoplan where genvej='" . db_escape_string(strtoupper($account_no)) . "' and regnskabsaar='" . db_escape_string($regnaar) . "'";
        if (!$row = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            return '';
        }
        $account_no = trim($row['kontonr']);
    }
    $qtxt = "select moms from kontoplan where kontonr='" . db_escape_string($account_no) . "' and regnskabsaar='" . db_escape_string($regnaar) . "'";
    $query = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    if ($row = db_fetch_array($query)) {
        return normalize_vat_code(if_isset($row['moms'], ''), $vat_codes);
    }

    return '';
}

/** @return string Code used by the web account lookup, preserving exemptions and explicit overrides. */
function resolve_lookup_vat_code($explicit_vat, $current_account, $current_type, $existing_account, $existing_type, $existing_vat, $momsfri, $regnaar, $vat_codes) {
    $current_account = scalar_input_text($current_account);
    $current_type = strtoupper(scalar_input_text($current_type));
    $existing_account = scalar_input_text($existing_account);
    $existing_type = strtoupper(scalar_input_text($existing_type));

    if ($current_account !== $existing_account || $current_type !== $existing_type) {
        return lookup_account_vat_code($current_account, $current_type, $regnaar, $vat_codes);
    }

    if ($explicit_vat !== null) {
        $explicit_vat = normalize_vat_code($explicit_vat, $vat_codes);
        if (!scalar_input_text($momsfri) && $explicit_vat === '') {
            return lookup_account_vat_code($current_account, $current_type, $regnaar, $vat_codes);
        }
        return $explicit_vat;
    }

    if ($existing_account !== '') {
        $existing_vat = normalize_vat_code($existing_vat, $vat_codes);
        if (!scalar_input_text($momsfri) && $existing_vat === '') {
            return lookup_account_vat_code($current_account, $current_type, $regnaar, $vat_codes);
        }
        return $existing_vat;
    }

    return lookup_account_vat_code($current_account, $current_type, $regnaar, $vat_codes);
}

/** @return string Code persisted by the web save, using its existing account-change and exemption rules. */
function resolve_post_vat_code($row_id, $field, $current_account, $current_type, $submitted_vat, $momsfri, $allow_blank, $regnaar, $vat_codes) {
    $row_id = (int)$row_id;
    $account_field = ($field === 'kreditvat') ? 'kredit' : 'debet';
    $type_field = ($field === 'kreditvat') ? 'k_type' : 'd_type';
    $existing_account = '';
    $existing_type = '';

    if ($row_id) {
        $qtxt = "select $account_field,$type_field from kassekladde where id='$row_id'";
        if ($row = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
            $existing_account = trim((string)$row[$account_field]);
            $existing_type = trim(strtoupper((string)$row[$type_field]));
        }
    }

    $current_account = scalar_input_text($current_account);
    $current_type = strtoupper(scalar_input_text($current_type));
    $submitted_vat = normalize_vat_code($submitted_vat, $vat_codes);

    // A blank VAT code is only an explicit choice when the line is marked VAT exempt.
    // Otherwise use the financial account's configured VAT code.
    if (!scalar_input_text($momsfri) && !$allow_blank && $submitted_vat === '') {
        return lookup_account_vat_code($current_account, $current_type, $regnaar, $vat_codes);
    }

    if ($current_account !== $existing_account || $current_type !== $existing_type) {
        return lookup_account_vat_code($current_account, $current_type, $regnaar, $vat_codes);
    }

    return $submitted_vat;
}

