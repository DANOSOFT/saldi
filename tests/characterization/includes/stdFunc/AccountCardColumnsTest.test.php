<?php
// tests/characterization/includes/stdFunc/AccountCardColumnsTest.test.php
//
// The version a debtor or creditor card carries in its form
// (account_card_version() in includes/stdFunc/accountCardVersion.php) covers
// every adresser column the cards' Gem writes, is the version of the row the
// form's fields were drawn from, and every card compares it and writes inside
// the transaction account_card_begin_save() opens with a lock on the account.
//
// The version hashes a list of columns, account_card_columns(), and not the
// whole row: other pages keep saldo, invoiced and bank_unconfirmed up to date,
// and a change there is not one a card would undo. A column added to a card's
// update without adding it to the list would be written back from a stale form
// again. This reads the cards' source, so it needs no database.
//
// The version comes before every field a form repeats per row (statuses,
// categories, contact persons): PHP keeps only the first max_input_vars
// (1000) fields of a post, and a version after them was dropped from a card
// with 500 contact persons while the fields before it were saved.
//
// History:
// 20261105 CL/LH Created.
// 20261115 CL/ASR Integration: the debtor card (debitor/debitorkort.php) is left out (decision T2: master's MB-82 check, DebitorkortStaleSaveWiringTest); the Sager and both creditor cards keep their cases.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../includes/stdFunc/accountCardVersion.php';

final class AccountCardColumnsTest extends TestCase
{
    private const HELPER = 'includes/stdFunc/accountCardVersion.php';

    /**
     * Each card's save file, the file that prints its form and the file that reads the row its fields are drawn from.
     *
     * @return array<string, array{0:string, 1:string, 2:string}>
     */
    public static function cards(): array
    {
        return [
            // The debtor card (debitor/debitorkort.php) left: it uses master's MB-82 check, pinned by DebitorkortStaleSaveWiringTest.
            'debitor/debkort_save.php' => ['debitor/debkort_save.php', 'sager/kunder.php', 'debitor/debkort_load_stillads.php'],
            'kreditor/kreditorkort.php' => ['kreditor/kreditorkort.php', 'kreditor/kreditorkort.php', 'kreditor/kreditorkort.php'],
            'includes/kreditorkort.php' => ['includes/kreditorkort.php', 'includes/kreditorkort.php', 'includes/kreditorkort.php'],
        ];
    }

