<?php
// tests/characterization/finans/JournalOpeningCarryLockTest.test.php
//
// A journal (finans/bogfor.php) posted into a fiscal year that has a following year takes the opening-balance carry's
// lock before its transaction begins (p1-yearend-F4). Before, it took the lock only in its carry, after its postings: its
// transaction held the journal's rows while it waited, which could deadlock with a POS day close or a year card holding
// the lock, and on MySQL and MariaDB, where every transaction reads one snapshot from its first read on, a carry another
// session committed after that read was not seen and the journal's carry wrote over it (traced: retained earnings -50
// instead of -150, the opening off by 100).
//
// The page runs for real (support/run_bogfor_page.php) against a fresh tenant, while the test holds the carry's lock in
// a session of its own, as a POS day close or a year card does, and commits a carry there (a posting into the year and
// the following year's opening moved by it) before it lets go. CI has no MySQL or MariaDB: their snapshot is
// PostgreSQL's REPEATABLE READ here, set for the page's connection with PGOPTIONS.
//
// History:
// 20261119 CL/ASR Created (p1-yearend-F4).

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';

final class JournalOpeningCarryLockTest extends TestCase
{
    /** The carry's PostgreSQL advisory lock (openingCarryLockKey() in includes/updateFollowingOpeningBalances.php). */
    private const CARRY_LOCK_KEY = "hashtext('saldi:opening_carry'), 0";

    /** The result destination of the standard chart: the first status account after 6935 Årets resultat. */
    private const RETAINED = 6940;

