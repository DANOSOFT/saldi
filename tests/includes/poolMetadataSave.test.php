<?php
// 20261124 CL/LH Created: the pool row's green save refused Marian's edit with 422 (ssl3 /master, bilag 784).
//                The row editor posts the stored account back, and a stored app or AI suggestion that is not in this year's kontoplan was checked like a typed one.
//                Only a changed account is checked now.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * poolMetadataSave() with the exact request the pool row editor sends, against an in-memory pool_files row.
 * Year 3: 7200 (D) is open. 9310 is not in the kontoplan: the Saldi app stores its own account suggestion unchecked.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class poolMetadataSave extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pool_test_kontoplan'] = ['3:7200' => true];
        $GLOBALS['pool_test_row'] = [
            'id' => '41', 'filename' => 'voucher-1795.pdf', 'subject' => 'jem & fix', 'account' => '9310',
            'amount' => '212.6', 'file_date' => '2021-11-26', 'invoice_number' => '762-421', 'description' => '',
            'currency' => 'DKK', 'updated' => '2026-11-24 09:41:00', 'manually_edited' => 'f',
        ];
        $GLOBALS['pool_test_updates'] = [];

        function db_escape_string($value)
        {
            return str_replace("'", "''", (string)$value);
        }

        function db_select($query, $errorFileLine = '')
        {
            if (preg_match("/^SELECT \* FROM pool_files WHERE (filename = '([^']*)' FOR UPDATE|id = '(\d+)')$/", $query, $m)) {
                $row = $GLOBALS['pool_test_row'];
                return ($m[2] ?? '') === $row['filename'] || ($m[3] ?? '') === $row['id'] ? $row : false;
            }
            if (preg_match("/^SELECT kontonr FROM kontoplan WHERE kontonr = '([^']*)' AND regnskabsaar = '(\d+)' /", $query, $m)) {
                return isset($GLOBALS['pool_test_kontoplan']["$m[2]:$m[1]"]) ? ['kontonr' => $m[1]] : false;
            }
            throw new LogicException('Unexpected database query: ' . $query);
        }

        function db_modify($query, $errorFileLine = '')
        {
            $GLOBALS['pool_test_updates'][] = $query;
        }

        function db_fetch_array($result)
        {
            return $result;
        }

        function transaktion($action)
        {
        }

        function findtekst($id, $languageId)
        {
            return substr((string)$id, strpos((string)$id, '|') + 1);
        }

        require_once __DIR__ . '/../../includes/docsIncludes/poolMetadata.php';
    }

    /** What docPool.php hands poolMetadataSave() for the row editor's FormData (saveRowData()). */
    private function rowEditorInput(string $account): array
    {
        return [
            'subject' => 'jem & fix',
            'account' => $account,
            // "212,60" in the field; saveRowData() swaps the separators before posting.
            'amount' => '212.60',
            // <input type="date"> posts ISO, whatever the user typed (21.11.2026).
            'file_date' => '2026-11-21',
            'invoice_number' => '762-421',
            'description' => '',
        ];
    }

    private function version(): string
    {
        return poolMetadataVersion($GLOBALS['pool_test_row']);
    }

    public function testRowEditorSaveKeepsAStoredSuggestionThatIsNotInTheKontoplan(): void
    {
        $result = poolMetadataSave('voucher-1795.pdf', $this->rowEditorInput('9310'), true, $this->version(), 3);

        $this->assertTrue($result['success']);
        $update = $GLOBALS['pool_test_updates'][0] ?? '';
        $this->assertStringContainsString("account = '9310'", $update);
        $this->assertStringContainsString("file_date = '2026-11-21'", $update);
        $this->assertStringContainsString("amount = '212.60'", $update);
        $this->assertStringContainsString("subject = 'jem & fix'", $update);
        $this->assertStringContainsString('norm_amount = 212.6', $update);
        $this->assertStringContainsString('manually_edited = true', $update);
    }

    public function testATypedAccountThatIsNotInTheKontoplanIsStillRefused(): void
    {
        try {
            poolMetadataSave('voucher-1795.pdf', $this->rowEditorInput('9311'), true, $this->version(), 3);
            $this->fail('A typed account outside the kontoplan was accepted');
        } catch (InvalidArgumentException $error) {
            $this->assertSame(422, $error->getCode());
            $this->assertSame('Invalid account', $error->getMessage());
            $this->assertSame([], $GLOBALS['pool_test_updates']);
        }
    }

    public function testATypedValidAccountIsSaved(): void
    {
        poolMetadataSave('voucher-1795.pdf', $this->rowEditorInput('7200'), true, $this->version(), 3);
        $this->assertStringContainsString("account = '7200'", $GLOBALS['pool_test_updates'][0] ?? '');
    }

    public function testStoredAccountZeroIsStillSavedAsNotSet(): void
    {
        $GLOBALS['pool_test_row']['account'] = '0';
        poolMetadataSave('voucher-1795.pdf', $this->rowEditorInput('0'), true, $this->version(), 3);
        $this->assertStringContainsString("account = ''", $GLOBALS['pool_test_updates'][0] ?? '');
    }

    public function testRefusedSaveNamesTheReason(): void
    {
        $this->assertSame(
            'Kontoen findes ikke i kontoplanen for regnskabsåret, eller den er lukket. Ingen ændringer er gemt.',
            poolMetadataErrorText(new InvalidArgumentException('Invalid account', 422), 1)
        );
        $this->assertSame('Beløbet kan ikke læses. Ingen ændringer er gemt.', poolMetadataErrorText(new InvalidArgumentException('Invalid amount', 422), 1));
        $this->assertSame('Datoen findes ikke. Ingen ændringer er gemt.', poolMetadataErrorText(new InvalidArgumentException('Invalid date', 422), 1));
        $this->assertSame('Dokumentet er ændret. Genindlæs det før du gemmer.', poolMetadataErrorText(new RuntimeException('Document changed', 409), 1));
        $this->assertSame('Kontrollér konto, beløb og dato. Ingen ændringer er gemt.', poolMetadataErrorText(new InvalidArgumentException('Invalid value', 422), 1));
    }
}
