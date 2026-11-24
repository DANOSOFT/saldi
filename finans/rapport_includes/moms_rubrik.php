<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/rapport_includes/moms_rubrik.php --- ver 5.1.1 --- 2026-11-20 ---
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
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260716 MJ R3 – Momsrubrikker (A-varer, A-ydelser, B-varer, B-ydelser, C):
//                  summerer nettobeloeb pr. rubrik baseret paa box5 paa momskoder.
//                  Kraever at box5 (Rubrik) er sat i Indstillinger -> Moms.
//                  CSV-eksport.
// 20260720 MJ  Fiscal-year-aware kvartalsgenveje (fy_start/slut vars).
// 20260722 MJ  COALESCE(debet,0)/COALESCE(kredit,0) i SUM: NULL-aritmetik
//                 gav NULL (vist som 0) for rene debet- eller kreditkonti.
// 20260723 MJ  Rubrik afleder nu fra ordrelinjer: ordrer.art (DO/DK=salg, KO/KK=kob) +
//                  DG.box10 (EU-zone paa debitorgruppe) + VG.box5 (varer/ydelser).
//                  B-varer/B-ydelser: salgsordrer til B2B-EU-kunder.
//                  C: salgsordrer til kunder udenfor EU.
// 20260724 MJ  Rubrik A implementeret: koebsordrer (KO/KK) til KG.box10='B2B-EU'-leverandoerer.
//                  A-varer/A-ydelser: splittet paa VG.box5 (varer/ydelser-type).
// 20260728 MJ  Q1/Q2/Q3/Q4/Hele-aaret genveje poster nu direkte (submit=ok) fremfor
//                  GET-link til forside.
// 20260813 LOE Corrected wrong text and link that should have valg as debitor. 
// 20261103 CL/MMR SST-933 The csv link carries the download attribute, so Safari saves the file instead of showing it as text.
//                 moms_rubrik() includes its files via __DIR__ (no behaviour change).
// 20261103 LOE SST-936 Cast the department filter, and match nothing when it is not a whole number.
// 20261105 CL/LH The CSV ends with the I alt row the screen shows.
// 20261113 CL/ASR Rubrik B is split into varer/ydelser by eu_sales_kind() (includes/euSalesListKind.php), as the EU sales list is.
//                 The EU sales account (box12) the lines were posted to decides first (the EU services or goods account in Indstillinger > Moms), then the Varer/ydelser type.
//                 The varegruppe row of the report's fiscal year is read when there is one, so box12 and the EU accounts come from the same year.
// 20261117 CL/ASR Only posted orders count: status 4 and postings that carry the order id, so an invoiced order that is not posted (status 3, or moved to a journal not yet posted) is left out.
//                 Postings without an order on the rubrik accounts of Indstillinger > Moms (MR box3..box7) count too, so EU purchases and sales from the kassekladde reach box A and B.
//                 On a box3/box4 account a posting counts when it carries reverse-charge VAT (its VAT on an EM/YM account in the same voucher), whether the VAT code came from the account or the journal line, so a sale on an account some ledgers name in box3/box4 or a domestic purchase is not EU.
//                 On a box5..box7 account a posting counts when it carries no VAT, so a sale charged with Danish VAT is not a sale without VAT.
//                 A Momsfri journal posting on a box3/box4 account counts when its voucher holds acquisition VAT (EM/YM) that no posting's own VAT explains, so reverse-charge VAT typed by hand counts and a fee beside a computed reverse charge does not.
// 20261119 CL/ASR Box A pairs each journal posting with one acquisition VAT posting of its voucher, so a domestic purchase with the same VAT as an EU purchase is not counted, and order lines count with the amount their invoice posted (a discount in kr, procent, lines without an item, a quantity discount line and its copy on a credit note in the box of the line it discounts), the groups read in PHP so the order part runs on MySQL and MariaDB too.
// 20261119 CL/ASR Box A counts a posting of a journal whose lines carry VAT codes by the journal line it was posted from (its journal, bilag, date, account, department, side and net amount, reverse-charged by the code finans/bogfor.php took), so a domestic purchase no longer takes the VAT of an EU purchase in another department, on an account outside box A or with a net an øre apart; journals without VAT codes and postings outside a journal keep the pairing by VAT.
// 20261119 CL/ASR The accounts that postings without an order count on are listed under the table at Konti uden ordre, by rubrik and with their names, since Indstillinger > Moms shows only the box A accounts (MR box3/box4); the footnote points to that list.
// 20261120 CL/ASR Box A reads a journal line whose saved VAT codes do not decide its VAT (no code, so the account's code, or another code against a debtor or creditor) by its posting, VAT on top of the net and that VAT on an acquisition VAT account in its voucher and department, instead of by the account's code and the groups as they are now, so a later change to them neither removes a posted EU purchase nor adds one posted without the reverse charge, and the footnote says plainly that in journals without VAT codes and outside a journal a domestic purchase can count as an EU purchase with the same VAT.

if (!function_exists('moms_rubrik_group_rows')) {
    /**
     * One grupper row per group number (kodenr), as the report reads the group settings.
     *
     * The row of fiscal year $year comes first when $year is given, then the row of the latest fiscal year. A row
     * without a fiscal year comes last, and of two rows that rank the same the first (by id) counts.
     *
     * @param list<array<string, mixed>> $rows The grupper rows of one art, in id order.
     * @param int $year The fiscal year whose row comes first, 0 for none.
     *
     * @return array<int, array<string, mixed>> The row per group number.
     */
    function moms_rubrik_group_rows(array $rows, int $year = 0): array
    {
        $picked = $rank = [];
        foreach ($rows as $row) {
            if (!is_numeric($row['kodenr'] ?? null)) continue;
            $kodenr = (int)$row['kodenr'];
            $fiscal_year = is_numeric($row['fiscal_year'] ?? null) ? (int)$row['fiscal_year'] : null;
            if ($fiscal_year === null) $r = PHP_INT_MIN;
            elseif ($year && $fiscal_year === $year) $r = PHP_INT_MAX;
            else $r = $fiscal_year;
            if (!isset($picked[$kodenr]) || $r > $rank[$kodenr]) {
                $picked[$kodenr] = $row;
                $rank[$kodenr] = $r;
            }
        }
        return $picked;
    }
}

