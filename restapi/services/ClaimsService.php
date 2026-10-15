<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/ClaimsService.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Add resumable mileage statements and atomic claim publication.
// 20261001 CDX/LH Validate attachment UUIDs, release expired reservations and restore pruned publication replays.

require_once __DIR__ . '/../core/EmployeeContext.php';
require_once __DIR__ . '/../core/Subject.php';
require_once __DIR__ . '/../core/ETag.php';
require_once __DIR__ . '/../core/Paging.php';
require_once __DIR__ . '/../models/attachment/DurableAttachmentModel.php';
require_once __DIR__ . '/../../includes/employeeClaims/mileageRates.php';
require_once __DIR__ . '/../../includes/employeeClaims/mileageStatement.php';
require_once __DIR__ . '/../../includes/docsIncludes/poolDateNormalizer.php';

/** Own claims/trips; annual allowance identity is deliberately separate from row ownership. */
final class ClaimsService
{
    private MobileSchema $store;
    public readonly EmployeeContext $employee;
    public function __construct(private $connection, private string $db, private string $subject,
        string $principal, int $userId)
    {
        $identity = Subject::parse($subject);
        if ($identity['principal'] !== $principal || $identity['user_id'] !== $userId) {
            throw new InvalidArgumentException('Inconsistent claims actor');
        }
        $this->store = new MobileSchema($connection);
        $this->employee = new EmployeeContext($connection,$principal,$userId);
    }

    /** @return string Employee/calendar-year allowance key; access control always uses subject. */
    public function allowanceScope(): string
    {
        return $this->employee->linked ? 'ansat:'.$this->employee->ansatId : $this->subject;
    }

    /** @return array<string,string> Current tenant settings, last row wins as in the web section. */
    public function settings(): array
    {
        $values = [];
        foreach ($this->store->rows("SELECT var_name,var_value FROM settings WHERE var_grp='mobile_marken' AND user_id=0 ORDER BY id") as $row) {
            $values[$row['var_name']] = (string)$row['var_value'];
        }
        return $values;
    }

    /** Installed claims are offered only with a usable payable account. @return bool */
    public function capability(): bool
    {
        $account = $this->settings()['claims_payable_account'] ?? '';
        if (!preg_match('/\A[1-9][0-9]{0,8}\z/',$account)) {
            return false;
        }
        return $this->store->rows("SELECT id FROM kontoplan WHERE kontonr=".(int)$account.
            " AND kontotype='S' AND COALESCE(trim(lukket),'')='' LIMIT 1") !== [];
    }

    /** Enforce configuration and account employee identity before accepting a claim upload. */
    public function requireAvailable(bool $employeeRequired = false): void
    {
        if ($employeeRequired && !$this->employee->linked) {
            throw new MobileT2Error('permission_denied',403,['reason'=>'no_employee_link']);
        }
        if (!$this->capability()) {
            throw new MobileT2Error('not_configured',422,['what'=>'claims_payable_account']);
        }
    }

    /** @return array{year:int,rate:?string,rate_low:?string,threshold_km:int|float,configured:bool} */
    public function rate(int $year): array
    {
        $this->requireAvailable();
        return mileage_rate($year,$this->connection);
    }

    /** Insert only inside the attachment publisher's final transaction; retries validate the existing claim. */
    public function publishExpense($connection, array $row): void
    {
        if ($connection !== $this->connection || pg_transaction_status($connection) !== PGSQL_TRANSACTION_INTRANS) {
            throw new LogicException('Claim publication requires the upload transaction');
        }
        $this->requireAvailable(true);
        if ($row['uploaded_by'] !== $this->subject || $row['status'] === 'staged') {
            throw new MobileT2Error('conflict',409,['reason'=>'attachment_processed']);
        }
        $id = (int)$row['id'];
        $claims = $this->store->rows("SELECT * FROM mobile_expense_claims WHERE pool_file_id=$id");
        if ($claims) {
            $claim = $claims[0];
            if ($claim['subject'] !== $this->subject || $claim['kind'] !== 'udlaeg'
                || (int)$claim['ansat_id'] !== $this->employee->ansatId || $claim['client_id'] !== $row['client_id']) {
                throw new MobileT2Error('conflict',409,['reason'=>'client_id_reused']);
            }
            return;
        }
        if ($row['status'] !== 'ubehandlet' || $row['review_state'] !== 'pending') {
            throw new MobileT2Error('conflict',409,['reason'=>'attachment_processed']);
        }
        $this->insertClaim($row['client_id'],$id);
    }

