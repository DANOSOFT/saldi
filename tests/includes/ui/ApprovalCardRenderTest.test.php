<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/ui/ApprovalCardRenderTest.test.php --- ver 5.0.0 --- 2026.10.08
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
// ApprovalCard HTML view: escaping of untrusted proposer text, the change table,
// state rendering (never green unless applied), the decision controls and the
// shared Danish receipt/error wording. No database: texts come from tekster.csv.
//
// 20261002 CL/LH Created.
// 20261002 CDX/LH Cover F1 integration behavior and regression cases.
// 20261005 CDX/LH Cover Danish quantity formatting in rendered approval cards.
// 20261007 CL/LH Raw 4+ decimal quantities render as Danish figures at render time; receipt button names
//                the document (tilbud, kreditnota).
// 20261008 CL/LH Load the real lookup helpers when running this renderer test on its own.

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/std_func.php';
require_once __DIR__ . '/../../../includes/automation/approvalCard.php';
require_once __DIR__ . '/../../../includes/tools/SaldiToolRuntime.php';

final class ApprovalCardRenderTest extends PHPUnit\Framework\TestCase
{
    private const XSS = '<script>alert("x")</script>\'"&';

    /** @return array<string, mixed> */
    private function item(array $card = [], array $item = []): array
    {
        $card += [
            'item_id' => 12, 'revision' => 1, 'digest' => str_repeat('a', 64), 'tenant_hash' => str_repeat('b', 32),
            'kind' => 'journal.draft_entry', 'kind_label' => 'Kladdelinjer', 'provenance' => 'agent',
            'provenance_label' => 'Saldi Assist', 'title' => '2 kladdelinjer: Adobe', 'summary' => '',
            'why' => 'Fakturaen er på 1.250 kr.', 'columns' => ['Dato', 'Bilag', 'Tekst', 'Debet', 'Kredit', 'Beløb'],
            'rows' => [['2026-10-01', '', 'Adobe abonnement', '2750 Software', '5820 Bank', '1250.00']],
            'fields' => [['label' => 'Kladde', 'value' => 'Ny kladde']],
            'evidence' => [['label' => 'Kassekladde 12', 'screen' => 'finans/kassekladde.php?kladde_id=12']],
            'notices' => [], 'next_step' => 'Når du godkender, lægges linjerne i en ny kassekladde.',
            'action_class' => 'reversible_draft', 'status' => 'open', 'can_undo' => false,
            'approve_label' => 'Godkend', 'reject_label' => 'Afvis', 'expires_at' => '2026-10-15T10:00:00Z',
            'screen' => 'inbox/index.php?item=12',
        ];
        return $item + [
            'id' => 12, 'kind' => $card['kind'], 'status' => $card['status'], 'created_by' => 'agent:saldi-assist',
            'created_at' => '2026-10-01T10:00:00Z', 'result' => null, 'card' => $card,
        ];
    }

    private function page(array $item, array $opts = []): string
    {
        return saldi_approval_card_html($item, $opts + [
            'mode' => 'page', 'csrf' => 'csrf"token', 'this_db' => 'plan_"mcp', 'form_action' => 'index.php?tab=open&x="y',
            'return_query' => 'tab=done', 'user_id' => 7,
        ]);
    }

    public function testUntrustedTitleWhyRowsFieldsAndEvidenceAreEscaped(): void
    {
        $html = $this->page($this->item([
            'title' => self::XSS, 'why' => self::XSS, 'rows' => [[self::XSS, '', '', '', '', '1.00']],
            'fields' => [['label' => self::XSS, 'value' => self::XSS]],
            'evidence' => [['label' => self::XSS, 'screen' => 'finans/kladdeliste.php']],
            'notices' => [['kind' => 'tainted', 'text' => self::XSS]], 'kind_label' => self::XSS, 'provenance_label' => self::XSS,
        ]));
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('"x")', $html);
        self::assertSame(9, substr_count($html, '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;&#039;&quot;&amp;'));
        self::assertStringContainsString('value="csrf&quot;token"', $html);
        self::assertStringContainsString('value="plan_&quot;mcp"', $html);
        self::assertStringContainsString('action="index.php?tab=open&amp;x=&quot;y"', $html);
    }

