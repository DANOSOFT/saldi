<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/finans/kladdePosting.php --- ver 5.0.0 --- 2026-10-03 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20121122 - Åbne poster udlignes ikke mere automatisk hvis forskelligt projektnummer. Søg 20121122
// 20130210 - Break ændret til break 1
// 20130224 - Ny saldo for kreditorer og debitorer vises nu i aktuelle regnskabsaar.
// 20130404 - addslashes erstattet af db_escape_string.
// 20130404 - Blokerer nu for postering hvis max. antal overskredet.
// 20130802 - Tilføjet simulering - i betatest - knap ikke synlig.
// 20130814 - Lukker istedet for returnering til kladde v. luk af simulering.
// 20131028 - Fejl v. manglende diffkonto til valuta. tilføjet !. Søg 20131028.
// 20131115	-	Fejlbogføring ved modsvarende differencer i forslellige bilag. Søg 20131115
// 20131117	-	Tilføjet !$debet[$i]||!$kredit[$i]. Kunne ikke bogføre 1 linjers posteringer. Søg 20131117
// 20140228 -	Det er nu atter muligt at bogføre selvom de enlekte bilag ikke stemmer, hvis summen gør # 20140228
// 20140428 -	Afrundingsfejl ved eu moms (PHR Danosoft jf http://forum.saldi.dk/viewtopic.php?f=5&t=1130)# Søg 20140428
// 20141128 -	Fejl i kontrolfunktion minus ændret til plus.
// 20150527	- Som ovenstående minus ændret til plus.
// 20170516 - Linjer uden indhold i debet og kredit slettes fra kladde ved bøgføring. 20170516
// 20190116 MSC - Rettet topmenu design til og rettet isset fejl
// 20190201 MSC - Rettet topmenu design til og gjort bogføring mere overskueligt med ny style.
// 20190205 MSC - Rettet isset fejl
// 20190213 MSC - Rettet topmenu design til
// 20190321 PHR Added call to equalizeMatchingRecords (in 'genberegn.php') to matches open records.
// 20190426 PHR Added 'genberegn' below includes includes to ensure that values in kontoplan is up to date.
// 20201205 PHR Added alert for missing account nr in EU VAT
// 20201205 PHR Lots of cleanup and rewriting of currency diff. handling
// 20210319 LOE - Translated some of these texts from Danish to English
// 20221018	PHR - Some cleanup.
// 20230324	PHR - Correted errors in 'findtekst' (wrong text ID)
// 20230626 PHR - Moved this part into 'if ($r = db_fetch_array($q)) {' from below as amount was aligned
// 20250612 PHR	- Extra diff controle
// 20251210 PHR - Missing financialYear in vat account lookup for E & Y
// 20260316 PHR Better error handling for missing debitor group
// 20260417 Sawaneh: overwrite vat selection
// 20260730 CL/NTR function bogfor: Removed the internal transaktion('begin')/('commit') so the caller
//                 (which already wraps the call together with the tmpkassekl cleanup) keeps ownership of
//                 the transaction; transaktion('rollback') before an early exit is kept since those paths
//                 still terminate the request immediately.
// 20260812 CX/PHR - Use the VAT code saved on each cash-journal line during validation, simulation and posting.
// 20260819 CX/PHR - Fall back to account VAT unless a confirmed journal line has VAT on only one side.
// 20260822 Sawaneh Show journal id and note in heading and above movements so output identifies the journal;
//                  print icon on the simulation/posting view prints just the report
// 20260907 CDX/PHR Update following fiscal years' opening balances within the journal posting transaction.
// 20260907 CDX/LH Share the difference predicate with the read-only assistant checks.
// 20260924 CDX/PHR Correct currency-rounding signs and reject unbalanced stored postings before commit.
//
//
// 20261001 CDX/LH Created. Extract shared journal posting and result-returning validation.
// 20261001 CDX/LH Use the latest participant date for new settlements, preserving historical reports.
// 20261001 CDX/LH Resolve participant dates before posting so existing settlements are never rewritten.

require_once __DIR__ . '/../std_func.php';
require_once __DIR__ . '/../db_query.php';
require_once __DIR__ . '/../openpost/udlignId.php';

/** Internal validation failure, contained by kladde_bogfor(). */
final class KladdePostingError extends RuntimeException {}

/**
 * Post a journal without taking ownership of the caller's transaction.
 * @param bool $propagateValidation Let the web page retain its former print-and-exit validation paths.
 * @return string|null Validation error, or null on success.
 */
function kladde_bogfor($kladde_id, $kladdenote, $simuler, bool $propagateValidation = false) {
    try {
        return kladde_bogfor_internal($kladde_id, $kladdenote, $simuler);
    } catch (KladdePostingError $error) {
        if ($propagateValidation) {
            throw $error;
        }
        return $error->getMessage();
    }
}

/** Read a saved journal VAT override.
 * @return string|null */
function kladde_saved_vat_override($row, $field) {
	if (!is_array($row) || !array_key_exists($field, $row) || $row[$field] === NULL) {
		return NULL;
	}
	return trim((string)$row[$field]);
}

/** Post the stored journal; the caller owns the transaction.
 * @return string|null Validation error, or null on success. */
