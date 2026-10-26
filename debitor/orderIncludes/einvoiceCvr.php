<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/orderIncludes/einvoiceCvr.php --- ver 5.0.0 --- 2026-10-26 ---
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
// 20261023 CL/MMR SD-774 Created. The order page (debitor/ordre.php) asks for the receiver's CVR when a digital invoice to an EAN would be rejected without it.
//                 einvoiceCvrSaveFromOrder() saves the typed CVR on the order and, when the user ticks it, on the debitor card.
//                 einvoiceCvrPopupView() hands the popup (javascript/einvoiceCvrPopup.js) its texts and the order's values.
// 20261026 CL/MMR SD-774 The popup treats a CVR field holding the EAN/GLN as empty. Text 5169 now names the check digit.

include_once(__DIR__ . '/../../includes/stdFunc/einvoiceCvr.php');

if (!function_exists('einvoiceCvrDigitalDelivered')) {
	/**
	 * Whether the document already reached the receiver, so asking for a CVR no longer helps.
	 *
	 * @param string|null $digitalStatus ordrer.digital_status.
	 * @return bool
	 */
	function einvoiceCvrDigitalDelivered($digitalStatus) {
		return in_array(trim((string)$digitalStatus), array('Sent', 'Received', 'Confirmed', 'Approved'), true);
	}
}

if (!function_exists('einvoiceCvrOrderEligible')) {
	/**
	 * Whether the CVR popup applies to this order at all: a sales order or credit note (the only
	 * documents sent digitally from the order page) that has not been delivered digitally yet.
	 *
	 * @param string|null $art           ordrer.art.
	 * @param string|null $digitalStatus ordrer.digital_status.
	 * @return bool
	 */
	function einvoiceCvrOrderEligible($art, $digitalStatus) {
		$art = (string)$art;
		if ($art !== 'DO' && $art !== 'DK') {
			return false;
		}
		return !einvoiceCvrDigitalDelivered($digitalStatus);
	}
}

if (!function_exists('einvoiceCvrRequestString')) {
	/**
	 * A request value as a string; arrays and other non-scalars become ''.
	 *
	 * @param mixed $value
	 * @return string
	 */
	function einvoiceCvrRequestString($value) {
		return is_scalar($value) ? (string)$value : '';
	}
}

