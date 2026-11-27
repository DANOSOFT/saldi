<?php
// includes/reminderCreate.php — ver 5.1.1 — 2026.11.26
// Copyright (c) 2003-2026 Danosoft ApS. Licensed under the GNU GPL version 2 or later.
// 20261117 CDX/ASR Extract reminder creation from ny_rykker.php for manual and scheduled runs.
// 20261126 CL/ASR Stripe port (#1147): this line's R2/R3 refusal (debitor/ny_rykker.php, 20261105) moved here with the loop.
//                 An R2 needs an R1 dated before the run date minus ffdage2 and an R3 an R2 dated before the run date minus ffdage3; the refused løbenr come back as 'not_due'.
// 20261126 ASR Review I12: creation serializes with other reminder runs through a transaction-scoped advisory lock (reminder_create_lock()) instead of LOCK TABLE ordrer.
//              The table lock blocked order saves, invoicing and POS sales for the whole "Ryk alle" run.
// 20261126 ASR Review I1 (owner default Q8): each debtor and each chain is created under its own savepoint, as "Ryk alle" handled each debtor on its own before.
//              One that cannot be created (a missing exchange rate, a previous fee without its open item, a fee that cannot be booked) is rolled back and named in the notes.
//              With $bookPrevious ("Ryk alle") the previous level is booked, its next level created and the previous level closed (betalt) under the same savepoint.
// 20261126 ASR Review I2: a level whose next level already exists gets no second one (a notice names it), and the new reminder's id is read as the newest match.
// 20261126 ASR Review R1: the previous level's fee open item is matched within 0.01 kr., so a fee booked in kroner from another currency (13,403 EUR at 746,12 = 100,003 kr.) is found.
// 20261126 ASR Review GPT-R-2: interest is calculated on amounts in the reminder's currency (reminder_create_in_currency()): an open item in another currency is converted through kroner with its own rate and the reminder's rate.
//              On a next level the earlier open items are converted the same way, and only the fee item's price (in kroner) is converted to the reminder's currency, so the order's sum equals its lines.
// 20261126 ASR Review GPT-R-3: a reminder whose invoice already had fees on 3 reminders, or a reminder with a fee less than 10 days ago, in this or an earlier chain, is made without a fee (reminder_claim_fee_refusal()), and a notice says why.
//              Its sum is still written for its interest.
// 20261126 ASR Review GPT-R-9: a new reminder is addressed to the customer's address and reminder contacts (getReminderEmails(), as the reminder editor merges them), so one sent without being opened, as the automatic reminders send it, reaches the same recipients.
// 20261126 ASR Review GPT-F-2: reminder_create_isolated() runs the work through db_checked(), so a statement the database rejects throws there and is rolled back to the savepoint.
//              In a page request db_modify() would otherwise show the error box and end the request, and with it "Ryk alle" for every debtor.
//              The debtor or chain is skipped with a notice that carries the error reference (8226); the statement is still logged and mailed.
// 20261126 ASR Review CL2-1: whether the previous level has a fee is read from its fee and interest lines (lines with an item), not from its sum.
//              The reminder page writes '0.000' as the sum of a reminder without them, and that level could never get its next level ("Det forrige rykkergebyr skal bogføres før næste rykker.").
//              Only a level whose lines carry a fee needs its fee open item; a sum left on a level without such lines lists the open item when there is one.
// 20261126 ASR Review AR2-2: the notice for an automatic reminder that was not sent is worded by its job's state (reminder_skip_notice() with the reminder's id).

require_once __DIR__ . '/reminderLegal.php';
require_once __DIR__ . '/stdFunc/dunningCutoff.php';
require_once __DIR__ . '/stripeIncludes/stripeDunning.php';
require_once __DIR__ . '/stripeIncludes/stripeReminder.php';
require_once __DIR__ . '/stdFunc/getKontaktEmail.php';

/**
 * Create selected reminders using the existing fee, interest and link-reminder rules.
 * The caller owns the transaction. reminder_create_lock() serializes reminder numbers and creation
 * with other manual/scheduled calls. Selection, reconciliation and delivery belong to the caller.
 * Each debtor and each chain is created under its own savepoint (reminder_create_isolated()).
 * One that cannot be created is rolled back and named in 'notes', and the others are created.
 *
 * @param array<int, int> $konto_id Accounts for first reminders, indexed independently of $rykker_id.
 * @param array<int, int> $rykker_id Previous reminders for escalation.
 * @param bool $bookPrevious "Ryk alle": book each previous reminder before its next level is made, and close it (betalt) once the next level exists.
 * @return array{ids: array<int, int>, notes: array<int, string>, not_due: array<int, string>} not_due: løbenr whose next level is not due yet (R2/R3 refusal).
 */
