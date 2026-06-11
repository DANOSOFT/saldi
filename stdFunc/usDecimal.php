<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/usDecimal.php --- lap 4.0.8 --- 2023-06-23 ---
// LICENS
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
// Copyright (c) 2003-2023 saldi.dk aps
// ----------------------------------------------------------------------

if (!function_exists('usdecimal')) {
function usdecimal($tal,$decimaler = NULL) {
	$tal = trim($tal);
	if (!$decimaler && $decimaler!='0') $decimaler=2;
	if (!$tal){
		$tal="0";
		if ($decimaler) {
			$tal.=',';
			for ($x=1;$x<=$decimaler;$x++) $tal.='0';
		}
	}
	$tal = str_replace(".","",$tal);
	$tal = str_replace(",",".",$tal);
	if (!is_numeric($tal)) $tal = 0;
	if ($decimaler < 4) {
		($decimaler < 3)?$tmp = 3:$tmp = $decimaler;
		$tal=round($tal+0.0001,3);
	}
	if (!$tal){
		$tal="0";
		if ($decimaler) {
			$tal.='.';
			for ($x=1;$x<=$decimaler;$x++) $tal.='0';
		}
	}
	return $tal;
}}
?>
