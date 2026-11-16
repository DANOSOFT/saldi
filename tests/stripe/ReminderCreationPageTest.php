<?php
// 20261116 CDX/ASR Execute the reminder creation controller with missing customers and trailing batch separators.
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/reminderFixtures.php';
require_once __DIR__ . '/../../includes/stdFunc/dunningCutoff.php';

final class ReminderCreationPageTest extends StripeDatabaseTestCase
{
    public function testMissingOrChangedReportLedgerIsRefused(): void
    {
        $this->assertFalse(reminder_request_ledger_matches(null, 'saldi_6'));
        $this->assertFalse(reminder_request_ledger_matches('saldi_1091', 'saldi_6'));
        $this->assertFalse(reminder_request_ledger_matches(['saldi_6'], 'saldi_6'));
        $this->assertTrue(reminder_request_ledger_matches('saldi_1091', 'saldi_1091'));
    }

    public function testSelectedBatchSkipsMissingDebtorsAndHandlesUnsetGroupsWithoutWarnings(): void
    {
        foreach (['ordrer', 'ordrelinjer'] as $table) {
            StripeTestEnvironment::query("CREATE TEMP TABLE $table (LIKE public.$table INCLUDING ALL)");
            StripeTestEnvironment::query("CREATE TEMP SEQUENCE batch_{$table}_seq START 900000000");
            StripeTestEnvironment::query("ALTER TABLE pg_temp.$table ALTER COLUMN id SET DEFAULT nextval('pg_temp.batch_{$table}_seq')");
        }
        StripeReminderFixtures::forms();
        StripeTestEnvironment::setting('stripe', 'reminder_format', 'classic');
        $first = StripeFixtures::debtor(['gruppe' => null]);
        $second = StripeFixtures::debtor(['gruppe' => 1, 'kontoansvarlig' => 999999999]);
        StripeFixtures::invoice($first);
        StripeFixtures::invoice($second);
        $before = $_GET;
        $_GET = ['kontoliste' => "$first;999999999;$second;", 'kontoantal' => 99];
        $stripeDunningOpen = $stripeDunningReminder = '1=1';
        $legalNotes = [];
        $topniveau = null;
        $brugernavn = 'reminder-test';
        $source = file_get_contents(__DIR__ . '/../../debitor/ny_rykker.php');
        if (!function_exists('find_rente')) {
            eval(substr($source, strpos($source, 'function find_rente')));
        }
        $start = strpos($source, '$konto_antal =');
        $end = strpos($source, 'if ($topniveau) $legalNotes');
        $body = substr($source, $start, $end - $start);
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }, E_WARNING | E_NOTICE);
        try {
            eval($body);
        } finally {
            restore_error_handler();
            $_GET = $before;
        }
        $rows = pg_fetch_all(StripeTestEnvironment::query("SELECT konto_id,art,ref,tidspkt FROM ordrer WHERE art='R1' ORDER BY konto_id"));
        $this->assertCount(2, $rows);
        $this->assertSame([$first, $second], array_map('intval', array_column($rows, 'konto_id')));
        $this->assertSame('', $rows[1]['ref']);
        $this->assertNotEmpty($rows[0]['tidspkt']);
        $this->assertCount(1, $legalNotes);
        $this->assertStringContainsString('999999999', $legalNotes[0]);
        $this->assertSame('2', pg_fetch_result(StripeTestEnvironment::query("SELECT count(*) FROM ordrelinjer WHERE vare_id > 0"), 0, 0));
    }
}
