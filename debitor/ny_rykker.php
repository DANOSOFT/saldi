<?php #topkode_start
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/ny_rykker.php --- ver 5.0.0 --- 2026.10.03 ---
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
// Copyright (c) 2003 - 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20121105 Fejl ved renteberegning af posteringer uden forfaldadato. Søg 20121105 
// 20140628 Valuta og valutakurs indsættes nu ved oprettelse af ny rykker. Søg 20140628 
// 20140707 Hvis ingen valuta sættes til DKK, hvis ingen kurs sættes til sættes til 100. Søg 20140707 
// 20141106 Indsat db_escape_string foran div variabler hvor de indsættes i tabeller.
// 20170303 PHR Tilføjet felt_5 til insert into ordrer aht inkasso. 
// 20210422 PHR currency now included in reminder  
// 20210507 PHR Added dec limit 1 to query as it took the oldest currency and not the newest. 2021057 
// 20220818 PHR Added db_escape_string at line ~247 
// 20250731 PHR PHP8
// 20260723 sawaneh Removed misplaced ';' after date('U') so ffdage2/3 are subtracted again; R2/R3 fees are booked only after grace days.
// 20260803 Sawaneh $rykkerfrist1 blev beregnet ud fra $dd foer den var sat, saa ffdage1 blev kasseret og der blev rykket
//                  dagen efter forfald. Fristen regnes nu som dags dato minus ffdage1, som ved ffdage2/3.
// 20260828 Sawaneh Review: the three cutoffs use calendar-day arithmetic (dunning_cutoff_date) instead of
//                  date('U') - days*86400, which shifted eligibility by a day around the DST switches.
// 20261001 CDX/LH Share transaction-owned dunning engine with mobile; allocate alle settlement IDs safely.
// --------------------- Bekrivelse ------------------------
// Ved generering af en rykker oprettes en ordre med art = R1. Hver ordre der indgår i rykkeren oprettes som en ordrelinje
// hvor feltet enhed indeholder id fra openpost tabellen og serienr indeholder forfaldsdatoen,.Beskrivelse indeholde beskrivelse.
// Da varenrfelt er tomt vil linjerne blive opfattes som kommentarlinjer ved bogføring
// Rykkergebyr tilføjes som en ordinær ordrelinje.med varenummer for rykkergebyr, og vil derfor blive behandlet som den eneste reelle ordrelinje
// ved bogføring.
// Ved generering af rykker "2" medtages evt gebyr fra rykker 1 på samme måden som v. ovenstående.
/**
 * Injected by ../includes/online.php and db_query.php through connect.php, included below:
 * @var string $brugernavn
 * @var bool $db_modify_fejl
 */
		
@session_start();
$s_id=session_id();

$topniveau=NULL;

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/../includes/stdFunc/dunningCutoff.php");
include(__DIR__ . "/../includes/formfunk.php");
include(__DIR__ . "/../includes/forfaldsdag.php");
include(__DIR__ . "/../includes/openpost.php");
require_once __DIR__ . "/../includes/dunning/dunningService.php";
require_once __DIR__ . "/../includes/openpost/udlignId.php";

$konto_antal=(int)ifset($_GET, 'kontoantal', 0);
if (isset($_GET['rykker_id'])) $rykker_id=explode(";", $_GET['rykker_id']);
else $rykker_id=NULL;
$konto_id =explode(";", (string)ifset($_GET, 'kontoliste', ''));

$rykkerdate=date("Y-m-d");

$rentesats_1=0;
$rentesats_2=0;
$rentesats_3=0;
$q=db_select("select formular, str from formularer where beskrivelse='GEBYR' and formular>='6' order by formular",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	if ($r['formular']==6) $rentesats_1=$r['str'];
	elseif ($r['formular']==7) $rentesats_2=$r['str'];
	elseif ($r['formular']==8) $rentesats_3=$r['str'];
}
$r = db_fetch_array(db_select("select box5,box6,box7,box8 from grupper where art='DIV' and kodenr= '4'",__FILE__ . " linje " . __LINE__));
$ffdage1=(int)$r['box5'];
$ffdage2=(int)$r['box6'];
$ffdage3=(int)$r['box7'];
$rykkerfrist1=dunning_cutoff_date($ffdage1);

$r = db_fetch_array(db_select("select id from formularer where beskrivelse LIKE '%inkassotekst'",__FILE__ . " linje " . __LINE__));
if ($r['id']) {
	$inkassotekst='';
}

