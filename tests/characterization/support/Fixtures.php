<?php
// tests/characterization/support/Fixtures.php
//
// Shared business-data builders for the DB-backed characterization suites.
//
// The template tenant (saldi_2) is an empty installation: it carries the
// chart of accounts, the VAT groups and the fiscal year, but no debtors,
// creditors, items or orders. Every suite therefore has to build its own
// data, and building it twice in two different shapes is how characterization
// tests start disagreeing with each other. These helpers are the single
// definition of "a debtor", "an item", "an order" for the whole suite.
//
// Rows are written directly over pg rather than through the pages, because
// the point of each test is to exercise ONE code path (posting, invoicing,
// dunning) with everything around it held still. Where the shape of a row
// matters to the code under test, the comment says which file reads it.
//
// History:
// 20260725 CL/LH Created for the end-to-end coverage push.

require_once __DIR__ . '/CharacterizationEnv.php';

final class Fixtures
{
    /** Distinguishes fixture rows from the template tenant's own seed rows. */
    public const TAG = 'chartest';

    /** @var resource|\PgSql\Connection */
    private $conn;
    private int $regnaar;

    /** Monotonic per-instance counter so repeated calls never collide. */
    private static int $seq = 0;

    public function __construct($conn, int $regnaar)
    {
        $this->conn = $conn;
        $this->regnaar = $regnaar;
    }

    public function regnaar(): int
    {
        return $this->regnaar;
    }

    private static function nextSeq(): int
    {
        return ++self::$seq;
    }

    // ---------------------------------------------------------------- accounts

    /**
     * Finance accounts with no VAT code, for entries that must not trigger
     * the VAT split in finans/bogfor.php.
     *
     * @return int[] kontonr values, at least $count of them
     */
    public function plainAccounts(int $count = 2): array
    {
        $rows = CharacterizationEnv::rows(
            $this->conn,
            "SELECT kontonr FROM kontoplan
             WHERE regnskabsaar = $1 AND kontotype = 'D' AND (moms IS NULL OR moms = '')
             ORDER BY kontonr LIMIT $2",
            [$this->regnaar, $count]
        );
        if (count($rows) < $count) {
            throw new RuntimeException("template tenant has fewer than $count plain finance accounts");
        }
        return array_map(fn($r) => (int)$r['kontonr'], $rows);
    }

    /**
     * A sales account carrying an outgoing-VAT code, together with the VAT
     * account and percentage its grupper row points at.
     *
     * @return array{0:int,1:int,2:float} [sales account, VAT account, VAT %]
     */
    public function vatSalesAccount(): array
    {
        return $this->vatAccount('S', 'SM');
    }

    /**
     * The purchase-side equivalent: an account with a K-prefixed VAT code and
     * the KM group behind it.
     *
     * @return array{0:int,1:int,2:float} [purchase account, VAT account, VAT %]
     */
    public function vatPurchaseAccount(): array
    {
        return $this->vatAccount('K', 'KM');
    }

    /** @return array{0:int,1:int,2:float} */
    private function vatAccount(string $prefix, string $art): array
    {
        $acct = CharacterizationEnv::one(
            $this->conn,
            "SELECT kontonr, moms FROM kontoplan
             WHERE regnskabsaar = $1 AND moms LIKE $2 ORDER BY kontonr LIMIT 1",
            [$this->regnaar, $prefix . '%']
        );
        if ($acct === null) {
            throw new RuntimeException("template tenant has no {$prefix}-VAT-coded account");
        }
        $kode = (int)substr(trim($acct['moms']), 1);
        $grp = CharacterizationEnv::one(
            $this->conn,
            "SELECT box1, box2 FROM grupper WHERE art = $1 AND kodenr = $2",
            [$art, $kode]
        );
        if ($grp === null) {
            throw new RuntimeException("$art VAT group missing for code $kode");
        }
        return [(int)$acct['kontonr'], (int)$grp['box1'], (float)$grp['box2']];
    }