    public function testTheWhyIsAttributedToTheAssistantOnlyForAgentProposals(): void
    {
        $agent = $this->page($this->item());
        self::assertStringContainsString('Saldi Assists forklaring', $agent);
        self::assertStringContainsString('Teksten er skrevet af Saldi Assist.', $agent);
        $rule = $this->page($this->item(['provenance' => 'rule', 'provenance_label' => 'Rutine']));
        self::assertStringNotContainsString('Saldi Assists forklaring', $rule);
        self::assertStringContainsString('>Forklaring<', $rule);
        self::assertStringNotContainsString('sac-why', $this->page($this->item(['why' => '  '])));
    }

    public function testChangeTableKeepsColumnsAndShowsDanishNotationOfTheExactValues(): void
    {
        $html = $this->page($this->item(['rows' => [
            ['2026-10-01', '17', 'Adobe', '2750 Software', '', '1250.00'],
            ['2026-10-02', '17', 'Moms', '6320 Moms', '', '1234567.05'],
        ]]));
        preg_match_all('/<th scope="col"[^>]*>([^<]*)<\/th>/', $html, $headers);
        self::assertSame(['Dato', 'Bilag', 'Tekst', 'Debet', 'Kredit', 'Beløb'], $headers[1]);
        self::assertStringContainsString('<caption>Ændringen</caption>', $html);
        self::assertStringContainsString('<td class="sac-nowrap">01-10-2026</td>', $html);
        self::assertStringContainsString('<td class="sac-num">1.250,00</td>', $html);
        self::assertStringContainsString('<td class="sac-num">1.234.567,05</td>', $html);
        self::assertSame(['text' => '-80,50 DKK', 'numeric' => true, 'date' => false], saldi_ui_cell('-80.50 DKK'));
        self::assertSame('0,05', saldi_ui_cell('0.05')['text']);
        self::assertSame('17', saldi_ui_cell('17')['text']);
        self::assertSame('', saldi_ui_cell(['nested'])['text']);
        $readOnly = $this->page($this->item(['action_class' => 'read_only']));
        self::assertStringContainsString('<caption>Indhold</caption>', $readOnly);
    }

    public function testQuantitiesUseDanishNotationWithoutTrailingZerosInRenderedCards(): void
    {
        foreach (['2.000' => '2', '2.500' => '2,5', '1234.250' => '1.234,25',
            '1000.000' => '1.000', '-2.500' => '-2,5', '-0.000' => '0',
            '999999999999.999' => '999.999.999.999,999', '' => ''] as $stored => $expected) {
            $quantity = SaldiToolRuntime::quantityDa($stored);
            self::assertSame($expected, $quantity);
            $html = $this->page($this->item(['columns' => ['Antal'], 'rows' => [[$quantity]]]));
            self::assertStringContainsString('>' . $expected . '</td>', $html);
        }
    }

