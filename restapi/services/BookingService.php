<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/BookingService.php --- ver 5.0.0 --- 2026.10.06
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

require_once __DIR__ . '/../core/EmployeeContext.php';
require_once __DIR__ . '/../core/Rights.php';
require_once __DIR__ . '/../core/ETag.php';
require_once __DIR__ . '/MobileEventOutbox.php';
require_once __DIR__ . '/../core/Subject.php';

/** App appointments use local dates/minutes; only audit stamps represent instants. */
final class BookingService
{
    private MobileSchema $store;

    public function __construct(private $connection, private string $principal, private int $userId, private string $subject)
    {
        $this->store = new MobileSchema($connection);
        $identity = Subject::parse($subject);
        if ($identity['principal'] !== $principal || $identity['user_id'] !== $userId
            || $identity['installation'] !== Subject::installation()) {
            throw new MobileT2Error('permission_denied', 403);
        }
    }

    /** @return EmployeeContext Current live employee, including when the service is used outside HTTP. */
    private function actor(): EmployeeContext
    {
        $actor = new EmployeeContext($this->connection, $this->principal, $this->userId);
        if (!$actor->linked) {
            throw new MobileT2Error('permission_denied', 403, ['reason'=>'no_employee_link']);
        }
        return $actor;
    }

    private function all(EmployeeContext $actor): bool
    {
        $rights = $this->store->rows('SELECT rettigheder FROM brugere WHERE id=' . (int)$this->userId)[0]['rettigheder'];
        return Rights::allows((string)$rights, 'admin', 'write')
            || Rights::allows((string)$rights, 'sager_godkend', 'write', $actor->caseDigits);
    }

