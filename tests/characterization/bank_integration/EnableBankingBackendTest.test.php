<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/bank_integration/EnableBankingBackendTest.test.php --- ver 5.1.1 --- 2026-11-19 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// 20261006 CDX/LH Created fixture-based backend acceptance coverage.
// 20261007 CDX/LH Added regression coverage for sandbox account matching, delivery, settings and page feedback.
// 20261007 CDX/LH Covered fix-round-two approval, tenant binding, native writes, precision, dates, journals and worker failures.
// 20261007 CDX/LH Covered fiscal-year destinations, frozen review fingerprints, mysqli text, long references and simulation locks.
// 20261022 CDX/LH Cover permanent hash ambiguity, journal id reuse, abandoned staging and closed ledger accounts.
// 20261028 CDX/LH Cover manual picking, auto fences, pause, reconnect, revocation and worker migration.
// 20261028 CDX/LH Cover period recovery, waiting status, stale feed levels and worker heartbeats.
// 20261028 CL/LH Restored possible duplicates stay in review; stale only when the scheduled fetch is overdue.
// 20261028 CDX/LH Cover large child output and failure exit status.
// 20261028 CL/LH Cover the rendered pick list, import switch, pause, rights and disconnect texts.
// 20261028 CL/LH Cover that a paused account with automatic import promises nothing before Genoptag.
// 20261028 CDX/LH Cover stopped access on resume and safe authorization-specific errors with support codes.
// 20261116 CL/LH Pull model: tests of bank-owned journals, automatic import, the journal line cap and the bogfor.php lock are gone; picks go through ebImportIntoJournal() into a given journal (vouchers, positions, links, locks, posted and simulated journals, fiscal year, idempotency, foreign ids), and the feed renders with and without a journal.
// 20261116 CL/LH Review fixes: posting and import serialize on the journal header (both directions, real processes), import after an account reset, ISO-8859-15 bank text.
// 20261117 CL/LH CodeRabbit fixes: consent cleanup, reference "0", value-date overlap hint, re-dated pre-cutover line, due re-check, change approval bound to the shown change; child helper keeps the first exit status and stops a stuck child.
// 20261117 CL/LH A missing key file after the bank session is created still closes the consent at the bank.
// 20261118 CL/LH A renewal does not close the replaced consent at the bank (it broke the new consent in the Nordea sandbox).
// 20261118 CL/LH Disconnecting clears the account's old fetch error.
// 20261118 CL/LH UI pass: Sæt på pause / Genoptag and Fjern bankforbindelse (with the waiting count) are checked on the accounts page.
//                The support code sits in a title attribute, dates are dd-mm-åååå text fields, the journal button is named Bankposteringer, and the expired notice replaces the removed "Næste hentning" row.
// 20261118 CL/LH The journal's bank status: accounts named ledger-first in the tooltip, the short label ("2 nye", "ajour", "hentning forsinket", "forny adgang", "fejl") and the link to the feed on the account.
// 20261118 CL/LH CodeRabbit: a manual fetch reports its own new_count and the cooldown reason (not for a held lease); action required reads "forny adgang" and the link opens the most urgent account.
// 20261119 CL/LH Marian's Nordea test: the payee name shows once in the text, and each day is listed and picked in the bank's own order from its balances.
//                Switching the company on says "henter igen" only with an account in use, and survives a failing lookup; disconnect also clears last_error.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../bank_integration/includes/ebClient.php';
require_once __DIR__ . '/support/EbTestEnv.php';

final class EnableBankingBackendTest extends TestCase
{
    private int $accountId;
    private int $connectionId;
    private int $ledger;
    private int $otherLedger;
    private ?int $journalId = null;

    /** @return void */
    public static function setUpBeforeClass(): void
    {
        if ($reason = CharacterizationEnv::unavailableReason()) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant();
        // Child processes (pages, worker, legacy scripts) find the UUID-owned test database and its session here.
        putenv('SALDI_EB_TEST_DB=' . CharacterizationEnv::testDb());
        putenv('SALDI_EB_SESSION_ID=' . CharacterizationEnv::sessionId());
        EbTestKeys::create();
        ebTestConnect();
        require_once __DIR__ . '/../../../bank_integration/includes/ebConnections.php';
        require_once __DIR__ . '/../../../includes/opdat_func/ebSchemaInstall.php';
        ebEnsureSchema();
        // Journal pages normally install these legacy editor columns on first use.
        foreach (['kassekladde', 'tmpkassekl'] as $table) {
            foreach (['debetvat', 'kreditvat'] as $column) {
                ebWrite('ALTER TABLE ' . $table . ' ADD COLUMN IF NOT EXISTS ' . $column . ' VARCHAR(10)');
            }
        }
    }

    /** @return void */
    public static function tearDownAfterClass(): void
    {
        if (isset($GLOBALS['connection'])) {
            pg_close($GLOBALS['connection']);
            unset($GLOBALS['connection']);
            EbTestKeys::cleanup();
        }
        CharacterizationEnv::teardownTenant();
        putenv('SALDI_EB_TEST_DB');
        putenv('SALDI_EB_SESSION_ID');
    }

    protected function setUp(): void
    {
        foreach (array_keys(ebSchemaTables()) as $table) {
            ebWrite('TRUNCATE ' . $table . ' RESTART IDENTITY');
        }
        ebWrite('DELETE FROM documents');
        ebWrite('DELETE FROM tmpkassekl');
        ebWrite('DELETE FROM kassekladde');
        ebWrite('DELETE FROM kladdeliste');
        ebWrite('DELETE FROM transaktioner');
        if (ebTableExists('moms_periode_luk')) {
            ebWrite('DELETE FROM moms_periode_luk');
        }
        ebWrite("DELETE FROM settings WHERE var_grp='enable_banking' OR var_name='baseCurrency'");
        $year = ebRow("SELECT MAX(kodenr) AS id FROM grupper WHERE art='RA'");
        $GLOBALS['regnaar'] = (int)$year['id'];
        ebWrite("UPDATE grupper SET box1='1', box2=" . ebSql(date('Y')) . ", box3='12', box4=" . ebSql(date('Y')) . ", box5='on' WHERE art='RA' AND kodenr=" . (int)$year['id']);
        $ledgers = ebRows('SELECT kontonr FROM kontoplan WHERE regnskabsaar=' . (int)$year['id'] . " AND kontotype='D' AND (moms IS NULL OR moms='') ORDER BY kontonr LIMIT 2");
        $this->assertCount(2, $ledgers);
        $this->ledger = (int)$ledgers[0]['kontonr'];
        $this->otherLedger = (int)$ledgers[1]['kontonr'];
        $this->connectionId = ebInsert('bank_connections', ['provider' => 'enablebanking', 'environment' => 'SANDBOX',
            'aspsp_name' => 'Fixture Bank', 'aspsp_country' => 'DK', 'psu_type' => 'business', 'session_enc' => oauthEncrypt(bin2hex(random_bytes(16))),
            'session_hash' => hash('sha256', random_bytes(16)), 'status' => 'active', 'valid_until' => date('Y-m-d H:i:s', time() + 86400),
            'created_by' => 'chartest', 'created_at' => ebNow(), 'updated_at' => ebNow()]);
        $this->accountId = $this->account($this->connectionId);
        ebSetCompanyEnabled(true, 'chartest');
        unset($GLOBALS['ebActionAccountId']);
        $this->journalId = null;
    }

    private function account(int $connectionId, string $hash = 'fixture-shared-hash'): int
    {
        $id = ebInsert('bank_accounts', ['connection_id' => $connectionId, 'identification_hash' => $hash,
            'display_name' => 'Driftskonto', 'display_identity' => 'DK50 **** **** 1234', 'currency' => 'DKK', 'cash_account_type' => 'CACC',
            'state' => 'active', 'ledger_account' => $this->ledger, 'import_from' => date('Y-m-01'),
            'created_at' => ebNow(), 'updated_at' => ebNow()]);
        ebInsert('bank_account_aliases', ['account_id' => $id, 'identification_hash' => $hash]);
        ebInsert('bank_account_sessions', ['account_id' => $id, 'connection_id' => $connectionId,
            'account_uid' => 'fixture-uid-' . $id, 'active' => 1, 'created_at' => ebNow()]);
        return $id;
    }

    /**
     * An open kassekladde, numbered like the journal list does (MAX(id)+1).
     * @param array<string, string|int|null> $fields Header fields to override (bogfort, hvem, tidspkt).
     * @return int kladdeliste.id
     */
    private function journal(array $fields = []): int
    {
        $id = (int)ebRow('SELECT COALESCE(MAX(id),0)+1 AS id FROM kladdeliste')['id'];
        return ebInsert('kladdeliste', $fields + ['id' => $id, 'kladdenote' => 'Bank test', 'kladdedate' => ebToday(),
            'bogfort' => '-', 'oprettet_af' => 'chartest', 'hvem' => '', 'tidspkt' => '']);
    }

    /**
     * Picks every row of the account that is under Nye (or a legacy after_review row) into one journal, as a user would.
     * @return array{imported:int, skipped:list<array{id:int, reason:string}>}
     */
    private function importPending(?int $accountId = null, ?int $journal = null): array
    {
        $accountId = $accountId ?? $this->accountId;
        $this->journalId = $journal ?? $this->journalId ?? $this->journal();
        $ids = array_map('intval', array_column(ebRows('SELECT id FROM bank_transactions WHERE account_id=' . (int)$accountId
            . " AND link_id IS NULL AND (review_state='pending' OR (review_state='review' AND review_reason='after_review')) ORDER BY id"), 'id'));
        $out = ['imported' => 0, 'skipped' => []];
        foreach (array_chunk($ids, 200) as $chunk) {
            $result = ebImportIntoJournal($this->journalId, $accountId, $chunk, 'chartest');
            $out['imported'] += $result['imported'];
            $out['skipped'] = array_merge($out['skipped'], $result['skipped']);
        }
        return $out;
    }

    /**
     * Picking rows that are not under Nye imports nothing and reports them as not_pending.
     * @param list<int> $ids bank_transactions ids.
     */
    private function assertNotPickable(array $ids): void
    {
        $before = ebRows('SELECT * FROM bank_transactions ORDER BY id');
        $result = ebImportIntoJournal($this->journal(), $this->accountId, $ids, 'chartest');
        $this->assertSame(0, $result['imported']);
        $this->assertSame(array_map(static fn(int $id): array => ['id' => $id, 'reason' => 'not_pending'], $ids), $result['skipped']);
        $this->assertSame($before, ebRows('SELECT * FROM bank_transactions ORDER BY id'));
    }

    /** @return array{transactions:list<array<string,mixed>>, continuation_key:?string} */
    private function page(): array
    {
        $page = json_decode(file_get_contents(__DIR__ . '/../../fixtures/enablebanking/booked.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($page['transactions'] as &$tx) {
            $tx['booking_date'] = ebToday();
            $tx['value_date'] = ebToday();
            $tx['transaction_amount']['amount'] = substr($tx['transaction_amount']['amount'], 0, -1);
        }
        unset($tx);
        return $page;
    }

    private function client(array $steps): EnableBankingClient
    {
        return new EnableBankingClient(EbTestKeys::config(), new EbFixtureTransport($steps));
    }

    private function step(array|string $body, int $status = 200, string $path = '/transactions?', string $method = 'GET'): array
    {
        return ['method' => $method, 'path' => $path, 'status' => $status, 'body' => $body];
    }

    private function persist(array $raw): array
    {
        $refs = $without = [];
        foreach ($raw as $tx) {
            $n = ebNormalizeTransaction($tx);
            if ($n['entry_reference'] === null) {
                $without[] = $n;
            } else {
                $refs[] = $n;
            }
        }
        return ebPersistTransactions($this->accountId, $refs, $without, 0);
    }

    private function rejects(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected an EbUserError');
        } catch (EbUserError $e) {
            $this->assertNotSame('', $e->getMessage());
        }
    }

    /** @return void */
    public function test_schema_and_company_scope_are_idempotent(): void
    {
        ebEnsureSchema();
        ebEnsureSchema();
        $this->assertTrue(ebSchemaReady());
        ebSetCompanyEnabled(false, 'chartest');
        ebInsert('settings', ['var_grp' => 'enable_banking', 'var_name' => 'enabled', 'user_id' => 7, 'var_value' => 'on']);
        $this->assertFalse(ebCompanyEnabled());
        ebSetCompanyEnabled(true, 'chartest');
        $this->assertTrue(ebCompanyEnabled());
        $this->assertCount(1, ebRows("SELECT id FROM settings WHERE var_grp='enable_banking' AND user_id=0"));
    }

    /** @return void */
    public function test_decimal_normalization_descriptions_dates_and_utf8(): void
    {
        [$credit, $debit] = $this->page()['transactions'];
        $credit['transaction_amount']['amount'] = '125.123';
        $debit['transaction_amount']['amount'] = '42.321';
        $n = ebNormalizeTransaction($credit);
        $this->assertSame('125.123', $n['amount']);
        $this->assertSame('Køber Betaling oktober', $n['description']);
        $this->assertSame('-42.321', ebNormalizeTransaction($debit)['amount']);
        $credit['transaction_amount']['amount'] = '+000125.120';
        $this->assertSame('125.12', ebNormalizeTransaction($credit)['amount']);
        $credit['transaction_amount']['amount'] = '-12.300';
        $this->assertFalse(ebNormalizeTransaction($credit)['valid_amount']);
        unset($credit['credit_debit_indicator']);
        $this->assertSame('-12.3', ebNormalizeTransaction($credit)['amount']);
        foreach (['1,23', '1.2345', '1e2', '1000000000000', 'NaN'] as $amount) {
            $credit['transaction_amount']['amount'] = $amount;
            $this->assertFalse(ebNormalizeTransaction($credit)['valid_amount']);
            $this->assertSame('0', ebNormalizeTransaction($credit)['amount']);
        }
        $credit['debtor']['name'] = str_repeat('ø', 140);
        $credit['creditor']['name'] = str_repeat('ø', 140);
        $credit['remittance_information'] = [str_repeat('å', 300)];
        $n = ebNormalizeTransaction($credit);
        $this->assertSame(255, mb_strlen($n['description']));
        $this->assertTrue(mb_check_encoding($n['description'], 'UTF-8'));
        $this->assertNull(ebDate('2026-02-30'));
        $credit['debtor'] = $credit['creditor'] = [];
        $credit['remittance_information'] = [];
        $this->assertSame('', ebNormalizeTransaction($credit)['description']);
    }

    /** @return void */
    public function test_jwt_signature_claims_and_refresh_cache(): void
    {
        $now = 1700000000;
        $client = new EnableBankingClient(EbTestKeys::config(), new EbFixtureTransport([]), static function () use (&$now): int { return $now; });
        $first = $client->jwt();
        [$h, $p, $s] = explode('.', $first);
        $decode = static fn(string $value): string => base64_decode(strtr($value, '-_', '+/'));
        $header = json_decode($decode($h), true);
        $payload = json_decode($decode($p), true);
        $this->assertSame(['typ' => 'JWT', 'alg' => 'RS256', 'kid' => 'fixture-application'], $header);
        $this->assertSame('enablebanking.com', $payload['iss']);
        $this->assertSame('api.enablebanking.com', $payload['aud']);
        $this->assertSame($now + 3600, $payload['exp']);
        $this->assertSame(1, openssl_verify($h . '.' . $p, $decode($s), EbTestKeys::$publicKey, OPENSSL_ALGO_SHA256));
        $now += 3299;
        $this->assertSame($first, $client->jwt());
        $now++;
        $this->assertNotSame($first, $client->jwt());
    }

    /** @return void */
    public function test_configuration_rejects_installation_key_and_honors_env_precedence(): void
    {
        $vars = ['APP_ID', 'KEY_PATH', 'REDIRECT_URL', 'ENV'];
        $previous = [];
        foreach ($vars as $var) {
            $previous[$var] = getenv('SALDI_ENABLEBANKING_' . $var);
        }
        try {
            putenv('SALDI_ENABLEBANKING_APP_ID=fixture-env');
            putenv('SALDI_ENABLEBANKING_KEY_PATH=' . EbTestKeys::$privatePath);
            putenv('SALDI_ENABLEBANKING_REDIRECT_URL=https://example.invalid/callback');
            putenv('SALDI_ENABLEBANKING_ENV=SANDBOX');
            $GLOBALS['enableBankingAppId'] = 'fixture-global';
            $this->assertTrue(ebFeatureAvailable());
            $this->assertSame('fixture-env', ebConfig()['app_id']);
            putenv('SALDI_ENABLEBANKING_KEY_PATH=' . __FILE__);
            $this->assertFalse(ebFeatureAvailable());
            $this->assertStringContainsString('uden for', ebConfig()['problem']);
            putenv('SALDI_ENABLEBANKING_APP_ID=');
            $this->assertSame('fixture-global', ebConfig()['app_id']);
        } finally {
            foreach ($previous as $var => $value) {
                putenv('SALDI_ENABLEBANKING_' . $var . ($value === false ? '' : '=' . $value));
            }
            unset($GLOBALS['enableBankingAppId']);
        }
    }

    /** @return void */
    public function test_non_json_and_wrong_shape_are_transient_and_errors_are_redacted(): void
    {
        foreach (['not json', ['transactions' => 'invalid'], ['transactions' => [], 'continuation_key' => 9]] as $body) {
            try {
                $this->client([$this->step($body)])->transactions('fixture-uid', ebToday(), ebToday(), null);
                $this->fail('Malformed response accepted');
            } catch (EbApiException $e) {
                $this->assertSame('INVALID_RESPONSE', $e->errorCode);
                $this->assertSame('transient', $e->kind());
            }
        }
        try {
            $this->client([$this->step(['error' => 'EXPIRED_SESSION', 'message' => 'sensitive body DK50000000001234'], 401)])->transactions('fixture-uid', ebToday(), ebToday(), null);
        } catch (EbApiException $e) {
            $this->assertSame('consent', $e->kind());
            $this->assertStringNotContainsString('DK50000000001234', $e->getMessage());
            $this->assertStringNotContainsString('sensitive', $e->getMessage());
        }
        $this->assertSame([], ebFilterPsuHeaders(['Psu-Ip-Address' => '127.0.0.1'], ['Psu-Ip-Address', 'Psu-User-Agent']));
    }

    /** @return void */
    public function test_same_window_twice_stores_one_row_per_reference_and_a_pick_writes_correct_bank_sides(): void
    {
        $page = $this->page();
        $client = $this->client([$this->step($page), $this->step($page)]);
        $this->assertSame('ok', ebSyncAccount($this->accountId, 'schedule', [], $client)['status']);
        $this->assertSame('ok', ebSyncAccount($this->accountId, 'schedule', [], $client)['status']);
        $this->assertCount(2, ebRows('SELECT id FROM bank_transactions'));
        // A fetch only stores lines; they reach a journal when a user picks them.
        $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
        $this->assertCount(0, ebRows('SELECT id FROM bank_import_links'));
        $this->assertSame(2, $this->importPending()['imported']);
        $this->assertCount(2, ebRows('SELECT id FROM bank_import_links'));
        $lines = ebRows('SELECT * FROM kassekladde ORDER BY id');
        $this->assertCount(2, $lines);
        $this->assertSame((string)$this->ledger, $lines[0]['debet']);
        $this->assertSame('0', $lines[0]['kredit']);
        $this->assertSame('125.120', $lines[0]['amount']);
        $this->assertSame((string)$this->ledger, $lines[1]['kredit']);
        $this->assertSame('0', $lines[1]['debet']);
        $this->assertSame('42.320', $lines[1]['amount']);
        foreach ($lines as $line) {
            $this->assertSame(ebToday(), $line['transdate']);
            $this->assertSame('', $line['faktura']);
            $this->assertSame('', $line['momsfri']);
            $this->assertSame('F', $line['d_type']);
            $this->assertSame('F', $line['k_type']);
            $this->assertSame('0', $line['valuta']);
        }
        $this->assertNotSame($lines[0]['bilag'], $lines[1]['bilag']);
        $this->assertCount(0, ebRows('SELECT id FROM tmpkassekl'));
        $this->assertCount(0, ebRows('SELECT id FROM transaktioner'));
    }

    /** @return void */
    public function test_identical_no_reference_payments_and_later_extra_occurrence(): void
    {
        $raw = $this->page()['transactions'][0];
        unset($raw['entry_reference']);
        $raw['booking_date'] = date('Y-m-d', strtotime('-1 day'));
        ebUpdate('bank_accounts', $this->accountId, ['import_from' => min(date('Y-m-01'), $raw['booking_date'])]);
        $this->assertSame(2, $this->persist([$raw, $raw])['new']);
        $this->assertSame(2, $this->importPending()['imported']);
        $this->assertSame(0, $this->persist([$raw, $raw])['new']);
        $this->assertSame(1, $this->persist([$raw, $raw, $raw])['new']);
        $this->assertSame(0, $this->importPending()['imported']);
        $tx = ebRow('SELECT * FROM bank_transactions ORDER BY id DESC LIMIT 1');
        $this->assertSame('possible_duplicate', $tx['review_reason']);
        $this->assertSame('review', $tx['review_state']);
        $this->assertCount(3, ebRows('SELECT id FROM bank_transactions WHERE identical_group=1'));
        $this->assertSame(0, $this->persist([$raw])['new']);
        $this->assertCount(3, ebRows('SELECT id FROM bank_transactions'));
        ebTransactionAction((int)$tx['id'], 'import', 'chartest');
        $this->assertSame(1, $this->importPending()['imported']);
    }

    /** @return void */
    public function test_restored_possible_duplicate_returns_to_review_and_is_not_pickable(): void
    {
        $raw = $this->page()['transactions'][0];
        unset($raw['entry_reference']);
        $raw['booking_date'] = date('Y-m-d', strtotime('-1 day'));
        ebUpdate('bank_accounts', $this->accountId, ['import_from' => min(date('Y-m-01'), $raw['booking_date'])]);
        $this->persist([$raw, $raw]);
        $this->assertSame(2, $this->importPending()['imported']);
        $this->assertSame(1, $this->persist([$raw, $raw, $raw])['new']);
        $tx = ebRow("SELECT * FROM bank_transactions WHERE review_reason='possible_duplicate'");
        $this->assertNotNull($tx);
        ebTransactionAction((int)$tx['id'], 'ignore', 'chartest');
        $result = ebTransactionAction((int)$tx['id'], 'restore', 'chartest');
        $this->assertSame('Posteringen ligger nu under Til gennemsyn.', $result['message']);
        $row = ebRow('SELECT review_state, review_reason FROM bank_transactions WHERE id=' . (int)$tx['id']);
        $this->assertSame('review', $row['review_state']);
        $this->assertSame('possible_duplicate', $row['review_reason']);
        $this->assertSame(0, $this->importPending()['imported']);
        $this->assertCount(2, ebRows('SELECT id FROM kassekladde'));
    }

