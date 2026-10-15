<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/ClassificationEndpointTest.php --- ver 5.0.0 --- 2026-10-07 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created. Characterized classification precedence, persistence, privacy and ownership.
// 20261001 CDX/LH Regress effective historical VAT and evidence-based reviewer feedback.
// 20261007 CDX/LH Cover undated configuration outside the current fiscal year.

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../includes/db_query.php';
require_once __DIR__ . '/../../restapi/services/ClassificationService.php';
require_once __DIR__ . '/../../restapi/core/RequestId.php';

final class ClassificationEndpointTest extends TestCase
{
    private static $tenant;
    private static array $login;
    private static array $headers;
    private static string $subject;
    private static int $year;
    private static int $sequence = 0;
    private int $attachment;
    private string $creditor;
    private int $vendorId;
    private array $requestUri;

    public static function setUpBeforeClass(): void
    {
        if (RestApiEnv::unavailableReason() !== null) {
            return;
        }
        RestApiEnv::bootstrapTenant();
        self::$tenant = RestApiEnv::connect(RestApiEnv::testDb());
        self::$login = RestApiEnv::loginData();
        self::$headers = RestApiEnv::authHeaders();
        $me = self::request('GET', 'auth/me.php');
        self::$subject = $me['json']['data']['subject'];
        $context = new EngineContext(self::$tenant, RestApiEnv::testDb(), self::$login['tenant']['id'], RestApiEnv::user(), self::$login['user']['id'], date('Y-m-d'));
        self::$year = $context->regnaar;
        // Older pool schemas deliberately retain optional matcher fields; provision fixture evidence only.
        foreach (['vendor_name' => 'text', 'vendor_cvr' => 'text', 'vendor_iban' => 'text', 'vendor_konto_id' => 'integer',
            'vendor_match' => 'text', 'vendor_score' => 'numeric'] as $field => $type) {
            self::sql("ALTER TABLE pool_files ADD COLUMN IF NOT EXISTS $field $type");
        }
        self::sql("INSERT INTO kontoplan (kontonr,beskrivelse,kontotype,regnskabsaar,moms,lukket) VALUES
            (92210,'Purchases','D',$1,'K91',''),(92750,'Representation','D',$1,'K92',''),
            (92220,'Travel','S',$1,'',''),(92999,'Closed','D',$1,'K91','on'),(92998,'Header','H',$1,'','')", [self::$year]);
        self::sql("INSERT INTO grupper (art,kode,kodenr,fiscal_year,beskrivelse,box1,box2) VALUES
            ('KM','K',91,$1,'Purchase VAT','92210','25'),('KM','K',92,$1,'Partial VAT','92750','25')", [self::$year]);
        self::sql("INSERT INTO kladdeliste (id,bogfort,kladdenote) VALUES (92080,'V','Posted'),(92081,'-','Open')");
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tenant) {
            pg_close(self::$tenant);
        }
        RestApiEnv::teardownTenant();
    }

    protected function setUp(): void
    {
        if (!self::$tenant) {
            self::markTestSkipped(RestApiEnv::unavailableReason() ?? 'Local PostgreSQL stack required');
        }
        $this->requestUri = [array_key_exists('REQUEST_URI', $_SERVER), ifset($_SERVER, 'REQUEST_URI')];
        $_SERVER['REQUEST_URI'] = '/tests/classification';
        $n = ++self::$sequence;
        $this->creditor = (string)(93000 + $n);
        $this->vendorId = (int)self::sql("INSERT INTO adresser (art,kontonr,firmanavn,cvrnr,lukket) VALUES ('K',$1,$2,$3,'') RETURNING id",
            [$this->creditor, 'Classification supplier ' . $n, (string)(98000000 + $n)])[0]['id'];
        $this->attachment = (int)self::sql("INSERT INTO pool_files (filename,status,uploaded_by,file_date,account,vendor_name,vendor_konto_id,vendor_match,vendor_score)
            VALUES ($1,'ubehandlet',$2,CURRENT_DATE,'92210',$3,$4,'cvr',1) RETURNING id",
            ['classification-' . $n . '.pdf', self::$subject, 'Classification supplier ' . $n, $this->vendorId])[0]['id'];
    }

    protected function tearDown(): void
    {
        if (isset($this->requestUri)) {
            if ($this->requestUri[0]) {
                $_SERVER['REQUEST_URI'] = $this->requestUri[1];
            } else {
                unset($_SERVER['REQUEST_URI']);
            }
        }
    }

    /** @return array<string,array{array}> */
    public static function precedenceCases(): array
    {
        $cases = json_decode(file_get_contents(__DIR__ . '/fixtures/t3/classification/precedence.json'), true, 512, JSON_THROW_ON_ERROR);
        $result = [];
        foreach ($cases as $case) {
            $result[$case['name']] = [$case];
        }
        return $result;
    }

    public function testUndatedSuggestionAndFeedbackUseConfiguration(): void
    {
        self::sql("UPDATE pool_files SET file_date='' WHERE id=$1", [$this->attachment]);
        $years = self::sql("SELECT id,box1,box2,box3,box4 FROM grupper WHERE art='RA'");
        self::sql("UPDATE grupper SET box1='1',box2='2000',box3='12',box4='2000' WHERE art='RA'");
        try {
            $suggest = $this->suggest();
            self::assertSame(200,$suggest['status'],$suggest['body']);
            $body = ['client_id'=>self::uuid(),'attachment_id'=>$this->attachment,'kontonr'=>92210,'vat_code'=>'K91'];
            $feedback = self::request('POST','classification/feedback.php',$body);
            self::assertSame(201,$feedback['status'],$feedback['body']);
            self::assertSame($feedback['json'],self::request('POST','classification/feedback.php',$body)['json']);
            self::sql("UPDATE pool_files SET file_date='2026-10-07' WHERE id=$1", [$this->attachment]);
            $dated = $this->suggest();
            self::assertSame(422,$dated['status'],$dated['body']);
            self::assertSame('outside_fiscal_year',$dated['json']['data']['details']['reason']);
            $invalid = self::request('GET','classification/suggest.php?vendor_name=Test&date=2026-02-30');
            self::assertSame(422,$invalid['status'],$invalid['body']);
            self::assertSame('invalid_date',$invalid['json']['data']['details']['reason']);
        } finally {
            foreach ($years as $year) {
                self::sql('UPDATE grupper SET box1=$1,box2=$2,box3=$3,box4=$4 WHERE id=$5',
                    [$year['box1'],$year['box2'],$year['box3'],$year['box4'],$year['id']]);
            }
        }
    }

    #[DataProvider('precedenceCases')]
    public function testPrecedenceTable(array $case): void
    {
        $decision = classification_decide($case['inputs']);
        self::assertSame($case['expected']['basis'], $decision['basis']);
        self::assertSame($case['expected']['kontonr'], $decision['kontonr']);
        self::assertSame($decision, classification_decide($case['inputs']));
    }

    public function testRankBreaksTiesByCountThenLatestThenLowestAccountAndVat(): void
    {
        $inputs = self::precedenceCases()['history beats OCR'][0]['inputs'];
        $rows = [
            ['kontonr' => 2750, 'vat' => 'K2', 'transdate' => '2026-09-01'],
            ['kontonr' => 2210, 'vat' => 'K1', 'transdate' => '2026-09-01'],
            ['kontonr' => 2220, 'vat' => null, 'transdate' => '2026-09-02'],
        ];
        $ranked = classification_rank($rows, $inputs['accounts'], $inputs['vat_codes']);
        self::assertSame([2220, 2210, 2750], array_column($ranked, 'kontonr'));
        $rows[] = $rows[1];
        self::assertSame(2210, classification_rank($rows, $inputs['accounts'], $inputs['vat_codes'])[0]['kontonr']);
        self::assertSame($ranked, classification_rank(array_reverse(array_slice($rows, 0, 3)), $inputs['accounts'], $inputs['vat_codes']));
        $mixed = array_merge(array_fill(0, 3, ['kontonr' => 2210, 'vat' => 'K1', 'transdate' => '2026-09-01']),
            array_fill(0, 3, ['kontonr' => 2210, 'vat' => 'K2', 'transdate' => '2026-09-02']),
            array_fill(0, 5, ['kontonr' => 2750, 'vat' => 'K2', 'transdate' => '2026-09-03']));
        $ranked = classification_rank($mixed, $inputs['accounts'], $inputs['vat_codes']);
        self::assertSame([2210, 2750], array_column($ranked, 'kontonr'));
        self::assertSame(6, $ranked[0]['count']);
        self::assertSame('K2', $ranked[0]['vat_code']);
        $inputs['history'] = array_reverse($ranked);
        self::assertSame(2210, classification_decide($inputs)['kontonr']);
    }

    public function testOcrResponseMatchesFixture(): void
    {
        $case = self::precedenceCases()['single historical line falls back to OCR'][0];
        $case['inputs']['history'] = [];
        $fixture = json_decode(file_get_contents(__DIR__ . '/fixtures/t3/classification/ocr-response.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($fixture['data'], classification_decide($case['inputs']));
    }

    public function testVendorKeysUseCertainCreditorMatchesAndKeepLongNamesDistinct(): void
    {
        $index = poolVendorBuildIndex(self::sql('SELECT * FROM adresser WHERE id=$1', [$this->vendorId]));
        $row = ['vendor_konto_id' => $this->vendorId, 'vendor_name' => 'Seller', 'vendor_score' => 0.70, 'vendor_match' => 'name'];
        self::assertSame('N:seller', classification_vendor_key($row, $index));
        $row['vendor_score'] = 0.90;
        self::assertSame('K:' . $this->creditor, classification_vendor_key($row, $index));
        foreach (['cvr', 'bank'] as $match) {
            $row['vendor_match'] = $match;
            self::assertSame('K:' . $this->creditor, classification_vendor_key($row, $index));
        }
        $first = classification_vendor_key(['subject' => str_repeat('seller ', 25) . 'one'], $index);
        $second = classification_vendor_key(['subject' => str_repeat('seller ', 25) . 'two'], $index);
        self::assertNotSame($first, $second);
        self::assertLessThanOrEqual(120, mb_strlen($first));
    }

    public function testStoredSuggestionIsDeterministicAndHistoryIsPostedOnlyAndPrivate(): void
    {
        $this->history(92210, 3, 92080, 'K91');
        $this->history(92750, 2, 92080, 'K92');
        $this->history(92750, 10, 92081, 'K92');
        $this->history(92750, 20, 92080, 'K92', "CURRENT_DATE - interval '25 months'");
        $first = $this->suggest();
        self::assertSame(200, $first['status'], $first['body']);
        $data = $first['json']['data'];
        self::assertSame('supplier_history', $data['basis']);
        self::assertSame(92210, $data['kontonr']);
        self::assertSame('K91', $data['vat_code']);
        self::assertSame(['count' => 3, 'total' => 5, 'last_used' => date('Y-m-d'), 'corrected_on' => null], $data['basis_detail']);
        self::assertSame($first['json'], $this->suggest()['json']);
        self::assertSame('1', self::sql('SELECT count(*) AS n FROM classification_suggestions WHERE pool_file_id=$1', [$this->attachment])[0]['n']);
        self::assertStringNotContainsString('PRIVATE VOUCHER TEXT', $first['body']);
        foreach (['amount', 'transdate', 'beskrivelse', 'bilag', 'text', 'kontoart'] as $key) {
            self::assertStringNotContainsString('"' . $key . '"', $first['body']);
        }
        $stored = self::sql('SELECT * FROM classification_suggestions WHERE id=$1', [$data['suggestion_id']])[0];
        self::assertSame((string)$data['kontonr'], $stored['kontonr']);
        self::assertSame('K:' . $this->creditor, $stored['vendor_key']);
        self::assertSame(self::$subject, $stored['requested_by']);
    }

    public function testClosedOcrFallsBackToUnselectedCommonAccounts(): void
    {
        $this->history(92210, 2, 92080, 'K91');
        self::sql("UPDATE pool_files SET account='92999',vendor_match='none',vendor_konto_id=NULL,vendor_name='Unmatched seller' WHERE id=$1", [$this->attachment]);
        $reply = $this->suggest();
        self::assertSame(200, $reply['status'], $reply['body']);
        self::assertSame('common_accounts', $reply['json']['data']['basis']);
        self::assertNull($reply['json']['data']['kontonr']);
        self::assertNotEmpty($reply['json']['data']['alternatives']);
        self::assertLessThanOrEqual(5, count($reply['json']['data']['alternatives']));
    }

    public function testChangedEvidenceAppendsSuggestionAndKeepsTheOriginal(): void
    {
        $first = $this->suggest()['json']['data'];
        self::sql("UPDATE pool_files SET account='92750' WHERE id=$1", [$this->attachment]);
        $second = $this->suggest()['json']['data'];
        self::assertNotSame($first['suggestion_id'], $second['suggestion_id']);
        self::assertSame(92750, $second['kontonr']);
        self::assertSame('92210', self::sql('SELECT kontonr FROM classification_suggestions WHERE id=$1', [$first['suggestion_id']])[0]['kontonr']);
        self::sql("UPDATE pool_files SET account='92210' WHERE id=$1", [$this->attachment]);
        self::assertSame($first, $this->suggest()['json']['data']);
        self::assertSame('2', self::sql('SELECT count(*) AS n FROM classification_suggestions WHERE pool_file_id=$1', [$this->attachment])[0]['n']);
    }

    public function testFeedbackWithoutSuggestionIdUsesServedProposalAndStoresConfirmationSeparately(): void
    {
        $suggestion = $this->suggest()['json']['data'];
        $reply = self::request('POST', 'classification/feedback.php', ['client_id' => self::uuid(), 'attachment_id' => $this->attachment,
            'kontonr' => 92210, 'vat_code' => 'K91', 'kreditor_kontonr' => $this->creditor]);
        self::assertSame(201, $reply['status'], $reply['body']);
        self::assertSame('accepted', $reply['json']['data']['outcome']);
        $rows = self::sql('SELECT f.role,f.outcome,s.pool_file_id,s.id FROM classification_feedback f
            JOIN classification_suggestions s ON s.id=f.suggestion_id WHERE f.id=$1', [$reply['json']['data']['feedback_id']]);
        self::assertSame('uploader', $rows[0]['role']);
        self::assertSame((string)$this->attachment, $rows[0]['pool_file_id']);
        self::assertSame((string)$suggestion['suggestion_id'], $rows[0]['id']);
    }

    public function testVendorFormDoesNotStoreSuggestions(): void
    {
        $this->history(92210, 2, 92080, 'K91');
        $cvr = self::sql('SELECT cvrnr FROM adresser WHERE id=$1', [$this->vendorId])[0]['cvrnr'];
        $before = self::sql('SELECT count(*) AS n FROM classification_suggestions')[0]['n'];
        $reply = self::request('GET', 'classification/suggest.php?vendor_cvr=' . $cvr . '&date=' . date('Y-m-d'));
        self::assertSame(200, $reply['status'], $reply['body']);
        self::assertSame('supplier_history', $reply['json']['data']['basis']);
        self::assertNull($reply['json']['data']['suggestion_id']);
        self::assertSame($before, self::sql('SELECT count(*) AS n FROM classification_suggestions')[0]['n']);
    }

    public function testUploaderFeedbackReplaysAndDoesNotTeachReviewerCorrections(): void
    {
        $suggestion = $this->suggest()['json']['data'];
        $body = ['client_id' => self::uuid(), 'attachment_id' => $this->attachment, 'suggestion_id' => $suggestion['suggestion_id'],
            'kontonr' => 92750, 'vat_code' => 'K92', 'kreditor_kontonr' => $this->creditor];
        $first = self::request('POST', 'classification/feedback.php', $body);
        self::assertSame(201, $first['status'], $first['body']);
        self::assertSame('corrected', $first['json']['data']['outcome']);
        $retry = self::requestWithHeaders('POST', 'classification/feedback.php', $body);
        self::assertSame($first['json'], $retry['json']);
        self::assertSame('true', $retry['headers']['idempotent-replayed']);
        $body['kontonr'] = 92210;
        $reuse = self::request('POST', 'classification/feedback.php', $body);
        self::assertSame(409, $reuse['status'], $reuse['body']);
        self::assertSame('client_id_reused', $reuse['json']['data']['details']['reason']);
        self::assertSame('1', self::sql('SELECT count(*) AS n FROM classification_feedback WHERE pool_file_id=$1', [$this->attachment])[0]['n']);
        self::assertSame('ocr', $this->suggest()['json']['data']['basis']);
    }

    /** @return array<string,array{?string,bool,string}> */
    public static function historicalVatCases(): array
    {
        $cases = [];
        foreach (['null' => null, 'blank' => '', 'whitespace' => '  ', 'override' => 'K92'] as $name => $vat) {
            foreach ([false, true] as $exempt) {
                foreach (['D', 'K'] as $side) {
                    $cases[$name . ($exempt ? ' exempt ' : ' taxable ') . $side] = [$vat, $exempt, $side];
                }
            }
        }
        return $cases;
    }

    #[DataProvider('historicalVatCases')]
    public function testHistoryAndCommonAccountsResolveVatBeforeRanking(?string $override, bool $exempt, string $side): void
    {
        $account = 95000 + self::$sequence;
        self::sql("INSERT INTO kontoplan (kontonr,beskrivelse,kontotype,regnskabsaar,moms,lukket)
            VALUES ($1,'VAT regression','D',$2,'K91','')", [$account, self::$year]);
        for ($i = 0; $i < 2; $i++) {
            self::sql("INSERT INTO kassekladde (kladde_id,bilag,transdate,debet,d_type,kredit,k_type,amount,beskrivelse,debetvat,kreditvat,momsfri)
                VALUES (92080,9812,CURRENT_DATE,$1,$2,$3,$4,125,'PRIVATE VAT EVIDENCE',$5,$6,$7)",
                [$side === 'D' ? $account : $this->creditor, $side === 'D' ? 'F' : 'K',
                    $side === 'D' ? $this->creditor : $account, $side === 'D' ? 'K' : 'F',
                    $side === 'D' ? $override : null, $side === 'K' ? $override : null, $exempt ? 'on' : null]);
        }
        $expected = $exempt ? null : (trim((string)$override) ?: 'K91');
        $reply = $this->suggest();
        self::assertSame(200, $reply['status'], $reply['body']);
        self::assertSame('supplier_history', $reply['json']['data']['basis']);
        self::assertSame($account, $reply['json']['data']['kontonr']);
        self::assertSame($expected, $reply['json']['data']['vat_code']);
        $loaded = LegacyEngine::run($this->context(), fn(): array => classification_inputs(
            self::sql('SELECT * FROM pool_files WHERE id=$1', [$this->attachment])[0], self::$year));
        $common = array_values(array_filter($loaded['inputs']['common'], static fn(array $row): bool => $row['kontonr'] === $account));
        self::assertCount(1, $common);
        self::assertSame($expected, $common[0]['vat_code']);
        self::assertSame(2, $common[0]['count']);
    }

    public function testRankCombinesDefaultVatEvidenceBeforeChoosingTheMostUsedVariant(): void
    {
        $rows = [];
        foreach ([null, '', ' ', 'K91', 'K92', 'K92', 'K92'] as $vat) {
            $rows[] = ['kontonr' => 92210, 'vat' => $vat, 'momsfri' => '', 'transdate' => '2026-09-01'];
        }
        $ranked = classification_rank($rows, [92210 => ['account_name' => 'Purchases', 'vat_code' => 'K91']],
            ['K91' => 'Purchase VAT', 'K92' => 'Partial VAT']);
        self::assertSame('K91', $ranked[0]['vat_code']);
        self::assertSame(7, $ranked[0]['count']);
    }

    public function testReviewerFeedbackWithoutIdUsesApplicableEvidenceAfterABA(): void
    {
        $first = $this->suggest()['json']['data'];
        self::sql("UPDATE pool_files SET account='92750' WHERE id=$1", [$this->attachment]);
        $second = $this->suggest()['json']['data'];
        self::assertGreaterThan($first['suggestion_id'], $second['suggestion_id']);
        self::sql("UPDATE pool_files SET account='92210' WHERE id=$1", [$this->attachment]);
        self::assertSame($first, $this->suggest()['json']['data']);
        $before = self::request('GET', 'classification/stats.php')['json']['data'];
        self::sql('BEGIN');
        try {
            $feedback = LegacyEngine::run($this->context(), fn(): array => classification_record_feedback($this->attachment,
                ['client_id' => self::uuid(), 'kontonr' => $first['kontonr'], 'vat_code' => $first['vat_code'],
                    'kreditor_kontonr' => $first['kreditor']['kontonr']], self::$year, self::$subject));
            self::sql('COMMIT');
        } finally {
            if (pg_transaction_status(self::$tenant) !== PGSQL_TRANSACTION_IDLE) {
                self::sql('ROLLBACK');
            }
        }
        self::assertSame('accepted', $feedback['outcome']);
        $stored = self::sql('SELECT suggestion_id FROM classification_feedback WHERE id=$1', [$feedback['feedback_id']])[0];
        self::assertSame((string)$first['suggestion_id'], $stored['suggestion_id']);
        $after = self::request('GET', 'classification/stats.php')['json']['data'];
        self::assertSame($before['accepted'] + 1, $after['accepted']);
        self::assertSame($before['corrected'], $after['corrected']);
        self::assertSame($before['vendors_learned'], $after['vendors_learned']);
        self::assertSame('ocr', $this->suggest()['json']['data']['basis']);
    }

    public function testReviewerFeedbackWithNoApplicableProposalRecordsNone(): void
    {
        $this->suggest();
        self::sql("UPDATE pool_files SET account='92750' WHERE id=$1", [$this->attachment]);
        self::sql('BEGIN');
        try {
            $feedback = LegacyEngine::run($this->context(), fn(): array => classification_record_feedback($this->attachment,
                ['client_id' => self::uuid(), 'kontonr' => 92750, 'vat_code' => 'K92',
                    'kreditor_kontonr' => $this->creditor], self::$year, self::$subject));
            self::sql('COMMIT');
        } finally {
            if (pg_transaction_status(self::$tenant) !== PGSQL_TRANSACTION_IDLE) {
                self::sql('ROLLBACK');
            }
        }
        self::assertSame('none', $feedback['outcome']);
        self::assertNull(self::sql('SELECT suggestion_id FROM classification_feedback WHERE id=$1', [$feedback['feedback_id']])[0]['suggestion_id']);
    }

    #[DataProvider('approvalProposalCases')]
    public function testApprovalPreservesProposalResolvedBeforeChangingInputs(bool $served): void
    {
        if (!$served) {
            self::sql("UPDATE pool_files SET account='92750' WHERE id=$1", [$this->attachment]);
            $this->suggest();
            self::sql("UPDATE pool_files SET account='92210' WHERE id=$1", [$this->attachment]);
        }
        $first = $served ? $this->suggest()['json']['data'] : null;
        self::sql('BEGIN');
        try {
            $feedback = LegacyEngine::run($this->context(), function () use ($first, $served): array {
                $row = self::sql('SELECT * FROM pool_files WHERE id=$1 FOR UPDATE', [$this->attachment])[0];
                $suggestionId = classification_applicable_suggestion_id($this->attachment, classification_inputs($row, self::$year));
                self::assertSame($served ? $first['suggestion_id'] : null, $suggestionId);
                self::sql("UPDATE pool_files SET account='92750' WHERE id=$1", [$this->attachment]);
                return classification_record_feedback($this->attachment,
                    ['client_id' => self::uuid(), 'suggestion_id' => $suggestionId, 'kontonr' => 92750,
                        'vat_code' => 'K92', 'kreditor_kontonr' => $this->creditor], self::$year, self::$subject);
            });
            self::sql('COMMIT');
        } finally {
            if (pg_transaction_status(self::$tenant) !== PGSQL_TRANSACTION_IDLE) {
                self::sql('ROLLBACK');
            }
        }
        self::assertSame($served ? 'corrected' : 'none', $feedback['outcome']);
        self::assertSame($served ? (string)$first['suggestion_id'] : null,
            self::sql('SELECT suggestion_id FROM classification_feedback WHERE id=$1', [$feedback['feedback_id']])[0]['suggestion_id']);
    }

    /** @return array<string,array{bool}> */
    public static function approvalProposalCases(): array
    {
        return ['served proposal' => [true], 'no proposal served' => [false]];
    }

    public function testUploaderFeedbackWithoutServedProposalRecordsNone(): void
    {
        $reply = self::request('POST', 'classification/feedback.php', ['client_id' => self::uuid(),
            'attachment_id' => $this->attachment, 'kontonr' => 92210, 'vat_code' => 'K91', 'kreditor_kontonr' => $this->creditor]);
        self::assertSame(201, $reply['status'], $reply['body']);
        self::assertSame('none', $reply['json']['data']['outcome']);
        self::assertSame('0', self::sql('SELECT count(*) AS n FROM classification_suggestions WHERE pool_file_id=$1', [$this->attachment])[0]['n']);
    }

    public function testReviewerCorrectionWinsAndAppendOnlyTablesRejectEveryMutation(): void
    {
        $suggestion = $this->suggest()['json']['data'];
        $context = $this->context();
        self::sql('BEGIN');
        $feedback = LegacyEngine::run($context, fn(): array => classification_record_feedback($this->attachment,
            ['client_id' => self::uuid(), 'suggestion_id' => $suggestion['suggestion_id'], 'kontonr' => 92750,
                'vat_code' => 'K92', 'kreditor_kontonr' => $this->creditor], self::$year, self::$subject));
        self::sql('COMMIT');
        self::assertSame('corrected', $feedback['outcome']);
        $this->history(92210, 5, 92080, 'K91');
        $reply = $this->suggest();
        self::assertSame('last_correction', $reply['json']['data']['basis']);
        self::assertSame(92750, $reply['json']['data']['kontonr']);
        self::assertSame('K92', $reply['json']['data']['vat_code']);
        self::assertSame($reply['json'], $this->suggest()['json']);
        foreach (['classification_feedback', 'classification_suggestions'] as $table) {
            foreach (["UPDATE $table SET id=id", "DELETE FROM $table", "TRUNCATE $table CASCADE"] as $sql) {
                self::sql('BEGIN');
                try {
                    self::assertTrue(pg_send_query(self::$tenant, $sql));
                    $result = pg_get_result(self::$tenant);
                    self::assertSame('55000', pg_result_error_field($result, PGSQL_DIAG_SQLSTATE));
                } finally {
                    self::sql('ROLLBACK');
                }
            }
        }
    }

    public function testReviewerFeedbackRollsBackWithApprovalTransaction(): void
    {
        $suggestion = $this->suggest()['json']['data'];
        self::sql('BEGIN');
        LegacyEngine::run($this->context(), fn(): array => classification_record_feedback($this->attachment,
            ['client_id' => self::uuid(), 'suggestion_id' => $suggestion['suggestion_id'], 'kontonr' => 92750], self::$year, self::$subject));
        self::sql('ROLLBACK');
        self::assertSame('0', self::sql('SELECT count(*) AS n FROM classification_feedback WHERE pool_file_id=$1', [$this->attachment])[0]['n']);
        self::assertSame('ocr', $this->suggest()['json']['data']['basis']);
    }

    public function testFeedbackValidatesAccountVatCreditorAndSuggestionScope(): void
    {
        $suggestion = $this->suggest()['json']['data'];
        $body = ['attachment_id' => $this->attachment, 'suggestion_id' => $suggestion['suggestion_id'], 'kontonr' => 92210];
        foreach ([['kontonr' => 92999], ['kontonr' => 92998], ['kontonr' => '92210'], ['vat_code' => 'BAD'], ['kreditor_kontonr' => '99999999'],
            ['suggestion_id' => 2147483647], ['role' => 'reviewer']] as $invalid) {
            $reply = self::request('POST', 'classification/feedback.php', array_replace($body, $invalid, ['client_id' => self::uuid()]));
            self::assertSame(422, $reply['status'], $reply['body']);
            self::assertSame('validation_failed', $reply['json']['data']['error']);
        }
        self::assertSame('0', self::sql('SELECT count(*) AS n FROM classification_feedback WHERE pool_file_id=$1', [$this->attachment])[0]['n']);
    }

    public function testReceiptUserCanReadOwnSuggestionsButCannotReadOthersOrStats(): void
    {
        $username = 'classification_receipt_' . self::$sequence;
        $password = TestCredentials::password($username);
        self::sql("INSERT INTO brugere (brugernavn,kode,rettigheder,status) VALUES ($1,$2,$3,true)", [$username, md5($password), str_repeat('0', 16)]);
        $login = RestApiEnv::login($username, $password, RestApiEnv::accountOpen());
        $headers = ['Authorization: Bearer ' . $login['json']['data']['access_token']];
        $other = self::request('GET', 'classification/suggest.php?attachment_id=' . $this->attachment, null, $headers);
        self::assertSame(404, $other['status'], $other['body']);
        $me = self::request('GET', 'auth/me.php', null, $headers);
        self::sql('UPDATE pool_files SET uploaded_by=$1 WHERE id=$2', [$me['json']['data']['subject'], $this->attachment]);
        self::assertSame(200, self::request('GET', 'classification/suggest.php?attachment_id=' . $this->attachment, null, $headers)['status']);
        self::assertSame(403, self::request('GET', 'classification/stats.php', null, $headers)['status']);
        $foreignFeedback = self::request('POST', 'classification/feedback.php', ['client_id' => self::uuid(), 'attachment_id' => $this->attachment, 'kontonr' => 92210]);
        self::assertSame(404, $foreignFeedback['status'], $foreignFeedback['body']);
    }

    public function testStatsCountsOnlyRecentFinalDecisions(): void
    {
        $before = self::request('GET', 'classification/stats.php')['json']['data'];
        foreach ([['reviewer', 'accepted', '1 day'], ['reviewer', 'corrected', '1 day'],
            ['reviewer', 'corrected', '91 days'], ['uploader', 'corrected', '1 day'], ['reviewer', 'none', '1 day']] as [$role, $outcome, $age]) {
            self::sql("INSERT INTO classification_feedback (pool_file_id,vendor_key,role,outcome,kontonr,actor,client_id,created_at)
                VALUES ($1,$2,$3,$4,92210,$5,$6,now()-$7::interval)", [$this->attachment, 'K:' . $this->creditor, $role, $outcome, self::$subject, self::uuid(), $age]);
        }
        $after = self::request('GET', 'classification/stats.php');
        self::assertSame(200, $after['status'], $after['body']);
        self::assertSame(['window_days' => 90, 'decisions' => $before['decisions'] + 2, 'accepted' => $before['accepted'] + 1,
            'corrected' => $before['corrected'] + 1, 'vendors_learned' => $before['vendors_learned'] + 1], $after['json']['data']);
    }

    public function testMissingMalformedDeletedAndProcessedAttachments(): void
    {
        self::assertSame(422, self::request('GET', 'classification/suggest.php?attachment_id[]=1')['status']);
        self::assertSame(404, self::request('GET', 'classification/suggest.php?attachment_id=2147483647')['status']);
        self::assertSame(422, self::request('GET', 'classification/suggest.php?vendor_name=Seller&date=invalid')['status']);
        self::sql("UPDATE pool_files SET status='attached' WHERE id=$1", [$this->attachment]);
        self::assertSame(409, self::request('POST', 'classification/feedback.php', ['client_id' => self::uuid(), 'attachment_id' => $this->attachment, 'kontonr' => 92210])['status']);
        self::sql("UPDATE pool_files SET status='deleted' WHERE id=$1", [$this->attachment]);
        self::assertSame(410, $this->suggest()['status']);
        foreach (['suggest', 'stats', 'feedback'] as $path) {
            $reply = RestApiEnv::http($path === 'feedback' ? 'POST' : 'GET', '/restapi/endpoints/v1/classification/' . $path . '.php', $path === 'feedback' ? [] : null);
            self::assertSame(401, $reply['status'], $reply['body']);
        }
    }

    private function context(): EngineContext
    {
        return new EngineContext(self::$tenant, RestApiEnv::testDb(), self::$login['tenant']['id'], RestApiEnv::user(), self::$login['user']['id'], date('Y-m-d'));
    }

    private function suggest(): array
    {
        return self::request('GET', 'classification/suggest.php?attachment_id=' . $this->attachment);
    }

    private function history(int $account, int $count, int $journal, string $vat, string $date = 'CURRENT_DATE'): void
    {
        for ($i = 0; $i < $count; $i++) {
            self::sql("INSERT INTO kassekladde (kladde_id,bilag,transdate,debet,d_type,kredit,k_type,amount,beskrivelse,debetvat)
                VALUES ($1,9812,$date,$2,'F',$3,'K',123456.78,'PRIVATE VOUCHER TEXT',$4)", [$journal, $account, $this->creditor, $vat]);
        }
    }

    private static function request(string $method, string $path, ?array $body = null, ?array $headers = null): array
    {
        return RestApiEnv::http($method, '/restapi/endpoints/v1/' . $path, $body, $headers ?? self::$headers);
    }

    /** @return array{status:int,json:array,headers:array<string,string>} */
    private static function requestWithHeaders(string $method, string $path, array $body): array
    {
        $headers = [];
        $curl = curl_init(RestApiEnv::baseUrl() . '/restapi/endpoints/v1/' . $path);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => array_merge(self::$headers, ['Content-Type: application/json']),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                if (str_contains($line, ':')) {
                    [$key, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($key))] = trim($value);
                }
                return strlen($line);
            }]);
        $raw = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return ['status' => $status, 'json' => json_decode($raw, true, 512, JSON_THROW_ON_ERROR), 'headers' => $headers];
    }

    private static function sql(string $sql, array $params = []): array
    {
        return RestApiEnv::rows(self::$tenant, $sql, $params);
    }

    private static function uuid(): string
    {
        return RequestId::resolve(null);
    }
}