    /** @return array{date:string,day_start:string,day_end:string,slot_minutes:int,items:list<array>,summary:array{planned:int,done:int,cancelled:int}} */
    public function day($date = null): array
    {
        $actor = $this->actor();
        $date = $date ?? (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
        $this->date($date);
        $settings = ['booking_day_start'=>'07:00','booking_day_end'=>'17:00','booking_slot_minutes'=>'60'];
        foreach ($this->store->rows("SELECT var_name,var_value FROM settings WHERE var_grp='mobile_marken' AND user_id=0
            AND var_name IN ('booking_day_start','booking_day_end','booking_slot_minutes') ORDER BY id") as $row) {
            $settings[$row['var_name']] = $row['var_value'];
        }
        $start = self::validTime($settings['booking_day_start']) ? $settings['booking_day_start'] : '07:00';
        $end = self::validTime($settings['booking_day_end']) ? $settings['booking_day_end'] : '17:00';
        if ($end <= $start) {
            $start = '07:00';
            $end = '17:00';
        }
        $slot = filter_var($settings['booking_slot_minutes'], FILTER_VALIDATE_INT);
        $slot = $slot !== false && $slot >= 5 && $slot <= 720 ? $slot : 60;
        $rows = $this->store->rows('SELECT * FROM mobile_bookings WHERE booking_date=' . $this->store->literal($date)
            . ' ORDER BY start_time,ansat_id,id');
        $items = [];
        $summary = ['planned'=>0,'done'=>0,'cancelled'=>0];
        $all = $this->all($actor);
        foreach ($rows as $row) {
            $items[] = $this->view($row, $actor, $all);
            $summary[['planlagt'=>'planned','udfoert'=>'done','aflyst'=>'cancelled'][$row['status']]]++;
        }
        return ['date'=>$date,'day_start'=>$start,'day_end'=>$end,'slot_minutes'=>$slot,'items'=>$items,'summary'=>$summary];
    }

    /** @return array{items:list<array{ansat_id:int,name:string,initials:string,is_me:bool}>} */
    public function employees(): array
    {
        $actor = $this->actor();
        $rows = $this->store->rows("SELECT id,navn,initialer FROM ansatte WHERE COALESCE(trim(lukket),'')<>'on'
            AND konto_id=(SELECT id FROM adresser WHERE art='S' ORDER BY id LIMIT 1) ORDER BY navn,id");
        return ['items'=>array_map(static fn(array $row): array => ['ansat_id'=>(int)$row['id'],
            'name'=>(string)$row['navn'],'initials'=>(string)$row['initialer'],'is_me'=>(int)$row['id']===$actor->ansatId], $rows)];
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    public function create(array $body): array
    {
        return $this->mutate('bookings.create', null, $body, null);
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    public function update($id, array $body, ?string $ifMatch): array
    {
        return $this->mutate('bookings.update', self::id($id, 'id'), $body, $ifMatch);
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    public function status(array $body, ?string $ifMatch): array
    {
        return $this->mutate('bookings.status', self::id(ifset($body, 'id'), 'id'), $body, $ifMatch);
    }

    /** One owned transaction contains domain row, history, outbox and replay record. */
    private function mutate(string $operation, ?int $id, array $body, ?string $ifMatch): array
    {
        if (!$this->connection instanceof \PgSql\Connection || pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Booking writes require an idle PostgreSQL connection');
        }
        $actor = $this->actor();
        $all = $this->all($actor);
        $this->store->execute('BEGIN');
        try {
            $result = (new Idempotency($this->connection))->execute($this->subject, $operation, $id, $body,
                function () use ($operation, $id, $body, $ifMatch, $actor, $all): array {
                    $before = null;
                    if ($id !== null) {
                        $rows = $this->store->rows('SELECT * FROM mobile_bookings WHERE id=' . (int)$id . ' FOR UPDATE');
                        if ($rows === []) {
                            throw new MobileT2Error('not_found', 404);
                        }
                        $before = $rows[0];
                        $this->canAssign((int)$before['ansat_id'], $actor, $all);
                        ETag::requireMatch($ifMatch, self::etag($before));
                    }
                    if ($operation === 'bookings.status') {
                        $this->fields($body, ['client_id','id','status']);
                        $status = ifset($body, 'status');
                        if (!in_array($status, ['planlagt','udfoert','aflyst'], true)) {
                            self::invalid('status');
                        }
                        $fields = ['status'=>$status];
                        $event = 'status';
                    } else {
                        $fields = $this->input($body, $before, $actor, $all);
                        $event = $before === null ? 'created' : 'edited';
                        if ($before !== null) {
                            foreach (['booking_date','start_time','duration_minutes','ansat_id'] as $key) {
                                if ((string)$fields[$key] !== (string)$before[$key]) {
                                    $event = 'moved';
                                }
                            }
                        }
                    }
                    $employees = array_unique([(int)ifset($before, 'ansat_id', $fields['ansat_id'] ?? 0),
                        (int)ifset($fields, 'ansat_id', ifset($before, 'ansat_id', 0))]);
                    sort($employees, SORT_NUMERIC);
                    foreach ($employees as $employee) {
                        $this->store->execute('SELECT pg_advisory_xact_lock(hashtext(\'saldi:bookings\'),' . (int)$employee . ')');
                    }
                    if ($before === null) {
                        $fields['client_id'] = Idempotency::clientId($body);
                        $fields['created_by'] = $this->subject;
                        $columns = implode(',', array_keys($fields));
                        $values = implode(',', array_map([$this, 'literal'], array_values($fields)));
                        $after = $this->store->rows("INSERT INTO mobile_bookings($columns) VALUES($values) RETURNING *")[0];
                    } else {
                        $sets = [];
                        foreach ($fields as $key=>$value) {
                            $sets[] = $key . '=' . $this->literal($value);
                        }
                        $sets[] = 'version=version+1';
                        $sets[] = 'updated_at=now()';
                        $sets[] = 'updated_by=' . $this->store->literal($this->subject);
                        if ($operation === 'bookings.status') {
                            $sets[] = $fields['status'] === 'udfoert' ? 'done_at=COALESCE(done_at,now())' : 'done_at=NULL';
                        }
                        $after = $this->store->rows('UPDATE mobile_bookings SET ' . implode(',', $sets)
                            . ' WHERE id=' . (int)$id . ' RETURNING *')[0];
                    }
                    $bookingId = (int)$after['id'];
                    $this->store->execute('INSERT INTO mobile_booking_history(booking_id,event,actor,before_json,after_json) VALUES('
                        . $bookingId . ',' . $this->store->literal($event) . ',' . $this->store->literal($this->subject) . ','
                        . ($before === null ? 'NULL' : $this->store->literal(json_encode($before, JSON_THROW_ON_ERROR))) . ','
                        . $this->store->literal(json_encode($after, JSON_THROW_ON_ERROR)) . ')');
                    $this->emit($after, $event);
                    $data = $this->view($after, $actor, $all);
                    $data['warnings'] = $this->overlap($after);
                    return ['status'=>$before === null ? 201 : 200,'body'=>['success'=>true,'message'=>'','data'=>$data],
                        'resource_type'=>'booking','resource_id'=>$bookingId];
                });
            $this->store->execute('COMMIT');
            return $result;
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        }
    }

    /** @return array<string,int|string|null> Complete editable fields with PATCH defaults from the locked row. */
    private function input(array $body, ?array $before, EmployeeContext $actor, bool $all): array
    {
        $allowed = ['client_id','date','start','duration_minutes','employee_ansat_id','customer','service','case_id','note'];
        $this->fields($body, $allowed);
        if ($before !== null && count($body) === 1) {
            self::invalid('body');
        }
        $date = ifset($body, 'date', ifset($before, 'booking_date'));
        $this->date($date);
        $start = ifset($body, 'start', $before === null ? null : substr($before['start_time'],0,5));
        if (!self::validTime($start)) {
            self::invalid('start');
        }
        $duration = ifset($body, 'duration_minutes', $before === null ? null : (int)$before['duration_minutes']);
        if (!is_int($duration) || $duration < 5 || $duration > 720 || self::minutes($start) + $duration > 1440) {
            self::invalid('duration_minutes');
        }
        $employee = self::id(ifset($body, 'employee_ansat_id', $before === null ? $actor->ansatId : (int)$before['ansat_id']), 'employee_ansat_id');
        $this->canAssign($employee, $actor, $all);
        $employees = $this->store->rows('SELECT id,konto_id,lukket FROM ansatte WHERE id=' . (int)$employee);
        if ($employees === [] || trim((string)$employees[0]['lukket']) === 'on') {
            self::invalid('employee_ansat_id');
        }
        if ($employee !== $actor->ansatId) {
            $company = $this->store->rows("SELECT id FROM adresser WHERE art='S' ORDER BY id LIMIT 1");
            if ($company === [] || (int)$employees[0]['konto_id'] !== (int)$company[0]['id']) {
                self::invalid('employee_ansat_id');
            }
        }
        $customer = ['konto_id'=>ifset($before, 'konto_id'), 'name'=>ifset($before, 'customer_name'),
            'address'=>ifset($before, 'customer_address'), 'phone'=>ifset($before, 'customer_phone')];
        if (array_key_exists('customer', $body)) {
            $input = $body['customer'];
            if ($input instanceof stdClass) {
                $input = (array)$input;
            }
            if (!is_array($input) || array_is_list($input)) {
                self::invalid('customer');
            }
            $this->fields($input, ['konto_id','name','address','phone']);
            if (array_key_exists('konto_id', $input)) {
                $kontoId = $input['konto_id'] === null ? null : self::id($input['konto_id'], 'customer.konto_id');
                $customer['konto_id'] = $kontoId;
                if ($kontoId !== null) {
                    $rows = $this->store->rows("SELECT firmanavn,addr1,addr2,postnr,bynavn,tlf FROM adresser WHERE art='D' AND id=" . (int)$kontoId
                        . " AND COALESCE(trim(lukket),'')<>'on'");
                    if ($rows === []) {
                        self::invalid('customer.konto_id');
                    }
                    $row = $rows[0];
                    $customer = ['konto_id'=>$kontoId,'name'=>$row['firmanavn'],
                        'address'=>trim(implode(' ', array_filter([$row['addr1'],$row['addr2'],$row['postnr'],$row['bynavn']]))), 'phone'=>$row['tlf']];
                }
            }
            $customer = array_replace($customer, $input);
        }
        $customer['konto_id'] = $customer['konto_id'] === null ? null : (int)$customer['konto_id'];
        $caseId = ifset($body, 'case_id', ifset($before, 'sag_id'));
        if ($caseId !== null) {
            $caseId = self::id($caseId, 'case_id');
            if ($this->store->rows('SELECT id FROM sager WHERE id=' . (int)$caseId) === []) {
                self::invalid('case_id');
            }
        }
        return ['booking_date'=>$date,'start_time'=>$start . ':00','duration_minutes'=>$duration,'ansat_id'=>$employee,
            'konto_id'=>$customer['konto_id'], 'customer_name'=>self::text($customer['name'], 'customer.name', 2000),
            'customer_address'=>self::text($customer['address'], 'customer.address', 4000, true),
            'customer_phone'=>self::text($customer['phone'], 'customer.phone', 40, true),
            'service'=>self::text(ifset($body, 'service', ifset($before, 'service')), 'service', 2000),
            'sag_id'=>$caseId, 'note'=>self::text(ifset($body, 'note', ifset($before, 'note')), 'note', 4000, true)];
    }

    private function canAssign(int $employee, EmployeeContext $actor, bool $all): void
    {
        if ($employee !== $actor->ansatId && !$all) {
            throw new MobileT2Error('permission_denied', 403);
        }
    }

    /**
     * Booking contract without subjects, payroll data or storage internals.
     * @return array{id:int,date:string,start:string,end:string,duration_minutes:int,
     *   employee:array{ansat_id:int,name:string,initials:string,is_me:bool},
     *   customer:array{konto_id:?int,name:string,address:?string,phone:?string},service:string,
     *   case:?array{case_id:int,sagsnr:string},note:?string,status:string,can_edit:bool,etag:string}
     */
    private function view(array $row, EmployeeContext $actor, bool $all): array
    {
        $employee = $this->store->rows('SELECT navn,initialer FROM ansatte WHERE id=' . (int)$row['ansat_id']);
        $case = $row['sag_id'] === null ? [] : $this->store->rows('SELECT id,sagsnr FROM sager WHERE id=' . (int)$row['sag_id']);
        $end = self::minutes(substr($row['start_time'],0,5)) + (int)$row['duration_minutes'];
        return ['id'=>(int)$row['id'],'date'=>$row['booking_date'],'start'=>substr($row['start_time'],0,5),
            'end'=>sprintf('%02d:%02d',intdiv($end,60),$end%60),'duration_minutes'=>(int)$row['duration_minutes'],
            'employee'=>['ansat_id'=>(int)$row['ansat_id'],'name'=>(string)ifset($employee,[0,'navn'],''),
                'initials'=>(string)ifset($employee,[0,'initialer'],''),'is_me'=>(int)$row['ansat_id']===$actor->ansatId],
            'customer'=>['konto_id'=>$row['konto_id'] === null ? null : (int)$row['konto_id'], 'name'=>$row['customer_name'],
                'address'=>$row['customer_address'],'phone'=>$row['customer_phone']], 'service'=>$row['service'],
            'case'=>$case === [] ? null : ['case_id'=>(int)$case[0]['id'],'sagsnr'=>(string)$case[0]['sagsnr']],
            'note'=>$row['note'],'status'=>$row['status'],'can_edit'=>$all || (int)$row['ansat_id']===$actor->ansatId,
            'etag'=>self::etag($row)];
    }

    /** @return list<array{code:string,booking_ids:list<int>}> Overlaps are advisory and exclude cancelled rows. */
    private function overlap(array $row): array
    {
        if ($row['status'] === 'aflyst') {
            return [];
        }
        $start = self::minutes(substr($row['start_time'],0,5));
        $end = $start + (int)$row['duration_minutes'];
        $rows = $this->store->rows('SELECT id FROM mobile_bookings WHERE id<>' . (int)$row['id']
            . ' AND ansat_id=' . (int)$row['ansat_id'] . ' AND booking_date=' . $this->store->literal($row['booking_date'])
            . " AND status<>'aflyst' AND extract(epoch FROM start_time)/60<$end"
            . " AND extract(epoch FROM start_time)/60+duration_minutes>$start ORDER BY id");
        return $rows === [] ? [] : [['code'=>'overlap','booking_ids'=>array_map(static fn(array $other): int => (int)$other['id'], $rows)]];
    }

    /** One version event snapshots linked recipients; fan-out still checks their live link and preference. */
    private function emit(array $row, string $event): void
    {
        $recipients = [];
        foreach ($this->store->rows('SELECT id FROM brugere WHERE ansat_id=' . (int)$row['ansat_id'] . ' AND id<>' . (int)$this->userId) as $user) {
            $recipients[] = Subject::build('account', (int)$user['id']);
        }
        if ($recipients === []) {
            return;
        }
        MobileEventOutbox::emit($this->connection, 'booking_assigned', 'booking_assigned:' . (int)$row['id'] . ':' . (int)$row['version'],
            'booking', (int)$row['id'], ['event'=>$event,'status'=>$row['status'],'employee_ansat_id'=>(int)$row['ansat_id'],
                'recipient_subjects'=>$recipients,'actor'=>$this->subject,'service'=>$row['service'],'customer_name'=>$row['customer_name'],
                'date'=>$row['booking_date'],'start'=>substr($row['start_time'],0,5)], null);
    }

    private static function etag(array $row): string
    {
        return '"' . hash('sha256', Idempotency::canonicalJson([(int)$row['id'],(int)$row['version']])) . '"';
    }

    private function literal($value): string
    {
        return $value === null ? 'NULL' : (is_int($value) ? (string)$value : $this->store->literal($value));
    }

    private function fields(array $body, array $allowed): void
    {
        foreach (array_keys($body) as $key) {
            if (!in_array($key, $allowed, true)) {
                self::invalid((string)$key);
            }
        }
    }

    private function date($value): void
    {
        if (!is_string($value) || !preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $value, $parts)
            || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])) {
            self::invalid('date');
        }
    }

    private static function validTime($value): bool
    {
        return is_string($value) && preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value) === 1;
    }

    private static function minutes(string $time): int
    {
        return (int)substr($time,0,2) * 60 + (int)substr($time,3,2);
    }

    private static function id($value, string $field): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)
            || filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value > 2147483647) {
            self::invalid($field);
        }
        return (int)$value;
    }

    private static function text($value, string $field, int $max, bool $nullable = false): ?string
    {
        if ($nullable && $value === null) {
            return null;
        }
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")
            || mb_strlen($value, 'UTF-8') > $max || (!$nullable && trim($value) === '')) {
            self::invalid($field);
        }
        return trim($value);
    }

    private static function invalid(string $field): void
    {
        throw new MobileT2Error('validation_failed', 422, ['reason'=>'booking_invalid','field'=>$field]);
    }
}
