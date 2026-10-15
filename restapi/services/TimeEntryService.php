<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/TimeEntryService.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Preserve omitted payroll on PATCH, remove fiscal coupling and refuse empty resource locks.

require_once __DIR__ . '/legacy/LegacyEngine.php';
require_once __DIR__ . '/../core/EmployeeContext.php';
require_once __DIR__ . '/../core/Rights.php';
require_once __DIR__ . '/../core/Subject.php';
require_once __DIR__ . '/../core/Paging.php';
require_once __DIR__ . '/../core/ETag.php';
require_once __DIR__ . '/../../includes/sager/loenTimer.php';

/** Server-selected identity for non-accounting time operations; no fiscal-year prerequisite. */
final class TimeEntryContext
{
    public function __construct(public $connection, public string $db, public int $dbId,
        public string $username, public int $userId)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $db) || $dbId <= 0 || $userId <= 0 || $username === '') {
            throw new InvalidArgumentException('Invalid time identity');
        }
    }
}

/** Employee-only time registrations; payroll fields stay inside the service. */
final class TimeEntryService
{
    private MobileSchema $store;

    public function __construct(private EngineContext|TimeEntryContext $context, private string $principal, private string $subject)
    {
        $this->store = new MobileSchema($context->connection);
        $identity = Subject::parse($subject);
        if ($identity['installation'] !== Subject::installation() || $identity['principal'] !== $principal
            || $identity['user_id'] !== $context->userId) {
            throw new MobileT2Error('permission_denied', 403);
        }
    }

    /** Live case login permission is required even for direct service calls. @return EmployeeContext */
    private function actor(string $action = 'read'): EmployeeContext
    {
        $actor = new EmployeeContext($this->context->connection, $this->principal, $this->context->userId);
        if (!$actor->linked) {
            throw new MobileT2Error('permission_denied', 403, ['reason'=>'no_employee_link']);
        }
        $users = $this->store->rows('SELECT rettigheder FROM brugere WHERE id=' . (int)$actor->userId);
        if (!Rights::allows((string)$users[0]['rettigheder'], 'sager_loen', $action, $actor->caseDigits)) {
            throw new MobileT2Error('permission_denied', 403);
        }
        return $actor;
    }

    /** @return array{items:list<array>,total:int,limit:int,offset:int} Active cases in stable number/id order. */
    public function cases(array $query = []): array
    {
        $this->actor();
        $paging = new Paging($query);
        $where = "status NOT IN ('Ordrebekræftelse','Tilbud','Beregning','Afsluttet')";
        $q = ifset($query, 'q', '');
        if (!is_string($q) || mb_strlen($q) > 200) {
            self::invalid('query_invalid');
        }
        if ($q !== '') {
            $search = $this->store->literal('%' . str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $q) . '%');
            $where .= " AND (sagsnr ILIKE $search OR beskrivelse ILIKE $search OR udf_addr1 ILIKE $search OR firmanavn ILIKE $search)";
        }
        $total = (int)$this->store->rows("SELECT count(*) AS n FROM sager WHERE $where")[0]['n'];
        $rows = $this->store->rows("SELECT id,sagsnr,beskrivelse,firmanavn,udf_addr1,udf_addr2,udf_postnr,udf_bynavn,status
            FROM sager WHERE $where ORDER BY sagsnr,id " . $paging->sql());
        $items = array_map(static fn(array $row): array => ['case_id'=>(int)$row['id'], 'sagsnr'=>(string)$row['sagsnr'],
            'description'=>(string)$row['beskrivelse'], 'customer_name'=>(string)$row['firmanavn'],
            'address'=>trim(implode(' ', array_filter([$row['udf_addr1'],$row['udf_addr2'],$row['udf_postnr'],$row['udf_bynavn']]))),
            'status'=>(string)$row['status']], $rows);
        return $paging->response($items, $total);
    }

