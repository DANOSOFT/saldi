<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------------finans/bogfor.php---------- ver 5.1.1 --- 2026-11-20 ---
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
// 20260930 CL/SZ SD-699: Moved get_saved_vat_override(), momsberegning() and gruppeopslag() to
//                  bogfor_includes/postingRules.php, shared with the "Kontokort med u-bogført" report.
//                  bogfor() now gets each line's accounts, VAT and DKK amount from postingLineAmounts().
// 20261019 CL/LH MB-71: Simulation and posting are refused, naming the vouchers, while a saved row has text, amount or invoice number but no debit or credit account (unfinishedJournalVouchers()).
//                Such rows are no longer deleted silently; only rows without any content are still dropped.
// 20261023 CL/LH MB-71: The refusal alert text goes through jsString(), so it is no longer empty when $db_encode isn't UTF8.
// 20261019 CL/LH MB-72: openpost() treats an empty, '0' and NULL project as the same, so a journal payment settles an invoice without a project.
//                The settlement date is the later of invoice and payment date, not the invoice date.
// 20261023 CL/LH MB-72: openpost() trims the project before matching and storing it, and a payment settling
//                several invoices gives every row of the group the group's latest date.
// 20261028 CL/LH MB-72: openpost() settles a payment naming several invoices ("A;B") only when they and the payment
//                net to zero, and writes the payment row once instead of once per settled invoice.
// 20261102 CL/ASR The old "+1000 postings for kr. 30,-" offer is not shown to accounts with a package from the self-service signup.
// 20261110 CDX/ASR Require module write access before this mutation endpoint runs.
// 20261117 CL/ASR bogfor() locks the journal row (select ... for update) in the posting transaction and refuses when bogfort is already 'V'.
//                 Two concurrent Bogfør requests from different sessions posted the same journal twice.
// 20261117 CL/ASR The preview converts currency lines with journalLineInBaseCurrency() (bogfor_includes/postingRules.php), as bogfor() does: a reversal written by Tilbagefør at the rate its original was posted at.
//                 bogfor() puts the rate it posts a currency line at on the line (recordPostedLineRate()), so a later Tilbagefør of that line finds its own rate.
// 20261119 CL/ASR A journal posted into a fiscal year that has a following year takes the opening-balance carry's lock before its transaction and releases it after the commit or rollback, so on MySQL and MariaDB its carry no longer writes over a carry another session committed after the journal's first read (p1-yearend-F4).
// 20261119 CL/ASR A journal refused because a foreign-currency invoice moved from Hent ordrer no longer balances in DKK (its date's rate was changed after the move) now names the voucher, the invoice and the rates and says to delete the voucher's lines and move the invoice again (movedInvoiceRateMessage()), instead of "Manglende konto til valutadiffencer!" or an unbalanced journal. The Simulér and Bogfør preview shows the same message under the vouchers with differences, where such a journal stops.
// 20261120 CL/ASR A posted journal keeps the opening-balance carry's lock until the page has recalculated the year after the commit and again where it shows the posted journal, so a POS day close waiting for the lock no longer starts in between and fails on its posting into that year with the database error box and a mail (p1-pos-close-N1).
//                 A voucher with an invoice moved from Hent ordrer that does not balance in DKK on its own is refused by Simulér and Bogfør with movedInvoiceRateMessage(), and its preview leaves out the button, also when another such voucher makes up for it in the journal's total (p1-journal-J3h).


require_once dirname(__DIR__, 1) . '/includes/assist/RecordRules.php';

@session_start();
$s_id=session_id();

$fejltext=NULL;$valutadiff=0;
$tjeksum=array();
$css="../css/standard.css";

$modulnr=2;
$title="Bogfør kassekladde";

include(__DIR__ . '/../includes/connect.php');
$permission_key = 'finans.kassekladde';
$permission_level = 'write';
include(__DIR__ . '/../includes/online.php');
include(__DIR__ . '/../includes/std_func.php');
include(__DIR__ . '/../includes/genberegn.php');
include(__DIR__ . '/../includes/topline_settings.php');

genberegn($regnaar);

include_once(__DIR__ . "/bogfor_includes/postingRules.php");

$funktion=if_isset($_GET['funktion']);
$kladde_id=if_isset($_GET['kladde_id']);
if (($_POST) && ($_POST['kladde_id'])) $kladde_id = $_POST['kladde_id'];

if (!isset ($onclick)) $onclick = NULL;

#if ($popup) $returside="../includes/luk.php";
#else $returside="kassekladde.php?kladde_id=$kladde_id";
$returside="kassekladde.php?kladde_id=$kladde_id";

$r =db_fetch_array(db_select("select * from grupper where kodenr = '$regnaar' and art = 'RA'",__FILE__ . " linje " . __LINE__));
$regnstart=$r['box2']."-".$r['box1']."-01";
$rsaar=$r['box4'];
$rsmd=$r['box3'];
$rsdd=31;
while (!checkdate($rsmd,$rsdd,$rsaar)) {
	$regnslut=$rsaar."-".$rsmd."-".$rsdd;
	$rsdd--;
	if ($rsdd<28) break 1;
}
$regnslut=$rsaar."-".$rsmd."-".$rsdd;

$kladdenote_vis='';
if ($kladde_id) {
	$row =db_fetch_array(db_select("select bogfort, kladdenote from kladdeliste where id = $kladde_id",__FILE__ . " linje " . __LINE__));
	if ($row['bogfort']=='V') {
		print "<BODY onLoad=\"javascript:alert('Kladden er allerede bogf&oslash;rt - kladden lukkes')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
		exit;
	}
	$kladdenote_vis = htmlentities(stripslashes(trim((string)$row['kladdenote'])), ENT_QUOTES, $charset);
	// A saved row with text, amount or invoice number but no account must be completed or deleted by the
	// user; neither the preview (Simuler/confirm) nor posting may start, and nothing is deleted.
	$unfinishedVouchers = unfinishedJournalVouchers($kladde_id);
	if ($unfinishedVouchers) {
		print "<script>alert(" . jsString(array(unfinishedJournalMessage($unfinishedVouchers))) . ");</script>";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
		exit;
	}
}
if ($funktion=='bogfor') {
	$overskrift = findtekst('2209|Bogfør kassekladde', $sprog_id).", ".findtekst('1087|Kladde', $sprog_id)." $kladde_id";
	$href="<a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>";
} elseif ($funktion=='simuler') {
	$overskrift="".findtekst(1085,$sprog_id)." ".findtekst(1086,$sprog_id).", ".findtekst(1087,$sprog_id)." $kladde_id"; #20210319
	$href="<a href=$returside accesskey=L>";
} else $href="<a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>";
if ($menu=='T') {
	print "";
} else {
	print "<center><table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
}
if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
} elseif ($menu=='S') {
	
	 ############################
     $icon_back = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';

    ##########################
	
	print "<tr><td align='center' valign='top' height='1%'>\n";
	print "<table width='100%' align='center' border='0' cellspacing='4' cellpadding='0'><tbody>\n";
	print "<td width='10%'>$href<button class='center-btn' style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".$icon_back  . findtekst('30|Tilbage', $sprog_id) . "</button></a></td>\n";

	print "<td width='80%' align=center style='$topStyle'>$overskrift</td>\n";

	print "<td width='10%' align=center style='$buttonStyle;'>
		   <br></td>\n";

	print "</tbody></table>\n"; 
	print "</td></tr>\n";
	print "<tr><td width=\"100%\" valign=\"top\">";
    ####
    print "</td></tr>\n";
    #####

    ?>
    <style>
    .headerbtn, .center-btn {
		display: flex;
		align-items: center;
		text-decoration: none;
		gap: 5px;

	}
	a {
		text-decoration: none;
		}

    </style>
    <?php
} else {
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$href ".findtekst(30,$sprog_id)."</a></td>";
print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$overskrift</td>";
print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"></a></td>";
print "</tbody></table>";
print "</td></tr>";
}

if (!isset ($_POST['bogfor'])) $_POST['bogfor'] = 0;
if (!isset ($_POST['simuler'])) $_POST['simuler'] = 0;
if (!isset ($_POST['luk'])) $_POST['luk'] = 0;
if (!isset ($_POST['kladdenote'])) $_POST['kladdenote'] = NULL;