if (!function_exists('einvoiceCvrSaveFromOrder')) {
	/**
	 * Controller for the popup's "Gem": saves the CVR the user typed on the order and, when asked,
	 * on the debitor card. Runs before ordre.php's own POST handling.
	 *
	 * Only new input is checked with einvoice_cvr_input_problem() (modulus 11 for a Danish number);
	 * nothing is written when it fails. The card is only written when its CVR is empty or still the
	 * value the popup showed ($cardWas), so a CVR someone saved on the card in the meantime is kept.
	 *
	 * For an open order (status < 3) the popup submits through the Gem button, and the order's save
	 * writes ordrer.cvrnr from $_POST['cvrnr'] again. formCvr is the value that save must use: the
	 * normalised CVR, or the order's stored CVR when the input was rejected.
	 *
	 * @param mixed      $ordreId  ordrer.id from the posted form; anything but a number is ignored.
	 * @param mixed      $cvrInput The CVR typed in the popup.
	 * @param bool       $toCard   True when the user ticked "save on the debitor card too".
	 * @param mixed      $cardWas  The card's CVR as the popup showed it.
	 * @return array{
	 *   handled: bool,         True when the order exists, is DO/DK and not delivered digitally; nothing else is done otherwise.
	 *   problem: string,       '' when the input was saved, 'missing' or 'invalid' (einvoice_cvr_input_problem()) when it was rejected.
	 *   input: string,         The input as typed, to show again with the error.
	 *   cvr: string,           The normalised CVR that was saved, '' when nothing was saved.
	 *   status: int,           ordrer.status.
	 *   savedCard: bool,       True when the card update ran (its where clause still skips a card saved in between).
	 *   cardChanged: bool,     True when the card was not updated because its CVR changed since the popup was shown.
	 *   formCvr: string|null,  The value $_POST['cvrnr'] must hold for the order's save (status < 3), null to leave the request alone.
	 * }
	 */
	function einvoiceCvrSaveFromOrder($ordreId, $cvrInput, $toCard, $cardWas) {
		$result = array(
			'handled'     => false,
			'problem'     => '',
			'input'       => einvoiceCvrRequestString($cvrInput),
			'cvr'         => '',
			'status'      => 0,
			'savedCard'   => false,
			'cardChanged' => false,
			'formCvr'     => null,
		);
		// (int) of an array is 1, so a posted id[] must not reach the cast.
		$ordreId = is_scalar($ordreId) ? (int)$ordreId : 0;
		if ($ordreId <= 0) {
			return $result;
		}
		$qtxt = "select konto_id, status, art, digital_status, cvrnr from ordrer where id = '$ordreId'";
		$order = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if (!$order || !einvoiceCvrOrderEligible(ifset($order, 'art', ''), ifset($order, 'digital_status', ''))) {
			return $result;
		}
		$result['handled'] = true;
		$result['status'] = (int)ifset($order, 'status', 0);
		$isOpen = $result['status'] < 3;

		$problem = einvoice_cvr_input_problem($result['input']);
		if ($problem !== '') {
			$result['problem'] = $problem;
			if ($isOpen) {
				$result['formCvr'] = trim((string)ifset($order, 'cvrnr', ''));
			}
			return $result;
		}

		$cvr = einvoice_normalize_cvr($result['input']);
		$result['cvr'] = $cvr;
		if ($isOpen) {
			$result['formCvr'] = $cvr;
		}
		$qtxt = "update ordrer set cvrnr = '" . db_escape_string($cvr) . "' where id = '$ordreId'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);

		$kontoId = (int)ifset($order, 'konto_id', 0);
		if ($toCard && $kontoId > 0) {
			$qtxt = "select cvrnr from adresser where id = '$kontoId'";
			$card = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			if ($card) {
				$cardNow = (string)ifset($card, 'cvrnr', '');
				$cardNowNormalised = einvoice_normalize_cvr($cardNow);
				if ($cardNowNormalised === '' || $cardNowNormalised === einvoice_normalize_cvr(einvoiceCvrRequestString($cardWas))) {
					// The where clause repeats the check against the value just read, so a card saved
					// between that read and this update keeps its CVR.
					$qtxt = "update adresser set cvrnr = '" . db_escape_string($cvr) . "' where id = '$kontoId'";
					$qtxt .= " and coalesce(cvrnr, '') = '" . db_escape_string($cardNow) . "'";
					db_modify($qtxt, __FILE__ . " linje " . __LINE__);
					$result['savedCard'] = true;
				} else {
					$result['cardChanged'] = true;
				}
			}
		}
		return $result;
	}
}

if (!function_exists('einvoiceCvrRedirectPending')) {
	/**
	 * Whether ordre.php has already printed a redirect for this request (doInvoice goes on to
	 * bogfor.php or levering.php, Levér/Modtag to levering.php), so the popup must not open on a
	 * page that is about to be left.
	 *
	 * @param string|null $bSubmit The page's $b_submit.
	 * @return bool
	 */
	function einvoiceCvrRedirectPending($bSubmit) {
		$bSubmit = (string)$bSubmit;
		return $bSubmit === 'doInvoice' || strpos($bSubmit, 'Lev') !== false;
	}
}

if (!function_exists('einvoiceCvrLoadCard')) {
	/**
	 * The debitor card's CVR, EAN and country.
	 *
	 * @param int|string|null $kontoId adresser.id (ordrer.konto_id).
	 * @return array{cvrnr: string, ean: string, land: string} Empty strings when there is no card.
	 */
	function einvoiceCvrLoadCard($kontoId) {
		$card = array('cvrnr' => '', 'ean' => '', 'land' => '');
		$kontoId = (int)$kontoId;
		if ($kontoId <= 0) {
			return $card;
		}
		$qtxt = "select cvrnr, ean, land from adresser where id = '$kontoId'";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			$card['cvrnr'] = trim((string)ifset($r, 'cvrnr', ''));
			$card['ean'] = trim((string)ifset($r, 'ean', ''));
			$card['land'] = trim((string)ifset($r, 'land', ''));
		}
		return $card;
	}
}