function reminder_create_batch(array $konto_id, array $rykker_id, string $rykkerdate, string $rykkerfrist1, string $actor, bool $bookPrevious = false): array
{
    reminder_create_lock();
    foreach (array($rykkerdate, $rykkerfrist1) as $date) {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Ugyldig rykkerdato.');
    }
    $konto_id = array_map('intval', $konto_id);
    $rykker_id = array_map('intval', $rykker_id);
    $result = array('ids' => array(), 'notes' => array(), 'not_due' => array());
    $topniveau = array();
    foreach (array_unique(array_merge(array_keys($konto_id), array_keys($rykker_id))) as $i) {
        $account = !empty($konto_id[$i]) ? array($i => $konto_id[$i]) : array();
        $previous = !empty($rykker_id[$i]) ? array($i => $rykker_id[$i]) : array();
        if (!$account && !$previous) continue;
        $one = null;
        $failure = reminder_create_isolated(function () use (&$one, $account, $previous, $rykkerdate, $rykkerfrist1, $actor, $bookPrevious) {
            $one = reminder_create_items($account, $previous, $rykkerdate, $rykkerfrist1, $actor, $bookPrevious);
        });
        if ($failure !== '') {
            $result['notes'][] = reminder_create_failure_note($account ? $konto_id[$i] : 0, $previous ? $rykker_id[$i] : 0, $failure);
            continue;
        }
        $result['ids'] = array_merge($result['ids'], $one['ids']);
        $result['notes'] = array_merge($result['notes'], $one['notes']);
        $result['not_due'] = array_merge($result['not_due'], $one['not_due']);
        if ($one['top'] !== '') $topniveau[] = $one['top'];
    }
    if ($topniveau) {
        $result['notes'][] = reminder_txt(8110, 'Topniveau nået for rykkere med løbenr {loebenr}', array('loebenr' => implode(', ', $topniveau)));
    }
    return $result;
}

/**
 * Run one debtor's or chain's work under a savepoint of the caller's transaction.
 * A failure rolls the work back to the savepoint and is returned, so the rest of the batch goes on.
 * The work runs through db_checked(): a statement the database rejects throws at once (after its usual log line and
 * error mail) instead of ending the request, so it reaches the rollback too.
 *
 * @param callable $work Writes through db_modify(); throws to give up.
 * @return string '' when the work is kept, else why it was rolled back.
 */
function reminder_create_isolated(callable $work): string
{
    $failedBefore = !empty($GLOBALS['db_modify_fejl']);
    if (transaktion('savepoint reminder_create_item') === false) throw new RuntimeException('Rykkeren kunne ikke oprettes.');
    try {
        if (function_exists('db_checked')) db_checked($work);
        else $work();
        if (!$failedBefore && !empty($GLOBALS['db_modify_fejl'])) throw new RuntimeException('Rykkeren kunne ikke oprettes.');
    } catch (Throwable $e) {
        transaktion('rollback to savepoint reminder_create_item');
        transaktion('release savepoint reminder_create_item');
        $GLOBALS['db_modify_fejl'] = $failedBefore;
        if ($e instanceof SaldiDbStatementError) {
            return reminder_txt(8226, 'databasen afviste en ændring, så intet er gemt for den. Fejlen er registreret med referencen {ref}.', array('ref' => $e->getRef()));
        }
        return $e->getMessage() !== '' ? $e->getMessage() : 'Rykkeren kunne ikke oprettes.';
    }
    transaktion('release savepoint reminder_create_item');
    return '';
}

/**
 * The notice for a debtor or chain that reminder_create_isolated() rolled back.
 *
 * @param int $account adresser.id of a debtor's first reminder, or 0.
 * @param int $previous ordrer.id of the reminder that was escalated, or 0.
 * @param string $reason Why it was rolled back.
 * @return string "Ingen ny rykker for 17-1: …" or "Ingen rykker for kunde 1001: …".
 */
function reminder_create_failure_note(int $account, int $previous, string $reason): string
{
    if ($previous) {
        $r = db_fetch_array(db_select("select fakturanr from ordrer where id = " . (int)$previous, __FILE__ . ' line ' . __LINE__));
        return reminder_skip_notice(trim((string)($r['fakturanr'] ?? '')) ?: (string)$previous, $reason);
    }
    $r = db_fetch_array(db_select("select kontonr from adresser where id = " . (int)$account, __FILE__ . ' line ' . __LINE__));
    return reminder_txt(8240, 'Ingen rykker for kunde {konto}: {tekst}', array('konto' => trim((string)($r['kontonr'] ?? '')) ?: (string)$account, 'tekst' => $reason));
}

/**
 * "Ryk alle": book a reminder's fee in the caller's transaction (includes/openpost.php bogfor_rykker()).
 *
 * @param int $id ordrer.id of the reminder.
 * @return void
 * @throws RuntimeException When it is not booked (status 4) afterwards; the caller's savepoint rolls it back.
 */
function reminder_create_book(int $id): void
{
    if (!function_exists('bogfor_rykker')) require_once __DIR__ . '/openpost.php';
    // includes/rapportfunc.php's copy manages its own transaction and would commit the caller's
    if ((new ReflectionFunction('bogfor_rykker'))->getNumberOfParameters() < 2) throw new RuntimeException('Rykkeren kan ikke bogføres her.');
    bogfor_rykker($id, false);
    $r = db_fetch_array(db_select("select status from ordrer where id = " . (int)$id, __FILE__ . ' line ' . __LINE__));
    if (!$r || (int)$r['status'] < 4) throw new RuntimeException(reminder_txt(8243, 'rykkergebyret kunne ikke bogføres.'));
}

/**
 * One debtor's first reminder or one chain's next level, for reminder_create_batch(), which owns the lock and the savepoint.
 *
 * @param array<int, int> $konto_id At most one account for a first reminder.
 * @param array<int, int> $rykker_id At most one previous reminder for escalation, under the same index.
 * @param bool $bookPrevious Book the previous reminder before its next level is made, and close it (betalt) once the next level exists.
 * @return array{ids: array<int, int>, notes: array<int, string>, not_due: array<int, string>, top: string} top: the løbenr at the top level.
 */