if ($_POST['bogfor'] || $_POST['simuler']) {
	$bogfor = trim($_POST['bogfor']);
	$simuler = trim($_POST['simuler']);
	$opdater = if_isset($_POST['opdater']);
	$bestil = if_isset($_POST['bestil']);
	$stop = if_isset($_POST['stop']);
	$kladde_id = $_POST['kladde_id'];
	$kladdenote = db_escape_string(trim($_POST['kladdenote']));
	if ($opdater=='1000') {
		include("../includes/connect.php");
		db_modify("update regnskab set posteringer = posteringer+1000 where db='$db'",__FILE__ . " linje " . __LINE__);
		$headers = 'From: saldi@saldi.dk'."\r\n".'Reply-To: saldi@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
		mail("saldi@saldi.dk", "SALDI Opgradering af $regnskab / $db", "$brugernavn har bestilt 1000 posteringer til regnskab $db", "$headers");
		include("../includes/online.php");
	} elseif ($stop) {
		print "<td align=\"center\">
			<iframe style=\"width:800px;height:100%\"
			src=\"../doc/gratis2proff.php\"
			frameborder=\"1\"></iframe>
		</td>";
		$bogfor=NULL;
		exit;
		#		include("../includes/connect.php");
#		$headers = 'From: saldi@saldi.dk'."\r\n".'Reply-To: saldi@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
#		mail("saldi@saldi.dk", "SALDI bestilling af professionelt regnskab for $regnskab / $db", "$brugernavn har bestilt opgradering af $regnskab til professionelt regnskab", "$headers");
#		include("../includes/online.php");
	} elseif ($bestil) {
		include("../includes/connect.php");
		$headers = 'From: saldi@saldi.dk'."\r\n".'Reply-To: saldi@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
		mail("saldi@saldi.dk", "SALDI bestilling af professionelt regnskab for $regnskab / $db", "$brugernavn har bestilt opgradering af $regnskab til professionelt regnskab", "$headers");
		include("../includes/online.php");
	}
	if ($bogfor) {
		$periode_fejl = NULL;
		$q_dates = db_select("SELECT DISTINCT transdate FROM kassekladde WHERE kladde_id=$kladde_id", __FILE__." linje ".__LINE__);
		while ($rd = db_fetch_array($q_dates)) {
			if ($err = check_periode_luk($rd['transdate'])) { $periode_fejl = $err; break; }
		}
		// p1-yearend-F4: a journal posted into a fiscal year that has a following year carries its balances into the
		// following years' opening (bogfor() -> updateFollowingOpeningBalances()). It takes the carry's lock before its
		// transaction begins, so its first read comes after any other session's carry has committed (on MySQL and MariaDB
		// a transaction reads one snapshot from its first read on) and no other carry commits before it has. It is
		// released after the rollback, or after the page's last recalculation of the year that follows the commit
		// (p1-pos-close-N1, below); an exit before that (a refusal in bogfor(), the database error box) leaves it to the
		// end of the request (the shutdown function of openingCarryLockHeld()).
		$carryLockRefused = '';
		if (!$periode_fejl) {
			include_once(__DIR__ . '/../includes/updateFollowingOpeningBalances.php');
			if (openingCarryHasFollowingYear($regnaar)) {
				try {
					openingCarryLockBeforeTransaction(true);
				} catch (OpeningCarryConflict $e) {
					error_log('Saldi journal ' . (int)$kladde_id . ': ' . $e->getMessage());
					$carryLockRefused = openingCarryConflictText($e, "Bogføringen er ikke gennemført, og intet er gemt: primo i de følgende regnskabsår kunne ikke låses mod samtidige opdateringer fra andre brugere. Bogfør igen.");
				}
			}
		}
		if ($periode_fejl) {
			print "<BODY onLoad=\"javascript:alert('" . addslashes($periode_fejl) . "')\">";
		} elseif ($carryLockRefused) {
			$bogfor = false;
			print "<script>alert(" . json_encode($carryLockRefused, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ");</script>";
		} else {
			transaktion('begin');
			$postingError = bogfor($kladde_id, $kladdenote,'');
			if (!$postingError && !$db_modify_fejl) {
				db_modify("delete from tmpkassekl where kladde_id = $kladde_id",__FILE__ . " linje " . __LINE__);
			}
			# 20260812 CL/LH (SD-644): SD-595-moenster som i includes/ordrefunc.php:1631 - en fejlet
			# skrivning (fx trigger-RAISE fra periodelaasen) maa aldrig rapporteres som succes.
			# Flaget tjekkes FOER commit og der rulles eksplicit tilbage: paa PostgreSQL er
			# transaktionen allerede afbrudt, men paa MySQL/InnoDB ville et COMMIT ellers
			# persistere de foregaaende succesfulde statements som en delvis kladde.
			if ($postingError || $db_modify_fejl) {
				transaktion('rollback');
				openingCarryUnlock();
				$bogfor = false;
				print "<BODY onLoad=\"javascript:alert('" . addslashes($postingError ?: "Bogfoering fejlede - databasen afviste transaktionen. Ingen posteringer er gemt.") . "')\">";
			} else {
				transaktion('commit');
				// p1-pos-close-N1: the carry's lock stays held through the recalculations of the year: genberegn() here and
				// again where the page shows the posted journal ("Kladden er bogført!"), which releases it. genberegn()
				// rewrites every kontoplan row of the year; released before it, a POS day close waiting for the lock took
				// its snapshot in between and failed on its own posting into that year with the database error box and a
				// mail. Where the page does not get there, the end of the request releases it.
				genberegn($regnaar);
				equalizeMatchingRecords();
				if ($popup) print "<BODY onLoad=\"javascript=opener.location.reload();\">";
			}
		}
	} elseif ($simuler) {
		transaktion('begin');
		$postingError = bogfor($kladde_id, $kladdenote,'on');
		if ($postingError || $db_modify_fejl) {
			transaktion('rollback');
			$simuler = false;
			print "<script>alert(" . json_encode($postingError ?: 'Simulering fejlede. Ingen posteringer er gemt.') . ");</script>";
		} else {
			transaktion('commit');
			if ($popup) {
				print "<BODY onLoad=\"javascript=opener.location.reload();\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
			}
		}
	}
	if ($funktion=='bogfor' || $funktion=='simuler') {
		if ($bogfor || $simuler) print "<meta http-equiv=\"refresh\" content=\"0;URL=kladdeliste.php\">";
		else print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";
	}
} elseif ($_POST['luk']) {
	#	if ($popup) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
	#	else print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";
}
$x=0;
$debetsum=0;
$kreditsum=0;
$valutadiff=0;
$valutaposter=0;

$dkkamount=array();
if ($kladde_id) {
	$posteringer=0;
	// A row with an amount but no account was refused at the top of the page, so the preview never deletes rows.
	$query = db_select("select * from kassekladde where kladde_id = $kladde_id and amount !=0 order by bilag",__FILE__ . " linje " . __LINE__);
	while ($row =	db_fetch_array($query)){
		if ($row['debet']||$row['kredit']) {
			$posteringer++;
			$bilag[$posteringer]=$row['bilag'];
			$y=$row['bilag'];
			if (!isset($tjeksum[$y])) $tjeksum[$y]=0;
			$d_type[$posteringer]=trim($row['d_type']);
			$debet[$posteringer]=$row['debet']*1;
			$k_type[$posteringer]=trim($row['k_type']);
			$kredit[$posteringer]=$row['kredit']*1;
			$faktura[$posteringer]=trim($row['faktura']);
			$amount[$posteringer]=$row['amount']*1;
			$momsfri[$posteringer]=trim($row['momsfri']);
			$debetvat[$posteringer]=get_saved_vat_override($row, 'debetvat');
			$kreditvat[$posteringer]=get_saved_vat_override($row, 'kreditvat');
			$valuta[$posteringer]=trim($row['valuta']);
			if (!$debet[$posteringer]) $d_type[$posteringer]='F';
			if (!$kredit[$posteringer]) $k_type[$posteringer]='F';
			if ($row['valuta'] && $row['amount'] && ($row['debet']||$row['kredit']))  {
				$valutaposter++;
				// The same rate as bogfor() (postingLineAmounts()), so the preview of a reversal balances as its posting does.
				list($dkkamount[$posteringer],$diffkonto,$valutakurs)=journalLineInBaseCurrency($amount[$posteringer],$row['valuta'],$row['transdate'],ifset($row,'ompost_id'));
#				if ($row['debet']) $valutadiff=$valutadiff+$dkkamount[$posteringer];
#				if ($row['kredit']) $valutadiff=$valutadiff-$dkkamount[$posteringer];
#				$valutadiff=round($valutadiff+0.0001,3);
#				echo "VS2 $valutadiff<br>";		
			} else $dkkamount[$posteringer]=$amount[$posteringer];
			if($debet[$posteringer]) {
				$tjeksum[$y]=afrund($tjeksum[$y]+$dkkamount[$posteringer],3);
				$debetsum=$debetsum+$dkkamount[$posteringer];
			}
			if($kredit[$posteringer]) {
				$tjeksum[$y]=afrund($tjeksum[$y]-$dkkamount[$posteringer],3);
				$kreditsum=$kreditsum+$dkkamount[$posteringer];
			}
		}
	}	
} else {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
	exit;
}

$x=0;
$debitor=array();
for ($y=1;$y<=$posteringer;$y++) {
	if (strstr($d_type[$y],'D')) {
		$d_debitor[$y]=$debet[$y];	
		if (!in_array($debet[$y],$debitor)) {
			$x++;
			$debitor[$x]=$debet[$y];
			$debitoramount[$x]=0;
		}
	} if (strstr($k_type[$y],'D')) {
		$k_debitor[$y]=$kredit[$y];	
		if (!in_array($kredit[$y],$debitor)) {
			$x++;
			$debitor[$x]=$kredit[$y];
			$debitoramount[$x]=0;
		}
	}
}
$debitorantal=$x;
$x=0;
$kreditor=array();
for ($y=1;$y<=$posteringer;$y++) {
	if (strstr($d_type[$y],'K')) {
		$d_kreditor[$y]=$debet[$y];	
		if (!in_array($debet[$y],$kreditor)) {
			$x++;
			$kreditor[$x]=$debet[$y];
			$kreditoramount[$x]=0;
		}
	} if (strstr($k_type[$y],'K')) {
		$k_kreditor[$y]=$kredit[$y];	
		if (!in_array($kredit[$y],$kreditor)) {
			$x++;
			$kreditor[$x]=$kredit[$y];
			$kreditoramount[$x]=0;
		}
	}
}
$kreditorantal=$x;

# kontrollerer om der er tale om en debitor eller kreditor konto hvor der skal beregnes moms 
# Konti fra kontoplanen bliver forbig&aring;et i funktionen
for ($y=1; $y<=$posteringer; $y++) {
	if (strlen($debet[$y])>0){
		list ($debet[$y], $d_momsart[$y]) =gruppeopslag($d_type[$y], $debet[$y]);
	}
	if (strlen($kredit[$y])>0){
		list ($kredit[$y], $k_momsart[$y])=gruppeopslag($k_type[$y], $kredit[$y]);
	}
}

# Funktionen momsberegning finder momssatsen og beregner momsen. 
for ($y=1; $y<=$posteringer; $y++) {
	$momsfri[$y]=str_replace(" ","",$momsfri[$y]);
	$debet[$y]=str_replace(" ","",$debet[$y]);
	$kredit[$y]=str_replace(" ","",$kredit[$y]);
	if ($debet[$y]>0)  $d_amount[$y]=$dkkamount[$y];
	if ($kredit[$y]>0) $k_amount[$y]=$dkkamount[$y];
	if ((!$momsfri[$y])&&($debet[$y]>0)&&($d_amount[$y]>0)) {
	list ($d_amount[$y], $d_moms[$y], $d_momskto[$y], $d_modkto[$y])=momsberegning($debet[$y], $d_amount[$y], $d_momsart[$y], $k_momsart[$y], $debetvat[$y], trim((string)$kreditvat[$y]) !== '');
	}
	if ((!$momsfri[$y])&&($kredit[$y]>0)&&($k_amount[$y]>0)){
		list ($k_amount[$y], $k_moms[$y], $k_momskto[$y], $k_modkto[$y])=momsberegning($kredit[$y], $k_amount[$y], $k_momsart[$y], $d_momsart[$y], $kreditvat[$y], trim((string)$debetvat[$y]) !== '');
	}
}
/*
Alle posteringer loebes igennem igen - Hvis der er tale en en postering med EU moms er der en modkonto (x-modkto)- 
Hvis der samtidig er en modpostering flyttes modposteringen op "for enden" af posteringsraekken og antallet af posteringer oeges med en.
Denne flytning sker KUN naar den er tale om en dobbeltpostering hvor den ene eller begge er konti fra kontoplanen med EU moms
*/
for ($y=1; $y<=$posteringer; $y++) {
	if (!isset($d_modkto[$y]))$d_modkto[$y]=0;
	if (!isset($k_modkto[$y]))$k_modkto[$y]=0;
	if (!isset($d_momskto[$y]))$d_momskto[$y]=0;
	if (!isset($k_momskto[$y]))$k_momskto[$y]=0;
	if ($d_modkto[$y]>0) {	
		if ($k_moms[$y]) {
			$posteringer++;
			$k_momskto[$posteringer]=$k_momskto[$y];
			$k_moms[$posteringer]=$k_moms[$y];
		}
		$k_moms[$y]=$d_moms[$y];
		$k_momskto[$y]=$d_modkto[$y];
	}
	if ($k_modkto[$y]>0) {
		if ($d_moms[$y]) { 
			$posteringer++;
			$d_momskto[$posteringer]=$d_momskto[$y];
			$d_moms[$posteringer]=$d_moms[$y];
		}
		$d_moms[$y]=$k_moms[$y];
		$d_momskto[$y]=$k_modkto[$y];
	}
}
$kontoantal=0;
$kontoliste=array()	;
for ($y=1; $y<=$posteringer; $y++) {
	if ((!in_array($debet[$y], $kontoliste))&&($debet[$y]>0)) {
		$kontoantal++;
		$kontoliste[$kontoantal]=$debet[$y];
	}
	if ((!in_array($kredit[$y], $kontoliste))&&($kredit[$y]>0)) {
		$kontoantal++;
		$kontoliste[$kontoantal]=$kredit[$y];
	}
	if (($d_momskto[$y])&&(!in_array($d_momskto[$y], $kontoliste))) {
		$kontoantal++;
		$kontoliste[$kontoantal]=$d_momskto[$y];
	}
	if (($k_momskto[$y])&&(!in_array($k_momskto[$y], $kontoliste))) {
		$kontoantal++;
		$kontoliste[$kontoantal]=$k_momskto[$y];
	}
}
sort($kontoliste);
$kontodebet=array();
$kontokredit=array();
for ($y=0; $y<$kontoantal; $y++) {
	if (!isset($kontodebet[$y]))$kontodebet[$y]=0;
	if (!isset($kontokredit[$y]))$kontokredit[$y]=0;
	for($z=1; $z<=$posteringer; $z++) {
		if ($kontoliste[$y]==$debet[$z]){$kontodebet[$y]=$kontodebet[$y]+$d_amount[$z];}
		if ($kontoliste[$y]==$kredit[$z]){$kontokredit[$y]=$kontokredit[$y]+$k_amount[$z];}
		if ($kontoliste[$y]==$d_momskto[$z]){$kontodebet[$y]=$kontodebet[$y]+$d_moms[$z];}
		if ($kontoliste[$y]==$k_momskto[$z]){$kontokredit[$y]=$kontokredit[$y]+$k_moms[$z];}
	}
}

print "<form name=kassekladde action=bogfor.php?funktion=$funktion method=post>";
if ($funktion=='bogfor') {
	$query = db_select("select kladdenote from kladdeliste where id=$kladde_id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	print "<td align=center valign=\"top\" height=10px><center><b><font face=\"Helvetica, Arial, sans-serif\">Bem&aelig;rkning:&nbsp;</b><input type=text size=95 name=kladdenote value='$row[kladdenote]'></center></td>";
	print "</tr><tr><td height=10px><hr></td></tr>";
}
$d_sum=0; $k_sum=0;
$kladde_vis = findtekst('601|Kassekladde', $sprog_id)." $kladde_id";
if ($kladdenote_vis) $kladde_vis .= " &ndash; $kladdenote_vis";
print "<style>@media print { .no-print, input[type=submit], button { display:none !important; } }</style>";
print "<tr><td align=center valign=\"top\" style='padding-top:10px'><b>$kladde_vis</b>&nbsp;
	<a class='no-print' href='javascript:void(0)' onclick='window.print(); return false;'>
	<img src='../ikoner/print.png' style='border:0; vertical-align:middle;' title='Print'></a></td></tr>";
print "<tr><td align = center valign=\"top\"><center><table width='75%' style='margin-top:20px' class='dataTableSmall' border=1 cellspacing=0 cellpadding=0><tbody>";
print "<tr><td colspan=\"6\" class='tableHeader'><b>".findtekst(1088,$sprog_id)."</b></td></tr>
	<tr><td class='tableText'>$font ".findtekst(440,$sprog_id)."</td>
	<td class='tableText'>$font ".(findtekst(914,$sprog_id))."</td>
	<td align=\"center\" class='tableText'>$font ".findtekst(1073,$sprog_id)."</td>
	<td align=\"center\" class='tableText'>$font ".ucfirst(findtekst(1000,$sprog_id))."</td>
	<td align=\"center\" class='tableText'>$font ".ucfirst(findtekst(1001,$sprog_id))."</td>
	<td align=\"center\" class='tableText'>$font ".findtekst(39,$sprog_id)." ".lcfirst(findtekst(1073,$sprog_id))."</td></tr>";
for ($y=0; $y<$kontoantal; $y++) {
	$d_sum=$d_sum+afrund($kontodebet[$y],2);
	$k_sum=$k_sum+afrund($kontokredit[$y],2);

	$query = db_select("select * from kontoplan where kontonr='$kontoliste[$y]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$saldo=$row['saldo'];
		$a=dkdecimal($saldo);
		$b=dkdecimal($kontodebet[$y]);
		$c=dkdecimal($kontokredit[$y]);
		$d=dkdecimal($saldo+$kontodebet[$y]-$kontokredit[$y]);
		$beskrivelse=db_escape_string($row['beskrivelse']);
		print "<tr><td>$font $kontoliste[$y]</td><td>$font $beskrivelse</td><td align=right>$font $a</td><td align=right>$font $b</td><td align=right>$font $c</td><td align=right>$font $d</td></tr>";
	}
	else {
		print "<tr><td>$font $kontoliste[$y]</td><td>FINDES IKKE !!</td><td align=right>$font $a</td><td align=right>$font $b</td><td align=right>$font $c</td><td align=right>$font $d</td></tr>";
		$fejltext = "OBS:Kontonr: $kontoliste[$y] FINDES IKKE !!";
	}
}
#valutadiff=round($valutadiff+0.0001,3);
$diff=afrund($debetsum-$kreditsum,3);
if (abs($diff) < $valutaposter/100) {
	$valutadiff=$diff;
} 

if ($valutadiff && abs($valutadiff) < $valutaposter/100) {
	if (isset($diffkonto)&&(!in_array($diffkonto,$kontoliste) && $diffkonto>0)) { 
		$y++;
		$kontoliste[$y]=$diffkonto;
		$kontokredit[$y]=0;
		$kontodebet[$y]=0;
	}
	if ($valutadiff > 0) {
		$k_sum=$k_sum+$valutadiff;
		$kontokredit[$y]=$valutadiff;
		$diff=$diff-$valutadiff;
	} else {
		$d_sum=$d_sum-$valutadiff;
		$kontodebet[$y]=$valutadiff*-1;
		$diff=$diff-$valutadiff;
	}
	$query = db_select("select * from kontoplan where kontonr='$diffkonto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$saldo=$row['saldo'];
		$a=dkdecimal($saldo);
		$b=dkdecimal($kontodebet[$y]);
		$c=dkdecimal($kontokredit[$y]);
		$d=dkdecimal($saldo+$kontodebet[$y]-$kontokredit[$y]);
		$beskrivelse=db_escape_string($row['beskrivelse']);
		print "<tr><td>$font $kontoliste[$y]</td><td>$font $beskrivelse</td><td align=right>$font $a</td><td align=right>$font $b</td><td align=right>$font $c</td><td align=right>$font $d</td></tr>";
	}
	else {
		print "<tr><td>$font $kontoliste[$y]</td><td>FINDES IKKE !!</td><td align=right>$font $a</td><td align=right>$font $b</td><td align=right>$font $c</td><td align=right>$font $d</td></tr>";
		$fejltext = "OBS:Kontonr: $kontoliste[$y] FINDES IKKE !!";
	}
}
$b=dkdecimal($d_sum);
$c=dkdecimal($k_sum);
print "<tr><td><br></td><td>$font ".findtekst(1084,$sprog_id)."</td><td align=right><br></td><td align=right>$font $b</td><td align=right>$font $c</td><td align=right><br></td></tr>";

# 20131115 ->
$x=0;
$diffbilag=array();

for ($y=1;$y<=$posteringer;$y++) {
	if (isset ($bilag[$y-1]) && $bilag[$y]!=$bilag[$y-1]) {
		if (afrund($b_sum[$x],2) && afrund($bvSum[$x],2)) $diffbilag[$y-1]=afrund($b_sum[$x],2);
		$x++;
	}
	if (!isset ($b_sum[$x])) $b_sum[$x] = 0;
	if (!isset ($bvSum[$x])) $bvSum[$x] = 0;
	if (!isset ($dkkamount[$y])) $dkkamount[$y] = 0;
	if (!isset ($amount[$y])) $amount[$y] = 0;
	if ($debet[$y]>0) {
		$b_sum[$x]+=afrund($dkkamount[$y],2);
		$bvSum[$x]+=afrund($amount[$y],2);
	}
	if ($kredit[$y]>0) {
		$b_sum[$x]-=afrund($dkkamount[$y],2);
		$bvSum[$x]-=afrund($amount[$y],2);
	}
}
if (afrund($b_sum[$x],2)) $diffbilag[$y-1]=afrund($b_sum[$x],2);
# <- 20131115
$fejl=0; #20140228
// p1-journal-J3h: a voucher with an invoice moved from Hent ordrer that does not balance in DKK on its own is not posted,
// also when another such voucher makes up for it in the journal's total, so the button that posts is left out. Any
// other journal is checked as before.
$movedInvoiceRefusal = movedInvoiceRateMessage($kladde_id, false, true);
if (saldi_assist_has_differences((float)$diff, $diffbilag)) { #20131115 ( || count($diffbilag))
	print "<tr><td colspan=6><br>";
	print "<table width=100% border=1><tbody>"; 
	print "<tr><td align=center colspan=2>Der er differencer p&aring; følgende bilag</td></tr>";
	print "<tr><td align=center>Bilag</td><td align=center>Difference</td></tr>";
	$tmp=NULL;
	for ($x=1; $x<=$posteringer; $x++) {
		$y=$bilag[$x];
		if ($tjeksum[$y]!=0) {
			 print "<tr><td align=right>$y</td><td align=right>".dkdecimal($tjeksum[$y])."</td></tr>";
			 $tmp=$y;
			 $tjeksum[$y]=0;
		} elseif (isset($diffbilag[$x]) && $diffbilag[$x] && $tmp!=$y) { #20131115
			 print "<tr><td align=right>$y</td><td align=right>".dkdecimal($diffbilag[$x]). "</td></tr>";
		}
	}
	print "</tbody></table></td></tr>";
	// 20261119 A voucher from Hent ordrer whose date's rate was changed after the move: the preview says why and what to do, as Simulér and Bogfør do. Text only, $fejl is unchanged.
	if ($rateMessage = movedInvoiceRateMessage($kladde_id, false)) {
		print "<tr><td align=center colspan=6>" . htmlspecialchars($rateMessage, ENT_QUOTES | ENT_SUBSTITUTE, $charset) . "</td></tr>";
	}
	#$fejl=1;
	if (abs($diff)>=0.01 || $movedInvoiceRefusal) $fejl=1;
} elseif ($b!=$c || abs($d_sum - $k_sum) >= 0.01) {
echo "$b!=$c $d_sum != $k_sum<br>";
	$message=$db." | Uoverensstemmelse i posteringssum | ".__FILE__ . " linje " . __LINE__." | ".$brugernavn." ".date("Y-m-d H:i:s");
	$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
	mail('fejl@saldi.dk', 'SALDI Fejl', $message, $headers);
	print "<BODY onLoad=\"javascript:alert('Uoverensstemmelse i posteringssum - Kontakt venligst SALDI teamet p&aring; telefon 4690 2208')\">";
	$fejl=1; #20140228
} elseif ($fejltext) {
	print "<tr><td colspan=6><br></td></tr><tr><td align=center colspan=6>$fejltext</td></tr>";
	$fejl=1; #20140228
}
if ($movedInvoiceRefusal && !$fejl) { # p1-journal-J3h, where the preview did not list the voucher
	print "<tr><td align=center colspan=6>" . htmlspecialchars($movedInvoiceRefusal, ENT_QUOTES | ENT_SUBSTITUTE, $charset) . "</td></tr>";
	$fejl=1;
}
if (!$fejl) { #20140228
	$query = db_select("select * from kladdeliste where id = $kladde_id and bogfort = 'V'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		print "Kladden er bogført!";
		genberegn($regnaar);
		// The last recalculation of the year after a posting: the carry's lock is released now (p1-pos-close-N1).
		if (function_exists('openingCarryUnlock')) openingCarryUnlock();
		exit;	
	}
	for ($x=1;$x<=$debitorantal;$x++) {
		$debitordebet[$x]=0;
		$debitorkredit[$x]=0;
		for ($y=1;$y<=$posteringer;$y++) {
			if (isset($d_debitor[$y]) && $debitor[$x]==$d_debitor[$y]) $debitordebet[$x]+=$dkkamount[$y];   
			if (isset($k_debitor[$y]) && $debitor[$x]==$k_debitor[$y]) $debitorkredit[$x]+=$dkkamount[$y];  
		}
		$r=db_fetch_array(db_select("select id,firmanavn from adresser where kontonr='$debitor[$x]' and art='D'",__FILE__ . " linje " . __LINE__));
		$debitor_id[$x]=$r['id']*1;
		$debitor_navn[$x]=$r['firmanavn'];
		$debitor_pre[$x]=0;
		$q=db_select("select amount,valutakurs from openpost where konto_id='$debitor_id[$x]' and transdate<='$regnslut'",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			if ($r['valutakurs']) $debitor_pre[$x]+=afrund($r['amount']*$r['valutakurs']/100,2);
			else $debitor_pre[$x]+=afrund($r['amount'],2);
			
		}
		$debitor_post[$x]=$debitor_pre[$x]+$debitordebet[$x]-$debitorkredit[$x];
	}

	if ($debitorantal) {
		print "<tr><td colspan=\"6\" class='tableHeader'><b>Debitorbev&aelig;gelser</b></td></tr>";
		print "<tr><td class='tableText'>$font Konto</td>
			<td class='tableText'>$font Beskrivelse</td>
			<td align=\"center\" class='tableText'>$font Saldo</td>
			<td align=\"center\" class='tableText'>$font Debet</td>
			<td align=\"center\" class='tableText'>$font Kredit</td>
			<td align=\"center\" class='tableText'>$font Ny saldo</td></tr>";
		for ($x=1;$x<=$debitorantal;$x++) {
			print "<tr><td>$debitor[$x]</td>
				<td>$debitor_navn[$x]</td>
				<td align=\"right\">".dkdecimal($debitor_pre[$x])."</td>
				<td align=\"right\">".dkdecimal($debitordebet[$x])."</td>
				<td align=\"right\">".dkdecimal($debitorkredit[$x])."</td>
				<td align=\"right\">".dkdecimal($debitor_post[$x])."</td></tr>";
		}
	}

	for ($x=1;$x<=$kreditorantal;$x++) {
		$kreditordebet[$x]=0;
		$kreditorkredit[$x]=0;
		for ($y=1;$y<=$posteringer;$y++) {
			if (isset($d_kreditor[$y]) && $kreditor[$x]==$d_kreditor[$y]) $kreditordebet[$x]+=$dkkamount[$y];  
			if (isset($k_kreditor[$y]) && $kreditor[$x]==$k_kreditor[$y]) $kreditorkredit[$x]+=$dkkamount[$y];  
		}
		$r=db_fetch_array(db_select("select id,firmanavn from adresser where kontonr='$kreditor[$x]' and art='K'",__FILE__ . " linje " . __LINE__));
		$kreditor_id[$x]=$r['id']*1;
		$kreditor_navn[$x]=$r['firmanavn'];
		$kreditor_pre[$x]=0;
		$q=db_select("select amount,valutakurs from openpost where konto_id='$kreditor_id[$x]' and transdate <= '$regnslut'",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			if ($r['valutakurs']) $kreditor_pre[$x]+=afrund($r['amount']*$r['valutakurs']/100,2);
			else $kreditor_pre[$x]+=afrund($r['amount'],2);

		}
		$kreditor_post[$x]=$kreditor_pre[$x]+$kreditordebet[$x]-$kreditorkredit[$x];
	}
	if ($kreditorantal) {
		print "<tr><td colspan=\"6\" class='tableHeader'><b>Kreditorbev&aelig;gelser</b></td></tr>";
		print "<tr><td class='tableText'>$font Konto</td>
			<td class='tableText'>$font Beskrivelse</td>
			<td align=\"center\" class='tableText'>$font Saldo</td>
			<td align=\"center\" class='tableText'>$font Debet</td>
			<td align=\"center\" class='tableText'>$font Kredit</td>
			<td align=\"center\" class='tableText'>$font Ny saldo</td></tr>";
		for ($x=1;$x<=$kreditorantal;$x++) {
			print "<tr><td>$kreditor[$x]</td>
				<td>$kreditor_navn[$x]</td>
				<td align=\"right\">".dkdecimal($kreditor_pre[$x])."</td>
				<td align=\"right\">".dkdecimal($kreditordebet[$x])."</td>
				<td align=\"right\">".dkdecimal($kreditorkredit[$x])."</td>
				<td align=\"right\">".dkdecimal($kreditor_post[$x])."</td></tr>";
		}
	}
#	else {
	print "<input type=hidden name=kladde_id value=$kladde_id>";
	if ($funktion=='bogfor') {
		$onclick="";
		// Not for accounts with a package from the self-service signup ($regnskab_pakke, includes/online.php): their
		// postings follow the price list on saldi.dk, not this old "+1000 for kr. 30,-" offer, and sales handles more.
		if (($sqdb=="saldi" || $sqdb=='gratis'|| $sqdb=='develop') && $max_posteringer && $max_posteringer<=8500 && $d_sum>0 && empty($regnskab_pakke)) {
			$y=date('Y')-1;$m=date('m');$d=date('d');
			while (!checkdate($m, $d, $y)) { #Skudår !
				$d=$d-1;
				if ($d<28) break 1;
			}
			$tmp=$y."-".$m."-".$d;
			$r=db_fetch_array(db_select("select count(id) as transantal from transaktioner where transdate>='$tmp'",__FILE__ . " linje " . __LINE__));
			$transantal=$r['transantal']*1;
			if ($transantal > $max_posteringer) {
				if ($sqdb=='saldi' || $sqdb=='develop') {
					$txt = "Dit maksikale posteringsantal ($max_posteringer) er overskredet.\\n";
					$txt.= "Der er i alt foretaget $transantal posteringer inden for de sidste 12 mdr.\\n";
					$txt.= "Klik OK for at opdatere med yderligere 1000 &aring;rlige posteringer.\\nDet koster kr. 30,- pr. m&aring;ned excl. moms.";
					$onclick= "onclick=\"return confirm('$txt')\"";
					print "<input type=\"hidden\" name=\"opdater\" value=\"1000\">";
				} elseif ($sqdb=="gratis" || $sqdb=="develop") {
					$txt = "Dit maksimale antal gratis posteringer ($max_posteringer) er overskredet.\\n";
					$txt.= "Der er i alt foretaget $transantal posteringer inden for de sidste 12 m&aring;neder, og der er ikke flere gratis posteringer.\\n";
					$txt.= "For at komme videre kan du foretage en sikkerhedskopi, bestille et professionelt regnskab p&aring; http://saldi.dk/bestilling \\n";
					$txt.= "og indlæse din sikkerhedskopi af hele dit regnskab der.\\n";
					$txt.= "Alternativt kan du kontakte Saldi.dk ApS på tlf 4690 2208 og høre om mulighederne for ekstra gratis posteringer,\\n";
					$txt.= "som kan fås ved at linke til saldi.dk fra din hjemmeside\\n";
					$onclick= "onclick=\"return confirm('$txt')\"";
					print "<input type=\"hidden\" name=\"stop\" value=\"on\">";
				}
			} 
		}
		print "<tr><td colspan=6 class='tableHeader'><br></td></tr><tr><td colspan=6 align=center><input class='button green medium' type=submit $onclick accesskey=\"b\" value=\"".findtekst('1065|Bogfør', $sprog_id)."\" name=\"bogfor\"> <input class='button red medium' type=submit accesskey=\"l\" value=\"".findtekst('30|Tilbage', $sprog_id)."\" name=\"luk\"></td></tr>";
	} else {
		print "<tr><td colspan=6 class='tableHeader'><br></td></tr><tr><td colspan=6 align=center><input class='button gray medium' type=submit $onclick accesskey=\"b\" value=\"".findtekst('1085|Simuleret',$sprog_id)." ".findtekst('1086|Bogføring', $sprog_id)."\" name=\"simuler\"> <input class='button red medium' type=submit accesskey=\"l\" value=\"".findtekst('30|Tilbage', $sprog_id)."\" name=\"luk\"></td></tr>";
	}
	print "</form>";
}
print "</td></tr></tbody></table>";
######################################################################################################################################
function bogfor($kladde_id,$kladdenote,$simuler) {
	global $connection;
	global $regnaar;
	global $brugernavn;

	$CurencyCheckSum=$tjeksum=$posteringer=$transantal=$transtjek=$d_sum=$k_sum=0;

	if (!isset ($valutakurs)) $valutakurs = NULL;
	if (!isset ($b_antal)) $b_antal = NULL;
	if (!isset ($y)) $y = NULL;
	if (!isset ($momsart)) $momsart = NULL;

	($simuler)?$tabel='simulering':$tabel='transaktioner';
	
	// The callers run bogfor() inside transaktion('begin'). The journal row stays locked until that transaction
	// ends, so a second request for the same journal waits here until the first has committed or rolled back.
	// It then reads the committed bogfort and posts nothing when the journal has been posted meanwhile.
	$qtxt="select bogfort from kladdeliste where id = ".(int)$kladde_id." for update";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r && trim((string)$r['bogfort'])=='V') {
		return("Kladde ".(int)$kladde_id." er allerede bogført. Ingen posteringer er gemt.");
	}
	$r=db_fetch_array(db_select("select max(id) as id from transaktioner where kladde_id = $kladde_id",__FILE__ . " linje " . __LINE__));
	if ($r['id']) {
		print "<BODY onLoad=\"javascript:alert('Kladden er allerede bogført!')\">";
		return("Kladden er allerede bogført");
		exit;
	}
	// Refuse before anything is written: an unfinished row would otherwise be skipped and lost.
	$unfinishedVouchers = unfinishedJournalVouchers($kladde_id);
	if ($unfinishedVouchers) return unfinishedJournalMessage($unfinishedVouchers);
	// p1-journal-J3h: a voucher with an invoice moved from Hent ordrer that does not balance in DKK on its own (the rate of
	// its date was changed after the move) is refused, also when another such voucher makes up for it in the journal's
	// total, which is all the checks below look at. Any other journal is checked as before.
	if ($rateMessage = movedInvoiceRateMessage($kladde_id, true, true)) return $rateMessage;

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
			$beskrivelse[$y]=db_escape_string($row['beskrivelse']);
			// Control account, VAT split and DKK amount come from the shared posting rules.
			$line=postingLineAmounts($row);
			$d_type[$y]=$line['d_type'];
			$k_type[$y]=$line['k_type'];
			$faktura[$y]=db_escape_string($row['faktura']);
			$amount[$y]=$row['amount'];
			$dkkamount[$y]=$line['dkkamount'];
			$diffkonto[$y]=$line['diffkonto'];
			$valutakurs[$y]=$line['valutakurs'];
			$afd[$y]=$row['afd'];
			$ansat[$y]=$row['ansat']*1;
			$projekt[$y]=$row['projekt'];
			$valuta[$y]=$row['valuta']*1;
			$ordre_id[$y]=$row['ordre_id']*1;
			if (!isset ($valutakurs[$y])) $valutakurs[$y] = NULL;
			if (!$valutakurs[$y]) $valutakurs[$y]=100;
			// The rate the line is posted at goes on the line too, so a later Tilbagefør of it converts at that rate
			// (reversedLineRate() in bogfor_includes/postingRules.php), also next to a line posted at another rate.
			if (!$simuler) recordPostedLineRate($row, $valutakurs[$y]);
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
			// Open items are booked on the debtor/creditor number itself, not on the control account.
			if (((strstr($d_type[$y],'D'))||(strstr($d_type[$y],'K'))) && $row['debet']>0) {
				if (!$simuler) openpost($d_type[$y], $row['debet'], $bilag[$y], $faktura[$y], $amount[$y], $beskrivelse[$y], $transdate[$y], $postid[$y], $valuta[$y], $valutakurs[$y], $forfaldsdate[$y], $betal_id[$y],$projekt[$y]);
			}
			if ((($k_type[$y]=='D')||($k_type[$y]=='K')) && $row['kredit']>0) {
				if (!$simuler) openpost($k_type[$y], $row['kredit'], $bilag[$y], $faktura[$y], $amount[$y]*-1, $beskrivelse[$y], $transdate[$y], $postid[$y], $valuta[$y], $valutakurs[$y], $forfaldsdate[$y], $betal_id[$y],$projekt[$y]);
			}
			$debet[$y]=$line['debet'];
			$kredit[$y]=$line['kredit'];
			$d_momsart[$y]=$line['d_momsart'];
			$k_momsart[$y]=$line['k_momsart'];
			if ($line['eu_vat']) $eufaktnr[$y]=$faktura[$y]; # Bruges laengere nede til at undgaa at transantal oeges v. eu momsposteringer.
			$momsfri[$y]=$line['momsfri'];
			$d_amount[$y]=$line['d_amount'];
			$d_moms[$y]=$line['d_moms'];
			$d_momskto[$y]=$line['d_momskto'];
			$d_modkto[$y]=$line['d_modkto'];
			$k_amount[$y]=$line['k_amount'];
			$k_moms[$y]=$line['k_moms'];
			$k_momskto[$y]=$line['k_momskto'];
			$k_modkto[$y]=$line['k_modkto'];
			$logdate=date("Y-m-d");
			$logtime=date("H:i");
			list ($x, $month, $x)=explode('-', $transdate[$y]);
			if (!$afd[$y]){$afd[$y]=0;}
		} elseif ($row['id'] && !journalRowHasContent($row)) { #20170516 - only rows without any content are dropped
			db_modify("delete from kassekladde where id = '$row[id]'",__FILE__ . " linje " . __LINE__);
		}
	}
	# end while
	$posteringer=$y;
	// NB: postingLineEntries() in bogfor_includes/postingRules.php repeats the EU reverse-charge swap below
	// (d_modkto/k_modkto) for the "Kontokort med u-bogført" report. Change both together, or the report's
	// unposted VAT rows will stop matching what posting writes.
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
				transaktion('rollback');
				print "<BODY onLoad=\"javascript:alert('Manglende modpostering i bilag $bilag[$i]!')\">";
				exit;
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
/*
				} elseif (($kontokredit[$y] || $kontodebet[$y]) && $b_diffkonto[$i]) {
					$qtxt="select * from kontoplan where kontonr='$b_diffkonto[$i]' and regnskabsaar='$regnaar'";
					$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
					if ($row = db_fetch_array($query)) {
						$saldo=$row['saldo'];
						$a=dkdecimal($saldo);
						$b=dkdecimal($kontodebet[$y]);
						$c=dkdecimal($kontokredit[$y]);
						$d=dkdecimal($saldo+$kontodebet[$y]-$kontokredit[$y]);
						$beskrivelse=db_escape_string($row['beskrivelse']);
						$qtxt = "insert into $tabel "; 
						$qtxt.= "(kontonr,bilag,transdate,logdate,logtime,beskrivelse,debet,kredit,faktura,kladde_id,afd,ansat,projekt,";
						$qtxt.= "valuta,valutakurs,ordre_id,moms)";
						$qtxt.= " values ";
						$qtxt.= "('$b_diffkonto[$i]','$b_bilag[$i]','$b_transdate[$i]','$logdate','$logtime','$beskrivelse','$kontodebet[$y]',";
						$qtxt.= "'$kontokredit[$y]','$b_faktura[$i]','$kladde_id','$b_afd[$i]','$b_ansat[$i]','$b_projekt[$i]','$b_valuta[$i]',";
						$qtxt.= "'$b_kurs[$i]','$b_ordre_id[$i]','0')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);

					} else {
						print "<BODY onLoad=\"javascript:alert('Konto $b_diffkonto[$i] til valutadifferncer eksisterer ikke!')\">";
						exit;
					} 
*/					
				} elseif (($kontokredit[$y] || $kontodebet[$y]) && !$b_diffkonto[$i] && $valuta[$i])  { #20131028 -- 20140228 tilføjet: && $valuta[$i
				// 20261119 A voucher from Hent ordrer whose rate changed: the caller rolls back and shows why, as for the other refusals.
				if ($rateMessage = movedInvoiceRateMessage($kladde_id)) return $rateMessage;
				transaktion('rollback');
				print "<BODY onLoad=\"javascript:alert('Manglende konto til valutadiffencer! (bilag: $b_bilag[$i])')\">";
				exit;
			}
		}
	}
	// Check the actual stored entries, including VAT and currency corrections, not only working sums.
	$qtxt = "SELECT COALESCE(SUM(COALESCE(debet,0)-COALESCE(kredit,0)),0) AS difference ";
	$qtxt .= "FROM $tabel WHERE kladde_id=" . (int)$kladde_id;
	$postedBalance = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (!$postedBalance || (float)$postedBalance['difference'] != 0.0) {
		if ($rateMessage = movedInvoiceRateMessage($kladde_id)) return $rateMessage; # 20261119
		$differenceText = $postedBalance ? dkdecimal($postedBalance['difference'], 3) : 'ukendt';
		return "Kladde $kladde_id balancerer ikke efter bogføring (debet minus kredit: $differenceText). Ingen posteringer er gemt.";
	}
	if (abs($tjeksum)<=0.01) { # && $transtjek==$transantal){
		$dato=date("Y-m-d");
		if ($simuler) {
			$qtxt="update kladdeliste set bogfort = 'S', bogforingsdate = '$dato', bogfort_af = '$brugernavn' where id = '$kladde_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else {
			$qtxt="update kladdeliste set bogfort = 'V', bogforingsdate = '$dato', bogfort_af = '$brugernavn' where id = '$kladde_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			for ($x=1; $x<=$transtjek; $x++) {
				$query = db_select("select saldo from kontoplan where id='$kasklid[$x]'",__FILE__ . " linje " . __LINE__);
				$row= db_fetch_array($query);
				$temp=$row['saldo'];
				if (!$temp) {$temp=0;}
				$transamount[$x]=($temp+$transamount[$x]);
				db_modify("update kontoplan set saldo = $transamount[$x] where id = '$kasklid[$x]'",__FILE__ . " linje " . __LINE__);
			}
			include_once(__DIR__ . '/../includes/updateFollowingOpeningBalances.php');
			updateFollowingOpeningBalances($regnaar);
		}
