<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/einvoiceErrors.php --- ver 5.1.0 --- 2026-10-30 ---
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
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261030 CL/MJ SST-882 Created from Morten Ramsing's draft: recognises the rejection texts EasyUBL and Semantic return for e-invoices.
//                Pure functions, no database and no network, so the patterns can be tested with the texts customers received.

if (!function_exists('einvoice_unescape_message')) {
	/**
	 * Turns an EasyUBL/Semantic status text into plain text.
	 *
	 * The texts arrive HTML-escaped (&quot; around "0184", and the Oxalis errorResponse XML is
	 * escaped inside the message, sometimes twice), so they are decoded twice.
	 *
	 * @param string $message Raw text as received.
	 * @return string Unescaped text with whitespace collapsed.
	 */
	function einvoice_unescape_message($message) {
		$text = html_entity_decode((string) $message, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		return trim(preg_replace('/\s+/u', ' ', $text));
	}
}

if (!function_exists('einvoice_error_patterns')) {
	/**
	 * The known rejection patterns, in priority order (first match wins).
	 *
	 * Order matters: the KMD "no postbox" text and the XSLT timeout come first, because one
	 * message can contain several codes, and those two must never be read as a missing CVR.
	 *
	 * @return array<int, array{
	 *   class: string,    Machine name of the error class.
	 *   pattern: string,  PCRE pattern matched against the unescaped text.
	 *   fixable: bool,    True when adding the receiver's CVR is the known fix.
	 *   retry: bool,      True when EasyUBL retries by itself and Saldi should only wait.
	 * }>
	 */
	function einvoice_error_patterns() {
		return array(
			array('class' => 'transient_validation', 'pattern' => '/Validation did not complete \(attempt \d+ of \d+\)|XSLT transformation exceeded/i', 'fixable' => false, 'retry' => true),
			array('class' => 'no_postbox', 'pattern' => '/is not a KMD customer|has no postbox purchase/i', 'fixable' => false, 'retry' => false),
			array('class' => 'cvr_missing', 'pattern' => '/\[DK-R-017\]|mandatory to specify schemeID as "0184"/i', 'fixable' => true, 'retry' => false),
			array('class' => 'negative_price_line', 'pattern' => '/\bBR-S-08\b|UNCL 5189|Coded allowance reasons MUST belong/i', 'fixable' => false, 'retry' => false),
			array('class' => 'sender_registration', 'pattern' => '/E-APS24007/i', 'fixable' => false, 'retry' => false),
			array('class' => 'receiver_rejected', 'pattern' => '/E-APS24003|Error while transmitting request/i', 'fixable' => true, 'retry' => false),
		);
	}
}

if (!function_exists('einvoice_classify_message')) {
	/**
	 * Classifies a rejection text from EasyUBL/Semantic.
	 *
	 * E-APS24003 means the receiving side rejected the document without saying why. Every time it
	 * was investigated (MEDSHOP, October 2026) the invoice lacked the receiver's CVR, so it counts
	 * as fixable only when the invoice has no CVR.
	 *
	 * @param string $message    Status text as received (escaped or not).
	 * @param string $invoiceCvr The CVR on the invoice (ordrer.cvrnr), '' when empty.
	 * @return array{
	 *   class: string,    One of the classes in einvoice_error_patterns(), 'unknown', or 'none' for an empty text.
	 *   codes: string[],  Codes found in the text, e.g. ['DK-R-017'] or ['E-APS24003'].
	 *   fixable: bool,    True when adding the receiver's CVR and sending again is the fix.
	 *   retry: bool,      True when EasyUBL retries by itself.
	 *   text: string,     The unescaped text, for display.
	 * }
	 */
	function einvoice_classify_message($message, $invoiceCvr = '') {
		$text = einvoice_unescape_message($message);
		preg_match_all('/\[(DK-R-\d+|BR-[A-Z-]*\d+|PEPPOL-[A-Z0-9-]+)\]|\b(E-APS\d+|BR-S-\d+)\b/', $text, $m);
		$codes = array_values(array_unique(array_filter(array_merge($m[1], $m[2]))));
		$result = array('class' => 'none', 'codes' => $codes, 'fixable' => false, 'retry' => false, 'text' => $text);
		if ($text === '') {
			return $result;
		}
		$result['class'] = 'unknown';
		foreach (einvoice_error_patterns() as $p) {
			if (preg_match($p['pattern'], $text)) {
				$result['class'] = $p['class'];
				$result['fixable'] = $p['fixable'];
				$result['retry'] = $p['retry'];
				break;
			}
		}
		if ($result['class'] === 'receiver_rejected' && trim((string) $invoiceCvr) !== '') {
			$result['fixable'] = false;
		}
		return $result;
	}
}