function reminder_create_items(array $konto_id, array $rykker_id, string $rykkerdate, string $rykkerfrist1, string $actor, bool $bookPrevious = false): array
{
    $konto_id = array_map('intval', $konto_id);
    $rykker_id = array_map('intval', $rykker_id);
    $brugernavn = db_escape_string($actor);
    $tidspkt = date('H:i:s');
    $stripeDunningOpen = stripe_dunning_openpost_sql();
    $stripeDunningReminder = stripe_dunning_reminder_sql();
    $legalNotes = $created = $ny_rykker_id = $renteamount = array();
    $topniveau = '';
    $x = 0;
    $rentesats_1 = $rentesats_2 = $rentesats_3 = 0;
    $q = db_select("select formular, str from formularer where beskrivelse='GEBYR' and formular in (6,7,8) order by formular", __FILE__ . ' line ' . __LINE__);
    while ($r = db_fetch_array($q)) {
        if ($r['formular'] == 6) $rentesats_1 = (float)$r['str'];
        elseif ($r['formular'] == 7) $rentesats_2 = (float)$r['str'];
        elseif ($r['formular'] == 8) $rentesats_3 = (float)$r['str'];
    }
    $r = db_fetch_array(db_select("select box6,box7 from grupper where art='DIV' and kodenr='4'", __FILE__ . ' line ' . __LINE__));
    $ffdage2 = (int)($r['box6'] ?? 0);
    $ffdage3 = (int)($r['box7'] ?? 0);
    // R2 needs an R1 dated before $rykkerfrist2 and R3 an R2 dated before $rykkerfrist3, as in the run for all debtors (calendar days back from the run date).
    $runDate = new DateTimeImmutable($rykkerdate);
    $rykkerfrist2 = dunning_cutoff_date($ffdage2, $runDate);
    $rykkerfrist3 = dunning_cutoff_date($ffdage3, $runDate);
    $notDue = array();
    foreach (array_unique(array_merge(array_keys($konto_id), array_keys($rykker_id))) as $i) {
        if (!empty($rykker_id[$i])) {
            $heldReminder = db_fetch_array(db_select("select id from ordrer where id = " . (int)$rykker_id[$i] . " and not ($stripeDunningReminder)", __FILE__ . ' linje ' . __LINE__));
            if ($heldReminder) continue;
        }
        $forfalden=0;
        if (!empty($konto_id[$i]) || !empty($rykker_id[$i])) {
            if (empty($rykker_id[$i])) {
                $sum=0;
                $r_ordrenr=0;

                if ($r = db_fetch_array(db_select("select MAX(ordrenr) as r_ordrenr from ordrer where art LIKE 'R%'",__FILE__ . " linje " . __LINE__))) $r_ordrenr=$r['r_ordrenr'];
                $r_ordrenr++;
                $r_fakturanr=$r_ordrenr."-1";
                $konto_id[$i] = (int)$konto_id[$i];
                $r=db_fetch_array(db_select("select * from adresser where id ='$konto_id[$i]' and art = 'D'",__FILE__ . " linje " . __LINE__));
                if (!$r) {
                    $legalNotes[] = reminder_txt(8180, 'Kunde {konto} findes ikke. Der er ikke oprettet en rykker for denne kunde.', array('konto' => (string)$konto_id[$i]));
                    continue;
                }
                $kontonr=db_escape_string((string)($r['kontonr'] ?? ''));
                $firmanavn=db_escape_string((string)($r['firmanavn'] ?? ''));
                $addr1=db_escape_string((string)($r['addr1'] ?? ''));
                $addr2=db_escape_string((string)($r['addr2'] ?? ''));
                $postnr=db_escape_string((string)($r['postnr'] ?? ''));
                $bynavn=db_escape_string((string)($r['bynavn'] ?? ''));
                $land=db_escape_string((string)($r['land'] ?? ''));
                $notes=db_escape_string((string)($r['notes'] ?? ''));
                $ref=db_escape_string((string)($r['kontoansvarlig'] ?? ''));
                $kontakt=db_escape_string((string)($r['kontakt'] ?? ''));
                $email=db_escape_string(getReminderEmails($konto_id[$i], (string)($r['email'] ?? ''))); # 20261126 ASR Review GPT-R-9: with the reminder contacts
                if ($ffdage2) {
                    $betalingsdage=$ffdage2;
                    $betalingsbet='Netto'   ;
                } else {
                    $betalingsdage=db_escape_string((string)($r['betalingsdage'] ?? ''));
                    $betalingsbet=db_escape_string((string)($r['betalingsbet'] ?? ''));
                }
                $cvrnr=db_escape_string((string)($r['cvrnr'] ?? ''));
                $ean=db_escape_string((string)($r['ean'] ?? ''));
                $institution=db_escape_string((string)($r['institution'] ?? ''));
                $gruppe = (int)($r['gruppe'] ?? 0);
                $r2 = db_fetch_array(db_select("select box1, box3, box4, box6 from grupper where art='DG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
                $valuta = $r2['box3'] ?? '';
                $sprog = $r2['box4'] ?? '';
                if ($valuta && $valuta!='DKK') {
                    if ($r2= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='" . db_escape_string((string)$valuta) . "' and valuta.gruppe=grupper.kodenr::INT and valuta.valdate <= '$rykkerdate' order by valuta.valdate desc limit 1",__FILE__ . " linje " . __LINE__))) {
                        $valutakurs=$r2['kurs'];
                    } else {
                        throw new RuntimeException("Der mangler valutakurs for $valuta den $rykkerdate.");
                    }
                } else {
                    $valuta='DKK';
                    $valutakurs=100;
                }
                if ($ref) {
                    $r2 = db_fetch_array(db_select("select navn from ansatte where id = '$ref'",__FILE__ . " linje " . __LINE__));
                    $ref = db_escape_string($r2['navn'] ?? '');
                }
                $op_id=NULL;
                $x=0;
                $q=db_select("select * from openpost where udlignet = '0' and ((forfaldsdate<'$rykkerfrist1' and amount>'0') or amount < '0') and konto_id='" . (int)$konto_id[$i] . "' and $stripeDunningOpen",__FILE__ . " linje " . __LINE__);
                while ($r=db_fetch_array($q)) {
    #               Finder tidligere rykkere på samme ordre.
                    if (!db_fetch_array(db_select("select ordrer.id from ordrer,ordrelinjer where ordrelinjer.enhed = '$r[id]' and ordrer.id = ordrelinjer.ordre_id and ordrer.betalt != 'on'",__FILE__ . " linje " . __LINE__)) && !db_fetch_array(db_select("select id from ordrer where id = '" . (int)($r['refnr'] ?? 0) . "' and art LIKE 'R%' and betalt != 'on'",__FILE__ . " linje " . __LINE__))) {
                        $x++;
                        if ($op_id) $op_id=$op_id." or id ='".$r['id']."'"; #Liste over open post id numre der skal rykkes
                        else $op_id="id ='".$r['id']."'";
                        $amount[$x]=reminder_create_in_currency($r['amount'], $r['valuta'], $r['valutakurs'], (string)$valuta, $valutakurs); # 20261126 ASR Review GPT-R-2
                        $forfaldsdate[$x]=$r['forfaldsdate'];
                        if (!$forfaldsdate[$x]) $forfaldsdate[$x]=$r['transdate']; #20121105
                        if ($rentesats_1) $renteamount[(int)$r['id']] = reminder_create_interest($rentesats_1,$forfaldsdate[$x],$amount[$x],$rykkerdate);
                        else $renteamount[(int)$r['id']]=0;
                    }
                }
                $ryk_antal=$x;
                if ($ryk_antal) {
                    db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, betalingsdage, betalingsbet, cvrnr, ean, institution, notes, art, ordredate, levdate, fakturadate, momssats, hvem, tidspkt, ref, status,valuta, valutakurs,fakturanr,email,betalt,kontakt,felt_5)
                        values ('$r_ordrenr', '$konto_id[$i]', '$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$betalingsdage', '$betalingsbet', '$cvrnr', '$ean', '$institution', '$notes', 'R1', '$rykkerdate', '$rykkerdate', '$rykkerdate', '0', '$brugernavn', '$tidspkt', '$ref', '2', '" . db_escape_string((string)$valuta) . "', '$valutakurs','$r_fakturanr','$email','-','$kontakt','')",__FILE__ . " linje " . __LINE__);
                    $r= db_fetch_array(db_select("select id from ordrer where ordrenr ='$r_ordrenr' and art = 'R1' order by id desc limit 1",__FILE__ . " linje " . __LINE__));
                    $rykker_id[$i]=$r['id'];
                    $created[] = (int)$r['id'];
                    $id=$konto_id[$i];
                    $x=0;
                    $pos=0;
                    $q2 = db_select("select * from openpost where ($op_id) and $stripeDunningOpen ",__FILE__ . " linje " . __LINE__);
                    while ($r2 = db_fetch_array($q2)) {
                        $x++;
                        if ($r2['valuta']) $opp_valuta=$r2['valuta'];
                        else $opp_valuta='DKK';
                        if ($r2['valutakurs']) $opp_valkurs=$r2['valutakurs'];
                        else $opp_valkurs=100;
                        if (($opp_valuta!='DKK' || $valuta!='DKK') && $opp_valuta!=$valuta)  $beskrivelse=$r2['beskrivelse']." (".$opp_valuta." ".dkdecimal($r2['amount']).")";
                        else $beskrivelse=$r2['beskrivelse'];
                        $opp_amount=reminder_create_in_currency($r2['amount'], $opp_valuta, $opp_valkurs, (string)$valuta, $valutakurs); # 20261126 ASR Review GPT-R-2: to the reminder's currency
                        $pos++;
                        db_modify("insert into ordrelinjer (posnr,enhed, ordre_id, serienr, beskrivelse) values ('$pos','$r2[id]', '$rykker_id[$i]', '$r2[transdate]', '".db_escape_string((string)$beskrivelse)."')",__FILE__ . " linje " . __LINE__);
                        $forfalden=$forfalden+$opp_amount;
                        $interest = $renteamount[(int)$r2['id']] ?? 0;
                        if ($interest) {
                            $q3 = db_select ("select * from varer where id IN (select yb from formularer where beskrivelse='GEBYR' and formular='6')",__FILE__ . " linje " . __LINE__);
                            if ($r3 = db_fetch_array($q3)) {
                                $beskrivelse=db_escape_string((string)($r3['beskrivelse'] ?? ''));
                                $dd=$rykkerdate;
                                $pos++;
                                db_modify("insert into ordrelinjer (posnr,ordre_id,vare_id,varenr,serienr,beskrivelse,antal,pris) values ($pos,'$rykker_id[$i]','$r3[id]','" . db_escape_string((string)$r3['varenr']) . "', '$dd', '$beskrivelse','1','$interest')",__FILE__ . " linje " . __LINE__);
                                $sum=$sum+$renteamount[(int)$r2['id']];
                            }
                        }
                    }
                    $q2 = db_select ("select * from varer where id IN (select xb from formularer where beskrivelse='GEBYR' and formular='6')",__FILE__ . " linje " . __LINE__);
                    if ($r2 = db_fetch_array($q2)) {
                        $gebyr=$r2['salgspris'];
                        if ($valutakurs)$gebyr=$gebyr*100/$valutakurs;
                        // 20261126 ASR Review GPT-R-3: renteloven § 9 b (L2, L3) for the invoices across chains: no fee when an earlier chain used them up
                        $claimRefusal = ($gebyr > 0) ? reminder_claim_fee_refusal((int)$rykker_id[$i], $rykkerdate) : '';
                        if ($claimRefusal !== '') {
                            $legalNotes[] = reminder_txt(8108, 'Rykker {rykker}: {tekst}', array('rykker' => $r_fakturanr, 'tekst' => $claimRefusal . ' ' . reminder_txt(8217, 'Rykkeren er oprettet uden gebyr.')));
                        } else {
                            // 20261109 CL/ASR renteloven § 9 b (L1): at most 100,00 kr. per reminder
                            $capped = reminder_legal_cap_fee((float)$gebyr, (string)$valuta, (float)($valutakurs ?? 0));
                            if ($capped['capped']) {
                                $gebyr = $capped['fee'];
                                $legalNotes[] = reminder_txt(8108, 'Rykker {rykker}: {tekst}', array('rykker' => $r_fakturanr, 'tekst' => reminder_cap_notice($gebyr, $valuta)));
                            }
                            $pos++;
                            db_modify("insert into ordrelinjer (posnr,ordre_id, varenr, vare_id, beskrivelse, antal, pris, serienr) values ('$pos','$rykker_id[$i]', '" . db_escape_string((string)($r2['varenr'] ?? '')) . "', '" . (int)$r2['id'] . "', '" . db_escape_string((string)($r2['beskrivelse'] ?? '')) . "', '1', '$gebyr' , '$rykkerdate')",__FILE__ . " linje " . __LINE__);
                            $forfalden=$forfalden+$r2['salgspris'];
                            $sum=$sum+$gebyr;
                        }
                        // 20261126 ASR Review GPT-R-3: left without a fee and interest, the sum stays NULL as for a reminder without a fee item (nothing to carry to the next level)
                        if ($claimRefusal === '' || $sum) db_modify("update ordrer set sum='$sum' where id=$rykker_id[$i]",__FILE__ . " linje " . __LINE__);
                    }
                    // 20261109 CL/ASR "Send pr. mail" when the customer has an e-mail address and the newest reminded invoice was e-mailed
                    $newestInvoice = reminder_newest_invoice((int)$rykker_id[$i]);
                    if (trim($email) !== '' && $newestInvoice && in_array($newestInvoice['udskriv_til'], array('email', 'Link', 'Stripe'), true)) {
                        db_modify("update ordrer set mail_fakt = 'on' where id = " . (int)$rykker_id[$i], __FILE__ . " linje " . __LINE__);
                    }
                    // 20261109 CL/ASR link reminder when eligible (stripeReminder.php); classic otherwise
                    if (function_exists('stripe_reminder_register')) stripe_reminder_register((int)$rykker_id[$i]);
                }
    ##########################################################################################################################
            } else {
                $sum = 0; # 20261109 CL/ASR the new level's fee and interest only, not those of an earlier reminder in this run
                $qtxt = "select * from ordrer where id = '$rykker_id[$i]'";
                $r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                if (!$r || !in_array($r['art'], array('R1', 'R2', 'R3'), true)) throw new RuntimeException('Den forrige rykker findes ikke.');
                $rykkernr=substr($r['art'],-1);
                // 20261109 CL/ASR a link reminder's fee and interest went on the next invoice: no open claim, so no interest on them
                $prevCarried = function_exists('stripe_reminder_carried') && stripe_reminder_carried((int)$rykker_id[$i]);
                $rentesum = $prevCarried ? 0 : $r['sum'];
                $valuta=$r['valuta'];
                $fakturadate=$r['fakturadate'];
                $rykkernr++;
                // 20261126 ASR Review I2: a stale overview, a second click or another run must not write a second next level (its lines and fee went into the existing one)
                $existing = ($rykkernr <= 3) ? db_fetch_array(db_select("select fakturanr from ordrer where konto_id = " . (int)$r['konto_id'] . " and ordrenr = " . (int)$r['ordrenr'] . " and art = 'R$rykkernr' order by id limit 1", __FILE__ . " linje " . __LINE__)) : false;
                if ($existing) {
                    $legalNotes[] = reminder_skip_notice($r['fakturanr'], reminder_txt(8241, 'rykkeren har allerede en {niveau}. rykker ({rykker}).', array('niveau' => (string)$rykkernr, 'rykker' => trim((string)$existing['fakturanr']))));
                    continue;
                }
                if ($rykkernr <= 3 && $r['ordredate'] >= (($rykkernr == 2) ? $rykkerfrist2 : $rykkerfrist3)) {
                    $notDue[] = (string)$r['ordrenr'];
                    continue;
                }
                if ($valuta && $valuta!='DKK') { #20140707
                    $qtxt = "select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='" . db_escape_string((string)$valuta) . "' and ";
                    $qtxt.= "valuta.gruppe=grupper.kodenr::INT and valuta.valdate <= '$rykkerdate' order by valuta.valdate desc limit 1"; # 20210507
                    if ($r2= db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
                        $valutakurs=$r2['kurs'];
                    } else {
                        throw new RuntimeException("Der mangler valutakurs for $valuta den $rykkerdate.");
                    }
                } else {
                    $valuta='DKK';
                    $valutakurs=100;
                }
                if ($rykkernr<=3) {
                    // 20261109 CL/ASR no next level when every invoice on the reminder is paid; renteloven § 9 b (L3): a reminder with a fee
                    // at least 10 days after the previous one was sent
                    if (($skip = reminder_escalation_skip((int)$rykker_id[$i], false, $rykkerdate)) !== '') {
                        $legalNotes[] = reminder_skip_notice($r['fakturanr'], $skip, (int)$rykker_id[$i]); // 20261126 ASR Review AR2-2: an unsent automatic reminder is worded by its job's state
                        continue;
                    }
                    // 20261126 ASR Review I1: "Ryk alle" books the previous level here, under the chain's savepoint, so it is never booked without its next level
                    if ($bookPrevious) reminder_create_book((int)$rykker_id[$i]);
                    $art="R".$rykkernr;
                    $qtxt = "select * from adresser where id ='$r[konto_id]'";
                    $r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                    $r_fakturanr=$r['ordrenr']."-".$rykkernr;
                    $qtxt = "insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, betalingsdage, ";
                    $qtxt.= "betalingsbet, cvrnr, ean, institution, notes, art, ordredate, levdate, fakturadate, momssats, hvem, tidspkt, ";
                    $qtxt.= "ref,status,valuta,valutakurs,fakturanr,email,betalt,kontakt,mail_fakt) values ";
                    $qtxt.= "('$r[ordrenr]', '$r[konto_id]', '" . db_escape_string((string)($r1['kontonr'] ?? '')) . "', '".db_escape_string((string)($r1['firmanavn'] ?? ''))."', ";
                    $qtxt.= "'".db_escape_string((string)($r1['addr1'] ?? ''))."', '".db_escape_string((string)($r1['addr2'] ?? ''))."', '".db_escape_string((string)($r1['postnr'] ?? ''))."',";
                    $qtxt.= "'".db_escape_string((string)($r1['bynavn'] ?? ''))."', '".db_escape_string((string)($r1['land'] ?? ''))."', '$ffdage3', 'Netto', ";
                    $qtxt.= "'".db_escape_string((string)($r1['cvrnr'] ?? ''))."', '".db_escape_string((string)($r1['ean'] ?? ''))."', '".db_escape_string((string)($r1['institution'] ?? ''))."',";
                    $qtxt.= "'".db_escape_string((string)($r1['notes'] ?? ''))."', '$art', '$rykkerdate', '$rykkerdate', '$rykkerdate', '0', '$brugernavn', ";
                    $qtxt.= "'$tidspkt', '".db_escape_string((string)($r['ref'] ?? ''))."', '2','" . db_escape_string((string)$valuta) . "', '$valutakurs','$r_fakturanr',";
                    $qtxt.= "'".db_escape_string(getReminderEmails((int)$r['konto_id'], (string)($r['email'] ?? '')))."','-','".db_escape_string((string)($r['kontakt'] ?? ''))."','" . db_escape_string((string)($r['mail_fakt'] ?? '')) . "')"; # 20261126 ASR Review GPT-R-9
                    db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                    $qtxt = "select id from ordrer where ordrenr ='$r[ordrenr]' and art = '$art' and fakturanr = '$r_fakturanr' and betalt != 'on' order by id desc limit 1";
                    $r= db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); #Henter ordrelinjer fra basisrykker.
                    $ny_rykker_id[$i]=$r['id'];
                    $created[] = (int)$r['id'];
                    $pos=0;
                    $qtxt = "select * from ordrelinjer where ordre_id = '$rykker_id[$i]' order by posnr";
                    $q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
                    while ($r2=db_fetch_array($q2)) { #og indsætter dem i den nye rykker
                        if (!$r2['vare_id'] && $r2['enhed']) {
                            $qtxt = "select konto_id, faktnr, amount, udlignet, valuta, valutakurs from openpost where id = '" . (int)$r2['enhed'] . "'";
                            $r3=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                            if ($r3 && reminder_openpost_paid($r3)) continue; # 20261109 CL/ASR a paid open item is not reminded again, nor charged interest
                            $pos++;
                            $qtxt = "insert into ordrelinjer (posnr,enhed, ordre_id, serienr, beskrivelse) values ";
                            $qtxt.= "('$pos','" . db_escape_string((string)($r2['enhed'] ?? '')) . "', '$ny_rykker_id[$i]', '" . db_escape_string((string)($r2['serienr'] ?? '')) . "', '" . db_escape_string((string)($r2['beskrivelse'] ?? '')) . "')";
                            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                            # 20261126 ASR Review GPT-R-2: in the reminder's currency, as the previous level's sum it is added to
                            $rentesum=$rentesum+($r3 ? reminder_create_in_currency($r3['amount'], $r3['valuta'], $r3['valutakurs'], (string)$valuta, $valutakurs) : 0);
                        }
                    }
                    $qtxt = "select * from ordrer where id = '$rykker_id[$i]'";
                    $r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                    // 20261126 ASR Review CL2-1: the previous level's fee and interest lines (lines with an item) decide whether it has a fee.
                    // Its sum does not: the reminder page (debitor/rykker.php) writes '0.000' for a reminder without them, which is a true string.
                    $r3 = $prevCarried ? false : db_fetch_array(db_select("select sum(pris) as sum from ordrelinjer where ordre_id = '$rykker_id[$i]' and vare_id > 0", __FILE__ . ' line ' . __LINE__));
                    $prevFee = abs((float)($r3['sum'] ?? 0)) >= 0.005;
                    // 20261109 CL/ASR a link reminder's fee went on the next invoice ($prevCarried): there is no fee open item to list
                    if (!$prevCarried && ($prevFee || abs((float)$r2['sum']) >= 0.005))    { # Henter gebyrinformation fra basisrykker.
                        if ($prevFee && $r3['sum'] != $r2['sum']) {
                            $tmp = $r3['sum'];
                            if (!empty($r3['valutakurs']) && $r3['valutakurs'] != 100) $tmp*=$r3['valutakurs']/100; # 20210422
                            $qtxt = "update ordrer set sum = '". afrund($tmp,2) ."' where id = '$rykker_id[$i]'";
                            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                            $r2['sum'] = $tmp;
                        }
                        if ($r2['valutakurs'] && $r2['valutakurs'] != 100) $r2['sum']*=$r2['valutakurs']/100; #20210422
                        // 20261126 ASR Review R1: within 0.01, as includes/openpost.php bogfor_nu() books a fee in another currency in kroner with three decimals
                        $qtxt = "select * from openpost where refnr = '$rykker_id[$i]' and konto_id='$r2[konto_id]' and ";
                        $qtxt.= "abs(amount - " . sprintf('%.3F', (float)$r2['sum']) . ") < 0.01 order by id desc limit 1";
                        $r4=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                        // 20261126 ASR Review CL2-1: only a fee requires its open item; a sum left on a level without fee lines is listed when its open item is there
                        if (!$r4 && $prevFee) throw new RuntimeException('Det forrige rykkergebyr skal bogføres før næste rykker.');
                        if ($r4) {
                            # Og indsætter disse i den nye rykker.
                            $pos++;
                            $qtxt = "insert into ordrelinjer (posnr,enhed, ordre_id, serienr, beskrivelse) values ";
                            $qtxt.= "('$pos','$r4[id]', '$ny_rykker_id[$i]','$r2[fakturadate]', '".db_escape_string((string)($r4['beskrivelse'] ?? ''))."')";
                            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                        }
                    }
                    $formular=$rykkernr+5; # fordi rykker 1 har formular nr. 6, rykekr 2 nr. 7 osv.
                    # Og tilføjer rykkergebyr
                    if ($rykkernr==2 && $rentesats_2) $renteamount[$x] = reminder_create_interest($rentesats_2,$fakturadate,$rentesum,$rykkerdate);
                    elseif ($rykkernr==3 && $rentesats_3) $renteamount[$x] = reminder_create_interest($rentesats_3,$fakturadate,$rentesum,$rykkerdate);
                    else $renteamount[$x]=0;
                    if ($renteamount[$x]) {
                        $qtxt = "select * from varer where id IN (select yb from formularer where beskrivelse='GEBYR' and formular='$formular')";
                        $q3 = db_select ($qtxt,__FILE__ . " linje " . __LINE__);
                        if ($r3 = db_fetch_array($q3)) {
                            $beskrivelse=db_escape_string((string)($r3['beskrivelse'] ?? ''));
                            $dd=$rykkerdate;
                            $pos++;
                            $qtxt = "insert into ordrelinjer (posnr,ordre_id,vare_id,varenr,serienr,beskrivelse,antal,pris) values ";
                            $qtxt.= "($pos,'$ny_rykker_id[$i]','$r3[id]','" . db_escape_string((string)$r3['varenr']) . "', '$dd', '$beskrivelse','1','$renteamount[$x]')";
                            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                            $sum=$sum+$renteamount[$x];
                        }
                    }
                    $qtxt = "select * from varer where id IN (select xb from formularer where beskrivelse='GEBYR' and formular='$formular')";
                    $q2 = db_select ($qtxt,__FILE__ . " linje " . __LINE__);
                    if ($r2 = db_fetch_array($q2)) {
                        $gebyr=$r2['salgspris'];
                        // 20261126 ASR Review GPT-R-2: only the fee item's price is in kroner; the interest in $sum is in the reminder's currency already
                        if ($valutakurs) $gebyr=afrund($gebyr*100/$valutakurs,2);
                        // 20261126 ASR Review GPT-R-3: renteloven § 9 b (L2, L3) for the invoices across chains: no fee when an earlier chain used them up
                        $claimRefusal = ($gebyr > 0) ? reminder_claim_fee_refusal((int)$ny_rykker_id[$i], $rykkerdate) : '';
                        if ($claimRefusal !== '') {
                            $legalNotes[] = reminder_txt(8108, 'Rykker {rykker}: {tekst}', array('rykker' => $r_fakturanr, 'tekst' => $claimRefusal . ' ' . reminder_txt(8217, 'Rykkeren er oprettet uden gebyr.')));
                        } else {
                            $sum=afrund($sum+$gebyr,2);
                            // 20261109 CL/ASR renteloven § 9 b (L1): at most 100,00 kr. per reminder
                            $capped = reminder_legal_cap_fee((float)$gebyr, (string)$valuta, (float)($valutakurs ?? 0));
                            if ($capped['capped']) {
                                $sum = afrund($sum - $gebyr + $capped['fee'], 2);
                                $gebyr = $capped['fee'];
                                $legalNotes[] = reminder_txt(8108, 'Rykker {rykker}: {tekst}', array('rykker' => $r_fakturanr, 'tekst' => reminder_cap_notice($gebyr, $valuta)));
                            }
                            $qtxt = "insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, pris, serienr) values ";
                            $qtxt.= "('$ny_rykker_id[$i]', '".db_escape_string((string)($r2['varenr'] ?? ''))."', '$r2[id]', ";
                            $qtxt.= "'".db_escape_string((string)($r2['beskrivelse'] ?? ''))."', '1', '$gebyr' , '$rykkerdate')";
                            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                        }
                        // 20261109 CL/ASR the sum belongs to the new reminder (it was written to the previous one)
                        // 20261126 ASR Review GPT-R-3: left without a fee and interest, the sum stays NULL (nothing to carry to the next level)
                        $qtxt = "update ordrer set sum='$sum' where id=" . (int)$ny_rykker_id[$i];
                        if ($claimRefusal === '' || $sum) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                    }
                    // 20261109 CL/ASR link reminder when the previous level is one (stripeReminder.php)
                    if (function_exists('stripe_reminder_register')) stripe_reminder_register((int)$ny_rykker_id[$i]);
                    // 20261126 ASR Review I1: the booked previous level is closed only now that its next level exists
                    if ($bookPrevious) db_modify("update ordrer set betalt = 'on' where id = " . (int)$rykker_id[$i], __FILE__ . " linje " . __LINE__);
                } else {
                    if ($topniveau) $topniveau=$topniveau.", ";
                    $topniveau=$topniveau.$r['ordrenr'];
                }
            }
        }
    }

    return array('ids' => $created, 'notes' => $legalNotes, 'not_due' => $notDue, 'top' => $topniveau);
}

