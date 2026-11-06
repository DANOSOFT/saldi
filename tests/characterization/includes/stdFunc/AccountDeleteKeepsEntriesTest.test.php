<?php
// tests/characterization/includes/stdFunc/AccountDeleteKeepsEntriesTest.test.php
//
// Every page that deletes a debtor or creditor from its card keeps an account
// that open posts or orders point at (account_has_entries() in
// includes/stdFunc/accountHasEntries.php), and says why.
//
// The cards hide Slet for such an account, but the handlers behind them
// deleted whatever id was posted: the adresser row went and its open posts
// and orders were left without an owner (testlab round 1, NC 1535 through the
// REST API; the REST paths are covered in tests/restapi/CustomersEndpointTest.php).
// The Sager customer card even offered Slet kunde for an invoiced customer.
//
// Each path is driven through the page itself with the form a browser would
// post. An account without open posts or orders is still deleted, which also
// shows that the post reached the delete branch.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../../support/CharacterizationTestCase.php';

final class AccountDeleteKeepsEntriesTest extends CharacterizationTestCase
{
    private const KEPT = 'Kontoen har åbne poster eller ordrer og kan ikke slettes.';

    /**
     * The delete paths: page, account kind, query string and form body, %d being the account id.
     *
     * @return array<string, array{0:string, 1:string, 2:string, 3:string}>
     */
    private static function paths(): array
    {
        return [
            'debtor card Slet' => ['debitor/debitorkort.php', 'D', 'id=%d', 'id=%d&submit=Slet'],
            'Sager Slet kunde' => ['sager/kunder.php', 'D', 'funktion=ret_kunde&konto_id=%d', 'id=%d&slet_kunde=1'],
            'Sager save with Slet (debitor/debkort_save.php)' => ['sager/kunder.php', 'D', 'funktion=ret_kunde&konto_id=%d', 'id=%d&gem_rettelse=1&submit=Slet'],
            'creditor card Slet' => ['kreditor/kreditorkort.php', 'K', 'id=%d', 'id=%d&submit=Slet'],
            'old creditor card Slet (includes/kreditorkort.php)' => ['includes/kreditorkort.php', 'K', 'id=%d', 'id=%d&submit=Slet'],
        ];
    }

    /** @return array<string, array{0:string, 1:string, 2:string, 3:string, 4:string}> */
    public static function pathsWithEntries(): array
    {
        $cases = [];
        foreach (self::paths() as $name => $path) {
            foreach (['openpost', 'ordrer'] as $table) {
                $cases["$name, $table"] = [...$path, $table];
            }
        }
        return $cases;
    }

    /** @return array<string, array{0:string, 1:string, 2:string, 3:string}> */
    public static function pathsWithoutEntries(): array
    {
        return self::paths();
    }

    /**
     * @return array{id:int, kontonr:string}
     */
    private function account(string $art): array
    {
        return $art === 'K' ? self::$fx->creditor() : self::$fx->debtor();
    }

    /** @return array{alerts:list<string>, warnings:list<string>} */
    private function delete(string $page, string $query, string $body, int $id): array
    {
        return $this->runChildJson('run_account_delete_page.php', [
            $page, sprintf($query, $id), sprintf($body, $id), CharacterizationEnv::SESSION_ID,
        ]);
    }

    private function accountExists(int $id): bool
    {
        return self::one('SELECT id FROM adresser WHERE id = $1', [$id]) !== null;
    }

    #[DataProvider('pathsWithEntries')]
    public function test_an_account_that_an_open_post_or_order_points_at_is_kept(string $page, string $art, string $query, string $body, string $table): void
    {
        $account = $this->account($art);
        if ($table === 'openpost') {
            self::$fx->openPost($account, 810.0, 10, 'DEL' . $account['id']);
        } elseif ($art === 'K') {
            self::$fx->creditorOrder($account, []);
        } else {
            self::$fx->order($account, []);
        }

        $out = $this->delete($page, $query, $body, $account['id']);

        $this->assertTrue($this->accountExists($account['id']), 'the account is kept');
        $owned = self::one("SELECT count(*) AS n FROM $table t JOIN adresser a ON a.id = t.konto_id WHERE t.konto_id = $1", [$account['id']]);
        $this->assertSame('1', $owned['n'], "the $table row keeps its owner");
        $this->assertContains(self::KEPT, $out['alerts'], 'the page says why');
        $this->assertSame([], $out['warnings']);
    }

    #[DataProvider('pathsWithoutEntries')]
    public function test_an_account_without_open_posts_or_orders_is_still_deleted(string $page, string $art, string $query, string $body): void
    {
        $account = $this->account($art);

        $out = $this->delete($page, $query, $body, $account['id']);

        $this->assertFalse($this->accountExists($account['id']), 'the account is deleted');
        $this->assertNotContains(self::KEPT, $out['alerts']);
        $this->assertSame([], $out['warnings']);
    }
}