#xit;
	} else {
		transaktion('rollback');
		print "<tr><td align=center>$font Der er konstateret en afvigelse!\nKladde ikke bogført\nKontakt venligst Saldi's udviklerteam!</td></tr>";
		exit;
	}
#xit;	
} #endfunc bogfor
######################################################################################################################################
function openpost($art,$debet,$bilag,$faktura,$amount,$beskrivelse,$transdate,$bilag_id,$valutakode,$valutakurs,$forfaldsdate,$betal_id,$projekt){
	global $connection;
	global $regnaar;
	global $kladde_id;

	// 20260902 CL/LH  L4 finding dunning-run DEVY-5: a zero-amount posting line (e.g. a reminder
	// finalised without a fee) must not create an open item. A 0,00 row can never settle
	// anything (the +/-0.005 match window below only matches other zero rows) and it shows
	// up as a duplicate unsettled post under the same faktnr as the original invoice.
	if (abs((float)$amount) < 0.005) return;

## Finder kreditorens valuta;
	if ($valutakode) {
		$r = db_fetch_array(db_select("select box1 from grupper where art = 'VK' and kodenr = '$valutakode'",__FILE__ . " linje " . __LINE__));
		$valuta=$r['box1'];
	} else $valuta='DKK';
	$udlignet=0;
	$udlign_date=$transdate;
	$dato=date("Y-m-d");
	// No project is stored as '' on journal lines but as '0' (or NULL) on invoices; all three mean no project.
	// Trimmed once, so the stored value is the one matched here.
	$projekt = trim((string)$projekt);
	$projektSql = db_escape_string($projekt);
	if (in_array($projekt, array('', '0'), true)) {
		$projektMatch = "(projekt is null or projekt = '' or projekt = '0')";
	} else {
		$projektMatch = "projekt = '$projektSql'";
	}
	$belob=$amount*-1;
	$debet=str_replace(" ","",$debet);
	$qtxt = "select id from adresser where kontonr = '$debet' and art ='$art'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($row = db_fetch_array($q)){
		$konto_id=$row['id'];
		$query = db_select("select MAX(udlign_id) as udlign_id from openpost",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) $udlign_id=$row['udlign_id']+1;
# -> 2009.05.04		
		$min=$belob-0.005; 
		$max=$belob+0.005;
		if (strpos($faktura,";")) {
			$invoices = explode(';',$faktura);
		} else $invoices[0] = $faktura; 
		
# 20121122 >>and projekt='$projekt'<< indsat herunder.
		if ($udlign_date<$transdate) $udlign_date=$transdate;
		// Every invoice the Faktura field names is looked up before anything is settled. Keyed by id, so a
		// reference listed twice ("A;A") is one post.
		$matches = array();
		$matchedSum = 0;
		$groupDate = $transdate;
		for ($x=0;$x<count($invoices);$x++) {
			$qtxt = "select id,transdate,amount from openpost where konto_id='$konto_id' and $projektMatch and udlignet!='1' and ";
			$qtxt.= " faktnr='$invoices[$x]'";
			if (count($invoices) == 1) $qtxt.= " and amount >= '$min' and amount < '$max'";  
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			if (($r = db_fetch_array($q)) && !isset($matches[(int)$r['id']])) {
				$matches[(int)$r['id']] = true;
				$matchedSum += (float)$r['amount'];
# $udlign_date infort 2011.02.22 -udligningsdato skal altid vaere seneste dato. 
				$groupDate = max($groupDate, (string)$r['transdate']); # The later of invoice and payment date
			}
		}
		// The invoices settle only when they and the payment net to zero, within half an øre as for a single
		// invoice. A short or excess "A;B" payment leaves every post open, so the unpaid rest stays listed.
		if ($matches && $matchedSum >= $min && $matchedSum < $max) {
			$groupDateSql = db_escape_string($groupDate);
			// One udlign_id and the group's latest date for the invoices and the payment.
			$qtxt = "update openpost set udlignet = '1',udlign_date= '$groupDateSql',udlign_id='$udlign_id' ";
			$qtxt.= "where id in (" . implode(',', array_keys($matches)) . ")";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			// 20230626 -->
			// The payment row is written once per payment, not once per settled invoice.
			$qtxt = "insert into openpost
			(konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,udlign_date,kladde_id,";
			if ($forfaldsdate) $qtxt.= "forfaldsdate,betal_id,";
			$qtxt.= "transdate,udlign_id,bilag_id,valuta,valutakurs,projekt) ";
			$qtxt.= "values ";
			$qtxt.= "('$konto_id','$debet','$faktura','$amount','$bilag','$beskrivelse','1','$groupDateSql','$kladde_id',";
			if ($forfaldsdate) $qtxt.= "'$forfaldsdate','$betal_id',";
			$qtxt.= "'$transdate','$udlign_id','$bilag_id','$valuta','$valutakurs','$projektSql')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$udlignet=1;
			// <-- 20230626
		}
	}
	if ($udlignet<1) {
		if ($faktura=="-") $faktura="";
		$qtxt = "insert into openpost (konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,transdate,kladde_id,";
		if ($forfaldsdate) $qtxt.= "forfaldsdate,betal_id,";
		$qtxt.= "bilag_id,valuta,valutakurs,projekt)";
		$qtxt.= " values ('$konto_id','$debet','$faktura','$amount','$bilag','$beskrivelse','0','$transdate','$kladde_id',";
		if ($forfaldsdate) $qtxt.= "'$forfaldsdate','$betal_id',";
		$qtxt.= "'$bilag_id','$valuta','$valutakurs','$projektSql')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
}
######################################################################################################################################
function valutaopslag($amount, $valuta, $transdate)
{
	global $connection;
	global $fejltext;
	
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
/**
 * Whether a saved journal row holds anything typed besides its date and voucher number: a text, an amount
 * or an invoice number. The date alone does not count, because saving the journal stamps a date on every
 * row (opdater() in kassekladde.php), so a row the user has cleared still has one.
 *
 * @param array $row A kassekladde row.
 * @return bool
 */
function journalRowHasContent($row) {
	return trim((string)$row['beskrivelse']) !== '' || (float)$row['amount'] != 0 || trim((string)$row['faktura']) !== '';
}
######################################################################################################################################
/**
 * Voucher numbers of saved journal rows that have content but neither a debit nor a credit account.
 * Posting skips such rows, so they must be completed or deleted before the journal is simulated or posted.
 * The account test is the same one bogfor() uses to decide whether it posts a row.
 *
 * @param int|string $kladde_id Journal id (kladdeliste.id).
 * @return int[] Distinct voucher numbers in ascending order, empty when no row is unfinished.
 */
function unfinishedJournalVouchers($kladde_id) {
	$vouchers = array();
	$qtxt = "select bilag, debet, kredit, amount, beskrivelse, faktura from kassekladde ";
	$qtxt.= "where kladde_id = " . (int)$kladde_id . " order by bilag";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!$r['debet'] && !$r['kredit'] && journalRowHasContent($r) && !in_array((int)$r['bilag'], $vouchers)) {
			$vouchers[] = (int)$r['bilag'];
		}
	}
	return $vouchers;
}
######################################################################################################################################
/**
 * Message refusing simulation/posting because of unfinished rows, in the database encoding like findtekst().
 * Inside a <script> block print it through jsString(), which also converts it from a non-UTF8 database.
 * bogfor() returns it as well and the posting branch prints that inside onLoad="...", so it holds no
 * double quotes or line breaks.
 *
 * @param int[] $vouchers Voucher numbers from unfinishedJournalVouchers().
 * @return string
 */
