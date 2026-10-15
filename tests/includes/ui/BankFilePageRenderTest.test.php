<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/ui/BankFilePageRenderTest.test.php --- ai/mcp-assistant --- 2026.10.05
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
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
// Rendering, access boundaries and posted page actions against disposable tenant fixtures.
//
// 20261004 CDX/LH Created.
// 20261005 CDX/LH Add regression coverage for companion review corrections.
// 20261007 CL/LH Empty selection and wrong bank account give their own message and field.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiBankTestCase.php';
if (!defined('SALDI_BANKFIL_LIBRARY')) {
    define('SALDI_BANKFIL_LIBRARY', true);
}
require_once __DIR__ . '/../../../finans/assist_bankfil.php';

final class BankFilePageRenderTest extends SaldiBankTestCase
{
    private const CSRF = 'bank-page-test-csrf';

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = ['inbox_csrf' => self::CSRF];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    private function submit(array $post, ?array $state = null, ?SaldiTenantContext $ctx = null): array
    {
        return saldi_bankfil_post(
            $post + ['csrf' => self::CSRF, 'thisDb' => 'plan_x'],
            [],
            'plan_x',
            ['ctx' => $ctx ?? $this->env->ctx(), 'db' => $this->env->db],
            $state,
            1
        );
    }

    private function import(): array
    {
        return $this->submit(['op' => 'import', 'bank_account' => '5500',
            'content' => "Dato;Tekst;Beløb\n04-06-2025;Faktura 501;1.234,56\n05-06-2025;Faktura 502;50,00"]);
    }

    public function testPreviewEscapesTextAndSelectsOnlyHighConfidence(): void
    {
        $match = ['party' => ['account' => '2005', 'name' => '<Rikke>', 'ledger' => 'customer'],
            'account' => null, 'invoice' => '358', 'confidence' => 'high'];
        $rows = [
            ['line_id' => 1, 'date' => '2025-06-04', 'description' => '<script>x</script>',
                'amount' => '1710.00', 'currency' => 'DKK', 'status' => 'new', 'match' => $match],
            ['line_id' => 2, 'date' => '2025-06-04', 'description' => 'Middel',
                'amount' => '-50.00', 'currency' => 'DKK', 'status' => 'new',
                'match' => array_replace($match, ['confidence' => 'medium'])],
            ['line_id' => 3, 'date' => '2025-06-04', 'description' => 'Dublet',
                'amount' => '-80.00', 'currency' => 'DKK', 'status' => 'duplicate',
                'journal_duplicate' => true, 'match' => null],
        ];
        $html = saldi_bankfil_preview_html($rows, true, null, 1);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('2005 &lt;Rikke&gt;', $html);
        self::assertStringContainsString('faktura 358', $html);
        self::assertStringContainsString('1.710,00 DKK', $html);
        self::assertStringContainsString('Allerede i en kassekladde', $html);
        self::assertSame(1, substr_count($html, ' checked'));
        self::assertSame(2, substr_count($html, 'type="checkbox"'));
        self::assertStringContainsString('scope="col"', $html);
        self::assertStringNotContainsString('type="checkbox"', saldi_bankfil_preview_html($rows, false, null, 1));
    }

    public function testLargePreviewPreselectsAtMostTwoHundredLines(): void
    {
        $row = ['line_id' => 1, 'date' => '2025-06-04', 'description' => 'Bank', 'amount' => '10.00',
            'currency' => 'DKK', 'status' => 'new', 'match' => ['party' => null,
                'account' => ['account' => '2800', 'name' => 'Software'], 'invoice' => null, 'confidence' => 'high']];
        $html = saldi_bankfil_preview_html(array_fill(0, 201, $row), true, null, 1);
        self::assertSame(200, substr_count($html, ' checked'));
    }

