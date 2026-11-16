<?php
// 20261023 CL/LH MB-95 A receipt print, and above all a reprint on another terminal, must not move the
// sale to the printing terminal's department.
//
// pos_txt_print() used to write the terminal's afd onto the order on every print. A POS sale already
// has its afd by then: while it is open, every render (vis_pos_linjer(), also called by betaling()
// inside afslut()) writes the current terminal's afd, and bogfor_nu() posts with it before the
// receipt is printed. So the print-time write only ever changed a booked sale, on a reprint.
//
// Both pieces of code are fragments of functions that cannot run here (pos_txt_print() includes a
// dozen files and writes under ../temp), so the fragments are lifted from the shipped source and run
// against sqlite.
//
// 20261116 CL/MMR SST-1019 The same block sets $afd again, read-only, because the per-regnskab receipt
// templates (debitor/pos_print/pos_print_<db_id>.php, only on the server) pick the shop header and the
// printer from it. MB-95 had removed the variable together with the write, and the templates fell back to
// the default header. The tests below pin the value and that it still never writes the order.

namespace Saldi\Tests\PosCallSites;

use PDO;
use PHPUnit\Framework\TestCase;

final class PosReceiptDepartmentTest extends TestCase
{
    private const REGNAAR = 2026;
    private const SALE = 7;