function kladde_bogfor_internal($kladde_id,$kladdenote,$simuler) {
	global $connection;
	global $regnaar;
	global $brugernavn;

	$kladde_id = (int)$kladde_id;
	$regnaar = (int)$regnaar;
	$kladdenote = db_escape_string($kladdenote);
	$brugernavnSql = db_escape_string($brugernavn);
	$CurencyCheckSum=$tjeksum=$posteringer=$transantal=$transtjek=$d_sum=$k_sum=0;

	if (!isset ($valutakurs)) $valutakurs = array();
	if (!isset ($b_antal)) $b_antal = 0;
	if (!isset ($y)) $y = NULL;
	if (!isset ($momsart)) $momsart = NULL;

	($simuler)?$tabel='simulering':$tabel='transaktioner';

	$r=db_fetch_array(db_select("select max(id) as id from transaktioner where kladde_id = $kladde_id",__FILE__ . " linje " . __LINE__));
	if ($r['id']) {
		return("Kladden er allerede bogført");
	}

	$d_momsart=array(); $k_momsart=array();
	if ($kladdenote) db_modify("update kladdeliste set kladdenote = '$kladdenote' where id = '$kladde_id'",__FILE__ . " linje " . __LINE__);
	$y=0;
	$v_antal=0;
	$b_diff=$bv_diff=0;
	$query = db_select("select * from kassekladde where kladde_id = $kladde_id order by bilag",__FILE__ . " linje " . __LINE__);
	while ($row =	db_fetch_array($query)) {
		if ($row['debet'] || $row['kredit']) {
			$y++;
			$postid[$y]=$row['id'];
			if ($row['debet']>0) $transantal++;
			if ($row['kredit']>0) $transantal++;
			$eufaktnr[$y]="!@&/(=bh#jH%Tf)D"; # maa ikke vaere en vaerdi som kan risikere at vaere et relt fakturanr.
			$bilag[$y]=$row['bilag'];
			$beskrivelseRaw[$y] = (string)$row['beskrivelse'];
			$beskrivelse[$y]=db_escape_string($beskrivelseRaw[$y]);
			$d_type[$y]=$row['d_type'];
			$debet[$y]=$row['debet'];
			$k_type[$y]=$row['k_type'];
			$kredit[$y]=$row['kredit'];
			if (!$debet[$y]) $d_type[$y]='F';
			if (!$kredit[$y]) $k_type[$y]='F';
			$fakturaRaw[$y] = (string)$row['faktura'];
			$faktura[$y]=db_escape_string($fakturaRaw[$y]);
			$amount[$y]=$row['amount'];
			$diffkonto[$y] = 0;
			if ($row['valuta'] && $row['amount'] && ($row['debet']||$row['kredit'])) {
				list($dkkamount[$y],$diffkonto[$y],$valutakurs[$y])=kladde_valutaopslag($amount[$y],$row['valuta'],$row['transdate']);
			} else $dkkamount[$y]=$amount[$y];
			$momsfri[$y]=$row['momsfri'];
			$debetvat[$y]=kladde_saved_vat_override($row, 'debetvat');
			$kreditvat[$y]=kladde_saved_vat_override($row, 'kreditvat');
			$afd[$y]=$row['afd'];
			$ansat[$y]=$row['ansat']*1;
			$projekt[$y]=$row['projekt'];
			$valuta[$y]=$row['valuta']*1;
			$ordre_id[$y]=$row['ordre_id']*1;
			if (!isset ($valutakurs[$y])) $valutakurs[$y] = NULL;
			if (!$valutakurs[$y]) $valutakurs[$y]=100;
			$transdate[$y]=$row['transdate'];
			$forfaldsdate[$y]=$row['forfaldsdate'];
			$betal_id[$y]=$row['betal_id'];
			if (!isset ($bilag[$y-1])) $bilag[$y-1] = NULL;
			if ($bilag[$y]==$bilag[$y-1]) {
				if ($valuta[$y]!='DKK') {
					$b_afd[$b_antal]=$afd[$y];
					$b_ansat[$b_antal]=$ansat[$y];
					$b_projekt[$b_antal]=$projekt[$y];
					$b_valuta[$b_antal]=$valuta[$y];
					$b_ordre_id[$b_antal]=$ordre_id[$y];
					$b_kurs[$b_antal]=$valutakurs[$y];
					$b_diffkonto[$b_antal]=$diffkonto[$y];
				}
				if ($row['debet']) {
					$b_sum[$b_antal]+=$dkkamount[$y];
					$b_diff+=$dkkamount[$y];
					$bv_sum[$b_antal]+=$amount[$y];
					$bv_diff+=$amount[$y];
				}
				if ($row['kredit']) {
					$b_sum[$b_antal]-=$dkkamount[$y];
					$b_diff-=$dkkamount[$y];
					$bv_sum[$b_antal]-=$amount[$y];
					$bv_diff-=$amount[$y];
				}
			} else {
				$b_antal++;
				$b_bilag[$b_antal]=$bilag[$y];
				$b_sum[$b_antal]=0;
				$bv_sum[$b_antal]=0;
				$b_faktura[$b_antal]=$faktura[$y];
				$b_transdate[$b_antal]=$transdate[$y];
				if ($valuta[$y]!='DKK') {
					$b_afd[$b_antal]=$afd[$y];
					$b_ansat[$b_antal]=$ansat[$y];
					$b_projekt[$b_antal]=$projekt[$y];
					$b_valuta[$b_antal]=$valuta[$y];
					$b_ordre_id[$b_antal]=$ordre_id[$y];
					$b_kurs[$b_antal]=$valutakurs[$y];
					$b_diffkonto[$b_antal]=$diffkonto[$y];
				}
				if ($row['debet']) {
					$b_sum[$b_antal]+=$dkkamount[$y];
					$b_diff+=$dkkamount[$y];
					$bv_sum[$b_antal]+=$amount[$y];
					$bv_diff+=$amount[$y];
				}
				if ($row['kredit']) {
					$b_sum[$b_antal]-=$dkkamount[$y];
					$b_diff-=$dkkamount[$y];
					$bv_sum[$b_antal]-=$amount[$y];
					$bv_diff-=$amount[$y];
				}
#				if ($row['debet'])  $b_sum[$b_antal]+=$dkkamount[$y];
#				if ($row['kredit']) $b_sum[$b_antal]-=$dkkamount[$y];
			}
			if (((strstr($d_type[$y],'D'))||(strstr($d_type[$y],'K'))) && $debet[$y]>0) {
				if (!$simuler) kladde_openpost($d_type[$y], $debet[$y], $bilag[$y], $fakturaRaw[$y], $amount[$y], $beskrivelseRaw[$y], $transdate[$y], $postid[$y], $valuta[$y], $valutakurs[$y], $forfaldsdate[$y], $betal_id[$y],$projekt[$y],$kladde_id);
				list ($debet[$y], $d_momsart[$y]) =kladde_gruppeopslag($d_type[$y], $debet[$y]);
				if (($d_momsart[$y]=='E')||($d_momsart[$y]=='Y')) $eufaktnr[$y]=$faktura[$y]; # Bruges laengere nede til at undgaa at transantal oeges v. eu momsposteringer.
			}
			if ((($k_type[$y]=='D')||($k_type[$y]=='K')) && $kredit[$y]>0) {
				if (!$simuler) kladde_openpost($k_type[$y], $kredit[$y], $bilag[$y], $fakturaRaw[$y], $amount[$y]*-1, $beskrivelseRaw[$y], $transdate[$y], $postid[$y], $valuta[$y], $valutakurs[$y], $forfaldsdate[$y], $betal_id[$y],$projekt[$y],$kladde_id);
				list ($kredit[$y], $k_momsart[$y])=kladde_gruppeopslag($k_type[$y], $kredit[$y]);
				if (($k_momsart[$y]=='E')||($k_momsart[$y]=='Y')) $eufaktnr[$y]=$faktura[$y];  # Bruges laengere nede til at undgaa at transantal oeges v. eu momsposteringer.
			}
			$momsfri[$y]=str_replace(" ","",$momsfri[$y]);
			$debet[$y]=str_replace(" ","",$debet[$y]);
			$kredit[$y]=str_replace(" ","",$kredit[$y]);
			$d_amount[$y]=$d_moms[$y]=$d_momskto[$y]=$d_modkto[$y]=0;
			$k_amount[$y]=$k_moms[$y]=$k_momskto[$y]=$k_modkto[$y]=0;
			if ($debet[$y]>0)  $d_amount[$y]=$dkkamount[$y];
			if ($kredit[$y]>0) $k_amount[$y]=$dkkamount[$y];
			$logdate=date("Y-m-d");
			$logtime=date("H:i");
			list ($x, $month, $x)=explode('-', $transdate[$y]);
			if (!$afd[$y]){$afd[$y]=0;}
			if (!isset ($d_momsart[$y])) $d_momsart[$y] = NULL;
			if (!isset ($k_momsart[$y])) $k_momsart[$y] = NULL;
			if ((!$momsfri[$y])&&($debet[$y]>0)&&($d_amount[$y]>0)&&(substr($momsart,0,1)!='E')&&(substr($momsart,0,1)!='Y')) list ($d_amount[$y], $d_moms[$y], $d_momskto[$y], $d_modkto[$y])=kladde_momsberegning($debet[$y], $d_amount[$y], $d_momsart[$y], $k_momsart[$y], $debetvat[$y], trim((string)$kreditvat[$y]) !== '');
			if ((!$momsfri[$y])&&($kredit[$y]>0)&&($k_amount[$y]>0)&&(substr($momsart,0,1)!='E')&&(substr($momsart,0,1)!='Y')) list ($k_amount[$y], $k_moms[$y], $k_momskto[$y], $k_modkto[$y])=kladde_momsberegning($kredit[$y], $k_amount[$y], $k_momsart[$y], $d_momsart[$y], $kreditvat[$y], trim((string)$debetvat[$y]) !== '');
		} elseif (!$row['debet'] && !$row['kredit'] && $row['id']) { #20170516
			db_modify("delete from kassekladde where id = '$row[id]'",__FILE__ . " linje " . __LINE__);
		}
	}
	# end while
	$posteringer=$y;
	for ($y=1; $y<=$posteringer; $y++) {
		$d_moms[$y]*=1;
		$k_moms[$y]*=1;
		if (($d_modkto[$y]>0)&&($eufaktnr[$y]!=$faktura[$y])){
			if ($k_moms[$y]) {
				$posteringer++;
				$k_momskto[$posteringer]=$k_momskto[$y];
				$k_moms[$posteringer]=$k_moms[$y];
				$bilag[$posteringer]=$bilag[$y];
				$beskrivelse[$posteringer]=$beskrivelse[$y];
				$faktura[$posteringer]=$faktura[$y];
				$afd[$posteringer]=$afd[$y];
				$transdate[$posteringer]=$transdate[$y];
				$ansat[$posteringer]=$ansat[$y];
				$projekt[$posteringer]=$projekt[$y];
				$ordre_id[$posteringer]=$ordre_id[$y];
				$valutakurs[$posteringer]=$valutakurs[$y];
				$valuta[$posteringer]=$valuta[$y];
			}
			$k_moms[$y]=$d_moms[$y];
			$k_momskto[$y]=$d_modkto[$y];
			$transantal++;
		}

		if (($k_modkto[$y]>0)&&($eufaktnr[$y]!=$faktura[$y])){
			if ($d_moms[$y]) {
				$posteringer++;
				$d_momskto[$posteringer]=$d_momskto[$y];
				$d_moms[$posteringer]=$d_moms[$y];
				$bilag[$posteringer]=$bilag[$y];
				$beskrivelse[$posteringer]=$beskrivelse[$y];
				$faktura[$posteringer]=$faktura[$y];
				$afd[$posteringer]=$afd[$y];
				$transdate[$posteringer]=$transdate[$y];
				$transdate[$posteringer]=$transdate[$y];
				$ansat[$posteringer]=$ansat[$y];
				$projekt[$posteringer]=$projekt[$y];
				$ordre_id[$posteringer]=$ordre_id[$y];
				$valutakurs[$posteringer]=$valutakurs[$y];
				$valuta[$posteringer]=$valuta[$y];
			}
			$d_moms[$y]=$k_moms[$y];
			$d_momskto[$y]=$k_modkto[$y];
			$transantal++;
		}
		if ($d_momskto[$y]>0) $transantal++;

		if ($k_momskto[$y]>0) $transantal++;
		if ($eufaktnr[$y]!=$faktura[$y]&&$d_momskto[$y]>0&&$k_momskto[$y]>0&&$d_momskto[$y]!=$k_momskto[$y]) $transantal--; # indsat 280807 grundet fejl ved konti (i kontoplan) m. eumoms
		if ($debet[$y]>0) {
			#$tjeksum=$tjeksum+$d_amount[$y];
			($d_momskto[$y])?$tmp=$d_moms[$y]*1:$tmp=0;
			$qtxt = "insert into $tabel ";
			$qtxt.= "(kontonr,bilag,transdate,logdate,logtime,beskrivelse,debet,faktura,";
			$qtxt.= "kladde_id,afd,ansat,projekt,valuta,valutakurs,ordre_id,moms)";
			$qtxt.= "values ";
			$qtxt.= "($debet[$y],$bilag[$y],'$transdate[$y]','$logdate','$logtime','$beskrivelse[$y]','$d_amount[$y]','$faktura[$y]',";
			$qtxt.= "'$kladde_id','$afd[$y]', '$ansat[$y]','$projekt[$y]','$valuta[$y]','$valutakurs[$y]','$ordre_id[$y]','$tmp')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "select * from $tabel where kontonr='$debet[$y]' and bilag='$bilag[$y]' and transdate='$transdate[$y]' ";
			$qtxt.= "and logdate='$logdate' and logtime='$logtime' and beskrivelse='$beskrivelse[$y]' and debet='$d_amount[$y]' ";
			$qtxt.= "and faktura='$faktura[$y]' and kladde_id='$kladde_id' and afd='$afd[$y]'";
			if (db_fetch_array(db_select($qtxt ,__FILE__ . " linje " . __LINE__))) {
				$transtjek++;
				$qtxt = "select id, saldo from kontoplan where kontonr='$debet[$y]' and regnskabsaar=$regnaar";
				$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				$row= db_fetch_array($query);
				$kasklid[$transtjek]=$row['id'];
				$kasklmonth[$transtjek]=$row['saldo'];
				$transamount[$transtjek]=$d_amount[$y];
			} else print "<tr><td>Der er sket en fejl ved bogføring af bilag: $bilag[$y], debetkonto: $debet[$y]!</td></tr>";
		}
		if ($kredit[$y]>0) {
			#$tjeksum=$tjeksum-$k_amount[$y];
			($k_momskto[$y])?$tmp=$k_moms[$y]*-1:$tmp=0;
			$qtxt = "insert into $tabel ";
			$qtxt.= "(kontonr,bilag,transdate,logdate,logtime,beskrivelse,kredit,faktura,";
			$qtxt.= "kladde_id,afd,ansat,projekt,valuta,valutakurs,ordre_id,moms)";
			$qtxt.= " values ";
			$qtxt.= "($kredit[$y],$bilag[$y],'$transdate[$y]','$logdate','$logtime','$beskrivelse[$y]','$k_amount[$y]','$faktura[$y]',";
			$qtxt.= "'$kladde_id','$afd[$y]','$ansat[$y]','$projekt[$y]','$valuta[$y]','$valutakurs[$y]','$ordre_id[$y]','$tmp')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "select * from $tabel where kontonr='$kredit[$y]' and bilag=$bilag[$y] and transdate='$transdate[$y]' ";
			$qtxt.= "and logdate='$logdate' and logtime='$logtime' and beskrivelse='$beskrivelse[$y]' and kredit='$k_amount[$y]' ";
			$qtxt.= "and faktura='$faktura[$y]' and kladde_id=$kladde_id and afd=$afd[$y]";
			if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$transtjek++;
				$qtxt = "select id, saldo from kontoplan where kontonr='$kredit[$y]' and regnskabsaar=$regnaar";
				$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				$r = db_fetch_array($q);
				$kasklid[$transtjek]=$r['id'];
				$kasklmonth[$transtjek]=$r['saldo'];
				$transamount[$transtjek]=$k_amount[$y]*-1;
			} else print "<tr><td>Der er sket en fejl ved bogføring af bilag: $bilag[$y], kreditkonto: $kredit[$y]!</td></tr>";
		}

		if ($d_momskto[$y]>0) { #moms af debetpostering
			$qtxt = "insert into $tabel ";
			$qtxt.= "(kontonr,bilag,transdate,logdate,logtime,beskrivelse,debet,faktura,";
			$qtxt.= "kladde_id,afd,ansat, projekt,valuta,valutakurs,ordre_id,moms)";
			$qtxt.= " values ";
			$qtxt.= "($d_momskto[$y],$bilag[$y],'$transdate[$y]','$logdate','$logtime','$beskrivelse[$y]','$d_moms[$y]','$faktura[$y]',";
			$qtxt.= "'$kladde_id','$afd[$y]','$ansat[$y]','$projekt[$y]','$valuta[$y]','$valutakurs[$y]','$ordre_id[$y]','0')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "select * from $tabel where kontonr=$d_momskto[$y] and bilag=$bilag[$y] and transdate='$transdate[$y]' ";
			$qtxt.= "and logdate='$logdate' and logtime='$logtime' and beskrivelse='$beskrivelse[$y]' and debet='$d_moms[$y]' ";
			$qtxt.= "and faktura='$faktura[$y]' and kladde_id=$kladde_id and afd=$afd[$y]";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			if ( db_fetch_array($q)) {
				$transtjek++;
				$qtxt = "select id, saldo from kontoplan where kontonr='$d_momskto[$y]' and regnskabsaar=$regnaar";
				$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				$r= db_fetch_array($q);
				$kasklid[$transtjek]=$r['id'];
				$kasklmonth[$transtjek]=$r['saldo'];
				$transamount[$transtjek]=$d_moms[$y];
			} else print "<tr><td>Der er sket en fejl ved bogføring af bilag: $bilag[$y], debetkonto: $d_momskto[$y]!</td></tr>";
		}
		if ($k_momskto[$y]>0) { #moms af kreditpostering
						$qtxt = "insert into $tabel ";
			$qtxt.= "(kontonr,bilag,transdate,logdate,logtime,beskrivelse,kredit,faktura,";
			$qtxt.= "kladde_id,afd,ansat,projekt,valuta,valutakurs,ordre_id,moms)";
			$qtxt.= " values ";
			$qtxt.= "($k_momskto[$y],$bilag[$y],'$transdate[$y]','$logdate','$logtime','$beskrivelse[$y]','$k_moms[$y]','$faktura[$y]',";
			$qtxt.= "'$kladde_id','$afd[$y]','$ansat[$y]','$projekt[$y]','$valuta[$y]','$valutakurs[$y]','$ordre_id[$y]','0')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt = "select * from $tabel where kontonr=$k_momskto[$y] and bilag=$bilag[$y] and transdate='$transdate[$y]' ";
			$qtxt.= "and logdate='$logdate' and logtime='$logtime' and beskrivelse='$beskrivelse[$y]' and kredit='$k_moms[$y]' ";
			$qtxt.= "and faktura='$faktura[$y]' and kladde_id=$kladde_id and afd=$afd[$y]";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			if (db_fetch_array($q)){
				$transtjek++;
				$query = db_select("select id, saldo from kontoplan where kontonr='$k_momskto[$y]' and regnskabsaar=$regnaar",__FILE__ . " linje " . __LINE__);
				$row= db_fetch_array($query);
				$kasklid[$transtjek]=$row['id'];
				$kasklmonth[$transtjek]=$row['saldo'];
				$transamount[$transtjek]=$k_moms[$y]*-1;
			} else print "<tr><td>Der er sket en fejl ved bogføring af bilag: $bilag[$y], kreditkonto: $k_momskto[$y]!</td></tr>";
		}
	}
	if ($b_diff) {
		$kontoliste=array();
		# 20131115 ->
		$kontokredit[$y]=array();
		$kontodebet[$y]=array();
		for ($i=1;$i<=$b_antal;$i++) {
			if ($bilag[$i]!=$bilag[$i-1] && $bilag[$i]!=$bilag[$i+1] && (!$debet[$i]||!$kredit[$i]) && $valuta[$i] != $valuta[$i-1]) { # 20131117 -- 20140228 tilføjet: && $valuta[$i] != $valuta[$i-1]
				throw new KladdePostingError("Manglende modpostering i bilag $bilag[$i]!");
			}
		# <- 20131115
			if (!isset($valutasum[$i])) $valutasum[$i] = 0;
			$valutasum[$i]=afrund($valutasum[$i],3);
			$CurencyCheckSum+=$valutasum[$i];
			$b_sum[$i]=afrund($b_sum[$i],2);
			if (!in_array($b_diffkonto[$i],$kontoliste)) {
				$y++;
				$kontoliste[$y]=$b_diffkonto[$i];
				$kontokredit[$y]=0;
				$kontodebet[$y]=0;
			}
			if ($b_sum[$i] > 0) {
				$k_sum=$k_sum+$b_sum[$i];
				$kontokredit[$y]=$kontokredit[$y]+$b_sum[$i];
				$tjeksum=$tjeksum+$b_sum[$i]; #20141128
			} else {
				$d_sum=$d_sum-$b_sum[$i];
				$kontodebet[$y]=$kontodebet[$y]-$b_sum[$i];
				$tjeksum=$tjeksum+$b_sum[$i]; #20150527
			}

			if ($b_sum[$i] && abs($b_sum[$i]) < 0.1 && afrund($bv_sum[$i], 2) == 0 && $b_diffkonto[$i]) {
					$roundingDebit = max(0, -$b_sum[$i]);
					$roundingCredit = max(0, $b_sum[$i]);
					$beskrivelse ='valutadiff';
					$qtxt = "insert into $tabel ";
					$qtxt.= "(kontonr,bilag,transdate,logdate,logtime,beskrivelse,debet,kredit,faktura,kladde_id,afd,ansat,projekt,";
					$qtxt.= "valuta,valutakurs,ordre_id,moms)";
					$qtxt.= " values ";
					$qtxt.= "('$b_diffkonto[$i]','$b_bilag[$i]','$b_transdate[$i]','$logdate','$logtime','$beskrivelse','$roundingDebit',";
					$qtxt.= "'$roundingCredit','$b_faktura[$i]','$kladde_id','$b_afd[$i]','$b_ansat[$i]','$b_projekt[$i]','DKK',";
					$qtxt.= "'100','$b_ordre_id[$i]','0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$tjeksum -= $b_sum[$i];

				} elseif (($kontokredit[$y] || $kontodebet[$y]) && !$b_diffkonto[$i] && $valuta[$i])  { #20131028 -- 20140228 tilføjet: && $valuta[$i
				throw new KladdePostingError("Manglende konto til valutadiffencer! (bilag: $b_bilag[$i])");
			}
		}
	}
	// Check the actual stored entries, including VAT and currency corrections, not only working sums.
	$qtxt = "SELECT COALESCE(SUM(COALESCE(debet,0)-COALESCE(kredit,0)),0) AS difference ";
	$qtxt .= "FROM $tabel WHERE kladde_id=" . (int)$kladde_id;
	$postedBalance = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (!$postedBalance || (float)$postedBalance['difference'] != 0.0) {
		$differenceText = $postedBalance ? dkdecimal($postedBalance['difference'], 3) : 'ukendt';
		return "Kladde $kladde_id balancerer ikke efter bogføring (debet minus kredit: $differenceText). Ingen posteringer er gemt.";
	}
	if (abs($tjeksum)<=0.01) { # && $transtjek==$transantal){
		$dato=date("Y-m-d");
		if ($simuler) {
			$qtxt="update kladdeliste set bogfort = 'S', bogforingsdate = '$dato', bogfort_af = '$brugernavnSql' where id = '$kladde_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else {
			$qtxt="delete from kassekladde where debet='0' and kredit='0' and kladde_id='$kladde_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="update kladdeliste set bogfort = 'V', bogforingsdate = '$dato', bogfort_af = '$brugernavnSql' where id = '$kladde_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			for ($x=1; $x<=$transtjek; $x++) {
				$query = db_select("select saldo from kontoplan where id='$kasklid[$x]'",__FILE__ . " linje " . __LINE__);
				$row= db_fetch_array($query);
				$temp=$row['saldo'];
				if (!$temp) {$temp=0;}
				$transamount[$x]=($temp+$transamount[$x]);
				db_modify("update kontoplan set saldo = $transamount[$x] where id = '$kasklid[$x]'",__FILE__ . " linje " . __LINE__);
			}
			include_once(__DIR__ . '/../updateFollowingOpeningBalances.php');
			updateFollowingOpeningBalances($regnaar);
		}