    public function testRawDatabaseQuantitiesNeverShowTrailingZerosEvenOnOldCards(): void
    {
        // A card whose kind still passes the raw ordrelinjer value ('0.00000') renders a Danish figure.
        foreach (['0.00000' => '0', '-0.00000' => '0', '2.50000' => '2,5', '12.5000' => '12,5', '1234.56780' => '1.234,5678', '7.46030' => '7,4603'] as $stored => $expected) {
            self::assertSame(['text' => $expected, 'numeric' => true, 'date' => false], saldi_ui_cell($stored), $stored);
        }
        // Three decimals stay as they are: '1.250' is also the Danish figure for 1250 (quantityDa output).
        self::assertSame(['text' => '1.250', 'numeric' => false, 'date' => false], saldi_ui_cell('1.250'));
        foreach (['1.2345678901', '5.0.0', '0.00000 DKK', '.00000'] as $text) {
            self::assertSame($text, saldi_ui_cell($text)['text'], $text);
        }
        $html = $this->page($this->item(['columns' => ['Varenr.', 'Tekst', 'Antal', 'Pris', 'Rabat'], 'rows' => [['pompstitchleo', 'My little leo kit', '2', '228.00', '0.00000']]]));
        self::assertStringNotContainsString('0.00000', $html);
        self::assertStringContainsString('<td class="sac-num">228,00</td><td class="sac-num">0</td>', $html);
    }

    public function testOnlyAllowlistedEvidenceScreensBecomeLinks(): void
    {
        $html = $this->page($this->item(['evidence' => [
            ['label' => 'Kassekladde 12', 'screen' => 'finans/kassekladde.php?kladde_id=12'],
            ['label' => 'Ondsindet', 'screen' => 'javascript:alert(1)'],
            ['label' => 'Handling', 'screen' => 'finans/kassekladde.php?kladde_id=12&slet=1'],
            ['label' => 'Ude', 'screen' => '../../etc/passwd.php'],
        ]]));
        self::assertStringContainsString('href="../finans/kassekladde.php?kladde_id=12" data-saldi-screen="finans/kassekladde.php?kladde_id=12"', $html);
        self::assertStringNotContainsString('Ondsindet', $html);
        self::assertStringNotContainsString('slet=1', $html);
        self::assertStringNotContainsString('passwd', $html);
    }

    public function testOpenCardHasApproveRejectAndNoteButNoUndo(): void
    {
        $html = $this->page($this->item(['notices' => [['kind' => 'tainted', 'text' => 'Forslaget bygger delvist på tekst fra dine data.']]]));
        self::assertStringContainsString('<button type="submit" name="op" value="approve" class="sac-btn sac-btn--primary">Godkend</button>', $html);
        self::assertStringContainsString('name="op" value="reject"', $html);
        self::assertStringContainsString('<textarea id="sac-note-12" name="note" maxlength="255"', $html);
        self::assertStringContainsString('<label for="sac-note-12">', $html);
        self::assertStringContainsString('name="digest" value="' . str_repeat('a', 64) . '"', $html);
        self::assertStringContainsString('name="tenant_hash" value="' . str_repeat('b', 32) . '"', $html);
        self::assertStringContainsString('name="return" value="tab=done"', $html);
        self::assertStringNotContainsString('value="undo"', $html);
        self::assertStringNotContainsString('value="cancel"', $html);
        self::assertStringContainsString('class="sac sac--open"', $html);
        self::assertStringContainsString('Forslaget bygger delvist på tekst fra dine data.', $html);
        self::assertStringContainsString('Udløber ', $html);
        self::assertStringContainsString('Når du godkender', $html);
    }

    public function testOwnProposalCanBeWithdrawn(): void
    {
        $html = $this->page($this->item([], ['created_by' => 'user:7']));
        self::assertStringContainsString('name="op" value="cancel"', $html);
    }

    public function testUndoButtonOnlyWhenAppliedAndUndoable(): void
    {
        $result = ['kladde_id' => 14, 'line_ids' => [101, 102], 'bilag' => 17, 'created_kladde' => true];
        $undoable = $this->page($this->item(['status' => 'applied', 'can_undo' => true], ['status' => 'applied', 'result' => $result]));
        self::assertStringContainsString('name="op" value="undo"', $undoable);
        self::assertStringNotContainsString('value="approve"', $undoable);
        $fixed = $this->page($this->item(['status' => 'applied', 'can_undo' => false], ['status' => 'applied', 'result' => $result]));
        self::assertStringNotContainsString('<form', $fixed);
        $openWithFlag = $this->page($this->item(['status' => 'open', 'can_undo' => true]));
        self::assertStringNotContainsString('value="undo"', $openWithFlag);
    }

