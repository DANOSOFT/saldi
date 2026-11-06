<?php
// tests/characterization/includes/stdFunc/AccountDeletePathsTest.test.php
//
// Every file that deletes adresser rows asks account_has_entries()
// (includes/stdFunc/accountHasEntries.php) first, or is listed below with the
// reason its delete cannot leave open posts or orders without an owner.
//
// The first fix for orphaned open posts guarded the REST API and missed five
// card handlers that ran the same delete (testlab round 1, review). A new
// delete path now fails here until it uses the check or says why it needs none.
// AccountDeleteKeepsEntriesTest drives the card handlers themselves.
//
// Reads the source only; runs everywhere.
//
// History:
// 20261105 CL/LH Created.

use PHPUnit\Framework\TestCase;

final class AccountDeletePathsTest extends TestCase
{
    /** Files that delete adresser rows without account_has_entries(), and why that is safe. */
    private const OTHER_GUARDS = [
        'debitor/kontofusion.php' => 'a merge: the open posts and orders move to the surviving account first',
        'kreditor/kontofusion.php' => 'a merge: the open posts and orders move to the surviving account first',
        'includes/kreditorFromCvr.php' => 'kreditorCvrUndo() refuses when kreditorCvrReferenced() finds an open post or order',
        'includes/settings/actions.php' => 'settings_gdpr_inactive() only selects accounts that no open post or order points at',
        'systemdata/importer_kreditor.php' => 'the row is inserted again with the same id straight after',
        'systemdata/resetAccount.php' => 'the reset empties the whole ledger, open posts and orders included',
        'systemdata/gdprInc/gdprLogic.php' => 'included nowhere: systemdata/gdpr.php redirects to the settings page (includes/settings/actions.php)',
    ];

    /** @return array<string, bool> repo-relative path of each file whose code (not its comments) deletes adresser rows => whether it calls account_has_entries() */
    private static function accountDeleters(): array
    {
        $root = dirname(__DIR__, 4);
        $files = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static fn(SplFileInfo $f): bool => !$f->isDir() || !in_array($f->getFilename(), ['.git', 'vendor', 'node_modules', 'tests'], true)
        ));
        $found = [];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = (string)file_get_contents($file->getPathname());
            if (!preg_match('/delete\s+from\s+adresser\b/i', $source)) {
                continue;
            }
            $deletes = $guarded = false;
            foreach (token_get_all($source) as $token) {
                if (!is_array($token) || in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML], true)) {
                    continue;
                }
                $deletes = $deletes || preg_match('/delete\s+from\s+adresser\b/i', $token[1]) === 1;
                $guarded = $guarded || ($token[0] === T_STRING && $token[1] === 'account_has_entries');
            }
            if ($deletes) {
                $found[substr($file->getPathname(), strlen($root) + 1)] = $guarded;
            }
        }
        ksort($found);
        return $found;
    }

    public function test_every_account_delete_asks_account_has_entries_first(): void
    {
        $unguarded = [];
        foreach (self::accountDeleters() as $path => $guarded) {
            if (!$guarded && !isset(self::OTHER_GUARDS[$path])) {
                $unguarded[] = $path;
            }
        }

        $this->assertSame([], $unguarded,
            'These files delete adresser rows without account_has_entries(); call it before the delete and its cleanup, '
            . 'or add the file to OTHER_GUARDS with the reason open posts and orders cannot be orphaned.');
    }

    public function test_the_card_and_api_deletes_are_among_the_guarded_ones(): void
    {
        $guarded = array_keys(array_filter(self::accountDeleters()));

        foreach ([
            'api/v2/addresses.php',
            'debitor/debitorkort.php',
            'debitor/debkort_save.php',
            'includes/kreditorkort.php',
            'kreditor/kreditorkort.php',
            'restapi/models/customers/CustomerModel.php',
            'sager/kunder.php',
        ] as $path) {
            $this->assertContains($path, $guarded, "$path deletes an account and must ask account_has_entries() first");
        }
    }

    public function test_every_listed_exception_still_deletes_accounts(): void
    {
        $deleters = self::accountDeleters();

        foreach (array_keys(self::OTHER_GUARDS) as $path) {
            $this->assertArrayHasKey($path, $deleters, "$path no longer deletes adresser rows; remove it from OTHER_GUARDS");
        }
    }
}