    /** @return void */
    public function test_no_reference_occurrences_are_counted_across_pages_and_empty_pages_followed(): void
    {
        $raw = $this->page()['transactions'][0];
        unset($raw['entry_reference']);
        $raw['booking_date'] = date('Y-m-d', strtotime('-1 day'));
        ebUpdate('bank_accounts', $this->accountId, ['import_from' => min(date('Y-m-01'), $raw['booking_date'])]);
        $script = [$this->step(['transactions' => [$raw], 'continuation_key' => 'fixture-page-2']),
            $this->step(['transactions' => [], 'continuation_key' => 'fixture-page-3']),
            $this->step(['transactions' => [$raw], 'continuation_key' => null])];
        $result = ebSyncAccount($this->accountId, 'schedule', [], $this->client($script));
        $this->assertSame('ok', $result['status']);
        $this->assertSame('3', ebRow('SELECT pages FROM bank_sync_runs')['pages']);
        $this->assertCount(2, ebRows('SELECT id FROM bank_transactions'));
        $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
    }

    /** @return void */
    public function test_failed_second_page_keeps_reference_rows_but_not_checkpoint_or_no_reference_buffer(): void
    {
        $page = $this->page();
        $noRef = $page['transactions'][0];
        unset($noRef['entry_reference']);
        $noRef['booking_date'] = date('Y-m-d', strtotime('-1 day'));
        ebUpdate('bank_accounts', $this->accountId, ['import_from' => min(date('Y-m-01'), $noRef['booking_date'])]);
        $first = ['transactions' => [$page['transactions'][0], $noRef], 'continuation_key' => 'fixture-next'];
        $failed = ebSyncAccount($this->accountId, 'schedule', [], $this->client([$this->step($first), $this->step(['error' => 'ASPSP_TIMEOUT'], 500)]));
        $this->assertSame('failed', $failed['status']);
        $this->assertNull(ebRow('SELECT fetched_through FROM bank_accounts WHERE id=' . $this->accountId)['fetched_through']);
        $this->assertCount(1, ebRows('SELECT id FROM bank_transactions'));
        $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
        $second = ['transactions' => [$page['transactions'][1]], 'continuation_key' => null];
        $done = ebSyncAccount($this->accountId, 'schedule', [], $this->client([$this->step($first), $this->step($second)]));
        $this->assertSame('ok', $done['status']);
        $this->assertSame(ebToday(), ebRow('SELECT fetched_through FROM bank_accounts WHERE id=' . $this->accountId)['fetched_through']);
        $this->assertCount(3, ebRows('SELECT id FROM bank_transactions'));
        $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
    }

    /** @return void */
    public function test_continuation_restart_is_bounded_and_does_not_duplicate_reference_rows(): void
    {
        $first = $this->page();
        $first['continuation_key'] = 'fixture-next';
        $error = $this->step(['error' => 'WRONG_CONTINUATION_KEY'], 400);
        $client = $this->client([$this->step($first), $error, $this->step($this->page())]);
        $this->assertSame('ok', ebSyncAccount($this->accountId, 'schedule', [], $client)['status']);
        $this->assertCount(2, ebRows('SELECT id FROM bank_transactions'));
        $this->assertSame('failed', ebSyncAccount($this->accountId, 'schedule', [], $this->client([$error, $error]))['status']);
    }

    /** @return void */
    public function test_pending_booked_reversal_missing_date_non_dkk_and_bad_amount_states(): void
    {
        $raw = $this->page()['transactions'][0];
        $pending = $raw;
        $pending['status'] = 'PDNG';
        $this->assertSame(1, $this->persist([$pending])['skipped']);
        $this->assertCount(0, ebRows('SELECT id FROM bank_transactions'));
        $reverse = $raw;
        $reverse['entry_reference'] = 'fixture-reversal';
        $reverse['credit_debit_indicator'] = 'DBIT';
        $missing = $raw;
        $missing['entry_reference'] = 'fixture-missing-date';
        unset($missing['booking_date']);
        $eur = $raw;
        $eur['entry_reference'] = 'fixture-eur';
        $eur['transaction_amount']['currency'] = 'EUR';
        $bad = $raw;
        $bad['entry_reference'] = 'fixture-bad';
        $bad['transaction_amount']['amount'] = 'bad';
        $this->persist([$raw, $reverse, $missing, $eur, $bad]);
        $this->assertSame(2, $this->importPending()['imported']);
        $rows = ebRows("SELECT review_reason FROM bank_transactions WHERE review_state='review' ORDER BY id");
        $this->assertSame(['missing_date', 'unsupported_currency', 'bad_amount'], array_column($rows, 'review_reason'));
        $tx = ebRow("SELECT id FROM bank_transactions WHERE review_reason='missing_date'");
        ebTransactionAction((int)$tx['id'], 'import_value_date', 'chartest');
        $this->assertSame(1, $this->importPending()['imported']);
    }

    /** @return void */
    public function test_changed_import_never_changes_journal_and_acknowledgement_is_stable(): void
    {
        $raw = $this->page()['transactions'][0];
        $this->persist([$raw]);
        $this->importPending();
        $before = ebRow('SELECT * FROM kassekladde');
        $raw['transaction_amount']['amount'] = '200.123';
        $this->assertSame(1, $this->persist([$raw])['changed']);
        $tx = ebRow('SELECT * FROM bank_transactions');
        $this->assertSame('changed_after_import', $tx['review_reason']);
        $this->assertSame($before, ebRow('SELECT * FROM kassekladde'));
        $this->rejects(fn() => ebTransactionAction((int)$tx['id'], 'import', 'chartest'));
        // Approval binds to the change the user saw: none, or an older one, is refused; the bank's newest change stays in review.
        $shown = hash('sha256', (string)$tx['changed_payload']);
        $this->rejects(fn() => ebTransactionAction((int)$tx['id'], 'accept_change', 'chartest'));
        $raw['transaction_amount']['amount'] = '300.12';
        $this->assertSame(1, $this->persist([$raw])['changed']);
        $this->rejects(fn() => ebTransactionAction((int)$tx['id'], 'accept_change', 'chartest', $shown));
        $this->assertSame('changed_after_import', ebRow('SELECT review_reason FROM bank_transactions')['review_reason']);
        $tx = ebRow('SELECT * FROM bank_transactions');
        ebTransactionAction((int)$tx['id'], 'accept_change', 'chartest', hash('sha256', (string)$tx['changed_payload']));
        $this->assertSame(0, $this->persist([$raw])['changed']);
        $this->assertSame('imported', ebRow('SELECT review_state FROM bank_transactions')['review_state']);
        $this->assertSame($before, ebRow('SELECT * FROM kassekladde'));
    }

    /** @return void */
    public function test_import_refuses_another_users_open_journal_and_accepts_own_and_expired_locks(): void
    {
        $this->persist($this->page()['transactions']);
        $journal = $this->journal();
        ebUpdate('kladdeliste', $journal, ['hvem' => 'another-user', 'tidspkt' => microtime()]);
        $before = ebRows('SELECT * FROM bank_transactions ORDER BY id');
        $this->rejects(fn() => ebImportIntoJournal($journal, $this->accountId, [1, 2], 'chartest'));
        $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
        $this->assertCount(0, ebRows('SELECT id FROM bank_import_links'));
        $this->assertSame($before, ebRows('SELECT * FROM bank_transactions ORDER BY id'));
        $this->assertCount(0, ebRows("SELECT id FROM bank_audit_log WHERE action='import_selected'"));
        // Staged lines of a save are never touched; an expired lock (older than an hour) does not block.
        ebWrite('INSERT INTO tmpkassekl (id, kladde_id, lobenr) VALUES (0,' . $journal . ',1)');
        ebUpdate('kladdeliste', $journal, ['hvem' => 'another-user', 'tidspkt' => '0.1 ' . (time() - 7200)]);
        $this->assertSame(1, ebImportIntoJournal($journal, $this->accountId, [1], 'chartest')['imported']);
        $this->assertCount(1, ebRows('SELECT id FROM tmpkassekl WHERE kladde_id=' . $journal));
        // The user's own edit lock (set by the Bankforbindelse button's save) is accepted.
        ebUpdate('kladdeliste', $journal, ['hvem' => 'chartest', 'tidspkt' => microtime()]);
        $this->assertSame(1, ebImportIntoJournal($journal, $this->accountId, [2], 'chartest')['imported']);
        $this->assertCount(2, ebRows('SELECT id FROM kassekladde WHERE kladde_id=' . $journal));
        $this->assertCount(1, ebRows('SELECT id FROM tmpkassekl WHERE kladde_id=' . $journal));
    }

    /** @return void */
    public function test_stale_save_calls_real_opdater_and_keeps_new_bank_lines(): void
    {
        $journal = $this->journal();
        $manual = ebInsert('kassekladde', ['kladde_id' => $journal, 'bilag' => 1, 'transdate' => ebToday(),
            'beskrivelse' => 'Manual old', 'debet' => $this->ledger, 'kredit' => $this->otherLedger, 'd_type' => 'F', 'k_type' => 'F', 'amount' => '10']);
        $this->persist($this->page()['transactions']);
        $this->assertSame(2, $this->importPending(null, $journal)['imported']);
        ebWrite("INSERT INTO tmpkassekl (id, kladde_id, lobenr, bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, amount,
            momsfri, afd, ansat, faktura, projekt, valuta, forfaldsdate, betal_id, debetvat, kreditvat)
            VALUES ($manual, $journal, 1, '1', " . ebSql(date('d-m-Y')) . ", 'Manual edited', 'F', " . ebSql((string)$this->ledger) . ", 'F', " . ebSql((string)$this->otherLedger) . ", '10,00', '', '0', '', '', '', 'DKK', '', '', '', '')");
        $result = CharacterizationEnv::runChild(__DIR__ . '/support/run_legacy.php', ['save', $journal]);
        $this->assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
        $this->assertSame('Manual edited', ebRow('SELECT beskrivelse FROM kassekladde WHERE id=' . $manual)['beskrivelse']);
        $this->assertCount(3, ebRows('SELECT id FROM kassekladde'));
        $this->assertCount(2, ebRows('SELECT id FROM bank_import_links'));
    }

    /** @return void */
    public function test_deleted_line_is_not_picked_again_until_moved_back_to_new_and_gets_a_new_link(): void
    {
        $this->persist([$this->page()['transactions'][0]]);
        $this->importPending();
        $link = ebRow('SELECT * FROM bank_import_links');
        ebWrite('DELETE FROM kassekladde WHERE id=' . (int)$link['kassekladde_id']);
        $this->assertSame(['deleted' => 1, 'posted' => 0], ebReconcileLinks($this->accountId));
        $this->persist([$this->page()['transactions'][0]]);
        $this->assertSame(0, $this->importPending()['imported']);
        ebTransactionAction((int)$link['transaction_id'], 'reimport', 'chartest');
        $this->assertSame(1, $this->importPending()['imported']);
        $links = ebRows('SELECT * FROM bank_import_links ORDER BY id');
        $this->assertSame('replaced', $links[0]['disposition']);
        $this->assertSame('active', $links[1]['disposition']);
        $this->assertNotSame($links[0]['kassekladde_id'], $links[1]['kassekladde_id']);
    }

    /** @return void */
    public function test_receipt_and_import_link_survive_real_posting_and_copy_has_no_second_link(): void
    {
        $raw = $this->page()['transactions'][0];
        $raw['transaction_amount']['amount'] = '125.120';
        $this->persist([$raw]);
        $this->importPending();
        $link = ebRow('SELECT * FROM bank_import_links');
        $lineId = (int)$link['kassekladde_id'];
        ebUpdate('kassekladde', $lineId, ['kredit' => $this->otherLedger]);
        ebInsert('documents', ['global_id' => 1, 'filename' => 'fixture-receipt.pdf', 'filepath' => '/finance/fixture',
            'source' => 'kassekladde', 'source_id' => $lineId, 'timestamp' => (string)time(), 'user_id' => 1]);
        $copied = CharacterizationEnv::runChild(__DIR__ . '/support/run_legacy.php', ['copy', $link['kladde_id'], $lineId]);
        $this->assertSame(0, $copied['exit'], $copied['stderr'] . $copied['stdout']);
        $this->assertCount(2, ebRows('SELECT id FROM kassekladde'));
        $this->assertCount(1, ebRows('SELECT id FROM bank_import_links'));
        $result = CharacterizationEnv::runChild(__DIR__ . '/../support/run_bogfor_page.php', ['bogfor', $link['kladde_id'], CharacterizationEnv::sessionId()]);
        $this->assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
        $this->assertSame('V', ebRow('SELECT bogfort FROM kladdeliste WHERE id=' . (int)$link['kladde_id'])['bogfort']);
        $this->assertSame(['deleted' => 0, 'posted' => 1], ebReconcileLinks($this->accountId));
        $this->assertNotNull(ebRow("SELECT id FROM documents WHERE source='kassekladde' AND source_id=" . $lineId));
        $this->assertNotNull(ebRow('SELECT id FROM kassekladde WHERE id=' . $lineId));
    }

    /** @return void */
    public function test_import_refuses_posted_and_simulated_journals_and_unknown_journals_writing_nothing(): void
    {
        $this->persist($this->page()['transactions']);
        $before = ebRows('SELECT * FROM bank_transactions ORDER BY id');
        foreach (['V', 'S', '!'] as $state) {
            $journal = $this->journal(['bogfort' => $state]);
            $this->rejects(fn() => ebImportIntoJournal($journal, $this->accountId, [1, 2], 'chartest'));
        }
        $this->rejects(fn() => ebImportIntoJournal(2147483647, $this->accountId, [1, 2], 'chartest'));
        $this->assertSame($before, ebRows('SELECT * FROM bank_transactions ORDER BY id'));
        $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
        $this->assertCount(0, ebRows('SELECT id FROM bank_import_links'));
        $this->assertCount(0, ebRows("SELECT id FROM bank_audit_log WHERE action='import_selected'"));
        $this->assertNull(ebAccountStatus($this->accountId)['last_import_at']);
    }

    /** @return void */
    public function test_lines_outside_the_year_closed_period_or_ledger_stay_new_with_their_reason(): void
    {
        $raw = $this->page()['transactions'][0];
        $old = $raw;
        $old['booking_date'] = '1900-01-01';
        $old['entry_reference'] = 'fixture-old';
        ebUpdate('bank_accounts', $this->accountId, ['import_from' => '1900-01-01']);
        $this->persist([$old, $this->page()['transactions'][1]]);
        $result = $this->importPending();
        $this->assertSame(1, $result['imported']);
        $this->assertSame([['id' => 1, 'reason' => 'outside_fiscal_year']], $result['skipped']);
        $row = ebRow('SELECT review_state, review_reason, link_id FROM bank_transactions WHERE id=1');
        $this->assertSame(['review_state' => 'pending', 'review_reason' => 'outside_fiscal_year', 'link_id' => null], $row);
        $raw['entry_reference'] = 'fixture-ledger';
        ebUpdate('bank_accounts', $this->accountId, ['ledger_account' => 2147483647]);
        $this->persist([$raw]);
        $this->importPending();
        $this->assertSame('no_ledger_account', ebRow("SELECT review_reason FROM bank_transactions WHERE entry_reference='fixture-ledger'")['review_reason']);
        if (!ebTableExists('moms_periode_luk')) {
            ebWrite('CREATE TABLE moms_periode_luk (kalender_aar INT, kalender_maaned INT, status VARCHAR(20))');
        }
        ebWrite("INSERT INTO moms_periode_luk (kalender_aar, kalender_maaned, status) VALUES (" . (int)date('Y') . ',' . (int)date('m') . ",'closed')");
        $raw['entry_reference'] = 'fixture-closed';
        ebUpdate('bank_accounts', $this->accountId, ['ledger_account' => $this->ledger]);
        $this->persist([$raw]);
        $this->importPending();
        $closed = ebRow("SELECT review_state, review_reason FROM bank_transactions WHERE entry_reference='fixture-closed'");
        $this->assertSame(['review_state' => 'pending', 'review_reason' => 'closed_period'], $closed);
        $this->assertSame(3, ebCountsByState($this->accountId)['pending']);
        $this->assertSame(0, ebCountsByState($this->accountId)['review']);
    }

    private function state(string $state, array $overrides = []): array
    {
        $id = ebInsert('bank_auth_states', $overrides + ['state_hash' => hash('sha256', $state), 'user_id' => 1,
            'username' => 'chartest', 'environment' => 'SANDBOX', 'purpose' => 'connect', 'connection_id' => null,
            'aspsp_name' => 'Fixture Bank', 'aspsp_country' => 'DK', 'psu_type' => 'business',
            'created_at' => ebNow(), 'expires_at' => date('Y-m-d H:i:s', time() + 1800)]);
        return ebRow('SELECT * FROM bank_auth_states WHERE id=' . $id);
    }

    /** @return void */
    public function test_authorization_replay_expiry_other_user_and_unknown_state_are_rejected(): void
    {
        $state = bin2hex(random_bytes(32));
        $this->state($state);
        $this->rejects(fn() => ebConsumeAuthorization($state, 2));
        $this->assertSame('1', ebConsumeAuthorization($state, 1)['user_id']);
        $this->rejects(fn() => ebConsumeAuthorization($state, 1));
        $expired = bin2hex(random_bytes(32));
        $this->state($expired, ['expires_at' => date('Y-m-d H:i:s', time() - 1)]);
        $this->rejects(fn() => ebConsumeAuthorization($expired, 1));
        $this->rejects(fn() => ebConsumeAuthorization(bin2hex(random_bytes(32)), 1));
        $this->rejects(fn() => ebTransactionAction(2147483647, 'ignore', 'chartest'));
        $this->persist($this->page()['transactions']);
        $tx = (int)ebRow('SELECT id FROM bank_transactions')['id'];
        $GLOBALS['ebActionAccountId'] = $this->account($this->connectionId, 'fixture-other-hash');
        $this->rejects(fn() => ebTransactionAction($tx, 'ignore', 'chartest'));
        $journal = $this->journal();
        $this->rejects(fn() => ebImportIntoJournal($journal, (int)$GLOBALS['ebActionAccountId'], [$tx], 'chartest'));
        $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
        unset($GLOBALS['ebActionAccountId']);
    }

    /** @return void */
    public function test_consent_that_cannot_be_stored_is_closed_at_the_bank(): void
    {
        $state = bin2hex(random_bytes(32));
        $this->state($state, []);
        $body = ['accounts' => [$this->resource('cleanup-primary', [str_repeat('x', 256)])], 'session_id' => 'orphan-session',
            'access' => ['valid_until' => date(DATE_RFC3339, time() + 86400)]];
        $connections = ebRows('SELECT id FROM bank_connections ORDER BY id');
        $transport = new EbFixtureTransport([$this->step($body, 200, '/sessions', 'POST'),
            $this->step(['message' => 'OK'], 200, '/sessions/orphan-session', 'DELETE')]);
        $client = new EnableBankingClient(EbTestKeys::config(), $transport);
        $row = ebConsumeAuthorization($state, 1);
        $this->rejects(fn() => ebCompleteAuthorization($client, $row, bin2hex(random_bytes(16)), 'chartest'));
        $this->assertSame($connections, ebRows('SELECT id FROM bank_connections ORDER BY id'));
        $this->assertSame(['POST', 'DELETE'], array_column($transport->requests, 'method'));
        $this->assertSame([], $transport->script);
    }

    /** @return void */
    public function test_consent_is_closed_when_the_key_file_is_missing(): void
    {
        $state = bin2hex(random_bytes(32));
        $this->state($state, []);
        $body = ['accounts' => [$this->resource('cleanup-key')], 'session_id' => 'keyless-session',
            'access' => ['valid_until' => date(DATE_RFC3339, time() + 86400)]];
        $connections = ebRows('SELECT id FROM bank_connections ORDER BY id');
        $transport = new EbFixtureTransport([$this->step($body, 200, '/sessions', 'POST'),
            $this->step(['message' => 'OK'], 200, '/sessions/keyless-session', 'DELETE')]);
        $client = new EnableBankingClient(EbTestKeys::config(), $transport);
        $row = ebConsumeAuthorization($state, 1);
        $key = oauthKeyPath();
        rename($key, $key . '.away');
        try {
            ebCompleteAuthorization($client, $row, bin2hex(random_bytes(16)), 'chartest');
            $this->fail('Expected the missing key file to stop the authorization');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('OAuth key file not found', $e->getMessage());
        } finally {
            rename($key . '.away', $key);
        }
        $this->assertSame($connections, ebRows('SELECT id FROM bank_connections ORDER BY id'));
        $this->assertSame(['POST', 'DELETE'], array_column($transport->requests, 'method'));
        $this->assertSame([], $transport->script);
    }