    public function testOnlyAppliedIsGreenAndClosedStatesSayNothingChanged(): void
    {
        $applied = $this->page($this->item(['status' => 'applied'], ['status' => 'applied', 'result' => ['kladde_id' => 14, 'line_ids' => [1, 2]]]));
        self::assertStringContainsString('class="sac sac--done"', $applied);
        self::assertStringContainsString('>Godkendt</span>', $applied);
        self::assertStringContainsString('2 linjer lagt i kassekladde 14. Intet er bogført.', $applied);
        self::assertStringContainsString('data-saldi-screen="finans/kassekladde.php?kladde_id=14">Åbn kassekladde 14</a>', $applied);
        foreach (['rejected' => 'Afvist', 'expired' => 'Udløbet', 'cancelled' => 'Trukket tilbage'] as $status => $label) {
            $html = $this->page($this->item(['status' => $status], ['status' => $status]));
            self::assertStringNotContainsString('sac--done', $html, $status);
            self::assertStringNotContainsString('sac-status--done', $html, $status);
            self::assertStringContainsString('>' . $label . '</span>', $html, $status);
            self::assertStringContainsString('Intet er ændret.', $html, $status);
            self::assertStringNotContainsString('<form', $html, $status);
            self::assertStringNotContainsString('Når du godkender', $html, $status);
        }
        self::assertStringContainsString('class="sac sac--warn"', $this->page($this->item(['status' => 'expired'], ['status' => 'expired'])));
    }

    public function testFailedAttemptIsAmberWithTheMessageEvenOnAnOpenCard(): void
    {
        $html = $this->page($this->item(), ['error' => ['code' => 'STALE_CONTEXT', 'message_da' => 'Grundlaget er <ændret>.']]);
        self::assertStringContainsString('class="sac sac--warn"', $html);
        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('Grundlaget er &lt;ændret&gt;.', $html);
        self::assertStringContainsString('Intet er ændret.', $html);
        self::assertStringNotContainsString('sac--done', $html);
    }

    public function testFollowupAndRoutineAppliedLabelsComeFromTheCard(): void
    {
        $task = $this->page($this->item(['kind' => 'followup.task', 'status' => 'applied', 'approve_label' => 'Udført', 'columns' => [], 'rows' => []], ['kind' => 'followup.task', 'status' => 'applied', 'result' => ['done' => true]]));
        self::assertStringContainsString('>Udført</span>', $task);
        self::assertStringContainsString('Markeret som udført.', $task);
        self::assertStringNotContainsString('sac-table', $task);
    }

    public function testModalModeRendersTheSameCardWithoutAnyControls(): void
    {
        $html = saldi_approval_card_html($this->item(), ['mode' => 'modal', 'heading_level' => 3, 'csrf' => 'secret-csrf']);
        self::assertStringNotContainsString('<form', $html);
        self::assertStringNotContainsString('<button', $html);
        self::assertStringNotContainsString('secret-csrf', $html);
        self::assertStringContainsString('<h3 class="sac-title" id="sac-m-title-12">', $html);
        self::assertStringContainsString('<td class="sac-num">1.250,00</td>', $html);
    }

    public function testReceiptWordingSingularAndUndo(): void
    {
        $one = saldi_approval_receipt(['kind' => 'journal.draft_entry', 'status' => 'applied', 'result' => ['kladde_id' => 9, 'line_ids' => [5]]], null, 1);
        self::assertSame('1 linje lagt i kassekladde 9. Intet er bogført.', $one['text']);
        self::assertSame('finans/kassekladde.php?kladde_id=9', $one['screen']);
        $undone = saldi_approval_receipt(['kind' => 'journal.draft_entry', 'status' => 'undone', 'result' => ['removed_lines' => 2]], null, 1);
        self::assertSame('Linjerne er fjernet fra kassekladden igen.', $undone['text']);
        self::assertNull(saldi_approval_receipt(['kind' => 'journal.draft_entry', 'status' => 'open'], null, 1));
        self::assertSame('2 lines added to journal 4. Nothing has been posted.', saldi_approval_receipt(['kind' => 'journal.draft_entry', 'status' => 'applied', 'result' => ['kladde_id' => 4, 'line_ids' => [1, 2]]], null, 2)['text']);
    }

