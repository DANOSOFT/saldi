<?php
// 20261105 CL/LH Created. store() puts the totals an order's own screen computes from its lines in the order's header (ordrer.sum, moms, kostpris).
//                OrderLineService calls it after a line is added, because only the screens stored the totals, so the API, SQL and the Tilbud list showed 0.
//                Sales orders follow debitor/ordre.php (m_rabat, følgevarer, sets, procenttillæg and the customer's VAT mode included), POS orders the POS screen, purchase orders kreditor/ordre.php.
//                A POS rabatgruppe's percentage discount on a line of a set is 0, as productLines.php computes it from the set line's price after drawing it at 0.
// 20261110 CL/LH store() writes each total as the screen writes it and lets the column round it to 3 decimals, as the screen's UPDATE does.
//                afrund() rounded 5.145 x 31.031 = 159.654495 to a kostpris of 159.655, where opening the order stores 159.654.

/**
 * The totals an order screen stores in an order's header when it draws the order, computed from the stored lines.
 *
 * The REST API adds lines without drawing the order, so it stores the header the way the order's own screen does:
 * sales(), pos() and purchase() each follow one screen's code, named in their PHPDoc. A change to how one of those
 * screens totals an order belongs here too; tests/characterization/restapi/RestOrderLineTotalsTest opens the screens and
 * compares.
 */
class OrderTotalsService
{
    /**
     * Stores the totals of order $orderId in its header, as the order's screen would store them on its next draw.
     * An invoiced order (status 3 or more) is left alone, and so is an order whose totals do not fit the columns
     * (the sales screen shows 'Beløbet ... er for stort' instead). Each total is written unrounded, as the screen
     * writes it, so the column's NUMERIC(15,3) rounds it as it rounds the screen's: afrund() nudges the value up
     * first and gave 159.655 for a kostpris of 159.654495, which the column stores as 159.654.
     *
     * @param int|string $orderId
     * @param int|string $regnaar Fiscal year whose groups give the VAT rates and the POS settings.
     * @return void
     */
    public static function store($orderId, $regnaar)
    {
        $orderId = (int) $orderId;
        $regnaar = (int) $regnaar;
        $qtxt = "SELECT art, status, momssats, procenttillag, konto_id FROM ordrer WHERE id = $orderId";
        $order = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        if (!$order || $order['status'] >= 3) {
            return;
        }
        if (in_array($order['art'], ['KO', 'KK'], true)) {
            $totals = self::purchase($orderId, $order);
        } elseif ($order['art'] == 'PO') {
            $totals = self::pos($orderId, $regnaar);
        } else {
            $totals = self::sales($orderId, $order, $regnaar);
        }
        $set = [];
        foreach ($totals as $column => $value) {
            // The columns hold 15 digits, 3 of them decimals
            if (abs($value) >= 100000000000) {
                return;
            }
            $set[] = "$column = " . (float) $value;
        }
        db_modify("UPDATE ordrer SET " . implode(', ', $set) . " WHERE id = $orderId AND status < 3", __FILE__ . " linje " . __LINE__);
    }