    public function testImportStagesAndRemembersOnlyTheCurrentUsersAccount(): void
    {
        $result = $this->import();
        self::assertSame('neutral', $result['flash']['tone']);
        self::assertSame(2, (int)$this->env->db->value('SELECT COUNT(*) FROM bank_import_lines'));
        $mine = SaldiSettings::getUser($this->env->db, $this->env->ctx(), 'assistant', 'bank_account', '');
        self::assertSame('5500', $mine);
        $other = SaldiSettings::getUser($this->env->db, $this->env->ctx(910002), 'assistant', 'bank_account', '');
        self::assertSame('', $other);
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM inbox_items'));
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM kassekladde'));
        self::assertSame(910001, $result['state']['user']);
    }

    public function testCsrfCompanyAndPermissionsRejectBeforeStaging(): void
    {
        foreach ([['csrf' => 'bad'], ['thisDb' => 'other']] as $bad) {
            self::assertSame('warn', $this->submit($bad + ['op' => 'import', 'bank_account' => '5500',
                'content' => "Dato;Tekst;Beløb\n04-06-2025;Test;10,00"])['flash']['tone']);
        }
        $reader = $this->env->ctx(910001, '1021111111111111');
        self::assertSame('warn', $this->submit(['op' => 'import'], null, $reader)['flash']['tone']);
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM bank_import_lines'));
    }

    public function testProposeCreatesHumanInboxItemWithoutJournalWritesAndRetriesOnce(): void
    {
        $this->open();
        $this->open('502', '100.00');
        $state = $this->import()['state'];
        $args = ['batch_id' => $state['batch']];
        $lines = SaldiBankTools::listLines($this->env->ctx(), $this->env->db, $args)['data']['rows'];
        self::assertSame(['high', 'medium'], array_column(array_column($lines, 'match'), 'confidence'));
        $post = ['op' => 'propose', 'batch' => $state['batch'], 'request' => $state['request'],
            'line_ids' => [(string)$lines[0]['line_id']]];
        $result = $this->submit($post, $state);
        self::assertSame('neutral', $result['flash']['tone']);
        self::assertGreaterThan(0, $result['state']['item']);
        $item = $this->inbox()->get($this->env->ctx(), $result['state']['item']);
        self::assertSame('user:910001', $item['created_by']);
        self::assertCount(1, $item['payload']['lines']);
        self::assertSame(['proposed', 'new'], $this->statuses($state['batch']));
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM kassekladde'));
        self::assertSame($result['state']['item'], $this->submit($post, $result['state'])['state']['item']);
        self::assertSame(1, (int)$this->env->db->value('SELECT COUNT(*) FROM inbox_items'));
        $next = $this->submit(array_replace($post, ['request' => $result['state']['request'],
            'line_ids' => [(string)$lines[1]['line_id']]]), $result['state']);
        self::assertSame('neutral', $next['flash']['tone']);
        self::assertNotSame($result['state']['item'], $next['state']['item']);
        self::assertSame(['proposed', 'proposed'], $this->statuses($state['batch']));
        self::assertSame(2, (int)$this->env->db->value('SELECT COUNT(*) FROM inbox_items'));
    }

    public function testExplicitMediumLineCanBeProposed(): void
    {
        $this->open('501', '1000.00');
        $state = $this->import()['state'];
        $args = ['batch_id' => $state['batch']];
        $line = SaldiBankTools::listLines($this->env->ctx(), $this->env->db, $args)['data']['rows'][0];
        self::assertSame('medium', $line['match']['confidence']);
        $result = $this->submit(['op' => 'propose', 'batch' => $state['batch'], 'request' => $state['request'],
            'line_ids' => [(string)$line['line_id']]], $state);
        self::assertSame('neutral', $result['flash']['tone']);
        self::assertGreaterThan(0, $result['state']['item']);
    }

    public function testForeignBatchAndUserCannotBeProposed(): void
    {
        $state = $this->import()['state'];
        $post = ['op' => 'propose', 'batch' => $state['batch'], 'request' => $state['request'], 'line_ids' => ['1']];
        $foreign = array_replace($post, ['batch' => str_repeat('f', 32)]);
        self::assertSame('warn', $this->submit($foreign, $state)['flash']['tone']);
        self::assertSame('warn', $this->submit($post, $state, $this->env->ctx(910002))['flash']['tone']);
        self::assertNull(saldi_bankfil_state($state, 'other', 910001));
        self::assertNull(saldi_bankfil_state($state, 'plan_x', 910002));
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM inbox_items'));
    }

