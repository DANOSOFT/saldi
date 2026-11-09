<?php
// 20261027 CL/LH Created: the pool's stored account on "Indsæt" / Bilagsmatch. Account 0 is not set, and a stored
//                suggestion the journal can't use is left out instead of refusing the attach with 422.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * poolJournalSuggestedAccount() and poolMetadataAccount() against an in-memory kontoplan and kassekladde.
 * Year 3: 7200 (D) and 58000 (S) open, 4000 closed, 5000 a heading. 2750 exists in year 5 only.
 * Journal 24: line 871 has an empty Debet (stored as 0), line 872 has Debet 7200.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class poolJournalSuggestedAccount extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['pool_test_kontoplan'] = [
            '3:7200' => ['D', ''], '3:58000' => ['S', ''], '3:4000' => ['D', 'on'], '3:5000' => ['H', ''], '5:2750' => ['D', ''],
        ];
        $GLOBALS['pool_test_kassekladde'] = ['871:24' => '0', '872:24' => '7200', '873:24' => '0.000'];

        function db_escape_string($value)
        {
            return str_replace("'", "''", (string)$value);
        }

        function db_select($query, $errorFileLine = '')
        {
            if (preg_match("/^SELECT debet FROM kassekladde WHERE id = '(\d+)' AND kladde_id = '(\d+)'$/", $query, $m)) {
                $debet = $GLOBALS['pool_test_kassekladde']["$m[1]:$m[2]"] ?? null;
                return $debet === null ? false : ['debet' => $debet];
            }
            if (preg_match("/^SELECT kontonr FROM kontoplan WHERE kontonr = '([^']*)' AND regnskabsaar = '(\d+)' AND kontotype IN \('D', 'S'\) AND \(lukket IS NULL OR lukket != 'on'\) LIMIT 1$/", $query, $m)) {
                $account = $GLOBALS['pool_test_kontoplan']["$m[2]:$m[1]"] ?? null;
                return ($account && in_array($account[0], ['D', 'S'], true) && $account[1] !== 'on') ? ['kontonr' => $m[1]] : false;
            }
            throw new LogicException('Unexpected database query: ' . $query);
        }

        function db_fetch_array($result)
        {
            return $result;
        }

        require_once __DIR__ . '/../../includes/docsIncludes/poolMetadata.php';
    }

    public function testAccountZeroIsNotSet(): void
    {
        $this->assertSame('', poolMetadataAccount('0', 3));
        $this->assertSame('', poolMetadataAccount(' 000 ', 3));
        $this->assertSame('', poolMetadataAccount('', 3));
        $this->assertSame('', poolMetadataAccount(null, 3));
    }

    public function testTypedAccountThatDoesNotExistIsStillRefused(): void
    {
        foreach (['9999', '2750', '4000', '5000', 'Netto'] as $account) {
            try {
                poolMetadataAccount($account, 3);
                $this->fail("Account $account was accepted");
            } catch (InvalidArgumentException $error) {
                $this->assertSame(422, $error->getCode());
            }
        }
        $this->assertSame('7200', poolMetadataAccount('7200', 3));
    }

    public function testEmptyDebetLineGetsAUsableSuggestion(): void
    {
        $this->assertSame('7200', poolJournalSuggestedAccount('7200', 'kassekladde', 871, 24, '', 3));
        // Debet stored with decimals is as empty as 0
        $this->assertSame('7200', poolJournalSuggestedAccount('7200', 'kassekladde', 873, 24, '', 3));
        $this->assertSame('7200', poolJournalSuggestedAccount('7200', 'kassekladde', 0, 24, '', 3));
    }

    public function testUnusableStoredSuggestionIsLeftOutInsteadOfRefused(): void
    {
        // Marian 2026-10-27: Debet empty, the pool receipt's stored account 0 or an app's suggestion -> 422 on Indsæt and Bilagsmatch
        foreach (['0', '9999', '2750', '4000', '5000', 'Netto', ''] as $account) {
            $this->assertSame('', poolJournalSuggestedAccount($account, 'kassekladde', 871, 24, '', 3), "Stored account '$account'");
            $this->assertSame('', poolJournalSuggestedAccount($account, 'kassekladde', 0, 24, '', 3), "Stored account '$account', new line");
        }
    }

    public function testExistingAndExplicitDebetArePreserved(): void
    {
        $this->assertSame('', poolJournalSuggestedAccount('58000', 'kassekladde', 872, 24, '', 3));
        $this->assertSame('', poolJournalSuggestedAccount('58000', 'kassekladde', 871, 24, '7200', 3));
        $this->assertSame('', poolJournalSuggestedAccount('58000', 'ordre', 871, 24, '', 3));
    }

    public function testLineOfAnotherJournalIsStillRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(422);
        poolJournalSuggestedAccount('7200', 'kassekladde', 871, 25, '', 3);
    }
}