    /**
     * The totals debitor/ordre.php stores when it draws a sales order or credit note (ordreside() and ordrelinjer()).
     * - The drawn lines are the lines with posnr > 0 in posnr order, except a component of an assembled item (numeric
     *   samlevare) and the head line of a set. A line's VAT rate is the order's when it has none, at most the order's,
     *   and 0 when momsfri or omvendt betalingspligt.
     * - A drawn line with a varenr (and not a negative følgevare) adds (pris - rabat %, or - rabat with rabatart
     *   'amount') x antal, x procent / 100 with Procentfakturering (DIV 3 box12), to 3 decimals, and its VAT to 3
     *   decimals. A stored quantity discount (m_rabat) on a line without rabat and rabatgruppe adds -m_rabat x antal and
     *   its VAT, each to øre.
     * - A line with a følgevare adds the follow item at the line's antal and procent: from its stored line (folgevare =
     *   -the line's id) or else from the item and its group's VAT.
     * - The last line of a set adds the head line's discounted pris, and the set's VAT is moved so the set totals its
     *   rounded saetpris.
     * - procenttillæg (ordrer.procenttillag) adds that percentage of the net, rounded to øre, with the order's VAT.
     * - moms is the VAT of the net to øre when every line has the order's rate and the customer's prices are without
     *   VAT; otherwise (mixed rates, momsfri lines, a set, or prices with VAT for the customer's kontotype, settings
     *   vatPrivateCustomers / vatBusinessCustomers) it is the lines' VAT rounded to øre.
     * - kostpris is kostpris x antal of the item lines (pris x fast_db with a fixed margin); with sets shown (POS 2
     *   box12) an assembled item's own line does not count.
     *
     * @param int                  $orderId
     * @param array<string, mixed> $order   ordrer row: momssats, procenttillag, konto_id.
     * @param int                  $regnaar
     * @return array{sum: float, kostpris: float, moms: float}
     */
    private static function sales($orderId, array $order, $regnaar)
    {
        $orderRate = (float) $order['momssats'];
        $r = db_fetch_array(db_select("SELECT box12 FROM grupper WHERE art = 'DIV' AND kodenr = '3'", __FILE__ . " linje " . __LINE__));
        $procentfakt = $r ? $r['box12'] : '';
        $qtxt = "SELECT box12 FROM grupper WHERE art = 'POS' AND kodenr = '2' AND fiscal_year = '$regnaar'";
        $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        $visSaet = $r ? $r['box12'] : '';
        $kontoId = (int) $order['konto_id'];
        $r = db_fetch_array(db_select("SELECT kontotype FROM adresser WHERE id = $kontoId", __FILE__ . " linje " . __LINE__));
        $vatSetting = ($r && $r['kontotype'] == 'erhverv') ? 'vatBusinessCustomers' : 'vatPrivateCustomers';
        $inclMoms = get_settings_value($vatSetting, 'ordre', '');

        $lines = [];
        $kostsum = 0;
        $q = db_select("SELECT * FROM ordrelinjer WHERE ordre_id = $orderId ORDER BY posnr", __FILE__ . " linje " . __LINE__);
        while ($row = db_fetch_array($q)) {
            if ($row['posnr'] <= 0 || is_numeric($row['samlevare']) || ($row['saet'] && $row['samlevare'] == 'on')) {
                continue;
            }
            $rate = (float) $row['momssats'];
            if (!$row['momsfri'] && !$rate) {
                $rate = $orderRate;
            } elseif ($rate > $orderRate) {
                $rate = $orderRate;
            } elseif ($row['momsfri'] || $row['omvbet']) {
                $rate = 0;
            }
            $row['rate'] = $rate;
            $row['varenr'] = trim((string) $row['varenr']);
            $row['m_rabat'] = (float) $row['m_rabat'] * -1;
            if ($row['vare_id'] && (!$row['samlevare'] || !$visSaet)) {
                $kostpris = (float) $row['fast_db'] ? (float) $row['pris'] * (float) $row['fast_db'] : (float) $row['kostpris'];
                $kostsum += $kostpris * (float) $row['antal'];
            }
            $lines[] = $row;
        }

        $t = ['sum' => 0.0, 'moms' => 0.0, 'mixed' => false];
        $saetnr = $saetpris = $saetSumBefore = $saetMomsBefore = 0;
        // ordreside() keeps a follow item's momsfri and VAT rate from one følgevare to the next when it finds none
        $fvMomsfri = null;
        $fvRate = 0.0;
        $count = count($lines);
        for ($x = 0; $x < $count; $x++) {
            $line = $lines[$x];
            if ($line['saet']) {
                if ($saetnr && $saetnr != $line['saet']) {
                    $saetpris = 0;
                }
                if (!$saetpris) {
                    $saetSumBefore = $t['sum'];
                    $saetMomsBefore = $t['moms'];
                }
                $linjesum = (float) $line['pris'] * (float) $line['antal'];
                $linjesum -= $linjesum * (float) $line['rabat'] / 100;
                $linjesum += $linjesum * $line['rate'] / 100;
                $saetpris += afrund($linjesum, 3);
                $saetnr = $line['saet'];
            } elseif ($saetnr) {
                $saetpris = $saetnr = 0;
            }
            if ((int) $line['folgevare'] >= 0) {
                self::addSalesLine($t, $line, $orderRate, $procentfakt);
            }
            if ((int) $line['folgevare'] > 0) {
                $linked = null;
                foreach ($lines as $other) {
                    if ((int) $other['folgevare'] == -(int) $line['id']) {
                        $linked = $other;
                    }
                }
                if ($linked) {
                    $fvVarenr = $linked['varenr'];
                    $fvPris = $linked['pris'];
                    $fvRate = $linked['rate'];
                } else {
                    $vareId = (int) $line['folgevare'];
                    $qtxt = "SELECT varer.varenr, varer.salgspris, grupper.box4, grupper.box7 FROM varer, grupper ";
                    $qtxt .= "WHERE varer.id = $vareId AND grupper.art = 'VG' AND grupper.kodenr = varer.gruppe";
                    $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
                    $fvVarenr = $r ? $r['varenr'] : null;
                    $fvPris = $r ? $r['salgspris'] : 0;
                    $fvMomsfri = $r ? $r['box7'] : null;
                    $vatCode = $r ? self::accountVatCode($r['box4'], $regnaar) : '';
                    if ($vatCode) {
                        $rate = self::salesVatRate($vatCode, $regnaar);
                        if ($rate) {
                            $fvRate = $rate;
                        }
                    } else {
                        $fvRate = $orderRate;
                    }
                }
                $followItem = [
                    'varenr' => $fvVarenr, 'pris' => $fvPris, 'rabat' => 0, 'rabatart' => 'percent',
                    'procent' => $line['procent'], 'antal' => $line['antal'], 'momsfri' => $fvMomsfri, 'rabatgruppe' => 0,
                    'm_rabat' => 0, 'rate' => $fvRate, 'samlevare' => 0, 'saet' => $line['saet'],
                ];
                self::addSalesLine($t, $followItem, $orderRate, $procentfakt);
            }
            $next = $lines[$x + 1] ?? null;
            if ($line['saet'] && $saetpris && !$line['samlevare'] && (!$next || $next['saet'] != $line['saet'] || $next['samlevare'])) {
                $saet = (int) $line['saet'];
                $qtxt = "SELECT varenr, pris, antal, rabat FROM ordrelinjer WHERE saet = $saet AND ordre_id = $orderId AND samlevare = 'on'";
                if ($head = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
                    // The head line's pris is the difference between the set's price and its items
                    $t['sum'] += (float) $head['pris'] * (1 - (float) $head['rabat'] / 100);
                    $setPrice = $saetpris != 0 ? round($saetpris) : round((float) $head['pris'] * ($head['antal'] ?: 1));
                    $headLine = [
                        'varenr' => $head['varenr'], 'pris' => $setPrice, 'rabat' => 0, 'rabatart' => 'percent', 'procent' => 100,
                        'antal' => 1, 'momsfri' => '', 'rabatgruppe' => 0, 'm_rabat' => 0, 'rate' => 0, 'samlevare' => 'on',
                        'saet' => $line['saet'],
                    ];
                    self::addSalesLine($t, $headLine, $orderRate, $procentfakt);
                    // The set's sum and VAT add up to the set price the screen shows
                    $t['moms'] += $setPrice - (($t['sum'] - $saetSumBefore) + ($t['moms'] - $saetMomsBefore));
                }
                $saetpris = $saetnr = 0;
            }
        }

        $net = $t['sum'];
        $sum = afrund($t['sum'], 2);
        $moms = afrund($t['moms'], 2);
        $procenttillag = (float) $order['procenttillag'];
        if ($procenttillag) {
            $tillag = $sum * $procenttillag / 100;
            $sum += $tillag;
            $net += $tillag;
            $moms += $tillag / 100 * $orderRate;
        }
        if (!$t['mixed'] && !$inclMoms) {
            $moms = afrund($net * $orderRate / 100, 2);
        }
        return ['sum' => $sum, 'kostpris' => $kostsum, 'moms' => afrund($moms, 3)];
    }