    private PDO $db;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is required for the isolated POS fixtures.');
        }
        require_once __DIR__ . '/support/pos_call_sites_database.php';

        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $GLOBALS['posCallSitesDb'] = $this->db;
        $GLOBALS['posCallSitesSql'] = [];
        $GLOBALS['posCallSitesAlerts'] = [];

        $this->db->exec("CREATE TABLE grupper (id INTEGER PRIMARY KEY AUTOINCREMENT, art TEXT, kodenr TEXT,
            box1 TEXT, box2 TEXT, box3 TEXT, box7 TEXT, fiscal_year TEXT)");
        $this->db->exec("CREATE TABLE ordrer (id INTEGER PRIMARY KEY, afd INTEGER, status INTEGER,
            sum NUMERIC, moms NUMERIC, hvem TEXT, felt_5 TEXT)");

        // Two terminals: terminal 1 is department 1, terminal 2 is department 2, each with a printer.
        $tab = chr(9);
        $this->db->exec("INSERT INTO grupper (art,kodenr,box3,fiscal_year)
            VALUES ('POS','1','1{$tab}2','" . self::REGNAAR . "')");
        $this->db->exec("INSERT INTO grupper (art,kodenr,box3,fiscal_year)
            VALUES ('POS','2','10.0.0.1{$tab}10.0.0.2','" . self::REGNAAR . "')");

        self::liftFragments();
    }

    private static function liftFragments(): void
    {
        static $lifted = false;
        if ($lifted) {
            return;
        }
        $print = liftBetween(
            shipped('debitor/pos_ordre_includes/posTxtPrint/posTxtPrintFunc.php'),
            'if (!$kasse) $kasse=find_kasse($kasse);',
            "if (!\$printserver)\$printserver='localhost';"
        );
        $ifset = liftBlock(shipped('includes/std_func.php'), 'function ifset(')
            . "\n" . liftBlock(shipped('includes/std_func.php'), 'function ifset_resolve_default(');
        // Only the department lookup itself, for the edge cases: the printer lookup next to it has its own,
        // older warnings for a register without a printer, which are not what these tests are about.
        $department = liftBetween(
            shipped('debitor/pos_ordre_includes/posTxtPrint/posTxtPrintFunc.php'),
            "\$afd = '';",
            "ifset(\$departments, (int)\$kasse - 1, ''));\n\t}"
        );
        $render = liftBlock(
            shipped('debitor/pos_ordre_includes/showPosLines/showPosLinesFunc.php'),
            "if ((\$nettosum || \$nettosum == '0') && \$status<3 && \$id) {"
        );
        eval('namespace ' . __NAMESPACE__ . '; ' . $ifset
            . ' function receipt_print_terminal_block($id, $kasse, $regnaar) { ' . $print . "\n return \$printserver; }"
            . ' function receipt_print_terminal_afd($id, $kasse, $regnaar) { ' . $print . "\n return \$afd; }"
            . ' function receipt_department_lookup($kasse, $regnaar) { ' . $department . "\n return \$afd; }"
            . ' function open_sale_render_update($id, $status, $nettosum, $sum, $afd, $kasse, $brugernavn) { '
            . $render . "\n }");
        $lifted = true;
    }

    private function sale(int $afd, int $status): void
    {
        $this->db->exec("INSERT INTO ordrer (id,afd,status,sum,moms,felt_5)
            VALUES (" . self::SALE . ",$afd,$status,100,25,'1')");
    }

    private function saleAfd(): int
    {
        return (int)$this->db->query("SELECT afd FROM ordrer WHERE id = " . self::SALE)->fetchColumn();
    }

    // ------------------------------------------------------------ the print

    public function testAReprintOnAnotherTerminalLeavesTheBookedSalesDepartment(): void
    {
        $this->sale(1, 3);   // sold and booked on terminal 1

        receipt_print_terminal_block(self::SALE, '2', self::REGNAAR);

        self::assertSame(1, $this->saleAfd(),
            'the copy printed on terminal 2 moved the sale to department 2, away from its postings');
        foreach ($GLOBALS['posCallSitesSql'] as $sql) {
            self::assertStringNotContainsStringIgnoringCase('update ordrer', $sql, 'a print does not write the order');
        }
    }

    public function testThePrintStillUsesThePrintingTerminalsPrinter(): void
    {
        self::assertSame('10.0.0.2', receipt_print_terminal_block(self::SALE, '2', self::REGNAAR));
        self::assertSame('10.0.0.1', receipt_print_terminal_block(self::SALE, ' 1 ', self::REGNAAR),
            'the terminal number is trimmed before it is used as an index');
    }

    public function testATerminalWithoutAPrinterFallsBackToLocalhost(): void
    {
        $this->db->exec("UPDATE grupper SET box3 = '10.0.0.1' || char(9) WHERE art = 'POS' AND kodenr = '2'");
        self::assertSame('localhost', receipt_print_terminal_block(self::SALE, '2', self::REGNAAR));
    }

    // ----------------------------- the department the receipt templates read

    /**
     * Runs $code with every PHP warning turned into a failure.
     *
     * @param callable(): string $code
     */
    private static function strict(callable $code): string
    {
        set_error_handler(static function (int $no, string $message, string $file, int $line): bool {
            throw new \ErrorException($message, 0, $no, $file, $line);
        });
        try {
            return $code();
        } finally {
            restore_error_handler();
        }
    }

    /** The $afd the whole print block leaves for the template. */
    private function templateAfd(string $kasse): string
    {
        return self::strict(fn() => receipt_print_terminal_afd(self::SALE, $kasse, self::REGNAAR));
    }

    /** The $afd from the department lookup alone. */
    private function lookupAfd(string $kasse): string
    {
        return self::strict(fn() => receipt_department_lookup($kasse, self::REGNAAR));
    }

    public function testTheTemplatesGetTheDepartmentOfTheSalesRegister(): void
    {
        // SST-1019: Phone 2 Fix's template prints the "Phone Expert" header when $afd is '2'.
        self::assertSame('2', $this->templateAfd('2'));
        self::assertSame('1', $this->templateAfd(' 1 '), 'the register number is trimmed before it is used');
    }

    public function testTheDepartmentIsReadOnlyAndNeverWrittenToTheOrder(): void
    {
        $this->sale(1, 3);

        self::assertSame('2', $this->templateAfd('2'));

        self::assertSame(1, $this->saleAfd(), 'reading the department for the template moved the sale');
        foreach ($GLOBALS['posCallSitesSql'] as $sql) {
            self::assertStringNotContainsStringIgnoringCase('update ordrer', $sql);
        }
    }

    public function testAnUnknownRegisterOrMissingSetupGivesAnEmptyDepartment(): void
    {
        self::assertSame('', $this->lookupAfd('3'), 'a register beyond the list');
        self::assertSame('', $this->lookupAfd('0'), 'register 0');
        self::assertSame('', $this->lookupAfd(''), 'no register');

        $this->db->exec("UPDATE grupper SET box3 = NULL WHERE art = 'POS' AND kodenr = '1'");
        self::assertSame('', $this->lookupAfd('2'), 'no departments set up');

        $this->db->exec("DELETE FROM grupper WHERE art = 'POS' AND kodenr = '1'");
        self::assertSame('', $this->lookupAfd('2'), 'no POS row for the year');
    }

    // ------------------------------------- where an open sale gets its afd

    public function testAnOpenSaleTakesTheDepartmentOfTheTerminalItIsShownOn(): void
    {
        $this->sale(0, 1);

        open_sale_render_update(self::SALE, 1, 100, 125, 2, '2', 'kasse2');

        self::assertSame(2, $this->saleAfd());
        self::assertSame('2', $this->db->query("SELECT felt_5 FROM ordrer WHERE id = " . self::SALE)->fetchColumn());
    }

    public function testABookedSaleIsNotRewrittenWhenItIsShown(): void
    {
        $this->sale(1, 3);

        open_sale_render_update(self::SALE, 3, 100, 125, 2, '2', 'kasse2');

        self::assertSame(1, $this->saleAfd());
    }

    public function testPaymentRendersTheSaleBeforeItIsBookedAndBooksBeforeItPrints(): void
    {
        // The order of the calls is what makes the render-time afd the one that is posted. Read from
        // the source: afslut() cannot run outside a POS request.
        $afslut = liftBlock(shipped('debitor/pos_ordre_includes/exitFunc/exit.php'), 'function afslut(');
        $pay = strpos($afslut, '$sum = betaling($id,');
        $book = strpos($afslut, 'include("settlePOS.php");');
        $print = strpos($afslut, 'pos_txt_print($id,');
        self::assertNotFalse($pay, 'afslut() no longer calls betaling()');
        self::assertNotFalse($book, 'afslut() no longer books through settlePOS.php');
        self::assertNotFalse($print, 'afslut() no longer prints the receipt');
        self::assertLessThan($book, $pay);
        self::assertLessThan($print, $book);

        $betaling = liftBlock(shipped('debitor/pos_ordre.php'), 'function betaling(');
        self::assertStringContainsString('vis_pos_linjer($id, $momssats, $status, NULL, $show)', $betaling,
            'betaling() no longer renders the sale, so it would no longer get its afd before booking');
    }
}
