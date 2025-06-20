<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/showPosLines/ordrelinjer.php ---------- lap 3.7.7----2019.05.10-------
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
// LN 20190510 Move part of the function vis_pos_linjer here

	if ($samlet_pris || $status >= 3) {
		$r=db_fetch_array(db_select("select varenr from ordrelinjer where varenr='R' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
		$rvnr=$r['varenr'];
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

