<?php
// tests/characterization/finans/MomsRubrikOrderLineValueTest.test.php
//
// Regression test for audit finding p1-reports-F3 (integration 2026-11-14,
// inherited from every source): Momsrubrikker
// (finans/rapport_includes/moms_rubrik.php) valued every order line as
// pris * (1 - rabat/100) * antal. A discount in kr per unit (rabatart 'amount')
// was read as a percentage and the line's procent (procentfakturering) was
// left out, so an EU invoice line of 500 with a discount of 50 kr, posted at
// 450, showed 250,00 in box B. A line without an item was not counted, although
// the invoice posts it to its own account.
//
// The boxes now hold each line with the amount its invoice posted: a sales
// invoice as bogfor_nu() in includes/ordrefunc.php posts it, a purchase invoice
// as kreditor/bogfor.php posts it, at the order's exchange rate.
//
// On the 12th of the fiscal year's first month the test invoices through
// ordrefunc's bogfor() (support/run_order_invoice.php):
// - to an EU business customer in DKK: 2 x 250 less 25 kr per unit (450),
//   1 x 200 less 10 % (180), 1 x 400 at 50 % (procentfakturering, 200), and a
//   line without an item of 100 on the box B services account 1150,
// - to the same customer in EUR at 750: 2 x 80 less 10 EUR per unit (140 EUR,
//   1.050,00 DKK),
// - to a customer outside the EU: 3 x 120 less 20 kr per unit (300),
// and adds an EU purchase posted as kreditor/bogfor.php posts it: 1 x 1.000 and
// 1 x 500 less 10 % on the box A goods account 1630, and a line without an item
// of 200 on the box A services account 1710. It checks the boxes by value and
// against what the invoices posted.
//
// A quantity discount (m_rabat) is invoiced as a line of its own: bogfor()
// copies the discounted line with pris -m_rabat, the same position, the rabat
// item of Indstillinger > Diverse > Ordrerelaterede valg (vare_id 0 when there
// is none, as in the template tenant) and the account the first discounted line
// had. On the 13th the test sells a services item, 2 x 500 less 50 kr per unit,
// to the EU business customer without a rabat item, and on the 14th a goods item
// (2 x 500 less 50) and a services item (1 x 400 less 40) on one invoice with a
// rabat item in the goods group. Each discount belongs in the box of the line it
// discounts.
//
// A credit note made with Kred on the order page (debitor/ordre.php) copies the
// invoice's lines with the quantity negated and the invoice line's id in
// kred_linje_id. It renumbers the positions, and none of its lines gets m_rabat.
// On the 15th the test credits the invoice of the 13th, on the 16th that of the
// 14th, through the page, and invoices the credit notes through bogfor(). Each
// credit note's boxes mirror its invoice's, and the 13th to the 16th net to 0.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261119 CL/ASR Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';
require_once __DIR__ . '/../support/PageForm.php';

final class MomsRubrikOrderLineValueTest extends CharacterizationTestCase
{
    /** The rubrik rows, in the order the screen lists them. */
    private const RUBRIKKER = ['A-varer', 'A-ydelser', 'B-varer', 'B-ydelser', 'C'];
    private const EU_CVR = 'DE123456789';