if ($konto_id[0]=="alle") { 
	$dd=date("Y-m-d");
	$x=0;
	$konto_id=array();
	$q=db_select("select * from openpost where udlignet = '0' order by konto_id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (!in_array($r['konto_id'],$konto_id)) {
			if ($x && !$kontosum[$x]) $udlign[$x]=1;
			$x++;
			$konto_id[$x]=$r['konto_id'];	
			$kontosum[$x]=$r['amount'];
			$udlign[$x]=0;
		} else
		$kontosum[$x]=$kontosum[$x]+$r['amount'];
	}
	$konto_antal=$x;
	for ($x=1;$x<=$konto_antal;$x++) { # udligner alle åbne poster og sletter tilhørende åbne rykkere hvor udestaaende er 0;
		if ($udlign[$x]) {
			$udlign_id=udlign_next_id();
			db_modify("update openpost set udlignet = '1', udlign_id = '$udlign_id' where konto_id='$konto_id[$x]' and udlignet = '0'",__FILE__ . " linje " . __LINE__);
			$q=db_select("select id from ordrer where konto_id='$konto_id[$x]' and art LIKE 'R%' and status < '3' and betalt != 'on'",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				db_modify("delete from ordrelinjer where ordre_id='$r[id]'",__FILE__ . " linje " . __LINE__); 
				db_modify("delete from ordrer where id='$r[id]'",__FILE__ . " linje " . __LINE__); 
			}	
		}
	}
	# finder resterende aabne rykkere som ikke er i listen over konto over aabne poster og sletter disse.
	$q=db_select("select id, konto_id from ordrer where art LIKE 'R%' and status < '3' and betalt != 'on'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (!in_array($r['konto_id'],$konto_id)) {
			db_modify("delete from ordrelinjer where ordre_id='$r[id]'",__FILE__ . " linje " . __LINE__); 
			db_modify("delete from ordrer where id='$r[id]'",__FILE__ . " linje " . __LINE__); 
		}
	}
	$konto_id=array();
	$x=0;
	$q=db_select("select openpost.* from openpost,adresser where openpost.udlignet = '0' and openpost.forfaldsdate<='$rykkerfrist1' and openpost.amount>'0' and adresser.id=openpost.konto_id and adresser.art = 'D' order by openpost.konto_id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (!db_fetch_array(db_select("select id from ordrelinjer where enhed = '$r[id]'",__FILE__ . " linje " . __LINE__))) { #Tjekker om der allerede eksisterer en rykker på ordren.
			if (!in_array($r['konto_id'],$konto_id)) {
				$konto_id[$x]=$r['konto_id']; #Liste over konto id numre der skal rykkes
				$x++;
			} 
		}
	}
	$konto_antal=$x;
	$rykker_id=array();
	$rykkerfrist2=dunning_cutoff_date($ffdage2);
	#finder aabne level_1 rykkere som har overskredet rykkerdato med ffdage2.og bogforer disse
	$q=db_select("select id,konto_id from ordrer where art = 'R1' and status < '3' and ordredate < '$rykkerfrist2'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$x++;
		db_modify("update ordrer set betalt = 'on' where id = '$r[id]'",__FILE__ . " linje " . __LINE__); 
		bogfor_rykker($r['id']);
		$rykker_id[$x]=$r['id'];
	}
	$rykkerfrist3=dunning_cutoff_date($ffdage3);
	#finder aabne level_2 rykkere som har overskredet rykkerdato med ffdage3.og bogforer disse
	$q=db_select("select id,konto_id from ordrer where art = 'R2' and status < '3' and ordredate < '$rykkerfrist3' and betalt != 'on'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$x++;
		db_modify("update ordrer set betalt = 'on' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
		bogfor_rykker($r['id']);
		$rykker_id[$x]=$r['id'];
	}
	$konto_antal=$x;
} else {
	$autoryk=0;
	$rykkerfrist1=$rykkerdate;
}
$legacyPageSum = 0;
for ($i=0; $i<=$konto_antal; $i++) {
    $account = (int)ifset($konto_id, $i, 0);
    $base = (int)ifset($rykker_id, $i, 0);
    if (!$account && !$base) {
        continue;
    }
    if (!$base) {
        $legacyPageSum = 0;
    }
    transaktion('begin');
    try {
        $result = $base ? dunning_advance($base, $rykkerdate, $legacyPageSum) : dunning_create_for_account($account, $rykkerdate, $rykkerfrist1);
        if ($result['skipped'] !== null || $db_modify_fejl) {
            transaktion('rollback');
            if ($result['skipped'] === 'top_level_reached') {
                $r = db_fetch_array(db_select("SELECT ordrenr FROM ordrer WHERE id=$base", __FILE__ . " linje " . __LINE__));
                $topniveau = ($topniveau ? $topniveau . ', ' : '') . $r['ordrenr'];
            }
        } else {
            transaktion('commit');
            $legacyPageSum = (float)ifset($result, 'legacy_sum', $legacyPageSum);
            if ($base) {
                $ny_rykker_id[$i] = $result['rykker_id'];
            } else {
                $rykker_id[$i] = $result['rykker_id'];
            }
        }
    } catch (Throwable $error) {
        transaktion('rollback');
        error_log('Dunning failed: ' . get_class($error));
    }
}
#	xit;
if ($topniveau) print "<BODY onLoad=\"javascript:alert('Topniveau nået for rykkere med l&oslash;benr $topniveau')\">";
print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