    public function testReceiptButtonNamesTheDocumentItOpens(): void
    {
        $receipt = static fn(string $kind, array $payload = []): array => saldi_approval_receipt(['kind' => $kind, 'status' => 'applied', 'payload' => $payload, 'result' => ['order_id' => 7]], null, 1);
        self::assertSame('Åbn tilbuddet', $receipt('sales_order.draft', ['type' => 'offer'])['screen_label']);
        self::assertSame('Åbn ordren', $receipt('sales_order.draft', ['type' => 'order'])['screen_label']);
        self::assertSame('Åbn kreditnotaen', $receipt('credit_note.draft')['screen_label']);
        self::assertSame('Åbn ordren', $receipt('purchase_order.invoice_stage')['screen_label']);
        self::assertSame('debitor/ordre.php?id=7', $receipt('sales_order.draft', ['type' => 'offer'])['screen']);
    }

    public function testErrorMessagesAreDanishAndNeverLeakInternals(): void
    {
        self::assertSame(['code' => 'CONFLICT', 'message_da' => 'Du har skiftet regnskab i et andet vindue. Genindlæs siden.', 'detail' => ['reason' => 'tenant_changed']], saldi_approval_error(new SaldiRuntimeError('CONFLICT', 'Different diagnostic', ['reason' => 'tenant_changed']), null, 1));
        self::assertSame('Kassekladden er åben hos en anden bruger lige nu. Prøv igen om lidt.', saldi_approval_error(new SaldiRuntimeError('CONFLICT', 'Different diagnostic', ['reason' => 'journal_in_use']), null, 1)['message_da']);
        self::assertSame('Datoen ligger i en lukket periode.', saldi_approval_error(new SaldiRuntimeError('CLOSED_PERIOD', 'VAT period closed'), null, 1)['message_da']);
        $leak = saldi_approval_error(new PDOException('SQLSTATE[42P01] relation "secret_table" does not exist'), null, 1);
        self::assertSame('UNAVAILABLE', $leak['code']);
        self::assertStringNotContainsString('secret_table', $leak['message_da']);
    }

    public function testDecidedCardsShowEscapedDeciderAndNoteOnlyAfterDecision(): void
    {
        $fields = ['decided_by_name' => self::XSS, 'decision_note' => self::XSS];
        $html = $this->page($this->item(['status' => 'rejected'], $fields));
        self::assertStringContainsString('Behandlet af &lt;script&gt;', $html);
        self::assertStringContainsString('Bemærkning: &lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        $zero = $this->page($this->item(['status' => 'rejected'], ['decided_by_name' => '0', 'decision_note' => '0']));
        self::assertStringContainsString('Behandlet af 0', $zero);
        self::assertStringContainsString('Bemærkning: 0', $zero);
        $open = $this->page($this->item([], $fields));
        self::assertStringNotContainsString('Behandlet af', $open);
        self::assertStringNotContainsString('sac-decision-note', $open);
    }

    public function testLocalTimeIsCopenhagen(): void
    {
        self::assertSame('15.10.2026 kl. 12:00', saldi_ui_local_time('2026-10-15T10:00:00Z'));
        self::assertSame('15.01.2026 kl. 11:00', saldi_ui_local_time('2026-01-15 10:00:00'));
        self::assertSame('', saldi_ui_local_time(null));
        self::assertSame('', saldi_ui_local_time('not a date'));
    }
}
