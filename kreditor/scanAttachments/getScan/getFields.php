<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- kreditor/scanAttachments/getScan/getFields.php --- lap 4.0.4 --- 2021.11.25 ---
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
//
// Copyright (c) 2021-2021 saldi.dk aps
// ----------------------------------------------------------------------
// 20211217 PHR Added LineCount instead of count($a) as lines were not added if no iten numbber.

$qtxt = "select voucher_id from paperflow where id = '$scanId[$x]'";
$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$voucherId = $r['voucher_id'];

$qtxt="select var_value from settings where var_grp='creditor' and var_name='paperflowBearer'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$bearer = $r['var_value'];

$curl = curl_init();
curl_setopt_array($curl, array(
//CURLOPT_URL => "https://api.bilagscan.dk/v1/vouchers/$checktxt/text",
	CURLOPT_URL => "https://api.bilagscan.dk/v1/vouchers/$voucherId",
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_ENCODING => "",
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_TIMEOUT => 0,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => "GET",
	CURLOPT_HTTPHEADER => array(
		"Accept: application/json",
		"Authorization: Bearer $bearer"
	),
));

$request = curl_exec($curl);
curl_close($curl);

$ga = json_decode($request, true);
$ax=$bx=$cx=$dx=$ex=$fx=$gx=$hx=0;
$gVarName = $gVarValue = array();
#var_dump($ga);
$a=$b=$c=$d=$e=$f=$g=$h=0;
foreach ($ga as $gb) {
	$b=0;
	if (is_array($gb)) {
		$c=0;
		foreach ($gb as $gc) {
			if (is_array($gc)) {
				$d=0;
				foreach ($gc as $gd) {
					if (is_array($gd)) {
						$e=0;
						foreach ($gd as $ge) {
							if (is_array($ge)) {
								$f=0;
								foreach ($ge as $gf) {
									if (is_array($gf)) {
										$g=0;
										foreach ($gf as $gg) {
											if (is_array($gg)) {
												$h=0;
												foreach ($gg as $gh) { 
													$h++; 
												}
											} else { 
												if ($a==0 && $b==0 && $c!=0 && $d==0 && $f==0 && $h==0) {
#cho __line__." a $a b $b c $c e $e f $f g $g h $h | $gg<br>";
													if ($e==1 && $g==0) {
														$gVarName[$gx]=$gg;
#cho __line__." $gg<br>";
													}
													if ($e==1 && $g==3) {
														$gVarValue[$gx]=$gg;
#cho __line__." $gg<br>";
														$gx++;
													}
												}
												$g++; 
											}
										}
									} else { 
										$f++; 
									}
								}
							} else { 
								if ($a==0 && $b==0 && $c!=0 && $d==0 && $f==0 && $g==0 && $h==0) {
#cho __line__." a $a b $b c $c e $e f $f g $g h $h | $ge<br>";
									if ($e==0) {
										$eVarName[$ex]=$ge;
#cho __line__." $ge<br>";
									}
									if ($e==3 || $e==4) { #20211217 Added  '$e==3 ||' as value is there now?? 
										$eVarValue[$ex]=$ge;
#cho __line__." $ge<br>";
										$ex++;
									}
								}
								$e++; 
							}
						}
					} else { 
						$d++; 
					}
				}
			} else { 
				if ($a==0 && $b==0 && $c==2 && $d==0 && $e==0 && $f==0 && $g==0 && $h==0) $dateTime=$gc;
				$c++; 
			}
		}
	} else { 
		$b++; 
	}
}
$qtxt = "";
for ($e=0;$e<count($eVarName);$e++) {
#cho __line__." $eVarName[$e] $eVarValue[$e]<br>";
	if ($eVarName[$e]=='company_vat_reg_no' && $eVarValue[$e]) {
		$qtxt.= "cvrnr = '$eVarValue[$e]',";
	} elseif ($eVarName[$e]=='company_name' && $eVarValue[$e]) {
		$qtxt.= "firmanavn = '$eVarValue[$e]',";
	} elseif ($eVarName[$e]=='invoice_date' && $eVarValue[$e]) {
		$qtxt.= "ordredate = '$eVarValue[$e]',fakturadate = '$eVarValue[$e]',";
	} elseif ($eVarName[$e]=='voucher_number' && $eVarValue[$e]) {
		$qtxt.= "fakturanr = '$eVarValue[$e]',";
	} elseif ($eVarName[$e]=='payment_date' && $eVarValue[$e]) {
		$qtxt.= "due_date = '$eVarValue[$e]',";
	} elseif ($eVarName[$e]=='country' && $eVarValue[$e]) {
		$qtxt.= "LAND = '$eVarValue[$e]',";
	} elseif ($eVarName[$e]=='currency' && $eVarValue[$e]) {
		$qtxt.= "valuta = '$eVarValue[$e]',";
	} elseif ($eVarName[$e]=='total_amount_excl_vat' && $eVarValue[$e]) {
		$totalAmountExVat = $eVarValue[$e];
		$qtxt.= "sum = '$eVarValue[$e]',";
	} elseif ($eVarName[$e]=='total_amount_incl_vat' && $eVarValue[$e]) {
		$totalAmountInclVat = $eVarValue[$e];
	} elseif ($eVarName[$e]=='total_vat_amount_scanned'&& $eVarValue[$e]) {
		$totalVat = $eVarValue[$e];
	} elseif ($eVarName[$e]=='payment_account_number' && $eVarValue[$e]) {
		$PayAccNo = $eVarValue[$e];
	} elseif ($eVarName[$e]=='payment_reg_number' && $eVarValue[$e]) {
		$PayRegNo = $eVarValue[$e];
	}

	#	echo "$e : <b>$eVarName[$e]:</b> $eVarValue[$e]<br>";
}
if (!$totalVat && $totalAmountInclVat && $totalAmountExVat) $totalVat = $totalAmountInclVat - $totalAmountExVat;
if ($totalVat) {
	$qtxt.= "moms = '$totalVat',";
	$vatPercent = round($totalVat*100/$totalAmountExVat,2);
}
if ($qtxt) {
	$qtxt = trim($qtxt,',');
	$qtxt = "update ordrer set ".$qtxt." where id = '$id[$x]'";
#cho __line__." $qtxt<br>";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "update paperflow set lines = '". count($gVarName) ."' where id = '$scanId[$x]'"; 
#cho __line__." $qtxt<br>";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$z=0;
	$qtxt = "select * from ordrelinjer where ordre_id = '$id[$x]'";
#cho __line__." $qtxt<br>";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$lineId[$x][$z]      = $r['id'];
		$levVarenr[$x][$z]   = $r['varenr'];
		$antal[$x][$z]       = $r['antal'];
		$beskrivelse[$x][$z] = $r['beskrivelse'];
		$pris[$x][$z]        = $r['pris'];
		$z++;
	}
	$qt1 = $qt2 = "";
	$a=$b=$c=$d=0;
	$article_number = array();
	for ($g=0;$g<count($gVarName);$g++) {
#cho __line__. " $gVarName[$g] $gVarValue[$g]<br>";
		if ($gVarName[$g]=='article_number') {
			$article_number[$a] = db_escape_string($gVarValue[$g]);
			$a++;
		}
		if ($gVarName[$g]=='quantity') {
			$quantity[$b]=$gVarValue[$g];
			$b++;
		} 
		if ($gVarName[$g]=='description') {
			$description[$c]=db_escape_string($gVarValue[$g]);
			$c++;
		}
		if ($gVarName[$g]=='unit_price') {
			$unit_price[$d]=$gVarValue[$g];
			$d++;
		}
	} 
	$ScanLineSum=0;
	for ($a=0;$a<count($article_number);$a++) {
		$ScanLineSum+= $quantity[$a] * $unit_price[$a];  
	}
	$lineCount = $a;
	if ($b > $a) $lineCount = $b;
	if ($c > $b) $lineCount = $c;
	if ($d > $c) $lineCount = $d;
	for ($a=0;$a<$lineCount;$a++) {
#cho "A $article_number[$a] ". $lineId[$x][$a] ." ". $levVarenr[$x][$a] ." ". $description[$a]."<br>";
		if ($ScanLineSum == $totalAmountInclVat) $unit_price[$a] = $unit_price[$a] * 100 / (100+$vatPercent);
		(isset($quantity[$a]))?$quantity[$a]*=1:$quantity[$a]=0;
		(isset($unit_price[$a]))?$unit_price[$a]*=1:$unit_price[$a]=0;
		if ($lineId[$x][$a]) {
			if (!$levVarenr[$x][$a]) {
				$qtxt = "update ordrelinjer set lev_varenr = '$article_number[$a]' where id = '". $lineId[$x][$a] ."'";
#cho "$qtxt<br>";
				db_modify ($qtxt, __FILE__ . " linje " . __LINE__);
			}
			if (!$antal[$x][$a]) {
				$qtxt = "update ordrelinjer set antal = '$quantity[$a]' where id = '". $lineId[$x][$a]."'";
#cho "$qtxt<br>";
				db_modify ($qtxt, __FILE__ . " linje " . __LINE__);
			}
			if (!$beskrivelse[$x][$a]) {
				$qtxt = "update ordrelinjer set beskrivelse = '$description[$a]' where id = '". $lineId[$x][$a] ."'";
#cho "$qtxt<br>";
				db_modify ($qtxt, __FILE__ . " linje " . __LINE__);
			}
			if (!$pris[$x][$a]) {
				$qtxt = "update ordrelinjer set pris = '$unit_price[$a]' where id = '". $lineId[$x][$a] ."'";
#cho "$qtxt<br>";
				db_modify ($qtxt, __FILE__ . " linje " . __LINE__);
			}
			$posnr=$a+1;
			$qtxt = "update ordrelinjer set posnr = '$posnr' where id = '". $lineId[$x][$a] ."'";
			db_modify ($qtxt, __FILE__ . " linje " . __LINE__);
		} else {
			$qtxt = "insert into ordrelinjer (ordre_id,lev_varenr,antal,beskrivelse,pris) values ";
			$qtxt.= "('$id[$x]','$article_number[$a]','$quantity[$a]','$description[$a]','$unit_price[$a]')";
#cho "$qtxt<br>";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
	}
}	
?>
