<?php
// 20261126 CDX/NTR A contact refusal must return the cashier to the editable sale, with its items and delivery choice intact (#1183).

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class PosContactRefusalPageTest extends CharacterizationTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $uuid = self::rows('SELECT uuid FROM public.saldi_phpunit_identity')[0]['uuid'];
        $schema = CharacterizationEnv::runChild(__DIR__ . '/../../shopify/support/run_pos_delivery.php',
            [CharacterizationEnv::testDb(), $uuid, 'UTF8', 'postgresql', json_encode([['ensure_schema']])]);
        self::assertSame(0, $schema['exit'], $schema['stderr']);

        self::rows("DELETE FROM grupper WHERE art = 'POS'");
        self::rows("INSERT INTO grupper (art,kodenr,fiscal_year,box1,box2,box3,box4,box5,box6,box7,box9,box10)
            VALUES ('POS','1',$1,'1','58200','1','1','Dankort','58050','1','','')", [self::$regnaar]);
        self::rows("INSERT INTO grupper (art,kodenr,fiscal_year,box3,box4,box5,box6,box7,box10,box12)
            VALUES ('POS','2',$1,'localhost','','','','','','')", [self::$regnaar]);
        self::rows("INSERT INTO grupper (art,kodenr,fiscal_year,box2,box3)
            VALUES ('POS','3',$1,'10','')", [self::$regnaar]);
        self::rows("UPDATE grupper SET box11='on' WHERE art='POS' AND kodenr='1'");
        self::rows("UPDATE grupper SET box4='2',box5=$1 WHERE art='POS' AND kodenr='1'", ["Dankort\tGavekort"]);
        self::rows("INSERT INTO settings (var_name,var_grp,var_value) VALUES ('voucherItems','Paycards',$1)", ["\ton"]);
    }

    /**
     * Replay the browser's request through the real page and its normal session.
     * @param array<string, int|string> $get
     * @param array<string, int|string> $post
     * @return string Rendered page, including any refusal alert.
     */
    private function page(array $get, array $post = []): string
    {
        $output = (string)tempnam(sys_get_temp_dir(), 'pos-refusal-page-');
        try {
            $request = json_encode(['get' => $get, 'post' => $post,
                'cookie' => ['saldi_pos' => '1', 'saldi_pfs' => '10', 'saldi_bordnr' => '']], JSON_THROW_ON_ERROR);
            $child = CharacterizationEnv::runChild(__DIR__ . '/../support/run_legacy_shop_page.php',
                ['debitor/pos_ordre.php', CharacterizationEnv::sessionId(), $request, $output]);
            self::assertSame(0, $child['exit'], $child['stderr']);
            self::assertStringContainsString('CHARTEST_PAGE_DONE', $child['stdout']);
            return (string)file_get_contents($output);
        } finally {
            unlink($output);
        }
    }

    /** @return array<string, array{string, array<string, int|string>}> */
    public static function refusedPayments(): array
    {
        $cases = [];
        foreach (['afhentning', 'levering'] as $type) {
            foreach ([
                'cash' => [],
                'part payment' => ['betaling2' => 'ukendt', 'modtaget' => '/2'],
                'gift card' => ['betaling' => 'Gavekort', 'modtaget' => '', 'afslut' => ''],
                'cash on amount' => ['betaling' => 'Kontant på beløb', 'modtaget' => '', 'afslut' => ''],
            ] as $label => $post) {
                $cases[$type . ', ' . $label] = [$type, $post];
            }
        }
        return $cases;
    }

    /** @param array<string, int|string> $payment */
    #[DataProvider('refusedPayments')]
    public function testAContactRefusalRestoresTheEditableSale(string $type, array $payment): void
    {
        $customer = self::$fx->debtor(['betalingsbet' => 'Kontant', 'email' => '']);
        $item = self::$fx->item(['salgspris' => 100, 'beskrivelse' => 'Contact refusal test item']);
        $sale = self::$fx->order($customer, [['item' => $item, 'beskrivelse' => 'Contact refusal test item']],
            ['art' => 'PO', 'status' => 0, 'betalingsbet' => 'Kontant']);
        $choice = self::rows("INSERT INTO pos_leveringsvalg (navn,type) VALUES ('Test delivery',$1) RETURNING id", [$type])[0]['id'];
        $date = date('Y-m-d', strtotime('+30 days'));
        self::rows("UPDATE ordrer SET email='',phone='',betalingsbet='Kontant',felt_5='1',
            leveringsvalg_id=$1,oensket_levdato=$2 WHERE id=$3", [$choice, $date, $sale['id']]);
        $get = ['id' => $sale['id'], 'kasse' => 1];

        $html = $this->page($get, $payment + ['betaling' => 'Kontant', 'fokus' => 'modtaget', 'sum' => 125,
            'rest' => 125, 'modtaget' => '125', 'afslut' => 'on', 'delbetaling' => 'on',
            'betvalkurs' => 100, 'kontonr' => $customer['kontonr']]);

        self::assertSame(1, substr_count($html, 'Afhentning og levering kræver kundens e-mail eller telefonnummer.'),
            'show the contact refusal once');
        self::assertTrue(str_contains($html, "name='leveringsvalg_id'"),
            'after dismissing the alert the cashier must be able to change the delivery choice');
        self::assertTrue(str_contains($html, 'Contact refusal test item'), 'the sale items must stay visible');
        self::assertMatchesRegularExpression('/name\s*=\s*[\'"]debitoropslag[\'"]/i', $html, 'the cashier must be able to attach another customer');
        self::assertMatchesRegularExpression('/name\s*=\s*[\'"]varenr_ny[\'"]/i', $html, 'return to the sale inputs');
        self::assertDoesNotMatchRegularExpression('/name\s*=\s*[\'"]modtaget[\'"]/i', $html, 'leave the refused payment screen');
        $order = self::rows('SELECT status,leveringsvalg_id,oensket_levdato FROM ordrer WHERE id=$1', [$sale['id']])[0];
        self::assertSame(0, (int)$order['status']);
        self::assertSame((int)$choice, (int)$order['leveringsvalg_id']);
        self::assertSame($date, $order['oensket_levdato']);
        self::assertSame(0, (int)self::rows('SELECT count(*) AS count FROM pos_betalinger WHERE ordre_id=$1', [$sale['id']])[0]['count']);
    }
}
