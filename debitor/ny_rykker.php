<?php #topkode_start
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/ny_rykker.php --- ver 5.1.1 --- 2026.11.26 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
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
// 20261105 CL/LH Ny rykker refuses R2 until R1 has stood ffdage2 days and R3 until R2 has stood ffdage3 days, as the run for all debtors does.
//                An alert names the refused løbenr.
// 20261110 CDX/ASR Require module write access before this mutation endpoint runs.
// 20261022 CDX/LH Exclude captured/in-flight Stripe invoices from new reminders and existing reminder escalation.
// 20261126 CL/ASR Stripe port (#850): open posts of an invoice Stripe has collected or is collecting (stripe_dunning_openpost_sql()) get no new reminder.
//                 A reminder on such an invoice (stripe_dunning_reminder_sql()) is neither escalated in the run for all debtors nor in Ny rykker.
// 20261109 CL/ASR Renteloven § 9 b for every reminder: a fee line above 100,00 kr. is created at 100,00 kr. with a notice (L1), and a 2nd/3rd reminder with a fee is not created until 10 days after the previous one was sent (L3), also not booked or escalated by "Ryk alle" before then.
//                 R1 gets "Send pr. mail" when the customer has an e-mail address and the newest reminded invoice was e-mailed.
//                 Link reminders: stripe_reminder_register() after the lines of R1 and R2/R3 are complete; no comment line for a previous fee that went on the next invoice (there is no open item), and no interest on it; "Ryk alle" first closes booked link reminders whose invoices are paid.
//                 The new level's sum is written to the new reminder, not to the previous one.
//                 The notices and "Topniveau nået" are shown in one alert.
// 20261109 CL/ASR Review fixes: "Ryk alle" and "Ny rykker" do not book or escalate a reminder whose invoices are all paid (a notice says to use Afslut), and a new level lists only unpaid open items and charges interest on those only.
//                 "Ryk alle" does not book a reminder dated outside the active fiscal year (as "Bogfør").
//                 Reminder ids from the request are cast to integers.
// 20261111 CL/ASR Review round 2: "Ryk alle" still books a reminder whose invoices were paid after it was sent (its fee is owed), only does not escalate it; a link reminder whose fee goes on the next invoice is not held by the fiscal year.
// 20261126 CL/ASR Stripe port (#1098): renteloven § 9 b checks, link reminders and the next-invoice fee from the source above.
//                 This line's R2/R3 refusal (20261105) stays first and keeps its own alert; the § 9 b notices and "Topniveau nået" share the second alert.
// 20261116 CDX/ASR Iterate actual batch keys, validate debtor rows and use numeric group lookups before creating reminders.
// 20261126 CL/ASR Stripe port (#1134): the page refuses to run unless thisDb names the session's ledger (reminder_request_ledger_matches()).
//                 An unknown debtor is skipped with a notice, and the group and employee lookups take integers.
// 20261117 CDX/ASR Share transactional reminder creation with the scheduled flow.
// 20261126 CL/ASR Stripe port (#1147): the creation loop is reminder_create_batch() in includes/reminderCreate.php, run in one transaction that is rolled back on an error (the error is shown in the alert).
//                 This line's R2/R3 refusal (20261105) moved with the loop and comes back as the batch's 'not_due' list; its alert is unchanged.
// 20261126 ASR Review I1 (owner default Q8): "Ryk alle" books the previous levels inside the transaction, each chain under its own savepoint in reminder_create_batch().
//              A previous level is closed (betalt) only once its next level exists, and a chain or debtor that fails is skipped with a notice while the others are created, as before the shared transaction.
// 20261126 ASR Review I3: "Ryk alle" leaves out automatic reminders that were not sent (reminder_automation_unsent_sql()), so their fee is not booked and they are not escalated.
// 20261126 ASR Review M1: the not-due notice, the § 9 b notices and "Topniveau nået" are one message, and the popup closes only after it is read (formReminderAlertThenGo()).
//              Saldi's message box does not block like alert(), so the meta refresh right after it closed the popup first.
// 20261126 ASR Review GPT-F-1: "Ryk alle" also books and escalates a reminder left at status 3 without anything posted (reminder_unposted_sql()), as it is not booked.
// 20261126 ASR Review AR2-2: the notice for an automatic reminder that was not sent is worded by its job's state (reminder_skip_notice() with the reminder's id).
// 20261126 ASR Review AR2-1: "Ryk alle" deletes an open reminder of a customer without open items only when it never went out (reminder_went_out()).
//              One that was mailed, printed or sent by the automatic flow, or whose sending is unresolved, stays, and a notice names it (8232); the same run neither books nor escalates it.
//              A deleted draft takes its draft link reminder row and its automatic flow's job with it (reminder_delete_draft()), so the flow's page lists no job for a reminder that is gone.
// 20261126 ASR Review R3E2E-1: "Ryk alle" keeps only a reminder that the automatic flow or a link reminder tracks as sent, or whose sending is unresolved (reminder_sending_tracked()).
//              A classic reminder that was only printed or mailed is deleted as before the update, with its reminder_dispatch row, as whether a delivered reminder is kept is owner question Q26.
// --------------------- Bekrivelse ------------------------
// Ved generering af en rykker oprettes en ordre med art = R1. Hver ordre der indgår i rykkeren oprettes som en ordrelinje
// hvor feltet enhed indeholder id fra openpost tabellen og serienr indeholder forfaldsdatoen,.Beskrivelse indeholde beskrivelse.
// Da varenrfelt er tomt vil linjerne blive opfattes som kommentarlinjer ved bogføring
// Rykkergebyr tilføjes som en ordinær ordrelinje.med varenummer for rykkergebyr, og vil derfor blive behandlet som den eneste reelle ordrelinje
// ved bogføring.
// Ved generering af rykker "2" medtages evt gebyr fra rykker 1 på samme måden som v. ovenstående. 
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
@session_start();
$s_id=session_id();

