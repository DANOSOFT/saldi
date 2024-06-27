<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/vareIncludes/addToPriceFunc.php --- lap 4.0.8 --- 2023-08-22 ---
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

if (!function_exists('addToPriceFunc')) {
function addToPriceFunc($newCost, $roundingMethod, $value, $CalculationMethod) {
	$price = 0;
	if ($CalculationMethod=="percentage") {
		$price = $newCost + (($value * $newCost) / 100);
	} elseif ($CalculationMethod=="amount") {
		$price = $newCost + $value;
	}
	if ($price && $roundingMethod=="st_rounding") return round($price);
	if ($price && $roundingMethod=="rounding_up") return ceil($price);
	if ($price && $roundingMethod=="round_down") return floor($price);
	return $price;
}}

?>
