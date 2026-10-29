<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/diverseIncludes/saveOrderOptions.php --- ver 5.1.0 --- 2026-10-29 ---
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
// 20261029 CL/MMR SD-786 Created: the save of Diverse > Ordrerelaterede valg, moved out of systemdata/diverse.php
//                 (controller/view convention) when "Vis besked når kostprisen er anslået" was added.
//                 Posted values are read with ifset() and escaped or cast where they reach SQL;
//                 update_settings_value() puts its value into the SQL as it is.

if (!function_exists('saveOrderOptions')) {
	/**
	 * Saves the order settings posted from Diverse > Ordrerelaterede valg.
	 *
	 * Writes grupper DIV 3 (box2-box14) and DIV 5 (box6 kostmetode, box8 sætvare), the order settings
	 * in the settings table, and orderNoteEnabled. Item numbers for rabat and sæt are looked up and
	 * stored as item ids. Nothing is printed: the alerts for unknown item numbers are returned for
	 * the page to show after the save.
	 *
	 * @param array      $post      The posted form ($_POST).
	 * @param int|string $sprog_id  Language for the alert texts.
	 * @param int|string $bruger_id The user, for the per-user setting ordreAutocomplete.
	 * @return string[] Alert texts to show the user, in the order they arose.
	 */
	function saveOrderOptions(array $post, $sprog_id, $bruger_id)
	{
		$alerts = array();
		$sqlText = function ($value) {
			return db_escape_string((string) $value);
		};

		$vatPrivateCustomers    = ifset($post, 'vatPrivateCustomers');
		$vatBusinessCustomers   = ifset($post, 'vatBusinessCustomers');
		$hideRevenue            = (ifset($post, 'hideRevenue') === 'on') ? 'on' : 'off'; #SD-657
		$box2                   = ifset($post, 'box2'); #Rabatvarenr
		$box3                   = ifset($post, 'box3'); #folge_s_tekst
		$box4                   = ifset($post, 'box4'); #hurtigfakt
		$box5                   = ifset($post, 'straks_deb') . ";" . ifset($post, 'straks_kred'); #straks_bogf
		$box6                   = ifset($post, 'box6'); #fifo
		$box7                   = ifset($post, 'box7'); #
		$box8                   = ifset($post, 'box8'); #vis_nul_lev
		$box9                   = ifset($post, 'box9'); #negativt_lager
		$box10                  = ifset($post, 'box10'); #
		$box11                  = ifset($post, 'box11'); #advar_lav_beh
		$box12                  = ifset($post, 'box12'); #$procentfakt
		$box13                  = ifset($post, 'procenttillag') . chr(9) . ifset($post, 'procentvare');
		$box14                  = ifset($post, 'box14');
		$kostmetode             = ifset($post, 'kostmetode');
		$saetvarenr             = ifset($post, 'saetvarenr'); #20150907
		$orderNoteEnabled       = ifset($post, 'orderNoteEnabled');
		$debitoripad            = ifset($post, 'debitoripad');
		$portovarenr            = ifset($post, 'portovarenr');
		$showDB                 = ifset($post, 'showDB');
		$showDG                 = ifset($post, 'showDG');
		$pluklisteEmail         = ifset($post, 'pluklisteEmail');
		$lockPayment            = ifset($post, 'lockPayment');
		$ordreAutocomplete      = ifset($post, 'ordreAutocomplete');
		$gs1parsing             = ifset($post, 'gs1_parsing');
		$ourRefStockSwitch      = ifset($post, 'ourRefStockSwitch');
		$stockWarningEnabled    = ifset($post, 'stockWarningEnabled');
		$showCostEstimateNotice = (ifset($post, 'showCostEstimateNotice') === 'on') ? 'on' : 'off'; #SD-786
		$showBothAddrExtra      = ifset($post, 'showBothAddrExtra');

		update_settings_value("debitoripad", "ordre", $sqlText($debitoripad), "Weather or not to include the debitor ipad system");
		update_settings_value("pluklisteEmail", "ordre", $sqlText($pluklisteEmail), "Email address to send plukliste to");
		update_settings_value("porto_varnr", "ordre", $sqlText($portovarenr), "Varenr to autmatically include on new orders");
		update_settings_value("showDB", "ordre", $sqlText($showDB), "Weather or not to show the DB on the order page");
		update_settings_value("showDG", "ordre", $sqlText($showDG), "Weather or not to show the DG on the order page");
		update_settings_value("lockedInvoiceButton", "debitor", $sqlText($lockPayment), "Locks the invoice button until payment has occured");
		update_settings_value("ordreAutocomplete", "ordre", $sqlText($ordreAutocomplete), "Enable or disable autocomplete search on order pages", $bruger_id);
		update_settings_value("gs1_parsing", "ordre", $sqlText($gs1parsing), "Enable GS1 barcode parsing on order line item entry");
		update_settings_value("ourRefStockSwitch", "ordre", $sqlText($ourRefStockSwitch), "Update order stock/warehouse from Our ref when the reference changes"); // no single quotes in the description: it goes into SQL as it is
		update_settings_value("stockWarningEnabled", "ordre", $sqlText($stockWarningEnabled), "Show popup and require approval note when selling out-of-stock items (POS + Debtor/Order)");
		update_settings_value("showBothAddrExtra", "ordre", $sqlText($showBothAddrExtra), "Show both delivery address and extra fields simultaneously on open orders");
		update_settings_value("showCostEstimateNotice", "ordre", $showCostEstimateNotice, "Show a message on delivery when a cost price is estimated (SD-786)");

		// Rabatvarenr is stored as the item id; an unknown number is kept as typed and reported
		if ($box2 && $r = db_fetch_array(db_select("select id from varer WHERE varenr = '" . $sqlText($box2) . "'", __FILE__ . " linje " . __LINE__))) {
			$box2 = $r['id'];
		} elseif ($box2) {
			$alerts[] = str_replace('XXXXX', $box2, findtekst('289|Varenr. XXXXX eksisterer ikke.', $sprog_id));
		}
		if ($box14 && !$box2) {
			$alerts[] = findtekst('1875|Samlet pris forudsætter at der er et varenr. til rabat', $sprog_id); #20210820
			$box14 = '';
		}
		#20150907 ->
		$saetvareid = 0;
		$qtxt = "select id from varer WHERE varenr = '" . $sqlText($saetvarenr) . "'";
		if ($saetvarenr && $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			$saetvareid = (int) $r['id'];
		}
		if ($saetvarenr && !$saetvareid) {
			$alerts[] = findtekst('1876|Varenummer for sæt eksisterer ikke', $sprog_id);
		}
		# <- 20150907

		$boxes = array(
			'box2' => $box2, 'box3' => $box3, 'box4' => $box4, 'box5' => $box5, 'box6' => $box6, 'box7' => $box7, 'box8' => $box8,
			'box9' => $box9, 'box10' => $box10, 'box11' => $box11, 'box12' => $box12, 'box13' => $box13, 'box14' => $box14,
		);
		$boxSql = array_map($sqlText, $boxes);
		if ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr='3'", __FILE__ . " linje " . __LINE__))) {
			$set = array();
			foreach ($boxSql as $name => $value) {
				$set[] = "$name='$value'";
			}
			$qtxt = "update grupper set " . implode(',', $set) . " WHERE id = '" . (int) $r['id'] . "'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		} else {
			$qtxt = "insert into grupper (beskrivelse,kodenr,art," . implode(',', array_keys($boxSql)) . ") ";
			$qtxt.= "values ('Div_valg (Ordrer)','3','DIV','" . implode("','", $boxSql) . "')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}

		// Save VAT options to settings table
		update_settings_value("vatPrivateCustomers", "ordre", $sqlText($vatPrivateCustomers), "Show VAT on orders for private customers");
		update_settings_value("vatBusinessCustomers", "ordre", $sqlText($vatBusinessCustomers), "Show VAT on orders for business customers");
		update_settings_value("hideRevenue", "finans", $hideRevenue, "Keep turnover from users without access to Settings");

		if ($r = db_fetch_array(db_select("select id from grupper WHERE art = 'DIV' and kodenr='5'", __FILE__ . " linje " . __LINE__))) {
			$qtxt = "update grupper set box6='" . $sqlText($kostmetode) . "',box8='$saetvareid' WHERE id = '" . (int) $r['id'] . "'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		} else {
			$qtxt = "insert into grupper (beskrivelse,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13) ";
			$qtxt.= "values ('Div_valg','5','DIV','','','','','','','" . $sqlText($kostmetode) . "','','','','','','')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}

		if ($r = db_fetch_array(db_select("select id from settings where var_name='orderNoteEnabled'", __FILE__ . " linje " . __LINE__))) { #20210729
			$qtxt = "update settings set var_value='" . $sqlText($orderNoteEnabled) . "' WHERE id='" . (int) $r['id'] . "'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		} else {
			$qtxt = "insert into settings (var_name, var_value) values ('orderNoteEnabled','" . $sqlText($orderNoteEnabled) . "')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}

		update_settings_value("debitoripad", "ordre", $sqlText($debitoripad), "Weather or not to include the debitor ipad system");

		return $alerts;
	}
}