$topniveau=NULL;
$fristIkkeUdloebet=array(); # løbenr of reminders whose next level is not due yet

include(__DIR__ . '/../includes/connect.php');
$permission_key = 'debitor.konti';
$permission_level = 'write';
include(__DIR__ . '/../includes/online.php');
include(__DIR__ . '/../includes/std_func.php');
include_once("../includes/stdFunc/dunningCutoff.php");
include(__DIR__ . '/../includes/formfunk.php');
include(__DIR__ . '/../includes/forfaldsdag.php');
include(__DIR__ . '/../includes/openpost.php');
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
$rykkerfrist2=dunning_cutoff_date($ffdage2);
$rykkerfrist3=dunning_cutoff_date($ffdage3);

$r = db_fetch_array(db_select("select id from formularer where beskrivelse LIKE '%inkassotekst'",__FILE__ . " linje " . __LINE__));
if (!empty($r['id'])) {
	$inkassotekst='';
}

$rykAlle = ($konto_id[0]=="alle"); # 20261126 ASR Review I1: "Ryk alle" books the previous levels in reminder_create_batch()
if ($rykAlle) {
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
	// 20261126 ASR Review AR2-1, R3E2E-1: an open reminder of a customer without open items is deleted, as before the update, unless
	// the automatic flow or a link reminder tracks it as sent or its sending as unresolved (reminder_sending_tracked()). Such a one stays
	// with a notice, and this run neither books nor escalates it ($keptReminder). Whether a delivered reminder is kept, and its fee booked
	// or waived, is owner question Q26, so a classic reminder that was only printed or mailed is still deleted.
	// The deleted one goes with its reminder_dispatch row, its draft link reminder row and its automatic flow's job (reminder_delete_draft()), one transaction each.
	$keptReminder = array();
	$deleteDraftOrKeep = function (array $reminder) use (&$legalNotes, &$keptReminder, $brugernavn): void {
		if (reminder_sending_tracked((int)$reminder['id'])) {
			$keptReminder[(int)$reminder['id']] = true;
			$legalNotes[] = reminder_txt(8232, 'Rykker {rykker} er ikke slettet, selvom kunden ikke har flere åbne poster, da den er sendt til kunden eller dens afsendelse er uafklaret. Behandl den manuelt.',
				array('rykker' => trim((string)$reminder['fakturanr'])));
			return;
		}
		transaktion('begin');
		reminder_delete_draft((int)$reminder['id'], (string)$brugernavn, 'Ryk alle: kunden har ingen åbne poster.');
		transaktion('commit');
	};
	for ($x=1;$x<=$konto_antal;$x++) { # udligner alle åbne poster og sletter tilhørende åbne rykkere hvor udestaaende er 0;
		if ($udlign[$x]) {
			$udlign_id++;
			db_modify("update openpost set udlignet = '1', udlign_id = '$udlign_id' where konto_id='$konto_id[$x]' and udlignet = '0'",__FILE__ . " linje " . __LINE__);
			$q=db_select("select id, fakturanr from ordrer where konto_id='$konto_id[$x]' and art LIKE 'R%' and status < '3' and betalt != 'on'",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) $deleteDraftOrKeep($r);
		}
	}
	# finder resterende aabne rykkere som ikke er i listen over konto over aabne poster og sletter disse.
	$q=db_select("select id, konto_id, fakturanr from ordrer where art LIKE 'R%' and status < '3' and betalt != 'on'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (!in_array($r['konto_id'],$konto_id)) $deleteDraftOrKeep($r);
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
	$bookOnly=array(); # 20261126 ASR Review I1: id => løbenr of reminders whose fee is booked without a next level, in the transaction below
	$unsentReminder = reminder_automation_unsent_sql(); # 20261126 ASR Review I3: an automatic reminder that was not sent is the automatic flow's
	$unpostedReminder = reminder_unposted_sql('ordrer'); # 20261126 ASR Review GPT-F-1: left at status 3 by a booking that stopped before it posted
	#finder aabne level_1 rykkere som har overskredet rykkerdato med ffdage2.og bogforer disse
	$q=db_select("select id,konto_id,fakturanr from ordrer where art = 'R1' and (status < '3' or ($unpostedReminder and betalt != 'on')) and ordredate < '$rykkerfrist2' and $stripeDunningReminder and $unsentReminder",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (isset($keptReminder[(int)$r['id']])) continue; # 20261126 ASR Review AR2-1: kept above, its customer has no open items
		// 20261109 CL/ASR not escalated: a reminder dated outside the active fiscal year (not booked either, as "Bogfør"), one whose
		// invoices are all paid and renteloven § 9 b (L3) before the next reminder may carry its fee
		if (($skip = reminder_escalation_skip((int)$r['id'], true)) !== '') {
			// 20261111 CL/ASR its invoices paid after it was sent: still booked, as its fee is owed (Afslut closes it)
			if ($skip === 'paid') $bookOnly[(int)$r['id']] = $r['fakturanr'];
			$legalNotes[] = reminder_skip_notice($r['fakturanr'], $skip, (int)$r['id']); // 20261126 ASR Review AR2-2
			continue;
		}
		$x++;
		$rykker_id[$x]=$r['id']; # 20261126 ASR booked, escalated and closed together by reminder_create_batch()
	}
	#finder aabne level_2 rykkere som har overskredet rykkerdato med ffdage3.og bogforer disse
	$q=db_select("select id,konto_id,fakturanr from ordrer where art = 'R2' and (status < '3' or $unpostedReminder) and ordredate < '$rykkerfrist3' and betalt != 'on' and $stripeDunningReminder and $unsentReminder",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if (isset($keptReminder[(int)$r['id']])) continue; # 20261126 ASR Review AR2-1: as for R1
		// 20261109 CL/ASR as for R1 above
		if (($skip = reminder_escalation_skip((int)$r['id'], true)) !== '') {
			if ($skip === 'paid') $bookOnly[(int)$r['id']] = $r['fakturanr'];
			$legalNotes[] = reminder_skip_notice($r['fakturanr'], $skip, (int)$r['id']); // 20261126 ASR Review AR2-2
			continue;
		}
		$x++;
		$rykker_id[$x]=$r['id'];
	}
	$konto_antal=$x;
} else {
	$autoryk=0;
	$rykkerfrist1=$rykkerdate;
}
transaktion('begin');
try {
    // 20261126 ASR Review I1: a paid reminder's owed fee is booked in the transaction too, each under its own savepoint
    if (!empty($bookOnly)) {
        reminder_create_lock();
        foreach ($bookOnly as $bookId => $bookNr) {
            $failure = reminder_create_isolated(function () use ($bookId) { reminder_create_book((int)$bookId); });
            if ($failure !== '') $legalNotes[] = reminder_txt(8108, 'Rykker {rykker}: {tekst}', array('rykker' => $bookNr, 'tekst' => $failure));
        }
    }
    $created = reminder_create_batch($konto_id, $rykker_id, $rykkerdate, $rykkerfrist1, (string)$brugernavn, $rykAlle);
    if (!empty($GLOBALS['db_modify_fejl'])) {
        throw new RuntimeException('Rykkeren kunne ikke oprettes.');
    }
    transaktion('commit');
    $legalNotes = array_merge($legalNotes, $created['notes']);
    $fristIkkeUdloebet = $created['not_due']; # 20261126 CL/ASR Stripe port (#1147): this line's R2/R3 refusal (20261105), now checked in reminder_create_batch()
} catch (Throwable $e) {
    transaktion('rollback');
    $legalNotes[] = $e->getMessage();
}
#	xit;
$messages = array(); # 20261126 ASR Review M1: one message, read before the popup closes
if ($fristIkkeUdloebet) {
	$tekst = findtekst('reminder.next_level_not_due|Der er ikke oprettet en ny rykker for løbenr. %s, fordi betalingsfristen for den forrige rykker ikke er overskredet.', $sprog_id);
	$messages[] = sprintf($tekst, implode(', ', $fristIkkeUdloebet));
}
if ($topniveau) $legalNotes[] = reminder_txt(8110, 'Topniveau nået for rykkere med løbenr {loebenr}', array('loebenr' => $topniveau));
print formReminderAlertThenGo(array_merge($messages, $legalNotes), '../includes/luk.php');
