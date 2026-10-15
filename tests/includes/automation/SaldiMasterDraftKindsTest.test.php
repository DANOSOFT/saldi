<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiMasterDraftKindsTest.test.php --- ai/mcp-assistant --- 2026.10.03
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
// Disposable-clone acceptance tests for parties, purchase orders and first reminders.
//
// 20261002 CDX/LH Created.
// 20261003 CDX/LH Add regression coverage for the confirmed Assist review findings.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';

final class SaldiMasterDraftKindsTest extends SaldiRuntimeDbTestCase
{
    private int $supplier;
    private int $customer;
    private int $product;
    private int $openItem;

    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->env->db;
        foreach (
            ['ordrelinjer',
            'ordrer',
            'openpost',
            'kassekladde',
            'transaktioner',
            'adresser',
            'vare_lev',
            'varer',
            'batch_kob',
            'documents'] as $table
        ) {
            $db->exec('DELETE FROM ' . $table);
        }
        $year = $this->env->ctx()->fiscalYear;
        $db->exec("DELETE FROM grupper WHERE art IN ('DG', 'KG', 'VG', 'KM', 'SM') OR (art = 'DIV' AND kodenr = 4)");
        foreach (
            [['DG',
            1,
            '',
            '',
            ''],
            ['KG',
            1,
            'K1',
            '',
            ''],
            ['KM',
            1,
            '',
            '25',
            ''],
            ['VG',
            1,
            '',
            '',
            '']] as [$art,
            $number,
            $box1,
            $box2,
            $box3]
        ) {
            $db->insert(
                'grupper',
                ['art' => $art,
                'kodenr' => $number,
                'box1' => $box1,
                'box2' => $box2,
                'box3' => $box3,
                'box4' => '',
                'box6' => '',
                'box7' => '',
                'box8' => '',
                'box9' => '',
                'fiscal_year' => $year]
            );
        }
        $db->insert('grupper', ['art' => 'DIV', 'kodenr' => 4, 'box5' => '5', 'box6' => '10']);
        $db->exec("DELETE FROM formularer WHERE beskrivelse = 'GEBYR'");
        $common = ['gruppe' => 1,
            'betalingsbet' => 'Netto',
            'betalingsdage' => 8,
            'lukket' => '',
            'addr1' => 'Testvej 1',
            'addr2' => '',
            'postnr' => '8000',
            'bynavn' => 'Aarhus',
            'land' => 'Danmark',
            'kontakt' => 'Kontakt',
            'email' => 'example@example.invalid',
            'notes' => 'Kortnote',
            'cvrnr' => '',
            'ean' => '',
            'institution' => '',
            'kontoansvarlig' => 0];
        $this->supplier = $db->insert(
            'adresser',
            ['art' => 'K',
            'kontonr' => '1000',
            'firmanavn' => 'Test leverandør'] + $common
        );
        $this->customer = $db->insert(
            'adresser',
            ['art' => 'D',
            'kontonr' => '1000',
            'firmanavn' => 'Test kunde'] + $common
        );
        $this->product = $db->insert(
            'varer',
            ['varenr' => 'TEST',
            'beskrivelse' => 'Testvare',
            'enhed' => 'stk',
            'gruppe' => 1,
            'kostpris' => '20.125',
            'salgspris' => '100.000',
            'lukket' => '',
            'serienr' => '',
            'samlevare' => '']
        );
        $db->insert(
            'vare_lev',
            ['vare_id' => $this->product,
            'lev_id' => $this->supplier,
            'kostpris' => '17.125',
            'lev_varenr' => 'LEV-TEST']
        );
        $this->openItem = $this->post();
    }

    private function businessDate(int $daysAgo = 0): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))
            ->modify('-' . $daysAgo . ' days')->format('Y-m-d');
    }

    private function post(): int
    {
        return $this->env->db->insert(
            'openpost',
            ['konto_id' => $this->customer,
            'konto_nr' => '1000',
            'amount' => '1000.000',
            'udlignet' => '0',
            'faktnr' => '123',
            'beskrivelse' => 'Faktura 123',
            'forfaldsdate' => $this->businessDate(40),
            'transdate' => $this->businessDate(50),
            'valuta' => 'DKK',
            'valutakurs' => '100']
        );
    }

    private function purchase(): array
    {
        return ['supplier_account' => '1000',
            'lines' => [['product_id' => $this->product,
            'quantity' => '2.500']],
            'delivery_date' => $this->businessDate(),
            'reference' => 'Testref',
            'note' => 'Ordrenote'];
    }

    private function reminder(): array
    {
        return ['customer_account' => '1000', 'open_item_ids' => [$this->openItem], 'note' => 'Rykkernote'];
    }

    public function testPartyInsertGapDefaultsCardAndUndo(): void
    {
        $db = $this->env->db;
        $db->insert('adresser', ['kontonr' => '1002', 'art' => 'D', 'firmanavn' => 'Anden']);
        $input = ['ledger' => 'customer',
            'name' => 'Ny virksomhed ApS',
            'cvr' => '12345678',
            'ean' => '1234567890123',
            'address' => 'Nyvej 2',
            'zip' => '8200',
            'city' => 'Aarhus',
            'country' => 'Danmark',
            'email' => 'ny@example.invalid',
            'phone' => '12345678',
            'payment_terms' => ['type' => 'lobende_maaned',
            'days' => 30]];
        $item = $this->inbox()->create($this->env->ctx(), 'party.create', $input);
        self::assertSame('Ny kunde: Ny virksomhed ApS', $item['title']);
        self::assertSame(['Felt', 'Værdi'], $item['card']['columns']);
        self::assertSame('Opret', $item['card']['approve_label']);
        $applied = $this->approve($item);
        self::assertSame('1001', $applied['result']['account']);
        $row = $db->one('SELECT * FROM adresser WHERE id = ?', [$applied['result']['party_id']]);
        self::assertSame('Lb. md.', $row['betalingsbet']);
        self::assertSame('1234567890123', $row['ean']);
        self::assertSame('0.000', $row['kreditmax']);
        self::assertEmpty($row['bank_konto']);
        self::assertTrue($applied['can_undo']);
        self::assertSame(
            'undone',
            $this->inbox()->undo(
                $this->env->ctx(),
                $applied['id'],
                $this->env->ctx()->tenantHash()
            )['status']
        );
    }

    public function testSupplierPermissionsAndContactDefaults(): void
    {
        $item = $this->inbox()->create(
            $this->env->ctx(),
            'party.create',
            ['ledger' => 'supplier',
            'name' => 'Ny leverandør',
            'contact' => 'Indkøb',
            'ean' => '1234567890123']
        );
        self::assertSame(
            'creditors.draft',
            $this->env->db->value(
                'SELECT assignee_permission FROM inbox_items WHERE id = ?',
                [$item['id']]
            )
        );
        $applied = $this->approve($item);
        $row = $this->env->db->one('SELECT * FROM adresser WHERE id = ?', [$applied['result']['party_id']]);
        self::assertSame('K', $row['art']);
        self::assertSame('Netto', $row['betalingsbet']);
        self::assertSame('8', (string)$row['betalingsdage']);
        self::assertTrue($applied['can_undo']);
    }

    public function testPartyDuplicateNameAndCvrAndUnknownFields(): void
    {
        $db = $this->env->db;
        $db->exec(
            "UPDATE adresser SET firmanavn = '  ACME   A/S ', cvrnr = '12345678' WHERE id = ?",
            [$this->customer]
        );
        foreach ([['name' => 'acme ApS'], ['name' => 'En anden', 'cvr' => '12345678']] as $fields) {
            $error = $this->error(
                'VALIDATION_ERROR',
                fn() => $this->inbox()->create(
                    $this->env->ctx(),
                    'party.create',
                    ['ledger' => 'customer'] + $fields
                )
            );
            self::assertSame('duplicate_party', $error->detail['reason']);
            self::assertSame($this->customer, $error->detail['existing']['party_id']);
        }
        $this->error(
            'VALIDATION_ERROR',
            fn() => $this->inbox()->create(
                $this->env->ctx(),
                'party.create',
                ['ledger' => 'customer',
                'name' => 'Ny',
                'bank_konto' => '123']
            )
        );
        $db->exec("UPDATE adresser SET lukket = 'on' WHERE id = ?", [$this->customer]);
        self::assertSame(
            'open',
            $this->inbox()->create(
                $this->env->ctx(),
                'party.create',
                ['ledger' => 'customer',
                'name' => 'acme']
            )['status']
        );
    }

    public function testPartyUndoBlockedByChangesAndEachReference(): void
    {
        $db = $this->env->db;
        foreach (['changed', 'openpost', 'ordrer', 'kassekladde', 'transaktioner'] as $reference) {
            $item = $this->approve($this->inbox()->create(
                $this->env->ctx(),
                'party.create',
                ['ledger' => 'customer',
                'name' => 'Ny ' . $reference]
            ));
            $id = $item['result']['party_id'];
            $account = $item['result']['account'];
            if ($reference === 'changed') {
                $db->exec("UPDATE adresser SET tlf = 'ændret' WHERE id = ?", [$id]);
            } elseif ($reference === 'kassekladde') {
                $db->insert('kassekladde', ['d_type' => 'D', 'debet' => $account]);
            } elseif ($reference === 'transaktioner') {
                $db->insert('transaktioner', ['adresser_id' => $id]);
            } else {
                $db->insert($reference, ['konto_id' => $id]);
            }
            self::assertFalse($this->inbox()->get($this->env->ctx(), $item['id'])['can_undo']);
            $this->error(
                'CONFLICT',
                fn() => $this->inbox()->undo(
                    $this->env->ctx(),
                    $item['id'],
                    $this->env->ctx()->tenantHash()
                )
            );
        }
    }

    public function testPurchaseInsertSupplierPriceNumberCardAndUndo(): void
    {
        $db = $this->env->db;
        $db->insert('ordrer', ['art' => 'KK', 'ordrenr' => 87]);
        $item = $this->inbox()->create($this->env->ctx(), 'purchase_order.draft', $this->purchase());
        self::assertSame(['Varenr.', 'Tekst', 'Antal', 'Pris'], $item['card']['columns']);
        $item = $this->approve($item);
        self::assertSame(88, $item['result']['order_number']);
        $header = $db->one('SELECT * FROM ordrer WHERE id = ?', [$item['result']['order_id']]);
        self::assertSame('KO', $header['art']);
        self::assertSame(0, $header['status']);
        self::assertSame('25.000', $header['momssats']);
        self::assertSame('Testref', $header['ref']);
        self::assertEmpty($header['hvem']);
        self::assertEmpty($header['tidspkt']);
        $line = $db->one('SELECT * FROM ordrelinjer WHERE ordre_id = ?', [$header['id']]);
        self::assertSame('17.125', $line['pris']);
        self::assertSame('2.500', $line['antal']);
        self::assertSame('LEV-TEST', $line['lev_varenr']);
        self::assertTrue($item['can_undo']);
        $this->inbox()->undo($this->env->ctx(), $item['id'], $this->env->ctx()->tenantHash());
        self::assertNull($db->value('SELECT id FROM ordrer WHERE id = ?', [$header['id']]));
    }

    public function testPurchaseClosedProductsInvalidLinesAndRevalidation(): void
    {
        $db = $this->env->db;
        $item = $this->inbox()->create($this->env->ctx(), 'purchase_order.draft', $this->purchase());
        $db->exec("UPDATE varer SET lukket = 'on' WHERE id = ?", [$this->product]);
        $this->error('VALIDATION_ERROR', fn() => $this->approve($item));
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM ordrer'));
        foreach (
            [[],
            [['description' => 'Tekst',
            'quantity' => '0']],
            [['item_no' => 'Ukendt',
            'quantity' => '1']]] as $lines
        ) {
            $this->error(
                'VALIDATION_ERROR',
                fn() => $this->inbox()->create(
                    $this->env->ctx(),
                    'purchase_order.draft',
                    ['supplier_account' => '1000',
                    'lines' => $lines]
                )
            );
        }
    }

    public function testPurchaseFallbackPriceCommentAndStaleSupplier(): void
    {
        $db = $this->env->db;
        $db->exec("UPDATE vare_lev SET kostpris = '0'");
        $input = $this->purchase();
        $input['lines'][] = ['description' => 'Kommentar', 'quantity' => '1'];
        $item = $this->inbox()->create($this->env->ctx(), 'purchase_order.draft', $input);
        self::assertArrayNotHasKey('unit_price', $item['payload']['lines'][0]);
        $db->exec("UPDATE adresser SET addr1 = 'Anden vej' WHERE id = ?", [$this->supplier]);
        $this->error('STALE_CONTEXT', fn() => $this->approve($item));
    }

    public function testPurchaseUndoBlockedByHeaderLinesDocumentsAndBatch(): void
    {
        $db = $this->env->db;
        foreach (['header', 'line', 'documents', 'batch_kob'] as $change) {
            $item = $this->approve($this->inbox()->create(
                $this->env->ctx(),
                'purchase_order.draft',
                $this->purchase()
            ));
            $id = $item['result']['order_id'];
            if ($change === 'header') {
                $db->exec('UPDATE ordrer SET status = 1 WHERE id = ?', [$id]);
            } elseif ($change === 'line') {
                $db->exec('UPDATE ordrelinjer SET antal = 3 WHERE ordre_id = ?', [$id]);
            } elseif ($change === 'documents') {
                $db->insert('documents', ['source' => 'kreditor', 'source_id' => $id]);
            } else {
                $db->insert('batch_kob', ['ordre_id' => $id]);
            }
            self::assertFalse($this->inbox()->get($this->env->ctx(), $item['id'])['can_undo']);
            $this->error(
                'CONFLICT',
                fn() => $this->inbox()->undo(
                    $this->env->ctx(),
                    $item['id'],
                    $this->env->ctx()->tenantHash()
                )
            );
        }
    }

    public function testReminderFeeInterestNumberCommentsCardAndUndo(): void
    {
        $db = $this->env->db;
        $db->insert('ordrer', ['art' => 'R3', 'ordrenr' => 12]);
        $interestProduct = $db->insert('varer', ['varenr' => 'RENTE', 'beskrivelse' => 'Rente']);
        $db->insert(
            'formularer',
            ['beskrivelse' => 'GEBYR',
            'formular' => 6,
            'xb' => $this->product,
            'yb' => $interestProduct,
            'str' => '2']
        );
        $item = $this->inbox()->create($this->env->ctx(), 'reminder.draft', $this->reminder());
        self::assertSame(['Faktura', 'Forfald', 'Beløb'], $item['card']['columns']);
        $item = $this->approve($item);
        self::assertSame(13, $item['result']['reminder_number']);
        $header = $db->one('SELECT * FROM ordrer WHERE id = ?', [$item['result']['order_id']]);
        self::assertSame('R1', $header['art']);
        self::assertSame('13-1', $header['fakturanr']);
        self::assertSame(2, $header['status']);
        self::assertSame('-', $header['betalt']);
        self::assertSame('10', (string)$header['betalingsdage']);
        self::assertEmpty($header['hvem']);
        $lines = $db->all('SELECT * FROM ordrelinjer WHERE ordre_id = ? ORDER BY posnr', [$header['id']]);
        self::assertCount(3, $lines);
        self::assertSame((string)$this->openItem, $lines[0]['enhed']);
        self::assertSame(
            $db->value(
                'SELECT forfaldsdate FROM openpost WHERE id = ?',
                [$this->openItem]
            ),
            $lines[0]['serienr']
        );
        self::assertSame('100.000', $lines[2]['pris']);
        self::assertTrue($item['can_undo']);
        $this->inbox()->undo($this->env->ctx(), $item['id'], $this->env->ctx()->tenantHash());
        self::assertSame('0', $db->value('SELECT udlignet FROM openpost WHERE id = ?', [$this->openItem]));
    }

    public function testPurchaseLineAcceptsMatchingProductIdAndItemNumber(): void
    {
        $varenr = (string)$this->env->db->value('SELECT varenr FROM varer WHERE id = ?', [$this->product]);
        $input = $this->purchase();
        $input['lines'][0]['item_no'] = strtolower($varenr);
        $item = $this->inbox()->create($this->env->ctx(), 'purchase_order.draft', $input);
        self::assertSame($this->product, $item['payload']['lines'][0]['product_id']);
        $input['lines'][0]['item_no'] = $varenr . '-other';
        try {
            $this->inbox()->create($this->env->ctx(), 'purchase_order.draft', $input + ['note' => 'mismatch']);
            self::fail('Expected a mismatch error');
        } catch (SaldiRuntimeError $e) {
            self::assertSame('product_mismatch', $e->detail['reason'] ?? null);
        }
    }

    public function testReminderWithoutItemIdsTakesEveryEligibleOverdueItem(): void
    {
        $input = $this->reminder();
        unset($input['open_item_ids']);
        $item = $this->inbox()->create($this->env->ctx(), 'reminder.draft', $input);
        self::assertSame([$this->openItem], $item['payload']['open_item_ids']);
        $this->env->db->exec("UPDATE openpost SET udlignet = '1' WHERE id = ?", [$this->openItem]);
        try {
            $again = $this->reminder();
            unset($again['open_item_ids']);
            $this->inbox()->create($this->env->ctx(), 'reminder.draft', $again + ['note' => 'second']);
            self::fail('Expected no eligible items');
        } catch (SaldiRuntimeError $e) {
            self::assertSame('VALIDATION_ERROR', $e->errorCode);
            self::assertSame('no_overdue_items', $e->detail['reason'] ?? null);
        }
    }

    public function testReminderRejectsIneligibleItemsAndAlreadyReminded(): void
    {
        $db = $this->env->db;
        foreach (
            [['udlignet',
            '1'],
            ['konto_id',
            $this->supplier],
            ['forfaldsdate',
            $this->businessDate()]] as [$column,
            $value]
        ) {
            $original = $db->value('SELECT ' . $column . ' FROM openpost WHERE id = ?', [$this->openItem]);
            $db->exec('UPDATE openpost SET ' . $column . ' = ? WHERE id = ?', [$value, $this->openItem]);
            $error = $this->error(
                'VALIDATION_ERROR',
                fn() => $this->inbox()->create(
                    $this->env->ctx(),
                    'reminder.draft',
                    $this->reminder()
                )
            );
            self::assertSame($this->openItem, $error->detail['open_item_id']);
            $db->exec('UPDATE openpost SET ' . $column . ' = ? WHERE id = ?', [$original, $this->openItem]);
        }
        $item = $this->approve($this->inbox()->create($this->env->ctx(), 'reminder.draft', $this->reminder()));
        $this->error(
            'VALIDATION_ERROR',
            fn() => $this->inbox()->create(
                $this->env->ctx(),
                'reminder.draft',
                $this->reminder()
            )
        );
        $db->exec('UPDATE ordrer SET status = 3 WHERE id = ?', [$item['result']['order_id']]);
        self::assertFalse($this->inbox()->get($this->env->ctx(), $item['id'])['can_undo']);
    }

    /** @return array{process:resource,pipes:array,pid:int} */
    private function startWorker(string $kind, array $input): array
    {
        $process = proc_open(
            [PHP_BINARY,
                __DIR__ . '/../support/SaldiMasterDraftWorker.php',
                $this->env->name,
                (string)$this->env->regnskab['id'],
                $kind,
                json_encode(
                    $input,
                    JSON_THROW_ON_ERROR
                )],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        self::assertIsResource($process);
        stream_set_timeout($pipes[1], 10);
        $ready = json_decode((string)fgets($pipes[1]), true, 512, JSON_THROW_ON_ERROR);
        return ['process' => $process, 'pipes' => $pipes, 'pid' => $ready['pid']];
    }

    /** @return list<array> */
    private function race(string $kind, array $inputs, string $lock): array
    {
        $db = $this->env->db;
        $workers = [];
        try {
            $db->transaction(function () use ($db, $kind, $inputs, $lock, &$workers): void {
                if (in_array($lock, ['legacy', 'supplier_legacy'], true)) {
                    $db->value('SELECT pg_advisory_xact_lock(?)', [$lock === 'legacy' ? 5130513 : 5130514]);
                } else {
                    $db->lock($lock);
                }
                foreach ($inputs as $input) {
                    $workers[] = $this->startWorker($kind, $input);
                }
                foreach ($workers as $worker) {
                    $deadline = microtime(true) + 3;
                    do {
                        $waiting = $db->value(
                            'SELECT 1 FROM pg_locks WHERE pid = ? AND NOT granted LIMIT 1',
                            [$worker['pid']]
                        );
                        if ($waiting === null) {
                            usleep(10000);
                        }
                    } while ($waiting === null && microtime(true) < $deadline);
                    self::assertNotNull($waiting, 'Worker must block behind the allocation lock');
                }
            });
            $results = [];
            foreach ($workers as $worker) {
                $results[] = json_decode((string)fgets($worker['pipes'][1]), true, 512, JSON_THROW_ON_ERROR);
                self::assertSame('', stream_get_contents($worker['pipes'][2]));
            }
            return $results;
        } finally {
            foreach ($workers as $worker) {
                foreach ($worker['pipes'] as $pipe) {
                    fclose($pipe);
                }
                self::assertSame(0, proc_close($worker['process']));
            }
        }
    }

    public function testConcurrentPartyOrderAndReminderAllocationsHoldLocks(): void
    {
        foreach (['customer' => 'legacy', 'supplier' => 'supplier_legacy'] as $ledger => $lock) {
            $results = $this->race('party.create', [
                ['ledger' => $ledger, 'name' => 'Concurrent Alpha ' . $ledger],
                ['ledger' => $ledger, 'name' => 'Concurrent Beta ' . $ledger],
            ], $lock);
            $accounts = array_column(array_column($results, 'result'), 'account');
            sort($accounts);
            self::assertSame(['1001', '1002'], $accounts);
            self::assertNotSame($results[0]['result']['party_id'], $results[1]['result']['party_id']);
        }
        $results = $this->race('purchase_order.draft', [$this->purchase(), $this->purchase()], 'saldi_ordrer_number');
        $numbers = array_column(array_column($results, 'result'), 'order_number');
        sort($numbers);
        self::assertSame([1, 2], $numbers);
        $other = $this->reminder();
        $other['open_item_ids'] = [$this->post()];
        $results = $this->race('reminder.draft', [$this->reminder(), $other], 'saldi_ordrer_number');
        $numbers = array_column(array_column($results, 'result'), 'reminder_number');
        sort($numbers);
        self::assertSame([1, 2], $numbers);
    }

    public function testConcurrentDuplicatePartyAndReminderAreRecheckedAfterLock(): void
    {
        $input = ['ledger' => 'customer', 'name' => 'Concurrent duplicate'];
        $results = $this->race('party.create', [$input, $input], 'legacy');
        self::assertCount(1, array_filter($results, fn(array $r): bool => isset($r['result'])));
        self::assertCount(
            1,
            array_filter(
                $results,
                fn(array $r): bool => ($r['detail']['reason'] ?? '') === 'duplicate_party'
            )
        );
        $results = $this->race('reminder.draft', [$this->reminder(), $this->reminder()], 'saldi_ordrer_number');
        self::assertCount(1, array_filter($results, fn(array $r): bool => isset($r['result'])));
        self::assertCount(1, array_filter($results, fn(array $r): bool => ($r['error'] ?? '') === 'VALIDATION_ERROR'));
    }

    public function testCustomerContactIsReversedOnlyWhileIntact(): void
    {
        $item = $this->approve($this->inbox()->create(
            $this->env->ctx(),
            'party.create',
            ['ledger' => 'customer',
            'name' => 'Kontaktkunde',
            'contact' => 'Karin']
        ));
        self::assertNotNull($item['result']['contact_id']);
        self::assertTrue($item['can_undo']);
        $this->env->db->exec("UPDATE ansatte SET navn = 'En anden' WHERE id = ?", [$item['result']['contact_id']]);
        self::assertFalse($this->inbox()->get($this->env->ctx(), $item['id'])['can_undo']);
        $item = $this->approve($this->inbox()->create(
            $this->env->ctx(),
            'party.create',
            ['ledger' => 'customer',
            'name' => 'Anden kontaktkunde',
            'contact' => 'Kurt']
        ));
        $this->inbox()->undo($this->env->ctx(), $item['id'], $this->env->ctx()->tenantHash());
        self::assertNull($this->env->db->value('SELECT id FROM ansatte WHERE id = ?', [$item['result']['contact_id']]));
    }

    public function testInstalledPartyColumnLimitsAreRejectedWithoutTruncation(): void
    {
        $error = $this->error(
            'VALIDATION_ERROR',
            fn() => $this->inbox()->create(
                $this->env->ctx(),
                'party.create',
                ['ledger' => 'customer',
                'name' => str_repeat(
                    'ø',
                    61
                )]
            )
        );
        self::assertSame('schema_limit', $error->detail['reason']);
        self::assertSame(60, $error->detail['max_length']);
    }

    public function testReminderGraceBoundaryDuplicateIdsLevelAndStaleAmounts(): void
    {
        $db = $this->env->db;
        $db->exec(
            'UPDATE openpost SET forfaldsdate = ? WHERE id = ?',
            [$this->businessDate(5),
            $this->openItem]
        );
        $item = $this->inbox()->create($this->env->ctx(), 'reminder.draft', $this->reminder());
        $db->exec('UPDATE openpost SET amount = 900 WHERE id = ?', [$this->openItem]);
        $this->error('STALE_CONTEXT', fn() => $this->approve($item));
        $input = $this->reminder();
        $input['open_item_ids'][] = $this->openItem;
        $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($this->env->ctx(), 'reminder.draft', $input));
        $input = $this->reminder();
        $input['level'] = 2;
        $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($this->env->ctx(), 'reminder.draft', $input));
        $db->exec(
            'UPDATE openpost SET forfaldsdate = ? WHERE id = ?',
            [$this->businessDate(4),
            $this->openItem]
        );
        $this->error(
            'VALIDATION_ERROR',
            fn() => $this->inbox()->create(
                $this->env->ctx(),
                'reminder.draft',
                $this->reminder()
            )
        );
    }

    public function testReminderChangedRowsAndDocumentsBlockUndo(): void
    {
        $db = $this->env->db;
        $item = $this->approve($this->inbox()->create($this->env->ctx(), 'reminder.draft', $this->reminder()));
        $db->exec("UPDATE ordrelinjer SET beskrivelse = 'Ændret' WHERE ordre_id = ?", [$item['result']['order_id']]);
        $this->error(
            'CONFLICT',
            fn() => $this->inbox()->undo(
                $this->env->ctx(),
                $item['id'],
                $this->env->ctx()->tenantHash()
            )
        );
        $input = $this->reminder();
        $input['open_item_ids'] = [$this->post()];
        $item = $this->approve($this->inbox()->create($this->env->ctx(), 'reminder.draft', $input));
        $db->insert('documents', ['source' => 'debitorOrdrer', 'source_id' => $item['result']['order_id']]);
        self::assertFalse($this->inbox()->get($this->env->ctx(), $item['id'])['can_undo']);
    }

    public function testSupplierOnlyWriterMayCreateAndApproveSupplierButNotCustomer(): void
    {
        $ctx = $this->env->ctx(910001, '0000000010000000');
        $item = $this->inbox()->create($ctx, 'party.create', ['ledger' => 'supplier', 'name' => 'Kun leverandør']);
        self::assertSame('applied', $this->approve($item, $ctx)['status']);
        $this->error('PERMISSION_DENIED', fn() => $this->inbox()->create($ctx, 'party.create', [
            'ledger' => 'customer', 'name' => 'Ingen kunder',
        ]));
    }

    public function testPurchaseItemNumberZeroPriceAndVatAccountAreStored(): void
    {
        $db = $this->env->db;
        $year = $this->env->ctx()->fiscalYear;
        $db->exec("UPDATE grupper SET box4 = '4321' WHERE art = 'VG'");
        $db->insert('kontoplan', [
            'kontonr' => 4321, 'regnskabsaar' => $year, 'moms' => 'S3',
        ]);
        $db->insert('grupper', [
            'art' => 'SM', 'kodenr' => 3, 'box2' => '12', 'fiscal_year' => $year,
        ]);
        $input = ['supplier_account' => '1000', 'lines' => [
            ['item_no' => 'TEST', 'quantity' => '1', 'unit_price' => '0.00'],
            ['description' => 'Kun tekst', 'quantity' => '1'],
        ]];
        $item = $this->approve($this->inbox()->create($this->env->ctx(), 'purchase_order.draft', $input));
        $lines = $db->all('SELECT * FROM ordrelinjer WHERE ordre_id = ? ORDER BY posnr', [
            $item['result']['order_id'],
        ]);
        self::assertSame('0.000', $lines[0]['pris']);
        self::assertSame(4321, $lines[0]['bogf_konto']);
        self::assertSame('12.000', $lines[0]['momssats']);
        self::assertNull($lines[1]['vare_id']);
        self::assertNull($lines[1]['antal']);
    }

    public function testReminderCompoundInterestAndFeeTotalsUseDecimals(): void
    {
        $db = $this->env->db;
        $due = (new DateTimeImmutable(
            'first day of last month',
            new DateTimeZone('Europe/Copenhagen')
        ))->format('Y-m-d');
        $db->exec('UPDATE openpost SET forfaldsdate = ? WHERE id = ?', [$due, $this->openItem]);
        $interestProduct = $db->insert('varer', ['varenr' => 'RENTE', 'beskrivelse' => 'Rente']);
        $db->insert('formularer', [
            'beskrivelse' => 'GEBYR', 'formular' => 6, 'xb' => $this->product,
            'yb' => $interestProduct, 'str' => '2.000',
        ]);
        $item = $this->approve($this->inbox()->create($this->env->ctx(), 'reminder.draft', $this->reminder()));
        self::assertSame('40.400', $db->value('SELECT pris FROM ordrelinjer WHERE ordre_id = ? AND vare_id = ?', [
            $item['result']['order_id'], $interestProduct,
        ]));
        self::assertSame('140.400', $db->value('SELECT sum FROM ordrer WHERE id = ?', [
            $item['result']['order_id'],
        ]));
        self::assertContains(['label' => 'Rente', 'value' => '40.40 DKK'], $item['card']['fields']);
    }

    public function testPurchaseAndReminderForeignCurrencyRatesAndFee(): void
    {
        $db = $this->env->db;
        $year = $this->env->ctx()->fiscalYear;
        $db->exec("UPDATE grupper SET box3 = 'EUR' WHERE art IN ('KG','DG')");
        $db->insert('grupper', [
            'art' => 'VK', 'kodenr' => 77, 'box1' => 'EUR', 'fiscal_year' => $year,
        ]);
        $db->insert('valuta', [
            'gruppe' => 77, 'kurs' => '750.000', 'valdate' => $this->businessDate(),
        ]);
        $purchase = $this->approve($this->inbox()->create(
            $this->env->ctx(),
            'purchase_order.draft',
            $this->purchase()
        ));
        self::assertSame('2.283', $db->value('SELECT pris FROM ordrelinjer WHERE ordre_id = ?', [
            $purchase['result']['order_id'],
        ]));
        $db->insert('formularer', [
            'beskrivelse' => 'GEBYR', 'formular' => 6, 'xb' => $this->product, 'str' => 0,
        ]);
        $item = $this->approve($this->inbox()->create($this->env->ctx(), 'reminder.draft', $this->reminder()));
        self::assertSame('13.333', $db->value('SELECT pris FROM ordrelinjer WHERE ordre_id = ? AND vare_id = ?', [
            $item['result']['order_id'], $this->product,
        ]));
        self::assertSame('EUR', $db->value('SELECT valuta FROM ordrer WHERE id = ?', [$item['result']['order_id']]));
    }

    public function testPurchaseAssemblyMirrorsLegacyComponentLines(): void
    {
        $db = $this->env->db;
        $assembly = $db->insert('varer', [
            'varenr' => 'SAMLE', 'beskrivelse' => 'Samlevare', 'gruppe' => 1,
            'samlevare' => 'on', 'kostpris' => '20.000',
        ]);
        $db->insert('styklister', [
            'indgaar_i' => $assembly, 'vare_id' => $this->product, 'antal' => '3', 'posnr' => 1,
        ]);
        $input = ['supplier_account' => '1000', 'lines' => [
            ['product_id' => $assembly, 'quantity' => '2'],
        ]];
        $item = $this->approve($this->inbox()->create($this->env->ctx(), 'purchase_order.draft', $input));
        $line = $db->one('SELECT * FROM ordrelinjer WHERE ordre_id = ?', [$item['result']['order_id']]);
        self::assertSame($this->product, $line['vare_id']);
        self::assertSame('6.000', $line['antal']);
        self::assertSame('6.000', $line['leveres']);
        self::assertSame(100, $line['posnr']);
        self::assertSame('20.125', $line['pris']);
        self::assertSame('20.000', $db->value('SELECT kostpris FROM varer WHERE id = ?', [$assembly]));
        self::assertTrue($item['can_undo']);
    }

    public function testPartyUndoBlocksLinkedSupplierAndReferencedContact(): void
    {
        $db = $this->env->db;
        $supplier = $this->approve($this->inbox()->create($this->env->ctx(), 'party.create', [
            'ledger' => 'supplier', 'name' => 'Leverandør med vare',
        ]));
        $db->insert('vare_lev', ['vare_id' => $this->product, 'lev_id' => $supplier['result']['party_id']]);
        self::assertFalse($this->inbox()->get($this->env->ctx(), $supplier['id'])['can_undo']);
        $customer = $this->approve($this->inbox()->create($this->env->ctx(), 'party.create', [
            'ledger' => 'customer', 'name' => 'Kontakt med bruger', 'contact' => 'Karin',
        ]));
        $db->exec('UPDATE brugere SET ansat_id = ? WHERE id = ?', [
            $customer['result']['contact_id'], $this->env->ctx()->userId,
        ]);
        self::assertFalse($this->inbox()->get($this->env->ctx(), $customer['id'])['can_undo']);
    }

    public function testUnknownDraftFieldsAndInvalidPartyFieldsFailBeforeWrites(): void
    {
        $inputs = [
            ['ledger' => 'customer', 'name' => 'Ny', 'cvr' => '123'],
            ['ledger' => 'customer', 'name' => 'Ny', 'ean' => '123'],
            ['ledger' => 'customer', 'name' => 'Ny', 'email' => 'invalid'],
            ['ledger' => 'customer', 'name' => 'Ny', 'group' => 999],
            ['ledger' => 'customer', 'name' => 'Ny', 'payment_terms' => ['type' => 'netto', 'days' => 121]],
        ];
        foreach ($inputs as $input) {
            $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($this->env->ctx(), 'party.create', $input));
        }
        $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create(
            $this->env->ctx(),
            'purchase_order.draft',
            $this->purchase() + ['hvem' => 'override']
        ));
        $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create(
            $this->env->ctx(),
            'reminder.draft',
            $this->reminder() + ['tidspkt' => 'override']
        ));
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM inbox_items'));
        self::assertSame(0, (int)$this->env->db->value('SELECT COUNT(*) FROM ordrer'));
    }

    public function testReminderWithMissingLiveRateCanStillBeReadAndRejected(): void
    {
        $db = $this->env->db;
        $db->exec("UPDATE grupper SET box3 = 'EUR' WHERE art = 'DG'");
        $db->insert('grupper', ['art' => 'VK', 'kodenr' => 77, 'box1' => 'EUR']);
        $db->insert('valuta', ['gruppe' => 77, 'kurs' => '750', 'valdate' => $this->businessDate()]);
        $db->insert('formularer', [
            'beskrivelse' => 'GEBYR', 'formular' => 6, 'xb' => $this->product, 'str' => 0,
        ]);
        $ctx = $this->env->ctx();
        $item = $this->inbox()->create($ctx, 'reminder.draft', $this->reminder());
        $db->exec('DELETE FROM valuta WHERE gruppe = 77');
        self::assertSame('open', $this->inbox()->preview($ctx, $item['id'])['status']);
        self::assertSame('rejected', $this->inbox()->decide(
            $ctx,
            $item['id'],
            'reject',
            $item['digest'],
            $ctx->tenantHash()
        )['status']);
        self::assertSame(0, (int)$db->value('SELECT COUNT(*) FROM ordrer'));
    }
    public function testApprovalUsesLiveRateAndApproverWithoutChangingProposalRevision(): void
    {
        $db = $this->env->db;
        $ctx = $this->env->ctx();
        $other = $this->env->ctx(910002);
        $employee = $db->insert('ansatte', ['navn' => 'Anden godkender', 'afd' => 77]);
        $db->exec('UPDATE brugere SET ansat_id = ? WHERE id = ?', [$employee, $other->userId]);
        $db->insert('grupper', ['art' => 'AFD', 'kodenr' => 77, 'box1' => '88']);
        $db->exec("UPDATE grupper SET box3 = 'EUR' WHERE art IN ('KG','DG')");
        $db->insert('grupper', ['art' => 'VK', 'kodenr' => 77, 'box1' => 'EUR']);
        $db->insert('valuta', ['gruppe' => 77, 'kurs' => '750', 'valdate' => $this->businessDate(1)]);
        $purchase = $this->purchase();
        unset($purchase['reference']);
        $purchase['order_date'] = $this->businessDate(1);
        $order = $this->inbox()->create($ctx, 'purchase_order.draft', $purchase);
        $reminder = $this->inbox()->create($ctx, 'reminder.draft', $this->reminder() + [
            'reminder_date' => $this->businessDate(1),
        ]);
        $db->insert('valuta', ['gruppe' => 77, 'kurs' => '800', 'valdate' => $this->businessDate()]);
        foreach ([$order, $reminder] as $item) {
            $revision = $db->value('SELECT proposed_rev FROM inbox_items WHERE id = ?', [$item['id']]);
            $kind = SaldiKindRegistry::default()->get($item['kind']);
            self::assertSame($revision, $kind->currentRevision($other, $db, $item));
            $applied = $this->approve($item, $other);
            $row = $db->one('SELECT * FROM ordrer WHERE id = ?', [$applied['result']['order_id']]);
            self::assertSame($this->businessDate(1), $row['ordredate']);
            self::assertSame('800.000', $row['valutakurs']);
            self::assertSame('800.000', $applied['result']['currency_rate']);
            self::assertSame($this->businessDate(), $applied['result']['approval_date']);
            if ($item['kind'] === 'purchase_order.draft') {
                self::assertSame(77, $row['afd']);
                self::assertSame(88, $row['lager']);
                self::assertSame('Anden godkender', $row['ref']);
                self::assertSame('2.141', $db->value('SELECT pris FROM ordrelinjer WHERE ordre_id = ?', [$row['id']]));
                self::assertSame(88, $applied['result']['warehouse']);
            } else {
                self::assertSame($this->businessDate(1), $row['fakturadate']);
            }
        }
    }

    public function testProposalRevisionDetectsChangesEvenToExplicitlyPricedProductSources(): void
    {
        $db = $this->env->db;
        $purchase = $this->purchase();
        $purchase['lines'][0]['unit_price'] = '17.125';
        $item = $this->inbox()->create($this->env->ctx(), 'purchase_order.draft', $purchase);
        $db->exec('UPDATE vare_lev SET kostpris = 99 WHERE vare_id = ?', [$this->product]);
        $this->error('STALE_CONTEXT', fn() => $this->approve($item));
        $db->insert('formularer', ['beskrivelse' => 'GEBYR', 'formular' => 6, 'xb' => $this->product]);
        $item = $this->inbox()->create($this->env->ctx(), 'reminder.draft', $this->reminder());
        $db->exec('UPDATE varer SET salgspris = 200 WHERE id = ?', [$this->product]);
        $this->error('STALE_CONTEXT', fn() => $this->approve($item));
    }

    public function testPartyUndoRefusesDeliveryEmailAndUnownedContactsWithoutDeletingThem(): void
    {
        $db = $this->env->db;
        foreach (['delivery_addresses' => 'account_id', 'kontakt_emails' => 'konto_id', 'ansatte' => 'konto_id'] as $table => $column) {
            if (!$db->hasTable($table)) {
                $db->exec('CREATE TABLE ' . $table . ' (id serial PRIMARY KEY, ' . $column . ' integer)');
            }
            $item = $this->approve($this->inbox()->create($this->env->ctx(), 'party.create', [
                'ledger' => 'customer', 'name' => 'Referenced ' . $table, 'contact' => 'Created contact',
            ]));
            $reference = [$column => $item['result']['party_id']];
            if ($table === 'kontakt_emails') {
                $reference['email'] = 'contact@example.invalid';
            }
            $id = $db->insert($table, $reference);
            self::assertFalse($this->inbox()->get($this->env->ctx(), $item['id'])['can_undo']);
            $this->error('CONFLICT', fn() => $this->inbox()->undo($this->env->ctx(), $item['id'], $this->env->ctx()->tenantHash()));
            self::assertNotNull($db->value('SELECT id FROM ' . $table . ' WHERE id = ?', [$id]));
            self::assertNotNull($db->value('SELECT id FROM ansatte WHERE id = ?', [$item['result']['contact_id']]));
        }
    }

    public function testReminderRequiresPositiveDebtAndPositiveBalanceAfterUnsettledCredits(): void
    {
        $db = $this->env->db;
        foreach (['-10', '0'] as $amount) {
            $db->exec('UPDATE openpost SET amount = ? WHERE id = ?', [$amount, $this->openItem]);
            $error = $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($this->env->ctx(), 'reminder.draft', $this->reminder()));
            self::assertSame('nonpositive_debt', $error->detail['reason']);
        }
        $db->exec('UPDATE openpost SET amount = 1000 WHERE id = ?', [$this->openItem]);
        $credit = $db->insert('openpost', ['konto_id' => $this->customer, 'amount' => '-1000', 'udlignet' => '', 'valuta' => 'DKK']);
        $error = $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($this->env->ctx(), 'reminder.draft', $this->reminder()));
        self::assertSame('nonpositive_net_balance', $error->detail['reason']);
        $db->exec('UPDATE openpost SET amount = -1200 WHERE id = ?', [$credit]);
        $this->error('VALIDATION_ERROR', fn() => $this->inbox()->create($this->env->ctx(), 'reminder.draft', $this->reminder()));
        $db->exec("UPDATE openpost SET udlignet = '1' WHERE id = ?", [$credit]);
        self::assertSame('open', $this->inbox()->create($this->env->ctx(), 'reminder.draft', $this->reminder())['status']);
    }

}
