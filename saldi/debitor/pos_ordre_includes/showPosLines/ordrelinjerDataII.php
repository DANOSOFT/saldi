<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/showPosLines/ordrelinjerDataII.php ---------- lap 3.7.7----2019.05.08-------
// LICENS
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
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190508 Move function vis_pos_linjer here


	$linjebg=$bgcolor;
	$x=0;
	$sum=0;
	$kostsum=0;
	$bruttosum=0;
	$svnr='S';
	$id=$id*1;
	$qtxt="select * from ordrelinjer where ordre_id = '$id' and varenr!='R' and ordre_id > 0 and posnr >= 0 ";
	if ($vis_saet) $qtxt.="order by saet desc,rabatgruppe,id desc";
	elseif ($status < 3) $qtxt.="order by posnr desc";
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
			$rabatart[$x]=$r['rabatart'];
			$m_rabat[$x]=$r['m_rabat']*-1;
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
			} else $pris[$x]=afrund($nettopris[$x],2);
			$nettopris[$x]=afrund($nettopris[$x],2);
			$kostsum+=$kostpris[$x]*$antal[$x];
			$nettosum+=afrund($antal[$x]*($nettopris[$x]-$nettopris[$x]/100*$rabat[$x]),2);
			$bruttosum+=$antal[$x]*$pris[$x];
			$sum+=afrund($antal[$x]*($pris[$x]-($pris[$x]*$rabat[$x]/100)),3);
			if ($lager[$x]) {
				for ($l=0;$l<count($lagernr);$l++) {
					if ($lagernr[$l]==$lager[$x] && strlen($lagernavn[$l])==1) $lager[$x]=$lagernavn[$l]; 
				}
			}
			
		}
	}



?>