function unfinishedJournalMessage($vouchers) {
	global $sprog_id;

	$txt = findtekst('5440|Kladden kan ikke bogføres. Følgende bilag har tekst eller beløb, men hverken debet- eller kreditkonto', $sprog_id);
	$txt.= ': ' . implode(', ', $vouchers) . '. ';
	$txt.= findtekst('5441|Udfyld kontoen, eller slet linjen ved at skrive - i bilagsfeltet.', $sprog_id);
	return $txt;
}
######################################################################################################################################
/**
 * Message for a journal that cannot be simulated or posted because a voucher that Hent ordrer > Flyt til kladde wrote
 * for a foreign-currency sales invoice no longer balances, typically because the rate table's rate for its date was
 * changed after the move.
 *
 * Flyt til kladde (finans/hentordrer.php) writes such an invoice's customer line in the currency (its open item) and
 * the revenue, VAT and rounding lines in DKK, at the rate table's rate of the invoice date, and it moves the invoice only
 * when the voucher balances in DKK. Posting converts the customer line at the rate table's rate of its date as the table
 * stands then. Once that rate is changed, the customer line no longer matches the DKK lines; bogfor() cannot write the
 * difference as currency rounding (only a voucher whose currency amounts net to zero gets that) and refused the whole
 * journal with "Manglende konto til valutadiffencer!" or as unbalanced, which pointed at the currency settings.
 *
 * A voucher counts when the lines of one order (kassekladde.ordre_id) are a customer line in a currency and DKK lines
 * (no other currency line: a voucher Flyt til kladde wrote before 20261119 is in the currency throughout) and they do
 * not balance in DKK at the rate posting uses (journalLineInBaseCurrency(), as postingLineAmounts()). When they balance
 * at the order's rate (ordrer.valutakurs, the rate of the invoice date when it was invoiced), the move was made at that
 * rate and the message names it and the rate now. Otherwise the rate was changed between invoicing and the move too,
 * or the voucher's lines were edited, and the message says that one of the two changed. Either way deleting the
 * voucher's lines and moving the invoice again writes it at the rate now.
 *
 * bogfor() refuses the journal with it, before it writes anything, whenever a voucher (kassekladde.bilag, with every
 * journal line on it) that holds such an order does not balance in DKK on its own ($unbalancedVouchers). The journal's
 * total does not show that when another such voucher is off by the opposite amount, as a moved invoice and its credit
 * note are after the same rate correction, which was posted with each voucher off by its difference (p1-journal-J3h). A voucher
 * whose other lines make up for the order's difference is posted as before, and so is every journal without such a
 * voucher; where bogfor() refuses a journal for another reason, the message names every such order. The message holds
 * no double quotes or line breaks (the posting branch prints it inside onLoad). The preview shows it as text where it
 * lists vouchers with a difference, since Kassekladde's Simulér and Bogfør open the preview, and leaves out the button
 * that posts where bogfor() would refuse.
 *
 * @param int|string $kladde_id Journal id (kladdeliste.id).
 * @param bool $refused true when Simulér or Bogfør refuse the journal: the message ends with "Ingen posteringer er gemt.";
 *                      false for the preview, which has posted nothing.
 * @param bool $unbalancedVouchers true: only the orders whose voucher, with every line on it, does not balance in DKK.
 * @return string The message, naming each such voucher and what to do, or '' when the journal has none.
 */