    /**
     * A sales account carrying a NEW outgoing-VAT group at the given rate.
     *
     * The stock chart of accounts only ships 25% (grupper art='SM' kodenr=1),
     * which is the one rate where gross/net splits happen to divide cleanly.
     * Rounding behavior in momsberegning() (finans/bogfor.php:1131) only shows
     * itself at the awkward rates, so tests that care about it mint their own.
     *
     * @return array{0:int,1:int,2:float} [sales account, VAT account, VAT %]
     */
    public function vatSalesAccountAt(float $pct): array
    {
        [, $vatAcct] = $this->vatSalesAccount();   // reuse the stock outgoing-VAT account

        $next = CharacterizationEnv::one(
            $this->conn,
            "SELECT COALESCE(max(kodenr), 0) + 1 AS kodenr FROM grupper WHERE art = 'SM'"
        );
        $kodenr = (int)$next['kodenr'];
        CharacterizationEnv::rows(
            $this->conn,
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, fiscal_year)
             VALUES ($1, 'S', $2, 'SM', $3, $4, $5)",
            [self::TAG . " salgsmoms $pct%", $kodenr, (string)$vatAcct, (string)$pct, $this->regnaar]
        );

        // A free account number in the chart's spare high range.
        $free = CharacterizationEnv::one(
            $this->conn,
            "SELECT g AS kontonr FROM generate_series(9100, 9899) g
             WHERE NOT EXISTS (SELECT 1 FROM kontoplan k WHERE k.kontonr = g AND k.regnskabsaar = $1)
             ORDER BY g LIMIT 1",
            [$this->regnaar]
        );
        if ($free === null) {
            throw new RuntimeException('no free account number left in 9100-9899');
        }
        $kontonr = (int)$free['kontonr'];
        CharacterizationEnv::rows(
            $this->conn,
            "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, lukket, primo, saldo, regnskabsaar)
             VALUES ($1, $2, 'D', $3, '', 0, 0, $4)",
            [$kontonr, self::TAG . " salg $pct%", 'S' . $kodenr, $this->regnaar]
        );