    /** The day everything is invoiced and posted on (Y-m-d), the 12th of the fiscal year's first month. */
    private static string $day = '';
    /** @var array{box3:string, box4:string, box5:string, box6:string, box7:string} the MR rubrik accounts of the installed tenant */
    private static array $mr = [];
    /** @var array<string, int> the test's orders by what they are */
    private static array $orders = [];
    /** @var array<int, string> the days with a quantity discount (Y-m-d), by day of the month */
    private static array $discountDays = [];
    /** @var array<int, int> the order with a quantity discount, by day of the month */
    private static array $discountOrders = [];
    /** @var array<int, int> the credit note made with Kred, by day of the month (15: of the 13th, 16: of the 14th) */
    private static array $creditNotes = [];
    /** The item that holds a quantity discount on the 14th (Indstillinger > Diverse > Ordrerelaterede valg). */
    private static int $rabatItem = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        CharacterizationEnv::resyncSequences(self::$tenant);
        // The report writes its CSV to temp/<db>/, which the login creates; these requests skip the login.
        $tempDir = CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb();
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0770, true);
        }

        $ra = self::one("SELECT box1, box2 FROM grupper WHERE art = 'RA' AND kodenr = $1", [self::$regnaar]);
        self::$day = sprintf('%04d-%02d-12', (int)$ra['box2'], (int)$ra['box1']);
        self::$mr = self::one("SELECT box3, box4, box5, box6, box7 FROM grupper WHERE art = 'MR' ORDER BY id LIMIT 1");

        // An EU business customer (EU-zone B2B-EU, no VAT code, an EU VAT number, so the invoice posts an item line to
        // its group's EU sales account 1050), a customer outside the EU (B2C-UDL) and an EU supplier (B2B-EU).
        $customer = self::$fx->debtor();
        self::rows('UPDATE adresser SET gruppe = $2, cvrnr = $3, land = $4 WHERE id = $1', [$customer['id'], self::group('DG', 'B2B-EU'), self::EU_CVR, 'Tyskland']);
        $abroad = self::$fx->debtor();
        self::rows('UPDATE adresser SET gruppe = $2, land = $3 WHERE id = $1', [$abroad['id'], self::group('DG', 'B2C-UDL'), 'Norge']);
        $supplier = self::$fx->creditor();
        self::rows('UPDATE adresser SET gruppe = $2, cvrnr = $3, land = $4 WHERE id = $1', [$supplier['id'], self::group('KG', 'B2B-EU'), 'DE987654321', 'Tyskland']);
        // A Handelsvarer item: its group's EU sales account (box12) is the MR box B goods account 1050.
        $item = self::$fx->item();

        // In DKK: a discount in kr per unit, one in percent, a line invoiced at 50 % and a line without an item.
        self::$orders['eu dkk'] = self::invoice($customer, [
            ['item' => $item, 'antal' => 2, 'pris' => 250.00, 'rabat' => 25.00, 'rabatart' => 'amount'],
            ['item' => $item, 'antal' => 1, 'pris' => 200.00, 'rabat' => 10.00],
            ['item' => $item, 'antal' => 1, 'pris' => 400.00, 'procent' => 50.00],
            ['konto' => (int)self::$mr['box6'], 'antal' => 1, 'pris' => 100.00, 'beskrivelse' => 'Konsulentydelse'],
        ], 930.00);

        // In EUR at 750 on the invoice date (grupper art 'VK' and its rate), with an exchange difference account.
        self::euroRate(750.00);
        self::$orders['eu eur'] = self::invoice($customer, [
            ['item' => $item, 'antal' => 2, 'pris' => 80.00, 'rabat' => 10.00, 'rabatart' => 'amount'],
        ], 140.00, 'EUR');

        self::$orders['abroad'] = self::invoice($abroad, [
            ['item' => $item, 'antal' => 3, 'pris' => 120.00, 'rabat' => 20.00, 'rabatart' => 'amount'],
        ], 300.00);

        self::$orders['purchase'] = self::purchase($supplier, $item);

        // Quantity discounts to the EU business customer, a services item (Varer/ydelser 'ydelser', EU sales account
        // the MR box B services account 1150) beside the goods item.
        foreach ([13, 14, 15, 16] as $d) {
            self::$discountDays[$d] = sprintf('%04d-%02d-%02d', (int)$ra['box2'], (int)$ra['box1'], $d);
        }
        $services = self::$fx->item();
        self::rows('UPDATE varer SET gruppe = $2 WHERE id = $1', [$services['id'], self::servicesGroup($item['gruppe'])]);
        // The template tenant has no rabat item: the discount line gets vare_id 0 and the discounted item's varenr.
        self::$discountOrders[13] = self::invoiceQuantityDiscount($customer, [[$services, 2, 500.00, 50.00]], self::$discountDays[13]);
        // A rabat item in the goods group: the discount lines get its id.
        $rabat = self::$fx->item(['salgspris' => 0, 'kostpris' => 0]);
        self::$rabatItem = $rabat['id'];
        self::rows("UPDATE grupper SET box2 = $1 WHERE art = 'DIV' AND kodenr = '3'", [(string)$rabat['id']]);
        self::$discountOrders[14] = self::invoiceQuantityDiscount($customer, [[$item, 2, 500.00, 50.00], [$services, 1, 400.00, 40.00]], self::$discountDays[14]);

        // The credit notes, made with Kred on the order page and invoiced on the 15th and the 16th.
        self::$creditNotes[15] = self::creditWithKred(self::$discountOrders[13], self::$discountDays[15]);
        self::$creditNotes[16] = self::creditWithKred(self::$discountOrders[14], self::$discountDays[16]);
    }

    /**
     * Credit invoice $invoiceId as a user does: open it on debitor/ordre.php, press Kredit with the form as the page
     * draws it, then invoice the credit note the page created on $day through ordrefunc's bogfor().
     *
     * @return int the credit note's order id
     */
    private static function creditWithKred(int $invoiceId, string $day): int
    {
        $page = static function (array $post) use ($invoiceId): string {
            $res = self::runChild('run_debitor_order_page.php', ["id=$invoiceId", CharacterizationEnv::sessionId(), $post === [] ? '' : http_build_query($post)]);
            $out = array_values(array_filter(explode("\n", trim($res['stdout']))));
            $json = $out ? json_decode(end($out), true) : null;
            if (!is_array($json) || str_contains((string)($json['html'] ?? ''), 'Fatal error')) {
                throw new RuntimeException("debitor/ordre.php?id=$invoiceId failed.\nstdout: " . substr($res['stdout'], -2000) . "\nstderr: " . substr($res['stderr'], -2000));
            }
            return (string)$json['html'];
        };
        $html = $page([]);
        if (!str_contains($html, 'value="Kredit" name="b_submit"')) {
            throw new RuntimeException("debitor/ordre.php?id=$invoiceId offers no Kredit");
        }
        $form = PageForm::fields($html, 'ordre');
        $form['b_submit'] = 'Kredit';
        $page($form);
        $credit = self::one("SELECT id FROM ordrer WHERE kred_ord_id = $1 AND art = 'DK'", [$invoiceId]);
        if ($credit === null) {
            throw new RuntimeException("Kredit made no credit note of order $invoiceId");
        }
        self::rows('UPDATE ordrer SET ordredate = $2 WHERE id = $1', [$credit['id'], $day]);
        $res = self::runChild('run_order_invoice.php', ['invoice', $credit['id'], CharacterizationEnv::testDb()]);
        $out = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $out = $out ? json_decode(end($out), true) : null;
        if (($out['bogfor'] ?? null) !== 'OK') {
            throw new RuntimeException("invoicing credit note {$credit['id']} failed.\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
        }
        return (int)$credit['id'];
    }

    /** A copy of item group $goods of the fiscal year with Varer/ydelser 'ydelser' and the MR box B services account as EU sales account. */
    private static function servicesGroup(int $goods): int
    {
        $new = self::one("SELECT max(kodenr::int) + 1 AS kodenr FROM grupper WHERE art = 'VG'");
        self::rows(
            "INSERT INTO grupper (beskrivelse, art, kodenr, kode, box1, box2, box3, box4, box5, box6, box7, box8, box9, box10, box11, box12, box13, box14, fiscal_year)
             SELECT $1, art, $2, kode, box1, box2, box3, box4, 'ydelser', box6, box7, box8, box9, box10, box11, $3, box13, box14, fiscal_year
             FROM grupper WHERE art = 'VG' AND kodenr = $4 AND fiscal_year = $5",
            [Fixtures::TAG . ' ydelser', (string)$new['kodenr'], self::$mr['box6'], (string)$goods, self::$regnaar]
        );
        return (int)$new['kodenr'];
    }

    /**
     * Invoice a sale on $day through ordrefunc's bogfor() with a quantity discount (m_rabat) in kr per unit on each line.
     *
     * @param array{id:int, kontonr:string} $customer
     * @param list<array{0:array, 1:float, 2:float, 3:float}> $lines item, antal, pris, m_rabat
     * @return int the order id
     */
    private static function invoiceQuantityDiscount(array $customer, array $lines, string $day): int
    {
        $sum = 0.0;
        $orderLines = [];
        foreach ($lines as [$item, $antal, $pris, $mRabat]) {
            $orderLines[] = ['item' => $item, 'antal' => $antal, 'pris' => $pris];
            $sum += $antal * ($pris - $mRabat);
        }
        $order = self::$fx->order($customer, $orderLines, ['ordredate' => $day, 'momssats' => 0]);
        self::rows('UPDATE ordrer SET cvrnr = (SELECT cvrnr FROM adresser WHERE id = $2), sum = $3, moms = 0 WHERE id = $1', [$order['id'], $customer['id'], $sum]);
        foreach ($lines as $i => $line) {
            self::rows("UPDATE ordrelinjer SET m_rabat = $3, rabat = 0, rabatart = '' WHERE ordre_id = $1 AND posnr = $2", [$order['id'], $i + 1, $line[3]]);
        }
        $res = self::runChild('run_order_invoice.php', ['invoice', $order['id'], CharacterizationEnv::testDb()]);
        $out = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $out = $out ? json_decode(end($out), true) : null;
        if (($out['bogfor'] ?? null) !== 'OK') {
            throw new RuntimeException("invoicing order {$order['id']} failed.\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
        }
        return $order['id'];
    }

    /** A new debtor ('DG') or creditor ('KG') group with EU-zone $zone, no VAT code, and the installed group's collective account. */
    private static function group(string $art, string $zone): int
    {
        $row = self::one(
            "INSERT INTO grupper (beskrivelse, art, kodenr, box1, box2, box3, box4, box10, fiscal_year)
             SELECT $1, art, (SELECT max(kodenr) + 1 FROM grupper WHERE art = $2), '', box2, box3, box4, $3, $4
             FROM grupper WHERE art = $2 ORDER BY kodenr LIMIT 1
             RETURNING kodenr",
            [Fixtures::TAG . ' ' . $zone, $art, $zone, self::$regnaar]
        );
        return (int)$row['kodenr'];
    }

    /** EUR (grupper art 'VK') at $rate from the test day, with an exchange difference account (box3) of the fiscal year. */
    private static function euroRate(float $rate): void
    {
        $difkto = self::one(
            "SELECT kontonr FROM kontoplan WHERE regnskabsaar = $1 AND kontotype = 'D' AND (moms IS NULL OR moms = '') ORDER BY kontonr LIMIT 1",
            [self::$regnaar]
        );
        $vk = self::one("SELECT COALESCE(max(kodenr), 0) + 1 AS kodenr FROM grupper WHERE art = 'VK'");
        self::rows(
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, fiscal_year) VALUES ('Euro', '', $1, 'VK', 'EUR', $2, $3, $4)",
            [(int)$vk['kodenr'], number_format($rate, 2, ',', ''), (string)$difkto['kontonr'], self::$regnaar]
        );
        self::rows('INSERT INTO valuta (gruppe, valdate, kurs) VALUES ($1, $2, $3)', [(int)$vk['kodenr'], self::$day, $rate]);
    }

    /**
     * Invoice a sale on the test day through ordrefunc's bogfor(), as remoteBooking does.
     *
     * A line is an item line (item, antal, pris, rabat, rabatart, procent) or a line without an item on an account of
     * its own (konto, antal, pris, beskrivelse). The order's sum is $sum, what its lines add up to, without VAT.
     *
     * @param array{id:int, kontonr:string} $customer
     * @param list<array<string, mixed>> $lines
     * @return int the order id
     */
    private static function invoice(array $customer, array $lines, float $sum, string $valuta = 'DKK'): int
    {
        $itemLines = array_values(array_filter($lines, static fn(array $l): bool => isset($l['item'])));
        $order = self::$fx->order($customer, $itemLines, ['ordredate' => self::$day, 'momssats' => 0, 'valuta' => $valuta]);
        self::rows('UPDATE ordrer SET cvrnr = (SELECT cvrnr FROM adresser WHERE id = $2), sum = $3, moms = 0 WHERE id = $1', [$order['id'], $customer['id'], $sum]);
        $posnr = 0;
        foreach ($lines as $line) {
            $posnr++;
            if (isset($line['item'])) {
                self::rows(
                    'UPDATE ordrelinjer SET rabatart = $3, procent = $4 WHERE ordre_id = $1 AND posnr = $2',
                    [$order['id'], $posnr, $line['rabatart'] ?? '', $line['procent'] ?? 100]
                );
            } else {
                self::rows(
                    "INSERT INTO ordrelinjer (ordre_id, varenr, beskrivelse, posnr, antal, pris, rabat, momssats, procent, bogf_konto, leveres, leveret)
                     VALUES ($1, '', $2, $3, $4, $5, 0, 0, 100, $6, 0, 0)",
                    [$order['id'], $line['beskrivelse'], $posnr, $line['antal'], $line['pris'], $line['konto']]
                );
            }
        }
        $res = self::runChild('run_order_invoice.php', ['invoice', $order['id'], CharacterizationEnv::testDb()]);
        $out = array_values(array_filter(explode("\n", trim($res['stdout']))));
        $out = $out ? json_decode(end($out), true) : null;
        if (($out['bogfor'] ?? null) !== 'OK') {
            throw new RuntimeException("invoicing order {$order['id']} failed.\nstdout: {$res['stdout']}\nstderr: {$res['stderr']}");
        }
        return $order['id'];
    }

    /**
     * An EU purchase posted as kreditor/bogfor.php posts it (status 4, each line on its bogf_konto at
     * pris * antal less its percent discount, the sum on the supplier group's collective account): 1 x 1.000 and
     * 1 x 500 less 10 % on the box A goods account, and a line without an item of 200 on the box A services account.
     *
     * @param array{id:int, kontonr:string} $supplier
     * @param array{id:int} $item
     * @return int the order id
     */
    private static function purchase(array $supplier, array $item): int
    {
        $box3 = (int)self::$mr['box3'];
        $box4 = (int)self::$mr['box4'];
        $order = self::$fx->creditorOrder($supplier, [
            ['item' => $item, 'antal' => 1, 'pris' => 1000.00],
            ['item' => $item, 'antal' => 1, 'pris' => 500.00, 'rabat' => 10.00],
        ], ['ordredate' => self::$day, 'momssats' => 0, 'status' => 4]);
        self::rows('UPDATE ordrer SET fakturadate = ordredate, fakturanr = $2, cvrnr = $3, sum = 1650 WHERE id = $1', [$order['id'], 'K' . $order['ordrenr'], 'DE987654321']);
        self::rows("UPDATE ordrelinjer SET bogf_konto = $2, projekt = '' WHERE ordre_id = $1", [$order['id'], $box3]);
        self::rows(
            "INSERT INTO ordrelinjer (ordre_id, varenr, beskrivelse, posnr, antal, pris, rabat, momssats, procent, bogf_konto, projekt)
             VALUES ($1, '', 'Fragt', 3, 1, 200, 0, 0, 100, $2, '')",
            [$order['id'], $box4]
        );
        $collective = self::one("SELECT g.box2 FROM adresser a JOIN grupper g ON g.art = 'KG' AND g.kodenr = a.gruppe WHERE a.id = $1", [$supplier['id']]);
        foreach ([[$box3, 1450.00, 0], [$box4, 200.00, 0], [(int)$collective['box2'], 0, 1650.00]] as [$konto, $debet, $kredit]) {
            self::rows(
                "INSERT INTO transaktioner (kontonr, bilag, transdate, beskrivelse, faktura, debet, kredit, kladde_id, afd, ordre_id, logdate, logtime)
                 VALUES ($1, 0, $2, $3, $4, $5, $6, 0, 0, $7, CURRENT_DATE, '12:00')",
                [$konto, self::$day, Fixtures::TAG . ' EU koeb', 'K' . $order['ordrenr'], $debet, $kredit, $order['id']]
            );
        }
        return $order['id'];
    }

    /**
     * Finans -> Rapporter -> Momsrubrikker for $day (the test day when empty), or from $day to $to, as the front page
     * posts it after OK.
     */
    private function report(string $day = '', string $to = ''): string
    {
        [$aar, $maaned, $dato] = array_map('intval', explode('-', $day !== '' ? $day : self::$day));
        [$aarTil, $maanedTil, $datoTil] = $to !== '' ? array_map('intval', explode('-', $to)) : [$aar, $maaned, $dato];
        $form = [
            'regnaar' => (string)self::$regnaar,
            'rapportart' => 'moms_rubrik',
            'maaned_fra' => "$aar|$maaned",
            'dato_fra' => (string)$dato,
            'maaned_til' => "$aarTil|$maanedTil",
            'dato_til' => (string)$datoTil,
            'submit' => ' OK ',
        ];
        return self::runChild('run_finans_rapport_page.php', [CharacterizationEnv::sessionId(), '', http_build_query($form)])['stdout'];
    }

    /**
     * The rubrik rows and I alt of the Momsrubrikker page, as the screen shows them.
     *
     * @return array<string, string|null>
     */
    private static function screenBoxes(string $html): array
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $rows = [];
        foreach ($doc->getElementsByTagName('tr') as $row) {
            $cells = [];
            foreach ($row->childNodes as $cell) {
                if ($cell instanceof DOMElement && strtolower($cell->nodeName) === 'td') {
                    $cells[] = trim((string)preg_replace('/\s+/u', ' ', $cell->textContent));
                }
            }
            if (count($cells) > 1 && !isset($rows[$cells[0]])) {
                $rows[$cells[0]] = (string)end($cells);
            }
        }
        $screen = [];
        foreach ([...self::RUBRIKKER, 'I alt'] as $kode) {
            $screen[$kode] = $rows[$kode] ?? null;
        }
        return $screen;
    }

    /** A Danish amount as dkdecimal() prints it, e.g. 1.000,00, as a number. */
    private static function amount(string $dk): float
    {
        return (float)str_replace(['.', ','], ['', '.'], $dk);
    }

    /** What order $orderId posted on account $konto, credit minus debit (or debit minus credit with $debit), in DKK. */
    private static function posted(int $orderId, int $konto, bool $debit = false): float
    {
        $r = self::one(
            'SELECT COALESCE(SUM(COALESCE(kredit, 0) - COALESCE(debet, 0)), 0) AS net FROM transaktioner WHERE ordre_id = $1 AND kontonr = $2',
            [$orderId, $konto]
        );
        return round(($debit ? -1 : 1) * (float)$r['net'], 2);
    }

    public function test_the_invoices_posted_the_lines_with_their_discounts(): void
    {
        $box5 = (int)self::$mr['box5'];
        $box6 = (int)self::$mr['box6'];
        $state = [];
        foreach (self::$orders as $kind => $id) {
            $state[$kind] = (int)self::one('SELECT status FROM ordrer WHERE id = $1', [$id])['status'];
        }
        $this->assertSame(['eu dkk' => 4, 'eu eur' => 4, 'abroad' => 4, 'purchase' => 4], $state, 'every invoice is posted');
        $abroad = self::one('SELECT COALESCE(SUM(kredit), 0) AS kredit FROM transaktioner WHERE ordre_id = $1', [self::$orders['abroad']]);
        $this->assertSame(
            [
                // 450 + 180 + 200 on the EU goods sales account, and the line without an item on its own account.
                'eu dkk' => [830.0, 100.0],
                // 140 EUR at 750.
                'eu eur' => [1050.0, 0.0],
                // 3 x 120 less 20 kr per unit, the only credit of that invoice.
                'abroad' => 300.0,
                // 1.000 + 450 on the box A goods account, 200 on the box A services account.
                'purchase' => [1450.0, 200.0],
            ],
            [
                'eu dkk' => [self::posted(self::$orders['eu dkk'], $box5), self::posted(self::$orders['eu dkk'], $box6)],
                'eu eur' => [self::posted(self::$orders['eu eur'], $box5), self::posted(self::$orders['eu eur'], $box6)],
                'abroad' => round((float)$abroad['kredit'], 2),
                'purchase' => [self::posted(self::$orders['purchase'], (int)self::$mr['box3'], true), self::posted(self::$orders['purchase'], (int)self::$mr['box4'], true)],
            ],
            'what each invoice posted, in DKK'
        );
    }

    /**
     * Each box holds its lines with the amounts the invoices posted. Before, B-varer was 2.035,00 (375 + 180 + 400 +
     * 1.080), B-ydelser and A-ydelser 0,00 and C 288,00.
     */
    public function test_the_boxes_hold_the_amounts_the_invoices_posted(): void
    {
        $screen = self::screenBoxes($this->report());
        $this->assertSame(
            [
                // The purchase's 1.000 and 500 less 10 %.
                'A-varer' => '1.450,00',
                // The purchase's line without an item on the box A services account.
                'A-ydelser' => '200,00',
                // 2 x 250 less 25 kr (450), 200 less 10 % (180), 400 at 50 % (200), and 2 x 80 EUR less 10 EUR at 750 (1.050).
                'B-varer' => '1.880,00',
                // The sale's line without an item on the box B services account.
                'B-ydelser' => '100,00',
                // 3 x 120 less 20 kr per unit.
                'C' => '300,00',
                'I alt' => '3.930,00',
            ],
            $screen,
            'the boxes on screen'
        );

        $box5 = (int)self::$mr['box5'];
        $box6 = (int)self::$mr['box6'];
        $this->assertSame(
            [
                'A-varer' => self::posted(self::$orders['purchase'], (int)self::$mr['box3'], true),
                'A-ydelser' => self::posted(self::$orders['purchase'], (int)self::$mr['box4'], true),
                'B-varer' => self::posted(self::$orders['eu dkk'], $box5) + self::posted(self::$orders['eu eur'], $box5),
                'B-ydelser' => self::posted(self::$orders['eu dkk'], $box6),
            ],
            [
                'A-varer' => self::amount($screen['A-varer']),
                'A-ydelser' => self::amount($screen['A-ydelser']),
                'B-varer' => self::amount($screen['B-varer']),
                'B-ydelser' => self::amount($screen['B-ydelser']),
            ],
            'each box is what the invoices posted on its accounts'
        );
    }

    /**
     * bogfor() invoiced each quantity discount as a line of its own at the position of the line it discounts, and
     * the invoice posted the lines less the discount.
     */
    public function test_the_quantity_discounts_were_invoiced_as_lines_of_their_own(): void
    {
        $lines = [];
        $posted = [];
        foreach (self::$discountOrders as $d => $id) {
            $this->assertSame(4, (int)self::one('SELECT status FROM ordrer WHERE id = $1', [$id])['status'], "the order of the {$d}th is posted");
            foreach (self::rows('SELECT posnr, vare_id, varenr, antal, pris, m_rabat FROM ordrelinjer WHERE ordre_id = $1 AND COALESCE(m_rabat, 0) = 0 ORDER BY posnr, id', [$id]) as $r) {
                $lines[$d][] = [(int)$r['posnr'], (int)$r['vare_id'], round((float)$r['antal'] * (float)$r['pris'], 2)];
            }
            $sum = self::one('SELECT COALESCE(SUM(COALESCE(kredit, 0) - COALESCE(debet, 0)), 0) AS net FROM transaktioner WHERE ordre_id = $1 AND kontonr IN ($2, $3)', [$id, (int)self::$mr['box5'], (int)self::$mr['box6']]);
            $all = self::one('SELECT COALESCE(SUM(COALESCE(kredit, 0)), 0) AS kredit, COALESCE(SUM(COALESCE(debet, 0)), 0) AS debet FROM transaktioner WHERE ordre_id = $1', [$id]);
            $posted[$d] = [round((float)$sum['net'], 2), round((float)$all['kredit'] - (float)$all['debet'], 2)];
        }
        $this->assertSame(
            [
                // Without a rabat item the discount line has no item (vare_id 0).
                13 => [[1, 0, -100.0]],
                // With a rabat item both discount lines carry it.
                14 => [[1, self::$rabatItem, -100.0], [2, self::$rabatItem, -40.0]],
            ],
            $lines,
            'the discount lines: position, item, amount'
        );
        $this->assertSame(
            [
                // The EU sales accounts hold the lines before the discount; the ledger balances.
                13 => [1000.0, 0.0],
                14 => [1400.0, 0.0],
            ],
            $posted,
            'what each invoice posted on the EU sales accounts, and that it balances'
        );
    }

    /**
     * A quantity discount counts in the box of the line it discounts. Before, the discount line without an item went by
     * the domestic sales account it was posted to (B-varer -100,00 beside B-ydelser 1.000,00 on the 13th), and a
     * discount line with the rabat item went by the rabat item's group (B-varer 860,00, B-ydelser 400,00 on the 14th).
     */
    public function test_a_quantity_discount_counts_in_the_box_of_the_line_it_discounts(): void
    {
        $this->assertSame(
            [
                13 => ['A-varer' => '0,00', 'A-ydelser' => '0,00', 'B-varer' => '0,00', 'B-ydelser' => '900,00', 'C' => '0,00', 'I alt' => '900,00'],
                14 => ['A-varer' => '0,00', 'A-ydelser' => '0,00', 'B-varer' => '900,00', 'B-ydelser' => '360,00', 'C' => '0,00', 'I alt' => '1.260,00'],
            ],
            [
                13 => self::screenBoxes($this->report(self::$discountDays[13])),
                14 => self::screenBoxes($this->report(self::$discountDays[14])),
            ],
            'the boxes on screen, day by day'
        );
    }

    /**
     * Kred copied each invoice line with the quantity negated and the invoice line's id in kred_linje_id, at positions
     * of its own and without m_rabat, and the credit notes posted the invoices' amounts back.
     */
    public function test_the_credit_notes_copy_the_discount_lines_with_a_link_to_the_invoice(): void
    {
        $lines = [];
        $posted = [];
        foreach ([15 => 13, 16 => 14] as $d => $of) {
            $credit = self::$creditNotes[$d];
            $this->assertSame(4, (int)self::one('SELECT status FROM ordrer WHERE id = $1', [$credit])['status'], "the credit note of the {$d}th is posted");
            $qtxt = 'SELECT c.posnr, c.antal, c.pris, c.m_rabat, i.posnr AS i_posnr, i.m_rabat AS i_m_rabat, i.pris AS i_pris'
                  . ' FROM ordrelinjer c JOIN ordrelinjer i ON i.id = c.kred_linje_id AND i.ordre_id = $2'
                  . ' WHERE c.ordre_id = $1 ORDER BY c.posnr, c.id';
            foreach (self::rows($qtxt, [$credit, self::$discountOrders[$of]]) as $r) {
                $lines[$d][] = [(int)$r['posnr'], round((float)$r['antal'] * (float)$r['pris'], 2), (float)$r['m_rabat'], (int)$r['i_posnr'], (float)$r['i_m_rabat'], (float)$r['i_pris']];
            }
            $all = self::one('SELECT COALESCE(SUM(COALESCE(kredit, 0)), 0) AS kredit, COALESCE(SUM(COALESCE(debet, 0)), 0) AS debet FROM transaktioner WHERE ordre_id = $1', [$credit]);
            $posted[$d] = [round((float)$all['debet'], 2), round((float)$all['kredit'] - (float)$all['debet'], 2)];
        }
        $this->assertSame(
            [
                // Position, amount, m_rabat; the invoice line it copies: position, m_rabat, pris.
                15 => [[1, -1000.0, 0.0, 1, 50.0, 500.0], [2, 100.0, 0.0, 1, 0.0, -50.0]],
                16 => [[1, -1000.0, 0.0, 1, 50.0, 500.0], [2, 100.0, 0.0, 1, 0.0, -50.0], [3, -400.0, 0.0, 2, 40.0, 400.0], [4, 40.0, 0.0, 2, 0.0, -40.0]],
            ],
            $lines,
            'the credit note lines and the invoice lines they copy'
        );
        $this->assertSame([15 => [900.0, 0.0], 16 => [1260.0, 0.0]], $posted, 'each credit note posts its invoice back, and balances');
    }

    /**
     * A credit note made with Kred counts in the boxes its invoice counts in, with the opposite sign, so the invoice and
     * its credit note together are 0 in every box. Before, the discount line with the rabat item on the credit note of
     * the 14th went by the rabat item's goods group while the invoice's went by the services line it discounts (B-varer
     * 40,00 and B-ydelser -40,00 over the 13th to the 16th); at HEAD the discount line without an item on the 13th was
     * left out while its copy on the credit note counted (B-ydelser 100,00).
     */
    public function test_a_credit_note_cancels_its_invoice_in_every_box(): void
    {
        $zero = ['A-varer' => '0,00', 'A-ydelser' => '0,00', 'B-varer' => '0,00', 'B-ydelser' => '0,00', 'C' => '0,00', 'I alt' => '0,00'];
        $this->assertSame(
            [
                15 => ['A-varer' => '0,00', 'A-ydelser' => '0,00', 'B-varer' => '0,00', 'B-ydelser' => '-900,00', 'C' => '0,00', 'I alt' => '-900,00'],
                16 => ['A-varer' => '0,00', 'A-ydelser' => '0,00', 'B-varer' => '-900,00', 'B-ydelser' => '-360,00', 'C' => '0,00', 'I alt' => '-1.260,00'],
                '13-16' => $zero,
            ],
            [
                15 => self::screenBoxes($this->report(self::$discountDays[15])),
                16 => self::screenBoxes($this->report(self::$discountDays[16])),
                '13-16' => self::screenBoxes($this->report(self::$discountDays[13], self::$discountDays[16])),
            ],
            'the credit notes day by day, and the invoices with their credit notes'
        );
    }
}
