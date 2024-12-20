<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/showPosLines/productLines.php -- lap 4.0.7 --- 2023.02.25 ---
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
// Copyright (c) 2019-2023 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20190508	LN Move function vis-pos_linjer here
// 20201031	PHR Background red if price is 0
// 20201114	PHR Enhanged 'tilfravalg' add/remove to food items, (fx. extra bacon or no tomatoes in burger) $tilfravalgNy
// 20210127 PHR Some minor design changes 
// 20210320 PHR Made it possible to change qty in items with add ons and some cleanup 202103220
// 20210503 PHR - Qty now red if stock below minimum stock - 20210503
// 20210813 LOE Added credit_type variable from pos_ordre.php file
// 20210815 LOE Set up $default_discounttxt for initial rabat on the frontend 
// 20210816 LOE and also added a block of code to function with it $default_discounttxt
// 20210822 PHR added $discounttxt and removed $default_discounttxt from above code.
// 20220812 PHR If both quantity discount and ordinary discount the quaitity discount is now regulated to fit the discountprice
//              see also ordrefunc.php & settlePOS.php
// 20230216 PHR	Removed all sum & nettosum calculations to ordrelinjerDataII.php    
// 20230225 PHR	Added decimal values to del_bord    

	print "<!-- ---------- start productLines.php ---------- -->\n";
	unlink("../temp/$db/customerDisplay.txt");
	if (isset($_GET['vare_id']) && $_GET['vare_id']) {
		$itemIdNew=$_GET['vare_id'];	
	} elseif ($varenr_ny) {
		$qtxt = "select id from varer where varenr = '". db_escape_string($varenr_ny) ."'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$itemIdNew=$r['id'];
	}
	$folger=if_isset($_GET['folger']);
	if (!$folger && isset($itemIdNew) && $tilfravalgNy && $show) {
		$tfv=explode(chr(9),$tilfravalgNy);
		for ($x=0;$x<count($tfv);$x++) {
			if ($tfv[$x]) {
				$qtxt = "select varenr,beskrivelse,salgspris from varer where id = '$tfv[$x]'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				print "<tr><td></td><td></td><td>$r[beskrivelse]</td><td colspan='2'></td>";
				print "<td align='center' style='color:red'>";
				print "<a href='pos_ordre.php?id=$id&vare_id=$itemIdNew&tilfravalgNy=". str_replace(chr(9),'|',$tilfravalgNy) ."&delFrTfv=$x'>";
				print "<button type='button' style='width:50px;height:30px;color:red'><big><big>X</big></big></button></a></td></tr>\n";
			}
		}
	}

	$s = 0;
	$stockGrp = array();
	$qtxt = "select kodenr from grupper where art = 'VG' and box8 = 'on'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$stockGrp[$s]=$r['kodenr'];
		$s++;
	}
	if (!isset($color)) $color = 0;
	for ($x=1;$x<=$varelinjer;$x++) {
		if ($vare_id[$x]) {
			$qtxt="select gruppe,beholdning,min_lager from varer where id = $vare_id[$x]";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$itemGroup[$x]  = $r['gruppe'];
			$beholdning[$x] = $r['beholdning'];
			$min_lager[$x]  = $r['min_lager'];
			}	else $itemGroup[$x] = $beholdning[$x] = $min_lager[$x] = 0;
		$txtColor = 'black';
		$qtyTitle = '';
		if (in_array($itemGroup[$x],$stockGrp) && $beholdning[$x] < $min_lager[$x]) { #20210503
			$txtColor ='red';
			$qtyTitle = "Obs!! Beholdning (". dkdecimal($beholdning[$x],0) .") mindre end ". dkdecimal($min_lager[$x],0);
		}	elseif ($beholdning[$x]) $qtyTitle = "Beholdning: ". dkdecimal($beholdning[$x],0);

		(isset($antal[$x]))?$antal[$x]*=1:$antal[$x]=0;
		($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
		if ($posnr[$x] && $show) {
			if (!$samlevare[$x]) {
				if ($pris[$x] == 0) $linjebg='red';
				print "<tr style=\"background-color:$linjebg;text-color:$color\">";
				print "<td style=\"width: 19%\">$varenr[$x]</td>";
				print "<td title = '$qtyTitle' style=\"color:$txtColor;width:7%;text-align:right\">".dkdecimal($antal[$x],2)."</td>";
				if ($lagerantal>1) print "<td style=\"width:7%;text-align:center\">$lager[$x]</td>";
				print "<td>$beskrivelse[$x]</td>";
				print "<td style=\"width:10%;text-align:right\" title=\"Kostpris ex. moms: ".dkdecimal($kostpris[$x],2)."\">";
				print dkdecimal($pris[$x],2);
				print "</td>";
				file_put_contents("../temp/$db/customerDisplay.txt",$antal[$x]."\t".$beskrivelse[$x]."\t".$pris[$x]."\n",FILE_APPEND);
				
			}
			if ($saet[$x]) {
				if (!isset($saet[$x-1]) || $saet[$x-1] != $saet[$x]) $saetpris=0;
				if ($samlevare[$x]) print "<td align=\"right\"><br></td><td style=\"width:50px;height:30px;text-align:right\">\n";
				$saetpris+=afrund($pris[$x]*$antal[$x]-($pris[$x]*$antal[$x]*$rabat[$x]/100),2); #20150214
				$pris[$x]=0;
			} else {
				print "<td style=\"width:10%;text-align:right\">".dkdecimal($pris[$x]*$antal[$x],2)."</td>";
				print "<td style=\"width:50px;height:30px;text-align:right\">\n";
			}
			if ($del_bord) {
				print "<select style=\"width:60px;height:30px;text-align:right;font-size:20px;\" name=\"delflyt[$x]\">\n";
				for ($a=0;$a<=$antal[$x];$a++) {
					print "<option style=\"text-align:right\" value=\"$linje_id[$x]:$vare_id[$x]:$a\">$a</option>";
				}
				for ($a=0;$a<=10;$a++) { #20230225
					$b = 1/$a;
					print "<option style=\"text-align:right\" value=\"$linje_id[$x]:$vare_id[$x]:$b\">1/$a</option>";
				}
				print "</select>";
			} elseif ($status<'3' && !$varenr_ny && !$saet[$x]) {
				$href="pos_ordre.php?id=$id&ret=$linje_id[$x]&antal=$antal[$x]&leveret=$leveret[$x]"; #20210320 Added antal
				print "<input type=\"button\" onclick=\"window.location.href='$href'\" $stil value=\"Ret\">\n";
			}
			print "</td></tr>\n";
		}
		if ($rabat[$x] && !$saet[$x] && !$rvnr && $show) {
			($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
			if ($rabatart[$x]=="amount") { 
/*	#20220302		
				if ($varemomssats[$x] & $momsfri[$x]!='on') $tmp=afrund($rabat[$x]+$rabat[$x]/100*$varemomssats[$x],2)*-1;
				else $tmp=afrund($rabat[$x],2)*-1;
*/
				if ($posnr[$x]) {
					print "<tr bgcolor=\"$linjebg\"><td>rabat</td><td align=\"right\">".dkdecimal($antal[$x],2)."</td><td>Rabat</td>";
					print "<td align=\"right\">".dkdecimal($rabat[$x],2)."</td><td align=\"right\">".dkdecimal($rabat[$x]*$antal[$x],2)."</td></tr>\n";
				}
#				$sum+=afrund($tmp*$antal[$x],2);
			} else {
				$tmp=afrund($pris[$x]*$rabat[$x]/-100,2);
				#if ($posnr[$x]) print "<tr bgcolor=\"$linjebg\"><td>rabats</td><td align=\"right\">".dkdecimal($antal[$x],2)."</td><td>$rabat[$x]% rabat</td><td align=\"right\">".dkdecimal($tmp,2)."</td><td align=\"right\">".dkdecimal($tmp*$antal[$x],2)."</td></tr>\n";
				if($discounttxt[$x]){
					if ($posnr[$x]) {
						print "<tr bgcolor=\"$linjebg\"><td>rabat</td><td align=\"right\">".dkdecimal($antal[$x],2)."</td>";
						print "<td>$discounttxt[$x] $rabat[$x]%</td><td align=\"right\">".dkdecimal($tmp,2)."</td>";
						print "<td align=\"right\">".dkdecimal($tmp*$antal[$x],2)."</td></tr>\n"; #20210815 + 20210816
					}
				} else {
				#if ($posnr[$x]) print "<tr bgcolor=\"$linjebg\"><td>rabats</td><td align=\"right\">".dkdecimal($antal[$x],2)."</td><td>$rabat[$x]% $credit_type</td><td align=\"right\">".dkdecimal($tmp,2)."</td><td align=\"right\">".dkdecimal($tmp*$antal[$x],2)."</td></tr>\n"; #20210813
					$discounttxt[$x] = findtekst(428, $sprog_id); #20210815 This is also used in debitor../productLines.php
					if ($posnr[$x]) {
						print "<tr bgcolor=\"$linjebg\"><td>rabat</td><td align=\"right\">".dkdecimal($antal[$x],2)."</td>";
						print "<td>$discounttxt[$x] $rabat[$x]%</td><td align=\"right\">".dkdecimal($tmp,2)."</td>";
						print "<td align=\"right\">".dkdecimal($tmp*$antal[$x],2)."</td></tr>\n"; #20210813
					}
				}	
			}
			} elseif ($saetpris && isset($saet[$x+1]) && ($saet[$x+1]!=$saet[$x]) && $saet[$x] && $show) {
			($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
			$saetpris=afrund($saetpris,2);
			$r2=db_fetch_array(db_select("select id,antal,varenr,beskrivelse,lev_varenr from ordrelinjer where samlevare='on' and ordre_id='$id' and saet='$saet[$x]'",__FILE__ . " linje " . __LINE__));
			list($lev_vnr,$srbt)=explode("|",$r2['lev_varenr']);
			$lev_vnr*=$r2['antal'];
			print "<tr bgcolor=\"$linjebg\"><td></td><td></td><td></td><td>$r2[beskrivelse]</td><td></td><td title=$srbt\" align=\"right\">".dkdecimal($lev_vnr,2)."</td>\n";
			if ($r2['varenr']!=$svnr) {
				$href="pos_ordre.php?id=$id&ret=$r2[id]&saet=$saet[$x]&leveret=$leveret[$x]";
				print "<td><!--<input type=\"button\" onclick=\"window.location.href='$href'\" $stil value=\"Ret\">--></td></tr>\n";
			}
		}
		if ($status < 3) {   #|| $tilfravalg[$x] fjernet 20190424
 			if ($folgevare[$x] > 0 || $tilfravalg[$x]) {
				if ($tilfravalg[$x]) $tfvare=explode(chr(9),$tilfravalg[$x]);
				else $tfvare[0]=$folgevare[$x];
				for($fv=0;$fv<count($tfvare);$fv++) {
					($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
					if ($tfvare[$fv]>0 && $show) {
						$qtxt="select varenr,beskrivelse,salgspris,gruppe from varer where id = '$tfvare[$fv]'";
						$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						$qtxt="select box4, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'";
						$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						$f_bogfkto=$r2['box4'];
						$f_momsfri=$r2['box7'];
					if ($f_momsfri){
						$f_momssats=0;
						$f_pris=$r['salgspris'];
					} else {
						$qtxt="select moms from kontoplan where kontonr = '$f_bogfkto' and regnskabsaar = '$regnaar'";
						$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						$kodenr=substr($r2['moms'],1);
						$r2 = db_fetch_array(db_select("select box2 from grupper where kodenr = '$kodenr' and art = 'SM'",__FILE__ . " linje " . __LINE__));
						$f_momssats=$r2['box2']*1;
						$f_pris=$r['salgspris']+$r['salgspris']*$f_momssats/100;
					}
				}
				if ($posnr[$x] && $r['varenr'] && $show) print "<tr bgcolor=\"$linjebg\"><td>$r[varenr]</td><td align=\"right\">".dkdecimal($antal[$x],2)."</td><td>".stripslashes($r['beskrivelse'])."</td><td align=\"right\">".dkdecimal($f_pris,2)."</td><td align=\"right\">".dkdecimal($antal[$x]*$f_pris,2)."</td>";
#cho __line__." S $sum<br>";
				$sum+=afrund($antal[$x]*$f_pris,2);
#cho __line__." S $sum<br>";
				}
			}
			if ($rabatantal[$x]) {
				($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
				list($grupperabat,$rabattype)=explode(";",grupperabat($rabatantal[$x],$rabatgruppe[$x]));
				if ($grupperabat) {
					if ($posnr[$x] && $show) {
						print "<tr bgcolor=\"$linjebg\"><td>rabat</td><td align=\"right\">".dkdecimal($rabatantal[$x],2)."</td><td>Rabat</td>";
						print "<td align=\"right\">".dkdecimal($grupperabat,2)."</td>";
						print "<td align=\"right\">".dkdecimal($grupperabat*$rabatantal[$x],2)."</td>\n";
					}
#cho __line__." S $sum<br>";
					$sum+=afrund($grupperabat*$rabatantal[$x],2);
#cho __line__." S $sum<br>";
				}
			} elseif ($m_rabat[$x] && !$rabatgruppe[$x]) {
				if ($posnr[$x] && $show) {
					if (!isset($antal[$x])) $antal[$x] = 0;
					if (!isset($r['varenr'])) $r['varenr'] = NULL;
					if ($m_rabat[$x] && $rabat[$x]) {
						$m_rabat[$x] = $m_rabat[$x] - $m_rabat[$x]*$rabat[$x]/100; #20220812
					}
					(isset($r['beskrivelse']) && $r['beskrivelse'])?$tmp=$r['beskrivelse']:$tmp='Rabat';
					print "<tr bgcolor=\"$linjebg\"><td>$r[varenr]</td><td align=\"right\">".dkdecimal($antal[$x],2)."</td>";
					print "<td>".stripslashes($tmp)."</td><td align=\"right\">".dkdecimal($m_rabat[$x],2)."</td>";
					print "<td align=\"right\">".dkdecimal($antal[$x]*$m_rabat[$x],2)."</td>\n";
				}
#echo __line__." S $sum<br>";
#				$sum+=afrund($m_rabat[$x]*$antal[$x],2);
#echo __line__." S $sum<br>";
			}
		}
	}
	print "<!-- ---------- end productLines.php ---------- -->\n";
?>