        return [$kontonr, $vatAcct, $pct];
    }

    /** The debtor collective account (grupper art='DG' box1 -> kontoplan.kontonr). */
    public function debtorCollectiveAccount(): int
    {
        $grp = CharacterizationEnv::one($this->conn, "SELECT box2 FROM grupper WHERE art = 'DG' ORDER BY kodenr LIMIT 1");
        return (int)($grp['box2'] ?? 0);
    }

    // ------------------------------------------------------------ master data

    /**
     * A debtor. `kontonr` is the human account number the order rows carry;
     * `art` 'D' is what debitor/ pages filter on.
     *
     * @param array<string,mixed> $overrides
     * @return array{id:int, kontonr:string}
     */
    public function debtor(array $overrides = []): array
    {
        $n = self::nextSeq();
        $kontonr = (string)($overrides['kontonr'] ?? (91000 + $n));
        $gruppe = CharacterizationEnv::one($this->conn, "SELECT kodenr FROM grupper WHERE art = 'DG' ORDER BY kodenr LIMIT 1");

        $row = CharacterizationEnv::one(
            $this->conn,
            "INSERT INTO adresser (kontonr, firmanavn, addr1, postnr, bynavn, land, email, art, gruppe,
                                   betalingsbet, betalingsdage, rabat, kreditmax, oprettet)
             VALUES ($1, $2, 'Testvej 1', '8000', 'Aarhus', 'Danmark', $3, 'D', $4, $5, $6, $7, $8, CURRENT_DATE)
             RETURNING id, kontonr",
            [
                $kontonr,
                $overrides['firmanavn'] ?? self::TAG . ' kunde ' . $n,
                $overrides['email'] ?? self::TAG . $n . '@example.invalid',
                $gruppe ? (int)$gruppe['kodenr'] : 1,
                $overrides['betalingsbet'] ?? 'Netto',
                (int)($overrides['betalingsdage'] ?? 8),
                (float)($overrides['rabat'] ?? 0),
                (float)($overrides['kreditmax'] ?? 0),
            ]
        );
        return ['id' => (int)$row['id'], 'kontonr' => (string)$row['kontonr']];
    }

    /**
     * A creditor. Same table as debtors, distinguished only by art='K' and
     * the KG group.
     *
     * @param array<string,mixed> $overrides
     * @return array{id:int, kontonr:string}
     */
    public function creditor(array $overrides = []): array
    {
        $n = self::nextSeq();
        $kontonr = (string)($overrides['kontonr'] ?? (81000 + $n));
        $gruppe = CharacterizationEnv::one($this->conn, "SELECT kodenr FROM grupper WHERE art = 'KG' ORDER BY kodenr LIMIT 1");

        $row = CharacterizationEnv::one(
            $this->conn,
            "INSERT INTO adresser (kontonr, firmanavn, addr1, postnr, bynavn, land, email, art, gruppe,
                                   betalingsbet, betalingsdage, oprettet)
             VALUES ($1, $2, 'Leverandoervej 2', '8000', 'Aarhus', 'Danmark', $3, 'K', $4, 'Netto', 14, CURRENT_DATE)
             RETURNING id, kontonr",
            [
                $kontonr,
                $overrides['firmanavn'] ?? self::TAG . ' leverandoer ' . $n,
                $overrides['email'] ?? self::TAG . 'k' . $n . '@example.invalid',
                $gruppe ? (int)$gruppe['kodenr'] : 1,
            ]
        );
        return ['id' => (int)$row['id'], 'kontonr' => (string)$row['kontonr']];
    }

    /**
     * A stock item. `beholdning` seeds the on-hand quantity so delivery
     * paths have something to draw down.
     *
     * By default the item lands in a STOCK-TRACKED item group. That matters:
     * `linjeopdat()` (includes/ordrefunc.php:429) only touches
     * `varer.beholdning` when the group's box8 is 'on'; for a group without
     * it, delivering writes a `batch_salg` row and leaves the stock balance
     * alone. The stock chart ships both kinds - "Handelsvarer" is tracked,
     * "Ydelser" is not - and picking the wrong one makes a stock assertion
     * silently untestable. Pass `stocked => false` for a service item.
     *
     * @param array<string,mixed> $overrides
     * @return array{id:int, varenr:string, salgspris:float, kostpris:float, gruppe:int}
     */
    public function item(array $overrides = []): array
    {
        $n = self::nextSeq();
        $varenr = (string)($overrides['varenr'] ?? (self::TAG . $n));
        $stocked = ($overrides['stocked'] ?? true) ? 'on' : '';
        $gruppe = CharacterizationEnv::one(
            $this->conn,
            "SELECT kodenr FROM grupper
             WHERE art = 'VG' AND fiscal_year = $1 AND COALESCE(trim(box8), '') = $2
               AND COALESCE(trim(box3), '') <> '' AND COALESCE(trim(box4), '') <> ''
             ORDER BY kodenr LIMIT 1",
            [$this->regnaar, $stocked]
        );
        if ($gruppe === null) {
            throw new RuntimeException(
                $stocked === 'on'
                    ? 'template tenant has no stock-tracked item group (VG with box8=on)'
                    : 'template tenant has no untracked item group'
            );
        }

        $salgspris = (float)($overrides['salgspris'] ?? 125.00);
        $kostpris = (float)($overrides['kostpris'] ?? 75.00);
        $row = CharacterizationEnv::one(
            $this->conn,
            "INSERT INTO varer (varenr, beskrivelse, enhed, salgspris, kostpris, beholdning,
                                min_lager, max_lager, gruppe, lukket)
             VALUES ($1, $2, 'stk', $3, $4, $5, $6, $7, $8, '')
             RETURNING id, varenr",
            [
                $varenr,
                $overrides['beskrivelse'] ?? self::TAG . ' vare ' . $n,
                $salgspris,
                $kostpris,
                (float)($overrides['beholdning'] ?? 100),
                (float)($overrides['min_lager'] ?? 0),
                (float)($overrides['max_lager'] ?? 0),
                (int)$gruppe['kodenr'],
            ]
        );
        return [
            'id' => (int)$row['id'],
            'varenr' => (string)$row['varenr'],
            'salgspris' => $salgspris,
            'kostpris' => $kostpris,
            'gruppe' => (int)$gruppe['kodenr'],
        ];
    }

    // ------------------------------------------------------------------ orders

    /**
     * A debtor order with one or more lines.
     *
     * `sum`/`moms` are stored on the header the way debitor/ordre.php leaves
     * them after a save, because includes/ordrefunc.php reads them back
     * rather than recomputing from the lines.
     *
     * @param array{id:int,kontonr:string}      $debtor
     * @param list<array{item:array,antal?:float,pris?:float,rabat?:float,momssats?:float}> $lines
     * @param array<string,mixed>               $overrides
     * @return array{id:int, ordrenr:int, sum:float, moms:float}
     */
    public function order(array $debtor, array $lines, array $overrides = []): array
    {
        $art = (string)($overrides['art'] ?? 'DO');
        $momssats = (float)($overrides['momssats'] ?? 25);

        $sum = 0.0;
        foreach ($lines as $line) {
            $antal = (float)($line['antal'] ?? 1);
            $pris = (float)($line['pris'] ?? $line['item']['salgspris']);
            $rabat = (float)($line['rabat'] ?? 0);
            $sum += round($antal * $pris * (1 - $rabat / 100), 2);
        }
        $sum = round($sum, 2);
        $moms = round($sum * $momssats / 100, 2);

        $order = CharacterizationEnv::one(
            $this->conn,
            "INSERT INTO ordrer (konto_id, kontonr, firmanavn, addr1, postnr, bynavn, land, email,
                                 betalingsbet, betalingsdage, art, valuta, valutakurs, sprog,
                                 ordredate, levdate, ordrenr, sum, moms, momssats, status, udskriv_til, ref)
             VALUES ($1, $2, $3, 'Testvej 1', '8000', 'Aarhus', 'Danmark', $4,
                     'Netto', $5, $6, $7, 100, '0',
                     $8, $8, (SELECT COALESCE(max(ordrenr), 0) + 1 FROM ordrer), $9, $10, $11, $12, 'email', $13)
             RETURNING id, ordrenr",
            [
                $debtor['id'],
                $debtor['kontonr'],
                $overrides['firmanavn'] ?? self::TAG . ' kunde',
                $overrides['email'] ?? self::TAG . '@example.invalid',
                (int)($overrides['betalingsdage'] ?? 8),
                $art,
                $overrides['valuta'] ?? 'DKK',
                $overrides['ordredate'] ?? date('Y-m-d'),
                $sum,
                $moms,
                $momssats,
                (int)($overrides['status'] ?? 1),
                self::TAG,
            ]
        );
        $orderId = (int)$order['id'];

        $pos = 0;
        foreach ($lines as $line) {
            $pos++;
            $item = $line['item'];
            CharacterizationEnv::rows(
                $this->conn,
                "INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, beskrivelse, enhed, posnr, antal,
                                          pris, kostpris, rabat, momssats, procent, leveres, leveret, oprettet_af)
                 VALUES ($1, $2, $3, $4, 'stk', $5, $6, $7, $8, $9, $10, 100, 0, 0, $11)",
                [
                    $orderId,
                    $item['id'],
                    $item['varenr'],
                    $line['beskrivelse'] ?? (self::TAG . ' linje'),
                    $pos,
                    (float)($line['antal'] ?? 1),
                    (float)($line['pris'] ?? $item['salgspris']),
                    (float)($line['kostpris'] ?? $item['kostpris']),
                    (float)($line['rabat'] ?? 0),
                    (float)($line['momssats'] ?? $momssats),
                    self::TAG,
                ]
            );
        }

        return ['id' => $orderId, 'ordrenr' => (int)$order['ordrenr'], 'sum' => $sum, 'moms' => $moms];
    }

    /**
     * A creditor (purchase) order. Same table and same lifecycle functions as
     * a debtor order - the difference is art='KO' and that delivering it
     * RECEIVES goods rather than shipping them.
     *
     * @param array{id:int,kontonr:string} $creditor
     * @param list<array{item:array,antal?:float,pris?:float,rabat?:float}> $lines
     * @param array<string,mixed> $overrides
     * @return array{id:int, ordrenr:int, sum:float, moms:float}
     */
    public function creditorOrder(array $creditor, array $lines, array $overrides = []): array
    {
        return $this->order($creditor, $lines, $overrides + ['art' => 'KO']);
    }

    // ----------------------------------------------------------------- dunning

    /**
     * The settings debitor/ny_rykker.php reads before it does anything:
     * the three grace-day counts and the fee/interest items.
     *
     * A stock tenant ships none of this - grupper DIV/4 box5-7 are empty and
     * there are no GEBYR formularer rows - so the dunning run finds nothing to
     * do. Tests that care about dunning have to supply it.
     *
     * ffdage1 is the days an invoice may stay overdue before it is dunned at
     * all; ffdage2 and ffdage3 are how long an R1 / R2 dunning may stand
     * before it is booked and escalated (ny_rykker.php:132/145).
     *
     * @return array{fee_item:array, fee:float}
     */
    public function dunningConfig(int $ffdage1, int $ffdage2, int $ffdage3, float $fee = 100.0): array
    {
        CharacterizationEnv::rows(
            $this->conn,
            "UPDATE grupper SET box5 = $1, box6 = $2, box7 = $3 WHERE art = 'DIV' AND kodenr = '4'",
            [(string)$ffdage1, (string)$ffdage2, (string)$ffdage3]
        );

        $feeItem = $this->item(['salgspris' => $fee, 'kostpris' => 0, 'beskrivelse' => self::TAG . ' rykkergebyr']);
        $interestItem = $this->item(['salgspris' => 0, 'kostpris' => 0, 'beskrivelse' => self::TAG . ' rente']);

        // One GEBYR row per dunning level: xb points at the fee item, yb at
        // the interest item, str carries the interest rate.
        CharacterizationEnv::rows($this->conn, "DELETE FROM formularer WHERE beskrivelse = 'GEBYR'");
        foreach ([6, 7, 8] as $formular) {
            CharacterizationEnv::rows(
                $this->conn,
                "INSERT INTO formularer (formular, art, beskrivelse, xb, yb, str)
                 VALUES ($1, 0, 'GEBYR', $2, $3, 0)",
                [$formular, $feeItem['id'], $interestItem['id']]
            );
        }

        return ['fee_item' => $feeItem, 'fee' => $fee];
    }

    /**
     * An unsettled open post standing directly on a debtor, dated in the past.
     *
     * Dunning works off `openpost`, not off orders, so this is the cheapest
     * way to put an overdue receivable in front of it.
     */
    public function openPost(array $debtor, float $amount, int $daysOverdue, string $faktnr): int
    {
        $due = date('Y-m-d', strtotime("-$daysOverdue days"));
        $row = CharacterizationEnv::one(
            $this->conn,
            "INSERT INTO openpost (konto_id, konto_nr, faktnr, amount, beskrivelse, udlignet,
                                   transdate, forfaldsdate, valuta, valutakurs, projekt)
             VALUES ($1, $2, $3, $4, $5, '0', $6, $6, 'DKK', 100, '')
             RETURNING id",
            [$debtor['id'], $debtor['kontonr'], $faktnr, $amount, self::TAG . ' faktura ' . $faktnr, $due]
        );
        return (int)$row['id'];
    }

    // -------------------------------------------------------------- kassekladde

    /**
     * A journal (kladde) with lines, ready to be posted by
     * support/run_bogfor_page.php.
     *
     * The id is allocated with MAX(id)+1 rather than the column default
     * because that is what every writer in the application does
     * (finans/kassekladde.php:1045) - see CharacterizationEnv::nextId().
     *
     * @param list<array{amount:float,debet?:?int,kredit?:?int,bilag?:int,tekst?:string,d_type?:string,k_type?:string,faktura?:string,transdate?:string}> $lines
     */
    public function kladde(array $lines, string $note = self::TAG): int
    {
        $kladdeId = CharacterizationEnv::nextId($this->conn, 'kladdeliste');
        CharacterizationEnv::rows(
            $this->conn,
            "INSERT INTO kladdeliste (id, kladdedate, bogfort, kladdenote, hvem, oprettet_af, tidspkt)
             VALUES ($1, CURRENT_DATE, '', $2, $3, $3, $4)",
            [$kladdeId, $note, self::TAG, (string)microtime(true)]
        );

        foreach ($lines as $line) {
            CharacterizationEnv::rows(
                $this->conn,
                "INSERT INTO kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit,
                                          amount, faktura, momsfri, valuta, valutakurs, forfaldsdate, kladde_id)
                 VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14)",
                [
                    $line['bilag'] ?? 1,
                    $line['transdate'] ?? date('Y-m-d'),
                    $line['tekst'] ?? self::TAG,
                    $line['d_type'] ?? 'F',
                    $line['debet'] ?? null,
                    $line['k_type'] ?? 'F',
                    $line['kredit'] ?? null,
                    $line['amount'],
                    $line['faktura'] ?? '',
                    $line['momsfri'] ?? '',
                    $line['valuta'] ?? null,
                    $line['valutakurs'] ?? null,
                    $line['forfaldsdate'] ?? null,
                    $kladdeId,
                ]
            );
        }
        return $kladdeId;
    }
}