    /** @return void */
    public function test_reference_zero_value_date_hint_corrected_cutover_and_due_recheck(): void
    {
        // "0" is a reference like any other.
        $raw = $this->page()['transactions'][0];
        $raw['entry_reference'] = '0';
        $this->assertSame(1, $this->persist([$raw])['new']);
        // The overlap hint uses the accounting date, also when it comes from "Brug valørdato".
        $tx = ebRow('SELECT * FROM bank_transactions');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        ebUpdate('bank_transactions', (int)$tx['id'], ['booking_date' => null, 'date_override' => $yesterday, 'review_state' => 'pending']);
        $amount = ltrim($tx['amount'], '-');
        $journal = $this->journal();
        ebInsert('kassekladde', ['kladde_id' => $journal, 'transdate' => $yesterday, 'amount' => $amount, 'bilag' => 1,
            'debet' => str_starts_with($tx['amount'], '-') ? $this->otherLedger : $this->ledger,
            'kredit' => str_starts_with($tx['amount'], '-') ? $this->ledger : $this->otherLedger, 'd_type' => 'F', 'k_type' => 'F']);
        $this->assertArrayHasKey((int)$tx['id'], ebOverlapHints($this->accountId, [(int)$tx['id']]));
        // A line before the start date that the bank re-dates past it becomes pickable.
        $today = ebToday();
        ebUpdate('bank_accounts', $this->accountId, ['import_from' => $today]);
        $old = $this->page()['transactions'][1];
        $old['entry_reference'] = 'redated';
        $old['booking_date'] = $yesterday;
        $this->persist([$old]);
        $this->assertSame('pre_cutover', ebRow("SELECT review_state FROM bank_transactions WHERE booking_date=" . ebSql($yesterday))['review_state']);
        $old['booking_date'] = $today;
        $this->assertSame(1, $this->persist([$old])['changed']);
        $this->assertSame('pending', ebRow('SELECT review_state FROM bank_transactions WHERE booking_date=' . ebSql($today))['review_state']);
        // The worker re-checks under the lease that the account is still due; --force does not.
        ebUpdate('bank_accounts', $this->accountId, ['next_fetch_at' => date('Y-m-d H:i:s', time() + 21600)]);
        $transport = new EbFixtureTransport([$this->step($this->page())]);
        $client = new EnableBankingClient(EbTestKeys::config(), $transport);
        $this->assertSame('skipped', ebSyncAccount($this->accountId, 'schedule', [], $client, true)['status']);
        $this->assertSame([], $transport->requests);
        $this->assertSame('ok', ebSyncAccount($this->accountId, 'schedule', [], $client, false)['status']);
    }

    /** @return void */
    public function test_reconnect_matches_any_alias_and_new_uid_preserves_mapping_checkpoint_and_rows(): void
    {
        $this->persist($this->page()['transactions']);
        $this->importPending();
        ebUpdate('bank_accounts', $this->accountId, ['fetched_through' => ebToday()]);
        ebDisconnect($this->connectionId, 'chartest', null);
        $state = bin2hex(random_bytes(32));
        $this->state($state, ['purpose' => 'reconnect', 'connection_id' => $this->connectionId]);
        $row = ebConsumeAuthorization($state, 1);
        $fixture = json_decode(file_get_contents(__DIR__ . '/../../fixtures/enablebanking/accounts.json'), true);
        $body = $fixture + ['session_id' => bin2hex(random_bytes(16)), 'access' => ['valid_until' => date(DATE_RFC3339, time() + 86400)]];
        $transport = new EbFixtureTransport([$this->step($body, 200, '/sessions', 'POST'), $this->step($this->page())]);
        $client = new EnableBankingClient(EbTestKeys::config(), $transport);
        $newId = ebCompleteAuthorization($client, $row, bin2hex(random_bytes(16)), 'chartest');
        $account = ebRow('SELECT * FROM bank_accounts WHERE id=' . $this->accountId);
        $this->assertSame((string)$newId, $account['connection_id']);
        $this->assertSame((string)$this->ledger, $account['ledger_account']);
        $this->assertSame('active', $account['state']);
        $this->assertSame(ebToday(), $account['fetched_through']);
        $this->assertCount(2, ebRows('SELECT id FROM bank_accounts'));
        $this->assertSame('unsupported', ebRow("SELECT state FROM bank_accounts WHERE currency='EUR'")['state']);
        $this->assertSame('fixture-account-one', ebRow('SELECT account_uid FROM bank_account_sessions WHERE account_id=' . $this->accountId . ' AND active=1')['account_uid']);
        $this->assertSame('ok', ebSyncAccount($this->accountId, 'schedule', [], $client)['status']);
        $this->assertCount(2, ebRows('SELECT id FROM bank_transactions'));
        $this->assertCount(2, ebRows('SELECT id FROM kassekladde'));
        $this->assertSame('replaced', ebRow('SELECT status FROM bank_connections WHERE id=' . $this->connectionId)['status']);
        // The replaced consent is left to run out at the bank; closing it broke the new one in the Nordea sandbox.
        $this->assertSame(['POST', 'GET'], array_column($transport->requests, 'method'));
    }

    /** @return void */
    public function test_mapping_manual_fetch_overlap_picking_and_ignore_restore(): void
    {
        ebUpdate('bank_accounts', $this->accountId, ['state' => 'new', 'ledger_account' => null, 'import_from' => null]);
        $this->rejects(fn() => ebMapAccount($this->accountId, 2147483647, ebToday(), 'chartest'));
        $this->rejects(fn() => ebMapAccount($this->accountId, $this->ledger, '2026-02-30', 'chartest'));
        ebMapAccount($this->accountId, $this->ledger, date('Y-m-01'), 'chartest');
        $this->assertSame('active', ebAccountStatus($this->accountId)['state']);
        $this->assertSame('ok', ebSyncAccount($this->accountId, 'schedule', [], $this->client([$this->step($this->page())]))['status']);
        $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
        $this->assertSame(2, ebCountsByState($this->accountId)['pending']);
        $txs = ebRows('SELECT id FROM bank_transactions ORDER BY id');
        $journal = $this->journal();
        ebInsert('kassekladde', ['kladde_id' => $journal, 'transdate' => ebToday(), 'debet' => $this->ledger,
            'kredit' => $this->otherLedger, 'd_type' => 'F', 'k_type' => 'F', 'amount' => '125.12', 'bilag' => 1]);
        $this->assertArrayHasKey((int)$txs[0]['id'], ebOverlapHints($this->accountId, [(int)$txs[0]['id']]));
        $this->assertSame([], ebOverlapHints($this->accountId, [(int)$txs[1]['id']]));
        $this->assertSame(1, ebIgnoreSelected($this->accountId, [(int)$txs[0]['id']], 'chartest'));
        $this->assertSame(1, ebImportIntoJournal($journal, $this->accountId, [(int)$txs[1]['id']], 'chartest')['imported']);
        $this->assertSame('active', ebAccountStatus($this->accountId)['state']);
        $this->assertSame(1, ebCountsByState($this->accountId)['ignored']);
        $this->assertSame(1, ebCountsByState($this->accountId)['imported']);
        ebTransactionAction((int)$txs[0]['id'], 'restore', 'chartest');
        $this->assertSame('pending', ebRow('SELECT review_state FROM bank_transactions WHERE id=' . (int)$txs[0]['id'])['review_state']);
        $this->assertSame(1, ebImportIntoJournal($journal, $this->accountId, [(int)$txs[0]['id']], 'chartest')['imported']);
        $this->assertCount(1, ebListAccounts($this->connectionId));
        $this->assertCount(2, ebListTransactions($this->accountId, 'imported'));
        $this->assertArrayNotHasKey('session_enc', ebListConnections()[0]);
        $this->assertNotEmpty(ebLedgerAccountOptions((int)$GLOBALS['regnaar']));
    }

    /** @return void */
    public function test_expired_consent_rate_limit_action_required_and_backoff_schedule(): void
    {
        $result = ebSyncAccount($this->accountId, 'schedule', [], $this->client([$this->step(['error' => 'ASPSP_RATE_LIMIT_EXCEEDED'], 429)]));
        $this->assertSame('rate_limited', $result['status']);
        $account = ebRow('SELECT * FROM bank_accounts WHERE id=' . $this->accountId);
        $this->assertEqualsWithDelta(time() + 21600, strtotime($account['next_fetch_at']), 3);
        $this->assertSame([], ebDueAccounts());
        $error = $this->step(['error' => 'ASPSP_TIMEOUT'], 500);
        ebSyncAccount($this->accountId, 'schedule', [], $this->client([$error]));
        $this->assertEqualsWithDelta(time() + 1800, strtotime(ebRow('SELECT next_fetch_at FROM bank_accounts WHERE id=' . $this->accountId)['next_fetch_at']), 3);
        ebSyncAccount($this->accountId, 'schedule', [], $this->client([$error]));
        $this->assertEqualsWithDelta(time() + 3600, strtotime(ebRow('SELECT next_fetch_at FROM bank_accounts WHERE id=' . $this->accountId)['next_fetch_at']), 3);
        ebSyncAccount($this->accountId, 'schedule', [], $this->client([$this->step(['error' => 'ASPSP_PSU_ACTION_REQUIRED'], 403)]));
        $this->assertNull(ebRow('SELECT next_fetch_at FROM bank_accounts WHERE id=' . $this->accountId)['next_fetch_at']);
        $this->assertSame([], ebDueAccounts());
        $this->assertSame('active', ebRow('SELECT status FROM bank_connections')['status']);
        $result = ebSyncAccount($this->accountId, 'schedule', [], $this->client([$this->step(['error' => 'EXPIRED_SESSION'], 401)]));
        $this->assertSame('consent_expired', $result['status']);
        $this->assertSame('expired', ebRow('SELECT status FROM bank_connections')['status']);
        $this->assertSame('consent_expired', ebRow('SELECT last_error_code FROM bank_accounts WHERE id=' . $this->accountId)['last_error_code']);
        $this->assertSame([], ebDueAccounts());
        $this->assertNull(ebRow('SELECT lease_owner FROM bank_accounts WHERE id=' . $this->accountId)['lease_owner']);
    }

    /** @return void */
    public function test_manual_refresh_is_throttled_and_does_not_move_background_schedule(): void
    {
        $due = date('Y-m-d H:i:s', time() + 5000);
        ebUpdate('bank_accounts', $this->accountId, ['next_fetch_at' => $due]);
        $client = $this->client([$this->step($this->page())]);
        $first = ebSyncAccount($this->accountId, 'manual', [], $client);
        $this->assertSame('ok', $first['status']);
        // "Hentet kl. HH:MM - 2 nye posteringer" counts the run's own new lines (bank_sync_runs.new_count).
        $this->assertSame(2, $first['new_count']);
        $this->assertSame('2', (string)ebRow('SELECT new_count FROM bank_sync_runs WHERE id=' . (int)$first['run_id'])['new_count']);
        $this->assertSame($due, ebRow('SELECT next_fetch_at FROM bank_accounts WHERE id=' . $this->accountId)['next_fetch_at']);
        $lastManual = ebRow('SELECT last_manual_at FROM bank_accounts WHERE id=' . $this->accountId)['last_manual_at'];
        $this->assertSame(['status' => 'skipped', 'run_id' => 0, 'message' => 'En hentning er allerede i gang eller er netop udført.',
            'reason' => 'cooldown', 'last_manual_at' => $lastManual], ebSyncAccount($this->accountId, 'manual', [], $client));
        $this->assertCount(1, ebRows('SELECT id FROM bank_sync_runs'));
        // A fetch already running (held lease) is not the cooldown, even right after a manual fetch: the feed keeps the run's message.
        ebUpdate('bank_accounts', $this->accountId, ['lease_owner' => 'other-process', 'lease_until' => date('Y-m-d H:i:s', time() + 600)]);
        $held = ebSyncAccount($this->accountId, 'manual', [], $client);
        $this->assertSame('skipped', $held['status']);
        $this->assertArrayNotHasKey('reason', $held);
        // Once the limit has passed, the same lines again are no new lines.
        ebUpdate('bank_accounts', $this->accountId, ['lease_owner' => null, 'lease_until' => null, 'last_manual_at' => date('Y-m-d H:i:s', time() - 600)]);
        $again = ebSyncAccount($this->accountId, 'manual', [], $this->client([$this->step($this->page())]));
        $this->assertSame(['ok', 0], [$again['status'], $again['new_count']]);
    }

