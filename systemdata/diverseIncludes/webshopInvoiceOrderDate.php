<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/diverseIncludes/webshopInvoiceOrderDate.php --- ver 5.1.0 --- 2026-10-29 ---
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
// Copyright (c) 2026-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261029 CL/MMR SST-911 Created. Saves "Fakturér webshopordrer med ordredato" from Ordrerelaterede valg.

/**
 * Saves the setting "Fakturér webshopordrer med ordredato" when the Ordrerelaterede valg form is posted.
 *
 * Runs from systemdata/diverse.php before the page prints anything. The legacy ordre_valg save branch
 * further down is left as it is. The form carries the hidden field webshopInvoiceOrderDateShown, so an
 * unticked checkbox (which the browser does not post) is only read as "off" when the checkbox was
 * actually on the form.
 *
 * debitor/orderIncludes/defaultInvoiceDate.php reads the setting.
 *
 * @param string $method  Request method ($_SERVER['REQUEST_METHOD']).
 * @param string $sektion The sektion of diverse.php being posted ($_GET['sektion']).
 * @param array  $post    The posted form ($_POST).
 * @return string|null 'on' or 'off' when the setting was saved, null when the request was not this form.
 */
function save_webshop_invoice_order_date($method, $sektion, array $post) {
	if ($method !== 'POST' || $sektion !== 'ordre_valg' || ifset($post, 'webshopInvoiceOrderDateShown') !== '1') {
		return null;
	}
	$value = (ifset($post, 'webshopInvoiceOrderDate') === 'on') ? 'on' : 'off';
	update_settings_value('webshopInvoiceOrderDate', 'ordre', $value, 'Webshop orders invoiced with an empty invoice date get the order date instead of today');
	return $value;
}
