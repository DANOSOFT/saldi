<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/showPosLines/ordrelinjerDataII.php --- lap 4.0.8 --- 2023.10.09 ---
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
// LN 20190508 Move function vis-pos_linjer here
// 20210822 PHR - Addded $discounttxt
// 20221123 PHR - nettosum is reduced with m_rabat. See also showPosLinesFunc.php
// 20230216 PHR - Nettosum was miscalculated as m_rabat was treaded as percent.
// 20231009 PHR - Added && !$rabatrguppe as m_rabat was calculated twice

	$linjebg=$bgcolor;
	$x=0;
	$sum=0;
	$kostsum=0;
	$bruttosum=0;
	$svnr='S';
	$id=$id*1;
	$qtxt="select * from ordrelinjer where ordre_id = '$id' and varenr!='R' and ordre_id > 0 and posnr >= 0 ";
	if ($vis_saet) $qtxt.="order by saet desc,rabatgruppe,id desc";
	elseif ($status < 3) $qtxt.="order by rabatgruppe,posnr desc";
	else $qtxt.="order by posnr";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
		if ($r['varenr']!='R' && (substr($r['tilfravalg'],0,2)!='L:') || $status >= 3) {
			$x++;
			$linje_id[$x]=$r['id'];
			$vare_id[$x]=$r['vare_id'];
			$posnr[$x]=$r['posnr'];
			$varenr[$x]=$r['varenr'];
			$beskrivelse[$x]=stripslashes($r['beskrivelse']);
			$nettopris[$x]=$r['pris'];
			$kostpris[$x]=$r['kostpris'];
			$antal[$x]=$r['antal'];
			$lager[$x]=$r['lager'];
			$folgevare[$x]=$r['folgevare'];
			$tilfravalg[$x]=$r['tilfravalg'];
			$rabatgruppe[$x]=$r['rabatgruppe'];
			$rabat[$x]=$r['rabat']*1;
			$nettorabat[$x]=$rabat[$x];
			$rabatart[$x]=$r['rabatart'];
			$m_rabat[$x]=$r['m_rabat']*-1;
			$discounttxt[$x]=$r['discounttxt']; # 20210822
			$momsfri[$x]=trim($r['momsfri']);
			($momsfri[$x])?$varemomssats[$x]=0:$varemomssats[$x]=$r['momssats'];
			$saet[$x]=$r['saet']*1;
			$samlevare[$x]=$r['samlevare'];
			$leveret[$x]=$r['leveret']*1;
			$vatPrice[$x]=$r['vat_price']*1;
			if (!$vatPrice[$x] && $nettopris[$x]) {
				$vatPrice[$x]=afrund($nettopris[$x]+$nettopris[$x]*$varemomssats[$x]/100,2);
				db_modify("update ordrelinjer set vat_price='$vatPrice[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
			}
			if ($rabatgruppe[$x]) {
				if ($rabatgruppe[$x]==$rabatgruppe[$x-1]) {
					$rabatantal[$x]=$antal[$x]+$rabatantal[$x-1];
					$rabatantal[$x-1]=0;
				} else $rabatantal[$x]=$antal[$x];
			} else $rabatantal[$x]=0;
			if ($varemomssats[$x]) {
				$pris[$x]=$vatPrice[$x];
				if ($m_rabat[$x]) $m_rabat[$x]=$m_rabat[$x]+$m_rabat[$x]/100*$varemomssats[$x];
				elseif ($rabatart[$x] == 'amount' && $rabat[$x]) $rabat[$x]=$rabat[$x]+$rabat[$x]/100*$varemomssats[$x];
			} else $pris[$x]=afrund($nettopris[$x],2);
			$nettopris[$x]=afrund($nettopris[$x],2);
			$kostsum+=$kostpris[$x]*$antal[$x];
			if ($rabatart[$x] == 'amount' && $rabat[$x]) { # 20230216 added && $rabat.
				$nettosum+=afrund($antal[$x]*($nettopris[$x]-= $nettorabat[$x]),2);
				$sum+=afrund($antal[$x]*($pris[$x]-$rabat[$x]),3);
			}	else {
				if ($m_rabat[$x] && !$rabatgruppe[$x]) { # 20231009 Discountgroup is handled in productlines.php
					$sum+=$antal[$x]*($pris[$x]+$m_rabat[$x]);
					$nettosum+=$antal[$x]*($nettopris[$x]+$m_rabat[$x]); #20221123
				} else {
					$nettosum+=afrund($antal[$x]*($nettopris[$x]-$nettopris[$x]/100*$rabat[$x]),2);
					$sum+=afrund($antal[$x]*($pris[$x]-($pris[$x]*$rabat[$x]/100)),3);
				}
			}
			$bruttosum+=$antal[$x]*$pris[$x];
			if ($lager[$x]) {
				for ($l=0;$l<count($lagernr);$l++) {
					if ($lagernr[$l]==$lager[$x] && strlen($lagernavn[$l])==1) $lager[$x]=$lagernavn[$l];
				}
			}
		}
	}
?>