    public function testInvalidPasteAndUploadAreRejected(): void
    {
        foreach (['', str_repeat('x', 200001), ['not a string']] as $content) {
            $post = ['op' => 'import', 'bank_account' => '5500', 'content' => $content];
            self::assertSame('warn', $this->submit($post)['flash']['tone']);
        }
        $tooLarge = ['statement' => ['error' => UPLOAD_ERR_INI_SIZE]];
        $this->error('VALIDATION_ERROR', static fn() => saldi_bankfil_input([], $tooLarge));
        $notUploaded = ['statement' => ['error' => UPLOAD_ERR_OK, 'tmp_name' => '/etc/passwd']];
        $this->error('VALIDATION_ERROR', static fn() => saldi_bankfil_input([], $notUploaded));
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM bank_import_lines'));
    }
    public function testBankConfidenceLabelsUseRegisteredTranslationsWithoutJournalCollisions(): void
    {
        $row = ['line_id' => 1, 'date' => '2025-06-04', 'description' => 'Bank', 'amount' => '10.00',
            'currency' => 'DKK', 'status' => 'new', 'match' => ['party' => null,
                'account' => ['account' => '2800', 'name' => 'Software'], 'invoice' => null, 'confidence' => 'high']];
        $medium = $row;
        $medium['match']['confidence'] = 'medium';
        foreach ([1 => ['Sikkerhed', 'Høj', 'Middel', 'Foreslået modpost'],
            2 => ['Confidence', 'High', 'Medium', 'Suggested counter account'],
            3 => ['Sikkerhet', 'Høy', 'Middels', 'Foreslått motkonto']] as $lang => $labels) {
            $html = saldi_bankfil_preview_html([$row, $medium], true, null, $lang);
            foreach ($labels as $label) {
                self::assertStringContainsString($label, $html);
            }
            foreach (range(6291, 6295) as $id) {
                self::assertStringNotContainsString(SaldiText::get(null, $id, $lang, ''), $html);
            }
        }
        self::assertSame('Leveringstid ved genbestilling (dage)', SaldiText::get(null, 6300, 1, ''));
        $source = file_get_contents(__DIR__ . '/../../../systemdata/assistent.php');
        self::assertStringContainsString("6300, 'Leveringstid ved genbestilling (dage)'", $source);
    }

    public function testAmbiguousImportDisplaysTheSpecificDanishHeaderWarning(): void
    {
        $result = $this->submit(['op' => 'import', 'bank_account' => '5500',
            'content' => "04-06-2025;Betaling;501;10,00\n05-06-2025;Betaling;502;20,00"]);
        self::assertSame('warn', $result['flash']['tone']);
        self::assertStringContainsString('overskriftsrække', $result['flash']['text']);
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM bank_import_lines'));
    }

    public function testEmptySelectionAndWrongAccountSayWhatToFix(): void
    {
        $state = $this->import()['state'];
        // Without script the button is enabled, so the server answers an empty selection.
        $empty = $this->submit(['op' => 'propose', 'batch' => $state['batch'], 'request' => $state['request']], $state);
        self::assertSame(['tone' => 'warn', 'text' => 'Vælg mindst én linje.', 'field' => ''], $empty['flash']);
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM inbox_items'));
        $account = $this->submit(['op' => 'import', 'bank_account' => '', 'content' => "Dato;Tekst;Beløb\n04-06-2025;Test;10,00"]);
        self::assertSame('bank_account', $account['flash']['field']);
        self::assertSame('Vælg en åben bankkonto i det aktive regnskabsår.', $account['flash']['text']);
        $file = $this->submit(['op' => 'import', 'bank_account' => '5500', 'content' => '']);
        self::assertSame('statement', $file['flash']['field']);
    }
}
