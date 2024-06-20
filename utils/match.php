<?php
//                         ___   _   _   ___  _
//                        / __| / \ | | |   \| |
//                        \__ \/ _ \| |_| |) | |
//                        |___/_/ \_|___|___/|_|

// --- includes/alignOpenpostIncludes/findMatch.php --- ver 4.0.8 --- 2016-14-04--------
// LICENS>
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2016 DANOSOFT ApS
// ----------------------------------------------------------------------
echo "<!- includes/alignOpenpostIncludes/findMatch.php -->";
$amount = array(85.878,27,-1314.313,-1611.413,-3199.400,-0.440,-6696.550,-3574.100,-1862.400,-1805.813,-4919.000,-3241.500,0.790,-568.675,-4420.238,-12058.338,-7500.475,-2162.763,-14850.500,-4885.075,-4042.138,-10334.550,-2275.538,-4612.750,-3550.775,-3005.350,0.120,-7311.975,-5370.175,-1415.563,-6107.388,-13855.638,-35.620,-8233.213,-6601.700,-11817.975,-2229.950,-5789.038,-1172.750,-4264.525,-2922.875,52816.060,-15388.513,-3911.600,-7738.175,-1946.188,-957.838,-13008.988,-7068.163,-3019.600,-29560.575,-3624.775,0.330,210.375,-4817.488,-14631.988,67447.980,-38.840,1218.800,-2379.313,-3981.463,-42476.025,-3208.900,-930.675,-1586.013,-32997.950,-2166.813,-6914.738,45923.970,-9969.275,-8571.825,-4317.025,-4442.075,10.000,-7591.600,-2939.475,-6176.138,-16995.875,905.250,-1202.988,-4613.175,-17223.850,-385.975,-1848.975,-2160.438,-1079.113,-20721.213,-373.000,75604.140,-1717.275,-0.820,-6265.638,-9512.613,-736.250,-21500.838,-7707.325,-6541.463,-11036.825,-4636.513,-2320.238,88992.630,0.090,-7505.000,-5231.100,-3914.000,-13054.538,-6607.088,-2376.575,45244.320,-5206.363,-17872.413,53206.770,-9587.488,0.880,93656.740,-2174.288,-2603.075,-20623.900,-3883.963,-4836.850,-5422.300,-2290.675,60002.720,-1153.750,-12023.975,-2732.813,-1790.913,-6852.125,-4323.775,-6154.625,-1772.425,101884.230,-3779.250,-8376.525,-285.775,-2083.275,-2911.875,-5128.538,-17724.375,34208.850,-361.900,-835.588,2956.387,-16419.138,-2059.650,-17151.750,-1966.725,-8120.263,84039.990,-21426.413,82261.270,-5788.900,-28000.000,-486.375,-9532.825,-26519.800,-1633.650,-1298.175,56412.780,-3621.025,-3699.363,-2912.988,-10073.675,99391.050,-3721.225,-13673.563,-73589.400,23145.150,-11248.888,-5295.463,-1950.850,-11411.775,-2774.350,-3719.625,-10295.325,41995.360,3044.862,-4406.725,-3100.963,-10726.975,0.880,44518.250,53.125,-7603.738,-3460.400,-16604.525,-7385.450,-5603.675,-6685.263,-18615.788,-4774.175,-8978.638,-3024.263,-3296.775,-4429.663,-8581.463,-16386.613,-2280.000,-3389.088,-4536.113,-12195.075,-676.275,-2503.188,-622.250,-146.438,-443.750,-5497.875,6078.070,-13879.400,4735.250,-6089.650,-4745.113,-6005.725);
$match[0] = 0;
for ($i=1;$i<count($amount);$i++) {
	if (!isset($match[$i])) $match[$i] = 0;
	if ($match[0] == 0 && $match[$i] == 0) { // amount is not matched
		if (abs($amount[0] + $amount[$i]) < 0.005) {
			$match[0] = $match[$i] = 1; // amount is matched;
#			echo __line__." $i $refnr[$i] $amount[$i]<br>";
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
echo 	count($amount) ." $i8 - $amount[$i8]<br>";
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
	if ($match[$i] == 1) {
		echo "$amount[$i],";
		$udlign[$i] = 'on';
	}
}

function findMatching($amount, $n) {
	global $match;
	for ($i=$n-1;$i<count($amount);$i++) {
		$chksum=0;
		for ($ia=0;$ia<=$n;$ia++) {
			$chksum+= $amount[$ia];
#echo "$amount[$ia] +";
		}
#echo "<br>";		
		if (abs($chksum) < 0.005) { 
			for ($ia=0;$ia<$n;$ia++) $match[$ia] = 1;
			return 1;
		}
	}
	return 0;
}

?>
