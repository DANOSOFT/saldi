<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/orderIncludes/einvoiceRejection.php --- ver 5.1.0 --- 2026-10-30 ---
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
// 20261030 CL/MJ SST-882 Created: an invoice whose e-invoice was rejected shows why at the top of the order page (debitor/ordre.php).
//                The message comes from einvoice_events, and a short explanation per kind of rejection says what to do.

include_once(__DIR__ . '/../../includes/stdFunc/einvoiceEvents.php');

if (!function_exists('einvoiceRejectionTexts')) {
	/**
	 * The notice's texts, through findtekst().
	 *
	 * @param int|string $sprogId $sprog_id.
	 * @return array<string, string> Keyed by 'title', 'message' and the error classes that have an explanation.
	 */
	function einvoiceRejectionTexts($sprogId) {
		return array(
			'title' => findtekst('einvoice.rejection.title|E-fakturaen blev afvist', $sprogId),
			'message' => findtekst('einvoice.rejection.message|Besked fra e-fakturaudbyderen', $sprogId),
			'cvr_missing' => findtekst('einvoice.rejection.cvr_missing|Fakturaen mangler modtagerens CVR-nummer. Sæt CVR-nummeret på fakturaen, og send den igen.', $sprogId),
			'receiver_rejected' => findtekst('einvoice.rejection.receiver_rejected|Modtageren afviste dokumentet uden at oplyse årsagen. Mangler fakturaen modtagerens CVR-nummer, så sæt det på, og send igen.', $sprogId),
			'no_postbox' => findtekst('einvoice.rejection.no_postbox|EAN-nummeret har ingen postkasse hos modtagerens udbyder. Kontakt modtageren, og få et gyldigt EAN-nummer.', $sprogId),
			'negative_price_line' => findtekst('einvoice.rejection.negative_price_line|En linje har negativ stykpris. Skriv linjen som negativt antal med positiv pris, og send igen.', $sprogId),
			'sender_registration' => findtekst('einvoice.rejection.sender_registration|Fejl i afsenderens registrering hos e-fakturaudbyderen. Kontakt saldi.dk support.', $sprogId),
		);
	}
}

if (!function_exists('einvoiceRejectionHtml')) {
	/**
	 * The notice as HTML. Every value is escaped: the message is EasyUBL's text and can contain markup.
	 *
	 * @param array{status: string, message: string, class: string, created: string} $rejection From einvoice_latest_rejection().
	 * @param array<string, string> $texts   From einvoiceRejectionTexts().
	 * @param string                $charset Charset of the page, for htmlspecialchars().
	 * @return string
	 */
	function einvoiceRejectionHtml(array $rejection, array $texts, $charset = 'UTF-8') {
		$e = function ($s) use ($charset) {
			return htmlspecialchars((string) $s, ENT_QUOTES, $charset);
		};
		$created = $rejection['created'] !== '' ? date('d-m-Y H:i', strtotime($rejection['created'])) : '';
		$html = "<div class='einvoiceRejection' style='margin:6px auto;padding:8px 12px;max-width:1100px;background:#fdecea;border:1px solid #c0392b;border-radius:4px'>";
		$html .= "<b>&#9888; " . $e($texts['title']) . "</b> <span style='color:#555'>(" . $e($rejection['status']) . ($created !== '' ? ', ' . $e($created) : '') . ")</span>";
		if (isset($texts[$rejection['class']])) {
			$html .= "<div style='margin-top:4px'>" . $e($texts[$rejection['class']]) . "</div>";
		}
		if ($rejection['message'] !== '') {
			$html .= "<div style='margin-top:4px;color:#555;white-space:pre-wrap'>" . $e($texts['message']) . ": " . $e($rejection['message']) . "</div>";
		}
		return $html . "</div>\n";
	}
}

if (!function_exists('einvoiceRejectionView')) {
	/**
	 * Prints the notice on an invoice or credit note whose newest e-invoice event is a rejection.
	 *
	 * @param int        $ordreId  ordrer.id.
	 * @param string     $art      ordrer.art.
	 * @param int|string $status   ordrer.status.
	 * @param int|string $sprogId  $sprog_id.
	 * @param string     $dbEncode $db_encode; anything other than 'UTF8' means the page is ISO-8859-1.
	 * @return void
	 */
	function einvoiceRejectionView($ordreId, $art, $status, $sprogId, $dbEncode) {
		if ((int) $ordreId <= 0 || (int) $status < 3 || !in_array($art, array('DO', 'DK'), true)) {
			return;
		}
		$rejection = einvoice_latest_rejection($ordreId);
		if ($rejection) {
			print einvoiceRejectionHtml($rejection, einvoiceRejectionTexts($sprogId), $dbEncode == 'UTF8' ? 'UTF-8' : 'ISO-8859-1');
		}
	}
}
