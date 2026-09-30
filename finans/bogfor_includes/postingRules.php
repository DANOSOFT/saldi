<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/bogfor_includes/postingRules.php --- ver 5.0.0 --- 2026-09-30 ---
//                           LICENSE
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
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// 20260930 CL/SZ SD-699: Created. The rules that turn a cash-journal line into ledger amounts
//                  (debtor/creditor to control account, VAT split, currency) moved here from finans/bogfor.php so
//                  posting and the "Kontokort med u-bogført" report use the same code.
//                  momsberegning() takes a $preview flag so the report lists a VAT split error instead of exiting.

/**
 * Posting rules shared by the cash-journal posting and the kontokort report.
 *
 * Used by:
 *  - finans/bogfor.php                           bogfor() posts (or simulates) a journal.
 *  - finans/rapport_includes/kontokort.php        "Kontokort med u-bogført" (rapportart kontokort_ubogfort)
 *                                                 shows what the open journals will post, via unpostedJournalEntries().
 *
 * The functions read the fiscal year from global $regnaar and the currency rate via valutaopslag(), which
 * finans/bogfor.php defines itself and includes/std_func.php defines for every other page.
 * They keep bogfor()'s old error handling: gruppeopslag() and valutaopslag() print a JavaScript alert on a
 * setup error, and momsberegning() mails fejl@saldi.dk and exits if the VAT split does not add up
 * (unless called with $preview, see below).
 */

/**
 * The VAT code saved on a journal line for one side, or NULL when the line has none.
 *
 * @param array|null $row   A kassekladde row.
 * @param string     $field 'debetvat' or 'kreditvat'.
 * @return string|null      The trimmed VAT code, '' when saved blank, or NULL when the column is missing or NULL.
 */
function get_saved_vat_override($row, $field) {
	if (!is_array($row) || !array_key_exists($field, $row) || $row[$field] === NULL) {
		return NULL;
	}
	return trim((string)$row[$field]);
}

/**
 * Splits an amount into net amount and VAT for one account.
 *
 * @param string|int  $konto      Finance account number.
 * @param float       $amount     Gross amount in DKK.
 * @param string|null $momsart    VAT type of the debtor/creditor group ('E'/'Y' for EU), or NULL.
 * @param string|null $kontrol    The other side's VAT type, used for EU purchases from a creditor.
 * @param string|null $lineVat    VAT code saved on the journal line; NULL/blank falls back to the account's code.
 * @param bool        $allowBlank True when a blank $lineVat means "no VAT" instead of "use the account's code".
 * @param bool        $preview    True for the kontokort preview: a VAT split that does not add up prints an alert
 *                                and returns instead of mailing fejl@saldi.dk and exiting as posting does.
 * @return array{
 *   0: float,        Net amount.
 *   1: float|null,   VAT amount.
 *   2: string|null,  VAT account.
 *   3: string|null,  Counter VAT account (EU reverse charge), or NULL.
 * }
 */
function momsberegning($konto,$amount,$momsart,$kontrol,$lineVat=NULL,$allowBlank=false,$preview=false) {
	global $connection;
	global $regnaar;
	global $db;
	global $brugernavn;

	$nettoamount=$amount;
	$errorTxt=$moms=$momskto=$modkto=NULL;

	$a=substr($momsart,0,1); #Foerste tegn i strengen
	$b=substr($momsart,1,1); #Andet tegn i strengen

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
			$a=substr($effectiveVat,0,1);
			$b=substr($effectiveVat,1);
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
			if ($preview) {
				print "<BODY onLoad=\"javascript:alert('Afvigelse ved momsberegning')\">";
				return array($nettoamount,$moms,$momskto,$modkto);
			}
			$message=$db." | Afvigelse ved momsberegning | ".__FILE__ . " linje " . __LINE__." | ".$brugernavn." ".date("Y-m-d H:i:s");
			$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
			mail('fejl@saldi.dk', 'SALDI Bogforingsfejl', $message, $headers);
			print "<BODY onLoad=\"javascript:alert('Afvigelse ved momsberegning! Kontakt venligst Saldi teamet p&aring; telefon 4690 2208')\">";
			exit;
		}
	}