/**
 * Serialize reminder creation with other manual and scheduled runs until the caller's transaction ends.
 * It covers the reminder numbers (MAX(ordrenr) + 1) and the re-checks for an existing reminder or next level.
 * An advisory lock blocks only other reminder runs; order saves, invoicing and POS sales go on.
 * MySQL ledgers take no lock, as before.
 *
 * @return void
 */
function reminder_create_lock(): void
{
    if (in_array($GLOBALS['db_type'] ?? '', array('mysql', 'mysqli'), true)) return;
    db_select("select pg_advisory_xact_lock(hashtext(current_database()), hashtext('reminder_create'))", __FILE__ . ' line ' . __LINE__);
}

/**
 * An amount in the reminder's currency. Interest is put on the reminder as a line in its currency, so it is calculated on
 * amounts in that currency. An open item is converted through kroner with its own rate (the rate it was posted at, 100
 * when it has none, as the reminder's lines describe it) and the reminder's rate of the reminder date.
 *
 * @param mixed $amount The amount in $fromValuta.
 * @param mixed $fromValuta The open item's currency ('' = DKK).
 * @param mixed $fromKurs The open item's rate (DKK per 100 units).
 * @param string $toValuta The reminder's currency ('' = DKK).
 * @param mixed $toKurs The reminder's rate (DKK per 100 units).
 * @return float The amount in $toValuta, not rounded.
 */