if (!function_exists('einvoiceCvrPopupState')) {
	/**
	 * Decides whether the order page gets the CVR popup and with which values. No database access.
	 *
	 * The EAN, CVR and country are the order's, each falling back to the debitor card when empty on
	 * the order, as debitor/api.php sendInvoice() does since SST-884.
	 *
	 * @param array{id?: int|string, art?: string, status?: int|string, udskriv_til?: string, digital_status?: string, ean?: string, cvrnr?: string, land?: string} $order
	 *                               The order as ordreside() shows it.
	 * @param array{cvrnr?: string, ean?: string, land?: string} $card The debitor card (einvoiceCvrLoadCard()).
	 * @param bool  $redirectPending einvoiceCvrRedirectPending() for this request.
	 * @param array $saveResult      einvoiceCvrSaveFromOrder()'s result for this request, array() when nothing was posted.
	 * @return array{
	 *   render: bool,          True when the page needs the popup's data and script.
	 *   open: bool,            True for an open order (status < 3), whose form is saved with the Gem button.
	 *   autoOpen: bool,        True when the popup opens as soon as the page is shown.
	 *   ean: string,           The EAN the invoice would go to.
	 *   cardEan: string,       The card's EAN, used when the order's EAN field is emptied.
	 *   cardCvr: string,       The card's CVR, sent back as einvoiceCvrCardWas.
	 *   receiverDanish: bool,  True when the receiver counts as Danish (only those are asked).
	 *   value: string,         Input to show again in the popup after it was rejected.
	 *   error: string,         '', 'missing' or 'invalid' from the rejected input.
	 * }
	 */
	function einvoiceCvrPopupState(array $order, array $card, $redirectPending, array $saveResult) {
		$state = array(
			'render'         => false,
			'open'           => false,
			'autoOpen'       => false,
			'ean'            => '',
			'cardEan'        => '',
			'cardCvr'        => '',
			'receiverDanish' => false,
			'value'          => '',
			'error'          => '',
			'orderId'        => (int)ifset($order, 'id', 0),
		);
		if ((int)ifset($order, 'id', 0) <= 0 || !einvoiceCvrOrderEligible(ifset($order, 'art', ''), ifset($order, 'digital_status', ''))) {
			return $state;
		}
		$orderEan = trim((string)ifset($order, 'ean', ''));
		$orderCvr = trim((string)ifset($order, 'cvrnr', ''));
		$orderLand = trim((string)ifset($order, 'land', ''));
		$state['cardEan'] = trim((string)ifset($card, 'ean', ''));
		$state['cardCvr'] = trim((string)ifset($card, 'cvrnr', ''));

		$state['ean'] = ($orderEan !== '') ? $orderEan : $state['cardEan'];
		$cvr = einvoice_cvr_present($orderCvr) ? $orderCvr : $state['cardCvr'];
		// Same rule as sendInvoice(): the order's own country field decides, never the card's.
		$land = $orderLand;
		$state['open'] = (int)ifset($order, 'status', 0) < 3;
		$state['receiverDanish'] = einvoice_receiver_country($land, '') === 'DK';

		if (ifset($saveResult, 'handled', false) && ifset($saveResult, 'problem', '') !== '') {
			$state['error'] = (string)$saveResult['problem'];
			$state['value'] = (string)ifset($saveResult, 'input', '');
		}
		$missing = einvoice_cvr_missing($state['ean'], $cvr, $land);
		$state['autoOpen'] = $missing && !$redirectPending
			&& ((string)ifset($order, 'udskriv_til', '') === 'Digitalt' || $state['error'] !== '');
		// An open order also opens the popup when "Udskriv til" changes to Digitalt; that can only
		// lead to it when the card has no CVR either and the receiver is Danish.
		$state['render'] = $state['autoOpen']
			|| ($state['open'] && !einvoice_cvr_present($state['cardCvr']) && $state['receiverDanish']);
		return $state;
	}
}

if (!function_exists('einvoiceCvrUtf8')) {
	/**
	 * Text from the database or findtekst() as UTF-8, for json_encode().
	 *
	 * @param string|null $text
	 * @param string|null $dbEncode $db_encode; anything other than 'UTF8' means ISO-8859-1.
	 * @return string
	 */
	function einvoiceCvrUtf8($text, $dbEncode) {
		$text = (string)$text;
		if ($dbEncode != 'UTF8') {
			$text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
		}
		return $text;
	}
}