    /** @return array{items:list<array>,total:int,limit:int,offset:int} Active tasks of an active case only. */
    public function tasks($caseId, array $query = []): array
    {
        $this->actor();
        $id = self::id($caseId, 'case_inactive');
        $this->activeCase($id);
        $paging = new Paging($query);
        $where = "assign_to='sager' AND assign_id=$id AND status NOT IN ('Ordrebekræftelse','Tilbud','Afsluttet')";
        $total = (int)$this->store->rows("SELECT count(*) AS n FROM opgaver WHERE $where")[0]['n'];
        $rows = $this->store->rows("SELECT id,nr,beskrivelse,status FROM opgaver WHERE $where ORDER BY nr,id " . $paging->sql());
        return $paging->response(array_map([self::class,'taskView'], $rows), $total);
    }

    /** @return array{items:list<array{code:string,label:string}>} Labels only, never the configured rates. */
    public function hourTypes(): array
    {
        $this->actor();
        return $this->read(static function (): array {
            $types = loen_rates()['hour_types'];
            ksort($types, SORT_STRING);
            $items = [];
            foreach ($types as $code=>$type) {
                $items[] = ['code'=>(string)$code, 'label'=>$type['label']];
            }
            return ['items'=>$items];
        });
    }

    /** @return array{items:list<array>,total:int,limit:int,offset:int,totals:array{hours:string}} Own hours, with totals over the full filter. */
    public function list(array $query = []): array
    {
        $actor = $this->actor();
        $paging = new Paging($query);
        $filter = $this->filter($query);
        return $this->read(function () use ($actor, $paging, $filter): array {
            $where = ["art='timer'", $this->store->literal((string)$actor->ansatId) . ' = ANY(string_to_array(ansatte,chr(9)))'];
            foreach (['date_from'=>'loendate >=','date_to'=>'loendate <=','sag_id'=>'sag_id ='] as $key=>$expression) {
                if (array_key_exists($key, $filter)) {
                    $where[] = $expression . ' ' . $this->store->literal((string)$filter[$key]);
                }
            }
            $condition = implode(' AND ', $where);
            $index = 'array_position(string_to_array(ansatte,chr(9)),' . $this->store->literal((string)$actor->ansatId) . ')';
            $totals = $this->store->rows("SELECT count(*) AS n,COALESCE(sum(NULLIF((string_to_array(timer,chr(9)))[$index],'')::numeric),0) AS hours
                FROM loen WHERE $condition")[0];
            $rows = loen_employee_rows($actor->ansatId, $filter + ['limit'=>$paging->limit,'offset'=>$paging->offset]);
            $items = array_map(fn(array $row): array => $this->view($row, $actor), $rows);
            return $paging->response($items, (int)$totals['n']) + ['totals'=>['hours'=>self::hours($totals['hours'])]];
        });
    }

    /** @return array<string,mixed> Own entry, or 404 without exposing membership. */
    public function detail($id): array
    {
        $actor = $this->actor();
        $id = self::id($id);
        return $this->read(fn(): array => $this->view($this->ownRow($id, $actor), $actor));
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    public function create(array $body): array
    {
        return $this->mutate('time.entry.create', null, $body, null);
    }

    /** @return array{status:int,body:array,headers:array<string,string>} PATCH defaults from the locked own entry. */
    public function update($id, array $body, ?string $ifMatch): array
    {
        return $this->mutate('time.entry.update', self::id($id), $body, $ifMatch);
    }

    /** @return array{status:int,body:array,headers:array<string,string>} Delete payroll row, retaining the app tombstone. */
    public function delete($id, array $body, ?string $ifMatch): array
    {
        return $this->mutate('time.entry.delete', self::id($id), $body, $ifMatch);
    }

    /** @return array{status:int,body:array,headers:array<string,string>} Bulk transfer is atomic; If-Match contains each selected entry's validator. */
    public function submit(array $body, ?string $ifMatch): array
    {
        return $this->mutate('time.entry.submit', null, $body, $ifMatch);
    }

    /** The extracted timer helpers need only a connection and never post accounting or own transactions. @return array */
    private function run(callable $operation): array
    {
        $hadConnection = array_key_exists('connection', $GLOBALS);
        $previous = $GLOBALS['connection'] ?? null;
        $GLOBALS['connection'] = $this->context->connection;
        try {
            return $operation();
        } finally {
            if ($hadConnection) {
                $GLOBALS['connection'] = $previous;
            } else {
                unset($GLOBALS['connection']);
            }
        }
    }

    /** Keep visible data and validators in one snapshot when the web changes a seddel during a read. @return array */
    private function read(callable $operation): array
    {
        if (pg_transaction_status($this->context->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Time reads require an idle PostgreSQL connection');
        }
        $this->store->execute('BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
        try {
            $result = $this->run($operation);
            $this->store->execute('COMMIT');
            return $result;
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        }
    }

    /** Replay, locks, payroll writes and app metadata share one owned transaction. */
    private function mutate(string $operation, ?int $id, array $body, ?string $ifMatch): array
    {
        $actor = $this->actor('write');
        $connection = $this->context->connection;
        if (pg_transaction_status($connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Time writes require an idle PostgreSQL connection');
        }
        $this->store->execute('BEGIN');
        try {
            $result = (new Idempotency($connection))->execute($this->subject, $operation, $id, $body,
                fn(): array => $this->run(function () use ($operation, $id, $body, $ifMatch, $actor): array {
                    if ($operation === 'time.entry.submit') {
                        $this->fields($body, ['client_id','ids']);
                        $ids = ifset($body, 'ids');
                        if (!is_array($ids) || !array_is_list($ids) || count($ids) < 1 || count($ids) > 1000) {
                            self::invalid('ids_invalid');
                        }
                        $ids = array_map(fn($value): int => self::id($value), $ids);
                        if (count(array_unique($ids)) !== count($ids)) {
                            self::invalid('ids_invalid');
                        }
                        $sorted = $ids;
                        sort($sorted, SORT_NUMERIC);
                        foreach ($sorted as $entryId) {
                            $row = $this->lockEditable($entryId, $actor);
                            ETag::requireMatch($ifMatch, $this->etag($entryId, $actor));
                            $this->input($this->inputDefaults($row), $actor);
                        }
                        foreach ($sorted as $entryId) {
                            loen_timer_query("UPDATE loen SET afsluttet=extract(epoch FROM clock_timestamp())::bigint::text,afsluttet_af=$1 WHERE id=$2",
                                [$this->context->username,$entryId]);
                            loen_timer_query('UPDATE mobile_time_entries SET submitted_at=now() WHERE loen_id=$1', [$entryId]);
                        }
                        $data = ['items'=>array_map(fn(int $entryId): array => $this->view($this->ownRow($entryId, $actor), $actor), $ids)];
                    } elseif ($operation === 'time.entry.delete') {
                        $this->fields($body, ['client_id']);
                        $this->lockEditable($id, $actor);
                        ETag::requireMatch($ifMatch, $this->etag($id, $actor));
                        loen_timer_query('DELETE FROM loen WHERE id=$1', [$id]);
                        loen_timer_query('UPDATE mobile_time_entries SET deleted_at=now() WHERE loen_id=$1', [$id]);
                        $data = ['id'=>$id, 'deleted'=>true];
                    } else {
                        $before = $id === null ? null : $this->lockEditable($id, $actor);
                        if ($id !== null) {
                            ETag::requireMatch($ifMatch, $this->etag($id, $actor));
                        }
                        $allowed = ['client_id','case_id','task_id','date','hours','overtime_50','overtime_100','hour_type','text','timer'];
                        $this->fields($body, $allowed);
                        $input = $before === null ? $body : array_replace($this->inputDefaults($before), $body);
                        $fields = $this->input($input, $actor);
                        $timer = array_key_exists('timer', $body) ? $this->timer($body['timer']) : null;
                        if ($id === null) {
                            $fields += $this->creationDefaults();
                            $fields += ['nummer'=>loen_next_number(),'art'=>'timer','kategori'=>0,'hvem'=>'',
                                'oprettet'=>(string)time(),'oprettet_af'=>$this->context->username,'afsluttet'=>'','afsluttet_af'=>'',
                                'godkendt'=>'','afregnet'=>'','afvist'=>'','afvist_af'=>'','afvist_pga'=>'','feriefra'=>'','ferietil'=>''];
                            if (loen_timer_query('SELECT loen_id FROM mobile_time_entries WHERE client_id=$1', [Idempotency::clientId($body)]) !== []) {
                                throw new MobileT2Error('conflict', 409, ['reason'=>'client_id_reused']);
                            }
                            $id = loen_seddel_insert($fields);
                            loen_timer_query('INSERT INTO mobile_time_entries(loen_id,client_id,subject,ansat_id,timer_started_at,timer_stopped_at)
                                VALUES($1,$2,$3,$4,$5,$6)', [$id,Idempotency::clientId($body),$this->subject,$actor->ansatId,
                                $timer['started_at'] ?? null,$timer['stopped_at'] ?? null]);
                        } else {
                            $fields = $this->updateFields($id, $fields, $body);
                            $sets = [];
                            $params = [];
                            foreach ($fields as $column=>$value) {
                                $params[] = $value;
                                $sets[] = $column . '=$' . count($params);
                            }
                            $params[] = $id;
                            if ($sets !== []) {
                                loen_timer_query('UPDATE loen SET ' . implode(',', $sets) . ' WHERE id=$' . count($params), $params);
                            }
                            if ($timer !== null) {
                                loen_timer_query('UPDATE mobile_time_entries SET timer_started_at=$1,timer_stopped_at=$2 WHERE loen_id=$3',
                                    [$timer['started_at'],$timer['stopped_at'],$id]);
                            }
                        }
                        $data = $this->view($this->ownRow($id, $actor), $actor);
                    }
                    return ['status'=>$operation === 'time.entry.create' ? 201 : 200,
                        'body'=>['success'=>true,'message'=>'','data'=>$data]];
                }));
            if (isset($result['headers']['Idempotent-Replayed'])) {
                $this->checkReplayOwnership($result['body']['data'], $actor);
            }
            $this->store->execute('COMMIT');
            return $result;
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        }
    }

    /** A changed login-to-employee link must never replay another employee's cached hours. */
    private function checkReplayOwnership(array $data, EmployeeContext $actor): void
    {
        $items = ifset($data, 'items', [$data]);
        foreach ($items as $item) {
            $id = (int)$item['id'];
            $markers = $this->store->rows('SELECT ansat_id FROM mobile_time_entries WHERE loen_id=' . $id);
            if ($markers === [] || (int)$markers[0]['ansat_id'] !== $actor->ansatId) {
                throw new MobileT2Error('not_found', 404);
            }
            if (!ifset($item, 'deleted', false)) {
                $rows = $this->store->rows("SELECT id FROM loen WHERE id=$id AND art='timer' AND "
                    . $this->store->literal((string)$actor->ansatId) . ' = ANY(string_to_array(ansatte,chr(9)))');
                if ($rows === []) {
                    throw new MobileT2Error('not_found', 404);
                }
            }
        }
    }

    /** @return array<string,int|float|string|null> Characterized single-employee timer packing. */
    private function input(array $body, EmployeeContext $actor): array
    {
        $errors = loen_timer_validate($body);
        if ($errors !== []) {
            self::invalid($errors[0]);
        }
        $case = $this->activeCase(self::id(ifset($body, 'case_id'), 'case_inactive'));
        $taskId = self::id(ifset($body, 'task_id'), 'task_required');
        $tasks = loen_timer_query("SELECT id,nr FROM opgaver WHERE id=$1 AND assign_to='sager' AND assign_id=$2
            AND status NOT IN ('Ordrebekræftelse','Tilbud','Afsluttet')", [$taskId,(int)$case['id']]);
        if ($tasks === []) {
            self::invalid('task_required');
        }
        foreach (['hours','overtime_50','overtime_100'] as $key) {
            $value = ifset($body, $key, '0.00');
            if (!is_string($value) || !preg_match('/^[0-9]{1,9}(?:\.[0-9]{1,2})?$/D', $value) || ($key === 'hours' && (float)$value > 24)) {
                self::invalid('hours_invalid');
            }
        }
        if (mb_strlen($body['text']) > 4000) {
            self::invalid('text_required');
        }
        $rates = loen_rates();
        $type = ifset($body, 'hour_type');
        if ($type !== null && (!is_string($type) || !array_key_exists($type, $rates['hour_types']))) {
            self::invalid('hour_type_invalid');
        }
        $employee = loen_timer_query('SELECT loen,trainee,startdate FROM ansatte WHERE id=$1', [$actor->ansatId])[0];
        $amount = loen_timer_amount($employee, $body['hours'], ifset($body,'overtime_50','0.00'), ifset($body,'overtime_100','0.00'), $type, $rates);
        if (!is_finite((float)$amount['amount']) || abs((float)$amount['amount']) >= 1000000000000) {
            self::invalid('hours_invalid');
        }
        return ['sag_id'=>(int)$case['id'],'sag_nr'=>(int)$case['sagsnr'],'sag_ref'=>(string)$case['ref'],
            'opg_id'=>$taskId,'opg_nr'=>$tasks[0]['nr'],'loendate'=>$body['date'],'tekst'=>$body['text'],
            'ansatte'=>(string)$actor->ansatId,'fordeling'=>(string)loen_fordeling($actor->ansatId,$employee['startdate'],$body['date'],(bool)$employee['trainee']),
            'loen'=>$amount['rate'],'sum'=>$amount['amount'],'timer'=>(string)(float)$body['hours'],
            't50pct'=>self::packedHours(ifset($body,'overtime_50','0')),'t100pct'=>self::packedHours(ifset($body,'overtime_100','0')),
            'hourtype'=>$type ?? ''];
    }

    /** @return array{skur:string,korsel:string,datoer:string,mentor:string,mentor_rate:int} Defaults apply only to a new seddel. */
    private function creationDefaults(): array
    {
        $groups = loen_timer_query("SELECT box6 FROM grupper WHERE art='loen'");
        $km = explode(chr(9), (string)ifset($groups, [0,'box6'], ''));
        return ['skur'=>'0|0','korsel'=>'0|' . ifset($km,0,'') . '|' . ifset($km,1,''),
            'datoer'=>'','mentor'=>'0.00','mentor_rate'=>0];
    }

    /**
     * Write only supplied app fields. Adjust the saved total by the change in hour pay,
     * retaining web-added shed, mentor and mileage (including its daily allowance calculation).
     * Private payroll values are read only after the single-employee resource lock is confirmed.
     * @param array<string,int|float|string|null> $fields
     * @param array<string,mixed> $body
     * @return array<string,int|float|string|null>
     */
    private function updateFields(int $id, array $fields, array $body): array
    {
        $columns = ['case_id'=>['sag_id','sag_nr','sag_ref'], 'task_id'=>['opg_id','opg_nr'],
            'date'=>['loendate','fordeling'], 'hours'=>['timer','loen'], 'overtime_50'=>['t50pct'],
            'overtime_100'=>['t100pct'], 'hour_type'=>['hourtype','loen'], 'text'=>['tekst']];
        $updates = [];
        foreach ($columns as $key=>$names) {
            if (array_key_exists($key, $body)) {
                foreach ($names as $name) {
                    $updates[$name] = $fields[$name];
                }
            }
        }
        if (array_intersect(['hours','overtime_50','overtime_100','hour_type'], array_keys($body)) !== []) {
            $saved = loen_timer_query('SELECT timer,loen,t50pct,t100pct,sum FROM loen WHERE id=$1', [$id])[0];
            $rates = loen_rates();
            $total = (float)$saved['sum'];
            if (isset($updates['loen'])) {
                $hours = (float)ifset($updates, 'timer', $saved['timer']);
                $total += $hours * (float)$updates['loen'] - (float)$saved['timer'] * (float)$saved['loen'];
            }
            foreach (['t50pct'=>'overtime_50','t100pct'=>'overtime_100'] as $column=>$rate) {
                if (isset($updates[$column])) {
                    $total += ((float)$updates[$column] - (float)$saved[$column]) * (float)$rates[$rate];
                }
            }
            if (!is_finite($total) || abs($total) >= 1000000000000) {
                self::invalid('hours_invalid');
            }
            $updates['sum'] = (string)$total;
        }
        return $updates;
    }

    /** @return array<string,mixed> Own projected row after acquiring the payroll resource lock. */
    private function lockEditable(int $id, EmployeeContext $actor): array
    {
        $locked = loen_timer_query("SELECT id FROM loen WHERE id=$1 AND art='timer'
            AND $2 = ANY(string_to_array(ansatte,chr(9))) FOR UPDATE", [$id,(string)$actor->ansatId]);
        if ($locked === []) {
            throw new MobileT2Error('not_found', 404);
        }
        $row = $this->ownRow($id, $actor);
        $reason = $this->readOnly($row, $actor);
        if ($reason !== null) {
            throw new MobileT2Error('conflict', 409, ['reason'=>'entry_read_only','read_only_reason'=>$reason]);
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function ownRow(int $id, EmployeeContext $actor): array
    {
        $rows = loen_employee_rows($actor->ansatId, ['id'=>$id]);
        if ($rows === []) {
            throw new MobileT2Error('not_found', 404);
        }
        return $rows[0];
    }

    /** @return array<string,mixed>|null Live app provenance; the employee link remains the access-control scope. */
    private function marker(int $id, EmployeeContext $actor): ?array
    {
        $rows = $this->store->rows('SELECT loen_id,deleted_at,submitted_at FROM mobile_time_entries WHERE loen_id=' . (int)$id
            . ' AND ansat_id=' . (int)$actor->ansatId . ' AND deleted_at IS NULL');
        return ifset($rows, 0);
    }

    private function readOnly(array $row, EmployeeContext $actor): ?string
    {
        if ($this->marker((int)$row['id'], $actor) === null || $row['employee_count'] !== 1) {
            return 'web_seddel';
        }
        foreach (['afsluttet','godkendt','afvist','afregnet','udbetalt'] as $key) {
            if (trim((string)$row[$key]) !== '' && trim((string)$row[$key]) !== '0') {
                return 'submitted';
            }
        }
        return $this->marker((int)$row['id'], $actor)['submitted_at'] !== null ? 'submitted' : null;
    }

    /** @return array{id:int,nummer:int,case:array,task:array,date:string,hours:string,overtime_50:string,overtime_100:string,hour_type:?string,text:string,state:string,rejection:?array,created_in_app:bool,editable:bool,read_only_reason:?string,etag:string} */
    private function view(array $row, EmployeeContext $actor): array
    {
        $case = $this->store->rows('SELECT id,sagsnr,beskrivelse FROM sager WHERE id=' . (int)$row['sag_id']);
        $task = $this->store->rows('SELECT id,nr,beskrivelse,status FROM opgaver WHERE id=' . (int)$row['opg_id']);
        $state = 'kladde';
        foreach (['afsluttet'=>'overfoert','godkendt'=>'godkendt','afvist'=>'afvist','afregnet'=>'afregnet','udbetalt'=>'afregnet'] as $stamp=>$name) {
            if ((int)$row[$stamp] > 0) {
                $state = $name;
            }
        }
        $reason = $this->readOnly($row, $actor);
        $rejection = null;
        if ($state === 'afvist') {
            $users = $this->store->rows('SELECT a.navn FROM brugere b LEFT JOIN ansatte a ON a.id=b.ansat_id
                WHERE b.brugernavn=' . $this->store->literal((string)$row['afvist_af']));
            $rejection = ['reason'=>(string)$row['afvist_pga'],'by_name'=>(string)ifset($users,[0,'navn'],$row['afvist_af']),
                'at'=>(new DateTimeImmutable('@' . (int)$row['afvist']))->format(DATE_ATOM)];
        }
        return ['id'=>(int)$row['id'],'nummer'=>(int)$row['nummer'],
            'case'=>['case_id'=>(int)$row['sag_id'],'sagsnr'=>(string)ifset($case,[0,'sagsnr'],$row['sag_nr']),
                'description'=>(string)ifset($case,[0,'beskrivelse'],'')],
            'task'=>$task === [] ? ['task_id'=>(int)$row['opg_id'],'nr'=>(string)$row['opg_nr'],'description'=>'','status'=>''] : self::taskView($task[0]),
            'date'=>$row['loendate'],'hours'=>self::hours($row['timer']),'overtime_50'=>self::hours($row['t50pct']),
            'overtime_100'=>self::hours($row['t100pct']),'hour_type'=>$row['hourtype'] === '' ? null : $row['hourtype'],
            'text'=>(string)$row['tekst'],'state'=>$state,'rejection'=>$rejection,
            'created_in_app'=>$this->marker((int)$row['id'], $actor) !== null,'editable'=>$reason === null,'read_only_reason'=>$reason,
            'etag'=>$this->etag((int)$row['id'], $actor)];
    }

    /** Validate own hours, membership count and state; wages never enter even the response's hash. */
    private function etag(int $id, EmployeeContext $actor): string
    {
        $row = $this->ownRow($id, $actor);
        $marker = $this->store->rows('SELECT loen_id,ansat_id,timer_started_at,timer_stopped_at,created_at,submitted_at,deleted_at
            FROM mobile_time_entries WHERE loen_id=' . (int)$id);
        return '"' . hash('sha256', Idempotency::canonicalJson([$row,ifset($marker,0)])) . '"';
    }

    /** @return array<string,mixed> */
    private function inputDefaults(array $row): array
    {
        return ['case_id'=>(int)$row['sag_id'],'task_id'=>(int)$row['opg_id'],'date'=>$row['loendate'],
            'hours'=>self::hours($row['timer']),'overtime_50'=>self::hours($row['t50pct']),'overtime_100'=>self::hours($row['t100pct']),
            'hour_type'=>$row['hourtype'] === '' ? null : $row['hourtype'],'text'=>(string)$row['tekst']];
    }

    /** @return array<string,mixed> */
    private function activeCase(int $id): array
    {
        $rows = $this->store->rows("SELECT id,sagsnr,ref FROM sager WHERE id=" . (int)$id
            . " AND status NOT IN ('Ordrebekræftelse','Tilbud','Beregning','Afsluttet')");
        if ($rows === []) {
            self::invalid('case_inactive');
        }
        return $rows[0];
    }

    /** @return array{task_id:int,nr:string,description:string,status:string} */
    private static function taskView(array $row): array
    {
        return ['task_id'=>(int)$row['id'],'nr'=>(string)$row['nr'],'description'=>(string)$row['beskrivelse'],'status'=>(string)$row['status']];
    }

    /** @return array{date_from?:string,date_to?:string,sag_id?:int} */
    private function filter(array $query): array
    {
        $filter = [];
        // The T4 app uses from/to; date_from/date_to and ISO week remain useful direct API selectors.
        foreach (['from'=>'date_from','to'=>'date_to'] as $alias=>$key) {
            if (array_key_exists($alias, $query)) {
                if (array_key_exists($key, $query) && $query[$key] !== $query[$alias]) {
                    self::invalid('query_invalid');
                }
                $query[$key] = $query[$alias];
            }
        }
        foreach (['date_from','date_to'] as $key) {
            if (array_key_exists($key, $query)) {
                self::date($query[$key]);
                $filter[$key] = $query[$key];
            }
        }
        if (array_key_exists('week', $query)) {
            if (count($filter) > 0 || !is_string($query['week']) || !preg_match('/^([0-9]{4})-W([0-9]{2})$/D', $query['week'], $parts)) {
                self::invalid('query_invalid');
            }
            $monday = (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->setISODate((int)$parts[1],(int)$parts[2],1);
            if ((int)$parts[1] < 1 || $monday->format('o-\WW') !== $query['week']) {
                self::invalid('query_invalid');
            }
            $filter['date_from'] = $monday->format('Y-m-d');
            $filter['date_to'] = $monday->modify('+6 days')->format('Y-m-d');
        }
        if (isset($filter['date_from'],$filter['date_to']) && $filter['date_from'] > $filter['date_to']) {
            self::invalid('query_invalid');
        }
        if (array_key_exists('case_id', $query)) {
            $filter['sag_id'] = self::id($query['case_id'], 'query_invalid');
        }
        return $filter;
    }

    /** @return array{started_at:string,stopped_at:string} Informational instants require explicit timezone offsets. */
    private function timer($value): array
    {
        if ($value instanceof stdClass) {
            $value = (array)$value;
        }
        if (!is_array($value) || array_diff(array_keys($value), ['started_at','stopped_at'])) {
            self::invalid('timer_invalid');
        }
        $result = [];
        foreach (['started_at','stopped_at'] as $key) {
            $stamp = ifset($value, $key);
            if (!is_string($stamp) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $stamp)) {
                self::invalid('timer_invalid');
            }
            try {
                $parsed = new DateTimeImmutable($stamp);
            } catch (Exception $error) {
                self::invalid('timer_invalid');
            }
            if ((int)$parsed->format('Y') < 1 || abs($parsed->getOffset()) > 14 * 3600
                || $parsed->format('Y-m-d\TH:i:s') !== substr($stamp,0,19)) {
                self::invalid('timer_invalid');
            }
            $result[$key] = $parsed->format('Y-m-d\TH:i:s.uP');
        }
        if (new DateTimeImmutable($result['stopped_at']) < new DateTimeImmutable($result['started_at'])) {
            self::invalid('timer_invalid');
        }
        return $result;
    }

    private function fields(array $body, array $allowed): void
    {
        if (array_diff(array_keys($body), $allowed)) {
            self::invalid('unknown_field');
        }
    }

    private static function id($value, string $reason = 'id_invalid'): int
    {
        if ((!is_int($value) && (!is_string($value) || !preg_match('/^[1-9][0-9]*$/D', $value)))
            || strlen((string)$value) > 10 || (int)$value < 1 || (int)$value > 2147483647) {
            self::invalid($reason);
        }
        return (int)$value;
    }

    private static function date($value): void
    {
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts)
            || !checkdate((int)$parts[2],(int)$parts[3],(int)$parts[1])) {
            self::invalid('date_missing');
        }
    }

    /** The web normalizes positive overtime numerically and renders a zero as 0.00 on the saved form. */
    private static function packedHours($value): string
    {
        return (float)$value > 0 ? (string)(float)$value : '0.00';
    }

    private static function hours($value): string
    {
        return number_format((float)$value,2,'.','');
    }

    private static function invalid(string $reason): void
    {
        throw new MobileT2Error('validation_failed', 422, ['reason'=>$reason]);
    }
}
