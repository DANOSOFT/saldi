<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------------finans/bogfor.php---------- ver 5.0.1 --- 2026-10-27 ---
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
// 20261021 CL/MJ MB-79 Automatic settlement matched on the currency amount alone, so an invoice and its
//                  payment settled each other even when they were booked at different rates - and the DKK
//                  difference between the rates was left on the debtor/creditor control account. It is now
//                  booked on the currency's difference account, the way manual settlement books it, and a
//                  post only settles against another post in the same currency. openpostRateDifference()
//                  measures the difference without writing anything, so bogfor() books it into simulering
//                  as well as transaktioner and openpost() keeps its "only when not simulating" guard.
// 20261023 CL/LH MB-71: The refusal alert text goes through jsString(), so it is no longer empty when $db_encode isn't UTF8.
// 20261026 CDX/MJ MB-79 Preserve exchange-difference settlement alongside current master journal validation.
// 20261027 CL/LH MB-79 The open-post row for an automatic exchange difference is written the way manual
//                  settlement writes it - 0,001 in the settled currency at a rate worth the DKK difference - so
//                  the account statement of a foreign-currency debtor/creditor no longer shows a currency
//                  balance on a settled account (rateDifferenceOpenpostAmount()).
// 20261027 CL/LH MB-79 Simuler and the confirmation page now book and show the same exchange differences as
//                  Bogfør: openpostRateDifference() keeps track of what the journal's earlier lines would have
//                  written to openpost (openpostOverlay()), so a journal holding an invoice and its payment, or
//                  two payments for one invoice, simulates what it posts, and the confirmation page adds the
//                  differences to its account and debtor/creditor movements (journalRateDifferences()). The
//                  same invoice reference twice ('F-1;F-1') is measured once, as openpost() settles it
//                  (openpostMatches()), and a difference account that is not in the chart of accounts is
//                  refused instead of posted to (rateDifferenceAccountError()).
// 20261027 CL/LH MB-79 When the journal also has in-voucher currency rounding, the confirmation page puts it on
//                  the difference account's row instead of a row without an account number
//                  (confirmationRoundingOnListedAccount()), so that row shows the account's new balance.


require_once dirname(__DIR__, 1) . '/includes/assist/RecordRules.php';

@session_start();
$s_id=session_id();

$fejltext=NULL;$valutadiff=0;
$tjeksum=array();
$css="../css/standard.css";

