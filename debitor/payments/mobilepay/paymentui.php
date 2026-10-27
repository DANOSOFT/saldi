<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/payments/mobilepay/paymentui.php --- ver 5.0.0 --- 2026-10-27 ---
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
// Copyright (c) 2024-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261027 CL/LH Added this header. A completed payment now sends its Vipps reference to pos_ordre.php
//                as receipt_id, so it is stored in pos_betalinger. Added mobilepay_api_base().

if (!function_exists('mobilepay_api_base')) {
	/**
	 * Base url of the Vipps MobilePay API used by mobilepay.php and mobilepayListen.php.
	 *
	 * Guarded by function_exists() so a test can define it first and point the pages at a
	 * local stub (tests/characterization/debitor/MobilepayPaymentFlowTest.test.php).
	 *
	 * @return string Base url without a trailing slash.
	 */
	function mobilepay_api_base() {
		return 'https://api.vipps.no';
	}
}

function ui_output($pretty_amount, $status_text, $status_color, $raw_amount = null, $indbetaling = null, $ordre_id = null, $ident = null) {
	if ($status_color === "red") {
		$bg = "#f00";
		$button = "
		<button style='
			width: 100%;
			height: 50px;

			margin-top: 5px;'
			onclick=\"window.location.replace('../pos_ordre.php?id=$ordre_id&godkendt=afvist')\"
		>
			Tilbage
		</button>";
	} else if ($status_color === "green") {
		$amount = (int) $raw_amount;
		$amount = $amount / 100;
		header("Location: ../pos_ordre.php?id=$ordre_id&godkendt=OK&indbetaling=$indbetaling&amount=$amount&betaling=mobilepay&receipt_id=" . urlencode((string) $ident));
		exit;
	}else if ($status_color === "yellow") {
		$bg = "#ff0";
		$button = "
		<button style='
			width: 100%;
			height: 50px;
			margin-top: 5px;
			' ";
		if ($status_text === "Afventer QR scan") {
			$button = $button . "onclick=\"window.location.replace('../pos_ordre.php?id=$ordre_id&godkendt=afvist')\">
				Annuller
			</button>";
		} else {
			$button = $button . "onclick=\"window.location.replace('mobilepayCancel.php?ident=$ident&id=$ordre_id')\">
				Tilbage
			</button>";
		}
	}



	print "<div style='
		position: 
		absolute; 
		top: 50%; 
		left: 50%; 
		transform: translate(-50%, -50%);   
		width: 350px;
		min-height: 170px;
		padding: 30px;
		background-color: #ddd;
		border-radius: 7px;
	'>";
	print "$pretty_amount kr<br>";

	print "<div style='
		width: calc(100% - 30px);

		border: 2px #000 solid;
		padding: 15px;
		vertical-align: middle;

		margin-top: 10px;
		margin-bottom: 10px;
		background-color: $bg;
	'>";
	print "$status_text";
	print "</div>";
	print "$button";


	print "</div>";
}

?>
