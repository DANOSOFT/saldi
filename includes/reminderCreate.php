<?php
// includes/reminderCreate.php — ver 5.0.0 — 2026.11.17
// Copyright (c) 2003-2026 Danosoft ApS. Licensed under the GNU GPL version 2 or later.
// 20261117 CDX/ASR Extract reminder creation from ny_rykker.php for manual and scheduled runs.

require_once __DIR__ . '/reminderLegal.php';
require_once __DIR__ . '/stripeIncludes/stripeDunning.php';
require_once __DIR__ . '/stripeIncludes/stripeReminder.php';

/**
 * Create selected reminders using the existing fee, interest and link-reminder rules.
 * The caller owns the transaction. PostgreSQL's table lock serializes reminder numbers and creation
 * with other manual/scheduled calls. Selection, posting, reconciliation and delivery belong to the caller.
 *
 * @param array<int, int> $konto_id Accounts for first reminders, indexed independently of $rykker_id.
 * @param array<int, int> $rykker_id Previous reminders for escalation.
 * @return array{ids: array<int, int>, notes: array<int, string>}
 */
function reminder_create_batch(array $konto_id, array $rykker_id, string $rykkerdate, string $rykkerfrist1, string $actor): array
{
    if (!in_array($GLOBALS['db_type'], array('mysql', 'mysqli'), true)) {
        db_select('LOCK TABLE ordrer IN SHARE ROW EXCLUSIVE MODE', __FILE__ . ' line ' . __LINE__);
    }
    foreach (array($rykkerdate, $rykkerfrist1) as $date) {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Ugyldig rykkerdato.');
    }
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
                $email=db_escape_string((string)($r['email'] ?? ''));
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
                        $amount[$x]=$r['amount'];
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
                    $r= db_fetch_array(db_select("select id from ordrer where ordrenr ='$r_ordrenr' and art = 'R1'",__FILE__ . " linje " . __LINE__));
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
                        if ($valuta=='DKK'&& $opp_valuta!='DKK') $opp_amount=$r2['amount']*$opp_valkurs/100;
                        elseif ($valuta!='DKK' && $opp_valuta=='DKK') $opp_amount=$r2['amount']*100/$opp_valkurs;
                        elseif ($valuta!='DKK' && $opp_valuta!='DKK' && $opp_valuta!=$valuta) {
                            $tmp=$r2['amount']*$opp_valkurs/100;
                            $opp_amount=$tmp*100/$opp_valkurs;
                        }
                        else $opp_amount=$r2['amount'];
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
                        db_modify("update ordrer set sum='$sum' where id=$rykker_id[$i]",__FILE__ . " linje " . __LINE__);
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
                        $legalNotes[] = reminder_skip_notice($r['fakturanr'], $skip);
                        continue;
                    }
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
                    $qtxt.= "'".db_escape_string((string)($r['email'] ?? ''))."','-','".db_escape_string((string)($r['kontakt'] ?? ''))."','" . db_escape_string((string)($r['mail_fakt'] ?? '')) . "')";
                    db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                    $qtxt = "select id from ordrer where ordrenr ='$r[ordrenr]' and art = '$art' and fakturanr = '$r_fakturanr' and betalt != 'on'";
                    $r= db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); #Henter ordrelinjer fra basisrykker.
                    $ny_rykker_id[$i]=$r['id'];
                    $created[] = (int)$r['id'];
                    $pos=0;
                    $qtxt = "select * from ordrelinjer where ordre_id = '$rykker_id[$i]' order by posnr";
                    $q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
                    while ($r2=db_fetch_array($q2)) { #og indsætter dem i den nye rykker
                        if (!$r2['vare_id'] && $r2['enhed']) {
                            $qtxt = "select konto_id, faktnr, amount, udlignet from openpost where id = '" . (int)$r2['enhed'] . "'";
                            $r3=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                            if ($r3 && reminder_openpost_paid($r3)) continue; # 20261109 CL/ASR a paid open item is not reminded again, nor charged interest
                            $pos++;
                            $qtxt = "insert into ordrelinjer (posnr,enhed, ordre_id, serienr, beskrivelse) values ";
                            $qtxt.= "('$pos','" . db_escape_string((string)($r2['enhed'] ?? '')) . "', '$ny_rykker_id[$i]', '" . db_escape_string((string)($r2['serienr'] ?? '')) . "', '" . db_escape_string((string)($r2['beskrivelse'] ?? '')) . "')";
                            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                            $rentesum=$rentesum+($r3 ? $r3['amount'] : 0);
                        }
                    }
                    $qtxt = "select * from ordrer where id = '$rykker_id[$i]'";
                    $r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                    // 20261109 CL/ASR a link reminder's fee went on the next invoice ($prevCarried): there is no fee open item to list
                    if ($r2['sum'] && !$prevCarried)    { # Henter gebyrinformation fra basisrykker.
                        $qtxt = "select sum(pris) as sum from ordrelinjer where ordre_id = '$rykker_id[$i]' and vare_id > 0";
                        $r3 = db_fetch_array(db_select($qtxt, __FILE__ . ' line ' . __LINE__));
                        if ($r3 && $r3['sum'] && $r3['sum'] != $r2['sum']) {
                            $tmp = $r3['sum'];
                            if (!empty($r3['valutakurs']) && $r3['valutakurs'] != 100) $tmp*=$r3['valutakurs']/100; # 20210422
                            $qtxt = "update ordrer set sum = '". afrund($tmp,2) ."' where id = '$rykker_id[$i]'";
                            db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                            $r2['sum'] = $tmp;
                        }
                        if ($r2['valutakurs'] && $r2['valutakurs'] != 100) $r2['sum']*=$r2['valutakurs']/100; #20210422
                        $qtxt = "select * from openpost where refnr = '$rykker_id[$i]' and konto_id='$r2[konto_id]' and ";
                        $qtxt.= "amount = ". afrund($r2['sum'],2);
    #                   $qtxt.= "amount > $r2[sum]-0.01 and amount < $r2[sum]+0.01";
                        $r4=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                        if (!$r4) throw new RuntimeException('Det forrige rykkergebyr skal bogføres før næste rykker.');
                        # Og indsætter disse i den nye rykker.
                        $pos++;
                        $qtxt = "insert into ordrelinjer (posnr,enhed, ordre_id, serienr, beskrivelse) values ";
                        $qtxt.= "('$pos','$r4[id]', '$ny_rykker_id[$i]','$r2[fakturadate]', '".db_escape_string((string)($r4['beskrivelse'] ?? ''))."')";
                        db_modify($qtxt,__FILE__ . " linje " . __LINE__);
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
                        $sum=$sum+$gebyr;
                        if ($valutakurs) {
                            $gebyr=afrund($gebyr*100/$valutakurs,2);
                            $sum=afrund($sum*100/$valutakurs,2);
                        }
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
                        // 20261109 CL/ASR the sum belongs to the new reminder (it was written to the previous one)
                        $qtxt = "update ordrer set sum='$sum' where id=" . (int)$ny_rykker_id[$i];
                        db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                    }
                    // 20261109 CL/ASR link reminder when the previous level is one (stripeReminder.php)
                    if (function_exists('stripe_reminder_register')) stripe_reminder_register((int)$ny_rykker_id[$i]);
                } else {
                    if ($topniveau) $topniveau=$topniveau.", ";
                    $topniveau=$topniveau.$r['ordrenr'];
                }
            }
        }
    }

    if ($topniveau) {
        $legalNotes[] = reminder_txt(8110, 'Topniveau nået for rykkere med løbenr {loebenr}', array('loebenr' => $topniveau));
    }
    return array('ids' => $created, 'notes' => $legalNotes);
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