#xit;
	} else {
		throw new KladdePostingError("Der er konstateret en afvigelse!\nKladde ikke bogført\nKontakt venligst Saldi's udviklerteam!");
	}
#xit;
} #endfunc bogfor
######################################################################################################################################
/** Write the debtor/creditor open item using the journal identity.
 * @return void */
function kladde_openpost($art,$debet,$bilag,$faktura,$amount,$beskrivelse,$transdate,$bilag_id,$valutakode,$valutakurs,$forfaldsdate,$betal_id,$projekt,$kladde_id = null){
	global $connection;
	global $regnaar;
	$kladde_id = $kladde_id === null ? (int)ifset($GLOBALS, 'kladde_id', 0) : (int)$kladde_id;

	$art = db_escape_string((string)$art);
	$debet = db_escape_string(str_replace(' ', '', (string)$debet));
	$bilag = (int)$bilag;
	$amount = (float)$amount;
	$beskrivelse = db_escape_string((string)$beskrivelse);
	$transdate = db_escape_string((string)$transdate);
	$forfaldsdate = db_escape_string((string)$forfaldsdate);
	$bilag_id = (int)$bilag_id;
	$valutakode = (int)$valutakode;
	$valutakurs = (float)$valutakurs;
	$betal_id = db_escape_string((string)$betal_id);
	$projekt = (int)$projekt;
	$faktura = (string)$faktura;
	// 20260902 CL/LH  L4 finding dunning-run DEVY-5: a zero-amount posting line (e.g. a reminder
	// finalised without a fee) must not create an open item. A 0,00 row can never settle
	// anything (the +/-0.005 match window below only matches other zero rows) and it shows
	// up as a duplicate unsettled post under the same faktnr as the original invoice.
	if (abs((float)$amount) < 0.005) return;

## Finder kreditorens valuta;
	if ($valutakode) {
		$r = db_fetch_array(db_select("select box1 from grupper where art = 'VK' and kodenr = '$valutakode'",__FILE__ . " linje " . __LINE__));
		$valuta=db_escape_string((string)$r['box1']);
	} else {
		$valuta='DKK';
	}
	$fakturaSql = db_escape_string($faktura);
	$udlignet=0;
	$belob=$amount*-1;
	$debet=str_replace(" ","",$debet);
	$qtxt = "select id from adresser where kontonr = '$debet' and art ='$art'";
	$accounts = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($accounts)) {
		$konto_id=(int)$row['id'];
		$udlign_id = udlign_next_id();
		$min=$belob-0.005;
		$max=$belob+0.005;
		if (strpos($faktura,";")) {
			$invoices = explode(';',$faktura);
		} else {
			$invoices = [$faktura];
		}

		// Find the latest participant date before writing any settlement rows.
		// Updating by settlement id alone can reach older rows from a legacy allocator.
		$matches = [];
		$udlign_date = $transdate;
		for ($x=0;$x<count($invoices);$x++) {
			$qtxt = "select id,transdate from openpost where konto_id='$konto_id' and projekt='$projekt' and udlignet!='1' and ";
			$qtxt.= " faktnr='" . db_escape_string($invoices[$x]) . "'";
			if (count($invoices) == 1) {
				$qtxt.= " and amount >= '$min' and amount < '$max'";
			}
			if ($matches) {
				$qtxt.= " and id NOT IN (" . implode(',', array_keys($matches)) . ")";
			}
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			if ($r = db_fetch_array($q)) {
				$matches[(int)$r['id']] = $r;
				$udlign_date=max($udlign_date, $r['transdate']);
			}
		}
		foreach (array_keys($matches) as $postId) {
			$qtxt = "update openpost set udlignet = '1',udlign_date= '$udlign_date',udlign_id='$udlign_id' ";
			$qtxt.= "where id = " . (int)$postId;
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			// Keep the legacy full payment row for each matched invoice reference.
			$qtxt = "insert into openpost (konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,udlign_date,kladde_id,";
			if ($forfaldsdate) {
				$qtxt.= "forfaldsdate,betal_id,";
			}
			$qtxt.= "transdate,udlign_id,bilag_id,valuta,valutakurs,projekt) ";
			$qtxt.= "values ";
			$qtxt.= "('$konto_id','$debet','$fakturaSql','$amount','$bilag','$beskrivelse','1','$udlign_date','$kladde_id',";
			if ($forfaldsdate) {
				$qtxt.= "'$forfaldsdate','$betal_id',";
			}
			$qtxt.= "'$transdate','$udlign_id','$bilag_id','$valuta','$valutakurs','$projekt')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$udlignet=1;
		}
	}
	if ($udlignet<1) {
		if ($faktura=="-") {
			$fakturaSql="";
		}
		$qtxt = "insert into openpost (konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,transdate,kladde_id,";
		if ($forfaldsdate) {
			$qtxt.= "forfaldsdate,betal_id,";
		}
		$qtxt.= "bilag_id,valuta,valutakurs,projekt)";
		$qtxt.= " values ('$konto_id','$debet','$fakturaSql','$amount','$bilag','$beskrivelse','0','$transdate','$kladde_id',";
		if ($forfaldsdate) {
			$qtxt.= "'$forfaldsdate','$betal_id',";
		}
		$qtxt.= "'$bilag_id','$valuta','$valutakurs','$projekt')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
}
######################################################################################################################################
/** Calculate the journal VAT split.
 * @return array{0:float,1:float,2:mixed,3:mixed} */
