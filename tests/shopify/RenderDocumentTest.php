<?php
// tests/shopify/RenderDocumentTest.php
//
// render_document() (SD-751, WP11-1) against a throwaway tenant: a posted invoice or credit note
// renders to temp/<db>/.ht_doc_<sha>.pdf without a session, mails nothing and prints nothing; a
// brand without its own layout (or with only its 'generelt' row) and an unposted order are refused;
// concurrent renders of the same document produce it once. Each render runs in a child process booted by shopify_bootstrap().
// pdftk is fake_pdftk.php unless the machine has a real one (the last test then uses it); ps2pdf
// is required. Skips when ShopifyEnv is not configured.
//
// History:
// 20261029 CL/SZ SD-751: created.
// 20261029 CL/SZ SD-751: a brand without a letterhead of its own renders without one.
// 20261029 CL/SZ SD-751 review: the refused-brand tests use brands no template has.
// 20261030 CL/SZ SD-751 review: another account on the same database gets its own PDF; a brand with a / is refused.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/ShopifyEnv.php';

final class RenderDocumentTest extends TestCase
{
    private static ?string $skip = null;
    private static string $db = '';
    private static string $ps2pdf = '';
    private static int $debtorId = 0;
    private static int $dbId = 0;
    private static int $next = 9100;

    public static function setUpBeforeClass(): void
    {
        self::$skip = ShopifyEnv::unavailableReason();
        self::$ps2pdf = trim((string)shell_exec('command -v ps2pdf 2>/dev/null'));
        if (self::$skip === null && self::$ps2pdf === '') {
            self::$skip = 'ps2pdf (ghostscript) is not installed';
        }
        if (self::$skip !== null) {
            return;
        }
        chmod(__DIR__ . '/support/fake_pdftk.php', 0755);
        try {
            self::$db = ShopifyEnv::testDb('_r');
            self::removeTempDir();
            ShopifyEnv::cloneTenant(self::$db);
            self::$dbId = ShopifyEnv::registerTenant(self::$db);
            $conn = ShopifyEnv::connect(self::$db);
            ShopifyEnv::rows($conn, "DELETE FROM grupper WHERE art = 'RA'");
            ShopifyEnv::rows($conn, "INSERT INTO grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) VALUES ('RA', 1, 'RA', '01', $1, '12', $1, 'on')", [date('Y')]);
            $debtor = ShopifyEnv::one($conn, "INSERT INTO adresser (kontonr, firmanavn, addr1, postnr, bynavn, email, art, gruppe, betalingsbet, betalingsdage)"
                . " VALUES (91900, 'Render Kunde', 'Pdfvej 3', '8000', 'Aarhus', 'render@example.invalid', 'D', 1, 'Netto', 8) RETURNING id");
            self::$debtorId = (int)$debtor['id'];
            pg_close($conn);
        } catch (Throwable $e) {
            self::tearDownAfterClass();
            throw $e;
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$db !== '') {
            self::removeTempDir();
        }
        if (self::$dbId > 0) {
            // formularprint() creates logolib/<db_id>/; the letterhead test writes files into it
            $logolib = ShopifyEnv::repoRoot() . '/logolib/' . self::$dbId;
            array_map('unlink', glob("$logolib/*.pdf") ?: []);
            @rmdir($logolib);
        }
        ShopifyEnv::unregisterTenants();
        ShopifyEnv::dropOwnedTenants();
    }

