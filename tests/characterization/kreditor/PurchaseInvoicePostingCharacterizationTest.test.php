<?php
// 20261113 CDX/NTR Characterize supplier invoices and credit notes before extracting their posting engine (SD-599).

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../support/CharacterizationEnv.php';
require_once __DIR__ . '/../support/Fixtures.php';

final class PurchaseInvoicePostingCharacterizationTest extends TestCase
{
    private static $tenant;
    private static int $year;
    private static int $purchaseAccount;
    private static int $vatAccount;
    private static int $collectiveAccount;

    public static function setUpBeforeClass(): void
    {
        $reason = CharacterizationEnv::unavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant(static::class);
        self::$tenant = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        $year = CharacterizationEnv::one(self::$tenant, "SELECT max(kodenr) AS year FROM grupper WHERE art='RA'");
        self::$year = (int)$year['year'];
        $fixtures = new Fixtures(self::$tenant, self::$year);
        [self::$purchaseAccount, self::$vatAccount] = $fixtures->vatPurchaseAccount();
        $collective = CharacterizationEnv::one(self::$tenant, "SELECT box2 FROM grupper WHERE art='KG' AND fiscal_year=$1 ORDER BY kodenr LIMIT 1", [self::$year]);
        self::$collectiveAccount = (int)$collective['box2'];
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO grupper (art,kodenr,fiscal_year,box1,box2) VALUES ('KG',9101,$1,'K1',$2)", [self::$year, self::$collectiveAccount]);
        CharacterizationEnv::rows(self::$tenant, "INSERT INTO ansatte (id,navn,afd) VALUES (9101,'SD599 purchaser',0)");
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant)) {
            pg_close(self::$tenant);
            CharacterizationEnv::teardownTenant();
        }
    }

    public static function invoices(): array
    {
        return [
            'supplier invoice' => ['KO', 1250.5, 1, 'DKK', 100],
            'supplier credit' => ['KK', 1250.5, -1, 'DKK', 100],
            'half ore invoice' => ['KO', 0.005, 1, 'DKK', 100],
            'half ore credit' => ['KK', 0.005, -1, 'DKK', 100],
            'zero invoice' => ['KO', 0.0, 1, 'DKK', 100],
            'zero credit' => ['KK', 0.0, -1, 'DKK', 100],
            'foreign invoice' => ['KO', 1250.5, 1, 'EUR', 750],
            'foreign credit' => ['KK', 1250.5, -1, 'EUR', 750],
        ];
    }

    #[DataProvider('invoices')]
    public function test_posting_keeps_supplier_signs_accounts_currency_and_caller_transaction(string $art, float $price, int $quantity, string $currency, int $rate): void
    {
        $net = round($price * $quantity, 2);
        $vat = round($net * 0.25, 2);
        $supplier = CharacterizationEnv::one(self::$tenant, "INSERT INTO adresser (kontonr,firmanavn,art,gruppe) VALUES (91001,'SD599 Supplier','K',9101) RETURNING id");
        $order = CharacterizationEnv::one(
            self::$tenant,
            "INSERT INTO ordrer (art,konto_id,kontonr,firmanavn,ref,ordredate,levdate,fakturadate,fakturanr,modtagelse,sum,moms,momssats,valuta,valutakurs,status,projekt)
             VALUES ($1,$2,91001,'SD599 Supplier','SD599 purchaser',CURRENT_DATE,CURRENT_DATE,CURRENT_DATE,'599',17,$3,$4,25,$5,$6,3,0) RETURNING id",
            [$art, $supplier['id'], $net, $vat, $currency, $rate]
        );
        $orderId = (int)$order['id'];
        CharacterizationEnv::rows(
            self::$tenant,
            "INSERT INTO ordrelinjer (ordre_id,vare_id,antal,pris,rabat,bogf_konto,posnr,projekt) VALUES ($1,9101,$2,$3,0,$4,1,0)",
            [$orderId, $quantity, $price, self::$purchaseAccount]
        );
        $result = CharacterizationEnv::runChild(__DIR__ . '/support/run_purchase_posting.php', [$orderId, CharacterizationEnv::testDb(), self::$year]);
        self::assertSame(0, $result['exit'], $result['stdout'] . $result['stderr']);
        $out = json_decode(trim($result['stdout']), true);
        self::assertIsArray($out, $result['stdout'] . $result['stderr']);
        self::assertSame(PGSQL_TRANSACTION_INTRANS, $out['transaction'], 'posting leaves the caller transaction open');
        $header = CharacterizationEnv::one(self::$tenant, 'SELECT status,fakturanr,valutakurs FROM ordrer WHERE id=$1', [$orderId]);
        self::assertSame('4', $header['status']);
        self::assertSame('599', $header['fakturanr']);
        self::assertEqualsWithDelta($rate, (float)$header['valutakurs'], 0.0001);
        $open = CharacterizationEnv::one(self::$tenant, 'SELECT amount,valuta,valutakurs,beskrivelse FROM openpost WHERE refnr=$1', [$orderId]);
        self::assertEqualsWithDelta(-($net + $vat), (float)$open['amount'], 0.0001);
        self::assertSame($currency, $open['valuta']);
        self::assertEqualsWithDelta($rate, (float)$open['valutakurs'], 0.0001);
        $description = $art === 'KK' ? 'Lev. kn.nr: 599, modt. nr 17' : 'Lev. fakt.nr:599, modt.nr: 17';
        self::assertSame($description, $open['beskrivelse']);
        $rows = CharacterizationEnv::rows(self::$tenant, 'SELECT kontonr,debet,kredit FROM transaktioner WHERE ordre_id=$1', [$orderId]);
        $expected = [];
        foreach ([self::$collectiveAccount => -($net + $vat), self::$purchaseAccount => $net, self::$vatAccount => $vat] as $account => $amount) {
            if ($amount != 0) {
                // The supplier engine retains three decimals for the VAT leg in foreign currency.
                $base = round($amount * $rate / 100, $account === self::$vatAccount ? 3 : 2);
                $expected[$account] = [max(0, $base), max(0, -$base)];
            }
        }
        $actual = [];
        foreach ($rows as $row) {
            $actual[(int)$row['kontonr']] = [(float)$row['debet'], (float)$row['kredit']];
        }
        ksort($expected);
        ksort($actual);
        self::assertEquals($expected, $actual);
    }
}
