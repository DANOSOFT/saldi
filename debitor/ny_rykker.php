<?php #topkode_start
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/ny_rykker.php --- ver 5.0.0 --- 2026.11.17 ---
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
// 20260916 Sawaneh Declared $permission_key (roles & permissions, phase 3)
// 20261022 CDX/LH Exclude captured/in-flight Stripe invoices from new reminders and existing reminder escalation.
// 20261023 CDX/LH Retain the redesign permission key with the fixed Stripe reminder exclusions.
// 20261109 CL/ASR Renteloven § 9 b for every reminder: a fee line above 100,00 kr. is created at 100,00 kr. with a notice (L1), and a 2nd/3rd reminder with a fee is not created until 10 days after the previous one was sent (L3), also not booked or escalated by "Ryk alle" before then.
//                 R1 gets "Send pr. mail" when the customer has an e-mail address and the newest reminded invoice was e-mailed.
//                 Link reminders: stripe_reminder_register() after the lines of R1 and R2/R3 are complete; no comment line for a previous fee that went on the next invoice (there is no open item), and no interest on it; "Ryk alle" first closes booked link reminders whose invoices are paid.
//                 The new level's sum is written to the new reminder, not to the previous one.
//                 The notices and "Topniveau nået" are shown in one alert.
// 20261109 CL/ASR Review fixes: "Ryk alle" and "Ny rykker" do not book or escalate a reminder whose invoices are all paid (a notice says to use Afslut), and a new level lists only unpaid open items and charges interest on those only.
//                 "Ryk alle" does not book a reminder dated outside the active fiscal year (as "Bogfør").
//                 Reminder ids from the request are cast to integers.
// 20261111 CL/ASR Review round 2: "Ryk alle" still books a reminder whose invoices were paid after it was sent (its fee is owed), only does not escalate it; a link reminder whose fee goes on the next invoice is not held by the fiscal year.
// 20261116 CDX/ASR Iterate actual batch keys, validate debtor rows and use numeric group lookups before creating reminders.
// 20261117 CDX/ASR Share transactional reminder creation with the scheduled flow.
// --------------------- Bekrivelse ------------------------
// Ved generering af en rykker oprettes en ordre med art = R1. Hver ordre der indgår i rykkeren oprettes som en ordrelinje
// hvor feltet enhed indeholder id fra openpost tabellen og serienr indeholder forfaldsdatoen,.Beskrivelse indeholde beskrivelse.
// Da varenrfelt er tomt vil linjerne blive opfattes som kommentarlinjer ved bogføring
// Rykkergebyr tilføjes som en ordinær ordrelinje.med varenummer for rykkergebyr, og vil derfor blive behandlet som den eneste reelle ordrelinje
// ved bogføring.
// Ved generering af rykker "2" medtages evt gebyr fra rykker 1 på samme måden som v. ovenstående. 
		
@session_start();
$s_id=session_id();

$topniveau=NULL;

include("../includes/connect.php");
$permission_key = 'debitor.konti';
include("../includes/online.php");
include("../includes/std_func.php");
include_once("../includes/stdFunc/dunningCutoff.php");
include("../includes/formfunk.php");
include("../includes/forfaldsdag.php");
include("../includes/openpost.php");
include_once(__DIR__ . '/../includes/stripeIncludes/stripeDunning.php');
$stripeDunningOpen = stripe_dunning_openpost_sql();
$stripeDunningReminder = stripe_dunning_reminder_sql();
include_once(__DIR__ . '/../includes/reminderLegal.php');
require_once __DIR__ . '/../includes/reminderCreate.php';
if (!reminder_request_ledger_matches($_GET['thisDb'] ?? null, (string)$db)) {
	alert(reminder_txt(8181, 'Regnskabet er skiftet, eller rapporten er for gammel. Åbn rapporten igen i det ønskede regnskab, før du opretter rykkere.'));
	exit;
}
// 20261109 CL/ASR link reminders; without the file every reminder is classic
if (is_file(__DIR__ . '/../includes/stripeIncludes/stripeReminder.php')) include_once(__DIR__ . '/../includes/stripeIncludes/stripeReminder.php');
$legalNotes = array(); # staff notices (renteloven § 9 b), shown in one alert at the end