    /** Removes temp/<test db>/ with everything in it, also what an earlier, aborted run left. */
    private static function removeTempDir(): void
    {
        $dir = ShopifyEnv::repoRoot() . '/temp/' . self::$db;
        if (!is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    protected function setUp(): void
    {
        if (self::$skip !== null) {
            $this->markTestSkipped(self::$skip);
        }
    }

    /** @return array{id: int, fakturanr: string} a posted invoice (DO) or credit note (DK), 2 x 125.00 at 25% VAT */
    private function posted(string $art = 'DO', string $sprog = 'Dansk', int $status = 3, string $udskrivTil = 'email'): array
    {
        $nr = (string)++self::$next;
        $sign = $art === 'DK' ? -1 : 1;
        $conn = ShopifyEnv::connect(self::$db);
        $order = ShopifyEnv::one($conn, "INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsbet, betalingsdage, kontonr, art, valuta,"
            . " ordredate, levdate, fakturadate, ordrenr, fakturanr, sum, moms, momssats, status, konto_id, udskriv_til, mail_fakt, sprog)"
            . " VALUES ('Render Kunde', 'Pdfvej 3', '8000', 'Aarhus', 'render@example.invalid', 'Netto', 8, 91900, $1, 'DKK',"
            . " '2026-10-01', '2026-10-01', '2026-10-01', $2, $3, $4, $5, 25, $6, $7, $8, 'on', $9) RETURNING id",
            [$art, (int)$nr, $nr, $sign * 250, $sign * 62.5, $status, self::$debtorId, $udskrivTil, $sprog]);
        ShopifyEnv::rows($conn, "INSERT INTO ordrelinjer (ordre_id, vare_id, varenr, beskrivelse, enhed, posnr, antal, pris, rabat, momssats, procent)"
            . " VALUES ($1, 0, 'R1', 'Rendervare', 'stk', 1, $2, 125.00, 0, 25, 100)", [(int)$order['id'], $sign * 2]);
        pg_close($conn);
        return ['id' => (int)$order['id'], 'fakturanr' => $nr];
    }

    /** @return array{proc: resource, pipes: array<int, resource>} */
    private function start(int $orderId, int $formular = 4, array $env = []): array
    {
        return ShopifyEnv::startChild('run_render_document.php', [self::$db, $orderId, $formular],
            $env + ['RENDER_PDFTK' => __DIR__ . '/support/fake_pdftk.php', 'RENDER_PS2PDF' => self::$ps2pdf]);
    }

    /** @return array<string, mixed> the runner's JSON */
    private function finish(array $child): array
    {
        $run = ShopifyEnv::waitChild($child);
        $json = json_decode($run['stdout'], true);
        $this->assertIsArray($json, "runner gave no JSON (exit {$run['exit']}): {$run['stdout']} {$run['stderr']}");
        return $json;
    }

    private function render(int $orderId, int $formular = 4, array $env = []): array
    {
        return $this->finish($this->start($orderId, $formular, $env));
    }

    private function docDir(): string
    {
        return ShopifyEnv::repoRoot() . '/temp/' . self::$db;
    }

    public function testAPostedInvoiceRendersToOnePdfWithoutMailOrOutput(): void
    {
        $doc = $this->posted();

        $out = $this->render($doc['id']);

        $this->assertArrayNotHasKey('error', $out, json_encode($out['error'] ?? null));
        $result = $out['result'];
        $this->assertMatchesRegularExpression('#/temp/' . preg_quote(self::$db, '#') . '/\.ht_doc_[0-9a-f]{64}\.pdf$#', $result['path']);
        $this->assertSame("fakt{$doc['fakturanr']}.pdf", $result['filename']);
        $this->assertSame(4, $result['formular']);
        $this->assertSame('dansk', $result['brand']);
        $this->assertTrue($result['rendered']);
        $this->assertSame('%PDF-', file_get_contents($result['path'], false, null, 0, 5));
        $this->assertSame([], $out['mails'], 'an invoice set to email is not mailed');
        $this->assertSame('', $out['printed'], 'nothing is printed');
        $this->assertSame($out['cwd_before'], $out['cwd_after'], 'the working directory is restored');
        $this->assertSame([], glob($this->docDir() . '/.ht_doc_*.work') ?: [], 'no work directory is left');
        $lock = preg_replace('/\.pdf$/', '.lock', $result['path']);
        $this->assertSame('0666', substr(sprintf('%o', fileperms($lock)), -4), 'the lock can be opened by another OS user (worker vs Apache)');
        $this->assertSame([], glob(ShopifyEnv::repoRoot() . '/temp/' . self::$db . '/0/*') ?: [], "nothing in the user's print directory");
    }

    public function testASecondRenderReturnsTheSamePdf(): void
    {
        $doc = $this->posted();
        $first = $this->render($doc['id'])['result'];

        $again = $this->render($doc['id'])['result'];

        $this->assertSame($first['path'], $again['path']);
        $this->assertFalse($again['rendered']);
    }

    public function testADifferentInvoiceUnderTheSameIdAndNumberGetsItsOwnPdf(): void
    {
        // e.g. after a backup is restored into the same database
        $doc = $this->posted();
        $first = $this->render($doc['id'])['result'];
        $conn = ShopifyEnv::connect(self::$db);
        ShopifyEnv::rows($conn, "UPDATE ordrer SET firmanavn = 'Anden Kunde' WHERE id = $1", [$doc['id']]);
        pg_close($conn);

        $second = $this->render($doc['id'])['result'];

        $this->assertNotSame($first['path'], $second['path']);
        $this->assertTrue($second['rendered']);
    }

    public function testAnotherAccountOnTheSameDatabaseGetsItsOwnPdf(): void
    {
        // the letterhead comes from logolib/<db_id>/, so one account's PDF is never another's
        $doc = $this->posted();
        $first = $this->render($doc['id'])['result'];

        $other = $this->render($doc['id'], 4, ['RENDER_DB_ID' => (string)(self::$dbId + 100000)])['result'];

        $this->assertNotSame($first['path'], $other['path']);
        $this->assertTrue($other['rendered']);
    }

    public function testALayoutRowEditedInPlaceRendersANewPdf(): void
    {
        $doc = $this->posted();
        $first = $this->render($doc['id'])['result'];
        $conn = ShopifyEnv::connect(self::$db);
        $row = ShopifyEnv::one($conn, "SELECT id, xa FROM formularer WHERE formular = 4 AND art = 3 AND beskrivelse <> 'generelt' AND lower(sprog) = 'dansk' ORDER BY id LIMIT 1");
        ShopifyEnv::rows($conn, 'UPDATE formularer SET xa = xa + 1 WHERE id = $1', [(int)$row['id']]);
        try {
            $second = $this->render($doc['id'])['result'];
        } finally {
            ShopifyEnv::rows($conn, 'UPDATE formularer SET xa = $2 WHERE id = $1', [(int)$row['id'], $row['xa']]);
            pg_close($conn);
        }

        $this->assertNotSame($first['path'], $second['path']);
        $this->assertTrue($second['rendered']);
    }

    public function testWithoutPdftkNothingIsRendered(): void
    {
        $doc = $this->posted();

        $out = $this->render($doc['id'], 4, ['RENDER_PDFTK' => 'no-such-pdftk-' . getmypid()]);

        $this->assertSame(['class' => 'DocumentRenderError', 'message' => 'pdftk is not installed', 'code' => 0], $out['error']);
    }

    public function testACreditNoteRendersAsKnWhicheverFormularIsAsked(): void
    {
        $doc = $this->posted('DK');

        $out = $this->render($doc['id'], 4);

        $this->assertArrayNotHasKey('error', $out, json_encode($out['error'] ?? null));
        $this->assertSame("kn{$doc['fakturanr']}.pdf", $out['result']['filename']);
        $this->assertSame(5, $out['result']['formular']);
    }

    public function testABrandWithoutItsOwnLayoutIsRefusedNotPrintedInAnother(): void
    {
        $doc = $this->posted('DO', 'Klingon'); // a brand no template has layouts for

        $out = $this->render($doc['id']);

        $this->assertSame(['class' => 'DocumentRenderError', 'message' => 'no layout for formular 4 in brand klingon', 'code' => 0], $out['error']);
    }

    public function testABrandThatIsNoPlainFileNameIsRefused(): void
    {
        // the brand names the letterhead files in logolib/<db_id>/
        $doc = $this->posted('DO', 'Dansk/../Norsk');

        $out = $this->render($doc['id']);

        $this->assertSame(['class' => 'DocumentRenderError', 'message' => 'brand dansk/../norsk is not a file name', 'code' => 0], $out['error']);
    }

    public function testABrandWithOnlyTheGeneralLayoutRowIsRefusedToo(): void
    {
        $conn = ShopifyEnv::connect(self::$db);
        ShopifyEnv::rows($conn, "INSERT INTO formularer (formular, art, beskrivelse, xa, ya, xb, sprog) SELECT formular, art, beskrivelse, xa, ya, xb, 'Vulcan'"
            . " FROM formularer WHERE formular = 4 AND art = 3 AND beskrivelse = 'generelt' AND lower(sprog) = 'dansk'");
        pg_close($conn);
        $doc = $this->posted('DO', 'Vulcan');

        $out = $this->render($doc['id']);

        $this->assertSame(['class' => 'DocumentRenderError', 'message' => 'no layout for formular 4 in brand vulcan', 'code' => 0], $out['error']);
    }

    public function testAnUnpostedOrderAndAnOrderThatIsNoInvoiceAreRefused(): void
    {
        $draft = $this->posted('DO', 'Dansk', 1);

        $unposted = $this->render($draft['id']);
        $missing = $this->render(999999);
        $delivery = $this->render($draft['id'], 3);

        $this->assertSame(['class' => 'DocumentRenderError', 'message' => "order {$draft['id']} is not posted", 'code' => 1], $unposted['error']);
        $this->assertSame(['class' => 'DocumentRenderError', 'message' => 'order 999999 is not an invoice or credit note', 'code' => 1], $missing['error']);
        $this->assertSame('InvalidArgumentException', $delivery['error']['class']);
    }

    public function testTheLetterheadIsTheBrandsOwnOrNone(): void
    {
        $conn = ShopifyEnv::connect(self::$db);
        $cols = implode(', ', array_map(fn(array $r): string => $r['column_name'],
            ShopifyEnv::rows($conn, "SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'formularer' AND column_name NOT IN ('id', 'sprog')")));
        ShopifyEnv::rows($conn, "INSERT INTO formularer ($cols, sprog) SELECT $cols, 'Norsk' FROM formularer WHERE formular = 4 AND lower(sprog) = 'dansk'");
        pg_close($conn);
        $logolib = ShopifyEnv::repoRoot() . '/logolib/' . self::$dbId;
        if (!is_dir($logolib)) {
            mkdir($logolib, 0777, true);
        }
        $pdf = "%PDF-1.4\n%%EOF\n";
        file_put_contents("$logolib/faktura_bg.pdf", $pdf);
        $log = tempnam(sys_get_temp_dir(), 'pdftk');
        $background = function (array $doc) use ($log): ?string {
            file_put_contents($log, '');
            $out = $this->render($doc['id'], 4, ['FAKE_PDFTK_LOG' => $log]);
            $this->assertArrayNotHasKey('error', $out, json_encode($out['error'] ?? null));
            foreach (file($log, FILE_IGNORE_NEW_LINES) ?: [] as $call) {
                if (preg_match('/ background (\S+) /', $call, $m)) {
                    return $m[1];
                }
            }
            return null;
        };
        try {
            $this->assertSame('faktura_bg.pdf', $background($this->posted('DO', 'Dansk')), 'dansk gets the default letterhead');
            $this->assertNull($background($this->posted('DO', 'Norsk')), 'a brand without its own letterhead gets none, not the dansk one');
            file_put_contents("$logolib/norsk_bg.pdf", $pdf);
            $this->assertSame('norsk_bg.pdf', $background($this->posted('DO', 'Norsk')), "a brand's own letterhead is used");
        } finally {
            unlink($log);
            array_map('unlink', glob("$logolib/*.pdf") ?: []);
        }
    }

    public function testConcurrentRendersOfOneDocumentRenderItOnceAndShareIt(): void
    {
        $doc = $this->posted();
        $log = tempnam(sys_get_temp_dir(), 'pdftk');
        $env = ['FAKE_PDFTK_LOG' => $log, 'FAKE_PDFTK_SLEEP_MS' => '700'];

        $children = [$this->start($doc['id'], 4, $env), $this->start($doc['id'], 4, $env)];
        $outs = array_map(fn(array $c): array => $this->finish($c), $children);

        $calls = file($log, FILE_IGNORE_NEW_LINES) ?: [];
        unlink($log);
        foreach ($outs as $out) {
            $this->assertArrayNotHasKey('error', $out, json_encode($out['error'] ?? null));
        }
        $this->assertSame($outs[0]['result']['path'], $outs[1]['result']['path']);
        $rendered = array_map(fn(array $o): bool => $o['result']['rendered'], $outs);
        sort($rendered);
        $this->assertSame([false, true], $rendered, 'exactly one of the two renders produced the PDF');
        $this->assertCount(1, array_unique(array_map(fn(string $l): string => strtok($l, ' '), $calls)), 'pdftk ran in one process only: ' . implode(' | ', $calls));
    }

    public function testWithTheRealPdftkTheInvoiceIsAOnePagePdf(): void
    {
        $pdftk = trim((string)shell_exec('command -v pdftk 2>/dev/null'));
        if ($pdftk === '') {
            $this->markTestSkipped('pdftk is not installed');
        }
        $doc = $this->posted();

        $out = $this->render($doc['id'], 4, ['RENDER_PDFTK' => $pdftk]);

        $this->assertArrayNotHasKey('error', $out, json_encode($out['error'] ?? null));
        $info = (string)shell_exec(escapeshellarg($pdftk) . ' ' . escapeshellarg($out['result']['path']) . ' dump_data 2>/dev/null');
        $this->assertStringContainsString('NumberOfPages: 1', $info);
    }
}
