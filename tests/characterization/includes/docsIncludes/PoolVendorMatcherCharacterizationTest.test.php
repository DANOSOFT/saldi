<?php
// 20260922 CL/LAH Leverandørforslag fra AI-scan: pins CVR/IBAN/name normalization and the
//                  cvr -> bank -> name match order of includes/docsIncludes/poolVendorMatcher.php
//                  (kravspec Bilagsflow AI-1/AI-2/AI-7). Same shape as
//                  PoolAmountNormalizerCharacterizationTest: pure functions, no database.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PoolVendorMatcherCharacterizationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        ob_start();
        require_once dirname(__DIR__, 4) . '/includes/docsIncludes/poolVendorMatcher.php';
        $includeOutput = ob_get_clean();
        self::assertSame('', $includeOutput, 'poolVendorMatcher.php emitted output at include time');
        self::assertTrue(function_exists('poolVendorMatch'));
    }

    /**
     * A small kreditor register shaped like adresser (art = 'K') rows.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function kreditorer(): array
    {
        return [
            ['id' => 418, 'kontonr' => '30120', 'firmanavn' => 'Dan Group Alarm', 'cvrnr' => 'DK12345678', 'iban' => null, 'bank_reg' => '3001', 'bank_konto' => '0004567890'],
            ['id' => 16, 'kontonr' => '1010', 'firmanavn' => 'Papyrus Supplies A/S', 'cvrnr' => '34895058', 'iban' => '', 'bank_reg' => '7632', 'bank_konto' => '2013814'],
            ['id' => 14, 'kontonr' => '1008', 'firmanavn' => 'Littmann - 3M Danmark - https://partnerportal.solventum.com/da_DK/', 'cvrnr' => 'DK43335316', 'iban' => null, 'bank_reg' => '0892', 'bank_konto' => '1001348'],
            ['id' => 15, 'kontonr' => '1009', 'firmanavn' => 'DSN Trade - iryna@dsntrade.com', 'cvrnr' => 'NL805494595B01', 'iban' => 'NL20INGB 0669504912', 'bank_reg' => null, 'bank_konto' => null],
            ['id' => 11, 'kontonr' => '1005', 'firmanavn' => 'KaWe - sales@kawemed.de', 'cvrnr' => 'DE146132614', 'iban' => null, 'bank_reg' => null, 'bank_konto' => 'DE96604500500000019077'],
            ['id' => 501, 'kontonr' => '30500', 'firmanavn' => 'Nordisk Kontor ApS', 'cvrnr' => '87654321', 'iban' => null, 'bank_reg' => null, 'bank_konto' => null],
            ['id' => 502, 'kontonr' => '30501', 'firmanavn' => 'Nordisk Kontor Odense ApS', 'cvrnr' => '87654321', 'iban' => null, 'bank_reg' => null, 'bank_konto' => null],
            ['id' => 503, 'kontonr' => '30502', 'firmanavn' => 'Nordisk Kontor Aarhus ApS', 'cvrnr' => '87654321', 'iban' => null, 'bank_reg' => null, 'bank_konto' => null],
            ['id' => 504, 'kontonr' => '30503', 'firmanavn' => 'Nordisk Kontor Aalborg ApS', 'cvrnr' => '87654321', 'iban' => null, 'bank_reg' => null, 'bank_konto' => null],
            ['id' => 505, 'kontonr' => '30504', 'firmanavn' => 'Nordisk Kontor Esbjerg ApS', 'cvrnr' => '87654321', 'iban' => null, 'bank_reg' => null, 'bank_konto' => null],
            ['id' => 506, 'kontonr' => '30505', 'firmanavn' => 'Nordisk Kontor Kolding ApS', 'cvrnr' => '87654321', 'iban' => null, 'bank_reg' => null, 'bank_konto' => null],
            ['id' => 600, 'kontonr' => '30600', 'firmanavn' => 'Byggemarked Nord A/S', 'cvrnr' => '', 'iban' => null, 'bank_reg' => null, 'bank_konto' => null],
            ['id' => 601, 'kontonr' => '30601', 'firmanavn' => 'Byggemarked Vest A/S', 'cvrnr' => '', 'iban' => null, 'bank_reg' => null, 'bank_konto' => null],
            ['id' => 700, 'kontonr' => '30700', 'firmanavn' => 'Elgiganten', 'cvrnr' => null, 'iban' => 'DK5000400440116243', 'bank_reg' => null, 'bank_konto' => null],
            ['id' => 800, 'kontonr' => '30800', 'firmanavn' => '', 'cvrnr' => null, 'iban' => null, 'bank_reg' => null, 'bank_konto' => null],
        ];
    }

    private static function index(): array
    {
        static $index = null;
        if ($index === null) {
            $index = poolVendorBuildIndex(self::kreditorer());
        }
        return $index;
    }

    // ---------------------------------------------------------------
    // AI-1: CVR normalization
    // ---------------------------------------------------------------

    /** @return array<string, array{string|null, string|null}> */
    public static function cvrInputs(): array
    {
        return [
            'spaced DK prefix'          => ['DK 12 34 56 78', '12345678'],
            'DK prefix'                 => ['DK12345678', '12345678'],
            'plain digits'              => ['12345678', '12345678'],
            'dashed digits'             => ['12-34-56-78', '12345678'],
            'lowercase dk'              => ['dk12345678', '12345678'],
            'CVR label'                 => ['CVR: 12345678', '12345678'],
            'CVR-nr label'              => ['CVR-nr.: DK 12345678', '12345678'],
            'SE-nr label'               => ['SE-nr. 12345678', '12345678'],
            'VAT no label'              => ['VAT no: DK12345678', '12345678'],
            'swedish VAT unchanged'     => ['SE556677889901', 'SE556677889901'],
            'swedish VAT spaced'        => ['SE 5566-7788 9901', 'SE556677889901'],
            'dutch VAT with letter'     => ['NL805494595B01', 'NL805494595B01'],
            'german VAT'                => ['DE 146 132 614', 'DE146132614'],
            'seven digits is not a CVR' => ['1234567', null],
            'nine digits is not a CVR'  => ['123456789', null],
            'free text'                 => ['ikke oplyst', null],
            'letters only'              => ['DKUKENDT', null],
            'empty'                     => ['', null],
            'null'                      => [null, null],
        ];
    }

    #[DataProvider('cvrInputs')]
    public function testCvrNormalization(?string $input, ?string $expected): void
    {
        self::assertSame($expected, normalizePoolVendorCvr($input));
    }

    // ---------------------------------------------------------------
    // Bank details: IBAN and reg.nr./kontonr.
    // ---------------------------------------------------------------

    public function testIbanNormalizationStripsSpacesAndRejectsNonIban(): void
    {
        self::assertSame('NL20INGB0669504912', normalizePoolVendorIban('NL20 INGB 0669 5049 12'));
        self::assertSame('DK5000400440116243', normalizePoolVendorIban('dk50 0040 0440 1162 43'));
        self::assertNull(normalizePoolVendorIban('0892 1001348'));
        self::assertNull(normalizePoolVendorIban(''));
        self::assertNull(normalizePoolVendorIban(null));
    }

    public function testBankAccountKeepsRegZerosAndDropsKontoZeros(): void
    {
        self::assertSame('0892-1001348', normalizePoolVendorBankAccount('0892', '0001001348'));
        self::assertSame('0892-1001348', normalizePoolVendorBankAccount('892', '1001348'));
        self::assertSame('7632-2013814', normalizePoolVendorBankAccount('7632', '2013814'));
        self::assertNull(normalizePoolVendorBankAccount('7632', ''));
        self::assertNull(normalizePoolVendorBankAccount(null, '2013814'));
    }

    public function testDanishIbanAlsoYieldsItsRegKontoKey(): void
    {
        self::assertSame(
            ['IBAN:DK5000400440116243', 'ACCT:0040-440116243'],
            poolVendorBankKeys('DK50 0040 0440 1162 43', null, null)
        );
        self::assertSame(['ACCT:0892-1001348'], poolVendorBankKeys(null, '0892', '1001348'));
        self::assertSame([], poolVendorBankKeys(null, null, null));
    }

    // ---------------------------------------------------------------
    // Name normalization and pg_trgm-style similarity
    // ---------------------------------------------------------------

    /** @return array<string, array{string|null, string}> */
    public static function nameInputs(): array
    {
        return [
            'ApS removed'                 => ['Dan Group Alarm ApS', 'dan group alarm'],
            'A/S removed'                 => ['Papyrus Supplies A/S', 'papyrus supplies'],
            'I/S removed'                 => ['Brdr. Hansen I/S', 'brdr hansen'],
            'AB removed'                  => ['Kontorsmöbler AB', 'kontorsmöbler'],
            'GmbH removed'                => ['KaWe GmbH', 'kawe'],
            'Ltd. removed'                => ['Amplivox Ltd.,', 'amplivox'],
            'email dropped'               => ['DSN Trade - iryna@dsntrade.com', 'dsn trade'],
            'url dropped'                 => ['Littmann - 3M Danmark - https://partnerportal.solventum.com/da_DK/', 'littmann 3m danmark'],
            '(LUKKET) dropped'            => ['PMB (LUKKET)', 'pmb'],
            'punctuation collapsed'       => ['Hans & Grete, Bageri.', 'hans grete bageri'],
            'as inside a word is kept'    => ['Assistancehuset', 'assistancehuset'],
            'empty'                       => ['', ''],
            'null'                        => [null, ''],
        ];
    }

    #[DataProvider('nameInputs')]
    public function testNameNormalization(?string $input, string $expected): void
    {
        self::assertSame($expected, normalizePoolVendorName($input));
    }

    public function testTrigramsFollowPgTrgmPadding(): void
    {
        self::assertSame(['  d', ' da', 'dan', 'an '], array_keys(poolVendorTrigrams('dan')));
        self::assertSame(1.0, poolVendorNameSimilarity(poolVendorTrigrams('dan group'), poolVendorTrigrams('dan group')));
        self::assertSame(0.0, poolVendorNameSimilarity(poolVendorTrigrams('dan'), poolVendorTrigrams('xyz')));
        self::assertSame(0.0, poolVendorNameSimilarity([], poolVendorTrigrams('dan')));
        $sim = poolVendorNameSimilarity(poolVendorTrigrams('dan group alarm'), poolVendorTrigrams('dan group'));
        self::assertGreaterThan(0.5, $sim);
        self::assertLessThan(1.0, $sim);
    }

    // ---------------------------------------------------------------
    // AI-2: match order cvr -> bank -> name, thresholds, ambiguity
    // ---------------------------------------------------------------

    public function testCvrWinsEvenWhenTheNameIsDifferent(): void
    {
        $r = poolVendorMatch(['name' => 'DGA Sikring ApS', 'cvr' => 'DK 12 34 56 78'], self::index());
        self::assertSame('cvr', $r['match']);
        self::assertSame(1.0, $r['score']);
        self::assertSame(418, $r['kontoId']);
        self::assertSame('30120', $r['kontonr']);
        self::assertSame('Dan Group Alarm', $r['firmanavn']);
        self::assertSame('12345678', $r['cvr']);
        self::assertSame('DGA Sikring ApS', $r['name']);
        self::assertSame([], $r['candidates']);
    }

    public function testForeignVatNumberMatchesUnchanged(): void
    {
        $r = poolVendorMatch(['name' => 'DSN Trade B.V.', 'cvr' => 'NL 8054.94.595.B01'], self::index());
        self::assertSame('cvr', $r['match']);
        self::assertSame(15, $r['kontoId']);
    }

    public function testBankMatchWhenCvrIsUnknown(): void
    {
        $r = poolVendorMatch(['name' => 'Unknown Supplier', 'bank_reg' => '0892', 'bank_konto' => '0001001348'], self::index());
        self::assertSame('bank', $r['match']);
        self::assertSame(0.95, $r['score']);
        self::assertSame(14, $r['kontoId']);
        self::assertSame('0892-1001348', $r['iban']);
    }

    public function testIbanMatchesAKreditorWhoseIbanHasSpaces(): void
    {
        $r = poolVendorMatch(['name' => '', 'iban' => 'NL20INGB0669504912'], self::index());
        self::assertSame('bank', $r['match']);
        self::assertSame(15, $r['kontoId']);
        self::assertSame('NL20INGB0669504912', $r['iban']);
        self::assertNull($r['name']);
    }

    public function testDanishIbanOnInvoiceMatchesRegKontoOnKreditor(): void
    {
        // Kreditor 16 only has reg 7632 / konto 2013814; the invoice prints the IBAN form.
        $r = poolVendorMatch(['name' => 'Some Paper Shop', 'iban' => 'DK12 7632 0002 0138 14'], self::index());
        self::assertSame('bank', $r['match']);
        self::assertSame(16, $r['kontoId']);
    }

    public function testCvrBeatsBankWhenBothPointAtDifferentKreditorer(): void
    {
        $r = poolVendorMatch(['name' => 'x', 'cvr' => '34895058', 'bank_reg' => '0892', 'bank_konto' => '1001348'], self::index());
        self::assertSame('cvr', $r['match']);
        self::assertSame(16, $r['kontoId']);
    }

    public function testNameMatchScoresBySimilarity(): void
    {
        $r = poolVendorMatch(['name' => 'Dan Group Alarm ApS'], self::index());
        self::assertSame('name', $r['match']);
        self::assertSame(418, $r['kontoId']);
        self::assertSame(1.0, $r['score']);
        self::assertNull($r['cvr']);

        $r = poolVendorMatch(['name' => 'Papyrus Supplies'], self::index());
        self::assertSame('name', $r['match']);
        self::assertSame(16, $r['kontoId']);

        $r = poolVendorMatch(['name' => 'Papyrus Suplies A/S'], self::index());
        self::assertSame('name', $r['match'], 'a typo still matches above the threshold');
        self::assertSame(16, $r['kontoId']);
        self::assertGreaterThanOrEqual(0.45, $r['score']);
        self::assertLessThan(1.0, $r['score']);
    }

    public function testNameBelowThresholdIsNone(): void
    {
        $r = poolVendorMatch(['name' => 'Kvickly Odense'], self::index());
        self::assertSame('none', $r['match']);
        self::assertSame(0.0, $r['score']);
        self::assertNull($r['kontoId']);
        self::assertSame([], $r['candidates']);

        $r = poolVendorMatch(['name' => 'Papyrus Suplies A/S'], self::index(), ['nameThreshold' => 0.95]);
        self::assertSame('none', $r['match'], 'the threshold is an option so it can be tuned on test data');
    }

    public function testSameCvrOnSeveralKreditorerIsAmbiguousWithAtMostFiveCandidates(): void
    {
        $r = poolVendorMatch(['name' => 'Nordisk Kontor Aarhus ApS', 'cvr' => '87654321'], self::index());
        self::assertSame('ambiguous', $r['match']);
        self::assertSame(0.0, $r['score']);
        self::assertNull($r['kontoId']);
        self::assertCount(5, $r['candidates']);
        self::assertSame(503, $r['candidates'][0]['kontoId'], 'the likeliest name comes first');
        self::assertSame(['kontoId', 'kontonr', 'firmanavn'], array_keys($r['candidates'][0]));
    }

    public function testEqualNameScoresAreAmbiguous(): void
    {
        $r = poolVendorMatch(['name' => 'Byggemarked A/S'], self::index());
        self::assertSame('ambiguous', $r['match']);
        self::assertSame([600, 601], array_column($r['candidates'], 'kontoId'));
    }

    public function testOwnCvrIsNeverTakenAsTheVendor(): void
    {
        // The buyer's CVR is the tenant's own; a scan that returned it must fall through.
        $r = poolVendorMatch(['name' => 'Dan Group Alarm', 'cvr' => 'DK12345678'], self::index(), ['ownCvr' => '12 34 56 78']);
        self::assertNull($r['cvr']);
        self::assertSame('name', $r['match']);
        self::assertSame(418, $r['kontoId']);
    }

    public function testReceiptWithoutIdentityIsNone(): void
    {
        $r = poolVendorMatch(['name' => null, 'cvr' => null], self::index());
        self::assertSame('none', $r['match']);
        self::assertNull($r['name']);
        self::assertNull($r['cvr']);
        self::assertNull($r['iban']);
    }

    public function testEmptyIndexIsNone(): void
    {
        $r = poolVendorMatch(['name' => 'Dan Group Alarm', 'cvr' => '12345678'], poolVendorBuildIndex([]));
        self::assertSame('none', $r['match']);
    }

    public function testTokenScanFindsTheSameHitAsTheFullScan(): void
    {
        $full = poolVendorMatch(['name' => 'Papyrus Suplies A/S'], self::index(), ['nameScan' => 'full']);
        $tokens = poolVendorMatch(['name' => 'Papyrus Suplies A/S'], self::index(), ['nameScan' => 'tokens']);
        self::assertSame($full, $tokens);

        // A name sharing no whole word is only reachable by the full scan - by design.
        $full = poolVendorMatch(['name' => 'Dangroup Alarm'], self::index(), ['nameScan' => 'full']);
        self::assertSame(418, $full['kontoId']);
    }

    public function testContractShapeMatchesTheSpec(): void
    {
        $r = poolVendorMatch(['name' => 'Dan Group Alarm ApS', 'cvr' => '12345678'], self::index());
        self::assertSame(['name', 'cvr', 'iban', 'kontoId', 'kontonr', 'firmanavn', 'match', 'score', 'candidates'], array_keys($r));
    }

    // ---------------------------------------------------------------
    // docData: rebuilding the contract from a pool_files row (AI-5/AI-6, edge cases)
    // ---------------------------------------------------------------

    public function testRowWithoutVendorMatchIsNull(): void
    {
        self::assertNull(poolVendorFromRow(['vendor_match' => null, 'vendor_name' => 'x'], self::index()));
        self::assertNull(poolVendorFromRow(['vendor_match' => ''], self::index()));
        self::assertFalse(poolVendorNeedsRematch(['vendor_match' => null, 'vendor_cvr' => '12345678'], self::index()));
    }

    public function testRowReadsKontonrAndFirmanavnOffTheLiveIndex(): void
    {
        $row = ['vendor_name' => 'Dan Group Alarm ApS', 'vendor_cvr' => '12345678', 'vendor_iban' => null, 'vendor_konto_id' => '418', 'vendor_match' => 'cvr', 'vendor_score' => '1.000'];
        $v = poolVendorFromRow($row, self::index());
        self::assertSame(418, $v['kontoId']);
        self::assertSame('30120', $v['kontonr']);
        self::assertSame('Dan Group Alarm', $v['firmanavn']);
        self::assertSame(1.0, $v['score']);
        self::assertFalse(poolVendorNeedsRematch($row, self::index()));
    }

    public function testDeletedKreditorIsIgnoredAndTriggersRematch(): void
    {
        $row = ['vendor_name' => 'Gone ApS', 'vendor_cvr' => '11111111', 'vendor_iban' => null, 'vendor_konto_id' => 999, 'vendor_match' => 'cvr', 'vendor_score' => '1.000'];
        $v = poolVendorFromRow($row, self::index());
        self::assertNull($v['kontoId']);
        self::assertSame('none', $v['match']);
        self::assertTrue(poolVendorNeedsRematch($row, self::index()));
    }

    public function testUnmatchedRowWithIdentityIsRematchedOnOpen(): void
    {
        $row = ['vendor_name' => 'Dan Group Alarm ApS', 'vendor_cvr' => '12345678', 'vendor_iban' => null, 'vendor_konto_id' => null, 'vendor_match' => 'none', 'vendor_score' => '0.000'];
        self::assertTrue(poolVendorNeedsRematch($row, self::index()));
        $row['vendor_cvr'] = null;
        $row['vendor_name'] = null;
        self::assertFalse(poolVendorNeedsRematch($row, self::index()), 'nothing to match on');
    }

    public function testAmbiguousRowRebuildsItsCandidates(): void
    {
        $row = ['vendor_name' => 'Nordisk Kontor Aarhus ApS', 'vendor_cvr' => '87654321', 'vendor_iban' => null, 'vendor_konto_id' => null, 'vendor_match' => 'ambiguous', 'vendor_score' => '0.000'];
        $v = poolVendorFromRow($row, self::index());
        self::assertSame('ambiguous', $v['match']);
        self::assertCount(5, $v['candidates']);
    }

    public function testStoredIbanSplitsBackIntoParts(): void
    {
        self::assertSame(['iban' => null, 'bank_reg' => '0892', 'bank_konto' => '1001348'], poolVendorRowFromIban('0892-1001348'));
        self::assertSame(['iban' => 'DK5000400440116243', 'bank_reg' => null, 'bank_konto' => null], poolVendorRowFromIban('DK5000400440116243'));
        self::assertSame(['iban' => null, 'bank_reg' => null, 'bank_konto' => null], poolVendorRowFromIban(''));
    }

    public function testWindows1252BytesInKreditorRowsAreConvertedSoJsonEncodeSucceeds(): void
    {
        $index = poolVendorBuildIndex([
            ['id' => 900, 'kontonr' => '30900', 'firmanavn' => "Gregershus Ejend\xF8mme ApS", 'cvrnr' => '29530068'],
        ]);
        self::assertSame('Gregershus Ejendømme ApS', $index['byId'][900]['firmanavn']);
        $r = poolVendorMatch(['name' => 'Gregershus', 'cvr' => 'DK29530068'], $index);
        self::assertSame('cvr', $r['match']);
        self::assertNotFalse(json_encode($r), 'the match result must always be JSON-encodable');
        self::assertSame('gregershus ejendømme', $index['names'][900]);
    }

    public function testBulkRematchOfTwoHundredFilesStaysCheap(): void
    {
        $kreditorer = self::kreditorer();
        for ($i = 0; $i < 2000; $i++) {
            $kreditorer[] = ['id' => 10000 + $i, 'kontonr' => (string) (40000 + $i), 'firmanavn' => "Leverandør nummer $i Handel ApS", 'cvrnr' => (string) (20000000 + $i)];
        }
        $index = poolVendorBuildIndex($kreditorer);
        $start = microtime(true);
        for ($i = 0; $i < 200; $i++) {
            poolVendorMatch(['name' => 'Leverandør nummer ' . ($i * 7) . ' Handel', 'cvr' => null], $index, ['nameScan' => 'tokens']);
        }
        $elapsed = microtime(true) - $start;
        self::assertLessThan(0.5, $elapsed, "200 token-scan matches against 2000 kreditorer took {$elapsed}s");
    }
}