function reminder_create_in_currency($amount, $fromValuta, $fromKurs, string $toValuta, $toKurs): float
{
    $from = strtoupper(trim((string)$fromValuta)) ?: 'DKK';
    $to = strtoupper(trim($toValuta)) ?: 'DKK';
    if ($from === $to) return (float)$amount;
    $kr = ($from === 'DKK') ? (float)$amount : (float)$amount * ((float)$fromKurs ?: 100) / 100;
    if ($to === 'DKK') return $kr;
    if ((float)$toKurs <= 0) throw new RuntimeException("Der mangler valutakurs for $to.");
    return $kr * 100 / (float)$toKurs;
}

/** Existing compound monthly interest calculation, with an explicit run date for scheduler tests. */
function reminder_create_interest($rate, $due, $amount, string $today): float
{
    $from = new DateTimeImmutable($due);
    $to = new DateTimeImmutable($today);
    $months = ((int)$to->format('Y') - (int)$from->format('Y')) * 12 + (int)$to->format('m') - (int)$from->format('m');
    if ((int)$to->format('d') >= (int)$from->format('d')) $months++;
    $interest = 0.0;
    for ($i = 0; $i < $months; $i++) {
        $interest += (($amount + $interest) * $rate / 100);
    }
    return round($interest, 2);
}