if (!function_exists('einvoiceCvrPopupTexts')) {
	/**
	 * The popup's texts as UTF-8.
	 *
	 * @param int|string  $sprogId  $sprog_id.
	 * @param string|null $dbEncode $db_encode.
	 * @return array<string, string>
	 */
	function einvoiceCvrPopupTexts($sprogId, $dbEncode) {
		$ids = array(
			'title'         => '5161|CVR-nummer mangler',
			'text'          => '5162|E-fakturaer til et EAN-nummer skal også have modtagerens CVR-nummer. Uden CVR-nummer bliver fakturaen afvist af modtageren.',
			'lookup'        => '5163|Slå CVR op i NemHandelsregisteret',
			'toCardNew'     => '5164|Gem også CVR-nummeret på debitorkortet',
			'toCardReplace' => '5165|Erstat CVR-nummeret på debitorkortet',
			'invalid'       => '5169|CVR-nummeret er ikke gyldigt. Et dansk CVR-nummer har 8 cifre, og kontrolcifferet skal passe. Kunder i udlandet skal have momsnummer med landekode, fx SE232100025501.',
			'missing'       => '5161|CVR-nummer mangler',
			'save'          => '3|Gem',
			'cancel'        => '5|Annullér',
			'cvrLabel'      => '376|CVR-nr.',
			'eanLabel'      => '379|EAN-nr.',
		);
		$texts = array();
		foreach ($ids as $key => $textId) {
			if (function_exists('findtekst_utf8')) {
				$texts[$key] = (string)findtekst_utf8($textId, $sprogId);
			} else {
				$texts[$key] = einvoiceCvrUtf8(findtekst($textId, $sprogId), $dbEncode);
			}
		}
		return $texts;
	}
}

if (!function_exists('einvoiceCvrPopupRender')) {
	/**
	 * Prints the popup's data as a JSON script tag and includes javascript/einvoiceCvrPopup.js,
	 * which builds the dialog. Prints nothing when $state['render'] is false.
	 *
	 * @param array       $state    einvoiceCvrPopupState().
	 * @param int|string  $sprogId  $sprog_id.
	 * @param string|null $dbEncode $db_encode.
	 * @return void
	 */
	function einvoiceCvrPopupRender(array $state, $sprogId, $dbEncode) {
		if (!ifset($state, 'render', false)) {
			return;
		}
		$data = array(
			'texts'          => einvoiceCvrPopupTexts($sprogId, $dbEncode),
			'lookupUrl'      => einvoice_nemhandel_lookup_url(),
			'open'           => (bool)$state['open'],
			'autoOpen'       => (bool)$state['autoOpen'],
			'ean'            => einvoiceCvrUtf8($state['ean'], $dbEncode),
			'cardEan'        => einvoiceCvrUtf8($state['cardEan'], $dbEncode),
			'cardCvr'        => einvoiceCvrUtf8($state['cardCvr'], $dbEncode),
			'receiverDanish' => (bool)$state['receiverDanish'],
			'value'          => einvoiceCvrUtf8($state['value'], $dbEncode),
			'error'          => (string)$state['error'],
			'orderId'        => (int)$state['orderId'],
		);
		// No JSON_UNESCAPED_UNICODE: \u escapes keep the tag ASCII, so it reads the same in a
		// UTF-8 and an ISO-8859-1 page. The JSON_HEX_* flags keep "</script>" and quotes inert.
		$json = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
		if ($json === false) {
			$json = '{}';
		}
		print "<script type=\"application/json\" id=\"saldi-einvoice-cvr-data\">$json</script>\n";
		$jsFile = __DIR__ . '/../../javascript/einvoiceCvrPopup.js';
		$jsVersion = file_exists($jsFile) ? filemtime($jsFile) : time();
		print "<script src=\"../javascript/einvoiceCvrPopup.js?v=" . (int)$jsVersion . "\"></script>\n";
	}
}

if (!function_exists('einvoiceCvrPopupView')) {
	/**
	 * View for ordreside(): loads the debitor card, decides whether the popup is needed and prints it.
	 * Called once, after the order form of either branch (invoiced or open) has been printed.
	 *
	 * @param array       $order           See einvoiceCvrPopupState(); konto_id is used to load the card.
	 * @param bool        $redirectPending einvoiceCvrRedirectPending() for this request.
	 * @param array       $saveResult      einvoiceCvrSaveFromOrder()'s result, array() when nothing was posted.
	 * @param int|string  $sprogId         $sprog_id.
	 * @param string|null $dbEncode        $db_encode.
	 * @return void
	 */
	function einvoiceCvrPopupView(array $order, $redirectPending, array $saveResult, $sprogId, $dbEncode) {
		if ((int)ifset($order, 'id', 0) <= 0 || !einvoiceCvrOrderEligible(ifset($order, 'art', ''), ifset($order, 'digital_status', ''))) {
			return;
		}
		$card = einvoiceCvrLoadCard(ifset($order, 'konto_id', 0));
		einvoiceCvrPopupRender(einvoiceCvrPopupState($order, $card, $redirectPending, $saveResult), $sprogId, $dbEncode);
	}
}
