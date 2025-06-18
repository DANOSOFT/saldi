<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- debitor/pos_ordre_includes/showPosLines/ordrelinjerData.php --- lap 4.0.0 --- 2021.02.117 ---
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
// Copyright (c) 2019-2021 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// LN 20190510 Move part of the function vis-pos_linjer here

	if ($samlet_pris || $status >= 3) {
		$qtxt = "select varenr from ordrelinjer where varenr='R' and ordre_id='$id'";
		($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$rvnr=$r['varenr']:$rvnr='';
		$ordresum=if_isset($_POST['sum']);
		if ($samlet_pris=='-' && $status < 3) {
			gendan_saet($id);
		} elseif ($samlet_pris!=$ordresum && $status < 3) {
			$bruttosum=0;
			$bruttosaetsum=0;
			if ($r=db_fetch_array(db_select("select id from varer where varenr='R'",__FILE__ . " linje " . __LINE__))) {
				$rvid=$r['id'];
				$rvnr='R';
				gendan_saet($id);
				$tmp=0;
				$s_pris=$samlet_pris;
				$over0=0;
				$under0=0;
				$q=db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
				while($r=db_fetch_array($q)) {
 					if ($r['antal']>0)$over0+=afrund($r['antal']*($r['pris']+$r['pris']*$r['momssats']/100),2);
					if ($r['antal']<0)$under0+=afrund($r['antal']*($r['pris']+$r['pris']*$r['momssats']/100),2);
					if (!$r['saet']) $bruttosaetsum+=afrund($r['antal']*($r['pris']+$r['pris']*$r['momssats']/100),2);
					elseif ($r['samlevare']) {
						list($tmp)=explode("|",$r['lev_varenr']);
						$bruttosaetsum+=$tmp;
					}
				}
				$bruttosum=$over0+$under0;
				$samlet_rabat=$bruttosum-$samlet_pris;
				if ($over0 && $under0) {
					$fordelingssum=$over0-$under0;
					$rabat_over0=$samlet_rabat*$over0/$fordelingssum;
					$rabat_over0=afrund($rabat_over0*100/$over0,3);
					$rabat_under0=$samlet_rabat*$under0/$fordelingssum*-1;
					$rabat_under0=afrund($rabat_under0*100/$under0,3);
				}  
				($bruttosum)?$samlet_rabatpct=afrund(($samlet_rabat)*100/$bruttosum,3):$samlet_rabatpct=0;
			} else fejl($id,'Intet varenummer "R" til kontering af øredifferencer ved rabat');
			if ($bruttosaetsum==$samlet_pris) $samlet_rabatpct=0;
			if ($samlet_rabatpct) {
				if ($over0 && $under0){
						$qtxt="update ordrelinjer set rabat='$rabat_over0' where ordre_id = '$id' and antal > 0";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$qtxt="update ordrelinjer set rabat='$rabat_under0' where ordre_id = '$id' and antal < 0";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				} else {
					$qtxt="update ordrelinjer set rabat=$samlet_rabatpct where ordre_id = '$id'"; 
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}


?>