    /**
     * Adds one line as ordrelinjer() in debitor/ordre.php draws it to the running totals $t (sum, moms, and mixed: a
     * VAT rate other than the order's or a momsfri line).
     *
     * @param array{sum: float, moms: float, mixed: bool} $t
     * @param array<string, mixed> $line varenr, pris, rabat, rabatart, procent, antal, momsfri, rabatgruppe, m_rabat (as
     *                                   drawn: the stored value negated), rate (the drawn VAT rate), samlevare, saet.
     * @param float                $orderRate
     * @param mixed                $procentfakt DIV 3 box12, Procentfakturering.
     * @return void
     */
    private static function addSalesLine(array &$t, array $line, $orderRate, $procentfakt)
    {
        $antal = 0;
        if ($line['varenr'] || ($line['saet'] && $line['samlevare'])) {
            $antal = (float) $line['antal'];
            $pris = (float) $line['pris'];
            $rabat = (float) $line['rabat'];
            $amount = ($line['rabatart'] == 'amount') ? ($pris - $rabat) * $antal : ($pris - $pris / 100 * $rabat) * $antal;
            if ($procentfakt) {
                $amount *= (float) $line['procent'] / 100;
            }
            $amount = afrund($amount, 3);
            // A set's head line only shows the set price; its items carry the amount
            if (!$line['saet'] || $line['samlevare'] != 'on') {
                $t['sum'] += $amount;
            }
            if ($line['momsfri'] != 'on') {
                $t['moms'] += afrund($amount * $line['rate'] / 100, 3);
                if ($line['rate'] != $orderRate) {
                    $t['mixed'] = true;
                }
            } else {
                $t['mixed'] = true;
            }
        }
        $mRabat = (float) $line['m_rabat'];
        if (!(float) $line['rabat'] && $mRabat && !$line['rabatgruppe']) {
            if ($line['momsfri'] != 'on') {
                $t['moms'] += afrund($mRabat * $antal * $line['rate'] / 100, 2);
                if ($line['rate'] != $orderRate) {
                    $t['mixed'] = true;
                }
            }
            $t['sum'] += afrund($mRabat * $antal, 2);
        }
    }

