<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Nulstil regnskab (systemdata/resetAccount.php) empties the CRM's own tables with historik and crm.
 *
 * The reset plan (accountResetStatements()) names every table it empties, and a table it does not name is kept. The CRM
 * tables crm_deals, crm_meetings and crm_participants were not named: after a reset their rows stayed and pointed at
 * the emptied historik, whose ids start again at 1 (RESTART IDENTITY), so the first new follow-up got an old meeting's
 * time and colleagues. A user's calendar feed (crm_ical, one row per brugere id) belongs to the user, and users stay.
 *
 * Pins: with and without Behold konti and Behold varer, the one TRUNCATE names the three CRM tables next to historik and
 * crm, and never crm_ical.
 *
 * History:
 * 20261124 CL/ASR Created.
 */
final class AccountResetCrmTablesTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../../systemdata/resetAccount.php';
    }

    /** @return list<string> the tables the plan's TRUNCATE names */
    private static function truncated(bool $keepAccounts, bool $keepItems): array
    {
        $existing = ['historik', 'crm', 'crm_deals', 'crm_meetings', 'crm_participants', 'crm_ical', 'brugere', 'adresser'];
        $statements = accountResetStatements($existing, $keepAccounts, $keepItems, 'postgresql');
        self::assertMatchesRegularExpression('/^TRUNCATE [a-z_,]+ RESTART IDENTITY$/', $statements[0]);
        return explode(',', substr($statements[0], strlen('TRUNCATE '), -strlen(' RESTART IDENTITY')));
    }

    public function test_the_crm_tables_are_reset_with_historik_and_a_users_calendar_feed_is_kept(): void
    {
        foreach ([[false, false], [true, false], [false, true], [true, true]] as [$keepAccounts, $keepItems]) {
            $tables = self::truncated($keepAccounts, $keepItems);
            foreach (['historik', 'crm', 'crm_deals', 'crm_meetings', 'crm_participants'] as $table) {
                $this->assertContains($table, $tables, "$table is reset (keep accounts: " . var_export($keepAccounts, true) . ', keep items: ' . var_export($keepItems, true) . ')');
            }
            $this->assertNotContains('crm_ical', $tables, "a user's calendar feed stays with the user");
            $this->assertNotContains('brugere', $tables);
        }
    }
}
