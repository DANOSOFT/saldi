<?php
//                         ___   _   _   ___  _
//                        / __| / \ | | |   \| |
//                        \__ \/ _ \| |_| |) | |
//                        |___/_/ \_|___|___/|_|

// --- includes/alignOpenpostIncludes/findMatch.php --- ver 5.0.0 --- 2026-05-07 ---
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
// Copyright (c) 2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 2026.05.07 CL Omskrevet: rekursiv DFS erstatter 7 nestede løkker.
//               Heltalsaritmetik (øre) for præcis sammenligning.
//               Kandidater sorteres efter størst beløb først - finder match hurtigere.
//               Tidsbegrænsning og timeout-flag bevaret fra v4.

echo "<!-- includes/alignOpenpostIncludes/findMatch.php -->";

if (!isset($findMatchTimeLimit)) $findMatchTimeLimit = 60;
set_time_limit($findMatchTimeLimit + 10);
$findMatchStartTime = microtime(true);
$findMatchTimeout   = false;

$n     = count($amount);
$match = array_fill(0, $n, 0);

// Konvertér til øre (heltal) for at undgå float-unøjagtigheder
$amtInt    = array_map(function($v) { return (int)round($v * 100); }, $amount);
$targetInt = -$amtInt[0]; // Vi søger en delmængde af [1..n-1] der summer til dette

// --- Trin 1: Alle beløb (inkl. amount[0]) går i nul ---
if (array_sum($amtInt) === 0) {
	$match = array_fill(0, $n, 1);
}

// --- Trin 2: Alle undtagen ét beløb går i nul ---
if (!$match[0]) {
	$total = array_sum($amtInt);
	for ($i = 1; $i < $n; $i++) {
		if (abs($total - $amtInt[$i]) < 1) {
			for ($j = 0; $j < $n; $j++) if ($j !== $i) $match[$j] = 1;
			break;
		}
	}
}

// --- Trin 3: Direkte 1-til-1 match med amount[0] ---
if (!$match[0]) {
	for ($i = 1; $i < $n; $i++) {
		if (abs($amtInt[0] + $amtInt[$i]) < 1) {
			$match[0] = $match[$i] = 1;
			break;
		}
	}
}

// --- Trin 4: Find par inden for amount[1..n] der udligner hinanden ---
for ($i = 1; $i < $n; $i++) {
	for ($j = $i + 1; $j < $n; $j++) {
		if (!$match[$i] && !$match[$j] && abs($amtInt[$i] + $amtInt[$j]) < 1) {
			$match[$i] = $match[$j] = 1;
		}
	}
}

// --- Trin 5: Rekursiv DFS - find 2-7 poster der tilsammen udligner amount[0] ---
if (!$match[0]) {

	// Sorter kandidater: størst absolut beløb først.
	// Regnskabsmæssigt matches store beløb hurtigere på den måde.
	$candidates = range(1, $n - 1);
	usort($candidates, function($a, $b) use ($amtInt) {
		return abs($amtInt[$b]) - abs($amtInt[$a]);
	});

	// Rekursiv dybde-først søgning med tidstjek og maks. dybde 7
	function findSubsetSumInt($amtInt, $candidates, $pos, $remaining, &$chosen, $maxDepth, &$match, &$timeout, $startTime, $timeLimit) {
		if ($remaining === 0) {
			$match[0] = 1;
			foreach ($chosen as $idx) $match[$idx] = 1;
			return true;
		}
		if ($pos >= count($candidates) || count($chosen) >= $maxDepth) return false;
		if (microtime(true) - $startTime > $timeLimit) { $timeout = true; return false; }

		for ($i = $pos; $i < count($candidates); $i++) {
			$idx      = $candidates[$i];
			$chosen[] = $idx;
			if (findSubsetSumInt($amtInt, $candidates, $i + 1, $remaining - $amtInt[$idx], $chosen, $maxDepth, $match, $timeout, $startTime, $timeLimit)) return true;
			array_pop($chosen);
			if ($timeout) return false;
		}
		return false;
	}

	$chosen = [];
	findSubsetSumInt($amtInt, $candidates, 0, $targetInt, $chosen, 7, $match, $findMatchTimeout, $findMatchStartTime, $findMatchTimeLimit);
}

// Overfør fund til $udlign (sætter flueben i formularen)
for ($i = 1; $i < $n; $i++) {
	if ($match[$i] === 1) $udlign[$i] = 'on';
}

$findMatchNoResult = (!$findMatchTimeout && !$match[0]);
$findMatchElapsed  = round(microtime(true) - $findMatchStartTime);
