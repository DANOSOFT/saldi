<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Kasseoptælling » Afregn kommission posts both sides of the settlement (testlab round 2, consignment-settlement
 * DEVY-3, on both legs).
 *
 * debitor/pos_ordre_includes/settleCommission/moveToCustomerAccount.php debits the customer commission accounts
 * (Kommisionsafregning, 50800/50810 in the lab) with what the sellers are owed. The other side, a credit on each
 * seller's debtor group account (DG box2, the samlekonto) and an "Afr:" open post the seller can be paid against,
 * sat behind $moveToCustomerAccount, which nothing sets. In the lab report 3913 got the debits 216,44 and 52,50 and
 * three payments, and nothing else: the day was out by 268,94 per settlement.
 *
 * Pins: with Afregn kommission, Dan betalingsliste or both ticked, the settlement's postings balance, each seller's
 * share is credited on the samlekonto of the seller's debtor group in the fiscal year being posted, and the seller
 * gets an open post for it. When an item group's shares cancel out (one seller's sale against another seller's
 * return), each share is still posted, as a credit or a debit, with its open post. A seller whose account (or whose
 * group's samlekonto) is missing stops the settlement with the existing alert before anything is written: no payment,
 * no posting and no open post, also from the item groups settled before it.
 *
 * "Fra liste" on a payment list (debitor/betalinger.php) pays each of those open posts once: after a settlement with
 * Dan betalingsliste it adds nothing, as the list already pays them, and after a settlement without it, it pays each
 * open post the first time and not again. The page knows an Afr open post without a kassekladde is on a list when a
 * payment carries the open post's id in openpost_id, so the settlement's payments carry it too. When one seller's
 * payment is deleted from the list, Fra liste pays that seller's open post again and no other. ordre_id stays 0: the
 * list and its XML export read it as an order, and the open posts' refnr is the report number every seller shares.
 *
 * Dan betalingsliste puts a settlement's payments on a new list of its own, owned by the user who settles, and leaves
 * every existing list as it was (testlab round 2, consignment-settlement DEVY-4, on both legs).
 *
 * The include runs as posbogfor() in debitor/pos_ordre.php runs it, with the local variables posbogfor() sets, on a
 * real Postgres against TEMP tables shaped like includes/opretfunc.php creates them, so nothing in the database
 * behind SALDI_CHAR_DSN is read or changed. A query that fails fails the test. "Fra liste" and the list's Gem are the
 * page's own `if (...) { ... }` blocks, taken from debitor/betalinger.php and run the same way, and the XML export is
 * udskriv_xml() from includes/payListFunc.php.
 *
 * History:
 * 20261105 CL/LH Created.
 *                Shares that cancel out, and a missing account with a payment list or in a later item group.
 *                Fra liste after a settlement, with and without Dan betalingsliste.
 *                A payment pays one open post (openpost_id): a deleted payment, and the XML export with an order whose id is the report number.
 *                Each settlement with Dan betalingsliste makes a list of its own, next to the lab's old lists and after deleted lists.
 */
#[RunTestsInSeparateProcesses]
final class CommissionSettlementTest extends TestCase
{
    private const SETTLE = __DIR__ . '/../../../debitor/pos_ordre_includes/settleCommission/moveToCustomerAccount.php';
    private const PAYMENT_LIST = __DIR__ . '/../../../debitor/betalinger.php';
    private const REPORT = 3913;

    /** @var \PgSql\Connection|null */
    private static $conn = null;

    private static function boot(): void
    {
        if (!extension_loaded('pgsql')) {
            self::markTestSkipped('the pgsql extension is required to run the settlement SQL.');
        }
        $dsn = (string) getenv('SALDI_CHAR_DSN');
        if ($dsn === '') {
            self::markTestSkipped('set SALDI_CHAR_DSN, SALDI_CHAR_PGUSER and SALDI_CHAR_PGPASS to run the settlement SQL.');
        }
        $host = preg_match('/host=([^;]+)/', $dsn, $m) ? $m[1] : 'localhost';
        $name = preg_match('/dbname=([^;]+)/', $dsn, $m) ? $m[1] : '';
        $conn = pg_connect(sprintf(
            "host=%s dbname=%s user=%s password=%s connect_timeout=5",
            $host,
            $name,
            (string) getenv('SALDI_CHAR_PGUSER'),
            (string) getenv('SALDI_CHAR_PGPASS')
        ));
        if ($conn === false) {
            self::markTestSkipped('could not connect to the database SALDI_CHAR_DSN names.');
        }
        self::$conn = $conn;
        $GLOBALS['__cs_conn'] = $conn;
        eval('
            function db_escape_string($s) { return pg_escape_string($GLOBALS["__cs_conn"], (string) $s); }
            function db_select($q, $where = "") {
                $r = @pg_query($GLOBALS["__cs_conn"], $q);
                if ($r === false) {
                    throw new \RuntimeException(pg_last_error($GLOBALS["__cs_conn"]) . " in: " . $q);
                }
                return $r;
            }
            function db_modify($q, $where = "", $global = false) { return db_select($q, $where); }
            function db_fetch_array($r) { return pg_fetch_array($r); }
        ');
        require_once dirname(__DIR__, 3) . '/includes/std_func.php'; // usdate(), dkdato(), dkdecimal(), afrund(), alert()
        require_once dirname(__DIR__, 3) . '/includes/forfaldsdag.php'; // forfaldsdag(), for Fra liste
        require_once dirname(__DIR__, 3) . '/includes/payListFunc.php'; // udskriv_xml()

        // TEMP tables only, and no DROP: pg_temp comes first in the search path.
        self::exec("CREATE TEMP TABLE adresser (id serial, firmanavn varchar(90), land varchar(60), bank_reg varchar(15), bank_konto varchar(15),
            bank_fi varchar(15), erh varchar(15), ean varchar(20), betalingsbet varchar(15), betalingsdage integer DEFAULT 0, kontonr varchar(30),
            art varchar(2), gruppe integer, cvrnr varchar(20), swift varchar(20), PRIMARY KEY (id))");
        self::exec("CREATE TEMP TABLE grupper (id serial, beskrivelse text, kode text, kodenr integer, art text, box1 text, box2 text, box3 text, box4 text,
            box5 text, box6 text, box7 text, box8 text, box9 text, box10 text, box11 text, box12 text, box13 text, box14 text, fiscal_year integer,
            PRIMARY KEY (id))");
        self::exec("CREATE TEMP TABLE kontoplan (id serial, kontonr numeric(15,0), beskrivelse text, kontotype varchar(1), moms text, regnskabsaar integer,
            PRIMARY KEY (id))");
        self::exec("CREATE TEMP TABLE varer (id serial, varenr text, beskrivelse text, gruppe integer, kostpris numeric(15,3), provision integer,
            PRIMARY KEY (id))");
        self::exec("CREATE TEMP TABLE ordrer (id serial, konto_id integer, art varchar(2), fakturadate date, status integer, felt_5 text,
            report_number int default 0, settletime numeric(15,0) default 0, PRIMARY KEY (id))");
        self::exec("CREATE TEMP TABLE ordrelinjer (id serial, varenr text, beskrivelse text, pris numeric(15,3), rabat numeric(15,3), ordre_id integer,
            vare_id integer, antal numeric(15,3), momssats numeric(15,3), kostpris numeric(15,3), PRIMARY KEY (id))");
        self::exec("CREATE TEMP TABLE pos_betalinger (id serial, ordre_id integer, betalingstype varchar(40), amount numeric(15,3), valuta varchar(3),
            valutakurs numeric(15,3), receipt_id varchar(75), payment_id integer, PRIMARY KEY (id))");
        self::exec("CREATE TEMP TABLE openpost (id serial, konto_id integer, konto_nr text, faktnr text, amount numeric(15,3), refnr integer,
            beskrivelse text, udlignet varchar(2), transdate date, uxtid text, kladde_id integer, bilag_id integer, udlign_id integer, udlign_date date,
            valuta text, projekt text, valutakurs numeric(15,3), forfaldsdate date, betal_id text, betalings_id text, PRIMARY KEY (id))");
        self::exec("CREATE TEMP TABLE transaktioner (id serial, kontonr numeric(15,0), bilag numeric(15,0), transdate date, logtime time, beskrivelse text,
            debet numeric(15,3), kredit numeric(15,3), faktura text, kladde_id integer, projekt text, ansat numeric(15,0), logdate date, afd integer,
            ordre_id integer, valuta text, valutakurs numeric(15,3), moms numeric(15,3), adresser_id int4, kasse_nr numeric(15,0), land varchar(3),
            report_number int default 0, pos smallint default 0, PRIMARY KEY (id))");
        self::exec("CREATE TEMP TABLE betalingsliste (id serial, listedate date, udskriftsdate date, listenote text, bogfort varchar(2), oprettet_af text,
            bogfort_af text, hvem text, tidspkt text, PRIMARY KEY (id))");
        self::exec("CREATE TEMP TABLE betalinger (id serial, bet_type text, fra_kto text, egen_ref text, til_kto text, modt_navn text, belob text,
            betalingsdato text, valuta text, kort_ref text, kvittering text, ordre_id integer, bilag_id integer, liste_id integer, openpost_id integer,
            PRIMARY KEY (id))");

        // The lab's sale: bon 362690 on register 1, with two items of seller 1000 (one new, one used) and one of seller
        // 60254604, each at a 30 % commission. Their kostpris is what the sellers are owed.
        self::exec("INSERT INTO adresser (id, firmanavn, land, bank_reg, bank_konto, ean, betalingsbet, betalingsdage, kontonr, art, gruppe, cvrnr) VALUES
            (1, 'LoppeWorld ApS', 'Denmark', '2559', '6282596448', '', NULL, 0, '', 'S', NULL, '12345678'),
            (3, 'Bjarke Agger', 'Denmark', '0674', '0747564787', '', 'Netto', 8, '1000', 'D', 1, ''),
            (1250, 'Jannik Ringtved', 'Denmark', '5039', '0001290181', '', 'Netto', 8, '60254604', 'D', 1, '')");
        // Debtor group 1 had another samlekonto in the year before; the settlement posts in year 11.
        self::exec("INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, fiscal_year) VALUES
            ('Debitorer', 1, 'DG', '66100', '56000', 10),
            ('Debitorer', 1, 'DG', '66100', '56100', 11),
            ('Salgsmoms', 1, 'SM', '66200', '25', 11)");
        self::exec("INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, regnskabsaar) VALUES
            (1000, 'Kommission, nye varer', 'D', 'S1', 11),
            (1001, 'Kommission, brugte varer', 'D', '', 11)");
        self::exec("INSERT INTO varer (id, varenr, beskrivelse, gruppe, kostpris, provision) VALUES
            (4008, 'knnyk1000', 'Sommerkjole str. 38', 1, 105, 30),
            (8, 'kbnyk1000', 'Brugt cykelhjelm', 1, 52.5, 30),
            (21044, 'kn00160254604', 'Keramikvase, kommission', 1, 111.44, 30)");
        self::exec("INSERT INTO ordrer (id, konto_id, art, fakturadate, status, felt_5, report_number) VALUES
            (362690, 0, 'PO', '2026-11-08', 3, '1', 3912)");
        self::exec("INSERT INTO ordrelinjer (id, varenr, beskrivelse, pris, rabat, ordre_id, vare_id, antal, momssats, kostpris) VALUES
            (1080766, 'knnyk1000', 'Sommerkjole str. 38', 150, 0, 362690, 4008, 1, 25, 105),
            (1080767, 'kbnyk1000', 'Brugt cykelhjelm', 75, 0, 362690, 8, 1, 25, 52.5),
            (1080768, 'kn00160254604', 'Keramikvase, kommission', 159.2, 0, 362690, 21044, 1, 25, 111.44)");
        self::exec("INSERT INTO pos_betalinger (ordre_id, betalingstype, amount, valuta, valutakurs) VALUES (362690, 'Kontant', 480.5, 'DKK', 100)");
    }

    private static function exec(string $sql): void
    {
        if (@pg_query(self::$conn, $sql) === false) {
            throw new \RuntimeException(pg_last_error(self::$conn) . ' in: ' . $sql);
        }
    }

    /**
     * @return list<array<string, string>>
     */
    private static function rows(string $sql): array
    {
        $out = [];
        $q = db_select($sql);
        while ($r = pg_fetch_assoc($q)) {
            $out[] = $r;
        }
        return $out;
    }

    /**
     * Runs the settlement the way posbogfor() does after Godkend, with its local variables and the posted period.
     *
     * @param string $day The day settled and posted on (yyyy-mm-dd), posted as the period's first and last day.
     * @return string What the page printed (the include's alert, if any).
     */
    private static function settle(bool $settleCommission, bool $createPayList, string $day = '2026-11-08'): string
    {
        $period = date('d-m-y', (int) strtotime($day));
        $_POST = ['commissionFromDate' => $period, 'commissionToDate' => $period];
        $kasse = 1;
        $regnaar = 11;
        $reportNumber = self::REPORT;
        $dd = $day;
        $logtime = '16:01:38';
        $ansat_id = 0;
        $afd = 1;
        $brugernavn = 'e2etest';
        $commissionAccountNew = '1000';
        $commissionAccountUsed = '1001';
        $customerCommissionAccountNew = '50800';
        $customerCommissionAccountUsed = '50810';
        $settleCommission = $settleCommission ? 'on' : '';
        $createPayList = $createPayList ? 'on' : '';
        ob_start();
        try {
            include self::SETTLE;
        } finally {
            $out = (string) ob_get_clean();
        }
        return $out;
    }

    /**
     * The code of debitor/betalinger.php's `if <condition> { ... }` block, as the shipped page has it.
     *
     * @param string $condition The condition with its parentheses, without whitespace.
     */
    private static function pageBlock(string $condition): string
    {
        $tokens = token_get_all((string) file_get_contents(self::PAYMENT_LIST));
        $blocks = [];
        foreach ($tokens as $i => $token) {
            if (!is_array($token) || $token[0] !== T_IF) {
                continue;
            }
            $head = '';
            for ($j = $i + 1; isset($tokens[$j]) && $tokens[$j] !== '{'; $j++) {
                if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_WHITESPACE) {
                    $head .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
                }
            }
            if ($head !== $condition) {
                continue;
            }
            $code = '';
            $depth = 0;
            for ($j = $i; isset($tokens[$j]); $j++) {
                $text = is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
                $code .= $text;
                if ($text === '{' || (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                    $depth++;
                } elseif ($text === '}' && --$depth === 0) {
                    break;
                }
            }
            $blocks[] = $code;
        }
        self::assertCount(1, $blocks, "debitor/betalinger.php has one if $condition { ... } block");
        return $blocks[0];
    }

    /**
     * Picks "Fra liste" on payment list $listeId in debitor/betalinger.php: the page's `if ($find) { ... }` block, run with
     * what the page has set by then (the company's bank account from adresser art 'S').
     */
    private static function fromList(int $listeId): void
    {
        $block = self::pageBlock('($find)');
        $r = db_fetch_array(db_select("select * from adresser where art = 'S'"));
        $myBank = $r['bank_konto'];
        $myReg = $r['bank_reg'];
        $myName = str_replace('&', 'og', $r['firmanavn']);
        $currency = 'DKK';
        $liste_id = $listeId;
        $find = 'fromList';
        eval($block);
    }

    /**
     * Ticks Slet on $sellerName's payment on list $listeId and presses Gem: the page's save block, with the form as the
     * page draws it (one row per payment, by recipient and date, with its fields and id).
     */
    private static function deletePayment(int $listeId, string $sellerName): void
    {
        $block = self::pageBlock("(isset(\$_POST['slet_ugyldige'])||isset(\$_POST['gem'])||isset(\$_POST['udskriv']))");
        $rows = self::rows("select * from betalinger where liste_id = $listeId order by modt_navn, betalingsdato");
        $post = ['listenote' => 'Afregning', 'gem' => 'Gem', 'format' => 'bec', 'antal' => (string) count($rows)];
        foreach ($rows as $i => $r) {
            $x = $i + 1;
            $post['id'][$x] = $r['id'];
            $post['erh'][$x] = $r['bet_type'];
            foreach (['fra_kto', 'egen_ref', 'til_kto', 'kort_ref', 'modt_navn', 'belob', 'valuta', 'betalingsdato'] as $field) {
                $post[$field][$x] = $r[$field];
            }
            $post['ugyldig'][$x] = '';
            if ($r['modt_navn'] === $sellerName) {
                $post['slet'][$x] = 'on';
            }
        }
        self::assertArrayHasKey('slet', $post, "$sellerName has a payment on list $listeId");
        $_POST = $post;
        $liste_id = $listeId;
        $brugernavn = 'e2etest';
        eval($block);
        $_POST = [];
    }

    /**
     * Udskriv on list $listeId in the XML format: udskriv_xml() writes ../temp/<db_id><bruger_id>.xml from the page's
     * directory.
     */
    private static function exportXml(int $listeId): string
    {
        $dir = sys_get_temp_dir() . '/commission-settlement-' . getmypid();
        foreach (["$dir/debitor", "$dir/temp"] as $sub) {
            if (!is_dir($sub)) {
                mkdir($sub, 0700, true);
            }
        }
        $cwd = (string) getcwd();
        chdir("$dir/debitor");
        $GLOBALS['format'] = 'xml';
        $GLOBALS['top_bund'] = '';
        ob_start();
        try {
            udskriv_xml(7, 1, $listeId);
        } finally {
            ob_end_clean();
            chdir($cwd);
        }
        $xml = (string) file_get_contents("$dir/temp/71.xml");
        unlink("$dir/temp/71.xml");
        rmdir("$dir/temp");
        rmdir("$dir/debitor");
        rmdir($dir);
        return $xml;
    }

    /**
     * @return list<array<string, string>>
     */
    private static function payments(): array
    {
        return self::rows("select egen_ref, modt_navn, belob, ordre_id, bilag_id, openpost_id, liste_id from betalinger order by egen_ref, belob");
    }

    /**
     * Each payment with the open post its openpost_id names (seller and amount).
     *
     * @return list<array<string, string|null>>
     */
    private static function paymentsWithOpenPost(): array
    {
        return self::rows("select b.egen_ref, b.modt_navn, b.belob, b.ordre_id, b.liste_id, o.konto_nr, o.amount
            from betalinger b left join openpost o on o.id = b.openpost_id order by b.egen_ref, b.belob, b.id");
    }

    /**
     * How many payments carry each open post, by open post.
     *
     * @return list<array<string, string>>
     */
    private static function paymentsPerOpenPost(): array
    {
        return self::rows("select o.konto_nr, o.amount, count(b.id) as payments from openpost o
            left join betalinger b on b.openpost_id = o.id group by o.id, o.konto_nr, o.amount order by o.konto_nr, o.amount");
    }

    /**
     * @param list<array<string, string>> $payments
     */
    private static function paid(array $payments): float
    {
        return round(array_sum(array_map(static fn(array $p): float => (float) str_replace(',', '.', $p['belob']), $payments)), 2);
    }

    /**
     * @return array<string, array{bool, bool}>
     */
    public static function ticked(): array
    {
        return [
            'Afregn kommission' => [true, false],
            'Afregn kommission and Dan betalingsliste (the lab)' => [true, true],
            'Dan betalingsliste alone' => [false, true],
        ];
    }

    #[DataProvider('ticked')]
    public function test_the_settlement_credits_each_seller_and_balances(bool $settleCommission, bool $createPayList): void
    {
        self::boot();

        self::settle($settleCommission, $createPayList);

        $postings = self::rows("select kontonr, beskrivelse, debet, kredit from transaktioner where report_number = " . self::REPORT . " order by kontonr, debet, kredit");
        self::assertSame([
            ['kontonr' => '50800', 'beskrivelse' => 'Kommisionsafregning', 'debet' => '216.440', 'kredit' => '0.000'],
            ['kontonr' => '50810', 'beskrivelse' => 'Kommisionsafregning', 'debet' => '52.500', 'kredit' => '0.000'],
            ['kontonr' => '56100', 'beskrivelse' => 'Kommisionsafregning konto 1000', 'debet' => '0.000', 'kredit' => '52.500'],
            ['kontonr' => '56100', 'beskrivelse' => 'Kommisionsafregning konto 1000', 'debet' => '0.000', 'kredit' => '105.000'],
            ['kontonr' => '56100', 'beskrivelse' => 'Kommisionsafregning konto 60254604', 'debet' => '0.000', 'kredit' => '111.440'],
        ], $postings);
        $sum = self::rows("select sum(debet) as debet, sum(kredit) as kredit from transaktioner where report_number = " . self::REPORT)[0];
        self::assertSame($sum['debet'], $sum['kredit'], 'the settlement balances');

        $openposts = self::rows("select konto_id, konto_nr, faktnr, amount, refnr, beskrivelse, udlignet, transdate from openpost order by konto_id, amount");
        self::assertSame([
            ['konto_id' => '3', 'konto_nr' => '1000', 'faktnr' => '0', 'amount' => '-105.000', 'refnr' => (string) self::REPORT,
                'beskrivelse' => 'Afr: 1000 08-11-2026 - 08-11-2026', 'udlignet' => '0', 'transdate' => '2026-11-08'],
            ['konto_id' => '3', 'konto_nr' => '1000', 'faktnr' => '0', 'amount' => '-52.500', 'refnr' => (string) self::REPORT,
                'beskrivelse' => 'Afr: 1000 08-11-2026 - 08-11-2026', 'udlignet' => '0', 'transdate' => '2026-11-08'],
            ['konto_id' => '1250', 'konto_nr' => '60254604', 'faktnr' => '0', 'amount' => '-111.440', 'refnr' => (string) self::REPORT,
                'beskrivelse' => 'Afr: 60254604 08-11-2026 - 08-11-2026', 'udlignet' => '0', 'transdate' => '2026-11-08'],
        ], $openposts, 'each seller has an open post for what the settlement credited');

        $settled = self::rows("select settletime from ordrer where id = 362690")[0];
        self::assertNotSame('0', $settled['settletime'], 'the bon is marked settled');

        $payments = self::paymentsWithOpenPost();
        if ($createPayList) {
            self::assertSame([
                ['egen_ref' => 'Afr: 1000 08112026 - 08112026', 'modt_navn' => 'Bjarke Agger', 'belob' => '105,00', 'ordre_id' => '0', 'liste_id' => '1',
                    'konto_nr' => '1000', 'amount' => '-105.000'],
                ['egen_ref' => 'Afr: 1000 08112026 - 08112026', 'modt_navn' => 'Bjarke Agger', 'belob' => '52,50', 'ordre_id' => '0', 'liste_id' => '1',
                    'konto_nr' => '1000', 'amount' => '-52.500'],
                ['egen_ref' => 'Afr: 60254604 08112026 - 08112026', 'modt_navn' => 'Jannik Ringtved', 'belob' => '111,44', 'ordre_id' => '0', 'liste_id' => '1',
                    'konto_nr' => '60254604', 'amount' => '-111.440'],
            ], $payments, 'the payment list is made as before, each payment carrying the open post it pays and no order');
        } else {
            self::assertSame([], $payments, 'no payment list without Dan betalingsliste');
        }
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function payList(): array
    {
        return [
            'Afregn kommission' => [false],
            'Afregn kommission and Dan betalingsliste' => [true],
        ];
    }

    /**
     * Seller 1000 sold an item the settlement owes 100,00 for; seller 60254604's item in the same item group came back
     * on a return bon (antal -1, kostpris 100,00). The group's shares add up to 0,00, but seller 1000 is still owed 100,00
     * and seller 60254604 owes the 100,00 paid out for the returned item before.
     */
    #[DataProvider('payList')]
    public function test_shares_that_cancel_out_are_still_posted(bool $createPayList): void
    {
        self::boot();
        self::exec("DELETE FROM ordrelinjer");
        self::exec("INSERT INTO ordrer (id, konto_id, art, fakturadate, status, felt_5, report_number) VALUES
            (362691, 0, 'PO', '2026-11-08', 3, '1', 3912)");
        self::exec("INSERT INTO ordrelinjer (id, varenr, beskrivelse, pris, rabat, ordre_id, vare_id, antal, momssats, kostpris) VALUES
            (1080770, 'knnyk1000', 'Sommerkjole str. 38', 150, 0, 362690, 4008, 1, 25, 100),
            (1080771, 'kn00160254604', 'Keramikvase, kommission', 150, 0, 362691, 21044, -1, 25, 100)");
        self::exec("INSERT INTO pos_betalinger (ordre_id, betalingstype, amount, valuta, valutakurs) VALUES (362691, 'Kontant', -187.5, 'DKK', 100)");

        self::settle(true, $createPayList);

        $postings = self::rows("select kontonr, beskrivelse, debet, kredit from transaktioner where report_number = " . self::REPORT . " order by kontonr, debet, kredit");
        self::assertSame([
            ['kontonr' => '56100', 'beskrivelse' => 'Kommisionsafregning konto 1000', 'debet' => '0.000', 'kredit' => '100.000'],
            ['kontonr' => '56100', 'beskrivelse' => 'Kommisionsafregning konto 60254604', 'debet' => '100.000', 'kredit' => '0.000'],
        ], $postings, 'each share is posted, and nothing on the customer commission account, as the shares add up to 0,00');

        $openposts = self::rows("select konto_id, konto_nr, amount, refnr, beskrivelse from openpost order by konto_id");
        self::assertSame([
            ['konto_id' => '3', 'konto_nr' => '1000', 'amount' => '-100.000', 'refnr' => (string) self::REPORT,
                'beskrivelse' => 'Afr: 1000 08-11-2026 - 08-11-2026'],
            ['konto_id' => '1250', 'konto_nr' => '60254604', 'amount' => '100.000', 'refnr' => (string) self::REPORT,
                'beskrivelse' => 'Afr: 60254604 08-11-2026 - 08-11-2026'],
        ], $openposts, 'seller 1000 is owed 100,00 and seller 60254604 owes 100,00');

        self::assertSame([], self::rows("select id from ordrer where settletime = 0"), 'both bons are marked settled');

        $payments = self::paymentsWithOpenPost();
        $lists = self::rows("select id, listenote, bogfort from betalingsliste order by id");
        if ($createPayList) {
            self::assertSame([
                ['egen_ref' => 'Afr: 1000 08112026 - 08112026', 'modt_navn' => 'Bjarke Agger', 'belob' => '100,00', 'ordre_id' => '0', 'liste_id' => '1',
                    'konto_nr' => '1000', 'amount' => '-100.000'],
            ], $payments, 'seller 1000 is paid against the open post');
            self::assertSame([['id' => '1', 'listenote' => 'Afregning 08112026 - 08112026', 'bogfort' => '-']], $lists,
                'the payment is on a list');
        } else {
            self::assertSame([], $payments, 'no payment list without Dan betalingsliste');
            self::assertSame([], $lists, 'no payment list without Dan betalingsliste');
        }
    }

    /**
     * The reviewer's case: the lab's settlement with both boxes ticked puts three payments on list 1, then "Fra liste"
     * is picked on that list (and on a later list). The three Afr open posts are already paid by the list, so Fra liste
     * adds nothing: three payments of 268,94 in all, not six of 537,88.
     */
    public function test_fra_liste_after_a_settlement_with_dan_betalingsliste_adds_no_payment(): void
    {
        self::boot();
        self::settle(true, true);
        $listed = self::payments();
        self::assertCount(3, $listed);

        self::fromList(1);
        self::fromList(2);

        self::assertSame($listed, self::payments(), 'Fra liste pays none of the open posts the settlement already put on the list');
        self::assertSame(268.94, self::paid(self::payments()));
    }

    /**
     * Without Dan betalingsliste the settlement leaves the sellers' Afr open posts for a payment list: the first Fra liste
     * pays each of them once, and a second Fra liste pays none of them again.
     */
    public function test_fra_liste_pays_each_open_post_of_a_settlement_without_a_list_once(): void
    {
        self::boot();
        self::settle(true, false);
        self::assertSame([], self::payments());

        self::fromList(1);
        $expected = [
            ['egen_ref' => 'Afr: 1000 08-11-2026 - 08-11-2026', 'modt_navn' => 'Bjarke Agger', 'belob' => '105,00', 'ordre_id' => '0', 'liste_id' => '1',
                'konto_nr' => '1000', 'amount' => '-105.000'],
            ['egen_ref' => 'Afr: 1000 08-11-2026 - 08-11-2026', 'modt_navn' => 'Bjarke Agger', 'belob' => '52,50', 'ordre_id' => '0', 'liste_id' => '1',
                'konto_nr' => '1000', 'amount' => '-52.500'],
            ['egen_ref' => 'Afr: 60254604 08-11-2026 - 08-11-2026', 'modt_navn' => 'Jannik Ringtved', 'belob' => '111,44', 'ordre_id' => '0', 'liste_id' => '1',
                'konto_nr' => '60254604', 'amount' => '-111.440'],
        ];
        self::assertSame($expected, self::paymentsWithOpenPost(), 'each open post is paid once, by a payment that carries it and no order');

        self::fromList(1);
        self::assertSame($expected, self::paymentsWithOpenPost(), 'a second Fra liste pays nothing again');
        self::assertSame(268.94, self::paid(self::payments()));
    }

    /**
     * The reviewer's case (round 3): report 3913 is settled, its three payments are on list 1, and Jannik's 111,44 is
     * deleted from the list (Slet, then Gem). Fra liste then pays Jannik's open post again and neither of Bjarke's: one
     * payment per open post, 268,94 in all. Once with the settlement's own list, once with a list Fra liste made.
     */
    #[DataProvider('payList')]
    public function test_fra_liste_pays_again_only_the_open_post_whose_payment_was_deleted(bool $createPayList): void
    {
        self::boot();
        self::settle(true, $createPayList);
        if (!$createPayList) {
            self::fromList(1);
        }
        self::assertSame(268.94, self::paid(self::payments()));

        self::deletePayment(1, 'Jannik Ringtved');
        self::assertSame(157.5, self::paid(self::payments()), "Jannik's payment is deleted");

        self::fromList(1);

        self::assertSame(268.94, self::paid(self::payments()), "Fra liste pays Jannik's 111,44 again");
        self::assertSame([
            ['konto_nr' => '1000', 'amount' => '-105.000', 'payments' => '1'],
            ['konto_nr' => '1000', 'amount' => '-52.500', 'payments' => '1'],
            ['konto_nr' => '60254604', 'amount' => '-111.440', 'payments' => '1'],
        ], self::paymentsPerOpenPost(), 'each open post has one payment again');
    }

    /**
     * The reviewer's export case (round 3): an earlier order has the id 3913, the report number, and belongs to the debtor
     * with kontonr 42, which has a SWIFT code. The XML file of the settlement's payment list must not send a seller's
     * payment to that debtor's bank: no payment carries an order, so no BIC is read from one, and each payment goes to the
     * seller's own account. Once with the settlement's own list, once with a list Fra liste made.
     */
    #[DataProvider('payList')]
    public function test_the_xml_export_takes_no_bank_from_an_order_whose_id_is_the_report_number(bool $createPayList): void
    {
        self::boot();
        self::exec("INSERT INTO adresser (id, firmanavn, land, bank_reg, bank_konto, kontonr, art, gruppe, swift) VALUES
            (42, 'Anden Kunde A/S', 'Denmark', '9999', '1234567890', '42', 'D', 1, 'UNRLDKKK')");
        self::exec("INSERT INTO ordrer (id, konto_id, art, fakturadate, status, felt_5) VALUES (" . self::REPORT . ", 42, 'DO', '2026-10-01', 4, '')");
        self::settle(true, $createPayList);
        if (!$createPayList) {
            self::exec("INSERT INTO betalingsliste (id, listedate, listenote, bogfort, tidspkt) VALUES (1, '2026-11-08', 'Afregning', '-', '0.50000000 1794142800')");
            self::fromList(1);
        }

        $xml = self::exportXml(1);

        self::assertSame(3, substr_count($xml, '<CdtTrfTxInf>'), 'the three payments are in the file');
        self::assertStringNotContainsString('UNRLDKKK', $xml, 'no BIC from the debtor of order 3913');
        preg_match_all('~<CdtrAcct>\s*<Id>\s*<Othr>\s*<Id>([^<]*)</Id>~', $xml, $accounts);
        sort($accounts[1]);
        self::assertSame(['06740747564787', '06740747564787', '50390001290181'], $accounts[1], "each payment goes to its seller's account");
        self::assertSame(['0'], array_values(array_unique(array_column(self::payments(), 'ordre_id'))), 'no payment carries an order');
    }

    /**
     * @return array<string, array{int, list<int>}>
     */
    public static function existingLists(): array
    {
        return [
            // The lab's ledger: lists 1 to 152, and payments on all of them but lists 1 and 6, two old open lists.
            'old open lists without payments (the lab)' => [152, [1, 6]],
            // Lists 151 and 152 were deleted with their payments (Slet on Betalingslister), so the database hands out 153 next.
            'the newest lists were deleted' => [150, []],
        ];
    }

    /**
     * The lab (round 2, consignment-settlement DEVY-4, on both legs): the settlement's list id was guessed from the
     * payments' liste_id. Its payments went on list 1, an old open list of carsten's without payments, and list 1 got
     * the settlement's date and note while it stayed carsten's, so Vis egne was empty. The next settlement did the same to
     * list 6. Where the guessed id had no list (the newest lists deleted), the payments pointed at a list that was never
     * made. Each settlement with Dan betalingsliste must make one new list, owned by the user who settles, with all of
     * its payments, and leave every existing list as it was.
     *
     * The second settlement settles the next day's sale, so it does not depend on what a repeated period settles.
     *
     * @param list<int> $withoutPayments
     */
    #[DataProvider('existingLists')]
    public function test_each_settlement_with_dan_betalingsliste_makes_a_list_of_its_own(int $lastList, array $withoutPayments): void
    {
        self::boot();
        self::exec("INSERT INTO betalingsliste (id, listedate, listenote, bogfort, oprettet_af, hvem, tidspkt)
            SELECT n, '2016-08-06', '', CASE WHEN n IN (1, 6, 7, 8) THEN '-' ELSE 'V' END, 'carsten', 'carsten', '0.10000000 1470441600'
            FROM generate_series(1, $lastList) AS n");
        self::exec("SELECT setval(pg_get_serial_sequence('betalingsliste', 'id'), 152)");
        $skip = $withoutPayments ? 'WHERE n NOT IN (' . implode(', ', $withoutPayments) . ')' : '';
        self::exec("INSERT INTO betalinger (bet_type, modt_navn, belob, liste_id)
            SELECT 'ERH351', 'Tidligere modtager', '10,00', n FROM generate_series(1, $lastList) AS n $skip");
        $existing = self::rows("select * from betalingsliste order by id");
        $today = date('Y-m-d');

        self::settle(true, true);
        // The next day Jannik's second vase is sold, and that day is settled.
        self::exec("INSERT INTO ordrer (id, konto_id, art, fakturadate, status, felt_5, report_number) VALUES
            (362692, 0, 'PO', '2026-11-09', 3, '1', 3914)");
        self::exec("INSERT INTO ordrelinjer (id, varenr, beskrivelse, pris, rabat, ordre_id, vare_id, antal, momssats, kostpris) VALUES
            (1080772, 'kn00160254604', 'Keramikvase, kommission', 159.2, 0, 362692, 21044, 1, 25, 111.44)");
        self::exec("INSERT INTO pos_betalinger (ordre_id, betalingstype, amount, valuta, valutakurs) VALUES (362692, 'Kontant', 199, 'DKK', 100)");
        self::settle(true, true, '2026-11-09');

        self::assertSame($existing, self::rows("select * from betalingsliste where id <= $lastList order by id"),
            'no existing list gets a settlement\'s date, note or user');
        self::assertSame([
            ['id' => '153', 'listedate' => $today, 'listenote' => 'Afregning 08112026 - 08112026', 'bogfort' => '-', 'oprettet_af' => 'e2etest', 'hvem' => 'e2etest'],
            ['id' => '154', 'listedate' => $today, 'listenote' => 'Afregning 09112026 - 09112026', 'bogfort' => '-', 'oprettet_af' => 'e2etest', 'hvem' => 'e2etest'],
        ], self::rows("select id, listedate, listenote, bogfort, oprettet_af, hvem from betalingsliste where id > $lastList order by id"),
            'each settlement makes one new list, owned by the user who settles');
        self::assertSame([
            ['liste_id' => '153', 'egen_ref' => 'Afr: 1000 08112026 - 08112026', 'modt_navn' => 'Bjarke Agger', 'belob' => '105,00'],
            ['liste_id' => '153', 'egen_ref' => 'Afr: 60254604 08112026 - 08112026', 'modt_navn' => 'Jannik Ringtved', 'belob' => '111,44'],
            ['liste_id' => '153', 'egen_ref' => 'Afr: 1000 08112026 - 08112026', 'modt_navn' => 'Bjarke Agger', 'belob' => '52,50'],
            ['liste_id' => '154', 'egen_ref' => 'Afr: 60254604 09112026 - 09112026', 'modt_navn' => 'Jannik Ringtved', 'belob' => '111,44'],
        ], self::rows("select liste_id, egen_ref, modt_navn, belob from betalinger where egen_ref like 'Afr:%' order by liste_id, belob"),
            'each settlement\'s payments are on its own list');
        // Betalingslister, Vis egne: the open lists the user made.
        self::assertSame([['id' => '153'], ['id' => '154']],
            self::rows("select id from betalingsliste where bogfort = '-' and oprettet_af = 'e2etest' order by id"),
            'Vis egne shows both settlements\' lists');
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function missingAccount(): array
    {
        $setups = [
            'the seller has no debtor account' => ["DELETE FROM adresser WHERE id = 1250", 'kn00160254604'],
            "the seller's debtor group has no samlekonto this year" => [
                "UPDATE adresser SET gruppe = 2 WHERE id = 1250; INSERT INTO grupper (beskrivelse, kodenr, art, box2, fiscal_year) VALUES ('Udland', 2, 'DG', '56200', 10)",
                'kn00160254604',
            ],
            // The used items are settled after every new item, so the new items' group is worked out by then.
            "a used item's seller has no debtor account" => [
                "INSERT INTO varer (id, varenr, beskrivelse, gruppe, kostpris, provision) VALUES (9, 'kbnyk2000', 'Brugt lampe', 1, 40, 30);
                 INSERT INTO ordrelinjer (id, varenr, beskrivelse, pris, rabat, ordre_id, vare_id, antal, momssats, kostpris) VALUES
                    (1080769, 'kbnyk2000', 'Brugt lampe', 60, 0, 362690, 9, 1, 25, 40)",
                'kbnyk2000',
            ],
        ];
        $cases = [];
        foreach ($setups as $name => [$setup, $itemNo]) {
            foreach (self::payList() as $ticked => [$createPayList]) {
                $cases["$name, $ticked"] = [$setup, $itemNo, $createPayList];
            }
        }
        return $cases;
    }

    /**
     * The seller that has no account is settled after another seller's item, whose share (and, with Dan betalingsliste,
     * payment) is already worked out. The missing account stops the settlement, and nothing of it may be written: a
     * payment or a share without the rest of the settlement would stay in the ledger when posbogfor() commits the cash
     * count.
     */
    #[DataProvider('missingAccount')]
    public function test_a_seller_without_an_account_stops_the_settlement_before_anything_is_posted(string $setup, string $itemNo, bool $createPayList): void
    {
        self::boot();
        self::exec($setup);

        $out = self::settle(true, $createPayList);

        self::assertStringContainsString("Konto mangler for varenummer $itemNo!", $out);
        self::assertSame([], self::rows("select kontonr, debet, kredit from transaktioner"), 'nothing is posted');
        self::assertSame([], self::rows("select konto_id, amount from openpost"), 'no open post is made');
        self::assertSame([], self::rows("select egen_ref, belob from betalinger"), 'no payment is made');
        self::assertSame([], self::rows("select id from betalingsliste"), 'no payment list is made');
        self::assertSame([], self::rows("select id from ordrer where settletime <> 0"), 'no bon is marked settled');
    }
}