function kladde_momsberegning($konto,$amount,$momsart,$kontrol,$lineVat=NULL,$allowBlank=false) {
	global $connection;
	global $regnaar;
	global $db;
	global $brugernavn;

	$konto = (int)$konto;
	$amount = (float)$amount;
	$regnaar = (int)$regnaar;
	$nettoamount=$amount;
	$errorTxt=$moms=$momskto=$modkto=NULL;

	$a=db_escape_string(substr((string)$momsart,0,1)); #Foerste tegn i strengen
	$b=db_escape_string(substr((string)$momsart,1,1)); #Andet tegn i strengen

	// This function is only called for lines that are not marked VAT exempt.
	// A missing line VAT code therefore falls back to the account setup.
	if (($lineVat === NULL || trim((string)$lineVat) === '') && !$allowBlank) {
		$r=db_fetch_array(db_select("select moms from kontoplan where kontonr='$konto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__));
		$effectiveVat=trim(if_isset($r['moms'], ''));
	} else {
		$effectiveVat=trim($lineVat);
	}
	if ($effectiveVat) {
		if ((($a=='E')||($a=='Y')) && $b) {
			$c=$a.'M';
			$qtxt = "select box1,box2,box3 from grupper where ";
			$qtxt.= "kode='$a' and kodenr='$b' and art='$c' and fiscal_year = '$regnaar'";
			$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			if($row =	db_fetch_array($query)) { # Så er der moms på kontoen
				$qtxt="select box1,box2,box3 from grupper where ";
				$qtxt.= "kode='$a' and kodenr='$b' and art='$c' and fiscal_year = '$regnaar'";
				$q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				$x=$row['box2'];
				$moms=$amount/100*$x;
				$momskto=trim($row['box1']);
				$modkto=trim($row['box3']);
			}
		} else {
			$a=db_escape_string(substr($effectiveVat,0,1));
			$b=db_escape_string(substr($effectiveVat,1));
#Hvis en momspligtig vare koebes i EU beregnes der EU moms. $kontrol er kun sat hvis der er tale om en kreditor
# og nedenst&aring;ende tr&aelig;der s&aring;ledes ikke i kraft naar der er tale om en finanskonto med EU moms.
			if ($a && ($a!='E' || $a!='Y') && (substr($kontrol,0,1)=='E' || substr($kontrol,0,1)=='Y')) {
				$a=substr($kontrol,0,1);
				$b=substr($kontrol,1.1);
			}
			$c=$a.'M';
			$qtxt = "select box1,box2,box3 from grupper where kode='$a' and kodenr='$b' and art='$c' and fiscal_year = '$regnaar'";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			if($r =	db_fetch_array($q)) { # Saa er der moms paa kontoen
				if (($a=='E' || $a=='Y') && (!$r['box1'] || !$r['box2'] || !$r['box3'])) alert("Fejl i kontoopsætning for EU moms");
				$qtxt="select box1,box2,box3 from grupper where kode='$a' and kodenr='$b' and art='$c' and fiscal_year = '$regnaar'";
				$q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				$x=$r['box2'];
				if ($a=='E' || $a=='Y'){
					$moms=$amount/100*$x;
					$momskto=trim($r['box3']);
					$modkto=trim($r['box1']);
				} elseif (substr($kontrol,0,1)=='E' || substr($kontrol,0,1)=='Y'){
					$momskto=trim($r['box1']);
					$modkto=trim($r['box1']);
					$moms=$amount/100*$x;
				} else {
					$momskto=trim($r['box1']);
					$moms=$amount-($amount/((100+$x)/100));
					$nettoamount=$amount-$moms;
				}
			}
		}
	}
# 2009.05.06 afrundingsdecimal rettet fra 3 til 2 grundet problem med Zen
	$amount=afrund($amount,2);
	$nettoamount=afrund($nettoamount,2);
	$moms=afrund($moms,2);
	if ($a!='E' && $a!='Y') { #20140428
		$tmp=afrund($amount-($nettoamount+$moms),2);
		# Nedenstaaende tilfojet 20090902 jvf saldi_2_20090902-1446.sdat
		if ($tmp>0) $moms=$moms+0.01;
		elseif ($tmp<0) $moms=$moms-0.01;
		$tmp=afrund($amount-($nettoamount+$moms),2);
		if (abs($tmp)>=0.01) { # 20140428 "fjernet $a!='E' && $a!='Y' &&"
			throw new KladdePostingError("Afvigelse ved momsberegning! Kontakt venligst Saldi teamet på telefon 4690 2208");
		}
	}
// #	$svar=array($amount,0,$momskto,$modkto);
	$svar=array($nettoamount,$moms,$momskto,$modkto);
	return $svar;
}
######################################################################################################################################
/** Resolve a debtor or creditor collective account.
 * @return array{0:mixed,1:string|null} */
function kladde_gruppeopslag($type, $konto) {

	global $connection, $regnaar;
	$konto = (int)$konto;
	$regnaar = (int)$regnaar;
	$art=NULL;$momsart=NULL;

	if ($type=='D') $art='DG';
	elseif ($type=='K') $art='KG';
	if ($art){
	$tmp=substr($art,0,1);
		$qtxt = "select gruppe from adresser where kontonr = '$konto' and art='$tmp'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($r['gruppe'])	{
			$qtxt = "select box1, box2 from grupper where art='$art' and kodenr='" . (int)$r['gruppe'] . "' and fiscal_year = '$regnaar'";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			if ($r =db_fetch_array($q)) {
				$konto=$r['box2'];
				$momsart=$r['box1'];
			} else alert("Fejl i kontoopsætning for konto $konto");
		} else alert("Konto $konto ikke tilknyttet en gruppe");
	}
	$svar=array($konto, $momsart);
	return $svar;
}
######################################################################################################################################
/** Resolve the dated exchange rate.
 * @return array{0:float,1:mixed,2:mixed} */
function kladde_valutaopslag($amount, $valuta, $transdate)
{
	global $connection;
	global $fejltext;

	$valuta = (int)$valuta;
	$transdate = db_escape_string((string)$transdate);
	$kurs = null;
	$r = db_fetch_array(db_select("select * from valuta where gruppe = '$valuta' and valdate <= '$transdate' order by valdate desc",__FILE__ . " linje " . __LINE__));
	if ($r['kurs']) {
		$kurs=$r['kurs'];
		$amount=afrund($amount*$kurs/100,2); # decimal rettet fra 3 til 2 20090617 grundet fejl i saldi_58_20090617-2224
	} else {
		$r = db_fetch_array(db_select("select box1 from grupper where art = 'VK' and kodenr = '$valuta'",__FILE__ . " linje " . __LINE__));
		$tmp=dkdato($transdate);
// 		$fejltext="---";
		print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs for $r[box1] den $tmp')\">";
	}
	$r = db_fetch_array(db_select("select box3 from grupper where art = 'VK' and kodenr = '$valuta'",__FILE__ . " linje " . __LINE__));
	$diffkonto=$r['box3'];

	return array($amount,$diffkonto,$kurs); # 3'die parameter tilfojet 2009.02.10
}
######################################################################################################################################

