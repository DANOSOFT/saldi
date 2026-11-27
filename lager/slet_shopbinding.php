<?php
//                         ___   _   _   __  _
//                        / __| / \ | | |  \| |
//                        \__ \/ _ \| |_| | | |
//                        |___/_/ \_|___|__/|_|
//
// ----------lager/slet_shopbinding.php----------ver 5.1.1-----2026-11-10-------
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261104 LOE MB-62 The shop-binding delete is refused for a read-only Items user.
// 20261110 CDX/ASR Require module write access before this mutation endpoint runs.
//

@session_start();
$s_id=session_id();

$title="Slet shopbinding";
$modulnr=9;
$css="../css/standard.css";

include(__DIR__ . '/../includes/connect.php');
$permission_key = 'lager.varer';
$permission_level = 'write';
include(__DIR__ . '/../includes/online.php');
include(__DIR__ . '/../includes/std_func.php');

$id = (int)($_GET['id'] ?? 0);
# MB-62 a read-only Items user may not remove a shop binding: the link is hidden and this page refuses it
if ($rettigheder !== '' && (substr($rettigheder, 9, 1) === '2' || substr($rettigheder, 9, 1) < '1')) {
	error_log('MB-62: shop binding delete refused for user ' . $brugernavn . ' (Items right ' . substr($rettigheder, 9, 1) . ')');
	$mb62Refused = true;
} else {
	$mb62Refused = false;
	if ($id > 0) {
		db_modify("delete from shop_varer where saldi_id='$id'", __FILE__ . " linje " . __LINE__);
	}
}
print "<meta http-equiv=\"refresh\" content=\"0;URL=varekort.php?id=$id\">";
?>
