<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/kreditorCreate.php --- ver 5.0.0 --- 2026-10-03 ---
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
// 20261003 CL/SZ SD-721 Created: the kreditor card's insert (kreditor/kreditorkort.php), moved here unchanged so the kreditor created from the CVR register is inserted the same way.
//                kreditorNextKontonr() is the card's automatic numbering, kreditorInsert() its insert into adresser.

if (!function_exists('kreditorNextKontonr')) {
	/**
	 * The first free kreditor number from 1000, as the kreditor card assigns it when the field is empty.
	 *
	 * @param int $excludeId adresser.id to leave out (the kreditor being edited), 0 for none.
	 * @return int
	 */
	function kreditorNextKontonr($excludeId = 0) {
		$taken = array();
		$q = db_select("select kontonr from adresser where art = 'K' and id != " . (int)$excludeId . " order by kontonr", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$taken[] = $r['kontonr'];
		}
		$kontonr = 1000;
		while (in_array($kontonr, $taken)) $kontonr++;
		return $kontonr;
	}
}

if (!function_exists('kreditorInsert')) {
	/**
	 * Inserts a kreditor into adresser (art 'K'), the kreditor card's insert.
	 *
	 * @param array<string, mixed> $fields Values by column name, already escaped for SQL by the caller (the card escapes as it reads its form):
	 *   kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf, mobile, email, web, betalingsdage, kreditmax, betalingsbet, cvrnr, notes,
	 *   gruppe, bank_navn, bank_reg, bank_konto, bank_fi, erh, swift, felt_1 ... felt_5, lukket. A missing field is stored empty.
	 * @return int|null adresser.id, or null when a kreditor with the number already exists.
	 */
	function kreditorInsert(array $fields) {
		$columns = array('kontonr', 'firmanavn', 'addr1', 'addr2', 'postnr', 'bynavn', 'land', 'kontakt', 'tlf', 'mobile', 'email', 'web', 'betalingsdage',
			'kreditmax', 'betalingsbet', 'cvrnr', 'notes', 'art', 'gruppe', 'bank_navn', 'bank_reg', 'bank_konto', 'bank_fi', 'erh', 'swift',
			'felt_1', 'felt_2', 'felt_3', 'felt_4', 'felt_5', 'lukket');
		$f = array();
		foreach ($columns as $column) {
			$f[$column] = isset($fields[$column]) ? $fields[$column] : '';
		}
		$f['art'] = 'K';
		if (db_fetch_array(db_select("select id from adresser where kontonr = '$f[kontonr]' and art = 'K'", __FILE__ . " linje " . __LINE__))) {
			return null;
		}
		db_modify("insert into adresser (kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,tlf,mobile,email,web,betalingsdage,kreditmax,betalingsbet,cvrnr,notes,art,gruppe,bank_navn,bank_reg,bank_konto,bank_fi,erh,swift,felt_1,felt_2,felt_3,felt_4,felt_5,lukket) values ('$f[kontonr]','$f[firmanavn]','$f[addr1]','$f[addr2]','$f[postnr]','$f[bynavn]','$f[land]','$f[kontakt]','$f[tlf]','$f[mobile]','$f[email]','$f[web]','$f[betalingsdage]','$f[kreditmax]','$f[betalingsbet]','$f[cvrnr]','$f[notes]','K',$f[gruppe],'$f[bank_navn]','$f[bank_reg]','$f[bank_konto]','$f[bank_fi]','$f[erh]','$f[swift]','$f[felt_1]','$f[felt_2]','$f[felt_3]','$f[felt_4]','$f[felt_5]','$f[lukket]')", __FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select id from adresser where kontonr = '$f[kontonr]' and art = 'K'", __FILE__ . " linje " . __LINE__));
		return $r ? (int)$r['id'] : null;
	}
}
?>