    private function insertClaim(string $clientId, int $poolId): void
    {
        $this->store->execute('INSERT INTO mobile_expense_claims(client_id,pool_file_id,kind,subject,ansat_id) VALUES ('
            .$this->store->literal($clientId).','.(int)$poolId.",'udlaeg',".$this->store->literal($this->subject).','.($this->employee->linked ? (string)(int)$this->employee->ansatId : 'NULL').')');
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    public function registerExpense(array $body): array
    {
        $this->requireAvailable();
        $id = self::id($body['attachment_id'] ?? null);
        return $this->mutate('expense.register',$id,$body,function () use ($id,$body): array {
            $rows = $this->store->rows("SELECT * FROM pool_files WHERE id=$id AND uploaded_by=".$this->store->literal($this->subject).' FOR UPDATE');
            if (!$rows) { throw new MobileT2Error('not_found',404); }
            $row = $rows[0];
            if ($row['status'] !== 'ubehandlet' || $row['review_state'] !== 'pending') {
                throw new MobileT2Error('conflict',409,['reason'=>'attachment_processed']);
            }
            if ($this->store->rows("SELECT id FROM pool_file_moves WHERE pool_file_id=$id AND state IN ('reserved','committed')")) {
                throw new MobileT2Error('conflict',409,['reason'=>'attachment_moving']);
            }
            if ($this->store->rows("SELECT id FROM mobile_expense_claims WHERE pool_file_id=$id OR client_id=".$this->store->literal(Idempotency::clientId($body)))) {
                throw new MobileT2Error('conflict',409,['reason'=>'already_claimed']);
            }
            $this->insertClaim(Idempotency::clientId($body),$id);
            return $this->expense($this->store->rows("SELECT c.id AS expense_id,a.navn AS claim_employee_name,p.* FROM mobile_expense_claims c JOIN pool_files p ON p.id=c.pool_file_id LEFT JOIN ansatte a ON a.id=c.ansat_id WHERE p.id=$id")[0]);
        },201);
    }

    /** @return array{items:list<array>,total:int,limit:int,offset:int} */
    public function expenses(array $query): array
    {
        $this->requireAvailable();
        $page = new Paging($query);
        $where = 'c.subject='.$this->store->literal($this->subject)." AND c.kind='udlaeg' AND p.status <> 'staged'";
        if (isset($query['period_from']) || isset($query['period_to'])) {
            $this->period($query,'p.file_date'); // Validate bounds; legacy bilag dates are varchar, often day-first.
            $dates = [];
            foreach ($this->store->rows("SELECT DISTINCT p.file_date FROM mobile_expense_claims c JOIN pool_files p ON p.id=c.pool_file_id WHERE $where") as $row) {
                $date = self::expenseDate($row['file_date']);
                if ($date !== null && $date >= ($query['period_from'] ?? '0001-01-01') && $date <= ($query['period_to'] ?? '9999-12-31')) {
                    $dates[] = $this->store->literal($row['file_date']);
                }
            }
            if (!$dates) { return $page->response([],0); }
            $where .= ' AND p.file_date IN ('.implode(',',$dates).')';
        }
        $count = $this->store->rows("SELECT count(*) AS total FROM mobile_expense_claims c JOIN pool_files p ON p.id=c.pool_file_id WHERE $where")[0];
        $rows = $this->store->rows("SELECT c.id AS expense_id,a.navn AS claim_employee_name,p.* FROM mobile_expense_claims c JOIN pool_files p ON p.id=c.pool_file_id LEFT JOIN ansatte a ON a.id=c.ansat_id WHERE $where ORDER BY c.created_at DESC,c.id DESC ".$page->sql());
        return $page->response(array_map(fn(array $row): array => $this->expense($row),$rows),(int)$count['total']);
    }

    /** @return array{expense_id:int,attachment_id:int,vendor:string,date:?string,amount:?string,currency:string,display_status:string,employee_name:string} */
    private function expense(array $row): array
    {
        $model = new DurableAttachmentModel($this->connection,$this->db,$this->subject);
        $attachment = $model->toArray($row);
        $normalized = normalizePoolAmount($row['amount']);
        return ['expense_id'=>(int)$row['expense_id'],'attachment_id'=>(int)$row['id'],'vendor'=>(string)$row['subject'],
            'date'=>self::expenseDate($row['file_date']),'amount'=>$normalized === null ? null : number_format((float)$normalized,2,'.',''),
            'currency'=>normalizePoolCurrency($row['currency']) ?: 'DKK','display_status'=>$attachment['display_status'],
            'employee_name'=>(string)($row['claim_employee_name'] ?? '')];
    }

    /** Normalize legacy receipt dates for the app; unrecognised or missing dates stay unknown. @return string|null */
    private static function expenseDate($value): ?string
    {
        $date = normalizeDateFormat((string)$value);
        try { return self::date($date); }
        catch (MobileT2Error $error) { return null; }
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    public function createTrip(array $body): array
    {
        $this->requireAvailable();
        return $this->mutate('mileage.trip.create',null,$body,function () use ($body): array {
            $fields = $this->tripFields($body);
            $client = $this->store->literal(Idempotency::clientId($body));
            if ($this->store->rows("SELECT id FROM mobile_mileage_trips WHERE client_id=$client")) {
                throw new MobileT2Error('conflict',409,['reason'=>'client_id_reused']);
            }
            $values = array_map([$this->store,'literal'],$fields);
            $row = $this->store->rows('INSERT INTO mobile_mileage_trips(client_id,subject,ansat_id,trip_date,from_text,to_text,km,purpose)
                VALUES ('.$client.','.$this->store->literal($this->subject).','.($this->employee->linked ? (string)(int)$this->employee->ansatId : 'NULL').",$values[0],$values[1],$values[2],$values[3],$values[4]) RETURNING *")[0];
            return $this->trip($row);
        },201);
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    public function updateTrip(int $id, array $body, ?string $ifMatch, bool $delete = false): array
    {
        $this->requireAvailable();
        return $this->mutate($delete ? 'mileage.trip.delete' : 'mileage.trip.update',$id,$body,function () use ($id,$body,$ifMatch,$delete): array {
            $row = $this->ownTrip($id,true);
            if ($row['statement_id'] !== null) {
                throw new MobileT2Error('conflict',409,['reason'=>'trip_claimed']);
            }
            ETag::requireMatch($ifMatch,$this->tripEtag($row));
            if ($delete) {
                $this->store->execute("UPDATE mobile_mileage_trips SET deleted_at=now(),updated_at=now(),version=version+1 WHERE id=$id");
                return ['id'=>$id,'deleted'=>true];
            }
            $input = ['date'=>$row['trip_date'],'from'=>$row['from_text'],'to'=>$row['to_text'],'km'=>$row['km'],'purpose'=>$row['purpose']];
            $fields = $this->tripFields(array_replace($input,$body));
            $v = array_map([$this->store,'literal'],$fields);
            $row = $this->store->rows("UPDATE mobile_mileage_trips SET trip_date=$v[0],from_text=$v[1],to_text=$v[2],km=$v[3],purpose=$v[4],updated_at=now(),version=version+1 WHERE id=$id RETURNING *")[0];
            return $this->trip($row);
        });
    }

    /** @return array{items:list<array>,total:int,limit:int,offset:int}|array */
    public function trips(array $query): array
    {
        $this->requireAvailable();
        if (isset($query['id'])) { return $this->trip($this->ownTrip(self::id($query['id']))); }
        $page = new Paging($query);
        $where = 'subject='.$this->store->literal($this->subject).' AND deleted_at IS NULL'.$this->period($query,'trip_date');
        $count = $this->store->rows("SELECT count(*) AS total FROM mobile_mileage_trips WHERE $where")[0];
        $rows = $this->store->rows("SELECT * FROM mobile_mileage_trips WHERE $where ORDER BY trip_date,id ".$page->sql());
        return $page->response(array_map(fn(array $row): array => $this->trip($row),$rows),(int)$count['total']);
    }

    /** @return array<string,mixed> */
    private function ownTrip(int $id, bool $lock = false): array
    {
        $rows = $this->store->rows('SELECT * FROM mobile_mileage_trips WHERE id='.(int)$id.' AND subject='
            .$this->store->literal($this->subject).' AND deleted_at IS NULL'.($lock ? ' FOR UPDATE' : ''));
        if (!$rows) { throw new MobileT2Error('not_found',404); }
        return $rows[0];
    }

    /** @return string Strong validator includes statement assignment and version. */
    private function tripEtag(array $row): string
    {
        return '"'.hash('sha256',Idempotency::canonicalJson([$row['id'],$row['version'],$row['statement_id'],$row['deleted_at']])).'"';
    }

    /** @return array{id:int,date:string,from:string,to:string,km:string,purpose:string,rate_preview:?string,amount_preview:?string,statement:?array,etag:string} */
    private function trip(array $row): array
    {
        $rate = $amount = null;
        $statement = null;
        if ($row['statement_id'] !== null) {
            $statementRow = $this->store->rows('SELECT id,state FROM mobile_mileage_statements WHERE id='.(int)$row['statement_id'])[0];
            $statement = ['id'=>(int)$statementRow['id'],'state'=>$statementRow['state']];
        } else {
            // All earlier open trips in this login share the remaining allowance in preview order.
            $year = (int)substr($row['trip_date'],0,4);
            $open = $this->store->rows('SELECT id,trip_date,km FROM mobile_mileage_trips WHERE subject='
                .$this->store->literal($this->subject)." AND deleted_at IS NULL AND statement_id IS NULL AND EXTRACT(YEAR FROM trip_date)=$year ORDER BY trip_date,id");
            if (mileage_rate($year,$this->connection)['configured']) {
                $allocation = mileage_allocate($this->allowanceScope(),$year,$open,$this->connection,true);
                foreach ($allocation['lines'] as $line) {
                    if ($line['trip_id'] === (int)$row['id']) {
                        $amount = $line['amount'];
                        $km = mileage_units($line['km'],1,20000);
                        $weightedRate = mileage_units($line['km_high'],1,20000)*mileage_units($line['rate'],2,100000)
                            + mileage_units($line['km_low'],1,20000)*mileage_units($line['rate_low'],2,100000);
                        $rate = mileage_decimal(intdiv($weightedRate + intdiv($km,2),$km),2);
                    }
                }
            }
        }
        return ['id'=>(int)$row['id'],'date'=>$row['trip_date'],'from'=>$row['from_text'],'to'=>$row['to_text'],'km'=>$row['km'],
            'purpose'=>$row['purpose'],'rate_preview'=>$rate,'amount_preview'=>$amount,'statement'=>$statement,'etag'=>$this->tripEtag($row)];
    }

    /** @return list<string> Validated storage fields. */
    private function tripFields(array $body): array
    {
        $date = self::date($body['date'] ?? null);
        $fields = [$date];
        foreach (['from','to'] as $field) { $fields[] = self::text($body[$field] ?? null); }
        $km = mileage_units($body['km'] ?? null,1,20000);
        if ($km < 1) { throw new MobileT2Error('validation_failed',422,['reason'=>'mileage_invalid']); }
        $fields[] = mileage_decimal($km,1);
        $fields[] = self::text($body['purpose'] ?? null);
        return $fields;
    }

    /** @return string Nonempty bounded trip text. */
    private static function text($value): string
    {
        if (!is_string($value) || !mb_check_encoding($value,'UTF-8') || trim($value) === '' || mb_strlen($value) > 200 || preg_match('/[\x00-\x1f\x7f]/',$value)) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'mileage_invalid']);
        }
        return trim($value);
    }

    /** @return string Valid calendar date. */
    public static function date($value): string
    {
        if (!is_string($value) || !preg_match('/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/',$value,$p)
            || !checkdate((int)$p[2],(int)$p[3],(int)$p[1])) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'date_missing']);
        }
        return $value;
    }

    /** @return int Positive server id. */
    public static function id($value): int
    {
        if ((!is_int($value) && (!is_string($value) || !preg_match('/\A[1-9][0-9]{0,9}\z/',$value)))
            || (int)$value < 1 || (int)$value > 2147483647) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'id_invalid']);
        }
        return (int)$value;
    }

    /** @return array{items:list<array>,total:int,limit:int,offset:int}|array Own statements; generated bytes stay private. */
    public function statements(array $query): array
    {
        $this->requireAvailable();
        if (isset($query['id'])) {
            $row = $this->ownStatement(self::id($query['id']));
            if ($row['state'] === 'generating') {
                throw new MobileT2Error('not_found', 404);
            }
            return $this->statementData($row);
        }
        $page = new Paging($query);
        $where = 'subject=' . $this->store->literal($this->subject) . " AND state <> 'generating'" . $this->period($query, 'period_to');
        $total = (int)$this->store->rows("SELECT count(*) AS n FROM mobile_mileage_statements WHERE $where")[0]['n'];
        $rows = $this->store->rows("SELECT id,km_total,amount,pool_file_id,state,lines_json FROM mobile_mileage_statements
            WHERE $where ORDER BY created_at DESC,id DESC " . $page->sql());
        return $page->response(array_map(fn(array $row): array => $this->statementData($row), $rows), $total);
    }

    /**
     * Reserve allowance, persist PDF, then publish the bilag with its claim. A retry resumes committed work.
     * The optional observer is for CLI crash/concurrency tests; the HTTP endpoint never supplies one.
     * @param callable(string,array):void|null $checkpoint
     * @return array{status:int,body:array,headers:array<string,string>}
     */
    public function issueStatement(array $body, ?callable $checkpoint = null): array
    {
        $this->requireAvailable();
        if ($this->employee->principal !== 'account') {
            throw new MobileT2Error('permission_denied', 403, ['reason'=>'no_employee_link']);
        }
        if (pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Statement issuance owns its transactions');
        }
        $clientId = DurableAttachmentModel::uuid(Idempotency::clientId($body));
        $from = self::date(ifset($body, 'period_from'));
        $to = self::date(ifset($body, 'period_to'));
        if ($from > $to) {
            throw new MobileT2Error('validation_failed', 422, ['reason'=>'period_invalid']);
        }
        $lock = $this->store->literal('saldi:mileage:issue:' . $clientId);
        $this->store->execute("SELECT pg_advisory_lock(hashtextextended($lock,0))");
        try {
            $this->store->execute('BEGIN');
            $replay = new Idempotency($this->connection);
            $previous = $replay->replay($this->subject, 'mileage.statement.issue', null, $body);
            // The shared replay store is pruned after thirty days; statement identity survives that cleanup.
            $existing = $this->store->rows('SELECT * FROM mobile_mileage_statements WHERE client_id=' . $this->store->literal($clientId));
            if ($previous === null && $existing) {
                $previous = $this->restoreStatementReplay($replay, $existing[0], $body);
            }
            if ($previous === null) {
                if ($this->store->rows('SELECT id FROM mobile_mileage_statements WHERE client_id=' . $this->store->literal($clientId))) {
                    throw new MobileT2Error('conflict', 409, ['reason'=>'client_id_reused']);
                }
                if (!mobileAttachmentRendererAvailable()) {
                    throw new MobileT2Error('internal', 503);
                }
                $scope = $this->allowanceScope();
                for ($year = (int)substr($from, 0, 4); $year <= (int)substr($to, 0, 4); $year++) {
                    mileage_lock($scope, $year, $this->connection);
                }
                $trips = $this->store->rows('SELECT * FROM mobile_mileage_trips WHERE subject=' . $this->store->literal($this->subject)
                    . ' AND deleted_at IS NULL AND statement_id IS NULL AND trip_date BETWEEN ' . $this->store->literal($from)
                    . ' AND ' . $this->store->literal($to) . ' ORDER BY trip_date,id FOR UPDATE');
                if (!$trips) {
                    throw new MobileT2Error('validation_failed', 422, ['reason'=>'nothing_to_claim']);
                }
                $byYear = [];
                $tripById = [];
                foreach ($trips as $trip) {
                    $byYear[(int)substr($trip['trip_date'], 0, 4)][] = $trip;
                    $tripById[(int)$trip['id']] = $trip;
                }
                $allocations = $rates = $lines = [];
                $amount = $km = 0;
                foreach ($byYear as $year=>$yearTrips) {
                    $allocation = mileage_allocate($scope, $year, $yearTrips, $this->connection);
                    $rates[$year] = mileage_rate($year, $this->connection);
                    $allocations[] = $allocation;
                    foreach ($allocation['lines'] as $line) {
                        $trip = $tripById[$line['trip_id']];
                        $lines[] = $line + ['from'=>$trip['from_text'], 'to'=>$trip['to_text'], 'purpose'=>$trip['purpose']];
                        $km += mileage_units($line['km'], 1, 20000);
                        $amount += mileage_units($line['amount'], 2, PHP_INT_MAX);
                    }
                }
                $name = trim((string)ifset($this->employee->employee, 'navn', ''));
                if ($name === '') {
                    $name = (string)$this->store->rows('SELECT brugernavn FROM brugere WHERE id=' . (int)$this->employee->userId)[0]['brugernavn'];
                }
                $settings = $this->settings();
                $account = ifset($settings, 'claims_mileage_account', '');
                if ($account !== '' && !preg_match('/\A[1-9][0-9]{0,8}\z/', $account)) {
                    throw new MobileT2Error('not_configured', 422, ['what'=>'claims_mileage_account']);
                }
                $snapshot = ['request_sha256'=>Idempotency::requestHash('mileage.statement.issue', null, $body),
                    'years'=>$rates, 'scope'=>$scope, 'employee_name'=>$name,
                    'mileage_account'=>$account, 'description'=>'Kørselsgodtgørelse ' . $from . ' - ' . $to . ' — ' . $name];
                $fields = ['client_id'=>$clientId, 'subject'=>$this->subject, 'period_from'=>$from, 'period_to'=>$to,
                    'km_total'=>mileage_decimal($km, 1), 'amount'=>mileage_decimal($amount, 2),
                    'rates_json'=>json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'lines_json'=>json_encode($lines, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'state'=>'generating'];
                // Reject an oversized statement before reserving any trips or allowance.
                mileage_statement_postscript($fields);
                $columns = implode(',', array_keys($fields));
                $values = implode(',', array_map([$this->store, 'literal'], array_values($fields)));
                $ansat = $this->employee->linked ? (string)(int)$this->employee->ansatId : 'NULL';
                $row = $this->store->rows("INSERT INTO mobile_mileage_statements($columns,ansat_id) VALUES ($values,$ansat) RETURNING *")[0];
                $id = (int)$row['id'];
                foreach ($allocations as $allocation) {
                    $high = $this->store->literal($allocation['km_high']);
                    $low = $this->store->literal($allocation['km_low']);
                    $year = (int)$allocation['year'];
                    $this->store->execute("INSERT INTO mobile_mileage_allocations(statement_id,scope,year,km_high,km_low) VALUES ($id,"
                        . $this->store->literal($scope) . ",$year,$high,$low)");
                }
                $ids = implode(',', array_map(static fn(array $trip): int => (int)$trip['id'], $trips));
                $this->store->execute("UPDATE mobile_mileage_trips SET statement_id=$id,version=version+1,updated_at=now() WHERE id IN ($ids)");
                $replay->store($this->subject, 'mileage.statement.issue', null, $body, 201,
                    ['success'=>true, 'message'=>'', 'data'=>['statement_id'=>$id, 'state'=>'generating']], 'mileage_statement', $id);
            } else {
                $id = (int)$previous['body']['data']['statement_id'];
                $row = $this->ownStatement($id);
                if ($row['state'] === 'withdrawn') {
                    throw new MobileT2Error('gone', 410);
                }
                if ($row['state'] === 'sent') {
                    $this->store->execute('COMMIT');
                    return $previous;
                }
            }
            $this->store->execute('COMMIT');
            if ($checkpoint !== null) {
                $checkpoint('after_allocation', $row);
            }
            if ($row['pdf'] === null) {
                $pdf = mileage_statement_pdf($row);
                $this->store->execute('BEGIN');
                $this->store->execute("UPDATE mobile_mileage_statements SET pdf=decode(" . $this->store->literal(bin2hex($pdf))
                    . ",'hex'),pdf_sha256=" . $this->store->literal(hash('sha256', $pdf)) . " WHERE id=$id AND pdf IS NULL");
                $this->store->execute('COMMIT');
                $row = $this->ownStatement($id);
            }
            if ($checkpoint !== null) {
                $checkpoint('after_pdf', $row);
            }
            $pdf = pg_unescape_bytea($row['pdf']);
            if (!hash_equals($row['pdf_sha256'], hash('sha256', $pdf))) {
                throw new RuntimeException('Stored statement digest mismatch');
            }
            $snapshot = json_decode($row['rates_json'], true, 512, JSON_THROW_ON_ERROR);
            $upload = ['client_id'=>$clientId, 'content'=>$pdf, 'sha256'=>$row['pdf_sha256'], 'size'=>strlen($pdf),
                'subject'=>$snapshot['employee_name'], 'accountnr'=>$snapshot['mileage_account'],
                'extracted_data'=>['total_amount'=>$row['amount'], 'invoice_date'=>$row['period_to'], 'currency'=>'DKK',
                    'invoice_description'=>$snapshot['description'], 'suggested_kontonr'=>$snapshot['mileage_account']],
                'publication_claim'=>['kind'=>'koersel', 'statement_id'=>$id, 'ansat_id'=>$row['ansat_id']]];
            $model = new DurableAttachmentModel($this->connection, $this->db, $this->subject);
            try {
                $model->upload($upload, null, function ($connection, array $pool) use ($id, $body, $checkpoint): void {
                    if ($checkpoint !== null) {
                        $checkpoint('before_publish', $pool);
                    }
                    $this->publishStatement($connection, $pool, $id, $body);
                });
            } catch (AttachmentException $error) {
                if ($error->error === 'gone' && $error->getStatusCode() === 410
                    && $row['state'] === 'generating' && $row['pool_file_id'] === null) {
                    // Expiry retains the attachment identity; release reservations before reporting gone.
                    $this->store->execute('BEGIN');
                    $expired = $model->find(['client_id'=>$clientId], true, true);
                    if ($expired['status'] === 'deleted') {
                        mileage_statement_withdraw($this->connection, $id);
                    }
                    $this->store->execute('COMMIT');
                }
                throw $error;
            }
            if ($checkpoint !== null) {
                $checkpoint('after_upload', $this->ownStatement($id));
            }
            $this->store->execute('BEGIN');
            $result = $replay->replay($this->subject, 'mileage.statement.issue', null, $body);
            $this->store->execute('COMMIT');
            // A recovered operation reports replay just like a completed retry.
            if ($previous === null) {
                $result['headers'] = [];
            }
            return $result;
        } catch (Throwable $error) {
            if (pg_connection_status($this->connection) === PGSQL_CONNECTION_OK
                && pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
                $this->store->execute('ROLLBACK');
            }
            throw $error;
        } finally {
            if (pg_connection_status($this->connection) === PGSQL_CONNECTION_OK) {
                $this->store->execute("SELECT pg_advisory_unlock(hashtextextended($lock,0))");
            }
        }
    }

    /**
     * Rebuild replay from immutable statement identity after replay() acquires the issuance lock.
     * @param array<string,mixed> $saved Persisted statement row.
     * @return array{status:int,body:array,headers:array<string,string>}
     */
    private function restoreStatementReplay(Idempotency $replay, array $saved, array $body): array
    {
        $snapshot = json_decode($saved['rates_json'], true, 512, JSON_THROW_ON_ERROR);
        if ($saved['subject'] !== $this->subject || !hash_equals((string)ifset($snapshot, 'request_sha256', ''),
            Idempotency::requestHash('mileage.statement.issue', null, $body))) {
            throw new MobileT2Error('conflict', 409, ['reason'=>'client_id_reused']);
        }
        if ($saved['state'] === 'withdrawn') {
            throw new MobileT2Error('gone', 410);
        }
        $response = ['success'=>true, 'message'=>'', 'data'=>$saved['state'] === 'sent'
            ? ifset($snapshot, 'issued_response') : ['statement_id'=>(int)$saved['id'], 'state'=>'generating']];
        $replay->store($this->subject, 'mileage.statement.issue', null, $body, 201, $response,
            'mileage_statement', (int)$saved['id']);
        return ['status'=>201, 'body'=>$response, 'headers'=>['Idempotent-Replayed'=>'true']];
    }

    /** Finalize only inside the attachment publisher; the pool row is already locked. */
    private function publishStatement($connection, array $pool, int $id, array $body): void
    {
        if ($connection !== $this->connection || pg_transaction_status($connection) !== PGSQL_TRANSACTION_INTRANS) {
            throw new LogicException('Statement publication requires the upload transaction');
        }
        $replay = new Idempotency($connection);
        $previous = $replay->replay($this->subject, 'mileage.statement.issue', null, $body);
        $row = $this->ownStatement($id, true);
        if ($row['state'] === 'withdrawn') {
            throw new MobileT2Error('gone', 410);
        }
        $poolId = (int)$pool['id'];
        if ($pool['uploaded_by'] !== $this->subject || $pool['client_id'] !== $row['client_id']
            || !hash_equals($pool['sha256'], $row['pdf_sha256'])) {
            throw new MobileT2Error('conflict', 409, ['reason'=>'client_id_reused']);
        }
        if ($previous === null) {
            // The upload publication marker may have pruned an aged issuance record between transactions.
            $this->restoreStatementReplay($replay, $row, $body);
        }
        $claims = $this->store->rows("SELECT * FROM mobile_expense_claims WHERE pool_file_id=$poolId");
        if ($claims) {
            $claim = $claims[0];
            if ($claim['kind'] !== 'koersel' || $claim['subject'] !== $this->subject
                || (int)$claim['statement_id'] !== $id || $row['state'] !== 'sent' || (int)$row['pool_file_id'] !== $poolId) {
                throw new MobileT2Error('conflict', 409, ['reason'=>'client_id_reused']);
            }
            return;
        }
        if ($row['state'] !== 'generating' || $pool['status'] !== 'ubehandlet' || $pool['review_state'] !== 'pending') {
            throw new MobileT2Error('conflict', 409, ['reason'=>'attachment_processed']);
        }
        $ansat = $row['ansat_id'] === null ? 'NULL' : (string)(int)$row['ansat_id'];
        $this->store->execute('INSERT INTO mobile_expense_claims(client_id,pool_file_id,kind,subject,ansat_id,statement_id) VALUES ('
            . $this->store->literal($row['client_id']) . ",$poolId,'koersel'," . $this->store->literal($this->subject) . ",$ansat,$id)");
        $row = $this->store->rows("UPDATE mobile_mileage_statements SET pool_file_id=$poolId,state='sent',sent_at=now() WHERE id=$id RETURNING *")[0];
        $data = $this->statementData($row);
        $snapshot = json_decode($row['rates_json'], true, 512, JSON_THROW_ON_ERROR);
        $snapshot['issued_response'] = $data;
        $this->store->execute('UPDATE mobile_mileage_statements SET rates_json='
            . $this->store->literal(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) . " WHERE id=$id");
        $replay->complete($this->subject, 'mileage.statement.issue', null, $body, 201,
            ['success'=>true, 'message'=>'', 'data'=>$data]);
    }

    /** @return array<string,mixed> Subject-scoped statement row, optionally locked. */
    private function ownStatement(int $id, bool $lock = false): array
    {
        $rows = $this->store->rows('SELECT * FROM mobile_mileage_statements WHERE id=' . (int)$id
            . ' AND subject=' . $this->store->literal($this->subject) . ($lock ? ' FOR UPDATE' : ''));
        if (!$rows) {
            throw new MobileT2Error('not_found', 404);
        }
        return $rows[0];
    }

    /**
     * @return array{statement_id:int,km_total:string,amount:string,attachment_id:?int,display_status:string,
     *   lines:list<array{trip_id:int,date:string,km:string,rate:string,amount:string,km_high:string,km_low:string,rate_low:string}>}
     */
    private function statementData(array $row): array
    {
        $display = $row['state'] === 'withdrawn' ? 'slettet' : 'generating';
        if ($row['pool_file_id'] !== null && $row['state'] !== 'withdrawn') {
            $pool = $this->store->rows('SELECT * FROM pool_files WHERE id=' . (int)$row['pool_file_id'])[0];
            $display = (new DurableAttachmentModel($this->connection, $this->db, $this->subject))->toArray($pool)['display_status'];
        }
        return ['statement_id'=>(int)$row['id'], 'km_total'=>$row['km_total'], 'amount'=>$row['amount'],
            'attachment_id'=>$row['pool_file_id'] === null ? null : (int)$row['pool_file_id'], 'display_status'=>$display,
            'lines'=>array_map(static fn(array $line): array => array_intersect_key($line, array_flip(
                ['trip_id', 'date', 'km', 'rate', 'amount', 'km_high', 'km_low', 'rate_low'])),
                json_decode($row['lines_json'], true, 512, JSON_THROW_ON_ERROR))];
    }

    /** @return string Validated optional period filter. */
    private function period(array $query, string $column): string
    {
        $sql = '';
        foreach (['period_from'=>'>=','period_to'=>'<='] as $key=>$operator) {
            if (isset($query[$key])) { $sql .= " AND $column $operator ".$this->store->literal(self::date($query[$key])); }
        }
        if (isset($query['period_from'],$query['period_to']) && $query['period_from'] > $query['period_to']) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'period_invalid']);
        }
        return $sql;
    }

    /** @return array{status:int,body:array,headers:array<string,string>} Atomic domain mutation and replay record. */
    private function mutate(string $operation, $id, array $body, callable $change, int $status = 200): array
    {
        if (pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('ClaimsService owns its transaction');
        }
        $this->store->execute('BEGIN');
        try {
            $response = (new Idempotency($this->connection))->execute($this->subject,$operation,$id,$body,
                static fn(): array => ['status'=>$status,'body'=>['success'=>true,'message'=>'','data'=>$change()]]);
            $this->store->execute('COMMIT');
            return $response;
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        }
    }
}