$modulnr=2;
$title="Bogfør kassekladde";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/genberegn.php");
include("../includes/topline_settings.php");

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
		if ($periode_fejl) {
			print "<BODY onLoad=\"javascript:alert('" . addslashes($periode_fejl) . "')\">";
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
				$bogfor = false;
				print "<BODY onLoad=\"javascript:alert('" . addslashes($postingError ?: "Bogfoering fejlede - databasen afviste transaktionen. Ingen posteringer er gemt.") . "')\">";
			} else {
				transaktion('commit');
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
				list($dkkamount[$posteringer],$diffkonto,$valutakurs)=valutaopslag($amount[$posteringer],$row['valuta'],$row['transdate']);
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
// 20261027 CL/LH MB-79 The exchange differences posting books when a line settles a foreign-currency post
// booked at another rate are added to the account and debtor/creditor movements below, so this page shows
// what Simuler and Bogfør write. One that cannot be booked stops the journal here instead of when posting.
$rateDifferences=journalRateDifferences($kladde_id);
foreach ($rateDifferences as $i => $rateDifference) {
	$rateDifferenceError=rateDifferenceAccountError($rateDifference['diffkonto'],$rateDifference['bilag']);
	if ($rateDifferenceError) {
		$fejltext="OBS: " . htmlspecialchars($rateDifferenceError, ENT_QUOTES, $charset);
		unset($rateDifferences[$i]);
	}
}
$kontoantal=0;
$kontoliste=array()	;
foreach ($rateDifferences as $rateDifference) {
	foreach (array($rateDifference['debetkonto'],$rateDifference['kreditkonto']) as $konto) {
		if (!in_array($konto, $kontoliste)) {
			$kontoantal++;
			$kontoliste[$kontoantal]=$konto;
		}
	}
}
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
	foreach ($rateDifferences as $rateDifference) {
		if ($kontoliste[$y]==$rateDifference['debetkonto']) $kontodebet[$y]+=$rateDifference['belob'];
		if ($kontoliste[$y]==$rateDifference['kreditkonto']) $kontokredit[$y]+=$rateDifference['belob'];
	}
}
// 20261027 CL/LH MB-79 An exchange difference gives the currency's difference account a row above. The in-voucher
// currency rounding further down then goes on that row: printed as a row of its own it had no account number,
// raised PHP warnings and showed a new balance that left out the exchange difference.
$diff=afrund($debetsum-$kreditsum,3);
if (abs($diff) < $valutaposter/100) $valutadiff=$diff;
$roundingOnListedAccount=false;
if ($valutadiff && abs($valutadiff) < $valutaposter/100 && isset($diffkonto)) {
	$roundingOnListedAccount=confirmationRoundingOnListedAccount($kontoliste,$kontodebet,$kontokredit,$diffkonto,$valutadiff);
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

if ($roundingOnListedAccount) {
	// Already on the difference account's row and in the totals above.
	$diff=$diff-$valutadiff;
} elseif ($valutadiff && abs($valutadiff) < $valutaposter/100) {
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
	#$fejl=1;
	if (abs($diff)>=0.01) $fejl=1;
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
if (!$fejl) { #20140228
	$query = db_select("select * from kladdeliste where id = $kladde_id and bogfort = 'V'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		print "Kladden er bogført!";
		genberegn($regnaar);
		exit;	
	}
	for ($x=1;$x<=$debitorantal;$x++) {
		$debitordebet[$x]=0;
		$debitorkredit[$x]=0;
		for ($y=1;$y<=$posteringer;$y++) {
			if (isset($d_debitor[$y]) && $debitor[$x]==$d_debitor[$y]) $debitordebet[$x]+=$dkkamount[$y];   
			if (isset($k_debitor[$y]) && $debitor[$x]==$k_debitor[$y]) $debitorkredit[$x]+=$dkkamount[$y];  
		}
		// 20261027 CL/LH MB-79 A difference that leaves a debit on the control account is credited to it, and to the debtor.
		foreach ($rateDifferences as $rateDifference) {
			if (strstr($rateDifference['art'],'D') && $debitor[$x]==$rateDifference['konto']) {
				if ($rateDifference['kursdiff'] > 0) $debitorkredit[$x]+=$rateDifference['belob'];
				else $debitordebet[$x]+=$rateDifference['belob'];
			}
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
		foreach ($rateDifferences as $rateDifference) {
			if (strstr($rateDifference['art'],'K') && $kreditor[$x]==$rateDifference['konto']) {
				if ($rateDifference['kursdiff'] > 0) $kreditorkredit[$x]+=$rateDifference['belob'];
				else $kreditordebet[$x]+=$rateDifference['belob'];
			}
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
		if (($sqdb=="saldi" || $sqdb=='gratis'|| $sqdb=='develop') && $max_posteringer && $max_posteringer<=8500 && $d_sum>0) {
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
	
	$r=db_fetch_array(db_select("select max(id) as id from transaktioner where kladde_id = $kladde_id",__FILE__ . " linje " . __LINE__));
	if ($r['id']) {
		print "<BODY onLoad=\"javascript:alert('Kladden er allerede bogført!')\">";
		return("Kladden er allerede bogført");
		exit;
	}
	// Refuse before anything is written: an unfinished row would otherwise be skipped and lost.
	$unfinishedVouchers = unfinishedJournalVouchers($kladde_id);
	if ($unfinishedVouchers) return unfinishedJournalMessage($unfinishedVouchers);

	$d_momsart=array(); $k_momsart=array();
	if ($kladdenote) db_modify("update kladdeliste set kladdenote = '$kladdenote' where id = '$kladde_id'",__FILE__ . " linje " . __LINE__);
	$y=0;
	$v_antal=0;
	$b_diff=$bv_diff=0;
	$openpostOverlay=($simuler) ? openpostOverlay() : NULL;
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
			// 20261021 CL/MJ MB-79 The exchange difference is measured before openpost() settles anything and
			// booked here, where $tabel already chooses between transaktioner and simulering - so Simuler shows
			// the row posting will write, without the open-post writer running during a simulation. The control
			// account comes from $line, which is where this line's own debtor/creditor posting goes, so the pair
			// clears exactly what it left behind.
			// 20261027 CL/LH MB-79 The debit side, then the credit side, as before (journalLineOpenpostSides()).
			// Simuler measures against $openpostOverlay, which records what openpost() would have written for
			// the journal's earlier lines, so a journal holding an invoice and its payment simulates the same
			// difference posting books, and two payments for one invoice do not both measure it.
			foreach (journalLineOpenpostSides($row,$line) as $side) {
				$kursdiff=openpostRateDifference($side['art'], $side['konto'], $faktura[$y], $side['amount'], $valuta[$y], $valutakurs[$y], $projekt[$y], $openpostOverlay);
				if ($kursdiff) {
					bookRateDifference($kursdiff, $side['samlekonto'], $diffkonto[$y], $tabel, $kladde_id, $row, openpostCurrencyLetters($valuta[$y]));
				}
				if (!$simuler) openpost($side['art'], $side['konto'], $bilag[$y], $faktura[$y], $side['amount'], $beskrivelse[$y], $transdate[$y], $postid[$y], $valuta[$y], $valutakurs[$y], $forfaldsdate[$y], $betal_id[$y],$projekt[$y]);
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
// 20261021 CL/MJ MB-79 From here down to openpost(): the first five are the pieces openpost() matches
// with, split out so openpostRateDifference() can ask the same question read-only - for Simuler, and for
// the difference bogfor() books - leaving one matcher, so the measurement and the settlement cannot drift
// apart. The last three are new: the read-only question, the posting text, and the two ledger rows.
// 20261027 CL/LH MB-79 A fourth new one, rateDifferenceOpenpostAmount(), gives the open-post row's figures.
// 20261027 CL/LH MB-79 openpostMatches() is now the one matcher, used by openpost() and openpostRateDifference()
// alike; openpostOverlay(), openpostOverlayMatch() and openpostOverlayRecord() stand in for openpost during
// Simuler; journalLineOpenpostSides() and journalRateDifferences() give the confirmation page the same
// differences; rateDifferenceAccounts() and rateDifferenceAccountError() are shared with bookRateDifference().
// 20261027 CL/LH MB-79 confirmationRoundingOnListedAccount() puts the page's currency rounding on that row.
/**
 * Resolves a cash-journal currency code to the letters stored in openpost.valuta.
 *
 * @param string|int $valutakode grupper art='VK' kodenr; 0 or '' is the installation's own currency.
 * @return string                The currency letters (grupper box1), 'DKK', or '' when the code has no VK row.
 */
function openpostCurrencyLetters($valutakode) {
	if (!$valutakode) {
		return 'DKK';
	}
	$r = db_fetch_array(db_select("select box1 from grupper where art = 'VK' and kodenr = '" . (int)$valutakode . "'",__FILE__ . " linje " . __LINE__));
	return $r ? (string)$r['box1'] : '';
}
/**
 * The open posts of one debtor or creditor number.
 *
 * @param string|int $debet Debtor/creditor number, spaces already removed by the caller.
 * @param string     $art   'D'/'K' and their variants, as postingLineAmounts() resolved the line's type.
 * @return string
 */
function openpostAccountQuery($debet,$art) {
	return "select id from adresser where kontonr = '" . db_escape_string((string)$debet) . "' and art ='" . db_escape_string((string)$art) . "'";
}
/**
 * The invoice references one posting settles against. A single posting can carry several, separated
 * by ';' - openpost() then drops the amount window, because no one reference matches the whole figure.
 *
 * @param string $faktura
 * @return array<int,string>
 */
function openpostInvoiceList($faktura) {
	if (strpos($faktura,";")) {
		return explode(';',$faktura);
	}
	return array($faktura);
}
/**
 * The query openpost() uses to find the open post a new posting settles.
 *
 * @param string|int $konto_id     adresser.id of the debtor/creditor.
 * @param string     $projekt      Project number; a post only settles within the same project (20121122).
 * @param string     $faktnr       One invoice reference, already escaped by bogfor().
 * @param string     $valuta       Currency letters of the new posting; '' is read as DKK.
 * @param float      $belob        The counter-amount to look for ($amount*-1).
 * @param bool       $amountWindow Apply the +/-0,005 window; only for a single invoice reference.
 * @param array      $excludeIds   openpost ids already taken by this posting or, during Simuler, settled by an
 *                                 earlier line of the journal (openpostMatches()). Non-numeric ids are the
 *                                 journal's own, not yet written posts and are left out of the query.
 * @return string
 */
function openpostMatchQuery($konto_id,$projekt,$faktnr,$valuta,$belob,$amountWindow,$excludeIds=array()) {
	// MB-79 Without the currency condition a DKK payment settles a foreign-currency post that happens to
	// carry the same invoice number and figure. Rows written before openpost.valuta existed have it NULL and
	// includes/ret_valutadiff.php writes '-', so an empty value is read as DKK the way
	// includes/udlign_openpost.php reads it - otherwise an ordinary DKK settlement would stop working.
	$matchValuta = ($valuta === '' || $valuta === NULL) ? 'DKK' : $valuta;
	$qtxt = "select id,transdate,amount,valutakurs from openpost where konto_id='" . (int)$konto_id . "'";
	$qtxt.= " and projekt='" . db_escape_string((string)$projekt) . "' and udlignet!='1'";
	// $faktnr arrives escaped from bogfor(); escaping it again would double any quote and lose the match.
	$qtxt.= " and faktnr='$faktnr'";
	$qtxt.= " and coalesce(nullif(valuta,''),'DKK')='" . db_escape_string($matchValuta) . "'";
	if ($amountWindow) {
		$qtxt.= " and amount >= '" . ($belob-0.005) . "' and amount < '" . ($belob+0.005) . "'";
	}
	// 20261027 CL/LH MB-79 The same reference twice ('F-1;F-1') must not find the same post twice, and with
	// several candidates openpost() and openpostRateDifference() must pick the same one, so the order is fixed.
	$excludeIds = array_map('intval', array_filter($excludeIds, 'is_numeric'));
	if ($excludeIds) {
		$qtxt.= " and id not in (" . implode(',', $excludeIds) . ")";
	}
	$qtxt.= " order by id";
	return $qtxt;
}
/**
 * The open posts one posting settles: for each invoice reference the first open post openpostMatchQuery()
 * finds, never the same post twice. openpost() settles exactly these and openpostRateDifference() measures
 * exactly these, so the difference booked on the ledger is the one written on the open-post ledger.
 *
 * Simuler writes nothing to openpost, so there $overlay carries what the journal's earlier lines would have
 * written (openpostOverlayRecord()): the stored posts they settled are skipped, and the posts they opened can
 * be settled after the stored ones, which have the lower ids.
 *
 * @param string|int        $konto_id adresser.id of the debtor/creditor.
 * @param string            $projekt
 * @param array<int,string> $invoices From openpostInvoiceList(), already escaped by bogfor().
 * @param string            $valuta   Currency letters of the new posting.
 * @param float             $belob    The counter-amount to look for ($amount*-1).
 * @param array|null        $overlay  From openpostOverlay() during Simuler, NULL when posting for real.
 * @return array<int, array{id: int|string, amount: float, valutakurs: float}> A stored match also carries
 *                                                                          its transdate.
 */
function openpostMatches($konto_id,$projekt,$invoices,$valuta,$belob,$overlay=NULL) {
	$matches = array();
	$taken = is_array($overlay) ? array_keys($overlay['settled']) : array();
	$amountWindow = (count($invoices) == 1);
	foreach ($invoices as $invoice) {
		$qtxt = openpostMatchQuery($konto_id,$projekt,$invoice,$valuta,$belob,$amountWindow,$taken);
		$match = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if (!$match && is_array($overlay)) {
			$match = openpostOverlayMatch($overlay,$konto_id,$projekt,$invoice,$valuta,$belob,$amountWindow,$taken);
		}
		if ($match) {
			$matches[] = $match;
			$taken[] = $match['id'];
		}
	}
	return $matches;
}
/**
 * An empty record of what a simulated journal has written to openpost so far.
 *
 * @return array{
 *   settled: array<int,bool>,     Stored openpost ids the journal's lines have settled.
 *   open: array<string,array>,    Posts the journal's lines have opened, keyed by 'j<n>', in line order.
 *   next: int,                    The number of the next opened post.
 * }
 */
function openpostOverlay() {
	return array('settled' => array(), 'open' => array(), 'next' => 1);
}
/**
 * The first post a simulated journal has opened that openpostMatchQuery() would find, if it had been written.
 *
 * @param array             $overlay      From openpostOverlay().
 * @param string|int        $konto_id
 * @param string            $projekt
 * @param string            $faktnr       One invoice reference, escaped the way the post was recorded.
 * @param string            $valuta       Currency letters; '' is read as DKK.
 * @param float             $belob        The counter-amount to look for.
 * @param bool              $amountWindow Apply the +/-0,005 window.
 * @param array<int,mixed>  $taken        Ids this posting has already matched.
 * @return array|null
 */
function openpostOverlayMatch($overlay,$konto_id,$projekt,$faktnr,$valuta,$belob,$amountWindow,$taken) {
	$matchValuta = ($valuta === '' || $valuta === NULL) ? 'DKK' : $valuta;
	foreach ($overlay['open'] as $post) {
		if (in_array($post['id'], $taken, true) || $post['konto_id'] != $konto_id || $post['projekt'] !== (string)$projekt) {
			continue;
		}
		if ($post['faktnr'] !== $faktnr || $post['valuta'] !== $matchValuta) {
			continue;
		}
		if ($amountWindow && ($post['amount'] < $belob-0.005 || $post['amount'] >= $belob+0.005)) {
			continue;
		}
		return $post;
	}
	return NULL;
}
/**
 * Records in $overlay what openpost() writes for one posting: the posts it settles, or else the new open post.
 *
 * @param array      $overlay    From openpostOverlay(), changed in place.
 * @param string|int $konto_id
 * @param string     $projekt
 * @param string     $faktura    The posting's invoice reference(s), escaped by bogfor().
 * @param string     $valuta     Currency letters.
 * @param float      $amount     Currency amount in openpost's sign convention.
 * @param float      $valutakurs
 * @param array      $matches    From openpostMatches().
 * @return void
 */
function openpostOverlayRecord(&$overlay,$konto_id,$projekt,$faktura,$valuta,$amount,$valutakurs,$matches) {
	foreach ($matches as $match) {
		if (isset($overlay['open'][$match['id']])) {
			unset($overlay['open'][$match['id']]);
		} else {
			$overlay['settled'][$match['id']] = true;
		}
	}
	if (!$matches) {
		$id = 'j' . $overlay['next']++;
		$overlay['open'][$id] = array(
			'id'         => $id,
			'konto_id'   => $konto_id,
			'projekt'    => (string)$projekt,
			'faktnr'     => ($faktura == "-") ? "" : (string)$faktura,
			'valuta'     => ($valuta === '' || $valuta === NULL) ? 'DKK' : $valuta,
			'amount'     => afrund($amount,3),
			'valutakurs' => $valutakurs,
		);
	}
}
/**
 * The DKK difference the exchange rates leave behind when $amount settles $matches.
 *
 * Each row's DKK value is amount*valutakurs/100 rounded to two decimals, and the difference is their
 * sum - the same arithmetic manual settlement does in includes/udlign_openpost.php. It is positive when
 * the settled group leaves a debit on the control account.
 *
 * Returns 0,00 unless this is a foreign-currency settlement whose currency amounts cancel and whose rates
 * actually differ, which is the only case where that sum is a rate difference rather than an ordinary
 * residual. A row with no rate at all says nothing about what it was worth in DKK, so nothing is derived
 * from it either - openpost.valuta and openpost.valutakurs were added to the table together, and a row
 * missing one is read as the settlement it was before MB-79 rather than as a rate of nothing, which would
 * turn the whole amount into a difference.
 *
 * @param float  $amount     The new posting's currency amount, in openpost's sign convention.
 * @param float  $valutakurs Its rate, in hundredths.
 * @param array  $matches    Rows from openpostMatchQuery(); amount and valutakurs are read.
 * @param string $valuta     Currency letters; DKK, or none, is never a rate difference.
 * @return float             The difference in DKK, or 0.0.
 */
function openpostRateDifferenceAmount($amount,$valutakurs,$matches,$valuta) {
	if (!$matches || $valuta === '' || $valuta === NULL || $valuta === 'DKK') {
		return 0.0;
	}
	$kurs = (float)$valutakurs;
	if (!$kurs) {
		return 0.0;
	}
	$dkkdiff = afrund($amount*$kurs/100,2);
	$residual = (float)$amount;
	$ratesDiffer = false;
	foreach ($matches as $match) {
		$matchKurs = (float)$match['valutakurs'];
		if (!$matchKurs) {
			return 0.0;
		}
		if ($matchKurs != $kurs) {
			$ratesDiffer = true;
		}
		$dkkdiff += afrund($match['amount']*$matchKurs/100,2);
		$residual += (float)$match['amount'];
	}
	if (!$ratesDiffer || abs($residual) >= 0.005 || abs($dkkdiff) < 0.01) {
		return 0.0;
	}
	return afrund($dkkdiff,2);
}
/**
 * What openpost() would settle for this journal line, measured without writing anything.
 *
 * bogfor() calls this for every debtor/creditor line, simulating or not, and books whatever comes back.
 * Only one adresser row is read, because openpost() only ever reaches one.
 *
 * Posting for real, the journal's earlier lines have already been written to openpost when a line is
 * measured. Simuler writes nothing there, so it passes $overlay and this records the line in it the way
 * openpost() would write it: a journal holding an invoice and its payment, or two payments for one invoice,
 * then simulates the differences posting books (20261027 CL/LH MB-79). A DKK line is not recorded, because
 * it can never settle a foreign-currency post or be settled by one.
 *
 * @param string     $art        The line's debtor/creditor type.
 * @param string|int $debet      Debtor/creditor number.
 * @param string     $faktura    Invoice reference(s), already escaped by bogfor().
 * @param float      $amount     Currency amount in openpost's sign convention.
 * @param string|int $valutakode grupper art='VK' kodenr.
 * @param float      $valutakurs The line's rate, in hundredths.
 * @param string     $projekt
 * @param array|null $overlay    From openpostOverlay() during Simuler and for the confirmation page, changed
 *                               in place; NULL when posting for real.
 * @return float                 The DKK exchange difference, or 0.0 when there is none.
 */
function openpostRateDifference($art,$debet,$faktura,$amount,$valutakode,$valutakurs,$projekt,&$overlay=NULL) {
	// openpost() writes nothing for a zero amount, so it settles nothing either.
	if (abs((float)$amount) < 0.005) {
		return 0.0;
	}
	$valuta = openpostCurrencyLetters($valutakode);
	if ($valuta === 'DKK' || $valuta === '') {
		return 0.0;
	}
	$belob = $amount*-1;
	$debet = str_replace(" ","",$debet);
	$q = db_select(openpostAccountQuery($debet,$art),__FILE__ . " linje " . __LINE__);
	if (!($row = db_fetch_array($q))) {
		return 0.0;
	}
	$matches = openpostMatches($row['id'],$projekt,openpostInvoiceList($faktura),$valuta,$belob,$overlay);
	if (is_array($overlay)) {
		openpostOverlayRecord($overlay,$row['id'],$projekt,$faktura,$valuta,$amount,$valutakurs,$matches);
	}
	return openpostRateDifferenceAmount($amount,$valutakurs,$matches,$valuta);
}
/**
 * The debtor/creditor sides of one journal line that open or settle a post, with what bogfor() hands
 * openpost() for each: the debit side when its type holds D or K, the credit side when its type is D or K.
 *
 * @param array $row  A kassekladde row.
 * @param array $line postingLineAmounts($row).
 * @return array<string, array{
 *   art: string,         The side's debtor/creditor type.
 *   konto: string,       Debtor/creditor number.
 *   amount: float,       Currency amount in openpost's sign convention: the line amount, negated on the credit side.
 *   samlekonto: string,  The control account the side's own posting goes to.
 * }>                     Keyed 'debet' and 'kredit', debit side first.
 */
function journalLineOpenpostSides($row,$line) {
	$sides = array();
	if ((strstr((string)$line['d_type'],'D') || strstr((string)$line['d_type'],'K')) && $row['debet'] > 0) {
		$sides['debet'] = array('art' => $line['d_type'], 'konto' => $row['debet'], 'amount' => $row['amount'], 'samlekonto' => $line['debet']);
	}
	if (($line['k_type'] == 'D' || $line['k_type'] == 'K') && $row['kredit'] > 0) {
		$sides['kredit'] = array('art' => $line['k_type'], 'konto' => $row['kredit'], 'amount' => $row['amount']*-1, 'samlekonto' => $line['kredit']);
	}
	return $sides;
}
/**
 * The exchange differences posting this journal would book, worked out without writing anything, for the
 * confirmation page Simuler and Bogfør show first. It walks the journal in bogfor()'s order and measures each
 * line the way Simuler does, so the page lists the rows Simuler and Bogfør write.
 *
 * Only lines in a foreign currency are read: a DKK line never settles a foreign-currency post. The posting
 * rules print an alert for a setup error; the page shows those errors itself, so they are not repeated here.
 *
 * @param int|string $kladde_id
 * @return array<int, array{
 *   bilag: string,        Voucher of the line that settles.
 *   art: string,          The side's debtor/creditor type.
 *   konto: string,        Debtor/creditor number.
 *   kursdiff: float,      The difference, positive when it leaves a debit on the control account.
 *   diffkonto: string,    The currency's difference account, '' when none is set up.
 *   debetkonto: string,   The account bookRateDifference() debits.
 *   kreditkonto: string,  The account it credits.
 *   belob: float,         The amount of both rows.
 * }>
 */
function journalRateDifferences($kladde_id) {
	$overlay = openpostOverlay();
	$differences = array();
	$q = db_select("select * from kassekladde where kladde_id = " . (int)$kladde_id . " order by bilag",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)) {
		if (!$row['valuta'] || abs((float)$row['amount']) < 0.005 || (!$row['debet'] && !$row['kredit'])) {
			continue;
		}
		ob_start();
		$line = postingLineAmounts($row, true);
		ob_end_clean();
		$valutakurs = $line['valutakurs'] ? $line['valutakurs'] : 100;
		foreach (journalLineOpenpostSides($row,$line) as $side) {
			$kursdiff = openpostRateDifference($side['art'], $side['konto'], db_escape_string($row['faktura']), $side['amount'], $row['valuta']*1, $valutakurs, $row['projekt'], $overlay);
			if ($kursdiff) {
				list($debetkonto,$kreditkonto,$belob) = rateDifferenceAccounts($kursdiff,$side['samlekonto'],$line['diffkonto']);
				$differences[] = array(
					'bilag'       => $row['bilag'],
					'art'         => $side['art'],
					'konto'       => $side['konto'],
					'kursdiff'    => $kursdiff,
					'diffkonto'   => (string)$line['diffkonto'],
					'debetkonto'  => $debetkonto,
					'kreditkonto' => $kreditkonto,
					'belob'       => $belob,
				);
			}
		}
	}
	return $differences;
}
/**
 * Puts the journal's in-voucher currency rounding on the confirmation page's row for the currency's
 * difference account when that account already has a row, as it does when the journal books an exchange
 * difference there. The page then shows the account once, with its whole movement and new balance.
 * Without such a row the page prints the rounding on a row of its own, as before.
 *
 * @param array<int,string|int> $kontoliste  The page's accounts, after sort().
 * @param array<int,float>      $kontodebet  Debit per account, changed in place.
 * @param array<int,float>      $kontokredit Credit per account, changed in place.
 * @param string|int|null       $diffkonto   The currency's difference account.
 * @param float                 $valutadiff  The rounding; positive is credited to the difference account.
 * @return bool                              Whether the rounding went on an existing row.
 */
function confirmationRoundingOnListedAccount($kontoliste,&$kontodebet,&$kontokredit,$diffkonto,$valutadiff) {
	if (!$valutadiff || !($diffkonto > 0)) {
		return false;
	}
	foreach ($kontoliste as $i => $konto) {
		if ($konto == $diffkonto) {
			if ($valutadiff > 0) {
				$kontokredit[$i]+=$valutadiff;
			} else {
				$kontodebet[$i]-=$valutadiff;
			}
			return true;
		}
	}
	return false;
}
/**
 * The posting text an exchange difference carries, in both the ledger and the open-post ledger, worded
 * the way manual settlement words it so the two are recognisable as the same thing.
 *
 * @param string $valuta  Currency letters.
 * @param float  $amount  The settled figure in that currency.
 * @param float  $dkkdiff The difference in DKK.
 * @return string
 */
function rateDifferenceText($valuta,$amount,$dkkdiff) {
	return "Udligning af valutadiff ($valuta " . dkdecimal(abs($amount)) . ", DKK " . dkdecimal($dkkdiff) . ")";
}
/**
 * The amount and rate of the open-post row that balances an automatic exchange difference.
 *
 * Written the way includes/alignOpenpostIncludes/doAlign.php writes it after a manual settlement whose
 * currency amounts cancel: 0,001 in the settled currency, at the rate that makes it worth the DKK
 * difference. The account statement (includes/reportFunc/accountChart.php, debitor/generalLedger.php)
 * shows that as 0,00 in the account's currency and the difference in DKK. A row in DKK at rate 100 is
 * worth the same in DKK, but the statement converts it into the account's currency at the day's rate,
 * so a settled EUR account would show a EUR balance and offer a rate regulation.
 *
 * @param float $dkkdiff The difference from openpostRateDifferenceAmount(); positive when the settled group
 *                       leaves a debit on the control account.
 * @return array{
 *   0: string,  amount: -0,001 when $dkkdiff is positive, 0,001 otherwise, so amount*valutakurs/100 is -$dkkdiff.
 *   1: float,   valutakurs: abs($dkkdiff)/0,001*100.
 * }
 */
function rateDifferenceOpenpostAmount($dkkdiff) {
	$amount = ($dkkdiff > 0) ? '-0.001' : '0.001';
	return array($amount, afrund(abs($dkkdiff)*100000,3));
}
/**
 * Which account an exchange difference debits and which it credits: a difference that leaves a debit on the
 * control account is a loss, booked on the currency's difference account against the control account; the
 * other way round it is a gain.
 *
 * @param float      $dkkdiff    From openpostRateDifferenceAmount().
 * @param string|int $samlekonto The line's control account.
 * @param string|int $diffkonto  The currency's difference account.
 * @return array{
 *   0: string|int,  The account debited.
 *   1: string|int,  The account credited.
 *   2: float,       The amount, rounded to two decimals.
 * }
 */
function rateDifferenceAccounts($dkkdiff,$samlekonto,$diffkonto) {
	$dkkdiff=afrund($dkkdiff,2);
	if ($dkkdiff > 0) {
		return array($diffkonto, $samlekonto, abs($dkkdiff));
	}
	return array($samlekonto, $diffkonto, abs($dkkdiff));
}
/**
 * Why an exchange difference cannot be booked on the currency's difference account, or '' when it can.
 * The account has to be set up on the currency and exist as a posting account in the fiscal year.
 *
 * @param string|int $diffkonto The currency's difference account (grupper art='VK' box3).
 * @param string|int $bilag     The voucher, named in the message.
 * @return string               The message in the database encoding, or ''.
 */
function rateDifferenceAccountError($diffkonto,$bilag) {
	global $regnaar, $sprog_id;

	$bilag = (string)$bilag;
	if (!$diffkonto) {
		return "Manglende konto til valutadiffencer! (bilag: $bilag)";
	}
	$qtxt = "select id from kontoplan where kontonr='" . db_escape_string((string)$diffkonto) . "'";
	$qtxt.= " and regnskabsaar='" . (int)$regnaar . "' and kontotype != 'H' and kontotype != 'Z'";
	if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$txt = findtekst('723|Difference konto <variable> eksisterer ikke', $sprog_id);
		return str_replace('<variable>', (string)$diffkonto, $txt) . " (bilag: $bilag)";
	}
	return '';
}
/**
 * Books a DKK exchange difference from an automatic settlement: the currency's difference account
 * against the debtor/creditor control account, which is where manual settlement puts it
 * (includes/alignOpenpostIncludes/doAlign.php). It writes to $tabel, so Simuler shows the same row.
 *
 * @param float      $dkkdiff    Positive when the settled group leaves a debit on the control account.
 * @param string|int $samlekonto The line's control account, as postingLineAmounts() resolved it.
 * @param string|int $diffkonto  The currency's difference account (grupper art='VK' box3).
 * @param string     $tabel      'transaktioner' or 'simulering'.
 * @param int        $kladde_id
 * @param array      $row        The cash-journal line being posted.
 * @param string     $valuta     Currency letters, for the posting text.
 * @return void
 */
function bookRateDifference($dkkdiff,$samlekonto,$diffkonto,$tabel,$kladde_id,$row,$valuta) {
	// 20131028 further up refuses a voucher whose currency difference has nowhere to go. A settlement
	// difference has the same problem: posting it to account '' would strand it without saying so.
	// 20261027 CL/LH MB-79 So has an account that is not in the chart of accounts: the rows were committed to it.
	$accountError=rateDifferenceAccountError($diffkonto,$row['bilag']);
	if ($accountError) {
		transaktion('rollback');
		print "<script>alert(" . jsString(array($accountError)) . ");</script>";
		exit;
	}
	$dkkdiff=afrund($dkkdiff,2);
	list($debetkonto,$kreditkonto,$belob)=rateDifferenceAccounts($dkkdiff,$samlekonto,$diffkonto);
	$beskrivelse=db_escape_string(rateDifferenceText($valuta,$row['amount'],$dkkdiff));
	$bilag=db_escape_string((string)$row['bilag']);
	$transdate=db_escape_string((string)$row['transdate']);
	$faktura=db_escape_string((string)$row['faktura']);
	$afd=db_escape_string((string)($row['afd'] ? $row['afd'] : 0));
	$ansat=(int)$row['ansat'];
	$projekt=db_escape_string((string)$row['projekt']);
	$ordre_id=(int)$row['ordre_id'];
	$logdate=date("Y-m-d");
	$logtime=date("H:i");
	// The difference is already in DKK, so the row carries no rate of its own - the same way the
	// in-voucher currency rounding above is written with 'DKK','100'.
	foreach (array(array($debetkonto,$belob,0), array($kreditkonto,0,$belob)) as $postering) {
		$qtxt = "insert into $tabel ";
		$qtxt.= "(kontonr,bilag,transdate,logdate,logtime,beskrivelse,debet,kredit,faktura,kladde_id,afd,ansat,projekt,";
		$qtxt.= "valuta,valutakurs,ordre_id,moms)";
		$qtxt.= " values ";
		$qtxt.= "('" . db_escape_string((string)$postering[0]) . "','$bilag','$transdate','$logdate','$logtime','$beskrivelse',";
		$qtxt.= "'$postering[1]','$postering[2]','$faktura','" . (int)$kladde_id . "','$afd','$ansat','$projekt','DKK',";
		$qtxt.= "'100','$ordre_id','0')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
}
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
	$valuta=openpostCurrencyLetters($valutakode);
	$udlignet=0;
	$udlign_date=$transdate;
	$dato=date("Y-m-d");
	$belob=$amount*-1;
	$debet=str_replace(" ","",$debet);
	$q = db_select(openpostAccountQuery($debet,$art),__FILE__ . " linje " . __LINE__);
	// 20261027 CL/LH MB-79 Only the first account row, as before: the old while() reused $q for the match
	// query, so it ended after the first row - unless a match query had found a second post, when it went on
	// with that openpost row as if it were an address. openpostMatches() leaves $q alone.
	if ($row = db_fetch_array($q)) {
		$konto_id=$row['id'];
		$query = db_select("select MAX(udlign_id) as udlign_id from openpost",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) $udlign_id=$row['udlign_id']+1;
# -> 2009.05.04		
		$invoices=openpostInvoiceList($faktura);
		
# 20121122 >>and projekt='$projekt'<< indsat herunder.
		if ($udlign_date<$transdate) $udlign_date=$transdate;
		// 20261021 CL/MJ MB-79 Collected so the rate difference is measured over the whole settled group:
		// a posting carrying several invoice references settles them all, and only their sum tells whether
		// the currency amounts cancelled.
		// 20261027 CL/LH MB-79 Found by openpostMatches(), the same lookup openpostRateDifference() measures.
		$matches=openpostMatches($konto_id,$projekt,$invoices,$valuta,$belob);
		foreach ($matches as $r) {
# $udlign_date infort 2011.02.22 -udligningsdato skal altid vaere seneste dato. 
			$udlign_date=$r['transdate'];
			$qtxt = "update openpost set udlignet = '1',udlign_date= '$udlign_date',udlign_id='$udlign_id' ";
			$qtxt.= "where id = '$r[id]'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			// 20230626 -->
			$qtxt = "insert into openpost
			(konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,udlign_date,kladde_id,";
			if ($forfaldsdate) $qtxt.= "forfaldsdate,betal_id,";
			$qtxt.= "transdate,udlign_id,bilag_id,valuta,valutakurs,projekt) ";
			$qtxt.= "values ";
			$qtxt.= "('$konto_id','$debet','$faktura','$amount','$bilag','$beskrivelse','1','$udlign_date','$kladde_id',";
			if ($forfaldsdate) $qtxt.= "'$forfaldsdate','$betal_id',";
			$qtxt.= "'$transdate','$udlign_id','$bilag_id','$valuta','$valutakurs','$projekt')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$udlignet=1;
			// <-- 20230626
		}
		// 20261021 CL/MJ MB-79 doAlign.php writes this row after a manual settlement so the settled group's
		// DKK value (amount*valutakurs/100) nets to zero against the difference booked on the control
		// account, and the two ledgers agree on what is left.
		// 20261027 CL/LH MB-79 It is now written in the settled currency the way doAlign.php writes it, not in DKK
		// at rate 100, which the account statement converted into a currency balance (rateDifferenceOpenpostAmount()).
		$kursdiff=openpostRateDifferenceAmount($amount,$valutakurs,$matches,$valuta);
		if ($kursdiff) {
			$kursdiffbesk=db_escape_string(rateDifferenceText($valuta,$amount,$kursdiff));
			list($kursdiffamount,$kursdiffkurs)=rateDifferenceOpenpostAmount($kursdiff);
			$qtxt = "insert into openpost (konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,udlign_date,";
			$qtxt.= "kladde_id,transdate,udlign_id,bilag_id,valuta,valutakurs,projekt)";
			$qtxt.= " values ";
			$qtxt.= "('$konto_id','$debet','$faktura','$kursdiffamount','$bilag','$kursdiffbesk','1','$udlign_date',";
			$qtxt.= "'$kladde_id','$transdate','$udlign_id','$bilag_id','" . db_escape_string($valuta) . "','$kursdiffkurs','$projekt')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
	if ($udlignet<1) {
		if ($faktura=="-") $faktura="";
		$qtxt = "insert into openpost (konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,transdate,kladde_id,";
		if ($forfaldsdate) $qtxt.= "forfaldsdate,betal_id,";
		$qtxt.= "bilag_id,valuta,valutakurs,projekt)";
		$qtxt.= " values ('$konto_id','$debet','$faktura','$amount','$bilag','$beskrivelse','0','$transdate','$kladde_id',";
		if ($forfaldsdate) $qtxt.= "'$forfaldsdate','$betal_id',";
		$qtxt.= "'$bilag_id','$valuta','$valutakurs','$projekt')";
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

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}
?>