if (!function_exists('moms_rubrik_box_a_counted')) {
    /**
     * The postings on the box A accounts that carry reverse-charge VAT.
     *
     * The ledger keeps no link between a posting and the VAT postings finans/bogfor.php wrote for it, so they are paired
     * one to one within the voucher (journal, bilag, date) by amount. A posting with VAT of its own (transaktioner.moms)
     * takes a posting on an acquisition VAT account with that amount (credit minus debit) that no other posting has
     * taken: first one whose group is for the posting's own rubrik (EM box1 goods, YM box1 services), then any, the
     * postings in id order. A voucher with an EU purchase and a domestic purchase with the same VAT counts one of them.
     * A journal posting without VAT (Momsfri) counts when its voucher holds more acquisition VAT postings of an amount
     * than postings with that VAT of their own, that is when the reverse-charge VAT was typed by hand on lines of its own.
     *
     * @param list<array{id:int, voucher:string, rubrik:string, moms:float, journal:bool}> $postings The box A postings
     *        in id order: the voucher key, the rubrik of the account, the posting's own VAT, and whether it is a journal's.
     * @param array<string, list<array{beloeb:float, rubrik:array<string, bool>}>> $vat Per voucher its postings on an
     *        acquisition VAT account: credit minus debit, and the box A rubrik(s) the account's group is for.
     * @param array<string, list<float>> $own Per voucher the VAT of its postings with VAT of their own, on any account.
     *
     * @return array<int, true> The ids of the postings box A counts.
     */
    function moms_rubrik_box_a_counted(array $postings, array $vat, array $own): array
    {
        $key = static fn(float $amount): string => sprintf('%.3f', round($amount, 3));
        $free = $vat_count = [];
        foreach ($vat as $voucher => $entries) {
            foreach ($entries as $entry) {
                if (round((float)$entry['beloeb'], 3) == 0) continue;
                $k = $key((float)$entry['beloeb']);
                $free[$voucher][$k][] = $entry['rubrik'];
                $vat_count[$voucher][$k] = ($vat_count[$voucher][$k] ?? 0) + 1;
            }
        }
        $counted = [];
        // A posting with VAT of its own takes an acquisition VAT posting of its own rubrik first, then any.
        foreach ([true, false] as $own_rubrik) {
            foreach ($postings as $p) {
                if (isset($counted[$p['id']]) || round($p['moms'], 3) == 0) continue;
                $k = $key($p['moms']);
                foreach ($free[$p['voucher']][$k] ?? [] as $i => $rubrikker) {
                    if ($own_rubrik && empty($rubrikker[$p['rubrik']])) continue;
                    unset($free[$p['voucher']][$k][$i]);
                    $counted[$p['id']] = true;
                    break;
                }
            }
        }
        // A journal posting without VAT counts in a voucher with acquisition VAT that no posting's own VAT explains.
        $unexplained = [];
        foreach ($vat_count as $voucher => $amounts) {
            $own_count = [];
            foreach ($own[$voucher] ?? [] as $moms) {
                if (round((float)$moms, 3) != 0) $own_count[$key((float)$moms)] = ($own_count[$key((float)$moms)] ?? 0) + 1;
            }
            foreach ($amounts as $k => $n) {
                if ($n > ($own_count[$k] ?? 0)) $unexplained[$voucher] = true;
            }
        }
        foreach ($postings as $p) {
            if (round($p['moms'], 3) == 0 && $p['journal'] && isset($unexplained[$p['voucher']])) $counted[$p['id']] = true;
        }
        return $counted;
    }
}

if (!function_exists('moms_rubrik_line_treatment')) {
    /**
     * How one side of a journal line was VAT-treated, as far as the line's own saved data decides it by the rule of
     * momsberegning() (finans/bogfor_includes/postingRules.php), which finans/bogfor.php posted it by.
     *
     * - 'reverse': the side's own code (kassekladde.debetvat or kreditvat) is an EU purchase code, E (VAT group EM) or Y
     *   (group YM), which posting reverse-charges whatever the account or a debtor or creditor on the other side has.
     * - 'none': a Momsfri line; a side without a code (NULL or blank) beside a side with one, which posting gives no
     *   VAT; or a side with another code while the other side is no debtor or creditor, which posting gives that code.
     * - 'posted': the saved codes do not decide it. A line without any code took the account's code (kontoplan.moms),
     *   and a side with another code beside a debtor or creditor was reverse-charged when that party's group had an EU
     *   code (DG/KG box1 E or Y). The account's code and the party's group can have changed since the line was posted,
     *   so the posting decides (moms_rubrik_box_a_journal_counted()).
     *
     * @param array<string, mixed> $line The kassekladde row: momsfri, debetvat, kreditvat.
     * @param string $side 'debet' or 'kredit'.
     * @param bool $other_party Whether the other side is a debtor or creditor.
     *
     * @return string 'reverse', 'none' or 'posted'.
     */
    function moms_rubrik_line_treatment(array $line, string $side, bool $other_party): string
    {
        if (str_replace(' ', '', (string)($line['momsfri'] ?? ''))) return 'none';
        $own = trim((string)($line[($side == 'kredit') ? 'kreditvat' : 'debetvat'] ?? ''));
        $other = trim((string)($line[($side == 'kredit') ? 'debetvat' : 'kreditvat'] ?? ''));
        if ($own === '') return ($other === '') ? 'posted' : 'none';
        $letter = substr($own, 0, 1);
        if ($letter == 'E' || $letter == 'Y') return 'reverse';
        return $other_party ? 'posted' : 'none';
    }
}

if (!function_exists('moms_rubrik_box_a_journal_counted')) {
    /**
     * The postings on the box A accounts that the journal lines they were posted from reverse-charged.
     *
     * A posting does not name its journal line, but it has the line's journal, bilag, date, account and department, and
     * its side. Within that key a posting goes with one line whose amount in DKK, as finans/bogfor.php posts it (a line
     * in a currency at the rate on the posting, rounded to øre), is the posting's amount, one posting per line. A
     * reverse-charged line is posted with that net (the VAT comes on top). In this order:
     * 1. A line its code reverse-charged ('reverse', moms_rubrik_line_treatment()) counts, first with a posting whose
     *    VAT came on top, taking an acquisition VAT posting of that VAT when there is one, then with a posting without
     *    VAT (a code whose EM/YM group was missing posted none).
     * 2. A posting without VAT goes with a line the code gives no VAT ('none') or that the posting decides ('posted'),
     *    which does not count, so that line is not left for a posting with VAT of another line.
     * 3. A line its code does not decide ('posted') counts when its posting shows the reverse charge, whatever the
     *    account and the debtor or creditor groups say now. The posting's own VAT (transaktioner.moms) came on top of the
     *    net, with the sign of its side (a purchase plus, a credit note minus): a domestic purchase is posted net of its
     *    VAT, and the other side of a reverse-charged line carries that VAT with the opposite sign. And the voucher's
     *    postings in its department on an acquisition VAT account hold that VAT, one to one, credited for a purchase as
     *    finans/bogfor.php posts it now or debited as older versions of it did.
     *
     * @param list<array{id:int, key:string, pool:string, side:string, rubrik:string, valuta:int, valutakurs:float,
     *        amount:float, moms:float}> $postings The postings in id order: the key, the voucher and department, the side
     *        ('debet', 'kredit', or '' for neither), the rubrik of the account, the currency and rate of the posting, its
     *        debit or credit amount and its own VAT.
     * @param array<string, list<array{treatment:string, valuta:int, amount:float}>> $lines Per key the lines: 'reverse',
     *        'none' or 'posted', the line's currency (0 for DKK) and amount.
     * @param array<string, list<array{beloeb:float, rubrik:array<string, bool>}>> $vat Per voucher and department its
     *        postings on an acquisition VAT account: credit minus debit, and the box A rubrik(s) the account's group is for.
     *
     * @return array<int, true> The ids of the postings box A counts.
     */
    function moms_rubrik_box_a_journal_counted(array $postings, array $lines, array $vat = []): array
    {
        $cents = static fn(float $amount): string => sprintf('%.2f', afrund($amount, 2));
        $vat_key = static fn(float $amount): string => sprintf('%.3f', round(abs($amount), 3));
        $free = [];
        foreach ($vat as $pool => $entries) {
            foreach ($entries as $entry) {
                if (round((float)$entry['beloeb'], 3) != 0) $free[$pool][$vat_key((float)$entry['beloeb'])][] = $entry['rubrik'];
            }
        }
        // Takes an acquisition VAT posting with the posting's VAT in its voucher and department, of its own rubrik first.
        $take = static function (array $p) use (&$free, $vat_key): bool {
            $k = $vat_key($p['moms']);
            foreach ([true, false] as $own_rubrik) {
                foreach ($free[$p['pool']][$k] ?? [] as $i => $rubrikker) {
                    if ($own_rubrik && empty($rubrikker[$p['rubrik']])) continue;
                    unset($free[$p['pool']][$k][$i]);
                    return true;
                }
            }
            return false;
        };
        $matched = $counted = [];
        // Each pass: the treatments of the lines it matches, in that order, whether the posting's VAT came on top (true)
        // or there is none (false), and whether the match counts.
        $passes = [
            [['reverse'], true, true],
            [['reverse'], false, true],
            [['none', 'posted'], false, false],
            [['posted'], true, true],
        ];
        foreach ($passes as [$treatments, $on_top, $counts]) {
            foreach ($postings as $p) {
                if (isset($matched[$p['id']])) continue;
                $sign = ($p['side'] == 'debet') ? 1 : (($p['side'] == 'kredit') ? -1 : 0);
                if ($on_top ? !($sign && round($p['moms'] * $sign, 3) > 0) : round($p['moms'], 3) != 0) continue;
                foreach ($treatments as $treatment) {
                    foreach ($lines[$p['key']] ?? [] as $i => $line) {
                        if ($line['treatment'] !== $treatment || $line['valuta'] !== $p['valuta']) continue;
                        $net = $line['valuta'] ? $line['amount'] * $p['valutakurs'] / 100 : $line['amount'];
                        if ($cents($net) !== $cents($p['amount'])) continue;
                        // A line the posting decides needs the acquisition VAT; one its code reverse-charged takes it if there.
                        if ($treatment == 'posted' && $counts) {
                            if (!$take($p)) continue 3;
                        } elseif ($treatment == 'reverse' && $on_top) {
                            $take($p);
                        }
                        unset($lines[$p['key']][$i]);
                        $matched[$p['id']] = true;
                        if ($counts) $counted[$p['id']] = true;
                        continue 3;
                    }
                }
            }
        }
        return $counted;
    }
}