    /**
     * The totals the POS screen stores for an open POS order (vis_pos_linjer() in
     * debitor/pos_ordre_includes/showPosLines/showPosLinesFunc.php, with ordrelinjerDataII.php and productLines.php):
     * sum is the net and moms the gross to øre less the net, 0 below 0,10. No kostpris.
     * - The lines are those with posnr >= 0 except the 'R' discount line and a tilfravalg 'L:' line, in the screen's
     *   order (sets first with sets shown, POS 2 box12; then rabatgruppe).
     * - A line's VAT rate is its own (0 when momsfri); its gross unit price is vat_price, or the net with VAT to øre
     *   when vat_price is 0. The net unit price is rounded to øre.
     * - The net adds antal x the net unit price less rabat % to øre, or with rabatart 'amount' antal x (price - 2 x
     *   rabat) to øre, or with a quantity discount (m_rabat, no rabatgruppe) antal x (price - m_rabat with VAT); the
     *   gross adds the same from the gross price, to 3 decimals.
     * - The gross also adds a følgevare's or tilfravalg's item price with VAT, a rabatgruppe's discount (grupperabat()
     *   in includes/ordrefunc.php) and the 'R' line's pris. A percentage discount is taken of the gross price of the
     *   group's last line, which is 0 for a line of a set with a posnr (productLines.php shows the set's price instead).
     *
     * @param int $orderId
     * @param int $regnaar
     * @return array{sum: float, moms: float}
     */
    private static function pos($orderId, $regnaar)
    {
        require_once __DIR__ . '/../../includes/ordrefunc.php'; // grupperabat()
        $qtxt = "SELECT box12 FROM grupper WHERE art = 'POS' AND kodenr = '2' AND fiscal_year = '$regnaar'";
        $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        $visSaet = $r ? trim((string) $r['box12']) : '';
        $qtxt = "SELECT * FROM ordrelinjer WHERE ordre_id = $orderId AND varenr != 'R' AND ordre_id > 0 AND posnr >= 0 ";
        $qtxt .= $visSaet ? "ORDER BY saet DESC, rabatgruppe, id DESC" : "ORDER BY rabatgruppe, posnr DESC";
        $lines = [];
        $q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
        while ($row = db_fetch_array($q)) {
            if ($row['varenr'] != 'R' && substr((string) $row['tilfravalg'], 0, 2) != 'L:') {
                $lines[] = $row;
            }
        }

        $gross = $net = 0;
        $groupQty = [];
        foreach ($lines as $x => $line) {
            $antal = (float) $line['antal'];
            // As in ordrelinjerDataII.php: a stored rate counts as set when it is truthy, so '0.000' takes vat_price too
            $storedRate = trim((string) $line['momsfri']) ? 0 : $line['momssats'];
            $rate = (float) $storedRate;
            $netPrice = (float) $line['pris'];
            $vatPrice = (float) $line['vat_price'];
            if (!$vatPrice && $netPrice) {
                $vatPrice = afrund($netPrice + $netPrice * $rate / 100, 2);
            }
            $rabat = $netRabat = (float) $line['rabat'];
            $mRabat = (float) $line['m_rabat'] * -1;
            // Lines of one rabatgruppe follow each other; the last of them carries the group's quantity
            $groupQty[$x] = 0;
            if ($line['rabatgruppe']) {
                $groupQty[$x] = $antal;
                if ($x > 0 && $line['rabatgruppe'] == $lines[$x - 1]['rabatgruppe']) {
                    $groupQty[$x] += $groupQty[$x - 1];
                    $groupQty[$x - 1] = 0;
                }
            }
            if ($storedRate) {
                $price = $vatPrice;
                if ($mRabat) {
                    $mRabat += $mRabat / 100 * $rate;
                } elseif ($line['rabatart'] == 'amount' && $rabat) {
                    $rabat += $rabat / 100 * $rate;
                }
            } else {
                $price = afrund($netPrice, 2);
            }
            $netPrice = afrund($netPrice, 2);
            if ($line['rabatart'] == 'amount' && $rabat) {
                // ordrelinjerDataII.php takes the amount off the net price twice
                $net += afrund($antal * ($netPrice - 2 * $netRabat), 2);
                $gross += afrund($antal * ($price - $rabat), 3);
            } elseif ($mRabat && !$line['rabatgruppe']) {
                $gross += $antal * ($price + $mRabat);
                $net += $antal * ($netPrice + $mRabat);
            } else {
                $net += afrund($antal * ($netPrice - $netPrice / 100 * $rabat), 2);
                $gross += afrund($antal * ($price - $price * $rabat / 100), 3);
            }
            // productLines.php draws a set's line (head or item) at 0 before it computes a rabatgruppe's discount
            $lines[$x]['price'] = ((int) $line['saet'] && $line['posnr'] > 0) ? 0 : $price;
        }
        // productLines.php, which keeps the item list and the price from one line to the next as it does here
        $items = [];
        $followPrice = 0;
        foreach ($lines as $x => $line) {
            $antal = (float) $line['antal'];
            if ($line['folgevare'] > 0 || $line['tilfravalg']) {
                if ($line['tilfravalg']) {
                    $items = explode(chr(9), $line['tilfravalg']);
                } else {
                    $items[0] = $line['folgevare'];
                }
                foreach ($items as $item) {
                    if ((int) $item > 0) {
                        $followPrice = self::posFollowItemPrice((int) $item, $regnaar);
                    }
                    $gross += afrund($antal * $followPrice, 2);
                }
            }
            if ($groupQty[$x]) {
                list($groupDiscount, $groupDiscountType) = explode(";", grupperabat($groupQty[$x], $line['rabatgruppe']));
                $groupDiscount = (float) $groupDiscount;
                if ($groupDiscount) {
                    if ($groupDiscountType != 'amount') {
                        $groupDiscount = $line['price'] / 100 * $groupDiscount;
                    }
                    $gross += afrund($groupDiscount * $groupQty[$x], 2);
                }
            }
        }
        $r = db_fetch_array(db_select("SELECT pris FROM ordrelinjer WHERE ordre_id = $orderId AND varenr = 'R'", __FILE__ . " linje " . __LINE__));
        if ($r) {
            $gross += (float) $r['pris'];
        }
        $moms = afrund($gross, 2) - $net;
        if (abs($moms) < 0.1) {
            $moms = 0;
        }
        return ['sum' => $net, 'moms' => $moms];
    }

