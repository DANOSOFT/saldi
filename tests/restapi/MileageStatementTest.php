<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MileageStatementTest.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Created.
// 20261001 CDX/LH Verify page margins, cross-year rollback and recovery after replay cleanup.
// 20261001 CDX/LH Regress incompatible UUIDs, expired reservations and publication replay pruning.

require_once __DIR__ . '/support/T4ClaimsTestCase.php';
require_once __DIR__ . '/../../includes/docsIncludes/poolReviewVersion.php';

final class MileageStatementTest extends T4ClaimsTestCase
{
    private const ROUTE = 'employee/mileage/statements.php';

    protected function setUp(): void
    {
        self::sql("UPDATE mobile_mileage_statements SET state='withdrawn'");
        self::sql('UPDATE mobile_mileage_trips SET deleted_at=now()');
        self::setting('km_threshold_2026', '20000');
        self::setting('km_rate_2026', '3.79');
        self::setting('km_rate_low_2026', '2.23');
        self::setting('claims_mileage_account', '987611');
        self::setting('km_rate_2027', '4.00');
        self::setting('km_threshold_2027', '20000');
    }

    /** @return array{status:int,json:?array,body:string,headers:string} Capture replay headers as well as JSON. */
    protected function request(string $method, string $route, ?array $body = null, ?array $headers = null): array
    {
        $curl = curl_init(RestApiEnv::baseUrl() . '/restapi/endpoints/v1/' . $route);
        $responseHeaders = '';
        try {
            curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>40, CURLOPT_CUSTOMREQUEST=>$method,
                CURLOPT_HTTPHEADER=>array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers ?? self::$headers),
                CURLOPT_HEADERFUNCTION=>static function ($handle, string $line) use (&$responseHeaders): int {
                    $responseHeaders .= $line;
                    return strlen($line);
                }]);
            if ($body !== null) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
            }
            $raw = curl_exec($curl);
            if ($raw === false) {
                throw new RuntimeException('Test HTTP request failed: ' . curl_error($curl));
            }
            return ['status'=>(int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'json'=>json_decode($raw, true),
                'body'=>$raw, 'headers'=>$responseHeaders];
        } finally {
            curl_close($curl);
        }
    }

    private function issueBody(string $from = '2026-10-01', string $to = '2026-10-31'): array
    {
        return $this->body(['period_from'=>$from, 'period_to'=>$to]);
    }

    private function addTrip(string $date, string $km, ?int $user = null, array $fields = []): int
    {
        return $this->service($user)->createTrip($this->tripBody($fields + ['date'=>$date, 'km'=>$km]))['body']['data']['id'];
    }

    private function stored(string $client): array
    {
        return self::sql('SELECT * FROM mobile_mileage_statements WHERE client_id=$1', [$client])[0];
    }

    public function test_statement_text_lists_every_trip_with_danish_escaping_split_rates_and_totals(): void
    {
        self::setting('km_threshold_2026', '50');
        $first = $this->addTrip('2026-10-01', '42.0', null, ['from'=>'Århus (kontor) \\ base', 'to'=>'Østerbro', 'purpose'=>'Besøg hos kunde']);
        $second = $this->addTrip('2026-10-02', '20.0', null, ['from'=>'Odense', 'to'=>'København', 'purpose'=>'Levering']);
        $body = $this->issueBody();
        $data = $this->assertStatus(201, $this->request('POST', self::ROUTE, $body));
        $this->assertSame('62.0', $data['km_total']);
        $this->assertSame('216.26', $data['amount']);
        $this->assertSame([$first, $second], array_column($data['lines'], 'trip_id'));
        $this->assertSame('159.18', $data['lines'][0]['amount']);
        $this->assertSame('8.0', $data['lines'][1]['km_high']);
        $this->assertSame('12.0', $data['lines'][1]['km_low']);
        $row = $this->stored($body['client_id']);
        $pdf = pg_unescape_bytea($row['pdf']);
        $this->assertSame(hash('sha256', $pdf), $row['pdf_sha256']);
        $path = tempnam(sys_get_temp_dir(), 'statement_text_');
        try {
            file_put_contents($path, $pdf);
            $text = mobileAttachmentGs(['-sDEVICE=txtwrite', '-sOutputFile=-', '-f', $path]);
            $text = str_replace('−', '-', $text); // Helvetica's hyphen glyph is extracted as Unicode minus by Ghostscript.
        } finally {
            unlink($path);
        }
        foreach (['Field employee', '2026-10-01', '2026-10-31', 'Århus (kontor)', 'Østerbro', 'Besøg hos kunde',
            'Odense', 'København', 'Levering', '62.0 km', '216.26 DKK', '8.0 km x 3.79', '12.0 km x 2.23'] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
        $pool = self::sql('SELECT * FROM pool_files WHERE id=$1', [$data['attachment_id']])[0];
        $this->assertSame('987611', $pool['account']);
        $this->assertSame('DKK', $pool['currency']);
        $this->assertSame($row['pdf_sha256'], $pool['sha256']);
        $claim = self::sql('SELECT * FROM mobile_expense_claims WHERE pool_file_id=$1', [$pool['id']])[0];
        $this->assertSame('koersel', $claim['kind']);
        $this->assertSame((int)$row['id'], (int)$claim['statement_id']);
        $this->assertSame(self::$employeeId, (int)$claim['ansat_id']);
    }

    public function test_long_trip_text_wraps_across_pages_and_large_statements_reserve_nothing(): void
    {
        $tripIds = [];
        for ($day = 1; $day <= 12; $day++) {
            $tripIds[] = $this->addTrip('2026-10-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT), '10.0', null,
                ['purpose'=>'Visit ' . $day . ' ' . str_repeat('æ', 180)]);
        }
        $body = $this->issueBody();
        $data = $this->assertStatus(201, $this->request('POST', self::ROUTE, $body));
        $this->assertSame($tripIds, array_column($data['lines'], 'trip_id'));
        $path = tempnam(sys_get_temp_dir(), 'statement_pages_');
        try {
            file_put_contents($path, pg_unescape_bytea($this->stored($body['client_id'])['pdf']));
            $this->assertGreaterThan(1, mobileAttachmentInspectPdf($path)['page_count']);
            $text = mobileAttachmentGs(['-sDEVICE=txtwrite', '-sOutputFile=-', '-f', $path]);
            foreach ($tripIds as $index=>$tripId) {
                $this->assertStringContainsString('Tur ' . $tripId . ' |', $text);
                $this->assertStringContainsString('Visit ' . ($index + 1) . ' ', $text);
            }
            $this->assertStringContainsString('454.80 DKK', $text);
        } finally {
            unlink($path);
        }
        // Enough rows to exceed the existing upload's twenty-page inspection cap.
        for ($index = 0; $index < 180; $index++) {
            self::sql("INSERT INTO mobile_mileage_trips(client_id,subject,trip_date,from_text,to_text,km,purpose)
                VALUES ($1,$2,'2026-11-01',$3,$3,1,$3)", [RequestId::resolve(null), self::$owner, str_repeat('x', 200)]);
        }
        $oversized = $this->issueBody('2026-11-01', '2026-11-30');
        $error = $this->assertStatus(422, $this->request('POST', self::ROUTE, $oversized));
        $this->assertSame('statement_too_large', $error['details']['reason']);
        $this->assertSame([], self::sql('SELECT id FROM mobile_mileage_statements WHERE client_id=$1', [$oversized['client_id']]));
        $this->assertSame('180', self::sql("SELECT count(*) AS n FROM mobile_mileage_trips WHERE trip_date='2026-11-01' AND statement_id IS NULL AND deleted_at IS NULL")[0]['n']);
    }

    public function test_endpoint_replay_list_detail_ownership_and_validation(): void
    {
        $this->assertStatus(401, $this->request('GET', self::ROUTE, null, []));
        $this->addTrip('2026-10-01', '12.5');
        $body = $this->issueBody();
        $first = $this->assertStatus(201, $this->request('POST', self::ROUTE, $body));
        $response = $this->request('POST', self::ROUTE, $body);
        $this->assertSame($first, $this->assertStatus(201, $response));
        $this->assertStringContainsString('Idempotent-Replayed: true', $response['headers']);
        $error = $this->assertStatus(409, $this->request('POST', self::ROUTE, array_replace($body, ['period_to'=>'2026-10-30'])));
        $this->assertSame('client_id_reused', $error['details']['reason']);
        $this->assertSame($first, $this->assertStatus(200, $this->request('GET', self::ROUTE . '?id=' . $first['statement_id'])));
        $this->assertStatus(404, $this->request('GET', self::ROUTE . '?id=' . $first['statement_id'], null, self::$otherHeaders));
        $this->assertSame(0, $this->assertStatus(200, $this->request('GET', self::ROUTE, null, self::$otherHeaders))['total']);
        $this->assertArrayNotHasKey('pdf', $first);
        foreach (['PATCH', 'DELETE', 'PUT'] as $method) {
            $this->assertStatus(405, $this->request($method, self::ROUTE, $this->body()));
        }
        $this->assertStatus(422, $this->request('POST', self::ROUTE, $this->body()));
        $empty = $this->assertStatus(422, $this->request('POST', self::ROUTE, $this->issueBody()));
        $this->assertSame('nothing_to_claim', $empty['details']['reason']);
    }

    public function test_wide_and_transliterated_trip_text_fits_printable_page_width(): void
    {
        $this->addTrip('2026-10-01', '42.0', null,
            ['from'=>str_repeat('W', 200), 'to'=>str_repeat('@', 200), 'purpose'=>str_repeat('…', 200)]);
        $body = $this->issueBody();
        $this->assertStatus(201, $this->request('POST', self::ROUTE, $body));
        $ps = mileage_statement_postscript($this->stored($body['client_id']));
        preg_match_all('/^40 [0-9]+ moveto (\(.*\)) show$/m', $ps, $matches);
        $this->assertNotEmpty($matches[1]);
        $fontEnd = strpos($ps, '%%Page:');
        $this->assertNotFalse($fontEnd);
        $program = substr($ps, 0, $fontEnd);
        foreach ($matches[1] as $literal) {
            $program .= $literal . " stringwidth pop =\n";
        }
        $path = tempnam(sys_get_temp_dir(), 'statement_width_');
        try {
            file_put_contents($path, $program . "quit\n");
            $widths = mobileAttachmentGs(['-dNODISPLAY', '-f', $path]);
            foreach (explode("\n", trim($widths)) as $width) {
                $this->assertIsNumeric($width);
                $this->assertLessThanOrEqual(515, (float)$width, 'A printed row must stay inside the right margin');
            }
            file_put_contents($path, pg_unescape_bytea($this->stored($body['client_id'])['pdf']));
            $text = mobileAttachmentGs(['-sDEVICE=txtwrite', '-sOutputFile=-', '-f', $path]);
            $compact = preg_replace('/\s+/u', '', $text);
            $this->assertStringContainsString(str_repeat('W', 200), $compact);
            $this->assertStringContainsString(str_repeat('@', 200), $compact);
            $this->assertStringContainsString(str_repeat('.', 600), $compact);
        } finally {
            unlink($path);
        }
    }

    public function test_statement_identity_and_original_response_survive_replay_cleanup(): void
    {
        $this->addTrip('2026-10-01', '42.0');
        $body = $this->issueBody();
        $first = $this->assertStatus(201, $this->request('POST', self::ROUTE, $body));
        $row = $this->stored($body['client_id']);
        self::sql("UPDATE pool_files SET status='attached',review_state='approved' WHERE id=$1", [$first['attachment_id']]);
        self::sql('DELETE FROM mobile_idempotency WHERE subject=$1 AND client_id=$2', [self::$owner, $body['client_id']]);
        $error = $this->assertStatus(409, $this->request('POST', self::ROUTE, array_replace($body, ['period_to'=>'2026-10-30'])));
        $this->assertSame('client_id_reused', $error['details']['reason']);
        $this->assertSame($first, $this->assertStatus(201, $this->request('POST', self::ROUTE, $body)));
        $this->assertSame($row, $this->stored($body['client_id']));
        $this->assertStatus(409, $this->request('POST', self::ROUTE, $body, self::$otherHeaders));
    }

    public function test_missing_year_rate_rolls_back_all_years_and_trip_assignment(): void
    {
        $trip = $this->addTrip('2026-10-01', '42.0');
        self::sql("DELETE FROM settings WHERE var_grp='mobile_marken' AND var_name='km_rate_2026'");
        $body = $this->issueBody();
        $error = $this->assertStatus(422, $this->request('POST', self::ROUTE, $body));
        $this->assertSame('not_configured', $error['error']);
        $this->assertSame('km_rate_2026', $error['details']['what']);
        $this->assertSame([], self::sql('SELECT id FROM mobile_mileage_statements WHERE client_id=$1', [$body['client_id']]));
        $this->assertNull(self::sql('SELECT statement_id FROM mobile_mileage_trips WHERE id=$1', [$trip])[0]['statement_id']);

        self::setting('km_rate_2026', '3.79');
        $nextYearTrip = $this->addTrip('2027-01-01', '10.0');
        self::sql("DELETE FROM settings WHERE var_grp='mobile_marken' AND var_name='km_rate_2027'");
        $crossYear = $this->issueBody('2026-10-01', '2027-01-31');
        $error = $this->assertStatus(422, $this->request('POST', self::ROUTE, $crossYear));
        $this->assertSame('not_configured', $error['error']);
        $this->assertSame('km_rate_2027', $error['details']['what']);
        $this->assertSame([], self::sql('SELECT id FROM mobile_mileage_statements WHERE client_id=$1', [$crossYear['client_id']]));
        $this->assertSame('0', self::sql("SELECT count(*) AS n FROM mobile_mileage_allocations a
            JOIN mobile_mileage_statements s ON s.id=a.statement_id WHERE s.state <> 'withdrawn'")[0]['n']);
        foreach ([$trip, $nextYearTrip] as $id) {
            $this->assertNull(self::sql('SELECT statement_id FROM mobile_mileage_trips WHERE id=$1', [$id])[0]['statement_id']);
        }
        $this->assertSame([], self::sql('SELECT id FROM mobile_idempotency WHERE subject=$1 AND client_id=$2', [self::$owner, $crossYear['client_id']]));
    }

    public function test_attachment_incompatible_uuids_reserve_no_statement_allowance_or_trips(): void
    {
        $trip = $this->addTrip('2026-10-01', '42.0');
        $before = self::sql('SELECT * FROM mobile_mileage_trips WHERE id=$1', [$trip])[0];
        $allocations = self::sql('SELECT count(*) AS n FROM mobile_mileage_allocations')[0]['n'];
        foreach ([[14, '7'], [14, '1'], [19, '0']] as [$offset, $digit]) {
            $body = $this->issueBody();
            $body['client_id'][$offset] = $digit;
            $error = $this->assertStatus(422, $this->request('POST', self::ROUTE, $body));
            $this->assertSame([], self::sql('SELECT id FROM mobile_mileage_statements WHERE client_id=$1', [$body['client_id']]));
            $this->assertSame($allocations, self::sql('SELECT count(*) AS n FROM mobile_mileage_allocations')[0]['n']);
            $this->assertSame($before, self::sql('SELECT * FROM mobile_mileage_trips WHERE id=$1', [$trip])[0]);
            $this->assertSame([], self::sql('SELECT id FROM mobile_idempotency WHERE subject=$1 AND client_id=$2', [self::$owner, $body['client_id']]));
            $this->assertSame([], self::sql('SELECT id FROM pool_files WHERE client_id=$1', [$body['client_id']]));
            $this->assertSame('validation_failed', $error['error']);
        }
        $valid = $this->issueBody();
        $valid['client_id'] = strtoupper($valid['client_id']);
        $first = $this->assertStatus(201, $this->request('POST', self::ROUTE, $valid));
        $valid['client_id'] = strtolower($valid['client_id']);
        $this->assertSame($first, $this->assertStatus(201, $this->request('POST', self::ROUTE, $valid)));
    }

    public function test_crash_expiry_and_retry_withdraw_unpublished_statement_and_free_stored_allowances(): void
    {
        self::setting('km_threshold_2026', '100');
        self::setting('km_threshold_2027', '100');
        $trips = [$this->addTrip('2026-12-31', '60.0'), $this->addTrip('2027-01-01', '60.0')];
        $body = $this->issueBody('2026-12-01', '2027-01-31');
        $child = $this->child($body, 'before_publish');
        try {
            $this->release($child);
            $this->assertSame('before_publish', $this->readChild($child['pipes'][1]));
        } finally {
            $this->closeChild($child, true);
        }
        $before = $this->stored($body['client_id']);
        $this->assertSame('generating', $before['state']);
        $this->assertNull($before['pool_file_id']);
        $allocations = self::sql('SELECT * FROM mobile_mileage_allocations WHERE statement_id=$1 ORDER BY year', [$before['id']]);
        $this->assertSame(['2026', '2027'], array_column($allocations, 'year'));
        $this->addTrip('2027-02-01', '60.0', self::$otherId);
        $later = $this->service(self::$otherId)->issueStatement($this->issueBody('2027-02-01', '2027-02-28'))['body']['data'];
        $laterRow = self::sql('SELECT * FROM mobile_mileage_statements WHERE id=$1', [$later['statement_id']])[0];
        $this->assertSame('200.00', $later['amount']);
        self::sql("UPDATE pool_files SET uploaded_at=now()-interval '8 days' WHERE client_id=$1", [$body['client_id']]);
        $model = new DurableAttachmentModel(self::$conn, RestApiEnv::testDb(), self::$owner);
        $this->assertSame(1, $model->expireStaged());
        $tombstone = $model->find(['client_id'=>$body['client_id']]);
        $this->assertSame('deleted', $tombstone['status']);
        $this->assertFileExists(self::$root . '/' . $tombstone['storage_path']);
        $this->assertSame($before['pdf_sha256'], hash_file('sha256', self::$root . '/' . $tombstone['storage_path']));
        $this->assertStatus(409, $this->request('POST', self::ROUTE, array_replace($body, ['period_to'=>'2027-01-30'])));
        $this->assertSame($before, $this->stored($body['client_id']));
        // A changed login link must not change which employee/year allocations withdrawal releases.
        $newEmployee = self::sql("INSERT INTO ansatte(navn,lukket) VALUES ('Replacement employee','') RETURNING id")[0]['id'];
        self::sql('UPDATE brugere SET ansat_id=$1 WHERE id=$2', [$newEmployee, self::$userId]);
        try {
            $error = $this->assertStatus(410, $this->request('POST', self::ROUTE, $body));
            $withdrawn = $this->stored($body['client_id']);
            $this->assertSame('withdrawn', $withdrawn['state']);
            $this->assertSame('gone', $error['error']);
            $this->assertNotNull($withdrawn['withdrawn_at']);
            $this->assertSame($before['pdf'], $withdrawn['pdf']);
            foreach ($trips as $trip) {
                $this->assertNull(self::sql('SELECT statement_id FROM mobile_mileage_trips WHERE id=$1', [$trip])[0]['statement_id']);
            }
            $this->assertStatus(410, $this->request('POST', self::ROUTE, $body));
            $this->assertSame($withdrawn, $this->stored($body['client_id']));
            self::sql('DELETE FROM mobile_idempotency WHERE subject=$1 AND client_id=$2', [self::$owner, $body['client_id']]);
            $this->assertStatus(410, $this->request('POST', self::ROUTE, $body));
        } finally {
            self::sql('UPDATE brugere SET ansat_id=$1 WHERE id=$2', [self::$employeeId, self::$userId]);
        }
        $this->assertSame($tombstone, $model->find(['client_id'=>$body['client_id']]));
        $this->assertSame($allocations, self::sql('SELECT * FROM mobile_mileage_allocations WHERE statement_id=$1 ORDER BY year', [$before['id']]));
        $this->assertSame($laterRow, self::sql('SELECT * FROM mobile_mileage_statements WHERE id=$1', [$later['statement_id']])[0]);
        $this->assertSame([], self::sql('SELECT id FROM mobile_expense_claims WHERE statement_id=$1', [$before['id']]));
        $replacement = $this->assertStatus(201, $this->request('POST', self::ROUTE, $this->issueBody('2026-12-01', '2027-01-31')));
        $this->assertSame($trips, array_column($replacement['lines'], 'trip_id'));
        $this->assertSame(['60.0', '60.0'], array_column($replacement['lines'], 'km_high'));
        $this->assertSame('467.40', $replacement['amount']);
    }

    public function test_publication_reconstructs_existing_issuance_replay_pruned_after_thirty_days(): void
    {
        $trip = $this->addTrip('2026-10-01', '42.0');
        $body = $this->issueBody();
        $child = $this->child($body, 'after_allocation');
        try {
            $this->release($child);
            $this->assertSame('after_allocation', $this->readChild($child['pipes'][1]));
        } finally {
            $this->closeChild($child, true);
        }
        $before = $this->stored($body['client_id']);
        $this->assertNull($before['pdf']);
        $this->assertSame([], self::sql('SELECT id FROM pool_files WHERE client_id=$1', [$body['client_id']]));
        self::sql("UPDATE mobile_mileage_statements SET created_at=now()-interval '31 days' WHERE id=$1", [$before['id']]);
        self::sql("UPDATE mobile_idempotency SET created_at=now()-interval '31 days' WHERE subject=$1 AND client_id=$2", [self::$owner, $body['client_id']]);
        $oldReplay = self::sql('SELECT * FROM mobile_idempotency WHERE subject=$1 AND client_id=$2', [self::$owner, $body['client_id']]);
        $this->assertCount(1, $oldReplay);
        $response = $this->request('POST', self::ROUTE, $body);
        $data = $this->assertStatus(201, $response);
        $this->assertStringContainsString('Idempotent-Replayed: true', $response['headers']);
        $after = $this->stored($body['client_id']);
        $this->assertSame('sent', $after['state']);
        $this->assertSame($before['id'], $after['id']);
        $newReplay = self::sql('SELECT * FROM mobile_idempotency WHERE subject=$1 AND client_id=$2', [self::$owner, $body['client_id']])[0];
        $this->assertNotSame($oldReplay[0]['id'], $newReplay['id']);
        $this->assertSame($oldReplay[0]['request_sha256'], $newReplay['request_sha256']);
        $this->assertSame($data, json_decode($newReplay['response_json'], true, 512, JSON_THROW_ON_ERROR)['data']);
        $this->assertSame('1', self::sql('SELECT count(*) AS n FROM pool_files WHERE client_id=$1', [$body['client_id']])[0]['n']);
        $this->assertSame('1', self::sql('SELECT count(*) AS n FROM mobile_expense_claims WHERE statement_id=$1', [$before['id']])[0]['n']);
        $this->assertSame($before['id'], self::sql('SELECT statement_id FROM mobile_mileage_trips WHERE id=$1', [$trip])[0]['statement_id']);
        $this->assertSame($data, $this->assertStatus(201, $this->request('POST', self::ROUTE, $body)));
        $this->assertSame($after, $this->stored($body['client_id']));
    }

    public function test_cross_year_statement_stores_separate_allowances(): void
    {
        $this->addTrip('2026-12-31', '10.0');
        $this->addTrip('2027-01-01', '10.0');
        $body = $this->issueBody('2026-12-01', '2027-01-31');
        $data = $this->assertStatus(201, $this->request('POST', self::ROUTE, $body));
        $this->assertSame('77.90', $data['amount']);
        $this->assertSame(['2026', '2027'], array_column(self::sql('SELECT year FROM mobile_mileage_allocations WHERE statement_id=$1 ORDER BY year', [$data['statement_id']]), 'year'));
    }

    /** @return array{process:resource,pipes:array} Child waiting at a start barrier. */
    private function child(array $body, string $stop = '', ?int $user = null): array
    {
        $id = $user ?? self::$userId;
        $process = proc_open([PHP_BINARY, __DIR__ . '/support/run_mileage_statement.php'],
            [0=>['pipe', 'r'], 1=>['pipe', 'w'], 2=>['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        fwrite($pipes[0], json_encode(['db'=>RestApiEnv::testDb(), 'subject'=>Subject::build('account', $id),
            'user_id'=>$id, 'body'=>$body, 'stop'=>$stop], JSON_THROW_ON_ERROR) . "\n");
        fflush($pipes[0]);
        $this->assertSame('ready', $this->readChild($pipes[1]));
        return ['process'=>$process, 'pipes'=>$pipes];
    }

    private function readChild($pipe): string
    {
        $read = [$pipe];
        $write = $except = [];
        if (stream_select($read, $write, $except, 25) !== 1) {
            throw new RuntimeException('Statement child timed out');
        }
        return trim((string)fgets($pipe));
    }

    private function release(array $child): void
    {
        fwrite($child['pipes'][0], "go\n");
        fflush($child['pipes'][0]);
    }

    private function closeChild(array $child, bool $kill = false): void
    {
        if ($kill) {
            proc_terminate($child['process'], 9);
        }
        fclose($child['pipes'][0]);
        fclose($child['pipes'][1]);
        $stderr = stream_get_contents($child['pipes'][2]);
        fclose($child['pipes'][2]);
        $exit = proc_close($child['process']);
        $this->assertSame('', $stderr);
        if (!$kill) {
            $this->assertSame(0, $exit);
        }
    }

    public function test_sigkill_after_each_committed_stage_resumes_one_statement_bilag_and_digest(): void
    {
        foreach (['after_allocation', 'after_pdf', 'after_upload'] as $phase) {
            $trip = $this->addTrip('2026-10-01', '42.0');
            $body = $this->issueBody();
            $child = $this->child($body, $phase);
            try {
                $this->release($child);
                $this->assertSame($phase, $this->readChild($child['pipes'][1]));
            } finally {
                $this->closeChild($child, true);
            }
            $before = $this->stored($body['client_id']);
            if ($before['state'] === 'generating') {
                $list = $this->assertStatus(200, $this->request('GET', self::ROUTE));
                $this->assertNotContains((int)$before['id'], array_column($list['items'], 'statement_id'));
                $this->assertStatus(404, $this->request('GET', self::ROUTE . '?id=' . $before['id']));
            }
            // Changing settings after PDF storage cannot change the persisted bytes or publication metadata.
            if ($phase === 'after_pdf') {
                self::setting('claims_mileage_account', '987610');
                self::setting('km_rate_2026', '9.99');
                self::sql('DELETE FROM mobile_idempotency WHERE subject=$1 AND client_id=$2', [self::$owner, $body['client_id']]);
            }
            $retry = $this->request('POST', self::ROUTE, $body);
            $data = $this->assertStatus(201, $retry);
            $this->assertStringContainsString('Idempotent-Replayed: true', $retry['headers']);
            $after = $this->stored($body['client_id']);
            $this->assertSame($before['id'], $after['id']);
            if ($before['pdf'] !== null) {
                $this->assertSame($before['pdf'], $after['pdf']);
                $this->assertSame($before['pdf_sha256'], $after['pdf_sha256']);
            }
            $this->assertSame('sent', $after['state']);
            $this->assertSame('1', self::sql('SELECT count(*) AS n FROM pool_files WHERE client_id=$1', [$body['client_id']])[0]['n']);
            $this->assertSame('1', self::sql('SELECT count(*) AS n FROM mobile_expense_claims WHERE statement_id=$1', [$after['id']])[0]['n']);
            $this->assertSame($after['id'], self::sql('SELECT statement_id FROM mobile_mileage_trips WHERE id=$1', [$trip])[0]['statement_id']);
            $this->assertSame($data, $this->assertStatus(201, $this->request('POST', self::ROUTE, $body)));
            self::setting('claims_mileage_account', '987611');
            self::setting('km_rate_2026', '3.79');
        }
    }

    public function test_sigkill_after_rename_before_publish_leaves_no_reviewable_bare_bilag(): void
    {
        $this->addTrip('2026-10-01', '42.0');
        $body = $this->issueBody();
        $child = $this->child($body, 'before_publish');
        try {
            $this->release($child);
            $this->assertSame('before_publish', $this->readChild($child['pipes'][1]));
        } finally {
            $this->closeChild($child, true);
        }
        $pool = self::sql('SELECT * FROM pool_files WHERE client_id=$1 FOR UPDATE', [$body['client_id']])[0];
        $id = (int)$pool['id'];
        $this->assertSame('staged', $pool['status']);
        $this->assertFileExists(self::$root . '/pulje/' . $body['client_id'] . '.pdf');
        $this->assertSame([], self::sql('SELECT id FROM mobile_expense_claims WHERE pool_file_id=$1', [$id]));
        $queue = $this->assertStatus(200, $this->request('GET', 'attachment/review.php', null, self::$reviewerHeaders));
        $this->assertNotContains($id, array_column($queue['items'], 'id'));
        $this->assertStatus(404, $this->request('GET', 'attachment/review.php?id=' . $id, null, self::$reviewerHeaders));
        $etag = '"' . pool_review_version($pool) . '"';
        $this->assertStatus(404, $this->request('POST', 'attachment/approve.php', $this->body(['id'=>$id, 'kontonr'=>987611]),
            array_merge(self::$reviewerHeaders, ['If-Match: ' . $etag])));
        $this->assertStatus(404, $this->request('PATCH', 'attachment/metadata.php?id=' . $id, $this->body(['amount'=>'99.00']),
            array_merge(self::$headers, ['If-Match: ' . $etag])));
        $this->assertStatus(409, $this->request('DELETE', 'attachment/?id=' . $id));
        // A plain upload cannot bypass the statement publisher after a crash.
        $row = $this->stored($body['client_id']);
        $pdf = pg_unescape_bytea($row['pdf']);
        $this->assertStatus(409, $this->request('POST', 'attachment/', ['id'=>$body['client_id'], 'sha256'=>$row['pdf_sha256'], 'image_base64'=>base64_encode($pdf)]));
        $data = $this->assertStatus(201, $this->request('POST', self::ROUTE, $body));
        $this->assertSame($id, $data['attachment_id']);
        $this->assertSame($row['pdf_sha256'], $this->stored($body['client_id'])['pdf_sha256']);
        $this->assertSame('1', self::sql('SELECT count(*) AS n FROM mobile_expense_claims WHERE pool_file_id=$1', [$id])[0]['n']);
    }

    /** @return list<array> Real simultaneous issuers released while the employee allowance is held. */
    private function race(array $bodies, array $users): array
    {
        $children = [];
        self::sql('BEGIN');
        mileage_lock($this->service()->allowanceScope(), 2026, self::$conn);
        try {
            foreach ($bodies as $index=>$body) {
                $children[] = $this->child($body, '', $users[$index]);
            }
            foreach ($children as $child) {
                $this->release($child);
            }
            $deadline = microtime(true) + 10;
            do {
                $waiting = (int)self::sql("SELECT count(*) AS n FROM pg_stat_activity WHERE datname=current_database() AND wait_event='advisory'")[0]['n'];
                if ($waiting >= 2) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            $this->assertGreaterThanOrEqual(2, $waiting, 'Both processes must actually contend before release');
            self::sql('COMMIT');
            $results = [];
            foreach ($children as $child) {
                $results[] = json_decode($this->readChild($child['pipes'][1]), true, 512, JSON_THROW_ON_ERROR);
            }
            return $results;
        } finally {
            if (pg_transaction_status(self::$conn) !== PGSQL_TRANSACTION_IDLE) {
                self::sql('ROLLBACK');
            }
            foreach ($children as $child) {
                $this->closeChild($child);
            }
        }
    }

    public function test_overlapping_concurrent_statements_assign_trips_exactly_once(): void
    {
        $trip = $this->addTrip('2026-10-01', '42.0');
        $results = $this->race([$this->issueBody(), $this->issueBody()], [self::$userId, self::$userId]);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([201, 422], $statuses);
        $failed = array_values(array_filter($results, static fn(array $result): bool => $result['status'] === 422))[0];
        $this->assertSame('nothing_to_claim', $failed['details']['reason']);
        $this->assertNotNull(self::sql('SELECT statement_id FROM mobile_mileage_trips WHERE id=$1', [$trip])[0]['statement_id']);
    }

    public function test_same_client_concurrent_issue_returns_one_response_and_one_pdf(): void
    {
        $this->addTrip('2026-10-01', '42.0');
        $body = $this->issueBody();
        $results = $this->race([$body, $body], [self::$userId, self::$userId]);
        $this->assertSame([201, 201], array_column($results, 'status'));
        $this->assertSame($results[0]['body'], $results[1]['body']);
        $headers = array_column($results, 'headers');
        $this->assertContains(['Idempotent-Replayed'=>'true'], $headers);
        $this->assertContains([], $headers);
        $this->assertSame('1', self::sql('SELECT count(*) AS n FROM mobile_mileage_statements WHERE client_id=$1', [$body['client_id']])[0]['n']);
        $this->assertSame('1', self::sql('SELECT count(*) AS n FROM pool_files WHERE client_id=$1', [$body['client_id']])[0]['n']);
    }

    public function test_disjoint_concurrent_statements_of_two_logins_share_employee_threshold(): void
    {
        self::setting('km_threshold_2026', '100');
        $this->addTrip('2026-01-01', '60.0');
        $this->addTrip('2026-02-01', '60.0', self::$otherId);
        $results = $this->race([$this->issueBody('2026-01-01', '2026-01-31'), $this->issueBody('2026-02-01', '2026-02-28')],
            [self::$userId, self::$otherId]);
        $this->assertSame([201, 201], array_column($results, 'status'));
        $allocation = self::sql("SELECT sum(a.km_high)::text AS high,sum(a.km_low)::text AS low FROM mobile_mileage_allocations a
            JOIN mobile_mileage_statements s ON s.id=a.statement_id WHERE s.state <> 'withdrawn'")[0];
        $this->assertSame('100.0', $allocation['high']);
        $this->assertSame('20.0', $allocation['low']);
        $this->assertEquals(423.60, array_sum(array_map(static fn(array $result): float => (float)$result['body']['data']['amount'], $results)));
    }

    public function test_reverse_month_issue_and_withdrawal_preserve_later_amounts_and_free_allowance(): void
    {
        self::setting('km_threshold_2026', '100');
        $januaryTrip = $this->addTrip('2026-01-01', '60.0');
        $februaryTrip = $this->addTrip('2026-02-01', '60.0', self::$otherId);
        $february = $this->service(self::$otherId)->issueStatement($this->issueBody('2026-02-01', '2026-02-28'))['body']['data'];
        $january = $this->service()->issueStatement($this->issueBody('2026-01-01', '2026-01-31'))['body']['data'];
        $this->assertSame('227.40', $february['amount']);
        $this->assertSame('196.20', $january['amount']);
        $later = self::sql('SELECT * FROM mobile_mileage_statements WHERE id=$1', [$january['statement_id']])[0];
        self::sql('BEGIN');
        try {
            // B7 owns wiring this helper into T1 deletion; exercise the required transaction ordering here.
            self::sql("UPDATE pool_files SET status='deleted',deleted_at=now() WHERE id=$1", [$february['attachment_id']]);
            mileage_statement_withdraw(self::$conn, $february['statement_id']);
            mileage_statement_withdraw(self::$conn, $february['statement_id']);
            self::sql('COMMIT');
        } catch (Throwable $error) {
            self::sql('ROLLBACK');
            throw $error;
        }
        $this->assertSame($later, self::sql('SELECT * FROM mobile_mileage_statements WHERE id=$1', [$january['statement_id']])[0]);
        $this->assertNull(self::sql('SELECT statement_id FROM mobile_mileage_trips WHERE id=$1', [$februaryTrip])[0]['statement_id']);
        $this->assertSame((string)$january['statement_id'], self::sql('SELECT statement_id FROM mobile_mileage_trips WHERE id=$1', [$januaryTrip])[0]['statement_id']);
        $marchTrip = $this->addTrip('2026-03-01', '60.0');
        $march = $this->service()->issueStatement($this->issueBody('2026-03-01', '2026-03-31'))['body']['data'];
        $this->assertSame('227.40', $march['amount']);
        $this->assertSame($marchTrip, $march['lines'][0]['trip_id']);
    }
}