    /** @var resource|\PgSql\Connection */
    private static $tenant;
    private static int $year;
    private static string $start;
    private static string $end;

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant(static::class);
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        // The session's fiscal year (online.regnskabsaar), the one the journal posts into.
        $ra = CharacterizationEnv::one(self::$tenant, "SELECT kodenr, box1, box2, box3, box4 FROM grupper WHERE art = 'RA' AND kodenr = 1");
        self::assertNotNull($ra, 'the tenant has fiscal year 1');
        self::$year = 1;
        self::$start = sprintf('%04d-%02d-01', (int)$ra['box2'], (int)$ra['box1']);
        self::$end = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', (int)$ra['box4'], (int)$ra['box3'])));
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant) && self::$tenant) {
            pg_close(self::$tenant);
            self::$tenant = null;
            CharacterizationEnv::teardownTenant();
        }
    }

    /**
     * Before: the journal posted its rows and kontoplan.saldo first and then waited for the lock in its carry
     * (pg_advisory_xact_lock), holding those rows. Now it waits before its transaction (pg_advisory_lock) and holds nothing
     * while it waits; a journal into the newest year takes no lock and is not held up.
     */
    public function testAJournalIntoAYearWithAFollowingYearWaitsForTheCarrysLockBeforeItsTransaction(): void
    {
        [$debit, $credit, $other, $drift] = $this->accounts();

        // Fiscal year 1 is the newest: nothing to carry, so the journal posts while another session holds the lock.
        $this->removeFollowingYear();
        $holder = $this->holdCarryLock();
        try {
            $first = $this->createKladde($debit, $credit, 640.00);
            $page = $this->finishPage($this->startPage($first), 60.0);
            self::assertSame(0, $page['exit'], $page['output']);
            self::assertSame('V', $this->bogfort($first), 'a journal into the newest year does not wait for the carry\'s lock: ' . $page['output']);
        } finally {
            $this->releaseCarryLock($holder);
        }

        // Fiscal year 2 follows: the journal's carry writes its opening, so the journal waits for the lock.
        $this->addFollowingYear();
        $before = $this->openings($debit, $credit, $other, self::RETAINED);
        $holder = $this->holdCarryLock();
        $child = $page = null;
        try {
            $journal = $this->createKladde($debit, $credit, 250.00);
            $child = $this->startPage($journal);
            $waiter = $this->carryLockWaiter(30.0);
            self::assertNotNull($waiter, 'the journal waits for the carry\'s lock');
            self::assertStringContainsString('pg_advisory_lock(', $waiter['query'], 'the journal waits before its transaction, not in its carry');
            self::assertSame([], $this->lockedTables((int)$waiter['pid']), 'the journal holds none of its rows while it waits');
            $this->commitCarry($holder, $other, $drift, 125.00);
        } finally {
            $this->releaseCarryLock($holder);
            if ($child !== null) {
                $page = $this->finishPage($child, 90.0);
            }
        }

        self::assertSame(0, $page['exit'], $page['output']);
        self::assertSame('V', $this->bogfort($journal), $page['output']);
        // The journal moved 250 from $credit to $debit in year 2's opening, on top of the other session's carry of 125.
        self::assertSame([
            $debit => $before[$debit] + 250.0,
            $credit => $before[$credit] - 250.0,
            $other => $before[$other] + 125.0,
            self::RETAINED => $before[self::RETAINED] - 125.0,
        ], $this->openings($debit, $credit, $other, self::RETAINED));
    }

    /**
     * MySQL and MariaDB read one snapshot per transaction (here PostgreSQL's REPEATABLE READ). Before: the journal took its
     * snapshot at its first read, waited for the lock in its carry, and was refused afterwards because another session's
     * carry had committed meanwhile (on MySQL, where it did not have to wait, it wrote over that carry). Now its
     * transaction begins after the other carry has committed, so it posts and carries on top of it.
     */
    public function testOnASnapshotTheJournalPostsOnTopOfACarryCommittedWhileItWaited(): void
    {
        self::assertSame('repeatable read', $this->isolationWithSnapshotOptions(), 'PGOPTIONS sets the page connection\'s isolation');
        [$debit, $credit, $other, $drift] = $this->accounts();
        $this->removeFollowingYear();
        $this->addFollowingYear();
        $before = $this->openings($debit, $credit, $other, self::RETAINED);

        $holder = $this->holdCarryLock();
        $child = $page = null;
        try {
            $journal = $this->createKladde($debit, $credit, 300.00);
            $child = $this->startPage($journal, ['PGOPTIONS' => '-c default_transaction_isolation=repeatable\\ read']);
            self::assertNotNull($this->carryLockWaiter(30.0), 'the journal waits for the carry\'s lock');
            $this->commitCarry($holder, $other, $drift, 50.00);
        } finally {
            $this->releaseCarryLock($holder);
            if ($child !== null) {
                $page = $this->finishPage($child, 90.0);
            }
        }

        self::assertSame(0, $page['exit'], $page['output']);
        self::assertSame('V', $this->bogfort($journal), 'the journal is posted, not refused: ' . $page['output']);
        self::assertSame([
            $debit => $before[$debit] + 300.0,
            $credit => $before[$credit] - 300.0,
            $other => $before[$other] + 50.0,
            self::RETAINED => $before[self::RETAINED] - 50.0,
        ], $this->openings($debit, $credit, $other, self::RETAINED));
        self::assertSame(0.0, $this->openingSum(), 'year 2 opens balanced');
    }

    /**
     * Three status accounts of year 1 without VAT or currency (the journal's debit and credit, and the account the other
     * session's carry posts on) and a drift account for that carry's other side.
     *
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function accounts(): array
    {
        $status = CharacterizationEnv::rows(
            self::$tenant,
            "SELECT kontonr FROM kontoplan WHERE regnskabsaar = $1 AND kontotype = 'S' AND (moms IS NULL OR moms = '')
               AND COALESCE(valuta, 0) = 0 AND (lukket IS NULL OR lukket = '') AND kontonr <> $2 ORDER BY kontonr LIMIT 3",
            [self::$year, self::RETAINED]
        );
        $drift = CharacterizationEnv::one(
            self::$tenant,
            "SELECT kontonr FROM kontoplan WHERE regnskabsaar = $1 AND kontotype = 'D' AND (moms IS NULL OR moms = '')
               AND COALESCE(valuta, 0) = 0 ORDER BY kontonr LIMIT 1",
            [self::$year]
        );
        self::assertCount(3, $status, 'the chart has three plain status accounts');
        self::assertNotNull($drift, 'the chart has a plain drift account');
        $r = CharacterizationEnv::one(self::$tenant, "SELECT count(*) AS n FROM kontoplan WHERE regnskabsaar = $1 AND kontonr = $2 AND kontotype = 'S'", [self::$year, self::RETAINED]);
        self::assertSame('1', $r['n'], 'the chart has 6940 Overført resultat');
        return [(int)$status[0]['kontonr'], (int)$status[1]['kontonr'], (int)$status[2]['kontonr'], (int)$drift['kontonr']];
    }

    /**
     * Fiscal year 2 after year 1, as Indstillinger » Regnskabsår » Opret creates it: year 1's chart, each status account
     * opening with its year 1 closing and 6940 with minus the others, so the opening sums to 0.
     */
    private function addFollowingYear(): void
    {
        $next = (int)substr(self::$end, 0, 4) + 1;
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, box4, box5, box6, box7, box8, box9, box10, box11, box12, box13, box14, fiscal_year)
             VALUES ($1, '', 2, 'RA', '01', $1, '12', $1, 'on', '0', '', '', '', '', '', '', '', '', 2)",
            [(string)$next]
        );
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO kontoplan (kontonr, beskrivelse, kontotype, moms, fra_kto, til_kto, lukket, primo, saldo, regnskabsaar, genvej, overfor_til, valuta)
             SELECT k.kontonr, k.beskrivelse, k.kontotype, k.moms, k.fra_kto, k.til_kto, k.lukket,
                    CASE WHEN k.kontotype = 'S' THEN COALESCE(k.primo, 0) + COALESCE((SELECT sum(COALESCE(t.debet, 0) - COALESCE(t.kredit, 0)) FROM transaktioner t
                        WHERE t.kontonr = k.kontonr AND t.transdate >= $1 AND t.transdate <= $2), 0) ELSE 0 END,
                    0, 2, k.genvej, k.overfor_til, k.valuta
             FROM kontoplan k WHERE k.regnskabsaar = $3 ORDER BY k.kontonr",
            [self::$start, self::$end, self::$year]
        );
        CharacterizationEnv::rows(
            self::$tenant,
            "UPDATE kontoplan SET primo = -(SELECT COALESCE(sum(o.primo), 0) FROM kontoplan o WHERE o.regnskabsaar = 2 AND o.kontotype = 'S' AND o.kontonr <> $1)
             WHERE regnskabsaar = 2 AND kontonr = $1",
            [self::RETAINED]
        );
        self::assertSame(0.0, $this->openingSum(), 'year 2 opens balanced');
    }

    private function removeFollowingYear(): void
    {
        CharacterizationEnv::rows(self::$tenant, 'DELETE FROM kontoplan WHERE regnskabsaar = 2');
        CharacterizationEnv::rows(self::$tenant, "DELETE FROM grupper WHERE art = 'RA' AND kodenr = 2");
    }

    /** @return array<int, float> Year 2's opening (primo) by account number. */
    private function openings(int ...$accounts): array
    {
        $out = [];
        foreach ($accounts as $kontonr) {
            $r = CharacterizationEnv::one(self::$tenant, 'SELECT primo FROM kontoplan WHERE regnskabsaar = 2 AND kontonr = $1', [$kontonr]);
            $out[$kontonr] = round((float)($r['primo'] ?? 0), 2);
        }
        return $out;
    }

    private function openingSum(): float
    {
        $r = CharacterizationEnv::one(self::$tenant, "SELECT COALESCE(sum(primo), 0) AS s FROM kontoplan WHERE regnskabsaar = 2 AND kontotype = 'S'");
        return round((float)$r['s'], 2) + 0.0;
    }

    private function createKladde(int $debit, int $credit, float $amount): int
    {
        $kladdeId = CharacterizationEnv::nextId(self::$tenant, 'kladdeliste');
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO kladdeliste (id, kladdedate, bogfort, kladdenote, oprettet_af) VALUES ($1, CURRENT_DATE, '', 'chartest', 'chartest')",
            [$kladdeId]
        );
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, amount, kladde_id)
             VALUES (1, CURRENT_DATE, 'chartest carry lock', 'F', $1, 'F', $2, $3, $4)",
            [$debit, $credit, $amount, $kladdeId]
        );
        return $kladdeId;
    }

    private function bogfort(int $kladdeId): string
    {
        $r = CharacterizationEnv::one(self::$tenant, 'SELECT bogfort FROM kladdeliste WHERE id = $1', [$kladdeId]);
        return trim((string)($r['bogfort'] ?? ''));
    }

    /**
     * Another session takes the carry's lock before its transaction, as a POS day close or Gem on a year card does.
     *
     * @return resource|\PgSql\Connection
     */
    private function holdCarryLock()
    {
        $holder = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        CharacterizationEnv::rows($holder, 'SELECT pg_advisory_lock(' . self::CARRY_LOCK_KEY . ')');
        return $holder;
    }

    /** @param resource|\PgSql\Connection $holder */
    private function releaseCarryLock($holder): void
    {
        @pg_query($holder, 'SELECT pg_advisory_unlock(' . self::CARRY_LOCK_KEY . ')');
        pg_close($holder);
    }

    /**
     * The other session's carry, committed while it holds the lock: $amount posted in year 1 from $drift onto $account,
     * and year 2's opening moved by it as updateFollowingOpeningBalances() moves it (the account up, 6940 down).
     *
     * @param resource|\PgSql\Connection $holder
     */
    private function commitCarry($holder, int $account, int $drift, float $amount): void
    {
        CharacterizationEnv::rows($holder, 'BEGIN');
        CharacterizationEnv::rows(
            $holder,
            "INSERT INTO transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id, kasse_nr)
             VALUES (0, CURRENT_DATE, 'chartest other carry', $1, '', $3, 0, 0, 0, CURRENT_DATE, CURRENT_TIME, '', 0, 0, 0),
                    (0, CURRENT_DATE, 'chartest other carry', $2, '', 0, $3, 0, 0, CURRENT_DATE, CURRENT_TIME, '', 0, 0, 0)",
            [$account, $drift, $amount]
        );
        CharacterizationEnv::rows($holder, 'UPDATE kontoplan SET primo = primo + $1 WHERE regnskabsaar = 2 AND kontonr = $2', [$amount, $account]);
        CharacterizationEnv::rows($holder, 'UPDATE kontoplan SET primo = primo - $1 WHERE regnskabsaar = 2 AND kontonr = $2', [$amount, self::RETAINED]);
        CharacterizationEnv::rows($holder, 'COMMIT');
    }

    /**
     * The connection to the test database that waits for an advisory lock, within $timeout seconds.
     *
     * @return array{pid: string, query: string}|null
     */
    private function carryLockWaiter(float $timeout): ?array
    {
        $deadline = microtime(true) + $timeout;
        do {
            $r = CharacterizationEnv::one(
                self::$tenant,
                "SELECT pid, query FROM pg_stat_activity
                 WHERE datname = current_database() AND pid <> pg_backend_pid() AND wait_event_type = 'Lock' AND wait_event = 'advisory'"
            );
            if ($r !== null) {
                return $r;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
        return null;
    }

    /** @return list<string> The journal's tables on which connection $pid holds a lock. */
    private function lockedTables(int $pid): array
    {
        $rows = CharacterizationEnv::rows(
            self::$tenant,
            "SELECT DISTINCT c.relname FROM pg_locks l JOIN pg_class c ON c.oid = l.relation
             WHERE l.pid = $1 AND c.relname IN ('kassekladde', 'kladdeliste', 'kontoplan', 'transaktioner') ORDER BY c.relname",
            [$pid]
        );
        return array_map(static fn(array $r): string => $r['relname'], $rows);
    }

    /** What a new connection with the PGOPTIONS the snapshot case gives the page reads as its isolation level. */
    private function isolationWithSnapshotOptions(): string
    {
        $conninfo = sprintf(
            "host='%s' user='%s' password='%s' dbname='%s' connect_timeout='3'",
            getenv('SALDI_CHAR_PGHOST') ?: 'postgres',
            getenv('SALDI_CHAR_PGUSER') ?: 'user',
            getenv('SALDI_CHAR_PGPASS') ?: '',
            CharacterizationEnv::testDb()
        );
        $env = getenv();
        $env['PGOPTIONS'] = '-c default_transaction_isolation=repeatable\\ read';
        $env['CHARTEST_CONNINFO'] = $conninfo;
        $process = proc_open(
            [PHP_BINARY, '-r', 'echo pg_fetch_result(pg_query(pg_connect(getenv("CHARTEST_CONNINFO")), "SHOW transaction_isolation"), 0, 0);'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env
        );
        self::assertIsResource($process);
        $out = (string)stream_get_contents($pipes[1]);
        $err = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        return trim($out) !== '' ? trim($out) : $err;
    }

    /**
     * Start the posting page in the background, with its own session save path (PHP's files handler locks a session file
     * until the request ends) and $env added to the environment.
     *
     * @param array<string, string> $env
     * @return array{proc: resource, out: resource, err: resource, sessionDir: string}
     */
    private function startPage(int $kladdeId, array $env = []): array
    {
        $sessionDir = sys_get_temp_dir() . '/chartest-carrylock-' . bin2hex(random_bytes(6));
        mkdir($sessionDir, 0700);
        $out = tmpfile();
        $err = tmpfile();
        $proc = proc_open(
            [PHP_BINARY, __DIR__ . '/../support/run_bogfor_page.php', 'bogfor', (string)$kladdeId, CharacterizationEnv::sessionId(), $sessionDir],
            [0 => ['file', '/dev/null', 'r'], 1 => $out, 2 => $err],
            $pipes,
            null,
            array_merge(getenv(), ['SALDI_CHAR_DEBUG' => '1'], $env)
        );
        self::assertIsResource($proc, 'could not start the posting page');
        return ['proc' => $proc, 'out' => $out, 'err' => $err, 'sessionDir' => $sessionDir];
    }

    /**
     * Wait for a page from startPage(); it is stopped after $timeout seconds.
     *
     * @param array{proc: resource, out: resource, err: resource, sessionDir: string} $child
     * @return array{exit: int, output: string}
     */
    private function finishPage(array $child, float $timeout): array
    {
        $deadline = microtime(true) + $timeout;
        $status = proc_get_status($child['proc']);
        while ($status['running'] && microtime(true) < $deadline) {
            usleep(50000);
            $status = proc_get_status($child['proc']);
        }
        if ($status['running']) {
            proc_terminate($child['proc'], 9);
        }
        proc_close($child['proc']);
        $exit = $status['running'] ? -1 : $status['exitcode'];
        $output = '';
        foreach (['out', 'err'] as $stream) {
            rewind($child[$stream]);
            $output .= stream_get_contents($child[$stream]);
            fclose($child[$stream]);
        }
        array_map('unlink', glob($child['sessionDir'] . '/*') ?: []);
        rmdir($child['sessionDir']);
        return ['exit' => $exit, 'output' => $output];
    }
}