    /** @return void */
    public function test_two_real_processes_share_account_lease_and_store_once(): void
    {
        $processes = [];
        for ($i = 0; $i < 2; $i++) {
            $proc = proc_open([PHP_BINARY, __DIR__ . '/support/run_sync.php', (string)$this->accountId, EbTestKeys::$privatePath],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $this->assertIsResource($proc);
            $this->assertSame("ready\n", fgets($pipes[1]));
            $processes[] = [$proc, $pipes];
        }
        foreach ($processes as [$proc, $pipes]) {
            fwrite($pipes[0], "go\n");
            fclose($pipes[0]);
        }
        $statuses = [];
        foreach ($processes as [$proc, $pipes]) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($proc), $err . $out);
            $statuses[] = json_decode(trim($out), true, 512, JSON_THROW_ON_ERROR)['status'];
        }
        sort($statuses);
        $this->assertSame(['ok', 'skipped'], $statuses);
        $this->assertCount(2, ebRows('SELECT id FROM bank_transactions'));
        $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
        $this->assertCount(1, ebRows('SELECT id FROM bank_sync_runs'));
    }

    /** @return void */
    public function test_start_authorization_uses_bank_validity_and_stores_only_state_hash(): void
    {
        $bank = ['name' => 'Fixture Bank', 'country' => 'DK', 'psu_types' => ['business'], 'maximum_consent_validity' => 86400];
        $transport = new EbFixtureTransport([$this->step(['aspsps' => [$bank]], 200, '/aspsps?'),
            $this->step(['url' => 'https://example.invalid/authorize', 'authorization_id' => 'fixture-authorization'], 200, '/auth', 'POST')]);
        $client = new EnableBankingClient(EbTestKeys::config(), $transport);
        $url = ebStartAuthorization($client, 'Fixture Bank', 'DK', 'business', 'connect', null, 1, 'chartest');
        $this->assertSame('https://example.invalid/authorize', $url);
        $body = json_decode($transport->requests[1]['body'], true);
        $row = ebRow('SELECT * FROM bank_auth_states');
        $this->assertSame(hash('sha256', $body['state']), $row['state_hash']);
        $this->assertNotSame($body['state'], $row['state_hash']);
        $this->assertEqualsWithDelta(time() + 86400 - 3600, strtotime($body['access']['valid_until']), 3);
        $this->assertEqualsWithDelta(time() + 1800, strtotime($row['expires_at']), 3);
        $this->assertSame('https://example.invalid/bank/callback', $body['redirect_url']);
        $this->assertSame('business', $body['psu_type']);
    }

    /** @return void */
    public function test_sync_stops_after_disconnect_during_http_without_stale_checkpoint_or_errors(): void
    {
        $step = $this->step($this->page());
        $step['effect'] = fn() => ebDisconnect($this->connectionId, 'chartest', null);
        $result = ebSyncAccount($this->accountId, 'schedule', [], $this->client([$step]));
        $this->assertSame('skipped', $result['status']);
        $this->assertCount(0, ebRows('SELECT id FROM bank_transactions'));
        $account = ebRow('SELECT * FROM bank_accounts WHERE id=' . $this->accountId);
        $this->assertSame('disconnected', $account['state']);
        $this->assertNull($account['fetched_through']);
        $this->assertNull($account['next_fetch_at']);
        $this->assertNull($account['last_error_code']);
    }

    /** @return void */
    public function test_transaction_wrapper_rolls_back_real_writes_and_remains_usable(): void
    {
        try {
            ebAtomic(function (): void {
                ebAudit('chartest', 'import_review', $this->accountId);
                throw new EbDbException();
            });
            $this->fail('Expected transaction failure');
        } catch (EbDbException $e) {
            $this->assertCount(0, ebRows("SELECT id FROM bank_audit_log WHERE action='import_review'"));
            $this->assertSame(0, $GLOBALS['db_transaktion_depth']);
        }
        ebAtomic(fn() => ebAudit('chartest', 'import_review', $this->accountId));
        $this->assertCount(1, ebRows("SELECT id FROM bank_audit_log WHERE action='import_review'"));
    }

    /** @return void */
    public function test_page_cap_is_partial_and_does_not_advance_checkpoint(): void
    {
        $step = $this->step(['transactions' => [], 'continuation_key' => 'fixture-endless']);
        $result = ebSyncAccount($this->accountId, 'schedule', [], $this->client(array_fill(0, 1000, $step)));
        $this->assertSame('partial', $result['status']);
        $run = ebRow('SELECT * FROM bank_sync_runs');
        $this->assertSame('1000', $run['pages']);
        $this->assertSame('PAGE_CAP', $run['error_code']);
        $this->assertNull(ebRow('SELECT fetched_through FROM bank_accounts WHERE id=' . $this->accountId)['fetched_through']);
        $this->assertEqualsWithDelta(time() + 21600, strtotime(ebRow('SELECT next_fetch_at FROM bank_accounts WHERE id=' . $this->accountId)['next_fetch_at']), 3);
    }


    /** @return void */
    public function test_cli_worker_dry_run_reads_the_cloned_tenant_without_importing(): void
    {
        $result = $this->worker(['--account=' . $this->accountId, '--force', '--dry-run']);
        $this->assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
        $this->assertStringContainsString('account=' . $this->accountId . ' dry_run', $result['stdout']);
        $this->assertCount(0, ebRows('SELECT id FROM bank_sync_runs'));
        $this->assertCount(0, ebRows('SELECT id FROM bank_transactions'));
        $this->assertNull(ebRow('SELECT lease_owner FROM bank_accounts WHERE id=' . $this->accountId)['lease_owner']);
    }

    /**
     * @param list<string> $args Additional worker options.
     * @return array{exit:int, stdout:string, stderr:string} Child execution with temporary provider configuration.
     */
    private function worker(array $args = [], bool $configured = true): array
    {
        $previous = [];
        $values = ['APP_ID' => 'fixture-application', 'KEY_PATH' => $configured ? EbTestKeys::$privatePath : '/nonexistent-eb-key',
            'REDIRECT_URL' => 'https://example.invalid/callback', 'ENV' => 'SANDBOX'];
        foreach ($values as $name => $value) {
            $previous[$name] = getenv('SALDI_ENABLEBANKING_' . $name);
            putenv('SALDI_ENABLEBANKING_' . $name . '=' . $value);
        }
        try {
            return CharacterizationEnv::runChild(__DIR__ . '/../../../bank_integration/worker.php',
                array_merge(['--tenant=' . CharacterizationEnv::testDb()], $args));
        } finally {
            foreach ($previous as $name => $value) {
                putenv('SALDI_ENABLEBANKING_' . $name . ($value === false ? '' : '=' . $value));
            }
        }
    }

    /** @return int Connect a synthetic provider session through the real authorization flow. */
    private function authorizeAccounts(array $accounts, ?int $reconnectId = null): int
    {
        $state = bin2hex(random_bytes(32));
        $this->state($state, $reconnectId === null ? [] : ['purpose' => 'reconnect', 'connection_id' => $reconnectId]);
        $body = ['accounts' => $accounts, 'session_id' => bin2hex(random_bytes(16)),
            'access' => ['valid_until' => date(DATE_RFC3339, time() + 86400)]];
        $steps = [$this->step($body, 200, '/sessions', 'POST')];
        if ($reconnectId !== null) {
            $steps[] = $this->step(['message' => 'OK'], 200, '/sessions/', 'DELETE');
        }
        return ebCompleteAuthorization($this->client($steps),
            ebConsumeAuthorization($state, 1), bin2hex(random_bytes(16)), 'chartest');
    }

    /** @return array{uid:string, identification_hash:string, identification_hashes:list<string>, currency:string} */
    private function resource(string $primary, array $secondary = [], string $currency = 'DKK'): array
    {
        return ['uid' => 'uid-' . $primary, 'identification_hash' => $primary,
            'identification_hashes' => array_merge([$primary], $secondary), 'currency' => $currency];
    }

    /** @return void */
    public function test_same_session_shared_secondary_never_merges_or_becomes_an_alias(): void
    {
        ebInsert('bank_account_aliases', ['account_id' => $this->accountId, 'identification_hash' => 'owner-name']);
        $cid = $this->authorizeAccounts([$this->resource('bank-one', ['owner-name']),
            $this->resource('bank-two', ['owner-name']), $this->resource('bank-three')]);
        $this->assertCount(3, ebListAccounts($cid));
        $this->assertCount(3, ebRows('SELECT DISTINCT account_id FROM bank_account_sessions WHERE connection_id=' . $cid));
        $this->assertNull(ebRow("SELECT id FROM bank_account_aliases WHERE identification_hash='owner-name'"));
    }

    /** @return void */
    public function test_primary_wins_secondary_ambiguity_and_alias_ownership_is_preserved(): void
    {
        $other = $this->account($this->connectionId, 'other-primary');
        $cid = $this->authorizeAccounts([$this->resource('fixture-shared-hash', ['other-primary'])]);
        $this->assertSame((string)$cid, ebRow('SELECT connection_id FROM bank_accounts WHERE id=' . $this->accountId)['connection_id']);
        $this->assertSame((string)$other, ebRow("SELECT account_id FROM bank_account_aliases WHERE identification_hash='other-primary'")['account_id']);
        $cid = $this->authorizeAccounts([$this->resource('ambiguous-primary', ['fixture-shared-hash', 'other-primary'])]);
        $this->assertCount(1, ebListAccounts($cid));
        $this->assertNotContains((int)ebListAccounts($cid)[0]['id'], [$this->accountId, $other]);
    }

    /** @return void */
    public function test_secondary_requires_equal_currency_and_local_account_can_only_be_claimed_once(): void
    {
        ebInsert('bank_account_aliases', ['account_id' => $this->accountId, 'identification_hash' => 'another-alias']);
        $cid = $this->authorizeAccounts([$this->resource('claim-one', ['fixture-shared-hash']),
            $this->resource('claim-two', ['another-alias'])]);
        $this->assertCount(2, ebListAccounts($cid));
        $this->assertSame((string)$cid, ebRow('SELECT connection_id FROM bank_accounts WHERE id=' . $this->accountId)['connection_id']);
        $cid = $this->authorizeAccounts([$this->resource('euro-primary', ['fixture-shared-hash'], 'EUR')]);
        $this->assertSame('unsupported', ebListAccounts($cid)[0]['state']);
        $this->assertNotSame((string)$this->accountId, ebListAccounts($cid)[0]['id']);
    }

    /** @return void */
    public function test_primary_matches_account_column_without_alias_and_shared_primary_stays_separate(): void
    {
        ebWrite('DELETE FROM bank_account_aliases');
        $cid = $this->authorizeAccounts([$this->resource('fixture-shared-hash')]);
        $this->assertSame((string)$this->accountId, ebListAccounts($cid)[0]['id']);
        $one = $this->resource('shared-primary');
        $two = $one;
        $two['uid'] = 'second-uid';
        $cid = $this->authorizeAccounts([$one, $two]);
        $this->assertCount(2, ebListAccounts($cid));
        $this->assertNull(ebRow("SELECT id FROM bank_account_aliases WHERE identification_hash='shared-primary'"));
    }

    /** @return void */
    public function test_proven_ambiguous_primary_is_never_identifying_again(): void
    {
        $one = $this->resource('fixture-shared-hash');
        $two = $one;
        $two['uid'] = 'second-uid';
        $this->authorizeAccounts([$one, $two], $this->connectionId);
        $account = ebRow('SELECT identification_hash FROM bank_accounts WHERE id=' . $this->accountId);
        $this->assertStringStartsWith('local:', $account['identification_hash']);
        $this->assertCount(1, ebRows("SELECT id FROM bank_ambiguous_hashes WHERE identification_hash='fixture-shared-hash'"));
        $this->assertNull(ebRow("SELECT id FROM bank_account_aliases WHERE identification_hash='fixture-shared-hash'"));

        $only = $this->resource('fixture-shared-hash');
        $only['uid'] = 'b-only';
        $cid = $this->authorizeAccounts([$only]);
        $accounts = ebListAccounts($cid);
        $this->assertCount(1, $accounts);
        $this->assertNotSame((string)$this->accountId, $accounts[0]['id']);
        $this->assertSame('new', $accounts[0]['state']);
        $this->assertNull($accounts[0]['ledger_account']);
        $account = ebRow('SELECT connection_id, state FROM bank_accounts WHERE id=' . $this->accountId);
        $this->assertNotSame((string)$cid, $account['connection_id']);
        $this->assertNotSame('active', $account['state']);
        $this->assertNull(ebRow("SELECT id FROM bank_account_aliases WHERE identification_hash='fixture-shared-hash'"));
        $this->assertCount(1, ebRows("SELECT id FROM bank_ambiguous_hashes WHERE identification_hash='fixture-shared-hash'"));
    }

    /** @return void */
    public function test_closed_ledger_account_is_not_offered_mapped_or_imported(): void
    {
        $year = (int)$GLOBALS['regnaar'];
        $ledgers = ebRows('SELECT kontonr, lukket FROM kontoplan WHERE regnskabsaar=' . $year
            . ' AND kontonr IN (' . $this->ledger . ',' . $this->otherLedger . ')');
        try {
            ebWrite("UPDATE kontoplan SET lukket='on' WHERE regnskabsaar=" . $year . ' AND kontonr=' . $this->otherLedger);
            $this->assertNotContains((string)$this->otherLedger, array_column(ebLedgerAccountOptions($year), 'kontonr'));
            $this->rejects(fn() => ebMapAccount($this->accountId, $this->otherLedger, date('Y-m-01'), 'chartest'));
            ebWrite("UPDATE kontoplan SET lukket='on' WHERE regnskabsaar=" . $year . ' AND kontonr=' . $this->ledger);
            $this->persist($this->page()['transactions']);
            $this->assertSame(0, $this->importPending()['imported']);
            $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
            $rows = ebRows('SELECT review_state, review_reason FROM bank_transactions ORDER BY id');
            $this->assertSame(['pending', 'pending'], array_column($rows, 'review_state'));
            $this->assertSame(['no_ledger_account', 'no_ledger_account'], array_column($rows, 'review_reason'));
            ebWrite("UPDATE kontoplan SET lukket='' WHERE regnskabsaar=" . $year . ' AND kontonr=' . $this->ledger);
            $this->assertSame(2, $this->importPending()['imported']);
        } finally {
            foreach ($ledgers as $ledger) {
                ebWrite('UPDATE kontoplan SET lukket=' . ebSql($ledger['lukket']) . ' WHERE regnskabsaar=' . $year . ' AND kontonr=' . (int)$ledger['kontonr']);
            }
        }
    }

    /** @return void */
    public function test_skip_reasons_are_rechecked_on_every_pick_and_retry_is_gone(): void
    {
        $this->persist([$this->page()['transactions'][0]]);
        $id = (int)ebRow('SELECT id FROM bank_transactions')['id'];
        ebUpdate('bank_transactions', $id, ['review_state' => 'review', 'review_reason' => 'closed_period']);
        $this->rejects(fn() => ebTransactionAction($id, 'retry', 'chartest'));
        ebUpdate('bank_transactions', $id, ['review_state' => 'pending', 'review_reason' => null]);
        $year = (int)$GLOBALS['regnaar'];
        try {
            $GLOBALS['regnaar'] = 2147483647; // A fiscal year that does not exist: nothing fits.
            $this->assertSame(0, $this->importPending()['imported']);
            $this->assertSame('outside_fiscal_year', ebRow('SELECT review_reason FROM bank_transactions')['review_reason']);
        } finally {
            $GLOBALS['regnaar'] = $year;
        }
        if (!ebTableExists('moms_periode_luk')) {
            ebWrite('CREATE TABLE moms_periode_luk (kalender_aar INT, kalender_maaned INT, status VARCHAR(20))');
        }
        ebWrite("INSERT INTO moms_periode_luk (kalender_aar, kalender_maaned, status) VALUES (" . (int)date('Y') . ',' . (int)date('m') . ",'closed')");
        $this->assertSame(0, $this->importPending()['imported']);
        $this->assertSame('closed_period', ebRow('SELECT review_reason FROM bank_transactions')['review_reason']);
        ebWrite('DELETE FROM moms_periode_luk');
        ebUpdate('bank_accounts', $this->accountId, ['ledger_account' => 2147483647]);
        $this->assertSame(0, $this->importPending()['imported']);
        $this->assertSame('no_ledger_account', ebRow('SELECT review_reason FROM bank_transactions')['review_reason']);
        ebUpdate('bank_accounts', $this->accountId, ['ledger_account' => $this->ledger]);
        $this->assertSame(1, $this->importPending()['imported']);
        $this->assertSame(['review_state' => 'imported', 'review_reason' => null], ebRow('SELECT review_state, review_reason FROM bank_transactions'));
    }

    /** @return void */
    public function test_remapping_preserves_imported_lines_and_reclassifies_cutover_rows(): void
    {
        $today = ebToday();
        $before = date('Y-m-d', strtotime('-1 day'));
        ebUpdate('bank_accounts', $this->accountId, ['import_from' => $today, 'fetched_through' => $today]);
        $raw = $this->page()['transactions'][0];
        $old = $raw;
        $old['entry_reference'] = 'before-cutover';
        $old['booking_date'] = $before;
        $this->persist([$raw, $old]);
        $this->importPending();
        $line = ebRow('SELECT * FROM kassekladde');
        $this->assertSame(1, ebCountsByState($this->accountId)['pre_cutover']);
        ebMapAccount($this->accountId, $this->otherLedger, $before, 'chartest');
        $this->assertNull(ebRow('SELECT fetched_through FROM bank_accounts WHERE id=' . $this->accountId)['fetched_through']);
        $this->assertSame(1, ebCountsByState($this->accountId)['pending']);
        $this->assertSame($line, ebRow('SELECT * FROM kassekladde'));
        ebMapAccount($this->accountId, $this->otherLedger, $today, 'chartest');
        $this->assertSame(1, ebCountsByState($this->accountId)['pre_cutover']);
        ebMapAccount($this->accountId, $this->otherLedger, $before, 'chartest');
        $this->assertSame(1, $this->importPending()['imported']);
        $this->assertSame((string)$this->otherLedger, ebRow('SELECT debet FROM kassekladde ORDER BY id DESC LIMIT 1')['debet']);
        foreach (['active', 'paused'] as $state) {
            ebUpdate('bank_accounts', $this->accountId, ['state' => $state]);
            ebMapAccount($this->accountId, $this->ledger, $today, 'chartest');
            $this->assertSame($state, ebAccountStatus($this->accountId)['state']);
        }
        $this->assertStringContainsString('Ledger ', ebRow("SELECT detail FROM bank_audit_log WHERE action='mapped' ORDER BY id DESC LIMIT 1")['detail']);
    }

    /** @return void */
    public function test_ui_projections_include_link_and_latest_run_started_at(): void
    {
        $this->persist($this->page()['transactions']);
        $this->importPending();
        $link = ebRow('SELECT * FROM bank_import_links ORDER BY transaction_id DESC LIMIT 1');
        $tx = ebListTransactions($this->accountId, 'imported')[0];
        foreach (['bilag', 'kladde_id', 'kassekladde_id', 'disposition'] as $field) {
            $this->assertSame($link[$field], $tx[$field]);
        }
        $started = date('Y-m-d H:i:s', time() - 60);
        ebInsert('bank_sync_runs', ['account_id' => $this->accountId, 'trigger' => 'manual', 'started_at' => $started,
            'finished_at' => ebNow(), 'status' => 'failed']);
        ebInsert('bank_sync_runs', ['account_id' => $this->accountId, 'trigger' => 'manual',
            'started_at' => date('Y-m-d H:i:s', time() - 120), 'status' => 'ok']);
        ebUpdate('bank_accounts', $this->accountId, ['last_error_code' => 'ASPSP_TIMEOUT']);
        $status = ebAccountStatus($this->accountId);
        $this->assertSame('active', $status['connection_status']);
        $this->assertSame('ASPSP_TIMEOUT', $status['last_error_code']);
        $this->assertSame($started, $status['last_run_at']);
        $this->assertSame('failed', $status['last_run_status']);
    }

    /** @return void */
    public function test_danish_errors_never_expose_provider_text_and_render_small_support_codes(): void
    {
        require_once __DIR__ . '/../../../bank_integration/includes/ebAccess.php';
        $cases = [['EXPIRED_SESSION', 'consent', 'Adgangen til banken er udløbet eller tilbagekaldt. Forny adgangen.'],
            ['ASPSP_RATE_LIMIT_EXCEEDED', 'rate_limit', 'Banken har midlertidigt begrænset antallet af opslag. Saldi prøver igen automatisk.'],
            ['ASPSP_TIMEOUT', 'transient', 'Banken svarede ikke. Saldi prøver igen automatisk.'],
            ['ASPSP_PSU_ACTION_REQUIRED', 'action_required', 'Banken kræver, at du logger ind igen. Forny adgangen.']];
        foreach ($cases as [$code, $kind, $text]) {
            $this->assertSame($text, ebErrorText($code, $kind));
            $this->assertStringContainsString('<small', ebErrorHtml($code, $kind));
            $this->assertStringContainsString(strtoupper($code), ebErrorHtml($code, $kind));
        }
        foreach (['PAGE_CAP', 'WRONG_CONTINUATION_KEY'] as $code) {
            $this->assertStringContainsString($code, ebErrorText($code, 'permanent'));
        }
        $text = ebErrorMessage(new EbApiException(500, 'ASPSP_TIMEOUT', 'raw English provider detail'));
        $this->assertStringNotContainsString('raw English', $text);
        $this->assertStringContainsString('ASPSP_TIMEOUT', $text);
    }

    /** @return void */
    public function test_authorization_errors_preserve_known_codes_without_fetch_wording_or_provider_text(): void
    {
        require_once __DIR__ . '/../../../bank_integration/includes/ebAccess.php';
        $codes = ['REDIRECT_URI_NOT_ALLOWED', 'AUTHORIZATION_NOT_PROVIDED', 'INVALID_HOST', 'UNAUTHORIZED_IP',
            'NO_ACCOUNTS_ADDED', 'WRONG_ASPSP_PROVIDED', 'WRONG_CREDENTIALS_PROVIDED', 'WRONG_REQUEST_PARAMETERS',
            'ALREADY_AUTHORIZED', 'PSU_HEADER_INVALID', 'EXPIRED_AUTHORIZATION_CODE', 'WRONG_AUTHORIZATION_CODE',
            'ACCESS_DENIED', 'ASPSP_TIMEOUT'];
        foreach (array_merge($codes, ['UNRECOGNIZED_PROVIDER_CODE']) as $code) {
            $client = $this->client([$this->step(['error' => $code, 'message' => 'private provider detail'], 400, '/auth', 'POST')]);
            try {
                $client->startAuth('Fixture Bank', 'DK', 'business', 'fixture-state', gmdate('c', time() + 86400));
                $this->fail('Authorization failure accepted');
            } catch (EbApiException $e) {
                $safeCode = in_array($code, $codes, true) ? $code : 'HTTP_ERROR';
                $this->assertSame($safeCode, $e->errorCode);
                $message = ebErrorMessage($e, 'authorization');
                $this->assertSame('Banken kunne ikke åbnes. Kontakt Saldi-support. (kode ' . $safeCode . ')', $message);
                $html = ebMessageHtml($message);
                $this->assertStringContainsString('<small', $html);
                $this->assertStringContainsString('Kode: ' . $safeCode, $html);
                $this->assertStringNotContainsString('private provider detail', $e->getMessage() . $html);
                $this->assertStringNotContainsString('Bankhentningen', $html);
                $this->assertStringNotContainsString('automatisk', $html);
            }
        }
        $this->assertSame('Banken kunne ikke åbnes. Kontakt Saldi-support.', ebErrorText(null, null, 'authorization'));
        $this->assertSame('Vælg en bank.', ebErrorMessage(new EbUserError('Vælg en bank.'), 'authorization'));
        $this->assertStringContainsString('Banken svarede ikke. Saldi prøver igen automatisk.',
            ebErrorMessage(new EbApiException(500, 'ASPSP_TIMEOUT', 'private provider detail')));
    }

    /** @return void */
    public function test_page_calls_release_sessions_and_flash_survives_reopening(): void
    {
        $root = __DIR__ . '/../../../bank_integration/';
        foreach (['connect.php' => 'ebStartAuthorization(', 'callback.php' => 'ebCompleteAuthorization(',
            'feed.php' => "ebSyncAccount($", 'accounts.php' => "ebSyncAccount($"] as $file => $call) {
            $source = file_get_contents($root . $file);
            $this->assertLessThan(strpos($source, $call), strpos($source, 'session_write_close();'), $file);
        }
        $directory = sys_get_temp_dir() . '/eb_session_' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        try {
            $result = CharacterizationEnv::runChild(__DIR__ . '/support/run_session.php', [$directory, EbTestKeys::$privatePath]);
            $this->assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
            $this->assertSame('session and flash ok', trim($result['stdout']));
        } finally {
            foreach (glob($directory . '/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    /** @return void */
    public function test_successful_manual_and_schedule_fetch_schedule_due_accounts_six_hours_later(): void
    {
        foreach (['manual', 'schedule'] as $trigger) {
            foreach ([null, date('Y-m-d H:i:s', time() - 60)] as $due) {
                ebUpdate('bank_accounts', $this->accountId, ['next_fetch_at' => $due, 'last_manual_at' => null]);
                $this->assertSame('ok', ebSyncAccount($this->accountId, $trigger, [], $this->client([$this->step($this->page())]))['status']);
                $this->assertEqualsWithDelta(time() + 21600, strtotime(ebAccountStatus($this->accountId)['next_fetch_at']), 3);
                $this->assertSame([], ebDueAccounts());
            }
        }
    }

    /** @return void */
    public function test_html_entities_decode_before_text_building_and_identity_hashes(): void
    {
        $raw = $this->page()['transactions'][0];
        $raw['debtor']['name'] = 'Fup &amp; Fidus A/S';
        $raw['remittance_information'] = ['Betaling &quot;æøå&quot;'];
        $raw['entry_reference'] = 'ref&amp;1';
        $raw['reference_number'] = 'bank&#039;ref';
        $n = ebNormalizeTransaction($raw);
        $this->assertSame('Fup & Fidus A/S Betaling "æøå"', $n['description']);
        $this->assertSame('Fup & Fidus A/S', $n['counterparty']);
        $this->assertSame('ref&1', $n['entry_reference']);
        $this->assertSame("bank'ref", $n['bank_reference']);
        $decoded = $raw;
        array_walk_recursive($decoded, static function (&$value): void {
            if (is_string($value)) {
                $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        });
        $this->assertSame(ebFingerprint(ebNormalizeTransaction($decoded)), ebFingerprint($n));
        $this->assertSame(ebPayloadHash(ebNormalizeTransaction($decoded)), ebPayloadHash($n));
        $this->assertSame(1, $this->persist([$raw])['new']);
        $this->assertSame(0, $this->persist([$decoded])['new']);
        $this->assertSame(0, $this->persist([$decoded])['changed']);
        $raw['remittance_information'] = [];
        $raw['note'] = 'Note &amp; tekst';
        $this->assertStringContainsString('Note & tekst', ebNormalizeTransaction($raw)['description']);
        $raw['note'] = '';
        $raw['bank_transaction_code']['description'] = 'Kode &amp; tekst';
        $this->assertStringContainsString('Kode & tekst', ebNormalizeTransaction($raw)['description']);
    }

    /** @return void */
    public function test_rendered_views_show_remapping_review_actions_vouchers_reasons_and_danish_errors(): void
    {
        $this->persist($this->page()['transactions']);
        $this->importPending();
        $imported = ebRow('SELECT * FROM bank_import_links ORDER BY transaction_id DESC LIMIT 1');
        $review = $this->page()['transactions'][0];
        $review['entry_reference'] = 'ui-review';
        $skipped = $review;
        $skipped['entry_reference'] = 'ui-skipped';
        $this->persist([$review, $skipped]);
        $id = (int)ebRow("SELECT id FROM bank_transactions WHERE entry_reference='ui-review'")['id'];
        ebUpdate('bank_transactions', $id, ['review_state' => 'review', 'review_reason' => 'possible_duplicate']);
        ebWrite("UPDATE bank_transactions SET review_reason='closed_period' WHERE entry_reference='ui-skipped'");
        ebUpdate('bank_accounts', $this->accountId, ['last_error_code' => 'ASPSP_TIMEOUT', 'last_error' => 'Raw English provider text']);
        ebInsert('bank_sync_runs', ['account_id' => $this->accountId, 'trigger' => 'manual', 'started_at' => ebNow(), 'status' => 'failed']);
        $render = function (string $view, string $tab = 'review'): string {
            $result = CharacterizationEnv::runChild(__DIR__ . '/support/run_bank_view.php', [$view, $this->accountId, EbTestKeys::$privatePath, $tab]);
            $this->assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
            $this->assertStringNotContainsString('Raw English provider text', $result['stdout']);
            return $result['stdout'];
        };
        foreach (['active', 'paused'] as $state) {
            ebUpdate('bank_accounts', $this->accountId, ['state' => $state]);
            $html = $render('accounts');
            $this->assertStringContainsString("name='ledger_account'", $html);
            $this->assertStringContainsString("name='import_from' value='" . date('01-m-Y') . "'", $html);
            $this->assertStringContainsString('Linjer, der allerede er lagt i en kassekladde, ændres ikke.', $html);
        }
        ebUpdate('bank_accounts', $this->accountId, ['state' => 'active']);
        $html = $render('feed');
        $this->assertStringContainsString("name='op' value='import'", $html);
        $this->assertStringContainsString('Flyt til Nye', $html);
        $this->assertStringNotContainsString("value='retry'", $html);
        $this->assertStringContainsString('Banken svarede ikke. Saldi prøver igen automatisk.', $html);
        $this->assertStringContainsString("title='Kode: ASPSP_TIMEOUT'", $html);
        $this->assertStringContainsString(', bilag ' . $imported['bilag'], $render('feed', 'imported'));
        $this->assertStringContainsString('Ikke lagt i kassekladden: Momsperioden for datoen er lukket', $render('feed', 'pending'));
        $html = $render('settings');
        $this->assertStringNotContainsString('eb_max_journal_lines', $html);
        $this->assertStringContainsString("name='eb_enabled'", $html);
    }

    /** @return void */
    public function test_rendered_views_pick_list_journal_context_pause_rights_and_disconnect(): void
    {
        require_once __DIR__ . '/../../../bank_integration/includes/ebAccess.php';
        $this->persist($this->page()['transactions']);
        $txs = array_map('intval', array_column(ebRows('SELECT id FROM bank_transactions ORDER BY id'), 'id'));
        $journal = $this->journal();
        ebInsert('kassekladde', ['kladde_id' => $journal, 'transdate' => ebToday(), 'debet' => $this->ledger,
            'kredit' => $this->otherLedger, 'd_type' => 'F', 'k_type' => 'F', 'amount' => '125.12', 'bilag' => 1]);
        $this->assertArrayHasKey($txs[0], ebOverlapHints($this->accountId, $txs));
        $render = function (string $view, string $tab = 'pending', ?int $kladde = null): string {
            $args = [$view, $this->accountId, EbTestKeys::$privatePath, $tab];
            if ($kladde !== null) {
                array_push($args, 'kladde', (string)$kladde);
            }
            $result = CharacterizationEnv::runChild(__DIR__ . '/support/run_bank_view.php', $args);
            $this->assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
            return $result['stdout'];
        };

        // Coming from a journal (kladde_id): the pick bar puts the picked lines into that journal.
        $html = $render('feed', 'pending', $journal);
        $this->assertStringContainsString("id='eb-pick'", $html);
        $this->assertStringContainsString("name='eb_csrf'", $html);
        $this->assertStringContainsString("name='action' value='import_selected'", $html);
        $this->assertStringContainsString('Læg valgte i kladde ' . $journal, $html);
        $this->assertStringContainsString("name='action' value='ignore_selected'", $html);
        $this->assertStringContainsString('kladde_id=' . $journal, $html);
        $this->assertStringNotContainsString('åbn kassekladden, og klik Bankposteringer', $html);
        foreach ($txs as $id) {
            $this->assertStringContainsString("name='ids[]' value='$id'", $html);
        }
        // The overlap hint is shown, but nothing is ever preselected.
        $this->assertStringContainsString('Der findes allerede en linje med samme dato og beløb på bankkontoen.', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*\bchecked\b/', $html);
        $this->assertStringContainsString('Nye (2)', $html);
        $this->assertStringNotContainsString('<dt>Import</dt>', $html);
        $this->assertStringNotContainsString('utomatisk import', $html);
        $this->assertStringNotContainsString("name='action' value='auto_import'", $html);
        $this->assertStringContainsString("type='text' name='from'", $html);
        $this->assertStringNotContainsString("name='action' value='pause'", $html);
        $accounts = $render('accounts');
        $this->assertStringContainsString("name='action' value='pause'", $accounts);
        $this->assertStringContainsString('2 posteringer venter stadig på at blive lagt i en kassekladde. Du kan stadig vælge dem under Bankposteringer, når forbindelsen er fjernet.', $accounts);

        // Without a journal: no import button, a hint with a link to the journal list; skipping still works.
        $html = $render('feed');
        $this->assertStringNotContainsString("value='import_selected'", $html);
        $this->assertStringNotContainsString('Læg valgte i kladde', $html);
        $this->assertStringContainsString("name='action' value='ignore_selected'", $html);
        $this->assertStringContainsString('åbn kassekladden, og klik Bankposteringer', $html);
        $this->assertStringContainsString("href='../finans/kladdeliste.php'", $html);

        // A posted journal is no journal context.
        $posted = $this->journal(['bogfort' => 'V']);
        $html = $render('feed', 'pending', $posted);
        $this->assertStringContainsString('Kassekladde ' . $posted . ' findes ikke eller er bogført', $html);
        $this->assertStringNotContainsString("value='import_selected'", $html);

        ebSetPaused($this->accountId, true, 'chartest');
        $html = $render('feed', 'pending', $journal);
        $this->assertStringContainsString("name='action' value='resume'", $render('accounts'));
        $this->assertStringContainsString('Hentningen er sat på pause.', $html);
        $this->assertStringContainsString("name='ids[]' value='{$txs[1]}'", $html);
        $this->assertStringContainsString("name='action' value='import_selected'", $html);
        ebSetPaused($this->accountId, false, 'chartest');

        // A bookkeeper without the Indstillinger right can pick lines but sees no connection controls.
        // includes/online.php reads the rights from the master online row for this session.
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        $rights = str_repeat('9', 50);
        $rights[1] = '0';
        pg_query_params($master, 'UPDATE online SET rettigheder = $1 WHERE session_id = $2', [$rights, CharacterizationEnv::sessionId()]);
        try {
            $html = $render('feed', 'pending', $journal);
            $this->assertStringContainsString("name='action' value='import_selected'", $html);
            $this->assertStringContainsString("name='ids[]' value='{$txs[0]}'", $html);
            foreach (["name='action' value='pause'", "name='action' value='disconnect'", "href='accounts.php"] as $hidden) {
                $this->assertStringNotContainsString($hidden, $html);
            }
        } finally {
            pg_query_params($master, 'UPDATE online SET rettigheder = $1 WHERE session_id = $2', [str_repeat('9', 50), CharacterizationEnv::sessionId()]);
            pg_close($master);
        }

        // Accounts: Fjern bankforbindelse per connection, also when every account on it is unsupported.
        ebUpdate('bank_accounts', $this->accountId, ['state' => 'unsupported', 'currency' => 'EUR']);
        $html = $render('accounts');
        $this->assertStringContainsString("name='action' value='disconnect_connection'", $html);
        $this->assertStringContainsString("name='connection' value='{$this->connectionId}'", $html);
        ebUpdate('bank_accounts', $this->accountId, ['state' => 'active', 'currency' => 'DKK']);

        // Unconfirmed revoke: the warning is shown and picking still works after disconnecting.
        ebDisconnect($this->connectionId, 'chartest', null);
        $warning = (string)ebRow('SELECT last_error FROM bank_connections WHERE id=' . $this->connectionId)['last_error'];
        $this->assertStringStartsWith('Banken bekræftede ikke afbrydelsen.', $warning);
        $this->assertStringContainsString(ebH($warning), $render('accounts'));
        $html = $render('feed', 'pending', $journal);
        $this->assertStringContainsString(ebH($warning), $html);
        $this->assertStringContainsString("name='action' value='import_selected'", $html);
        $this->assertStringContainsString('Du kan stadig lægge de hentede posteringer under Nye i en kassekladde.', $html);
    }

    /** @return void */
    public function test_remapping_during_fetch_cannot_restore_the_old_checkpoint(): void
    {
        ebUpdate('bank_accounts', $this->accountId, ['import_from' => ebToday(), 'fetched_through' => ebToday()]);
        $step = $this->step($this->page());
        $step['effect'] = fn() => ebMapAccount($this->accountId, $this->ledger, date('Y-m-d', strtotime('-1 day')), 'chartest');
        $result = ebSyncAccount($this->accountId, 'manual', [], $this->client([$step]));
        $this->assertSame('skipped', $result['status']);
        $this->assertNull(ebRow('SELECT fetched_through FROM bank_accounts WHERE id=' . $this->accountId)['fetched_through']);
        $this->assertCount(0, ebRows('SELECT id FROM bank_transactions'));
    }

    /** @return void */
    public function test_native_writes_preserve_bank_text_detect_unique_sqlstate_and_do_not_log(): void
    {
        $log = CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb() . '/.ht_modify.log';
        $before = is_file($log) ? file_get_contents($log) : '';
        $previous = $GLOBALS['db_skriv_id'];
        $GLOBALS['db_skriv_id'] = 99; // Ensure legacy logging would be enabled.
        $connection = $GLOBALS['connection'];
        try {
            $raw = $this->page()['transactions'][0];
            $raw['remittance_information'] = ["Bank \\'tekst; æøå"];
            $this->persist([$raw]);
            $this->assertSame(1, $this->importPending()['imported']);
            $this->assertSame(ebNormalizeTransaction($raw)['description'], ebRow('SELECT beskrivelse FROM kassekladde')['beskrivelse']);
            $this->assertSame($connection, $GLOBALS['connection']);
            try {
                ebAtomic(fn() => ebInsert('bank_account_aliases', ['account_id' => $this->accountId, 'identification_hash' => 'fixture-shared-hash']));
                $this->fail('Expected a unique violation');
            } catch (EbDbException $e) {
                $this->assertTrue($e->uniqueViolation);
                $this->assertStringNotContainsString('fixture-shared-hash', $e->getMessage());
            }
            $this->assertSame(0, $GLOBALS['db_transaktion_depth']);
            ebAtomic(fn() => ebAudit('chartest', 'native_write_ok', $this->accountId));
            $this->assertSame($before, is_file($log) ? file_get_contents($log) : '');
        } finally {
            $GLOBALS['db_skriv_id'] = $previous;
        }
    }

    /** @return void */
    public function test_every_bank_controller_rejects_forms_from_the_previous_tenant_before_mutation(): void
    {
        require_once __DIR__ . '/../../../bank_integration/includes/ebAccess.php';
        $_SESSION = [];
        $tenant = $GLOBALS['db'];
        $a = ebCsrfToken();
        $GLOBALS['db'] = 'rendering_tenant_a';
        $b = ebCsrfToken();
        $this->assertNotSame($a, $b);
        $GLOBALS['db'] = $tenant;
        $this->assertSame($a, ebCsrfToken());
        $this->assertStringContainsString("name='eb_db' value='" . $tenant . "'", ebCsrfField());
        $_POST = ['eb_db' => $tenant, 'eb_csrf' => $a];
        ebCsrfCheck(); // A valid current-tenant form still succeeds.
        ebUpdate('bank_accounts', $this->accountId, ['state' => 'active', 'fetched_through' => ebToday()]);
        $this->persist($this->page()['transactions']);
        $before = ebRows('SELECT * FROM bank_audit_log ORDER BY id');
        foreach (['feed', 'accounts', 'connect', 'settings'] as $view) {
            foreach (['', 'same_tenant'] as $mode) {
                $result = CharacterizationEnv::runChild(__DIR__ . '/support/run_bank_view.php', [$view, $this->accountId, EbTestKeys::$privatePath, 'review', 'csrf', $mode]);
                $this->assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
                $this->assertStringContainsString('HTTP_STATUS:403', $result['stdout'], $view);
                $this->assertStringContainsString('Handlingen blev ikke udført', $result['stdout']);
                $this->assertSame($before, ebRows('SELECT * FROM bank_audit_log ORDER BY id'));
            }
        }
        $this->assertSame('active', ebAccountStatus($this->accountId)['state']);
        $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
    }

    /** @return void */
    public function test_non_dkk_base_currency_refuses_mapping_and_picking(): void
    {
        $this->assertSame('DKK', ebBaseCurrency());
        ebInsert('settings', ['var_grp' => 'currencies', 'var_name' => 'baseCurrency', 'user_id' => 0, 'var_value' => 'EUR']);
        $this->rejects(fn() => ebMapAccount($this->accountId, $this->otherLedger, ebToday(), 'chartest'));
        $this->assertSame((string)$this->ledger, ebRow('SELECT ledger_account FROM bank_accounts WHERE id=' . $this->accountId)['ledger_account']);
        $this->persist([$this->page()['transactions'][0]]);
        $this->assertSame(0, $this->importPending()['imported']);
        $this->assertSame(['review_state' => 'pending', 'review_reason' => 'base_currency'], ebRow('SELECT review_state, review_reason FROM bank_transactions'));
        $this->assertSame('ok', ebSyncAccount($this->accountId, 'schedule', [], $this->client([$this->step($this->page())]))['status']);
        $this->assertSame(2, ebCountsByState($this->accountId)['pending']);
        $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
        $html = CharacterizationEnv::runChild(__DIR__ . '/support/run_bank_view.php', ['accounts', $this->accountId, EbTestKeys::$privatePath, 'review']);
        $this->assertSame(0, $html['exit'], $html['stderr']);
        $this->assertStringContainsString('regnskabets basisvaluta er DKK', $html['stdout']);
        $this->assertStringNotContainsString("name='ledger_account'", $html['stdout']);
        ebWrite("UPDATE settings SET var_value='DKK' WHERE var_name='baseCurrency'");
        $this->assertSame(2, $this->importPending()['imported']);
    }

    /** @return void */
    public function test_third_decimal_is_reviewed_without_rounding_and_two_decimals_import_unchanged(): void
    {
        $raw = $this->page()['transactions'][0];
        $rows = [];
        foreach (['125.123', '42.321', '125.120', '42.32'] as $i => $amount) {
            $raw['entry_reference'] = 'precision-' . $i;
            $raw['transaction_amount']['amount'] = $amount;
            $raw['credit_debit_indicator'] = $i % 2 ? 'DBIT' : 'CRDT';
            $rows[] = $raw;
        }
        $this->persist($rows);
        $this->assertSame(2, $this->importPending()['imported']);
        $this->assertSame(['bad_amount', 'bad_amount'], array_column(ebRows("SELECT review_reason FROM bank_transactions WHERE review_state='review' ORDER BY id"), 'review_reason'));
        $this->assertSame(['125.120', '42.320'], array_column(ebRows('SELECT amount FROM kassekladde ORDER BY id'), 'amount'));
        $this->assertSame(['125.123', '-42.321'], array_column(ebRows("SELECT amount FROM bank_transactions WHERE review_state='review' ORDER BY id"), 'amount'));
        // Review rows are not pickable.
        $this->assertNotPickable([1, 2]);
    }

    /** @return void */
    public function test_incomplete_no_reference_day_waits_for_complete_occurrence_count(): void
    {
        ebUpdate('bank_accounts', $this->accountId, ['state' => 'active']);
        $raw = $this->page()['transactions'][0];
        unset($raw['entry_reference']);
        foreach ([[$raw], [$raw, $raw]] as $transactions) {
            $this->assertSame('ok', ebSyncAccount($this->accountId, 'schedule', [], $this->client([$this->step(['transactions' => $transactions])]))['status']);
            $this->assertCount(0, ebRows('SELECT id FROM bank_transactions'));
            $this->assertSame(ebToday(), ebRow('SELECT fetched_through FROM bank_accounts WHERE id=' . $this->accountId)['fetched_through']);
        }
        $future = $raw;
        $future['booking_date'] = date('Y-m-d', strtotime('+1 day'));
        $this->assertSame(0, $this->persist([$future])['new']);
        $reference = $raw;
        $reference['entry_reference'] = 'today-reference';
        $this->assertSame(1, $this->persist([$reference])['new']);
        // Advance the logical provider day to a completed date within the next overlap window.
        $raw['booking_date'] = date('Y-m-d', strtotime('-1 day'));
        ebUpdate('bank_accounts', $this->accountId, ['import_from' => min(date('Y-m-01'), $raw['booking_date'])]);
        $this->assertSame('ok', ebSyncAccount($this->accountId, 'schedule', [], $this->client([$this->step(['transactions' => [$raw, $raw]])]))['status']);
        $this->assertSame(['pending', 'pending'], array_column(ebRows('SELECT review_state FROM bank_transactions WHERE entry_reference IS NULL'), 'review_state'));
        $this->assertCount(0, ebRows("SELECT id FROM bank_transactions WHERE review_reason='possible_duplicate'"));
    }

    /** @return void */
    public function test_value_date_override_survives_identical_provider_payload(): void
    {
        $raw = $this->page()['transactions'][0];
        unset($raw['booking_date']);
        $this->persist([$raw]);
        $before = ebRow('SELECT * FROM bank_transactions');
        ebTransactionAction((int)$before['id'], 'import_value_date', 'chartest');
        $chosen = ebRow('SELECT * FROM bank_transactions');
        $this->assertNull($chosen['booking_date']);
        $this->assertSame(ebToday(), $chosen['date_override']);
        $this->assertSame($before['payload_hash'], $chosen['payload_hash']);
        $this->assertSame($before['fingerprint'], $chosen['fingerprint']);
        $this->assertSame(0, $this->persist([$raw])['changed']);
        $this->assertSame(1, $this->importPending()['imported']);
        $this->assertSame(ebToday(), ebRow('SELECT transdate FROM kassekladde')['transdate']);
        $this->assertSame(0, $this->persist([$raw])['changed']);
        $this->assertSame('imported', ebRow('SELECT review_state FROM bank_transactions')['review_state']);
        $this->assertSame(ebToday(), ebRow('SELECT date_override FROM bank_transactions')['date_override']);
    }

    /** @return void */
    public function test_worker_lock_open_failure_exits_two_and_contention_exits_zero(): void
    {
        $root = sys_get_temp_dir() . '/eb_lock_' . bin2hex(random_bytes(8));
        mkdir($root . '/bank_integration', 0700, true);
        mkdir($root . '/temp', 0700);
        symlink(CharacterizationEnv::repoRoot() . '/includes', $root . '/includes');
        symlink(CharacterizationEnv::repoRoot() . '/bank_integration/includes', $root . '/bank_integration/includes');
        copy(CharacterizationEnv::repoRoot() . '/bank_integration/worker.php', $root . '/bank_integration/worker.php');
        $path = $root . '/temp/.ht_bank_worker.lock';
        try {
            mkdir($path, 0700); // fopen must fail: the lock path is a directory.
            $failed = CharacterizationEnv::runChild($root . '/bank_integration/worker.php', ['--dry-run']);
            $this->assertSame(2, $failed['exit'], $failed['stderr'] . $failed['stdout']);
            $this->assertStringContainsString('cannot open or create the lock file', $failed['stderr']);
            $this->assertStringNotContainsString('already running', $failed['stdout']);
            rmdir($path);
            $lock = fopen($path, 'c');
            flock($lock, LOCK_EX);
            try {
                $busy = CharacterizationEnv::runChild($root . '/bank_integration/worker.php', ['--dry-run']);
                $this->assertSame(0, $busy['exit'], $busy['stderr'] . $busy['stdout']);
                $this->assertStringContainsString('already running', $busy['stdout']);
            } finally {
                fclose($lock);
            }
        } finally {
            if (is_dir($path)) {
                rmdir($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
            unlink($root . '/includes');
            unlink($root . '/bank_integration/includes');
            unlink($root . '/bank_integration/worker.php');
            rmdir($root . '/bank_integration');
            rmdir($root . '/temp');
            rmdir($root);
        }
    }

    /** @return void */
    public function test_settings_search_hides_bank_entry_when_feature_is_unavailable(): void
    {
        foreach (['' => true, 'unconfigured' => false] as $mode => $visible) {
            $result = CharacterizationEnv::runChild(__DIR__ . '/support/run_bank_view.php', ['search', $this->accountId, EbTestKeys::$privatePath, '', $mode]);
            $this->assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
            $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($visible, in_array('bank_integration', array_column($json['results'], 'key'), true));
        }
    }

    /** @return void */
    public function test_switching_the_company_on_says_fetching_resumes_only_for_an_account_in_use(): void
    {
        require_once __DIR__ . '/../../../systemdata/diverseIncludes/bank_integration.php';
        $resumes = 'Bankforbindelse er slået til. Saldi henter igen fra de forbundne bankkonti.';
        $plain = 'Bankforbindelse er slået til.';
        $this->assertSame($resumes, bankIntegrationEnabledMessage());
        // A connected bank whose accounts are all paused or not in use: nothing resumes, and no new connection is needed.
        ebSetPaused($this->accountId, true, 'chartest');
        $this->assertSame($plain, bankIntegrationEnabledMessage());
        ebUpdate('bank_accounts', $this->accountId, ['state' => 'new']);
        $this->assertSame($plain, bankIntegrationEnabledMessage());
        ebUpdate('bank_accounts', $this->accountId, ['state' => 'active']);
        ebUpdate('bank_connections', $this->connectionId, ['status' => 'disconnected']);
        $this->assertSame('Bankforbindelse er slået til. Forbind en bank for at komme i gang.', bankIntegrationEnabledMessage());
        // The switch is already committed when the message is built; a failing lookup gives the neutral message, not an error.
        ebUpdate('bank_connections', $this->connectionId, ['status' => 'active']);
        ebWrite('ALTER TABLE bank_accounts RENAME TO bank_accounts_eb_hidden');
        $failed = [];
        // The failing query's own pg_query() warning is the point of this step; record it instead of reporting it.
        set_error_handler(static function (int $no, string $text) use (&$failed): bool {
            $failed[] = $text;
            return true;
        }, E_WARNING);
        try {
            $this->assertSame($plain, bankIntegrationEnabledMessage());
        } finally {
            restore_error_handler();
            ebWrite('ALTER TABLE bank_accounts_eb_hidden RENAME TO bank_accounts');
        }
        $this->assertStringContainsString('bank_accounts', implode(' ', $failed));
    }

    /** @return void */
    public function test_empty_fetch_stays_active_and_writes_no_journal(): void
    {
        $this->assertSame('ok', ebSyncAccount($this->accountId, 'schedule', [], $this->client([$this->step(['transactions' => []])]))['status']);
        $this->assertSame('active', ebAccountStatus($this->accountId)['state']);
        $this->assertCount(0, ebRows('SELECT id FROM kladdeliste'));
        $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
    }

    /** @return void */
    public function test_backend_text_replaces_non_bmp_only_for_mysqli_before_hashing(): void
    {
        $text = 'Æøå 😀 𐐀';
        $replacement = 'Æøå � �';
        $this->assertSame($text, ebBackendText($text, false));
        $this->assertSame($replacement, ebBackendText($text, true));
        $raw = $this->page()['transactions'][0];
        $raw['debtor']['name'] = $text;
        $raw['remittance_information'] = ['&#x1F600;'];
        $raw['reference_number'] = $text;
        $raw['entry_reference'] = $text;
        $postgres = ebNormalizeTransaction($raw);
        try {
            $GLOBALS['db_type'] = 'mysqli';
            $mysql = ebNormalizeTransaction($raw);
            $again = ebNormalizeTransaction($raw);
        } finally {
            $GLOBALS['db_type'] = 'pgsql';
        }
        $this->assertSame($replacement, $mysql['counterparty']);
        $this->assertSame($replacement, $mysql['entry_reference']);
        $this->assertSame($replacement, $mysql['bank_reference']);
        // The payment text only repeats a character of the name, so the name shows once.
        $this->assertSame($replacement, $mysql['description']);
        $this->assertSame($text, $postgres['counterparty']);
        $this->assertSame($text, $postgres['description']);
        $this->assertSame(ebFingerprint($mysql), ebFingerprint($again));
        $this->assertSame(ebPayloadHash($mysql), ebPayloadHash($again));
        $this->assertNotSame(ebFingerprint($postgres), ebFingerprint($mysql));
        $resource = $this->resource('unicode-account');
        $resource['name'] = $text;
        $cid = $this->authorizeAccounts([$resource]);
        $this->assertSame($text, ebListAccounts($cid)[0]['display_name']);
    }

    /** @return void */
    public function test_long_entry_reference_is_stored_safely_and_deduplicated_using_its_full_value(): void
    {
        $raw = $this->page()['transactions'][0];
        $raw['entry_reference'] = str_repeat('ø', 400);
        $this->assertSame(1, $this->persist([$raw])['new']);
        $stored = ebRow('SELECT entry_reference, dedup_key FROM bank_transactions');
        $this->assertSame(str_repeat('ø', 255), $stored['entry_reference']);
        $this->assertSame('r:' . hash('sha256', $raw['entry_reference']), $stored['dedup_key']);
        $this->assertSame(0, $this->persist([$raw])['new']);
        $this->assertCount(1, ebRows('SELECT id FROM bank_transactions'));
        $raw['entry_reference'] = str_repeat('ø', 399) . 'å';
        $this->assertSame(1, $this->persist([$raw])['new']);
        $this->assertCount(2, ebRows('SELECT id FROM bank_transactions'));
    }

    /** @return void */
    public function test_import_into_journal_takes_only_picked_rows_and_accepts_legacy_after_review(): void
    {
        $raw = $this->page()['transactions'][0];
        $extra = $raw;
        $extra['entry_reference'] = 'unpicked';
        $this->persist(array_merge($this->page()['transactions'], [$extra]));
        ebUpdate('bank_transactions', 2, ['review_state' => 'review', 'review_reason' => 'after_review']);
        $changed = $this->page()['transactions'][1];
        $changed['remittance_information'] = ['Changed legacy review row'];
        $this->persist([$changed]);
        $this->assertSame('after_review', ebRow('SELECT review_reason FROM bank_transactions WHERE id=2')['review_reason']);
        $journal = $this->journal();
        $result = ebImportIntoJournal($journal, $this->accountId, [1, 2, 1], 'chartest');
        $this->assertSame(['imported' => 2, 'skipped' => []], $result);
        $this->assertSame(['imported', 'imported', 'pending'], array_column(ebRows('SELECT review_state FROM bank_transactions ORDER BY id'), 'review_state'));
        $this->assertSame(['1', '2'], array_column(ebRows('SELECT transaction_id FROM bank_import_links ORDER BY transaction_id'), 'transaction_id'));
        $this->assertSame([(string)$journal], array_values(array_unique(array_column(ebRows('SELECT kladde_id FROM kassekladde'), 'kladde_id'))));
        $this->assertSame(['chartest', 'chartest'], array_column(ebRows('SELECT created_by FROM bank_import_links ORDER BY id'), 'created_by'));
        $this->assertCount(1, ebRows('SELECT id FROM kladdeliste'));
        $audit = ebRows("SELECT actor, detail FROM bank_audit_log WHERE action='import_selected'");
        $this->assertSame([['actor' => 'chartest', 'detail' => 'Journal ' . $journal . '; selected: 2; imported: 2; skipped: 0']], $audit);
    }

    /** @return void */
    public function test_import_rejects_invalid_and_foreign_ids_and_skips_rows_that_are_not_new(): void
    {
        $this->persist($this->page()['transactions']);
        $other = $this->account($this->connectionId, 'foreign-selected-account');
        $journal = $this->journal();
        $beforeAudit = ebRows('SELECT * FROM bank_audit_log ORDER BY id');
        foreach ([[], range(1, 201), [0], [-1], ['1 OR 1=1'], [[1]]] as $ids) {
            $this->rejects(fn() => ebImportIntoJournal($journal, $this->accountId, $ids, 'chartest'));
        }
        $this->rejects(fn() => ebImportIntoJournal($journal, $this->accountId, [1, 2147483647], 'chartest'));
        $this->rejects(fn() => ebImportIntoJournal($journal, $other, [1], 'chartest'));
        ebUpdate('bank_transactions', 2, ['account_id' => $other]);
        $beforeRows = ebRows('SELECT * FROM bank_transactions ORDER BY id');
        $this->rejects(fn() => ebImportIntoJournal($journal, $this->accountId, [1, 2], 'chartest'));
        $this->assertSame($beforeRows, ebRows('SELECT * FROM bank_transactions ORDER BY id'));
        $this->assertSame($beforeAudit, ebRows('SELECT * FROM bank_audit_log ORDER BY id'));
        $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
        $this->assertCount(0, ebRows('SELECT id FROM bank_import_links'));
        // Rows that are not under Nye (review, before the start date, imported, skipped, linked) are reported, not imported.
        ebUpdate('bank_transactions', 2, ['account_id' => $this->accountId]);
        foreach ([['review', 'possible_duplicate', null], ['review', 'missing_date', null], ['pre_cutover', null, null],
            ['imported', null, null], ['ignored', null, null], ['pending', null, 12345]] as [$state, $reason, $link]) {
            ebUpdate('bank_transactions', 2, ['review_state' => $state, 'review_reason' => $reason, 'link_id' => $link]);
            $this->assertNotPickable([2]);
        }
        $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
    }

    /** @return void */
    public function test_two_hundred_picked_rows_go_into_one_journal_without_a_line_cap(): void
    {
        $raw = $this->page()['transactions'][0];
        $rows = [];
        for ($i = 0; $i < 201; $i++) {
            $raw['entry_reference'] = 'selected-limit-' . $i;
            $rows[] = $raw;
        }
        $this->persist($rows);
        $journal = $this->journal(['hvem' => 'chartest', 'tidspkt' => microtime()]);
        $this->rejects(fn() => ebImportIntoJournal($journal, $this->accountId, range(1, 201), 'chartest'));
        $this->assertSame(200, ebImportIntoJournal($journal, $this->accountId, range(1, 200), 'chartest')['imported']);
        $this->assertSame(1, ebCountsByState($this->accountId)['pending']);
        $this->assertCount(1, ebRows('SELECT id FROM kladdeliste'));
        $this->assertCount(200, ebRows('SELECT id FROM kassekladde WHERE kladde_id=' . $journal));
        $this->assertCount(200, ebRows('SELECT DISTINCT bilag FROM kassekladde WHERE kladde_id=' . $journal));
    }

    /** @return void */
    public function test_ignore_selected_validates_the_whole_batch_and_audits_once(): void
    {
        $raw = $this->page()['transactions'][0];
        $rows = [];
        for ($i = 0; $i < 4; $i++) {
            $raw['entry_reference'] = 'ignore-' . $i;
            $rows[] = $raw;
        }
        $this->persist($rows);
        ebUpdate('bank_transactions', 2, ['review_state' => 'review', 'review_reason' => 'possible_duplicate']);
        ebUpdate('bank_transactions', 3, ['review_state' => 'pre_cutover']);
        $before = ebRows('SELECT * FROM bank_transactions ORDER BY id');
        $other = $this->account($this->connectionId, 'foreign-ignore-account');
        foreach ([[], range(1, 201), [1, 2147483647]] as $ids) {
            $this->rejects(fn() => ebIgnoreSelected($this->accountId, $ids, 'chartest'));
            $this->assertSame($before, ebRows('SELECT * FROM bank_transactions ORDER BY id'));
        }
        foreach ([['imported', null], ['ignored', null], ['pending', 12345]] as [$state, $link]) {
            ebUpdate('bank_transactions', 4, ['review_state' => $state, 'link_id' => $link]);
            $beforeAttempt = ebRows('SELECT * FROM bank_transactions ORDER BY id');
            $this->rejects(fn() => ebIgnoreSelected($this->accountId, [1, 4], 'chartest'));
            $this->assertSame($beforeAttempt, ebRows('SELECT * FROM bank_transactions ORDER BY id'));
        }
        ebUpdate('bank_transactions', 4, ['review_state' => 'pending', 'link_id' => null, 'account_id' => $other]);
        $this->rejects(fn() => ebIgnoreSelected($this->accountId, [1, 4], 'chartest'));
        $this->assertCount(0, ebRows("SELECT id FROM bank_audit_log WHERE action='ignored'"));
        $this->assertSame(3, ebIgnoreSelected($this->accountId, [1, 2, 3, 1], 'chartest'));
        $this->assertSame(['ignored', 'ignored', 'ignored', 'pending'], array_column(ebRows('SELECT review_state FROM bank_transactions ORDER BY id'), 'review_state'));
        $this->assertSame([['detail' => 'Ignored: 3']], ebRows("SELECT detail FROM bank_audit_log WHERE action='ignored'"));
    }

    /** @return void */
    public function test_reconnect_reactivates_mapped_accounts(): void
    {
        foreach (['disconnected', 'unsupported'] as $state) {
            $oldConnection = (int)ebRow('SELECT connection_id FROM bank_accounts WHERE id=' . $this->accountId)['connection_id'];
            ebUpdate('bank_accounts', $this->accountId, ['state' => $state]);
            $newConnection = $this->authorizeAccounts([$this->resource('fixture-shared-hash')], $oldConnection);
            $account = ebRow('SELECT * FROM bank_accounts WHERE id=' . $this->accountId);
            $this->assertSame('active', $account['state']);
            $this->assertSame((string)$newConnection, $account['connection_id']);
            $this->assertNotNull($account['next_fetch_at']);
        }
        ebUpdate('bank_accounts', $this->accountId, ['state' => 'disconnected', 'ledger_account' => null]);
        $this->authorizeAccounts([$this->resource('fixture-shared-hash')]);
        $this->assertSame('new', ebAccountStatus($this->accountId)['state']);
    }

    /** @return void */
    public function test_pause_resume_controls_scheduling_and_picking_works_after_disconnect(): void
    {
        $this->persist($this->page()['transactions']);
        ebSetPaused($this->accountId, true, 'chartest');
        $this->assertSame('paused', ebAccountStatus($this->accountId)['state']);
        $this->assertNull(ebAccountStatus($this->accountId)['next_fetch_at']);
        $this->assertSame([], ebDueAccounts());
        $transport = new EbFixtureTransport([]);
        foreach (['schedule', 'manual'] as $trigger) {
            $this->assertSame('skipped', ebSyncAccount($this->accountId, $trigger, [], new EnableBankingClient(EbTestKeys::config(), $transport))['status']);
        }
        $this->assertSame([], $transport->requests);
        $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
        $journal = $this->journal();
        $this->assertSame(1, ebImportIntoJournal($journal, $this->accountId, [1], 'chartest')['imported']);
        ebSetPaused($this->accountId, false, 'chartest');
        $this->assertSame('active', ebAccountStatus($this->accountId)['state']);
        $this->assertEqualsWithDelta(time(), strtotime(ebAccountStatus($this->accountId)['next_fetch_at']), 3);
        $this->assertSame([$this->accountId], ebDueAccounts());
        $this->assertCount(1, ebRows("SELECT id FROM bank_audit_log WHERE action='paused'"));
        $this->assertCount(1, ebRows("SELECT id FROM bank_audit_log WHERE action='resumed'"));
        ebDisconnect($this->connectionId, 'chartest', null);
        $this->assertSame(1, ebImportIntoJournal($journal, $this->accountId, [2], 'chartest')['imported']);
        $this->assertSame('disconnected', ebAccountStatus($this->accountId)['state']);
        $this->rejects(fn() => ebSetPaused($this->accountId, true, 'chartest'));
        foreach (['new', 'unsupported'] as $state) {
            ebUpdate('bank_accounts', $this->accountId, ['state' => $state, 'currency' => 'DKK']);
            $this->rejects(fn() => ebSetPaused($this->accountId, false, 'chartest'));
        }
    }

    /** @return void */
    public function test_resume_preserves_stopped_access_and_period_errors(): void
    {
        foreach (['expired', 'revoked'] as $connectionStatus) {
            ebUpdate('bank_connections', $this->connectionId, ['status' => $connectionStatus]);
            ebUpdate('bank_accounts', $this->accountId, ['last_error_code' => null]);
            ebSetPaused($this->accountId, true, 'chartest');
            ebSetPaused($this->accountId, false, 'chartest');
            $this->assertSame('active', ebAccountStatus($this->accountId)['state']);
            $this->assertNull(ebAccountStatus($this->accountId)['next_fetch_at']);
            $this->assertSame([], ebDueAccounts());
        }
        ebUpdate('bank_accounts', $this->accountId, ['last_error_code' => 'consent_expired']);
        ebUpdate('bank_connections', $this->connectionId, ['status' => 'expired']);
        $view = CharacterizationEnv::runChild(__DIR__ . '/support/run_bank_view.php',
            ['feed', $this->accountId, EbTestKeys::$privatePath, 'pending']);
        $this->assertSame(0, $view['exit'], $view['stderr'] . $view['stdout']);
        $this->assertStringContainsString('Adgangen til banken er udløbet. Saldi henter ikke flere posteringer, før adgangen er fornyet.', $view['stdout']);

        ebUpdate('bank_connections', $this->connectionId, ['status' => 'active']);
        foreach (['consent_expired', 'CONSENT_EXPIRED', 'EXPIRED_SESSION', 'REVOKED_SESSION', 'CLOSED_SESSION',
            'SESSION_DOES_NOT_EXIST', 'ACCOUNT_DOES_NOT_EXIST', 'ASPSP_ACCOUNT_NOT_ACCESSIBLE', 'UNAUTHORIZED_ACCESS',
            'WRONG_SESSION_STATUS', 'ASPSP_PSU_ACTION_REQUIRED', 'WRONG_TRANSACTIONS_PERIOD', 'WRONG_DATE_INTERVAL'] as $code) {
            ebUpdate('bank_accounts', $this->accountId, ['last_error_code' => $code, 'last_error' => 'Stored error']);
            ebSetPaused($this->accountId, true, 'chartest');
            ebSetPaused($this->accountId, false, 'chartest');
            $status = ebAccountStatus($this->accountId);
            $this->assertSame('active', $status['state'], $code);
            $this->assertNull($status['next_fetch_at'], $code);
            $this->assertSame($code, $status['last_error_code']);
            $this->assertSame('Stored error', $status['last_error']);
            $this->assertSame([], ebDueAccounts(), $code);
        }
        foreach ([null, 'ASPSP_TIMEOUT'] as $code) {
            ebUpdate('bank_accounts', $this->accountId, ['last_error_code' => $code]);
            ebSetPaused($this->accountId, true, 'chartest');
            ebSetPaused($this->accountId, false, 'chartest');
            $this->assertEqualsWithDelta(time(), strtotime(ebAccountStatus($this->accountId)['next_fetch_at']), 3);
            $this->assertSame([$this->accountId], ebDueAccounts());
        }
    }

    /** @return void */
    public function test_disconnect_reports_provider_failure_and_preserves_local_data(): void
    {
        $this->persist($this->page()['transactions']);
        $this->assertSame(2, ebWaitingCountForConnection($this->connectionId));
        ebImportIntoJournal($this->journal(), $this->accountId, [1], 'chartest');
        $this->assertSame(1, ebWaitingCountForConnection($this->connectionId));
        $this->assertSame(0, ebWaitingCountForConnection(2147483647));
        $before = ebRows('SELECT * FROM bank_transactions ORDER BY id');
        ebUpdate('bank_accounts', $this->accountId, ['last_error_code' => 'ASPSP_ERROR', 'last_error' => 'Enable Banking: ASPSP_ERROR (HTTP 400).']);
        $transport = new EbFixtureTransport([$this->step(['error' => 'INTERNAL_SERVER_ERROR'], 500, '/sessions/', 'DELETE')]);
        ebDisconnect($this->connectionId, 'chartest', new EnableBankingClient(EbTestKeys::config(), $transport));
        $connection = ebRow('SELECT * FROM bank_connections WHERE id=' . $this->connectionId);
        $this->assertSame('disconnected', $connection['status']);
        $this->assertSame('Banken bekræftede ikke afbrydelsen. Adgangen udløber senest ' . date('d.m.Y', strtotime($connection['valid_until'])) . '.', $connection['last_error']);
        $this->assertCount(1, $transport->requests);
        $this->assertSame($before, ebRows('SELECT * FROM bank_transactions ORDER BY id'));
        $this->assertSame('disconnected', ebAccountStatus($this->accountId)['state']);
        $this->assertNull(ebAccountStatus($this->accountId)['next_fetch_at']);
        // The old fetch error promised an automatic retry that no longer happens.
        $this->assertSame(['last_error_code' => null, 'last_error' => null], ebRow('SELECT last_error_code, last_error FROM bank_accounts WHERE id=' . $this->accountId));
        $this->assertCount(0, ebRows('SELECT id FROM bank_account_sessions WHERE active=1'));
        $this->assertCount(1, ebRows('SELECT id FROM kassekladde'));
        ebUpdate('bank_connections', $this->connectionId, ['valid_until' => null, 'session_enc' => '']);
        $emptyTransport = new EbFixtureTransport([]);
        ebDisconnect($this->connectionId, 'chartest', new EnableBankingClient(EbTestKeys::config(), $emptyTransport));
        $this->assertSame([], $emptyTransport->requests);
        $this->assertSame('Banken bekræftede ikke afbrydelsen.', ebRow('SELECT last_error FROM bank_connections WHERE id=' . $this->connectionId)['last_error']);
        $this->assertFalse(ebCloseProviderSession(['session_enc' => ''], null));
    }

    /** @return void */
    public function test_successful_provider_revoke_clears_warning_and_waiting_count_spans_accounts(): void
    {
        $this->persist($this->page()['transactions']);
        $other = $this->account($this->connectionId, 'waiting-other-account');
        ebUpdate('bank_transactions', 2, ['account_id' => $other, 'review_state' => 'review', 'review_reason' => 'bad_amount']);
        $this->assertSame(2, ebWaitingCountForConnection($this->connectionId));
        ebIgnoreSelected($other, [2], 'chartest');
        $this->assertSame(1, ebWaitingCountForConnection($this->connectionId));
        ebUpdate('bank_connections', $this->connectionId, ['last_error' => 'Previous revocation failed']);
        ebDisconnect($this->connectionId, 'chartest', $this->client([$this->step(['message' => 'OK'], 200, '/sessions/', 'DELETE')]));
        $this->assertNull(ebRow('SELECT last_error FROM bank_connections WHERE id=' . $this->connectionId)['last_error']);
    }

    /** @return void */
    public function test_disabled_or_unconfigured_worker_child_is_silent_and_installs_no_bank_tables(): void
    {
        ebSetCompanyEnabled(false, 'chartest');
        foreach (array_reverse(array_keys(ebSchemaTables())) as $table) {
            ebWrite('DROP TABLE ' . $table);
        }
        try {
            foreach ([[], ['--dry-run'], ['--force']] as $args) {
                $result = $this->worker($args);
                $this->assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
                $this->assertSame('', $result['stdout']);
                foreach (array_keys(ebSchemaTables()) as $table) {
                    $this->assertFalse(ebTableExists($table));
                }
            }
            ebWrite("UPDATE settings SET var_value='on' WHERE var_grp='enable_banking' AND var_name='enabled' AND user_id=0");
            $result = $this->worker([], false);
            $this->assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
            $this->assertSame('', $result['stdout']);
            foreach (array_keys(ebSchemaTables()) as $table) {
                $this->assertFalse(ebTableExists($table));
            }
        } finally {
            ebEnsureSchema();
        }
    }

    /** @return void */
    public function test_worker_between_fetches_never_writes_a_journal(): void
    {
        $this->persist($this->page()['transactions']);
        ebUpdate('bank_accounts', $this->accountId, ['next_fetch_at' => date('Y-m-d H:i:s', time() + 21600)]);
        $result = $this->worker();
        $this->assertSame(0, $result['exit'], $result['stderr']);
        $this->assertSame('', $result['stdout']);
        $this->assertCount(0, ebRows('SELECT id FROM bank_sync_runs'));
        $this->assertCount(0, ebRows('SELECT id FROM kladdeliste'));
        $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
        $this->assertSame(2, ebCountsByState($this->accountId)['pending']);
    }

    /** @return void */
    public function test_list_date_filters_and_import_use_accounting_date_overrides(): void
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        ebUpdate('bank_accounts', $this->accountId, ['import_from' => $yesterday]);
        $this->persist($this->page()['transactions']);
        ebUpdate('bank_transactions', 1, ['booking_date' => $yesterday, 'date_override' => ebToday()]);
        ebUpdate('bank_transactions', 2, ['date_override' => $yesterday]);
        $this->assertSame(['1'], array_column(ebListTransactions($this->accountId, 'pending', 200, 0, ebToday(), ebToday()), 'id'));
        $this->assertSame(['2'], array_column(ebListTransactions($this->accountId, 'pending', 200, 0, $yesterday, $yesterday), 'id'));
        $this->assertCount(2, ebListTransactions($this->accountId, 'pending', 200, 0, '2026-02-30', 'not-a-date'));
        $this->assertSame(1, ebImportIntoJournal($this->journal(), $this->accountId, [1], 'chartest')['imported']);
        $this->assertSame(ebToday(), ebRow('SELECT transdate FROM kassekladde')['transdate']);
        $this->assertSame('pending', ebRow('SELECT review_state FROM bank_transactions WHERE id=2')['review_state']);
        $this->assertSame([], ebOverlapHints($this->accountId, []));
        $this->rejects(fn() => ebOverlapHints($this->accountId, range(1, 201)));
    }

    /** @return void */
    public function test_import_rolls_back_every_line_when_a_later_write_fails(): void
    {
        $raw = $this->page()['transactions'][0];
        $rows = [];
        for ($i = 0; $i < 11; $i++) {
            $raw['entry_reference'] = 'rollback-selected-' . $i;
            $raw['transaction_amount']['amount'] = $i === 10 ? '42.32' : '125.12';
            $rows[] = $raw;
        }
        $this->persist($rows);
        $journal = $this->journal();
        $before = ebRows('SELECT * FROM bank_audit_log ORDER BY id');
        ebWrite('ALTER TABLE kassekladde ADD CONSTRAINT eb_selected_failure CHECK (amount <> 42.32)');
        try {
            try {
                ebImportIntoJournal($journal, $this->accountId, range(1, 11), 'chartest');
                $this->fail('Expected the later journal write to fail');
            } catch (EbDbException $e) {
                $this->assertSame(0, $GLOBALS['db_transaktion_depth']);
            }
            $this->assertSame(11, ebCountsByState($this->accountId)['pending']);
            $this->assertCount(0, ebRows('SELECT id FROM kassekladde'));
            $this->assertCount(0, ebRows('SELECT id FROM bank_import_links'));
            $this->assertNull(ebAccountStatus($this->accountId)['last_import_at']);
            $this->assertSame($before, ebRows('SELECT * FROM bank_audit_log ORDER BY id'));
        } finally {
            ebWrite('ALTER TABLE kassekladde DROP CONSTRAINT eb_selected_failure');
        }
        $this->assertSame(11, ebImportIntoJournal($journal, $this->accountId, range(1, 11), 'chartest')['imported']);
    }

    /** @return void */
    public function test_transaction_list_paginates_pending_rows_and_overlap_hints_are_page_bound(): void
    {
        $raw = $this->page()['transactions'][0];
        $rows = [];
        for ($i = 0; $i < 405; $i++) {
            $raw['entry_reference'] = 'pending-page-' . $i;
            $rows[] = $raw;
        }
        $this->persist($rows);
        $ids = [];
        foreach ([0, 200, 400] as $offset) {
            $page = ebListTransactions($this->accountId, 'pending', 200, $offset);
            $this->assertCount($offset === 400 ? 5 : 200, $page);
            $ids = array_merge($ids, array_column($page, 'id'));
        }
        $this->assertCount(405, array_unique($ids));
        $this->assertSame('405', $ids[0]);
        $this->assertSame('1', $ids[404]);
        $journal = $this->journal();
        ebInsert('kassekladde', ['kladde_id' => $journal, 'transdate' => ebToday(), 'debet' => $this->ledger,
            'kredit' => $this->otherLedger, 'd_type' => 'F', 'k_type' => 'F', 'amount' => '125.12', 'bilag' => 1]);
        $hints = ebOverlapHints($this->accountId, [404, 405]);
        ksort($hints);
        $this->assertSame([404, 405], array_keys($hints));
    }

    /** @return void */
    public function test_period_errors_stop_manual_and_scheduled_retries_until_the_start_date_changes(): void
    {
        require_once __DIR__ . '/../../../bank_integration/includes/ebAccess.php';
        $old = date('Y-m-d', strtotime('-10 days'));
        $later = date('Y-m-d', strtotime('-5 days'));
        $text = 'Banken udleverer ikke posteringer så langt tilbage. Vælg en senere startdato under Indstillinger.';
        foreach (['WRONG_TRANSACTIONS_PERIOD', 'WRONG_DATE_INTERVAL'] as $code) {
            foreach (['schedule', 'manual'] as $trigger) {
                ebUpdate('bank_accounts', $this->accountId, ['import_from' => $old, 'last_manual_at' => null,
                    'last_fetch_at' => null, 'next_fetch_at' => ebNow(), 'last_error_code' => null, 'last_error' => null]);
                $this->assertContains($this->accountId, ebDueAccounts());
                $result = ebSyncAccount($this->accountId, $trigger, [], $this->client([$this->step(['error' => $code], 400)]));
                $this->assertSame('period', (new EbApiException(500, $code, 'provider detail'))->kind());
                $this->assertSame('failed', $result['status']);
                $this->assertSame('period', $result['error_kind']);
                $this->assertSame($code, $result['error_code']);
                $account = ebRow('SELECT * FROM bank_accounts WHERE id=' . $this->accountId);
                $this->assertNull($account['next_fetch_at']);
                $this->assertSame($code, $account['last_error_code']);
                $this->assertSame($code, ebRow('SELECT error_code FROM bank_sync_runs ORDER BY id DESC LIMIT 1')['error_code']);
                $this->assertNotContains($this->accountId, ebDueAccounts());
                $this->assertSame($text, ebErrorText($code, null));
                $this->assertSame($text, ebErrorText(null, 'period'));
                ebMapAccount($this->accountId, $this->ledger, $old, 'chartest');
                $this->assertSame($code, ebRow('SELECT last_error_code FROM bank_accounts WHERE id=' . $this->accountId)['last_error_code']);
                $this->assertNull(ebRow('SELECT next_fetch_at FROM bank_accounts WHERE id=' . $this->accountId)['next_fetch_at']);
                $this->assertNotContains($this->accountId, ebDueAccounts());
                ebMapAccount($this->accountId, $this->ledger, $later, 'chartest');
                $account = ebRow('SELECT * FROM bank_accounts WHERE id=' . $this->accountId);
                $this->assertNull($account['last_error_code']);
                $this->assertNull($account['last_error']);
                $this->assertEqualsWithDelta(time(), strtotime($account['next_fetch_at']), 3);
                $this->assertContains($this->accountId, ebDueAccounts());
            }
        }
    }

    /** @return void */
    public function test_correcting_a_period_error_while_paused_does_not_schedule_a_fetch(): void
    {
        foreach (['WRONG_TRANSACTIONS_PERIOD', 'WRONG_DATE_INTERVAL'] as $code) {
            ebUpdate('bank_accounts', $this->accountId, ['state' => 'paused', 'next_fetch_at' => null,
                'import_from' => date('Y-m-d', strtotime('-10 days')), 'last_error_code' => $code, 'last_error' => 'Period rejected']);
            ebMapAccount($this->accountId, $this->ledger, ebToday(), 'chartest');
            $account = ebRow('SELECT * FROM bank_accounts WHERE id=' . $this->accountId);
            $this->assertNull($account['last_error_code']);
            $this->assertNull($account['last_error']);
            $this->assertNull($account['next_fetch_at']);
            $this->assertSame('paused', $account['state']);
            $this->assertNotContains($this->accountId, ebDueAccounts());
        }
        ebUpdate('bank_accounts', $this->accountId, ['last_error_code' => 'ASPSP_TIMEOUT', 'last_error' => 'Timeout']);
        ebMapAccount($this->accountId, $this->ledger, date('Y-m-d', strtotime('-1 day')), 'chartest');
        $this->assertSame('ASPSP_TIMEOUT', ebRow('SELECT last_error_code FROM bank_accounts WHERE id=' . $this->accountId)['last_error_code']);
    }

    /** @return void */
    public function test_waiting_status_counts_only_unlinked_pending_rows_of_the_account(): void
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $older = date('Y-m-d', strtotime('-20 days'));
        $raw = $this->page()['transactions'][0];
        $rows = [];
        for ($i = 0; $i < 8; $i++) {
            $raw['entry_reference'] = 'waiting-' . $i;
            $rows[] = $raw;
        }
        $this->persist($rows);
        ebUpdate('bank_transactions', 1, ['booking_date' => $older, 'date_override' => ebToday()]);
        ebUpdate('bank_transactions', 2, ['date_override' => $yesterday]);
        foreach ([3 => 'review', 4 => 'ignored', 5 => 'imported', 6 => 'pre_cutover'] as $id => $state) {
            ebUpdate('bank_transactions', $id, ['review_state' => $state, 'booking_date' => $older]);
        }
        ebUpdate('bank_transactions', 7, ['link_id' => 123, 'booking_date' => $older]);
        $other = $this->account($this->connectionId, 'waiting-other');
        ebUpdate('bank_transactions', 8, ['account_id' => $other, 'booking_date' => $older]);
        $status = ebAccountStatus($this->accountId);
        $this->assertSame(2, $status['waiting']);
        $this->assertSame($yesterday, $status['waiting_oldest']);
        $this->assertArrayNotHasKey('auto_import', $status);
        $this->assertArrayNotHasKey('journal_id', $status);
    }

    /** @return void */
    public function test_status_normalizes_waiting_and_whole_fetch_hours_with_safe_defaults(): void
    {
        require_once __DIR__ . '/../../../bank_integration/includes/ebAccess.php';
        $status = ebAccountStatus($this->accountId);
        $this->assertNull($status['stale_hours']);
        $this->assertSame(0, $status['waiting']);
        $this->assertNull($status['waiting_oldest']);
        ebUpdate('bank_accounts', $this->accountId, ['last_fetch_at' => date('Y-m-d H:i:s', time() - 14 * 3600 - 120)]);
        $status = ebNormalizeStatus(ebAccountStatus($this->accountId));
        $this->assertSame(14, $status['stale_hours']);
        $normalized = ebNormalizeStatus(['waiting' => '3', 'waiting_oldest' => ebToday(), 'stale_hours' => '31']);
        $this->assertSame(3, $normalized['waiting']);
        $this->assertSame(ebToday(), $normalized['waiting_oldest']);
        $this->assertSame(31, $normalized['stale_hours']);
        $defaults = ebNormalizeStatus([]);
        $this->assertSame(0, $defaults['waiting']);
        $this->assertNull($defaults['waiting_oldest']);
        $this->assertNull($defaults['stale_hours']);
    }

    /**
     * Exercise the production indicator with an isolated heartbeat value and temporary server configuration.
     * @return array{level:int, reasons:list<string>, href:string, label:string}
     */
    private function indicator(?int $heartbeat = null): array
    {
        $path = CharacterizationEnv::repoRoot() . '/temp/.ht_bank_worker.heartbeat';
        $savedHeartbeat = is_file($path) ? file_get_contents($path) : null;
        $previous = [];
        $rights = $GLOBALS['rettigheder'] ?? null;
        foreach (['APP_ID' => 'fixture-application', 'KEY_PATH' => EbTestKeys::$privatePath,
            'REDIRECT_URL' => 'https://example.invalid/callback', 'ENV' => 'SANDBOX'] as $name => $value) {
            $previous[$name] = getenv('SALDI_ENABLEBANKING_' . $name);
            putenv('SALDI_ENABLEBANKING_' . $name . '=' . $value);
        }
        $GLOBALS['rettigheder'] = str_repeat('9', 50);
        $user = $GLOBALS['bruger_id'] ?? null;
        $GLOBALS['bruger_id'] = 1;
        ebWrite("DELETE FROM settings WHERE var_grp='bank_integration' AND var_name='show_status'");
        // The icon is a personal choice (Vis bankstatus i kassekladden), off until the user switches it on.
        ebInsert('settings', ['var_grp' => 'bank_integration', 'var_name' => 'show_status', 'user_id' => 1, 'var_value' => '1']);
        try {
            if ($heartbeat === null) {
                if (is_file($path)) {
                    unlink($path);
                }
            } else {
                file_put_contents($path, (string)$heartbeat);
            }
            $this->assertSame($heartbeat === null, ebWorkerHeartbeatAge() === null);
            if ($heartbeat !== null) {
                $this->assertEqualsWithDelta(max(0, time() - $heartbeat), ebWorkerHeartbeatAge(), 2);
            }
            ob_start();
            try {
                require_once __DIR__ . '/../../../bank_integration/includes/auth_check_icon.php';
            } finally {
                ob_end_clean();
            }
            return ebStatusIndicator();
        } finally {
            if ($savedHeartbeat === null) {
                if (is_file($path)) {
                    unlink($path);
                }
            } else {
                file_put_contents($path, $savedHeartbeat);
            }
            $GLOBALS['rettigheder'] = $rights;
            $GLOBALS['bruger_id'] = $user;
            foreach ($previous as $name => $value) {
                putenv('SALDI_ENABLEBANKING_' . $name . ($value === false ? '' : '=' . $value));
            }
        }
    }

    /**
     * The account's name in the indicator's tooltip: ledger first, then bank and the last four digits.
     * @return string
     */
    private function indicatorName(): string
    {
        $ledgerName = trim((string)(ebRow('SELECT beskrivelse FROM kontoplan WHERE kontonr=' . $this->ledger . ' AND regnskabsaar=' . (int)$GLOBALS['regnaar'])['beskrivelse'] ?? ''));
        return trim($this->ledger . ' ' . $ledgerName) . " · Fixture Bank \u{2026}1234";
    }

    /** @return void */
    public function test_indicator_reports_stale_thresholds_and_never_reports_paused_accounts_stale(): void
    {
        ebUpdate('bank_connections', $this->connectionId, ['valid_until' => date('Y-m-d H:i:s', time() + 90 * 86400)]);
        $indicator = $this->indicator();
        $this->assertSame(1, $indicator['level']); // Never fetched is unknown, not stale.
        $this->assertSame('ajour', $indicator['label']);
        // The only account: the link opens the feed on it (no journal outside the kassekladde).
        $this->assertSame('../bank_integration/feed.php?account=' . $this->accountId, $indicator['href']);
        foreach ([13 => 1, 14 => 2, 30 => 2, 31 => 3] as $hours => $level) {
            // The worker runs (fresh heartbeat) but this account's scheduled fetch, 6 h after the last one, is overdue.
            $last = time() - $hours * 3600 - 120;
            ebUpdate('bank_accounts', $this->accountId, ['last_fetch_at' => date('Y-m-d H:i:s', $last), 'next_fetch_at' => date('Y-m-d H:i:s', $last + 21600)]);
            $indicator = $this->indicator(time());
            $this->assertSame($level, $indicator['level']);
            $this->assertSame($hours > 13, in_array($this->indicatorName() . ': Ingen hentning i ' . $hours . ' timer', $indicator['reasons'], true));
            $this->assertSame($hours > 13 ? 'hentning forsinket' : 'ajour', $indicator['label']);
        }
        // Just resumed or renewed: the next fetch is due now, so an old last fetch is not reported as stale.
        ebUpdate('bank_accounts', $this->accountId, ['next_fetch_at' => ebNow()]);
        $indicator = $this->indicator(time());
        $this->assertSame(1, $indicator['level']);
        $this->assertStringNotContainsString('Ingen hentning', implode(' ', $indicator['reasons']));
        ebSetPaused($this->accountId, true, 'chartest');
        $indicator = $this->indicator();
        $this->assertSame(1, $indicator['level']);
        $this->assertContains($this->indicatorName() . ': Automatisk hentning er sat på pause', $indicator['reasons']);
        $this->assertStringNotContainsString('Ingen hentning', implode(' ', $indicator['reasons']));
        $this->assertSame('sat på pause', $indicator['label']);
        // Access running out within 14 days asks for renewal (amber), expired access too (red).
        ebSetPaused($this->accountId, false, 'chartest');
        ebUpdate('bank_connections', $this->connectionId, ['valid_until' => date('Y-m-d H:i:s', time() + 10 * 86400)]);
        $indicator = $this->indicator(time());
        $this->assertSame([2, 'forny adgang'], [$indicator['level'], $indicator['label']]);
        ebUpdate('bank_connections', $this->connectionId, ['valid_until' => date('Y-m-d H:i:s', time() - 3600)]);
        $indicator = $this->indicator(time());
        $this->assertSame([3, 'forny adgang'], [$indicator['level'], $indicator['label']]);
        $this->assertSame('../bank_integration/feed.php?account=' . $this->accountId, $indicator['href']);
    }

    /** @return void */
    public function test_indicator_waiting_age_uses_the_accounting_date_and_only_escalates_after_fourteen_days(): void
    {
        ebUpdate('bank_connections', $this->connectionId, ['valid_until' => date('Y-m-d H:i:s', time() + 90 * 86400)]);
        $this->persist($this->page()['transactions']);
        foreach ([0 => 1, 14 => 1, 15 => 2] as $days => $level) {
            $date = date('Y-m-d', strtotime(ebToday() . ' -' . $days . ' days'));
            ebUpdate('bank_transactions', 1, ['date_override' => $date]);
            $indicator = $this->indicator();
            $this->assertSame($level, $indicator['level']);
            $this->assertContains($this->indicatorName() . ': 2 nye bankposteringer (ældste ' . date('d.m.Y', strtotime($date)) . ')', $indicator['reasons']);
            $this->assertSame('2 nye', $indicator['label']);
        }
        ebUpdate('bank_transactions', 2, ['review_state' => 'ignored']);
        $indicator = $this->indicator();
        $this->assertContains($this->indicatorName() . ': 1 ny bankpostering (ældste ' . date('d.m.Y', strtotime($date)) . ')', $indicator['reasons']);
        $this->assertSame('1 ny', $indicator['label']);
        // An account that is not in use yet keeps the status amber; the waiting line still leads the label.
        $unused = $this->account($this->connectionId, 'unused-account');
        ebUpdate('bank_accounts', $unused, ['state' => 'new', 'ledger_account' => null]);
        $indicator = $this->indicator();
        $this->assertSame([2, '1 ny'], [$indicator['level'], $indicator['label']]);
        $this->assertSame('../bank_integration/feed.php?account=' . $this->accountId, $indicator['href']);
        ebUpdate('bank_transactions', 1, ['review_state' => 'ignored']);
        $this->assertSame('1 konto ikke i brug', $this->indicator()['label']);
    }

    /** @return void */
    public function test_indicator_requires_an_overdue_active_account_and_missing_or_old_worker_heartbeat(): void
    {
        ebUpdate('bank_connections', $this->connectionId, ['valid_until' => date('Y-m-d H:i:s', time() + 90 * 86400)]);
        $reason = 'Den automatiske hentning kører ikke. Kontakt Saldi-support.';
        ebUpdate('bank_accounts', $this->accountId, ['next_fetch_at' => date('Y-m-d H:i:s', time() - 1800)]);
        $this->assertSame(1, $this->indicator()['level']);
        ebUpdate('bank_accounts', $this->accountId, ['next_fetch_at' => date('Y-m-d H:i:s', time() - 7200)]);
        $other = $this->account($this->connectionId, 'overdue-other');
        ebUpdate('bank_accounts', $other, ['next_fetch_at' => date('Y-m-d H:i:s', time() - 7200)]);
        foreach ([null, time() - 7200] as $heartbeat) {
            $indicator = $this->indicator($heartbeat);
            $this->assertSame(3, $indicator['level']);
            $this->assertSame(1, count(array_keys($indicator['reasons'], $reason, true)));
            $this->assertSame('fejl', $indicator['label']);
        }
        $this->assertSame(1, $this->indicator(time())['level']);
        ebUpdate('bank_accounts', $other, ['state' => 'disconnected']);
        ebUpdate('bank_accounts', $this->accountId, ['state' => 'paused']);
        $indicator = $this->indicator();
        $this->assertSame(1, $indicator['level']);
        $this->assertNotContains($reason, $indicator['reasons']);
        ebUpdate('bank_accounts', $this->accountId, ['state' => 'new']);
        $this->assertNotContains($reason, $this->indicator()['reasons']);
    }

    /** @return void */
    public function test_indicator_says_renew_after_action_required_and_links_the_most_urgent_account(): void
    {
        ebUpdate('bank_connections', $this->connectionId, ['valid_until' => date('Y-m-d H:i:s', time() + 90 * 86400)]);
        $run = ebSyncAccount($this->accountId, 'schedule', [], $this->client([$this->step(['error' => 'ASPSP_PSU_ACTION_REQUIRED'], 403)]));
        $this->assertSame('failed', $run['status']);
        // The run is stored as failed, but the user has to act at the bank: "forny adgang", not "fejl".
        $indicator = $this->indicator();
        $this->assertSame([3, 'forny adgang'], [$indicator['level'], $indicator['label']]);
        ebWrite('DELETE FROM bank_sync_runs');
        ebUpdate('bank_accounts', $this->accountId, ['last_error_code' => null, 'last_error' => null]);
        // The first account's access runs out soon (amber); a later account's access has expired (red): the link opens the later one.
        ebUpdate('bank_connections', $this->connectionId, ['valid_until' => date('Y-m-d H:i:s', time() + 10 * 86400)]);
        $this->assertSame('../bank_integration/feed.php?account=' . $this->accountId, $this->indicator()['href']);
        $expired = ebInsert('bank_connections', ['provider' => 'enablebanking', 'environment' => 'SANDBOX',
            'aspsp_name' => 'Fixture Bank', 'aspsp_country' => 'DK', 'psu_type' => 'business', 'session_enc' => oauthEncrypt(bin2hex(random_bytes(16))),
            'session_hash' => hash('sha256', random_bytes(16)), 'status' => 'active', 'valid_until' => date('Y-m-d H:i:s', time() - 3600),
            'created_by' => 'chartest', 'created_at' => ebNow(), 'updated_at' => ebNow()]);
        $urgent = $this->account($expired, 'urgent-expired');
        $tied = $this->account($expired, 'urgent-expired-later');
        $indicator = $this->indicator();
        $this->assertSame([3, 'forny adgang'], [$indicator['level'], $indicator['label']]);
        // Two expired accounts: the first one.
        $this->assertSame('../bank_integration/feed.php?account=' . $urgent, $indicator['href']);
        $this->assertNotSame($urgent, $tied);
    }

    /** @return void */
    public function test_child_output_capture_preserves_large_diagnostics_and_failure_exit(): void
    {
        $script = tempnam(sys_get_temp_dir(), 'eb_child_output_');
        try {
            file_put_contents($script, '<?php
                fwrite(STDOUT, "worker started\\n");
                fwrite(STDERR, str_repeat("tenant diagnostic\\n", 32768));
                fwrite(STDOUT, str_repeat("tenant result\\n", 32768));
                fwrite(STDERR, "worker finished\\n");
                exit(7);
            ');
            $result = CharacterizationEnv::runChild($script);
            $this->assertSame(7, $result['exit']);
            $this->assertSame("worker started\n" . str_repeat("tenant result\n", 32768), $result['stdout']);
            $this->assertSame(str_repeat("tenant diagnostic\n", 32768) . "worker finished\n", $result['stderr']);
        } finally {
            unlink($script);
        }
    }

    /** @return void */
    public function test_worker_parent_records_a_heartbeat_after_locking_and_ignores_write_failure(): void
    {
        $root = sys_get_temp_dir() . '/eb_heartbeat_' . bin2hex(random_bytes(8));
        mkdir($root . '/bank_integration', 0700, true);
        mkdir($root . '/temp', 0700);
        symlink(CharacterizationEnv::repoRoot() . '/includes', $root . '/includes');
        symlink(CharacterizationEnv::repoRoot() . '/bank_integration/includes', $root . '/bank_integration/includes');
        copy(CharacterizationEnv::repoRoot() . '/bank_integration/worker.php', $root . '/bank_integration/worker.php');
        $path = $root . '/temp/.ht_bank_worker.heartbeat';
        $lock = fopen($root . '/temp/.ht_bank_worker.lock', 'c');
        try {
            flock($lock, LOCK_EX);
            $busy = CharacterizationEnv::runChild($root . '/bank_integration/worker.php', ['--dry-run', '--account=0']);
            $this->assertSame(0, $busy['exit'], $busy['stderr']);
            $this->assertFileDoesNotExist($path);
            flock($lock, LOCK_UN);
            // A parent dry run exercises the real registry without fetching or writing tenant data.
            $started = time();
            $ran = CharacterizationEnv::runChild($root . '/bank_integration/worker.php', ['--dry-run', '--account=0']);
            $this->assertFileExists($path);
            $timestamp = (int)file_get_contents($path);
            $this->assertGreaterThanOrEqual($started, $timestamp);
            $this->assertLessThanOrEqual(time(), $timestamp);
            unlink($path);
            mkdir($path, 0700); // A directory makes the optional heartbeat write fail.
            $failedWrite = CharacterizationEnv::runChild($root . '/bank_integration/worker.php', ['--dry-run', '--account=0']);
            $this->assertSame($ran['exit'], $failedWrite['exit'], $failedWrite['stderr']);
            $this->assertSame($ran['stdout'], $failedWrite['stdout']);
            $this->assertStringNotContainsString('file_put_contents', $failedWrite['stderr']);
            $this->assertDirectoryExists($path);
        } finally {
            fclose($lock);
            if (is_dir($path)) {
                rmdir($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
            unlink($root . '/temp/.ht_bank_worker.lock');
            unlink($root . '/includes');
            unlink($root . '/bank_integration/includes');
            unlink($root . '/bank_integration/worker.php');
            rmdir($root . '/bank_integration');
            rmdir($root . '/temp');
            rmdir($root);
        }
    }

    /** @return void */
    public function test_import_into_journal_continues_vouchers_and_positions_and_links_every_line(): void
    {
        // An empty journal continues the fiscal year's voucher series (another journal used 40).
        $other = $this->journal();
        ebInsert('kassekladde', ['kladde_id' => $other, 'bilag' => 40, 'transdate' => ebToday(), 'amount' => '1', 'pos' => 1]);
        $this->persist($this->page()['transactions']);
        $journal = $this->journal(['hvem' => 'chartest', 'tidspkt' => microtime()]);
        $this->assertSame(['imported' => 1, 'skipped' => []], ebImportIntoJournal($journal, $this->accountId, [1], 'chartest'));
        $first = ebRow('SELECT * FROM kassekladde WHERE kladde_id=' . $journal);
        $this->assertSame('41', $first['bilag']);
        $this->assertSame('1', $first['pos']);
        // A journal with lines continues its own series and positions.
        ebInsert('kassekladde', ['kladde_id' => $journal, 'bilag' => 57, 'transdate' => ebToday(), 'amount' => '1', 'pos' => 6]);
        $this->assertSame(1, ebImportIntoJournal($journal, $this->accountId, [2], 'chartest')['imported']);
        $second = ebRow('SELECT * FROM kassekladde WHERE kladde_id=' . $journal . ' ORDER BY id DESC LIMIT 1');
        $this->assertSame('58', $second['bilag']);
        $this->assertSame('7', $second['pos']);
        $this->assertSame((string)$this->ledger, $second['kredit']);
        $this->assertSame('0', $second['debet']);
        $this->assertSame('42.320', $second['amount']);
        foreach ([1 => $first, 2 => $second] as $txId => $line) {
            $tx = ebRow('SELECT * FROM bank_transactions WHERE id=' . $txId);
            $link = ebRow('SELECT * FROM bank_import_links WHERE id=' . (int)$tx['link_id']);
            $this->assertSame('imported', $tx['review_state']);
            $this->assertNull($tx['review_reason']);
            $this->assertSame(['transaction_id' => (string)$txId, 'account_id' => (string)$this->accountId, 'kladde_id' => (string)$journal,
                'kassekladde_id' => $line['id'], 'bilag' => $line['bilag'], 'disposition' => 'active', 'created_by' => 'chartest'],
                array_intersect_key($link, array_flip(['transaction_id', 'account_id', 'kladde_id', 'kassekladde_id', 'bilag', 'disposition', 'created_by'])));
            $this->assertSame($tx['description'], $line['beskrivelse']);
            $this->assertSame('0', $line['valuta']);
            $this->assertSame('', $line['momsfri']);
        }
        $this->assertEqualsWithDelta(time(), strtotime(ebAccountStatus($this->accountId)['last_import_at']), 5);
        $this->assertCount(2, ebRows("SELECT id FROM bank_audit_log WHERE action='import_selected'"));
        // The journal's save lock (MB-81) is released again after the import.
        $this->assertSame(0, (int)($GLOBALS['kkJournalLocked'] ?? 0));
    }

    /** @return void */
    public function test_import_into_journal_is_idempotent_and_skip_restore_still_work(): void
    {
        $this->persist($this->page()['transactions']);
        $journal = $this->journal();
        $this->assertSame(2, ebImportIntoJournal($journal, $this->accountId, [1, 2], 'chartest')['imported']);
        $lines = ebRows('SELECT * FROM kassekladde ORDER BY id');
        $links = ebRows('SELECT * FROM bank_import_links ORDER BY id');
        $again = ebImportIntoJournal($journal, $this->accountId, [1, 2], 'chartest');
        $this->assertSame(['imported' => 0, 'skipped' => [['id' => 1, 'reason' => 'not_pending'], ['id' => 2, 'reason' => 'not_pending']]], $again);
        $this->assertSame($lines, ebRows('SELECT * FROM kassekladde ORDER BY id'));
        $this->assertSame($links, ebRows('SELECT * FROM bank_import_links ORDER BY id'));
        // A line that is skipped can be restored to Nye and picked into another journal.
        $raw = $this->page()['transactions'][0];
        $raw['entry_reference'] = 'skip-then-restore';
        $this->persist([$raw]);
        $this->assertSame(1, ebIgnoreSelected($this->accountId, [3], 'chartest'));
        $this->assertNotPickable([3]);
        $this->assertSame('Posteringen ligger nu under Nye, hvor du kan lægge den i kassekladden.', ebTransactionAction(3, 'restore', 'chartest')['message']);
        $next = $this->journal();
        $this->assertSame(1, ebImportIntoJournal($next, $this->accountId, [3], 'chartest')['imported']);
        $this->assertSame('1', ebRow('SELECT COUNT(*) AS n FROM kassekladde WHERE kladde_id=' . $next)['n']);
    }

    /**
     * Starts a support script without waiting for it; output goes to temporary files.
     * @param list<string> $args
     * @return array{proc:resource, out:resource, err:resource}
     */
    private function startChild(string $script, array $args): array
    {
        $out = tmpfile();
        $err = tmpfile();
        $proc = proc_open(array_merge([PHP_BINARY, $script], array_map('strval', $args)), [0 => ['pipe', 'r'], 1 => $out, 2 => $err], $pipes);
        $this->assertIsResource($proc);
        fclose($pipes[0]);
        return ['proc' => $proc, 'out' => $out, 'err' => $err];
    }

    /** @return array{exit:int, stdout:string, stderr:string} */
    private function finishChild(array $child): array
    {
        // Keep the first finished status: before PHP 8.3 only that call reports the real exit code.
        $deadline = time() + 120;
        while (($status = proc_get_status($child['proc']))['running'] && time() < $deadline) {
            usleep(100000);
        }
        if ($status['running']) {
            // A child stuck on a lock must not hang the suite: proc_close() would wait for it.
            proc_terminate($child['proc']);
            for ($i = 0; $i < 20 && proc_get_status($child['proc'])['running']; $i++) {
                usleep(100000);
            }
            if (proc_get_status($child['proc'])['running']) {
                proc_terminate($child['proc'], 9);
            }
        }
        $exit = proc_close($child['proc']);
        rewind($child['out']);
        rewind($child['err']);
        $result = ['exit' => $status['running'] ? -1 : ($status['exitcode'] >= 0 ? $status['exitcode'] : $exit),
            'stdout' => (string)stream_get_contents($child['out']), 'stderr' => (string)stream_get_contents($child['err'])];
        fclose($child['out']);
        fclose($child['err']);
        return $result;
    }

    /** @return void Waits until another session of this database is blocked on a lock (the child), at most 60 s. */
    private function waitForLockWaiter($holder, array $child): void
    {
        $deadline = time() + 60;
        do {
            // pg_stat_activity is a snapshot per transaction, and the holder is inside one.
            pg_query($holder, 'SELECT pg_stat_clear_snapshot()');
            $waiting = (int)pg_fetch_result(pg_query($holder, "SELECT COUNT(*) FROM pg_stat_activity WHERE datname=current_database()"
                . " AND pid<>pg_backend_pid() AND wait_event_type='Lock'"), 0, 0);
            if ($waiting > 0) {
                return;
            }
            $this->assertTrue(proc_get_status($child['proc'])['running'], 'the child ended without waiting for the journal header');
            usleep(100000);
        } while (time() < $deadline);
        $this->fail('The child never waited for the journal header lock');
    }

    /** @return void An import in progress holds the header; posting waits and then posts the bank lines too. */
    public function test_posting_waits_for_an_import_holding_the_journal_header_and_posts_its_lines(): void
    {
        $this->persist([$this->page()['transactions'][0]]);
        // Without a journal note posting took no header lock before it read the lines (the race this covers).
        $journal = $this->journal(['kladdenote' => '']);
        $this->importPending(null, $journal);
        ebWrite('UPDATE kassekladde SET kredit=' . $this->otherLedger . ' WHERE kladde_id=' . $journal . ' AND kredit=0');
        ebWrite('UPDATE kassekladde SET debet=' . $this->otherLedger . ' WHERE kladde_id=' . $journal . ' AND debet=0');
        $import = CharacterizationEnv::connect(ebTestDb());
        pg_query($import, 'BEGIN');
        pg_query($import, 'SELECT id FROM kladdeliste WHERE id=' . $journal . ' FOR UPDATE');
        pg_query($import, "INSERT INTO kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, faktura, amount, kladde_id, momsfri, afd, projekt, ansat, valuta, pos)
            VALUES (99, '" . ebToday() . "', 'Bankimport i gang', 'F', " . $this->ledger . ", 'F', " . $this->otherLedger . ", '', 10, " . $journal . ", '', 0, '', '0', 0, 99)");
        $posting = $this->startChild(__DIR__ . '/../support/run_bogfor_page.php', ['bogfor', $journal, CharacterizationEnv::sessionId(), '']);
        // Commit only once posting is blocked on a row lock: with the header lock that is before it reads the lines.
        try {
            $this->waitForLockWaiter($import, $posting);
        } finally {
            pg_query($import, 'COMMIT');
            pg_close($import);
            $result = $this->finishChild($posting);
        }
        $this->assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
        $this->assertSame('V', ebRow('SELECT bogfort FROM kladdeliste WHERE id=' . $journal)['bogfort']);
        // Every line of the posted journal is in transaktioner, the one committed while posting waited included.
        $this->assertNotNull(ebRow("SELECT id FROM transaktioner WHERE kladde_id=" . $journal . " AND beskrivelse='Bankimport i gang'"));
        $this->assertSame('2', ebRow('SELECT COUNT(DISTINCT bilag) AS n FROM transaktioner WHERE kladde_id=' . $journal)['n']);
        $this->assertStringContainsString('for update', strtolower(file_get_contents(__DIR__ . '/../../../finans/bogfor.php')));
    }

    /** @return void Posting holds the header; the import waits and then refuses the posted journal, writing nothing. */
    public function test_import_waiting_for_a_posting_refuses_the_posted_journal(): void
    {
        $this->persist($this->page()['transactions']);
        $journal = $this->journal(['kladdenote' => '']);
        $posting = CharacterizationEnv::connect(ebTestDb());
        pg_query($posting, 'BEGIN');
        pg_query($posting, 'SELECT id FROM kladdeliste WHERE id=' . $journal . ' FOR UPDATE');
        $import = $this->startChild(__DIR__ . '/support/run_import.php', [$journal, $this->accountId, $GLOBALS['regnaar'], 1, 2]);
        try {
            $this->waitForLockWaiter($posting, $import);
            pg_query($posting, "UPDATE kladdeliste SET bogfort='V' WHERE id=" . $journal);
        } finally {
            pg_query($posting, 'COMMIT');
            pg_close($posting);
            $result = $this->finishChild($import);
        }
        $this->assertSame(0, $result['exit'], $result['stderr'] . $result['stdout']);
        $this->assertArrayHasKey('refused', json_decode(trim($result['stdout']), true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame('0', ebRow('SELECT COUNT(*) AS n FROM kassekladde WHERE kladde_id=' . $journal)['n']);
        $this->assertSame('0', ebRow('SELECT COUNT(*) AS n FROM bank_import_links')['n']);
        $this->assertSame('2', ebRow("SELECT COUNT(*) AS n FROM bank_transactions WHERE review_state='pending'")['n']);
    }

    /** @return void A reset restarts the kassekladde ids; the old links let go of them, so the next import works. */
    public function test_import_after_account_reset_does_not_collide_with_old_links(): void
    {
        require_once __DIR__ . '/../../../systemdata/resetAccount.php';
        $this->persist($this->page()['transactions']);
        ebRows("SELECT setval(pg_get_serial_sequence('kassekladde','id'), 1, false)");
        $first = $this->journal();
        $this->assertSame(1, ebImportIntoJournal($first, $this->accountId, [1], 'chartest')['imported']);
        $oldLink = ebRow('SELECT * FROM bank_import_links');
        $this->assertSame('1', $oldLink['kassekladde_id']);
        foreach (accountResetStatements(['kassekladde', 'kladdeliste', 'bank_import_links'], true, true, 'postgresql') as $statement) {
            ebWrite($statement);
        }
        $link = ebRow('SELECT * FROM bank_import_links WHERE id=' . (int)$oldLink['id']);
        $this->assertSame(['deleted', (string)-(int)$oldLink['id'], $oldLink['transaction_id'], $oldLink['kladde_id'], $oldLink['bilag']],
            [$link['disposition'], $link['kassekladde_id'], $link['transaction_id'], $link['kladde_id'], $link['bilag']]);
        $second = $this->journal();
        $result = ebImportIntoJournal($second, $this->accountId, [2], 'chartest');
        $this->assertSame(1, $result['imported']);
        $this->assertSame('1', ebRow('SELECT kassekladde_id FROM bank_import_links WHERE transaction_id=2')['kassekladde_id']);
        // The line from before the reset can be moved back to Nye and picked again.
        $GLOBALS['ebActionAccountId'] = $this->accountId;
        ebTransactionAction(1, 'reimport', 'chartest');
        $this->assertSame(1, ebImportIntoJournal($second, $this->accountId, [1], 'chartest')['imported']);
    }

    /** @return void On an ISO-8859-15 company the bank text is stored as that charset and read back as UTF-8. */
    public function test_latin9_company_gets_bank_text_in_its_own_charset(): void
    {
        $name = 'eb_latin9_' . bin2hex(random_bytes(6));
        $admin = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        pg_query($admin, "CREATE DATABASE $name ENCODING 'LATIN9' LC_COLLATE 'C' LC_CTYPE 'C' TEMPLATE template0");
        $saved = [$GLOBALS['connection'], $GLOBALS['db_encode']];
        try {
            $latin = CharacterizationEnv::connect($name);
            pg_set_client_encoding($latin, 'LATIN9'); // what db_connect() does for $db_encode LATIN9
            $GLOBALS['connection'] = $latin;
            $GLOBALS['db_encode'] = 'LATIN9';
            ebWrite('CREATE TABLE eb_text (id SERIAL PRIMARY KEY, txt VARCHAR(100))');
            $id = ebInsert('eb_text', ['txt' => 'Køb hos Søren']);
            // Legacy code (kassekladde) reads the tenant charset: one byte per letter.
            $this->assertSame(mb_convert_encoding('Køb hos Søren', 'ISO-8859-15', 'UTF-8'), pg_fetch_result(pg_query($latin, 'SELECT txt FROM eb_text WHERE id=' . $id), 0, 0));
            // The bank code reads UTF-8 again, legacy text included.
            pg_query($latin, "INSERT INTO eb_text (txt) VALUES ('" . mb_convert_encoding('Lønkonto', 'ISO-8859-15', 'UTF-8') . "')");
            $this->assertSame(['Køb hos Søren', 'Lønkonto'], array_column(ebRows('SELECT txt FROM eb_text ORDER BY id'), 'txt'));
            $this->assertSame('1', ebRow('SELECT COUNT(*) AS n FROM eb_text WHERE txt=' . ebSql('Køb hos Søren'))['n']);
            // A session user name that is already ISO-8859-15 is neither converted twice nor broken.
            $latinUser = mb_convert_encoding('Søren', 'ISO-8859-15', 'UTF-8');
            $this->assertSame($latinUser, ebToDb($latinUser));
            $this->assertSame('Søren', ebUtf8($latinUser));
            $this->assertSame('Søren', ebUtf8('Søren'));
            // The stored bytes read through a UTF-8 client are the real letters, not "KÃ¸b".
            $utf = CharacterizationEnv::connect($name);
            pg_set_client_encoding($utf, 'UTF8');
            $this->assertSame('Køb hos Søren', pg_fetch_result(pg_query($utf, 'SELECT txt FROM eb_text WHERE id=' . $id), 0, 0));
            pg_close($utf);
            pg_close($latin);
        } finally {
            [$GLOBALS['connection'], $GLOBALS['db_encode']] = $saved;
            pg_query($admin, "DROP DATABASE IF EXISTS $name");
            pg_close($admin);
        }
    }

    /**
     * Nordea sends a debit with creditor name and payment text, sometimes repeating each other.
     * @param list<string> $remittance
     * @return array<string,mixed>
     */
    private function nordeaLine(string $ref, string $amount, string $name, array $remittance, ?string $balance, string $reference = ''): array
    {
        $line = ['entry_reference' => $ref, 'booking_date' => ebToday(), 'value_date' => ebToday(), 'status' => 'BOOK',
            'credit_debit_indicator' => str_starts_with($amount, '-') ? 'DBIT' : 'CRDT',
            'transaction_amount' => ['currency' => 'DKK', 'amount' => ltrim($amount, '-')],
            (str_starts_with($amount, '-') ? 'creditor' : 'debtor') => ['name' => $name], 'remittance_information' => $remittance,
            'bank_transaction_code' => ['description' => 'Kodetekst']];
        if ($balance !== null) {
            $line['balance_after_transaction'] = ['currency' => 'DKK', 'amount' => $balance];
        }
        if ($reference !== '') {
            $line['reference_number'] = $reference;
        }
        return $line;
    }

    /** @return void */
    public function test_payee_name_shows_once_and_the_reference_is_not_repeated_in_the_text(): void
    {
        $text = fn(string $name, array $remittance, string $reference = ''): string
            => ebNormalizeTransaction($this->nordeaLine('x', '-1.00', $name, $remittance, null, $reference))['description'];
        $this->assertSame('Thrusty 105023 Bgs', $text('Thrusty 105023', ['Bgs Thrusty 105023']));
        $this->assertSame('Business debit køb, . SIMPLY.COM Den 30.10', $text('Business debit køb, . SIMPLY.COM   Den 30.10', ['Business debit køb, . SIMPLY.COM   Den 30.10']));
        $this->assertSame('Til Frokostkonto Dan Fra DANOSOFT APS Bgs', $text('Til Frokostkonto Dan', ['Fra DANOSOFT APS', 'Bgs Til Frokostkonto Dan']));
        $this->assertSame('Mobilepay Netto Reference 206', $text('Mobilepay Netto Reference 206', ['SCTI2TX1893514235', '', 'Mobilepay Netto Reference 206'], 'SCTI2TX1893514235'));
        $this->assertSame('Straksoverførsel Per Danosoft Aconto', $text('Straksoverførsel Per', ['SCTI2TX1894122619', 'Danosoft Aconto', 'Straksoverførsel Per'], 'SCTI2TX1894122619'));
        $this->assertSame('Køge Kommune Ref.: 2630 Meddelnr. 0200', $text('Køge Kommune', ['Køge KommuneRef.: 2630', 'Køge Kommune Meddelnr. 0200']));
        // A shorter text inside the name adds nothing; an unrelated text is kept; only a missing text falls back to the code text.
        $this->assertSame('Business debit køb EUR 197,53 ANTHROPIC', $text('Business debit køb EUR 197,53 ANTHROPIC', ['ANTHROPIC']));
        $this->assertSame('Leverandør Køb', $text('Leverandør', ['Køb']));
        $this->assertSame('Leverandør Kodetekst', $text('Leverandør', []));
        $this->assertSame('Leverandør', $text('Leverandør', ['leverandør']));
        $line = $this->nordeaLine('x', '-1.00', 'A', [], '-12.50');
        $this->assertSame('-12.50', ebNormalizeTransaction($line)['balance_after']);
        foreach ([['amount' => '1,5'], ['amount' => '1', 'currency' => 'EUR'], ['currency' => 'DKK']] as $balance) {
            $line['balance_after_transaction'] = $balance;
            $this->assertNull(ebNormalizeTransaction($line)['balance_after']);
        }
    }

    /** @return void */
    public function test_each_day_is_listed_and_picked_in_the_banks_order_from_its_balances(): void
    {
        // Nordea's netbank, newest first: UnivAdv, Frokost, Thrusty, Helsinge, Overførsel, Køge. The API sends the instant
        // transfer (Overførsel) after the others, so the order it arrives in is not the bank's order.
        $this->persist([
            $this->nordeaLine('univ', '-20000.00', 'UnivAdv', [], '303717.50'),
            $this->nordeaLine('frokost', '-1000.00', 'Frokost', [], '323717.50'),
            $this->nordeaLine('thrusty', '-4077.50', 'Thrusty', [], '324717.50'),
            $this->nordeaLine('helsinge', '-660.00', 'Helsinge', [], '328795.00'),
            $this->nordeaLine('koege', '15968.68', 'Køge', [], '327970.00'),
            $this->nordeaLine('nobalance', '-1.00', 'Uden saldo', [], null),
            $this->nordeaLine('overf', '1485.00', 'Overførsel', [], '329455.00'),
        ]);
        $names = fn(): array => array_column(ebListTransactions($this->accountId, 'pending'), 'counterparty');
        $this->assertSame(['UnivAdv', 'Frokost', 'Thrusty', 'Helsinge', 'Overførsel', 'Køge', 'Uden saldo'], $names());
        // A later fetch adds the day's newest line; it goes on top, and the day's lines keep their places.
        $this->persist([$this->nordeaLine('anima', '-150.00', 'ANIMA', [], '303567.50'),
            $this->nordeaLine('univ', '-20000.00', 'UnivAdv', [], '303717.50')]);
        $this->assertSame(['ANIMA', 'UnivAdv', 'Frokost', 'Thrusty', 'Helsinge', 'Overførsel', 'Køge', 'Uden saldo'], $names());
        // Picked into a journal, the day reads oldest first, as on a bank statement.
        $this->importPending();
        $this->assertSame(['Uden saldo Kodetekst', 'Køge Kodetekst', 'Overførsel Kodetekst', 'Helsinge Kodetekst', 'Thrusty Kodetekst', 'Frokost Kodetekst', 'UnivAdv Kodetekst', 'ANIMA Kodetekst'],
            array_column(ebRows('SELECT beskrivelse FROM kassekladde ORDER BY id'), 'beskrivelse'));
    }

    /** @return void */
    public function test_chain_order_survives_a_gap_a_tie_and_a_zero_amount(): void
    {
        $row = static fn(int $id, string $amount, ?string $balance): array => ['id' => $id, 'amount' => $amount, 'balance_after' => $balance];
        // Newest first the bank sends 1, 2, (3 missing), 4, 5: two chains, taken in the order the bank sent their newest line.
        $this->assertSame([1 => 1, 2 => 2, 4 => 3, 5 => 4],
            ebChainOrder([$row(2, '-10', '110'), $row(1, '-10', '100'), $row(5, '-10', '140'), $row(4, '-10', '130')]));
        // +100 then -100 returns to the same balance; every line still gets one place.
        $tie = ebChainOrder([$row(1, '100', '200'), $row(2, '-100', '100'), $row(3, '5', '105'), $row(4, '0', '105')]);
        $this->assertCount(4, $tie);
        $this->assertEqualsCanonicalizing([1, 2, 3, 4], array_values($tie));
        $this->assertSame([], ebChainOrder([$row(1, '5', null)]));
    }

    /** @return void */
    public function test_columns_added_later_are_added_to_an_existing_table(): void
    {
        ebWrite('ALTER TABLE bank_transactions DROP COLUMN day_position');
        $this->assertFalse(ebSchemaReady());
        ebEnsureSchema();
        $this->assertTrue(ebSchemaReady());
        $this->assertTrue(ebColumnExists('bank_transactions', 'day_position'));
    }
}