$konto_antal = $_GET['kontoantal'] ?? 0;
if (isset($_GET['rykker_id'])) $rykker_id=array_map('intval', explode(";", $_GET['rykker_id'])); # 20261109 CL/ASR integers
else $rykker_id=array();
$konto_id = explode(";", (string)($_GET['kontoliste'] ?? ''));

$rykkerdate=date("Y-m-d");
$tidspkt = date("H:i:s");

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
if (!empty($r['id'])) {
	$inkassotekst='';
}

if ($konto_id[0]=="alle") { 
	// 20261109 CL/ASR booked link reminders whose invoices are all paid are closed first, like "Afslut"
	if (function_exists('stripe_reminder_close_paid')) stripe_reminder_close_paid();
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
	$r=db_fetch_array(db_select("select max(udlign_id) as udlign_id from openpost",__FILE__ . " linje " . __LINE__));
	$udlign_id=$r['udlign_id'];
	for ($x=1;$x<=$konto_antal;$x++) { # udligner alle åbne poster og sletter tilhørende åbne rykkere hvor udestaaende er 0;
		if ($udlign[$x]) {
			$udlign_id++;
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
	$q=db_select("select openpost.* from openpost,adresser where openpost.udlignet = '0' and openpost.forfaldsdate<='$rykkerfrist1' and openpost.amount>'0' and adresser.id=openpost.konto_id and adresser.art = 'D' and $stripeDunningOpen order by openpost.konto_id",__FILE__ . " linje " . __LINE__);
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
	$q=db_select("select id,konto_id,fakturanr from ordrer where art = 'R1' and status < '3' and ordredate < '$rykkerfrist2' and $stripeDunningReminder",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		// 20261109 CL/ASR not escalated: a reminder dated outside the active fiscal year (not booked either, as "Bogfør"), one whose
		// invoices are all paid and renteloven § 9 b (L3) before the next reminder may carry its fee
		if (($skip = reminder_escalation_skip((int)$r['id'], true)) !== '') {
			// 20261111 CL/ASR its invoices paid after it was sent: still booked, as its fee is owed (Afslut closes it)
			if ($skip === 'paid') bogfor_rykker($r['id']);
			$legalNotes[] = reminder_skip_notice($r['fakturanr'], $skip);
			continue;
		}
		$x++;
		db_modify("update ordrer set betalt = 'on' where id = '$r[id]'",__FILE__ . " linje " . __LINE__); 
		bogfor_rykker($r['id']);
		$rykker_id[$x]=$r['id'];
	}
	$rykkerfrist3=dunning_cutoff_date($ffdage3);
	#finder aabne level_2 rykkere som har overskredet rykkerdato med ffdage3.og bogforer disse
	$q=db_select("select id,konto_id,fakturanr from ordrer where art = 'R2' and status < '3' and ordredate < '$rykkerfrist3' and betalt != 'on' and $stripeDunningReminder",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		// 20261109 CL/ASR as for R1 above
		if (($skip = reminder_escalation_skip((int)$r['id'], true)) !== '') {
			if ($skip === 'paid') bogfor_rykker($r['id']);
			$legalNotes[] = reminder_skip_notice($r['fakturanr'], $skip);
			continue;
		}
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
transaktion('begin');
try {
    $created = reminder_create_batch($konto_id, $rykker_id, $rykkerdate, $rykkerfrist1, (string)$brugernavn);
    if (!empty($GLOBALS['db_modify_fejl'])) {
        throw new RuntimeException('Rykkeren kunne ikke oprettes.');
    }
    transaktion('commit');
    $legalNotes = array_merge($legalNotes, $created['notes']);
} catch (Throwable $e) {
    transaktion('rollback');
    $legalNotes[] = $e->getMessage();
}
#	xit;
if ($topniveau) $legalNotes[] = reminder_txt(8110, 'Topniveau nået for rykkere med løbenr {loebenr}', array('loebenr' => $topniveau));
if ($legalNotes) {
	/**
	 * jsString() comes from includes/stdFunc/jsString.php, which std_func.php includes.
	 *
	 * @see jsString()
	 */
	print "<BODY onLoad=\"javascript:alert(" . htmlspecialchars(jsString(array(implode("\n", $legalNotes))), ENT_QUOTES, 'UTF-8') . ")\">";
}
print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
