<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/posTxtPrint/escPosBarcode.php -- lap 3.9.3 -- 2020.09.05 ---
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
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------

if (!function_exists('escPosBarcode')) {
	function escPosBarcode ($barcode) {
		while (strlen($barcode)<12) $barcode='0'.$barcode;
		$bc = chr(27).'a'.chr(1); #Center
		$bc.= chr(29).'h'.chr(80).chr(29).'k'.chr(73).chr(10).'{B{C';
		for ($x=0;$x<12;$x+=2) $bc.= chr(substr($barcode,$x,2));
		$bc.= "\n";
		return $bc;
	}
}
?>
 