function moms_rubrik($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til,
                     $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart,
                     $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,
                     $simulering, $lagerbev) {

    global $db, $md, $menu, $sprog_id, $top_bund;
    global $buttonStyle, $topStyle;
    global $ansatte_id, $afd_navn, $ansatte;

    include_once(__DIR__ . '/../../includes/euSalesListKind.php');

    $regnaar    = (int)$regnaar;
    $maaned_fra = trim($maaned_fra);
    $maaned_til = trim($maaned_til);

    for ($x = 1; $x <= 12; $x++) {
        if ($maaned_fra == $md[$x]) $maaned_fra = $x;
        if ($maaned_til == $md[$x]) $maaned_til = $x;
        if (strlen($maaned_fra) == 1) $maaned_fra = '0'.$maaned_fra;
        if (strlen($maaned_til) == 1) $maaned_til = '0'.$maaned_til;
    }
    $mf = $maaned_fra;
    $mt = $maaned_til;

    $row = db_fetch_array(db_select("select * from grupper where kodenr='$regnaar' and art='RA'", __FILE__." linje ".__LINE__));
    $startmaaned = (int)($row['box1'] ?? 1);
    $startaar    = (int)($row['box2'] ?? $regnaar);
    $slutmaaned  = (int)($row['box3'] ?? 12);
    $slutaar     = (int)($row['box4'] ?? $regnaar);
    $fy_startmaaned = $startmaaned;
    $fy_slutmaaned  = $slutmaaned;
    $fy_startaar    = $startaar;
    $fy_slutaar     = $slutaar;
    $startdato   = 1;
    $slutdato    = 31;

    if ($aar_fra < $aar_til) {
        if ($maaned_til > $slutmaaned)      $aar_til = $aar_fra;
        elseif ($maaned_fra < $startmaaned) $aar_fra = $aar_til;
    }
    if ($aar_fra)    $startaar    = $aar_fra;
    if ($aar_til)    $slutaar     = $aar_til;
    if ($maaned_fra) $startmaaned = $maaned_fra;
    if ($maaned_til) $slutmaaned  = $maaned_til;
    if ($dato_fra)   $startdato   = $dato_fra;
    if ($dato_til)   $slutdato    = $dato_til;

    $startdato = (int)$startdato;
    if ($startdato < 10) $startdato = '0'.$startdato;
    while (!checkdate($startmaaned, $startdato, $startaar)) { $startdato--; if ($startdato < 28) break; }
    while (!checkdate($slutmaaned,  $slutdato,  $slutaar))  { $slutdato--;  if ($slutdato  < 28) break; }
    if (strlen((string)$startdato) < 2) $startdato = '0'.$startdato;

    $regnstart = "$startaar-$startmaaned-$startdato";
    $regnslut  = "$slutaar-$slutmaaned-$slutdato";

    $afd_filter = '';
    $afd_filter_trans = '';
    if ($afd || $afd == '0') {
        $afd_filter = preg_match('/^\d+$/', (string)$afd) ? "AND ord.afd = " . (int)$afd . " " : "AND 1 = 0 ";
        $afd_filter_trans = preg_match('/^\d+$/', (string)$afd) ? "AND t.afd = " . (int)$afd . " " : "AND 1 = 0 ";
    }

    $csvfile = "../temp/$db/moms_rubrik.csv";
    $csv     = fopen($csvfile, "w");

    $title    = 'Rapport • Momsrubrikker (A/B/C)';
    $back_url = "rapport.php?rapportart=moms_rubrik&regnaar=$regnaar"
              . "&dato_fra=$startdato&maaned_fra=$mf&aar_fra=$aar_fra"
              . "&dato_til=$slutdato&maaned_til=$mt&aar_til=$aar_til"
              . "&konto_fra=$konto_fra&konto_til=$konto_til"
              . "&ansat_fra=$ansat_fra&ansat_til=$ansat_til"
              . "&afd=$afd&projekt_fra=$projekt_fra&projekt_til=$projekt_til"
              . "&simulering=$simulering&lagerbev=$lagerbev";
    $csv_btn  = "<a href='$csvfile' download style='color:#ffffff; text-decoration:none;'><i class='fa fa-download'></i> CSV</a>";

    include(__DIR__ . '/../../includes/topline_settings.php');

    if ($menu == 'T') {
        include_once(__DIR__ . '/../../includes/top_header.php');
        include_once(__DIR__ . '/../../includes/top_menu.php');
        print "<div id=\"header\">";
        print "<div class=\"headerbtnLft headLink\"><a href=\"$back_url\" accesskey=\"L\"><i class='fa fa-close fa-lg'></i> Luk</a></div>";
        print "<div class=\"headerTxt\">Momsrubrikker (A/B/C)</div>";
        print "<div class=\"headerbtnRght headLink\">$csv_btn</div>";
        print "</div>";
        print "<div class='content-noside'>";
    } elseif ($menu == 'S') {
        $back_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8l-4 4 4 4M16 12H9"/></svg>';
        print "<table bgcolor='#eeeef0' width='100%' cellpadding='0' cellspacing='0' border='0'><tbody><tr><td colspan=8 align=center>";
        print "<table width='100%' align='center' border='0' cellspacing='4' cellpadding='0'><tbody>";
        print "<td width='5%'><a href=\"javascript:confirmClose('$back_url','')\" accesskey=L>"
            . "<button class='headerbtn' type='button' style='$buttonStyle; width:100%; display:flex; align-items:center; gap:5px;'>"
            . "$back_icon Tilbage</button></a></td>";
        print "<td width='85%' align='center' style='$topStyle'>Momsrubrikker (A/B/C)</td>";
        print "<td width='10%' align='center' style='$buttonStyle'>$csv_btn</td>";
        print "</tbody></table></td></tr></tbody></table>";
    } else {
        print "<table width='100%' cellpadding='0' cellspacing='1px' border='0' valign='top' align='center'>";
        print "<tr><td colspan='6' height='8'>";
        print "<table width='100%' align='center' border='0' cellspacing='3' cellpadding='0'><tbody>";
        print "<td width='10%' $top_bund><a accesskey=L href='$back_url'>Luk</a></td>";
        print "<td width='80%' $top_bund>Rapport - Momsrubrikker (A/B/C)</td>";
        print "<td width='10%' $top_bund><a href='$csvfile' download>CSV</a></td>";
        print "</tbody></table></td></tr></table>";
    }

    $row = db_fetch_array(db_select("select firmanavn from adresser where art='S'", __FILE__." linje ".__LINE__));
    $firmanavn = $row ? $row['firmanavn'] : '';
    print "<div style='padding:8px 12px;'><big><b>$firmanavn</b></big><br>";
    print "Periode: " . dkdato($regnstart) . " – " . dkdato($regnslut) . "</div>";

    // Quarter shortcuts — POST to rapport.php with submit=ok so the report renders directly.
    $q_hidden_base = "<input type='hidden' name='rapportart' value='moms_rubrik'>"
                  . "<input type='hidden' name='submit' value='ok'>"
                  . "<input type='hidden' name='regnaar' value='$regnaar'>"
                  . "<input type='hidden' name='dato_fra' value='01'>"
                  . "<input type='hidden' name='dato_til' value='31'>"
                  . "<input type='hidden' name='konto_fra' value='$konto_fra'>"
                  . "<input type='hidden' name='konto_til' value='$konto_til'>"
                  . "<input type='hidden' name='ansat_fra' value='$ansat_fra'>"
                  . "<input type='hidden' name='ansat_til' value='$ansat_til'>"
                  . "<input type='hidden' name='afd' value='$afd'>"
                  . "<input type='hidden' name='projekt_fra' value='$projekt_fra'>"
                  . "<input type='hidden' name='projekt_til' value='$projekt_til'>"
                  . "<input type='hidden' name='simulering' value='$simulering'>"
                  . "<input type='hidden' name='lagerbev' value='$lagerbev'>";
    $q_btn_style = "background:none;border:none;padding:0;color:#555;font-size:0.9em;"
                 . "cursor:pointer;text-decoration:underline;margin-right:8px;";
    print "<div class='no-print' style='padding:2px 12px 6px; font-size:0.9em; color:#555;'>Genveje: ";
    foreach (['Q1'=>[1,3],'Q2'=>[4,6],'Q3'=>[7,9],'Q4'=>[10,12]] as $ql => $qm) {
        $qaar = ($fy_startaar !== $fy_slutaar && $qm[0] >= $fy_startmaaned) ? $fy_startaar : $fy_slutaar;
        print "<form method='post' action='rapport.php' style='display:inline; margin:0;'>"
            . $q_hidden_base
            . "<input type='hidden' name='maaned_fra' value='{$qm[0]}'>"
            . "<input type='hidden' name='aar_fra' value='$qaar'>"
            . "<input type='hidden' name='maaned_til' value='{$qm[1]}'>"
            . "<input type='hidden' name='aar_til' value='$qaar'>"
            . "<button type='submit' style='$q_btn_style'>$ql</button>"
            . "</form>";
    }
    print "<form method='post' action='rapport.php' style='display:inline; margin:0;'>"
        . $q_hidden_base
        . "<input type='hidden' name='maaned_fra' value='$fy_startmaaned'>"
        . "<input type='hidden' name='aar_fra' value='$fy_startaar'>"
        . "<input type='hidden' name='maaned_til' value='$fy_slutmaaned'>"
        . "<input type='hidden' name='aar_til' value='$fy_slutaar'>"
        . "<button type='submit' style='$q_btn_style'>Hele &aring;ret</button>"
        . "</form>";
    print "</div>";

    // Rubrik labels per spec section 2.3
    $rubrikker = [
        'A-varer'   => 'Rubrik A – varer: V&aelig;rdien uden moms af varek&oslash;b i andre EU-lande',
        'A-ydelser' => 'Rubrik A – ydelser: V&aelig;rdien uden moms af ydelsesk&oslash;b i andre EU-lande',
        'B-varer'   => 'Rubrik B – varer: V&aelig;rdien af varesalg uden moms til andre EU-lande (B2B)',
        'B-ydelser' => 'Rubrik B – ydelser: V&aelig;rdien af visse ydelsessalg uden moms til andre EU-lande',
        'C'         => 'Rubrik C: V&aelig;rdien af andre varer og ydelser leveret uden afgift',
    ];

    // The rubrik accounts of the VAT report settings (Indstillinger > Moms, grupper art='MR' of the fiscal year).
    $mr_rows = [];
    $q = db_select("select box3, box4, box5, box6, box7, fiscal_year from grupper where art = 'MR' order by id", __FILE__." linje ".__LINE__);
    while ($r = db_fetch_array($q)) $mr_rows[] = $r;
    $mr_row = vat_report_row($mr_rows, $regnaar);
    $eu_accounts = eu_sales_accounts($regnaar);
    $konto_a_ydelser = eu_sales_account_number($mr_row['box4'] ?? '');

    // Only orders that are in the ledger count: status 4 and postings that carry the order id. An order that is
    // invoiced but not posted (status 3), or moved to a journal that is not posted yet (Hent ordrer sets status 4
    // first), is not in the ledger, so it is not in the VAT return either.
    $posted_filter = " AND ord.status >= 4"
                   . " AND EXISTS (SELECT 1 FROM transaktioner t WHERE t.ordre_id = ord.id)";

    // The groups the order lines are classified by, one row per group number (moms_rubrik_group_rows()): the EU-zone
    // (box10) of the debtor and creditor groups, and the Varer/ydelser type (box5) and EU sales account (box12) of the
    // item groups. Box A reads the item group of the latest fiscal year, boxes B and C that of the report's year when
    // there is one.
    $group_rows = ['DG' => [], 'KG' => [], 'VG' => []];
    $q = db_select("select art, kodenr, box5, box10, box12, fiscal_year from grupper where art in ('DG','KG','VG') order by id", __FILE__." linje ".__LINE__);
    while ($r = db_fetch_array($q)) $group_rows[$r['art']][] = $r;
    $dg = moms_rubrik_group_rows($group_rows['DG']);
    $kg = moms_rubrik_group_rows($group_rows['KG']);
    $vg_a = moms_rubrik_group_rows($group_rows['VG']);
    $vg_bc = moms_rubrik_group_rows($group_rows['VG'], $regnaar);

    // Each order line counts with the amount its invoice posted, in DKK:
    // - a sales invoice (DO/DK) as bogfor_nu() in includes/ordrefunc.php posts it: the discount in kr per unit
    //   (rabatart 'amount') or in percent, then the line's procent (percentage invoicing);
    // - a purchase invoice (KO/KK) as kreditor/bogfor.php posts it: the discount in percent, the line rounded to øre;
    // - at the order's exchange rate, except a line with a negative position, which is posted in DKK.
    // A line counts when it has an item, or when it has an account of its own (bogf_konto), which is what the invoice
    // posts a line without an item to.
    // A quantity discount (m_rabat) is a line of its own: bogfor() copies the discounted line with pris -m_rabat at the
    // same position, with the rabat item of Indstillinger > Diverse > Ordrerelaterede valg (none: vare_id 0) and the
    // account the first discounted line had then. It is classified by the item of the line it discounts, the line of
    // its order and position with m_rabat, so the discount lowers the box that line is in.
    // A credit note copies the invoice's lines with the quantity negated and the invoice line's id in kred_linje_id (Kred
    // in debitor/ordre.php also renumbers the positions, and a line with a negative quantity gets no m_rabat). Its copy of
    // a discount line is classified as the invoice's discount line is, so a sale and its credit note cancel out.
    $discounted_item = "(CASE WHEN COALESCE(ol.m_rabat,0) = 0 AND COALESCE(ol.pris,0) < 0 THEN COALESCE("
                     .   "(SELECT MIN(s.vare_id) FROM ordrelinjer s"
                     .     " WHERE s.ordre_id = ol.ordre_id AND s.posnr = ol.posnr AND s.id <> ol.id"
                     .     " AND COALESCE(s.m_rabat,0) <> 0 AND s.vare_id > 0),"
                     .   " (SELECT MIN(s.vare_id) FROM ordrelinjer k"
                     .     " JOIN ordrelinjer s ON s.ordre_id = k.ordre_id AND s.posnr = k.posnr AND s.id <> k.id"
                     .     " WHERE k.id = ol.kred_linje_id AND COALESCE(k.m_rabat,0) = 0 AND COALESCE(k.pris,0) < 0"
                     .     " AND COALESCE(s.m_rabat,0) <> 0 AND s.vare_id > 0))"
                     . " END)";
    $line_value = "(CASE"
                .   " WHEN ord.art IN ('KO','KK')"
                .     " THEN ROUND(COALESCE(ol.pris,0) * COALESCE(ol.antal,0)"
                .       " - COALESCE(ol.pris,0) * COALESCE(ol.antal,0) * COALESCE(ol.rabat,0) / 100, 2)"
                .   " WHEN ol.rabatart = 'amount'"
                .     " THEN (COALESCE(ol.pris,0) * COALESCE(ol.antal,0) - COALESCE(ol.rabat,0) * COALESCE(ol.antal,0))"
                .       " * COALESCE(ol.procent,100) / 100"
                .   " ELSE (COALESCE(ol.pris,0) * COALESCE(ol.antal,0)"
                .       " - COALESCE(ol.pris,0) * COALESCE(ol.antal,0) * COALESCE(ol.rabat,0) / 100)"
                .       " * COALESCE(ol.procent,100) / 100"
                . " END)"
                . " * (CASE WHEN ol.posnr < 0 OR COALESCE(ord.valutakurs,0) = 0 THEN 1 ELSE ord.valutakurs / 100 END)";
    $qtxt = "SELECT x.art, x.konto_gruppe, v.gruppe AS vare_gruppe, x.bogf_konto, SUM(x.beloeb) AS nettobeloeb"
          . " FROM (SELECT ord.art, adr.gruppe AS konto_gruppe, ol.bogf_konto,"
          .     " COALESCE($discounted_item, ol.vare_id) AS vare_id, $line_value AS beloeb"
          .   " FROM ordrelinjer ol"
          .   " JOIN ordrer ord ON ord.id = ol.ordre_id"
          .   " JOIN adresser adr ON adr.id = ord.konto_id"
          .   " WHERE ord.art IN ('DO','DK','KO','KK')"
          .     $posted_filter
          .     " AND ord.fakturadate >= '$regnstart'"
          .     " AND ord.fakturadate <= '$regnslut'"
          .     " $afd_filter"
          . ") x"
          . " LEFT JOIN varer v ON v.id = x.vare_id AND x.vare_id > 0"
          . " WHERE (v.id IS NOT NULL OR x.bogf_konto > 0)"
          . " GROUP BY x.art, x.konto_gruppe, v.gruppe, x.bogf_konto";
    $rubrik_sum = [];
    $q = db_select($qtxt, __FILE__." linje ".__LINE__);
    while ($r = db_fetch_array($q)) {
        $salg = ($r['art'] == 'DO' || $r['art'] == 'DK');
        // Rubrik A: purchases from suppliers in a group with EU-zone B2B-EU. Rubrik B: sales to customers in a group
        // with EU-zone B2B-EU, rubrik C: to customers outside the EU (B2B-UDL, B2C-UDL).
        $konto_gruppe = ($r['konto_gruppe'] === null) ? null : (($salg ? $dg : $kg)[(int)$r['konto_gruppe']] ?? null);
        $zone = ($konto_gruppe === null) ? '' : trim((string)$konto_gruppe['box10']);
        if ($salg && $zone == 'B2B-EU') $rubrik = 'B';
        elseif ($salg && ($zone == 'B2B-UDL' || $zone == 'B2C-UDL')) $rubrik = 'C';
        elseif (!$salg && $zone == 'B2B-EU') $rubrik = 'A';
        else continue;
        // The item group decides goods or services (for a quantity discount the group of the item it discounts). A line
        // without one (no item, or its group is gone) was posted to its own account, and that account decides: the box
        // A services account (MR box4), and for box B the EU accounts as eu_sales_kind() reads them.
        $vare_gruppe = ($r['vare_gruppe'] === null) ? null : (($salg ? $vg_bc : $vg_a)[(int)$r['vare_gruppe']] ?? null);
        $bogf_konto = (int)$r['bogf_konto'];
        if ($vare_gruppe === null && $bogf_konto <= 0) continue;
        if ($rubrik == 'A') {
            if ($vare_gruppe !== null) $ydelser = (trim((string)$vare_gruppe['box5']) == 'ydelser');
            else $ydelser = ($konto_a_ydelser !== '' && (int)$konto_a_ydelser === $bogf_konto);
            $rubrik = $ydelser ? 'A-ydelser' : 'A-varer';
        } elseif ($rubrik == 'B') {
            $kind = ($vare_gruppe !== null)
                ? eu_sales_kind($vare_gruppe['box5'], $vare_gruppe['box12'], $eu_accounts)
                : eu_sales_kind('', $bogf_konto, $eu_accounts);
            $rubrik = ($kind == 'services') ? 'B-ydelser' : 'B-varer';
        }
        $rubrik_sum[$rubrik] = ($rubrik_sum[$rubrik] ?? 0) + (float)$r['nettobeloeb'];
    }

    // Postings without an order (kassekladde, dokumentpulje) count on the rubrik accounts of the VAT report settings:
    // box3 A goods, box4 A services, box5/box6 B (split by eu_sales_kind() as above) and box7 C. Postings that carry an
    // order id belong to the orders counted above.
    // An account named both as a box B or C account and as a box A account counts as the sales account.
    // A journal line's VAT code comes from the line (debetvat/kreditvat) or from the account. The ledger keeps neither,
    // but the posted lines stay in the kassekladde:
    // - Box A, a posting of a journal whose lines carry VAT codes: it counts when the journal line it was posted from
    //   was reverse-charged. The posting does not name its line, so per journal, bilag, date, account, department and
    //   side it counts for as many reverse-charged lines as have its net amount (moms_rubrik_box_a_journal_counted()).
    //   The line's own code decides where it decides the posting by itself (moms_rubrik_line_treatment()): E/EM or
    //   Y/YM is reverse-charged; Momsfri, a side without a code beside one with a code, and another code against an
    //   account (no debtor or creditor) are not. Otherwise (a line without codes, which took the account's code, or
    //   another code against a debtor or creditor, whose group's EU code decided it) the posting decides: its own VAT
    //   came on top of the net and the voucher holds that VAT on an acquisition VAT account in its department. The
    //   account's code and the groups as they are now do not count, so a later change to them leaves a posted EU
    //   purchase in box A and adds no purchase that was posted without the reverse charge. A domestic purchase is not
    //   counted, also when it has the same VAT as an EU purchase in its voucher, when the EU purchase is in another
    //   department or on an account outside box A, or when the two nets differ by an øre.
    // - Box A, every other posting: a posting of a journal posted before kassekladde had VAT code columns (no line of the
    //   journal has a code), or of no journal (journal 0). It counts when it carries reverse-charge VAT: its own VAT
    //   (transaktioner.moms) paired with a posting of that amount (credit minus debit) in the same voucher (journal,
    //   bilag, date) on an acquisition VAT account, box1 of the EM and YM groups (7700/7720 as standard), which
    //   Momsangivelse shows. The pairing is one to one (moms_rubrik_box_a_counted()), so a domestic purchase with the
    //   same VAT as an EU purchase in its voucher is not counted with it; it reads the whole voucher, so there a
    //   department filter, an EU purchase on an account outside box A, or two nets with the same rounded VAT can still
    //   let a domestic purchase take the EU purchase's VAT. The ledger does not tell those cases apart, so the footnote
    //   and the help say so.
    // - Box A, either way: a journal posting without VAT (Momsfri) that no line reverse-charged counts when its voucher
    //   holds acquisition VAT that no posting's own VAT explains: the reverse-charge VAT typed by hand on lines of their
    //   own (7740 against 7700). A domestic purchase (VAT on the KM account only), a posting without VAT in a voucher
    //   without acquisition VAT or beside another line's computed reverse charge, and a sale on an account that some
    //   ledgers name in box3/box4 (the EU sales list used to read them that way, see includes/euSalesListKind.php) are
    //   left out.
    // - Box B and C: the posting counts when it carries no VAT. A sale charged with Danish VAT is not a sale without VAT.
    $journal_rubrik = [];
    foreach (['box5' => 'B', 'box6' => 'B', 'box7' => 'C', 'box3' => 'A-varer', 'box4' => 'A-ydelser'] as $box => $rubrik) {
        $konto = eu_sales_account_number($mr_row[$box] ?? '');
        if ($konto === '' || !preg_match('/^\d+$/', $konto) || isset($journal_rubrik[(int)$konto])) continue;
        if ($rubrik == 'B') {
            $rubrik = (eu_sales_kind('', $konto, $eu_accounts) == 'services') ? 'B-ydelser' : 'B-varer';
        }
        $journal_rubrik[(int)$konto] = $rubrik;
    }
    $konti_a = $konti_bc = [];
    foreach ($journal_rubrik as $konto => $rubrik) {
        if ($rubrik == 'A-varer' || $rubrik == 'A-ydelser') $konti_a[] = $konto;
        else $konti_bc[] = $konto;
    }
    // The acquisition VAT accounts of the report's fiscal year, which the posting used, and of the VAT report
    // settings' year, which Momsangivelse reads, each with the box A rubrik its group is for (EM goods, YM services).
    $konti_eu_moms = [];
    if ($konti_a) {
        $qtxt = "select art, box1 from grupper where art in ('EM','YM')"
              . " and fiscal_year in (" . (int)$regnaar . "," . (int)($mr_row['fiscal_year'] ?? $regnaar) . ")";
        $q = db_select($qtxt, __FILE__." linje ".__LINE__);
        while ($r = db_fetch_array($q)) {
            $momskonto = trim((string)$r['box1']);
            if (preg_match('/^\d+$/', $momskonto) && (int)$momskonto) {
                $konti_eu_moms[(int)$momskonto][($r['art'] == 'EM') ? 'A-varer' : 'A-ydelser'] = true;
            }
        }
    }
    $qtxt_base = "SELECT t.kontonr, SUM(COALESCE(t.debet,0)) AS debet, SUM(COALESCE(t.kredit,0)) AS kredit"
               . " FROM transaktioner t"
               . " WHERE t.transdate >= '$regnstart' AND t.transdate <= '$regnslut'"
               . " AND (t.ordre_id IS NULL OR t.ordre_id = 0)"
               . " $afd_filter_trans";
    if ($konti_bc) {
        $qtxt = $qtxt_base
              . " AND t.kontonr IN (" . implode(',', $konti_bc) . ")"
              . " AND COALESCE(t.moms,0) = 0"
              . " GROUP BY t.kontonr";
        $qj = db_select($qtxt, __FILE__." linje ".__LINE__);
        while ($r = db_fetch_array($qj)) {
            // Box B and C hold sales (credit).
            $rubrik = $journal_rubrik[(int)$r['kontonr']];
            $rubrik_sum[$rubrik] = ($rubrik_sum[$rubrik] ?? 0) + (float)$r['kredit'] - (float)$r['debet'];
        }
    }
    if ($konti_a && $konti_eu_moms) {
        // The postings on the box A accounts, and in their vouchers the postings on an acquisition VAT account and the
        // postings with VAT of their own. Postings outside a journal (stock, payment and system postings) have journal 0,
        // so their voucher is journal 0 with their bilag and date, as before.
        $box_a = "(t.ordre_id IS NULL OR t.ordre_id = 0)"
               . " AND t.kontonr IN (" . implode(',', $konti_a) . ")"
               . " $afd_filter_trans";
        $postings = [];
        $qtxt = "SELECT t.id, t.kontonr, t.kladde_id, t.bilag, t.transdate, t.afd, t.valuta, t.valutakurs,"
              .   " COALESCE(t.debet,0) AS debet, COALESCE(t.kredit,0) AS kredit, COALESCE(t.moms,0) AS moms"
              . " FROM transaktioner t"
              . " WHERE t.transdate >= '$regnstart' AND t.transdate <= '$regnslut'"
              .   " AND $box_a"
              . " ORDER BY t.id";
        $qj = db_select($qtxt, __FILE__." linje ".__LINE__);
        while ($r = db_fetch_array($qj)) {
            // A posting without a journal or bilag has no voucher (SQL never matches a NULL).
            if ($r['kladde_id'] === null || $r['bilag'] === null) continue;
            // The key of the journal lines it can come from: journal, bilag, date, account, department and side.
            $debet = (float)$r['debet'];
            $kredit = (float)$r['kredit'];
            $side = ($kredit == 0 && $debet != 0) ? 'debet' : (($debet == 0 && $kredit != 0) ? 'kredit' : '');
            $postings[] = [
                'id'         => (int)$r['id'],
                'voucher'    => $r['kladde_id'] . '|' . $r['bilag'] . '|' . $r['transdate'],
                'rubrik'     => $journal_rubrik[(int)$r['kontonr']],
                'moms'       => (float)$r['moms'],
                'journal'    => ((int)$r['kladde_id'] > 0),
                'beloeb'     => $debet - $kredit,
                'kladde_id'  => (int)$r['kladde_id'],
                'key'        => (int)$r['kladde_id'] . '|' . (int)$r['bilag'] . '|' . $r['transdate'] . '|' . (int)$r['kontonr'] . '|' . (int)$r['afd'] . '|' . $side,
                'pool'       => (int)$r['kladde_id'] . '|' . (int)$r['bilag'] . '|' . $r['transdate'] . '|' . (int)$r['afd'],
                'side'       => $side,
                'valuta'     => (int)$r['valuta'],
                'valutakurs' => (float)$r['valutakurs'],
                'amount'     => $debet + $kredit,
            ];
        }
        // The journal lines of the postings' journals. A journal whose lines carry no VAT code at all (posted before
        // finans/kassekladde.php added kassekladde.debetvat/kreditvat, or a ledger without them) keeps the pairing by VAT.
        $coded = $journal_lines = [];
        $journals = array_unique(array_filter(array_column($postings, 'kladde_id')));
        include_once(__DIR__ . '/../../includes/docsIncludes/poolAccountInfo.php');
        if ($journals && poolVatColumnsReady()) {
            $qtxt = "SELECT kladde_id FROM kassekladde WHERE kladde_id IN (" . implode(',', $journals) . ")"
                  . " AND (debetvat IS NOT NULL OR kreditvat IS NOT NULL) GROUP BY kladde_id";
            $qj = db_select($qtxt, __FILE__." linje ".__LINE__);
            while ($r = db_fetch_array($qj)) $coded[(int)$r['kladde_id']] = true;
        }
        if ($coded) {
            $lines = [];
            $qtxt = "SELECT kladde_id, bilag, transdate, d_type, debet, k_type, kredit, amount, momsfri, afd, valuta, debetvat, kreditvat"
                  . " FROM kassekladde"
                  . " WHERE kladde_id IN (" . implode(',', array_keys($coded)) . ")"
                  .   " AND transdate >= '$regnstart' AND transdate <= '$regnslut'"
                  .   " AND (debet IN (" . implode(',', $konti_a) . ") OR kredit IN (" . implode(',', $konti_a) . "))"
                  . " ORDER BY id";
            $qj = db_select($qtxt, __FILE__." linje ".__LINE__);
            while ($r = db_fetch_array($qj)) {
                // A debtor or creditor side holds its number, which bogfor() posts to the group's collective account.
                $r['d_party'] = (strstr((string)$r['d_type'], 'D') || strstr((string)$r['d_type'], 'K')) && (float)$r['debet'] > 0;
                $r['k_party'] = (trim((string)$r['k_type']) == 'D' || trim((string)$r['k_type']) == 'K') && (float)$r['kredit'] > 0;
                $lines[] = $r;
            }
            // Each side on a box A account that is no debtor or creditor, with how it was VAT-treated: by its code
            // ('reverse' or 'none'), or by its posting ('posted'). Neither the account's VAT code nor a debtor's or
            // creditor's group is read: they are what they are now, not what they were when the line was posted.
            $konti_a_set = array_flip($konti_a);
            foreach ($lines as $line) {
                foreach (['debet' => 'kredit', 'kredit' => 'debet'] as $side => $other) {
                    $konto = (int)$line[$side];
                    if ($line[($side == 'debet') ? 'd_party' : 'k_party'] || !isset($konti_a_set[$konto])) continue;
                    $treatment = moms_rubrik_line_treatment($line, $side, (bool)$line[($other == 'debet') ? 'd_party' : 'k_party']);
                    $key = (int)$line['kladde_id'] . '|' . (int)$line['bilag'] . '|' . $line['transdate'] . '|' . $konto . '|' . (int)$line['afd'] . '|' . $side;
                    $journal_lines[$key][] = ['treatment' => $treatment, 'valuta' => (int)$line['valuta'], 'amount' => (float)$line['amount']];
                }
            }
        }
        // The vouchers' postings on an acquisition VAT account, per voucher for the pairing by VAT and per voucher and
        // department for the journal lines (bogfor() posts a line's VAT in the line's department).
        $voucher_vat = $voucher_own = $line_vat = [];
        if ($postings) {
            $qtxt = "SELECT m.kladde_id, m.bilag, m.transdate, m.afd, m.kontonr,"
                  .   " COALESCE(m.debet,0) AS debet, COALESCE(m.kredit,0) AS kredit, COALESCE(m.moms,0) AS moms"
                  . " FROM transaktioner m"
                  . " WHERE m.transdate >= '$regnstart' AND m.transdate <= '$regnslut'"
                  .   " AND (m.kontonr IN (" . implode(',', array_keys($konti_eu_moms)) . ") OR COALESCE(m.moms,0) <> 0)"
                  .   " AND EXISTS (SELECT 1 FROM transaktioner t"
                  .     " WHERE t.kladde_id = m.kladde_id AND t.bilag = m.bilag AND t.transdate = m.transdate"
                  .     " AND t.transdate >= '$regnstart' AND t.transdate <= '$regnslut' AND $box_a)"
                  . " ORDER BY m.id";
            $qj = db_select($qtxt, __FILE__." linje ".__LINE__);
            while ($r = db_fetch_array($qj)) {
                $voucher = $r['kladde_id'] . '|' . $r['bilag'] . '|' . $r['transdate'];
                if (isset($konti_eu_moms[(int)$r['kontonr']])) {
                    $entry = ['beloeb' => (float)$r['kredit'] - (float)$r['debet'], 'rubrik' => $konti_eu_moms[(int)$r['kontonr']]];
                    $voucher_vat[$voucher][] = $entry;
                    $line_vat[(int)$r['kladde_id'] . '|' . (int)$r['bilag'] . '|' . $r['transdate'] . '|' . (int)$r['afd']][] = $entry;
                }
                if ((float)$r['moms'] != 0) $voucher_own[$voucher][] = (float)$r['moms'];
            }
        }
        // A posting of a journal with VAT codes counts by its journal lines. One of them without VAT that no line
        // reverse-charged, and every other posting, count by the pairing with the voucher's acquisition VAT.
        $by_line = $by_vat = [];
        foreach ($postings as $p) {
            if (isset($coded[$p['kladde_id']])) $by_line[] = $p;
        }
        $counted = moms_rubrik_box_a_journal_counted($by_line, $journal_lines, $line_vat);
        foreach ($postings as $p) {
            if (!isset($coded[$p['kladde_id']]) || (!isset($counted[$p['id']]) && round($p['moms'], 3) == 0)) $by_vat[] = $p;
        }
        $counted += moms_rubrik_box_a_counted($by_vat, $voucher_vat, $voucher_own);
        foreach ($postings as $p) {
            // Box A holds purchases (debit).
            if (isset($counted[$p['id']])) $rubrik_sum[$p['rubrik']] = ($rubrik_sum[$p['rubrik']] ?? 0) + $p['beloeb'];
        }
    }

    // The accounts postings without an order count on, by rubrik, with their names, for the list under the table
    // ("Konti uden ordre", which the help points to): Indstillinger > Moms shows only the box A accounts (MR box3/box4),
    // not box5..box7. A box A account counts only when the fiscal years have an acquisition VAT account (EM/YM box1).
    $konti_uden_ordre = $konto_navne = [];
    foreach ($journal_rubrik as $konto => $rubrik) {
        if (($rubrik == 'A-varer' || $rubrik == 'A-ydelser') && !$konti_eu_moms) continue;
        $konti_uden_ordre[$rubrik][] = $konto;
    }
    if ($konti_uden_ordre) {
        $qtxt = "SELECT kontonr, beskrivelse FROM kontoplan WHERE regnskabsaar = " . (int)$regnaar
              . " AND kontonr IN (" . implode(',', array_keys($journal_rubrik)) . ") ORDER BY id";
        $q = db_select($qtxt, __FILE__." linje ".__LINE__);
        while ($r = db_fetch_array($q)) {
            if (!isset($konto_navne[(int)$r['kontonr']])) $konto_navne[(int)$r['kontonr']] = trim((string)$r['beskrivelse']);
        }
    }

    // Check if DG groups have EU-zone configured (prerequisite for rubrik derivation)
    $has_mapping = db_fetch_array(db_select(
        "SELECT 1 FROM grupper WHERE art = 'DG'"
        . " AND NULLIF(TRIM(COALESCE(box10,'')), '') IS NOT NULL LIMIT 1",
        __FILE__." linje ".__LINE__));
    if (!$has_mapping) {
        print "<div style='padding:16px 12px; color:#c00;'>"; 
        print "<b>Rubrik-konfiguration mangler.</b><br>";
        print "Gå til <a href='../systemdata/syssetup.php?valg=debitor'>Indstillinger &rarr; Debitorgrupper</a> ";
        print "og udfyld feltet <b>EU-zone</b> for dine kundegrupper.<br>";
        print "S&aelig;t ogs&aring; <b>Varer/ydelser</b>-typen på dine ";
        print "<a href='../systemdata/syssetup.php?valg=varer'>Varegrupper</a>.";
        print "</div>";
    }

    print "<div style='padding:0 12px;'>";
    print "<table class='dataTable' border='0' cellspacing='1' width='600px'>";
    print "<thead><tr style='background:#eeeef0;'>";
    print "<th align='left'>Rubrik</th>";
    print "<th align='left'>Beskrivelse</th>";
    print "<th align='right' style='width:140px'>Nettobeloeb</th>";
    print "</tr></thead><tbody>";

    fwrite($csv, mb_convert_encoding("Rubrik;Beskrivelse;Nettobeloeb\n", 'ISO-8859-1', 'UTF-8'));

    $grand = 0;
    foreach ($rubrikker as $kode => $beskrivelse) {
        $sum = $rubrik_sum[$kode] ?? null;
        $bg  = ($sum === null) ? '#fafafa' : '#ffffff';
        $sum_txt = ($sum !== null) ? dkdecimal($sum, 2) : '<span style="color:#aaa">0,00</span>';
        $sum_csv = ($sum !== null) ? dkdecimal($sum, 2) : '0,00';
        if ($sum !== null) $grand += $sum;
        print "<tr style='background:$bg;'>";
        print "<td><b>$kode</b></td>";
        print "<td>$beskrivelse</td>";
        print "<td align='right'>$sum_txt</td>";
        print "</tr>";
        fwrite($csv, mb_convert_encoding("$kode;$beskrivelse;\"$sum_csv\"\n", 'ISO-8859-1', 'UTF-8'));
    }

    print "<tr style='background:#c8d8e8; font-weight:bold;'>";
    print "<td colspan='2' align='right'>I alt</td>";
    print "<td align='right'>" . dkdecimal($grand, 2) . "</td>";
    print "</tr>";
    fwrite($csv, mb_convert_encoding("I alt;;\"" . dkdecimal($grand, 2) . "\"\n", 'ISO-8859-1', 'UTF-8'));
    print "</tbody></table></div>";

    // Konti uden ordre: each rubrik with the accounts its postings without an order come from.
    $konti_txt = [];
    foreach (array_keys($rubrikker) as $kode) {
        if (empty($konti_uden_ordre[$kode])) continue;
        $konti = [];
        foreach ($konti_uden_ordre[$kode] as $konto) {
            $navn = $konto_navne[$konto] ?? '';
            $konti[] = $konto . (($navn !== '') ? ' (' . htmlspecialchars($navn, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ')' : '');
        }
        $konti_txt[] = "$kode " . implode(', ', $konti);
    }
    print "<div class='momsRubrikKonti' style='padding:8px 12px 0;'><b>Konti uden ordre:</b> ";
    print $konti_txt ? implode(' &middot; ', $konti_txt) : 'ingen. Posteringer uden ordre indg&aring;r ikke.';
    print "</div>";

    print "<div style='padding:6px 12px 12px; font-size:0.85em; color:#888;'>";
    print "Rubrik A afledes fra bogf&oslash;rte k&oslash;bsordrer: ";
    print "EU-zone p&aring; <a href='../systemdata/syssetup.php?valg=kreditorer'>Kreditorgrupper</a> ";
    print "+ Varer/ydelser-type p&aring; <a href='../systemdata/syssetup.php?valg=varer'>Varegrupper</a>.<br>";
    print "Rubrik B + C afledes fra bogf&oslash;rte salgsordrer: ";
    print "EU-zone p&aring; <a href='../systemdata/syssetup.php?valg=debitor'>Debitorgrupper</a> ";
    print "+ Varer/ydelser-type p&aring; Varegrupper.<br>";
    print "Ordrelinjerne indg&aring;r med det bel&oslash;b, fakturaen er bogf&oslash;rt med (rabat i kr. eller procent, procentfakturering, valutakurs); en m&aelig;ngderabat f&oslash;lger varen p&aring; den linje, den giver rabat p&aring;, og en anden linje uden vare f&oslash;lger den konto, den er bogf&oslash;rt p&aring;.<br>";
    print "Finansposteringer uden ordre (fx kassekladden) indg&aring;r med periodens bev&aelig;gelse p&aring; kontiene ved Konti uden ordre: ";
    print "p&aring; rubrik A-kontiene de posteringer, hvor bogf&oslash;ringen har beregnet moms af varek&oslash;b eller ydelsesk&oslash;b i udlandet, og momsfri posteringer i et bilag, hvor den moms er tastet p&aring; egne linjer; p&aring; rubrik B- og C-kontiene de posteringer, der er uden moms. ";
    print "Posteringer uden ordre p&aring; andre konti indg&aring;r ikke. ";
    print "I en kassekladde, hvor ingen linje har en momskode (bogf&oslash;rt f&oslash;r kassekladden fik momskoder), og for posteringer uden kassekladde kan rapporten ikke se, hvilken linje momsen h&oslash;rer til, s&aring; den parrer posteringen med bilagets moms af k&oslash;b i udlandet: d&eacute;r kan et indenlandsk k&oslash;b p&aring; en rubrik A-konto t&aelig;lle med som EU-k&oslash;b, n&aring;r bilaget ogs&aring; har et EU-k&oslash;b med samme moms, ogs&aring; et p&aring; en anden konto eller i en anden afdeling. Tjek de bilag. ";
    print "Rubrik A-kontiene er Rubrik A1 og A2 i <a href='../systemdata/syssetup.php?valg=moms'>Indstillinger &rarr; Moms</a> (Momsrapport). ";
    print "Fakturerede ordrer, der ikke er bogf&oslash;rt, indg&aring;r ikke.";
    print "</div>";

    fclose($csv);

    if ($menu == 'T') {
        include_once(__DIR__ . '/../../includes/topmenu/footer.php');
    } else {
        include_once(__DIR__ . '/../../includes/oldDesign/footer.php');
    }
}
?>
