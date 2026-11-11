<?php
// 20261110 CDX/ASR Verify CRM document claims, rejected replacement links and transaction-local order identity.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DocumentLinking extends TestCase
{
    private ?CrmLeadFixture $fixture = null;

    protected function setUp(): void
    {
        $dsn = (string) getenv('SALDI_CHAR_PG_DSN');
        if ($dsn === '') {
            self::markTestSkipped('Set SALDI_CHAR_PG_DSN to the disposable PostgreSQL fixture database.');
        }
        self::assertTrue(extension_loaded('pgsql'), 'The configured fixture requires the pgsql extension.');
        require_once __DIR__ . '/../support/CrmLeadFixture.php';
        $this->fixture = new CrmLeadFixture($dsn);
        $this->fixture->query("INSERT INTO crm_deals (id, konto_id, title, state) VALUES (31, 2, $1, 'open')", ["Øster's case"]);
        $this->fixture->query("INSERT INTO ordrer (id, konto_id, art, ordrenr, status) VALUES
            (41, 2, 'DO', 701, 0), (42, 2, 'DO', 702, 1)");
    }

    protected function tearDown(): void
    {
        if ($this->fixture !== null) {
            $this->fixture->close();
        }
    }

    /** @return array<string, mixed> */
    private function link(int $customer = 2, int $deal = 31, int $order = 41): array
    {
        return $this->fixture->action(static fn(): array => crm_link_document($customer, $deal, $order, 11));
    }

    /** @return list<array<string, string|null>> */
    private function history(): array
    {
        return $this->fixture->rows('SELECT * FROM historik ORDER BY id');
    }

    /** @return array<string, array{0: int, 1: string}> */
    public static function documents(): array
    {
        return ['quotation' => [41, 'Tilbud 701'], 'order' => [42, 'Ordre 702']];
    }

    #[DataProvider('documents')]
    public function testLinksTheRequestedDocumentAndRecordsItsNumberAndAuthor(int $order, string $label): void
    {
        $result = $this->link(order: $order);
        self::assertArrayNotHasKey('error', $result);
        self::assertSame(31, $result['id']);
        self::assertGreaterThan(0, $result['hid']);
        self::assertSame((string) $order, $this->fixture->rows('SELECT ordre_id FROM crm_deals WHERE id = 31')[0]['ordre_id']);
        $history = $this->history();
        self::assertCount(1, $history);
        self::assertSame((string) $result['hid'], $history[0]['id']);
        self::assertSame('2', $history[0]['konto_id']);
        self::assertSame('11', $history[0]['ansat_id']);
        self::assertSame('11', $history[0]['author_id']);
        self::assertSame('deal', $history[0]['type']);
        self::assertSame("Sag »Øster's case«: " . $label, $history[0]['notat']);
    }

    public function testRetryAndCompetingDocumentCannotReplaceTheFirstLinkOrAppendHistory(): void
    {
        self::assertArrayNotHasKey('error', $this->link());
        $deals = $this->fixture->rows('SELECT * FROM crm_deals ORDER BY id');
        $history = $this->history();
        foreach ([41, 42] as $order) {
            self::assertSame('linked', $this->link(order: $order)['error'] ?? null);
            self::assertSame($deals, $this->fixture->rows('SELECT * FROM crm_deals ORDER BY id'));
            self::assertSame($history, $this->history());
        }
    }

    /** @return array<string, array{0: string}> */
    public static function invalidDocumentsAndCases(): array
    {
        return [
            'document belongs to another customer' => ["UPDATE ordrer SET konto_id = 3 WHERE id = 41"],
            'case belongs to another customer' => ["UPDATE crm_deals SET konto_id = 3 WHERE id = 31"],
            'purchase order' => ["UPDATE ordrer SET art = 'KO' WHERE id = 41"],
            'credit note' => ["UPDATE ordrer SET art = 'DK' WHERE id = 41"],
            'won case' => ["UPDATE crm_deals SET state = 'won' WHERE id = 31"],
            'lost case' => ["UPDATE crm_deals SET state = 'lost' WHERE id = 31"],
            'deleted case' => ["UPDATE crm_deals SET state = 'deleted' WHERE id = 31"],
            'missing document' => ['DELETE FROM ordrer WHERE id = 41'],
            'missing case' => ['DELETE FROM crm_deals WHERE id = 31'],
        ];
    }

    #[DataProvider('invalidDocumentsAndCases')]
    public function testInvalidDocumentOrCaseLeavesLinksAndHistoryUntouched(string $change): void
    {
        $this->fixture->query($change);
        $deals = $this->fixture->rows('SELECT * FROM crm_deals ORDER BY id');
        self::assertSame('fields', $this->link()['error'] ?? null);
        self::assertSame($deals, $this->fixture->rows('SELECT * FROM crm_deals ORDER BY id'));
        self::assertSame([], $this->history());
    }

    public function testCallerCanRollBackTheLinkAndHistoryTogether(): void
    {
        $deals = $this->fixture->rows('SELECT * FROM crm_deals ORDER BY id');
        $this->fixture->query('BEGIN');
        $result = crm_link_document(2, 31, 41, 11);
        self::assertArrayNotHasKey('error', $result);
        self::assertSame(PGSQL_TRANSACTION_INTRANS, pg_transaction_status($this->fixture->connection));
        self::assertSame('41', $this->fixture->rows('SELECT ordre_id FROM crm_deals WHERE id = 31')[0]['ordre_id']);
        self::assertCount(1, $this->history());
        $this->fixture->query('ROLLBACK');
        self::assertSame($deals, $this->fixture->rows('SELECT * FROM crm_deals ORDER BY id'));
        self::assertSame([], $this->history());
    }

    public function testReadOnlyCrmCannotLinkAnExistingOrder(): void
    {
        $GLOBALS['rettigheder'][6] = '2';
        $deals = $this->fixture->rows('SELECT * FROM crm_deals ORDER BY id');
        self::assertSame('noaccess', $this->link()['error'] ?? null);
        self::assertSame($deals, $this->fixture->rows('SELECT * FROM crm_deals ORDER BY id'));
        self::assertSame([], $this->history());
    }

    public function testCrmWriterCanLinkAnExistingOrderWithoutOrderWritePermission(): void
    {
        $GLOBALS['rettigheder'][5] = '2';
        $result = $this->fixture->action(static fn(): array => crm_action('link_doc', ['cid' => 2, 'deal' => 31, 'doc' => 42]));
        self::assertArrayNotHasKey('error', $result);
        self::assertSame('42', $this->fixture->rows('SELECT ordre_id FROM crm_deals WHERE id = 31')[0]['ordre_id']);
        self::assertCount(1, $this->history());
    }

    public function testOrderIdentitySurvivesAnotherTableInsertAndANewerCustomerOrder(): void
    {
        $inserted = $this->fixture->rows("INSERT INTO ordrer (konto_id, art, ordrenr, status)
            VALUES (2, 'DO', 703, 0) RETURNING id")[0]['id'];
        $this->fixture->query("SELECT setval(pg_get_serial_sequence('historik', 'id'), 500, false)");
        $this->fixture->query("INSERT INTO historik (konto_id, type, notat) VALUES (2, 'note', 'Another sequence advanced')");
        // An explicit later id models an order inserted by another connection without advancing this connection's sequence.
        $this->fixture->query("INSERT INTO ordrer (id, konto_id, art, ordrenr, status) VALUES (999, 2, 'DO', 704, 0)");
        self::assertSame((int) $inserted, crm_last_id('ordrer', 2));
        self::assertSame(500, crm_last_id('historik', 2));
    }
}