function movedInvoiceRateMessage($kladde_id, $refused = true, $unbalancedVouchers = false) {
	$orders = array();
	$qtxt = "select k.*, o.valutakurs as ordre_kurs from kassekladde k, ordrer o ";
	$qtxt.= "where k.kladde_id = " . (int)$kladde_id . " and k.ordre_id > 0 and o.id = k.ordre_id order by k.ordre_id, k.id";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!$r['debet'] && !$r['kredit']) continue;
		$o = (int)$r['ordre_id'];
		if (!isset($orders[$o])) {
			$orders[$o] = array('bilag' => $r['bilag'], 'faktura' => $r['faktura'], 'kurs' => (float)$r['ordre_kurs'], 'now' => 0, 'moved' => 0);
			$orders[$o] += array('valuta' => 0, 'rate' => 0, 'transdate' => '', 'customer' => false, 'other' => false, 'dkk' => false, 'vouchers' => array());
		}
		$orders[$o]['vouchers'][(int)$r['bilag']] = 0;
		$sign = ($r['debet'] ? 1 : 0) - ($r['kredit'] ? 1 : 0); # as bogfor() sums a voucher
		if ($r['valuta'] && $r['amount']) {
			list($dkkamount, , $rate) = journalLineInBaseCurrency($r['amount'], $r['valuta'], $r['transdate'], ifset($r, 'ompost_id'));
			$orders[$o]['now'] += $sign * $dkkamount;
			$orders[$o]['moved'] += $sign * afrund($r['amount'] * $orders[$o]['kurs'] / 100, 2);
			$orders[$o]['valuta'] = (int)$r['valuta'];
			$orders[$o]['rate'] = (float)$rate;
			$orders[$o]['transdate'] = $r['transdate'];
			if ((trim((string)$r['d_type']) == 'D' && $r['debet']) xor (trim((string)$r['k_type']) == 'D' && $r['kredit'])) $orders[$o]['customer'] = true;
			else $orders[$o]['other'] = true;
		} else {
			$orders[$o]['now'] += $sign * $r['amount'];
			$orders[$o]['moved'] += $sign * $r['amount'];
			$orders[$o]['dkk'] = true;
		}
	}
	$kurs = static function ($rate) {
		return dkdecimal($rate, (afrund($rate, 2) == $rate) ? 2 : 3);
	};
	foreach ($orders as $o => $order) {
		if (!$order['customer'] || $order['other'] || !$order['dkk'] || !$order['rate'] || !afrund($order['now'], 2)) unset($orders[$o]);
	}
	if ($unbalancedVouchers && $orders) {
		// Every line on the vouchers that hold these orders, summed as bogfor() sums a voucher, at the rate posting uses.
		$voucher = array();
		foreach ($orders as $order) {
			$voucher += $order['vouchers'];
		}
		$qtxt = "select * from kassekladde where kladde_id = " . (int)$kladde_id . " and bilag in (" . implode(',', array_keys($voucher)) . ")";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$sign = ($r['debet'] ? 1 : 0) - ($r['kredit'] ? 1 : 0);
			if (!$sign) continue;
			if ($r['valuta'] && $r['amount']) list($dkkamount) = journalLineInBaseCurrency($r['amount'], $r['valuta'], $r['transdate'], ifset($r, 'ompost_id'));
			else $dkkamount = (float)$r['amount'];
			$voucher[(int)$r['bilag']] += $sign * $dkkamount;
		}
		foreach ($orders as $o => $order) {
			$unbalanced = false;
			foreach (array_keys($order['vouchers']) as $bilag) {
				if (afrund($voucher[$bilag], 2)) $unbalanced = true;
			}
			if (!$unbalanced) unset($orders[$o]);
		}
	}
	$txt = array();
	foreach ($orders as $o) {
		$difference = afrund($o['now'], 2);
		$r = db_fetch_array(db_select("select box1 from grupper where art = 'VK' and kodenr = '" . $o['valuta'] . "'", __FILE__ . " linje " . __LINE__));
		$valuta = trim((string)($r['box1'] ?? ''));
		$dato = dkdato($o['transdate']);
		$faktura = str_replace(array('"', "\r", "\n"), '', (string)$o['faktura']);
		$differenceText = dkdecimal(abs($difference), 2);
		if ($o['kurs'] && !afrund($o['moved'], 2) && abs($o['rate'] - $o['kurs']) >= 0.0005) {
			$line = "Kursen for $valuta den $dato er ændret fra " . $kurs($o['kurs']) . " til " . $kurs($o['rate']) . ", efter at faktura $faktura";
			$line.= " blev flyttet til kladden fra Hent ordrer (bilag " . $o['bilag'] . "), så bilaget ikke længere balancerer i DKK (difference $differenceText).";
		} else {
			$line = "Bilag " . $o['bilag'] . " med faktura $faktura er flyttet til kladden fra Hent ordrer og balancerer ikke længere i DKK med kursen ";
			$line.= $kurs($o['rate']) . " for $valuta den $dato (difference $differenceText): kursen for den dato eller bilagets linjer er ændret, efter at fakturaen blev flyttet.";
		}
		$txt[] = $line;
	}
	if (!$txt) return '';
	if (count($txt) == 1) $txt[] = "Slet bilagets linjer i kladden, så kommer fakturaen tilbage i Hent ordrer, og flyt den til kladden igen.";
	else $txt[] = "Slet bilagenes linjer i kladden, så kommer fakturaerne tilbage i Hent ordrer, og flyt dem til kladden igen.";
	if ($refused) $txt[] = "Ingen posteringer er gemt.";
	return implode(' ', $txt);
}
######################################################################################################################################

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}
?>
