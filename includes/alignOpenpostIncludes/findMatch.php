<?php
//                         ___   _   _   ___  _
//                        / __| / \ | | |   \| |
//                        \__ \/ _ \| |_| |) | |
//                        |___/_/ \_|___|___/|_|

// --- includes/alignOpenpostIncludes/findMatch.php --- ver 4.0.8 --- 2023-07-23 ---
/// LICENSE
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
echo "<!- includes/alignOpenpostIncludes/findMatch.php -->";
$match[0] = 0;
$arraySum = array_sum($amount);
#cho "$arraySum  | $amount[0] ".abs($arraySum)."<br>";
if (abs($arraySum) < 0.005) {
	for ($i=0; $i<count($amount);$i++) $match[$i]	= 1;
}
if (!$match[0]) {
	for ($i=1;$i<count($amount);$i++) {
		if (!isset($match[$i])) $match[$i] = 0; 
		if ($match[0] == 0 && $match[$i] == 0) { // amount is not matched
			if (abs($arraySum - $amount[$i]) < 0.005) {
				$match[0] = $match[$i] = 2; // amount is matched;
			}
		}
	}
	if ($match[0] == 2) {
		$match[0] = 1;
		for ($i=1;$i<count($amount);$i++) {
			($match[$i] == 2)?$match[$i] = 0:$match[$i] = 1;
		}
	}
}
if (!$match[0]) {
	for ($i=1;$i<count($amount);$i++) {
		if (!isset($match[$i])) $match[$i] = 0; 
		if ($match[0] == 0 && $match[$i] == 0) { // amount is not matched
			if (abs($amount[0] + $amount[$i]) < 0.005) {
				$match[0] = $match[$i] = 1; // amount is matched;
			}
		}
	}
}
for ($i=1;$i<count($amount);$i++) { # find matching pairs
	for ($a=$i+1;$a<count($amount);$a++) {
		if (!isset($match[$a])) $match[$a] = 0;
		if ($match[$i] == 0 && $match[$a] == 0) { // amount is not matched
			if (abs($amount[$i] + $amount[$a]) < 0.005) {
#cho "$amount[$i] matcher  $amount[$a]<br>";
				$match[$i] = $match[$a] = 1; // amount is matched;
			}
		}
	}
}
if (!$match[0]) {
	for ($i=1;$i<count($amount);$i++) {
		for ($i2=$i+1;$i2<count($amount);$i2++) {
			if (abs($amount[0] + $amount[$i] + $amount[$i2]) < 0.005) {
				$match[0] = $match[$i] = $match[$i2] = 1;
				break(2);
			} else {
				for ($i3=$i2+1;$i3<count($amount);$i3++) {
					if (abs($amount[0] + $amount[$i] + $amount[$i2] + $amount[$i3]) < 0.005) {
						$match[0] = $match[$i] = $match[$i2] = $match[$i3] = 1;
						break(3);
					} else {
						for ($i4=$i3+1;$i4<count($amount);$i4++) {
							if (abs($amount[0] + $amount[$i] + $amount[$i2] + $amount[$i3] + $amount[$i4] ) < 0.005) {
								$match[0] = $match[$i] = $match[$i2] = $match[$i3] = $match[$i4] = 1;
								break(4);
							} else {
								for ($i5=$i4+1;$i5<count($amount);$i5++) {
									if (abs($amount[0] + $amount[$i] + $amount[$i2] + $amount[$i3] + $amount[$i4] + $amount[$i5]) < 0.005) {
										$match[0] = $match[$i] = $match[$i2] = $match[$i3] = $match[$i4] = $match[$i5] = 1;
										break(5);
									} else {
										for ($i6=$i5+1;$i6<count($amount);$i6++) {
											if (abs($amount[0] + $amount[$i] + $amount[$i2] + $amount[$i3] + $amount[$i4] + $amount[$i5] + $amount[$i6]) < 0.005) {
												$match[0] = $match[$i] = $match[$i2] = $match[$i3] = $match[$i4] = $match[$i5] = $match[$i6] = 1;
												break(6);
											} else {
												for ($i7=$i6+1;$i7<count($amount);$i7++) {
													if (abs($amount[0] + $amount[$i] + $amount[$i2] + $amount[$i3] + $amount[$i4] + $amount[$i5] +
														$amount[$i6] + $amount[$i7]) < 0.005) {
														$match[0] = $match[$i] = $match[$i2] = $match[$i3] = $match[$i4] = $match[$i5] = $match[$i6] 
														= $match[$i7] = 1;
														break(7);
/*
													} else {
														for ($i8=$i7+1;$i8<count($amount);$i8++) {
#cho 	count($amount) ." $i8 - $amount[$i8]<br>";
if (!isset($i8)) exit;
															if (abs($amount[0] + $amount[$i] + $amount[$i2] + $amount[$i3] + $amount[$i4] + 
																$amount[$i5] + $amount[$i6] + $amount[$i7] + $amount[$i8]) < 0.005) {
																$match[0] = $match[$i] = $match[$i2] = $match[$i3] = $match[$i4] = $match[$i5] = 
																$match[$i6] = $match[$i7] = $match[$i8] = 1;
																break(8);
															}
														}
*/														
													}
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}
}

for ($i=1;$i<count($amount);$i++) {
#cho "$i $amount[$i]	$match[$i]<br>"; 
	if ($match[$i] == 1) $udlign[$i] = 'on';
}

function findMatching($amount, $n) {
	global $match;
	for ($i=$n-1;$i<count($amount);$i++) {
		$chksum=0;
		for ($ia=0;$ia<=$n;$ia++) {
			$chksum+= $amount[$ia];
#cho "$amount[$ia] +";
		}
#cho "<br>";
		if (abs($chksum) < 0.005) { 
			for ($ia=0;$ia<$n;$ia++) $match[$ia] = 1;
			return 1;
		}
	}
	return 0;
}

?>