    /** The file's PHP code without its comments. */
    private static function code(string $file): string
    {
        $code = '';
        foreach (token_get_all((string)file_get_contents(__DIR__ . '/../../../../' . $file)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }
        return $code;
    }

    /**
     * The file's updates of adresser: where each starts, and the columns it sets.
     *
     * @return list<array{offset:int, columns:list<string>}>
     */
    private static function updates(string $code): array
    {
        preg_match_all('/update\s+adresser\s+set\b(.*?)\bwhere\b/is', $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $updates = [];
        foreach ($matches as $match) {
            preg_match_all("/\b([a-z_][a-z0-9_]*)\s*=\s*'/i", $match[1][0], $columns);
            $updates[] = ['offset' => $match[0][1], 'columns' => array_map('strtolower', $columns[1])];
        }
        return $updates;
    }

    #[DataProvider('cards')]
    public function test_every_column_the_card_writes_is_in_its_version(string $saveFile, string $formFile, string $loadFile): void
    {
        $updates = self::updates(self::code($saveFile));
        $written = array_unique(array_merge(...array_column($updates, 'columns')));
        $this->assertContains('notes', $written, "$saveFile writes the card's Bemærkning");
        $this->assertSame([], array_values(array_diff($written, account_card_columns())), "columns $saveFile writes that account_card_columns() leaves out");
    }

    #[DataProvider('cards')]
    public function test_the_card_compares_its_version_and_writes_under_the_accounts_lock(string $saveFile, string $formFile, string $loadFile): void
    {
        $save = self::code($saveFile);
        $this->assertStringContainsString('!account_card_begin_save(', $save, "$saveFile refuses a stale form");
        $this->assertStringContainsString('account_card_end_save()', $save, "$saveFile commits the save it wrote");
        $cardUpdate = array_values(array_filter(self::updates($save), static fn(array $update): bool => in_array('notes', $update['columns'], true)));
        $this->assertCount(1, $cardUpdate, "$saveFile has one update of the whole card");
        $begin = strpos($save, 'account_card_begin_save(');
        $this->assertLessThan($cardUpdate[0]['offset'], $begin, "$saveFile takes the lock and compares before that update");
        $this->assertGreaterThan($cardUpdate[0]['offset'], strrpos($save, 'account_card_end_save()'), "$saveFile commits after that update");
        // A transaction of the page's own would commit or roll back in the middle of the card's and let the lock go.
        $this->assertDoesNotMatchRegularExpression("/transaktion\\(\\s*['\"](begin|commit|rollback)['\"]\\s*\\)/i", $save, "$saveFile opens or ends no transaction of its own");
    }

    #[DataProvider('cards')]
    public function test_the_form_carries_the_version_of_the_row_its_fields_are_drawn_from(string $saveFile, string $formFile, string $loadFile): void
    {
        $load = self::code($loadFile);
        $this->assertMatchesRegularExpression(
            '/select \* from adresser where id = \'\$id\'"[^;]*;\s*\$r = db_fetch_array\(\$q\);\s*\$accountCard = \$r \?: array\(\);/',
            $load,
            "$loadFile keeps the row it draws the fields from, as it read it"
        );
        $this->assertMatchesRegularExpression('/print account_card_version_field\(\$accountCard\b/', self::code($formFile), "$formFile prints the version of that row");
    }

    /**
     * The file that prints each card's form, and the form's name.
     *
     * @return array<string, array{0:string, 1:string}>
     */
    public static function forms(): array
    {
        return [
            // The debtor card (debitor/debitorkort.php) left: its form carries master's recordHash, not this version.
            'sager/kunder.php' => ['sager/kunder.php', 'ret_kunde'],
            'kreditor/kreditorkort.php' => ['kreditor/kreditorkort.php', 'kreditorkort'],
            'includes/kreditorkort.php' => ['includes/kreditorkort.php', 'kreditorkort'],
        ];
    }

    #[DataProvider('forms')]
    public function test_the_version_comes_before_every_field_the_form_repeats_per_row(string $formFile, string $formName): void
    {
        $code = self::code($formFile);
        $this->assertSame(1, preg_match('/<form name=\\\\?["\']?' . $formName . '\b/', $code, $tag, PREG_OFFSET_CAPTURE), "$formFile draws the $formName form");
        $version = strpos($code, 'print account_card_version_field(', $tag[0][1]);
        $this->assertNotFalse($version, "$formFile prints the version in the $formName form");
        // The first field named as an array (status_id[...], cat_valg[...], posnr[...]) after the form's start tag.
        if (preg_match('/name=\\\\?["\']?[A-Za-z_][A-Za-z0-9_]*\[/', $code, $repeated, PREG_OFFSET_CAPTURE, $tag[0][1])) {
            $this->assertLessThan($repeated[0][1], $version, "$formFile prints the version before {$repeated[0][0]}");
        }
    }

    public function test_the_lock_is_the_first_statement_of_the_save_and_the_version_is_read_after_it(): void
    {
        $function = new ReflectionFunction('account_card_begin_save');
        $lines = array_slice(file((string)$function->getFileName()), $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1);
        $body = implode('', $lines);
        preg_match_all("/transaktion\\('begin'\\)|db_select\\(\"([^\"]*)\"|account_card_stored_version\\(/", $body, $calls, PREG_SET_ORDER);
        $order = array_map(static fn(array $call): string => isset($call[1]) ? $call[1] : $call[0], $calls);
        $this->assertSame(["transaktion('begin')", 'select id from adresser where id = $id for update', 'account_card_stored_version('], $order, self::HELPER . ' reads nothing in the save before the lock');
    }

    public function test_the_version_is_that_of_the_values_and_rows_however_they_were_read(): void
    {
        $account = ['id' => '7', 'notes' => '', 'tlf' => '86123450', 'saldo' => '12.50', 'modtime' => '2026-11-05 10:00'];
        $fetched = $account + array_values($account); // db_fetch_array() returns both the names and the positions
        $emails = [['id' => '3', 'email' => 'a@example.invalid'], ['id' => '9', 'email' => 'b@example.invalid']];
        $addresses = [['id' => '5', 'is_primary' => 'f'], ['id' => '4', 'is_primary' => 't']]; // drawn primary first
        $contacts = ['kontakt_emails' => $emails, 'delivery_addresses' => $addresses];
        $version = account_card_version($account, $contacts);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $version);
        $this->assertSame($version, account_card_version($fetched, $contacts), 'the positions db_fetch_array() adds do not count');
        $this->assertSame($version, account_card_version($account, ['kontakt_emails' => array_reverse($emails), 'delivery_addresses' => array_reverse($addresses)]), 'the rows count in id order');
        $this->assertSame($version, account_card_version(['saldo' => '99.00', 'modtime' => '2026-11-06 08:00'] + $account, $contacts), 'columns the card does not write do not count');
        $this->assertSame($version, account_card_version(['notes' => null] + $account, $contacts), "NULL is ''");
        $this->assertNotSame($version, account_card_version(['notes' => 'Levering kun hverdage'] + $account, $contacts), 'a saved note does');
        $this->assertNotSame($version, account_card_version($account, ['kontakt_emails' => [$emails[0]], 'delivery_addresses' => $addresses]), 'an e-mail added or removed does');
        $this->assertNotSame($version, account_card_version($account), 'the debtor card version includes its rows');
        $this->assertSame('', account_card_version(false), 'no account');
        $this->assertSame('', account_card_version([]), 'a new card');
        $this->assertSame("<input type=hidden name=card_version value='$version'>\n", account_card_version_field($fetched, $contacts), 'the field carries the version of what it is given, read from nowhere else');
    }
}