// #	$svar=array($amount,0,$momskto,$modkto);
	$svar=array($nettoamount,$moms,$momskto,$modkto);
	return $svar;
}

/**
 * Finds the control account and VAT type for a debtor or creditor.
 *
 * @param string     $type  'D' (debtor), 'K' (creditor), anything else is returned unchanged.
 * @param string|int $konto Debtor/creditor number.
 * @return array{
 *   0: string|int,   The group's control account (box2), or $konto unchanged when not D/K or not found.
 *   1: string|null,  The group's VAT type (box1), or NULL.
 * }
 */
function gruppeopslag($type, $konto) {

	global $connection, $regnaar;
	$art=NULL;$momsart=NULL;

	if ($type=='D') $art='DG';
	elseif ($type=='K') $art='KG';
	if ($art){
	$tmp=substr($art,0,1);
		$qtxt = "select gruppe from adresser where kontonr = '$konto' and art='$tmp'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($r['gruppe'])	{
			$qtxt = "select box1, box2 from grupper where art='$art' and kodenr='$r[gruppe]' and fiscal_year = '$regnaar'";
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

/**
 * Applies the posting rules to one journal line: debtor/creditor numbers become their control account,
 * foreign currency becomes DKK, and each side is split into net amount and VAT.
 *
 * This is the per-line part of bogfor()'s first loop. bogfor() still handles vouchers, open items
 * (openpost) and the inserts itself; postingLineEntries() turns the result into ledger rows.
 *
 * @param array $row     A kassekladde row (debetvat/kreditvat columns are optional).
 * @param bool  $preview Passed on to momsberegning(); true only from unpostedJournalEntries().
 * @return array{
 *   d_type: string,          Debit type after defaulting ('F' when there is no debit account).
 *   k_type: string,          Credit type after defaulting.
 *   debet: string,           Debit finance account (control account for D/K), '' when none.
 *   kredit: string,          Credit finance account, '' when none.
 *   d_momsart: string|null,  VAT type of the debit debtor/creditor group, or NULL.
 *   k_momsart: string|null,  VAT type of the credit debtor/creditor group, or NULL.
 *   eu_vat: bool,            True when a debtor/creditor group has EU VAT ('E'/'Y'); bogfor() then skips
 *                            the reverse-charge swap for the line.
 *   dkkamount: float,        Line amount in DKK.
 *   diffkonto: string|null,  Currency difference account, or NULL when the line is in DKK.
 *   valutakurs: float|null,  Exchange rate used, or NULL when the line is in DKK.
 *   momsfri: string,         The line's VAT-exempt flag with spaces removed.
 *   d_amount: float,         Net debit amount.
 *   d_moms: float|int|null,  Debit VAT.
 *   d_momskto: string|int|null, Debit VAT account.
 *   d_modkto: string|int|null,  Debit counter VAT account (EU reverse charge).
 *   k_amount: float,         Net credit amount.
 *   k_moms: float|int|null,  Credit VAT.
 *   k_momskto: string|int|null, Credit VAT account.
 *   k_modkto: string|int|null,  Credit counter VAT account (EU reverse charge).
 * }
 */
function postingLineAmounts($row, $preview=false) {
	$dType  = $row['d_type'];
	$debet  = $row['debet'];
	$kType  = $row['k_type'];
	$kredit = $row['kredit'];
	if (!$debet)  $dType = 'F';
	if (!$kredit) $kType = 'F';

	$diffkonto = $valutakurs = NULL;
	if ($row['valuta'] && $row['amount'] && ($row['debet'] || $row['kredit'])) {
		list($dkkamount, $diffkonto, $valutakurs) = valutaopslag($row['amount'], $row['valuta'], $row['transdate']);
	} else $dkkamount = $row['amount'];
	$debetvat  = get_saved_vat_override($row, 'debetvat');
	$kreditvat = get_saved_vat_override($row, 'kreditvat');

	$dMomsart = $kMomsart = NULL;
	$euVat = false;
	if ((strstr($dType, 'D') || strstr($dType, 'K')) && $debet > 0) {
		list($debet, $dMomsart) = gruppeopslag($dType, $debet);
		if ($dMomsart == 'E' || $dMomsart == 'Y') $euVat = true;
	}
	if (($kType == 'D' || $kType == 'K') && $kredit > 0) {
		list($kredit, $kMomsart) = gruppeopslag($kType, $kredit);
		if ($kMomsart == 'E' || $kMomsart == 'Y') $euVat = true;
	}
	$momsfri = str_replace(" ", "", (string)$row['momsfri']);
	$debet   = str_replace(" ", "", (string)$debet);
	$kredit  = str_replace(" ", "", (string)$kredit);

	$dAmount = $dMoms = $dMomskto = $dModkto = 0;
	$kAmount = $kMoms = $kMomskto = $kModkto = 0;
	if ($debet > 0)  $dAmount = $dkkamount;
	if ($kredit > 0) $kAmount = $dkkamount;
	if (!$momsfri && $debet > 0 && $dAmount > 0) {
		list($dAmount, $dMoms, $dMomskto, $dModkto) = momsberegning($debet, $dAmount, $dMomsart, $kMomsart, $debetvat, trim((string)$kreditvat) !== '', $preview);
	}
	if (!$momsfri && $kredit > 0 && $kAmount > 0) {
		list($kAmount, $kMoms, $kMomskto, $kModkto) = momsberegning($kredit, $kAmount, $kMomsart, $dMomsart, $kreditvat, trim((string)$debetvat) !== '', $preview);
	}

	return array(
		'd_type'     => $dType,
		'k_type'     => $kType,
		'debet'      => $debet,
		'kredit'     => $kredit,
		'd_momsart'  => $dMomsart,
		'k_momsart'  => $kMomsart,
		'eu_vat'     => $euVat,
		'dkkamount'  => $dkkamount,
		'diffkonto'  => $diffkonto,
		'valutakurs' => $valutakurs,
		'momsfri'    => $momsfri,
		'd_amount'   => $dAmount,
		'd_moms'     => $dMoms,
		'd_momskto'  => $dMomskto,
		'd_modkto'   => $dModkto,
		'k_amount'   => $kAmount,
		'k_moms'     => $kMoms,
		'k_momskto'  => $kMomskto,
		'k_modkto'   => $kModkto,
	);
}

/**
 * Turns one line from postingLineAmounts() into the ledger rows bogfor() would write for it.
 *
 * Mirrors bogfor()'s second loop, including the EU reverse-charge swap that moves VAT to the counter
 * account. bogfor() keeps its own copy of that loop because it checks each insert as it goes; a change to
 * the swap in one place must be made in the other.
 * Currency rounding rows (valutadiff) are per voucher, not per line, and are not included.
 *
 * @param array $line The result of postingLineAmounts().
 * @return array<int, array{
 *   kontonr: string,  Finance account.
 *   debet: float,     Debit amount (0 on a credit row).
 *   kredit: float,    Credit amount (0 on a debit row).
 * }>
 */
function postingLineEntries($line) {
	$dMoms    = $line['d_moms'] * 1;
	$kMoms    = $line['k_moms'] * 1;
	$dMomskto = $line['d_momskto'];
	$kMomskto = $line['k_momskto'];
	$entries  = array();

	if ($line['d_modkto'] > 0 && !$line['eu_vat']) {
		if ($kMoms && $kMomskto > 0) $entries[] = array('kontonr' => $kMomskto, 'debet' => 0, 'kredit' => $kMoms);
		$kMoms    = $dMoms;
		$kMomskto = $line['d_modkto'];
	}
	if ($line['k_modkto'] > 0 && !$line['eu_vat']) {
		if ($dMoms && $dMomskto > 0) $entries[] = array('kontonr' => $dMomskto, 'debet' => $dMoms, 'kredit' => 0);
		$dMoms    = $kMoms;
		$dMomskto = $line['k_modkto'];
	}
	if ($line['debet'] > 0)  $entries[] = array('kontonr' => $line['debet'],  'debet' => $line['d_amount'], 'kredit' => 0);
	if ($line['kredit'] > 0) $entries[] = array('kontonr' => $line['kredit'], 'debet' => 0, 'kredit' => $line['k_amount']);
	if ($dMomskto > 0)       $entries[] = array('kontonr' => $dMomskto, 'debet' => $dMoms, 'kredit' => 0);
	if ($kMomskto > 0)       $entries[] = array('kontonr' => $kMomskto, 'debet' => 0, 'kredit' => $kMoms);

	return $entries;
}

/**
 * The ledger rows every journal that is not yet posted would write, grouped by finance account.
 *
 * A journal counts as not posted unless kladdeliste.bogfort is 'V', so simulated journals ('S') are included.
 * Used by "Kontokort med u-bogført" (finans/rapport_includes/kontokort.php).
 *
 * A report reads every open journal, so a setup error must not pop up an alert for each line the way
 * posting does. A line whose rules print an alert (e.g. a debtor whose group has no setup for the fiscal
 * year) is left out and reported in 'problems' instead.
 *
 * @param string $fromDate First transaction date to include (Y-m-d).
 * @param string $toDate   Last transaction date to include (Y-m-d).
 * @param string $dim      The report's department/employee/project filter, as an SQL fragment starting with 'and'.
 * @return array{
 *   entries: array<string, array<int, array{
 *     transdate: string,   Line date (Y-m-d).
 *     bilag: string,       Voucher number.
 *     beskrivelse: string, Line text.
 *     debet: float,        Debit amount.
 *     kredit: float,       Credit amount.
 *     kladde_id: int,      Journal the line is in.
 *     valuta: int,         Currency group, 0 for DKK.
 *     valutakurs: float,   Exchange rate, 100 for DKK.
 *   }>>,  Keyed by account number, each list in date, voucher, line order.
 *   problems: array<int, array{
 *     kladde_id: int,   Journal the left-out line is in.
 *     bilag: string,    Its voucher number.
 *     message: string,  The alert text the rules produced, without markup.
 *   }>,
 * }
 */
function unpostedJournalEntries($fromDate, $toDate, $dim) {
	// bogfor() posts an empty department as 0, so an "afd = 0" filter must also match lines without one.
	$dim = str_replace("and afd = ", "and coalesce(afd,0) = ", $dim);

	$qtxt = "select * from kassekladde where kladde_id in ";
	$qtxt.= "(select id from kladdeliste where bogfort is null or bogfort != 'V') ";
	$qtxt.= "and transdate >= '" . db_escape_string($fromDate) . "' and transdate <= '" . db_escape_string($toDate) . "' ";
	$qtxt.= "and (debet > 0 or kredit > 0) $dim ";
	$qtxt.= "order by transdate, bilag, id";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);

	$byAccount = array();
	$problems  = array();
	while ($row = db_fetch_array($q)) {
		ob_start();
		$line   = postingLineAmounts($row, true);
		$output = ob_get_clean();
		if (trim($output) !== '') {
			// The alert text sits either in a <script> block or in a <BODY onLoad="..."> attribute, so read it
			// from the raw output before any tags are stripped.
			if (preg_match("/alert\\(['\"](.*?)['\"]\\)/s", $output, $m)) $message = $m[1];
			else $message = strip_tags($output);
			$message = html_entity_decode(strip_tags($message), ENT_QUOTES, 'UTF-8');
			$problems[] = array('kladde_id' => (int)$row['kladde_id'], 'bilag' => $row['bilag'], 'message' => trim($message));
			continue;
		}
		foreach (postingLineEntries($line) as $entry) {
			$byAccount[$entry['kontonr']][] = array(
				'transdate'   => $row['transdate'],
				'bilag'       => $row['bilag'],
				'beskrivelse' => $row['beskrivelse'],
				'debet'       => $entry['debet'],
				'kredit'      => $entry['kredit'],
				'kladde_id'   => (int)$row['kladde_id'],
				'valuta'      => (int)$row['valuta'],
				'valutakurs'  => $line['valutakurs'] ? $line['valutakurs'] : 100,
			);
		}
	}
	return array('entries' => $byAccount, 'problems' => $problems);
}