    /**
     * A følgevare's or tilfravalg's price with VAT as productLines.php shows it: the item's salgspris, plus VAT from its
     * group's account unless the group is momsfri (VG box7).
     *
     * @param int $vareId
     * @param int $regnaar
     * @return float
     */
    private static function posFollowItemPrice($vareId, $regnaar)
    {
        $r = db_fetch_array(db_select("SELECT salgspris, gruppe FROM varer WHERE id = $vareId", __FILE__ . " linje " . __LINE__));
        if (!$r) {
            return 0.0;
        }
        $gruppe = (int) $r['gruppe'];
        $qtxt = "SELECT box4, box7 FROM grupper WHERE art = 'VG' AND kodenr = '$gruppe' AND fiscal_year = '$regnaar'";
        $group = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        if ($group && $group['box7']) {
            return (float) $r['salgspris'];
        }
        $vatCode = $group ? self::accountVatCode($group['box4'], $regnaar) : '';
        $rate = $vatCode ? self::salesVatRate($vatCode, $regnaar) : 0.0;
        return (float) $r['salgspris'] + (float) $r['salgspris'] * $rate / 100;
    }

    /**
     * The totals kreditor/ordre.php stores when it draws a purchase order (orderIncludes/openOrderLines.php): each line
     * with a varenr and posnr > 0 adds (pris - rabat %) x antal to øre, and moms is the order's rate of the lines that are
     * neither momsfri nor omvendt betalingspligt, nudged by 0.0001 (down on a credit note) and rounded to 3 decimals.
     *
     * @param int                  $orderId
     * @param array<string, mixed> $order ordrer row: art, momssats.
     * @return array{sum: float, moms: float}
     */
    private static function purchase($orderId, array $order)
    {
        $sum = $vatBase = 0;
        $q = db_select("SELECT * FROM ordrelinjer WHERE ordre_id = $orderId AND posnr > 0", __FILE__ . " linje " . __LINE__);
        while ($line = db_fetch_array($q)) {
            if (trim((string) $line['varenr'])) {
                $pris = (float) $line['pris'];
                $amount = afrund(($pris - $pris / 100 * (float) $line['rabat']) * (float) $line['antal'], 2);
                $sum += $amount;
                if ($line['momsfri'] != 'on' && !$line['omvbet']) {
                    $vatBase += $amount;
                }
            }
        }
        $moms = afrund($vatBase / 100 * (float) $order['momssats'] + ($order['art'] == 'KK' ? -0.0001 : 0.0001), 3);
        return ['sum' => $sum, 'moms' => $moms];
    }

    /**
     * The VAT code of account $kontonr in fiscal year $regnaar (kontoplan.moms, e.g. 'S1'), or '' when it has none.
     *
     * @param mixed $kontonr
     * @param int   $regnaar
     * @return string
     */
    private static function accountVatCode($kontonr, $regnaar)
    {
        $kontonr = (int) $kontonr;
        $qtxt = "SELECT moms FROM kontoplan WHERE kontonr = '$kontonr' AND regnskabsaar = '$regnaar'";
        $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        return $r ? trim((string) $r['moms']) : '';
    }

    /**
     * The rate of sales VAT code $vatCode ('S1' is SM group 1) in fiscal year $regnaar, 0 when the group has none.
     *
     * @param string $vatCode
     * @param int    $regnaar
     * @return float
     */
    private static function salesVatRate($vatCode, $regnaar)
    {
        $kodenr = (int) substr($vatCode, 1);
        $qtxt = "SELECT box2 FROM grupper WHERE art = 'SM' AND kodenr = '$kodenr' AND fiscal_year = '$regnaar'";
        $r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
        return $r ? (float) $r['box2'] : 0.0;
    }
}
